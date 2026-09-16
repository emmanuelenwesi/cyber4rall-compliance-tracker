<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

http_response_code(404);
$pageTitle = 'Page not found';
$pageDescription = 'The page you were looking for could not be found.';
require __DIR__ . '/includes/header.php';
$user = current_user();
?>
<div class="page-narrow" style="max-width:520px;margin:56px auto;text-align:center;">
    <h1 style="font-size:3rem;">404</h1>
    <p>We couldn't find that page. It may have moved, or the link might be out of date.</p>
    <a class="btn" href="<?= $user ? '/dashboard.php' : '/index.php' ?>">Back to <?= $user ? 'dashboard' : 'homepage' ?></a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
