<?php
/**
 * community/_flash.php — flash message helper for community pages.
 * Reads ?ok= / ?err= / ?warn= query strings (same convention as admin).
 */
function community_flash() {
    $out = '';
    if (!empty($_GET['ok']))   $out .= '<div class="c-alert ok">&#10003; ' . htmlspecialchars($_GET['ok'], ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($_GET['err']))  $out .= '<div class="c-alert err">&#9888; ' . htmlspecialchars($_GET['err'], ENT_QUOTES, 'UTF-8') . '</div>';
    if (!empty($_GET['warn'])) $out .= '<div class="c-alert warn">&#9888; ' . htmlspecialchars($_GET['warn'], ENT_QUOTES, 'UTF-8') . '</div>';
    echo $out;
}
