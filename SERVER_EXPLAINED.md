# How server.php Works — A Beginner's Guide

This document explains every part of `server.php` in plain English, assuming no prior PHP knowledge.

---

## What Is This File?

`server.php` is the **entire backend** of this application. When someone (a browser, a mobile app, a client script) sends an HTTP request to this server, PHP runs this single file from top to bottom. It reads the request, talks to a database, and writes a JSON response back.

Think of it like a receptionist: it receives every call, figures out what the caller wants, goes to the filing cabinet (database), and reads back an answer.

---

## The Big Picture — Request Lifecycle

```
Client (browser/app)
      |
      | HTTP Request (GET /users, POST /login, etc.)
      v
   server.php
      |
      |-- 1. Send CORS headers
      |-- 2. Load environment variables (.env)
      |-- 3. Connect to the database
      |-- 4. Read the URL path and HTTP method
      |-- 5. Route to the right handler function
      |-- 6. Run the database query
      |-- 7. Send back a JSON response
      |
      v
Client receives JSON
```

---

## Part 1 — `loadEnv()` (Lines 5–32)

### What it does
Reads a hidden file called `.env` that stores sensitive configuration — like the database password — so it never has to be hardcoded in the source code.

### Step by step
1. Checks that the `.env` file actually exists on disk. If not, it throws an error and stops.
2. Reads the file line by line into an array called `$lines`.
3. Loops through every line:
   - Skips blank lines and lines that start with `#` (those are comments).
   - Splits each line at the `=` sign. For example `DATABASE_URL=postgres://...` becomes key `DATABASE_URL` and value `postgres://...`.
   - Strips surrounding quotes from the value (e.g. `"mypassword"` → `mypassword`).
   - Stores the key/value pair so the rest of the code can read it with `$_ENV['KEY']`.

### Example `.env` line
```
DATABASE_URL=postgres://user:pass@host:5432/mydb?sslmode=require
```

---

## Part 2 — URL & Request Helpers (Lines 34–72)

PHP gives you information about the incoming HTTP request through special global arrays like `$_SERVER`, `$_GET`, and `$_POST`. These helper functions wrap that raw data into clean, reusable pieces.

### `normalizeRequestPath()` (Line 34)
Cleans up URL paths. Removes a trailing slash so `/users/` and `/users` are treated the same. If the path is completely empty, it returns `/`.

### `getRequestPath()` (Line 41)
Reads the full URL from the request (e.g. `http://myserver.com/users?email=x`), strips the query string, and returns only the path part: `/users`.

### `getRequestMethod()` (Line 49)
Returns the HTTP verb in uppercase: `GET`, `POST`, `PUT`, `PATCH`, or `DELETE`. This tells us *what action* the client wants to perform.

### `getRequestData()` (Line 54)
Reads the **body** of the request. When a client sends a `POST` request with JSON like `{"email":"x","password":"y"}`, this function reads that raw text from `php://input` (a special PHP stream), parses it as JSON, and returns a PHP array. If there is no JSON body, it falls back to the traditional HTML form data in `$_POST`.

### `getRequestInput()` (Line 69)
Combines URL query parameters (`$_GET`, the `?key=value` part of the URL) with the request body data. This way one function gives you all inputs regardless of where they came from.

---

## Part 3 — Database Connection (Lines 74–115)

### `getDatabaseUrl()` (Line 74)
Looks in three places for the `DATABASE_URL` value (environment variables, server variables, system environment) and returns whichever one it finds.

### `createDatabasePdo()` (Line 81)
Creates and returns a **PDO** object — PHP's built-in database connector. PDO stands for *PHP Data Objects*. Think of it as the phone line between this script and the PostgreSQL database.

Step by step:
1. Gets the database URL string (e.g. `postgres://user:pass@db.supabase.co:5432/postgres?sslmode=require`).
2. Parses that URL into its parts: host, port, username, password, database name, SSL mode.
3. Builds a DSN (Data Source Name) — a formatted connection string PostgreSQL understands.
4. Opens the connection with `new PDO(...)`.
5. Configures PDO to throw exceptions on errors and return results as associative arrays (key → value pairs).

---

## Part 4 — Response Helpers (Lines 117–130)

### `respondJson()` (Line 117)
Sends an HTTP response back to the client.
1. Sets the HTTP status code (200 = OK, 404 = Not Found, 500 = Server Error, etc.).
2. Sets the `Content-Type` header to `application/json` so the client knows what format to expect.
3. Converts the PHP array to a JSON string and prints it — this becomes the response body.

### `sendCorsHeaders()` (Line 124)
CORS (Cross-Origin Resource Sharing) is a browser security rule that blocks web pages from calling APIs on a different domain unless the server explicitly allows it. This function sends four headers that say:
- `Allow-Origin: *` — any website can call this API.
- `Allow-Methods` — these HTTP methods are permitted.
- `Allow-Headers` — clients may send `Content-Type` and `Accept` headers.
- `Max-Age` — browsers can cache these permissions for 24 hours (86400 seconds).

---

## Part 5 — Password Helper (Lines 132–141)

### `getRawPasswordMatch()` (Line 132)
Handles two password storage formats that may exist in the database:

- **Hashed passwords** (modern, secure): stored as a long scrambled string like `$2y$10$...`. Uses `password_verify()` to safely compare.
- **Plain text passwords** (legacy, insecure): uses `hash_equals()` for a timing-safe comparison that prevents a type of attack called a *timing attack*.

The function automatically detects which format the stored password is in via `password_get_info()`.

---

## Part 6 — Business Logic Functions (Lines 143–407)

These functions each handle one specific API operation.

---

### `loginUser()` — POST /login (Lines 143–186)

**What it does:** Checks if an email/password pair is valid.

1. Reads `email` and `password` from the request input.
2. Returns an error if either is empty.
3. Runs a SQL query: `SELECT ... FROM user_demo WHERE email = :email`. The `:email` is a *prepared statement placeholder* — it prevents SQL injection attacks by never directly inserting user input into the SQL string.
4. If no row is found → returns "email not found".
5. Compares the submitted password against the stored (possibly hashed) password using `getRawPasswordMatch()`.
6. If they don't match → returns "incorrect password".
7. If everything checks out → returns `success: true`.

---

### `signupUser()` — POST /signup (Lines 188–237)

**What it does:** Creates a new user account.

1. Reads `name`, `email`, and `password` from the request.
2. Returns an error if any field is missing.
3. **Hashes the password** with `password_hash()` using PHP's default algorithm (bcrypt). Passwords are *never* stored in plain text.
4. Runs an `INSERT INTO user_demo ...` SQL query.
5. If the email already exists, PostgreSQL raises a `23505` unique constraint violation. The `catch` block intercepts this and returns a friendly "email already registered" error instead of crashing.
6. On success → returns `success: true`.

---

### `updateUser()` — PUT/PATCH /update (Lines 239–326)

**What it does:** Updates an existing user's name, email, and password.

1. Reads the current `email` (to identify the user) and the new values: `new-email`, `new-name`, `new-password`.
2. Returns an error if the current email is missing.
3. Looks up the user by their current email to get their `user_id`. Returns "user not found" if no match.
4. Returns an error if any of the new values are missing.
5. Hashes the new password.
6. Runs `UPDATE user_demo SET ... WHERE user_id = :user_id RETURNING user_id, name, email`. The `RETURNING` clause (PostgreSQL-specific) gives back the updated row immediately.
7. Catches duplicate email errors the same way `signupUser` does.
8. Returns the updated user data on success.

---

### `deleteUser()` — DELETE /delete (Lines 328–355)

**What it does:** Permanently removes a user from the database.

1. Reads `email` from the request.
2. Runs `DELETE FROM user_demo WHERE email = :email RETURNING user_id, email`.
3. If no row was deleted (email didn't exist) → returns "user not found".
4. Returns the deleted user's ID and email as confirmation.

---

### `getUsers()` — GET /users (Lines 357–396)

**What it does:** Retrieves one user or all users.

- **If `?email=...` is in the URL:** runs a `SELECT` with a `WHERE email = :email` clause and returns that one user.
- **If no email is given:** runs `SELECT ... FROM user_demo ORDER BY user_id ASC` and returns every user in the table as an array, plus a `count`.

Note: passwords are **never** included in the SELECT — only `user_id`, `name`, and `email`.

---

### `handleRoot()` — GET / (Lines 398–407)

**What it does:** A simple health check. Runs `SELECT NOW()` to ask the database for its current timestamp. If this succeeds, it proves the database connection is alive.

---

## Part 7 — The Main Router (Lines 409–528)

This is where execution actually starts. All the functions above are just definitions — nothing runs until PHP reaches this block.

```
try {
    1. Send CORS headers
    2. If OPTIONS request → respond 204 and stop
    3. Load .env
    4. Connect to database
    5. Read URL path and HTTP method
    6. Match path to a handler
    7. Call handler, send response
} catch (Throwable $e) {
    // If anything threw an error at any point, send a 500 JSON error
}
```

### Step-by-step walkthrough

**Step 1 — CORS headers** (Line 410)  
The very first thing every response does, before anything else, is send CORS headers. This is important because even error responses need them.

**Step 2 — OPTIONS preflight** (Lines 412–415)  
Browsers send an `OPTIONS` request before a real request to check if the server allows it (a "preflight check"). The server responds with `204 No Content` and stops — no database work needed.

**Step 3 — Load .env** (Line 417)  
Reads the `.env` file so `DATABASE_URL` and other secrets are available.

**Step 4 — Connect to the database** (Line 419)  
Creates the PDO connection. If this fails (bad credentials, network issue), the `catch` block at the bottom returns a 500 error.

**Step 5 — Read path and method** (Lines 420–421)  
Captures what the client is asking for, e.g. path = `/login`, method = `POST`.

**Steps 6 & 7 — Route matching** (Lines 423–517)  
A series of `if` statements checks the path. The first one that matches runs the corresponding handler:

| Path | Method(s) | Handler |
|---|---|---|
| `/users` | GET | `getUsers()` |
| `/login` | POST | `loginUser()` |
| `/signup` | POST | `signupUser()` |
| `/update` | PUT, PATCH | `updateUser()` |
| `/delete` | DELETE | `deleteUser()` |
| `/` | any | `handleRoot()` |
| anything else | any | 404 "Route not found" |

For each route, if the HTTP method is wrong (e.g. sending a `GET` to `/login`), the server responds with `405 Method Not Allowed` and an `Allow` header telling the client which methods are valid.

**Error boundary — `catch (Throwable $e)`** (Lines 523–527)  
`Throwable` catches every possible PHP error or exception. If anything inside the `try` block fails — a database error, a null reference, anything — execution jumps here and a `500 Internal Server Error` JSON response is sent with the error message.

---

## Summary Table — API Endpoints

| Endpoint | Method | Required Fields | What Happens |
|---|---|---|---|
| `/` | GET | — | Returns DB server time (health check) |
| `/users` | GET | `email` (optional) | Returns one or all users |
| `/login` | POST | `email`, `password` | Validates credentials |
| `/signup` | POST | `name`, `email`, `password` | Creates a new user |
| `/update` | PUT or PATCH | `email`, `new-email`, `new-name`, `new-password` | Updates user record |
| `/delete` | DELETE | `email` | Deletes user permanently |

---

## Key Security Practices Used

| Practice | Where | Why |
|---|---|---|
| Prepared statements (`:email`) | All SQL queries | Prevents SQL injection |
| `password_hash()` | signup, update | Passwords are never stored in plain text |
| `hash_equals()` | login (plain-text fallback) | Prevents timing attacks |
| Passwords excluded from SELECT | `getUsers()` | Passwords are never exposed via the API |
| `.env` file for secrets | `loadEnv()` | Keeps credentials out of source code |
