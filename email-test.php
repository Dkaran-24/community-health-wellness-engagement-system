<?php
/**
 * email-test.php — Email diagnostic & test tool for New Life Fitness Club.
 *
 * Visit this in your browser (after logging in as admin):
 *   https://yourdomain.com/new-life-fitness/email-test.php
 *
 * It checks, step by step:
 *   1. PHPMailer library is installed
 *   2. SMTP columns exist in the settings table
 *   3. Email sending is enabled + credentials are saved
 *   4. A live test email can be sent (with full SMTP debug log on failure)
 *
 * DELETE THIS FILE once your email feature is confirmed working, or protect
 * it behind admin login (it already requires login below).
 */
$PAGE_TITLE = 'Email Diagnostic';
$PAGE_KEY   = 'email-test';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/mailer.php';
$db = db();
$cur = cur();

/* Force-refresh settings so we read the latest saved values */
$settings = get_settings(true);

/* ── Run the checks ── */
$checks = [];

// 1. PHPMailer installed?
$pmInstalled = phpMailerInstalled();
$pmPath = __DIR__ . '/includes/PHPMailer/src/PHPMailer.php';
$checks[] = [
    'label' => 'PHPMailer library installed',
    'ok' => $pmInstalled,
    'detail' => $pmInstalled
        ? 'PHPMailer ' . \PHPMailer\PHPMailer\PHPMailer::VERSION . ' found at includes/PHPMailer/src/'
        : 'MISSING. The folder includes/PHPMailer/ was not uploaded. Download the latest project zip (which now bundles PHPMailer) and re-upload the includes/PHPMailer folder.',
];

// 2. SMTP columns present?
$colCheck = @$db->query("SHOW COLUMNS FROM settings LIKE 'smtp_host'");
$colsExist = ($colCheck && $colCheck->num_rows > 0);
$checks[] = [
    'label' => 'SMTP columns in database',
    'ok' => $colsExist,
    'detail' => $colsExist
        ? 'The settings table has the smtp_* columns (smtp_host, smtp_username, smtp_password, smtp_enabled, …).'
        : 'The settings table is MISSING the smtp_* columns. Run this SQL in phpMyAdmin → SQL tab:<br><code>ALTER TABLE settings ADD COLUMN smtp_enabled TINYINT(1) DEFAULT 0, ADD COLUMN smtp_host VARCHAR(120) DEFAULT \'smtp.gmail.com\', ADD COLUMN smtp_port INT DEFAULT 587, ADD COLUMN smtp_secure VARCHAR(10) DEFAULT \'tls\', ADD COLUMN smtp_username VARCHAR(120) DEFAULT NULL, ADD COLUMN smtp_password VARCHAR(120) DEFAULT NULL, ADD COLUMN smtp_from_name VARCHAR(120) DEFAULT \'New Life Fitness Club\';</code>',
];

// 3. Settings enabled + credentials present?
$cfg = getSmtpSettings($db);
$enabled = (bool)$cfg['enabled'];
$hasUser = !empty($cfg['username']);
$hasPass = !empty($cfg['password']);
$credOk = $enabled && $hasUser && $hasPass;
$checks[] = [
    'label' => 'Email enabled & credentials saved',
    'ok' => $credOk,
    'detail' => 'Enabled: <b>' . ($enabled ? 'Yes' : 'NO — tick "Enable Email Sending" in Settings') . '</b><br>'
              . 'Gmail address: <b>' . e($cfg['username'] ?: '(empty — enter it in Settings)') . '</b><br>'
              . 'App password: <b>' . ($hasPass ? 'saved (' . strlen($cfg['password']) . ' chars)' : 'NOT saved — paste your 16-char App Password in Settings') . '</b><br>'
              . 'Host / Port / Encryption: ' . e($cfg['host']) . ':' . (int)$cfg['port'] . ' / ' . e($cfg['secure'] ?: 'none'),
];

// 4. Recipient lookup — members with email
$membersWithEmail = [];
$res = @$db->query("SELECT id, name, email FROM members WHERE email IS NOT NULL AND email <> '' ORDER BY name LIMIT 20");
if ($res) { while ($r = $res->fetch_assoc()) $membersWithEmail[] = $r; }

/* ── Handle a live test send ── */
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sendtest') {
    $to = trim($_POST['test_email'] ?? '');
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $testResult = ['ok' => false, 'error' => 'Please enter a valid recipient email address.'];
    } else {
        $html = "<div style='font-family:Segoe UI,Arial,sans-serif;padding:20px;'>
                   <h2 style='color:#2e9e5b;'>✅ Test Email Successful!</h2>
                   <p>This confirms that your SMTP email settings are working correctly.</p>
                   <p><strong>" . e($settings['gym_name'] ?? 'New Life Fitness Club') . "</strong> can now send
                      payment receipts and membership renewal emails to members.</p>
                   <hr><p style='color:#999;font-size:12px;'>Sent: " . date('Y-m-d H:i:s') . "</p>
                 </div>";
        $testResult = sendSmtpEmail($db, $to, 'Test Recipient', 'Test Email — SMTP Settings OK', $html, '', true);
    }
}

/* Pre-fill from query string (passed by settings/index.php on failure) */
$prefillErr = $_GET['err'] ?? '';
$testResult = $testResult ?: ($prefillErr !== '' ? ['ok' => false, 'error' => $prefillErr] : null);
$prefillEmail = $_GET['email'] ?? ($cfg['username'] ?: '');
?>

<?= flash() ?>

<div class="page-head">
  <div>
    <h2>📧 Email Diagnostic &amp; Test</h2>
    <p>Step-by-step check of why emails aren't being sent, plus a live send test.</p>
  </div>
  <a href="<?= $base ?>/admin/settings/index.php" class="btn btn-ghost">&larr; Back to Email Settings</a>
</div>

<!-- Checklist -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body">
    <h3 style="margin:0 0 16px;color:var(--navy-700);font-size:18px;border-bottom:1px solid var(--border);padding-bottom:10px;">Pre-flight Checklist</h3>    <?php foreach ($checks as $c): ?>
      <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #f0f0f0;align-items:flex-start;">
        <div style="font-size:24px;line-height:1;flex-shrink:0;">
          <?= $c['ok'] ? '<span style="color:#2e9e5b;">✅</span>' : '<span style="color:#e53935;">❌</span>' ?>
        </div>
        <div style="flex:1;">
          <div style="font-weight:600;color:var(--navy-800);font-size:15px;"><?= e($c['label']) ?></div>
          <div style="color:var(--muted);font-size:13px;line-height:1.6;margin-top:4px;"><?= $c['detail'] ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!$pmInstalled || !$colsExist || !$credOk): ?>
<div class="card" style="margin-bottom:20px;border-left:4px solid #e53935;">
  <div class="card-body">
    <h3 style="margin:0 0 10px;color:#e53935;">⚠️ Fix the issues above first</h3>
    <p style="color:var(--muted);font-size:14px;line-height:1.7;margin:0;">
      The test send below will not work until every checklist item shows ✅.
      The most common cause is the <strong>PHPMailer library missing</strong> — re-upload the
      <code>includes/PHPMailer</code> folder from the latest project zip. The second most common is
      <strong>Gmail credentials not saved</strong> — go to <a href="<?= $base ?>/admin/settings/index.php">Email Settings</a>,
      enter your Gmail address + 16-character App Password, tick "Enable Email Sending", and Save.
    </p>
  </div>
</div>
<?php endif; ?>

<!-- Live Test -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body">
    <h3 style="margin:0 0 16px;color:var(--navy-700);font-size:18px;">🧪 Send a Live Test Email</h3>
    <p style="color:var(--muted);font-size:14px;margin:0 0 16px;">
      Enter any email address you can check (your own Gmail is fine). This performs a real SMTP
      connection and shows the full Gmail SMTP conversation if it fails.
    </p>
    <form method="post" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="action" value="sendtest">
      <div class="form-field" style="flex:1;min-width:280px;margin:0;">
        <label>Send test email to</label>
        <input type="email" name="test_email" placeholder="your-personal-email@gmail.com" required
               value="<?= e($prefillEmail) ?>">
      </div>
      <button class="btn btn-primary">📨 Send Test Email</button>
    </form>
  </div>
</div>

<?php if ($testResult !== null): ?>
<div class="card" style="margin-bottom:20px;border-left:4px solid <?= $testResult['ok'] ? '#2e9e5b' : '#e53935' ?>;">
  <div class="card-body">
    <h3 style="margin:0 0 10px;color:<?= $testResult['ok'] ? '#2e9e5b' : '#e53935' ?>;">
      <?= $testResult['ok'] ? '✅ Test email sent successfully!' : '❌ Test email failed' ?>
    </h3>
    <?php if ($testResult['ok']): ?>
      <p style="color:#333;font-size:14px;line-height:1.7;">
        Your SMTP settings are working. Check the recipient's inbox (and spam folder).
        Now go to <strong>Fee Management → Record Payment</strong>, pick a member with an email,
        choose a plan, mark as Paid, and Save — the member will get the receipt automatically.
      </p>
    <?php else: ?>
      <p style="color:#333;font-size:14px;line-height:1.7;margin:0 0 12px;">
        <strong>Error:</strong> <?= nl2br(e($testResult['error'])) ?>
      </p>
      <?php if (!empty($testResult['smtp_log'])): ?>
        <details style="margin-top:12px;">
          <summary style="cursor:pointer;color:var(--navy-500);font-size:13px;font-weight:600;">
            Show full SMTP conversation log
          </summary>
          <pre style="background:#1a1a2e;color:#a8dadc;padding:14px;border-radius:6px;font-size:12px;overflow-x:auto;margin-top:8px;white-space:pre-wrap;"><?= $testResult['smtp_log'] ?></pre>
        </details>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Members with email -->
<div class="card">
  <div class="card-body">
    <h3 style="margin:0 0 12px;color:var(--navy-700);font-size:18px;">👥 Members with an email address (<?= count($membersWithEmail) ?>)</h3>
    <?php if (empty($membersWithEmail)): ?>
      <p style="color:var(--muted);font-size:14px;">No members have an email address yet. Add emails in Member Management to send them receipts.</p>
    <?php else: ?>
      <p style="color:var(--muted);font-size:13px;margin:0 0 10px;">Use one of these to test the full payment-receipt flow:</p>
      <table class="data-table" style="width:100%;">
        <thead><tr><th>Name</th><th>Email</th></tr></thead>
        <tbody>
          <?php foreach ($membersWithEmail as $m): ?>
            <tr><td><?= e($m['name']) ?></td><td><?= e($m['email']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- Quick fix guide -->
<div class="card" style="margin-top:20px;border-left:4px solid var(--navy-500);">
  <div class="card-body">
    <h3 style="margin:0 0 12px;color:var(--navy-700);font-size:16px;">🔧 If the test still fails — quick fixes</h3>
    <div style="font-size:13px;color:var(--muted);line-height:1.8;">
      <p style="margin:0 0 6px;"><strong>SMTP 535 / "Username and password not accepted":</strong> You used your normal Gmail password instead of an App Password. Generate one at <a href="https://myaccount.google.com/apppasswords" target="_blank">myaccount.google.com/apppasswords</a> (2-Step Verification must be ON). Paste it WITHOUT spaces.</p>
      <p style="margin:0 0 6px;"><strong>"Could not connect" / "Connection refused":</strong> Your host (InfinityFree) is blocking outbound SMTP. Try port <b>465 + SSL</b> in Settings. If that also fails, InfinityFree's free tier may block SMTP entirely — you'd need a host that allows outbound SMTP.</p>
      <p style="margin:0 0 6px;"><strong>"STARTTLS failed":</strong> Switch Encryption to <b>SSL</b> and Port to <b>465</b>.</p>
      <p style="margin:0;"><strong>Settings saved but nothing happens:</strong> Make sure you ticked <b>"Enable Email Sending"</b> AND entered the password AND clicked Save. Re-open Settings — if the password field shows "saved", it's stored.</p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
