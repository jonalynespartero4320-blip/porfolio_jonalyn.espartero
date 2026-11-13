<footer class="site-footer text-white">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-4 text-start">
          <img src="<?= e(asset_url('assets/images/professional-mark.svg')) ?>" alt="City Logo" class="footer-logo">
          <h4 class="fw-bold">Demo Services</h4>
          <p><i class="fa fa-map-marker-alt me-2 footer-icon"></i>Sample Service Center · Fictional location</p>
          <p><i class="fa fa-phone me-2 footer-icon"></i>Sample contact</p>
          <p><i class="fa fa-clock me-2 footer-icon"></i>Open Hours: Mon - Fri: 7:00 am - 5:00 pm</p>
          <p><i class="fa fa-envelope me-2 footer-icon"></i><a href="mailto:support@example.test">support@example.test</a></p>
        </div>

        <div class="col-md-4">
          <h5 class="fw-bold">Hotlines</h5>
          <p><span class="highlight-number">Sample contact</span><br>Police</p>
          <p><span class="highlight-number">Sample contact / Sample contact</span><br>Fire</p>
          <p><span class="highlight-number">Sample contact</span><br>Hospital</p>
          <p><span class="highlight-number">Sample contact</span><br>Disaster / Rescue</p>
        </div>

        <div class="col-md-4">
          <h5 class="fw-bold">City Map</h5>
          <div class="ratio ratio-4x3 rounded overflow-hidden shadow-sm">
            <img src="<?= e(asset_url('map.png')) ?>" alt="Illustrated city location map" style="width:100%;height:100%;object-fit:contain">
          </div>
        </div>
      </div>
      <div class="footer-bottom">
        <p>Privacy notice: Data, names, and icons in this demo have been changed for privacy purposes. Sample records are fictional and are used only to demonstrate system functionality.</p>
        <div>© 2026 Demo Services</div>
        <small>Developed by Demo Development Team</small>
      </div>
    </div>
  </footer>
