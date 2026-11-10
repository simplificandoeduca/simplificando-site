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
    ['Helena', 5, 'Mariana Souza', 'mãe', '(11) 9 8765-4321', '123.456.789-00', 10, 418, 121, 1],   // no espaço, em dia
    ['Theo', 4, 'Rafael Lima', 'pai', '(11) 9 7654-3210', '234.567.890-11', 5, 278, 12, 1],         // no espaço, pacote acabando
    ['Miguel', 5, 'Carla Dias', 'mãe', '(11) 9 5432-1098', '456.789.012-33', 10, 600, 0, 0],        // fora, pacote acabou, sem foto
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
  $ven = $db->prepare('INSERT INTO vendas (crianca_id, horas, valor, quando, por, itens) VALUES (?, ?, ?, ?, ?, ?)');
  $msg = $db->prepare('INSERT INTO mensagens (crianca_id, de, texto, quando, por, auto, foto, lida, wa_id, erro) VALUES (?, ?, ?, ?, ?, ?, \'\', ?, \'\', \'\')');
  $hm = fn(int $m) => $m <= 0 ? '0h' : ($m < 60 ? '0h' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) : intdiv($m, 60) . 'h' . ($m % 60 ? str_pad((string)($m % 60), 2, '0', STR_PAD_LEFT) : ''));
  $hora = fn(string $q) => substr($q, 11, 5);
  // quem está dentro: rosto lido na entrada + aviso automático à família
  foreach ($lista as $l) {
    [$nome, , $resp, , , , $pac, $usado, $dentro] = $l; if (!$dentro) continue;
    $q = $t($dentro); $id = $nomeId[$nome];
    $reg->execute([$q, 'ent', $id, $nome, 'Rosto reconhecido na entrada']);
    $msg->execute([$id, 'nos', 'Oi, ' . explode(' ', $resp)[0] . '! ' . $nome . ' chegou na Play Space às ' . $hora($q) . ' 💛 Tem ' . $hm($pac * 60 - $usado) . ' no pacote. Qualquer coisa a gente te chama por aqui.', $q, 'sistema', 1, 1]);
  }
  // Helena: a mãe respondeu e a equipe também; venda de hoje
  $id = $nomeId['Helena'];
  $msg->execute([$id, 'ela', 'Obrigada! Ela vai ficar até umas 16h, tudo bem?', $t(110), '', 0, 1]);
  $msg->execute([$id, 'nos', 'Tudo bem, Mariana! Avisamos quando faltar pouco tempo no pacote.', $t(105), 'Camila', 0, 1]);
  $ven->execute([$id, 10, 450, $t(125), $por, 'meia antiderrapante']); $reg->execute([$t(125), 'venda', $id, 'Helena', 'Pacote de 10h com meia antiderrapante vendido · R$ 450']);
  $ven->execute([$id, 0, 30, $t(123), $por, 'Carrinho']); $reg->execute([$t(123), 'venda', $id, 'Helena', 'Carrinho · R$ 30']);   // um produto à parte, para o histórico do cliente
  // as compras antigas dos outros dois (aparecem no Banco de horas e no histórico)
  $ven->execute([$nomeId['Theo'], 5, 250, $d(-9, '13:50'), $por, '']); $ven->execute([$nomeId['Miguel'], 10, 450, $d(-6, '12:55'), $por, '']);
  // Theo: o pai perguntou e ninguém respondeu ainda
  $msg->execute([$nomeId['Theo'], 'ela', 'Quanto tempo ainda tem no pacote dele?', $t(4), '', 0, 0]);
  // Miguel: veio ontem, o pacote acabou; hoje a equipe ofereceu renovar
  $id = $nomeId['Miguel']; $vis->execute([$id, $d(-1, '14:20'), $d(-1, '16:10'), 110, 'idface']);
  $msg->execute([$id, 'nos', 'Oi, Carla! Miguel chegou na Play Space às 14:20 💛 Tem 1h50 no pacote. Qualquer coisa a gente te chama por aqui.', $d(-1, '14:20'), 'sistema', 1, 1]);
  $msg->execute([$id, 'nos', 'Miguel saiu às 16:10. Hoje ficou 1h50 e o pacote acabou. Até a próxima! 🧸', $d(-1, '16:10'), 'sistema', 1, 1]);
  $msg->execute([$id, 'ela', 'Obrigada! Ele adorou a piscina de bolinhas 😄', $d(-1, '16:40'), '', 0, 1]);
  $msg->execute([$id, 'nos', 'Oi, Carla! O pacote do Miguel acabou ontem. Quer renovar? 10h por R$ 450 ou 5h por R$ 250 💛', $t(150), 'Beatriz', 0, 1]);
  $msg->execute([$id, 'ela', 'Pode ser as 10h! Passo aí amanhã pra acertar.', $t(140), '', 0, 1]);
  // visitas de dias anteriores, para a ficha ter histórico
  foreach ([['Helena', -3, '14:00', '16:10', 130], ['Helena', -7, '10:30', '12:38', 128], ['Helena', -10, '15:00', '17:40', 160], ['Miguel', -2, '14:20', '17:00', 160], ['Miguel', -6, '13:00', '16:30', 210],
            ['Theo', -2, '15:00', '17:30', 150], ['Theo', -9, '14:00', '16:08', 128]] as $v)
    $vis->execute([$nomeId[$v[0]], $d($v[1], $v[2]), $d($v[1], $v[3]), $v[4], 'idface']);
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
  foreach (['visitas', 'vendas', 'registros', 'mensagens', 'batidas', 'criancas'] as $tabela) $db->prepare("DELETE FROM $tabela WHERE " . ($tabela === 'criancas' ? 'id' : 'crianca_id') . " IN ($marcas)")->execute($ids);
  foreach ($ids as $id) { apagarCfg('mandar_' . $id); apagarCfg('foto_' . $id); apagarCfg('renomear_' . $id); }
  $cids = array_map('intval', $e['compromissos'] ?? []);
  if ($cids) $db->prepare('DELETE FROM compromissos WHERE id IN (' . implode(',', array_fill(0, count($cids), '?')) . ')')->execute($cids);
  apagarCfg('exemplo');
}
