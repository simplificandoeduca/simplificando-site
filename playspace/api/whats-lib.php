<?php
/* =====================================================================
   Play Space — WhatsApp pela Evolution API (a mesma do CRM da Sophi).
   Conectar é escanear o QR Code em Ajustes; depois o sistema manda os avisos de entrada e saída
   (com a foto da chegada) e as mensagens da equipe, e recebe as respostas pelo webhook.
   ===================================================================== */
declare(strict_types=1);

const WHATS_PADRAO = ['url' => 'http://143.95.164.89', 'chave' => '', 'instancia' => 'playspace', 'numero' => '', 'nome' => '', 'conectado_em' => null, 'webhook_token' => '', 'ultima_recebida' => null];
function whatsCfg(): array { $c = cfg('whats', null); return (is_array($c) ? $c : []) + WHATS_PADRAO; }
function whatsToken(array &$c): string { if ($c['webhook_token'] === '') { $c['webhook_token'] = bin2hex(random_bytes(12)); gravarCfg('whats', $c); } return $c['webhook_token']; }
function whatsPronto(array $c): bool { return $c['url'] !== '' && $c['chave'] !== '' && $c['instancia'] !== '' && !empty($c['conectado_em']); }
function mascara(string $s): string { return $s === '' ? '' : (strlen($s) > 4 ? str_repeat('•', 6) . substr($s, -4) : '••••'); }
function whatsPublico(array $c): array { whatsToken($c); return ['url' => $c['url'], 'instancia' => $c['instancia'], 'chave' => mascara((string)$c['chave']), 'tem_chave' => $c['chave'] !== '', 'numero' => $c['numero'], 'nome' => $c['nome'], 'conectado_em' => $c['conectado_em'], 'ultima_recebida' => $c['ultima_recebida'], 'webhook' => urlBase() . '/api/whats-webhook.php?t=' . $c['webhook_token']]; }
function numeroLimpo(string $t): string { $d = preg_replace('/\D/', '', $t); if ($d !== '' && strlen($d) <= 11 && strpos(ltrim($t), '+') !== 0) $d = '55' . $d; return $d; }   // 1.16: número com "+" já traz o código do país

function http(string $metodo, string $url, array $cabecalhos = [], $corpo = null, int $tempo = 25): array {
  $ch = curl_init($url);
  $cab = array_merge(['Accept: application/json'], $cabecalhos);
  $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_TIMEOUT => $tempo, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true];
  if ($corpo !== null) { $op[CURLOPT_POSTFIELDS] = json_encode($corpo, JSON_UNESCAPED_UNICODE); $cab[] = 'Content-Type: application/json'; }
  $op[CURLOPT_HTTPHEADER] = $cab;
  curl_setopt_array($ch, $op);
  $res = curl_exec($ch);
  if ($res === false) { $e = curl_error($ch); curl_close($ch); throw new RuntimeException('Não consegui falar com a API do WhatsApp: ' . $e); }
  $st = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
  $j = json_decode((string)$res, true);
  return [$st, is_array($j) ? $j : null, (string)$res];
}
function msgErro(?array $j, string $raw, int $st): string {
  if ($j) foreach (['message', 'error', 'erro', 'response'] as $k) if (isset($j[$k])) { $v = $j[$k]; if (is_array($v)) $v = isset($v['message']) ? $v['message'] : json_encode($v, JSON_UNESCAPED_UNICODE); return (string)$v; }
  return 'erro ' . $st . ($raw !== '' ? ': ' . mb_substr(strip_tags($raw), 0, 160) : '');
}

/* ---------- Evolution API ---------- */
function evoBase(array $c): string { return rtrim($c['url'], '/'); }
function evoCab(array $c): array { return ['apikey: ' . $c['chave']]; }
function evoEstado(array $c): array {
  [$st, $j, $raw] = http('GET', evoBase($c) . '/instance/connectionState/' . rawurlencode($c['instancia']), evoCab($c));
  if ($st === 404) return ['estado' => 'inexistente'];
  if ($st === 401 || $st === 403) throw new RuntimeException('A Evolution API recusou a chave (apikey). Confira em Ajustes.');
  if ($st >= 400) throw new RuntimeException('Evolution API: ' . msgErro($j, $raw, $st));
  $s = (string)($j['instance']['state'] ?? $j['state'] ?? '');
  return ['estado' => $s === 'open' ? 'conectado' : ($s === 'connecting' ? 'aguardando' : 'desconectado')];
}
function evoInfo(array $c): array {
  [$st, $j] = http('GET', evoBase($c) . '/instance/fetchInstances?instanceName=' . rawurlencode($c['instancia']), evoCab($c));
  if ($st >= 400 || !is_array($j)) return [];
  $i = isset($j[0]) ? $j[0] : $j; $inst = isset($i['instance']) && is_array($i['instance']) ? $i['instance'] : $i;
  $dono = (string)($inst['owner'] ?? $inst['ownerJid'] ?? '');
  return ['numero' => preg_replace('/\D/', '', explode('@', $dono)[0]), 'nome' => (string)($inst['profileName'] ?? '')];
}
function evoCriar(array $c): void {
  [$st, $j, $raw] = http('POST', evoBase($c) . '/instance/create', evoCab($c), ['instanceName' => $c['instancia'], 'qrcode' => true, 'integration' => 'WHATSAPP-BAILEYS', 'groupsIgnore' => true, 'readMessages' => false, 'readStatus' => false, 'alwaysOnline' => false, 'rejectCall' => false]);
  if ($st >= 400 && !preg_match('/already|já existe|in use/i', (string)$raw)) throw new RuntimeException('Não deu para criar a instância na Evolution API: ' . msgErro($j, $raw, $st));
}
function evoQr(array $c): array {
  $url = evoBase($c) . '/instance/connect/' . rawurlencode($c['instancia']);
  [$st, $j, $raw] = http('GET', $url, evoCab($c));
  if ($st === 404) { evoCriar($c); [$st, $j, $raw] = http('GET', $url, evoCab($c)); }
  if ($st === 401 || $st === 403) throw new RuntimeException('A Evolution API recusou a chave (apikey). Confira em Ajustes.');
  if ($st >= 400) throw new RuntimeException('Evolution API: ' . msgErro($j, $raw, $st));
  $j = $j ?: [];
  if (($j['instance']['state'] ?? '') === 'open') return ['estado' => 'conectado', 'qr' => null, 'codigo' => ''];
  $img = (string)($j['base64'] ?? $j['qrcode']['base64'] ?? '');
  if ($img !== '' && strpos($img, 'data:') !== 0) $img = 'data:image/png;base64,' . $img;
  return ['estado' => 'aguardando', 'qr' => $img ?: null, 'codigo' => (string)($j['pairingCode'] ?? '')];
}
function evoSair(array $c): void {
  [$st, $j, $raw] = http('DELETE', evoBase($c) . '/instance/logout/' . rawurlencode($c['instancia']), evoCab($c));
  if ($st >= 400 && $st !== 404) throw new RuntimeException('Evolution API: ' . msgErro($j, $raw, $st));
}
// o webhook: a Evolution chama o nosso whats-webhook.php a cada mensagem (formato da v2; cai para o da v1 se ela recusar)
function evoWebhook(array $c): void {
  $alvo = urlBase() . '/api/whats-webhook.php?t=' . whatsToken($c);
  [$st, $j, $raw] = http('POST', evoBase($c) . '/webhook/set/' . rawurlencode($c['instancia']), evoCab($c), ['webhook' => ['enabled' => true, 'url' => $alvo, 'webhookByEvents' => false, 'webhookBase64' => false, 'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE']]]);
  if ($st >= 400) [$st, $j, $raw] = http('POST', evoBase($c) . '/webhook/set/' . rawurlencode($c['instancia']), evoCab($c), ['url' => $alvo, 'enabled' => true, 'webhook_by_events' => false, 'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE']]);
  if ($st >= 400) throw new RuntimeException('Não consegui ligar o webhook: ' . msgErro($j, $raw, $st));
}
function evoEnviar(array $c, string $numero, string $texto): string {
  $url = evoBase($c) . '/message/sendText/' . rawurlencode($c['instancia']);
  [$st, $j, $raw] = http('POST', $url, evoCab($c), ['number' => $numero, 'text' => $texto]);
  if ($st >= 400 && preg_match('/textMessage/i', (string)$raw)) [$st, $j, $raw] = http('POST', $url, evoCab($c), ['number' => $numero, 'textMessage' => ['text' => $texto]]);
  if ($st >= 400) throw new RuntimeException('Não enviou: ' . msgErro($j, $raw, $st));
  return (string)(((array)($j['key'] ?? []))['id'] ?? '');
}
// a foto (data URL ou base64 puro) com a legenda
function evoEnviarImagem(array $c, string $numero, string $foto, string $legenda): string {
  $b64 = $foto; $mime = 'image/jpeg';
  if (preg_match('#^data:(image/[a-z]+);base64,(.+)$#s', $foto, $m)) { $mime = $m[1]; $b64 = $m[2]; }
  $url = evoBase($c) . '/message/sendMedia/' . rawurlencode($c['instancia']);
  $corpo = ['number' => $numero, 'mediatype' => 'image', 'mimetype' => $mime, 'caption' => $legenda, 'media' => $b64, 'fileName' => 'chegada.jpg'];
  [$st, $j, $raw] = http('POST', $url, evoCab($c), $corpo, 60);
  if ($st >= 400) [$st, $j, $raw] = http('POST', $url, evoCab($c), ['number' => $numero, 'mediaMessage' => ['mediatype' => 'image', 'caption' => $legenda, 'media' => $b64, 'fileName' => 'chegada.jpg']], 60);
  if ($st >= 400) throw new RuntimeException('Não enviou a foto: ' . msgErro($j, $raw, $st));
  return (string)(((array)($j['key'] ?? []))['id'] ?? '');
}
// acha a criança pelo telefone do responsável (compara os últimos 8 dígitos, que não mudam com DDD ou o 9)
function criancaPorTelefone(string $tel): ?array {
  $d = preg_replace('/\D/', '', $tel); if (strlen($d) < 8) return null;
  $fim = substr($d, -8);
  $todas = db()->query("SELECT * FROM criancas WHERE ativo = 1 AND (tel <> '' OR tel2 <> '')")->fetchAll();
  foreach (['tel', 'tel2'] as $campo) foreach ($todas as $c) {   // primeiro o WhatsApp principal de todo mundo; depois o segundo telefone
    $t = preg_replace('/\D/', '', (string)($c[$campo] ?? '')); if ($t !== '' && substr($t, -8) === $fim) return $c;
  }
  return null;
}
