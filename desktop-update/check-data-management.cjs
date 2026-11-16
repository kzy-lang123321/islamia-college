const { chromium } = require('C:/Users/Tariq Laptops/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const http = require('http'), fs = require('fs'), path = require('path'), assert = require('assert/strict');
const dist = 'E:/ICTManagementSystem/src/ICTManagementSystem.UI/dist';
const server = http.createServer((req, res) => {
  const file = path.join(dist, req.url === '/' ? 'index.html' : req.url.split('?')[0]);
  res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : file.endsWith('.png') ? 'image/png' : 'text/html');
  fs.createReadStream(file).on('error', () => { res.statusCode = 404; res.end(); }).pipe(res);
});
(async () => {
  await new Promise(r => server.listen(4177, '127.0.0.1', r));
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1600, height: 1100 } });
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    const setup = async (p, role) => p.addInitScript(({ role }) => {
      localStorage.setItem('ict_user', JSON.stringify({ id: 1, username: 'fixture-admin', fullName: 'Fixture Admin', role }));
      const callbacks = []; window.calls = []; window.imported = null; let outcome, newBatch;
      const courses = () => [...new Map((window.imported?.sheets || []).filter(s => s.kind === 'students').map((s, i) => [s.courseCode, { id: i + 1, courseCode: s.courseCode, courseName: s.courseName, standardFee: s.standardFee, ttbExamFee: 0 }])).values()];
      window.chrome = { webview: { addEventListener: (_, cb) => callbacks.push(cb), postMessage: raw => {
        const m = JSON.parse(raw); window.calls.push(m); let data = [];
        switch (m.method) {
          case 'dashboard.getSummary': data = { totalStudents: 0 }; break;
          case 'data.authorize': data = 'fixture-admin-token'; break;
          case 'data.overview': data = { administrator: 'fixture-admin', tables: { Students: 2, Courses: 2, Batches: 2, Enrollments: 2 } }; break;
          case 'data.importWorkbook':
            window.imported = m.payload;
            outcome = { hash: 'FIXTURE', fileName: m.payload.fileName, originalFilePath: 'FIXTURE ONLY — no live data imported', worksheets: m.payload.sheets.length,
              sheets: m.payload.sheets.map(s => ({ name: s.name, kind: s.kind, imported: s.kind === 'students' ? s.students.length : 0, linked: s.kind === 'students' ? s.students.length : 0, skipped: 0, batchCode: s.batchCode, courseId: s.kind === 'students' ? courses().find(c => c.courseCode === s.courseCode).id : null, warnings: s.detectionWarnings })), alreadyImported: false }; data = outcome; break;
          case 'data.archives': data = outcome ? [{ hash: outcome.hash, fileName: outcome.fileName, worksheets: outcome.worksheets, importedAt: new Date().toISOString() }] : []; break;
          case 'data.manifest': data = outcome; break;
          case 'data.archiveSheet': data = { Name: window.imported.sheets[m.payload.index].name, SourceRows: window.imported.sheets[m.payload.index].sourceRows }; break;
          case 'courses.getAll': data = courses(); break;
          case 'courses.getPaged': data = { items: courses(), totalCount: courses().length, totalPages: 1 }; break;
          case 'courses.getNextBatchCode': data = 'ICT-ELL-B26'; break;
          case 'courses.createBatch': newBatch = { id: 900, ...m.payload, status: 'active' }; data = newBatch; break;
          case 'courses.getBatches': data = newBatch ? [newBatch] : []; break;
          case 'students.getNextRegNo': data = 'ICT-ELL-2526'; break;
          case 'students.getPaged': data = { items: [], totalCount: 0, totalPages: 0 }; break;
        }
        setTimeout(() => callbacks.forEach(cb => cb({ data: { rpcId: m.rpcId, response: JSON.stringify({ success: true, data }) } })), 3);
      } } };
    }, { role });
    await setup(page, 'Admin');
    await page.goto('http://127.0.0.1:4177/#/data-management');
    await page.getByLabel('Administrator password', { exact: true }).fill('fixture-password');
    await page.getByRole('button', { name: 'Unlock Data Management', exact: true }).click();
    await page.getByRole('button', { name: 'Clear college data', exact: true }).click();
    const wipe = page.getByRole('button', { name: 'Back up and clear college data', exact: true });
    assert.equal(await wipe.isDisabled(), true);
    await page.getByLabel('Wipe confirmation phrase').fill('WIPE COLLEGE DATA'); assert.equal(await wipe.isDisabled(), true);
    await page.getByLabel('Wipe administrator password').fill('fixture-password'); assert.equal(await wipe.isEnabled(), true);
    page.once('dialog', dialog => dialog.dismiss()); await wipe.click();
    assert.equal(await page.evaluate(() => window.calls.filter(c => c.method === 'data.wipe').length), 0);
    await page.getByRole('button', { name: 'Import all worksheets', exact: true }).click();
    await page.getByLabel('Select multi-sheet workbook').setInputFiles('C:/xampp/htdocs/islamia college/ICT COMPLETE Budget 2018-26.xlsx');
    await page.getByRole('button', { name: 'Import all 63 worksheets', exact: true }).waitFor({ timeout: 60000 });
    assert.equal(await page.getByLabel('Handling ICT-STAFF', { exact: true }).inputValue(), 'reference');
    assert.equal(await page.getByLabel('Handling ELL-B25', { exact: true }).inputValue(), 'students');
    await page.getByLabel('New course fee ELL-B25', { exact: true }).fill('3500');
    assert.equal(await page.getByLabel('New course fee ELL-B24', { exact: true }).inputValue(), '3500');
    page.once('dialog', dialog => dialog.dismiss()); await page.getByRole('button', { name: 'Import all 63 worksheets', exact: true }).click();
    assert.equal(await page.evaluate(() => window.calls.filter(c => c.method === 'data.importWorkbook').length), 0);
    await page.screenshot({ path: __dirname + '/workbook-import-preview.png', fullPage: true });
    page.once('dialog', dialog => dialog.accept()); await page.getByRole('button', { name: 'Import all 63 worksheets', exact: true }).click();
    await page.getByRole('button', { name: 'Create next admission batch' }).waitFor();
    const summary = await page.evaluate(() => ({ sheets: window.imported.sheets.length,
      studentSheets: window.imported.sheets.filter(s => s.kind === 'students').length,
      studentRows: window.imported.sheets.filter(s => s.kind === 'students').reduce((n, s) => n + s.students.length, 0),
      sourceRows: window.imported.sheets.reduce((n, s) => n + s.sourceRows.length, 0),
      firstReg: window.imported.sheets.find(s => s.name === 'ELL-B25').students[0].registration,
      ditRows: window.imported.sheets.find(s => s.name === 'ICT-DIT-B6').students.length,
      base64Length: window.imported.fileBase64.length }));
    assert.equal(summary.sheets, 63); assert.ok(summary.studentSheets >= 40); assert.ok(summary.studentRows > 1000); assert.ok(summary.ditRows > 0); assert.equal(summary.firstReg, 'ELL-2511'); assert.ok(summary.base64Length > 1000000);
    await page.getByRole('button', { name: 'Original worksheet archive', exact: true }).click();
    await page.getByLabel('Archived worksheet').waitFor();
    assert.equal(await page.getByLabel('Archived worksheet').locator('option').count(), 63);
    await page.getByLabel('Archived worksheet').selectOption({ label: 'ICT-STAFF' });
    await page.getByRole('cell', { name: 'Month Name', exact: true }).first().waitFor();
    await page.getByRole('button', { name: 'Import all worksheets', exact: true }).click();
    await page.getByRole('button', { name: 'Create next admission batch' }).click();
    await page.getByText('Create New Academic Batch', { exact: true }).waitFor();
    assert.equal(await page.locator('input[value="ICT-ELL-B26"]').count(), 1);
    page.once('dialog', dialog => dialog.accept()); await page.getByRole('button', { name: 'Save & Start Admissions' }).click();
    await page.getByLabel('Automatic registration number preview').waitFor();
    assert.equal(await page.getByLabel('Automatic registration number preview').inputValue(), 'ICT-ELL-2526');
    const cashier = await browser.newPage(); await setup(cashier, 'Cashier');
    await cashier.goto('http://127.0.0.1:4177/#/data-management'); await cashier.waitForURL('**/#/');
    assert.equal(await cashier.getByRole('link', { name: 'Data Management', exact: true }).count(), 0);
    await cashier.getByText('0 Courses', { exact: true }).waitFor();
    assert.equal(await cashier.getByText('1,454', { exact: true }).count(), 0);
    assert.deepEqual(errors, []);
    console.log('PASS: admin unlock; wipe phrase/password/cancel; non-admin page blocked; actual 63-tab workbook detected, reviewed and archived through MOCK bridge only; next-batch and admission handoff.');
    console.log(JSON.stringify(summary));
  } finally { await browser.close(); server.close(); }
})().catch(e => { console.error(e); server.close(); process.exitCode = 1; });
