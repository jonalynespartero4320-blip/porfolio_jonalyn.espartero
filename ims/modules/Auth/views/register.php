<?php include ROOT_PATH.'/modules/layout/bootstrap.php'; ?>

<style>
  body.auth-screen {
    min-height: 100vh;
    background: url('<?= BASE_URL ?>/assets/images/demo-scene.svg') no-repeat center center fixed;
    background-size: cover;
  }
  body.auth-screen::before {
    display: block;
    background: linear-gradient(180deg, rgba(247, 249, 255, 0.78), rgba(243, 247, 255, 0.82));
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
  .auth-submit {
    width: 100%;
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: linear-gradient(135deg, #4b5ef7 0%, #3d73f6 100%);
    color: #fff;
    font-weight: 700;
    box-shadow: 0 14px 26px rgba(75, 94, 247, .22);
    margin-top: 1.25rem;
  }
  .auth-submit:hover {
    color: #fff;
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
  .password-helper {
    display: flex;
    gap: .45rem;
    align-items: center;
    color: #7c84a3;
    font-size: .8rem;
    margin-top: .5rem;
  }
  .password-match {
    color: #2d8d5f;
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
    max-width: 280px;
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
  .auth-progress span:nth-child(2) {
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
                <div class="auth-kicker">Create Account</div>
                <h1 class="auth-title">Register</h1>
                <p class="auth-subtitle">Create your profile and start using the inventory workspace.</p>

                <?php if (!empty($_SESSION['notification'])): ?>
                    <div class="alert alert-<?= e($_SESSION['notification']['type']) ?> py-2 mb-3">
                        <?= e($_SESSION['notification']['message']) ?>
                    </div>
                    <?php unset($_SESSION['notification']); ?>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/register-save" id="registerForm">
                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <input type="text" name="name" class="form-control auth-input" placeholder="Enter your full name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control auth-input" placeholder="Choose a username" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group auth-input-group">
                            <input type="password" name="password" id="password" class="form-control auth-input" placeholder="Create a password" required>
                            <button type="button" class="btn" onclick="togglePassword('password', 'passwordIcon')" aria-label="Toggle password visibility">
                                <i class="bi bi-eye-slash" id="passwordIcon"></i>
                                <span id="passwordIconLabel">Show</span>
                            </button>
                        </div>
                        <div class="password-helper">
                            <i class="bi bi-info-circle"></i>
                            <span>Minimum 6 characters</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm password</label>
                        <div class="input-group auth-input-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control auth-input" placeholder="Confirm your password" required>
                            <button type="button" class="btn" onclick="togglePassword('confirm_password', 'confirmPasswordIcon')" aria-label="Toggle password visibility">
                                <i class="bi bi-eye-slash" id="confirmPasswordIcon"></i>
                                <span id="confirmPasswordIconLabel">Show</span>
                            </button>
                        </div>
                    </div>

                    <div id="passwordError" class="alert alert-danger py-2 px-3 mb-3 d-none">
                        Passwords do not match.
                    </div>
                    <div id="passwordMatchHint" class="password-helper d-none">
                        <i class="bi bi-check-circle"></i>
                        <span>Password confirmation matched.</span>
                    </div>

                    <button class="btn auth-submit">Create Account</button>

                    <div class="auth-links">
                        Already have an account?
                        <a href="<?= BASE_URL ?>/login" class="auth-footer-link">Back to login</a>
                    </div>
                </form>
            </div>
        </section>

        <aside class="auth-visual-panel">
            <div class="auth-visual-card">
                <img src="<?= BASE_URL ?>/assets/images/demo-scene.svg" class="auth-visual-image" alt="Inventory illustration">
                <div class="auth-visual-title">Set up your workspace access</div>
                <p class="auth-visual-text">Create a user profile for equipment records, maintenance tracking, service reports, and transmittal workflows.</p>
                <div class="auth-progress">
                    <span></span><span></span><span></span>
                </div>
            </div>
        </aside>
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
        icon.className = isPassword ? 'bi bi-eye' : 'bi bi-eye-slash';
    }
    const label = document.getElementById(iconId + 'Label');
    if (label) {
        label.textContent = isPassword ? 'Hide' : 'Show';
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
