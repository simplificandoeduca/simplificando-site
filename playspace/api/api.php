<?php
/* =====================================================================
   Play Space — a API que a tela usa (POST JSON com "acao"; a resposta é JSON)
   Entrar, equipe (gestor, supervisor, atendente), crianças e pacotes de horas, entrada e saída,
   registros, conversas do WhatsApp, agenda, ajustes e as integrações (WhatsApp e iDFace).
   ===================================================================== */
declare(strict_types=1);
require __DIR__ . '/lib.php';
require __DIR__ . '/whats-lib.php';
set_exception_handler(function (Throwable $e) { registrarErro($e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()); if ($e instanceof RuntimeException) falha($e->getMessage(), 400); falha('Erro no servidor: ' . $e->getMessage(), 500); });

$in = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $bruto = file_get_contents('php://input') ?: '';
  if (strlen($bruto) > 6000000) falha('Envio grande demais.', 413);
  $in = json_decode($bruto, true); if (!is_array($in)) $in = $_POST;
}
$acao = (string)($in['acao'] ?? $_GET['acao'] ?? '');
// a tela manda o cabeçalho X-Play: um formulário de outro site não consegue (proteção contra CSRF)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_SERVER['HTTP_X_PLAY'] ?? '') !== '1') falha('Pedido sem o cabeçalho do app.', 403);
iniciarSessao();
$db = db();
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$val = fn(string $k, string $p = '') => trim((string)($in[$k] ?? $p));

/* ---------- o que a tela carrega de uma vez ---------- */
function criancaPublica(array $c): array {
  return ['id' => (int)$c['id'], 'nome' => $c['nome'], 'idade' => (int)$c['idade'], 'resp' => $c['resp'], 'rel' => $c['rel'], 'tel' => $c['tel'], 'cpf' => $c['cpf'], 'obs' => $c['obs'],
    'pacote' => round((int)$c['pacote_min'] / 60, 2), 'usado' => (int)$c['usado_min'], 'entrou' => $c['entrou'], 'idface_id' => $c['idface_id'] === null ? null : (int)$c['idface_id'],
    'rosto' => !empty($c['rosto']) || $c['idface_id'] !== null, 'foto' => (string)$c['foto'], 'ativo' => (bool)$c['ativo'], 'liberado' => !isset($c['liberado']) || (bool)$c['liberado']];
}
function dados(array $eu): array {
  $db = db(); $hojeMenos = date('Y-m-d H:i:s', time() - 86400);
  $criancas = array_map('criancaPublica', $db->query('SELECT * FROM criancas WHERE ativo = 1 ORDER BY nome')->fetchAll());
  $visitas = $db->query('SELECT * FROM visitas ORDER BY id DESC LIMIT 400')->fetchAll();
  $porCrianca = []; foreach ($visitas as $v) $porCrianca[(int)$v['crianca_id']][] = ['id' => (int)$v['id'], 'dia' => $v['saiu'], 'entrou' => substr($v['entrou'], 11, 5), 'saiu' => substr($v['saiu'], 11, 5), 'deb' => (int)$v['debitado_min']];
  foreach ($criancas as &$c) $c['visitas'] = $porCrianca[$c['id']] ?? [];
  unset($c);
  $registros = array_map(fn($r) => ['id' => (int)$r['id'], 'h' => $r['quando'], 'tipo' => $r['tipo'], 'nome' => $r['nome'], 'txt' => $r['texto'], 'crianca' => $r['crianca_id'] === null ? null : (int)$r['crianca_id']], $db->query('SELECT * FROM registros ORDER BY id DESC LIMIT 300')->fetchAll());
  $msgs = $db->query('SELECT * FROM mensagens ORDER BY quando ASC, id ASC')->fetchAll();
  $conversas = [];
  foreach ($msgs as $m) {
    $cid = (int)$m['crianca_id'];
    if (!isset($conversas[$cid])) $conversas[$cid] = ['id' => $cid, 'crianca' => $cid, 'lidas' => true, 'msgs' => []];
    $conversas[$cid]['msgs'][] = ['id' => (int)$m['id'], 'de' => $m['de'], 'txt' => $m['texto'], 'h' => $m['quando'], 'por' => $m['por'], 'auto' => (bool)$m['auto'], 'foto' => $m['foto'], 'erro' => $m['erro']];
    if ($m['de'] === 'ela' && empty($m['lida'])) $conversas[$cid]['lidas'] = false;
  }
  $compromissos = array_map(fn($x) => ['id' => (int)$x['id'], 'dia' => $x['dia'], 'hora' => $x['hora'], 'titulo' => $x['titulo'], 'tipo' => $x['tipo'], 'sub' => $x['sub'], 'obs' => $x['obs'], 'feito' => !empty($x['feito'])], $db->query('SELECT * FROM compromissos ORDER BY dia, hora')->fetchAll());
  $vendasHoje = $db->prepare('SELECT COUNT(*) n, COALESCE(SUM(horas),0) h, COALESCE(SUM(valor),0) v FROM vendas WHERE quando > ?'); $vendasHoje->execute([$hojeMenos]); $vh = $vendasHoje->fetch();
  $equipe = $eu['papel'] === 'atendente' ? [] : array_map(fn($u) => ['id' => (int)$u['id'], 'nome' => $u['nome'], 'login' => $u['login'], 'papel' => $u['papel']], $db->query('SELECT id, nome, login, papel FROM usuarios WHERE ativo = 1 ORDER BY nome')->fetchAll());
  require_once __DIR__ . '/idface-lib.php';
  return ['criancas' => array_values($criancas), 'registros' => $registros, 'conversas' => array_values($conversas), 'compromissos' => $compromissos, 'regra' => regra(),
    'vendas_hoje' => ['n' => (int)$vh['n'], 'horas' => (int)$vh['h'], 'valor' => (float)$vh['v']], 'equipe' => $equipe, 'eu' => $eu, 'versao' => PLAY_VERSAO,
    'idface' => idfaceEstado(), 'whats' => whatsResumo(), 'agora' => agora(), 'exemplo' => ($ex = cfg('exemplo', null)) && is_array($ex) ? count($ex['criancas'] ?? []) : 0];
}
function whatsResumo(): array { $c = whatsCfg(); return ['conectado' => whatsPronto($c), 'numero' => $c['numero'], 'nome' => $c['nome'], 'configurado' => $c['chave'] !== '']; }
function podeAjustar(array $u): void { if ($u['papel'] !== 'gestor') falha('Só o gestor mexe nos ajustes.', 403); }
function textoCriancaBasico(array &$in, array $atual = []): array {
  $nome = trim((string)($in['nome'] ?? $atual['nome'] ?? '')); if ($nome === '') falha('Falta o nome da criança.');
  return ['nome' => mb_substr($nome, 0, 60), 'idade' => max(0, min(17, (int)($in['idade'] ?? $atual['idade'] ?? 0))), 'resp' => mb_substr(trim((string)($in['resp'] ?? $atual['resp'] ?? '')), 0, 80),
    'rel' => mb_substr(trim((string)($in['rel'] ?? $atual['rel'] ?? 'responsável')), 0, 20), 'tel' => mb_substr(trim((string)($in['tel'] ?? $atual['tel'] ?? '')), 0, 30),
    'cpf' => preg_replace('/[^\d.\-]/', '', (string)($in['cpf'] ?? $atual['cpf'] ?? '')), 'obs' => mb_substr(trim((string)($in['obs'] ?? $atual['obs'] ?? '')), 0, 200)];
}

switch ($acao) {
  /* ---------- entrar e sair ---------- */
  case 'estado': {
    $n = (int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    responder(['primeiro_acesso' => $n === 0, 'usuario' => eu(), 'versao' => PLAY_VERSAO]);
  }
  case 'criar_gestor': { // só quando ainda não existe ninguém
    if ((int)$db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) falha('O primeiro gestor já foi criado. Entre com o login dele.', 409);
    $nome = $val('nome'); $login = mb_strtolower($val('login')); $senha = (string)($in['senha'] ?? '');
    if ($nome === '' || $login === '' || strlen($senha) < 6) falha('Preencha nome, login e uma senha com pelo menos 6 caracteres.');
    $db->prepare("INSERT INTO usuarios (nome, login, senha, papel, criado_em) VALUES (?, ?, ?, 'gestor', ?)")->execute([$nome, $login, password_hash($senha, PASSWORD_DEFAULT), agora()]);
    session_regenerate_id(true); $_SESSION['uid'] = (int)$db->lastInsertId();
    responder(['usuario' => eu()]);
  }
  case 'entrar': {
    $login = mb_strtolower($val('login')); $senha = (string)($in['senha'] ?? '');
    $db->prepare('DELETE FROM tentativas WHERE quando < ?')->execute([time() - 900]);
    $q = $db->prepare('SELECT COUNT(*) FROM tentativas WHERE (login = ? OR ip = ?) AND quando > ?'); $q->execute([$login, $ip, time() - 900]);
    if ((int)$q->fetchColumn() >= 8) falha('Muitas tentativas. Espere 15 minutos e tente de novo.', 429);
    $q = $db->prepare('SELECT * FROM usuarios WHERE login = ? AND ativo = 1'); $q->execute([$login]); $u = $q->fetch();
    if (!$u || !password_verify($senha, $u['senha'])) { $db->prepare('INSERT INTO tentativas (login, ip, quando) VALUES (?, ?, ?)')->execute([$login, $ip, time()]); usleep(400000); falha('Login ou senha errados.', 401); }
    session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id'];
    responder(['usuario' => eu()]);
  }
  case 'sair': { $_SESSION = []; session_destroy(); responder(); }
  case 'trocar_senha': {
    $u = exigir(); $atual = (string)($in['atual'] ?? ''); $nova = (string)($in['nova'] ?? '');
    $q = $db->prepare('SELECT senha FROM usuarios WHERE id = ?'); $q->execute([$u['id']]);
    if (!password_verify($atual, (string)$q->fetchColumn())) falha('A senha atual não confere.');
    if (strlen($nova) < 6) falha('A senha nova precisa ter pelo menos 6 caracteres.');
    $db->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $u['id']]);
    responder();
  }

  /* ---------- tudo que a tela mostra ---------- */
  case 'dados': { $u = exigir(); session_write_close(); responder(dados($u)); }
  case 'pulso': { // barato: muda quando qualquer coisa que a tela mostra mudou; a tela pergunta a cada 2 s e só recarrega se mudou
    exigir(); session_write_close();
    $a = $db->query("SELECT COALESCE(MAX(id), 0) || ':' || COUNT(*) FROM registros")->fetchColumn();
    $b = $db->query("SELECT COALESCE(MAX(id), 0) || ':' || COUNT(*) || ':' || COALESCE(SUM(lida), 0) FROM mensagens")->fetchColumn();
    $c = $db->query("SELECT COUNT(*) || ':' || COALESCE(SUM(pacote_min), 0) || ':' || COALESCE(SUM(usado_min), 0) || ':' || COALESCE(SUM(liberado), 0) || ':' || COALESCE(SUM(rosto), 0) || ':' || COALESCE(SUM(ativo), 0) || ':' || COALESCE(SUM(entrou IS NOT NULL), 0) || ':' || COALESCE(MAX(entrou), '') || ':' || COALESCE(SUM(idface_id), 0) || ':' || COALESCE(SUM(LENGTH(foto) + LENGTH(nome) + LENGTH(resp) + LENGTH(tel) + LENGTH(obs) + idade), 0) FROM criancas")->fetchColumn();
    $d = $db->query("SELECT COUNT(*) || ':' || COALESCE(SUM(feito), 0) || ':' || COALESCE(MAX(id), 0) || ':' || COALESCE(SUM(LENGTH(titulo) + LENGTH(dia) + LENGTH(hora)), 0) FROM compromissos")->fetchColumn();
    $ps = cfg('push_estado', []); $pe = cfg('ponte_estado', []); $em = (is_array($ps) ? ($ps['em'] ?? null) : null) ?: (is_array($pe) ? ($pe['em'] ?? null) : null);
    $ap = ($em && time() - strtotime((string)$em) < 120) ? 1 : 0; $ic = cfg('idface', []); $lig = !is_array($ic) || !array_key_exists('ligado', $ic) || $ic['ligado'] ? 1 : 0;
    responder(['p' => md5("$a|$b|$c|$d|$ap|$lig|" . json_encode(whatsResumo()) . '|' . json_encode(regra()) . '|' . (cfg('exemplo') ? 1 : 0))]);
  }

  /* ---------- crianças ---------- */
  case 'crianca_salvar': {
    $u = exigir(); $id = (int)($in['id'] ?? 0);
    if ($id) {
      $c = crianca($id); if (!$c) falha('Criança não encontrada.', 404);
      $d = textoCriancaBasico($in, $c);
      $db->prepare('UPDATE criancas SET nome = ?, idade = ?, resp = ?, rel = ?, tel = ?, cpf = ?, obs = ? WHERE id = ?')->execute([$d['nome'], $d['idade'], $d['resp'], $d['rel'], $d['tel'], $d['cpf'], $d['obs'], $id]);
      // o nome mudou: o aparelho recebe o nome novo
      if ($c['idface_id'] !== null && $c['nome'] !== $d['nome']) gravarCfg('renomear_' . $id, (int)$c['idface_id']);
      responder(['id' => $id]);
    }
    $d = textoCriancaBasico($in);
    if ($d['resp'] === '') falha('Falta o nome de quem é responsável.');
    $pac = (array)($in['pacote'] ?? []); $horas = max(0, (int)($pac['h'] ?? 0)); $valor = (float)($pac['v'] ?? 0);
    $foto = (string)($in['foto'] ?? ''); if ($foto !== '' && !preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $foto)) $foto = '';
    if (strlen($foto) > 1500000) falha('A foto ficou grande demais. Tire outra.');
    $db->prepare('INSERT INTO criancas (nome, idade, resp, rel, tel, cpf, obs, pacote_min, usado_min, foto, foto_origem, rosto, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?)')->execute([$d['nome'], $d['idade'], $d['resp'], $d['rel'], $d['tel'], $d['cpf'], $d['obs'], $foto, $foto !== '' ? 'celular' : '', $foto !== '' ? 1 : 0, agora()]);
    $id = (int)$db->lastInsertId(); $c = crianca($id);
    if ($horas > 0) fazerVenda($c, $horas, $valor, (int)$u['id']);
    gravarCfg('mandar_' . $id, $id); // vai para o iDFace (nome agora, foto quando o aparelho confirmar o cadastro)
    responder(['id' => $id]);
  }
  case 'crianca_apagar': {
    $u = exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $db->prepare('UPDATE criancas SET ativo = 0, idface_id = NULL WHERE id = ?')->execute([$c['id']]);
    if ($c['idface_id'] !== null) gravarCfg('apagar_' . (int)$c['idface_id'], (int)$c['idface_id']);
    apagarCfg('mandar_' . $c['id']); apagarCfg('foto_' . $c['id']);
    responder();
  }
  case 'foto_salvar': {
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $foto = (string)($in['foto'] ?? ''); if (!preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $foto)) falha('Foto inválida.');
    if (strlen($foto) > 1500000) falha('A foto ficou grande demais. Tire outra.');
    $db->prepare("UPDATE criancas SET foto = ?, foto_origem = 'celular', rosto = 1 WHERE id = ?")->execute([$foto, $c['id']]);
    if ($c['idface_id'] !== null) gravarCfg('foto_' . $c['id'], (int)$c['idface_id']); else gravarCfg('mandar_' . $c['id'], (int)$c['id']);
    responder();
  }
  case 'entrada': { // à mão: só gestor e supervisor, para corrigir; o normal é o rosto no iDFace
    exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    if ($c['entrou']) falha($c['nome'] . ' já está no espaço.');
    responder(fazerEntrada($c, 'mão'));
  }
  case 'saida': {
    exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    if (!$c['entrou']) falha($c['nome'] . ' não está no espaço.');
    responder(fazerSaida($c, 'mão'));
  }
  case 'desfazer_entrada': { // volta a criança para fora e apaga o registro e o aviso
    exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['entrou']) falha('Nada para desfazer.');
    $db->prepare('UPDATE criancas SET entrou = NULL WHERE id = ?')->execute([$c['id']]);
    if (!empty($in['registro'])) $db->prepare('DELETE FROM registros WHERE id = ? AND crianca_id = ?')->execute([(int)$in['registro'], $c['id']]);
    if (!empty($in['mensagem'])) $db->prepare('DELETE FROM mensagens WHERE id = ? AND crianca_id = ?')->execute([(int)$in['mensagem'], $c['id']]);
    responder();
  }
  case 'desfazer_saida': {
    exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Nada para desfazer.');
    $q = $db->prepare('SELECT * FROM visitas WHERE id = ? AND crianca_id = ?'); $q->execute([(int)($in['visita'] ?? 0), $c['id']]); $v = $q->fetch(); if (!$v) falha('Nada para desfazer.');
    $db->prepare('UPDATE criancas SET entrou = ?, usado_min = MAX(0, usado_min - ?) WHERE id = ?')->execute([$v['entrou'], (int)$v['debitado_min'], $c['id']]);
    $db->prepare('DELETE FROM visitas WHERE id = ?')->execute([$v['id']]);
    if (!empty($in['registro'])) $db->prepare('DELETE FROM registros WHERE id = ? AND crianca_id = ?')->execute([(int)$in['registro'], $c['id']]);
    if (!empty($in['mensagem'])) $db->prepare('DELETE FROM mensagens WHERE id = ? AND crianca_id = ?')->execute([(int)$in['mensagem'], $c['id']]);
    responder();
  }
  case 'vender': {
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $horas = (int)($in['horas'] ?? 0); $valor = (float)($in['valor'] ?? 0); if ($horas <= 0) falha('Quantas horas?');
    responder(fazerVenda($c, $horas, $valor, (int)$u['id']));
  }
  case 'desfazer_venda': {
    exigir(); $q = $db->prepare('SELECT * FROM vendas WHERE id = ?'); $q->execute([(int)($in['venda'] ?? 0)]); $v = $q->fetch(); if (!$v) falha('Nada para desfazer.');
    $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$v['horas'] * 60, $v['crianca_id']]);
    $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([$v['id']]);
    if (!empty($in['registro'])) $db->prepare('DELETE FROM registros WHERE id = ?')->execute([(int)$in['registro']]);
    responder();
  }

  /* ---------- WhatsApp: conversa e conexão ---------- */
  case 'mensagem_enviar': {
    $u = exigir(); $c = crianca((int)($in['crianca'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $texto = trim((string)($in['texto'] ?? '')); if ($texto === '') falha('Escreva a mensagem.');
    $r = mandarMensagem($c, mb_substr($texto, 0, 4000), false, '', $u['nome']);
    $db->prepare("UPDATE mensagens SET lida = 1 WHERE crianca_id = ? AND de = 'ela'")->execute([$c['id']]);
    responder($r);
  }
  case 'mensagens_lidas': { exigir(); $db->prepare("UPDATE mensagens SET lida = 1 WHERE crianca_id = ? AND de = 'ela'")->execute([(int)($in['crianca'] ?? 0)]); responder(); }
  case 'whats_config': { $u = exigir(['gestor', 'supervisor']); responder(['config' => whatsPublico(whatsCfg())]); }
  case 'whats_salvar': {
    $u = exigir(); podeAjustar($u); $c = whatsCfg();
    $url = $val('url'); if ($url !== '' && !preg_match('#^https?://#', $url)) falha('O endereço da Evolution API precisa começar com http:// ou https://.');
    if ($url !== '') $c['url'] = rtrim($url, '/');
    $inst = preg_replace('/[^A-Za-z0-9_-]/', '', $val('instancia')); if ($inst !== '') $c['instancia'] = $inst;
    $chave = $val('chave'); if ($chave !== '' && strpos($chave, '•') === false) $c['chave'] = $chave;
    gravarCfg('whats', $c); responder(['config' => whatsPublico($c)]);
  }
  case 'whats_qr': { // o QR Code para escanear no celular da loja
    $u = exigir(); podeAjustar($u); $c = whatsCfg(); if ($c['chave'] === '') falha('Antes, salve a chave (apikey) da Evolution API.');
    $r = evoQr($c);
    if ($r['estado'] === 'conectado') { $i = evoInfo($c); $c['numero'] = $i['numero'] ?? $c['numero']; $c['nome'] = $i['nome'] ?? $c['nome']; $c['conectado_em'] = $c['conectado_em'] ?: agora(); gravarCfg('whats', $c); try { evoWebhook($c); } catch (Throwable $e) { registrarErro($e->getMessage()); } }
    responder($r + ['config' => whatsPublico($c)]);
  }
  case 'whats_status': {
    $u = exigir(['gestor', 'supervisor']); $c = whatsCfg(); if ($c['chave'] === '') responder(['estado' => 'sem_chave', 'config' => whatsPublico($c)]);
    try { $r = evoEstado($c); } catch (Throwable $e) { responder(['estado' => 'erro', 'erro' => $e->getMessage(), 'config' => whatsPublico($c)]); }
    if ($r['estado'] === 'conectado') { if (!$c['conectado_em'] || $c['numero'] === '') { $i = evoInfo($c); $c['numero'] = $i['numero'] ?? $c['numero']; $c['nome'] = $i['nome'] ?? $c['nome']; $c['conectado_em'] = $c['conectado_em'] ?: agora(); gravarCfg('whats', $c); try { evoWebhook($c); } catch (Throwable $e) { registrarErro($e->getMessage()); } } }
    elseif ($c['conectado_em']) { $c['conectado_em'] = null; gravarCfg('whats', $c); }
    responder($r + ['config' => whatsPublico($c)]);
  }
  case 'whats_desconectar': {
    $u = exigir(); podeAjustar($u); $c = whatsCfg();
    try { evoSair($c); } catch (Throwable $e) { /* já estava fora */ }
    $c['conectado_em'] = null; $c['numero'] = ''; $c['nome'] = ''; gravarCfg('whats', $c); responder(['config' => whatsPublico($c)]);
  }

  /* ---------- agenda ---------- */
  case 'comp_feito': { exigir(); $db->prepare('UPDATE compromissos SET feito = ? WHERE id = ?')->execute([empty($in['feito']) ? 0 : 1, (int)($in['id'] ?? 0)]); responder(); }
  case 'comp_salvar': {
    exigir(); $id = (int)($in['id'] ?? 0); $titulo = mb_substr($val('titulo'), 0, 120); if ($titulo === '') falha('Falta dizer o quê.');
    $dia = $val('dia'); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) falha('Dia inválido.');
    $hora = $val('hora', '15:00'); if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) $hora = '15:00';
    $tipo = in_array($val('tipo'), ['oficina', 'festa', 'compromisso', 'postagem'], true) ? $val('tipo') : 'oficina';
    $subs = ['oficina' => 'Oficina', 'festa' => 'Festa', 'compromisso' => 'Compromisso', 'postagem' => 'Postagem'];
    if ($id) $db->prepare('UPDATE compromissos SET dia = ?, hora = ?, titulo = ?, tipo = ?, sub = ?, obs = ? WHERE id = ?')->execute([$dia, $hora, $titulo, $tipo, $subs[$tipo], mb_substr($val('obs'), 0, 500), $id]);
    else { $db->prepare('INSERT INTO compromissos (dia, hora, titulo, tipo, sub, obs, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$dia, $hora, $titulo, $tipo, $subs[$tipo], mb_substr($val('obs'), 0, 500), agora()]); $id = (int)$db->lastInsertId(); }
    responder(['id' => $id]);
  }
  case 'comp_apagar': { exigir(); $db->prepare('DELETE FROM compromissos WHERE id = ?')->execute([(int)($in['id'] ?? 0)]); responder(); }

  /* ---------- ajustes: a regra das horas e os pacotes ---------- */
  case 'regra_salvar': {
    $u = exigir(); podeAjustar($u); $r = regra();
    foreach (['arredonda' => [1, 15, 30, 60], 'cortesia' => [0, 5, 10, 15], 'minimo' => [0, 30, 60], 'aviso' => [30, 60, 120]] as $k => $ops) if (isset($in[$k]) && in_array((int)$in[$k], $ops, true)) $r[$k] = (int)$in[$k];
    if (isset($in['autoMsg']) && in_array($in['autoMsg'], ['ambos', 'entrada', 'nao'], true)) $r['autoMsg'] = $in['autoMsg'];
    if (isset($in['bloqueioAuto']) && in_array($in['bloqueioAuto'], ['sim', 'nao'], true)) $r['bloqueioAuto'] = $in['bloqueioAuto'];
    if (isset($in['pacotes']) && is_array($in['pacotes'])) {
      $ps = []; foreach ($in['pacotes'] as $p) { $h = (int)($p['h'] ?? 0); $v = (float)($p['v'] ?? 0); if ($h > 0 && $v >= 0) $ps[$h] = ['h' => $h, 'v' => $v]; }
      if (!$ps) falha('Deixe pelo menos um pacote.'); ksort($ps); $r['pacotes'] = array_values($ps);
    }
    gravarCfg('regra', $r); responder(['regra' => $r]);
  }

  /* ---------- equipe ---------- */
  case 'usuario_salvar': {
    $u = exigir(); podeAjustar($u); $id = (int)($in['id'] ?? 0);
    $nome = mb_substr($val('nome'), 0, 80); $login = mb_strtolower(preg_replace('/[^a-z0-9._-]/i', '', $val('login'))); $papel = in_array($val('papel'), array_keys(PAPEIS), true) ? $val('papel') : 'atendente'; $senha = (string)($in['senha'] ?? '');
    if ($nome === '' || $login === '') falha('Preencha o nome e o login.');
    $q = $db->prepare('SELECT id FROM usuarios WHERE login = ? AND id <> ?'); $q->execute([$login, $id]); if ($q->fetchColumn()) falha('Já existe alguém com esse login.');
    if ($id) {
      if ($id === (int)$u['id'] && $papel !== 'gestor') falha('Você não pode tirar o seu próprio papel de gestor.');
      $db->prepare('UPDATE usuarios SET nome = ?, login = ?, papel = ? WHERE id = ?')->execute([$nome, $login, $papel, $id]);
      if ($senha !== '') { if (strlen($senha) < 6) falha('A senha precisa ter pelo menos 6 caracteres.'); $db->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), $id]); }
    } else {
      if (strlen($senha) < 6) falha('Defina uma senha inicial com pelo menos 6 caracteres.');
      $db->prepare('INSERT INTO usuarios (nome, login, senha, papel, criado_em) VALUES (?, ?, ?, ?, ?)')->execute([$nome, $login, password_hash($senha, PASSWORD_DEFAULT), $papel, agora()]); $id = (int)$db->lastInsertId();
    }
    responder(['id' => $id]);
  }
  case 'usuario_apagar': {
    $u = exigir(); podeAjustar($u); $id = (int)($in['id'] ?? 0); if ($id === (int)$u['id']) falha('Você não pode remover a si mesmo.');
    $db->prepare('UPDATE usuarios SET ativo = 0 WHERE id = ?')->execute([$id]); responder();
  }

  /* ---------- iDFace ---------- */
  case 'idface_salvar': {
    $u = exigir(); podeAjustar($u); require_once __DIR__ . '/idface-lib.php';
    $c = idfaceCfg();
    if (isset($in['ip'])) $c['ip'] = mb_substr(trim((string)$in['ip']), 0, 80);
    if (isset($in['usuario'])) $c['usuario'] = mb_substr(trim((string)$in['usuario']), 0, 40) ?: 'admin';
    if (isset($in['nome'])) $c['nome'] = mb_substr(trim((string)$in['nome']), 0, 40) ?: 'Recepção';
    if (isset($in['ligado'])) $c['ligado'] = (bool)$in['ligado'];
    gravarCfg('idface', $c); responder(['idface' => idfaceEstado()]);
  }
  case 'idface_nova_chave': { $u = exigir(); podeAjustar($u); require_once __DIR__ . '/idface-lib.php'; gravarCfg('ponte_chave', bin2hex(random_bytes(20))); responder(['idface' => idfaceEstado()]); }
  case 'crianca_liberar': { // a chavinha: liberada, a criança bate o rosto e as horas correm; bloqueada, o iDFace recusa
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $lib = empty($in['liberado']) ? 0 : 1; bloquearAcesso($c, (bool)$lib);
    responder(['liberado' => (bool)$lib]);
  }
  case 'idface_rosto': { // o aparelho vai pedir o rosto da criança ("olhe para a câmera"); fica na fila até o aparelho buscar
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    require_once __DIR__ . '/idface-lib.php'; $e = idfaceEstado(); if (!$e['ligado']) falha('O iDFace está desligado em Ajustes.'); if (!$e['ativo']) falha('O iDFace está sem contato com o site agora. Confira o aparelho e tente de novo.');
    apagarCfg('rosto_erro_' . $c['id']); apagarCfg('rosto_ok_' . $c['id']); apagarCfg('rosto_em_' . $c['id']);
    if ($c['idface_id'] === null) gravarCfg('mandar_' . $c['id'], (int)$c['id']);
    gravarCfg('pedirosto_' . $c['id'], (int)$c['id']); responder();
  }
  case 'idface_rosto_estado': { // a folha "olhe para a câmera" pergunta a cada 2 s
    exigir(); $id = (int)($in['id'] ?? 0); $c = crianca($id); if (!$c) falha('Criança não encontrada.', 404);
    $ps = cfg('push_estado', []); $fly = is_array($ps) ? ($ps['inflight'] ?? null) : null;
    $fase = cfg('rosto_ok_' . $id) ? 'ok' : (cfg('rosto_erro_' . $id) ? 'erro' : (is_array($fly) && ($fly['tipo'] ?? '') === 'rosto' && (int)($fly['crianca'] ?? 0) === $id ? 'capturando' : (cfg('pedirosto_' . $id) ? ($c['idface_id'] === null ? 'criando' : 'fila') : 'nada')));
    responder(['fase' => $fase, 'erro' => (string)cfg('rosto_erro_' . $id, ''), 'rosto' => !empty($c['rosto'])]);
  }
  case 'idface_mandar': { // manda (ou manda de novo) a criança para o aparelho
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    if ($c['idface_id'] !== null && $c['foto'] !== '') gravarCfg('foto_' . $c['id'], (int)$c['idface_id']); else gravarCfg('mandar_' . $c['id'], (int)$c['id']);
    responder();
  }
  case 'exemplos_carregar': { // 10 crianças de exemplo, 5 no espaço, conversas e agenda, para conhecer o sistema
    $u = exigir(); podeAjustar($u); require_once __DIR__ . '/exemplos.php'; responder(exemplosCarregar((int)$u['id']));
  }
  case 'exemplos_apagar': { $u = exigir(); podeAjustar($u); require_once __DIR__ . '/exemplos.php'; exemplosApagar(); responder(); }
  case 'idface_simular': { // para testar sem o aparelho: como se a criança tivesse olhado para a câmera
    $u = exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    if ($c['entrou']) responder(['resultado' => 'saída'] + fazerSaida($c, 'simulação'));
    responder(['resultado' => 'entrada'] + fazerEntrada($c, 'simulação'));
  }
  default: falha('Ação desconhecida: ' . $acao, 404);
}
