# Cyber4rall NDPR Compliance Tracker

A multi-tenant PHP + MySQL SaaS tool: companies sign up, self-assess against
the Nigeria Data Protection Act (NDPR), get a weighted compliance score by
category, track remediation of gaps, and subscribe via Paystack
(₦25,000/month, 14-day free trial).

## Setup

See [SETUP.md](./SETUP.md) for full deployment instructions.

Quick start:
1. Copy `public_html/config.example.php` to `public_html/config.php` and fill in your real DB + Paystack credentials. `config.php` is gitignored — never commit it.
2. Import `sql/schema.sql`, then `sql/002_billing.sql`, then `sql/003_analytics.sql`, in that order.
3. Point your web server's document root at `public_html/`.

## Structure
- `public_html/` — the application (PHP 8.2+, no framework, no Composer dependency)
- `sql/` — schema + migrations, run in numeric order
- `SETUP.md` — full DirectAdmin/cPanel deployment walkthrough
