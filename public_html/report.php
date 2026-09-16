<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();
require_active_access($user);
$companyId = $user['company_id'];
$conn = db();

$stmt = $conn->prepare('SELECT name FROM companies WHERE id = ?');
$stmt->bind_param('i', $companyId);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
$stmt->close();

$scores = compute_scores($companyId);

$stmt = $conn->prepare('SELECT * FROM remediation_items WHERE company_id = ? AND status != "done" ORDER BY due_date IS NULL, due_date ASC');
$stmt->bind_param('i', $companyId);
$stmt->execute();
$openItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Compliance Report';
require __DIR__ . '/includes/header.php';
?>
<div class="no-print" style="margin-bottom:20px;">
    <button onclick="window.print()">Print / Save as PDF</button>
</div>

<h1><?= e($company['name']) ?> — NDPR Compliance Report</h1>
<p class="helper">Generated <?= date('j F Y') ?></p>

<div class="panel">
    <div class="score-hero">
        <div>
            <div class="score-number mono"><?= number_format($scores['overall'], 1) ?>%</div>
            <div class="score-meta">Overall compliance score</div>
            <span class="risk-pill <?= risk_class($scores['overall']) ?>"><?= risk_label($scores['overall']) ?></span>
        </div>
    </div>
    <h2>By category</h2>
    <?php foreach ($scores['categories'] as $cat): ?>
        <div class="cat-row">
            <div class="cat-name"><?= e($cat['name']) ?></div>
            <div class="meter"><div class="meter-fill" style="width:<?= $cat['score'] ?>%;"></div></div>
            <div class="cat-score"><?= number_format($cat['score'], 0) ?>%</div>
        </div>
    <?php endforeach; ?>
</div>

<div class="panel" style="margin-top:16px;">
    <h2>Open remediation items (<?= count($openItems) ?>)</h2>
    <table>
        <thead><tr><th>Item</th><th>Assigned to</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$openItems): ?>
            <tr><td colspan="4" class="helper">No open items — great work.</td></tr>
        <?php endif; ?>
        <?php foreach ($openItems as $item): ?>
            <tr>
                <td><?= e($item['title']) ?></td>
                <td><?= e($item['assigned_to'] ?: '—') ?></td>
                <td><?= e($item['due_date'] ?: '—') ?></td>
                <td><?= e(ucwords(str_replace('_', ' ', $item['status']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
