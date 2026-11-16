// 📲 O que aparece no celular: os service workers (site/treino/sw.js e site/personal/sw.js) montam cada notificação.
// Roda o código de verdade num "celular de mentira" (sem navegador) e confere qual aviso aparece em cada situação.
const { test, expect } = require('./apoio');
const fs = require('fs'), path = require('path');

function celular(arquivo, url) {
  const code = fs.readFileSync(path.join(__dirname, '..', 'site', arquivo), 'utf8');
  const store = new Map(), shown = [], L = {}, fetches = []; let resposta = null;
  const caches = { open: async () => ({ match: async k => store.has(k) ? { json: async () => JSON.parse(store.get(k)) } : undefined, put: async (k, r) => store.set(k, await r.text()), delete: async k => store.delete(k) }), keys: async () => [] };
  const self = { location: url, skipWaiting() {}, registration: { showNotification: async (t, o) => shown.push({ t, b: o.body, url: o.data && o.data.url, botoes: (o.actions || []).map(a => a.title) }), active: null }, addEventListener: (n, f) => { L[n] = f; }, clients: { claim() {}, matchAll: async () => [] } };
  const fetchF = async (u, o) => { fetches.push(u); return { json: async () => resposta }; };
  new Function('self', 'caches', 'Response', 'fetch', code)(self, caches, Response, fetchF);
  const push = async () => { let w; L.push({ waitUntil: x => { w = x; } }); await w; return shown.splice(0); };
  const toque = async (acao, data = {}) => { let w; L.notificationclick({ action: acao, notification: { close() {}, data }, waitUntil: x => { w = x; } }); await w; };
  return { store, push, toque, fetches, setResposta: r => { resposta = r; } };
}
const hoje = (n = 0) => { const d = new Date(Date.now() + n * 864e5); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };

test('app do aluno: sem nada pendente, o aviso é o lembrete de água', async () => {
  const c = celular('treino/sw.js', 'https://simplificandoeduca.com/treino/sw.js');
  const [n] = await c.push(); expect(n.t).toMatch(/água|garrafinha|copo/i); expect(n.url).toBe('/treino/#agua');
});

test('fim do descanso: "Bora, Joana! 💪" uma vez, com os botões e o +15 s pedindo um aviso novo', async () => {
  const c = celular('treino/sw.js', 'https://simplificandoeduca.com/treino/sw.js');
  c.store.set('/treino/__descanso', JSON.stringify({ fim: Date.now(), k: 'abc123', id: 'robo1234567890ab', nome: 'Joana', ex: 'Puxada frente', s: 3, n: 4, av: 0 }));
  const [n] = await c.push();
  expect(n.t).toBe('Bora, Joana! 💪'); expect(n.b).toBe('Seu descanso acabou. Próxima: Puxada frente, série 3 de 4.'); expect(n.botoes).toEqual(['Abrir treino', '+15 s']);
  expect((await c.push())[0].t).toMatch(/água|garrafinha|copo/i);
  await c.toque('mais'); expect(c.fetches.some(u => u.includes('acao=descanso'))).toBe(true);
  expect(JSON.parse(c.store.get('/treino/__descanso')).av).toBe(0);
});

test('treino esquecido aberto: avisa com quase 3 h, só uma vez', async () => {
  const c = celular('treino/sw.js', 'https://simplificandoeduca.com/treino/sw.js');
  c.store.set('/treino/__treino', JSON.stringify({ ini: Date.now() - 60 * 60e3, t: 'B', nome: 'Superiores 1', av: 0 }));
  expect((await c.push())[0].t).toMatch(/água|garrafinha|copo/i);
  c.store.set('/treino/__treino', JSON.stringify({ ini: Date.now() - 182 * 60e3, t: 'B', nome: 'Superiores 1', av: 0 }));
  const [n] = await c.push(); expect(n.t).toBe('Seu treino ainda está aberto ⏱️'); expect(n.b).toContain('3h02'); expect(n.url).toBe('/treino/#treino-aberto');
  expect((await c.push())[0].t).not.toContain('treino ainda');
});

test('lembrete do treino do dia: só da 2ª semana, depois do horário daquele dia e se ainda não treinou', async () => {
  const c = celular('treino/sw.js', 'https://simplificandoeduca.com/treino/sw.js');
  const d = new Date(), min = d.getHours() * 60 + d.getMinutes(), wd = d.getDay();
  test.skip(min < 20, 'logo depois da meia-noite');
  const base = { on: 1, h: 1200, hs: { [wd]: min - 10 }, dias: [wd], feito: '', pausa: '', desde: hoje(-1), nome: 'Joana', t: 'B', tn: 'Empurrar', av: '' };
  const tenta = async extra => { c.store.set('/treino/__lembrete', JSON.stringify({ ...base, ...extra })); return (await c.push())[0].t; };
  expect(await tenta({ desde: hoje(3) })).not.toContain('Bora treinar');
  expect(await tenta({ hs: { [wd]: min + 40 } })).not.toContain('Bora treinar');
  expect(await tenta({ feito: hoje() })).not.toContain('Bora treinar');
  expect(await tenta({})).toBe('Bora treinar, Joana? 💪');
});

test('painel do personal: aviso de conta nova com nome, objetivo e hora; várias juntas viram um só', async () => {
  const c = celular('personal/sw.js', 'https://simplificandoeduca.com/personal/sw.js');
  c.store.set('/personal/__dono', JSON.stringify({ k: 'k'.repeat(32), ult: 0 }));
  const em = Math.floor(new Date('2026-11-16T18:40:00').getTime() / 1000);
  c.setResposta({ ok: true, novos: [{ nome: 'Joana Lima', ed: 'emagrecer', plano: 'ia', em }] });
  let [n] = await c.push();
  expect(n.t).toBe('Nova conta no Simplificando 🎉'); expect(n.b).toBe('Joana Lima · Emagrecer · treino montado pela IA · 18:40'); expect(n.url).toBe('/personal/#perfis');
  c.setResposta({ ok: true, novos: [{ nome: 'Ana', ed: 'hipertrofia', plano: 'meu', em: em + 60 }, { nome: 'Bruno', ed: 'corrida', plano: 'ia', em: em + 90 }] });
  [n] = await c.push(); expect(n.t).toBe('2 contas novas no Simplificando 🎉'); expect(n.b).toBe('Ana, Bruno.');
  c.setResposta(null); [n] = await c.push(); expect(n.b).toBe('Toque para ver no painel.');
});
