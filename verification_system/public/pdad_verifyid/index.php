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
  <title>Professional ID Professional ID Verification</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/style-pdad.css')) ?>" />
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
        <span class="logo module-brand-icon" aria-hidden="true"><i class="fa-solid fa-id-badge"></i></span>
        <div class="title-container">
          <p class="eyebrow">Demo Services</p>
          <h1 class="title-pwd-manda">Professional Affairs Division</h1>
          <p class="header-subtitle">Official Professional ID verification portal for demo-issued cards</p>
        </div>
        <span class="logo module-brand-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
      </div>

      <nav class="menu-slider" aria-label="Verification systems">
        <a href="<?= e(asset_url('')) ?>" class="menu-items">Dashboard</a>
        <div class="divider">|</div>
        <a href="<?= e(asset_url('public/pdad_verifyid/')) ?>" class="menu-items active-link">Professional ID Verification</a>
        <div class="divider">|</div>
        <a href="<?= e(asset_url('public/osca_verifyid/')) ?>" class="menu-items">Home ID Verification</a>
        <div class="divider">|</div>
        <a href="<?= e(asset_url('public/gad_verifyid/')) ?>" class="menu-items">Food ID Verification</a>
      </nav>
    </header>

    <section class="hero-section">
      <div class="container">
        <div class="hero-grid">
          <article class="hero-panel hero-story">
            <div class="story-badge">Professional ID Verification</div>
            <h2>Verify a Demo-issued Professional ID in a few seconds.</h2>
            <p class="hero-copy">
              Select the registered barangay, enter the last 7 digits of the ID number, and review a masked verification result for privacy-safe confirmation.
            </p>

            <div class="feature-list">
              <div class="feature-card">
                <span class="feature-icon"><i class="bi bi-shield-check"></i></span>
                <div>
                  <h3>Secure verification</h3>
                  <p>Personal details are protected and only limited masked information is shown.</p></div>
              </div>
              <div class="feature-card">
                <span class="feature-icon"><i class="bi bi-lightning-charge"></i></span>
                <div>
                  <h3>Accessible public service</h3>
                  <p>Designed to provide a clear, respectful, and reliable verification experience for every user.</p>
                </div>
              </div>
            </div>

            <div class="hero-visual">
              <img src="<?= e(asset_url('professional.png')) ?>" alt="professional ID sample card" class="hero-image" />
            </div>
          </article>

          <aside class="hero-panel verification-panel">
            <div class="panel-header">
              <p class="panel-kicker">Professional ID Verification</p>
              <h2>Demo City verification form</h2>
              <p class="panel-copy">Use the barangay registered with the ID. Only the final 7 digits are required.</p>
            </div>

            <form id="verifyForm" class="verification-form" autocomplete="off" novalidate>
              <input type="hidden" id="brgy_code" name="brgy_code" />

              <div class="field-block">
                <label for="brgySelect" class="form-label">Barangay</label>
                <select id="brgySelect" class="form-select form-select-lg" autocomplete="off" required>
                  <option value="">Select a barangay</option>
                </select>
                <small class="field-help">The selected barangay must match the registered Professional ID record.</small>
              </div>

              <div class="field-block">
                <label for="unique_id" class="form-label">Professional ID Number</label>
                <div class="input-group id-input-group">
                  <span class="input-group-text icon-box"><i class="fa fa-hashtag"></i></span>
                  <span class="input-group-text prefix-box">PRO-ID</span>
                  <input
                    type="text"
                    class="form-control id-entry"
                    id="unique_id"
                    name="unique_id"
                    inputmode="numeric"
                    maxlength="7"
                    placeholder="0000000"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    aria-describedby="pwdHelp"
                    required
                  />
                </div>
                <small id="pwdHelp" class="field-help">Example: if the card shows PRO-ID-0000001, enter 0000001. For the demo, select Demo Barangay.</small>
              </div>

              <p id="error-message" class="form-feedback" role="alert" aria-live="polite"></p>
              <p id="success-message" class="form-success" aria-live="polite"></p>

              <button type="submit" id="checkVerification" class="action-button">
                <span class="btn-label">Verify Professional ID</span>
                <span class="btn-loader spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
              </button>
            </form>
            <p class="field-help mt-3 mb-2">Need to verify another type of ID? Choose a service from the dashboard.</p>
            <div class="d-grid gap-2">
              <a href="<?= e(asset_url('')) ?>" class="btn btn-outline-primary py-3">Back to Verification Dashboard</a>
            </div>
            <!-- <div class="trust-strip">
              <div><strong>Privacy</strong><span>Masked result details</span></div>
              <div><strong>Status</strong><span>Validity check included</span></div>
              <div><strong>Support</strong><span>Professional ID contacts below</span></div>
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
          <h5 class="modal-title">Professional ID Verification Result</h5>
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
            <p><span class="label">ID Number</span><span class="value" id="v_pwd_id"></span></p>
            <p class="status-line">
              <span class="label">ID Status</span>
              <span class="status-inline">
                <span id="valid_status_badge" class="badge rounded-pill"></span>
                <span class="value" id="v_valid_until"></span>
              </span>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="notVerifiedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content result-modal">
        <div class="modal-header border-0">
          <h5 class="modal-title">Professional ID Verification Result</h5>
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
          <p class="modal-copy">Professional ID Number is not registered in the database. Please contact the Professional Affairs Division (Professional ID).</p>
          <div class="modal-contact text-start mx-auto">
            <p><i class="bi bi-telephone-fill text-primary me-2"></i><strong>Landline:</strong> Sample contact loc 811</p>
            <p><i class="bi bi-phone-fill text-success me-2"></i><strong>Mobile:</strong> Demo support</p>
            <p><i class="bi bi-envelope-fill text-danger me-2"></i><strong>Email:</strong> <a href="mailto:support@example.test">support@example.test</a></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="wrongBrgyCodeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content result-modal">
        <div class="modal-header border-0">
          <h5 class="modal-title">Professional ID Verification Result</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <div class="fail-icon">
            <svg class="draw-icon red" viewBox="0 0 52 52">
              <circle cx="26" cy="26" r="20"></circle>
              <path d="M16 16 L36 36 M36 16 L16 36"></path>
            </svg>
          </div>
          <div class="notverify-badge mb-4"><span>INFORMATION MISMATCH</span></div>
          <p class="modal-copy">The selected barangay does not match the registered Professional ID. Please contact Professional ID for record confirmation.</p>
          <div class="modal-contact text-start mx-auto">
            <p><i class="bi bi-telephone-fill text-primary me-2"></i><strong>Landline:</strong> Sample contact loc 811</p>
            <p><i class="bi bi-phone-fill text-success me-2"></i><strong>Mobile:</strong> Demo support</p>
            <p><i class="bi bi-envelope-fill text-danger me-2"></i><strong>Email:</strong> <a href="mailto:support@example.test">support@example.test</a></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
  <script src="<?= e(asset_url('assets/js/module-loader.js?v=verification-enabled-2')) ?>" data-logo="<?= e(asset_url('assets/images/professional-mark.svg')) ?>" data-delay="500"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const verifyForm = document.getElementById('verifyForm');
      const brgySelect = document.getElementById('brgySelect');
      const brgyCodeInput = document.getElementById('brgy_code');
      const uniqueIdInput = document.getElementById('unique_id');
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
        buttonLabel.textContent = isLoading ? 'Checking record...' : 'Verify Professional ID';
      }

      function setFeedback(message, type) {
        errorMessage.textContent = type === 'error' ? message : '';
        successMessage.textContent = type === 'success' ? message : '';
      }

      uniqueIdInput.addEventListener('input', () => {
        uniqueIdInput.value = uniqueIdInput.value.replace(/\D/g, '').slice(0, 7);
      });

      fetch('<?= e(endpoint_url('api/get-brgylist')) ?>')
        .then((response) => response.json())
        .then((data) => {
          data.forEach((row) => {
            const option = document.createElement('option');
            option.value = row.brgy_code;
            option.textContent = row.brgy_name;
            brgySelect.appendChild(option);
          });
        })
        .catch(() => setFeedback('Unable to load barangay options right now. Please refresh the page.', 'error'));

      brgySelect.addEventListener('change', () => {
        brgyCodeInput.value = brgySelect.value || '';
      });

      verifyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        setFeedback('', '');

        const brgyCode = brgyCodeInput.value.trim();
        const uniqueId = uniqueIdInput.value.trim();

        if (!brgyCode) {
          setFeedback('Please select the barangay registered with this Professional ID.', 'error');
          brgySelect.focus();
          return;
        }

        if (!/^\d{7}$/.test(uniqueId)) {
          setFeedback('Please enter exactly 7 digits for the Professional ID number.', 'error');
          uniqueIdInput.focus();
          return;
        }

        setLoadingState(true);

        try {
          const formData = new URLSearchParams();
          formData.append('brgy_code', brgyCode);
          formData.append('unique_id', uniqueId);

          const response = await fetch('<?= e(endpoint_url('api/verify-pdad-id')) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
          });
          const data = await response.json();

          if (data.status === 'success') {
            document.getElementById('v_firstname').textContent = data.firstname || '-';
            document.getElementById('v_middlename').textContent = data.middlename || '-';
            document.getElementById('v_lastname').textContent = data.lastname || '-';
            document.getElementById('v_pwd_id').textContent = data.pwd_id || '-';
            document.getElementById('v_valid_until').textContent = data.valid_until || 'N/A';

            const statusBadge = document.getElementById('valid_status_badge');
            const validUntilDate = data.valid_until ? new Date(data.valid_until + 'T23:59:59') : null;
            const isValid = validUntilDate && validUntilDate >= new Date();
            statusBadge.textContent = isValid ? 'VALID UNTIL' : 'EXPIRED';
            statusBadge.className = 'badge rounded-pill ' + (isValid ? 'bg-success' : 'bg-danger');

            bootstrap.Modal.getOrCreateInstance(document.getElementById('verifiedModal')).show();
            return;
          }

          if (data.status === 'mismatch_brgy_code') {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('wrongBrgyCodeModal')).show();
            return;
          }

          if (data.status === 'not_found') {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('notVerifiedModal')).show();
            return;
          }

          setFeedback(data.message || 'Unable to verify the provided Professional ID.', 'error');
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
        brgyCodeInput.value = '';
        setFeedback('', '');
      }
      function showSessionExpired() {
        clearSensitiveState();

        ['verifiedModal', 'notVerifiedModal', 'wrongBrgyCodeModal'].forEach((modalId) => {
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
