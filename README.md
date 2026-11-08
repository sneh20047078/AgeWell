# AgeWell Senior Care

AgeWell is a web application for coordinating senior-care information. It provides tools for managing senior profiles, caregiver assignments, appointments, health records, schedules, notifications, and operational reports.

## Features

- **Role-based access:** separate administrator and caregiver experiences.
- **Senior profiles:** contact and emergency-contact details, care information, and caregiver assignments.
- **Appointments and schedules:** create and review appointments and caregiver schedules.
- **Health records and alerts:** record health metrics, view history, and track threshold-based alerts.
- **Notifications:** in-app notifications, appointment reminders, and optional email/SMS integrations.
- **Administration:** manage users, review contact messages and replies, and view activity logs.
- **Reports:** review and export senior, appointment, and health information.

## Requirements

- PHP 8.0 or later
- MySQL or MariaDB
- PHP extensions: `mysqli`, `pdo_mysql`, `curl`, `openssl`, and `mbstring`
- Composer (to install the PHP dependencies)
- A web server such as Apache, or PHP's built-in development server

The application has been exercised with PHP 8.0 and MariaDB 10.4. The bundled database dump uses a MariaDB-compatible collation.

## Local setup

1. **Get the project files** and open a terminal in the project root.

2. **Install PHP dependencies:**

   ```powershell
   composer install
   ```

   Dependencies are declared in `composer.json`. If Composer is already unavailable or the `vendor` directory is present, you can skip this step for a local run.

3. **Create and load the database.**

   Start MySQL/MariaDB, then import [`database/agewell-schema.sql`](database/agewell-schema.sql) using phpMyAdmin or your preferred MySQL client. This published dump creates the `agewell` database and tables without preloaded user, senior, or health records.

   > **Warning:** The schema dump contains `DROP TABLE IF EXISTS` statements for application tables. Importing it into an existing AgeWell database replaces those tables and their data. Back up any database you need to keep before importing.

4. **Configure database access.**

   Set the database host, username, password, and database name in `includes/db.php` to match your local MySQL/MariaDB installation. The default database name is `agewell`. Register an application account after setup.

5. **Configure integrations if needed.**

   The application reads provider settings from process environment variables. For PowerShell, set the variables in the same terminal session before starting the PHP server:

   ```powershell
   $env:AGEWELL_EMAIL_SMTP_ENABLED = "1"
   $env:AGEWELL_EMAIL_SMTP_USERNAME = "your-smtp-username"
   $env:AGEWELL_EMAIL_SMTP_PASSWORD = "your-smtp-password"
   $env:AGEWELL_EMAIL_FROM_ADDRESS = "your-sender@example.com"
   $env:AGEWELL_TWILIO_ACCOUNT_SID = "your-account-sid"
   $env:AGEWELL_TWILIO_AUTH_TOKEN = "your-auth-token"
   $env:AGEWELL_TWILIO_PHONE_NUMBER = "your-twilio-number"
   ```

   Email is disabled unless `AGEWELL_EMAIL_SMTP_ENABLED` is set to `1`. SMS requires valid Twilio settings. Never commit real credentials; rotate any credentials that may already have been exposed.

6. **Start the application.**

   For local development, from the project root run:

   ```powershell
   php -S 127.0.0.1:8080
   ```

   Then open [http://127.0.0.1:8080/](http://127.0.0.1:8080/) in a browser. Keep the terminal open while using the app; press `Ctrl+C` to stop the server.

   With XAMPP, you can instead place the project under the Apache document root, start Apache and MySQL, and visit the corresponding `http://localhost/<project-folder>/` URL.

The PHP built-in server is for development only. Use a properly configured web server and database for deployment.

## Application areas

| Area | Main pages |
| --- | --- |
| Sign in and account access | `login.php`, `register.php`, `forgot_password.php` |
| Dashboards | `admin_dashboard.php`, `caregiver_dashboard.php` |
| Senior records | `senior_citizens.php`, `senior_add.php`, `senior_edit.php`, `senior_profile.php` |
| Caregiver assignments | `caregiver_assignments.php`, `assign_caregiver.php` |
| Appointments and schedules | `appointments.php`, `appointment_add.php`, `schedule_view.php`, `schedule_add.php` |
| Health records and alerts | `medical_records.php`, `medical_record_add.php`, `health_metrics.php`, `health_alerts.php` |
| Administration and reports | `users.php`, `contact_messages.php`, `activity_logs.php`, `reports.php` |

Access to pages and actions depends on the signed-in user's role.

## Testing and verification

There is currently no configured PHPUnit/Pest suite in the repository. You can check an individual PHP file for syntax errors with:

```powershell
php -l path\to\file.php
```

For functional checks, run the app with a local database and verify the relevant workflows using test data. Exercise email and SMS only with test recipients and valid provider accounts; those integrations can contact real people and may incur charges.

## Troubleshooting

- **Database connection error:** verify MySQL/MariaDB is running and the values in `includes/db.php` match your local database account.
- **Missing table or column:** confirm the intended database was imported from `database/agewell.sql`; check that schema changes required by the current application are present.
- **Blank page or PHP error:** check the PHP/web-server error log and verify required extensions are enabled.
- **Email/SMS not delivered:** check the provider configuration and logs. A successful page load does not verify external delivery.
- **Icons or external assets missing:** some frontend assets are loaded from external CDNs and require network access.

## Project layout

```text
.
├── admin/                 # Additional administration pages
├── assets/                # CSS, JavaScript, and images
├── database/              # Database schema and sample data
├── includes/              # Shared PHP helpers and configuration
├── sql/                   # Supplemental SQL setup
├── vendor/                # Composer-managed PHP dependencies
├── *.php                  # Application pages and actions
└── composer.json          # PHP dependency manifest
```
