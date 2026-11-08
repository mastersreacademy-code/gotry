import {validateStep,safeDraft} from './wizard.js';
const encoder=new TextEncoder();
const json=(data,status=200,extra={})=>Response.json(data,{status,headers:{'cache-control':'no-store','x-robots-tag':'noindex, nofollow',...extra}});
const bad=(message,status=400)=>json({error:message},status);
const cookieName='gotry_session';
const hex=buffer=>Array.from(new Uint8Array(buffer),x=>x.toString(16).padStart(2,'0')).join('');
async function sha256(s){return hex(await crypto.subtle.digest('SHA-256',encoder.encode(s)));}
async function passwordHash(password,salt=crypto.randomUUID()){const key=await crypto.subtle.importKey('raw',encoder.encode(password),'PBKDF2',false,['deriveBits']);const bits=await crypto.subtle.deriveBits({name:'PBKDF2',salt:encoder.encode(salt),iterations:210000,hash:'SHA-256'},key,256);return salt+':'+hex(bits);}
async function passwordMatches(password,stored){if(!stored||!stored.includes(':'))return false;const salt=stored.split(':')[0];const candidate=await passwordHash(password,salt);const a=encoder.encode(candidate),b=encoder.encode(stored);if(a.length!==b.length)return false;let mismatch=0;for(let i=0;i<a.length;i++)mismatch|=a[i]^b[i];return mismatch===0;}
function sessionCookie(token,maxAge){return cookieName+'='+token+'; Path=/; HttpOnly; Secure; SameSite=Lax; Max-Age='+maxAge;}
async function readBody(req){const length=Number(req.headers.get('content-length')||0);if(length>20000)throw Error('Request too large');const raw=await req.text();if(raw.length>20000)throw Error('Request too large');return JSON.parse(raw);}
async function currentUser(req,db){const cookie=req.headers.get('cookie')||'';const token=cookie.split(';').map(x=>x.trim()).find(x=>x.startsWith(cookieName+'='))?.slice(cookieName.length+1);if(!token||!/^[a-f0-9]{64}$/.test(token))return null;return db.prepare('SELECT u.id,u.email,u.role FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>?').bind(await sha256(token),Math.floor(Date.now()/1000)).first();}
function sameOrigin(req){const origin=req.headers.get('origin');if(!origin)return false;try{return new URL(origin).origin===new URL(req.url).origin;}catch{return false;}}
export async function handleApi(req,env){
 const db=env.DB;if(!db)return bad('Database is not configured',503);
 const url=new URL(req.url),path=url.pathname,method=req.method;
 if(!['GET','POST','PATCH'].includes(method))return bad('Method not allowed',405);
 if(method!=='GET'&&!sameOrigin(req))return bad('Origin verification failed',403);
 if(method!=='GET'&&!req.headers.get('content-type')?.toLowerCase().startsWith('application/json'))return bad('JSON required',415);
 try{
 if(path==='/api/activities'&&method==='GET'){
  const rows=await db.prepare("SELECT id,title,data_json FROM activities WHERE status='pending_registrations' ORDER BY created_at DESC LIMIT 100").all();
  return json({activities:(rows.results||[]).map(a=>{const data=JSON.parse(a.data_json);return {id:a.id,title:a.title,category:data.category,description:data.description,location:data.location,minAge:data.minAge,maxAge:data.maxAge};})});
 }
 if(path==='/api/auth/register'&&method==='POST'){
  const body=await readBody(req),email=String(body.email||'').trim().toLowerCase(),password=String(body.password||''),role=body.role;
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)||email.length>254||password.length<12||password.length>128||!['parent','provider'].includes(role))return bad('Valid email, role and password of 12-128 characters required');
  const existing=await db.prepare('SELECT id FROM users WHERE email=?').bind(email).first();if(existing)return bad('Account already exists',409);
  await db.prepare('INSERT INTO users(id,email,password_hash,role) VALUES(?,?,?,?)').bind(crypto.randomUUID(),email,await passwordHash(password),role).run();
  return json({ok:true},201);
 }
 if(path==='/api/auth/login'&&method==='POST'){
  const body=await readBody(req),email=String(body.email||'').trim().toLowerCase(),password=String(body.password||'');
  const user=await db.prepare('SELECT id,email,role,password_hash FROM users WHERE email=?').bind(email).first();
  if(!user||!await passwordMatches(password,user.password_hash))return bad('Invalid credentials',401);
  const token=hex(crypto.getRandomValues(new Uint8Array(32))),hash=await sha256(token),expiry=Math.floor(Date.now()/1000)+86400;
  await db.prepare('INSERT INTO sessions(token_hash,user_id,expires_at) VALUES(?,?,?)').bind(hash,user.id,expiry).run();
  return json({user:{id:user.id,email:user.email,role:user.role}},200,{'set-cookie':sessionCookie(token,86400)});
 }
 const user=await currentUser(req,db);
 if(path==='/api/auth/me'&&method==='GET')return user?json({user}):bad('Unauthenticated',401);
 if(path==='/api/auth/logout'&&method==='POST'){
  const cookie=req.headers.get('cookie')||'';const token=cookie.split(';').map(x=>x.trim()).find(x=>x.startsWith(cookieName+'='))?.slice(cookieName.length+1);
  if(token&&/^[a-f0-9]{64}$/.test(token))await db.prepare('DELETE FROM sessions WHERE token_hash=?').bind(await sha256(token)).run();
  return json({ok:true},200,{'set-cookie':sessionCookie('',0)});
 }
 if(!user)return bad('Unauthenticated',401);
 if(path==='/api/parent/interests'&&method==='GET'){
  if(user.role!=='parent')return bad('Forbidden',403);
  const rows=await db.prepare("SELECT i.id,i.child_name,i.child_age,i.status,i.created_at,a.title,a.status AS activity_status FROM interests i JOIN activities a ON a.id=i.activity_id WHERE i.parent_id=? ORDER BY i.created_at DESC LIMIT 100").bind(user.id).all();
  return json({interests:rows.results||[]});
 }
 if(path==='/api/parent/interests'&&method==='POST'){
  if(user.role!=='parent')return bad('Forbidden',403);
  const body=await readBody(req),activityId=String(body.activityId||''),childName=String(body.childName||'').trim(),childAge=Number(body.childAge);
  if(!/^[a-f0-9-]{36}$/.test(activityId)||childName.length<1||childName.length>80||!Number.isInteger(childAge)||childAge<1||childAge>18)return bad('Valid activity, child name and age required');
  const activity=await db.prepare("SELECT data_json FROM activities WHERE id=? AND status='pending_registrations'").bind(activityId).first();
  if(!activity)return bad('Activity is not accepting interest',404);
  const details=JSON.parse(activity.data_json);
  if(childAge<Number(details.minAge)||childAge>Number(details.maxAge))return bad('Age is outside the activity range',422);
  const existing=await db.prepare('SELECT id,status FROM interests WHERE activity_id=? AND parent_id=? AND child_name=?').bind(activityId,user.id,childName).first();
  if(existing){if(existing.status==='interested')return bad('Interest already registered',409);await db.prepare("UPDATE interests SET status='interested',child_age=?,updated_at=CURRENT_TIMESTAMP WHERE id=?").bind(childAge,existing.id).run();return json({id:existing.id,status:'interested'});}
  const id=crypto.randomUUID();
  await db.prepare('INSERT INTO interests(id,activity_id,parent_id,child_name,child_age) VALUES(?,?,?,?,?)').bind(id,activityId,user.id,childName,childAge).run();
  return json({id,status:'interested'},201);
 }
 const withdraw=path.match(/^\/api\/parent\/interests\/([a-f0-9-]{36})\/withdraw$/);
 if(withdraw&&method==='POST'){
  if(user.role!=='parent')return bad('Forbidden',403);
  const result=await db.prepare("UPDATE interests SET status='withdrawn',updated_at=CURRENT_TIMESTAMP WHERE id=? AND parent_id=? AND status='interested'").bind(withdraw[1],user.id).run();
  if(result.meta?.changes!==1)return bad('Interest not found or already withdrawn',404);
  return json({id:withdraw[1],status:'withdrawn'});
 }
 if(path==='/api/provider/activities'&&method==='POST'){
  if(user.role!=='provider')return bad('Forbidden',403);
  const body=await readBody(req),data=safeDraft(body),errors={};
  for(let step=0;step<4;step++)Object.assign(errors,validateStep(step,data));
  if(Object.keys(errors).length)return json({errors},422);
  const id=crypto.randomUUID();
  await db.prepare("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES(?,?,?,'pending_approval',?)").bind(id,user.id,data.activityName,JSON.stringify(data)).run();
  return json({id,status:'pending_approval'},201);
 }
 if(path==='/api/provider/activities'&&method==='GET'){
  if(user.role!=='provider')return bad('Forbidden',403);
  const rows=await db.prepare('SELECT id,title,status,created_at,updated_at FROM activities WHERE provider_id=? ORDER BY created_at DESC LIMIT 100').bind(user.id).all();
  return json({activities:rows.results});
 }
 if(path==='/api/admin/activities'&&method==='GET'){
  if(user.role!=='admin')return bad('Forbidden',403);
  const rows=await db.prepare("SELECT id,title,status,created_at FROM activities WHERE status='pending_approval' ORDER BY created_at ASC LIMIT 100").all();
  return json({activities:rows.results});
 }
 const match=path.match(/^\/api\/admin\/activities\/([a-f0-9-]{36})\/decision$/);
 if(match&&method==='POST'){
  if(user.role!=='admin')return bad('Forbidden',403);
  const body=await readBody(req);if(!['approve','reject'].includes(body.decision))return bad('Invalid decision');
  const status=body.decision==='approve'?'pending_registrations':'rejected',note=String(body.note||'').slice(0,1000);
  const result=await db.prepare("UPDATE activities SET status=?,admin_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending_approval'").bind(status,note,match[1]).run();
  if(result.meta?.changes!==1)return bad('Activity unavailable or already reviewed',409);
  await db.prepare('INSERT INTO audit_events(id,actor_id,activity_id,action) VALUES(?,?,?,?)').bind(crypto.randomUUID(),user.id,match[1],body.decision).run();
  return json({id:match[1],status});
 }
 return bad('Not found',404);
 }catch(e){if(e instanceof SyntaxError)return bad('Invalid JSON');if(e.message==='Request too large')return bad(e.message,413);return bad('Request failed',500);}
}
