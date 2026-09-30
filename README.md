# 🏫 Feenion — School Fee Management System

**A full-stack, multi-branch fee collection & billing platform for schools, built in PHP/MySQL.**

Feenion handles the entire fee lifecycle for an educational institution — from defining class fee structures, to automated monthly invoice generation, manual and parent-submitted payment collection, family-level combined vouchers, PDF/QR-coded receipts, and financial reporting — across multiple campuses and user roles.

> Originally built and shipped as a production system for a real school client. This repository showcases the architecture and feature set of the platform.

---

## ✨ Key Features

### 👨‍👩‍👧‍👦 Parent-Based Combined Family Vouchers
- One consolidated voucher per **family**, per month — instead of one per child — so parents with multiple kids enrolled see everything in a single view.
- Payments made against a family voucher are automatically distributed across children, either **proportionally** (by amount owed) or **split equally**.
- Month-by-month navigation to view or retroactively edit past vouchers and invoices.
- Family-level discounts with configurable reasons (sibling, bulk, loyalty, hardship, etc.).

### 💵 Billing & Invoicing
- Configurable **fee heads** (tuition, transport, exam fee, etc.) and **class-level fee structures**.
- Per-student fee overrides independent of the class default.
- **Automated monthly invoice generation** via a secured cron endpoint, with a configurable due-date offset.
- Manual, staff-driven collection workflow for walk-in / cash payments.

### 💳 Payment Collection & Verification
- **Parent self-service portal**: parents can view outstanding vouchers, submit payments, and upload proof of payment (receipt image).
- **Verification queue** for accountants/admins to approve or reject submitted payments before they post to the ledger.
- Full **payment history** per family and per student.

### 🧾 Documents & Notifications
- Server-side **PDF generation** (mPDF) for professional printable vouchers and receipts.
- **QR codes** embedded on vouchers (chillerlan/php-qrcode) for quick verification/scanning at the counter.
- Transactional **email notifications** via PHPMailer (payment reminders, receipts).
- Bulk reminder sending via a dedicated cron job.

### 🏢 Multi-Branch / Multi-Campus
- Branch-aware data model — students, invoices, and settings are scoped per branch.
- Branch switcher for admins who manage more than one campus.
- Per-branch settings alongside global system settings.

### 📊 Reporting & Operations
- Dashboard and reporting views (collections, dues, expenses) for financial oversight.
- **Expense tracking** with configurable expense categories, separate from fee income.
- **Student ledger** — a running account view of every charge and payment per student.
- Print Center for bulk-printing paid/unpaid vouchers.
- **Audit log** of sensitive admin actions, plus a database **backup/restore** tool.

### 🔐 Roles & Security
- Three distinct portals with route-level access control: **Admin**, **Accountant**, **Parent**.
- Session-based authentication with `password_hash`/`password_verify`, session timeouts, and forgot/reset-password flows.
- **CSRF token** validation on all state-changing forms.
- Centralized activity logging (`log_activity`) for traceability.

---

## 🧱 Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP (procedural, no framework — direct PDO/MySQL access) |
| Database | MySQL / MariaDB |
| Frontend | AdminLTE 3 (Bootstrap 4), DataTables, jQuery |
| PDF generation | [mPDF](https://mpdf.github.io/) |
| QR codes | [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode) |
| Email | [PHPMailer](https://github.com/PHPMailer/PHPMailer) |
| Dependency management | Composer |
| Scheduled jobs | Cron-triggered PHP endpoints (monthly billing, bulk reminders) |

---

## 🗂️ Project Structure

```
├── admin/                  # Admin portal — students, invoices, fees, reports, settings, users
├── accountant/             # Accountant portal — invoices, payments, ledger
├── parent/                 # Parent self-service portal — vouchers, payment submission, history
├── includes/               # Shared core: db connection, auth/session, helpers, header/footer
├── cron/                   # Scheduled jobs: monthly billing, bulk payment reminders
├── lib/php-qrcode/         # QR code generation library
├── vendor/                 # Composer dependencies (mPDF, PHPMailer, php-qrcode)
├── uploads/                # Logos, payment proofs, generated QR codes
├── database.sql            # Full base schema
├── 00_DATABASE_MIGRATIONS.sql   # Schema migrations for the family-voucher upgrade
├── 06_API_DOCUMENTATION.md      # Internal API / function reference
├── 07_STAFF_TRAINING_GUIDE.md   # End-user (staff) training documentation
├── 09_TESTING_PROCEDURES.md     # QA test plan
└── config.php               # DB & site configuration
```

### Core Database Entities
`branches`, `classes`, `sections`, `students`, `fee_heads`, `class_fees`, `student_fee_structure`, `invoices`, `invoice_items`, `vouchers`, `payments`, `expenses`, `expense_categories`, `users`, `audit_logs`, `settings`

---

## ⚙️ Getting Started

1. **Clone & install dependencies**
   ```bash
   git clone <this-repo>
   cd feenion
   composer install
   ```
2. **Create the database** and import the schema:
   ```bash
   mysql -u root -p your_database < database.sql
   mysql -u root -p your_database < 00_DATABASE_MIGRATIONS.sql
   ```
3. **Configure the app** — edit `config.php` with your DB credentials and `SITE_URL`.
4. **Set up scheduled jobs** (optional, for production use):
   - `cron/run_monthly_billing.php?key=YOUR_SECRET_KEY` — generates monthly invoices.
   - `cron/send_bulk_reminders.php` — sends payment reminder emails.
5. Serve the app with your local stack of choice (XAMPP/Apache/Nginx + PHP 8+).

Full step-by-step installation notes are in `INSTALLATION_PARENT_SYSTEM.md`.

---

## 📸 Screenshots

*(Add dashboard, voucher, and payment-collection screenshots here before publishing.)*

---

## 📄 License

open source
