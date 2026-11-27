/* ---------- animação da execução (boneco clássico) ---------- */
const XA = (() => {
  const f = n => (+n).toFixed(1), P = p => f(p[0]) + ',' + f(p[1]);
  const add = (a, b, k = 1) => [a[0] + b[0] * k, a[1] + b[1] * k], sub = (a, b) => [a[0] - b[0], a[1] - b[1]];
  const len = a => Math.hypot(a[0], a[1]), nrm = a => { const l = len(a) || 1; return [a[0] / l, a[1] / l]; };
  const R = d => d * Math.PI / 180, dir = a => [Math.sin(R(a)), Math.cos(R(a))], ang = v => Math.atan2(v[0], v[1]) * 180 / Math.PI;
  const lerp = (a, b, t) => a + (b - a) * t, mix = (a, b, t) => [lerp(a[0], b[0], t), lerp(a[1], b[1], t)];
  const ease = x => x < .5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2;
  // medidas do boneco (px): canela, coxa, quadril→ombro, braço, antebraço; chão em GY
  const LS = 76, LT = 76, LEG = LS + LT, LTOR = 92, LU = 52, LF = 46, GY = 392, AY = GY - 13, OFF = [-6, -2];
  let SFX = '', NOEQ = false; // sufixo dos ids de gradiente (várias animações na mesma página) · NOEQ: sem barra, halter ou anilha
  const INK = '#2E3933', FAR = '#B6BDB8', GHOST = '#E9ECE9', PROP = '#E2E6E3', PROP2 = '#C9D0CB', PLATE = '#A9B1AB', GRIP = '#5B665E', RED = '#FF4646';

  // 2 segmentos: raiz Rt, alvo T; a articulação do meio dobra para o lado de "pole"
  function ik(Rt, T, l1, l2, pole) {
    const d0 = sub(T, Rt), L = len(d0) || 1, u = [d0[0] / L, d0[1] / L], d = Math.min(L, (l1 + l2) * .999);
    const a = (l1 * l1 - l2 * l2 + d * d) / (2 * d), h = Math.sqrt(Math.max(0, l1 * l1 - a * a));
    let n = [-u[1], u[0]]; if (n[0] * pole[0] + n[1] * pole[1] < 0) n = [-n[0], -n[1]];
    const M = add(add(Rt, u, a), n, h);
    return [M, add(M, nrm(sub(T, M)), l2)];
  }
  // tronco: tor = direção quadril→ombro (graus: 0 baixo, 90 direita, 180 cima, -90 esquerda); head = giro da cabeça
  function body(H, tor, head = 0) {
    const u = dir(tor), v = [-u[1], u[0]], hu = dir(tor + head), hv = [-hu[1], hu[0]], Nk = add(H, u, LTOR + 9);
    return { H, tor, u, v, S: add(add(H, u, LTOR), v, 2), Nk, hu, hv, Hd: add(add(Nk, hu, 25), hv, 2) };
  }
  const legTo = (H, A, pole, ft = 90) => { const [K, A2] = ik(H, A, LT, LS, pole); return { H, K, A: A2, ft }; };
  const legAng = (H, th, sh, ft) => { const K = add(H, dir(th), LT), A = add(K, dir(sh), LS); return { H, K, A, ft: ft == null ? sh + 90 : ft }; };
  const armTo = (S, W, pole, su = 1, sf = 1) => { const [E, W2] = ik(S, W, LU * su, LF * sf, pole); return { S, E, W: W2 }; };
  const armAng = (S, ua, fa, su = 1, sf = 1) => { const E = add(S, dir(ua), LU * su); return { S, E, W: add(E, dir(fa), LF * sf) }; };
  const far = p => add(p, OFF);
  // ponto da sola (x ao longo do pé, y para a sola) → posição do tornozelo
  // gira o ponto p em torno de c (graus, sentido horário na tela, como o rotate do SVG)
  const rotP = (p, c, deg) => { const r = R(deg), dx = p[0] - c[0], dy = p[1] - c[1]; return [c[0] + dx * Math.cos(r) - dy * Math.sin(r), c[1] + dx * Math.sin(r) + dy * Math.cos(r)]; };
  const ankleFrom = (pt, ft, x, y) => { const fd = dir(ft), n = [-fd[1], fd[0]]; return sub(sub(pt, [fd[0] * x, fd[1] * x]), [n[0] * y, n[1] * y]); };

  // ---------- desenho do boneco ----------
  function limb(A, B, r1, r2, bf = 0, bb = 0, pk = .42) {
    const d = nrm(sub(B, A)), n = [-d[1], d[0]], L = len(sub(B, A)), m = add(A, d, L * pk), rm = r1 + (r2 - r1) * pk;
    const a1 = add(A, n, r1), a2 = add(A, n, -r1), b1 = add(B, n, r2), b2 = add(B, n, -r2);
    return `M${P(a1)} Q${P(add(m, n, rm + 2 * bb))} ${P(b1)} A${f(r2)},${f(r2)} 0 0 0 ${P(b2)} Q${P(add(m, n, -(rm + 2 * bf)))} ${P(a2)} A${f(r1)},${f(r1)} 0 0 0 ${P(a1)} Z`;
  }
  function footPath(A, ftDeg) { // x = rumo dos dedos, y = lado da sola
    const fd = dir(ftDeg), n = [-fd[1], fd[0]], L = (x, y) => P(add(add(A, fd, x), n, y));
    return `M${L(-9, -3)} Q${L(-15, 2)} ${L(-14, 9)} Q${L(-13, 13)} ${L(-8, 13)} L${L(32, 13)} Q${L(38, 13)} ${L(37, 9)} Q${L(36, 5)} ${L(26, 3)} Q${L(12, -1)} ${L(6, -6)} Z`;
  }
  function man(B, flat, col) {
    const g = [], C = flat ? col : `url(#xaInk${SFX})`, FC = flat ? col : FAR;
    const st = fill => flat ? `fill="${fill}"` : `fill="${fill}" stroke="#FFFFFF" stroke-width="3.2" stroke-linejoin="round" paint-order="stroke"`;
    const seg = (A, Bq, r1, r2, bf, bb, pk, c) => `<path d="${limb(A, Bq, r1, r2, bf, bb, pk)}" ${st(c)}/>`;
    const dot = (p, r, c) => flat ? '' : `<circle cx="${f(p[0])}" cy="${f(p[1])}" r="${r}" ${st(c)}/>`;
    const hand = (E, W, c) => { const h = add(W, nrm(sub(W, E)), 4); return `<circle cx="${f(h[0])}" cy="${f(h[1])}" r="6" ${st(c)}/>`; };
    const legD = (L, c, isFar) => seg(L.H, L.K, 14.5, 9.5, 3.5, 2.2, .42, c) + seg(L.K, L.A, 9.5, 6, 0, 3.4, .3, c) + `<path d="${footPath(L.A, L.ft)}" ${st(c)}/>` +
      (isFar ? dot(L.K, 6, FAR) : dot(L.H, 9, INK) + dot(L.K, 7.2, INK) + dot(L.A, 5.2, INK));
    const armD = (Ar, c, isFar) => seg(Ar.S, Ar.E, 8, 6.2, 1, 1.2, .42, c) + seg(Ar.E, Ar.W, 6.2, 4.6, .6, .8, .3, c) + hand(Ar.E, Ar.W, c) + (isFar ? '' : dot(Ar.S, 8.2, INK) + dot(Ar.E, 5.8, INK) + dot(Ar.W, 4.2, INK));
    g.push(armD(B.fA, FC, true), legD(B.fL, FC, true));
    const T = (a, b) => P(add(add(B.H, B.u, a), B.v, b));
    g.push(seg(add(B.H, B.u, LTOR - 6), add(B.Nk, B.hu, 8), 7.5, 7, 0, 0, .42, C));
    let hd = ''; for (let i = 0; i <= 36; i++) { const t = i / 36 * Math.PI * 2, e = [17.5 * Math.sin(t), -20.5 * Math.cos(t)]; hd += (i ? 'L' : 'M') + P(add(add(B.Hd, B.hv, e[0]), B.hu, -e[1])); }
    g.push(`<path d="${hd}Z" ${st(C)}/>`);
    if (!flat) g.push(`<path d="M${P(add(add(B.Hd, B.hv, 16.5), B.hu, 2))} l${f(B.hv[0] * 4 - B.hu[0] * 3)},${f(B.hv[1] * 4 - B.hu[1] * 3)}" stroke="#FFFFFF" stroke-width="1.3" stroke-linecap="round" opacity=".7"/>`);
    g.push(`<path d="M${T(34, 14)} Q${T(30, 23)} ${T(10, 22)} Q${T(-10, 21)} ${T(-15, 6)} Q${T(-17, -12)} ${T(-6, -22)} Q${T(8, -27)} ${T(26, -21)} Q${T(38, -16)} ${T(34, 14)} Z" ${st(C)}/>`);
    g.push(`<path d="M${T(48, -15)} Q${T(46, 4)} ${T(50, 16)} Q${T(62, 27)} ${T(82, 24)} Q${T(100, 20)} ${T(101, 2)} Q${T(103, -16)} ${T(94, -21)} Q${T(74, -27)} ${T(56, -22)} Q${T(48, -20)} ${T(48, -15)} Z" ${st(C)}/>`);
    g.push(dot(add(add(B.H, B.u, 42), B.v, -1), 8, INK));
    g.push(legD(B.nL, C, false), armD(B.nA, C, false));
    return g.join('');
  }
  // boneco de frente: F = {cx, a (abdução do braço, graus), el (dobra do cotovelo)} ou F.W(s, ombro) = onde fica a mão;
  // F.seat = sentado (coxas vindo na direção de quem olha), F.kx = afastamento dos joelhos sentado
  function manFront(F, flat, col) {
    const C = flat ? col : `url(#xaInk${SFX})`, st = fill => flat ? `fill="${fill}"` : `fill="${fill}" stroke="#FFFFFF" stroke-width="3.2" stroke-linejoin="round" paint-order="stroke"`;
    const dot = (p, r) => flat ? '' : `<circle cx="${f(p[0])}" cy="${f(p[1])}" r="${r}" ${st(INK)}/>`, x = F.cx, g = [];
    const hipY = F.seat ? 300 : AY - LEG + 2, shY = hipY - 86, neckY = shY - 6, headC = [x, neckY - 26], kx = F.kx == null ? 20 : F.kx;
    let ank = null;
    for (const s of [-1, 1]) {
      const H = [x + s * 13, hipY], ab = F.abd && F.abd.s === s ? F.abd.a : 0;
      let K = F.seat ? [x + s * kx, hipY + 16] : [x + s * 15, hipY + LT], A = F.seat ? [x + s * (kx + 1), AY] : [x + s * 16, AY];
      if (ab) { const d = dir(s * ab); K = add(H, d, LT); A = add(K, d, AY - hipY - LT); ank = add(A, d, -10); }
      const sh = [[A[0] - 9, A[1] + 3], [A[0], A[1] - 6], [A[0] + 9, A[1] + 3], [A[0] + s * 4 + 8, A[1] + 13], [A[0] + s * 4 - 8, A[1] + 13]].map(p => ab ? rotP(p, A, -s * ab) : p);
      g.push(`<path d="${limb(H, K, 14, 9.5)}" ${st(C)}/><path d="${limb(K, A, 9.5, 6)}" ${st(C)}/><path d="M${P(sh[0])} Q${P(sh[1])} ${P(sh[2])} L${P(sh[3])} L${P(sh[4])} Z" ${st(C)}/>`);
      g.push(dot(H, 8.5), dot(K, 7), dot(A, 5));
    }
    g.push(`<path d="M${f(x - 25)},${f(hipY - 26)} Q${f(x)},${f(hipY - 32)} ${f(x + 25)},${f(hipY - 26)} Q${f(x + 30)},${f(hipY + 8)} ${f(x + 18)},${f(hipY + 14)} Q${f(x)},${f(hipY + 20)} ${f(x - 18)},${f(hipY + 14)} Q${f(x - 30)},${f(hipY + 8)} ${f(x - 25)},${f(hipY - 26)} Z" ${st(C)}/>`);
    g.push(`<path d="M${f(x - 21)},${f(shY + 50)} Q${f(x - 35)},${f(shY + 8)} ${f(x - 30)},${f(shY - 3)} Q${f(x)},${f(shY - 12)} ${f(x + 30)},${f(shY - 3)} Q${f(x + 35)},${f(shY + 8)} ${f(x + 21)},${f(shY + 50)} Q${f(x)},${f(shY + 58)} ${f(x - 21)},${f(shY + 50)} Z" ${st(C)}/>`);
    g.push(dot([x, shY + 60], 7.5));
    g.push(`<path d="${limb([x, shY + 2], [x, neckY - 8], 7.5, 7)}" ${st(C)}/><ellipse cx="${f(headC[0])}" cy="${f(headC[1])}" rx="17" ry="20.5" ${st(C)}/>`);
    const arms = [];
    for (const s of [-1, 1]) {
      const S = [x + s * 31, shY + 4]; let E, W;
      let T = F.W ? F.W(s, S) : null;
      if (T && T.a == null && F.lean) T = rotP(T, F.piv, -F.lean); // alvo dado na cena já inclinada
      if (T && T.a == null) { const k = Math.min(1, Math.max(.5, len(sub(T, S)) / ((LU + LF) * .96))); [E, W] = ik(S, T, LU * k, LF * k, [s, .7]); }
      else { const q = T || F; E = add(S, dir(s * q.a), LU); W = add(E, dir(s * (q.a + q.el)), LF); }
      g.push(`<path d="${limb(S, E, 8, 6.2)}" ${st(C)}/><path d="${limb(E, W, 6.2, 4.6)}" ${st(C)}/><circle cx="${f(add(W, nrm(sub(W, E)), 4)[0])}" cy="${f(add(W, nrm(sub(W, E)), 4)[1])}" r="6" ${st(C)}/>`);
      g.push(dot(S, 8.2), dot(E, 5.8), dot(W, 4.2)); arms.push(add(W, nrm(sub(W, E)), 6));
    }
    if (F.lean) { const R = p => p && rotP(p, F.piv, F.lean); return { svg: `<g transform="rotate(${f(F.lean)} ${P(F.piv)})">${g.join('')}</g>`, hands: arms.map(R), ank: R(ank), hipY, shY, kx, cx: x }; }
    return { svg: g.join(''), hands: arms, ank, hipY, shY, kx, cx: x };
  }

  // ---------- acessórios ----------
  const rect = (x, y, w, h, r = 4, c = PROP) => `<rect x="${f(x)}" y="${f(y)}" width="${f(w)}" height="${f(h)}" rx="${r}" fill="${c}"/>`;
  const bar = (a, b, w, c = PROP) => `<path d="M${P(a)} L${P(b)}" stroke="${c}" stroke-width="${w}" stroke-linecap="round"/>`;
  const plate = (p, r = 34) => `<circle cx="${f(p[0])}" cy="${f(p[1])}" r="${r}" fill="${PROP}" stroke="${PROP2}" stroke-width="2"/><circle cx="${f(p[0])}" cy="${f(p[1])}" r="${f(r * .58)}" fill="none" stroke="${PROP2}" stroke-width="1.5"/><circle cx="${f(p[0])}" cy="${f(p[1])}" r="5" fill="${PLATE}"/>`;
  const plateRing = (p, r = 34) => `<circle cx="${f(p[0])}" cy="${f(p[1])}" r="${r}" fill="none" stroke="${PROP2}" stroke-width="2.2"/><circle cx="${f(p[0])}" cy="${f(p[1])}" r="5" fill="${GRIP}"/>`;
  const dbEnd = p => `<circle cx="${f(p[0])}" cy="${f(p[1])}" r="10" fill="${PLATE}" stroke="#FFFFFF" stroke-width="1.6"/><circle cx="${f(p[0])}" cy="${f(p[1])}" r="4" fill="${GRIP}"/>`;
  const dbSide = (p, axis = [1, 0]) => { const a = nrm(axis), n = [-a[1], a[0]], q = k => add(p, a, k);
    return `<path d="M${P(q(-15))} L${P(q(15))}" stroke="${GRIP}" stroke-width="4" stroke-linecap="round"/>` + [-15, 15].map(k => { const c = q(k); return `<path d="M${P(add(c, n, -10))} L${P(add(c, n, 10))}" stroke="${PLATE}" stroke-width="9" stroke-linecap="round"/>`; }).join(''); };
  const kettle = p => `<path d="M${f(p[0] - 8)},${f(p[1] + 3)} q8,-15 16,0" fill="none" stroke="${GRIP}" stroke-width="3.4"/><circle cx="${f(p[0])}" cy="${f(p[1] + 15)}" r="13" fill="${PLATE}" stroke="#FFFFFF" stroke-width="1.6"/>`;
  const cable = (a, b) => `<path d="M${P(a)} L${P(b)}" stroke="#8C958F" stroke-width="1.8"/>`;
  const pulley = p => `<circle cx="${f(p[0])}" cy="${f(p[1])}" r="7.5" fill="${PROP}" stroke="${PROP2}" stroke-width="2.4"/>`;
  const bench = (x1, x2, top) => rect(x1, top, x2 - x1, 12, 6, PROP2) + rect(x1 + 12, top + 10, 9, GY - top - 10, 2) + rect(x2 - 21, top + 10, 9, GY - top - 10, 2);

  // ---------- acessórios das máquinas ----------
  const CX = 158;
  const grip = (W, round) => round ? `<circle cx="${f(W[0])}" cy="${f(W[1])}" r="5.5" fill="${GRIP}"/>` : `<path d="M${f(W[0])},${f(W[1] - 9)} L${f(W[0])},${f(W[1] + 9)}" stroke="${GRIP}" stroke-width="6" stroke-linecap="round"/>`;
  const grips = F => F.hands.map(h => `<circle cx="${f(h[0])}" cy="${f(h[1])}" r="5.5" fill="${GRIP}"/>`).join('');
  const stack = () => rect(262, 64, 12, GY - 64, 3) + rect(274, 238, 26, 130, 3, PROP2) + [0, 1, 2, 3, 4, 5].map(i => rect(276, 242 + i * 20, 22, 16, 2, PLATE)).join('');
  const seatBack = b => rect(b.H[0] - 26, b.H[1] + 11, 70, 12, 6, PROP2) + bar(add(b.H, [-26, 4]), add(b.H, [-34, -102]), 17, PROP2) + rect(b.H[0] - 4, b.H[1] + 21, 10, GY - b.H[1] - 21, 2) + rect(b.H[0] - 40, GY - 8, 250, 8, 4);
  const seatFront = F => rect(CX - 36, F.shY - 52, 72, F.hipY - F.shY + 52, 14, PROP2) + rect(CX - 46, F.hipY + 4, 92, 14, 7, PROP2) + rect(CX - 6, F.hipY + 18, 12, GY - F.hipY - 18, 2) + rect(CX - 60, GY - 8, 120, 8, 4);
  const crossPose = (W0, W1, bulge) => t => ({ cx: CX, W: s => { const p = mix(W0(s), W1(s), t); return [p[0] + s * Math.sin(Math.PI * t) * bulge, p[1]]; } });
  const crossBack = (L, Rr) => F => rect(L[0] - 12, 58, 12, GY - 58, 3) + rect(Rr[0], 58, 12, GY - 58, 3) + pulley(L) + pulley(Rr) + cable(L, F.hands[0]) + cable(Rr, F.hands[1]);

  // ---------- exercícios (vista lateral, olhando para a direita) ----------
  // pose(t): t=0 posição inicial, t=1 fim do movimento. ord 'down' = começa descendo (pausa embaixo);
  // ord 'up' = começa fazendo força (pausa na contração). verbs = [ida, volta].
  const FWD = [1, 0], UP = [0, -1], DOWN = [0, 1], BACK = [-1, 0];
  // braço por posições-chave relativas ao ombro (cotovelo E e mão W no início e no fim)
  const armKF = (S, E0, W0, E1, W1, t) => ({ S, E: add(S, mix(E0, E1, t)), W: add(S, mix(W0, W1, t)) });
  const kf2 = (b, E0, W0, E1, W1, t) => [armKF(b.S, E0, W0, E1, W1, t), armKF(far(b.S), E0, W0, E1, W1, t)];
  const mk = (b, nL, fL, nA, fA) => Object.assign(b, { nL, fL: fL || nL, nA, fA: fA || nA });
  const EX = {
    // agachamento com barra nas costas: barra sempre sobre o meio do pé
    agach: { ord: 'down', verbs: ['Desce', 'Sobe'], eq: 'Barra', mus: [['quads', 'glutes'], ['hams', 'lowback']],
      pose: t => {
        const A = [150, AY], mid = A[0] + 10, lean = lerp(3, 40, t), u = dir(180 - lean), v = [-u[1], u[0]];
        const hx = mid - (u[0] * (LTOR + 4) - v[0] * 13), hy = lerp(230, 327, t), b = body([hx, hy], 180 - lean, lerp(0, 24, t));
        const barP = add(add(b.H, u, LTOR + 4), v, -13), W = add(barP, v, 3), E = add(add(barP, u, -34), v, -10);
        b.bar = barP;
        const arm = S => ({ S, E: add(E, sub(S, b.S)), W: add(W, sub(S, b.S)) });
        return mk(b, legTo(b.H, A, FWD), legTo(far(b.H), far(A), FWD), arm(b.S), arm(far(b.S)));
      },
      back: b => plate(b.bar, 34), front: b => `<circle cx="${f(b.bar[0])}" cy="${f(b.bar[1])}" r="5" fill="${GRIP}"/>`, mov: b => b.H },
    // goblet: peso junto ao peito, tronco mais em pé
    goblet: { ord: 'down', verbs: ['Desce', 'Sobe'], eq: 'Kettlebell', mus: [['quads', 'glutes'], ['hams', 'abs']],
      pose: t => {
        const A = [150, AY], lean = lerp(3, 24, t), u = dir(180 - lean), v = [-u[1], u[0]];
        const hb = 162 - (u[0] * (LTOR - 26) + v[0] * 28), hx = lerp(158, hb, t), hy = lerp(230, 330, t), b = body([hx, hy], 180 - lean, lerp(0, 10, t));
        const W = add(add(b.H, u, LTOR - 24), v, 26); b.kb = W;
        return mk(b, legTo(b.H, A, FWD), legTo(far(b.H), far(A), FWD), armTo(b.S, W, [-u[0], -u[1]]), armTo(far(b.S), far(W), [-u[0], -u[1]]));
      },
      front: b => kettle(add(b.kb, [2, -2])), mov: b => b.H },
    // leg press 45°: quadril fixo no banco, pés na plataforma que corre no trilho de 45°
    leg: { ord: 'down', verbs: ['Desce', 'Empurra'], eq: 'Máquina', mus: [['quads'], ['glutes', 'hams']],
      pose: t => {
        const H = [128, 300], r = dir(135), b = body(H, -135, -12), d = lerp(146, 104, t), A = add(H, r, d), pole = dir(-135);
        b.rail = r; b.plat = add(A, r, 13);
        const W = add(H, [14, 10]);
        return mk(b, legTo(H, A, pole, -135), legTo(far(H), far(A), pole, -135), armTo(b.S, W, DOWN), armTo(far(b.S), far(W), DOWN));
      },
      back: b => { const r = b.rail, qq = [-r[1], r[0]], H = b.H, pl = b.plat, u = b.u, v = b.v;
        return rect(40, GY - 8, 256, 8, 4) + bar(add(add(H, r, 40), qq, 30), add(add(H, r, 238), qq, 30), 9) + bar(add(add(H, r, 196), qq, 30), [add(add(H, r, 196), qq, 30)[0], GY - 6], 8) +
          bar(add(add(H, v, -17), u, -12), add(add(H, v, -17), u, 118), 17, PROP2) + bar(add(add(H, u, -17), v, -8), add(add(H, u, -17), v, 46), 14, PROP2) +
          bar(add(H, u, -20), [H[0] + 14, GY - 6], 9) + bar(add(add(pl, r, 8), qq, 4), add(add(pl, r, 40), qq, 14), 26, PROP2) + plate(add(add(pl, r, 30), qq, 38), 24) +
          bar(add(add(pl, r, 3), qq, 34), add(add(pl, r, 3), qq, -34), 9, PLATE); },
      mov: b => b.nL.A },
    // cadeira extensora: estende o joelho, segura no alto, volta devagar
    extensora: { ord: 'up', verbs: ['Estende', 'Desce'], eq: 'Máquina', mus: [['quads'], []],
      pose: t => {
        const H = [100, 286], b = body(H, 188, 0), sh = lerp(-4, 80, t), W = add(H, [12, 16]);
        const L = legAng(H, 90, sh, sh + 90), Lf = legAng(far(H), 90, sh, sh + 90);
        b.sh = sh;
        return mk(b, L, Lf, armTo(b.S, W, add(DOWN, BACK, .3)), armTo(far(b.S), far(W), add(DOWN, BACK, .3)));
      },
      back: b => { const K = b.nL.K, A = b.nL.A, d = nrm(sub(A, K)), fr = [d[1], -d[0]], roll = add(add(A, d, -8), fr, 14);
        return rect(b.H[0] - 22, b.H[1] + 12, 104, 13, 6, PROP2) + bar(add(b.H, [-24, 4]), add(b.H, [-30, -104]), 18, PROP2) + rect(b.H[0] + 22, b.H[1] + 22, 11, GY - b.H[1] - 22, 2) +
          rect(b.H[0] - 32, GY - 8, 150, 8, 4) + `<circle cx="${f(K[0])}" cy="${f(K[1])}" r="11" fill="${PROP2}"/>` + bar(K, roll, 7, PROP2); },
      front: b => { const K = b.nL.K, A = b.nL.A, d = nrm(sub(A, K)), fr = [d[1], -d[0]], roll = add(add(A, d, -8), fr, 14);
        return `<circle cx="${f(roll[0])}" cy="${f(roll[1])}" r="9.5" fill="${PLATE}" stroke="#FFFFFF" stroke-width="1.6"/>`; },
      mov: b => b.nL.A },
    // afundo parado (passada): joelho de trás quase no chão, canela da frente na vertical
    afundo: { ord: 'down', verbs: ['Desce', 'Sobe'], eq: 'Halteres', mus: [['glutes', 'quads'], ['hams']],
      pose: t => {
        const Af = [204, AY], Ab = [64, AY - 13], H = mix([140, 247], [130, 305], t), b = body(H, 180 - lerp(3, 6, t), 0);
        return mk(b, legTo(H, Af, FWD, 90), legTo(far(H), far(Ab), [1, .25], 64), armAng(b.S, 2, 0), armAng(far(b.S), 2, 0));
      },
      back: b => dbSide(add(b.fA.W, [2, 8])), front: b => dbSide(add(b.nA.W, [2, 8])), mov: b => b.H },
    // agachamento búlgaro: o peito do pé de trás fica em cima do banco; desce reto com a perna da frente
    bulgaro: { ord: 'down', verbs: ['Desce', 'Sobe'], eq: 'Banco', mus: [['quads', 'glutes'], ['hams']],
      pose: t => {
        const Af = [200, AY], Ab = [58, GY - 60], H = mix([134, 238], [124, 296], t), b = body(H, 180 - lerp(5, 11, t), 0);
        return mk(b, legTo(H, Af, FWD, 90), legTo(far(H), far(Ab), [1, .35], -80), armAng(b.S, 2, 0), armAng(far(b.S), 2, 0));
      },
      back: b => bench(12, 96, GY - 52) + dbSide(add(b.fA.W, [2, 8])), front: b => dbSide(add(b.nA.W, [2, 8])), mov: b => b.H },
    // agachamento búlgaro com tronco inclinado (foco glúteo): quadril vai para trás e o tronco inclina uns 40° à frente
    bulgaroinc: { ord: 'down', verbs: ['Desce', 'Sobe'], eq: 'Banco', mus: [['glutes', 'quads'], ['hams']],
      pose: t => {
        const Af = [204, AY], Ab = [58, GY - 60], H = mix([130, 240], [114, 300], t), b = body(H, 180 - lerp(22, 42, t), lerp(-6, -14, t));
        return mk(b, legTo(H, Af, FWD, 90), legTo(far(H), far(Ab), [1, .35], -80), armAng(b.S, 2, 0), armAng(far(b.S), 2, 0));
      },
      back: b => bench(12, 96, GY - 52) + dbSide(add(b.fA.W, [2, 8])), front: b => dbSide(add(b.nA.W, [2, 8])), mov: b => b.H },
    // elevação pélvica: costas no banco, sobe o quadril até alinhar com as coxas
    pelvica: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Barra', mus: [['glutes'], ['hams']],
      pose: t => {
        const Sp = [102, 304], H = mix([179, 356], [194, 306], t), tor = ang(sub(Sp, H)), b = body(H, tor, lerp(-34, -26, t));
        const barP = add(H, b.v, 21); b.bar = barP; const W = add(barP, [-2, 1]);
        return mk(b, legTo(H, [266, AY], UP), legTo(far(H), far([266, AY]), UP), armTo(b.S, W, DOWN), armTo(far(b.S), far(W), DOWN));
      },
      back: b => bench(20, 116, 316) + plate(b.bar, 36), front: b => plateRing(b.bar, 36), mov: b => b.H },
    // panturrilha no degrau: ponta do pé na borda, calcanhar desce e sobe
    panturrilha: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Degrau', mus: [['calves'], []], view: [.9, 4],
      pose: t => {
        const ft = lerp(110, 52, t), A = ankleFrom([122, GY - 24], ft, 26, 13), H = add(A, [2, -149]), b = body(H, 180, 0);
        const W = [150, b.S[1] + 82];
        return mk(b, legTo(H, A, FWD, ft), legTo(far(H), far(A), FWD, ft), armTo(b.S, W, DOWN), armTo(far(b.S), far(W), DOWN));
      },
      back: () => rect(112, GY - 24, 92, 24, 4, PROP2) + rect(152, 150, 10, GY - 174, 3), mov: b => b.H },
    // supino reto: barra sobre os ombros → desce até o meio do peito → empurra
    supino: { ord: 'down', verbs: ['Desce', 'Empurra'], eq: 'Barra', mus: [['chest'], ['delt_f', 'triceps']],
      pose: t => {
        const H = [192, 307], b = body(H, -90, 0), [nA, fA] = kf2(b, [2, -48], [3, -96], [14, 18], [24, -24], t);
        b.bar = add(nA.W, [0, -2]);
        return mk(b, legTo(H, [266, AY], UP), legTo(far(H), far([266, AY]), UP), nA, fA);
      },
      back: b => bench(26, 234, 318) + plate(b.bar, 36), front: b => plateRing(b.bar, 36), mov: b => b.nA.W },
    // supino inclinado: banco a ~40°, halteres sobem na vertical sobre a parte alta do peito
    inclinado: { ord: 'down', verbs: ['Desce', 'Empurra'], eq: 'Halteres', mus: [['chest'], ['delt_f', 'triceps']],
      pose: t => {
        const H = [176, 307], b = body(H, -130, 0), [nA, fA] = kf2(b, [8, -47], [14, -94], [8, 22], [22, -20], t);
        return mk(b, legTo(H, [250, AY], UP), legTo(far(H), far([250, AY]), UP), nA, fA);
      },
      back: b => bar(add(add(b.H, b.u, -8), b.v, -17), add(add(b.H, b.u, 126), b.v, -17), 17, PROP2) + bar(add(b.H, [-20, 15]), add(b.H, [34, 15]), 13, PROP2) +
        bar(add(b.H, [-6, 18]), [b.H[0] - 6, GY - 6], 9) + bar(add(add(b.H, b.u, 70), b.v, -20), [b.H[0] - 70, GY - 6], 7) + rect(b.H[0] - 90, GY - 8, 140, 8, 4),
      front: b => dbEnd(b.nA.W), mov: b => b.nA.W },
    // desenvolvimento sentado: halteres na altura das orelhas → braços estendidos
    desenv: { ord: 'down', verbs: ['Desce', 'Empurra'], eq: 'Halteres', mus: [['delt_f', 'delt_s'], ['triceps', 'traps']],
      pose: t => {
        const H = [148, 310], b = body(H, 184, 0), [nA, fA] = kf2(b, [8, -47], [12, -94], [20, 4], [26, -40], t);
        return mk(b, legTo(H, [226, AY], UP), legTo(far(H), far([226, AY]), UP), nA, fA);
      },
      back: b => rect(b.H[0] - 26, b.H[1] + 11, 70, 12, 6, PROP2) + bar(add(b.H, [-26, 4]), add(b.H, [-34, -102]), 17, PROP2) + rect(b.H[0] - 4, b.H[1] + 21, 10, GY - b.H[1] - 21, 2) + rect(b.H[0] - 40, GY - 8, 96, 8, 4),
      front: b => dbEnd(b.nA.W), mov: b => b.nA.W },
    // elevação lateral (de frente): braços sobem pela lateral até a linha do ombro
    lateral: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Halteres', mus: [['delt_s'], ['traps']], frontView: true,
      pose: t => ({ cx: 158, a: lerp(8, 86, t), el: lerp(6, 12, t) }) },
    // tríceps na polia: cotovelos parados junto ao corpo, só o antebraço mexe
    triceps: { ord: 'up', verbs: ['Estende', 'Volta'], eq: 'Polia', mus: [['triceps'], []],
      pose: t => {
        // braço colado ao corpo; o antebraço volta só até 90° (paralelo ao chão), não sobe até o peito
        const A = [140, AY], H = [134, 233], b = body(H, 168, 8), ua = 2, fa = lerp(ua + 90, ua, t);
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), armAng(b.S, ua, fa), armAng(far(b.S), ua, fa));
      },
      back: b => { const p = [b.S[0] + 56, 102]; return rect(p[0] + 22, 86, 11, GY - 86, 3) + bar([p[0] + 26, 92], p, 7, PROP2) + pulley(p) + cable(p, b.nA.W); },
      front: b => { const W = b.nA.W, d = nrm(sub(W, b.nA.E)); return `<path d="M${P(W)} l${f(d[0] * 12 - 3)},${f(d[1] * 12)} M${P(W)} l${f(d[0] * 12 + 4)},${f(d[1] * 12 + 2)}" stroke="${GRIP}" stroke-width="3.4" stroke-linecap="round"/>`; },
      mov: b => b.nA.W },
    // pulldown na máquina (articulada): sentado, as pegadas presas numa alavanca descem em arco até a altura do peito
    pulldownmaq: { ord: 'up', verbs: ['Puxa', 'Volta'], eq: 'Máquina', mus: [['lats'], ['biceps', 'delt_r']],
      pose: t => {
        const H = [128, 310], b = body(H, 188, -4), Pv = [239, 138], R0 = 110, a = lerp(154, 224, t) * Math.PI / 180, W = [Pv[0] + R0 * Math.cos(a), Pv[1] - R0 * Math.sin(a)];
        b.lev = { Pv, W };
        return mk(b, legTo(H, [206, AY], UP), legTo(far(H), far([206, AY]), UP), armTo(b.S, W, [0, 1]), armTo(far(b.S), far(W), [0, 1]));
      },
      back: b => { const { Pv, W } = b.lev, d = nrm(sub(Pv, W)), E = add(Pv, d, 46);
        return rect(b.H[0] - 26, b.H[1] + 10, 72, 12, 6, PROP2) + rect(b.H[0] - 4, b.H[1] + 20, 10, GY - b.H[1] - 20, 2) + `<circle cx="${f(b.nL.K[0] - 4)}" cy="${f(b.nL.K[1] - 20)}" r="11" fill="${PROP2}"/>` +
          rect(236, Pv[1], 12, GY - Pv[1], 3) + rect(196, GY - 8, 80, 8, 4) + bar(W, E, 9, PROP2) + `<circle cx="${f(Pv[0])}" cy="${f(Pv[1])}" r="7" fill="${PLATE}"/>` + bar(add(E, [-3, -10]), add(E, [3, 10]), 14, PLATE); },
      front: b => `<circle cx="${f(b.nA.W[0])}" cy="${f(b.nA.W[1])}" r="6" fill="${GRIP}"/>`, mov: b => b.nA.W },
    // puxada frente: tronco um pouco para trás, barra desce até o alto do peito
    puxada: { ord: 'up', verbs: ['Puxa', 'Volta'], eq: 'Máquina', mus: [['lats'], ['biceps', 'delt_r']],
      pose: t => {
        const H = [128, 310], b = body(H, 196, -6), [nA, fA] = kf2(b, [8, -43], [12, -86], [4, 30], [20, -10], t);
        return mk(b, legTo(H, [206, AY], UP), legTo(far(H), far([206, AY]), UP), nA, fA);
      },
      back: b => { const p = [168, 84]; return rect(b.H[0] - 26, b.H[1] + 10, 72, 12, 6, PROP2) + rect(b.H[0] - 4, b.H[1] + 20, 10, GY - b.H[1] - 20, 2) + rect(252, 76, 12, GY - 76, 3) +
        bar(p, [258, 80], 8, PROP2) + pulley(p) + cable(p, b.nA.W) + `<circle cx="${f(b.nL.K[0] - 4)}" cy="${f(b.nL.K[1] - 20)}" r="11" fill="${PROP2}"/>` + rect(196, GY - 8, 80, 8, 4); },
      front: b => `<circle cx="${f(b.nA.W[0])}" cy="${f(b.nA.W[1])}" r="5" fill="${GRIP}"/>`, mov: b => b.nA.W },
    // remada curvada: tronco a ~50°, barra sobe até a parte baixa da barriga
    remada: { ord: 'up', verbs: ['Puxa', 'Volta'], eq: 'Barra', mus: [['lats', 'uback'], ['biceps', 'delt_r', 'lowback']],
      pose: t => {
        const A = [152, AY], H = [92, 246], b = body(H, 130, 14), W = mix(add(b.S, [2, 96]), add(add(H, b.u, 28), b.v, 25), t), pole = [-b.v[0], -b.v[1]];
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), armTo(b.S, W, pole), armTo(far(b.S), far(W), pole));
      },
      back: b => plate(b.nA.W, 34), front: b => plateRing(b.nA.W, 34), mov: b => b.nA.W },
    // remada baixa: sentado, joelhos quase estendidos, puxa o triângulo até o abdômen
    baixa: { ord: 'up', verbs: ['Puxa', 'Volta'], eq: 'Polia', mus: [['lats', 'uback'], ['biceps', 'delt_r']],
      pose: t => {
        const H = [98, 328], b = body(H, lerp(172, 182, t), 0), A = [242, 318], W = mix(add(b.S, [94, 18]), add(add(H, b.u, 36), b.v, 24), t), pole = BACK;
        return mk(b, legTo(H, A, UP, 172), legTo(far(H), far(A), UP, 172), armTo(b.S, W, pole), armTo(far(b.S), far(W), pole));
      },
      back: b => { const p = [272, 334]; return rect(30, 340, 150, 12, 6, PROP2) + rect(60, 350, 10, GY - 350, 2) + rect(150, 350, 10, GY - 350, 2) + bar([262, 296], [262, 344], 9, PROP2) +
        rect(280, 236, 12, GY - 236, 3) + pulley(p) + cable(p, b.nA.W) + rect(236, GY - 8, 60, 8, 4); },
      front: b => `<path d="M${f(b.nA.W[0] - 2)},${f(b.nA.W[1] - 10)} l9,10 l-9,10" fill="none" stroke="${GRIP}" stroke-width="3.2" stroke-linejoin="round"/>`, mov: b => b.nA.W },
    // serrote: joelho e mão de apoio no banco, tronco paralelo ao chão, halter sobe até o quadril
    serrote: { ord: 'up', verbs: ['Puxa', 'Volta'], eq: 'Halter', mus: [['lats'], ['biceps', 'delt_r']],
      pose: t => {
        const H = [128, 232], b = body(H, 84, 6), W = mix(add(b.S, [1, 96]), add(add(H, b.u, 42), b.v, 14), t), pole = [-b.v[0], -b.v[1]];
        const sup = [far(b.S)[0] - 2, 309];
        return mk(b, legTo(H, [104, AY], FWD), legAng(far(H), 0, -88, -96), armTo(b.S, W, pole), armTo(far(b.S), sup, BACK));
      },
      back: () => bench(40, 252, 318), front: b => dbSide(add(b.nA.W, [0, 6])), mov: b => b.nA.W },
    // face pull: polia na altura do rosto, puxa a corda na direção da testa com cotovelos altos
    facepull: { ord: 'up', verbs: ['Puxa', 'Volta'], eq: 'Polia', mus: [['delt_r'], ['traps', 'uback']],
      pose: t => {
        const A = [128, AY], H = [130, 231], b = body(H, 184, 0), [nA, fA] = kf2(b, [46, -8], [94, -16], [-6, -8], [26, -40], t);
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), nA, fA);
      },
      back: b => { const p = [286, 112]; return rect(292, 70, 10, GY - 70, 3) + pulley(p) + cable(p, b.nA.W); },
      front: b => `<path d="M${P(b.nA.W)} l-2,10 M${P(b.nA.W)} l-2,-10" stroke="${GRIP}" stroke-width="3.2" stroke-linecap="round"/>`, mov: b => b.nA.W },
    // rosca direta: cotovelos parados ao lado do corpo
    rosca: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Barra', mus: [['biceps'], ['forearm']],
      pose: t => {
        const A = [150, AY], H = [152, 231], b = body(H, 180, 0), ua = lerp(2, 12, t), fa = lerp(4, 160, t);
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), armAng(b.S, ua, fa), armAng(far(b.S), ua, fa));
      },
      back: b => plate(b.nA.W, 26), front: b => plateRing(b.nA.W, 26), mov: b => b.nA.W },
    // flexão: corpo reto apoiado nos dedos, desce até o peito quase encostar
    flexao: { ord: 'down', verbs: ['Desce', 'Empurra'], eq: 'Peso do corpo', mus: [['chest'], ['triceps', 'delt_f', 'abs']], view: [.84, -14],
      pose: t => {
        const T = [34, GY - 2];
        const at = beta => { const d = [Math.cos(R(beta)), -Math.sin(R(beta))], fd = dir(ang(d) - 90), A = sub(T, [fd[0] * 30, fd[1] * 30]), H = add(A, d, LEG); return { b: body(H, ang(d), 8), A }; };
        const solve = y => { let lo = 0, hi = 45, o = at(0); for (let i = 0; i < 28; i++) { const m = (lo + hi) / 2; o = at(m); if (o.b.S[1] > y) lo = m; else hi = m; } return o; };
        const top = solve(GY - 6 - 92), hand = [top.b.S[0] + 4, GY - 6], { b, A } = solve(lerp(GY - 6 - 92, GY - 6 - 30, t)), ft = ang(sub(T, A));
        return mk(b, legTo(b.H, A, UP, ft), legTo(far(b.H), far(A), UP, ft), armTo(b.S, hand, [-.6, -.8], .92), armTo(far(b.S), far(hand), [-.6, -.8], .92));
      },
      mov: b => b.S },
    // stiff: joelhos levemente dobrados e fixos, quadril vai para trás, halteres descem rente às pernas
    stiff: { ord: 'down', verbs: ['Desce', 'Sobe'], eq: 'Halteres', mus: [['hams', 'glutes'], ['lowback']],
      pose: t => {
        const A = [156, AY], H = mix([156, 231], [84, 252], t), b = body(H, lerp(180, 104, t), lerp(0, 22, t));
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), armAng(b.S, 0, 0), armAng(far(b.S), 0, 0));
      },
      front: b => dbEnd(add(b.nA.W, [0, 6])), mov: b => b.nA.W },
    // levantamento terra convencional: a barra sai do chão (joelhos dobrados, costas retas), passa rente às canelas
    // e o quadril vai à frente até ficar totalmente em pé; primeiro estica o joelho, depois o quadril
    terra: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Barra', mus: [['glutes', 'hams'], ['quads', 'lowback']],
      pose: t => {
        const A = [176, AY], k = t < .45 ? ease(t / .45) * .45 : .45 + ease((t - .45) / .55) * .55;
        const P = (a, m, z) => k < .45 ? lerp(a, m, k / .45) : lerp(m, z, (k - .45) / .55);
        const H = [P(98, 104, 168), P(292, 276, 231)], b = body(H, P(112, 128, 180), P(-34, -18, 0));
        const W = [P(181, 175, 170), b.S[1] + 96]; // braços sempre esticados: a barra fica embaixo dos ombros
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), armTo(b.S, W, BACK), armTo(far(b.S), far(W), BACK));
      },
      back: b => plate(add(b.nA.W, [0, 6]), 34), front: b => plateRing(add(b.nA.W, [0, 6]), 34), mov: b => b.nA.W },
    // dead bug: braços para cima, quadril e joelhos a 90°; estende braço e perna opostos (alterna)
    deadbug: { ord: 'up', verbs: ['Estende', 'Volta'], eq: 'Peso do corpo', mus: [['abs'], ['obl']], view: [.72, -28], alt: true,
      pose: (t, side) => {
        const H = [186, 372], b = body(H, -90, -6), table = H2 => legAng(H2, 180, 90, 180), ext = H2 => legAng(H2, lerp(180, 100, t), lerp(90, 98, t), lerp(180, 170, t));
        const up = S2 => armAng(S2, 180, 180), over = S2 => armAng(S2, lerp(180, 262, t), lerp(180, 264, t));
        return side ? mk(b, table(H), ext(far(H)), over(b.S), up(far(b.S))) : mk(b, ext(H), table(far(H)), up(b.S), over(far(b.S)));
      },
      back: () => rect(-60, GY - 8, 410, 8, 4), mov: b => b.nL.A },
    // prancha: antebraços no chão, cotovelo embaixo do ombro, corpo em linha reta
    prancha: { iso: true, eq: 'Peso do corpo', mus: [['abs'], ['obl', 'delt_f']], view: [.86, -4],
      pose: () => {
        const E = [234, GY - 9], S = add(E, [0, -LU]), A = [0, 368], dx = Math.sqrt((LEG + LTOR) ** 2 - (A[1] - S[1]) ** 2); A[0] = S[0] - dx;
        const d = nrm(sub(S, A)), H = add(A, d, LEG), b = body(H, ang(d), 6);
        b.S = S; return mk(b, legTo(H, A, UP, ang(sub([A[0] + 10, GY], A))), null, armAng(S, 0, 90), armAng(far(S), 0, 90));
      },
      front: b => { const a = add(b.nL.A, [0, -22]), s = add(b.S, [0, -28]); return `<path d="M${P(a)} L${P(s)}" stroke="${RED}" stroke-width="2" stroke-dasharray="5 5" stroke-linecap="round" opacity=".85"/>`; }, mov: b => b.H },
    // ---------- variações em máquina, polia e abdominais ----------
    // crossover (de frente): polias alta, média e baixa; as mãos se encontram na frente do corpo
    crossalta: { ord: 'up', verbs: ['Fecha', 'Abre'], eq: 'Polia', mus: [['chest'], ['delt_f']], frontView: true, noDb: true,
      pose: crossPose(s => [CX + s * 98, 96], s => [CX + s * 9, 222], 12), back: crossBack([30, 74], [286, 74]), front: grips, fmov: F => F.hands[1] },
    crossmedia: { ord: 'up', verbs: ['Fecha', 'Abre'], eq: 'Polia', mus: [['chest'], ['delt_f']], frontView: true, noDb: true,
      pose: crossPose(s => [CX + s * 104, 154], s => [CX + s * 9, 190], 6), back: crossBack([30, 150], [286, 150]), front: grips, fmov: F => F.hands[1] },
    crossbaixa: { ord: 'up', verbs: ['Fecha', 'Abre'], eq: 'Polia', mus: [['chest'], ['delt_f']], frontView: true, noDb: true,
      pose: crossPose(s => [CX + s * 78, 262], s => [CX + s * 10, 160], 12), back: crossBack([30, 360], [286, 360]), front: grips, fmov: F => F.hands[1] },
    // voador (peck deck), sentado de frente: as pegadas se fecham na frente do peito
    voador: { ord: 'up', verbs: ['Fecha', 'Abre'], eq: 'Máquina', mus: [['chest'], ['delt_f']], frontView: true, noDb: true,
      pose: t => ({ cx: CX, seat: true, kx: 22, W: (s, S) => [CX + s * lerp(100, 14, t), S[1] + lerp(-4, 12, t)] }),
      back: F => seatFront(F) + F.hands.map((h, i) => bar([CX + (i ? 1 : -1) * 40, F.shY - 62], h, 7, PROP2)).join(''), front: grips, fmov: F => F.hands[1] },
    // crucifixo inverso na máquina: o caminho do voador ao contrário (abre os braços)
    voadorinv: { ord: 'up', verbs: ['Abre', 'Fecha'], eq: 'Máquina', mus: [['delt_r'], ['uback', 'traps']], frontView: true, noDb: true,
      pose: t => ({ cx: CX, seat: true, kx: 22, W: (s, S) => [CX + s * lerp(14, 100, t), S[1] + lerp(12, -4, t)] }),
      back: F => seatFront(F) + F.hands.map((h, i) => bar([CX + (i ? 1 : -1) * 40, F.shY - 62], h, 7, PROP2)).join(''), front: grips, fmov: F => F.hands[1] },
    // elevação lateral na polia, unilateral (de frente): polia baixa do lado oposto, o cabo passa na frente do corpo;
    // o braço de fora sobe pela lateral até a linha do ombro e a outra mão segura a máquina
    lateralpolia: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Polia', mus: [['delt_s'], ['traps']], frontView: true, noDb: true,
      pose: t => ({ cx: 146, W: s => s > 0 ? { a: lerp(-12, 86, t), el: lerp(10, 12, t) } : [34, 222] }),
      back: F => rect(18, 120, 12, GY - 120, 3) + pulley([30, 366]) + cable([30, 366], F.hands[1]),
      front: F => `<circle cx="${f(F.hands[1][0])}" cy="${f(F.hands[1][1])}" r="5.5" fill="${GRIP}"/>`, fmov: F => F.hands[1] },
    // cadeira abdutora e adutora (de frente, sentado): os joelhos abrem ou fecham contra as almofadas
    abdutora: { ord: 'up', verbs: ['Abre', 'Fecha'], eq: 'Máquina', mus: [['glutes'], []], frontView: true, noDb: true,
      pose: t => ({ cx: CX, seat: true, kx: lerp(20, 62, t), a: 16, el: 6 }), back: F => seatFront(F),
      front: F => [-1, 1].map(s => rect(CX + s * (F.kx + 13) - 6, F.hipY - 2, 12, 40, 6, PROP2)).join(''), fmov: F => [CX + F.kx, F.hipY + 16] },
    adutora: { ord: 'up', verbs: ['Fecha', 'Abre'], eq: 'Máquina', mus: [['adductors'], []], frontView: true, noDb: true,
      pose: t => ({ cx: CX, seat: true, kx: lerp(62, 22, t), a: 16, el: 6 }), back: F => seatFront(F),
      front: F => [-1, 1].map(s => rect(CX + s * (F.kx - 12) - 6, F.hipY - 2, 12, 40, 6, PROP2)).join(''), fmov: F => [CX + F.kx, F.hipY + 16] },
    // supino reto na máquina: sentado, encosto, empurra as pegadas para a frente na linha do peito
    supmaq: { ord: 'up', verbs: ['Empurra', 'Volta'], eq: 'Máquina', mus: [['chest'], ['delt_f', 'triceps']],
      pose: t => { const H = [112, 310], b = body(H, 184, 0), [nA, fA] = kf2(b, [-34, 30], [8, 28], [48, 22], [94, 26], t);
        return mk(b, legTo(H, [190, AY], UP), legTo(far(H), far([190, AY]), UP), nA, fA); },
      back: b => seatBack(b) + stack() + bar([262, 96], b.nA.W, 8, PROP2), front: b => grip(b.nA.W), mov: b => b.nA.W },
    // supino inclinado na máquina: empurra para cima e para a frente
    supmaqinc: { ord: 'up', verbs: ['Empurra', 'Volta'], eq: 'Máquina', mus: [['chest'], ['delt_f', 'triceps']],
      pose: t => { const H = [112, 310], b = body(H, 186, 0), [nA, fA] = kf2(b, [-34, 24], [6, 20], [34, -16], [70, -50], t);
        return mk(b, legTo(H, [190, AY], UP), legTo(far(H), far([190, AY]), UP), nA, fA); },
      back: b => seatBack(b) + stack() + bar([262, 96], b.nA.W, 8, PROP2), front: b => grip(b.nA.W), mov: b => b.nA.W },
    // remada na máquina: peito no apoio, puxa as pegadas até a linha do abdômen
    remadamaq: { ord: 'up', verbs: ['Puxa', 'Volta'], eq: 'Máquina', mus: [['lats', 'uback'], ['biceps', 'delt_r']],
      pose: t => { const H = [112, 310], b = body(H, 176, 0), [nA, fA] = kf2(b, [48, 28], [92, 32], [-40, 26], [4, 32], t);
        return mk(b, legTo(H, [190, AY], UP), legTo(far(H), far([190, AY]), UP), nA, fA); },
      back: b => { const S = b.S; return rect(b.H[0] - 26, b.H[1] + 11, 70, 12, 6, PROP2) + rect(b.H[0] - 4, b.H[1] + 21, 10, GY - b.H[1] - 21, 2) + rect(b.H[0] - 40, GY - 8, 250, 8, 4) +
        bar([S[0] + 34, S[1] + 12], [S[0] + 36, S[1] + 76], 16, PROP2) + bar([S[0] + 40, S[1] + 70], [S[0] + 52, GY - 6], 8) + stack() + bar([262, 96], b.nA.W, 8, PROP2); },
      front: b => grip(b.nA.W), mov: b => b.nA.W },
    // tríceps testa: deitado, braço parado, só o cotovelo dobra e a barra vem até a testa
    testa: { ord: 'down', verbs: ['Desce', 'Estende'], eq: 'Barra', mus: [['triceps'], []],
      pose: t => { const H = [192, 307], b = body(H, -90, 0), ua = 192, fa = lerp(190, 296, t);
        return mk(b, legTo(H, [266, AY], UP), legTo(far(H), far([266, AY]), UP), armAng(b.S, ua, fa), armAng(far(b.S), ua, fa)); },
      back: b => bench(26, 234, 318) + plate(b.nA.W, 24), front: b => plateRing(b.nA.W, 24), mov: b => b.nA.W },
    // tríceps francês: sentado, as duas mãos seguram o halter pela anilha de cima; cotovelos apontando para o alto,
    // só o antebraço desce e o halter (pendurado) passa atrás da cabeça
    frances: { ord: 'down', verbs: ['Desce', 'Estende'], eq: 'Halter', mus: [['triceps'], []],
      pose: t => { const H = [148, 310], b = body(H, 182, 0), ua = 176, fa = lerp(178, 302, t);
        return mk(b, legTo(H, [226, AY], UP), legTo(far(H), far([226, AY]), UP), armAng(b.S, ua, fa), armAng(far(b.S), ua, fa)); },
      back: b => rect(b.H[0] - 26, b.H[1] + 11, 70, 12, 6, PROP2) + bar(add(b.H, [-26, 4]), add(b.H, [-29, -58]), 17, PROP2) + rect(b.H[0] - 4, b.H[1] + 21, 10, GY - b.H[1] - 21, 2) + rect(b.H[0] - 40, GY - 8, 96, 8, 4) +
        dbSide(add(b.nA.W, [0, 17]), [0, 1]),
      mov: b => b.nA.W },
    // tríceps coice: tronco inclinado com apoio no banco, braço colado ao corpo, estende o cotovelo para trás
    coicetri: { ord: 'up', verbs: ['Estende', 'Volta'], eq: 'Halter', mus: [['triceps'], []],
      pose: t => { const H = [128, 232], b = body(H, 84, 6), ua = -98, fa = lerp(-4, -96, t), sup = [far(b.S)[0] - 2, 309];
        return mk(b, legTo(H, [104, AY], FWD), legAng(far(H), 0, -88, -96), armAng(b.S, ua, fa), armTo(far(b.S), sup, BACK)); },
      back: () => bench(40, 252, 318), front: b => { const d = nrm(sub(b.nA.W, b.nA.E)); return dbSide(add(b.nA.W, d, 4), [-d[1], d[0]]); }, mov: b => b.nA.W },
    // rosca Scott: braço apoiado no banco inclinado, só o antebraço sobe
    scott: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Banco Scott', mus: [['biceps'], ['forearm']],
      pose: t => { const H = [112, 312], b = body(H, 166, -4), ua = 42, fa = lerp(46, 168, t);
        return mk(b, legTo(H, [192, AY], UP), legTo(far(H), far([192, AY]), UP), armAng(b.S, ua, fa), armAng(far(b.S), ua, fa)); },
      back: b => { const S = b.S, d = dir(42), n = [-d[1], d[0]], p1 = add(add(S, d, 2), n, 13), p2 = add(add(S, d, 60), n, 13);
        return rect(b.H[0] - 26, b.H[1] + 11, 70, 12, 6, PROP2) + rect(b.H[0] - 4, b.H[1] + 21, 10, GY - b.H[1] - 21, 2) + rect(b.H[0] - 40, GY - 8, 200, 8, 4) +
          bar(p1, p2, 18, PROP2) + bar(add(p2, n, 6), [p2[0] - 6, GY - 6], 9) + plate(b.nA.W, 24); },
      front: b => plateRing(b.nA.W, 24), mov: b => b.nA.W },
    // elevação frontal: braços esticados sobem pela frente até a linha do ombro
    frontal: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Halteres', mus: [['delt_f'], ['delt_s', 'chest']],
      pose: t => { const A = [150, AY], H = [152, 231], b = body(H, 180, 0), ua = lerp(8, 92, t), fa = ua + 6;
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), armAng(b.S, ua, fa), armAng(far(b.S), ua, fa)); },
      front: b => dbEnd(add(b.nA.W, nrm(sub(b.nA.W, b.nA.E)), 4)), mov: b => b.nA.W },
    // encolhimento: só os ombros sobem em direção às orelhas
    encolh: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Halteres', mus: [['traps'], []],
      pose: t => { const A = [150, AY], H = [152, 231], b = body(H, 180, 0); b.S = add(b.S, [0, -lerp(0, 12, t)]);
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), armAng(b.S, 2, 0), armAng(far(b.S), 2, 0)); },
      back: b => dbSide(add(b.fA.W, [2, 8])), front: b => dbSide(add(b.nA.W, [2, 8])), mov: b => b.S },
    // pulldown com braços estendidos: polia alta à frente, braços quase retos descem até as coxas
    pulldownreto: { ord: 'up', verbs: ['Puxa', 'Volta'], eq: 'Polia', mus: [['lats'], ['triceps', 'delt_r']],
      // quadril para trás, joelhos dobrados, tronco inclinado ~40°; braços retos saem da linha do tronco (mãos acima da cabeça) e descem até as coxas
      pose: t => { const A = [118, AY], H = [84, 240], b = body(H, lerp(137, 145, t), 10), ua = lerp(122, -6, t), fa = ua + lerp(6, 3, t);
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), armAng(b.S, ua, fa), armAng(far(b.S), ua, fa)); },
      back: b => { const p = [276, 56]; return rect(284, 44, 12, GY - 44, 3) + rect(262, GY - 8, 46, 8, 4) + pulley(p) + cable(p, b.nA.W); }, front: b => grip(b.nA.W, true), mov: b => b.nA.W },
    // glúteo na polia (coice): tronco inclinado ~34°, a perna começa com o joelho a 90° (canela para trás) e vai para trás e para cima até quase esticar
    coicepolia: { ord: 'up', verbs: ['Leva', 'Volta'], eq: 'Polia', mus: [['glutes'], ['hams']],
      pose: t => { const A = [146, AY], H = [140, 233], b = body(H, 146, -4), th = lerp(8, -46, t), sh = lerp(-82, -54, t), hold = [236, 204];
        return mk(b, legAng(H, th, sh, sh + 90), legTo(far(H), far(A), FWD), armTo(b.S, hold, DOWN), armTo(far(b.S), far(hold), DOWN)); },
      back: b => { const p = [236, GY - 18]; return rect(244, 120, 12, GY - 120, 3) + pulley(p) + cable(p, add(b.nL.A, [2, -4])); },
      front: b => `<circle cx="${f(b.nL.A[0])}" cy="${f(b.nL.A[1] - 4)}" r="7.5" fill="none" stroke="${GRIP}" stroke-width="3"/>`, mov: b => b.nL.A },
    // glúteo na polia com tronco inclinado (amplitude total): o joelho sobe até perto do quadril e a perna chuta para trás e para cima
    coicepoliainc: { ord: 'up', verbs: ['Chuta', 'Volta'], eq: 'Polia', mus: [['glutes'], ['hams']],
      pose: t => { const A = [128, AY], H = [124, 234], b = body(H, 140, 2), th = lerp(78, -42, t), sh = lerp(-8, -58, Math.pow(t, .6)), hold = [240, 210];
        return mk(b, legAng(H, th, sh, sh + 90), legTo(far(H), far(A), FWD), armTo(b.S, hold, DOWN), armTo(far(b.S), far(hold), DOWN)); },
      back: b => { const p = [236, GY - 18]; return rect(244, 120, 12, GY - 120, 3) + pulley(p) + cable(p, add(b.nL.A, [2, -4])); },
      front: b => `<circle cx="${f(b.nL.A[0])}" cy="${f(b.nL.A[1] - 4)}" r="7.5" fill="none" stroke="${GRIP}" stroke-width="3"/>`, mov: b => b.nL.A },
    // mesa flexora: deitado de bruços, o rolo atrás do tornozelo sobe em direção ao glúteo
    flexora: { ord: 'up', verbs: ['Flexiona', 'Volta'], eq: 'Máquina', mus: [['hams'], ['calves']], view: [.82, 8],
      pose: t => { const H = [150, 290], b = body(H, 90, 8), sh = lerp(-92, -204, t), L = H2 => legAng(H2, -90, sh, sh + 90), W = add(b.S, [40, 46]); // braços descem ao lado do banco e seguram a pegada embaixo, à frente do ombro
        return mk(b, L(H), L(far(H)), armTo(b.S, W, [-.6, 1]), armTo(far(b.S), far(W), [-.6, 1])); },
      back: b => { const K = b.nL.K, A = b.nL.A, d = nrm(sub(A, K)), n = [-d[1], d[0]], roll = add(add(A, d, -8), n, 14);
        return bench(36, 286, 314) + `<circle cx="${f(K[0])}" cy="${f(K[1])}" r="11" fill="${PROP2}"/>` + bar(K, roll, 7, PROP2); },
      front: b => { const K = b.nL.K, A = b.nL.A, d = nrm(sub(A, K)), n = [-d[1], d[0]], roll = add(add(A, d, -8), n, 14); return `<circle cx="${f(roll[0])}" cy="${f(roll[1])}" r="9.5" fill="${PLATE}" stroke="#FFFFFF" stroke-width="1.6"/>`; },
      mov: b => b.nL.A },
    // cadeira flexora: sentado, o rolo atrás do tornozelo desce e vai para trás
    cadflexora: { ord: 'up', verbs: ['Flexiona', 'Volta'], eq: 'Máquina', mus: [['hams'], ['calves']],
      pose: t => { const H = [100, 286], b = body(H, 190, 0), sh = lerp(80, -16, t), W = add(H, [12, 16]);
        const L = legAng(H, 90, sh, sh + 90), Lf = legAng(far(H), 90, sh, sh + 90);
        return mk(b, L, Lf, armTo(b.S, W, add(DOWN, BACK, .3)), armTo(far(b.S), far(W), add(DOWN, BACK, .3))); },
      back: b => { const K = b.nL.K, A = b.nL.A, d = nrm(sub(A, K)), n = [-d[1], d[0]], roll = add(add(A, d, -8), n, 14);
        return rect(b.H[0] - 22, b.H[1] + 12, 104, 13, 6, PROP2) + bar(add(b.H, [-24, 4]), add(b.H, [-30, -104]), 18, PROP2) + rect(b.H[0] + 22, b.H[1] + 22, 11, GY - b.H[1] - 22, 2) +
          rect(b.H[0] - 32, GY - 8, 150, 8, 4) + `<circle cx="${f(K[0])}" cy="${f(K[1])}" r="11" fill="${PROP2}"/>` + bar(K, roll, 7, PROP2); },
      front: b => { const K = b.nL.K, A = b.nL.A, d = nrm(sub(A, K)), n = [-d[1], d[0]], roll = add(add(A, d, -8), n, 14);
        return bar(add(K, [-44, -17]), add(K, [-8, -17]), 12, PROP2) + `<circle cx="${f(roll[0])}" cy="${f(roll[1])}" r="9.5" fill="${PLATE}" stroke="#FFFFFF" stroke-width="1.6"/>`; },
      mov: b => b.nL.A },
    // abdominal no chão: joelhos dobrados, só a parte de cima das costas sai do chão
    crunch: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Peso do corpo', mus: [['abs'], ['obl']],
      pose: t => { const H = [166, 372], tor = lerp(-92, -122, t), b = body(H, tor, lerp(-4, -18, t)), A = [258, AY], ua = tor + 194;
        return mk(b, legTo(H, A, UP, 90), legTo(far(H), far(A), UP, 90), armAng(b.S, ua, ua), armAng(far(b.S), ua, ua)); },
      back: () => rect(18, GY - 6, 282, 6, 3, PROP2), mov: b => b.S },
    // abdominal com anilha: anilha deitada no peito, braços cruzados segurando; o tronco enrola e sobe até perto de 45°
    crunchanilha: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Anilha', mus: [['abs'], ['obl']],
      pose: t => { const H = [166, 372], tor = lerp(-92, -138, t), b = body(H, tor, lerp(-6, -30, t)), A = [258, AY];
        const C = add(add(H, b.u, LTOR * .66), b.v, 16), W = add(C, b.v, 9);
        return mk(b, legTo(H, A, UP, 90), legTo(far(H), far(A), UP, 90), armTo(b.S, W, add([-b.u[0], -b.u[1]], b.v, .5)), armTo(far(b.S), far(W), add([-b.u[0], -b.u[1]], b.v, .5))); },
      back: () => rect(18, GY - 6, 282, 6, 3, PROP2),
      front: b => { if (NOEQ) return ''; const C = add(add(b.H, b.u, LTOR * .64), b.v, 17); return bar(add(C, b.u, -23), add(C, b.u, 23), 11, PLATE) + bar(add(C, b.u, -6), add(C, b.u, 6), 11, GRIP); },
      mov: b => b.S },
    // abdominal infra: deitado, as pernas esticadas sobem até apontar para o teto
    legraise: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Peso do corpo', mus: [['abs'], ['obl']], view: [.8, -8],
      pose: t => { const H = [150, 372], b = body(H, -90, -4), th = lerp(100, 176, t);
        return mk(b, legAng(H, th, th, th + 90), legAng(far(H), th, th, th + 90), armAng(b.S, 86, 86), armAng(far(b.S), 86, 86)); },
      back: () => rect(-60, GY - 6, 410, 6, 3, PROP2), mov: b => b.nL.A },
    // elevação de pernas na barra fixa: pendurado, as pernas esticadas sobem até a linha do quadril
    infrabarra: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Barra fixa', mus: [['abs'], ['obl']], view: [.8, 0],
      pose: t => { const H = [150, 214], b = body(H, lerp(180, 187, t), 0), th = lerp(2, 92, t), BP = [154, 28];
        return mk(b, legAng(H, th, th, th + 90), legAng(far(H), th, th, th + 90), armTo(b.S, BP, FWD), armTo(far(b.S), far(BP), FWD)); },
      back: b => rect(250, 20, 12, GY - 20, 3) + bar([256, 26], [154, 26], 8, PROP2), front: () => `<circle cx="154" cy="28" r="6" fill="${GRIP}"/>`, mov: b => b.nL.A },
    // elevação de joelhos no apoio (paralela): antebraços apoiados, joelhos sobem até o quadril
    infracad: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Paralela', mus: [['abs'], ['obl']], view: [.9, 0],
      pose: t => { const H = [146, 204], b = body(H, 180, 0), th = lerp(4, 100, t), sh = lerp(2, 10, t);
        return mk(b, legAng(H, th, sh, sh + 90), legAng(far(H), th, sh, sh + 90), armAng(b.S, 2, 92), armAng(far(b.S), 2, 92)); },
      back: b => bar(add(b.H, [-24, 6]), add(b.H, [-26, -96]), 16, PROP2) + bar(add(b.nA.E, [-4, 10]), add(b.nA.W, [10, 10]), 12, PROP2) + rect(b.H[0] - 52, 70, 12, GY - 70, 3) + rect(b.H[0] - 70, GY - 8, 120, 8, 4),
      mov: b => b.nL.K },
    // abdominal na máquina: sentado, segura as pegadas junto ao peito e enrola o tronco
    abdmaq: { ord: 'up', verbs: ['Flexiona', 'Volta'], eq: 'Máquina', mus: [['abs'], ['obl']],
      pose: t => { const H = [128, 312], b = body(H, lerp(184, 146, t), lerp(0, -14, t)), W = add(add(b.S, b.u, 12), b.v, 26), pole = add(b.v, b.u, -1);
        return mk(b, legTo(H, [206, AY], UP), legTo(far(H), far([206, AY]), UP), armTo(b.S, W, pole), armTo(far(b.S), far(W), pole)); },
      back: b => rect(b.H[0] - 26, b.H[1] + 11, 70, 12, 6, PROP2) + rect(b.H[0] - 4, b.H[1] + 21, 10, GY - b.H[1] - 21, 2) + rect(b.H[0] - 40, GY - 8, 140, 8, 4) +
        bar(add(add(b.H, b.v, -20), b.u, 14), add(add(b.H, b.v, -20), b.u, 96), 16, PROP2) + rect(40, 120, 12, GY - 120, 3) + bar([46, 128], add(b.nA.W, [-6, -6]), 7, PROP2),
      front: b => grip(b.nA.W), mov: b => b.S },
    // tríceps pulley unilateral: um braço só; o cotovelo fica colado ao corpo e a outra mão na cintura
    tricepsunil: { ord: 'up', verbs: ['Estende', 'Volta'], eq: 'Polia', mus: [['triceps'], []], grip: 'unil',
      pose: t => { const A = [140, AY], H = [134, 233], b = body(H, 170, 6), ua = 2, fa = lerp(ua + 90, ua, t), cint = add(far(H), [12, -6]);
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), armAng(b.S, ua, fa), armTo(far(b.S), cint, BACK)); },
      back: b => { const p = [b.S[0] + 56, 102]; return rect(p[0] + 22, 86, 11, GY - 86, 3) + bar([p[0] + 26, 92], p, 7, PROP2) + pulley(p) + cable(p, b.nA.W); },
      front: b => { const W = b.nA.W, d = nrm(sub(W, b.nA.E)), n = [-d[1], d[0]]; return `<path d="M${P(add(add(W, d, 3), n, -8))} L${P(add(add(W, d, 3), n, 8))}" stroke="${GRIP}" stroke-width="5" stroke-linecap="round"/>`; },
      mov: b => b.nA.W },
    // abdominal no banco inclinado: pés presos no alto do banco, o tronco sai deitado e sobe até ficar de frente para os joelhos
    abdinclinado: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Banco', mus: [['abs'], ['obl']],
      pose: t => { const H = [156, 270], tor = lerp(-70, -192, t), b = body(H, tor, lerp(-2, -16, t)), A = [252, 252], W = add(add(b.S, b.u, -16), b.v, 18), pole = add(b.v, b.u, -1);
        return mk(b, legTo(H, A, UP, 112), legTo(far(H), far(A), UP, 112), armTo(b.S, W, pole), armTo(far(b.S), far(W), pole)); },
      back: () => bar([30, 333], [250, 254], 13, PROP2) + rect(62, 330, 10, GY - 330, 2) + rect(214, 270, 10, GY - 270, 2) + bar([240, 258], [262, 226], 8, PROP2) + rect(40, GY - 8, 200, 8, 4),
      front: () => `<circle cx="262" cy="230" r="9.5" fill="${PLATE}" stroke="#FFFFFF" stroke-width="1.6"/>`, mov: b => b.S },
    // abdutora reta na polia (de frente): de lado para a polia baixa, a perna de fora abre para o lado; a mão de dentro segura a máquina
    abdutorapolia: { ord: 'up', verbs: ['Abre', 'Volta'], eq: 'Polia', mus: [['glutes'], []], frontView: true, noDb: true,
      pose: t => ({ cx: 158, abd: { s: 1, a: lerp(2, 40, t) }, W: (s, S) => s < 0 ? [34, 206] : [158 + 25, 200] }),
      back: () => rect(18, 120, 12, GY - 120, 3) + bar([30, 206], [44, 206], 6, PROP2) + pulley([30, 368]),
      front: F => cable([30, 368], F.ank) + `<circle cx="${f(F.ank[0])}" cy="${f(F.ank[1])}" r="7" fill="none" stroke="${GRIP}" stroke-width="3"/>`, fmov: F => F.ank },
    // abdutora inclinada na polia: o corpo inclina em direção à máquina (braço de dentro esticado) e a perna de fora abre
    abdutorapoliainc: { ord: 'up', verbs: ['Abre', 'Volta'], eq: 'Polia', mus: [['glutes'], []], frontView: true, noDb: true,
      pose: t => ({ cx: 172, lean: -15, piv: [156, AY], abd: { s: 1, a: lerp(4, 42, t) }, W: (s, S) => s < 0 ? [32, 178] : [172 + 25, 200] }),
      back: () => rect(18, 100, 12, GY - 100, 3) + bar([30, 178], [42, 178], 6, PROP2) + pulley([30, 368]),
      front: F => cable([30, 368], F.ank) + `<circle cx="${f(F.ank[0])}" cy="${f(F.ank[1])}" r="7" fill="none" stroke="${GRIP}" stroke-width="3"/>`, fmov: F => F.ank },
    // agachamento no V-squat: ombros nas almofadas, costas no encosto; o corpo desce na diagonal do trilho com os pés na plataforma
    vsquat: { ord: 'down', verbs: ['Desce', 'Sobe'], eq: 'Máquina', mus: [['quads', 'glutes'], ['hams']],
      pose: t => { const r = nrm([-0.3, 1]), H = add([158, 224], r, lerp(0, 84, t)), b = body(H, 196, -4), A = [204, AY - 16];
        const W = add(add(b.S, b.v, 14), b.u, 4), pole = add(b.v, b.u, -1);
        return mk(b, legTo(H, A, FWD, 100), legTo(far(H), far(A), FWD, 100), armTo(b.S, W, pole), armTo(far(b.S), far(W), pole)); },
      back: b => bar([120, 66], [56, 344], 9) + bar(add(add(b.H, b.v, -19), b.u, -2), add(add(b.H, b.v, -19), b.u, 100), 16, PROP2) + bar([168, GY - 8], [244, GY - 30], 12, PROP2) + rect(40, GY - 8, 220, 8, 4),
      front: b => `<circle cx="${f(add(b.S, b.u, 14)[0])}" cy="${f(add(b.S, b.u, 14)[1])}" r="12" fill="${PROP2}" stroke="#FFFFFF" stroke-width="1.6"/>`, mov: b => b.H },
    // elevação pélvica no solo: ombros e pés no chão, o quadril sobe até alinhar joelhos, quadril e ombros
    pelvicasolo: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Peso do corpo', mus: [['glutes'], ['hams']],
      pose: t => { const Sp = [72, 370], H = mix([163, 372], [150, 322], t), tor = ang(sub(Sp, H)), b = body(H, tor, lerp(0, -30, t)), A = [240, AY];
        return mk(b, legTo(H, A, UP, 90), legTo(far(H), far(A), UP, 90), armAng(b.S, 88, 88), armAng(far(b.S), 88, 88)); },
      back: () => rect(18, GY - 6, 282, 6, 3, PROP2), mov: b => b.H },
    // tríceps francês na polia: de costas para a polia baixa, corda atrás da cabeça; cotovelos para cima, só o antebraço estende
    francespolia: { ord: 'up', verbs: ['Estende', 'Volta'], eq: 'Polia', mus: [['triceps'], []], grip: 'corda',
      pose: t => { const H = [170, 231], b = body(H, 166, 4), ua = 162, fa = lerp(298, 166, t);
        return mk(b, legTo(H, [182, AY], FWD), legTo(far(H), [132, AY], FWD), armAng(b.S, ua, fa), armAng(far(b.S), ua, fa)); },
      back: b => { const p = [42, GY - 22]; return rect(20, 120, 12, GY - 120, 3) + pulley(p) + cable(p, b.nA.W); },
      front: b => `<path d="M${P(b.nA.W)} l-3,11 M${P(b.nA.W)} l5,9" stroke="${GRIP}" stroke-width="3.2" stroke-linecap="round"/>`, mov: b => b.nA.W },
    // barra fixa: pendurado, as mãos ficam na barra e o corpo sobe até o queixo passar dela
    barrafixa: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Barra fixa', mus: [['lats'], ['biceps', 'delt_r']], view: [.8, 0],
      pose: t => { const H = [148, lerp(232, 178, t)], b = body(H, 182, lerp(0, -6, t)), BP = [158, 44];
        return mk(b, legAng(H, 6, -16, 74), legAng(far(H), 6, -16, 74), armTo(b.S, BP, FWD), armTo(far(b.S), far(BP), FWD)); },
      back: () => rect(256, 38, 12, GY - 38, 3) + bar([262, 44], [158, 44], 8, PROP2), front: () => `<circle cx="158" cy="44" r="6" fill="${GRIP}"/>`, mov: b => b.H },
    // mergulho nas paralelas: mãos nas barras, o corpo desce até o cotovelo chegar a ~90° e volta
    mergulho: { ord: 'down', verbs: ['Desce', 'Sobe'], eq: 'Paralelas', mus: [['chest', 'triceps'], ['delt_f']],
      pose: t => { const H = [146, lerp(244, 290, t)], b = body(H, 168, 4), W = [166, 246];
        return mk(b, legAng(H, 14, -58, 30), legAng(far(H), 14, -58, 30), armTo(b.S, W, BACK), armTo(far(b.S), far(W), BACK)); },
      back: () => rect(160, 252, 10, GY - 252, 3) + rect(130, GY - 8, 80, 8, 4), front: () => `<circle cx="166" cy="248" r="7" fill="${GRIP}"/>`, mov: b => b.S },
    // rosca Bayesian: de costas para a polia, o braço fica atrás do corpo e só o antebraço sobe
    bayesian: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Polia', mus: [['biceps'], ['forearm']], grip: 'unilsup',
      pose: t => { const H = [168, 231], b = body(H, 172, 2), ua = -22, fa = lerp(-18, 158, t), cint = add(far(H), [12, -6]);
        return mk(b, legTo(H, [184, AY], FWD), legTo(far(H), [134, AY], FWD), armAng(b.S, ua, fa), armTo(far(b.S), cint, BACK)); },
      back: b => { const p = [44, 214]; return rect(20, 100, 12, GY - 100, 3) + pulley(p) + cable(p, b.nA.W); },
      front: b => { const W = b.nA.W, d = nrm(sub(W, b.nA.E)), n = [-d[1], d[0]]; return `<path d="M${P(add(add(W, d, 3), n, -8))} L${P(add(add(W, d, 3), n, 8))}" stroke="${GRIP}" stroke-width="5" stroke-linecap="round"/>`; }, mov: b => b.nA.W },
    // rosca no banco inclinado: costas no banco a ~45°, braços pendurados para trás; só o antebraço sobe
    roscainc: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Halteres', mus: [['biceps'], ['forearm']], grip: 'halter',
      pose: t => { const H = [150, 304], b = body(H, 216, -8), ua = 2, fa = lerp(4, 150, t);
        return mk(b, legTo(H, [232, AY], UP), legTo(far(H), far([232, AY]), UP), armAng(b.S, ua, fa), armAng(far(b.S), ua, fa)); },
      back: b => bar(add(add(b.H, b.v, -18), b.u, -6), add(add(b.H, b.v, -18), b.u, 104), 16, PROP2) + rect(b.H[0] - 30, b.H[1] + 10, 74, 12, 6, PROP2) + rect(b.H[0] - 6, b.H[1] + 20, 10, GY - b.H[1] - 20, 2) + rect(b.H[0] - 70, GY - 8, 150, 8, 4) + dbEnd(b.fA.W),
      front: b => dbEnd(b.nA.W), mov: b => b.nA.W },
    // pullover com halter: deitado no banco, braços quase retos levam o halter de cima do peito até atrás da cabeça
    pullover: { ord: 'down', verbs: ['Desce', 'Volta'], eq: 'Halter', mus: [['lats', 'chest'], ['triceps']],
      pose: t => { const H = [192, 307], b = body(H, -90, 0), ua = lerp(184, 258, t), fa = ua + 10;
        return mk(b, legTo(H, [266, AY], UP), legTo(far(H), far([266, AY]), UP), armAng(b.S, ua, fa), armAng(far(b.S), ua, fa)); },
      back: () => bench(26, 234, 318), front: b => { const d = nrm(sub(b.nA.W, b.nA.E)); return dbSide(add(b.nA.W, d, 6), d); }, mov: b => b.nA.W },
    // remada alta na polia: a corda sobe rente ao corpo até o peito, cotovelos sempre acima das mãos
    remadaalta: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Polia', mus: [['delt_s', 'traps'], ['delt_f']], grip: 'corda',
      pose: t => { const A = [150, AY], H = [150, 231], b = body(H, 180, 0), W = add(b.S, [12, lerp(92, 22, t)]), pole = [-0.2, -1];
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), armTo(b.S, W, pole), armTo(far(b.S), far(W), pole)); },
      back: b => { const p = [210, GY - 18]; return rect(222, 140, 12, GY - 140, 3) + pulley(p) + cable(p, b.nA.W); },
      front: b => `<path d="M${P(b.nA.W)} l-3,11 M${P(b.nA.W)} l5,9" stroke="${GRIP}" stroke-width="3.2" stroke-linecap="round"/>`, mov: b => b.nA.W },
    // desenvolvimento militar em pé: a barra sai da altura do queixo e sobe até os braços esticarem
    militar: { ord: 'down', verbs: ['Desce', 'Empurra'], eq: 'Barra', mus: [['delt_f', 'delt_s'], ['triceps', 'traps']],
      pose: t => { const A = [150, AY], H = [150, 231], b = body(H, 182, lerp(0, -6, t)), [nA, fA] = kf2(b, [8, -47], [12, -94], [24, 10], [32, -32], t);
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), nA, fA); },
      back: b => plate(b.nA.W, 30), front: b => plateRing(b.nA.W, 30), mov: b => b.nA.W },
    // hiperextensão 45°: quadril apoiado na almofada, o tronco desce e volta até ficar alinhado com as pernas
    hiperext: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Banco 45°', mus: [['glutes', 'lowback'], ['hams']],
      pose: t => { const A = [82, 350], d = dir(135), H = add(A, d, LEG - 4), b = body(H, lerp(28, 135, t), lerp(-10, 0, t)), W = add(add(b.S, b.u, -14), b.v, 18), pole = add(b.v, b.u, -1);
        const L = H2 => { const K = add(H2, dir(-45), LT), A2 = add(K, dir(-45), LS - 4); return { H: H2, K, A: A2, ft: 55 }; };
        return mk(b, L(H), L(far(H)), armTo(b.S, W, pole), armTo(far(b.S), far(W), pole)); },
      back: () => bar([70, 356], [176, 250], 10, PROP2) + bar([150, 262], [196, 228], 18, PROP2) + rect(100, 300, 10, GY - 300, 2) + rect(60, 360, 10, GY - 360, 2) + rect(40, GY - 8, 180, 8, 4) + `<circle cx="74" cy="342" r="9" fill="${PLATE}"/>`, mov: b => b.S },
    // crucifixo sentado na polia (de frente): sentado no banco entre as polias, as mãos se encontram na frente do peito
    crossent: { ord: 'up', verbs: ['Fecha', 'Abre'], eq: 'Polia', mus: [['chest'], ['delt_f']], frontView: true, noDb: true,
      pose: t => ({ cx: CX, seat: true, kx: 22, W: (s, S) => [CX + s * lerp(104, 12, t), S[1] + lerp(0, 12, t)] }),
      back: F => seatFront(F) + rect(18, 120, 12, GY - 120, 3) + rect(286, 120, 12, GY - 120, 3) + pulley([30, 216]) + pulley([286, 216]) + cable([30, 216], F.hands[0]) + cable([286, 216], F.hands[1]), front: grips, fmov: F => F.hands[1] },
    // coice de glúteo em quatro apoios: a perna sobe para trás com o joelho dobrado (sola do pé para o teto)
    coice4: { ord: 'up', verbs: ['Sobe', 'Volta'], eq: 'Peso do corpo', mus: [['glutes'], ['hams']],
      pose: t => { const H = [128, 308], b = body(H, 102, 6), th = lerp(0, -118, t), sh = th - lerp(90, 88, t);
        return mk(b, legAng(H, th, sh, sh + 90), legAng(far(H), 0, -90, -90), armAng(b.S, 0, 0), armAng(far(b.S), 0, 0)); },
      back: () => rect(30, GY - 6, 260, 6, 3, PROP2), mov: b => b.nL.A },
    // kettlebell swing: o quadril vai para trás e volta com força; os braços esticados levam o peso até a altura do peito
    kbswing: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Kettlebell', mus: [['glutes', 'hams'], ['lowback']],
      pose: t => { const A = [156, AY], H = mix([96, 252], [156, 231], t), b = body(H, lerp(116, 180, t), lerp(18, 0, t)), ua = lerp(-6, 96, t);
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), armAng(b.S, ua, ua + 4), armAng(far(b.S), ua, ua + 4)); },
      front: b => kettle(add(b.nA.W, [0, -4])), mov: b => b.nA.W },
    // crucifixo com halteres: deitado; os braços quase retos descem abertos para os lados (o braço de perto vem na nossa direção) e fecham em arco
    crucifixo: { ord: 'down', verbs: ['Abre', 'Fecha'], eq: 'Halteres', mus: [['chest'], ['delt_f']],
      pose: t => { const H = [192, 307], b = body(H, -90, 0), [nA, fA] = kf2(b, [4, -46], [6, -94], [-6, -26], [-2, -6], t);
        return mk(b, legTo(H, [266, AY], UP), legTo(far(H), far([266, AY]), UP), nA, fA); },
      back: () => bench(26, 234, 318), front: b => dbEnd(b.nA.W), mov: b => b.nA.W },
    // plate press: em pé, aperta a anilha entre as mãos na frente do peito e empurra para a frente
    platepress: { ord: 'up', verbs: ['Empurra', 'Volta'], eq: 'Anilha', mus: [['chest'], ['delt_f']],
      pose: t => { const A = [150, AY], H = [150, 231], b = body(H, 180, 0), [nA, fA] = kf2(b, [-30, 34], [10, 30], [46, 24], [92, 28], t);
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), nA, fA); },
      back: b => plate(add(b.nA.W, [4, 0]), 22), front: b => plateRing(add(b.nA.W, [4, 0]), 22), mov: b => b.nA.W },
    // tríceps no banco: mãos no banco atrás do quadril, pernas à frente; o corpo desce dobrando os cotovelos
    banco: { ord: 'down', verbs: ['Desce', 'Sobe'], eq: 'Banco', mus: [['triceps'], ['chest', 'delt_f']],
      pose: t => { const W = [92, 300], S = [104 + lerp(0, 10, t), 204 + lerp(0, 46, t)], H = [S[0] + 4, S[1] + 92], b = body(H, 182, lerp(0, 4, t)); b.S = S;
        return mk(b, legTo(H, [262, AY], UP), legTo(far(H), far([262, AY]), UP), armTo(S, W, BACK), armTo(far(S), far(W), BACK)); },
      back: () => bench(20, 108, 302), mov: b => b.S },
    // elevação em Y na polia (de frente): cabos cruzados das polias baixas; os braços sobem em Y acima da cabeça
    yraise: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Polia', mus: [['delt_s'], ['traps', 'delt_f']], frontView: true, noDb: true,
      pose: t => ({ cx: CX, a: lerp(12, 128, t), el: lerp(6, 10, t) }),
      back: F => rect(18, 150, 12, GY - 150, 3) + rect(286, 150, 12, GY - 150, 3) + pulley([30, 364]) + pulley([286, 364]) + cable([286, 364], F.hands[0]) + cable([30, 364], F.hands[1]), front: grips, fmov: F => F.hands[1] },
    // crucifixo inverso na polia (de frente): cabos cruzados na altura dos ombros; as mãos saem da frente do peito e abrem
    crossinv: { ord: 'up', verbs: ['Abre', 'Fecha'], eq: 'Polia', mus: [['delt_r'], ['uback', 'traps']], frontView: true, noDb: true,
      pose: crossPose(s => [CX + s * 10, 168], s => [CX + s * 104, 150], 4), back: F => rect(18, 100, 12, GY - 100, 3) + rect(286, 100, 12, GY - 100, 3) + pulley([30, 150]) + pulley([286, 150]) + cable([286, 150], F.hands[0]) + cable([30, 150], F.hands[1]), front: grips, fmov: F => F.hands[1] },
    // elevação lateral na máquina (de frente, sentado): as almofadas nos braços sobem até a linha do ombro
    lateralmaq: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Máquina', mus: [['delt_s'], ['traps']], frontView: true, noDb: true,
      pose: t => ({ cx: CX, seat: true, kx: 22, a: lerp(14, 86, t), el: lerp(8, 14, t) }),
      back: F => seatFront(F), front: F => F.hands.map((h, i) => { const s = i ? 1 : -1; return bar([CX + s * 31, F.shY + 4], add(h, [0, 0]), 1, 'transparent') + `<circle cx="${f(h[0] - s * 30)}" cy="${f(h[1] + (F.hands[i][1] < F.shY ? 6 : -6))}" r="10" fill="${PROP2}"/>`; }).join(''), fmov: F => F.hands[1] },
    // abdominal na polia: ajoelhado, corda junto à cabeça, enrola o tronco em direção às coxas
    abdpolia: { ord: 'up', verbs: ['Flexiona', 'Volta'], eq: 'Polia', mus: [['abs'], ['obl']],
      pose: t => { const H = [160, 306], b = body(H, lerp(172, 118, t), lerp(-6, -24, t)), W = add(add(b.Nk, b.hu, 14), b.hv, 18), pole = add(b.v, b.u, -.8);
        const L = H2 => legAng(H2, 0, -90, -90);
        return mk(b, L(H), L(far(H)), armTo(b.S, W, pole), armTo(far(b.S), far(W), pole)); },
      back: b => { const p = [264, 72]; return rect(274, 58, 12, GY - 58, 3) + pulley(p) + cable(p, b.nA.W) + rect(50, GY - 6, 170, 6, 3, PROP2); },
      front: b => `<path d="M${P(b.nA.W)} l-3,11 M${P(b.nA.W)} l4,10" stroke="${GRIP}" stroke-width="3.2" stroke-linecap="round"/>`, mov: b => b.S },
  };
  // pegada mostrada no quadrinho do canto (roscas e tríceps)
  EX.rosca.grip = 'barra'; EX.triceps.grip = 'corda'; EX.scott.grip = 'barra';
  // banco Scott sem o peso (usado nas variações)
  const scottBench = b => { const S = b.S, d = dir(42), n = [-d[1], d[0]], p1 = add(add(S, d, 2), n, 13), p2 = add(add(S, d, 60), n, 13);
    return rect(b.H[0] - 26, b.H[1] + 11, 70, 12, 6, PROP2) + rect(b.H[0] - 4, b.H[1] + 21, 10, GY - b.H[1] - 21, 2) + rect(b.H[0] - 40, GY - 8, 200, 8, 4) + bar(p1, p2, 18, PROP2) + bar(add(p2, n, 6), [p2[0] - 6, GY - 6], 9); };
  // variações que reaproveitam o movimento e trocam só o equipamento
  Object.assign(EX, {
    inclbarra: Object.assign({}, EX.inclinado, { eq: 'Barra', back: b => EX.inclinado.back(b) + plate(b.nA.W, 34), front: b => plateRing(b.nA.W, 34) }),
    supinohalter: Object.assign({}, EX.supino, { eq: 'Halteres', back: () => bench(26, 234, 318), front: b => dbEnd(b.nA.W) }),
    supinosmith: Object.assign({}, EX.supino, { eq: 'Smith', back: b => bench(26, 234, 318) + bar([b.bar[0], 62], [b.bar[0], GY - 4], 7, PROP2) + plate(b.bar, 36) }),
    inclsmith: Object.assign({}, EX.inclinado, { eq: 'Smith', back: b => EX.inclinado.back(b) + bar([b.nA.W[0], 62], [b.nA.W[0], GY - 4], 7, PROP2) + plate(b.nA.W, 34), front: b => plateRing(b.nA.W, 34) }),
    desenvbarra: Object.assign({}, EX.desenv, { eq: 'Barra', front: b => plateRing(b.nA.W, 30), back: b => EX.desenv.back(b) + plate(b.nA.W, 30) }),
    testahalter: Object.assign({}, EX.testa, { eq: 'Halteres', back: b => bench(26, 234, 318) + dbEnd(b.fA.W), front: b => dbEnd(b.nA.W) }),
    francespoliabarra: Object.assign({}, EX.francespolia, { grip: 'pronada', front: b => grip(b.nA.W, true) }),
    coicetripolia: Object.assign({}, EX.coicetri, { eq: 'Polia', back: b => { const p = [292, 318]; return bench(40, 252, 318) + rect(292, 200, 10, GY - 200, 3) + pulley(p) + cable(p, b.nA.W); }, front: b => grip(b.nA.W, true) }),
    agachfrontal: Object.assign({}, EX.goblet, { eq: 'Barra', back: b => plate(b.kb, 30), front: b => plateRing(b.kb, 30) }),
    hack: Object.assign({}, EX.vsquat, { back: b => bar([138, 60], [40, 352], 10, PROP2) + bar(add(add(b.H, b.v, -19), b.u, -6), add(add(b.H, b.v, -19), b.u, 104), 16, PROP2) + bar([170, GY - 8], [246, GY - 34], 12, PROP2) + rect(30, GY - 8, 240, 8, 4) }),
    // rosca Scott na máquina: o braço fica no apoio e a alavanca gira em volta do cotovelo
    scottmaq: Object.assign({}, EX.scott, { eq: 'Máquina', grip: 'pronada',
      back: b => { const E = b.nA.E, d = nrm(sub(b.nA.W, E)); return scottBench(b) + bar(add(E, d, -14), add(b.nA.W, d, 4), 9, PROP2) + bar(E, add(E, [10, 30]), 8, PROP2) + `<circle cx="${f(E[0])}" cy="${f(E[1])}" r="10" fill="${PROP}" stroke="${PROP2}" stroke-width="3"/>`; },
      front: b => grip(b.nA.W, true) }),
    // rosca martelo no banco Scott: halter de pé na mão (palma virada para dentro)
    scottmartelo: Object.assign({}, EX.scott, { eq: 'Halteres', grip: 'neutra',
      back: b => { const d = nrm(sub(b.fA.W, b.fA.E)); return scottBench(b) + dbSide(add(b.fA.W, d, 2), [-d[1], d[0]]); },
      front: b => { const d = nrm(sub(b.nA.W, b.nA.E)); return dbSide(add(b.nA.W, d, 2), [-d[1], d[0]]); } }),
    // afundo no Smith: barra presa no trilho, apoiada nas costas
    afundosmith: Object.assign({}, EX.afundo, { eq: 'Smith',
      pose: t => { const b = EX.afundo.pose(t), barP = add(add(b.H, b.u, LTOR + 4), b.v, -13), W = add(barP, b.v, 3), E = add(add(barP, b.u, -34), b.v, -10);
        const arm = S => ({ S, E: add(E, sub(S, b.S)), W: add(W, sub(S, b.S)) }); b.bar = barP; b.nA = arm(b.S); b.fA = arm(far(b.S)); return b; },
      back: b => bar([b.bar[0], 62], [b.bar[0], GY - 4], 7, PROP2) + plate(b.bar, 34), front: b => plateRing(b.bar, 34) }),
    // subida no banco (step-up): um pé em cima do caixote; o corpo sobe até ficar em pé sobre ele e desce devagar
    stepup: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Caixote', mus: [['glutes', 'quads'], ['hams']], view: [.84, 6],
      pose: t => { const Af = [212, AY - 62], H = mix([150, 262], [204, 172], t), b = body(H, 180 - lerp(16, 2, t), 0), k = Math.max(0, Math.min(1, t * 1.6 - .5)), Ab = [lerp(108, 196, k), lerp(AY, AY - 62, k) - Math.sin(Math.PI * k) * 46];
        return mk(b, legTo(H, Af, FWD, 90), legTo(far(H), far(Ab), FWD, 90), armAng(b.S, 2, 0), armAng(far(b.S), 2, 0)); },
      back: b => rect(180, GY - 62, 90, 62, 5, PROP2) + dbSide(add(b.fA.W, [2, 8])), front: b => dbSide(add(b.nA.W, [2, 8])), mov: b => b.H },
    // panturrilha no leg press 45°: joelhos quase esticados; só o tornozelo empurra a plataforma com a ponta dos pés
    pantleg: Object.assign({}, EX.leg, { ord: 'up', verbs: ['Empurra', 'Volta'], mus: [['calves'], []],
      pose: t => {
        const H = [128, 300], r = dir(135), qq = [-r[1], r[0]], b = body(H, -135, -12), pole = dir(-135);
        // a ponta do pé fica na parte de baixo da plataforma; o calcanhar desce (alonga) e sobe (contrai), com a perna sempre quase esticada
        const ft = -135 + lerp(24, -18, t), fd = dir(ft), n = [-fd[1], fd[0]];
        const k = add(add(add(qq.map(x => x * 8), r, -4), fd, -28), n, -13), rk = r[0] * k[0] + r[1] * k[1], Dp = -rk + Math.sqrt(rk * rk - (k[0] * k[0] + k[1] * k[1]) + 148 * 148);
        const pl = add(H, r, Dp), A = add(H, add(r.map(x => x * Dp), k));
        b.rail = r; b.plat = pl;
        const W = add(H, [14, 10]);
        return mk(b, legTo(H, A, pole, ft), legTo(far(H), far(A), pole, ft), armTo(b.S, W, DOWN), armTo(far(b.S), far(W), DOWN));
      }, mov: b => b.plat }),
    agachpc: Object.assign({}, EX.agach, { eq: 'Peso do corpo', back: null, front: null }),
    afundopc: Object.assign({}, EX.afundo, { eq: 'Peso do corpo', back: null, front: null }),
    agachsmith: Object.assign({}, EX.agach, { eq: 'Smith', back: b => bar([b.bar[0], 62], [b.bar[0], GY - 4], 7, PROP2) + plate(b.bar, 34) }),
    desenvmaq: Object.assign({}, EX.desenv, { eq: 'Máquina', back: b => EX.desenv.back(b) + bar([60, 160], b.nA.W, 8, PROP2), front: b => grip(b.nA.W) }),
    roscapolia: Object.assign({}, EX.rosca, { eq: 'Polia', grip: 'polia', back: b => { const p = [b.H[0] + 66, GY - 16]; return rect(p[0] + 8, 150, 12, GY - 150, 3) + pulley(p) + cable(p, b.nA.W); }, front: b => grip(b.nA.W, true) }),
    // rosca alternada: um braço sobe enquanto o outro espera embaixo, depois troca
    roscahalter: { ord: 'up', verbs: ['Sobe', 'Desce'], eq: 'Halteres', mus: [['biceps'], ['forearm']], alt: true, grip: 'halter',
      pose: (t, side) => { const A = [150, AY], H = [152, 231], b = body(H, 180, 0), curl = S2 => armAng(S2, lerp(2, 12, t), lerp(4, 160, t)), down = S2 => armAng(S2, 2, 4); b.side = side;
        return mk(b, legTo(H, A, FWD), legTo(far(H), far(A), FWD), side ? down(b.S) : curl(b.S), side ? curl(far(b.S)) : down(far(b.S))); },
      back: b => dbEnd(b.fA.W), front: b => dbEnd(b.nA.W), mov: b => b.side ? b.fA.W : b.nA.W },
    // rosca martelo: palmas viradas uma para a outra, o halter fica de pé na mão
    martelo: Object.assign({}, EX.rosca, { eq: 'Halteres', grip: 'neutra',
      back: b => { const d = nrm(sub(b.fA.W, b.fA.E)); return dbSide(add(b.fA.W, d, 2), [-d[1], d[0]]); },
      front: b => { const d = nrm(sub(b.nA.W, b.nA.E)); return dbSide(add(b.nA.W, d, 2), [-d[1], d[0]]); } }),
    tricepsbarra: Object.assign({}, EX.triceps, { grip: 'pronada', front: b => grip(b.nA.W, true) }),
    frontalpolia: Object.assign({}, EX.frontal, { eq: 'Polia', back: b => { const p = [b.H[0] - 44, GY - 16]; return rect(p[0] - 20, 150, 12, GY - 150, 3) + pulley(p) + cable(p, b.nA.W); }, front: b => grip(b.nA.W, true) }),
    pelvicamaq: Object.assign({}, EX.pelvica, { eq: 'Máquina', back: b => bench(20, 116, 316) + bar(b.bar, [296, GY - 40], 8, PROP2) + rect(286, GY - 46, 14, 46, 3),
      front: b => `<circle cx="${f(b.bar[0])}" cy="${f(b.bar[1])}" r="14" fill="${PROP2}" stroke="#FFFFFF" stroke-width="1.6"/>` }),
    stiffbarra: Object.assign({}, EX.stiff, { eq: 'Barra', back: b => plate(add(b.nA.W, [0, 6]), 34), front: b => plateRing(add(b.nA.W, [0, 6]), 34) }),
    remadacav: Object.assign({}, EX.remada, { eq: 'Barra T', back: b => { const W = b.nA.W, d = nrm(sub(W, [24, GY - 6])); return bar([24, GY - 6], add(W, d, 30), 8, PROP2) + plate(add(W, d, 30), 28); },
      front: b => `<circle cx="${f(b.nA.W[0])}" cy="${f(b.nA.W[1])}" r="5" fill="${GRIP}"/>` }),
  });
  const ALIAS = [[/goblet/, 'goblet'], [/agach|squat/, 'agach'], [/leg ?press/, 'leg'], [/extensora/, 'extensora'], [/afundo|passada|b[uú]lgaro|lunge/, 'afundo'],
    [/p[eé]lvica|hip thrust|ponte/, 'pelvica'], [/panturrilha|g[eê]meos/, 'panturrilha'], [/inclinado/, 'inclinado'], [/supino|chest press|crucifixo/, 'supino'],
    [/desenvolvimento|militar|arnold/, 'desenv'], [/eleva[cç][aã]o lateral|\blateral/, 'lateral'], [/tr[ií]ceps|franc[eê]s|testa/, 'triceps'], [/puxada|pulldown|barra fixa|graviton/, 'puxada'],
    [/serrote|unilateral/, 'serrote'], [/remada baixa|remada sentada|baixa/, 'baixa'], [/remada/, 'remada'], [/face ?pull|crucifixo inverso/, 'facepull'], [/rosca|b[ií]ceps|martelo/, 'rosca'],
    [/flex[aã]o|push/, 'flexao'], [/stiff|terra|rdl|levantamento/, 'stiff'], [/dead ?bug/, 'deadbug'], [/prancha|plank/, 'prancha'], [/abdominal|abd[oô]men|crunch/, 'crunch']];
  const BYGROUP = [[/quadr/, 'agach'], [/gl[uú]te/, 'pelvica'], [/posterior/, 'stiff'], [/panturr/, 'panturrilha'], [/peit/, 'supino'], [/ombro|delt/, 'desenv'],
    [/tr[ií]ceps/, 'triceps'], [/b[ií]ceps/, 'rosca'], [/costa|dorsal/, 'puxada'], [/abd|core/, 'prancha']];
  // nomes de variações (máquina, polia, barra) que precisam vir antes da regra geral
  const SPEC = [[/pulldown.*m[aá]quina|puxada.*(articulad|m[aá]quina)/, 'pulldownmaq'], [/b[uú]lgaro.*(inclinad|gl[uú]teo)/, 'bulgaroinc'], [/b[uú]lgaro/, 'bulgaro'], [/panturrilha.*(leg|press)/, 'pantleg'], [/bayesian/, 'bayesian'], [/rosca.*inclinad|inclinad.*rosca/, 'roscainc'], [/scott.*m[aá]quina|m[aá]quina.*scott/, 'scottmaq'], [/martelo.*scott|scott.*martelo/, 'scottmartelo'],
    [/franc[eê]s.*polia.*barra|franc[eê]s.*barra.*polia/, 'francespoliabarra'], [/coice.*polia.*tr[ií]ceps|tr[ií]ceps.*coice.*polia/, 'coicetripolia'], [/testa.*halter/, 'testahalter'],
    [/supino inclinado.*smith|inclinado.*smith/, 'inclsmith'], [/supino.*smith/, 'supinosmith'], [/(afundo|passada|lunge).*smith|smith.*(afundo|passada)/, 'afundosmith'],
    [/agachamento frontal|front squat/, 'agachfrontal'], [/hack|p[eê]ndulo/, 'hack'], [/step-? ?up|subida no (banco|caixote|step)/, 'stepup'], [/kettlebell swing|\bswing/, 'kbswing'],
    [/quatro apoios|donkey/, 'coice4'], [/hiperextens|extens[aã]o lombar|banco romano|back extension/, 'hiperext'], [/pullover/, 'pullover'], [/remada alta/, 'remadaalta'],
    [/militar/, 'militar'], [/desenvolvimento.*barra/, 'desenvbarra'], [/eleva[cç][aã]o em y|y.?raise/, 'yraise'], [/lateral.*m[aá]quina/, 'lateralmaq'],
    [/(crucifixo|fly).*sentad|cross.*sentad/, 'crossent'], [/(crucifixo inverso|cross.*invers|crossover invers).*(polia|cabo)|(polia|cabo).*invers/, 'crossinv'],
    [/plate press|anilha.*peito|svend/, 'platepress'], [/levantamento terra(?! romeno)|^terra(?! romeno)/, 'terra'], [/rack pull/, 'stiffbarra'],
    [/franc[eê]s.*(polia|cabo|corda)|(polia|cabo).*franc[eê]s/, 'francespolia'], [/p[eé]lvica.*(solo|ch[aã]o)|ponte de gl[uú]teo|gl[uú]teo no ch[aã]o/, 'pelvicasolo'], [/cadeira abdutora.*inclinad|abdutora inclinad[ao] na (cadeira|m[aá]quina)/, 'abdutora'], [/abdu(tora|[cç][aã]o).*inclinad/, 'abdutorapoliainc'], [/abdu(tora|[cç][aã]o).*(polia|cabo)/, 'abdutorapolia'], [/v-? ?squat|agachamento no v\b/, 'vsquat'], [/tr[ií]ceps.*unilateral|pulley unilateral|unilateral.*tr[ií]ceps/, 'tricepsunil'], [/abdominal.*banco (inclinado|declinado)|banco (inclinado|declinado).*abd|abd[oô]men.*banco/, 'abdinclinado'], [/serrote|remada unilateral/, 'serrote'], [/cross.*alta|crucifixo.*polia alta/, 'crossalta'], [/cross.*baixa|crucifixo.*polia baixa/, 'crossbaixa'], [/cross|crucifixo.*polia/, 'crossmedia'],
    [/voador inverso|crucifixo inverso|peck ?deck inverso/, 'voadorinv'], [/voador|peck ?deck|crucifixo.*m[aá]quina|fly.*m[aá]quina/, 'voador'], [/crucifixo/, 'crucifixo'],
    [/supino.*inclinad.*m[aá]quina|supino.*m[aá]quina.*inclinad/, 'supmaqinc'], [/supino.*m[aá]quina|chest press|supino articulado/, 'supmaq'],
    [/supino inclinado.*barra/, 'inclbarra'], [/supino (reto )?com halter/, 'supinohalter'], [/remada.*m[aá]quina|remada articulada/, 'remadamaq'],
    [/cavalinho|remada t\b|barra t\b/, 'remadacav'], [/pulldown|bra[cç]os? (estendidos|retos?)/, 'pulldownreto'], [/desenvolvimento.*m[aá]quina/, 'desenvmaq'],
    [/eleva[cç][aã]o lateral.*polia|lateral na polia/, 'lateralpolia'], [/eleva[cç][aã]o frontal.*polia|frontal na polia/, 'frontalpolia'], [/eleva[cç][aã]o frontal/, 'frontal'],
    [/encolhimento|shrug/, 'encolh'], [/smith/, 'agachsmith'], [/rosca.*(polia|cabo)/, 'roscapolia'], [/scott/, 'scott'], [/martelo/, 'martelo'], [/rosca.*alternad|rosca.*halter/, 'roscahalter'],
    [/testa/, 'testa'], [/franc[eê]s/, 'frances'], [/coice.*tr[ií]ceps|tr[ií]ceps.*(coice|kickback)/, 'coicetri'], [/tr[ií]ceps.*(barra|pulley)/, 'tricepsbarra'],
    [/(coice|gl[uú]teo).*polia.*(varia|inclinad)|kickback.*inclinad/, 'coicepoliainc'], [/coice|gl[uú]teo.*polia|kickback/, 'coicepolia'], [/abdominal.*m[aá]quina/, 'abdmaq'], [/(abdominal|crunch).*(anilha|carga|peso|halter)/, 'crunchanilha'], [/abdominal.*(polia|corda)/, 'abdpolia'],
    [/(pernas|joelhos).*barra fixa|infra.*barra|barra fixa.*(pernas|infra)/, 'infrabarra'], [/eleva[cç][aã]o de joelhos|capit[aã]o|infra.*(apoio|paralela)|joelhos.*apoio/, 'infracad'],
    [/eleva[cç][aã]o de pernas|abdominal infra|^infra/, 'legraise'], [/p[eé]lvica.*m[aá]quina|hip thrust.*m[aá]quina/, 'pelvicamaq'], [/stiff.*barra|terra romeno|rdl/, 'stiffbarra'],
    [/cadeira flexora|flexora sentad/, 'cadflexora'], [/flexora/, 'flexora'], [/abdutora|abdu[cç][aã]o/, 'abdutora'], [/adutora|adu[cç][aã]o/, 'adutora'], [/sum[oô]/, 'goblet'],
    [/tr[ií]ceps.*banco|mergulho.*banco|banco.*mergulho/, 'banco'], [/mergulho|paralelas|\bdips?\b/, 'mergulho'], [/barra fixa|chin-? ?up|pull-? ?up/, 'barrafixa']];
  const NONE = /burpee|polichinelo|corda naval|esteira|bicicleta|bike|el[ií]ptico|escada|pular corda|concentrad|prancha lateral|russian|russo|canivete|remador|escalador|panturrilha sentad|horizontal|obl[ií]quo|rodinha|pallof|lenhador/;
  function pick(e) {
    if (e.anim === 'none') return null;
    if (e.anim && EX[e.anim]) return e.anim;
    if (EX[e.id]) return e.id;
    const n = (e.nome || '').toLowerCase(), g = (e.alvo || e.grupo || '').toLowerCase();
    for (const [r, k] of SPEC) if (r.test(n)) return k;
    if (NONE.test(n)) return null; // máquinas e movimentos que ainda não têm animação própria
    for (const [r, k] of ALIAS) if (r.test(n)) return k;
    for (const [r, k] of BYGROUP) if (r.test(g)) return k;
    return null;
  }
  const LABELS = { agach: 'Agachamento livre', goblet: 'Agachamento goblet', leg: 'Leg press 45°', extensora: 'Cadeira extensora', afundo: 'Afundo / passada',
    pelvica: 'Elevação pélvica', panturrilha: 'Panturrilha em pé', stiff: 'Stiff / terra romeno', terra: 'Levantamento terra', supino: 'Supino reto', inclinado: 'Supino inclinado',
    desenv: 'Desenvolvimento', lateral: 'Elevação lateral', triceps: 'Tríceps na polia', flexao: 'Flexão de braço', puxada: 'Puxada frente',
    remada: 'Remada curvada', baixa: 'Remada baixa', serrote: 'Remada unilateral (serrote)', facepull: 'Face pull', rosca: 'Rosca direta',
    deadbug: 'Dead bug', prancha: 'Prancha', crossalta: 'Crossover polia alta', crossmedia: 'Crossover polia média', crossbaixa: 'Crossover polia baixa',
    voador: 'Voador (peck deck)', voadorinv: 'Crucifixo inverso máquina', supmaq: 'Supino reto máquina', supmaqinc: 'Supino inclinado máquina', supinohalter: 'Supino reto com halteres',
    inclbarra: 'Supino inclinado com barra', remadamaq: 'Remada na máquina', remadacav: 'Remada cavalinho', pulldownreto: 'Pulldown', pulldownmaq: 'Pulldown na máquina', desenvmaq: 'Desenvolvimento máquina',
    lateralpolia: 'Elevação lateral unilateral na polia', frontal: 'Elevação frontal', frontalpolia: 'Elevação frontal na polia', encolh: 'Encolhimento', agachsmith: 'Agachamento no Smith',
    roscapolia: 'Rosca na polia', agachpc: 'Agachamento sem peso', afundopc: 'Afundo sem peso', roscahalter: 'Rosca alternada', martelo: 'Rosca martelo', scott: 'Rosca Scott', testa: 'Tríceps testa', frances: 'Tríceps francês', coicetri: 'Tríceps coice',
    tricepsbarra: 'Tríceps polia com barra', coicepolia: 'Glúteo na polia (coice)', coicepoliainc: 'Coice na polia (variação)', pelvicamaq: 'Elevação pélvica máquina', stiffbarra: 'Stiff com barra', flexora: 'Mesa flexora',
    cadflexora: 'Cadeira flexora', abdutora: 'Cadeira abdutora', adutora: 'Cadeira adutora', crunch: 'Abdominal no chão', crunchanilha: 'Abdominal com anilha', legraise: 'Abdominal infra',
    infrabarra: 'Elevação de pernas na barra', infracad: 'Elevação de joelhos no apoio', abdmaq: 'Abdominal na máquina', abdpolia: 'Abdominal na polia', tricepsunil: 'Tríceps pulley unilateral', pelvicasolo: 'Elevação pélvica no solo', francespolia: 'Tríceps francês na polia', abdutorapolia: 'Abdutora reta na polia', abdutorapoliainc: 'Abdutora inclinada na polia', vsquat: 'Agachamento no V-squat', abdinclinado: 'Abdominal no banco inclinado',
    barrafixa: 'Barra fixa', mergulho: 'Mergulho nas paralelas', bayesian: 'Rosca Bayesian na polia', roscainc: 'Rosca no banco inclinado', pullover: 'Pullover com halter', remadaalta: 'Remada alta na polia', militar: 'Desenvolvimento militar em pé', hiperext: 'Hiperextensão 45°', crossent: 'Crucifixo sentado na polia', coice4: 'Coice em quatro apoios', kbswing: 'Kettlebell swing', crucifixo: 'Crucifixo com halteres', platepress: 'Plate press', banco: 'Tríceps no banco', yraise: 'Elevação em Y na polia', crossinv: 'Crucifixo inverso na polia', lateralmaq: 'Elevação lateral na máquina', supinosmith: 'Supino reto no Smith', inclsmith: 'Supino inclinado no Smith', desenvbarra: 'Desenvolvimento com barra', testahalter: 'Tríceps testa com halteres', francespoliabarra: 'Tríceps francês na polia (barra)', coicetripolia: 'Tríceps coice na polia', agachfrontal: 'Agachamento frontal', hack: 'Agachamento no hack', scottmaq: 'Rosca Scott na máquina', scottmartelo: 'Rosca martelo no Scott', afundosmith: 'Afundo no Smith', stepup: 'Subida no banco (step-up)', bulgaro: 'Agachamento búlgaro', bulgaroinc: 'Agachamento búlgaro (tronco inclinado)', pantleg: 'Panturrilha no leg press' };
  const LIST = Object.keys(LABELS).map(k => [k, LABELS[k]]);
  // ---------- mapa muscular ----------
  const NAMES = { quads: 'Quadríceps', glutes: 'Glúteos', hams: 'Posteriores da coxa', calves: 'Panturrilhas', chest: 'Peitoral', delt_f: 'Ombro (frente)', delt_s: 'Ombro (lateral)',
    delt_r: 'Ombro (posterior)', triceps: 'Tríceps', biceps: 'Bíceps', forearm: 'Antebraço', abs: 'Abdômen', obl: 'Oblíquos', adductors: 'Adutores', traps: 'Trapézio', lats: 'Dorsal', uback: 'Meio das costas', lowback: 'Lombar' };
  function mapFig(cx, top, sc, back, pr, au) {
    const T = (x, y) => f(cx + x * sc) + ',' + f(top + y * sc);
    const cap = (a, b, r1, r2) => limb([cx + a[0] * sc, top + a[1] * sc], [cx + b[0] * sc, top + b[1] * sc], r1 * sc, r2 * sc);
    const base = '#D7DBD8', g = [], col = k => pr.includes(k) ? RED : au.includes(k) ? '#F4A39C' : null;
    const reg = (k, d) => { const c = col(k); if (c) g.push(`<path d="${d}" fill="${c}"/>`); };
    g.push(`<ellipse cx="${f(cx)}" cy="${f(top + 11 * sc)}" rx="${f(8.6 * sc)}" ry="${f(10.6 * sc)}" fill="${base}"/>`);
    g.push(`<path d="M${T(-4, 19)} L${T(4, 19)} L${T(4.5, 28)} L${T(-4.5, 28)} Z" fill="${base}"/>`);
    g.push(`<path d="M${T(-19, 30)} Q${T(0, 25)} ${T(19, 30)} Q${T(21, 32)} ${T(18, 48)} Q${T(14, 62)} ${T(14, 72)} Q${T(18, 80)} ${T(17.5, 88)} L${T(-17.5, 88)} Q${T(-18, 80)} ${T(-14, 72)} Q${T(-14, 62)} ${T(-18, 48)} Q${T(-21, 32)} ${T(-19, 30)} Z" fill="${base}"/>`);
    if (!back) { reg('abs', `M${T(-6.5, 52)} L${T(6.5, 52)} L${T(6, 80)} L${T(-6, 80)} Z`); }
    else { reg('traps', `M${T(-13, 30)} Q${T(0, 22)} ${T(13, 30)} L${T(5, 47)} L${T(-5, 47)} Z`); reg('lowback', `M${T(-6, 64)} L${T(6, 64)} L${T(7, 82)} L${T(-7, 82)} Z`); }
    for (const s of [-1, 1]) {
      g.push(`<path d="${cap([s * 21, 34], [s * 25, 58], 4.6, 3.8)}" fill="${base}"/><path d="${cap([s * 25, 58], [s * 26, 81], 3.8, 3)}" fill="${base}"/><circle cx="${f(cx + s * 26 * sc)}" cy="${f(top + 85 * sc)}" r="${f(3.6 * sc)}" fill="${base}"/>`);
      g.push(`<path d="M${T(s * 17.5, 86)} L${T(s * 1.2, 86)} Q${T(s * 1.5, 104)} ${T(s * 3.5, 123)} Q${T(s * 8, 127)} ${T(s * 12, 124)} Q${T(s * 17, 104)} ${T(s * 17.5, 86)} Z" fill="${base}"/>`);
      g.push(`<path d="${cap([s * 8, 126], [s * 7.5, 160], 4.8, 3.2)}" fill="${base}"/><ellipse cx="${f(cx + s * 8.5 * sc)}" cy="${f(top + 164 * sc)}" rx="${f(4.2 * sc)}" ry="${f(2.6 * sc)}" fill="${base}"/>`);
      const delt = `M${T(s * 15, 30)} Q${T(s * 23, 29)} ${T(s * 24, 38)} Q${T(s * 20, 41)} ${T(s * 16, 38)} Z`;
      if (!back) {
        reg('chest', `M${T(s * 1.5, 32)} Q${T(s * 15, 30)} ${T(s * 17, 39)} Q${T(s * 13, 50)} ${T(s * 1.5, 48)} Z`);
        reg('delt_f', delt); reg('delt_s', delt);
        reg('biceps', cap([s * 22, 40], [s * 24.6, 55], 3.4, 2.8));
        reg('forearm', cap([s * 25.2, 61], [s * 25.8, 77], 3, 2.4));
        reg('obl', `M${T(s * 8, 54)} Q${T(s * 14, 60)} ${T(s * 13.5, 74)} L${T(s * 7.5, 80)} Z`);
        reg('quads', `M${T(s * 15.5, 92)} Q${T(s * 17.5, 106)} ${T(s * 12.5, 121)} L${T(s * 7, 122)} Q${T(s * 5, 106)} ${T(s * 6.5, 91)} Z`);
        reg('quads', `M${T(s * 5.8, 93)} Q${T(s * 2.5, 104)} ${T(s * 4.2, 115)} Q${T(s * 6.4, 122)} ${T(s * 8.5, 121)} Q${T(s * 6.4, 107)} ${T(s * 5.8, 93)} Z`);
        reg('adductors', `M${T(s * 1.4, 89)} L${T(s * 6.2, 90)} Q${T(s * 3.6, 101)} ${T(s * 3.4, 111)} Q${T(s * 1.6, 102)} ${T(s * 1.4, 89)} Z`);
      } else {
        reg('delt_r', delt); reg('delt_s', delt);
        reg('uback', `M${T(s * 3, 34)} L${T(s * 13, 35)} L${T(s * 12, 47)} L${T(s * 3, 49)} Z`);
        reg('lats', `M${T(s * 5, 50)} Q${T(s * 16, 45)} ${T(s * 17, 51)} Q${T(s * 15, 64)} ${T(s * 6, 74)} Z`);
        reg('triceps', cap([s * 22, 39], [s * 24.6, 56], 3.6, 2.8));
        reg('glutes', `M${T(s * 1, 80)} Q${T(s * 15, 77)} ${T(s * 17.5, 88)} Q${T(s * 17, 99)} ${T(s * 1.2, 99)} Z`);
        reg('hams', `M${T(s * 15.5, 103)} Q${T(s * 16.5, 114)} ${T(s * 12, 123)} L${T(s * 4.5, 123)} Q${T(s * 2.6, 112)} ${T(s * 2.6, 103)} Z`);
        reg('calves', cap([s * 8.2, 130], [s * 7.9, 148], 4.4, 3.2));
      }
    }
    return g.join('');
  }
  const list = a => a.map(k => NAMES[k]).join(', ').replace(/, ([^,]*)$/, ' e $1');

  // ---------- tempo: ritmo natural fixo (sem cadência) ----------
  // ida, pausa só na contração (exercícios que começam com a força), volta controlada e um respiro
  function steps(e, X) {
    const [v1, v2] = X.verbs, down = X.ord === 'down';
    const go = down ? 2 : 1.3, hold = down ? 0 : .8, back = down ? 1.3 : 2;
    const one = side => [{ a: 0, b: 1, d: go, ph: v1, side }, { a: 1, b: 1, d: hold, ph: 'Segura', side },
      { a: 1, b: 0, d: back, ph: v2, side }, { a: 0, b: 0, d: .6, ph: v2, side }].filter(x => x.d > 0);
    return X.alt ? one(0).concat(one(1)) : one(0);
  }
  function phaseAt(e, X, sec) {
    if (X.iso) return { t: 0, ph: 'Segure firme', dir: 0, side: 0 };
    const L = steps(e, X), T = L.reduce((s, x) => s + x.d, 0); let s = sec % T;
    for (const x of L) { if (s < x.d) { const k = x.a === x.b ? 0 : ease(s / x.d); return { t: lerp(x.a, x.b, k), ph: x.ph, dir: Math.sign(x.b - x.a), side: x.side }; } s -= x.d; }
    return { t: 0, ph: L[0].ph, dir: 0, side: 0 };
  }

  // ---------- cena ----------
  const defs = () => `<defs><linearGradient id="xaInk${SFX}" gradientUnits="userSpaceOnUse" x1="40" y1="90" x2="260" y2="400"><stop offset="0" stop-color="#4A574F"/><stop offset="1" stop-color="#232C27"/></linearGradient>
      <radialGradient id="xaSh${SFX}" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#0F1712" stop-opacity=".11"/><stop offset="1" stop-color="#0F1712" stop-opacity="0"/></radialGradient></defs>`;
  function stateOf(e, X, sec, still) {
    if (still === true) return { t: X.iso ? 0 : 1, ph: X.iso ? 'Segure firme' : 'Ponto mais difícil', dir: 0, side: 0 };
    const a = phaseAt(e, X, sec); if (typeof still === 'number') { a.t = still; a.dir = 0; } return a;
  }
  // só o boneco e os aparelhos (área x 0–310, y 70–410 da cena)
  function figSvg(X, a) {
    const vw = X.view || [1, 0], sc = vw[0];
    let fig = `<rect x="${X.view ? -40 : 18}" y="${GY}" width="${X.view ? 400 : 282}" height="1.6" fill="#E3E7E4"/><ellipse cx="160" cy="${GY + 2}" rx="100" ry="8" fill="url(#xaSh${SFX})"/>`;
    if (X.frontView) {
      const F = manFront(X.pose(a.t), false), F0 = manFront(X.pose(0), true, GHOST);
      if (X.back && !NOEQ) fig += X.back(F, a.t);
      if (a.t > .001) fig += `<g opacity="${f(Math.min(1, a.t * 8))}">${F0.svg}</g>`;
      fig += F.svg + (NOEQ || X.noDb ? '' : F.hands.map(dbEnd).join(''));
      if (X.front && !NOEQ) fig += X.front(F, a.t);
      if (a.dir && X.fmov) {
        const m = X.fmov(F), m2 = X.fmov(manFront(X.pose(Math.min(1, Math.max(0, a.t + .04 * a.dir))), true, GHOST)), dv = sub(m2, m);
        if (len(dv) > .05) { // seta do lado de fora do movimento (longe do tronco), sem sair da cena
          const d = nrm(dv), n = [-d[1], d[0]], mid = [F.cx, (F.shY + F.hipY) / 2], out = c => c[1] < 74 || c[0] > 296 || c[0] < 20;
          let c = add(m, n, 24), c2 = add(m, n, -24); if (len(sub(c2, mid)) > len(sub(c, mid))) [c, c2] = [c2, c]; if (out(c)) c = c2; fig += arrow(c, d); }
      } else if (a.dir) { const h = F.hands[1], d = a.dir > 0 ? [.15, -1] : [-.15, 1], c = add(h, [16, 0]); fig += arrow(c, nrm(d)); }
    } else {
      const B = X.pose(a.t, a.side), B0 = X.pose(0, a.side);
      if (X.back && !NOEQ) fig += X.back(B, a.t);
      if (a.t > .001 && !X.iso) fig += `<g opacity="${f(Math.min(1, a.t * 8))}">${man(B0, true, GHOST)}</g>`;
      fig += man(B, false);
      if (X.front && !NOEQ) fig += X.front(B, a.t);
      if (a.dir && X.mov) {
        const m = X.mov(B), m2 = X.mov(X.pose(Math.min(1, Math.max(0, a.t + .04 * a.dir)), a.side)), dv = sub(m2, m);
        if (len(dv) > .05) { const d = nrm(dv), n = [-d[1], d[0]]; let c = add(m, n, 26); if (c[0] > 296) c = add(m, n, -26); fig += arrow(c, d); }
      }
    }
    return sc !== 1 || vw[1] ? `<g transform="translate(${f(160 * (1 - sc) + vw[1])} ${f(GY * (1 - sc))}) scale(${sc})">${fig}</g>` : fig;
  }
  // miniatura (viewBox sugerido: XA.FIGVB)
  function figure(e, sec, still, sfx = '') {
    const k = pick(e); if (!k) return '';
    SFX = sfx; NOEQ = !!e.semEq; const X = EX[k], out = defs() + figSvg(X, stateOf(e, X, sec, still)); SFX = ''; NOEQ = false; return out;
  }
  function scene(e, sec, still, sfx = '') {
    const k = pick(e); if (!k) return '';
    SFX = sfx; NOEQ = !!e.semEq; const X = EX[k], a = stateOf(e, X, sec, still), eqL = NOEQ ? 'Sem peso' : X.eq;
    const nome = String(e.nome || '').toUpperCase(), short = (nome.length > 30 ? nome.slice(0, 29) + '…' : nome).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    let s = defs() + `<rect width="480" height="420" fill="#FFFFFF"/>`;
    s += `<text x="22" y="34" font-size="12.5" font-weight="800" letter-spacing="1.1" fill="#0F1712">${short}</text>`;
    s += `<text x="22" y="61" font-size="23" font-weight="800" letter-spacing="-.4" fill="#0F1712">${a.ph}</text>`;
    const cw = eqL.length * 6.6 + 26;
    s += `<rect x="${f(466 - cw)}" y="24" width="${f(cw)}" height="26" rx="13" fill="#F4F4F1"/><circle cx="${f(466 - cw + 13)}" cy="37" r="3.5" fill="${RED}"/><text x="${f(466 - cw + 21)}" y="41.5" font-size="11.5" font-weight="700" fill="#0F1712">${eqL}</text>`;
    s += figSvg(X, a);
    if (X.grip) s += gripBadge(X.grip);
    const [pr, au] = X.mus;
    s += `<rect x="312" y="84" width="154" height="320" rx="22" fill="#F4F4F1"/>`;
    s += `<text x="352" y="110" text-anchor="middle" font-size="10.5" font-weight="800" letter-spacing=".8" fill="#6B756E">FRENTE</text><text x="426" y="110" text-anchor="middle" font-size="10.5" font-weight="800" letter-spacing=".8" fill="#6B756E">COSTAS</text>`;
    s += mapFig(352, 120, 1.12, false, pr, au) + mapFig(426, 120, 1.12, true, pr, au);
    const wrap = (txt, x, y) => { const w = txt.split(' '), lines = ['']; for (const t of w) { if ((lines[lines.length - 1] + ' ' + t).trim().length > 22) lines.push(t); else lines[lines.length - 1] = (lines[lines.length - 1] + ' ' + t).trim(); }
      return lines.slice(0, 2).map((l, i) => `<text x="${x}" y="${y + i * 12.5}" font-size="10.5" font-weight="600" fill="#6B756E">${l}</text>`).join(''); };
    let ly = au.length ? 324 : 344;
    s += `<circle cx="328" cy="${ly - 4}" r="5" fill="${RED}"/><text x="339" y="${ly}" font-size="12" font-weight="800" fill="#0F1712">Principal</text>` + wrap(list(pr), 339, ly + 14);
    if (au.length) { ly += 40; s += `<circle cx="328" cy="${ly - 4}" r="5" fill="#F4A39C"/><text x="339" y="${ly}" font-size="12" font-weight="800" fill="#0F1712">Auxiliar</text>` + wrap(list(au), 339, ly + 14); }
    SFX = ''; NOEQ = false; return s;
  }
  // quadrinho "pegada" no canto de cima: como a mão segura (de frente)
  const GRIPS = { barra: ['Supinada', 'com barra'], halter: ['Supinada', 'alternada'], neutra: ['Neutra', 'martelo'],
    polia: ['Supinada', 'barra na polia'], corda: ['Neutra', 'na corda'], pronada: ['Pronada', 'barra reta'], unil: ['Pronada', 'um braço por vez'], unilsup: ['Supinada', 'um braço por vez'] };
  function gripBadge(k) {
    const L = GRIPS[k]; if (!L) return '';
    const x = 18, y = 74, w = 100, h = 100, cx = x + w / 2, cy = y + 27;
    const fist = px => `<circle cx="${f(px)}" cy="${f(cy)}" r="7.5" fill="${INK}"/>`, arm = px => `<path d="M${f(px)},${f(cy + 4)} L${f(px)},${f(cy + 24)}" stroke="${INK}" stroke-width="9" stroke-linecap="round"/>`;
    let g = `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="14" fill="#F4F4F1"/>`;
    if (k === 'barra' || k === 'pronada' || k === 'polia') {
      if (k === 'polia') g += `<path d="M${f(cx)},${f(cy + 26)} L${f(cx)},${f(cy + 2)}" stroke="#8C958F" stroke-width="1.8"/>`;
      g += arm(cx - 18) + arm(cx + 18) + bar([cx - 38, cy], [cx + 38, cy], 4, GRIP);
      if (k !== 'polia') g += rect(cx - 44, cy - 11, 6, 22, 2, PLATE) + rect(cx + 38, cy - 11, 6, 22, 2, PLATE);
      g += fist(cx - 18) + fist(cx + 18);
    } else if (k === 'halter') {
      [[cx - 24, -5], [cx + 24, 5]].forEach(([px, dy]) => { const yy = cy + dy;
        g += `<path d="M${f(px)},${f(yy + 4)} L${f(px)},${f(yy + 22)}" stroke="${INK}" stroke-width="9" stroke-linecap="round"/>` + bar([px - 12, yy], [px + 12, yy], 3.5, GRIP) +
          rect(px - 17, yy - 9, 5, 18, 2, PLATE) + rect(px + 12, yy - 9, 5, 18, 2, PLATE) + `<circle cx="${f(px)}" cy="${f(yy)}" r="7.5" fill="${INK}"/>`; });
    } else if (k === 'neutra') {
      for (const px of [cx - 20, cx + 20]) g += arm(px) + bar([px, cy - 13], [px, cy + 13], 3.5, GRIP) + rect(px - 9, cy - 18, 18, 5, 2, PLATE) + rect(px - 9, cy + 13, 18, 5, 2, PLATE) + fist(px);
    } else if (k === 'unil' || k === 'unilsup') {
      g += `<path d="M${f(cx)},${f(cy - 24)} L${f(cx)},${f(cy - 6)}" stroke="#8C958F" stroke-width="1.8"/>` + arm(cx) + bar([cx - 15, cy], [cx + 15, cy], 4, GRIP) + fist(cx);
    } else if (k === 'corda') {
      g += `<path d="M${f(cx)},${f(cy - 22)} L${f(cx - 16)},${f(cy)} M${f(cx)},${f(cy - 22)} L${f(cx + 16)},${f(cy)}" stroke="${GRIP}" stroke-width="3.5" stroke-linecap="round" fill="none"/>` + arm(cx - 16) + arm(cx + 16) + fist(cx - 16) + fist(cx + 16);
    }
    g += `<text x="${f(cx)}" y="${f(y + 64)}" text-anchor="middle" font-size="9" font-weight="800" letter-spacing=".9" fill="#6B756E">PEGADA</text>`;
    g += `<text x="${f(cx)}" y="${f(y + 80)}" text-anchor="middle" font-size="14" font-weight="800" fill="#0F1712">${L[0]}</text>`;
    g += `<text x="${f(cx)}" y="${f(y + 93)}" text-anchor="middle" font-size="10.5" font-weight="600" fill="#6B756E">${L[1]}</text>`;
    return g;
  }
  function arrow(c, d) {
    const n = [-d[1], d[0]], p1 = add(c, d, -9), p2 = add(c, d, 9);
    return `<g stroke="${RED}" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" fill="none"><path d="M${P(p1)} L${P(p2)}"/><path d="M${P(add(add(p2, d, -5), n, 4.5))} L${P(p2)} L${P(add(add(p2, d, -5), n, -4.5))}"/></g>`;
  }
  return { scene, figure, pick, EX, LIST, label: k => LABELS[k] || '', FIGVB: '4 66 306 340' };
})();

const EX3D = { crossalta:'ex-crossalta-m' }; // animações 3D prontas (arquivo em src/b3)
// toca a animação da execução que estiver aberta na tela
(function(){
  let t0 = performance.now(), last = 0, cur = null;
  function tick(now){
    requestAnimationFrame(tick);
    const box = document.querySelector('.exa');
    if(!box){ cur = null; return; }
    const hit = findEx(box.dataset.ex); if(!hit) return;
    const v3 = EM_TESTE && EX3D[XA.pick(hit.e)]; // exercício com animação 3D (vídeo em vai e volta): por enquanto só no app de teste
    if(v3){ if(box.dataset.v3 !== v3){ box.dataset.v3 = v3; box.hidden = false; box.innerHTML = '<video src="b3/'+v3+'.mp4?v=198" autoplay loop muted playsinline disablepictureinpicture></video>'; } cur = null; return; }
    if(cur !== box){ cur = box; t0 = now; last = 0; }
    const still = RM();
    if(still ? box.dataset.drawn === '1' : now - last < 33) return;
    last = now; box.dataset.drawn = '1';
    const html = XA.scene(hit.e, (now - t0) / 1000, still);
    box.hidden = !html; // exercício sem animação (escolhido pelo personal ou sem correspondência)
    if(html) box.firstElementChild.innerHTML = html;
  }
  requestAnimationFrame(tick);
})();
})();

