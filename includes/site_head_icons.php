<?php
declare(strict_types=1);
/**
 * Earth / globe favicon for browser tab & home-screen. Set $SITE_ICON_BASE before include:
 *  '' from site root (index.php); '../' from user/*.php or admin/*.php; '../../' from user/handlers/*.php
 */
$b = isset($SITE_ICON_BASE) ? (string) $SITE_ICON_BASE : '';
unset($SITE_ICON_BASE);
$href = htmlspecialchars($b . 'assets/favicon.svg', ENT_QUOTES, 'UTF-8');
$manifestHref = htmlspecialchars($b . 'assets/manifest.webmanifest', ENT_QUOTES, 'UTF-8');
$scopeHref = htmlspecialchars($b, ENT_QUOTES, 'UTF-8');
?>
<link rel="icon" href="<?= $href ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= $href ?>">
<link rel="manifest" href="<?= $manifestHref ?>">
<meta name="theme-color" content="#B07154">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Book King">
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= $scopeHref ?>sw.js').catch(function () {
            // Keep silent in production UI if SW fails.
        });
    });
}
</script>
