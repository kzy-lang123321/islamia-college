# EXCEL WORKBOOK TO RELATIONAL DATABASE MAPPING

**Source Workbook**: `ICT COMPLETE Budget 2018-26.xlsx`  
**Target Database**: `islamia_college_db` (MySQL 8.0+ / MariaDB 10.4+)  
**Institution**: Islamia College of Technology (ICT), Matta Swat, Khyber Pakhtunkhwa  

---

## 1. Overview of Mapping Strategy

The source workbook contains 63 sheets with over 2,160 operational and financial records spanning 2018 through 2026. The original Excel structure was organized with individual sheets representing course cohorts (e.g., `ELL01` to `ELL-B25`, `DIT-B1` to `DIT-B7`, `ICT-CIT-B1` to `ICT-CIT-B5`), board registration rosters, employee salary sheets, building rent ledgers, asset logs, and loan accounts.

Rather than creating 63 flat, disjoint tables, the data is normalized into **15 production-ready 3NF relational tables**.

---

## 2. Comprehensive Sheet-to-Table Mapping

### 2.1 Language Programs (ELL01 – ELL-B25, PTE-OXFORD-D)
* **Target Tables**: `students`, `batches`, `enrollments`, `fee_transactions`
* **Field Transformations**:
  * `S.N` / `S.No` / `Roll No` / `R No` / `STD-ID` → `enrollments.roll_number` and parsed as candidate for `students.student_reg_no`.
  * `Name` → `students.full_name` (trimmed, extra whitespaces collapsed, casing preserved).
  * `F/Name` / `Father Name` → `students.father_name`.
  * `Addres` / `Address` → `students.address` (standardized village/locality names such as Matta, Sambat, Bamakhela, Kharerai, Gurra, Shawar).
  * `Contact` / `Phone` → `students.contact_number` (cleaned to valid telecommunication format, e.g., `0341-8168778`).
  * `Fee` / `Total Fee` → `enrollments.total_agreed_fee`.
  * `PAID` / `Paid` → `fee_transactions.amount_paid`.
  * `Dues` → `fee_transactions.due_amount`.
  * `DATE` / `Date` → `fee_transactions.payment_date` (normalized to ISO `YYYY-MM-DD`).
  * `Slip No` / `Paid NO` / `Receipt` → `enrollments.receipt_slip_no` and `fee_transactions.receipt_voucher_no`.
  * `FARAZ SEB PAID` / `FARAZ SIR` / `W Noor` / `Irfan sir` → `fee_transactions.received_by`.

---

### 2.2 Diploma in Information Technology (DIT-B1 to DIT-B7, ICT-DIT-B1 to ICT-DIT-B6)
* **Target Tables**: `students`, `batches`, `enrollments`, `fee_transactions`, `board_examinations`
* **Field Transformations**:
  * `Rg.N` / `Reg No` / `Reg.No` → `students.student_reg_no` and `enrollments.roll_number`.
  * `Name /Date` / `Name` → Cleaned student name separated from accidental embedded dates.
  * `Father Name` / `F/NAME` → `students.father_name`.
  * `Contact` → `students.contact_number`.
  * `CNIC` → `students.cnic` (e.g., `15601-8339569-3`).
  * `Address` / `Addess` → `students.address`.
  * `Admission Fee` → `enrollments.admission_fee`.
  * `Sem-1 Fee` / `Total Fee` → `enrollments.total_agreed_fee` (sum of Sem 1 & 2).
  * `Sem-1 Paid` → `fee_transactions.amount_paid` (Semester = 1).
  * `Sem-1 Dues` → `fee_transactions.due_amount` (Semester = 1).
  * `Sem-2 Fee` → Recorded in enrollment financial schedule.
  * `Sem-2 Paid` → `fee_transactions.amount_paid` (Semester = 2).
  * `Sem-2 Dues` → `fee_transactions.due_amount` (Semester = 2).
  * `TTB Roll No Sem-1` → `board_examinations.board_roll_no`.

---

### 2.3 Certificate in Information Technology (ICT-CIT-B1 to ICT-CIT-B-5 Sep 26)
* **Target Tables**: `students`, `batches`, `enrollments`, `fee_transactions`
* **Field Transformations**:
  * `Reg No` (e.g., `ICT-CIT-101`, `CIT-311`, `CIT-511`) → `students.student_reg_no`.
  * `Name` / `F NAME` / `Addess` / `Contact` → Standardized student profile.
  * `Sem-1 Fee` → `enrollments.total_agreed_fee`.
  * `Paid` → `fee_transactions.amount_paid`.
  * `Total Dues` → `fee_transactions.due_amount`.
  * `DATE` → `fee_transactions.payment_date`.

---

### 2.4 Emerging & Vocational Programs (ICT-AI-B1, ICT-VE-B-1, SAFETY COURSES, Special Courses, ICT-TUITION)
* **Target Tables**: `students`, `batches`, `enrollments`, `fee_transactions`
* **Field Transformations**:
  * `STD-ID` / `Reg No` (e.g., `ICT-AI-111`, `ICT-VE-111`, `ICT-SAF-101`, `ICT-SPC-1`, `ICT-T-101`, `ICT-9TH-101`) → `students.student_reg_no`.
  * `D OF B` → `students.date_of_birth` (`YYYY-MM-DD`).
  * `EMAIL` → `students.email`.
  * `CNIC` → `students.cnic`.
  * `Course description` / `Course Name` (e.g., `IOSH MS (UK)`, `OSHA 30`, `Ethical Hacking`, `AutoCAD Civil`, `Electrician`) → Resolved to `courses.id`.
  * `Class` (`4th`, `6th`, `9th`, `11th`) → `enrollments.tuition_class`.
  * `Subjects` (`Physics-7`, `Maths-7`, `Chemistry-7`) → `enrollments.tuition_subjects`.

---

### 2.5 Board Examination & Results (TTB Reg 1 Sep, TTB Reg 2 Dec, TTB Reg AUG 26, ICT-DIT-2-TTB D, Result CIT-DIT-B2, Positions 2025)
* **Target Tables**: `board_examinations`, `academic_positions`, `students`
* **Field Transformations**:
  * `RegistrationNumber` (e.g., `ICT/SWT/DIT/MRH/24/01`, `ICT-TTB-EL-314`) → `board_examinations.board_registration_no`.
  * `RollNo` / `Roll No` (e.g., `56766`, `45559`, `81561`) → `board_examinations.board_roll_no`.
  * `Status` (`Pass:604`, `Fail`, `Enrolled`) → Split into `exam_status` ('pass', 'fail', 'enrolled') and `marks_obtained` (e.g., 604.00).
  * Total Marks (e.g., 1400, 700) → `board_examinations.total_marks`.
  * `Positions 2025` positions (`1st`, `2nd`, `3rd`, `Appreciation Award`) → `academic_positions`.

---

### 2.6 Financial Operations & Assets (ICT-STAFF, ICT Rent, Solar Panels, AMIN QARZY)
* **Target Tables**: `staff_members`, `staff_payroll`, `campus_expenses`, `capital_assets`, `asset_payment_installments`, `loans_and_advances`
* **Field Transformations**:
  * `ICT-STAFF`:
    * `Month Name`, `Salary`, `P1`, `DATE`, `P2`, `Date2`, `P3`, `Date3`, `PAID`, `DUES` → `staff_payroll`.
    * Staff names extracted and linked to `staff_members`.
  * `ICT Rent`:
    * `Month Name`, `Salary` (Rent Amount), `P1`, `DATE`, `PAID`, `DUES` → `campus_expenses`.
  * `Solar Panels`:
    * Panels, Inverters, Total Cash, Dues → `capital_assets` and `asset_payment_installments`.
  * `AMIN QARZY`:
    * `NAME`, `TOTAL`, `Purpose` (e.g., Tabligh, Printing, Books, Advance) → `loans_and_advances`.

---

### 2.7 Course Catalogs (Courses, Courses Students)
* **Target Tables**: `courses`, `departments`
* **Field Transformations**:
  * 67+ course titles from `Courses` sheet cataloged into `courses` with auto-generated unique course codes.
  * Fee and duration specifications from `Courses Students` mapped to `standard_fee`, `ttb_exam_fee`, and `duration`.

---

### 2.8 Unused Sheets
* `Sheet1`: 0 rows, 1 blank cell. Documented and excluded from table loading.
