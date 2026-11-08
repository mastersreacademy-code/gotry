import {test,expect} from '@playwright/test';
test('D1 registration, login and provider/parent authorization',async({request})=>{
 const suffix=crypto.randomUUID().slice(0,8);
 const password='TestingOnlyPassword123!';
 const post=(path,body,headers={})=>request.post(path,{data:body,headers:{origin:'http://127.0.0.1:8787',...headers}});
 const provider='provider-'+suffix+'@example.test';
 const parent='parent-'+suffix+'@example.test';
 let r=await post('/api/auth/register',{email:provider,password,role:'provider'});expect(r.status()).toBe(201);
 r=await post('/api/auth/register',{email:parent,password,role:'parent'});expect(r.status()).toBe(201);
 r=await post('/api/auth/register',{email:'admin-'+suffix+'@example.test',password,role:'admin'});expect(r.status()).toBe(400);
 r=await post('/api/auth/login',{email:provider,password});expect(r.status()).toBe(200);
 const providerCookie=r.headers()['set-cookie'].split(';')[0];
 const draft={businessName:'Test Studio',contactName:'Alex',email:provider,activityName:'Art Trial',category:'Art',description:'A creative introduction',location:'Sydney',minAge:'5',maxAge:'10',trialType:'group',trialDate:'2027-01-10',minParticipants:'2',maxParticipants:'8',regularDescription:'Weekly lessons',regularPrice:'25'};
 r=await post('/api/provider/activities',draft,{cookie:providerCookie});expect(r.status()).toBe(201);
 expect((await r.json()).status).toBe('pending_approval');
 r=await request.get('/api/provider/activities',{headers:{cookie:providerCookie}});expect(r.status()).toBe(200);
 expect((await r.json()).activities.some(a=>a.title==='Art Trial')).toBeTruthy();
 r=await request.get('/api/admin/activities',{headers:{cookie:providerCookie}});expect(r.status()).toBe(403);
 r=await post('/api/auth/login',{email:parent,password});expect(r.status()).toBe(200);
 const parentCookie=r.headers()['set-cookie'].split(';')[0];
 r=await request.get('/api/provider/activities',{headers:{cookie:parentCookie}});expect(r.status()).toBe(403);
 r=await request.get('/api/admin/activities',{headers:{cookie:parentCookie}});expect(r.status()).toBe(403);
});
test('admin review UI is accessible but API protected',async({page})=>{
 await page.goto('/admin');
 await expect(page.getByRole('heading',{name:'Pending activities'})).toBeVisible();
 await expect(page.getByRole('status')).toContainText('Unauthenticated');
});
