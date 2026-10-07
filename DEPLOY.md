> ⚠️ **THIS IS THE LEGACY GUIDE FOR THE ORIGINAL GYM SYSTEM DEPLOYMENT.**
> For deploying **this Community Engagement Project (CEP)**, use
> **[`DEPLOY-CEP.md`](DEPLOY-CEP.md)** instead — the CEP must go to its own
> separate hosting account and its own database (never the personal deployment).

---

# 🚀 Deployment Guide — New Life Fitness Club (Free Hosting)

This guide walks you through deploying the Gym Management System to a **free PHP + MySQL host**. The recommended free host is **InfinityFree**, but the same steps apply to 000webhost and most other cPanel-style shared hosts.

---

## Prerequisites

- The `new-life-fitness.zip` file (the project package)
- An email address to sign up for free hosting
- 15–20 minutes

---

## Option A — InfinityFree (Recommended, free) ⭐

### Step 1 — Sign up for InfinityFree

1. Go to **https://infinityfree.com**
2. Click **"Sign Up"** and create an account with your email
3. Check your email and confirm/verify the account
4. Log in to the **client area** (https://app.infinityfree.com)

### Step 2 — Create a hosting account

1. In the client area, click **"New Account"** → **"Create Account"**
2. Choose a **free subdomain**, for example:
   - `newlifefitness.epizy.com` (or pick from the available domains)
   - Or use your own domain if you have one (still free to host)
3. Enter a label (e.g. "New Life Fitness") and click **"Create"**
4. Wait ~2 minutes for the account to activate
5. You'll see your account details including:
   - **Control Panel URL** (cPanel)
   - **FTP details** (host, username, password)
   - **Nameservers** (if using your own domain)

### Step 3 — Create a MySQL database

1. Log in to the **Control Panel** (cPanel) for your account
2. Scroll to the **"Databases"** section → click **"MySQL Databases"**
3. Under **"Create New Database"**, enter a name like `newlife_fitness`
   - Your full database name will be prefixed, e.g. `if0_12345678_newlife_fitness`
   - **Write this down — you'll need it**
4. Click **"Create Database"**
5. Under **"MySQL Users"**, create a new user:
   - Username: e.g. `gymadmin`
   - Password: choose a strong password (and **write it down**)
   - Your full username will be prefixed, e.g. `if0_12345678_gymadmin`
6. **Add the user to the database** — select both, click **"Add"**, and grant **ALL PRIVILEGES**

### Step 4 — Import the database schema

1. In the Control Panel, go to **"phpMyAdmin"** (in the Databases section)
2. Select your new database from the left sidebar
3. Click the **"Import"** tab at the top
4. Click **"Choose File"** and select `database.sql` from the unzipped project folder
5. Scroll down and click **"Go"** (or "Import")
6. You should see a success message: *"Import has been successfully finished"*
7. Verify: click your database in the left sidebar — you should see 7 tables:
   `admins`, `attendance`, `fees`, `members`, `membership_plans`, `settings`, `trainers`

### Step 5 — Configure your database credentials

1. In the unzipped project folder, find `config.example.php`
2. **Make a copy** of it and rename the copy to `config.local.php`
3. Open `config.local.php` in a text editor and fill in your InfinityFree details:

```php
<?php
$DB_HOST = 'sqlXXX.infinityfree.com';           // ← see below how to find this
$DB_USER = 'if0_12345678_gymadmin';             // your DB username from Step 3
$DB_PASS = 'your_strong_password';              // the password you set in Step 3
$DB_NAME = 'if0_12345678_newlife_fitness';      // your full DB name from Step 3
```

**Finding your DB host:** In the Control Panel → "MySQL Databases", scroll down to your database — the **hostname** is listed there (it looks like `sql101.infinityfree.com` or `sql203.byethost3.org`).

> 💡 The `.htaccess` file included in the project **blocks web access to `config.local.php`**, so even if someone guesses the URL, they can't download your password.

### Step 6 — Upload the project files

You have two options:

#### Option 1 — File Manager (easiest, no software needed)
1. In the Control Panel, open **"Online File Manager"**
2. Navigate to the **`htdocs`** folder (this is your web root — files here are public)
3. Click **"Upload"** and upload the `new-life-fitness.zip` file
4. Right-click the zip → **"Extract"** → extract into `htdocs/`
5. You should now have `htdocs/new-life-fitness/index.php`, `htdocs/new-life-fitness/login.php`, etc.
6. Make sure `uploads/members/` exists and is writable:
   - Right-click `uploads/members` → **"Permissions"** → set to `755` (or `775`)

#### Option 2 — FTP (better for large uploads)
1. Download **FileZilla** (https://filezilla-project.org/)
2. Connect using your FTP details from Step 2:
   - Host: `ftpupload.net` (or the host shown in your account)
   - Username: your InfinityFree FTP username (e.g. `if0_12345678`)
   - Password: your InfinityFree account password
   - Port: `21`
3. Navigate to the `htdocs` folder on the server (right pane)
4. Drag the entire `new-life-fitness` folder from your computer (left pane) into `htdocs`
5. Wait for the upload to complete
6. Right-click `uploads/members` → "File permissions" → set numeric value to `755`

### Step 7 — Visit your live site! 🎉

1. Open your browser and go to:
   ```
   http://newlifefitness.epizy.com/new-life-fitness/login.php
   ```
   (replace with your actual subdomain and folder name)
2. Log in with the admin credentials:
   - **Username:** `admin`
   - **Password:** `admin123`
3. You should see the dashboard! 🎊

### Step 8 — IMPORTANT: Change the admin password

For security, immediately change the default admin password:
1. Go to **Members** or use phpMyAdmin to update the `admins` table
2. Generate a new bcrypt hash:
   - Use an online bcrypt generator, or
   - In phpMyAdmin, run:
     ```sql
     UPDATE admins SET password_hash = '$2y$10$YOUR_NEW_BCRYPT_HASH_HERE' WHERE username = 'admin';
     ```
   - Or create a temporary PHP file `genhash.php` in htdocs with:
     ```php
     <?php echo password_hash('yournewpassword', PASSWORD_DEFAULT);
     ```
     Visit it in your browser, copy the hash, then **delete the file**.

---

## Option B — 000webhost (alternative free host)

The steps are nearly identical:
1. Sign up at **https://www.000webhost.com**
2. Create a site → go to **Manage Website** → **File Manager**
3. Create a MySQL database under **"Manage Database"** → note the host (usually `localhost` on 000webhost), username, password, and name
4. Import `database.sql` via their built-in phpMyAdmin
5. Create `config.local.php` with your 000webhost DB credentials
6. Upload the `new-life-fitness` folder to the `public_html` directory
7. Visit `https://yoursite.000webhostapp.com/new-life-fitness/login.php`

---

## 🔒 Security Checklist After Deployment

- [ ] Changed the default admin password (`admin123` → something strong)
- [ ] `config.local.php` exists and has real credentials (NOT the example values)
- [ ] `uploads/members/` directory is writable (chmod 755)
- [ ] Tested that you can add a member with a photo upload
- [ ] Verified `.htaccess` is working (try visiting `https://yoursite.com/new-life-fitness/includes/helpers.php` → should get **403 Forbidden**)
- [ ] Verified the database connection works (dashboard loads with real data)

---

## 🌐 Want a Custom Domain?

InfinityFree lets you use your own domain for free:
1. Buy a domain (~$10/year from Namecheap, Cloudflare, etc.)
2. In the InfinityFree client area → **"Custom Domains"** → add your domain
3. At your domain registrar, point the nameservers to InfinityFree's (shown in your account)
4. Wait 24–48 hours for DNS to propagate
5. Free SSL: InfinityFree provides free Let's Encrypt certificates — enable it in the Control Panel → "SSL/TLS"

---

## 🆘 Troubleshooting

| Problem | Solution |
|---------|----------|
| **"Database connection failed"** | Check `config.local.php` — the host, user, pass, or name is wrong. Double-check against the "MySQL Databases" page in cPanel. |
| **Blank white page** | Add `display_errors` — create a `.user.ini` file in `htdocs` with `display_errors = On` to see the error, then remove it. |
| **Photos don't upload** | Make sure `uploads/members/` exists and has `755` permissions. Check that the folder is writable. |
| **403 Forbidden on everything** | Some hosts don't allow all `.htaccess` directives. Try temporarily renaming `.htaccess` to `.htaccess.bak` — if the site works, your host restricts certain directives. Remove the offending lines. |
| **Can't access phpMyAdmin** | It's in the Control Panel under "Databases" → "phpMyAdmin", not at `/phpmyadmin` directly. |
| **"No input file specified"** | You're hitting the folder root instead of a PHP file. Always include `login.php` in the URL. |
| **Page loads but no styles/images** | You may have uploaded the *contents* of `new-life-fitness/` instead of the *folder itself*. The CSS is at `new-life-fitness/assets/css/style.css` — make sure that path exists on the server. |

---

## 📊 Free Hosting Limitations to Keep in Mind

| Limitation | Impact | Mitigation |
|------------|--------|------------|
| Account sleeps after 30–90 days of no visits | Site goes offline | Visit periodically, or use a free uptime monitor (e.g. UptimeRobot) to ping it |
| No automated backups | You could lose data | Export your database via phpMyAdmin regularly |
| Shared IP / resources | Occasional slow periods | Upgrade to paid hosting if speed matters |
| Bandwidth/disk limits | Fine for a small gym | Monitor usage in the Control Panel |
| No email sending | Receipts can't be emailed | Not a feature of this app currently, so no impact |

For a **real gym business** in production, consider upgrading to a paid shared host (~$2–3/month) or a VPS (~$5/month) for reliability, backups, and a custom domain without the free-host limitations.
