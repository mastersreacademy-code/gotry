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

## Mobile/desktop browser regression

Run `npx playwright test e2e/site-visual-consistency.spec.js` on a local checkout with Wrangler, Playwright and Chromium installed. The spec covers all eight pages at the existing desktop and iPhone 13 project sizes: shared header/footer, noindex, brand colour, focusability, and horizontal overflow. It also opens and closes the Explore registration dialog and checks viewport bounds.

Nested session forms must **not** inherit the standalone card surface styles; the common CSS now restricts that surface treatment to cards and panels. Modal dialogs have capped viewport height, scrolling and responsive input sizing. The Playwright test was authored but not executed in the assistant environment because a runnable Wrangler project checkout was unavailable. The eight template/inline-script syntax checks did execute and passed.

## Accessibility and states (follow-up)

Every page now has a keyboard-visible skip link to the focusable main landmark. The Explore Register Interest button no longer uses inline styling that conflicts with shared button rules. Activity discovery now distinguishes (a) no approved activities, (b) no matches for active filters and (c) failed API requests, with an accessible loading indicator (`aria-busy`). Session-fetch buttons are temporarily disabled during requests to reduce duplicate actions.

Browser regression coverage includes mocked API success/empty/error and no-match filter cases. Run `npx playwright test e2e/site-visual-consistency.spec.js` after installing dependencies and launching the local Wrangler test environment. No browser suite was executed during this change; only actual V8 page-template evaluation and inline-script syntax checks ran.

## Browser environment status and Explore session feedback

Local execution environment check (2026-11-09): Node.js 22.16.0 and Python Playwright are available, but the GoTry repository cannot be cloned because GitHub DNS resolution fails; `wrangler` is not installed; the Playwright-managed Chromium browser is not installed. A system Chromium executable exists at `/usr/bin/chromium`, but this is not a substitute for a runnable GoTry dev server and test data. Do not label Playwright/Wrangler tests as passed.

Explore session registration now surfaces fully booked / unavailable session messages in the visible page status instead of only in the hidden dialog. Successful registration feedback is shown **after** the activities reload, preventing the refreshed count from overwriting confirmation. Playwright regression cases were added but not run. The generated templates and inline scripts were evaluated/compiled successfully in V8 for all eight pages.

## Local browser execution (without GitHub Actions)

The Playwright config now supports the installed system Chromium through `GOTRY_CHROMIUM_PATH` and supports an already-running local Wrangler instance with `GOTRY_EXTERNAL_SERVER=1`. This avoids requiring Playwright's browser download when a compatible system Chromium is present.

On a machine with a GitHub checkout, dependencies and Wrangler installed:

```sh
npm ci
npx wrangler d1 migrations apply gotry-ci --config wrangler.ci.jsonc --local
GOTRY_CHROMIUM_PATH=/usr/bin/chromium npm run e2e:ui
```

The UI suite normally starts local Wrangler using `playwright.config.js`. To use a separately running local server:

```sh
npx wrangler dev --config wrangler.ci.jsonc --local --ip 127.0.0.1 --port 8787
# In another shell:
GOTRY_EXTERNAL_SERVER=1 GOTRY_CHROMIUM_PATH=/usr/bin/chromium npm run e2e:ui
```

On macOS, omit `GOTRY_CHROMIUM_PATH` if Playwright Chromium is installed, or set it to the local Chromium executable. Both desktop Chrome and mobile iPhone-sized Chromium projects are configured. No GitHub Actions CI is required. This is a local-only workflow and never deploys Cloudflare.

**Verification boundary (2026-11-09):** The assistant's container has Node and system Chromium but cannot resolve github.com to clone the repository, has no cached Wrangler package, and cannot start the real Worker/D1. Browser tests remain **not run**. Source-template JavaScript checks are separate and do not prove browser layout. The mobile header now scrolls navigation horizontally rather than growing into multiple rows; confirm it in the browser suite.
