<?php
/* =====================================================================
   Play Space 2.70 — a API do Insta (a página /insta): as conversas do Instagram e do WhatsApp para o gestor ver, e a ligação das contas.
   POST JSON com "acao", com o cabeçalho X-Play (como a tela do Play Space) e o login de gestor do Play Space.
   ===================================================================== */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/conversas-lib.php';
set_exception_handler(function (Throwable $e) { registrarErro('[insta] ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()); if ($e instanceof RuntimeException) falha($e->getMessage(), 400); falha('Algo deu errado. Tente de novo em instantes.', 500); });

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') falha('Use a tela do Insta.', 405);
$bruto = file_get_contents('php://input') ?: ''; if (strlen($bruto) > 200000) falha('Envio grande demais.', 413);
$in = json_decode($bruto, true); if (!is_array($in)) $in = [];
if (($_SERVER['HTTP_X_PLAY'] ?? '') !== '1') falha('Pedido sem o cabeçalho do app.', 403);
$acao = (string)($in['acao'] ?? '');
iniciarSessao();
$u = exigir(['gestor']);
if (!in_array($acao, ['ig_conectar'], true)) session_write_close();
cvTabelas(); $db = db();

$lista = function (string $onde = '', array $p = [], int $lim = 400) use ($db): array {
  $q = $db->prepare('SELECT * FROM cv_conversas' . ($onde !== '' ? ' WHERE ' . $onde : '') . ' ORDER BY COALESCE(ultima_em, criado_em) DESC, id DESC LIMIT ' . $lim); $q->execute($p);
  return array_map('cvPublica', $q->fetchAll());
};
$contas = fn() => ['insta' => igPublico(igCfg()), 'zap' => zapPublico(zapCfg())];

switch ($acao) {
  case 'dados': {   /* tudo de uma vez: as conversas, as contas e os avisos; o Instagram é conferido por trás (no máximo a cada minuto) */
    if (aCada('cv_ig_tela', 60)) cvSincronizarDepois();
    $q = $db->query('SELECT MAX(id) FROM cv_mensagens'); $ult = (int)$q->fetchColumn();
    responder(['conversas' => $lista(), 'contas' => $contas(), 'avisos' => !empty(cvCfg()['avisos']), 'marca' => $ult, 'agora' => agora(), 'eu' => (string)$u['nome']]);
  }
  case 'novidades': {   /* o que mudou desde a última olhada (a tela pergunta a cada 12 s) */
    if (aCada('cv_ig_tela', 60)) cvSincronizarDepois();
    $marca = (int)($in['marca'] ?? 0); $q = $db->query('SELECT MAX(id) FROM cv_mensagens'); $ult = (int)$q->fetchColumn();
    if ($ult <= $marca) responder(['marca' => $ult, 'conversas' => []]);
    responder(['marca' => $ult, 'conversas' => $lista('id IN (SELECT DISTINCT conversa_id FROM cv_mensagens WHERE id > ?)', [$marca], 200)]);
  }
  case 'conversa': {   /* as mensagens de uma conversa (as 400 mais novas); abrir marca como lida */
    $id = (int)($in['id'] ?? 0); $q = $db->prepare('SELECT * FROM cv_conversas WHERE id = ?'); $q->execute([$id]); $c = $q->fetch(); if (!$c) falha('Conversa não encontrada.', 404);
    $antes = (int)($in['antes'] ?? 0);
    $m = $db->prepare('SELECT id, de, texto, tipo, midia, quando FROM cv_mensagens WHERE conversa_id = ?' . ($antes ? ' AND id < ?' : '') . ' ORDER BY quando DESC, id DESC LIMIT 400'); $m->execute($antes ? [$id, $antes] : [$id]);
    $M = array_reverse(array_map(fn($x) => ['id' => (int)$x['id'], 'de' => (string)$x['de'], 'texto' => (string)$x['texto'], 'tipo' => (string)$x['tipo'], 'midia' => (string)$x['midia'], 'em' => (string)$x['quando']], $m->fetchAll()));
    if ((int)$c['nao_lidas'] > 0 && empty($in['naoMarcar'])) { $db->prepare('UPDATE cv_conversas SET nao_lidas = 0 WHERE id = ?')->execute([$id]); $c['nao_lidas'] = 0; }
    $ps = null; if ($c['ps_id'] !== null) { $k = $db->prepare('SELECT id, nome, resp FROM criancas WHERE id = ?'); $k->execute([(int)$c['ps_id']]); $x = $k->fetch(); if ($x) $ps = ['id' => (int)$x['id'], 'nome' => (string)$x['nome'], 'resp' => (string)$x['resp']]; }
    responder(['conversa' => array_merge(cvPublica($c), ['conta' => cvContaNome((string)$c['canal'], (string)$c['conta']), 'contaId' => (string)$c['conta'], 'cadastro' => $ps]), 'mensagens' => $M, 'mais' => count($M) >= 400]);
  }
  case 'lida': {   /* marcar como lida ou não lida */
    $id = (int)($in['id'] ?? 0); $db->prepare('UPDATE cv_conversas SET nao_lidas = ? WHERE id = ?')->execute([!empty($in['naoLida']) ? 1 : 0, $id]); responder();
  }
  case 'avisos': { $c = cvCfg(); $c['avisos'] = !empty($in['ligar']); gravarCfg('conversas', $c); responder(['avisos' => $c['avisos']]); }
  case 'aviso_teste': {   /* um aviso de teste nos celulares do gestor, como o de uma conversa nova */
    require_once __DIR__ . '/push-lib.php';
    $q = $db->prepare('SELECT * FROM push_inscricoes WHERE usuario_id = ? ORDER BY id'); $q->execute([(int)$u['id']]); $L = $q->fetchAll();
    if (!$L) falha('Este login ainda não tem os avisos ligados em nenhum aparelho. Toque em "Ativar avisos neste aparelho".');
    $r = pushMandar($L, fn($i) => ['t' => 'Instagram · conversa nova', 'b' => 'Teste: é assim que chega o aviso quando um cliente começa a conversar.', 'tag' => 'cv-teste', 'url' => './insta/']);
    responder(['enviados' => $r['ok'], 'erros' => $r['erro']]);
  }

  /* ---------- Instagram ---------- */
  case 'ig_salvar': {   /* o ID e a chave secreta do app da Meta (a chave só muda quando vem preenchida) */
    $c = igCfg(); $id = preg_replace('/\D/', '', (string)($in['appId'] ?? ''));
    if ($id === '' || strlen($id) < 8) falha('O ID do app do Instagram tem só números (Meta for Developers → o app → Instagram → Configuração da API).');
    $c['app_id'] = $id; $seg = trim((string)($in['segredo'] ?? '')); if ($seg !== '') { if (!preg_match('/^[a-f0-9]{20,64}$/i', $seg)) falha('A chave secreta do app tem letras de a a f e números (32 no total). Copie de novo.'); $c['segredo'] = $seg; }
    if ($c['segredo'] === '') falha('Falta a chave secreta do app.');
    igGravar($c); responder(['contas' => $contas()]);
  }
  case 'ig_conectar': {   /* o endereço do Instagram para liberar a conta (volta no insta-retorno.php) */
    $c = igCfg(); if ($c['app_id'] === '' || $c['segredo'] === '') falha('Primeiro o ID e a chave secreta do app (passo 2).');
    $e = bin2hex(random_bytes(16)); $_SESSION['ig_estado'] = $e; $_SESSION['ig_estado_em'] = time(); session_write_close();
    responder(['url' => igAutorizarUrl($c, $e)]);
  }
  case 'ig_sincronizar': { $r = igSincronizar((string)($in['conta'] ?? '')); responder(['resultado' => $r, 'contas' => $contas()]); }
  case 'ig_sair': {   /* desliga a conta (as conversas guardadas ficam) */
    $id = (string)($in['conta'] ?? ''); $c = igCfg(); $antes = count($c['contas']); $c['contas'] = array_values(array_filter($c['contas'], fn($x) => (string)$x['id'] !== $id));
    if (count($c['contas']) === $antes) falha('Conta não encontrada.', 404); igGravar($c); responder(['contas' => $contas()]);
  }

  /* ---------- WhatsApp ---------- */
  case 'zap_salvar': {   /* o servidor da Evolution API: o endereço e a chave (apikey) */
    $z = zapCfg(); $url = rtrim(trim((string)($in['url'] ?? '')), '/');
    if (!preg_match('~^https?://[^\s/]+(:\d+)?(/[^\s]*)?$~i', $url)) falha('O endereço do servidor começa com http:// ou https:// (ex.: https://whats.minhaloja.com.br).');
    $z['url'] = $url; $k = trim((string)($in['chave'] ?? '')); if ($k !== '') $z['chave'] = mb_substr($k, 0, 200);
    if ($z['chave'] === '') falha('Falta a chave (apikey) do servidor do WhatsApp.');
    [$st, $j, $raw] = http('GET', rtrim($url, '/') . '/instance/fetchInstances', ['apikey: ' . $z['chave']], null, 15);
    if ($st === 401 || $st === 403) falha('O servidor respondeu, mas recusou a chave (apikey). Confira a chave.');
    if ($st >= 400 && $st !== 404) falha('O servidor do WhatsApp respondeu com erro ' . $st . '. Confira o endereço.');
    zapGravar($z); responder(['contas' => $contas()]);
  }
  case 'zap_novo': { $c = zapNovo((string)($in['nome'] ?? '')); $r = zapQr((string)$c['id']); responder(['conta' => (string)$c['id'], 'qr' => $r, 'contas' => $contas()]); }
  case 'zap_qr': { $id = (string)($in['conta'] ?? ''); $r = zapQr($id); responder(['qr' => $r, 'contas' => $contas()]); }
  case 'zap_estado': { $id = (string)($in['conta'] ?? ''); $e = zapEstado($id); responder(['estado' => $e, 'contas' => $contas()]); }
  case 'zap_historico': { $id = (string)($in['conta'] ?? ''); $r = zapHistorico($id); responder(['resultado' => $r, 'contas' => $contas()]); }
  case 'zap_sair': { zapSair((string)($in['conta'] ?? '')); responder(['contas' => $contas()]); }
}
falha('Ação desconhecida.', 400);
