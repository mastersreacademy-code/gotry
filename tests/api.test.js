import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';
const request=(path,method='GET',body,headers={})=>new Request('https://gotry.example'+path,{method,headers:{...(body?{'content-type':'application/json','origin':'https://gotry.example'}:{}),...headers},body:body?JSON.stringify(body):undefined});
test('API fails closed without configured D1',async()=>{const r=await worker.fetch(request('/api/auth/me'),{});assert.equal(r.status,503);});
test('mutating API rejects cross-origin requests',async()=>{const db={};const r=await worker.fetch(request('/api/auth/register','POST',{email:'test@example.com',password:'verylongpassword',role:'provider'},{origin:'https://evil.example'}),{DB:db});assert.equal(r.status,403);});
test('unauthenticated provider activity API is rejected',async()=>{const db={};const r=await worker.fetch(request('/api/provider/activities'),{DB:db});assert.equal(r.status,401);});
test('registration does not allow self-service admin',async()=>{const db={};const r=await worker.fetch(request('/api/auth/register','POST',{email:'test@example.com',password:'verylongpassword',role:'admin'}),{DB:db});assert.equal(r.status,400);});
test('provider API requires JSON for mutations',async()=>{const r=await worker.fetch(request('/api/provider/activities','POST',undefined,{'origin':'https://gotry.example'}),{DB:{}});assert.equal(r.status,415);});
