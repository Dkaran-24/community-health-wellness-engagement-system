<?php
/**
 * includes/output_guard.php — central output-buffer guard for New Life Fitness.
 *
 * WHY THIS EXISTS
 *   Many pages include their layout header (which prints the page head +
 *   navigation) BEFORE running POST handlers that call header('Location: …').
 *   Once HTML has been sent to the browser, PHP cannot send redirect headers
 *   anymore — the classic "Cannot modify header information - headers already
 *   sent" warning. On XAMPP the default `output_buffering = 4096` in php.ini
 *   happened to mask this bug for years; production hosts and stricter
 *   php.ini settings expose it immediately.
 *
 * HOW IT WORKS
 *   1. Every protected page begins by loading one of the three auth guards
 *      (includes/auth.php, includes/community_auth.php, includes/member_auth.php).
 *      Those guards now start a single output buffer via this file.
 *   2. The buffer collects everything the page prints, so header()/setcookie()
 *      calls made at ANY point in the request still work — headers go out
 *      before the buffered body.
 *   3. If a Location redirect is sent, register_shutdown_function discards the
 *      buffer so the user never sees the half-rendered page behind a redirect.
 *   4. Normal page rendering is untouched: at request end the buffer is simply
 *      flushed to the browser as usual.
 *
 * This is the same approach used by mainstream PHP frameworks (Laravel's
 * middleware, Symfony's kernel response handling) and makes the app safe on
 * ANY hosting configuration, regardless of php.ini output_buffering settings.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('NLF_OUTPUT_GUARD')) {
    define('NLF_OUTPUT_GUARD', true);

    /* Start one buffer level we control. ob_start() with a chunk size of 0
       keeps everything in memory until the request ends. */
    ob_start();

    /* When the script ends: if a redirect header was queued, throw the
       buffered page away (the browser is following the Location header, so
       any body we already printed would be discarded by the browser anyway —
       and printing it is what triggers the warning). Otherwise flush the
       buffer normally so the page displays exactly as before. */
    register_shutdown_function(function () {
        $redirecting = false;
        if (function_exists('headers_list')) {
            foreach (headers_list() as $h) {
                if (stripos($h, 'Location:') === 0) {
                    $redirecting = true;
                    break;
                }
            }
        }
        while (ob_get_level() > 0) {
            if ($redirecting) {
                ob_end_clean();      /* discard half-rendered page */
            } else {
                ob_end_flush();      /* normal page delivery */
            }
        }
    });
}
