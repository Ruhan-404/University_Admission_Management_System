#  University Admission Management System
A full-stack web application that digitizes the complete university admission process. The system provides role-based dashboards for every stakeholder — from student registration to final enrollment — with automated step tracking, payment integration, and registration number generation.

---

##  Features

###  Authentication & User Management
- **Secure Access**: bcrypt password hashing with session-based authentication
- **Role-Based Access Control (RBAC)**: Separate login and dashboard for each role — Students, Teachers, Dean's Office, Register Office, Exam Controller, and Admin

###  Student Module
- **Registration**: GST roll verification before signup
- **Form Submission**: Personal details and photo upload
- **Live Progress Dashboard**: Real-time 7-step admission tracking
- **Payments**: Online payment via SSLCommerz or manual transaction ID submission
- **ID Card**: Digital registration number and ID card display after clearance

### Administrative Modules
- **Teacher Panel**: Department viva approval/rejection + payment transaction verification
- **Dean's Office**: Step 4 approval for all departments
- **Register Office**: Step 5 approval with auto-generated registration numbers (`REG-YYYY-DEPT-#####`)
- **Exam Controller**: Step 7 final clearance
- **Admin Panel**: Full student progress overview and manual payment approval

---

## Admission Workflow (7 Steps)

| Step | Title | Handled By |
|------|-------|------------|
| 1 | Form Submission | Student |
| 2 | Department Viva | Teacher |
| 3 | Bank Payment | Student + Teacher |
| 4 | Dean's Office | Dean |
| 5 | Register Office | Register Officer |
| 6 | IT / ID Card | Student (auto) |
| 7 | Exam Controller | Exam Controller |

---

## Tech Stack

- **Frontend**: HTML5, CSS3, Custom CSS (no framework), Font Awesome icons
- **Backend**: PHP 8 (pure, no framework), MySQLi with prepared statements
- **Database**: MySQL / MariaDB
- **Payment Gateway**: SSLCommerz (sandbox + live ready)
- **Server**: Apache (XAMPP locally / InfinityFree for deployment)


##  Project Structure

```text
admission/
├── css/                  # Global stylesheet
├── includes/             # db.php, header.php, footer.php
├── uploads/              # Student photo uploads
├── sql/                  # Database schema
├── teacher/              # Teacher login & panel
├── dean/                 # Dean login & panel
├── register/             # Register Office login & panel
├── exam/                 # Exam Controller login & panel
├── payment/              # SSLCommerz integration & manual verify
├── index.php             # Landing page
├── login.php             # Student login
├── signup.php            # Student registration
├── dashboard.php         # Student admission progress dashboard
├── form_submission.php   # Admission form
├── admin_login.php       # Admin login
└── admin_progress.php    # Admin panel
```

---

## Database

11 tables in total:

| Table | Purpose |
|-------|---------|
| `students` | Registered student accounts |
| `gst_rolls` | Pre-loaded GST roll numbers |
| `admission_steps` | Master list of 7 steps |
| `student_step_status` | Per-student step progress |
| `admission_forms` | Submitted form data |
| `payments` | Payment records |
| `teachers` | Teacher accounts |
| `admins` | Admin accounts |
| `deans` | Dean accounts |
| `register_office` | Register Office accounts |
| `exam_controller` | Exam Controller accounts |



## Deployment (InfinityFree)

 Visit https://ruhan.infinityfreeapp.com/index.php

