# MajorCare troubleshooting

## Where to find error details

- Apache error log: `C:\xampp\apache\logs\error.log`
- PHP errors appear on screen while `APP_DEBUG` is `true` in `config\app.php`.
- Set `APP_DEBUG` to `false` before a final demonstration, so technical details stay hidden.

## Page problems

**The page is blank.**
Check `error.log` for the last lines. Usually a PHP syntax error, such as a missing semicolon, a missing bracket, or a file saved with a stray character before `<?php`.

**"Headers already sent" appears.**
A file has a space, an empty line, or a byte-order mark before `<?php`. Open the file in Notepad, delete anything before `<?php`, and save as UTF-8, not UTF-8 with BOM.

**The page has no styling.**
The stylesheet did not load. Open `http://localhost/majorcare/assets/css/style.css`. You should see CSS code. If you see "Not Found," move the file to `C:\xampp\htdocs\MAJORCARE\assets\css\`. Press **Ctrl+F5** to reload the page.

**A page shows the footer error "Failed opening required ... footer.php".**
The file `templates\footer.php` is missing or saved with a `.txt` ending. Recreate it.

**A changed page still shows the old version.**
Press **Ctrl+F5**. Chrome may have stored the old page.

## Login problems

**"Invalid ID or password."**
Check the school ID spelling. For staff, use the exact staff ID, such as `STAFF-001`. If a student forgot the password, staff reset it.

**"Your account is still waiting for clinic staff approval."**
The registration has not been approved. Staff approve it under **Registrations**.

**"Your registration was rejected."**
The message shows the reason. The student must contact the clinic.

**Logged out after a short time.**
The session ended. Log in again. Do not use several tabs to submit forms after a long pause, because the security token expires.

## Database problems

**"Access denied for user 'root'."**
The password in `config\database.php` does not match MySQL. Set `DB_PASS` to the correct password.

**"Unknown database 'majorcare'."**
Run `majorcare_full_install.sql` in phpMyAdmin.

**MySQL does not start in XAMPP.**
Port 3306 is used by another program. Stop the other MySQL service in the Windows Services list, or change the port in XAMPP.

**"Table ... doesn't exist" after an update.**
A migration was not run. Run the migration file for that phase in phpMyAdmin, once.

**"Duplicate entry" when adding a slot.**
A slot already exists with the same date and start time. Choose another time.

## Upload problems

**"The file is larger than the server allows."**
The file is over 5 MB, or `php.ini` has not been saved. Check the settings in the setup guide and restart Apache.

**"Only PDF, JPG, and PNG files are accepted."**
The file's real content is not one of those types. A renamed file is rejected on purpose. Save the document as a real PDF, JPG, or PNG.

**"Call to undefined" or "Class 'finfo' not found."**
The `fileinfo` extension is off. Remove the semicolon in front of `extension=fileinfo` in `php.ini`, and restart Apache.

**A document shows the wrong type, such as "Other".**
Check the saved value first. In phpMyAdmin, run `SELECT id, document_type FROM medical_documents ORDER BY id DESC LIMIT 5;`. If the value is `laboratory_result` but the page shows "Other," the label list in `includes\documents.php` does not match. Check that the key `'laboratory_result'` is spelled exactly that way.

## Booking and appointment problems

**"That time is full."**
Every seat is taken. Staff can raise the seat count on the **Slots** page.

**"You already have a request for this time."**
The student already holds a seat in that slot. Choose another time.

**"That time is no longer available."**
Another request took the last seat, or staff closed the slot.

**Reminders do not appear.**
A reminder is created only for an approved appointment that starts within 24 hours. Open **Notifications** after the time window opens, or open the dashboard.

## Notification and message problems

**The notification count is wrong.**
Open **Notifications** and choose **Mark all as read**, then check the count again.

**A message does not show as read.**
A message becomes read only when the other party opens the conversation.

## Phone display

**A table runs off the edge of a phone screen.**
Tables on staff pages are wide. Rotate the phone or scroll sideways. The design pass resolves this.

## When nothing above helps

Copy the exact message on screen, the last 20 lines of `error.log`, and the steps you took. Send them to the developer.