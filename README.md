# PHP + Supabase Setup Guide (Windows)

This guide will help you install PHP on Windows, verify that it works in PowerShell, and fix the most common setup issues for our REST API project.

It also includes the fix for the `pgsql` and `pdo_pgsql` extensions so PHP can connect to Supabase PostgreSQL. PHP publishes official Windows downloads through its downloads page and Windows build distribution, and Scoop provides a Windows package workflow for PHP. ([php.net](https://www.php.net/downloads.php?utm_source=chatgpt.com))

---

## What you need

- Windows 10 or 11
- PowerShell
- Internet connection
- A Supabase project
- The project `server.php`
- Your Supabase `DATABASE_URL`

---

## Recommended install method: Scoop

Scoop is a Windows command-line installer that places apps in your user profile and exposes them through shims in your `PATH`. The Scoop PHP bucket provides installable PHP versions for Windows. ([scoop.sh](https://scoop.sh/?utm_source=chatgpt.com))

### 1) Install Scoop

Open **PowerShell** and run:

```powershell
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser
irm get.scoop.sh | iex
```

### 2) Add the PHP bucket

```powershell
scoop bucket add php
```

### 3) Install PHP

```powershell
scoop install php
```

### 4) Confirm PHP is installed

Close PowerShell, open a **new** PowerShell window, then run:

```powershell
php -v
php --ini
```

Expected result:

- `php -v` shows a PHP version
- `php --ini` shows the active configuration file

---

## Manual install method

Use this if Scoop fails or is blocked on your machine.

PHP provides official Windows builds from its downloads pages. ([php.net](https://www.php.net/downloads.php?utm_source=chatgpt.com))

### 1) Download PHP for Windows

Download a **Thread Safe x64 ZIP** build from the official PHP Windows downloads linked from php.net.

### 2) Extract PHP

Extract it to a simple folder, for example:

```text
C:\php
```

### 3) Add PHP to PATH

Add `C:\php` to your Windows `Path` environment variable.

Steps:

1. Open **Start**
2. Search for **Environment Variables**
3. Open **Edit the system environment variables**
4. Click **Environment Variables**
5. Select **Path**
6. Click **Edit**
7. Click **New**
8. Add `C:\php`
9. Save everything
10. Open a new PowerShell window

### 4) Create `php.ini`

Inside `C:\php`, copy:

```text
php.ini-development
```

to:

```text
php.ini
```

### 5) Verify installation

```powershell
php -v
php --ini
```

---

## Verify required PHP extensions

For this project, PHP needs:

- `PDO`
- `pdo_pgsql`
- `pgsql`

Check them with:

```powershell
php -m | findstr PDO
php -m | findstr pgsql
```

Expected output should include something like:

```text
PDO
pdo_pgsql
pgsql
```

PHP’s PostgreSQL support and PDO PostgreSQL driver are provided by the `pgsql` and `pdo_pgsql` extensions. ([php.net](https://www.php.net/manual/en/ref.pdo-pgsql.php?utm_source=chatgpt.com))

---

## Fix: `pgsql` or `pdo_pgsql` not loading

This is the most common issue when connecting PHP to Supabase.

### Step 1: Find the active `php.ini`

Run:

```powershell
php --ini
```

Look for:

- **Loaded Configuration File**
- or **Additional .ini files parsed**

Example from Scoop:

```text
C:\Users\YOUR_NAME\scoop\apps\php\current\cli\php.ini
```

### Step 2: Open the correct config file

Example:

```powershell
notepad C:\Users\YOUR_NAME\scoop\apps\php\current\cli\php.ini
```

Or for manual install:

```powershell
notepad C:\php\php.ini
```

### Step 3: Enable PostgreSQL extensions

Find these lines:

```ini
;extension=pdo_pgsql
;extension=pgsql
```

Remove the semicolons so they become:

```ini
extension=pdo_pgsql
extension=pgsql
```

Save the file.

### Step 4: Restart PowerShell and verify

Close PowerShell, open it again, then run:

```powershell
php -m | findstr pgsql
php -m | findstr PDO
```

If it works, you should now see:

```text
PDO
pdo_pgsql
pgsql
```

---

## If PHP still says `php is not recognized`

That means Windows still cannot find `php.exe`.

### Fix 1: Open a new PowerShell window

Sometimes `PATH` changes only apply to newly opened terminals.

### Fix 2: Check where PHP is installed

Run:

```powershell
where.exe php
```

If nothing appears, PHP is not in `PATH` yet.

### Fix 3: Add the correct folder to PATH

Use one of these common locations:

```text
C:\php
C:\Users\YOUR_NAME\scoop\shims
C:\Users\YOUR_NAME\scoop\apps\php\current
```

Then open a new PowerShell window and test again:

```powershell
php -v
```

---

## If `php --ini` shows `(none)`

This usually means PHP is running, but it has not loaded a main config file yet.

### Fix

- For Scoop, use the `.ini` path shown under **Additional .ini files parsed**
- For manual install, make sure `php.ini` exists in your PHP folder
- Re-run:

```powershell
php --ini
```

If you already have an additional parsed config file, that is usually the file you should edit.

---

## If `pdo_pgsql` still does not appear after enabling it

Try these checks.

### 1) Confirm you edited the right file

Run:

```powershell
php --ini
```

Make sure the file you edited matches the loaded or parsed config path.

### 2) Check for typos

These must be exact:

```ini
extension=pdo_pgsql
extension=pgsql
```

### 3) Check the extension directory

Open `php.ini` and make sure `extension_dir` points to PHP’s `ext` folder.

Common example:

```ini
extension_dir="ext"
```

For manual installs, it may also work as a full path:

```ini
extension_dir="C:\php\ext"
```

### 4) Confirm the extension files exist

Look inside your PHP `ext` folder and confirm these files exist:

```text
php_pdo_pgsql.dll
php_pgsql.dll
```

If those files are missing, reinstall PHP using Scoop or re-download the correct official ZIP package.

---

## Project setup after PHP works

### 1) Set your Supabase connection string

In PowerShell:

```powershell
$env:DATABASE_URL="postgresql://postgres:YOUR_PASSWORD@db.YOUR_PROJECT.supabase.co:5432/postgres?sslmode=require"
```

### 2) Start the PHP server

From the project folder:

```powershell
php -S localhost:8000 server.php
```

### 3) Open the API

In your browser:

```text
http://localhost:8000
```

---

## Create the sample table in Supabase

Run this in the Supabase SQL Editor:

```sql
create table if not exists user_demo (
  user_id bigserial primary key,
  name text not null,
  email text not null unique,
  password text not null
);
```

---

## Run and test in terminal

From the project folder, start the PHP server:

```powershell
php -S localhost:8000 server.php
```

## API endpoints

### `POST /signup`

Creates a new account in `public.user_demo`.

Expected fields:

- `name`
- `email`
- `password`

Example:

```powershell
Invoke-RestMethod -Method Post -Uri http://localhost:8000/signup `
  -ContentType "application/json" `
  -Body '{"name":"Ada","email":"ada@example.com","password":"secret123"}'
```

If the email already exists, the API returns:

```json
{
  "success": false,
  "error": "duplicate_email",
  "field": "email",
  "message": "This email is already registered. Please use a different email or log in."
}
```

### `POST /login`

Checks whether the provided email and password match an existing account.

Expected fields:

- `email`
- `password`

Example:

```powershell
Invoke-RestMethod -Method Post -Uri http://localhost:8000/login `
  -ContentType "application/json" `
  -Body '{"email":"ada@example.com","password":"secret123"}'
```

If the credentials match, the API returns a success message. If not, it returns:

```json
{
  "success": false,
  "message": "incorrect email or password",
  "hint": "not account yet? go to /signup"
}
```

### `PUT /update`

Updates an existing account by email.

Expected fields:

- `email`
- `name` or `password` or `new_email`

Example:

```powershell
Invoke-RestMethod -Method Put -Uri http://localhost:8000/update `
  -ContentType "application/json" `
  -Body '{"email":"ada@example.com","name":"Ada Lovelace","password":"newsecret123"}'
```

### `DELETE /delete`

Deletes an account by email.

Example:

```powershell
Invoke-RestMethod -Method Delete -Uri "http://localhost:8000/delete?email=ada@example.com"
```

## Testing in a browser

The browser address bar can only do simple `GET` requests, so the easiest browser test is to open:

```text
http://localhost:8000
```

That should return the JSON response from the root endpoint.

To test the API from the browser:

1. Open `http://localhost:8000` in your browser.
2. Open DevTools.
3. Go to the Console tab.
4. Run one of these commands.

### Browser console: signup

```javascript
fetch("http://localhost:8000/signup", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    name: "Ada",
    email: "ada@example.com",
    password: "secret123",
  }),
})
  .then((response) => response.json())
  .then(console.log);
```

### Browser console: login

```javascript
fetch("http://localhost:8000/login", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    email: "ada@example.com",
    password: "secret123",
  }),
})
  .then((response) => response.json())
  .then(console.log);
```

### Browser console: update

```javascript
fetch("http://localhost:8000/update", {
  method: "PUT",
  headers: {
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    email: "ada@example.com",
    name: "Ada Lovelace",
    password: "newsecret123",
  }),
})
  .then((response) => response.json())
  .then(console.log);
```

### Browser console: delete

```javascript
fetch("http://localhost:8000/delete?email=ada@example.com", {
  method: "DELETE",
})
  .then((response) => response.json())
  .then(console.log);
```

---

## Quick troubleshooting checklist

### PHP not found

- Install PHP with Scoop
- Or install manually from official PHP Windows downloads
- Add PHP to `PATH`
- Open a new PowerShell window

### `pdo_pgsql` not found

- Run `php --ini`
- Edit the correct `php.ini`
- Enable:
  - `extension=pdo_pgsql`
  - `extension=pgsql`

- Confirm `extension_dir` is correct
- Confirm the DLL files exist in `ext`
- Restart PowerShell

### Server does not start

- Make sure you are in the project folder
- Confirm `server.php` exists
- Run:

```powershell
php -S localhost:8000 server.php
```

### Database connection fails

- Check `DATABASE_URL`
- Confirm your Supabase password is correct
- Confirm the table exists
- Confirm `pdo_pgsql` is loaded

---

## Recommended commands to paste when asking for help

If setup still fails, send these outputs to the group:

```powershell
php -v
php --ini
php -m | findstr PDO
php -m | findstr pgsql
where.exe php
```

That usually shows exactly what is broken.

---

## References

- PHP downloads and Windows binaries: php.net / windows.php.net ([php.net](https://www.php.net/downloads.php?utm_source=chatgpt.com))
- Scoop installer and PHP bucket: Scoop and ScoopInstaller PHP bucket ([scoop.sh](https://scoop.sh/?utm_source=chatgpt.com))
- PHP PostgreSQL and PDO PostgreSQL documentation: PHP manual ([php.net](https://www.php.net/manual/en/ref.pdo-pgsql.php?utm_source=chatgpt.com))
