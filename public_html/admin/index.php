<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_platform_admin();
$conn = db();

$companies = $conn->query('SELECT id, name, created_at, subscription_status, trial_ends_at, current_period_end, cancels_at FROM companies ORDER BY created_at DESC')->fetch_all(MYSQLI_ASSOC);

foreach ($companies as &$c) {
    $s = compute_scores((int)$c['id']);
    $c['score'] = $s['overall'];
    $c['status'] = effective_status($c);

    $g = $conn->prepare('SELECT COUNT(*) AS c FROM remediation_items WHERE company_id = ? AND status != "done"');
    $g->bind_param('i', $c['id']);
    $g->execute();
    $c['open_gaps'] = (int)$g->get_result()->fetch_assoc()['c'];
    $g->close();
}
unset($c);

$pageTitle = 'Platform Admin';
require __DIR__ . '/../includes/header.php';
?>
<h1>All Companies</h1>
<p class="helper">Visible only to Cyber4rAll platform admins.</p>

<div class="panel">
    <table>
        <thead><tr><th>Company</th><th>Signed up</th><th>Subscription</th><th>Score</th><th>Open gaps</th><th>Risk</th></tr></thead>
        <tbody>
        <?php if (!$companies): ?>
            <tr><td colspan="6" class="helper">No companies yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($companies as $c): ?>
            <tr>
                <td><?= e($c['name']) ?></td>
                <td><?= e(date('j M Y', strtotime($c['created_at']))) ?></td>
                <td><?= e(ucwords(str_replace('_', ' ', $c['status']))) ?></td>
                <td class="mono"><?= number_format($c['score'], 1) ?>%</td>
                <td><?= $c['open_gaps'] ?></td>
                <td><span class="risk-pill <?= risk_class($c['score']) ?>"><?= risk_label($c['score']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
