<?php
require __DIR__ . '/config.php';
$projects = [
    'bjmp' => ['ID & Queuing System', 'Visitor registration & queuing', '../bjmp_vaqs/public/', 'Register visitors, manage records, issue IDs, and follow queue activity.'],
    'ims' => ['Inventory Management System', 'Inventory management system', '../ims/public/', 'Explore equipment, assignments, requests, maintenance, and reports.'],
    'verification' => ['Verification Portal', 'Professional ID, Home ID & Food ID verification', '../verification_system/', 'Verify fictional IDs and see the original privacy-masked responses.'],
];
$key = $_GET['project'] ?? 'bjmp';
if (!is_string($key) || !isset($projects[$key])) { http_response_code(404); exit('Project not found.'); }
[$title, $subtitle, $url, $description] = $projects[$key];
$ready = true;
try {
    $table = $key === 'verification' ? 'verification_info' : 'users';
    foreach ($key === 'verification' ? ['pdad', 'osca'] : [$key] as $db) {
        demo_pdo($db)->query("SELECT 1 FROM $table LIMIT 1");
    }
} catch (PDOException $e) { $ready = false; }
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $title ?> · Live portfolio demo</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f6f5f1;color:#20231f;font:16px/1.65 system-ui,sans-serif}main{max-width:960px;margin:auto;padding:32px 24px 64px}a{color:inherit}nav{display:flex;flex-wrap:wrap;gap:20px;justify-content:space-between;border-bottom:1px solid #d6d8cd;padding-bottom:24px}nav a{text-decoration:none}header{padding:60px 0 24px}.eyebrow{text-transform:uppercase;letter-spacing:.16em;font-size:12px}h1{font-size:clamp(40px,8vw,76px);line-height:1.05;letter-spacing:-.05em;margin:18px 0}h2{font-size:22px;margin-top:0}.intro{max-width:650px;color:#576052}.badge{display:inline-block;border:1px solid #98b673;border-radius:30px;padding:4px 14px;font-size:13px;background:#e9f2de}.panel{background:white;border:1px solid #dce0d4;padding:28px;border-radius:20px;margin:20px 0}.credentials{display:flex;flex-wrap:wrap;gap:30px}code{background:#f0f2e9;border-radius:5px;padding:5px 9px;font-size:16px}small{display:block;color:#66705e}.button{display:inline-block;background:#dfff54;color:#20231f;border:0;border-radius:30px;text-decoration:none;padding:14px 26px;font-weight:650;margin:8px 0}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:12px;border-bottom:1px solid #e5e7df}th{font-size:13px}footer{font-size:13px;color:#66705e;margin-top:24px}@media(max-width:540px){.panel{padding:18px}td,th{padding:8px;font-size:13px}code{font-size:13px}}
</style></head><body><main>
<nav><a href="../index.html#projects">← Portfolio</a><div><?php foreach ($projects as $id => $p): ?><a href="?project=<?= $id ?>" style="margin-left:16px" <?= $id === $key ? 'aria-current="page"' : '' ?>><?= $p[0] ?></a><?php endforeach ?></div></nav>
<header><span class="badge"><?= $ready ? 'Database connected' : 'Setup needed' ?></span><p class="eyebrow"><?= $subtitle ?></p><h1><?= $title ?></h1><p class="intro"><?= $description ?> This is the actual application from the project folder, with a separate database for demo changes.</p></header>
<?php if (!$ready): ?><div class="panel"><h2>Start the demo database</h2><p>Start MySQL in XAMPP, then run <code>C:\xampp\php\php.exe demo/setup.php</code> from the portfolio directory and refresh this page.</p></div>
<?php else: ?>
<section class="panel"><h2><?= $key === 'verification' ? 'Sample verification records' : 'Demo login' ?></h2>
<?php if ($key !== 'verification'): ?>
<div class="credentials"><div><small>Username</small><code>demo</code></div><div><small>Password</small><code>Demo2026!</code></div></div>
<p>Sign in with these credentials to explore the working application. Changes are shared by visitors using this local demo.</p>
<?php else: ?>
<p>No login is needed for public ID lookups. Use these fictional records:</p>
<table><thead><tr><th>Service</th><th>Input</th><th>Result</th></tr></thead><tbody><tr><td>Professional ID</td><td>Demo Barangay · <code>0000001</code></td><td>Verified</td></tr><tr><td>Home ID</td><td><code>000001</code></td><td>Verified</td></tr><tr><td>Food ID</td><td><code>00001</code></td><td>Verified</td></tr><tr><td>Home ID</td><td><code>999999</code></td><td>Not found</td></tr></tbody></table>
<p>For a barangay mismatch, select Sample Barangay and enter <code>0000001</code>. For Food ID, enter 00001.</p>
<?php endif ?>
<a class="button" href="<?= $url ?>" target="_blank" rel="noopener">Open <?= $title ?> ↗</a><small>Opens in a new tab so these instructions stay available.</small></section>
<?php endif ?>
<footer>Portfolio sandbox · Fictional sample records · Changes persist until the demo databases are recreated.</footer>
</main></body></html>
