<?php
/**
 * admin/settings/index.php — Email SMTP settings configuration.
 *
 * Lets the admin configure SMTP credentials so the system can send
 * payment receipts and membership renewal emails to members.
 *
 * InfinityFree blocks PHP mail(), so we MUST use external SMTP.
 * Works with Gmail, Brevo (Sendinblue), SMTP2GO, SendGrid, Mailgun, etc.
 *
 * Setup steps for Gmail (default):
 *   1. Enable 2-Step Verification on your Gmail account
 *   2. Generate an App Password (16 chars) at myaccount.google.com/apppasswords
 *   3. Enter your Gmail address + App Password below
 */
$PAGE_TITLE = 'Email Settings';
$PAGE_KEY   = 'settings';
require_once __DIR__ . '/../../includes/header.php';
$db = db();

/* ── Handle form submission ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test') {
        /* Send a test email */
        $testEmail = trim($_POST['test_email'] ?? '');
        if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            header('Location: index.php?err=' . urlencode('Please enter a valid test email address.'));
            exit;
        }
        require_once __DIR__ . '/../../includes/mailer.php';
        $settings = get_settings();
        $cfg = getSmtpSettings($db);
        $providerLabel = ($cfg['provider'] === 'brevo_api') ? 'Brevo API' : 'SMTP';
        $html = "<div style='font-family:Segoe UI,Arial,sans-serif;padding:20px;'>
                   <h2 style='color:#2e9e5b;'>✅ Test Email Successful!</h2>
                   <p>This confirms that your {$providerLabel} email settings are working correctly.</p>
                   <p><strong>" . e($settings['gym_name'] ?? 'New Life Fitness Club') . "</strong> can now send
                      payment receipts and membership renewal emails to members.</p>
                   <hr><p style='color:#999;font-size:12px;'>Sent: " . date('Y-m-d H:i:s') . "</p>
                 </div>";
        // Use the universal dispatcher — routes to Brevo API or SMTP automatically
        $result = sendEmail($db, $testEmail, 'Test Recipient', "Test Email — {$providerLabel} Settings OK", $html, '', true);
        if ($result['ok']) {
            header('Location: index.php?ok=' . urlencode('✅ Test email sent successfully to ' . $testEmail . '! Check the inbox (and spam folder).'));
        } else {
            // Show the error message directly on the settings page.
            // Keep it short for the URL; the full SMTP log is available on
            // the Email Diagnostic page (email-test.php in the sidebar).
            $detail = $result['error'];
            // Truncate to avoid URL-length issues
            if (strlen($detail) > 500) {
                $detail = substr($detail, 0, 500) . '... (See Email Diagnostic page for full details)';
            }
            header('Location: index.php?err=' . urlencode('Test email failed: ' . $detail));
        }
        exit;
    }

    /* Save SMTP/Brevo settings */
    $enabled   = isset($_POST['smtp_enabled']) ? 1 : 0;
    $provider  = $_POST['email_provider'] ?? 'smtp';     // 'smtp' or 'brevo_api'
    $brevoKey  = trim($_POST['brevo_api_key'] ?? '');     // Brevo API key
    $host      = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
    $port      = (int)($_POST['smtp_port'] ?? 587);
    $secure    = $_POST['smtp_secure'] ?? 'tls';
    $username  = trim($_POST['smtp_username'] ?? '');
    $password  = trim($_POST['smtp_password'] ?? '');
    $fromName  = trim($_POST['smtp_from_name'] ?? 'New Life Fitness Club');
    $fromEmail = trim($_POST['smtp_from_email'] ?? '');

    // If from_email is blank, default it to the SMTP username (Gmail-style
    // where login == from address). For Brevo etc., the admin must set it
    // explicitly to their verified sender email.
    if ($fromEmail === '') {
        $fromEmail = $username;
    }

    // Auto-enable email sending when valid credentials are provided, so the
    // admin doesn't have to separately tick the "Enable" checkbox.
    if ($provider === 'brevo_api') {
        if ($brevoKey !== '' && $fromEmail !== '') { $enabled = 1; }
    } else {
        if ($username !== '' && $password !== '') { $enabled = 1; }
        // If no password entered but we already have one saved, keep enabled
        if ($password === '' && $username !== '') { $enabled = 1; }
    }

    try {
        // Temporarily turn OFF exception-throwing so that a failed ALTER TABLE
        // (e.g. duplicate columns) doesn't crash the page with a blank screen.
        // We check errors manually instead.
        mysqli_report(MYSQLI_REPORT_OFF);

        // Check if SMTP columns exist before trying to save
        $colCheck = $db->query("SHOW COLUMNS FROM settings LIKE 'smtp_host'");
        $smtpColsExist = $colCheck && $colCheck->num_rows > 0;

        if (!$smtpColsExist) {
            // Columns don't exist — run the ALTER TABLE automatically.
            @$db->query("ALTER TABLE settings
                ADD COLUMN smtp_enabled    TINYINT(1)    DEFAULT 0,
                ADD COLUMN smtp_host       VARCHAR(120)  DEFAULT 'smtp.gmail.com',
                ADD COLUMN smtp_port       INT           DEFAULT 587,
                ADD COLUMN smtp_secure     VARCHAR(10)   DEFAULT 'tls',
                ADD COLUMN smtp_username   VARCHAR(120)  DEFAULT NULL,
                ADD COLUMN smtp_password   VARCHAR(200)  DEFAULT NULL,
                ADD COLUMN smtp_from_name  VARCHAR(120)  DEFAULT 'New Life Fitness Club',
                ADD COLUMN smtp_from_email VARCHAR(150)  DEFAULT NULL");
        }

        // smtp_from_email was added later — make sure it exists even on
        // installs where the columns were created by an older version.
        $fecCheck = @$db->query("SHOW COLUMNS FROM settings LIKE 'smtp_from_email'");
        if ($fecCheck && $fecCheck->num_rows === 0) {
            @$db->query("ALTER TABLE settings ADD COLUMN smtp_from_email VARCHAR(150) DEFAULT NULL");
        }

        // Auto-migrate: add email_provider + brevo_api_key columns if missing
        $epCheck = @$db->query("SHOW COLUMNS FROM settings LIKE 'email_provider'");
        if ($epCheck && $epCheck->num_rows === 0) {
            @$db->query("ALTER TABLE settings ADD COLUMN email_provider VARCHAR(20) DEFAULT 'smtp'");
            @$db->query("ALTER TABLE settings ADD COLUMN brevo_api_key VARCHAR(200) DEFAULT NULL");
        }

        // If password field is empty, keep the existing password (don't overwrite)
        // Same for Brevo API key — if blank, keep existing.
        $keepPassword = ($password === '');
        $keepBrevoKey = ($brevoKey === '');

        if ($keepPassword && $keepBrevoKey) {
            $stmt = $db->prepare("UPDATE settings SET smtp_enabled=?, email_provider=?, smtp_host=?, smtp_port=?, smtp_secure=?, smtp_username=?, smtp_from_name=?, smtp_from_email=? WHERE id=1");
            if (!$stmt) {
                throw new \Exception('Database prepare failed: ' . $db->error);
            }
            $stmt->bind_param('ississss', $enabled, $provider, $host, $port, $secure, $username, $fromName, $fromEmail);
        } elseif ($keepPassword && !$keepBrevoKey) {
            $stmt = $db->prepare("UPDATE settings SET smtp_enabled=?, email_provider=?, brevo_api_key=?, smtp_host=?, smtp_port=?, smtp_secure=?, smtp_username=?, smtp_from_name=?, smtp_from_email=? WHERE id=1");
            if (!$stmt) {
                throw new \Exception('Database prepare failed: ' . $db->error);
            }
            $stmt->bind_param('isssissss', $enabled, $provider, $brevoKey, $host, $port, $secure, $username, $fromName, $fromEmail);
        } elseif (!$keepPassword && $keepBrevoKey) {
            $stmt = $db->prepare("UPDATE settings SET smtp_enabled=?, email_provider=?, smtp_host=?, smtp_port=?, smtp_secure=?, smtp_username=?, smtp_password=?, smtp_from_name=?, smtp_from_email=? WHERE id=1");
            if (!$stmt) {
                throw new \Exception('Database prepare failed: ' . $db->error);
            }
            $stmt->bind_param('ississsss', $enabled, $provider, $host, $port, $secure, $username, $password, $fromName, $fromEmail);
        } else {
            $stmt = $db->prepare("UPDATE settings SET smtp_enabled=?, email_provider=?, brevo_api_key=?, smtp_host=?, smtp_port=?, smtp_secure=?, smtp_username=?, smtp_password=?, smtp_from_name=?, smtp_from_email=? WHERE id=1");
            if (!$stmt) {
                throw new \Exception('Database prepare failed: ' . $db->error);
            }
            $stmt->bind_param('isssisssss', $enabled, $provider, $brevoKey, $host, $port, $secure, $username, $password, $fromName, $fromEmail);
        }

        if ($stmt->execute()) {
            $stmt->close();
            // Also update gym contact info if provided
            $gymName  = trim($_POST['gym_name'] ?? '');
            $gymAddr  = trim($_POST['address'] ?? '');
            $gymTel   = trim($_POST['contact'] ?? '');
            $gymEmail = trim($_POST['gym_email'] ?? '');
            if ($gymName || $gymAddr || $gymTel || $gymEmail) {
                $stmt2 = $db->prepare("UPDATE settings SET gym_name=?, address=?, contact=?, email=? WHERE id=1");
                if ($stmt2) {
                    $stmt2->bind_param('ssss', $gymName, $gymAddr, $gymTel, $gymEmail);
                    @$stmt2->execute();
                    $stmt2->close();
                }
            }
            // Clear the static settings cache so new values take effect immediately
            if (function_exists('get_settings')) {
                get_settings(true);
            }
            header('Location: index.php?ok=' . urlencode('Email settings saved successfully. Now click "Send Test Email" below to verify ' . ($provider === 'brevo_api' ? 'Brevo API' : 'SMTP') . ' is working.'));
        } else {
            $err = $stmt->error;
            $stmt->close();
            header('Location: index.php?err=' . urlencode('Failed to save: ' . $err));
        }
    } catch (\Throwable $e) {
        // Catch ANY exception (mysqli_sql_exception, etc.) so the user sees a
        // readable error message instead of a blank page.
        header('Location: index.php?err=' . urlencode('Save error: ' . $e->getMessage()));
    }
    exit;
}

/* ── Load current settings ── */
$smtp = null;
$res = $db->query("SELECT * FROM settings LIMIT 1");
if ($res) $smtp = $res->fetch_assoc();
if (!$smtp) $smtp = [];

// Check if SMTP columns exist (migration might not be run yet)
$hasSmtpColumns = isset($smtp['smtp_host']);
$hasPassword = !empty($smtp['smtp_password']);
// Brevo API state (columns are auto-added by getSmtpSettings / save handler)
$emailProvider = $smtp['email_provider'] ?? 'smtp';
$hasBrevoKey   = !empty($smtp['brevo_api_key']);
?>

<?= flash() ?>

<?php if (!$hasSmtpColumns): ?>
<div class="alert err" style="margin-bottom:20px;">
  ⚠️ <strong>Database migration needed!</strong> The SMTP columns are missing from your database.
  Please run the SQL migration in phpMyAdmin first:
  <pre style="background:#fff;padding:12px;border-radius:6px;margin:10px 0;font-size:13px;overflow-x:auto;">ALTER TABLE settings
  ADD COLUMN smtp_enabled    TINYINT(1)    DEFAULT 0,
  ADD COLUMN smtp_host       VARCHAR(120)  DEFAULT 'smtp.gmail.com',
  ADD COLUMN smtp_port       INT           DEFAULT 587,
  ADD COLUMN smtp_secure     VARCHAR(10)   DEFAULT 'tls',
  ADD COLUMN smtp_username   VARCHAR(120)  DEFAULT NULL,
  ADD COLUMN smtp_password   VARCHAR(200)  DEFAULT NULL,
  ADD COLUMN smtp_from_name  VARCHAR(120)  DEFAULT 'New Life Fitness Club',
  ADD COLUMN smtp_from_email VARCHAR(150)  DEFAULT NULL;</pre>
  Go to InfinityFree → phpMyAdmin → your database → SQL tab → paste & run.
</div>
<?php endif; ?>

<div class="page-head">
  <div><h2>Email Settings</h2><p>Configure how payment receipts &amp; membership renewal emails are sent to members. Choose <strong>Brevo API</strong> (recommended on InfinityFree &mdash; uses HTTPS, never blocked) or classic <strong>SMTP</strong> (Gmail, Brevo SMTP, SMTP2GO, etc.).</p></div>
</div>

<!-- Deliverability notice -->
<div class="card" style="margin-bottom:20px;border-left:4px solid #2e9e5b;">
  <div class="card-body" style="padding:14px 20px;">
    <h3 style="margin:0 0 8px;color:#2e9e5b;font-size:15px;">✅ Inbox Deliverability Enabled</h3>
    <p style="margin:0;font-size:13px;color:var(--muted);line-height:1.6;">
      Emails include proper <strong>Message-ID matching your From domain</strong>, <code>Auto-Submitted</code>,
      <code>Precedence</code>, and <code>Return-Path</code> headers to prevent them from landing in spam.
      <br><strong>For best inbox delivery, use Brevo or SMTP2GO</strong> instead of Gmail-to-Gmail (which Gmail heavily filters as automated). <strong>The From Email must be a verified sender</strong> in your provider's dashboard.
    </p>
  </div>
</div>

<!-- Info Banner -->
<div class="card" style="margin-bottom:20px;border-left:4px solid var(--navy-500);">
  <div class="card-body" style="padding:16px 20px;">
    <h3 style="margin:0 0 10px;color:var(--navy-700);font-size:16px;">📋 Choose your email provider</h3>
    <div style="font-size:14px;color:var(--muted);line-height:1.7;">
      <p style="margin:0 0 6px;"><strong style="color:#2e9e5b;">🟢 Brevo API (RECOMMENDED on InfinityFree):</strong></p>
      <p style="margin:0 0 4px;padding-left:16px;">Uses the Brevo <em>REST API</em> over <strong>HTTPS (port 443)</strong>, which InfinityFree never blocks. SMTP ports 25/465/587 are blocked on the free tier.</p>
      <p style="margin:0 0 4px;padding-left:16px;">1. Create a free account at <a href="https://www.brevo.com" target="_blank" style="color:var(--navy-500);">brevo.com</a> (300 emails/day free).</p>
      <p style="margin:0 0 4px;padding-left:16px;">2. Go to <strong>Settings → Senders &amp; IP → Add a Sender</strong>, enter your From Email, and verify it via the 6-digit code.</p>
      <p style="margin:0 0 10px;padding-left:16px;">3. Go to <strong>Settings → SMTP &amp; API → API Keys</strong>, generate a <strong>new API key</strong> and paste it in the <em>Brevo API Key</em> field below.</p>
      <p style="margin:0 0 6px;"><strong style="color:var(--navy-700);">📮 Brevo SMTP / Gmail (SMTP method):</strong></p>
      <p style="margin:0 0 4px;padding-left:16px;"><strong>Brevo SMTP:</strong> SMTP Host <code>smtp-relay.brevo.com</code>, port 587, TLS. Username = the SMTP login (xxx@smtp-brevo.com), password = an SMTP key from Settings → SMTP &amp; API. <em>Note: SMTP may be blocked on InfinityFree free hosting — use Brevo API instead.</em></p>
      <p style="margin:0 0 4px;padding-left:16px;"><strong>Gmail:</strong> Enable <a href="https://myaccount.google.com/security" target="_blank" style="color:var(--navy-500);">2-Step Verification</a>, generate a <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:var(--navy-500);">16-char App Password</a>. Use your Gmail address as From Email and SMTP Username, App Password as SMTP Password.</p>
    </div>
  </div>
</div>

<!-- SMTP Settings Form -->
<div class="card">
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="action" value="save">

      <h3 style="margin:0 0 16px;color:var(--navy-700);font-size:18px;border-bottom:1px solid var(--border);padding-bottom:10px;">Email Configuration</h3>

      <div class="form-grid">
        <div class="form-field full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="smtp_enabled" value="1" <?= !empty($smtp['smtp_enabled']) ? 'checked' : '' ?>
                   style="width:18px;height:18px;cursor:pointer;">
            <span><strong>Enable Email Sending</strong> &mdash; when checked, payment receipts will be emailed to members automatically. (Auto-enabled when you save valid credentials below.)</span>
          </label>
        </div>

        <div class="form-field full">
          <label>Email Provider <span class="req">*</span></label>
          <select name="email_provider" id="email_provider" onchange="toggleProvider()">
            <option value="brevo_api" <?= ($emailProvider === 'brevo_api') ? 'selected' : '' ?>>Brevo API (HTTPS &mdash; recommended on InfinityFree)</option>
            <option value="smtp" <?= ($emailProvider !== 'brevo_api') ? 'selected' : '' ?>>SMTP (Gmail / Brevo SMTP / SMTP2GO / SendGrid)</option>
          </select>
          <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block;">
            <strong>Brevo API</strong> sends via HTTPS (port 443) &mdash; never blocked by InfinityFree. <strong>SMTP</strong> uses ports 25/465/587 which InfinityFree free tier blocks, causing &ldquo;Could not authenticate&rdquo; errors.
          </small>
        </div>

        <!-- Brevo API fields (shown when provider = brevo_api) -->
        <div class="form-field full" id="brevo_fields" style="display:<?= ($emailProvider === 'brevo_api') ? 'block' : 'none' ?>;">
          <label>Brevo API Key <?= $hasBrevoKey ? '' : '<span class="req">*</span>' ?></label>
          <input type="password" name="brevo_api_key"
                 placeholder="<?= $hasBrevoKey ? '•••••••••••••••••••••• (saved — leave blank to keep)' : 'Paste your Brevo API key (starts with xkeysib-...)' ?>">
          <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block;">
            Get this from Brevo → Settings → SMTP &amp; API → API Keys tab → <strong>Generate a new API key</strong>. Copy the full key (it starts with <code>xkeysib-</code>).
          </small>
        </div>
      </div>

      <!-- SMTP fields (shown when provider = smtp) -->
      <div id="smtp_fields" style="display:<?= ($emailProvider !== 'brevo_api') ? 'block' : 'none' ?>;">
      <h3 style="margin:24px 0 16px;color:var(--navy-700);font-size:16px;border-bottom:1px solid var(--border);padding-bottom:8px;">SMTP Details</h3>

      <div class="form-grid">
        <div class="form-field">
          <label>SMTP Host</label>
          <input type="text" name="smtp_host" value="<?= e($smtp['smtp_host'] ?? 'smtp.gmail.com') ?>"
                 placeholder="smtp.gmail.com">
        </div>

        <div class="form-field">
          <label>SMTP Port</label>
          <input type="number" name="smtp_port" value="<?= e($smtp['smtp_port'] ?? 587) ?>"
                 placeholder="587">
        </div>

        <div class="form-field">
          <label>Encryption</label>
          <select name="smtp_secure">
            <option value="tls" <?= ($smtp['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (recommended, port 587)</option>
            <option value="ssl" <?= ($smtp['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (port 465)</option>
            <option value="" <?= ($smtp['smtp_secure'] ?? '') === '' ? 'selected' : '' ?>>None</option>
          </select>
        </div>

        <div class="form-field">
          <label>SMTP Username (Login) <span class="req">*</span></label>
          <input type="text" name="smtp_username" value="<?= e($smtp['smtp_username'] ?? '') ?>"
                 placeholder="Gmail: your@email.com  &nbsp;|&nbsp;  Brevo: xxx@smtp-brevo.com">
          <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block;">
            The login your SMTP server expects. Gmail: your email address. Brevo: the SMTP login from Settings &rarr; SMTP &amp; API (looks like xxx@smtp-brevo.com).
          </small>
        </div>

        <div class="form-field">
          <label>SMTP Password (App Password / Key) <?= $hasPassword ? '' : '<span class="req">*</span>' ?></label>
          <input type="password" name="smtp_password"
                 placeholder="<?= $hasPassword ? '•••••••••••••••• (saved — leave blank to keep)' : '16-character App Password (Gmail) or SMTP key' ?>">
        </div>
      </div>
      </div><!-- /smtp_fields -->

      <h3 style="margin:24px 0 16px;color:var(--navy-700);font-size:18px;border-bottom:1px solid var(--border);padding-bottom:10px;">Sender &amp; Gym Contact Info (shown in emails)</h3>

      <div class="form-grid">
        <div class="form-field">
          <label>From Name</label>
          <input type="text" name="smtp_from_name" value="<?= e($smtp['smtp_from_name'] ?? 'New Life Fitness Club') ?>"
                 placeholder="New Life Fitness Club">
        </div>

        <div class="form-field">
          <label>From Email (Sender Address) <span class="req">*</span></label>
          <input type="email" name="smtp_from_email" value="<?= e($smtp['smtp_from_email'] ?? '') ?>"
                 placeholder="your-sender@email.com" required>
          <small style="color:var(--muted);font-size:12px;margin-top:4px;display:block;">
            The address recipients see in the &ldquo;From&rdquo; field. For Brevo API/SMTP, this must be a <strong>verified sender</strong> in your Brevo dashboard. For Gmail, this is your Gmail address.
          </small>
        </div>

        <div class="form-field">
          <label>Gym Name</label>
          <input type="text" name="gym_name" value="<?= e($smtp['gym_name'] ?? 'New Life Fitness Club') ?>">
        </div>
        <div class="form-field">
          <label>Contact Phone</label>
          <input type="text" name="contact" value="<?= e($smtp['contact'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Gym Email (shown in receipt)</label>
          <input type="email" name="gym_email" value="<?= e($smtp['email'] ?? '') ?>">
        </div>
        <div class="form-field full">
          <label>Address</label>
          <input type="text" name="address" value="<?= e($smtp['address'] ?? '') ?>">
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary">&#10003; Save Settings</button>
      </div>
    </form>
  </div>
</div>

<!-- Test Email -->
<div class="card" style="margin-top:20px;">
  <div class="card-body">
    <h3 style="margin:0 0 16px;color:var(--navy-700);font-size:18px;">📧 Send Test Email</h3>
    <p style="color:var(--muted);font-size:14px;margin:0 0 16px;">
      After saving your SMTP settings, send a test email to verify everything works.
    </p>
    <form method="post" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="action" value="test">
      <div class="form-field" style="flex:1;min-width:250px;margin:0;">
        <label>Test Email Address</label>
        <input type="email" name="test_email" placeholder="any-email@example.com" required>
      </div>
      <button class="btn btn-navy">📨 Send Test Email</button>
    </form>
  </div>
</div>

<!-- Gym Announcements link -->
<div class="card" style="margin-top:20px;">
  <div class="card-body" style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <h3 style="margin:0 0 6px;color:var(--navy-700);font-size:18px;">📢 Gym Announcements</h3>
      <p style="color:var(--muted);font-size:14px;margin:0;">Send push notifications to all members about gym events, holidays, and offers.</p>
    </div>
    <a href="announcements.php" class="btn btn-primary">Manage Announcements →</a>
  </div>
</div>

<!-- JavaScript: toggle SMTP vs Brevo API fields based on provider selection -->
<script>
function toggleProvider() {
  var sel   = document.getElementById('email_provider');
  var smtp  = document.getElementById('smtp_fields');
  var brevo = document.getElementById('brevo_fields');
  if (!sel) return;
  if (sel.value === 'brevo_api') {
    if (brevo) brevo.style.display = 'block';
    if (smtp)  smtp.style.display  = 'none';
  } else {
    if (brevo) brevo.style.display = 'none';
    if (smtp)  smtp.style.display  = 'block';
  }
}
// Run on page load to sync display with saved provider
(function(){ toggleProvider(); })();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
