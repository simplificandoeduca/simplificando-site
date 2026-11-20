<?php
/* =====================================================================
   Play Space Finanças 3.0 (Play Space 1.69): o relatório por cliente.
   Pedido do dono: "uma opção de relatório com o nome do cliente e o pacote e o produto, tudo discriminado".
   - Cada atendimento em que houve pacote ou produto (no período e na unidade): quando, o cliente (o responsável e a criança), cada pacote
     (o nome, as horas, o valor), cada produto (o nome, a quantidade, o valor), o desconto, o total, como foi pago e quem vendeu.
   - O atendimento é o mesmo grupo de vendas da 1.67 (vend_g); nas vendas de antes, as linhas da mesma criança no mesmo segundo.
   - A venda cancelada não aparece (ela deixa de existir). A festa e a recreação avulsa não entram (têm as abas delas).
   ===================================================================== */
declare(strict_types=1);

function vcDia($d, string $padrao): string { $d = (string)$d; return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $d : $padrao; }
function vcListar(array $in): array {
  $db = db(); $hoje = date('Y-m-d', agoraTs()); $de = vcDia($in['de'] ?? '', $hoje); $ate = vcDia($in['ate'] ?? '', $de); if ($ate < $de) [$de, $ate] = [$ate, $de];
  if ((strtotime($ate . ' 12:00:00') - strtotime($de . ' 12:00:00')) / 86400 > 400) falha('Escolha um período de até 400 dias.');
  $uni = isset($in['u']) && $in['u'] !== '' && $in['u'] !== 'todas' ? (int)$in['u'] : null;
  $q = $db->prepare("SELECT v.id, v.crianca_id, v.quando, v.valor, v.desc_valor, v.minutos, v.servico, v.itens, COALESCE(v.produto_id, 0) pr, COALESCE(v.qtd, 1) qtd, v.alug, v.auto, v.voucher,
      v.vend_k, v.vendedora, v.vend_g, COALESCE(v.unidade_id, 0) u, c.nome cri, c.resp, c.tel
    FROM vendas v LEFT JOIN criancas c ON c.id = v.crianca_id WHERE v.quando >= ? AND v.quando < ? AND v.servico != ?" . ($uni !== null ? ' AND COALESCE(v.unidade_id, 0) = ' . $uni : '') . ' ORDER BY v.quando, v.id');
  $q->execute([$de, date('Y-m-d', strtotime($ate . ' 12:00:00') + 86400), SERV_IMPORTADO]);
  $G = []; $ordem = [];
  foreach ($q->fetchAll() as $v) {
    $g = grupoDoCaixa(['s' => (string)$v['servico'], 'pr' => (int)$v['pr'], 'a' => (string)$v['alug'], 'm' => (int)$v['minutos'], 'au' => (int)$v['auto'], 'vo' => (string)$v['voucher']]);
    if ($g !== 'pacotes' && $g !== 'produtos') continue;
    $k = $v['vend_g'] ? 'g' . (int)$v['vend_g'] : 'c' . (int)$v['crianca_id'] . '|' . (string)$v['quando'];
    if (!isset($G[$k])) { $G[$k] = ['quando' => (string)$v['quando'], 'u' => (int)$v['u'], 'resp' => (string)($v['resp'] ?? ''), 'cri' => (string)($v['cri'] ?? ''), 'tel' => (string)($v['tel'] ?? ''), 'pacotes' => [], 'produtos' => [], 'desconto' => 0.0, 'total' => 0.0, 'vendas' => [], 'vend' => '']; $ordem[] = $k; }
    $x = &$G[$k]; $val = round((float)$v['valor'], 2); $x['vendas'][] = (int)$v['id']; $x['total'] = round($x['total'] + $val, 2); $x['desconto'] = round($x['desconto'] + (float)$v['desc_valor'], 2);
    if ($x['vend'] === '' && (string)$v['vendedora'] !== '') $x['vend'] = (string)$v['vendedora'];
    if ($g === 'pacotes') $x['pacotes'][] = ['n' => trim((string)$v['servico']) !== '' ? (string)$v['servico'] : nomePacoteVenda((int)$v['minutos'], (string)$v['itens']), 'h' => round((int)$v['minutos'] / 60, 1), 'v' => $val, 'com' => preg_match('/^com /u', (string)$v['itens']) ? mb_substr((string)$v['itens'], 4) : ''];
    else $x['produtos'][] = ['n' => (string)$v['servico'], 'q' => max(1, (int)$v['qtd']), 'v' => $val];
    unset($x);
  }
  /* como foi pago: as formas dos pagamentos das vendas do atendimento */
  $ids = []; foreach ($G as $x) foreach ($x['vendas'] as $id) $ids[] = $id; $pg = [];
  foreach (array_chunk($ids, 400) as $ch) { $r = $db->query('SELECT venda_id, forma, SUM(valor) v FROM pagamentos WHERE venda_id IN (' . implode(',', $ch) . ') GROUP BY venda_id, forma'); foreach ($r->fetchAll() as $p) $pg[(int)$p['venda_id']][(string)$p['forma']] = (float)$p['v']; }
  $L = []; $T = ['atend' => 0, 'clientes' => 0, 'pacN' => 0, 'pacV' => 0.0, 'prodN' => 0, 'prodV' => 0.0, 'desconto' => 0.0, 'total' => 0.0, 'aReceber' => 0.0]; $cli = [];
  foreach ($ordem as $k) { $x = $G[$k]; $f = [];
    foreach ($x['vendas'] as $id) foreach (($pg[$id] ?? []) as $fo => $vv) $f[$fo] = round(($f[$fo] ?? 0) + $vv, 2);
    $pago = round(array_sum($f), 2); $x['formas'] = []; foreach ($f as $fo => $vv) $x['formas'][] = ['f' => $fo, 'n' => FORMAS_PAGAMENTO[$fo] ?? $fo, 'v' => $vv];
    $x['aReceber'] = round(max(0, $x['total'] - $pago), 2); unset($x['vendas']); $L[] = $x;
    $T['atend']++; $cli[mb_strtoupper(trim($x['resp'] . '|' . $x['cri']))] = 1; $T['pacN'] += count($x['pacotes']); $T['pacV'] += array_sum(array_column($x['pacotes'], 'v'));
    $T['prodN'] += array_sum(array_column($x['produtos'], 'q')); $T['prodV'] += array_sum(array_column($x['produtos'], 'v')); $T['desconto'] += $x['desconto']; $T['total'] += $x['total']; $T['aReceber'] += $x['aReceber']; }
  $T['clientes'] = count($cli); foreach (['pacV', 'prodV', 'desconto', 'total', 'aReceber'] as $k) $T[$k] = round($T[$k], 2);
  return ['de' => $de, 'ate' => $ate, 'linhas' => $L, 'total' => $T, 'unidades' => unidades()];
}
/* o PDF bonito do relatório por cliente */
function vcPdf(array $in): string {
  $R = vcListar($in); $T = $R['total']; $uni = isset($in['u']) && $in['u'] !== '' && $in['u'] !== 'todas' ? (int)$in['u'] : null;
  $nomeU = []; foreach ($R['unidades'] as $u) $nomeU[(int)$u['id']] = (string)$u['nome'];
  $per = $R['de'] === $R['ate'] ? finDataBR($R['de']) : finDataBR($R['de']) . ' a ' . finDataBR($R['ate']);
  $d = new PdfBonito('Vendas por cliente', $per . ' · ' . ($uni ? $nomeU[$uni] ?? 'unidade' : 'todas as unidades'), 'Vendas por cliente');
  $d->capa([$T['clientes'] . ($T['clientes'] === 1 ? ' cliente' : ' clientes'), $T['atend'] . ($T['atend'] === 1 ? ' atendimento' : ' atendimentos')], $T['atend'] ? 'Cada pacote e cada produto vendido, com o nome do cliente.' : 'Nenhuma venda de pacote ou de produto neste período.');
  $d->kpis([['Total vendido', finBrl($T['total']), 'pacotes e produtos', PdfBonito::CORAL], ['Pacotes', (string)$T['pacN'], finBrl($T['pacV']), PdfBonito::VERDE],
    ['Produtos', (string)$T['prodN'], finBrl($T['prodV']), PdfBonito::MOSTARDA], ['Descontos', finBrl($T['desconto']), $T['aReceber'] > 0.004 ? 'a receber ' . finBrl($T['aReceber']) : 'nada a receber', PdfBonito::PIX]]);
  if ($R['linhas']) {
    $d->secao('As vendas', 'Cada atendimento: o cliente, o pacote, o produto, o total e como foi pago.');
    $T2 = []; foreach ($R['linhas'] as $x) {
      $pac = implode(' + ', array_map(fn($p) => mb_substr($p['n'], 0, 26) . ' ' . finBrl($p['v']), $x['pacotes'])); $prod = implode(' + ', array_map(fn($p) => ($p['q'] > 1 ? $p['q'] . '× ' : '') . mb_substr($p['n'], 0, 22) . ' ' . finBrl($p['v']), $x['produtos']));
      $T2[] = [substr($x['quando'], 8, 2) . '/' . substr($x['quando'], 5, 2) . ' ' . substr($x['quando'], 11, 5), mb_substr($x['resp'] !== '' ? $x['resp'] : $x['cri'], 0, 24) . ($x['resp'] !== '' && $x['cri'] !== '' ? ' · ' . mb_substr($x['cri'], 0, 14) : ''), $pac !== '' ? $pac : '—', $prod !== '' ? $prod : '—', ['t' => finBrl($x['total']), 'b' => true],
        $x['formas'] ? implode(' + ', array_map(fn($f) => $f['n'], $x['formas'])) : ($x['aReceber'] > 0.004 ? ['t' => 'a receber', 'cor' => PdfBonito::VERM] : '—')]; }
    $d->tabela([['Quando', .1, 'E'], ['Cliente', .24, 'E'], ['Pacote', .23, 'E'], ['Produto', .21, 'E'], ['Total', .1, 'D'], ['Pago', .12, 'E']], $T2, ['Total', $T['clientes'] . ' clientes', finBrl($T['pacV']), finBrl($T['prodV']), finBrl($T['total']), '']);
  }
  return $d->pdf();
}
