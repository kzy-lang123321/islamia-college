"""Run against the isolated PHP server on port 18081 after integration.php."""
import http.cookiejar
import json
import re
import urllib.error
import urllib.parse
import urllib.request

BASE = 'http://127.0.0.1:18081'
checks = 0

def request(client, path, data=None, method=None, headers=None, expected=200):
    global checks
    req = urllib.request.Request(BASE + path, data=data, method=method, headers=headers or {})
    try:
        response = client.open(req)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read().decode()
    assert response.status == expected, (path, response.status, body[:200])
    checks += 1
    return body, response

def login(role):
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    body, _ = request(client, '/login.php')
    token = re.search(r'name="csrf_token" value="([^"]+)"', body)[1]
    data = urllib.parse.urlencode(dict(action='login', identifier='check_' + role, password='changed-secret-2026' if role == 'admin' else 'test-secret-2026', csrf_token=token)).encode()
    _, response = request(client, '/login.php', data)
    assert response.url.endswith('/change_password.php' if role == 'forced' else '/index.php')
    return client, token

admin, token = login('admin')
modules = ['students', 'courses', 'departments', 'batches', 'enrollments', 'fees', 'staff', 'payroll', 'expenses', 'assets', 'loans', 'board-examinations', 'academic-positions', 'users', 'reports']
for path in ['/index.php', '/profile.php'] + ['/modules/' + name + '/index.php' for name in modules]:
    request(admin, path)
for kind in ['fees', 'dues', 'students', 'board', 'expenses', 'payroll', 'loans']:
    request(admin, '/modules/reports/index.php?type=' + kind)
    request(admin, '/modules/reports/index.php?export=csv&type=' + kind)
for method in ['PUT', 'DELETE']:
    request(admin, '/api/students/index.php?id=1', b'{}', method, {'Content-Type': 'application/json'}, 403)
request(admin, '/api/staff/remove_photo.php', expected=405)
profile = json.loads(request(admin, '/api/fees/student_fee_details.php?enrollment_id=1')[0])['data']
payment = json.dumps(dict(enrollment_id=1, amount_paid=123, payment_date='2026-11-01', receipt_no='HTTP-TEST-RECEIPT')).encode()
headers = {'Content-Type': 'application/json', 'X-CSRF-Token': token}
result = json.loads(request(admin, '/api/fees/index.php', payment, headers=headers, expected=201)[0])
updated = json.loads(request(admin, '/api/fees/student_fee_details.php?enrollment_id=1')[0])['data']
assert updated['cumulative_paid'] == profile['cumulative_paid'] + 123
request(admin, '/modules/fees/receipt.php?id=' + str(result['id']))
request(admin, '/api/fees/index.php', payment, headers=headers, expected=409)
for role in ['staff', 'accountant', 'registrar']:
    client, _ = login(role)
    if role != 'admin':
        request(client, '/modules/reports/index.php?export=csv&type=payroll', expected=403)
    if role in ['staff', 'registrar']:
        request(client, '/modules/reports/index.php?export=csv&type=expenses', expected=403)
        results = json.loads(request(client, '/api/search/index.php?q=ali')[0])['results']
        assert not any(item['category'] in ['Staff', 'Asset'] for item in results)
    if role == 'accountant':
        request(client, '/api/students/index.php', expected=403)
forced, _ = login('forced')
request(forced, '/api/students/index.php', expected=403)
print(f'{checks} HTTP checks passed, including module pages, CSV exports, permissions, payment and receipt.')
