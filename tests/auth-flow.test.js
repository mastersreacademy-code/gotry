import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';
import { authPage } from '../src/auth-page.js';

const origin='https://gotry.example';
function request(path,method='GET',body,headers={}){
 return new Request(origin+path,{method,headers:{...(body?{'content-type':'application/json',origin}:{}),...headers},body:body?JSON.stringify(body):undefined});
}

test('account page serves both auth modes, role selector and accessible controls',async()=>{
 const response=await worker.fetch(request('/account'));
 assert.equal(response.status,200);
 assert.match(response.headers.get('x-robots-tag'),/noindex/);
 const html=await response.text();
 for(const fragment of ['id="login-tab"','id="signup-tab"','value="parent"','value="provider"','id="confirm-password"','id="toggle-password"','aria-live="polite"','@media(max-width:790px)'])assert.ok(html.includes(fragment),fragment);
 assert.match(html,/mode==='register'/);
 assert.match(html,/location.assign\(data.user.role/);
});

test('self-service signup rejects admin role and weak passwords before database queries',async()=>{
 const db={};
 for(const body of [{email:'admin@example.test',password:'longpassword123',role:'admin'},{email:'parent@example.test',password:'short',role:'parent'},{email:'bad-email',password:'longpassword123',role:'provider'}]){
  const r=await worker.fetch(request('/api/auth/register','POST',body),{DB:db});
  assert.equal(r.status,400);
 }
});

test('cross-origin signup and login are blocked',async()=>{
 for(const path of ['/api/auth/register','/api/auth/login','/api/auth/logout']){
  const r=await worker.fetch(request(path,'POST',{email:'parent@example.test',password:'longpassword123',role:'parent'},{origin:'https://attacker.example'}),{DB:{}});
  assert.equal(r.status,403);
 }
});

test('signup fails closed without database',async()=>{
 const r=await worker.fetch(request('/api/auth/register','POST',{email:'parent@example.test',password:'longpassword123',role:'parent'}),{});
 assert.equal(r.status,503);
});
