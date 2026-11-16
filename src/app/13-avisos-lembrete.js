/* ---------- lembrete do treino do dia: "Bora treinar, Joana? 💪" ----------
   1ª semana: não manda nada, só observa. A partir da 2ª semana: nos dias de treino, 30 min antes do horário em que a pessoa
   costuma começar (estudado nos treinos das últimas 4 semanas, por dia da semana; vai se ajustando sozinho).
   O app guarda o texto em simp-estado (o service worker monta o aviso) e manda os dias e horários para o push.php.
   Não avisa se já treinou (ou está treinando) hoje; sem dia fixo, para quando bate a meta da semana. */
const hmMin = t => { const m = /^(\d{1,2}):(\d{2})/.exec(String(t||'')); return m ? +m[1]*60 + +m[2] : null; };
const hIni = h => { const a = hmMin(h.hi); if(a!=null) return a; const f = hmMin(h.hr); return f==null ? null : f - (+h.min||0); }; // hora em que o treino começou
function usoIni(){ const D = [S.aceite && S.aceite.em ? String(S.aceite.em).slice(0,10) : '', avalUsoIni()].filter(Boolean).sort(); return D[0] || today(); }
const lbAPartir = () => addDays(usoIni(), 7);
const lbArred = m => Math.max(5*60, Math.min(22*60, Math.round((m-30)/15)*15)); // 30 min antes, de 15 em 15 min
const mediana = L => { const o = L.slice().sort((x,y) => x-y), k = Math.floor(o.length/2); return o.length%2 ? o[k] : (o[k-1]+o[k])/2; };
const lbDias = () => noPlan() ? [] : tDays().map(i => (i+1)%7); // 0 = domingo, como o celular conta
function lbHoras(){
  const ini = addDays(today(), -28), H = (S.history||[]).filter(h => h.date>=ini).map(h => ({ d:h.date, w:parse(h.date).getDay(), m:hIni(h) })).filter(x => x.m!=null && x.m>=240 && x.m<=23*60+30).sort((x,y) => x.d<y.d ? -1 : x.d>y.d ? 1 : 0);
  const geral = H.length ? lbArred(H[H.length-1].m) : 18*60, hs = {}; // dia sem histórico: o horário do treino mais recente
  lbDias().forEach(w => { const L = H.filter(x => x.w===w).map(x => x.m); hs[w] = L.length ? lbArred(mediana(L)) : geral; });
  return { h:geral, hs, base:H.length };
}
function lbSubTxt(){
  if(noPlan()) return 'Monte seu treino para receber';
  if(PREF.avDia===false) return 'Desligado';
  if(today() < lbAPartir()) return 'Começa na 2ª semana, no horário em que você costuma treinar';
  const x = lbHoras(), dias = lbDias(), vs = [...new Set(dias.map(w => x.hs[w]))];
  const qd = diasLivres() ? 'Até bater a meta da semana' : joinPt(tDays().map(i => TDAYS_L[i]));
  if(vs.length<=1) return qd+' · '+(x.base ? 'por volta das ' : 'às ')+hmTxt(vs[0]!=null ? vs[0] : x.h);
  return 'No seu horário de cada dia: '+dias.slice(0,3).map(w => TDAYS_L[(w+6)%7]+' '+hmTxt(x.hs[w])).join(', ')+(dias.length>3 ? '…' : '');
}
let LBC = '';
function lbSync(){
  if(!S || !ACCT) return;
  const hj = today(), wk = weekDays(0), on = PREF.avDia!==false && !noPlan();
  const feito = (S.history||[]).some(h => h.date===hj) || (S.session && (S.session.d||hj)===hj) ? hj : '';
  const pausa = diasLivres() && (S.history||[]).filter(h => wk.includes(h.date)).length >= SEMANA_TREINOS ? wk[6] : '';
  const t = nextTreino(), x = lbHoras(), dado = { on:on?1:0, h:x.h, hs:x.hs, dias:lbDias(), feito, pausa, desde:lbAPartir() };
  const txt = { nome:firstName(S.nome||''), t:t||'', tn:(PLAN[t]||{}).nome||'' }, k = JSON.stringify([dado, txt]);
  if(k===LBC) return; LBC = k;
  const url = new URL('__lembrete', location.href).href;
  try { if(window.caches) caches.open('simp-estado').then(c => c.match(url).then(r => r ? r.json() : null).catch(() => null).then(o => c.put(url, new Response(JSON.stringify(Object.assign({}, dado, txt, { av:o && o.av || '' })), {headers:{'Content-Type':'application/json'}})))).catch(() => {}); } catch(e){ /* sem cache */ }
  const ks = JSON.stringify(dado);
  if(pushLigado() && PREF.pushId && PREF.lbK!==ks){ PREF.lbK = ks; savePref(); pushPost('lembrete', dado); }
}
