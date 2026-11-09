import test from 'node:test';
import assert from 'node:assert/strict';
import worker from '../src/index.js';
test('home links to main pages',async()=>{const r=await worker.fetch(new Request('https://gotry.example/'));const html=await r.text();for(const path of ['/activities','/provider/new','/account'])assert.ok(html.includes('href="'+path+'"'));});
test('discovery route is available and noindexed',async()=>{const r=await worker.fetch(new Request('https://gotry.example/activities'));assert.equal(r.status,200);assert.match(r.headers.get('x-robots-tag'),/noindex/);assert.match(await r.text(),/Explore trial activities/);});
test('public discovery excludes unapproved activities and private fields',async()=>{let sql='';const DB={prepare(s){sql=s;return{bind(){return this;},all:async()=>({results:[{id:'1',title:'Art',data_json:JSON.stringify({category:'Art',description:'Painting',location:'Sydney',email:'secret@example.test',phone:'123'})}]})}}};const r=await worker.fetch(new Request('https://gotry.example/api/activities'),{DB});assert.equal(r.status,200);assert.match(sql,/pending_registrations/);const body=await r.text();assert.ok(!body.includes('secret@example.test'));assert.ok(!body.includes('123'));});

