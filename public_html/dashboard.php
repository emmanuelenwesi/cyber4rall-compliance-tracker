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
$overall = $scores['overall'];

$openGaps = $conn->prepare('SELECT COUNT(*) AS c FROM remediation_items WHERE company_id = ? AND status != "done"');
$openGaps->bind_param('i', $companyId);
$openGaps->execute();
$openGapsCount = (int)$openGaps->get_result()->fetch_assoc()['c'];
$openGaps->close();

$totalQ = $conn->prepare('SELECT COUNT(*) AS c FROM questions');
$totalQ->execute();
$totalQuestions = (int)$totalQ->get_result()->fetch_assoc()['c'];
$totalQ->close();

$answeredQ = array_sum(array_column($scores['categories'], 'answered_questions'));

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<h1><?= e($company['name']) ?></h1>
<p class="helper" style="margin-bottom:24px;"><?= $answeredQ ?> of <?= $totalQuestions ?> questions answered</p>

<div class="panel">
    <div class="score-hero">
        <div>
            <div class="score-number mono"><?= number_format($overall, 1) ?>%</div>
            <div class="score-meta">Overall NDPR compliance score</div>
            <span class="risk-pill <?= risk_class($overall) ?>"><?= risk_label($overall) ?></span>
        </div>
        <div style="margin-left:auto;text-align:right;">
            <div style="font-size:2rem;font-family:'IBM Plex Mono';font-weight:600;"><?= $openGapsCount ?></div>
            <div class="score-meta">open remediation item<?= $openGapsCount === 1 ? '' : 's' ?></div>
        </div>
    </div>

    <h2 style="margin-bottom:14px;">By category</h2>
    <?php foreach ($scores['categories'] as $cat): ?>
        <div class="cat-row">
            <div>
                <div class="cat-name"><?= e($cat['name']) ?></div>
                <div class="cat-sub"><?= $cat['answered_questions'] ?>/<?= $cat['total_questions'] ?> answered</div>
            </div>
            <div class="meter"><div class="meter-fill" style="width:<?= $cat['score'] ?>%;background:<?= $cat['score'] >= 80 ? 'var(--risk-low)' : ($cat['score'] >= 50 ? 'var(--risk-mid)' : 'var(--risk-high)') ?>;"></div></div>
            <div class="cat-score"><?= number_format($cat['score'], 0) ?>%</div>
        </div>
    <?php endforeach; ?>
</div>

<div style="margin-top:20px;display:flex;gap:10px;">
    <a class="btn" href="/questionnaire.php"><?= $answeredQ === 0 ? 'Start assessment' : 'Continue assessment' ?></a>
    <a class="btn btn-secondary" href="/remediation.php">View remediation plan</a>
    <a class="btn btn-secondary" href="/report.php">View report</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
