# Tabib

A multi-tenant clinic management system (SaaS) for small clinics, by Fida Technologies.
Every clinic has its own address (`name.<domain>`) and its own database, works in Dari,
Pashto and English, and keeps appointments, the full history of every patient,
prescriptions and billing in one place. It is built to work on a slow connection.

Tabib started from the `fida-sarafi` template: the platform (tenancy, central panel,
resellers, sign-in, languages, assets, deploy) is the same; the business modules are
built for clinics. The plan is in the product requirements document (PRD).

## What is built so far (phase 0: foundation)

- One database per clinic, chosen from the address of the request.
- Public website on the central address with the registration form in three steps
  (the clinic, the owner, price and send). A request waits for the administrator, who
  approves or rejects it in the panel or from the email he is sent.
- System administrator panel at `/admin`: clinics, registration requests, invoices,
  payments, subscriptions and renewals, resellers, announcements, support tickets,
  backups, system health, activity log, staff and settings.
- Reseller panel at `/reseller`.
- Inside a clinic: sign-in page with the clinic's own logo and name, dashboard with a
  setup checklist, branches, users, roles and permissions, clinic settings (licence of
  the Ministry of Public Health, working hours, usual appointment length, logo), audit log,
  support and subscription pages.
- The usual roles of a clinic are made for every new clinic: Clinic Admin, Doctor, Nurse,
  Receptionist and Accountant, with permissions for patients, appointments, clinical
  records, prescriptions, billing and reports.
- The Tabib colours: `data-primary="tabib"` (a medical teal) in `resources/assets/css/fida.css`.

Next (phase 1 onwards): clinic setup wizard and appointments, patients and their history,
visits and clinical notes, prescriptions, billing, SMS reminders, reports.

## Stack

Laravel 13, PHP 8.3 or newer, MySQL 8, Blade with a little JavaScript,
spatie/laravel-permission. No Node is needed: `php tools/build-assets.php` builds the CSS,
JavaScript and icons (and `--check` says whether the committed files are up to date).

## Set up

```
composer install
cp .env.example .env
php artisan key:generate
# create the central database named in .env (DB_DATABASE), then:
php artisan migrate
php artisan app:create-admin
```

The database user must be allowed to create databases: each clinic gets `tabib_<short name>`.

Sign in at `http://localhost:8000/admin` and add a clinic, or use the command:

```
php artisan tenants:create acme --name="Acme Clinic" --owner-name="Owner" --owner-email=owner@example.com
```

The clinic is then at `acme.tabib.test` (see `TENANT_BASE_DOMAIN` in `.env`). On a
development machine, point that name at 127.0.0.1 in the hosts file.

After every update of the system:

```
php artisan migrate            # the central database
php artisan tenants:migrate    # the database of every clinic
```

## Addresses

- `CENTRAL_DOMAINS`: hosts that open the website, the registration form and the panels.
- `TENANT_BASE_DOMAIN`: every clinic gets `<short name>.<base domain>`.
- Every other host must be the domain of a clinic; an unknown host answers "not found".

## Putting it on the SaaS domain

`.github/workflows/deploy.yml` puts the system on a server over SSH (made for cPanel, works on a
plain server too). Before the first run, set in the repository settings:

- the variable `TABIB_DOMAIN` (for example `tabib.af`), and
- the secrets `TABIB_SSH_HOST`, `TABIB_SSH_USER`, `TABIB_SSH_KEY` (and optionally
  `TABIB_SSH_PORT`, `TABIB_DEPLOY_PATH`, `TABIB_ENV_FILE`, `TABIB_PHP`).

Then run the workflow by hand ("check" first, then "deploy"), or push a tag `check-…` / `deploy-…`.
The DNS of the domain needs a wildcard record (`*.tabib.af`) so every clinic's address reaches
the server. `.env.production.example` is the starting point of the server's `.env`.

## Tests

```
php artisan test
```

Tests run on SQLite. GitHub Actions also runs the migrations and opens a clinic on MySQL 8.

## Rules of the repository

- Work happens on `development`; nothing goes to `main` without approval.
- No patient data, passwords or keys in the repository.
