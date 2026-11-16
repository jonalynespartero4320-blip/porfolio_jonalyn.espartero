# IMS original interface browser demo

This export uses the restored `ims/` source. Original page markup, sidebar/footer JavaScript, jQuery DataTables, Select2, Bootstrap modals, SweetAlert prompts, form fields, and application JavaScript are retained. The previous replacement navigation, record dialogs, and toolbar are not loaded or included.

Open `index.html` through GitHub Pages or a local HTTP server. Login: **demo / Demo2026!**. This is a fictional demo login, not secure authentication.

`bridge.js` supplies localStorage-backed responses matching the original PHP endpoint shapes. `runtime.js` connects those responses to the original jQuery/fetch calls and maps navigation to static HTML pages. `masterlist-schema.js` contains field metadata from the original masterlist definitions, with no SQL or original records. The copied `assets/` directory supplies the original design. `original-demo.css` adds only the demo notice and the previously requested plain page background.

Supported browser workflows include equipment batch creation, complete hardware fields, View/Edit/status changes, archive/restore, supply records, assignments and unassignment, borrowed items, subscriptions, transmittals, masterlists, and basic request/service/maintenance records. Archive screens use their actual original templates. Original server-only utilities such as real imports, account administration, and production document generation are explicitly unavailable. Some export controls already marked pending in the source remain pending. This is not a production server replacement.

Only fictional sample masterlists and records are seeded. No original database export, passwords, employee records, or uploaded documents are copied into storage. New demo records use `je-demo-v2-inventory-original`; the prior simplified demo's storage is separate. Changes stay on the current device and origin. Reset clears this IMS demo only. Sample PDF attachments are capped at 1 MB each and total browser storage limits still apply.

For deployment, replace the entire `browser-demo/ims/` folder in the GitHub clone. Keep `browser-demo/shared/` alongside it. Commit and push. The updated clean archive is `deployment/github-pages-upload.zip` at the repository root; extract it before uploading its contents.

Rebuild with `C:\xampp\php\php.exe tools/export-bjmp-demo.php --ims`. This reads original views and public assets without connecting to a database. It does not edit the original application. Temporary compilation output is ignored by Git.

Validation: original form and sidebar handlers preserved across 25 pages; 97 inline scripts compile; adapter tests cover original response shapes, full equipment fields, atomic batch writes, assignments, archive/restore, masterlists, reload persistence, and failed-save rollback. A temporary Edge browser verified login, original sidebar visibility, original DataTable loading, batch submission and row refresh, View modal, assignment submission, borrowed-item submission, module initialization, and mobile sidebar open/close, with no JavaScript runtime exceptions in the tested flows.
