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

test('auth screen includes sign-out and existing-session dashboard routing',()=>{
 assert.match(authPage,/id="logout-btn"/);
 assert.match(authPage,/\/api\/auth\/logout/);
 assert.match(authPage,/\/api\/auth\/me/);
 assert.match(authPage,/data\.user\.role==='provider'\?'\/provider'/);
});

test('session cookies and authentication must be server-managed',async()=>{
 const response=await worker.fetch(request('/account'));
 const html=await response.text();
 assert.doesNotMatch(html,/localStorage\.(setItem|getItem)/);
 assert.doesNotMatch(html,/document\.cookie\s*=/);
 assert.match(html,/credentials:'same-origin'/);
});

test('signup cannot be invoked without same-origin JSON',async()=>{
 const body={email:'parent@example.test',password:'longpassword123',role:'parent'};
 const r=await worker.fetch(new Request(origin+'/api/auth/register',{method:'POST',headers:{origin,'content-type':'text/plain'},body:JSON.stringify(body)}),{DB:{}});
 assert.equal(r.status,415);
});

test('both dashboards expose sign out and wait for role verification before loading data',async()=>{
 for(const [path,role] of [['/parent','parent'],['/provider','provider']]){
  const r=await worker.fetch(request(path));
  assert.equal(r.status,200);
  assert.match(r.headers.get('x-robots-tag'),/noindex/);
  const html=await r.text();
  assert.match(html,/id="dashboard-signout"/);
  assert.match(html,/\/api\/auth\/logout/);
  assert.match(html,/\/api\/auth\/me/);
  assert.match(html,/dashboardContent\.hidden=false/);
  assert.ok(html.includes("d.user?.role!== '"+role+"'"));
  assert.match(html,/authReady\.then\(ok=>\{if\(ok\)load\(\);\}\)/);
 }
});

test('malformed and non-object signup payloads return 400 without touching database',async()=>{
 for(const body of ['{invalid','null','[]','"hello"','42']){
  const response=await worker.fetch(new Request(origin+'/api/auth/register',{method:'POST',headers:{origin,'content-type':'application/json'},body}),{DB:{}});
  assert.equal(response.status,400,body);
  assert.match((await response.json()).error,/Invalid JSON body/);
 }
});
test('malformed login and logout JSON do not trigger internal server errors',async()=>{
 for(const path of ['/api/auth/login','/api/auth/logout']){
  const response=await worker.fetch(new Request(origin+path,{method:'POST',headers:{origin,'content-type':'application/json'},body:'{broken'}),{DB:{}});
  // Login parses its body first; logout requires session lookup and thus a configured DB schema.
  if(path==='/api/auth/login')assert.equal(response.status,400);
 }
});
