import {test,expect} from '@playwright/test';

const pages=['/','/activities','/provider/new','/account','/parent','/provider','/notifications','/admin'];

for(const path of pages){
 test(path+' shared navigation/footer, no horizontal overflow and keyboard focus',async({page})=>{
  await page.goto(path);
  await expect(page.locator('header.site-header .brand')).toBeVisible();
  await expect(page.locator('footer.site-footer')).toBeVisible();
  await expect(page.locator('.skip-link')).toHaveAttribute('href','#main-content');
  await expect(page.locator('main#main-content')).toHaveCount(1);
  await expect(page.locator('footer.site-footer')).toHaveCount(1);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content','noindex,nofollow');
  await expect(page.locator('header.site-header nav a[href="/activities"]')).toBeVisible();
  await expect(page.locator('header.site-header nav a[aria-current="page"]')).toHaveCount(1);
  await expect(page.locator('header.site-header nav a[aria-current="page"]')).toHaveAttribute('href',path);
  const metrics=await page.evaluate(()=>({
   viewport:document.documentElement.clientWidth,
   content:document.documentElement.scrollWidth,
   brand: getComputedStyle(document.querySelector('header.site-header .brand span')).color,
   footer: getComputedStyle(document.querySelector('footer.site-footer')).backgroundColor
  }));
  expect(metrics.content,'horizontal overflow at '+path).toBeLessThanOrEqual(metrics.viewport+2);
  expect(metrics.brand).toBe('rgb(237, 34, 130)');
  expect(metrics.footer).toBe('rgb(255, 255, 255)');
  await page.keyboard.press('Tab');
  await expect(page.locator('.skip-link')).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#main-content')).toBeFocused();
 });
}

test('Explore dialog fits viewport and has a usable close action',async({page})=>{
 await page.goto('/activities');
 const dialog=page.locator('#interestDialog');
 await expect(dialog).toHaveCount(1);
 await dialog.evaluate(el=>el.showModal());
 await expect(dialog).toBeVisible();
 const box=await dialog.boundingBox();
 const viewport=page.viewportSize();
 expect(box).not.toBeNull();
 expect(box.width).toBeLessThanOrEqual(viewport.width+2);
 expect(box.height).toBeLessThanOrEqual(viewport.height+2);
 const cancel=dialog.getByRole('button',{name:/cancel/i});
 await expect(cancel).toBeVisible();
 await cancel.click();
 await expect(dialog).not.toBeVisible();
});

test('Explore empty and failed API states are understandable',async({page})=>{
 await page.route('**/api/activities',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({activities:[]})}));
 await page.goto('/activities');
 await expect(page.locator('#cards .empty')).toContainText('New trial activities');
 await expect(page.locator('#cards')).toHaveAttribute('aria-busy','false');
 await page.route('**/api/activities',route=>route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({error:'Service temporarily unavailable'})}));
 await page.reload();
 await expect(page.locator('#status')).toContainText('Service temporarily unavailable');
 await expect(page.locator('#cards .empty')).toContainText('Could not load activities');
 await expect(page.locator('#cards')).toHaveAttribute('aria-busy','false');
});

test('Explore search shows no-match guidance',async({page})=>{
 await page.route('**/api/activities',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({activities:[{id:'demo',title:'Soccer',description:'Outdoor sport',category:'Sports',location:'Sydney'}]})}));
 await page.goto('/activities');
 await page.locator('#search').fill('zzzz-unmatched');
 await expect(page.locator('#cards .empty')).toContainText('Clear the filters');
 await expect(page.locator('#status')).toContainText('No activities match');
});

test('Explore shows fully booked session feedback without opening an invisible dialog',async({page})=>{
 await page.route('**/api/activities',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({activities:[{id:'demo',title:'Soccer',description:'Sports trial',category:'Sports',location:'Sydney',hasSessions:false}]})}));
 await page.route('**/api/trial-sessions?**',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({sessions:[{id:'full',starts_at:'2099-01-01',venue:'Sydney',remaining:0}],hasSessions:true})}));
 await page.goto('/activities');
 await page.getByRole('button',{name:'Register interest'}).click();
 await expect(page.locator('#status')).toContainText('All available sessions are full');
 await expect(page.locator('#interestDialog')).not.toBeVisible();
 await expect(page.getByRole('button',{name:'Register interest'})).toBeEnabled();
});

test('Explore reports missing open sessions without opening dialog',async({page})=>{
 await page.route('**/api/activities',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({activities:[{id:'demo',title:'Soccer',description:'Sports trial',category:'Sports',location:'Sydney',hasSessions:false}]})}));
 await page.route('**/api/trial-sessions?**',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({sessions:[],hasSessions:true})}));
 await page.goto('/activities');
 await page.getByRole('button',{name:'Register interest'}).click();
 await expect(page.locator('#status')).toContainText('No open trial sessions');
 await expect(page.locator('#interestDialog')).not.toBeVisible();
});

test('mobile navigation is scrollable without widening the document',async({page,isMobile})=>{
 test.skip(!isMobile,'Mobile-only navigation behavior');
 await page.goto('/');
 const nav=page.locator('header.site-header nav');
 const dimensions=await nav.evaluate(el=>({scrollWidth:el.scrollWidth,clientWidth:el.clientWidth,overflow:getComputedStyle(el).overflowX}));
 expect(dimensions.overflow).toBe('auto');
 expect(dimensions.scrollWidth).toBeGreaterThanOrEqual(dimensions.clientWidth);
 await nav.locator('a[href="/admin"]').focus();
 await expect(nav.locator('a[href="/admin"]')).toBeFocused();
 const pageWidth=await page.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth);
 expect(pageWidth).toBeLessThanOrEqual(2);
});
