# PHP + Supabase REST API

A simple PHP REST API backed by Supabase PostgreSQL.

## Features

- User signup
- User login
- Get all users
- Get a single user by email
- Update a user by email
- Delete a user by email
- Root health check for database connectivity

## API Routes

| Method | Endpoint            | Description               |
| ------ | ------------------- | ------------------------- |
| GET    | `/`                 | Check database connection |
| POST   | `/signup`           | Create a new user         |
| POST   | `/login`            | Validate user credentials |
| GET    | `/users`            | Get all users             |
| GET    | `/users?email=...`  | Get one user by email     |
| PUT    | `/update`           | Update a user             |
| PATCH  | `/update`           | Update a user             |
| DELETE | `/delete?email=...` | Delete a user             |

## Requirements

- Windows 10 or 11
- PowerShell
- Git
- PHP
- A Supabase project

## 1) Clone the project

```powershell
git clone https://github.com/YOUR_USERNAME/YOUR_REPOSITORY.git
cd YOUR_REPOSITORY
```

## 2) Install PHP

### Recommended: Scoop

```powershell
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser
irm get.scoop.sh | iex
scoop bucket add php
scoop install php
```

Open a new PowerShell window, then verify:

```powershell
php -v
php --ini
```

### Manual install

If Scoop is unavailable:

1. Download a **Thread Safe x64 ZIP** build of PHP for Windows
2. Extract it to a folder such as:

```text
C:\php
```

3. Add `C:\php` to your system `Path`
4. Copy `php.ini-development` to `php.ini`
5. Verify:

```powershell
php -v
php --ini
```

## 3) Enable PostgreSQL extensions

This project needs:

- `PDO`
- `pdo_pgsql`
- `pgsql`

Check loaded extensions:

```powershell
php -m | findstr PDO
php -m | findstr pgsql
```

If needed, open your active `php.ini` and enable:

```ini
extension=pdo_pgsql
extension=pgsql
```

Then restart PowerShell and verify again.

## 4) Create the database table

Run this in the Supabase SQL Editor:

```sql
create table if not exists user_demo (
  user_id bigserial primary key,
  name text not null,
  email text not null unique,
  password text not null
);
```

## 5) Configure environment variables

Create a `.env` file in the same folder as `server.php`:

```env
DATABASE_URL=postgresql://postgres:YOUR_PASSWORD@db.YOUR_PROJECT.supabase.co:5432/postgres?sslmode=require
```

Replace `YOUR_PASSWORD` and `YOUR_PROJECT` with your real Supabase credentials.

## 6) Run the server

From the project folder:

```powershell
php -S localhost:8000 server.php
```

Open:

```text
http://localhost:8000
```

## Example Requests

### Root

```powershell
Invoke-RestMethod -Method Get -Uri http://localhost:8000/
```

### Signup

```powershell
Invoke-RestMethod -Method Post -Uri http://localhost:8000/signup `
  -ContentType "application/json" `
  -Body '{"name":"Ada","email":"ada@example.com","password":"secret123"}'
```

### Login

```powershell
Invoke-RestMethod -Method Post -Uri http://localhost:8000/login `
  -ContentType "application/json" `
  -Body '{"email":"ada@example.com","password":"secret123"}'
```

### Get all users

```powershell
Invoke-RestMethod -Method Get -Uri http://localhost:8000/users
```

### Get one user by email

```powershell
Invoke-RestMethod -Method Get -Uri "http://localhost:8000/users?email=ada@example.com"
```

### Update user

```powershell
Invoke-RestMethod -Method Put -Uri http://localhost:8000/update `
  -ContentType "application/json" `
  -Body '{"email":"ada@example.com","new-email":"ada.lovelace@example.com","new-name":"Ada Lovelace","new-password":"newsecret123"}'
```

### Delete user

```powershell
Invoke-RestMethod -Method Delete -Uri "http://localhost:8000/delete?email=ada@example.com"
```

## Response Notes

### Failed login

Invalid login returns HTTP `401 Unauthorized` with JSON like:

```json
{
  "success": false,
  "message": "incorrect email or password",
  "hint": "no account yet? go to /signup"
}
```

Because PowerShell throws on `401`, use `try/catch` if you want to inspect the response body:

```powershell
try {
    $response = Invoke-RestMethod -Method Post -Uri "http://localhost:8000/login" `
        -ContentType "application/json" `
        -Body '{"email":"ada@example.com","password":"wrong-password"}' `
        -ErrorAction Stop

    $response | ConvertTo-Json -Depth 10
}
catch {
    $reader = [System.IO.StreamReader]::new($_.Exception.Response.GetResponseStream())
    $body = $reader.ReadToEnd()
    $json = $body | ConvertFrom-Json
    $json | ConvertTo-Json -Depth 10
}
```

### Users endpoint

- `GET /users` returns all users
- `GET /users?email=...` returns one user
- Passwords are not included in responses

## Common status codes

- `200 OK` - successful request
- `201 Created` - successful signup
- `400 Bad Request` - missing or invalid input
- `401 Unauthorized` - invalid login
- `404 Not Found` - user or route not found
- `405 Method Not Allowed` - wrong HTTP method
- `409 Conflict` - duplicate email
- `500 Internal Server Error` - unexpected server error

## Troubleshooting

### PHP not found

```powershell
where.exe php
```

If nothing appears, PHP is not in `PATH`.

### PostgreSQL extensions missing

```powershell
php --ini
php -m | findstr PDO
php -m | findstr pgsql
```

Make sure `pdo_pgsql` and `pgsql` are enabled in the correct `php.ini`.

### Server will not start

Make sure you are in the project folder and `server.php` exists:

```powershell
php -S localhost:8000 server.php
```

### Database connection fails

Check:

- `.env` exists
- `DATABASE_URL` is correct
- Supabase credentials are valid
- `user_demo` table exists
- `pdo_pgsql` is loaded
