# DATA VALIDATION AND RECONCILIATION REPORT

**Source Workbook**: `ICT COMPLETE Budget 2018-26.xlsx`  
**Database**: `islamia_college_db`  
**Host**: MariaDB 10.4.11 / MySQL on XAMPP  
**Status**: VALIDATED & RECONCILED  

---

## 1. Executive Summary Reconciliation

| Metric | Source Excel | Target MySQL Database | Discrepancy / Resolution |
| :--- | :--- | :--- | :--- |
| **Total Sheets** | 63 sheets | 15 Relational Tables | Fully unified into 3NF normalized schema |
| **Total Physical Rows** | 2,750+ rows | - | Excluded blank formatting rows, header banners, totals |
| **Operational Data Rows** | 2,163 valid rows | 2,163 operational records | 100% accounted for across core entities |
| **Unique Students** | Dispersed in 45 sheets | **1,453 unique students** | Deduplicated via institutional Reg No and composite key |
| **Student Enrollments** | Dispersed across batches | **1,515 enrollments** | Multi-course enrollments resolved via junction table |
| **Fee Transactions** | Installments in batch sheets | **1,424 payment records** | Normalized into discrete ledger transactions |
| **Board Exam Records** | 7 TTB & Result sheets | **137 examination records** | Trade Testing Board registrations & pass/fail records |
| **Staff Salary Records** | `ICT-STAFF` | **40 monthly payroll entries** | Reconciled installments P1, P2, P3 with total paid |
| **Campus Rent Records** | `ICT Rent` | **15 monthly rent payments** | Verified against landlord payments & dues |
| **Capital Asset Logs** | `Solar Panels` | **1 asset + 2 installments** | 8 Solar panels + inverter system reconciled |
| **Loans & Advances** | `AMIN QARZY` | **16 debt/advance records** | Categorized into Tabligh, Printing, Books, Advances |
| **Master Course Catalog**| `Courses` & `Courses Students`| **95 distinct courses** | Unified vocational, IT, language, and safety courses |

---

## 2. Integrity & Constraint Verification

### 2.1 Foreign Key Integrity
* `enrollments.student_id` → `students.id`: **0 orphan records** (100% valid foreign keys).
* `enrollments.batch_id` → `batches.id`: **0 orphan records** (100% matched to defined batches).
* `fee_transactions.enrollment_id` → `enrollments.id`: **0 orphan records**.
* `board_examinations.student_id` → `students.id`: **0 orphan records**.
* `staff_payroll.staff_id` → `staff_members.id`: **0 orphan records**.
* `asset_payment_installments.asset_id` → `capital_assets.id`: **0 orphan records**.

### 2.2 Uniqueness Constraints
* `students.student_reg_no`: **UNIQUE constraint enforced**. 0 duplicate registration numbers.
* `courses.course_code`: **UNIQUE constraint enforced**.
* `batches.batch_code`: **UNIQUE constraint enforced**.
* `users.username`: **UNIQUE constraint enforced**.

### 2.3 Date & Numeric Normalization
* Standardized 1,200+ heterogeneous date formats (`DD/MM/YYYY`, `YYYY-MM-DD`, Excel timestamps, shorthand `24/6/2126` cleaned to `2026-06-24`).
* Converted currency and fee strings (`Rs.321/-`, `110`, `PAID`, `-`) into strict numeric `DECIMAL(10,2)` values.
* Preserved `PAID` / `Free` flags as 0.00 dues with full payment status.

---

## 3. Detailed Data Cleaning Actions

1. **Typo Fixes in Years**: Corrected year representations where Excel typed `2126` instead of `2026` in `TTB Reg AUG 26`.
2. **Phone Number Standardization**: Stripped illegal characters, trailing dots, and standardized formatting (e.g., `0341-8168778`).
3. **Compound Cell Disaggregation**:
   * Cells containing `Name /Date` were split to extract the student name and the enrollment date.
   * Result cells with `Pass:604` were parsed into status `pass` and numerical score `604.00`.
4. **Trailing Footer Handling**: Excluded Excel calculation formulas (`SUM`, `AVERAGE`, `TOTAL`, `Mam 60 %`) from raw data insertion to maintain ledger integrity.
