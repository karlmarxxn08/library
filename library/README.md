# Library Management System (PHP + MySQL)

## Install
1. Copy this folder to your web root (XAMPP: `htdocs/library`). Requires PHP 8.0+ and MySQL/MariaDB.
2. Import `database.sql` in phpMyAdmin (it creates the `library_db` database).
3. Edit `config.php`: database login and `BASE_URL` (`/library` if the folder is `htdocs/library`).
4. Open `/library/setup.php`, create the admin account, then **delete `setup.php`**.

## Features
- Public catalog: search by title, author or ISBN, category filter, pagination, live availability, reservations.
- Accounts: self-registration, sign in/out, roles (admin, librarian, member), suspend accounts.
- Books CRUD, categories CRUD, members/staff CRUD (admin-only role changes).
- Circulation: issue, return, renew (max 2), per-day fines, mark fines paid, loan limit, duplicate-loan check, reservations.
- CMS: pages (shown in the menu) and announcements (shown on the catalog), draft/publish, editable HTML.
- Settings: library name, loan days, fine per day, maximum loans.
- Dashboard: counts, overdue list, latest loans.
- Security: PDO prepared statements, password_hash, CSRF tokens, escaped output, session regeneration.
