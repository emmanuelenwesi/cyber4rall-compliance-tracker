<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) {
    header('Location: /dashboard.php');
    exit;
}
$pageTitle = 'NDPR Compliance Tracker';
require __DIR__ . '/includes/header.php';
?>
<div class="page-narrow" style="max-width:620px;margin:56px auto;">
    <h1>Know where your NDPR compliance stands, and what to fix next.</h1>
    <p>A guided self-assessment against Nigeria's Data Protection Act, built by Cyber4rAll. Answer a structured questionnaire, get a compliance score by category, and track remediation until you close every gap.</p>
    <div style="margin-top:24px;">
        <a class="btn" href="/signup.php">Start your assessment</a>
        <a class="btn btn-secondary" href="/login.php" style="margin-left:10px;">Log in</a>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
