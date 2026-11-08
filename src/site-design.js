// One visual system for Home, Explore, Wizard, Account, Dashboards, Admin and Notifications.
// Keep styling as HTML/CSS rather than embedding text in images.
export const siteHeader = `<header class="site-header"><a href="/" class="brand" aria-label="GoTry home">Go<span>Try</span></a><nav aria-label="Main navigation"><a href="/">Home</a><a href="/activities">Explore activities</a><a href="/provider/new">For providers</a><a href="/parent">Parent</a><a href="/provider">Provider</a><a href="/notifications">Notifications</a><a href="/account">Account</a></nav></header>`;

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
main{width:100%;max-width:1080px;margin:40px auto;padding:0 24px}
main h1{color:var(--ink);font-size:clamp(32px,5vw,48px);letter-spacing:-1.35px;line-height:1.13;margin:0 0 20px}
main h2,main h3{color:var(--ink);line-height:1.3}
main p{line-height:1.65}
main .panel,main article,main form:not(.filters),main .empty{border:1px solid var(--line);background:#fff;border-radius:var(--radius);box-shadow:0 8px 22px #15315a0b}
main .panel,main article{padding:24px}
main button,main .cta{font:inherit;font-weight:700;border-radius:11px;min-height:44px;padding:11px 18px;background:var(--blue);color:#fff;border:1px solid transparent;cursor:pointer}
main button.secondary,main .cta.secondary{background:#fff;color:var(--blue);border-color:#c8d8ed}
main button.danger{background:#a32d4d;color:#fff}
main button:disabled{opacity:.55;cursor:not-allowed}
main input,main select,main textarea{font:inherit;max-width:100%;border:1px solid #c8d8ed;border-radius:10px;min-height:44px;padding:10px 12px;background:#fff;color:var(--ink)}
main a{color:var(--blue)}
main button:focus-visible,main input:focus-visible,main select:focus-visible,main textarea:focus-visible,a:focus-visible{outline:3px solid #74a7f5;outline-offset:2px}
main .note{border-radius:12px}
@media(max-width:700px){header.site-header{padding:16px 20px;align-items:flex-start;gap:12px}header.site-header nav{gap:10px 14px}header.site-header nav a{font-size:13px}main{margin:26px auto;padding:0 18px}main .panel,main article{padding:18px}main h1{font-size:clamp(30px,8vw,40px)}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
`;
