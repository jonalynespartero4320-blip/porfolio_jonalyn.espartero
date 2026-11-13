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
  <title>Food ID Verification</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>" />
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
        <span class="logo module-brand-icon" aria-hidden="true"><i class="fa-solid fa-basket-shopping"></i></span>
        <div class="title-container">
          <p class="eyebrow">Demo Services</p>
          <h1 class="title-pwd-manda">Food ID Verification</h1>
          <h1 class="title-rtc-manda-title2">Demo Food ID Card</h1>
          <p class="header-subtitle">Food ID Services verification portal for empowering partners and LGBTQIA+ families</p>
        </div>
        <span class="logo module-brand-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
      </div>

      <nav class="menu-slider" aria-label="Verification systems">
        <a href="<?= e(asset_url('')) ?>" class="menu-items">Dashboard</a>
        <div class="divider">|</div>
        <a href="<?= e(asset_url('public/gad_verifyid/')) ?>" class="menu-items active-link">Food ID Verification</a>
        <div class="divider">|</div>
        <a href="<?= e(asset_url('public/pdad_verifyid/')) ?>" class="menu-items">Professional ID Verification</a>
        <div class="divider">|</div>
        <a href="<?= e(asset_url('public/osca_verifyid/')) ?>" class="menu-items">Home ID Verification</a>
      </nav>
    </header>

    <section class="hero-section  ">
      <div class="container">
        <div class="hero-grid">
          <article class="hero-panel hero-story">
            <div class="story-badge">Food ID Food ID</div>
            <h2>Verify a Demo Food ID with dignity and care.</h2>
            <p class="hero-copy">
              Enter the RTC code or scan the QR code, then review a privacy-safe confirmation for common-law partners and LGBTQIA+ residents.
            </p>

            <div class="feature-list">
              <div class="feature-card">
                <span class="feature-icon"><i class="bi bi-shield-check"></i></span>
                <div>
                  <h3>Secure verification</h3>
                  <p>Personal details are protected and only limited masked information is shown.</p>
                </div>
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
              <img src="<?= e(asset_url('food.png')) ?>" alt="food ID sample card" class="hero-image" />
            </div>
          </article>

          <aside class="hero-panel verification-panel">
            <div class="panel-header">
              <p class="panel-kicker text-black">Food ID Verification</p>  
              <h2>Demo City Food ID </br>ID Verification</h2>
              <p class="panel-copy">Enter the RTC code or scan the QR code.</p>
            </div>


            <form id="verifyForm" class="verification-form" autocomplete="off" novalidate>
              <div class="field-block">
                <label for="unique_id" class="form-label">Food ID Number</label>
                <div class="input-group id-input-group">
                  <span class="input-group-text icon-box"><i class="fa fa-hashtag"></i></span>
                  <input
                    type="text"
                    class="form-control id-entry"
                    id="unique_id"
                    name="unique_id"
                    inputmode="text"
                    maxlength="13"
                    placeholder="FOOD-ID-XXXXX"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    aria-describedby="pwdHelp"
                    required
                  />
                </div>
                <small id="pwdHelp" class="field-help">Enter the last 5 digits of your card number. The FOOD-ID- prefix is added automatically.</small>
              </div>

              <div class="hospital-scan-panel" aria-live="polite">
                <button
                  type="button"
                  id="hospitalScanToggle"
                  class="btn btn-gad-outline w-100 d-inline-flex align-items-center justify-content-center gap-2"
                  data-bs-toggle="modal"
                  data-bs-target="#hospitalScanModal"
                >
                  <i class="bi bi-hospital"></i>
                  <span>For Service desk use only, click here</span>
                </button>
              </div>

              <p id="error-message" class="form-feedback" role="alert" aria-live="polite"></p>
              <p id="success-message" class="form-success" aria-live="polite"></p>

              <button type="submit" id="checkVerification" class="action-button">
                <span class="btn-label">Verify Food ID Number</span>
                <span class="btn-loader spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
              </button>
            </form>

            <!-- <div class="trust-strip">
              <div><strong>Privacy</strong><span>Masked result details</span></div>
              <div><strong>Status</strong><span>Validity check included</span></div>
              <div><strong>Support</strong><span>Food ID assistance details below</span></div>
            </div> -->
          </aside>
        </div>
      </div>
    </section>
  </main>

  <?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>

  <div class="modal fade" id="hospitalScanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content result-modal hospital-access-modal">
        <div class="modal-header border-0">
          <h5 class="modal-title">Service desk Scanner Access</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div>
            <label for="hospital_scan_pass" class="form-label">Assigned Password</label>
            <input
              type="password"
              class="form-control"
              id="hospital_scan_pass"
              name="hospital_scan_pass"
              placeholder="Enter password"
              autocomplete="off"
            />
          </div>
          <div class="d-grid gap-2 d-sm-flex mt-3">
            <button type="button" id="unlockScanButton" class="btn btn-gad-primary flex-fill d-inline-flex align-items-center justify-content-center gap-2">
              <i class="bi bi-unlock"></i>
              <span>Unlock QR Scanner</span>
            </button>
            <button type="button" id="scanQrButton" class="btn btn-gad-outline flex-fill d-inline-flex align-items-center justify-content-center gap-2" aria-label="Scan QR code" disabled>
              <i class="bi bi-qr-code-scan"></i>
              <span>Scan QR</span>
            </button>
          </div>
          <p id="hospitalScanFeedback" class="form-text fw-semibold mt-2 mb-0"></p>
          <div id="qrScanner" class="qr-scanner d-none mt-3" aria-live="polite">
            <video id="qrVideo" class="qr-video" playsinline muted></video>
            <div class="qr-scanner-actions">
              <span id="qrStatus">Point the camera at the card QR code.</span>
              <button type="button" id="stopQrButton" class="qr-stop-button">Close scanner</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="verifiedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content result-modal">
        <div class="modal-header border-0">
          <h5 class="modal-title">Food ID Verification Result</h5>
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
            <p><span class="label">ID No.</span><span class="value" id="v_rtc_code"></span></p>
            <p class="status-line">
              <span class="label">Status</span>
              <span class="status-inline">
                <span id="valid_status_badge" class="badge rounded-pill"></span>
              </span>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="hospitalVerifiedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content result-modal">
        <div class="modal-header border-0">
          <h5 class="modal-title">Service desk QR Verification Result</h5>
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
            <p><span class="label">ID No.</span><span class="value" id="h_rtc_code"></span></p>
            <p><span class="label">Principal</span><span class="value" id="h_principal_fullname"></span></p>
            <p><span class="label">Food ID</span><span class="value" id="h_hcp_fullname"></span></p>
            <p class="status-line">
              <span class="label">Status</span>
              <span class="status-inline">
                <span id="hospital_valid_status_badge" class="badge rounded-pill"></span>
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
          <h5 class="modal-title">Food ID Verification Result</h5>
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
          <p class="modal-copy">Food ID number is not registered in the database. Please contact the Food ID Services office for assistance.</p>
          <div class="modal-contact text-start mx-auto">
            <p><i class="bi bi-telephone-fill text-primary me-2"></i><strong>Landline:</strong> Sample contact Local 817</p>
            <p><i class="bi bi-phone-fill text-success me-2"></i><strong>Mobile:</strong> Demo support</p>
            <p><i class="bi bi-pin-map-fill text-success me-2"></i><strong>Food ID Office:</strong> Sample Service Center
Sample District
Demo District ? Fictional address</p>


            <!-- <p><i class="bi bi-envelope-fill text-danger me-2"></i><strong>Email:</strong> <a href="mailto:support@example.test">support@example.test</a></p> -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
  <script src="<?= e(asset_url('assets/js/module-loader.js?v=verification-enabled-2')) ?>" data-logo="<?= e(asset_url('assets/images/food-mark.svg')) ?>" data-delay="500"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const verifyForm = document.getElementById('verifyForm');
      const uniqueIdInput = document.getElementById('unique_id');
      const errorMessage = document.getElementById('error-message');
      const successMessage = document.getElementById('success-message');
      const submitButton = document.getElementById('checkVerification');
      const buttonLabel = submitButton.querySelector('.btn-label');
      const buttonLoader = submitButton.querySelector('.btn-loader');
      const hospitalScanModal = document.getElementById('hospitalScanModal');
      const hospitalScanPassInput = document.getElementById('hospital_scan_pass');
      const unlockScanButton = document.getElementById('unlockScanButton');
      const hospitalScanFeedback = document.getElementById('hospitalScanFeedback');
      const scanQrButton = document.getElementById('scanQrButton');
      const qrScanner = document.getElementById('qrScanner');
      const qrVideo = document.getElementById('qrVideo');
      const qrStatus = document.getElementById('qrStatus');
      const stopQrButton = document.getElementById('stopQrButton');
      const hospitalId = '01';
      let hospitalScanUnlocked = false;
      let qrStream = null;
      let qrScanTimer = null;
      let qrDisplayExpiryTimer = null;
      const qrDisplayExpiryMs = 5 * 60 * 1000;
      const qrCanvas = document.createElement('canvas');
      const qrCanvasContext = qrCanvas.getContext('2d', { willReadFrequently: true });
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
        buttonLabel.textContent = isLoading ? 'Checking record...' : 'Verify Food ID';
      }

      function setFeedback(message, type) {
        errorMessage.textContent = type === 'error' ? message : '';
        successMessage.textContent = type === 'success' ? message : '';
      }

      function setService deskScanFeedback(message, type) {
        hospitalScanFeedback.textContent = message;
        hospitalScanFeedback.className = 'form-text fw-semibold mt-2 mb-0';
        if (type === 'error') {
          hospitalScanFeedback.classList.add('text-danger');
        }
        if (type === 'success') {
          hospitalScanFeedback.classList.add('text-success');
        }
      }

      function setService deskScanUnlocked(isUnlocked) {
        hospitalScanUnlocked = isUnlocked;
        scanQrButton.disabled = !isUnlocked;
        scanQrButton.classList.toggle('is-unlocked', isUnlocked);
        unlockScanButton.querySelector('span').textContent = isUnlocked ? 'Scanner Unlocked' : 'Unlock QR Scanner';
      }

      function formatCardNumber(value) {
        const prefix = 'FOOD-ID-';
        const digits = value.replace(/^FOOD-ID-/i, '').replace(/\D/g, '').slice(0, 5);

        return digits ? prefix + digits : '';
      }

      function getRtcCode() {
        return uniqueIdInput.value.trim().toUpperCase();
      }

      function extractRtcCode(value) {
        const rtcMatch = value.match(/FOOD-ID-\d{5}/i);

        if (rtcMatch) {
          return rtcMatch[0].toUpperCase();
        }

        const digits = value.replace(/\D/g, '').slice(-5);
        return digits.length === 5 ? 'FOOD-ID-' + digits : '';
      }

      function fillVerifiedDetails(data) {
        document.getElementById('v_rtc_code').textContent = data.rtc_code || '-';
        const statusBadge = document.getElementById('valid_status_badge');
        statusBadge.textContent = data.verification_status || 'VERIFIED';
        statusBadge.className = 'badge rounded-pill bg-success';
      }

      function fillService deskVerifiedDetails(data) {
        document.getElementById('h_rtc_code').textContent = data.rtc_code || '-';
        document.getElementById('h_principal_fullname').textContent = data.principal_fullname || '-';
        document.getElementById('h_hcp_fullname').textContent = data.hcp_fullname || '-';
        const statusBadge = document.getElementById('hospital_valid_status_badge');
        statusBadge.textContent = data.verification_status || 'VERIFIED';
        statusBadge.className = 'badge rounded-pill bg-success';
      }

      async function verifyRtcCode(rtcCode, options = {}) {
        setLoadingState(true);

        try {
          const formData = new URLSearchParams();
          formData.append('unique_id', rtcCode);

          const response = await fetch('<?= e(endpoint_url('api/verify-food')) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
          });
          const data = await response.json();

          if (data.status === 'success') {
            if (options.fromQr) {
              fillService deskVerifiedDetails(data);
              bootstrap.Modal.getOrCreateInstance(document.getElementById('hospitalVerifiedModal')).show();
            } else {
              fillVerifiedDetails(data);
              bootstrap.Modal.getOrCreateInstance(document.getElementById('verifiedModal')).show();
            }
            return;
          }

          if (data.status === 'not_found') {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('notVerifiedModal')).show();
            return;
          }

          setFeedback(data.message || 'Unable to verify the provided Food ID.', 'error');
        } catch (error) {
          console.error('Verification error:', error);
          setFeedback('Something went wrong while verifying. Please try again in a moment.', 'error');
        } finally {
          setLoadingState(false);
          if (options.fromQr) {
            setService deskScanFeedback('', '');
          }
        }
      }

      function stopQrScanner() {
        if (qrDisplayExpiryTimer) {
          clearTimeout(qrDisplayExpiryTimer);
          qrDisplayExpiryTimer = null;
        }
        if (qrScanTimer) {
          clearInterval(qrScanTimer);
          qrScanTimer = null;
        }
        if (qrStream) {
          qrStream.getTracks().forEach((track) => track.stop());
          qrStream = null;
        }
        qrVideo.srcObject = null;
        qrScanner.classList.add('d-none');
      }

      async function startQrScanner() {
        setFeedback('', '');

        if (!hospitalScanUnlocked) {
          setService deskScanFeedback('Please enter the assigned service desk password before scanning.', 'error');
          hospitalScanPassInput.focus();
          return;
        }

        const supportsNativeDetector = 'BarcodeDetector' in window;
        const supportsJsQr = typeof window.jsQR === 'function' && qrCanvasContext;

        if (!supportsNativeDetector && !supportsJsQr) {
          setFeedback('QR scanning is not available right now. Please type the card number instead.', 'error');
          return;
        }

        try {
          qrScanner.classList.remove('d-none');
          qrStatus.textContent = 'Opening camera...';
          const detector = supportsNativeDetector ? new BarcodeDetector({ formats: ['qr_code'] }) : null;
          qrStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
          qrVideo.srcObject = qrStream;
          await qrVideo.play();
          qrStatus.textContent = 'Point the camera at the card QR code.';

          // Keep the camera/QR display available for at most five minutes.
          clearTimeout(timeoutHandle);
          qrDisplayExpiryTimer = setTimeout(() => {
            stopQrScanner();
            setService deskScanUnlocked(false);
            setService deskScanFeedback('QR scanner expired after 5 minutes. Unlock it again if needed.', 'error');
            resetSessionTimer();
          }, qrDisplayExpiryMs);

          const readQrValue = async () => {
            if (detector) {
              const codes = await detector.detect(qrVideo);
              return codes.length ? (codes[0].rawValue || '') : '';
            }

            if (!qrVideo.videoWidth || !qrVideo.videoHeight) {
              return '';
            }

            qrCanvas.width = qrVideo.videoWidth;
            qrCanvas.height = qrVideo.videoHeight;
            qrCanvasContext.drawImage(qrVideo, 0, 0, qrCanvas.width, qrCanvas.height);
            const imageData = qrCanvasContext.getImageData(0, 0, qrCanvas.width, qrCanvas.height);
            const code = window.jsQR(imageData.data, imageData.width, imageData.height);
            return code ? code.data : '';
          };

          qrScanTimer = setInterval(async () => {
            try {
              const rawValue = await readQrValue();
              if (!qrStream || !rawValue) return;

              const code = extractRtcCode(rawValue);
              if (!code) {
                qrStatus.textContent = 'QR found, but no valid RTC-CN card code was detected.';
                return;
              }

              uniqueIdInput.value = code;
              stopQrScanner();
              resetSessionTimer();
              bootstrap.Modal.getInstance(hospitalScanModal)?.hide();
              setFeedback('QR code scanned. Verifying record...', 'success');
              await verifyRtcCode(code, { fromQr: true });
            } catch (error) {
              console.error('QR scan error:', error);
            }
          }, 700);
        } catch (error) {
          console.error('Camera error:', error);
          stopQrScanner();
          resetSessionTimer();
          setFeedback('Unable to open the camera. Please allow camera access or type the card number.', 'error');
        }
      }

      async function unlockService deskScanner() {
        const hospitalPassword = hospitalScanPassInput.value.trim();

        stopQrScanner();
        setService deskScanUnlocked(false);
        setService deskScanFeedback('', '');

        if (hospitalPassword === '') {
          setService deskScanFeedback('Please enter the assigned service desk password.', 'error');
          hospitalScanPassInput.focus();
          return;
        }

        unlockScanButton.disabled = true;

        try {
          const formData = new URLSearchParams();
          formData.append('hospital_password', hospitalPassword);

          const response = await fetch('<?= e(endpoint_url('api/verify-hospital-scan-access')) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
          });
          const data = await response.json();

          if (response.ok && data.status === 'success') {
            setService deskScanUnlocked(true);
            setService deskScanFeedback('Scanner access approved for ' + (data.hospital || 'this hospital') + '.', 'success');
            return;
          }

          setService deskScanFeedback(data.message || 'Service desk scanner access was not approved.', 'error');
          hospitalScanPassInput.focus();
        } catch (error) {
          console.error('Service desk scanner access error:', error);
          setService deskScanFeedback('Unable to validate hospital access right now.', 'error');
        } finally {
          unlockScanButton.disabled = false;
        }
      }

      uniqueIdInput.addEventListener('input', () => {
        uniqueIdInput.value = formatCardNumber(uniqueIdInput.value);
      });

      hospitalScanPassInput.addEventListener('input', () => setService deskScanUnlocked(false));
      hospitalScanModal.addEventListener('shown.bs.modal', () => {
        hospitalScanPassInput.focus();
      });
      hospitalScanModal.addEventListener('hidden.bs.modal', () => {
        stopQrScanner();
        setService deskScanUnlocked(false);
        setService deskScanFeedback('', '');
      });
      unlockScanButton.addEventListener('click', unlockService deskScanner);
      scanQrButton.addEventListener('click', startQrScanner);
      stopQrButton.addEventListener('click', () => {
        stopQrScanner();
        bootstrap.Modal.getInstance(hospitalScanModal)?.hide();
        resetSessionTimer();
      });

      verifyForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        setFeedback('', '');

        const rtcCode = getRtcCode();

        if (!/^FOOD-ID-\d{5}$/.test(rtcCode)) {
          setFeedback('Please enter the Food ID number in FOOD-ID-00001 format.', 'error');
          uniqueIdInput.focus();
          return;
        }

        await verifyRtcCode(rtcCode);
      });

      let timeoutHandle;
      const timeoutMs = 3 * 60 * 1000;
      function clearSensitiveState() {
        verifyForm.reset();
        hospitalScanPassInput.value = '';
        stopQrScanner();
        setService deskScanUnlocked(false);
        setService deskScanFeedback('', '');
        setFeedback('', '');
      }
      function showSessionExpired() {
        clearSensitiveState();

        ['hospitalScanModal', 'verifiedModal', 'hospitalVerifiedModal', 'notVerifiedModal'].forEach((modalId) => {
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
        if (qrStream) {
          return;
        }
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
