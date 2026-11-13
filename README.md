# Jonalyn Espartero portfolio

The portfolio launches the original PHP projects in `ims`, `bjmp_vaqs`, and `verification_system` through `demo/`. This version runs locally with XAMPP Apache and MySQL.

The **Health Appointment System (Wellnest)** card launches `Health/public/customer` directly. Open http://localhost/porfolio_jonalyn.espartero-main/Health/public/customer for the public booking flow; no customer login is required. Health uses its own database configuration and is not initialized or reset by `demo/setup.php`. See `Health/README.md` for its database setup. Appointments submitted through this link are saved in the Health database.

1. Start Apache and MySQL in XAMPP.
2. From this directory, run `C:\xampp\php\php.exe demo/setup.php`.
3. Open http://localhost/porfolio_jonalyn.espartero-main/ and choose a project.

IMS and BJMP login: username **demo**, password **Demo2026!**. Each application has its own login session.

Verification needs no login. For Professional ID, choose **Demo Barangay** and enter **0000001**. For Home IDs, enter **000001**. Enter **999999** for a missing senior ID, or use **Sample Barangay** with **0000001** for a barangay mismatch. For Food ID, enter **00001**.

Setup imports table definitions only and inserts fictional records, never the source SQL exports' records. It creates only `portfolio_demo_ims`, `portfolio_demo_bjmp`, `portfolio_demo_pdad`, `portfolio_demo_osca`, and `portfolio_demo_rtc`. Demo writes are shared across visitors and persist until reset; these are disposable MySQL databases, not expiring in-memory storage.

Re-running setup preserves changes. To erase only these five demo databases and recreate the samples, run `C:\xampp\php\php.exe demo/setup.php --reset`. Setup and seed scripts run only from the command line. The database host, user, and password can be set through `DEMO_DB_HOST`, `DEMO_DB_USER`, and `DEMO_DB_PASSWORD`; defaults match local XAMPP (`127.0.0.1`, `root`, empty password).

The apps require PHP/MySQL hosting; static hosting alone cannot run these demos. Existing application dependencies loaded from CDNs require an internet connection. This change has been checked through local HTTP requests; browser layout and camera scanning have not been verified.

Public demo branding uses neutral ID & Queuing System, Inventory Management System, and Verification Portal labels. Professional ID, Home ID, and Food ID use fictional records. Original SQL, company documents, legacy uploads, and agency image files are denied over HTTP. New demo uploads use separate demo-uploads directories. Original source artifacts remain on disk and must be excluded from any public source distribution.
