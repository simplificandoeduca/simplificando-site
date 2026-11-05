<?php
// Confere se quem chamou é o dono do painel (a conta que está em sistema/dono no Firebase).
// O painel manda o token de login do Firebase no cabeçalho "Authorization: Bearer ...".
// Não guarda senha nem chave: usa a configuração pública do app (treino/firebase-config.js).
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(404); exit; }

function dono_http($url, $post = null) {
  if (!function_exists('curl_init')) return [0, null];
  $c = curl_init($url);
  $op = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => 'simplificando'];
  if ($post !== null) { $op[CURLOPT_POST] = true; $op[CURLOPT_POSTFIELDS] = json_encode($post); $op[CURLOPT_HTTPHEADER] = ['Content-Type: application/json']; }
  curl_setopt_array($c, $op);
  $r = curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
  return [$st, $r === false ? null : json_decode($r, true)];
}
function dono_cfg() {
  $js = @file_get_contents(__DIR__ . '/treino/firebase-config.js');
  $k = preg_match('/apiKey:\s*"([^"]+)"/', (string)$js, $a) ? $a[1] : '';
  $p = preg_match('/projectId:\s*"([^"]+)"/', (string)$js, $b) ? $b[1] : '';
  return [$k, $p];
}
function dono_token() {
  $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
  if (!$h && function_exists('getallheaders')) { foreach (getallheaders() as $n => $v) if (strtolower($n) === 'authorization') $h = $v; }
  if (preg_match('/Bearer\s+(\S+)/', $h, $m)) return $m[1];
  return isset($_POST['token']) ? (string)$_POST['token'] : '';
}
// devolve o uid do dono, ou null se o token não for do dono
function dono_uid() {
  [$key, $pid] = dono_cfg(); $tok = dono_token();
  if (!$key || !$pid || strlen($tok) < 100) return null;
  [$st, $j] = dono_http('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($key), ['idToken' => $tok]);
  $uid = ($st == 200 && isset($j['users'][0]['localId'])) ? $j['users'][0]['localId'] : '';
  if (!$uid) return null;
  [$st2, $d] = dono_http('https://firestore.googleapis.com/v1/projects/' . rawurlencode($pid) . '/databases/(default)/documents/sistema/dono?key=' . urlencode($key));
  $dono = ($st2 == 200 && isset($d['fields']['uid']['stringValue'])) ? $d['fields']['uid']['stringValue'] : '';
  return ($dono && hash_equals($dono, $uid)) ? $uid : null;
}
