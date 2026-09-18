<?php
/**
 * Site configuration TEMPLATE.
 * Copy this to config.php and fill in real values. config.php itself is
 * gitignored so your DB password and Paystack keys never end up in version control.
 */

// --- Database credentials ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// --- Site settings ---
define('SITE_NAME', 'Cyber4rAll Compliance Tracker');
define('SITE_URL', 'https://compliance.cyber4rall.com');

// --- Paystack ---
// Get these from https://dashboard.paystack.com/#/settings/developer
// Start with TEST keys (sk_test_..., pk_test_...) until you're ready to go live.
define('PAYSTACK_SECRET_KEY', 'sk_test_REPLACE_ME');
define('PAYSTACK_PUBLIC_KEY', 'pk_test_REPLACE_ME');
// Create a Plan in Paystack Dashboard > Payments > Plans:
//   Name: Cyber4rAll Compliance Tracker, Amount: 25000 NGN, Interval: Monthly
// then paste its plan code (starts with PLN_) here.
define('PAYSTACK_PLAN_CODE', 'PLN_REPLACE_ME');
define('SUBSCRIPTION_PRICE_NAIRA', 25000);
define('TRIAL_DAYS', 14);

// --- Brand assets (hotlinked from the main cyber4rall.com site: same domain, so no CORS issue) ---
define('BRAND_LOGO_URL', 'https://www.cyber4rall.com/wp-content/uploads/2026/06/Gemini_Generated_Image_9vm5uo9vm5uo9vm5-removebg-preview-removebg-preview-1-4-50x57.png');
define('BRAND_ICON_URL', 'https://www.cyber4rall.com/wp-content/uploads/2026/06/cropped-cyber4rall_site_icon-trans-270x270.png');
define('BRAND_OG_IMAGE_URL', 'https://www.cyber4rall.com/wp-content/uploads/2026/06/pexels-photo-5380597-1024x682.jpeg');

// --- Contact & socials (from cyber4rall.com) ---
define('BRAND_CONTACT_EMAIL', 'hello@cyber4rall.com');
define('BRAND_CONTACT_PHONE', '+234 916 863 8158');
define('BRAND_ADDRESS', 'Lagos, Nigeria');
define('SOCIAL_LINKS', [
    'Facebook' => 'https://www.facebook.com/profile.php?id=61568060291163',
    'X' => 'https://x.com/Cyber4rAll',
    'Instagram' => 'https://www.instagram.com/cyber4rall/',
    'LinkedIn' => 'https://www.linkedin.com/company/cyber4rall/',
    'YouTube' => 'https://www.youtube.com/@Cyber4rAll',
]);

// --- Default meta description (overridden per-page via $pageDescription) ---
define('DEFAULT_META_DESCRIPTION', 'A guided NDPR / Nigeria Data Protection Act compliance self-assessment and remediation tracker, built by Cyber4rall.');

// --- Email ---
// Set to true only in local/dev testing to write emails to a log file
// instead of sending them. Always false in production.
define('MAIL_TEST_MODE', false);

// --- Session security ---
// Cookies only sent over HTTPS once SSL is active on the subdomain.
define('FORCE_HTTPS_COOKIES', true);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // set to '1' temporarily if you need to debug a blank page
ini_set('log_errors', '1');
