<?php include ROOT_PATH.'/modules/layout/bootstrap.php'; ?>

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
  .register-stage {
    width: min(1180px, 100%);
    display: grid;
    grid-template-columns: minmax(320px, 1fr) minmax(320px, 1fr);
    border-radius: 30px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 28px 70px rgba(8, 19, 31, 0.26);
    backdrop-filter: blur(10px);
  }
  .register-info {
    position: relative;
    padding: 3rem;
    color: #fff;
    background:
      linear-gradient(145deg, rgba(6, 33, 80, 0.94), rgba(93, 141, 102, 0.92));
  }
  .register-info::after {
    content: "";
    position: absolute;
    right: -80px;
    top: -80px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
  }
  .register-info-content {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 1.35rem;
  }
  .register-badge {
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    padding: .75rem 1rem;
    border-radius: 16px;
    background: rgba(151, 187, 146, 0.14);
    width: fit-content;
    border: 1px solid rgba(151, 187, 146, 0.2);
  }
  .register-points { display: grid; gap: .8rem; }
  .register-point {
    display: flex;
    gap: .75rem;
    align-items: flex-start;
    padding: .9rem 1rem;
    border-radius: 18px;
    background: rgba(151, 187, 146, 0.14);
  }
  .register-form-panel {
    background: rgba(255, 255, 255, 0.95);
    padding: 3rem;
  }
  .register-card {
    max-width: 470px;
    margin: 0 auto;
  }
  .register-kicker {
    font-size: .8rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #062150;
    font-weight: 800;
  }
  .register-title {
    font-size: clamp(1.95rem, 2.5vw, 2.6rem);
    line-height: 1.06;
    font-weight: 800;
    color: #062150;
    margin-bottom: .75rem;
  }
  .register-subtitle {
    color: #5b6b79;
    margin-bottom: 1.75rem;
  }
  .register-input {
    height: 52px;
    border-radius: 16px;
    border: 1px solid rgba(151, 187, 146, 0.34);
    background: #fff;
    padding-inline: 1rem;
  }
  .register-input:focus {
    border-color: rgba(93, 141, 102, 0.78);
    box-shadow: 0 0 0 4px rgba(151, 187, 146, 0.24);
  }
  .register-input-group .btn {
    border-radius: 16px;
    min-width: 56px;
  }
  .register-submit {
    height: 52px;
    border-radius: 16px;
    font-weight: 700;
    box-shadow: 0 14px 28px rgba(6, 33, 80, 0.20);
  }
  .password-helper {
    display: flex;
    gap: .5rem;
    align-items: center;
    color: #64748b;
    font-size: .82rem;
    margin-top: .55rem;
  }
  .password-match {
    color: #5D8D66;
  }
  .password-mismatch {
    color: #dc2626;
  }
  .auth-footer-link {
    color: #062150;
    font-weight: 600;
    text-decoration: none;
  }
  .auth-footer-link:hover { color: #5D8D66; }
  @media (max-width: 991px) {
    .register-stage { grid-template-columns: 1fr; }
    .register-info, .register-form-panel { padding: 2rem; }
  }
</style>

<div class="auth-shell">
    <div class="register-stage">
        <section class="register-info">
            <div class="register-info-content">
                <div class="register-badge">
                    <i class="bi bi-person-badge-fill"></i>
                    <span class="fw-semibold">Account Provisioning</span>
                </div>
                <div>
                    <div class="text-uppercase small fw-semibold text-white-50 mb-2">Staff Registration</div>
                    <h1 class="display-6 fw-bold mb-3">Create a new system account</h1>
                    <p class="mb-0 text-white-50">
                        Fill in your name, username, and password to register.
                    </p>
                </div>
                <div class="register-points">
                    <div class="register-point">
                        <i class="bi bi-shield-check fs-4"></i>
                        <div>
                            <div class="fw-semibold">Default access</div>
                            <div class="small text-white-50">New accounts start as staff.</div>
                        </div>
                    </div>
                    <div class="register-point">
                        <i class="bi bi-diagram-3 fs-4"></i>
                        <div>
                            <div class="fw-semibold">Permission-based</div>
                            <div class="small text-white-50">Extra actions need admin approval.</div>
                        </div>
                    </div>
                    <div class="register-point">
                        <i class="bi bi-journal-check fs-4"></i>
                        <div>
                            <div class="fw-semibold">Quick setup</div>
                            <div class="small text-white-50">Register first, then sign in.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="register-form-panel">
            <div class="register-card">
                <div class="register-kicker">Create Account</div>
                <h2 class="register-title">Register your profile</h2>
                <p class="register-subtitle">Enter your account details below.</p>

                <?php if (!empty($_SESSION['notification'])): ?>
                    <div class="alert alert-<?= e($_SESSION['notification']['type']) ?> border-0 rounded-4 mb-4">
                        <?= e($_SESSION['notification']['message']) ?>
                    </div>
                    <?php unset($_SESSION['notification']); ?>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/register-save" id="registerForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input
                            type="text"
                            name="name"
                            class="form-control register-input"
                            placeholder="Enter your full name"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username</label>
                        <input
                            type="text"
                            name="username"
                            class="form-control register-input"
                            placeholder="Choose a username"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <div class="input-group register-input-group">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control register-input"
                                placeholder="Create a password"
                                required
                            >
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password', 'passwordIcon')" aria-label="Toggle password visibility">
                                <i class="bi bi-eye" id="passwordIcon"></i>
                            </button>
                        </div>
                        <div class="password-helper">
                            <i class="bi bi-info-circle"></i>
                            <span>Minimum 6 characters</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm Password</label>
                        <div class="input-group register-input-group">
                            <input
                                type="password"
                                name="confirm_password"
                                id="confirm_password"
                                class="form-control register-input"
                                placeholder="Confirm your password"
                                required
                            >
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password', 'confirmPasswordIcon')" aria-label="Toggle password visibility">
                                <i class="bi bi-eye" id="confirmPasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div id="passwordError" class="alert alert-danger border-0 rounded-4 py-2 px-3 mb-3 d-none">
                        Passwords do not match.
                    </div>
                    <div id="passwordMatchHint" class="password-helper d-none">
                        <i class="bi bi-check-circle"></i>
                        <span>Password confirmation matched.</span>
                    </div>

                    <button class="btn btn-success w-100 register-submit my-4">
                        <i class="bi bi-person-plus me-1"></i> Create Account
                    </button>

                    <div class="text-center text-muted small">
                        Already have an account?
                        <a href="<?= BASE_URL ?>/login" class="auth-footer-link">Back to Login</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<script>
document.body.classList.add('auth-screen');

function togglePassword(id, iconId) {
    const input = document.getElementById(id);
    const icon = document.getElementById(iconId);
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    if (icon) {
        icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
}

const registerForm = document.getElementById('registerForm');
const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const passwordError = document.getElementById('passwordError');
const passwordMatchHint = document.getElementById('passwordMatchHint');

function syncPasswordHint() {
    const password = passwordInput.value;
    const confirm = confirmInput.value;

    if (!confirm) {
        passwordError.classList.add('d-none');
        passwordMatchHint.classList.add('d-none');
        passwordMatchHint.classList.remove('password-match');
        return;
    }

    if (password === confirm) {
        passwordError.classList.add('d-none');
        passwordMatchHint.classList.remove('d-none');
        passwordMatchHint.classList.add('password-match');
        return;
    }

    passwordMatchHint.classList.add('d-none');
    passwordError.classList.remove('d-none');
}

passwordInput.addEventListener('input', syncPasswordHint);
confirmInput.addEventListener('input', syncPasswordHint);

registerForm.addEventListener('submit', function (e) {
    if (passwordInput.value !== confirmInput.value) {
        e.preventDefault();
        passwordError.classList.remove('d-none');
        passwordMatchHint.classList.add('d-none');
        confirmInput.focus();
    }
});
</script>
