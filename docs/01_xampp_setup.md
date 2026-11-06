# MajorCare setup with XAMPP

## What you need

- XAMPP V3.3.0 (Apache, MySQL/MariaDB, PHP 8.2)
- Google Chrome
- The project folder `MAJORCARE`, placed at `C:\xampp\htdocs\MAJORCARE`

## 1. Set the PHP settings

Open `C:\xampp\php\php.ini` in Notepad and set these values:

    upload_max_filesize = 5M
    post_max_size = 8M
    extension=fileinfo

Save the file. In the XAMPP Control Panel, stop and start Apache.

Check the settings in Command Prompt:

    C:\xampp\php\php.exe -i | findstr /i "upload_max_filesize post_max_size fileinfo"

The output must show `5M`, `8M`, and `fileinfo support => enabled`.

## 2. Start the servers

Open the XAMPP Control Panel. Start **Apache** and **MySQL**. Both must show a green background.

## 3. Create the database

1. Open `http://localhost/phpmyadmin` in Chrome.
2. Click the **SQL** tab.
3. Open `database\majorcare_full_install.sql` in Notepad, select all, copy, and paste it into the SQL box.
4. Click **Go**.

The left panel should show a database named `majorcare` with 17 tables.

This file deletes any existing `majorcare` database. Use it only for a new install.

## 4. Check the database password

Open `config\database.php`. The default XAMPP setup uses user `root` with an empty password. If you set a MySQL password, update `DB_PASS`.

## 5. Create the first staff account

Run this in Command Prompt:

    C:\xampp\php\php.exe C:\xampp\htdocs\MAJORCARE\database\create_staff.php

Enter a staff ID, a full name, and a password of at least 8 characters. The script prints "Staff account created."

Run the connection test to confirm:

    C:\xampp\php\php.exe C:\xampp\htdocs\MAJORCARE\database\test_connection.php

The output must end with `PASSED`.

## 6. Create the storage folders

These folders sit outside `htdocs`, so the web server cannot serve their files directly.

    mkdir C:\xampp\majorcare_storage
    mkdir C:\xampp\majorcare_storage\documents
    mkdir C:\xampp\majorcare_storage\results

Run the storage test:

    C:\xampp\php\php.exe C:\xampp\htdocs\MAJORCARE\database\test_storage.php

## 7. Protect the folders

Create a file named `.htaccess` in each of these folders, with the two lines below inside. Use Notepad, and save as **All Files** with the name in quotation marks, `".htaccess"`.

    # Blocks direct browser access to this folder
    Require all denied

Folders: `config`, `database`.

Open `http://localhost/majorcare/config/` in Chrome. It must show "Forbidden."

## 8. Open the application

Go to `http://localhost/majorcare/public/index.php`.

Students register from the **Register** link. Staff log in with the staff ID created in step 5.

## Resetting the database

To start again with empty tables, run step 3 again, then step 5. This erases all data.