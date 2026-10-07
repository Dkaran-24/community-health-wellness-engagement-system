> ℹ️ **This README documents the GYM ADMIN features inherited from the original
> New Life Fitness system — all of which are preserved and working inside this
> CEP.** For the Community Engagement Project overview (community platform,
> demo workflow, AI prototypes), read **[`README-CEP.md`](README-CEP.md)**.

---

# New Life Fitness Club — Gym Management System

A full-stack, web-based **Gym Management System** for **New Life Fitness Club**, built for administrators to digitally manage member registrations, trainers, attendance, membership plans, and fee payments — replacing manual paper-based record keeping. The entire UI is branded with the club's official navy-and-gold emblem logo and a color scheme derived directly from that logo.

![New Life Fitness Club Logo](assets/images/logo.jpg)

---

## Tech Stack

| Layer     | Technology                          |
|-----------|-------------------------------------|
| Frontend  | HTML, CSS, JavaScript (vanilla)     |
| Backend   | PHP                                 |
| Database  | MySQL                               |
| Server    | XAMPP (Apache + MySQL + PHP)        |

---

## Features / Modules

1. **Admin Authentication** — branded login page (logo + gym name), session-based auth, logout, automatic redirect of unauthenticated users. Passwords stored as bcrypt hashes; queries use prepared statements.
2. **Dashboard** — branded welcome banner ("Welcome to New Life Fitness Club Admin Panel"), live summary cards (total members, active memberships, trainers, today's attendance, pending fees, monthly revenue, plans, transactions), newest members & expiring-soon lists, and quick navigation.
3. **Member Management** — add / edit / delete members (name, contact, email, address, DOB, gender, join date, plan, trainer, status, **optional photo upload**), searchable & sortable table with photo thumbnails, individual member profile with large photo, attendance and fee history. Member photos are uploaded via a secure file-input (JPG/PNG/GIF/WebP, max 5 MB, MIME-validated), stored in `uploads/members/`, and can be replaced or removed from the Edit Member page.
4. **Trainer Management** — add / edit / delete trainers (name, specialization, contact, email, schedule, salary), list with member-count badges.
5. **Attendance Management** — mark daily present/absent per active member via a bulk sheet, view by date, recent attendance log.
6. **Membership Plan Management** — create / edit / delete plans (Monthly, Quarterly, Half-Yearly, Yearly) with pricing & duration, member counts, automatic expiry/expiring-soon flags on member rows.
7. **Fee Management** — record payments (amount, date, mode, member, plan, status), track Paid / Pending / Overdue, payment history, and **printable branded receipts** with New Life Fitness Club letterhead (logo + name + address + contact).
8. **Reports** — active members, expired memberships, monthly revenue, and attendance summary; all printable on the same branded letterhead, with a "Print All" action.
9. **Offer Management & Email Campaign** — a complete marketing module (detailed below) that lets admins create promotional offers with poster banners and broadcast personalized HTML emails to every active gym member.
10. **Settings & Email** — SMTP configuration stored in the database, test-email diagnostic, payment-receipt and reminder emails, and a full Email Diagnostic page.

---

## File Structure

```
new-life-fitness/
├── assets/
│   ├── css/
│   │   └── style.css            # Navy/gold theme (derived from logo)
│   ├── js/
│   │   └── main.js              # Validation, confirmations, search, sort
│   └── images/
│       ├── logo.jpg             # Official club emblem
│       ├── favicon.ico          # Favicon derived from logo
│       └── favicon-32.png
├── includes/
│   ├── auth.php                 # Session guard + base URL helpers
│   ├── header.php               # Shared <head> + topbar
│   ├── sidebar.php              # Branded navigation sidebar
│   ├── footer.php               # Closing layout + JS include
│   ├── helpers.php              # e(), fmtMoney(), flash(), expiryBadge(), photo helpers…
│   ├── mailer.php               # PHPMailer SMTP wrapper (receipts, reminders, offer campaigns)
│   ├── offers.php               # Offer CRUD, campaign orchestration, email-queue worker, stats
│   └── PHPMailer/               # PHPMailer library (src/ + language/)
├── uploads/
│   ├── members/                 # Uploaded member photos (auto-created, .htaccess-protected)
│   └── offers/                  # Uploaded offer posters/banners (auto-created, .htaccess-protected)
├── admin/
│   ├── members/   (index, add, edit, delete, profile).php
│   ├── trainers/  (index, add, edit, delete).php
│   ├── attendance/(index).php
│   ├── plans/     (index, add, edit, delete).php
│   ├── fees/      (index, record, receipt, send-reminder).php
│   ├── offers/    (index, add, edit, delete, toggle, view, campaign, preview, process, resend, track-open, dashboard).php
│   ├── trainer-payments/ (index, add, edit, delete).php
│   ├── analytics/ (index).php
│   ├── reports/   (index).php
│   ├── settings/  (index).php
│   └── email-test.php
├── index.php                   # Dashboard
├── login.php                   # Branded login
├── logout.php                  # Session destroy
├── db_connect.php              # MySQL connection (env-var / config.local.php / XAMPP fallback)
├── config.example.php          # Template for config.local.php (rename & fill for hosting)
├── database.sql                # Full schema + seed data (includes offers/campaigns/logs tables)
├── offers_schema.sql           # Standalone schema for the Offers module (4 tables)
├── .htaccess                   # Security headers, blocks sensitive files
├── DEPLOY.md                   # Free hosting deployment guide (InfinityFree, 000webhost)
└── README.md
```

---

## Setup Instructions (XAMPP)

### 1. Install & start XAMPP
Download XAMPP from <https://www.apachefriends.org/>, install it, then start **Apache** and **MySQL** from the XAMPP control panel.

### 2. Copy the project
Copy the entire `new-life-fitness` folder into your XAMPP htdocs directory:
- **Windows:** `C:\xampp\htdocs\new-life-fitness`
- **macOS:** `/Applications/XAMPP/htdocs/new-life-fitness`
- **Linux:** `/opt/lampp/htdocs/new-life-fitness`

### 3. Create the database
Open phpMyAdmin (`http://localhost/phpmyadmin`) and either:
- Click **Import**, choose `database.sql` from this project, and click **Go**, **or**
- Run in the SQL tab: `SOURCE /path/to/new-life-fitness/database.sql;`

This creates the `newlife_fitness` database with all tables and seed data.

### 4. Configure the database connection
Open `db_connect.php` and confirm the credentials match your XAMPP setup (defaults below work for a standard XAMPP install):

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // default XAMPP user
define('DB_PASS', '');          // default XAMPP password is empty
define('DB_NAME', 'newlife_fitness');
```

### 5. Confirm the logo is in place
The logo is already at `assets/images/logo.jpg` and the favicon at `assets/images/favicon.ico`. If you replace the logo, keep the same path or update the `logo_path` value in the `settings` table.

### 5b. Ensure the uploads folders are writable
The `uploads/members/` directory stores member photos and `uploads/offers/` stores offer poster banners, both uploaded through their respective Add/Edit forms. The folders ship inside the project; if either is missing (e.g. after a partial copy) the app will try to create it automatically. On Linux/XAMPP make sure Apache can write to them:

```bash
chmod -R 775 uploads/
```

(On Windows XAMPP this is usually not needed.)

### 5c. Configure SMTP for email features (optional but recommended)
The system can send payment receipts, payment reminders, and offer-campaign emails via SMTP using the bundled PHPMailer library. To enable email:
1. Go to **Admin → Settings** after logging in.
2. Fill in your SMTP host, port, encryption, username, password, from-name, and from-email.
3. Toggle **SMTP Enabled** and click **Send Test Email** to verify.

Email features gracefully degrade — if SMTP is disabled or misconfigured, the rest of the system continues to work normally.

### 6. Run the application
Visit **http://localhost/new-life-fitness/login.php** in your browser.

### 7. Log in
Use the seeded administrator credentials:

| Field    | Value      |
|----------|------------|
| Username | `admin`    |
| Password | `admin123` |

> The password is stored as a bcrypt hash. To create a new admin, generate a hash in PHP:
> `echo password_hash('yourpassword', PASSWORD_DEFAULT);`
> and insert it into the `admins` table.

---

## Offer Management & Email Campaign Module

This module adds a complete marketing workflow to the system: admins create promotional offers (with poster banners), then broadcast personalized HTML emails to every active gym member — all from within the admin panel. It is production-ready, follows the existing project architecture, and integrates seamlessly without affecting other features.

### What it does

- **Offer CRUD** — Create, view, edit, delete, and activate/deactivate offers. Each offer stores a title, rich description, discount/promotion text, a poster/banner image, start date, end date, status (Active/Inactive), and created/updated timestamps.
- **Poster uploads** — Posters are uploaded through a secure file input (JPG/PNG/GIF/WebP, MIME-validated, max 5 MB), stored in `uploads/offers/` (PHP execution blocked via `.htaccess`), and shown as card thumbnails and large detail previews. A live preview appears before you submit the form.
- **Email campaign publishing** — When an admin publishes an offer, a personalized email is sent to every active gym member that has a valid email address. The email embeds the offer poster inline (so it renders even when remote images are blocked), greets the member by name, lists the offer title, description, discount, and validity dates, and includes the gym branding header/footer.
- **Responsive HTML email template** — The email uses a navy-and-gold branded template with the subject **"🎉 Exclusive Gym Offer Just for You!"** and placeholders `{{MemberName}}`, `{{OfferPoster}}`, `{{OfferTitle}}`, `{{OfferDescription}}`, `{{OfferDiscount}}`, `{{StartDate}}`, `{{EndDate}}` that are replaced per recipient.
- **Preview before sending** — A full iframe preview of the exact email (with sample member data) is shown on the campaign page before anything is sent.
- **Send now or schedule** — Campaigns can be sent immediately or scheduled for a future date/time. Scheduled campaigns are automatically promoted to "Pending" when they become due (no external cron needed — checked opportunistically on page visits).
- **Non-blocking batch sending** — Emails are sent through a background queue/worker pattern: the browser polls an AJAX endpoint (`process.php`) that sends a small batch (5 emails) per request, updating a live progress bar with percentage, sent count, and failed count. The UI never freezes, and large member lists are processed in batches.
- **Email logs** — Every individual email is tracked in the `email_logs` table with the campaign ID, member ID, email address, status (Pending/Sent/Failed), error message, sent timestamp, attempt count, and open-tracking flag. A UNIQUE key on (campaign_id, member_id) prevents duplicate emails.
- **Failed-email resending** — Failed emails can be re-sent individually per campaign or in bulk across all campaigns for an offer. The system resets Failed→Pending and re-queues them.
- **Open tracking** — Each email contains a 1×1 transparent tracking pixel that calls `track-open.php` when the member opens the email, recording an open event and timestamp.
- **Campaign dashboard** — A dedicated analytics page shows aggregate KPIs: total recipients, emails sent, emails failed, pending emails, delivery percentage, open rate, total campaigns, and completed campaigns. A per-campaign history table (searchable, filterable, paginated) shows delivery %, opens, open rate, and resend buttons.
- **Notifications** — After publishing, a success notification displays the recipient count, emails sent count, and failed emails count.
- **Audit logging** — Every campaign action (create, send, reschedule, cancel, resend) is recorded in the `email_campaign_actions` table with the admin ID, action, and detail.

### Database tables (auto-created)

The four tables below are created automatically (idempotent `CREATE TABLE IF NOT EXISTS`) the first time any offers page is loaded — via `offers_ensure_schema()` in `includes/offers.php`. They are also included in `database.sql` and the standalone `offers_schema.sql`.

| Table | Purpose |
|-------|---------|
| `offers` | Offer records (title, description, discount, poster_path, dates, status, created_by, timestamps) |
| `email_campaigns` | One per publish action (offer_id, admin_id, subject, recipient/sent/failed/pending counts, status, schedule_at, timestamps) |
| `email_logs` | One row per recipient email (campaign_id, member_id, email, status, error, opened, sent_at, attempts) — UNIQUE(campaign_id, member_id) prevents duplicates |
| `email_campaign_actions` | Audit log of every campaign action (campaign_id, offer_id, admin_id, action, detail, created_at) |

All tables use InnoDB, utf8mb4, and foreign keys with `ON DELETE CASCADE` / `ON DELETE SET NULL` as appropriate.

### Prerequisite: configure SMTP

The campaign emails use PHPMailer via SMTP (the library is already bundled at `includes/PHPMailer/`). Before sending campaigns, configure SMTP in **Admin → Settings**:

1. Go to **Settings** in the sidebar.
2. Enter your SMTP host, port, encryption (TLS/SSL), username, password, from-name, and from-email.
3. Toggle SMTP to **Enabled**.
4. Use the **Send Test Email** button to verify connectivity.

Recommended free SMTP providers: **Gmail** (App Password required for 2FA accounts), **Brevo/Sendinblue** (free tier, 300 emails/day). The Settings page auto-migrates the SMTP columns if they are missing.

### How to use

1. **Create an offer** — Admin → Offers → "New Offer". Fill in the title, description, discount text, upload a poster image, set the start/end dates, and save. The offer is created as Active by default.
2. **Review offers** — The Offers page shows all offers as responsive cards with poster previews, status badges, live/expired indicators, and quick actions. Use the search box and status filter.
3. **Publish a campaign** — Click **Publish** on an offer card (or from the offer detail page). Review the recipient count, preview the email in the iframe, choose **Send now** or **Schedule**, and submit. A live progress bar appears as emails are batch-sent.
4. **Monitor delivery** — Open the offer's detail page to see campaign stat cards, campaign history, and a per-recipient delivery log (with status, open indicator, and error messages). Use the **Campaign Dashboard** for system-wide analytics.
5. **Resend failures** — Click **Resend Failed** on a campaign or use the bulk resend from the dashboard to re-queue any emails that failed.

### Security

- Only authenticated admins (via the existing `require_login()` session guard) can access any offers page — no changes to the auth model were needed.
- Poster uploads are MIME-validated via `finfo` and restricted to image types; the `uploads/offers/` directory blocks PHP execution and directory listing via `.htaccess`.
- Duplicate email sending is prevented by a UNIQUE database key plus `INSERT IGNORE` during campaign creation.
- All database writes use prepared statements and transactions where appropriate (offer create/update/delete, campaign creation, resending).
- Every campaign action is audit-logged with the admin's identity.

---

## Deploying to a Live Server (Free Hosting)

This app is built with PHP + MySQL, so it runs on any standard PHP hosting provider — including several **free** options. Database credentials are read from environment variables or a `config.local.php` file (see below), so the same code works on XAMPP *and* on a live host without editing `db_connect.php`.

**Full step-by-step instructions are in [`DEPLOY.md`](DEPLOY.md).** Quick summary:

| Host | Free? | PHP + MySQL | Notes |
|------|-------|-------------|-------|
| **InfinityFree** | ✅ Yes | ✅ Yes | Recommended free host — 5 GB, phpMyAdmin, supports uploads |
| **000webhost** | ✅ Yes | ✅ Yes | Free but more limited (300 MB, sleeps) |
| **Hostinger / Namecheap** | ~$2–3/mo | ✅ Yes | Reliable for real production use |
| **GitHub Pages / Netlify / Vercel** | ✅ Yes | ❌ No | Static-only — **will not work** for this app |

### How database credentials work (deployment-ready)

`db_connect.php` reads credentials in this priority order:

1. **Environment variables** — `NLF_DB_HOST`, `NLF_DB_USER`, `NLF_DB_PASS`, `NLF_DB_NAME` (set via server config or `.htaccess` `SetEnv`)
2. **`config.local.php`** — a file you create next to `db_connect.php` (template provided as `config.example.php`). This file is protected from web access by `.htaccess`.
3. **XAMPP defaults** — `localhost` / `root` / empty / `newlife_fitness` (used when developing locally)

To deploy: copy `config.example.php` → `config.local.php`, fill in your host's DB credentials, and upload. No need to touch `db_connect.php`.

### Security hardening (included)

The project ships with three `.htaccess` files:
- **Root `.htaccess`** — security headers (X-Frame-Options, X-Content-Type-Options, XSS protection), disables directory listing, blocks access to `.sql`/`.md`/`config.local.php` files
- **`includes/.htaccess`** — denies all direct web access to PHP include files
- **`uploads/members/.htaccess`** — allows only image files to be served, disables PHP execution (prevents uploaded scripts from running), blocks directory listing

## Security Notes

- Admin passwords are hashed using PHP's `password_hash()` (bcrypt).
- All user input is handled via **prepared statements** (`mysqli` `bind_param`) to prevent SQL injection.
- Client-side **and** server-side validation on all forms.
- Delete actions require JavaScript confirmation.
- Unauthenticated requests to any admin page are redirected to the login screen.
- `.htaccess` files block direct access to `includes/`, config files, SQL files, and disable PHP execution in the uploads folders (`uploads/members/` and `uploads/offers/`).
- Database credentials can be stored in `config.local.php` (web-protected) or environment variables — never hardcoded or committed to version control.
- Offer email campaigns enforce duplicate-send protection via a UNIQUE database key and `INSERT IGNORE`, and every campaign action is audit-logged with the admin's identity.

---

## Color Scheme (derived from the logo)

| Role            | Color    | Hex           |
|-----------------|----------|---------------|
| Primary / Navy  | Navy     | `#1B3A5C` / `#16324A` |
| Accent / Gold   | Orange-gold | `#E8A33D` / `#D98C2B` |
| Secondary       | Steel blue | `#6FA8C9` |
| Metallic accent | Muted gold | `#C9A24B` |
| Content base    | Off-white | `#f4f6f9` |
| Text            | Charcoal | `#1f2933` |

Conventional green / red / yellow are used for success / error / warning alerts but styled to sit cleanly against the navy-and-gold theme.

---

## Optional / Future Scope (not in v1)

- QR-code–based attendance check-in
- Online payment gateway integration
- Diet & workout tracking
- SMS notifications
- Native mobile app

---

## Credits

Built as a branded management system for **New Life Fitness Club**. Emblem logo and all branding belong to New Life Fitness Club.
