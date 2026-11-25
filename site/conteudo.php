<?php
// Conteúdo do hub (hub › Conteúdo): os posts do Instagram e do TikTok que o dono sobe pelo próprio hub ("Novo post").
// Cada post tem a data, a rede, o tema, a legenda e os arquivos (vídeo, imagens do carrossel ou foto, e a capa do reels).
// Tudo fica guardado no servidor, FORA da pasta do site (~/simplificando-conteudo): ninguém acha um post pelo endereço.
// Só o dono do painel, logado, usa este arquivo (token do Firebase no cabeçalho Authorization).
//   acao=semana&ini=AAAA-MM-DD          → os posts dos 7 dias a partir de ini (e o que já foi marcado como postado)
//   acao=arquivo&id=...                 → um arquivo de um post
//   acao=feito   (POST k, on)           → marca ou desmarca "postado" (k = id do post)
//   acao=novo    (POST data, plat, tema, legenda) → cria o post (sem arquivos ainda) e devolve o id
//   acao=pedaco&id=..&k=..&parte=..&nome=..&mime=..  (corpo = um pedaço do arquivo, até 4 MB) → manda um arquivo em pedaços (vídeo grande)
//   acao=pronto  (POST id)              → termina o envio: confere os arquivos e diz se é reels, carrossel ou foto
//   acao=apagar  (POST id)              → apaga o post e os arquivos
// Robô de testes: SIMP_TESTE_DONO (o token aceito como dono).
header('Cache-Control: no-store');
function cd_sai($a, $c = 200) { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
$casa = dirname(__DIR__);
$agora = getenv('SIMP_AGORA') ? (int)getenv('SIMP_AGORA') : time();
date_default_timezone_set('America/Sao_Paulo');
const CD_PLATS = ['instagram' => 'Instagram', 'tiktok' => 'TikTok'];
const CD_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];
const CD_PEDACO = 4 * 1024 * 1024 + 1024, CD_MAX_ARQ = 700 * 1024 * 1024, CD_MAX_N = 20;

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

// ---------- onde os posts ficam (fora do site) ----------
$DIR = $casa . '/simplificando-conteudo'; $ARQS = $DIR . '/arquivos'; $IDX = $DIR . '/posts.json';
function cd_pasta($DIR, $ARQS) { foreach ([$DIR, $ARQS] as $d) if (!is_dir($d)) { @mkdir($d, 0700, true); @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n"); } }
function cd_le($IDX) { $P = json_decode((string)@file_get_contents($IDX), true); return is_array($P) ? $P : []; }
function cd_grava($IDX, $P) { $t = $IDX . '.novo'; @file_put_contents($t, json_encode($P, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX); @chmod($t, 0600); @rename($t, $IDX); }
const CD_ID = '/^[a-z0-9]{12}$/';
// reels (tem vídeo), carrossel (2 imagens ou mais) ou foto (1 imagem); no reels, a 1ª imagem é a capa
function cd_monta($p) {
  $arqs = array_values(array_filter($p['arqs'] ?? [], fn($a) => !empty($a['ok'])));
  $vid = array_values(array_filter($arqs, fn($a) => strpos($a['mime'], 'video/') === 0));
  $img = array_values(array_filter($arqs, fn($a) => strpos($a['mime'], 'image/') === 0));
  $f = fn($a) => ['id' => $p['id'] . '-' . $a['k'], 'nome' => $a['nome'], 'mime' => $a['mime'], 'tam' => (int)$a['tam']];
  if ($vid) { $tipo = 'reels'; $midia = array_map($f, $vid); $capa = $img ? $f($img[0]) : null; }
  elseif (count($img) >= 2) { $tipo = 'carrossel'; $midia = array_map($f, $img); $capa = $midia[0]; }
  elseif ($img) { $tipo = 'foto'; $midia = [$f($img[0])]; $capa = $midia[0]; }
  else { $tipo = 'vazio'; $midia = []; $capa = null; }
  return ['id' => $p['id'], 'plat' => $p['plat'], 'tema' => $p['tema'], 'tipo' => $tipo, 'capa' => $capa, 'midia' => $midia, 'legenda' => $p['legenda'], 'em' => $p['em']];
}

$acao = $_GET['acao'] ?? '';
if (!in_array($acao, ['semana', 'arquivo', 'feito', 'novo', 'pedaco', 'pronto', 'apagar'], true)) cd_sai(['ok' => false], 400);
if (!cd_dono($casa, $agora)) cd_sai(['ok' => false, 'erro' => 'Só o dono do painel. Saia e entre de novo no hub.'], 403);
if ($acao !== 'semana' && $acao !== 'arquivo' && $_SERVER['REQUEST_METHOD'] !== 'POST') cd_sai(['ok' => false], 405);
cd_pasta($DIR, $ARQS);
$arqF = $casa . '/.simplificando-conteudo-feito.json';

if ($acao === 'feito') {
  $k = (string)($_POST['k'] ?? ''); if (!preg_match(CD_ID, $k)) cd_sai(['ok' => false], 400);
  $F = json_decode((string)@file_get_contents($arqF), true); if (!is_array($F)) $F = [];
  if (!empty($_POST['on'])) $F[$k] = $agora; else unset($F[$k]);
  foreach ($F as $kk => $v) if ($v < $agora - 400 * 86400) unset($F[$kk]);
  @file_put_contents($arqF, json_encode($F), LOCK_EX);
  cd_sai(['ok' => true]);
}

if ($acao === 'novo') {
  $data = (string)($_POST['data'] ?? ''); $plat = (string)($_POST['plat'] ?? '');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !checkdate((int)substr($data, 5, 2), (int)substr($data, 8, 2), (int)substr($data, 0, 4))) cd_sai(['ok' => false, 'erro' => 'Escolha a data do post.'], 400);
  if (!isset(CD_PLATS[$plat])) cd_sai(['ok' => false, 'erro' => 'Escolha Instagram ou TikTok.'], 400);
  $P = cd_le($IDX); if (count($P) >= 2000) cd_sai(['ok' => false, 'erro' => 'Muitos posts guardados. Apague os antigos.'], 429);
  $id = substr(bin2hex(random_bytes(8)), 0, 12);
  $P[$id] = ['id' => $id, 'data' => $data, 'plat' => $plat, 'tema' => mb_substr(trim((string)($_POST['tema'] ?? '')), 0, 120),
    'legenda' => mb_substr(str_replace("\r\n", "\n", trim((string)($_POST['legenda'] ?? ''))), 0, 2200), 'arqs' => [], 'em' => $agora, 'pronto' => false];
  cd_grava($IDX, $P);
  cd_sai(['ok' => true, 'id' => $id]);
}

if ($acao === 'pedaco') {
  $id = (string)($_GET['id'] ?? ''); $k = (int)($_GET['k'] ?? -1); $parte = (int)($_GET['parte'] ?? -1); $mime = strtolower((string)($_GET['mime'] ?? ''));
  $P = cd_le($IDX); if (!preg_match(CD_ID, $id) || !isset($P[$id])) cd_sai(['ok' => false, 'erro' => 'Post não encontrado.'], 404);
  if ($k < 0 || $k >= CD_MAX_N || $parte < 0) cd_sai(['ok' => false], 400);
  if (!isset(CD_MIMES[$mime])) cd_sai(['ok' => false, 'erro' => 'Tipo de arquivo não aceito. Use JPG, PNG, WEBP, HEIC, MP4, MOV ou WEBM.'], 415);
  $corpo = file_get_contents('php://input', false, null, 0, CD_PEDACO + 1);
  if ($corpo === false || $corpo === '' || strlen($corpo) > CD_PEDACO) cd_sai(['ok' => false, 'erro' => 'Pedaço inválido.'], 400);
  $f = $ARQS . '/' . $id . '-' . $k . '.' . CD_MIMES[$mime];
  if ($parte === 0) @unlink($f); elseif (!is_file($f)) cd_sai(['ok' => false, 'erro' => 'Envio fora de ordem. Tente de novo.'], 409);
  if ((is_file($f) ? filesize($f) : 0) + strlen($corpo) > CD_MAX_ARQ) { @unlink($f); cd_sai(['ok' => false, 'erro' => 'Arquivo grande demais (até 700 MB).'], 413); }
  if (@file_put_contents($f, $corpo, FILE_APPEND | LOCK_EX) === false) cd_sai(['ok' => false, 'erro' => 'O servidor não conseguiu gravar. Pode ser falta de espaço.'], 507);
  @chmod($f, 0600);
  $nome = preg_replace('/[^A-Za-z0-9._\- ]/', '_', mb_substr((string)($_GET['nome'] ?? ''), 0, 120)) ?: ('arquivo-' . ($k + 1) . '.' . CD_MIMES[$mime]);
  $L = array_values(array_filter($P[$id]['arqs'], fn($a) => $a['k'] !== $k));
  $L[] = ['k' => $k, 'nome' => $nome, 'mime' => $mime, 'arq' => basename($f), 'tam' => filesize($f), 'ok' => !empty($_GET['fim'])];
  usort($L, fn($a, $b) => $a['k'] <=> $b['k']); $P[$id]['arqs'] = $L; cd_grava($IDX, $P);
  cd_sai(['ok' => true, 'tam' => filesize($f)]);
}

if ($acao === 'pronto') {
  $id = (string)($_POST['id'] ?? ''); $P = cd_le($IDX); if (!preg_match(CD_ID, $id) || !isset($P[$id])) cd_sai(['ok' => false], 404);
  $P[$id]['pronto'] = true; cd_grava($IDX, $P);
  cd_sai(['ok' => true, 'post' => cd_monta($P[$id])]);
}

if ($acao === 'apagar') {
  $id = (string)($_POST['id'] ?? ''); $P = cd_le($IDX); if (!preg_match(CD_ID, $id) || !isset($P[$id])) cd_sai(['ok' => false], 404);
  foreach ($P[$id]['arqs'] as $a) @unlink($ARQS . '/' . basename($a['arq']));
  unset($P[$id]); cd_grava($IDX, $P);
  $F = json_decode((string)@file_get_contents($arqF), true); if (is_array($F) && isset($F[$id])) { unset($F[$id]); @file_put_contents($arqF, json_encode($F), LOCK_EX); }
  cd_sai(['ok' => true]);
}

if ($acao === 'semana') {
  $ini = (string)($_GET['ini'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ini)) $ini = date('Y-m-d', $agora);
  $dias = []; for ($i = 0; $i < 7; $i++) $dias[] = date('Y-m-d', strtotime($ini . ' 12:00 +' . $i . ' day'));
  $res = array_fill_keys($dias, []); $P = cd_le($IDX);
  // um envio que ficou pela metade (a pessoa fechou a página) some depois de 1 dia
  $mudou = false; foreach ($P as $pid => $p) if (empty($p['pronto']) && $p['em'] < $agora - 86400) { foreach ($p['arqs'] as $a) @unlink($ARQS . '/' . basename($a['arq'])); unset($P[$pid]); $mudou = true; }
  if ($mudou) cd_grava($IDX, $P);
  foreach ($P as $p) if (!empty($p['pronto']) && isset($res[$p['data']])) $res[$p['data']][] = cd_monta($p);
  foreach ($res as &$l) usort($l, fn($a, $b) => strcmp($a['plat'], $b['plat']) ?: ($a['em'] <=> $b['em'])); unset($l);
  $F = json_decode((string)@file_get_contents($arqF), true); if (!is_array($F)) $F = [];
  $feito = []; foreach ($res as $l) foreach ($l as $p) if (isset($F[$p['id']])) $feito[$p['id']] = $F[$p['id']];
  cd_sai(['ok' => true, 'ini' => $ini, 'dias' => $res, 'feito' => (object)$feito]);
}

// acao=arquivo: só arquivos de posts guardados aqui
$id = (string)($_GET['id'] ?? '');
if (!preg_match('/^([a-z0-9]{12})-(\d{1,2})$/', $id, $m)) cd_sai(['ok' => false], 404);
$P = cd_le($IDX); $a = null; foreach (($P[$m[1]]['arqs'] ?? []) as $x) if ($x['k'] === (int)$m[2] && !empty($x['ok'])) $a = $x;
$f = $a ? $ARQS . '/' . basename($a['arq']) : ''; if (!$a || !is_file($f)) cd_sai(['ok' => false, 'erro' => 'Arquivo não encontrado.'], 404);
header('Content-Type: ' . (isset(CD_MIMES[$a['mime']]) ? $a['mime'] : 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._\- ]/', '_', $a['nome']) . '"');
header('X-Content-Type-Options: nosniff'); header('Content-Length: ' . filesize($f));
@set_time_limit(600); while (ob_get_level()) ob_end_flush();
readfile($f);
