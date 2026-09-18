# Deploying the NDPR Compliance Tracker to compliance.cyber4rall.com

## What's in this package
- `public_html/` — the entire app. Everything inside this folder goes into your subdomain's document root.
- `sql/schema.sql` — database tables + the seeded NDPR question bank (9 categories, ~30 questions).
- This guide.

## Step 1 — Import the database
1. In DirectAdmin, open your `cyberra1_compliance` database (Database Management → phpMyAdmin, or similar).
2. Open phpMyAdmin, select the `cyberra1_compliance` database, go to **Import**, and upload `sql/schema.sql`.
3. Confirm you now see 7 tables: `companies`, `users`, `categories`, `questions`, `responses`, `remediation_items`, `score_history`, and that `categories`/`questions` are pre-filled.

## Step 2 — Upload the app
1. Open **File Manager** (or use FTP) and go to `/domains/compliance.cyber4rall.com/public_html`.
2. Upload the entire contents of this package's `public_html/` folder into that directory (not the folder itself — its *contents*).
3. Confirm `config.php` ends up at the document root, i.e. `/domains/compliance.cyber4rall.com/public_html/config.php`.

## Step 3 — Set up your config.php
If you're deploying straight from the zip, `config.php` is already filled in with your DirectAdmin credentials — skip to Step 4.

If you're deploying from git (see the "Version control" section near the end of this guide), `config.php` isn't tracked in the repo on purpose — copy `config.example.php` to `config.php` and fill in the real values:
```
DB_HOST = localhost
DB_NAME = cyberra1_compliance
DB_USER = cyberra1_compliance
DB_PASS = (the password DirectAdmin generated)
```

## Step 4 — Enable SSL on the subdomain
In DirectAdmin, make sure AutoSSL / Let's Encrypt is issued for `compliance.cyber4rall.com`. The app forces HTTPS and sets secure cookies — it won't log users in reliably over plain HTTP.

## Step 5 — Confirm PHP version
Under MultiPHP Manager (or similar), set `compliance.cyber4rall.com` to **PHP 8.2** — you already have this available.

## Step 6 — Set up Paystack billing
The app charges ₦25,000/month per company, with a 14-day free trial on signup. To wire this up:

1. **Import the billing migration**: in phpMyAdmin, run `sql/002_billing.sql` against `cyberra1_compliance` (after `sql/schema.sql`). This adds subscription columns to `companies` and a `payments` log table.
2. **Get your API keys**: in the Paystack Dashboard, go to Settings → API Keys & Webhooks. Start with the **Test** secret/public keys while you try everything out; switch to Live keys once you're ready to charge real cards.
3. **Create a Plan**: Dashboard → Payments → Plans → Create Plan.
   - Name: `Cyber4rAll Compliance Tracker`
   - Amount: `25000` NGN
   - Interval: `Monthly`
   - Copy the resulting plan code (starts with `PLN_`).
4. **Fill in `config.php`**:
   ```
   PAYSTACK_SECRET_KEY = sk_test_... (or sk_live_... when you go live)
   PAYSTACK_PUBLIC_KEY = pk_test_... (or pk_live_...)
   PAYSTACK_PLAN_CODE  = PLN_...
   ```
5. **Set the webhook URL**: Dashboard → Settings → API Keys & Webhooks → Webhook URL:
   ```
   https://compliance.cyber4rall.com/webhooks/paystack.php
   ```
   This is what keeps subscription status in sync automatically — renewals, failed payments, and cancellations all flow through it, no manual reconciliation needed.
6. **Test it**: sign up a test company, go to Billing, click Subscribe, and pay with one of [Paystack's test cards](https://paystack.com/docs/payments/test-payments/) while your keys are still in Test mode. Confirm you land back on the dashboard with an active subscription, and that a row appears in the `payments` table.
7. **Go live**: once you're happy, swap the Test keys for Live keys in `config.php`, and create a *Live-mode* version of the Plan in the dashboard (test and live plans are separate) — update `PAYSTACK_PLAN_CODE` to match.

### How billing works
- New signups get a 14-day trial automatically (`trial_ends_at` on the company).
- `dashboard.php`, `questionnaire.php`, `remediation.php`, and `report.php` are gated behind `require_active_access()` — anyone past their trial without an active subscription is redirected to `/billing.php`. Platform admins (you) always bypass this.
- Subscribing sends the user to Paystack's checkout with your Plan attached; `paystack_callback.php` verifies the payment and activates access immediately for a fast experience.
- The webhook (`webhooks/paystack.php`) is the real source of truth going forward — it handles monthly renewals (`charge.success`), failed renewal attempts (`invoice.payment_failed`, sets status to `past_due`), and cancellations (`subscription.disable`).
- A company can cancel any time from `/billing.php`; this calls Paystack to stop future billing and marks them `cancelled` locally.

## Step 7 — Import the analytics migration
Run `sql/003_analytics.sql` in phpMyAdmin (after the other two). This adds a lightweight, cookie-free `pageviews` table — no Google Analytics, no third-party tracking, just a simple log of which pages get visited, which fits a privacy-compliance product much better.

## Step 7b — Import the MFA/cancellation migration
Run `sql/004_cancel_and_mfa.sql` (after 003). This adds:
- `companies.cancels_at` — lets cancellation stop future billing without cutting off the period already paid for
- `users.mfa_secret`, `mfa_enabled`, `mfa_recovery_codes` — TOTP two-factor authentication, no third-party service involved

## Step 7c — Import the password reset migration
Run `sql/005_password_reset.sql` (after 004). This adds `users.reset_token_hash` and `reset_token_expires` — password reset links work by hashing the token before storing it, the same principle as password hashing, so a database leak alone can't be used to reset anyone's password.

Password reset emails go out via PHP's built-in `mail()`, which DirectAdmin/cPanel wires up to your domain's own mail setup automatically — no SMTP credentials needed. If reset emails aren't arriving, check your spam folder first, then check that your DirectAdmin account has outbound mail enabled (some hosts require a one-time toggle).

## Step 7d — Import the rate-limiting migration
Run `sql/006_rate_limiting.sql` (after 005). This adds a `rate_limit_events` table used to lock out repeated failed login attempts (5 per account / 15 min, plus 20 per IP address / 15 min to catch one attacker trying many accounts), repeated wrong MFA codes (5 attempts), and repeated password-reset requests (3 per email / 15 min, so the form can't be used to spam someone's inbox). No cron job needed — old rows are pruned automatically.

## Launch checklist — what's now built in
- **Privacy Policy & Terms** — `/privacy.php` and `/terms.php`, written specifically for this product (NDPR-relevant, covers Paystack billing data, retention, and rights requests). Linked in the footer of every page.
- **Cookie notice** — a small non-blocking banner (bottom of screen) explaining that only a necessary session cookie is used — no tracking/ad cookies to consent to in the first place.
- **SEO meta** — every page sets its own `<title>` and `<meta name="description">`; set `$pageTitle` and `$pageDescription` before including `includes/header.php` on any new page you add.
- **Social preview cards** — Open Graph + Twitter Card tags, using your logo/photo from cyber4rall.com, so links shared on WhatsApp/LinkedIn/X render nicely.
- **Favicon** — hotlinked from your existing site icon on cyber4rall.com. If you ever redesign the main site's icon, this subdomain picks it up automatically; to decouple them later, just download the icon and swap `BRAND_ICON_URL` in `config.php` for a local `/assets/` path.
- **Sitemap & robots.txt** — `/sitemap.xml` lists the public pages; `/robots.txt` tells search engines not to index private app pages (dashboard, questionnaire, admin, etc.).
- **Custom 404 page** — `404.php`, wired up via `.htaccess` (`ErrorDocument 404 /404.php`). Note: the DirectAdmin/Apache environment will serve this correctly; PHP's own built-in dev server (used only for local testing) ignores `.htaccess` and shows its default 404 instead — that's expected and not a bug.
- **Spam protection** — a honeypot field on signup (invisible to real visitors, irresistible to bots) silently drops bot submissions before they touch the database.
- **Color contrast** — the palette was checked against WCAG AA; one shade (the "High Risk" red) was adjusted for readability.
- **Mobile nav** — the top navigation collapses into a hamburger menu below 640px width.
- **Analytics** — a first-party, cookie-free pageview log (see Step 8). Query it directly in phpMyAdmin for now, e.g. `SELECT path, COUNT(*) FROM pageviews GROUP BY path ORDER BY COUNT(*) DESC;` — a proper dashboard for this can come later if useful.

### What's already true and didn't need extra work
- **Secrets off the frontend** — `config.php` and everything in `includes/` are denied at the web-server level via `.htaccess`; credentials never reach the browser.
- **Force HTTPS** — already in `.htaccess`; just make sure SSL is issued (Step 4).
- **Mobile friendly** — the layout is responsive by default (flexible grid, fluid widths); the hamburger nav (above) rounds this out.
- **Fast page loads** — one small CSS file, no JS frameworks, fonts preloaded with `preconnect`, and the logo/OG image are hotlinked from your existing (already-optimized) site rather than adding new image weight.
- **Form validation** — signup and login already validate required fields, email format, and minimum password length both in the browser and again on the server.
- **One clear call to action** — each page leads with a single primary button (e.g. "Start your assessment", "Subscribe").

## Step 8 — Create your account and make yourself a platform admin
1. Visit `https://compliance.cyber4rall.com/signup.php` and create your own company + account (this becomes your first tenant — you can use it as a demo/test company, or your own internal test).
2. Open `public_html/admin/make_admin.php` and change `$SETUP_KEY` to something private.
3. Re-upload that one file.
4. Visit `https://compliance.cyber4rall.com/admin/make_admin.php?email=YOUR_EMAIL&key=YOUR_KEY`.
5. You should see a confirmation. Log out and back in — you'll now see an "Admin" link in the top nav showing every client company's score.
6. **Delete `admin/make_admin.php` from the server** — it's a one-time setup tool and shouldn't stay live.

## How it works
- **Multi-tenant**: every company that signs up gets its own row in `companies`, and every user, response, and remediation item is scoped to `company_id`. Companies can never see each other's data.
- **Scoring**: each question has a weight; "Yes" = full weight, "Partial" = half weight, "No"/unanswered = zero. Category and overall scores are weighted averages, shown as a percentage with a risk label (Low/Moderate/High Risk).
- **Remediation**: any "No" or "Partial" answer automatically creates an open remediation item. Marking the question "Yes" later auto-closes it. Staff can also add their own items.
- **Report**: `/report.php` is a print-friendly page — the "Print / Save as PDF" button uses the browser's built-in PDF export, so there's no extra server library needed on shared hosting.

## Extending the question bank later
The questionnaire is entirely data-driven from the `categories` and `questions` tables — add more via phpMyAdmin (or a small admin form later) without touching code.

## Selling it
Each new client just visits the site and signs up (`/signup.php`) — no manual account creation needed. You can point prospective clients straight at `https://compliance.cyber4rall.com` for a self-serve trial, or create their account for them during a paid onboarding call.

## Version control (git) — since you already deployed manually
You uploaded the app via File Manager first, which is fine — this section brings that live folder under git afterward, without losing anything.

### 1. Create a private GitHub repo
On github.com, create a new **private** repository (e.g. `cyber4rall-compliance-tracker`). Don't initialize it with a README/gitignore/license — you're pushing an existing project into it.

### 2. Push this project to it
From the extracted project folder on your own computer (the one with `.git` already inside it):
```
git remote add origin https://github.com/YOUR-USERNAME/cyber4rall-compliance-tracker.git
git branch -M main
git push -u origin main
```
Your DB password and Paystack keys are safe — `config.php` is gitignored and was never committed; only `config.example.php` (a placeholder template) went up.

### 3. Bring the live server under git via SSH
SSH into your Go54 hosting, then:
```
cd /domains/compliance.cyber4rall.com
mv public_html public_html_backup      # keep the working copy safe
git clone https://github.com/YOUR-USERNAME/cyber4rall-compliance-tracker.git tmp_repo
mv tmp_repo/public_html public_html
mv tmp_repo/sql .                      # optional, handy to keep alongside
rm -rf tmp_repo
cp public_html_backup/config.php public_html/config.php   # restore your real credentials
```
Visit the site again and confirm it still works exactly as before — same login, same data (nothing in the database changed, only how the files are managed).

Once you're confident it's working, you can remove `public_html_backup`.

### 4. Going forward
Make changes locally (or have me make them), test, then:
```
git add -A
git commit -m "describe the change"
git push
```
Then on the server:
```
cd /domains/compliance.cyber4rall.com/public_html
git pull
```
`config.php` is never touched by `git pull` since it's gitignored and untracked on the server — your live credentials stay put across every update.
