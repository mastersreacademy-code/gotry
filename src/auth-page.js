import { siteCss, siteHeaderFor, siteFooter, siteRefinements } from './site-design.js';
export const authPage = String.raw`<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>GoTry · Welcome</title>
<style>${siteCss}
.auth-main{max-width:1120px;margin:44px auto 80px;display:grid;grid-template-columns:minmax(0,1fr) minmax(340px,440px);gap:clamp(26px,5vw,76px);align-items:center}
.auth-intro{padding:22px 0}.eyebrow{display:inline-block;color:#176dd0;font-size:12px;font-weight:850;letter-spacing:.12em;text-transform:uppercase;margin-bottom:18px}
.auth-intro h1{font-size:clamp(40px,5.5vw,68px);line-height:1.07;letter-spacing:-2.7px;margin-bottom:24px}.auth-intro h1 span{color:#e32685}
.auth-intro>p{color:#586b85;max-width:500px;font-size:18px;line-height:1.75}
.benefits{display:grid;gap:18px;margin-top:40px}.benefit{display:flex;gap:14px;align-items:flex-start}.benefit-icon{display:grid;place-items:center;flex:0 0 44px;height:44px;border-radius:14px;background:#eaf3ff;color:#176dd0;font-weight:850}
.benefit strong{display:block;font-size:15px}.benefit small{display:block;color:#627089;font-size:13px;line-height:1.6;margin-top:3px}
.auth-card{background:#fff;border:1px solid #e1e9f4;border-radius:26px;padding:clamp(22px,4vw,36px);box-shadow:0 24px 65px #19396612}
.auth-card h2{font-size:28px;letter-spacing:-.8px;margin:0 0 8px}.auth-sub{font-size:14px;color:#627089;margin:0 0 24px}
.mode-tabs{display:grid;grid-template-columns:1fr 1fr;background:#f1f5fb;border-radius:13px;padding:5px;margin-bottom:25px;gap:5px}
main .mode-tabs button{background:transparent!important;color:#53647c!important;border:0;border-radius:10px;min-height:42px;padding:9px;font-size:14px}
main .mode-tabs button[aria-selected=true]{background:#fff!important;color:#14376c!important;box-shadow:0 2px 9px #10234a14}
.role-label{font-weight:750;font-size:13px;display:block;margin:0 0 10px}.roles{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:22px}
.role-choice{border:1.5px solid #d8e2ef;border-radius:13px;padding:13px;cursor:pointer;display:flex;align-items:center;gap:9px;font-weight:750;font-size:14px}
.role-choice:has(input:checked){border-color:#176dd0;background:#f0f7ff}.role-choice input{width:17px;min-height:17px;accent-color:#176dd0;margin:0}
.field{display:block;margin:0 0 18px;font-weight:750;font-size:13px}.field>span{display:block;margin-bottom:8px}
main .field input{width:100%;min-height:50px;padding:12px 14px;border-radius:12px;font-size:15px}
.password-wrap{position:relative}.password-wrap input{padding-right:76px}
main .show-password{position:absolute;right:7px;top:6px;min-height:38px;padding:7px 10px;background:transparent!important;color:#176dd0!important;font-size:12px}
.hint{display:block;color:#718096;font-weight:450;font-size:12px;margin-top:6px}
main .submit-btn{width:100%;min-height:51px;border-radius:12px;font-size:15px;margin-top:8px}
.auth-status{font-size:13px;margin:14px 0 0;min-height:20px;color:#a12646}.auth-status.ok{color:#176544}
.auth-foot{margin:23px 0 0;font-size:12px;color:#74839a;text-align:center;line-height:1.65}
[hidden]{display:none!important}
@media(max-width:790px){.auth-main{grid-template-columns:minmax(0,1fr);max-width:530px;margin:26px auto 48px}.auth-intro{padding:0}.auth-intro h1{font-size:clamp(34px,8vw,48px);letter-spacing:-1.8px}.auth-intro>p{font-size:15px}.benefits{display:none}.auth-card{padding:24px}}
${siteRefinements}</style></head>
<body>${siteHeaderFor('/account')}
<main id="main-content" class="auth-main" tabindex="-1">
<section class="auth-intro"><span class="eyebrow">Welcome to GoTry</span><h1>Discover more.<br><span>Try something new.</span></h1>
<p>Explore activities for children, connect with local providers, and express interest in a trial before committing.</p>
<div class="benefits"><div class="benefit"><span class="benefit-icon">01</span><div><strong>Explore with confidence</strong><small>Discover activities and choose a trial session that suits your family.</small></div></div>
<div class="benefit"><span class="benefit-icon">02</span><div><strong>Made for families and providers</strong><small>One simple place to manage trial interest and session updates.</small></div></div>
<div class="benefit"><span class="benefit-icon">03</span><div><strong>No payment at registration</strong><small>Expressing interest is not a confirmed booking or payment.</small></div></div></div></section>
<section class="auth-card" aria-label="Account access"><h2 id="heading">Welcome back</h2><p id="subtitle" class="auth-sub">Sign in to continue to your dashboard.</p>
<div class="mode-tabs" role="tablist" aria-label="Account action"><button id="login-tab" role="tab" type="button" aria-selected="true">Sign in</button><button id="signup-tab" role="tab" type="button" aria-selected="false">Create account</button></div>
<form id="auth-form" novalidate>
<div id="role-section" hidden><span class="role-label">I am joining as a</span><div class="roles"><label class="role-choice"><input type="radio" name="role" value="parent" checked> Parent</label><label class="role-choice"><input type="radio" name="role" value="provider"> Provider</label></div></div>
<label class="field"><span>Email address</span><input name="email" type="email" autocomplete="email" placeholder="you@example.com" required maxlength="254"></label>
<label class="field"><span>Password</span><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required maxlength="128"><button id="toggle-password" class="show-password" type="button" aria-label="Show password">Show</button></div><small id="password-hint" class="hint" hidden>Use at least 12 characters.</small></label>
<label id="confirm-field" class="field" hidden><span>Confirm password</span><input id="confirm-password" type="password" autocomplete="new-password" placeholder="Enter your password again"></label>
<button id="submit-btn" class="submit-btn" type="submit">Sign in</button><p id="status" class="auth-status" role="status" aria-live="polite"></p></form>
<div id="signed-in-actions" hidden><a id="dashboard-link" href="/parent" class="cta">Open dashboard</a> <button id="logout-btn" type="button" class="secondary">Sign out</button></div><p class="auth-foot">GoTry is an early prototype. Please avoid using sensitive personal information or a password you use elsewhere.</p></section>
</main>
<script>
(function(){
 const form=document.getElementById('auth-form'),status=document.getElementById('status'),submit=document.getElementById('submit-btn');
 const loginTab=document.getElementById('login-tab'),signupTab=document.getElementById('signup-tab'),password=document.getElementById('password'),confirm=document.getElementById('confirm-password');
 let mode='login',busy=false;
 function setMode(next){if(busy)return;mode=next;const register=mode==='register';loginTab.setAttribute('aria-selected',String(!register));signupTab.setAttribute('aria-selected',String(register));
 document.getElementById('heading').textContent=register?'Create your account':'Welcome back';
 document.getElementById('subtitle').textContent=register?'Join GoTry as a parent or activity provider.':'Sign in to continue to your dashboard.';
 document.getElementById('role-section').hidden=!register;document.getElementById('confirm-field').hidden=!register;document.getElementById('password-hint').hidden=!register;
 confirm.required=register;password.minLength=register?12:1;password.autocomplete=register?'new-password':'current-password';
 submit.textContent=register?'Create account':'Sign in';status.textContent='';status.className='auth-status';}
 loginTab.addEventListener('click',()=>setMode('login'));signupTab.addEventListener('click',()=>setMode('register'));
 document.getElementById('toggle-password').addEventListener('click',function(){const show=password.type==='password';password.type=show?'text':'password';this.textContent=show?'Hide':'Show';this.setAttribute('aria-label',show?'Hide password':'Show password');});
 form.addEventListener('submit',async function(event){event.preventDefault();if(busy)return;status.textContent='';status.className='auth-status';
 const email=form.elements.email.value.trim(),pass=password.value;
 if(!form.elements.email.validity.valid){status.textContent='Enter a valid email address.';form.elements.email.focus();return;}
 if(mode==='register'&&pass.length<12){status.textContent='Password must contain at least 12 characters.';password.focus();return;}
 if(mode==='register'&&pass!==confirm.value){status.textContent='Passwords do not match.';confirm.focus();return;}
 if(!pass){status.textContent='Enter your password.';password.focus();return;}
 const body={email,password:pass};if(mode==='register')body.role=form.querySelector('input[name="role"]:checked').value;
 busy=true;submit.disabled=true;loginTab.disabled=true;signupTab.disabled=true;submit.textContent=mode==='register'?'Creating account…':'Signing in…';
 try{const response=await fetch('/api/auth/'+mode,{method:'POST',credentials:'same-origin',headers:{'content-type':'application/json'},body:JSON.stringify(body)});
 const data=await response.json().catch(()=>({}));if(!response.ok)throw Error(data.error||'Unable to complete request. Please try again.');
 if(!data.user||!['parent','provider','admin'].includes(data.user.role))throw Error('Account response is incomplete. Please try signing in.');
 status.className='auth-status ok';status.textContent='Success. Opening your dashboard…';
 location.assign(data.user.role==='provider'?'/provider':data.user.role==='admin'?'/admin':'/parent');
 }catch(error){status.textContent=error.message||'Please try again.';busy=false;submit.disabled=false;loginTab.disabled=false;signupTab.disabled=false;submit.textContent=mode==='register'?'Create account':'Sign in';}});
 document.getElementById('logout-btn').addEventListener('click',async function(){this.disabled=true;status.textContent='Signing out…';try{const r=await fetch('/api/auth/logout',{method:'POST',credentials:'same-origin',headers:{'content-type':'application/json'},body:'{}'});if(!r.ok)throw Error('Unable to sign out');location.assign('/account');}catch(e){status.textContent=e.message;this.disabled=false;}});
 const params=new URLSearchParams(location.search);if(params.get('mode')==='register'||params.get('mode')==='signup')setMode('register');
 fetch('/api/auth/me',{credentials:'same-origin'}).then(r=>r.ok?r.json():null).then(data=>{if(data&&data.user&&['parent','provider','admin'].includes(data.user.role)){document.getElementById('subtitle').textContent='You are already signed in. Open your dashboard or sign out.';document.getElementById('signed-in-actions').hidden=false;document.getElementById('dashboard-link').href=data.user.role==='admin'?'/admin':data.user.role==='provider'?'/provider':'/parent';}}).catch(()=>{});
})();
</script>${siteFooter}</body></html>`;