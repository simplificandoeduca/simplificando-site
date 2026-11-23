/* ===== conta online (Firebase): só no site (pasta /treino), quando a página carrega o SDK =====
   O aluno entra com a senha de 6 números que o personal gerou no painel e o app lê o treino dele. */
const FB = (() => {
  const cfg = window.SIMPLIFICANDO_FIREBASE, fb = window.firebase;
  if(!cfg || !fb || typeof fb.initializeApp!=='function') return null;
  let app = null;
  try { app = fb.apps.find(x => x.name==='[DEFAULT]') || fb.initializeApp(cfg); } catch(e){ return null; }
  const auth = app.auth(), db = app.firestore();
  if(cfg.emulador){ auth.useEmulator('http://'+cfg.emulador+':9099'); db.useEmulator(cfg.emulador, 8080); }
  const CK = 'simplificando-conta', mail = c => 'a'+c+'@alunos.'+cfg.projectId+'.firebaseapp.com', pass = c => 'simp-'+c;
  const toAluno = p => ({ codigo:p.aid, perfil:p.perfil||{}, metas:p.metas||{}, plano:p.plano||{nome:'', treinos:{}}, financeiro:p.financeiro||{}, ajustes:Array.isArray(p.ajustes) ? p.ajustes : [], email:'' });
  const cached = () => { try { return JSON.parse(localStorage.getItem(CK)||'null'); } catch(e){ return null; } };
  const keep = v => { try { localStorage.setItem(CK, JSON.stringify(v)); } catch(e){ /* sem armazenamento */ } };
  const ready = () => new Promise(r => { const un = auth.onAuthStateChanged(u => { un(); r(u); }); });
  async function fetchMe(){
    const u = auth.currentUser || await ready();
    if(!u) return { err:'Sua sessão terminou. Entre de novo com sua senha.', out:true };
    try {
      const d = await db.collection('appAlunos').doc(u.uid).get();
      if(!d.exists) return { err:'Seu acesso ainda não foi liberado. Fale com seu personal.', out:true };
      const v = d.data(); keep(v); return { p:v };
    } catch(e){
      if(e && e.code==='permission-denied') return { err:'Seu acesso está bloqueado ou ainda não foi liberado. Fale com seu personal.', out:true };
      const c = cached(); return c ? { p:c, offline:true } : { err:'Sem internet. Conecte e tente de novo.' };
    }
  }
  // aluno com personal: e-mail + código de 6 números. Contas antigas entram só com o código.
  async function login(code, email){
    const em = String(email||'').trim().toLowerCase(), net = k => k==='auth/too-many-requests' ? 'Muitas tentativas. Espere alguns minutos e tente de novo.' : k==='auth/network-request-failed' ? 'Sem internet. Conecte e tente de novo.' : '';
    let ok = false, cru = false;
    // só a senha: o servidor (login.php) acha de quem é o código e devolve um passe de entrada daquele aluno
    if(!em){
      let j = null;
      try { const f = new FormData(); f.append('codigo', code); j = await fetch('/login.php', {method:'POST', body:f, cache:'no-store'}).then(r => r.json()); } catch(e){ j = null; }
      if(j && j.ok && j.token){ try { await auth.signInWithCustomToken(j.token); ok = true; } catch(e){ if(net(e && e.code)) return { err:net(e.code) }; } }
      else if(j && j.erro && !j.semServidor) return { err:j.erro };
      else if(!j && !navigator.onLine) return { err:'Sem internet. Conecte e tente de novo.' };
    }
    if(em){
      try { await auth.signInWithEmailAndPassword(em, pass(code)); ok = true; }
      catch(e){ if(net(e && e.code)) return { err:net(e.code) };
        try { await auth.signInWithEmailAndPassword(em, code); ok = true; cru = true; } catch(e2){ /* tenta a conta antiga */ } }
    }
    if(!ok){
      try { await auth.signInWithEmailAndPassword(mail(code), pass(code)); }
      catch(e){ const k = e && e.code; return { err: net(k) || (em ? 'E-mail ou senha não conferem. Confira com seu personal ou toque em “Esqueci a senha”.' : 'Senha não encontrada. Confira com seu personal.') }; }
    }
    // trocou o código pela página padrão do Firebase: deixa a conta no formato do app e avisa o painel
    if(cru){ try { await auth.currentUser.updatePassword(pass(code)); } catch(e){ /* segue */ } await setCodigo(code); }
    const r = await fetchMe(); if(r.out) await logout(); return r;
  }
  async function setCodigo(code){ const u = auth.currentUser; if(!u) return; try { await db.collection('codigos').doc(u.uid).set({ codigo:String(code), em:Date.now() }); } catch(e){ /* regras antigas: o painel não fica sabendo */ } }
  // link do e-mail de "esqueci": confere e grava a senha ou o código novo
  const resetErr = e => { const k = e && e.code; return k==='auth/expired-action-code' ? 'Esse link venceu. Peça um novo em “Esqueci a senha”.' : k==='auth/invalid-action-code' ? 'Esse link já foi usado ou não vale mais. Peça um novo em “Esqueci a senha”.' : k==='auth/weak-password' ? 'Senha fraca. Use pelo menos 8 caracteres.' : k==='auth/network-request-failed' ? 'Sem internet. Conecte e tente de novo.' : 'Não deu certo. Peça um link novo em “Esqueci a senha”.'; };
  async function resetCheck(oob){ try { return { email:await auth.verifyPasswordResetCode(oob) }; } catch(e){ return { err:resetErr(e) }; } }
  async function resetSave(oob, nova, aluno){
    try { const em = await auth.verifyPasswordResetCode(oob); await auth.confirmPasswordReset(oob, aluno ? pass(nova) : nova);
      if(!aluno) return { ok:true, email:em };
      await auth.signInWithEmailAndPassword(em, pass(nova));
      // conta de quem treina sem personal (não tem acesso de aluno): a senha fica do jeito que ela digitou
      const u = auth.currentUser; let ehAluno = false; try { ehAluno = (await db.collection('appAlunos').doc(u.uid).get()).exists; } catch(e){ ehAluno = !(e && e.code==='permission-denied'); }
      if(!ehAluno){ try { await u.updatePassword(nova); } catch(e){ /* segue: o "entrar" também aceita o formato antigo */ } await logout(); return { ok:true, email:em, self:true }; }
      await setCodigo(nova); const r = await fetchMe(); if(r.out){ await logout(); return { err:r.err||'Seu acesso não está liberado. Fale com seu personal.' }; } return { ok:true, email:em, p:r.p }; }
    catch(e){ return { err:resetErr(e) }; }
  }
  async function logout(){ try { localStorage.removeItem(CK); } catch(e){ /* nada */ } try { await auth.signOut(); } catch(e){ /* nada */ } }
  /* aluno sem personal: conta com e-mail e senha; o app inteiro fica salvo em autonomos/{uid} */
  async function signUp(email, pw, nome){
    try { const c = await auth.createUserWithEmailAndPassword(email, pw); try { await c.user.updateProfile({displayName:nome}); } catch(e){ /* nome é opcional */ } return { uid:c.user.uid }; }
    catch(e){ const k = e && e.code; return { err: k==='auth/email-already-in-use' ? 'Esse e-mail já tem conta. Toque em “Entrar”, logo acima.' : k==='auth/invalid-email' ? 'Confira o e-mail. Ex.: voce@email.com' : k==='auth/weak-password' ? 'Senha fraca. Use pelo menos 8 caracteres.' : k==='auth/network-request-failed' ? 'Sem internet. Conecte e tente de novo.' : k==='auth/too-many-requests' ? 'Muitas tentativas. Espere alguns minutos e tente de novo.' : k==='auth/operation-not-allowed' ? 'O cadastro por e-mail está desligado no Firebase (Authentication › Sign-in method).' : 'Não deu para criar a conta agora. Tente de novo.' }; }
  }
  async function signIn(email, pw){
    try { const c = await auth.signInWithEmailAndPassword(email, pw); return { uid:c.user.uid, nome:c.user.displayName||'' }; }
    catch(e){ const k = e && e.code;
      if(/^\d{6}$/.test(String(pw)) && !/too-many|network/.test(k||'')){ try { const c = await auth.signInWithEmailAndPassword(email, pass(pw)); try { await c.user.updatePassword(String(pw)); } catch(e2){ /* fica no formato antigo */ } return { uid:c.user.uid, nome:c.user.displayName||'' }; } catch(e3){ /* não era isso */ } } return { err: k==='auth/too-many-requests' ? 'Muitas tentativas. Espere alguns minutos e tente de novo.' : k==='auth/network-request-failed' ? 'Sem internet. Conecte e tente de novo.' : 'E-mail ou senha não conferem.' }; }
  }
  async function resetMail(email, aluno){
    try { auth.languageCode = 'pt-BR'; } catch(e){ /* idioma padrão */ }
    try { await auth.sendPasswordResetEmail(String(email).trim(), aluno ? { url:location.origin+location.pathname.replace(/[^/]*$/, '')+'?tipo=aluno' } : undefined); return { ok:true }; }
    catch(e){ const k = e && e.code; if(k==='auth/network-request-failed') return { err:'Sem internet. Conecte e tente de novo.' }; if(k==='auth/invalid-email') return { err:'Confira o e-mail. Ex.: voce@email.com' }; if(k==='auth/too-many-requests') return { err:'Muitas tentativas. Espere alguns minutos e tente de novo.' }; return { ok:true }; }
  }
  async function autoLoad(){
    const u = auth.currentUser || await ready(); if(!u) return { out:true };
    try { const d = await db.collection('autonomos').doc(u.uid).get(); if(!d.exists) return { uid:u.uid, s:null }; let v = null; try { v = JSON.parse(d.data().s); } catch(e){ v = null; } return { uid:u.uid, s:v }; }
    catch(e){ return { uid:u.uid, err:true }; }
  }
  let autoT = null, autoP = null;
  // antes de gravar, lê a nuvem e junta (outro aparelho ou o app da tela inicial podem ter gravado treinos que este não tem)
  async function autoFlush(){
    clearTimeout(autoT); const p = autoP; autoP = null; if(!p) return;
    const u = auth.currentUser; if(!u || u.uid!==p.uid) return;
    const ref = db.collection('autonomos').doc(p.uid);
    try {
      let txt = p.txt;
      if(p.junta){ const d = await ref.get(); if(d.exists){ let v = null; try { v = JSON.parse(d.data().s); } catch(e){ v = null; } const m = v ? p.junta(v) : null; if(m) txt = m; } }
      await ref.set({ s:txt, em:Date.now() });
    } catch(e){ if(!autoP) autoP = p; }
  }
  function autoSave(uid, st, now, junta){ autoP = { uid, txt:JSON.stringify(st), junta:junta||null }; clearTimeout(autoT); if(now) return autoFlush(); autoT = setTimeout(autoFlush, 1500); return Promise.resolve(); }
  // aluno com personal: o que ele fez vai para progresso/{uid}; só ele grava o seu e só o personal lê
  // null = ainda não tem nada na nuvem; undefined = não deu para ler (sem internet): tenta de novo depois
  async function progLoad(){ const u = auth.currentUser || await ready(); if(!u) return undefined; try { const d = await db.collection('progresso').doc(u.uid).get(); if(!d.exists) return null; try { return JSON.parse(d.data().s||'null'); } catch(e){ return null; } } catch(e){ return undefined; } }
  async function progresso(aid, txt){ const u = auth.currentUser || await ready(); if(!u) return false; try { await db.collection('progresso').doc(u.uid).set({ aid, s:txt, em:Date.now() }); return true; } catch(e){ return false; } }
  function perfil(d){ const u = auth.currentUser; if(!u) return; db.collection('perfis').doc(u.uid).set(JSON.parse(JSON.stringify(d)), { merge:true }).catch(() => { /* regras antigas ou sem internet: tenta na próxima abertura */ }); }
  async function deleteSelf(pw){
    const u = auth.currentUser; if(!u) return { err:'Sua sessão terminou. Saia, entre de novo e tente outra vez.' };
    try { await u.reauthenticateWithCredential(fb.auth.EmailAuthProvider.credential(u.email, pw)); }
    catch(e){ const k = e && e.code; return { err: /wrong-password|invalid-credential|invalid-login/.test(k||'') ? 'Senha incorreta.' : k==='auth/network-request-failed' ? 'Sem internet. Conecte e tente de novo.' : k==='auth/too-many-requests' ? 'Muitas tentativas. Espere alguns minutos e tente de novo.' : 'Não deu para confirmar a sua senha.' }; }
    try { clearTimeout(autoT); autoP = null; await db.collection('autonomos').doc(u.uid).delete(); try { await db.collection('perfis').doc(u.uid).delete(); } catch(e){ /* sem resumo */ } await u.delete(); return { ok:true }; }
    catch(e){ return { err: e && e.code==='auth/requires-recent-login' ? 'Por segurança, saia, entre de novo e tente outra vez.' : 'Não deu para excluir agora. Confira a internet e tente de novo.' }; }
  }
  // registro avulso (caixinha de sugestões, modelo de story usado): cada um grava só com o próprio uid; só o dono do painel lê
  async function evento(col, d){ const u = auth.currentUser || await ready(); if(!u) return 'sem-login'; try { await db.collection(col).add(Object.assign({}, d, { uid:u.uid })); return true; } catch(e){ return (e && e.code) || 'erro'; } }
  async function idToken(){ try { const u = auth.currentUser || await ready(); return u ? await u.getIdToken() : ''; } catch(e){ return ''; } }
  return { idToken, login, fetchMe, logout, toAluno, cached, signUp, signIn, resetMail, resetCheck, resetSave, setCodigo, autoLoad, autoSave, autoFlush, perfil, progresso, progLoad, deleteSelf, evento, account:aid => { const c = cached(); return c && c.aid===aid ? toAluno(c) : null; } };
})();
// abriu com a conta guardada (ou voltou para o app): confere online se o personal mudou o treino ou bloqueou o acesso
document.addEventListener('visibilitychange', () => { if(document.visibilityState==='visible') fbRefresh(); else if(FB) FB.autoFlush(); });
/* aluno com personal: o iPhone separa o app da tela de início do Safari (cada um com seus dados).
   O histórico que já foi para a nuvem (treinos, cargas, cardio, água e check-in) volta para o aparelho que não tem. */
let PROG_REST = false, PROG_OK = false; // só envia o resumo depois de ler o que já está na nuvem (senão um aparelho vazio apagaria o histórico)
function progRestore(){
  if(PROG_REST || !FB || !FB.progLoad || !ACCT || ACCT.mode!=='personal') return; PROG_REST = true;
  const code = ACCT.code;
  FB.progLoad().then(d => {
    if(d===undefined){ PROG_REST = false; return; } // sem internet: tenta na próxima
    if(!ACCT || ACCT.code!==code || !S) return;
    PROG_OK = true; if(!d){ progSync(true); return; }
    if(d.lay && (!PREF.cardioLayout || !PREF.treinoLayout)){ const c0 = PREF.cardioLayout, t0 = PREF.treinoLayout; PREF.cardioLayout = c0 || d.lay.c || undefined; PREF.treinoLayout = t0 || d.lay.t || undefined; if(PREF.cardioLayout!==c0 || PREF.treinoLayout!==t0){ S.lay = { c:PREF.cardioLayout||null, t:PREF.treinoLayout||null }; savePref(); ui.pstep = null; renderPick(); render(); } }
    let n = 0;
    const tem = new Set(S.history.map(h => h.date+'|'+h.t));
    (d.h||[]).forEach(h0 => { if(!h0 || !h0.d) return; const h = Object.assign({}, h0, { d:ajPara(h0.d, h0.t) }); if(tem.has(h.d+'|'+h.t)) return;
      const hx = { date:h0.d, t:h0.t, hi:h0.hi||'', hr:h0.hr||'', m0:h0.m0, min:h0.min||0, sets:h0.sets||0 }, aj = AJ().find(j => (j.del || +j.min>0) && ajBate(j, hx));
      if(aj && aj.del) return; // o personal excluiu este treino: não volta da nuvem
      tem.add(h.d+'|'+h.t); n++;
      S.history.push({ date:h.d, t:h.t, min:aj ? Math.round(+aj.min) : h.min||0, m0:aj ? (h0.m0!=null ? h0.m0 : h0.min||0) : h0.m0, hi:h0.hi||'', hr:h0.hr||'', vol:h.vol||0, sets:h.sets||0, prs:[], rpe:h.rpe||0, note:h.note||'', gym:h.gym||'', ex:h.ex||null, nuvem:true });
      (h.ex||[]).forEach(e => { if(!e || !e.id || !(e.s||[]).length) return; const mk = Math.max(...e.s.map(z => +z[0]||0)); if(!mk) return;
        const top = e.s.filter(z => +z[0]===mk), old = S.last[e.id];
        if(!old || !old.d || old.d<h.d) S.last[e.id] = { kg:mk, lastKg:+e.s[e.s.length-1][0]||mk, reps:top.map(z => +z[1]||0), rir:top.map(() => 1), d:h.d, g:h.gym||'' };
        const LH = S.loadHist[e.id] = (S.loadHist[e.id]||[]).filter(x => x.d!==h.d); LH.push({ d:h.d, kg:mk }); LH.sort((x,y) => x.d<y.d?-1:1); while(LH.length>16) LH.shift();
        let bi = -1, bv = 0; e.s.forEach((z,j) => { const v = e1(+z[0]||0, +z[1]||0); if(v>bv){ bv = v; bi = j; } });
        if(bi>=0 && (!S.pr[e.id] || bv>S.pr[e.id].e1)) S.pr[e.id] = { e1:bv, kg:+e.s[bi][0], reps:+e.s[bi][1], d:h.d }; }); });
    S.history.sort((x,y) => x.date<y.date ? -1 : x.date>y.date ? 1 : 0);
    const tc = new Set(S.cardio.map(c => c.date+'|'+c.local+'|'+c.min).concat(S.cDel||[]));
    (d.c||[]).forEach(c => { if(!c || !c.d || tc.has(c.d+'|'+c.l+'|'+c.min)) return; tc.add(c.d+'|'+c.l+'|'+c.min); n++; S.cardio.push({ date:c.d, local:c.l||'Outro', min:+c.min||0, int:c.i||INTENS[0], km:0 }); });
    S.cardio.sort((x,y) => x.date<y.date ? -1 : x.date>y.date ? 1 : 0);
    Object.keys(d.w||{}).forEach(k => { const v = +d.w[k]||0; if(v > (+S.water[k]||0)){ S.water[k] = v; n++; } });
    Object.keys(d.k||{}).forEach(k => { if(!S.checkin[k] && d.k[k]){ S.checkin[k] = d.k[k]; n++; } });
    if(n){ save(); render(); toast('Seu histórico foi recuperado.'); } else progSync(true);
  });
}
function fbRefresh(){
  perfilSync();
  if(FB && ACCT && ACCT.mode==='self' && ACCT.uid){ autoSync(); return; }
  if(!FB || !ACCT || ACCT.mode!=='personal') return;
  progRestore();
  const before = JSON.stringify(FB.cached()), code = ACCT.code;
  FB.fetchMe().then(r => {
    if(!ACCT || ACCT.code!==code) return;
    if(r.out){ logout(); LG.err = r.err; renderLogin(); return; }
    if(r.p && JSON.stringify(r.p)!==before){ ACCT.aluno = FB.toAluno(r.p); applyAccount(); tidy(); save(); render(); }
  });
}
/* junta duas cópias da conta sem personal (aparelho e nuvem, ou o app da tela inicial e o navegador, que guardam separado):
   o que foi registrado numa não some por causa da outra. Treinos e cardio: os das duas, sem repetir (vale o editado por último);
   água: o maior de cada dia; check-in, recordes, últimas cargas e evolução: o melhor ou o mais recente. Devolve quantas coisas vieram da outra. */
function autoJunta(base, outra){
  if(!base || !outra || outra.v!==2) return 0; let n = 0;
  const hk = h => h.date+'|'+h.t+'|'+(h.hi||'')+'|'+(h.hr||''), H = new Map();
  (base.history||[]).forEach(h => H.set(hk(h), h));
  (outra.history||[]).forEach(h => { if(!h || !h.date) return; const k = hk(h), b = H.get(k); if(!b || (h.edit||0)>(b.edit||0)){ H.set(k, h); n++; } });
  base.history = [...H.values()].sort((x,y) => x.date<y.date ? -1 : x.date>y.date ? 1 : 0);
  const del = [...new Set((base.cDel||[]).concat(outra.cDel||[]))], ck = c => c.date+'|'+c.local+'|'+c.min, C = new Map();
  if(del.length) base.cDel = del;
  (base.cardio||[]).forEach(c => C.set(ck(c), c));
  (outra.cardio||[]).forEach(c => { if(c && c.date && !C.has(ck(c)) && !del.includes(ck(c))){ C.set(ck(c), c); n++; } });
  base.cardio = [...C.values()].filter(c => !del.includes(ck(c))).sort((x,y) => x.date<y.date ? -1 : x.date>y.date ? 1 : 0);
  base.water = base.water || {}; Object.keys(outra.water||{}).forEach(k => { const v = +outra.water[k]||0; if(v>(+base.water[k]||0)){ base.water[k] = v; n++; } });
  base.wlog = base.wlog || {}; Object.keys(outra.wlog||{}).forEach(k => { if((outra.wlog[k]||[]).length>(base.wlog[k]||[]).length) base.wlog[k] = outra.wlog[k]; });
  base.checkin = base.checkin || {}; Object.keys(outra.checkin||{}).forEach(k => { if(!base.checkin[k] && outra.checkin[k]){ base.checkin[k] = outra.checkin[k]; n++; } });
  base.pr = base.pr || {}; Object.keys(outra.pr||{}).forEach(id => { const o = outra.pr[id], b = base.pr[id]; if(o && (!b || (+o.e1||0)>(+b.e1||0))) base.pr[id] = o; });
  base.last = base.last || {}; Object.keys(outra.last||{}).forEach(id => { const o = outra.last[id], b = base.last[id]; if(o && (!b || (o.d||'')>(b.d||''))) base.last[id] = o; });
  base.loadHist = base.loadHist || {}; Object.keys(outra.loadHist||{}).forEach(id => { const M = {}; (base.loadHist[id]||[]).concat(outra.loadHist[id]||[]).forEach(x => { if(x && x.d) M[x.d] = Math.max(M[x.d]||0, +x.kg||0); });
    base.loadHist[id] = Object.keys(M).sort().slice(-16).map(d => ({ d, kg:M[d] })); });
  return n;
}
// na hora de gravar: junta o que a nuvem tem a mais neste aparelho e devolve o texto para gravar (null = grava como está)
function autoJuntaAgora(v){
  if(!ACCT || ACCT.mode!=='self' || !S || !v || v.v!==2) return null;
  if(!autoJunta(S, v)) return null;
  tidy(); S._em = Date.now(); LASTSYNC = syncTxt(); try { localStorage.setItem(UKEY, JSON.stringify(S)); } catch(e){ /* sem armazenamento */ }
  setTimeout(() => { try { render(); } catch(e){ /* redesenha depois */ } }, 0);
  return JSON.stringify(S);
}
/* aluno sem personal com conta online: a cópia mais nova é a base e recebe da outra tudo o que só existia nela */
function autoSync(){
  const uid = ACCT.uid;
  FB.autoLoad().then(r => {
    if(!ACCT || ACCT.uid!==uid || r.out || r.err || r.uid!==uid) return;
    if(r.s && r.s.v===2 && r.s.conta && (r.s._em||0)>(S._em||0) && !S.session){
      const loc = S, n = autoJunta(r.s, loc);
      S = r.s; applyAccount(); if(ACCT && ACCT.mode==='self' && S.conta && (S.conta.plan==='meu' || S.conta.plan==='ia') && mtLetras()) applyAccount(); tidy(); LASTSYNC = syncTxt();
      try { localStorage.setItem(UKEY, JSON.stringify(S)); } catch(e){ /* sem armazenamento */ }
      if(S.lay && (!PREF.cardioLayout || !PREF.treinoLayout)){ PREF.cardioLayout = PREF.cardioLayout || S.lay.c || undefined; PREF.treinoLayout = PREF.treinoLayout || S.lay.t || undefined; savePref(); ui.pstep = null; renderPick(); }
      ACCT.syncOk = true; ACCT.dirty = false; ui.sheet = null; renderSheet(); render();
      if(n){ S._em = Date.now(); LASTSYNC = syncTxt(); try { localStorage.setItem(UKEY, JSON.stringify(S)); } catch(e){ /* sem armazenamento */ } FB.autoSave(uid, S, false, autoJuntaAgora); } // o que só estava neste aparelho volta para a nuvem
      return;
    }
    if(r.s && r.s.v===2 && autoJunta(S, r.s)){ tidy(); save(); render(); } // a nuvem tinha registros que este aparelho não tinha
    ACCT.syncOk = true;
    if(ACCT.dirty || !r.s || (S._em||0)>(r.s._em||0)){ ACCT.dirty = false; FB.autoSave(uid, S, false, autoJuntaAgora); }
  });
}
const SKEY = 'simplificando-sessao';
function readSess(){ try { const v = JSON.parse(localStorage.getItem(SKEY)||'null'); return v && ((v.mode==='personal' && v.code) || v.mode==='self') ? v : null; } catch(e){ return null; } }
function writeSess(v){ try { if(v) localStorage.setItem(SKEY, JSON.stringify(v)); else localStorage.removeItem(SKEY); } catch(e){ /* sem armazenamento: vale só nesta visita */ } }
let ACCT = null, UKEY = KEY;
const isPersonal = () => !!ACCT && ACCT.mode==='personal';
const sexOf = () => ACCT && ACCT.sexo==='M' ? 'M' : 'F';
const DEF_DESC = 'Ritmo em que dá para conversar, mas não para cantar.';
const firstName = n => String(n||'').trim().split(/\s+/)[0] || '';
const welcomeWord = s => s==='M' ? 'Bem-vindo' : 'Bem-vinda';
/* ajustes do personal no painel: mudar o dia (começou num dia e finalizou no outro), excluir o treino (ex.: marcado 2 vezes) e alterar o tempo */
const AJ = () => (isPersonal() && ACCT.aluno && Array.isArray(ACCT.aluno.ajustes)) ? ACCT.aluno.ajustes.filter(x => x && x.de && x.t && (x.para || x.del || +x.min>0)) : [];
const ajPara = (d, t) => { const x = AJ().find(j => j.para && j.de===d && j.t===t); return x ? x.para : d; };
// o treino certo (mesmo com dois iguais no mesmo dia): dia, letra, hora de início e de fim, tempo original e séries
const ajBate = (j, h) => (h.date===j.de || h.date===ajPara(j.de, j.t)) && h.t===j.t && (!j.hi || j.hi===(h.hi||'')) && (!j.hr || j.hr===(h.hr||'')) && (j.m0==null || j.m0===(h.m0!=null ? h.m0 : (h.min||0))) && (j.s0==null || j.s0===(h.sets||0));
function ajustaDias(){
  const L = AJ(); if(!L.length || !S || !S.history) return;
  const ok = S.ajOk || (S.ajOk = []); let mud = 0;
  L.filter(j => (j.del || +j.min>0) && !ok.includes(j.id)).forEach(j => { const i = S.history.findIndex(h => ajBate(j, h)); if(i<0) return;
    if(j.del) S.history.splice(i, 1); else { const h = S.history[i]; if(h.m0==null) h.m0 = h.min||0; h.min = Math.round(+j.min); h.sec = h.min*60; }
    ok.push(j.id); mud++; });
  if(ok.length>60) ok.splice(0, ok.length-60);
  let n = mud; const fora = new Set();
  S.history.forEach(h => { const x = L.find(j => j.para && j.de===h.date && j.t===h.t); if(!x) return; n++;
    if(S.history.some(o => o!==h && o.date===x.para && o.t===h.t)) fora.add(h); else h.date = x.para; }); // já veio da nuvem no dia certo: tira a cópia
  if(fora.size) S.history = S.history.filter(h => !fora.has(h));
  if(n) S.history.sort((x,y) => x.date<y.date ? -1 : x.date>y.date ? 1 : 0);
}
/* o mesmo exercício pode ganhar um código novo (ciclo novo, modelo aplicado, treino copiado ou montado com a IA):
   o gráfico de evolução, a carga da última vez e os recordes passam para o código novo (mesmo exercício da biblioteca ou mesmo nome) */
const exBase = id => String(id||'').replace(/-[a-z0-9]{4}$/, '');
const exNome = n => String(n||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/\s+/g,' ').trim();
function ligaHist(){
  if(!S || !PLAN) return;
  const nomes = {}; // código antigo -> nome, pelos treinos salvos
  (S.history||[]).forEach(h => (h.ex||[]).forEach(e => { if(e && e.id && e.n) nomes[e.id] = exNome(e.n); }));
  const ids = new Set([...Object.keys(S.loadHist||{}), ...Object.keys(S.last||{}), ...Object.keys(S.pr||{})]);
  Object.values(PLAN).forEach(t => (t.ex||[]).forEach(e => {
    if(!e || !e.id || (S.loadHist[e.id]||[]).length || S.last[e.id] || S.pr[e.id]) return;
    const b = exBase(e.id), nm = exNome(e.nome), generico = /^(novo|voz|ex)$/.test(b);
    const velhos = [...ids].filter(c => c!==e.id && ((!generico && exBase(c)===b) || (nm && nomes[c]===nm))); if(!velhos.length) return;
    const porDia = {}; velhos.forEach(c => (S.loadHist[c]||[]).forEach(x => { if(x && x.d && (!porDia[x.d] || x.kg>porDia[x.d])) porDia[x.d] = x.kg; }));
    const LH = Object.keys(porDia).sort().map(d => ({ d, kg:porDia[d] })).slice(-16); if(LH.length) S.loadHist[e.id] = LH;
    const ult = velhos.map(c => S.last[c]).filter(Boolean).sort((x,y) => String(y.d||'').localeCompare(String(x.d||'')))[0]; if(ult) S.last[e.id] = Object.assign({}, ult);
    const pr = velhos.map(c => S.pr[c]).filter(Boolean).sort((x,y) => (y.e1||0)-(x.e1||0))[0]; if(pr) S.pr[e.id] = Object.assign({}, pr);
    Object.values(S.gymLast||{}).forEach(m => { const g = velhos.map(c => m[c]).filter(Boolean).sort((x,y) => String(y.d||'').localeCompare(String(x.d||'')))[0]; if(g && !m[e.id]) m[e.id] = Object.assign({}, g); });
  }));
}
function applyAccount(){
  if(isPersonal()){
    const a = ACCT.aluno, mc = a.metas.cardio || {};
    PLAN = a.plano.treinos;
    CARDIO = { min:mc.min||25, zona:mc.zona||'Zona 2', desc:mc.desc||DEF_DESC };
    OBRIG = (Array.isArray(mc.obrig) ? mc.obrig : []).filter(o => o && LOCAIS_ALL.includes(o.local) && +o.min>0).map(o => ({ local:o.local, min:Math.round(+o.min), vezes:Math.max(1, Math.min(7, +o.vezes||0)) || 0, dias:(o.dias||[]).filter(d => DAYK.includes(d)), obs:String(o.obs||'').slice(0,140) }));
    LOCAIS = LOCAIS_ALL.slice(); // todos os aparelhos sempre aparecem para o aluno
    ACCT.dias = (a.perfil.dias||[]).map(d => DAYK.indexOf(d)).filter(n => n>=0).sort((x,y) => x-y);
    ACCT.sexo = a.perfil.sexo==='M' ? 'M' : 'F'; ACCT.nome = a.perfil.nome; ACCT.plano = a.plano.nome; ACCT.pagoAte = a.financeiro && a.financeiro.pagoAte;
    SEMANA_TREINOS = a.metas.semanaTreinos || ACCT.dias.length || 3;
    S.nome = firstName(a.perfil.nome); if(ED[a.perfil.objetivo]) S.ed = a.perfil.objetivo;
    S.waterGoal = a.metas.waterGoal || 3000; S.cardioGoal = a.metas.cardioGoal || 120;
    ajustaDias();
  } else {
    const c = S.conta || (S.conta = {sexo:'F', dias:[0,2,4], plan:'abc'});
    const own = c.plan==='meu' || c.plan==='ia';
    if(own) Object.values(c.treinos || {}).forEach(t => (t.ex || []).forEach(e => { const k = catOf(e.id); if(!k) return; e.nome = k.nome; e.alvo = k.alvo || GNOME[k.g]; e.cues = k.cues.slice(); e.erro = k.erro; if(k.anim) e.anim = k.anim; else delete e.anim; delete e.semEq; }));
    if(own){ c.treinos = c.treinos || {}; PLAN = {}; Object.keys(c.treinos).sort().forEach(l => { const t = c.treinos[l]; if(t && t.ex && t.ex.length) PLAN[l] = t; }); }
    else PLAN = c.plan==='ab' ? PLAN_AB : PLAN_ABC;
    CARDIO = { min:25, zona:'Zona 2', desc:DEF_DESC }; LOCAIS = LOCAIS_ALL.slice(); OBRIG = [];
    let nd = 0;
    if(own){ const u = new Set(); Object.values(PLAN).forEach(t => (t.dias||[]).forEach(d => u.add(+d))); nd = u.size; ACCT.dias = nd ? [...u].sort((x,y) => x-y) : []; }
    else { ACCT.dias = c.dias || [0,2,4]; nd = ACCT.dias.length; }
    ACCT.sexo = c.sexo==='M' ? 'M' : 'F'; ACCT.nome = S.nome;
    ACCT.plano = own ? (c.plan==='ia' ? 'Montado pela IA' : 'Montado por você') : c.plan==='ab' ? 'Corpo todo A e B' : 'A · B · C';
    SEMANA_TREINOS = nd || +c.semana || (c.ia ? iqN(c.ia) : 0) || Object.keys(PLAN).length || 3;
  }
  Object.keys(PLAN).forEach(l => { const t = PLAN[l]; t.nome = String(t.nome||'').trim() || String(t.foco||'').trim() || 'Treino '+l; tecNorm(t.ex); }); // técnicas: só as que existem e nas séries certas
  LETTERS = Object.keys(PLAN).sort();
  if(S.session && !PLAN[S.session.t]) S.session = null;
  ligaHist();
}
function startApp(){
  const sess = readSess();
  if(!sess){ renderLogin(); return; }
  if(sess.mode==='personal'){
    const a = FB ? FB.account(sess.code) : getAlunoByCode(sess.code);
    if(!a){ writeSess(null); renderLogin(); return; }
    ACCT = {mode:'personal', code:sess.code, aluno:a}; UKEY = KEY+':'+sess.code;
  } else {
    if(!sess.uid && !TESTE_LOCAL){ // usava sem conta: agora o app só funciona com conta (os treinos do aparelho vão junto)
      writeSess(null); LG.view = 'setup'; LG.step = 1; LG.serr = ''; LG.semConta = true; renderLogin('#su-nome'); return; }
    if(sess.uid && !autoLocal(sess.uid)){ writeSess(null); LG.view = 'entrar'; LG.en = {email:'', senha:'', show:false, msg:'Entre de novo para carregar seus treinos.'}; renderLogin('#en-email'); return; }
    ACCT = {mode:'self', uid:sess.uid||null, syncOk:!FB, dirty:false}; UKEY = sess.uid ? autoKey(sess.uid) : KEY+':self';
  }
  S = load() || fresh(false); applyAccount(); if(ACCT && ACCT.mode==='self' && S.conta && (S.conta.plan==='meu' || S.conta.plan==='ia') && mtLetras()) applyAccount(); tidy(); LASTSYNC = syncTxt(); save();
  if(needAceite()){ LG.view = 'aceite'; LG.ac = {t:false, s:false, p:false}; renderLogin(); return; }
  GS = loadGS(); FOTO = loadFotoU();
  uiReset(); loadLayouts();
  const lc = S.cardio[S.cardio.length-1];
  if(lc){ if(LOCAIS.includes(lc.local)) ui.cf.local = lc.local; if(INTENS.includes(lc.int)) ui.cf.int = lc.int; }
  if(!LOCAIS.includes(ui.cf.local)) ui.cf.local = LOCAIS[0];
  const lg = document.getElementById('login'); if(lg) lg.remove();
  document.body.classList.remove('locked');
  applyLook(); window.scrollTo(0,0); render();
  if(AFTER==='meus' && ACCT.mode==='self' && !(S.inst==='novo' && instMobile())){ AFTER = null; setTimeout(mtOpen, 400); }
  fbRefresh(); instAsk(); acompAsk(); siteAsk(); avalAsk();
  try { const vv = localStorage.getItem('simp-app-v'); if(vv && vv!==APPV) setTimeout(() => toast('App atualizado: versão '+APPV), 900); localStorage.setItem('simp-app-v', APPV); } catch(e){ /* sem armazenamento */ }
}
function logout(){
  PROG_REST = false; PROG_OK = false;
  save();
  if(FB) FB.autoFlush().then(() => FB.logout()); ui.rest = null; renderRest(); ui.sheet = null; $sheet.innerHTML = '';
  writeSess(null); ACCT = null; $app.innerHTML = ''; uiReset();
  const dk = document.getElementById('rdock'); if(dk) dk.remove(); document.body.classList.remove('has-dock');
  LG.view = 'login'; LG.code = ''; LG.err = ''; renderLogin();
}
/* entrada */
const SELF_ED = ['hipertrofia','emagrecer','corrida']; // sem personal: sem a opção Elas
const LG = { view:'login', code:'', err:'', show:false, fg:{email:'', sent:false, preview:null}, rs:{a:'', b:'', s1:false, s2:false, err:'', done:false}, step:1, f:{nome:'', email:'', senha:'', show:false, sexo:'F', ed:'hipertrofia', peso:'', plan:null, ck:{t:false, s:false, p:false}}, uid:null, uidMail:'', serr:'', gen:false, en:{email:'', senha:'', show:false, msg:''}, ac:{t:false, s:false, p:false}, doc:'termos', back:'login' };
const DPRESET = {2:[0,3], 3:[0,2,4], 4:[0,1,3,4], 5:[0,1,2,3,4], 6:[0,1,2,3,4,5]};
function bigLogo(){
  const arc = (d,c,i) => `<path class="lg-arc" style="--i:${i}" pathLength="1" d="${d}" fill="none" stroke="${c}" stroke-width="3.8"/>`;
  return `<svg class="logo" viewBox="-50 -50 100 100" aria-hidden="true"><circle r="50" fill="#0F1712"/>`+
    arc('M0 -44.3A44.3 44.3 0 0 1 44.3 0.31','#B794FF',0)+arc('M44.3 0A44.3 44.3 0 0 1 -0.31 44.3','#C8F542',1)+
    arc('M0 44.3A44.3 44.3 0 0 1 -44.3 -0.31','#FF9A2E',2)+arc('M-44.3 0A44.3 44.3 0 0 1 0.31 -44.3','#FF4646',3)+
    `<g transform="translate(-1 0) scale(0.5593) translate(-47.03 36.38)"><g class="lg-s"><path d="${S_PATH}" fill="#F6F5EF"/><path d="${DOT_PATH}" fill="#F6F5EF"/></g></g></svg>`;
}
function lgShell(){
  let w = document.getElementById('login');
  if(!w){ w = document.createElement('div'); w.id = 'login'; w.className = 'login'; w.setAttribute('role','dialog'); w.setAttribute('aria-modal','true'); w.setAttribute('aria-label','Entrar no Simplificando'); document.body.appendChild(w); }
  document.body.classList.add('locked');
  return w;
}
function instLogin(){
  if(!instMobile()) return '';
  return `<button type="button" class="in-card" data-a="lg-inst" aria-expanded="${!!LG.inst}"><span class="in-logo">${logo()}</span><span class="in-ct"><b>Use como aplicativo</b><span>Coloque na tela do celular. Sem loja, grátis.</span></span>${ic('chev', LG.inst ? 'in-chev on' : 'in-chev')}</button>`+
    (LG.inst ? `<div class="in-login">${instCorpo()}</div>` : '');
}
function renderLogin(focusSel){
  const bt = document.querySelector('.boot'); if(bt) bt.remove(); // a abertura (logo girando) sai quando o login aparece
  const w = lgShell();
  if(LG.view==='setup'){ w.innerHTML = vSetup(); }
  else if(LG.view==='entrar'){ w.innerHTML = vEntrar(); }
  else if(LG.view==='aceite'){ w.innerHTML = vAceite(); }
  else if(LG.view==='doc'){ w.innerHTML = vDoc(); }
  else if(LG.view==='forgot'){ w.innerHTML = vForgot(); }
  else if(LG.view==='reset'){ w.innerHTML = vReset(); }
  else if(LG.view==='novo'){ w.innerHTML = vNovo(); }
  else {
    const c = LG.code, err = LG.err;
    w.innerHTML = `<div class="lg-in"><div class="lg-hero"><span class="lg-logo">${bigLogo()}</span>
      <p class="lg-word">simplificando<span class="dot" aria-hidden="true"></span></p>
      <p class="lg-tag">Seu treino, sua água e seu cardio num lugar só. Do jeito que seu personal montou.</p></div>
      <div class="wave-strip" aria-hidden="true">${WAVE}</div>
      <form class="lg-form" data-lg="form" novalidate>
        ${FB && LG.forgot?`<label class="flabel" for="lg-em">E-mail para receber o link</label><div class="inp-wrap lg-mail"><input class="inp" id="lg-em" type="email" inputmode="email" autocomplete="username" autocapitalize="off" spellcheck="false" maxlength="80" placeholder="seu@email.com" value="${esc(LG.email||'')}"></div>`:''}
        <label class="flabel" for="lg-pw">Senha</label>
        <div class="lg-field${err?' err':''}"><input id="lg-pw" class="lg-inp" type="${LG.show?'text':'password'}" ${FB?'inputmode="numeric" maxlength="6"':'maxlength="64"'} autocomplete="current-password" autocapitalize="off" spellcheck="false" placeholder="Sua senha" aria-describedby="lg-err lg-help" aria-invalid="${!!err}" value="${esc(c)}">
          <button type="button" class="lg-eye" data-a="lg-eye" aria-pressed="${LG.show}" aria-label="${LG.show?'Esconder senha':'Mostrar senha'}">${ic(LG.show?'eyeOff':'eye')}</button></div>
        <p id="lg-err" class="lg-err" role="alert">${err}</p>
        <p id="lg-help" class="lg-help">${LG.help ? esc(LG.help) : 'A senha de 6 números cadastrada.'}</p>
        <button type="submit" class="btn primary block lg-go"${c.length?'':' disabled'}>Entrar${ic('chev')}</button>
      </form>
      <div class="lg-links"><button type="button" class="linkbtn" data-a="lg-forgot">Esqueci a senha</button><button type="button" class="linkbtn" data-a="lg-self">Treinar sem personal</button></div>
      <p class="lg-legal"><button type="button" class="linkbtn" data-a="lg-doc" data-v="termos">Termos de uso</button><span aria-hidden="true">·</span><button type="button" class="linkbtn" data-a="lg-doc" data-v="privacidade">Privacidade</button></p>
      ${instLogin()}
      ${FB?'':`<p class="lg-demo">${ic('bolt')}<span>Para testar: <b>482913</b> (Lucas) ou <b>731506</b> (Marina)</span></p>`}</div>`;
  }
  const f = focusSel && w.querySelector(focusSel);
  if(f){ try { f.focus({preventScroll:true}); } catch(e){ /* foco opcional */ } }
}
// e-mail de "esqueci a senha" com o design do Simplificando (recuperar.php no site); sem a chave lá, usa o e-mail padrão do Firebase
async function siteReset(email, aluno){
  try { const r = await fetch('/recuperar.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ email, aluno:!!aluno }) });
    const j = await r.json(); if(j && j.ok) return { ok:true, proprio:true }; if(j && j.erro) return { err:j.erro }; } catch(e){ /* sem o arquivo no site */ }
  return FB.resetMail(email, aluno);
}
// aluno com personal esqueceu a senha: e-mail para escolher um código novo
async function lgEsqueci(){
  const em = String(LG.email||'').trim(), h = document.getElementById('lg-help');
  if(!/^\S+@\S+\.\S+$/.test(em)){ if(!LG.forgot){ LG.forgot = true; LG.err = ''; renderLogin('#lg-em'); } lgFail('Digite o e-mail que você passou para o seu personal e toque de novo em “Esqueci a senha”.'); const i = document.getElementById('lg-em'); if(i) i.focus(); return; }
  if(h) h.textContent = 'Enviando…';
  const r = await siteReset(em, true);
  LG.help = r.err ? '' : r.proprio ? 'Pronto. Se esse e-mail estiver cadastrado pelo seu personal, chega um e-mail do Simplificando com o botão para escolher sua senha nova de 6 números. Olhe também o spam.' : 'Pronto. Se esse e-mail estiver cadastrado pelo seu personal, chega um link (olhe também o spam). No link, crie uma senha de 6 números e depois entre aqui com o seu e-mail e essa senha.';
  if(r.err){ lgFail(r.err); if(h) h.textContent = ''; return; }
  if(h) h.textContent = LG.help;
}
/* link do e-mail: escolher o código novo (aluno com personal) ou a senha nova (sem personal) */
function resetParams(){ try { const q = new URLSearchParams(location.search); if(q.get('mode')!=='resetPassword' || !q.get('oobCode')) return null; const cu = q.get('continueUrl')||''; return { oob:q.get('oobCode'), aluno:/tipo=aluno/.test(cu) || q.get('tipo')==='aluno' }; } catch(e){ return null; } }
function openNovo(rp){
  LG.view = 'novo'; LG.nv = { oob:rp.oob, aluno:rp.aluno, email:'', a:'', b:'', err:'', busy:false }; renderLogin();
  FB.resetCheck(rp.oob).then(r => { if(LG.view!=='novo') return; if(r.err) LG.nv.err = r.err; else LG.nv.email = r.email; renderLogin('#nv-a'); });
}
function vNovo(){
  const n = LG.nv, al = n.aluno;
  return `<div class="lg-in su"><p class="eyebrow">Simplificando</p><h1 class="lg-h">${al?'Escolha sua senha nova':'Crie uma senha nova'}<span class="dot" aria-hidden="true"></span></h1>
    <p class="muted">${n.email ? 'Conta: <b>'+esc(n.email)+'</b>' : n.err ? '' : 'Conferindo o link…'}</p>
    <form class="lg-form su-form" data-lg="novo" novalidate>
      <label class="flabel" for="nv-a">Senha nova (6 números)</label><div class="inp-wrap"><input class="inp" id="nv-a" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password" value="${esc(n.a)}"></div>
      <label class="flabel" for="nv-b">Repita a senha</label><div class="inp-wrap"><input class="inp" id="nv-b" type="password" inputmode="numeric" maxlength="6" autocomplete="new-password" value="${esc(n.b)}"></div>
      <p class="lg-err" id="nv-err" role="alert">${esc(n.err||'')}</p>
      <button type="submit" class="btn primary block lg-go"${n.busy||!n.email?' disabled':''}>${al?'Salvar e entrar':'Salvar senha'}${ic('chev')}</button>
    </form>
    ${al ? '<p class="foot0">Seu personal também passa a ver essa senha no painel, para te ajudar se precisar.</p>' : ''}
    <div class="lg-links"><button type="button" class="linkbtn" data-a="nv-sair">Voltar para a entrada</button></div></div>`;
}
async function novoSalvar(){
  const n = LG.nv, a = (document.getElementById('nv-a')||{}).value||'', b = (document.getElementById('nv-b')||{}).value||''; n.a = a; n.b = b;
  const bad = m => { n.err = m; const e = document.getElementById('nv-err'); if(e) e.textContent = m; vibrate([40,40,40]); };
  if(!/^\d{6}$/.test(a)) return bad('A senha tem que ter exatamente 6 números.');
  if(a!==b) return bad('As duas senhas não são iguais.');
  n.busy = true; renderLogin();
  const r = await FB.resetSave(n.oob, a, n.aluno);
  n.busy = false;
  if(r.err){ n.err = r.err; renderLogin('#nv-a'); return; }
  try { history.replaceState(null, '', location.pathname); } catch(e){ /* sem histórico */ }
  if(n.aluno && r.p){ writeSess({mode:'personal', code:r.p.aid}); LG.view = 'login'; LG.nv = null; welcome(firstName(r.p.perfil.nome), r.p.perfil.sexo, 'Senha nova salva. Seu treino está pronto.'); return; }
  LG.view = 'entrar'; LG.en = {email:r.email||'', senha:'', show:false, msg:'Senha nova salva. Entre com ela.'}; LG.nv = null; renderLogin('#en-senha');
}
function lgInput(el){
  const v = el.value;
  LG.code = v;
  const go = document.querySelector('#login .lg-go'); if(go) go.disabled = !v.length;
  if(LG.err){ LG.err = ''; const e = document.getElementById('lg-err'); if(e) e.textContent = ''; const fd = el.closest('.lg-field'); if(fd) fd.classList.remove('err'); el.setAttribute('aria-invalid','false'); }
}
function lgFail(msg){
  LG.err = msg;
  const fd = document.querySelector('#login .lg-field'), e = document.getElementById('lg-err'), inp = document.getElementById('lg-pw');
  if(e) e.textContent = LG.err; if(inp){ inp.setAttribute('aria-invalid','true'); inp.select(); }
  if(fd){ fd.classList.remove('err'); void fd.offsetWidth; fd.classList.add('err'); }
  vibrate([40,40,40]);
}
async function tryLoginFB(){
  const code = String(LG.code||'').replace(/\D/g,'');
  if(!code){ lgFail('Digite sua senha.'); return; }
  if(code.length!==6){ lgFail('A senha do app tem 6 números. Confira com seu personal.'); return; }
  const go = document.querySelector('#login .lg-go'); if(go){ go.disabled = true; go.classList.add('busy'); }
  const em = LG.forgot ? String(LG.email||'').trim() : '';
  if(em && !/^\S+@\S+\.\S+$/.test(em)){ lgFail('Confira o e-mail. Ex.: voce@email.com'); return; }
  const r = await FB.login(code, em);
  if(go){ go.disabled = false; go.classList.remove('busy'); }
  if(r.err){ lgFail(r.err); return; }
  writeSess({mode:'personal', code:r.p.aid});
  const inp = document.getElementById('lg-pw'); if(inp) inp.blur();
  welcome(firstName(r.p.perfil.nome), r.p.perfil.sexo);
}
function tryLogin(){
  if(FB){ tryLoginFB(); return; }
  const a = LG.code ? findAlunoByPassword(LG.code) : null;
  if(!a){ lgFail(!LG.code ? 'Digite sua senha.' : 'Senha não encontrada. Confira com seu personal.'); return; }
  writeSess({mode:'personal', code:a.codigo});
  const inp = document.getElementById('lg-pw'); if(inp) inp.blur();
  welcome(firstName(a.perfil.nome), a.perfil.sexo);
}
function welcome(nome,sexo,msg){
  const w = document.createElement('div'); w.className = 'wel'; w.setAttribute('role','status');
  w.innerHTML = `<span class="wel-ring">${bigLogo()}</span><h1>${welcomeWord(sexo)}, ${esc(nome)}<span class="dot" aria-hidden="true"></span></h1><p>${msg||'Seu treino está pronto.'}</p>`;
  document.body.appendChild(w);
  setTimeout(() => { startApp(); w.classList.add('out'); setTimeout(() => w.remove(), RM() ? 0 : 380); }, RM() ? 700 : 1500);
}
function lgForgot(){ LG.view = 'forgot'; LG.fg = {email:'', sent:false, preview:null}; renderLogin('#fg-email'); }
function vForgot(){
  const f = LG.fg;
  const top = `<div class="lg-top"><button type="button" class="linkbtn pk-back" data-a="lg-back">${ic('chevL')}Voltar ao login</button></div>`;
  if(!f.sent) return `<div class="lg-in su">${top}<p class="eyebrow">Senha</p><h1 class="lg-h">Esqueceu a senha<span class="dot" aria-hidden="true"></span></h1>
    <p class="muted">Digite o e-mail que seu personal cadastrou. Vamos mandar um link para você criar uma nova senha.</p>
    <form class="lg-form" data-lg="forgot" novalidate><label class="flabel" for="fg-email">E-mail</label><div class="inp-wrap lg-mail"><input class="inp" id="fg-email" type="email" inputmode="email" autocomplete="email" autocapitalize="off" spellcheck="false" placeholder="voce@email.com" value="${esc(f.email)}"></div>
    <p class="lg-err" id="fg-err" role="alert"></p><button type="submit" class="btn primary block lg-go"${f.email.trim()?'':' disabled'}>Enviar link${ic('chev')}</button></form>
    <p class="lg-demo">${ic('bolt')}<span>Para testar: <b>lucas.exemplo@email.com</b> ou <b>marina.exemplo@email.com</b></span></p></div>`;
  const pv = f.preview;
  return `<div class="lg-in su">${top}<h1 class="lg-h">Confira seu e-mail<span class="dot" aria-hidden="true"></span></h1>
    <p class="muted" role="status">Se esse e-mail estiver cadastrado, você vai receber um link para criar uma nova senha.</p>
    ${pv ? `<section class="mailpv" aria-label="Prévia do e-mail, modo de teste"><p class="mp-tag">${ic('msg')}Prévia do e-mail (modo de teste)</p>
      <div class="mp-head"><span class="mp-av" aria-hidden="true">${logo()}</span><div><b>Simplificando</b><span>para ${esc(pv.email)}</span></div></div>
      <p class="mp-subj">Redefina sua senha</p>
      <p>Oi, ${esc(pv.nome)}! Recebemos um pedido para criar uma nova senha no Simplificando. É só tocar no botão abaixo. O link vale por 1 hora.</p>
      <button type="button" class="btn primary block" data-a="lg-reset-open">Criar nova senha</button>
      <p class="mp-foot">Não foi você? Pode ignorar este e-mail. Sua senha continua a mesma.</p></section>` : ''}
    <button type="button" class="btn block ghost" data-a="lg-back">Voltar ao login</button></div>`;
}
const pwRules = (a,b) => ({ len:/^\d{6}$/.test(a), match:!!a && a===b });
function pwStrength(a){ if(!a) return ['','']; let k = 0; if(/[a-zA-Z]/.test(a)) k++; if(/\d/.test(a)) k++; if(/[^a-zA-Z0-9]/.test(a)) k++; if(a.length>=12) k++; return a.length<8 ? ['fraca','w1'] : k>=3 ? ['forte','w3'] : ['média','w2']; }
function vReset(){
  const r = LG.rs, tk = readReset();
  if(r.done) return `<div class="lg-in su lg-ok"><span class="lg-okc" aria-hidden="true">${ic('check')}</span><h1 class="lg-h">Senha alterada<span class="dot" aria-hidden="true"></span></h1><p class="muted" role="status">Pronto. Use a nova senha para entrar. A antiga não funciona mais neste aparelho.</p><button type="button" class="btn primary block" data-a="lg-back">Ir para o login${ic('chev')}</button></div>`;
  if(!tk) return `<div class="lg-in su"><h1 class="lg-h">Link inválido<span class="dot" aria-hidden="true"></span></h1><p class="muted">Esse link expirou ou já foi usado. Peça um novo em “Esqueci a senha”.</p><button type="button" class="btn primary block" data-a="lg-forgot">Pedir novo link</button><button type="button" class="btn block ghost" data-a="lg-back">Voltar ao login</button></div>`;
  const ru = pwRules(r.a,r.b), stg = ['',''];
  const fld = (id,lab,v,show,k,ac) => `<label class="flabel" for="${id}">${lab}</label><div class="lg-field sm"><input id="${id}" class="lg-inp" type="${show?'text':'password'}" inputmode="numeric" maxlength="6" autocomplete="${ac}" autocapitalize="off" spellcheck="false" value="${esc(v)}" aria-describedby="rs-rules"><button type="button" class="lg-eye" data-a="lg-eye${k}" aria-pressed="${show}" aria-label="${show?'Esconder':'Mostrar'} ${lab.toLowerCase()}">${ic(show?'eyeOff':'eye')}</button></div>`;
  return `<div class="lg-in su"><div class="lg-hero"><span class="lg-logo sm">${bigLogo()}</span></div><p class="eyebrow">Nova senha</p><h1 class="lg-h">Crie sua nova senha<span class="dot" aria-hidden="true"></span></h1>
    <form class="lg-form" data-lg="reset" novalidate>${fld('rs-1','Nova senha',r.a,r.s1,1,'new-password')}
      <p class="rs-str ${stg[1]}" data-rs-str aria-live="polite">${stg[0]?'Força da senha: <b>'+stg[0]+'</b>':''}</p>
      ${fld('rs-2','Confirme a nova senha',r.b,r.s2,2,'new-password')}
      <ul class="rs-rules" id="rs-rules"><li data-rule="len" class="${ru.len?'ok':''}">${ic('check')}6 números</li><li data-rule="match" class="${ru.match?'ok':''}">${ic('check')}As duas senhas iguais</li></ul>
      <p class="lg-err" id="rs-err" role="alert">${r.err}</p>
      <button type="submit" class="btn primary block lg-go"${ru.len&&ru.match?'':' disabled'}>Salvar nova senha</button></form></div>`;
}
function rsInput(){
  const a = document.getElementById('rs-1'), b = document.getElementById('rs-2'); if(!a || !b) return;
  LG.rs.a = a.value; LG.rs.b = b.value; LG.rs.err = '';
  const ru = pwRules(a.value,b.value), stg = ['',''];
  document.querySelectorAll('#login [data-rule]').forEach(li => li.classList.toggle('ok', !!ru[li.dataset.rule]));
  const st = document.querySelector('#login [data-rs-str]'); if(st){ st.className = 'rs-str '+stg[1]; st.innerHTML = stg[0] ? 'Força da senha: <b>'+stg[0]+'</b>' : ''; }
  const go = document.querySelector('#login .lg-go'); if(go) go.disabled = !(ru.len && ru.match);
  const e = document.getElementById('rs-err'); if(e) e.textContent = '';
}
function openReset(){ LG.view = 'reset'; LG.rs = {a:'', b:'', s1:false, s2:false, err:'', done:false}; renderLogin('#rs-1'); }
function lgSubmit(kind){
  if(kind==='form') return tryLogin();
  if(kind==='novo') return novoSalvar();
  if(kind==='conta') return suConta();
  if(kind==='entrar') return suEntrar();
  if(kind==='forgot'){
    const v = (document.getElementById('fg-email')||{}).value || '';
    if(!/^\S+@\S+\.\S+$/.test(v.trim())){ const e = document.getElementById('fg-err'); if(e) e.textContent = 'Confira o e-mail. Ex.: voce@email.com'; return; }
    const r = requestPasswordReset(v); LG.fg = {email:v, sent:true, preview:r.preview}; renderLogin(); return;
  }
  if(kind==='reset'){
    const tk = readReset(), res = resetPassword(tk && tk.token, LG.rs.a);
    if(!res.ok){ LG.rs.err = res.err; renderLogin('#rs-1'); return; }
    const ss = readSess(); if(ss && ss.code===res.code) writeSess(null);
    LG.rs.done = true; renderLogin('.lg-ok .btn');
  }
}
function lgCopy(){
  const ta = document.getElementById('fg-msg'); if(!ta) return;
  const ok = () => toast('Mensagem copiada. Agora é só colar na conversa.');
  const fb = () => { try { ta.focus(); ta.select(); const r = document.execCommand && document.execCommand('copy'); if(r) ok(); else toast('Selecione o texto e copie.'); } catch(e){ toast('Selecione o texto e copie.'); } };
  try { if(navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(ta.value).then(ok, fb); else fb(); } catch(e){ fb(); }
}
