const { chromium } = require('C:/Users/Tariq Laptops/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
const vm = require('vm');
const assert = require('assert/strict');

(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.addInitScript(() => {
    localStorage.setItem('ict_user', JSON.stringify({ id: 1, fullName: 'Test Admin', role: 'Admin' }));
    localStorage.setItem('ict_token', 'test');
    const course = { id: 1, courseCode: 'DIT', courseName: 'Diploma in Information Technology', category: 'IT', duration: '2 Semesters', standardFee: 22000, ttbExamFee: 2500, totalBatches: 2, totalStudentsEnrolled: 1, totalFeesCollected: 0, outstandingDues: 22000, isActive: true };
    const batches = [{ id: 1, batchCode: 'DIT-2026', batchName: 'Morning', courseId: 1, courseName: course.courseName, status: 'active', activeStudentsCount: 1 }, { id: 2, batchCode: 'DIT-OLD', batchName: 'Closed', courseId: 1, status: 'inactive', activeStudentsCount: 0 }];
    const student = { id: 1, studentRegNo: 'ICT-DIT-001', fullName: 'Test Student', fatherName: 'Father', contactNumber: '+92-3001234567', status: 'Active', totalAgreedFee: 22000, totalPaid: 0, balanceDue: 22000 };
    window.calls = [];
    const listeners = [];
    window.chrome = { webview: { addEventListener: (type, callback) => listeners.push(callback), postMessage: raw => {
      const msg = JSON.parse(raw); if (msg.type !== 'RPC') return;
      window.calls.push(msg);
      let data;
      switch (msg.method) {
        case 'courses.getAll': data = [course]; break;
        case 'courses.getPaged': data = { items: [course], totalCount: 1, totalPages: 1 }; break;
        case 'courses.getById': data = { ...course, batches, subjects: [], enrollments: [], totalOutstandingDues: 22000 }; break;
        case 'courses.getBatches': data = batches; break;
        case 'students.getNextRegNo': data = 'ICT-DIT-002'; break;
        case 'students.getPaged': data = { items: [student], totalCount: 1, totalPages: 1 }; break;
        case 'students.create': data = { ...student, studentRegNo: 'ICT-DIT-002' }; break;
        case 'courses.getEnrollments': data = [{ id: 1, studentId: 1, studentName: student.fullName, studentRegNo: student.studentRegNo, courseName: course.courseName, batchCode: 'DIT-2026', totalAgreedFee: 22000, totalPaid: 0, balanceDue: 22000, status: 'Active' }]; break;
        case 'academic.getExams': data = []; break;
        case 'academic.importStudents': case 'academic.importMarks': data = { imported: msg.payload.rows.length, skipped: 0, errors: [] }; break;
        default: data = [];
      }
      setTimeout(() => listeners.forEach(callback => callback({ data: { rpcId: msg.rpcId, response: JSON.stringify({ success: true, data }) } })), 5);
    } } };
  });
  await page.goto('http://127.0.0.1:4173/#/students?selectedBatchId=1&openAdmission=true');
  await page.getByText('Registration No (automatic preview)').waitFor();
  assert.equal(await page.getByLabel('Automatic registration number preview').inputValue(), 'ICT-DIT-002');
  const phone = page.locator('input[inputmode="tel"]');
  await phone.fill('03001234567'); assert.equal(await phone.inputValue(), '+92-3001234567');
  const cnic = page.locator('input[inputmode="numeric"]');
  await cnic.fill('1234512345671'); assert.equal(await cnic.inputValue(), '12345-1234567-1');
  assert.equal(await page.locator('select').filter({ has: page.locator('option[value="2"]') }).count(), 1); // historical filter retains closed batch
  assert.equal(await page.getByText('Total Agreed Tuition Fee (Rs.)', { exact: true }).count(), 0);
  await page.getByRole('button', { name: 'Cancel', exact: true }).click();
  await page.getByRole('link', { name: 'Courses & Batches' }).click();
  await page.getByRole('button', { name: 'Add Course', exact: true }).click();
  assert.equal(await page.getByText('Course Code *', { exact: true }).count(), 0);
  assert.equal(await page.getByPlaceholder('Type a category').count(), 1);
  await page.getByRole('button', { name: 'Cancel', exact: true }).click();
  await page.getByRole('link', { name: 'Exams & Results' }).click();
  await page.locator('label').filter({ hasText: /^Academic batch/ }).locator('select').selectOption('1');
  await page.getByRole('option', { name: 'Semester 2' }).waitFor({ state: 'attached' });
  await page.locator('label').filter({ hasText: /^Semester/ }).locator('select').selectOption('2');
  const context = {};
  vm.runInNewContext(fs.readFileSync(__dirname + '/src/ICTManagementSystem.UI/public/xlsx.full.min.js', 'utf8'), context);
  const X = context.XLSX;
  const book = X.utils.book_new();
  X.utils.book_append_sheet(book, X.utils.aoa_to_sheet([['Reg No','Subject','Total Marks','Obtained Marks'], ['ICT-DIT-001','ICT',100,80]]), 'Semester 2');
  for (const bookType of ['xls', 'xlsx']) {
    const bytes = Buffer.from(X.write(book, { type: 'array', bookType }));
    await page.locator('input[type="file"]').setInputFiles({ name: `test.${bookType}`, mimeType: 'application/octet-stream', buffer: bytes });
    await page.getByRole('button', { name: 'Import 1 rows into selected batch' }).waitFor();
    await page.getByRole('button', { name: 'Import 1 rows into selected batch' }).click();
    await page.getByText('1 saved · 0 skipped · 0 errors').waitFor();
  }
  const imports = await page.evaluate(() => window.calls.filter(c => c.method === 'academic.importMarks'));
  assert.equal(imports.length, 2); assert.equal(imports[0].payload.semester, 2);
  assert.equal(imports[0].payload.rows[0].obtainedMarks, 80);
  await page.screenshot({ path: __dirname + '/exam-import-check.png', fullPage: true });
  assert.deepEqual(errors, []);
  await browser.close();
  console.log('PASS: admission preselection, phone/CNIC formatting, hidden manual fees/code, custom category, DIT semester selector, XLS and XLSX upload/import, no page errors.');
})().catch(e => { console.error(e); process.exit(1); });
