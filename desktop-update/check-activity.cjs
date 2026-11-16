const { chromium } = require('C:/Users/Tariq Laptops/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const http = require('http'), fs = require('fs'), path = require('path'), assert = require('assert/strict');
const dist = 'E:/ICTManagementSystem/src/ICTManagementSystem.UI/dist';
const server = http.createServer((req, res) => {
  const file = path.join(dist, req.url === '/' ? 'index.html' : req.url.split('?')[0]);
  res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : file.endsWith('.png') ? 'image/png' : 'text/html');
  fs.createReadStream(file).on('error', () => { res.statusCode = 404; res.end(); }).pipe(res);
});
(async () => {
  await new Promise(r => server.listen(4176, '127.0.0.1', r));
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    await page.addInitScript(() => {
      localStorage.setItem('ict_user', JSON.stringify({ id: 1, username: 'admin', fullName: 'Test Admin', role: 'Admin' }));
      const callbacks = []; window.calls = []; let undone = false;
      let course = { id: 1, courseCode: 'DIT', courseName: 'DIT', category: 'IT', duration: '1 Year', standardFee: 1000, ttbExamFee: 200, totalFeesCollected: 0, totalOutstandingDues: 0, subjects: [], enrollments: [], batches: [{ id: 1, batchCode: 'DIT-AM', batchName: 'Morning', status: 'active', activeStudentsCount: 1 }] };
      window.chrome = { webview: { addEventListener: (_, cb) => callbacks.push(cb), postMessage: raw => {
        const m = JSON.parse(raw); window.calls.push(m); let data = [];
        switch (m.method) {
          case 'courses.getById': data = course; break;
          case 'courses.update': course = { ...course, standardFee: m.payload.standardFee, ttbExamFee: m.payload.ttbExamFee }; data = course; break;
          case 'activity.getDaily': data = { items: m.payload.search === 'empty' ? [] : [{ id: 5, timestampUtc: '2026-11-05T10:00:00Z', username: 'admin', action: 'courses.update', description: 'Course details / fees updated', entityName: 'Course', entityId: '1', canUndo: !undone, undone }, ...(undone ? [{ id: 6, timestampUtc: '2026-11-05T10:05:00Z', username: 'admin', action: 'Undo', description: 'Previous change undone', entityName: 'AuditLog', entityId: '5', canUndo: false, undone: false }] : [])], totalCount: m.payload.search === 'empty' ? 0 : undone ? 2 : 1, pageNumber: 1, totalPages: 1 }; break;
          case 'activity.undo': undone = true; course.standardFee = 1000; data = true; break;
        }
        setTimeout(() => callbacks.forEach(cb => cb({ data: { rpcId: m.rpcId, response: JSON.stringify({ success: true, data }) } })), 5);
      } } };
    });
    await page.goto('http://127.0.0.1:4176/#/courses/1');
    const fee = page.locator('input[name="fee"]'); await fee.waitFor(); await fee.fill('2000');
    page.once('dialog', dialog => dialog.dismiss());
    await page.getByRole('button', { name: 'Save course fees', exact: true }).click();
    assert.equal(await page.evaluate(() => window.calls.filter(m => m.method === 'courses.update').length), 0);
    page.once('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: 'Save course fees', exact: true }).click();
    await page.waitForFunction(() => window.calls.some(m => m.method === 'courses.update'));
    assert.equal(await page.evaluate(() => window.calls.find(m => m.method === 'courses.update').payload.__actor), 'admin');
    await page.getByRole('button', { name: 'History & Undo', exact: true }).click();
    await page.getByRole('heading', { name: 'Daily Activity & Recent Changes', exact: true }).waitFor();
    await page.getByRole('button', { name: 'Undo', exact: true }).waitFor();
    page.once('dialog', dialog => dialog.dismiss()); await page.getByRole('button', { name: 'Undo', exact: true }).click();
    assert.equal(await page.evaluate(() => window.calls.filter(m => m.method === 'activity.undo').length), 0);
    page.once('dialog', dialog => dialog.accept()); await page.getByRole('button', { name: 'Undo', exact: true }).click();
    await page.getByText('Undone', { exact: true }).waitFor();
    assert.equal(await page.getByRole('button', { name: 'Undo', exact: true }).count(), 0);
    await page.getByLabel('Activity date', { exact: true }).fill('2026-11-04');
    await page.waitForFunction(() => window.calls.some(m => m.method === 'activity.getDaily' && m.payload.date === '2026-11-04'));
    await page.getByLabel('Search activity').fill('empty'); await page.getByRole('button', { name: 'Search history' }).click();
    await page.getByText('No recorded activity for this date and filter.').waitFor();
    assert.equal(await page.getByRole('button', { name: 'Undo latest listed change' }).isDisabled(), true);
    await page.getByLabel('Search activity').fill(''); await page.getByRole('button', { name: 'Search history' }).click();
    await page.getByText('Previous change undone', { exact: true }).waitFor();
    await page.screenshot({ path: __dirname + '/activity-history.png', fullPage: true });
    assert.deepEqual(errors, []);
    console.log('PASS: confirmation cancel sends no write; accepted change sends actor; History & Undo navigation; Undo cancel sends no write; Undo refreshes and prevents repeats; date/user search and empty state; no page errors.');
  } finally { await browser.close(); server.close(); }
})().catch(e => { console.error(e); server.close(); process.exitCode = 1; });
