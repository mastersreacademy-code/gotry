import { page } from './page.js';
import { handleApi } from './api.js';
import { adminPage } from './admin-page.js';
import { authPage } from './auth-page.js';
import { wizardPage } from './wizard-page.js';
export default {
  async fetch(request, env = {}) {
    const { pathname } = new URL(request.url);
    if (pathname.startsWith('/api/')) return handleApi(request, env);
    if (pathname === '/health') return Response.json({ app: 'gotry', status: 'ok', version: '0.1.0' }, { headers: { 'cache-control': 'no-store', 'x-robots-tag': 'noindex, nofollow' } });
    if ((pathname === '/' || pathname === '/provider/new' || pathname === '/admin' || pathname === '/account') && request.method === 'GET') return new Response(pathname === '/' ? page : pathname === '/admin' ? adminPage : pathname === '/account' ? authPage : wizardPage, { headers: {
      'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store',
      'x-robots-tag': 'noindex, nofollow', 'x-content-type-options': 'nosniff',
      'x-frame-options': 'DENY', 'referrer-policy': 'strict-origin-when-cross-origin',
      'content-security-policy': (pathname === '/provider/new' || pathname === '/admin' || pathname === '/account') ? "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'" : "default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"
    } });
    return new Response('Not found', { status: 404, headers: { 'x-robots-tag': 'noindex, nofollow' } });
  }
};