<?php
/* =====================================================================
   Play Space Finanças 2.8 (Play Space 1.67): as Comissões (o bônus das vendedoras).
   Pedido do dono: "uma aba Comissões. Toda vez que elas forem vender um pacote, selecionar o nome de quem está vendendo (Aline, Edjane,
   Karol e Maria Vitória); nas comissões vai constar o nome. As regras pré-configuradas e uma opção para o financeiro editar, caso amanhã
   mude e passe a ser 5%, 1%". O comunicado do bônus trimestral (válido a partir de 15/05/2026):
     produto de R$ 190 → 7% · produto de R$ 350 → R$ 30 fixo · pacote de 5 h (R$ 400) → 2,5% · pacote de 10 h (R$ 800) → 2,5% ·
     pacotes sem produto de R$ 450 a R$ 580 → 3%.
   - Cada linha da venda (o pacote, o produto) passa pelas regras na ordem da lista: a primeira que serve é a que conta.
     O valor que a regra olha é o de tabela (o da venda antes do desconto). O bônus em % é sobre o que foi pago pela linha (já com o
     desconto); o bônus fixo é por unidade do produto.
   - "Pacote sem produto": o pacote vendido sem nenhum produto no mesmo atendimento (e que não é um pacote "com produto").
   - A venda cancelada sai sozinha (ela deixa de existir). A venda de pacote ou de produto sem o nome (de antes da 1.67) aparece à parte.
   - As regras e os nomes mudam aqui; cada mudança fica guardada (quem, quando, como era e como ficou). Regra não se apaga: encerra
     (a data "até"), e as vendas antigas continuam com a regra da época.
   ===================================================================== */
declare(strict_types=1);

const CM_TIPOS = ['produto' => 'Produto', 'pacote' => 'Pacote', 'pacote_sem' => 'Pacote sem produto'];
const CM_REGRAS_PADRAO = [
  ['id' => 'p190', 'tipo' => 'produto', 'h' => 0, 'de' => 190.0, 'ate' => 190.0, 'modo' => 'pct', 'bonus' => 7.0, 'desde' => '2026-05-15', 'fim' => ''],
  ['id' => 'p350', 'tipo' => 'produto', 'h' => 0, 'de' => 350.0, 'ate' => 350.0, 'modo' => 'fixo', 'bonus' => 30.0, 'desde' => '2026-05-15', 'fim' => ''],
  ['id' => 'k5', 'tipo' => 'pacote', 'h' => 5, 'de' => 400.0, 'ate' => 400.0, 'modo' => 'pct', 'bonus' => 2.5, 'desde' => '2026-05-15', 'fim' => ''],
  ['id' => 'k10', 'tipo' => 'pacote', 'h' => 10, 'de' => 800.0, 'ate' => 800.0, 'modo' => 'pct', 'bonus' => 2.5, 'desde' => '2026-05-15', 'fim' => ''],
  ['id' => 'ks', 'tipo' => 'pacote_sem', 'h' => 0, 'de' => 450.0, 'ate' => 580.0, 'modo' => 'pct', 'bonus' => 3.0, 'desde' => '2026-05-15', 'fim' => ''],
];
const CM_REGRAS_MAX = 40;

function cmPreparar(): void {
  db()->exec("CREATE TABLE IF NOT EXISTS fin_com_hist (id INTEGER PRIMARY KEY, quando TEXT NOT NULL, quem TEXT NOT NULL DEFAULT '', oque TEXT NOT NULL, antes TEXT NOT NULL DEFAULT '', depois TEXT NOT NULL DEFAULT '')");
}
function cmData($d): string { $d = (string)$d; return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $d : ''; }
function cmNum($v): float { if (is_string($v)) { $v = trim(str_replace(['R$', ' ', '%'], '', $v)); if (strpos($v, ',') !== false) $v = str_replace(['.', ','], ['', '.'], $v); } return round(max(0, (float)$v), 2); }
/* uma regra conferida (null = a linha está vazia ou não serve) */
function cmRegraLimpa(array $r, int $i) {
  $tipo = (string)($r['tipo'] ?? ''); if (!isset(CM_TIPOS[$tipo])) falha('Regra ' . ($i + 1) . ': escolha o que ela paga (produto, pacote ou pacote sem produto).');
  $de = cmNum($r['de'] ?? 0); $ate = array_key_exists('ate', $r) && trim((string)$r['ate']) !== '' ? cmNum($r['ate']) : $de;
  if ($de <= 0 && $ate <= 0) falha('Regra ' . ($i + 1) . ': escreva o valor da venda (por exemplo, 190 ou de 450 a 580).');
  if ($ate < $de) [$de, $ate] = [$ate, $de];
  $modo = ($r['modo'] ?? '') === 'fixo' ? 'fixo' : 'pct'; $bonus = cmNum($r['bonus'] ?? 0);
  if ($bonus <= 0) falha('Regra ' . ($i + 1) . ': escreva o bônus (em % ou em R$).');
  if ($modo === 'pct' && $bonus > 100) falha('Regra ' . ($i + 1) . ': o bônus em % vai até 100.');
  $desde = cmData($r['desde'] ?? ''); if ($desde === '') falha('Regra ' . ($i + 1) . ': escreva a data em que ela começa a valer.');
  $fim = cmData($r['fim'] ?? ''); if (trim((string)($r['fim'] ?? '')) !== '' && $fim === '') falha('Regra ' . ($i + 1) . ': a data "até" não é uma data válida.');
  if ($fim !== '' && $fim < $desde) falha('Regra ' . ($i + 1) . ': a data "até" é antes da data em que ela começa.');
  $id = preg_replace('/[^a-z0-9]/', '', strtolower((string)($r['id'] ?? ''))); if ($id === '') $id = 'r' . substr(bin2hex(random_bytes(4)), 0, 6);
  return ['id' => substr($id, 0, 12), 'tipo' => $tipo, 'h' => $tipo === 'produto' ? 0 : max(0, min(500, (int)($r['h'] ?? 0))), 'de' => $de, 'ate' => $ate, 'modo' => $modo, 'bonus' => $bonus, 'desde' => $desde, 'fim' => $fim];
}
function cmRegras(): array {
  $c = cfg('fin_comissoes', null); if (!is_array($c) || !isset($c['regras']) || !is_array($c['regras'])) return CM_REGRAS_PADRAO;
  $r = []; foreach ($c['regras'] as $i => $x) { if (!is_array($x)) continue; try { $r[] = cmRegraLimpa($x, (int)$i); } catch (Throwable $e) { } }
  return $r;
}
/* o nome da regra, para ler: "Produto de R$ 190,00 → 7%", "Pacote de 5 h de R$ 400,00 → 2,5%", "Pacote sem produto de R$ 450,00 a R$ 580,00 → 3%" */
function cmRegraNome(array $r): string {
  $v = abs($r['ate'] - $r['de']) < 0.005 ? finBrl($r['de']) : finBrl($r['de']) . ' a ' . finBrl($r['ate']);
  $b = $r['modo'] === 'fixo' ? finBrl($r['bonus']) . ' fixo' : rtrim(rtrim(number_format($r['bonus'], 2, ',', ''), '0'), ',') . '%';
  return CM_TIPOS[$r['tipo']] . ($r['h'] ? ' de ' . $r['h'] . ' h' : '') . ' de ' . $v . ' → ' . $b;
}
/* o nome curto (na coluna da tabela do PDF): "Produto R$ 190 · 7%", "Pacote 5 h R$ 400 · 2,5%", "Sem produto R$ 450 a 580 · 3%" */
function cmRegraCurta(array $r): string {
  $f = fn($v) => abs($v - round($v)) < 0.005 ? 'R$ ' . number_format($v, 0, ',', '.') : finBrl($v);
  $v = abs($r['ate'] - $r['de']) < 0.005 ? $f($r['de']) : $f($r['de']) . ' a ' . number_format($r['ate'], abs($r['ate'] - round($r['ate'])) < 0.005 ? 0 : 2, ',', '.');
  $b = $r['modo'] === 'fixo' ? $f($r['bonus']) : rtrim(rtrim(number_format($r['bonus'], 2, ',', ''), '0'), ',') . '%';
  return ['produto' => 'Produto', 'pacote' => 'Pacote', 'pacote_sem' => 'Sem produto'][$r['tipo']] . ($r['h'] ? ' ' . $r['h'] . ' h' : '') . ' ' . $v . ' · ' . $b;
}
function cmHist(string $oque, $antes, $depois, array $eu): void {
  cmPreparar();
  db()->prepare('INSERT INTO fin_com_hist (quando, quem, oque, antes, depois) VALUES (?, ?, ?, ?, ?)')->execute([date('Y-m-d H:i:s', agoraTs()), (string)($eu['nome'] ?? ''), $oque, json_encode($antes, JSON_UNESCAPED_UNICODE), json_encode($depois, JSON_UNESCAPED_UNICODE)]);
}
function cmRegrasSalvar(array $in, array $eu): array {
  $L = (array)($in['regras'] ?? []); if (count($L) > CM_REGRAS_MAX) falha('São regras demais (até ' . CM_REGRAS_MAX . ').');
  $novas = []; $ids = [];
  foreach (array_values($L) as $i => $x) { if (!is_array($x)) continue; $r = cmRegraLimpa($x, $i); while (isset($ids[$r['id']])) $r['id'] = 'r' . substr(bin2hex(random_bytes(4)), 0, 6); $ids[$r['id']] = 1; $novas[] = $r; }
  $antes = cmRegras();
  /* regra não se apaga: a que sumiu da lista volta encerrada (o financeiro encerra com a data "até") */
  foreach ($antes as $a) if (!isset($ids[$a['id']])) falha('Uma regra não pode ser apagada: para ela parar de valer, escreva a data "até" (ou toque em Encerrar).');
  if ($novas != $antes) { gravarCfg('fin_comissoes', ['regras' => $novas]); cmHist('regras', $antes, $novas, $eu); }
  return ['regras' => cmRegras(), 'hist' => cmHistorico()];
}
function cmVendedorasSalvar(array $in, array $eu): array {
  $novas = vendedorasLimpas((array)($in['vendedoras'] ?? [])); if (!$novas) falha('Deixe pelo menos um nome.');
  $antes = vendedoras(); if ($novas !== $antes) { gravarCfg('vendedoras', $novas); cmHist('vendedoras', $antes, $novas, $eu); }
  return ['vendedoras' => vendedoras(), 'hist' => cmHistorico()];
}
function cmHistorico(): array {
  cmPreparar(); $R = [];
  foreach (db()->query('SELECT * FROM fin_com_hist ORDER BY id DESC LIMIT 20')->fetchAll() as $h) {
    $a = json_decode((string)$h['antes'], true); $d = json_decode((string)$h['depois'], true); $txt = '';
    if ($h['oque'] === 'vendedoras') $txt = 'Os nomes: ' . implode(', ', (array)$d);
    else { $na = array_map('cmRegraNome', array_filter((array)$a, 'is_array')); $nd = array_map('cmRegraNome', array_filter((array)$d, 'is_array'));
      $mud = array_values(array_diff($nd, $na)); $txt = $mud ? 'Regras: ' . implode(' · ', array_slice($mud, 0, 4)) . (count($mud) > 4 ? '…' : '') : 'Regras: datas ou ordem'; }
    $R[] = ['quando' => (string)$h['quando'], 'quem' => (string)$h['quem'], 'oque' => (string)$h['oque'], 'txt' => $txt];
  }
  return $R;
}
/* o período: um trimestre ("2026-T4") ou um mês ("2026-11") */
function cmPeriodo($p): array {
  $p = (string)$p; $agora = agoraTs();
  if (preg_match('/^(\d{4})-T([1-4])$/', $p, $m)) { $a = (int)$m[1]; $t = (int)$m[2]; }
  elseif (preg_match('/^(\d{4})-(\d{2})$/', $p, $m) && (int)$m[2] >= 1 && (int)$m[2] <= 12) {
    $de = $p . '-01'; return ['per' => $p, 'tipo' => 'mes', 'de' => $de, 'ate' => date('Y-m-t', strtotime($de . ' 12:00:00')), 'nome' => finMesNome($p), 'meses' => [$p]]; }
  else { $a = (int)date('Y', $agora); $t = intdiv((int)date('n', $agora) - 1, 3) + 1; }
  $m1 = ($t - 1) * 3 + 1; $meses = []; for ($i = 0; $i < 3; $i++) $meses[] = sprintf('%04d-%02d', $a, $m1 + $i);
  $NM = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  return ['per' => $a . '-T' . $t, 'tipo' => 'tri', 'de' => $meses[0] . '-01', 'ate' => date('Y-m-t', strtotime($meses[2] . '-01 12:00:00')), 'nome' => $t . 'º trimestre de ' . $a . ' (' . $NM[$m1 - 1] . ' a ' . $NM[$m1 + 1] . ')', 'meses' => $meses];
}
/* a regra que serve para uma linha (null = nenhuma) */
function cmRegraDa(array $l, array $regras, bool $grupoComProduto): ?array {
  $dia = substr((string)$l['quando'], 0, 10); $k = (string)$l['vend_k']; $qtd = max(1, (int)($l['qtd'] ?? 1));
  $tab = round((float)$l['valor'] + (float)$l['desc_valor'], 2); $un = $k === 'produto' ? round($tab / $qtd, 2) : $tab; $h = (int)$l['minutos'] / 60;
  foreach ($regras as $r) {
    if ($dia < $r['desde'] || ($r['fim'] !== '' && $dia > $r['fim'])) continue;
    if ($r['tipo'] === 'produto' && $k !== 'produto') continue;
    if ($r['tipo'] === 'pacote' && $k !== 'pacote' && $k !== 'pacote_prod') continue;
    if ($r['tipo'] === 'pacote_sem' && ($k !== 'pacote' || $grupoComProduto)) continue;
    if ($r['h'] && abs($h - $r['h']) > 0.001) continue;
    if ($un < $r['de'] - 0.004 || $un > $r['ate'] + 0.004) continue;
    return $r;
  }
  return null;
}
function cmBonus(array $r, array $l): float { return round($r['modo'] === 'fixo' ? $r['bonus'] * ($l['vend_k'] === 'produto' ? max(1, (int)$l['qtd']) : 1) : (float)$l['valor'] * $r['bonus'] / 100, 2); }
function cmListar(array $in): array {
  $db = db(); $P = cmPeriodo($in['per'] ?? ''); $regras = cmRegras();
  $uni = isset($in['u']) && $in['u'] !== '' && $in['u'] !== 'todas' ? (int)$in['u'] : null;
  $q = $db->prepare("SELECT v.id, v.quando, v.valor, v.desc_valor, v.minutos, v.servico, v.itens, COALESCE(v.qtd, 1) qtd, v.vend_k, v.vendedora, v.vend_g, COALESCE(v.unidade_id, 0) u, c.nome cri, c.resp
    FROM vendas v LEFT JOIN criancas c ON c.id = v.crianca_id WHERE v.vend_k != '' AND v.quando >= ? AND v.quando < ?" . ($uni !== null ? ' AND COALESCE(v.unidade_id, 0) = ' . $uni : '') . ' ORDER BY v.quando, v.id');
  $q->execute([$P['de'], date('Y-m-d', strtotime($P['ate'] . ' 12:00:00') + 86400)]); $V = $q->fetchAll();
  $comProd = []; foreach ($V as $l) if ($l['vend_k'] === 'produto' && $l['vend_g']) $comProd[(int)$l['vend_g']] = 1;
  $L = []; $porV = []; $sem = ['n' => 0, 'v' => 0.0]; $tot = ['bonus' => 0.0, 'vendido' => 0.0, 'n' => 0, 'semRegra' => 0];
  foreach ($V as $l) {
    $r = cmRegraDa($l, $regras, isset($comProd[(int)$l['vend_g']])); $b = $r ? cmBonus($r, $l) : 0.0; $vend = (string)$l['vendedora'];
    $item = trim((string)$l['servico']) !== '' ? (string)$l['servico'] : nomePacoteVenda((int)$l['minutos'], (string)$l['itens']);
    $L[] = ['id' => (int)$l['id'], 'quando' => (string)$l['quando'], 'u' => (int)$l['u'], 'cri' => (string)($l['cri'] ?? ''), 'resp' => (string)($l['resp'] ?? ''), 'item' => $item, 'k' => (string)$l['vend_k'],
      'qtd' => (int)$l['qtd'], 'tab' => round((float)$l['valor'] + (float)$l['desc_valor'], 2), 'pago' => round((float)$l['valor'], 2), 'vend' => $vend, 'regra' => $r ? $r['id'] : '', 'rnome' => $r ? cmRegraNome($r) : '', 'rcurta' => $r ? cmRegraCurta($r) : '', 'bonus' => $b];
    if ($vend === '') { $sem['n']++; $sem['v'] += (float)$l['valor']; continue; }
    if (!isset($porV[$vend])) { $porV[$vend] = ['n' => $vend, 'vendas' => 0, 'vendido' => 0.0, 'bonus' => 0.0, 'comBonus' => 0, 'meses' => array_fill_keys($P['meses'], 0.0)]; }
    $x = &$porV[$vend]; $x['vendas']++; $x['vendido'] += (float)$l['valor']; $x['bonus'] += $b; if ($b > 0) $x['comBonus']++; $mm = substr((string)$l['quando'], 0, 7); if (isset($x['meses'][$mm])) $x['meses'][$mm] += $b; unset($x);
    $tot['bonus'] += $b; $tot['vendido'] += (float)$l['valor']; $tot['n']++; if (!$r) $tot['semRegra']++;
  }
  /* as vendedoras da lista aparecem mesmo sem venda no período (com zero) */
  foreach (vendedoras() as $n) if (!isset($porV[$n])) $porV[$n] = ['n' => $n, 'vendas' => 0, 'vendido' => 0.0, 'bonus' => 0.0, 'comBonus' => 0, 'meses' => array_fill_keys($P['meses'], 0.0)];
  $porV = array_values($porV); usort($porV, fn($a, $b) => [$b['bonus'], $b['vendido'], $a['n']] <=> [$a['bonus'], $a['vendido'], $b['n']]);
  foreach ($porV as &$x) { $x['vendido'] = round($x['vendido'], 2); $x['bonus'] = round($x['bonus'], 2); foreach ($x['meses'] as $k => $v) $x['meses'][$k] = round($v, 2); } unset($x);
  $tot['bonus'] = round($tot['bonus'], 2); $tot['vendido'] = round($tot['vendido'], 2); $sem['v'] = round($sem['v'], 2);
  return ['per' => $P['per'], 'tipo' => $P['tipo'], 'de' => $P['de'], 'ate' => $P['ate'], 'nome' => $P['nome'], 'meses' => $P['meses'], 'linhas' => $L, 'porVend' => $porV, 'sem' => $sem, 'total' => $tot,
    'regras' => array_map(fn($r) => $r + ['nome' => cmRegraNome($r)], $regras), 'tipos' => CM_TIPOS, 'vendedoras' => vendedoras(), 'hist' => cmHistorico(), 'unidades' => unidades()];
}
/* o PDF bonito das comissões do período */
function cmPdf(array $in): string {
  $R = cmListar($in); $T = $R['total']; $uni = isset($in['u']) && $in['u'] !== '' && $in['u'] !== 'todas' ? (int)$in['u'] : null;
  $nomeU = []; foreach ($R['unidades'] as $u) $nomeU[(int)$u['id']] = (string)$u['nome'];
  $com = array_values(array_filter($R['porVend'], fn($x) => $x['vendas'] > 0));
  $d = new PdfBonito('Comissões', $R['nome'] . ' · ' . ($uni ? $nomeU[$uni] ?? 'unidade' : 'todas as unidades'), 'Comissões');
  $top = $com ? $com[0] : null;
  $fala = !$T['n'] ? 'Nenhuma venda com vendedora neste período.' : ($top && $top['bonus'] > 0 ? 'Quem mais ganhou bônus: ' . mb_convert_case($top['n'], MB_CASE_TITLE, 'UTF-8') . ', ' . finBrl($top['bonus']) . '.' : 'Nenhuma venda deu bônus pelas regras.');
  $d->capa([count($com) . (count($com) === 1 ? ' vendedora' : ' vendedoras'), $T['n'] . ($T['n'] === 1 ? ' venda' : ' vendas')], $fala);
  $d->kpis([['Bônus do período', finBrl($T['bonus']), 'a soma de todas', PdfBonito::CORAL], ['Vendido', finBrl($T['vendido']), 'com o nome da vendedora', PdfBonito::VERDE],
    ['Vendas', (string)$T['n'], $T['semRegra'] ? $T['semRegra'] . ' sem regra (sem bônus)' : 'todas com regra', PdfBonito::MOSTARDA], ['Sem vendedora', (string)$R['sem']['n'], $R['sem']['n'] ? finBrl($R['sem']['v']) . ' sem o nome' : 'todas com o nome', $R['sem']['n'] ? PdfBonito::VERM : PdfBonito::PIX]]);
  if ($com) {
    $d->secao('O bônus de cada vendedora', 'O total do período, pelas regras de comissão.', 30 + 18 * min(8, count($com)));
    $d->barrasH(array_map(fn($x) => [$x['n'], $x['bonus'], PdfBonito::CORAL, finBrl($x['bonus']) . ' · ' . $x['vendas'] . ($x['vendas'] === 1 ? ' venda' : ' vendas')], $com));
    if (count($R['meses']) > 1) {
      $cats = array_map(fn($m) => mb_substr(finMesNome($m), 0, 3), $R['meses']); $cores = [PdfBonito::CORAL, PdfBonito::MOSTARDA, PdfBonito::VERDE, PdfBonito::PIX, PdfBonito::PESSEGO, PdfBonito::CINZA];
      $d->secao('Mês a mês', 'O bônus de cada vendedora em cada mês do trimestre.', 210);
      $d->barras(150, $cats, array_map(fn($x, $i) => [$x['n'], $cores[$i % 6], array_values($x['meses'])], array_slice($com, 0, 6), array_keys(array_slice($com, 0, 6))), false, 'em reais');
    }
    $d->secao('Resumo', 'Vendas com vendedora no período.');
    $d->tabela([['Vendedora', .34, 'E'], ['Vendas', .14, 'D'], ['Com bônus', .16, 'D'], ['Vendido', .18, 'D'], ['Bônus', .18, 'D']], array_map(fn($x) => [$x['n'], (string)$x['vendas'], (string)$x['comBonus'], finBrl($x['vendido']), ['t' => finBrl($x['bonus']), 'b' => true, 'cor' => PdfBonito::CORAL]], $com),
      ['Total', (string)$T['n'], (string)array_sum(array_column($com, 'comBonus')), finBrl($T['vendido']), finBrl($T['bonus'])]);
  }
  foreach ($com as $x) {
    $L = array_values(array_filter($R['linhas'], fn($l) => $l['vend'] === $x['n'])); $fundo = [];
    $d->secao($x['n'], $x['vendas'] . ($x['vendas'] === 1 ? ' venda' : ' vendas') . ' · vendido ' . finBrl($x['vendido']) . ' · bônus ' . finBrl($x['bonus']));
    $T2 = [];
    foreach ($L as $k => $l) { $T2[] = [substr($l['quando'], 8, 2) . '/' . substr($l['quando'], 5, 2), mb_substr($l['item'], 0, 34), mb_substr($l['resp'] !== '' ? $l['resp'] : $l['cri'], 0, 22), finBrl($l['pago']), $l['rcurta'] !== '' ? $l['rcurta'] : ['t' => 'sem regra', 'cor' => PdfBonito::MUDO], $l['bonus'] > 0 ? ['t' => finBrl($l['bonus']), 'b' => true] : '—'];
      if ($l['bonus'] <= 0) $fundo[$k] = 'F6F1EA'; }
    $d->tabela([['Dia', .07, 'E'], ['O que', .25, 'E'], ['Cliente', .17, 'E'], ['Pago', .11, 'D'], ['Regra', .29, 'E'], ['Bônus', .11, 'D']], $T2, ['Total', '', '', finBrl($x['vendido']), '', finBrl($x['bonus'])], ['fundo' => $fundo]);
  }
  $d->secao('As regras', 'A primeira regra da lista que serve é a que conta. % sobre o que foi pago (já com o desconto); o fixo é por unidade.', 60 + 18 * count($R['regras']));
  $d->tabela([['Regra', .62, 'E'], ['Vale de', .19, 'E'], ['Até', .19, 'E']], array_map(fn($r) => [$r['nome'], finDataBR($r['desde']), $r['fim'] !== '' ? finDataBR($r['fim']) : '—'], $R['regras']), null);
  if ($R['sem']['n']) $d->nota($R['sem']['n'] . ($R['sem']['n'] === 1 ? ' venda' : ' vendas') . ' de pacote ou produto sem o nome da vendedora (' . finBrl($R['sem']['v']) . ') não ' . ($R['sem']['n'] === 1 ? 'entra' : 'entram') . ' no bônus: são de antes da escolha da vendedora no atendimento.');
  return $d->pdf();
}
