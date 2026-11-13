<?php
/* =====================================================================
   Play Space — dados de exemplo: um dia cheio, para ver como a tela inicial fica com o espaço lotado.
   10 crianças fictícias, todas no espaço, com nomes, responsáveis e horários de entrada diferentes (umas acabaram de
   entrar, outras estão acabando o tempo, na tolerância ou já com cobrança por atraso; uma é PCD, uma entrou com voucher
   e dois irmãos têm pacote de horas). Mais as vendas e os registros do dia, três carrinhos fora da loja, uma conversa
   no WhatsApp e alguns compromissos na agenda.
   Tudo o que entra aqui fica anotado em config 'exemplo' e sai inteiro com exemplosApagar().
   Nada é mandado ao iDFace nem ao WhatsApp: só entra no banco.
   ===================================================================== */
declare(strict_types=1);

function exemplosAtivos(): ?array { $e = cfg('exemplo', null); return is_array($e) && !empty($e['criancas']) ? $e : null; }
// um CPF fictício, mas com os dígitos certos (para o cadastro achar o cliente e aceitar um irmão)
function exemploCpf(int $n): string {
  $d = str_split(str_pad((string)(100000000 + ($n * 7654321) % 899999999), 9, '0', STR_PAD_LEFT));
  for ($k = 9; $k <= 10; $k++) { $t = 0; for ($i = 0; $i < $k; $i++) $t += (int)$d[$i] * ($k + 1 - $i); $r = ($t * 10) % 11; $d[] = (string)($r === 10 ? 0 : $r); }
  $s = implode('', $d); return substr($s, 0, 3) . '.' . substr($s, 3, 3) . '.' . substr($s, 6, 3) . '-' . substr($s, 9, 2);
}

function exemplosCarregar(?int $por): array {
  if (exemplosAtivos()) falha('Os exemplos já estão carregados. Apague antes de carregar de novo.');
  $db = db();
  $t = fn(float $minAtras) => date('Y-m-d H:i:s', time() - (int)round($minAtras * 60));    // minutos atrás
  $st = servicoTempo(); $P = periodo(); $V = (float)($st['v'] ?? 80); $SN = (string)($st['n'] ?? 'RECREAÇÃO'); $tol = (int)regra()['tolerancia'];
  $VP = precoPcd(['pcd' => 1], $V); $rotPcd = 'PCD ' . pcdDesc() . '%';
  // quem tem pacote de horas (pac): o pacote de 5 h da tabela, comprado há três dias. O tempo sai do pacote, sem cobrança por atraso
  $pk = null; foreach ((array)regra()['pacotes'] as $p) if ((int)($p['h'] ?? 0) === 5 && tipoPacote($p) === 'seco') { $pk = $p; if (abs((float)$p['v'] - 420) < 0.005) break; }
  $pacH = $pk ? (int)$pk['h'] : 5; $pacV = $pk ? (float)$pk['v'] : 420.0; $pacServ = $pk ? servicoDoPacote($pk) : ''; $pacItens = $pk ? itensDoPacote($pk) : 'seco';

  // quem está no espaço: nome, responsável, entrou há (min), períodos comprados, e o que tem de diferente
  // (pcd, voucher, pac = tem pacote de horas, obs, fam = mesma família de outra criança, sem = sem sapateira).
  // São 10, e os horários cobrem as situações da lista: acabou de entrar, acabando o tempo, na tolerância e já com cobrança por atraso.
  // Há uma família com duas crianças (ficam juntas na lista).
  $dentro = [
    ['Ana Clara', 'Carla Menezes', 2, 1, ['np' => 1]],                              ['Cecília', 'Fernanda Castro', 5, 1, ['voucher' => 1]],
    ['Elisa', 'Patrícia Nunes', 8, 2, ['pac' => 1]],                     ['Felipe', 'Patrícia Nunes', 8, 2, ['fam' => 'Elisa', 'pac' => 1]],
    ['Gabriela', 'Aline Prado', 14, 1, ['obs' => 'ALERGIA A CORANTE']],  ['Isabela', 'Juliana Rocha', 18, 1, ['pcd' => 1]],
    ['Lara', 'Camila Farias', 24, 1],                                    ['Pedro', 'Gustavo Ribeiro', 33, 1, ['sem' => 1, 'np' => 1]],
    ['Helena', 'Mariana Souza', 47, 1, ['obs' => 'ALERGIA A AMENDOIM']], ['Gael', 'Paulo Henrique Cunha', 72, 1],
  ];
  $ins = $db->prepare("INSERT INTO criancas (nome, idade, resp, rel, tel, cpf, obs, pacote_min, usado_min, entrou, idface_id, foto, rosto, ativo, criado_em, pcd) VALUES (?, ?, ?, 'responsável', ?, ?, ?, ?, ?, ?, NULL, '', 0, 1, ?, ?)");
  $reg = $db->prepare('INSERT INTO registros (quando, tipo, crianca_id, nome, texto) VALUES (?, ?, ?, ?, ?)');
  $ven = $db->prepare('INSERT INTO vendas (crianca_id, horas, minutos, valor, quando, por, itens, servico, auto, voucher, pcd, cheio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
  $msg = $db->prepare("INSERT INTO mensagens (crianca_id, de, texto, quando, por, auto, foto, lida, wa_id, erro) VALUES (?, ?, ?, ?, ?, 0, '', ?, '', '')");
  $pagar = $db->prepare('INSERT INTO pagamentos (venda_id, crianca_id, forma, valor, quando, por) VALUES (?, ?, ?, ?, ?, ?)'); $formas = array_keys(FORMAS_PAGAMENTO);
  $vender = function (int $id, string $nome, int $min, float $valor, string $quando, string $itens, array $m = []) use ($db, $ven, $reg, $por, $SN, $pagar, $formas) {   // uma venda de recreação, com a linha do dia
    $ven->execute([$id, round($min / 60, 2), $min, $valor, $quando, empty($m['auto']) ? $por : null, $itens, $SN, empty($m['auto']) ? 0 : 1, (string)($m['voucher'] ?? ''), empty($m['pcd']) ? 0 : 1, $m['cheio'] ?? null]);
    if (empty($m['auto']) && $valor > 0 && empty($m['np'])) $pagar->execute([(int)$db->lastInsertId(), $id, $formas[$id % count($formas)], $valor, $quando, $por]);   // paga na hora da venda; a cobrança por atraso fica a receber (1.02: np = ficou de pagar na saída)
    $reg->execute([$quando, empty($m['auto']) ? 'venda' : 'cobr', $id, $nome, empty($m['auto']) ? $SN . ' · ' . dur($min) . ($itens !== '' ? ' · ' . $itens : '') . ' · ' . dinheiro($valor)
      : 'Nova cobrança por atraso: ficou além do tempo comprado · ' . $SN . ' +' . dur($min) . ' · ' . dinheiro($valor) . (empty($m['pcd']) ? '' : ' · ' . $m['rot']) . ' a receber na saída']);
  };
  $ids = []; $nomeId = []; $cpfDe = []; $telDe = []; $nDentro = 0; $sap = 0;
  foreach ($dentro as $i => $l) {
    [$nome, $resp, $ha, $per] = $l; $x = $l[4] ?? []; $pcd = !empty($x['pcd']); $entrou = $t($ha + ($i % 7) * 0.13);   // os segundos também variam
    if (isset($x['fam'])) { $cpf = $cpfDe[$x['fam']]; $tel = $telDe[$x['fam']]; } else { $cpf = exemploCpf($i + 1); $tel = '(11) 9 ' . (6100 + $i * 13) . '-' . (1000 + $i * 217); }
    $cpfDe[$nome] = $cpf; $telDe[$nome] = $tel;
    $min = $per * $P; $autos = max(0, (int)ceil(($ha - $tol) / $P) - $per);   // já passou do tempo comprado mais a tolerância: tem cobrança por atraso
    $pac = !empty($x['pac']); if ($pac) { $min = $pacH * 60; $autos = 0; }
    $ins->execute([$nome, 0, $resp, $tel, $cpf, (string)($x['obs'] ?? ''), $min + $autos * $P, 0, $entrou, $t(60 * 24 * 12), $pcd ? 1 : 0]);
    $id = (int)$db->lastInsertId(); $ids[] = $id; $nomeId[$nome] = $id; $nDentro++;
    if (empty($x['sem'])) { do { $sap++; } while ($sap <= 44 && donoSapateira($sap)); if ($sap <= 44) $db->prepare('UPDATE criancas SET sapateira = ? WHERE id = ?')->execute([$sap, $id]); }
    if ($pac) $ven->execute([$id, $pacH, $min, $pacV, $t(60 * 24 * 3 + $i), $por, $pacItens, $pacServ, 0, '', 0, null]);
    elseif (isset($x['voucher'])) $vender($id, $nome, $min, 0.0, $entrou, 'voucher', ['voucher' => 'sim', 'cheio' => $per * $V]);
    elseif ($pcd) $vender($id, $nome, $min, $per * $VP, $entrou, $rotPcd, ['pcd' => 1, 'cheio' => $per * $V]);
    else $vender($id, $nome, $min, $per * $V, $entrou, '', empty($x['np']) ? [] : ['np' => 1]);
    $reg->execute([$entrou, 'ent', $id, $nome, 'Entrada registrada']);
    for ($k = 1; $k <= $autos; $k++) $vender($id, $nome, $P, $pcd ? $VP : $V, date('Y-m-d H:i:s', strtotime($entrou) + (($per + $k - 1) * $P + $tol) * 60 + 1), 'cobrança por atraso' . ($pcd ? ' · ' . $rotPcd : ''), ['auto' => 1, 'pcd' => $pcd, 'cheio' => $pcd ? $V : null, 'rot' => $rotPcd]);
  }

  // o que saiu da loja e ainda não voltou (fica na lista até "Devolveu"): um fusquinha dentro do tempo, um que já passou e um carrinho de bebê
  $alu = $db->prepare('INSERT INTO vendas (crianca_id, horas, minutos, valor, quando, por, itens, servico, alug, alug_min, qtd) VALUES (?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?)');
  $sair = function (string $nome, array $sv, float $ha, int $q = 1, bool $np = false) use ($db, $alu, $reg, &$nomeId, $por, $t, $pagar, $formas) {
    $porTempo = ($sv['modo'] ?? 'fixo') === 'periodo'; $m = $porTempo ? $q * max(1, (int)$sv['min']) : 0; $obs = (string)($sv['obs'] ?? ''); $valor = $q * (float)$sv['v'];
    $itens = $porTempo ? dur($m) : (($q > 1 ? $q . ' unidades' . ($obs !== '' ? ' · ' : '') : '') . $obs);
    $alu->execute([$nomeId[$nome], $valor, $t($ha), $por, $itens, (string)$sv['n'], (string)$sv['id'], $m, $porTempo ? 1 : $q]);
    if (!$np) $pagar->execute([(int)$db->lastInsertId(), $nomeId[$nome], $formas[$nomeId[$nome] % count($formas)], $valor, $t($ha), $por]);   // 1.02: np = ficou de pagar na devolução
    $reg->execute([$t($ha), 'venda', $nomeId[$nome], $nome, $sv['n'] . ($itens !== '' ? ' · ' . $itens : '') . ' · ' . dinheiro($valor)]);
  };
  $porTempo = null; $semHora = null;
  foreach ((array)regra()['servicos'] as $sv) if (!empty($sv['volta'])) { if (($sv['modo'] ?? 'fixo') === 'periodo') $porTempo = $porTempo ?? $sv; else $semHora = $semHora ?? $sv; }
  if ($porTempo) { $sair('Lara', $porTempo, 16); $sair('Gael', $porTempo, (int)$porTempo['min'] + 13, 1, true); }
  if ($semHora) $sair('Helena', $semHora, 45);
  // uma conversa no WhatsApp (nenhuma mensagem automática: só o que a família e a equipe escreveram)
  $id = $nomeId['Helena'];
  $msg->execute([$id, 'ela', 'Oi! Esqueci de avisar: a Helena não pode comer nada com amendoim, tá?', $t(44), '', 1]);
  $msg->execute([$id, 'nos', 'Pode deixar, Mariana! Já está anotado na ficha dela 💛', $t(41), 'Camila', 1]);

  // agenda
  $comp = $db->prepare('INSERT INTO compromissos (dia, hora, titulo, tipo, sub, obs, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?)'); $compIds = [];
  foreach ([[0, '16:30', 'Contação de histórias', 'oficina', 'Oficina', 'Na sala do castelinho'], [1, '15:00', 'Oficina de pintura no rosto', 'oficina', 'Oficina', ''],
            [2, '15:00', 'Oficina de slime', 'oficina', 'Oficina', 'Até 12 crianças · levar aventais'], [5, '16:00', 'Festa da Lara (6 anos)', 'festa', 'Festa', 'Salão reservado das 15h às 19h'],
            [7, '10:00', 'Reunião com a escola Pequeno Príncipe', 'compromisso', 'Compromisso', 'Parceria para as férias'], [9, '09:00', 'Post: horário das férias', 'postagem', 'Postagem', 'Arte com o castelinho'],
            [12, '15:30', 'Oficina de pintura', 'oficina', 'Oficina', ''], [-3, '14:00', 'Oficina de massinha', 'oficina', 'Oficina', 'Feita · 9 crianças']] as $c) {
    $comp->execute([date('Y-m-d', strtotime("$c[0] days")), $c[1], emMaiusculas($c[2]), $c[3], $c[4], emMaiusculas($c[5]), agora()]); $compIds[] = (int)$db->lastInsertId();
  }
  nomesEmMaiusculas($db, $ids);   // os nomes do teste também ficam em maiúsculas, como os de verdade
  if (($uni = unidadeDoPedido()) !== null && $ids) { $marcas = implode(',', array_fill(0, count($ids), '?'));   // 1.06: o teste nasce na unidade do aparelho que pediu
    foreach (['criancas' => 'id', 'vendas' => 'crianca_id', 'registros' => 'crianca_id'] as $tabela => $col) $db->prepare("UPDATE $tabela SET unidade_id = ? WHERE $col IN ($marcas)")->execute(array_merge([$uni], $ids)); }
  gravarCfg('exemplo', ['criancas' => $ids, 'compromissos' => $compIds, 'em' => agora()]);
  return ['criancas' => count($ids), 'dentro' => $nDentro];
}

function exemplosApagar(): void {
  $e = exemplosAtivos(); if (!$e) return;
  $db = db(); $ids = array_map('intval', $e['criancas']); $marcas = implode(',', array_fill(0, count($ids), '?'));
  $q = $db->prepare("SELECT produto_id, qtd FROM vendas WHERE produto_id IS NOT NULL AND crianca_id IN ($marcas)"); $q->execute($ids);
  foreach ($q->fetchAll() as $v) devolverEstoque($v);   // produto vendido para uma criança de teste: volta para o estoque (a venda nunca existiu)
  foreach (['cupons', 'pagamentos', 'visitas', 'vendas', 'registros', 'mensagens', 'batidas', 'cancelamentos', 'criancas'] as $tabela) $db->prepare("DELETE FROM $tabela WHERE " . ($tabela === 'criancas' ? 'id' : 'crianca_id') . " IN ($marcas)")->execute($ids);
  try { $db->prepare("DELETE FROM papeis WHERE crianca_id IN ($marcas)")->execute($ids); } catch (Throwable $x) { }   // 1.14: os cupons guardados das crianças de teste
  foreach ($ids as $id) { apagarCfg('mandar_' . $id); apagarCfg('foto_' . $id); apagarCfg('renomear_' . $id); apagarCfg('regra_' . $id); apagarCfg('desregra_' . $id); apagarCfg('regra_ok_' . $id); }
  $cids = array_map('intval', $e['compromissos'] ?? []);
  if ($cids) $db->prepare('DELETE FROM compromissos WHERE id IN (' . implode(',', array_fill(0, count($cids), '?')) . ')')->execute($cids);
  apagarCfg('exemplo');
}
