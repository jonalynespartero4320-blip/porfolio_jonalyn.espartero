# WAMP MySQL / MariaDB setup

## Database creation file

Use **[create-database.sql](create-database.sql)** for a fresh installation. In phpMyAdmin, select your MySQL server, open **Import**, choose this file, then click **Go**. It creates `health_appointments` and all seven tables, including attendance fields, cancellation fields, daily reference counters, foreign keys, and booking indexes. It also seeds three doctors and the legacy AM/PM counters. Daily counters are created by the application when bookings are made.

This file contains no customer data or admin passwords. After importing, configure `config/database.php` using `config/database.example.php`, then run `scripts/create-super-admin.php` with WAMP PHP to provision the first super admin (the script accepts JSON containing `username` and `password` on standard input). Existing installations already have staff accounts; do not recreate them. Importing this file does not update older table definitions; use the separate migration SQL files for those installations.

The current local application uses MySQL port **3308**. Match the port in `config/database.php` to the server you imported into. Check connectivity with WAMP PHP running `scripts/check-database.php`. The creation file was checked for all seven required table declarations; it has not been imported into the live database.

**Daily reference numbers:** New bookings use `001-MMDDYY-A` / `001-MMDDYY-P`. The first booking for each appointment date and AM/PM time starts at 001. The live WAMP database has already received `daily-reference-numbers.sql`; other existing installations should import it once. Fresh installations include the table in `health.sql`. Existing references and appointments are retained. Cancellation accepts both formats. Admin lists sort by appointment date and numeric reference order.

**Username-only update:** No email addresses are collected or used. Fresh imports use the updated `health.sql`. If you already imported an older schema with an `email` column, export a backup and import `remove-email.sql` once instead of re-importing the schema to remove that column. The migration preserves account IDs and passwords; old accounts without usernames receive `legacy-ID`. The existing super admin username remains unchanged.

**Public cancellation update:** Existing MySQL/MariaDB databases should also import `customer-cancellations.sql` once to add cancellation reason, time, and source fields. Fresh imports already include them in `health.sql`. SQLite adds these fields automatically. Customer cancellation is available at `/Health/public/cancel-appointment` without an account or email.

1. Start WAMP's Apache and MySQL (or MariaDB) services. Use MySQL 8.0+ or MariaDB 10.5+.
2. Open **http://localhost/phpmyadmin/** and sign in using your database administrator credentials.
3. Open **Import**, choose `database/health.sql`, then click **Go / Import**. The file creates the `health_appointments` database, its six tables, three doctors, AM/PM sequence rows, and the requested super admin account. The account seed contains a password hash, not a plaintext password. Importing again does not overwrite existing accounts or reset counters. If your phpMyAdmin account cannot create databases, ask the database administrator to create `health_appointments` first.
4. Copy `config/database.example.php` to **config/database.php**. Set the database username/password and port used by your WAMP server (usually 3306; MariaDB may use 3307). The example uses `root` with an empty password for a default local WAMP installation; replace these with your actual values. Use a dedicated restricted database account for deployment. The actual config is ignored by Git and blocked from web access.
5. Enable **pdo_mysql** for the PHP version used by Apache, then restart WAMP. CLI PHP may use another php.ini. Test with `php -d extension=pdo_mysql scripts/check-database.php`, or omit the extension flag if already enabled.
6. Open **http://localhost/Health/public/login**. Sign in as **cictd-administrator** using your previously supplied password, then use **Manage admins**. Customer booking is at **http://localhost/Health/public/customer**.

The app keeps using SQLite until `config/database.php` selects `mysql`. Removing that config file returns to the existing SQLite database. This SQL creates a fresh MySQL schema and seeds; it **does not transfer existing SQLite bookings, patients, attendance records, or sequence values**. Keep `storage/health.sqlite` if you need those records. No live MySQL database has been modified by preparing these files.

InnoDB transactions lock the AM/PM sequence row before checking customer capacity, allocating the reference, and inserting the booking. All customer booking writers must use that application transaction; manual database inserts bypass application capacity validation. The doctor's active-slot unique index uses a generated column, permitting rebooking after cancellation. Existing security controls, form timeouts, and prepared queries remain enabled with either database.

Validation on this workstation covers PHP syntax and the SQLite regression suite. A MySQL/MariaDB server is not installed/listening here, so importing and running the MySQL path still needs verification on your WAMP server. Run the database check after import, then confirm booking, duplicate-slot rejection, login, admin creation, attendance, and cancellation in the app. Existing `*-http.php` tests currently perform cleanup against SQLite directly; do not run them against MySQL until adapted for that database.

Engine references: [MySQL locking reads](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html), [MySQL generated-column indexes](https://dev.mysql.com/doc/refman/8.4/en/create-table-secondary-indexes.html), [MariaDB generated columns](https://mariadb.com/docs/server/reference/sql-statements/data-definition/create/generated-columns).
