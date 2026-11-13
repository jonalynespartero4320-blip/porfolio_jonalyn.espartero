<?php
$page_title = 'My Account';
include ROOT_PATH . '/modules/layout/header.php';

global $pdo;

$sessionUser = current_user();
$userId = (int) ($sessionUser['id'] ?? 0);
$hasName = db_column_exists($pdo, 'users', 'name');
$hasEmail = db_column_exists($pdo, 'users', 'email');

$columns = ['id', 'username'];
if ($hasName) {
    $columns[] = 'name';
}
if ($hasEmail) {
    $columns[] = 'email';
}

$stmt = $pdo->prepare('SELECT ' . implode(', ', $columns) . ' FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="profile-avatar">
                            <?= e(strtoupper(substr((string)($account['name'] ?? $account['username'] ?? 'U'), 0, 1))) ?>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1">My Account</h4>
                            <p class="text-muted mb-0">Update your basic account details for this MIS workspace.</p>
                        </div>
                    </div>

                    <form method="POST" action="<?= BASE_URL ?>/account-save">
                        <?php if ($hasName): ?>
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= e($account['name'] ?? '') ?>" required>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= e($account['username'] ?? '') ?>" required>
                        </div>

                        <?php if ($hasEmail): ?>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= e($account['email'] ?? '') ?>">
                            </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?= e(ucwords(str_replace('_', ' ', $sessionUser['role_name'] ?? 'User'))) ?>" disabled>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle"></i> Save Account
                            </button>
                            <a href="<?= BASE_URL ?>/change-password" class="btn btn-outline-secondary">
                                <i class="bi bi-key"></i> Change Password
                            </a>
                            <a href="<?= BASE_URL ?>/ictd/dashboard" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include ROOT_PATH . '/modules/layout/footer.php'; ?>
