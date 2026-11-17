<?php
// Lembretes para voltar a treinar, por e-mail (o do celular fica no push.php).
//  · 1 dia depois do cadastro sem o 1º treino (e de novo com 4 e 10 dias): "Seu treino já está pronto";
//  · 4 dias sem treinar: "Bora voltar?"; 10 dias sem treinar: "Vamos recomeçar?".
// Uma vez por etapa para cada pessoa; se ela volta a treinar, recomeça. Roda uma vez por dia (o push.php chama às 18h30)
// ou à mão: php lembretes.php cron. Lê as contas no Firebase com a chave ~/simplificando-chave.json (só a data dos treinos).
// Quem não quer: link "Desligar os lembretes por e-mail" (acao=sair) ou a chave do app (Perfil › Avisos, acao=pref).
// Robô de testes: SIMP_TESTE_LEMB (contas de mentira), SIMP_TESTE_PUSH (anota os e-mails em vez de mandar), SIMP_AGORA (relógio).
if (!defined('LEMB_INCLUIDO')) { header('Cache-Control: no-store'); }
require_once __DIR__ . '/email.inc.php';
$LB_CASA = dirname(__DIR__);
$LB_ARQ = $LB_CASA . '/.simplificando-lembretes.json';
function lb_le() { global $LB_ARQ; $v = json_decode((string)@file_get_contents($LB_ARQ), true); return is_array($v) ? $v + ['env' => [], 'sair' => []] : ['env' => [], 'sair' => []]; }
function lb_grava($v) { global $LB_ARQ; @file_put_contents($LB_ARQ, json_encode($v, JSON_UNESCAPED_UNICODE), LOCK_EX); @chmod($LB_ARQ, 0600); }
function lb_segredo() {
  global $LB_CASA; $a = $LB_CASA . '/.simplificando-lembretes-segredo'; $s = trim((string)@file_get_contents($a));
  if (strlen($s) < 32) { $s = bin2hex(random_bytes(32)); @file_put_contents($a, $s, LOCK_EX); @chmod($a, 0600); }
  return $s;
}
function lb_tk($uid) { return substr(hash_hmac('sha256', 'sair:' . $uid, lb_segredo()), 0, 32); }
function lb_agora() { return (PHP_SAPI === 'cli' && getenv('SIMP_AGORA')) ? (int)getenv('SIMP_AGORA') : time(); }
function lb_dia($ts) { return gmdate('Y-m-d', $ts - 3 * 3600); } // dia em Brasília
function lb_dias($de, $ate) { return (int)floor((strtotime($ate . ' 12:00:00 UTC') - strtotime($de . ' 12:00:00 UTC')) / 86400); }

// etapa: n1/n4/n10 (sem o 1º treino, desde o cadastro) ou t4/t10 (sem treinar, desde o último treino)
function lb_etapa($cad, $ult, $hoje) {
  $ref = $ult ?: $cad; if (!$ref) return ['', '', 0];
  $d = lb_dias($ref, $hoje);
  $e = $ult ? ($d >= 10 ? 't10' : ($d >= 4 ? 't4' : '')) : ($d >= 10 ? 'n10' : ($d >= 4 ? 'n4' : ($d >= 1 ? 'n1' : '')));
  return [$e, $e ? $e . '|' . $ref : '', $d];
}

/* ---------- contas: Firebase (ou as de mentira do robô) ---------- */
function lb_fv($v) { // valor do Firestore -> PHP
  if (!is_array($v)) return null;
  foreach (['stringValue', 'booleanValue', 'timestampValue'] as $k) if (array_key_exists($k, $v)) return $v[$k];
  if (isset($v['integerValue'])) return (int)$v['integerValue']; if (isset($v['doubleValue'])) return (float)$v['doubleValue'];
  if (isset($v['mapValue'])) { $o = []; foreach (($v['mapValue']['fields'] ?? []) as $k => $x) $o[$k] = lb_fv($x); return $o; }
  if (isset($v['arrayValue'])) return array_map('lb_fv', $v['arrayValue']['values'] ?? []);
  return null;
}
function lb_contas() {
  global $LB_CASA;
  if ($f = getenv('SIMP_TESTE_LEMB')) { $j = json_decode((string)@file_get_contents($f), true); return is_array($j) ? $j : ['contas' => []]; }
  $k = is_file($LB_CASA . '/simplificando-chave.json') ? json_decode((string)file_get_contents($LB_CASA . '/simplificando-chave.json'), true) : null;
  if (!$k || empty($k['private_key']) || !function_exists('curl_init')) return null;
  $pid = $k['project_id'] ?? 'simplificando-26c09'; $t = time();
  $b = function ($x) { return rtrim(strtr(base64_encode($x), '+/', '-_'), '='); };
  $j = $b(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b(json_encode(['iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/datastore', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $t, 'exp' => $t + 600], JSON_UNESCAPED_SLASHES));
  openssl_sign($j, $sg, $k['private_key'], 'sha256WithRSAEncryption');
  $c = curl_init('https://oauth2.googleapis.com/token'); curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $j . '.' . $b($sg)])]);
  $tk = json_decode((string)curl_exec($c), true); curl_close($c); if (empty($tk['access_token'])) return null;
  $base = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($pid) . '/databases/(default)/documents/';
  $lista = function ($col) use ($base, $tk) { $out = []; $pg = '';
    for ($i = 0; $i < 20; $i++) {
      $c = curl_init($base . $col . '?pageSize=300' . ($pg ? '&pageToken=' . rawurlencode($pg) : '')); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tk['access_token']]]);
      $r = json_decode((string)curl_exec($c), true); curl_close($c);
      foreach (($r['documents'] ?? []) as $d) { $o = []; foreach (($d['fields'] ?? []) as $kk => $x) $o[$kk] = lb_fv($x); $out[basename($d['name'])] = $o; }
      $pg = $r['nextPageToken'] ?? ''; if (!$pg) break;
    }
    return $out; };
  $perfis = $lista('perfis'); $auto = $lista('autonomos'); $prog = $lista('progresso'); $alunos = $lista('alunos'); $sis = $lista('sistema');
  $dono = $sis['dono']['uid'] ?? '';
  $dt = function ($x) { if (is_numeric($x) && $x > 1e11) return gmdate('Y-m-d', (int)($x / 1000) - 3 * 3600); return preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$x) ? lb_dia(strtotime((string)$x)) : ''; };
  $contas = [];
  // sem personal: perfis/{uid} (nome, e-mail, cadastro) + autonomos/{uid} (os treinos)
  foreach ($perfis as $uid => $p) { if (($p['tipo'] ?? '') !== 'autonomo') continue;
    $S = json_decode((string)($auto[$uid]['s'] ?? ''), true); $S = is_array($S) ? $S : [];
    $tr = []; foreach (($S['conta']['treinos'] ?? []) as $L => $x) $tr[$L] = (string)($x['nome'] ?? '');
    $contas[] = ['uid' => $uid, 'nome' => (string)($p['nome'] ?? ($S['nome'] ?? '')), 'email' => (string)($p['email'] ?? ''), 'cad' => $dt($p['criadoEm'] ?? '') ?: $dt($S['aceite']['em'] ?? ''),
      'treinos' => array_map(function ($h) { return ['d' => (string)($h['date'] ?? ''), 't' => (string)($h['t'] ?? ''), 'min' => (int)($h['min'] ?? 0)]; }, array_values(array_filter((array)($S['history'] ?? []), 'is_array'))), 'plano' => $tr]; }
  // com personal: alunos/{id} (nome, e-mail, acesso) + progresso/{uid} (os treinos)
  foreach ($alunos as $aid => $a) { $uid = (string)($a['uid'] ?? ''); if (!$uid || !empty($a['exemplo'])) continue;
    if (($a['perfil']['status'] ?? 'ativo') === 'inativo' || (($a['acesso']['ativo'] ?? true) === false)) continue;
    $P = json_decode((string)($prog[$uid]['s'] ?? ''), true); $P = is_array($P) ? $P : [];
    $tr = []; foreach (($a['plano']['treinos'] ?? []) as $L => $x) $tr[$L] = (string)($x['nome'] ?? ($x['foco'] ?? ''));
    $contas[] = ['uid' => $uid, 'nome' => (string)($a['perfil']['nome'] ?? ''), 'email' => (string)(($a['perfil']['email'] ?? '') ?: ($perfis[$uid]['email'] ?? '')), 'cad' => $dt($a['criadoEm'] ?? '') ?: $dt($perfis[$uid]['criadoEm'] ?? ''),
      'treinos' => array_map(function ($h) { return ['d' => (string)($h['d'] ?? ''), 't' => (string)($h['t'] ?? ''), 'min' => (int)($h['min'] ?? 0)]; }, array_values(array_filter((array)($P['h'] ?? []), 'is_array'))), 'plano' => $tr]; }
  return ['dono' => $dono, 'contas' => $contas];
}

/* ---------- os e-mails (mesmo visual do app; as imagens ficam em /img/email/) ---------- */
function lb_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
const LB_SITE = 'https://simplificandoeduca.com';
const LB_DSEM = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
function lb_email($etapa, $c, $d, $ult, $prox) {
  $nm = trim(preg_split('/\s+/', trim($c['nome']))[0] ?? ''); $nm = $nm ? mb_strtoupper(mb_substr($nm, 0, 1)) . mb_substr($nm, 1) : '';
  $N = lb_h($nm); $B = function ($t) { return '<b style="color:#F6F5EF">' . $t . '</b>'; };
  $tn = function ($L) use ($c) { $n = trim((string)($c['plano'][$L] ?? '')); return 'Treino ' . lb_h($L) . ($n && $n !== 'Treino ' . $L ? ' · ' . lb_h($n) : ''); };
  if ($etapa[0] === 'n') {
    $ass = ($nm ? $nm . ', s' : 'S') . 'eu treino já está pronto 💪'; $pre = 'Seu treino já está montado. Leva menos de 1 minuto para começar.';
    $eb = 'Seu primeiro treino'; $h1 = ($nm ? $N . ', s' : 'S') . 'eu treino já está pronto.'; $img = 'lembrete-1.jpg';
    $txt = 'Tudo bem? Você criou sua conta ' . ($d <= 1 ? 'ontem' : 'há ' . $d . ' dias') . ' e o seu treino já está montado. ' . $B('Estamos esperando você!');
    $passo = function ($n, $t, $s) { return '<tr><td width="40" valign="top" style="padding:8px 0"><div style="width:30px;height:30px;line-height:30px;border-radius:15px;background:#FF4646;color:#0F1712;text-align:center;font-weight:bold;font-size:15px">' . $n . '</div></td><td style="padding:8px 0;font-size:15px;line-height:1.35;color:#F6F5EF"><b>' . $t . '</b><br><span style="color:#A9B0AA;font-size:13.5px">' . $s . '</span></td></tr>'; };
    $meio = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#172019;border-radius:20px"><tr><td style="padding:12px 18px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' .
      $passo(1, 'Abra o app', 'Pelo ícone na tela do celular ou pelo site.') . $passo(2, 'Toque em Treino', 'Seu treino ' . lb_h($prox ?: 'A') . ' já está lá, com as séries e as cargas.') . $passo(3, 'Arraste “Começar treino”', 'E marque cada série. O app cuida do resto.') . '</table></td></tr></table>';
    $btn = 'Abrir meu treino'; $bc = '#FF4646'; $bt = '#FFFFFF'; $nota = 'Leva menos de 1 minuto para começar.';
  } elseif ($etapa === 't4') {
    $ass = ($nm ? $nm . ', b' : 'B') . 'ora voltar? 👀'; $pre = 'Faz ' . $d . ' dias do seu último treino.' . ($prox ? ' O treino ' . $prox . ' está te esperando.' : '');
    $eb = 'Sentimos sua falta 👀'; $h1 = ($nm ? $N . ', b' : 'B') . 'ora voltar?'; $img = 'lembrete-2.jpg';
    $txt = 'Faz ' . $B($d . ' dias') . ' do seu último treino. Bora voltar hoje?' . ($prox ? ' O ' . $B('treino ' . lb_h($prox)) . ' está te esperando.' : '');
    $u = $ult ? $tn($ult['t']) . ' · ' . LB_DSEM[(int)gmdate('w', strtotime($ult['d'] . ' 12:00 UTC'))] . ', ' . gmdate('d/m', strtotime($ult['d'] . ' 12:00 UTC')) . ($ult['min'] ? ' · ' . $ult['min'] . ' min' : '') : '';
    $lin = []; if ($u) $lin[] = ['Seu último treino', $u]; if ($prox) $lin[] = ['O de hoje', $tn($prox)];
    $meio = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#172019;border-radius:20px">';
    foreach ($lin as $i => $x) $meio .= '<tr><td style="padding:14px 18px;' . ($i ? 'border-top:1px solid #2E3931;' : '') . 'font-size:13px;color:#A9B0AA">' . $x[0] . '<br><b style="font-size:16px;color:#F6F5EF">' . $x[1] . '</b></td></tr>';
    $meio .= '</table>';
    $btn = 'Treinar hoje'; $bc = '#FF9A2E'; $bt = '#0F1712'; $nota = 'Um treino curto já conta. O app guarda tudo para você.';
  } else {
    $ass = ($nm ? $nm . ', v' : 'V') . 'amos recomeçar? 🔥'; $pre = 'São ' . $d . ' dias sem treinar. Recomece leve: o app ajusta as cargas para você.';
    $eb = 'Ainda dá tempo de retomar 🔥'; $h1 = ($nm ? $N . ', v' : 'V') . 'amos recomeçar?'; $img = 'lembrete-3.jpg';
    $txt = 'São ' . $B($d . ' dias') . ' sem treinar. Recomece leve hoje: ' . $B('o app ajusta as cargas para você.') . ' Sua evolução continua salva, do jeito que você deixou.';
    $meio = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">';
    foreach ([['🏋️', 'Carga sugerida', 'para um recomeço sem exagero'], ['📈', 'Seu histórico e seus recordes', 'tudo guardado no app'], ['⏰', 'Lembrete na hora certa', 'no horário em que você costuma treinar']] as $x)
      $meio .= '<tr><td width="34" valign="top" style="padding:7px 0;font-size:18px">' . $x[0] . '</td><td style="padding:7px 0;font-size:15px;line-height:1.4;color:#F6F5EF"><b>' . $x[1] . '</b> <span style="color:#A9B0AA">' . $x[2] . '</span></td></tr>';
    $meio .= '</table>';
    $btn = 'Voltar a treinar'; $bc = '#B794FF'; $bt = '#0F1712'; $nota = 'Bastam 3 treinos para pegar o ritmo de novo.';
  }
  $link = LB_SITE . '/treino/'; $sair = LB_SITE . '/lembretes.php?acao=sair&u=' . rawurlencode($c['uid']) . '&t=' . lb_tk($c['uid']);
  $F = 'font-family:Arial,Helvetica,sans-serif';
  $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head><body style="margin:0;padding:0;background:#E9E8E1">' .
    '<span style="display:none;max-height:0;overflow:hidden">' . lb_h($pre) . '</span>' .
    '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#E9E8E1"><tr><td align="center" style="padding:28px 12px">' .
    '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#0F1712;border-radius:28px;overflow:hidden;color:#F6F5EF;' . $F . '">' .
    '<tr><td style="height:6px;background:#FF4646;background:linear-gradient(90deg,#FF4646,#FF9A2E,#C8F542,#B794FF);font-size:0;line-height:0">&nbsp;</td></tr>' .
    '<tr><td style="padding:26px 30px 0"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td><img src="' . LB_SITE . '/treino/icon-192.png" width="44" height="44" alt="" style="display:block;border-radius:12px"></td><td style="padding-left:12px;font-size:19px;font-weight:bold;color:#F6F5EF">simplificando<span style="color:#FF4646">.</span></td></tr></table></td></tr>' .
    '<tr><td style="padding:26px 30px 0"><p style="margin:0;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#C8F542;font-weight:bold">' . $eb . '</p>' .
    '<h1 style="margin:10px 0 0;font-size:31px;line-height:1.08;color:#F6F5EF">' . $h1 . '</h1>' .
    '<p style="margin:14px 0 0;font-size:16px;line-height:1.55;color:#C9CDC6">' . $txt . '</p></td></tr>' .
    '<tr><td style="padding:24px 18px 0"><a href="' . lb_h($link) . '"><img src="' . LB_SITE . '/img/email/' . $img . '" width="524" alt="O app do Simplificando" style="display:block;width:100%;max-width:524px;border-radius:22px;border:0"></a></td></tr>' .
    '<tr><td style="padding:22px 30px 0">' . $meio . '</td></tr>' .
    '<tr><td style="padding:24px 30px 0"><a href="' . lb_h($link) . '" style="display:block;padding:18px 0;background:' . $bc . ';color:' . $bt . ';text-align:center;font-size:17px;font-weight:bold;text-decoration:none;border-radius:999px">' . $btn . '</a></td></tr>' .
    '<tr><td style="padding:14px 30px 0;text-align:center;font-size:13.5px;color:#A9B0AA">' . $nota . '</td></tr>' .
    '<tr><td style="padding:28px 30px 26px"><p style="margin:0;padding-top:18px;border-top:1px solid #2E3931;font-size:12px;line-height:1.55;color:#86928A">Você recebe este lembrete porque tem conta no Simplificando. <a href="' . lb_h($sair) . '" style="color:#A9B0AA">Desligar os lembretes por e-mail</a>.<br>Simplificando · simplificandoeduca.com</p></td></tr>' .
    '</table></td></tr></table></body></html>';
  return [$ass, $html];
}

/* ---------- a tarefa do dia ---------- */
function lb_rodar() {
  global $LB_CASA;
  $D = lb_contas(); if (!is_array($D)) return ['ok' => false, 'erro' => 'sem a chave do Firebase'];
  $hoje = lb_dia(lb_agora()); $st = lb_le(); $env = 0; $pul = 0;
  foreach (($D['contas'] ?? []) as $c) {
    $uid = (string)($c['uid'] ?? ''); $em = strtolower(trim((string)($c['email'] ?? '')));
    if (!$uid || $uid === ($D['dono'] ?? '') || isset($st['sair'][$uid])) { $pul++; continue; }
    if (!filter_var($em, FILTER_VALIDATE_EMAIL) || preg_match('/@alunos\.|firebaseapp\.com$/', $em)) { $pul++; continue; } // conta interna, sem e-mail de verdade
    $T = array_values(array_filter((array)($c['treinos'] ?? []), function ($h) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($h['d'] ?? '')); }));
    usort($T, function ($a, $b) { return strcmp($a['d'], $b['d']); });
    $ult = $T ? $T[count($T) - 1] : null;
    [$et, $vk, $d] = lb_etapa((string)($c['cad'] ?? ''), $ult ? $ult['d'] : '', $hoje);
    if (!$et || ($st['env'][$uid]['k'] ?? '') === $vk) continue;
    $L = array_keys((array)($c['plano'] ?? [])); sort($L);
    $prox = ''; if ($L) { $i = $ult ? array_search($ult['t'], $L, true) : false; $prox = $i === false ? $L[0] : $L[($i + 1) % count($L)]; }
    [$ass, $html] = lb_email($et, $c, $d, $ult, $prox);
    if (em_envia($LB_CASA, $em, $ass, $html, ['etapa' => $et])) { $st['env'][$uid] = ['k' => $vk, 'em' => lb_agora()]; $env++; }
    if ($env >= 300) break; // um limite por dia
  }
  lb_grava($st);
  return ['ok' => true, 'enviados' => $env, 'pulados' => $pul];
}

if (defined('LEMB_INCLUIDO')) return; // chamado pelo push.php (agendamento): só as funções
$acao = PHP_SAPI === 'cli' ? ($argv[1] ?? '') : ($_GET['acao'] ?? '');
if ($acao === 'cron') { if (PHP_SAPI !== 'cli') { http_response_code(403); exit; } echo json_encode(lb_rodar(), JSON_UNESCAPED_UNICODE), "\n"; exit; }

// desligar (ou ligar de novo) pelo link do e-mail
if ($acao === 'sair' || $acao === 'voltar') {
  header('Content-Type: text/html; charset=utf-8');
  $uid = (string)($_GET['u'] ?? ''); $ok = preg_match('/^[A-Za-z0-9_-]{6,128}$/', $uid) && hash_equals(lb_tk($uid), (string)($_GET['t'] ?? ''));
  if ($ok) { $st = lb_le(); if ($acao === 'sair') $st['sair'][$uid] = time(); else unset($st['sair'][$uid]); lb_grava($st); }
  $q = 'u=' . rawurlencode($uid) . '&t=' . rawurlencode((string)($_GET['t'] ?? ''));
  $tit = !$ok ? 'Link inválido' : ($acao === 'sair' ? 'Pronto, lembretes desligados.' : 'Lembretes ligados de novo.');
  $txt = !$ok ? 'Este link não está completo. Abra de novo pelo e-mail.' : ($acao === 'sair' ? 'Você não vai mais receber os lembretes por e-mail. Mudou de ideia?' : 'Você volta a receber os lembretes por e-mail quando ficar alguns dias sem treinar.');
  echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Lembretes · Simplificando</title></head>' .
    '<body style="margin:0;min-height:100vh;background:#0F1712;color:#F6F5EF;font-family:Arial,Helvetica,sans-serif;display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box">' .
    '<div style="max-width:420px"><img src="/treino/icon-192.png" width="64" height="64" alt="" style="border-radius:16px"><h1 style="font-size:30px;line-height:1.1;margin:22px 0 10px">' . lb_h($tit) . '</h1><p style="font-size:16px;line-height:1.5;color:#C9CDC6">' . lb_h($txt) . '</p>' .
    ($ok ? '<p style="margin-top:22px"><a href="?acao=' . ($acao === 'sair' ? 'voltar' : 'sair') . '&' . lb_h($q) . '" style="display:inline-block;padding:14px 22px;border-radius:999px;background:' . ($acao === 'sair' ? '#C8F542' : '#2E3931') . ';color:' . ($acao === 'sair' ? '#0F1712' : '#F6F5EF') . ';text-decoration:none;font-weight:bold">' . ($acao === 'sair' ? 'Ligar de novo' : 'Desligar') . '</a></p>' : '') .
    '<p style="margin-top:26px"><a href="/treino/" style="color:#A9B0AA">Abrir o app</a></p></div></body></html>';
  exit;
}

// a chave do app (Perfil › Avisos › Lembretes para voltar): o app manda o login da pessoa
if ($acao === 'pref') {
  header('Content-Type: application/json; charset=utf-8');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }
  require_once __DIR__ . '/dono.inc.php';
  [$key] = dono_cfg(); $tok = dono_token(); $uid = '';
  if ($key && strlen($tok) > 100) { [$s, $j] = dono_http('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($key), ['idToken' => $tok]); $uid = ($s == 200) ? (string)($j['users'][0]['localId'] ?? '') : ''; }
  if (!$uid) { http_response_code(403); echo json_encode(['ok' => false, 'erro' => 'Entre de novo no app e tente outra vez.']); exit; }
  $st = lb_le(); if (empty($_POST['on'])) $st['sair'][$uid] = time(); else unset($st['sair'][$uid]); lb_grava($st);
  echo json_encode(['ok' => true]); exit;
}
http_response_code(400); header('Content-Type: application/json; charset=utf-8'); echo '{"ok":false}';
