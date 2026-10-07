<?php
/**
 * mailer.php — Email sending helper using PHPMailer + Gmail SMTP.
 *
 * InfinityFree (and most free hosts) block the built-in PHP mail() function,
 * so we use an external SMTP server (Gmail by default) via PHPMailer.
 *
 * SMTP credentials are stored in the `settings` table columns:
 *   smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password,
 *   smtp_from_name, smtp_from_email, smtp_enabled
 *
 * NOTE: smtp_from_email lets you decouple the "From" address from the SMTP
 * login. This is essential for providers like Brevo/Sendinblue where the
 * SMTP username is a generic login (e.g. xxx@smtp-brevo.com) but you want
 * the email to appear from your own address (e.g. you@gmail.com).
 * The sender address MUST be verified in your provider's dashboard first.
 *
 * IMPORTANT: The PHPMailer library MUST be uploaded to includes/PHPMailer/src/
 * Without it, every send() returns false with "PHPMailer library not found."
 *
 * Usage:
 *   require_once __DIR__ . '/mailer.php';
 *   $result = sendPaymentReceiptEmail($db, $feeId);
 *   // $result = ['ok'=>true] or ['ok'=>false,'error'=>'message']
 */

/* ── Load PHPMailer (only if the files actually exist) ── */
$pmDir   = __DIR__ . '/PHPMailer/src';
$pmFound = file_exists($pmDir . '/PHPMailer.php')
        && file_exists($pmDir . '/SMTP.php')
        && file_exists($pmDir . '/Exception.php');

if ($pmFound) {
    require_once $pmDir . '/PHPMailer.php';
    require_once $pmDir . '/SMTP.php';
    require_once $pmDir . '/Exception.php';
} else {
    // PHPMailer not uploaded yet — define a dummy so the page doesn't crash,
    // but EVERY send() will fail with a clear, actionable error message.
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        eval('namespace PHPMailer\\PHPMailer; class PHPMailer { public $ErrorInfo = "PHPMailer library not found. Upload the PHPMailer folder to includes/ (it is part of the project zip)."; const ENCRYPTION_STARTTLS="tls"; const ENCRYPTION_SMTPS="ssl"; function __construct($ex=true){} function isSMTP(){} function setFrom($a,$b){} function addReplyTo($a,$b){} function addAddress($a,$b){} function isHTML($v){} function send(){ return false; } }');
        eval('namespace PHPMailer\\PHPMailer; class Exception extends \\Exception {}');
        eval('namespace PHPMailer\\PHPMailer; class SMTP {}');
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Returns true if the real PHPMailer library is installed (not the dummy).
 */
function phpMailerInstalled() {
    return class_exists('PHPMailer\\PHPMailer\\PHPMailer')
        && method_exists('PHPMailer\\PHPMailer\\PHPMailer', 'send')
        && defined('PHPMailer\\PHPMailer\\PHPMailer::VERSION');
}

/**
 * Get SMTP settings from the database settings table.
 * Returns an array with keys: host, port, secure, username, password,
 * from_name, from_email, enabled
 */
function getSmtpSettings($db) {
    $defaults = [
        'enabled'       => 0,
        'provider'      => 'smtp',        // 'smtp' or 'brevo_api'
        'brevo_api_key' => '',
        'host'          => 'smtp.gmail.com',
        'port'          => 587,
        'secure'        => 'tls',
        'username'      => '',
        'password'      => '',
        'from_name'     => 'New Life Fitness Club',
        'from_email'    => ''
    ];

    // Check if SMTP columns exist
    $colCheck = @$db->query("SHOW COLUMNS FROM settings LIKE 'smtp_host'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        return $defaults; // Columns don't exist yet — return defaults
    }

    // Auto-migrate: add email_provider + brevo_api_key columns if missing.
    // This lets the admin switch to Brevo REST API (HTTPS port 443) which
    // bypasses InfinityFree's SMTP port blocking entirely.
    $epCheck = @$db->query("SHOW COLUMNS FROM settings LIKE 'email_provider'");
    if ($epCheck && $epCheck->num_rows === 0) {
        @$db->query("ALTER TABLE settings ADD COLUMN email_provider VARCHAR(20) DEFAULT 'smtp'");
        @$db->query("ALTER TABLE settings ADD COLUMN brevo_api_key VARCHAR(200) DEFAULT NULL");
    }

    // Build the SELECT — smtp_from_email may not exist on older installs,
    // so we detect it and fall back to the SMTP username (Gmail behaviour).
    $hasFromEmailCol = false;
    $fc = @$db->query("SHOW COLUMNS FROM settings LIKE 'smtp_from_email'");
    if ($fc && $fc->num_rows > 0) {
        $hasFromEmailCol = true;
    }

    $sql = "SELECT smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password, smtp_from_name, smtp_enabled, email_provider, brevo_api_key";
    if ($hasFromEmailCol) {
        $sql .= ", smtp_from_email";
    }
    $sql .= " FROM settings LIMIT 1";

    $res = @$db->query($sql);
    if (!$res) {
        // The SELECT may fail if email_provider column wasn't added yet;
        // retry without those columns.
        $sql2 = "SELECT smtp_host, smtp_port, smtp_secure, smtp_username, smtp_password, smtp_from_name, smtp_enabled";
        if ($hasFromEmailCol) $sql2 .= ", smtp_from_email";
        $sql2 .= " FROM settings LIMIT 1";
        $res = @$db->query($sql2);
        if (!$res) return $defaults;
    }

    $row = $res->fetch_assoc();
    if (!$row) return $defaults;

    // From email: use the dedicated column if present & non-empty, otherwise
    // fall back to the SMTP username (preserves Gmail behaviour where the
    // login IS the from address).
    $fromEmail = '';
    if ($hasFromEmailCol && !empty($row['smtp_from_email'])) {
        $fromEmail = trim($row['smtp_from_email']);
    }
    if ($fromEmail === '' && !empty($row['smtp_username'])) {
        $fromEmail = $row['smtp_username'];
    }

    return [
        'enabled'       => (int)($row['smtp_enabled'] ?? 0),
        'provider'      => $row['email_provider'] ?? 'smtp',
        'brevo_api_key' => $row['brevo_api_key'] ?? '',
        'host'          => $row['smtp_host'] ?? 'smtp.gmail.com',
        'port'          => (int)($row['smtp_port'] ?? 587),
        'secure'        => $row['smtp_secure'] ?? 'tls',
        'username'      => $row['smtp_username'] ?? '',
        'password'      => $row['smtp_password'] ?? '',
        'from_name'     => $row['smtp_from_name'] ?? 'New Life Fitness Club',
        'from_email'    => $fromEmail,
    ];
}

/**
 * Send an email via the Brevo REST API (HTTPS port 443).
 *
 * This is the RECOMMENDED method on InfinityFree because the free tier
 * blocks outbound SMTP (ports 25/465/587).  The Brevo API uses plain
 * HTTPS (port 443) which is NEVER blocked.
 *
 * Free tier: 300 emails/day.  Sign up at https://www.brevo.com
 * API key: Brevo dashboard → Settings → API Keys → Generate.
 *
 * @param array  $cfg      Settings from getSmtpSettings()
 * @param string $toEmail  Recipient email
 * @param string $toName   Recipient name
 * @param string $subject  Email subject
 * @param string $htmlBody HTML body
 * @param string $altBody  Plain-text alternative (optional)
 * @param string $posterPath  Optional path to inline poster image
 * @return array ['ok'=>true] or ['ok'=>false,'error'=>'...']
 */
function sendBrevoApiEmail($cfg, $toEmail, $toName, $subject, $htmlBody, $altBody = '', $posterPath = '') {
    $apiKey = trim($cfg['brevo_api_key'] ?? '');
    if ($apiKey === '') {
        return ['ok' => false, 'error' => 'Brevo API key is empty. Go to Settings → Email Settings, select "Brevo API" as the provider, and paste your Brevo API key (Brevo → Settings → API Keys → Generate).'];
    }
    $fromEmail = !empty($cfg['from_email']) ? $cfg['from_email'] : $cfg['username'];
    if (!$fromEmail) {
        return ['ok' => false, 'error' => 'From Email is not set. Go to Settings → Email Settings and enter your verified sender email address.'];
    }

    // Build the Brevo API payload
    $payload = [
        'sender'     => ['name' => $cfg['from_name'], 'email' => $fromEmail],
        'to'         => [['name' => $toName, 'email' => $toEmail]],
        'replyTo'    => ['name' => $cfg['from_name'], 'email' => $fromEmail],
        'subject'    => $subject,
        'htmlContent'=> $htmlBody,
    ];
    if ($altBody) {
        $payload['textContent'] = $altBody;
    }

    // Inline image attachment (for offer posters) — Brevo accepts base64
    if ($posterPath && file_exists($posterPath)) {
        $imgData = file_get_contents($posterPath);
        if ($imgData !== false) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $posterPath);
            finfo_close($finfo);
            $payload['attachment'] = [[
                'name'       => basename($posterPath),
                'content'    => base64_encode($imgData),
                'contentType'=> $mime ?: 'image/jpeg',
            ]];
        }
    }

    $jsonPayload = json_encode($payload);

    // Send via cURL over HTTPS (port 443 — never blocked by InfinityFree)
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $apiKey,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'Could not connect to Brevo API (HTTPS). cURL error: ' . $curlErr];
    }

    // 201 = Created (success).  400/401/403 = error.
    if ($httpCode === 201 || $httpCode === 200) {
        return ['ok' => true];
    }

    // Parse error response
    $errBody = json_decode($response, true);
    $errMsg = $errBody['message'] ?? $errBody['error'] ?? $response;

    // Brevo API key invalid
    if ($httpCode === 401 || $httpCode === 403) {
        return ['ok' => false, 'error' => 'Brevo API rejected the API key (HTTP ' . $httpCode . '). ' .
            'Go to Brevo → Settings → API Keys → Generate a new key, then paste it in Settings → Email Settings. ' .
            'Detail: ' . $errMsg];
    }
    // Sender not verified
    if (strpos(strtolower($errMsg), 'sender') !== false) {
        return ['ok' => false, 'error' => 'The From email (' . $fromEmail . ') is not verified in Brevo. ' .
            'Go to Brevo → Settings → Senders & IP → Add a Sender, enter ' . $fromEmail . ', click the verification code in that inbox. ' .
            'Detail: ' . $errMsg];
    }

    return ['ok' => false, 'error' => 'Brevo API error (HTTP ' . $httpCode . '): ' . $errMsg];
}

/**
 * Universal email dispatcher.
 *
 * Routes to sendBrevoApiEmail() (HTTPS, recommended on InfinityFree) or
 * sendSmtpEmail() (PHPMailer SMTP) based on the email_provider setting.
 * This is the single entry point all email functions should use.
 *
 * @param mysqli  $db
 * @param string  $toEmail
 * @param string  $toName
 * @param string  $subject
 * @param string  $htmlBody
 * @param string  $altBody
 * @param bool    $debug
 * @return array ['ok'=>true] or ['ok'=>false,'error'=>'...']
 */
function sendEmail($db, $toEmail, $toName, $subject, $htmlBody, $altBody = '', $debug = false) {
    $cfg = getSmtpSettings($db);

    // Safety net: if email is "disabled" but the selected provider has valid
    // credentials, treat it as enabled.  This prevents the confusing
    // "Email sending is disabled" error after saving credentials.
    $providerReady = false;
    if ($cfg['provider'] === 'brevo_api') {
        $providerReady = (!empty($cfg['brevo_api_key']) && !empty($cfg['from_email']));
    } else {
        $providerReady = (!empty($cfg['host']) && !empty($cfg['username']));
    }
    if (!$cfg['enabled'] && !$providerReady) {
        return ['ok' => false, 'error' => 'Email sending is disabled. Go to Settings → Email Settings, fill in your credentials, and click Save Settings.'];
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Recipient email address is missing or invalid: ' . $toEmail];
    }

    // Route to the selected provider
    if ($cfg['provider'] === 'brevo_api') {
        return sendBrevoApiEmail($cfg, $toEmail, $toName, $subject, $htmlBody, $altBody);
    }

    // Default: SMTP via PHPMailer
    return sendSmtpEmail($db, $toEmail, $toName, $subject, $htmlBody, $altBody, $debug);
}

/**
 * Low-level: send an HTML email via SMTP using stored settings.
 *
 * @param mysqli  $db
 * @param string  $toEmail   Recipient email address
 * @param string  $toName    Recipient name
 * @param string  $subject   Email subject
 * @param string  $htmlBody  HTML body
 * @param string  $altBody   Plain-text alternative (optional)
 * @param bool    $debug     When true, captures SMTP conversation for diagnostics
 * @return array ['ok'=>true] or ['ok'=>false,'error'=>'...', 'smtp_log'=>'...']
 */
function sendSmtpEmail($db, $toEmail, $toName, $subject, $htmlBody, $altBody = '', $debug = false) {
    // Force-refresh the cached settings so we read the LATEST saved values.
    $cfg = getSmtpSettings($db);

    /* ── Pre-flight validation with clear messages ── */
    if (!phpMailerInstalled()) {
        return ['ok' => false, 'error' => 'PHPMailer library is missing. Upload the includes/PHPMailer folder to your server (it is included in the project zip).'];
    }
    if (!$cfg['enabled']) {
        // If the Brevo API provider is configured, don't block — the
        // sendEmail() dispatcher handles that provider separately.
        if ($cfg['provider'] === 'brevo_api' && !empty($cfg['brevo_api_key']) && !empty($cfg['from_email'])) {
            // fall through — Brevo API will be used by the dispatcher
        } else {
            return ['ok' => false, 'error' => 'Email sending is disabled. Tick "Enable Email Sending" in Settings and Save.'];
        }
    }
    if (!$cfg['host'] || !$cfg['username'] || !$cfg['password']) {
        $missing = [];
        if (!$cfg['host'])     $missing[] = 'SMTP Host';
        if (!$cfg['username']) $missing[] = 'SMTP Username (login)';
        if (!$cfg['password']) $missing[] = 'SMTP Password (App Password / SMTP key)';
        return ['ok' => false, 'error' => 'SMTP not fully configured. Missing: ' . implode(', ', $missing) . '. Go to Settings → Email Settings, fill them in, and click Save Settings.'];
    }
    if (!$toEmail || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Recipient email address is missing or invalid: ' . $toEmail];
    }

    /* ── Capture SMTP debug output if requested ── */
    $smtpLog = '';
    if ($debug) {
        ob_start();
    }

    try {
        $mail = new PHPMailer(true);

        // UTF-8 so the ₹ symbol and em-dashes render correctly in the email
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'quoted-printable'; // safer for spam filters than 8bit

        /* ── Determine the From address ──
         * With Gmail, the SMTP login IS the from address (both are the
         * same Gmail address). With Brevo / SMTP2GO / SendGrid, the SMTP
         * login is a generic service address (e.g. xxx@smtp-brevo.com)
         * while the From address is your own verified email. We use the
         * dedicated smtp_from_email setting, falling back to the SMTP
         * username for backwards compatibility with Gmail setups.
         */
        $fromEmail = !empty($cfg['from_email']) ? $cfg['from_email'] : $cfg['username'];

        /* ── Deliverability headers (prevent emails landing in spam) ──
         * The #1 cause of automated emails going to spam is a MISMATCH
         * between the From address domain (e.g. gmail.com) and the
         * Message-ID domain.  By default PHPMailer generates the Message-ID
         * using $_SERVER['SERVER_NAME'] (e.g. new-life-fitness.kesug.com or
         * even localhost.localdomain), so the recipient sees:
         *   From:       karandevalla38@gmail.com
         *   Message-ID: <xxx@new-life-fitness.kesug.com>
         * That mismatch trips spam filters.  Fix: set Hostname to the
         * domain part of the FROM address so the Message-ID matches.
         */
        $fromDomain = 'localhost.localdomain';
        if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $fromEmail);
            $fromDomain = array_pop($parts);
        }
        $mail->Hostname = $fromDomain;           // makes Message-ID = <id@gmail.com>

        // Envelope sender (Return-Path) must match From for best reputation
        $mail->Sender = $fromEmail;

        // A clean X-Mailer instead of advertising the PHPMailer version
        $mail->XMailer = $cfg['from_name'];

        // Server settings
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->Port       = $cfg['port'];
        $mail->Timeout    = 30;
        $mail->SMTPKeepAlive = false;

        if ($debug) {
            $mail->SMTPDebug = SMTP::DEBUG_CONNECTION; // 3 = connection-level
            $mail->Debugoutput = function($str, $level) use (&$smtpLog) {
                $smtpLog .= htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
            };
        }

        if ($cfg['secure'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($cfg['secure'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        // From — use the dedicated from_email (Brevo) or fall back to SMTP
        // username (Gmail). The from address MUST be verified by the provider.
        $mail->setFrom($fromEmail, $cfg['from_name']);
        $mail->addReplyTo($fromEmail, $cfg['from_name']);

        // Recipient
        $mail->addAddress($toEmail, $toName);

        /* ── Anti-spam custom headers ──
         * Auto-Submitted tells filters this is an automated (transactional)
         * message, not unsolicited bulk mail — legitimate for receipts/reminders.
         * Precedence: bulk is the RFC standard for automated mail.
         */
        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
        $mail->addCustomHeader('Precedence', 'bulk');
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, OOFN');

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));

        $mail->send();

        if ($debug) { ob_end_clean(); }
        return ['ok' => true];
    } catch (Exception $e) {
        if ($debug) { ob_end_clean(); }
        $err = $mail->ErrorInfo ?: $e->getMessage();
        return ['ok' => false, 'error' => friendlySmtpError($err, $cfg), 'smtp_log' => $smtpLog];
    } catch (\Throwable $e) {
        if ($debug) { ob_end_clean(); }
        return ['ok' => false, 'error' => 'Error: ' . $e->getMessage(), 'smtp_log' => $smtpLog];
    }
}

/**
 * Translate raw SMTP/PHPMailer errors into plain-English, actionable advice.
 */
function friendlySmtpError($err, $cfg) {
    $err = (string)$err;
    $low = strtolower($err);

    // Brevo / Sendinblue sender not verified
    if (strpos($low, 'sender') !== false && strpos($low, 'not allowed') !== false
        || strpos($low, 'invalid sender') !== false
        || (strpos($low, 'brevo') !== false && strpos($low, 'sender') !== false)) {
        return "The sender (From) email address is not verified in your Brevo account. "
             . "Go to Brevo → Settings → Senders & IP → Add a Sender, enter the From Email "
             . "address from your Settings page, click the 6-digit verification code in the "
             . "inbox of that email, then try again. "
             . "Raw error: " . $err;
    }
    // Gmail App Password problems / SMTP auth failures
    if (strpos($low, '535') !== false
        || strpos($low, 'authentication') !== false
        || strpos($low, 'authenticate') !== false
        || strpos($low, 'username and password not accepted') !== false
        || strpos($low, 'auth required') !== false) {
        return "SMTP login failed — the SMTP server rejected the username/password. "
             . "Common causes: "
             . "(1) For Gmail: you used your normal password instead of a 16-character App Password "
             . "— generate one at https://myaccount.google.com/apppasswords (requires 2-Step Verification ON). "
             . "(2) For Brevo: you used your account password instead of the SMTP key "
             . "— generate one in Brevo → Settings → SMTP & API → Generate a new SMTP key. "
             . "(3) The password has stray spaces — paste it exactly as provided. "
             . "(4) InfinityFree's free tier BLOCKS outbound SMTP (ports 25/465/587) — this is the most "
             . "common cause on InfinityFree. The connection may partially work but auth fails. "
             . "\n\n>>> EASIEST FIX: Switch to Brevo API in Settings → Email Settings. "
             . "The Brevo REST API uses HTTPS (port 443) which is NEVER blocked by InfinityFree. "
             . "Just select 'Brevo API' as the provider and paste your Brevo API key. "
             . "Raw error: " . $err;
    }
    // Network / connection refused (InfinityFree blocks outbound SMTP on some plans)
    if (strpos($low, 'connection refused') !== false || strpos($low, 'connection failed') !== false
        || strpos($low, 'network is unreachable') !== false || strpos($low, 'could not connect') !== false
        || strpos($low, 'connection timed out') !== false) {
        return "Could not connect to {$cfg['host']}:{$cfg['port']}. Your hosting provider (InfinityFree) may block outbound SMTP connections, OR port {$cfg['port']} is blocked by a firewall. "
             . "Try: (a) use port 465 with SSL encryption in Settings, or (b) port 587 with TLS. "
             . "If neither works, the host is blocking SMTP — InfinityFree's free tier often blocks outbound SMTP; you may need a host that allows it. "
             . "Raw error: " . $err;
    }
    // TLS / STARTTLS issues
    if (strpos($low, 'starttls') !== false || strpos($low, 'tls') !== false) {
        return "TLS/STARTTLS negotiation failed with {$cfg['host']}. Try switching Encryption to 'SSL' and Port to '465' in Settings. "
             . "Raw error: " . $err;
    }
    // Empty error (some hosts swallow it)
    if (trim($err) === '') {
        return "Email send failed with no error message. This usually means the SMTP connection was blocked by your host (InfinityFree). "
             . "Run the Email Diagnostic (email-test.php) for full details. Host: {$cfg['host']}:{$cfg['port']}";
    }
    return $err;
}

/**
 * Send a payment receipt + membership renewal email to a member.
 *
 * Fetches the fee record, member details, plan details, and settings,
 * builds a branded HTML email, and sends it via SMTP.
 *
 * @param mysqli $db
 * @param int    $feeId    The fees.id of the payment
 * @param bool   $renewed  Whether the membership was renewed (affects email content)
 * @return array ['ok'=>true] or ['ok'=>false,'error'=>'...']
 */
function sendPaymentReceiptEmail($db, $feeId, $renewed = false) {
    $feeId = (int)$feeId;
    if (!$feeId) return ['ok' => false, 'error' => 'Invalid fee ID.'];

    // Fetch fee + member + plan
    $f = $db->query(
      "SELECT f.*, m.name AS member_name, m.email, m.contact,
              p.plan_name, p.duration_months,
              DATE_ADD(m.join_date, INTERVAL p.duration_months MONTH) AS new_expiry
       FROM fees f
       JOIN members m ON f.member_id = m.id
       LEFT JOIN membership_plans p ON f.plan_id = p.id
       WHERE f.id = $feeId"
    );
    if (!$f) return ['ok' => false, 'error' => 'Database query failed.'];
    $row = $f->fetch_assoc();
    if (!$row) return ['ok' => false, 'error' => 'Fee record not found.'];

    $memberEmail = trim($row['email'] ?? '');
    if (!$memberEmail) {
        return ['ok' => false, 'error' => 'Member has no email address on file. Add an email in Member Management to send receipts.'];
    }

    // Force-refresh settings so gym branding is current
    $settings = get_settings(true);
    $gymName  = $settings['gym_name'] ?? 'New Life Fitness Club';
    $gymAddr  = $settings['address'] ?? '';
    $gymTel   = $settings['contact'] ?? '';
    $gymEmail = $settings['email'] ?? '';
    $curSym   = cur();

    $memberName  = e($row['member_name']);
    $receiptNo   = e($row['receipt_no'] ?? ('#' . $feeId));
    $payDate     = fmtDate($row['payment_date']);
    $planName    = e($row['plan_name'] ?? '—');
    $amount      = fmtMoney($row['amount'], $curSym);
    $payMode     = e($row['payment_mode'] ?? '—');
    $status      = e($row['status'] ?? '—');
    $newExpiry   = $row['new_expiry'] ? fmtDate($row['new_expiry']) : '—';

    // Build HTML email
    $renewalSection = '';
    if ($renewed && $row['plan_name']) {
        $renewalSection = "
        <div style='background:#e8f5e9;border-left:4px solid #2e9e5b;padding:16px 20px;border-radius:8px;margin:20px 0;'>
          <h3 style='margin:0 0 8px;color:#2e9e5b;font-size:18px;'>✅ Membership Renewed!</h3>
          <p style='margin:0;color:#333;font-size:15px;line-height:1.6;'>
            Your <strong>{$planName}</strong> membership has been renewed for
            <strong>" . (int)$row['duration_months'] . " months</strong>.<br>
            Your new membership is valid until <strong>{$newExpiry}</strong>.
          </p>
        </div>";
    }

    $statusColor = $row['status'] === 'Paid' ? '#2e9e5b' : ($row['status'] === 'Overdue' ? '#e53935' : '#f5a623');

    $html = "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f4f6f9;font-family:Segoe UI,Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f9;padding:20px;'>
    <tr><td align='center'>
      <table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);'>
        <!-- Header -->
        <tr>
          <td style='background:linear-gradient(135deg,#1a3a5c,#2d5a87);padding:24px 30px;'>
            <h1 style='margin:0;color:#fff;font-size:24px;'>{$gymName}</h1>
            <p style='margin:4px 0 0;color:#a8c4e0;font-size:13px;'>{$gymAddr}</p>
            <p style='margin:2px 0 0;color:#a8c4e0;font-size:13px;'>Tel: {$gymTel} &middot; {$gymEmail}</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style='padding:30px;'>
            <h2 style='margin:0 0 6px;color:#1a3a5c;font-size:22px;'>Payment Receipt</h2>
            <p style='margin:0 0 20px;color:#666;font-size:14px;'>Receipt #{$receiptNo} &middot; {$payDate}</p>

            <p style='font-size:16px;color:#333;line-height:1.6;'>Dear <strong>{$memberName}</strong>,</p>
            <p style='font-size:15px;color:#555;line-height:1.6;'>
              Thank you for your payment! Here is your receipt for the membership plan below.
            </p>

            {$renewalSection}

            <!-- Receipt Table -->
            <table width='100%' cellpadding='12' cellspacing='0' style='border-collapse:collapse;margin:20px 0;border:1px solid #e0e0e0;border-radius:8px;'>
              <tr style='background:#f8f9fa;'>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;width:40%;'>Receipt No.</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;font-weight:600;'>{$receiptNo}</td>
              </tr>
              <tr>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;'>Member Name</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;font-weight:600;'>{$memberName}</td>
              </tr>
              <tr>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;'>Membership Plan</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;font-weight:600;'>{$planName}</td>
              </tr>
              <tr>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;'>Payment Date</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;font-weight:600;'>{$payDate}</td>
              </tr>
              <tr>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;'>Payment Mode</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;font-weight:600;'>{$payMode}</td>
              </tr>
              <tr>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;'>Status</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;font-weight:600;color:{$statusColor};'>{$status}</td>
              </tr>";

    if (!empty($row['notes'])) {
        $html .= "
              <tr>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;'>Notes</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;'>" . e($row['notes']) . "</td>
              </tr>";
    }

    $html .= "
              <tr style='background:#1a3a5c;'>
                <td style='color:#a8c4e0;font-size:16px;font-weight:600;'>Amount Paid</td>
                <td style='color:#fff;font-size:22px;font-weight:700;'>{$amount}</td>
              </tr>
            </table>";

    if ($renewed && $row['plan_name']) {
        $html .= "
            <p style='font-size:14px;color:#555;line-height:1.6;'>
              Your membership is now <strong style='color:#2e9e5b;'>Active</strong> and valid until
              <strong>{$newExpiry}</strong>. You can continue enjoying all gym facilities.
            </p>";
    }

    $html .= "
            <p style='font-size:14px;color:#666;line-height:1.6;margin-top:24px;'>
              If you have any questions about this payment, please contact us at
              <a href='mailto:{$gymEmail}' style='color:#2d5a87;'>{$gymEmail}</a> or call
              <strong>{$gymTel}</strong>.
            </p>

            <p style='font-size:14px;color:#333;line-height:1.6;'>
              Best regards,<br>
              <strong>{$gymName}</strong> Team
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style='background:#f8f9fa;padding:16px 30px;border-top:1px solid #e0e0e0;'>
            <p style='margin:0;color:#999;font-size:12px;text-align:center;line-height:1.5;'>
              This is an automated email from {$gymName}.<br>
              Generated on " . date('l, F j, Y \a\t g:i a') . "
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>";

    $altBody = "Payment Receipt - {$gymName}\n\n" .
               "Receipt #: {$receiptNo}\n" .
               "Date: {$payDate}\n" .
               "Member: {$memberName}\n" .
               "Plan: {$planName}\n" .
               "Amount: {$amount}\n" .
               "Mode: {$payMode}\n" .
               "Status: {$status}\n";

    if ($renewed && $row['plan_name']) {
        $altBody .= "\nYour membership has been renewed! Valid until {$newExpiry}.\n";
    }

    $altBody .= "\nThank you!\n{$gymName}";

    $subject = "Payment Receipt #{$receiptNo}" . ($renewed ? " & Membership Renewal" : "") . " — {$gymName}";

    return sendEmail($db, $memberEmail, $row['member_name'], $subject, $html, $altBody);
}

/**
 * Send a payment reminder / due-notice email to a member whose payment
 * is Pending or Overdue.
 *
 * Fetches the fee record, member details, plan details, and settings,
 * builds a branded HTML email asking the member to pay the fee, and
 * sends it via SMTP.
 *
 * @param mysqli $db
 * @param int    $feeId    The fees.id of the payment
 * @return array ['ok'=>true] or ['ok'=>false,'error'=>'...']
 */
function sendPaymentReminderEmail($db, $feeId) {
    $feeId = (int)$feeId;
    if (!$feeId) return ['ok' => false, 'error' => 'Invalid fee ID.'];

    // Fetch fee + member + plan
    $f = $db->query(
      "SELECT f.*, m.name AS member_name, m.email, m.contact
       FROM fees f
       JOIN members m ON f.member_id = m.id
       WHERE f.id = $feeId"
    );
    if (!$f) return ['ok' => false, 'error' => 'Database query failed.'];
    $row = $f->fetch_assoc();
    if (!$row) return ['ok' => false, 'error' => 'Fee record not found.'];

    $memberEmail = trim($row['email'] ?? '');
    if (!$memberEmail) {
        return ['ok' => false, 'error' => 'Member has no email address on file. Add an email in Member Management to send reminders.'];
    }

    // Force-refresh settings so gym branding is current
    $settings = get_settings(true);
    $gymName  = $settings['gym_name'] ?? 'New Life Fitness Club';
    $gymAddr  = $settings['address'] ?? '';
    $gymTel   = $settings['contact'] ?? '';
    $gymEmail = $settings['email'] ?? '';
    $curSym   = cur();

    $memberName = e($row['member_name']);
    $receiptNo  = e($row['receipt_no'] ?? ('#' . $feeId));
    $payDate    = fmtDate($row['payment_date']);
    $amount     = fmtMoney($row['amount'], $curSym);
    $payMode    = e($row['payment_mode'] ?? '—');
    $statusRaw  = $row['status'] ?? 'Pending';
    $status     = e($statusRaw);
    $memberPhone = e($row['contact'] ?? '');

    // Fetch plan name separately (fees table has plan_id; may be null)
    $planName = '—';
    if (!empty($row['plan_id'])) {
        $pq = @$db->query("SELECT plan_name FROM membership_plans WHERE id = " . (int)$row['plan_id']);
        if ($pq) {
            $pr = $pq->fetch_assoc();
            if ($pr) $planName = e($pr['plan_name']);
        }
    }

    // Messaging depends on whether this is Pending or Overdue
    $isOverdue   = (strtolower($statusRaw) === 'overdue');
    $bannerColor = $isOverdue ? '#e53935' : '#f5a623';
    $bannerBg    = $isOverdue ? '#fdecea' : '#fff8e1';
    $bannerTitle = $isOverdue ? '⚠ Payment Overdue' : '⏳ Payment Pending';
    $bannerText  = $isOverdue
        ? "Your membership payment is now <strong>overdue</strong>. Please clear your dues at the earliest to keep your membership active and avoid interruption of gym access."
        : "Your membership payment is currently <strong>pending</strong>. Kindly pay the fee at your earliest convenience to activate or continue your membership.";
    $subjectLine = $isOverdue ? "Payment Overdue Reminder" : "Payment Reminder — Fee Due";

    $html = "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f4f6f9;font-family:Segoe UI,Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f9;padding:20px;'>
    <tr><td align='center'>
      <table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);'>
        <!-- Header -->
        <tr>
          <td style='background:linear-gradient(135deg,#1a3a5c,#2d5a87);padding:24px 30px;'>
            <h1 style='margin:0;color:#fff;font-size:24px;'>{$gymName}</h1>
            <p style='margin:4px 0 0;color:#a8c4e0;font-size:13px;'>{$gymAddr}</p>
            <p style='margin:2px 0 0;color:#a8c4e0;font-size:13px;'>Tel: {$gymTel} &middot; {$gymEmail}</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style='padding:30px;'>
            <h2 style='margin:0 0 6px;color:#1a3a5c;font-size:22px;'>{$subjectLine}</h2>
            <p style='margin:0 0 20px;color:#666;font-size:14px;'>Reference #{$receiptNo} &middot; {$payDate}</p>

            <p style='font-size:16px;color:#333;line-height:1.6;'>Dear <strong>{$memberName}</strong>,</p>

            <!-- Status banner -->
            <div style='background:{$bannerBg};border-left:4px solid {$bannerColor};padding:16px 20px;border-radius:8px;margin:20px 0;'>
              <h3 style='margin:0 0 8px;color:{$bannerColor};font-size:18px;'>{$bannerTitle}</h3>
              <p style='margin:0;color:#333;font-size:15px;line-height:1.6;'>
                {$bannerText}
              </p>
            </div>

            <!-- Amount Due Table -->
            <table width='100%' cellpadding='12' cellspacing='0' style='border-collapse:collapse;margin:20px 0;border:1px solid #e0e0e0;border-radius:8px;'>
              <tr style='background:#f8f9fa;'>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;width:40%;'>Reference No.</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;font-weight:600;'>{$receiptNo}</td>
              </tr>
              <tr>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;'>Member Name</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;font-weight:600;'>{$memberName}</td>
              </tr>
              <tr>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;'>Membership Plan</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;font-weight:600;'>{$planName}</td>
              </tr>
              <tr>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;'>Recorded Date</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;font-weight:600;'>{$payDate}</td>
              </tr>
              <tr>
                <td style='border-bottom:1px solid #e0e0e0;color:#666;font-size:14px;'>Payment Status</td>
                <td style='border-bottom:1px solid #e0e0e0;font-size:14px;font-weight:600;color:{$bannerColor};'>{$status}</td>
              </tr>
              <tr style='background:{$bannerColor};'>
                <td style='color:#fff;font-size:16px;font-weight:600;'>Amount Due</td>
                <td style='color:#fff;font-size:22px;font-weight:700;'>{$amount}</td>
              </tr>
            </table>";

    if (!empty($row['notes'])) {
        $html .= "
            <p style='font-size:14px;color:#555;line-height:1.6;background:#f8f9fa;padding:12px 16px;border-radius:8px;'>
              <strong>Note:</strong> " . e($row['notes']) . "
            </p>";
    }

    $html .= "
            <!-- How to pay -->
            <div style='background:#e3f2fd;border-left:4px solid #2d5a87;padding:16px 20px;border-radius:8px;margin:20px 0;'>
              <h3 style='margin:0 0 8px;color:#1a3a5c;font-size:17px;'>💳 How to Pay</h3>
              <p style='margin:0;color:#333;font-size:14px;line-height:1.7;'>
                Please visit the gym reception to make your payment in person, or contact us using the details below
                to arrange an alternative payment method (Cash, Card, UPI, or Bank Transfer).
                <br><br>
                Once your payment is received, you will automatically receive a <strong>Payment Receipt</strong> by email.
              </p>
            </div>

            <p style='font-size:14px;color:#666;line-height:1.6;margin-top:24px;'>
              If you have already paid, please ignore this reminder. For any questions,
              contact us at <a href='mailto:{$gymEmail}' style='color:#2d5a87;'>{$gymEmail}</a>
              or call <strong>{$gymTel}</strong>.
            </p>

            <p style='font-size:14px;color:#333;line-height:1.6;'>
              Best regards,<br>
              <strong>{$gymName}</strong> Team
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style='background:#f8f9fa;padding:16px 30px;border-top:1px solid #e0e0e0;'>
            <p style='margin:0;color:#999;font-size:12px;text-align:center;line-height:1.5;'>
              This is an automated reminder from {$gymName}.<br>
              Generated on " . date('l, F j, Y \a\t g:i a') . "
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>";

    $altBody = "{$subjectLine} - {$gymName}\n\n" .
               "Dear {$memberName},\n\n" .
               "{$bannerText}\n\n" .
               "Reference #: {$receiptNo}\n" .
               "Member: {$memberName}\n" .
               "Plan: {$planName}\n" .
               "Amount Due: {$amount}\n" .
               "Status: {$status}\n" .
               "Recorded Date: {$payDate}\n\n" .
               "How to pay: Please visit the gym reception or contact us to arrange payment.\n" .
               "Once paid, you will receive a payment receipt by email.\n\n" .
               "If you have already paid, please ignore this reminder.\n\n" .
               "Contact: {$gymEmail} / {$gymTel}\n\n" .
               "Best regards,\n{$gymName} Team";

    $subject = "{$subjectLine} (Ref #{$receiptNo}) — {$gymName}";

    return sendEmail($db, $memberEmail, $row['member_name'], $subject, $html, $altBody);
}

/**
 * Build the responsive HTML email body for an Offer Campaign.
 *
 * Reuses the gym branding from the settings table and embeds the offer
 * poster/banner image inline (PHPMailer CID) so it renders inside the email
 * even when the recipient's client blocks remote images.
 *
 * Template variables (per the project spec):
 *   {{MemberName}}  {{OfferPoster}}  {{OfferTitle}}  {{OfferDescription}}
 *   {{OfferDiscount}}  {{StartDate}}  {{EndDate}}
 *
 * @param array  $offer     Offer row (must contain title, description, discount,
 *                          poster_path, start_date, end_date)
 * @param array  $member    Member row (name, email)
 * @param array  $settings  Gym settings row
 * @param int    $logId     Email-log row id (for the open-tracking pixel)
 * @return array ['html'=>string, 'alt'=>string]  (NOT sent — caller sends it)
 */
function buildOfferEmailHtml($offer, $member, $settings, $logId = 0) {
    $gymName  = e($settings['gym_name'] ?? 'New Life Fitness Club');
    $gymAddr  = e($settings['address'] ?? '');
    $gymTel   = e($settings['contact'] ?? '');
    $gymEmail = e($settings['email'] ?? '');
    $base     = base_url();

    $memberName = e($member['name'] ?? 'Member');
    $title      = e($offer['title'] ?? '');
    $desc       = nl2br(e($offer['description'] ?? ''));
    $discount   = e($offer['discount'] ?? '');
    $startDt    = fmtDate($offer['start_date'] ?? null);
    $endDt      = fmtDate($offer['end_date'] ?? null);

    /* Poster: embed via CID if a poster file exists, otherwise a branded
       gradient placeholder block. The CID is set by the caller via
       $mail->addEmbeddedImage(... 'offerPoster'). */
    $posterBlock = '';
    if (!empty($offer['poster_path'])) {
        $posterBlock = "<img src=\"cid:offerPoster\" alt=\"Offer banner\" "
                     . "style=\"width:100%;max-width:560px;height:auto;display:block;border-radius:10px;margin:18px 0;\">";
    } else {
        $posterBlock = "<div style=\"background:linear-gradient(135deg,#1a3a5c,#2d5a87);"
                     . "color:#fff;border-radius:10px;padding:40px 20px;text-align:center;margin:18px 0;\">"
                     . "<div style=\"font-size:40px;line-height:1;\">🎉</div>"
                     . "<div style=\"margin-top:10px;font-size:18px;font-weight:700;\">{$gymName}</div>"
                     . "<div style=\"font-size:13px;color:#a8c4e0;\">Exclusive Member Offer</div>"
                     . "</div>";
    }

    /* Open-tracking pixel (1x1 transparent image). Loaded by the recipient's
       mail client, which fires the track-open.php endpoint and flips
       email_logs.opened = 1. Gracefully no-ops if hosting blocks it. */
    $trackPixel = '';
    if ($logId) {
        $trackUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://'
                  . ($_SERVER['HTTP_HOST'] ?? '') . $base
                  . '/admin/offers/track-open.php?log=' . (int)$logId;
        $trackPixel = "<img src=\"{$trackUrl}\" width=\"1\" height=\"1\" alt=\"\" "
                    . "style=\"display:none;border:0;width:1px;height:1px;\">";
    }

    $html = "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'></head>
<body style='margin:0;padding:0;background:#f4f6f9;font-family:Segoe UI,Arial,sans-serif;'>
  <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f6f9;padding:20px;'>
    <tr><td align='center'>
      <table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);max-width:600px;width:100%;'>
        <!-- Header -->
        <tr>
          <td style='background:linear-gradient(135deg,#1a3a5c,#2d5a87);padding:24px 30px;text-align:center;'>
            <h1 style='margin:0;color:#fff;font-size:24px;'>{$gymName}</h1>
            <p style='margin:4px 0 0;color:#a8c4e0;font-size:13px;'>{$gymAddr}</p>
            <p style='margin:2px 0 0;color:#a8c4e0;font-size:13px;'>Tel: {$gymTel} &middot; {$gymEmail}</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style='padding:30px;'>
            <h2 style='margin:0 0 10px;color:#1a3a5c;font-size:22px;text-align:center;'>🎉 Exclusive Gym Offer Just for You!</h2>
            <p style='font-size:16px;color:#333;line-height:1.6;'>Hello <strong>{$memberName}</strong>,</p>
            <p style='font-size:15px;color:#555;line-height:1.6;'>We have an exciting offer for you!</p>

            {$posterBlock}

            <table width='100%' cellpadding='0' cellspacing='0' style='margin:20px 0;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;'>
              <tr style='background:#f8f9fa;'>
                <td style='padding:14px 18px;color:#666;font-size:14px;width:38%;border-bottom:1px solid #e0e0e0;'>Offer</td>
                <td style='padding:14px 18px;font-size:15px;font-weight:700;color:#1a3a5c;border-bottom:1px solid #e0e0e0;'>{$title}</td>
              </tr>
              <tr>
                <td colspan='2' style='padding:14px 18px;font-size:14px;color:#444;line-height:1.6;border-bottom:1px solid #e0e0e0;'>{$desc}</td>
              </tr>
              <tr style='background:#fff8e1;'>
                <td style='padding:14px 18px;color:#666;font-size:14px;border-bottom:1px solid #e0e0e0;'>Discount</td>
                <td style='padding:14px 18px;font-size:16px;font-weight:700;color:#D98C2B;border-bottom:1px solid #e0e0e0;'>{$discount}</td>
              </tr>
              <tr>
                <td style='padding:14px 18px;color:#666;font-size:14px;border-bottom:1px solid #e0e0e0;'>Valid From</td>
                <td style='padding:14px 18px;font-size:14px;font-weight:600;color:#333;border-bottom:1px solid #e0e0e0;'>{$startDt}</td>
              </tr>
              <tr>
                <td style='padding:14px 18px;color:#666;font-size:14px;'>Valid Until</td>
                <td style='padding:14px 18px;font-size:14px;font-weight:600;color:#333;'>{$endDt}</td>
              </tr>
            </table>

            <div style='background:#e3f2fd;border-left:4px solid #2d5a87;padding:14px 18px;border-radius:8px;margin:20px 0;'>
              <p style='margin:0;color:#333;font-size:14px;line-height:1.6;'>Don't miss this limited-time opportunity. Visit the gym or contact us to claim your offer.</p>
            </div>

            <p style='font-size:14px;color:#333;line-height:1.6;margin-top:24px;'>
              Thank you,<br>
              <strong>{$gymName}</strong> Team
            </p>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style='background:#f8f9fa;padding:16px 30px;border-top:1px solid #e0e0e0;'>
            <p style='margin:0;color:#999;font-size:12px;text-align:center;line-height:1.5;'>
              This is an automated email from {$gymName}.<br>
              Generated on " . date('l, F j, Y \a\\t g:i a') . "
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
  {$trackPixel}
</body>
</html>";

    $alt = "Exclusive Gym Offer Just for You!\n\n"
         . "Hello {$memberName},\n\n"
         . "We have an exciting offer for you!\n\n"
         . "Offer: {$title}\n"
         . "{$offer['description']}\n\n"
         . "Discount: {$discount}\n"
         . "Valid From: {$startDt}\n"
         . "Valid Until: {$endDt}\n\n"
         . "Don't miss this limited-time opportunity. Visit the gym or contact us to claim your offer.\n\n"
         . "Thank you,\n{$gymName} Team";

    return ['html' => $html, 'alt' => $alt];
}

/**
 * Send a single Offer Campaign email to one member.
 *
 * Builds the responsive HTML template (with the poster embedded inline via
 * PHPMailer CID) and sends it through the configured SMTP settings. The
 * caller is responsible for creating the email_logs row first and passing
 * its id so the open-tracking pixel can be wired up.
 *
 * @param mysqli $db
 * @param array  $offer     Offer row
 * @param array  $member    Member row (name, email)
 * @param int    $logId     email_logs.id (for open tracking)
 * @return array ['ok'=>true] or ['ok'=>false,'error'=>'...']
 */
function sendOfferCampaignEmail($db, $offer, $member, $logId = 0) {
    require_once __DIR__ . '/mailer.php'; // self-include safe (require_once)

    $cfg = getSmtpSettings($db);

    $memberEmail = trim($member['email'] ?? '');
    $memberName  = trim($member['name'] ?? 'Member');
    if (!$memberEmail || !filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Recipient email missing or invalid: ' . $memberEmail];
    }

    $settings = get_settings(true);
    $built    = buildOfferEmailHtml($offer, $member, $settings, $logId);
    $subject  = '🎉 Exclusive Gym Offer Just for You!';

    // Determine poster file path for inline image
    $posterFile = '';
    if (!empty($offer['poster_path'])) {
        $posterFile = __DIR__ . '/../' . $offer['poster_path'];
        if (!file_exists($posterFile)) $posterFile = '';
    }

    // ── Brevo API path (HTTPS, recommended on InfinityFree) ──
    if ($cfg['provider'] === 'brevo_api' && !empty($cfg['brevo_api_key'])) {
        return sendBrevoApiEmail($cfg, $memberEmail, $memberName, $subject,
                                 $built['html'], $built['alt'], $posterFile);
    }

    /* ── SMTP path (PHPMailer) ── */
    /* Pre-flight validation with clear messages */
    if (!phpMailerInstalled()) {
        return ['ok' => false, 'error' => 'PHPMailer library is missing. Upload the includes/PHPMailer folder.'];
    }
    if (!$cfg['enabled']) {
        return ['ok' => false, 'error' => 'Email sending is disabled. Enable it in Settings → Email Settings.'];
    }
    if (!$cfg['host'] || !$cfg['username'] || !$cfg['password']) {
        return ['ok' => false, 'error' => 'SMTP not fully configured. Complete Settings → Email Settings first.'];
    }

    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'quoted-printable';

        $fromEmail = !empty($cfg['from_email']) ? $cfg['from_email'] : $cfg['username'];
        $fromDomain = 'localhost.localdomain';
        if (filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $fromEmail);
            $fromDomain = array_pop($parts);
        }
        $mail->Hostname = $fromDomain;
        $mail->Sender   = $fromEmail;
        $mail->XMailer  = $cfg['from_name'];

        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->Port       = $cfg['port'];
        $mail->Timeout    = 25;
        $mail->SMTPKeepAlive = false;

        if ($cfg['secure'] === 'tls')      $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        elseif ($cfg['secure'] === 'ssl')  $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

        $mail->setFrom($fromEmail, $cfg['from_name']);
        $mail->addReplyTo($fromEmail, $cfg['from_name']);
        $mail->addAddress($memberEmail, $memberName);

        $mail->addCustomHeader('Auto-Submitted', 'auto-generated');
        $mail->addCustomHeader('Precedence', 'bulk');
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, OOFN');

        /* Embed the poster inline so it shows inside the email body */
        if ($posterFile) {
            $mail->addEmbeddedImage($posterFile, 'offerPoster');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $built['html'];
        $mail->AltBody = $built['alt'];

        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        return ['ok' => false, 'error' => friendlySmtpError($err, $cfg)];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => 'Error: ' . $e->getMessage()];
    }
}
