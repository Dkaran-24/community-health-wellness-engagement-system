# 🚀 DEPLOY-CEP.md — Deploying the New Life Fitness **Community Engagement Project**

This guide deploys the **CEP edition** of New Life Fitness to a **separate, free
InfinityFree hosting account** — completely independent from any personal/hosted
edition of the project. The CEP is a college submission and must never share a
hosting account, database, or credentials with a personal deployment.

> **Golden rule:** PERSONAL PROJECT = separate hosting + separate database.
> CEP PROJECT = this guide. The two must never be mixed.

---

## 📋 What you need before starting

| Item | Where / Notes |
|---|---|
| `new-life-fitness-CEP.zip` (or this project folder) | The CEP package (see §9 for contents) |
| A **new** email address | For the CEP-only InfinityFree account |
| 30–40 minutes | First deployment typically takes this long |

The CEP ships with **no real credentials** inside. It contains a
`config.example.php` template, and `db_connect.php` falls back to XAMPP defaults
so the exact same code runs locally and on free hosting.

---

## 🎯 Overview — 4 phases

```
Phase A: Create the separate InfinityFree account  (CEP-only!)
Phase B: Upload the CEP files
Phase C: Create + populate the CEP database
Phase D: Configure, verify, and harden
```

---

## Phase A — Create the SEPARATE CEP hosting account

> ⚠️ **Why a separate account?** If the CEP were deployed to the personal
> account, a college evaluator logging in to the CEP admin panel could also
> reach (and potentially harm) the personal gym site and its database. Separate
> accounts keep the two deployments isolated — different FTP, different MySQL,
> different passwords. This is also a requirement of this project.

### A1. Sign up for InfinityFree (a fresh account)

1. Go to **https://infinityfree.com/registration** (or infinityfree.com → Sign Up)
2. Register using the **CEP-dedicated email address** — **not** any personal email
   that is already tied to a personal InfinityFree account.
3. Verify the account via the confirmation email, then log in to the client area:
   **https://app.infinityfree.com**

### A2. Create the hosting account

1. Client area → **"New Account"** → **"Create Account"**
2. Pick a free subdomain, for example:
   - `newlifefitness-cep.epizy.com`
   - `newlifefitnesscep.rf.gd`
   - or any available domain that clearly says this is the CEP site
3. Give it a label like "New Life Fitness — CEP" and click **Create**.
4. Wait for DNS/SSL to settle (usually a few minutes; can take up to ~10 min).
   The client area shows "✓ SSL Active" when ready.

### A3. Grab your hosting credentials (you'll need them in Phase B/C)

In the client area, open your new account's **Control Panel** and note:

| Credential | Looks like | Used for |
|---|---|---|
| **FTP hostname** | `ftpupload.net` | Uploading files |
| **FTP username** | `if0_12345678` | Uploading files |
| **FTP password** | (the account password) | Uploading files |
| **MySQL host** | `sqlXXX.infinityfree.com` (e.g. `sql300.infinityfree.com`) | Database |
| **MySQL username** | `if0_12345678` | Database |
| **MySQL database name** | `if0_12345678_XXX` (you create it in Phase C) | Database |

---

## Phase B — Upload the CEP files

You can upload by **FTP** (works everywhere) or by the online **file manager**
(suitable for zip packages). Use whichever you prefer.

### B1 (FTP). Upload via FileZilla

1. Download FileZilla from https://filezilla-project.org
2. Connect: **Host** `ftpupload.net`, **Username** `if0_12345678` (yours),
   **Password** (account password), **Port** `21`.
3. Navigate into `htdocs` on the remote side.
4. Upload the **contents** of the project folder (not the folder itself) into
   `htdocs` — so that `htdocs/index.php`, `htdocs/community/`, `htdocs/admin/`
   etc. exist. If you have the zip, upload it and extract via file manager.
5. Wait for all files to finish. Total upload ≈ 9–10 MB (mostly demo images).

### B2 (File Manager). Upload + extract the ZIP

1. Control Panel → **Online File Manager** (VistaPanel) → login
2. Open `htdocs`, then use **Upload** to send `new-life-fitness-CEP.zip`
   (file size limit ~10 MB per upload — the CEP zip fits in one go).
3. Extract the zip **into `htdocs`**, then verify `htdocs/index.php`,
   `htdocs/community/`, `htdocs/admin/`, `htdocs/includes/` all exist.
4. `cep_install.sql` can stay in `htdocs` — the root `.htaccess` blocks all
   `*.sql` files from web access.

---

## Phase C — Create + populate the CEP database

> **Import rule:** import ONE file — `cep_install.sql` — and then optionally
> `cep_seed.sql` for demo data. Never import the legacy `database.sql` /
> `offers_schema.sql` — those are kept only as build-time sources for
> `build_cep_install.py`.

### C1. Create the database

1. Control Panel → **MySQL Databases** (under "Databases").
2. Create a database, e.g. `if0_12345678_newlife_cep`.
3. Note the MySQL host (e.g. `sql300.infinityfree.com`) and user (your hosting user).
4. InfinityFree auto-creates the matching MySQL username — **there is no
   separate DB password to set**; the FTP/control-panel password is used for MySQL.

### C2. Import the schema via phpMyAdmin

1. Control Panel → **phpMyAdmin** → login with the MySQL credentials above.
2. Click the database name (e.g. `if0_12345678_newlife_cep`) in the left sidebar.
3. **Import** tab → **Choose file** → select `cep_install.sql` from the project
   → **Go**.
4. Wait for the green "Import has been successfully finished" message
   (36 tables will be created).
5. Optionally, for demo data: **Import** tab again → `cep_seed.sql` → **Go**.
   (Two separate imports — phpMyAdmin imports one file at a time.)

> ⚠️ **Known import quirks — read before reporting a failure:**
>
> 1. **`ERROR 1067 (Invalid default value)`** — you imported into a database
>    whose charset was not utf8mb4 (the installer has emoji defaults in the
>    email-campaign tables). Fix: phpMyAdmin → select the DB → **Operations** →
>    set Collation to `utf8mb4_general_ci` → retry the import.
> 2. **"No data was received to import"** — usually a slow/flaky upload of the
>    file to phpMyAdmin. Retry the import; if it persists, upload the .sql file
>    again via file manager and import from the server copy.
>
> (When importing from the **mysql CLI** locally, add
> `--default-character-set=utf8mb4` for the same reason.)

### C3. Set up `config.local.php`

1. In `htdocs` (via file manager or FTP), copy `config.example.php` to
   `config.local.php`.
2. Edit `config.local.php` and replace the placeholders with **your CEP host's**
   values:
   ```php
   $DB_HOST = 'sql300.infinityfree.com';   // your MySQL host
   $DB_USER = 'if0_12345678';              // your MySQL username
   $DB_PASS = 'your-hosting-account-password';  // same password as FTP/panel
   $DB_NAME = 'if0_12345678_newlife_cep';  // the database you created in C1
   ```
3. The root `.htaccess` already blocks web access to `config.local.php`, so the
   password inside it cannot be downloaded over HTTP. (Never commit this file
   to any public repository.)
4. Upload your edited `config.local.php` back into `htdocs`.
5. *(Advanced, optional)* Alternatively define environment variables
   `NLF_DB_HOST` / `NLF_DB_USER` / `NLF_DB_PASS` / `NLF_DB_NAME` —
   `db_connect.php` reads them with the highest priority.

---

## Phase D — Configure, verify, and harden

### D1. First visit + admin login

1. Browse to your CEP URL, e.g. `https://newlifefitness-cep.epizy.com/`
   — the public CEP homepage should appear with the hero
   "NEW LIFE FITNESS — Stronger Together, Healthier Community!".
2. **Login → Admin** → use `admin / admin123` (the default demo admin from
   `cep_install.sql`).

> 🛡️ **IMPORTANT — change the admin password immediately:** Admins → edit your
> account → set a strong personal password. The default is widely known (it
> ships in the installer SQL so the site is usable straight after import).

### D2. Set SMTP email settings (optional, but recommended)

The offers/email-campaign feature (and any future notifications) needs SMTP.

1. Control Panel → **Email Accounts** → create an email account, e.g.
   `no-reply@newlifefitness-cep.epizy.com`.
2. Note the SMTP host / user / password shown in the control panel.
3. Log in to the CEP admin → **Settings → Email/SMTP** → fill in host, port
   (usually 465 for SSL), SMTP user, password, From name & address → Save.
4. Admin → **Offer Management → Email Test** (`email-test.php`) → send a test
   email to yourself to confirm SMTP works.

### D2-extra. Uploads permissions (only if images fail to save)

InfinityFree doesn't need chmod. If member/offer photos fail to save, check in
the file manager that `htdocs/uploads/members/` and `htdocs/uploads/offers/`
exist and contain the `.htaccess` from the package (they block PHP execution
inside uploads while allowing images).

### D3. Hardening checklist (do this before submitting)

- [ ] Changed default admin password (`admin/admin123` → strong personal one)
- [ ] Deleted `diag.php` and `email-test.php` after confirming everything works
- [ ] `config.local.php` created and blocked (verify: `https://…/config.local.php` → 403)
- [ ] Visiting `/cep_install.sql` returns 403 (blocked by `.htaccess`)
- [ ] Visiting `/includes/header.php` returns 403 (blocked by `.htaccess`)
- [ ] Registration on the public site works (test with a real email you control)
- [ ] Event registration + feedback + volunteer flow works end-to-end
- [ ] Demo data clearly marked (demo emails end in `@community.demo`; impact
      dashboard carries an honest demo-data disclosure)

### D4. Wrap-up

1. Bookmark your CEP URL + admin panel URL.
2. Keep the local copy of the project for future edits, and re-upload changed
   files by FTP when you update something.
3. Keep a copy of `cep_install.sql` + `cep_seed.sql` offline for reinstalls.

---

## §5. Local development (XAMPP) — quick reference

1. Copy the project to `C:\xampp\htdocs\new-life-fitness` (or any folder).
2. Start Apache + MySQL in XAMPP. Create database `newlife_cep` (phpMyAdmin,
   collation `utf8mb4_general_ci`), import `cep_install.sql` then `cep_seed.sql`.
3. No config needed — `db_connect.php` falls back to `localhost / root / ""`.
4. Visit `http://localhost/new-life-fitness/` and login as
   `admin / admin123` (gym admin) or register a community account.

---

## §6. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| "Database connection failed" after deploy | No `config.local.php`, or wrong host/user/db in it | Create it per §C3; DB name must be the full `if0_12345678_xxx` |
| Import error 1067 invalid default value | DB charset not utf8mb4 | phpMyAdmin → Operations → Collation `utf8mb4_general_ci` → re-import |
| 500 errors on all pages | PHP version too old | Pick PHP 8.x in the InfinityFree control panel |
| Login redirects but looks "logged out" | Cookies / http-vs-https mismatch | Clear cookies for the site; always use the same URL scheme |
| Photos fail to upload | `uploads/` structure or .htaccess missing | Re-upload the whole `uploads/` folder from the package |
| Emails not sending | SMTP not configured | §D2 |
| Admin shows blank/white page | `display_errors=Off` hides the error | Check the control panel error log; usually a missing DB table → re-import `cep_install.sql` |

---

## §7. File upload limits on InfinityFree

- `post_max_size = 8M`, `upload_max_filesize = 6M` (set via the shipped `.user.ini`)
- `.user.ini` changes take up to 10 minutes to apply (host caches PHP config)
- Member photos (max 5 MB per photo) fit within these limits.

---

## §8. Demo accounts shipped in the seed (clearly labelled demo)

| Role | Login | Password | Notes |
|---|---|---|---|
| Gym/CEP Admin | `admin` | `admin123` | Change after first login (§D1) |
| Community user | `priya.sharma@community.demo` | `Community@123` | Demo account |
| Community user | `rahul.verma@community.demo` | `Community@123` | Demo account |
| Community user | `anjali.deshmukh@community.demo` | `Community@123` | Demo account |

All demo community users share the password `Community@123`. Every demo record
is labelled demo (demo emails end in `@community.demo`), and the impact dashboard
carries an honest "demo data" disclosure.

---

## §9. What's inside the CEP ZIP

```
new-life-fitness-CEP.zip
├── index.php               # public CEP homepage
├── login.php / logout.php  # admin entry
├── db_connect.php          # env → config.local.php → XAMPP fallback
├── config.example.php      # template for hosting credentials
├── .htaccess               # blocks .sql/.md/.py + config + includes/docs/tests
├── .user.ini               # upload limits for shared hosting
├── cep_install.sql         # ONE-FILE installer (36 tables) — import this
├── cep_seed.sql            # demo data (optional import)
├── database.sql / offers_schema.sql  # legacy build sources (kept for history)
├── build_cep_install.py    # local build tool (not needed on hosting)
├── community/              # public-facing community pages
├── admin/                  # full gym admin + admin/community/* modules
├── member/                 # member portal (gym members)
├── includes/               # PHPMailer, ai_*.php, auth, helpers, csrf, mailer
├── uploads/                # demo images (with PHP-blocked .htaccess)
├── docs/                   # AI_DOCUMENTATION.md etc.
├── tests/                  # smoke + e2e scripts (dev only)
├── DEPLOY-CEP.md           # this guide
├── README-CEP.md           # project overview
└── README-DEV-CONFIG.md    # why config.local.php is absent
```

---

## §10. Rollback / reinstall

To reset the CEP database: phpMyAdmin → select DB → **Check All** tables →
**With selected: Drop**. Then re-import `cep_install.sql` (+ optionally
`cep_seed.sql`). To reset the hosting: delete `htdocs` contents and re-upload
the package.

---

## §11. Separation guarantees (recap)

1. The CEP zip contains **no personal credentials** — `config.local.php` was
   deliberately excluded (see `README-DEV-CONFIG.md`).
2. The CEP deploys to its **own InfinityFree account** with its **own database**.
3. The personal project's files were never modified to build the CEP — the CEP
   is a separate copy that was extended.
