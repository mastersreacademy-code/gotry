"""Real HTTP/CSRF/session smoke against an isolated localhost SQLite Laravel server.

python3 tests/http_parent_smoke.py http://127.0.0.1:8123 database/browser-test.sqlite
Seeds one synthetic approved activity; creates provider and parent test accounts.
This checks HTTP behavior, not JavaScript rendering or layout.
"""
import http.cookiejar
import json
from pathlib import Path
import re
import sqlite3
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from datetime import datetime, timedelta, timezone

if len(sys.argv) != 3:
    raise SystemExit('Usage: python3 tests/http_parent_smoke.py http://127.0.0.1:8123 database/browser-test.sqlite')
base = sys.argv[1].rstrip('/')
assert urllib.parse.urlparse(base).hostname in ('127.0.0.1', 'localhost'), 'Use localhost only'
path = Path(sys.argv[2]).resolve()
assert path.parent == Path(__file__).resolve().parents[1] / 'database' and path.name.endswith('-test.sqlite'), 'Use a dedicated database/*-test.sqlite database'
assert path.exists(), 'Migrate the test database first'


class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def get(self, route):
        response = self.opener.open(base + route)
        assert response.headers.get('X-Robots-Tag') == 'noindex, nofollow'
        return response.read().decode()

    def post(self, route, html, fields, expected=200, csrf=True):
        token = re.search(r'name="_token" value="([^"]+)"', html).group(1)
        body = {**({'_token': token} if csrf else {}), **fields}
        request = urllib.request.Request(base + route, data=urllib.parse.urlencode(body).encode())
        try:
            response = self.opener.open(request)
        except urllib.error.HTTPError as error:
            response = error
        text = response.read().decode()
        assert response.status == expected, (route, response.status, text[:200])
        return text

    def signup(self, role):
        self.email = f'parent-smoke-{role}-{time.time_ns()}@example.test'
        self.post('/register', self.get('/register'), {'name': 'HTTP Test User', 'email': self.email, 'role': role,
            'password': 'HTTP test password!', 'password_confirmation': 'HTTP test password!'})


provider, parent_a, parent_b = Client(), Client(), Client()
provider.signup('provider')
parent_a.signup('parent')
parent_b.signup('parent')
now = datetime.now(timezone.utc)
day = (now + timedelta(days=3)).strftime('%Y-%m-%d')
db = sqlite3.connect(path)
provider_id = db.execute('SELECT id FROM users WHERE email=?', (provider.email,)).fetchone()[0]
details = {'description': 'HTTP registration check', 'venue': 'Sydney', 'min_age': 6, 'max_age': 10,
    'trial_type': 'group', 'trial_date': day, 'trial_time': '09:00', 'trial_min': 1, 'trial_max': 1, 'trial_duration': 45,
    'deadline': day, 'regular_format': 'individual', 'usual_availability': 'After school', 'individual_price': 60, 'discount_type': 'none'}
activity_id = db.execute('INSERT INTO activities(provider_id,submission_token,title,category,status,version,details,submitted_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
    (provider_id, str(uuid.uuid4()), 'HTTP Parent Trial', 'Music', 'pending_registrations', 2, json.dumps(details), now.isoformat(), now.isoformat(), now.isoformat())).lastrowid
db.commit()
route = f'/activities/{activity_id}'
payload = {'child_name': 'HTTP Private Child Alpha', 'child_age': 8, 'expected_activity_version': 2, 'interest_terms': 1}
html = parent_a.get(route)
assert '1 places remaining' in html
parent_a.post(route + '/interest', html, payload)
parent_a.post(route + '/interest', html, payload)  # Harmless retry.
interest_a = db.execute('SELECT id FROM interests WHERE activity_id=?', (activity_id,)).fetchone()[0]
assert 'HTTP Private Child Alpha' in parent_a.get('/parent')
assert 'HTTP Private Child Alpha' not in parent_b.get('/parent')
html_b = parent_b.get(route)
assert 'HTTP Private Child Alpha' not in html_b
parent_b.post(route + '/interest', html_b, {**payload, 'child_name': 'HTTP Private Child Beta'}, expected=409)
parent_a.post(f'/parent/interests/{interest_a}/withdraw', parent_a.get('/parent'), {'expected_version': 1, 'withdraw_confirm': 1})
parent_a.post(route + '/interest', parent_a.get(route), payload, expected=409)  # Stale create cannot restore.
parent_b.post(route + '/interest', parent_b.get(route), {**payload, 'child_name': 'HTTP Private Child Beta'})
interest_b = db.execute('SELECT id FROM interests WHERE activity_id=? AND id<>?', (activity_id, interest_a)).fetchone()[0]
restore = {'expected_version': 2, 'expected_activity_version': 2, 'interest_terms': 1}
parent_a.post(f'/parent/interests/{interest_a}/restore', parent_a.get('/parent'), restore, expected=409)
parent_b.post(f'/parent/interests/{interest_b}/withdraw', parent_b.get('/parent'), {'expected_version': 1, 'withdraw_confirm': 1})
parent_a.post(f'/parent/interests/{interest_a}/restore', parent_a.get('/parent'), restore)
parent_a.post(f'/parent/interests/{interest_a}/restore', parent_a.get('/parent'), restore)  # Duplicate restore.
parent_a.post(f'/parent/interests/{interest_a}/withdraw', parent_a.get('/parent'), {'expected_version': 1, 'withdraw_confirm': 1}, expected=409)
parent_b.post(f'/parent/interests/{interest_a}/withdraw', parent_b.get('/parent'), {'expected_version': 3, 'withdraw_confirm': 1}, expected=404)
parent_a.post(f'/parent/interests/{interest_a}/withdraw', parent_a.get('/parent'), {'expected_version': 3, 'withdraw_confirm': 1}, expected=419, csrf=False)
provider.post(route + '/interest', provider.get(route), payload, expected=403)
public = Client().get(route)
assert 'HTTP Private Child Alpha' not in public and 'HTTP Private Child Beta' not in public
assert db.execute("SELECT COUNT(*) FROM interests WHERE activity_id=? AND status='interested'", (activity_id,)).fetchone()[0] == 1
assert db.execute('SELECT COUNT(*) FROM interest_events WHERE interest_id IN (SELECT id FROM interests WHERE activity_id=?)', (activity_id,)).fetchone()[0] == 5
db.close()
print('PASS: real HTTP signup, CSRF, group capacity, duplicate retries, withdrawal, explicit restore, stale forms, roles, parent isolation and public privacy; one active interest / five audit events')
