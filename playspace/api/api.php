<?php
/* =====================================================================
   Play Space — a API que a tela usa (POST JSON com "acao"; a resposta é JSON)
   Entrar, equipe (gestor, supervisor, atendente), crianças e pacotes de horas, entrada e saída,
   registros, conversas do WhatsApp, agenda, ajustes e as integrações (WhatsApp e iDFace).
   ===================================================================== */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/whats-lib.php';
set_exception_handler(function (Throwable $e) { registrarErro($e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()); if ($e instanceof RuntimeException) falha($e->getMessage(), 400); falha('Erro no servidor: ' . $e->getMessage(), 500); });

$in = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $bruto = file_get_contents('php://input') ?: '';
  if (strlen($bruto) > 6000000) falha('Envio grande demais.', 413);
  $in = json_decode($bruto, true); if (!is_array($in)) $in = $_POST;
}
$acao = (string)($in['acao'] ?? '');
if ($acao === '' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $acao = (string)($_GET['acao'] ?? '');
$GLOBALS['_gz'] = !empty($in['gz']) && stripos((string)($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip') !== false;   // 1.01: a tela pediu a resposta comprimida (ver responder())
// a tela manda o cabeçalho X-Play: um formulário de outro site não consegue (proteção contra CSRF)
// 1.49 (auditoria): TODO pedido precisa do cabeçalho (antes, só o POST: um link de outro site, aberto pela gestora, fazia a ação com a sessão
// dela). Sem ele, só o "estado" (só leitura)
if (($_SERVER['HTTP_X_PLAY'] ?? '') !== '1' && $acao !== 'estado') falha('Pedido sem o cabeçalho do app.', 403);
iniciarSessao();
$db = db();
// 1.45 (auditoria): o que muda os dados é feito um pedido de cada vez. Dois aparelhos que tocam juntos (a mesma saída em dois computadores,
// o mesmo "Liberar" no celular da gestora e no da supervisora, o mesmo desconto, a cobrança do período que nasce no pulso de várias telas)
// não passam os dois pela conferência: o segundo espera o primeiro terminar e já vê o que ele fez. A trava cai sozinha no fim do pedido
travarEscrita($acao);
/* 2.70: o Insta confere o Instagram de tempos em tempos, depois de responder (no máximo a cada 3 min; só faz algo com conta conectada) */
if (aCada('cv_ig', 180) && is_file(__DIR__ . '/conversas-lib.php')) { try { require_once __DIR__ . '/conversas-lib.php'; cvSincronizarDepois(); } catch (Throwable $e) { } }
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$val = fn(string $k, string $p = '') => trim((string)($in[$k] ?? $p));
// 1.49 (auditoria): de quando em quando este aparelho falou com o servidor (de 30 em 30 s, nos últimos dias): é o que diz se ele estava
// mesmo sem internet numa hora mandada como "feito sem internet"
if (!empty($_SESSION['uid']) && empty($_SESSION['desde'])) $_SESSION['desde'] = time();   // desde quando esta sessão existe (com alguém dentro)
if (!is_array($in['offline'] ?? null) || !isset($in['offline']['em'])) { $sl = intdiv(time(), 30); if (empty($_SESSION['vistos'][$sl])) { $_SESSION['vistos'][$sl] = 1;
  if (count($_SESSION['vistos']) > 6000) $_SESSION['vistos'] = array_filter($_SESSION['vistos'], fn($k) => $k > $sl - OFFLINE_DIAS * 2880, ARRAY_FILTER_USE_KEY);
  if (!empty($_SESSION['uid']) && (int)($_SERVER['HTTP_X_PLAY_UNIDADE'] ?? 0) > 0) { try { $db->prepare('INSERT OR IGNORE INTO unidades_vistas (u, slot) VALUES (?, ?)')->execute([(int)$_SERVER['HTTP_X_PLAY_UNIDADE'], $sl]); if ($sl % 240 === 0) $db->prepare('DELETE FROM unidades_vistas WHERE slot < ?')->execute([$sl - OFFLINE_DIAS * 2880 - 240]); } catch (Throwable $e) { } }   /* 1.91: a unidade também */
  if (!empty($_SESSION['uid'])) { try { $db->prepare('INSERT OR IGNORE INTO usuarios_vistos (uid, slot) VALUES (?, ?)')->execute([(int)$_SESSION['uid'], $sl]);
    if ($sl % 240 === 0) $db->prepare('DELETE FROM usuarios_vistos WHERE slot < ?')->execute([$sl - OFFLINE_DIAS * 2880 - 240]); } catch (Throwable $e) { } } } }
// 1.40: o que foi feito sem internet chega depois, na ordem, com a hora em que foi feito ("offline": { uid, em }): o pedido inteiro corre
// naquela hora (ver agoraTs). Cada envio tem uma marca única: o mesmo envio de novo (a internet caiu antes de a resposta chegar) não é feito
// duas vezes, volta a mesma resposta. Com internet a tela já manda a marca (sem a hora), para o caso de a resposta se perder no caminho
const OFFLINE_ACOES = ['crianca_salvar', 'atender', 'entrada', 'sapateira', 'saida', 'papel_guardar'];
if (is_array($in['offline'] ?? null)) {
  $off = $in['offline']; unset($in['offline']); $uid = (string)preg_replace('/[^A-Za-z0-9_-]/', '', (string)($off['uid'] ?? ''));
  if ($uid === '' || strlen($uid) > 64) falha('Envio sem a marca.');
  if (!in_array($acao, OFFLINE_ACOES, true)) falha('Isto não é feito sem internet.');
  $db->exec('CREATE TABLE IF NOT EXISTS offline_envios (uid TEXT PRIMARY KEY, acao TEXT NOT NULL, em TEXT, recebido TEXT NOT NULL, por INTEGER, resposta TEXT NOT NULL)');
  $q = $db->prepare('SELECT resposta FROM offline_envios WHERE uid = ?'); $q->execute([$uid]); $ja = $q->fetchColumn(); $q->closeCursor();
  if ($ja !== false) { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo $ja; exit; }   // já tinha chegado: a mesma resposta
  $em = null;
  if (isset($off['em'])) { $ts = (int)$off['em'];
    if ($ts > time() + 300) falha('A hora deste envio está no futuro: confira o relógio do computador.', 409);
    if ($ts < time() - OFFLINE_DIAS * 86400) falha('Este envio é de mais de ' . OFFLINE_DIAS . ' dias atrás: não entra sozinho. Confira com o gestor.', 409);
    // 1.49 (auditoria): o aparelho falou com o servidor antes E depois dessa hora (2 min para cada lado)? Então estava com internet: a hora
    // mandada não vale (seria uma saída com a hora que se quisesse) e vale a de agora. O gestor, que já corrige horas, fica de fora
    $vs = $_SESSION['vistos'] ?? []; $s0 = intdiv($ts, 30); $antes = false; $depois = false;
    for ($k = 1; $k <= 4; $k++) { if (!empty($vs[$s0 - $k])) $antes = true; if (!empty($vs[$s0 + $k])) $depois = true; }
    // a hora mandada é de antes de esta sessão existir (entrou de novo depois)? Confere pelo usuário, em qualquer aparelho
    if (!($antes && $depois) && !empty($_SESSION['uid']) && $ts < (int)($_SESSION['desde'] ?? 0) - 60) { try {
      $qv = $db->prepare('SELECT COALESCE(SUM(slot < ?), 0) a, COALESCE(SUM(slot > ?), 0) d FROM usuarios_vistos WHERE uid = ? AND slot BETWEEN ? AND ?');
      $qv->execute([$s0, $s0, (int)$_SESSION['uid'], $s0 - 4, $s0 + 4]); $rv = $qv->fetch(); $antes = (int)$rv['a'] > 0; $depois = (int)$rv['d'] > 0; } catch (Throwable $e) { } }
    /* 1.91 (auditoria): com outro login (ou o relógio do computador mudado), a hora passava: vale também a unidade, qualquer aparelho dela */
    if (!($antes && $depois) && (int)($_SERVER['HTTP_X_PLAY_UNIDADE'] ?? 0) > 0) { try {
      $qv = $db->prepare('SELECT COALESCE(SUM(slot < ?), 0) a, COALESCE(SUM(slot > ?), 0) d FROM unidades_vistas WHERE u = ? AND slot BETWEEN ? AND ?');
      $qv->execute([$s0, $s0, (int)$_SERVER['HTTP_X_PLAY_UNIDADE'], $s0 - 4, $s0 + 4]); $rv = $qv->fetch(); if ((int)$rv['a'] > 0 && (int)$rv['d'] > 0) { $antes = true; $depois = true; } } catch (Throwable $e) { } }
    $trocou = false; if ($antes && $depois && $ts < time() - 120) { $eu0 = eu(); if ($eu0 && $eu0['papel'] !== 'gestor') { $ts = time(); $trocou = true; } }
    $GLOBALS['PS_AGORA'] = min($ts, time()); $em = $trocou ? null : date('Y-m-d H:i:s', $GLOBALS['PS_AGORA']); $in['mesmo_assim'] = true; }   // (a venda repetida é conferida pela marca)
  $GLOBALS['PS_OFF'] = ['uid' => $uid, 'acao' => $acao, 'em' => $em, 'negada' => (!empty($trocou) ? date('H:i', (int)$off['em']) : null)];
}

/* ---------- o que a tela carrega de uma vez ---------- */
/* 2.60: a lista leve. Quem pede (enx = 1, a tela 2.60 em diante) recebe cada cadastro sem os campos que estão no de sempre (a tela põe de
   volta), e a assinatura (sig) do que foi mandado. Pedindo de novo com a mesma assinatura, sem nada novo, a resposta é só "igual" */
const CRI_PADRAO = ['obs' => '', 'pacote' => 0.0, 'usado' => 0, 'entrou' => null, 'idface_id' => null, 'rosto' => false, 'foto' => '', 'ativo' => true, 'liberado' => true, 'sapateira' => null, 'doc' => 'cpf', 'tel2' => '', 'pmin' => 0, 'pcd' => false, 'imp' => false, 'uni' => null, 'obs_antes' => '', 'obs_em' => null, 'obs_por' => '', 'nasc' => '', 'festa' => null, 'vabriu' => '', 'visitas' => [], 'venda' => null, 'atraso' => null, 'uv' => 0, 'vouch' => false, 'idade' => 0, 'rec' => '', 'rec_em' => null, 'rec_por' => ''];
function criancasEnxutas(array $L): array { foreach ($L as $i => $c) foreach (CRI_PADRAO as $k => $p) if (array_key_exists($k, $c) && $c[$k] === $p) unset($L[$i][$k]); return $L; }
function criancaPublica(array $c): array {
  return ['id' => (int)$c['id'], 'nome' => $c['nome'], 'plano_pref' => planoPreferido($c), 'idade' => (int)$c['idade'], 'resp' => $c['resp'], 'rel' => $c['rel'], 'tel' => $c['tel'], 'cpf' => $c['cpf'], 'obs' => $c['obs'],
    'pacote' => round((int)$c['pacote_min'] / 60, 2), 'usado' => (int)$c['usado_min'], 'entrou' => $c['entrou'], 'idface_id' => $c['idface_id'] === null ? null : (int)$c['idface_id'],
    'rosto' => !empty($c['rosto']) || $c['idface_id'] !== null, 'foto' => (string)$c['foto'], 'ativo' => (bool)$c['ativo'], 'liberado' => !isset($c['liberado']) || (bool)$c['liberado'], 'sapateira' => isset($c['sapateira']) && $c['sapateira'] !== null ? (int)$c['sapateira'] : null,
    'doc' => ($c['doc_tipo'] ?? 'cpf') === 'rne' ? 'rne' : 'cpf', 'tel2' => (string)($c['tel2'] ?? ''), 'pmin' => (int)$c['pacote_min'], 'pcd' => !empty($c['pcd']), 'imp' => (string)($c['lote'] ?? '') !== '', 'uni' => isset($c['unidade_id']) && $c['unidade_id'] !== null ? (int)$c['unidade_id'] : null,
    'obs_antes' => (string)($c['obs_antes'] ?? ''), 'obs_em' => $c['obs_em'] ?? null, 'obs_por' => (string)($c['obs_por'] ?? ''), 'nasc' => (string)($c['nascimento'] ?? ''), 'festa' => !empty($c['festa_id']) && $c['entrou'] ? (int)$c['festa_id'] : null, 'vabriu' => quemAbriu($c), 'rec' => (string)($c['recado'] ?? ''), 'rec_em' => $c['recado_em'] ?? null, 'rec_por' => (string)($c['recado_por'] ?? '')];   /* rec (2.72): o recado do responsável */   /* vabriu (1.68): quem abriu o pedido da visita de agora */   // festa (1.29): a festa em que a criança está agora   // nasc (1.11): a data de nascimento, AAAA-MM-DD ou ''   // a observação anterior, e quem mudou a de agora e quando   // pmin: o tempo comprado em minutos exatos   // doc: o campo cpf guarda o CPF ou o RNE (estrangeiro)
}
function dados(array $eu): array {
  $db = db(); $hojeMenos = date('Y-m-d') . ' 00:00:00';   // 1.45 (auditoria): "hoje" é o dia, desde a meia-noite (era as últimas 24 h), como o Caixa do dia e o painel
  try { baixaAutomatica(); } catch (Throwable $e) { registrarErro('baixa automática: ' . $e->getMessage()); }   // 1.90: quem ficou sem saída de um dia para o outro
  acertarTodas();   // quem passou do tempo comprado mais a tolerância já aparece com a nova cobrança
  acertarCupons();   // 0.93: venda desfeita ou cancelada: o cupom fiscal dela acompanha
  if (mudancas() > (int)($GLOBALS['PS_MARCADO'] ?? 0)) bancoMudou();   /* 2.66: as tarefas acima gravaram: a lista guardada não vale mais */
  $balcao = balcaoCT(true);   /* 2.00: o cliente da loja da Casa Tom vai à parte (fora das listas) */
  $criancas = cacheGrande('lista', 2.0, function () use ($db, $balcao) {   /* 2.66: a lista de clientes (a mesma para todos) é montada uma vez para as telas que recarregam juntas; gravou, sai */
  $criancas = array_map('criancaPublica', $db->query('SELECT * FROM criancas WHERE ativo = 1' . ($balcao ? ' AND id != ' . (int)$balcao['id'] : '') . ' ORDER BY nome')->fetchAll());
  $visitas = $db->query('SELECT * FROM visitas ORDER BY id DESC LIMIT 400')->fetchAll();
  $porCrianca = []; foreach ($visitas as $v) $porCrianca[(int)$v['crianca_id']][] = ['id' => (int)$v['id'], 'dia' => $v['saiu'], 'ini' => $v['entrou'], 'entrou' => substr($v['entrou'], 11, 5), 'saiu' => substr($v['saiu'], 11, 5), 'deb' => (int)$v['debitado_min'], 'uni' => $v['unidade_id'] === null ? null : (int)$v['unidade_id']];   /* 2.71: a unidade da visita (o "Saíram há pouco" de cada unidade) */
  $ultimas = []; foreach ($db->query('SELECT v.crianca_id, v.minutos, v.valor, v.quando, v.itens, v.servico FROM vendas v JOIN (SELECT crianca_id, MAX(id) AS m FROM vendas WHERE minutos > 0 GROUP BY +crianca_id) u ON u.m = v.id')->fetchAll() as $v) $ultimas[(int)$v['crianca_id']] = ['h' => round((int)$v['minutos'] / 60, 2), 'm' => (int)$v['minutos'], 'v' => (float)$v['valor'], 'quando' => $v['quando'], 'com' => (string)$v['itens'], 'servico' => (string)$v['servico']];
  // as cobranças por atraso da visita de quem está no espaço (quantas e quanto falta receber na saída)
  $atrasos = []; foreach ($db->query('SELECT v.crianca_id, COUNT(*) n, SUM(v.valor) t FROM criancas c CROSS JOIN vendas v ON v.crianca_id = c.id WHERE c.entrou IS NOT NULL AND v.auto = 1 AND v.quando >= c.entrou AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = v.id) GROUP BY v.crianca_id')->fetchAll() as $a) $atrasos[(int)$a['crianca_id']] = ['n' => (int)$a['n'], 'valor' => (float)$a['t']];
  // uv: a última linha de venda de cada criança (entre as 2000 linhas mais novas do dia a dia). A tela usa para saber que um atendimento que ficou em aberto num aparelho já foi registrado em outro
  $uv = []; foreach ($db->query("SELECT crianca_id, MAX(id) m FROM registros WHERE id > (SELECT COALESCE(MAX(id), 0) - 2000 FROM registros) AND tipo = 'venda' AND crianca_id IS NOT NULL AND texto NOT LIKE 'FESTA %' AND texto NOT LIKE 'FESTA:%' AND texto NOT LIKE 'UPGRADE DE FESTA%' AND texto NOT LIKE 'ADICIONAL DE FESTA%' GROUP BY crianca_id")->fetchAll() as $l) $uv[(int)$l['crianca_id']] = (int)$l['m'];   // 1.12: a venda da festa não derruba o atendimento comum que estava guardado
  // vouch: a criança tem um voucher de hoje sem saída depois dele (o da visita em curso, ou um guardado): a tela mostra e não oferece outro
  $vou = []; $qv = $db->prepare("SELECT v.crianca_id FROM vendas v WHERE v.quando >= ? AND v.voucher != '' AND v.quando > COALESCE((SELECT MAX(x.saiu) FROM visitas x WHERE x.crianca_id = v.crianca_id), '')"); $qv->execute([date('Y-m-d') . ' 00:00:00']);
  foreach ($qv->fetchAll() as $l) $vou[(int)$l['crianca_id']] = true;
  $planos = planosDoEspaco();   // de onde sai o tempo de quem está no espaço: 'pacote' ou 'avulso'
  // 1.01: quem já recebeu horas de uma importação (não recebe de novo: a tela mostra isso antes de importar)
  $imph = array_flip(array_map('intval', $db->query("SELECT crianca_id FROM vendas WHERE servico = '" . SERV_IMPORTADO . "'")->fetchAll(PDO::FETCH_COLUMN)));
  $tvis = tempoAvulsoDeTodos();   // 1.11: o tempo comprado para a visita em curso que não é de pacote (para a saída com "pagar avulso")
  $pendi = pendItensDeTodos();
  $papeis = []; foreach ($db->query('SELECT p.crianca_id, p.papel FROM festa_presencas p JOIN criancas c ON c.id = p.crianca_id AND c.festa_id = p.festa_id WHERE c.entrou IS NOT NULL')->fetchAll() as $x) $papeis[(int)$x['crianca_id']] = (string)$x['papel'];   // 1.29: aniversariante ou convidado, de quem está numa festa   // 1.02: quanto cada criança ainda tem a pagar do que comprou nesta visita (a tela mostra PAGO ou NÃO PAGO); 1.03: item por item
  foreach ($criancas as &$c) { if (isset($papeis[$c['id']])) $c['festa_papel'] = $papeis[$c['id']]; if ($c['entrou']) { $c['plano'] = $planos[$c['id']] ?? 'avulso'; $c['tvis'] = $tvis[$c['id']] ?? 0; } $c['visitas'] = $porCrianca[$c['id']] ?? []; $c['venda'] = $ultimas[$c['id']] ?? null; $c['atraso'] = $atrasos[$c['id']] ?? null; $c['uv'] = $uv[$c['id']] ?? 0; $c['vouch'] = isset($vou[$c['id']]); if (isset($imph[$c['id']])) $c['imph'] = true; if (isset($pendi[$c['id']])) { $c['pendi'] = $pendi[$c['id']]; $c['pend'] = round(array_sum(array_column($pendi[$c['id']], 'v')), 2); } if (ehExemplo($c['id'])) $c['teste'] = true; }   // teste: criança dos dados de exemplo (o botão do rodapé)
  unset($c);
  return $criancas; });
  $registros = array_map(fn($r) => ['id' => (int)$r['id'], 'h' => $r['quando'], 'tipo' => $r['tipo'], 'nome' => $r['nome'], 'txt' => $r['texto'], 'crianca' => $r['crianca_id'] === null ? null : (int)$r['crianca_id'], 'uni' => ($r['unidade_id'] ?? null) === null ? null : (int)$r['unidade_id']], $db->query('SELECT * FROM registros ORDER BY id DESC LIMIT 300')->fetchAll());
  $msgs = $db->query('SELECT * FROM mensagens ORDER BY quando ASC, id ASC')->fetchAll();
  $conversas = [];
  foreach ($msgs as $m) {
    $cid = (int)$m['crianca_id'];
    if (!isset($conversas[$cid])) $conversas[$cid] = ['id' => $cid, 'crianca' => $cid, 'lidas' => true, 'msgs' => []];
    $conversas[$cid]['msgs'][] = ['id' => (int)$m['id'], 'de' => $m['de'], 'txt' => $m['texto'], 'h' => $m['quando'], 'por' => $m['por'], 'auto' => (bool)$m['auto'], 'foto' => $m['foto'], 'erro' => $m['erro']];
    if ($m['de'] === 'ela' && empty($m['lida'])) $conversas[$cid]['lidas'] = false;
  }
  $compromissos = array_map(fn($x) => ['id' => (int)$x['id'], 'dia' => $x['dia'], 'hora' => $x['hora'], 'titulo' => $x['titulo'], 'tipo' => $x['tipo'], 'sub' => $x['sub'], 'obs' => $x['obs'], 'feito' => !empty($x['feito'])], $db->query('SELECT * FROM compromissos ORDER BY dia, hora')->fetchAll());
  $vendasHoje = $db->prepare('SELECT COUNT(*) n, COALESCE(SUM(minutos),0) m, COALESCE(SUM(valor),0) v FROM vendas WHERE quando >= ? AND servico != ?'); $vendasHoje->execute([$hojeMenos, SERV_IMPORTADO]); $vh = $vendasHoje->fetch();   // tudo o que foi vendido nas últimas 24 h; m = minutos de brincar
  $equipe = $eu['papel'] === 'atendente' ? [] : array_map(fn($u) => ['id' => (int)$u['id'], 'nome' => $u['nome'], 'login' => $u['login'], 'papel' => $u['papel'], 'uni' => (int)($u['unidade_id'] ?? 0), 'vende' => usuarioVende($u)], $db->query('SELECT id, nome, login, papel, unidade_id, vende FROM usuarios WHERE ativo = 1 AND loja = 0 ORDER BY nome')->fetchAll());   /* 2.22: vende */   /* 2.10: sem a "loja" (o computador do balcão) */   // 2.02: e a unidade
  $q = $db->prepare("SELECT COUNT(*) n, COALESCE(SUM(valor), 0) v FROM vendas WHERE auto = 1 AND quando >= ? AND itens NOT LIKE 'avulsa%'"); $q->execute([$hojeMenos]); $ah = $q->fetch(); $atrasoHoje = ['n' => (int)$ah['n'], 'valor' => (float)$ah['v']];   // 1.15: o tempo além do pacote nas últimas 24 h (a recreação avulsa é venda comum, acertada na saída)
  $q = $db->prepare("SELECT COALESCE(SUM(voucher != ''), 0) nv, COALESCE(SUM(CASE WHEN voucher != '' THEN cheio ELSE 0 END), 0) vv, COALESCE(SUM(pcd = 1), 0) np, COALESCE(SUM(CASE WHEN pcd = 1 THEN cheio - valor - desc_valor ELSE 0 END), 0) vp, COALESCE(SUM(desc_valor), 0) vd FROM vendas WHERE quando > ?"); $q->execute([$hojeMenos]); $dh = $q->fetch();
  $q = $db->prepare("SELECT COUNT(*) FROM registros WHERE tipo = 'desc' AND quando > ?"); $q->execute([$hojeMenos]); $nd = $q->fetchColumn();   // 1.04: quantos descontos o gestor deu nas últimas 24 h
  $descontosHoje = ['vouchers' => (int)$dh['nv'], 'voucher_valor' => (float)$dh['vv'], 'pcd' => (int)$dh['np'], 'pcd_valor' => (float)$dh['vp'], 'desc' => (int)$nd, 'desc_valor' => round((float)$dh['vd'], 2)];   // 1.04: e o desconto dado pelo gestor   // o que saiu por voucher e com desconto PCD nas últimas 24 h
  require_once __DIR__ . '/idface-lib.php';
  // as entradas canceladas sem cobrar: só gestor e supervisor recebem a lista (são eles que conferem o motivo)
  $canc = $eu['papel'] === 'atendente' ? [] : array_map('cancelamentoPublico', $db->query('SELECT * FROM cancelamentos ORDER BY id DESC LIMIT 80')->fetchAll());
  // cadastro feito hoje e o atendimento ainda por fazer: sem venda, sem visita, sem entrada cancelada e fora do espaço.
  // A tela mostra em "Falta finalizar" e, ao tocar na criança, abre direto o atendimento (continua de onde parou).
  $q = $db->prepare('SELECT c.id, c.criado_em FROM criancas c WHERE c.ativo = 1 AND c.entrou IS NULL AND c.lote = \'\' AND c.criado_em >= ? AND NOT EXISTS (SELECT 1 FROM vendas v WHERE v.crianca_id = c.id)
    AND NOT EXISTS (SELECT 1 FROM visitas x WHERE x.crianca_id = c.id) AND NOT EXISTS (SELECT 1 FROM cancelamentos k WHERE k.crianca_id = c.id AND k.tipo != \'desfeita\') ORDER BY c.criado_em, c.id');
  $q->execute([date('Y-m-d') . ' 00:00:00']); $fora = pendFora();   // quem a equipe tirou da lista hoje ("não vai fazer agora") não volta
  $pend = array_values(array_filter(array_map(fn($p) => ['id' => (int)$p['id'], 'em' => $p['criado_em']], $q->fetchAll()), fn($p) => !isset($fora[$p['id']])));
  return ['criancas' => array_values($criancas), 'registros' => $registros, 'conversas' => array_values($conversas), 'compromissos' => $compromissos, 'vfut' => vfutLista(), 'festas' => festasDaTela(), 'regra' => regra(), 'cancelamentos' => $canc, 'pendentes' => $pend, 'produtos' => produtos(), 'balcao' => $balcao ? criancaPublica($balcao) : null, 'unidades' => unidades(), 'alugueis' => alugueis(), 'pagamentos_hoje' => pagamentosDesde($hojeMenos), 'cupons' => cuponsDaTela(),
    'vendas_hoje' => ['n' => (int)$vh['n'], 'min' => (int)$vh['m'], 'horas' => round((int)$vh['m'] / 60, 2), 'valor' => (float)$vh['v']], 'atraso_hoje' => $atrasoHoje, 'descontos_hoje' => $descontosHoje, 'equipe' => $equipe, 'eu' => $eu, 'versao' => PLAY_VERSAO, 'anotacoes' => anotacoesLista(),
    'idface' => (function (array $x) use ($eu) { if ($eu['papel'] !== 'gestor') unset($x['chave'], $x['push_url'], $x['url_ponte']); return $x; })(idfaceEstado()),   /* 1.49: a chave da ponte só para o gestor */ 'whats' => whatsResumo(), 'agora' => agora(), 'exemplo' => ($ex = cfg('exemplo', null)) && is_array($ex) ? count($ex['criancas'] ?? []) : 0,
    'limpeza' => $eu['papel'] === 'gestor' ? ['oferta' => cfg('limpeza_oferta', null) !== null, 'ultima' => limpezaPublica()] : null,
    'importacao' => $eu['papel'] === 'gestor' ? importacaoLeve() : null,
    'copia' => $eu['papel'] === 'gestor' ? copiaInfo() : null,   // 1.51: a cópia de segurança da semana
    'meta' => metaDoDia($eu), 'avisos' => avisosDaGestora($eu), 'painel' => $eu['papel'] === 'gestor' ? painelDoDia() : null, 'caixa' => caixaDoDia($eu['papel'] === 'gestor' ? 0 : (int)(unidadeDoPedido() ?? 0)), 'finalizados' => finalizadosDoDia($eu['papel'] === 'gestor' ? 0 : (int)(unidadeDoPedido() ?? 0)),   /* 2.15: só a unidade de quem não é o gestor */   // 1.25: o que a gestora ainda não viu, o dia de cada unidade, e os finalizados do dia
    'pedidos' => pedidosDaTela($eu), 'nf_emitir' => $eu['papel'] === 'atendente' ? 0 : (int)$db->query("SELECT COUNT(*) FROM pedidos WHERE tipo = 'nf' AND status = 'liberado'")->fetchColumn()];   // 1.23: os pedidos de autorização desta tela, e quantas notas autorizadas esperam o número   // 0.98: só o gestor recebe (é ele quem limpa e quem desfaz)
}
// quem a equipe tirou de "Falta finalizar" hoje: [id da criança => dia]. O que é de outro dia não conta mais
function pendFora(): array { $f = cfg('pend_fora', []); $hoje = date('Y-m-d'); return is_array($f) ? array_filter($f, fn($d) => $d === $hoje) : []; }
function whatsResumo(): array { $c = whatsCfg(); return ['conectado' => whatsPronto($c), 'numero' => $c['numero'], 'nome' => $c['nome'], 'configurado' => $c['chave'] !== '']; }
// 1.01: a última importação, para o cartão de Ajustes (o arquivo da importação só é carregado se houve uma)
function importacaoLeve(): ?array { if (cfg('importacao', null) === null) return null; require_once __DIR__ . '/importar.php'; return importacaoPublica(); }
function podeAjustar(array $u): void { if ($u['papel'] !== 'gestor') falha('Só o gestor mexe nos ajustes.', 403); }
function textoCriancaBasico(array &$in, array $atual = []): array {
  $nome = trim((string)($in['nome'] ?? $atual['nome'] ?? '')); if ($nome === '') falha('Falta o nome da criança.');
  // o documento do responsável: CPF (só números, ponto e traço) ou RNE de estrangeiro (letras e números)
  $doc = ($in['doc'] ?? $atual['doc_tipo'] ?? 'cpf') === 'rne' ? 'rne' : 'cpf'; $bruto = (string)($in['cpf'] ?? $atual['cpf'] ?? '');
  $numDoc = $doc === 'rne' ? mb_substr(mb_strtoupper(trim(preg_replace('/[^A-Za-z0-9.\-\/ ]/', '', $bruto))), 0, 20) : preg_replace('/[^\d.\-]/', '', $bruto);
  // 1.11: a data de nascimento é opcional. Veio vazia: fica sem (ou é apagada). Veio escrita: precisa ser uma data que existe e já passou (1.17: de qualquer idade). Com ela, a idade guardada sai dela
  $nasc = (string)($atual['nascimento'] ?? ''); if (array_key_exists('nasc', $in)) { $bruta = trim((string)$in['nasc']); $nasc = $bruta === '' ? '' : nascimentoValido($bruta); if ($bruta !== '' && $nasc === '') falha('Confira a data de nascimento.'); }
  return ['doc' => $doc, 'tel2' => mb_substr(trim((string)($in['tel2'] ?? $atual['tel2'] ?? '')), 0, 30),'nome' => mb_substr(maiusculo($nome), 0, 60), 'nascimento' => $nasc, 'idade' => $nasc !== '' ? min(120, idadeDeNascimento($nasc)) : max(0, min(120, (int)($in['idade'] ?? $atual['idade'] ?? 0))), 'resp' => mb_substr(maiusculo((string)($in['resp'] ?? $atual['resp'] ?? '')), 0, 80),
    'rel' => mb_substr(trim((string)($in['rel'] ?? $atual['rel'] ?? 'responsável')), 0, 20), 'tel' => mb_substr(trim((string)($in['tel'] ?? $atual['tel'] ?? '')), 0, 30),
    'cpf' => $numDoc, 'obs' => mb_substr(emMaiusculas((string)($in['obs'] ?? $atual['obs'] ?? '')), 0, 500), 'pcd' => empty($in['pcd'] ?? $atual['pcd'] ?? 0) ? 0 : 1];
}
function cpfValido(string $v): bool {
  $d = preg_replace('/\D/', '', $v); if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) return false;
  for ($n = 9; $n <= 10; $n++) { $t = 0; for ($k = 0; $k < $n; $k++) $t += (int)$d[$k] * ($n + 1 - $k); $r = ($t * 10) % 11; if ($r === 10) $r = 0; if ($r !== (int)$d[$n]) return false; }
  return true;
}
function cpfJaCliente(string $digitos): bool { // já existe criança ativa com este CPF (11 números)?
  if (strlen($digitos) !== 11) return false;
  $q = db()->prepare("SELECT 1 FROM criancas WHERE ativo = 1 AND doc_tipo != 'rne' AND REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ? LIMIT 1"); $q->execute([$digitos]); return (bool)$q->fetchColumn();
}
// O cadastro só fecha com: o documento do responsável (CPF válido, ou RNE), o nome dele, um telefone com DDD e o nome da criança.
// Ao editar, o que não foi mexido passa como está (cadastro antigo, de antes desta regra): só o que mudou precisa estar certo, e nada disso pode ser apagado.
function conferirCadastro(array $d, array $atual = []): void {
  $novo = !$atual; $so = fn(string $t) => preg_replace('/\D/', '', $t); $chave = fn(string $tipo, string $v) => $tipo === 'rne' ? preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($v)) : $so($v);
  $tipoAntes = ($atual['doc_tipo'] ?? 'cpf') === 'rne' ? 'rne' : 'cpf';
  if ($novo || $d['doc'] !== $tipoAntes || $chave($d['doc'], $d['cpf']) !== $chave($tipoAntes, (string)($atual['cpf'] ?? ''))) {
    if ($d['doc'] === 'rne') { if (strlen($chave('rne', $d['cpf'])) < 5) falha('Falta o RNE do responsável.'); }
    elseif ($so($d['cpf']) === '') falha('Falta o CPF do responsável (ou o RNE, se for estrangeiro).');
    elseif (!cpfValido($d['cpf']) && !cpfJaCliente($so($d['cpf']))) falha('Confira o CPF: os números não batem.');   // o documento de quem já é cliente passa como está (a irmã entra no mesmo cliente)
  }
  if ($d['resp'] === '' && ($novo || trim((string)($atual['resp'] ?? '')) !== '')) falha('Falta o nome do responsável.');
  if ($novo || $so($d['tel']) !== $so((string)($atual['tel'] ?? '')) || $so($d['tel2']) !== $so((string)($atual['tel2'] ?? ''))) {
    // 1.16: número de fora do Brasil começa com "+" (o código do país): não tem DDD e vale com 8 números ou mais
    $fora = fn(string $t) => strpos(ltrim($t), '+') === 0; $completo = fn(string $t) => strlen($so($t)) >= ($fora($t) ? 8 : 10);
    if ($so($d['tel']) === '' && $so($d['tel2']) === '') falha('Falta um telefone do responsável.');
    if (!$completo($d['tel']) && !$completo($d['tel2'])) falha($fora($so($d['tel']) !== '' ? $d['tel'] : $d['tel2']) ? 'Telefone internacional incompleto: coloque o + com o código do país e o número.' : 'Telefone incompleto: coloque o DDD e o número.');
  }
}

switch ($acao) {
  /* ---------- entrar e sair ---------- */
  case 'estado': {
    $n = (int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($n === 0) $db->prepare("INSERT OR IGNORE INTO config (chave, valor) VALUES ('instalado_em', ?)")->execute([(string)time()]);   // 1.49: a hora em que o site novo foi aberto pela primeira vez
    responder(['primeiro_acesso' => $n === 0, 'usuario' => eu(), 'versao' => PLAY_VERSAO]);
  }
  case 'notas_ponte': { /* 2.49: o bilhete para entrar no sistema de notas sem pedir o login (o Notas 191 confere) */
    $u = exigir(); session_write_close();
    responder(['token' => notasPonteBilhete((int)$u['id'], (string)$u['nome'], (string)$u['papel'] === 'gestor', 'ps')]);
  }
  case 'finalizado_excluir': { /* 2.43: a lixeira do gestor nos Finalizados do dia */
    $u = exigir(); responder(excluirFinalizado($in, $u));
  }
  case 'ct_venda_corrigir': { /* 2.45: o lápis do gestor nos Vendidos do dia da Casa Tom */
    $u = exigir(); responder(corrigirVendaCT($in, $u));
  }
  case 'ct_venda_excluir': { /* 2.45: a lixeira do gestor nos Vendidos do dia da Casa Tom */
    $u = exigir(); responder(excluirVendaCT($in, $u));
  }
  case 'caixa_dia': { /* 2.26: o caixa e os finalizados de outro dia (Finalizados do dia → Ontem / outra data); quem não é o gestor vê só a unidade dele */
    $u = exigir(); $dia = (string)($in['dia'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) || !checkdate((int)substr($dia, 5, 2), (int)substr($dia, 8, 2), (int)substr($dia, 0, 4))) falha('Escolha uma data.');
    if ($dia > date('Y-m-d')) falha('Esse dia ainda não chegou.'); session_write_close(); $so = $u['papel'] === 'gestor' ? 0 : (int)(unidadeDoPedido() ?? 0);
    responder(['dia' => $dia, 'caixa' => caixaDoDia($so, $dia), 'finalizados' => finalizadosDoDia($so, $dia)]);
  }
  case 'criar_gestor': { // só quando ainda não existe ninguém
    if ((int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) falha('O primeiro gestor já foi criado. Entre com o login dele.', 409);
    // 1.49 (auditoria): o primeiro gestor só nasce nas 2 primeiras horas depois que o site novo foi aberto (quem instala cria logo em seguida);
    // depois disso, só com o arquivo "liberar-primeiro-acesso" posto na pasta de dados pelo cPanel
    $db->prepare("INSERT OR IGNORE INTO config (chave, valor) VALUES ('instalado_em', ?)")->execute([(string)time()]);
    $q0 = $db->query("SELECT valor FROM config WHERE chave = 'instalado_em'"); $inst = (int)$q0->fetchColumn(); $q0->closeCursor();
    if (time() - $inst > 7200 && !is_file(pastaDados() . '/liberar-primeiro-acesso')) falha('A instalação está aberta há mais de 2 horas sem gestor. Por segurança, ponha um arquivo chamado liberar-primeiro-acesso na pasta de dados (cPanel) e tente de novo.', 403);
    $nome = maiusculo($val('nome')); $login = loginDe($val('login')); $senha = (string)($in['senha'] ?? '');   /* 2.02: o login pode ter espaço */
    if ($nome === '' || $login === '' || strlen($senha) < 6) falha('Preencha nome, login e uma senha com pelo menos 6 caracteres.');
    $db->prepare("INSERT INTO usuarios (nome, login, senha, papel, criado_em) VALUES (?, ?, ?, 'gestor', ?)")->execute([$nome, $login, password_hash($senha, PASSWORD_DEFAULT), agora()]);
    session_regenerate_id(true); $_SESSION['uid'] = (int)$db->lastInsertId(); sessaoMarca((int)$_SESSION['uid']);
    responder(['usuario' => eu()]);
  }
  case 'entrar': {
    $login = mb_strtolower($val('login')); $senha = (string)($in['senha'] ?? '');
    $db->prepare('DELETE FROM tentativas WHERE quando < ?')->execute([time() - 900]);
    // 1.49 (auditoria): 8 erros deste login neste aparelho, ou 30 erros deste aparelho (qualquer login), em 15 min (antes, 8 erros de qualquer lugar travavam a gestora)
    $q = $db->prepare('SELECT COALESCE(SUM(login = ?), 0) l, COUNT(*) t FROM tentativas WHERE ip = ? AND quando > ?'); $q->execute([$login, $ip, time() - 900]); $tq = $q->fetch();
    if ((int)$tq['l'] >= 8 || (int)$tq['t'] >= 30) falha('Muitas tentativas. Espere 15 minutos e tente de novo.', 429);
    $q = $db->prepare('SELECT * FROM usuarios WHERE login IN (?, ?) AND ativo = 1 ORDER BY (login = ?) DESC LIMIT 1'); $q->execute([$login, loginDe($login), $login]); $u = $q->fetch();   /* 2.02: "Julia  Alves" entra como "julia alves" */
    if (!$u || !password_verify($senha, $u['senha'])) { $db->prepare('INSERT INTO tentativas (login, ip, quando) VALUES (?, ?, ?)')->execute([$login, $ip, time()]); usleep(400000); falha('Login ou senha errados.', 401); }
    session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id']; sessaoMarca((int)$u['id']); $_SESSION['uni0'] = max(0, (int)($_SERVER['HTTP_X_PLAY_UNIDADE'] ?? 0));   // 1.91: a unidade em que entrou
    responder(['usuario' => eu()]);
  }
  case 'loja_unidades': { /* 2.10: a tela de entrar do computador da loja (só com a chave dele) */
    $m = maquinaDaChave((string)($in['chave'] ?? '')); if (!$m) falha('Este computador não está mais liberado. Entre com usuário e senha, ou peça ao gestor para liberar de novo.', 403);
    responder(['unidades' => unidadesDaLoja((string)($m['local'] ?? '')), 'nome' => (string)$m['nome'], 'senha' => lojaPedeSenha(), 'local' => (string)($m['local'] ?? '')]);   /* 2.35: só as do lugar do computador */   /* 2.21: se pede a senha da unidade */
  }
  case 'maquina_lugar': { /* 2.36 (o dono: "eu selecionar uma vez onde está essa máquina, na tela de login. E daí não tem como trocar, só se eu colocar
     em configurações"): o lugar escolhido na tela de entrar, só uma vez (depois, só o gestor troca, em Ajustes → Computadores da loja) */
    $m = maquinaDaChave((string)($in['chave'] ?? '')); if (!$m) falha('Este computador não está mais liberado. Entre com usuário e senha, ou peça ao gestor para liberar de novo.', 403);
    if ((string)($m['local'] ?? '') !== '') falha('O lugar deste computador já foi escolhido. Só o gestor troca, em Ajustes → Computadores da loja.', 409);
    $loc = lojaLocalOk((string)($in['local'] ?? '')); if ($loc === '') falha('Escolha onde fica este computador.');
    $db->prepare("UPDATE maquinas SET local = ? WHERE id = ? AND local = ''")->execute([$loc, (int)$m['id']]);
    registrar('alt', null, 'Computador da loja', 'Lugar escolhido na tela de entrar: ' . $m['nome'] . ' · ' . $loc);
    responder(['unidades' => unidadesDaLoja($loc), 'local' => $loc, 'nome' => (string)$m['nome'], 'senha' => lojaPedeSenha()]);
  }
  case 'entrar_loja': { /* 2.10: um toque na unidade, sem senha (o computador liberado pelo gestor) */
    $db->prepare('DELETE FROM tentativas WHERE quando < ?')->execute([time() - 900]);
    $q = $db->prepare('SELECT COUNT(*) FROM tentativas WHERE ip = ? AND quando > ?'); $q->execute([$ip, time() - 900]); if ((int)$q->fetchColumn() >= 30) falha('Muitas tentativas. Espere 15 minutos e tente de novo.', 429);
    $m = maquinaDaChave((string)($in['chave'] ?? ''));
    if (!$m) { $db->prepare('INSERT INTO tentativas (login, ip, quando) VALUES (?, ?, ?)')->execute(['#loja', $ip, time()]); usleep(400000); falha('Este computador não está mais liberado. Entre com usuário e senha, ou peça ao gestor para liberar de novo.', 403); }
    $uni = (int)($in['unidade'] ?? 0); $lu = usuarioDaLoja($uni); if (!$lu) falha('Unidade não encontrada.', 404);
    /* 2.35 (o dono: "Morumbi só visualiza Casatom Morumbi e Morumbi"): o computador entra só nas unidades do lugar dele */
    $nu = ''; foreach (unidadesDaLoja() as $x) if ($x['id'] === $uni) $nu = $x['nome']; $loc = (string)($m['local'] ?? '');
    if ($loc !== '' && uniLocal($nu) !== $loc) falha('Este computador é de outra unidade. Toque na unidade daqui.', 403);
    $q = $db->prepare('SELECT COUNT(*) FROM tentativas WHERE login = ? AND ip = ? AND quando > ?'); $q->execute(['#loja' . $uni, $ip, time() - 900]); if ((int)$q->fetchColumn() >= 8) falha('Muitas tentativas. Espere 15 minutos e tente de novo.', 429);
    $q = $db->prepare('SELECT senha FROM usuarios WHERE id = ?'); $q->execute([(int)$lu['id']]); $h = (string)$q->fetchColumn(); $q->closeCursor();   /* 2.11: a senha da unidade */
    if (lojaPedeSenha() && !password_verify((string)($in['senha'] ?? ''), $h)) {   /* 2.21: sem a senha, provisoriamente (Ajustes → Computadores da loja) */ $db->prepare('INSERT INTO tentativas (login, ip, quando) VALUES (?, ?, ?)')->execute(['#loja' . $uni, $ip, time()]); usleep(400000); falha('Senha da unidade errada.', 401); }
    $db->prepare('UPDATE maquinas SET ultimo_uso = ? WHERE id = ?')->execute([agora(), (int)$m['id']]);
    if ($loc === '' && $nu !== '' && uniLocal($nu) !== '') $db->prepare('UPDATE maquinas SET local = ? WHERE id = ?')->execute([uniLocal($nu), (int)$m['id']]);   /* 2.35: o computador já liberado aprende o lugar na primeira entrada */
    session_regenerate_id(true); $_SESSION['uid'] = (int)$lu['id']; sessaoMarca((int)$lu['id']); $_SESSION['uni0'] = $uni; $_SESSION['maq'] = (int)$m['id'];
    responder(['usuario' => eu()]);
  }
  case 'maquina_liberar': { /* 2.10: o gestor libera este computador (a chave volta só uma vez, para ficar guardada nele) */
    $u = exigir(['gestor']); $nome = mb_substr(maiusculo(trim((string)($in['nome'] ?? ''))), 0, 40); if ($nome === '') $nome = 'COMPUTADOR DE ' . date('d/m H:i');
    if ((int)$db->query('SELECT COUNT(*) FROM maquinas WHERE ativo = 1')->fetchColumn() >= 30) falha('Já há 30 computadores liberados. Tire o acesso dos que não usam mais.');
    $loc = lojaLocalOk((string)($in['local'] ?? ''));   /* 2.35: o lugar do computador (a tela de entrar mostra só as unidades de lá) */
    $t = bin2hex(random_bytes(24)); $db->prepare('INSERT INTO maquinas (token, nome, criado_em, criado_por, local) VALUES (?, ?, ?, ?, ?)')->execute([hash('sha256', $t), $nome, agora(), (int)$u['id'], $loc]);
    registrar('alt', null, 'Computador da loja', 'Liberado para entrar pela unidade, sem senha: ' . $nome . ' · por ' . primeiroNome($u['nome']));
    responder(['chave' => $t, 'nome' => $nome, 'unidades' => unidadesDaLoja($loc), 'maquinas' => maquinasLista()]);
  }
  case 'maquinas': { exigir(['gestor']); responder(['maquinas' => maquinasLista()]); }
  case 'maquina_local': { /* 2.35: o gestor troca o lugar de um computador liberado (vazio = todas as unidades) */
    $u = exigir(['gestor']); $loc = lojaLocalOk((string)($in['local'] ?? '')); $id = (int)($in['id'] ?? 0);
    $q = $db->prepare('SELECT nome FROM maquinas WHERE id = ? AND ativo = 1'); $q->execute([$id]); $n = $q->fetchColumn(); if ($n === false) falha('Computador não encontrado.', 404);
    $db->prepare('UPDATE maquinas SET local = ? WHERE id = ?')->execute([$loc, $id]); registrar('alt', null, 'Computador da loja', 'Lugar de ' . $n . ': ' . ($loc !== '' ? $loc : 'todas as unidades') . ' · por ' . primeiroNome($u['nome']));
    responder(['maquinas' => maquinasLista()]); }
  case 'loja_senha': { /* 2.11: o gestor troca a senha de uma unidade (a do computador da loja) */
    $u = exigir(['gestor']); $uni = (int)($in['unidade'] ?? 0); $s = (string)($in['senha'] ?? ''); if (mb_strlen($s) < 4 || mb_strlen($s) > 40) falha('A senha precisa ter de 4 a 40 caracteres.');
    $lu = usuarioDaLoja($uni); if (!$lu) falha('Unidade não encontrada.', 404);
    $db->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($s, PASSWORD_DEFAULT), (int)$lu['id']]); sessaoNova((int)$lu['id']);   /* 2.60: os computadores da unidade entram de novo */
    $n = ''; foreach (unidadesDaLoja() as $x) if ($x['id'] === $uni) $n = $x['nome']; registrar('alt', null, 'Computador da loja', 'Senha da unidade ' . $n . ' trocada · por ' . primeiroNome($u['nome']));
    responder();
  }
  case 'maquina_tirar': { /* 2.10: o computador volta a pedir usuário e senha (a sessão aberta nele cai) */
    $u = exigir(['gestor']); $m = !empty($in['chave']) ? maquinaDaChave((string)$in['chave']) : null; $id = $m ? (int)$m['id'] : (int)($in['id'] ?? 0);
    $q = $db->prepare('SELECT nome FROM maquinas WHERE id = ? AND ativo = 1'); $q->execute([$id]); $n = $q->fetchColumn();
    if ($n !== false) { $db->prepare('UPDATE maquinas SET ativo = 0 WHERE id = ?')->execute([$id]); registrar('alt', null, 'Computador da loja', 'Acesso sem senha tirado: ' . $n . ' · por ' . primeiroNome($u['nome'])); }
    responder(['maquinas' => maquinasLista()]);
  }
  case 'anotacao_salvar': { /* 2.12: a anotação do cliente (toda a equipe). No computador da loja, o nome de quem escreve vem tocado */
    $u = exigir(); $c = crianca((int)($in['crianca'] ?? 0)); if (!$c) falha('Cliente não encontrado.', 404);
    $t = trim(preg_replace('/[ \t]+/u', ' ', (string)($in['texto'] ?? ''))); $t = mb_substr($t, 0, 500); if ($t === '') falha('Escreva a anotação.');
    $quem = mb_substr(maiusculo((string)($in['quem'] ?? '')), 0, 30); $un = unidadeDoPedido();
    if ($quem === '' || !in_array($quem, array_merge($un ? vendedorasDaUnidade((int)$un) : vendedorasTodas(), vendedoras()), true)) { if (!empty($u['loja'])) falha('Toque em quem está escrevendo.'); $quem = mb_substr(maiusculo((string)$u['nome']), 0, 30); }
    $db->prepare('INSERT INTO anotacoes (crianca_id, texto, quando, por, por_id) VALUES (?, ?, ?, ?, ?)')->execute([(int)$c['id'], $t, agora(), $quem, (int)$u['id']]);
    responder(['anotacoes' => anotacoesLista()]);
  }
  case 'anotacao_apagar': { /* 2.12: quem escreveu (no mesmo login) ou o gestor */
    $u = exigir(); $q = $db->prepare('SELECT * FROM anotacoes WHERE id = ? AND apagada = 0'); $q->execute([(int)($in['id'] ?? 0)]); $x = $q->fetch(); if (!$x) falha('Anotação não encontrada.', 404);
    if ($u['papel'] !== 'gestor' && (int)$x['por_id'] !== (int)$u['id']) falha('Só quem escreveu ou o gestor apaga a anotação.', 403);
    $db->prepare('UPDATE anotacoes SET apagada = 1 WHERE id = ?')->execute([(int)$x['id']]);
    responder(['anotacoes' => anotacoesLista()]);
  }
  case 'sair': { $_SESSION = []; session_destroy(); responder(); }
  case 'trocar_senha': {
    $u = exigir(); $atual = (string)($in['atual'] ?? ''); $nova = (string)($in['nova'] ?? '');
    $q = $db->prepare('SELECT senha FROM usuarios WHERE id = ?'); $q->execute([$u['id']]);
    if (!password_verify($atual, (string)$q->fetchColumn())) falha('A senha atual não confere.');
    if (strlen($nova) < 6) falha('A senha nova precisa ter pelo menos 6 caracteres.');
    $db->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $u['id']]); sessaoNova((int)$u['id']);   /* 2.60: sai dos outros computadores (este continua) */
    responder();
  }

  /* ---------- tudo que a tela mostra ---------- */
  case 'dados': { $u = exigir(); session_write_close(); $d = dados($u);
    if (!empty($in['enx'])) {   /* 2.60: a lista leve e a assinatura (o que muda sozinho no iDFace a cada contato não conta) */
      $idf = is_array($d['idface'] ?? null) ? $d['idface'] : []; $fixo = $idf;
      foreach (['segundos', 'ultimo_contato', 'ultima_batida', 'periodo', 'erro', 'fila'] as $k) unset($fixo[$k]);
      $base = $d; unset($base['agora'], $base['idface']); $j = json_encode($base, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); $sig = $j === false ? '' : md5($j . '|' . json_encode($fixo)); unset($base, $j);
      if ($sig !== '' && (string)($in['sig'] ?? '') === $sig) responder(['igual' => 1, 'sig' => $sig, 'agora' => $d['agora'], 'idface' => $idf]);
      $d['criancas'] = criancasEnxutas($d['criancas']); $d['sig'] = $sig; }
    responder($d); }
  case 'gz_teste': { exigir(); responder(['eco' => str_repeat('Play Space · ', 900)]); }   // 1.01: a tela confere, uma vez, se lê a resposta comprimida deste servidor
  case 'copia_fazer': { exigir(['gestor']); $n = copiaSemanal(true); if (!$n) falha('Uma cópia já está sendo feita agora. Espere um instante e confira a lista.', 409); responder(['copia' => copiaInfo()]); }   // 1.51
  case 'copia_baixar': {   // 1.51: o gestor baixa uma cópia da semana (a mais nova, se não disser qual). O arquivo vai inteiro, sem passar pelo JSON
    $u = exigir(['gestor']); $L = copiasDaSemana(); if (!$L) falha('Ainda não há cópia. Toque em "Fazer uma cópia agora".', 404);
    $nome = trim((string)($in['nome'] ?? '')); $arq = $nome === '' ? $L[0] : pastaDados() . '/copias/' . basename($nome);
    if (!preg_match('/^semana-\d{8}-\d{6}-[0-9a-f]+\.sqlite$/', basename($arq)) || !in_array($arq, $L, true)) falha('Cópia não encontrada.', 404);
    $tmp = (int)cfg('fin_dono', 0) === (int)$u['id'] ? null : copiaSemFinancas($arq); $serve = $tmp ?: $arq;   /* 1.91 (auditoria): sem as Finanças, a não ser para o dono delas */
    gravarCfg('copia_baixada', ['nome' => basename($arq), 'em' => date('Y-m-d H:i:s'), 'por' => (string)$u['nome']]); session_write_close();
    $m = []; preg_match('/^semana-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})/', basename($arq), $m);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/octet-stream'); header('Content-Length: ' . filesize($serve)); header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="playspace-copia-' . $m[1] . '-' . $m[2] . '-' . $m[3] . '-' . $m[4] . 'h' . $m[5] . '.sqlite"');
    readfile($serve); if ($tmp) @unlink($tmp); exit;
  }
  case 'email_resumo': { // 1.56: o relatório do dia por e-mail, nos Ajustes (o mesmo das Finanças → Conta): ver, salvar, mandar agora. Só o gestor
    exigir(['gestor']); require_once __DIR__ . '/../financas/resumo.php'; $op = (string)($in['op'] ?? 'ver');
    if ($op === 'salvar') responder(['email' => finEmailSalvar($in)]);
    if ($op === 'enviar') { $c = finEmailCfg(); if (!$c['para']) falha('Cadastre pelo menos um e-mail e salve.');
      $r = finEmailMandar(date('Y-m-d'), $c['para']); $c['res'] = $r['ok'] ? 'ok' : $r['erro']; $c['quando'] = date('Y-m-d H:i:s'); $d = finDominio($c); if ($d !== '') $c['dominio'] = $d; finEmailGuarda($c);
      if (!$r['ok']) falha($r['erro'], 502); responder(['email' => finEmailPublico(), 'enviado' => $r]); }
    responder(['email' => finEmailPublico()]);
  }
  case 'meta_plano': { /* 1.87: a meta do mês de uma unidade (só o gestor): o valor do mês (0 tira), o mesmo mês do ano passado e a % (só para
       lembrar a conta), o peso do fim de semana e os dias fixados; e os dois alertas (valem para todas as unidades) */
    $u = exigir(['gestor']); $uni = (int)($in['uni'] ?? 0); $mes = (string)($in['mes'] ?? '');
    $q = db()->prepare('SELECT id FROM unidades WHERE id = ? AND ativo = 1'); $q->execute([$uni]); if (!$q->fetchColumn()) falha('Escolha a unidade.');
    if (!preg_match('/^(20\d\d)-(0[1-9]|1[0-2])$/', $mes)) falha('Escolha o mês.');
    $num = fn($k, $max) => ($in[$k] ?? null) === null || ($in[$k] ?? '') === '' ? null : (is_numeric($in[$k]) && (float)$in[$k] >= 0 && (float)$in[$k] < $max ? round((float)$in[$k], 2) : false);
    $v = $num('valor', 100000000); if ($v === false) falha('Escreva a meta do mês, por exemplo 410.000,00.'); $v = (float)$v;
    $base = $num('base', 100000000); if ($base === false) falha('Escreva o faturamento do ano passado em reais.');
    $pct = $in['pct'] ?? null; $pct = $pct === null || $pct === '' ? null : (is_numeric($pct) && (float)$pct > -100 && (float)$pct <= 1000 ? round((float)$pct, 2) : false); if ($pct === false) falha('Escreva a porcentagem, por exemplo 15.');
    $fds = $in['fds'] ?? 2; if (!is_numeric($fds) || (float)$fds < 1 || (float)$fds > 5) falha('O peso do fim de semana vai de 1 a 5 (2 = vale o dobro de um dia de semana).'); $fds = round((float)$fds, 1);
    $fixos = [];
    foreach (is_array($in['fixos'] ?? null) ? $in['fixos'] : [] as $dia => $fv) {
      $dia = (string)$dia; if ($fv === null || $fv === '') continue;
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) || substr($dia, 0, 7) !== $mes || !checkdate((int)substr($dia, 5, 2), (int)substr($dia, 8, 2), (int)substr($dia, 0, 4))) falha('Um dia fixado não é deste mês.');
      if (!is_numeric($fv) || (float)$fv < 0 || (float)$fv >= 10000000) falha('A meta de ' . substr($dia, 8, 2) . '/' . substr($dia, 5, 2) . ' tem que ser um valor em reais.');
      $fixos[$dia] = round((float)$fv, 2);
    }
    ksort($fixos);
    if ($v > 0 && array_sum($fixos) > $v + 0.004) falha('Os dias fixados somam ' . dinheiro(array_sum($fixos)) . ', mais que a meta do mês (' . dinheiro($v) . '). Diminua algum dia ou aumente a meta.');   /* 1.91 (auditoria) */
    if (array_key_exists('alertas', $in)) {   /* 1.81: os dois alertas (quanto falta para a meta do dia) */
      $al = []; foreach (array_slice(is_array($in['alertas']) ? $in['alertas'] : [], 0, 2) as $a) {
        if ($a === null || $a === '' || (is_numeric($a) && (float)$a == 0)) continue;
        if (!is_numeric($a) || (float)$a < 0 || (float)$a >= 10000000) falha('Escreva o alerta em reais, por exemplo 500,00.');
        $al[] = round((float)$a, 2); }
      $al = array_values(array_unique($al, SORT_REGULAR)); rsort($al); if ($al) gravarCfg('meta_alertas', $al); else apagarCfg('meta_alertas');
    }
    $c = cfg('metas', []); if (!is_array($c)) $c = []; if (!is_array($c['u'] ?? null)) $c['u'] = [];
    if ($v > 0) $c['u'][$uni][$mes] = ['valor' => $v, 'base' => $base, 'pct' => $pct, 'fds' => $fds, 'fixos' => (object)$fixos, 'por' => (string)($u['nome'] ?? ''), 'em' => agora()];
    else { unset($c['u'][$uni][$mes]); if (empty($c['u'][$uni])) unset($c['u'][$uni]); }
    if (empty($c['u'])) $c['u'] = (object)[];
    gravarCfg('metas', $c);
    responder(['meta' => metaDoDia($u), 'mes' => metaMesVer($uni, $mes)]);
  }
  case 'meta_mes': { /* 1.87: o mês de uma unidade (o calendário da meta): todos veem; só o gestor muda */
    exigir(); $uni = (int)($in['uni'] ?? 0); $mes = (string)($in['mes'] ?? '');
    if (!preg_match('/^(20\d\d)-(0[1-9]|1[0-2])$/', $mes)) falha('Escolha o mês.');
    session_write_close(); responder(['mes' => metaMesVer($uni, $mes)]);
  }
  case 'venda_vezes': { /* 2.39: ajustar as vezes no crédito de uma compra já feita */
    $u = exigir(); responder(ajustarVezes($in, $u));
  }
  case 'caixa_pagamentos': { /* 2.50: os pagamentos do caixa do dia (Finalizados do dia → Ajustar caixa); quem não é o gestor vê só a unidade dele */
    $u = exigir(); $dia = (string)($in['dia'] ?? ''); if ($dia === '') $dia = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) || !checkdate((int)substr($dia, 5, 2), (int)substr($dia, 8, 2), (int)substr($dia, 0, 4))) falha('Escolha uma data.');
    if ($dia > date('Y-m-d')) falha('Esse dia ainda não chegou.'); session_write_close();
    $uni = $u['papel'] === 'gestor' ? (int)($in['uni'] ?? 0) : (int)(unidadeDoPedido() ?? 0);
    responder(pagamentosDoCaixa($dia, $uni));
  }
  case 'cm_dia': { /* 2.53: Ajustes de comissão: as comissões de um dia (as regras das Finanças), por unidade. Só o gestor */
    $u = exigir(['gestor']); $dia = (string)($in['dia'] ?? ''); if ($dia === '') $dia = date('Y-m-d');
    $mes = (string)($in['mes'] ?? '');   /* 2.67: a aba Comissões pede o mês inteiro (AAAA-MM) */
    if ($mes !== '') { if (!preg_match('/^(\d{4})-(\d{2})$/', $mes, $mm) || (int)$mm[2] < 1 || (int)$mm[2] > 12) falha('Escolha o mês.'); if ($mes > date('Y-m')) falha('Esse mês ainda não chegou.'); $dia = $mes; }
    elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) || !checkdate((int)substr($dia, 5, 2), (int)substr($dia, 8, 2), (int)substr($dia, 0, 4))) falha('Escolha uma data.');
    if ($mes === '' && $dia > date('Y-m-d')) falha('Esse dia ainda não chegou.'); session_write_close();
    require_once __DIR__ . '/../financas/resumo.php'; require_once __DIR__ . '/../financas/comissoes.php'; require_once __DIR__ . '/../financas/pdfbonito.php';   /* 2.67: o nome do mês (finMesNome) */
    $R = cmListar(['per' => $dia, 'u' => (isset($in['u']) && (int)$in['u'] > 0) ? (int)$in['u'] : 'todas']); unset($R['hist']);
    responder($R + ['vend_todas' => array_values(array_unique(array_merge(vendedorasTodas(), vendedoras())))]);
  }
  case 'cm_ajustar': { /* 2.53: trocar quem fez a venda (a comissão vai junto). Só o gestor */
    $u = exigir(['gestor']); responder(comissaoAjustar($in, $u));
  }
  case 'ct_sinais': { /* 2.52: Casa Tom → Sinais: quem deu o sinal de 50% e ainda vai buscar; quem não é o gestor vê só a unidade dele */
    $u = exigir(); session_write_close(); responder(ctSinaisLista($u['papel'] === 'gestor' ? (int)($in['uni'] ?? 0) : (int)(unidadeDoPedido() ?? 0)));
  }
  case 'ct_sinal_receber': { /* 2.52: na retirada, o resto do sinal */
    $u = exigir(); responder(ctSinalReceber($in, $u));
  }
  case 'pagamento_trocar': { // 1.57: trocar a forma de pagamento; 1.71: a equipe também a qualquer hora, e a forma pode ser dividida (partes)
    $u = exigir(); responder(trocarPagamento($in, $u));
  }
  case 'resumo_pdf': { // 1.54: o resumo do dia em PDF (o mesmo das Finanças), para o gestor
    $u = exigir(); if (($u['papel'] ?? '') !== 'gestor') falha('Só o gestor baixa o resumo do dia.', 403); session_write_close();
    require_once __DIR__ . '/../financas/resumo.php'; finPdfSaida(['de' => (string)($in['de'] ?? date('Y-m-d')), 'ate' => (string)($in['ate'] ?? ($in['de'] ?? date('Y-m-d'))), 'uni' => 'todas']);
  }
  case 'pulso': { // barato: muda quando qualquer coisa que a tela mostra mudou; a tela pergunta a cada 1 s (1.83; eram 2 s) e só recarrega se mudou
    $u = exigir(); session_write_close(); $tc0 = mudancas();   /* 2.66: a conta do pulso uma vez para todas as telas */
    try { copiaSemanal(); } catch (Throwable $e) { registrarErro('cópia da semana: ' . $e->getMessage()); }
    try { require_once __DIR__ . '/../financas/resumo.php'; if (finEmailDevido()) { require_once __DIR__ . '/push-lib.php'; pushDepois(function () { finEmailAuto(); }); } } catch (Throwable $e) { registrarErro('e-mail do resumo do dia: ' . $e->getMessage()); }   // 1.54: o resumo do dia por e-mail, uma vez por dia, quando a loja fecha   // 1.51: a cada 7 dias, a cópia de segurança (antes de qualquer consulta; nunca derruba o pulso)
    try { metaDoDia(null); } catch (Throwable $e) { registrarErro('meta do dia: ' . $e->getMessage()); }   /* 1.81: a meta (e o aviso no celular) também sem esperar uma tela recarregar; 1.87: as metas do mês de cada unidade */
    try { baixaAutomatica(); } catch (Throwable $e) { registrarErro('baixa automática: ' . $e->getMessage()); }   // 1.90
    if (aCada('acertar', 1)) { acertarTodas(); acertarCupons(); }   /* passou da tolerância, nasce a nova cobrança (e o pulso muda, então todas as telas mostram). 2.66: uma vez por segundo para todas as telas juntas (era a cada pulso de cada tela) */
    if (mudancas() > $tc0) bancoMudou();   /* 2.66: as tarefas gravaram (nasceu uma cobrança): o pulso é refeito agora */
    $p = cacheCurto('pulso', 1.5, function () use ($db) {
    $a = $db->query("SELECT COALESCE(MAX(id), 0) || ':' || COUNT(*) FROM registros")->fetchColumn();
    $b = $db->query("SELECT COALESCE(MAX(id), 0) || ':' || COUNT(*) || ':' || COALESCE(SUM(lida), 0) FROM mensagens")->fetchColumn();
    $c = $db->query("SELECT COUNT(*) || ':' || COALESCE(SUM(pacote_min), 0) || ':' || COALESCE(SUM(usado_min), 0) || ':' || COALESCE(SUM(liberado), 0) || ':' || COALESCE(SUM(pcd), 0) || ':' || COALESCE(SUM(sapateira), 0) || ':' || COALESCE(SUM(sapateira IS NOT NULL), 0) || ':' || COALESCE(SUM(rosto), 0) || ':' || COALESCE(SUM(ativo), 0) || ':' || COALESCE(SUM(entrou IS NOT NULL), 0) || ':' || COALESCE(MAX(entrou), '') || ':' || COALESCE(SUM(idface_id), 0) || ':' || COALESCE(SUM(unidade_id), 0) || ':' || COALESCE(SUM(LENGTH(foto) + LENGTH(nome) + LENGTH(resp) + LENGTH(tel) + LENGTH(tel2) + LENGTH(cpf) + LENGTH(doc_tipo) + LENGTH(obs) + idade + COALESCE(CAST(REPLACE(nascimento, '-', '') AS INTEGER), 0)), 0) FROM criancas")->fetchColumn();
    $d = $db->query("SELECT COUNT(*) || ':' || COALESCE(SUM(feito), 0) || ':' || COALESCE(MAX(id), 0) || ':' || COALESCE(SUM(LENGTH(titulo) + LENGTH(dia) + LENGTH(hora)), 0) FROM compromissos")->fetchColumn();
    $ps = cfg('push_estado', []); $pe = cfg('ponte_estado', []); $em = (is_array($ps) ? ($ps['em'] ?? null) : null) ?: (is_array($pe) ? ($pe['em'] ?? null) : null);
    $ap = ($em && time() - strtotime((string)$em) < 120) ? 1 : 0; $ic = cfg('idface', []); $lig = !is_array($ic) || !array_key_exists('ligado', $ic) || $ic['ligado'] ? 1 : 0;
    $d .= '|' . $db->query("SELECT COUNT(*) || ':' || COALESCE(SUM(visto_em IS NOT NULL), 0) FROM cancelamentos")->fetchColumn();   // um cancelamento conferido pela supervisão também muda a tela
    $d .= '|' . $db->query("SELECT COUNT(*) || ':' || COALESCE(SUM(ativo), 0) || ':' || COALESCE(SUM(estoque), 0) || ':' || COALESCE(SUM(estoque IS NULL), 0) || ':' || COALESCE(SUM(valor), 0) || ':' || COALESCE(SUM(LENGTH(nome)), 0) || ':' || COALESCE(MAX(mudou_em), '') FROM produtos")->fetchColumn();   // produto novo, valor novo ou estoque que mudou
    $d .= '|' . $db->query("SELECT COUNT(*) || ':' || COALESCE(SUM(alug_min), 0) || ':' || COALESCE(SUM(qtd), 0) || ':' || COALESCE(MAX(id), 0) FROM vendas WHERE alug != '' AND voltou IS NULL")->fetchColumn();   // o que está fora da loja (carrinho, fusquinha)
    $d .= '|' . $db->query("SELECT COUNT(*) || ':' || COALESCE(SUM(ativo), 0) || ':' || COALESCE(SUM(LENGTH(nome)), 0) || ':' || COALESCE(MAX(mudou_em), '') FROM unidades")->fetchColumn();   // 1.06: unidade nova, com outro nome ou removida
    $d .= '|' . $db->query("SELECT COALESCE(SUM(status = 'liberado'), 0) || ':' || COALESCE(SUM(status = 'usado'), 0) FROM pedidos WHERE tipo = 'nf'")->fetchColumn();   // 1.23: nota fiscal autorizada ou emitida
    $d .= '|' . festasPulso();   // 1.29: festa marcada, tema ou dia que mudou, criança que entrou na festa
    $d .= '|' . pulsoCupons();   // 0.93: o cupom fiscal de cada pagamento (nasceu, foi marcado, ou a venda foi desfeita)
    return md5("$a|$b|$c|$d|$ap|$lig|" . json_encode(whatsResumo()) . '|' . json_encode(regra()) . '|' . (cfg('exemplo') ? 1 : 0) . '|' . json_encode(pendFora()) . '|' . md5(metaPulso()) . '|' . date('Y-m-d')); });
    responder(['p' => $p, 'ped' => pedidosDaTela($u), 'av' => avisosDaGestora($u)]);   // 1.25: av = os cancelamentos que a gestora ainda não viu   // 1.23: ped = os pedidos de autorização desta tela (chegam mesmo com uma folha aberta)   // quem saiu de "Falta finalizar" e a virada do dia também mudam a tela
  }

  /* ---------- crianças ---------- */
  case 'crianca_pcd': { /* 2.56 (o dono: "uma opção que fique bloqueada, escrito PCD, que só o gestor pode marcar"): o botão PCD da ficha. O gestor
       marca ou desmarca; os outros, com a senha do gestor. No meio da visita, a recreação lançada (e não paga) acompanha */
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Cliente não encontrado.', 404);
    $pcd = empty($in['pcd']) ? 0 : 1; $como = '';
    if ($u['papel'] !== 'gestor') {
      if (!array_key_exists('senha', $in)) falha('Só o gestor ' . ($pcd ? 'marca' : 'desmarca') . ' a criança PCD. Digite a senha do gestor.', 403);
      $g = autorizadorPelaSenha('pcd', (string)$in['senha'], $u); $como = ' (senha do gestor ' . maiusculo(primeiroNome((string)$g['nome'])) . ')';
    }
    if ((int)($c['pcd'] ?? 0) === $pcd) responder(['ok' => true, 'pcd' => $pcd, 'ja' => true]);
    $db->exec('BEGIN IMMEDIATE');
    try { $db->prepare('UPDATE criancas SET pcd = ? WHERE id = ?')->execute([$pcd, (int)$c['id']]); $mud = pcdRefazVisita($c, $pcd); $db->exec('COMMIT'); }
    catch (Throwable $e) { $db->exec('ROLLBACK'); throw $e; }
    registrar('cad', (int)$c['id'], (string)$c['nome'], ($pcd ? 'Marcada como criança PCD: ' . pcdDesc() . '% de desconto na recreação' : 'Deixou de estar marcada como criança PCD')
      . (!empty($c['entrou']) ? ' · durante a visita (entrou ' . substr((string)$c['entrou'], 11, 5) . ')' : '') . ' · por ' . primeiroNome((string)$u['nome']) . $como);
    responder(['ok' => true, 'pcd' => $pcd, 'visita' => $mud]);
  }
  case 'crianca_salvar': {
    $u = exigir(); $id = (int)($in['id'] ?? 0);
    if ($id) {
      if ($u['papel'] !== 'gestor') falha('Só o gestor muda um cadastro (em Ajustes → Cadastros).', 403);   // 1.00: a equipe cadastra e atende; mudar o que já está cadastrado é do administrador
      $c = crianca($id); if (!$c) falha('Criança não encontrada.', 404);
      $d = textoCriancaBasico($in, $c); conferirCadastro($d, $c);
      marcarObs($c, (string)$d['obs'], $u);   // a observação mudou: a anterior continua guardada, com quem mudou
      $db->prepare('UPDATE criancas SET nome = ?, idade = ?, resp = ?, rel = ?, tel = ?, tel2 = ?, doc_tipo = ?, cpf = ?, obs = ?, pcd = ?, nascimento = ? WHERE id = ?')->execute([$d['nome'], $d['idade'], $d['resp'], $d['rel'], $d['tel'], $d['tel2'], $d['doc'], $d['cpf'], $d['obs'], $d['pcd'], $d['nascimento'] !== '' ? $d['nascimento'] : null, $id]);
      // marcar (ou desmarcar) PCD muda o preço: fica anotado no dia, com quem fez
      if ((int)($c['pcd'] ?? 0) !== $d['pcd']) pcdRefazVisita($c, $d['pcd']);   /* 2.56: no meio da visita, a recreação lançada acompanha */
      if ((int)($c['pcd'] ?? 0) !== $d['pcd']) registrar('cad', $id, $d['nome'], ($d['pcd'] ? 'Marcada como criança PCD: ' . pcdDesc() . '% de desconto na recreação' : 'Deixou de estar marcada como criança PCD') . ' · por ' . primeiroNome($u['nome']));
      // o nome mudou: o aparelho recebe o nome novo
      if ($c['idface_id'] !== null && $c['nome'] !== $d['nome']) gravarCfg('renomear_' . $id, (int)$c['idface_id']);
      responder(['id' => $id]);
    }
    $d = textoCriancaBasico($in); conferirCadastro($d);
    if ($d['tel'] === '' && $d['tel2'] !== '') { $d['tel'] = $d['tel2']; $d['tel2'] = ''; }   // só um telefone: ele é o principal (é para onde vão os avisos)
    $horas = 0;   // 1.45 (auditoria): o cadastro não vende mais horas (o caminho antigo, de antes da 0.98, aceitava qualquer valor, até negativo)
    $foto = (string)($in['foto'] ?? ''); if ($foto !== '' && !preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $foto)) $foto = '';
    if (strlen($foto) > 1500000) falha('A foto ficou grande demais. Tire outra.');
    $sap = max(0, (int)($in['sapateira'] ?? 0));   // (até a 1.15 a sapateira era escolhida no cadastro; desde a 1.16 a tela pergunta a cada entrada e não manda mais nada aqui)
    if ($sap > (int)regra()['sapateiras']) falha('Essa sapateira não existe.');
    if ($sap && ($dono = donoSapateira($sap))) falha('A sapateira ' . $sap . ' já está com ' . $dono['nome'] . '. Escolha outra.');
    // o cadastro não vende mais o pacote: sem horas, o acesso nasce bloqueado (se a regra é bloquear sozinho) e a primeira venda libera
    $liberado = ($horas > 0 || regra()['bloqueioAuto'] !== 'sim') ? 1 : 0;
    $db->prepare('INSERT INTO criancas (nome, idade, resp, rel, tel, tel2, doc_tipo, cpf, obs, pacote_min, usado_min, foto, foto_origem, rosto, liberado, criado_em, pcd, nascimento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?)')->execute([$d['nome'], $d['idade'], $d['resp'], $d['rel'], $d['tel'], $d['tel2'], $d['doc'], $d['cpf'], $d['obs'], $foto, $foto !== '' ? 'celular' : '', $foto !== '' ? 1 : 0, $liberado, agora(), $d['pcd'], $d['nascimento'] !== '' ? $d['nascimento'] : null]);
    $id = (int)$db->lastInsertId(); if ($d['obs'] !== '') $db->prepare('UPDATE criancas SET obs_em = ?, obs_por = ? WHERE id = ?')->execute([agora(), primeiroNome((string)$u['nome']), $id]);
    /* 2.08: quem está cadastrando (conta nas Comissões): o nome tocado na tela, se é vendedor desta unidade; sem ele, quem está logado */
    $cp = mb_substr(maiusculo((string)($in['cad_por'] ?? '')), 0, 30); $un = unidadeDoPedido(); $ok = $cp !== '' && in_array($cp, array_merge($un ? vendedorasDaUnidade((int)$un) : vendedorasTodas(), vendedoras()), true);
    $db->prepare('UPDATE criancas SET cad_por = ?, cad_uni = ? WHERE id = ?')->execute([$ok ? $cp : (!empty($u['loja']) ? '' : mb_substr(maiusculo((string)$u['nome']), 0, 30)), $un, $id]);   /* 2.10: a "loja" não conta como quem cadastrou */
    if ($d['pcd']) registrar('cad', $id, $d['nome'], 'Cadastrada como criança PCD: ' . pcdDesc() . '% de desconto na recreação · por ' . primeiroNome($u['nome'])); if ($sap) $db->prepare('UPDATE criancas SET sapateira = ? WHERE id = ?')->execute([$sap, $id]); $c = crianca($id);
    gravarCfg('mandar_' . $id, $id); // vai para o iDFace (nome agora, foto quando o aparelho confirmar o cadastro)
    responder(['id' => $id]);
  }
  case 'cadastro_corrigir': { // 1.19: a equipe acerta o nome (do responsável ou da criança) e os telefones digitados errado, pela ficha.
    // Qualquer pessoa da equipe faz. Não muda o documento nem a data de nascimento (isso é do gestor, em Ajustes → Cadastros) e não apaga nada.
    // O nome do responsável e os telefones valem para os irmãos (é o mesmo cliente), onde estavam iguais. Fica uma linha no dia: de quê para quê, e quem mudou.
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    $e = []; foreach (['nome', 'resp', 'tel', 'tel2'] as $k) if (array_key_exists($k, $in)) $e[$k] = (string)$in[$k];
    $d = textoCriancaBasico($e, $c); conferirCadastro($d, $c);
    if ($d['tel'] === '' && $d['tel2'] !== '') { $d['tel'] = $d['tel2']; $d['tel2'] = ''; }   // só um telefone: ele é o principal
    $vazio = fn(string $t) => $t === '' ? '(vazio)' : $t; $mud = [];
    $kn = fn($t) => maiusculo((string)$t);   // o nome comparado sem ligar para maiúscula e espaço sobrando
    $kt = function ($t) { $n = (string)preg_replace('/\D/', '', (string)$t); return strlen($n) >= 8 ? substr($n, -8) : $n; };   // o telefone comparado pelo final (como a tela junta os irmãos)
    $mResp = $d['resp'] !== $kn($c['resp']); $mNome = $d['nome'] !== $kn($c['nome']); $mTel = $d['tel'] !== trim((string)$c['tel']); $mTel2 = $d['tel2'] !== trim((string)$c['tel2']);
    if ($mResp) $mud[] = 'responsável de "' . $c['resp'] . '" para "' . $d['resp'] . '"';
    if ($mNome) $mud[] = 'criança de "' . $c['nome'] . '" para "' . $d['nome'] . '"';
    if ($mTel) $mud[] = 'telefone de ' . $vazio((string)$c['tel']) . ' para ' . $vazio($d['tel']);
    if ($mTel2) $mud[] = 'outro telefone de ' . $vazio((string)$c['tel2']) . ' para ' . $vazio($d['tel2']);
    if (!$mud) responder(['id' => (int)$c['id'], 'mudou' => 0, 'irmaos' => 0]);
    // Quem acompanha: os outros cadastros do mesmo cliente. Pelo documento, sempre. Quando o que junta é só o telefone (cadastro antigo, sem documento),
    // só com o mesmo nome de responsável: um telefone repetido não leva a correção para o cadastro de outra pessoa. E cada dado só muda no irmão onde
    // estava igual ao daqui: o que a tela não mostrou (um telefone diferente no cadastro do irmão) não é sobrescrito.
    $irm = irmaosDe($c); $doc = chaveDocCadastro($c); $junto = [];   // antes de mudar: é pelo documento (ou pelo telefone) de agora que os irmãos são achados
    $db->beginTransaction();
    try {
      $db->prepare('UPDATE criancas SET nome = ?, resp = ?, tel = ?, tel2 = ? WHERE id = ?')->execute([$d['nome'], $d['resp'], $d['tel'], $d['tel2'], (int)$c['id']]);
      $qi = $db->prepare('UPDATE criancas SET resp = ?, tel = ?, tel2 = ? WHERE id = ?');
      $qc = $db->prepare("UPDATE cupons SET nome = ?, resp = ? WHERE crianca_id = ? AND status = 'pendente'");   // o cupom fiscal ainda não emitido sai com o nome certo (o já emitido fica como saiu)
      $qc->execute([$d['nome'], $d['resp'], (int)$c['id']]);
      foreach ($irm as $x) {
        if (!(($doc !== '' && chaveDocCadastro($x) === $doc) || ($kn($c['resp']) !== '' && $kn($x['resp']) === $kn($c['resp'])))) continue;
        $n = ['resp' => (string)$x['resp'], 'tel' => (string)$x['tel'], 'tel2' => (string)$x['tel2']];
        if ($mResp && $kn($x['resp']) === $kn($c['resp'])) $n['resp'] = $d['resp'];
        if ($mTel && $kt($x['tel']) === $kt($c['tel'])) $n['tel'] = $d['tel'];
        if ($mTel2 && $kt($x['tel2']) === $kt($c['tel2'])) $n['tel2'] = $d['tel2'];
        if ($n['resp'] === (string)$x['resp'] && $n['tel'] === (string)$x['tel'] && $n['tel2'] === (string)$x['tel2']) continue;
        $qi->execute([$n['resp'], $n['tel'], $n['tel2'], (int)$x['id']]); $qc->execute([(string)$x['nome'], $n['resp'], (int)$x['id']]); $junto[] = (string)$x['nome'];
      }
      $tambem = $junto ? ' · também em ' . (count($junto) > 1 ? implode(', ', array_slice($junto, 0, -1)) . ' e ' . $junto[count($junto) - 1] : $junto[0]) : '';
      registrar('cad', (int)$c['id'], $d['nome'], 'Cadastro corrigido: ' . implode(' · ', $mud) . $tambem . ' · por ' . primeiroNome((string)$u['nome']));
      if ($c['idface_id'] !== null && $c['nome'] !== $d['nome']) gravarCfg('renomear_' . (int)$c['id'], (int)$c['idface_id']);   // o aparelho recebe o nome novo
      $db->commit();
    } catch (Throwable $t) { $db->rollBack(); throw $t; }
    responder(['id' => (int)$c['id'], 'mudou' => count($mud), 'irmaos' => count($junto)]);
  }
  case 'obs_salvar': { // a observação escrita (ou mudada) na venda ou na comanda, sem abrir o cadastro inteiro. Vazia apaga; a anterior continua guardada
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    marcarObs($c, mb_substr(emMaiusculas((string)($in['obs'] ?? '')), 0, 500), $u); $c = crianca((int)$c['id']);
    responder(['obs' => (string)$c['obs'], 'obs_antes' => (string)$c['obs_antes'], 'obs_em' => $c['obs_em'], 'obs_por' => (string)$c['obs_por']]);
  }
  case 'recado_salvar': { /* 2.72: o recado do responsável (toda a equipe): fica à vista, em vermelho, ao lado do nome do responsável. Vale o último escrito na família; vazio apaga */
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Cliente não encontrado.', 404);
    $t = mb_substr(trim((string)preg_replace('/\s+/u', ' ', emMaiusculas((string)($in['texto'] ?? '')))), 0, 80); $quem = primeiroNome((string)$u['nome']);
    $em = agora() . sprintf('.%03d', (int)(fmod(microtime(true), 1) * 1000));   /* (com os milésimos: o último da família, mesmo no mesmo segundo) */
    $db->prepare('UPDATE criancas SET recado = ?, recado_em = ?, recado_por = ? WHERE id = ?')->execute([$t, $em, $quem, (int)$c['id']]);
    registrar('obs', (int)$c['id'], (string)$c['nome'], ($t !== '' ? 'Recado do responsável: ' . $t : 'Recado do responsável apagado') . ' · por ' . $quem);
    responder(['rec' => $t]);
  }
  case 'crianca_apagar': {
    $u = exigir(['gestor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);   // 1.00: remover cadastro é só do gestor
    // 0.92: as horas compradas nunca expiram. Remover o cadastro esconde o saldo: com horas guardadas, só o gestor remove, e confirmando.
    // Nada é apagado do banco (o cadastro fica inativo, com as horas, as vendas e as visitas), e a remoção vira uma linha do dia.
    $guardado = $c['ativo'] ? restante($c) : 0;
    if ($guardado > 0 && $u['papel'] !== 'gestor') falha($c['nome'] . ' tem ' . hm($guardado) . ' guardadas, e as horas não expiram. Só o gestor remove um cadastro com horas.', 403);
    if ($guardado > 0 && empty($in['confirmado'])) falha($c['nome'] . ' tem ' . hm($guardado) . ' guardadas, e as horas não expiram. Confirme para remover o cadastro mesmo assim.', 409);
    if ($guardado > 0) registrar('cad', (int)$c['id'], (string)$c['nome'], 'Cadastro removido com ' . hm($guardado) . ' guardadas (as horas continuam no banco de dados) · por ' . primeiroNome((string)$u['nome']));
    $db->prepare('UPDATE criancas SET ativo = 0, idface_id = NULL, sapateira = NULL WHERE id = ?')->execute([$c['id']]);
    if ($c['idface_id'] !== null) gravarCfg('apagar_' . (int)$c['idface_id'], (int)$c['idface_id']);
    apagarCfg('mandar_' . $c['id']); apagarCfg('foto_' . $c['id']);
    responder();
  }
  case 'sapateira': { // escolhe (ou tira, n = 0) a sapateira de uma criança; cada número só pode estar com uma criança de cada vez
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    $n = (int)($in['n'] ?? 0); if ($n < 0 || $n > (int)regra()['sapateiras']) falha('Essa sapateira não existe.');
    if ($n && ($dono = donoSapateira($n, (int)$c['id']))) falha('A sapateira ' . $n . ' já está com ' . $dono['nome'] . '. Escolha outra.');
    $db->prepare('UPDATE criancas SET sapateira = ? WHERE id = ?')->execute([$n ?: null, $c['id']]);
    responder(['sapateira' => $n ?: null, 'antes' => $c['sapateira'] === null ? null : (int)$c['sapateira']]);
  }
  case 'sapateira_liberar': { // deixa um número livre, esteja com quem estiver (para destravar uma sapateira esquecida)
    exigir(); $n = (int)($in['n'] ?? 0); $dono = $n > 0 ? donoSapateira($n) : null;
    if ($dono) $db->prepare('UPDATE criancas SET sapateira = NULL WHERE id = ?')->execute([$dono['id']]);
    responder(['crianca' => $dono ? (int)$dono['id'] : null]);
  }
  case 'foto_salvar': {
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $foto = (string)($in['foto'] ?? ''); if (!preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $foto)) falha('Foto inválida.');
    if (strlen($foto) > 1500000) falha('A foto ficou grande demais. Tire outra.');
    $db->prepare("UPDATE criancas SET foto = ?, foto_origem = 'celular', rosto = 1 WHERE id = ?")->execute([$foto, $c['id']]);
    if ($c['idface_id'] !== null) gravarCfg('foto_' . $c['id'], (int)$c['idface_id']); else gravarCfg('mandar_' . $c['id'], (int)$c['id']);
    responder();
  }
  case 'entrada': { // à mão. Sem o iDFace é o jeito normal: toda a equipe. Com o iDFace, só gestor e supervisor, para corrigir
    // 1.15: não exige mais um período comprado. Quem tem horas usa as horas; quem não tem entra na recreação avulsa e acerta na saída
    $manual = modoManual(); if ($manual) exigir(); else exigir(['gestor', 'supervisor']);
    $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    if (($c['lote'] ?? '') === 'balcao') falha('O cliente da loja da Casa Tom não entra no espaço.');   /* 2.00 */
    if ($c['entrou']) falha($c['nome'] . ' já está no espaço.');
    responder(fazerEntrada($c, 'mão', null, null, vendedoraDoPedido($in['vendedora'] ?? '')));   /* 1.68: quem abriu o pedido */
  }
  case 'festa_entrada': { // 1.29: a criança entra na festa de hoje, como aniversariante ou convidado: sem cobrar e sem gastar horas
    $u = exigir();   // (toda a equipe, também com o iDFace ligado: o aparelho não sabe que a criança veio para a festa)
    $f = festaComConta((int)($in['festa'] ?? 0)); if (!$f) falha('A festa não está mais marcada.', 404);
    $nomeF = (string)(crianca((int)$f['crianca_id'])['nome'] ?? '');
    if ($f['data'] !== date('Y-m-d')) falha('A festa de ' . $nomeF . ' é em ' . substr($f['data'], 8, 2) . '/' . substr($f['data'], 5, 2) . '/' . substr($f['data'], 0, 4) . ': a entrada na festa é só no dia.');
    $un = unidadeDoPedido(); if ($un !== null && $f['unidade_id'] !== null && (int)$f['unidade_id'] !== $un) { $nu = ''; foreach (unidades() as $x) if ($x['id'] === (int)$f['unidade_id']) $nu = $x['nome']; falha('A festa de ' . $nomeF . ' é na unidade ' . $nu . '. A entrada é dada lá.'); }
    $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    if ($c['entrou'] && !empty($c['festa_id'])) falha($c['nome'] . ' já está numa festa.');
    if ($c['entrou'] && substr((string)$c['entrou'], 0, 10) !== date('Y-m-d')) falha($c['nome'] . ' está no espaço desde outro dia: dê a saída antes.');   // quem entrou hoje como recreação passa para a festa
    responder(festaEntrada($f, $c, ($in['papel'] ?? '') === 'aniversariante' ? 'aniversariante' : 'convidado', $u));
  }
  case 'festa_salvar': { // 1.29: o tema, o horário, o dia (do mesmo tipo de festa) e a unidade da festa marcada. Fica na linha do dia, com quem mudou
    $u = exigir(['gestor']); $f = festaComConta((int)($in['id'] ?? 0)); if (!$f) falha('A festa não está mais marcada.', 404);   // 1.76: só o gestor muda a festa marcada (o operador agenda pela venda)
    $nomeF = (string)(crianca((int)$f['crianca_id'])['nome'] ?? ''); $mud = [];
    $tema = array_key_exists('tema', $in) ? mb_substr(maiusculo((string)$in['tema']), 0, 60) : (string)$f['tema']; if ($tema !== $f['tema']) $mud[] = 'tema ' . ($f['tema'] !== '' ? $f['tema'] . ' → ' : '') . ($tema !== '' ? $tema : 'sem tema');
    $hora = array_key_exists('hora', $in) ? horaDaFesta($in['hora'], (string)$f['dia']) : (string)$f['hora']; if ($hora !== $f['hora']) $mud[] = 'horário ' . ($hora !== '' ? $hora : 'sem horário');
    $data = (string)$f['data']; $novoTipo = null;   // 1.54: o gestor muda para qualquer dia (o tipo da festa acompanha)
    if (array_key_exists('data', $in) && (string)$in['data'] !== $f['data']) {
      $d = trim((string)$in['data']); if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) falha('Confira a data da festa.');
      if ($d < date('Y-m-d')) falha('A data nova já passou.');
      if ((int)$f['conv'] + (int)$f['aniv'] > 0) falha('A festa já começou (já tem criança que entrou nela): o dia não muda.');
      $fds = (int)date('N', strtotime($d)) >= 6; if ($fds !== ($f['dia'] === 'fds') && ($u['papel'] ?? '') === 'gestor') $novoTipo = $fds ? 'fds' : 'semana'; elseif ($fds !== ($f['dia'] === 'fds')) falha($f['dia'] === 'fds' ? 'Esta é uma festa de final de semana: o dia novo precisa ser sábado ou domingo (ou fale com o gestor para refazer a venda).' : 'Esta é uma festa de semana: num sábado ou domingo ela vira festa de final de semana. Fale com o gestor para refazer a venda.');
      $data = $d; $mud[] = 'dia ' . substr($f['data'], 8, 2) . '/' . substr($f['data'], 5, 2) . ' → ' . substr($d, 8, 2) . '/' . substr($d, 5, 2) . ($novoTipo ? ' (vira festa de ' . ($novoTipo === 'fds' ? 'final de semana' : 'semana') . '; o valor da venda não muda)' : '');
      if ($novoTipo === 'fds') $hora = '';   // (o final de semana tem o horário fixo)
    }
    $uni = $f['unidade_id'] === null ? null : (int)$f['unidade_id'];
    if (array_key_exists('uni', $in) && $uni !== null && (int)$in['uni'] !== (int)$uni) falha('A unidade da festa não muda (o que foi vendido fica na unidade dela). Para outra unidade, cancele e marque de novo.');   /* 2.62 */
    if (array_key_exists('uni', $in) && (int)$in['uni'] !== (int)$uni) { $nu = ''; foreach (unidades() as $x) if ($x['id'] === (int)$in['uni'] && $x['ativo']) $nu = $x['nome']; if ($nu === '') falha('Escolha a unidade da festa.'); $uni = (int)$in['uni']; $mud[] = 'unidade ' . $nu; }
    if (!$mud) responder(['festa' => festaPublica($f)]);
    $db->prepare('UPDATE festas SET tema = ?, hora = ?, data = ?, unidade_id = ?, dia = ?, mudou_em = ? WHERE id = ?')->execute([$tema, $hora, $data, $uni, $novoTipo ?: (string)$f['dia'], agora(), (int)$f['id']]);
    registrar('festa', (int)$f['crianca_id'], $nomeF, 'Festa de ' . $nomeF . ': ' . implode(' · ', $mud) . ' · por ' . porQuem($u));
    responder(['festa' => festaPublica(festaComConta((int)$f['id']))]);
  }
  case 'festa_cancelar': { // 1.54: o gestor cancela a festa: a venda dela, o upgrade e o adicional (sem o prazo de 7 dias), com o que foi pago para devolver
    $u = exigir(); if (($u['papel'] ?? '') !== 'gestor') falha('Só o gestor cancela a festa.', 403);
    $f = festaComConta((int)($in['id'] ?? 0)); if (!$f) falha('A festa não está mais marcada.', 404);
    if ((int)$f['conv'] + (int)$f['aniv'] > 0) falha('A festa já começou (já tem criança que entrou nela): não dá para cancelar.');
    $motivo = mb_substr(maiusculo(trim((string)($in['motivo'] ?? ''))), 0, 200); if (mb_strlen($motivo) < 5) falha('Escreva o motivo do cancelamento da festa.');
    $q = $db->prepare('SELECT id FROM vendas WHERE festa_id = ? ORDER BY festa_n > 0, id DESC'); $q->execute([(int)$f['id']]); $ids = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)); $q->closeCursor();
    $nomeF = (string)(crianca((int)$f['crianca_id'])['nome'] ?? ''); $dev = []; $n = 0;
    foreach ($ids as $vid) { $r = cancelarVenda($vid, $u, 'FESTA CANCELADA: ' . $motivo, null, '', true); $n++; if ($r['devolver'] !== '') $dev[] = $r['devolver']; }
    $db->prepare('UPDATE festas SET mudou_em = ? WHERE id = ?')->execute([agora(), (int)$f['id']]);
    registrar('festa', (int)$f['crianca_id'], $nomeF, 'Festa de ' . $nomeF . ' (' . substr((string)$f['data'], 8, 2) . '/' . substr((string)$f['data'], 5, 2) . ') cancelada · ' . $motivo . ' · por ' . porQuem($u) . ($dev ? ' · devolver ao cliente: ' . implode(' + ', $dev) : ''));
    responder(['cancelada' => $n, 'devolver' => implode(' + ', $dev)]);
  }
  case 'festa_relatorio': { // 1.29: quem veio na festa, com o responsável e os telefones (para mandar ao responsável do aniversariante)
    exigir(); $f = festaComConta((int)($in['id'] ?? 0)); if (!$f) falha('A festa não está mais marcada.', 404);
    responder(['festa' => festaPublica($f), 'criancas' => festaRelatorioLinhas($f, festaRelatorio($f)), 'vendas' => festaVendas($f)]);   // 1.38: e a venda da festa em partes
  }
  case 'saida': {
    $us = modoManual() ? exigir() : exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    if (!$c['entrou']) falha($c['nome'] . ' não está no espaço.');
    if (!empty($GLOBALS['PS_OFF']['em'])) { $te = strtotime((string)$c['entrou']); $to = strtotime((string)$GLOBALS['PS_OFF']['em']);   // 1.45 (auditoria): a saída feita sem internet de antes da entrada de agora não entra (sem deixar cobrança para trás)
      if ($to < $te - 60) falha('Esta saída, feita sem internet às ' . substr((string)$GLOBALS['PS_OFF']['em'], 11, 5) . ', é de antes da entrada de agora (' . substr((string)$c['entrou'], 11, 5) . '): não entra sozinha. Confira com o gestor.', 409);
      if ($to < $te) $GLOBALS['PS_AGORA'] = $te; }   // (o relógio do computador um pouco atrás: vale a hora da entrada)
    if (!empty($c['festa_id'])) unset($in['avulso']);   // 1.29: a visita da festa não gasta horas: não há o que pagar avulso
    // gestor e supervisor podem dizer a hora em que a criança saiu de verdade (saída esquecida), com o motivo
    $hh = trim((string)($in['quando'] ?? '')); $corrige = null;
    if ($hh !== '') {
      $u = exigir(['gestor']); if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hh)) falha('Escreva a hora da saída assim: 14:30.');   // 1.00: só o gestor
      $motivo = mb_substr(maiusculo((string)($in['motivo'] ?? '')), 0, 300); if (mb_strlen($motivo) < 5) falha('Escreva o motivo da correção da hora.');
      $corrige = substr((string)$c['entrou'], 0, 10) . ' ' . $hh . ':00';
      if (strtotime($corrige) > time()) falha('A hora corrigida não pode ser depois de agora.');
      if (strtotime($corrige) < strtotime(substr((string)$c['entrou'], 0, 16) . ':00')) falha('A hora corrigida não pode ser antes da entrada (' . substr((string)$c['entrou'], 11, 5) . ').');
    }
    $famH = null; if (!empty($in['familia']) && empty($in['avulso']) && $corrige === null) { $famH = usarHorasDaFamilia($c, agora()); if ($famH) $c = crianca((int)$c['id']); }   /* 2.10: descontar do pacote da família (o irmão tem as horas) */
    // 1.15: a tela manda o total que mostrou (já com o desconto) e a forma de pagamento. Se a conta do servidor deu outro valor (nasceu
    // mais um período com a folha aberta, ou o relógio do aparelho anda diferente), a saída não é dada: a tela recarrega e mostra o
    // valor certo. Assim nenhuma criança sai com período sem receber. Sem "total" (tela antiga), vale como antes
    // 1.21: quem não estorna sempre fecha a conta: sem o "total" (um pedido que não veio da tela), vale como "nada a receber" e é conferido igual
    if ((isset($in['total']) || !podeEstornar($us)) && $corrige === null) {
      $in['total'] = (float)($in['total'] ?? 0);
      $t0 = agora(); if (acertarCobrancas($c, $t0)) $c = crianca((int)$c['id']);
      if (desfazerCobrancasSobrando($c, $t0)['n']) $c = crianca((int)$c['id']);
      $bruto = array_sum(array_map(fn($v) => (float)$v['falta'], pendentesDaVisita($c))) + (float)atrasoDaVisita((int)$c['id'], (string)$c['entrou'])['valor'] + (!empty($in['avulso']) ? (float)contaVisitaAvulsa($c, $t0)['valor'] : 0.0);
      $dv = lerDesconto($in['desconto'] ?? null, eu() ?: [], $c, (float)$bruto); $devido = round(max(0, $bruto - ($dv ? (float)$dv['valor'] : 0)), 2); $pgv = lerPagamento($in['pagamento'] ?? null);
      if (abs($devido - round((float)$in['total'], 2)) >= 0.01 || abs($devido - round((float)array_sum($pgv), 2)) >= 0.01)
        falha($devido > 0 ? 'O valor a receber mudou para ' . dinheiro($devido) . '. Confira e confirme de novo.' : 'Não há mais nada a receber nesta saída. Confira e confirme de novo.', 409);
    }
    // 1.11: "pagar avulso": a visita não desconta das horas que o cliente já tinha. O que ela usaria dessas horas é vendido agora e entra
    // no que ficou para pagar na saída (logo abaixo); as horas do cliente ficam como estavam. Não vale com a hora corrigida
    $av = null; if (!empty($in['avulso']) && $corrige === null) { $av = venderVisitaAvulsa($c, eu() ?: null); if ($av) $c = crianca((int)$c['id']); }
    $pendentes = pendentesDaVisita($c);   // 1.02: o que ficou para pagar na saída (visto antes de dar a saída: depois dela a visita já fechou)
    $ud = eu(); $desc = lerDesconto($in['desconto'] ?? null, $ud ?: [], $c);   // 1.04: o desconto do gestor (conferido antes de dar a saída)
    $pgS = lerPagamento($in['pagamento'] ?? null); $parcS = $pgS ? lerParcelas($in, $pgS, $us, $c) : PARC_NADA;   // 1.89: em quantas vezes no crédito (conferido antes de dar a saída)
    $r = fazerSaida($c, 'mão', null, $av !== null); $r['pend'] = round(array_sum(array_map(fn($v) => (float)$v['falta'], $pendentes)), 2); if ($av) $r['avulso'] = $av; if ($famH) $r['familia'] = $famH;
    if ($corrige !== null) { $k = corrigirSaida((int)$r['visita'], $corrige, $u, $motivo); $r['corrigida'] = $k; $r['debitado'] = $k['debitado']; $r['atraso'] = atrasoDaVisita((int)$c['id'], (string)$c['entrou'], date('Y-m-d H:i:s', time() + 5)); }
    // 0.90: a forma em que as cobranças por atraso desta visita foram recebidas. Vale só para as que ainda não estavam pagas, até o valor de cada uma
    $q = $db->prepare('SELECT id, valor FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = vendas.id) ORDER BY id'); $q->execute([(int)$c['id'], (string)$c['entrou']]); $autos = $q->fetchAll();   // as cobranças por atraso desta visita que ainda não foram pagas
    if ($desc) { // 1.04: o desconto sai do que a saída recebe (o que ficou a pagar e as cobranças por atraso), em proporção
      $faltas = []; foreach ($pendentes as $v) $faltas[(int)$v['id']] = (float)$v['falta']; foreach ($autos as $l) $faltas[(int)$l['id']] = (float)$l['valor'];
      $ad = aplicarDesconto($desc, $faltas, $c, $ud); if ($ad) { $r['desc'] = $ad; usarAutorizacao($desc, (float)$ad['valor']); } }
    $pg = lerPagamento($in['pagamento'] ?? null); $r['pago'] = '';
    if ($pg) {
      $eu2 = eu(); $antesPg = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM pagamentos')->fetchColumn();
      $pago = gravarPagamento(array_merge(array_map(fn($v) => (int)$v['id'], $pendentes), array_map(fn($l) => (int)$l['id'], $autos)), $pg, (int)$c['id'], $eu2 ? (int)$eu2['id'] : null);   // primeiro o que ficou a pagar, depois as cobranças por atraso
      anotarParcelas($antesPg, $parcS, $eu2 ?: $us, (int)$c['id']);   // 1.89
      if ($pago) { $r['pag_de'] = $antesPg; $db->prepare('UPDATE visitas SET pag_de = ? WHERE id = ?')->execute([$antesPg, (int)$r['visita']]);   // (para o Desfazer da saída tirar o que foi recebido nela; 1.21: a visita guarda, para o servidor saber que esta saída recebeu)
        if ($r['pend'] > 0) $r['pag_registro'] = registrar('pag', (int)$c['id'], $c['nome'], 'Pagamento na saída · ' . dinheiro((float)array_sum($pago)) . ' · ' . textoPagamento($pago)); }
      if ($pago) $r['cupom'] = criarCupom($c, 'saida', $antesPg, $eu2);   // 0.93: o cupom fiscal do que foi recebido na saída
      if ($pago) { $r['pago'] = textoPagamento($pago); $db->prepare("UPDATE registros SET texto = REPLACE(texto, ' a receber', ' · recebido: ' || ?) WHERE id = ? AND crianca_id = ?")->execute([$r['pago'], (int)$r['registro'], (int)$c['id']]); }
    }
    fecharPedidosDeDesconto(eu(), $c, 'saida');   // 1.23: o pedido de desconto que esperava a resposta não fica para depois
    responder($r);
  }
  case 'visita_corrigir': { // depois da saída: a supervisão corrige a hora (a criança saiu antes e a saída foi marcada tarde)
    $u = exigir(['gestor']); $hh = trim((string)($in['quando'] ?? '')); if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hh)) falha('Escreva a hora da saída assim: 14:30.');   // 1.00: só o gestor
    $motivo = mb_substr(maiusculo((string)($in['motivo'] ?? '')), 0, 300); if (mb_strlen($motivo) < 5) falha('Escreva o motivo da correção da hora.');
    $q = $db->prepare('SELECT * FROM visitas WHERE id = ?'); $q->execute([(int)($in['visita'] ?? 0)]); $v = $q->fetch(); if (!$v) falha('Visita não encontrada.', 404);
    if (time() - strtotime((string)$v['saiu']) > 2 * 86400) falha('Só dá para corrigir saídas dos últimos dois dias.');
    responder(corrigirSaida((int)$v['id'], substr((string)$v['entrou'], 0, 10) . ' ' . $hh . ':00', $u, $motivo));
  }
  case 'desfazer_entrada': { // volta a criança para fora e apaga o registro e o aviso
    $us = modoManual() ? exigir() : exigir(['gestor', 'supervisor']); exigirDesfazer($us); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['entrou']) falha('Nada para desfazer.');
    // o "Desfazer" do aviso é para o toque errado, nos instantes seguintes; passou disso, a entrada só sai pelo cancelamento, com o motivo
    if (time() - strtotime((string)$c['entrou']) > 120) falha('O tempo de desfazer já passou. Para tirar esta entrada, use "Cancelar entrada" e escreva o motivo.');
    if (!podeEstornar($us)) { // 1.21: quem não estorna não apaga a entrada: ela sai, mas fica registrada no nome dele, na lista de conferência (nada some sem rastro)
      lerAtendente($in, $us);   // 1.25: com o nome da atendente (a gestora recebe o aviso)
      $k = cancelarEntrada($c, $us, MOTIVO_DESFEITA, [], true);
      responder(['registrado' => true, 'cancelamento' => $k['cancelamento']]);
    }
    $tirou = tirarAutosDaVisita($c);   // 1.15: a recreação avulsa que o sistema lançou para esta entrada sai junto (a entrada nunca existiu)
    tirarPresencaDaEntrada($c);   // 1.29: a entrada na festa desfeita não conta como criança que veio
    desfazerHorasDaFamilia($c, (string)$c['entrou'], 'ent');   /* 2.18: as horas do CPF que vieram com a entrada voltam para os irmãos */
    $db->prepare('UPDATE criancas SET entrou = NULL, sapateira = NULL, festa_id = NULL WHERE id = ?')->execute([$c['id']]);   // 1.16: a sapateira marcada nesta entrada fica livre de novo
    if ($tirou['n']) { $k = crianca((int)$c['id']); if ($k && restante($k) < periodo() && regra()['bloqueioAuto'] === 'sim' && (!isset($k['liberado']) || (int)$k['liberado'])) bloquearAcesso($k, false); }
    if (!empty($in['registro'])) $db->prepare('DELETE FROM registros WHERE id = ? AND crianca_id = ?')->execute([(int)$in['registro'], $c['id']]);
    if (!empty($in['mensagem'])) $db->prepare('DELETE FROM mensagens WHERE id = ? AND crianca_id = ?')->execute([(int)$in['mensagem'], $c['id']]);
    responder();
  }
  /* ---------- cancelar a entrada sem cobrar (com o motivo, para a supervisão conferir) ---------- */
  case 'reabrir_visita': { // até regra.reabreMin minutos depois da saída: a saída é desfeita e o tempo continua desde a entrada
    $u = modoManual() ? exigir() : exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    responder(reabrirVisita($c, $u));
  }
  case 'cancelar_previa': { // o que a folha "Cancelar a entrada" mostra: há quanto tempo entrou, se ainda está no prazo e as vendas desta visita
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    if (!$c['entrou']) falha($c['nome'] . ' não está no espaço.');
    [$lim, $seg, $noPrazo] = prazoCancelar($c);
    responder(['limite' => $lim, 'segundos' => $seg, 'no_prazo' => $noPrazo, 'pode' => $noPrazo, 'entrou' => $c['entrou'],
      'vendas' => array_map(fn($v) => ['id' => (int)$v['id'], 'n' => nomeDaVenda($v), 'm' => (int)$v['minutos'], 'v' => (float)$v['valor'], 'quando' => $v['quando'], 'junto' => vendaJuntoDaEntrada($v, $c), 'auto' => !empty($v['auto']) && faltaDaVenda((int)$v['id']) > 0.004, 'presa' => !podeEstornar($u) && vendaPresaNoCancelamento($v), 'falta' => faltaDaVenda((int)$v['id']), 'fora' => (string)($v['alug'] ?? '') !== '' && $v['voltou'] === null], vendasDaVisita($c))]);
  }
  case 'cancelar_entrada': {
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    if (!$c['entrou']) falha($c['nome'] . ' não está no espaço: não há entrada para cancelar.');
    $motivo = mb_substr(maiusculo((string)($in['motivo'] ?? '')), 0, 300);
    if (mb_strlen($motivo) < 5) falha('Escreva o motivo do cancelamento: a supervisão vai conferir.');
    [$lim, , $noPrazo] = prazoCancelar($c);
    // 1.00: passou do prazo, ninguém cancela (nem gestor, nem supervisor), qualquer que seja o motivo: o período é cobrado
    if (!$noPrazo && $u['papel'] !== 'gestor') falha($lim > 0 ? 'Já passou de ' . $lim . ' min da entrada de ' . $c['nome'] . ': agora só o gestor cancela (fica anotado como fora da tolerância).' : 'Só o gestor cancela a entrada.', 403);   // 1.56: o gestor cancela fora da tolerância (fica anotado: fora do prazo)
    lerAtendente($in, $u);   // 1.25
    $r = cancelarEntrada($c, $u, $motivo, isset($in['vendas']) && is_array($in['vendas']) ? array_map('intval', $in['vendas']) : null);
    $r['mensagem'] = is_array($r['mensagem']) ? ['id' => (int)$r['mensagem']['id'], 'erro' => (string)$r['mensagem']['erro']] : null;
    responder($r);
  }
  case 'pendente_tirar': { // "não vai fazer agora": a criança sai de "Falta finalizar" em todos os aparelhos (voltar = o Desfazer do aviso)
    // 1.25: e fica registrado como atendimento cancelado, com o nome da atendente e o motivo (a gestora recebe o aviso). Sem id: o cadastro pela metade
    $u = exigir(); $id = (int)($in['id'] ?? 0); $c = $id ? crianca($id) : null; if ($id && (!$c || !$c['ativo'])) falha('Criança não encontrada.', 404);
    if (!empty($in['voltar'])) { exigirDesfazer($u); if ($id) { $f = pendFora(); unset($f[$id]); gravarCfg('pend_fora', $f); } if (!empty($in['cancelamento'])) tirarCancelamentoAtendimento((int)$in['cancelamento'], (int)($in['registro'] ?? 0), $u);
      elseif ($id) { $q = $db->prepare("SELECT id FROM cancelamentos WHERE crianca_id = ? AND tipo = 'atendimento' AND por = ? AND quando >= ? ORDER BY id DESC LIMIT 1"); $q->execute([$id, (int)$u['id'], date('Y-m-d H:i:s', time() - 600)]); $k = (int)$q->fetchColumn();   // (sem o número: o último dele, desta criança)
        if ($k) { $q = $db->prepare("SELECT id FROM registros WHERE crianca_id = ? AND tipo = 'canc' AND texto LIKE 'Atendimento cancelado%' ORDER BY id DESC LIMIT 1"); $q->execute([$id]); tirarCancelamentoAtendimento($k, (int)$q->fetchColumn(), $u); } }
      responder(); }
    lerAtendente($in, $u);
    if ($id) { $f = pendFora(); $f[$id] = date('Y-m-d'); gravarCfg('pend_fora', $f); }
    responder(cancelarAtendimento($c, $in, $u));
  }
  case 'cancelamento_visto': { // a supervisão conferiu: fica marcado quem viu e quando
    $u = exigir(['gestor', 'supervisor']);
    { $neg = array_values(array_filter(array_map('intval', array_merge((array)($in['ids'] ?? []), [(int)($in['id'] ?? 0)])), fn($x) => $x < 0));   // 1.57: o aviso da troca de pagamento (id negativo)
      if (!empty($in['todos'])) $db->prepare('UPDATE trocas_pag SET visto_por = ?, visto_em = ? WHERE visto_em IS NULL')->execute([$u['nome'], agora()]);
      elseif ($neg) $db->prepare('UPDATE trocas_pag SET visto_por = ?, visto_em = ? WHERE visto_em IS NULL AND id IN (' . implode(',', array_map(fn($x) => -$x, $neg)) . ')')->execute([$u['nome'], agora()]); }
    if (!empty($in['todos'])) $db->prepare("UPDATE cancelamentos SET visto_por = ?, visto_em = ? WHERE visto_em IS NULL")->execute([$u['nome'], agora()]);
    elseif (!empty($in['ids']) && is_array($in['ids'])) { $ids = array_slice(array_values(array_filter(array_map('intval', $in['ids']))), 0, 100); if ($ids) $db->prepare('UPDATE cancelamentos SET visto_por = ?, visto_em = ? WHERE visto_em IS NULL AND id IN (' . implode(',', $ids) . ')')->execute([$u['nome'], agora()]); }   // 1.25: os avisos que estão no painel
    else $db->prepare("UPDATE cancelamentos SET visto_por = ?, visto_em = ? WHERE id = ? AND visto_em IS NULL")->execute([$u['nome'], agora(), (int)($in['id'] ?? 0)]);
    responder(['avisos' => avisosDaGestora($u)]);   // 1.25: os avisos que ainda faltam ver (o cartão da gestora)
  }
  case 'desfazer_saida': {
    $us = modoManual() ? exigir() : exigir(['gestor', 'supervisor']); exigirDesfazer($us); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Nada para desfazer.');
    $q = $db->prepare('SELECT * FROM visitas WHERE id = ? AND crianca_id = ?'); $q->execute([(int)($in['visita'] ?? 0), $c['id']]); $v = $q->fetch(); if (!$v) falha('Nada para desfazer.');
    if (time() - strtotime((string)$v['saiu']) > 120) falha('O tempo de desfazer esta saída já passou.');
    $estorna = podeEstornar($us);
    if (!$estorna) { // 1.21: a saída que recebeu pagamento não é desfeita por quem não estorna (o pagamento sairia junto, sem rastro)
      $recebeu = false;
      if (($v['pag_de'] ?? null) !== null) { $qp = $db->prepare('SELECT COUNT(*) FROM pagamentos WHERE crianca_id = ? AND id > ?'); $qp->execute([$c['id'], (int)$v['pag_de']]); $recebeu = (int)$qp->fetchColumn() > 0; }
      if ($recebeu) falha('Esta saída recebeu pagamento: só ' . quemEstorna() . ' desfaz. Se a criança voltou para o espaço, use "Reabrir visita".', 403);
      unset($in['pag_de'], $in['pag_registro'], $in['desc']);   // (nada foi recebido nesta saída: não há pagamento nem desconto para tirar)
    }
    $db->prepare('UPDATE criancas SET entrou = ?, usado_min = MAX(0, usado_min - ?), festa_id = ? WHERE id = ?')->execute([$v['entrou'], (int)$v['debitado_min'], $v['festa_id'] ?? null, $c['id']]);   // 1.29: a visita da festa volta a ser da festa
    $sap = (int)($in['sapateira'] ?? 0); if ($sap > 0 && !donoSapateira($sap, (int)$c['id'])) $db->prepare('UPDATE criancas SET sapateira = ? WHERE id = ?')->execute([$sap, $c['id']]);
    $db->prepare('DELETE FROM visitas WHERE id = ?')->execute([$v['id']]);
    desfazerHorasDaFamilia($c, (string)$v['entrou']);   /* 2.10: as horas da família voltam para os irmãos */
    if (!$estorna) { /* 1.21: nada a tirar (esta saída não recebeu) */ }
    elseif (isset($in['pag_de'])) { // 1.02: tudo o que foi recebido nessa saída (o que ficou a pagar e as cobranças por atraso)
      apagarPagamentosDaOperacao((int)$c['id'], (int)$in['pag_de'], (string)$v['saiu']);   // 1.31: só os desta saída
      if (!empty($in['pag_registro'])) $db->prepare("DELETE FROM registros WHERE id = ? AND crianca_id = ? AND tipo = 'pag'")->execute([(int)$in['pag_registro'], $c['id']]);
    } else $db->prepare('DELETE FROM pagamentos WHERE crianca_id = ? AND quando >= ? AND venda_id IN (SELECT id FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ?)')->execute([$c['id'], $v['saiu'], $c['id'], $v['entrou']]);   // o que foi recebido nessa saída
    if (!empty($in['registro'])) $db->prepare('DELETE FROM registros WHERE id = ? AND crianca_id = ?' . ($estorna ? '' : " AND tipo = 'sai'"))->execute([(int)$in['registro'], $c['id']]);   // (1.21: quem não estorna só tira a linha da própria saída)
    if (!empty($in['mensagem'])) $db->prepare('DELETE FROM mensagens WHERE id = ? AND crianca_id = ?')->execute([(int)$in['mensagem'], $c['id']]);
    if (!empty($in['desc']) && is_array($in['desc'])) tirarDesconto((int)$c['id'], (array)($in['desc']['vendas'] ?? []), (int)($in['desc']['registro'] ?? 0), mb_substr((string)($in['desc']['motivo'] ?? ''), 0, 120));   // 1.04: o desconto dado nessa saída volta para as vendas
    if (!empty($in['avulso']) && is_array($in['avulso'])) { // 1.11: a visita tinha sido vendida avulsa nessa saída: a venda sai (o que foi recebido nela já saiu acima) e as horas dela também
      $q = $db->prepare('SELECT * FROM vendas WHERE id = ? AND crianca_id = ? AND auto = 0 AND quando >= ? AND itens LIKE ?'); $q->execute([(int)($in['avulso']['venda'] ?? 0), $c['id'], $v['entrou'], ITENS_AVULSO_SAIDA . '%']); $x = $q->fetch();
      if ($x && faltaDaVenda((int)$x['id']) >= (float)$x['valor'] - 0.004) {
        $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$x['minutos'], $c['id']]);
        $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([(int)$x['id']]);
        if (!empty($x['registro'])) $db->prepare("DELETE FROM registros WHERE id = ? AND crianca_id = ? AND tipo = 'venda'")->execute([(int)$x['registro'], $c['id']]);
      }
    }
    responder();
  }
  case 'vender': { // (das primeiras versões: a tela não usa mais. 1.21: minutos e valor livres, só o gestor)
    $u = exigir(['gestor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $min = isset($in['minutos']) ? max(0, (int)$in['minutos']) : max(0, (int)round((float)($in['horas'] ?? 0) * 60)); $valor = round(max(0, (float)($in['valor'] ?? 0)), 2); $itens = mb_substr(trim((string)($in['itens'] ?? '')), 0, 80);
    if ($min <= 0 && $itens === '') falha('O que foi vendido?');
    responder(fazerVenda($c, $min, $valor, (int)$u['id'], $itens));
  }
  case 'atender': { // a venda do atendimento: um ou mais serviços de uma vez. O valor sai da tabela de Ajustes, não do que a tela mandar
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    if (!empty($in['entrar']) && empty($c['entrou'])) $GLOBALS['PS_ENTRA_AQUI'] = true;   /* 2.62: a entrada é dada neste aparelho: a visita e o que é vendido com ela são desta unidade */
    $r = regra(); $porId = []; foreach ($r['servicos'] as $s) $porId[(string)$s['id']] = $s;
    $linhas = [];
    foreach ((array)($in['linhas'] ?? []) as $l) {
      $s = $porId[(string)($l['s'] ?? '')] ?? null; $q = min(48, (int)($l['q'] ?? 0)); if (!$s || $q < 1) continue;
      { $uq = uniDaAlteracao($c); if ($uq !== null && in_array($uq, array_map('intval', (array)($s['fora'] ?? [])), true)) falha($s['n'] . ' não tem nesta unidade. Confira em Ajustes → Serviços.'); }   /* 1.99 */
      if (isset($l['vt']) && abs((float)$l['vt'] - (float)$s['v']) >= 0.005) falha('O valor de ' . $s['n'] . ' mudou para ' . dinheiro((float)$s['v']) . '. Feche e abra o atendimento de novo.');   // 1.45 (auditoria): como o pacote e o produto
      if (!empty($s['volta']) && ($s['modo'] ?? 'fixo') !== 'periodo') $q = min(ALUG_MAX, $q);   // 1.22: o que sai da loja com valor único (o carrinho de bebê): até 4 de uma vez
      if (alugEmAberto($s)) { // 1.20: o que sai da loja e é cobrado por período (o fusquinha) só "sai": não há tempo a escolher nem valor agora; a conta fecha na devolução
        if ($ja = alugueis((int)$c['id'], (string)$s['id'])) falha($s['n'] . ' de ' . $c['nome'] . ' já está fora da loja desde as ' . substr((string)$ja[0]['ini'], 11, 5) . '. Marque a devolução antes de registrar outra saída.');
        $per = max(1, (int)($s['min'] ?? 30)); $qa = max(1, min(ALUG_MAX, $q));   // 1.22: saem até 4 de uma vez. Cada um é uma linha (um pode voltar antes do outro); a linha do dia é uma só
        for ($i = 0; $i < $qa; $i++) $linhas[] = ['servico' => (string)$s['n'], 'min' => 0, 'valor' => 0.0, 'itens' => ITENS_ALUG_ABERTO, 'mais' => ['alug' => (string)$s['id'], 'alug_min' => 0, 'qtd' => 1]
          + ($i === 0 ? ['texto' => ($qa > 1 ? $qa . ' × ' : '') . $s['n'] . ' · ' . ($qa > 1 ? 'saíram' : 'saiu') . ' da loja · ' . dinheiro((float)$s['v']) . ' a cada ' . $per . ' min' . ($qa > 1 ? ', cada um' : '') . ', ' . ITENS_ALUG_ABERTO] : ['mesmaLinha' => true])];
        continue; }
      if (($s['modo'] ?? 'fixo') === 'periodo' && empty($s['banco']) && !empty($s['volta']) && ($ja = alugueis((int)$c['id'], (string)$s['id'])) && !empty($ja[0]['aberto'])) falha($s['n'] . ' de ' . $c['nome'] . ' já está fora da loja desde as ' . substr((string)$ja[0]['ini'], 11, 5) . ', com a conta para fechar na devolução. Marque a devolução antes de vender mais tempo.');
      if (($s['modo'] ?? 'fixo') === 'periodo') { $tot = $q * max(1, (int)($s['min'] ?? 30)); $tempo = !empty($s['banco']); $un = $tempo ? precoPcd($c, (float)$s['v']) : (float)$s['v']; $comPcd = $un < (float)$s['v'];   // a criança PCD paga a recreação avulsa com desconto (carrinhos e pacotes, não)
        $linhas[] = ['servico' => (string)$s['n'], 'min' => $tempo ? $tot : 0, 'valor' => $q * $un, 'itens' => ($tempo ? '' : dur($tot)) . ($comPcd ? 'PCD ' . pcdDesc() . '%' : ''), 'mais' => ($comPcd ? ['pcd' => 1, 'cheio' => $q * (float)$s['v']] : []) + (!$tempo && !empty($s['volta']) ? ['alug' => (string)$s['id'], 'alug_min' => $tot, 'qtd' => 1] : [])]; }
      else { $obs = trim((string)($s['obs'] ?? '')); $linhas[] = ['servico' => (string)$s['n'], 'min' => 0, 'valor' => $q * (float)$s['v'], 'itens' => ($q > 1 ? $q . ' unidades' . ($obs !== '' ? ' · ' : '') : '') . $obs, 'mais' => !empty($s['volta']) ? ['alug' => (string)$s['id'], 'alug_min' => 0, 'qtd' => $q] : []]; }
    }
    // pacote de horas não tem o desconto PCD: ele vale só na recreação avulsa
    $altUsadas = [];   // 1.33: as alterações de valor desta venda
    // a tela manda as horas, o tipo (com produto ou seco) e o valor que mostrou: se a tabela mudou no meio do atendimento, avisa em vez de vender outro pacote
    foreach ((array)($in['pacotes'] ?? []) as $i) {
      if (is_array($i)) { $p = null; $outro = null; $h = (int)($i['h'] ?? 0); $tp = ($i['tipo'] ?? '') === 'produto' ? 'produto' : 'seco';
        $nm = array_key_exists('nome', $i) ? mb_strtolower(trim((string)$i['nome'])) : null; $val = isset($i['v']) ? (float)$i['v'] : null;   // pode haver vários pacotes do mesmo tempo: o nome e o valor dizem qual é
        foreach ($r['pacotes'] as $x) { if ((int)$x['h'] !== $h || tipoPacote($x) !== $tp) continue; if ($nm !== null && mb_strtolower(trim((string)($x['nome'] ?? ''))) !== $nm) continue;
          if ($val === null || abs((float)$x['v'] - $val) < 0.005) { $p = $x; break; } $outro = $outro ?? $x; }
        if (!$p && $outro) falha('O valor do pacote de ' . $h . ' h mudou para ' . dinheiro((float)$outro['v']) . '. Feche e abra o atendimento de novo.');
        if (!$p) falha('O pacote de ' . $h . ' h não está mais na tabela. Feche e abra o atendimento de novo.');
      } else $p = $r['pacotes'][(int)$i] ?? null;   // a tela antiga mandava a posição na lista
      if ($p) { $lv = ['servico' => servicoDoPacote($p), 'min' => (int)$p['h'] * 60, 'valor' => (float)$p['v'], 'itens' => itensDoPacote($p), 'ck' => tipoPacote($p) === 'produto' ? 'pacote_prod' : 'pacote'];
        if (is_array($i) && !empty($i['alt'])) { $aid = (int)$i['alt']; if (isset($altUsadas[$aid])) falha('A mesma alteração de valor só vale para um item.'); $altUsadas[$aid] = 1;   // 1.33: o valor alterado (aprovado)
          $a = autorizacaoDeValor($aid, $u, $c, itemDoPacote($p)); $lv['valor'] = $a['para']; $lv['alt'] = $a;
          if (!empty($a['horas'])) { $lv['min'] = (int)$a['horas'] * 60; if ($lv['servico'] !== '') $lv['servico'] = 'PACOTE ' . (int)$a['horas'] . 'H'; } }   // 1.36: o pacote com as horas novas
        $linhas[] = $lv; }
    }
    // produtos: a tela manda o produto, a quantidade e o valor que mostrou. Se o valor mudou no meio do atendimento, avisa em vez de cobrar outro
    foreach ((array)($in['produtos'] ?? []) as $l) {
      if (!is_array($l)) continue; $q = min(99, (int)($l['q'] ?? 0)); if ($q < 1) continue;
      $p = produto((int)($l['p'] ?? 0)); if (!$p || !$p['ativo']) falha('Um dos produtos saiu da lista. Feche e abra o atendimento de novo.');
      if (isset($l['v']) && abs((float)$l['v'] - (float)$p['valor']) >= 0.005) falha('O valor de ' . $p['nome'] . ' mudou para ' . dinheiro((float)$p['valor']) . '. Feche e abra o atendimento de novo.');
      $lv = ['servico' => (string)$p['nome'], 'min' => 0, 'valor' => round($q * (float)$p['valor'], 2), 'itens' => $q > 1 ? $q . ' unidades' : '', 'mais' => ['produto' => (int)$p['id'], 'qtd' => $q], 'ck' => 'produto'];
      if (!empty($l['alt'])) { $aid = (int)$l['alt']; if (isset($altUsadas[$aid])) falha('A mesma alteração de valor só vale para um item.'); $altUsadas[$aid] = 1;   // 1.33
        $a = autorizacaoDeValor($aid, $u, $c, itemDoProduto($p, $q)); $lv['valor'] = $a['para']; $lv['alt'] = $a; }
      $linhas[] = $lv;
    }
    // 1.12: a festa (só pelo botão Festa): as linhas saem da tabela da loja, e a festa é paga na hora da venda
    $festa = linhasDaFesta($in['festa'] ?? null, $c); foreach ($festa as $l) $linhas[] = $l;
    if ($festa && !empty($in['depois'])) falha('A festa é paga na hora da venda.');
    // voucher do shopping: 30 min de recreação grátis, um por visita, sem código. A tela só marca (voucher: true); a tela antiga mandava { q, codigo } e vale como um voucher.
    // Confere antes de vender qualquer coisa, para não ficar uma venda pela metade
    $vo = $in['voucher'] ?? null; $comVoucher = is_array($vo) ? (int)($vo['q'] ?? 0) > 0 : !empty($vo);
    if ($comVoucher) { if (!servicoTempo()) falha('Não há serviço de tempo de recreação para usar o voucher.'); if (voucherAberto($c)) falha(voucherJaTem($c));
      if ((regra()['voucherEntrada'] ?? true) !== false) { // 1.35: o voucher só na entrada (na saída, nunca)
        if ($c['entrou']) falha('O voucher só vale na entrada: ' . $c['nome'] . ' já está no espaço.');
        if (modoManual() && empty($in['entrar'])) falha('O voucher é dado na entrada: marque "Dar entrada agora".'); } }
    $o = (array)($in['outro'] ?? []); $on = mb_substr(maiusculo((string)($o['n'] ?? '')), 0, 60); if ($on !== '') $linhas[] = ['servico' => $on, 'min' => 0, 'valor' => round(max(0, (float)($o['v'] ?? 0)), 2), 'itens' => ''];   /* 1.91 (auditoria): 2 casas */
    if (!$linhas && !$comVoucher) falha('Escolha o serviço.');
    $vendTela = $festa ? '' : vendedoraDoPedido($in['vendedora'] ?? '');   /* 1.67: quem está vendendo (a tela pede em toda venda de pacote e de produto) */
    $vend = $festa ? '' : (quemAbriu($c) ?: $vendTela);   /* 1.68: com a criança no espaço, a venda é de quem abriu o pedido na entrada */
    // a criança já teve uma venda registrada (em outro aparelho) depois que esta tela abriu o atendimento: avisa antes de cobrar de novo. "mesmo_assim" confirma
    $visto = (int)($in['visto'] ?? 0);
    if ($visto > 0 && empty($in['mesmo_assim'])) {
      $qd = $db->prepare("SELECT quando, texto FROM registros WHERE tipo = 'venda' AND crianca_id = ? AND id > ? ORDER BY id DESC LIMIT 1"); $qd->execute([(int)$c['id'], $visto]);
      if ($ja = $qd->fetch()) falha('Já foi registrada uma venda para ' . $c['nome'] . ' às ' . substr((string)$ja['quando'], 11, 5) . ': ' . $ja['texto'] . '. Confira antes de registrar outra.', 409);
    }
    // 0.90: a forma de pagamento (até três). Confere antes de vender: tem de fechar com o total que o servidor calculou, não com o que a tela mostrou
    $pg = lerPagamento($in['pagamento'] ?? null); $aPagar = round(array_sum(array_map(fn($l) => (float)$l['valor'], $linhas)), 2);
    // 1.47 (o dono: "deixa sempre para cobrar na saída"): o que sai da loja e volta (o carrinho de bebê) nunca é pago na venda. Vendido junto com o que é
    // pago agora (um pacote, um produto), sem a criança no espaço, ele fica a receber na devolução: o pagamento de agora (e o desconto) é só do resto
    $devIdx = []; if (!empty($in['carrinho_depois']) && !$festa) foreach ($linhas as $i => $l) if (!empty($l['mais']['alug']) && (float)$l['valor'] > 0.004) $devIdx[$i] = 1;
    $devV = round(array_sum(array_map(fn($i) => (float)$linhas[$i]['valor'], array_keys($devIdx))), 2); $aPagar = round($aPagar - $devV, 2);
    $desc = lerDesconto($in['desconto'] ?? null, $u, $c, (float)$aPagar); $cheio = $aPagar;   // 1.04: o desconto do gestor: o pagamento fecha com o total já descontado
    if ($desc) { if ($desc['valor'] > $cheio + 0.004) falha('O desconto (' . dinheiro($desc['valor']) . ') passa do total, que é ' . dinheiro($cheio) . '.'); $aPagar = round($cheio - $desc['valor'], 2); }
    /* 2.52: o sinal de 50% (só a venda de produtos da Casa Tom): o cliente paga a metade agora e o resto na retirada */
    $sinal = !empty($in['sinal']); $sinalV = 0.0; $sNome = ''; $sTel = '';
    if ($sinal) {
      $bct = balcaoCT(false); if (!$bct || (int)$bct['id'] !== (int)$c['id']) falha('O sinal de 50% é só na venda da Casa Tom.');
      if ($festa || $comVoucher || $devIdx || !empty($in['depois'])) falha('O sinal de 50% é só na venda de produtos da Casa Tom.');
      $sNome = mb_substr(maiusculo(trim(preg_replace('/\s+/', ' ', (string)($in['sinal_nome'] ?? '')))), 0, 80); $sTel = mb_substr(trim((string)($in['sinal_tel'] ?? '')), 0, 30);
      if (mb_strlen($sNome) < 2) falha('Escreva o nome do cliente que deu o sinal.');
      if (strlen(preg_replace('/\D/', '', $sTel)) < 10) falha('Escreva o telefone do cliente que deu o sinal (com o DDD).');
      if (!$pg) falha('Escolha a forma de pagamento do sinal.');
      $sinalV = round(round(round($aPagar * 100) / 2) / 100, 2);
      if (abs(array_sum($pg) - $sinalV) >= 0.01) falha('O sinal (50%) é ' . dinheiro($sinalV) . ', e o pagamento deu ' . dinheiro((float)array_sum($pg)) . '. Feche e abra o pagamento de novo.');
    }
    if ($pg && !$sinal && abs(array_sum($pg) - $aPagar) >= 0.01) falha('O pagamento (' . dinheiro((float)array_sum($pg)) . ') não fecha com o total, que é ' . dinheiro($aPagar) . '. Feche e abra o atendimento de novo.');
    if ($festa && !$pg && $aPagar > 0) falha('Escolha a forma de pagamento da festa.');   // 1.12: a festa não fica a receber
    if (!$pg && $aPagar > 0 && !podeEstornar($u)) { // 1.21: venda a receber só com hora certa de receber: a saída (a criança está no espaço, ou entra agora) ou a devolução do que sai da loja
      $naSaida = (bool)$c['entrou'] || (!empty($in['entrar']) && modoManual()); $soVolta = true;
      foreach ($linhas as $l) if ((float)$l['valor'] > 0.004 && empty($l['mais']['alug'])) { $soVolta = false; break; }
      if (!$naSaida && !$soVolta) falha('Escolha a forma de pagamento. Sem a criança no espaço, só ' . quemEstorna() . ' deixa uma venda a receber.', 403);
    }
    /* 1.65: o crédito até 4x é livre; de 5x a 12x, só o gestor (ou com a liberação dele, pedida antes). 1.89: em toda venda, não só na festa */
    $parc = $pg ? lerParcelas($in, $pg, $u, $c) : PARC_NADA;
    $vendas = []; $regs = []; $total = 0; $min = 0;
    foreach ($linhas as $l) { $mais = $l['mais'] ?? []; if (!empty($mais['mesmaLinha']) && $regs) $mais['registro'] = (int)end($regs);   // 1.22: o 2º fusquinha da mesma saída fica na linha do dia do primeiro
      $x = fazerVenda($c, $l['min'], $l['valor'], (int)$u['id'], $l['itens'], $l['servico'], false, '', $mais); $vendas[] = $x['venda']; $regs[] = $x['registro']; $total += $l['valor']; $min += $l['min']; $c = crianca((int)$c['id']); }
    foreach ($linhas as $i => $l) if (!empty($l['alt'])) $regs[] = usarAlteracao($l['alt'], (int)$vendas[$i], $c, $u);   // 1.33: o valor alterado fica registrado (o pedido, usado, com a venda)
    /* 1.67: para a comissão: o que cada linha é (pacote, pacote com produto, produto), quem vendeu e o atendimento (a primeira venda dele) */
    $qk = $db->prepare('UPDATE vendas SET vend_k = ?, vendedora = ?, vend_g = ? WHERE id = ?');
    foreach ($linhas as $i => $l) if (!empty($l['ck']) || $vend !== '') $qk->execute([(string)($l['ck'] ?? ''), $vend, (int)$vendas[0], (int)$vendas[$i]]);
    $festaId = $festa ? gravarFesta($linhas, $vendas, $u) : 0;   // 1.29: a festa nova nasce; o upgrade e o adicional ficam com a festa deles
    /* 1.62: em quantas vezes a festa foi no crédito (as Finanças mostram) */
    if ($festa && $parc['n']) $db->prepare('UPDATE vendas SET parcelas = ? WHERE id IN (' . implode(',', array_map('intval', $vendas)) . ')')->execute([$parc['n']]);   // (a festa toda: as Finanças mostram por festa)
    $ad = [];   // 1.04: o desconto sai das vendas desta venda, em proporção ao valor de cada uma
    if ($desc && $cheio > 0) { $faltas = []; foreach ($linhas as $i => $l) if (!isset($devIdx[$i])) $faltas[$vendas[$i]] = (float)$l['valor'];   /* 1.47: o desconto não mexe no carrinho que fica para a devolução */ $ad = aplicarDesconto($desc, $faltas, $c, $u); if ($ad) { $regs[] = $ad['registro']; $total = round($total - $ad['valor'], 2); usarAutorizacao($desc, (float)$ad['valor']); } }
    $vinfo = null;   // o voucher entra por último: com cobrança por atraso a receber, ele cobre essa cobrança em vez de somar tempo
    if ($comVoucher) { $v = usarVoucher($c, $u); $vendas[] = $v['venda']; $regs[] = $v['registro']; $min += $v['minutos']; $c = crianca((int)$c['id']); $vinfo = ['n' => $v['n'], 'cobriu' => $v['cobriu']]; }
    if ($pg && $aPagar > 0) { // fica anotado em cada venda e numa linha do dia ("Pagamento · R$ 680 · Pix R$ 200 + Dinheiro R$ 480")
      $antesPg = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM pagamentos')->fetchColumn();
      $pago = gravarPagamento($devIdx ? array_values(array_filter($vendas, fn($v, $i) => !isset($devIdx[$i]), ARRAY_FILTER_USE_BOTH)) : $vendas, $pg, (int)$c['id'], (int)$u['id']);   // 1.47: sem o carrinho, que fica para a devolução
      anotarParcelas($antesPg, $parc, $u, (int)$c['id']);   // 1.89
      $regs[] = registrar('pag', (int)$c['id'], $c['nome'], $sinal ? 'Sinal de 50% · ' . dinheiro($sinalV) . ' · ' . textoPagamento($pago ?: $pg) . ' · falta ' . dinheiro(round($aPagar - $sinalV, 2)) . ' na retirada · ' . $sNome : 'Pagamento · ' . dinheiro($aPagar) . ' · ' . textoPagamento($pago ?: $pg));   /* 2.52 */
      $cupomId = criarCupom($c, 'venda', $antesPg, $u);   // 0.93: o cupom fiscal deste pagamento, a emitir (0 = módulo desligado)
    }
    $depois = !$pg && $aPagar > 0 && !empty($in['depois']);   // 1.02: o cliente vai pagar na saída (ou na devolução): a venda fica a receber
    if ($depois) $regs[] = registrar('pag', (int)$c['id'], $c['nome'], 'A receber na ' . (!empty($in['entrar']) || $c['entrou'] ? 'saída' : 'devolução') . ' · ' . dinheiro($aPagar));
    if ($devIdx && $devV > 0) $regs[] = registrar('pag', (int)$c['id'], $c['nome'], 'A receber na devolução · ' . dinheiro($devV));   // 1.47: o carrinho
    $sinalR = $sinal ? ctSinalNovo($vendas, $sNome, $sTel, $aPagar, $sinalV, $vend, $u) : null;   /* 2.52 */
    $resp = ['sinal' => $sinalR, 'vendas' => $vendas, 'registros' => $regs, 'total' => $total, 'minutos' => $min, 'festa' => $festaId, 'voucher' => $vinfo, 'pagamento' => $pg ? textoPagamento($pg) : '', 'cupom' => $cupomId ?? 0, 'a_receber' => $depois ? $aPagar : ($devIdx ? $devV : 0), 'desconto' => $ad['valor'] ?? 0];
    if ($min > 0 && $c['entrou']) { $des = desfazerCobrancasSobrando($c); if ($des['n']) { $resp['desfeitas'] = $des; $c = crianca((int)$c['id']); } }   // 1.00: comprou tempo com a criança no espaço: o que sobrou de cobrança por atraso sai
    if (!empty($in['entrar']) && modoManual() && !$c['entrou']) { // sem iDFace: registrou e já entra, o tempo começa a contar (1.15: com ou sem tempo comprado)
      $e = fazerEntrada($c, 'mão', null, null, $vendTela); $resp['entrada'] = ['registro' => $e['registro'], 'mensagem' => is_array($e['mensagem']) ? (int)($e['mensagem']['id'] ?? 0) : 0];
    }
    fecharPedidosDeDesconto($u, $c, 'venda');   // 1.23: o pedido de desconto que esperava a resposta não fica para depois
    responder($resp);
  }
  /* ---------- 0.93: o cupom fiscal de cada pagamento ---------- */
  case 'cupom_emitido': { // a equipe emitiu o cupom na máquina e marca aqui (com o número, se quiser). Marcar de novo só acerta o número
    $u = exigir(); acertarCupons(); $k = cupom((int)($in['id'] ?? 0)); if (!$k) falha('Esse pagamento foi desfeito: não há mais cupom a emitir.', 404);
    $num = mb_substr(mb_strtoupper(preg_replace('/[^0-9A-Za-z.\-\/]/', '', (string)($in['numero'] ?? ''))), 0, 20);
    if ($k['status'] === 'emitido') { if ($num !== '') $db->prepare('UPDATE cupons SET numero = ? WHERE id = ?')->execute([$num, (int)$k['id']]); responder(['cupom' => cupomPublico(cupom((int)$k['id']))]); }
    if ($k['status'] !== 'pendente') falha('Esse cupom não está mais a emitir.', 409);
    $db->prepare("UPDATE cupons SET status = 'emitido', numero = ?, resolvido_em = ?, resolvido_por = ? WHERE id = ? AND status = 'pendente'")->execute([$num, agora(), primeiroNome((string)$u['nome']), (int)$k['id']]);
    responder(['cupom' => cupomPublico(cupom((int)$k['id']))]);
  }
  case 'cupom_sem': { // o pagamento fica sem cupom, com o motivo: só gestor e supervisor, e vira uma linha do dia
    $u = exigir(['gestor', 'supervisor']); acertarCupons(); $k = cupom((int)($in['id'] ?? 0)); if (!$k) falha('Esse pagamento foi desfeito: não há mais cupom a emitir.', 404);
    if ($k['status'] !== 'pendente') falha('Esse cupom não está mais a emitir.', 409);
    $motivo = mb_substr(maiusculo((string)($in['motivo'] ?? '')), 0, 200); if (mb_strlen($motivo) < 5) falha('Escreva o motivo: por que este pagamento fica sem cupom fiscal?');
    $db->prepare("UPDATE cupons SET status = 'sem', motivo = ?, resolvido_em = ?, resolvido_por = ? WHERE id = ? AND status = 'pendente'")->execute([$motivo, agora(), primeiroNome((string)$u['nome']), (int)$k['id']]);
    $reg = registrar('cup', (int)$k['crianca_id'], (string)$k['nome'], 'Pagamento de ' . dinheiro((float)$k['valor']) . ' sem cupom fiscal · Motivo: ' . $motivo . ' · por ' . primeiroNome((string)$u['nome']));
    responder(['cupom' => cupomPublico(cupom((int)$k['id'])), 'registro' => $reg]);
  }
  case 'cupom_cancelado': { // o cupom de uma venda desfeita foi cancelado na máquina
    $u = exigir(); $k = cupom((int)($in['id'] ?? 0)); if (!$k) falha('Cupom não encontrado.', 404);
    if ($k['status'] === 'cancelado') responder(['cupom' => cupomPublico($k)]);
    if ($k['status'] !== 'cancelar') falha('Esse cupom não está na lista dos que precisam ser cancelados.', 409);
    $db->prepare("UPDATE cupons SET status = 'cancelado', resolvido_em = ?, resolvido_por = ? WHERE id = ? AND status = 'cancelar'")->execute([agora(), primeiroNome((string)$u['nome']), (int)$k['id']]);
    responder(['cupom' => cupomPublico(cupom((int)$k['id']))]);
  }
  case 'cupom_voltar': { // desfaz a marcação (toque errado). 1.26: só o gestor (com a regra 'desfazer' = 'todos': qualquer um nos 10 min, gestor e supervisor a qualquer hora)
    $u = exigir(); exigirDesfazer($u); $k = cupom((int)($in['id'] ?? 0)); if (!$k) falha('Cupom não encontrado.', 404);
    if (!in_array($k['status'], ['emitido', 'sem', 'cancelado'], true)) falha('Nada para desfazer neste cupom.', 409);
    if ($u['papel'] === 'atendente' && (!$k['resolvido_em'] || time() - strtotime((string)$k['resolvido_em']) > 600)) falha('Já passou de 10 min: agora só Gestor ou Supervisor desfaz a marcação do cupom.', 403);
    if ($k['status'] === 'cancelado') $db->prepare("UPDATE cupons SET status = 'cancelar', resolvido_em = NULL, resolvido_por = '' WHERE id = ?")->execute([(int)$k['id']]);
    else { $db->prepare("UPDATE cupons SET status = 'pendente', numero = '', motivo = '', resolvido_em = NULL, resolvido_por = '' WHERE id = ?")->execute([(int)$k['id']]);
      if ($k['status'] === 'sem') registrar('cup', (int)$k['crianca_id'], (string)$k['nome'], 'O pagamento de ' . dinheiro((float)$k['valor']) . ' voltou para "cupom a emitir" · por ' . primeiroNome((string)$u['nome'])); }
    acertarCupons(); $k2 = cupom((int)$k['id']); responder(['cupom' => $k2 ? cupomPublico($k2) : null]);
  }
  case 'devolver_previa': { // 1.00: o que há para receber se o que está fora for devolvido agora (o tempo a mais do fusquinha)
    exigir(); $c = crianca((int)($in['crianca'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    $g = alugueis((int)$c['id'], (string)($in['s'] ?? '')); if (!$g) falha('Isso já foi marcado como devolvido.', 409);
    [$lim, $seg, $noPrazo] = prazoCancelarAluguel($g[0]);   // 1.20: no prazo de cancelar (o mesmo da entrada), a saída em aberto pode ser cancelada sem cobrar
    responder(['extra' => aluguelExtra($g[0]), 'pend' => round((float)($g[0]['pend'] ?? 0), 2), 'cancela' => ['limite' => $lim, 'segundos' => $seg, 'pode' => $noPrazo]]);   // 1.02: e o que ficou a pagar do próprio carrinho
  }
  case 'devolver': { // o carrinho (ou o fusquinha) voltou para a loja: sai da lista. voltar = o Desfazer do aviso (só nos 10 min seguintes)
    $u = exigir(); $c = crianca((int)($in['crianca'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    if (!empty($in['voltar'])) { exigirDesfazer($u);
      $ids = array_values(array_filter(array_map('intval', (array)($in['vendas'] ?? [])))); if (!$ids) falha('Nada para desfazer.');
      if (!podeEstornar($u)) { // 1.21: a devolução que recebeu (o tempo do fusquinha, o tempo a mais, o que tinha ficado a pagar) não é desfeita por quem não estorna
        $qv = $db->prepare('SELECT MIN(voltou) FROM vendas WHERE id IN (' . implode(',', $ids) . ") AND crianca_id = ? AND alug != '' AND voltou IS NOT NULL"); $qv->execute([(int)$c['id']]); $volt = (string)$qv->fetchColumn();
        if ($volt !== '') { $qp = $db->prepare('SELECT COUNT(*) FROM pagamentos WHERE crianca_id = ? AND quando >= ?'); $qp->execute([(int)$c['id'], $volt]);   // o que foi recebido da hora da devolução para cá
          if ((int)$qp->fetchColumn() > 0) falha('Esta devolução recebeu pagamento: só ' . quemEstorna() . ' desfaz.', 403); }
        unset($in['pag_de'], $in['registros'], $in['extra'], $in['aberto']);   // (nada foi recebido: não há venda de tempo a mais, conta fechada nem pagamento para tirar)
      }
      $q = $db->prepare('UPDATE vendas SET voltou = NULL WHERE id IN (' . implode(',', $ids) . ") AND crianca_id = ? AND alug != '' AND voltou IS NOT NULL AND voltou >= ?");
      $q->execute([(int)$c['id'], date('Y-m-d H:i:s', time() - 600)]); if (!$q->rowCount()) falha('Não dá mais para desfazer.');
      $db->prepare("DELETE FROM registros WHERE id = ? AND crianca_id = ? AND tipo = 'dev'")->execute([(int)($in['registro'] ?? 0), (int)$c['id']]);
      $extra = (int)($in['extra'] ?? 0); $tirou = false;   // 1.00: o tempo a mais que foi cobrado na devolução sai junto (a venda, o pagamento dela e as linhas do dia)
      if ($extra) { $q = $db->prepare("SELECT id, registro FROM vendas WHERE id = ? AND crianca_id = ? AND itens LIKE 'tempo a mais%' AND quando >= ?"); $q->execute([$extra, (int)$c['id'], date('Y-m-d H:i:s', time() - 660)]);
        if ($v = $q->fetch()) { $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([(int)$v['id']]); $tirou = true; } }
      if (isset($in['pag_de'])) { apagarPagamentosDaOperacao((int)$c['id'], (int)$in['pag_de'], date('Y-m-d H:i:s', time() - 660)); $tirou = true; }   /* 1.31: só os desta devolução */   // 1.02: e o que foi recebido do próprio carrinho
      // 1.20: a saída em aberto volta a ficar sem valor (a conta fecha de novo na próxima devolução). 1.22: cada carrinho que tinha voltado
      $abs = is_array($in['aberto'] ?? null) ? array_values(array_unique(array_filter(array_map('intval', array_merge((array)($in['aberto']['vendas'] ?? []), [(int)($in['aberto']['venda'] ?? 0)]))))) : [];
      foreach ($abs as $ab) if (in_array($ab, $ids, true)) { $q = $db->prepare("SELECT alug FROM vendas WHERE id = ? AND crianca_id = ? AND alug != '' AND alug_min = 0 AND voltou IS NULL"); $q->execute([$ab, (int)$c['id']]); $al = $q->fetchColumn();
        $svA = $al !== false ? servicoPorId((string)$al) : null;
        if ($svA !== null && ($svA['modo'] ?? 'fixo') === 'periodo' && empty($svA['banco'])) { $db->prepare('UPDATE vendas SET valor = 0, itens = ? WHERE id = ?')->execute([ITENS_ALUG_ABERTO, $ab]); $tirou = true; } }
      if ($tirou) foreach (array_filter(array_map('intval', (array)($in['registros'] ?? []))) as $rid) $db->prepare("DELETE FROM registros WHERE id = ? AND crianca_id = ? AND tipo IN ('venda', 'pag')")->execute([$rid, (int)$c['id']]);
      responder();
    }
    if (!empty($in['cancelar'])) { lerAtendente($in, $u); responder(cancelarAluguel($c, (string)($in['s'] ?? ''), $u, mb_substr(maiusculo((string)($in['motivo'] ?? '')), 0, 300))); }   // 1.25: com o nome da atendente   // 1.20: saiu por engano, ou o cliente desistiu: sem cobrar, no prazo, com o motivo
    $pgD = lerPagamento($in['pagamento'] ?? null); $parcD = $pgD ? lerParcelas($in, $pgD, $u, $c) : PARC_NADA; $antesD = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM pagamentos')->fetchColumn();   // 1.89
    $rD = devolverAluguel($c, (string)($in['s'] ?? ''), $u, $pgD, isset($in['quantos']) ? (int)$in['quantos'] : null); anotarParcelas($antesD, $parcD, $u, (int)$c['id']); responder($rD);   // 1.22: quantos voltaram agora (dos que saíram juntos)
  }
  case 'receber_previa': { // 1.02: o que há para receber desta criança agora: o que ficou a pagar e as cobranças por atraso
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    $p = pendentesDaVisita($c); $at = $c['entrou'] ? atrasoDaVisita((int)$c['id'], (string)$c['entrou']) : ['n' => 0, 'valor' => 0.0];
    if (!$c['entrou']) foreach (autosSemPagarDaSaida($c) as $v) $p[] = $v + ['falta' => round((float)$v['valor'], 2)];   // 1.31: a saída pelo rosto que não recebeu
    responder(['itens' => array_map(fn($v) => ['n' => nomeDaVenda($v), 'v' => (float)$v['falta']], $p), 'atraso' => $at, 'total' => round(array_sum(array_map(fn($v) => (float)$v['falta'], $p)) + (float)$at['valor'], 2)]);
  }
  case 'receber': { // 1.02: recebe agora o que ficou a pagar (sem esperar a saída). voltar = o Desfazer do aviso
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    if (!empty($in['voltar'])) { exigirDesfazer($u);
      if (!podeEstornar($u)) falha('Pagamento recebido: só ' . quemEstorna() . ' desfaz.', 403);   // 1.21
      if (!isset($in['pag_de'])) falha('Nada para desfazer.');
      if (!apagarPagamentosDaOperacao((int)$c['id'], (int)$in['pag_de'], date('Y-m-d H:i:s', time() - 180))) falha('Não dá mais para desfazer.');   // 1.31: só os deste recebimento
      $db->prepare("DELETE FROM registros WHERE id = ? AND crianca_id = ? AND tipo = 'pag'")->execute([(int)($in['registro'] ?? 0), (int)$c['id']]);
      responder();
    }
    $p = pendentesDaVisita($c); if (!$c['entrou']) foreach (autosSemPagarDaSaida($c) as $v) $p[] = $v + ['falta' => round((float)$v['valor'], 2)];   // 1.31: a saída pelo rosto que não recebeu
    $ids = array_map(fn($v) => (int)$v['id'], $p); $total = array_sum(array_map(fn($v) => (float)$v['falta'], $p));
    if ($c['entrou']) { // as cobranças por atraso que ainda não foram pagas entram junto
      $q = $db->prepare('SELECT id, valor FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = vendas.id) ORDER BY id'); $q->execute([(int)$c['id'], (string)$c['entrou']]);
      foreach ($q->fetchAll() as $l) { $ids[] = (int)$l['id']; $total += (float)$l['valor']; }
    }
    $total = round($total, 2); if ($total <= 0) falha('Não há nada a receber de ' . $c['nome'] . ' agora.', 409);
    $pg = lerPagamento($in['pagamento'] ?? null); if (!$pg) falha('Escolha a forma de pagamento.'); $parcR = lerParcelas($in, $pg, $u, $c);   // 1.89
    if (abs(array_sum($pg) - $total) >= 0.01) falha('O valor a receber mudou para ' . dinheiro($total) . '. Feche e abra de novo.', 409);
    $antesPg = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM pagamentos')->fetchColumn();
    $pago = gravarPagamento($ids, $pg, (int)$c['id'], (int)$u['id']); anotarParcelas($antesPg, $parcR, $u, (int)$c['id']);   // 1.89
    $reg = registrar('pag', (int)$c['id'], $c['nome'], 'Pagamento · ' . dinheiro($total) . ' · ' . textoPagamento($pago ?: $pg));
    responder(['valor' => $total, 'pago' => textoPagamento($pago ?: $pg), 'registro' => $reg, 'pag_de' => $antesPg, 'cupom' => criarCupom($c, 'venda', $antesPg, $u)]);
  }
  case 'historico': { // tudo de um cliente (as crianças do mesmo responsável): todas as compras e todas as visitas
    exigir(); session_write_close();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($in['ids'] ?? []))))); if (!$ids || count($ids) > 30) falha('Cliente não encontrado.', 404);
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $tudo = !empty($in['tudo']); $limV = $tudo ? 5000 : 500; $limS = $tudo ? 5000 : 1000;   // 1.19: o relatório do cliente (o extrato) pede tudo, para o saldo de cada linha fechar
    $uni = fn($v) => isset($v['unidade_id']) && $v['unidade_id'] !== null ? (int)$v['unidade_id'] : null;
    $q = $db->prepare("SELECT v.*, u.nome AS por_nome FROM vendas v LEFT JOIN usuarios u ON u.id = v.por WHERE v.crianca_id IN ($marcas) ORDER BY v.quando DESC, v.id DESC LIMIT $limV"); $q->execute($ids);
    $pagas = []; $qp = $db->prepare("SELECT venda_id, forma, SUM(valor) t FROM pagamentos WHERE crianca_id IN ($marcas) GROUP BY venda_id, forma ORDER BY MIN(id)"); $qp->execute($ids);
    foreach ($qp->fetchAll() as $l) $pagas[(int)$l['venda_id']][(string)$l['forma']] = round((float)$l['t'], 2);
    $pgEm = []; $qp = $db->prepare("SELECT venda_id, MAX(quando) q FROM pagamentos WHERE crianca_id IN ($marcas) GROUP BY venda_id"); $qp->execute($ids); foreach ($qp->fetchAll() as $l) $pgEm[(int)$l['venda_id']] = (string)$l['q'];   // 1.57
    // 1.02: o que ainda não foi pago desta visita (o que ficou para a saída e as cobranças por atraso a receber): o histórico marca e não soma em "Pagou ao todo"
    $np = []; foreach ($ids as $cid) { $k = crianca($cid); if (!$k) continue; foreach (pendentesDaVisita($k) as $v) $np[(int)$v['id']] = (float)$v['falta'];
      if (!$k['entrou']) foreach (autosSemPagarDaSaida($k) as $v) $np[(int)$v['id']] = round((float)$v['valor'], 2);   // 1.45 (auditoria): a saída pelo rosto que não recebeu não conta como paga
      if ($k['entrou']) { $qa = $db->prepare('SELECT id, valor FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = vendas.id)'); $qa->execute([$cid, (string)$k['entrou']]); foreach ($qa->fetchAll() as $l) $np[(int)$l['id']] = (float)$l['valor']; } }
    $nfs = []; $cvs = []; $qx = $db->prepare("SELECT * FROM pedidos WHERE crianca_id IN ($marcas) AND venda_id IS NOT NULL AND ((tipo = 'nf' AND status IN ('pendente', 'liberado', 'usado', 'negado')) OR (tipo = 'cancelar_venda' AND status = 'pendente')) ORDER BY id"); $qx->execute($ids);
    foreach ($qx->fetchAll() as $p) { if ($p['tipo'] === 'nf') $nfs[(int)$p['venda_id']] = pedidoPublico($p); else $cvs[(int)$p['venda_id']] = pedidoPublico($p); }   // 1.23: a nota fiscal de cada compra (a mais nova) e o pedido de cancelamento que espera
    $vendas = array_map(fn($v) => ['np' => $np[(int)$v['id']] ?? 0, 'pago' => isset($pagas[(int)$v['id']]) ? textoPagamento($pagas[(int)$v['id']]) : '', 'pg' => $pagas[(int)$v['id']] ?? null, 'pg_em' => $pgEm[(int)$v['id']] ?? null, 'parc' => (int)($v['parcelas'] ?? 0), 'id' => (int)$v['id'], 'crianca' => (int)$v['crianca_id'], 'h' => round((int)$v['minutos'] / 60, 2), 'm' => (int)$v['minutos'], 'v' => (float)$v['valor'], 'itens' => (string)($v['itens'] ?? ''), 'servico' => (string)($v['servico'] ?? ''), 'quando' => $v['quando'], 'por' => (string)($v['por_nome'] ?? ''), 'vend' => (string)($v['vendedora'] ?? ''), 'auto' => !empty($v['auto']), 'voucher' => (string)($v['voucher'] ?? ''), 'pcd' => !empty($v['pcd']), 'desc' => round((float)($v['desc_valor'] ?? 0), 2), 'desc_motivo' => (string)($v['desc_motivo'] ?? ''), 'un' => $uni($v), 'canc' => vendaCancelavel($v), 'prod' => (int)($v['produto_id'] ?? 0) > 0, 'g' => (int)($v['vend_g'] ?? 0), 'fora' => (string)($v['alug'] ?? '') !== '' && $v['voltou'] === null, 'nf' => $nfs[(int)$v['id']] ?? null, 'cv' => $cvs[(int)$v['id']] ?? null], $q->fetchAll());
    $q = $db->prepare("SELECT * FROM visitas WHERE crianca_id IN ($marcas) ORDER BY saiu DESC, id DESC LIMIT $limS"); $q->execute($ids);
    $visitas = array_map(fn($v) => ['id' => (int)$v['id'], 'crianca' => (int)$v['crianca_id'], 'dia' => $v['saiu'], 'entrou' => substr($v['entrou'], 11, 5), 'saiu' => substr($v['saiu'], 11, 5), 'deb' => (int)$v['debitado_min'], 'e' => $v['entrou'], 'un' => $uni($v)], $q->fetchAll());
    // as ocorrências que explicam um acerto: saída corrigida, visita reaberta, entrada cancelada, desconto e cadastro corrigido (só quando a tela pede tudo)
    $ocorr = []; if ($tudo) { $qo = $db->prepare("SELECT quando, tipo, crianca_id, texto FROM registros WHERE crianca_id IN ($marcas) AND tipo IN ('corr', 'reab', 'canc', 'desc', 'alt', 'cad') ORDER BY quando DESC, id DESC LIMIT 1000"); $qo->execute($ids);
      $ocorr = array_map(fn($g) => ['q' => $g['quando'], 'tipo' => (string)$g['tipo'], 'crianca' => (int)$g['crianca_id'], 'texto' => (string)$g['texto']], $qo->fetchAll()); }
    $q = $db->prepare("SELECT * FROM cancelamentos WHERE crianca_id IN ($marcas) ORDER BY id DESC LIMIT 200"); $q->execute($ids); $canc = $q->fetchAll();
    $qa = $db->prepare("SELECT id, quando, crianca_id, texto FROM registros WHERE crianca_id IN ($marcas) AND tipo = 'alt' AND texto LIKE 'Ajuste do gestor:%' ORDER BY quando DESC, id DESC LIMIT 100"); $qa->execute($ids);   /* 2.78: os ajustes de horas do gestor */
    $ajustes = array_map(fn($g) => ['id' => (int)$g['id'], 'crianca' => (int)$g['crianca_id'], 'quando' => (string)$g['quando'], 'texto' => (string)$g['texto'], 'min' => minDoAjuste((string)$g['texto']), 'desfeito' => strpos((string)$g['texto'], ' · desfeito') !== false], $qa->fetchAll());
    responder(['vendas' => $vendas, 'visitas' => $visitas, 'cancelados' => array_map('cancelamentoPublico', $canc), 'ocorr' => $ocorr, 'ajustes' => $ajustes]);
  }
  /* ---------- 1.14: reimprimir o cupom ---------- */
  case 'papel_guardar': { // a tela guarda as duas vias de cada cupom que sai, para reimprimir depois
    $u = exigir(); $c = crianca((int)($in['crianca'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $tipo = in_array($in['tipo'] ?? '', ['entrada', 'saida', 'devolucao', 'festa', 'venda'], true) ? (string)$in['tipo'] : 'entrada';
    if (!empty($in['troca'])) { // 1.57: o cupom refeito depois da troca da forma de pagamento entra no lugar do anterior (o dos últimos 10 min)
      $q = $db->prepare('SELECT id FROM papeis WHERE crianca_id = ? AND tipo = ? AND quando >= ? ORDER BY id DESC LIMIT 1'); $q->execute([(int)$c['id'], $tipo, date('Y-m-d H:i:s', time() - 600)]);
      $ant = (int)$q->fetchColumn(); $q->closeCursor();
      if ($ant && empty($in['vendas'])) { $q = $db->prepare('SELECT vendas FROM papeis WHERE id = ?'); $q->execute([$ant]); $in['vendas'] = (string)$q->fetchColumn(); $q->closeCursor(); }   /* 2.45: o refeito continua com as vendas dele */
      if ($ant) $db->prepare('DELETE FROM papeis WHERE id = ?')->execute([$ant]); }
    responder(['id' => papelGuardar($c, $tipo, mb_substr(trim(strip_tags((string)($in['titulo'] ?? ''))), 0, 60), mb_substr(trim(strip_tags((string)($in['resumo'] ?? ''))), 0, 140), (array)($in['vias'] ?? []), $u, $in['vendas'] ?? '')]);   /* 2.45: de quais vendas é o cupom */
  }
  case 'papel_lista': { // os últimos cupons de um cliente (as crianças do mesmo responsável), do mais novo para o mais velho
    exigir(); session_write_close();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($in['ids'] ?? []))))); if (!$ids || count($ids) > 30) falha('Cliente não encontrado.', 404);
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $q = $db->prepare("SELECT p.id, p.crianca_id, p.quando, p.tipo, p.titulo, p.resumo, u.nome AS por_nome FROM papeis p LEFT JOIN usuarios u ON u.id = p.por WHERE p.crianca_id IN ($marcas) AND p.quando >= ? ORDER BY p.id DESC LIMIT 20");
    $q->execute(array_merge($ids, [date('Y-m-d H:i:s', time() - PAPEL_DIAS * 86400)]));
    responder(['papeis' => array_map('papelPublico', $q->fetchAll()), 'dias' => PAPEL_DIAS]);
  }
  case 'papel_dia': { /* 2.35 (o dono: "Casatom ... uma aba tipo vendidos do dia ... e selecione, tem a opção de reimprimir cupom lá"): os cupons de hoje da Casa Tom desta unidade */
    $u = exigir(); $b = balcaoCT(false); $uni = unidadeDoPedido(); session_write_close(); if (!$b) responder(['papeis' => []]);
    $q = $db->prepare("SELECT p.id, p.crianca_id, p.quando, p.tipo, p.titulo, p.resumo, p.vendas, p.estado, p.unidade_id, u.nome AS por_nome FROM papeis p LEFT JOIN usuarios u ON u.id = p.por WHERE p.crianca_id = ? AND p.quando >= ? AND p.estado != 'excluida'" . ($uni ? ' AND COALESCE(p.unidade_id, 0) = ' . (int)$uni : '') . ' ORDER BY p.id DESC LIMIT 300');
    $q->execute([(int)$b['id'], date('Y-m-d') . ' 00:00:00']);   /* 2.45: as vendas de cada cupom (o lápis e a lixeira do gestor); 2.69: para todos (os produtos da nota de produto) */
    responder(['papeis' => array_map(fn($l) => papelPublico($l) + ['vendas' => array_map('vendaCtPublica', vendasDoPapel($l))], $q->fetchAll())]);
  }
  case 'papel_ver': { // as vias de um cupom guardado
    $u = exigir(); session_write_close();
    $q = $db->prepare('SELECT p.*, u.nome AS por_nome FROM papeis p LEFT JOIN usuarios u ON u.id = p.por WHERE p.id = ?'); $q->execute([(int)($in['id'] ?? 0)]); $l = $q->fetch();
    if (!$l) falha('Este cupom não está mais guardado.', 404);
    $ctHoje = ($b = balcaoCT(false)) && (int)$l['crianca_id'] === (int)$b['id'] && substr((string)$l['quando'], 0, 10) === date('Y-m-d');   /* 2.35: a venda de hoje da Casa Tom, a equipe reimprime */
    /* 2.57 (o dono: "a reimpressão pode ser feita pelos operadores do cupom"): toda a equipe reimprime, a qualquer hora (1.57: era só nos 2 min) */
    responder(papelPublico($l) + ['vias' => papelVias($l)]);
  }
  case 'horas_ajuste': { /* 2.16 (o dono: "as meninas lançaram as horas erradas ... o gestor consiga consertar isso de uma maneira prática"): o gestor soma ou tira horas do saldo, com o motivo (sem venda: não mexe no caixa) */
    $u = exigir(); if (($u['papel'] ?? '') !== 'gestor') { if (!array_key_exists('senha', $in)) falha('Só o gestor corrige as horas.', 403); $u = autorizadorPelaSenha('desconto', (string)$in['senha'], $u); }   /* 2.77: na comanda (o lápis do comprou), com a senha do gestor */
    $c = crianca((int)($in['crianca'] ?? 0)); if (!$c) falha('Cliente não encontrado.', 404);
    $m = (int)($in['minutos'] ?? 0); if ($m === 0 || abs($m) > 999 * 60) falha('Escreva quantas horas somar ou tirar.');
    $mot = mb_substr(maiusculo(trim((string)($in['motivo'] ?? ''))), 0, 200); if (mb_strlen($mot) < 5) falha('Escreva o motivo da correção.');
    $saldo = (int)$c['pacote_min'] - autosDaVisitaMin($c) - (int)$c['usado_min']; if ($m < 0 && $saldo + $m < 0) falha('Não dá para tirar mais do que o saldo (' . dur(max(0, $saldo)) . ').');
    db()->prepare('UPDATE criancas SET pacote_min = pacote_min + ? WHERE id = ?')->execute([$m, (int)$c['id']]);
    registrar('alt', (int)$c['id'], (string)$c['nome'], 'Ajuste do gestor: ' . ($m > 0 ? '+' : '−') . dur(abs($m)) . ' nas horas' . (isset($in['comprou']) && is_numeric($in['comprou']) ? ' · comprou passa a ' . dur(max(0, (int)$in['comprou'])) : '') . ' · ' . $mot . ' · por ' . maiusculo(primeiroNome((string)$u['nome'])));
    responder(['saldo' => $saldo + $m]);
  }
  case 'ajuste_desfazer': { /* 2.78 (o dono: "eu tenho que apagar um lançamento que foi feito, um ajuste que foi feito"): desfaz um ajuste de horas do gestor; as horas dele voltam (o comprou e o saldo acompanham) */
    $u = exigir(); if (($u['papel'] ?? '') !== 'gestor') { if (!array_key_exists('senha', $in)) falha('Só o gestor desfaz o ajuste.', 403); $u = autorizadorPelaSenha('desconto', (string)$in['senha'], $u); }
    $q = $db->prepare("SELECT * FROM registros WHERE id = ? AND tipo = 'alt'"); $q->execute([(int)($in['id'] ?? 0)]); $g = $q->fetch(); $q->closeCursor();
    if (!$g || strpos((string)$g['texto'], 'Ajuste do gestor:') !== 0) falha('Este ajuste não foi encontrado.', 404);
    if (strpos((string)$g['texto'], ' · desfeito') !== false) falha('Este ajuste já foi desfeito.', 409);
    $m = minDoAjuste((string)$g['texto']); if (!$m) falha('Não consegui ler as horas deste ajuste.');
    $c = crianca((int)$g['crianca_id']); if (!$c) falha('Cliente não encontrado.', 404);
    $mot = mb_substr(maiusculo(trim((string)($in['motivo'] ?? ''))), 0, 200); if (mb_strlen($mot) < 5) falha('Escreva o motivo.');
    $saldo = (int)$c['pacote_min'] - autosDaVisitaMin($c) - (int)$c['usado_min'];
    if ($m > 0 && $saldo - $m < 0) falha('Não dá para desfazer: as horas deste ajuste já foram usadas (o saldo é ' . dur(max(0, $saldo)) . '). Corrija a visita primeiro, ou use o lápis do comprou na comanda.');
    $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([$m, (int)$c['id']]);
    $db->prepare("UPDATE registros SET texto = texto || ' · desfeito' WHERE id = ?")->execute([(int)$g['id']]);
    registrar('alt', (int)$c['id'], (string)$c['nome'], 'Ajuste desfeito: ' . ($m > 0 ? '+' : '−') . dur(abs($m)) . ' (de ' . substr((string)$g['quando'], 8, 2) . '/' . substr((string)$g['quando'], 5, 2) . ') · ' . $mot . ' · por ' . maiusculo(primeiroNome((string)$u['nome'])));
    responder(['saldo' => $saldo - $m]);
  }
  case 'plano_trocar': { /* 2.89 (o dono: "o gestor, quando clicar em cima do avulso ou pacote, tem a opção de mudar ... é só para questão de dados ... só o gestor pode fazer") */
    $u = exigir(); if ((string)($in['senha'] ?? '') === '') falha('Digite a senha do gestor.', 403);   /* 2.90 (o dono: "peça a senha do gestor, de qualquer gestor"): sempre, até no login do gestor */
    $c = crianca((int)($in['id'] ?? 0)); if (!$c || empty($c['entrou'])) falha('Esta criança não está no espaço agora.', 404);
    if (!empty($c['festa_id'])) falha('Na festa não há pacote nem avulso.', 409);
    $p = (string)($in['plano'] ?? ''); if ($p !== 'pacote' && $p !== 'avulso') falha('Escolha PACOTE ou AVULSA.');
    $u = autorizadorPelaSenha('plano', (string)$in['senha'], $u);   /* a senha de qualquer gestor; quem troca é ele */
    $auto = planoDaCrianca($c) === 'pacote' ? 'pacote' : 'avulso'; $era = planoPreferido($c) ?: $auto;
    $db->prepare('UPDATE criancas SET plano_pref = ?, plano_pref_em = ? WHERE id = ?')->execute([$p === $auto ? '' : $p, $p === $auto ? null : (string)$c['entrou'], (int)$c['id']]);   /* voltar ao que o sistema já dizia tira a marca */
    if ($p !== $era) registrar('alt', (int)$c['id'], (string)$c['nome'], 'Gestor trocou a recreação para ' . ($p === 'pacote' ? 'PACOTE' : 'AVULSA') . ' (era ' . ($era === 'pacote' ? 'PACOTE' : 'AVULSA') . ') · só nos dados · por ' . maiusculo(primeiroNome((string)$u['nome'])));
    responder(['plano' => $p, 'marcado' => $p !== $auto, 'por' => (string)$u['nome']]);
  }
  case 'venda_horas': { // 1.55: o gestor corrige as horas de um pacote (lançado errado), com o motivo
    $u = exigir(['gestor']); responder(alterarHorasVenda((int)($in['venda'] ?? 0), (int)($in['minutos'] ?? -1), mb_substr(maiusculo(trim((string)($in['motivo'] ?? ''))), 0, 200), $u));
  }
  case 'venda_cancelar': { // 1.21: quem estorna cancela uma venda registrada, com o motivo (pelo histórico do cliente)
    $u = exigir(); if (!podeEstornar($u)) falha('Só ' . quemEstorna() . ' cancela uma venda.', 403);
    if (mb_strlen(maiusculo((string)($in['motivo'] ?? ''))) >= 5) lerAtendente($in, $u);   // 1.25 (sem o motivo, o erro é o do motivo)
    responder(cancelarVenda((int)($in['venda'] ?? 0), $u, mb_substr(maiusculo((string)($in['motivo'] ?? '')), 0, 300)));
  }
  /* ---------- 1.23: pedidos de autorização (desconto, cancelar uma venda, nota fiscal) ---------- */
  case 'pedido_criar': { // quem não pode fazer, pede: aparece na tela de quem autoriza. Com a senha de quem autoriza, vale na hora
    $u = exigir(); $tipo = (string)($in['tipo'] ?? ''); if (!in_array($tipo, PEDIDO_TIPOS, true)) falha('Pedido desconhecido.');
    $g = array_key_exists('senha', $in) && !podeDecidir($u, $tipo) ? autorizadorPelaSenha($tipo, (string)$in['senha'], $u) : null;   // a senha é conferida antes: errada, nada é criado
    $r = pedidoCriar($u, $in);
    if ($g && $r['pedido']['status'] === 'pendente') $r = pedidoDecidir((int)$r['pedido']['id'], $g, true, 'senha');
    if (empty($r['ja']) && ($r['pedido']['status'] ?? '') === 'pendente') { $pid = (int)$r['pedido']['id'];   // 1.32: o aviso no celular de quem decide, depois de responder
      require_once __DIR__ . '/push-lib.php'; pushDepois(function () use ($pid) { $p = pedido($pid); if ($p && $p['status'] === 'pendente') pushPedido($p); }); }
    $r['pedidos'] = pedidosDaTela($u); responder($r);
  }
  case 'pedido_decidir': { // liberar ou negar: um toque de quem autoriza (ou a senha dele, no aparelho de quem pediu)
    $u = exigir(); $id = (int)($in['id'] ?? 0); $p = pedido($id); if (!$p) falha('Pedido não encontrado.', 404);
    $g = $u; $como = 'toque'; if (array_key_exists('senha', $in)) { $g = autorizadorPelaSenha((string)$p['tipo'], (string)$in['senha'], $u); $como = 'senha'; }
    $r = pedidoDecidir($id, $g, !array_key_exists('ok', $in) || !empty($in['ok']), $como); $r['pedidos'] = pedidosDaTela($u); responder($r);
  }
  case 'pedido_cancelar': { // quem pediu desiste (ou tira o desconto liberado que não vai usar); o gestor também tira a nota autorizada que não vai sair
    $u = exigir(); $p = pedido((int)($in['id'] ?? 0)); if (!$p) falha('Pedido não encontrado.', 404);
    $meu = (int)$p['pedido_por'] === (int)$u['id']; if (!$meu && !podeDecidir($u, (string)$p['tipo'])) falha('Este pedido é de outra pessoa.', 403);
    $pode = $p['status'] === 'pendente' || ($p['status'] === 'liberado' && (in_array($p['tipo'], PEDIDO_SEM_VENDA, true) || ($p['tipo'] === 'nf' && $u['papel'] === 'gestor')));
    if ($pode) $db->prepare("UPDATE pedidos SET status = 'cancelado', resultado = ? WHERE id = ? AND status = ?")->execute([$meu ? 'CANCELADO POR QUEM PEDIU' : 'CANCELADO POR ' . maiusculo(primeiroNome((string)$u['nome'])), (int)$p['id'], (string)$p['status']]);
    responder(['pedido' => pedidoPublico(pedido((int)$p['id'])), 'pedidos' => pedidosDaTela($u)]);
  }
  case 'nf_emitida': { // a nota autorizada foi emitida: quem emitiu marca o número. O gestor corrige o número, ou desfaz a marcação (voltar)
    $u = exigir();
    if (empty($in['id']) && !empty($in['venda'])) { // 1.55: o número que voltou do Play Space Notas marca a nota da compra sozinho
      $q = $db->prepare("SELECT * FROM pedidos WHERE tipo = 'nf' AND venda_id = ? AND status IN ('pendente', 'liberado', 'usado') ORDER BY id DESC LIMIT 1"); $q->execute([(int)$in['venda']]); $p = $q->fetch(); $q->closeCursor();
      if (!$p) falha('Esta compra não tem nota aberta no Play Space.', 404);
      if ($p['status'] === 'usado' && (string)$p['numero'] === mb_substr(maiusculo((string)($in['numero'] ?? '')), 0, 40)) responder(['pedido' => pedidoPublico($p), 'pedidos' => pedidosDaTela($u)]);   // (já marcada com este número)
      if ($p['status'] === 'pendente') pedidoDecidir((int)$p['id'], $u, true, 'proprio', true);
      $in['id'] = (int)$p['id'];
    }
    $p = pedido((int)($in['id'] ?? 0)); if (!$p || $p['tipo'] !== 'nf') falha('Nota não encontrada.', 404);
    if (!empty($in['voltar'])) {
      if ($u['papel'] !== 'gestor') falha('Só o gestor desfaz a marcação da nota.', 403); if ($p['status'] !== 'usado') falha('Esta nota não está marcada como emitida.', 409);
      $db->prepare("UPDATE pedidos SET status = 'liberado', numero = '', feito_por = NULL, feito_nome = '', feito_em = NULL WHERE id = ?")->execute([(int)$p['id']]); responder(['pedido' => pedidoPublico(pedido((int)$p['id']))]);
    }
    $num = mb_substr(maiusculo((string)($in['numero'] ?? '')), 0, 40); if ($num === '') falha('Escreva o número da nota.');
    if ($p['status'] === 'usado' && $u['papel'] !== 'gestor') falha('Esta nota já está marcada como emitida. Só o gestor corrige o número.', 403);
    if (!in_array($p['status'], ['liberado', 'usado'], true)) falha($p['status'] === 'pendente' ? 'O gestor ainda não autorizou esta nota.' : 'Esta nota não está autorizada.', 409);
    $db->prepare("UPDATE pedidos SET status = 'usado', numero = ?, feito_por = ?, feito_nome = ?, feito_em = ? WHERE id = ?")->execute([$num, (int)$u['id'], (string)$u['nome'], agora(), (int)$p['id']]);
    responder(['pedido' => pedidoPublico(pedido((int)$p['id'])), 'pedidos' => pedidosDaTela($u)]);
  }
  case 'nf_livre': { /* 2.55 (o dono: "NF eu tenho que poder emitir pra todo mundo, viu? Tipo, que seja pacote de horas que comprou"): a nota com
       outro valor, já emitida no Play Space Notas (sem a compra aqui com valor, como as horas que vieram do sistema anterior), fica guardada:
       entra nas notas do dia, no faturamento em notas das Finanças e no histórico do cliente. O mesmo número de novo não guarda duas vezes */
    $u = exigir(); $c = crianca((int)($in['crianca'] ?? 0)); if (!$c) falha('Cliente não encontrado.', 404);
    if (nfUnidadeBloqueada((int)($in['un'] ?? 0) ?: (int)($u['unidade_id'] ?? 0) ?: (int)($c['unidade_id'] ?? 0))) falha('Na Anália Franco a nota fiscal não sai pelo Play Space (é outro CNPJ).', 403);   /* 2.85 */
    $num = mb_substr(maiusculo(trim((string)($in['numero'] ?? ''))), 0, 40); if ($num === '') falha('Falta o número da nota.');
    $valor = round((float)($in['valor'] ?? 0), 2); if ($valor <= 0.004 || $valor > 99999) falha('Confira o valor da nota.');
    $q = $db->prepare("SELECT * FROM pedidos WHERE tipo = 'nf' AND venda_id IS NULL AND crianca_id = ? AND numero = ? AND status = 'usado' ORDER BY id DESC LIMIT 1"); $q->execute([(int)$c['id'], $num]); $ja = $q->fetch(); $q->closeCursor();
    if ($ja) responder(['pedido' => pedidoPublico($ja), 'pedidos' => pedidosDaTela($u), 'ja' => true]);
    $nome = mb_substr(maiusculo(trim((string)($in['nome'] ?? ''))), 0, 120); if ($nome === '') $nome = (string)($c['resp'] !== '' ? $c['resp'] : $c['nome']);
    $dig = (string)preg_replace('/\D/', '', (string)($in['doc'] ?? '')); if (strlen($dig) !== 11 && strlen($dig) !== 14) falha('Confira o CPF ou o CNPJ da nota: o CPF tem 11 números e o CNPJ, 14.');
    $doc = strlen($dig) === 11 ? substr($dig, 0, 3) . '.' . substr($dig, 3, 3) . '.' . substr($dig, 6, 3) . '-' . substr($dig, 9) : substr($dig, 0, 2) . '.' . substr($dig, 2, 3) . '.' . substr($dig, 5, 3) . '/' . substr($dig, 8, 4) . '-' . substr($dig, 12);
    $email = mb_strtolower(mb_substr(trim((string)($in['email'] ?? '')), 0, 120)); if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    $desc = mb_substr(trim((string)preg_replace('/\s+/', ' ', (string)($in['descricao'] ?? ''))), 0, 200); if ($desc === '') $desc = 'Recreação infantil: pacote de horas';
    $nid = substr((string)preg_replace('/[^a-z0-9]/i', '', (string)($in['nota'] ?? '')), 0, 40); $emp = mb_substr(trim((string)($in['emp'] ?? '')), 0, 40); $agora = agora();
    $dados = ['nome' => $nome, 'doc' => $doc, 'email' => $email, 'item' => 'OUTRO VALOR: ' . maiusculo($desc), 'livre' => 1, 'nota' => $nid, 'emp' => $emp];
    $texto = 'Nota fiscal com outro valor: ' . $desc . ' (' . dinheiro($valor) . ') · em nome de ' . $nome . ' · ' . (strlen($dig) === 14 ? 'CNPJ ' : 'CPF ') . $doc;
    $db->prepare("INSERT INTO pedidos (tipo, status, crianca_id, venda_id, nome, resp, texto, motivo, valor, dados, pedido_por, pedido_nome, pedido_em, decidido_por, decidido_nome, decidido_em, como, feito_por, feito_nome, feito_em, numero, unidade_id) VALUES ('nf', 'usado', ?, NULL, ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, 'proprio', ?, ?, ?, ?, ?)")
      ->execute([(int)$c['id'], (string)$c['nome'], (string)$c['resp'], $texto, $valor, json_encode($dados, JSON_UNESCAPED_UNICODE), (int)$u['id'], (string)$u['nome'], $agora, (int)$u['id'], (string)$u['nome'], $agora, (int)$u['id'], (string)$u['nome'], $agora, $num, uniDaAlteracao($c)]);   /* 2.62 */
    $id = (int)$db->lastInsertId();
    registrar('alt', (int)$c['id'], (string)$c['nome'], 'Nota fiscal nº ' . $num . ' emitida com outro valor: ' . dinheiro($valor) . ' · ' . $desc . ($emp !== '' ? ' · ' . maiusculo($emp) : '') . ' · por ' . maiusculo(primeiroNome((string)$u['nome'])));
    responder(['pedido' => pedidoPublico(pedido($id)), 'pedidos' => pedidosDaTela($u)]);
  }
  case 'nf_cancelar': { // 1.55: a nota foi cancelada (só o gestor): sai do faturamento em notas e fica no relatório do dia como cancelada
    $u = exigir(['gestor']); $p = pedido((int)($in['id'] ?? 0)); if (!$p || $p['tipo'] !== 'nf') falha('Nota não encontrada.', 404);
    if ($p['status'] !== 'usado') falha('Esta nota não está marcada como emitida.', 409);
    $m = mb_substr(maiusculo(trim((string)($in['motivo'] ?? ''))), 0, 200); if (mb_strlen($m) < 5) falha('Escreva o motivo do cancelamento da nota.');
    $d = json_decode((string)$p['dados'], true); $d = is_array($d) ? $d : []; $d['canc'] = ['em' => agora(), 'por' => (string)$u['nome'], 'motivo' => $m];
    $st = $db->prepare("UPDATE pedidos SET status = 'cancelado', resultado = ?, dados = ? WHERE id = ? AND status = 'usado'");
    $st->execute(['NOTA CANCELADA: ' . $m . ' · POR ' . maiusculo(primeiroNome((string)$u['nome'])), json_encode($d, JSON_UNESCAPED_UNICODE), (int)$p['id']]);
    if (!$st->rowCount()) falha('Esta nota mudou agora mesmo em outro aparelho. Abra de novo.', 409);
    if ($p['crianca_id'] !== null) registrar('alt', (int)$p['crianca_id'], (string)$p['nome'], 'Nota fiscal nº ' . $p['numero'] . ' cancelada (' . dinheiro((float)$p['valor']) . ') · Motivo: ' . $m . ' · por ' . maiusculo(primeiroNome((string)$u['nome'])));
    responder(['pedido' => pedidoPublico(pedido((int)$p['id'])), 'pedidos' => pedidosDaTela($u)]);
  }
  case 'pedidos_historico': { /* 2.29 (o dono: "o histórico de autorização para que a gente consiga ver quem autorizou e o que autorizou"): os pedidos de um período, pelo dia em que foram pedidos */
    $u = exigir(['gestor', 'supervisor']); session_write_close(); $ok = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
    $de = (string)($in['de'] ?? ''); $ate = (string)($in['ate'] ?? ''); if (!$ok($de) || !$ok($ate)) falha('Escolha as datas.'); if ($ate < $de) [$de, $ate] = [$ate, $de];
    if ((strtotime($ate . ' 12:00:00') - strtotime($de . ' 12:00:00')) / 86400 > 400) falha('Escolha um período de até 400 dias.');
    $q = $db->prepare('SELECT * FROM pedidos WHERE pedido_em >= ? AND pedido_em < ? ORDER BY id DESC LIMIT 3000'); $q->execute([$de . ' 00:00:00', date('Y-m-d', strtotime($ate . ' 12:00:00') + 86400) . ' 00:00:00']);
    responder(['de' => $de, 'ate' => $ate, 'pedidos' => array_map('pedidoPublico', $q->fetchAll())]);
  }
  case 'pedidos_lista': { // a conferência das autorizações: gestor e supervisor veem todas; o operador, as que ele pediu
    $u = exigir(); session_write_close();
    $sql = 'SELECT p.* FROM pedidos p';
    if ($u['papel'] === 'atendente') { $q = $db->prepare($sql . ' WHERE p.pedido_por = ? ORDER BY p.id DESC LIMIT 60'); $q->execute([(int)$u['id']]); } else $q = $db->query($sql . ' ORDER BY p.id DESC LIMIT 200');
    responder(['pedidos' => array_map('pedidoPublico', $q->fetchAll())]);
  }
  case 'desfazer_venda': {
    $u = exigir(); exigirDesfazer($u); $ids = array_values(array_unique(array_filter(array_map('intval', array_merge((array)($in['vendas'] ?? []), [(int)($in['venda'] ?? 0)])))));
    $q = $db->prepare('SELECT * FROM vendas WHERE id = ?'); $tempoDe = []; $alvo = [];   // criança => minutos de brincar desfeitos
    foreach ($ids as $vid) {
      $q->execute([$vid]); $v = $q->fetch(); if (!$v) continue;
      if (time() - strtotime((string)$v['quando']) > 120) continue;   // o "Desfazer" do aviso só alcança a venda que acabou de ser feita
      $alvo[] = $v;
    }
    // 1.21: quem não estorna só desfaz o que não tem valor (o voucher, a saída do que acerta na devolução). A venda com valor, paga ou a receber, é de quem estorna
    $ficaLinha = [];   // as linhas do dia que não são apagadas (o que fica registrado)
    if (!podeEstornar($u)) {
      foreach ($alvo as $v) if (vendaPresa($v)) falha('Só ' . quemEstorna() . ' desfaz uma venda com valor: ela é cancelada no histórico do cliente, com o motivo.', 403);
      // a saída do que acerta na devolução (o fusquinha), desfeita na hora, fica registrada como saída cancelada, no nome de quem desfez (como a entrada)
      $abertos = fn($v) => (string)$v['alug'] !== '' && $v['voltou'] === null && (int)$v['alug_min'] === 0 && (string)$v['itens'] === ITENS_ALUG_ABERTO;
      if (array_filter($alvo, $abertos)) lerAtendente($in, $u);   // 1.25: com o nome da atendente
      foreach ($alvo as $i => $v) if ((string)$v['alug'] !== '' && $v['voltou'] === null && (int)$v['alug_min'] === 0 && (string)$v['itens'] === ITENS_ALUG_ABERTO) {
        $k = crianca((int)$v['crianca_id']); if (!$k || !alugueis((int)$k['id'], (string)$v['alug'])) continue;
        cancelarAluguel($k, (string)$v['alug'], $u, MOTIVO_DESFEITA, true); unset($alvo[$i]); $tempoDe[(int)$v['crianca_id']] = $tempoDe[(int)$v['crianca_id']] ?? 0; if (!empty($v['registro'])) $ficaLinha[(int)$v['registro']] = 1;
      }
    }
    foreach ($alvo as $v) {
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$v['minutos'], $v['crianca_id']]);
      $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([$v['id']]); $tempoDe[(int)$v['crianca_id']] = ($tempoDe[(int)$v['crianca_id']] ?? 0) + (int)$v['minutos'];
      devolverEstoque($v);   // era venda de produto: as unidades voltam para o estoque
    }
    if (!$tempoDe) falha('Nada para desfazer.');
    // 1.21: só as linhas do dia da própria venda (da mesma criança e de agora há pouco): o Desfazer não apaga outra linha do dia
    $dr = $db->prepare('DELETE FROM registros WHERE id = ? AND crianca_id IN (' . implode(',', array_map('intval', array_keys($tempoDe))) . ') AND quando >= ?'); $desde = date('Y-m-d H:i:s', time() - 300);
    foreach (array_filter(array_map('intval', array_merge((array)($in['registros'] ?? []), [(int)($in['registro'] ?? 0)]))) as $rid) if (!isset($ficaLinha[$rid])) $dr->execute([$rid, $desde]);
    // desfez o tempo e o saldo voltou a zero: o acesso volta a ficar bloqueado (senão vender e desfazer liberava a entrada de graça)
    foreach ($tempoDe as $cid => $m) { $c = crianca($cid);
      if ($c && $m > 0 && $c['entrou'] === null && restante($c) < periodo() && regra()['bloqueioAuto'] === 'sim' && (!isset($c['liberado']) || (int)$c['liberado'])) bloquearAcesso($c, false); }
    responder();
  }

  /* ---------- WhatsApp: conversa e conexão ---------- */
  case 'mensagem_enviar': {
    $u = exigir(); $c = crianca((int)($in['crianca'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $texto = trim((string)($in['texto'] ?? '')); if ($texto === '') falha('Escreva a mensagem.');
    $r = mandarMensagem($c, mb_substr($texto, 0, 4000), false, '', $u['nome']);
    $db->prepare("UPDATE mensagens SET lida = 1 WHERE crianca_id = ? AND de = 'ela'")->execute([$c['id']]);
    responder($r);
  }
  case 'mensagens_lidas': { exigir(); $db->prepare("UPDATE mensagens SET lida = 1 WHERE crianca_id = ? AND de = 'ela'")->execute([(int)($in['crianca'] ?? 0)]); responder(); }
  case 'whats_config': { $u = exigir(['gestor', 'supervisor']); responder(['config' => whatsPublico(whatsCfg())]); }
  case 'whats_salvar': {
    $u = exigir(); podeAjustar($u); $c = whatsCfg();
    $url = $val('url'); if ($url !== '' && !preg_match('#^https?://#', $url)) falha('O endereço da Evolution API precisa começar com http:// ou https://.');
    if ($url !== '') $c['url'] = rtrim($url, '/');
    $inst = preg_replace('/[^A-Za-z0-9_-]/', '', $val('instancia')); if ($inst !== '') $c['instancia'] = $inst;
    $chave = $val('chave'); if ($chave !== '' && strpos($chave, '•') === false) $c['chave'] = $chave;
    gravarCfg('whats', $c); responder(['config' => whatsPublico($c)]);
  }
  case 'whats_qr': { // o QR Code para escanear no celular da loja
    $u = exigir(); podeAjustar($u); $c = whatsCfg(); if ($c['chave'] === '') falha('Antes, salve a chave (apikey) da Evolution API.');
    $r = evoQr($c);
    if ($r['estado'] === 'conectado') { $i = evoInfo($c); $c['numero'] = $i['numero'] ?? $c['numero']; $c['nome'] = $i['nome'] ?? $c['nome']; $c['conectado_em'] = $c['conectado_em'] ?: agora(); gravarCfg('whats', $c); try { evoWebhook($c); } catch (Throwable $e) { registrarErro($e->getMessage()); } }
    responder($r + ['config' => whatsPublico($c)]);
  }
  case 'whats_status': {
    $u = exigir(['gestor', 'supervisor']); $c = whatsCfg(); if ($c['chave'] === '') responder(['estado' => 'sem_chave', 'config' => whatsPublico($c)]);
    try { $r = evoEstado($c); } catch (Throwable $e) { responder(['estado' => 'erro', 'erro' => $e->getMessage(), 'config' => whatsPublico($c)]); }
    if ($r['estado'] === 'conectado') { if (!$c['conectado_em'] || $c['numero'] === '') { $i = evoInfo($c); $c['numero'] = $i['numero'] ?? $c['numero']; $c['nome'] = $i['nome'] ?? $c['nome']; $c['conectado_em'] = $c['conectado_em'] ?: agora(); gravarCfg('whats', $c); try { evoWebhook($c); } catch (Throwable $e) { registrarErro($e->getMessage()); } } }
    elseif ($c['conectado_em']) { $c['conectado_em'] = null; gravarCfg('whats', $c); }
    responder($r + ['config' => whatsPublico($c)]);
  }
  case 'whats_desconectar': {
    $u = exigir(); podeAjustar($u); $c = whatsCfg();
    try { evoSair($c); } catch (Throwable $e) { /* já estava fora */ }
    $c['conectado_em'] = null; $c['numero'] = ''; $c['nome'] = ''; gravarCfg('whats', $c); responder(['config' => whatsPublico($c)]);
  }

  /* ---------- agenda ---------- */
  case 'comp_feito': { $u = exigir(); if ($u['papel'] !== 'gestor') { $q = $db->prepare('SELECT tipo FROM compromissos WHERE id = ?'); $q->execute([(int)($in['id'] ?? 0)]); if ($q->fetchColumn() !== 'vfut') falha('Só o gestor mexe na agenda.', 403); }   /* 2.08: o lembrete da venda futura, a equipe também marca */ $db->prepare('UPDATE compromissos SET feito = ? WHERE id = ?')->execute([empty($in['feito']) ? 0 : 1, (int)($in['id'] ?? 0)]); responder(); }
  case 'comp_salvar': {   // 1.49: só o gestor marca (e muda) na agenda; a festa é marcada no atendimento
    exigir(['gestor']); $id = (int)($in['id'] ?? 0); $titulo = mb_substr(emMaiusculas($val('titulo')), 0, 120); if ($titulo === '') falha('Falta dizer o quê.');
    $dia = $val('dia'); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) falha('Dia inválido.');
    $hora = $val('hora', '15:00'); if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) $hora = '15:00';
    $tipo = in_array($val('tipo'), ['oficina', 'festa', 'compromisso', 'postagem'], true) ? $val('tipo') : 'oficina';
    $subs = ['oficina' => 'Oficina', 'festa' => 'Festa', 'compromisso' => 'Compromisso', 'postagem' => 'Postagem'];
    if ($id) $db->prepare('UPDATE compromissos SET dia = ?, hora = ?, titulo = ?, tipo = ?, sub = ?, obs = ? WHERE id = ?')->execute([$dia, $hora, $titulo, $tipo, $subs[$tipo], mb_substr(emMaiusculas($val('obs')), 0, 500), $id]);
    else { $db->prepare('INSERT INTO compromissos (dia, hora, titulo, tipo, sub, obs, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$dia, $hora, $titulo, $tipo, $subs[$tipo], mb_substr(emMaiusculas($val('obs')), 0, 500), agora()]); $id = (int)$db->lastInsertId(); }
    responder(['id' => $id]);
  }
  case 'comp_apagar': { exigir(['gestor']); $db->prepare('DELETE FROM compromissos WHERE id = ?')->execute([(int)($in['id'] ?? 0)]); responder(); }

  /* ---------- ajustes: a regra das horas e os pacotes ---------- */
  case 'regra_salvar': {
    $u = exigir(); podeAjustar($u); $r = regra();
    foreach (['tolerancia' => [0, 5, 9, 10, 15], 'fecha' => [20, 21, 22, 23, 24], 'aviso' => [5, 10, 15, 30, 60, 120], 'cancelaMin' => [0, 5, 9, 10, 15], 'reabreMin' => [0, 10, 15, 30, 60], 'tolCupom' => [0, 5, 9, 10, 15]] as $k => $ops) if (isset($in[$k]) && in_array((int)$in[$k], $ops, true)) $r[$k] = (int)$in[$k];
    if (isset($in['autoMsg']) && in_array($in['autoMsg'], ['ambos', 'entrada', 'nao'], true)) $r['autoMsg'] = $in['autoMsg'];
    if (isset($in['bloqueioAuto']) && in_array($in['bloqueioAuto'], ['sim', 'nao'], true)) $r['bloqueioAuto'] = $in['bloqueioAuto'];
    if (isset($in['estorno']) && in_array($in['estorno'], ['gestor', 'supervisor', 'todos'], true)) $r['estorno'] = $in['estorno'];   // 1.21: quem desfaz ou cancela o que já tem valor
    if (isset($in['cupom']) && in_array($in['cupom'], ['sim', 'nao'], true)) $r['cupom'] = $in['cupom'];   // 0.93: acompanhar o cupom fiscal de cada pagamento
    // 0.94: o que vai escrito no cupom impresso: o nome e o telefone da loja e o aviso do pé
    if (isset($in['cupomLoja'])) { $n = mb_substr(maiusculo((string)$in['cupomLoja']), 0, 40); if ($n === '') falha('Escreva o nome da loja que sai no cupom.'); $r['cupomLoja'] = $n; }
    if (isset($in['cupomTel'])) $r['cupomTel'] = mb_substr(trim((string)preg_replace('/[^0-9()+\-. ]/', '', (string)$in['cupomTel'])), 0, 30);
    if (isset($in['cupomAviso'])) $r['cupomAviso'] = mb_substr(maiusculo((string)$in['cupomAviso']), 0, 300);
    if (isset($in['cupomTam']) && in_array($in['cupomTam'], ['normal', 'compacto', 'economico'], true)) $r['cupomTam'] = $in['cupomTam'];
    if (isset($in['horasCpf']) && in_array($in['horasCpf'], ['sim', 'nao'], true)) $r['horasCpf'] = $in['horasCpf'];
    if (isset($in['senhaUni']) && in_array($in['senhaUni'], ['sim', 'nao'], true)) {   /* 2.21: pedir (ou não) a senha da unidade na tela de entrar da loja: só o gestor */
      if (($u['papel'] ?? '') !== 'gestor') falha('Só o gestor muda a senha das unidades.', 403);
      if ((string)($r['senhaUni'] ?? 'nao') !== $in['senhaUni']) registrar('alt', null, 'Computador da loja', ($in['senhaUni'] === 'sim' ? 'A tela de entrar volta a pedir a senha da unidade' : 'A tela de entrar fica sem a senha da unidade') . ' · por ' . primeiroNome((string)$u['nome']));
      $r['senhaUni'] = $in['senhaUni']; }   /* 2.11: as horas são do CPF */   /* 2.09: o tamanho do cupom */
    if (isset($in['ctJunto']) && is_array($in['ctJunto'])) {   /* 2.37: o Play e a Casa Tom juntos ou separados em cada lugar: só o gestor */
      if (($u['papel'] ?? '') !== 'gestor') falha('Só o gestor muda.', 403); $j = (array)($r['ctJunto'] ?? []);
      foreach ($in['ctJunto'] as $k => $v) { $k = lojaLocalOk((string)$k); if ($k === '' || !in_array($v, ['sim', 'nao'], true)) continue; if (($j[$k] ?? '') !== $v) registrar('alt', null, 'Unidades', 'Play e Casa Tom em ' . $k . ': ' . ($v === 'sim' ? 'juntos (troca por dentro)' : 'separados') . ' · por ' . primeiroNome((string)$u['nome'])); $j[$k] = $v; }
      $r['ctJunto'] = $j; }
    if (isset($in['sapateiras'])) { $n = (int)$in['sapateiras']; if ($n < 1 || $n > 200) falha('Quantas sapateiras existem? De 1 a 200.'); $r['sapateiras'] = $n; }
    if (isset($in['pacotes']) && is_array($in['pacotes'])) {
      // um pacote: horas, valor, tipo (com produto, o brinde já no valor; ou seco, só as horas), nome opcional e em até quantas vezes parcela. Pode haver vários do mesmo tempo
      $ps = []; foreach ($in['pacotes'] as $p) { $h = (int)($p['h'] ?? 0); $v = round((float)($p['v'] ?? 0), 2); if ($h <= 0 || $h > 500 || $v < 0) continue;
        $com = mb_substr(maiusculo((string)($p['com'] ?? '')), 0, 60); $x = ['h' => $h, 'v' => $v, 'tipo' => tipoPacote(['tipo' => $p['tipo'] ?? null, 'com' => $com])];
        $nome = mb_substr(maiusculo((string)($p['nome'] ?? '')), 0, 40); if ($nome !== '') $x['nome'] = $nome;   // o nome é opcional: serve para separar pacotes do mesmo tempo
        if ($x['tipo'] === 'produto' && $com !== '') $x['com'] = $com; $parc = (int)($p['parc'] ?? 0); if ($parc > 1 && $parc <= 12) $x['parc'] = $parc;
        $ps[chavePacote($x)] = $x; }
      ksort($ps); $r['pacotes'] = array_values($ps);   // pacotes são opcionais: a lista pode ficar vazia
    }
    if (isset($in['servicos']) && is_array($in['servicos'])) { // os serviços e produtos da loja: valor único ou a cada tantos minutos
      $ss = []; $ids = []; $eraVolta = []; $eraTempo = []; foreach ((array)$r['servicos'] as $x) { $eraVolta[(string)($x['id'] ?? '')] = !empty($x['volta']); $eraTempo[(string)($x['id'] ?? '')] = !empty($x['porTempo']); }
      $eraFora = []; foreach ((array)$r['servicos'] as $x) $eraFora[(string)($x['id'] ?? '')] = array_values(array_map('intval', (array)($x['fora'] ?? []))); $uniOk = array_flip(array_map('intval', $db->query('SELECT id FROM unidades')->fetchAll(PDO::FETCH_COLUMN)));   /* 1.99 */
      foreach ($in['servicos'] as $k => $s) {
        $n = mb_substr(maiusculo((string)($s['n'] ?? '')), 0, 40); if ($n === '' || count($ss) >= 30) continue;
        $id = preg_replace('/[^a-z0-9_]/', '', (string)($s['id'] ?? '')); if ($id === '' || isset($ids[$id])) $id = 's' . dechex(crc32($n . '|' . $k . '|' . microtime())); $ids[$id] = 1;
        $modo = ($s['modo'] ?? 'fixo') === 'periodo' ? 'periodo' : 'fixo';
        $ss[] = ['id' => $id, 'n' => $n, 'v' => round(max(0, (float)($s['v'] ?? 0)), 2), 'modo' => $modo, 'min' => $modo === 'periodo' ? max(5, min(240, (int)($s['min'] ?? 30))) : 0, 'banco' => $modo === 'periodo' && !empty($s['banco']), 'obs' => mb_substr(maiusculo((string)($s['obs'] ?? '')), 0, 60),
          // volta: sai da loja e volta (fica na lista até "Devolveu"). O tempo de brincar nunca; uma tela antiga, que não manda a marca, deixa como estava
          'volta' => !($modo === 'periodo' && !empty($s['banco'])) && (array_key_exists('volta', (array)$s) ? !empty($s['volta']) : ($eraVolta[$id] ?? in_array($id, SERVICOS_QUE_VOLTAM, true))),
          // 1.20: porTempo = o que sai da loja e é cobrado a cada período é vendido por tempo comprado (como até a 1.19), em vez de fechar a conta na devolução. A tela que não manda a marca deixa como estava
          'porTempo' => $modo === 'periodo' && empty($s['banco']) && (array_key_exists('porTempo', (array)$s) ? !empty($s['porTempo']) : ($eraTempo[$id] ?? false)),
          // 1.99: as unidades onde o serviço não tem (a tela que não manda fica como estava)
          'fora' => array_key_exists('fora', (array)$s) ? array_values(array_unique(array_filter(array_map('intval', (array)$s['fora']), fn($x) => isset($uniOk[$x])))) : ($eraFora[$id] ?? [])];
      }
      if (!$ss) falha('Deixe pelo menos um serviço.'); $r['servicos'] = $ss; $r['extras'] = [];
    }
    if (isset($in['extras']) && is_array($in['extras'])) { // (versões antigas) produtos vendidos à parte
      $es = []; foreach ($in['extras'] as $e) { $n = mb_substr(trim((string)($e['n'] ?? '')), 0, 40); $v = (float)($e['v'] ?? 0); if ($n !== '' && $v >= 0 && count($es) < 30) $es[] = ['n' => $n, 'v' => $v]; }
      $r['extras'] = $es;
    }
    $g = $r; unset($g['festa'], $g['vendedoras'], $g['vend_uni']); gravarCfg('regra', $g); responder(['regra' => $r]);   // 1.12: a tabela da festa é a de fábrica: vai para a tela, mas não fica gravada
  }

  /* ---------- produtos da loja ---------- */
  case 'produto_ct_novo': { /* 2.10 (o dono: "no Casatom eu quero um botão vermelho escrito adicionar produto, para que os próprios operadores
       adicionem o valor e o produto"): qualquer pessoa da equipe cadastra um produto da Casa Tom na hora da venda (nome, valor, categoria) */
    $u = exigir(); $nome = mb_substr(maiusculo(trim(preg_replace('/\s+/u', ' ', (string)($in['n'] ?? '')))), 0, 60); if (mb_strlen($nome) < 2) falha('Como se chama o produto?');
    $valor = isset($in['v']) && is_numeric($in['v']) ? round((float)$in['v'], 2) : -1; if ($valor <= 0 || $valor > 100000) falha('Qual é o valor do produto?');
    $cat = mb_substr(maiusculo(trim((string)($in['cat'] ?? ''))), 0, 30);
    $q = $db->prepare('SELECT * FROM produtos WHERE ativo = 1 AND nome = ? COLLATE NOCASE ORDER BY id'); $q->execute([$nome]); $ja = $q->fetchAll();
    foreach ($ja as $x) if (abs((float)$x['valor'] - $valor) < 0.005) {   /* o mesmo produto: passa a valer também na Casa Tom */
      $lj = lojasValidas((string)($x['lojas'] ?? 'play') . ',ct'); if ($lj !== (string)$x['lojas']) $db->prepare('UPDATE produtos SET lojas = ?, mudou_em = ? WHERE id = ?')->execute([$lj, agora(), (int)$x['id']]);
      responder(['produto' => produtoPublico(produto((int)$x['id'])), 'produtos' => produtos(), 'ja' => true]); }
    if ($ja) falha('Já existe ' . $nome . ' no cadastro, por ' . dinheiro((float)$ja[0]['valor']) . '. Procure na lista (em "Ver todo o cadastro") ou peça ao gestor para mudar o valor.');
    $db->prepare('INSERT INTO produtos (nome, valor, estoque, ativo, criado_em, mudou_em, lojas, cat) VALUES (?, ?, NULL, 1, ?, ?, ?, ?)')->execute([$nome, $valor, agora(), agora(), 'ct', $cat]); $id = (int)$db->lastInsertId();
    registrar('est', null, $nome, 'Produto novo da Casa Tom · ' . dinheiro($valor) . ($cat !== '' ? ' · ' . $cat : '') . ' · cadastrado na venda por ' . primeiroNome($u['nome']));
    responder(['produto' => produtoPublico(produto($id)), 'produtos' => produtos()]);
  }
  case 'produto_salvar': { // só a gestora: nome, valor e estoque (vazio = a loja não controla o estoque desse produto)
    $u = exigir(); podeAjustar($u); $id = (int)($in['id'] ?? 0); $p = $id ? produto($id) : null; if ($id && (!$p || !$p['ativo'])) falha('Produto não encontrado.', 404);
    $nome = mb_substr(maiusculo((string)($in['n'] ?? '')), 0, 60); if ($nome === '') falha('Como se chama o produto?');
    $lojas = array_key_exists('l', $in) ? lojasValidas((string)$in['l']) : (string)($p['lojas'] ?? 'play'); if ($lojas === '') falha('Marque em qual loja o produto é vendido.');   /* 2.00 */
    $cat = array_key_exists('cat', $in) ? mb_substr(maiusculo(trim((string)$in['cat'])), 0, 30) : (string)($p['cat'] ?? '');
    $valor = isset($in['v']) && is_numeric($in['v']) ? round((float)$in['v'], 2) : -1; if ($valor < 0 || $valor > 100000) falha('Qual é o valor do produto?');
    $e = $in['est'] ?? null; $est = ($e === null || $e === '') ? null : (int)$e; if ($est !== null && ($est < -9999 || $est > 99999)) falha('Quantos há no estoque?');
    $q = $db->prepare('SELECT id FROM produtos WHERE ativo = 1 AND id != ? AND nome = ? COLLATE NOCASE AND ABS(valor - ?) < 0.005'); $q->execute([$id, $nome, $valor]);
    if ($q->fetchColumn()) falha('Já existe um produto com este nome e este valor.');
    $quem = ' · por ' . primeiroNome($u['nome']);
    if ($p) {
      $antes = $p['estoque'] === null ? null : (int)$p['estoque']; $novo = $est; $visto = $in['est_visto'] ?? null;
      // a tela diz o estoque que mostrava: se alguém vendeu enquanto a folha estava aberta, vale a diferença (chegaram 10 → soma 10), para a venda não se perder
      if ($est !== null && $antes !== null && $visto !== null && $visto !== '' && (int)$visto !== $antes) $novo = $antes + ($est - (int)$visto);
      $db->prepare('UPDATE produtos SET nome = ?, valor = ?, estoque = ?, mudou_em = ?, lojas = ?, cat = ? WHERE id = ?')->execute([$nome, $valor, $novo, agora(), $lojas, $cat, $id]);
      if ($novo !== $antes) registrar('est', null, $nome, 'Estoque: ' . ($antes === null ? 'sem controle' : $antes) . ' → ' . ($novo === null ? 'sem controle' : $novo) . $quem);
      if (abs((float)$p['valor'] - $valor) >= 0.005) registrar('est', null, $nome, 'Valor: ' . dinheiro((float)$p['valor']) . ' → ' . dinheiro($valor) . $quem);
    } else {
      $db->prepare('INSERT INTO produtos (nome, valor, estoque, ativo, criado_em, mudou_em, lojas, cat) VALUES (?, ?, ?, 1, ?, ?, ?, ?)')->execute([$nome, $valor, $est, agora(), agora(), $lojas, $cat]); $id = (int)$db->lastInsertId();
      registrar('est', null, $nome, 'Produto novo · ' . dinheiro($valor) . ($est === null ? '' : ' · estoque ' . $est) . $quem);
    }
    responder(['produto' => produtoPublico(produto($id)), 'produtos' => produtos()]);
  }
  case 'ct_balcao': { exigir(); $b = balcaoCT(true); responder(['balcao' => $b ? criancaPublica($b) : null]); }   /* 2.00: o cliente da loja da Casa Tom (quando o aparelho acabou de trocar para lá) */
  case 'produtos_importar': { /* 2.00: a planilha de produtos (a Casa Tom). O que já existe com o mesmo nome fica com o preço de lá e passa a valer
       também nesta loja (o dono: "o cadastro de produto pode se estender para 2 lojas"). Até 500 por pedido */
    $u = exigir(); podeAjustar($u); $lj = lojasValidas((string)($in['l'] ?? 'ct')); if ($lj === '') $lj = 'ct';
    $tem = []; foreach ($db->query('SELECT id, nome, lojas FROM produtos WHERE ativo = 1')->fetchAll() as $x) $tem[mb_strtoupper(trim((string)$x['nome']))] = ['id' => (int)$x['id'], 'l' => (string)($x['lojas'] ?? 'play')];
    $upd = $db->prepare('UPDATE produtos SET lojas = ?, mudou_em = ? WHERE id = ?'); $junto = 0;
    $ins = $db->prepare('INSERT INTO produtos (nome, valor, estoque, ativo, criado_em, mudou_em, lojas, cat) VALUES (?, ?, NULL, 1, ?, ?, ?, ?)'); $novos = 0; $ja = 0; $ruins = 0; $ag = agora();
    $db->beginTransaction();
    try {
      foreach (array_slice((array)($in['linhas'] ?? []), 0, 500) as $l) {
        if (!is_array($l)) { $ruins++; continue; } $n = mb_substr(maiusculo(trim((string)preg_replace('/\s+/u', ' ', (string)($l['n'] ?? '')))), 0, 60); $v = is_numeric($l['v'] ?? null) ? round((float)$l['v'], 2) : -1;
        if ($n === '' || $v < 0 || $v > 100000) { $ruins++; continue; } $k = mb_strtoupper($n);
        if (isset($tem[$k])) { $ja++; $x = $tem[$k]; $nl = lojasValidas($x['l'] . ',' . $lj); if ($nl !== lojasValidas($x['l'])) { $upd->execute([$nl, $ag, $x['id']]); $tem[$k]['l'] = $nl; $junto++; } continue; }
        $ins->execute([$n, $v, $ag, $ag, $lj, mb_substr(maiusculo(trim((string)($l['cat'] ?? ''))), 0, 30)]); $tem[$k] = ['id' => (int)$db->lastInsertId(), 'l' => $lj]; $novos++;
      }
      $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); registrarErro('produtos_importar: ' . $e->getMessage()); falha('Não consegui importar os produtos. Nada deste lote foi gravado.', 500); }
    if ($novos || $junto) registrar('est', null, 'PRODUTOS', 'Produtos importados: ' . $novos . ($junto ? ' · ' . $junto . ' já cadastrados passam a valer também nesta loja' : '') . ' · por ' . primeiroNome($u['nome']));
    responder(['novos' => $novos, 'ja' => $ja, 'junto' => $junto, 'ruins' => $ruins, 'produtos' => produtos()]);
  }
  case 'produto_apagar': { // sai da lista; as vendas antigas continuam no histórico. voltar = o Desfazer do aviso
    $u = exigir(); podeAjustar($u); $p = produto((int)($in['id'] ?? 0)); if (!$p) falha('Produto não encontrado.', 404);
    $volta = !empty($in['voltar']);
    if ((bool)$p['ativo'] !== $volta) {
      $db->prepare('UPDATE produtos SET ativo = ?, mudou_em = ? WHERE id = ?')->execute([$volta ? 1 : 0, agora(), (int)$p['id']]);
      registrar('est', null, (string)$p['nome'], ($volta ? 'Produto voltou para a lista' : 'Produto tirado da lista') . ' · por ' . primeiroNome($u['nome']));
    }
    responder(['produtos' => produtos()]);
  }

  /* ---------- equipe ---------- */
  /* ---------- 1.06: unidades ---------- */
  case 'unidade_salvar': { // nova (sem id) ou com outro nome. O nome fica em maiúsculas e não pode repetir entre as ativas
    $u = exigir(); podeAjustar($u); $id = (int)($in['id'] ?? 0); $nome = mb_substr(maiusculo($val('nome')), 0, 40);
    if ($nome === '') falha('Escreva o nome da unidade.');
    foreach (unidades() as $x) if ($x['ativo'] && $x['id'] !== $id && mb_strtolower($x['nome'], 'UTF-8') === mb_strtolower($nome, 'UTF-8')) falha('Já existe uma unidade com esse nome.', 409);
    if ($id) {
      $q = $db->prepare('SELECT * FROM unidades WHERE id = ?'); $q->execute([$id]); $antes = $q->fetch(); if (!$antes) falha('Unidade não encontrada.', 404);
      $db->prepare('UPDATE unidades SET nome = ?, ativo = 1, mudou_em = ? WHERE id = ?')->execute([$nome, agora(), $id]);
      if (uniEhCasaTom($nome) && !uniEhCasaTom((string)$antes['nome'])) $db->prepare('UPDATE unidades SET ct_desde = ? WHERE id = ?')->execute([date('Y-m-d', strtotime('+1 day', agoraTs())), $id]);   /* 1.91: virou Casa Tom agora: vale de amanhã em diante */
      if ($antes['nome'] !== $nome) registrar('cad', null, 'UNIDADES', 'Unidade ' . $antes['nome'] . ' agora é ' . $nome . ' · por ' . primeiroNome((string)$u['nome']));
    } else {
      if ((int)$db->query('SELECT COUNT(*) FROM unidades WHERE ativo = 1')->fetchColumn() >= UNIDADES_MAX) falha('São no máximo ' . UNIDADES_MAX . ' unidades.');
      $db->prepare('INSERT INTO unidades (nome, criado_em, mudou_em, ct_desde) VALUES (?, ?, ?, ?)')->execute([$nome, agora(), agora(), uniEhCasaTom($nome) ? date('Y-m-d', strtotime('+1 day', agoraTs())) : null]); $id = (int)$db->lastInsertId();
      registrar('cad', null, 'UNIDADES', 'Nova unidade: ' . $nome . ' · por ' . primeiroNome((string)$u['nome']));
    }
    responder(['id' => $id, 'unidades' => unidades()]);
  }
  case 'unidade_apagar': { // sai das escolhas; o que já foi registrado nela continua com o nome. voltar = o Desfazer do aviso
    $u = exigir(); podeAjustar($u); $id = (int)($in['id'] ?? 0);
    $q = $db->prepare('SELECT * FROM unidades WHERE id = ?'); $q->execute([$id]); $x = $q->fetch(); if (!$x) falha('Unidade não encontrada.', 404);
    if (!empty($in['voltar'])) { $db->prepare('UPDATE unidades SET ativo = 1, mudou_em = ? WHERE id = ?')->execute([agora(), $id]); if (!empty($in['registro'])) $db->prepare("DELETE FROM registros WHERE id = ? AND tipo = 'cad'")->execute([(int)$in['registro']]); responder(['unidades' => unidades()]); }
    $q = $db->prepare('SELECT COUNT(*) FROM criancas WHERE ativo = 1 AND entrou IS NOT NULL AND unidade_id = ?'); $q->execute([$id]); $n = (int)$q->fetchColumn();
    if ($n) falha(($n === 1 ? 'Há uma criança' : "Há $n crianças") . ' no espaço nesta unidade agora. Dê a saída antes de remover a unidade.', 409);
    $db->prepare('UPDATE unidades SET ativo = 0, mudou_em = ? WHERE id = ?')->execute([agora(), $id]);
    $reg = registrar('cad', null, 'UNIDADES', 'Unidade removida: ' . $x['nome'] . ' · por ' . primeiroNome((string)$u['nome']));
    responder(['registro' => $reg, 'unidades' => unidades()]);
  }

  case 'usuario_salvar': {
    $u = exigir(); podeAjustar($u); $id = (int)($in['id'] ?? 0);
    $nome = mb_substr(maiusculo($val('nome')), 0, 80); $login = loginDe($val('login'));   /* 2.02: o login pode ter espaço (antes o espaço era tirado: "juliaalves") */ $papel = in_array($val('papel'), array_keys(PAPEIS), true) ? $val('papel') : 'atendente'; $senha = (string)($in['senha'] ?? '');
    if ($nome === '' || $login === '') falha('Preencha o nome e o login.');
    /* 2.02: a unidade da pessoa (0 = qualquer uma). A tela de antes não manda: fica como estava */
    $temUni = array_key_exists('unidade', $in); $uni = (int)($in['unidade'] ?? 0);
    if ($uni > 0) { $q = $db->prepare('SELECT id FROM unidades WHERE id = ? AND ativo = 1'); $q->execute([$uni]); if (!$q->fetchColumn()) falha('Essa unidade não existe mais.'); }
    $q = $db->prepare('SELECT id, ativo FROM usuarios WHERE login = ? AND id <> ?'); $q->execute([$login, $id]); $outro = $q->fetch();
    if ($outro && (int)$outro['ativo']) falha('Já existe alguém com esse login.');
    /* 2.01 (o dono: "quando eu excluí alguém da equipe, ele tem que me deixar criar novos usuários com esse nome"): quem saiu continua
       guardado (com o que fez), mas solta o login */
    if ($outro) $db->prepare("UPDATE usuarios SET login = login || '.saiu' || id WHERE id = ?")->execute([(int)$outro['id']]);
    if ($id) {
      if ($id === (int)$u['id'] && $papel !== 'gestor') falha('Você não pode tirar o seu próprio papel de gestor.');
      $db->prepare('UPDATE usuarios SET nome = ?, login = ?, papel = ? WHERE id = ?')->execute([$nome, $login, $papel, $id]);
      if ($temUni) $db->prepare('UPDATE usuarios SET unidade_id = ? WHERE id = ?')->execute([$uni ?: null, $id]);
      if ($senha !== '') { if (strlen($senha) < 6) falha('A senha precisa ter pelo menos 6 caracteres.'); $db->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $id]); sessaoNova($id); }   /* 2.60 */
    } else {
      if (strlen($senha) < 6) falha('Defina uma senha inicial com pelo menos 6 caracteres.');
      $db->prepare('INSERT INTO usuarios (nome, login, senha, papel, criado_em, unidade_id) VALUES (?, ?, ?, ?, ?, ?)')->execute([$nome, $login, password_hash($senha, PASSWORD_DEFAULT), $papel, agora(), $uni ?: null]); $id = (int)$db->lastInsertId();
    }
    if (array_key_exists('vende', $in)) $db->prepare('UPDATE usuarios SET vende = ? WHERE id = ?')->execute([$in['vende'] === 'sim' ? 1 : ($in['vende'] === 'nao' ? 0 : null), $id]);   /* 2.22: aparece em "Quem está vendendo" */
    responder(['id' => $id]);
  }
  case 'usuario_apagar': {
    $u = exigir(); podeAjustar($u); $id = (int)($in['id'] ?? 0); if ($id === (int)$u['id']) falha('Você não pode remover a si mesmo.');
    $db->prepare("UPDATE usuarios SET ativo = 0, login = CASE WHEN login LIKE '%.saiu%' THEN login ELSE login || '.saiu' || id END WHERE id = ?")->execute([$id]); responder();   /* 2.01: o login fica livre para alguém novo */
  }

  /* ---------- iDFace ---------- */
  case 'idface_salvar': {
    $u = exigir(); podeAjustar($u); require_once __DIR__ . '/idface-lib.php';
    $c = idfaceCfg();
    if (isset($in['ip'])) $c['ip'] = mb_substr(trim((string)$in['ip']), 0, 80);
    if (isset($in['usuario'])) $c['usuario'] = mb_substr(trim((string)$in['usuario']), 0, 40) ?: 'admin';
    if (isset($in['nome'])) $c['nome'] = mb_substr(trim((string)$in['nome']), 0, 40) ?: 'Recepção';
    if (isset($in['ligado'])) $c['ligado'] = (bool)$in['ligado'];
    gravarCfg('idface', $c); responder(['idface' => idfaceEstado()]);
  }
  case 'idface_nova_chave': { $u = exigir(); podeAjustar($u); require_once __DIR__ . '/idface-lib.php'; gravarCfg('ponte_chave', bin2hex(random_bytes(20))); responder(['idface' => idfaceEstado()]); }
  case 'crianca_liberar': { // a chavinha: liberada, a criança bate o rosto e as horas correm; bloqueada, o iDFace recusa
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $lib = empty($in['liberado']) ? 0 : 1; bloquearAcesso($c, (bool)$lib);
    responder(['liberado' => (bool)$lib]);
  }
  case 'idface_rosto': { // o aparelho vai pedir o rosto da criança ("olhe para a câmera"); fica na fila até o aparelho buscar
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    require_once __DIR__ . '/idface-lib.php'; $e = idfaceEstado(); if (!$e['ligado']) falha('O iDFace está desligado em Ajustes.'); if (!$e['ativo']) falha('O iDFace está sem contato com o site agora. Confira o aparelho e tente de novo.');
    apagarCfg('rosto_erro_' . $c['id']); apagarCfg('rosto_ok_' . $c['id']); apagarCfg('rosto_em_' . $c['id']);
    if ($c['idface_id'] === null) gravarCfg('mandar_' . $c['id'], (int)$c['id']);
    gravarCfg('pedirosto_' . $c['id'], (int)$c['id']); responder();
  }
  case 'idface_rosto_estado': { // a folha "olhe para a câmera" pergunta a cada 2 s
    exigir(); $id = (int)($in['id'] ?? 0); $c = crianca($id); if (!$c) falha('Criança não encontrada.', 404);
    $ps = cfg('push_estado', []); $fly = is_array($ps) ? ($ps['inflight'] ?? null) : null;
    $fase = cfg('rosto_ok_' . $id) ? 'ok' : (cfg('rosto_erro_' . $id) ? 'erro' : (is_array($fly) && ($fly['tipo'] ?? '') === 'rosto' && (int)($fly['crianca'] ?? 0) === $id ? 'capturando' : (cfg('pedirosto_' . $id) ? ($c['idface_id'] === null ? 'criando' : 'fila') : 'nada')));
    responder(['fase' => $fase, 'erro' => (string)cfg('rosto_erro_' . $id, ''), 'rosto' => !empty($c['rosto'])]);
  }
  case 'idface_mandar': { // manda (ou manda de novo) a criança para o aparelho
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    if ($c['idface_id'] !== null && $c['foto'] !== '') gravarCfg('foto_' . $c['id'], (int)$c['idface_id']); else gravarCfg('mandar_' . $c['id'], (int)$c['id']);
    responder();
  }
  /* ---------- 0.98: começar do zero. Só o gestor, e a limpeza pede a senha dele de novo ---------- */
  case 'limpeza_previa': { $u = exigir(); podeAjustar($u); responder(['agora' => limpezaResumo(), 'ultima' => limpezaPublica()]); }   // o que a folha mostra antes de apagar
  case 'cadastros_limpar': {
    $u = exigir(); podeAjustar($u);
    if (!senhaConfere($u, (string)($in['senha'] ?? ''))) { usleep(400000); falha('A senha não confere. Nada foi apagado.', 403); }
    responder(limparCadastros($u));
  }
  case 'cadastros_voltar': { $u = exigir(); podeAjustar($u); responder(limpezaDesfazer($u)); }   // o Desfazer da limpeza, enquanto não houver cadastro novo
  // 1.01: importar os clientes e as horas do sistema anterior (só o gestor)
  case 'importar_previa': { $u = exigir(); podeAjustar($u); require_once __DIR__ . '/importar.php'; responder(['ultima' => importacaoResumo(), 'lote' => IMPORTA_LOTE]); }
  case 'importar': { $u = exigir(); podeAjustar($u); require_once __DIR__ . '/importar.php'; responder(importarLinhas(array_values((array)($in['linhas'] ?? [])), $u, (string)($in['lote'] ?? ''), (string)($in['ja'] ?? 'horas') !== 'nada')); }   // ja = 'nada': quem já está cadastrado não recebe as horas da planilha
  case 'importar_desfazer': { $u = exigir(); podeAjustar($u); require_once __DIR__ . '/importar.php'; responder(importacaoDesfazer($u)); }
  case 'limpeza_depois': { $u = exigir(); podeAjustar($u); apagarCfg('limpeza_oferta'); responder(); }   // "agora não": a oferta que veio com a atualização não aparece de novo (a limpeza continua em Ajustes)
  case 'exemplos_carregar': { // o teste do rodapé: 10 crianças de exemplo no espaço, em horários diferentes, e alguns compromissos na agenda
    $u = exigir(); podeAjustar($u); require_once __DIR__ . '/exemplos.php'; responder(exemplosCarregar((int)$u['id']));
  }
  case 'exemplos_apagar': { $u = exigir(); podeAjustar($u); require_once __DIR__ . '/exemplos.php'; exemplosApagar(); responder(); }
  case 'idface_simular': { // para testar sem o aparelho: como se a criança tivesse olhado para a câmera
    $u = exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    if ($c['entrou'] && !podeEstornar($u)) falha('Só ' . quemEstorna() . ' simula a saída pelo rosto (ela não passa pelo pagamento).', 403);   // 1.21
    if ($c['entrou']) responder(['resultado' => 'saída'] + fazerSaida($c, 'simulação'));
    responder(['resultado' => 'entrada'] + fazerEntrada($c, 'simulação'));
  }
  // 1.32: os avisos no celular. A chave pública da loja (para o navegador fazer a inscrição) e se este aparelho já está ligado
  case 'push_chave': {
    $u = exigir(); require_once __DIR__ . '/push-lib.php'; $ep = (string)($in['endpoint'] ?? '');
    if (!pushServidorPronto()) falha('O servidor da loja ainda não consegue mandar avisos: a hospedagem precisa do PHP 7.3 ou mais novo, com openssl e curl. Peça para atualizar o PHP no painel da hospedagem.');
    $q = $db->prepare('SELECT usuario_id FROM push_inscricoes WHERE endpoint = ?'); $q->execute([$ep]); $dono = $q->fetchColumn();
    $n = $db->prepare('SELECT COUNT(*) FROM push_inscricoes WHERE usuario_id = ?'); $n->execute([(int)$u['id']]);
    responder(['chave' => pushChaves()['pub'], 'ligado' => $dono !== false && (int)$dono === (int)$u['id'], 'aparelhos' => (int)$n->fetchColumn()]);
  }
  case 'push_inscrever': { // o aparelho ligou os avisos: a inscrição fica com quem está logado nele (outro login no mesmo aparelho passa a ser o dono)
    $u = exigir(); require_once __DIR__ . '/push-lib.php';
    $ep = trim((string)($in['endpoint'] ?? '')); $pk = trim((string)($in['p256dh'] ?? '')); $au = trim((string)($in['auth'] ?? ''));
    if ($ep === '' || strlen($ep) > 900 || !pushHostPermitido($ep)) falha('Este navegador não mandou um endereço de avisos que o sistema aceite.');
    $a = pushDeB64u($pk); if (strlen($a) !== 65 || $a[0] !== "\x04" || strlen(pushDeB64u($au)) !== 16) falha('A inscrição do aparelho veio incompleta. Tente ligar de novo.');
    $ap = mb_substr(trim((string)($in['aparelho'] ?? '')), 0, 80);
    $db->prepare('INSERT INTO push_inscricoes (usuario_id, endpoint, p256dh, auth, aparelho, criado_em, falhas) VALUES (?, ?, ?, ?, ?, ?, 0) ON CONFLICT(endpoint) DO UPDATE SET usuario_id = excluded.usuario_id, p256dh = excluded.p256dh, auth = excluded.auth, aparelho = excluded.aparelho, falhas = 0')
      ->execute([(int)$u['id'], $ep, $pk, $au, $ap, agora()]);
    responder(['ligado' => true]);
  }
  case 'push_sair': { // desligar os avisos deste aparelho
    $u = exigir(); $db->prepare('DELETE FROM push_inscricoes WHERE endpoint = ? AND usuario_id = ?')->execute([(string)($in['endpoint'] ?? ''), (int)$u['id']]); responder();
  }
  case 'push_testar': { // um aviso de teste para os aparelhos de quem está logado (ou só para este)
    $u = exigir(); require_once __DIR__ . '/push-lib.php'; $ep = (string)($in['endpoint'] ?? '');
    $q = $db->prepare('SELECT * FROM push_inscricoes WHERE usuario_id = ?' . ($ep !== '' ? ' AND endpoint = ?' : '') . ' ORDER BY id'); $q->execute($ep !== '' ? [(int)$u['id'], $ep] : [(int)$u['id']]); $L = $q->fetchAll();
    if (!$L) falha('Os avisos ainda não estão ligados neste aparelho.', 409);
    $r = pushMandar($L, fn($i) => ['t' => 'Play Space', 'b' => 'Aviso de teste: os pedidos de autorização vão chegar assim, mesmo com o Play Space fechado.', 'tag' => 'ps-teste', 'url' => in_array($u['papel'], ['gestor', 'supervisor'], true) ? './#aprovar' : './']);
    if (!$r['ok']) falha($r['saiu'] ? 'O serviço de avisos do navegador desligou este aparelho. Toque em "Ativar avisos" de novo.' : 'O aviso não saiu agora (o serviço de avisos do navegador não respondeu). Tente de novo em instantes.', 502);
    responder(['enviados' => $r['ok'], 'falhas' => $r['erro']]);
  }
  default: falha('Ação desconhecida: ' . $acao, 404);
}
