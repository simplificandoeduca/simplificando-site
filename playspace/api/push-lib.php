<?php
// 1.32: os avisos no celular (Web Push), sem biblioteca de fora. O gestor liga os avisos no aparelho dele (um toque, e o navegador pergunta
// se pode avisar); o navegador devolve uma "inscrição" (o endereço do serviço de avisos do Google, da Apple, da Mozilla ou da Microsoft e
// duas chaves do aparelho). A cada pedido de autorização, o servidor cifra o aviso para aquele aparelho (RFC 8291, aes128gcm) e entrega ao
// serviço de avisos, assinado com a chave da loja (VAPID, RFC 8292, curva P-256). Só o servidor guarda a chave privada da loja.

function pushB64u(string $b): string { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }
function pushDeB64u(string $s): string { $s = strtr(trim($s), '-_', '+/'); $r = base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true); return $r === false ? '' : $r; }
// a chave pública crua (65 bytes: 0x04, x, y) num PEM que o openssl lê
function pushPemPublica(string $ponto): string {
  $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $ponto;
  return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}
function pushPonto($chave): string { $d = openssl_pkey_get_details($chave); return "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT); }
// o PHP da hospedagem tem o que os avisos precisam? (PHP 7.3 ou mais novo, com openssl e curl)
function pushServidorPronto(): bool { return function_exists('openssl_pkey_derive') && function_exists('hash_hkdf') && function_exists('curl_init') && in_array('aes-128-gcm', openssl_get_cipher_methods(), true); }
function pushNovaChave() {
  $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
  if (!$k) throw new RuntimeException('O servidor não conseguiu criar a chave dos avisos (openssl).');
  return $k;
}
// a chave da loja: criada uma vez e guardada (config push_vapid). Trocar a chave desliga os avisos de todos os aparelhos
function pushChaves(): array {
  $c = cfg('push_vapid', null);
  if (is_array($c) && !empty($c['priv']) && !empty($c['pub'])) return $c;
  $k = pushNovaChave(); openssl_pkey_export($k, $priv);
  $c = ['priv' => $priv, 'pub' => pushB64u(pushPonto($k)), 'em' => agora()]; gravarCfg('push_vapid', $c); return $c;
}
// a assinatura ES256: o openssl devolve DER (SEQUENCE de dois INTEGER); o JWT quer r e s juntos, com 32 bytes cada
function pushDerParaRs(string $der): string {
  $p = 2; if (ord($der[1]) & 0x80) $p += ord($der[1]) & 0x7f;
  $lr = ord($der[$p + 1]); $r = substr($der, $p + 2, $lr); $p += 2 + $lr;
  $ls = ord($der[$p + 1]); $s = substr($der, $p + 2, $ls);
  return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
}
// quem manda os avisos (o serviço de avisos pede um contato): o endereço do próprio sistema
function pushContato(): string { $h = preg_replace('/[^a-z0-9.\-:]/i', '', (string)($_SERVER['HTTP_HOST'] ?? '')); return 'https://' . ($h !== '' ? $h : 'localhost') . '/'; }
function pushJwt(string $aud, array $c): string {
  $h = pushB64u('{"typ":"JWT","alg":"ES256"}');
  $p = pushB64u((string)json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => pushContato()], JSON_UNESCAPED_SLASHES));
  if (!openssl_sign($h . '.' . $p, $der, $c['priv'], OPENSSL_ALGO_SHA256)) throw new RuntimeException('Não deu para assinar o aviso.');
  return $h . '.' . $p . '.' . pushB64u(pushDerParaRs($der));
}
// o aviso cifrado para um aparelho (RFC 8291 com aes128gcm, um registro só): sal, tamanho do registro, a chave pública de uma vez só, a cifra
function pushCifrar(string $texto, string $p256dh, string $auth): string {
  $ua = pushDeB64u($p256dh); $au = pushDeB64u($auth);
  if (strlen($ua) !== 65 || $ua[0] !== "\x04" || strlen($au) !== 16) throw new InvalidArgumentException('A inscrição do aparelho está incompleta.');
  $eph = pushNovaChave(); $as = pushPonto($eph);
  $seg = openssl_pkey_derive(openssl_pkey_get_public(pushPemPublica($ua)), $eph, 32);
  if ($seg === false || strlen($seg) !== 32) throw new RuntimeException('A cifra do aviso falhou.');
  $ikm = hash_hkdf('sha256', $seg, 32, "WebPush: info\0" . $ua . $as, $au);
  $sal = random_bytes(16);
  $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $sal);
  $non = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $sal);
  $cif = openssl_encrypt($texto . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $non, $tag);
  if ($cif === false) throw new RuntimeException('A cifra do aviso falhou.');
  return $sal . pack('N', 4096) . chr(65) . $as . $cif . $tag;
}
// só os serviços de avisos dos navegadores (e, no computador de teste, o serviço de mentira dele): o servidor não manda nada para outro lugar
function pushHostPermitido(string $endpoint): bool {
  $u = parse_url($endpoint); if (!is_array($u) || empty($u['host'])) return false; $h = strtolower((string)$u['host']);
  if (($u['scheme'] ?? '') === 'https' && preg_match('/(^|\.)(fcm\.googleapis\.com|android\.googleapis\.com|push\.services\.mozilla\.com|notify\.windows\.com|push\.apple\.com)$/', $h)) return true;
  $aqui = strtolower((string)($_SERVER['SERVER_NAME'] ?? ''));
  return ($u['scheme'] ?? '') === 'http' && in_array($h, ['127.0.0.1', 'localhost'], true) && in_array($aqui, ['127.0.0.1', 'localhost'], true);
}
// entrega um aviso a uma inscrição: devolve o código HTTP do serviço de avisos (0 = não conectou)
function pushEnviar(array $insc, array $msg): int {
  if (!pushHostPermitido((string)$insc['endpoint'])) return 0;
  $c = pushChaves(); $u = parse_url((string)$insc['endpoint']); $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
  $corpo = pushCifrar((string)json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string)$insc['p256dh'], (string)$insc['auth']);
  $ch = curl_init((string)$insc['endpoint']);
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corpo, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => ['Content-Type: application/octet-stream', 'Content-Encoding: aes128gcm', 'TTL: 43200', 'Urgency: high', 'Authorization: vapid t=' . pushJwt($aud, $c) . ', k=' . $c['pub']]]);
  curl_exec($ch); $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return $st;
}
// entrega a várias inscrições: a que o serviço diz que não existe mais (404/410) sai; a que deu certo fica marcada
function pushMandar(array $L, callable $msgDe): array {
  $db = db(); $o = ['ok' => 0, 'erro' => 0, 'saiu' => 0];
  foreach ($L as $i) {
    try { $st = pushEnviar($i, $msgDe($i)); } catch (Throwable $e) { $st = 0; }
    if ($st >= 200 && $st < 300) { $o['ok']++; $db->prepare('UPDATE push_inscricoes SET ultimo_ok = ?, falhas = 0 WHERE id = ?')->execute([agora(), (int)$i['id']]); }
    elseif ($st === 404 || $st === 410) { $o['saiu']++; $db->prepare('DELETE FROM push_inscricoes WHERE id = ?')->execute([(int)$i['id']]); }
    else { $o['erro']++; $db->prepare('UPDATE push_inscricoes SET falhas = falhas + 1 WHERE id = ?')->execute([(int)$i['id']]); }
  }
  return $o;
}
// o aviso de um pedido novo: no celular de quem decide aquele tipo de pedido (menos quem pediu)
function pushPedido(array $p): array {
  $L = array_values(array_filter(db()->query('SELECT i.*, u.papel FROM push_inscricoes i JOIN usuarios u ON u.id = i.usuario_id AND u.ativo = 1 ORDER BY i.id')->fetchAll(),
    fn($i) => (int)$i['usuario_id'] !== (int)$p['pedido_por'] && podeDecidir(['id' => (int)$i['usuario_id'], 'papel' => (string)$i['papel']], (string)$p['tipo'])));
  if (!$L) return ['ok' => 0, 'erro' => 0, 'saiu' => 0];
  $titulo = ['desconto' => 'Pedido de desconto', 'cancelar_venda' => 'Pedido para cancelar uma venda', 'nf' => 'Pedido de nota fiscal', 'valor' => 'Pedido para alterar um valor'][(string)$p['tipo']] ?? 'Pedido de autorização';
  $uni = ''; if (($p['unidade_id'] ?? null) !== null) foreach (unidades() as $x) if ((int)$x['id'] === (int)$p['unidade_id']) $uni = (string)$x['nome'];
  $quem = trim((string)$p['pedido_nome']); $corpo = mb_substr(($quem !== '' ? $quem . ($uni !== '' ? ' · ' . $uni : '') . ': ' : ($uni !== '' ? $uni . ': ' : '')) . trim((string)$p['texto']) . (trim((string)($p['motivo'] ?? '')) !== '' ? ' · Motivo: ' . trim((string)$p['motivo']) : ''), 0, 200);   // 1.54: o motivo vai junto
  return pushMandar($L, fn($i) => ['t' => $titulo, 'b' => $corpo, 'tag' => 'pedido-' . (int)$p['id'], 'url' => in_array((string)$i['papel'], ['gestor', 'supervisor'], true) ? './#aprovar' : './']);   // 1.33: a supervisora também tem as Aprovações
}
// 1.57: o aviso da troca da forma de pagamento feita pela equipe: no celular do gestor
function pushTroca(int $id): array {
  $q = db()->prepare('SELECT * FROM trocas_pag WHERE id = ?'); $q->execute([$id]); $t = $q->fetch(); if (!$t) return ['ok' => 0, 'erro' => 0, 'saiu' => 0];
  $L = array_values(array_filter(db()->query('SELECT i.*, u.papel FROM push_inscricoes i JOIN usuarios u ON u.id = i.usuario_id AND u.ativo = 1 ORDER BY i.id')->fetchAll(), fn($i) => (string)$i['papel'] === 'gestor' && (int)$i['usuario_id'] !== (int)$t['por']));
  if (!$L) return ['ok' => 0, 'erro' => 0, 'saiu' => 0];
  $corpo = mb_substr((trim((string)($t['atendente'] ?? '')) !== '' ? trim((string)$t['atendente']) . ' (' . trim((string)$t['por_nome']) . ')' : trim((string)$t['por_nome'])) . ': ' . trim((string)($t['resp'] !== '' ? $t['resp'] : $t['nome'])) . ' · ' . (FORMAS_PAGAMENTO[$t['de']] ?? $t['de']) . ' ' . dinheiro((float)$t['valor']) . ' → ' . (($pt = json_decode((string)($t['partes'] ?? ''), true)) ? implode(' + ', array_map(fn($x) => (FORMAS_PAGAMENTO[$x['f']] ?? $x['f']) . ' ' . dinheiro((float)$x['v']), $pt)) : (FORMAS_PAGAMENTO[$t['para']] ?? $t['para'])), 0, 200);   /* 1.71: a forma dividida */
  return pushMandar($L, fn($i) => ['t' => ($t['origem'] ?? '') === 'caixa' ? 'Quebra de caixa' : 'Troca de pagamento', 'b' => $corpo, 'tag' => 'troca-' . $id, 'url' => './#aprovar']);   /* 3.10 */
}
/* 1.81: a meta do dia batida, no celular de cada gestor */
function pushMeta(array $b): array {   /* 1.81; 1.87: a meta do dia ou a do mês de uma unidade, para cada gestor */
  $L = array_values(array_filter(db()->query('SELECT i.*, u.papel FROM push_inscricoes i JOIN usuarios u ON u.id = i.usuario_id AND u.ativo = 1 ORDER BY i.id')->fetchAll(), fn($i) => (string)$i['papel'] === 'gestor'));
  if (!$L) return ['ok' => 0, 'erro' => 0, 'saiu' => 0];
  $onde = trim((string)($b['nome'] ?? '')); $pre = $onde !== '' ? $onde . ': ' : ''; $em = (string)$b['em'];
  if (($b['tipo'] ?? 'dia') === 'mes') {
    $mn = META_MESES[(int)substr((string)$b['mes'], 5, 2) - 1] ?? '';
    $t = 'Meta do mês batida! 🏆'; $tag = 'meta-mes-' . (string)$b['mes'] . '-' . (int)($b['uni'] ?? 0);
    $corpo = $pre . 'meta de ' . $mn . ' (' . dinheiro((float)$b['valor']) . ') batida em ' . substr($em, 8, 2) . '/' . substr($em, 5, 2) . ' às ' . substr($em, 11, 5) . ' · faturado no mês ' . dinheiro((float)($b['faturado'] ?? 0)) . '. Parabéns, equipe!';
  } else {
    $t = 'Meta do dia batida! 🎉'; $tag = 'meta-' . (string)$b['dia'] . '-' . (int)($b['uni'] ?? 0);
    $corpo = $pre . 'meta de ' . dinheiro((float)$b['valor']) . ' batida às ' . substr($em, 11, 5) . ' · faturado hoje ' . dinheiro((float)($b['faturado'] ?? 0)) . '. Parabéns, equipe!';
  }
  return pushMandar($L, fn($i) => ['t' => $t, 'b' => $corpo, 'tag' => $tag, 'url' => './#painel']);
}
// depois de responder à tela: quem pediu não espera os avisos saírem (o PHP fecha a conexão e continua). A sessão é solta antes
function pushDepois(callable $f): void {
  register_shutdown_function(function () use ($f) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request(); elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
    ignore_user_abort(true);
    try { $f(); } catch (Throwable $e) { error_log('Play Space, aviso no celular: ' . $e->getMessage()); }
  });
}
