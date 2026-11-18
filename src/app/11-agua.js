/* ---------- Água ---------- */
function waterTimeline(){
  const t0 = today(), L = (S.wlog[t0]||[]).slice().sort((a,b) => a.m-b.m), g = S.waterGoal, total = S.water[t0]||0;
  const W = 320, H = 178, Lm = 34, R = 12, T = 16, B = 24, x0 = 360, x1 = 1380;
  const ymax = Math.max(g*1.15, total*1.05);
  const X = m => Lm+(Math.min(x1,Math.max(x0,m))-x0)/(x1-x0)*(W-Lm-R), Y = v => T+(1-v/ymax)*(H-T-B);
  const nm = Math.min(x1,Math.max(x0,nowMin()));
  let cum = Math.max(0, total-L.reduce((a,x) => a+x.ml,0));
  let d = `M${X(x0).toFixed(1)},${Y(cum).toFixed(1)}`; const pts = [];
  L.forEach(e => { d += ` H${X(e.m).toFixed(1)}`; cum += e.ml; d += ` V${Y(cum).toFixed(1)}`; pts.push([X(e.m),Y(cum)]); });
  d += ` H${X(nm).toFixed(1)}`;
  const area = d+` V${Y(0).toFixed(1)} H${X(x0).toFixed(1)} Z`;
  let grid = '';
  for(let v=0; v<=ymax; v+=1000) grid += `<line class="grid" x1="${Lm}" x2="${W-R}" y1="${Y(v).toFixed(1)}" y2="${Y(v).toFixed(1)}"/><text x="${Lm-6}" y="${(Y(v)+3.5).toFixed(1)}" text-anchor="end">${v/1000} L</text>`;
  const xl = [6,9,12,15,18,21].map(h => `<text x="${X(h*60).toFixed(1)}" y="${H-6}" text-anchor="middle">${h}h</text>`).join('');
  const gy = Y(g).toFixed(1);
  return `<svg class="tl" viewBox="0 0 ${W} ${H}" role="img" aria-label="Água acumulada ao longo do dia: ${fmtL(total)} de ${fmtL(g)}">${grid}
    <line class="goal" x1="${Lm}" x2="${W-R}" y1="${gy}" y2="${gy}"/><text class="hl" x="${W-R}" y="${(gy-5)}" text-anchor="end">meta ${fmtL(g)}</text>
    <line class="ideal" x1="${X(420).toFixed(1)}" y1="${Y(0).toFixed(1)}" x2="${X(1260).toFixed(1)}" y2="${gy}"/>
    <path class="area" d="${area}"/><path class="ln" d="${d}"/>
    <line class="nowl" x1="${X(nm).toFixed(1)}" x2="${X(nm).toFixed(1)}" y1="${T}" y2="${Y(0).toFixed(1)}"/><text class="hl" x="${X(nm).toFixed(1)}" y="${T-4}" text-anchor="${X(nm)>W-40?'end':X(nm)<Lm+30?'start':'middle'}">agora</text>
    ${pts.map(p => `<circle class="pt" cx="${p[0].toFixed(1)}" cy="${p[1].toFixed(1)}" r="5"/>`).join('')}${xl}</svg>`;
}
function weekWaterBars(){
  const ds = Array.from({length:7},(_,i) => daysAgo(6-i)), g = S.waterGoal, vals = ds.map(k => S.water[k]||0), mx = Math.max(g*1.2, ...vals);
  const y = v => 112-v/mx*96;
  const bars = ds.map((k,i) => { const v = vals[i], x = 14+i*44, by = y(v);
    return `<rect class="b ${v>=g?'hit':''}" x="${x}" y="${by.toFixed(1)}" width="28" height="${(112-by).toFixed(1)}" rx="8"/><text x="${x+14}" y="${(by-6).toFixed(1)}" text-anchor="middle" class="v">${v?(v/1000).toFixed(1).replace('.',','):''}</text><text x="${x+14}" y="132" text-anchor="middle">${WD[(parse(k).getDay()+6)%7]}</text>`; }).join('');
  const gy = y(g).toFixed(1);
  return `<svg class="bars" viewBox="0 0 320 140" role="img" aria-label="Água por dia nos últimos 7 dias"><line class="base" x1="6" x2="314" y1="112" y2="112"/>${bars}<line class="goal" x1="6" x2="314" y1="${gy}" y2="${gy}"/><text class="gl" x="314" y="${gy-5}" text-anchor="end">meta ${fmtL(g)}</text></svg>`;
}
function vAgua(){
  const t0 = today(), g = S.waterGoal, L = (S.wlog[t0]||[]).slice().sort((a,b) => a.m-b.m);
  const hit7 = Array.from({length:7},(_,i) => daysAgo(6-i)).filter(k => (S.water[k]||0)>=g).length;
  const rt = remindTxt();
  return header('Água','Meta de '+fmtL(g)+' por dia')+exampleNote()+waterHero()+
    `<section class="card rm-line${S.remind.on?' on':''}"><span class="rm-ic">${ic('bell')}</span><span class="rm-tx"><b>Lembretes de água</b><span>${S.remind.on ? 'A cada '+cadaTxt(S.remind.every)+', das '+hmTxt(RINI())+' às '+hmTxt(RFIM()) : 'Desligados'}</span></span><button type="button" class="btn small" data-a="avisos-open">${S.remind.on ? 'Ajustar' : 'Ligar'}</button></section>`+
    `<section class="card"><div class="card-h"><span class="label">${ic('timer')}Ao longo do dia</span><span class="pill">${L.length} copo${L.length===1?'':'s'}</span></div>${waterTimeline()}
      <div class="leg"><span><i></i>você</span><span><i class="dash"></i>ritmo ideal até 21h</span></div>
      ${L.length?`<div class="drinks">${L.map(e => `<span class="chip">${hm(e.m)} · ${e.ml} ml</span>`).join('')}</div>`:''}</section>`+
    `<section class="card"><div class="card-h"><span class="label">${ic('chart')}Últimos 7 dias</span><span class="pill${hit7>=5?' ok':''}">${hit7} de 7 na meta</span></div>${weekWaterBars()}</section>`+
    (isPersonal() ? `<section class="card"><div class="sw-row"><div><b>Meta de água: ${fmtL(g)} por dia</b><span>Definida pelo seu personal. Se precisar mudar, fale com ele.</span></div><span class="pill">${ic('lock')}Personal</span></div></section>`
      : `<section class="card"><div class="field"><span class="flabel">Meta de água por dia</span><div class="stepper"><button type="button" class="btn round" data-a="goal" data-d="-250" aria-label="Diminuir meta de água">${ic('minus')}</button><output>${fmtL(g)}</output><button type="button" class="btn round" data-a="goal" data-d="250" aria-label="Aumentar meta de água">${ic('plus')}</button></div></div><p class="foot0">Uma referência comum é 35 ml por kg de peso.</p></section>`);
}

function bottle(ml,g){
  const cap = g*1.2, p = Math.min(1, ml/cap), top = 70, bot = 292, y = bot-p*(bot-top), gy = bot-(g/cap)*(bot-top), hit = ml>=g;
  const body = 'M74 48H126V60C126 72 158 80 158 106V264C158 281 146 292 129 292H71C54 292 42 281 42 264V106C42 80 74 72 74 60Z';
  const wave = 'M-200 0q25 -7 50 0'+'t50 0'.repeat(11)+'V260H-200Z';
  let ticks = '';
  for(let v=1000; v<g; v+=1000){ const yv = bot-(v/cap)*(bot-top); ticks += `<line class="b-tick" x1="42" x2="56" y1="${yv.toFixed(1)}" y2="${yv.toFixed(1)}"/><text x="62" y="${(yv+4).toFixed(1)}">${v/1000} L</text>`; }
  const star = (x,y,r) => `<path d="M${x} ${y-r}Q${x} ${y} ${x+r} ${y}Q${x} ${y} ${x} ${y+r}Q${x} ${y} ${x-r} ${y}Q${x} ${y} ${x} ${y-r}Z"/>`;
  return `<div class="bottle${hit?' full':''}"><svg viewBox="0 0 200 300" role="img" aria-label="Garrafinha com ${fmtL(ml)} de ${fmtL(g)}${hit?', meta batida':''}">
    <defs><clipPath id="bclip"><path d="${body}"/></clipPath></defs>
    <rect class="b-cap" x="80" y="10" width="40" height="30" rx="8"/><line class="b-neck" x1="78" x2="122" y1="44" y2="44"/>
    <path class="b-glass" d="${body}"/>
    <g clip-path="url(#bclip)"><g class="b-lvl" data-lvl="bt" data-y="${y.toFixed(1)}" style="transform:translateY(${y.toFixed(1)}px)"><path class="b-w2" d="${wave}"/><path class="b-w1" d="${wave}"/></g>
    <path class="b-shine" d="M134 118V250"/></g>
    ${ticks}<line class="b-goal" x1="38" x2="162" y1="${gy.toFixed(1)}" y2="${gy.toFixed(1)}"/><text class="gl" x="100" y="${(gy-8).toFixed(1)}" text-anchor="middle">meta</text>
    <path class="b-line" d="${body}"/>
    ${hit?`<g class="b-spark">${star(24,70,9)}${star(176,128,8)}${star(170,40,6)}</g><g class="b-badge"><circle cx="150" cy="30" r="20"/><path d="M141 30l6 6 12-12"/></g>`:''}</svg></div>`;
}
function waterHero(){
  const t0 = today(), ml = S.water[t0]||0, g = S.waterGoal, hit = ml>=g, wp = waterPace(ml), L = S.wlog[t0]||[], last = L[L.length-1], pct = Math.round(ml/g*100), rt = remindTxt();
  const status = hit ? 'Meta batida. Garrafinha cheia.' : 'Faltam '+fmtN(g-ml)+' ml. '+(wp.ok ? 'Você está no ritmo.' : 'Até agora o ideal era '+fmtL(wp.exp)+'.');
  return `<section class="card"><div class="card-h"><span class="label">${ic('drop')}Sua garrafinha</span>${hit?`<span class="pill ok">${ic('check')}Meta batida</span>`:`<span class="pill">${wp.ok?'No ritmo':'Atrás do ritmo'}</span>`}</div>
    <div class="wh-body">${bottle(ml,g)}<div class="wh-txt">${tw('w-pct',pct,'pct',pct+'%').replace('<b ','<b class="wh-pct" ')}<span>da meta de hoje</span>${tw('w-ml',ml,'ml',fmtN(ml)+' ml').replace('<b ','<b class="wh-ml" ')}<span>de ${fmtN(g)} ml</span></div></div>
    <div class="wh-btns">${[200,300,500].map(v => `<button type="button" class="btn primary" data-a="water" data-ml="${v}" aria-label="Adicionar ${v} ml">+${v} ml</button>`).join('')}<button type="button" class="btn minus" data-a="water-minus" aria-label="Tirar 250 ml"${ml?'':' disabled'}>−250 ml</button></div>
    ${last?`<button type="button" class="btn ghost small self-start" data-a="undo-water">${ic('undo')}${last.ml<0?'Desfazer −'+(-last.ml)+' ml':'Desfazer '+last.ml+' ml'}</button>`:''}</section>`;
}
const ANIM_KIND = { Esteira:'tread', Bicicleta:'bike', Rua:'road', Escada:'stair', 'Elíptico':'ell', Outro:'rope' };
const ANIM_CAP = {
  Esteira:['caminhada na esteira','caminhada rápida virando trote','corrida, com fase de voo'],
  Rua:['caminhada na rua','trote leve','corrida'],
  Bicicleta:['sentado, giro leve','sentado, giro firme','em pé no pedal, giro forte'],
  Escada:['passo tranquilo, degrau a degrau','passo firme, joelho mais alto','passo acelerado, tronco à frente'],
  'Elíptico':['movimento leve e amplo','braços e pernas firmes','ritmo forte, passada longa'],
  Outro:['pular corda com os dois pés','pular corda alternando os pés','pular corda com joelho alto']
};
/* parâmetros por aparelho e intensidade: a troca de seleção interpola tudo em 400 ms */
const TAU = Math.PI*2, D2R = Math.PI/180;
const f1 = v => String(Math.round(v*10)/10);
const lerpP = (a,b,t) => [a[0]+(b[0]-a[0])*t, a[1]+(b[1]-a[1])*t];
const at = (o,a,L) => [o[0]+Math.sin(a)*L, o[1]+Math.cos(a)*L];
const rot = (v,p) => [v[0]*Math.cos(p)-v[1]*Math.sin(p), v[0]*Math.sin(p)+v[1]*Math.cos(p)];
const addv = (a,b) => [a[0]+b[0], a[1]+b[1]];
const nrm = v => { const L = Math.hypot(v[0],v[1]) || 1; return [v[0]/L, v[1]/L]; };
const bump = (u,c,w) => { let d = u-c; d -= Math.round(d); return Math.exp(-d*d/(2*w*w)); };
const sstep = x => x*x*(3-2*x);
const frac = x => x-Math.floor(x);
function cap(a,b,wa,wb){
  const dx = b[0]-a[0], dy = b[1]-a[1], L = Math.hypot(dx,dy) || .001, nx = -dy/L, ny = dx/L;
  return `M${f1(a[0]+nx*wa)} ${f1(a[1]+ny*wa)}L${f1(b[0]+nx*wb)} ${f1(b[1]+ny*wb)}A${f1(wb)} ${f1(wb)} 0 0 0 ${f1(b[0]-nx*wb)} ${f1(b[1]-ny*wb)}L${f1(a[0]-nx*wa)} ${f1(a[1]-ny*wa)}A${f1(wa)} ${f1(wa)} 0 0 0 ${f1(a[0]+nx*wa)} ${f1(a[1]+ny*wa)}Z`;
}
const circ = (c,r) => `M${f1(c[0]-r)} ${f1(c[1])}a${f1(r)} ${f1(r)} 0 1 0 ${f1(2*r)} 0a${f1(r)} ${f1(r)} 0 1 0 ${f1(-2*r)} 0Z`;
function ik(a,b,l1,l2,fwd){
  const dx = b[0]-a[0], dy = b[1]-a[1], d = Math.max(1, Math.min(Math.hypot(dx,dy), l1+l2-.01)), ang = Math.atan2(dy,dx);
  const c = Math.acos(Math.max(-1, Math.min(1, (l1*l1+d*d-l2*l2)/(2*l1*d))));
  const s = ang + (fwd ? -c : c);
  return [a[0]+Math.cos(s)*l1, a[1]+Math.sin(s)*l1];
}
/* pé: tornozelo, calcanhar e ponta; p = inclinação (positivo = ponta para baixo) */
function footS(ankle,p){
  const heel = addv(ankle, rot([-3.6,4.2],p)), toe = addv(ankle, rot([10,4.2],p));
  return { heel, toe, low:Math.max(heel[1],toe[1])+3.3, shoe:cap(heel,toe,3.4,2.7), sole:cap(addv(heel,rot([.4,2.7],p)), addv(toe,rot([-.6,2.3],p)),1.25,1.05) };
}
function body(hip,lean,hl){
  const sh = at(hip,Math.PI-lean,34), neckTop = at(sh,Math.PI-hl,6), head = at(neckTop,Math.PI-hl,7.4);
  return { hip, sh, neckTop, head, waist:lerpP(hip,sh,.32), chest:lerpP(hip,sh,.68), sho:lerpP(hip,sh,.9) };
}
const SCN = {
  stair(ph,P){
    const u = frac(ph), D = 17, H = 13, s = .62, Pt = [180,157.5], dP = [-2*s*D, 2*s*H];
    const foot = uu => {
      if(uu<s){ const q = uu/s; return { pos:[Pt[0]+dP[0]*q, Pt[1]+dP[1]*q], p:0 }; }
      const w = (uu-s)/(1-s), e = sstep(w);
      return { pos:[Pt[0]+dP[0]*(1-e)+3*Math.sin(Math.PI*w), Pt[1]+dP[1]*(1-e)-P.lift*Math.sin(Math.PI*w)], p:-12*Math.sin(Math.PI*w)*D2R };
    };
    const hip = [160+P.lean*.1, 114+1.2*Math.cos(2*TAU*u)], B = body(hip, P.lean*D2R, P.lean*.5*D2R);
    const leg = o => { const ankle = addv(o.pos, rot([-2,-7.5],o.p)); return { ankle, p:o.p, knee:ik(hip,ankle,27,26,true) }; };
    const n = leg(foot(u)), f = leg(foot(frac(u+.5))), gN = [188,111], gF = [184,112];
    const o = frac(2*ph); let tr = '', ns = '', first = null, last = null;
    for(let i=7; i>=-5; i--){
      const x = Pt[0]+(i+o)*-D, y = Pt[1]+(i+o)*H;
      if(!first){ first = [x-8,y]; tr = `M${f1(x-8)} 200L${f1(x-8)} ${f1(y)}`; }
      tr += `L${f1(x+9)} ${f1(y)}L${f1(x+9)} ${f1(y-H)}`; last = [x+9,y-H];
      ns += cap([x-7,y+1.3],[x+8,y+1.3],1.1,1.1);
    }
    tr += `L${f1(last[0]+40)} ${f1(last[1])}L${f1(last[0]+40)} 200Z`;
    return { J:Object.assign(B, { n:Object.assign(n, {wrist:gN, elbow:ik(B.sho,gN,19,17,false)}), f:Object.assign(f, {wrist:gF, elbow:ik(B.sho,gF,19,17,false)}) }), d:{ eqB:tr, grB:ns } };
  },
  ell(ph,P){
    const a = TAU*ph, Rc = [108,166], Pv = [205,117], Ls = 48, Lp = 92;
    const side = q => {
      const K = [Rc[0]+P.r*Math.cos(q), Rc[1]+P.r*Math.sin(q)], dx = Pv[0]-K[0], dy = Pv[1]-K[1], dd = Math.hypot(dx,dy);
      const A = (Lp*Lp-Ls*Ls+dd*dd)/(2*dd), h = Math.sqrt(Math.max(0,Lp*Lp-A*A)), bx = K[0]+A*dx/dd, by = K[1]+A*dy/dd;
      const E1 = [bx-h*dy/dd, by+h*dx/dd], E2 = [bx+h*dy/dd, by-h*dx/dd], E = E1[1]>E2[1] ? E1 : E2;
      const pos = [K[0]+(E[0]-K[0])*.56, K[1]+(E[1]-K[1])*.56-3.5], p = Math.atan2(E[1]-K[1],E[0]-K[0]);
      const upv = nrm([Pv[0]-E[0], Pv[1]-E[1]]), g = rot(upv,-62*D2R), grip = [Pv[0]+g[0]*30, Pv[1]+g[1]*30];
      const ankle = addv(pos, rot([-2,-7.5],p));
      return { K, E, pos, p, grip, ankle, dir:[Math.cos(p),Math.sin(p)] };
    };
    const hip = [156, 106+.5*Math.sin(2*a)], B = body(hip, P.lean*D2R, P.lean*.5*D2R);
    const n = side(a), f = side(a+Math.PI);
    const plate = o => cap([o.pos[0]-o.dir[0]*7, o.pos[1]+3.5-o.dir[1]*7], [o.pos[0]+o.dir[0]*7, o.pos[1]+3.5+o.dir[1]*7], 2.2, 2.2);
    const mk = o => Object.assign({ ankle:o.ankle, p:o.p, knee:ik(hip,o.ankle,27,26,true), wrist:o.grip, elbow:ik(B.sho,o.grip,19,17,false) });
    return { J:Object.assign(B, { n:mk(n), f:mk(f) }), d:{
      dkB:cap(f.K,f.E,2,2)+cap(f.E,Pv,2.2,2.2)+cap(Pv,f.grip,1.9,1.9)+plate(f),
      eqM:cap(n.K,n.E,2.4,2.4)+plate(n), grM:cap(Rc,n.K,1.9,1.9)+circ(Rc,3.2),
      eqF:cap(n.E,Pv,2.5,2.5)+cap(Pv,n.grip,2.1,2.1)+circ(Pv,2.8), grF:cap(addv(n.grip,[-2.5,-1.5]),addv(n.grip,[2.5,1.5]),2.8,2.8) } };
  },
  rope(ph,P){
    const u = frac(ph), sel = Math.floor(ph)%2, w = (u-.22)/.56, air = w>0&&w<1 ? Math.sin(Math.PI*w) : 0, h = w>0&&w<1 ? P.H*4*w*(1-w) : 0;
    const kc = (10+24*bump(u,0,.09))*D2R;
    const leg = lifted => { let th = 5*D2R+kc*.45, kn = kc; if(lifted){ th += P.tl*D2R*air; kn += P.kl*D2R*air; } const knee = at([0,0],th,27); return { knee, ankle:at(knee,th-kn,26), p:18*air*D2R }; };
    const Ln = leg(sel===0), Lf = leg(sel===1), ground = 182;
    const hip = [168, ground-Math.max(footS(Ln.ankle,Ln.p).low, footS(Lf.ankle,Lf.p).low)-h];
    const mv = q => [q[0]+hip[0], q[1]+hip[1]], B = body(hip,3*D2R,2*D2R);
    const al = TAU*u-Math.PI/2;
    const arm = dx => { const ang = 8*D2R, elbow = at(B.sho,ang,19), wr = at(elbow,ang+78*D2R,17); return { elbow, wrist:[wr[0]+dx+2.5*Math.cos(al), wr[1]+2.5*Math.sin(al)] }; };
    const an = arm(0), af = arm(-2.5), C = [hip[0]+2, hip[1]+4], sa = Math.sin(al), T = [C[0]+36*Math.cos(al), C[1]+(sa<0 ? 66 : ground-1.5-C[1])*sa];
    const tg = [-Math.sin(al), Math.cos(al)];
    const nv = [-tg[1], tg[0]];
    const half = (wr,sg) => { const m = lerpP(wr,T,.5), L = Math.hypot(T[0]-wr[0], T[1]-wr[1]), c = [m[0]-tg[0]*L*.22+nv[0]*sg*L*.3, m[1]-tg[1]*L*.22+nv[1]*sg*L*.3]; return `M${f1(wr[0])} ${f1(wr[1])}Q${f1(c[0])} ${f1(c[1])} ${f1(T[0])} ${f1(T[1])}`; };
    const hd = wr => cap(addv(wr,[-1,-3]), addv(wr,[1.5,5]), 2.2, 2.2);
    return { J:Object.assign(B, { n:Object.assign({knee:mv(Ln.knee), ankle:mv(Ln.ankle), p:Ln.p}, an), f:Object.assign({knee:mv(Lf.knee), ankle:mv(Lf.ankle), p:Lf.p}, af) }),
      d:{ ropeB:half(af.wrist,-1), ropeF:half(an.wrist,1), grF:hd(an.wrist), grB:hd(af.wrist) } };
  }
};
