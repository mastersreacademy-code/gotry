import { test, expect } from '@playwright/test';

test('public discovery is responsive and never indexed',async({page})=>{
 await page.goto('/activities');
 await expect(page.locator('body')).toBeVisible();
 const robots=await page.locator('meta[name="robots"]').getAttribute('content');
 expect(robots||'').toMatch(/noindex/i);
 const width=await page.evaluate(()=>({scroll:document.documentElement.scrollWidth,viewport:window.innerWidth}));
 expect(width.scroll).toBeLessThanOrEqual(width.viewport+2);
});

test('private roster and admin endpoints are inaccessible anonymously',async({request})=>{
 const id='00000000-0000-4000-8000-000000000001';
 for(const path of ['/api/provider/sessions/'+id+'/roster','/api/admin/sessions','/api/parent/interests']){
  const response=await request.get(path);
  expect(response.status(),path).toBe(401);
 }
});

test('anonymous users cannot transfer or withdraw interest',async({request})=>{
 const id='00000000-0000-4000-8000-000000000001';
 for(const action of ['transfer','withdraw']){
  const response=await request.post('/api/parent/interests/'+id+'/'+action,{data:{sessionId:id}});
  expect(response.status(),action).toBe(401);
 }
});
