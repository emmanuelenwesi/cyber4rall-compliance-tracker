<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Terms & Conditions';
$pageDescription = 'Terms of use for the Cyber4rall NDPR Compliance Tracker.';
require __DIR__ . '/includes/header.php';
?>
<div style="max-width:760px;">
<h1>Terms &amp; Conditions</h1>
<p class="helper">Last updated <?= date('j F Y') ?></p>

<div class="panel">
<h2>1. The service</h2>
<p>The NDPR Compliance Tracker is a self-assessment and remediation-tracking tool provided by Cyber4rall. It helps you gauge your organization's alignment with the Nigeria Data Protection Act 2023 and track progress closing identified gaps.</p>

<h2>2. Not legal advice</h2>
<p>The questionnaire, scoring, and remediation suggestions are provided for informational and operational purposes only and do not constitute legal advice. Achieving a high score on this tool does not guarantee regulatory compliance or certification. For formal legal opinions or regulatory filings, consult a qualified data protection professional or legal counsel.</p>

<h2>3. Your account</h2>
<p>You're responsible for the accuracy of information you enter and for keeping your login credentials confidential. One account represents one company; each user within that account may have admin or staff-level access as assigned.</p>

<h2>4. Subscriptions and billing</h2>
<p>New accounts include a <?= TRIAL_DAYS ?>-day free trial. After the trial, continued access requires an active paid subscription, billed monthly at ₦<?= number_format(SUBSCRIPTION_PRICE_NAIRA) ?> via Paystack. You may cancel at any time from the Billing page; access continues until the end of the current billing period. Fees already paid are non-refundable except where required by law.</p>

<h2>5. Acceptable use</h2>
<p>You agree not to use the service to store data you're not authorized to hold, to attempt to access other companies' accounts, or to interfere with the security or normal operation of the platform.</p>

<h2>6. Availability</h2>
<p>We aim to keep the service available and reliable but do not guarantee uninterrupted access. We may update features or pricing from time to time, with reasonable notice for material changes affecting paid subscribers.</p>

<h2>7. Limitation of liability</h2>
<p>The service is provided "as is." To the extent permitted by law, Cyber4rall is not liable for indirect or consequential losses arising from use of the tool, including reliance on its scoring or remediation suggestions in place of independent professional advice.</p>

<h2>8. Contact</h2>
<p>Questions about these terms: <a href="mailto:<?= e(BRAND_CONTACT_EMAIL) ?>"><?= e(BRAND_CONTACT_EMAIL) ?></a>.</p>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
