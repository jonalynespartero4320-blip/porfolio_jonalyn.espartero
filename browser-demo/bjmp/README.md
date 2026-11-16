# BJMP browser demo using the original interface

Open `index.html` through a static server or GitHub Pages. Demo credentials: `demo` / `Demo2026!`. This is a fictional login flow, not authentication.

The HTML is exported from the original BJMP view templates and uses copies of the existing CSS and neutral demo assets. No original PHP views, database credentials, SQL exports, photos, or uploaded documents are changed or published by the exporter.

Converted browser workflows:

- Original login screen, sidebar, and dashboard.
- Resident form and resident listing, including authorized visitor entries.
- Original visitor registration tabs, editable details, small fictional photo/document uploads, and verification.
- Visitor management, local bans, demo ID display, and queue submission.
- Queue admission and time-out with a ten-person capacity, monitor, and visitor board.
- Basic browser reports, CSV export, printing, and local activity logs.

The server administration forms, role permissions, imports, camera/QR scanning, real ID verification, and production ID printing are not converted. Their original screens remain visible; unconverted server form submissions show a notice. This is not feature parity with the PHP application. Reports currently apply date-range filtering; other original advanced report controls are not implemented.

Use fictional records only. Data is stored under `je-bjmp-original-v1` on the current browser/site origin. Files must be PNG, JPG, or PDF and under 350 KB each. Storage quota failures keep the previously saved data. Reset clears only this demo. Tabs on the same origin share updates; different devices do not.

To publish, copy the whole `browser-demo/bjmp` directory plus the updated root `script.js` into the cloned repository, commit, and push. No build or PHP runtime is needed on GitHub Pages. Existing Bootstrap/font/icon CDN assets need internet access. Other portfolio applications still use their original PHP URLs.

Development only: `C:\xampp\php\php.exe tools/export-bjmp-demo.php` regenerates HTML/CSS assets from the original views without connecting to a database. It creates the ignored `.static-export-bjmp/` workspace. `local.js` and `local.css` are maintained separately. Never upload the temporary workspace.

Validation: PHP export succeeds for all 19 screens. JavaScript syntax and adapter storage tests pass. Visual browser QA could not run in this environment; the included browser test reports a debugging connection failure.
