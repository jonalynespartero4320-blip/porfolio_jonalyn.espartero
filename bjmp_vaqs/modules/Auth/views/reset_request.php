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
  .reset-stage {
    width: min(1080px, 100%);
    display: grid;
    grid-template-columns: minmax(320px, 1fr) minmax(320px, .9fr);
    border-radius: 30px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 28px 70px rgba(8, 19, 31, 0.26);
    backdrop-filter: blur(10px);
  }
  .reset-info {
    position: relative;
    padding: 3rem;
    color: #fff;
    background:
      linear-gradient(145deg, rgba(6, 33, 80, 0.94), rgba(93, 141, 102, 0.92));
  }
  .reset-info::after {
    content: "";
    position: absolute;
    left: -72px;
    bottom: -72px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
  }
  .reset-info-content {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 1.25rem;
  }
  .reset-badge {
    display: inline-flex;
    align-items: center;
    gap: .6rem;
    padding: .75rem 1rem;
    border-radius: 16px;
    background: rgba(151, 187, 146, 0.14);
    width: fit-content;
    border: 1px solid rgba(151, 187, 146, 0.2);
  }
  .reset-points { display: grid; gap: .8rem; }
  .reset-point {
    display: flex;
    gap: .75rem;
    align-items: flex-start;
    padding: .9rem 1rem;
    border-radius: 18px;
    background: rgba(151, 187, 146, 0.14);
  }
  .reset-form-panel {
    background: rgba(255, 255, 255, 0.95);
    padding: 3rem;
  }
  .reset-card {
    max-width: 430px;
    margin: 0 auto;
  }
  .reset-kicker {
    font-size: .8rem;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #062150;
    font-weight: 800;
  }
  .reset-title {
    font-size: clamp(1.9rem, 2.4vw, 2.5rem);
    line-height: 1.08;
    font-weight: 800;
    color: #062150;
    margin-bottom: .75rem;
  }
  .reset-subtitle {
    color: #5b6b79;
    margin-bottom: 1.75rem;
  }
  .reset-input {
    height: 52px;
    border-radius: 16px;
    border: 1px solid rgba(151, 187, 146, 0.34);
    background: #fff;
  }
  .reset-input:focus {
    border-color: rgba(93, 141, 102, 0.78);
    box-shadow: 0 0 0 4px rgba(151, 187, 146, 0.24);
  }
  .reset-submit {
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
    .reset-stage { grid-template-columns: 1fr; }
    .reset-info, .reset-form-panel { padding: 2rem; }
  }
</style>

<div class="auth-shell">
    <div class="reset-stage">
        <section class="reset-info">
            <div class="reset-info-content">
                <div class="reset-badge">
                    <i class="bi bi-key-fill"></i>
                    <span class="fw-semibold">Password Reset Workflow</span>
                </div>
                <div>
                    <div class="text-uppercase small fw-semibold text-white-50 mb-2">Account Recovery</div>
                    <h1 class="display-6 fw-bold mb-3">Request administrator-assisted password recovery</h1>
                    <p class="mb-0 text-white-50">
                        Submit your username for review. A system administrator will verify the request and issue a new approved password when authorized.
                    </p>
                </div>
                <div class="reset-points">
                    <div class="reset-point">
                        <i class="bi bi-send-check fs-4"></i>
                        <div>
                            <div class="fw-semibold">Submit a single request</div>
                            <div class="small text-white-50">Use your correct username so the request can be traced quickly by the admin team.</div>
                        </div>
                    </div>
                    <div class="reset-point">
                        <i class="bi bi-person-badge fs-4"></i>
                        <div>
                            <div class="fw-semibold">Await administrator approval</div>
                            <div class="small text-white-50">Requests are reviewed inside the admin panel before any password is reset.</div>
                        </div>
                    </div>
                    <div class="reset-point">
                        <i class="bi bi-shield-check fs-4"></i>
                        <div>
                            <div class="fw-semibold">Return to secure access</div>
                            <div class="small text-white-50">Once approved, sign in again with the newly issued password and update it immediately.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="reset-form-panel">
            <div class="reset-card">
                <div class="reset-kicker">Forgot Password</div>
                <h2 class="reset-title">Send a reset request</h2>
                <p class="reset-subtitle">Enter your username below and we will route the request for administrator review.</p>

                <?php if (!empty($_SESSION['notification'])): ?>
                    <div class="alert alert-<?= e($_SESSION['notification']['type']) ?> border-0 rounded-4 mb-4">
                        <?= e($_SESSION['notification']['message']) ?>
                    </div>
                    <?php unset($_SESSION['notification']); ?>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/reset-request-save" id="resetForm">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Username</label>
                        <input
                            type="text"
                            name="username"
                            class="form-control reset-input"
                            placeholder="Enter your username"
                            required
                        >
                    </div>

                    <button class="btn btn-success w-100 reset-submit mb-4">
                        <i class="bi bi-send me-1"></i> Submit Reset Request
                    </button>

                    <div class="text-center text-muted small">
                        Remember your password?
                        <a href="<?= BASE_URL ?>/login" class="auth-footer-link">Back to Login</a>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<script>
document.body.classList.add('auth-screen');
</script>
