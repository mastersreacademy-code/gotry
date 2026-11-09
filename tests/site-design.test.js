import test from 'node:test';
import assert from 'node:assert/strict';
import {page} from '../src/page.js';
import {activitiesPage} from '../src/activities-page.js';
import {authPage} from '../src/auth-page.js';
import {adminPage} from '../src/admin-page.js';
import {notificationsPage} from '../src/notifications-page.js';
import {wizardPage} from '../src/wizard-page.js';
import {parentDashboard,providerDashboard} from '../src/dashboard-pages.js';

const pages={Home:page,Explore:activitiesPage,Account:authPage,Admin:adminPage,Notifications:notificationsPage,Wizard:wizardPage,Parent:parentDashboard,Provider:providerDashboard};
for(const [name,html] of Object.entries(pages)){
 test(name+' uses shared GoTry visual system and noindex',()=>{
  assert.match(html,/class="site-header"/);
  assert.match(html,/class="skip-link" href="#main-content"/);
  assert.match(html,/id="main-content" tabindex="-1"/);
  assert.match(html,/class="site-footer"/);
  assert.match(html,/aria-label="Footer navigation"/);
  assert.match(html,/\.site-footer \.footer-inner/);
  assert.match(html,/@media\(max-width:700px\)/);
  assert.match(html,/class="brand"/);
  assert.match(html,/Go<span>Try<\/span>/);
  assert.match(html,/--pink:#ed2282/);
  assert.match(html,/--blue:#176dd0/);
  assert.match(html,/name="robots" content="noindex,nofollow"/);
  assert.match(html,/name="viewport"/);
  assert.match(html,/href="\/activities"/);
  assert.match(html,/href="\/parent"/);
  assert.match(html,/href="\/provider"/);
 });
}
