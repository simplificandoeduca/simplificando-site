<?php
/* =====================================================================
   Play Space — dados de exemplo: um dia cheio, para ver como a tela inicial fica com o espaço lotado.
   34 crianças fictícias: 30 no espaço, com nomes, responsáveis e horários de entrada diferentes (umas acabaram de
   entrar, outras estão acabando o tempo, na tolerância ou já com cobrança por atraso; duas são PCD e duas entraram
   com voucher) e 4 fora, uma delas com o cadastro feito agora há pouco e o atendimento por fazer. Mais as vendas e os
   registros do dia, uma entrada cancelada para a supervisão conferir, conversas no WhatsApp e alguns compromissos na agenda.
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
  $d = fn(int $dias, string $h) => date('Y-m-d', strtotime("$dias days")) . " $h:00";      // dias para frente (negativo: para trás) numa hora
  $hora = fn(string $q) => substr($q, 11, 5);
  $st = servicoTempo(); $P = periodo(); $V = (float)($st['v'] ?? 80); $SN = (string)($st['n'] ?? 'Recreação'); $tol = (int)regra()['tolerancia'];
  $VP = precoPcd(['pcd' => 1], $V); $rotPcd = 'PCD ' . pcdDesc() . '%';

  // quem está no espaço: nome, responsável, entrou há (min), períodos comprados, e o que tem de diferente
  // (pcd, voucher, obs, fam = mesma família de outra criança, sem = sem sapateira). Os horários cobrem todas as situações da lista.
  $dentro = [
    ['Ana Clara', 'Carla Menezes', 2, 1],                      ['Bento', 'Rodrigo Alves', 4, 1],
    ['Cecília', 'Fernanda Castro', 5, 1, ['voucher' => 1]],    ['Davi Luiz', 'Tiago Lima', 7, 1],
    ['Elisa', 'Patrícia Nunes', 8, 2],                         ['Felipe', 'Marcos Vieira', 10, 1],
    ['Gabriela', 'Aline Prado', 11, 1, ['obs' => 'Alergia a corante']], ['Theo', 'Rafael Lima', 12, 1],
    ['Heitor', 'Bruno Teixeira', 13, 1, ['sem' => 1]],         ['Isabela', 'Juliana Rocha', 14, 1, ['pcd' => 1]],
    ['Joaquim', 'André Martins', 15, 2],                       ['Lara', 'Camila Farias', 16, 1],
    ['Noah', 'Vanessa Duarte', 18, 1],                         ['Olívia', 'Renata Campos', 21, 1],
    ['Pedro', 'Gustavo Ribeiro', 22, 1, ['obs' => 'Não mexer no cabelo']], ['Rafaela', 'Daniela Moraes', 24, 1],
    ['Samuel', 'Leandro Pires', 25, 1, ['voucher' => 1]],      ['Valentina', 'Priscila Azevedo', 26, 1],
    ['Yuri', 'Eduardo Barros', 28, 1, ['sem' => 1]],           ['Zoe', 'Larissa Monteiro', 29, 1],
    ['Antônio', 'Felipe Cardoso', 31, 1],                      ['Bia', 'Simone Araújo', 33, 1],
    ['Caio', 'Roberto Nogueira', 35, 1, ['pcd' => 1]],         ['Dora', 'Luciana Freitas', 37, 1],
    ['João', 'Sérgio Lopes', 34, 2],                           ['Enzo', 'Marcelo Tavares', 42, 1],
    ['Flora', 'Bianca Sales', 47, 1, ['obs' => 'Pode fazer pintura no rosto']], ['Helena', 'Mariana Souza', 47, 2, ['obs' => 'Alergia a amendoim']],
    ['Íris', 'Mariana Souza', 47, 2, ['fam' => 'Helena']],     ['Gael', 'Paulo Henrique Cunha', 56, 1],
  ];
  $ins = $db->prepare("INSERT INTO criancas (nome, idade, resp, rel, tel, cpf, obs, pacote_min, usado_min, entrou, idface_id, foto, rosto, ativo, criado_em, pcd) VALUES (?, ?, ?, 'responsável', ?, ?, ?, ?, ?, ?, NULL, '', 0, 1, ?, ?)");
  $reg = $db->prepare('INSERT INTO registros (quando, tipo, crianca_id, nome, texto) VALUES (?, ?, ?, ?, ?)');
  $vis = $db->prepare('INSERT INTO visitas (crianca_id, entrou, saiu, debitado_min, origem) VALUES (?, ?, ?, ?, ?)');
  $ven = $db->prepare('INSERT INTO vendas (crianca_id, horas, minutos, valor, quando, por, itens, servico, auto, voucher, pcd, cheio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
  $msg = $db->prepare("INSERT INTO mensagens (crianca_id, de, texto, quando, por, auto, foto, lida, wa_id, erro) VALUES (?, ?, ?, ?, ?, 0, '', ?, '', '')");
  $vender = function (int $id, string $nome, int $min, float $valor, string $quando, string $itens, array $m = []) use ($ven, $reg, $por, $SN) {   // uma venda de recreação, com a linha do dia
    $ven->execute([$id, round($min / 60, 2), $min, $valor, $quando, empty($m['auto']) ? $por : null, $itens, $SN, empty($m['auto']) ? 0 : 1, (string)($m['voucher'] ?? ''), empty($m['pcd']) ? 0 : 1, $m['cheio'] ?? null]);
    $reg->execute([$quando, empty($m['auto']) ? 'venda' : 'cobr', $id, $nome, empty($m['auto']) ? $SN . ' · ' . dur($min) . ($itens !== '' ? ' · ' . $itens : '') . ' · ' . dinheiro($valor)
      : 'Nova cobrança por atraso: ficou além do tempo comprado · ' . $SN . ' +' . dur($min) . ' · ' . dinheiro($valor) . (empty($m['pcd']) ? '' : ' · ' . $m['rot']) . ' a receber na saída']);
  };
  $ids = []; $nomeId = []; $cpfDe = []; $telDe = []; $nDentro = 0; $sap = 0;
  foreach ($dentro as $i => $l) {
    [$nome, $resp, $ha, $per] = $l; $x = $l[4] ?? []; $pcd = !empty($x['pcd']); $entrou = $t($ha + ($i % 7) * 0.13);   // os segundos também variam
    if (isset($x['fam'])) { $cpf = $cpfDe[$x['fam']]; $tel = $telDe[$x['fam']]; } else { $cpf = exemploCpf($i + 1); $tel = '(11) 9 ' . (6100 + $i * 13) . '-' . (1000 + $i * 217); }
    $cpfDe[$nome] = $cpf; $telDe[$nome] = $tel;
    $min = $per * $P; $autos = max(0, (int)ceil(($ha - $tol) / $P) - $per);   // já passou do tempo comprado mais a tolerância: tem cobrança por atraso
    $ins->execute([$nome, 0, $resp, $tel, $cpf, (string)($x['obs'] ?? ''), $min + $autos * $P, 0, $entrou, $t(60 * 24 * 12), $pcd ? 1 : 0]);
    $id = (int)$db->lastInsertId(); $ids[] = $id; $nomeId[$nome] = $id; $nDentro++;
    if (empty($x['sem'])) { do { $sap++; } while ($sap <= 44 && donoSapateira($sap)); if ($sap <= 44) $db->prepare('UPDATE criancas SET sapateira = ? WHERE id = ?')->execute([$sap, $id]); }
    if (isset($x['voucher'])) $vender($id, $nome, $min, 0.0, $entrou, 'voucher', ['voucher' => 'sim', 'cheio' => $per * $V]);
    elseif ($pcd) $vender($id, $nome, $min, $per * $VP, $entrou, $rotPcd, ['pcd' => 1, 'cheio' => $per * $V]);
    else $vender($id, $nome, $min, $per * $V, $entrou, '');
    $reg->execute([$entrou, 'ent', $id, $nome, 'Entrada registrada']);
    for ($k = 1; $k <= $autos; $k++) $vender($id, $nome, $P, $pcd ? $VP : $V, date('Y-m-d H:i:s', strtotime($entrou) + (($per + $k - 1) * $P + $tol) * 60 + 1), 'cobrança por atraso' . ($pcd ? ' · ' . $rotPcd : ''), ['auto' => 1, 'pcd' => $pcd, 'cheio' => $pcd ? $V : null, 'rot' => $rotPcd]);
  }
  // dois serviços à parte, para o histórico e para o dia
  $outros = $db->prepare('INSERT INTO vendas (crianca_id, horas, minutos, valor, quando, por, itens, servico) VALUES (?, 0, 0, ?, ?, ?, ?, ?)');
  $outros->execute([$nomeId['Elisa'], 30, $t(8), $por, 'das 10h às 22h', 'Carrinho de bebê']); $reg->execute([$t(8), 'venda', $nomeId['Elisa'], 'Elisa', 'Carrinho de bebê · das 10h às 22h · R$ 30']);
  $outros->execute([$nomeId['Lara'], 15, $t(16), $por, dur(30), 'Carrinho fusquinha']); $reg->execute([$t(16), 'venda', $nomeId['Lara'], 'Lara', 'Carrinho fusquinha · ' . dur(30) . ' · R$ 15']);

  // três que não estão no espaço: nome, responsável
  foreach ([['Miguel', 'Carla Dias'], ['Nina', 'Tatiane Brito'], ['Otávio', 'Cláudio Reis']] as $i => $l) {
    $ins->execute([$l[0], 0, $l[1], '(11) 9 ' . (5400 + $i * 31) . '-' . (2000 + $i * 333), exemploCpf(60 + $i), '', 0, 0, null, $t(60 * 24 * 20), 0]);
    $id = (int)$db->lastInsertId(); $ids[] = $id; $nomeId[$l[0]] = $id;
  }
  // Nina veio hoje: 35 min com 30 comprados (dentro da tolerância) → um período
  $id = $nomeId['Nina']; $vender($id, 'Nina', $P, $V, $t(150), ''); $reg->execute([$t(150), 'ent', $id, 'Nina', 'Entrada registrada']);
  $vis->execute([$id, $t(150), $t(115), $P, 'mão']); $reg->execute([$t(115), 'sai', $id, 'Nina', 'Ficou ' . dur(35) . ' · ' . dur($P) . ' debitados']);
  $db->prepare('UPDATE criancas SET pacote_min = ?, usado_min = ? WHERE id = ?')->execute([$P, $P, $id]);
  // Otávio veio hoje: 1h10 com 30 comprados → três períodos, dois deles por atraso
  $id = $nomeId['Otávio']; $vender($id, 'Otávio', $P, $V, $t(125), ''); $reg->execute([$t(125), 'ent', $id, 'Otávio', 'Entrada registrada']);
  $vender($id, 'Otávio', $P, $V, $t(125 - $P - $tol), 'cobrança por atraso', ['auto' => 1]); $vender($id, 'Otávio', $P, $V, $t(125 - 2 * $P - $tol), 'cobrança por atraso', ['auto' => 1]);
  $vis->execute([$id, $t(125), $t(55), 3 * $P, 'mão']); $reg->execute([$t(55), 'sai', $id, 'Otávio', 'Ficou 1h10 · ' . dur(3 * $P) . ' debitados · 2 cobranças por atraso: ' . dinheiro(2 * $V) . ' a receber']);
  $db->prepare('UPDATE criancas SET pacote_min = ?, usado_min = ? WHERE id = ?')->execute([3 * $P, 3 * $P, $id]);
  // Miguel: a entrada de hoje foi cancelada aos 6 min, sem cobrar; fica para a supervisão conferir. Veio também ontem.
  $id = $nomeId['Miguel'];
  $reg->execute([$t(96), 'venda', $id, 'Miguel', $SN . ' · ' . dur($P) . ' · ' . dinheiro($V) . ' · cancelada']); $reg->execute([$t(96), 'ent', $id, 'Miguel', 'Entrada registrada']);
  $reg->execute([$t(90), 'canc', $id, 'Miguel', 'Entrada das ' . $hora($t(96)) . ' cancelada aos 6 min · não cobrado: ' . $SN . ' · ' . dur($P) . ' (' . dinheiro($V) . ') · Motivo: A criança chorou e não quis ficar · por João']);
  $db->prepare('INSERT INTO cancelamentos (crianca_id, nome, resp, entrou, quando, minutos, valor, itens, motivo, por, por_nome, papel, fora) VALUES (?, ?, ?, ?, ?, 6, ?, ?, ?, ?, ?, ?, 0)')
    ->execute([$id, 'Miguel', 'Carla Dias', $t(96), $t(90), $V, json_encode([['n' => $SN . ' · ' . dur($P), 'm' => $P, 'v' => $V]], JSON_UNESCAPED_UNICODE), 'A criança chorou e não quis ficar', $por, 'João (exemplo)', 'atendente']);
  $ven->execute([$id, round($P / 60, 2), $P, $V, $d(-1, '14:20'), $por, '', $SN, 0, '', 0, null]); $vis->execute([$id, $d(-1, '14:20'), $d(-1, '14:52'), $P, 'mão']);
  $db->prepare('UPDATE criancas SET pacote_min = ?, usado_min = ? WHERE id = ?')->execute([$P, $P, $id]);

  // Marina: o cadastro foi feito agora há pouco e o atendimento ficou por fazer (aparece em "Falta finalizar" e abre direto no atendimento)
  $ins->execute(['Marina', 0, 'Marcelo Antunes', '(11) 9 5493-2999', exemploCpf(63), '', 0, 0, null, $t(6), 0]); $ids[] = (int)$db->lastInsertId(); $nomeId['Marina'] = (int)$db->lastInsertId();

  // conversas no WhatsApp (nenhuma mensagem automática: só o que a família e a equipe escreveram)
  $id = $nomeId['Helena'];
  $msg->execute([$id, 'ela', 'Oi! Esqueci de avisar: a Helena não pode comer nada com amendoim, tá?', $t(44), '', 1]);
  $msg->execute([$id, 'nos', 'Pode deixar, Mariana! Já está anotado na ficha dela 💛', $t(41), 'Camila', 1]);
  $msg->execute([$nomeId['Theo'], 'ela', 'Oi! Vocês têm oficina no sábado?', $t(4), '', 0]);   // o pai perguntou e ninguém respondeu ainda
  $id = $nomeId['Miguel'];
  $msg->execute([$id, 'ela', 'Obrigada! Ele adorou a piscina de bolinhas 😄', $d(-1, '16:40'), '', 1]);
  $msg->execute([$id, 'nos', 'Que bom, Carla! Sábado tem oficina de slime às 15h, o Miguel vai adorar 💛', $d(-1, '16:55'), 'Beatriz', 1]);
  $msg->execute([$id, 'ela', 'Que legal! Vamos sim.', $d(-1, '17:10'), '', 1]);

  // agenda
  $comp = $db->prepare('INSERT INTO compromissos (dia, hora, titulo, tipo, sub, obs, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?)'); $compIds = [];
  foreach ([[2, '15:00', 'Oficina de slime', 'oficina', 'Oficina', 'Até 12 crianças · levar aventais'], [5, '16:00', 'Festa da Lara (6 anos)', 'festa', 'Festa', 'Salão reservado das 15h às 19h'],
            [7, '10:00', 'Reunião com a escola Pequeno Príncipe', 'compromisso', 'Compromisso', 'Parceria para as férias'], [9, '09:00', 'Post: horário das férias', 'postagem', 'Postagem', 'Arte com o castelinho'],
            [12, '15:30', 'Oficina de pintura', 'oficina', 'Oficina', ''], [-3, '14:00', 'Oficina de massinha', 'oficina', 'Oficina', 'Feita · 9 crianças']] as $c) {
    $comp->execute([date('Y-m-d', strtotime("$c[0] days")), $c[1], $c[2], $c[3], $c[4], $c[5], agora()]); $compIds[] = (int)$db->lastInsertId();
  }
  gravarCfg('exemplo', ['criancas' => $ids, 'compromissos' => $compIds, 'em' => agora()]);
  return ['criancas' => count($ids), 'dentro' => $nDentro];
}

function exemplosApagar(): void {
  $e = exemplosAtivos(); if (!$e) return;
  $db = db(); $ids = array_map('intval', $e['criancas']); $marcas = implode(',', array_fill(0, count($ids), '?'));
  $q = $db->prepare("SELECT produto_id, qtd FROM vendas WHERE produto_id IS NOT NULL AND crianca_id IN ($marcas)"); $q->execute($ids);
  foreach ($q->fetchAll() as $v) devolverEstoque($v);   // produto vendido para uma criança de teste: volta para o estoque (a venda nunca existiu)
  foreach (['visitas', 'vendas', 'registros', 'mensagens', 'batidas', 'cancelamentos', 'criancas'] as $tabela) $db->prepare("DELETE FROM $tabela WHERE " . ($tabela === 'criancas' ? 'id' : 'crianca_id') . " IN ($marcas)")->execute($ids);
  foreach ($ids as $id) { apagarCfg('mandar_' . $id); apagarCfg('foto_' . $id); apagarCfg('renomear_' . $id); apagarCfg('regra_' . $id); apagarCfg('desregra_' . $id); apagarCfg('regra_ok_' . $id); }
  $cids = array_map('intval', $e['compromissos'] ?? []);
  if ($cids) $db->prepare('DELETE FROM compromissos WHERE id IN (' . implode(',', array_fill(0, count($cids), '?')) . ')')->execute($cids);
  apagarCfg('exemplo');
}
