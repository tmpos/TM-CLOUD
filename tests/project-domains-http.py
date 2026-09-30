"""Run against a disposable container initialized with project-domains-fixture.php."""
import http.client
import json
import re
import urllib.parse

cookies = {}


def request(path, host='127.0.0.1', data=None, json_data=None, csrf=None):
    connection = http.client.HTTPConnection('127.0.0.1', 18765, timeout=20)
    headers = {'Host': host, 'Cookie': cookies.get(host, '')}
    if data is not None:
        headers['Content-Type'] = 'application/x-www-form-urlencoded'
        body = urllib.parse.urlencode(data)
    elif json_data is not None:
        headers['Content-Type'] = 'application/json'
        headers['X-CSRF-Token'] = csrf
        body = json.dumps(json_data)
    else:
        body = None
    connection.request('POST' if body is not None else 'GET', path, body, headers)
    result = connection.getresponse()
    status, location = result.status, result.getheader('Location')
    cookie = result.getheader('Set-Cookie')
    if cookie:
        cookies[host] = cookie.split(';')[0]
    text = result.read().decode()
    connection.close()
    return status, location, text


def csrf(text):
    return re.search(r'name="_csrf" value="([^"]+)"', text)[1]


status, _, text = request('/', 'shop.example.test')
assert status == 200 and 'Domain Fixture' in text, (status, text[:200])
for path in ['/dashboard', '/projects', '/store/other', '/api/system/other/session', '/sistema/domain-fixture']:
    assert request(path, 'shop.example.test')[0] == 404, path
assert request('/', 'unassigned.example.test')[0] == 404
assert request('/store/domain-fixture/cart', 'shop.example.test')[0] == 200
assert request('/')[0] == 200

assert request('/', 'system.example.test')[:2] == (302, '/sistema/domain-fixture')
assert request('/api/system/domain-fixture/session', 'system.example.test')[0] == 401
status, _, text = request('/sistema/domain-fixture/login', 'system.example.test')
assert status == 200
assert request('/sistema/domain-fixture/login', 'system.example.test', {'pin': '9876', '_csrf': csrf(text)})[0] in (302, 303)
status, _, text = request('/sistema/domain-fixture', 'system.example.test')
assert status == 200 and 'type="module"' in text
status, _, text = request('/api/system/domain-fixture/session', 'system.example.test')
assert status == 200
session = json.loads(text)['data']
assert session['project']['slug'] == 'domain-fixture'
status, _, text = request('/api/system/domain-fixture/runtime', 'system.example.test', json_data={'action': 'db/getAll', 'data': {'tabla': 'usuarios'}}, csrf=session['csrf'])
assert status == 200 and json.loads(text)['success'] is True
assert request('/api/system/other/runtime', 'system.example.test', json_data={}, csrf=session['csrf'])[0] == 404

status, _, text = request('/')
assert request('/login', data={'email': 'domains@example.test', 'password': 'Disposable-domain-test-2026', '_csrf': csrf(text)})[0] in (302, 303)
uid = session['project']['uid']
url = '/projects/' + uid + '/domains'
status, _, text = request(url)
assert status == 200 and 'Dominios del proyecto' in text and 'shop.example.test' in text
assert request(url, data={'store_domain': 'changed.example.test', '_csrf': 'invalid'})[0] in (302, 303)
assert 'shop.example.test' in request(url)[2], 'CSRF must prevent edits'
token = csrf(request(url)[2])
assert request(url, data={'store_domain': 'changed.example.test', 'enable_system': '1', '_csrf': token})[0] in (302, 303)
text = request(url)[2]
assert 'sistema.changed.example.test' in text and 'DNS pendiente' in text
assert request('/', 'shop.example.test')[0] == 404, 'Retired domain must not expose admin'
print('PASS: store root/cart, system PIN/session/runtime, domain UI, default subdomain, CSRF and cross-project isolation')
