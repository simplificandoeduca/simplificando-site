<?php
/* =====================================================================
   Play Space Finanças 2.6 (Play Space 1.65): os relatórios bonitos do caixa e das festas (o dos freelancers está em freelas.php)
   - O caixa (de um dia ou de um período): o total, cada forma (crédito, débito, Pix, dinheiro), a Rede (taxas e líquido, do PDF anexado),
     a conferência dia a dia (bateu / não bateu / falta a Rede), os consertos, o crédito em detalhe (taxa média, vendas, ticket médio e em
     quantas vezes as festas foram parceladas) e o que cai na conta nos próximos dias.
   - As festas (do mês): o valor bruto, os custos, o lucro real, para onde vai o dinheiro, como as festas foram pagas (e em quantas vezes
     no crédito) e cada festa numa linha.
   ===================================================================== */

/* ---------- o caixa: os números de um período ---------- */
function cxPeriodo(string $de, string $ate): array {
  $S = cxSistema($de, $ate); $R = cxRede($de, $ate); $A = cxAjustes($de, $ate);
  $dias = []; for ($x = $de; $x <= $ate; $x = date('Y-m-d', strtotime($x . ' 12:00:00 +1 day'))) $dias[] = $x;
  $T = ['total' => 0.0, 'dinheiro' => 0.0, 'outras' => 0.0, 'sis' => ['credito' => 0.0, 'debito' => 0.0, 'pix' => 0.0], 'rede' => ['credito' => 0.0, 'debito' => 0.0, 'pix' => 0.0], 'temRede' => ['credito' => false, 'debito' => false, 'pix' => false],
    'taxa' => ['credito' => 0.0, 'debito' => 0.0, 'pix' => 0.0], 'brutoTaxa' => ['credito' => 0.0, 'debito' => 0.0, 'pix' => 0.0], 'liquido' => ['credito' => 0.0, 'debito' => 0.0, 'pix' => 0.0], 'qtde' => ['credito' => 0, 'debito' => 0, 'pix' => 0],
    'sit' => ['ok' => 0, 'dif' => 0, 'falta' => 0], 'consertos' => [], 'dif' => []];
  $porDia = []; $porUni = [];
  foreach ($dias as $d) {
    $porDia[$d] = ['credito' => 0.0, 'debito' => 0.0, 'pix' => 0.0, 'dinheiro' => 0.0];
    foreach (cxUnidadesDoDia($S, $R, $d) as $u) {
      $U = cxUnidade($S, $R, $d, $u, $A); $tem = abs($U['total']) > 0.004 || $U['rede'] !== null || $U['ajustes'];
      if (!$tem) continue;
      $porUni[$u] = $porUni[$u] ?? ['total' => 0.0, 'credito' => 0.0, 'debito' => 0.0, 'pix' => 0.0, 'dinheiro' => 0.0, 'taxa' => 0.0, 'ok' => 0, 'dif' => 0, 'falta' => 0];
      $T['total'] = round($T['total'] + $U['total'], 2); $T['dinheiro'] = round($T['dinheiro'] + $U['dinheiro'], 2); $T['outras'] = round($T['outras'] + $U['outras'], 2);
      $porDia[$d]['dinheiro'] = round($porDia[$d]['dinheiro'] + $U['dinheiro'], 2); $porUni[$u]['total'] = round($porUni[$u]['total'] + $U['total'], 2); $porUni[$u]['dinheiro'] = round($porUni[$u]['dinheiro'] + $U['dinheiro'], 2);
      foreach ($U['formas'] as $F) { $f = $F['f'];
        $T['sis'][$f] = round($T['sis'][$f] + $F['sis'], 2); $porDia[$d][$f] = round($porDia[$d][$f] + $F['sis'], 2); $porUni[$u][$f] = round($porUni[$u][$f] + $F['sis'], 2);
        if ($F['rede'] !== null) { $T['rede'][$f] = round($T['rede'][$f] + $F['rede'], 2); $T['temRede'][$f] = true; }
        if ($F['taxa'] !== null) { $T['taxa'][$f] = round($T['taxa'][$f] + $F['taxa'], 2); $T['brutoTaxa'][$f] = round($T['brutoTaxa'][$f] + (float)$F['rede'], 2); $T['liquido'][$f] = round($T['liquido'][$f] + (float)$F['liquido'], 2); $porUni[$u]['taxa'] = round($porUni[$u]['taxa'] + $F['taxa'], 2); }
        if ($F['qtde'] !== null) $T['qtde'][$f] += (int)$F['qtde'];
        if ($F['sit'] === 'dif') $T['dif'][] = ['dia' => $d, 'u' => $u, 'f' => $F['nome'], 'sis' => $F['sis'], 'rede' => $F['rede'], 'dif' => $F['dif']];
      }
      if (isset($T['sit'][$U['sit']])) { $T['sit'][$U['sit']]++; $porUni[$u][$U['sit']]++; }
      foreach ($U['ajustes'] as $aj) $T['consertos'][] = $aj + ['dia' => $d, 'u' => $u];
      $linhas[] = ['dia' => $d, 'U' => $U];
    }
  }
  /* em quantas vezes no crédito (as vendas com as vezes guardadas: as festas) */
  $parc = [];
  $temParc = in_array('parcelas', array_column(db()->query('PRAGMA table_info(vendas)')->fetchAll(), 'name'), true);
  if ($temParc) { $q = db()->prepare("SELECT v.parcelas n, COUNT(DISTINCT v.id) q, COALESCE(SUM(p.valor), 0) t FROM vendas v JOIN pagamentos p ON p.venda_id = v.id AND p.forma = 'credito'
      WHERE p.quando >= ? AND p.quando < ? AND v.parcelas > 0 GROUP BY v.parcelas ORDER BY v.parcelas"); $q->execute([$de . ' 00:00:00', date('Y-m-d', strtotime($ate . ' 12:00:00 +1 day')) . ' 00:00:00']);
    foreach ($q->fetchAll() as $l) $parc[] = ['n' => (int)$l['n'], 'q' => (int)$l['q'], 't' => round((float)$l['t'], 2)]; }
  return ['de' => $de, 'ate' => $ate, 'dias' => $dias, 'T' => $T, 'porDia' => $porDia, 'porUni' => $porUni, 'linhas' => $linhas ?? [], 'parc' => $parc];
}
function cxRelPdf(array $in): string {
  $de = cxDiaOk($in['de'] ?? cxHoje()); $ate = cxDiaOk($in['ate'] ?? $de); if ($ate < $de) [$de, $ate] = [$ate, $de];
  if ((strtotime($ate) - strtotime($de)) / 86400 > 92) falha('Escolha até 3 meses de cada vez.');
  $P = cxPeriodo($de, $ate); $T = $P['T']; $um = $de === $ate; $nomeU = []; foreach (unidades() as $u) $nomeU[(int)$u['id']] = (string)$u['nome'];
  $per = $um ? ucfirst(finDiaLongo($de)) : finDataBR($de) . ' a ' . finDataBR($ate);
  $conf = $T['sit']['ok'] + $T['sit']['dif'] + $T['sit']['falta'];
  $fala = !$conf ? 'Nenhum movimento no caixa neste período.' : ($T['sit']['dif'] ? ($T['sit']['dif'] === 1 ? '1 conferência não bateu com a Rede: veja abaixo.' : $T['sit']['dif'] . ' conferências não bateram com a Rede: veja abaixo.')
    : ($T['sit']['falta'] ? 'Bateu tudo o que foi conferido. Falta lançar a Rede em ' . $T['sit']['falta'] . ($T['sit']['falta'] === 1 ? ' caixa.' : ' caixas.') : 'O caixa bateu com a Rede em tudo!'));
  $d = new PdfBonito($um ? 'Caixa do dia' : 'Relatório do caixa', $per . ' · todas as unidades', $um ? 'Caixa do dia' : 'Relatório do caixa');
  $chips = $um ? [finDataBR($de)] : [count($P['dias']) . ' dias']; $chips[] = count($P['porUni']) . (count($P['porUni']) === 1 ? ' unidade' : ' unidades');
  $d->capa($chips, $fala);
  $rede = array_sum($T['rede']); $taxa = array_sum($T['taxa']); $brT = array_sum($T['brutoTaxa']); $liq = array_sum($T['liquido']); $cartao = array_sum($T['sis']);
  $d->kpis([['Total do caixa', finBrl($T['total']), 'crédito, débito, Pix e dinheiro', PdfBonito::CORAL],
    ['Crédito', finBrl($T['sis']['credito']), PdfBonito::pct($T['sis']['credito'], $T['total']) . ' do caixa', PdfBonito::CORAL],
    ['Débito', finBrl($T['sis']['debito']), PdfBonito::pct($T['sis']['debito'], $T['total']) . ' do caixa', PdfBonito::MOSTARDA],
    ['Pix', finBrl($T['sis']['pix']), PdfBonito::pct($T['sis']['pix'], $T['total']) . ' do caixa', PdfBonito::PIX],
    ['Taxas da Rede', finBrl($taxa), $brT > 0 ? 'taxa média ' . PdfBonito::pct($taxa, $brT, 2) : 'anexe o PDF da Rede', PdfBonito::VERM],
    ['Líquido (Rede)', $brT > 0 ? finBrl($liq) : '–', $brT > 0 ? 'de ' . finBrl($brT) . ' com a taxa' : 'sem o PDF da Rede', PdfBonito::VERDE],
    ['Conferência', $T['sit']['ok'] . ' de ' . $conf, $T['sit']['dif'] ? $T['sit']['dif'] . ' não bateram' : ($T['sit']['falta'] ? $T['sit']['falta'] . ' sem a Rede' : 'tudo bateu'), $T['sit']['dif'] ? PdfBonito::VERM : PdfBonito::VERDE],
    ['Dinheiro', finBrl($T['dinheiro']), 'não passa pela Rede', PdfBonito::CINZA]]);
  if ($T['dif']) $d->aviso('Não bateu: ' . implode('; ', array_map(fn($x) => substr($x['dia'], 8, 2) . '/' . substr($x['dia'], 5, 2) . ' ' . ($nomeU[$x['u']] ?? '') . ' · ' . $x['f'] . ' (Rede ' . finBrl(abs($x['dif'])) . ($x['dif'] < 0 ? ' a menos' : ' a mais') . ')', array_slice($T['dif'], 0, 6))) . (count($T['dif']) > 6 ? '; e mais ' . (count($T['dif']) - 6) . '.' : '.'), PdfBonito::VERM2, '9A2E1C');
  $d->secao('Como o cliente pagou', 'O que passou no caixa em cada forma de pagamento' . ($um ? ' no dia.' : ' no período.'));
  $y = $d->y; $h = $d->rosca($d->M, $y, 60, array_map(fn($f) => [PdfBonito::FORMAS[$f][0], $f === 'dinheiro' ? $T['dinheiro'] : $T['sis'][$f], PdfBonito::FORMAS[$f][1]], ['credito', 'debito', 'pix', 'dinheiro']), PdfBonito::curto($T['total']), $um ? 'no dia' : 'no período', 210);
  $d->y = $y + $h + 14;
  if (!$um) {
    $cats = array_map(fn($x) => substr($x, 8, 2), $P['dias']); $ser = [];
    foreach (['credito', 'debito', 'pix', 'dinheiro'] as $f) $ser[] = [PdfBonito::FORMAS[$f][0], PdfBonito::FORMAS[$f][1], array_map(fn($x) => $P['porDia'][$x][$f], $P['dias'])];
    $d->secao('Dia a dia', 'Crédito, débito, Pix e dinheiro de cada dia, empilhados.', 220);
    $d->barras(150, $cats, $ser, true, 'em reais');
  }
  if (count($P['porUni']) > 1) {
    $d->secao('Cada unidade', 'O total do caixa de cada unidade e o quanto foi crédito.', 30 + 18 * count($P['porUni']));
    $d->barrasH(array_map(fn($u, $x) => [$nomeU[$u] ?? 'Sem unidade', $x['total'], PdfBonito::CORAL, finBrl($x['total']) . ' · crédito ' . PdfBonito::pct($x['credito'], $x['total'], 0)], array_keys($P['porUni']), $P['porUni']), 140);
  }
  /* o crédito em detalhe */
  $c = $T['sis']['credito']; $cq = $T['qtde']['credito'];
  $d->secao('O crédito', 'O que a Rede descontou no crédito (do PDF anexado), as vendas e em quantas vezes as festas foram parceladas.', 170);
  $d->kpis([['Crédito no caixa', finBrl($c), PdfBonito::pct($c, $T['total']) . ' do caixa', PdfBonito::CORAL], ['Taxa do crédito', $T['brutoTaxa']['credito'] > 0 ? finBrl($T['taxa']['credito']) : '–', $T['brutoTaxa']['credito'] > 0 ? 'média ' . PdfBonito::pct($T['taxa']['credito'], $T['brutoTaxa']['credito'], 2) : 'sem o PDF da Rede', PdfBonito::VERM],
    ['Líquido do crédito', $T['brutoTaxa']['credito'] > 0 ? finBrl($T['liquido']['credito']) : '–', 'cai em 2 dias úteis', PdfBonito::VERDE], ['Vendas no crédito', $cq ? (string)$cq : '–', $cq ? 'ticket médio ' . finBrl(($T['rede']['credito'] ?: $c) / $cq) : 'a Rede diz no PDF', PdfBonito::MOSTARDA]]);
  if ($P['parc']) { $d->nota('Em quantas vezes (as festas pagas no crédito' . ($um ? ' no dia' : ' no período') . '):', PdfBonito::TINTA, 9);
    $d->barrasH(array_map(fn($x) => [$x['n'] . 'x', $x['t'], $x['n'] > 4 ? PdfBonito::VERM : PdfBonito::CORAL, finBrl($x['t']) . ' · ' . $x['q'] . ($x['q'] === 1 ? ' venda' : ' vendas') . ($x['n'] > 4 ? ' · liberado pelo gestor' : '')], $P['parc']), 60); }
  /* a Rede, forma a forma */
  $d->secao('O sistema e a Rede', 'O que o sistema registrou (com os consertos) e o que a Rede diz, com a taxa e o líquido de cada forma.', 120);
  $L = []; foreach (['credito', 'debito', 'pix'] as $f) $L[] = [PdfBonito::FORMAS[$f][0], finBrl($T['sis'][$f]), $T['temRede'][$f] ? finBrl($T['rede'][$f]) : '–', $T['temRede'][$f] ? finBrl(round($T['rede'][$f] - $T['sis'][$f], 2)) : '–', $T['brutoTaxa'][$f] > 0 ? finBrl($T['taxa'][$f]) : '–', $T['brutoTaxa'][$f] > 0 ? PdfBonito::pct($T['taxa'][$f], $T['brutoTaxa'][$f], 2) : '–', $T['brutoTaxa'][$f] > 0 ? finBrl($T['liquido'][$f]) : '–', $T['qtde'][$f] ? (string)$T['qtde'][$f] : '–'];
  $d->tabela([['Forma', .14, 'E'], ['Sistema', .14, 'D'], ['Rede', .14, 'D'], ['Diferença', .13, 'D'], ['Taxa', .12, 'D'], ['Taxa %', .09, 'D'], ['Líquido', .14, 'D'], ['Vendas', .1, 'D']], $L,
    ['Total', finBrl($cartao), $rede > 0 ? finBrl($rede) : '–', '', $brT > 0 ? finBrl($taxa) : '–', $brT > 0 ? PdfBonito::pct($taxa, $brT, 2) : '–', $brT > 0 ? finBrl($liq) : '–', array_sum($T['qtde']) ? (string)array_sum($T['qtde']) : '–']);
  /* a conferência de cada dia e de cada unidade */
  $d->secao($um ? 'A conferência de cada unidade' : 'A conferência dia a dia', 'Bateu: o sistema e a Rede são iguais nas três formas. Falta a Rede: o valor da Rede ainda não foi lançado (nem o PDF anexado).', 110);
  $sitT = ['ok' => ['bateu', PdfBonito::VERDE], 'dif' => ['não bateu', PdfBonito::VERM], 'falta' => ['falta a Rede', 'A07A1C'], '' => ['—', PdfBonito::MUDO]];
  $L = []; $fundo = [];
  foreach ($P['linhas'] as $k => $x) { $U = $x['U']; $fm = []; foreach ($U['formas'] as $F) $fm[$F['f']] = $F;
    $L[] = [substr($x['dia'], 8, 2) . '/' . substr($x['dia'], 5, 2), $nomeU[$U['u']] ?? 'Sem unidade', finBrl($fm['credito']['sis']), finBrl($fm['debito']['sis']), finBrl($fm['pix']['sis']), finBrl($U['dinheiro']), $U['rede'] !== null ? finBrl($U['rede']) : '–', ['t' => $sitT[$U['sit']][0], 'b' => true, 'cor' => $sitT[$U['sit']][1]]];
    if ($U['sit'] === 'dif') $fundo[$k] = 'FDEBE6'; }
  $d->tabela([['Dia', .09, 'E'], ['Unidade', .19, 'E'], ['Crédito', .13, 'D'], ['Débito', .12, 'D'], ['Pix', .12, 'D'], ['Dinheiro', .11, 'D'], ['Rede', .12, 'D'], ['', .12, 'D']], $L,
    ['Total', '', finBrl($T['sis']['credito']), finBrl($T['sis']['debito']), finBrl($T['sis']['pix']), finBrl($T['dinheiro']), $rede > 0 ? finBrl($rede) : '–', ''], ['fundo' => $fundo, 'tam' => 8.3]);
  if ($T['consertos']) {
    $d->secao('Os consertos do caixa', 'O que a equipe somou ou tirou do sistema para bater com a Rede, com o porquê.', 80, PdfBonito::MOSTARDA);
    $d->tabela([['Dia', .09, 'E'], ['Unidade', .17, 'E'], ['Forma', .1, 'E'], ['Valor', .12, 'D'], ['Por quê', .36, 'E'], ['Quem', .16, 'E']],
      array_map(fn($a) => [substr($a['dia'], 8, 2) . '/' . substr($a['dia'], 5, 2), $nomeU[$a['u']] ?? '', $a['nome'], ['t' => ($a['v'] < 0 ? '- ' : '+ ') . finBrl(abs($a['v'])), 'b' => true, 'cor' => $a['v'] < 0 ? PdfBonito::VERM : PdfBonito::VERDE], $a['obs'], $a['por']], $T['consertos']), null, ['tam' => 8.2]);
  }
  /* o que cai na conta nos próximos dias (quando o período chega até hoje) */
  if ($ate >= cxMenosDias(cxHoje(), 1)) {
    $Lq = cxLiquido($ate, array_keys($P['porUni']) ?: array_map(fn($u) => (int)$u['id'], array_filter(unidades(), fn($u) => $u['ativo'])));
    $d->secao('O que cai na conta nos próximos dias', 'Pix no mesmo dia, débito em 1 dia útil e crédito em 2 dias úteis (sem fim de semana nem feriado). Com o PDF da Rede, já sem a taxa.', 120, PdfBonito::VERDE);
    $d->tabela([['Dia', .3, 'E'], ['Cai na conta', .25, 'D'], ['', .45, 'E']], array_map(fn($x) => [ucfirst(finDiaLongo($x['dia'])), $x['util'] ? ['t' => finBrl($x['liquido']), 'b' => true] : '—', $x['util'] ? ($x['estimado'] ? 'parte sem a taxa (sem o PDF da Rede)' : '') : 'não é dia útil'], $Lq['proximos']), null, ['tam' => 8.4]);
  }
  return $d->pdf();
}

/* ---------- as festas do mês ---------- */
function fsPdf(array $in): string {
  $R = fsListar($in); $uni = isset($in['u']) && $in['u'] !== '' && $in['u'] !== 'todas' ? (int)$in['u'] : null;
  $nomeU = []; foreach ($R['nomes'] as $u) $nomeU[(int)$u['id']] = (string)$u['nome'];
  $L = array_values(array_filter($R['festas'], fn($f) => $uni === null || (int)$f['u'] === $uni));
  $T = ['bruto' => 0.0, 'pago' => 0.0, 'buffet' => 0.0, 'salgados' => 0.0, 'papelaria' => 0.0, 'gasto' => 0.0, 'custos' => 0.0, 'lucro' => 0.0, 'kids' => 0, 'vieram' => 0, 'faltam' => 0];
  $formas = []; $parc = [];
  foreach ($L as $f) { foreach (['bruto', 'pago', 'gasto', 'custos', 'lucro'] as $k) $T[$k] = round($T[$k] + (float)$f[$k], 2); foreach (['buffet', 'salgados', 'papelaria'] as $k) $T[$k] = round($T[$k] + (float)($f[$k] ?? 0), 2);
    $T['kids'] += $f['contratadas'] + $f['extra']; $T['vieram'] += $f['vieram']; if ($f['falta']) $T['faltam']++;
    foreach ($f['formas'] as $p) { $formas[$p['f']] = round(($formas[$p['f']] ?? 0) + $p['v'], 2); if ($p['f'] === 'credito') { $n = (int)($f['parcSis'] ?: ($f['parcelas'] ?? 0)); $k = $n ?: 0; $parc[$k] = $parc[$k] ?? ['q' => 0, 't' => 0.0]; $parc[$k]['q']++; $parc[$k]['t'] = round($parc[$k]['t'] + $p['v'], 2); } } }
  ksort($parc); $n = count($L);
  $d = new PdfBonito('Festas', finMesNome($R['mes']) . ' · ' . ($uni ? $nomeU[$uni] ?? 'unidade' : 'todas as unidades'), 'Festas');
  $fala = !$n ? 'Nenhuma festa neste mês.' : ($T['faltam'] ? 'Faltam os custos de ' . $T['faltam'] . ($T['faltam'] === 1 ? ' festa' : ' festas') . ' para o lucro real ficar certinho.' : 'Lucro real do mês: ' . finBrl($T['lucro']) . ' (margem ' . PdfBonito::pct($T['lucro'], $T['bruto'], 0) . ').');
  /* 4.6 (1.96): os blocos coloridos (a opção 3 que o dono escolheu) */
  $d->capaBlocos($fala); $p = $d->p; $M = $d->M; $W = $d->W; $g = 8; $y = $d->y;
  $FALTA = $T['faltam'] ? $T['faltam'] . ($T['faltam'] === 1 ? ' festa sem os custos lançados' : ' festas sem os custos lançados') : '';
  $rec = max(0, $T['bruto'] - $T['pago']); $neg = $T['lucro'] < -0.004; $temB = $T['bruto'] > 0.004;
  /* o lucro real (o bloco coral grande); ao lado, o valor bruto (amarelo) e os custos (verde) */
  $wl = ($W - $g) / 2; $hh = 104; $hl = 2 * $hh + $g; $xr = $M + $wl + $g; $cl = 'FFE4DB';
  $p->ret($M, $y, $wl, $hl, $neg ? PdfBonito::VERM : PdfBonito::CORAL, 16);
  $d->rotulo($M + 15, $y + 25, 'Lucro real do mês', 'FFD9CF');
  $d->valor($M + 15, $y + 60, finBrl($T['lucro']), 32, $wl - 30, 'FFFFFF');
  $p->texto($M + 15, $y + 78, $p->corta($n ? 'de ' . finBrl($T['bruto']) . ' vendidos nas festas' : 'nenhuma festa neste mês', $wl - 30, 9), 9, false, $cl);
  $bw = $wl - 30; $mg = $temB ? max(0, min(1, $T['lucro'] / $T['bruto'])) : 0;
  $p->ret($M + 15, $y + 94, $bw, 10, 'EB8A75', 5); if ($mg > 0) $p->ret($M + 15, $y + 94, max(10, $bw * $mg), 10, 'FFFFFF', 5);
  $p->texto($M + 15, $y + 120, $temB ? PdfBonito::pct($T['lucro'], $T['bruto']) . ' de margem' : 'sem vendas de festa', 8, false, $cl);
  if ($temB) $p->texto($M + $wl - 15, $y + 120, 'custos ' . PdfBonito::pct($T['custos'], $T['bruto']), 8, false, $cl, 'D');
  $p->linha($M + 15, $y + 136, $M + $wl - 15, $y + 136, 'EB8A75', 0.8);
  $x2 = $M + 15 + $bw / 2;
  $p->texto($M + 15, $y + 160, 'Lucro por festa', 7.8, false, $cl); $d->valor($M + 15, $y + 179, $n ? finBrl($T['lucro'] / $n) : '–', 13, $bw / 2 - 8, 'FFFFFF');
  $p->texto($x2, $y + 160, 'Ticket médio', 7.8, false, $cl); $d->valor($x2, $y + 179, $n ? finBrl($T['bruto'] / $n) : '–', 13, $bw / 2 - 4, 'FFFFFF');
  $p->ret($xr, $y, $wl, $hh, PdfBonito::MOSTARDA, 16);
  $d->rotulo($xr + 15, $y + 25, 'Valor bruto', '7A5A12'); $d->valor($xr + 15, $y + 56, finBrl($T['bruto']), 21, $wl - 30, PdfBonito::TINTA);
  $p->texto($xr + 15, $y + 74, $p->corta($n . ($n === 1 ? ' festa' : ' festas') . ' · ' . $T['kids'] . ' crianças combinadas', $wl - 30, 8.2), 8.2, false, '6B4F10');
  $y2 = $y + $hh + $g; $p->ret($xr, $y2, $wl, $hh, 'DCE8DA', 16);
  $d->rotulo($xr + 15, $y2 + 25, 'Custos', '2F6B47'); $d->valor($xr + 15, $y2 + 56, finBrl($T['custos']), 21, $wl - 30, PdfBonito::TINTA);
  $p->texto($xr + 15, $y2 + 74, $p->corta('buffet, salgados, papelaria e adiantamento', $wl - 30, 8.2), 8.2, false, '2F6B47');
  if ($FALTA !== '') $p->texto($xr + 15, $y2 + 88, $p->corta($FALTA, $wl - 30, 8.2), 8.2, true, '7A5A12');
  /* festas, crianças e a receber */
  $y += $hl + $g; $w3 = ($W - 2 * $g) / 3; $hs = 64;
  foreach ([['Festas', (string)$n, $FALTA !== '' ? $FALTA : ($n ? 'todas com os custos lançados' : 'nenhuma neste mês'), PdfBonito::TINTA], ['Crianças', (string)$T['kids'], 'vieram ' . $T['vieram'] . ' convidados', PdfBonito::TINTA],
    ['A receber', finBrl($rec), 'recebido ' . finBrl($T['pago']), $rec > 0.004 ? PdfBonito::VERM : PdfBonito::TINTA]] as $i => [$r, $v, $sb, $c]) {
    $x = $M + $i * ($w3 + $g); $p->ret($x, $y, $w3, $hs, 'FFFFFF', 16, 'EFE6DA');
    $d->rotulo($x + 14, $y + 21, $r, PdfBonito::MUDO); $d->valor($x + 14, $y + 42, $v, 17, $w3 - 28, $c); $p->texto($x + 14, $y + 55, $p->corta($sb, $w3 - 28, 7.8), 7.8, false, PdfBonito::MUDO);
  }
  $y += $hs + $g;
  /* para onde vai o dinheiro: a barra colorida e as etiquetas */
  if ($n) {
    $itens = [['Buffet', $T['buffet'], PdfBonito::MOSTARDA], ['Salgados', $T['salgados'], PdfBonito::PESSEGO], ['Papelaria', $T['papelaria'], 'B9A6E0'], ['Gasto do adiantamento', $T['gasto'], PdfBonito::CINZA], ['Lucro real', max(0, $T['lucro']), PdfBonito::VERDE]];
    $tot = 0.0; foreach ($itens as $it) $tot += max(0, (float)$it[1]);
    $ix = $M + 15; $lim = $M + $W - 15; $ch = []; $cx = $ix; $cy = 0; $tc = 7.8;
    foreach ($itens as $it) { $t2 = finBrl($it[1]); $t3 = ' · ' . PdfBonito::pct(max(0, (float)$it[1]), $tot);
      $w = 17 + $p->larg($it[0], $tc) + 5 + $p->larg($t2, $tc, true) + $p->larg($t3, $tc) + 9; if ($cx + $w > $lim && $cx > $ix) { $cx = $ix; $cy += 21; }
      $ch[] = [$cx, $cy, $w, $it, $t2, $t3]; $cx += $w + 6; }
    $ht = 76 + $cy + 17 + 14; $d->y = $y; $d->cabe($ht); $y = $d->y;
    $p->ret($M, $y, $W, $ht, 'FFFFFF', 16, 'EFE6DA');
    $p->texto($M + 15, $y + 24, 'Para onde vai o dinheiro das festas', 12.5, true, PdfBonito::TINTA);
    $p->texto($M + 15, $y + 39, $p->corta($neg ? 'Os custos passaram o valor das festas em ' . finBrl(-$T['lucro']) . '.' : 'Dos ' . finBrl($T['bruto']) . ' das festas, ' . finBrl($T['lucro']) . ' ficam de lucro real.', $W - 30, 8.6), 8.6, false, PdfBonito::MUDO);
    $seg = array_values(array_filter($itens, fn($it) => (float)$it[1] > 0.004)); $by = $y + 50; $bw = $W - 30; $gp = 3;
    if ($seg && $tot > 0.004) { $av = $bw - $gp * (count($seg) - 1); $bx = $ix;
      foreach ($seg as $k => $it) { $w = $k === count($seg) - 1 ? $ix + $bw - $bx : max(3, $av * $it[1] / $tot); $p->ret($bx, $by, $w, 22, $it[2], 7);
        if ($it[0] === 'Lucro real' && $w > 96) $p->texto($bx + 10, $by + 14.6, PdfBonito::pct($it[1], $tot) . ' lucro real', 8.4, true, 'FFFFFF');
        $bx += $w + $gp; } }
    else $p->ret($ix, $by, $bw, 22, PdfBonito::GRADE, 7);
    foreach ($ch as [$cx, $cy, $w, $it, $t2, $t3]) { $yy = $by + 32 + $cy; $p->ret($cx, $yy, $w, 17, PdfBonito::CREME, 8.5); $p->circulo($cx + 10, $yy + 8.5, 3.2, $it[2]);
      $p->texto($cx + 17, $yy + 11.6, $it[0], $tc, false, PdfBonito::TINTA); $xx = $cx + 17 + $p->larg($it[0], $tc) + 5; $p->texto($xx, $yy + 11.6, $t2, $tc, true, PdfBonito::TINTA);
      $p->texto($xx + $p->larg($t2, $tc, true), $yy + 11.6, $t3, $tc, false, PdfBonito::MUDO); }
    $y += $ht + $g;
  }
  /* cada festa num cartão: a data num círculo colorido, o aniversariante, a unidade, as crianças e o pagamento; bruto, custos e lucro */
  $d->y = $y + 4; $d->cabe(36 + 57);
  $p->texto($M + 2, $d->y + 14, 'Cada festa', 13, true, PdfBonito::TINTA);
  $p->texto($M + 2, $d->y + 28, $p->corta('Lucro real = valor bruto − buffet − salgados − papelaria − gasto do adiantamento (adiantamento − retornado).', $W - 4, 7.8), 7.8, false, PdfBonito::MUDO);
  $d->y += 38;
  $MES = ['', 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ']; $CORES = [[PdfBonito::PESSEGO, '7A2E1C'], [PdfBonito::MOSTARDA, '6B4F10'], ['DCE8DA', '2F6B47'], ['E4DCF5', '4B3A78']];
  $R0 = $M + $W - 14; $cols = [[$R0 - 160, 'Bruto'], [$R0 - 80, 'Custos'], [$R0, 'Lucro real']];
  foreach ($L as $k => $f) {
    $hc = 50; $d->cabe($hc + 7); $y = $d->y; $falta = !empty($f['falta']);
    $p->ret($M, $y, $W, $hc, $falta ? 'FFFBF1' : 'FFFFFF', 16, $falta ? 'F3DFB0' : 'EFE6DA');
    [$cf, $ct] = $CORES[$k % 4]; $ccx = $M + 31; $ccy = $y + 25; $p->circulo($ccx, $ccy, 19, $cf);
    $p->texto($ccx, $ccy + 2.5, substr($f['data'], 8, 2), 13.5, true, $ct, 'C'); $p->textoEsp($ccx, $ccy + 12, $MES[(int)substr($f['data'], 5, 2)] ?? '', 6, true, $ct, 0.6, 'C');
    $pg = implode(' + ', array_map(fn($q) => $q['nome'] . ($q['f'] === 'credito' && ($f['parcSis'] || $f['parcelas']) ? ' ' . ($f['parcSis'] ?: $f['parcelas']) . 'x' : ''), $f['formas'])) ?: 'sem pagamento';
    $wn = $cols[0][0] - 74 - ($M + 60);
    $p->texto($M + 60, $y + 22, $p->corta(mb_strtoupper($f['aniv'] ?: 'Festa') . ((string)($f['tema'] ?? '') !== '' ? ' · ' . $f['tema'] : ''), $wn, 10.5, true), 10.5, true, PdfBonito::TINTA);
    $p->texto($M + 60, $y + 35, $p->corta(implode(' · ', array_filter([$nomeU[(int)$f['u']] ?? '', ($f['contratadas'] + $f['extra']) . ' crianças', $pg], fn($v) => $v !== '')), $wn, 7.6), 7.6, false, PdfBonito::MUDO);
    foreach ($cols as [$xc, $r]) $d->rotulo($xc, $y + 20, $r === 'Lucro real' ? 'Lucro' : $r, PdfBonito::MUDO, 6.4, 'D');
    $p->texto($cols[0][0], $y + 34, finBrl($f['bruto']), 9.6, true, PdfBonito::TINTA, 'D');
    if ($falta) { $t = 'falta lançar'; $w = $p->larg($t, 7.4, true) + 12; $p->ret($cols[1][0] - $w, $y + 25, $w, 13, 'FFF1D2', 6.5); $p->texto($cols[1][0] - 6, $y + 34.2, $t, 7.4, true, '7A5A12', 'D'); }
    else $p->texto($cols[1][0], $y + 34, finBrl($f['custos']), 9.6, true, PdfBonito::TINTA, 'D');
    $p->texto($cols[2][0], $y + 34, finBrl($f['lucro']), 9.6, true, $f['lucro'] < -0.004 ? PdfBonito::VERM : PdfBonito::VERDE, 'D');
    $d->y += $hc + 7;
  }
  if ($n) { $d->cabe(36); $y = $d->y; $p->ret($M, $y, $W, 30, 'F1E6D7', 15);
    $p->texto($M + 14, $y + 19, 'Total · ' . $n . ($n === 1 ? ' festa' : ' festas') . ' · ' . $T['kids'] . ' crianças', 9, true, PdfBonito::TINTA);
    $p->texto($cols[0][0], $y + 19, finBrl($T['bruto']), 9.6, true, PdfBonito::TINTA, 'D'); $p->texto($cols[1][0], $y + 19, finBrl($T['custos']), 9.6, true, PdfBonito::TINTA, 'D');
    $p->texto($cols[2][0], $y + 19, finBrl($T['lucro']), 9.6, true, $neg ? PdfBonito::VERM : PdfBonito::VERDE, 'D'); $d->y += 40; }
  else $d->nota('Nenhuma festa em ' . mb_strtolower(finMesNome($R['mes'])) . '.');
  /* o lucro de cada festa e como foram pagas, em cartões brancos */
  if ($n) {
    $d->secao('O lucro de cada festa', 'O valor bruto e o lucro real de cada festa do mês, pela data da festa.', 250);
    $d->cartao(function () use ($d, $L) { $d->barras(150, array_map(fn($f) => substr($f['data'], 8, 2) . '/' . substr($f['data'], 5, 2), $L), [['Valor bruto', PdfBonito::PESSEGO, array_map(fn($f) => (float)$f['bruto'], $L)], ['Lucro real', PdfBonito::VERDE, array_map(fn($f) => max(0, (float)$f['lucro']), $L)]], false, 'em reais'); });
    $d->secao('Como as festas foram pagas', 'As formas de pagamento das festas e, no crédito, em quantas vezes.', 190 + 18 * count($parc));
    $d->cartao(function () use ($d, $formas, $parc) { $y = $d->y; $h = $d->rosca($d->M, $y, 48, array_map(fn($f) => [PdfBonito::FORMAS[$f][0], $formas[$f] ?? 0, PdfBonito::FORMAS[$f][1]], ['credito', 'debito', 'pix', 'dinheiro']), PdfBonito::curto(array_sum($formas)), 'recebido', 200); $d->y = $y + $h + 10;
      if ($parc) $d->barrasH(array_map(fn($k, $x) => [$k ? $k . 'x no crédito' : 'Crédito (vezes?)', $x['t'], $k > 4 ? PdfBonito::VERM : PdfBonito::CORAL, finBrl($x['t']) . ' · ' . $x['q'] . ($x['q'] === 1 ? ' festa' : ' festas') . ($k > 4 ? ' · liberado pelo gestor' : '')], array_keys($parc), $parc), 120); });
  }
  return $d->pdf();
}
