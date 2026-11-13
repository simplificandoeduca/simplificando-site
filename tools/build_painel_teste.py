# Gera o painel de TESTE para outros personais (um HTML só, dados só no aparelho) a partir do src/admin.html.
# Uso: python3 tools/build_painel_teste.py  → produto/painel-personal-teste.html
# O seu painel (src/admin.html) não muda: as diferenças ficam todas aqui.
import os
R = os.path.dirname(os.path.dirname(os.path.abspath(__file__))) + '/'
s = open(R+'src/admin.html').read()
def rep(a, b, n=1):
    global s
    c = s.count(a)
    assert c == n, (c, a[:120])
    s = s.replace(a, b)

# ---------- página independente ----------
rep('<title>Simplificando · Painel do personal</title>',
    '<!doctype html>\n<html lang="pt-BR">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">\n<meta name="theme-color" content="#0F1712">\n<title>Simplificando · Painel do personal (teste)</title>')
s = s.rstrip('\n') + '\n</body>\n</html>\n'
# dados deste teste separados de qualquer outro painel no mesmo navegador
s = s.replace("'simplificando-admin-", "'simp-teste-")
# sem tela de senha: abre direto no Painel
rep('\nGate.start();\n', '\n')
# sem o banco online: o "app dos alunos" vem dos dados de teste guardados neste aparelho
rep("const pfOnline = () => !!FBP && Store.kind==='firebase';", "const pfOnline = () => true;")
rep("const L = await FBP.listProgresso(), by = {};", "const L = DEMO.listProgresso(), by = {};")
rep("const [a, b] = await Promise.all([FBP.listCol('sugestoes'), FBP.listCol('storiesUso')]); ST.sug = a; ST.sto = b.filter(x => x.acao!=='abriu' && x.acao!=='teste');",
    "const meus = new Set(Object.values(C.alunos).map(x => x.uid).filter(Boolean)), a = DEMO.list('sug').filter(x => meus.has(x.uid)), b = DEMO.list('sto').filter(x => meus.has(x.uid)); ST.sug = a; ST.sto = b;")
rep("FBP.setLida(d.k, !!d.v).catch(", "DEMO.setLida(d.k, !!d.v).catch(")
rep("try { ST.av = await FBP.listCol('avaliacoes'); ST.avErr = ''; }", "try { const meus2 = new Set(Object.values(C.alunos).map(x => x.uid).filter(Boolean)); ST.av = DEMO.list('av').filter(x => meus2.has(x.uid)); ST.avErr = ''; }")
rep("FBP.dropDoc('sugestoes', d.k).catch(", "DEMO.dropDoc('sug', d.k).catch(")

# ---------- abas: Painel, Alunos, Biblioteca, Estatísticas ----------
rep("const VIEWS = [['painel','Painel','grid'],['alunos','Alunos','users'],['perfis','Perfis','eye'],['biblioteca','Biblioteca','book'],['estatisticas','Estatísticas','chart'],['ajustes','Ajustes','gear']];",
    "const VIEWS = [['painel','Painel','grid'],['alunos','Alunos','users'],['biblioteca','Biblioteca','book'],['estatisticas','Estatísticas','chart']];")
rep("esc(cfg().nomePersonal||'Defina seu nome em Ajustes')", "esc(cfg().nomePersonal||'Versão de teste')")
rep("+'Modo local: os dados ficam só neste navegador</span>'", "+'Versão de teste: os dados ficam só neste aparelho</span>'")

# ---------- Painel: Agenda no lugar de "Precisa de atenção" ----------
rep("  h += stashCard();\n  if(all.length) h += semanaCard();\n  h += lembreteDatas();\n",
    "  h += demoCard();\n  if(all.length) h += semanaCard();\n")
rep("""<h3 class="sec">Precisa de atenção<span class="dot" aria-hidden="true"></span></h3><div class="list">'+att+'</div></div>'+""",
    """<h3 class="sec">Agenda<span class="dot" aria-hidden="true"></span></h3>'+agendaCard()+'</div>'+""")

AGENDA = r'''
/* ---------- versão de teste: botão "Cliente teste" e o app dos alunos simulado ---------- */
const DEMO_K = 'simp-teste-app', SITE_TESTE = /^https?:$/.test(location.protocol) && /\/teste\/?/.test(location.pathname);
const DEMO = {
  get(){ try { const v = JSON.parse(localStorage.getItem(DEMO_K)||'null'); if(v && typeof v==='object') return v; } catch(e){ /* sem armazenamento */ } return { prog:{}, sto:[], sug:[] }; },
  put(v){ try { localStorage.setItem(DEMO_K, JSON.stringify(v)); } catch(e){ /* sem armazenamento */ } },
  listProgresso(){ const P0 = this.get().prog||{}; return Object.keys(P0).map(aid => ({ uid:P0[aid].uid, aid, em:P0[aid].em, d:P0[aid].d })); },
  list(k){ return (this.get()[k]||[]).slice().sort((x,y) => (y.em||0)-(x.em||0)); },
  async setLida(id, v){ const D = this.get(); (D.sug||[]).forEach(x => { if(x.id===id) x.lida = v; }); this.put(D); },
  async dropDoc(k, id){ const D = this.get(); D[k] = (D[k]||[]).filter(x => x.id!==id); this.put(D); },
  wipe(){ try { localStorage.removeItem(DEMO_K); } catch(e){ /* nada */ } }
};
const temDemo = () => Object.keys(C.alunos).some(id => C.alunos[id] && C.alunos[id].demo);
function demoCard(){
  if(!Store.loaded) return '';
  const tem = temDemo();
  return '<section class="card demo-c"><div class="card-h"><span class="label">'+ic('flag')+'Versão de teste</span>'+(tem ? '<span class="pill acc">Clientes de teste no painel</span>' : '')+'</div>'+
    '<p class="muted">'+(tem ? 'Estes alunos são fictícios, com treinos, agenda, pagamentos e uso do app simulados. Apague quando quiser começar do zero.' : 'O painel começa vazio. Toque em Cliente teste para criar alguns alunos fictícios e ver tudo funcionando: agenda, treinos, evolução e números.')+'</p>'+
    '<div class="btn-row">'+(tem ? armBtn('demoWipe','x','Apagar dados de teste','Toque de novo para apagar','','trash') : '<button type="button" class="btn primary" data-a="demoSeed">'+ic('users')+'Cliente teste</button>')+(SITE_TESTE ? '<button type="button" class="btn ghost" data-a="demoSair">'+ic('chevL')+'Sair do teste</button>' : '')+'</div></section>';
}
const DOW_K = ['dom','seg','ter','qua','qui','sex','sab'];
const ATEND = { presencial:'Presencial', online:'Online' };
const atendDe = a => P(a).atendimento==='online' ? 'online' : 'presencial';
const addMin = (h, m) => { const [H, M] = String(h).split(':').map(Number), t = (H*60+M+(+m||0)) % 1440; return String(Math.floor(t/60)).padStart(2,'0')+':'+String(t%60).padStart(2,'0'); };
function agendaDia(k){
  const dw = DOW_K[parse(k).getDay()], L = [];
  alunos().filter(a => (P(a).status||'ativo')==='ativo').forEach(a => (P(a).horarios||[]).forEach(x => { if(x && x.d===dw && /^\d\d:\d\d$/.test(x.h||'')) L.push({ a, h:x.h, dur:+x.dur||60, modo:atendDe(a) }); }));
  return L.sort((x,y) => x.h.localeCompare(y.h) || nomeDe(x.a).localeCompare(nomeDe(y.a),'pt-BR'));
}
function agendaCard(){
  const t0 = today(), sel = ui.agD && ui.agD>=t0 && diffDays(ui.agD, t0)<7 ? ui.agD : t0, dias = Array.from({length:7}, (_,i) => addDays(t0, i));
  const L = agendaDia(sel), np = L.filter(x => x.modo==='presencial').length, no = L.length-np;
  const now = new Date(), hm = String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0');
  const strip = '<div class="ag-days" role="group" aria-label="Dia da agenda">'+dias.map((k,i) => { const n = agendaDia(k).length;
    return '<button type="button" class="ag-d'+(k===sel?' on':'')+'" data-a="agD" data-v="'+k+'" aria-pressed="'+(k===sel)+'"><span>'+(i===0?'Hoje':i===1?'Amanhã':DSEM[parse(k).getDay()])+'</span><b>'+(+k.slice(8,10))+'</b><i>'+(n ? n+(n===1?' aluno':' alunos') : 'livre')+'</i></button>'; }).join('')+'</div>';
  const rows = L.map(x => { const fim = addMin(x.h, x.dur), hoje = sel===t0, passou = hoje && fim<=hm, agora = hoje && x.h<=hm && fim>hm, p = P(x.a);
    const onde = x.modo==='online' ? 'Chamada de vídeo' : (LOCAL[p.local]||'Presencial');
    return '<button type="button" class="row ag-r'+(passou?' done':'')+'" data-a="open" data-id="'+esc(x.a._id)+'" data-tab="perfil"><span class="ag-h"><b>'+x.h+'</b><span>'+fim+'</span></span><span class="main-t"><b>'+esc(nomeDe(x.a))+'</b><span>'+(agora ? '<b class="live">● agora</b> · ' : passou ? 'concluído · ' : '')+esc(onde)+' · '+esc(ED[p.objetivo]||'')+'</span></span><span class="end"><span class="pill '+(x.modo==='online'?'ag-on':'ag-pr')+'">'+ATEND[x.modo]+'</span>'+ic('chev','chev')+'</span></button>'; }).join('');
  const sem = alunos().filter(a => (P(a).status||'ativo')==='ativo' && !(P(a).horarios||[]).length);
  return '<section class="card ag-card"><div class="card-h"><span class="label">'+ic('cal')+(sel===t0 ? 'Hoje' : new Date(sel+'T12:00:00').toLocaleDateString('pt-BR',{weekday:'long',day:'numeric',month:'long'}))+'</span>'+
    (L.length ? '<span class="ag-sum">'+(np ? '<span class="pill ag-pr">'+np+' presencia'+(np===1?'l':'is')+'</span>' : '')+(no ? '<span class="pill ag-on">'+no+' online</span>' : '')+'</span>' : '')+'</div>'+strip+
    (L.length ? '<div class="list ag-list">'+rows+'</div>' : '<div class="list"><div class="row"><span class="ico ok">'+ic('check')+'</span><span class="main-t"><b>Dia livre</b><span>Nenhum aluno com horário neste dia.</span></span></div></div>')+
    '<p class="foot">'+(sem.length ? 'Sem horário fixo: '+esc(sem.slice(0,8).map(a => curto(a)).join(', '))+(sem.length>8 ? ' e mais '+(sem.length-8) : '')+'. ' : '')+'Os horários ficam em Alunos › Perfil › Atendimento.</p></section>';
}
function tAtend(a){
  const p = P(a), id = a._id, H = p.horarios||[];
  return '<section class="card"><span class="label">'+ic('cal')+'Atendimento</span><div class="form">'+
    segB({b:AB(id,'perfil.atendimento'),label:'Como você atende este aluno',val:atendDe(a),opts:Object.entries(ATEND),cls:'wide'})+'</div>'+
    '<span class="flabel" style="margin-top:6px">Horários na agenda</span>'+
    (H.length ? '<div class="ag-hs">'+H.map((x,i) => '<div class="ag-hr">'+
      sel({b:AB(id,'perfil.horarios.'+i+'.d'),label:'Dia',val:x.d,opts:DIAS,cls:i?'ag-nl':''})+
      inp({b:AB(id,'perfil.horarios.'+i+'.h'),label:'Hora',val:x.h,type:'time',cls:i?'ag-nl':''})+
      sel({b:AB(id,'perfil.horarios.'+i+'.dur'),label:'Duração',val:String(x.dur||60),t:'i',cls:i?'ag-nl':'',opts:[['30','30 min'],['45','45 min'],['60','1 h'],['90','1 h 30']]})+
      '<button type="button" class="ibtn" data-a="agDel" data-k="'+i+'" aria-label="Tirar este horário" title="Tirar este horário">'+ic('trash')+'</button></div>').join('')+'</div>' : '<p class="muted" style="margin:4px 0 8px">Sem horário fixo. '+(atendDe(a)==='online' ? 'Para online, coloque se tiver chamada ou check-in marcado.' : 'Coloque os dias e horários das aulas.')+'</p>')+
    '<div class="btn-row"><button type="button" class="btn small" data-a="agAdd">'+ic('plus')+'Adicionar horário</button></div><p class="foot">Aparece na Agenda do Painel.</p></section>';
}
/* clientes fictícios: alunos com treino, agenda, pagamentos, avaliações e o uso do app dos últimos dias */
function demoSeed(){
  if(!Object.keys(C.exercicios).length) loadStarterLib();
  const t = today(), dw = n => DOW_K[parse(addDays(t, n)).getDay()], now = Date.now();
  const lib = Object.keys(C.exercicios).map(id => Object.assign({_id:id}, C.exercicios[id]));
  const doG = (g, n, off) => lib.filter(e => e.grupo===g).slice(off||0, (off||0)+n);
  const ex = (e, s, r, aq) => Object.assign(exFromLib(e._id), { s, r, d:aq ? 90 : 60, aq:!!aq, aqW: aq ? [{p:50,r:10},{p:75,r:5}] : [] });
  const T = {
    A:{ nome:'Inferiores', foco:'Quadríceps e glúteo', ex:[].concat(doG('Quadríceps',2).map((e,i) => ex(e,3,'8–10',i===0)), doG('Glúteo',1).map(e => ex(e,3,'10–12')), doG('Posterior',1).map(e => ex(e,3,'10–12')), doG('Panturrilha',1).map(e => ex(e,4,'12–15'))) },
    B:{ nome:'Superiores', foco:'Peito, costas e ombro', ex:[].concat(doG('Peito',2).map((e,i) => ex(e,3,'8–10',i===0)), doG('Costas',2).map(e => ex(e,3,'10–12')), doG('Ombro',1).map(e => ex(e,3,'12–15'))) },
    C:{ nome:'Glúteo e braços', foco:'Glúteo, bíceps e tríceps', ex:[].concat(doG('Glúteo',2,1).map((e,i) => ex(e,3,'10–12',i===0)), doG('Bíceps',1).map(e => ex(e,3,'10–12')), doG('Tríceps',1).map(e => ex(e,3,'10–12')), doG('Abdômen',1).map(e => ex(e,3,'15'))) }
  };
  const KG = { 'Quadríceps':40, 'Glúteo':30, 'Posterior':25, 'Panturrilha':30, 'Peito':16, 'Costas':35, 'Ombro':6, 'Bíceps':10, 'Tríceps':15, 'Abdômen':0 };
  const pessoas = [
    { n:'Ana Souza', ap:'Ana', sx:'F', ob:'hipertrofia', at:'presencial', hs:[[0,'07:00'],[2,'07:00'],[4,'07:00']], pl:'presencial', v:650, pago:20, nasc:'1994-03-12', alt:165, peso:62.5, nv:'intermediario', ini:-120, plIni:-28, app:{ dias:[0,2,5], f:1.15, nota:'Treino top hoje! Bati recorde no leg.' },
      av:[[-90,64.8,27.5],[-30,62.5,24.8]], sto:[['crono','share',2],['cupom','baixar',5],['crono','share',9]], sug:'Podia ter um lembrete para tomar a creatina junto com o da água.' },
    { n:'Bruno Lima', ap:'Bruno', sx:'M', ob:'emagrecer', at:'presencial', hs:[[0,'18:30'],[2,'18:30']], pl:'presencial', v:600, pago:-6, nasc:'1988-07-30', alt:178, peso:96, nv:'iniciante', ini:-60, plIni:-21, app:{ dias:[0,5], f:.9, sono:4 },
      av:[[-60,99.4,31]], sto:[['cbil','share',3]] },
    { n:'Carla Mendes', ap:'Carla', sx:'F', ob:'corrida', at:'online', hs:[[0,'20:00',30]], pl:'consultoria', v:250, pago:3, nasc:'1991-11-02', alt:160, peso:57, nv:'intermediario', ini:-75, plIni:-35, app:{ dias:[6,1,3], f:.8, dor:true, cardio:true },
      les:{ regiao:'Joelho direito', descricao:'Condromalácia patelar', evitar:'Agachamento profundo e saltos' }, av:[[-70,58.6,25],[-10,57,23.6]], sto:[['cbon','baixar',1],['cpul','share',6]], sug:'Queria ver o pace médio das minhas corridas no cardio.' },
    { n:'Diego Rocha', ap:'Diego', sx:'M', ob:'hipertrofia', at:'online', hs:[], pl:'online', v:300, pago:12, nasc:'1999-01-20', alt:182, peso:78, nv:'avancado', ini:-150, plIni:-53, app:{ dias:[0,1,3,4], f:1.4 },
      av:[[-140,74,14],[-50,78,15.5]], sto:[['foto','share',4]] },
    { n:'Fernanda Alves', ap:'Fê', sx:'F', ob:'hipertrofia', at:'presencial', hs:[[0,'09:00'],[1,'09:00'],[3,'10:00',90]], pl:'presencial', v:650, pago:15, nasc:addDays(t,3).replace(/^\d{4}/,'1996'), alt:168, peso:64, nv:'intermediario', ini:-200, plIni:-14, app:{ dias:[5,0,1,3], f:1.05, agora:true },
      av:[[-45,65.2,26.1]], sto:[['foto','share',0],['crono','baixar',7]] },
    { n:'Gabriel Costa', ap:'Gabriel', sx:'M', ob:'emagrecer', at:'presencial', hs:[[1,'06:00'],[3,'06:00']], pl:'presencial', v:600, pago:25, nasc:'2001-05-09', alt:175, peso:88, nv:'iniciante', ini:-5, plIni:-5, app:null, av:[] }
  ];
  const D = DEMO.get(); D.prog = D.prog||{}; D.sto = D.sto||[]; D.sug = D.sug||[];
  pessoas.forEach((x, k) => {
    const id = 'a-demo-'+(k+1), u = 'demo-u'+(k+1), base = newAluno({ nome:x.n, apelido:x.ap, whatsapp:'(11) 9'+(8000+k*111)+'-'+(1000+k*37), email:x.n.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/\s+/g,'.')+'@exemplo.com', sexo:x.sx, objetivo:x.ob, origem:k%2 ? 'instagram' : 'particular', plano:x.pl });
    Object.assign(base.perfil, { nascimento:x.nasc, altura:x.alt, peso:x.peso, nivel:x.nv, inicio:addDays(t, x.ini), atendimento:x.at, horarios:x.hs.map(h => ({ d:dw(h[0]), h:h[1], dur:h[2]||60 })), local:x.at==='online' ? 'casa' : 'academia', tempoSessao:60 });
    const Ls = x.ob==='corrida' ? ['A','B'] : ['A','B','C'], tr = {}; Ls.forEach(L => { tr[L] = clone(T[L]); tr[L].ex.forEach(e => { e.id = newExId(e.exId); }); });
    Object.assign(base.plano, { nome:'Protocolo '+(x.plIni<-40 ? 2 : 1), objetivoCiclo:ED[x.ob], inicio:addDays(t, x.plIni), semanas:8, frequencia:Ls.length, treinos:tr }); base.metas.semanaTreinos = Ls.length;
    base.financeiro.valor = x.v; base.financeiro.vencimentoDia = Math.min(28, +addDays(t, x.pago).slice(8,10)); base.financeiro.pagoAte = addDays(t, x.pago);
    base.financeiro.pagamentos = [1,2].map(m => ({ id:uid('p'), data:addDays(t, x.pago-30*m-2), valor:x.v, ref:addDays(t, x.pago-30*m).slice(0,7), forma:m===1 ? 'pix' : 'cartao' }));
    base.avaliacoes = x.av.map(v => ({ id:uid('av'), data:addDays(t, v[0]), peso:v[1], gordura:v[2], medidas:{ cintura:Math.round(x.alt*.45 - (v[0]>-40 ? 2 : 0)), quadril:Math.round(x.alt*.6) }, obs:'' }));
    if(x.les) base.saude.lesoes = [Object.assign({ id:uid('l') }, x.les)];
    if(x.ob==='emagrecer' || x.ob==='corrida'){ base.metas.cardioGoal = 150; base.metas.cardio.dias = ['seg','qua','sex']; }
    base.uid = u; base.demo = true;
    C.alunos[id] = base; Store.save('alunos', id, 0);
    // o que a pessoa fez no app nas últimas 5 semanas
    if(x.app){ const h = [], c = [], w = {}, ck = {}; let n = 0;
      for(let i=-35;i<=0;i++){ const dia = addDays(t, i), dwi = (parse(dia).getDay()+6)%7, fixo = x.app.dias.map(o => (parse(addDays(t,o)).getDay()+6)%7);
        w[dia] = Math.round((base.metas.waterGoal*.72 + ((((i*37+k*11) % 9)+9) % 9)*base.metas.waterGoal*.05) / 50) * 50; const m3 = (((i+k)%3)+3)%3; ck[dia] = { sono:(x.app.sono && i>-3) ? 4 : 6+m3, energia:3+(m3%2), dor:(x.app.dor && i>-4) ? 3 : (i%4===0 ? 1 : 0) };
        if(fixo.includes(dwi) && !(x.app.agora && i===0) && (i>-3 || (((i*7+k)%9)+9)%9!==0)){ const L = Ls[n % Ls.length]; n++; const sem = Math.floor((i+35)/7);
          const exs = tr[L].ex.map((e, j) => { const kg0 = (/prancha|abdominal|flex[aã]o|dead bug|superman/i.test(e.nome) ? 0 : KG[e.alvo]!=null ? KG[e.alvo] : 10) * x.app.f, kg = kg0 ? Math.round((kg0 + sem*(kg0>20 ? 2 : 1))*2)/2 : 0, ns = +e.s; const sets = Array.from({length:ns}, (_,q) => [kg, Math.max(6, 12 - q - ((i+j)%2))]);
            const o = { id:e.id, n:e.nome, s:sets, q:[], c:'' }; if(e.aq){ o.w = 2; o.wt = 2; }
            if(x.app.dor && i>-8 && j===0){ o.q = ['Senti dor']; o.c = 'Incomodou o joelho na descida.'; }
            if(!x.app.dor && i===x.app.dias[0]-7 && j===1) o.q = ['Pesado'];
            return o; });
          const sets = exs.reduce((s2,e) => s2 + e.s.length, 0), vol = exs.reduce((s2,e) => s2 + e.s.reduce((a2,q) => a2 + q[0]*q[1], 0), 0);
          h.push({ d:dia, t:L, nm:tr[L].nome, min:45+((i+k)%4)*5, sets, vol:Math.round(vol), gym:x.at==='online' ? 'Academia do prédio' : 'Academia', note:(x.app.nota && i>-3) ? x.app.nota : '', ex:exs }); }
        if((x.app.cardio || x.ob==='emagrecer') && (dwi===1 || dwi===5)) c.push({ d:dia, l:x.app.cardio ? 'Rua' : 'Esteira', min:x.app.cardio ? 35 : 25, km:x.app.cardio ? 5.2 : 0 }); }
      const d = { v:1, h, c, w, wg:base.metas.waterGoal, k:ck };
      if(x.app.agora) d.ses = { d:t, t:Ls[n % Ls.length], ini:now - 25*60e3 };
      D.prog[id] = { uid:u, em:now - (x.app.agora ? 2 : (k+1)*47)*60e3, d }; }
    (x.sto||[]).forEach((s2, j) => D.sto.push({ id:'st'+k+'-'+j, uid:u, nome:x.n, tipo:'personal', modelo:s2[0], cor:['vermelho','preto','lilas'][j%3], acao:s2[1], tema:/^c/.test(s2[0]) ? 'cardio' : 'treino', em:now - s2[2]*864e5 - 3600e3*(j+1) }));
    if(x.sug) D.sug.push({ id:'sg'+k, uid:u, nome:x.n, tipo:'personal', texto:x.sug, em:now - (k+2)*864e5 });
    if(x.app){ D.av = D.av||[]; const nt = [[5,4,4,5,4],[4,3,3,4,5],[5,5,3,4,4],[5,4,5,5,5],[4,4,4,3,4]][k%5]; D.av.push({ id:'av'+k, uid:u, nome:x.n, tipo:'personal', notas:{ treino:nt[0], cardio:nt[1], agua:nt[2], facil:nt[3], visual:nt[4] }, texto:['Amei o cronômetro de descanso!','Queria mais opções de cardio em casa.','','O lembrete de água me salvou.','Podia ter tema escuro mais escuro.'][k%5], em:now - (k+1)*2*864e5 }); }
  });
  DEMO.put(D); PG.by = null; PG.at = 0; ST.sug = null; ST.av = null; ST.at = 0;
  if(!cfg().nomePersonal){ if(!C.config.geral) C.config.geral = clone(DEF_CFG); C.config.geral.nomePersonal = 'Personal Teste'; Store.save('config','geral',0); }
  toast(pessoas.length+' clientes de teste criados');
}
function demoWipe(){
  Object.keys(C.alunos).forEach(id => { if(C.alunos[id] && C.alunos[id].demo) Store.remove('alunos', id); });
  if(ui.aluno && !C.alunos[ui.aluno]) ui.aluno = null;
  DEMO.wipe(); PG.by = null; PG.at = 0; ST.sug = null; ST.sto = null; ST.at = 0; toast('Dados de teste apagados');
}
'''
rep("function vPainel(){", AGENDA + "function vPainel(){")
# ações
rep("    case 'estLida':", """    case 'agD': ui.agD = d.v; break;
    case 'agAdd': { if(!A) break; const H = (A.perfil.horarios||[]).slice(), ult = H[H.length-1]; H.push({ d:ult ? DOW_K[(DOW_K.indexOf(ult.d)+2)%7] : DOW_K[(parse(today()).getDay())], h:ult ? ult.h : '07:00', dur:ult ? ult.dur||60 : 60 }); A.perfil.horarios = H; Store.save('alunos',ui.aluno,0); break; }
    case 'agDel': { if(!A) break; A.perfil.horarios = (A.perfil.horarios||[]).filter((_,i) => i!==+d.k); Store.save('alunos',ui.aluno,0); break; }
    case 'demoSeed': demoSeed(); ui.view = 'painel'; break;
    case 'demoWipe': demoWipe(); break;
    case 'demoSair': try { localStorage.removeItem('simp-teste-ok'); sessionStorage.removeItem('simp-teste-ok'); } catch(e){} location.href = '../'; return false;
    case 'estLida':""")
# o botão "apagar" de duas etapas (armBtn) precisa do primeiro toque
rep("'applyToAluno','wipeEx']);", "'applyToAluno','wipeEx','demoWipe']);")

# planos de cobrança genéricos (sem a assinatura do Instagram, que é do Simplificando)
rep("const PLANOS = { instagram:'Assinatura Instagram', presencial:'Particular presencial', online:'Particular online', consultoria:'Consultoria' };",
    "const PLANOS = { presencial:'Presencial', online:'Online', consultoria:'Consultoria' };")
# ---------- Alunos: só alunos do personal, presencial ou online ----------
rep("perfil:{ nome:d.nome.trim(),", "perfil:{ atendimento:d.atendimento==='online' ? 'online' : 'presencial', horarios:[], nome:d.nome.trim(),")
rep("""      segB({d:'novo.origem',label:'Origem',val:d.origem||'particular',opts:Object.entries(ORIGEM)})+""",
    """      segB({d:'novo.atendimento',label:'Atendimento',val:d.atendimento||'presencial',opts:Object.entries(ATEND)})+
      segB({d:'novo.origem',label:'Origem',val:d.origem||'particular',opts:Object.entries(ORIGEM)})+""")
rep("""<span>'+esc(ED[p.objetivo]||'')+' · '+esc(ORIGEM[p.origem]||'')+(p.status""", """<span>'+ATEND[atendDe(a)]+' · '+esc(ED[p.objetivo]||'')+(p.status""")
rep("""inp({b:b('email'),label:'E-mail (para redefinir a senha)',val:p.email,type:'email',im:'email',v:'emailreq',ph:'nome@email.com'})+'</div></section>'+""",
    """inp({b:b('email'),label:'E-mail (para redefinir a senha)',val:p.email,type:'email',im:'email',v:'emailreq',ph:'nome@email.com'})+'</div></section>'+tAtend(a)+""")
rep("""<p class="eyebrow">'+esc(ED[p.objetivo]||'Objetivo')+' · '+esc(ORIGEM[p.origem]||'')+'</p>""", """<p class="eyebrow">'+esc(ED[p.objetivo]||'Objetivo')+' · '+ATEND[atendDe(a)]+'</p>""")

# ---------- Biblioteca: sem ranking/tier list, só os exercícios com animação ----------
rep("const rankTag = (o, big) => {", "const rankTag = (o, big) => ''; const rankTag0 = (o, big) => {")
rep("const rankBox = o => {", "const rankBox = o => ''; const rankBox0 = o => {")
rep("const rankLegenda = () =>", "const rankLegenda = () => ''; const rankLegenda0 = () =>")
rep("const rankOrdem = () =>", "const rankOrdem = () => ''; const rankOrdem0 = () =>")
rep("usando o ranking (tier list) para escolher os melhores exercícios da sua biblioteca", "escolhendo os exercícios da sua biblioteca")
rep("if(Store.loaded && !libMigrou){ libMigrou = true; libMigra(); }",
    "if(Store.loaded && !libMigrou){ libMigrou = true; if(!Object.keys(C.exercicios).length && loadStarterLib()) render(); }")
rep("LIB_PRONTA.forEach(([id,nome,grupo,equipamento,cues,erro,anim]) => { if(C.exercicios[id]) return;",
    "LIB_PRONTA.forEach(([id,nome,grupo,equipamento,cues,erro,anim]) => { if(C.exercicios[id] || !temAnim(nome, grupo, anim)) return;")
rep("function loadStarterLib(){", "const temAnim = (nome, grupo, anim) => { try { return !!XA.pick(xaDesc({ nome, grupo, anim:anim||'' })); } catch(e){ return true; } };\nfunction loadStarterLib(){")
rep("const nf = libFaltam() + libVelhos().length,", "const nf = 0,")

# ---------- Estatísticas: só os alunos deste personal ----------
rep("let h = header('Estatísticas', 'Para decidir as próximas mudanças', '');", "let h = header('Estatísticas', 'Como os seus alunos usam o app', '');")
rep("tipoTxt(x.tipo)+' · '+plural(x.n,'vez','vezes')", "plural(x.n,'vez','vezes')")
rep("esc(x.nome||'Sem nome')+' · '+tipoTxt(x.tipo)+' · '", "esc(x.nome||'Sem nome')+' · '")
rep(" Vale para alunos com e sem personal.</p></section>';", " Conta só os seus alunos.</p></section>';")
rep("Fica no fim do Perfil, no app, para quem treina com e sem personal.", "Fica no fim do Perfil, no app dos seus alunos.")
i = s.index("  // próximas datas especiais"); j = s.index("  // de onde vieram (cadastro de quem treina sem personal)", i)
s = s[:i] + s[j:]

# o aviso de "modelo pouco usado" é para quem cria os designs, não para o personal
rep("(menos && menos[1]<.2 ? '<p class=\"callout\">'", "(false ? '<p class=\"callout\">'")
# "De onde vieram" é dos cadastros do Simplificando (sem personal): não entra no painel de teste
i = s.index("  // de onde vieram (cadastro de quem treina sem personal)"); j = s.index("  // avaliação do app (estrelas por área, a cada 15 dias)", i)
s = s[:i] + s[j:]
# ---------- estilos da agenda ----------
CSS = '''
/* versão de teste: agenda e atendimento */
.demo-c .btn-row{margin-top:2px}
.ag-days{display:flex;gap:8px;overflow-x:auto;padding:2px 2px 10px;margin:4px -2px 6px;scrollbar-width:none}.ag-days::-webkit-scrollbar{display:none}
.ag-d{flex:0 0 auto;min-width:66px;display:flex;flex-direction:column;align-items:center;gap:1px;padding:9px 8px;border-radius:16px;background:var(--ctl);color:var(--ctl-fg);border:0;font:inherit;cursor:pointer}
.ag-d span{font-size:12px;font-weight:600;text-transform:capitalize;color:var(--muted)}.ag-d b{font-size:20px;line-height:1.15}.ag-d i{font-style:normal;font-size:11px;color:var(--muted)}
.ag-d.on{background:var(--fg);color:var(--bg)}.ag-d.on span,.ag-d.on i{color:inherit;opacity:.8}
.ag-sum{display:flex;gap:6px;flex-wrap:wrap}
.pill.ag-pr{background:color-mix(in srgb,var(--brand) 16%,transparent);color:color-mix(in srgb,var(--brand) 78%,var(--fg))}
.pill.ag-on{background:color-mix(in srgb,var(--water) 18%,transparent);color:color-mix(in srgb,var(--water) 70%,var(--fg))}
.ag-r .ag-h{flex:0 0 52px;display:flex;flex-direction:column;align-items:flex-start;line-height:1.1}.ag-r .ag-h b{font-size:16px;font-variant-numeric:tabular-nums}.ag-r .ag-h span{font-size:12px;color:var(--muted);font-variant-numeric:tabular-nums}
.ag-r.done{opacity:.55}
.ag-hs{display:flex;flex-direction:column;gap:8px;margin:6px 0 10px}
.ag-hr{display:grid;grid-template-columns:1.05fr 1fr 1fr 36px;gap:6px;align-items:end}.ag-hr .fld{margin:0;min-width:0}.ag-hr .inp{padding-left:10px!important;padding-right:6px!important;min-width:0;text-align:left}.ag-hr input.inp{padding-right:4px!important}.ag-hr .inp-wrap{padding-left:2px!important;padding-right:2px!important}.ag-hr .ibtn{margin-bottom:4px;width:36px}.ag-hr input[type=time]::-webkit-calendar-picker-indicator{display:none}.ag-hr input[type=time]{font-variant-numeric:tabular-nums}
.ag-nl>label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
.ag-r .main-t b{white-space:normal}
'''
k = s.index('</style>'); s = s[:k] + CSS + s[k:]

os.makedirs(R+'produto', exist_ok=True)
open(R+'produto/painel-personal-teste.html', 'w').write(s)
print('ok produto/painel-personal-teste.html', len(s)//1024, 'KB')
# no site: /personal/teste/ (só abre depois do login de teste na tela do painel)
web = s.replace('<meta name="theme-color" content="#0F1712">', '''<meta name="theme-color" content="#0F1712">
<meta name="robots" content="noindex">
<link rel="icon" type="image/png" sizes="192x192" href="../icon-p-tab-192.png">
<link rel="apple-touch-icon" href="../icon-p-180.png">
<script>try { if(!localStorage.getItem('simp-teste-ok') && !sessionStorage.getItem('simp-teste-ok')) location.replace('../'); } catch(e){ location.replace('../'); }</script>''', 1)
os.makedirs(R+'site/personal/teste', exist_ok=True)
open(R+'site/personal/teste/index.html', 'w').write(web)
print('ok site/personal/teste/index.html')
