const { chromium } = require('C:/Users/Tariq Laptops/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const http = require('http');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const dist = 'E:/ICTManagementSystem/src/ICTManagementSystem.UI/dist';
const server = http.createServer((req, res) => {
  const file = path.join(dist, req.url === '/' ? 'index.html' : req.url.split('?')[0]);
  res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : file.endsWith('.png') ? 'image/png' : 'text/html');
  fs.createReadStream(file).on('error', () => { res.statusCode = 404; res.end(); }).pipe(res);
});
(async () => {
  await new Promise(resolve => server.listen(4174, '127.0.0.1', resolve));
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage();
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    await page.addInitScript(() => {
      localStorage.setItem('ict_user', JSON.stringify({ id: 1, fullName: 'Test Admin', role: 'Admin' }));
      const callbacks = [];
      window.chrome = { webview: { addEventListener: (_, cb) => callbacks.push(cb), postMessage: raw => {
        const request = JSON.parse(raw);
        const data = request.method === 'dashboard.getSummary' ? { totalStudents: 0, activeBatches: 0 } : [];
        setTimeout(() => callbacks.forEach(cb => cb({ data: { rpcId: request.rpcId, response: JSON.stringify({ success: true, data }) } })), 1);
      } } };
    });
    await page.goto('http://127.0.0.1:4174/#/attendance');
    await page.waitForURL('**/#/');
    await page.getByRole('link', { name: 'Dashboard', exact: true }).waitFor();
    await page.getByText('Loading executive dashboard data...', { exact: true }).waitFor({ state: 'hidden' });
    assert.equal(await page.getByRole('link', { name: 'Attendance', exact: true }).count(), 0);
    assert.equal(await page.getByText("Today's Attendance", { exact: true }).count(), 0);
    assert.equal(await page.getByText('Class Attendance Management', { exact: true }).count(), 0);
    assert.deepEqual(errors, []);
    console.log('PASS: Attendance sidebar entry and dashboard card removed; old attendance URL redirects to Dashboard; no page errors.');
  } finally { await browser.close(); server.close(); }
})().catch(e => { console.error(e); server.close(); process.exitCode = 1; });
