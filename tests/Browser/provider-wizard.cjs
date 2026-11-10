// Run against an isolated, migrated Laravel instance. Creates synthetic accounts/listings.
// GOTRY_TEST_URL=http://127.0.0.1:8123 node tests/Browser/provider-wizard.cjs
const {chromium} = require('playwright');
const assert = require('node:assert/strict');

(async () => {
    const base = process.env.GOTRY_TEST_URL;
    if (!base || !['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) throw Error('Use an isolated localhost Laravel server.');
    const browser = await chromium.launch({headless: true, executablePath: process.env.CHROMIUM_PATH || undefined, args: ['--no-sandbox']});
    try {
        for (const individual of [false, true]) {
            const context = await browser.newContext({viewport: individual ? {width: 390, height: 844} : {width: 1280, height: 900}});
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            const fit = async () => assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Page overflows viewport');
            const fill = async data => {
                for (const [name, value] of Object.entries(data)) {
                    const input = page.locator(`[name="${name}"]`);
                    if (await input.evaluate(element => element.tagName === 'SELECT')) await input.selectOption(String(value));
                    else await input.fill(String(value));
                }
            };
            const next = async step => {
                await fit();
                await page.getByRole('button', {name: 'Continue →', exact: true}).click();
                await page.waitForURL(`**/provider/new/${step}`);
            };
            const response = await page.goto(base + '/register');
            assert.equal(response.headers()['x-robots-tag'], 'noindex, nofollow');
            await fill({name: 'Browser Provider', role: 'provider', email: `provider-${Date.now()}-${individual}@example.test`, password: 'browser test password!', password_confirmation: 'browser test password!'});
            await page.getByRole('button', {name: 'Create account', exact: true}).click();
            await page.waitForURL('**/provider');
            await page.getByRole('link', {name: 'List an activity →'}).click();
            await page.waitForURL('**/provider/new/1');
            await fill({business_name: 'Browser Studio', contact_name: 'Private Browser Contact', phone: '0412345678', contact_email: 'private-browser@example.test'});
            await next(2);
            await fill({activity_name: individual ? 'Browser individual trial' : 'Browser group trial', category: 'Music', min_age: 6, max_age: 10, description: 'Learn rhythm and confidence.', venue: 'North Sydney'});
            await next(3);
            const date = new Date(); date.setUTCDate(date.getUTCDate() + 60);
            const day = date.toISOString().slice(0, 10);
            await fill({trial_type: individual ? 'individual' : 'group'});
            if (individual) {
                assert.equal(await page.locator('[name="trial_date"]').isVisible(), false);
                await fill({trial_availability: 'Weekday afternoons'});
            } else await fill({trial_date: day, trial_time: '09:00', trial_min: 8, trial_max: 12});
            await fill({trial_duration: 45, deadline: day});
            await next(4);
            if (individual) {
                await fill({regular_format: 'individual', usual_availability: 'After school', individual_price: '60', discount_type: 'fixed', discount_value: '10', discount_applies: 'lesson'});
                assert.equal(await page.locator('[name="regular_day"]').isVisible(), false);
                assert.match(await page.locator('#price-preview').innerText(), /\$50\.00/);
            } else {
                await fill({regular_format: 'group', multiple_days: '1', class_times: 'Monday 16:00; Saturday 09:00', regular_min: 8, regular_standard: 10, regular_max: 12, standard_price: '20', small_group_price: '25', lessons_per_term: 10, discount_type: 'percent', discount_value: '10', discount_applies: 'term'});
                assert.match(await page.locator('#price-preview').innerText(), /\$180\.00/);
                assert.match(await page.locator('#price-preview').innerText(), /\$225\.00/);
                await fill({discount_type: 'fixed', discount_value: '10'});
                assert.match(await page.locator('#price-preview').innerText(), /\$190\.00/);
                assert.match(await page.locator('#price-preview').innerText(), /\$240\.00/);
                await fill({discount_type: 'percent'});
            }
            await next(5);
            await page.reload();
            await page.getByRole('heading', {name: 'Review your listing'}).waitFor();
            assert.match(await page.locator('main').innerText(), individual ? /\$50\.00/ : /\$180\.00/);
            for (const name of ['child_safety','insurance','honour_terms']) await page.locator(`[name="${name}"]`).check();
            await fit();
            await page.getByRole('button', {name: 'Submit for approval →'}).click();
            await page.waitForURL('**/provider/activities/*');
            assert.match(await page.locator('main').innerText(), /Pending Approval/);
            assert.deepEqual(errors, []);
            await context.close();
            console.log(`PASS: ${individual ? 'mobile individual' : 'desktop group'} signup, all 5 steps, live pricing, review reload and submit`);
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
