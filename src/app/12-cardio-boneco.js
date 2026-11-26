/* ---------- boneco do cardio (canvas): esqueleto com ângulos, membros que afinam, tênis de verdade ---------- */
/* mundo em unidades do boneco (1 m ≈ 88): coxa 39, canela 38, tronco 46; chão de cada cena em y conhecido */
const FIGL = { th:39, sh:38, torso:46, ua:25, fa:23 };
const FIGR = { F:{sh:6.2,el:4.2,wr:3.1,hp:7.4,kn:5.4,an:3.6,t1:1,t2:1}, M:{sh:7.2,el:4.8,wr:3.5,hp:7.8,kn:5.8,an:3.9,t1:1.08,t2:1.25} };
/* marcha por trajetória do pé: no apoio o ponto de contato anda com a lona (calcanhar, pé chapado, ponta), no balanço uma curva Hermite; joelho por IK */
const GAITP = [
  {freq:.95,duty:.62,travel:58,X0:28,H0:81,B:1.2,pOn:-16,pOff:32,a1:.16,a2:.55,lift:7,liftSk:.9,pMid:-4,lean:3,armA:15,armOff:-2,elbow:28,elbowA:6,wind:140,
   hm:6,ha:21,hc:.88,k0:5,k1:12,k1c:.13,k2:58,k2c:.72,k2w:.1,to:.6,pT:28,pH:14,fl:0,bob:1.4},
  {freq:1.3,duty:.42,travel:56,X0:21,H0:79.8,B:-2.2,pOn:-8,pOff:38,a1:.12,a2:.42,lift:22,liftSk:.65,pMid:-6,lean:7,armA:24,armOff:4,elbow:88,elbowA:8,wind:420,
   hm:11,ha:26,hc:.86,k0:13,k1:22,k1c:.14,k2:88,k2c:.64,k2w:.13,to:.38,pT:30,pH:8,fl:2.2,bob:0},
  {freq:1.45,duty:.36,travel:64,X0:21,H0:79,B:-3.2,pOn:-5,pOff:42,a1:.1,a2:.38,lift:30,liftSk:.55,pMid:-10,lean:11,armA:34,armOff:6,elbow:92,elbowA:10,wind:760,
   hm:15,ha:33,hc:.84,k0:15,k1:25,k1c:.13,k2:112,k2c:.6,k2w:.13,to:.33,pT:36,pH:6,fl:3.6,bob:0}
];
const AP = {
  tread:GAITP, road:GAITP,
  bike:[{freq:1,stand:0,lean:50,wind:160},{freq:1.4,stand:0,lean:53,wind:260},{freq:1.75,stand:1,lean:40,wind:380}],
  stair:[{freq:.62,lean:12,lift:8,wind:90},{freq:.85,lean:18,lift:11,wind:140},{freq:1.1,lean:25,lift:14,wind:200}],
  ell:[{freq:.8,r:8,lean:10,wind:110},{freq:1.08,r:9,lean:13,wind:170},{freq:1.36,r:10,lean:17,wind:240}],
  rope:[{freq:1.5,H:8,tl:0,kl:0,wind:60},{freq:1.95,H:8,tl:30,kl:55,wind:80},{freq:2.35,H:12,tl:78,kl:100,wind:110}]
};
const STILL = { tread:.62, road:.62, bike:.1, stair:.3, ell:.15, rope:.5 };
const ABOX = { tread:[46,24,318,220], road:[40,22,320,218], bike:[58,36,300,216], stair:[66,22,292,220], ell:[58,22,292,220], rope:[64,8,276,220] };
const HEEL = [-5,10], BALL = [19.5,10], DHB = 24.5;
const herm = (a,b,m0,m1,t) => { const t2 = t*t, t3 = t2*t; return (2*t3-3*t2+1)*a+(t3-2*t2+t)*m0+(-2*t3+3*t2)*b+(t3-t2)*m1; };
function pitchS(s,P){ if(s<P.a1) return P.pOn*D2R*(1-sstep(s/P.a1)); if(s<P.a2) return 0; const q = (s-P.a2)/(1-P.a2); return P.pOff*D2R*q*q; }
function stanceFoot(s,P){
  const h = P.X0-P.travel*s, p = pitchS(s,P);
  if(p<0){ const q = rot(HEEL,p); return {A:[h-q[0],-q[1]], p}; }
  const q = rot(BALL,p); return {A:[h+DHB-q[0],-q[1]], p};
}
/* pé no referencial do chão (x a partir do quadril, y = 0 no chão) para a fase v da perna */
function footAt(v,P){
  if(v<P.duty) return stanceFoot(v/P.duty,P);
  const w = (v-P.duty)/(1-P.duty), e0 = stanceFoot(1,P), e1 = stanceFoot(0,P), d0 = stanceFoot(.995,P), d1 = stanceFoot(.005,P), k = (1-P.duty)/.005;
  const m0 = [(e0.A[0]-d0.A[0])*k,(e0.A[1]-d0.A[1])*k,(e0.p-d0.p)*k], m1 = [(d1.A[0]-e1.A[0])*k,(d1.A[1]-e1.A[1])*k,(d1.p-e1.p)*k];
  const ws = Math.pow(w,P.liftSk), L = P.lift*Math.pow(Math.sin(Math.PI*ws),2);
  return { A:[herm(e0.A[0],e1.A[0],m0[0],m1[0],w), herm(e0.A[1],e1.A[1],m0[1],m1[1],w)-L], p:herm(e0.p,e1.p,m0[2],m1[2],w)+P.pMid*D2R*Math.sin(Math.PI*w) };
}
const hipH = (u,P) => P.H0+P.B*Math.cos(2*TAU*(u-P.duty/2));
const gaitSpeed = P => P.travel*P.freq/P.duty;
/* IK de dois segmentos: escolhe a dobra do lado de pref; devolve [junta, ponta alcançada] */
function ik2(A,B,l1,l2,pref){
  const dx = B[0]-A[0], dy = B[1]-A[1], d0 = Math.hypot(dx,dy) || .001, d = Math.max(Math.abs(l1-l2)+.01, Math.min(d0, l1+l2-.01)), ux = dx/d0, uy = dy/d0;
  const a = (l1*l1-l2*l2+d*d)/(2*d), h = Math.sqrt(Math.max(0, l1*l1-a*a)), sg = (-uy*pref[0]+ux*pref[1])>=0 ? 1 : -1;
  return [[A[0]+ux*a-uy*h*sg, A[1]+uy*a+ux*h*sg], d0>d ? [A[0]+ux*d, A[1]+uy*d] : B];
}
function torsoJ(hip,lean,tilt){ const S = at(hip,Math.PI-lean,FIGL.torso); return { hip, S, H:at(S,Math.PI-(lean*.35+Math.max(0,lean-20*D2R)*.55),AN.sex==='M'?19.5:17.5), lean, tilt }; }
/* corrida por ângulos (como nos dados de biomecânica): quadril, joelho e pé seguem curvas suaves da fase;
   a altura do quadril vem da perna mais baixa, então o pé de apoio fica no chão sem escorregar para baixo dele */
const gBump = (u,c,w) => { let d = Math.abs(frac(u-c)); d = Math.min(d,1-d); return Math.exp(-d*d/(2*w*w)); };
function gaitLeg(u,P){
  const th = (P.hm+P.ha*Math.cos(TAU*(u-P.hc)))*D2R, kn = (P.k0+P.k1*gBump(u,P.k1c,.07)+P.k2*gBump(u,P.k2c,P.k2w))*D2R;
  const K = at([0,0],th,FIGL.th), sa = th-kn, A = at(K,sa,FIGL.sh);
  const p = P.pT*D2R*gBump(u,P.to+.05,.07) - P.pH*D2R*gBump(u,.985,.05) + 10*D2R*gBump(u,P.k2c,.1);
  const low = A[1]+Math.max(rot(HEEL,p)[1], rot(BALL,p)[1]);
  return {K, A, p, low};
}
function gaitPose(u,P,cx,gy){
  const ln = gaitLeg(u,P), lf = gaitLeg(frac(u+.5),P), k = .8;
  const reach = Math.log(Math.exp(ln.low*k)+Math.exp(lf.low*k))/k - Math.log(2)/k*.35;
  const fly = P.fl*(gBump(u,P.to+.06,.06)+gBump(u,P.to+.56,.06)) + P.bob*Math.cos(2*TAU*(u-.25));
  const hip = [cx, gy-reach-fly];
  const leg = g => ({ A:addv(hip,g.A), K:addv(hip,g.K), p:g.p });
  const n = leg(ln), f = leg(lf), c = Math.cos(TAU*(u-P.hc));
  const lean = (P.lean+1.2*Math.cos(2*TAU*(u-P.to)))*D2R, J = torsoJ(hip,lean,lean*.12);
  const arm = (s,S0) => { const a = (P.armOff-s*P.armA*c)*D2R, e = (P.elbow-s*P.elbowA*c)*D2R, E = at(S0,a,FIGL.ua); return { S0, E, W:at(E,a+e,FIGL.fa) }; };
  const cr = 1.2*c;
  J.n = Object.assign(n, arm(1,[J.S[0]-cr,J.S[1]])); J.f = Object.assign(f, arm(-1,[J.S[0]+cr,J.S[1]]));
  return J;
}
/* cenas antigas (escada, elíptico, corda) calculadas na escala anterior e levadas para a nova */
const OS = 1.45, OM = p => [170+(p[0]-170)*OS, 214+(p[1]-183)*OS];
function fromOld(R,grips){
  const o = R.J, hip = OM(o.hip), lean = Math.atan2(o.sh[0]-o.hip[0], o.hip[1]-o.sh[1]), J = torsoJ(hip,lean,lean*.15);
  const side = s => { const q = o[s], A = OM(q.ankle), ar = ik2(J.S, grips ? grips[s] : OM(q.wrist), FIGL.ua, FIGL.fa, [-.3,1]); return { A, p:q.p, K:ik2(hip,A,FIGL.th,FIGL.sh,[1,0])[0], E:ar[0], W:ar[1] }; };
  J.n = side('n'); J.f = side('f');
  return J;
}
let SHOE_P = null;
function shoeP(){
  if(!SHOE_P) SHOE_P = { up:new Path2D('M-5.5,-4.5C-8.5,1 -9,6 -7.5,8.4L17.5,8.4C22.5,8.4 23.8,5.6 22.2,3.2C19.5,0.6 13,-0.4 8.5,-1.6C6,-5.4 2,-7 -5.5,-4.5Z'),
    sole:new Path2D('M-8.2,6.6L22.9,6.6C23.3,8.4 22,10.2 18,10.2L-6.8,10.2C-8.4,10.2 -8.8,8.4 -8.2,6.6Z'),
    lace:new Path2D('M2.5,-2.2l3,2.4M6,-1.4l3,2.2M9.5,-0.6l2.6,1.8'), sw:new Path2D('M-4,1.8C1,3.6 8,3.8 14,2.6'),
    faceF:new Path2D('M-9.8,1C-10,-9 -3,-11.5 3,-11C9,-10 11,-5 10.6,0L12.4,3L10.4,4C10,9 6,11.5 1,11C-5,10.5 -9.6,6 -9.8,1Z'),
    hairF:new Path2D('M-10.4,2C-11.5,-8 -4,-13.6 3,-12.8C9.4,-12 12,-7 11,-3.5C7,-7 2,-7.4 -2,-6C-4,-2 -6,1 -6.4,4Z'),
    hiF:new Path2D('M-3,-10.6C2,-11.4 6,-10 8.6,-7.4'), browF:new Path2D('M3.6,-4q2.4,-1.2 4.4,-.2'),
    faceM:new Path2D('M-9.8,1C-10,-9 -3,-11.5 3,-11C9,-10 11,-5 10.6,0L12.6,3.4L10.4,4.4C10.4,8 8,11.6 2,11.6C-5,11.4 -9.6,6 -9.8,1Z'),
    hairM:new Path2D('M-10.6,3C-12.4,-7 -6,-14.4 2,-14C8,-13.8 12.6,-10 11.4,-5.6C8,-7.6 3,-8 -1,-7C-3.6,-4 -5.4,-1 -6,3Z'),
    hiM:new Path2D('M-6,-11C-1,-13 5,-12.6 9,-9.6'), sideM:new Path2D('M-9.6,3C-9.4,6 -7.6,8 -5.4,8.4'), browM:new Path2D('M3.4,-4.2q2.4,-1 4.8,-.2'),
    jawM:new Path2D('M1,8.6C4,12 9,10.6 10.6,5'), mouth:new Path2D('M5.6,6.2q2,.8 3.6,-.4') };
  return SHOE_P;
}
/* desenho */
function capC(c,A,B,r1,r2){
  const dx = B[0]-A[0], dy = B[1]-A[1], a = Math.atan2(dy,dx), nx = -Math.sin(a), ny = Math.cos(a);
  c.moveTo(A[0]+nx*r1, A[1]+ny*r1); c.lineTo(B[0]+nx*r2, B[1]+ny*r2);
  c.arc(B[0],B[1],r2,a+Math.PI/2,a-Math.PI/2,true); c.lineTo(A[0]-nx*r1, A[1]-ny*r1);
  c.arc(A[0],A[1],r1,a-Math.PI/2,a+Math.PI/2,true); c.closePath();
}
function fcap(c,A,B,r1,r2,col){ c.beginPath(); capC(c,A,B,r1,r2); c.fillStyle = col; c.fill(); }
function sline(c,pts,w,col){ c.beginPath(); c.moveTo(pts[0][0],pts[0][1]); for(let i=1;i<pts.length;i++) c.lineTo(pts[i][0],pts[i][1]); c.lineWidth = w; c.strokeStyle = col; c.stroke(); }
function rrect(c,x,y,w,h,r,col){ c.beginPath(); c.moveTo(x+r,y); c.arcTo(x+w,y,x+w,y+h,r); c.arcTo(x+w,y+h,x,y+h,r); c.arcTo(x,y+h,x,y,r); c.arcTo(x,y,x+w,y,r); c.closePath(); c.fillStyle = col; c.fill(); }
function disc(c,p,r,col){ c.beginPath(); c.arc(p[0],p[1],r,0,TAU); c.fillStyle = col; c.fill(); }
function drawShoe(c,A,p,C){
  const P = shoeP();
  c.save(); c.translate(A[0],A[1]); c.rotate(p);
  c.fillStyle = C.shoe; c.fill(P.up); c.fillStyle = C.sole; c.fill(P.sole);
  c.strokeStyle = C.lace; c.lineWidth = 1.2; c.stroke(P.lace); c.globalAlpha = .7; c.lineWidth = 1.3; c.stroke(P.sw); c.globalAlpha = 1;
  c.restore();
}
function drawArm(c,J,s,R,C,sex,top){
  const S0 = s.S0 || J.S, E = s.E, W = s.W, d = nrm([W[0]-E[0], W[1]-E[1]]), h = rot(d,.38);
  fcap(c,S0,E,R.sh-1.6,R.el-.6,C.skin); fcap(c,E,W,R.el-.6,R.wr-.4,C.skin);
  fcap(c,[W[0]+d[0]*.6,W[1]+d[1]*.6],[W[0]+d[0]*2.4+h[0]*2.6,W[1]+d[1]*2.4+h[1]*2.6],3.3,2.6,C.skin);
  if(sex==='M') fcap(c,S0,lerpP(S0,E,.36),R.sh+.2,R.el+1,top);
}
function drawLeg(c,J,s,R,C,short){
  fcap(c,J.hip,s.K,R.hp,R.kn,C.skin); fcap(c,s.K,s.A,R.kn,R.an,C.skin);
  fcap(c,J.hip,lerpP(J.hip,s.K,.4),R.hp+.9,R.kn+1.6,short);
  drawShoe(c,s.A,s.p,C);
}
function drawTorso(c,J,R,C,sex){
  const hip = J.hip, S = J.S, vx = S[0]-hip[0], vy = S[1]-hip[1], T = Math.hypot(vx,vy), nx = -vy/T, ny = vx/T;
  const pt = (k,w) => { const m = w*(k>.5 ? R.t2 : R.t1)*(sex==='M' && k>.5 && k<.8 && w>0 ? .9 : 1); return [hip[0]+vx*k+nx*m, hip[1]+vy*k+ny*m]; };
  const P = (...a) => a.map(q => pt(q[0],q[1])).flat();
  const Q = (k,w) => [hip[0]+vx*k+nx*w, hip[1]+vy*k+ny*w], QQ = (...a) => a.map(q => Q(q[0],q[1])).flat();
  c.beginPath();
  if(sex==='M'){ c.moveTo(...Q(0,8.8)); c.bezierCurveTo(...QQ([.3,8.2],[.6,11.6],[.84,11.4])); c.bezierCurveTo(...QQ([.97,11.2],[1.06,6],[1.02,1.5])); c.bezierCurveTo(...QQ([1.01,-4],[.93,-10.6],[.78,-10.8])); c.bezierCurveTo(...QQ([.55,-10.6],[.3,-8.4],[0,-8.8])); }
  else { c.moveTo(...pt(0,9)); c.bezierCurveTo(...P([.35,7],[.62,11],[.9,10])); c.quadraticCurveTo(...P([1.08,2],[1,-7])); c.bezierCurveTo(...P([.8,-10],[.4,-8],[0,-9])); }
  c.closePath(); c.fillStyle = C.top; c.fill();
  if(sex==='M'){ c.lineWidth = .9; c.strokeStyle = 'rgba(0,0,0,.12)'; c.beginPath(); c.moveTo(...Q(.82,10)); c.quadraticCurveTo(...QQ([.7,5],[.74,.5])); c.stroke(); }
  c.beginPath(); c.moveTo(...pt(.02,9.6)); c.lineTo(...pt(.02,-9.6)); c.lineTo(...pt(-.06,-9.4)); c.lineTo(...pt(-.06,9.4)); c.closePath(); c.fillStyle = C.short; c.fill();
}
/* rabo de cavalo: corrente de Verlet presa no prendedor, com atraso, gravidade e vento da corrida */
function ponyStep(root,tilt,dt,wind){
  const N = 5, L = 6.4, rest = nrm(rot([-.9,.42],tilt));
  let p = AN.pony;
  if(!p || Math.hypot(p[0].x-root[0], p[0].y-root[1])>24){ p = AN.pony = []; for(let i=0;i<N;i++){ const x = root[0]+rest[0]*L*i, y = root[1]+rest[1]*L*i; p.push({x,y,px:x,py:y}); } }
  p[0].x = root[0]; p[0].y = root[1];
  const h = Math.min(dt,1/30);
  if(h>0){ const dmp = Math.pow(.03,h), g = 560*h*h, wd = wind*h*h; for(let i=1;i<N;i++){ const q = p[i], vx = (q.x-q.px)*dmp, vy = (q.y-q.py)*dmp; q.px = q.x; q.py = q.y; q.x += vx-wd; q.y += vy+g; } }
  const st = h>0 ? 1-Math.pow(.004,h) : .25;
  for(let it=0; it<3; it++) for(let i=1;i<N;i++){
    const a = p[i-1], b = p[i], k0 = st*(1-i*.14);
    b.x += (a.x+rest[0]*L-b.x)*k0; b.y += (a.y+rest[1]*L-b.y)*k0;
    const dx = b.x-a.x, dy = b.y-a.y, d = Math.hypot(dx,dy) || 1e-3, k = (d-L)/d;
    if(i===1){ b.x -= dx*k; b.y -= dy*k; } else { a.x += dx*k*.5; a.y += dy*k*.5; b.x -= dx*k*.5; b.y -= dy*k*.5; }
  }
}
function drawPony(c,C){
  const p = AN.pony, W = [3.6,4.4,4,3,1.5];
  for(let i=0;i<p.length-1;i++) fcap(c,[p[i].x,p[i].y],[p[i+1].x,p[i+1].y],W[i],W[i+1],C.hair);
  c.beginPath(); c.moveTo(p[0].x-1,p[0].y); for(let i=1;i<p.length-1;i++) c.lineTo(p[i].x-.3,p[i].y-1.2);
  c.lineWidth = 1.1; c.strokeStyle = C.hair2; c.stroke();
}
function drawHead(c,J,C,sex){
  const P = shoeP(), H = J.H;
  const nb = addv(H, rot(sex==='M'?[-1.6,9.5]:[-1.2,8.5],J.tilt)), ns = lerpP(J.S, H, sex==='M'?.08:.18);
  if(sex==='M') fcap(c,ns,nb,4.6,3.7,C.skin); else sline(c,[ns, nb],7,C.skin);
  c.save(); c.translate(H[0],H[1]); c.rotate(J.tilt);
  c.fillStyle = C.skin; c.fill(sex==='M' ? P.faceM : P.faceF);
  c.fillStyle = C.hair; c.fill(sex==='M' ? P.hairM : P.hairF);
  c.lineWidth = 1.1; c.strokeStyle = C.hair2; c.stroke(sex==='M' ? P.hiM : P.hiF);
  if(sex==='M'){ c.lineWidth = 2.2; c.strokeStyle = C.hair; c.stroke(P.sideM); }
  else { c.save(); c.translate(-9.6,-4.3); c.rotate(-20*D2R); rrect(c,-2,-2.5,4,5,1.6,C.band); c.restore(); }
  c.globalAlpha = .55; c.beginPath(); c.ellipse(-3.4,sex==='M'?1.8:1.6,2,2.9,0,0,TAU); c.fillStyle = C.ear; c.fill(); c.globalAlpha = 1;
  disc(c,[5.6,-.6],1.35,C.eye);
  c.lineWidth = sex==='M' ? 1.6 : 1.2; c.strokeStyle = C.hair; c.stroke(sex==='M' ? P.browM : P.browF);
  c.lineWidth = 1.1; c.strokeStyle = C.lip; c.stroke(P.mouth);
  if(sex==='M'){ c.globalAlpha = .35; c.lineWidth = 1; c.strokeStyle = C.hair; c.stroke(P.jawM); c.globalAlpha = 1; }
  c.restore();
}
/* ordem: braço de trás, perna de trás, [meio do aparelho], cabelo, cabeça, tronco, perna da frente, braço da frente */
function drawFigure(c,J,dt,P,mid,front){
  const sex = AN.sex, R = FIGR[sex], C = AN.col;
  drawArm(c,J,J.f,R,C,sex,C.top2); drawLeg(c,J,J.f,R,C,C.short2);
  if(mid) mid();
  if(sex==='F'){
    const r = addv(J.H, rot([-9.6,-4.3],J.tilt));
    ponyStep(r,J.tilt,dt,P.wind||0); drawPony(c,C);
  }
  drawHead(c,J,C,sex); drawTorso(c,J,R,C,sex);
  if(front) front();
  drawLeg(c,J,J.n,R,C,C.short); drawArm(c,J,J.n,R,C,sex,C.top);
}
function shadow(c,x,y,rx){ c.beginPath(); c.ellipse(x,y,rx,3,0,0,TAU); c.fillStyle = AN.col.shadow; c.fill(); }
const SC2 = {
  tread(c,ph,P,dt){
    const C = AN.col, u = frac(ph);
    AN.W += gaitSpeed(P)*dt;
    sline(c,[[40,214],[320,214]],2,C.floor);
    rrect(c,70,196,170,12,6,C.e1); rrect(c,76,193,158,5,2.5,C.e2);
    c.save(); c.beginPath(); c.rect(77,193,156,5); c.clip();
    const off = AN.W%17; for(let x=76-off; x<240; x+=17) rrect(c,x,194,8,3,1.5,C.e3);
    c.restore();
    sline(c,[[236,202],[258,108]],6,C.e2); sline(c,[[258,108],[222,118]],5,C.e2);
    rrect(c,246,96,30,12,5,C.e2); rrect(c,252,100,14,3,1.5,C.gr);
    drawFigure(c,gaitPose(u,P,158,194),dt,P);
  },
  road(c,ph,P,dt,V){
    const C = AN.col, u = frac(ph), gy = 204, W = AN.W += gaitSpeed(P)*dt;
    c.globalAlpha = .22; disc(c,[272,62],15,C.gr); c.globalAlpha = 1;
    const tile = (sp,len,fn) => { const o = (W*sp)%len; for(let x=Math.floor(V[0]/len)*len-o-len; x<V[2]+len; x+=len) fn(x); };
    tile(.18,300,x => { [[6,112,46],[58,82,38],[102,124,54],[162,98,34],[202,132,62],[270,108,26]].forEach(b => rrect(c,x+b[0],b[1],b[2],gy-b[1]+2,3,C.bld)); });
    tile(.5,230,x => { fcap(c,[x+44,gy],[x+44,150],2.8,2.2,C.dim); disc(c,[x+44,136],21,C.crown); disc(c,[x+58,148],13,C.crown);
      fcap(c,[x+168,gy],[x+168,170],2,1.8,C.dim); disc(c,[x+168,160],13,C.crown); });
    c.fillStyle = C.eq; c.fillRect(V[0],gy,V[2]-V[0],V[3]-gy);
    sline(c,[[V[0],gy+.5],[V[2],gy+.5]],2,C.floor);
    tile(1,56,x => rrect(c,x,gy+9,32,4,2,C.dim));
    shadow(c,178,gy+1,24);
    drawFigure(c,gaitPose(u,P,170,gy),dt,P);
  },
  bike(c,ph,P,dt){
    const C = AN.col, a = TAU*ph, C0 = [150,182], Rk = 14, st = P.stand;
    const hip = lerpP([138,115+.6*Math.sin(2*a)], [152,110+2.5*Math.sin(2*a)], st), lean = P.lean*D2R, J = torsoJ(hip,lean,-lean*.22);
    const ped = q => { const pd = [C0[0]+Rk*Math.cos(q), C0[1]+Rk*Math.sin(q)], p = (4+12*Math.sin(q+.3))*D2R, o = rot([9,12],p), A = [pd[0]-o[0], pd[1]-o[1]]; return { pd, A, p, K:ik2(hip,A,FIGL.th,FIGL.sh,[1,-.2])[0] }; };
    const n = ped(a), f = ped(a+Math.PI), g = lerpP([206,112],[208,110],st);
    const hand = (s,w) => { const r = ik2(s.S0||J.S,w,FIGL.ua,FIGL.fa,[-.3,1]); s.E = r[0]; s.W = r[1]; return s; };
    J.n = hand(n,g); J.f = hand(f,[g[0]-3,g[1]-1.5]);
    const crank = s => { fcap(c,C0,s.pd,2.4,2,C.dk); fcap(c,[s.pd[0]-4.5,s.pd[1]+1],[s.pd[0]+4.5,s.pd[1]+1],1.9,1.9,C.dk); };
    sline(c,[[40,210],[320,210]],2,C.floor);
    crank(f);
    drawFigure(c,J,dt,P,() => {
      sline(c,[[112,207],[216,207]],7,C.e2);
      c.lineWidth = 6; c.strokeStyle = C.e2; c.beginPath();
      [[[146,206],[148,184]],[[148,184],[138,124]],[[148,184],[192,144]],[[140,140],[190,136]],[[196,182],[190,136]],[[190,136],[196,116]],[[190,206],[196,182]]].forEach(([p,q]) => { c.moveTo(p[0],p[1]); c.lineTo(q[0],q[1]); });
      c.stroke();
      c.beginPath(); c.arc(196,182,21,0,TAU); c.lineWidth = 5; c.stroke();
      disc(c,[196,182],4,C.e2);
      const sp = a*2.4; sline(c,[[196-13*Math.cos(sp),182-13*Math.sin(sp)],[196+13*Math.cos(sp),182+13*Math.sin(sp)]],2,C.gr);
      c.beginPath(); c.moveTo(124,120); c.bezierCurveTo(130,116,146,116,152,120); c.lineWidth = 7; c.strokeStyle = C.dk; c.stroke();
      sline(c,[[196,116],[212,114]],5,C.e2);
      disc(c,C0,4,C.dk);
    }, () => crank(n));
  }
};
/* escada, elíptico e corda: mesma cinemática de antes, aparelho desenhado na escala nova */
let OLDST = null;
function oldStatic(){
  if(OLDST) return OLDST;
  OLDST = {
    stair:[['floor','M70 183H262'],['eq',cap([112,181],[214,181],3,3)+cap([116,182],[116,128],2.6,2.6)+cap([206,182],[206,82],3.6,3.6)+cap([196,80],[220,77],5,4.4)+cap([206,111],[180,112],2.4,2.4)+cap([180,112],[180,122],2,2)],['gr',cap([201,79],[214,77.5],1.6,1.6)]],
    ell:[['floor','M70 183H262'],['eq',cap([96,181],[222,181],3,3)+circ([108,166],15)+cap([214,181],[205,117],3.4,3.2)+cap([205,117],[202,92],3,3)+cap([192,88],[214,85],5,4.4)],['dk',circ([108,166],5.5)],['gr',cap([198,87],[209,86],1.6,1.6)]],
    rope:[['floor','M90 183H250']]
  };
  return OLDST;
}
function oldFill(c,key,str){
  const C = AN.col;
  if(!str) return;
  if(key==='floor'){ c.lineWidth = 3; c.strokeStyle = C.dim; c.stroke(new Path2D(str)); return; }
  if(key==='ropeB' || key==='ropeF'){ c.lineWidth = 2.2; c.strokeStyle = key==='ropeB' ? C.hback : C.gr; c.stroke(new Path2D(str)); return; }
  c.fillStyle = C[key.slice(0,2)==='eq' ? 'eq' : key.slice(0,2)==='dk' ? 'dk' : 'gr']; c.fill(new Path2D(str));
}
['stair','ell','rope'].forEach(kind => {
  SC2[kind] = (c,ph,P,dt) => {
    const R = SCN[kind](ph,P), d = R.d || {}, J = fromOld(R, kind==='stair' ? {n:[187,110.5], f:[184,111.5]} : null);
    const old = fn => { c.save(); c.translate(170,214); c.scale(OS,OS); c.translate(-170,-183); fn(); c.restore(); };
    if(kind==='rope') shadow(c,170,214,30);
    old(() => {
      oldStatic()[kind].forEach(([k,s]) => oldFill(c,k,s));
      if(kind==='stair'){ c.save(); c.beginPath(); c.rect(118,118,86,65); c.clip(); }
      ['eqB','dkB','grB'].forEach(k => oldFill(c,k,d[k]));
      if(kind==='stair') c.restore();
      oldFill(c,'ropeB',d.ropeB);
    });
    drawFigure(c,J,dt,P,() => old(() => ['eqM','dkM','grM'].forEach(k => oldFill(c,k,d[k]))));
    old(() => { ['eqF','dkF','grF'].forEach(k => oldFill(c,k,d[k])); oldFill(c,'ropeF',d.ropeF); });
  };
});
/* ---------- boneco 3D (por enquanto só no app de teste e só na Rua): sequência de imagens recortadas numa cena de rua desenhada pelo app ---------- */
/* cada folha (src/b3/rua-*.webp) é uma passada completa; w,h = tamanho de um quadro · D = quadros · g = linha do chão · vel = quanto o pé de apoio anda para trás por quadro · bot/cx = pé mais baixo e centro do corpo em cada quadro */
const B3_ON = false, B3_TROCA = false; // B3_TROCA: botão para ver a mulher ou o homem (só no app de teste)
const b3Sexo = () => B3.sx || sexOf();
const B3_S = {
  road:{ F:{w:218,h:300,D:21,g:297,vel:21.5,cxm:122.5,alt:164,bot:[283,289,295,296,297,297,297,296,296,295,288,282,288,294,296,295,295,295,294,294,291],cx:[126,126,126,126,122,116,110,116,121,123,126,130,131,124,118,112,116,123,127,128,128],arq:'rua-f'},
    M:{w:220,h:300,D:20,g:297,vel:22.5,cxm:132.4,alt:172,bot:[297,297,297,296,296,296,295,289,287,291,294,295,295,295,294,294,293,287,287,294],cx:[130,131,132,131,134,136,134,132,132,132,134,136,133,136,135,133,131,128,128,128],arq:'rua-m'} } };
/* esteira: a mesma corrida da rua, em cima da esteira parada (b3/esteira.webp, 216 px de largura na cena; a lona fica 27,6 px acima do piso e o meio dela a 97,7 px da ponta de trás) */
/* pular corda (o "Outro" do app), de frente: a pessoa pula no lugar; só a sombra muda com a altura do pulo */
B3_S.rope = { F:{w:161,h:300,D:24,g:297,alt:186,pulo:1,arq:'corda-f',bot:[283,281,282,285,290,297,297,297,297,297,297,289,283,282,282,284,290,297,297,297,297,297,297,289]} };
B3_S.tread = { F:Object.assign({}, B3_S.road.F, {est:1, alt:150}), M:Object.assign({}, B3_S.road.M, {est:1, alt:156}) };
const B3_ARV = '<svg viewBox="0 0 105 96" aria-hidden="true"><rect x="20" y="52" width="5" height="44" fill="var(--b3-tronco)"/><circle cx="22" cy="40" r="19" fill="var(--b3-arv)"/><circle cx="34" cy="50" r="13" fill="var(--b3-arv)"/><circle cx="11" cy="52" r="11" fill="var(--b3-arv)"/><rect x="76" y="18" width="3.5" height="78" fill="var(--b3-poste)"/><rect x="76" y="18" width="16" height="3.5" rx="1.5" fill="var(--b3-poste)"/><rect x="88" y="20" width="8" height="5" rx="2.5" fill="var(--b3-faixa)"/></svg>';
const B3 = { sx:'', el:null, M:null, s:1, k:1, ph:0, cx:179, raf:0, last:0, ro:null, p:null };
const b3Html = (k,rot,kind) => `<div class="b3 b3-${kind}${kind==='road' ? '' : ' b3-gym'}" data-kind="${kind}" data-int="${k}" role="img" aria-label="${esc(rot)}"><div class="b3i"><i class="ceu"></i><i class="sol"></i><i class="jan"></i><svg class="longe" viewBox="0 0 358 74" preserveAspectRatio="none" aria-hidden="true"><path fill="var(--b3-longe)" d="M0 74V40h22V22h26v18h14V8h30v32h18V28h24v12h12V16h34v24h16V34h22v6h14V4h28v36h20V24h26v16h20V36h0V74z"/></svg><div class="meio">${B3_ARV.repeat(12)}</div><i class="calc"></i><i class="rua"></i><i class="grao"></i><i class="faixa"></i><i class="sme"></i><i class="est"></i><i class="lona"></i><i class="sm"></i><i class="bn"></i></div>${B3_TROCA ? `<button type="button" class="b3-sx" data-a="b3-sx">${ic('swap')}Ver ${b3Sexo()==='M' ? 'a mulher' : 'o homem'}</button>` : ''}</div>`;
function b3Draw(){
  const M = B3.M, s = B3.s, p = B3.p; if(!M || !p) return;
  const k = Math.floor(B3.ph)%M.D;
  p.bn.style.backgroundPositionX = (-k*B3.sw)+'px';
  const e = 1-Math.min(.5,(M.g-M.bot[k])*s/16);
  if(M.pulo){ p.bn.style.left = (B3.cx-B3.sw/2)+'px'; p.sm.style.left = B3.cx+'px'; p.sm.style.transform = 'scale('+e+')'; p.sm.style.opacity = .5+e*.5; return; }
  const v = M.vel*s, o = (B3.ph%(M.D*3))*v;
  if(M.est){ // pés no meio da lona: o centro da passada fica um pouco atrás do centro do corpo
    const x0 = B3.cx-108, c = x0+97.7+13*s;
    p.es.style.left = p.se.style.left = x0+'px'; p.lo.style.left = (x0+23)+'px'; p.lo.style.backgroundPositionX = (-o)+'px';
    p.bn.style.left = (c-M.cxm*s-(M.cx[k]-M.cxm)*s*.7)+'px'; p.sm.style.left = c+'px'; p.sm.style.transform = 'scale('+e+')'; p.sm.style.opacity = .5+e*.5; return;
  }
  p.bn.style.left = (B3.cx-M.cxm*s-(M.cx[k]-M.cxm)*s*.7)+'px';
  p.fx.style.backgroundPositionX = p.gr.style.backgroundPositionX = (-o)+'px';
  p.me.style.transform = 'translateX('+(-((B3.ph%(M.D*3))*105/M.D)%105)+'px)';
  p.sm.style.left = B3.cx+'px'; p.sm.style.transform = 'scale('+e+')'; p.sm.style.opacity = .55+e*.45;
}
function b3Frame(ts){
  B3.raf = 0;
  if(!B3.el || !B3.el.isConnected || document.visibilityState==='hidden'){ B3.last = 0; return; }
  const dt = B3.last ? Math.min(.05,(ts-B3.last)/1000) : 0; B3.last = ts;
  B3.ph += dt*24*[.8,1,1.22][B3.k]; b3Draw();
  B3.raf = requestAnimationFrame(b3Frame);
}
function b3Kick(){ if(!B3.raf && B3.el && B3.el.isConnected && !RM() && document.visibilityState!=='hidden'){ B3.last = 0; B3.raf = requestAnimationFrame(b3Frame); } }
function b3Init(){
  const el = ($sheet && $sheet.querySelector('.sheet-wrap:not(.closing) .b3')) || $app.querySelector('.b3');
  if(B3.ro){ B3.ro.disconnect(); B3.ro = null; }
  if(!el){ cancelAnimationFrame(B3.raf); B3.raf = 0; B3.el = null; return; }
  const M = B3_S[el.dataset.kind][b3Sexo()], i = el.firstElementChild, q = c => i.querySelector(c), sw = Math.round(M.w*M.alt/(M.g-4)), s = sw/M.w, per = (M.vel||18)*s*M.D/3;
  B3.el = el; B3.M = M; B3.s = s; B3.sw = sw; B3.k = Math.max(0, Math.min(2, +el.dataset.int||0)); B3.p = { bn:q('.bn'), sm:q('.sm'), fx:q('.faixa'), gr:q('.grao'), me:q('.meio'), es:q('.est'), se:q('.sme'), lo:q('.lona') };
  B3.p.bn.style.cssText = 'background-image:url(b3/'+M.arq+'.webp);width:'+sw+'px;height:'+(M.h*s)+'px;bottom:'+((M.est ? 57.6 : 36)-(M.h-M.g)*s)+'px';
  B3.p.lo.style.backgroundSize = (per/3)+'px 100%';
  B3.p.fx.style.backgroundImage = 'repeating-linear-gradient(90deg,var(--b3-faixa) 0 '+(per*.52)+'px,transparent '+(per*.52)+'px '+per+'px)'; B3.p.gr.style.backgroundSize = per+'px 50px';
  const cabe = () => { const r = el.getBoundingClientRect(); if(!r.height) return; const z = r.height/232; i.style.width = (r.width/z)+'px'; i.style.transform = 'scale('+z+')'; B3.cx = r.width/z/2; b3Draw(); };
  cabe(); if('ResizeObserver' in window){ B3.ro = new ResizeObserver(cabe); B3.ro.observe(el); }
  if(RM()){ B3.ph = 4; b3Draw(); return; }
  b3Kick();
}
function sceneCanvas(local,int_){
  const k = Math.max(0, INTENS.indexOf(int_)), kind = ANIM_KIND[local] || 'rope', cp = (ANIM_CAP[local] || ANIM_CAP.Outro)[k];
  if(B3_ON && B3_S[kind] && B3_S[kind][b3Sexo()]) return b3Html(k, local+', intensidade '+INT_L[int_].toLowerCase()+': '+cp, kind);
  return `<canvas class="anim" data-kind="${kind}" data-int="${k}" role="img" aria-label="${esc(local)}, intensidade ${INT_L[int_].toLowerCase()}: ${cp}"></canvas>`;
}
/* animador único: a fase continua entre telas e a troca de intensidade mistura os parâmetros em 400 ms */
const AN = { cv:null, ctx:null, kind:null, k:null, P:null, from:null, to:null, tb:0, phase:.1, W:0, raf:0, visible:true, io:null, ro:null, col:null, sex:'F', pony:null, w:0, h:0, dpr:1, last:0 };
function readCols(el){
  const cs = getComputedStyle(el), g = n => cs.getPropertyValue(n).trim();
  return { skin:g('--a-skin'), hair:g('--a-hair'), hair2:g('--a-hair2'), top:g('--fig-top'), top2:g('--fig-top2'), short:g('--fig-short'), short2:g('--fig-short2'), shoe:g('--fig-shoe'), sole:g('--fig-sole'),
    lace:g('--fig-lace'), band:g('--fig-band'), eye:g('--fig-eye'), lip:g('--fig-lip'), ear:g('--fig-ear'), e1:g('--eq-1'), e2:g('--eq-2'), e3:g('--eq-3'), floor:g('--floor'),
    eq:g('--a-eq'), dk:g('--a-dk'), gr:g('--cardio-line'), dim:g('--a-dim'), bld:g('--a-bld'), crown:g('--a-crown'), shadow:g('--a-shadow'), hback:g('--a-hback') };
}
function sizeAnim(){
  const cv = AN.cv; if(!cv) return;
  const r = cv.getBoundingClientRect(), dpr = Math.min(3, window.devicePixelRatio || 1);
  AN.w = r.width; AN.h = r.height; AN.dpr = dpr;
  const W = Math.max(1, Math.round(r.width*dpr)), H = Math.max(1, Math.round(r.height*dpr));
  if(cv.width!==W) cv.width = W; if(cv.height!==H) cv.height = H;
}
function drawAnim(dt){
  const c = AN.ctx; if(!c || !AN.w || !AN.h) return;
  const box = ABOX[AN.kind], bw = box[2]-box[0], bh = box[3]-box[1], s = Math.min(AN.w/bw, AN.h/bh), ox = (AN.w-bw*s)/2-box[0]*s, oy = (AN.h-bh*s)/2-box[1]*s, d = AN.dpr;
  c.setTransform(d,0,0,d,0,0); c.clearRect(0,0,AN.w,AN.h);
  c.setTransform(d*s,0,0,d*s,d*ox,d*oy); c.lineCap = 'round'; c.lineJoin = 'round';
  SC2[AN.kind](c, AN.phase, AN.P, dt, [-ox/s, -oy/s, (AN.w-ox)/s, (AN.h-oy)/s]);
}
function animFrame(ts){
  AN.raf = 0;
  if(!AN.cv || !AN.cv.isConnected || !AN.visible || document.visibilityState==='hidden'){ AN.last = 0; return; }
  const dt = AN.last ? Math.min(.05,(ts-AN.last)/1000) : 0; AN.last = ts;
  if(AN.to){
    const q = Math.min(1,(ts-AN.tb)/400), e = q<.5 ? 2*q*q : 1-Math.pow(-2*q+2,2)/2;
    for(const key in AN.to) AN.P[key] = AN.from[key]+(AN.to[key]-AN.from[key])*e;
    if(q>=1) AN.to = null;
  }
  AN.phase += dt*AN.P.freq;
  drawAnim(dt);
  AN.raf = requestAnimationFrame(animFrame);
}
function kickAnim(){ if(!AN.raf && AN.cv && AN.cv.isConnected && AN.visible && !RM() && document.visibilityState!=='hidden'){ AN.last = 0; AN.raf = requestAnimationFrame(animFrame); } }
function initAnim(){
  b3Init();
  const cv = ($sheet && $sheet.querySelector('.sheet-wrap:not(.closing) canvas.anim')) || $app.querySelector('canvas.anim');
  if(AN.io){ AN.io.disconnect(); AN.io = null; }
  if(AN.ro){ AN.ro.disconnect(); AN.ro = null; }
  if(!cv){ cancelAnimationFrame(AN.raf); AN.raf = 0; AN.cv = null; return; }
  const kind = cv.dataset.kind, k = +cv.dataset.int, target = AP[kind][k], sex = sexOf();
  AN.cv = cv; AN.ctx = cv.getContext('2d'); AN.col = readCols(cv);
  if(AN.sex!==sex){ AN.sex = sex; AN.pony = null; }
  if(AN.kind!==kind || !AN.P){ AN.P = Object.assign({}, target); AN.to = null; AN.pony = null; }
  else if(AN.k!==k){ AN.from = Object.assign({}, AN.P); AN.to = target; AN.tb = performance.now(); }
  AN.kind = kind; AN.k = k;
  sizeAnim();
  if(RM()){
    AN.P = Object.assign({}, target); AN.to = null; AN.phase = STILL[kind]; AN.pony = null;
    for(let i=0;i<40;i++) drawAnim(1/60);
    return;
  }
  drawAnim(0);
  AN.visible = true;
  if('IntersectionObserver' in window){ AN.io = new IntersectionObserver(es => { AN.visible = es[es.length-1].isIntersecting; kickAnim(); }); AN.io.observe(cv); }
  if('ResizeObserver' in window){ AN.ro = new ResizeObserver(() => { sizeAnim(); drawAnim(0); }); AN.ro.observe(cv); }
  kickAnim();
}
document.addEventListener('visibilitychange', () => { kickAnim(); b3Kick(); });
function sugEnviar(b){
  const t = document.getElementById('sug-txt'), v = t ? t.value.trim() : '';
  if(!v && !ui.sugFoto){ toast('Escreva a sugestão ou anexe uma foto antes de enviar.'); if(t) t.focus(); return; }
  if(!FB){ toast('Sem conexão com o servidor agora. Tente de novo mais tarde.'); return; }
  if(b) b.disabled = true;
  FB.evento('sugestoes', Object.assign(quem(), { texto:v.slice(0,1000), em:Date.now(), ver:APPV }, ui.sugFoto ? { foto:ui.sugFoto } : {})).then(ok => {
    if(b) b.disabled = false;
    if(ok===true){ ui.sugD = ''; ui.sugFoto = ''; if(t) t.value = ''; renderSheet(); toast('Sugestão enviada. Obrigado!'); } else toast('Não deu para enviar agora. Confira a internet e tente de novo.');
  });
}
document.addEventListener('input', e => { if(e.target && e.target.id==='sug-txt') ui.sugD = e.target.value; });
// foto da caixinha: diminui para no máximo 1100 px e JPEG, para caber junto com a sugestão
document.addEventListener('change', e => { const el = e.target; if(!el || el.id!=='sug-file') return; const f = el.files && el.files[0]; el.value = ''; if(!f) return;
  const u = URL.createObjectURL(f), im = new Image();
  im.onload = () => { URL.revokeObjectURL(u); let q = .72, k = Math.min(1, 1100/Math.max(im.width, im.height)), d = '';
    for(let i=0; i<5; i++){ const cv = document.createElement('canvas'); cv.width = Math.round(im.width*k); cv.height = Math.round(im.height*k); cv.getContext('2d').drawImage(im,0,0,cv.width,cv.height); d = cv.toDataURL('image/jpeg', q); if(d.length<700000) break; k *= .8; q = Math.max(.5, q-.06); }
    if(d.length>=780000){ toast('A foto ficou grande demais. Tente um print da tela.'); return; }
    ui.sugFoto = d; renderSheet(); };
  im.onerror = () => { URL.revokeObjectURL(u); toast('Não deu para abrir essa foto. Tente outra.'); };
  im.src = u; });
