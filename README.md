# Hear2Lead - Student Leadership & Organization Management System

**Live Production URL:** [https://hear2lead-live.vercel.app/](https://hear2lead-live.vercel.app/)

A full-featured **Native PHP 8+, HTML5, CSS3, Vanilla JavaScript (ES6), and Dual-Database (MySQL / Supabase PostgreSQL)** web application designed for school student leadership organizations (e.g., SSLG, YES-O, BKD, Academic & Campus Clubs).

---

## 🌟 Portals & Core Features

### 1. 🎓 Student Portal (`/student/index.php`)
Accessible to verified students on campus to:
- 🏆 **Accomplishments (`/student/accomplishments.php`)**: View student government and club achievements, photo galleries, PDF documents, and impact summaries.
- 📊 **Progress Tracker (`/student/progress.php`)**: Monitor real-time completion percentages (`0–100%`) and linked task milestones of active programs.
- 📁 **Projects & Programs (`/student/projects.php`)**: Browse past, ongoing, and planned leadership initiatives with status filters.
- 💬 **Verified Student Feedback (`/student/feedback.php`)**: Submit categorized suggestions, inquiries, or concerns to specific student organizations and engage in two-way conversation threads with officers.
- 🔐 **Account Security**:
  - **Self-Registration with CAPTCHA & Admin Approval**: New student accounts register with a 5-character security CAPTCHA and strong password validation, awaiting Administrator approval before login.
  - **Forgot Password Ticket Request**: Students can submit their LRN or registered email to generate a password reset ticket directly for the System Administrator.
  - **Change Password in Header**: Authenticated students can update their password anytime from the profile dropdown menu.

---

### 2. 🛡️ Leadership Portal (`/leadership/dashboard.php`)
Accessible to organization officers and administrators:
- 📊 **Task Organizer & Tracker (`/leadership/tasks.php`)**:
  - Real-time metrics for **Total Tasks**, **Accomplished**, **In Progress**, **To Do**, and **Overdue** (with automatic deadline synchronization via `syncOverdueTasks()`).
  - Multi-officer task assignment, priority badges, project linking, and immutable **Task Activity Audit Logs (`task_logs`)**.
- 🚀 **Programs & Projects (`/leadership/projects.php`)**: Create and manage organizational projects with `0–100%` progress sliders and project audit logs (`project_logs`).
- 🏆 **Accomplishments Manager (`/leadership/accomplishments.php`)**: Publish completed initiatives with multi-file image/PDF attachments and audit logs (`accomplishment_logs`).
- 💬 **Student Feedbacks Monitor (`/leadership/feedbacks.php`)**: Review student concerns, update statuses (*Pending*, *Under Review*, *In Action*, *Resolved*), and post official multi-reply responses (`feedback_logs`).
- 🔔 **Notifications & Activity Center (`/leadership/notifications.php`)**: Deadline reminders for assigned officers plus a global login and module activity monitor for Administrators.
- 📈 **Performance & Audit Reports (`/leadership/reports.php` & `/leadership/print_report.php`)**: Per-officer task accountability metrics, date-range filtering, 4-tab audit trail logs, and printable official reports.
- 👥 **User Management Module (`/leadership/users.php`)**:
  - **Tab 1 — Leaders & Officers Accounts**: Create, edit, and manage organization officers.
  - **Tab 2 — Student Accounts *(Admin Only)***: Approve, reject, create, or edit student accounts and inspect approval history (`student_logs`).
  - **Tab 3 — Role Library & System Accessibility *(Admin Only)***: Configure custom roles (`user_roles`) and granular module permissions.
  - **Tab 4 — Password Reset Tickets *(Admin Only)***: Review and resolve "Forgot Password" tickets submitted by students or officers (auto-generate or set a new password, or reject requests).
- 🏛️ **Organization Management (`/leadership/organizations.php`)**: *(Admin Only)* Manage campus organizations, acronyms, descriptions, and custom logos.

---

## 🛠️ Technology Stack & Deployment Connectivity

| Layer | Local Development (XAMPP) | Cloud Production (Vercel + Supabase) |
|---|---|---|
| **Web Server / Runtime** | Apache HTTP Server (XAMPP PHP 8.2+) | Vercel Serverless PHP (`vercel-php@0.7.3`) via `api/index.php` |
| **Database Engine** | MySQL / MariaDB (`student_leadership_db`) | Supabase PostgreSQL (Connection Pooler Port `6543`) |
| **Database Adapter** | PDO MySQL (`config/db.php`) | `SupabaseCompatPDO` (Auto-translates MySQL dialect to PostgreSQL) |
| **Session Storage** | Local Filesystem / MySQL `user_sessions` | Database-Backed Sessions (`DatabaseSessionHandler` in `user_sessions`) |
| **Frontend UI** | HTML5, CSS3, Vanilla JS, Font Awesome 6.4, SweetAlert2 | Served via Vercel Global Edge CDN (`assets/*`) |
| **Version Control / CI/CD** | Git & GitHub Desktop (`benejr27` & `tomandjerrynolastname-byte`) | Automatic Git Push Deployment to Vercel |

---

## 💻 Local Deployment (XAMPP)

1. Open **XAMPP Control Panel** and start **Apache** and **MySQL**.
2. Place the project folder inside `htdocs`:
   C:\xampp\htdocs\Hear2Lead
3. Open your browser and visit:

   http://localhost/Hear2Lead/
4. **Automatic Database Initialization**:
   - When no `DATABASE_URL` environment variable is set, `config/db.php` automatically connects to local MySQL (`127.0.0.1`, user `root`, database `student_leadership_db`), creates all required tables (`organizations`, `users`, `students`, `tasks`, `projects`, `accomplishments`, `feedbacks`, `password_reset_tickets`, and all `*_logs` tables), and seeds default data automatically.

---

## ☁️ Cloud Deployment (Vercel + Supabase + GitHub)

See **[`DEPLOYMENT_GUIDE.md`](DEPLOYMENT_GUIDE.md)** and **`Hear2Lead_Deployment_and_Connectivity_Guide.docx`** for the full step-by-step guide on how Local XAMPP, GitHub, Supabase PostgreSQL, and Vercel Serverless PHP connect and deploy.
