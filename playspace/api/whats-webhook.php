<?php
/* =====================================================================
   Play Space — webhook do WhatsApp: a Evolution API chama este endereço a cada mensagem recebida
   ou enviada no número da loja. Guarda na conversa do responsável certo (pelo telefone) e marca como não lida.
   Endereço: api/whats-webhook.php?t=TOKEN (o token nasce sozinho ao conectar). Sempre responde 200.
   ===================================================================== */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/whats-lib.php';
header('Content-Type: application/json; charset=utf-8');
function fim(array $r): void { echo json_encode($r, JSON_UNESCAPED_UNICODE); exit; }
set_exception_handler(function (Throwable $e) { registrarErro('webhook whats: ' . $e->getMessage()); fim(['ok' => false, 'erro' => $e->getMessage()]); });

$cfg = whatsCfg();
$t = (string)($_GET['t'] ?? '');
if ($cfg['webhook_token'] === '' || !hash_equals($cfg['webhook_token'], $t)) { http_response_code(403); fim(['ok' => false, 'erro' => 'token']); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fim(['ok' => true, 'webhook' => 'Play Space']);
$j = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($j)) fim(['ok' => true, 'ignorado' => 'sem json']);
$evento = mb_strtolower((string)($j['event'] ?? ''));

// o número conectou ou caiu
if (strpos($evento, 'connection') !== false) {
  $estado = (string)($j['data']['state'] ?? $j['data']['status'] ?? '');
  if ($estado === 'open') { $cfg['conectado_em'] = $cfg['conectado_em'] ?: agora(); gravarCfg('whats', $cfg); }
  elseif ($estado === 'close') { $cfg['conectado_em'] = null; gravarCfg('whats', $cfg); }
  fim(['ok' => true]);
}
if (!isset($j['data']) || !is_array($j['data']) || (strpos($evento, 'messages') === false && !isset($j['data']['key']))) fim(['ok' => true, 'ignorado' => $evento ?: 'evento desconhecido']);
$itens = isset($j['data']['key']) ? [$j['data']] : (isset($j['data'][0]) ? $j['data'] : []);
$m = $itens[0] ?? null; if (!$m) fim(['ok' => true, 'ignorado' => 'sem mensagem']);
$k = (array)($m['key'] ?? []); $jid = (string)($k['remoteJid'] ?? '');
if (strpos($jid, '@g.us') !== false || strpos($jid, 'status@') !== false) fim(['ok' => true, 'ignorado' => 'grupo']);
$deMim = !empty($k['fromMe']);
$alt = (string)($k['remoteJidAlt'] ?? $k['senderPn'] ?? '');
$tel = strpos($jid, '@lid') !== false ? (strpos($alt, '@s.whatsapp.net') !== false ? preg_replace('/\D/', '', explode('@', $alt)[0]) : '') : preg_replace('/\D/', '', explode('@', $jid)[0]);
if ($tel === '' || strlen($tel) < 10) fim(['ok' => true, 'ignorado' => 'número desconhecido']);
$msg = $m['message'] ?? [];
$texto = (string)($msg['conversation'] ?? $msg['extendedTextMessage']['text'] ?? $msg['imageMessage']['caption'] ?? $msg['videoMessage']['caption'] ?? $msg['buttonsResponseMessage']['selectedDisplayText'] ?? $msg['listResponseMessage']['title'] ?? '');
if ($texto === '' && $msg) $texto = isset($msg['audioMessage']) ? '[áudio]' : (isset($msg['imageMessage']) ? '[imagem]' : (isset($msg['documentMessage']) ? '[arquivo]' : (isset($msg['stickerMessage']) ? '[figurinha]' : '[mensagem]')));
if ($texto === '') fim(['ok' => true, 'ignorado' => 'vazia']);
$c = criancaPorTelefone($tel);
if (!$c) fim(['ok' => true, 'ignorado' => 'telefone sem cadastro']);
$db = db(); $waId = (string)($k['id'] ?? '');
// a própria mensagem do sistema volta pelo webhook como "fromMe": não entra duas vezes
if ($waId !== '') { $q = $db->prepare('SELECT 1 FROM mensagens WHERE wa_id = ?'); $q->execute([$waId]); if ($q->fetchColumn()) fim(['ok' => true, 'ignorado' => 'já guardada']); }
if ($deMim) { $q = $db->prepare("SELECT 1 FROM mensagens WHERE crianca_id = ? AND de = 'nos' AND texto = ? AND quando > ?"); $q->execute([$c['id'], $texto, date('Y-m-d H:i:s', time() - 300)]); if ($q->fetchColumn()) fim(['ok' => true, 'ignorado' => 'eco']); }
$db->prepare('INSERT INTO mensagens (crianca_id, de, texto, quando, por, auto, foto, lida, wa_id) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)')->execute([$c['id'], $deMim ? 'nos' : 'ela', $texto, agora(), $deMim ? 'WhatsApp' : '', '', $deMim ? 1 : 0, $waId]);
$cfg['ultima_recebida'] = agora(); gravarCfg('whats', $cfg);
fim(['ok' => true]);
