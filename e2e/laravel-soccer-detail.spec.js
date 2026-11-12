import { test, expect } from '@playwright/test';

// Isolated DOM regression checks for the Laravel activity page's public JS/CSS.
// The page is loaded from the app origin, then a minimal activity fragment is used
// so tests do not depend on seeded activities or user accounts.
test('soccer countdown starts hidden, displays time, and hides after expiry', async ({ page }) => {
  await page.clock.install({ time: new Date('2026-11-12T00:00:00Z') });
  await page.route('**/activities', route => route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body></body></html>' }));
  await page.goto('/activities');
  await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1"><div class="discovery-ui activity-page">
    <div class="activity-countdown" data-trial-deadline="2026-11-12T00:01:00Z"
         role="group" aria-label="Time left until the interest deadline" hidden>
      <span><strong data-days></strong>Days</span>
      <span><strong data-hours></strong>Hours</span>
      <span><strong data-minutes></strong>Mins</span>
    </div>
  </div>`);
  // Deliberately omit app.css here: its global [hidden] !important rule
  // would mask a regression in discovery.css itself.
  await page.addStyleTag({ url: '/css/discovery.css' });
  const countdown = page.locator('[data-trial-deadline]');
  await expect(countdown).toBeHidden();
  await expect(countdown).toHaveCSS('display', 'none');

  await page.addScriptTag({ url: '/js/trial-selection.mjs', type: 'module' });
  await expect(countdown).toBeVisible();
  await expect(countdown.locator('[data-days]')).toHaveText('0');
  await expect(countdown.locator('[data-hours]')).toHaveText('00');
  await expect(countdown.locator('[data-minutes]')).toHaveText('01');

  await page.clock.runFor(61_000);
  await expect(countdown).toBeHidden();
  await expect(countdown).toHaveCSS('display', 'none');
});

test('registration and waitlist actions track selected session and recover on pageshow', async ({ page }) => {
  await page.route('**/activities', route => route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body></body></html>' }));
  await page.goto('/activities');
  await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1"><form data-trial-form>
    <select id="trial_session">
      <option value="">Choose a session</option>
      <option value="1" data-intent="register">Open session</option>
      <option value="2" data-intent="waitlist">Full session</option>
    </select>
    <button data-intent="register">Register interest</button>
    <button data-intent="waitlist">Join waitlist</button>
  </form>`);
  await page.addScriptTag({ url: '/js/trial-selection.mjs', type: 'module' });
  const register = page.locator('button[data-intent="register"]');
  const waitlist = page.locator('button[data-intent="waitlist"]');
  await expect(register).toBeDisabled();
  await expect(waitlist).toBeDisabled();
  await expect(register).toBeVisible();
  await expect(waitlist).toBeVisible();

  await page.locator('#trial_session').selectOption('1');
  await expect(register).toBeVisible();
  await expect(register).toBeEnabled();
  await expect(waitlist).toBeHidden();

  await page.locator('#trial_session').selectOption('2');
  await expect(waitlist).toBeVisible();
  await expect(waitlist).toBeEnabled();
  await expect(register).toBeHidden();

  await page.locator('#trial_session').evaluate(el => {
    el.value = '1';
    window.dispatchEvent(new Event('pageshow'));
  });
  await expect(register).toBeEnabled();
  await expect(register).toBeVisible();
  await expect(waitlist).toBeHidden();
});

for (const width of [375, 768, 1440]) {
  test(`soccer activity layout does not overflow at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await page.route('**/activities', route => route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body></body></html>' }));
    await page.goto('/activities');
    await page.setContent(`<meta name="viewport" content="width=device-width, initial-scale=1"><main><div class="discovery-ui activity-page">
      <section class="activity-hero activity-hero-photo">
        <div class="activity-hero-copy"><h1>Junior Soccer</h1>
          <p class="activity-description">Try soccer with a friendly local team</p></div>
        <img alt="" src="/images/soccer-trial.webp" width="1200" height="800">
      </section>
      <section class="panel activity-overview"><h2>Programme overview</h2>
        <dl class="activity-facts"><div><dt>Ages</dt><dd>6–10 years</dd></div>
        <div><dt>Venue</dt><dd>Sydney</dd></div>
        <div><dt>Usual lessons</dt><dd>Multiple class times</dd></div>
        <div><dt>Programme</dt><dd>10 lessons per term</dd></div></dl>
      </section>
      <div class="discovery-detail">
        <section class="panel discovery-trial"><h2>Trial class</h2></section>
        <section class="panel discovery-programme"><h2>Programme price</h2></section>
      </div>
      <div class="activity-final-action"><a class="button" href="#trial">Register interest</a></div>
      <section id="trial" class="panel activity-registration"><h2>Register interest</h2></section>
    </div></main>`);
    await page.addStyleTag({ url: '/css/app.css' });
    await page.addStyleTag({ url: '/css/discovery.css' });
    const metrics = await page.evaluate(() => ({
      content: document.documentElement.scrollWidth,
      viewport: document.documentElement.clientWidth,
    }));
    expect(metrics.content).toBeLessThanOrEqual(metrics.viewport + 2);
    await expect(page.locator('.activity-final-action a')).toHaveAttribute('href', '#trial');
    await page.locator('.activity-final-action a').click();
    await expect(page).toHaveURL(/#trial$/);
  });
}
