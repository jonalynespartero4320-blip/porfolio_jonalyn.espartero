<?php include ROOT_PATH.'/modules/layout/bootstrap.php'; ?>
<?php require_once dirname(__DIR__, 4) . '/demo/home-link.php'; ?>

<style>
  body.auth-screen {
    min-height: 100vh;
    background:
      linear-gradient(rgba(7, 18, 29, 0.54), rgba(7, 18, 29, 0.66)),
      url('<?= BASE_URL ?>/assets/images/demo-scene.svg') center center / cover no-repeat fixed;
  }
  body.auth-screen::before { display: none; }
  .auth-shell {
    min-height: 100vh;
    padding: 2rem 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .auth-stage {
    width: min(1180px, 100%);
    display: grid;
    grid-template-columns: minmax(320px, 1.05fr) minmax(320px, .95fr);
    border-radius: 30px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 28px 70px rgba(8, 19, 31, 0.26);
    backdrop-filter: blur(10px);
  }
  .auth-brand-panel {
    position: relative;
    padding: 3rem;
    color: #fff;
    background:
      linear-gradient(145deg, rgba(6, 33, 80, 0.94), rgba(93, 141, 102, 0.92));
  }
  .auth-brand-panel::after {
    content: "";
    position: absolute;
    right: -88px;
    bottom: -88px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
  }
  .auth-brand-content {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 1.5rem;
    height: 100%;
  }
  .auth-logo-strip {
    display: inline-flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    padding: .2rem 1rem;
    border-radius: 30px;
    background: rgba(151, 187, 146, 0.14);
    border: 1px solid rgba(151, 187, 146, 0.22);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.14), 0 16px 34px rgba(0,0,0,.12);
  }
  /* .auth-brand-logo {
    width: 86px;
    height: 86px;
    object-fit: contain;
    filter: drop-shadow(0 8px 14px rgba(0,0,0,.18));
  } */
  .auth-brand-logo-bp {
    width: 90px;
    height: 90px;
    object-fit: contain;
    filter: drop-shadow(0 8px 14px rgba(0,0,0,.18));
  }
  .auth-brand-logo-bjmp {
    width: 90px;
    height: 90px;
    object-fit: contain;
    filter: drop-shadow(0 8px 14px rgba(0,0,0,.18));
  }
  .auth-brand-logo-bjmpn {
    width: 78px;
    height: 78px;
    object-fit: contain;
    filter: drop-shadow(0 8px 14px rgba(0,0,0,.18));
  }
  .auth-brand-logo.logo-wide { width: 94px; }
  .auth-logo-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: clamp(64px, 8vw, 90px);
    height: clamp(64px, 8vw, 90px);
    border-radius: 16px;
    background: rgba(255, 255, 255, .12);
    border: 1px solid rgba(255, 255, 255, .2);
    color: #fff;
    font-size: clamp(30px, 4vw, 42px);
  }
  .auth-feature-list { display: grid; gap: .85rem; }
  .auth-feature {
    display: flex;
    gap: .8rem;
    align-items: flex-start;
    padding: .9rem 1rem;
    border-radius: 18px;
    background: rgba(151, 187, 146, 0.14);
    border: 1px solid rgba(151, 187, 146, 0.18);
  }
  .auth-form-panel {
    background: rgba(255, 255, 255, 0.95);
    padding: 3rem;
  }
  .auth-form-card {
    max-width: 440px;
    margin: 0 auto;
  }
  .auth-kicker {
    font-size: .8rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #062150;
    font-weight: 800;
  }
  .auth-title {
    font-size: clamp(2rem, 2.6vw, 2.6rem);
    line-height: 1.05;
    font-weight: 800;
    color: #062150;
    margin-bottom: .75rem;
  }
  .auth-subtitle {
    color: #5b6b79;
    margin-bottom: 1.75rem;
  }
  .auth-input {
    height: 52px;
    border-radius: 16px;
    border: 1px solid rgba(151, 187, 146, 0.34);
    background: #fff;
    padding-inline: 1rem;
  }
  .auth-input:focus {
    border-color: rgba(93, 141, 102, 0.78);
    box-shadow: 0 0 0 4px rgba(151, 187, 146, 0.24);
  }
  .auth-input-group .btn {
    border-radius: 16px;
    min-width: 56px;
  }
  .auth-submit {
    height: 52px;
    border-radius: 16px;
    font-weight: 700;
    box-shadow: 0 14px 28px rgba(6, 33, 80, 0.20);
  }
  .auth-footer-link {
    color: #062150;
    font-weight: 600;
    text-decoration: none;
  }
  .auth-footer-link:hover { color: #5D8D66; }
  @media (max-width: 991px) {
    .auth-stage { grid-template-columns: 1fr; }
    .auth-brand-panel, .auth-form-panel { padding: 2rem; }
    .auth-brand-logo { width: 72px; height: 72px; }
    .auth-brand-logo.logo-wide { width: 80px; }
  }
</style>

<div class="auth-shell">
    <div class="auth-stage">
        <section class="auth-brand-panel">
            <div class="auth-brand-content">
                <div>
                    <div class="auth-logo-strip" aria-label="Demo symbols">
                        <span class="auth-logo-icon" role="img" aria-label="ID verification"><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
                        <span class="auth-logo-icon" role="img" aria-label="QR code lookup"><i class="bi bi-qr-code-scan" aria-hidden="true"></i></span>
                        <span class="auth-logo-icon" role="img" aria-label="Queue management"><i class="bi bi-people" aria-hidden="true"></i></span>
                       
                       
                    </div>
                </div>
                <div>
                    <div class="auth-kicker text-white-50">Demo Service Center</div>
                    <h1 class="display-5 fw-bold mb-3">ID & Queuing System</h1>
                     <p class="mb-0 text-white">
                        ID & Queuing System
                    </p>
                    <p class="mb-0 text-white-50">
                        Visitor verification, queue control, ID issuance, and operational reporting..
                    </p>
                </div>
                <div class="auth-feature-list">
                    <div class="auth-feature">
                        <i class="bi bi-person-check fs-4"></i>
                        <div>
                            <div class="fw-semibold">Controlled Access</div>
                            <div class="small text-white-50">Role-based permissions and supervised operations for every module action.</div>
                        </div>
                    </div>
                    <div class="auth-feature">
                        <i class="bi bi-clock-history fs-4"></i>
                        <div>
                            <div class="fw-semibold">Live Admission Tracking</div>
                            <div class="small text-white-50">Queue monitoring, time in, time out, and stay-duration visibility in real time.</div>
                        </div>
                    </div>
                    <div class="auth-feature">
                        <i class="bi bi-file-earmark-bar-graph fs-4"></i>
                        <div>
                            <div class="fw-semibold">Operational Reporting</div>
                            <div class="small text-white-50">Detailed reports, exports, and audit-ready workflows aligned with jail operations.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="auth-form-panel">
            <div class="auth-form-card">
                <div class="auth-kicker">Authorized Access</div>
                <h2 class="auth-title">Sign in to continue</h2>
                <p class="auth-subtitle">Use your approved account credentials to open the ID & Queuing System.</p>

                <?php if (!empty($_SESSION['notification'])): ?>
                    <?php
                        $noticeType = $_SESSION['notification']['type'] ?? 'info';
                        $noticeClass = $noticeType === 'success' ? 'alert-success' : ($noticeType === 'warning' ? 'alert-warning' : ($noticeType === 'info' ? 'alert-info' : 'alert-danger'));
                    ?>
                    <div class="alert <?= e($noticeClass) ?> border-0 rounded-4 mb-4">
                        <?= e($_SESSION['notification']['message'] ?? 'Unable to sign in.') ?>
                    </div>
                    <?php unset($_SESSION['notification']); ?>
                <?php elseif (!empty($_SESSION['error'])): ?>
                    <div class="alert alert-danger border-0 rounded-4 mb-4">
                        <?= e($_SESSION['error']) ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/login" autocomplete="off" autocapitalize="off" spellcheck="false">
                    <input type="text" name="fake_username" autocomplete="username" tabindex="-1" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; opacity:0;">
                    <input type="password" name="fake_password" autocomplete="current-password" tabindex="-1" aria-hidden="true" style="position:absolute; left:-9999px; width:1px; height:1px; opacity:0;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input
                            type="text"
                            name="username"
                            id="loginUsername"
                            class="form-control auth-input"
                            placeholder="Enter your username or email"
                            autocomplete="off"
                            autocapitalize="none"
                            spellcheck="false"
                            required
                            autofocus
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <div class="input-group auth-input-group">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control auth-input"
                                placeholder="Enter your password"
                                autocomplete="new-password"
                                required
                            >
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()" aria-label="Toggle password visibility">
                                <i class="bi bi-eye" id="passwordToggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="small text-muted">Protected by role-based access control</span>
                        <a href="<?= BASE_URL ?>/reset-request" class="auth-footer-link small">Forgot Password?</a>
                    </div>

                    <button class="btn btn-success w-100 auth-submit mb-4">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                    </button>

                    <div class="text-center text-muted small">
                        Need a system account?
                        <a href="<?= BASE_URL ?>/register" class="auth-footer-link">Register here</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<script>
document.body.classList.add('auth-screen');

function clearAutofilledLoginFields() {
    const username = document.getElementById('loginUsername');
    const password = document.getElementById('password');
    if (username) username.value = '';
    if (password) password.value = '';
}

window.addEventListener('pageshow', clearAutofilledLoginFields);
window.addEventListener('load', function () {
    setTimeout(clearAutofilledLoginFields, 50);
});

function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('passwordToggleIcon');
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    if (icon) {
        icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
}
</script>
