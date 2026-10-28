# Hear2Lead Deployment Guide (Supabase + Vercel + Git)

This guide walks you through deploying **Hear2Lead** to **Supabase** (PostgreSQL Database) and **Vercel** (Serverless Hosting), with automatic continuous deployment connected to your **GitHub** repository.

---

## Architecture Overview

- **Database**: Supabase PostgreSQL
- **Backend / Routing**: Vercel Serverless PHP (`vercel-php`) + `api/index.php` Router
- **Static Assets**: Vercel Edge Network (`assets/css`, `assets/js`, `assets/uploads`)
- **Version Control**: Git / GitHub (`https://github.com/benejr27/StudentLeadership.git`)

---

## Step 1: Set Up Supabase Database

1. Go to [Supabase](https://supabase.com) and log in (or sign up).
2. Click **New Project**:
   - **Name**: `Hear2Lead` (or any name you prefer)
   - **Database Password**: Set a strong password (save this safely!)
   - **Region**: Choose the region closest to your users (e.g., `Singapore (ap-southeast-1)` or `US East`).
   - Click **Create new project**.

3. Once your project is created, open the **SQL Editor** from the left sidebar.
4. Click **New Query**, open the `supabase_schema.sql` file in your repository, and copy all its contents.
5. Paste the SQL into the Supabase SQL Editor and click **Run**.
   - This creates all required tables (`organizations`, `users`, `students`, `tasks`, `projects`, `accomplishments`, `feedbacks`, `user_sessions`, audit logs, etc.).
   - It also populates the initial seed data (default organizations, admin officer, demo students, projects, and tasks).

6. Retrieve your Supabase Connection String:
   - Go to **Project Settings** (gear icon) -> **Database**.
   - Scroll down to **Connection parameters** or **Connection string**.
   - Under **Connection string**, select **URI**.
   - Copy the string. It will look like this:
     ```text
     postgresql://postgres.[your-project-ref]:[YOUR-PASSWORD]@aws-0-[region].pooler.supabase.com:6543/postgres
     ```
   - Replace `[YOUR-PASSWORD]` with the actual database password you created in step 2.

---

## Step 2: Deploy to Vercel

1. Log in to [Vercel](https://vercel.com).
2. Click **Add New...** -> **Project**.
3. Under **Import Git Repository**, select your repository:
   - `benejr27/StudentLeadership`
4. In the Project Configuration screen:
   - **Framework Preset**: Leave as *Other*.
   - **Root Directory**: Leave as `./` (root).
5. Expand the **Environment Variables** section and add:

   | Key | Value | Notes |
   |---|---|---|
   | `DATABASE_URL` | `postgresql://postgres.[project-ref]:[password]@aws-0-[region].pooler.supabase.com:6543/postgres` | Your Supabase pooled connection string (port `6543`) |
   | `DB_CONNECTION` | `pgsql` | Tells Hear2Lead to use the PostgreSQL driver |

6. Click **Deploy**.
7. Vercel will build the project using `vercel-php@0.7.3` and provide a live URL (e.g., `https://student-leadership.vercel.app`).

---

## Step 3: Default Login Credentials

After deploying and seeding the database with `supabase_schema.sql`:

### 1. Leadership / Administrator Portal (`/leadership/login.php`)
- **Username**: `admin`
- **Password**: `password` (or the hash from your original database)

### 2. Supreme Secondary Learner Government Leader (`/leadership/login.php`)
- **Username**: `maria_sslg`
- **Password**: Same as default password hash or reset via admin

### 3. Student Portal (`/student/login.php`)
- **LRN**: `2026-0001`
- **Password**: `student123`

---

## Local Development (XAMPP MySQL)

Your local development environment remains 100% functional. When no `DATABASE_URL` or `DB_CONNECTION=pgsql` is set, Hear2Lead automatically connects to your local XAMPP MySQL database (`127.0.0.1`, user `root`, database `student_leadership_db`).
