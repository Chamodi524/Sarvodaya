# Sarvodaya Shramadhana Society Banking System

## Project Overview

This system was developed as part of the course module **IMGT 3+34** for the **B.Sc. (Joint Major/Special) Degree** program. It is designed to replace the manual banking operations of the **Sarvodaya Shramadhana Society** with a computerized solution, enhancing efficiency, accuracy, and data management.

---

## Features

- User account management  
- Secure login and password recovery  
- Transaction recording and history tracking  
- Automated reporting and data analysis  

---

## Installation and Setup


---

## Forgot Password Functionality

In the file `forgot_password_process.php`, the system uses **PHPMailer** to send password reset links to users' email addresses.

You must **use your Gmail address and an App Password** for this to work:

```php
$mail->Username = 'youremail@gmail.com'; // Your Gmail address
$mail->Password = 'abcd efgh ijkl mnop'; // Your App Password (not your Gmail password)
