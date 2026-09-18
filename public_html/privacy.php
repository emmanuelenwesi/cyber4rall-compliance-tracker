<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Privacy Policy';
$pageDescription = 'How Cyber4rall collects, uses, and protects personal data through the NDPR Compliance Tracker.';
require __DIR__ . '/includes/header.php';
?>
<div style="max-width:760px;">
<h1>Privacy Policy</h1>
<p class="helper">Last updated <?= date('j F Y') ?></p>

<div class="panel">
<p>Cyber4rall ("we", "us", "our") operates the NDPR Compliance Tracker at compliance.cyber4rall.com. This policy explains what personal data we collect through the service, why, and the choices you have, consistent with the Nigeria Data Protection Act 2023 (NDPA) and its supporting regulations.</p>

<h2>What we collect</h2>
<p><strong>Account data:</strong> your name, work email, and company name when you sign up.</p>
<p><strong>Assessment data:</strong> the answers, notes, and remediation items you enter while using the questionnaire. This describes your organization's own compliance practices, not personal data about third parties, unless you choose to include it in a note.</p>
<p><strong>Billing data:</strong> subscription status and payment references. Card details are entered directly with our payment processor, Paystack, and never touch our servers. We store only the transaction reference, amount, and status Paystack reports back to us.</p>
<p><strong>Usage data:</strong> we log which pages are visited, in aggregate, to understand how the product is used. This is first-party and cookie-free. We do not use third-party analytics or advertising trackers.</p>
<p><strong>Cookies:</strong> we set one strictly necessary session cookie to keep you signed in, and one small cookie to remember that you've seen our cookie notice. Neither is used for tracking or advertising.</p>

<h2>Why we process it</h2>
<p>To provide the service you've signed up for (contract), to process payment for a paid subscription (contract), and to keep the service secure and working correctly (legitimate interest).</p>

<h2>Who can see your data</h2>
<p>Your company's assessment data is visible only to users within your own company account. Cyber4rall staff with platform-admin access can see your compliance score and subscription status to provide support, never your detailed answers or notes, unless you explicitly share them with us for support purposes.</p>

<h2>Data retention</h2>
<p>We retain account and assessment data for as long as your account is active, and for a reasonable period afterward in case you wish to reactivate. You can request deletion at any time (see below).</p>

<h2>Your rights under the NDPA</h2>
<p>You may request access to, correction of, or deletion of your personal data, and may object to or request restriction of certain processing. To exercise any of these rights, contact us at <a href="mailto:<?= e(BRAND_CONTACT_EMAIL) ?>"><?= e(BRAND_CONTACT_EMAIL) ?></a>.</p>

<h2>Contact</h2>
<p>Cyber4rall, <?= e(BRAND_ADDRESS) ?>. Email: <a href="mailto:<?= e(BRAND_CONTACT_EMAIL) ?>"><?= e(BRAND_CONTACT_EMAIL) ?></a>. Phone: <?= e(BRAND_CONTACT_PHONE) ?>.</p>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
