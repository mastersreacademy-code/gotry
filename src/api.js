import {validateStep,safeDraft} from './wizard.js';
import {sydneyToUtc,utcToSydney} from './sydney-time.js';
const encoder=new TextEncoder();
const json=(data,status=200,extra={})=>Response.json(data,{status,headers:{'cache-control':'no-store','x-robots-tag':'noindex, nofollow',...extra}});
const bad=(message,status=400)=>json({error:message},status);
const cookieName='gotry_session';
async function notify(db,userId,activityId,kind,title,message){
 await db.prepare('INSERT INTO notifications(id,user_id,activity_id,kind,title,message) VALUES(?,?,?,?,?,?)').bind(crypto.randomUUID(),userId,activityId,kind,title,message).run();
}
async function notifyInterested(db,activityId,kind,title,message){
 await db.prepare("INSERT INTO notifications(id,user_id,activity_id,kind,title,message) SELECT lower(hex(randomblob(4)))||'-'||lower(hex(randomblob(2)))||'-4'||substr(lower(hex(randomblob(2))),2)||'-a'||substr(lower(hex(randomblob(2))),2)||'-'||lower(hex(randomblob(6))),parent_id,activity_id,?,?,? FROM (SELECT DISTINCT parent_id,activity_id FROM interests WHERE activity_id=? AND status='interested')").bind(kind,title,message,activityId).run();
}
function publicTrial(d,count=0){const min=d.trialType==='group'?Number(d.minParticipants):1;const max=d.trialType==='group'?Number(d.maxParticipants):1;return {type:d.trialType,date:d.trialDate,deadline:d.deadline||d.trialDate,time:d.trialTime||'',minParticipants:min,maxParticipants:max,interestCount:count,remaining:Math.max(0,max-count),minimumReached:count>=min};}
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
 // Public schedule exposes only approved activities and non-sensitive session fields.
 if(path==='/api/trial-sessions'&&method==='GET'){
  const activityId=url.searchParams.get('activityId')||'';
  if(!/^[a-f0-9-]{36}$/.test(activityId))return bad('Valid activity ID required');
  const rows=await db.prepare("SELECT s.id,s.starts_at,s.ends_at,s.starts_at_utc,s.ends_at_utc,s.timezone,s.venue,s.min_participants,s.max_participants,s.status,(SELECT COUNT(*) FROM interests i WHERE i.session_id=s.id AND i.status='interested') AS interested FROM trial_sessions s JOIN activities a ON a.id=s.activity_id WHERE s.activity_id=? AND a.status='pending_registrations' AND s.status='open' AND s.starts_at_utc>? AND COALESCE(json_extract(a.data_json,'$.deadline'),json_extract(a.data_json,'$.trialDate'))>=? ORDER BY COALESCE(s.starts_at_utc,s.starts_at) ASC LIMIT 50").bind(activityId,new Date().toISOString(),utcToSydney(new Date().toISOString()).slice(0,10)).all();
  const count=await db.prepare("SELECT COUNT(*) AS n FROM trial_sessions WHERE activity_id=? AND EXISTS(SELECT 1 FROM activities WHERE id=? AND status='pending_registrations')").bind(activityId,activityId).first();
  return json({hasSessions:Number(count.n)>0,sessions:(rows.results||[]).map(s=>({...s,remaining:Math.max(0,s.max_participants-s.interested),minimumReached:s.interested>=s.min_participants}))});
 }
 if(path==='/api/activities'&&method==='GET'){
  const rows=await db.prepare("SELECT a.id,a.title,a.data_json,(SELECT COUNT(*) FROM trial_sessions s WHERE s.activity_id=a.id) AS session_count,(SELECT COUNT(*) FROM interests i WHERE i.activity_id=a.id AND i.status='interested') AS interest_count,(SELECT COUNT(*) FROM trial_sessions s WHERE s.activity_id=a.id AND s.status='open' AND s.starts_at_utc>?) AS available_sessions,(SELECT COALESCE(SUM(MAX(0,s.max_participants-(SELECT COUNT(*) FROM interests i WHERE i.session_id=s.id AND i.status='interested'))),0) FROM trial_sessions s WHERE s.activity_id=a.id AND s.status='open' AND s.starts_at_utc>?) AS session_remaining FROM activities a WHERE a.status='pending_registrations' ORDER BY a.created_at DESC LIMIT 100").bind(new Date().toISOString(),new Date().toISOString()).all();
  return json({activities:(rows.results||[]).map(a=>{const data=JSON.parse(a.data_json);return {id:a.id,title:a.title,category:data.category,description:data.description,location:data.location,minAge:data.minAge,maxAge:data.maxAge,trial:Number(a.session_count)>0?{...publicTrial(data,Number(a.interest_count)),remaining:Number(a.session_remaining)}:publicTrial(data,Number(a.interest_count)),hasSessions:Number(a.session_count)>0,availableSessions:Number(a.available_sessions),availablePlaces:Number(a.session_remaining),registrationClosed:!!((data.deadline||data.trialDate)&&(data.deadline||data.trialDate)<utcToSydney(new Date().toISOString()).slice(0,10))};})});
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
  if(email.length>254||password.length>128)return bad('Invalid credentials',401);
  const now=Math.floor(Date.now()/1000),emailHash=await sha256(email);
  const attempts=await db.prepare('SELECT attempts,window_started,blocked_until FROM auth_attempts WHERE email_hash=?').bind(emailHash).first();
  if(attempts&&attempts.blocked_until>now)return bad('Too many attempts. Try again later',429);
  const user=await db.prepare('SELECT id,email,role,password_hash FROM users WHERE email=?').bind(email).first();
  if(!user||!await passwordMatches(password,user.password_hash)){
   const within=attempts&&now-attempts.window_started<900,failed=within?attempts.attempts+1:1;
   await db.prepare('INSERT INTO auth_attempts(email_hash,attempts,window_started,blocked_until) VALUES(?,?,?,?) ON CONFLICT(email_hash) DO UPDATE SET attempts=excluded.attempts,window_started=excluded.window_started,blocked_until=excluded.blocked_until').bind(emailHash,failed,within?attempts.window_started:now,failed>=5?now+900:0).run();
   return bad('Invalid credentials',401);
  }
  await db.prepare('DELETE FROM auth_attempts WHERE email_hash=?').bind(emailHash).run();
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
 if(path==='/api/notifications'&&method==='GET'){
  // A D1 batch is transactional: delivery and acknowledgement commit together.
  // Repeated GETs cannot duplicate notifications, because the notification id
  // is deterministically derived from the outbox id.
  await db.batch([
   db.prepare("INSERT OR IGNORE INTO notifications(id,user_id,activity_id,kind,title,message,created_at) SELECT substr(id,1,8)||'-'||substr(id,9,4)||'-'||substr(id,13,4)||'-'||substr(id,17,4)||'-'||substr(id,21,12),user_id,activity_id,kind,title,message,created_at FROM session_notification_outbox WHERE user_id=? AND delivered_at IS NULL").bind(user.id),
   db.prepare("UPDATE session_notification_outbox SET delivered_at=CURRENT_TIMESTAMP WHERE user_id=? AND delivered_at IS NULL").bind(user.id)
  ]);
  const rows=await db.prepare('SELECT id,kind,title,message,activity_id,read_at,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50').bind(user.id).all();
  return json({notifications:rows.results||[]});
 }
 const readNotice=path.match(/^\/api\/notifications\/([a-f0-9-]{36})\/read$/);
 if(readNotice&&method==='POST'){
  const result=await db.prepare('UPDATE notifications SET read_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=? AND read_at IS NULL').bind(readNotice[1],user.id).run();
  if(result.meta?.changes!==1){
   const existing=await db.prepare('SELECT id FROM notifications WHERE id=? AND user_id=?').bind(readNotice[1],user.id).first();
   if(!existing)return bad('Notification unavailable',404);
  }
  return json({ok:true});
 }
 if(path==='/api/parent/interests'&&method==='GET'){
  if(user.role!=='parent')return bad('Forbidden',403);
  const rows=await db.prepare("SELECT i.id,i.activity_id,i.session_id,i.child_name,i.child_age,i.status,i.created_at,a.title,a.status AS activity_status,s.starts_at AS session_start,s.starts_at_utc AS session_start_utc,s.timezone AS session_timezone,s.status AS session_status,s.venue AS session_venue FROM interests i JOIN activities a ON a.id=i.activity_id LEFT JOIN trial_sessions s ON s.id=i.session_id WHERE i.parent_id=? ORDER BY i.created_at DESC LIMIT 100").bind(user.id).all();
  return json({interests:rows.results||[]});
 }
 if(path==='/api/parent/interests'&&method==='POST'){
  if(user.role!=='parent')return bad('Forbidden',403);
  const body=await readBody(req),activityId=String(body.activityId||''),sessionId=String(body.sessionId||''),childName=String(body.childName||'').trim(),childAge=Number(body.childAge);
  if(!/^[a-f0-9-]{36}$/.test(activityId)||childName.length<1||childName.length>80||!Number.isInteger(childAge)||childAge<1||childAge>18)return bad('Valid activity, child name and age required');
  const activity=await db.prepare("SELECT data_json FROM activities WHERE id=? AND status='pending_registrations'").bind(activityId).first();
  if(!activity)return bad('Activity is not accepting interest',404);
  const details=JSON.parse(activity.data_json);
  let capacity=details.trialType==='group'?Number(details.maxParticipants):1;
  let session=null;
  if(sessionId){
   if(!/^[a-f0-9-]{36}$/.test(sessionId))return bad('Invalid session ID',422);
   session=await db.prepare("SELECT id,starts_at,starts_at_utc,max_participants,status FROM trial_sessions WHERE id=? AND activity_id=?").bind(sessionId,activityId).first();
   if(!session||session.status!=='open'||(!session.starts_at_utc||session.starts_at_utc<=new Date().toISOString()))return bad('Session unavailable',409);
   capacity=Number(session.max_participants);
  }else{
   const slots=await db.prepare('SELECT COUNT(*) AS n FROM trial_sessions WHERE activity_id=?').bind(activityId).first();
   if(Number(slots.n)>0)return bad('Select a trial session',422);
  }
  if(!Number.isInteger(capacity)||capacity<1)return bad('Activity capacity is invalid',422);
  const deadline=details.deadline||details.trialDate||(session?session.starts_at.slice(0,10):'');
  if(!/^\d{4}-\d{2}-\d{2}$/.test(deadline)||deadline<utcToSydney(new Date().toISOString()).slice(0,10))return bad('Registration deadline has passed',409);
  if(childAge<Number(details.minAge)||childAge>Number(details.maxAge))return bad('Age is outside the activity range',422);
  // One active registration per child per activity, regardless of session.
  // The database trigger in migration 0010 closes concurrent-request races.
  const active=await db.prepare("SELECT id FROM interests WHERE activity_id=? AND parent_id=? AND lower(trim(child_name))=lower(trim(?)) AND status='interested' LIMIT 1").bind(activityId,user.id,childName).first();
  if(active)return bad('This child already has an active registration for this activity. Use Parent dashboard to change sessions.',409);
  const existing=await db.prepare('SELECT id,status,session_id FROM interests WHERE activity_id=? AND parent_id=? AND lower(trim(child_name))=lower(trim(?)) AND session_id IS ? ORDER BY created_at DESC LIMIT 1').bind(activityId,user.id,childName,sessionId||null).first();
  
  if(existing){if(existing.status==='interested')return bad('Interest already registered',409);const result=await db.prepare("UPDATE interests SET status='interested',child_age=?,session_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='withdrawn' AND EXISTS(SELECT 1 FROM activities WHERE id=? AND status='pending_registrations' AND COALESCE(json_extract(data_json,'$.deadline'),json_extract(data_json,'$.trialDate'))>=?) AND ((?='' AND NOT EXISTS(SELECT 1 FROM trial_sessions WHERE activity_id=?)) OR (?<>'' AND EXISTS(SELECT 1 FROM trial_sessions WHERE id=? AND activity_id=? AND status='open' AND starts_at_utc>?))) AND (SELECT COUNT(*) FROM interests WHERE activity_id=? AND status='interested' AND (?='' OR session_id=?)) < ?").bind(childAge,sessionId||null,existing.id,activityId,utcToSydney(new Date().toISOString()).slice(0,10),sessionId,activityId,sessionId,sessionId,activityId,new Date().toISOString(),activityId,sessionId,sessionId,capacity).run();if(result.meta?.changes!==1)return bad('Trial is full',409);return json({id:existing.id,status:'interested'});}
  const id=crypto.randomUUID();
  const result=await db.prepare("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) SELECT ?,?,?,?,?,? WHERE (SELECT COUNT(*) FROM interests WHERE activity_id=? AND status='interested' AND (?='' OR session_id=?)) < ? AND EXISTS(SELECT 1 FROM activities WHERE id=? AND status='pending_registrations' AND COALESCE(json_extract(data_json,'$.deadline'),json_extract(data_json,'$.trialDate'))>=?) AND ((?='' AND NOT EXISTS(SELECT 1 FROM trial_sessions WHERE activity_id=?)) OR (?<>'' AND EXISTS(SELECT 1 FROM trial_sessions WHERE id=? AND activity_id=? AND status='open' AND starts_at_utc>?)))").bind(id,activityId,user.id,childName,childAge,sessionId||null,activityId,sessionId,sessionId,capacity,activityId,utcToSydney(new Date().toISOString()).slice(0,10),sessionId,activityId,sessionId,sessionId,activityId,new Date().toISOString()).run();
  if(result.meta?.changes!==1)return bad('Trial is full or unavailable',409);
  // Interest triggers enqueue parent/provider notifications atomically with this insert.
  return json({id,status:'interested'},201);
 }
 const transfer=path.match(/^\/api\/parent\/interests\/([a-f0-9-]{36})\/transfer$/);
 if(transfer&&method==='POST'){
  if(user.role!=='parent')return bad('Forbidden',403);
  const b=await readBody(req),target=String(b.sessionId||'');
  if(!/^[a-f0-9-]{36}$/.test(target))return bad('Valid target session required',422);
  const interest=await db.prepare("SELECT i.activity_id,i.session_id,i.status,i.child_age,a.data_json FROM interests i JOIN activities a ON a.id=i.activity_id LEFT JOIN trial_sessions source ON source.id=i.session_id WHERE i.id=? AND i.parent_id=? AND a.status='pending_registrations' AND (i.session_id IS NULL OR source.status='open')").bind(transfer[1],user.id).first();
  if(!interest||interest.status!=='interested')return bad('Active interest not found',404);
  if(interest.session_id===target)return bad('Already in this session',409);
  if(interest.session_id===null)return bad('Legacy interests cannot be transferred until their trial session is assigned',409);
  const d=JSON.parse(interest.data_json);
  if(interest.child_age<Number(d.minAge)||interest.child_age>Number(d.maxAge))return bad('Age outside activity range',422);
  const cutoff=String(d.deadline||d.trialDate||'');
  if(cutoff&&(!/^\d{4}-\d{2}-\d{2}$/.test(cutoff)||cutoff<utcToSydney(new Date().toISOString()).slice(0,10)))return bad('Registration deadline has passed',409);
  const now=new Date().toISOString(),today=utcToSydney(now).slice(0,10);
  const updated=await db.prepare("UPDATE interests SET session_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND parent_id=? AND status='interested' AND session_id=? AND NOT EXISTS(SELECT 1 FROM interests other WHERE other.activity_id=interests.activity_id AND other.parent_id=interests.parent_id AND lower(trim(other.child_name))=lower(trim(interests.child_name)) AND other.session_id=? AND other.id<>interests.id) AND EXISTS(SELECT 1 FROM trial_sessions s JOIN activities a ON a.id=s.activity_id WHERE s.id=? AND s.activity_id=interests.activity_id AND s.status='open' AND s.starts_at_utc>? AND substr(s.starts_at,1,10)>=? AND a.status='pending_registrations' AND COALESCE(json_extract(a.data_json,'$.deadline'),json_extract(a.data_json,'$.trialDate'))>=? AND EXISTS(SELECT 1 FROM trial_sessions old WHERE old.id=interests.session_id AND old.status='open' AND old.starts_at_utc>?) AND (SELECT COUNT(*) FROM interests i WHERE i.session_id=s.id AND i.status='interested')<s.max_participants)").bind(target,transfer[1],user.id,interest.session_id,target,target,now,today,today,now).run();
  if(updated.meta?.changes!==1)return bad('Session changed or target is full. Refresh Parent dashboard and try again.',409);
  return json({id:transfer[1],sessionId:target,status:'interested'});
 }
 const withdraw=path.match(/^\/api\/parent\/interests\/([a-f0-9-]{36})\/withdraw$/);
 if(withdraw&&method==='POST'){
  if(user.role!=='parent')return bad('Forbidden',403);
  const body=await readBody(req),expectedSessionId=body.expectedSessionId;
  if(expectedSessionId!==null&&!/^[a-f0-9-]{36}$/.test(String(expectedSessionId||'')))return bad('Expected session ID required (or null for legacy interests)',422);
  const result=await db.prepare("UPDATE interests SET status='withdrawn',updated_at=CURRENT_TIMESTAMP WHERE id=? AND parent_id=? AND status='interested' AND session_id IS ? AND (session_id IS NULL OR EXISTS(SELECT 1 FROM trial_sessions s WHERE s.id=interests.session_id AND s.status='open' AND s.starts_at_utc>?))").bind(withdraw[1],user.id,expectedSessionId,new Date().toISOString()).run();
  if(result.meta?.changes!==1)return bad('Registration changed, was withdrawn or session closed. Refresh Parent dashboard.',409);
  
  return json({id:withdraw[1],status:'withdrawn'});
 }
 const providerSessions=path.match(/^\/api\/provider\/activities\/([a-f0-9-]{36})\/sessions$/);
 if(providerSessions&&method==='GET'){
  if(user.role!=='provider')return bad('Forbidden',403);
  const rows=await db.prepare("SELECT s.id,s.starts_at,s.ends_at,s.starts_at_utc,s.ends_at_utc,s.timezone,s.venue,s.min_participants,s.max_participants,s.status,(SELECT COUNT(*) FROM interests i WHERE i.session_id=s.id AND i.status='interested') AS interested FROM trial_sessions s JOIN activities a ON a.id=s.activity_id WHERE a.provider_id=? AND a.id=? ORDER BY s.starts_at ASC LIMIT 50").bind(user.id,providerSessions[1]).all();
  return json({sessions:rows.results||[]});
 }
 if(providerSessions&&method==='POST'){
  if(user.role!=='provider')return bad('Forbidden',403);
  const b=await readBody(req),startsAt=String(b.startsAt||''),endsAt=String(b.endsAt||''),venue=String(b.venue||'').trim(),min=Number(b.minParticipants),max=Number(b.maxParticipants);
  if(!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(startsAt)||!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(endsAt)||!Number.isFinite(Date.parse(startsAt))||!Number.isFinite(Date.parse(endsAt))||startsAt>=endsAt||startsAt<=utcToSydney(new Date().toISOString())||venue.length<2||venue.length>160||!Number.isInteger(min)||!Number.isInteger(max)||min<1||max<min||max>100)return bad('Valid future session, venue and capacity required',422);
  let startUtc,endUtc;
  try{startUtc=sydneyToUtc(startsAt);endUtc=sydneyToUtc(endsAt);}catch(e){return bad(e.message,422);}
  if(startUtc>=endUtc||startUtc<=new Date().toISOString())return bad('Session must be in the future',422);
  const activity=await db.prepare("SELECT id FROM activities WHERE id=? AND provider_id=? AND status='pending_approval'").bind(providerSessions[1],user.id).first();
  if(!activity)return bad('Only your pending-approval activities can add sessions',403);
  const id=crypto.randomUUID();
  const inserted=await db.prepare("INSERT INTO trial_sessions(id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc) SELECT ?,?,?,?,?,?,?,?,? WHERE EXISTS(SELECT 1 FROM activities WHERE id=? AND provider_id=? AND status='pending_approval')").bind(id,providerSessions[1],startsAt,endsAt,venue,min,max,startUtc,endUtc,providerSessions[1],user.id).run();
  if(inserted.meta?.changes!==1)return bad('Activity changed while scheduling; refresh Provider dashboard',409);
  await notify(db,user.id,providerSessions[1],'session_created','Trial session scheduled','A new trial session was added to your pending activity.');
  return json({id,status:'open'},201);
 }
 const roster=path.match(/^\/api\/provider\/sessions\/([a-f0-9-]{36})\/roster$/);
 if(roster&&method==='GET'){
  if(user.role!=='provider')return bad('Forbidden',403);
  const owned=await db.prepare('SELECT s.id,s.status,a.status AS activity_status FROM trial_sessions s JOIN activities a ON a.id=s.activity_id WHERE s.id=? AND a.provider_id=?').bind(roster[1],user.id).first();
  if(!owned)return bad('Not found',404);
  // Both count and names are read from one SQL snapshot. Names are gated in
  // SQL, so a stale dashboard cannot request names for an unconfirmed session.
  const rows=await db.prepare("SELECT CASE WHEN s.status='confirmed' AND a.status NOT LIKE 'cancelled%' THEN i.child_name ELSE NULL END AS child_name, (SELECT COUNT(*) FROM interests all_i WHERE all_i.session_id=s.id AND all_i.status='interested') AS total, CASE WHEN s.status='confirmed' AND a.status NOT LIKE 'cancelled%' THEN 1 ELSE 0 END AS names_available FROM trial_sessions s JOIN activities a ON a.id=s.activity_id LEFT JOIN interests i ON i.session_id=s.id AND i.status='interested' WHERE s.id=? AND a.provider_id=? ORDER BY i.created_at ASC LIMIT 100").bind(roster[1],user.id).all();
  const result=rows.results||[];
  if(!result.length)return bad('Not found',404);
  const namesAvailable=Boolean(result[0].names_available);
  return json({roster:namesAvailable?result.filter(x=>x.child_name!==null).map(x=>({child_name:x.child_name})):[],count:Number(result[0].total),namesAvailable});
 }
 const cancelSession=path.match(/^\/api\/provider\/sessions\/([a-f0-9-]{36})\/cancel$/);
 if(cancelSession&&method==='POST'){
  if(user.role!=='provider')return bad('Forbidden',403);
  const b=await readBody(req),expectedStartsAt=String(b.expectedStartsAt||'');
  if(!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(expectedStartsAt))return bad('Original session start required; refresh and retry',422);
  const s=await db.prepare("SELECT s.activity_id,s.status FROM trial_sessions s JOIN activities a ON a.id=s.activity_id WHERE s.id=? AND a.provider_id=? AND a.status IN ('pending_approval','pending_registrations')").bind(cancelSession[1],user.id).first();
  if(!s)return bad('Not found',404);
  if(s.status!=='open')return bad('Only open sessions can be cancelled',409);
  const changed=await db.prepare("UPDATE trial_sessions SET status='cancelled',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='open' AND starts_at=? AND starts_at_utc>? AND EXISTS(SELECT 1 FROM activities WHERE id=trial_sessions.activity_id AND provider_id=? AND status IN ('pending_approval','pending_registrations'))").bind(cancelSession[1],expectedStartsAt,new Date().toISOString(),user.id).run();
  if(changed.meta?.changes!==1)return bad('Session changed or was already cancelled. Refresh Provider dashboard.',409);
  return json({id:cancelSession[1],status:'cancelled'});
 }
 const reschedule=path.match(/^\/api\/provider\/sessions\/([a-f0-9-]{36})\/reschedule$/);
 if(reschedule&&method==='POST'){
  if(user.role!=='provider')return bad('Forbidden',403);
  const b=await readBody(req),start=String(b.startsAt||''),end=String(b.endsAt||''),venue=String(b.venue||'').trim();
  const expectedStartsAt=String(b.expectedStartsAt||'');if(!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(expectedStartsAt))return bad('Original session start required; refresh and retry',422);
  let startUtc,endUtc;
  try{startUtc=sydneyToUtc(start);endUtc=sydneyToUtc(end);}catch(e){return bad(e.message,422);}
  if(startUtc<=new Date().toISOString()||endUtc<=startUtc||venue.length<2||venue.length>160)return bad('Invalid future Sydney schedule or venue',422);
  const session=await db.prepare("SELECT s.activity_id,s.starts_at,s.starts_at_utc,s.venue,s.status,a.status AS activity_status FROM trial_sessions s JOIN activities a ON a.id=s.activity_id WHERE s.id=? AND a.provider_id=?").bind(reschedule[1],user.id).first();
  if(!session)return bad('Not found',404);
  if(session.status!=='open'||!['pending_approval','pending_registrations'].includes(session.activity_status))return bad('Only open sessions can be rescheduled',409);
  if(!session.starts_at_utc||session.starts_at_utc<=new Date().toISOString())return bad('Started or past sessions cannot be rescheduled',409);
  const changed=await db.prepare("UPDATE trial_sessions SET starts_at=?,ends_at=?,starts_at_utc=?,ends_at_utc=?,venue=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='open' AND starts_at=? AND starts_at_utc>? AND EXISTS(SELECT 1 FROM activities WHERE id=trial_sessions.activity_id AND provider_id=? AND status IN ('pending_approval','pending_registrations'))").bind(start,end,startUtc,endUtc,venue,reschedule[1],expectedStartsAt,new Date().toISOString(),user.id).run();
  if(changed.meta?.changes!==1)return bad('Session schedule changed. Refresh Provider dashboard before retrying.',409);
  return json({id:reschedule[1],startsAt:start,endsAt:end,timezone:'Australia/Sydney'});
 }
 if(path==='/api/provider/activities'&&method==='POST'){
  if(user.role!=='provider')return bad('Forbidden',403);
  const body=await readBody(req),data=safeDraft(body),errors={};
  for(let step=0;step<4;step++)Object.assign(errors,validateStep(step,data));
  if(Object.keys(errors).length)return json({errors},422);
  const id=crypto.randomUUID();
  await db.prepare("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES(?,?,?,'pending_approval',?)").bind(id,user.id,data.activityName,JSON.stringify(data)).run();
  await notify(db,user.id,id,'activity_submitted','Activity submitted','Your activity is awaiting admin review.');
  return json({id,status:'pending_approval'},201);
 }
 if(path==='/api/provider/activities'&&method==='GET'){
  if(user.role!=='provider')return bad('Forbidden',403);
  const rows=await db.prepare("SELECT a.id,a.title,a.status,a.admin_note,a.data_json,a.created_at,a.updated_at,(SELECT COUNT(*) FROM trial_sessions s WHERE s.activity_id=a.id) AS session_count,(SELECT COUNT(*) FROM interests i WHERE i.activity_id=a.id AND i.status='interested') AS interest_count FROM activities a WHERE a.provider_id=? ORDER BY a.created_at DESC LIMIT 100").bind(user.id).all();
  return json({activities:(rows.results||[]).map(({data_json,...row})=>({...row,trial:publicTrial(JSON.parse(data_json),Number(row.interest_count))}))});
 }
 const cancel=path.match(/^\/api\/provider\/activities\/([a-f0-9-]{36})\/cancel$/);
 if(cancel&&method==='POST'){
  if(user.role!=='provider')return bad('Forbidden',403);
  // D1 batch is transactional. Couple status, audit and parent notifications
  // so a partial failure cannot cancel the activity without its notices.
  const auditId=crypto.randomUUID();
  const [result]=await db.batch([
   db.prepare("UPDATE activities SET status='cancelled_by_provider',updated_at=CURRENT_TIMESTAMP WHERE id=? AND provider_id=? AND status IN ('pending_approval','pending_registrations')").bind(cancel[1],user.id),
   db.prepare("INSERT INTO audit_events(id,actor_id,activity_id,action) SELECT ?,?,?,? WHERE changes()=1").bind(auditId,user.id,cancel[1],'cancel_by_provider'),
   db.prepare("INSERT INTO notifications(id,user_id,activity_id,kind,title,message) SELECT lower(hex(randomblob(4)))||'-'||lower(hex(randomblob(2)))||'-4'||substr(lower(hex(randomblob(2))),2)||'-a'||substr(lower(hex(randomblob(2))),2)||'-'||lower(hex(randomblob(6))),parent_id,activity_id,'trial_cancelled','Trial cancelled','The provider has cancelled this trial. No payment was collected by GoTry.' FROM (SELECT DISTINCT parent_id,activity_id FROM interests WHERE activity_id=? AND status='interested') WHERE EXISTS(SELECT 1 FROM audit_events WHERE id=?)").bind(cancel[1],auditId)
  ]);
  if(result.meta?.changes!==1)return bad('Activity unavailable or cannot be cancelled',409);
  return json({id:cancel[1],status:'cancelled_by_provider'});
 }
 if(path==='/api/admin/activities'&&method==='GET'){
  if(user.role!=='admin')return bad('Forbidden',403);
  const rows=await db.prepare("SELECT id,title,status,created_at FROM activities WHERE status='pending_approval' ORDER BY created_at ASC LIMIT 100").all();
  return json({activities:rows.results});
 }
 if(path==='/api/admin/trials'&&method==='GET'){
  if(user.role!=='admin')return bad('Forbidden',403);
  const rows=await db.prepare("SELECT a.id,a.title,a.status,a.data_json,(SELECT COUNT(*) FROM interests i WHERE i.activity_id=a.id AND i.status='interested') AS interest_count FROM activities a WHERE a.status='pending_registrations' AND NOT EXISTS(SELECT 1 FROM trial_sessions s WHERE s.activity_id=a.id) ORDER BY a.created_at ASC LIMIT 100").all();
  return json({trials:(rows.results||[]).map(({data_json,...a})=>({...a,trial:publicTrial(JSON.parse(data_json),Number(a.interest_count))}))});
 }
 if(path==='/api/admin/sessions'&&method==='GET'){
  if(user.role!=='admin')return bad('Forbidden',403);
  const rows=await db.prepare("SELECT s.id,s.activity_id,s.starts_at,s.ends_at,s.starts_at_utc,s.ends_at_utc,s.timezone,s.venue,s.min_participants,s.max_participants,s.status,a.title,(SELECT COUNT(*) FROM interests i WHERE i.session_id=s.id AND i.status='interested') AS interested FROM trial_sessions s JOIN activities a ON a.id=s.activity_id WHERE a.status='pending_registrations' ORDER BY s.starts_at ASC LIMIT 100").all();
  return json({sessions:rows.results||[]});
 }
 const sessionDecision=path.match(/^\/api\/admin\/sessions\/([a-f0-9-]{36})\/decision$/);
 if(sessionDecision&&method==='POST'){
  if(user.role!=='admin')return bad('Forbidden',403);
  const b=await readBody(req),decision=String(b.decision||'');
  if(!['confirm','cancel'].includes(decision))return bad('Invalid decision');
  const expectedStartsAt=String(b.expectedStartsAt||'');
  if(!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(expectedStartsAt))return bad('Original session start required; refresh Admin schedule',422);
  const s=await db.prepare("SELECT s.activity_id,s.starts_at,s.starts_at_utc,s.min_participants,s.status,(SELECT COUNT(*) FROM interests i WHERE i.session_id=s.id AND i.status='interested') AS interested FROM trial_sessions s JOIN activities a ON a.id=s.activity_id WHERE s.id=? AND a.status='pending_registrations'").bind(sessionDecision[1]).first();
  if(!s||s.status!=='open')return bad('Session unavailable',409);
  if(decision==='confirm'&&(s.interested<s.min_participants||!s.starts_at_utc||s.starts_at_utc<=new Date().toISOString()))return bad('Minimum not reached or session has started',409);
  if(decision==='cancel'&&(s.interested>=s.min_participants||(s.starts_at_utc?s.starts_at_utc>=new Date().toISOString():s.starts_at.slice(0,10)>=utcToSydney(new Date().toISOString()).slice(0,10))))return bad('Cannot cancel before session date or when minimum is reached',409);
  const next=decision==='confirm'?'confirmed':'cancelled';
  const [changed]=await db.batch([
   db.prepare("UPDATE trial_sessions SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='open' AND starts_at=? AND EXISTS(SELECT 1 FROM activities WHERE id=trial_sessions.activity_id AND status='pending_registrations') AND ((?='confirmed' AND starts_at_utc IS NOT NULL AND starts_at_utc>? AND (SELECT COUNT(*) FROM interests WHERE session_id=trial_sessions.id AND status='interested')>=min_participants) OR (?='cancelled' AND (SELECT COUNT(*) FROM interests WHERE session_id=trial_sessions.id AND status='interested')<min_participants AND starts_at_utc IS NOT NULL AND starts_at_utc<?))").bind(next,sessionDecision[1],expectedStartsAt,next,new Date().toISOString(),next,new Date().toISOString()),
   db.prepare("INSERT INTO audit_events(id,actor_id,activity_id,action) SELECT ?,?,?,? WHERE changes()=1 AND EXISTS(SELECT 1 FROM trial_sessions WHERE id=? AND status=?)").bind(crypto.randomUUID(),user.id,s.activity_id,'session_'+decision,sessionDecision[1],next)
  ]);
  if(changed.meta?.changes!==1)return bad('Already updated',409);
  return json({id:sessionDecision[1],status:next});
 }
 const detail=path.match(/^\/api\/admin\/activities\/([a-f0-9-]{36})$/);
 if(detail&&method==='GET'){
  if(user.role!=='admin')return bad('Forbidden',403);
  const activity=await db.prepare('SELECT id,title,status,data_json,admin_note,created_at FROM activities WHERE id=?').bind(detail[1]).first();
  if(!activity)return bad('Not found',404);
  return json({activity:{id:activity.id,title:activity.title,status:activity.status,details:JSON.parse(activity.data_json),adminNote:activity.admin_note,createdAt:activity.created_at}});
 }
 const lifecycle=path.match(/^\/api\/admin\/activities\/([a-f0-9-]{36})\/lifecycle$/);
 if(lifecycle&&method==='POST'){
  if(user.role!=='admin')return bad('Forbidden',403);
  const body=await readBody(req),action=String(body.action||'');
  if(!['confirm_minimum','cancel_low_numbers'].includes(action))return bad('Invalid lifecycle action');
  const activity=await db.prepare("SELECT data_json,status FROM activities WHERE id=?").bind(lifecycle[1]).first();
  if(!activity||activity.status!=='pending_registrations')return bad('Activity is not awaiting registrations',409);
  const scheduled=await db.prepare('SELECT COUNT(*) AS n FROM trial_sessions WHERE activity_id=?').bind(lifecycle[1]).first();
  if(Number(scheduled.n)>0)return bad('Manage scheduled sessions individually',409);
  const details=JSON.parse(activity.data_json);
  const count=await db.prepare("SELECT COUNT(*) AS total FROM interests WHERE activity_id=? AND status='interested'").bind(lifecycle[1]).first();
  const minimum=details.trialType==='group'?Number(details.minParticipants):1;
  if(action==='confirm_minimum'&&Number(count.total)<minimum)return bad('Minimum participants not reached',409);
  if(action==='confirm_minimum'&&Number(count.total)>Number(details.trialType==='group'?details.maxParticipants:1))return bad('Trial capacity exceeded',409);
  if(action==='cancel_low_numbers'&&Number(count.total)>=minimum)return bad('Minimum already reached',409);
  if(action==='cancel_low_numbers'){const deadline=details.deadline||details.trialDate;if(!/^\d{4}-\d{2}-\d{2}$/.test(deadline)||deadline>=utcToSydney(new Date().toISOString()).slice(0,10))return bad('Registration deadline has not passed',409);}
  const status=action==='confirm_minimum'?'proceeding_pending_payments':'cancelled_low_numbers';
  // Recheck attendance, schedule and deadline in the write itself. The earlier
  // SELECTs provide helpful errors but are not concurrency guarantees.
  const attendance="(SELECT COUNT(*) FROM interests i WHERE i.activity_id=activities.id AND i.status='interested')";
  const noSessions="NOT EXISTS(SELECT 1 FROM trial_sessions s WHERE s.activity_id=activities.id)";
  const trialMinimum="CASE WHEN json_extract(data_json,'$.trialType')='group' THEN CAST(json_extract(data_json,'$.minParticipants') AS INTEGER) ELSE 1 END";
  const trialMaximum="CASE WHEN json_extract(data_json,'$.trialType')='group' THEN CAST(json_extract(data_json,'$.maxParticipants') AS INTEGER) ELSE 1 END";
  const today=utcToSydney(new Date().toISOString()).slice(0,10);
  const lifecycleCondition=action==='confirm_minimum'
   ? attendance+'>='+trialMinimum+' AND '+attendance+'<='+trialMaximum
   : attendance+'<'+trialMinimum+" AND COALESCE(json_extract(data_json,'$.deadline'),json_extract(data_json,'$.trialDate'))<?";
  const sql="UPDATE activities SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending_registrations' AND "+noSessions+" AND "+lifecycleCondition;
  const query=db.prepare(sql);
  const result=await (action==='confirm_minimum'?query.bind(status,lifecycle[1]):query.bind(status,lifecycle[1],today)).run();
  if(result.meta?.changes!==1)return bad('Activity already updated',409);
  await db.prepare('INSERT INTO audit_events(id,actor_id,activity_id,action) VALUES(?,?,?,?)').bind(crypto.randomUUID(),user.id,lifecycle[1],action).run();
  const owner=await db.prepare('SELECT provider_id FROM activities WHERE id=?').bind(lifecycle[1]).first();
  if(owner)await notify(db,owner.provider_id,lifecycle[1],'trial_status','Trial status updated',status.replaceAll('_',' '));
  await notifyInterested(db,lifecycle[1],'trial_status','Trial status updated',status==='proceeding_pending_payments'?'Minimum interest reached. This is not a confirmed booking or payment request.':'The trial did not reach the minimum and has been cancelled.');
  return json({id:lifecycle[1],status});
 }
 const match=path.match(/^\/api\/admin\/activities\/([a-f0-9-]{36})\/decision$/);
 if(match&&method==='POST'){
  if(user.role!=='admin')return bad('Forbidden',403);
  const body=await readBody(req);if(!['approve','reject'].includes(body.decision))return bad('Invalid decision');
  const status=body.decision==='approve'?'pending_registrations':'rejected',note=String(body.note||'').slice(0,1000);
  const result=await db.prepare("UPDATE activities SET status=?,admin_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending_approval'").bind(status,note,match[1]).run();
  if(result.meta?.changes!==1)return bad('Activity unavailable or already reviewed',409);
  await db.prepare('INSERT INTO audit_events(id,actor_id,activity_id,action) VALUES(?,?,?,?)').bind(crypto.randomUUID(),user.id,match[1],body.decision).run();
  const owner=await db.prepare('SELECT provider_id FROM activities WHERE id=?').bind(match[1]).first();
  if(owner)await notify(db,owner.provider_id,match[1],'approval_decision',status==='rejected'?'Activity rejected':'Activity approved',status==='rejected'?'Admin rejected this submission. See your dashboard for feedback.':'Your activity is now open for expressions of interest.');
  return json({id:match[1],status});
 }
 return bad('Not found',404);
 }catch(e){if(e instanceof SyntaxError)return bad('Invalid JSON');if(e.message==='Request too large')return bad(e.message,413);if(/child already registered for this activity/i.test(String(e.message||'')))return bad('This child already has an active registration for this activity. Use Parent dashboard to change sessions.',409);if(/trial session unavailable or full|UNIQUE constraint failed: interests|interests_child_session_unique|interests_child_legacy_unique/i.test(String(e.message||'')))return bad('Session unavailable, full or already registered',409);return bad('Request failed',500);}
}
