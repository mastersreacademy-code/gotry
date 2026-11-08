import {test,expect} from '@playwright/test';
test('provider wizard navigates and preserves draft after reload',async({page})=>{
 await page.goto('/provider/new');
 await expect(page.getByRole('heading',{name:'Create an activity'})).toBeVisible();
 await page.getByLabel('Business name').fill('Sample Academy');
 await page.getByLabel('Contact name').fill('Taylor');
 await page.getByLabel('Email').fill('taylor@example.test');
 await page.getByRole('button',{name:'Continue'}).click();
 await expect(page.getByRole('heading',{name:'Activity'})).toBeVisible();
 await page.reload();
 await expect(page.getByRole('heading',{name:'Create an activity'})).toBeVisible();
 await expect(page.getByLabel('Business name')).toHaveValue('Sample Academy');
});
test('unauthenticated API is blocked',async({request})=>{
 const response=await request.get('/api/auth/me');
 expect(response.status()).toBe(401); // D1 local database is bound in CI
});
