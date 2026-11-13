<?php
// Shared navigation for the original applications and their login screens.
$demoScriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$demoPortfolioPath = preg_replace('~/(?:ims|bjmp_vaqs|verification_system)/.*$~', '', $demoScriptPath);
$demoHomeUrl = htmlspecialchars($demoPortfolioPath . '/index.html', ENT_QUOTES, 'UTF-8');
?>
<a class="portfolio-home-link" href="<?= $demoHomeUrl ?>" target="_top" aria-label="Back to portfolio home">&#8592; Back to home</a>
<style>
.portfolio-home-link{position:fixed;right:20px;bottom:20px;z-index:10000;display:inline-flex;align-items:center;padding:12px 18px;border:1px solid #c3d752;border-radius:999px;background:#dfff54;color:#20231f!important;font:600 14px/1.4 system-ui,sans-serif;text-decoration:none!important;box-shadow:0 4px 18px #0002}
.portfolio-home-link:hover{background:#eaff94;transform:translateY(-1px)}
.portfolio-home-link:focus-visible{outline:3px solid #164b85;outline-offset:3px}
@media(max-width:600px){.portfolio-home-link{right:12px;bottom:12px;padding:10px 14px}}
@media print{.portfolio-home-link{display:none!important}}
</style>
