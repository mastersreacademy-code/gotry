// One visual system for Home, Explore, Wizard, Account, Dashboards, Admin and Notifications.
// Keep styling as HTML/CSS rather than embedding text in images.
export const siteHeader = `<a class="skip-link" href="#main-content">Skip to main content</a><header class="site-header"><a href="/" class="brand" aria-label="GoTry home">Go<span>Try</span></a><nav aria-label="Main navigation"><a href="/">Home</a><a href="/activities">Explore activities</a><a href="/provider/new">For providers</a><a href="/parent">Parent</a><a href="/provider">Provider</a><a href="/notifications">Notifications</a><a href="/account">Account</a><a href="/admin">Admin</a></nav></header>`;

// Mark the current destination without client-side scripts (Home has a restrictive CSP).
export function siteHeaderFor(path) {
  const allowed = ['/', '/activities', '/provider/new', '/parent', '/provider', '/notifications', '/account', '/admin'];
  if (!allowed.includes(path)) return siteHeader;
  const navStart = siteHeader.indexOf('<nav aria-label="Main navigation">');
  const prefix = siteHeader.slice(0, navStart);
  const nav = siteHeader.slice(navStart);
  return prefix + nav.replace('href="' + path + '"', 'href="' + path + '" aria-current="page"');
}

export const siteCss = `
:root{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#10234a;background:#f8fbff;--ink:#10234a;--muted:#627089;--blue:#176dd0;--pink:#ed2282;--line:#e3e9f2;--paper:#fff;--radius:16px}
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:#f8fbff;color:var(--ink);font:16px/1.6 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
header.site-header{background:#fff;border-bottom:1px solid var(--line);padding:20px max(24px,calc((100vw - 1080px)/2));display:flex;align-items:center;justify-content:space-between;gap:22px;flex-wrap:wrap}
.site-header .brand{font-size:27px;font-weight:850;letter-spacing:-1.2px;color:#126dd4;text-decoration:none;line-height:1.2;white-space:nowrap}
.site-header .brand span{color:var(--pink)}
.site-header nav{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.site-header nav a{color:#28476d;text-decoration:none;font-weight:650;font-size:14px}
.site-header nav a:hover{color:var(--blue);text-decoration:underline}
main{width:100%;max-width:1080px;margin:40px auto;padding:0 24px}main.home-main{margin:0 auto;padding:72px 24px}
main h1{color:var(--ink);font-size:clamp(32px,5vw,48px);letter-spacing:-1.35px;line-height:1.13;margin:0 0 20px}
main h2,main h3{color:var(--ink);line-height:1.3}
main p{line-height:1.65}
main .panel,main article,main .empty{border:1px solid var(--line);background:#fff;border-radius:var(--radius);box-shadow:0 8px 22px #15315a0b}
main .panel,main article{padding:24px}
main button,main .cta{font:inherit;font-weight:700;border-radius:11px;min-height:44px;padding:11px 18px;background:var(--blue);color:#fff;border:1px solid transparent;cursor:pointer}
main button.secondary,main .cta.secondary{background:#fff;color:var(--blue);border-color:#c8d8ed}
main button.danger{background:#a32d4d;color:#fff}
main button:disabled{opacity:.55;cursor:not-allowed}
main input,main select,main textarea{font:inherit;max-width:100%;border:1px solid #c8d8ed;border-radius:10px;min-height:44px;padding:10px 12px;background:#fff;color:var(--ink)}
main a{color:var(--blue)}
main button:focus-visible,main input:focus-visible,main select:focus-visible,main textarea:focus-visible,a:focus-visible{outline:3px solid #74a7f5;outline-offset:2px}
main .note{border-radius:12px}
@media(max-width:700px){header.site-header{padding:16px 20px;align-items:flex-start;gap:12px}header.site-header nav{gap:10px 14px}header.site-header nav a{font-size:13px}main{margin:26px auto;padding:0 18px}main.home-main{margin:0 auto;padding:42px 18px}main .panel,main article{padding:18px}main h1{font-size:clamp(30px,8vw,40px)}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
`;

export const siteFooter = `<footer class="site-footer"><div class="footer-inner"><a href="/" class="brand" aria-label="GoTry home">Go<span>Try</span></a><p>Try before you enrol. Early prototype — no live bookings or payments.</p><nav aria-label="Footer navigation"><a href="/">Home</a><a href="/activities">Explore</a><a href="/provider/new">For providers</a><a href="/account">Account</a></nav></div></footer>`;
export const siteRefinements = `
/* Shared refinements are appended last, after each page's legacy CSS. */
.skip-link{position:absolute;left:12px;top:-100px;z-index:1000;padding:10px 16px;border-radius:10px;background:#fff;color:#10234a;font-weight:750;box-shadow:0 3px 14px #10234a33}
.skip-link:focus{top:10px}
main:focus{outline:none}
:root{color-scheme:light}
body{min-width:0;overflow-wrap:break-word}
header.site-header{min-width:0}
header.site-header nav{min-width:0}
header.site-header nav a{white-space:nowrap}
header.site-header nav{row-gap:10px}
header.site-header nav a{min-height:36px;display:inline-flex;align-items:center}
header.site-header nav a:focus-visible{border-radius:5px}
main{min-width:0}
main .panel,main article,main form{min-width:0}
main .panel,main article{border-color:var(--line);border-radius:var(--radius);background:var(--paper)}
main h1{color:var(--ink)}
main button:not(.danger):not(.secondary){background:var(--blue);color:#fff}
main button.secondary{background:#fff;color:var(--blue);border:1px solid #c8d8ed}
main button.danger{background:#a32d4d;color:#fff}
main button{min-height:44px;border-radius:11px;font-weight:700}
main input,main select,main textarea{min-height:44px;border-radius:10px;border-color:#c8d8ed;color:var(--ink)}
main .actions{display:flex;flex-wrap:wrap;gap:12px}
main .actions>*{min-width:0}
main .note{background:#fff5d8;color:#63470c;padding:16px;border-radius:12px}
main .empty{border:1px dashed #bac9da;box-shadow:none}
main .pill{border-radius:999px}
main .meter{overflow:hidden;border-radius:999px}
main form{max-width:100%;min-width:0}
main form input,main form select,main form textarea{max-width:100%}
main .filters{min-width:0}
main .register-interest{width:100%;margin-top:auto}
main #cards>.empty{grid-column:1/-1;margin:0}
main [aria-busy=true]{opacity:.8}
main [role=status],main [role=alert]{overflow-wrap:anywhere}
main dialog{max-width:min(94vw,480px);max-height:min(88dvh,760px);overflow:auto;border:1px solid var(--line);border-radius:18px;padding:clamp(18px,4vw,28px);box-shadow:0 24px 70px #142b5260;color:var(--ink)}
main dialog::backdrop{background:#0f244b99}
main dialog .actions{justify-content:flex-end}
main dialog input,main dialog select{width:100%}
main .session-form,main .fields{min-width:0}
main .session-form>*,main .fields>*{min-width:0}
main .session-form input,main .session-form select,main .fields input,main .fields select{width:100%}
main #status[role=status],main [role=alert]{overflow-wrap:anywhere}
.site-header nav a[aria-current=page]{color:var(--blue);text-decoration:underline;text-underline-offset:5px}
.site-footer{margin-top:70px;border-top:1px solid var(--line);background:#fff;color:var(--muted)}
.site-footer .footer-inner{max-width:1080px;margin:0 auto;padding:30px 24px;display:flex;align-items:center;gap:16px 28px;flex-wrap:wrap}
.site-footer .brand{font-size:23px;font-weight:850;letter-spacing:-1px;color:#126dd4;text-decoration:none}
.site-footer .brand span{color:var(--pink)}
.site-footer p{margin:0;flex:1 1 250px;font-size:14px}
.site-footer nav{display:flex;flex-wrap:wrap;gap:12px 18px}
.site-footer nav a{color:#28476d;font-size:14px;text-decoration:none;font-weight:650}
.site-footer nav a:hover{text-decoration:underline}
@media(max-width:700px){header.site-header{display:block}header.site-header nav{display:flex;flex-wrap:nowrap;overflow-x:auto;overscroll-behavior-x:contain;scrollbar-width:thin;gap:16px;padding:8px 0 5px;max-width:100%;-webkit-overflow-scrolling:touch}header.site-header nav a{flex:0 0 auto;min-height:44px}header.site-header .brand{display:inline-block;margin-bottom:4px}main dialog{max-height:90dvh}main .filters>*{min-width:0;max-width:100%}.site-footer{margin-top:42px}.site-footer .footer-inner{padding:24px 18px;align-items:flex-start}.site-footer nav{width:100%}main .actions>button{max-width:100%}main .fields{grid-template-columns:minmax(0,1fr)}main .transfer-form{grid-template-columns:minmax(0,1fr)}}

/* Cross-page component polish: predictable sizing, readable states and mobile-safe content. */
.site-header{position:relative;z-index:2}
.site-header nav a{border-radius:8px;padding:4px 6px;transition:background-color .15s ease,color .15s ease}
.site-header nav a:hover,.site-header nav a:focus-visible{background:#edf5ff}
.site-header nav a[aria-current=page]{background:#eaf3ff;text-decoration:none;font-weight:800}
main .panel,main article{transition:box-shadow .15s ease,border-color .15s ease}
main article:hover{border-color:#c9d9ed;box-shadow:0 12px 28px #15315a14}
main .cta{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;gap:8px}
main button:not(:disabled):hover,main .cta:hover{filter:brightness(.96)}
main button:disabled,main [aria-disabled=true]{cursor:not-allowed;opacity:.55}
main :is(input,select,textarea):invalid:not(:placeholder-shown){border-color:#bd3659}
main :is(input,select,textarea):focus-visible{border-color:var(--blue)}
main [role=alert]{border-left:4px solid #b63854;padding:10px 14px;background:#fff2f5;border-radius:8px}
main [role=status]{line-height:1.55}
main :is(table,pre,code){max-width:100%}
main table{display:block;overflow-x:auto;border-collapse:collapse}
main th,main td{text-align:left;vertical-align:top}
main img,main svg,main video{max-width:100%;height:auto}
.site-footer nav a{padding:4px 0}
@media(max-width:700px){.site-header nav{margin-inline:-4px}.site-header nav a{padding:7px 10px}.site-header nav a[aria-current=page]{background:#eaf3ff}.site-footer .footer-inner{gap:14px}main :is(button,.cta){max-width:100%}main .panel,main article{overflow-wrap:anywhere}}
@media(prefers-reduced-motion:reduce){.site-header nav a,main .panel,main article{transition:none}}
`;
