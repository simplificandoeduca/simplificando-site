<?php
/* =====================================================================
   Play Space — dados de exemplo: 10 crianças (5 no espaço), vendas, registros do dia, conversas de hoje e de ontem
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
    ['Helena', 5, 'Mariana Souza', 'mãe', '(11) 9 8765-4321', '123.456.789-00', 10, 418, 121, 1],
    ['Theo', 4, 'Rafael Lima', 'pai', '(11) 9 7654-3210', '234.567.890-11', 5, 278, 12, 1],
    ['Alice', 6, 'Juliana Alves', 'mãe', '(11) 9 6543-2109', '345.678.901-22', 20, 90, 93, 1],
    ['Miguel', 5, 'Carla Dias', 'mãe', '(11) 9 5432-1098', '456.789.012-33', 10, 600, 0, 1],
    ['Laura', 7, 'Bruno Costa', 'pai', '(11) 9 4321-0987', '567.890.123-44', 10, 140, 0, 1],
    ['Davi', 3, 'Fernanda Rocha', 'mãe', '(11) 9 3210-9876', '678.901.234-55', 5, 0, 0, 0],
    ['Sofia', 6, 'Patrícia Nunes', 'avó', '(11) 9 2109-8765', '789.012.345-66', 10, 195, 45, 1],
    ['Pedro', 8, 'André Martins', 'pai', '(11) 9 1098-7654', '890.123.456-77', 5, 120, 70, 1],
    ['Lívia', 4, 'Camila Freitas', 'mãe', '(11) 9 0987-6543', '901.234.567-88', 10, 580, 0, 1],
    ['Bento', 5, 'Renata Moraes', 'mãe', '(11) 9 9876-5432', '012.345.678-99', 20, 420, 0, 0],
  ];
  $ids = []; $nomeId = [];
  $ins = $db->prepare('INSERT INTO criancas (nome, idade, resp, rel, tel, cpf, obs, pacote_min, usado_min, entrou, idface_id, foto, rosto, ativo, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, 1, ?)');
  foreach ($lista as $l) {
    [$nome, $idade, $resp, $rel, $tel, $cpf, $pac, $usado, $dentro, $rosto] = $l;
    $ins->execute([$nome, $idade, $resp, $rel, $tel, $cpf, 'exemplo', $pac * 60, $usado, $dentro ? $t($dentro) : null, '', $rosto, $t(60 * 24 * 20)]);
    $id = (int)$db->lastInsertId(); $ids[] = $id; $nomeId[$nome] = $id;
  }
  $reg = $db->prepare('INSERT INTO registros (quando, tipo, crianca_id, nome, texto) VALUES (?, ?, ?, ?, ?)');
  $vis = $db->prepare('INSERT INTO visitas (crianca_id, entrou, saiu, debitado_min, origem) VALUES (?, ?, ?, ?, ?)');
  $ven = $db->prepare('INSERT INTO vendas (crianca_id, horas, valor, quando, por) VALUES (?, ?, ?, ?, ?)');
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
  // Laura veio hoje e já saiu (2h20); Miguel também e o pacote acabou
  $id = $nomeId['Laura']; $vis->execute([$id, $t(197), $t(57), 140, 'idface']);
  $reg->execute([$t(197), 'ent', $id, 'Laura', 'Rosto reconhecido na entrada']); $reg->execute([$t(57), 'sai', $id, 'Laura', '2h20 debitadas · restam 7h40']);
  $msg->execute([$id, 'nos', 'Oi, Bruno! Laura chegou na Play Space às ' . $hora($t(197)) . ' 💛 Tem 10h no pacote. Qualquer coisa a gente te chama por aqui.', $t(197), 'sistema', 1, 1]);
  $msg->execute([$id, 'nos', 'Laura saiu às ' . $hora($t(57)) . '. Hoje ficou 2h20 e ainda tem 7h40 no pacote. Até a próxima! 🧸', $t(57), 'sistema', 1, 1]);
  $msg->execute([$id, 'ela', 'Valeu! Ela adorou a tarde 😊', $t(49), '', 0, 1]);
  $id = $nomeId['Miguel']; $vis->execute([$id, $t(290), $t(180), 110, 'idface']);
  $reg->execute([$t(290), 'ent', $id, 'Miguel', 'Rosto reconhecido na entrada']); $reg->execute([$t(180), 'sai', $id, 'Miguel', '1h50 debitadas · restam 0h']);
  $msg->execute([$id, 'nos', 'Miguel saiu às ' . $hora($t(180)) . '. Hoje ficou 1h50 e o pacote acabou. Até a próxima! 🧸', $t(180), 'sistema', 1, 1]);
  $msg->execute([$id, 'nos', 'Oi, Carla! O pacote do Miguel acabou hoje. Quer renovar? 10h por R$ 450 ou 5h por R$ 250 💛', $t(150), 'Beatriz', 0, 1]);
  $msg->execute([$id, 'ela', 'Pode ser as 10h! Passo aí amanhã pra acertar.', $t(140), '', 0, 1]);
  // vendas de hoje: Davi (novo, com cadastro) e Pedro
  $id = $nomeId['Davi']; $ven->execute([$id, 5, 250, $t(28), $por]); $reg->execute([$t(28), 'venda', $id, 'Davi', 'Pacote de 5h vendido · R$ 250']);
  $msg->execute([$id, 'nos', 'Oi, Fernanda! Cadastro do Davi feito, com 5h no pacote. Pode trazer quando quiser 💛 Na primeira visita a gente tira a foto para o reconhecimento.', $t(27), 'Camila', 0, 1]);
  $msg->execute([$id, 'ela', 'Que ótimo, obrigada! Vamos amanhã de manhã.', $t(20), '', 0, 1]);
  $id = $nomeId['Pedro']; $ven->execute([$id, 5, 250, $t(75), $por]); $reg->execute([$t(75), 'venda', $id, 'Pedro', 'Pacote de 5h vendido · R$ 250']);
  // perguntas das famílias que ainda não foram respondidas
  $msg->execute([$nomeId['Theo'], 'ela', 'Quanto tempo ainda tem no pacote dele?', $t(4), '', 0, 0]);
  $msg->execute([$nomeId['Sofia'], 'ela', 'Ela almoçou direitinho?', $t(16), '', 0, 0]);
  $msg->execute([$nomeId['Helena'], 'ela', 'Obrigada! Ela vai ficar até umas 16h, tudo bem?', $t(110), '', 0, 1]);
  $msg->execute([$nomeId['Helena'], 'nos', 'Tudo bem, Mariana! Avisamos quando faltar pouco tempo no pacote.', $t(105), 'Camila', 0, 1]);
  // ontem: Bento e Lívia vieram; Lívia está acabando o pacote
  $id = $nomeId['Bento']; $vis->execute([$id, $d(-1, '14:05'), $d(-1, '17:10'), 185, 'idface']); $ven->execute([$id, 20, 750, $d(-1, '14:02'), $por]);
  $msg->execute([$id, 'nos', 'Oi, Renata! Bento chegou na Play Space às 14:05 💛 Tem 20h no pacote. Qualquer coisa a gente te chama por aqui.', $d(-1, '14:05'), 'sistema', 1, 1]);
  $msg->execute([$id, 'nos', 'Bento saiu às 17:10. Hoje ficou 3h05 e ainda tem 16h55 no pacote. Até a próxima! 🧸', $d(-1, '17:10'), 'sistema', 1, 1]);
  $msg->execute([$id, 'ela', 'Obrigada! Ele chegou em casa falando da piscina de bolinhas 😄', $d(-1, '17:40'), '', 0, 1]);
  $msg->execute([$id, 'nos', 'Que bom! Esperamos ele de novo 💛', $d(-1, '17:52'), 'João Pedro', 0, 1]);
  $id = $nomeId['Lívia']; $vis->execute([$id, $d(-1, '15:30'), $d(-1, '17:45'), 135, 'idface']);
  $msg->execute([$id, 'nos', 'Lívia saiu às 17:45. Hoje ficou 2h15 e ainda tem 0h20 no pacote. Até a próxima! 🧸', $d(-1, '17:45'), 'sistema', 1, 1]);
  $msg->execute([$id, 'nos', 'Oi, Camila! Restam só 20 minutos no pacote da Lívia. Quer renovar antes da próxima visita?', $d(-1, '18:02'), 'Beatriz', 0, 1]);
  // visitas de dias anteriores, para a ficha ter histórico
  foreach ([['Helena', -3, '14:00', '16:10', 130], ['Helena', -7, '10:30', '12:38', 128], ['Helena', -10, '15:00', '17:40', 160], ['Miguel', -2, '14:20', '17:00', 160], ['Miguel', -6, '13:00', '16:30', 210],
            ['Alice', -4, '10:00', '11:30', 90], ['Sofia', -5, '14:00', '17:15', 195], ['Lívia', -8, '13:30', '17:00', 210], ['Lívia', -12, '14:00', '17:55', 235], ['Theo', -2, '15:00', '17:30', 150], ['Theo', -9, '14:00', '16:08', 128], ['Pedro', -3, '16:00', '18:00', 120]] as $v)
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
