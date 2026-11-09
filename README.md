# Invoice Generator (Laravel)

Multi-user (SaaS) invoice generator. BDT is the default currency, with USD, EUR, GBP, INR or any custom currency code.

## Features

- **Accounts** — register / sign in. Every user sees only their own invoices and vendors (enforced by `InvoicePolicy` and by scoping every query to the signed-in user).
- **Two invoice types**
  - **One time** — rows: SL, Description, Quantity, Amount. Add or remove as many rows as you need.
  - **Monthly** — billing month plus rows: SL, Description, Amount.
- **Totals** — Total amount, Before pay, Now pay and Due amount (Total − Before pay − Now pay), plus a comment. Totals update live in the browser and are always recalculated on the server when saved.
- **Same-vendor auto-fill** — pick a saved vendor and the form fills in their phone, email, address, and the type, currency, items and comment of their last invoice (monthly invoices move to the next billing month). It also warns if that last invoice still has money due.
- **Invoice list** with search, Paid / Partly paid / Unpaid status, and BDT totals (billed, collected, outstanding).
- **Vendors page** — each vendor's invoice count and outstanding amount, with "New invoice" shortcuts.
- **My business** — your logo (PNG, JPG or WebP, up to 2 MB), name, address, contact and invoice-number prefix (numbers run `INV-202611-001`, `INV-202611-002`, …).
- **Print / Save as PDF** — clean printable invoice page.

## Requirements

- PHP 8.3+ with `pdo_sqlite` (or MySQL), `mbstring`, `xml`
- Composer 2

No Node/npm build is needed: the CSS and JS are plain files in `public/`.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # Windows: type nul > database\database.sqlite
php artisan migrate
php artisan db:seed                   # optional demo data: demo@example.com / password
php artisan serve
```

Open http://127.0.0.1:8000 and create an account.

Uploaded logos are saved directly in `public/uploads/logos/`, so no `storage:link` is needed. On a server, make sure the web server can write to that folder (for example `chmod -R 775 public/uploads`).

### Using MySQL instead of SQLite

In `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taka_invoice
DB_USERNAME=root
DB_PASSWORD=
```

then `php artisan migrate`.

## Super admin

A super admin can open **Super admin** in the top bar and see every account on the platform: each account's business details, totals, invoices and vendors. Viewing is **read-only**: a super admin cannot edit or delete another account's invoices or vendors. The role cannot be chosen at registration; it is set only from the command line:

```bash
php artisan app:super-admin admin@example.com             # create (asks for a password) or promote an existing account
php artisan app:super-admin admin@example.com --revoke    # remove the role
php artisan app:super-admin --list                        # show current super admins
```

Normal users who try `/super-admin` get "Not Found".

## Tests

```bash
php artisan test
```

Covers: guest redirect, registration, server-side totals, monthly vs one-time rules, custom currency, user isolation (a user cannot view, edit, delete or look up another user's data), vendor auto-fill, and update/delete.

## Where things are

| Part | File |
|---|---|
| Routes | `routes/web.php` |
| Invoice CRUD | `app/Http/Controllers/InvoiceController.php` |
| Validation | `app/Http/Requests/InvoiceRequest.php` |
| Save + totals (transaction) | `app/Services/InvoiceSaver.php` |
| Vendor auto-fill endpoint | `app/Http/Controllers/VendorController.php` → `GET /vendors/lookup` |
| Ownership rules | `app/Policies/InvoicePolicy.php` |
| Models | `app/Models/{User,Vendor,Invoice,InvoiceItem}.php` |
| Migrations | `database/migrations/2026_11_08_*` |
| Views | `resources/views/{layouts,auth,invoices,vendors,profile}` |
| Form behaviour (rows, totals, auto-fill) | `public/js/invoice-form.js` |
| Styles | `public/css/app.css` |
