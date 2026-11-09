# GoTry visual consistency

The Home page is the visual reference for the entire prototype. All eight page outputs now use `src/site-design.js` for the GoTry wordmark, navigation, brand colours, typography, buttons, panels, spacing and responsive breakpoints.

Pages: Home, Explore activities, Provider activity wizard, Account, Parent dashboard, Provider dashboard, Notifications and Admin. The shared header includes the Admin link; server-side authorization remains authoritative. All pages preserve `noindex,nofollow`.

## Verification

Run `npm run check` and `npm run e2e` on a checkout with dependencies installed. `tests/site-design.test.js` asserts that each page contains the shared header, branding, navigation, responsive viewport and noindex metadata. Browser-based desktop/mobile visual comparison remains necessary to check actual layout, overflow, focus and contrast. No production deployment is included.

Avoid introducing separate one-off page headers or hard-coded colour palettes. Put common visual changes in `src/site-design.js` and keep only page-specific styles in individual page files.

## Shared footer and conflict cleanup

All eight pages now render the same `siteFooter` and the final `siteRefinements` stylesheet after their legacy CSS. This final layer resolves conflicting button variants, input sizing, card backgrounds, responsive action rows, multi-column forms and brand colors. The Home page's former inline footer was replaced with a prototype note to avoid duplicate site footers.

When adding a page, import `siteCss`, `siteHeader`, `siteFooter` and `siteRefinements` from `src/site-design.js`. Append refinements **after** local page CSS and render the shared footer once, before `</body>`. The regression in `tests/site-design.test.js` now checks the shared footer and responsive stylesheet across all eight pages.

Manual visual QA is still required on desktop and mobile: compare header wrapping, cards, dialog focus, disabled actions, narrow input grids and footer placement. The assistant validated generated HTML and inline JavaScript syntax but did not execute Playwright or Wrangler.
