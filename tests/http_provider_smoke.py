"""Synthetic-account smoke test against an isolated localhost Laravel server.

Run: python3 tests/http_provider_smoke.py http://127.0.0.1:8123
Uses real CSRF, cookies, database sessions, all wizard steps and static assets.
This is HTTP integration, not a browser/JavaScript layout test.
"""
import http.cookiejar
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timedelta, timezone

if len(sys.argv) != 2:
    raise SystemExit('Usage: python3 tests/http_provider_smoke.py http://127.0.0.1:8123')
base = sys.argv[1].rstrip('/')
assert urllib.parse.urlparse(base).hostname in ('127.0.0.1', 'localhost'), 'Use an isolated localhost test server'
day = (datetime.now(timezone.utc) + timedelta(days=60)).strftime('%Y-%m-%d')

for individual in (False, True):
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def get(path):
        response = opener.open(base + path)
        assert response.headers.get('X-Robots-Tag') == 'noindex, nofollow'
        return response.read().decode()

    def post(path, html, fields):
        token = re.search(r'name="_token" value="([^"]+)"', html).group(1)
        request = urllib.request.Request(base + path, data=urllib.parse.urlencode({'_token': token, **fields}).encode())
        response = opener.open(request)
        return response.geturl(), response.read().decode()

    url, html = post('/register', get('/register'), {'name': 'HTTP Provider', 'role': 'provider',
        'email': f'http-{time.time_ns()}@example.test', 'password': 'HTTP test password!', 'password_confirmation': 'HTTP test password!'})
    assert url.endswith('/provider'), url
    html = get('/provider/new')
    steps = {
        1: {'business_name': 'HTTP Studio', 'contact_name': 'HTTP Contact', 'contact_email': 'private-http@example.test', 'phone': '0412345678'},
        2: {'activity_name': 'HTTP individual' if individual else 'HTTP group', 'category': 'Music', 'min_age': 6, 'max_age': 10, 'description': 'Learn music', 'venue': 'Sydney'},
        3: {'trial_type': 'individual' if individual else 'group', 'trial_duration': 45, 'deadline': day,
            **({'trial_availability': 'After school'} if individual else {'trial_date': day, 'trial_time': '09:00', 'trial_min': 8, 'trial_max': 12})},
        4: {'regular_format': 'individual' if individual else 'group', 'discount_type': 'percent', 'discount_value': 10, 'discount_applies': 'term',
            **({'usual_availability': 'After school', 'individual_price': 60} if individual else {'multiple_days': '1', 'class_times': 'Monday 16:00; Saturday 09:00', 'regular_min': 8, 'regular_standard': 10, 'regular_max': 12, 'standard_price': 20, 'small_group_price': 25, 'lessons_per_term': 10})},
    }
    for step, fields in steps.items():
        url, html = post(f'/provider/new/{step}', html, fields)
        assert url.endswith(f'/provider/new/{step + 1}'), (url, html[:300])
    assert 'Review your listing' in html
    assert ('$54.00' if individual else '$180.00') in html
    submission = re.search(r'name="submission_token" value="([^"]+)"', html).group(1)
    review = html
    fields = {'submission_token': submission, 'child_safety': 1, 'insurance': 1, 'honour_terms': 1}
    url, html = post('/provider/submit', review, fields)
    assert '/provider/activities/' in url and 'Pending Approval' in html
    duplicate_url, _ = post('/provider/submit', review, fields)
    assert duplicate_url == url
    print(f'PASS: {"individual" if individual else "group"} signup, five-step HTTP submission, pricing and duplicate retry')

for asset in ('/js/provider-wizard.mjs', '/js/programme-pricing.mjs', '/css/app.css'):
    response = urllib.request.urlopen(base + asset)
    assert response.status == 200
    assert 'text/html' not in response.headers.get('Content-Type', '')
print('PASS: CSS and JavaScript module assets served')
