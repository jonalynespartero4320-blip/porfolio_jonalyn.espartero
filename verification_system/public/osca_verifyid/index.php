<?php
declare(strict_types=1);

define('APP_ALLOW_DIRECT_ENTRY', true);

$routeBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$publicPosition = strpos($routeBase, '/public/');
$rootBase = $publicPosition === false ? '' : rtrim(substr($routeBase, 0, $publicPosition), '/');

define('APP_ROUTE_BASE_URL', $routeBase);
define('APP_ROOT_BASE_URL', $rootBase);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

send_security_headers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Home ID Home ID Verification</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/style-osca.css')) ?>" />
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/module-loader.css')) ?>" />
<link rel="stylesheet" href="<?= e(asset_url('assets/css/module-icons.css')) ?>">
</head>
<body>
<?php require_once dirname(__DIR__, 3) . '/demo/home-link.php'; ?>
  <div id="sessionExpiredAlert" class="session-alert" role="alert" aria-live="assertive">
    <i class="bi bi-exclamation-triangle-fill"></i>
    Your session has expired due to inactivity. The page will refresh shortly.
  </div>

  <div id="top-header" class="top-strip">Loading current date and time...</div>

  <main class="page-shell">
    <header class="site-header">
      <div class="header-group">
        <span class="logo module-brand-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
        <span class="logo module-brand-icon" aria-hidden="true"><i class="fa-solid fa-house"></i></span>
        <div class="title-container">
          <p class="eyebrow">Demo Services</p>
          <h1 class="title-pwd-manda">Home ID Services</h1>
          <p class="header-subtitle">Official verification portal for demo-issued Home ID cards</p>
        </div>
        <span class="logo module-brand-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
      </div>

      <nav class="menu-slider" aria-label="Verification systems">
        <a href="<?= e(asset_url('')) ?>" class="menu-items">Dashboard</a>
        <div class="divider">|</div>
        <a href="<?= e(asset_url('public/pdad_verifyid/')) ?>" class="menu-items">Professional ID Verification</a>
        <div class="divider">|</div>
        <a href="<?= e(asset_url('public/osca_verifyid/')) ?>" class="menu-items active-link">Home ID Verification</a>
        <div class="divider">|</div>
        <a href="<?= e(asset_url('public/gad_verifyid/')) ?>" class="menu-items">Food ID Verification</a>
      </nav>
    </header>

    <section class="hero-section">
      <div class="container">
        <div class="hero-grid">
          <article class="hero-panel hero-story">
            <div class="story-badge osca-badge">Home ID Verification</div>
            <h2>Confirm a Demo Home ID with a cleaner, faster flow.</h2>
            <p class="hero-copy">
              Enter the registered 6-digit Home ID, or include the letter suffix if shown on the card, and review a masked verification result for safe confirmation.
            </p>

            <div class="hero-visual">
              <img src="<?= e(asset_url('home.png')) ?>" alt="Home ID sample card" class="hero-image" />
            </div>
          </article>

          <aside class="hero-panel verification-panel">
            <div class="panel-header">
              <p class="panel-kicker osca-badge">Home ID Verification</p>
              <h2>Demo Home ID verification form</h2>
              <p class="panel-copy">Use the exact ID printed on the card. Add the suffix only if it appears on the issued ID.</p>
            </div>

            <form id="verifyForm" class="verification-form" autocomplete="off" novalidate>
              <div class="field-block">
                <label for="senior_citizen_id" class="form-label">Home ID Number</label>
                <div class="input-group id-input-group">
                  <span class="input-group-text icon-box"><i class="fa fa-id-card"></i></span>
                  <input
                    type="text"
                    class="form-control id-entry"
                    id="senior_citizen_id"
                    name="senior_citizen_id"
                    maxlength="8"
                    placeholder="123456 or 123456-T"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    aria-describedby="seniorHelp"
                    required
                  />
                </div>
                <small id="seniorHelp" class="field-help">Accepted formats: 123456 or 123456-T.</small>
              </div>

              <p id="error-message" class="form-feedback" role="alert" aria-live="polite"></p>
              <p id="success-message" class="form-success" aria-live="polite"></p>

              <button type="submit" id="checkVerification" class="action-button">
                <span class="btn-label">Verify Home ID Number</span>
                <span class="btn-loader spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
              </button>
            </form>

            <!-- <div class="trust-strip">
              <div><strong>Format</strong><span>6 digits with optional suffix</span></div>
              <div><strong>Result</strong><span>Masked personal details</span></div>
              <div><strong>Support</strong><span>Home ID help details below</span></div>
            </div> -->
          </aside>
        </div>
      </div>
    </section>
  </main>

  <?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>

  <div class="modal fade" id="verifiedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content result-modal">
        <div class="modal-header border-0">
          <h5 class="modal-title">Home ID Verification Result</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <div class="check-icon">
            <svg class="draw-icon" viewBox="0 0 52 52">
              <circle cx="26" cy="26" r="20"></circle>
              <path d="M14 27 L22 35 L38 19"></path>
            </svg>
          </div>
          <div class="verify-badge mb-4"><span>VERIFIED</span></div>
          <div class="details-box text-start mx-auto">
            <div class="row text-center mb-3 g-3">
              <div class="col-md-4 col-12">
                <div class="small text-muted">First Name</div>
                <div class="fw-semibold name-value" id="v_firstname"></div>
              </div>
              <div class="col-md-4 col-12">
                <div class="small text-muted">Middle Name</div>
                <div class="fw-semibold name-value" id="v_middlename"></div>
              </div>
              <div class="col-md-4 col-12">
                <div class="small text-muted">Last Name</div>
                <div class="fw-semibold name-value" id="v_lastname"></div>
              </div>
            </div>
            <p><span class="label">ID Number</span><span class="value" id="v_senior_citizen_id"></span></p>
            <p class="status-line"><span class="label">Account Status</span><span id="v_acc_status" class="badge rounded-pill"></span></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="notVerifiedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content result-modal">
        <div class="modal-header border-0">
          <h5 class="modal-title">Home ID Verification Result</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <div class="fail-icon">
            <svg class="draw-icon red" viewBox="0 0 52 52">
              <circle cx="26" cy="26" r="20"></circle>
              <path d="M16 16 L36 36 M36 16 L16 36"></path>
            </svg>
          </div>
          <div class="notverify-badge mb-4"><span>NOT VERIFIED</span></div>
          <p class="modal-copy">Home ID is not registered in the database. Please contact the Home ID Services.</p>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
  <script src="<?= e(asset_url('assets/js/module-loader.js?v=verification-enabled-2')) ?>" data-logo="<?= e(asset_url('assets/images/home-mark.svg')) ?>" data-delay="500"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const verifyForm = document.getElementById('verifyForm');
      const seniorIdInput = document.getElementById('senior_citizen_id');
      const errorMessage = document.getElementById('error-message');
      const successMessage = document.getElementById('success-message');
      const submitButton = document.getElementById('checkVerification');
      const buttonLabel = submitButton.querySelector('.btn-label');
      const buttonLoader = submitButton.querySelector('.btn-loader');

      function updateTopHeader() {
        const header = document.getElementById('top-header');
        const now = new Date();
        const date = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const time = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' });
        header.innerHTML = '<i class="bi bi-calendar3"></i> ' + date + ' <span class="dot-separator"></span> <i class="bi bi-clock"></i> ' + time;
      }

      function setLoadingState(isLoading) {
        submitButton.disabled = isLoading;
        buttonLoader.classList.toggle('d-none', !isLoading);
        buttonLabel.textContent = isLoading ? 'Checking record...' : 'Verify Home ID Number';
      }

      function setFeedback(message, type) {
        errorMessage.textContent = type === 'error' ? message : '';
        successMessage.textContent = type === 'success' ? message : '';
      }
      function applyAccountStatus(status) {
        const badge = document.getElementById('v_acc_status');
        const normalized = (status || '').toLowerCase();
        badge.textContent = status || 'Unknown';
        badge.className = 'badge rounded-pill';
        if (normalized === 'active') badge.classList.add('bg-success');
        else if (normalized === 'unclaimed') badge.classList.add('bg-warning', 'text-dark');
        else if (normalized === 'transferred') badge.classList.add('bg-secondary');
        else badge.classList.add('bg-light', 'text-dark');
      }

      seniorIdInput.addEventListener('input', () => {
        let value = seniorIdInput.value.toUpperCase().replace(/[^0-9A-Z-]/g, '');
        if (value.length > 8) value = value.slice(0, 8);
        if (value.length > 6 && value[6] !== '-') {
          value = value.slice(0, 6) + '-' + value.slice(6).replace(/-/g, '');
        }
        seniorIdInput.value = value;
      });

      verifyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        setFeedback('', '');

        const seniorId = seniorIdInput.value.trim().toUpperCase();
        if (!/^\d{6}(-[A-Z])?$/.test(seniorId)) {
          setFeedback('Please use a valid format like 123456 or 123456-T.', 'error');
          seniorIdInput.focus();
          return;
        }

        setLoadingState(true);

        try {
          const response = await fetch('<?= e(endpoint_url('api/verify-osca')) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'senior_citizen_id=' + encodeURIComponent(seniorId)
          });
          const data = await response.json();

          if (data.status === 'success') {
            document.getElementById('v_firstname').textContent = data.firstname || '-';
            document.getElementById('v_middlename').textContent = data.middlename || '-';
            document.getElementById('v_lastname').textContent = data.lastname || '-';
            document.getElementById('v_senior_citizen_id').textContent = data.senior_citizen_id || '-';
            applyAccountStatus(data.acc_status);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('verifiedModal')).show();
            return;
          }

          if (data.status === 'not_found') {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('notVerifiedModal')).show();
            return;
          }

          setFeedback(data.message || 'Unable to verify the provided Home ID.', 'error');
        } catch (error) {
          console.error('Verification error:', error);
          setFeedback('Something went wrong while verifying. Please try again in a moment.', 'error');
        } finally {
          setLoadingState(false);
        }
      });

      let timeoutHandle;
      const timeoutMs = 3 * 60 * 1000;
      function clearSensitiveState() {
        verifyForm.reset();
        setFeedback('', '');
      }
      function showSessionExpired() {
        clearSensitiveState();

        ['verifiedModal', 'notVerifiedModal'].forEach((modalId) => {
          const modalElement = document.getElementById(modalId);
          const modalInstance = bootstrap.Modal.getInstance(modalElement);
          if (modalInstance) {
            modalInstance.hide();
          }
        });

        document.getElementById('sessionExpiredAlert').classList.add('show');
        setTimeout(() => window.location.reload(), 2200);
      }
      function resetSessionTimer() {
        clearTimeout(timeoutHandle);
        timeoutHandle = setTimeout(showSessionExpired, timeoutMs);
      }
      ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach((eventName) => {
        document.addEventListener(eventName, resetSessionTimer, { passive: true });
      });
      window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
          clearSensitiveState();
        }
      });

      updateTopHeader();
      setInterval(updateTopHeader, 1000);
      resetSessionTimer();
    });
  </script>
</body>
</html>
