<?php
/* =====================================================================
   Play Space Finanças 4.3 (Play Space 1.90): o Adiantamento por unidade
   Pedido do dono: "uma aba adiantamento por unidade. No Anália eu coloco 700 e no Morumbi 1500. E eu tenho que ir discriminando do que
   foi esses valores e lá ele me dá o quanto sobrou".
   - Cada movimento (fin_adiant): a unidade, o dia, o valor e o tipo: 'entrada' (o valor colocado) ou 'gasto' (no que foi usado: a
     descrição é obrigatória).
   - Sobrou = tudo o que foi colocado − tudo o que foi gasto. O que sobra continua para o mês seguinte: cada mês mostra o que vinha de
     antes, o colocado e o gasto no mês e o que sobrou no fim.
   - Mudar um movimento fica guardado nele (quem, quando, o que era); tirar não apaga: fica guardado quem tirou.
   - Fica à parte: nada disto entra no Painel, nos Lançamentos, no Relatório financeiro, no Controle de caixa nem no resumo.
   ===================================================================== */
function adPreparar(): void {
  static $ok = false; if ($ok) return; $ok = true;
  db()->exec("CREATE TABLE IF NOT EXISTS fin_adiant (id INTEGER PRIMARY KEY, unidade_id INTEGER NOT NULL, tipo TEXT NOT NULL, dia TEXT NOT NULL, valor REAL NOT NULL,
    descricao TEXT NOT NULL DEFAULT '', por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '', removido_em TEXT, removido_por TEXT NOT NULL DEFAULT '', hist TEXT NOT NULL DEFAULT '')");
}
function adMes($m): string { $m = (string)$m; return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : date('Y-m', agoraTs()); }
/* o mês de cada unidade: o que vinha de antes, os movimentos do mês, o colocado, o gasto e o que sobrou (no fim do mês e hoje) */
function adListar(array $in): array {
  adPreparar(); $mes = adMes($in['mes'] ?? ''); $de = $mes . '-01'; $ate = date('Y-m-t', strtotime($de . ' 12:00:00'));
  $M = db()->query('SELECT * FROM fin_adiant WHERE removido_em IS NULL ORDER BY dia, id')->fetchAll(); $por = [];
  foreach ($M as $m) $por[(int)$m['unidade_id']][] = $m;
  $L = [];
  foreach (unidades() as $u) {
    $id = (int)$u['id']; $ms = $por[$id] ?? []; if (empty($u['ativo']) && !$ms) continue;
    $antes = 0.0; $col = 0.0; $gas = 0.0; $tudo = 0.0; $movs = [];
    foreach ($ms as $m) {
      $v = round((float)$m['valor'], 2); $s = $m['tipo'] === 'entrada' ? $v : -$v; $tudo += $s;
      if ((string)$m['dia'] < $de) $antes += $s;
      elseif ((string)$m['dia'] <= $ate) { if ($s > 0) $col += $v; else $gas += $v;
        $movs[] = ['id' => (int)$m['id'], 'tipo' => (string)$m['tipo'], 'dia' => (string)$m['dia'], 'valor' => $v, 'desc' => (string)$m['descricao'], 'por' => (string)$m['por'], 'em' => (string)$m['em'], 'mudou' => (string)$m['hist'] !== ''];
      }
    }
    $L[] = ['u' => $id, 'nome' => (string)$u['nome'], 'antes' => round($antes, 2), 'colocado' => round($col, 2), 'gasto' => round($gas, 2), 'sobrou' => round($antes + $col - $gas, 2), 'hoje' => round($tudo, 2), 'movs' => array_reverse($movs)];
  }
  return ['mes' => $mes, 'unis' => $L, 'hoje' => date('Y-m-d', agoraTs())];
}
function adPegar(int $id): array { adPreparar(); $q = db()->prepare('SELECT * FROM fin_adiant WHERE id = ? AND removido_em IS NULL'); $q->execute([$id]); $m = $q->fetch(); if (!$m) falha('Este lançamento não está mais aqui.', 404); return $m; }
/* lançar (sem id) ou mudar: o valor colocado na unidade, ou um gasto com a descrição */
function adSalvar(array $in, array $eu): array {
  adPreparar(); $db = db(); $id = (int)($in['id'] ?? 0);
  $tipo = (string)($in['tipo'] ?? ''); if (!in_array($tipo, ['entrada', 'gasto'], true)) falha('Escolha: valor colocado ou gasto.');
  $u = (int)($in['u'] ?? 0); $q = $db->prepare('SELECT id FROM unidades WHERE id = ?'); $q->execute([$u]); if (!$q->fetchColumn()) falha('Escolha a unidade.');
  $valor = cxValor($in['valor'] ?? null); if ($valor === null || $valor <= 0) falha($tipo === 'entrada' ? 'Escreva o valor colocado (ex.: 700,00).' : 'Escreva quanto foi gasto (ex.: 45,90).');
  if ($valor > 1000000) falha('Confira o valor.');
  $dia = fzDiaOk($in['dia'] ?? ''); $desc = fzTxt($in['desc'] ?? '', 160);
  if ($tipo === 'gasto' && mb_strlen($desc) < 2) falha('Escreva no que foi gasto (ex.: mercado, gás, conserto).');
  $novo = ['unidade_id' => $u, 'tipo' => $tipo, 'dia' => $dia, 'valor' => round($valor, 2), 'descricao' => $desc];
  if ($id) {
    $m = adPegar($id); $antes = ['unidade_id' => (int)$m['unidade_id'], 'tipo' => (string)$m['tipo'], 'dia' => (string)$m['dia'], 'valor' => round((float)$m['valor'], 2), 'descricao' => (string)$m['descricao']];
    if ($antes != $novo) { $h = json_decode((string)$m['hist'], true); $h = is_array($h) ? $h : []; $h[] = ['por' => (string)$eu['nome'], 'em' => agora(), 'era' => $antes];
      $db->prepare('UPDATE fin_adiant SET unidade_id = ?, tipo = ?, dia = ?, valor = ?, descricao = ?, hist = ? WHERE id = ?')->execute([$u, $tipo, $dia, round($valor, 2), $desc, json_encode(array_slice($h, -30), JSON_UNESCAPED_UNICODE), $id]); }
  } else {
    $db->prepare('INSERT INTO fin_adiant (unidade_id, tipo, dia, valor, descricao, por, em) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$u, $tipo, $dia, round($valor, 2), $desc, (string)$eu['nome'], agora()]); $id = (int)$db->lastInsertId();
  }
  return adListar(['mes' => $in['mes'] ?? substr($dia, 0, 7)]) + ['salvo' => $id];
}
/* tirar o que foi lançado por engano (fica guardado quem tirou) */
function adTirar(array $in, array $eu): array {
  $m = adPegar((int)($in['id'] ?? 0));
  db()->prepare('UPDATE fin_adiant SET removido_em = ?, removido_por = ? WHERE id = ?')->execute([agora(), (string)$eu['nome'], (int)$m['id']]);
  return adListar(['mes' => $in['mes'] ?? substr((string)$m['dia'], 0, 7)]);
}
