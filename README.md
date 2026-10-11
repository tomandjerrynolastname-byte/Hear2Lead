# Hear2Lead - Student Leadership System

A complete Native PHP, HTML, CSS, JavaScript, and MySQL application for school student leadership organizations (e.g., SSLG, YES-O, CSS).

---

## 🌟 Portals & Key Features

### 1. 🎓 Student Portal (`/student/index.php`)
Publicly accessible portal for all students in the campus to:
- 🏆 **Accomplishments**: View student government & club achievements, milestone summaries, and impact metrics.
- 📊 **Progress Tracker**: Monitor real-time completion percentages (0–100%) and timeline targets of active programs.
- 📁 **Projects & Programs**: Browse the catalog of past, present, and upcoming leadership activities with status filters.
- 💬 **Suggestions & Feedbacks**: Submit ideas, inquiries, or feedback directly to student leaders (supports **Anonymous submission**) and view published officer responses.

---

### 2. 🛡️ Leadership Portal (`/leadership/login.php` & `/leadership/dashboard.php`)
Accessible strictly by organization officers (e.g., SSLG, YES-O, CSS officers) to organize tasks and monitor student feedback:
- 📊 **Task Organizer & Tracker**: Real-time counter metrics matching requested layout:
  - **Total Tasks**: Counter of all tasks
  - **Accomplished**: Count & quick list of completed tasks
  - **To Do**: Pending task queue
  - **Overdue**: Automatically flags tasks past deadline
  - **Deadlines**: Calendar date-badge list of upcoming task deadlines
- 🛠️ **Task CRUD Management**: Create, edit, assign officers, set priorities (High, Medium, Low), and set deadline dates.
- 💬 **Student Portal Feedback Monitor**: Review student feedback sent to their specific organization, change status (*Under Review*, *In Action*, *Resolved*), and write official responses displayed on the Student Portal.
- 🚀 **Projects & Accomplishments Manager**: Add/update active project progress bars and post new accomplishments.

---

## 🔑 Demo Credentials (Officer Login)

| Portal | Username | Password | Role / Organization |
|---|---|---|---|
| **Leadership Portal** | `sslg_leader` | `admin123` | SSLG President |
| **Leadership Portal** | `sslg_officer` | `admin123` | SSLG Secretary |
| **Leadership Portal** | `yeso_leader` | `admin123` | YES-O President |

---

## 🚀 XAMPP Setup & Installation Guide

1. Ensure **XAMPP Apache & MySQL** services are running.
2. Place the project folder in `htdocs`:
   `d:\xampp_8_2_12\htdocs\Hear2Lead`
3. Open your web browser and navigate to:
   `http://localhost/Hear2Lead/`
4. The system **auto-initializes** the MySQL database `student_leadership_db` and seeds tables automatically upon first visit! *(Optional: You can also manually import `database.sql` into phpMyAdmin if desired).*
