<?php
// Envio de e-mail do servidor (o mesmo caminho do "esqueci a senha"): Brevo, se a chave estiver em ~/simplificando-brevo.txt;
// senão, a conta nao-responda@ (SMTP, ~/simplificando-email.txt ou .json); por último, o envio interno do servidor.
// Robô de testes: com SIMP_TESTE_PUSH, só anota o envio no arquivo (não manda nada).
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(404); exit; }

function em_brevoCfg($casa) {
  $t = $casa . '/simplificando-brevo.txt'; if (!is_file($t)) return null;
  $L = array_map('trim', preg_split('/\r\n|\r|\n/', preg_replace('/^\xEF\xBB\xBF/', '', (string)file_get_contents($t))));
  return !empty($L[0]) && filter_var($L[1] ?? '', FILTER_VALIDATE_EMAIL) ? ['chave' => $L[0], 'remetente' => strtolower($L[1])] : null;
}
function em_smtpCfg($casa) {
  $t = $casa . '/simplificando-email.txt';
  if (is_file($t)) { $L = array_map(function ($x) { return trim(preg_replace('/^\xEF\xBB\xBF/', '', $x)); }, preg_split('/\r\n|\r|\n/', (string)file_get_contents($t)));
    return ($L[0] ?? '') && ($L[1] ?? '') !== '' ? ['usuario' => $L[0], 'senha' => $L[1]] : null; }
  $j = $casa . '/simplificando-email.json';
  if (is_file($j)) { $c = json_decode((string)file_get_contents($j), true); return is_array($c) && !empty($c['usuario']) && !empty($c['senha']) ? $c : null; }
  return null;
}
function em_brevo($b, $para, $assunto, $html) {
  $c = curl_init('https://api.brevo.com/v3/smtp/email');
  curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $b['chave']],
    CURLOPT_POSTFIELDS => json_encode(['sender' => ['name' => 'Simplificando', 'email' => $b['remetente']], 'to' => [['email' => $para]], 'subject' => $assunto, 'htmlContent' => $html], JSON_UNESCAPED_UNICODE)]);
  curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
  return $st >= 200 && $st < 300;
}
function em_smtp($cfg, $para, $assunto, $html) {
  $user = strtolower(trim((string)$cfg['usuario'])); $senha = (string)$cfg['senha']; $dom = substr(strrchr($user, '@'), 1);
  foreach ([['ssl://mail.' . $dom, 465, false], ['tcp://localhost', 587, true], ['ssl://localhost', 465, false]] as $a) {
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
    $f = @stream_socket_client($a[0] . ':' . $a[1], $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx); if (!$f) continue;
    stream_set_timeout($f, 20);
    $le = function () use ($f) { $t = ''; while (($l = fgets($f, 600)) !== false) { $t .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return trim($t); };
    $diz = function ($c) use ($f, $le) { fwrite($f, $c . "\r\n"); return $le(); };
    $ok = function ($r, $cod) { return strpos($r, (string)$cod) === 0; };
    $le(); $diz('EHLO ' . $dom);
    if ($a[2]) { $r = $diz('STARTTLS'); if (!$ok($r, 220) || !@stream_socket_enable_crypto($f, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($f); continue; } $diz('EHLO ' . $dom); }
    $r = $diz('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $senha));
    if (!$ok($r, 235)) { $diz('AUTH LOGIN'); $diz(base64_encode($user)); $r = $diz(base64_encode($senha)); }
    if (!$ok($r, 235)) { fclose($f); continue; }
    if (!$ok($diz('MAIL FROM:<' . $user . '>'), 250)) { fclose($f); return false; }
    $r = $diz('RCPT TO:<' . $para . '>'); if (!$ok($r, 250) && !$ok($r, 251)) { fclose($f); return false; }
    if (!$ok($diz('DATA'), 354)) { fclose($f); return false; }
    $msg = "From: =?UTF-8?B?" . base64_encode('Simplificando') . "?= <$user>\r\nTo: <$para>\r\nSubject: =?UTF-8?B?" . base64_encode($assunto) . "?=\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . "@$dom>\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html));
    $r = $diz($msg . "\r\n."); $diz('QUIT'); fclose($f);
    return $ok($r, 250);
  }
  return false;
}
// manda um e-mail (assunto em texto normal, HTML pronto); devolve por onde saiu ('' = não saiu)
function em_envia($casa, $para, $assunto, $html, $extra = []) {
  if ($f = getenv('SIMP_TESTE_PUSH')) { @file_put_contents($f, json_encode(['email' => $para, 'assunto' => $assunto, 'html' => strlen($html)] + $extra, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX); return 'teste'; }
  if (($b = em_brevoCfg($casa)) && em_brevo($b, $para, $assunto, $html)) return 'brevo';
  if (($s = em_smtpCfg($casa)) && em_smtp($s, $para, $assunto, $html)) return 'smtp';
  $dom = 'simplificandoeduca.com'; $rem = 'nao-responda@' . $dom;
  $cab = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: =?UTF-8?B?" . base64_encode('Simplificando') . "?= <$rem>\r\nReply-To: $rem\r\n";
  return @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $html, $cab, '-f' . $rem) ? 'interno' : '';
}
