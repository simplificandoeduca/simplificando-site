<?php
/* =====================================================================
   Play Space Finanças 2.1 (Play Space 1.60): mais usabilidade, como num sistema de gestão financeira
   Pedido do dono: "dar mais usabilidade, as informações mais claras, como um sistema de gestão de finanças; registrar fornecedor; e
   uma aba de planilhas: importo uma planilha modelo, o sistema lê, copia tudo o que foi escrito e os próximos campos são alimentados
   direto no sistema (planilha 1, planilha 2, planilha 3), no mesmo visual do sistema; a planilha em si não precisa aparecer".
   - Fornecedores (fin_fornecedores): o cadastro (nome, CNPJ/CPF, contato, telefone, e-mail, Pix, banco, o centro de custo e o grupo de
     costume) ligado à despesa; a ficha mostra quanto foi pago e o que está a pagar.
   - Contas a pagar: a despesa pode ficar "a pagar", com o vencimento; "Pagar" marca o dia e a forma. Repetir todo mês (aluguel, salário).
   - O painel financeiro do mês: receitas, despesas, resultado, o que vence, os maiores fornecedores e os últimos lançamentos.
   - Planilhas (fin_planilhas e fin_planilha_linhas): a tela lê o arquivo (.xlsx), acha o cabeçalho, as colunas e o tipo de cada uma
     (data, valor, número, lista, texto) e manda as linhas; aqui ficam as colunas e as linhas (cada linha com quem lançou e corrigiu).
     O arquivo não fica guardado: o que fica é a planilha dentro do sistema.
   - Nada se perde: tirar marca quem tirou e quando.
   ===================================================================== */
const PL_TIPOS = ['texto', 'data', 'valor', 'numero', 'lista'];

function fl21Preparar(PDO $db): void {
  $db->exec("CREATE TABLE IF NOT EXISTS fin_fornecedores (id INTEGER PRIMARY KEY, nome TEXT NOT NULL, documento TEXT NOT NULL DEFAULT '', contato TEXT NOT NULL DEFAULT '',
    telefone TEXT NOT NULL DEFAULT '', email TEXT NOT NULL DEFAULT '', pix TEXT NOT NULL DEFAULT '', banco TEXT NOT NULL DEFAULT '', centro TEXT NOT NULL DEFAULT '',
    grupo TEXT NOT NULL DEFAULT '', obs TEXT NOT NULL DEFAULT '', criado_por TEXT NOT NULL DEFAULT '', criado_em TEXT NOT NULL DEFAULT '', alterado_por TEXT NOT NULL DEFAULT '',
    alterado_em TEXT NOT NULL DEFAULT '', removido_por TEXT NOT NULL DEFAULT '', removido_em TEXT)");
  $db->exec("CREATE TABLE IF NOT EXISTS fin_planilhas (id INTEGER PRIMARY KEY, nome TEXT NOT NULL, colunas TEXT NOT NULL, arquivo TEXT NOT NULL DEFAULT '',
    criado_por TEXT NOT NULL DEFAULT '', criado_em TEXT NOT NULL DEFAULT '', alterado_por TEXT NOT NULL DEFAULT '', alterado_em TEXT NOT NULL DEFAULT '', removido_por TEXT NOT NULL DEFAULT '', removido_em TEXT)");
  $db->exec("CREATE TABLE IF NOT EXISTS fin_planilha_linhas (id INTEGER PRIMARY KEY, planilha_id INTEGER NOT NULL, dados TEXT NOT NULL, origem TEXT NOT NULL DEFAULT 'manual',
    criado_por TEXT NOT NULL DEFAULT '', criado_em TEXT NOT NULL DEFAULT '', alterado_por TEXT NOT NULL DEFAULT '', alterado_em TEXT NOT NULL DEFAULT '', removido_por TEXT NOT NULL DEFAULT '', removido_em TEXT)");
  $db->exec('CREATE INDEX IF NOT EXISTS fin_pl_linhas_p ON fin_planilha_linhas (planilha_id, removido_em)');
}
/* o mesmo dia, k meses depois (31/01 + 1 mês = 28 ou 29/02) */
function flMaisMeses(string $ymd, int $k): string {
  [$a, $m, $d] = array_map('intval', explode('-', $ymd)); $m += $k; $a += intdiv($m - 1, 12); $m = ($m - 1) % 12 + 1;
  return sprintf('%04d-%02d-%02d', $a, $m, min($d, (int)date('t', mktime(12, 0, 0, $m, 1, $a))));
}
function flHoje(): string { return date('Y-m-d', agoraTs()); }

/* ---------- fornecedores ---------- */
function fnPublico(array $f): array {
  $o = []; foreach (['id', 'nome', 'documento', 'contato', 'telefone', 'email', 'pix', 'banco', 'centro', 'grupo', 'obs', 'criado_por', 'criado_em', 'alterado_por', 'alterado_em'] as $k) $o[$k] = $k === 'id' ? (int)$f[$k] : (string)$f[$k];
  foreach (['n' => 0, 'ano' => 0.0, 'total' => 0.0, 'aberto' => 0.0, 'ult' => ''] as $k => $v) if (array_key_exists($k, $f)) $o[$k] = is_float($v) ? round((float)$f[$k], 2) : (is_int($v) ? (int)$f[$k] : (string)$f[$k]);
  return $o;
}
function fnDoc(string $d): string {
  $n = preg_replace('/\D/', '', $d); if ($n === '') return '';
  if (strlen($n) === 14) return substr($n, 0, 2) . '.' . substr($n, 2, 3) . '.' . substr($n, 5, 3) . '/' . substr($n, 8, 4) . '-' . substr($n, 12);
  if (strlen($n) === 11) return substr($n, 0, 3) . '.' . substr($n, 3, 3) . '.' . substr($n, 6, 3) . '-' . substr($n, 9);
  falha('Confira o CNPJ (14 números) ou o CPF (11 números).');
}
function fnAcha(string $nome): ?int {
  $k = flChave($nome); if ($k === '') return null;
  foreach (db()->query('SELECT id, nome FROM fin_fornecedores WHERE removido_em IS NULL')->fetchAll() as $f) if (flChave((string)$f['nome']) === $k) return (int)$f['id'];
  return null;
}
/* o fornecedor da despesa: o escolhido na lista, ou o nome digitado (achado no cadastro, ou cadastrado agora só com o nome) */
function fnResolve(array $x, array $eu): ?int {
  $id = (int)($x['fornecedor_id'] ?? 0);
  if ($id) { $q = db()->prepare('SELECT id FROM fin_fornecedores WHERE id = ?'); $q->execute([$id]); if ($q->fetchColumn()) return $id; }
  $nome = flTxt($x['fornecedor'] ?? '', 120); if ($nome === '') return null;
  $id = fnAcha($nome); if ($id) return $id;
  db()->prepare('INSERT INTO fin_fornecedores (nome, criado_por, criado_em) VALUES (?, ?, ?)')->execute([$nome, (string)$eu['nome'], agora()]);
  return (int)db()->lastInsertId();
}
function fnListar(array $in): array {
  flPreparar(); $ano = substr(flHoje(), 0, 4); $p = [$ano]; $w = 'f.removido_em IS NULL';
  $b = flTxt($in['busca'] ?? '', 60); if ($b !== '') { $t = '%' . str_replace(['%', '_'], ['', ''], $b) . '%'; $w .= ' AND (f.nome LIKE ? OR f.documento LIKE ? OR f.contato LIKE ? OR f.centro LIKE ?)'; array_push($p, $t, $t, $t, $t); }
  $base = "FROM fin_lanc l WHERE l.fornecedor_id = f.id AND l.removido_em IS NULL AND l.tipo = 'despesa'";
  $q = db()->prepare("SELECT f.*, (SELECT COUNT(*) $base) n, (SELECT COALESCE(SUM(l.valor), 0) $base AND l.situacao != 'aberto' AND substr(l.data, 1, 4) = ?) ano,
      (SELECT COALESCE(SUM(l.valor), 0) $base AND l.situacao != 'aberto') total, (SELECT COALESCE(SUM(l.valor), 0) $base AND l.situacao = 'aberto') aberto, (SELECT COALESCE(MAX(l.data), '') $base) ult
    FROM fin_fornecedores f WHERE $w ORDER BY f.nome COLLATE NOCASE LIMIT 1000");
  $q->execute($p); return ['fornecedores' => array_map('fnPublico', $q->fetchAll()), 'ano' => (int)$ano];
}
function fnVer(array $in): array {
  flPreparar(); $id = (int)($in['id'] ?? 0); $q = db()->prepare('SELECT * FROM fin_fornecedores WHERE id = ?'); $q->execute([$id]); $f = $q->fetch(); if (!$f) falha('Fornecedor não encontrado.', 404);
  $q = db()->prepare(FL_SEL . " WHERE l.fornecedor_id = ? AND l.removido_em IS NULL AND l.tipo = 'despesa' ORDER BY l.data DESC, l.id DESC LIMIT 300"); $q->execute([$id]);
  $t = db()->prepare("SELECT substr(data, 1, 4) a, COALESCE(SUM(CASE WHEN situacao != 'aberto' THEN valor END), 0) pago, COALESCE(SUM(CASE WHEN situacao = 'aberto' THEN valor END), 0) aberto, COUNT(*) n
    FROM fin_lanc WHERE fornecedor_id = ? AND removido_em IS NULL AND tipo = 'despesa' GROUP BY a ORDER BY a DESC"); $t->execute([$id]);
  return ['fornecedor' => fnPublico($f) + ['removido' => $f['removido_em'] !== null], 'despesas' => array_map('flPublico', $q->fetchAll()),
    'anos' => array_map(fn($x) => ['ano' => (int)$x['a'], 'pago' => round((float)$x['pago'], 2), 'aberto' => round((float)$x['aberto'], 2), 'n' => (int)$x['n']], $t->fetchAll())];
}
function fnSalvar(array $in, array $eu): array {
  flPreparar(); $db = db(); $id = (int)($in['id'] ?? 0); $x = (array)($in['fornecedor'] ?? []);
  $nome = flTxt($x['nome'] ?? '', 120); if ($nome === '') falha('Escreva o nome do fornecedor.');
  $outro = fnAcha($nome); if ($outro && $outro !== $id) falha('Já existe um fornecedor com este nome.', 409);
  $em = flTxt($x['email'] ?? '', 120); if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) falha('Confira o e-mail.');
  $L = flListas();
  $r = ['nome' => $nome, 'documento' => fnDoc((string)($x['documento'] ?? '')), 'contato' => flTxt($x['contato'] ?? '', 80), 'telefone' => flTxt($x['telefone'] ?? '', 40), 'email' => mb_strtolower($em),
    'pix' => flTxt($x['pix'] ?? '', 120), 'banco' => flTxt($x['banco'] ?? '', 160), 'centro' => flCanon((string)($x['centro'] ?? ''), $L['centro']), 'grupo' => flCanon((string)($x['grupo'] ?? ''), $L['grupo']), 'obs' => flTxt($x['obs'] ?? '', 500)];
  if ($id) { $q = $db->prepare('SELECT id FROM fin_fornecedores WHERE id = ? AND removido_em IS NULL'); $q->execute([$id]); if (!$q->fetchColumn()) falha('Fornecedor não encontrado.', 404);
    $db->prepare('UPDATE fin_fornecedores SET ' . implode(', ', array_map(fn($c) => "$c = ?", array_keys($r))) . ', alterado_por = ?, alterado_em = ? WHERE id = ?')->execute(array_merge(array_values($r), [(string)$eu['nome'], agora(), $id])); }
  else { $db->prepare('INSERT INTO fin_fornecedores (' . implode(', ', array_keys($r)) . ', criado_por, criado_em) VALUES (' . implode(', ', array_fill(0, count($r), '?')) . ', ?, ?)')->execute(array_merge(array_values($r), [(string)$eu['nome'], agora()])); $id = (int)$db->lastInsertId(); }
  if ($r['centro'] !== '' || $r['grupo'] !== '') flAprende([['tipo' => 'despesa', 'centro' => $r['centro'], 'grupo' => $r['grupo']]]);
  $q = $db->prepare('SELECT * FROM fin_fornecedores WHERE id = ?'); $q->execute([$id]); return ['fornecedor' => fnPublico($q->fetch()), 'listas' => flListas()];
}
function fnRemover(array $in, array $eu): array {
  flPreparar(); $q = db()->prepare('UPDATE fin_fornecedores SET removido_por = ?, removido_em = ? WHERE id = ? AND removido_em IS NULL'); $q->execute([(string)$eu['nome'], agora(), (int)($in['id'] ?? 0)]);
  if (!$q->rowCount()) falha('Fornecedor não encontrado.', 404); return [];
}
/* a lista curta (para escolher na despesa): o nome e o costume (centro de custo e grupo) */
function fnNomes(): array {
  return array_map(fn($f) => ['id' => (int)$f['id'], 'nome' => (string)$f['nome'], 'centro' => (string)$f['centro'], 'grupo' => (string)$f['grupo']],
    db()->query('SELECT id, nome, centro, grupo FROM fin_fornecedores WHERE removido_em IS NULL ORDER BY nome COLLATE NOCASE LIMIT 2000')->fetchAll());
}

/* ---------- contas a pagar ---------- */
function apSomas(): array {
  $h = flHoje(); $s7 = date('Y-m-d', strtotime($h . ' 12:00:00 +7 days'));
  $q = db()->prepare("SELECT COUNT(*) n, COALESCE(SUM(valor), 0) v, SUM(vencimento < ?) nv, COALESCE(SUM(CASE WHEN vencimento < ? THEN valor END), 0) vv, SUM(vencimento = ?) nh, COALESCE(SUM(CASE WHEN vencimento = ? THEN valor END), 0) vh,
      SUM(vencimento > ? AND vencimento <= ?) ns, COALESCE(SUM(CASE WHEN vencimento > ? AND vencimento <= ? THEN valor END), 0) vs FROM fin_lanc WHERE removido_em IS NULL AND tipo = 'despesa' AND situacao = 'aberto'");
  $q->execute([$h, $h, $h, $h, $h, $s7, $h, $s7]); $r = $q->fetch();
  return ['hoje' => $h, 'total' => ['n' => (int)$r['n'], 'v' => round((float)$r['v'], 2)], 'vencidas' => ['n' => (int)$r['nv'], 'v' => round((float)$r['vv'], 2)],
    'vencemHoje' => ['n' => (int)$r['nh'], 'v' => round((float)$r['vh'], 2)], 'semana' => ['n' => (int)$r['ns'], 'v' => round((float)$r['vs'], 2)]];
}
function apListar(array $in): array {
  flPreparar(); $ver = (string)($in['ver'] ?? 'abertas'); $S = apSomas();
  if ($ver === 'pagas') { $mes = preg_match('/^\d{4}-\d{2}$/', (string)($in['mes'] ?? '')) ? (string)$in['mes'] : substr($S['hoje'], 0, 7);
    $q = db()->prepare(FL_SEL . " WHERE l.removido_em IS NULL AND l.tipo = 'despesa' AND l.situacao = 'pago' AND l.vencimento != '' AND substr(COALESCE(NULLIF(l.pago_em, ''), l.data), 1, 7) = ? ORDER BY l.pago_em DESC, l.id DESC LIMIT 1000");
    $q->execute([$mes]); return ['somas' => $S, 'mes' => $mes, 'linhas' => array_map('flPublico', $q->fetchAll())]; }
  $q = db()->query(FL_SEL . " WHERE l.removido_em IS NULL AND l.tipo = 'despesa' AND l.situacao = 'aberto' ORDER BY l.vencimento, l.id LIMIT 1000");
  return ['somas' => $S, 'linhas' => array_map('flPublico', $q->fetchAll())];
}
/* pagar: o dia e a forma (e o valor, se mudou: juros, desconto) */
function apPagar(array $in, array $eu): array {
  flPreparar(); $db = db(); $id = (int)($in['id'] ?? 0);
  $q = $db->prepare("SELECT * FROM fin_lanc WHERE id = ? AND removido_em IS NULL AND tipo = 'despesa'"); $q->execute([$id]); $l = $q->fetch(); if (!$l) falha('Conta não encontrada.', 404);
  if ((string)$l['situacao'] !== 'aberto') falha('Esta conta já foi paga.', 409);
  $pe = flData($in['pago_em'] ?? '') ?: flHoje(); $fo = (string)($in['forma'] ?? ''); if (!isset(FL_FORMAS_PAG[$fo])) $fo = (string)$l['forma'];
  $v = array_key_exists('valor', $in) && (string)$in['valor'] !== '' ? flNum($in['valor']) : (float)$l['valor']; if ($v === null || $v < 0.005) falha('Confira o valor pago.');
  $db->prepare("UPDATE fin_lanc SET situacao = 'pago', pago_em = ?, forma = ?, valor = ?, alterado_por = ?, alterado_em = ? WHERE id = ?")->execute([$pe, $fo, round($v, 2), (string)$eu['nome'], agora(), $id]);
  $q = $db->prepare(FL_SEL . ' WHERE l.id = ?'); $q->execute([$id]); return ['lanc' => flPublico($q->fetch())];
}

/* desfazer o pagamento (marcou "pago" sem querer): a conta volta a ser devida, com o vencimento que tinha (ou a data dela) */
function apReabrir(array $in, array $eu): array {
  flPreparar(); $db = db(); $id = (int)($in['id'] ?? 0);
  $q = $db->prepare("SELECT * FROM fin_lanc WHERE id = ? AND removido_em IS NULL AND tipo = 'despesa'"); $q->execute([$id]); $l = $q->fetch(); if (!$l) falha('Conta não encontrada.', 404);
  if ((string)$l['situacao'] === 'aberto') falha('Esta conta já está a pagar.', 409);
  $vc = (string)$l['vencimento'] !== '' ? (string)$l['vencimento'] : (string)$l['data'];
  $db->prepare("UPDATE fin_lanc SET situacao = 'aberto', pago_em = '', vencimento = ?, data = ?, alterado_por = ?, alterado_em = ? WHERE id = ?")->execute([$vc, $vc, (string)$eu['nome'], agora(), $id]);
  $q = $db->prepare(FL_SEL . ' WHERE l.id = ?'); $q->execute([$id]); return ['lanc' => flPublico($q->fetch())];
}

/* ---------- o painel financeiro do mês ---------- */
function flPainel(array $in): array {
  flPreparar(); $db = db(); $mes = preg_match('/^\d{4}-\d{2}$/', (string)($in['mes'] ?? '')) ? (string)$in['mes'] : substr(flHoje(), 0, 7);
  $ant = substr(flMaisMeses($mes . '-01', -1), 0, 7);
  $soma = function (string $m) use ($db) {
    $q = $db->prepare("SELECT tipo, (tipo = 'despesa' AND (grupo = ? OR segmento = ? OR filial = ?)) inv, COALESCE(SUM(valor), 0) v, COUNT(*) n FROM fin_lanc WHERE removido_em IS NULL AND substr(data, 1, 7) = ? GROUP BY tipo, inv");
    $q->execute([FL_INVEST, FL_INVEST, FL_INVEST, $m]); $o = ['receita' => 0.0, 'despesa' => 0.0, 'inv' => 0.0, 'recebimento' => 0.0, 'rede' => 0.0, 'n' => 0];
    foreach ($q->fetchAll() as $r) { $k = $r['tipo'] === 'despesa' && (int)$r['inv'] ? 'inv' : (string)$r['tipo']; if (isset($o[$k])) $o[$k] = round($o[$k] + (float)$r['v'], 2); $o['n'] += (int)$r['n']; }
    $o['resultado'] = round($o['receita'] - $o['despesa'], 2); return $o; };
  $q = $db->prepare("SELECT CAST(substr(data, 9, 2) AS INTEGER) d, tipo, COALESCE(SUM(valor), 0) v FROM fin_lanc WHERE removido_em IS NULL AND substr(data, 1, 7) = ? AND tipo IN ('receita', 'despesa')
      AND NOT (tipo = 'despesa' AND (grupo = ? OR segmento = ? OR filial = ?)) GROUP BY d, tipo"); $q->execute([$mes, FL_INVEST, FL_INVEST, FL_INVEST]);
  $dias = []; foreach ($q->fetchAll() as $r) $dias[] = [(int)$r['d'], (string)$r['tipo'], round((float)$r['v'], 2)];
  $S = apSomas(); $s7 = date('Y-m-d', strtotime($S['hoje'] . ' 12:00:00 +7 days'));
  $q = $db->prepare(FL_SEL . " WHERE l.removido_em IS NULL AND l.tipo = 'despesa' AND l.situacao = 'aberto' AND l.vencimento <= ? ORDER BY l.vencimento, l.id LIMIT 8"); $q->execute([$s7]); $prox = array_map('flPublico', $q->fetchAll());
  $q = $db->prepare("SELECT COALESCE(f.nome, 'Sem fornecedor') nome, l.fornecedor_id id, ROUND(SUM(l.valor), 2) v, COUNT(*) n FROM fin_lanc l LEFT JOIN fin_fornecedores f ON f.id = l.fornecedor_id
      WHERE l.removido_em IS NULL AND l.tipo = 'despesa' AND substr(l.data, 1, 7) = ? AND l.fornecedor_id IS NOT NULL GROUP BY l.fornecedor_id ORDER BY v DESC LIMIT 6"); $q->execute([$mes]);
  $forn = array_map(fn($r) => ['id' => (int)$r['id'], 'nome' => (string)$r['nome'], 'v' => (float)$r['v'], 'n' => (int)$r['n']], $q->fetchAll());
  $q = $db->prepare("SELECT CASE WHEN centro = '' THEN 'Sem centro de custo' ELSE centro END c, ROUND(SUM(valor), 2) v FROM fin_lanc WHERE removido_em IS NULL AND tipo = 'despesa' AND substr(data, 1, 7) = ?
      AND NOT (grupo = ? OR segmento = ? OR filial = ?) GROUP BY c ORDER BY v DESC LIMIT 6"); $q->execute([$mes, FL_INVEST, FL_INVEST, FL_INVEST]);
  $cen = array_map(fn($r) => ['nome' => (string)$r['c'], 'v' => (float)$r['v']], $q->fetchAll());
  $q = $db->query(FL_SEL . " WHERE l.removido_em IS NULL AND l.origem = 'manual' ORDER BY CASE WHEN l.alterado_em > l.criado_em THEN l.alterado_em ELSE l.criado_em END DESC, l.id DESC LIMIT 6"); $ult = array_map('flPublico', $q->fetchAll());
  return ['mes' => $mes, 'atual' => $soma($mes), 'anterior' => $soma($ant), 'dias' => $dias, 'apagar' => $S, 'proximas' => $prox, 'fornecedores' => $forn, 'centros' => $cen, 'ultimos' => $ult];
}

/* ---------- planilhas ---------- */
function plColunas($cols): array {
  if (!is_array($cols) || !$cols) falha('A planilha precisa de pelo menos uma coluna.'); if (count($cols) > 40) falha('No máximo 40 colunas.');
  $o = []; $ids = [];
  foreach (array_values($cols) as $c) { $c = (array)$c; $id = (string)($c['id'] ?? ''); if (!preg_match('/^c\d{1,4}$/', $id) || isset($ids[$id])) falha('Coluna inválida.'); $ids[$id] = 1;
    $nome = flTxt($c['nome'] ?? '', 60); if ($nome === '') falha('Dê um nome a cada coluna.'); $tipo = in_array($c['tipo'] ?? '', PL_TIPOS, true) ? (string)$c['tipo'] : 'texto';
    $op = []; if ($tipo === 'lista') foreach ((array)($c['opcoes'] ?? []) as $v) { $v = flTxt($v, 60); if ($v !== '' && !in_array($v, $op, true) && count($op) < 80) $op[] = $v; }
    $o[] = ['id' => $id, 'nome' => $nome, 'tipo' => $tipo, 'opcoes' => $op, 'oculta' => !empty($c['oculta'])]; }
  return $o;
}
/* uma data em "2026-11-14" ou "14/11/2026" */
function plData($v): string { $s = trim((string)$v); if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m)) $s = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]); return flData($s); }
/* os valores de uma linha, conferidos coluna a coluna; devolve [dados, erro] (a linha vazia é um erro) */
function plDados(array $cols, $x): array {
  $x = is_array($x) ? $x : []; $o = [];
  foreach ($cols as $c) { $v = $x[$c['id']] ?? null; if ($v === null || (is_string($v) && trim($v) === '')) continue;
    if ($c['tipo'] === 'data') { $d = plData($v); if ($d === '') return [null, 'Confira a data em "' . $c['nome'] . '".']; $o[$c['id']] = $d; }
    elseif ($c['tipo'] === 'valor' || $c['tipo'] === 'numero') { $n = flNum($v); if ($n === null) return [null, 'Confira o número em "' . $c['nome'] . '".']; $o[$c['id']] = round($n, $c['tipo'] === 'valor' ? 2 : 4); }
    else $o[$c['id']] = flTxt(is_bool($v) ? ($v ? 'Sim' : 'Não') : $v, 500); }
  return $o ? [$o, null] : [null, 'A linha está vazia.'];
}
/* os valores novos das colunas de lista entram nas opções (para escolher na próxima vez) */
function plAprende(array &$cols, array $linhas): bool {
  $mudou = false; foreach ($cols as &$c) { if ($c['tipo'] !== 'lista') continue; foreach ($linhas as $l) { $v = $l[$c['id']] ?? ''; if ($v !== '' && !in_array($v, $c['opcoes'], true) && count($c['opcoes']) < 80) { $c['opcoes'][] = $v; $mudou = true; } } } unset($c);
  return $mudou;
}
function plPega(int $id): array {
  flPreparar(); $q = db()->prepare('SELECT * FROM fin_planilhas WHERE id = ? AND removido_em IS NULL'); $q->execute([$id]); $p = $q->fetch(); if (!$p) falha('Planilha não encontrada.', 404);
  $p['colunas'] = json_decode((string)$p['colunas'], true) ?: []; return $p;
}
function plPublico(array $p, ?int $n = null): array {
  return ['id' => (int)$p['id'], 'nome' => (string)$p['nome'], 'colunas' => $p['colunas'], 'arquivo' => (string)$p['arquivo'], 'criado_por' => (string)$p['criado_por'], 'criado_em' => (string)$p['criado_em'],
    'alterado_por' => (string)$p['alterado_por'], 'alterado_em' => (string)$p['alterado_em']] + ($n === null ? [] : ['n' => $n]);
}
function plListar(): array {
  flPreparar(); $L = db()->query("SELECT p.*, (SELECT COUNT(*) FROM fin_planilha_linhas l WHERE l.planilha_id = p.id AND l.removido_em IS NULL) n,
      (SELECT MAX(CASE WHEN l.alterado_em > l.criado_em THEN l.alterado_em ELSE l.criado_em END) FROM fin_planilha_linhas l WHERE l.planilha_id = p.id) ult FROM fin_planilhas p WHERE p.removido_em IS NULL ORDER BY p.id")->fetchAll();
  return ['planilhas' => array_map(function ($p) { $n = (int)$p['n']; $u = (string)($p['ult'] ?? ''); $p['colunas'] = json_decode((string)$p['colunas'], true) ?: []; return plPublico($p, $n) + ['ult' => max($u, (string)$p['alterado_em'], (string)$p['criado_em'])]; }, $L)];
}
function plInserir(int $pid, array &$cols, array $linhas, string $origem, array $eu): array {
  $db = db(); $ok = 0; $erros = []; $boas = [];
  $ins = $db->prepare('INSERT INTO fin_planilha_linhas (planilha_id, dados, origem, criado_por, criado_em) VALUES (?, ?, ?, ?, ?)');
  $db->beginTransaction();
  try { foreach ($linhas as $i => $x) { [$d, $e] = plDados($cols, $x); if ($e) { if (count($erros) < 20) $erros[] = ['linha' => (int)((is_array($x) ? ($x['_l'] ?? 0) : 0) ?: $i + 1), 'erro' => $e]; continue; }
      $ins->execute([$pid, json_encode($d, JSON_UNESCAPED_UNICODE), $origem, (string)$eu['nome'], agora()]); $ok++; $boas[] = $d; }
    if (plAprende($cols, $boas)) $db->prepare('UPDATE fin_planilhas SET colunas = ? WHERE id = ?')->execute([json_encode($cols, JSON_UNESCAPED_UNICODE), $pid]);
    $db->commit(); }
  catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  return ['entrou' => $ok, 'erros' => count($linhas) - $ok, 'exemplos' => $erros];
}
function plCriar(array $in, array $eu): array {
  flPreparar(); $db = db(); $cols = plColunas($in['colunas'] ?? []); $linhas = (array)($in['linhas'] ?? []); if (count($linhas) > 2000) falha('Mande no máximo 2000 linhas por vez.');
  $nome = flTxt($in['nome'] ?? '', 80); if ($nome === '') { $n = (int)$db->query('SELECT COUNT(*) FROM fin_planilhas')->fetchColumn(); $nome = 'Planilha ' . ($n + 1); }
  $db->prepare('INSERT INTO fin_planilhas (nome, colunas, arquivo, criado_por, criado_em) VALUES (?, ?, ?, ?, ?)')->execute([$nome, json_encode($cols, JSON_UNESCAPED_UNICODE), flTxt($in['arquivo'] ?? '', 160), (string)$eu['nome'], agora()]);
  $id = (int)$db->lastInsertId(); $r = plInserir($id, $cols, $linhas, 'importada', $eu);
  return ['planilha' => plPublico(plPega($id))] + $r;
}
function plLinhasAdd(array $in, array $eu): array {
  $p = plPega((int)($in['id'] ?? 0)); $linhas = (array)($in['linhas'] ?? []); if (count($linhas) > 2000) falha('Mande no máximo 2000 linhas por vez.');
  $cols = $p['colunas']; return plInserir((int)$p['id'], $cols, $linhas, 'importada', $eu);
}
function plVer(array $in): array {
  $p = plPega((int)($in['id'] ?? 0));
  $q = db()->prepare('SELECT id, dados, origem, criado_por, criado_em, alterado_por, alterado_em FROM fin_planilha_linhas WHERE planilha_id = ? AND removido_em IS NULL ORDER BY id LIMIT 20000'); $q->execute([(int)$p['id']]);
  $L = array_map(fn($l) => ['id' => (int)$l['id'], 'd' => json_decode((string)$l['dados'], true) ?: (object)[], 'origem' => (string)$l['origem'], 'criado_por' => (string)$l['criado_por'], 'criado_em' => (string)$l['criado_em'],
    'alterado_por' => (string)$l['alterado_por'], 'alterado_em' => (string)$l['alterado_em']], $q->fetchAll());
  return ['planilha' => plPublico($p, count($L)), 'linhas' => $L];
}
function plLinhaSalvar(array $in, array $eu): array {
  $p = plPega((int)($in['planilha_id'] ?? 0)); $db = db(); $cols = $p['colunas']; [$d, $e] = plDados($cols, $in['dados'] ?? []); if ($e) falha($e);
  $id = (int)($in['id'] ?? 0);
  if ($id) { $q = $db->prepare('UPDATE fin_planilha_linhas SET dados = ?, alterado_por = ?, alterado_em = ? WHERE id = ? AND planilha_id = ? AND removido_em IS NULL'); $q->execute([json_encode($d, JSON_UNESCAPED_UNICODE), (string)$eu['nome'], agora(), $id, (int)$p['id']]);
    if (!$q->rowCount()) falha('Linha não encontrada.', 404); }
  else { $db->prepare("INSERT INTO fin_planilha_linhas (planilha_id, dados, origem, criado_por, criado_em) VALUES (?, ?, 'manual', ?, ?)")->execute([(int)$p['id'], json_encode($d, JSON_UNESCAPED_UNICODE), (string)$eu['nome'], agora()]); $id = (int)$db->lastInsertId(); }
  if (plAprende($cols, [$d])) $db->prepare('UPDATE fin_planilhas SET colunas = ? WHERE id = ?')->execute([json_encode($cols, JSON_UNESCAPED_UNICODE), (int)$p['id']]);
  $q = $db->prepare('SELECT * FROM fin_planilha_linhas WHERE id = ?'); $q->execute([$id]); $l = $q->fetch();
  return ['linha' => ['id' => $id, 'd' => json_decode((string)$l['dados'], true), 'origem' => (string)$l['origem'], 'criado_por' => (string)$l['criado_por'], 'criado_em' => (string)$l['criado_em'], 'alterado_por' => (string)$l['alterado_por'], 'alterado_em' => (string)$l['alterado_em']], 'colunas' => $cols];
}
function plLinhaRemover(array $in, array $eu): array {
  $p = plPega((int)($in['planilha_id'] ?? 0)); $q = db()->prepare('UPDATE fin_planilha_linhas SET removido_por = ?, removido_em = ? WHERE id = ? AND planilha_id = ? AND removido_em IS NULL');
  $q->execute([(string)$eu['nome'], agora(), (int)($in['id'] ?? 0), (int)$p['id']]); if (!$q->rowCount()) falha('Linha não encontrada.', 404); return [];
}
/* o nome e as colunas (renomear, mudar o tipo, pôr uma coluna nova, esconder); o que já foi lançado continua guardado */
function plSalvar(array $in, array $eu): array {
  $p = plPega((int)($in['id'] ?? 0)); $nome = array_key_exists('nome', $in) ? flTxt($in['nome'], 80) : (string)$p['nome']; if ($nome === '') falha('Dê um nome à planilha.');
  $cols = array_key_exists('colunas', $in) ? plColunas($in['colunas']) : $p['colunas'];
  db()->prepare('UPDATE fin_planilhas SET nome = ?, colunas = ?, alterado_por = ?, alterado_em = ? WHERE id = ?')->execute([$nome, json_encode($cols, JSON_UNESCAPED_UNICODE), (string)$eu['nome'], agora(), (int)$p['id']]);
  return ['planilha' => plPublico(plPega((int)$p['id']))];
}
function plRemover(array $in, array $eu): array {
  $p = plPega((int)($in['id'] ?? 0)); db()->prepare('UPDATE fin_planilhas SET removido_por = ?, removido_em = ? WHERE id = ?')->execute([(string)$eu['nome'], agora(), (int)$p['id']]); return [];
}
