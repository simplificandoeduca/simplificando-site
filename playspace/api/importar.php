<?php
/* =====================================================================
   Play Space — importar os clientes e as horas do sistema anterior (1.01).
   A loja traz, de uma planilha, quem já era cliente e as horas que cada criança ainda tem. A tela lê a planilha e manda
   as linhas já separadas, em lotes; aqui cada linha é conferida de novo.
   - Criança que ainda não está no sistema vira um cadastro.
   - Criança que já está cadastrada não muda de cadastro. Se a planilha traz saldo para ela, as horas entram (uma vez só:
     quem já recebeu horas de uma importação não recebe de novo), a não ser que a tela peça para não mexer.
   As horas entram como um pacote de valor zero ("PACOTE DO SISTEMA ANTERIOR"): aparecem no histórico do cliente, valem
   como pacote na entrada e não entram no que foi vendido no dia.
   Só o gestor importa (em Ajustes). Antes da primeira linha o servidor guarda uma cópia do banco. Dá para desfazer a última
   importação: saem os cadastros que vieram nela e ainda não tiveram movimento, e as horas dadas a quem já estava cadastrado,
   se ainda não foram usadas.
   ===================================================================== */
declare(strict_types=1);

const IMPORTA_LOTE = 400;          // linhas por pedido (a tela manda em lotes)
const IMPORTA_MIN_MAX = 60000;     // mil horas: acima disso é coluna errada, não saldo (a linha entra sem horas)
// o cadastro importado que ninguém usou ainda: fora do espaço, sem visita, sem entrada cancelada, sem foto e sem venda além do pacote que veio na importação
const IMPORTA_SEM_MOVIMENTO = "c.entrou IS NULL AND c.foto = '' AND c.idface_id IS NULL"
  . " AND NOT EXISTS (SELECT 1 FROM visitas x WHERE x.crianca_id = c.id) AND NOT EXISTS (SELECT 1 FROM cancelamentos k WHERE k.crianca_id = c.id)"
  . " AND NOT EXISTS (SELECT 1 FROM mensagens m WHERE m.crianca_id = c.id)"
  . " AND NOT EXISTS (SELECT 1 FROM vendas v WHERE v.crianca_id = c.id AND NOT (v.servico = '" . SERV_IMPORTADO . "' AND v.valor = 0 AND v.por IS NULL))";
// as horas que a importação deu a quem já estava cadastrado e que ainda estão inteiras no saldo (a criança está fora do espaço)
const IMPORTA_HORAS_INTEIRAS = "v.lote = ? AND c.lote != v.lote AND c.entrou IS NULL AND c.pacote_min - c.usado_min >= v.minutos";

// o nome sem acento e em maiúsculas, para comparar ("JOÃO" e "JOAO" são a mesma criança)
function importaSemAcento(string $s): string {
  return strtr(maiusculo($s), ['Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
    'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ç' => 'C', 'Ñ' => 'N']);
}
// o documento como a planilha trouxe: com letras é RNE; CPF com 11 números sai formatado; se a planilha comeu os zeros da frente, eles voltam
function importaDoc(string $bruto): array {
  $t = trim($bruto); if ($t === '') return ['cpf', ''];
  if (preg_match('/[A-Za-z]/', $t)) return ['rne', mb_substr(mb_strtoupper(trim((string)preg_replace('/[^A-Za-z0-9.\-\/ ]/', '', $t))), 0, 20)];
  $d = (string)preg_replace('/\D/', '', $t);
  if (strlen($d) >= 8 && strlen($d) <= 10 && cpfValido(str_pad($d, 11, '0', STR_PAD_LEFT))) $d = str_pad($d, 11, '0', STR_PAD_LEFT);
  if (strlen($d) === 11) return ['cpf', substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2)];
  return ['cpf', mb_substr((string)preg_replace('/[^\d.\-]/', '', $t), 0, 20)];
}
// o telefone com DDD fica como a tela escreve: (11) 9 0000-0000 ou (11) 0000-0000; o resto fica como veio
function importaTel(string $bruto): string {
  $t = trim($bruto); if ($t === '') return '';
  $d = (string)preg_replace('/\D/', '', $t);
  if ((strlen($d) === 12 || strlen($d) === 13) && substr($d, 0, 2) === '55') $d = substr($d, 2);   // veio com o 55 do Brasil
  if ((strlen($d) === 11 || strlen($d) === 12) && $d[0] === '0') $d = substr($d, 1);               // veio com o zero antes do DDD
  if (strlen($d) === 11) return '(' . substr($d, 0, 2) . ') ' . $d[2] . ' ' . substr($d, 3, 4) . '-' . substr($d, 7);
  if (strlen($d) === 10) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
  return mb_substr($t, 0, 30);
}
// As chaves de um cadastro, para saber se a criança já está no sistema. A primeira é a principal: o nome com o documento do responsável;
// sem documento, o nome com o telefone; sem os dois, o nome com o responsável. As outras servem para a linha seguinte achar esta.
function importaChaves(string $nome, string $resp, string $doc, string $cpf, string $tel, string $tel2): array {
  $n = importaSemAcento($nome); $o = [];
  $dk = $doc === 'rne' ? (string)preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($cpf)) : (string)preg_replace('/\D/', '', $cpf);
  if ($doc === 'rne' ? strlen($dk) >= 5 : strlen($dk) === 11) $o[] = $n . '|d:' . $dk;
  foreach ([$tel, $tel2] as $t) { $d = (string)preg_replace('/\D/', '', $t); if (strlen($d) >= 8) $o[] = $n . '|t:' . substr($d, -8); }
  $o[] = $n . '|r:' . importaSemAcento($resp);
  return array_values(array_unique($o));
}

// Um lote de linhas da planilha. $lote identifica a importação inteira (a tela manda o mesmo em todos os pedidos dela).
// $horasParaQuemJaEsta: a criança que já está cadastrada recebe o saldo que a planilha traz para ela (uma vez só).
function importarLinhas(array $linhas, array $u, string $lote, bool $horasParaQuemJaEsta = true): array {
  $db = db(); if (!preg_match('/^[a-z0-9]{8,24}$/', $lote)) falha('Importação sem identificação. Feche a folha e abra de novo.');
  if (count($linhas) > IMPORTA_LOTE) falha('Linhas demais num pedido só.', 413);
  $quem = primeiroNome((string)$u['nome']);
  // a cópia de segurança é feita uma vez, antes da primeira linha desta importação
  $and = cfg('importando', null);
  if (!is_array($and) || ($and['lote'] ?? '') !== $lote) { $and = ['lote' => $lote, 'em' => agora(), 'por' => $quem, 'copia' => copiarBanco('Nada foi importado.')]; gravarCfg('importando', $and); }
  $imp = cfg('importacao', null);
  if (!is_array($imp) || ($imp['lote'] ?? '') !== $lote) $imp = ['lote' => $lote, 'em' => $and['em'], 'por' => $quem, 'copia' => (string)$and['copia'], 'cadastros' => 0, 'horas' => 0, 'min' => 0, 'registro' => 0];
  // quem já está cadastrado: a chave leva ao cadastro (os removidos não contam: podem voltar pela planilha)
  $tem = [];
  foreach ($db->query('SELECT id, nome, resp, tel, tel2, doc_tipo, cpf FROM criancas WHERE ativo = 1 ORDER BY id')->fetchAll() as $c)
    foreach (importaChaves((string)$c['nome'], (string)$c['resp'], ($c['doc_tipo'] ?? 'cpf') === 'rne' ? 'rne' : 'cpf', (string)$c['cpf'], (string)$c['tel'], (string)($c['tel2'] ?? '')) as $k) if (!isset($tem[$k])) $tem[$k] = (int)$c['id'];
  $jaRecebeu = null;   // quem já recebeu horas de uma importação (só é lido se precisar)
  $novos = 0; $existentes = 0; $comHoras = 0; $invalidas = 0; $min = 0; $agora = agora(); $bloqueia = regra()['bloqueioAuto'] === 'sim';
  $insC = $db->prepare("INSERT INTO criancas (nome, idade, resp, rel, tel, tel2, doc_tipo, cpf, obs, pacote_min, usado_min, foto, foto_origem, rosto, liberado, criado_em, pcd, lote) VALUES (?, 0, ?, 'responsável', ?, ?, ?, ?, ?, ?, ?, '', '', 0, ?, ?, 0, ?)");
  $insV = $db->prepare("INSERT INTO vendas (crianca_id, horas, minutos, valor, quando, por, itens, servico, auto, voucher, pcd, cheio, produto_id, qtd, alug, alug_min, lote) VALUES (?, ?, ?, 0, ?, NULL, '', ?, 0, '', 0, NULL, NULL, 0, '', 0, ?)");
  $obsEm = $db->prepare('UPDATE criancas SET obs_em = ?, obs_por = ? WHERE id = ?');
  $somar = $db->prepare('UPDATE criancas SET pacote_min = pacote_min + ? WHERE id = ?');
  $db->beginTransaction();
  try {
    foreach ($linhas as $l) {
      if (!is_array($l)) { $invalidas++; continue; }
      $nome = mb_substr(maiusculo((string)($l['nome'] ?? '')), 0, 60); if ($nome === '') { $invalidas++; continue; }   // sem o nome da criança não há cadastro
      $resp = mb_substr(maiusculo((string)($l['resp'] ?? '')), 0, 80);
      [$doc, $cpf] = importaDoc((string)($l['cpf'] ?? ''));
      $tel = importaTel((string)($l['tel'] ?? '')); $tel2 = importaTel((string)($l['tel2'] ?? '')); if ($tel === '' && $tel2 !== '') { $tel = $tel2; $tel2 = ''; }
      $obs = mb_substr(emMaiusculas((string)($l['obs'] ?? '')), 0, 500);
      $pac = (int)($l['pac'] ?? 0); if ($pac < 0 || $pac > IMPORTA_MIN_MAX) $pac = 0;   // saldo negativo ou absurdo: entra sem horas
      $usado = max(0, min($pac, (int)($l['usado'] ?? 0))); $saldo = $pac - $usado;
      $ch = importaChaves($nome, $resp, $doc, $cpf, $tel, $tel2);
      if (isset($tem[$ch[0]])) {   // já está no sistema (ou veio duas vezes na planilha): o cadastro não muda
        $cid = $tem[$ch[0]];
        if ($horasParaQuemJaEsta && $saldo > 0) {
          if ($jaRecebeu === null) $jaRecebeu = array_flip(array_map('intval', $db->query("SELECT DISTINCT crianca_id FROM vendas WHERE servico = '" . SERV_IMPORTADO . "'")->fetchAll(PDO::FETCH_COLUMN)));
          if (!isset($jaRecebeu[$cid])) {   // as horas do sistema anterior entram, uma vez só
            $somar->execute([$saldo, $cid]); $insV->execute([$cid, round($saldo / 60, 2), $saldo, $agora, SERV_IMPORTADO, $lote]);
            $c = crianca($cid); if ($c && !(int)$c['liberado']) bloquearAcesso($c, true);   // tem tempo de novo: o acesso volta
            $jaRecebeu[$cid] = true; $comHoras++; $min += $saldo; continue;
          }
        }
        $existentes++; continue;
      }
      $insC->execute([$nome, $resp, $tel, $tel2, $doc, $cpf, $obs, $pac, $usado, ($saldo > 0 || !$bloqueia) ? 1 : 0, $agora, $lote]);
      $id = (int)$db->lastInsertId(); foreach ($ch as $k) if (!isset($tem[$k])) $tem[$k] = $id;
      if ($obs !== '') $obsEm->execute([$agora, $quem, $id]);
      if ($pac > 0) { $insV->execute([$id, round($pac / 60, 2), $pac, $agora, SERV_IMPORTADO, $lote]); if ($jaRecebeu !== null) $jaRecebeu[$id] = true; }
      $novos++; $min += $saldo;
    }
    $imp['cadastros'] = (int)$imp['cadastros'] + $novos; $imp['horas'] = (int)($imp['horas'] ?? 0) + $comHoras; $imp['min'] = (int)$imp['min'] + $min;
    if ($imp['cadastros'] > 0 || $imp['horas'] > 0) {
      // uma linha só em "Hoje na recepção" (não uma por cadastro): nasce no primeiro lote e acompanha os seguintes
      $texto = 'Importação do sistema anterior: ' . importaFrase((int)$imp['cadastros'], (int)$imp['horas']) . ($imp['min'] > 0 ? ', com ' . dur((int)$imp['min']) . ' de saldo' : '') . ' · por ' . $imp['por'];
      if (!empty($imp['registro'])) $db->prepare('UPDATE registros SET texto = ? WHERE id = ?')->execute([$texto, (int)$imp['registro']]);
      else $imp['registro'] = registrar('cad', null, 'CADASTROS', $texto);
      unset($imp['desfeita']); gravarCfg('importacao', $imp);
    }
    $db->commit();
  } catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack(); registrarErro('importação: ' . $e->getMessage());
    falha('Não consegui importar este lote. Nada dele foi gravado.', 500);
  }
  return ['novos' => $novos, 'horas' => $comHoras, 'existentes' => $existentes, 'invalidas' => $invalidas, 'min' => $min, 'total' => (int)$imp['cadastros']];
}
// "12 cadastros e as horas de 3 que já estavam cadastrados"
function importaFrase(int $cad, int $horas): string {
  $a = $cad ? $cad . ($cad === 1 ? ' cadastro' : ' cadastros') : ''; $b = $horas ? 'as horas de ' . $horas . ($horas === 1 ? ' que já estava cadastrado' : ' que já estavam cadastrados') : '';
  return $a . ($a !== '' && $b !== '' ? ' e ' : '') . $b;
}
// a última importação, para o cartão de Ajustes (sem contar nada: a conta fica para a folha)
function importacaoPublica(): ?array {
  $i = cfg('importacao', null); if (!is_array($i) || empty($i['lote']) || ((int)($i['cadastros'] ?? 0) <= 0 && (int)($i['horas'] ?? 0) <= 0)) return null;
  return ['em' => (string)$i['em'], 'por' => (string)($i['por'] ?? ''), 'cadastros' => (int)$i['cadastros'], 'horas' => (int)($i['horas'] ?? 0), 'min' => (int)($i['min'] ?? 0), 'desfeita' => !empty($i['desfeita'])];
}
// a última importação com a conta do que o Desfazer tira e do que fica
function importacaoResumo(): ?array {
  $p = importacaoPublica(); if (!$p) return null; $db = db(); $lote = (string)cfg('importacao')['lote'];
  $um = function (string $sql) use ($db, $lote): int { $q = $db->prepare($sql); $q->execute([$lote]); return (int)$q->fetchColumn(); };
  $todos = $um('SELECT COUNT(*) FROM criancas c WHERE c.lote = ?'); $saem = $um('SELECT COUNT(*) FROM criancas c WHERE c.lote = ? AND ' . IMPORTA_SEM_MOVIMENTO);
  $hTodas = $um('SELECT COUNT(*) FROM vendas v JOIN criancas c ON c.id = v.crianca_id WHERE v.lote = ? AND c.lote != v.lote'); $hSaem = $um('SELECT COUNT(*) FROM vendas v JOIN criancas c ON c.id = v.crianca_id WHERE ' . IMPORTA_HORAS_INTEIRAS);
  return $p + ['saem' => $saem, 'ficam' => $todos - $saem, 'horas_saem' => $hSaem, 'horas_ficam' => $hTodas - $hSaem];
}
function importacaoDesfazer(array $u): array {
  $db = db(); $imp = cfg('importacao', null); if (!is_array($imp) || empty($imp['lote'])) falha('Não há importação para desfazer.', 404);
  $lote = (string)$imp['lote']; $quem = primeiroNome((string)$u['nome']); $bloqueia = regra()['bloqueioAuto'] === 'sim';
  $db->beginTransaction();
  try {
    $q = $db->prepare('SELECT c.id FROM criancas c WHERE c.lote = ? AND ' . IMPORTA_SEM_MOVIMENTO); $q->execute([$lote]); $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    $q = $db->prepare('SELECT v.id, v.crianca_id, v.minutos FROM vendas v JOIN criancas c ON c.id = v.crianca_id WHERE ' . IMPORTA_HORAS_INTEIRAS); $q->execute([$lote]); $horas = $q->fetchAll();
    if (!$ids && !$horas) { $db->rollBack(); falha('O que veio nessa importação já teve movimento (venda, entrada ou visita): não dá para desfazer.', 409); }
    // as horas dadas a quem já estava cadastrado saem do saldo (só as que ainda estão inteiras)
    foreach ($horas as $v) {
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$v['minutos'], (int)$v['crianca_id']]);
      $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([(int)$v['id']]);
      $c = crianca((int)$v['crianca_id']); if ($c && $bloqueia && (int)$c['liberado'] && (int)$c['pacote_min'] - (int)$c['usado_min'] <= 0) bloquearAcesso($c, false);   // ficou sem tempo: como quem zerou o pacote
    }
    foreach (array_chunk($ids, 400) as $parte) {
      $marcas = implode(',', array_fill(0, count($parte), '?'));
      $db->prepare("DELETE FROM vendas WHERE crianca_id IN ($marcas)")->execute($parte);                       // só o pacote que veio na importação (quem teve outra venda não está na lista)
      $db->prepare("UPDATE registros SET crianca_id = NULL WHERE crianca_id IN ($marcas)")->execute($parte);  // a linha do dia fica, sem apontar para um cadastro que saiu
      $db->prepare("DELETE FROM criancas WHERE id IN ($marcas)")->execute($parte);
    }
    $q = $db->prepare('SELECT COUNT(*) n, COALESCE(SUM(pacote_min - usado_min), 0) m FROM criancas WHERE lote = ?'); $q->execute([$lote]); $r = $q->fetch(); $ficam = (int)$r['n'];
    $q = $db->prepare('SELECT COUNT(*) n, COALESCE(SUM(v.minutos), 0) m FROM vendas v JOIN criancas c ON c.id = v.crianca_id WHERE v.lote = ? AND c.lote != v.lote'); $q->execute([$lote]); $h = $q->fetch(); $hFicam = (int)$h['n'];
    $saiu = importaFrase(count($ids), count($horas)); $ficou = importaFrase($ficam, $hFicam);
    registrar('cad', null, 'CADASTROS', 'A importação do sistema anterior foi desfeita: ' . (count($ids) === 1 && !$horas ? 'saiu ' : 'saíram ') . $saiu
      . ($ficou !== '' ? ' · ' . ($ficam === 1 && !$hFicam ? 'ficou ' : 'ficaram ') . $ficou . ', porque já ' . (($ficam + $hFicam) === 1 ? 'teve' : 'tiveram') . ' movimento' : '') . ' · por ' . $quem);
    if ($ficam || $hFicam) gravarCfg('importacao', ['cadastros' => $ficam, 'horas' => $hFicam, 'min' => max(0, (int)$r['m']) + max(0, (int)$h['m']), 'desfeita' => agora()] + $imp); else apagarCfg('importacao');
    apagarCfg('importando');
    $db->commit();
  } catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack(); registrarErro('desfazer a importação: ' . $e->getMessage());
    falha('Não consegui desfazer a importação. Nada foi mudado.', 500);
  }
  return ['sairam' => count($ids), 'ficaram' => $ficam, 'horas_sairam' => count($horas), 'horas_ficaram' => $hFicam];
}
