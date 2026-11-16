const { chromium } = require('C:/Users/Tariq Laptops/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const http = require('http'), fs = require('fs'), path = require('path'), assert = require('assert/strict');
const dist = 'E:/ICTManagementSystem/src/ICTManagementSystem.UI/dist';
const server = http.createServer((req, res) => {
  const file = path.join(dist, req.url === '/' ? 'index.html' : req.url.split('?')[0]);
  res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : file.endsWith('.png') ? 'image/png' : 'text/html');
  fs.createReadStream(file).on('error', () => { res.statusCode = 404; res.end(); }).pipe(res);
});
(async () => {
  await new Promise(r => server.listen(4175, '127.0.0.1', r));
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    await page.addInitScript(() => {
      localStorage.setItem('ict_user', JSON.stringify({ id: 1, fullName: 'Test Admin', role: 'Admin' }));
      const students = [
        { enrollmentId: 11, studentId: 1, studentRegNo: 'ICT-DIT-001', fullName: 'Ali Khan', fatherName: 'Akbar', courseName: 'DIT', batchCode: 'DIT-AM', rollNumber: '01' },
        { enrollmentId: 12, studentId: 2, studentRegNo: 'ICT-ELL-002', fullName: 'Imran Khan', fatherName: 'Bashir', courseName: 'English', batchCode: 'ELL-PM', rollNumber: '02' }
      ];
      window.calls = []; window.responded = [];
      const callbacks = [];
      window.chrome = { webview: { addEventListener: (_, cb) => callbacks.push(cb), postMessage: raw => {
        const m = JSON.parse(raw); window.calls.push(m); let data = [], delay = 5;
        if (m.method === 'students.getPaged') data = { items: [], totalCount: 0, totalPages: 0 };
        if (m.method === 'fees.getNextReceiptNo') data = 'REC-TEST-0001';
        if (m.method === 'fees.suggestStudents') {
          data = m.payload.query === 'khan' ? students : m.payload.query === 'kh' ? [{ ...students[0], fullName: 'Outdated result' }] : [];
          delay = m.payload.query === 'kh' ? 500 : 5;
        }
        if (m.method === 'fees.getStudentDetails') {
          data = { ...students.find(s => s.enrollmentId === m.payload.enrollmentId), totalAgreedFee: 1000, totalPaid: 100, currentDues: 900, pendingFines: 0, availableDiscounts: 0, totalPayable: 900, paymentHistory: [] };
          delay = m.payload.enrollmentId === 11 ? 450 : 5;
        }
        setTimeout(() => {
          const response = m.method === 'fees.suggestStudents' && m.payload.query === 'fail' ? { success: false, error: 'Test search unavailable' } : { success: true, data };
          callbacks.forEach(cb => cb({ data: { rpcId: m.rpcId, response: JSON.stringify(response) } }));
          window.responded.push(m);
        }, delay);
      } } };
    });
    await page.goto('http://127.0.0.1:4175/#/students');
    await page.getByRole('heading', { name: 'Students Directory', exact: true }).waitFor();
    assert.equal(await page.getByText('Active Cohort Financial Scope', { exact: true }).count(), 0);
    assert.equal(await page.getByText('Collected Income', { exact: true }).count(), 0);
    await page.getByRole('link', { name: 'Finance & Fees' }).click();
    const input = page.getByRole('combobox', { name: 'Find student for fee collection' });
    await input.fill('khan');
    await page.getByRole('option').filter({ hasText: 'Imran Khan' }).waitFor();
    assert.equal(await page.getByRole('option').count(), 2);
    assert.equal(await page.getByText('Amount Received (Rs.) *', { exact: true }).count(), 0);
    await input.press('ArrowDown'); await input.press('ArrowDown'); await input.press('Enter');
    await page.getByRole('heading', { name: 'Imran Khan', exact: true }).waitFor();
    const selectedId = await page.evaluate(() => window.calls.filter(m => m.method === 'fees.getStudentDetails').at(-1).payload.enrollmentId);
    assert.equal(selectedId, 12);
    assert.equal(await page.locator('input[type="number"]').first().inputValue(), '900');
    await input.fill('unknown');
    assert.equal(await page.getByRole('heading', { name: 'Imran Khan', exact: true }).count(), 0);
    await page.getByText('No matching enrollments. Try another name or registration number.').waitFor();
    await input.fill('kh');
    await page.waitForFunction(() => window.calls.some(m => m.method === 'fees.suggestStudents' && m.payload.query === 'kh'));
    await input.fill('khan');
    await page.getByRole('option').filter({ hasText: 'Imran Khan' }).waitFor();
    await page.waitForFunction(() => window.responded.some(m => m.method === 'fees.suggestStudents' && m.payload.query === 'kh'));
    assert.equal(await page.getByText('Outdated result', { exact: false }).count(), 0);
    await page.getByRole('option').filter({ hasText: 'Ali Khan' }).click();
    await input.fill('unknown');
    await page.waitForFunction(() => window.responded.some(m => m.method === 'fees.getStudentDetails' && m.payload.enrollmentId === 11));
    assert.equal(await page.getByRole('heading', { name: 'Ali Khan', exact: true }).count(), 0);
    await input.fill('fail'); await page.getByRole('alert').filter({ hasText: 'Test search unavailable' }).waitFor();
    const failedCalls = await page.evaluate(() => window.calls.filter(m => m.method === 'fees.suggestStudents' && m.payload.query === 'fail').length);
    assert.equal(failedCalls, 1);
    await input.fill('khan'); await page.getByRole('option').filter({ hasText: 'Imran Khan' }).waitFor();
    await page.screenshot({ path: __dirname + '/fee-suggestions.png', fullPage: true });
    assert.deepEqual(errors, []);
    console.log('PASS: student cards removed; multiple fee suggestions; explicit keyboard selection; correct enrollment and dues; editing clears selection; stale searches and selections ignored; empty and failure states; no page errors.');
  } finally { await browser.close(); server.close(); }
})().catch(e => { console.error(e); server.close(); process.exitCode = 1; });
