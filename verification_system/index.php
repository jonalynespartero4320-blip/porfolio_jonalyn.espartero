<?php require_once __DIR__ . '/config/bootstrap.php'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verification Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-body">
<div class="top-strip">Online ID Verification <span class="dot-separator"></span> Portfolio Demo</div>
<main class="dashboard-shell">
<header class="dashboard-header">
<div class="dashboard-brand"><h1 class="dashboard-title">Verification Portal</h1></div>
<p class="dashboard-copy">Choose a service to verify your ID.</p>
<nav class="menu-slider" aria-label="Verification services">
<a class="menu-items active-link" href="./" aria-current="page">Dashboard</a><span class="divider" aria-hidden="true">|</span>
<a class="menu-items" href="public/pdad_verifyid/">Professional ID Verification</a><span class="divider" aria-hidden="true">|</span>
<a class="menu-items" href="public/osca_verifyid/">Home ID Verification</a><span class="divider" aria-hidden="true">|</span>
<a class="menu-items" href="public/gad_verifyid/">Food ID Verification</a>
</nav>
</header>
<section class="verification-choice-grid" aria-label="Choose a verification service">
<a class="verification-choice pdad-card" href="public/pdad_verifyid/">
<span class="choice-label">PROFESSIONAL ID</span>
<span class="choice-icon" aria-hidden="true"><i class="fa-solid fa-id-badge"></i></span>

<strong class="choice-title">Professional ID Verification</strong>
<span class="choice-copy">Verify a professional ID using its number and registered barangay.</span>
<span class="choice-action">Verify Professional ID <span aria-hidden="true">&#8594;</span></span>
<span class="choice-image-wrap"><img class="choice-image" src="professional.png" alt="Professional ID sample card" width="1536" height="1024"></span>
</a>
<a class="verification-choice osca-card" href="public/osca_verifyid/">
<span class="choice-label">HOME ID</span>
<span class="choice-icon" aria-hidden="true"><i class="fa-solid fa-house"></i></span>

<strong class="choice-title">Home ID Verification</strong>
<span class="choice-copy">Look up a home ID and check its verification result.</span>
<span class="choice-action">Verify Home ID <span aria-hidden="true">&#8594;</span></span>
<span class="choice-image-wrap"><img class="choice-image" src="home.png" alt="Home ID sample card" width="1536" height="1024"></span>
</a>
<a class="verification-choice" href="public/gad_verifyid/">
<span class="choice-label">FOOD ID</span>
<span class="choice-icon" aria-hidden="true"><i class="fa-solid fa-basket-shopping"></i></span>

<strong class="choice-title">Food ID Verification</strong>
<span class="choice-copy">Check a food ID against the available demo records.</span>
<span class="choice-action">Verify Food ID <span aria-hidden="true">&#8594;</span></span>
<span class="choice-image-wrap"><img class="choice-image" src="food.png" alt="Food ID sample card" width="1536" height="1024"></span>
</a>
</section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body></html>
