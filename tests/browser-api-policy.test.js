import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';

for (const route of ['/account', '/provider/new', '/activities', '/parent', '/provider', '/admin', '/notifications']) {
  test(route + ' permits same-origin browser API requests', async () => {
    const response = await worker.fetch(new Request('https://gotry.example' + route));
    const policy = new Map(response.headers.get('content-security-policy').split(';').map(part => {
      const [directive, ...sources] = part.trim().split(/\s+/);
      return [directive, sources];
    }));
    // fetch() uses connect-src, falling back to default-src when absent.
    assert.deepEqual(policy.get('connect-src') ?? policy.get('default-src'), ["'self'"]);
    assert.deepEqual(policy.get('default-src'), ["'none'"]);
    assert.deepEqual(policy.get('frame-ancestors'), ["'none'"]);
    assert.equal(response.headers.get('x-robots-tag'), 'noindex, nofollow');
  });
}
