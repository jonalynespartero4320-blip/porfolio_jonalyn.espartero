<?php include ROOT_PATH.'/modules/layout/bootstrap.php'; ?>
<?php require_once dirname(__DIR__, 4) . '/demo/home-link.php'; ?>

<style>
  body.auth-screen {
    min-height: 100vh;
    background: #edf1fa;
    position: relative;
    isolation: isolate;
  }
  body.auth-screen::before {
    content: "";
    display: block;
    position: fixed;
    inset: -180px -180px auto auto;
    width: 440px;
    height: 440px;
    border: 54px solid rgba(75, 94, 247, .06);
    border-radius: 50%;
    background: transparent;
    pointer-events: none;
    z-index: -1;
  }
  body.auth-screen::after {
    content: "";
    position: fixed;
    left: -90px;
    bottom: -110px;
    width: 280px;
    height: 280px;
    border: 1px solid rgba(75, 94, 247, .12);
    border-radius: 48px;
    transform: rotate(25deg);
    pointer-events: none;
    z-index: -1;
  }
  .auth-shell {
    min-height: 100vh;
    padding: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .auth-stage {
    width: min(1120px, 96vw);
    display: grid;
    grid-template-columns: minmax(360px, 1fr) minmax(320px, .92fr);
    background: rgba(255,255,255,.88);
    border: 1px solid rgba(122, 145, 255, .16);
    border-radius: 28px;
    overflow: hidden;
    box-shadow: 0 30px 70px rgba(52, 72, 132, 0.18);
    backdrop-filter: blur(14px);
  }
  .auth-form-panel {
    padding: 56px 54px 40px;
    background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(251,252,255,.92));
  }
  .auth-visual-panel {
    position: relative;
    padding: 42px 34px;
    background:
      radial-gradient(circle at top right, rgba(79, 108, 255, .14), transparent 30%),
      linear-gradient(180deg, rgba(244, 247, 255, .96), rgba(239, 243, 252, .96));
    border-left: 1px solid rgba(122, 145, 255, .12);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .auth-form-card {
    max-width: 350px;
    margin: 0 auto;
  }
  .auth-kicker {
    color: #4d63f0;
    font-size: .82rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
    margin-bottom: 22px;
  }
  .auth-title {
    font-size: 2rem;
    font-weight: 800;
    color: #1c2440;
    margin-bottom: .45rem;
  }
  .auth-subtitle {
    color: #6e7695;
    font-size: .96rem;
    margin-bottom: 1.9rem;
  }
  .form-label {
    color: #8188a6;
    font-size: .76rem;
    font-weight: 700;
    margin-bottom: .45rem;
  }
  .auth-input {
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: #eef2fb;
    padding-inline: 14px;
    color: #20284a;
  }
  .auth-input:focus {
    background: #edf2ff;
    box-shadow: 0 0 0 .2rem rgba(77, 99, 240, .14);
  }
  .auth-input-group .btn {
    border: 0;
    background: #eef2fb;
    color: #7b84a7;
    border-radius: 10px;
  }
  .auth-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 1.25rem;
    font-size: .82rem;
    color: #7c84a3;
  }
  .auth-submit {
    width: 100%;
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: linear-gradient(135deg, #4b5ef7 0%, #3d73f6 100%);
    color: #fff;
    font-weight: 700;
    box-shadow: 0 14px 26px rgba(75, 94, 247, .22);
  }
  .auth-submit:hover {
    color: #fff;
    box-shadow: 0 18px 30px rgba(75, 94, 247, .28);
  }
  .auth-links {
    margin-top: 1.2rem;
    text-align: center;
    color: #8a90aa;
    font-size: .84rem;
  }
  .auth-footer-link {
    color: #4b5ef7;
    font-weight: 700;
    text-decoration: none;
  }
  .auth-footer-link:hover {
    text-decoration: underline;
  }
  .auth-remember {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
  }
  .auth-remember input {
    accent-color: #4b5ef7;
  }
  .auth-visual-card {
    width: 100%;
    max-width: 360px;
    text-align: center;
  }
  .auth-visual-image {
    width: 100%;
    max-width: 280px;
    height: auto;
    margin: 0 auto 1rem;
    display: block;
  }
  .auth-visual-title {
    font-size: 1.2rem;
    font-weight: 800;
    color: #1f2748;
    margin-bottom: .55rem;
  }
  .auth-visual-text {
    color: #7c84a3;
    font-size: .9rem;
    line-height: 1.65;
    max-width: 270px;
    margin: 0 auto 1.2rem;
  }
  .auth-progress {
    display: flex;
    justify-content: center;
    gap: .45rem;
  }
  .auth-progress span {
    width: 30px;
    height: 4px;
    border-radius: 999px;
    background: rgba(75, 94, 247, .18);
  }
  .auth-progress span:first-child {
    background: #4b5ef7;
  }
  .alert {
    border-radius: 12px;
    border: 0;
  }
  @media (max-width: 900px) {
    .auth-shell {
      padding: 18px;
    }
    .auth-stage {
      grid-template-columns: 1fr;
    }
    .auth-visual-panel {
      order: -1;
      padding: 26px 22px;
      border-left: 0;
      border-bottom: 1px solid rgba(122, 145, 255, .12);
    }
    .auth-form-panel {
      padding: 32px 22px 28px;
    }
    .auth-visual-image {
      max-width: 220px;
    }
  }
</style>

<div class="auth-shell">
    <div class="auth-stage">
        <section class="auth-form-panel">
            <div class="auth-form-card">
                <div class="auth-kicker">Inventory Management System</div>
                <h1 class="auth-title">Login</h1>
                <p class="auth-subtitle">Access your inventory workspace with your email or username.</p>

                <?php if (!empty($_SESSION['notification'])): ?>
                    <?php $loginAlert = $_SESSION['notification']; unset($_SESSION['notification']); ?>
                    <div class="alert alert-<?= e(($loginAlert['type'] ?? '') === 'error' ? 'danger' : ($loginAlert['type'] ?? 'info')) ?> py-2 mb-3">
                        <?= e($loginAlert['message'] ?? '') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/login">
                    <div class="mb-3">
                        <label class="form-label">Email or username</label>
                        <input type="text" name="identifier" class="form-control auth-input" placeholder="Enter your email or username" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group auth-input-group">
                            <input type="password" name="password" id="password" class="form-control auth-input" placeholder="Enter your password" required>
                            <button type="button" class="btn" onclick="togglePassword()" aria-label="Toggle password visibility">
                                <i class="bi bi-eye-slash" id="passwordToggleIcon"></i>
                                <span id="passwordToggleLabel">Show</span>
                            </button>
                        </div>
                    </div>

                    <div class="auth-meta">
                        <label class="auth-remember">
                            <input type="checkbox" disabled>
                            <span>Remember me</span>
                        </label>
                        <a href="<?= BASE_URL ?>/reset-request" class="auth-footer-link">Forgot password?</a>
                    </div>

                    <button class="btn auth-submit">Login</button>

                    <div class="auth-links">
                        Don't have an account?
                        <a href="<?= BASE_URL ?>/register" class="auth-footer-link">Sign up</a>
                    </div>
                </form>
            </div>
        </section>

        <aside class="auth-visual-panel">
            <div class="auth-visual-card">
                <img src="<?= BASE_URL ?>/assets/images/demo-scene.svg" class="auth-visual-image" alt="Inventory illustration">
                <div class="auth-visual-title">Check your project progress</div>
                <p class="auth-visual-text">Monitor equipment, assignments, maintenance, and service activity from one focused inventory workspace.</p>
                <div class="auth-progress">
                    <span></span><span></span><span></span>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
document.body.classList.add('auth-screen');

function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('passwordToggleIcon');
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    if (icon) {
        icon.className = isPassword ? 'bi bi-eye' : 'bi bi-eye-slash';
    }
    const label = document.getElementById('passwordToggleLabel');
    if (label) {
        label.textContent = isPassword ? 'Hide' : 'Show';
    }
}
</script>
