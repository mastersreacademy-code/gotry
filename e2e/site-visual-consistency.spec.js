import {test,expect} from '@playwright/test';

const pages=['/','/activities','/provider/new','/account','/parent','/provider','/notifications','/admin'];

for(const path of pages){
 test(path+' shared navigation/footer, no horizontal overflow and keyboard focus',async({page})=>{
  await page.goto(path);
  await expect(page.locator('header.site-header .brand')).toBeVisible();
  await expect(page.locator('footer.site-footer')).toBeVisible();
  await expect(page.locator('footer.site-footer')).toHaveCount(1);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content','noindex,nofollow');
  await expect(page.locator('header.site-header nav a[href="/activities"]')).toBeVisible();
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
  const focused=await page.evaluate(()=>document.activeElement?.tagName);
  expect(['A','BUTTON','INPUT','SELECT']).toContain(focused);
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
