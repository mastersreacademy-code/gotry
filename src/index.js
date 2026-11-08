import { page } from './page.js';
export default {
  async fetch(request) {
    const { pathname } = new URL(request.url);
    if (pathname === '/health') return Response.json({ app: 'gotry', status: 'ok', version: '0.1.0' }, { headers: { 'cache-control': 'no-store', 'x-robots-tag': 'noindex, nofollow' } });
    if (pathname === '/' && request.method === 'GET') return new Response(page, { headers: {
      'content-type': 'text/html; charset=utf-8', 'cache-control': 'no-store',
      'x-robots-tag': 'noindex, nofollow', 'x-content-type-options': 'nosniff',
      'x-frame-options': 'DENY', 'referrer-policy': 'strict-origin-when-cross-origin',
      'content-security-policy': "default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"
    } });
    return new Response('Not found', { status: 404, headers: { 'x-robots-tag': 'noindex, nofollow' } });
  }
};