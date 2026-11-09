// Run with: node --disable-warning=ExperimentalWarning --test tests/auth-sqlite.integration.test.js
// Uses Node's built-in SQLite engine and the actual worker/API and SQL migrations.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { DatabaseSync } from 'node:sqlite';
import worker from '../src/index.js';

const origin='https://gotry.example';
function fixture(){
 const sqlite=new DatabaseSync(':memory:');
 const migrationDir=new URL('../migrations/',import.meta.url);
 const migrations=readdirSync(migrationDir).filter(name=>/^\\d+_.*\\.sql$/.test(name)).sort();
 assert.equal(migrations.length,13,'Review new migrations before changing expected count');
 sqlite.exec('PRAGMA foreign_keys=ON');
 for(const migration of migrations)sqlite.exec(readFileSync(new URL(migration,migrationDir),'utf8'));
 assert.deepEqual(sqlite.prepare('PRAGMA foreign_key_check').all(),[]);
 const prepare=(sql)=>({
  bind(...args){const statement=sqlite.prepare(sql);return {
   first:async()=>statement.get(...args)||null,
   all:async()=>({results:statement.all(...args)}),
   run:async()=>({meta:{changes:statement.run(...args).changes}})
  };}
 });
 const db={prepare,async batch(queries){
  sqlite.exec('BEGIN IMMEDIATE');
  try{const result=[];for(const q of queries)result.push(await q.run());sqlite.exec('COMMIT');return result;}
  catch(e){sqlite.exec('ROLLBACK');throw e;}
 }};
 return {db,sqlite};
}
function request(path,method='GET',body,cookie){
 const headers={};if(method!=='GET'){headers.origin=origin;headers['content-type']='application/json';}
 if(cookie)headers.cookie=cookie;
 return new Request(origin+path,{method,headers,body:body===undefined?undefined:JSON.stringify(body)});
}
async function call(db,path,method='GET',body,cookie){return worker.fetch(request(path,method,body,cookie),{DB:db});}
const credentials=(role,email)=>({role,email,password:'a very long password 123!'});

for(const role of ['parent','provider']){
 test(role+' signup, duplicate, login, session, logout, and expiry against real SQLite',async()=>{
  const {db,sqlite}=fixture();
  try{
   const body=credentials(role,role+'@example.test');
   const signup=await call(db,'/api/auth/register','POST',body);
   assert.equal(signup.status,201,await signup.clone().text());
   const data=await signup.json();assert.equal(data.user.role,role);
   const setCookie=signup.headers.get('set-cookie');
   assert.match(setCookie,/^gotry_session=[a-f0-9]{64};/);
   for(const flag of ['HttpOnly','Secure','SameSite=Lax','Path=/','Max-Age=86400'])assert.ok(setCookie.includes(flag),flag);
   const cookie=setCookie.split(';')[0];
   const me=await call(db,'/api/auth/me','GET',undefined,cookie);
   assert.equal(me.status,200);assert.equal((await me.json()).user.role,role);
   const duplicate=await call(db,'/api/auth/register','POST',body);
   assert.equal(duplicate.status,409);
   const invalid=await call(db,'/api/auth/login','POST',{email:body.email,password:'wrong'});
   assert.equal(invalid.status,401);
   const login=await call(db,'/api/auth/login','POST',{email:body.email,password:body.password});
   assert.equal(login.status,200);
   assert.equal((await login.json()).user.role,role);
   const loginCookie=login.headers.get('set-cookie').split(';')[0];
   const logout=await call(db,'/api/auth/logout','POST',{},loginCookie);
   assert.equal(logout.status,200);
   assert.match(logout.headers.get('set-cookie'),/Max-Age=0/);
   assert.equal((await call(db,'/api/auth/me','GET',undefined,loginCookie)).status,401);
   sqlite.prepare('UPDATE sessions SET expires_at=0 WHERE user_id=?').run(data.user.id);
   assert.equal((await call(db,'/api/auth/me','GET',undefined,cookie)).status,401);
  }finally{sqlite.close();}
 });
}
test('SQLite rollback prevents orphaned account when session insert fails',async()=>{
 const {db,sqlite}=fixture();
 try{
  sqlite.exec('CREATE TRIGGER fail_new_session BEFORE INSERT ON sessions BEGIN SELECT RAISE(ABORT, \'session insert blocked\'); END;');
  const result=await call(db,'/api/auth/register','POST',credentials('parent','rollback@example.test'));
  assert.equal(result.status,500);
  assert.equal(sqlite.prepare('SELECT COUNT(*) AS n FROM users').get().n,0);
 }finally{sqlite.close();}
});
test('parent and provider cannot access each other\'s protected API',async()=>{
 const {db,sqlite}=fixture();
 try{
  const sessions={};
  for(const role of ['parent','provider']){
   const r=await call(db,'/api/auth/register','POST',credentials(role,role+'2@example.test'));
   assert.equal(r.status,201);sessions[role]=r.headers.get('set-cookie').split(';')[0];
  }
  assert.equal((await call(db,'/api/provider/activities','GET',undefined,sessions.parent)).status,403);
  assert.equal((await call(db,'/api/parent/interests','GET',undefined,sessions.provider)).status,403);
 }finally{sqlite.close();}
});

test('login locks after five failed attempts, then blocks even the correct password',async()=>{
 const {db,sqlite}=fixture();
 try{
  const body=credentials('parent','locked@example.test');
  assert.equal((await call(db,'/api/auth/register','POST',body)).status,201);
  for(let n=0;n<5;n++){
   const r=await call(db,'/api/auth/login','POST',{email:body.email,password:'incorrect password'});
   assert.equal(r.status,401,'failed attempt '+(n+1));
  }
  const blocked=await call(db,'/api/auth/login','POST',{email:body.email,password:body.password});
  assert.equal(blocked.status,429);
  sqlite.prepare('UPDATE auth_attempts SET blocked_until=0 WHERE email_hash IS NOT NULL').run();
  const success=await call(db,'/api/auth/login','POST',{email:body.email,password:body.password});
  assert.equal(success.status,200);
  assert.equal(sqlite.prepare('SELECT COUNT(*) AS n FROM auth_attempts').get().n,0);
 }finally{sqlite.close();}
});
test('duplicate email is case-insensitive and cannot change account role',async()=>{
 const {db,sqlite}=fixture();
 try{
  const first=await call(db,'/api/auth/register','POST',credentials('parent','Same@Example.Test'));
  assert.equal(first.status,201);
  const second=await call(db,'/api/auth/register','POST',credentials('provider','same@example.test'));
  assert.equal(second.status,409);
  assert.equal(sqlite.prepare('SELECT COUNT(*) AS n FROM users').get().n,1);
  assert.equal(sqlite.prepare('SELECT role FROM users').get().role,'parent');
 }finally{sqlite.close();}
});
test('forged, expired, and revoked session cookies are rejected',async()=>{
 const {db,sqlite}=fixture();
 try{
  const created=await call(db,'/api/auth/register','POST',credentials('provider','cookies@example.test'));
  assert.equal(created.status,201);
  const cookie=created.headers.get('set-cookie').split(';')[0];
  assert.equal((await call(db,'/api/auth/me','GET',undefined,'gotry_session='+('f'.repeat(64)))).status,401);
  assert.equal((await call(db,'/api/auth/me','GET',undefined,'gotry_session=invalid')).status,401);
  assert.equal((await call(db,'/api/auth/me','GET',undefined,cookie)).status,200);
  assert.equal((await call(db,'/api/auth/logout','POST',{},cookie)).status,200);
  assert.equal((await call(db,'/api/auth/me','GET',undefined,cookie)).status,401);
 }finally{sqlite.close();}
});
