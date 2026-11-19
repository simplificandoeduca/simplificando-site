<?php
/* =====================================================================
   Play Space Finanças 2.3 (Play Space 1.62): as Festas, com o lucro real de cada uma
   Pedido do dono: "embaixo do caixa, um Festas: do sistema, a data, quem fez a venda, o valor bruto da festa, a data da festa, a forma de
   pagamento e em quantas vezes foi. E os campos que eu lanço à mão (as meninas não lançam): buffet (varia com a quantidade de crianças),
   salgados e papelaria; o adiantamento da festa (R$ 500, para balão e outras despesas) e o valor retornado (nem sempre gastam tudo).
   No fim, o lucro real. Tudo para digitar, tipo um Excel, mas com a cara do sistema."
   - Do sistema (só leitura): a festa (festas), as vendas ligadas a ela (a festa, o upgrade, o adicional: vendas.festa_id), os pagamentos
     dessas vendas por forma, em quantas vezes no crédito (vendas.parcelas, escolhido na venda da festa) e as crianças que entraram nela.
   - À mão (fin_festas, uma linha por festa; cada mudança em fin_festas_hist): buffet, salgados, papelaria, adiantamento, retornado, as
     vezes (quando a venda não disse) e uma observação.
   - Lucro real = valor bruto − buffet − salgados − papelaria − (adiantamento − retornado).
   ===================================================================== */
const FS_CAMPOS_RS = ['buffet', 'salgados', 'papelaria', 'adiant', 'retornado'];
const FS_SERV_FESTA = ['FESTA DE SEMANA', 'FESTA DE FINAL DE SEMANA'];

function fsPreparar(): void {
  static $ok = false; if ($ok) return; $ok = true;
  $db = db();
  $db->exec("CREATE TABLE IF NOT EXISTS fin_festas (festa_id INTEGER PRIMARY KEY, buffet REAL, salgados REAL, papelaria REAL, adiant REAL, retornado REAL,
    parcelas INTEGER, obs TEXT NOT NULL DEFAULT '', por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '')");
  $db->exec("CREATE TABLE IF NOT EXISTS fin_festas_hist (id INTEGER PRIMARY KEY, festa_id INTEGER NOT NULL, campo TEXT NOT NULL, antes TEXT, depois TEXT,
    por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '')");
}
function fsMes($m): string {
  $m = (string)$m; if (!preg_match('/^(\d{4})-(\d{2})$/', $m, $x) || (int)$x[2] < 1 || (int)$x[2] > 12) falha('Escolha o mês.');
  return $m;
}
function fsIn(array $ids): string { return implode(',', array_map('intval', $ids)) ?: '0'; }
/* a conta da festa: o que foi gasto do adiantamento e o lucro real */
function fsConta(array $x): array {
  $n = fn($k) => (float)($x[$k] ?? 0);
  $gasto = round($n('adiant') - $n('retornado'), 2);
  $custos = round($n('buffet') + $n('salgados') + $n('papelaria') + $gasto, 2);
  return ['gasto' => $gasto, 'custos' => $custos, 'lucro' => round($n('bruto') - $custos, 2)];
}

/* as festas do mês (pela data da festa), com o que vem do sistema e o que foi lançado à mão */
function fsListar(array $in): array {
  fsPreparar(); $db = db();
  $mes = fsMes($in['mes'] ?? date('Y-m', agoraTs())); $de = $mes . '-01'; $ate = date('Y-m-t', strtotime($de . ' 12:00:00'));
  $F = $db->prepare('SELECT f.*, c.nome aniv FROM festas f LEFT JOIN criancas c ON c.id = f.crianca_id WHERE f.data >= ? AND f.data <= ? ORDER BY f.data, f.hora, f.id');
  $F->execute([$de, $ate]); $festas = $F->fetchAll();
  $ids = array_map(fn($f) => (int)$f['id'], $festas); $in_ = fsIn($ids);
  $temParc = in_array('parcelas', array_column($db->query('PRAGMA table_info(vendas)')->fetchAll(), 'name'), true);
  $nomes = []; foreach ($db->query('SELECT id, nome FROM usuarios')->fetchAll() as $u) $nomes[(int)$u['id']] = (string)$u['nome'];
  $V = []; foreach ($db->query('SELECT id, festa_id, quando, por, valor, servico, festa_n, qtd' . ($temParc ? ', parcelas' : ', 0 parcelas') . ' FROM vendas WHERE festa_id IN (' . $in_ . ') ORDER BY id')->fetchAll() as $v) $V[(int)$v['festa_id']][] = $v;
  $P = []; foreach ($db->query('SELECT v.festa_id fid, p.forma f, SUM(p.valor) t FROM pagamentos p JOIN vendas v ON v.id = p.venda_id WHERE v.festa_id IN (' . $in_ . ') GROUP BY fid, f')->fetchAll() as $p)
    if ((float)$p['t'] > 0.004) $P[(int)$p['fid']][(string)$p['f']] = round((float)$p['t'], 2);
  $E = []; foreach ($db->query("SELECT festa_id, SUM(papel = 'convidado') c, SUM(papel != 'convidado') a FROM festa_presencas WHERE festa_id IN (" . $in_ . ') GROUP BY festa_id')->fetchAll() as $e) $E[(int)$e['festa_id']] = ['c' => (int)$e['c'], 'a' => (int)$e['a']];
  $M = []; foreach ($db->query('SELECT * FROM fin_festas WHERE festa_id IN (' . $in_ . ')')->fetchAll() as $m) $M[(int)$m['festa_id']] = $m;
  $uni = isset($in['u']) && $in['u'] !== '' && $in['u'] !== 'todas' ? (int)$in['u'] : null;
  $L = []; $T = ['n' => 0, 'bruto' => 0.0, 'buffet' => 0.0, 'salgados' => 0.0, 'papelaria' => 0.0, 'adiant' => 0.0, 'retornado' => 0.0, 'gasto' => 0.0, 'custos' => 0.0, 'lucro' => 0.0, 'faltam' => 0];
  foreach ($festas as $f) {
    $id = (int)$f['id']; $vs = $V[$id] ?? []; $m = $M[$id] ?? null;
    if (!$vs && !$m) continue;   /* festa sem venda (cancelada) e sem nada lançado: não entra */
    if ($uni !== null && (int)($f['unidade_id'] ?? 0) !== $uni) continue;
    $principal = null; foreach ($vs as $v) if (in_array((string)$v['servico'], FS_SERV_FESTA, true)) { $principal = $v; break; }
    if (!$principal && $vs) $principal = $vs[0];
    $contrat = 0; $extra = 0; $parc = 0; $bruto = 0.0; $vendedoras = [];
    foreach ($vs as $v) { if ((int)$v['festa_n'] > 0) $contrat = (int)$v['festa_n']; if ((string)$v['servico'] === 'ADICIONAL DE FESTA') $extra += (int)$v['qtd'];
      $parc = max($parc, (int)$v['parcelas']); $bruto = round($bruto + (float)$v['valor'], 2); }
    $pago = round(array_sum($P[$id] ?? []), 2);
    $formas = []; foreach ((array)($P[$id] ?? []) as $fm => $val) $formas[] = ['f' => $fm, 'nome' => FORMAS_PAGAMENTO[$fm] ?? $fm, 'v' => $val];
    $man = []; foreach (FS_CAMPOS_RS as $k) $man[$k] = $m && $m[$k] !== null ? round((float)$m[$k], 2) : null;
    $linha = ['id' => $id, 'data' => (string)$f['data'], 'hora' => (string)$f['hora'], 'tema' => (string)$f['tema'], 'dia' => (string)$f['dia'], 'u' => (int)($f['unidade_id'] ?? 0),
      'aniv' => (string)($f['aniv'] ?? ''), 'venda' => $principal ? substr((string)$principal['quando'], 0, 10) : '', 'vendaHora' => $principal ? substr((string)$principal['quando'], 11, 5) : '',
      'vendedora' => $principal ? ($nomes[(int)$principal['por']] ?? 'Sem nome') : '', 'cancelada' => !$vs,
      'contratadas' => $contrat, 'extra' => $extra, 'vieram' => $E[$id]['c'] ?? 0, 'bruto' => $bruto, 'pago' => $pago, 'formas' => $formas,
      'parcSis' => $parc, 'parcelas' => $m && $m['parcelas'] !== null ? (int)$m['parcelas'] : null, 'obs' => $m ? (string)$m['obs'] : '',
      'por' => $m ? (string)$m['por'] : '', 'em' => $m ? (string)$m['em'] : ''] + $man;
    $linha += fsConta($linha);
    $linha['falta'] = $man['buffet'] === null && $man['salgados'] === null && $man['papelaria'] === null;
    $L[] = $linha; $T['n']++; if ($linha['falta']) $T['faltam']++;
    foreach (['bruto', 'gasto', 'custos', 'lucro'] as $k) $T[$k] = round($T[$k] + $linha[$k], 2);
    foreach (FS_CAMPOS_RS as $k) $T[$k] = round($T[$k] + (float)($man[$k] ?? 0), 2);
  }
  return ['mes' => $mes, 'festas' => $L, 'total' => $T, 'nomes' => unidades()];
}

/* lança (ou tira) um campo à mão de uma festa */
function fsSalvar(array $in, array $eu): array {
  fsPreparar(); $db = db();
  $id = (int)($in['id'] ?? 0); $campo = (string)($in['campo'] ?? '');
  $q = $db->prepare('SELECT id FROM festas WHERE id = ?'); $q->execute([$id]); if (!$q->fetchColumn()) falha('Festa não encontrada.', 404);
  if (!in_array($campo, array_merge(FS_CAMPOS_RS, ['parcelas', 'obs']), true)) falha('Campo desconhecido.');
  $q = $db->prepare('SELECT * FROM fin_festas WHERE festa_id = ?'); $q->execute([$id]); $m = $q->fetch() ?: null;
  if ($campo === 'obs') $v = mb_substr(trim((string)preg_replace('/\s+/u', ' ', (string)($in['valor'] ?? ''))), 0, 300);
  elseif ($campo === 'parcelas') { $t = trim((string)($in['valor'] ?? '')); $v = $t === '' ? null : (int)preg_replace('/\D/', '', $t); if ($v !== null && ($v < 1 || $v > 12)) falha('As vezes vão de 1 a 12.'); }
  else $v = cxValor($in['valor'] ?? null);
  /* o retornado nunca passa do adiantamento (o que voltou do que foi dado) */
  $ad = $campo === 'adiant' ? $v : ($m ? $m['adiant'] : null); $re = $campo === 'retornado' ? $v : ($m ? $m['retornado'] : null);
  if ($re !== null && (float)$re > (float)($ad ?? 0) + 0.004) falha('O valor retornado (' . finBrl($re) . ') passa do adiantamento (' . finBrl((float)($ad ?? 0)) . '). Confira os dois.');
  $antes = $m ? $m[$campo] : null;
  $mudou = $campo === 'obs' ? (string)($antes ?? '') !== $v : (($antes === null) !== ($v === null) || ($v !== null && abs((float)$antes - (float)$v) > 0.004));
  if ($mudou) {
    $quem = (string)$eu['nome']; $em = agora();
    if (!$m) $db->prepare('INSERT INTO fin_festas (festa_id) VALUES (?)')->execute([$id]);
    $db->prepare("UPDATE fin_festas SET $campo = ?, por = ?, em = ? WHERE festa_id = ?")->execute([$v, $quem, $em, $id]);
    $db->prepare('INSERT INTO fin_festas_hist (festa_id, campo, antes, depois, por, em) VALUES (?, ?, ?, ?, ?, ?)')->execute([$id, $campo, $antes === null ? null : (string)$antes, $v === null ? null : (string)$v, $quem, $em]);
  }
  $q = $db->prepare('SELECT substr(data, 1, 7) FROM festas WHERE id = ?'); $q->execute([$id]); $mes = (string)$q->fetchColumn();
  $R = fsListar(['mes' => $mes]); $R['festa'] = null; foreach ($R['festas'] as $x) if ($x['id'] === $id) $R['festa'] = $x;
  return $R;
}
