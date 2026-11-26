# DAR-RMS local record management system

DAR-RMS runs on XAMPP and stores accounts, outgoing records, and attachments
in a local MySQL/MariaDB database that you can view in phpMyAdmin.

## Requirements

- XAMPP with Apache and MySQL started
- PHP 8.1 or later with `pdo_mysql`

## Connect to XAMPP and phpMyAdmin

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin/`.
3. Create a database named `dar_rms` using the `utf8mb4_unicode_ci` collation.
4. The app creates its three tables the first time it connects:
   `accounts`, `outgoing_records`, and `outgoing_images`.
5. If your MySQL root account has a password, copy
   `database.config.example.php` to `database.config.php` and set the password
   there. The local config file is ignored by Git. The default XAMPP setup uses
   `root` with an empty password.
6. To copy the existing local SQLite data into the new database, run this once
   from PowerShell:

   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\DAR\scripts\migrate-sqlite-to-mysql.php
   ```

   It copies account password hashes, outgoing records, and image attachments
   without deleting the original `data\ledgerly.sqlite` backup. The migration
   stops if the MySQL tables already contain data, to avoid duplicates.
7. Open `http://localhost/DAR/`.

## Database tables

- `accounts` stores administrator and outgoing-user accounts.
- `outgoing_records` stores outgoing correspondence details.
- `outgoing_images` stores image and PDF attachments as binary data.

All tables use InnoDB with foreign keys, so deleting an account or record also
removes its owned records or attachments. The outgoing list displays record
cards and supports filtering by received month, searching names or record numbers,
updating or removing records, and printing individual record cards.

## Project layout

- `index.php` handles authentication and the shared page shell.
- `web.php` connects to MySQL and creates the application tables.
- `database.config.example.php` shows the local connection settings.
- `scripts/migrate-sqlite-to-mysql.php` copies the existing SQLite data once.
- `actions/outgoing.php` validates and saves outgoing records.
- `pages/admin.php` renders administrator account management.
- `pages/outgoing.php` routes the outgoing dashboard, entry form, and record list.
- `pages/outgoing-dashboard.php` shows the signed-in user's total record count.
- `pages/outgoing-entry.php` and `pages/outgoing-list.php` render the sidebar views.
- `outgoing-image.php` serves image and PDF attachments to their authenticated owner.
- `assets/css/styles.css` contains the responsive interface and login background styling.
- `assets/js/script.js` contains the browser-side interface interactions.
- `assets/login-background.jpg` is the softly blurred login background photo.
- `assets/dar-rms-logo.png` is the DAR-RMS application logo.
