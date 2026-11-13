# Wellnest — PHP health appointment system

## Current local database

The running WAMP website now uses **MySQL 8.4.7** at **127.0.0.1:3308**, database **health_appointments**, configured in the private `config/database.php`. The four existing customer bookings, super admin account, doctors, counters, and login-attempt records were copied from SQLite and verified field by field. The application connects with a dedicated account restricted to SELECT, INSERT, UPDATE, and DELETE.

To view saved records, open **http://localhost/phpmyadmin/**, choose the **MySQL server on port 3308**, select **health_appointments**, then open **customer_bookings → Browse**. Staff accounts are in `users`; appointment references, company/job details, schedules, attendance, and cancellation reasons are in `customer_bookings`.

The original `storage/health.sqlite` and a dated `storage/health-before-mysql-*.sqlite` backup remain available. After the switch, new records go only to MySQL; the old SQLite file is no longer the current database. Removing `config/database.php` would reopen that older SQLite data, so do not remove it as a routine troubleshooting step.

`scripts/migrate-to-mysql.php` performed the local transfer. It refuses to overwrite an existing target database or connection config. `tests/mysql-capacity.php` verified schema import, references, idempotency, 250/session capacity, eight competing requests for the last slot, cancellations, and doctor-slot uniqueness in a separate temporary MySQL database, which was deleted afterward. Existing SQLite-only HTTP suites intentionally refuse to run against the now-configured MySQL app. Use `php -d extension=pdo_mysql scripts/check-database.php` to check the active connection.

**WAMP MySQL/phpMyAdmin:** Import [database/health.sql](database/health.sql), copy `config/database.example.php` to `config/database.php`, and set your MySQL connection values. Enable `pdo_mysql`. Follow [database/README.md](database/README.md) for complete instructions. The import includes the requested super admin account as a password hash. MySQL is optional; without this config, the existing SQLite setup continues unchanged. The SQL import does not migrate existing SQLite appointments or accounts.

A dependency-free PHP 8.2+ project with an open customer appointment form and a separate administrator portal. Customers never create accounts or sign in. Public registration and the old account-based customer routes are disabled; `/login` accepts only admin and super admin accounts. SQLite stores accounts and bookings outside the public directory. Passwords are stored as hashes; credentials are not embedded in application source. Existing legacy records remain available to staff.

## Super admin and staff access

Sign in at **http://localhost/Health/public/login** using a username and password. No email address is collected or used anywhere in the customer or staff flows. The requested local super admin account **cictd-administrator** has been created in `storage/health.sqlite` with the supplied password. Existing IDs, passwords, and bookings are preserved when removing the old email field. SQLite migrates automatically; existing MySQL installations should import `database/remove-email.sql` once. Fresh installations use the updated `database/health.sql`.

Super admins can open **Manage admins** (`/Health/public/manage-admins`) to create an admin with a name, username, and password. Only super admins have access to this page or its POST action. Created accounts always receive the `admin` role, regardless of submitted role fields. Admins and super admins land on **All appointments** after sign-in and can see all customer schedules and all legacy patient appointments. Legacy accounts with no username receive `legacy-ID`, such as `legacy-12`.

Use **Mark arrived** when the person comes in. The record stores the arrival time in Singapore time and the staff member who recorded it; repeated clicks preserve the original entry. Future, cancelled, and completed appointments cannot be checked in. Arrival is recorded separately from booking status and does not release capacity. The dashboard shows the attendance timestamp and staff name.

`scripts/create-super-admin.php` is a CLI-only provisioning tool accepting JSON with `username` and `password` on stdin. It hashes the password and refuses to overwrite existing accounts. It does not run automatically on web requests. `scripts/create-admin.php` remains available for trusted local CLI administration. To check role restrictions and attendance against the local server, run `php -d extension=pdo_sqlite tests/staff-http.php`.

## Customer scheduling

### Future reservations and customer cancellation

Confirmed customer bookings reserve one slot for the exact chosen date and AM/PM session, including dates several weeks ahead. Reservations are stored in the database and remain after leaving the browser or after the three-minute form reset. New bookings are available **Monday–Friday only** within the existing 90-day window. Saturdays and Sundays are rejected by the server and blocked from session selection in the browser. Existing bookings remain saved. Selecting a date alone does not hold capacity; clicking **Confirm appointment** reserves the slot.

Customers who cannot attend can open **http://localhost/Health/public/cancel-appointment** (also linked in navigation and the receipt). Enter the reference number, full name as booked, and a reason of 10–500 characters, then confirm cancellation. Full name verification provides an additional check because reference numbers are predictable. No account or email is required. Failed attempts are rate-limited to reduce reference guessing.

A successful cancellation immediately releases exactly one slot for the original date and session. Live availability sees the change on its next five-second refresh. Repeating the cancellation does not release another slot. Past/ended sessions, checked-in bookings, and completed bookings cannot be cancelled publicly. The original reference, reason, cancellation time, and source are kept for staff review. The cancellation form also uses CSRF protection, a booking token, and the three-minute inactivity reset.

Existing MySQL/MariaDB installations must import `database/customer-cancellations.sql` once before using cancellation. Fresh setups import the updated `database/health.sql`. SQLite migrates automatically. `php -d extension=pdo_sqlite tests/cancellation-http.php` verifies reservations on a distant weekday, exact date/session capacity, cancellation validation, duplicate submissions, audit data, escaping, and guessing limits against the local SQLite server.

### Three-minute appointment form timeout

Customer details, schedule selection, confirmation, and the specialist booking form clear after **180 seconds without mouse, keyboard, touch, or form activity**. The page shows a countdown. Reset clears the unfinished customer draft, selected schedule, browser-visible details, and receipt access; it does **not** delete confirmed bookings or sign out a staff account. It returns to a blank customer form with a new booking token.

The browser sends activity updates only after user interactions. Live slot polling does not count as activity. PHP independently expires the booking state on the next request after 180 seconds, and stale form tokens are rejected. Background/suspended tabs are checked when they resume. With JavaScript disabled, the server still enforces expiry based on booking page requests/submissions, but cannot observe typing or automatically clear the visible page.

Security hardening includes prepared SQL queries, escaped HTML output, CSRF tokens and separate booking tokens, strict role checks, request field/size validation, hashed passwords, login throttling, strict cookie-only sessions with an app-specific cookie, HttpOnly/SameSite cookies, Secure cookies and HSTS over HTTPS, restrictive browser headers, suppressed public PHP error details, and private data outside `public`. Serve only `public` and enable HTTPS for deployment. These controls reduce common risks; passing automated tests is not a guarantee that no vulnerabilities exist.

Run `php -d extension=pdo_sqlite tests/booking-timeout.php` for exact timeout boundaries and `php -d extension=pdo_sqlite tests/security-http.php` against the local server for stale-form replay, CSRF, SQL/script payloads, malformed input, private files, and real server-side expiry with a CLI-aged test session. No public endpoint can change the server clock or session age. The timeout follows [OWASP's server-side expiry guidance](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html); SQL uses [PDO prepared statements](https://www.php.net/manual/en/pdo.prepare.php).

Open **http://localhost/Health/public/customer**. The project root now redirects guests to this booking form. No sign-in is required.

1. Enter full name, gender, company name, and job applying for. Click **Next**.
2. Select a date in the calendar, then choose **AM** (8 AM–12 PM) or **PM** (1 PM–5 PM), in Singapore time. Booking is available Monday to Friday through the next 90 days; sessions close at their end time.
3. Confirm and save or print the reference number. New references use **queue-MMDDYY-time**, such as **001-010126-A** for January 1, 2026, AM, or **001-010126-P** for PM. Each appointment date and AM/PM time has its own counter starting at **001**; for January 2 the first references are **001-010226-A** and **001-010226-P**. The date is the scheduled appointment date. Names and initials are not part of the reference. Numbers are not reused after cancellation on the same date/time. Existing references remain unchanged and still support cancellation. Only Monday–Friday dates within 90 days can be booked.

Each date has **250 AM slots and 250 PM slots**, shared across all customer bookings, companies, and job positions. The remaining slot counts update immediately on date selection and poll every **5 seconds** while the page is visible. The server rechecks capacity under a SQLite write lock at confirmation, preventing concurrent customers from overbooking. Retrying the same submission returns the original reservation. These customer session limits are separate from the existing individual doctor appointment times.

Customer bookings are confirmed immediately. Administrators can view customer details and references under **Customer health schedules** on `/public/admin`, cancel a booking, or mark a past session completed. Cancelled bookings free capacity; completed bookings still count. Guests can view their latest confirmation only in the browser session used to book. The availability endpoint returns aggregate counts only. Without JavaScript, select a calendar date and click **Check available slots** to refresh the counts manually.

## Run with WAMP

1. Enable `pdo_sqlite` in the PHP version used by Apache and restart WAMP. The PHP CLI may use a separate php.ini. Ensure `storage/` is writable by PHP.
2. Enable Apache `mod_rewrite`, allow `.htaccess` overrides (`AllowOverride All`), and open **http://localhost/Health/public/login**.
3. Open **http://localhost/Health/public/customer** and book directly: customer details, then calendar date and AM/PM session. No account, email, or password is required. Staff can review the booking and record attendance in the admin dashboard.
4. To create an administrator, run `php scripts/create-admin.php` from this folder and follow the prompts. Do not use a patient password on a shared terminal. Sign in with the new account and open `/Health/public/admin`.

Recommended deployment: set the virtual host's DocumentRoot to `C:/wamp/www/Health/public`. Customer routes are `/customer`, `/schedule`, and `/booking-confirmation`. Staff routes include `/login`, `/admin`, and `/manage-admins`. Only `public` should be served. Root `.htaccess` redirects this project's root to `public/` and blocks internal directories for the WAMP subfolder setup.

## PHP development server

```powershell
php -S 127.0.0.1:8080 -t public public/router.php
```

Open http://127.0.0.1:8080/login. If the SQLite DLL exists but is disabled, use `php -d extension=pdo_sqlite -S 127.0.0.1:8080 -t public public/router.php`. Use the same extension flag for admin creation and tests if needed. This server is for local development.

## Layout and security

```text
app/bootstrap.php          sessions, database, validation, URL helpers
public/index.php           front controller, explicit routes, views
public/assets/style.css    responsive portal design
public/.htaccess           clean Apache URLs
public/router.php          local PHP server router
scripts/create-admin.php   CLI-only administrator creation
app/customer.php           customer validation, capacity, reference allocation
app/customer-view.php      customer details, scheduling, confirmation
app/customer-admin.php     administrator customer queue
public/assets/customer.js  live slot counts and receipt printing
storage/                  private SQLite runtime data (ignored by Git)
tests/smoke.php            isolated HTTP integration checks
```

All state changes use POST with CSRF tokens. Staff passwords are hashed with PHP's password API. Sessions regenerate on authentication and use HttpOnly and SameSite cookies (Secure over HTTPS). Queries are prepared, output is escaped, and booking capacity is checked transactionally. Staff control booking status and attendance. Login failures are throttled by username/IP. Public registration is disabled, and only super admins can create admins through the web interface. Use HTTPS and serve only `public` in production. Back up the database securely; it contains personal information. There are no email/SMS notifications, password recovery, billing, or medical record features.

Run `php tests/smoke.php` with a development server at port 8080, or set `HEALTH_TEST_URL` to your local URL including `/Health/public`. Tests create a unique temporary patient and use a separate future slot; do not run against production data.

Customer checks: `php tests/customer-capacity.php` verifies the 500/day limit, idempotency, reference numbers, and concurrent requests using isolated temporary databases. `php tests/customer-http.php` checks the two-step guest flow, live availability, receipt access, and admin cancellation against your local server, and removes its uniquely identified test records afterward. Add `-d extension=pdo_sqlite` after `php` if your CLI SQLite driver is disabled. HTTP tests consume reference sequence numbers even though their booking records are removed. No browser automation was available for visual or JavaScript interaction testing.
