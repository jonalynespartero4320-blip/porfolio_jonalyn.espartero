# GitHub Pages project demos

All four portfolio projects now have separate HTML/CSS/JavaScript demos. Their original PHP applications are unchanged. Existing screen templates and CSS are reused, with localStorage replacing the demo data operations. Page backgrounds are plain light gray.

| Project | Start page | Supported demo workflows |
| --- | --- | --- |
| ID & Queuing | `bjmp/index.html` | Resident and visitor records, registration, fictional IDs, queue admission/time-out, basic reports |
| Inventory | `ims/index.html` | Equipment, supplies, assignments, borrowed items, subscriptions, transmittals, equipment/service requests, edit/archive/delete, CSV export, summary reports |
| Appointments | `health/customer.html` | Customer details, weekday calendar, AM/PM capacity, reference confirmation, cancellation, staff login, attendance/status updates, browser admin accounts |
| Verification | `verification/index.html` | Original service selection and ID screens, fictional matching/missing/barangay mismatch results |

Demo login for BJMP, Inventory, and Health staff: **demo / Demo2026!**. Health public booking does not need login. Professional ID: `0000001` with `Demo Barangay`; Home ID: `000001`; Food ID: `00001`. These are fictional records, not real identity checks.

## Upload

Copy the entire `browser-demo/` folder plus the updated root `index.html` and `script.js` into the cloned GitHub repository. Keep the existing root portfolio CSS, images, and resume. Commit and push in GitHub Desktop, wait for Pages deployment, and refresh the portfolio. Each project URL now starts with `browser-demo/`.

Alternatively, extract `deployment/github-pages-upload.zip` and copy its contents into the cloned repository root. Do not upload only the ZIP: GitHub Pages needs the extracted HTML/CSS/JS files. The archive contains the public portfolio assets and static demos, excluding PHP/configuration files, SQL exports, private uploads, and temporary export workspaces.

## Storage and scope

Data is saved per browser and site origin. Different devices and visitors do not share records. Reset affects only the selected demo. Health has 250 AM and 250 PM slots per weekday and supports bookings up to 90 days ahead. Browser admins and login flows provide no real security. Small fictional BJMP attachments are limited to 350 KB each. Use fictional information only.

These are portfolio demos, not complete replacements for the original server applications. Secure authentication, real identity verification, multi-user concurrency, email, original database/file imports, camera scanning, production ID printing, and advanced report customization are not reproduced. Inventory now retains the restored original JavaScript, sidebar, tables, modals, and form fields. A response adapter replaces PHP calls with localStorage; there is no replacement toolbar or generic record form. Unconverted server-only utilities show a notice. External Bootstrap/font/icon assets still need internet access.

## Development

Run these from the repository root:

```powershell
C:\xampp\php\php.exe tools/export-bjmp-demo.php
C:\xampp\php\php.exe tools/export-bjmp-demo.php --ims
C:\xampp\php\php.exe tools/export-health-demo.php
C:\xampp\php\php.exe tools/export-verification-demo.php
C:\xampp\php\php.exe tools/check-demo-pages.php
```

Run `tools/check-all-demo-storage.cjs` and `tools/check-bjmp-storage.cjs` with Node.js for data behavior checks. Temporary compilation folders are ignored by Git. The exports use an empty fake data provider and do not connect to or change any original database. BJMP/Health/Verification local scripts and `shared/` are maintained separately from generated screens. IMS uses `bridge.js`, `runtime.js`, and `original-demo.css`; see `ims/README.md`.

Validation passed: JavaScript syntax, original form controls, all 56 static pages' local links, project destinations, booking/cancellation rules, persistence and quota failure handling, lookup results, and BJMP queue behavior. A temporary Edge browser verified the restored IMS login, original sidebar, original Equipment batch save and View modal, assignment and borrowed-item submission, module initialization, and mobile sidebar behavior without runtime exceptions. See `ims/README.md`. Earlier visual verification limits still apply to the other demos.
