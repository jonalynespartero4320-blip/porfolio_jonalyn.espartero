<?php include ROOT_PATH.'/modules/layout/header.php'; ?>

<h2 class="mb-4">Dashboard</h2>

<div class="row">

<div class="col-md-3">
<div class="card shadow-sm">
<div class="card-body">
<h5>Page 1</h5>
<p>Access Page 1 module</p>
<a href="<?= BASE_URL ?>/page1" class="btn btn-primary btn-sm">Open</a>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card shadow-sm">
<div class="card-body">
<h5>Page 2</h5>
<p>Access Page 2 module</p>
<a href="<?= BASE_URL ?>/page2" class="btn btn-primary btn-sm">Open</a>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card shadow-sm">
<div class="card-body">
<h5>Page 3</h5>
<p>Access Page 3 module</p>
<a href="<?= BASE_URL ?>/page3" class="btn btn-primary btn-sm">Open</a>
</div>
</div>
</div>

<div class="col-md-3">
<div class="card shadow-sm">
<div class="card-body">
<h5>Admin Panel</h5>
<p>System administration</p>
<a href="<?= BASE_URL ?>/admin" class="btn btn-danger btn-sm">Open</a>
</div>
</div>
</div>

</div>

<?php include ROOT_PATH.'/modules/layout/footer.php'; ?>