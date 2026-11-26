<?php
// Play Space Finanças 1.3 (Play Space 1.54): o resumo de um dia (ou de um período). finResumo: os números da tela das Finanças (o mesmo
// código de antes, agora numa função); finPdf: o resumo em PDF (feito aqui mesmo, sem biblioteca); o e-mail do dia com o PDF anexado.
// Usado pelas Finanças (api.php) e pelo Play Space (o PDF do dia do gestor e o envio sozinho no pulso).
function finResumo(array $in): array {
  $db = db();
  $de = (string)($in['de'] ?? ''); $ate = (string)($in['ate'] ?? '');
  $dataOk = fn(string $x) => (bool)preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $x, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);   // 1.49: 30/02 não existe
  if (!$dataOk($de) || !$dataOk($ate)) falha('Escolha o período (datas que existem).');
  if ($ate < $de) [$de, $ate] = [$ate, $de];
  $dias = (int)round((strtotime($ate) - strtotime($de)) / 86400) + 1; if ($dias > 800) falha('Escolha um período de até 800 dias.');
  $ini = $de . ' 00:00:00'; $fim = date('Y-m-d', strtotime($ate . ' +1 day')) . ' 00:00:00';
  // 1.53: o filtro de produto ('' = tudo · g:<tipo> · i:<tipo>|<NOME>)
  $prod = trim((string)($in['prod'] ?? ''));
  if ($prod !== '' && !preg_match('/^(g:(recreacao|pacotes|festa|produtos|carrinhos|outros)|i:(recreacao|pacotes|festa|produtos|carrinhos|outros)\|.{1,160})$/su', $prod)) $prod = '';
  // o tipo (grupo) e o nome de cada venda, como no Caixa do dia: o pacote pelo nome, o produto da loja pelo nome, a festa, o carrinho, a recreação
  $cls = function (array $r): array {
    $g = grupoDoCaixa(['s' => (string)($r['s'] ?? ''), 'pr' => (int)($r['pr'] ?? 0) > 0 ? 1 : 0, 'a' => (string)($r['a'] ?? ''), 'm' => (int)($r['m'] ?? 0), 'au' => (int)($r['au'] ?? 0), 'vo' => (string)($r['vo'] ?? '')]);
    $n = mb_strtoupper(trim((string)preg_replace('/\s+/u', ' ', (string)($r['s'] ?? ''))));
    if ($n === '') $n = $g === 'pacotes' ? 'PACOTE ' . str_pad((string)intdiv((int)($r['m'] ?? 0), 60), 2, '0', STR_PAD_LEFT) . ' HORAS' : ['recreacao' => 'RECREAÇÃO', 'produtos' => 'PRODUTO', 'carrinhos' => 'CARRINHO', 'festa' => 'FESTA'][$g] ?? 'OUTROS';
    return [$g, $n];
  };
  $passa = function (string $g, string $n) use ($prod): bool { if ($prod === '') return true; if (strpos($prod, 'g:') === 0) return substr($prod, 2) === $g; return substr($prod, 2) === $g . '|' . $n; };
  $uni = []; $U = function (int $u) use (&$uni) { if (!isset($uni[$u])) $uni[$u] = ['faturado' => 0.0, 'vendas' => 0, 'recebido' => 0.0, 'devolvido' => 0.0, 'aberto' => 0.0, 'visitas' => 0, 'criancas' => 0, 'festaVisitas' => 0, 'agora' => 0,
    'cancN' => 0, 'cancV' => 0.0, 'desfN' => 0, 'foraN' => 0, 'nfN' => 0, 'nfV' => 0.0, 'nfCN' => 0, 'nfCV' => 0.0, 'descN' => 0, 'descV' => 0.0, 'descPct' => 0.0, 'pcdN' => 0, 'pcdV' => 0.0, 'voucherN' => 0, 'pacN' => 0, 'pacV' => 0.0, 'festas' => 0]; };
  $nomeUsu = []; foreach ($db->query('SELECT id, nome FROM usuarios')->fetchAll() as $x) $nomeUsu[(int)$x['id']] = (string)$x['nome'];
  // vendas: o faturado, os grupos (recreação, pacotes, festa, produtos, carrinhos), os pacotes por nome, por dia e por atendente
  $grupos = []; $pacotes = []; $porDia = []; $atend = [];
  $q = $db->prepare("SELECT COALESCE(unidade_id, 0) u, servico s, (COALESCE(produto_id, 0) > 0) pr, alug a, minutos m, auto au, voucher vo, COUNT(*) n, SUM(valor > 0.004) nv, SUM(valor > 0.004 AND auto = 0) nr, COALESCE(SUM(valor), 0) v
    FROM vendas WHERE quando >= ? AND quando < ? AND servico != ? GROUP BY u, s, pr, a, (minutos > 0), auto, (voucher != ''), (CASE WHEN minutos > 0 THEN minutos ELSE 0 END)");
  $q->execute([$ini, $fim, SERV_IMPORTADO]);
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $v = round((float)$l['v'], 2); $nv = (int)$l['nv'];
    $uni[$u]['faturado'] = round($uni[$u]['faturado'] + $v, 2); $uni[$u]['vendas'] += (int)$l['nr']; if ((string)$l['vo'] !== '') $uni[$u]['voucherN'] += (int)$l['n'];
    if ($nv < 1 && $v <= 0.004) continue;
    $g = grupoDoCaixa(['s' => (string)$l['s'], 'pr' => (int)$l['pr'], 'a' => (string)$l['a'], 'm' => (int)$l['m'], 'au' => (int)$l['au'], 'vo' => (string)$l['vo']]);
    $k = $u . '|' . $g; if (!isset($grupos[$k])) $grupos[$k] = ['u' => $u, 'g' => $g, 'n' => 0, 'v' => 0.0]; $grupos[$k]['n'] += $nv; $grupos[$k]['v'] = round($grupos[$k]['v'] + $v, 2);
    if ($g === 'pacotes') { $nm = trim((string)$l['s']) !== '' ? mb_strtoupper(trim((string)$l['s'])) : 'PACOTE ' . str_pad((string)intdiv((int)$l['m'], 60), 2, '0', STR_PAD_LEFT) . ' HORAS';
      $uni[$u]['pacN'] += $nv; $uni[$u]['pacV'] = round($uni[$u]['pacV'] + $v, 2);
      $k = $u . '|' . $nm; if (!isset($pacotes[$k])) $pacotes[$k] = ['u' => $u, 'n' => $nm, 'h' => round((int)$l['m'] / 60, 1), 'q' => 0, 'v' => 0.0]; $pacotes[$k]['q'] += $nv; $pacotes[$k]['v'] = round($pacotes[$k]['v'] + $v, 2); } }
  /* 2.40 (Finanças 5.5): o produto que foi dentro do pacote (o nome escrito no "Alterar pacote"): o que mais sai */
  $prodPac = []; $q = $db->prepare("SELECT COALESCE(unidade_id, 0) u, prod_nome n, COUNT(*) q, COALESCE(SUM(prod_valor), 0) v FROM vendas WHERE quando >= ? AND quando < ? AND prod_nome != '' GROUP BY u, n");
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) $prodPac[] = ['u' => (int)$l['u'], 'n' => (string)$l['n'], 'q' => (int)$l['q'], 'v' => round((float)$l['v'], 2)];
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, substr(quando, 1, 10) d, COALESCE(SUM(valor), 0) v FROM vendas WHERE quando >= ? AND quando < ? AND servico != ? GROUP BY u, d ORDER BY d');
  $q->execute([$ini, $fim, SERV_IMPORTADO]); foreach ($q->fetchAll() as $l) $porDia[] = ['u' => (int)$l['u'], 'd' => (string)$l['d'], 'v' => round((float)$l['v'], 2)];
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, por, COUNT(*) n, COALESCE(SUM(valor), 0) v FROM vendas WHERE quando >= ? AND quando < ? AND servico != ? AND valor > 0.004 AND auto = 0 GROUP BY u, por');
  $q->execute([$ini, $fim, SERV_IMPORTADO]); foreach ($q->fetchAll() as $l) $atend[] = ['u' => (int)$l['u'], 'nome' => $nomeUsu[(int)$l['por']] ?? 'Sem nome', 'n' => (int)$l['n'], 'v' => round((float)$l['v'], 2)];
  // recebido: os pagamentos do período (na unidade em que o dinheiro entrou), menos o devolvido no período
  $formas = [];
  $q = $db->prepare('SELECT COALESCE(p.unidade_id, v.unidade_id, 0) u, p.forma f, COALESCE(SUM(p.valor), 0) t FROM pagamentos p JOIN vendas v ON v.id = p.venda_id WHERE p.quando >= ? AND p.quando < ? GROUP BY u, f');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $t = round((float)$l['t'], 2); $uni[$u]['recebido'] = round($uni[$u]['recebido'] + $t, 2); $formas[$u . '|' . $l['f']] = ['u' => $u, 'f' => (string)$l['f'], 'v' => round(($formas[$u . '|' . $l['f']]['v'] ?? 0) + $t, 2)]; }
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, forma f, COALESCE(SUM(valor), 0) t FROM estornos WHERE quando >= ? AND quando < ? GROUP BY u, f');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $t = round((float)$l['t'], 2); $uni[$u]['devolvido'] = round($uni[$u]['devolvido'] + $t, 2); $uni[$u]['recebido'] = round($uni[$u]['recebido'] - $t, 2);
    $formas[$u . '|' . $l['f']] = ['u' => $u, 'f' => (string)$l['f'], 'v' => round(($formas[$u . '|' . $l['f']]['v'] ?? 0) - $t, 2)]; }
  // 1.49 (auditoria): a venda cancelada em outro dia leva junto o pagamento dela (que é apagado) e deixa o estorno com o dia em que tinha sido
  // paga: esse dinheiro volta ao Recebido daquele dia (senão sairia duas vezes: o pagamento apagado e o estorno)
  $q = $db->prepare('SELECT COALESCE(pago_uni, unidade_id, 0) u, forma f, COALESCE(SUM(valor), 0) t FROM estornos WHERE pago_em >= ? AND pago_em < ? GROUP BY u, f');   // 1.91: na unidade em que tinha entrado
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $t = round((float)$l['t'], 2); $uni[$u]['recebido'] = round($uni[$u]['recebido'] + $t, 2);
    $formas[$u . '|' . $l['f']] = ['u' => $u, 'f' => (string)$l['f'], 'v' => round(($formas[$u . '|' . $l['f']]['v'] ?? 0) + $t, 2)]; }
  // em aberto: o vendido no período que ainda não foi pago
  $q = $db->prepare('SELECT COALESCE(v.unidade_id, 0) u, COALESCE(SUM(v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0)), 0) f FROM vendas v
    WHERE v.quando >= ? AND v.quando < ? AND v.servico != ? AND v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) > 0.004 GROUP BY u');
  $q->execute([$ini, $fim, SERV_IMPORTADO]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $uni[$u]['aberto'] = round((float)$l['f'], 2); }
  // as crianças: as visitas que terminaram no período (e quantas crianças diferentes), as que estão no espaço agora e as festas marcadas
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, COUNT(*) n, COUNT(DISTINCT crianca_id) c, SUM(festa_id IS NOT NULL) f FROM visitas WHERE saiu >= ? AND saiu < ? GROUP BY u');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $uni[$u]['visitas'] = (int)$l['n']; $uni[$u]['criancas'] = (int)$l['c']; $uni[$u]['festaVisitas'] = (int)$l['f']; }
  $q = $db->prepare('SELECT COUNT(DISTINCT crianca_id) FROM visitas WHERE saiu >= ? AND saiu < ?'); $q->execute([$ini, $fim]); $criancasTotal = (int)$q->fetchColumn();
  foreach ($db->query('SELECT COALESCE(unidade_id, 0) u, COUNT(*) n FROM criancas WHERE entrou IS NOT NULL AND ativo = 1 GROUP BY u')->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $uni[$u]['agora'] = (int)$l['n']; }
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, COUNT(*) n FROM festas WHERE data >= ? AND data <= ? GROUP BY u'); $q->execute([$de, $ate]);
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $uni[$u]['festas'] = (int)$l['n']; }
  // 1.53: cada venda do período, uma a uma: o "produto a produto" (sempre de tudo) e, com o filtro de produto, os números só daquilo
  $porItem = []; $VQ = $db->prepare('SELECT COALESCE(unidade_id, 0) u, substr(quando, 1, 10) d, servico s, COALESCE(produto_id, 0) pr, alug a, minutos m, auto au, voucher vo, valor v, COALESCE(qtd, 1) qtd, por FROM vendas WHERE quando >= ? AND quando < ? AND servico != ?');
  $VQ->execute([$ini, $fim, SERV_IMPORTADO]); $VL = $VQ->fetchAll();
  foreach ($VL as $r) { [$g, $n] = $cls($r); $v = (float)$r['v']; if ($v <= 0.004) continue; $k = (int)$r['u'] . '|' . $g . '|' . $n;
    if (!isset($porItem[$k])) $porItem[$k] = ['u' => (int)$r['u'], 'g' => $g, 'n' => $n, 'q' => 0, 'v' => 0.0]; $porItem[$k]['q'] += $g === 'produtos' ? max(1, (int)$r['qtd']) : 1; $porItem[$k]['v'] = round($porItem[$k]['v'] + $v, 2); }
  if ($prod !== '') {
    foreach ($uni as $u => $x) { foreach (['faturado', 'recebido', 'devolvido', 'aberto', 'pacV'] as $c) $uni[$u][$c] = 0.0; foreach (['vendas', 'pacN'] as $c) $uni[$u][$c] = 0; }
    $grupos = []; $pacotes = []; $porDia = []; $atend = []; $formas = []; $pdP = []; $atP = [];
    foreach ($VL as $r) { [$g, $n] = $cls($r); if (!$passa($g, $n)) continue; $u = (int)$r['u']; $U($u); $v = round((float)$r['v'], 2); $valeu = $v > 0.004;
      $uni[$u]['faturado'] = round($uni[$u]['faturado'] + $v, 2); if ($valeu && !(int)$r['au']) $uni[$u]['vendas']++;
      $pdP[$u . '|' . $r['d']] = ['u' => $u, 'd' => (string)$r['d'], 'v' => round(($pdP[$u . '|' . $r['d']]['v'] ?? 0) + $v, 2)];
      if (!$valeu) continue;
      $k = $u . '|' . $g; if (!isset($grupos[$k])) $grupos[$k] = ['u' => $u, 'g' => $g, 'n' => 0, 'v' => 0.0]; $grupos[$k]['n']++; $grupos[$k]['v'] = round($grupos[$k]['v'] + $v, 2);
      if (!(int)$r['au']) { $k = $u . '|' . (int)$r['por']; if (!isset($atP[$k])) $atP[$k] = ['u' => $u, 'nome' => $nomeUsu[(int)$r['por']] ?? 'Sem nome', 'n' => 0, 'v' => 0.0]; $atP[$k]['n']++; $atP[$k]['v'] = round($atP[$k]['v'] + $v, 2); }
      if ($g === 'pacotes') { $uni[$u]['pacN']++; $uni[$u]['pacV'] = round($uni[$u]['pacV'] + $v, 2);
        $k = $u . '|' . $n; if (!isset($pacotes[$k])) $pacotes[$k] = ['u' => $u, 'n' => $n, 'h' => round((int)$r['m'] / 60, 1), 'q' => 0, 'v' => 0.0]; $pacotes[$k]['q']++; $pacotes[$k]['v'] = round($pacotes[$k]['v'] + $v, 2); } }
    ksort($pdP); $porDia = array_values($pdP); $atend = array_values($atP);
    $soma = function (int $u, string $f, float $t) use (&$uni, &$formas, $U) { $U($u); $uni[$u]['recebido'] = round($uni[$u]['recebido'] + $t, 2); $formas[$u . '|' . $f] = ['u' => $u, 'f' => $f, 'v' => round(($formas[$u . '|' . $f]['v'] ?? 0) + $t, 2)]; };
    $q = $db->prepare('SELECT COALESCE(p.unidade_id, v.unidade_id, 0) u, p.forma f, p.valor t, v.servico s, COALESCE(v.produto_id, 0) pr, v.alug a, v.minutos m, v.auto au, v.voucher vo FROM pagamentos p JOIN vendas v ON v.id = p.venda_id WHERE p.quando >= ? AND p.quando < ?');
    $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { [$g, $n] = $cls($l); if ($passa($g, $n)) $soma((int)$l['u'], (string)$l['f'], round((float)$l['t'], 2)); }
    // o devolvido: a venda pode já ter sido apagada (cancelada): vale o tipo guardado na devolução
    $eq = 'SELECT COALESCE(e.unidade_id, 0) u, e.forma f, e.valor t, e.grupo eg, e.item it, v.id vid, v.servico s, COALESCE(v.produto_id, 0) pr, v.alug a, v.minutos m, v.auto au, v.voucher vo FROM estornos e LEFT JOIN vendas v ON v.id = e.venda_id WHERE e.';
    $clsE = fn(array $l): array => $l['vid'] ? $cls($l) : [(string)$l['eg'], mb_strtoupper(trim((string)$l['it']))];
    $q = $db->prepare($eq . 'quando >= ? AND e.quando < ?'); $q->execute([$ini, $fim]);
    foreach ($q->fetchAll() as $l) { [$g, $n] = $clsE($l); if (!$passa($g, $n)) continue; $t = round((float)$l['t'], 2); $u = (int)$l['u']; $U($u); $uni[$u]['devolvido'] = round($uni[$u]['devolvido'] + $t, 2); $soma($u, (string)$l['f'], -$t); }
    $q = $db->prepare($eq . 'pago_em >= ? AND e.pago_em < ?'); $q->execute([$ini, $fim]);
    foreach ($q->fetchAll() as $l) { [$g, $n] = $clsE($l); if ($passa($g, $n)) $soma((int)$l['u'], (string)$l['f'], round((float)$l['t'], 2)); }
    $q = $db->prepare('SELECT COALESCE(v.unidade_id, 0) u, v.servico s, COALESCE(v.produto_id, 0) pr, v.alug a, v.minutos m, v.auto au, v.voucher vo, v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) f FROM vendas v
      WHERE v.quando >= ? AND v.quando < ? AND v.servico != ? AND v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) > 0.004');
    $q->execute([$ini, $fim, SERV_IMPORTADO]); foreach ($q->fetchAll() as $l) { [$g, $n] = $cls($l); if (!$passa($g, $n)) continue; $u = (int)$l['u']; $U($u); $uni[$u]['aberto'] = round($uni[$u]['aberto'] + (float)$l['f'], 2); }
  }
  // 1.53: o catálogo (para o filtro e o produto a produto trazerem todos, mesmo o que não vendeu): os produtos da loja, os pacotes, os serviços e as festas
  $catalogo = []; $poe = function (string $g, string $n) use (&$catalogo) { $n = mb_strtoupper(trim((string)preg_replace('/\s+/u', ' ', $n))); if ($n !== '') $catalogo[$g . '|' . $n] = ['g' => $g, 'n' => $n]; };
  foreach ($db->query('SELECT nome FROM produtos WHERE ativo = 1')->fetchAll(PDO::FETCH_COLUMN) as $n) $poe('produtos', (string)$n);
  foreach ((array)(regra()['pacotes'] ?? []) as $p) if (is_array($p)) $poe('pacotes', servicoDoPacote($p) !== '' ? servicoDoPacote($p) : 'PACOTE ' . str_pad((string)(int)($p['h'] ?? 0), 2, '0', STR_PAD_LEFT) . ' HORAS');
  foreach ((array)(regra()['servicos'] ?? []) as $s) if (is_array($s) && trim((string)($s['n'] ?? '')) !== '') { [$g, $n] = $cls(['s' => (string)$s['n'], 'pr' => 0, 'a' => !empty($s['volta']) ? 'x' : '', 'm' => 0, 'au' => 0, 'vo' => '']); $poe($g, $n); }
  foreach (array_merge(array_values(FESTA_SERVICO), ['UPGRADE DE FESTA', 'ADICIONAL DE FESTA']) as $n) $poe('festa', $n);
  // os cancelamentos: quantos, quanto deixou de ser cobrado, de quem (o nome da atendente que a pessoa escreveu; sem ele, quem estava no aparelho)
  $tipos = ['' => 'Entrada cancelada', 'desfeita' => 'Entrada desfeita na hora', 'venda' => 'Venda cancelada', 'atendimento' => 'Atendimento cancelado', 'aluguel' => 'Saída do carrinho cancelada', 'excluida' => 'Saída excluída pelo gestor'];
  $canc = []; $cancTotal = 0; $cancAt = []; $cancTipo = [];
  $q = $db->prepare('SELECT id, quando, COALESCE(unidade_id, 0) u, nome, resp, valor, minutos, itens, motivo, por_nome, papel, atendente, tipo, fora, visto_por FROM cancelamentos WHERE quando >= ? AND quando < ? ORDER BY quando DESC, id DESC');
  $q->execute([$ini, $fim]);
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $conta = (string)$l['tipo'] !== 'desfeita'; $cancTotal++;   // 1.49: a entrada desfeita na hora não conta (como no painel)
    $at = trim((string)$l['atendente']) !== '' ? mb_strtoupper(trim((string)$l['atendente'])) : mb_strtoupper(primeiroNome((string)$l['por_nome'])); $tp = $tipos[(string)$l['tipo']] ?? 'Cancelamento';
    if ($conta) { $uni[$u]['cancN']++; $uni[$u]['cancV'] = round($uni[$u]['cancV'] + (float)$l['valor'], 2); if ((int)$l['fora'] === 1) $uni[$u]['foraN']++;
      $k = $u . '|' . $at; if (!isset($cancAt[$k])) $cancAt[$k] = ['u' => $u, 'nome' => $at, 'n' => 0, 'v' => 0.0, 'fora' => 0]; $cancAt[$k]['n']++; $cancAt[$k]['v'] = round($cancAt[$k]['v'] + (float)$l['valor'], 2); if ((int)$l['fora'] === 1) $cancAt[$k]['fora']++;
      $k = $u . '|' . $tp; if (!isset($cancTipo[$k])) $cancTipo[$k] = ['u' => $u, 'tipo' => $tp, 'n' => 0]; $cancTipo[$k]['n']++; }
    else $uni[$u]['desfN']++;
    if (count($canc) >= 1500) continue;
    $it = json_decode((string)$l['itens'], true); $cont = [];   // os itens iguais juntos ("3 × RECREAÇÃO · 30 MIN"), para a linha não ficar enorme
    if (is_array($it)) foreach ($it as $x) { $n = is_array($x) ? trim((string)($x['n'] ?? '')) : ''; if ($n !== '') $cont[$n] = ($cont[$n] ?? 0) + 1; }
    $itens = implode(', ', array_map(fn($n, $q) => ($q > 1 ? $q . ' × ' : '') . $n, array_keys($cont), $cont)); if (mb_strlen($itens) > 160) $itens = mb_substr($itens, 0, 157) . '…';
    $canc[] = ['id' => (int)$l['id'], 'quando' => (string)$l['quando'], 'u' => $u, 'tipo' => $tipos[(string)$l['tipo']] ?? 'Cancelamento', 'crianca' => (string)$l['nome'], 'resp' => (string)$l['resp'], 'valor' => round((float)$l['valor'], 2), 'min' => (int)$l['minutos'],
      'itens' => $itens, 'motivo' => (string)$l['motivo'], 'atendente' => $at, 'usuario' => (string)$l['por_nome'], 'conta' => $conta, 'entrada' => in_array((string)$l['tipo'], ['', 'desfeita'], true),
      'papel' => PAPEIS[(string)$l['papel']] ?? (string)$l['papel'], 'fora' => (int)$l['fora'] === 1, 'visto' => (string)$l['visto_por']]; }
  // os descontos: cada desconto dado (o do gestor, em R$ ou %) é uma linha, mesmo que tenha sido repartido em mais de uma venda;
  // o desconto PCD (o da tabela, automático) e os vouchers do shopping vão à parte
  $desc = []; $descQuem = []; $descTotal = 0;
  // 1.49 (auditoria): cada desconto dado é uma linha só, na data em que foi dado (o desconto guarda a linha do dia dele: desc_reg), mesmo
  // repartido em vendas de outro dia; dois descontos diferentes nunca se juntam. Os de antes da 1.49 (sem desc_reg) ficam agrupados como antes
  $tem = (bool)$db->query("SELECT 1 FROM pragma_table_info('vendas') WHERE name = 'desc_reg'")->fetchColumn();
  $L = [];
  if ($tem) { $q = $db->prepare("SELECT * FROM (SELECT COALESCE(MAX(rg.quando), MAX(v.quando)) quando, COALESCE(MAX(rg.unidade_id), MAX(v.unidade_id), 0) u, MAX(v.desc_por) desc_por, MAX(v.desc_motivo) desc_motivo, MIN(v.por) por,
        GROUP_CONCAT(v.servico, ' + ') itens, COALESCE(SUM(v.valor), 0) valor, COALESCE(SUM(v.desc_valor), 0) d, MAX(c.nome) nome, MAX(c.resp) resp FROM vendas v LEFT JOIN registros rg ON rg.id = v.desc_reg LEFT JOIN criancas c ON c.id = v.crianca_id
      WHERE v.desc_reg IS NOT NULL AND v.desc_valor > 0.004 GROUP BY v.desc_reg) WHERE quando >= ? AND quando < ?"); $q->execute([$ini, $fim]); $L = $q->fetchAll(); }
  $q = $db->prepare("SELECT COALESCE(v.unidade_id, 0) u, v.crianca_id, v.desc_por, v.desc_motivo, substr(v.quando, 1, 10) dia, MAX(v.quando) quando, MIN(v.por) por, GROUP_CONCAT(v.servico, ' + ') itens,
      COALESCE(SUM(v.valor), 0) valor, COALESCE(SUM(v.desc_valor), 0) d, c.nome, c.resp FROM vendas v LEFT JOIN criancas c ON c.id = v.crianca_id
    WHERE v.quando >= ? AND v.quando < ? AND v.desc_valor > 0.004" . ($tem ? ' AND v.desc_reg IS NULL' : '') . " GROUP BY u, v.crianca_id, v.desc_por, v.desc_motivo, dia");
  $q->execute([$ini, $fim]); $L = array_merge($L, $q->fetchAll()); usort($L, fn($x, $y) => strcmp((string)$y['quando'], (string)$x['quando']));
  foreach ($L as $l) { $u = (int)$l['u']; $U($u); $d = round((float)$l['d'], 2); $uni[$u]['descN']++; $uni[$u]['descV'] = round($uni[$u]['descV'] + $d, 2); $descTotal++;
    $cheio0 = round((float)$l['valor'] + $d, 2); $uni[$u]['descPct'] = round($uni[$u]['descPct'] + ($cheio0 > 0 ? $d / $cheio0 * 100 : 0), 2);
    $qn = $nomeUsu[(int)$l['desc_por']] ?? 'Sem nome'; $k = $u . '|' . $qn; if (!isset($descQuem[$k])) $descQuem[$k] = ['u' => $u, 'nome' => $qn, 'n' => 0, 'v' => 0.0]; $descQuem[$k]['n']++; $descQuem[$k]['v'] = round($descQuem[$k]['v'] + $d, 2);
    if (count($desc) >= 1500) continue; $cheio = round((float)$l['valor'] + $d, 2);
    $desc[] = ['quando' => (string)$l['quando'], 'u' => $u, 'crianca' => (string)$l['nome'], 'resp' => (string)$l['resp'], 'itens' => (string)$l['itens'], 'cheio' => $cheio, 'desconto' => $d, 'pct' => $cheio > 0 ? round($d / $cheio * 100, 1) : 0,
      'motivo' => (string)$l['desc_motivo'], 'liberou' => $nomeUsu[(int)$l['desc_por']] ?? '', 'atendente' => $nomeUsu[(int)$l['por']] ?? '']; }
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, COUNT(*) n, COALESCE(SUM(cheio - valor - desc_valor), 0) v FROM vendas WHERE quando >= ? AND quando < ? AND pcd = 1 AND cheio IS NOT NULL AND cheio - valor - desc_valor > 0.004 GROUP BY u');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $uni[$u]['pcdN'] = (int)$l['n']; $uni[$u]['pcdV'] = round((float)$l['v'], 2); }
  // 1.55: as notas fiscais do período (pelo dia em que foram emitidas; as canceladas à parte) e o caixa de cada dia, para comparar:
  // o caixa é o recebido (os pagamentos menos o devolvido), sempre de todos os produtos
  $nfL = []; $nfDia = [];
  $q = $db->prepare("SELECT * FROM pedidos WHERE tipo = 'nf' AND numero != '' AND feito_em >= ? AND feito_em < ? AND status IN ('usado', 'cancelado') ORDER BY feito_em DESC, id DESC"); $q->execute([$ini, $fim]);
  foreach ($q->fetchAll() as $p) { $n = notaPublica($p); $u = $n['uni']; $U($u); $d = substr($n['quando'], 0, 10); $k = $u . '|' . $d; if (!isset($nfDia[$k])) $nfDia[$k] = ['u' => $u, 'd' => $d, 'nf' => 0.0, 'n' => 0, 'cx' => 0.0];
    if ($n['st'] === 'emitida') { $uni[$u]['nfN']++; $uni[$u]['nfV'] = round($uni[$u]['nfV'] + $n['valor'], 2); $nfDia[$k]['n']++; $nfDia[$k]['nf'] = round($nfDia[$k]['nf'] + $n['valor'], 2); }
    else { $uni[$u]['nfCN']++; $uni[$u]['nfCV'] = round($uni[$u]['nfCV'] + $n['valor'], 2); }
    if (count($nfL) < 3000) $nfL[] = $n; }
  $cx = function (string $sql) use ($db, $ini, $fim, &$nfDia, $U) { $q = $db->prepare($sql); $q->execute([$ini, $fim]); return $q->fetchAll(); };
  $somaCx = function (array $L, int $sinal) use (&$nfDia, $U) { foreach ($L as $l) { $u = (int)$l['u']; $U($u); $k = $u . '|' . $l['d']; if (!isset($nfDia[$k])) $nfDia[$k] = ['u' => $u, 'd' => (string)$l['d'], 'nf' => 0.0, 'n' => 0, 'cx' => 0.0]; $nfDia[$k]['cx'] = round($nfDia[$k]['cx'] + $sinal * (float)$l['t'], 2); } };
  $somaCx($cx('SELECT COALESCE(p.unidade_id, v.unidade_id, 0) u, substr(p.quando, 1, 10) d, COALESCE(SUM(p.valor), 0) t FROM pagamentos p JOIN vendas v ON v.id = p.venda_id WHERE p.quando >= ? AND p.quando < ? GROUP BY u, d'), 1);
  $somaCx($cx('SELECT COALESCE(unidade_id, 0) u, substr(quando, 1, 10) d, COALESCE(SUM(valor), 0) t FROM estornos WHERE quando >= ? AND quando < ? GROUP BY u, d'), -1);
  $somaCx($cx('SELECT COALESCE(pago_uni, unidade_id, 0) u, substr(pago_em, 1, 10) d, COALESCE(SUM(valor), 0) t FROM estornos WHERE pago_em >= ? AND pago_em < ? GROUP BY u, d'), 1);
  // 1.88: a Casa Tom de cada lugar (unidade): o produto vendido no Play antes do primeiro dia da regra nova continua da Casa Tom (o de antes não muda)
  $casaTom = [];
  foreach (uniCasaTomPares() as $pu => $pa) {
    $x = ['u' => $pu, 'ct' => $pa['ct'], 'desde' => $pa['desde'], 'antes' => 0.0];
    if ($ini < $pa['desde'] . ' 00:00:00') {
      $q = $db->prepare('SELECT servico s, 1 pr, alug a, minutos m, auto au, voucher vo, valor v FROM vendas WHERE quando >= ? AND quando < ? AND unidade_id = ? AND COALESCE(produto_id, 0) > 0 AND servico != ?');
      $q->execute([$ini, min($fim, $pa['desde'] . ' 00:00:00'), $pu, SERV_IMPORTADO]);
      foreach ($q->fetchAll() as $r) { [$g, $n] = $cls($r); if ($g !== 'produtos' || !$passa($g, $n) || (float)$r['v'] <= 0.004) continue; $x['antes'] = round($x['antes'] + (float)$r['v'], 2); }
    }
    $casaTom[] = $x;
  }
  ksort($nfDia);
  ksort($uni); $out = []; foreach ($uni as $u => $x) $out[] = ['u' => $u] + $x;
  return ['de' => $de, 'ate' => $ate, 'dias' => $dias, 'gerado' => agora(), 'hoje' => date('Y-m-d'), 'unidades' => unidades(), 'porUnidade' => $out, 'criancasTotal' => $criancasTotal,
    'grupos' => array_values($grupos), 'pacotes' => array_values($pacotes), 'prodPac' => $prodPac, 'porDia' => $porDia, 'atendentes' => $atend, 'formas' => array_values($formas),
    'prod' => $prod, 'itens' => array_values($porItem), 'catalogo' => array_values($catalogo), 'casaTom' => $casaTom,
    'cancelamentos' => ['total' => $cancTotal, 'lista' => $canc, 'atendentes' => array_values($cancAt), 'tipos' => array_values($cancTipo)], 'descontos' => ['total' => $descTotal, 'lista' => $desc, 'quem' => array_values($descQuem)], 'notas' => ['lista' => $nfL, 'dia' => array_values($nfDia)]];
}

/* ---------- 1.54: o PDF (A4 em pé, Helvetica), feito aqui mesmo, sem biblioteca ---------- */
// As letras vão em Windows-1252 (o português inteiro cabe); as larguras de cada letra são as da Helvetica (para alinhar à direita e cortar).
final class PdfSimples {
  const W = 595.28; const H = 841.89;
  const LW = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,761,556,0,222,556,333,1000,556,556,333,1000,667,333,1000,0,611,0,0,222,222,333,333,350,556,1000,333,1000,500,333,944,0,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500];
  const BW = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,761,556,0,278,556,500,1000,556,556,333,1000,667,333,1000,0,611,0,0,278,278,500,500,350,556,1000,333,1000,556,333,944,0,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556];
  private $pags = []; private $s = ''; private $imgs = [];   // 2.0: as imagens (JPEG), uma vez cada no arquivo
  // 2.0: uma imagem JPEG (a logo) na posição e no tamanho pedidos (y de cima para baixo, como o resto)
  function imagem($jpg, $x, $y, $w, $h) {
    $k = md5((string)$jpg);
    if (!isset($this->imgs[$k])) { [$iw, $ih, $cp] = self::jpegTam((string)$jpg); if (!$iw) return; $this->imgs[$k] = ['n' => 'Im' . (count($this->imgs) + 1), 'w' => $iw, 'h' => $ih, 'c' => $cp, 'b' => (string)$jpg]; }
    $this->s .= 'q ' . self::n($w) . ' 0 0 ' . self::n($h) . ' ' . self::n($x) . ' ' . self::n(self::H - $y - $h) . ' cm /' . $this->imgs[$k]['n'] . " Do Q\n";
  }
  static function jpegTam($b) {
    $n = strlen($b); if ($n < 12 || substr($b, 0, 2) !== "\xFF\xD8") return [0, 0, 0]; $i = 2;
    while ($i + 9 < $n) { if (ord($b[$i]) !== 0xFF) { $i++; continue; } $m = ord($b[$i + 1]); if ($m === 0xFF) { $i++; continue; } $len = unpack('n', substr($b, $i + 2, 2))[1];
      if (in_array($m, [0xC0, 0xC1, 0xC2], true)) return [unpack('n', substr($b, $i + 7, 2))[1], unpack('n', substr($b, $i + 5, 2))[1], ord($b[$i + 9])];
      $i += 2 + $len; }
    return [0, 0, 0];
  }
  function pagina() { if ($this->s !== '') $this->pags[] = $this->s; $this->s = ''; }
  function paginas() { return count($this->pags) + ($this->s !== '' ? 1 : 0); }
  static function win($t) {
    $t = strtr((string)$t, ["\u{2212}" => '-', "\u{2713}" => '', "\u{2192}" => '>', "\u{00A0}" => ' ', "\u{2009}" => ' ', "\u{202F}" => ' ']);
    $r = function_exists('iconv') ? @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $t) : false;
    if ($r === false || $r === '') $r = function_exists('mb_convert_encoding') ? (string)@mb_convert_encoding($t, 'Windows-1252', 'UTF-8') : (string)preg_replace('/[^\x20-\x7E]/', '?', $t);
    return $r;
  }
  static function n($x) { return rtrim(rtrim(number_format((float)$x, 2, '.', ''), '0'), '.'); }
  static function cor($hex) { $h = ltrim((string)$hex, '#'); return self::n(hexdec(substr($h, 0, 2)) / 255) . ' ' . self::n(hexdec(substr($h, 2, 2)) / 255) . ' ' . self::n(hexdec(substr($h, 4, 2)) / 255); }
  function larg($t, $tam, $b = false) { $s = self::win($t); $L = $b ? self::BW : self::LW; $w = 0; for ($i = 0, $n = strlen($s); $i < $n; $i++) { $c = ord($s[$i]); if ($c >= 32) $w += $L[$c - 32] ?: 556; } return $w * $tam / 1000; }
  // o texto cortado com "…" para caber na largura
  function corta($t, $max, $tam, $b = false) { $t = (string)$t; if ($this->larg($t, $tam, $b) <= $max) return $t; while ($t !== '' && $this->larg($t . '…', $tam, $b) > $max) $t = mb_substr($t, 0, mb_strlen($t) - 1); return rtrim($t) . '…'; }
  // o texto quebrado em linhas que cabem na largura
  function quebra($t, $max, $tam, $b = false) { $o = []; $l = ''; foreach (preg_split('/\s+/u', trim((string)$t)) as $p) { $x = $l === '' ? $p : $l . ' ' . $p; if ($l !== '' && $this->larg($x, $tam, $b) > $max) { $o[] = $l; $l = $p; } else $l = $x; } if ($l !== '') $o[] = $l; return $o ?: ['']; }
  // y conta de cima para baixo (como na tela); al: E (esquerda), D (direita), C (centro)
  function texto($x, $y, $t, $tam = 10, $b = false, $cor = '3B3B3B', $al = 'E') {
    if ($al === 'D') $x -= $this->larg($t, $tam, $b); elseif ($al === 'C') $x -= $this->larg($t, $tam, $b) / 2;
    $s = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], self::win($t));
    $this->s .= 'BT /' . ($b ? 'F2' : 'F1') . ' ' . self::n($tam) . ' Tf ' . self::cor($cor) . ' rg ' . self::n($x) . ' ' . self::n(self::H - $y) . " Td ($s) Tj ET\n";
  }
  /* 4.6 (1.96): o texto com as letras espaçadas (os rótulos em maiúsculas dos blocos) */
  function textoEsp($x, $y, $t, $tam, $b, $cor, $esp, $al = 'E') {
    $w = $this->larg($t, $tam, $b) + $esp * max(0, mb_strlen((string)$t) - 1); if ($al === 'D') $x -= $w; elseif ($al === 'C') $x -= $w / 2;
    $s = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], self::win($t));
    $this->s .= 'BT /' . ($b ? 'F2' : 'F1') . ' ' . self::n($tam) . ' Tf ' . self::n($esp) . ' Tc ' . self::cor($cor) . ' rg ' . self::n($x) . ' ' . self::n(self::H - $y) . " Td ($s) Tj 0 Tc ET\n";
  }
  /* 4.6 (1.96): o cartão desenhado por baixo do que já está na página (o fundo entra no ponto marcado antes) */
  function marca() { return strlen($this->s); }
  function fundoEm($pos, $x, $y, $w, $h, $cor, $raio = 0, $borda = null) { $s = $this->s; $this->s = ''; $this->ret($x, $y, $w, $h, $cor, $raio, $borda); $r = $this->s; $this->s = substr($s, 0, $pos) . $r . substr($s, $pos); }
  function ret($x, $y, $w, $h, $cor, $raio = 0, $borda = null) {
    $X = $x; $Y = self::H - $y - $h; $r = min($raio, $w / 2, $h / 2); $k = 0.5523 * $r;
    $p = $r > 0 ? self::n($X + $r) . ' ' . self::n($Y) . ' m ' . self::n($X + $w - $r) . ' ' . self::n($Y) . ' l ' . self::n($X + $w - $r + $k) . ' ' . self::n($Y) . ' ' . self::n($X + $w) . ' ' . self::n($Y + $r - $k) . ' ' . self::n($X + $w) . ' ' . self::n($Y + $r) . ' c '
      . self::n($X + $w) . ' ' . self::n($Y + $h - $r) . ' l ' . self::n($X + $w) . ' ' . self::n($Y + $h - $r + $k) . ' ' . self::n($X + $w - $r + $k) . ' ' . self::n($Y + $h) . ' ' . self::n($X + $w - $r) . ' ' . self::n($Y + $h) . ' c '
      . self::n($X + $r) . ' ' . self::n($Y + $h) . ' l ' . self::n($X + $r - $k) . ' ' . self::n($Y + $h) . ' ' . self::n($X) . ' ' . self::n($Y + $h - $r + $k) . ' ' . self::n($X) . ' ' . self::n($Y + $h - $r) . ' c '
      . self::n($X) . ' ' . self::n($Y + $r) . ' l ' . self::n($X) . ' ' . self::n($Y + $r - $k) . ' ' . self::n($X + $r - $k) . ' ' . self::n($Y) . ' ' . self::n($X + $r) . ' ' . self::n($Y) . ' c h'
      : self::n($X) . ' ' . self::n($Y) . ' ' . self::n($w) . ' ' . self::n($h) . ' re';
    $this->s .= ($cor ? self::cor($cor) . ' rg ' : '') . ($borda ? self::cor($borda) . ' RG 0.7 w ' : '') . $p . ' ' . ($cor && $borda ? 'B' : ($cor ? 'f' : 'S')) . "\n";
  }
  /* 2.6 (1.65): a elipse (e o círculo), a fatia da rosca e o traço grosso, para os relatórios com gráficos e o ursinho. y de cima para baixo */
  function elipse($cx, $cy, $rx, $ry, $cor, $borda = null, $esp = 0.7) {
    $X = $cx; $Y = self::H - $cy; $kx = 0.5523 * $rx; $ky = 0.5523 * $ry; $n = fn($v) => self::n($v);
    $p = $n($X + $rx) . ' ' . $n($Y) . ' m '
      . $n($X + $rx) . ' ' . $n($Y + $ky) . ' ' . $n($X + $kx) . ' ' . $n($Y + $ry) . ' ' . $n($X) . ' ' . $n($Y + $ry) . ' c '
      . $n($X - $kx) . ' ' . $n($Y + $ry) . ' ' . $n($X - $rx) . ' ' . $n($Y + $ky) . ' ' . $n($X - $rx) . ' ' . $n($Y) . ' c '
      . $n($X - $rx) . ' ' . $n($Y - $ky) . ' ' . $n($X - $kx) . ' ' . $n($Y - $ry) . ' ' . $n($X) . ' ' . $n($Y - $ry) . ' c '
      . $n($X + $kx) . ' ' . $n($Y - $ry) . ' ' . $n($X + $rx) . ' ' . $n($Y - $ky) . ' ' . $n($X + $rx) . ' ' . $n($Y) . ' c h';
    $this->s .= ($cor ? self::cor($cor) . ' rg ' : '') . ($borda ? self::cor($borda) . ' RG ' . self::n($esp) . ' w ' : '') . $p . ' ' . ($cor && $borda ? 'B' : ($cor ? 'f' : 'S')) . "\n";
  }
  function circulo($cx, $cy, $r, $cor, $borda = null, $esp = 0.7) { $this->elipse($cx, $cy, $r, $r, $cor, $borda, $esp); }
  /* o arco em curvas (até 90 graus cada): ângulos em graus, no sentido do relógio, a partir do meio-dia */
  private static function arco($cx, $cy, $r, $a0, $a1, bool $comeca) {
    $pt = fn($a) => [$cx + $r * sin(deg2rad($a)), self::H - ($cy - $r * cos(deg2rad($a)))];
    $o = ''; $passo = $a1 >= $a0 ? 1 : -1; $n = max(1, (int)ceil(abs($a1 - $a0) / 90)); $d = ($a1 - $a0) / $n;
    if ($comeca) { [$x, $y] = $pt($a0); $o .= self::n($x) . ' ' . self::n($y) . ' m '; } else { [$x, $y] = $pt($a0); $o .= self::n($x) . ' ' . self::n($y) . ' l '; }
    for ($i = 0; $i < $n; $i++) {
      $a = $a0 + $i * $d; $b = $a + $d; $k = 4 / 3 * tan(deg2rad($b - $a) / 4) * $r;
      [$x0, $y0] = $pt($a); [$x3, $y3] = $pt($b);
      $x1 = $x0 + $k * cos(deg2rad($a)); $y1 = $y0 - $k * sin(deg2rad($a)); $x2 = $x3 - $k * cos(deg2rad($b)); $y2 = $y3 + $k * sin(deg2rad($b));
      $o .= self::n($x1) . ' ' . self::n($y1) . ' ' . self::n($x2) . ' ' . self::n($y2) . ' ' . self::n($x3) . ' ' . self::n($y3) . ' c ';
    }
    return $o;
  }
  /* a fatia da rosca: do raio de dentro (r2) ao de fora (r), de a0 a a1 graus */
  function fatia($cx, $cy, $r, $r2, $a0, $a1, $cor) {
    if ($a1 - $a0 >= 359.99) { $this->elipse($cx, $cy, $r, $r, $cor); if ($r2 > 0) $this->elipse($cx, $cy, $r2, $r2, 'FFFFFF'); return; }
    $p = self::arco($cx, $cy, $r, $a0, $a1, true) . ($r2 > 0 ? self::arco($cx, $cy, $r2, $a1, $a0, false) : self::n($cx) . ' ' . self::n(self::H - $cy) . ' l ') . 'h';
    $this->s .= self::cor($cor) . ' rg ' . $p . " f\n";
  }
  /* o traço grosso com as pontas redondas (a linha do gráfico, a boca do ursinho): [[x, y], ...] */
  function traco(array $pts, $cor, $esp = 1.5) {
    if (count($pts) < 2) return; $o = '';
    foreach ($pts as $i => [$x, $y]) $o .= self::n($x) . ' ' . self::n(self::H - $y) . ($i ? ' l ' : ' m ');
    $this->s .= 'q 1 J 1 j ' . self::cor($cor) . ' RG ' . self::n($esp) . ' w ' . $o . "S Q\n";
  }
  /* a curva simples (de p0 a p2, puxada por p1): o sorriso */
  function curva($x0, $y0, $x1, $y1, $x2, $y2, $cor, $esp = 1.5) {
    $c1x = $x0 + 2 / 3 * ($x1 - $x0); $c1y = $y0 + 2 / 3 * ($y1 - $y0); $c2x = $x2 + 2 / 3 * ($x1 - $x2); $c2y = $y2 + 2 / 3 * ($y1 - $y2);
    $this->s .= 'q 1 J ' . self::cor($cor) . ' RG ' . self::n($esp) . ' w ' . self::n($x0) . ' ' . self::n(self::H - $y0) . ' m ' . self::n($c1x) . ' ' . self::n(self::H - $c1y) . ' ' . self::n($c2x) . ' ' . self::n(self::H - $c2y) . ' ' . self::n($x2) . ' ' . self::n(self::H - $y2) . " c S Q\n";
  }
  function linha($x1, $y1, $x2, $y2, $cor = 'E8DFD3', $esp = 0.7) { $this->s .= self::cor($cor) . ' RG ' . self::n($esp) . ' w ' . self::n($x1) . ' ' . self::n(self::H - $y1) . ' m ' . self::n($x2) . ' ' . self::n(self::H - $y2) . " l S\n"; }
  // o rodapé de cada página (precisa saber quantas páginas são): chamado no fim, para cada uma
  function rodape(callable $f) { $this->pagina(); foreach ($this->pags as $i => $s) { $this->s = ''; $f($this, $i + 1, count($this->pags)); $this->pags[$i] = $s . $this->s; } $this->s = ''; }
  function pdf($titulo = 'Play Space') {
    if ($this->s !== '') $this->pagina(); if (!$this->pags) $this->pags[] = '';
    $obj = []; $n = count($this->pags); $kids = [];
    $obj[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $obj[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $obj[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $obj[5] = '<< /Title (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], self::win($titulo)) . ') /Producer (Play Space) /CreationDate (D:' . date('YmdHis') . ') >>';
    $xo = ''; $base = 6 + 2 * $n; $im = 0;   // 2.0: as imagens depois das páginas
    foreach ($this->imgs as $g) { $id = $base + $im++; $xo .= ' /' . $g['n'] . " $id 0 R";
      $obj[$id] = '<< /Type /XObject /Subtype /Image /Width ' . $g['w'] . ' /Height ' . $g['h'] . ' /ColorSpace /' . ($g['c'] === 1 ? 'DeviceGray' : ($g['c'] === 4 ? 'DeviceCMYK' : 'DeviceRGB')) . ' /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($g['b']) . " >>\nstream\n" . $g['b'] . "\nendstream"; }
    foreach ($this->pags as $i => $c) { $pg = 6 + 2 * $i; $ct = $pg + 1; $kids[] = "$pg 0 R";
      $z = function_exists('gzcompress') ? gzcompress($c, 6) : false;
      $obj[$pg] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::n(self::W) . ' ' . self::n(self::H) . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . ($xo !== '' ? ' /XObject <<' . $xo . ' >>' : '') . ' >> /Contents ' . $ct . ' 0 R >>';
      $obj[$ct] = $z !== false ? '<< /Length ' . strlen($z) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream" : '<< /Length ' . strlen($c) . " >>\nstream\n" . $c . "\nendstream"; }
    $obj[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
    ksort($obj); $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $off = [];
    foreach ($obj as $k => $v) { $off[$k] = strlen($out); $out .= "$k 0 obj\n$v\nendobj\n"; }
    $x = strlen($out); $max = max(array_keys($obj)); $out .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($k = 1; $k <= $max; $k++) $out .= isset($off[$k]) ? sprintf("%010d 00000 n \n", $off[$k]) : "0000000000 65535 f \n";
    return $out . "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n$x\n%%EOF\n";
  }
}

/* ---------- 1.54: o resumo do dia (ou do período) em PDF ---------- */
const FIN_GRUPOS = ['pacotes' => 'Pacotes de horas', 'recreacao' => 'Recreação avulsa', 'festa' => 'Festas', 'produtos' => 'Casa Tom (produtos)', 'carrinhos' => 'Carrinhos', 'outros' => 'Outros'];
const FIN_FORMAS = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'credito' => 'Crédito', 'debito' => 'Débito'];
function finBrl($v) { $v = round((float)$v, 2); return ($v < 0 ? '- ' : '') . 'R$ ' . number_format(abs($v), 2, ',', '.'); }
function finInt($v) { return number_format((int)$v, 0, ',', '.'); }
function finDiaLongo($d) { $t = strtotime($d . ' 12:00:00'); $sem = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado']; $mes = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
  return $sem[(int)date('w', $t)] . ', ' . (int)date('j', $t) . ' de ' . $mes[(int)date('n', $t)] . ' de ' . date('Y', $t); }
function finDataBR($d) { return substr((string)$d, 8, 2) . '/' . substr((string)$d, 5, 2) . '/' . substr((string)$d, 0, 4); }
// os números do resumo para uma unidade (ou todas): os totais, as formas, os tipos, os itens, os cancelamentos e os descontos
function finResumoUni(array $R, $uni) {
  $na = function ($x) use ($uni) { return $uni === 'todas' || (int)($x['u'] ?? 0) === (int)$uni; };
  $t = ['faturado' => 0.0, 'recebido' => 0.0, 'devolvido' => 0.0, 'aberto' => 0.0, 'vendas' => 0, 'visitas' => 0, 'criancas' => 0, 'pacN' => 0, 'pacV' => 0.0, 'festas' => 0, 'cancN' => 0, 'cancV' => 0.0, 'descN' => 0, 'descV' => 0.0, 'agora' => 0, 'nfN' => 0, 'nfV' => 0.0, 'nfCN' => 0, 'nfCV' => 0.0];
  $U = array_values(array_filter((array)$R['porUnidade'], $na));
  foreach ($U as $x) foreach ($t as $k => $v) $t[$k] = is_float($v) ? round($v + (float)($x[$k] ?? 0), 2) : $v + (int)($x[$k] ?? 0);
  if ($uni === 'todas' && isset($R['criancasTotal'])) $t['criancas'] = (int)$R['criancasTotal'];
  $F = []; foreach (array_filter((array)$R['formas'], $na) as $x) $F[(string)$x['f']] = round(($F[(string)$x['f']] ?? 0) + (float)$x['v'], 2);
  $G = []; foreach (array_filter((array)$R['grupos'], $na) as $x) { $g = (string)$x['g']; if (!isset($G[$g])) $G[$g] = ['n' => 0, 'v' => 0.0]; $G[$g]['n'] += (int)$x['n']; $G[$g]['v'] = round($G[$g]['v'] + (float)$x['v'], 2); }
  $I = []; foreach (array_filter((array)($R['itens'] ?? []), $na) as $x) { $k = $x['g'] . '|' . $x['n']; if (!isset($I[$k])) $I[$k] = ['g' => (string)$x['g'], 'n' => (string)$x['n'], 'q' => 0, 'v' => 0.0]; $I[$k]['q'] += (int)$x['q']; $I[$k]['v'] = round($I[$k]['v'] + (float)$x['v'], 2); }
  $ordem = array_flip(array_keys(FIN_GRUPOS)); usort($I, function ($a, $b) use ($ordem) { return (($ordem[$a['g']] ?? 9) <=> ($ordem[$b['g']] ?? 9)) ?: ($b['v'] <=> $a['v']) ?: strcmp($a['n'], $b['n']); });
  $C = array_values(array_filter((array)($R['cancelamentos']['lista'] ?? []), function ($x) use ($na) { return $na($x) && !empty($x['conta']); }));
  $D = array_values(array_filter((array)($R['descontos']['lista'] ?? []), $na));
  $N = array_values(array_filter((array)($R['notas']['lista'] ?? []), function ($x) use ($uni) { return $uni === 'todas' || (int)($x['uni'] ?? 0) === (int)$uni; }));   // 1.55
  return ['t' => $t, 'U' => $U, 'F' => $F, 'G' => $G, 'I' => array_values($I), 'C' => $C, 'D' => $D, 'N' => $N];
}
function finNomeUni($id, array $R) { foreach ((array)$R['unidades'] as $u) if ((int)$u['id'] === (int)$id) return (string)$u['nome']; return (int)$id ? 'Unidade ' . (int)$id : 'Sem unidade'; }
function finPdf(array $R, $uni = 'todas') {
  $Z = finResumoUni($R, $uni); $t = $Z['t']; $p = new PdfSimples(); $M = 40; $W = PdfSimples::W - 2 * $M; $y = 0;
  $CORAL = 'E25B3F'; $TINTA = '3B3B3B'; $MUDO = '7A736B'; $GRADE = 'ECE2D5'; $FUNDO = 'FFF8EE';
  $um = $R['de'] === $R['ate'];
  $titulo = $um ? 'Resumo do dia' : 'Resumo do período';
  $sub = ($um ? finDiaLongo($R['de']) : finDataBR($R['de']) . ' a ' . finDataBR($R['ate'])) . ' · ' . ($uni === 'todas' ? (count((array)$R['unidades']) > 1 ? 'todas as unidades' : 'a loja') : finNomeUni($uni, $R));
  $cab = function () use ($p, $M, $W, $titulo, $sub, $CORAL, $TINTA, $MUDO, $R, &$y) {
    $p->ret(0, 0, PdfSimples::W, 6, $CORAL);
    $p->texto($M, 34, 'PLAY SPACE · FINANÇAS', 8.5, true, $CORAL); $p->texto($M + $W, 34, 'Gerado em ' . finDataBR(substr((string)$R['gerado'], 0, 10)) . ' às ' . substr((string)$R['gerado'], 11, 5), 8.5, false, $MUDO, 'D');
    $p->texto($M, 62, $titulo, 22, true, $TINTA); $p->texto($M, 80, $sub, 10.5, false, $MUDO); $y = 98;
  };
  $novaPag = function () use ($p, $cab) { $p->pagina(); $cab(); };
  $cabe = function ($h) use ($novaPag, &$y) { if ($y + $h > PdfSimples::H - 46) $novaPag(); };
  $secao = function ($nome) use ($p, $M, $TINTA, $cabe, &$y) { $cabe(46); $y += 10; $p->texto($M, $y + 12, $nome, 12.5, true, $TINTA); $y += 22; };
  $cab();
  // os números grandes
  $caixas = [['Faturado', finBrl($t['faturado']), finInt($t['vendas']) . ' vendas'], ['Recebido', finBrl($t['recebido']), $t['devolvido'] > 0.004 ? 'já sem ' . finBrl($t['devolvido']) . ' devolvidos' : 'pagamentos do dia'],
    ['Em aberto', finBrl($t['aberto']), 'vendido e ainda não pago'], ['Crianças', finInt($t['visitas']), finInt($t['criancas']) . ' diferentes' . ($t['agora'] ? ' · ' . finInt($t['agora']) . ' no espaço' : '')]];
  $cw = ($W - 3 * 8) / 4;
  foreach ($caixas as $i => $c) { $x = $M + $i * ($cw + 8); $p->ret($x, $y, $cw, 58, $FUNDO, 6, $GRADE); $p->texto($x + 10, $y + 16, mb_strtoupper($c[0]), 7.5, true, $MUDO); $p->texto($x + 10, $y + 36, $p->corta($c[1], $cw - 20, 15, true), 15, true, $TINTA); $p->texto($x + 10, $y + 50, $p->corta($c[2], $cw - 20, 8), 8, false, $MUDO); }
  $y += 66;
  $caixas = [['Pacotes vendidos', finInt($t['pacN']), finBrl($t['pacV'])], ['Descontos', finInt($t['descN']), finBrl($t['descV'])], ['Cancelamentos', finInt($t['cancN']), finBrl($t['cancV']) . ' não cobrado'], ['Festas marcadas', finInt($t['festas']), $um ? 'para o dia' : 'no período']];
  foreach ($caixas as $i => $c) { $x = $M + $i * ($cw + 8); $p->ret($x, $y, $cw, 50, $FUNDO, 6, $GRADE); $p->texto($x + 10, $y + 16, mb_strtoupper($c[0]), 7.5, true, $MUDO); $p->texto($x + 10, $y + 34, $c[1], 13, true, $TINTA); $p->texto($x + 10 + $p->larg($c[1], 13, true) + 6, $y + 34, $p->corta($c[2], $cw - 30 - $p->larg($c[1], 13, true), 8.5), 8.5, false, $MUDO); }
  $y += 58;
  // uma tabela: cabeçalho e linhas, as colunas com largura e alinhamento
  $tabela = function (array $cols, array $linhas, $total = null) use ($p, $M, $TINTA, $MUDO, $GRADE, $cabe, &$y) {
    $lh = 17; $cabeca = function () use ($p, $cols, $M, $MUDO, $GRADE, &$y) { $x = $M; foreach ($cols as $c) { $p->texto($c[2] === 'D' ? $x + $c[1] - 4 : $x + 4, $y + 11, mb_strtoupper($c[0]), 7.2, true, $MUDO, $c[2]); $x += $c[1]; } $y += 16; $p->linha($M, $y, $M + array_sum(array_column($cols, 1)), $y, $GRADE, 0.8); };
    $cabe(16 + $lh * min(3, count($linhas) + 1)); $cabeca();
    foreach ($linhas as $i => $l) {
      if ($y + $lh > PdfSimples::H - 46) { $cabe(999); $cabeca(); }
      $x = $M; $neg = !empty($l['_b']); foreach ($cols as $j => $c) { $v = (string)($l[$j] ?? ''); $p->texto($c[2] === 'D' ? $x + $c[1] - 4 : $x + 4, $y + 12, $p->corta($v, $c[1] - 8, 8.8, $neg), 8.8, $neg, $neg ? $TINTA : '4A4540', $c[2]); $x += $c[1]; }
      $y += $lh; $p->linha($M, $y, $M + array_sum(array_column($cols, 1)), $y, 'F3ECE2', 0.6); }
    if ($total) { $x = $M; foreach ($cols as $j => $c) { $v = (string)($total[$j] ?? ''); $p->texto($c[2] === 'D' ? $x + $c[1] - 4 : $x + 4, $y + 13, $p->corta($v, $c[1] - 8, 9, true), 9, true, $TINTA, $c[2]); $x += $c[1]; } $y += $lh + 2; }
    $y += 4;
  };
  // cada unidade (com mais de uma)
  if ($uni === 'todas' && count($Z['U']) > 1) {
    $secao('Cada unidade'); $us = $Z['U']; $cw0 = 150; $cwu = ($W - $cw0) / (count($us) + 1);
    $cols = [['', $cw0, 'E']]; foreach ($us as $x) $cols[] = [finNomeUni($x['u'], $R), $cwu, 'D']; $cols[] = ['Total', $cwu, 'D'];
    $lin = function ($rot, $k, $fmt) use ($us, $t) { $l = [$rot]; foreach ($us as $x) $l[] = $fmt($x[$k] ?? 0); $l[] = $fmt($t[$k] ?? 0); return $l; };
    $tabela($cols, [$lin('Faturado', 'faturado', 'finBrl'), $lin('Recebido', 'recebido', 'finBrl'), $lin('Em aberto', 'aberto', 'finBrl'), $lin('Vendas', 'vendas', 'finInt'), $lin('Visitas (crianças)', 'visitas', 'finInt'),
      $lin('Pacotes vendidos', 'pacN', 'finInt'), $lin('Valor dos pacotes', 'pacV', 'finBrl'), $lin('Descontos', 'descV', 'finBrl'), $lin('Cancelamentos', 'cancN', 'finInt')]);
  }
  // as formas de pagamento
  $secao('Formas de pagamento'); $rec = $t['recebido']; $L = [];
  foreach (FIN_FORMAS as $f => $nm) if (abs($Z['F'][$f] ?? 0) > 0.004) $L[] = [$nm, finBrl($Z['F'][$f]), $rec > 0.004 ? number_format(($Z['F'][$f]) / $rec * 100, 1, ',', '') . '%' : ''];
  foreach ($Z['F'] as $f => $v) if (!isset(FIN_FORMAS[$f]) && abs($v) > 0.004) $L[] = [ucfirst($f), finBrl($v), $rec > 0.004 ? number_format($v / $rec * 100, 1, ',', '') . '%' : ''];
  if (!$L) $L[] = ['Nenhum pagamento', '', ''];
  $tabela([['Forma', $W - 220, 'E'], ['Valor', 130, 'D'], ['%', 90, 'D']], $L, ['Total recebido', finBrl($rec), '']);
  // 1.55: as notas fiscais: o faturamento em notas ao lado do caixa, e cada nota
  $secao('Notas fiscais'); $sem = round($rec - $t['nfV'], 2);
  $tabela([['', $W - 220, 'E'], ['Valor', 130, 'D'], ['%', 90, 'D']], [['Em notas fiscais (' . finInt($t['nfN']) . ($t['nfN'] === 1 ? ' nota' : ' notas') . ')', finBrl($t['nfV']), $rec > 0.004 ? number_format($t['nfV'] / $rec * 100, 1, ',', '') . '% do caixa' : ''],
    ['No caixa (recebido)', finBrl($rec), ''], [$sem >= 0 ? 'Sem nota' : 'Notas acima do caixa', finBrl(abs($sem)), ''], ['Canceladas (' . finInt($t['nfCN']) . ')', finBrl($t['nfCV']), 'não contam']]);
  if ($Z['N']) { $L = []; foreach ($Z['N'] as $x) $L[] = [($um ? '' : substr((string)$x['quando'], 8, 2) . '/' . substr((string)$x['quando'], 5, 2) . ' ') . substr((string)$x['quando'], 11, 5), (string)$x['numero'], (string)($x['nome'] !== '' ? $x['nome'] : $x['cliente']), (string)$x['item'], finBrl($x['valor']), $x['st'] === 'cancelada' ? 'CANCELADA' : ($x['vc'] ? 'venda cancelada' : (string)$x['por'])];
    $tabela([['Hora', $um ? 42 : 70, 'E'], ['Nº', 56, 'E'], ['Em nome de', 140, 'E'], ['Compra', $W - ($um ? 42 : 70) - 56 - 140 - 78 - 96, 'E'], ['Valor', 78, 'D'], ['Emitida por', 96, 'E']], $L); }
  // o que foi vendido, por tipo
  $secao('O que foi vendido'); $L = []; $fat = $t['faturado'];
  foreach (FIN_GRUPOS as $g => $nm) if (isset($Z['G'][$g]) && ($Z['G'][$g]['n'] || abs($Z['G'][$g]['v']) > 0.004)) $L[] = [$nm, finInt($Z['G'][$g]['n']), finBrl($Z['G'][$g]['v']), $fat > 0.004 ? number_format($Z['G'][$g]['v'] / $fat * 100, 1, ',', '') . '%' : ''];
  if (!$L) $L[] = ['Nenhuma venda', '', '', ''];
  $tabela([['Tipo', $W - 300, 'E'], ['Qtd', 70, 'D'], ['Valor', 130, 'D'], ['%', 100, 'D']], $L, ['Total', '', finBrl($fat), '']);
  // produto a produto
  if ($Z['I']) { $secao('Produto a produto'); $L = []; foreach ($Z['I'] as $x) $L[] = [FIN_GRUPOS[$x['g']] ?? $x['g'], $x['n'], finInt($x['q']), finBrl($x['v'])];
    $tabela([['Tipo', 120, 'E'], ['Produto', $W - 320, 'E'], ['Qtd', 70, 'D'], ['Valor', 130, 'D']], $L); }
  // os descontos
  if ($Z['D']) { $secao('Descontos (' . finInt(count($Z['D'])) . ')'); $L = [];
    foreach ($Z['D'] as $x) $L[] = [substr((string)$x['quando'], 11, 5), trim(($x['resp'] !== '' ? $x['resp'] . ' · ' : '') . $x['crianca']), finBrl($x['desconto']), (string)$x['motivo'], (string)$x['liberou']];
    $tabela([['Hora', 42, 'E'], ['Cliente', 150, 'E'], ['Desconto', 78, 'D'], ['Motivo', $W - 360, 'E'], ['Liberou', 90, 'E']], $L); }
  // os cancelamentos
  if ($Z['C']) { $secao('Cancelamentos (' . finInt(count($Z['C'])) . ')'); $L = [];
    foreach ($Z['C'] as $x) $L[] = [substr((string)$x['quando'], 11, 5), (string)$x['tipo'], trim((string)$x['crianca']), finBrl($x['valor']), (string)$x['motivo'], (string)$x['atendente']];
    $tabela([['Hora', 42, 'E'], ['O quê', 110, 'E'], ['Criança', 110, 'E'], ['Valor', 70, 'D'], ['Motivo', $W - 412, 'E'], ['Atendente', 80, 'E']], $L); }
  $p->rodape(function ($p, $i, $n) use ($M, $W, $MUDO, $GRADE) { $p->linha($M, PdfSimples::H - 30, $M + $W, PdfSimples::H - 30, $GRADE, 0.6); $p->texto($M, PdfSimples::H - 18, 'Play Space · Finanças · os números da tela Visão geral', 7.5, false, $MUDO); $p->texto($M + $W, PdfSimples::H - 18, 'página ' . $i . ' de ' . $n, 7.5, false, $MUDO, 'D'); });
  return $p->pdf($titulo . ' · ' . $sub);
}
function finPdfNome(array $R) { return 'play-space-resumo-' . ($R['de'] === $R['ate'] ? $R['de'] : $R['de'] . '-a-' . $R['ate']) . '.pdf'; }
// manda o PDF para o navegador (baixar)
function finPdfSaida(array $in) {
  $uni = (string)($in['uni'] ?? 'todas'); $uni = $uni === 'todas' || $uni === '' ? 'todas' : (int)$uni;
  $R = finResumo(['de' => (string)($in['de'] ?? ''), 'ate' => (string)($in['ate'] ?? ''), 'prod' => '']); $pdf = finPdf($R, $uni);
  header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . finPdfNome($R) . '"'); header('Cache-Control: no-store'); header('Content-Length: ' . strlen($pdf));
  echo $pdf; exit;
}

/* ---------- 1.54: o resumo do dia por e-mail ---------- */
// Quem recebe fica em config (fin_email): { para: [...], auto: true|false, ultimo: o último dia mandado sozinho, res, quando }. Sozinho, sai uma vez
// por dia, 30 min depois da hora em que a loja fecha (regra 'fecha'): no primeiro pulso de qualquer tela aberta depois disso (se nenhuma
// estiver aberta, no primeiro da manhã seguinte, com o resumo de ontem).
const FIN_EMAIL_DEPOIS_MIN = 30;
function finEmailCfg() { $q = db()->query("SELECT valor FROM config WHERE chave = 'fin_email'"); $v = $q->fetchColumn(); $q->closeCursor(); $j = json_decode((string)$v, true); return (is_array($j) ? $j : []) + ['para' => [], 'auto' => false, 'ultimo' => '', 'res' => '', 'quando' => '', 'dominio' => '']; }
function finEmailBruto() { $q = db()->query("SELECT valor FROM config WHERE chave = 'fin_email'"); $v = $q->fetchColumn(); $q->closeCursor(); return $v === false ? null : (string)$v; }
function finEmailGuarda(array $c) { db()->prepare("INSERT OR REPLACE INTO config (chave, valor) VALUES ('fin_email', ?)")->execute([json_encode($c, JSON_UNESCAPED_UNICODE)]); }
function finEmailLista($bruto) {
  $o = []; foreach (preg_split('/[\s,;]+/u', mb_strtolower(trim((string)$bruto))) as $e) { if ($e === '') continue; if (!filter_var($e, FILTER_VALIDATE_EMAIL)) falha('Confira o e-mail: ' . $e); if (!in_array($e, $o, true)) $o[] = $e; }
  if (count($o) > 10) falha('Até 10 e-mails.'); return $o;
}
function finEmailHora() { $f = (int)(regra()['fecha'] ?? 22); $m = $f * 60 + FIN_EMAIL_DEPOIS_MIN; return $m >= 1440 ? null : sprintf('%02d:%02d', intdiv($m, 60), $m % 60); }   // null: depois da meia-noite (sai na manhã seguinte)
function finEmailAlvo() { $f = (int)(regra()['fecha'] ?? 22); $hoje = date('Y-m-d'); return time() >= strtotime($hoje . ' 00:00:00') + $f * 3600 + FIN_EMAIL_DEPOIS_MIN * 60 ? $hoje : date('Y-m-d', strtotime($hoje . ' 12:00:00 -1 day')); }
function finDominio(array $c) {
  $h = mb_strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? ''))); $h = preg_replace('/^www\./', '', $h);
  if ($h !== '' && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $h)) return $h; return (string)($c['dominio'] ?? '');
}
function finEmailHtml(array $R) {
  $Z = finResumoUni($R, 'todas'); $t = $Z['t']; $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
  $l = function ($a, $b) use ($e) { return '<tr><td style="padding:6px 0;color:#6b655e">' . $e($a) . '</td><td style="padding:6px 0;text-align:right;font-weight:700;color:#3B3B3B">' . $e($b) . '</td></tr>'; };
  $f = ''; foreach (FIN_FORMAS as $k => $nm) if (abs($Z['F'][$k] ?? 0) > 0.004) $f .= $l($nm, finBrl($Z['F'][$k]));
  $u = ''; if (count($Z['U']) > 1) foreach ($Z['U'] as $x) $u .= $l(finNomeUni($x['u'], $R), finBrl($x['faturado']) . ' faturado · ' . finBrl($x['recebido']) . ' recebido');
  return '<!doctype html><html><body style="margin:0;background:#FAF3E9;font-family:Arial,Helvetica,sans-serif"><div style="max-width:520px;margin:0 auto;padding:24px 18px">'
    . '<div style="font-size:12px;font-weight:700;color:#E25B3F;letter-spacing:.05em">PLAY SPACE · FINANÇAS</div><h1 style="margin:6px 0 2px;font-size:24px;color:#3B3B3B">Resumo do dia</h1><div style="color:#6b655e;font-size:14px">' . $e(ucfirst(finDiaLongo($R['de']))) . '</div>'
    . '<table style="width:100%;margin-top:16px;border-collapse:collapse;font-size:15px">' . $l('Faturado', finBrl($t['faturado'])) . $l('Recebido', finBrl($t['recebido'])) . $l('Em aberto', finBrl($t['aberto'])) . $l('Crianças (visitas)', finInt($t['visitas']))
    . $l('Pacotes vendidos', finInt($t['pacN']) . ' · ' . finBrl($t['pacV'])) . $l('Descontos', finInt($t['descN']) . ' · ' . finBrl($t['descV'])) . $l('Cancelamentos', finInt($t['cancN'])) . $l('Notas fiscais', finInt($t['nfN']) . ' · ' . finBrl($t['nfV']) . ($t['recebido'] - $t['nfV'] > 0.004 ? ' · sem nota ' . finBrl($t['recebido'] - $t['nfV']) : '')) . '</table>'
    . ($f ? '<h2 style="font-size:15px;color:#3B3B3B;margin:18px 0 0">Formas de pagamento</h2><table style="width:100%;border-collapse:collapse;font-size:14px">' . $f . '</table>' : '')
    . ($u ? '<h2 style="font-size:15px;color:#3B3B3B;margin:18px 0 0">Cada unidade</h2><table style="width:100%;border-collapse:collapse;font-size:14px">' . $u . '</table>' : '')
    . '<p style="color:#6b655e;font-size:13px;margin-top:18px">O resumo completo (notas fiscais, produto a produto, descontos e cancelamentos) vai no PDF anexado.</p></div></body></html>';
}
// manda o resumo de um dia para a lista; devolve { ok, para, erro }
function finEmailMandar($dia, array $para) {
  if (!$para) falha('Cadastre pelo menos um e-mail.');
  $c = finEmailCfg(); $dom = finDominio($c); if ($dom === '') falha('Não deu para saber o endereço do site para mandar o e-mail. Abra as Finanças pelo endereço da loja e tente de novo.');
  $R = finResumo(['de' => $dia, 'ate' => $dia, 'prod' => '']); $pdf = finPdf($R, 'todas'); $html = finEmailHtml($R); $nome = finPdfNome($R);
  $de = 'nao-responda@' . $dom; $b = '=_ps_' . bin2hex(random_bytes(8));
  $assunto = '=?UTF-8?B?' . base64_encode('Play Space · resumo do dia ' . substr($dia, 8, 2) . '/' . substr($dia, 5, 2) . ' · ' . finBrl(finResumoUni($R, 'todas')['t']['faturado'])) . '?=';
  $cab = 'From: =?UTF-8?B?' . base64_encode('Play Space Finanças') . "?= <$de>\r\nReply-To: $de\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$b\"";
  $corpo = "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b\r\nContent-Type: application/pdf; name=\"$nome\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$nome\"\r\n\r\n" . chunk_split(base64_encode($pdf)) . "--$b--\r\n";
  $ok = function_exists('mail') && @mail(implode(', ', $para), $assunto, $corpo, $cab, '-f' . $de);
  return ['ok' => (bool)$ok, 'para' => $para, 'dia' => $dia, 'erro' => $ok ? '' : 'O servidor não conseguiu mandar o e-mail (a hospedagem precisa liberar o envio de e-mail pelo PHP).'];
}
// sozinho, uma vez por dia (chamado no pulso): marca o dia antes de mandar, para dois aparelhos não mandarem duas vezes
function finEmailAuto() {
  $bruto = finEmailBruto(); if ($bruto === null) return; $c = finEmailCfg(); if (empty($c['auto']) || !$c['para']) return;
  $alvo = finEmailAlvo(); if ((string)$c['ultimo'] >= $alvo) return;
  $n = $c; $n['ultimo'] = $alvo; $n['quando'] = date('Y-m-d H:i:s'); $n['res'] = 'mandando';
  $q = db()->prepare("UPDATE config SET valor = ? WHERE chave = 'fin_email' AND valor = ?"); $q->execute([json_encode($n, JSON_UNESCAPED_UNICODE), $bruto]); if ($q->rowCount() !== 1) return;
  try { $r = finEmailMandar($alvo, $c['para']); $n['res'] = $r['ok'] ? 'ok' : $r['erro']; } catch (Throwable $e) { $n['res'] = $e->getMessage(); }
  finEmailGuarda($n);
}
function finEmailPublico() { $c = finEmailCfg(); $cs = is_array($c['campos'] ?? null) ? array_values($c['campos']) : array_slice((array)$c['para'], 0, 4); while (count($cs) < 4) $cs[] = ''; return ['campos' => array_slice($cs, 0, 4), 'para' => $c['para'], 'auto' => !empty($c['auto']), 'ultimo' => (string)$c['ultimo'], 'res' => (string)$c['res'], 'quando' => (string)$c['quando'], 'hora' => finEmailHora(), 'fecha' => (int)(regra()['fecha'] ?? 22)]; }
// salvar: a lista e o "mandar sozinho". Ligado agora, o primeiro envio sozinho é o de hoje (no fim do dia), nunca o de ontem
function finEmailSalvar(array $in) {
  $c = finEmailCfg();
  if (isset($in['campos']) && is_array($in['campos'])) {   // 1.56: os 4 e-mails dos Ajustes (gestor, financeiro, outro, outro), cada um no seu lugar
    $cs = array_map(fn($x) => mb_strtolower(trim((string)$x)), array_slice(array_values($in['campos']), 0, 4)); while (count($cs) < 4) $cs[] = '';
    foreach ($cs as $e) if ($e !== '') finEmailLista($e);   // (confere cada um)
    $c['campos'] = $cs; $c['para'] = finEmailLista(implode("\n", $cs));
  } else { $c['para'] = finEmailLista($in['para'] ?? ''); unset($c['campos']); }
  $auto = !empty($in['auto']);
  if ($auto && !$c['para']) falha('Para mandar sozinho, cadastre pelo menos um e-mail.');
  if ($auto && empty($c['auto'])) $c['ultimo'] = max((string)$c['ultimo'], date('Y-m-d', strtotime(date('Y-m-d') . ' 12:00:00 -1 day')));   // (o de ontem não sai; o de hoje sai no fim do dia)
  $c['auto'] = $auto; $d = finDominio($c); if ($d !== '') $c['dominio'] = $d; finEmailGuarda($c); return finEmailPublico();
}
function finEmailDevido() { $c = finEmailCfg(); return !empty($c['auto']) && $c['para'] && (string)$c['ultimo'] < finEmailAlvo(); }   // (barato: o pulso pergunta antes de mandar)
