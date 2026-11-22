<?php
/* =====================================================================
   Play Space Finanças 2.6 (Play Space 1.65): os Freelancers
   Pedido do dono: "um campo para os freelancers. O freelancer é um funcionário que eu preciso saber a data que ele trabalhou, se eu paguei
   ele ou não paguei, o valor que eu paguei, o valor que eu combinei com ele, se eu paguei o vale transporte, o nome dele e o pagamento
   total. Porque às vezes o freelancer vai trabalhar um mês inteiro."
   - O cadastro (fin_freelas): o nome, o telefone, a chave Pix, a função, a diária e o VT combinados (o que vem preenchido nos dias).
   - Os dias trabalhados (fin_freela_dias): o dia, a unidade, o combinado e o VT daquele dia; se a diária foi paga (e quanto, e quando) e
     se o VT foi pago. Lançar o mês inteiro é tocar nos dias do calendário. Tirar um dia não apaga: fica guardado quem tirou.
   - Cada mudança fica em fin_freela_hist (quem, quando, de quanto para quanto).
   - A conta do dia: devido = combinado + VT; pago = (diária paga ? valor pago : 0) + (VT pago ? VT : 0); falta = o que ainda não foi pago.
   ===================================================================== */
const FZ_CAMPOS = ['combinado', 'vt', 'pago', 'pago_valor', 'vt_pago', 'u', 'obs'];
/* 3.0 (Play Space 1.69): a diária do freelancer pelo dia (o dono: "no fim de semana o freelancer é 120, durante a semana é 90; já deixa isso
   e uma opção de trocar"). Sábado e domingo usam a de fim de semana. O freelancer com diária fixa no cadastro continua com a dele */
const FZ_VALORES_PADRAO = ['semana' => 90.0, 'fds' => 120.0];
function fzValores(): array { $v = cfg('fin_freela_valores', null); return is_array($v) && isset($v['semana'], $v['fds']) ? ['semana' => round((float)$v['semana'], 2), 'fds' => round((float)$v['fds'], 2)] : FZ_VALORES_PADRAO; }
function fzValoresSalvar(array $in, array $eu): array {
  fzPreparar(); $s = cxValor($in['semana'] ?? null); $f = cxValor($in['fds'] ?? null);
  if ($s === null || $s <= 0 || $f === null || $f <= 0) falha('Escreva a diária de semana e a de fim de semana (ex.: 90,00 e 120,00).');
  $antes = fzValores(); $novo = ['semana' => round($s, 2), 'fds' => round($f, 2)];
  if ($novo != $antes) { gravarCfg('fin_freela_valores', $novo); fzHist(0, null, 'valores', json_encode($antes), json_encode($novo), $eu); }
  return fzListar(['mes' => $in['mes'] ?? date('Y-m', agoraTs()), 'u' => $in['u_filtro'] ?? 'todas']);
}
function fzFimDeSemana(string $dia): bool { $w = (int)date('w', strtotime($dia . ' 12:00:00')); return $w === 0 || $w === 6; }

function fzPreparar(): void {
  static $ok = false; if ($ok) return; $ok = true;
  $db = db();
  $db->exec("CREATE TABLE IF NOT EXISTS fin_freelas (id INTEGER PRIMARY KEY, nome TEXT NOT NULL, tel TEXT NOT NULL DEFAULT '', pix TEXT NOT NULL DEFAULT '', funcao TEXT NOT NULL DEFAULT '',
    diaria REAL, vt REAL, obs TEXT NOT NULL DEFAULT '', ativo INTEGER NOT NULL DEFAULT 1, por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '')");
  $db->exec("CREATE TABLE IF NOT EXISTS fin_freela_dias (id INTEGER PRIMARY KEY, freela_id INTEGER NOT NULL, dia TEXT NOT NULL, unidade_id INTEGER,
    combinado REAL NOT NULL DEFAULT 0, vt REAL NOT NULL DEFAULT 0, pago INTEGER NOT NULL DEFAULT 0, pago_valor REAL, pago_em TEXT, vt_pago INTEGER NOT NULL DEFAULT 0, vt_pago_em TEXT,
    obs TEXT NOT NULL DEFAULT '', por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '', removido_em TEXT, removido_por TEXT NOT NULL DEFAULT '')");
  $db->exec('CREATE INDEX IF NOT EXISTS fin_freela_dias_dia ON fin_freela_dias (dia, freela_id)');
  $db->exec("CREATE TABLE IF NOT EXISTS fin_freela_hist (id INTEGER PRIMARY KEY, freela_id INTEGER NOT NULL, dia_id INTEGER, campo TEXT NOT NULL, antes TEXT, depois TEXT,
    por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '')");
}
function fzMes($m): string { $m = (string)$m; if (!preg_match('/^(\d{4})-(\d{2})$/', $m, $x) || (int)$x[2] < 1 || (int)$x[2] > 12) falha('Escolha o mês.'); return $m; }
function fzDiaOk($d): string { $d = (string)$d; if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) falha('Confira a data.');
  if ($d < '2000-01-01' || $d > date('Y-m-d', strtotime('+1 year', agoraTs()))) falha('Confira a data (de 2000 até um ano para frente).'); return $d; }   /* 1.91 (auditoria) */
function fzTxt($v, int $max = 120): string { return mb_substr(trim((string)preg_replace('/\s+/u', ' ', (string)$v)), 0, $max); }
function fzUni($v): ?int { if ($v === null || $v === '' || $v === 'todas' || (int)$v <= 0) return null; $u = (int)$v; $q = db()->prepare('SELECT id FROM unidades WHERE id = ?'); $q->execute([$u]); if (!$q->fetchColumn()) falha('Unidade não encontrada.'); return $u; }
function fzHist(int $f, ?int $d, string $campo, $antes, $depois, array $eu): void {
  db()->prepare('INSERT INTO fin_freela_hist (freela_id, dia_id, campo, antes, depois, por, em) VALUES (?, ?, ?, ?, ?, ?, ?)')
    ->execute([$f, $d, $campo, $antes === null ? null : (string)$antes, $depois === null ? null : (string)$depois, (string)$eu['nome'], agora()]);
}
/* a conta de um dia */
function fzConta(array $x): array {
  $comb = round((float)$x['combinado'], 2); $vt = round((float)$x['vt'], 2); $pago = !empty($x['pago']); $vtp = !empty($x['vt_pago']);
  $pv = $pago ? round((float)($x['pago_valor'] ?? $comb), 2) : 0.0;
  return ['devido' => round($comb + $vt, 2), 'totalPago' => round($pv + ($vtp ? $vt : 0), 2), 'falta' => round(($pago ? 0 : $comb) + ($vtp ? 0 : $vt), 2), 'dif' => $pago ? round($pv - $comb, 2) : 0.0];
}
function fzDiaPub(array $d): array {
  $o = ['id' => (int)$d['id'], 'freela' => (int)$d['freela_id'], 'dia' => (string)$d['dia'], 'u' => $d['unidade_id'] === null ? null : (int)$d['unidade_id'],
    'combinado' => round((float)$d['combinado'], 2), 'vt' => round((float)$d['vt'], 2), 'pago' => !empty($d['pago']), 'pago_valor' => $d['pago_valor'] === null ? null : round((float)$d['pago_valor'], 2),
    'pago_em' => (string)($d['pago_em'] ?? ''), 'vt_pago' => !empty($d['vt_pago']), 'vt_pago_em' => (string)($d['vt_pago_em'] ?? ''), 'obs' => (string)$d['obs'], 'por' => (string)$d['por']];
  return $o + fzConta($d);
}
function fzFreelaPub(array $f): array {
  return ['id' => (int)$f['id'], 'nome' => (string)$f['nome'], 'tel' => (string)$f['tel'], 'pix' => (string)$f['pix'], 'funcao' => (string)$f['funcao'],
    'diaria' => $f['diaria'] === null ? null : round((float)$f['diaria'], 2), 'vt' => $f['vt'] === null ? null : round((float)$f['vt'], 2), 'obs' => (string)$f['obs'], 'ativo' => !empty($f['ativo'])];
}
function fzZero(): array { return ['dias' => 0, 'combinado' => 0.0, 'vt' => 0.0, 'devido' => 0.0, 'pagoDiarias' => 0.0, 'pagoVt' => 0.0, 'totalPago' => 0.0, 'falta' => 0.0, 'dif' => 0.0, 'diasPagos' => 0, 'vtPagos' => 0, 'comVt' => 0]; }
function fzSoma(array &$T, array $d): void {
  $T['dias']++; $T['combinado'] = round($T['combinado'] + $d['combinado'], 2); $T['vt'] = round($T['vt'] + $d['vt'], 2); $T['devido'] = round($T['devido'] + $d['devido'], 2);
  $T['totalPago'] = round($T['totalPago'] + $d['totalPago'], 2); $T['falta'] = round($T['falta'] + $d['falta'], 2); $T['dif'] = round($T['dif'] + $d['dif'], 2);
  if ($d['pago']) { $T['diasPagos']++; $T['pagoDiarias'] = round($T['pagoDiarias'] + (float)$d['pago_valor'], 2); }
  if ($d['vt'] > 0.004) { $T['comVt']++; if ($d['vt_pago']) { $T['vtPagos']++; $T['pagoVt'] = round($T['pagoVt'] + $d['vt'], 2); } }
}

/* os freelancers do mês: cada um com os dias trabalhados (na unidade escolhida) e a conta; os ativos aparecem mesmo sem dia (para lançar) */
function fzListar(array $in): array {
  fzPreparar(); $db = db();
  $mes = fzMes($in['mes'] ?? date('Y-m', agoraTs())); $de = $mes . '-01'; $ate = date('Y-m-t', strtotime($de . ' 12:00:00'));
  $uni = isset($in['u']) && $in['u'] !== '' && $in['u'] !== 'todas' ? (int)$in['u'] : null;
  $q = $db->prepare('SELECT * FROM fin_freela_dias WHERE dia >= ? AND dia <= ? AND removido_em IS NULL' . ($uni !== null ? ' AND unidade_id = ' . $uni : '') . ' ORDER BY dia, id');
  $q->execute([$de, $ate]); $D = []; foreach ($q->fetchAll() as $d) $D[(int)$d['freela_id']][] = fzDiaPub($d);
  $L = []; $T = fzZero() + ['freelas' => 0];
  foreach ($db->query('SELECT * FROM fin_freelas ORDER BY nome COLLATE NOCASE, id')->fetchAll() as $f) {
    $id = (int)$f['id']; $dias = $D[$id] ?? [];
    if (!$dias && empty($f['ativo'])) continue;
    $t = fzZero(); foreach ($dias as $d) { fzSoma($t, $d); fzSoma($T, $d); }
    if ($dias) $T['freelas']++;
    $L[] = fzFreelaPub($f) + ['dias' => $dias, 'tot' => $t];
  }
  $F = ffListar($mes, $uni);   /* 2.9: o fluxo de caixa (o dinheiro entregue ao gerente) */
  return ['mes' => $mes, 'freelas' => $L, 'total' => $T, 'nomes' => unidades(), 'hoje' => date('Y-m-d', agoraTs()), 'fluxos' => $F, 'fluxoTot' => ffResumo($F), 'valores' => fzValores()];
}

/* o cadastro: novo (sem id) ou a mudança de um */
function fzFreelaSalvar(array $in, array $eu): array {
  fzPreparar(); $db = db(); $id = (int)($in['id'] ?? 0);
  $nome = maiusculo(fzTxt($in['nome'] ?? '', 80)); if (mb_strlen($nome) < 2) falha('Escreva o nome do freelancer.');
  $q = $db->prepare('SELECT id FROM fin_freelas WHERE nome = ? COLLATE NOCASE AND id != ?'); $q->execute([$nome, $id]); if ($q->fetchColumn()) falha('Já tem um freelancer com o nome ' . $nome . '.', 409);
  $v = ['nome' => $nome, 'tel' => fzTxt($in['tel'] ?? '', 40), 'pix' => fzTxt($in['pix'] ?? '', 120), 'funcao' => fzTxt($in['funcao'] ?? '', 60),
    'diaria' => cxValor($in['diaria'] ?? null), 'vt' => cxValor($in['vt'] ?? null), 'obs' => fzTxt($in['obs'] ?? '', 300), 'ativo' => array_key_exists('ativo', $in) ? (empty($in['ativo']) ? 0 : 1) : 1];
  if ($id) {
    $q = $db->prepare('SELECT * FROM fin_freelas WHERE id = ?'); $q->execute([$id]); $f = $q->fetch(); if (!$f) falha('Freelancer não encontrado.', 404);
    foreach ($v as $k => $x) { $a = $f[$k]; $mud = in_array($k, ['diaria', 'vt'], true) ? (($a === null) !== ($x === null) || ($x !== null && abs((float)$a - (float)$x) > 0.004)) : (string)$a !== (string)$x; if ($mud) fzHist($id, null, $k, $a, $x, $eu); }
    $db->prepare('UPDATE fin_freelas SET nome = ?, tel = ?, pix = ?, funcao = ?, diaria = ?, vt = ?, obs = ?, ativo = ? WHERE id = ?')->execute([$v['nome'], $v['tel'], $v['pix'], $v['funcao'], $v['diaria'], $v['vt'], $v['obs'], $v['ativo'], $id]);
  } else {
    $db->prepare('INSERT INTO fin_freelas (nome, tel, pix, funcao, diaria, vt, obs, ativo, por, em) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)')->execute([$v['nome'], $v['tel'], $v['pix'], $v['funcao'], $v['diaria'], $v['vt'], $v['obs'], (string)$eu['nome'], agora()]);
    $id = (int)$db->lastInsertId(); fzHist($id, null, 'criado', null, $nome, $eu);
  }
  return fzListar($in) + ['id' => $id];
}

/* lançar os dias trabalhados (um ou o mês inteiro): o dia que já está lançado para ele não entra de novo */
function fzLancar(array $in, array $eu): array {
  fzPreparar(); $db = db(); $fid = (int)($in['freela'] ?? 0);
  $q = $db->prepare('SELECT * FROM fin_freelas WHERE id = ?'); $q->execute([$fid]); $f = $q->fetch(); if (!$f) falha('Freelancer não encontrado.', 404);
  $datas = array_values(array_unique(array_map('fzDiaOk', (array)($in['datas'] ?? [])))); sort($datas);
  if (!$datas) falha('Toque nos dias que ele trabalhou.'); if (count($datas) > 62) falha('No máximo 62 dias de uma vez.');
  $u = fzUni($in['u'] ?? null);
  /* 3.0: a diária de cada dia: a de semana ou a de fim de semana (a tela manda as duas); "combinado" (a tela antiga) vale para todos os dias */
  $V = fzValores(); $vs = array_key_exists('semana', $in) ? cxValor($in['semana']) : null; $vf = array_key_exists('fds', $in) ? cxValor($in['fds']) : null;
  if (array_key_exists('semana', $in) || array_key_exists('fds', $in)) { if ($vs === null || $vf === null) falha('Escreva a diária de semana e a de fim de semana.'); $comb = null; }
  else { $comb = array_key_exists('combinado', $in) ? cxValor($in['combinado']) : ($f['diaria'] === null ? null : (float)$f['diaria']); if ($comb === null) { $vs = $V['semana']; $vf = $V['fds']; } }
  $combDe = fn(string $d) => $comb !== null ? (float)$comb : (fzFimDeSemana($d) ? (float)$vf : (float)$vs);
  $vt = array_key_exists('vt', $in) ? (cxValor($in['vt']) ?? 0.0) : (float)($f['vt'] ?? 0);
  $ja = $db->prepare('SELECT 1 FROM fin_freela_dias WHERE freela_id = ? AND dia = ? AND removido_em IS NULL');
  $ins = $db->prepare('INSERT INTO fin_freela_dias (freela_id, dia, unidade_id, combinado, vt, por, em) VALUES (?, ?, ?, ?, ?, ?, ?)');
  $n = 0; $pulou = [];
  foreach ($datas as $d) { $ja->execute([$fid, $d]); if ($ja->fetchColumn()) { $pulou[] = $d; continue; } $ins->execute([$fid, $d, $u, $combDe($d), $vt, (string)$eu['nome'], agora()]); fzHist($fid, (int)$db->lastInsertId(), 'lancado', null, $d, $eu); $n++; }
  $mes = substr($datas[0], 0, 7);
  return fzListar(['mes' => $in['mes'] ?? $mes, 'u' => $in['u_filtro'] ?? 'todas']) + ['lancados' => $n, 'pulados' => $pulou];
}

/* mudar um campo de um dia: o combinado, o VT, a diária paga (e quanto), o VT pago, a unidade, a observação */
function fzDiaSalvar(array $in, array $eu): array {
  fzPreparar(); $db = db(); $id = (int)($in['id'] ?? 0); $campo = (string)($in['campo'] ?? '');
  if (!in_array($campo, FZ_CAMPOS, true)) falha('Campo desconhecido.');
  $q = $db->prepare('SELECT * FROM fin_freela_dias WHERE id = ? AND removido_em IS NULL'); $q->execute([$id]); $d = $q->fetch(); if (!$d) falha('Este dia não está mais lançado.', 404);
  $hoje = date('Y-m-d', agoraTs()); $set = []; $fid = (int)$d['freela_id'];
  if ($campo === 'combinado' || $campo === 'vt') { $v = cxValor($in['valor'] ?? null) ?? 0.0; $set[$campo] = $v; }
  elseif ($campo === 'pago') {
    $sim = !empty($in['valor']) && $in['valor'] !== '0';
    $set = $sim ? ['pago' => 1, 'pago_valor' => $d['pago_valor'] !== null ? (float)$d['pago_valor'] : (float)$d['combinado'], 'pago_em' => fzDiaOk($in['data'] ?? $hoje)] : ['pago' => 0, 'pago_valor' => null, 'pago_em' => null];
  } elseif ($campo === 'pago_valor') {
    $v = cxValor($in['valor'] ?? null);
    $set = $v === null ? ['pago' => 0, 'pago_valor' => null, 'pago_em' => null] : ['pago' => 1, 'pago_valor' => $v, 'pago_em' => $d['pago_em'] ?: $hoje];   /* escrever o valor pago já marca como pago */
  } elseif ($campo === 'vt_pago') {
    $sim = !empty($in['valor']) && $in['valor'] !== '0'; if ($sim && (float)$d['vt'] <= 0.004) falha('Este dia não tem vale-transporte.');
    $set = $sim ? ['vt_pago' => 1, 'vt_pago_em' => fzDiaOk($in['data'] ?? $hoje)] : ['vt_pago' => 0, 'vt_pago_em' => null];
  } elseif ($campo === 'u') $set['unidade_id'] = fzUni($in['valor'] ?? null);
  else $set['obs'] = fzTxt($in['valor'] ?? '', 200);
  if (isset($set['vt']) && $set['vt'] <= 0.004) { $set['vt_pago'] = 0; $set['vt_pago_em'] = null; }
  $mud = false; foreach ($set as $k => $v) { $a = $d[$k]; if ((string)($a ?? '') !== (string)($v ?? '') && !(is_numeric($a) && is_numeric($v) && abs((float)$a - (float)$v) < 0.005)) { $mud = true; fzHist($fid, $id, $k, $a, $v, $eu); } }
  if ($mud) { $cols = implode(', ', array_map(fn($k) => "$k = ?", array_keys($set))); $db->prepare("UPDATE fin_freela_dias SET $cols WHERE id = ?")->execute(array_merge(array_values($set), [$id])); }
  $q = $db->prepare('SELECT * FROM fin_freela_dias WHERE id = ?'); $q->execute([$id]);
  return fzListar(['mes' => substr((string)$d['dia'], 0, 7), 'u' => $in['u_filtro'] ?? 'todas']) + ['dia' => fzDiaPub($q->fetch())];
}

/* pagar tudo o que falta de um freelancer no mês (a diária pelo combinado e o VT), na data escolhida */
function fzPagar(array $in, array $eu): array {
  fzPreparar(); $db = db(); $fid = (int)($in['freela'] ?? 0); $mes = fzMes($in['mes'] ?? ''); $data = fzDiaOk($in['data'] ?? date('Y-m-d', agoraTs()));
  $q = $db->prepare('SELECT id FROM fin_freelas WHERE id = ?'); $q->execute([$fid]); if (!$q->fetchColumn()) falha('Freelancer não encontrado.', 404);
  $uni = isset($in['u']) && $in['u'] !== '' && $in['u'] !== 'todas' ? (int)$in['u'] : null; $comVt = !array_key_exists('vt', $in) || !empty($in['vt']);
  $q = $db->prepare('SELECT * FROM fin_freela_dias WHERE freela_id = ? AND dia >= ? AND dia <= ? AND removido_em IS NULL' . ($uni !== null ? ' AND unidade_id = ' . $uni : '') . ' ORDER BY dia');
  $q->execute([$fid, $mes . '-01', date('Y-m-t', strtotime($mes . '-01 12:00:00'))]); $n = 0; $valor = 0.0;
  foreach ($q->fetchAll() as $d) {
    $id = (int)$d['id'];
    if (empty($d['pago'])) { $db->prepare('UPDATE fin_freela_dias SET pago = 1, pago_valor = combinado, pago_em = ? WHERE id = ?')->execute([$data, $id]); fzHist($fid, $id, 'pago', 0, 1, $eu); $valor += (float)$d['combinado']; $n++; }
    if ($comVt && empty($d['vt_pago']) && (float)$d['vt'] > 0.004) { $db->prepare('UPDATE fin_freela_dias SET vt_pago = 1, vt_pago_em = ? WHERE id = ?')->execute([$data, $id]); fzHist($fid, $id, 'vt_pago', 0, 1, $eu); $valor += (float)$d['vt']; }
  }
  if ($valor <= 0.004) falha('Não há nada a pagar deste freelancer neste mês.');
  return fzListar(['mes' => $mes, 'u' => $in['u'] ?? 'todas']) + ['pagos' => $n, 'valor' => round($valor, 2)];
}

/* tirar um dia lançado por engano (fica guardado quem tirou) */
function fzDiaTirar(array $in, array $eu): array {
  fzPreparar(); $db = db(); $id = (int)($in['id'] ?? 0);
  $q = $db->prepare('SELECT * FROM fin_freela_dias WHERE id = ? AND removido_em IS NULL'); $q->execute([$id]); $d = $q->fetch(); if (!$d) falha('Este dia não está mais lançado.', 404);
  $db->prepare('UPDATE fin_freela_dias SET removido_em = ?, removido_por = ? WHERE id = ?')->execute([agora(), (string)$eu['nome'], $id]); fzHist((int)$d['freela_id'], $id, 'tirado', $d['dia'], null, $eu);
  return fzListar(['mes' => substr((string)$d['dia'], 0, 7), 'u' => $in['u_filtro'] ?? 'todas']);
}

/* ---------- 2.9 (Play Space 1.68): o Fluxo de caixa dos freelancers ----------
   Pedido do dono: "ela faz um lançamento de um valor para ser usado no final de semana: o valor que ela passa para o gerente. Conforme
   ela for colocando os funcionários e os valores, e colocando como pago, esse valor vai sendo debitado do valor que ela emprestou (deu de
   adiantamento de fluxo de caixa para o gerente), e fica o valor final que ela precisa receber de Pix de volta".
   - Cada fluxo: o valor entregue, para quem (o gerente), os dias (de/até) e a unidade (ou todas).
   - O que vai sendo debitado: o que foi marcado como pago (a diária paga e o VT pago) dos dias trabalhados dentro do período, na unidade.
   - Falta devolver = entregue − pago. "Recebi o Pix" guarda quanto voltou e quando (dá para desfazer). Tirar um fluxo não apaga: fica
     guardado quem tirou. Dois fluxos da mesma unidade não podem ter dias em comum (cada pagamento é debitado de um só).
   - O freelancer fica à parte: nada disto entra no Painel, nos Lançamentos, no Relatório financeiro, no Controle de caixa nem no resumo. */
function ffPreparar(): void {
  static $ok = false; if ($ok) return; $ok = true;
  db()->exec("CREATE TABLE IF NOT EXISTS fin_freela_fluxo (id INTEGER PRIMARY KEY, de TEXT NOT NULL, ate TEXT NOT NULL, unidade_id INTEGER, valor REAL NOT NULL DEFAULT 0,
    gerente TEXT NOT NULL DEFAULT '', obs TEXT NOT NULL DEFAULT '', por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '',
    dev_valor REAL, dev_em TEXT, dev_por TEXT NOT NULL DEFAULT '', removido_em TEXT, removido_por TEXT NOT NULL DEFAULT '')");
}
function ffConta(array $f): array {
  $db = db(); $u = $f['unidade_id'] === null ? null : (int)$f['unidade_id'];
  $q = $db->prepare('SELECT d.*, f.nome FROM fin_freela_dias d JOIN fin_freelas f ON f.id = d.freela_id WHERE d.removido_em IS NULL AND d.dia >= ? AND d.dia <= ? AND (d.pago = 1 OR d.vt_pago = 1)' . ($u !== null ? ' AND d.unidade_id = ' . $u : '') . ' ORDER BY d.dia, f.nome COLLATE NOCASE, d.id');
  $q->execute([(string)$f['de'], (string)$f['ate']]); $L = []; $pd = 0.0; $pv = 0.0;
  foreach ($q->fetchAll() as $d) {
    $dia = !empty($d['pago']) ? round((float)($d['pago_valor'] ?? $d['combinado']), 2) : 0.0; $vt = !empty($d['vt_pago']) ? round((float)$d['vt'], 2) : 0.0;
    if ($dia + $vt <= 0.004) continue;
    $L[] = ['id' => (int)$d['id'], 'dia' => (string)$d['dia'], 'nome' => (string)$d['nome'], 'diaria' => $dia, 'vt' => $vt, 'total' => round($dia + $vt, 2), 'u' => $d['unidade_id'] === null ? null : (int)$d['unidade_id']];
    $pd += $dia; $pv += $vt;
  }
  $usado = round($pd + $pv, 2); $valor = round((float)$f['valor'], 2); $saldo = round($valor - $usado, 2);
  $dev = $f['dev_valor'] === null ? null : round((float)$f['dev_valor'], 2);
  return ['id' => (int)$f['id'], 'de' => (string)$f['de'], 'ate' => (string)$f['ate'], 'u' => $u, 'valor' => $valor, 'gerente' => (string)$f['gerente'], 'obs' => (string)$f['obs'], 'por' => (string)$f['por'], 'em' => (string)$f['em'],
    'pagos' => $L, 'pagoDiarias' => round($pd, 2), 'pagoVt' => round($pv, 2), 'usado' => $usado, 'saldo' => $saldo,
    'devolvido' => $dev, 'dev_em' => (string)($f['dev_em'] ?? ''), 'dev_por' => (string)$f['dev_por'], 'acertado' => $dev !== null, 'dif' => $dev !== null ? round($dev - $saldo, 2) : 0.0];
}
/* os fluxos que têm dias no mês (na unidade escolhida: os dela e os de todas as unidades) */
function ffListar(string $mes, ?int $uni): array {
  ffPreparar(); $de = $mes . '-01'; $ate = date('Y-m-t', strtotime($de . ' 12:00:00'));
  $q = db()->prepare('SELECT * FROM fin_freela_fluxo WHERE removido_em IS NULL AND de <= ? AND ate >= ?' . ($uni !== null ? ' AND (unidade_id IS NULL OR unidade_id = ' . $uni . ')' : '') . ' ORDER BY de, id');
  $q->execute([$ate, $de]); return array_map('ffConta', $q->fetchAll());
}
function ffResumo(array $F): array {
  $T = ['n' => count($F), 'valor' => 0.0, 'usado' => 0.0, 'aDevolver' => 0.0, 'devolvido' => 0.0, 'abertos' => 0, 'aMais' => 0.0];
  foreach ($F as $f) { $T['valor'] += $f['valor']; $T['usado'] += $f['usado'];
    if ($f['acertado']) $T['devolvido'] += $f['devolvido']; else { $T['abertos']++; if ($f['saldo'] > 0) $T['aDevolver'] += $f['saldo']; else $T['aMais'] += -$f['saldo']; } }
  foreach ($T as $k => $v) if (is_float($v)) $T[$k] = round($v, 2);
  return $T;
}
function ffHist(int $id, string $campo, $antes, $depois, array $eu): void { fzHist(0, $id, 'fluxo:' . $campo, is_array($antes) ? json_encode($antes, JSON_UNESCAPED_UNICODE) : $antes, is_array($depois) ? json_encode($depois, JSON_UNESCAPED_UNICODE) : $depois, $eu); }
function ffPegar(int $id): array { ffPreparar(); $q = db()->prepare('SELECT * FROM fin_freela_fluxo WHERE id = ? AND removido_em IS NULL'); $q->execute([$id]); $f = $q->fetch(); if (!$f) falha('Este fluxo de caixa não está mais lançado.', 404); return $f; }
/* lançar (sem id) ou mudar um fluxo: o valor entregue ao gerente, para quem, os dias e a unidade */
function ffSalvar(array $in, array $eu): array {
  fzPreparar(); ffPreparar(); $db = db(); $id = (int)($in['id'] ?? 0);
  $valor = cxValor($in['valor'] ?? null); if ($valor === null || $valor <= 0) falha('Escreva o valor entregue ao gerente (ex.: 1.000,00).');
  $de = fzDiaOk($in['de'] ?? ''); $ate = trim((string)($in['ate'] ?? '')) === '' ? $de : fzDiaOk($in['ate']);
  if ($ate < $de) falha('O último dia é antes do primeiro.');
  if ((strtotime($ate . ' 12:00:00') - strtotime($de . ' 12:00:00')) / 86400 > 31) falha('O fluxo de caixa vale para até 31 dias.');
  $u = fzUni($in['u'] ?? null); $ger = maiusculo(fzTxt($in['gerente'] ?? '', 60)); if (mb_strlen($ger) < 2) falha('Escreva para quem foi entregue o dinheiro (o gerente).');
  $obs = fzTxt($in['obs'] ?? '', 200);
  /* dois fluxos da mesma unidade (ou um de todas) com dias em comum: cada pagamento só pode ser debitado de um */
  $q = $db->prepare('SELECT * FROM fin_freela_fluxo WHERE removido_em IS NULL AND id != ? AND de <= ? AND ate >= ?' . ($u !== null ? ' AND (unidade_id IS NULL OR unidade_id = ' . $u . ')' : ''));
  $q->execute([$id, $ate, $de]);
  if ($x = $q->fetch()) falha('Já há um fluxo de caixa com dias em comum (' . finDataBR($x['de']) . ($x['ate'] !== $x['de'] ? ' a ' . finDataBR($x['ate']) : '') . ', ' . (string)$x['gerente'] . '). Mude os dias ou a unidade.');
  $novo = ['de' => $de, 'ate' => $ate, 'unidade_id' => $u, 'valor' => $valor, 'gerente' => $ger, 'obs' => $obs];
  if ($id) { $f = ffPegar($id); $antes = array_intersect_key($f, $novo);
    $db->prepare('UPDATE fin_freela_fluxo SET de = ?, ate = ?, unidade_id = ?, valor = ?, gerente = ?, obs = ? WHERE id = ?')->execute([$de, $ate, $u, $valor, $ger, $obs, $id]); ffHist($id, 'editado', $antes, $novo, $eu); }
  else { $db->prepare('INSERT INTO fin_freela_fluxo (de, ate, unidade_id, valor, gerente, obs, por, em) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$de, $ate, $u, $valor, $ger, $obs, (string)$eu['nome'], agora()]); $id = (int)$db->lastInsertId(); ffHist($id, 'lancado', null, $novo, $eu); }
  return fzListar(['mes' => $in['mes'] ?? substr($de, 0, 7), 'u' => $in['u_filtro'] ?? 'todas']) + ['fluxo' => $id];
}
/* "Recebi o Pix": quanto o gerente devolveu e quando (sem valor: desfaz) */
function ffDevolver(array $in, array $eu): array {
  $f = ffPegar((int)($in['id'] ?? 0)); $db = db();
  if (!empty($in['desfazer'])) { $db->prepare("UPDATE fin_freela_fluxo SET dev_valor = NULL, dev_em = NULL, dev_por = '' WHERE id = ?")->execute([(int)$f['id']]); ffHist((int)$f['id'], 'devolvido', $f['dev_valor'], null, $eu); }
  else { $v = cxValor($in['valor'] ?? null); if ($v === null || $v < 0) falha('Escreva quanto o gerente devolveu (pode ser 0,00).'); $quando = fzDiaOk($in['data'] ?? date('Y-m-d', agoraTs()));
    $db->prepare('UPDATE fin_freela_fluxo SET dev_valor = ?, dev_em = ?, dev_por = ? WHERE id = ?')->execute([$v, $quando, (string)$eu['nome'], (int)$f['id']]); ffHist((int)$f['id'], 'devolvido', $f['dev_valor'], $v, $eu); }
  return fzListar(['mes' => $in['mes'] ?? substr((string)$f['de'], 0, 7), 'u' => $in['u_filtro'] ?? 'todas']);
}
/* tirar um fluxo lançado por engano (fica guardado quem tirou) */
function ffTirar(array $in, array $eu): array {
  $f = ffPegar((int)($in['id'] ?? 0));
  db()->prepare('UPDATE fin_freela_fluxo SET removido_em = ?, removido_por = ? WHERE id = ?')->execute([agora(), (string)$eu['nome'], (int)$f['id']]); ffHist((int)$f['id'], 'tirado', (float)$f['valor'], null, $eu);
  return fzListar(['mes' => $in['mes'] ?? substr((string)$f['de'], 0, 7), 'u' => $in['u_filtro'] ?? 'todas']);
}

/* ---------- o PDF do mês: os números, os gráficos e cada freelancer com os dias ---------- */
function fzPdf(array $in): string {
  $R = fzListar($in); $T = $R['total']; $mesNome = finMesNome($R['mes']); $uni = isset($in['u']) && $in['u'] !== '' && $in['u'] !== 'todas' ? (int)$in['u'] : null;
  $nomeU = []; foreach ($R['nomes'] as $u) $nomeU[(int)$u['id']] = (string)$u['nome'];
  $com = array_values(array_filter($R['freelas'], fn($f) => count($f['dias']) > 0));
  $d = new PdfBonito('Freelancers', $mesNome . ' · ' . ($uni ? $nomeU[$uni] ?? 'unidade' : 'todas as unidades'), 'Freelancers');
  $fala = !$T['dias'] ? 'Nenhum dia de freelancer lançado neste mês.' : ($T['falta'] > 0.004 ? 'Falta pagar ' . finBrl($T['falta']) . ' a ' . count(array_filter($com, fn($f) => $f['tot']['falta'] > 0.004)) . (count(array_filter($com, fn($f) => $f['tot']['falta'] > 0.004)) === 1 ? ' freelancer.' : ' freelancers.') : 'Tudo pago neste mês!');
  $d->capa([count($com) . (count($com) === 1 ? ' freelancer' : ' freelancers'), $T['dias'] . ($T['dias'] === 1 ? ' dia trabalhado' : ' dias trabalhados')], $fala);
  $d->kpis([['Total do mês', finBrl($T['devido']), 'diárias + vale-transporte', PdfBonito::CORAL], ['Pago', finBrl($T['totalPago']), PdfBonito::pct($T['totalPago'], $T['devido'], 0) . ' do total', PdfBonito::VERDE],
    ['Falta pagar', finBrl($T['falta']), $T['falta'] > 0.004 ? 'diárias e VT em aberto' : 'nada em aberto', $T['falta'] > 0.004 ? PdfBonito::VERM : PdfBonito::VERDE], ['Dias trabalhados', (string)$T['dias'], count($com) . ' freelancers', PdfBonito::MOSTARDA],
    ['Diárias combinadas', finBrl($T['combinado']), $T['dias'] ? 'média ' . finBrl($T['combinado'] / $T['dias']) . ' por dia' : '', PdfBonito::CORAL], ['Diárias pagas', $T['diasPagos'] . ' de ' . $T['dias'], finBrl($T['pagoDiarias']) . ' pagos', PdfBonito::VERDE],
    ['Vale-transporte', finBrl($T['vt']), $T['comVt'] . ' dias com VT', PdfBonito::MOSTARDA], ['VT pago', $T['vtPagos'] . ' de ' . $T['comVt'], finBrl($T['pagoVt']) . ' pagos', PdfBonito::PIX]]);
  if ($T['dif'] < -0.004 || $T['dif'] > 0.004) $d->aviso('Nas diárias pagas, foi pago ' . finBrl(abs($T['dif'])) . ($T['dif'] < 0 ? ' a menos' : ' a mais') . ' do que o combinado (o detalhe está em cada freelancer).');
  /* 2.9: o fluxo de caixa (o dinheiro entregue ao gerente): o que foi pago com ele e o que falta voltar por Pix */
  if ($R['fluxos']) {
    $FT = $R['fluxoTot'];
    $d->secao('Fluxo de caixa', 'O dinheiro entregue ao gerente para pagar os freelancers: o que foi pago com ele e o que falta voltar por Pix.', 60 + 18 * count($R['fluxos']));
    $d->tabela([['Dias', .2, 'E'], ['Unidade', .16, 'E'], ['Gerente', .16, 'E'], ['Entregue', .12, 'D'], ['Pago', .12, 'D'], ['Situação', .24, 'D']],
      array_map(fn($f) => [finDataBR($f['de']) . ($f['ate'] !== $f['de'] ? ' a ' . substr(finDataBR($f['ate']), 0, 5) : ''), $f['u'] ? ($nomeU[$f['u']] ?? '') : 'todas', $f['gerente'], finBrl($f['valor']), finBrl($f['usado']),
        $f['acertado'] ? ['t' => 'voltou ' . finBrl($f['devolvido']) . ' (Pix)', 'cor' => PdfBonito::VERDE, 'b' => true] : ($f['saldo'] >= 0 ? ['t' => 'falta voltar ' . finBrl($f['saldo']), 'cor' => PdfBonito::VERM, 'b' => true] : ['t' => 'gerente pagou ' . finBrl(-$f['saldo']) . ' a mais', 'cor' => PdfBonito::VERM, 'b' => true])], $R['fluxos']),
      ['Total', '', '', finBrl($FT['valor']), finBrl($FT['usado']), $FT['aDevolver'] > 0.004 ? 'falta voltar ' . finBrl($FT['aDevolver']) : ($FT['abertos'] ? '' : 'tudo acertado')]);
  }
  if ($T['dias']) {
    $d->secao('Pago e a pagar', 'O total do mês (diárias e vale-transporte), separado entre o que já foi pago e o que falta.');
    $y = $d->y; $h = $d->rosca($d->M, $y, 56, [['Diárias pagas', $T['pagoDiarias'], PdfBonito::VERDE], ['VT pago', $T['pagoVt'], PdfBonito::PIX], ['Falta pagar', $T['falta'], PdfBonito::CORAL]], PdfBonito::curto($T['devido']), 'no mês', 210);
    $d->y = $y + $h + 14;
    $ult = (int)date('t', strtotime($R['mes'] . '-01 12:00:00')); $cats = []; $sd = []; $sv = [];
    for ($i = 1; $i <= $ult; $i++) { $cats[] = sprintf('%02d', $i); $sd[] = 0.0; $sv[] = 0.0; }
    foreach ($com as $f) foreach ($f['dias'] as $x) { $k = (int)substr($x['dia'], 8, 2) - 1; $sd[$k] += $x['combinado']; $sv[$k] += $x['vt']; }
    $d->secao('Quanto custou cada dia', 'A soma das diárias e do vale-transporte de todos os freelancers, dia a dia.', 210);
    $d->barras(150, $cats, [['Diárias', PdfBonito::CORAL, $sd], ['Vale-transporte', PdfBonito::MOSTARDA, $sv]], true, 'em reais');
    $d->secao('Cada freelancer no mês', 'O total de cada um (diárias + VT) e o que ainda falta pagar.', 30 + 18 * min(6, count($com)));
    $rank = $com; usort($rank, fn($a, $b) => $b['tot']['devido'] <=> $a['tot']['devido']);
    $d->barrasH(array_map(fn($f) => [$f['nome'], $f['tot']['devido'], $f['tot']['falta'] > 0.004 ? PdfBonito::CORAL : PdfBonito::VERDE, finBrl($f['tot']['devido']) . ($f['tot']['falta'] > 0.004 ? ' · falta ' . finBrl($f['tot']['falta']) : ' · pago')], $rank), 150);
  }
  foreach ($com as $f) {
    $t = $f['tot']; $info = array_filter([$f['funcao'], $f['tel'] !== '' ? 'tel. ' . $f['tel'] : '', $f['pix'] !== '' ? 'Pix ' . $f['pix'] : '']);
    $d->secao($f['nome'], ($info ? implode(' · ', $info) . ' · ' : '') . $t['dias'] . ($t['dias'] === 1 ? ' dia' : ' dias') . ' · total ' . finBrl($t['devido']) . ' · pago ' . finBrl($t['totalPago']) . ($t['falta'] > 0.004 ? ' · falta ' . finBrl($t['falta']) : ' · tudo pago'), 110, $t['falta'] > 0.004 ? PdfBonito::CORAL : PdfBonito::VERDE);
    $L = []; $fundo = [];
    foreach ($f['dias'] as $k => $x) {
      $ds = finDiaLongo($x['dia']); $L[] = [substr($x['dia'], 8, 2) . '/' . substr($x['dia'], 5, 2) . ' · ' . mb_substr($ds, 0, 3), $x['u'] ? ($nomeU[$x['u']] ?? '') : '—', finBrl($x['combinado']), $x['vt'] > 0.004 ? finBrl($x['vt']) : '—',
        $x['pago'] ? ['t' => finBrl($x['pago_valor']) . ($x['dif'] < -0.004 || $x['dif'] > 0.004 ? ' *' : ''), 'cor' => PdfBonito::VERDE, 'b' => true] : ['t' => 'não pago', 'cor' => PdfBonito::VERM, 'b' => true],
        $x['vt'] > 0.004 ? ($x['vt_pago'] ? ['t' => 'pago', 'cor' => PdfBonito::VERDE, 'b' => true] : ['t' => 'não pago', 'cor' => PdfBonito::VERM, 'b' => true]) : '—',
        finBrl($x['totalPago']), $x['falta'] > 0.004 ? ['t' => finBrl($x['falta']), 'cor' => PdfBonito::VERM, 'b' => true] : '—'];
      if ($x['falta'] > 0.004) $fundo[$k] = 'FFF3EE';
    }
    $d->tabela([['Dia', .13, 'E'], ['Unidade', .19, 'E'], ['Combinado', .12, 'D'], ['VT', .1, 'D'], ['Diária paga', .14, 'D'], ['VT pago', .1, 'C'], ['Total pago', .12, 'D'], ['Falta', .1, 'D']], $L,
      ['Total', '', finBrl($t['combinado']), finBrl($t['vt']), finBrl($t['pagoDiarias']), $t['vtPagos'] . '/' . $t['comVt'], finBrl($t['totalPago']), finBrl($t['falta'])], ['fundo' => $fundo]);
    if ($t['dif'] < -0.004 || $t['dif'] > 0.004) $d->nota('* Pago diferente do combinado: ' . finBrl(abs($t['dif'])) . ($t['dif'] < 0 ? ' a menos' : ' a mais') . ' no mês.');
  }
  if (!$com) $d->nota('Nenhum dia de freelancer lançado em ' . mb_strtolower($mesNome) . '. Na tela Freelancers, toque em "Lançar dias".');
  return $d->pdf();
}
