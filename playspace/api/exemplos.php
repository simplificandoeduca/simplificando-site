<?php
/* =====================================================================
   Play Space — dados de exemplo: 3 crianças (2 no espaço), vendas, registros do dia, conversas de hoje e de ontem
   no WhatsApp e alguns compromissos na agenda. Para a equipe conhecer o sistema antes de usar de verdade.
   Tudo o que entra aqui fica anotado em config 'exemplo' e sai inteiro com exemplosApagar().
   Nada é mandado ao iDFace nem ao WhatsApp: só entra no banco.
   ===================================================================== */
declare(strict_types=1);

function exemplosAtivos(): ?array { $e = cfg('exemplo', null); return is_array($e) && !empty($e['criancas']) ? $e : null; }

function exemplosCarregar(?int $por): array {
  if (exemplosAtivos()) falha('Os exemplos já estão carregados. Apague antes de carregar de novo.');
  $db = db();
  $t = fn(int $minAtras) => date('Y-m-d H:i:s', time() - $minAtras * 60);               // minutos atrás
  $d = fn(int $dias, string $h) => date('Y-m-d', strtotime("$dias days")) . " $h:00";      // dias para frente (negativo: para trás) numa hora
  $hoje = date('Y-m-d');
  // nome, idade, responsável, relação, telefone, cpf, pacote (h), usado (min), entrou há (min, 0 = fora), tem rosto
  $lista = [
    ['Helena', 5, 'Mariana Souza', 'mãe', '(11) 9 8765-4321', '529.982.247-25', 10, 420, 121, 1],   // no espaço há 2 h, com tempo comprado até 3 h
    ['Theo', 4, 'Rafael Lima', 'pai', '(11) 9 7654-3210', '111.444.777-35', 5, 270, 33, 1],         // no espaço há 33 min com 30 comprados: está na tolerância; passou de 9 min, nasce a cobrança por atraso
    ['Miguel', 5, 'Carla Dias', 'mãe', '(11) 9 5432-1098', '390.533.447-05', 10, 600, 0, 0],        // fora, pacote acabou, sem foto
  ];
  $ids = []; $nomeId = [];
  $ins = $db->prepare('INSERT INTO criancas (nome, idade, resp, rel, tel, cpf, obs, pacote_min, usado_min, entrou, idface_id, foto, rosto, ativo, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, 1, ?)');
  foreach ($lista as $l) {
    [$nome, $idade, $resp, $rel, $tel, $cpf, $pac, $usado, $dentro, $rosto] = $l;
    $ins->execute([$nome, $idade, $resp, $rel, $tel, $cpf, 'exemplo', $pac * 60, $usado, $dentro ? $t($dentro) : null, '', $rosto, $t(60 * 24 * 20)]);
    $id = (int)$db->lastInsertId(); $ids[] = $id; $nomeId[$nome] = $id;
  }
  // quem está no espaço já tem o sapato guardado (se o número estiver livre)
  foreach (['Helena' => 12, 'Theo' => 7] as $nome => $sap) if (!donoSapateira($sap)) $db->prepare('UPDATE criancas SET sapateira = ? WHERE id = ?')->execute([$sap, $nomeId[$nome]]);
  $reg = $db->prepare('INSERT INTO registros (quando, tipo, crianca_id, nome, texto) VALUES (?, ?, ?, ?, ?)');
  $vis = $db->prepare('INSERT INTO visitas (crianca_id, entrou, saiu, debitado_min, origem) VALUES (?, ?, ?, ?, ?)');
  $ven = $db->prepare('INSERT INTO vendas (crianca_id, horas, minutos, valor, quando, por, itens, servico) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');   // horas, minutos de brincar, valor, quando, quem, detalhe, serviço
  $msg = $db->prepare('INSERT INTO mensagens (crianca_id, de, texto, quando, por, auto, foto, lida, wa_id, erro) VALUES (?, ?, ?, ?, ?, ?, \'\', ?, \'\', \'\')');
  $hm = fn(int $m) => $m <= 0 ? '0h' : ($m < 60 ? '0h' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) : intdiv($m, 60) . 'h' . ($m % 60 ? str_pad((string)($m % 60), 2, '0', STR_PAD_LEFT) : ''));
  $hora = fn(string $q) => substr($q, 11, 5);
  // quem está dentro: a entrada marcada na tela (nenhum aviso automático vai para a família)
  foreach ($lista as $l) {
    [$nome, , , , , , , , $dentro] = $l; if (!$dentro) continue;
    $reg->execute([$t($dentro), 'ent', $nomeId[$nome], $nome, 'Entrada registrada']);
  }
  // Helena: a mãe perguntou e a equipe respondeu; venda de hoje
  $id = $nomeId['Helena'];
  $msg->execute([$id, 'ela', 'Oi! Esqueci de avisar: a Helena não pode comer nada com amendoim, tá?', $t(110), '', 0, 1]);
  $msg->execute([$id, 'nos', 'Pode deixar, Mariana! Já está anotado na ficha dela 💛', $t(105), 'Camila', 0, 1]);
  $ven->execute([$id, 2, 120, 320, $t(125), $por, '', 'Recreação']); $reg->execute([$t(125), 'venda', $id, 'Helena', 'Recreação · 2h · R$ 320']);
  $ven->execute([$id, 0, 0, 30, $t(123), $por, 'das 10h às 22h', 'Carrinho de bebê']); $reg->execute([$t(123), 'venda', $id, 'Helena', 'Carrinho de bebê · das 10h às 22h · R$ 30']);   // um serviço à parte, para o histórico do cliente
  $ven->execute([$id, 8, 480, 1280, $d(-10, '14:50'), $por, '', 'Recreação']);
  // as compras antigas dos outros dois (aparecem no Banco de horas e no histórico)
  $ven->execute([$nomeId['Theo'], 5, 300, 800, $d(-9, '13:50'), $por, '', 'Recreação']); $ven->execute([$nomeId['Theo'], 0, 0, 30, $d(-9, '13:52'), $por, '1h', 'Carrinho fusquinha']);
  $ven->execute([$nomeId['Miguel'], 10, 600, 1600, $d(-6, '12:55'), $por, '', 'Recreação']);
  // Miguel: voltou hoje, a entrada foi cancelada aos 6 min sem cobrar; fica para a supervisão conferir
  $id = $nomeId['Miguel'];
  $reg->execute([$t(96), 'venda', $id, 'Miguel', 'Recreação · 30 min · R$ 80 · cancelada']); $reg->execute([$t(96), 'ent', $id, 'Miguel', 'Entrada registrada']);
  $reg->execute([$t(90), 'canc', $id, 'Miguel', 'Entrada das ' . $hora($t(96)) . ' cancelada aos 6 min · não cobrado: Recreação · 30 min (R$ 80) · Motivo: A criança chorou e não quis ficar · por João']);
  $db->prepare('INSERT INTO cancelamentos (crianca_id, nome, resp, entrou, quando, minutos, valor, itens, motivo, por, por_nome, papel, fora) VALUES (?, ?, ?, ?, ?, 6, 80, ?, ?, ?, ?, ?, 0)')
    ->execute([$id, 'Miguel', 'Carla Dias', $t(96), $t(90), json_encode([['n' => 'Recreação · 30 min', 'm' => 30, 'v' => 80]], JSON_UNESCAPED_UNICODE), 'A criança chorou e não quis ficar', $por, 'João (exemplo)', 'atendente']);
  // Theo: o pai perguntou e ninguém respondeu ainda
  $msg->execute([$nomeId['Theo'], 'ela', 'Oi! Vocês têm oficina no sábado?', $t(4), '', 0, 0]);
  // Miguel: veio ontem, o pacote acabou; hoje a equipe ofereceu renovar
  $id = $nomeId['Miguel']; $vis->execute([$id, $d(-1, '14:20'), $d(-1, '16:10'), 120, 'mão']);
  $msg->execute([$id, 'ela', 'Obrigada! Ele adorou a piscina de bolinhas 😄', $d(-1, '16:40'), '', 0, 1]);
  $msg->execute([$id, 'nos', 'Oi, Carla! As horas do Miguel acabaram ontem. Quer renovar? A recreação é R$ 80 a cada 30 minutos 💛', $t(150), 'Beatriz', 0, 1]);
  $msg->execute([$id, 'ela', 'Pode ser 1 hora! Passo aí amanhã pra acertar.', $t(140), '', 0, 1]);
  // visitas de dias anteriores, para a ficha ter histórico
  foreach ([['Helena', -3, '14:00', '16:10', 150], ['Helena', -7, '10:30', '12:38', 120], ['Helena', -10, '15:00', '17:40', 150], ['Miguel', -2, '14:20', '17:00', 180], ['Miguel', -6, '13:00', '16:30', 210],
            ['Theo', -2, '15:00', '17:30', 150], ['Theo', -9, '14:00', '16:08', 120]] as $v)
    $vis->execute([$nomeId[$v[0]], $d($v[1], $v[2]), $d($v[1], $v[3]), $v[4], 'mão']);
  // agenda
  $comp = $db->prepare('INSERT INTO compromissos (dia, hora, titulo, tipo, sub, obs, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?)'); $compIds = [];
  foreach ([[2, '15:00', 'Oficina de slime', 'oficina', 'Oficina', 'Até 12 crianças · levar aventais'], [5, '16:00', 'Festa da Lara (6 anos)', 'festa', 'Festa', 'Salão reservado das 15h às 19h'],
            [7, '10:00', 'Reunião com a escola Pequeno Príncipe', 'compromisso', 'Compromisso', 'Parceria para as férias'], [9, '09:00', 'Post: horário das férias', 'postagem', 'Postagem', 'Arte com o castelinho'],
            [12, '15:30', 'Oficina de pintura', 'oficina', 'Oficina', ''], [-3, '14:00', 'Oficina de massinha', 'oficina', 'Oficina', 'Feita · 9 crianças']] as $c) {
    $comp->execute([date('Y-m-d', strtotime("$c[0] days")), $c[1], $c[2], $c[3], $c[4], $c[5], agora()]); $compIds[] = (int)$db->lastInsertId();
  }
  gravarCfg('exemplo', ['criancas' => $ids, 'compromissos' => $compIds, 'em' => agora()]);
  return ['criancas' => count($ids)];
}

function exemplosApagar(): void {
  $e = exemplosAtivos(); if (!$e) return;
  $db = db(); $ids = array_map('intval', $e['criancas']); $marcas = implode(',', array_fill(0, count($ids), '?'));
  foreach (['visitas', 'vendas', 'registros', 'mensagens', 'batidas', 'cancelamentos', 'criancas'] as $tabela) $db->prepare("DELETE FROM $tabela WHERE " . ($tabela === 'criancas' ? 'id' : 'crianca_id') . " IN ($marcas)")->execute($ids);
  foreach ($ids as $id) { apagarCfg('mandar_' . $id); apagarCfg('foto_' . $id); apagarCfg('renomear_' . $id); }
  $cids = array_map('intval', $e['compromissos'] ?? []);
  if ($cids) $db->prepare('DELETE FROM compromissos WHERE id IN (' . implode(',', array_fill(0, count($cids), '?')) . ')')->execute($cids);
  apagarCfg('exemplo');
}
