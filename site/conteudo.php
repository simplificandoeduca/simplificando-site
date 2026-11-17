<?php
// Conteúdo do dia (hub › Conteúdo): lê as pastas de posts do Google Drive do dono e mostra o post de cada dia.
// Organização no Drive: uma pasta "Instagram" e uma "TikTok"; dentro, uma pasta por dia ("17/11" ou "22/11 - Tema do post")
// com a capa, o vídeo (reels) ou as imagens (carrossel) e a legenda num .txt.
// Quem lê é a conta de serviço do Firebase (a mesma chave do login: ~/simplificando-chave.json). O dono compartilha as duas
// pastas com o e-mail dela, como Leitor. Nenhum id de pasta nem chave fica no GitHub.
// Só o dono do painel, logado, usa este arquivo (token do Firebase no cabeçalho Authorization).
//   acao=semana&ini=AAAA-MM-DD  → os posts dos 7 dias a partir de ini (e o que já foi marcado como feito)
//   acao=arquivo&id=...         → a capa, a imagem ou o vídeo (só arquivos de dentro das pastas de posts)
//   acao=feito (POST k, on)     → marca ou desmarca "postado"
// Robô de testes: SIMP_TESTE_DRIVE (um .json no lugar do Drive) e SIMP_TESTE_DONO (o token aceito como dono).
header('Cache-Control: no-store');
function cd_sai($a, $c = 200) { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
$casa = dirname(__DIR__);
$agora = getenv('SIMP_AGORA') ? (int)getenv('SIMP_AGORA') : time();
date_default_timezone_set('America/Sao_Paulo');
const CD_PLATS = ['instagram' => 'Instagram', 'tiktok' => 'TikTok'];
const CD_PASTA = 'application/vnd.google-apps.folder';

// ---------- só o dono (o login confirmado fica guardado 10 minutos, para as imagens não conferirem uma a uma) ----------
function cd_dono($casa, $agora) {
  require_once __DIR__ . '/dono.inc.php';
  $tok = dono_token(); if (strlen($tok) < 20) return false;
  if (($t = getenv('SIMP_TESTE_DONO')) !== false && $t !== '') return hash_equals($t, $tok);
  $arq = $casa . '/.simplificando-conteudo-sessao.json'; $h = hash('sha256', $tok);
  $S = json_decode((string)@file_get_contents($arq), true); if (!is_array($S)) $S = [];
  if (($S[$h] ?? 0) > $agora) return true;
  if (!dono_uid()) return false;
  foreach ($S as $k => $v) if ($v <= $agora) unset($S[$k]);
  $S[$h] = $agora + 600; @file_put_contents($arq, json_encode($S), LOCK_EX); @chmod($arq, 0600);
  return true;
}

// ---------- o Drive (de verdade, ou o de mentira do robô) ----------
function cd_teste() { static $D = null; $f = getenv('SIMP_TESTE_DRIVE'); if (!$f) return null; if ($D === null) $D = json_decode((string)file_get_contents($f), true) ?: []; return $D; }
function cd_chave($casa) { $k = is_file($casa . '/simplificando-chave.json') ? json_decode((string)file_get_contents($casa . '/simplificando-chave.json'), true) : null; return ($k && !empty($k['private_key']) && !empty($k['client_email'])) ? $k : null; }
function cd_http($url, $tok, $corpo = null, $saida = null) {
  $c = curl_init($url); $op = [CURLOPT_RETURNTRANSFER => $saida === null, CURLOPT_TIMEOUT => $saida === null ? 25 : 300, CURLOPT_FOLLOWLOCATION => true];
  if ($tok) $op[CURLOPT_HTTPHEADER] = ['Authorization: Bearer ' . $tok];
  if ($corpo !== null) { $op[CURLOPT_POST] = true; $op[CURLOPT_POSTFIELDS] = $corpo; }
  if ($saida !== null) $op[CURLOPT_WRITEFUNCTION] = $saida;
  curl_setopt_array($c, $op); $r = curl_exec($c); $st = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
  return [$st, $r];
}
// passe da conta de serviço só para LER o Drive (guardado fora do site por 50 minutos)
function cd_token($casa, $agora) {
  $arq = $casa . '/.simplificando-drive-token.json'; $T = json_decode((string)@file_get_contents($arq), true);
  if (is_array($T) && ($T['exp'] ?? 0) > $agora + 60) return $T['tok'];
  $k = cd_chave($casa); if (!$k || !function_exists('curl_init')) return null;
  $b = function ($x) { return rtrim(strtr(base64_encode($x), '+/', '-_'), '='); };
  $j = $b(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b(json_encode(['iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/drive.readonly', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $agora, 'exp' => $agora + 3600], JSON_UNESCAPED_SLASHES));
  openssl_sign($j, $sg, $k['private_key'], 'sha256WithRSAEncryption');
  [$st, $r] = cd_http('https://oauth2.googleapis.com/token', null, http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $j . '.' . $b($sg)]));
  $d = json_decode((string)$r, true); if (empty($d['access_token'])) return null;
  @file_put_contents($arq, json_encode(['tok' => $d['access_token'], 'exp' => $agora + (int)($d['expires_in'] ?? 3600)]), LOCK_EX); @chmod($arq, 0600);
  return $d['access_token'];
}
class CdErro extends Exception {}
// lista os itens (id, name, mimeType, size) de uma busca do Drive
function cd_busca($casa, $agora, $q) {
  if (($D = cd_teste()) !== null) {
    $out = [];
    if (preg_match("/^'([^']+)' in parents/", $q, $m)) { foreach ($D as $id => $f) if (($f['pai'] ?? '') === $m[1] && (strpos($q, "mimeType='") === false || $f['mime'] === CD_PASTA)) $out[] = ['id' => $id, 'name' => $f['nome'], 'mimeType' => $f['mime'], 'size' => strlen(base64_decode($f['dados'] ?? ''))]; }
    else foreach ($D as $id => $f) if (($f['mime'] ?? '') === CD_PASTA && empty($f['pai'])) $out[] = ['id' => $id, 'name' => $f['nome'], 'mimeType' => CD_PASTA];
    return $out;
  }
  $tok = cd_token($casa, $agora); if (!$tok) throw new CdErro(cd_chave($casa) ? 'login' : 'semChave');
  $out = []; $pg = '';
  do {
    [$st, $r] = cd_http('https://www.googleapis.com/drive/v3/files?' . http_build_query(['q' => $q, 'fields' => 'nextPageToken,files(id,name,mimeType,size)', 'pageSize' => 200, 'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true', 'pageToken' => $pg]), $tok);
    $d = json_decode((string)$r, true);
    if ($st != 200) throw new CdErro(($st == 403 && strpos((string)$r, 'accessNotConfigured') !== false) || strpos((string)$r, 'SERVICE_DISABLED') !== false ? 'semApi' : 'drive');
    foreach ($d['files'] ?? [] as $f) $out[] = $f;
    $pg = $d['nextPageToken'] ?? '';
  } while ($pg);
  return $out;
}
function cd_texto($casa, $agora, $f) {
  if (($D = cd_teste()) !== null) return (string)base64_decode($D[$f['id']]['dados'] ?? '');
  $tok = cd_token($casa, $agora); if (!$tok) return '';
  $url = $f['mimeType'] === 'application/vnd.google-apps.document'
    ? 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($f['id']) . '/export?mimeType=text%2Fplain'
    : 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($f['id']) . '?alt=media&supportsAllDrives=true';
  [$st, $r] = cd_http($url, $tok);
  return $st == 200 ? (string)$r : '';
}

// ---------- as duas pastas (Instagram e TikTok), achadas pelo nome entre as pastas compartilhadas com a conta de serviço ----------
function cd_raizes($casa, $agora) {
  $arq = $casa . '/.simplificando-conteudo-raizes.json';
  if (cd_teste() === null) { $R = json_decode((string)@file_get_contents($arq), true); if (is_array($R) && ($R['ate'] ?? 0) > $agora && !empty($R['r'])) return $R['r']; }
  $L = cd_busca($casa, $agora, "mimeType='" . CD_PASTA . "' and trashed=false and (name contains 'Instagram' or name contains 'TikTok' or name contains 'Tiktok' or name contains 'instagram' or name contains 'tiktok')");
  $r = [];
  foreach (CD_PLATS as $k => $n) {
    $ok = array_values(array_filter($L, fn($f) => mb_strtolower(trim($f['name'])) === $k));
    if (!$ok) $ok = array_values(array_filter($L, fn($f) => strpos(mb_strtolower($f['name']), $k) !== false));
    if ($ok) $r[$k] = $ok[0]['id'];
  }
  if ($r && cd_teste() === null) @file_put_contents($arq, json_encode(['ate' => $agora + 3600, 'r' => $r]), LOCK_EX);
  return $r;
}
// "17/11", "17/11/2026" ou "22/11 - Tema" → [data AAAA-MM-DD, tema]; o ano é o mais perto de hoje
function cd_dia($nome, $agora) {
  if (!preg_match('#^\s*(\d{1,2})\s*[/.\-]\s*(\d{1,2})(?:\s*[/.\-]\s*(\d{2,4}))?\s*(?:[-–—:·|]\s*(.*))?$#u', $nome, $m)) return null;
  $d = (int)$m[1]; $mm = (int)$m[2]; if ($mm < 1 || $mm > 12 || $d < 1 || $d > 31) return null;
  $ano0 = (int)date('Y', $agora);
  if (!empty($m[3])) $anos = [strlen($m[3]) == 2 ? 2000 + (int)$m[3] : (int)$m[3]]; else $anos = [$ano0 - 1, $ano0, $ano0 + 1];
  $melhor = null; $dist = PHP_INT_MAX;
  foreach ($anos as $a) { if (!checkdate($mm, $d, $a)) continue; $t = mktime(12, 0, 0, $mm, $d, $a); if (abs($t - $agora) < $dist) { $dist = abs($t - $agora); $melhor = date('Y-m-d', $t); } }
  return $melhor ? [$melhor, trim($m[4] ?? '')] : null;
}
// "Reel-Mito-abdominal-queima-gordura.mp4" → "Mito abdominal queima gordura"
function cd_tema($nome) {
  $t = preg_replace('/\.[a-z0-9]{2,4}$/i', '', $nome);
  $t = preg_replace('/^(capa|reels?|video|vídeo|carrossel|carousel|post|legendas?|imagem|img|foto)[\s_\-]*\d*[\s_\-]*/iu', '', $t);
  $t = trim(preg_replace('/[\s_\-]+/', ' ', $t));
  return $t === '' ? '' : mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
}
// um dia de uma plataforma: o que tem na pasta vira o post (reels, carrossel ou foto) com a legenda
function cd_post($casa, $agora, $plat, $pasta, $tema) {
  $L = cd_busca($casa, $agora, "'" . $pasta . "' in parents and trashed=false");
  usort($L, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
  $img = $vid = $txt = []; $capa = null;
  foreach ($L as $f) {
    $mt = $f['mimeType'] ?? ''; $it = ['id' => $f['id'], 'nome' => $f['name'], 'mime' => $mt, 'tam' => (int)($f['size'] ?? 0)];
    if (strpos($mt, 'video/') === 0) $vid[] = $it;
    elseif (strpos($mt, 'image/') === 0) { if (!$capa && preg_match('/^(capa|cover|thumb)/i', $f['name'])) $capa = $it; else $img[] = $it; }
    elseif ($mt === 'text/plain' || $mt === 'application/vnd.google-apps.document' || preg_match('/\.txt$/i', $f['name'])) $txt[] = $f;
  }
  if ($vid) { $tipo = 'reels'; $midia = $vid; }
  elseif (count($img) >= 2) { $tipo = 'carrossel'; $midia = $img; }
  elseif ($img || $capa) { $tipo = 'foto'; $midia = $img ?: [$capa]; }
  else { $tipo = 'vazio'; $midia = []; }
  if (!$capa && $img) $capa = $img[0];
  $leg = trim(implode("\n\n", array_map(fn($f) => trim(preg_replace('/^\xEF\xBB\xBF/', '', str_replace("\r\n", "\n", cd_texto($casa, $agora, $f)))), $txt)));
  if ($tema === '') foreach (array_merge($vid, $img, $capa ? [$capa] : []) as $f) { if (($tema = cd_tema($f['nome'])) !== '') break; }
  return ['plat' => $plat, 'tema' => $tema, 'tipo' => $tipo, 'capa' => $capa, 'midia' => $midia, 'legenda' => $leg, 'pasta' => $pasta];
}

$acao = $_GET['acao'] ?? '';
if (!in_array($acao, ['semana', 'arquivo', 'feito'], true)) cd_sai(['ok' => false], 400);
if (!cd_dono($casa, $agora)) cd_sai(['ok' => false, 'erro' => 'Só o dono do painel. Saia e entre de novo no hub.'], 403);
$arqF = $casa . '/.simplificando-conteudo-feito.json';
$arqC = $casa . '/.simplificando-conteudo-cache.json';

if ($acao === 'feito') {
  $k = (string)($_POST['k'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}\|(instagram|tiktok)$/', $k)) cd_sai(['ok' => false], 400);
  $F = json_decode((string)@file_get_contents($arqF), true); if (!is_array($F)) $F = [];
  if (!empty($_POST['on'])) $F[$k] = $agora; else unset($F[$k]);
  foreach ($F as $kk => $v) if ($v < $agora - 400 * 86400) unset($F[$kk]);
  @file_put_contents($arqF, json_encode($F), LOCK_EX);
  cd_sai(['ok' => true]);
}

if ($acao === 'semana') {
  $ini = (string)($_GET['ini'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini)) $ini = date('Y-m-d', $agora);
  $dias = []; for ($i = 0; $i < 7; $i++) $dias[] = date('Y-m-d', strtotime($ini . ' 12:00 +' . $i . ' day'));
  $C = json_decode((string)@file_get_contents($arqC), true); if (!is_array($C)) $C = [];
  $forca = !empty($_GET['forca']); $ck = 'sem:' . $ini;
  try {
    if (!$forca && cd_teste() === null && isset($C[$ck]) && $C[$ck]['ate'] > $agora) $res = $C[$ck]['d'];
    else {
      $R = cd_raizes($casa, $agora);
      if (!$R) { $k = cd_chave($casa); cd_sai(['ok' => false, 'erro' => 'semPastas', 'email' => cd_teste() !== null ? 'robo@teste' : ($k['client_email'] ?? '')]); }
      $res = array_fill_keys($dias, []);
      foreach ($R as $plat => $raiz) {
        foreach (cd_busca($casa, $agora, "'" . $raiz . "' in parents and trashed=false and mimeType='" . CD_PASTA . "'") as $p) {
          $dt = cd_dia($p['name'], $agora); if (!$dt || !isset($res[$dt[0]])) continue;
          $res[$dt[0]][] = cd_post($casa, $agora, $plat, $p['id'], $dt[1]);
        }
      }
      foreach ($res as &$l) usort($l, fn($a, $b) => strcmp($a['plat'], $b['plat'])); unset($l);
      // guarda 10 minutos, e os arquivos liberados para baixar (só os de dentro das pastas de posts)
      foreach ($C as $kk => $v) if (($v['ate'] ?? 0) < $agora - 86400) unset($C[$kk]);
      $ids = $C['ids'] ?? []; foreach ($res as $l) foreach ($l as $p) foreach (array_merge($p['midia'], $p['capa'] ? [$p['capa']] : []) as $f) $ids[$f['id']] = [$f['mime'], $f['tam'], $f['nome'], $agora];
      if (count($ids) > 3000) { uasort($ids, fn($a, $b) => $b[3] <=> $a[3]); $ids = array_slice($ids, 0, 2000, true); }
      $C['ids'] = $ids; $C[$ck] = ['ate' => $agora + 600, 'd' => $res];
      if (cd_teste() === null) @file_put_contents($arqC, json_encode($C), LOCK_EX); else @file_put_contents($arqC, json_encode(['ids' => $ids]), LOCK_EX);
    }
  } catch (CdErro $e) {
    $k = cd_chave($casa);
    cd_sai(['ok' => false, 'erro' => $e->getMessage(), 'email' => $k['client_email'] ?? '']);
  }
  $F = json_decode((string)@file_get_contents($arqF), true); if (!is_array($F)) $F = [];
  $feito = []; foreach ($dias as $d) foreach (CD_PLATS as $p => $_) if (isset($F[$d . '|' . $p])) $feito[$d . '|' . $p] = $F[$d . '|' . $p];
  cd_sai(['ok' => true, 'ini' => $ini, 'dias' => $res, 'feito' => (object)$feito]);
}

// acao=arquivo: só o que já apareceu numa semana do Conteúdo (pasta de posts)
$id = (string)($_GET['id'] ?? '');
$C = json_decode((string)@file_get_contents($arqC), true); $f = $C['ids'][$id] ?? null;
if (!preg_match('/^[A-Za-z0-9_\-]{1,200}$/', $id) || !$f) cd_sai(['ok' => false, 'erro' => 'Arquivo fora das pastas de posts.'], 404);
[$mime, $tam, $nome] = $f;
if (!preg_match('#^(image|video)/[a-z0-9.+\-]+$#i', $mime)) $mime = 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._\- ]/', '_', $nome) . '"');
header('X-Content-Type-Options: nosniff');
if (($D = cd_teste()) !== null) { $b = base64_decode($D[$id]['dados'] ?? ''); header('Content-Length: ' . strlen($b)); echo $b; exit; }
$tok = cd_token($casa, $agora); if (!$tok) cd_sai(['ok' => false], 503);
@set_time_limit(600); while (ob_get_level()) ob_end_flush();
if ($tam > 0) header('Content-Length: ' . $tam);
[$st] = cd_http('https://www.googleapis.com/drive/v3/files/' . rawurlencode($id) . '?alt=media&supportsAllDrives=true', $tok, null, function ($c, $pedaco) { echo $pedaco; flush(); return strlen($pedaco); });
exit;
