<?php
/* =====================================================================
   Play Space — a ponte com o iDFace (Control iD)
   MODO DIRETO (o normal): o próprio aparelho, com internet, chama este arquivo pela internet a cada poucos segundos:
     GET  idface.php/<chave>/push   → o site responde com um comando da API do aparelho (ler rostos reconhecidos novos,
                                       cadastrar uma criança, mandar a foto dela, acertar o relógio, apagar, renomear)
     POST idface.php/<chave>/result → o aparelho devolve o resultado do comando
   PONTE: um computador na rede do aparelho roda o programa ponte-idface.ps1 e chama idface.php?acao=ponte com a chave.
   CONECTAR: idface.php?acao=conectar_bat baixa o "Conectar o iDFace.bat", que liga o modo direto no aparelho em dois cliques.
   Cada rosto reconhecido vira entrada (quem estava fora) ou saída (quem estava dentro), com débito do pacote e aviso no WhatsApp.
   ===================================================================== */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/idface-lib.php';
set_exception_handler(function (Throwable $e) { registrarErro('idface: ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()); falha('Erro no servidor: ' . $e->getMessage(), 500); });

const REGISTRO_PREFIXO = 'P'; // a matrícula no aparelho: P + id da criança
function pushComando(string $endpoint, $body): array { return ['verb' => 'POST', 'endpoint' => $endpoint, 'body' => $body, 'contentType' => 'application/json']; }
function quandoDoTs(int $ts): string { return date('Y-m-d H:i:s', $ts + 60 * (int)cfg('ajuste_min', 0)); }
function ultimoLog(string $aparelho): int { $q = db()->prepare('SELECT MAX(log_id) FROM batidas WHERE aparelho = ?'); $q->execute([$aparelho]); return max((int)$q->fetchColumn(), (int)cfg('log_corte', 0)); }
function regrasFila(): array { $r = []; foreach (cfgComo('regra_') as $k => $v) if (preg_match('/^regra_\d+$/', $k)) $r[$k] = $v; return $r; }   // só a fila (regra_<id>), não regra_id nem regra_ok_
function fotoBase64(string $foto): string { return preg_match('#^data:image/[a-z]+;base64,(.+)$#s', $foto, $m) ? $m[1] : $foto; }
// a resposta do aparelho com a imagem: procura o primeiro texto base64 grande numa chave "image"
function acharImagem($r): string {
  if (!is_array($r)) return '';
  foreach ($r as $k => $v) { if (is_string($v) && ($k === 'image' || $k === 'user_image') && strlen($v) > 200) return $v; if (is_array($v)) { $x = acharImagem($v); if ($x !== '') return $x; } }
  return '';
}
// a imagem do aparelho vem grande; no cadastro basta uma miniatura (uns 15 KB), senão a tela pesa a cada sincronia
function miniatura(string $b64, int $max = 320): string {
  $bin = base64_decode($b64, true); if ($bin === false) return '';
  if (!function_exists('imagecreatefromstring')) return 'data:image/jpeg;base64,' . $b64;
  $im = @imagecreatefromstring($bin); if (!$im) return '';
  $w = imagesx($im); $h = imagesy($im); $e = min(1, $max / max($w, $h)); $nw = max(1, (int)round($w * $e)); $nh = max(1, (int)round($h * $e));
  $out = imagecreatetruecolor($nw, $nh); imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
  ob_start(); imagejpeg($out, null, 82); $jpg = ob_get_clean(); imagedestroy($im); imagedestroy($out);
  return 'data:image/jpeg;base64,' . base64_encode($jpg);
}

// grava os rostos reconhecidos (a mesma leitura não entra duas vezes) e processa cada um como entrada ou saída
function salvarBatidas(string $aparelho, array $batidas, bool $aoVivo): int {
  $db = db(); $novas = 0;
  $corte = cfg('log_corte', null);
  if ($corte === null && !(int)$db->query('SELECT COUNT(*) FROM batidas')->fetchColumn()) {
    // primeira leitura: o que já estava no aparelho (testes, outra loja) fica para trás; conta daqui para a frente
    $mx = 0; foreach ($batidas as $b) if (is_array($b)) $mx = max($mx, (int)($b['id'] ?? 0));
    gravarCfg('log_corte', $mx); return 0;
  }
  $corte = (int)$corte;
  $fila = array_map('intval', array_values(cfgComo('apagar_')));
  $ins = $db->prepare('INSERT OR IGNORE INTO batidas (idface_user, ts, log_id, aparelho, quando, crianca_id, resultado, criado_em) VALUES (?, ?, ?, ?, ?, (SELECT id FROM criancas WHERE idface_id = ?), ?, ?)');
  usort($batidas, fn($a, $b) => ((int)($a['time'] ?? 0)) <=> ((int)($b['time'] ?? 0)));
  foreach ($batidas as $b) {
    if (!is_array($b)) continue;
    $uid = (int)($b['user_id'] ?? 0); $ts = (int)($b['time'] ?? 0); $lid = (int)($b['id'] ?? 0); $ev = (int)($b['event'] ?? 7);
    if ($uid <= 0 || $ts <= 0 || $lid <= $corte || $ev !== 7 || in_array($uid, $fila, true)) continue; // 7: acesso liberado (rosto reconhecido)
    $quando = quandoDoTs($ts);
    // leitura antiga demais (o aparelho ficou sem internet e acumulou): registra, mas só processa o que tem até 6 horas
    $resultado = (time() - strtotime($quando)) > 6 * 3600 ? 'antiga' : processarRosto($uid, $quando);
    $ins->execute([$uid, $ts, $lid, $aparelho, $quando, $uid, $resultado, agora()]);
    if ($ins->rowCount()) $novas++;
  }
  return $novas;
}
// a lista de cadastros que o aparelho já tem: quem tem a matrícula P<id> fica ligado à criança; o resto fica guardado para a tela
function guardarUsuariosAparelho(array $us): void {
  $db = db(); $soltos = [];
  foreach ($us as $u) {
    if (!is_array($u) || !isset($u['id'])) continue; $id = (int)$u['id']; $mat = (string)($u['registration'] ?? $u['matricula'] ?? '');
    if (preg_match('/^' . REGISTRO_PREFIXO . '(\d+)$/', $mat, $m)) {
      $cid = (int)$m[1]; $c = crianca($cid);
      if ($c && $c['idface_id'] === null) { try { $db->prepare('UPDATE criancas SET idface_id = ? WHERE id = ?')->execute([$id, $cid]); apagarCfg('mandar_' . $cid); if ($c['foto'] !== '') gravarCfg('foto_' . $cid, $id); } catch (Throwable $e) { } }
      if ($c && !empty($c['liberado']) && !cfg('regra_ok_' . $cid) && !cfg('regra_' . $cid) && !cfg('desregra_' . $cid)) gravarCfg('regra_' . $cid, $id);
      continue;
    }
    $q = $db->prepare('SELECT 1 FROM criancas WHERE idface_id = ?'); $q->execute([$id]);
    if (!$q->fetchColumn()) $soltos[] = ['id' => $id, 'nome' => mb_substr((string)($u['name'] ?? $u['nome'] ?? ''), 0, 80)];
  }
  gravarCfg('aparelho_soltos', $soltos);
}
function marcarContato(array $extra = [], bool $direto = true): void {
  $ant = cfg('push_estado', []); $ant = is_array($ant) ? $ant : [];
  gravarCfg('push_estado', array_merge($ant, ['em' => agora(), 'erro' => '', 'direto' => $direto], $extra));
}

/* ---------- modo direto (push) ---------- */
function acaoPush(string $rota): void {
  header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
  $db = db(); $c = idfaceCfg(); $aparelho = 'idface';
  $st = cfg('push_estado', []); $st = is_array($st) ? $st : []; $st['em'] = agora();
  if (!$c['ligado']) { gravarCfg('push_estado', $st); echo '{}'; exit; }
  if ($rota === 'result') {
    $bruto = (string)file_get_contents('php://input', false, null, 0, 6000000);
    $j = json_decode($bruto, true); $fly = $st['inflight'] ?? null; unset($st['inflight']);
    if (is_array($j) && is_array($fly)) {
      $resp = $j['response'] ?? null; if (is_string($resp)) $resp = json_decode($resp, true);
      $tipo = (string)($fly['tipo'] ?? '');
      if ($tipo === 'apagar' && !empty($fly['alvo'])) apagarCfg('apagar_' . (int)$fly['alvo']); // sai da fila com qualquer resposta
      if (!empty($j['error'])) {
        $st['erro'] = 'o aparelho respondeu: ' . substr((string)$j['error'], 0, 200);
        if ($tipo === 'regra' && !empty($fly['crianca'])) { $cid = (int)$fly['crianca']; $n = (int)cfg('falhas_regra_' . $cid, 0) + 1; gravarCfg('falhas_regra_' . $cid, $n); if ($n >= 3) { apagarCfg('regra_' . $cid); apagarCfg('falhas_regra_' . $cid); } if (stripos((string)$j['error'], 'exist') !== false || stripos((string)$j['error'], 'UNIQUE') !== false) { apagarCfg('regra_' . $cid); gravarCfg('regra_ok_' . $cid, agora()); } }
        if ($tipo === 'regras_lista' || $tipo === 'regra_criar') gravarCfg('regra_id', 0);
        if ($tipo === 'desregra' && !empty($fly['crianca'])) { apagarCfg('desregra_' . (int)$fly['crianca']); apagarCfg('regra_ok_' . (int)$fly['crianca']); }   // sem regra para tirar: já está bloqueada
        if ($tipo === 'rosto' && !empty($fly['crianca'])) { apagarCfg('pedirosto_' . (int)$fly['crianca']); gravarCfg('rosto_erro_' . (int)$fly['crianca'], 'O aparelho recusou a captura: ' . substr((string)$j['error'], 0, 120)); }
        if (in_array($tipo, ['criar', 'foto', 'renomear'], true) && !empty($fly['crianca'])) { $k = 'falhas_' . $tipo . '_' . (int)$fly['crianca']; $n = (int)cfg($k, 0) + 1; gravarCfg($k, $n); if ($n >= 3) { apagarCfg(($tipo === 'criar' ? 'mandar_' : ($tipo === 'foto' ? 'foto_' : 'renomear_')) . (int)$fly['crianca']); apagarCfg($k); } }
      } else {
        $st['erro'] = '';
        if ($tipo === 'batidas') { $novas = salvarBatidas($aparelho, (array)($resp['access_logs'] ?? []), !empty($st['ja_leu'])); $st['ja_leu'] = true; if ($novas) $st['ultima_batida'] = agora(); }
        elseif ($tipo === 'usuarios') { guardarUsuariosAparelho((array)($resp['users'] ?? [])); $st['usuarios_em'] = time(); }
        elseif ($tipo === 'criar') {
          $uid = (int)(((array)($resp['ids'] ?? []))[0] ?? 0); $cid = (int)($fly['crianca'] ?? 0);
          if ($uid && $cid) {
            $up = $db->prepare('UPDATE criancas SET idface_id = ? WHERE id = ? AND idface_id IS NULL'); $up->execute([$uid, $cid]);
            if ($up->rowCount()) { $cr = crianca($cid); if ($cr && $cr['foto'] !== '') gravarCfg('foto_' . $cid, $uid); if ($cr && !empty($cr['liberado'])) gravarCfg('regra_' . $cid, $uid); }
            else gravarCfg('apagar_' . $uid, $uid);   // a criança já tinha cadastro no aparelho: este saiu duplicado, apaga
            apagarCfg('mandar_' . $cid);
          }
          $st['usuarios_em'] = 0;
        }
        // a regra de acesso do aparelho: sem ela, o rosto é reconhecido mas o visor diz "Não autorizado" e a leitura não vale
        elseif ($tipo === 'regras_lista') {
          $rs = (array)($resp['access_rules'] ?? []); $id = 0;
          foreach ($rs as $r) { if (is_array($r) && isset($r['id'])) { $id = (int)$r['id']; break; } }
          if ($id) gravarCfg('regra_id', $id); else gravarCfg('regra_id', 'criar');
        }
        elseif ($tipo === 'regra_criar') { $id = (int)(((array)($resp['ids'] ?? []))[0] ?? 0); if ($id) gravarCfg('regra_id', $id); else gravarCfg('regra_id', 0); }
        elseif ($tipo === 'regra') { $cid = (int)($fly['crianca'] ?? 0); apagarCfg('regra_' . $cid); gravarCfg('regra_ok_' . $cid, agora()); }
        elseif ($tipo === 'desregra') { $cid = (int)($fly['crianca'] ?? 0); apagarCfg('desregra_' . $cid); apagarCfg('regra_ok_' . $cid); }
        elseif ($tipo === 'rosto') { // o aparelho capturou (ou não) o rosto da criança
          $cid = (int)($fly['crianca'] ?? 0); $ok = is_array($resp) && (!empty($resp['success']) || (isset($resp['user_id']) && empty($resp['error'])));
          apagarCfg('pedirosto_' . $cid);
          if ($ok) { $db->prepare('UPDATE criancas SET rosto = 1 WHERE id = ?')->execute([$cid]); gravarCfg('rosto_ok_' . $cid, agora()); apagarCfg('rosto_erro_' . $cid); $cr = crianca($cid); if ($cr && $cr['idface_id'] !== null) gravarCfg('baixarfoto_' . $cid, (int)$cr['idface_id']); }
          else gravarCfg('rosto_erro_' . $cid, 'O aparelho não conseguiu capturar o rosto' . (is_array($resp) && !empty($resp['error']) ? ' (' . substr((string)$resp['error'], 0, 80) . ')' : '') . '. Tente de novo com a criança de frente, a uns 50 cm da câmera.');
        }
        elseif ($tipo === 'baixarfoto') { // a imagem do rosto que o aparelho capturou vai para o cadastro
          $cid = (int)($fly['crianca'] ?? 0); apagarCfg('baixarfoto_' . $cid);
          $b64 = acharImagem($resp);
          if ($b64 !== '') { $mini = miniatura($b64); if ($mini !== '') $db->prepare("UPDATE criancas SET foto = ?, foto_origem = 'idface' WHERE id = ? AND (foto = '' OR foto_origem = 'idface')")->execute([$mini, $cid]); }
        }
        elseif ($tipo === 'foto') { apagarCfg('foto_' . (int)$fly['crianca']); $db->prepare('UPDATE criancas SET rosto = 1 WHERE id = ?')->execute([(int)$fly['crianca']]); }
        elseif ($tipo === 'renomear') apagarCfg('renomear_' . (int)$fly['crianca']);
        elseif ($tipo === 'relogio') $st['relogio_em'] = time();
        elseif ($tipo === 'periodo') unset($st['periodo']);
      }
    }
    gravarCfg('push_estado', $st); echo '{}'; exit;
  }
  // GET /push: o próximo comando
  $agoraS = microtime(true); $dt = $agoraS - (float)($st['get_em'] ?? 0); $st['get_em'] = $agoraS;
  if ($dt > 0 && $dt < 600) $st['periodo'] = round(empty($st['periodo']) ? $dt : 0.7 * (float)$st['periodo'] + 0.3 * $dt, 1);
  $cmd = null; $tipo = ''; $alvo = null; $cid = 0;
  $apagar = cfgComo('apagar_'); $mandar = cfgComo('mandar_'); $fotos = cfgComo('foto_'); $renomear = cfgComo('renomear_');
  if ($apagar) { $tipo = 'apagar'; $alvo = (int)reset($apagar); $cmd = pushComando('destroy_objects', ['object' => 'users', 'where' => ['users' => ['id' => $alvo]]]); }
  elseif (time() - (int)($st['relogio_em'] ?? 0) > 3600) { $tipo = 'relogio'; $cmd = pushComando('set_system_time', ['year' => (int)date('Y'), 'month' => (int)date('n'), 'day' => (int)date('j'), 'hour' => (int)date('G'), 'minute' => (int)date('i'), 'second' => (int)date('s')]); }
  elseif ((float)($st['periodo'] ?? 0) > 4 && time() - (int)($st['periodo_ajuste_em'] ?? 0) > 3600) { $tipo = 'periodo'; $st['periodo_ajuste_em'] = time(); $cmd = pushComando('set_configuration', ['push_server' => ['push_request_period' => '3']]); }
  elseif ($mandar) {
    foreach ($mandar as $k => $v) { $cr = crianca((int)$v); if ($cr && $cr['ativo'] && $cr['idface_id'] === null) { $cid = (int)$cr['id']; $tipo = 'criar'; $cmd = pushComando('create_objects', ['object' => 'users', 'values' => [['name' => mb_substr($cr['nome'], 0, 50), 'registration' => REGISTRO_PREFIXO . $cr['id']]]]); break; } apagarCfg($k); }
  }
  if (!$cmd) { // bloqueadas: a regra de acesso sai do aparelho e o rosto passa a ser recusado
    foreach (cfgComo('desregra_') as $k => $uid) { $cid = (int)substr($k, 9); $cr = crianca($cid); if (!$cr || $cr['idface_id'] === null) { apagarCfg($k); continue; } $tipo = 'desregra'; $cmd = pushComando('destroy_objects', ['object' => 'user_access_rules', 'where' => ['user_access_rules' => ['user_id' => (int)$cr['idface_id']]]]); break; }
  }
  if (!$cmd && regrasFila()) { // a regra de acesso para quem acabou de entrar no aparelho
    $rid = cfg('regra_id', null);
    if ($rid === null) { $tipo = 'regras_lista'; $cmd = pushComando('load_objects', ['object' => 'access_rules']); }
    elseif ($rid === 'criar') { $tipo = 'regra_criar'; $cmd = pushComando('create_objects', ['object' => 'access_rules', 'values' => [['name' => 'Play Space', 'type' => 1, 'priority' => 0]]]); }
    elseif ((int)$rid > 0) {
      foreach (regrasFila() as $k => $uid) { $cid = (int)substr($k, 6); $cr = crianca($cid); if (!$cr || $cr['idface_id'] === null || (isset($cr['liberado']) && !(int)$cr['liberado'])) { apagarCfg($k); continue; } $tipo = 'regra'; $cmd = pushComando('create_objects', ['object' => 'user_access_rules', 'values' => [['user_id' => (int)$cr['idface_id'], 'access_rule_id' => (int)$rid]]]); break; }
    }
    else foreach (regrasFila() as $k => $uid) apagarCfg($k);   // o aparelho não tem regras nem deixou criar: segue sem
  }
  if (!$cmd) { // o rosto ao vivo: o aparelho entra no modo de cadastro e pede para a criança olhar para a câmera
    foreach (cfgComo('pedirosto_') as $k => $v) {
      $cid = (int)substr($k, 10); $cr = crianca($cid);
      if (!$cr || !$cr['ativo']) { apagarCfg($k); continue; }
      if ($cr['idface_id'] === null) { if (!cfg('mandar_' . $cid)) gravarCfg('mandar_' . $cid, $cid); continue; }   // primeiro o cadastro no aparelho
      $tipo = 'rosto'; gravarCfg('rosto_em_' . $cid, agora());
      $cmd = pushComando('remote_enroll', ['type' => 'face', 'user_id' => (int)$cr['idface_id'], 'save' => true, 'sync' => true, 'auto' => true]); break;
    }
  }
  if (!$cmd) {
    foreach (cfgComo('baixarfoto_') as $k => $uid) { $cid = (int)substr($k, 11); $cr = crianca($cid); if (!$cr || $cr['idface_id'] === null) { apagarCfg($k); continue; } $tipo = 'baixarfoto'; $cmd = pushComando('user_get_image_list', ['user_ids' => [(int)$cr['idface_id']]]); break; }
  }
  if (!$cmd && $fotos) {
    foreach ($fotos as $k => $uid) { $cid = (int)substr($k, 5); $cr = crianca($cid); if ($cr && ($cr['foto_origem'] ?? '') === 'idface') { apagarCfg($k); continue; } if ($cr && $cr['foto'] !== '' && $cr['idface_id'] !== null) { $tipo = 'foto'; $cmd = pushComando('user_set_image_list', ['match' => false, 'user_images' => [['user_id' => (int)$cr['idface_id'], 'image' => fotoBase64($cr['foto'])]]]); break; } apagarCfg($k); }
  }
  if (!$cmd && $renomear) {
    foreach ($renomear as $k => $uid) { $cid = (int)substr($k, 9); $cr = crianca($cid); if ($cr && $cr['idface_id'] !== null) { $tipo = 'renomear'; $cmd = pushComando('modify_objects', ['object' => 'users', 'values' => ['name' => mb_substr($cr['nome'], 0, 50)], 'where' => ['users' => ['id' => (int)$cr['idface_id']]]]); break; } apagarCfg($k); }
  }
  if (!$cmd && time() - (int)($st['usuarios_em'] ?? 0) > 60) { $tipo = 'usuarios'; $cmd = pushComando('load_objects', ['object' => 'users']); }
  if (!$cmd) {
    if (microtime(true) - (float)($st['batidas_em'] ?? 0) < 2) { unset($st['inflight']); gravarCfg('push_estado', $st); exit; }
    $tipo = 'batidas'; $st['batidas_em'] = microtime(true);
    $cmd = pushComando('load_objects', ['object' => 'access_logs', 'where' => ['access_logs' => ['id' => ['>' => ultimoLog($aparelho)]]]]);
  }
  $st['inflight'] = ['tipo' => $tipo, 'em' => time(), 'crianca' => $cid ?: null, 'alvo' => $alvo];
  gravarCfg('push_estado', $st);
  echo json_encode($cmd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
}

/* ---------- a ponte (o computador da loja rodando ponte-idface.ps1) ---------- */
function acaoPonte(array $in): void {
  $chave = (string)($_SERVER['HTTP_X_PONTE_CHAVE'] ?? $in['chave'] ?? '');
  if ($chave === '' || !hash_equals(chavePonte(), $chave)) { usleep(400000); falha('Chave da ponte errada. Copie de novo em Ajustes → iDFace.', 403); }
  $db = db(); $c = idfaceCfg(); $aparelho = 'idface';
  $st = ['em' => agora(), 'nome' => substr((string)($in['nome'] ?? ''), 0, 60), 'versao' => substr((string)($in['versao'] ?? ''), 0, 20), 'erro' => substr((string)($in['erro'] ?? ''), 0, 300)];
  if (!$c['ligado']) { gravarCfg('ponte_estado', $st + ['erro' => 'o iDFace está desligado em Ajustes']); responder(['desligado' => true, 'ultimo_log' => ultimoLog($aparelho), 'novas' => 0, 'criar' => [], 'apagar' => [], 'agora' => agora(), 'push_url' => urlPush(), 'direto_ok' => false]); }
  if (isset($in['usuarios'])) guardarUsuariosAparelho((array)$in['usuarios']);
  foreach ((array)($in['criados'] ?? []) as $cr) {
    if (!is_array($cr)) continue; $cid = (int)($cr['pessoa_id'] ?? $cr['crianca_id'] ?? 0); $uid = (int)($cr['user_id'] ?? 0);
    if ($cid && $uid) { try { $db->prepare('UPDATE criancas SET idface_id = ? WHERE id = ? AND idface_id IS NULL')->execute([$uid, $cid]); } catch (Throwable $e) { } apagarCfg('mandar_' . $cid); $x = crianca($cid); if ($x && $x['foto'] !== '') gravarCfg('foto_' . $cid, $uid); }
  }
  foreach ((array)($in['apagados'] ?? []) as $ap) { $ap = (int)$ap; if ($ap > 0) apagarCfg('apagar_' . $ap); }
  $novas = salvarBatidas($aparelho, (array)($in['batidas'] ?? []), !empty($in['ao_vivo']));
  $st['novas'] = $novas; if ($novas) $st['ultima_batida'] = agora(); else { $ant = cfg('ponte_estado', []); if (is_array($ant) && !empty($ant['ultima_batida'])) $st['ultima_batida'] = $ant['ultima_batida']; }
  gravarCfg('ponte_estado', $st);
  $criar = [];
  foreach (cfgComo('mandar_') as $k => $v) { $x = crianca((int)$v); if ($x && $x['ativo'] && $x['idface_id'] === null) $criar[] = ['pessoa_id' => (int)$x['id'], 'nome' => $x['nome'], 'matricula' => REGISTRO_PREFIXO . $x['id']]; }
  $ps = cfg('push_estado', []); $direto = is_array($ps) && !empty($ps['em']) && time() - strtotime((string)$ps['em']) < 120;
  responder(['ultimo_log' => ultimoLog($aparelho), 'novas' => $novas, 'criar' => $criar, 'apagar' => array_values(array_map('intval', cfgComo('apagar_'))), 'agora' => agora(), 'push_url' => urlPush(), 'direto_ok' => $direto]);
}

/* ---------- entrada ---------- */
// o aparelho chama .../idface.php/<chave>/push e .../result; se o servidor não passar o PATH_INFO, lemos da própria URL
$rota = (string)($_SERVER['PATH_INFO'] ?? '');
if ($rota === '' && preg_match('#idface\.php(/[^?]*)#', (string)($_SERVER['REQUEST_URI'] ?? ''), $mr)) $rota = $mr[1];
if ($rota === '' && isset($_GET['r'])) $rota = '/' . ltrim((string)$_GET['r'], '/');
if (preg_match('#^/([0-9a-f]{20,64})/(push|result)/?$#', $rota, $mp)) {
  if (!hash_equals(chavePonte(), $mp[1])) { http_response_code(403); exit; }
  acaoPush($mp[2]);
}
$in = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { $bruto = file_get_contents('php://input') ?: ''; $in = json_decode($bruto, true); if (!is_array($in)) $in = $_POST; }
$acao = (string)($in['acao'] ?? $_GET['acao'] ?? '');
if ($acao === 'ponte') acaoPonte($in);

iniciarSessao();
// a versão para Mac: o mesmo conector em bash (.command abre no Terminal com dois cliques). Vai num .zip para o arquivo chegar
// com permissão de executar; sem ZipArchive, vai o arquivo cru. Com ?chave=..., serve o texto puro para colar no Terminal.
function scriptMac(): string {
  $sh = @file_get_contents(__DIR__ . '/../ponte/conectar-idface.sh'); if ($sh === false || strpos($sh, '__SITE__') === false) falha('Não achei o conector para Mac no servidor (ponte/conectar-idface.sh).', 500);
  $c = idfaceCfg(); $q = fn($v) => str_replace("'", "'\\''", (string)$v);
  return str_replace(['__SITE__', '__CHAVE__', '__IP__', '__USUARIO__', '__NOME__'], [$q(urlIdface()), $q(chavePonte()), $q($c['ip']), $q($c['usuario']), $q($c['nome'])], $sh);
}
if ($acao === 'conectar_sh') { // texto puro, autorizado pela própria chave da ponte: bash <(curl -fsSL '...?acao=conectar_sh&chave=...')
  $k = (string)($_GET['chave'] ?? ''); if ($k === '' || !hash_equals(chavePonte(), $k)) { usleep(400000); http_response_code(403); exit; }
  header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: no-store'); echo scriptMac(); exit;
}
if ($acao === 'conectar_command') {
  $u = eu(); if (!$u || $u['papel'] !== 'gestor') { header('Location: ../', true, 302); exit; }
  $sh = scriptMac(); $nome = 'Conectar o iDFace.command';
  if (class_exists('ZipArchive')) {
    $tmp = tempnam(sys_get_temp_dir(), 'ps'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
    $z->addFromString($nome, $sh); $z->setExternalAttributesName($nome, ZipArchive::OPSYS_UNIX, (0100755 << 16)); $z->close();
    $dados = file_get_contents($tmp); @unlink($tmp);
    header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="Conectar o iDFace (Mac).zip"'); header('Content-Length: ' . strlen($dados)); header('Cache-Control: no-store'); echo $dados; exit;
  }
  header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="' . $nome . '"'); header('Content-Length: ' . strlen($sh)); header('Cache-Control: no-store'); echo $sh; exit;
}
if ($acao === 'conectar_bat') { // um arquivo só: o CONECTAR com o programa e a configuração dentro (dois cliques no Windows)
  $u = eu(); if (!$u || $u['papel'] !== 'gestor') { header('Location: ../', true, 302); exit; }
  $ps1 = @file_get_contents(__DIR__ . '/../ponte/ponte-idface.ps1'); if ($ps1 === false || strpos($ps1, '$UMA_VEZ') === false) falha('Não achei o programa de conexão no servidor (ponte/ponte-idface.ps1).', 500);
  $c = idfaceCfg();
  $cfg = json_encode(['site' => urlIdface(), 'chave' => chavePonte(), 'ip' => $c['ip'], 'usuario' => $c['usuario'], 'nome' => $c['nome']], JSON_UNESCAPED_SLASHES);
  $extrai = "\$m1='#__'+'CONFIG__'; \$m2='#__'+'PONTE__'; \$t=[IO.File]::ReadAllText(\$env:PLAY_BAT,[Text.Encoding]::UTF8); \$a=\$t.IndexOf(\$m1); \$b=\$t.IndexOf(\$m2); if (\$a -lt 0 -or \$b -lt \$a) { exit 1 }; \$d=\$env:PLAY_DIR; [IO.File]::WriteAllText(\$d+'\\ponte-config.json',\$t.Substring(\$a+\$m1.Length,\$b-\$a-\$m1.Length).Trim(),[Text.Encoding]::UTF8); [IO.File]::WriteAllText(\$d+'\\ponte-idface.ps1',\$t.Substring(\$b+\$m2.Length).TrimStart(),[Text.Encoding]::UTF8)";
  $ps = '"%SystemRoot%\\System32\\WindowsPowerShell\\v1.0\\powershell.exe"';
  $linhas = ['@echo off', 'title Play Space - conectar o iDFace',
    'rem Um arquivo so: dentro dele vem o programa e a configuracao do site. Dois cliques, a senha do iDFace e pronto.',
    'set "PLAY_DIR=%LOCALAPPDATA%\\PlaySpace"', 'set "PLAY_BAT=%~f0"', 'if not exist "%PLAY_DIR%" mkdir "%PLAY_DIR%"',
    $ps . ' -NoProfile -ExecutionPolicy Bypass -Command "' . $extrai . '"',
    'if errorlevel 1 (echo. & echo  Nao consegui preparar o programa. Se apareceu "language mode" ou "not recognized", este Windows nao deixa o programa rodar. & echo. & pause & exit /b 1)',
    'echo.', 'echo  Play Space - conectar o iDFace ao site', 'echo  --------------------------------------',
    'echo  Este computador precisa estar na MESMA rede (mesmo roteador) do iDFace.', 'echo  Vai abrir uma tela no navegador para o usuario e a senha do iDFace.', 'echo.',
    $ps . ' -NoProfile -ExecutionPolicy Bypass -File "%PLAY_DIR%\\ponte-idface.ps1" -UmaVez',
    'if errorlevel 2 exit /b 0',
    'if errorlevel 1 (echo. & echo  Nao concluiu. Se apareceu "running scripts is disabled", "not digitally signed" ou "language mode", este Windows nao deixa o programa rodar. & echo.)',
    'echo.', 'pause', 'goto :eof', '', '#__CONFIG__', $cfg, '#__PONTE__', $ps1];
  $bat = implode("\r\n", array_map(fn($l) => preg_replace("/\r\n?|\n/", "\r\n", $l), $linhas));
  header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="Conectar o iDFace.bat"'); header('Content-Length: ' . strlen($bat)); header('Cache-Control: no-store');
  echo $bat; exit;
}
falha('Ação desconhecida.', 404);
