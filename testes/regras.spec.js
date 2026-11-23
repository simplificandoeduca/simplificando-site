// 🔒 Regras do Firebase (REGRAS-FIRESTORE.txt) no emulador: cada um só lê e grava o que pode.
// Roda num projeto separado do emulador (demo-regras), para não misturar com os outros testes.
const { test, expect } = require('./apoio');
const PRJ = 'demo-regras', BASE = `http://127.0.0.1:8080/v1/projects/${PRJ}/databases/(default)/documents/`;
const b64 = o => Buffer.from(JSON.stringify(o)).toString('base64url');
const tok = uid => { const n = Math.floor(Date.now() / 1000); return b64({ alg: 'none', typ: 'JWT' }) + '.' + b64({ sub: uid, user_id: uid, iss: 'https://securetoken.google.com/' + PRJ, aud: PRJ, iat: n, exp: n + 3600, auth_time: n, firebase: { sign_in_provider: 'password' } }) + '.'; };
const val = v => v === null ? { nullValue: null } : typeof v === 'boolean' ? { booleanValue: v } : Number.isInteger(v) ? { integerValue: String(v) } : typeof v === 'number' ? { doubleValue: v } : typeof v === 'string' ? { stringValue: v }
  : Array.isArray(v) ? { arrayValue: { values: v.map(val) } } : { mapValue: { fields: Object.fromEntries(Object.entries(v).map(([k, x]) => [k, val(x)])) } };
const H = uid => uid === 'ADMIN' ? { Authorization: 'Bearer owner' } : uid ? { Authorization: 'Bearer ' + tok(uid) } : {};
async function grava(uid, path, obj) { const r = await fetch(BASE + path, { method: 'PATCH', headers: { ...H(uid), 'Content-Type': 'application/json' }, body: JSON.stringify({ fields: Object.fromEntries(Object.entries(obj).map(([k, v]) => [k, val(v)])) }) }); return r.status; }
async function le(uid, path) { return (await fetch(BASE + path, { headers: H(uid) })).status; }
const ok = 200, nao = 403;
const id = Math.random().toString(36).slice(2, 8), DONO = 'dono-' + id, ALU = 'alu-' + id, OUTRO = 'outro-' + id, AUT = 'aut-' + id;

test.beforeAll(async () => {
  // o dono do painel deste projeto de teste e uma aluna com acesso liberado
  await fetch(BASE + 'sistema/dono', { method: 'DELETE', headers: H('ADMIN') });
  expect(await grava(DONO, 'sistema/dono', { uid: DONO })).toBe(ok);
  expect(await grava('ADMIN', 'appAlunos/' + ALU, { aid: 'a1', ativo: true, nome: 'Ana' })).toBe(ok);
  expect(await grava('ADMIN', 'alunos/a1', { nome: 'Ana' })).toBe(ok);
});
test.describe.configure({ mode: 'serial' });

test('dono do painel: qualquer um vê quem é; ninguém troca depois de criado', async () => {
  expect(await le(null, 'sistema/dono')).toBe(ok);
  expect(await grava(OUTRO, 'sistema/dono', { uid: OUTRO })).toBe(nao);
});
test('dados do painel (alunos, exercícios, modelos, config): só o dono', async () => {
  expect(await le(null, 'alunos/a1')).toBe(nao);
  expect(await le(ALU, 'alunos/a1')).toBe(nao);
  expect(await le(DONO, 'alunos/a1')).toBe(ok);
  expect(await grava(OUTRO, 'config/geral', { x: 1 })).toBe(nao);
  expect(await grava(DONO, 'config/geral', { x: 1 })).toBe(ok);
});
test('app do aluno: cada um lê só o próprio treino, e só com o acesso liberado', async () => {
  expect(await le(ALU, 'appAlunos/' + ALU)).toBe(ok);
  expect(await le(OUTRO, 'appAlunos/' + ALU)).toBe(nao);
  await grava('ADMIN', 'appAlunos/' + ALU, { aid: 'a1', ativo: false, nome: 'Ana' });
  expect(await le(ALU, 'appAlunos/' + ALU)).toBe(nao);
  await grava('ADMIN', 'appAlunos/' + ALU, { aid: 'a1', ativo: true, nome: 'Ana' });
});
test('perfis: cada um grava o seu (sem dado de saúde); só o dono lê todos', async () => {
  expect(await grava(AUT, 'perfis/' + AUT, { tipo: 'autonomo', nome: 'Bia', acomp: false })).toBe(ok);
  expect(await grava(AUT, 'perfis/' + AUT, { tipo: 'autonomo', nome: 'Bia', peso: 70 })).toBe(nao);
  expect(await grava(OUTRO, 'perfis/' + AUT, { tipo: 'autonomo', nome: 'X' })).toBe(nao);
  expect(await le(OUTRO, 'perfis/' + AUT)).toBe(nao);
  expect(await le(DONO, 'perfis/' + AUT)).toBe(ok);
});
test('treinos de quem não tem personal: só a pessoa; o dono só vê se ela permitiu (compartilhar)', async () => {
  expect(await grava(AUT, 'autonomos/' + AUT, { s: '{}', em: 1 })).toBe(ok);
  expect(await le(OUTRO, 'autonomos/' + AUT)).toBe(nao);
  expect(await le(DONO, 'autonomos/' + AUT)).toBe(nao);
  await grava(AUT, 'perfis/' + AUT, { tipo: 'autonomo', nome: 'Bia', acomp: true });
  expect(await le(DONO, 'autonomos/' + AUT)).toBe(ok);
});
test('progresso do aluno com personal: só o próprio (com acesso liberado); o dono lê', async () => {
  expect(await grava(ALU, 'progresso/' + ALU, { aid: 'a1', s: '{}', em: 1 })).toBe(ok);
  expect(await grava(ALU, 'progresso/' + ALU, { aid: 'outro', s: '{}', em: 1 })).toBe(nao);
  expect(await grava(OUTRO, 'progresso/' + OUTRO, { aid: 'a1', s: '{}', em: 1 })).toBe(nao);
  expect(await le(DONO, 'progresso/' + ALU)).toBe(ok);
  expect(await le(OUTRO, 'progresso/' + ALU)).toBe(nao);
});
test('sugestões, avaliações e stories: qualquer conta envia o seu; só o dono lê', async () => {
  expect(await grava(AUT, 'sugestoes/s-' + id, { uid: AUT, nome: 'Bia', tipo: 'autonomo', texto: 'Ideia', em: 1 })).toBe(ok);
  expect(await grava(AUT, 'sugestoes/s2-' + id, { uid: AUT, texto: '', em: 1 })).toBe(nao);
  expect(await grava(AUT, 'sugestoes/s3-' + id, { uid: OUTRO, texto: 'x', em: 1 })).toBe(nao);
  expect(await le(AUT, 'sugestoes/s-' + id)).toBe(nao);
  expect(await le(DONO, 'sugestoes/s-' + id)).toBe(ok);
  expect(await grava(AUT, 'avaliacoes/a-' + id, { uid: AUT, nome: 'Bia', tipo: 'autonomo', notas: { treino: 5 }, texto: '', em: 1 })).toBe(ok);
  expect(await grava(AUT, 'avaliacoes/a2-' + id, { uid: AUT, notas: { preco: 5 }, texto: '', em: 1 })).toBe(nao);
  expect(await grava(AUT, 'storiesUso/st-' + id, { uid: AUT, modelo: 'crono', em: 1 })).toBe(ok);
  expect(await grava(AUT, 'storiesUso/st2-' + id, { uid: AUT, modelo: 'inventado', em: 1 })).toBe(nao);
});
test('recado no app: só o dono escreve; a pessoa lê o seu e só marca como lido; foto na sugestão até o limite', async () => {
  const R = { texto: 'Oi! O app foi atualizado.', em: 1000, sug: true };
  expect(await grava(AUT, 'recados/' + AUT, R)).toBe(nao);
  expect(await grava(DONO, 'recados/' + AUT, R)).toBe(ok);
  expect(await le(AUT, 'recados/' + AUT)).toBe(ok);
  expect(await le(OUTRO, 'recados/' + AUT)).toBe(nao);
  expect(await grava(AUT, 'recados/' + AUT, { ...R, lido: 2000 })).toBe(ok);
  expect(await grava(AUT, 'recados/' + AUT, { ...R, texto: 'mudei', lido: 2000 })).toBe(nao);
  expect(await grava(AUT, 'recados/' + AUT + 'x', R)).toBe(nao);
  expect(await grava(AUT, 'sugestoes/sf-' + id, { uid: AUT, nome: 'A', tipo: 'autonomo', texto: '', em: 1, ver: 'x', foto: 'data:image/jpeg;base64,AAAA' })).toBe(ok);
  expect(await grava(AUT, 'sugestoes/sf2-' + id, { uid: AUT, nome: 'A', tipo: 'autonomo', texto: '', em: 1, ver: 'x' })).toBe(nao);
  expect(await grava(AUT, 'sugestoes/sf3-' + id, { uid: AUT, nome: 'A', tipo: 'autonomo', texto: 'x', em: 1, ver: 'x', foto: 'a'.repeat(820000) })).toBe(nao);
});
test('senha nova do aluno (6 números): só a própria e só número', async () => {
  expect(await grava(ALU, 'codigos/' + ALU, { codigo: '123456', em: 1 })).toBe(ok);
  expect(await grava(ALU, 'codigos/' + ALU, { codigo: '12ab56', em: 1 })).toBe(nao);
  expect(await grava(OUTRO, 'codigos/' + ALU, { codigo: '123456', em: 1 })).toBe(nao);
});
