<?php
// "Esqueci a senha" com o e-mail do Simplificando (design próprio), sem depender dos modelos do Firebase.
// 1) pede ao Firebase só o link seguro de redefinição (com a chave guardada FORA do public_html);
// 2) monta o e-mail com a logo e o botão e envia pelo servidor da HostGator.
// Não revela se o e-mail tem conta. No máximo 3 pedidos por e-mail e 10 por aparelho a cada hora.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function anota($passo, $info = '') {
  $f = dirname(__DIR__) . '/.simplificando-recuperar-log.json';
  $L = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
  $L[] = [date('d/m H:i:s'), $passo, substr((string)$info, 0, 200)]; $L = array_slice($L, -15);
  @file_put_contents($f, json_encode($L, JSON_UNESCAPED_UNICODE));
}
$esconde = function ($e) { $p = explode('@', $e); return substr($p[0], 0, 2) . '***@' . ($p[1] ?? ''); };
function sai($a, $c = 200) { http_response_code($c); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
$casa = dirname(__DIR__);
$chave = $casa . '/simplificando-chave.json';
// teste: simplificandoeduca.com/recuperar.php?teste=1 mostra em que passo está (não envia e-mail)
if (isset($_GET['teste'])) {
  $d = ['arquivo_no_site' => true, 'pasta_da_chave' => $casa, 'chave_encontrada' => is_file($chave)];
  $outras = array_values(array_filter(scandir($casa) ?: [], function ($f) { return substr($f, -5) === '.json' && $f[0] !== '.'; }));
  if (!$d['chave_encontrada']) $d['arquivos_json_na_pasta'] = $outras;
  $k = $d['chave_encontrada'] ? json_decode(file_get_contents($chave), true) : null;
  $d['chave_valida'] = !!($k && !empty($k['private_key']) && !empty($k['client_email']));
  $d['curl'] = function_exists('curl_init'); $d['envio_de_email'] = function_exists('mail');
  if ($d['chave_valida'] && $d['curl']) {
    $b = function ($x) { return rtrim(strtr(base64_encode($x), '+/', '-_'), '='); }; $t = time();
    $j = $b(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b(json_encode(['iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/identitytoolkit https://www.googleapis.com/auth/cloud-platform', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $t, 'exp' => $t + 600]));
    $ok = openssl_sign($j, $sg, $k['private_key'], 'sha256WithRSAEncryption');
    $c = curl_init('https://oauth2.googleapis.com/token'); curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $j . '.' . $b($sg)])]);
    $r = json_decode((string)curl_exec($c), true); curl_close($c);
    $d['google_aceitou_a_chave'] = $ok && !empty($r['access_token']); if (!$d['google_aceitou_a_chave']) $d['erro_google'] = $r['error_description'] ?? ($r['error'] ?? 'sem resposta');
  }
  $lf = $casa . '/.simplificando-recuperar-log.json';
  $d['ultimos_pedidos'] = is_file($lf) ? (json_decode((string)file_get_contents($lf), true) ?: []) : [];
  sai($d);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sai(['ok' => false, 'erro' => 'Pedido inválido.'], 405);
if (!is_file($chave)) { anota('sem chave'); sai(['ok' => false, 'sem' => 'chave']); } // o app usa o e-mail padrão do Firebase
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$email = strtolower(trim((string)($in['email'] ?? '')));
$aluno = !empty($in['aluno']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) sai(['ok' => false, 'erro' => 'Confira o e-mail. Ex.: voce@email.com'], 400);

// limite de pedidos
$lf = $casa . '/.simplificando-recuperar.json';
$fp = fopen($lf, 'c+'); if ($fp) flock($fp, LOCK_EX);
$L = $fp ? (json_decode(stream_get_contents($fp), true) ?: []) : [];
$agora = time(); $L = array_filter($L, function ($v) use ($agora) { return $agora - $v[1] < 3600; });
$ke = 'e' . sha1($email); $ki = 'i' . sha1($_SERVER['REMOTE_ADDR'] ?? '');
$ne = count(array_filter($L, function ($v) use ($ke) { return $v[0] === $ke; }));
$ni = count(array_filter($L, function ($v) use ($ki) { return $v[0] === $ki; }));
$bloq = $ne >= 3 || $ni >= 10;
if (!$bloq) { $L[] = [$ke, $agora]; $L[] = [$ki, $agora]; }
if ($fp) { ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode(array_values($L))); flock($fp, LOCK_UN); fclose($fp); }
if ($bloq) { anota('bloqueado pelo limite', $esconde($email)); } if ($bloq) sai(['ok' => false, 'erro' => 'Muitos pedidos. Espere um pouco e tente de novo.'], 429);

function post($url, $body, $hdr = []) {
  $c = curl_init($url);
  curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $hdr]);
  $r = curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
  return [$st, json_decode((string)$r, true)];
}
$b64 = function ($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); };
$k = json_decode(file_get_contents($chave), true);
if (!$k || empty($k['private_key']) || empty($k['client_email']) || empty($k['project_id'])) sai(['ok' => false, 'sem' => 'chave', 'motivo' => 'chave inválida']);
// token de acesso da conta de serviço
$jwt = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode(['iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/identitytoolkit https://www.googleapis.com/auth/cloud-platform', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $agora, 'exp' => $agora + 3000]));
if (!openssl_sign($jwt, $sig, $k['private_key'], 'sha256WithRSAEncryption')) sai(['ok' => false, 'sem' => 'chave']);
list($st, $tk) = post('https://oauth2.googleapis.com/token', http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt . '.' . $b64($sig)]));
if ($st != 200 || empty($tk['access_token'])) { anota('google recusou a chave', $st); } if ($st != 200 || empty($tk['access_token'])) sai(['ok' => false, 'sem' => 'chave', 'motivo' => 'google recusou a chave']);
// link seguro de redefinição (o Firebase não manda e-mail: só devolve o link)
$host = preg_replace('/[^a-z0-9.\-]/', '', strtolower($_SERVER['HTTP_HOST'] ?? 'simplificandoeduca.com'));
list($st, $r) = post('https://identitytoolkit.googleapis.com/v1/projects/' . rawurlencode($k['project_id']) . '/accounts:sendOobCode',
  json_encode(['requestType' => 'PASSWORD_RESET', 'email' => $email, 'returnOobLink' => true]), ['Content-Type: application/json', 'Authorization: Bearer ' . $tk['access_token']]);
if ($st != 200 || empty($r['oobLink'])) { anota('firebase não deu o link', $esconde($email) . ' · ' . $st . ' · ' . (($r['error']['message'] ?? '') ?: 'sem detalhe')); sai(['ok' => true]); } // e-mail sem conta: responde igual, sem enviar nada
parse_str((string)parse_url($r['oobLink'], PHP_URL_QUERY), $q);
if (empty($q['oobCode'])) sai(['ok' => true]);
$link = 'https://' . $host . '/treino/?mode=resetPassword&oobCode=' . rawurlencode($q['oobCode']) . ($aluno ? '&tipo=aluno' : '');
// e-mail com o design do Simplificando
$html = <<<'HTML'
<div style="margin:0;padding:32px 16px;background:#E9E8E1;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;margin:0 auto;background:#0F1712;border-radius:24px;overflow:hidden;color:#F6F5EF">
<tr><td style="height:6px;background:linear-gradient(90deg,#FF4646,#FF9A3C,#C6F04A,#A98BFF);font-size:0">&nbsp;</td></tr>
<tr><td style="padding:30px 32px 0"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td><img src="https://simplificandoeduca.com/treino/icon-192.png" width="76" height="76" alt="Simplificando" style="border-radius:20px;display:block"></td><td style="padding-left:16px;font-size:28px;font-weight:bold;letter-spacing:-0.5px;color:#F6F5EF">simplificando<span style="color:#FF4646">.</span></td></tr></table></td></tr>
<tr><td style="padding:28px 32px 0"><p style="margin:0;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#C6F04A;font-weight:bold">Redefinir senha</p><h1 style="margin:8px 0 0;font-size:30px;line-height:1.15;letter-spacing:-0.8px">Bora voltar<br>pro treino<span style="color:#FF4646">.</span></h1></td></tr>
<tr><td style="padding:16px 32px 0;font-size:15px;line-height:1.55;color:#C9CDC6">Pediram uma senha nova para a conta <b style="color:#F6F5EF">%EMAIL%</b>. Toque no botão e escolha a sua.</td></tr>
<tr><td style="padding:28px 32px"><a href="%LINK%" style="display:block;padding:17px 0;background:#FF4646;color:#ffffff;text-align:center;font-size:16px;font-weight:bold;text-decoration:none;border-radius:14px">Redefinir minha senha &rarr;</a></td></tr>
<tr><td style="padding:0 32px 30px;font-size:12.5px;line-height:1.5;color:#86928A">O link vale por 1 hora. Não pediu? Pode ignorar: nada muda na sua conta.</td></tr>
</table></div>
HTML;
$html = str_replace(['%EMAIL%', '%LINK%'], [htmlspecialchars($email, ENT_QUOTES, 'UTF-8'), htmlspecialchars($link, ENT_QUOTES, 'UTF-8')], $html);
$html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0">' . $html . '</body></html>';
$dom = preg_replace('/^www\./', '', $host);
$assunto = '=?UTF-8?B?' . base64_encode('Bora voltar pro treino: crie sua senha nova') . '?=';
$cab = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: =?UTF-8?B?" . base64_encode('Simplificando') . "?= <nao-responda@$dom>\r\nReply-To: nao-responda@$dom\r\n";
$ok = @mail($email, $assunto, $html, $cab, '-fnao-responda@' . $dom);
anota($ok ? 'e-mail entregue ao servidor' : 'servidor recusou o envio', $esconde($email) . ' · de nao-responda@' . $dom);
sai($ok ? ['ok' => true] : ['ok' => false, 'sem' => 'email']);
