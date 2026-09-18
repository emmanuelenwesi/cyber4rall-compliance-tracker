<?php
require_once __DIR__ . '/db.php';

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function slugify(string $text): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
    return $slug !== '' ? $slug : 'company';
}

/**
 * Answer scoring: yes = full weight, partial = half weight, no/unanswered = 0.
 */
function answer_points(?string $answer, int $weight): float
{
    return match ($answer) {
        'yes' => $weight,
        'partial' => $weight * 0.5,
        default => 0.0,
    };
}

/**
 * Compute overall + per-category compliance scores for a company.
 * Returns ['overall' => float, 'categories' => [ [id,name,score,answered,total], ... ] ]
 */
function compute_scores(int $companyId): array
{
    $conn = db();

    $sql = "SELECT c.id AS category_id, c.name AS category_name, c.sort_order,
                   q.id AS question_id, q.weight,
                   r.answer
            FROM categories c
            JOIN questions q ON q.category_id = c.id
            LEFT JOIN responses r ON r.question_id = q.id AND r.company_id = ?
            ORDER BY c.sort_order, q.sort_order";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();

    $categories = [];
    $totalWeight = 0.0;
    $totalPoints = 0.0;

    while ($row = $result->fetch_assoc()) {
        $catId = (int)$row['category_id'];
        if (!isset($categories[$catId])) {
            $categories[$catId] = [
                'id' => $catId,
                'name' => $row['category_name'],
                'weight' => 0.0,
                'points' => 0.0,
                'total_questions' => 0,
                'answered_questions' => 0,
            ];
        }
        $weight = (float)$row['weight'];
        $points = answer_points($row['answer'], (int)$row['weight']);

        $categories[$catId]['weight'] += $weight;
        $categories[$catId]['points'] += $points;
        $categories[$catId]['total_questions']++;
        if ($row['answer'] !== null) {
            $categories[$catId]['answered_questions']++;
        }

        $totalWeight += $weight;
        $totalPoints += $points;
    }
    $stmt->close();

    foreach ($categories as &$cat) {
        $cat['score'] = $cat['weight'] > 0 ? round(($cat['points'] / $cat['weight']) * 100, 1) : 0.0;
    }
    unset($cat);

    $overall = $totalWeight > 0 ? round(($totalPoints / $totalWeight) * 100, 1) : 0.0;

    return [
        'overall' => $overall,
        'categories' => array_values($categories),
    ];
}

function record_score_snapshot(int $companyId, float $score): void
{
    $conn = db();
    $stmt = $conn->prepare('INSERT INTO score_history (company_id, score) VALUES (?, ?)');
    $stmt->bind_param('id', $companyId, $score);
    $stmt->execute();
    $stmt->close();
}

/**
 * Ensure a remediation item exists for every "no" or "partial" answer,
 * and auto-close remediation items whose question was answered "yes".
 */
function sync_remediation_items(int $companyId): void
{
    $conn = db();

    $sql = "SELECT q.id AS question_id, q.prompt, c.name AS category_name, r.answer
            FROM questions q
            JOIN categories c ON c.id = q.category_id
            LEFT JOIN responses r ON r.question_id = q.id AND r.company_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $qid = (int)$row['question_id'];

        $check = $conn->prepare('SELECT id, status FROM remediation_items WHERE company_id = ? AND question_id = ?');
        $check->bind_param('ii', $companyId, $qid);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        $needsGap = in_array($row['answer'], ['no', 'partial'], true);

        if ($needsGap && !$existing) {
            $title = 'Address gap: ' . $row['prompt'];
            $details = 'Category: ' . $row['category_name'];
            $ins = $conn->prepare('INSERT INTO remediation_items (company_id, question_id, title, details, status) VALUES (?, ?, ?, ?, "open")');
            $ins->bind_param('iiss', $companyId, $qid, $title, $details);
            $ins->execute();
            $ins->close();
        } elseif (!$needsGap && $existing && $existing['status'] !== 'done' && $row['answer'] === 'yes') {
            $upd = $conn->prepare('UPDATE remediation_items SET status = "done" WHERE id = ?');
            $upd->bind_param('i', $existing['id']);
            $upd->execute();
            $upd->close();
        }
    }
}

function get_company(int $companyId): ?array
{
    $conn = db();
    $stmt = $conn->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Whether a company currently has access to the app: either an active,
 * unexpired subscription, or a live trial.
 */
function has_active_access(array $company): bool
{
    $now = new DateTime();

    if ($company['subscription_status'] === 'active') {
        if (empty($company['current_period_end'])) return true; // safety net
        return new DateTime($company['current_period_end']) >= $now;
    }

    if ($company['subscription_status'] === 'trial') {
        if (empty($company['trial_ends_at'])) return false;
        return new DateTime($company['trial_ends_at']) >= $now;
    }

    return false; // past_due or cancelled
}

/**
 * Gate the core app behind an active subscription/trial.
 * Platform admins (Cyber4rAll staff) always pass through.
 * Call this after require_login() on any page that should be paywalled.
 */
function require_active_access(array $user): void
{
    if (!empty($user['is_platform_admin'])) return;

    $company = get_company($user['company_id']);
    if (!$company || !has_active_access($company)) {
        header('Location: /billing.php');
        exit;
    }
}

/**
 * The raw subscription_status column doesn't tell the whole story once
 * cancellation-at-period-end is involved; this derives what to actually
 * show/label the company as right now.
 */
function effective_status(array $company): string
{
    $now = new DateTime();

    if ($company['subscription_status'] === 'trial') {
        return has_active_access($company) ? 'trial' : 'trial_expired';
    }

    if ($company['subscription_status'] === 'active') {
        $periodEnd = !empty($company['current_period_end']) ? new DateTime($company['current_period_end']) : null;
        $cancelsAt = !empty($company['cancels_at']) ? new DateTime($company['cancels_at']) : null;

        if ($cancelsAt && $cancelsAt <= $now) return 'cancelled';
        if ($periodEnd && $periodEnd < $now) return 'expired';
        if ($cancelsAt) return 'cancelling';
        return 'active';
    }

    return $company['subscription_status']; // past_due or cancelled
}

function risk_label(float $score): string
{
    if ($score >= 80) return 'Low Risk';
    if ($score >= 50) return 'Moderate Risk';
    return 'High Risk';
}

function risk_class(float $score): string
{
    if ($score >= 80) return 'risk-low';
    if ($score >= 50) return 'risk-mid';
    return 'risk-high';
}
