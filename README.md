# PHP Forum System

## Project Purpose

This project was developed during an internship to gain practical experience in PHP and web application security.

In addition to basic forum functionality, the project focuses on user management, database operations, session management, and measures that can be taken against common web security vulnerabilities.

---

## Technologies Used

- PHP
- MySQL
- PDO
- Apache

Database operations are performed through PDO. It is recommended to run the application on a real web server and, whenever possible, use HTTPS.

---

# Implemented Security Measures

## SQL Injection

Secure query methods are used for database queries, and user-supplied data is not directly concatenated into SQL queries.

This is intended to provide basic protection against SQL Injection attacks.

---

## XSS (Cross-Site Scripting)

User-provided data is appropriately processed before being displayed on the page.

Content Security Policies are also used to help restrict potentially malicious code that could be executed on the client side.

---

## CSRF (Cross-Site Request Forgery)

Security tokens associated with the user's session are used for form operations.

Server-side validation makes it more difficult for unauthorized requests to be executed.

---

## Password Security

User passwords are not stored in plain text.

PHP's built-in password security mechanisms are used to securely store and verify passwords.

A basic password policy is also applied to user passwords.

---

## Brute-Force Protection

Rate limiting and temporary account locking mechanisms have been implemented to protect against failed login attempts.

Different error messages that could reveal whether a user's credentials exist in the system are avoided during the login process.

These measures are intended to make password-guessing attacks and username enumeration more difficult.

---

## Session Security

Secure cookie and session settings are used to improve the security of user sessions.

Session IDs are also regenerated under certain circumstances to provide additional protection against attacks such as session fixation.

---

## Security HTTP Headers

Various security-related HTTP headers are used in the application.

These headers help reduce the impact of client-side attacks such as clickjacking, MIME type manipulation, and unnecessary referrer information disclosure.

In environments where HTTPS is used, implementing additional security policies is also recommended.

---

## Input Validation

User-provided data is validated on the server side.

Appropriate validation, length, and format checks are applied to fields such as usernames, email addresses, passwords, and forum content.

Numeric parameters are also validated to ensure that they match the expected data type before being processed.

Client-side validation is not considered sufficient for security purposes; the primary validation is performed on the server side.

---

## Error Handling

In production environments, the application prevents technical error details from being displayed to users.

Users are shown generic error messages whenever possible, while necessary technical information is recorded in server-side logs.

This approach helps reduce the risk of exposing information about the application's internal structure.

---

## File Access

Access to sensitive files and directories is restricted through web server configuration.

Directory listing is also disabled.

This is intended to prevent sensitive files such as database files, logs, and configuration files from being directly accessible from outside.

---

## File Uploads

The application does not include file or image upload functionality.

Due to the additional security risks that file upload mechanisms can introduce, this feature was not included within the scope of the project.

---

# Database

The project uses a **MySQL** database.

Database operations are performed through PDO from PHP.

MySQL was chosen because it is widely used with PHP and Apache and is suitable for creating an environment based on a real database server.

---

# Installation and Setup

## Requirements

- PHP 8.1+
- Apache
- MySQL 5.7+ / MariaDB 10.3+ (InnoDB and `INSERT ... ON DUPLICATE KEY UPDATE` support are required)
- `pdo_mysql` extension
- HTTPS is recommended

The required PDO driver that allows PHP to communicate with MySQL must be enabled for the application to work.

## Database Connection Settings

Connection details are read from environment variables in `config.php`. If they are not defined, development-oriented default values are used:

| Variable  | Description          | Default           |
| --------- | -------------------- | ----------------- |
| `DB_HOST` | MySQL server address | `127.0.0.1`       |
| `DB_PORT` | MySQL port           | `3306`            |
| `DB_NAME` | Database name        | `forum`           |
| `DB_USER` | Username             | `forum`           |
| `DB_PASS` | Password             | `str0ng-p4ssw@rd` |

First, create the database and user:

```sql
CREATE DATABASE forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'forum'@'localhost' IDENTIFIED BY 'str0ng-p4ssw@rd';
GRANT ALL PRIVILEGES ON forum.* TO 'forum'@'localhost';
FLUSH PRIVILEGES;
```

Then configure the environment variables (e.g., using Apache `SetEnv` or system environment variables) and run the application. The tables are automatically created by `config.php` on the first request in an idempotent manner, so manually importing a schema is not required.

## Server Environment

It is recommended to run the application on a real web server such as Apache.

PHP's built-in development server can be used for testing and development. For a real production environment, an appropriate server configuration and HTTPS are recommended.

The database connection is established through PDO according to the application's configuration.

---

# Project Status

This project was primarily developed for learning and gaining practical experience in PHP, database usage, and web application security.

The implemented security measures are intended to provide basic protection against common types of attacks. However, due to the scope of the project, it should not be considered an independent or comprehensive security solution.

The project can be further developed by adding new security controls and application features in the future.

---

# License

The source code in this repository is licensed under the **MIT License**.

**Logos and other visual assets in this repository are not covered by the MIT License and may not be copied, modified, redistributed, or used without prior written permission from the copyright holder.**
