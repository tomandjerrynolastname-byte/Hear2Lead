# Hear2Lead — Complete Deployment & Connectivity Guide (Local XAMPP + Vercel + Supabase + GitHub)

**Live Production URL:** [https://hear2lead-live.vercel.app/](https://hear2lead-live.vercel.app/)

This document explains **what tools and technologies are used** to run **Hear2Lead** both locally and in the cloud, **how each component connects**, and **how to deploy updates**.

---

## 1. Technologies & Platforms Used for Deployment

| Component | Local Environment | Cloud Production Environment | Purpose |
|---|---|---|---|
| **Application Runtime** | XAMPP Apache + PHP 8.2+ | **Vercel Serverless Functions** (`vercel-php@0.7.3`) | Executes PHP scripts (`index.php`, `student/*.php`, `leadership/*.php`, `includes/*.php`) |
| **Request Router** | Apache Direct File Serving | **`vercel.json` + `api/index.php`** | Routes incoming HTTP requests to the matching `.php` file or static asset |
| **Database Server** | Local MySQL / MariaDB (`3306`) | **Supabase PostgreSQL** (Pooler Port `6543`) | Stores organizations, officers, students, projects, tasks, accomplishments, feedbacks, password reset tickets, and audit logs |
| **Database Compatibility Layer** | `config/db.php` (PDO MySQL) | **`SupabaseCompatPDO` (`config/db.php`)** | Automatically detects environment variables and translates MySQL queries (`DATE_FORMAT`, `CURDATE()`, `IFNULL`, backticks) into PostgreSQL syntax at runtime |
| **Session Management** | PHP Native Session | **`DatabaseSessionHandler` (`user_sessions` table)** | Persists login & CAPTCHA sessions across stateless Vercel serverless function invocations |
| **Source Control & CI/CD** | **Git + GitHub Desktop** | **GitHub (`benejr27/Hear2Lead-1` & `tomandjerrynolastname-byte/Hear2Lead`)** | Dual-remote Git push (`origin` pushes to both repositories simultaneously) triggering automatic Vercel builds |

---

## 2. How the Connectivity Works (Architecture Flow)

```text
[ User Browser ]
       │
       ├──► Local: http://localhost/Hear2Lead/
       │      └──► Apache Web Server (XAMPP)
       │             └──► config/db.php (Detects no DATABASE_URL -> Uses Local MySQL)
       │                    └──► MySQL Database (127.0.0.1:3306 / student_leadership_db)
       │
       └──► Cloud: https://hear2lead-live.vercel.app/
              └──► Vercel Edge Network (vercel.json)
                     ├──► /assets/* ──► Served directly as static CSS/JS/Images
                     └──► /*        ──► Routed to Serverless Entrypoint: api/index.php
                                          └──► Loads target PHP file (e.g. leadership/users.php)
                                                 └──► config/db.php (Detects DATABASE_URL / DB_CONNECTION=pgsql)
                                                        ├──► SupabaseCompatPDO (Connects via SSL to Supabase Pooler)
                                                        ├──► DatabaseSessionHandler (Stores $_SESSION in user_sessions)
                                                        └──► Supabase PostgreSQL Database (Port 6543)
```

### Key Connectivity Files in the Codebase:
1. **[`vercel.json`](vercel.json)**:
   - Configures the Vercel serverless PHP runtime (`vercel-php@0.7.3`) on `api/index.php`.
   - Routes `/assets/(.*)` directly to `/assets/$1` for fast static delivery and forwards all dynamic routes `/(.*)` to `/api/index.php`.
2. **[`api/index.php`](api/index.php)**:
   - Acts as the Serverless Front Controller on Vercel.
   - Parses `$_SERVER['REQUEST_URI']`, resolves the target PHP file in the project root (`/index.php`, `/student/*.php`, `/leadership/*.php`, `/includes/*.php`), sets the working directory (`chdir`), and executes the script seamlessly.
3. **[`config/db.php`](config/db.php)**:
   - **Auto-Detection**: Checks for `DATABASE_URL` or `DB_CONNECTION=pgsql` environment variables.
   - **If Cloud (`pgsql`)**: Parses the `DATABASE_URL` URI and connects to Supabase PostgreSQL with `sslmode=require`. Uses `SupabaseCompatPDO` to translate MySQL SQL functions (`DATE_FORMAT`, `CURDATE()`, `NOW()`, `LAST_INSERT_ID()`) into PostgreSQL equivalents automatically, and registers `DatabaseSessionHandler` so `$_SESSION` (logins and CAPTCHAs) works reliably across serverless instances. Also auto-provisions new tables such as `password_reset_tickets`.
   - **If Local (`mysql`)**: Connects to `127.0.0.1` (`root`), auto-creates `student_leadership_db` if missing, imports `database.sql`, and runs automatic schema migrations.

---

## 3. How to Deploy Locally (XAMPP)

1. Install and open **XAMPP Control Panel**.
2. Start **Apache** and **MySQL**.
3. Place the project in:
   C:\xampp\htdocs\Hear2Lead
4. Open your browser and go to:
   http://localhost/Hear2Lead/
5. **Zero-Config Database Setup**:
   - On the first page load, `config/db.php` automatically creates the `student_leadership_db` MySQL database, initializes all tables (including `password_reset_tickets`, `user_roles`, `student_logs`, `login_logs`, etc.), and seeds the default organizations and accounts.

---

## 4. How to Deploy to Cloud (Supabase + Vercel + GitHub)

### Step 1: Set Up Supabase PostgreSQL Database
1. Log in to [Supabase](https://supabase.com) under your deployment account (`tomandjerrynolastname@gmail.com`).
2. Create a project (`Hear2Lead`) and open the **SQL Editor**.
3. Copy the contents of [`supabase_schema.sql`](supabase_schema.sql), paste into the SQL Editor, and click **Run**.
4. Go to **Project Settings** → **Database** → **Connection string** → select **URI** (Transaction Pooler, port `6543`):
   ```text
   postgresql://postgres.[project-ref]:[YOUR-PASSWORD]@aws-0-[region].pooler.supabase.com:6543/postgres
   ```
  
### Step 2: Configure Vercel Project
1. Log in to [Vercel](https://vercel.com) (`tomandjerrynolastname-byte`).
2. Import the GitHub repository **`tomandjerrynolastname-byte/Hear2Lead`**.
3. Under **Settings** → **Environment Variables**, configure:

   | Key | Value |
   |---|---|
   | `DATABASE_URL` | `postgresql://postgres.[project-ref]:[password]@aws-0-[region].pooler.supabase.com:6543/postgres` |
   | `DB_CONNECTION` | `pgsql` |

4. Vercel builds the project using `vercel.json` and publishes it at:
   **[https://hear2lead-live.vercel.app/](https://hear2lead-live.vercel.app/)**

### Step 3: Dual-Repository Git Push Workflow (`benejr27` ➔ `tomandjerrynolastname-byte` ➔ Vercel)
Your local Git configuration (`.git/config`) is configured with **dual push URLs** under `origin`:
```ini
[remote "origin"]
	url = https://github.com/benejr27/Hear2Lead-1.git
	pushurl = https://github.com/benejr27/Hear2Lead-1.git
	pushurl = https://github.com/tomandjerrynolastname-byte/Hear2Lead.git
	fetch = +refs/heads/*:refs/remotes/origin/*
[user]
	name = tomandjerrynolastname-byte
	email = tomandjerrynolastname@gmail.com
```
- **`benejr27`** is added as a Collaborator on **`tomandjerrynolastname-byte/Hear2Lead`**.
- Whenever you commit and click **Push origin** in **GitHub Desktop**, Git automatically pushes your code to **both**:
  1. `https://github.com/benejr27/Hear2Lead-1.git`
  2. `https://github.com/tomandjerrynolastname-byte/Hear2Lead.git`
- As soon as the push lands on `tomandjerrynolastname-byte/Hear2Lead`, **Vercel automatically builds and updates `https://hear2lead-live.vercel.app/`** within ~20–30 seconds!
