# ISLAMIA COLLEGE OF TECHNOLOGY (ICT) - DATABASE DOCUMENTATION

## 1. System Overview
* **System Name**: Islamia College of Technology Management System
* **Database Engine**: InnoDB
* **Character Set**: `utf8mb4`
* **Collation**: `utf8mb4_unicode_ci`
* **Platform Compatibility**: XAMPP, phpMyAdmin, MySQL 8.0+, MariaDB 10.4+, PHP 7.4 - 8.3+

The database manages complete student lifecycles, course catalogs, batch cohorts, fee collection ledgers, board registrations (Trade Testing Board KPK), exam results, staff payroll, campus building rent expenses, capital assets (solar systems), loans/advances, and administrative user security.

---

## 2. Table Specifications & Data Dictionary

### 2.1 `departments`
Stores academic and vocational divisions.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `dept_name`: VARCHAR(100) NOT NULL UNIQUE
* `dept_code`: VARCHAR(20) NOT NULL UNIQUE
* `description`: VARCHAR(255) NULL
* `created_at`: TIMESTAMP

### 2.2 `courses`
Master catalog of academic, vocational, technical, language, and safety courses.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `course_code`: VARCHAR(50) NOT NULL UNIQUE
* `course_name`: VARCHAR(150) NOT NULL
* `category`: VARCHAR(100) (Information Technology, Languages, Vocational, Safety, School Academics)
* `duration`: VARCHAR(50) (e.g., '1 Year / 2 Semesters', '3 Months', '2 Weeks')
* `standard_fee`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `ttb_exam_fee`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `description`: TEXT NULL

### 2.3 `batches`
Specific academic cohorts and intake sessions.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `batch_code`: VARCHAR(50) NOT NULL UNIQUE (e.g., 'ELL-B25', 'DIT-B7', 'CIT-B1')
* `batch_name`: VARCHAR(150) NOT NULL
* `course_id`: INT NOT NULL (FK -> `courses.id`)
* `start_date`: DATE NULL
* `end_date`: DATE NULL
* `status`: ENUM('active', 'completed', 'upcoming') NOT NULL DEFAULT 'active'

### 2.4 `students`
Centralized demographic and identity profiles of enrolled students.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `student_reg_no`: VARCHAR(50) NOT NULL UNIQUE (e.g., 'ICT-DIT-411', 'ELL-2411')
* `full_name`: VARCHAR(150) NOT NULL
* `father_name`: VARCHAR(150) NULL
* `contact_number`: VARCHAR(30) NULL
* `secondary_contact`: VARCHAR(30) NULL
* `cnic`: VARCHAR(25) NULL
* `date_of_birth`: DATE NULL
* `email`: VARCHAR(100) NULL
* `address`: VARCHAR(255) NULL (e.g., Matta, Sambat, Shawar, Bamakhela)
* `created_at`: TIMESTAMP
* `updated_at`: TIMESTAMP

### 2.5 `enrollments`
Junction table tracking student enrollment per course batch.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `student_id`: INT NOT NULL (FK -> `students.id`)
* `batch_id`: INT NOT NULL (FK -> `batches.id`)
* `roll_number`: VARCHAR(50) NULL
* `enrollment_date`: DATE NULL
* `total_agreed_fee`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `admission_fee`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `discount_amount`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `receipt_slip_no`: VARCHAR(50) NULL
* `tuition_class`: VARCHAR(50) NULL
* `tuition_subjects`: VARCHAR(200) NULL
* `status`: ENUM('active', 'completed', 'dropped') NOT NULL DEFAULT 'active'

### 2.6 `fee_transactions`
Ledger of student fee installments, receipts, and outstanding dues.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `enrollment_id`: INT NOT NULL (FK -> `enrollments.id`)
* `semester`: TINYINT DEFAULT 1
* `installment_no`: TINYINT DEFAULT 1
* `amount_paid`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `due_amount`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `payment_date`: DATE NULL
* `payment_mode`: ENUM('cash', 'bank', 'slip', 'other') NOT NULL DEFAULT 'cash'
* `receipt_voucher_no`: VARCHAR(50) NULL
* `received_by`: VARCHAR(100) NULL (e.g., 'FARAZ SEB', 'Irfan sir')
* `remarks`: VARCHAR(255) NULL

### 2.7 `board_examinations`
Registration and performance tracking for Trade Testing Board (TTB) Peshawar.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `student_id`: INT NOT NULL (FK -> `students.id`)
* `board_name`: VARCHAR(100) DEFAULT 'Trade Testing Board (TTB) Peshawar'
* `board_registration_no`: VARCHAR(100) NULL
* `board_roll_no`: VARCHAR(50) NULL
* `trade_course_name`: VARCHAR(150) NOT NULL
* `registration_date`: DATE NULL
* `exam_fee_paid`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `exam_status`: ENUM('enrolled', 'pass', 'fail', 'absent') NOT NULL DEFAULT 'enrolled'
* `marks_obtained`: DECIMAL(6,2) NULL
* `total_marks`: DECIMAL(6,2) NULL

### 2.8 `academic_positions`
Top merit awards and rank achievements in board/session exams.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `student_id`: INT NULL (FK -> `students.id`)
* `session_name`: VARCHAR(100) NOT NULL (e.g., 'Spring Sem-2 2024')
* `batch_name`: VARCHAR(100) NOT NULL
* `position_rank`: VARCHAR(50) NOT NULL ('1st', '2nd', '3rd', 'Appreciation')
* `student_name`: VARCHAR(150) NOT NULL
* `marks_obtained`: INT NULL
* `total_marks`: INT NULL
* `award_title`: VARCHAR(200) NULL

### 2.9 `staff_members`
Faculty, instructors, administrative officers, and proctors.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `staff_code`: VARCHAR(50) NOT NULL UNIQUE
* `full_name`: VARCHAR(150) NOT NULL
* `designation`: VARCHAR(100) NOT NULL
* `phone`: VARCHAR(30) NULL
* `base_salary`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `is_active`: BOOLEAN DEFAULT TRUE

### 2.10 `staff_payroll`
Monthly salary payments, multiple installments, and dues.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `staff_id`: INT NOT NULL (FK -> `staff_members.id`)
* `month_name`: VARCHAR(30) NOT NULL
* `year_num`: INT NOT NULL
* `salary_agreed`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `installment_p1`: DECIMAL(10,2) DEFAULT 0.00
* `date_p1`: DATE NULL
* `installment_p2`: DECIMAL(10,2) DEFAULT 0.00
* `date_p2`: DATE NULL
* `installment_p3`: DECIMAL(10,2) DEFAULT 0.00
* `date_p3`: DATE NULL
* `total_paid`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `dues`: DECIMAL(10,2) NOT NULL DEFAULT 0.00

### 2.11 `campus_expenses`
Campus rental obligations and operational utilities.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `expense_type`: ENUM('rent', 'utility', 'maintenance', 'other') DEFAULT 'rent'
* `month_name`: VARCHAR(30) NOT NULL
* `year_num`: INT NOT NULL
* `amount_due`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `amount_paid`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `payment_date`: DATE NULL
* `balance_dues`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `payee_name`: VARCHAR(150) DEFAULT 'Campus Landlord'
* `notes`: TEXT NULL

### 2.12 `capital_assets` & `asset_payment_installments`
College solar energy system and capital infrastructure.
* `capital_assets`: `id`, `asset_name`, `specification`, `units_count`, `total_cost`, `total_paid`, `dues`, `purchase_date`, `vendor_name`, `status`
* `asset_payment_installments`: `id`, `asset_id` (FK), `payment_date`, `amount_paid`, `remarks`

### 2.13 `loans_and_advances`
Institutional receivables, staff advances, and community loans (Qarz-e-Hasana).
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `borrower_name`: VARCHAR(150) NOT NULL
* `amount_disbursed`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `amount_repaid`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `balance_due`: DECIMAL(10,2) NOT NULL DEFAULT 0.00
* `purpose`: VARCHAR(200) NULL
* `disbursement_date`: DATE NULL
* `status`: ENUM('pending', 'partially_paid', 'settled') NOT NULL DEFAULT 'pending'

### 2.14 `users`
Application authentication, RBAC, and user credentials.
* `id`: INT AUTO_INCREMENT PRIMARY KEY
* `username`: VARCHAR(50) NOT NULL UNIQUE
* `password_hash`: VARCHAR(255) NOT NULL
* `full_name`: VARCHAR(100) NOT NULL
* `role`: ENUM('admin', 'accountant', 'registrar', 'staff') DEFAULT 'staff'
* `is_active`: BOOLEAN DEFAULT TRUE

---

## 3. How to Import and Run

### 3.1 Via phpMyAdmin (XAMPP)
1. Open XAMPP Control Panel and start **Apache** and **MySQL**.
2. Navigate to `http://localhost/phpmyadmin/`.
3. Click on the **Import** tab.
4. Select `database.sql` located at `c:\xampp\htdocs\islamia college\database.sql`.
5. Click **Go**. The database `islamia_college_db` will be created with all 15 tables and data.

### 3.2 Via MySQL Command Line
```bash
c:\xampp\mysql\bin\mysql.exe -u root -p < "c:\xampp\htdocs\islamia college\database.sql"
```

### 3.3 Default Administrator Accounts
* **Initial Superuser**: `admin`
* **Accountant**: `accountant`
* **Registrar**: `registrar`
* Note: Passwords are encrypted with bcrypt (`PASSWORD_BCRYPT`). Maintain credentials securely in accordance with institutional policy.
