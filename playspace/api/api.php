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
    'rosto' => !empty($c['rosto']) || $c['idface_id'] !== null, 'foto' => (string)$c['foto'], 'ativo' => (bool)$c['ativo'], 'liberado' => !isset($c['liberado']) || (bool)$c['liberado'], 'sapateira' => isset($c['sapateira']) && $c['sapateira'] !== null ? (int)$c['sapateira'] : null,
    'doc' => ($c['doc_tipo'] ?? 'cpf') === 'rne' ? 'rne' : 'cpf', 'tel2' => (string)($c['tel2'] ?? ''), 'pmin' => (int)$c['pacote_min'], 'pcd' => !empty($c['pcd'])];   // pmin: o tempo comprado em minutos exatos   // doc: o campo cpf guarda o CPF ou o RNE (estrangeiro)
}
function dados(array $eu): array {
  $db = db(); $hojeMenos = date('Y-m-d H:i:s', time() - 86400);
  acertarTodas();   // quem passou do tempo comprado mais a tolerância já aparece com a nova cobrança
  $criancas = array_map('criancaPublica', $db->query('SELECT * FROM criancas WHERE ativo = 1 ORDER BY nome')->fetchAll());
  $visitas = $db->query('SELECT * FROM visitas ORDER BY id DESC LIMIT 400')->fetchAll();
  $porCrianca = []; foreach ($visitas as $v) $porCrianca[(int)$v['crianca_id']][] = ['id' => (int)$v['id'], 'dia' => $v['saiu'], 'entrou' => substr($v['entrou'], 11, 5), 'saiu' => substr($v['saiu'], 11, 5), 'deb' => (int)$v['debitado_min']];
  $ultimas = []; foreach ($db->query('SELECT v.crianca_id, v.minutos, v.valor, v.quando, v.itens, v.servico FROM vendas v JOIN (SELECT crianca_id, MAX(id) AS m FROM vendas WHERE minutos > 0 GROUP BY crianca_id) u ON u.m = v.id')->fetchAll() as $v) $ultimas[(int)$v['crianca_id']] = ['h' => round((int)$v['minutos'] / 60, 2), 'm' => (int)$v['minutos'], 'v' => (float)$v['valor'], 'quando' => $v['quando'], 'com' => (string)$v['itens'], 'servico' => (string)$v['servico']];
  // as cobranças por atraso da visita de quem está no espaço (quantas e quanto falta receber na saída)
  $atrasos = []; foreach ($db->query('SELECT v.crianca_id, COUNT(*) n, SUM(v.valor) t FROM vendas v JOIN criancas c ON c.id = v.crianca_id WHERE v.auto = 1 AND c.entrou IS NOT NULL AND v.quando >= c.entrou GROUP BY v.crianca_id')->fetchAll() as $a) $atrasos[(int)$a['crianca_id']] = ['n' => (int)$a['n'], 'valor' => (float)$a['t']];
  // uv: a última linha de venda de cada criança (entre as 2000 linhas mais novas do dia a dia). A tela usa para saber que um atendimento que ficou em aberto num aparelho já foi registrado em outro
  $uv = []; foreach ($db->query("SELECT crianca_id, MAX(id) m FROM registros WHERE id > (SELECT COALESCE(MAX(id), 0) - 2000 FROM registros) AND tipo = 'venda' AND crianca_id IS NOT NULL GROUP BY crianca_id")->fetchAll() as $l) $uv[(int)$l['crianca_id']] = (int)$l['m'];
  // vouch: a criança tem um voucher de hoje sem saída depois dele (o da visita em curso, ou um guardado): a tela mostra e não oferece outro
  $vou = []; $qv = $db->prepare("SELECT DISTINCT v.crianca_id FROM vendas v WHERE v.voucher != '' AND v.quando >= ? AND v.quando > COALESCE((SELECT MAX(x.saiu) FROM visitas x WHERE x.crianca_id = v.crianca_id), '')"); $qv->execute([date('Y-m-d') . ' 00:00:00']);
  foreach ($qv->fetchAll() as $l) $vou[(int)$l['crianca_id']] = true;
  foreach ($criancas as &$c) { $c['visitas'] = $porCrianca[$c['id']] ?? []; $c['venda'] = $ultimas[$c['id']] ?? null; $c['atraso'] = $atrasos[$c['id']] ?? null; $c['uv'] = $uv[$c['id']] ?? 0; $c['vouch'] = isset($vou[$c['id']]); if (ehExemplo($c['id'])) $c['teste'] = true; }   // teste: criança dos dados de exemplo (o botão do rodapé)
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
  $vendasHoje = $db->prepare('SELECT COUNT(*) n, COALESCE(SUM(minutos),0) m, COALESCE(SUM(valor),0) v FROM vendas WHERE quando > ?'); $vendasHoje->execute([$hojeMenos]); $vh = $vendasHoje->fetch();   // tudo o que foi vendido nas últimas 24 h; m = minutos de brincar
  $equipe = $eu['papel'] === 'atendente' ? [] : array_map(fn($u) => ['id' => (int)$u['id'], 'nome' => $u['nome'], 'login' => $u['login'], 'papel' => $u['papel']], $db->query('SELECT id, nome, login, papel FROM usuarios WHERE ativo = 1 ORDER BY nome')->fetchAll());
  $q = $db->prepare('SELECT COUNT(*) n, COALESCE(SUM(valor), 0) v FROM vendas WHERE auto = 1 AND quando > ?'); $q->execute([$hojeMenos]); $ah = $q->fetch(); $atrasoHoje = ['n' => (int)$ah['n'], 'valor' => (float)$ah['v']];   // cobranças por atraso das últimas 24 h
  $q = $db->prepare("SELECT COALESCE(SUM(voucher != ''), 0) nv, COALESCE(SUM(CASE WHEN voucher != '' THEN cheio ELSE 0 END), 0) vv, COALESCE(SUM(pcd = 1), 0) np, COALESCE(SUM(CASE WHEN pcd = 1 THEN cheio - valor ELSE 0 END), 0) vp FROM vendas WHERE quando > ?"); $q->execute([$hojeMenos]); $dh = $q->fetch();
  $descontosHoje = ['vouchers' => (int)$dh['nv'], 'voucher_valor' => (float)$dh['vv'], 'pcd' => (int)$dh['np'], 'pcd_valor' => (float)$dh['vp']];   // o que saiu por voucher e com desconto PCD nas últimas 24 h
  require_once __DIR__ . '/idface-lib.php';
  // as entradas canceladas sem cobrar: só gestor e supervisor recebem a lista (são eles que conferem o motivo)
  $canc = $eu['papel'] === 'atendente' ? [] : array_map('cancelamentoPublico', $db->query('SELECT * FROM cancelamentos ORDER BY id DESC LIMIT 80')->fetchAll());
  // cadastro feito hoje e o atendimento ainda por fazer: sem venda, sem visita, sem entrada cancelada e fora do espaço.
  // A tela mostra em "Falta finalizar" e, ao tocar na criança, abre direto o atendimento (continua de onde parou).
  $q = $db->prepare('SELECT c.id, c.criado_em FROM criancas c WHERE c.ativo = 1 AND c.entrou IS NULL AND c.criado_em >= ? AND NOT EXISTS (SELECT 1 FROM vendas v WHERE v.crianca_id = c.id)
    AND NOT EXISTS (SELECT 1 FROM visitas x WHERE x.crianca_id = c.id) AND NOT EXISTS (SELECT 1 FROM cancelamentos k WHERE k.crianca_id = c.id) ORDER BY c.criado_em, c.id');
  $q->execute([date('Y-m-d') . ' 00:00:00']); $fora = pendFora();   // quem a equipe tirou da lista hoje ("não vai fazer agora") não volta
  $pend = array_values(array_filter(array_map(fn($p) => ['id' => (int)$p['id'], 'em' => $p['criado_em']], $q->fetchAll()), fn($p) => !isset($fora[$p['id']])));
  return ['criancas' => array_values($criancas), 'registros' => $registros, 'conversas' => array_values($conversas), 'compromissos' => $compromissos, 'regra' => regra(), 'cancelamentos' => $canc, 'pendentes' => $pend,
    'vendas_hoje' => ['n' => (int)$vh['n'], 'min' => (int)$vh['m'], 'horas' => round((int)$vh['m'] / 60, 2), 'valor' => (float)$vh['v']], 'atraso_hoje' => $atrasoHoje, 'descontos_hoje' => $descontosHoje, 'equipe' => $equipe, 'eu' => $eu, 'versao' => PLAY_VERSAO,
    'idface' => idfaceEstado(), 'whats' => whatsResumo(), 'agora' => agora(), 'exemplo' => ($ex = cfg('exemplo', null)) && is_array($ex) ? count($ex['criancas'] ?? []) : 0];
}
// quem a equipe tirou de "Falta finalizar" hoje: [id da criança => dia]. O que é de outro dia não conta mais
function pendFora(): array { $f = cfg('pend_fora', []); $hoje = date('Y-m-d'); return is_array($f) ? array_filter($f, fn($d) => $d === $hoje) : []; }
function whatsResumo(): array { $c = whatsCfg(); return ['conectado' => whatsPronto($c), 'numero' => $c['numero'], 'nome' => $c['nome'], 'configurado' => $c['chave'] !== '']; }
function podeAjustar(array $u): void { if ($u['papel'] !== 'gestor') falha('Só o gestor mexe nos ajustes.', 403); }
function textoCriancaBasico(array &$in, array $atual = []): array {
  $nome = trim((string)($in['nome'] ?? $atual['nome'] ?? '')); if ($nome === '') falha('Falta o nome da criança.');
  // o documento do responsável: CPF (só números, ponto e traço) ou RNE de estrangeiro (letras e números)
  $doc = ($in['doc'] ?? $atual['doc_tipo'] ?? 'cpf') === 'rne' ? 'rne' : 'cpf'; $bruto = (string)($in['cpf'] ?? $atual['cpf'] ?? '');
  $numDoc = $doc === 'rne' ? mb_substr(mb_strtoupper(trim(preg_replace('/[^A-Za-z0-9.\-\/ ]/', '', $bruto))), 0, 20) : preg_replace('/[^\d.\-]/', '', $bruto);
  return ['doc' => $doc, 'tel2' => mb_substr(trim((string)($in['tel2'] ?? $atual['tel2'] ?? '')), 0, 30),'nome' => mb_substr($nome, 0, 60), 'idade' => max(0, min(17, (int)($in['idade'] ?? $atual['idade'] ?? 0))), 'resp' => mb_substr(trim((string)($in['resp'] ?? $atual['resp'] ?? '')), 0, 80),
    'rel' => mb_substr(trim((string)($in['rel'] ?? $atual['rel'] ?? 'responsável')), 0, 20), 'tel' => mb_substr(trim((string)($in['tel'] ?? $atual['tel'] ?? '')), 0, 30),
    'cpf' => $numDoc, 'obs' => mb_substr(trim((string)($in['obs'] ?? $atual['obs'] ?? '')), 0, 500), 'pcd' => empty($in['pcd'] ?? $atual['pcd'] ?? 0) ? 0 : 1];
}
function cpfValido(string $v): bool {
  $d = preg_replace('/\D/', '', $v); if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) return false;
  for ($n = 9; $n <= 10; $n++) { $t = 0; for ($k = 0; $k < $n; $k++) $t += (int)$d[$k] * ($n + 1 - $k); $r = ($t * 10) % 11; if ($r === 10) $r = 0; if ($r !== (int)$d[$n]) return false; }
  return true;
}
function cpfJaCliente(string $digitos): bool { // já existe criança ativa com este CPF (11 números)?
  if (strlen($digitos) !== 11) return false;
  $q = db()->prepare("SELECT 1 FROM criancas WHERE ativo = 1 AND doc_tipo != 'rne' AND REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ? LIMIT 1"); $q->execute([$digitos]); return (bool)$q->fetchColumn();
}
// O cadastro só fecha com: o documento do responsável (CPF válido, ou RNE), o nome dele, um telefone com DDD e o nome da criança.
// Ao editar, o que não foi mexido passa como está (cadastro antigo, de antes desta regra): só o que mudou precisa estar certo, e nada disso pode ser apagado.
function conferirCadastro(array $d, array $atual = []): void {
  $novo = !$atual; $so = fn(string $t) => preg_replace('/\D/', '', $t); $chave = fn(string $tipo, string $v) => $tipo === 'rne' ? preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($v)) : $so($v);
  $tipoAntes = ($atual['doc_tipo'] ?? 'cpf') === 'rne' ? 'rne' : 'cpf';
  if ($novo || $d['doc'] !== $tipoAntes || $chave($d['doc'], $d['cpf']) !== $chave($tipoAntes, (string)($atual['cpf'] ?? ''))) {
    if ($d['doc'] === 'rne') { if (strlen($chave('rne', $d['cpf'])) < 5) falha('Falta o RNE do responsável.'); }
    elseif ($so($d['cpf']) === '') falha('Falta o CPF do responsável (ou o RNE, se for estrangeiro).');
    elseif (!cpfValido($d['cpf']) && !cpfJaCliente($so($d['cpf']))) falha('Confira o CPF: os números não batem.');   // o documento de quem já é cliente passa como está (a irmã entra no mesmo cliente)
  }
  if ($d['resp'] === '' && ($novo || trim((string)($atual['resp'] ?? '')) !== '')) falha('Falta o nome do responsável.');
  if ($novo || $so($d['tel']) !== $so((string)($atual['tel'] ?? '')) || $so($d['tel2']) !== $so((string)($atual['tel2'] ?? ''))) {
    $maior = max(strlen($so($d['tel'])), strlen($so($d['tel2'])));
    if ($maior === 0) falha('Falta um telefone do responsável.'); if ($maior < 10) falha('Telefone incompleto: coloque o DDD e o número.');
  }
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
    acertarTodas();   // a cada pulso de qualquer tela aberta: passou da tolerância, nasce a nova cobrança (e o pulso muda, então todas as telas mostram)
    $a = $db->query("SELECT COALESCE(MAX(id), 0) || ':' || COUNT(*) FROM registros")->fetchColumn();
    $b = $db->query("SELECT COALESCE(MAX(id), 0) || ':' || COUNT(*) || ':' || COALESCE(SUM(lida), 0) FROM mensagens")->fetchColumn();
    $c = $db->query("SELECT COUNT(*) || ':' || COALESCE(SUM(pacote_min), 0) || ':' || COALESCE(SUM(usado_min), 0) || ':' || COALESCE(SUM(liberado), 0) || ':' || COALESCE(SUM(pcd), 0) || ':' || COALESCE(SUM(sapateira), 0) || ':' || COALESCE(SUM(sapateira IS NOT NULL), 0) || ':' || COALESCE(SUM(rosto), 0) || ':' || COALESCE(SUM(ativo), 0) || ':' || COALESCE(SUM(entrou IS NOT NULL), 0) || ':' || COALESCE(MAX(entrou), '') || ':' || COALESCE(SUM(idface_id), 0) || ':' || COALESCE(SUM(LENGTH(foto) + LENGTH(nome) + LENGTH(resp) + LENGTH(tel) + LENGTH(tel2) + LENGTH(cpf) + LENGTH(doc_tipo) + LENGTH(obs) + idade), 0) FROM criancas")->fetchColumn();
    $d = $db->query("SELECT COUNT(*) || ':' || COALESCE(SUM(feito), 0) || ':' || COALESCE(MAX(id), 0) || ':' || COALESCE(SUM(LENGTH(titulo) + LENGTH(dia) + LENGTH(hora)), 0) FROM compromissos")->fetchColumn();
    $ps = cfg('push_estado', []); $pe = cfg('ponte_estado', []); $em = (is_array($ps) ? ($ps['em'] ?? null) : null) ?: (is_array($pe) ? ($pe['em'] ?? null) : null);
    $ap = ($em && time() - strtotime((string)$em) < 120) ? 1 : 0; $ic = cfg('idface', []); $lig = !is_array($ic) || !array_key_exists('ligado', $ic) || $ic['ligado'] ? 1 : 0;
    $d .= '|' . $db->query("SELECT COUNT(*) || ':' || COALESCE(SUM(visto_em IS NOT NULL), 0) FROM cancelamentos")->fetchColumn();   // um cancelamento conferido pela supervisão também muda a tela
    responder(['p' => md5("$a|$b|$c|$d|$ap|$lig|" . json_encode(whatsResumo()) . '|' . json_encode(regra()) . '|' . (cfg('exemplo') ? 1 : 0) . '|' . json_encode(pendFora()) . '|' . date('Y-m-d'))]);   // quem saiu de "Falta finalizar" e a virada do dia também mudam a tela
  }

  /* ---------- crianças ---------- */
  case 'crianca_salvar': {
    $u = exigir(); $id = (int)($in['id'] ?? 0);
    if ($id) {
      $c = crianca($id); if (!$c) falha('Criança não encontrada.', 404);
      $d = textoCriancaBasico($in, $c); conferirCadastro($d, $c);
      $db->prepare('UPDATE criancas SET nome = ?, idade = ?, resp = ?, rel = ?, tel = ?, tel2 = ?, doc_tipo = ?, cpf = ?, obs = ?, pcd = ? WHERE id = ?')->execute([$d['nome'], $d['idade'], $d['resp'], $d['rel'], $d['tel'], $d['tel2'], $d['doc'], $d['cpf'], $d['obs'], $d['pcd'], $id]);
      // marcar (ou desmarcar) PCD muda o preço: fica anotado no dia, com quem fez
      if ((int)($c['pcd'] ?? 0) !== $d['pcd']) registrar('cad', $id, $d['nome'], ($d['pcd'] ? 'Marcada como criança PCD: ' . pcdDesc() . '% de desconto na recreação' : 'Deixou de estar marcada como criança PCD') . ' · por ' . primeiroNome($u['nome']));
      // o nome mudou: o aparelho recebe o nome novo
      if ($c['idface_id'] !== null && $c['nome'] !== $d['nome']) gravarCfg('renomear_' . $id, (int)$c['idface_id']);
      responder(['id' => $id]);
    }
    $d = textoCriancaBasico($in); conferirCadastro($d);
    if ($d['tel'] === '' && $d['tel2'] !== '') { $d['tel'] = $d['tel2']; $d['tel2'] = ''; }   // só um telefone: ele é o principal (é para onde vão os avisos)
    $pac = (array)($in['pacote'] ?? []); $horas = max(0, (int)($pac['h'] ?? 0)); $valor = (float)($pac['v'] ?? 0);
    $foto = (string)($in['foto'] ?? ''); if ($foto !== '' && !preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $foto)) $foto = '';
    if (strlen($foto) > 1500000) falha('A foto ficou grande demais. Tire outra.');
    $sap = max(0, (int)($in['sapateira'] ?? 0));   // a sapateira escolhida no cadastro (0 = nenhuma)
    if ($sap > (int)regra()['sapateiras']) falha('Essa sapateira não existe.');
    if ($sap && ($dono = donoSapateira($sap))) falha('A sapateira ' . $sap . ' já está com ' . $dono['nome'] . '. Escolha outra.');
    // o cadastro não vende mais o pacote: sem horas, o acesso nasce bloqueado (se a regra é bloquear sozinho) e a primeira venda libera
    $liberado = ($horas > 0 || regra()['bloqueioAuto'] !== 'sim') ? 1 : 0;
    $db->prepare('INSERT INTO criancas (nome, idade, resp, rel, tel, tel2, doc_tipo, cpf, obs, pacote_min, usado_min, foto, foto_origem, rosto, liberado, criado_em, pcd) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?)')->execute([$d['nome'], $d['idade'], $d['resp'], $d['rel'], $d['tel'], $d['tel2'], $d['doc'], $d['cpf'], $d['obs'], $foto, $foto !== '' ? 'celular' : '', $foto !== '' ? 1 : 0, $liberado, agora(), $d['pcd']]);
    $id = (int)$db->lastInsertId(); if ($d['pcd']) registrar('cad', $id, $d['nome'], 'Cadastrada como criança PCD: ' . pcdDesc() . '% de desconto na recreação · por ' . primeiroNome($u['nome'])); if ($sap) $db->prepare('UPDATE criancas SET sapateira = ? WHERE id = ?')->execute([$sap, $id]); $c = crianca($id);
    if ($horas > 0) fazerVenda($c, $horas * 60, $valor, (int)$u['id']);
    gravarCfg('mandar_' . $id, $id); // vai para o iDFace (nome agora, foto quando o aparelho confirmar o cadastro)
    responder(['id' => $id]);
  }
  case 'crianca_apagar': {
    $u = exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $db->prepare('UPDATE criancas SET ativo = 0, idface_id = NULL, sapateira = NULL WHERE id = ?')->execute([$c['id']]);
    if ($c['idface_id'] !== null) gravarCfg('apagar_' . (int)$c['idface_id'], (int)$c['idface_id']);
    apagarCfg('mandar_' . $c['id']); apagarCfg('foto_' . $c['id']);
    responder();
  }
  case 'sapateira': { // escolhe (ou tira, n = 0) a sapateira de uma criança; cada número só pode estar com uma criança de cada vez
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    $n = (int)($in['n'] ?? 0); if ($n < 0 || $n > (int)regra()['sapateiras']) falha('Essa sapateira não existe.');
    if ($n && ($dono = donoSapateira($n, (int)$c['id']))) falha('A sapateira ' . $n . ' já está com ' . $dono['nome'] . '. Escolha outra.');
    $db->prepare('UPDATE criancas SET sapateira = ? WHERE id = ?')->execute([$n ?: null, $c['id']]);
    responder(['sapateira' => $n ?: null, 'antes' => $c['sapateira'] === null ? null : (int)$c['sapateira']]);
  }
  case 'sapateira_liberar': { // deixa um número livre, esteja com quem estiver (para destravar uma sapateira esquecida)
    exigir(); $n = (int)($in['n'] ?? 0); $dono = $n > 0 ? donoSapateira($n) : null;
    if ($dono) $db->prepare('UPDATE criancas SET sapateira = NULL WHERE id = ?')->execute([$dono['id']]);
    responder(['crianca' => $dono ? (int)$dono['id'] : null]);
  }
  case 'foto_salvar': {
    exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $foto = (string)($in['foto'] ?? ''); if (!preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $foto)) falha('Foto inválida.');
    if (strlen($foto) > 1500000) falha('A foto ficou grande demais. Tire outra.');
    $db->prepare("UPDATE criancas SET foto = ?, foto_origem = 'celular', rosto = 1 WHERE id = ?")->execute([$foto, $c['id']]);
    if ($c['idface_id'] !== null) gravarCfg('foto_' . $c['id'], (int)$c['idface_id']); else gravarCfg('mandar_' . $c['id'], (int)$c['id']);
    responder();
  }
  case 'entrada': { // à mão. Sem o iDFace é o jeito normal: toda a equipe, e só para quem tem tempo comprado. Com o iDFace, só gestor e supervisor, para corrigir
    $manual = modoManual(); if ($manual) exigir(); else exigir(['gestor', 'supervisor']);
    $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    if ($c['entrou']) falha($c['nome'] . ' já está no espaço.');
    if ($manual && restante($c) < periodo()) falha($c['nome'] . ' não tem um período de recreação comprado. Registre a venda antes da entrada.');
    responder(fazerEntrada($c, 'mão'));
  }
  case 'saida': {
    if (modoManual()) exigir(); else exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    if (!$c['entrou']) falha($c['nome'] . ' não está no espaço.');
    // gestor e supervisor podem dizer a hora em que a criança saiu de verdade (saída esquecida), com o motivo
    $hh = trim((string)($in['quando'] ?? '')); $corrige = null;
    if ($hh !== '') {
      $u = exigir(['gestor', 'supervisor']); if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hh)) falha('Escreva a hora da saída assim: 14:30.');
      $motivo = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($in['motivo'] ?? ''))), 0, 300); if (mb_strlen($motivo) < 5) falha('Escreva o motivo da correção da hora.');
      $corrige = substr((string)$c['entrou'], 0, 10) . ' ' . $hh . ':00';
      if (strtotime($corrige) > time()) falha('A hora corrigida não pode ser depois de agora.');
      if (strtotime($corrige) < strtotime(substr((string)$c['entrou'], 0, 16) . ':00')) falha('A hora corrigida não pode ser antes da entrada (' . substr((string)$c['entrou'], 11, 5) . ').');
    }
    $r = fazerSaida($c, 'mão');
    if ($corrige !== null) { $k = corrigirSaida((int)$r['visita'], $corrige, $u, $motivo); $r['corrigida'] = $k; $r['debitado'] = $k['debitado']; $r['atraso'] = atrasoDaVisita((int)$c['id'], (string)$c['entrou'], date('Y-m-d H:i:s', time() + 5)); }
    responder($r);
  }
  case 'visita_corrigir': { // depois da saída: a supervisão corrige a hora (a criança saiu antes e a saída foi marcada tarde)
    $u = exigir(['gestor', 'supervisor']); $hh = trim((string)($in['quando'] ?? '')); if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hh)) falha('Escreva a hora da saída assim: 14:30.');
    $motivo = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($in['motivo'] ?? ''))), 0, 300); if (mb_strlen($motivo) < 5) falha('Escreva o motivo da correção da hora.');
    $q = $db->prepare('SELECT * FROM visitas WHERE id = ?'); $q->execute([(int)($in['visita'] ?? 0)]); $v = $q->fetch(); if (!$v) falha('Visita não encontrada.', 404);
    if (time() - strtotime((string)$v['saiu']) > 2 * 86400) falha('Só dá para corrigir saídas dos últimos dois dias.');
    responder(corrigirSaida((int)$v['id'], substr((string)$v['entrou'], 0, 10) . ' ' . $hh . ':00', $u, $motivo));
  }
  case 'desfazer_entrada': { // volta a criança para fora e apaga o registro e o aviso
    if (modoManual()) exigir(); else exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['entrou']) falha('Nada para desfazer.');
    // o "Desfazer" do aviso é para o toque errado, nos instantes seguintes; passou disso, a entrada só sai pelo cancelamento, com o motivo
    if (time() - strtotime((string)$c['entrou']) > 120) falha('O tempo de desfazer já passou. Para tirar esta entrada, use "Cancelar entrada" e escreva o motivo.');
    $db->prepare('UPDATE criancas SET entrou = NULL WHERE id = ?')->execute([$c['id']]);
    if (!empty($in['registro'])) $db->prepare('DELETE FROM registros WHERE id = ? AND crianca_id = ?')->execute([(int)$in['registro'], $c['id']]);
    if (!empty($in['mensagem'])) $db->prepare('DELETE FROM mensagens WHERE id = ? AND crianca_id = ?')->execute([(int)$in['mensagem'], $c['id']]);
    responder();
  }
  /* ---------- cancelar a entrada sem cobrar (com o motivo, para a supervisão conferir) ---------- */
  case 'cancelar_previa': { // o que a folha "Cancelar a entrada" mostra: há quanto tempo entrou, se ainda está no prazo e as vendas desta visita
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    if (!$c['entrou']) falha($c['nome'] . ' não está no espaço.');
    [$lim, $seg, $noPrazo] = prazoCancelar($c);
    responder(['limite' => $lim, 'segundos' => $seg, 'no_prazo' => $noPrazo, 'pode' => $noPrazo || in_array($u['papel'], ['gestor', 'supervisor'], true), 'entrou' => $c['entrou'],
      'vendas' => array_map(fn($v) => ['id' => (int)$v['id'], 'n' => nomeDaVenda($v), 'm' => (int)$v['minutos'], 'v' => (float)$v['valor'], 'quando' => $v['quando'], 'junto' => vendaJuntoDaEntrada($v, $c)], vendasDaVisita($c))]);
  }
  case 'cancelar_entrada': {
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    if (!$c['entrou']) falha($c['nome'] . ' não está no espaço: não há entrada para cancelar.');
    $motivo = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($in['motivo'] ?? ''))), 0, 300);
    if (mb_strlen($motivo) < 5) falha('Escreva o motivo do cancelamento: a supervisão vai conferir.');
    [$lim, , $noPrazo] = prazoCancelar($c);
    if (!$noPrazo && !in_array($u['papel'], ['gestor', 'supervisor'], true)) falha($lim > 0 ? 'Já passou de ' . $lim . ' min da entrada de ' . $c['nome'] . ': agora só Gestor ou Supervisor cancela.' : 'Só Gestor ou Supervisor cancela a entrada.', 403);
    $r = cancelarEntrada($c, $u, $motivo, isset($in['vendas']) && is_array($in['vendas']) ? array_map('intval', $in['vendas']) : null);
    $r['mensagem'] = is_array($r['mensagem']) ? ['id' => (int)$r['mensagem']['id'], 'erro' => (string)$r['mensagem']['erro']] : null;
    responder($r);
  }
  case 'pendente_tirar': { // "não vai fazer agora": a criança sai de "Falta finalizar" em todos os aparelhos (voltar = o Desfazer do aviso)
    exigir(); $id = (int)($in['id'] ?? 0); $c = crianca($id); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    $f = pendFora(); if (!empty($in['voltar'])) unset($f[$id]); else $f[$id] = date('Y-m-d');
    gravarCfg('pend_fora', $f); responder();
  }
  case 'cancelamento_visto': { // a supervisão conferiu: fica marcado quem viu e quando
    $u = exigir(['gestor', 'supervisor']);
    if (!empty($in['todos'])) $db->prepare("UPDATE cancelamentos SET visto_por = ?, visto_em = ? WHERE visto_em IS NULL")->execute([$u['nome'], agora()]);
    else $db->prepare("UPDATE cancelamentos SET visto_por = ?, visto_em = ? WHERE id = ? AND visto_em IS NULL")->execute([$u['nome'], agora(), (int)($in['id'] ?? 0)]);
    responder();
  }
  case 'desfazer_saida': {
    if (modoManual()) exigir(); else exigir(['gestor', 'supervisor']); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Nada para desfazer.');
    $q = $db->prepare('SELECT * FROM visitas WHERE id = ? AND crianca_id = ?'); $q->execute([(int)($in['visita'] ?? 0), $c['id']]); $v = $q->fetch(); if (!$v) falha('Nada para desfazer.');
    if (time() - strtotime((string)$v['saiu']) > 120) falha('O tempo de desfazer esta saída já passou.');
    $db->prepare('UPDATE criancas SET entrou = ?, usado_min = MAX(0, usado_min - ?) WHERE id = ?')->execute([$v['entrou'], (int)$v['debitado_min'], $c['id']]);
    $sap = (int)($in['sapateira'] ?? 0); if ($sap > 0 && !donoSapateira($sap, (int)$c['id'])) $db->prepare('UPDATE criancas SET sapateira = ? WHERE id = ?')->execute([$sap, $c['id']]);
    $db->prepare('DELETE FROM visitas WHERE id = ?')->execute([$v['id']]);
    if (!empty($in['registro'])) $db->prepare('DELETE FROM registros WHERE id = ? AND crianca_id = ?')->execute([(int)$in['registro'], $c['id']]);
    if (!empty($in['mensagem'])) $db->prepare('DELETE FROM mensagens WHERE id = ? AND crianca_id = ?')->execute([(int)$in['mensagem'], $c['id']]);
    responder();
  }
  case 'vender': {
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c) falha('Criança não encontrada.', 404);
    $min = isset($in['minutos']) ? max(0, (int)$in['minutos']) : max(0, (int)round((float)($in['horas'] ?? 0) * 60)); $valor = max(0, (float)($in['valor'] ?? 0)); $itens = mb_substr(trim((string)($in['itens'] ?? '')), 0, 80);
    if ($min <= 0 && $itens === '') falha('O que foi vendido?');
    responder(fazerVenda($c, $min, $valor, (int)$u['id'], $itens));
  }
  case 'atender': { // a venda do atendimento: um ou mais serviços de uma vez. O valor sai da tabela de Ajustes, não do que a tela mandar
    $u = exigir(); $c = crianca((int)($in['id'] ?? 0)); if (!$c || !$c['ativo']) falha('Criança não encontrada.', 404);
    $r = regra(); $porId = []; foreach ($r['servicos'] as $s) $porId[(string)$s['id']] = $s;
    $linhas = [];
    foreach ((array)($in['linhas'] ?? []) as $l) {
      $s = $porId[(string)($l['s'] ?? '')] ?? null; $q = min(48, (int)($l['q'] ?? 0)); if (!$s || $q < 1) continue;
      if (($s['modo'] ?? 'fixo') === 'periodo') { $tot = $q * max(1, (int)($s['min'] ?? 30)); $tempo = !empty($s['banco']); $un = $tempo ? precoPcd($c, (float)$s['v']) : (float)$s['v']; $comPcd = $un < (float)$s['v'];   // a criança PCD paga a recreação avulsa com desconto (carrinhos e pacotes, não)
        $linhas[] = ['servico' => (string)$s['n'], 'min' => $tempo ? $tot : 0, 'valor' => $q * $un, 'itens' => ($tempo ? '' : dur($tot)) . ($comPcd ? 'PCD ' . pcdDesc() . '%' : ''), 'mais' => $comPcd ? ['pcd' => 1, 'cheio' => $q * (float)$s['v']] : []]; }
      else { $obs = trim((string)($s['obs'] ?? '')); $linhas[] = ['servico' => (string)$s['n'], 'min' => 0, 'valor' => $q * (float)$s['v'], 'itens' => ($q > 1 ? $q . ' unidades' . ($obs !== '' ? ' · ' : '') : '') . $obs]; }
    }
    // pacote de horas não tem o desconto PCD: ele vale só na recreação avulsa
    // a tela manda as horas, o tipo (com produto ou seco) e o valor que mostrou: se a tabela mudou no meio do atendimento, avisa em vez de vender outro pacote
    foreach ((array)($in['pacotes'] ?? []) as $i) {
      if (is_array($i)) { $p = null; $outro = null; $h = (int)($i['h'] ?? 0); $tp = ($i['tipo'] ?? '') === 'produto' ? 'produto' : 'seco';
        $nm = array_key_exists('nome', $i) ? mb_strtolower(trim((string)$i['nome'])) : null; $val = isset($i['v']) ? (float)$i['v'] : null;   // pode haver vários pacotes do mesmo tempo: o nome e o valor dizem qual é
        foreach ($r['pacotes'] as $x) { if ((int)$x['h'] !== $h || tipoPacote($x) !== $tp) continue; if ($nm !== null && mb_strtolower(trim((string)($x['nome'] ?? ''))) !== $nm) continue;
          if ($val === null || abs((float)$x['v'] - $val) < 0.005) { $p = $x; break; } $outro = $outro ?? $x; }
        if (!$p && $outro) falha('O valor do pacote de ' . $h . ' h mudou para ' . dinheiro((float)$outro['v']) . '. Feche e abra o atendimento de novo.');
        if (!$p) falha('O pacote de ' . $h . ' h não está mais na tabela. Feche e abra o atendimento de novo.');
      } else $p = $r['pacotes'][(int)$i] ?? null;   // a tela antiga mandava a posição na lista
      if ($p) $linhas[] = ['servico' => servicoDoPacote($p), 'min' => (int)$p['h'] * 60, 'valor' => (float)$p['v'], 'itens' => itensDoPacote($p)];
    }
    // voucher do shopping: 30 min de recreação grátis, um por visita, sem código. A tela só marca (voucher: true); a tela antiga mandava { q, codigo } e vale como um voucher.
    // Confere antes de vender qualquer coisa, para não ficar uma venda pela metade
    $vo = $in['voucher'] ?? null; $comVoucher = is_array($vo) ? (int)($vo['q'] ?? 0) > 0 : !empty($vo);
    if ($comVoucher) { if (!servicoTempo()) falha('Não há serviço de tempo de recreação para usar o voucher.'); if (voucherAberto($c)) falha(voucherJaTem($c)); }
    $o = (array)($in['outro'] ?? []); $on = mb_substr(trim((string)($o['n'] ?? '')), 0, 60); if ($on !== '') $linhas[] = ['servico' => $on, 'min' => 0, 'valor' => max(0, (float)($o['v'] ?? 0)), 'itens' => ''];
    if (!$linhas && !$comVoucher) falha('Escolha o serviço.');
    // a criança já teve uma venda registrada (em outro aparelho) depois que esta tela abriu o atendimento: avisa antes de cobrar de novo. "mesmo_assim" confirma
    $visto = (int)($in['visto'] ?? 0);
    if ($visto > 0 && empty($in['mesmo_assim'])) {
      $qd = $db->prepare("SELECT quando, texto FROM registros WHERE tipo = 'venda' AND crianca_id = ? AND id > ? ORDER BY id DESC LIMIT 1"); $qd->execute([(int)$c['id'], $visto]);
      if ($ja = $qd->fetch()) falha('Já foi registrada uma venda para ' . $c['nome'] . ' às ' . substr((string)$ja['quando'], 11, 5) . ': ' . $ja['texto'] . '. Confira antes de registrar outra.', 409);
    }
    $vendas = []; $regs = []; $total = 0; $min = 0;
    foreach ($linhas as $l) { $x = fazerVenda($c, $l['min'], $l['valor'], (int)$u['id'], $l['itens'], $l['servico'], false, '', $l['mais'] ?? []); $vendas[] = $x['venda']; $regs[] = $x['registro']; $total += $l['valor']; $min += $l['min']; $c = crianca((int)$c['id']); }
    $vinfo = null;   // o voucher entra por último: com cobrança por atraso a receber, ele cobre essa cobrança em vez de somar tempo
    if ($comVoucher) { $v = usarVoucher($c, $u); $vendas[] = $v['venda']; $regs[] = $v['registro']; $min += $v['minutos']; $c = crianca((int)$c['id']); $vinfo = ['n' => $v['n'], 'cobriu' => $v['cobriu']]; }
    $resp = ['vendas' => $vendas, 'registros' => $regs, 'total' => $total, 'minutos' => $min, 'voucher' => $vinfo];
    if (!empty($in['entrar']) && modoManual() && !$c['entrou'] && restante($c) >= periodo()) { // sem iDFace: pagou e já entra, o tempo começa a contar
      $e = fazerEntrada($c, 'mão'); $resp['entrada'] = ['registro' => $e['registro'], 'mensagem' => is_array($e['mensagem']) ? (int)($e['mensagem']['id'] ?? 0) : 0];
    }
    responder($resp);
  }
  case 'historico': { // tudo de um cliente (as crianças do mesmo responsável): todas as compras e todas as visitas
    exigir(); session_write_close();
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($in['ids'] ?? []))))); if (!$ids || count($ids) > 30) falha('Cliente não encontrado.', 404);
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $q = $db->prepare("SELECT v.*, u.nome AS por_nome FROM vendas v LEFT JOIN usuarios u ON u.id = v.por WHERE v.crianca_id IN ($marcas) ORDER BY v.quando DESC, v.id DESC LIMIT 500"); $q->execute($ids);
    $vendas = array_map(fn($v) => ['id' => (int)$v['id'], 'crianca' => (int)$v['crianca_id'], 'h' => round((int)$v['minutos'] / 60, 2), 'm' => (int)$v['minutos'], 'v' => (float)$v['valor'], 'itens' => (string)($v['itens'] ?? ''), 'servico' => (string)($v['servico'] ?? ''), 'quando' => $v['quando'], 'por' => (string)($v['por_nome'] ?? ''), 'auto' => !empty($v['auto']), 'voucher' => (string)($v['voucher'] ?? ''), 'pcd' => !empty($v['pcd'])], $q->fetchAll());
    $q = $db->prepare("SELECT * FROM visitas WHERE crianca_id IN ($marcas) ORDER BY saiu DESC, id DESC LIMIT 1000"); $q->execute($ids);
    $visitas = array_map(fn($v) => ['id' => (int)$v['id'], 'crianca' => (int)$v['crianca_id'], 'dia' => $v['saiu'], 'entrou' => substr($v['entrou'], 11, 5), 'saiu' => substr($v['saiu'], 11, 5), 'deb' => (int)$v['debitado_min']], $q->fetchAll());
    $q = $db->prepare("SELECT * FROM cancelamentos WHERE crianca_id IN ($marcas) ORDER BY id DESC LIMIT 200"); $q->execute($ids);
    responder(['vendas' => $vendas, 'visitas' => $visitas, 'cancelados' => array_map('cancelamentoPublico', $q->fetchAll())]);
  }
  case 'desfazer_venda': {
    exigir(); $ids = array_values(array_unique(array_filter(array_map('intval', array_merge((array)($in['vendas'] ?? []), [(int)($in['venda'] ?? 0)])))));
    $q = $db->prepare('SELECT * FROM vendas WHERE id = ?'); $tempoDe = [];   // criança => minutos de brincar desfeitos
    foreach ($ids as $vid) {
      $q->execute([$vid]); $v = $q->fetch(); if (!$v) continue;
      if (time() - strtotime((string)$v['quando']) > 120) continue;   // o "Desfazer" do aviso só alcança a venda que acabou de ser feita
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$v['minutos'], $v['crianca_id']]);
      $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([$v['id']]); $tempoDe[(int)$v['crianca_id']] = ($tempoDe[(int)$v['crianca_id']] ?? 0) + (int)$v['minutos'];
    }
    if (!$tempoDe) falha('Nada para desfazer.');
    foreach (array_filter(array_map('intval', array_merge((array)($in['registros'] ?? []), [(int)($in['registro'] ?? 0)]))) as $rid) $db->prepare('DELETE FROM registros WHERE id = ?')->execute([$rid]);
    // desfez o tempo e o saldo voltou a zero: o acesso volta a ficar bloqueado (senão vender e desfazer liberava a entrada de graça)
    foreach ($tempoDe as $cid => $m) { $c = crianca($cid);
      if ($c && $m > 0 && $c['entrou'] === null && restante($c) < periodo() && regra()['bloqueioAuto'] === 'sim' && (!isset($c['liberado']) || (int)$c['liberado'])) bloquearAcesso($c, false); }
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
    foreach (['tolerancia' => [0, 5, 9, 10, 15], 'fecha' => [20, 21, 22, 23, 24], 'aviso' => [5, 10, 15, 30, 60, 120], 'cancelaMin' => [0, 5, 9, 10, 15]] as $k => $ops) if (isset($in[$k]) && in_array((int)$in[$k], $ops, true)) $r[$k] = (int)$in[$k];
    if (isset($in['autoMsg']) && in_array($in['autoMsg'], ['ambos', 'entrada', 'nao'], true)) $r['autoMsg'] = $in['autoMsg'];
    if (isset($in['bloqueioAuto']) && in_array($in['bloqueioAuto'], ['sim', 'nao'], true)) $r['bloqueioAuto'] = $in['bloqueioAuto'];
    if (isset($in['sapateiras'])) { $n = (int)$in['sapateiras']; if ($n < 1 || $n > 200) falha('Quantas sapateiras existem? De 1 a 200.'); $r['sapateiras'] = $n; }
    if (isset($in['pacotes']) && is_array($in['pacotes'])) {
      // um pacote: horas, valor, tipo (com produto, o brinde já no valor; ou seco, só as horas), nome opcional e em até quantas vezes parcela. Pode haver vários do mesmo tempo
      $ps = []; foreach ($in['pacotes'] as $p) { $h = (int)($p['h'] ?? 0); $v = (float)($p['v'] ?? 0); if ($h <= 0 || $h > 500 || $v < 0) continue;
        $com = mb_substr(trim((string)($p['com'] ?? '')), 0, 60); $x = ['h' => $h, 'v' => $v, 'tipo' => tipoPacote(['tipo' => $p['tipo'] ?? null, 'com' => $com])];
        $nome = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($p['nome'] ?? ''))), 0, 40); if ($nome !== '') $x['nome'] = $nome;   // o nome é opcional: serve para separar pacotes do mesmo tempo
        if ($x['tipo'] === 'produto' && $com !== '') $x['com'] = $com; $parc = (int)($p['parc'] ?? 0); if ($parc > 1 && $parc <= 12) $x['parc'] = $parc;
        $ps[chavePacote($x)] = $x; }
      ksort($ps); $r['pacotes'] = array_values($ps);   // pacotes são opcionais: a lista pode ficar vazia
    }
    if (isset($in['servicos']) && is_array($in['servicos'])) { // os serviços e produtos da loja: valor único ou a cada tantos minutos
      $ss = []; $ids = [];
      foreach ($in['servicos'] as $k => $s) {
        $n = mb_substr(trim((string)($s['n'] ?? '')), 0, 40); if ($n === '' || count($ss) >= 30) continue;
        $id = preg_replace('/[^a-z0-9_]/', '', (string)($s['id'] ?? '')); if ($id === '' || isset($ids[$id])) $id = 's' . dechex(crc32($n . '|' . $k . '|' . microtime())); $ids[$id] = 1;
        $modo = ($s['modo'] ?? 'fixo') === 'periodo' ? 'periodo' : 'fixo';
        $ss[] = ['id' => $id, 'n' => $n, 'v' => max(0, (float)($s['v'] ?? 0)), 'modo' => $modo, 'min' => $modo === 'periodo' ? max(5, min(240, (int)($s['min'] ?? 30))) : 0, 'banco' => $modo === 'periodo' && !empty($s['banco']), 'obs' => mb_substr(trim((string)($s['obs'] ?? '')), 0, 60)];
      }
      if (!$ss) falha('Deixe pelo menos um serviço.'); $r['servicos'] = $ss; $r['extras'] = [];
    }
    if (isset($in['extras']) && is_array($in['extras'])) { // (versões antigas) produtos vendidos à parte
      $es = []; foreach ($in['extras'] as $e) { $n = mb_substr(trim((string)($e['n'] ?? '')), 0, 40); $v = (float)($e['v'] ?? 0); if ($n !== '' && $v >= 0 && count($es) < 30) $es[] = ['n' => $n, 'v' => $v]; }
      $r['extras'] = $es;
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
  case 'exemplos_carregar': { // o teste do rodapé: 34 crianças de exemplo (30 no espaço, em horários diferentes), conversas e agenda
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
