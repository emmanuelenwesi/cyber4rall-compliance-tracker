<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/analytics.php';

$user = current_user();
$pageTitle = $pageTitle ?? SITE_NAME;
$pageDescription = $pageDescription ?? DEFAULT_META_DESCRIPTION;
$canonicalUrl = SITE_URL . ($_SERVER['REQUEST_URI'] ?? '/');

log_pageview($user['company_id'] ?? null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — <?= e(SITE_NAME) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">

<!-- Social preview -->
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?> — <?= e(SITE_NAME) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:image" content="<?= e(BRAND_OG_IMAGE_URL) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?> — <?= e(SITE_NAME) ?>">
<meta name="twitter:description" content="<?= e($pageDescription) ?>">
<meta name="twitter:image" content="<?= e(BRAND_OG_IMAGE_URL) ?>">

<!-- Favicon (hotlinked from cyber4rall.com's site icon) -->
<link rel="icon" href="<?= e(BRAND_ICON_URL) ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= e(BRAND_ICON_URL) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= $user ? '/dashboard.php' : '/index.php' ?>">
            <img src="<?= e(BRAND_LOGO_URL) ?>" alt="Cyber4rall logo" class="brand-logo">
            <span class="brand-text">Cyber4rall <span>Compliance</span></span>
        </a>
        <button class="nav-toggle no-print" aria-label="Menu" onclick="document.querySelector('.nav').classList.toggle('open')">☰</button>
        <?php if ($user): ?>
        <nav class="nav">
            <a href="/dashboard.php">Dashboard</a>
            <a href="/questionnaire.php">Questionnaire</a>
            <a href="/remediation.php">Remediation</a>
            <a href="/report.php">Report</a>
            <a href="/billing.php">Billing</a>
            <?php if (!empty($user['is_platform_admin'])): ?>
            <a href="/admin/index.php">Admin</a>
            <?php endif; ?>
            <a href="/logout.php">Log out</a>
        </nav>
        <?php endif; ?>
    </div>
</header>
<main class="page">
<?php
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($user && empty($user['is_platform_admin']) && !in_array($currentScript, ['billing.php', 'paystack_callback.php', 'login.php', 'signup.php'], true)) {
    $company = get_company($user['company_id']);
    if ($company) {
        if ($company['subscription_status'] === 'trial' && has_active_access($company)) {
            $daysLeft = (new DateTime())->diff(new DateTime($company['trial_ends_at']))->days;
            echo '<div class="panel no-print" style="margin-bottom:20px;border-color:var(--risk-mid);">'
               . 'Free trial — ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left. '
               . '<a href="/billing.php">Subscribe</a> to keep access after it ends.</div>';
        } elseif (!has_active_access($company)) {
            $msg = $company['subscription_status'] === 'past_due'
                ? 'Your last payment failed — access is paused until this is resolved.'
                : 'Your trial or subscription has ended — access is paused.';
            echo '<div class="panel no-print" style="margin-bottom:20px;border-color:var(--risk-high);">'
               . e($msg) . ' <a href="/billing.php">Go to billing</a></div>';
        }
    }
}
?>
