(() => {
'use strict';

/* ---------- conteúdo do personal ---------- */
const ED = { hipertrofia:{nome:'Hipertrofia',c:'#FF4646'}, emagrecer:{nome:'Emagrecer',c:'#FF9A2E'}, corrida:{nome:'Corrida',c:'#C8F542'}, elas:{nome:'Elas',c:'#B794FF'} };
let CARDIO = { min:25, zona:'Zona 2', desc:'Ritmo em que dá para conversar, mas não para cantar.' };
let OBRIG = []; // cardio obrigatório do personal: [{local:'Escada', min:40, vezes:3, dias:['seg','qua']}]
const LOCAIS_ALL = ['Esteira','Bicicleta','Rua','Escada','Elíptico','Outro'];
let LOCAIS = LOCAIS_ALL.slice();
const INTENS = ['Leve','Moderada','Forte'];
const ZONA = { Leve:['Zona 2','dá para conversar tranquilo'], Moderada:['Zona 3','só frases curtas'], Forte:['Zona 4','só palavras soltas'] };
let SEMANA_TREINOS = 4;
const streakMin = () => Math.max(1, Math.min(7, SEMANA_TREINOS || STREAK_MIN_DEF));
const STREAK_MIN_DEF = 3;
const SONO = [5,6,7,8,9], SONO_L = ['5h ou -','6h','7h','8h','9h ou +'];
const DOR = ['Nenhuma','Leve','Média','Forte'];

let PLAN = {
  A:{ nome:'Inferiores', foco:'Quadríceps e glúteo', min:55, ex:[
    { id:'agach', nome:'Agachamento livre', s:4, r:'8–10', d:90, cad:'3-1-1', alvo:'Quadríceps e glúteo', aq:true, cues:['Pés na largura dos ombros, pontas levemente para fora.','Desça levando o quadril para trás até a coxa passar da linha do joelho.','Joelhos seguem a direção dos pés. Suba soltando o ar.'], erro:'tirar o calcanhar do chão ou deixar o joelho cair para dentro.' },
    { id:'leg', nome:'Leg press 45°', s:3, r:'10–12', d:75, cad:'3-0-1', alvo:'Quadríceps', cues:['Lombar e quadril encostados no banco o tempo todo.','Desça até o joelho chegar perto de 90°.','Empurre com o pé inteiro, sem travar o joelho no alto.'], erro:'descer tanto que o quadril sai do banco.' },
    { id:'extensora', nome:'Cadeira extensora', s:3, r:'12–15', d:60, cad:'2-1-2', alvo:'Quadríceps', cues:['Joelho alinhado ao eixo da máquina.','Estenda até quase travar e segure 1 segundo.','Volte devagar, sem deixar o peso bater.'], erro:'dar impulso com o quadril.' },
    { id:'afundo', nome:'Afundo com halteres', s:3, r:'10 cada', d:60, cad:'2-0-1', alvo:'Glúteo e quadríceps', cues:['Passo longo, tronco levemente inclinado à frente.','Desça até o joelho de trás quase tocar o chão.','Suba empurrando com o calcanhar da perna da frente.'], erro:'passo curto demais, que joga a carga no joelho.' },
    { id:'pelvica', nome:'Elevação pélvica', s:4, r:'10', d:75, cad:'2-2-1', alvo:'Glúteo', cues:['Escápulas apoiadas no banco, barra na dobra do quadril.','Suba até o tronco ficar alinhado com as coxas.','Segure 2 segundos no alto contraindo o glúteo.'], erro:'arquear a lombar em vez de subir com o quadril.' },
    { id:'panturrilha', nome:'Panturrilha em pé', s:4, r:'15', d:45, cad:'2-2-1', alvo:'Panturrilha', cues:['Ponta dos pés na borda do degrau.','Desça o calcanhar até alongar bem.','Suba o máximo e segure 2 segundos.'], erro:'fazer meio movimento, rápido demais.' }
  ]},
  B:{ nome:'Empurrar', foco:'Peito, ombro e tríceps', min:50, ex:[
    { id:'supino', nome:'Supino reto com barra', s:4, r:'8–10', d:90, cad:'3-1-1', alvo:'Peitoral', aq:true, cues:['Escápulas juntas e para baixo, pés firmes no chão.','Desça a barra até a linha do mamilo.','Empurre numa linha levemente diagonal, em direção aos olhos.'], erro:'tirar o quadril do banco para empurrar mais.' },
    { id:'inclinado', nome:'Supino inclinado com halteres', s:3, r:'10', d:75, cad:'3-0-1', alvo:'Peitoral superior', cues:['Banco entre 30° e 45°.','Cotovelos a cerca de 45° do tronco.','Suba aproximando os halteres, sem bater um no outro.'], erro:'abrir demais os cotovelos.' },
    { id:'desenv', nome:'Desenvolvimento com halteres', s:3, r:'10', d:75, cad:'2-0-1', alvo:'Ombro', cues:['Sentado, costas apoiadas e abdômen firme.','Halteres na altura das orelhas.','Empurre para cima sem arquear a lombar.'], erro:'jogar o tronco para trás.' },
    { id:'lateral', nome:'Elevação lateral', s:3, r:'12–15', d:45, cad:'2-1-2', alvo:'Deltoide lateral', cues:['Cotovelos levemente flexionados.','Suba até a linha do ombro, mãos na altura dos cotovelos.','Desça devagar, sem balançar.'], erro:'subir com impulso do tronco.' },
    { id:'triceps', nome:'Tríceps na polia com corda', s:3, r:'12', d:45, cad:'2-1-1', alvo:'Tríceps', cues:['Cotovelos colados ao tronco.','Estenda abrindo a corda no final.','Volte até 90° sem mexer o ombro.'], erro:'deixar os cotovelos subirem.' }
  ]},
  C:{ nome:'Puxar', foco:'Costas e bíceps', min:50, ex:[
    { id:'puxada', nome:'Puxada frente', s:4, r:'10', d:75, cad:'2-1-2', alvo:'Dorsal', aq:true, cues:['Pegada um pouco mais aberta que os ombros.','Puxe a barra até o alto do peito, peito para cima.','Suba controlando até esticar os braços.'], erro:'puxar atrás da nuca ou balançar o tronco.' },
    { id:'remada', nome:'Remada curvada', s:4, r:'8–10', d:90, cad:'2-1-2', alvo:'Costas', cues:['Tronco inclinado a 45°, coluna neutra.','Puxe a barra até o umbigo.','Junte as escápulas no alto.'], erro:'arredondar a lombar.' },
    { id:'baixa', nome:'Remada baixa', s:3, r:'12', d:60, cad:'2-1-2', alvo:'Costas', cues:['Joelhos levemente dobrados, tronco reto.','Puxe o triângulo até o abdômen.','Volte deixando as escápulas abrirem.'], erro:'levar o tronco para trás a cada repetição.' },
    { id:'facepull', nome:'Face pull', s:3, r:'15', d:45, cad:'2-1-2', alvo:'Ombro posterior', cues:['Polia na altura do rosto.','Puxe a corda em direção à testa, abrindo as mãos.','Cotovelos altos o tempo todo.'], erro:'usar carga alta e perder a postura.' },
    { id:'rosca', nome:'Rosca direta', s:3, r:'10–12', d:60, cad:'2-1-2', alvo:'Bíceps', cues:['Cotovelos fixos ao lado do corpo.','Suba sem balançar o tronco.','Desça até quase esticar o braço.'], erro:'usar o quadril para levantar a barra.' }
  ]}
};
let LETTERS = ['A','B','C'];

/* ---------- datas e formatos ---------- */
const pad = n => String(n).padStart(2,'0');
const dk = d => d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate());
/* dia (no fuso do celular) de uma data guardada em ISO/UTC: o aceite das 21h às 24h é do mesmo dia, não do seguinte */
function diaLocal(s){ const d = new Date(s); return isNaN(d) ? String(s||'').slice(0,10) : dk(d); }
const today = () => dk(new Date());
const parse = k => { const p = k.split('-').map(Number); return new Date(p[0],p[1]-1,p[2]); };
const addDays = (k,n) => { const d = parse(k); d.setDate(d.getDate()+n); return dk(d); };
const daysAgo = n => addDays(today(),-n);
const diffDays = (a,b) => Math.round((parse(a)-parse(b))/864e5);
const dm = k => { const d = parse(k); return pad(d.getDate())+'/'+pad(d.getMonth()+1); };
const WD = 'STQQSSD';
function dayLabel(k){ const d = diffDays(today(),k); if(d===0) return 'hoje'; if(d===1) return 'ontem'; return parse(k).toLocaleDateString('pt-BR',{weekday:'short',day:'numeric'}).replace('.',''); }
const fmtL = ml => (ml/1000).toFixed(1).replace('.',',')+' L';
const fmtN = n => Math.round(n).toLocaleString('pt-BR');
const fmtKg = v => { v = Math.round(v*100)/100; return Number.isInteger(v) ? String(v) : String(v).replace('.',','); };
const fmtKm = v => (Math.round(v*10)/10).toFixed(1).replace('.',',');
const num = s => { const v = parseFloat(String(s==null?'':s).replace(',','.')); return isFinite(v) ? v : 0; };
const int = s => parseInt(s,10) || 0;
function fmtDur(ms){ const s = Math.max(0,Math.floor(ms/1000)), h = Math.floor(s/3600), m = Math.floor(s%3600/60); return (h ? h+':'+pad(m) : pad(m))+':'+pad(s%60); }
const hm = m => pad(Math.floor(m/60))+':'+pad(Math.floor(m%60));
const nowMin = () => { const d = new Date(); return d.getHours()*60+d.getMinutes(); };
const esc = s => String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const range = r => { const m = String(r).match(/\d+/g) || ['10']; const lo = +m[0]; return [lo, m[1] ? +m[1] : lo]; };
const e1 = (kg,r) => r>0 ? kg*(1+Math.min(r,15)/30) : 0;
const step = kg => kg<10 ? 1 : kg<40 ? 2 : kg<100 ? 2.5 : 5;
const roundTo = (v,s) => Math.round(v/s)*s;
function pace(min,km){ const p = min/km; let m = Math.floor(p), s = Math.round((p-m)*60); if(s===60){ m++; s=0; } return m+':'+pad(s); }
const RM = () => { try { return matchMedia('(prefers-reduced-motion: reduce)').matches; } catch(e){ return false; } };
const vibrate = p => { try { if(navigator.userActivation && !navigator.userActivation.hasBeenActive) return; navigator.vibrate && navigator.vibrate(p); } catch(e){ /* sem vibração */ } };

/* ---------- estado ---------- */
const KEY = 'simplificando-pro-v2';
const APPV = '17 nov · 148'; // muda a cada zip: confira no perfil se o site está com a versão nova
function fresh(example){
  return { v:2, example:!!example, nome:'', ed:'hipertrofia', waterGoal:3000, cardioGoal:120, water:{}, wlog:{}, remind:{on:true,every:90},
    history:[], cardio:[], logs:{}, warm:{}, last:{}, loadHist:{}, pr:{}, checkin:{}, session:null };
}
function rng(seed){ return () => { seed = seed*16807 % 2147483647; return (seed-1)/2147483646; }; }
const BASE = {agach:55,leg:180,extensora:45,afundo:12,pelvica:70,panturrilha:40,supino:40,inclinado:16,desenv:12,lateral:7,triceps:25,puxada:45,remada:35,baixa:40,facepull:20,rosca:20};
const TOP = ['agach','extensora','pelvica','supino','lateral','puxada','facepull'];
function seed(){
  const s = fresh(true), r = rng(11), t0 = today();
  s.nome = 'Marina';
  for(let i=41;i>=1;i--) s.water[daysAgo(i)] = Math.round((2250+r()*1250)/50)*50;
  const days = [];
  for(let i=42;i>=1;i--){
    const k = daysAgo(i), wd = parse(k).getDay();
    let on = wd===1||wd===2||wd===4||wd===5||(wd===6&&r()<.35);
    if(on && r()<.1) on = false;
    if(on) days.push(k);
  }
  const n = days.length, occ = {A:[],B:[],C:[]};
  days.forEach((k,j) => { const t = LETTERS[(((2-(n-1-j))%3)+3)%3]; occ[t].push(k); s.history.push({date:k,t,min:0,vol:0,sets:0,prs:[],rpe:0,note:''}); });
  LETTERS.forEach(t => {
    const ds = occ[t], N = ds.length;
    PLAN[t].ex.forEach(e => {
      const base = BASE[e.id], st = step(base), rg = range(e.r);
      s.loadHist[e.id] = [];
      ds.forEach((k,q) => {
        const kg = Math.max(st, base-Math.floor((N-1-q)/2)*st);
        let reps, rir;
        if(q===N-1){
          if(TOP.includes(e.id)){ reps = Array(e.s).fill(rg[1]); rir = reps.map((v,i) => i>=e.s-2 ? 1 : 2); }
          else { reps = Array.from({length:e.s},(_,i) => Math.max(rg[0]-(i===e.s-1&&rg[0]===rg[1]?1:0), rg[1]-i)); rir = reps.map((v,i) => i===e.s-1 ? 0 : 1); }
        } else { reps = Array(e.s).fill(rg[1]-(q%2)); rir = Array(e.s).fill(1); }
        s.loadHist[e.id].push({d:k,kg});
        const h = s.history.find(x => x.date===k);
        h.sets += e.s; h.vol += reps.reduce((a,b)=>a+b,0)*kg;
        const best = e1(kg,Math.max(...reps)), old = s.pr[e.id];
        if(!old || best>old.e1+0.05){ if(old && q>=N-2 && TOP.includes(e.id)) h.prs.push(e.id); s.pr[e.id] = {e1:best,kg,reps:Math.max(...reps),d:k}; }
        if(q===N-1) s.last[e.id] = {kg,reps,rir,d:k};
      });
    });
  });
  s.history.forEach(h => { h.min = PLAN[h.t].min+Math.round(r()*12-6); h.vol = Math.round(h.vol); h.rpe = 6+Math.floor(r()*4); });
  const H = s.history;
  if(H.length>2){ H[H.length-3].note = 'Senti um pouco o joelho no afundo. Reduzi a amplitude na última série.'; H[H.length-1].note = 'Treino rendeu. Fiz tudo dentro do tempo.'; }
  for(let i=41;i>=1;i--){
    const k = daysAgo(i), wd = parse(k).getDay();
    if((wd===0||wd===2||wd===3||wd===6) && r()<.62){
      const local = ['Esteira','Rua','Bicicleta','Esteira','Elíptico','Rua'][Math.floor(r()*6)], min = 20+5*Math.floor(r()*4);
      const int_ = INTENS[r()<.55 ? 0 : r()<.7 ? 1 : 2];
      let km = 0;
      if(local==='Esteira'||local==='Rua') km = Math.round(min/(5.6+r()*1.6)*10)/10;
      else if(local==='Bicicleta') km = Math.round(min/60*(17+r()*6)*10)/10;
      s.cardio.push({date:k,local,min,int:int_,km});
    }
  }
  [[7,4,1],[6,3,2],[8,5,0],[5,2,2],[7,4,1],[8,4,1]].forEach((c,i) => { s.checkin[daysAgo(6-i)] = {sono:c[0],energia:c[1],dor:c[2]}; });
  const nm = nowMin();
  const ents = [[330,300],[235,500],[150,250],[50,200]].map(([ago,ml]) => ({m:nm-ago,ml})).filter(x => x.m>=360);
  s.wlog[t0] = ents; s.water[t0] = ents.reduce((a,x)=>a+x.ml,0);
  // nenhum treino em andamento: o aluno começa arrastando o botão na tela inicial
  return s;
}
function load(){ try { const raw = localStorage.getItem(UKEY); const v = raw ? JSON.parse(raw) : null; return v && v.v===2 ? v : null; } catch(e){ return null; } }
let LASTSYNC = '';
const syncTxt = () => { const em = S._em; S._em = 0; const t = JSON.stringify(S); S._em = em; return t; };
function save(){
  if(!ACCT) return;
  sesUlt(); try { lbSync(); } catch(e){ /* lembrete opcional */ }
  if(FB && ACCT.mode==='self' && ACCT.uid){
    const t = syncTxt(); if(t!==LASTSYNC){ LASTSYNC = t; S._em = Date.now(); ACCT.dirty = true; }
    if(ACCT.dirty && ACCT.syncOk){ ACCT.dirty = false; FB.autoSave(ACCT.uid, S); }
  }
  try { localStorage.setItem(UKEY, JSON.stringify(S)); } catch(e){ /* sem armazenamento: segue em memória */ }
  progSync();
}
/* o que o aluno com personal fez vai para o painel: treinos (academia, séries, comentários, esforço e mensagem), cardio, água e check-in */
let PROGTXT = '', progT = null, progBusy = false;
function progPayload(){
  const H = (S.history||[]).slice(-80).map(h => ({ d:h.date, t:h.t, nm:(PLAN[h.t]||{}).nome||'', min:h.min||0, m0:h.m0, hi:h.hi||'', hr:h.hr||'', sets:h.sets||0, vol:h.vol||0, rpe:h.rpe||0, note:h.note||'', gym:h.gym||'', ex:h.ex||null }));
  const w = {}, k = {}; for(let i=0; i<28; i++){ const dd = daysAgo(i); if(S.water && S.water[dd]) w[dd] = S.water[dd]; if(S.checkin && S.checkin[dd]) k[dd] = S.checkin[dd]; }
  const t0 = tDia(), X0 = (S.exc||{})[t0] || {}, fin = (S.history||[]).some(h => h.date===t0 && h.ex);
  const xc = fin ? [] : Object.keys(X0).map(id => { const c = X0[id], e = Object.values(PLAN).flatMap(p => p.ex||[]).find(x => x.id===id); return c && ((c.q||[]).length || c.n) ? { id, n:e ? e.nome : '', q:(c.q||[]).slice(), c:c.n||'' } : null; }).filter(Boolean);
  return JSON.stringify({ v:1, lay:S.lay||null, xc:xc.length ? { d:t0, ex:xc } : null, h:H, c:(S.cardio||[]).slice(-60).map(c => ({d:c.date, l:c.local, min:c.min, i:c.int})), w, wg:S.waterGoal||0, k, ses:S.session ? {t:S.session.t, gym:S.session.gym||'', d:S.session.d||today()} : null });
}
function progSync(now){
  if(!ACCT || !isPersonal() || !FB || !ACCT.code || !S || !PROG_OK) return;
  const txt = progPayload(); if(txt===PROGTXT) return;
  const code = ACCT.code;
  clearTimeout(progT);
  // o envio espera 2,5 s: se a pessoa saiu da conta nesse meio-tempo, não envia
  progT = setTimeout(async () => { if(!ACCT || ACCT.code!==code) return; if(progBusy){ progSync(); return; } progBusy = true; try { const ok = await FB.progresso(code, txt); if(ok) PROGTXT = txt; } catch(e){ /* tenta na próxima */ } progBusy = false; }, now ? 0 : 2500);
}

let S = fresh(false);
const PKEY = 'simplificando-pro-ui';
let PREF = (() => { try { const v = JSON.parse(localStorage.getItem(PKEY)||'null'); return v && typeof v==='object' ? v : {glass:false}; } catch(e){ return {glass:false}; } })();
const ACC = { laranja:['Laranja','#FF9A2E'], roxo:['Lilás','#B794FF'], vermelho:['Vermelho','#FF4646'], verde:['Verde','#C8F542'], branco:['Branco neutro','n'] };
if(!ACC[PREF.accent]) PREF.accent = 'vermelho';
const FKEY = 'simplificando-pro-foto';
const uidOf = () => !ACCT ? '' : ACCT.mode==='personal' ? ACCT.code : ACCT.uid ? 'auto:'+ACCT.uid : 'self';
let FOTO = '';
function loadFotoU(){ try { return localStorage.getItem(FKEY+':'+uidOf()) || ''; } catch(e){ return ''; } }
function saveFoto(v){ FOTO = v; try { v ? localStorage.setItem(FKEY+':'+uidOf(), v) : localStorage.removeItem(FKEY+':'+uidOf()); } catch(e){ toast('Não deu para salvar a foto neste navegador.'); } }
const avatarIn = () => FOTO ? `<span class="av-ph"><img src="${FOTO}" alt=""></span>` : logo();
function loadFoto(file){
  if(!file || !/^image\//.test(file.type)){ toast('Escolha uma imagem (JPG ou PNG).'); return; }
  const r = new FileReader();
  r.onload = () => { const im = new Image(); im.onload = () => {
    const n = 320, c = document.createElement('canvas'); c.width = c.height = n;
    const k = Math.min(im.width, im.height), x = c.getContext('2d');
    x.drawImage(im, (im.width-k)/2, (im.height-k)/2, k, k, 0, 0, n, n);
    saveFoto(c.toDataURL('image/jpeg', .86)); render(); renderSheet(); toast('Foto de perfil atualizada.'); vibrate(15);
  }; im.onerror = () => toast('Não consegui abrir essa imagem. Tente outra.'); im.src = r.result; };
  r.readAsDataURL(file);
}
function savePref(){
  const u = uidOf();
  if(u){ PREF.byUser = PREF.byUser || {}; PREF.byUser[u] = {c:PREF.cardioLayout||null, t:PREF.treinoLayout||null}; }
  // a escolha de jeito do cardio e do treino também vai com os dados do aluno: vale em qualquer aparelho (e no app da tela de início do iPhone)
  if(typeof S!=='undefined' && S && ACCT && (PREF.cardioLayout || PREF.treinoLayout)){ const l = {c:PREF.cardioLayout||null, t:PREF.treinoLayout||null}; if(JSON.stringify(S.lay||null)!==JSON.stringify(l)){ S.lay = l; try { save(); } catch(e){ /* salva na próxima */ } } }
  try { localStorage.setItem(PKEY, JSON.stringify(PREF)); } catch(e){ /* sem armazenamento */ }
}
/* cada conta escolhe os próprios modelos de cardio e treino no primeiro acesso */
function loadLayouts(){
  const u = uidOf(), b = (PREF.byUser||{})[u], n = (typeof S!=='undefined' && S && S.lay) || {};
  PREF.cardioLayout = (b && b.c) || n.c || undefined; PREF.treinoLayout = (b && b.t) || n.t || undefined;
}
const deviceTheme = () => { try { return matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'; } catch(e){ return 'dark'; } };
const curTheme = () => PREF.theme==='light'||PREF.theme==='dark' ? PREF.theme : deviceTheme();
function applyLook(){
  const r = document.documentElement, c = ACC[PREF.accent][1];
  r.classList.toggle('glass', !!PREF.glass);
  r.dataset.theme = curTheme(); r.dataset.accent = PREF.accent;
  r.style.setProperty('--brand', c==='n' ? 'var(--neutral)' : c);
  r.style.setProperty('--on-brand', c==='n' ? 'var(--on-neutral)' : '#0F1712');
}
const applyGlass = applyLook;
function tidy(){
  if(S && S.remind && ![30,60,90].includes(+S.remind.every)) S.remind.every = 90;
  const t = today(), td = tDia(); // o dia do treino aberto não é apagado
  Object.keys(S.logs).forEach(k => { if(k!==t && k!==td) delete S.logs[k]; });
  Object.keys(S.warm).forEach(k => { if(k!==t && k!==td) delete S.warm[k]; });
  if(S.exc) Object.keys(S.exc).forEach(k => { if(k!==t && k!==td) delete S.exc[k]; });
  Object.keys(S.wlog).forEach(k => { if(diffDays(t,k)>2) delete S.wlog[k]; });
}
tidy();

const UI0 = () => ({ tab:'hoje', t:null, sheet:null, pane:'series', rest:null, arm:null, ci:null, ciEdit:false, tw:{}, month:0, chartEx:null,
  remindFired:null, enter:true, day:today(), cf:{local:'Esteira',min:30,int:'Moderada'}, cfMore:false, cs:null, pick:null, tpick:null, pstep:null, pback:false, tsel:null, lopen:null, fopen:[], fed:null, fdone:null });
const ui = UI0();
function uiReset(){ Object.keys(ui).forEach(k => delete ui[k]); Object.assign(ui, UI0()); }

/* ---------- contas: aluno com personal (senha de 6 números) ou treino sem personal ---------- */
/* diretório de exemplo: hoje vem embutido; depois getAlunoByCode vira uma chamada ao servidor */
const DEMO_ALUNOS = [{"codigo":"482913","perfil":{"nome":"Lucas Andrade","sexo":"M","objetivo":"hipertrofia","dias":["seg","ter","qui","sex"]},"metas":{"waterGoal":3200,"cardioGoal":120,"cardio":{"min":25,"zona":"Zona 2","locais":["Esteira","Bicicleta","Rua"],"dias":["qua","sab"]},"semanaTreinos":4},"plano":{"nome":"Ciclo 3: força e volume","treinos":{"A":{"nome":"Inferiores","foco":"Quadríceps e glúteo","min":60,"ex":[{"id":"agach","nome":"Agachamento livre","alvo":"Quadríceps","s":4,"r":"8–10","d":90,"kg":55,"cad":"3-1-1","rir":2,"tec":"normal","aq":true,"cues":["Pés na largura dos ombros, pontas levemente para fora.","Desça levando o quadril para trás até a coxa passar da linha do joelho.","Joelhos seguem a direção dos pés. Suba soltando o ar."],"erro":"Tirar o calcanhar do chão ou deixar o joelho cair para dentro."},{"id":"leg","nome":"Leg press 45°","alvo":"Quadríceps","s":3,"r":"10–12","d":75,"kg":180,"cad":"3-0-1","rir":2,"tec":"normal","cues":["Lombar e quadril encostados no banco o tempo todo.","Desça até o joelho chegar perto de 90°.","Empurre com o pé inteiro, sem travar o joelho no alto."],"erro":"Descer tanto que o quadril sai do banco."},{"id":"extensora","nome":"Cadeira extensora","alvo":"Quadríceps","s":3,"r":"12–15","d":60,"kg":45,"cad":"2-1-2","rir":1,"tec":"normal","cues":["Joelho alinhado ao eixo da máquina.","Estenda até quase travar e segure 1 segundo.","Volte devagar, sem deixar o peso bater."],"erro":"Dar impulso com o quadril."},{"id":"afundo","nome":"Afundo com halteres","alvo":"Glúteo","s":3,"r":"10 cada","d":60,"kg":12,"cad":"2-0-1","rir":2,"tec":"normal","cues":["Passo longo, tronco levemente inclinado à frente.","Desça até o joelho de trás quase tocar o chão.","Suba empurrando com o calcanhar da perna da frente."],"erro":"Passo curto demais, que joga a carga no joelho."},{"id":"pelvica","nome":"Elevação pélvica","alvo":"Glúteo","s":4,"r":"10","d":75,"kg":70,"cad":"2-2-1","rir":2,"tec":"normal","cues":["Escápulas apoiadas no banco, barra na dobra do quadril.","Suba até o tronco ficar alinhado com as coxas.","Segure 2 segundos no alto contraindo o glúteo."],"erro":"Arquear a lombar em vez de subir com o quadril."},{"id":"panturrilha","nome":"Panturrilha em pé","alvo":"Panturrilha","s":4,"r":"15","d":45,"kg":40,"cad":"2-2-1","rir":1,"tec":"normal","cues":["Ponta dos pés na borda do degrau.","Desça o calcanhar até alongar bem.","Suba o máximo e segure 2 segundos."],"erro":"Fazer meio movimento, rápido demais."}]},"B":{"nome":"Empurrar","foco":"Peito, ombro e tríceps","min":50,"ex":[{"id":"supino","nome":"Supino reto com barra","alvo":"Peito","s":4,"r":"8–10","d":90,"kg":40,"cad":"3-1-1","rir":2,"tec":"normal","aq":true,"cues":["Escápulas juntas e para baixo, pés firmes no chão.","Desça a barra até a linha do mamilo.","Empurre numa linha levemente diagonal, em direção aos olhos."],"erro":"Tirar o quadril do banco para empurrar mais."},{"id":"inclinado","nome":"Supino inclinado com halteres","alvo":"Peito","s":3,"r":"10","d":75,"kg":16,"cad":"3-0-1","rir":2,"tec":"normal","cues":["Banco entre 30° e 45°.","Cotovelos a cerca de 45° do tronco.","Suba aproximando os halteres, sem bater um no outro."],"erro":"Abrir demais os cotovelos."},{"id":"desenv","nome":"Desenvolvimento com halteres","alvo":"Ombro","s":3,"r":"10","d":75,"kg":12,"cad":"2-0-1","rir":2,"tec":"normal","cues":["Sentado, costas apoiadas e abdômen firme.","Halteres na altura das orelhas.","Empurre para cima sem arquear a lombar."],"erro":"Jogar o tronco para trás."},{"id":"lateral","nome":"Elevação lateral","alvo":"Ombro","s":3,"r":"12–15","d":45,"kg":7,"cad":"2-1-2","rir":1,"tec":"biset","cues":["Cotovelos levemente flexionados.","Suba até a linha do ombro, mãos na altura dos cotovelos.","Desça devagar, sem balançar."],"erro":"Subir com impulso do tronco.","sup":"triceps"},{"id":"triceps","nome":"Tríceps na polia com corda","alvo":"Tríceps","s":3,"r":"12","d":45,"kg":25,"cad":"2-1-1","rir":1,"tec":"normal","cues":["Cotovelos colados ao tronco.","Estenda abrindo a corda no final.","Volte até 90° sem mexer o ombro."],"erro":"Deixar os cotovelos subirem."}]},"C":{"nome":"Puxar","foco":"Costas e bíceps","min":50,"ex":[{"id":"puxada","nome":"Puxada frente","alvo":"Costas","s":4,"r":"10","d":75,"kg":45,"cad":"2-1-2","rir":2,"tec":"normal","aq":true,"cues":["Pegada um pouco mais aberta que os ombros.","Puxe a barra até o alto do peito, peito para cima.","Suba controlando até esticar os braços."],"erro":"Puxar atrás da nuca ou balançar o tronco."},{"id":"remada","nome":"Remada curvada","alvo":"Costas","s":4,"r":"8–10","d":90,"kg":35,"cad":"2-1-2","rir":2,"tec":"normal","cues":["Tronco inclinado a 45°, coluna neutra.","Puxe a barra até o umbigo.","Junte as escápulas no alto."],"erro":"Arredondar a lombar."},{"id":"baixa","nome":"Remada baixa","alvo":"Costas","s":3,"r":"12","d":60,"kg":40,"cad":"2-1-2","rir":2,"tec":"normal","cues":["Joelhos levemente dobrados, tronco reto.","Puxe o triângulo até o abdômen.","Volte deixando as escápulas abrirem."],"erro":"Levar o tronco para trás a cada repetição."},{"id":"facepull","nome":"Face pull","alvo":"Ombro","s":3,"r":"15","d":45,"kg":20,"cad":"2-1-2","rir":2,"tec":"normal","cues":["Polia na altura do rosto.","Puxe a corda em direção à testa, abrindo as mãos.","Cotovelos altos o tempo todo."],"erro":"Usar carga alta e perder a postura."},{"id":"rosca","nome":"Rosca direta","alvo":"Bíceps","s":3,"r":"10–12","d":60,"kg":20,"cad":"2-1-2","rir":1,"tec":"normal","cues":["Cotovelos fixos ao lado do corpo.","Suba sem balançar o tronco.","Desça até quase esticar o braço."],"erro":"Usar o quadril para levantar a barra."}]}}},"financeiro":{"pagoAte":"2026-11-05"},"email":"lucas.exemplo@email.com"},{"codigo":"731506","perfil":{"nome":"Marina Costa","sexo":"F","objetivo":"emagrecer","dias":["seg","qua","sex"]},"metas":{"waterGoal":2500,"cardioGoal":150,"cardio":{"min":30,"zona":"Zona 2","locais":["Bicicleta","Elíptico","Esteira"],"dias":["ter","qui","sab"]},"semanaTreinos":3},"plano":{"nome":"Base e adaptação","treinos":{"A":{"nome":"Corpo todo 1","foco":"Base de força","min":45,"ex":[{"id":"goblet","nome":"Agachamento goblet","alvo":"Quadríceps","s":3,"r":"10–12","d":60,"kg":12,"cad":"3-1-1","rir":3,"tec":"normal","aq":true,"cues":["Kettlebell junto ao peito, cotovelos para baixo.","Desça entre as pernas com o tronco alto.","Suba empurrando o chão com o pé inteiro."],"erro":"Inclinar o tronco à frente e perder o peso do peito."},{"id":"supino","nome":"Supino reto com barra","alvo":"Peito","s":3,"r":"10–12","d":60,"cad":"3-1-1","rir":3,"tec":"normal","cues":["Escápulas juntas e para baixo, pés firmes no chão.","Desça a barra até a linha do mamilo.","Empurre numa linha levemente diagonal, em direção aos olhos."],"erro":"Tirar o quadril do banco para empurrar mais."},{"id":"puxada","nome":"Puxada frente","alvo":"Costas","s":3,"r":"10–12","d":60,"cad":"2-1-2","rir":3,"tec":"normal","cues":["Pegada um pouco mais aberta que os ombros.","Puxe a barra até o alto do peito, peito para cima.","Suba controlando até esticar os braços."],"erro":"Puxar atrás da nuca ou balançar o tronco."},{"id":"pelvica","nome":"Elevação pélvica","alvo":"Glúteo","s":3,"r":"12","d":60,"cad":"2-2-1","rir":3,"tec":"normal","cues":["Escápulas apoiadas no banco, barra na dobra do quadril.","Suba até o tronco ficar alinhado com as coxas.","Segure 2 segundos no alto contraindo o glúteo."],"erro":"Arquear a lombar em vez de subir com o quadril."},{"id":"prancha","nome":"Prancha","alvo":"Abdômen","s":3,"r":"30 s","d":45,"cad":"isometria","rir":3,"tec":"normal","cues":["Antebraços sob os ombros.","Contraia abdômen e glúteo, corpo em linha reta.","Respire curto sem soltar a barriga."],"erro":"Deixar o quadril subir ou cair."}]},"B":{"nome":"Corpo todo 2","foco":"Base de força","min":45,"ex":[{"id":"leg","nome":"Leg press 45°","alvo":"Quadríceps","s":3,"r":"12","d":60,"cad":"3-0-1","rir":3,"tec":"normal","aq":true,"cues":["Lombar e quadril encostados no banco o tempo todo.","Desça até o joelho chegar perto de 90°.","Empurre com o pé inteiro, sem travar o joelho no alto."],"erro":"Descer tanto que o quadril sai do banco."},{"id":"flexao","nome":"Flexão de braço","alvo":"Peito","s":3,"r":"8–12","d":60,"cad":"2-0-1","rir":3,"tec":"normal","cues":["Mãos um pouco mais abertas que os ombros.","Corpo reto da cabeça ao calcanhar, abdômen firme.","Desça até o peito quase tocar o chão e empurre."],"erro":"Deixar o quadril cair."},{"id":"serrote","nome":"Remada unilateral (serrote)","alvo":"Costas","s":3,"r":"10 cada","d":60,"cad":"2-1-2","rir":3,"tec":"normal","cues":["Joelho e mão do mesmo lado apoiados no banco.","Puxe o halter em direção ao quadril.","Desça até esticar o braço, sem girar o tronco."],"erro":"Girar o tronco para subir o peso."},{"id":"stiff","nome":"Stiff com halteres","alvo":"Posterior","s":3,"r":"12","d":60,"cad":"3-1-1","rir":3,"tec":"normal","cues":["Joelhos levemente dobrados e fixos.","Leve o quadril para trás descendo os halteres rente às pernas.","Suba contraindo glúteo e posterior, sem arredondar as costas."],"erro":"Arredondar a lombar para descer mais."},{"id":"deadbug","nome":"Dead bug","alvo":"Abdômen","s":3,"r":"10 cada","d":45,"cad":"2-1-2","rir":3,"tec":"normal","cues":["Deitado, lombar colada no chão.","Estenda braço e perna opostos devagar.","Volte e troque o lado sem tirar a lombar do chão."],"erro":"Arquear a lombar ao estender a perna."}]}}},"financeiro":{"pagoAte":"2026-11-01"},"email":"marina.exemplo@email.com"},{"codigo":"905274","perfil":{"nome":"Rafael Souza","sexo":"M","objetivo":"corrida","dias":["ter","qui","sab"]},"metas":{"waterGoal":3000,"cardioGoal":180,"cardio":{"min":40,"zona":"Zona 2","locais":["Rua","Esteira"],"dias":["seg","qua","sex","dom"]},"semanaTreinos":2},"plano":{"nome":"Força para os 10 km","treinos":{"A":{"nome":"Corpo todo 1","foco":"Base de força","min":45,"ex":[{"id":"goblet","nome":"Agachamento goblet","alvo":"Quadríceps","s":3,"r":"10–12","d":60,"kg":12,"cad":"3-1-1","rir":3,"tec":"normal","aq":true,"cues":["Kettlebell junto ao peito, cotovelos para baixo.","Desça entre as pernas com o tronco alto.","Suba empurrando o chão com o pé inteiro."],"erro":"Inclinar o tronco à frente e perder o peso do peito."},{"id":"supino","nome":"Supino reto com barra","alvo":"Peito","s":3,"r":"10–12","d":60,"cad":"3-1-1","rir":3,"tec":"normal","cues":["Escápulas juntas e para baixo, pés firmes no chão.","Desça a barra até a linha do mamilo.","Empurre numa linha levemente diagonal, em direção aos olhos."],"erro":"Tirar o quadril do banco para empurrar mais."},{"id":"puxada","nome":"Puxada frente","alvo":"Costas","s":3,"r":"10–12","d":60,"cad":"2-1-2","rir":3,"tec":"normal","cues":["Pegada um pouco mais aberta que os ombros.","Puxe a barra até o alto do peito, peito para cima.","Suba controlando até esticar os braços."],"erro":"Puxar atrás da nuca ou balançar o tronco."},{"id":"pelvica","nome":"Elevação pélvica","alvo":"Glúteo","s":3,"r":"12","d":60,"cad":"2-2-1","rir":3,"tec":"normal","cues":["Escápulas apoiadas no banco, barra na dobra do quadril.","Suba até o tronco ficar alinhado com as coxas.","Segure 2 segundos no alto contraindo o glúteo."],"erro":"Arquear a lombar em vez de subir com o quadril."},{"id":"prancha","nome":"Prancha","alvo":"Abdômen","s":3,"r":"30 s","d":45,"cad":"isometria","rir":3,"tec":"normal","cues":["Antebraços sob os ombros.","Contraia abdômen e glúteo, corpo em linha reta.","Respire curto sem soltar a barriga."],"erro":"Deixar o quadril subir ou cair."}]},"B":{"nome":"Corpo todo 2","foco":"Base de força","min":45,"ex":[{"id":"leg","nome":"Leg press 45°","alvo":"Quadríceps","s":3,"r":"12","d":60,"cad":"3-0-1","rir":3,"tec":"normal","aq":true,"cues":["Lombar e quadril encostados no banco o tempo todo.","Desça até o joelho chegar perto de 90°.","Empurre com o pé inteiro, sem travar o joelho no alto."],"erro":"Descer tanto que o quadril sai do banco."},{"id":"flexao","nome":"Flexão de braço","alvo":"Peito","s":3,"r":"8–12","d":60,"cad":"2-0-1","rir":3,"tec":"normal","cues":["Mãos um pouco mais abertas que os ombros.","Corpo reto da cabeça ao calcanhar, abdômen firme.","Desça até o peito quase tocar o chão e empurre."],"erro":"Deixar o quadril cair."},{"id":"serrote","nome":"Remada unilateral (serrote)","alvo":"Costas","s":3,"r":"10 cada","d":60,"cad":"2-1-2","rir":3,"tec":"normal","cues":["Joelho e mão do mesmo lado apoiados no banco.","Puxe o halter em direção ao quadril.","Desça até esticar o braço, sem girar o tronco."],"erro":"Girar o tronco para subir o peso."},{"id":"stiff","nome":"Stiff com halteres","alvo":"Posterior","s":3,"r":"12","d":60,"cad":"3-1-1","rir":3,"tec":"normal","cues":["Joelhos levemente dobrados e fixos.","Leve o quadril para trás descendo os halteres rente às pernas.","Suba contraindo glúteo e posterior, sem arredondar as costas."],"erro":"Arredondar a lombar para descer mais."},{"id":"deadbug","nome":"Dead bug","alvo":"Abdômen","s":3,"r":"10 cada","d":45,"cad":"2-1-2","rir":3,"tec":"normal","cues":["Deitado, lombar colada no chão.","Estenda braço e perna opostos devagar.","Volte e troque o lado sem tirar a lombar do chão."],"erro":"Arquear a lombar ao estender a perna."}]}}},"financeiro":{"pagoAte":"2026-10-10"},"email":"rafa.exemplo@email.com"},{"codigo":"260418","perfil":{"nome":"Carla Mendes","sexo":"F","objetivo":"hipertrofia","dias":["seg","ter","qui","sex","sab"]},"metas":{"waterGoal":2800,"cardioGoal":120,"cardio":{"min":25,"zona":"Zona 2","locais":["Escada","Esteira"],"dias":["qua","dom"]},"semanaTreinos":5},"plano":{"nome":"Glúteo e postura","treinos":{"A":{"nome":"Inferiores","foco":"Quadríceps e glúteo","min":60,"ex":[{"id":"agach","nome":"Agachamento livre","alvo":"Quadríceps","s":4,"r":"8–10","d":90,"kg":55,"cad":"3-1-1","rir":2,"tec":"normal","aq":true,"cues":["Pés na largura dos ombros, pontas levemente para fora.","Desça levando o quadril para trás até a coxa passar da linha do joelho.","Joelhos seguem a direção dos pés. Suba soltando o ar."],"erro":"Tirar o calcanhar do chão ou deixar o joelho cair para dentro."},{"id":"leg","nome":"Leg press 45°","alvo":"Quadríceps","s":3,"r":"10–12","d":75,"kg":180,"cad":"3-0-1","rir":2,"tec":"normal","cues":["Lombar e quadril encostados no banco o tempo todo.","Desça até o joelho chegar perto de 90°.","Empurre com o pé inteiro, sem travar o joelho no alto."],"erro":"Descer tanto que o quadril sai do banco."},{"id":"extensora","nome":"Cadeira extensora","alvo":"Quadríceps","s":3,"r":"12–15","d":60,"kg":45,"cad":"2-1-2","rir":1,"tec":"normal","cues":["Joelho alinhado ao eixo da máquina.","Estenda até quase travar e segure 1 segundo.","Volte devagar, sem deixar o peso bater."],"erro":"Dar impulso com o quadril."},{"id":"afundo","nome":"Afundo com halteres","alvo":"Glúteo","s":3,"r":"10 cada","d":60,"kg":12,"cad":"2-0-1","rir":2,"tec":"normal","cues":["Passo longo, tronco levemente inclinado à frente.","Desça até o joelho de trás quase tocar o chão.","Suba empurrando com o calcanhar da perna da frente."],"erro":"Passo curto demais, que joga a carga no joelho."},{"id":"pelvica","nome":"Elevação pélvica","alvo":"Glúteo","s":4,"r":"10","d":75,"kg":70,"cad":"2-2-1","rir":2,"tec":"normal","cues":["Escápulas apoiadas no banco, barra na dobra do quadril.","Suba até o tronco ficar alinhado com as coxas.","Segure 2 segundos no alto contraindo o glúteo."],"erro":"Arquear a lombar em vez de subir com o quadril."},{"id":"panturrilha","nome":"Panturrilha em pé","alvo":"Panturrilha","s":4,"r":"15","d":45,"kg":40,"cad":"2-2-1","rir":1,"tec":"normal","cues":["Ponta dos pés na borda do degrau.","Desça o calcanhar até alongar bem.","Suba o máximo e segure 2 segundos."],"erro":"Fazer meio movimento, rápido demais."}]},"B":{"nome":"Empurrar","foco":"Peito, ombro e tríceps","min":50,"ex":[{"id":"supino","nome":"Supino reto com barra","alvo":"Peito","s":4,"r":"8–10","d":90,"kg":40,"cad":"3-1-1","rir":2,"tec":"normal","aq":true,"cues":["Escápulas juntas e para baixo, pés firmes no chão.","Desça a barra até a linha do mamilo.","Empurre numa linha levemente diagonal, em direção aos olhos."],"erro":"Tirar o quadril do banco para empurrar mais."},{"id":"inclinado","nome":"Supino inclinado com halteres","alvo":"Peito","s":3,"r":"10","d":75,"kg":16,"cad":"3-0-1","rir":2,"tec":"normal","cues":["Banco entre 30° e 45°.","Cotovelos a cerca de 45° do tronco.","Suba aproximando os halteres, sem bater um no outro."],"erro":"Abrir demais os cotovelos."},{"id":"desenv","nome":"Desenvolvimento com halteres","alvo":"Ombro","s":3,"r":"10","d":75,"kg":12,"cad":"2-0-1","rir":2,"tec":"normal","cues":["Sentado, costas apoiadas e abdômen firme.","Halteres na altura das orelhas.","Empurre para cima sem arquear a lombar."],"erro":"Jogar o tronco para trás."},{"id":"lateral","nome":"Elevação lateral","alvo":"Ombro","s":3,"r":"12–15","d":45,"kg":7,"cad":"2-1-2","rir":1,"tec":"biset","cues":["Cotovelos levemente flexionados.","Suba até a linha do ombro, mãos na altura dos cotovelos.","Desça devagar, sem balançar."],"erro":"Subir com impulso do tronco.","sup":"triceps"},{"id":"triceps","nome":"Tríceps na polia com corda","alvo":"Tríceps","s":3,"r":"12","d":45,"kg":25,"cad":"2-1-1","rir":1,"tec":"normal","cues":["Cotovelos colados ao tronco.","Estenda abrindo a corda no final.","Volte até 90° sem mexer o ombro."],"erro":"Deixar os cotovelos subirem."}]},"C":{"nome":"Puxar","foco":"Costas e bíceps","min":50,"ex":[{"id":"puxada","nome":"Puxada frente","alvo":"Costas","s":4,"r":"10","d":75,"kg":45,"cad":"2-1-2","rir":2,"tec":"normal","aq":true,"cues":["Pegada um pouco mais aberta que os ombros.","Puxe a barra até o alto do peito, peito para cima.","Suba controlando até esticar os braços."],"erro":"Puxar atrás da nuca ou balançar o tronco."},{"id":"remada","nome":"Remada curvada","alvo":"Costas","s":4,"r":"8–10","d":90,"kg":35,"cad":"2-1-2","rir":2,"tec":"normal","cues":["Tronco inclinado a 45°, coluna neutra.","Puxe a barra até o umbigo.","Junte as escápulas no alto."],"erro":"Arredondar a lombar."},{"id":"baixa","nome":"Remada baixa","alvo":"Costas","s":3,"r":"12","d":60,"kg":40,"cad":"2-1-2","rir":2,"tec":"normal","cues":["Joelhos levemente dobrados, tronco reto.","Puxe o triângulo até o abdômen.","Volte deixando as escápulas abrirem."],"erro":"Levar o tronco para trás a cada repetição."},{"id":"facepull","nome":"Face pull","alvo":"Ombro","s":3,"r":"15","d":45,"kg":20,"cad":"2-1-2","rir":2,"tec":"normal","cues":["Polia na altura do rosto.","Puxe a corda em direção à testa, abrindo as mãos.","Cotovelos altos o tempo todo."],"erro":"Usar carga alta e perder a postura."},{"id":"rosca","nome":"Rosca direta","alvo":"Bíceps","s":3,"r":"10–12","d":60,"kg":20,"cad":"2-1-2","rir":1,"tec":"normal","cues":["Cotovelos fixos ao lado do corpo.","Suba sem balançar o tronco.","Desça até quase esticar o braço."],"erro":"Usar o quadril para levantar a barra."}]}}},"financeiro":{"pagoAte":"2026-11-15"},"email":"carla.exemplo@email.com"}];
const PLAN_ABC = PLAN, PLAN_AB = {"A":{"nome":"Corpo todo 1","foco":"Base de força","min":45,"ex":[{"id":"goblet","nome":"Agachamento goblet","alvo":"Quadríceps","s":3,"r":"10–12","d":60,"cad":"3-1-1","rir":3,"tec":"normal","aq":true,"cues":["Kettlebell junto ao peito, cotovelos para baixo.","Desça entre as pernas com o tronco alto.","Suba empurrando o chão com o pé inteiro."],"erro":"Inclinar o tronco à frente e perder o peso do peito."},{"id":"supino","nome":"Supino reto com barra","alvo":"Peito","s":3,"r":"10–12","d":60,"cad":"3-1-1","rir":3,"tec":"normal","cues":["Escápulas juntas e para baixo, pés firmes no chão.","Desça a barra até a linha do mamilo.","Empurre numa linha levemente diagonal, em direção aos olhos."],"erro":"Tirar o quadril do banco para empurrar mais."},{"id":"puxada","nome":"Puxada frente","alvo":"Costas","s":3,"r":"10–12","d":60,"cad":"2-1-2","rir":3,"tec":"normal","cues":["Pegada um pouco mais aberta que os ombros.","Puxe a barra até o alto do peito, peito para cima.","Suba controlando até esticar os braços."],"erro":"Puxar atrás da nuca ou balançar o tronco."},{"id":"pelvica","nome":"Elevação pélvica","alvo":"Glúteo","s":3,"r":"12","d":60,"cad":"2-2-1","rir":3,"tec":"normal","cues":["Escápulas apoiadas no banco, barra na dobra do quadril.","Suba até o tronco ficar alinhado com as coxas.","Segure 2 segundos no alto contraindo o glúteo."],"erro":"Arquear a lombar em vez de subir com o quadril."},{"id":"prancha","nome":"Prancha","alvo":"Abdômen","s":3,"r":"30 s","d":45,"cad":"isometria","rir":3,"tec":"normal","cues":["Antebraços sob os ombros.","Contraia abdômen e glúteo, corpo em linha reta.","Respire curto sem soltar a barriga."],"erro":"Deixar o quadril subir ou cair."}]},"B":{"nome":"Corpo todo 2","foco":"Base de força","min":45,"ex":[{"id":"leg","nome":"Leg press 45°","alvo":"Quadríceps","s":3,"r":"12","d":60,"cad":"3-0-1","rir":3,"tec":"normal","aq":true,"cues":["Lombar e quadril encostados no banco o tempo todo.","Desça até o joelho chegar perto de 90°.","Empurre com o pé inteiro, sem travar o joelho no alto."],"erro":"Descer tanto que o quadril sai do banco."},{"id":"flexao","nome":"Flexão de braço","alvo":"Peito","s":3,"r":"8–12","d":60,"cad":"2-0-1","rir":3,"tec":"normal","cues":["Mãos um pouco mais abertas que os ombros.","Corpo reto da cabeça ao calcanhar, abdômen firme.","Desça até o peito quase tocar o chão e empurre."],"erro":"Deixar o quadril cair."},{"id":"serrote","nome":"Remada unilateral (serrote)","alvo":"Costas","s":3,"r":"10 cada","d":60,"cad":"2-1-2","rir":3,"tec":"normal","cues":["Joelho e mão do mesmo lado apoiados no banco.","Puxe o halter em direção ao quadril.","Desça até esticar o braço, sem girar o tronco."],"erro":"Girar o tronco para subir o peso."},{"id":"stiff","nome":"Stiff com halteres","alvo":"Posterior","s":3,"r":"12","d":60,"cad":"3-1-1","rir":3,"tec":"normal","cues":["Joelhos levemente dobrados e fixos.","Leve o quadril para trás descendo os halteres rente às pernas.","Suba contraindo glúteo e posterior, sem arredondar as costas."],"erro":"Arredondar a lombar para descer mais."},{"id":"deadbug","nome":"Dead bug","alvo":"Abdômen","s":3,"r":"10 cada","d":45,"cad":"2-1-2","rir":3,"tec":"normal","cues":["Deitado, lombar colada no chão.","Estenda braço e perna opostos devagar.","Volte e troque o lado sem tirar a lombar do chão."],"erro":"Arquear a lombar ao estender a perna."}]}};
const DAYK = ['seg','ter','qua','qui','sex','sab','dom'];
/* senhas trocadas neste aparelho (modo de teste): a senha nova substitui o código original */
const PWKEY = 'simplificando-senhas', RSKEY = 'simplificando-reset';
function pwOverrides(){ try { return JSON.parse(localStorage.getItem(PWKEY)||'{}') || {}; } catch(e){ return {}; } }
function findAlunoByPassword(pw){ const ov = pwOverrides(); const a = DEMO_ALUNOS.find(x => ov[x.codigo] ? ov[x.codigo]===pw : x.codigo===pw); return a ? JSON.parse(JSON.stringify(a)) : null; }
/* trocar por chamadas ao servidor: requestPasswordReset(email) e resetPassword(token, novaSenha) */
function requestPasswordReset(email){
  const e = String(email||'').trim().toLowerCase(), a = DEMO_ALUNOS.find(x => (x.email||'').toLowerCase()===e);
  if(!a) return { ok:true, preview:null };
  const token = Array.from({length:4}, () => Math.random().toString(36).slice(2,8)).join('');
  try { localStorage.setItem(RSKEY, JSON.stringify({token, code:a.codigo, exp:Date.now()+3600e3})); } catch(err){ /* sem armazenamento */ }
  return { ok:true, preview:{ nome:firstName(a.perfil.nome), email:a.email } };
}
function readReset(){ try { const v = JSON.parse(localStorage.getItem(RSKEY)||'null'); return v && v.token && v.exp>Date.now() ? v : null; } catch(e){ return null; } }
function resetPassword(token, pw){
  const r = readReset(); if(!r || r.token!==token) return { ok:false, err:'Link inválido ou expirado. Peça um novo.' };
  if(!/^\d{6}$/.test(String(pw))) return { ok:false, err:'A senha tem que ter 6 números.' };
  const ov = pwOverrides(); ov[r.code] = String(pw);
  try { localStorage.setItem(PWKEY, JSON.stringify(ov)); localStorage.removeItem(RSKEY); } catch(e){ return { ok:false, err:'Não deu para salvar neste navegador.' }; }
  return { ok:true, code:r.code };
}
function getAlunoByCode(code){ const a = DEMO_ALUNOS.find(x => x.codigo===String(code)); return a ? JSON.parse(JSON.stringify(a)) : null; }
