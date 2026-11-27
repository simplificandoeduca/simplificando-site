<?php
/* =====================================================================
   Play Space — o que todos os arquivos do servidor usam
   PHP 7.4 ou mais novo, com pdo_sqlite (já vem ligado na HostGator). Não precisa criar banco:
   os dados ficam num arquivo SQLite FORA da pasta pública (playspace-dados, ao lado do public_html).
   Se não der para criar lá, fica em api/dados, trancada por .htaccess.
   ===================================================================== */
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');
header('X-Content-Type-Options: nosniff');

const PLAY_VERSAO = '0.30';
const PAPEIS = ['gestor' => 'Gestor', 'supervisor' => 'Supervisor', 'atendente' => 'Operador'];   // 1.21: o atendente passa a se chamar Operador (a chave no banco continua a mesma)
// 0.89: o módulo do WhatsApp fica desligado (a loja não vai usar essa parte por agora). Nada foi apagado: com true aqui (e COM_WHATS = true na tela),
// a aba, os botões e os avisos voltam. Desligado, nenhuma mensagem automática sai, mesmo que a regra antiga mandasse
const MODULO_WHATS = false;
// 0.90: as formas de pagamento que a tela oferece depois da escolha do serviço. Um pagamento pode ser dividido em até três delas
const FORMAS_PAGAMENTO = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'credito' => 'Crédito', 'debito' => 'Débito', 'link' => 'Link'];   /* 2.51: o link de pagamento */

function falha(string $msg, int $st = 400): void { http_response_code($st); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => false, 'erro' => $msg], JSON_UNESCAPED_UNICODE); exit; }
function responder(array $d = []): void {
  header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
  $j = (string)json_encode(['ok' => true] + $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if (!empty($GLOBALS['PS_OFF'])) { $o = $GLOBALS['PS_OFF']; try { $u0 = eu(); db()->prepare('INSERT OR IGNORE INTO offline_envios (uid, acao, em, recebido, por, resposta) VALUES (?, ?, ?, ?, ?, ?)')->execute([$o['uid'], $o['acao'], $o['em'], date('Y-m-d H:i:s'), $u0 ? (int)$u0['id'] : null, $j]);
    if (random_int(1, 40) === 1) db()->prepare('DELETE FROM offline_envios WHERE recebido < ?')->execute([date('Y-m-d H:i:s', time() - 8 * 86400)]); } catch (Throwable $e) { } }   // 1.40
  // 1.01: com milhares de cadastros a lista fica grande. Ela vai comprimida quando a tela pede (gz = 1), e a tela só pede depois de conferir
  // que lê a resposta comprimida deste servidor. Se o servidor já comprime sozinho, não mexe.
  if (!empty($GLOBALS['_gz']) && strlen($j) > 8000 && function_exists('gzencode') && !headers_sent() && !ini_get('zlib.output_compression') && !ini_get('output_handler')) {
    $z = @gzencode($j, 4);
    if (is_string($z) && $z !== '') { header('Content-Encoding: gzip'); header('Vary: Accept-Encoding'); header('Content-Length: ' . strlen($z)); echo $z; exit; }
  }
  echo $j; exit;
}
// 1.40: a hora "de agora" do pedido. O que foi feito sem internet chega depois com a hora em que foi feito: o pedido inteiro corre naquela hora
function agoraTs(): int { return isset($GLOBALS['PS_AGORA']) ? (int)$GLOBALS['PS_AGORA'] : time(); }
function agora(): string { return date('Y-m-d H:i:s', agoraTs()); }
const OFFLINE_DIAS = 2;   // o que ficou sem internet por mais que isso não entra sozinho (o gestor confere)
function registrarErro(string $msg): void { error_log('[playspace] ' . $msg); }
// os nomes (da criança e do responsável) ficam sempre em maiúsculas, com um espaço só entre as palavras, mesmo que tenham sido escritos em minúsculas
function maiusculo(string $s): string { return mb_strtoupper(trim((string)preg_replace('/\s+/u', ' ', $s)), 'UTF-8'); }
// na mensagem para o responsável o nome vai como se escreve ("Pedro Henrique da Silva"), não em maiúsculas
function nomeMsg(string $s): string {
  $t = mb_convert_case(mb_strtolower(trim($s), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
  return (string)preg_replace_callback('/(?<=\s)(Da|De|Do|Das|Dos|E)(?=\s)/u', fn($m) => mb_strtolower($m[1], 'UTF-8'), $t);
}
// põe em maiúsculas os nomes que já estão no banco: todos ($ids = null) ou só os dessas crianças (o teste do rodapé)
function nomesEmMaiusculas(PDO $pdo, ?array $ids = null): void {
  $lista = $ids === null ? '' : implode(',', array_map('intval', $ids) ?: [0]);
  foreach ([['criancas', 'id', ['nome', 'resp']], ['cancelamentos', 'crianca_id', ['nome', 'resp']], ['registros', 'crianca_id', ['nome']]] as [$t, $chave, $cols]) {
    $up = $pdo->prepare("UPDATE $t SET " . implode(', ', array_map(fn($k) => "$k = ?", $cols)) . ' WHERE id = ?');
    $onde = $ids === null ? ($t === 'registros' ? ' WHERE crianca_id IS NOT NULL' : '') : " WHERE $chave IN ($lista)";   // as linhas sem criança (estoque de produto) ficam como estão
    foreach ($pdo->query('SELECT id, ' . implode(', ', $cols) . " FROM $t" . $onde)->fetchAll() as $l) {
      $velho = array_map(fn($k) => (string)$l[$k], $cols); $novo = array_map('maiusculo', $velho);
      if ($novo !== $velho) $up->execute(array_merge($novo, [$l['id']]));
    }
  }
}
// 0.89: tudo o que é digitado fica em maiúsculas. Texto livre (observação, motivo): mantém as quebras de linha
function emMaiusculas(string $s): string { return mb_strtoupper(trim($s), 'UTF-8'); }
// põe em maiúsculas o que já estava digitado no banco: observações, motivos, nomes de produtos, de serviços, de pacotes, da equipe e a agenda.
// O que o sistema escreveu sozinho (as linhas do dia, as vendas antigas) fica como está; o login de cada pessoa também
function textosEmMaiusculas(PDO $pdo): void {
  foreach ([['criancas', ['obs'], 'emMaiusculas'], ['cancelamentos', ['motivo'], 'emMaiusculas'], ['compromissos', ['titulo', 'obs'], 'emMaiusculas'], ['produtos', ['nome'], 'maiusculo'], ['usuarios', ['nome'], 'maiusculo']] as [$t, $cols, $fn]) {
    try {
      $up = $pdo->prepare("UPDATE $t SET " . implode(', ', array_map(fn($k) => "$k = ?", $cols)) . ' WHERE id = ?');
      foreach ($pdo->query('SELECT id, ' . implode(', ', $cols) . " FROM $t")->fetchAll() as $l) {
        $velho = array_map(fn($k) => (string)$l[$k], $cols); $novo = array_map($fn, $velho);
        if ($novo !== $velho) $up->execute(array_merge($novo, [$l['id']]));
      }
    } catch (Throwable $e) { }
  }
  try {
    $q = $pdo->query("SELECT valor FROM config WHERE chave = 'regra'")->fetchColumn(); $rg = $q !== false ? json_decode((string)$q, true) : null;
    if (is_array($rg)) { $antes = $rg;
      foreach ((array)($rg['servicos'] ?? []) as $k => $x) if (is_array($x)) foreach (['n', 'obs'] as $f) if (isset($x[$f])) $rg['servicos'][$k][$f] = maiusculo((string)$x[$f]);
      foreach ((array)($rg['pacotes'] ?? []) as $k => $x) if (is_array($x)) foreach (['nome', 'com'] as $f) if (isset($x[$f])) $rg['pacotes'][$k][$f] = maiusculo((string)$x[$f]);
      if ($rg !== $antes) $pdo->prepare("UPDATE config SET valor = ? WHERE chave = 'regra'")->execute([json_encode($rg, JSON_UNESCAPED_UNICODE)]);
    }
  } catch (Throwable $e) { }
}

/* ---------- 1.45: um pedido de cada vez para o que muda os dados ---------- */
function travarEscrita(string $acao): void {
  if (in_array($acao, ['estado', 'gz_teste', 'notas_ponte'], true) || isset($GLOBALS['_trava'])) return;   // (só estes não mudam nada)
  $f = @fopen(pastaDados() . '/escrita.trava', 'c'); if (!$f) return;
  if (@flock($f, LOCK_EX)) $GLOBALS['_trava'] = $f;   // fica aberta até o fim do pedido (o PHP solta a trava sozinho)
}

/* ---------- 2.49: a ponte com o sistema de notas (entra lá sem pedir o login) ---------- */
// O dono: "não precisa pedir login do sistema de nota, pode deixar logado direto". Quem está dentro do Play Space (ou das Finanças) recebe
// um bilhete assinado, que vale 2 minutos; o Notas (191) confere a assinatura com o mesmo segredo e abre a sessão dele (o usuário
// automático "Play Space", ou "Play Space (gestor)" para o gestor). O segredo fica num arquivo da pasta do Notas (notas/api/
// ponte-segredo.php), feito aqui na primeira vez, e nunca sai do servidor.
function notasPonteArquivo(): string { return dirname(__DIR__) . '/notas/api/ponte-segredo.php'; }
function notasPonteLer(string $f): string { $t = @file_get_contents($f); return ($t !== false && preg_match("/'([0-9a-f]{64})'/", $t, $m)) ? $m[1] : ''; }
function notasPonteSegredo(): string {
  $f = notasPonteArquivo(); $s = notasPonteLer($f); if ($s !== '') return $s;
  if (!is_dir(dirname($f))) falha('O sistema de notas não está na pasta "notas" deste site.', 404);
  $tmp = $f . '.' . bin2hex(random_bytes(6)) . '.tmp';
  $php = "<?php\n// Play Space 2.49: o segredo da ponte com o Notas (feito sozinho pelo Play Space). Não mostre a ninguém.\n"
    . "if (!defined('GAMA_NOTAS')) { http_response_code(404); exit; }\nreturn '" . bin2hex(random_bytes(32)) . "';\n";
  if (@file_put_contents($tmp, $php, LOCK_EX) === false) falha('Não consegui ligar o Play Space ao sistema de notas (a pasta notas/api não deixa gravar).', 500);
  @chmod($tmp, 0600);
  if (!@link($tmp, $f) && !is_file($f)) @rename($tmp, $f);   /* (dois pedidos juntos: fica o primeiro) */
  @unlink($tmp);
  $s = notasPonteLer($f); if ($s === '') falha('Não consegui ligar o Play Space ao sistema de notas.', 500);
  return $s;
}
function notasPonteBilhete(int $uid, string $nome, bool $gestor, string $origem): string {
  $b64 = fn(string $x): string => rtrim(strtr(base64_encode($x), '+/', '-_'), '=');
  $p = $b64((string)json_encode(['u' => $uid, 'n' => $nome, 'g' => $gestor ? 1 : 0, 'o' => $origem, 'e' => time() + 120, 'r' => bin2hex(random_bytes(8))], JSON_UNESCAPED_UNICODE));
  return $p . '.' . $b64(hash_hmac('sha256', 'notas-ponte|' . $p, notasPonteSegredo(), true));
}

/* ---------- onde os dados ficam ---------- */
function pastaDados(): string {
  static $pasta = null;
  if ($pasta) return $pasta;
  $raiz = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
  // 1.44: a instalação que já traz a pasta api/dados (o pacote do domínio da loja) guarda os dados só ali, e nunca na ~/playspace-dados,
  // que outra instalação na mesma hospedagem (a de teste) pode estar usando: os dados de verdade não se misturam com os do teste
  $lugares = is_dir(__DIR__ . '/dados') ? [__DIR__ . '/dados'] : [dirname($raiz) . '/playspace-dados', __DIR__ . '/dados'];
  foreach ($lugares as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_dir($dir) && is_writable($dir)) {
      if (!file_exists($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
      if (!file_exists($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
      return $pasta = $dir;
    }
  }
  falha('Não consegui criar a pasta de dados. No cPanel, dê permissão de escrita (755) para a pasta playspace/api.', 500);
}
function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) falha('O PHP do servidor está sem a extensão pdo_sqlite. No cPanel: Selecionar versão do PHP → Extensões → marque pdo_sqlite.', 500);
  $dir = pastaDados();
  $existentes = glob($dir . '/playspace-*.sqlite') ?: [];
  $arquivo = $existentes[0] ?? ($dir . '/playspace-' . bin2hex(random_bytes(8)) . '.sqlite');
  $novo = !file_exists($arquivo);
  $pdo = new PDO('sqlite:' . $arquivo, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  if ($novo) @chmod($arquivo, 0600);
  $pdo->exec('PRAGMA busy_timeout = 6000');
  @$pdo->exec('PRAGMA journal_mode = WAL');
  $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
  if ($v < 1) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (id INTEGER PRIMARY KEY, nome TEXT NOT NULL, login TEXT NOT NULL UNIQUE COLLATE NOCASE, senha TEXT NOT NULL, papel TEXT NOT NULL DEFAULT 'atendente', ativo INTEGER NOT NULL DEFAULT 1, criado_em TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS criancas (id INTEGER PRIMARY KEY, nome TEXT NOT NULL, idade INTEGER NOT NULL DEFAULT 0, resp TEXT NOT NULL DEFAULT '', rel TEXT NOT NULL DEFAULT 'responsável', tel TEXT NOT NULL DEFAULT '', cpf TEXT NOT NULL DEFAULT '', obs TEXT NOT NULL DEFAULT '', pacote_min INTEGER NOT NULL DEFAULT 0, usado_min INTEGER NOT NULL DEFAULT 0, entrou TEXT, idface_id INTEGER UNIQUE, foto TEXT NOT NULL DEFAULT '', rosto INTEGER NOT NULL DEFAULT 0, ativo INTEGER NOT NULL DEFAULT 1, criado_em TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS visitas (id INTEGER PRIMARY KEY, crianca_id INTEGER NOT NULL, entrou TEXT NOT NULL, saiu TEXT NOT NULL, debitado_min INTEGER NOT NULL DEFAULT 0, origem TEXT NOT NULL DEFAULT 'idface')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS vendas (id INTEGER PRIMARY KEY, crianca_id INTEGER NOT NULL, horas INTEGER NOT NULL, valor REAL NOT NULL DEFAULT 0, quando TEXT NOT NULL, por INTEGER)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS registros (id INTEGER PRIMARY KEY, quando TEXT NOT NULL, tipo TEXT NOT NULL, crianca_id INTEGER, nome TEXT NOT NULL DEFAULT '', texto TEXT NOT NULL DEFAULT '')");
    $pdo->exec("CREATE INDEX IF NOT EXISTS registros_quando ON registros (quando)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mensagens (id INTEGER PRIMARY KEY, crianca_id INTEGER NOT NULL, de TEXT NOT NULL, texto TEXT NOT NULL DEFAULT '', quando TEXT NOT NULL, por TEXT NOT NULL DEFAULT '', auto INTEGER NOT NULL DEFAULT 0, foto TEXT NOT NULL DEFAULT '', lida INTEGER NOT NULL DEFAULT 1, wa_id TEXT NOT NULL DEFAULT '', erro TEXT NOT NULL DEFAULT '')");
    $pdo->exec("CREATE INDEX IF NOT EXISTS mensagens_crianca ON mensagens (crianca_id, quando)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS compromissos (id INTEGER PRIMARY KEY, dia TEXT NOT NULL, hora TEXT NOT NULL DEFAULT '', titulo TEXT NOT NULL, tipo TEXT NOT NULL DEFAULT 'oficina', sub TEXT NOT NULL DEFAULT '', obs TEXT NOT NULL DEFAULT '', criado_em TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS batidas (id INTEGER PRIMARY KEY, idface_user INTEGER NOT NULL, ts INTEGER NOT NULL, log_id INTEGER, aparelho TEXT NOT NULL DEFAULT 'idface', quando TEXT NOT NULL, crianca_id INTEGER, resultado TEXT NOT NULL DEFAULT '', criado_em TEXT)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS batidas_log ON batidas (aparelho, log_id) WHERE log_id IS NOT NULL");
    $pdo->exec("CREATE TABLE IF NOT EXISTS config (chave TEXT PRIMARY KEY, valor TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tentativas (login TEXT NOT NULL, ip TEXT NOT NULL, quando INTEGER NOT NULL)");
    $pdo->exec('PRAGMA user_version = 1');
  }
  if ($v < 2) { // 0.37: compromisso riscado como feito
    try { $pdo->exec('ALTER TABLE compromissos ADD COLUMN feito INTEGER NOT NULL DEFAULT 0'); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 2');
  }
  if ($v < 3) { // 0.55: de onde veio a foto (celular ou o próprio iDFace)
    try { $pdo->exec("ALTER TABLE criancas ADD COLUMN foto_origem TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 3');
  }
  if ($v < 4) { // 0.58: a chavinha "entrada liberada" (desligada, o iDFace recusa o rosto e a leitura não conta)
    try { $pdo->exec('ALTER TABLE criancas ADD COLUMN liberado INTEGER NOT NULL DEFAULT 1'); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 4');
  }
  if ($v < 5) { // 0.68: o que mais foi na venda (carrinho, produto que veio no pacote); venda só de produto tem horas = 0
    try { $pdo->exec("ALTER TABLE vendas ADD COLUMN itens TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 5');
  }
  if ($v < 6) { // 0.69: a sapateira onde fica o sapato da criança enquanto ela brinca (número de 1 até regra.sapateiras; vazio = nenhuma)
    try { $pdo->exec('ALTER TABLE criancas ADD COLUMN sapateira INTEGER'); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 6');
  }
  if ($v < 7) { // 0.70: o documento do responsável pode ser CPF ou RNE (estrangeiro); segundo telefone
    try { $pdo->exec("ALTER TABLE criancas ADD COLUMN doc_tipo TEXT NOT NULL DEFAULT 'cpf'"); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE criancas ADD COLUMN tel2 TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 7');
  }
  if ($v < 8) { // 0.71: a venda guarda os minutos de brincar que deu (meia hora conta) e o nome do serviço vendido
    try { $pdo->exec('ALTER TABLE vendas ADD COLUMN minutos INTEGER NOT NULL DEFAULT 0'); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE vendas ADD COLUMN servico TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }
    try { $pdo->exec('UPDATE vendas SET minutos = CAST(horas * 60 AS INTEGER) WHERE minutos = 0 AND horas > 0'); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 8');
  }
  if ($v < 9) { // 0.72: a loja marca entrada e saída à mão por enquanto: a leitura do iDFace fica desligada (religa em Ajustes → iDFace → Ligar leitura)
    try {
      $q = $pdo->query("SELECT valor FROM config WHERE chave = 'idface'")->fetchColumn(); $ic = $q !== false ? json_decode((string)$q, true) : []; if (!is_array($ic)) $ic = [];
      $ic['ligado'] = false; $pdo->prepare('INSERT INTO config (chave, valor) VALUES (?, ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor')->execute(['idface', json_encode($ic, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 9');
  }
  if ($v < 10) { // 0.74: entradas canceladas sem cobrar (quem cancelou, quando, o que deixou de ser cobrado e o motivo), para a supervisão conferir
    $pdo->exec("CREATE TABLE IF NOT EXISTS cancelamentos (id INTEGER PRIMARY KEY, crianca_id INTEGER NOT NULL, nome TEXT NOT NULL DEFAULT '', resp TEXT NOT NULL DEFAULT '', entrou TEXT NOT NULL, quando TEXT NOT NULL, minutos INTEGER NOT NULL DEFAULT 0, valor REAL NOT NULL DEFAULT 0, itens TEXT NOT NULL DEFAULT '', motivo TEXT NOT NULL DEFAULT '', por INTEGER, por_nome TEXT NOT NULL DEFAULT '', papel TEXT NOT NULL DEFAULT '', fora INTEGER NOT NULL DEFAULT 0, visto_por TEXT NOT NULL DEFAULT '', visto_em TEXT)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS cancelamentos_crianca ON cancelamentos (crianca_id, quando)");
    try { $pdo->exec('ALTER TABLE vendas ADD COLUMN registro INTEGER'); } catch (Throwable $e) { }   // a linha de "Hoje na recepção" que a venda gerou (para marcar "cancelada" se a entrada for cancelada)
    $pdo->exec('PRAGMA user_version = 10');
  }
  if ($v < 11) { // 0.75: cobrança por atraso gerada pelo sistema (vendas.auto) e nenhum aviso automático no WhatsApp (o responsável não acompanha o tempo pelo celular)
    try { $pdo->exec('ALTER TABLE vendas ADD COLUMN auto INTEGER NOT NULL DEFAULT 0'); } catch (Throwable $e) { }
    try {
      $q = $pdo->query("SELECT valor FROM config WHERE chave = 'regra'")->fetchColumn(); $rg = $q !== false ? json_decode((string)$q, true) : null;
      if (is_array($rg)) { $rg['autoMsg'] = 'nao'; unset($rg['arredonda'], $rg['cortesia'], $rg['minimo']); $pdo->prepare("UPDATE config SET valor = ? WHERE chave = 'regra'")->execute([json_encode($rg, JSON_UNESCAPED_UNICODE)]); }
    } catch (Throwable $e) { }
    try { // a cobrança automática só vale para entradas feitas daqui em diante: quem já estava marcado "no espaço" (saída esquecida de outro dia) não ganha cobrança de uma vez
      $pdo->prepare('INSERT INTO config (chave, valor) VALUES (?, ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor')->execute(['cobranca_desde', json_encode(date('Y-m-d H:i:s'))]);
      $pdo->exec('UPDATE criancas SET usado_min = pacote_min WHERE usado_min > pacote_min');   // a regra antiga deixava "dever" minutos: zera, senão o próximo período comprado não daria para entrar
    } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 11');
  }
  if ($v < 12) { // 0.76: criança PCD (paga metade na recreação) e venda com voucher (período sem cobrança, com o código anotado)
    try { $pdo->exec('ALTER TABLE criancas ADD COLUMN pcd INTEGER NOT NULL DEFAULT 0'); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE vendas ADD COLUMN voucher TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }
    try { $pdo->exec('ALTER TABLE vendas ADD COLUMN pcd INTEGER NOT NULL DEFAULT 0'); } catch (Throwable $e) { }
    try { $pdo->exec('ALTER TABLE vendas ADD COLUMN cheio REAL'); } catch (Throwable $e) { }   // o valor de tabela, quando a venda saiu com desconto PCD ou por voucher
    $pdo->exec('PRAGMA user_version = 12');
  }
  if ($v < 13) { // 0.81: os pacotes da loja, em dois tipos (com produto e seco). Os valores que a loja passou entram na tabela; o que já estava cadastrado de outros tamanhos fica
    try {
      $q = $pdo->query("SELECT valor FROM config WHERE chave = 'regra'")->fetchColumn(); $rg = $q !== false ? json_decode((string)$q, true) : null; if (!is_array($rg)) $rg = [];
      $atuais = (array)($rg['pacotes'] ?? []); if ($atuais == PACOTES_DE_EXEMPLO) $atuais = [];
      $ps = [];
      foreach ($atuais as $p) { $h = (int)($p['h'] ?? 0); if ($h <= 0) continue; $p['h'] = $h;
        if (!isset($p['tipo'])) $p['tipo'] = tipoPacote($p);   // pacote antigo, sem o tipo: é "com produto" se tem o nome do produto
        $ps[chavePacote($p)] = $p; }
      foreach (PACOTES_DA_LOJA as $l) { $k = chavePacote($l); $ps[$k] = $l + ($ps[$k] ?? []); }   // vale o valor que a loja passou; o nome do produto que já estava fica
      ksort($ps); $rg['pacotes'] = array_values($ps);
      $pdo->prepare("INSERT INTO config (chave, valor) VALUES ('regra', ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor")->execute([json_encode($rg, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 13');
  }
  if ($v < 14) { // 0.82: saem os três pacotes "com produto" instalados pela 0.81 (tabela antiga), se ninguém mexeu neles. O que a gestora cadastrou ou mudou fica
    try {
      $q = $pdo->query("SELECT valor FROM config WHERE chave = 'regra'")->fetchColumn(); $rg = $q !== false ? json_decode((string)$q, true) : null;
      if (is_array($rg) && is_array($rg['pacotes'] ?? null)) {
        $rg['pacotes'] = array_values(array_filter($rg['pacotes'], function ($p) {
          foreach (PACOTES_DA_TABELA_ANTIGA as [$h, $val, $parc]) if ((int)($p['h'] ?? 0) === $h && tipoPacote($p) === 'produto' && abs((float)($p['v'] ?? 0) - $val) < 0.005 && (int)($p['parc'] ?? 0) === $parc && trim((string)($p['com'] ?? '')) === '') return false;
          return true; }));
        $pdo->prepare("UPDATE config SET valor = ? WHERE chave = 'regra'")->execute([json_encode($rg, JSON_UNESCAPED_UNICODE)]);
      }
    } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 14');
  }
  if ($v < 15) { // 0.83: entram os pacotes do sistema que a loja usava. Quem já estava na tabela com o mesmo tempo e valor (o de 5 h por R$ 420) só ganha o nome
    try {
      $q = $pdo->query("SELECT valor FROM config WHERE chave = 'regra'")->fetchColumn(); $rg = $q !== false ? json_decode((string)$q, true) : null; if (!is_array($rg)) $rg = [];
      $ps = array_values(array_filter((array)($rg['pacotes'] ?? []), fn($p) => is_array($p) && (int)($p['h'] ?? 0) > 0));
      foreach (PACOTES_DO_SISTEMA_ANTIGO as [$h, $val, $nome]) { $achou = false;
        foreach ($ps as $k => $p) if ((int)$p['h'] === $h && abs((float)($p['v'] ?? 0) - $val) < 0.005) { $achou = true; if (trim((string)($p['nome'] ?? '')) === '') $ps[$k]['nome'] = $nome; break; }
        if (!$achou) $ps[] = ['h' => $h, 'v' => $val, 'tipo' => 'seco', 'nome' => $nome]; }
      $ord = []; foreach ($ps as $p) { $p['h'] = (int)$p['h']; $p['tipo'] = tipoPacote($p); $ord[chavePacote($p)] = $p; } ksort($ord); $rg['pacotes'] = array_values($ord);
      $pdo->prepare("INSERT INTO config (chave, valor) VALUES ('regra', ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor")->execute([json_encode($rg, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 15');
  }
  if ($v < 16) { // 0.84: os produtos da loja (nome, valor e estoque; estoque vazio = não controla) e a venda que sabe qual produto levou e quantos
    $pdo->exec("CREATE TABLE IF NOT EXISTS produtos (id INTEGER PRIMARY KEY, nome TEXT NOT NULL, valor REAL NOT NULL DEFAULT 0, estoque INTEGER, ativo INTEGER NOT NULL DEFAULT 1, criado_em TEXT, mudou_em TEXT)");
    try { $pdo->exec('ALTER TABLE vendas ADD COLUMN produto_id INTEGER'); } catch (Throwable $e) { }
    try { $pdo->exec('ALTER TABLE vendas ADD COLUMN qtd INTEGER NOT NULL DEFAULT 0'); } catch (Throwable $e) { }
    try { // entram os produtos do sistema que a loja usava, com o estoque que estava lá (a gestora confere a contagem)
      if (!(int)$pdo->query('SELECT COUNT(*) FROM produtos')->fetchColumn()) { $ins = $pdo->prepare('INSERT INTO produtos (nome, valor, estoque, ativo, criado_em, mudou_em) VALUES (?, ?, ?, 1, ?, ?)');
        foreach (PRODUTOS_DO_SISTEMA_ANTIGO as [$n, $val, $est]) $ins->execute([$n, $val, $est, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]); }
    } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 16');
  }
  if ($v < 17) { // 0.85: os nomes (criança e responsável) ficam sempre em maiúsculas: os que já estavam no banco são convertidos, nos cadastros e nas linhas do dia
    try { $pdo->beginTransaction(); nomesEmMaiusculas($pdo); $pdo->commit(); } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
    $pdo->exec('PRAGMA user_version = 17');
  }
  if ($v < 18) { // 0.89: o que sai da loja e volta (carrinho de bebê, fusquinha) fica na lista até ser devolvido; e tudo o que foi digitado passa a maiúsculas
    foreach (["ALTER TABLE vendas ADD COLUMN alug TEXT NOT NULL DEFAULT ''",        // o serviço que saiu (o id dele); vazio = venda comum
              'ALTER TABLE vendas ADD COLUMN alug_min INTEGER NOT NULL DEFAULT 0',   // o tempo comprado, em minutos (0 = sem hora para voltar)
              'ALTER TABLE vendas ADD COLUMN voltou TEXT'] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { } }   // quando voltou (vazio = ainda está fora)
    try { $pdo->exec("CREATE INDEX IF NOT EXISTS vendas_fora ON vendas (crianca_id) WHERE alug != '' AND voltou IS NULL"); } catch (Throwable $e) { }
    try { $pdo->exec('CREATE INDEX IF NOT EXISTS vendas_crianca ON vendas (crianca_id, id)'); } catch (Throwable $e) { }
    try { $pdo->beginTransaction(); textosEmMaiusculas($pdo); $pdo->commit(); } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); }
    $pdo->exec('PRAGMA user_version = 18');
  }
  if ($v < 19) { // 0.90: como cada venda foi paga (Pix, dinheiro, crédito, débito). Uma linha por venda e forma; apagar a venda apaga o pagamento dela
    $pdo->exec('CREATE TABLE IF NOT EXISTS pagamentos (id INTEGER PRIMARY KEY, venda_id INTEGER NOT NULL, crianca_id INTEGER NOT NULL, forma TEXT NOT NULL, valor REAL NOT NULL DEFAULT 0, quando TEXT NOT NULL, por INTEGER)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS pagamentos_venda ON pagamentos (venda_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS pagamentos_quando ON pagamentos (quando)');
    $pdo->exec('CREATE TRIGGER IF NOT EXISTS vendas_apaga_pagamentos AFTER DELETE ON vendas BEGIN DELETE FROM pagamentos WHERE venda_id = OLD.id; END');
    $pdo->exec('PRAGMA user_version = 19');
  }
  if ($v < 20) { // 0.91: a observação do cliente não se perde: ao mudar, o texto anterior continua guardado, com quem mudou e quando
    foreach (["ALTER TABLE criancas ADD COLUMN obs_antes TEXT NOT NULL DEFAULT ''", 'ALTER TABLE criancas ADD COLUMN obs_em TEXT', "ALTER TABLE criancas ADD COLUMN obs_por TEXT NOT NULL DEFAULT ''"] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { } }
    $pdo->exec('PRAGMA user_version = 20');
  }
  if ($v < 21) { // 0.93: o cupom fiscal de cada pagamento. Nasce "pendente" (a emitir); a equipe marca "emitido"; se a venda for desfeita depois de emitido, vira "cancelar"
    $pdo->exec("CREATE TABLE IF NOT EXISTS cupons (id INTEGER PRIMARY KEY AUTOINCREMENT, crianca_id INTEGER NOT NULL, nome TEXT NOT NULL DEFAULT '', resp TEXT NOT NULL DEFAULT '', cpf TEXT NOT NULL DEFAULT '', quando TEXT NOT NULL, origem TEXT NOT NULL DEFAULT 'venda',
      valor REAL NOT NULL DEFAULT 0, itens TEXT NOT NULL DEFAULT '[]', formas TEXT NOT NULL DEFAULT '[]', status TEXT NOT NULL DEFAULT 'pendente', numero TEXT NOT NULL DEFAULT '', motivo TEXT NOT NULL DEFAULT '', por TEXT NOT NULL DEFAULT '', resolvido_em TEXT, resolvido_por TEXT NOT NULL DEFAULT '')");
    $pdo->exec('CREATE INDEX IF NOT EXISTS cupons_status ON cupons (status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS cupons_quando ON cupons (quando)');
    try { $pdo->exec('ALTER TABLE pagamentos ADD COLUMN cupom_id INTEGER'); } catch (Throwable $e) { }   // de qual cupom é cada pagamento
    $pdo->exec('CREATE INDEX IF NOT EXISTS pagamentos_cupom ON pagamentos (cupom_id)');
    $pdo->exec('PRAGMA user_version = 21');
  }
  if ($v < 22) { // 0.98: a loja vai começar a fase de verdade e pediu para limpar os cadastros de teste. A limpeza não roda sozinha na atualização:
    // fica oferecida uma vez ao gestor, que vê o que vai sair e confirma com a senha. Banco que ainda não tem cadastro não oferece nada.
    try { if ((int)$pdo->query('SELECT COUNT(*) FROM criancas')->fetchColumn() > 0) $pdo->prepare("INSERT OR REPLACE INTO config (chave, valor) VALUES ('limpeza_oferta', ?)")->execute([json_encode(['em' => agora()])]); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 22');
  }
  if ($v < 23) { // 1.01: importar os clientes e as horas do sistema anterior. O cadastro importado guarda de qual importação veio, para o Desfazer tirar só ela
    try { $pdo->exec("ALTER TABLE criancas ADD COLUMN lote TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE vendas ADD COLUMN lote TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }   // e o pacote de horas que a importação trouxe também (inclusive para quem já estava cadastrado)
    try { $pdo->exec('CREATE INDEX IF NOT EXISTS visitas_crianca ON visitas (crianca_id)'); } catch (Throwable $e) { }   // com milhares de cadastros, achar as visitas de uma criança não pode varrer a tabela
    $pdo->exec('PRAGMA user_version = 23');
  }
  if ($v < 24) { // 1.04: desconto dado pelo gestor na tela de pagamento (em reais ou em percentual, com o motivo). A venda guarda quanto saiu do valor dela
    try { $pdo->exec('ALTER TABLE vendas ADD COLUMN desc_valor REAL NOT NULL DEFAULT 0'); } catch (Throwable $e) { }
    try { $pdo->exec("ALTER TABLE vendas ADD COLUMN desc_motivo TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }
    try { $pdo->exec('ALTER TABLE vendas ADD COLUMN desc_por INTEGER'); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 24');
  }
  if ($v < 25) { // 1.06: as unidades da Play Space (Anália Franco, Morumbi e as que vierem). A entrada, a visita, a venda e a linha do dia guardam a unidade do aparelho em que foram feitas
    $pdo->exec('CREATE TABLE IF NOT EXISTS unidades (id INTEGER PRIMARY KEY, nome TEXT NOT NULL, ativo INTEGER NOT NULL DEFAULT 1, criado_em TEXT, mudou_em TEXT)');
    try { if (!(int)$pdo->query('SELECT COUNT(*) FROM unidades')->fetchColumn()) { $q = $pdo->prepare('INSERT INTO unidades (nome, criado_em, mudou_em) VALUES (?, ?, ?)'); foreach (UNIDADES_DE_FABRICA as $n) $q->execute([$n, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]); } } catch (Throwable $e) { }
    foreach (['criancas', 'visitas', 'vendas', 'registros'] as $t) { try { $pdo->exec("ALTER TABLE $t ADD COLUMN unidade_id INTEGER"); } catch (Throwable $e) { } }
    $pdo->exec('PRAGMA user_version = 25');
  }
  if ($v < 26) { // 1.11: a data de nascimento da criança (opcional): a idade sai dela, sem ninguém fazer a conta
    try { $pdo->exec('ALTER TABLE criancas ADD COLUMN nascimento TEXT'); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 26');
  }
  if ($v < 27) { // 1.14: os cupons que saíram (as duas vias de cada um), para reimprimir pela ficha do cliente
    $pdo->exec("CREATE TABLE IF NOT EXISTS papeis (id INTEGER PRIMARY KEY, crianca_id INTEGER NOT NULL, quando TEXT NOT NULL, tipo TEXT NOT NULL DEFAULT '', titulo TEXT NOT NULL DEFAULT '', resumo TEXT NOT NULL DEFAULT '', vias BLOB NOT NULL, por INTEGER, unidade_id INTEGER)");
    $pdo->exec('CREATE INDEX IF NOT EXISTS papeis_crianca ON papeis (crianca_id, id)');
    $pdo->exec('PRAGMA user_version = 27');
  }
  if ($v < 28) { // 1.16: a sapateira é de cada visita (marcada na entrada, livre na saída): as que ficaram presas a quem está fora do espaço (escolhidas no cadastro) são soltas
    $pdo->exec('UPDATE criancas SET sapateira = NULL WHERE entrou IS NULL AND sapateira IS NOT NULL');
    $pdo->exec('PRAGMA user_version = 28');
  }
  if ($v < 29) { // 1.21: o que foi cancelado. '' = a entrada (no prazo, com o motivo); 'desfeita' = a entrada desfeita na hora por quem não estorna;
    // 'aluguel' = a saída do que acerta na devolução (1.20); 'venda' = uma venda, cancelada por quem estorna. Só o cancelamento da ENTRADA fecha
    // a visita (as vendas feitas antes dele deixam de ser dela): os outros ficam na lista de conferência e não mexem nessa conta
    try { $pdo->exec("ALTER TABLE cancelamentos ADD COLUMN tipo TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }
    try { $pdo->exec("UPDATE cancelamentos SET tipo = 'aluguel' WHERE tipo = '' AND itens LIKE '%\"tipo\":\"aluguel\"%'"); } catch (Throwable $e) { }
    try { $pdo->exec('ALTER TABLE visitas ADD COLUMN pag_de INTEGER'); } catch (Throwable $e) { }   // a saída que recebeu: o último pagamento que já existia antes dela (os dela vêm depois). Vazio = não recebeu nada
    $pdo->exec('PRAGMA user_version = 29');
  }
  if ($v < 30) { // 1.23: os pedidos de autorização (desconto, cancelar uma venda, nota fiscal): quem pediu, quem decidiu, quando e como
    $pdo->exec("CREATE TABLE IF NOT EXISTS pedidos (id INTEGER PRIMARY KEY, tipo TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pendente', crianca_id INTEGER, venda_id INTEGER, nome TEXT NOT NULL DEFAULT '', resp TEXT NOT NULL DEFAULT '',
      texto TEXT NOT NULL DEFAULT '', motivo TEXT NOT NULL DEFAULT '', valor REAL NOT NULL DEFAULT 0, usado REAL NOT NULL DEFAULT 0, dados TEXT NOT NULL DEFAULT '{}',
      pedido_por INTEGER, pedido_nome TEXT NOT NULL DEFAULT '', pedido_em TEXT NOT NULL, decidido_por INTEGER, decidido_nome TEXT NOT NULL DEFAULT '', decidido_em TEXT, como TEXT NOT NULL DEFAULT '',
      feito_por INTEGER, feito_nome TEXT NOT NULL DEFAULT '', feito_em TEXT, numero TEXT NOT NULL DEFAULT '', resultado TEXT NOT NULL DEFAULT '', unidade_id INTEGER)");
    $pdo->exec('CREATE INDEX IF NOT EXISTS pedidos_status ON pedidos (status, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS pedidos_venda ON pedidos (venda_id)');
    // a venda apagada (cancelada, desfeita, limpeza) solta os pedidos dela: o número de uma venda apagada pode voltar numa venda nova
    $pdo->exec("CREATE TRIGGER IF NOT EXISTS vendas_solta_pedidos AFTER DELETE ON vendas BEGIN UPDATE pedidos SET venda_id = NULL, status = CASE WHEN status IN ('pendente', 'liberado') THEN 'cancelado' ELSE status END, resultado = CASE WHEN resultado = '' THEN 'A VENDA SAIU' ELSE resultado END WHERE venda_id = OLD.id; END");
    $pdo->exec('PRAGMA user_version = 30');
  }
  if ($v < 31) { // 1.25: quem cancelou (o nome da atendente: o aparelho fica num usuário só) e em que unidade; e os índices das contas do dia (o painel da gestora, os finalizados)
    try { $pdo->exec("ALTER TABLE cancelamentos ADD COLUMN atendente TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }
    try { $pdo->exec('ALTER TABLE cancelamentos ADD COLUMN unidade_id INTEGER'); } catch (Throwable $e) { }
    $pdo->exec('CREATE INDEX IF NOT EXISTS cancelamentos_quando ON cancelamentos (quando)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS visitas_saiu ON visitas (saiu)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS vendas_quando ON vendas (quando)');   // (o faturado do dia do painel e as contas de 24 h)
    $pdo->exec("CREATE INDEX IF NOT EXISTS vendas_voltou ON vendas (voltou) WHERE alug != '' AND voltou IS NOT NULL");
    $pdo->exec('PRAGMA user_version = 31');
  }
  if ($v < 32) { // 1.29: a festa marcada (data, unidade, tema, aniversariante, horário) e quem entrou nela. As crianças do pacote (n) e o
    // adicional (extra) não ficam na festa: saem das vendas ligadas a ela (vendas.festa_id). A festa nova e o upgrade guardam em festa_n a
    // quantidade de crianças da festa depois dela; o adicional guarda em qtd quantas crianças a mais. Venda cancelada: a festa acompanha sozinha
    $pdo->exec("CREATE TABLE IF NOT EXISTS festas (id INTEGER PRIMARY KEY, crianca_id INTEGER NOT NULL, unidade_id INTEGER, data TEXT NOT NULL, hora TEXT NOT NULL DEFAULT '', tema TEXT NOT NULL DEFAULT '', dia TEXT NOT NULL DEFAULT 'semana', criado_em TEXT, por INTEGER, mudou_em TEXT)");
    $pdo->exec('CREATE INDEX IF NOT EXISTS festas_data ON festas (data)');
    $pdo->exec("CREATE TABLE IF NOT EXISTS festa_presencas (id INTEGER PRIMARY KEY, festa_id INTEGER NOT NULL, crianca_id INTEGER NOT NULL, papel TEXT NOT NULL DEFAULT 'convidado', quando TEXT NOT NULL, por INTEGER, a_mais INTEGER NOT NULL DEFAULT 0, UNIQUE (festa_id, crianca_id))");
    foreach (['ALTER TABLE vendas ADD COLUMN festa_id INTEGER', 'ALTER TABLE vendas ADD COLUMN festa_n INTEGER NOT NULL DEFAULT 0', 'ALTER TABLE criancas ADD COLUMN festa_id INTEGER', 'ALTER TABLE visitas ADD COLUMN festa_id INTEGER'] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { } }
    $pdo->exec('CREATE INDEX IF NOT EXISTS vendas_festa ON vendas (festa_id) WHERE festa_id IS NOT NULL');
    $pdo->exec('CREATE INDEX IF NOT EXISTS criancas_festa ON criancas (festa_id) WHERE festa_id IS NOT NULL');   // (quem está numa festa: a conta de cada festa)
    // as festas já vendidas com a data escrita, de hoje em diante, entram no calendário (o tema e o horário ficam para a equipe pôr)
    try {
      $hoje = date('Y-m-d'); $ins = $pdo->prepare('INSERT INTO festas (crianca_id, unidade_id, data, dia, criado_em, mudou_em) VALUES (?, ?, ?, ?, ?, ?)');
      foreach ($pdo->query("SELECT * FROM vendas WHERE festa_id IS NULL AND servico IN ('FESTA DE SEMANA', 'FESTA DE FINAL DE SEMANA') AND itens LIKE '%festa em %' ORDER BY id")->fetchAll() as $x) {
        if (!preg_match('/^(\d+) crianças.*festa em (\d\d)\/(\d\d)\/(\d{4})/u', (string)$x['itens'], $m)) continue; $data = $m[4] . '-' . $m[3] . '-' . $m[2]; if ($data < $hoje) continue;
        $ins->execute([(int)$x['crianca_id'], $x['unidade_id'], $data, $x['servico'] === 'FESTA DE SEMANA' ? 'semana' : 'fds', $x['quando'], $x['quando']]); $fid = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE vendas SET festa_id = ?, festa_n = ? WHERE id = ?')->execute([$fid, (int)$m[1], (int)$x['id']]);
        $j = $pdo->prepare("SELECT id, itens FROM vendas WHERE festa_id IS NULL AND servico = 'ADICIONAL DE FESTA' AND crianca_id = ? AND id > ? AND quando >= ? AND quando <= datetime(?, '+5 seconds')"); $j->execute([(int)$x['crianca_id'], (int)$x['id'], $x['quando'], $x['quando']]);   // o adicional vendido junto (sem a data no texto)
        foreach ($j->fetchAll() as $y) if (preg_match('/^(\d+) criança/u', (string)$y['itens'], $k)) $pdo->prepare('UPDATE vendas SET festa_id = ?, qtd = ? WHERE id = ?')->execute([$fid, (int)$k[1], (int)$y['id']]);
        $q = $pdo->prepare("SELECT id, servico, itens FROM vendas WHERE festa_id IS NULL AND id > ? AND servico IN ('UPGRADE DE FESTA', 'ADICIONAL DE FESTA') AND itens LIKE ? AND crianca_id = ?"); $q->execute([(int)$x['id'], '%festa em ' . $m[2] . '/' . $m[3] . '/' . $m[4] . '%', (int)$x['crianca_id']]);
        foreach ($q->fetchAll() as $y) { if ($y['servico'] === 'UPGRADE DE FESTA' && preg_match('/^de \d+ para (\d+) crianças/u', (string)$y['itens'], $k)) $pdo->prepare('UPDATE vendas SET festa_id = ?, festa_n = ? WHERE id = ?')->execute([$fid, (int)$k[1], (int)$y['id']]);
          elseif ($y['servico'] === 'ADICIONAL DE FESTA' && preg_match('/^(\d+) criança/u', (string)$y['itens'], $k)) $pdo->prepare('UPDATE vendas SET festa_id = ?, qtd = ? WHERE id = ?')->execute([$fid, (int)$k[1], (int)$y['id']]); }
      }
    } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 32');
  }
  if ($v < 33) { // 1.31: o caixa. O pagamento guarda a unidade em que foi recebido; a devolução de um pagamento de outro dia (a venda cancelada
    // hoje) fica anotada em estornos, para sair do caixa do dia em que o dinheiro voltou ao cliente
    try { $pdo->exec('ALTER TABLE pagamentos ADD COLUMN unidade_id INTEGER'); } catch (Throwable $e) { }
    $pdo->exec("CREATE TABLE IF NOT EXISTS estornos (id INTEGER PRIMARY KEY, quando TEXT NOT NULL, unidade_id INTEGER, forma TEXT NOT NULL, valor REAL NOT NULL, grupo TEXT NOT NULL DEFAULT 'outros', item TEXT NOT NULL DEFAULT '', pago_em TEXT, venda_id INTEGER, crianca_id INTEGER)");
    $pdo->exec('CREATE INDEX IF NOT EXISTS estornos_quando ON estornos (quando)');
    $pdo->exec('PRAGMA user_version = 33');
  }
  if ($v < 34) { // 1.32: os avisos no celular (Web Push): cada aparelho com os avisos ligados, de quem é, e as chaves dele (a chave da loja fica em config)
    $pdo->exec("CREATE TABLE IF NOT EXISTS push_inscricoes (id INTEGER PRIMARY KEY, usuario_id INTEGER NOT NULL, endpoint TEXT NOT NULL UNIQUE, p256dh TEXT NOT NULL, auth TEXT NOT NULL, aparelho TEXT NOT NULL DEFAULT '', criado_em TEXT NOT NULL, ultimo_ok TEXT, falhas INTEGER NOT NULL DEFAULT 0)");
    $pdo->exec('PRAGMA user_version = 34');
  }
  if ($v < 35) { // 1.49: o desconto guarda de qual linha do dia ele é (cada desconto dado conta uma vez nas Finanças)
    try { $pdo->exec('ALTER TABLE vendas ADD COLUMN desc_reg INTEGER'); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 35');
  }
  if ($v < 36) { // 1.49: de 30 em 30 s, quem falou com o servidor (a conferência da hora "sem internet"; guarda só 2 dias)
    $pdo->exec('CREATE TABLE IF NOT EXISTS usuarios_vistos (uid INTEGER NOT NULL, slot INTEGER NOT NULL, PRIMARY KEY (uid, slot)) WITHOUT ROWID');
    $pdo->exec('PRAGMA user_version = 36');
  }
  if ($v < 37) { // 1.57: as trocas da forma de pagamento (o gestor vê o aviso de cada troca feita pela equipe)
    $pdo->exec("CREATE TABLE IF NOT EXISTS trocas_pag (id INTEGER PRIMARY KEY, quando TEXT NOT NULL, crianca_id INTEGER, nome TEXT NOT NULL DEFAULT '', resp TEXT NOT NULL DEFAULT '', de TEXT NOT NULL, para TEXT NOT NULL, valor REAL NOT NULL DEFAULT 0,
      pago_em TEXT, por INTEGER, por_nome TEXT NOT NULL DEFAULT '', papel TEXT NOT NULL DEFAULT '', unidade_id INTEGER, visto_em TEXT, visto_por TEXT NOT NULL DEFAULT '')");
    $pdo->exec('PRAGMA user_version = 37');
  }
  if ($v < 38) { // 1.62: em quantas vezes a festa foi no crédito (0 = a venda não disse). As Finanças mostram na tela das Festas
    try { $pdo->exec('ALTER TABLE vendas ADD COLUMN parcelas INTEGER NOT NULL DEFAULT 0'); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 38');
  }
  if ($v < 39) { /* 1.67: quem vendeu (a vendedora escolhida no atendimento), o que conta para a comissão (pacote, pacote com produto, produto) e o atendimento de que a venda fez parte */
    foreach (["ALTER TABLE vendas ADD COLUMN vendedora TEXT NOT NULL DEFAULT ''", "ALTER TABLE vendas ADD COLUMN vend_k TEXT NOT NULL DEFAULT ''", 'ALTER TABLE vendas ADD COLUMN vend_g INTEGER'] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { } }
    $pdo->exec('PRAGMA user_version = 39');
  }
  if ($v < 40) { /* 1.68: quem abriu o pedido (a vendedora que atendeu na entrada): a comissão do que for vendido na visita, até na saída, é dela */
    foreach (["ALTER TABLE criancas ADD COLUMN vend_abriu TEXT NOT NULL DEFAULT ''", "ALTER TABLE visitas ADD COLUMN vendedora TEXT NOT NULL DEFAULT ''"] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { } }
    $pdo->exec('PRAGMA user_version = 40');
  }
  if ($v < 41) { /* 1.71: a forma de pagamento dividida em duas ou mais ("o cliente desmembrou"): as partes da troca, para o aviso do gestor */
    try { $pdo->exec("ALTER TABLE trocas_pag ADD COLUMN partes TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 41');
  }
  if ($v < 42) { /* 1.91 (auditoria): o estorno guarda a unidade em que o dinheiro tinha entrado; a unidade, o dia em que virou Casa Tom;
       e de quando em quando cada unidade falou com o servidor (para a hora do "feito sem internet") */
    foreach (['ALTER TABLE estornos ADD COLUMN pago_uni INTEGER', 'ALTER TABLE unidades ADD COLUMN ct_desde TEXT'] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { } }
    try { foreach ($pdo->query('SELECT id, nome, criado_em FROM unidades')->fetchAll() as $u) if (preg_match('/CASA\s*TO[MN]\b/u', mb_strtoupper((string)$u['nome']))) {
      $cr = substr((string)($u['criado_em'] ?? ''), 0, 10); $pdo->prepare('UPDATE unidades SET ct_desde = ? WHERE id = ?')->execute([preg_match('/^\d{4}-\d{2}-\d{2}$/', $cr) ? date('Y-m-d', strtotime($cr . ' +1 day')) : '2000-01-01', (int)$u['id']]); } } catch (Throwable $e) { }
    $pdo->exec('CREATE TABLE IF NOT EXISTS unidades_vistas (u INTEGER NOT NULL, slot INTEGER NOT NULL, PRIMARY KEY (u, slot)) WITHOUT ROWID');
    $pdo->exec('PRAGMA user_version = 42');
  }
  if ($v < 43) { /* 2.00: em que loja o produto é vendido (play, ct ou as duas) e a categoria (a Casa Tom) */
    foreach (["ALTER TABLE produtos ADD COLUMN lojas TEXT NOT NULL DEFAULT 'play'", "ALTER TABLE produtos ADD COLUMN cat TEXT NOT NULL DEFAULT ''"] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { } }
    $pdo->exec('PRAGMA user_version = 43');
  }
  if ($v < 44) { /* 2.02: a unidade de cada pessoa (Ajustes → Equipe): quem tem a unidade escolhida fica nela; só o gestor troca */
    try { $pdo->exec('ALTER TABLE usuarios ADD COLUMN unidade_id INTEGER'); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 44');
  }
  if ($v < 45) { /* 2.08: quem fez o cadastro (conta nas Comissões) e em que unidade */
    foreach (["ALTER TABLE criancas ADD COLUMN cad_por TEXT NOT NULL DEFAULT ''", 'ALTER TABLE criancas ADD COLUMN cad_uni INTEGER'] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { } }
    $pdo->exec('PRAGMA user_version = 45');
  }
  if ($v < 46) { /* 2.10: as horas da família que passaram de uma criança para a visita avulsa do irmão (o Desfazer da saída devolve) */
    $pdo->exec('CREATE TABLE IF NOT EXISTS fam_horas (id INTEGER PRIMARY KEY, quando TEXT NOT NULL, para INTEGER NOT NULL, de INTEGER NOT NULL, minutos INTEGER NOT NULL, reg_de INTEGER, reg_para INTEGER, desfeito INTEGER NOT NULL DEFAULT 0)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS fam_horas_para ON fam_horas (para, quando)');
    $pdo->exec('PRAGMA user_version = 46');
  }
  if ($v < 47) { /* 2.10: os computadores da loja (entram pela unidade, sem senha) */
    try { $pdo->exec('ALTER TABLE usuarios ADD COLUMN loja INTEGER NOT NULL DEFAULT 0'); } catch (Throwable $e) { }
    $pdo->exec("CREATE TABLE IF NOT EXISTS maquinas (id INTEGER PRIMARY KEY, token TEXT NOT NULL UNIQUE, nome TEXT NOT NULL DEFAULT '', criado_em TEXT NOT NULL, criado_por INTEGER, ultimo_uso TEXT, ativo INTEGER NOT NULL DEFAULT 1)");
    $pdo->exec('PRAGMA user_version = 47');
  }
  if ($v < 48) { /* 2.11: as horas do CPF que passaram na entrada (tipo 'ent': o Desfazer da saída não mexe nelas) */
    try { $pdo->exec("ALTER TABLE fam_horas ADD COLUMN tipo TEXT NOT NULL DEFAULT 'sai'"); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 48');
  }
  if ($v < 49) { /* 2.11: a "loja" de cada unidade entra com senha (de fábrica 123456) */
    foreach ($pdo->query('SELECT id FROM usuarios WHERE loja = 1')->fetchAll(PDO::FETCH_COLUMN) as $id) $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash('123456', PASSWORD_DEFAULT), (int)$id]);
    $pdo->exec('PRAGMA user_version = 49');
  }
  if ($v < 50) { /* 2.12: as anotações do cliente */
    $pdo->exec("CREATE TABLE IF NOT EXISTS anotacoes (id INTEGER PRIMARY KEY, crianca_id INTEGER NOT NULL, texto TEXT NOT NULL, quando TEXT NOT NULL, por TEXT NOT NULL DEFAULT '', por_id INTEGER, apagada INTEGER NOT NULL DEFAULT 0)");
    $pdo->exec('CREATE INDEX IF NOT EXISTS anotacoes_crianca ON anotacoes (crianca_id)');
    $pdo->exec('PRAGMA user_version = 50');
  }
  if ($v < 51) { /* 2.22: aparece em "Quem está vendendo" (vazio = pelo papel). 2.23: a 2.22 pôs no passo 47 e o banco da loja (já no 50) ficou sem a coluna */
    try { $pdo->exec('ALTER TABLE usuarios ADD COLUMN vende INTEGER'); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 51');
  }
  if ($v < 52) { /* 2.35: o lugar do computador da loja (ANALIA, MORUMBI; vazio = ainda não escolhido) */
    try { $pdo->exec("ALTER TABLE maquinas ADD COLUMN local TEXT NOT NULL DEFAULT ''"); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 52');
  }
  if ($v < 53) { /* 2.40: qual produto foi no pacote (escrito no "Alterar pacote") e o valor dele, para as Finanças verem o que mais sai */
    foreach (["ALTER TABLE vendas ADD COLUMN prod_nome TEXT NOT NULL DEFAULT ''", 'ALTER TABLE vendas ADD COLUMN prod_valor REAL NOT NULL DEFAULT 0'] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { } }
    $pdo->exec('PRAGMA user_version = 53');
  }
  if ($v < 54) { /* 2.45: o cupom guardado sabe de quais vendas ele é (a Casa Tom corrige ou exclui a venda pelos Vendidos do dia) e se foi corrigido ou excluído */
    foreach (["ALTER TABLE papeis ADD COLUMN vendas TEXT NOT NULL DEFAULT ''", "ALTER TABLE papeis ADD COLUMN estado TEXT NOT NULL DEFAULT ''"] as $sql) { try { $pdo->exec($sql); } catch (Throwable $e) { } }
    $pdo->exec('PRAGMA user_version = 54');
  }
  if ($v < 55) { /* 2.60 (o dono: "quando eu trocar senha, eu quero que deslogue de todos os computadores"): o número da sessão de cada usuário */
    try { $pdo->exec('ALTER TABLE usuarios ADD COLUMN sessao INTEGER NOT NULL DEFAULT 0'); } catch (Throwable $e) { }
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS fin_usuarios (id INTEGER PRIMARY KEY, nome TEXT NOT NULL, login TEXT NOT NULL UNIQUE COLLATE NOCASE, senha TEXT NOT NULL, criado_em TEXT NOT NULL, ultimo_login TEXT)"); $pdo->exec('ALTER TABLE fin_usuarios ADD COLUMN sessao INTEGER NOT NULL DEFAULT 0'); } catch (Throwable $e) { }
    $pdo->exec('PRAGMA user_version = 55');
  }
  return $pdo;
}
/* ---------- 1.06: unidades ---------- */
// A Play Space tem mais de uma unidade. O gestor cadastra as unidades em Ajustes; cada aparelho guarda em qual delas está e manda em todo
// pedido (cabeçalho X-Play-Unidade). O servidor confere se a unidade existe e guarda na entrada, na venda e na linha do dia.
// Unidade removida não some dos registros antigos: fica inativa (some só das escolhas).
const UNIDADES_DE_FABRICA = ['ANÁLIA FRANCO', 'MORUMBI'];
const UNIDADES_MAX = 30;
function unidadePublica(array $u): array { return ['id' => (int)$u['id'], 'nome' => (string)$u['nome'], 'ativo' => !empty($u['ativo']), 'criado' => substr((string)($u['criado_em'] ?? ''), 0, 10)]; }   // 1.88: o dia em que foi criada (a Casa Tom: a regra nova vale do dia seguinte)
/* 1.88: a Casa Tom de cada lugar como unidade (CASA TOM ANÁLIA, CASA TOM MORUMBI). Pedido do dono: "quem está no Morumbi troca entre a
   unidade Morumbi e a Casa Tom Morumbi; quem está na Anália, entre a Anália e a Casa Tom Anália". Escolhas dele: o que é vendido com o
   aparelho na Casa Tom é da Casa Tom; com o aparelho no Play, é do Play (até produto); o gestor e o supervisor trocam para qualquer uma.
   O lugar de uma unidade: o nome sem o "Casa Tom", a primeira palavra (ANÁLIA FRANCO e CASA TOM ANÁLIA são da Anália). A regra nova vale
   a partir do dia seguinte ao que a Casa Tom do lugar foi criada; antes, o produto vendido no Play era da Casa Tom (como era). */
function uniNorm(string $n): string { return strtoupper(strtr(mb_strtoupper(trim($n), 'UTF-8'), ['Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C'])); }
function uniEhCasaTom(string $nome): bool { return (bool)preg_match('/CASA\s*TO[MN]\b/', uniNorm($nome)); }
function uniLocal(string $nome): string { $n = trim((string)preg_replace('/CASA\s*TO[MN]\b/', '', uniNorm($nome))); $p = preg_split('/\s+/', $n); return (string)($p[0] ?? ''); }
/* os pares: [unidade do Play => ['ct' => a Casa Tom do lugar, 'desde' => o primeiro dia da regra nova]] (só os lugares que têm as duas) */
function uniMesmoLugar(int $a, int $b): bool {   // 1.91: duas unidades do mesmo lugar (MORUMBI e CASA TOM MORUMBI)
  if ($a === $b) return true; $q = db()->prepare('SELECT id, nome FROM unidades WHERE id IN (?, ?)'); $q->execute([$a, $b]); $n = []; foreach ($q->fetchAll() as $u) $n[(int)$u['id']] = uniLocal((string)$u['nome']);
  return isset($n[$a], $n[$b]) && $n[$a] !== '' && $n[$a] === $n[$b];
}
/* 2.37 (o dono: "No Anália ... Lá o play é separado da casa Tom"): em cada lugar, o Play e a Casa Tom juntos (troca por dentro) ou separados.
   De fábrica, a Anália separada e os outros juntos; o gestor muda em Ajustes → Computadores da loja (regra ctJunto: lugar → sim | nao) */
function uniParJunto(string $local): bool { $j = (array)(regra()['ctJunto'] ?? []); return isset($j[$local]) ? $j[$local] === 'sim' : $local !== 'ANALIA'; }
function uniPar(int $a, int $b): bool { if ($a === $b) return true; if (!uniMesmoLugar($a, $b)) return false; $q = db()->prepare('SELECT nome FROM unidades WHERE id = ?'); $q->execute([$a]); return uniParJunto(uniLocal((string)$q->fetchColumn())); }
function uniCasaTomPares(): array {   /* 1.91 (auditoria): com as unidades apagadas também (o passado não muda) e o dia em que virou Casa Tom (ct_desde) */
  $L = array_filter(db()->query('SELECT id, nome, criado_em, ct_desde FROM unidades ORDER BY ativo DESC, id')->fetchAll(), fn($u) => uniLocal((string)$u['nome']) !== ''); $o = [];
  foreach ($L as $p) { if (uniEhCasaTom((string)$p['nome'])) continue;
    foreach ($L as $c) if (uniEhCasaTom((string)$c['nome']) && uniLocal((string)$c['nome']) === uniLocal((string)$p['nome'])) {
      if (isset($o[(int)$p['id']])) break; $cd = (string)($c['ct_desde'] ?? ''); $cr = substr((string)($c['criado_em'] ?? ''), 0, 10);
      $o[(int)$p['id']] = ['ct' => (int)$c['id'], 'desde' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $cd) ? $cd : (preg_match('/^\d{4}-\d{2}-\d{2}$/', $cr) ? date('Y-m-d', strtotime($cr . ' +1 day')) : '2000-01-01')]; break; } }
  return $o;
}

function unidadesDaAnalia(): array { $o = []; try { foreach (db()->query('SELECT id, nome FROM unidades WHERE ativo = 1')->fetchAll() as $u) if (uniLocal((string)$u['nome']) === 'ANALIA') $o[] = (int)$u['id']; } catch (Throwable $e) { } return $o; }   /* 1.99 (a Anália e a Casa Tom Anália) */
function unidades(): array { return array_map('unidadePublica', db()->query('SELECT * FROM unidades ORDER BY ativo DESC, id')->fetchAll()); }   // as ativas primeiro; as inativas vão junto, para a tela saber o nome das antigas
function unidadeDoPedido(): ?int {
  if (!array_key_exists('_unidade', $GLOBALS)) {
    $id = (int)($_SERVER['HTTP_X_PLAY_UNIDADE'] ?? 0); $GLOBALS['_unidade'] = null;
    if ($id > 0) { $q = db()->prepare('SELECT id FROM unidades WHERE id = ? AND ativo = 1'); $q->execute([$id]); if ($q->fetchColumn()) $GLOBALS['_unidade'] = $id; }
    /* 1.91 (auditoria): a atendente fica na unidade em que entrou e na Casa Tom do mesmo lugar (a tela já não deixava trocar; o servidor
       também não deixa mais): outra unidade no cabeçalho vale como a de quando entrou */
    $u0 = (int)($_SESSION['uni0'] ?? 0); $eu = $u0 > 0 && $GLOBALS['_unidade'] !== null && $GLOBALS['_unidade'] !== $u0 ? eu() : null;
    if ($eu && !in_array($eu['papel'], ['gestor', 'supervisor'], true) && !uniPar($u0, (int)$GLOBALS['_unidade'])) {   /* 2.37: no lugar separado, só a unidade em que entrou */
      $q = db()->prepare('SELECT id FROM unidades WHERE id = ? AND ativo = 1'); $q->execute([$u0]); $GLOBALS['_unidade'] = $q->fetchColumn() ? $u0 : null; }
    /* 2.02: a pessoa com a unidade escolhida pelo gestor (Ajustes → Equipe) fica nela, venha o pedido de que aparelho vier; só o gestor troca */
    $eu2 = eu(); $fixa = $eu2 && $eu2['papel'] !== 'gestor' ? (int)($eu2['unidade_id'] ?? 0) : 0;
    /* 2.35 (o dono: "Morumbi só visualiza Casatom Morumbi e Morumbi"): e a Casa Tom do mesmo lugar */
    if ($fixa > 0 && !($GLOBALS['_unidade'] !== null && $GLOBALS['_unidade'] !== $fixa && uniPar($fixa, (int)$GLOBALS['_unidade']))) { $q = db()->prepare('SELECT id FROM unidades WHERE id = ? AND ativo = 1'); $q->execute([$fixa]); if ($q->fetchColumn()) $GLOBALS['_unidade'] = $fixa; }
  }
  return $GLOBALS['_unidade'];
}
function cfg(string $k, $padrao = null) { $q = db()->prepare('SELECT valor FROM config WHERE chave = ?'); $q->execute([$k]); $v = $q->fetchColumn(); return $v === false ? $padrao : json_decode((string)$v, true); }
function gravarCfg(string $k, $v): void { if ($k === 'regra') unset($GLOBALS['_regra']); if ($k === 'exemplo') unset($GLOBALS['_exemplo']); db()->prepare('INSERT INTO config (chave, valor) VALUES (?, ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor')->execute([$k, json_encode($v, JSON_UNESCAPED_UNICODE)]); }
function apagarCfg(string $k): void { db()->prepare('DELETE FROM config WHERE chave = ?')->execute([$k]); }
function cfgComo(string $prefixo): array { $q = db()->prepare("SELECT chave, valor FROM config WHERE chave LIKE ?"); $q->execute([$prefixo . '%']); $o = []; foreach ($q->fetchAll() as $r) $o[$r['chave']] = json_decode((string)$r['valor'], true); return $o; }

/* ---------- o endereço público desta instalação ---------- */
function https(): bool { return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); }
function urlBase(): string { // .../playspace
  $dir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/playspace/api/x.php')), '/');
  return (https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}

/* ---------- a regra das horas (as mesmas opções da tela de Ajustes) ---------- */
// os serviços da loja. modo 'periodo': cobra a cada `min` minutos; 'fixo': valor único. banco: o tempo comprado entra no saldo de brincar da criança
const SERVICOS_PADRAO = [
  ['id' => 'recreacao', 'n' => 'RECREAÇÃO', 'v' => 80, 'modo' => 'periodo', 'min' => 30, 'banco' => true, 'obs' => '', 'volta' => false],
  ['id' => 'fusquinha', 'n' => 'CARRINHO FUSQUINHA', 'v' => 15, 'modo' => 'periodo', 'min' => 30, 'banco' => false, 'obs' => '', 'volta' => true],
  ['id' => 'bebe', 'n' => 'CARRINHO DE BEBÊ', 'v' => 30, 'modo' => 'fixo', 'min' => 0, 'banco' => false, 'obs' => 'DAS 10H ÀS 22H', 'volta' => true],
];
// volta: o serviço sai da loja e volta (o carrinho, o fusquinha). Vendido, fica na lista da tela inicial até alguém marcar "Devolveu".
// Serviço cadastrado antes da 0.89, sem essa marca: vale para os dois carrinhos que a loja já tinha
const SERVICOS_QUE_VOLTAM = ['fusquinha', 'bebe'];
// o que a loja já passou da tabela de pacotes: o pacote seco de 5 h (R$ 420). Os outros valores a gestora coloca (a tabela nova ainda vem). parc = em até quantas vezes
const PACOTES_DA_LOJA = [['h' => 5, 'v' => 420, 'tipo' => 'seco']];
// os três valores "com produto" que a 0.81 instalou a partir de uma tabela antiga: [horas, valor, parcelas]. A 0.82 tira os que continuam como foram instalados
const PACOTES_DA_TABELA_ANTIGA = [[5, 680.0, 3], [10, 1220.0, 4], [15, 1720.0, 4]];
// o tipo de um pacote: 'produto' (vem com o produto, que já está no valor) ou 'seco' (só as horas). Pacote antigo, sem o tipo: é "com produto" se tem o nome do produto
function tipoPacote(array $p): string { return ($p['tipo'] ?? (trim((string)($p['com'] ?? '')) !== '' ? 'produto' : 'seco')) === 'produto' ? 'produto' : 'seco'; }
// a ordem da lista (e o que faz dois pacotes serem o mesmo): por horas, depois por valor, com produto antes do seco, e o nome
function chavePacote(array $p): string { return sprintf('%05d-%012.2f-%d-%s', (int)$p['h'], (float)($p['v'] ?? 0), tipoPacote($p) === 'produto' ? 0 : 1, mb_strtolower(trim((string)($p['nome'] ?? '')))); }
// os pacotes do sistema que a loja usava (tela "Venda de pacotes de tempo", novembro de 2026): [horas, valor da coluna Valor, o nome que tinham lá]
const PACOTES_DO_SISTEMA_ANTIGO = [[2, 280.0, '02 HORAS 260'], [3, 225.0, '03 HORAS 360'], [4, 380.0, '04 HORAS 420'], [5, 390.0, '05 HORAS 360 ESPECIAL'], [5, 500.0, '05 HORAS 380'], [5, 420.0, 'PACOTE 05 HORAS 420'], [5, 480.0, '05 HORAS 450'], [10, 820.0, '10 HORAS 840']];
// os produtos do sistema que a loja usava (tela "Produtos disponíveis", novembro de 2026): [nome, valor da coluna Valor, estoque da coluna Estoque].
// Lá o nome trazia um número que quase nunca bate com o valor ("AMIGURUMI 230" custa R$ 320): aqui fica só o nome. As duas "BONECA ARTICULADA 150" têm valores diferentes
const PRODUTOS_DO_SISTEMA_ANTIGO = [['Amigurumi', 320.0, 8], ['Boneca articulada', 150.0, 19], ['Boneca articulada', 230.0, 40], ['Kit bijoux', 230.0, 10], ['Kit laço', 260.0, 8], ['Maleta', 350.0, 20], ['Maquiagem', 160.0, 4]];
// como a venda de um pacote com nome aparece: o nome vira o "serviço" da venda ("Pacote 05 HORAS 380 · 5h")
function servicoDoPacote(array $p): string { $n = trim((string)($p['nome'] ?? '')); return $n === '' ? '' : (preg_match('/^pacote\b/iu', $n) ? $n : 'PACOTE ' . $n); }
function itensDoPacote(array $p): string { $prod = tipoPacote($p) === 'produto'; $com = trim((string)($p['com'] ?? ''));
  return trim((string)($p['nome'] ?? '')) === '' ? ($prod ? ($com ?: 'produto') : 'seco') : ($prod ? 'com ' . ($com ?: 'produto') : ''); }
// como a venda de um pacote aparece: "Pacote de 5h com produto", "Pacote seco de 5h", "Hora avulsa"
function nomePacoteVenda(int $min, string $itens): string { if ($itens === 'seco') return 'Pacote seco de ' . dur($min); return ($min === 60 ? 'Hora avulsa' : 'Pacote de ' . dur($min)) . ($itens !== '' ? ' com ' . $itens : ''); }
const PACOTES_DE_EXEMPLO = [['h' => 1, 'v' => 60], ['h' => 5, 'v' => 250], ['h' => 10, 'v' => 450], ['h' => 20, 'v' => 800]];   // inventados nas primeiras versões: saem sozinhos se ninguém mexeu neles
// cancelaMin: até quantos minutos depois da entrada o próprio atendente cancela sem cobrar (0 = só gestor e supervisor cancelam)
// tolerancia: quantos minutos depois do fim do tempo comprado a criança ainda pode ficar sem gerar nova cobrança
// autoMsg 'nao': nenhum aviso automático no WhatsApp (a loja não quer que o responsável fique acompanhando o tempo)
// fecha: a hora em que a loja fecha (24 = meia-noite): depois dela o tempo de quem ficou marcado no espaço para de contar
// pcdDesc: o desconto, em %, da criança PCD na recreação avulsa (o serviço por período e a cobrança por atraso); pacote de horas e carrinhos não têm
// reabreMin: até quantos minutos depois da saída a equipe reabre a visita (a criança chorou e o responsável deixou ficar mais); 0 = não reabre
// cupom 'sim': cada pagamento vira uma nota fiscal a emitir, que a equipe acompanha na tela (0.93). Fica guardado: desde a 0.94 a tela não tem mais o botão que liga
// cupomLoja, cupomTel, cupomAviso (0.94): o nome e o telefone da loja e o aviso do pé do cupom impresso (o papel que o responsável assina na entrada)
// estorno (1.21): quem desfaz ou cancela o que já tem valor. 'gestor' (de fábrica), 'supervisor' (o gestor e o supervisor) ou 'todos' (como era até a 1.20)
const REGRA_PADRAO = ['tolerancia' => 9, 'fecha' => 22, 'pcdDesc' => 50, 'aviso' => 10, 'autoMsg' => 'nao', 'bloqueioAuto' => 'sim', 'pacotes' => [], 'extras' => [], 'sapateiras' => 44, 'cancelaMin' => 9, 'reabreMin' => 30, 'tolCupom' => 5, 'cupom' => 'nao', 'estorno' => 'gestor',
  'cupomLoja' => 'PLAY SPACE', 'cupomTel' => '', 'cupomAviso' => 'ATENÇÃO: SOMENTE O RESPONSÁVEL QUE ASSINAR A ENTRADA DA CRIANÇA PODERÁ ASSINAR SUA SAÍDA.', 'cupomTam' => 'normal', 'horasCpf' => 'sim', 'senhaUni' => 'nao'];   // 2.11: as horas do pacote são do CPF (todas as crianças do responsável usam)   // 2.09: o tamanho do cupom impresso (normal, compacto, economico)
function regra(): array {
  if (isset($GLOBALS['_regra'])) return $GLOBALS['_regra'];   // lida uma vez por pedido (a conta do tempo consulta a regra muitas vezes)
  return $GLOBALS['_regra'] = regraDoBanco();
}
function regraDoBanco(): array {
  $r = cfg('regra', null); $r = (is_array($r) ? $r : []) + REGRA_PADRAO;
  if (($r['pacotes'] ?? []) == PACOTES_DE_EXEMPLO) $r['pacotes'] = [];
  if (!isset($r['servicos']) || !is_array($r['servicos'])) { // ninguém mexeu ainda: os três serviços da loja, mais o que já estava em "produtos e extras"
    $r['servicos'] = SERVICOS_PADRAO;
    if ((int)$r['aviso'] === 30) $r['aviso'] = 10;   // a recreação é vendida de 30 em 30 min: avisar com 30 min marcava todo mundo como "acabando" já na compra
    foreach ((array)($r['extras'] ?? []) as $k => $e) if (trim((string)($e['n'] ?? '')) !== '') $r['servicos'][] = ['id' => 'x' . $k, 'n' => (string)$e['n'], 'v' => (float)($e['v'] ?? 0), 'modo' => 'fixo', 'min' => 0, 'banco' => false, 'obs' => ''];
  }
  foreach ($r['servicos'] as $k => $x) if (is_array($x) && !array_key_exists('volta', $x)) $r['servicos'][$k]['volta'] = empty($x['banco']) && in_array((string)($x['id'] ?? ''), SERVICOS_QUE_VOLTAM, true);
  /* 1.99: o serviço que uma unidade não tem (o dono: "tira o carrinho de bebê da Anália e o fusquinha, não tem aqui"): fora = as unidades onde não
     é vendido (Ajustes → o serviço → "Tem nas unidades"). Uma vez só, na primeira vez desta versão: o que sai e volta (carrinho, fusquinha) sai da Anália */
  foreach ($r['servicos'] as $k => $x) if (is_array($x)) $r['servicos'][$k]['fora'] = array_values(array_map('intval', (array)($x['fora'] ?? [])));
  if (cfg('servuni_feito', null) === null) { $anl = unidadesDaAnalia();
    if ($anl) { foreach ($r['servicos'] as $k => $x) if (is_array($x) && !empty($x['volta'])) $r['servicos'][$k]['fora'] = array_values(array_unique(array_merge($r['servicos'][$k]['fora'], $anl)));
      $g = $r; unset($g['festa'], $g['vendedoras'], $g['vend_uni']); gravarCfg('regra', $g); }
    gravarCfg('servuni_feito', date('Y-m-d H:i:s')); }
  /* 2.35 (o dono: "No Anália Franco ... tirar as opções ... de carrinho bebê, carrinho fusquinha, porque lá não tem"): de novo, uma vez, e só
     quando a Anália já existe (a 1.99 se dava por feita mesmo sem a Anália cadastrada) */
  if (cfg('servuni2_feito', null) === null) { $anl = unidadesDaAnalia();
    if ($anl) { foreach ($r['servicos'] as $k => $x) if (is_array($x) && !empty($x['volta'])) $r['servicos'][$k]['fora'] = array_values(array_unique(array_merge($r['servicos'][$k]['fora'], $anl)));
      $g = $r; unset($g['festa'], $g['vendedoras'], $g['vend_uni']); gravarCfg('regra', $g); gravarCfg('servuni2_feito', date('Y-m-d H:i:s')); } }
  if (!MODULO_WHATS) $r['autoMsg'] = 'nao';   // com o módulo desligado, nenhum aviso automático sai
  $r['festa'] = FESTA_DE_FABRICA;   // 1.12: a tabela da festa vai com a regra para a tela (a de fábrica: não fica gravada)
  $r['vendedoras'] = vendedoras();   /* 1.67: os nomes de quem vende (o financeiro muda nas Finanças → Comissões) */
  $r['vend_uni'] = (object)vendedorasPorUnidade();   /* 2.07: os vendedores de cada unidade (as pessoas com usuário) */
  return $r;
}
/* 1.67: as vendedoras (pedido do dono: "toda venda de pacote, selecionar o nome de quem está vendendo: Aline, Edjane, Karol e Maria Vitória").
   Ficam à parte da regra (config "vendedoras"): quem muda é o financeiro, nas Finanças. Em maiúsculas, como tudo o que é digitado no sistema */
const VENDEDORAS_PADRAO = ['ALINE', 'EDJANE', 'KAROL', 'MARIA VITÓRIA'];
const VENDEDORAS_MAX = 12;
function vendedorasLimpas($lista): array {
  $r = []; foreach ((array)$lista as $n) { $n = mb_substr(trim(preg_replace('/\s+/u', ' ', maiusculo((string)$n))), 0, 30); if ($n !== '' && !in_array($n, $r, true)) $r[] = $n; }
  return array_slice($r, 0, VENDEDORAS_MAX);
}
function vendedoras(): array { $v = cfg('vendedoras', null); return is_array($v) ? vendedorasLimpas($v) : VENDEDORAS_PADRAO; }
/* 2.07 (o dono: "os vendedores do Anália são vendedores do Anália, os vendedores da Casa Tom são vendedores da Casa Tom ... Tem que separar ...
   todo mundo que tiver usuário, você já pode colocar como vendedor"): os vendedores de cada unidade ativa são as pessoas com usuário (ativas)
   que têm aquela unidade em Ajustes → Equipe, mais as que não têm unidade escolhida (vendem em todas). Em ordem alfabética, em maiúsculas. */
/* 2.22 (o dono: "não precisa aparecer o nome do José e nem o nome da Renata ... como vendedores da equipe"): quem aparece em "Quem está
   vendendo". Ajustes → Equipe → a pessoa → Sim | Não; sem escolher, o gestor não aparece (o dono, o financeiro) e os outros aparecem */
function usuarioVende(array $x): bool { $v = $x['vende'] ?? null; return $v === null || $v === '' ? (string)($x['papel'] ?? '') !== 'gestor' : (int)$v === 1; }
function vendedorasPorUnidade(): array {
  if (isset($GLOBALS['_vend_uni'])) return $GLOBALS['_vend_uni'];
  $U = []; $livres = []; $nome = fn($n) => mb_substr(maiusculo((string)$n), 0, 30);
  foreach (db()->query('SELECT id FROM unidades WHERE ativo = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $id) $U[(int)$id] = [];
  foreach (db()->query('SELECT nome, unidade_id, papel, vende FROM usuarios WHERE ativo = 1 AND loja = 0')->fetchAll() as $x) { if (!usuarioVende($x)) continue;   /* 2.22 */   /* 2.10: a "loja" (o computador do balcão) não é vendedora */ $n = $nome($x['nome']); if ($n === '') continue; $u = (int)($x['unidade_id'] ?? 0); if ($u && isset($U[$u])) $U[$u][] = $n; else $livres[] = $n; }
  $chave = fn($s) => strtr($s, ['Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C']);
  foreach ($U as $id => $L) { $L = array_values(array_unique(array_merge($L, $livres))); usort($L, fn($a, $b) => strcmp($chave($a), $chave($b))); $U[$id] = $L; }
  return $GLOBALS['_vend_uni'] = $U;
}
function vendedorasDaUnidade(int $u): array { $M = vendedorasPorUnidade(); return $M[$u] ?? []; }
function vendedorasTodas(): array { $o = []; foreach (vendedorasPorUnidade() as $L) foreach ($L as $n) if (!in_array($n, $o, true)) $o[] = $n; sort($o); return $o; }
/* 1.68 (o dono: "a comissão sempre é de quem dá início no pedido do cliente, não de quem dá saída: ela oferta o pacote no início; às vezes o
   cliente fecha no final, mas a comissão é de quem abre o pedido"): com a criança no espaço, a venda é de quem abriu o pedido na entrada */
function quemAbriu(array $c): string { return !empty($c['entrou']) && empty($c['festa_id']) ? trim((string)($c['vend_abriu'] ?? '')) : ''; }
/* o nome que a tela mandou, conferido com a lista (vazio = a tela não mandou) */
function vendedoraDoPedido($n): string {
  $n = trim(preg_replace('/\s+/u', ' ', maiusculo((string)$n))); if ($n === '') return '';
  $u = unidadeDoPedido(); foreach (array_merge($u ? vendedorasDaUnidade((int)$u) : vendedorasTodas(), vendedoras()) as $x) if ($x === $n) return $x;   /* 2.07: os vendedores da unidade */
  falha(($u ? $n . ' não é vendedora desta unidade' : 'A vendedora ' . $n . ' não está mais na lista') . '. Escolha de novo quem está vendendo.');
}
function dur(int $min): string { $min = max(0, $min); return intdiv($min, 60) . 'h' . ($min % 60 ? str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT) : ''); }   // sempre em horas: 0h30, 1h, 1h30 (a loja vende meia hora e hora)
// O tempo é sempre debitado em períodos inteiros (não existe período quebrado): quem ficou 11 min debita 30.
// O período é o do serviço de tempo de brincar (Recreação, 30 min). O período seguinte só conta depois da tolerância:
// com 30 min e tolerância de 9, até 39:00 é um período; passou disso, dois; depois de 1h09, três.
function servicoTempo(): ?array { foreach ((array)regra()['servicos'] as $s) if (!empty($s['banco']) && ($s['modo'] ?? '') === 'periodo') return $s; return null; }
function periodo(): int { $s = servicoTempo(); return max(5, (int)($s['min'] ?? 30)); }
function periodosDe(float $minutos): int { return max(1, (int)ceil(($minutos - max(0, (int)regra()['tolerancia'])) / periodo())); }
function cobrar(float $minutos): int { return periodosDe($minutos) * periodo(); }
// 1.45 (auditoria): o "ficou" que se lê: os minutos inteiros; perto do limite (de 39:01 a 39:59), o minuto que começou, para não ler 0h39 com dois períodos cobrados
function ficouMin(float $m): int { $f = (int)floor($m); return periodosDe($f) === periodosDe($m) ? $f : (int)ceil($m); }
// o preço da recreação avulsa para esta criança: a criança PCD paga com o desconto (50% de fábrica)
function pcdDesc(): int { return max(0, min(100, (int)(regra()['pcdDesc'] ?? 50))); }
/* 2.56: a criança marcada (ou desmarcada) PCD no meio da visita: a recreação lançada nesta visita e ainda não paga passa a ter o desconto
   (ou volta ao valor cheio). Devolve quantas vendas mudaram */
function pcdRefazVisita(array $c, int $pcd): int {
  if (empty($c['entrou'])) return 0; $db = db(); $n = 0;
  $q = $db->prepare("SELECT v.* FROM vendas v WHERE v.crianca_id = ? AND v.auto = 1 AND v.quando >= ? AND v.voucher = '' AND v.valor > 0.004 AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = v.id)");
  $q->execute([(int)$c['id'], (string)$c['entrou']]); $up = $db->prepare('UPDATE vendas SET valor = ?, pcd = ?, cheio = ?, itens = ? WHERE id = ?');
  foreach ($q->fetchAll() as $v) {
    $cheio = (float)$v['cheio'] > (float)$v['valor'] + 0.004 ? (float)$v['cheio'] : (float)$v['valor']; $novo = $pcd ? precoPcd(['pcd' => 1], $cheio) : $cheio;
    $it = trim((string)preg_replace('/ · PCD \d+%/u', '', (string)$v['itens'])) . ($pcd ? ' · PCD ' . pcdDesc() . '%' : '');
    $up->execute([$novo, $pcd, $cheio, $it, (int)$v['id']]); $n++;
  }
  return $n;
}
function precoPcd(array $c, float $v): float { return !empty($c['pcd']) ? intdiv((int)round($v * 100) * (100 - pcdDesc()) + 50, 100) / 100 : $v; }   // 1.31: em centavos inteiros, a mesma conta da tela
// quantos minutos a visita conta até $fim: no máximo até a hora em que a loja fecha (no dia da entrada) e nunca mais de 12 h.
// Assim uma saída esquecida não vira cobrança a noite toda (a supervisão corrige a hora da saída depois).
function tetoDaVisita(array $c): int {   // 1.90: a hora em que a conta da visita para (o "relógio interno")
  $ent = strtotime((string)$c['entrou']); $f = (int)(regra()['fecha'] ?? 22); $teto = $ent + 43200;
  $fecha = strtotime(date('Y-m-d', $ent) . ($f >= 24 ? ' 23:59:59' : sprintf(' %02d:00:00', max(0, $f))));
  if ($fecha > $ent && !ehExemplo((int)$c['id'])) $teto = min($teto, $fecha);   // entrou depois da hora de fechar, ou é criança do teste do rodapé (que precisa funcionar a qualquer hora): vale só o limite de 12 h
  return $teto;
}
function minutosDaVisita(array $c, int $fim): float {
  $ent = strtotime((string)$c['entrou']); return max(0, (min($fim, tetoDaVisita($c)) - $ent) / 60);
}
function hm(int $min): string { $min = max(0, $min); return intdiv($min, 60) . 'h' . ($min % 60 ? str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT) : ''); }
function restante(array $c): int { return max(0, (int)$c['pacote_min'] - (int)$c['usado_min']); }
function minDentro(array $c): float { return $c['entrou'] ? (agoraTs() - strtotime((string)$c['entrou'])) / 60 : 0; }
function primeiroNome(string $n): string { $p = preg_split('/\s+/', trim($n)); return $p[0] ?? $n; }

/* ---------- sessão e quem está usando ---------- */
function iniciarSessao(): void {
  $dir = pastaDados() . '/sessoes';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
  ini_set('session.gc_maxlifetime', '2592000'); ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
  $caminho = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/playspace/api/api.php')), '/') . '/';
  session_name('PLAYSPACE');
  session_set_cookie_params(['lifetime' => 2592000, 'path' => $caminho, 'secure' => https(), 'httponly' => true, 'samesite' => 'Lax']);
  session_start();
}
/* 2.10: o computador da loja (entra pela unidade, sem senha). A chave fica só no computador; o banco guarda o hash dela */
const SENHA_LOJA = '123456';   /* 2.11: a senha de fábrica das unidades (o dono: "essa senha pode colocar de 1 a 6 todos e depois a opção de eu trocar") */
function maquinaDaChave(string $t): ?array {
  if (!preg_match('/^[0-9a-f]{48}$/', $t)) return null;
  $q = db()->prepare('SELECT * FROM maquinas WHERE token = ? AND ativo = 1'); $q->execute([hash('sha256', $t)]); return $q->fetch() ?: null;
}
function usuarioDaLoja(int $uni): ?array {   // a "loja" da unidade: um usuário só para o computador do balcão (fora da Equipe e dos vendedores)
  $db = db(); $q = $db->prepare('SELECT * FROM unidades WHERE id = ? AND ativo = 1'); $q->execute([$uni]); $un = $q->fetch(); if (!$un) return null;
  $nome = maiusculo(preg_replace('/^CASA\s+TO[MN]\s+/iu', 'CASATOM ', trim((string)$un['nome'])));
  $q = $db->prepare('SELECT id, nome, ativo FROM usuarios WHERE loja = 1 AND unidade_id = ? ORDER BY id LIMIT 1'); $q->execute([$uni]); $u = $q->fetch();
  if ($u) { if ((int)$u['ativo'] !== 1 || (string)$u['nome'] !== $nome) $db->prepare('UPDATE usuarios SET ativo = 1, nome = ? WHERE id = ?')->execute([$nome, (int)$u['id']]); return ['id' => (int)$u['id']]; }
  $db->prepare("INSERT INTO usuarios (nome, login, senha, papel, ativo, criado_em, unidade_id, loja) VALUES (?, ?, ?, 'atendente', 1, ?, ?, 1)")->execute([$nome, '__loja_' . $uni . '_' . bin2hex(random_bytes(3)), password_hash(SENHA_LOJA, PASSWORD_DEFAULT), agora(), $uni]);   /* 2.11: a senha da unidade (de fábrica 123456) */
  return ['id' => (int)$db->lastInsertId()];
}
function maquinasLista(): array {
  $d = fn($t) => $t ? substr((string)$t, 8, 2) . '/' . substr((string)$t, 5, 2) . ' ' . substr((string)$t, 11, 5) : '';
  return array_map(fn($x) => ['id' => (int)$x['id'], 'nome' => (string)$x['nome'], 'local' => (string)($x['local'] ?? ''), 'criado' => $d($x['criado_em']), 'uso' => $d($x['ultimo_uso']), 'ativo' => (int)$x['ativo'] === 1], db()->query('SELECT * FROM maquinas ORDER BY ativo DESC, id DESC LIMIT 40')->fetchAll());
}
function unidadesDaLoja(string $local = ''): array { $L = array_map(fn($x) => ['id' => (int)$x['id'], 'nome' => (string)$x['nome']], db()->query('SELECT id, nome FROM unidades WHERE ativo = 1 ORDER BY id')->fetchAll());
  return $local === '' ? $L : array_values(array_filter($L, fn($x) => uniLocal($x['nome']) === $local)); }   /* 2.35: só as do lugar do computador */
function lojaLocalOk(string $l): string { $l = uniNorm($l); if ($l === '') return ''; foreach (unidadesDaLoja() as $x) if (uniLocal($x['nome']) === $l) return $l; falha('Lugar não encontrado.'); }
/* 2.12: as anotações do cliente (a lista vai inteira nos dados: são poucas e curtas) */
function anotacoesLista(): array {
  return array_map(fn($x) => ['id' => (int)$x['id'], 'c' => (int)$x['crianca_id'], 't' => (string)$x['texto'], 'q' => (string)$x['quando'], 'p' => (string)$x['por'], 'pid' => (int)($x['por_id'] ?? 0)],
    db()->query('SELECT * FROM anotacoes WHERE apagada = 0 ORDER BY id DESC LIMIT 3000')->fetchAll());
}
function eu(): ?array {
  $id = (int)($_SESSION['uid'] ?? 0); if (!$id) return null;
  $q = db()->prepare('SELECT id, nome, login, papel, unidade_id, loja, sessao FROM usuarios WHERE id = ? AND ativo = 1'); $q->execute([$id]);   // 2.02: e a unidade dela
  $u = $q->fetch() ?: null;
  if ($u && (int)$u['sessao'] !== (int)($_SESSION['sv'] ?? 0)) { $GLOBALS['PS_SENHA_TROCADA'] = true; return null; }   /* 2.60: a senha foi trocada depois que esta sessão entrou */
  if ($u) unset($u['sessao']);
  if ($u && !empty($u['loja'])) { $q2 = db()->prepare('SELECT 1 FROM maquinas WHERE id = ? AND ativo = 1'); $q2->execute([(int)($_SESSION['maq'] ?? 0)]); if (!$q2->fetchColumn()) return null; }   /* 2.10: o computador que perdeu a liberação sai na hora */
  return $u;
}
/* 2.60: a sessão guarda o número de sessão do usuário na hora de entrar; trocar a senha soma 1 (as sessões antigas caem). Quem trocou a
   própria senha continua dentro (a sessão dele leva o número novo) */
function sessaoMarca(int $uid): void { $q = db()->prepare('SELECT sessao FROM usuarios WHERE id = ?'); $q->execute([$uid]); $_SESSION['sv'] = (int)$q->fetchColumn(); }
function sessaoNova(int $uid): void { db()->prepare('UPDATE usuarios SET sessao = sessao + 1 WHERE id = ?')->execute([$uid]); if ((int)($_SESSION['uid'] ?? 0) === $uid && session_status() === PHP_SESSION_ACTIVE) sessaoMarca($uid); }
function exigir(array $papeis = []): array {
  $u = eu(); if (!$u) falha(!empty($GLOBALS['PS_SENHA_TROCADA']) ? 'A senha foi trocada. Entre de novo com a senha nova.' : 'Entre de novo para continuar.', 401);
  if ($papeis && !in_array($u['papel'], $papeis, true)) falha('Só ' . implode(' ou ', array_map(fn($p) => PAPEIS[$p] ?? $p, $papeis)) . ' faz isso.', 403);
  return $u;
}
/* ---------- 1.21: a blindagem do operador ---------- */
// Quem estorna desfaz e cancela o que já tem valor: a venda registrada, o pagamento recebido, a saída e a devolução que receberam.
// De fábrica é só o gestor (regra.estorno). Quem não estorna continua fazendo tudo da recepção (cadastro, entrada, venda, saída,
// devolução, receber); o que ele errar e tiver valor é cancelado por quem estorna, com o motivo, e fica na lista de conferência.
function podeEstornar(array $u): bool { $q = (string)(regra()['estorno'] ?? 'gestor'); $p = (string)($u['papel'] ?? ''); return $p === 'gestor' || $q === 'todos' || ($q === 'supervisor' && $p === 'supervisor'); }
function quemEstorna(): string { return (string)(regra()['estorno'] ?? 'gestor') === 'supervisor' ? 'o gestor ou o supervisor' : 'o gestor'; }
// 1.26: o Desfazer é só do gestor (o operador e o supervisor não desfazem nada; uma tela antiga ainda aberta recebe a recusa).
// regra 'desfazer' = 'todos' (não aparece em Ajustes): o jeito da 1.25, com as travas de antes (a do valor, a dos 10 min).
function podeDesfazer(array $u): bool { return (string)($u['papel'] ?? '') === 'gestor' || (string)(regra()['desfazer'] ?? 'gestor') === 'todos'; }
function exigirDesfazer(array $u): void { if (!podeDesfazer($u)) falha('Só o gestor desfaz. Se foi engano, chame o gestor.', 403); }
const MOTIVO_DESFEITA = 'DESFEITA NA HORA (TOQUE ERRADO)';   // o motivo da entrada desfeita pelo botão do aviso por quem não estorna

/* ---------- 1.25: o nome de quem cancelou, e o aviso que chega à gestora ---------- */
// O aparelho da recepção fica no usuário do operador, e quem está no balcão muda ao longo do dia: todo cancelamento (a entrada, a saída do
// carrinho, a venda, o "não vai fazer agora", o Desfazer que fica registrado) pede o nome da atendente. Ele vai no cancelamento, na linha
// do dia e no aviso que chega à gestora, na tela dela, na hora. O gestor não precisa escrever: é ele mesmo.
function atendente(?string $n = null): string { static $a = ''; if ($n !== null) $a = $n; return $a; }
function nomeAtendente(string $s): string { return mb_substr(maiusculo($s), 0, 40); }
function lerAtendente(array $in, array $u): string {
  if (!array_key_exists('atendente', $in)) return atendente(($u['papel'] ?? '') === 'gestor' ? nomeAtendente(primeiroNome((string)$u['nome'])) : '');   // a tela de antes da 1.25 (ainda aberta noutro aparelho) não tem o campo: fica no nome do login
  $n = nomeAtendente((string)($in['atendente'] ?? ''));
  if ($n === '' && ($u['papel'] ?? '') === 'gestor') $n = nomeAtendente(primeiroNome((string)$u['nome']));
  if (mb_strlen($n) < 2) falha('Escreva o nome da atendente: todo cancelamento vai para a gestora, com o nome de quem cancelou.');
  return atendente($n);
}
// "· por MARIA (login João)": quem cancelou e, quando é outra pessoa, o usuário em que o aparelho estava
/* 2.02 (o dono: "tentei criar um usuário tipo Julia Alves, ele não deixou ... quero com o espaço"): o login aceita espaço. Fica em
   minúsculas, com um espaço só entre as palavras, e o acento vira a letra sem acento ("Júlia  Alves" = "julia alves"). Vale para a Play
   Space e para as Finanças */
function loginDe($v): string {
  $v = trim((string)$v); $v = function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
  $v = strtr($v, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
    'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n']);
  $v = (string)preg_replace('/[^a-z0-9 ._-]/', '', (string)preg_replace('/\s+/u', ' ', $v));
  return trim(substr(trim($v), 0, 40));
}
function porQuem(array $u): string { $p = primeiroNome((string)$u['nome']); $a = atendente(); return $a !== '' && mb_strtoupper($a) !== mb_strtoupper($p) ? $a . ' (login ' . $p . ')' : $p; }
// os cancelamentos que a gestora ainda não viu (de quem não é gestor, nas últimas 24 h): chegam em toda carga e a cada pulso, como os
// pedidos de autorização (mesmo com uma folha aberta). "Visto" tira da tela dela e da lista de conferência
const AVISO_HORAS = 24;
function avisosDaGestora(array $u): array {
  if (($u['papel'] ?? '') !== 'gestor') return [];
  $q = db()->prepare("SELECT * FROM cancelamentos WHERE visto_em IS NULL AND papel != 'gestor' AND quando >= ? ORDER BY id DESC LIMIT 40"); $q->execute([date('Y-m-d H:i:s', time() - AVISO_HORAS * 3600)]);
  $L = array_map('cancelamentoPublico', $q->fetchAll());
  $q = db()->prepare("SELECT * FROM trocas_pag WHERE visto_em IS NULL AND papel != 'gestor' AND quando >= ? ORDER BY id DESC LIMIT 40"); $q->execute([date('Y-m-d H:i:s', time() - AVISO_HORAS * 3600)]);   // 1.57: e as trocas da forma de pagamento (id negativo)
  foreach ($q->fetchAll() as $t) $L[] = trocaPublica($t);
  return $L;
}

/* ---------- 1.23: os pedidos de autorização ---------- */
// O que só o gestor faz (dar desconto, autorizar a nota fiscal) e o que só quem estorna faz (cancelar uma venda), o operador PEDE.
// O pedido aparece na tela de quem autoriza, que libera ou nega com um toque; quem autoriza também pode digitar a senha dele no aparelho
// de quem pediu. Fica guardado quem pediu, quem decidiu, quando e como (toque ou senha), e o que foi feito com a autorização.
// status: pendente → liberado (o desconto pode ser usado; a nota pode ser emitida) → usado (o desconto foi aplicado; a venda foi cancelada;
// a nota foi emitida, com o número) | negado | cancelado (quem pediu desistiu, ou a venda saiu).
const PEDIDO_TIPOS = ['desconto', 'cancelar_venda', 'nf', 'valor', 'parcelas', 'venda_futura'];   // 2.08: a venda futura (o gestor aprova)   // 1.65: parcelas = o crédito da festa em mais de 4x (o gestor libera)
const PARC_LIVRE = 4;   // 1.65: no crédito, até 4x qualquer um vende; de 5x a 12x, só com a liberação do gestor
function parcLivre(): int { return max(1, min(12, (int)(regra()['festa']['parcelas'] ?? PARC_LIVRE))); }   // (a mesma regra do "em até 4x sem juros")   // 1.33: valor = alterar o valor de um pacote ou de um produto na venda
const PEDIDO_VALE_MIN = 30;   // o desconto liberado vale por 30 min; depois disso, pede de novo
const PEDIDO_ESPERA_H = 12;   // o pedido de desconto ou de cancelamento sem resposta sai da tela depois disso (o da nota fiscal espera o gestor)
const PEDIDO_JUNTO_SEG = 120;   // o desconto já usado em parte só completa a mesma conta (a saída em conjunto dos irmãos) dentro deste tempo
function podeDecidir(array $u, string $tipo): bool { return $tipo === 'cancelar_venda' ? podeEstornar($u) : ($tipo === 'valor' ? in_array($u['papel'] ?? '', ['gestor', 'supervisor'], true) : ($u['papel'] ?? '') === 'gestor'); }   // 1.33: a alteração de valor, a supervisora ou o gestor
function quemDecide(string $tipo): string { return $tipo === 'cancelar_venda' ? quemEstorna() : ($tipo === 'valor' ? 'o supervisor ou o gestor' : 'o gestor'); }
const PARC_ONDE = ['festa' => 'festa', 'venda' => 'venda', 'saida' => 'saída', 'devolucao' => 'devolução', 'receber' => 'a receber'];   // 1.89: de onde é o pedido do crédito em mais vezes
const PEDIDO_SEM_VENDA = ['desconto', 'valor', 'parcelas', 'venda_futura'];   // 1.33: os pedidos que nascem antes da venda (os outros são de uma venda que já existe)
function pedido(int $id): ?array { $q = db()->prepare('SELECT * FROM pedidos WHERE id = ?'); $q->execute([$id]); return $q->fetch() ?: null; }
function pedidoPublico(array $p): array {
  $d = json_decode((string)$p['dados'], true);
  $o = ['id' => (int)$p['id'], 'tipo' => (string)$p['tipo'], 'status' => (string)$p['status'], 'crianca' => $p['crianca_id'] === null ? null : (int)$p['crianca_id'], 'venda' => $p['venda_id'] === null ? null : (int)$p['venda_id'],
    'nome' => (string)$p['nome'], 'resp' => (string)$p['resp'], 'texto' => (string)$p['texto'], 'motivo' => (string)$p['motivo'], 'valor' => round((float)$p['valor'], 2), 'usado' => round((float)$p['usado'], 2), 'dados' => is_array($d) ? $d : [],
    'por' => (string)$p['pedido_nome'], 'por_id' => (int)$p['pedido_por'], 'em' => (string)$p['pedido_em'], 'dec' => (string)$p['decidido_nome'], 'dec_em' => $p['decidido_em'], 'como' => (string)$p['como'],
    'feito_por' => (string)$p['feito_nome'], 'feito_em' => $p['feito_em'], 'numero' => (string)$p['numero'], 'resultado' => (string)$p['resultado'], 'uni' => ($p['unidade_id'] ?? null) === null ? null : (int)$p['unidade_id'], 'sem_venda' => !in_array($p['tipo'], PEDIDO_SEM_VENDA, true) && $p['venda_id'] === null];   // sem_venda: a venda do pedido saiu (foi cancelada ou desfeita)
  if (in_array($p['tipo'], PEDIDO_SEM_VENDA, true) && $p['status'] === 'liberado' && $p['decidido_em']) $o['vale'] = date('Y-m-d H:i:s', strtotime((string)$p['decidido_em']) + PEDIDO_VALE_MIN * 60);   // até quando o desconto liberado pode ser usado
  return $o;
}
// o que cada tela recebe (em toda carga e a cada pulso): os pedidos que esperam a resposta de quem está logado, e os dele mesmo
// (o que ainda espera e o que foi respondido nas últimas horas, para a tela dizer o que o gestor decidiu)
function pedidosDaTela(array $u): array {
  $db = db(); $lim = date('Y-m-d H:i:s', time() - PEDIDO_ESPERA_H * 3600); $eu = (int)$u['id']; $o = [];
  $q = $db->prepare("SELECT p.* FROM pedidos p WHERE p.status = 'pendente' AND (p.tipo IN ('nf', 'venda_futura') OR p.pedido_em >= ?) ORDER BY p.id DESC LIMIT 60"); $q->execute([$lim]);
  foreach ($q->fetchAll() as $p) { $meu = (int)$p['pedido_por'] === $eu; $decide = podeDecidir($u, (string)$p['tipo']);
    if ((!in_array($p['tipo'], PEDIDO_SEM_VENDA, true) && $p['venda_id'] === null) || !($meu || $decide)) continue;   // (o pedido de uma venda que saiu não espera mais nada: o gatilho já o cancelou)
    $o[] = pedidoPublico($p) + ['meu' => $meu, 'decide' => $decide]; }
  $q = $db->prepare("SELECT p.* FROM pedidos p WHERE p.pedido_por = ? AND p.status != 'pendente' AND COALESCE(p.decidido_em, p.pedido_em) >= ? ORDER BY p.id DESC LIMIT 30"); $q->execute([$eu, $lim]);
  foreach ($q->fetchAll() as $p) $o[] = pedidoPublico($p) + ['meu' => true, 'decide' => false];
  return $o;
}
// a senha de quem autoriza, digitada no aparelho de quem pediu: devolve quem é (ou falha). 8 tentativas erradas em 15 min param por 15 min
function autorizadorPelaSenha(string $tipo, string $senha, array $u): array {
  $db = db(); $chave = 'aut:' . (int)$u['id'];
  $db->prepare('DELETE FROM tentativas WHERE quando < ?')->execute([time() - 900]);
  $q = $db->prepare('SELECT COUNT(*) FROM tentativas WHERE login = ? AND quando > ?'); $q->execute([$chave, time() - 900]);
  if ((int)$q->fetchColumn() >= 8) falha('Muitas tentativas de senha. Espere 15 minutos, ou peça para ' . quemDecide($tipo) . ' liberar na tela dele.', 429);
  if ($senha !== '') foreach ($db->query("SELECT id, nome, login, papel, senha FROM usuarios WHERE ativo = 1 ORDER BY papel = 'gestor' DESC, id")->fetchAll() as $g) {
    if (podeDecidir($g, $tipo) && password_verify($senha, (string)$g['senha'])) { unset($g['senha']); return $g; }
  }
  $db->prepare("INSERT INTO tentativas (login, ip, quando) VALUES (?, '', ?)")->execute([$chave, time()]); usleep(400000);
  falha('A senha não confere com a ' . (quemDecide($tipo) === 'o gestor' ? 'do gestor' : 'do gestor nem com a do supervisor') . '.', 403);
}
function pedidoCriar(array $u, array $in): array {
  $db = db(); $tipo = (string)($in['tipo'] ?? ''); if (!in_array($tipo, PEDIDO_TIPOS, true)) falha('Pedido desconhecido.');
  $agora = agora(); $dados = []; $valor = 0.0; $vendaId = null; $motivo = mb_substr(maiusculo((string)($in['motivo'] ?? '')), 0, 300);
  if ($tipo === 'desconto') {
    $c = crianca((int)($in['crianca'] ?? 0)); if (!$c) falha('Cliente não encontrado.', 404);
    $valor = round((float)($in['valor'] ?? 0), 2); if ($valor <= 0) falha('Escreva o valor do desconto.');
    $pct = round((float)($in['pct'] ?? 0), 2); if ($pct < 0 || $pct > 100) falha('O desconto não passa de 100%.');
    $base = round(max(0, (float)($in['base'] ?? 0)), 2); if ($base > 0 && $valor > $base + 0.004) falha('O desconto (' . dinheiro($valor) . ') passa do total, que é ' . dinheiro($base) . '.');
    $motivo = mb_substr($motivo, 0, 120); if (mb_strlen($motivo) < 3) falha('Escreva o motivo do desconto.');
    $dados = ['pct' => $pct, 'base' => $base, 'onde' => ($in['onde'] ?? '') === 'saida' ? 'saida' : 'venda'];
    $texto = 'Desconto de ' . dinheiro($valor) . ($pct > 0 ? ' (' . rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',') . '%)' : '') . ($base > 0 ? ' em ' . dinheiro($base) : '') . ($dados['onde'] === 'saida' ? ' · na saída' : '');
    // um pedido de desconto por vez para o mesmo cliente: o anterior (sem resposta, ou liberado e não usado) deixa de valer
    $db->prepare("UPDATE pedidos SET status = 'cancelado', resultado = 'TROCADO POR OUTRO PEDIDO' WHERE tipo = 'desconto' AND pedido_por = ? AND crianca_id = ? AND status IN ('pendente', 'liberado')")->execute([(int)$u['id'], (int)$c['id']]);
  } elseif ($tipo === 'parcelas') { // 1.65: o crédito em 5x a 12x (até 4x é livre): o gestor libera, na tela dele ou com a senha aqui. 1.89: em todo pagamento, não só na festa
    $c = crianca((int)($in['crianca'] ?? 0)); if (!$c) falha('Cliente não encontrado.', 404);
    $n = (int)($in['n'] ?? 0); if ($n <= parcLivre() || $n > 12) falha('A liberação é para o crédito de ' . (parcLivre() + 1) . 'x a 12x (até ' . parcLivre() . 'x não precisa).');
    $onde = (string)($in['onde'] ?? 'festa'); if (!isset(PARC_ONDE[$onde])) $onde = 'festa';
    $valor = round(max(0, (float)($in['valor'] ?? 0)), 2); $motivo = mb_substr($motivo, 0, 120); $dados = ['n' => $n, 'onde' => $onde];
    $texto = 'Crédito em ' . $n . 'x' . ($valor > 0 ? ': ' . dinheiro($valor) . ' (' . $n . 'x de ' . dinheiro(floor($valor * 100 / $n) / 100) . ')' : '') . ' · ' . PARC_ONDE[$onde];
    $db->prepare("UPDATE pedidos SET status = 'cancelado', resultado = 'TROCADO POR OUTRO PEDIDO' WHERE tipo = 'parcelas' AND pedido_por = ? AND crianca_id = ? AND status IN ('pendente', 'liberado')")->execute([(int)$u['id'], (int)$c['id']]);
  } elseif ($tipo === 'venda_futura') { /* 2.08: o que o cliente vai fechar, o valor, quando fecha, quando paga e quem vende */
    $c = crianca((int)($in['crianca'] ?? 0)); if (!$c) falha('Cliente não encontrado.', 404);
    $oque = mb_substr(maiusculo((string)($in['oque'] ?? '')), 0, 120); if (mb_strlen($oque) < 3) falha('Escreva o que o cliente vai fechar (por exemplo, festa de 20 crianças).');
    $valor = round((float)($in['valor'] ?? 0), 2); if ($valor <= 0 || $valor > 999999) falha('Escreva o valor combinado.');
    $hoje = substr(agora(), 0, 10); $fecha = (string)($in['fecha'] ?? ''); $paga = (string)($in['paga'] ?? '');
    foreach ([[$fecha, 'em que o cliente disse que vai fechar'], [$paga, 'do pagamento']] as [$dt, $oq]) if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dt, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || $dt < $hoje) falha('Escolha a data ' . $oq . ' (de hoje em diante).');
    $vend = vendedoraDoPedido($in['vendedora'] ?? ''); if ($vend === '') falha('Toque no nome de quem está vendendo.');
    $dados = ['oque' => $oque, 'fecha' => $fecha, 'paga' => $paga, 'vendedora' => $vend, 'obs' => mb_substr(maiusculo((string)($in['obs'] ?? '')), 0, 300), 'pac' => empty($in['pac']) ? 0 : 1]; $motivo = $dados['obs'];   /* 2.16: pac = o pacote futuro */
    $dm = fn($s) => substr($s, 8, 2) . '/' . substr($s, 5, 2);
    $texto = (empty($in['pac']) ? 'Venda futura: ' : 'Pacote futuro: ') . $oque . ' · ' . dinheiro($valor) . ' · fecha em ' . $dm($fecha) . ' · pagamento em ' . $dm($paga) . ' · vendedora ' . $vend;
  } elseif ($tipo === 'valor' && (int)($in['venda'] ?? 0) > 0) { // 1.39: na saída, o pacote ou o produto desta visita que ficou para pagar na saída
    $q = $db->prepare('SELECT * FROM vendas WHERE id = ?'); $q->execute([(int)$in['venda']]); $v = $q->fetch(); $q->closeCursor(); if (!$v) falha('Esta venda não existe mais: já foi desfeita ou cancelada.', 404);
    $c = crianca((int)$v['crianca_id']); if (!$c) falha('Cliente não encontrado.', 404); $k = altDaVenda($v); if ($k === '') falha('Esta venda não tem o valor alterado por aqui (só o pacote ou o produto desta visita).');
    if (pagoPorForma([(int)$v['id']])) falha('Esta venda já foi paga: o valor não muda mais.');
    $vendaId = (int)$v['id']; $de = round((float)$v['valor'], 2); $min = (int)$v['minutos']; $nm = $k === 'pac' ? (trim((string)$v['servico']) !== '' ? trim((string)$v['servico']) : 'PACOTE') : nomeDaVenda($v);   // (o pacote, sem as horas no nome: elas vêm ao lado)
    $para = round((float)($in['para'] ?? 0), 2); $pp = lerPacProd($in, $k); if ($pp) $para = $pp['para'];   // 1.41
    if ($para <= 0) falha('Escreva o valor novo.'); if ($para > 99999) falha('Confira o valor novo: ' . dinheiro($para) . '.');
    $horas = null; if ($k === 'pac' && isset($in['horas'])) { $hh = (int)$in['horas']; if ($hh < 1 || $hh > 200) falha('As horas do pacote vão de 1 a 200.'); if ($hh * 60 !== $min) $horas = $hh; }
    if (abs($para - $de) < 0.005 && $horas === null) falha('O valor novo é igual ao de agora (' . dinheiro($de) . ').');
    $motivo = mb_substr($motivo, 0, 120); if (mb_strlen($motivo) < 3) falha('Escreva o motivo: por que o pacote (ou o valor) muda.');
    $valor = $para; $dados = ($pp ? ['pac' => $pp['pac'], 'prod' => $pp['prod']] + ($pp['pn'] !== '' ? ['prod_nome' => $pp['pn']] : []) : []) + ['venda' => $vendaId, 'item' => ['k' => 'venda', 'tipo' => $k, 'n' => $nm, 'h' => $k === 'pac' ? intdiv($min, 60) : null, 'min' => $min, 'de' => $de], 'de' => $de, 'para' => $para, 'horas' => $horas, 'onde' => 'saida'];
    $texto = ($pp ? 'Alterar o pacote: ' . altTextoPac($nm, intdiv($min, 60), $de, $horas ?: intdiv($min, 60), $pp['pac'], $pp['prod'], $para, $pp['pn']) : ($horas ? 'Alterar o pacote: ' . $nm . ' (' . dur($min) . ', ' . dinheiro($de) . ') para ' . $horas . 'h por ' . dinheiro($para) : 'Alterar o valor: ' . $nm . ' de ' . dinheiro($de) . ' para ' . dinheiro($para))) . ' · na saída';
    $db->prepare("UPDATE pedidos SET status = 'cancelado', resultado = 'TROCADO POR OUTRO PEDIDO' WHERE tipo = 'valor' AND pedido_por = ? AND venda_id = ? AND status IN ('pendente', 'liberado')")->execute([(int)$u['id'], $vendaId]);   // um pedido por venda
  } elseif ($tipo === 'valor') { // 1.33: o valor novo de um pacote ou de um produto da venda que está sendo feita
    $c = crianca((int)($in['crianca'] ?? 0)); if (!$c) falha('Cliente não encontrado.', 404);
    $it = itemDoValor((array)($in['item'] ?? [])); $para = round((float)($in['para'] ?? 0), 2); $pp = lerPacProd($in, $it['k']); if ($pp) $para = $pp['para'];   // 1.41
    if ($para <= 0) falha('Escreva o valor novo.'); if ($para > 99999) falha('Confira o valor novo: ' . dinheiro($para) . '.');
    $horas = null; if ($it['k'] === 'pac' && isset($in['horas'])) { $hh = (int)$in['horas']; if ($hh < 1 || $hh > 200) falha('As horas do pacote vão de 1 a 200.'); if ($hh !== (int)$it['h']) $horas = $hh; }   // 1.36: o pacote que não existe na tabela (as horas também mudam)
    if (abs($para - $it['de']) < 0.005 && $horas === null) falha('O valor novo é igual ao de agora (' . dinheiro($it['de']) . ').');
    $motivo = mb_substr($motivo, 0, 120); if (mb_strlen($motivo) < 3) falha('Escreva o motivo: por que o pacote (ou o valor) muda.');   // (1.41: com os três campos, o motivo é opcional)
    $valor = $para; $dados = ['item' => $it, 'de' => $it['de'], 'para' => $para, 'horas' => $horas] + ($pp ? ['pac' => $pp['pac'], 'prod' => $pp['prod']] + ($pp['pn'] !== '' ? ['prod_nome' => $pp['pn']] : []) : []);
    $texto = $pp ? 'Alterar o pacote: ' . altTextoPac($it['n'], (int)$it['h'], (float)$it['de'], $horas ?: (int)$it['h'], $pp['pac'], $pp['prod'], $para, $pp['pn']) : ($horas ? 'Alterar o valor: ' . $it['n'] . ' (' . (int)$it['h'] . 'h, ' . dinheiro($it['de']) . ') para ' . $horas . 'h por ' . dinheiro($para) : 'Alterar o valor: ' . $it['n'] . ' de ' . dinheiro($it['de']) . ' para ' . dinheiro($para));
    // uma alteração por vez para o mesmo cliente: a anterior (sem resposta, ou aprovada e não usada) deixa de valer
    $db->prepare("UPDATE pedidos SET status = 'cancelado', resultado = 'TROCADO POR OUTRO PEDIDO' WHERE tipo = 'valor' AND pedido_por = ? AND crianca_id = ? AND venda_id IS NULL AND status IN ('pendente', 'liberado')")->execute([(int)$u['id'], (int)$c['id']]);   // (1.39: o da saída fica)
  } else {
    $q = $db->prepare('SELECT * FROM vendas WHERE id = ?'); $q->execute([(int)($in['venda'] ?? 0)]); $v = $q->fetch(); if (!$v) falha('Esta venda não existe mais: já foi desfeita ou cancelada.', 404);
    $c = crianca((int)$v['crianca_id']); if (!$c) falha('Cliente não encontrado.', 404); $vendaId = (int)$v['id']; $valor = round((float)$v['valor'], 2); $nomeV = nomeDaVenda($v);
    $pago = pagoPorForma([$vendaId]); $pagoTxt = $pago ? 'pago: ' . textoPagamento($pago, true) : ($valor > 0.004 ? 'ainda não foi paga' : '');
    $oque = $nomeV . ' (' . dinheiro($valor) . ') · venda de ' . date('d/m', strtotime((string)$v['quando'])) . ' às ' . substr((string)$v['quando'], 11, 5) . ($pagoTxt !== '' ? ' · ' . $pagoTxt : '');
    if ((string)($v['servico'] ?? '') === SERV_IMPORTADO) falha('As horas que vieram do sistema anterior não são uma venda: não dá para ' . ($tipo === 'nf' ? 'tirar a nota' : 'cancelar') . ' por aqui.');
    if ($tipo === 'cancelar_venda') {
      if (!empty($v['auto'])) falha('A recreação que a visita somou sozinha não é cancelada por aqui: ela é acertada na saída.');
      if (time() - strtotime((string)$v['quando']) > VENDA_CANCELA_DIAS * 86400) falha('Só dá para cancelar vendas dos últimos ' . VENDA_CANCELA_DIAS . ' dias.');
      if (mb_strlen($motivo) < 5) falha('Escreva o motivo do cancelamento.');
      $min = (int)$v['minutos']; $resta = restante($c);
      if ($min > 0 && $resta < $min) falha('As horas desta venda já foram usadas' . ($resta > 0 ? ' em parte: sobram ' . hm($resta) . ' de ' . hm($min) : '') . '. Não dá para cancelar a venda inteira.');
      $q = $db->prepare("SELECT * FROM pedidos WHERE tipo = 'cancelar_venda' AND venda_id = ? AND status = 'pendente' ORDER BY id DESC LIMIT 1"); $q->execute([$vendaId]); $ja = $q->fetch();
      if ($ja) return ['pedido' => pedidoPublico($ja), 'ja' => true];   // já havia o pedido desta venda, à espera
      $dados = ['item' => $nomeV, 'quando' => (string)$v['quando'], 'pago' => $pago ? textoPagamento($pago, true) : '', 'min' => $min, 'atendente' => lerAtendente($in, $u)];   // 1.25: quem pediu (o nome da atendente)
      $texto = 'Cancelar a venda: ' . $oque;
    } else { // a nota fiscal de uma compra
      if (emAtendimento($c)) falha('Cliente em atendimento: a nota fiscal sai depois de finalizar o cliente (nos Finalizados do dia).', 403);   /* 2.58; 2.60: para todos, o gestor também */
      if ($valor <= 0.004) falha('Esta compra não tem valor: não há nota para tirar.');
      $q = $db->prepare("SELECT * FROM pedidos WHERE tipo = 'nf' AND venda_id = ? AND status IN ('pendente', 'liberado', 'usado') ORDER BY id DESC LIMIT 1"); $q->execute([$vendaId]); $ja = $q->fetch();
      if ($ja) return $ja['status'] === 'pendente' ? pedidoDecidir((int)$ja['id'], $u, true, 'proprio', true) : ['pedido' => pedidoPublico($ja), 'ja' => true];   // uma nota por compra (1.55: a pedida antes, que esperava o gestor, sai agora)
      $nfNome = mb_substr(maiusculo((string)($in['nf_nome'] ?? '')), 0, 120); if ($nfNome === '') $nfNome = (string)($c['resp'] ?: $c['nome']);
      $bruto = array_key_exists('nf_doc', $in) ? (string)$in['nf_doc'] : (($c['doc_tipo'] ?? 'cpf') === 'cpf' ? (string)$c['cpf'] : ''); $dig = (string)preg_replace('/\D/', '', $bruto);
      if ($dig !== '' && strlen($dig) !== 11 && strlen($dig) !== 14) falha('Confira o CPF ou o CNPJ da nota: o CPF tem 11 números e o CNPJ, 14.');
      $doc = strlen($dig) === 11 ? substr($dig, 0, 3) . '.' . substr($dig, 3, 3) . '.' . substr($dig, 6, 3) . '-' . substr($dig, 9) : (strlen($dig) === 14 ? substr($dig, 0, 2) . '.' . substr($dig, 2, 3) . '.' . substr($dig, 5, 3) . '/' . substr($dig, 8, 4) . '-' . substr($dig, 12) : '');
      $email = mb_substr(trim((string)($in['nf_email'] ?? '')), 0, 120); if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) falha('Confira o e-mail para onde a nota vai.');
      $dados = ['nome' => $nfNome, 'doc' => $doc, 'email' => mb_strtolower($email), 'item' => $nomeV, 'quando' => (string)$v['quando'], 'pago' => $pago ? textoPagamento($pago, true) : ''];
      $texto = 'Nota fiscal: ' . $oque . ' · em nome de ' . $nfNome . ($doc !== '' ? ' · ' . (strlen($dig) === 14 ? 'CNPJ ' : 'CPF ') . $doc : '');
      $motivo = '';
    }
  }
  /* 2.47 (o dono: "aqui só pode alterar o valor para mais"): na Casa Tom, o valor do produto só sobe (para menos é o desconto), nem o gestor baixa */
  if ($tipo === 'valor' && $vendaId === null && (($dados['item'] ?? [])['k'] ?? '') === 'prod' && (float)($dados['para'] ?? 0) < (float)($dados['de'] ?? 0) - 0.004 && ($bct = balcaoCT(false)) && (int)$bct['id'] === (int)$c['id'])
    falha('Na Casa Tom o valor só sobe. Para menos é desconto: use o "Desconto" (só o gestor).');
  $db->prepare('INSERT INTO pedidos (tipo, status, crianca_id, venda_id, nome, resp, texto, motivo, valor, dados, pedido_por, pedido_nome, pedido_em, unidade_id) VALUES (?, \'pendente\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$tipo, (int)$c['id'], $vendaId, (string)$c['nome'], (string)$c['resp'], $texto, $motivo, $valor, json_encode($dados, JSON_UNESCAPED_UNICODE), (int)$u['id'], (string)$u['nome'], $agora, unidadeDoPedido()]);
  $id = (int)$db->lastInsertId();
  /* 2.42 (o dono: "no Casatom eu quero poder ajustar o valor para mais do produto sem pedir autorização, só se for desconto"): o produto
     que sobe de valor vale na hora, para qualquer um; baixar continua com quem aprova */
  $sobe = $tipo === 'valor' && $vendaId === null && (($dados['item'] ?? [])['k'] ?? '') === 'prod' && (float)($dados['para'] ?? 0) > (float)($dados['de'] ?? 0) + 0.004;
  if (podeDecidir($u, $tipo) || $tipo === 'nf' || $sobe) return pedidoDecidir($id, $u, true, 'proprio', $tipo === 'nf' || $sobe);   // quem pede já autoriza (o gestor): vale na hora. 1.55: a nota fiscal, toda a equipe emite
  return ['pedido' => pedidoPublico(pedido($id))];
}
// liberar ou negar. $g = quem decide (quem está logado, ou o dono da senha digitada); $como = 'toque' | 'senha' | 'proprio'
function pedidoDecidir(int $id, array $g, bool $ok, string $como = 'toque', bool $livre = false): array {   // 1.55: $livre = a nota fiscal (toda a equipe emite)
  $db = db(); $p = pedido($id); if (!$p) falha('Pedido não encontrado.', 404);
  $tipo = (string)$p['tipo']; if (!$livre && !podeDecidir($g, $tipo)) falha('Só ' . quemDecide($tipo) . ' responde a este pedido.', 403);
  if ($p['status'] !== 'pendente') falha($p['status'] === 'cancelado' ? 'Este pedido não vale mais: foi cancelado.' : 'Este pedido já foi respondido' . ($p['decidido_nome'] !== '' ? ' por ' . primeiroNome((string)$p['decidido_nome']) : '') . '.', 409);
  if ($ok && in_array($tipo, ['desconto', 'valor', 'parcelas'], true) && time() - strtotime((string)$p['pedido_em']) > 43200) { $db->prepare("UPDATE pedidos SET status = 'cancelado', resultado = 'PEDIDO DE MAIS DE 12 H' WHERE id = ? AND status = 'pendente'")->execute([$id]); falha('Este pedido é de mais de 12 horas atrás e não vale mais. Peça de novo.', 409); }   // 1.45 (auditoria)
  $agora = agora(); $extra = []; $status = $ok ? 'liberado' : 'negado'; $resultado = '';
  if ($ok && $tipo === 'cancelar_venda') { // liberar o cancelamento É cancelar a venda (no nome de quem liberou, com o motivo de quem pediu)
    $q = $db->prepare('SELECT 1 FROM vendas WHERE id = ?'); $q->execute([(int)$p['venda_id']]); $existe = $p['venda_id'] !== null && (bool)$q->fetchColumn(); $q->closeCursor();
    if (!$existe) { $db->prepare("UPDATE pedidos SET status = 'cancelado', resultado = 'A VENDA NÃO EXISTE MAIS' WHERE id = ?")->execute([$id]); falha('Esta venda não existe mais: já foi desfeita ou cancelada. O pedido saiu da lista.', 404); }
    $dp = json_decode((string)$p['dados'], true); $at = is_array($dp) ? nomeAtendente((string)($dp['atendente'] ?? '')) : ''; $pn = maiusculo(primeiroNome((string)$p['pedido_nome'])); $outro = (int)$p['pedido_por'] !== (int)$g['id'];   // 1.25: o nome da atendente que pediu
    $r = cancelarVenda((int)$p['venda_id'], $g, mb_substr((string)$p['motivo'] . ($outro ? ' (PEDIDO DE ' . ($at !== '' && $at !== $pn ? $at . ', LOGIN ' . $pn : $pn) . ')' : ''), 0, 300), $outro ? ($at !== '' ? $at : $pn) : null, $outro ? $pn : '');
    $status = 'usado'; $resultado = $r['devolver'] !== '' ? 'DEVOLVER AO CLIENTE: ' . $r['devolver'] : 'VENDA CANCELADA'; $extra['cancelada'] = $r;
  }
  if ($ok && $tipo === 'valor' && $p['venda_id'] !== null) { $extra['alterada'] = alterarVendaNaSaida($p, $g); $status = 'usado'; $resultado = 'VALOR ALTERADO NA VENDA'; }   // 1.39
  if ($ok && $tipo === 'venda_futura') { $extra['vfut'] = vfutLancar($p, $g); $status = 'usado'; $resultado = 'LEMBRETES NO CALENDÁRIO'; }   /* 2.08 */
  $q = $db->prepare('UPDATE pedidos SET status = ?, decidido_por = ?, decidido_nome = ?, decidido_em = ?, como = ?, resultado = ?' . ($status === 'usado' ? ', feito_por = ?, feito_nome = ?, feito_em = ?' : '') . ' WHERE id = ?' . ($status === 'usado' ? '' : " AND status = 'pendente'"));
  $q->execute(array_merge([$status, (int)$g['id'], (string)$g['nome'], $agora, $como, $resultado], $status === 'usado' ? [(int)$g['id'], (string)$g['nome'], $agora] : [], [$id]));
  if (!$q->rowCount()) falha('Este pedido já foi respondido em outro aparelho.', 409);
  if ($status === 'usado' && $tipo === 'valor') $db->prepare('UPDATE pedidos SET usado = valor WHERE id = ?')->execute([$id]);   // 1.39
  return ['pedido' => pedidoPublico(pedido($id))] + $extra;
}
/* 2.08: a venda futura aprovada vira dois lembretes no calendário (tipo vfut): FECHAR no dia em que o cliente disse que fecha e COBRAR no
   dia do pagamento. O pedido guarda os dois (dados.comp); o COBRAR feito = paga */
function vfutLancar(array $p, array $g): array {
  $db = db(); $d = json_decode((string)$p['dados'], true); if (!is_array($d)) $d = []; $resp = (string)($p['resp'] !== '' ? $p['resp'] : $p['nome']); $v = dinheiro((float)$p['valor']); $oque = (string)($d['oque'] ?? '');
  $obs = mb_substr('VENDA FUTURA DE ' . maiusculo((string)$p['nome']) . ' · VENDEDORA ' . (string)($d['vendedora'] ?? '') . ' · APROVADA POR ' . maiusculo(primeiroNome((string)$g['nome'])) . ((string)($d['obs'] ?? '') !== '' ? ' · ' . $d['obs'] : ''), 0, 500);
  $ins = $db->prepare("INSERT INTO compromissos (dia, hora, titulo, tipo, sub, obs, criado_em) VALUES (?, '10:00', ?, 'vfut', 'Venda futura', ?, ?)");
  $so1 = !empty($d['pac']) && (string)$d['fecha'] === (string)$d['paga']; $c1 = 0;   /* 2.16: o pacote futuro tem uma data só: só o COBRAR */
  if (!$so1) $ins->execute([(string)$d['fecha'], mb_substr('FECHAR · ' . $resp . ' · ' . $oque . ' · ' . $v, 0, 120), $obs, agora()]); $c1 = (int)$db->lastInsertId();
  $ins->execute([(string)$d['paga'], mb_substr('COBRAR · ' . $resp . ' · ' . $v . ' · ' . $oque, 0, 120), $obs, agora()]); $c2 = (int)$db->lastInsertId(); if ($so1) $c1 = $c2;
  $d['comp'] = [$c1, $c2]; $db->prepare('UPDATE pedidos SET dados = ? WHERE id = ?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), (int)$p['id']]);
  return ['comp' => [$c1, $c2]];
}
/* as vendas futuras para a tela (Festas → "Vendas futuras"): as esperando o gestor e as dos últimos 60 dias (pela data do pagamento) */
function vfutLista(): array {
  $db = db(); $lim = date('Y-m-d', agoraTs() - 60 * 86400); $o = []; $feito = [];
  foreach ($db->query("SELECT id, feito FROM compromissos WHERE tipo = 'vfut'")->fetchAll() as $c) $feito[(int)$c['id']] = (int)$c['feito'];
  foreach ($db->query("SELECT * FROM pedidos WHERE tipo = 'venda_futura' AND status IN ('pendente', 'usado', 'negado') ORDER BY id DESC LIMIT 300")->fetchAll() as $p) {
    $d = json_decode((string)$p['dados'], true); if (!is_array($d)) $d = []; if ($p['status'] !== 'pendente' && (string)($d['paga'] ?? '') < $lim) continue;
    $comp = array_map('intval', (array)($d['comp'] ?? []));
    $o[] = ['id' => (int)$p['id'], 'status' => (string)$p['status'], 'crianca' => (int)$p['crianca_id'], 'nome' => (string)$p['nome'], 'resp' => (string)$p['resp'], 'valor' => round((float)$p['valor'], 2),
      'oque' => (string)($d['oque'] ?? ''), 'fecha' => (string)($d['fecha'] ?? ''), 'paga' => (string)($d['paga'] ?? ''), 'vendedora' => (string)($d['vendedora'] ?? ''), 'obs' => (string)($d['obs'] ?? ''),
      'por' => (string)$p['pedido_nome'], 'dec' => (string)$p['decidido_nome'], 'comp' => $comp, 'pago' => isset($comp[1]) && !empty($feito[$comp[1]]), 'uni' => ($p['unidade_id'] ?? null) === null ? null : (int)$p['unidade_id']];
  }
  usort($o, fn($a, $b) => [$a['paga'], $a['id']] <=> [$b['paga'], $b['id']]); return $o;
}
// 1.55: o gestor corrige as horas de uma compra de horas (o pacote, ou as horas que vieram do sistema anterior): só as horas mudam (o valor
// fica), o saldo acompanha a diferença e não tira o que já foi usado. Fica o registro, com o motivo e quem mudou
function alterarHorasVenda(int $vendaId, int $min1, string $motivo, array $u): array {
  $db = db(); $q = $db->prepare('SELECT * FROM vendas WHERE id = ?'); $q->execute([$vendaId]); $v = $q->fetch(); $q->closeCursor();
  if (!$v) falha('Esta compra não existe mais: foi desfeita ou cancelada.', 404);
  $c = crianca((int)$v['crianca_id']); if (!$c) falha('Cliente não encontrado.', 404);
  $min0 = (int)$v['minutos']; $imp = (string)($v['servico'] ?? '') === SERV_IMPORTADO;
  if (!empty($v['auto']) || (string)($v['alug'] ?? '') !== '' || (int)($v['produto_id'] ?? 0) > 0 || ($min0 <= 0 && !$imp)) falha('Esta compra não é de horas: não dá para alterar as horas dela.');
  if ($min1 < 0 || $min1 > 999 * 60) falha('Escreva as horas certas do pacote (de 0 a 999 horas).');
  if ($min1 === $min0) falha('As horas são as mesmas de agora (' . hm($min0) . ').');
  if (mb_strlen($motivo) < 5) falha('Escreva o motivo da correção (por que as horas mudam).');
  $dif = $min1 - $min0; $agora = agora();
  $db->beginTransaction();
  try {
    $c = crianca((int)$c['id']);
    if ($dif < 0 && restante($c) < -$dif) { $db->rollBack(); $r = restante($c); falha('Essas horas já foram usadas: ' . ($r > 0 ? 'dá para tirar no máximo ' . hm($r) . ' (o saldo de agora).' : 'o saldo de agora é zero.'), 409); }
    $up = $db->prepare('UPDATE vendas SET minutos = ?, horas = ? WHERE id = ? AND minutos = ?'); $up->execute([$min1, round($min1 / 60, 2), $vendaId, $min0]);
    if (!$up->rowCount()) { $db->rollBack(); falha('Esta compra mudou agora mesmo em outro aparelho. Abra de novo.', 409); }
    $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min + ?) WHERE id = ?')->execute([$dif, (int)$c['id']]);
    $reg = registrar('alt', (int)$c['id'], $c['nome'], 'Horas do pacote corrigidas pelo gestor · ' . nomeDaVenda($v) . ' de ' . date('d/m', strtotime((string)$v['quando'])) . ': ' . hm($min0) . ' → ' . hm($min1)
      . ' (' . ($dif > 0 ? '+' : '−') . hm(abs($dif)) . ' no saldo) · Motivo: ' . $motivo . ' · por ' . maiusculo(primeiroNome((string)$u['nome'])));
    $db->commit();
  } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  $c = crianca((int)$c['id']);
  if ($c['entrou']) acertarCobrancas($c);   // está no espaço: a conta da visita acompanha o saldo novo
  elseif ($dif < 0 && restante($c) < periodo() && regra()['bloqueioAuto'] === 'sim' && (!isset($c['liberado']) || (int)$c['liberado'])) bloquearAcesso($c, false);
  elseif ($dif > 0 && restante($c) >= periodo() && isset($c['liberado']) && !(int)$c['liberado'] && regra()['bloqueioAuto'] === 'sim') bloquearAcesso($c, true);
  return ['venda' => $vendaId, 'de' => $min0, 'para' => $min1, 'saldo' => restante($c), 'registro' => $reg];
}
// 1.55: uma nota fiscal emitida (ou cancelada depois), como o painel do gestor e as Finanças mostram. vc: a venda foi cancelada e a nota
// continua valendo (o gestor cancela a nota também)
function notaPublica(array $p): array {
  $d = json_decode((string)$p['dados'], true); $d = is_array($d) ? $d : []; $k = is_array($d['canc'] ?? null) ? $d['canc'] : [];
  return ['id' => (int)$p['id'], 'numero' => (string)$p['numero'], 'quando' => (string)$p['feito_em'], 'uni' => (int)($p['unidade_id'] ?? 0), 'crianca' => $p['crianca_id'] === null ? null : (int)$p['crianca_id'],
    'nome' => (string)($d['nome'] ?? ''), 'doc' => (string)($d['doc'] ?? ''), 'cliente' => (string)($p['resp'] !== '' ? $p['resp'] : $p['nome']), 'item' => (string)($d['item'] ?? ''), 'valor' => round((float)$p['valor'], 2),
    'por' => (string)$p['feito_nome'], 'st' => $p['status'] === 'usado' ? 'emitida' : 'cancelada', 'canc' => (string)($k['motivo'] ?? ''), 'canc_por' => (string)($k['por'] ?? ''), 'canc_em' => (string)($k['em'] ?? ''),
    'vc' => $p['status'] === 'usado' && $p['venda_id'] === null && empty($d['livre']), 'livre' => !empty($d['livre'])];   // 2.55: livre = a nota com outro valor (sem a compra aqui)
}
// 1.41: a conta do pacote alterado com os três campos: "PACOTE 03 HORAS 360 (3h, R$ 225) para 4h: pacote R$ 300 + produto R$ 60 = R$ 360"
function altTextoPac(string $n, int $h0, float $de, int $h1, float $pac, float $prod, float $para, string $pn = ''): string {   /* 2.40: pn = qual produto */
  return $n . ' (' . $h0 . 'h, ' . dinheiro($de) . ') para ' . $h1 . 'h' . ($prod > 0.004 ? ': pacote ' . dinheiro($pac) . ' + produto ' . ($pn !== '' ? $pn . ' ' : '') . dinheiro($prod) . ' = ' . dinheiro($para) : ' por ' . dinheiro($para));
}
// 1.41: o valor do pacote e o do produto (os campos da tela nova): o total é a soma. null = o pedido do jeito antigo (só o valor novo)
function lerPacProd(array $in, string $k): ?array {
  if (!isset($in['pac'])) return null; $pac = round((float)$in['pac'], 2); $prod = round((float)($in['prod'] ?? 0), 2);
  if ($k !== 'pac') falha('O valor do pacote e o do produto são para um pacote.');
  if ($pac <= 0) falha('Escreva o valor do pacote.'); if ($prod < 0) falha('Confira o valor do produto.');
  /* 2.40: qual produto (obrigatório quando o produto tem valor; a tela de antes da 2.40 não manda o campo) */
  $pn = $prod > 0.004 ? mb_substr(trim((string)preg_replace('/\s+/u', ' ', maiusculo((string)($in['prod_nome'] ?? '')))), 0, 40) : '';
  if ($prod > 0.004 && array_key_exists('prod_nome', $in) && mb_strlen($pn) < 2) falha('Escreva qual é o produto (ex.: CARRINHO, CASINHA).');
  return ['pac' => $pac, 'prod' => $prod, 'para' => round($pac + $prod, 2), 'pn' => $pn];
}
// 1.39: a alteração aprovada na saída muda a venda na hora: o valor e, no pacote, as horas (o saldo do cliente acompanha). A venda tem de
// estar como era quando foi pedida e sem pagamento; se não estiver, o pedido sai da lista (cancelado) e quem aprovou fica sabendo
function alterarVendaNaSaida(array $p, array $g): array {
  $db = db(); $id = (int)$p['id']; $d = json_decode((string)$p['dados'], true); $d = is_array($d) ? $d : []; $it = (array)($d['item'] ?? []);
  $sai = function (string $res, string $msg) use ($db, $id) { $db->prepare("UPDATE pedidos SET status = 'cancelado', resultado = ? WHERE id = ?")->execute([$res, $id]); falha($msg . ' O pedido saiu da lista.', 409); };
  $q = $db->prepare('SELECT * FROM vendas WHERE id = ?'); $q->execute([(int)$p['venda_id']]); $v = $q->fetch(); $q->closeCursor();
  if (!$v) $sai('A VENDA NÃO EXISTE MAIS', 'Esta venda não existe mais: já foi desfeita ou cancelada.');
  if (pagoPorForma([(int)$v['id']])) $sai('A VENDA JÁ FOI PAGA', 'Esta venda já foi paga: o valor não muda mais.');
  $min0 = (int)$v['minutos']; if (abs((float)$v['valor'] - (float)($d['de'] ?? -1)) >= 0.005 || $min0 !== (int)($it['min'] ?? -1)) $sai('A VENDA MUDOU', 'Esta venda mudou depois do pedido. Peça de novo.');
  $c = crianca((int)$v['crianca_id']); $para = round((float)($d['para'] ?? 0), 2); $hh = !empty($d['horas']) ? (int)$d['horas'] : 0; $min1 = $hh > 0 ? $hh * 60 : $min0;
  if ($c && $min1 !== $min0) { if ((int)$c['pacote_min'] + $min1 - $min0 < (int)$c['usado_min']) $sai('AS HORAS JÁ FORAM USADAS', 'As horas deste pacote já foram usadas: não dá para diminuir para ' . $hh . 'h.');
    $db->prepare('UPDATE criancas SET pacote_min = pacote_min + ? WHERE id = ?')->execute([$min1 - $min0, (int)$c['id']]); }
  $serv = $min1 !== $min0 && trim((string)$v['servico']) !== '' ? 'PACOTE ' . $hh . 'H' : (string)$v['servico'];
  $db->prepare('UPDATE vendas SET valor = ?, minutos = ?, horas = ?, servico = ? WHERE id = ?')->execute([$para, $min1, round($min1 / 60, 2), $serv, (int)$v['id']]);
  if (isset($d['pac'])) $db->prepare('UPDATE vendas SET prod_nome = ?, prod_valor = ? WHERE id = ?')->execute([(string)($d['prod_nome'] ?? ''), round((float)($d['prod'] ?? 0), 2), (int)$v['id']]);   /* 2.40 */
  $por = primeiroNome((string)$p['pedido_nome']); $dec = primeiroNome((string)$g['nome']); $de = round((float)$v['valor'], 2);
  $reg = registrar('alt', (int)$v['crianca_id'], (string)($c['nome'] ?? $p['nome']), isset($d['pac']) ? 'Pacote alterado na saída · ' . altTextoPac((string)($it['n'] ?? nomeDaVenda($v)), intdiv($min0, 60), $de, intdiv($min1, 60), (float)$d['pac'], (float)($d['prod'] ?? 0), $para, (string)($d['prod_nome'] ?? '')) . ((string)$p['motivo'] !== '' ? ' · Motivo: ' . (string)$p['motivo'] : '') . ' · por ' . $por . ($dec !== '' && mb_strtoupper($dec) !== mb_strtoupper($por) ? ' · aprovado por ' . $dec : '') : 'Valor alterado na saída · ' . (string)($it['n'] ?? nomeDaVenda($v)) . ($hh > 0 ? ' (' . dur($min0) . '): ' . dinheiro($de) . ' → ' . $hh . 'h por ' : ': ' . dinheiro($de) . ' → ') . dinheiro($para) . ' · Motivo: ' . (string)$p['motivo'] . ' · por ' . $por . ($dec !== '' && mb_strtoupper($dec) !== mb_strtoupper($por) ? ' · aprovado por ' . $dec : ''));
  return ['venda' => (int)$v['id'], 'de' => $de, 'para' => $para, 'min' => $min1, 'registro' => $reg];
}
// 1.33: o pacote ou o produto da alteração de valor, achado na tabela da loja: o nome, o valor de agora (de) e como achar de novo na venda
function itemDoPacote(array $x): array {
  $h = (int)$x['h']; $tp = tipoPacote($x); $n = servicoDoPacote($x); if ($n === '') $n = 'PACOTE ' . ($tp === 'seco' ? 'SECO ' : '') . 'DE ' . $h . 'H';
  return ['k' => 'pac', 'h' => $h, 'tipo' => $tp, 'nome' => (string)($x['nome'] ?? ''), 'de' => round((float)$x['v'], 2), 'n' => $n];
}
function itemDoProduto(array $p, int $q): array { return ['k' => 'prod', 'p' => (int)$p['id'], 'q' => $q, 'de' => round($q * (float)$p['valor'], 2), 'n' => ($q > 1 ? $q . ' × ' : '') . (string)$p['nome']]; }
function itemDoValor(array $i): array {
  if (($i['k'] ?? '') === 'pac') { $h = (int)($i['h'] ?? 0); $tp = ($i['tipo'] ?? '') === 'produto' ? 'produto' : 'seco'; $nm = mb_strtolower(trim((string)($i['nome'] ?? ''))); $val = isset($i['v']) ? (float)$i['v'] : null;
    foreach ((array)(regra()['pacotes'] ?? []) as $x) { if ((int)$x['h'] !== $h || tipoPacote($x) !== $tp || mb_strtolower(trim((string)($x['nome'] ?? ''))) !== $nm) continue;
      if ($val !== null && abs((float)$x['v'] - $val) >= 0.005) continue; return itemDoPacote($x); }
    falha('Este pacote não está mais na tabela, ou o valor dele mudou. Feche e abra o atendimento de novo.'); }
  if (($i['k'] ?? '') === 'prod') { $p = produto((int)($i['p'] ?? 0)); if (!$p || !$p['ativo']) falha('Este produto saiu da lista. Feche e abra o atendimento de novo.');
    return itemDoProduto($p, max(1, min(99, (int)($i['q'] ?? 1)))); }
  falha('Escolha o pacote ou o produto que muda de valor.');
}
// a alteração de valor na venda: o pedido tem de ser de quem vende, aprovado há menos de 30 min, do mesmo cliente (ou de um irmão) e do
// mesmo item, com o mesmo valor de tabela de quando foi pedido. Devolve o que a venda usa: o valor novo, o nome, o motivo e quem aprovou
function autorizacaoDeValor(int $id, array $u, array $c, array $it): array {
  $p = pedido($id); if (!$p || $p['tipo'] !== 'valor') falha('A alteração de valor não foi encontrada. Peça de novo.', 409);
  if ((int)$p['pedido_por'] !== (int)$u['id']) falha('Esta alteração de valor foi pedida em outro login. Peça de novo.', 403);
  if ($p['status'] === 'pendente') falha('A alteração de valor ainda espera a resposta do supervisor ou do gestor.', 409);
  if ($p['status'] === 'negado') falha('A alteração de valor não foi aprovada.', 409);
  if ($p['status'] !== 'liberado') falha($p['status'] === 'usado' ? 'Esta alteração de valor já foi usada em outra venda.' : 'A alteração de valor não vale mais. Peça de novo.', 409);
  if (time() - strtotime((string)$p['decidido_em']) > PEDIDO_VALE_MIN * 60) falha('A aprovação da alteração de valor venceu (vale por ' . PEDIDO_VALE_MIN . ' min). Peça de novo.', 409);
  $pc = (int)$p['crianca_id']; if ($pc !== (int)$c['id'] && !in_array($pc, array_map(fn($x) => (int)$x['id'], irmaosDe($c)), true)) falha('A alteração de valor foi aprovada para outro cliente.', 409);
  $d = json_decode((string)$p['dados'], true); $pi = is_array($d) ? (array)($d['item'] ?? []) : [];
  $mesmo = ($pi['k'] ?? '') === $it['k'] && ($it['k'] === 'pac' ? (int)($pi['h'] ?? 0) === $it['h'] && ($pi['tipo'] ?? '') === $it['tipo'] && mb_strtolower(trim((string)($pi['nome'] ?? ''))) === mb_strtolower(trim($it['nome']))
    : (int)($pi['p'] ?? 0) === $it['p'] && (int)($pi['q'] ?? 0) === $it['q']) && abs((float)($d['de'] ?? -1) - $it['de']) < 0.005;
  if (!$mesmo) falha('A alteração aprovada é de outro item (' . ($pi['n'] ?? '') . '), ou o valor da tabela mudou. Peça de novo.', 409);
  $hh = $it['k'] === 'pac' && !empty($d['horas']) ? (int)$d['horas'] : null;   // 1.36
  return ['id' => (int)$p['id'], 'para' => round((float)($d['para'] ?? 0), 2), 'de' => $it['de'], 'n' => $it['n'], 'motivo' => (string)$p['motivo'], 'por' => (string)$p['pedido_nome'], 'dec' => (string)$p['decidido_nome'], 'horas' => $hh, 'h' => $it['k'] === 'pac' ? (int)$it['h'] : null, 'pac' => isset($d['pac']) ? (float)$d['pac'] : null, 'prod' => isset($d['prod']) ? (float)$d['prod'] : null, 'pn' => (string)($d['prod_nome'] ?? '')];   // 1.41; 2.40: pn
}
// a venda saiu com o valor alterado: o pedido fica usado (com a venda) e o dia ganha a linha "Valor alterado"
function usarAlteracao(array $a, int $vendaId, array $c, array $u): int {
  db()->prepare("UPDATE pedidos SET status = 'usado', usado = ?, venda_id = ?, feito_por = ?, feito_nome = ?, feito_em = ?, resultado = 'VALOR ALTERADO NA VENDA' WHERE id = ? AND status = 'liberado'")
    ->execute([$a['para'], $vendaId, (int)$u['id'], (string)$u['nome'], agora(), $a['id']]);
  $por = primeiroNome($a['por']); $dec = primeiroNome($a['dec']);
  if ($a['pac'] !== null) db()->prepare('UPDATE vendas SET prod_nome = ?, prod_valor = ? WHERE id = ?')->execute([(string)($a['pn'] ?? ''), round((float)($a['prod'] ?? 0), 2), $vendaId]);   /* 2.40 */
  if ($a['pac'] !== null) return registrar('alt', (int)$c['id'], $c['nome'], 'Pacote alterado · ' . altTextoPac($a['n'], (int)$a['h'], (float)$a['de'], (int)($a['horas'] ?: $a['h']), (float)$a['pac'], (float)($a['prod'] ?? 0), (float)$a['para'], (string)($a['pn'] ?? '')) . ($a['motivo'] !== '' ? ' · Motivo: ' . $a['motivo'] : '') . ' · por ' . $por . ($dec !== '' && mb_strtoupper($dec) !== mb_strtoupper($por) ? ' · aprovado por ' . $dec : ''));   // 1.41
  return registrar('alt', (int)$c['id'], $c['nome'], 'Valor alterado · ' . $a['n'] . (!empty($a['horas']) ? ' (' . $a['h'] . 'h): ' . dinheiro($a['de']) . ' → ' . $a['horas'] . 'h por ' : ': ' . dinheiro($a['de']) . ' → ') . dinheiro($a['para']) . ' · Motivo: ' . $a['motivo'] . ' · por ' . $por . ($dec !== '' && mb_strtoupper($dec) !== mb_strtoupper($por) ? ' · aprovado por ' . $dec : ''));
}
// o desconto de quem não é gestor: o pedido tem de ser dele, estar liberado, dentro do prazo, ser do mesmo cliente e caber no valor liberado
function autorizacaoDeDesconto(int $id, array $u, ?array $c, float $v): array {
  if ($id <= 0) falha('Só o gestor dá desconto.', 403);
  $p = pedido($id); if (!$p || $p['tipo'] !== 'desconto' || (int)$p['pedido_por'] !== (int)$u['id']) falha('Só o gestor dá desconto.', 403);
  if ($p['status'] === 'pendente') falha('O gestor ainda não respondeu ao pedido de desconto.', 409);
  if ($p['status'] === 'negado') falha('O gestor não liberou este desconto.', 409);
  // usado em parte agora há pouco: é a mesma conta (a saída em conjunto usa o mesmo desconto em mais de uma criança, uma em seguida da outra)
  $junto = $p['status'] === 'usado' && (float)$p['usado'] < (float)$p['valor'] - 0.004 && $p['feito_em'] && time() - strtotime((string)$p['feito_em']) <= PEDIDO_JUNTO_SEG;
  if ($p['status'] !== 'liberado' && !$junto) falha($p['status'] === 'usado' ? 'Este desconto já foi usado.' : 'O pedido de desconto não vale mais. Peça de novo.', 409);
  if (time() - strtotime((string)$p['decidido_em']) > PEDIDO_VALE_MIN * 60) falha('A autorização do desconto venceu (vale por ' . PEDIDO_VALE_MIN . ' min). Peça de novo.', 409);
  if ($c) { $pc = (int)$p['crianca_id']; if ((int)$c['id'] !== $pc && !in_array($pc, array_map(fn($x) => (int)$x['id'], irmaosDe($c)), true)) falha('O desconto foi liberado para outro cliente.', 409); }
  if ($v > round((float)$p['valor'] - (float)$p['usado'], 2) + 0.004) falha('O desconto liberado é de ' . dinheiro((float)$p['valor']) . ((float)$p['usado'] > 0.004 ? ' (já foram usados ' . dinheiro((float)$p['usado']) . ')' : '') . '.', 409);
  return $p;
}
/* 1.65: o crédito em mais de 4x de quem não é gestor: o pedido tem de ser dele, estar liberado, dentro do prazo, do mesmo cliente (ou irmão)
   e de pelo menos essas vezes */
function autorizacaoDeParcelas(int $id, array $u, array $c, int $n): array {
  $msg = 'No crédito em ' . $n . 'x, só com a liberação do gestor (até ' . parcLivre() . 'x não precisa).';
  if ($id <= 0) falha($msg, 403);
  $p = pedido($id); if (!$p || $p['tipo'] !== 'parcelas' || (int)$p['pedido_por'] !== (int)$u['id']) falha($msg, 403);
  $junto = $p['status'] === 'usado' && (int)($p['feito_por'] ?? 0) === (int)$u['id'] && time() - strtotime((string)$p['feito_em']) <= 180;   // 1.89: a saída em conjunto (cada criança e cada carrinho da mesma conta)
  if (!$junto) {
  if ($p['status'] === 'pendente') falha('O gestor ainda não respondeu ao pedido do crédito em ' . $n . 'x.', 409);
  if ($p['status'] === 'negado') falha('O gestor não liberou o crédito em ' . $n . 'x.', 409);
  if ($p['status'] !== 'liberado') falha($p['status'] === 'usado' ? 'Esta liberação já foi usada.' : 'O pedido do crédito em ' . $n . 'x não vale mais. Peça de novo.', 409);
  if (time() - strtotime((string)$p['decidido_em']) > PEDIDO_VALE_MIN * 60) falha('A liberação do crédito em ' . $n . 'x venceu (vale por ' . PEDIDO_VALE_MIN . ' min). Peça de novo.', 409);
  }
  $pc = (int)$p['crianca_id']; if ((int)$c['id'] !== $pc && !in_array($pc, array_map(fn($x) => (int)$x['id'], irmaosDe($c)), true)) falha('A liberação do crédito foi para outro cliente.', 409);
  $d = json_decode((string)$p['dados'], true); if ((int)($d['n'] ?? 0) < $n) falha('O gestor liberou até ' . (int)($d['n'] ?? 0) . 'x, não ' . $n . 'x.', 409);
  return $p;
}
function usarParcelas(array $p, int $vendaId, int $n, array $u): void {
  db()->prepare("UPDATE pedidos SET status = 'usado', venda_id = ?, feito_por = ?, feito_nome = ?, feito_em = ?, resultado = ? WHERE id = ? AND status = 'liberado'")
    ->execute([$vendaId, (int)$u['id'], (string)$u['nome'], agora(), 'CRÉDITO EM ' . $n . 'X NA VENDA', (int)$p['id']]);
}
/* 1.89: em quantas vezes no crédito, em todo pagamento (a venda, a saída, a devolução, o Receber): até 4x qualquer um escolhe; de 5x a 12x,
   só o gestor (ou com a liberação dele, pedida antes). Sem o crédito no pagamento (ou sem escolher as vezes): 0 */
const PARC_NADA = ['n' => 0, 'aut' => null];
function lerParcelas(array $in, array $pg, ?array $u, array $c): array {
  $n = (int)($in['parcelas'] ?? 0); if (!isset($pg['credito']) || $n < 1 || $n > 12) return PARC_NADA;   // (fora de 1 a 12, como na festa antes: a venda passa e as vezes não ficam)
  $aut = $n > parcLivre() && ($u['papel'] ?? '') !== 'gestor' ? autorizacaoDeParcelas((int)($in['parc_ped'] ?? 0), $u ?: ['id' => 0], $c, $n) : null;
  return ['n' => $n, 'aut' => $aut];
}
// depois de gravar o pagamento: as vendas que receberam no crédito agora ficam com as vezes; a liberação do gestor fica usada
function anotarParcelas(int $antesPg, array $parc, ?array $u, int $criancaId): void {
  if (!$parc['n']) return;
  $q = db()->prepare("SELECT DISTINCT venda_id FROM pagamentos WHERE id > ? AND crianca_id = ? AND forma = 'credito' ORDER BY venda_id"); $q->execute([$antesPg, $criancaId]);
  $ids = array_values(array_filter(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)))); if (!$ids) return;
  db()->prepare('UPDATE vendas SET parcelas = ? WHERE id IN (' . implode(',', $ids) . ')')->execute([$parc['n']]);
  if ($parc['aut'] && $u) usarParcelas($parc['aut'], $ids[0], $parc['n'], $u);
}
function usarAutorizacao(?array $desc, float $valor): void { // o desconto autorizado foi aplicado: fica marcado quanto dele já foi usado
  if (!$desc || empty($desc['aut']) || $valor <= 0) return;
  db()->prepare("UPDATE pedidos SET usado = ROUND(usado + ?, 2), feito_em = ?, status = 'usado' WHERE id = ?")->execute([$valor, agora(), (int)$desc['aut']]);   // vale uma vez: usado, mesmo em parte, não fica para outra conta
}
// a conta deste cliente foi fechada (a venda, a saída): o pedido de desconto que ainda esperava a resposta não tem mais para onde ir
function fecharPedidosDeDesconto(?array $u, array $c, string $onde): void { // $onde: 'venda' (o atendimento) ou 'saida'
  if (!$u) return;
  db()->prepare("UPDATE pedidos SET status = 'cancelado', resultado = 'A CONTA FOI FECHADA SEM ESPERAR A RESPOSTA' WHERE tipo = 'desconto' AND status = 'pendente' AND pedido_por = ? AND crianca_id = ? AND dados LIKE ?")->execute([(int)$u['id'], (int)$c['id'], '%"onde":"' . ($onde === 'saida' ? 'saida' : 'venda') . '"%']);
  if ($onde === 'venda' && ($k = crianca((int)$c['id'])) && !$k['entrou']) db()->prepare("UPDATE pedidos SET status = 'cancelado', resultado = 'A CONTA FOI FECHADA NO ATENDIMENTO' WHERE tipo = 'desconto' AND status = 'pendente' AND crianca_id = ? AND dados LIKE ?")->execute([(int)$c['id'], '%"onde":"saida"%']);   // 1.45 (auditoria): sem a criança no espaço, não há saída para esperar o desconto
  db()->prepare("UPDATE pedidos SET status = 'cancelado', resultado = 'A CONTA FOI FECHADA SEM ESPERAR A RESPOSTA' WHERE tipo = 'parcelas' AND status = 'pendente' AND pedido_por = ? AND crianca_id = ?")->execute([(int)$u['id'], (int)$c['id']]);   // 1.65
  db()->prepare("UPDATE pedidos SET status = 'cancelado', resultado = 'A CONTA FOI FECHADA SEM ESPERAR A RESPOSTA' WHERE tipo = 'valor' AND status = 'pendente' AND pedido_por = ? AND crianca_id = ? AND venda_id IS " . ($onde === 'saida' ? 'NOT NULL' : 'NULL'))->execute([(int)$u['id'], (int)$c['id']]);   // 1.33 (1.39: o da saída fecha com a saída)
  if ($onde === 'saida') db()->prepare("UPDATE pedidos SET status = 'cancelado', resultado = 'A CRIANÇA SAIU SEM ESPERAR A RESPOSTA' WHERE tipo IN ('desconto', 'valor') AND status = 'pendente' AND crianca_id = ? AND (dados LIKE ? OR venda_id IS NOT NULL)")->execute([(int)$c['id'], '%"onde":"saida"%']);   // 1.45 (auditoria): a saída fechou a conta, dê quem der a saída
}

/* ---------- registros (o painel "Hoje na recepção") e as mensagens ---------- */
function registrar(string $tipo, ?int $criancaId, string $nome, string $texto, ?string $quando = null, $unidade = false): int {   // 1.06: $unidade = false usa a do aparelho que fez o pedido
  if (!empty($GLOBALS['PS_OFF']['em'])) $texto .= ' · feito sem internet (chegou às ' . date('H:i') . ')';   // 1.40
  elseif (!empty($GLOBALS['PS_OFF']['negada'])) $texto .= ' · mandado como feito sem internet às ' . $GLOBALS['PS_OFF']['negada'] . ', mas o aparelho estava com internet: vale a hora de chegada';   // 1.49
  db()->prepare('INSERT INTO registros (quando, tipo, crianca_id, nome, texto, unidade_id) VALUES (?, ?, ?, ?, ?, ?)')->execute([$quando ?: agora(), $tipo, $criancaId, $nome, $texto, $unidade === false ? unidadeDoPedido() : ($unidade ? (int)$unidade : null)]);
  return (int)db()->lastInsertId();
}
// 1.19: os outros cadastros ativos do mesmo cliente, do jeito que a tela junta os irmãos: o mesmo documento do responsável; quando um dos
// dois não tem documento, o mesmo telefone (os 8 últimos números). Serve para a correção do nome do responsável e dos telefones valer para todos.
function chaveDocCadastro(array $x): string {
  $v = (string)($x['cpf'] ?? '');
  if (($x['doc_tipo'] ?? 'cpf') === 'rne') { $r = (string)preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($v)); return strlen($r) >= 5 ? 'r' . $r : ''; }
  $d = (string)preg_replace('/\D/', '', $v); return strlen($d) === 11 ? 'c' . $d : '';
}
function chaveTelCadastro(array $x): string { $d = (string)preg_replace('/\D/', '', (string)($x['tel'] ?? '')); return strlen($d) >= 8 ? substr($d, -8) : ''; }
/* 2.58: o cliente está em atendimento (alguém da família no espaço, ou um carrinho fora da loja): a nota fiscal dele, só o gestor */
function emAtendimento(array $c): bool {
  $ids = array_values(array_unique(array_merge([(int)$c['id']], array_map(fn($x) => (int)$x['id'], irmaosDe($c))))); $m = implode(',', array_fill(0, count($ids), '?'));
  $q = db()->prepare("SELECT 1 FROM criancas WHERE id IN ($m) AND entrou IS NOT NULL UNION ALL SELECT 1 FROM vendas WHERE crianca_id IN ($m) AND alug != '' AND voltou IS NULL LIMIT 1");
  $q->execute(array_merge($ids, $ids)); return (bool)$q->fetchColumn();
}
function irmaosDe(array $c): array {
  $doc = chaveDocCadastro($c); $tel = chaveTelCadastro($c); if ($doc === '' && $tel === '') return [];
  // o banco separa os candidatos (o mesmo documento escrito de qualquer jeito, ou o telefone com o mesmo final); a conferência exata é feita abaixo
  $docLimpo = $doc !== '' ? substr($doc, 1) : '~';
  $q = db()->prepare("SELECT * FROM criancas WHERE ativo = 1 AND id != ? AND (upper(replace(replace(replace(replace(cpf, '.', ''), '-', ''), ' ', ''), '/', '')) = ? OR (? != '' AND tel LIKE ?))");
  $q->execute([(int)$c['id'], $docLimpo, $tel, '%' . substr($tel, -4)]);
  return array_values(array_filter($q->fetchAll(), function ($x) use ($doc, $tel) {
    $dx = chaveDocCadastro($x);
    return ($doc !== '' && $dx === $doc) || (!($doc !== '' && $dx !== '') && $tel !== '' && chaveTelCadastro($x) === $tel);
  }));
}
function crianca(int $id): ?array { $q = db()->prepare('SELECT * FROM criancas WHERE id = ?'); $q->execute([$id]); return $q->fetch() ?: null; }

/* ---------- entrada, saída e venda: a mesma lógica para o iDFace e para o toque na tela ---------- */
// sem o iDFace (leitura desligada em Ajustes), a equipe marca a entrada e a saída na tela
function modoManual(): bool { $c = cfg('idface', null); return is_array($c) && array_key_exists('ligado', $c) && !$c['ligado']; }
function fazerEntrada(array $c, string $origem = 'iDFace', ?string $quando = null, ?int $festa = null, string $vend = ''): array {   /* $vend (1.68): quem abriu o pedido (vazio = não se sabe: o iDFace, a festa) */   // $festa (1.29): a festa em que a criança entra (a visita não cobra nem gasta horas)
  $db = db(); $quando = $quando ?: agora();
  $db->prepare('UPDATE criancas SET entrou = ?, unidade_id = ?, festa_id = ?, vend_abriu = ? WHERE id = ?')->execute([$quando, unidadeDoPedido(), $festa ?: null, $vend, $c['id']]);   // 1.06: a unidade do aparelho em que a entrada foi dada (o iDFace não diz: fica sem)
  $c['entrou'] = $quando; $c['unidade_id'] = unidadeDoPedido(); $c['festa_id'] = $festa ?: null; $c['vend_abriu'] = $vend;
  $fh = !$festa ? horasDaFamiliaNaEntrada(crianca((int)$c['id'])) : null;   /* 2.11: as horas do CPF vão com a criança */
  $reg = registrar('ent', (int)$c['id'], $c['nome'], $origem === 'iDFace' ? 'Rosto reconhecido na entrada' : (modoManual() ? 'Entrada registrada' : 'Entrada registrada à mão'), $quando);
  $msg = null;
  if (regra()['autoMsg'] !== 'nao') {
    $texto = 'Oi, ' . nomeMsg(primeiroNome($c['resp'])) . '! ' . nomeMsg($c['nome']) . ' chegou na Play Space às ' . date('H:i', strtotime($quando)) . ' 💛 Qualquer coisa a gente te chama por aqui.';   // sem falar de tempo nem de saldo
    $msg = mandarMensagem($c, $texto, true, $c['foto'] ?: '');
  }
  return ['registro' => $reg, 'mensagem' => $msg] + ($fh ? ['familia' => $fh] : []);
}
/* ---------- 1.15: a recreação avulsa é acertada na saída ---------- */
// O cliente sem pacote não compra períodos na entrada: a criança entra e o tempo dela é contado em períodos (30 min, com a tolerância
// entre um e outro). Cada período que a visita passa a precisar, além do que o cliente já tinha, vira sozinho uma venda a receber
// (vendas.auto = 1), e a saída recebe todas de uma vez. Para quem está na recreação avulsa essa é a conta normal da visita: a venda leva
// 'avulsa' e não vira linha em "Hoje na recepção" (a linha da saída diz o total). Para quem entrou com horas de pacote e elas acabaram,
// é o tempo além do pacote: a venda leva 'além do pacote' e vira uma linha do dia, para todo mundo ver que o pacote acabou.
// É chamada a cada pulso das telas abertas (para quem ainda está no espaço) e de novo na saída. Devolve as vendas criadas.
const ITENS_AVULSA = 'avulsa';
const ITENS_ALEM = 'além do pacote';
// de onde sai o tempo desta criança agora: 'pacote' ou 'avulso' (a mesma conta de planosDoEspaco(), para uma criança só)
function planoDaCrianca(array $c): string {
  $pf = planoPelasHoras($c); if ($pf !== null) return $pf;   /* 2.11 */
  $resta = (int)$c['usado_min'];
  $q = db()->prepare('SELECT minutos, servico, itens, auto, voucher FROM vendas WHERE crianca_id = ? AND minutos > 0 ORDER BY id'); $q->execute([(int)$c['id']]);
  foreach ($q->fetchAll() as $v) { $m = (int)$v['minutos']; if ($resta >= $m) { $resta -= $m; continue; } $resta = 0; if (ehVendaDePacote($v)) return 'pacote'; }
  return 'avulso';
}
// "recreação avulsa: R$ 240" ou "além do pacote 0h30: R$ 80": como as cobranças a receber de uma visita aparecem na linha da saída
function textoAutos(array $atraso, string $plano): string {
  return $plano === 'pacote' ? ITENS_ALEM . ' ' . dur((int)$atraso['n'] * periodo()) . ': ' . dinheiro((float)$atraso['valor']) : 'recreação avulsa ' . dur((int)$atraso['n'] * periodo()) . ': ' . dinheiro((float)$atraso['valor']);
}
// as cobranças que nasceram sozinhas nesta visita e ainda não foram pagas saem (a entrada foi desfeita): devolve quantas e quanto
// 1.45 (auditoria): o voucher desta visita (o tempo dado na entrada, sem valor) que não foi usado: sai junto quando a entrada é cancelada
// (ou na cortesia) e quando a visita passa para a festa. Antes ficava como 30 min guardados
function vouchersDaVisita(array $c): array {
  if (empty($c['entrou'])) return [];
  return array_values(array_filter(vendasDaVisita($c), fn($v) => (string)($v['voucher'] ?? '') !== '' && (float)$v['valor'] <= 0.004 && (int)$v['minutos'] > 0 && !pagoPorForma([(int)$v['id']])));
}
function tirarAutosDaVisita(array $c): array {
  $o = ['n' => 0, 'valor' => 0.0, 'minutos' => 0]; if (!$c['entrou']) return $o; $db = db();
  $q = $db->prepare('SELECT * FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = vendas.id)'); $q->execute([(int)$c['id'], (string)$c['entrou']]);
  foreach ($q->fetchAll() as $x) {
    $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$x['minutos'], (int)$c['id']]);
    $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([(int)$x['id']]);
    if (!empty($x['registro'])) $db->prepare("DELETE FROM registros WHERE id = ? AND crianca_id = ? AND tipo = 'cobr'")->execute([(int)$x['registro'], (int)$c['id']]);
    $o['n']++; $o['valor'] += (float)$x['valor']; $o['minutos'] += (int)$x['minutos'];
  }
  return $o;
}
// criança dos dados de exemplo (o teste do rodapé): com elas a regra do tempo vale desde a entrada, para a tela mostrar tudo funcionando
function ehExemplo(int $id): bool {
  if (!isset($GLOBALS['_exemplo'])) { $e = cfg('exemplo', null); $GLOBALS['_exemplo'] = is_array($e) ? array_map('intval', (array)($e['criancas'] ?? [])) : []; }
  return in_array($id, $GLOBALS['_exemplo'], true);
}
function acertarCobrancas(array $c, ?string $quando = null): array {
  if (!$c['entrou'] || !servicoTempo() || !empty($c['festa_id'])) return [];   // 1.29: a visita da festa não gera cobrança
  if ((string)$c['entrou'] < (string)cfg('cobranca_desde', '') && !ehExemplo((int)$c['id'])) return [];   // já estava no espaço quando esta regra chegou: não gera cobrança para trás
  $fim = $quando ? strtotime($quando) : agoraTs();
  if ((int)$c['usado_min'] + cobrar(minutosDaVisita($c, $fim)) <= (int)$c['pacote_min']) return [];
  $db = db(); $s = servicoTempo(); $p = periodo(); $criadas = [];
  $db->exec('BEGIN IMMEDIATE');   // uma tela de cada vez: duas perguntando no mesmo instante não geram a cobrança em dobro
  try {
    $c = crianca((int)$c['id']);
    if ($c && $c['entrou']) {
      $min = minutosDaVisita($c, $fim); $falta = (int)$c['usado_min'] + cobrar($min) - (int)$c['pacote_min'];
      $avulsa = planoDaCrianca($c) !== 'pacote';   // 1.15: recreação avulsa (a conta normal da visita) ou o tempo além do pacote que acabou
      for ($n = $falta > 0 ? (int)ceil($falta / $p) : 0; $n > 0; $n--) {
        $preco = precoPcd($c, (float)$s['v']); $pcdTxt = $preco < (float)$s['v'] ? ' · PCD ' . pcdDesc() . '%' : '';   // a criança PCD paga a recreação avulsa com o mesmo desconto
        $x = fazerVenda($c, $p, $preco, null, ($avulsa ? ITENS_AVULSA : ITENS_ALEM) . $pcdTxt, (string)$s['n'], true,
          $avulsa ? '' : 'As horas do pacote acabaram · ' . $s['n'] . ' +' . dur($p) . ' · ' . dinheiro($preco) . $pcdTxt . ' a receber na saída',
          ($pcdTxt !== '' ? ['pcd' => 1, 'cheio' => (float)$s['v']] : []) + ($avulsa ? ['quieto' => 1] : []));
        $criadas[] = $x['venda']; $c = crianca((int)$c['id']);
      }
    }
    $db->exec('COMMIT');
  } catch (Throwable $e) { try { $db->exec('ROLLBACK'); } catch (Throwable $e2) { } throw $e; }
  return $criadas;
}
function acertarTodas(): int { $n = 0; foreach (db()->query('SELECT * FROM criancas WHERE entrou IS NOT NULL AND ativo = 1')->fetchAll() as $c) $n += count(acertarCobrancas($c)); return $n; }
// as cobranças por atraso de uma visita (entre a entrada e a saída): quantas e quanto
function atrasoDaVisita(int $criancaId, string $entrou, ?string $ate = null): array {
  $q = db()->prepare('SELECT COUNT(*) n, COALESCE(SUM(valor), 0) v FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND quando <= ? AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = vendas.id)'); $q->execute([$criancaId, $entrou, $ate ?: '9999']); $a = $q->fetch();   // só as que ainda não foram pagas
  return ['n' => (int)$a['n'], 'valor' => (float)$a['v']];
}
// 1.00: a cobrança por atraso que deixou de ser necessária. O cliente comprou tempo (ou um pacote) com a criança no espaço, e o que ele
// comprou já cobre o que ela ficou: as cobranças por atraso desta visita que ainda não foram pagas e sobraram são desfeitas (saem da conta,
// e os minutos delas saem do saldo). Vale na hora da venda e, por garantia, na saída. É a mesma conta que a folha da saída já mostrava.
function desfazerCobrancasSobrando(array $c, ?string $quando = null): array {
  $nada = ['n' => 0, 'valor' => 0.0]; if (!$c['entrou'] || !servicoTempo() || !empty($c['festa_id'])) return $nada;
  $db = db(); $p = periodo(); $fim = $quando ? strtotime($quando) : agoraTs();
  $q = $db->prepare('SELECT v.* FROM vendas v WHERE v.crianca_id = ? AND v.auto = 1 AND v.quando >= ? AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = v.id) ORDER BY v.id DESC');
  $q->execute([(int)$c['id'], (string)$c['entrou']]); $autos = $q->fetchAll(); if (!$autos) return $nada;
  $minAutos = 0; foreach ($autos as $x) $minAutos += (int)$x['minutos'];
  $comprado = (int)$c['pacote_min'] - $minAutos;   // o que o cliente comprou de verdade (sem as cobranças por atraso a receber)
  $precisa = max(0, (int)ceil(((int)$c['usado_min'] + cobrar(minutosDaVisita($c, $fim)) - $comprado) / $p));   // quantas cobranças por atraso a visita ainda precisa
  $tirar = array_slice($autos, 0, max(0, count($autos) - $precisa)); if (!$tirar) return $nada;   // as mais novas saem primeiro
  $valor = 0.0; $db->beginTransaction();
  try {
    foreach ($tirar as $x) {
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$x['minutos'], (int)$c['id']]);
      $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([(int)$x['id']]); $valor += (float)$x['valor'];
      if (!empty($x['registro'])) $db->prepare("UPDATE registros SET texto = REPLACE(texto, ' a receber na saída', '') || ' · desfeita: o tempo comprado cobre a visita' WHERE id = ? AND crianca_id = ? AND texto NOT LIKE '% · desfeita%'")->execute([(int)$x['registro'], (int)$c['id']]);
    }
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
  return ['n' => count($tirar), 'valor' => $valor];
}
/* 2.10 (o dono: "quando eu entro alguém no avulso, eu tenho que, mesmo assim, ter a opção de descontar do pacote no final ... Se a pessoa
   tiver horas no pacote"): as horas são da família (o mesmo responsável). A criança que entrou na recreação avulsa usa, na saída, as horas
   guardadas dos irmãos: os minutos passam do pacote do irmão para o dela, e as cobranças avulsas que sobram são desfeitas logo depois
   (desfazerCobrancasSobrando). Passa só o que tira cobrança: o que não fecha um período inteiro continua avulso. Fica em fam_horas (o
   Desfazer da saída devolve) e nos registros das duas crianças */
function autosDaVisitaMin(array $c): int {
  if (empty($c['entrou'])) return 0;
  $q = db()->prepare('SELECT COALESCE(SUM(minutos), 0) FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = vendas.id)');
  $q->execute([(int)$c['id'], (string)$c['entrou']]); return (int)$q->fetchColumn();
}
function horasLivresDaFamilia(array $c, int $fim): array {   // [[irmão, minutos livres]]: o que tem mais primeiro (quem está no espaço guarda o que a visita dele já usou)
  $L = [];
  foreach (irmaosDe($c) as $x) {
    $uso = !empty($x['entrou']) && empty($x['festa_id']) ? cobrar(minutosDaVisita($x, $fim)) : 0;
    $tem = (int)$x['pacote_min'] - autosDaVisitaMin($x) - (int)$x['usado_min'] - $uso;
    if ($tem > 0) $L[] = [$x, $tem];
  }
  usort($L, fn($a, $b) => ($b[1] <=> $a[1]) ?: ((int)$a[0]['id'] <=> (int)$b[0]['id']));
  return $L;
}
function usarHorasDaFamilia(array $c, string $quando): ?array {
  if (empty($c['entrou']) || !servicoTempo() || !empty($c['festa_id'])) return null;
  $p = periodo(); $fim = strtotime($quando);
  $precisa = (int)$c['usado_min'] + cobrar(minutosDaVisita($c, $fim)) - ((int)$c['pacote_min'] - autosDaVisitaMin($c)); if ($precisa <= 0) return null;
  $L = horasLivresDaFamilia($c, $fim); $tem = array_sum(array_map(fn($y) => $y[1], $L)); if ($tem <= 0) return null;
  $fica = $tem >= $precisa ? 0 : (int)ceil(($precisa - $tem) / $p); $t = $precisa - $fica * $p; if ($t <= 0) return null;
  $db = db(); $de = []; $resta = $t; $db->beginTransaction();
  try {
    foreach ($L as [$x, $livre]) { if ($resta <= 0) break; $m = min($livre, $resta); $resta -= $m;
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([$m, (int)$x['id']]);
      $rd = registrar('alt', (int)$x['id'], (string)$x['nome'], 'Horas da família: ' . dur($m) . ' usadas na visita de ' . $c['nome']);
      $db->prepare('INSERT INTO fam_horas (quando, para, de, minutos, reg_de) VALUES (?, ?, ?, ?, ?)')->execute([agora(), (int)$c['id'], (int)$x['id'], $m, $rd]);
      $de[] = ['id' => (int)$db->lastInsertId(), 'crianca' => (int)$x['id'], 'nome' => (string)$x['nome'], 'min' => $m]; }
    $db->prepare('UPDATE criancas SET pacote_min = pacote_min + ? WHERE id = ?')->execute([$t, (int)$c['id']]);
    $rp = registrar('alt', (int)$c['id'], (string)$c['nome'], 'Horas da família: ' . dur($t) . ' do pacote de ' . implode(' e ', array_map(fn($y) => $y['nome'], $de)) . ' (descontou do pacote, em vez de pagar avulso)');
    $db->prepare('UPDATE fam_horas SET reg_para = ? WHERE id IN (' . implode(',', array_map(fn($y) => (int)$y['id'], $de)) . ')')->execute([$rp]);
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
  return ['min' => $t, 'de' => $de];
}
function horasDoCpf(): bool { return (string)(regra()['horasCpf'] ?? 'sim') !== 'nao'; }   /* 2.11: as horas são do CPF */
function lojaPedeSenha(): bool { return (string)(regra()['senhaUni'] ?? 'nao') === 'sim'; }   /* 2.21: a tela de entrar da loja pede a senha da unidade (de fábrica, não) */
/* 2.11: na entrada, as horas do CPF vão com a criança que entra: tudo o que os irmãos que estão fora têm guardado; e, se mesmo assim ela não
   tem um período, uma parte igual do que sobra aos irmãos que estão no espaço (em períodos inteiros) */
function horasDaFamiliaNaEntrada(array $c): ?array {
  if (!horasDoCpf() || !servicoTempo() || !empty($c['festa_id']) || empty($c['entrou'])) return null;
  $p = periodo(); $fim = agoraTs(); $L = irmaosDe($c); if (!$L) return null; $mov = [];
  foreach ($L as $x) if (empty($x['entrou'])) { $m = (int)$x['pacote_min'] - (int)$x['usado_min']; if ($m > 0) $mov[] = [$x, $m]; }
  $meu = (int)$c['pacote_min'] - autosDaVisitaMin($c) - (int)$c['usado_min'] + array_sum(array_map(fn($y) => $y[1], $mov));
  $D = array_values(array_filter($L, fn($x) => !empty($x['entrou']) && empty($x['festa_id'])));
  if ($meu < $p && $D) foreach ($D as $x) { $sobra = (int)$x['pacote_min'] - autosDaVisitaMin($x) - (int)$x['usado_min'] - cobrar(minutosDaVisita($x, $fim)); $m = intdiv(max(0, $sobra), (count($D) + 1) * $p) * $p; if ($m > 0) $mov[] = [$x, $m]; }
  if (!$mov) return null;
  $db = db(); $t = 0; $de = []; $db->beginTransaction();
  try {
    foreach ($mov as [$x, $m]) {
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([$m, (int)$x['id']]); $t += $m;
      $rd = registrar('alt', (int)$x['id'], (string)$x['nome'], 'Horas do CPF: ' . dur($m) . ' passaram para a entrada de ' . $c['nome']);
      $db->prepare("INSERT INTO fam_horas (quando, para, de, minutos, reg_de, tipo) VALUES (?, ?, ?, ?, ?, 'ent')")->execute([agora(), (int)$c['id'], (int)$x['id'], $m, $rd]);
      $de[] = ['id' => (int)$db->lastInsertId(), 'crianca' => (int)$x['id'], 'nome' => (string)$x['nome'], 'min' => $m]; }
    $db->prepare('UPDATE criancas SET pacote_min = pacote_min + ? WHERE id = ?')->execute([$t, (int)$c['id']]);
    $rp = registrar('alt', (int)$c['id'], (string)$c['nome'], 'Horas do CPF: ' . dur($t) . ' de ' . implode(' e ', array_map(fn($y) => $y['nome'], $de)) . ' (entrada; as horas são do CPF)');
    $db->prepare('UPDATE fam_horas SET reg_para = ? WHERE id IN (' . implode(',', array_map(fn($y) => (int)$y['id'], $de)) . ')')->execute([$rp]);
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
  return ['min' => $t, 'de' => $de];
}
/* 2.11: a criança que recebeu (ou deu) horas do CPF está no pacote enquanto tem horas guardadas (as que vieram não são uma venda dela) */
function planoPelasHoras(array $c): ?string {
  $q = db()->prepare('SELECT 1 FROM fam_horas WHERE desfeito = 0 AND (para = ? OR de = ?) LIMIT 1'); $q->execute([(int)$c['id'], (int)$c['id']]); if (!$q->fetchColumn()) return null;
  return (int)$c['pacote_min'] - autosDaVisitaMin($c) - (int)$c['usado_min'] > 0 ? 'pacote' : 'avulso';
}
function desfazerHorasDaFamilia(array $c, string $desde, string $tipo = 'sai'): int {   // o Desfazer da saída (2.18: e o da entrada, tipo 'ent'): as horas voltam para os irmãos
  $db = db(); $q = $db->prepare('SELECT * FROM fam_horas WHERE para = ? AND quando >= ? AND desfeito = 0 AND tipo = ?'); $q->execute([(int)$c['id'], $desde, $tipo]); $n = 0;
  foreach ($q->fetchAll() as $x) {
    $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$x['minutos'], (int)$c['id']]);
    $db->prepare('UPDATE criancas SET pacote_min = pacote_min + ? WHERE id = ?')->execute([(int)$x['minutos'], (int)$x['de']]);
    $db->prepare('UPDATE fam_horas SET desfeito = 1 WHERE id = ?')->execute([(int)$x['id']]);
    foreach (['reg_de', 'reg_para'] as $k) if (!empty($x[$k])) $db->prepare("DELETE FROM registros WHERE id = ? AND tipo = 'alt'")->execute([(int)$x[$k]]);
    $n++;
  }
  return $n;
}
function fazerSaida(array $c, string $origem = 'iDFace', ?string $quando = null, bool $avulso = false): array {   // $avulso (1.11): a visita acabou de ser vendida avulsa (venderVisitaAvulsa)
  $db = db(); $quando = $quando ?: agora();
  if (!empty($c['festa_id'])) return saidaDaFesta($c, $origem, $quando);   // 1.29: a visita da festa: sai sem debitar e sem cobrar
  $plano = planoDaCrianca($c);   // 1.15: antes de debitar: recreação avulsa, ou pacote (para a linha da saída dizer o que é o valor a receber)
  if (acertarCobrancas($c, $quando)) $c = crianca((int)$c['id']);   // o que a visita precisa até a hora da saída e ainda não tinha sido lançado nasce agora
  if (!$avulso && desfazerCobrancasSobrando($c, $quando)['n']) $c = crianca((int)$c['id']);   // 1.00: o que o cliente comprou durante a visita cobre o tempo: a cobrança por atraso que sobrou é desfeita
  $min = minutosDaVisita($c, strtotime($quando)); $deb = min(cobrar($min), restante($c)); $atraso = atrasoDaVisita((int)$c['id'], (string)$c['entrou']);   // nunca debita mais do que há (a cobrança por atraso já cobriu o que faltava)
  $db->prepare('INSERT INTO visitas (crianca_id, entrou, saiu, debitado_min, origem, unidade_id, vendedora) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$c['id'], $c['entrou'], $quando, $deb, $origem, $c['unidade_id'] ?? null, (string)($c['vend_abriu'] ?? '')]);   // 1.06: a visita fica com a unidade em que a criança entrou
  $visita = (int)$db->lastInsertId();
  $sap = isset($c['sapateira']) && $c['sapateira'] !== null ? (int)$c['sapateira'] : 0;   // a sapateira fica livre na saída
  $db->prepare('UPDATE criancas SET entrou = NULL, usado_min = usado_min + ?, sapateira = NULL WHERE id = ?')->execute([$deb, $c['id']]);
  $c['usado_min'] += $deb; $c['entrou'] = null;
  $r = restante($c);
  if ($r < periodo() && regra()['bloqueioAuto'] === 'sim') bloquearAcesso($c, false);   // não sobra um período inteiro: o iDFace passa a recusar até venderem tempo
  // 1.18: na recreação avulsa nada é "debitado" (o cliente não comprou período): a linha diz a recreação que a visita somou e, se houver, o tempo que saiu do que ele já tinha (voucher)
  $proprias = max(0, $deb - (int)$atraso['n'] * periodo());
  $reg = registrar('sai', (int)$c['id'], $c['nome'], 'Ficou ' . dur(ficouMin((float)$min)) . ($plano === 'pacote' ? ' · ' . dur($deb) . ' debitados' : ($proprias > 0 ? ' · ' . dur($proprias) . ' do tempo que já tinha' : '')) . ($r > 0 ? ' · restam ' . hm($r) : '') . ($atraso['n'] ? ' · ' . textoAutos($atraso, $plano) . ' a receber' : '') . ($sap ? ' · sapato na sapateira ' . $sap : '') . ($origem === 'iDFace' || modoManual() ? '' : ' · saída à mão'), $quando);
  $msg = null;
  if (regra()['autoMsg'] === 'ambos' && $origem !== 'automática') {   // (1.90: a saída automática não manda mensagem)
    $texto = nomeMsg($c['nome']) . ' saiu às ' . date('H:i', strtotime($quando)) . '. Até a próxima! 🧸';
    $msg = mandarMensagem($c, $texto, true);
  }
  return ['registro' => $reg, 'visita' => $visita, 'debitado' => $deb, 'minutos' => (int)floor($min), 'atraso' => $atraso, 'plano' => $plano, 'mensagem' => $msg, 'sapateira' => $sap ?: null];
}
/* ---------- 1.90: a baixa automática ("o relógio interno") ----------
   Pedido do dono: uma criança ficou marcada no espaço de um dia para o outro e a tela mostrava 48 h. A conta da visita já parava na hora em
   que a loja fecha (no dia da entrada, nunca mais de 12 h); agora, virado o dia e passada essa hora, a visita sai sozinha, contada até lá,
   quando não há nada a receber (o pacote cobre o tempo e nada vendido ficou para a saída). Com algo a receber, a criança continua na lista
   com o relógio parado (a tela diz "sem saída"), para a equipe dar a saída e receber: a conta é a mesma, só até a loja fechar. */
function baixaAutomatica(): int {
  $db = db(); $n = 0;
  $q = $db->prepare('SELECT * FROM criancas WHERE entrou IS NOT NULL AND ativo = 1 AND entrou < ?'); $q->execute([date('Y-m-d') . ' 00:00:00']);
  foreach ($q->fetchAll() as $c) {
    if (ehExemplo((int)$c['id'])) continue; $teto = tetoDaVisita($c); if (agoraTs() <= $teto) continue;
    if (empty($c['festa_id'])) {   // (a visita da festa não cobra: sai sempre)
      if (pendentesDaVisita($c)) continue;   // vendido para pagar na saída
      $u = $db->prepare('SELECT COUNT(*) FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = vendas.id)'); $u->execute([(int)$c['id'], (string)$c['entrou']]);
      if ((int)$u->fetchColumn() > 0) continue;   // a cobrança do tempo (recreação avulsa, além do pacote) a receber
      $cobra = servicoTempo() && (string)$c['entrou'] >= (string)cfg('cobranca_desde', '');
      if ($cobra && (int)$c['usado_min'] + cobrar(minutosDaVisita($c, $teto)) > (int)$c['pacote_min']) continue;   // o pacote não cobre o tempo até a loja fechar
    }
    if (!metaMarca('baixa:' . (int)$c['id'] . ':' . (string)$c['entrou'], date('Y-m-d H:i:s'))) continue;   // (um aparelho por vez: o primeiro que chega dá a baixa)
    $c = crianca((int)$c['id']); if (!$c || !$c['entrou']) continue;
    $quando = date('Y-m-d H:i:s', $teto); $r = fazerSaida($c, 'automática', $quando);
    if (!empty($r['registro'])) $db->prepare('UPDATE registros SET texto = texto || ? WHERE id = ?')->execute([' · saída automática: ninguém deu a saída; o tempo contou até ' . date('H:i', $teto) . ' de ' . date('d/m', $teto) . ' (a loja fecha)', (int)$r['registro']]);
    $n++;
  }
  return $n;
}

/* ---------- reabrir a visita: a criança saiu e voltou logo em seguida ---------- */
// Até regra.reabreMin minutos depois da saída, a equipe reabre: a saída é desfeita e o tempo continua contando desde a entrada,
// como se a criança não tivesse saído (o que foi debitado volta para o saldo e a conta é refeita na próxima saída).
// Fica anotado em "Hoje na recepção", com quem reabriu. Passou do prazo, é uma entrada nova.
function ultimaVisita(int $criancaId): ?array { $q = db()->prepare('SELECT * FROM visitas WHERE crianca_id = ? ORDER BY saiu DESC, id DESC LIMIT 1'); $q->execute([$criancaId]); return $q->fetch() ?: null; }
function reabrirVisita(array $c, array $u): array {
  $db = db(); $lim = max(0, (int)(regra()['reabreMin'] ?? 30));
  if ($c['entrou']) falha($c['nome'] . ' já está no espaço.');
  if ($lim <= 0) falha('Reabrir a visita está desligado em Ajustes.');
  $v = ultimaVisita((int)$c['id']); if (!$v) falha($c['nome'] . ' ainda não tem visita para reabrir.');
  $fora = max(0, agoraTs() - strtotime((string)$v['saiu']));
  $foraPrazo = $fora > $lim * 60 + 10; $gestor = ($u['papel'] ?? '') === 'gestor';   // 1.57: depois do prazo, só o gestor reabre (no mesmo dia), e fica anotado
  if ($foraPrazo && (!$gestor || substr((string)$v['saiu'], 0, 10) !== date('Y-m-d'))) falha('Já passou de ' . $lim . ' min da saída de ' . $c['nome'] . ' (saiu às ' . substr((string)$v['saiu'], 11, 5) . '). Agora é uma entrada nova' . ($gestor ? ' (a saída foi em outro dia).' : '. Depois do prazo, só o gestor reabre.'));   // 10 s de folga para o pedido que já estava a caminho
  $q = $db->prepare("SELECT 1 FROM cancelamentos WHERE crianca_id = ? AND entrou >= ? AND tipo = ''"); $q->execute([$c['id'], $v['saiu']]);   // uma entrada feita depois dessa saída e cancelada
  if ($q->fetchColumn()) falha('Depois dessa saída houve uma entrada cancelada. Faça uma entrada nova.');
  // a sapateira que a criança tinha na saída volta para ela, se ainda estiver livre
  $q = $db->prepare("SELECT id, texto FROM registros WHERE crianca_id = ? AND tipo = 'sai' AND quando = ? ORDER BY id DESC LIMIT 1"); $q->execute([$c['id'], $v['saiu']]); $linha = $q->fetch() ?: null;
  $sap = $linha && preg_match('/sapateira (\d+)/u', (string)$linha['texto'], $m) ? (int)$m[1] : 0;
  if ($sap && ($sap > (int)regra()['sapateiras'] || donoSapateira($sap, (int)$c['id']))) $sap = 0;
  $agora = agora(); $min = intdiv($fora, 60);
  $db->beginTransaction();
  try {
    $db->prepare('UPDATE criancas SET entrou = ?, usado_min = MAX(0, usado_min - ?), sapateira = ?, festa_id = ? WHERE id = ?')->execute([$v['entrou'], (int)$v['debitado_min'], $sap ?: null, $v['festa_id'] ?? null, $c['id']]);   // 1.29: a visita da festa volta a ser da festa
    $db->prepare('DELETE FROM visitas WHERE id = ?')->execute([$v['id']]);
    if ($linha) $db->prepare('UPDATE registros SET texto = texto || ? WHERE id = ?')->execute([' · visita reaberta às ' . substr($agora, 11, 5), $linha['id']]);
    $reg = registrar('reab', (int)$c['id'], $c['nome'], 'Visita reaberta: a saída das ' . substr((string)$v['saiu'], 11, 5) . ' foi desfeita (' . ($min < 1 ? 'menos de 1 min' : $min . ' min') . ' fora) · o tempo continua desde a entrada das ' . substr((string)$v['entrou'], 11, 5) . ' · por ' . primeiroNome($u['nome']) . ($foraPrazo ? ' · REABERTA FORA DO PRAZO (' . $lim . ' MIN), PELO GESTOR' : ''), $agora);
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
  $c = crianca((int)$c['id']);
  if (restante($c) >= periodo() && isset($c['liberado']) && !(int)$c['liberado']) bloquearAcesso($c, true);   // a saída tinha bloqueado o acesso por falta de saldo: com o tempo de volta, libera
  $novas = acertarCobrancas($c);   // o tempo fora também conta: se passou do tempo comprado mais a tolerância, a cobrança por atraso nasce agora
  return ['registro' => $reg, 'entrou' => $v['entrou'], 'saiu' => $v['saiu'], 'fora_min' => $min, 'sapateira' => $sap ?: null, 'cobrancas' => count($novas)];
}
// A supervisão corrige a hora de uma saída (a criança saiu antes e a saída só foi marcada depois): o tempo debitado é refeito
// e as cobranças por atraso que passaram a sobrar são desfeitas. Sempre com o motivo, que fica em "Hoje na recepção".
function corrigirSaida(int $visitaId, string $novaSaida, array $u, string $motivo): array {
  $db = db(); $q = $db->prepare('SELECT * FROM visitas WHERE id = ?'); $q->execute([$visitaId]); $v = $q->fetch(); if (!$v) falha('Visita não encontrada.', 404);
  $c = crianca((int)$v['crianca_id']); if (!$c) falha('Criança não encontrada.', 404);
  $ent = strtotime((string)$v['entrou']); $sai0 = strtotime((string)$v['saiu']); $sai1 = strtotime($novaSaida);
  if ($sai1 !== false && $sai1 < $ent && $ent - $sai1 < 60) $sai1 = $ent;   // a mesma hora e minuto da entrada
  if ($sai1 === false || $sai1 < $ent || $sai1 >= $sai0) falha('A hora corrigida precisa ficar entre a entrada (' . substr((string)$v['entrou'], 11, 5) . ') e a saída marcada (' . substr((string)$v['saiu'], 11, 5) . ').');
  $p = periodo(); $deb0 = (int)$v['debitado_min']; $deb1 = min($deb0, cobrar(($sai1 - $ent) / 60)); $dif = $deb0 - $deb1;
  $q = $db->prepare('SELECT * FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND quando <= ? ORDER BY id DESC'); $q->execute([$c['id'], $v['entrou'], date('Y-m-d H:i:s', $sai0 + 5)]);
  $autos = $q->fetchAll(); $tirar = array_slice($autos, 0, min(count($autos), intdiv($dif, $p))); $valor = 0.0; $nova = date('Y-m-d H:i:s', $sai1);
  $devolver = pagoPorForma(array_map(fn($x) => (int)$x['id'], $tirar));   // 1.45 (auditoria): o que o cliente já tinha pago por essas cobranças volta para ele
  $db->beginTransaction();
  try {
    estornarPagosAntes($tirar);   // (o que foi pago em outro dia entra hoje em "Devolvido hoje" no caixa)
    $db->prepare('UPDATE visitas SET saiu = ?, debitado_min = ? WHERE id = ?')->execute([$nova, $deb1, $v['id']]);
    $db->prepare("UPDATE registros SET texto = texto || ' · corrigida' WHERE crianca_id = ? AND tipo = 'sai' AND quando = ? AND texto NOT LIKE '% · corrigida'")->execute([$c['id'], $v['saiu']]);   // a linha da saída antiga fica marcada
    $db->prepare('UPDATE criancas SET usado_min = MAX(0, usado_min - ?) WHERE id = ?')->execute([$dif, $c['id']]);
    foreach ($tirar as $x) {
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$x['minutos'], $c['id']]);
      $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([$x['id']]); $valor += (float)$x['valor'];
      if (!empty($x['registro'])) $db->prepare("UPDATE registros SET texto = texto || ' · desfeita' WHERE id = ? AND crianca_id = ? AND texto NOT LIKE '% · desfeita'")->execute([(int)$x['registro'], $c['id']]);
    }
    $reg = registrar('corr', (int)$c['id'], $c['nome'], 'Saída corrigida de ' . substr((string)$v['saiu'], 11, 5) . ' para ' . substr($nova, 11, 5) . ' · ' . dur($deb1) . ' debitados' . ($dif > 0 ? ' (eram ' . dur($deb0) . ')' : '')
      . ($tirar ? ' · ' . (count($tirar) > 1 ? count($tirar) . ' cobranças de recreação desfeitas' : 'cobrança de recreação desfeita') . ': ' . dinheiro($valor) : '') . ($devolver ? ' · devolver ao cliente: ' . textoPagamento($devolver, true) : '') . ' · Motivo: ' . $motivo . ' · por ' . primeiroNome($u['nome']));
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
  $c = crianca((int)$c['id']);
  if (!$c['entrou'] && restante($c) >= $p && isset($c['liberado']) && !(int)$c['liberado'] && regra()['bloqueioAuto'] === 'sim') bloquearAcesso($c, true);   // voltou a ter um período guardado
  return ['registro' => $reg, 'debitado' => $deb1, 'antes' => $deb0, 'desfeitas' => count($tirar), 'valor' => $valor, 'saiu' => $nova, 'devolver' => $devolver ? textoPagamento($devolver, true) : ''];
}
// a chavinha "acesso no iDFace": liberada, o aparelho autoriza o rosto e as horas correm; bloqueada, ele recusa
function donoSapateira(int $n, int $menosId = 0): ?array { $q = db()->prepare('SELECT id, nome FROM criancas WHERE ativo = 1 AND sapateira = ? AND id != ?'); $q->execute([$n, $menosId]); return $q->fetch() ?: null; }
function bloquearAcesso(array $c, bool $liberar): void {
  db()->prepare('UPDATE criancas SET liberado = ? WHERE id = ?')->execute([$liberar ? 1 : 0, $c['id']]);
  if ($c['idface_id'] === null) return;
  if ($liberar) { apagarCfg('desregra_' . $c['id']); apagarCfg('regra_ok_' . $c['id']); gravarCfg('regra_' . $c['id'], (int)$c['idface_id']); }
  else { apagarCfg('regra_' . $c['id']); gravarCfg('desregra_' . $c['id'], (int)$c['idface_id']); }
}
// $min: minutos de brincar que a venda põe no saldo da criança (0 = nenhum). $servico: o nome do serviço vendido (Recreação, Carrinho fusquinha).
// $itens: o detalhe. Num serviço, a duração ou a observação; num pacote antigo, o que veio junto; sem serviço e sem minutos, o próprio produto.
// $auto: a venda foi gerada pelo sistema (cobrança por atraso); $textoAuto é a linha que vai para "Hoje na recepção"
// $mais: ['voucher' => 'sim', 'pcd' => 1, 'cheio' => valor de tabela] quando a venda saiu por voucher ou com o desconto PCD (as vendas antigas guardam o código do voucher no lugar do 'sim')
function fazerVenda(array $c, int $min, float $valor, ?int $por, string $itens = '', string $servico = '', bool $auto = false, string $textoAuto = '', array $mais = []): array {
  $db = db(); $itens = mb_substr(trim($itens), 0, 140); $servico = mb_substr(trim($servico), 0, 60); $min = max(0, $min);
  if ($min > 0) {
    $db->prepare('UPDATE criancas SET pacote_min = pacote_min + ? WHERE id = ?')->execute([$min, $c['id']]);
    if (isset($c['liberado']) && !(int)$c['liberado']) bloquearAcesso($c, true);   // pagou o tempo: o acesso volta
  }
  $prod = (int)($mais['produto'] ?? 0); $qtd = $prod > 0 ? max(1, (int)($mais['qtd'] ?? 1)) : 0;   // venda de produto: qual e quantos (o estoque acompanha)
  $alug = $prod ? '' : mb_substr((string)($mais['alug'] ?? ''), 0, 40); if ($alug !== '') $qtd = max(1, (int)($mais['qtd'] ?? 1));   // o que sai e volta: qual serviço e quantos saíram
  $daVisita = !empty($c['entrou']) && isset($c['unidade_id']) && $c['unidade_id'] !== null ? (int)$c['unidade_id'] : null;
  $uni = $auto ? $daVisita : (unidadeDoPedido() ?? $daVisita);   // 1.06: a unidade do aparelho que vendeu; a cobrança por atraso (que nasce sozinha) fica com a da visita
  $db->prepare('INSERT INTO vendas (crianca_id, horas, minutos, valor, quando, por, itens, servico, auto, voucher, pcd, cheio, produto_id, qtd, alug, alug_min, unidade_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([$c['id'], round($min / 60, 2), $min, $valor, agora(), $por, $itens, $servico, $auto ? 1 : 0, (string)($mais['voucher'] ?? ''), empty($mais['pcd']) ? 0 : 1, $mais['cheio'] ?? null, $prod ?: null, $qtd, $alug, $alug !== '' ? max(0, (int)($mais['alug_min'] ?? 0)) : 0, $uni]);
  $venda = (int)$db->lastInsertId();
  if ($prod) mexerEstoque($prod, $qtd, -1);
  $dinheiro = 'R$ ' . number_format($valor, fmod($valor, 1) ? 2 : 0, ',', '.');
  if ($servico !== '') $texto = $servico . ($min > 0 ? ' · ' . dur($min) : '') . ($itens !== '' ? ' · ' . $itens : '') . ' · ' . $dinheiro;
  else $texto = $min > 0 ? nomePacoteVenda($min, $itens) . ' vendido · ' . $dinheiro : $itens . ' · ' . $dinheiro;
  if (!$auto && trim((string)($mais['texto'] ?? '')) !== '') $texto = mb_substr(trim((string)$mais['texto']), 0, 300);   // 1.20: a linha do dia já vem escrita (a saída do fusquinha, que não tem valor na hora)
  if (!$auto && !empty($mais['registro'])) { $db->prepare('UPDATE vendas SET registro = ? WHERE id = ?')->execute([(int)$mais['registro'], $venda]); return ['venda' => $venda, 'registro' => (int)$mais['registro']]; }   // 1.22: mais um do mesmo (o 2º fusquinha da mesma saída): a linha do dia é a do primeiro
  if ($auto && !empty($mais['quieto'])) return ['venda' => $venda, 'registro' => 0];   // 1.15: o período da recreação avulsa não vira linha do dia (a linha da saída diz o total)
  $reg = $auto ? registrar('cobr', (int)$c['id'], $c['nome'], $textoAuto !== '' ? $textoAuto : $texto, null, $uni) : registrar('venda', (int)$c['id'], $c['nome'], $texto, null, $uni);
  $db->prepare('UPDATE vendas SET registro = ? WHERE id = ?')->execute([$reg, $venda]);
  return ['venda' => $venda, 'registro' => $reg];
}
const SERV_IMPORTADO = 'PACOTE DO SISTEMA ANTERIOR';   // 1.01: as horas que vieram na importação entram como um pacote de valor zero com este nome
function dinheiro(float $v): string { return 'R$ ' . number_format($v, fmod($v, 1) ? 2 : 0, ',', '.'); }
/* ---------- voucher do shopping: 30 min de recreação grátis, um por visita, sem código ---------- */
// voucherAberto: a criança já tem um voucher de hoje sem nenhuma saída depois dele (o desta visita, ou um guardado para a próxima entrada)
function voucherAberto(array $c): bool {
  $q = db()->prepare("SELECT 1 FROM vendas v WHERE v.crianca_id = ? AND v.voucher != '' AND v.quando >= ? AND v.quando > COALESCE((SELECT MAX(x.saiu) FROM visitas x WHERE x.crianca_id = v.crianca_id), '') LIMIT 1");
  $q->execute([$c['id'], date('Y-m-d') . ' 00:00:00']); return (bool)$q->fetchColumn();
}
function voucherJaTem(array $c): string { return $c['nome'] . ($c['entrou'] ? ' já usou o voucher nesta visita.' : ' já tem um voucher de hoje, ainda sem usar.') . ' O voucher é um só por visita.'; }
// Usa o voucher. Se a criança está no espaço e a visita já tem cobrança por atraso a receber, ele cobre a mais nova: a cobrança sai e o período
// do voucher entra no lugar (o saldo fica igual, o valor a receber diminui). Sem cobrança pendente, entra como um período grátis no saldo:
// passou dele (mais a tolerância), o tempo a mais é cobrado normalmente, ou sai do pacote de quem tem.
// Devolve a venda, o registro, os minutos que entraram no saldo, o que cobriu e quantos vouchers a criança usou hoje (o 2º fica marcado).
function usarVoucher(array $c, array $u): array {
  $st = servicoTempo(); if (!$st) falha('Não há serviço de tempo de recreação para usar o voucher.');
  if (voucherAberto($c)) falha(voucherJaTem($c));
  $db = db(); $p = periodo(); $cobriu = null;
  $q = $db->prepare("SELECT COUNT(*) FROM vendas WHERE crianca_id = ? AND voucher != '' AND quando >= ?"); $q->execute([$c['id'], date('Y-m-d') . ' 00:00:00']); $n = (int)$q->fetchColumn() + 1;
  $db->exec('BEGIN IMMEDIATE');   // junto com a cobrança por atraso: uma coisa de cada vez
  try {
    $c = crianca((int)$c['id']); $auto = null;
    if ($c['entrou']) { $qa = $db->prepare('SELECT * FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = vendas.id) ORDER BY id DESC LIMIT 1'); $qa->execute([$c['id'], $c['entrou']]); $auto = $qa->fetch() ?: null; }
    $x = fazerVenda($c, $p, 0.0, (int)$u['id'], 'voucher' . ($n > 1 ? ' (' . $n . 'º de hoje)' : '') . ($auto ? ' · cobriu ' . dur((int)$auto['minutos']) . ' de recreação (' . dinheiro((float)$auto['valor']) . ')' : ''), (string)$st['n'], false, '', ['voucher' => 'sim', 'cheio' => (float)$st['v']]);
    if ($auto) {   // primeiro entra o período do voucher, depois sai a cobrança: o saldo nunca fica faltando no meio
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$auto['minutos'], $c['id']]);
      $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([$auto['id']]);
      if (!empty($auto['registro'])) $db->prepare("UPDATE registros SET texto = texto || ' · coberta pelo voucher' WHERE id = ? AND crianca_id = ? AND texto NOT LIKE '% · coberta pelo voucher'")->execute([(int)$auto['registro'], $c['id']]);
      $cobriu = ['valor' => (float)$auto['valor'], 'minutos' => (int)$auto['minutos']];
    }
    $db->exec('COMMIT');
  } catch (Throwable $e) { try { $db->exec('ROLLBACK'); } catch (Throwable $e2) { } throw $e; }
  return ['venda' => $x['venda'], 'registro' => $x['registro'], 'minutos' => $auto ? 0 : $p, 'cobriu' => $cobriu, 'n' => $n];
}
/* 1.70: "no comprovante do cliente só sai o pacote: o produto é como um brinde que vem dentro do pacote". O tipo de cada venda no papel
   (o cupom da saída e o cupom fiscal), o nome dela sem o produto que vem com o pacote, e o produto vendido com um pacote dentro da linha dele */
function tipoNoPapel(array $v): string {
  if ((int)($v['produto_id'] ?? 0) > 0) return 'produto';
  return (int)($v['minutos'] ?? 0) > 0 && empty($v['auto']) && (string)($v['alug'] ?? '') === '' && (string)($v['voucher'] ?? '') === '' && empty($v['festa_id']) ? 'pacote' : '';
}
function nomeNoPapel(array $v): string {
  if (tipoNoPapel($v) !== 'pacote') return nomeDaVenda($v);
  $s = trim((string)($v['servico'] ?? '')); $min = (int)$v['minutos'];
  return $s !== '' ? $s . ' · ' . dur($min) : nomePacoteVenda($min, '');
}
function soPacoteNoPapel(array $itens): array {
  $pac = null; $soma = 0.0; foreach ($itens as $i => $x) { if (($x['t'] ?? '') === 'pacote' && $pac === null) $pac = $i; if (($x['t'] ?? '') === 'produto') $soma += (float)$x['v']; }
  if ($pac === null || $soma <= 0.004) return $itens;
  $o = []; foreach ($itens as $i => $x) { if (($x['t'] ?? '') === 'produto') continue; if ($i === $pac) $x['v'] = round((float)$x['v'] + $soma, 2); $o[] = $x; }
  return $o;
}
function nomeDaVenda(array $v): string { // "Recreação · 30 min", "Carrinho de bebê · das 10h às 22h", "Pacote de 5h"
  $min = (int)($v['minutos'] ?? 0); $s = trim((string)($v['servico'] ?? '')); $it = trim((string)($v['itens'] ?? ''));
  if ($s !== '') return $s . ($min > 0 ? ' · ' . dur($min) : '') . ($it !== '' ? ' · ' . $it : '');
  return $min > 0 ? nomePacoteVenda($min, $it) : ($it !== '' ? $it : 'Produto');
}

/* ---------- produtos da loja: nome, valor e estoque ---------- */
// estoque NULL = a loja não controla o estoque desse produto. A venda nunca é barrada pelo estoque: ele pode ficar negativo, e a tela mostra em vermelho para a gestora acertar
function produto(int $id): ?array { $q = db()->prepare('SELECT * FROM produtos WHERE id = ?'); $q->execute([$id]); return $q->fetch() ?: null; }
function produtoPublico(array $p): array { return ['id' => (int)$p['id'], 'n' => (string)$p['nome'], 'v' => (float)$p['valor'], 'est' => $p['estoque'] === null ? null : (int)$p['estoque'], 'l' => (string)($p['lojas'] ?? 'play'), 'cat' => (string)($p['cat'] ?? '')]; }   // 2.00: a loja e a categoria
function lojasValidas(string $s): string { $o = []; foreach (preg_split('/[,\s]+/', strtolower($s)) as $l) if (in_array($l, ['play', 'ct'], true) && !in_array($l, $o, true)) $o[] = $l; sort($o); return implode(',', array_reverse($o)); }   /* 2.00: 'play', 'ct' ou 'play,ct' */
/* 2.00: o cliente da loja da Casa Tom (o balcão): a venda de produto da Casa Tom vai em nome dele, e o pagamento, o cupom, a comissão e as
   Finanças funcionam como sempre (pela unidade do aparelho). Não aparece nas listas. Nasce na primeira vez que um aparelho na Casa Tom fala com o servidor */
function balcaoCT(bool $criar = false): ?array {
  $db = db(); $id = (int)cfg('ct_balcao', 0); if ($id) { $c = crianca($id); if ($c && $c['ativo']) return $c; }
  if (!$criar) return null; $u = unidadeDoPedido(); if ($u === null) return null;
  $q = $db->prepare('SELECT nome FROM unidades WHERE id = ?'); $q->execute([$u]); if (!uniEhCasaTom((string)$q->fetchColumn())) return null;
  $db->prepare("INSERT INTO criancas (nome, resp, liberado, criado_em, lote) VALUES ('CLIENTE DA LOJA', 'CASA TOM', 1, ?, 'balcao')")->execute([agora()]);
  $id = (int)$db->lastInsertId(); gravarCfg('ct_balcao', $id); return crianca($id);
}
function produtos(): array { return array_map('produtoPublico', db()->query('SELECT * FROM produtos WHERE ativo = 1 ORDER BY nome COLLATE NOCASE, valor, id')->fetchAll()); }
// $sinal -1: a venda levou as unidades; +1: a venda foi desfeita (ou deixou de ser cobrada) e elas voltam
function mexerEstoque(int $produtoId, int $qtd, int $sinal): void {
  if ($produtoId > 0 && $qtd > 0) db()->prepare('UPDATE produtos SET estoque = estoque + ?, mudou_em = ? WHERE id = ? AND estoque IS NOT NULL')->execute([$sinal * $qtd, agora(), $produtoId]);
}
function devolverEstoque(array $venda): void { mexerEstoque((int)($venda['produto_id'] ?? 0), (int)($venda['qtd'] ?? 0), 1); }

/* ---------- 0.91: a observação do cliente (só a equipe vê) ---------- */
// A observação mudou: grava a nova, guarda a anterior (para ninguém perder o que estava escrito) e anota quem mudou e quando.
// A linha do dia não repete o texto: a tela só mostra a observação a quem toca no OBS
function marcarObs(array $c, string $nova, array $u): bool {
  $velha = (string)$c['obs']; if ($nova === $velha) return false; $quem = primeiroNome((string)$u['nome']);
  db()->prepare("UPDATE criancas SET obs = ?, obs_antes = CASE WHEN ? != '' THEN ? ELSE obs_antes END, obs_em = ?, obs_por = ? WHERE id = ?")->execute([$nova, $velha, $velha, agora(), $quem, (int)$c['id']]);
  registrar('obs', (int)$c['id'], (string)$c['nome'], ($velha === '' ? 'Observação anotada' : ($nova === '' ? 'Observação apagada · a anterior continua guardada' : 'Observação alterada · a anterior continua guardada')) . ' · por ' . $quem);
  return true;
}

/* ---------- 0.90: a forma de pagamento (Pix, dinheiro, crédito, débito), em até três formas ---------- */
// O que a tela manda: [{ f: 'pix', v: 200 }, { f: 'dinheiro', v: 100 }]. Devolve [forma => valor] (formas repetidas somam; valor zero não conta)
function lerPagamento($bruto): array {
  $o = [];
  foreach ((array)$bruto as $p) {
    if (!is_array($p)) continue; $f = (string)($p['f'] ?? ''); $v = round((float)($p['v'] ?? 0), 2);
    if (!isset(FORMAS_PAGAMENTO[$f])) falha('Forma de pagamento desconhecida.'); if ($v <= 0) continue;
    $o[$f] = round(($o[$f] ?? 0) + $v, 2);
  }
  if (count($o) > 3) falha('O pagamento pode ser dividido em até três formas.');
  return $o;
}
// "Pix" (uma forma só) ou "Pix R$ 200 + Dinheiro R$ 100 + Crédito R$ 380"
function textoPagamento(array $formas, bool $comValor = false): string {
  if (count($formas) === 1 && !$comValor) return FORMAS_PAGAMENTO[array_key_first($formas)] ?? (string)array_key_first($formas);
  $t = []; foreach ($formas as $f => $v) $t[] = (FORMAS_PAGAMENTO[$f] ?? $f) . ' ' . dinheiro((float)$v);
  return implode(' + ', $t);
}
// quanto já foi pago dessas vendas, por forma: [forma => valor]
function pagoPorForma(array $vendaIds): array {
  $ids = array_values(array_filter(array_map('intval', $vendaIds))); if (!$ids) return [];
  $o = []; foreach (db()->query('SELECT forma, SUM(valor) t FROM pagamentos WHERE venda_id IN (' . implode(',', $ids) . ') GROUP BY forma')->fetchAll() as $l) if ((float)$l['t'] > 0.004) $o[(string)$l['forma']] = round((float)$l['t'], 2);
  return $o;
}
// Reparte o pagamento pelas vendas, na ordem: cada venda fica com as formas que a pagaram (até o valor dela).
// Assim, se uma venda for desfeita ou deixar de ser cobrada, sai só o que era dela. Devolve o que de fato foi gravado: [forma => valor]
function gravarPagamento(array $vendaIds, array $formas, int $criancaId, ?int $por, ?string $quando = null): array {
  $db = db(); $quando = $quando ?: agora(); $gravado = [];
  $q = $db->prepare('SELECT v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) FROM vendas v WHERE v.id = ?');
  $ins = $db->prepare('INSERT INTO pagamentos (venda_id, crianca_id, forma, valor, quando, por, unidade_id) VALUES (?, ?, ?, ?, ?, ?, ?)'); $uni = unidadeDoPedido();   // 1.31: a unidade em que o dinheiro entrou
  foreach ($vendaIds as $vid) {
    $q->execute([(int)$vid]); $falta = $q->fetchColumn(); if ($falta === false) continue; $falta = round((float)$falta, 2);
    foreach ($formas as $f => $resta) {
      if ($falta < 0.005) break; if ($resta < 0.005) continue; $x = round(min($falta, $resta), 2);
      $ins->execute([(int)$vid, $criancaId, $f, $x, $quando, $por, $uni]); $formas[$f] = round($resta - $x, 2); $falta = round($falta - $x, 2); $gravado[$f] = round(($gravado[$f] ?? 0) + $x, 2);
    }
  }
  return $gravado;
}
/* ---------- 1.57: trocar a forma de pagamento ("o cliente disse crédito e pagou no Pix") ----------
   1.71: "as meninas podem trocar a forma de pagamento; depois, se precisarem, elas editam a forma de pagamento para bater o caixa": a equipe
   troca a qualquer hora, como o gestor (antes, só nos 2 minutos depois de pagar). A troca vale para o pagamento inteiro (todas as
   linhas daquela forma, gravadas no mesmo instante, das crianças do responsável): o caixa, as Finanças e o cupom fiscal acompanham. Fica
   o registro; a troca feita pela equipe vira um aviso para o gestor (na tela e no celular). */
const TROCA_PAG_SEG = 120;   // 1.71: só a reimpressão pela equipe continua com os 2 minutos
function trocarPagamento(array $in, array $u): array {
  /* 1.71: "às vezes o cliente desmembrou": em vez de uma forma nova (para), as partes ([{f, v}], duas ou mais formas com o valor de cada
     uma; a soma é o valor pago naquela forma). As linhas do pagamento são repartidas entre as partes (e divididas quando precisa) */
  $db = db(); $de = (string)($in['de'] ?? ''); $para = (string)($in['para'] ?? ''); $partes = isset($in['partes']) && is_array($in['partes']) ? $in['partes'] : null;
  if (!isset(FORMAS_PAGAMENTO[$de]) || ($partes === null && !isset(FORMAS_PAGAMENTO[$para]))) falha('Escolha a forma de pagamento.');
  if ($partes === null && $de === $para) falha('A forma nova é a mesma de agora.');
  if (!empty($in['pag'])) { $q = $db->prepare('SELECT * FROM pagamentos WHERE id = ? AND forma = ?'); $q->execute([(int)$in['pag'], $de]); }   /* 2.50: o pagamento escolhido no Ajustar caixa */
  elseif (!empty($in['venda'])) { $q = $db->prepare('SELECT * FROM pagamentos WHERE venda_id = ? AND forma = ? ORDER BY id DESC LIMIT 1'); $q->execute([(int)$in['venda'], $de]); }
  else { $c0 = crianca((int)($in['crianca'] ?? 0)); if (!$c0) falha('Cliente não encontrado.', 404); $f0 = array_merge([(int)$c0['id']], array_map(fn($x) => (int)$x['id'], irmaosDe($c0)));
    $q = $db->prepare('SELECT * FROM pagamentos WHERE crianca_id IN (' . implode(',', $f0) . ') AND forma = ? ORDER BY id DESC LIMIT 1'); $q->execute([$de]); }
  $p = $q->fetch(); $q->closeCursor(); if (!$p) falha('Não achei esse pagamento em ' . FORMAS_PAGAMENTO[$de] . '.', 404);
  $c = crianca((int)$p['crianca_id']); $fam = $c ? array_merge([(int)$c['id']], array_map(fn($x) => (int)$x['id'], irmaosDe($c))) : [(int)$p['crianca_id']];
  $gestor = ($u['papel'] ?? '') === 'gestor';
  $q = $db->prepare('SELECT * FROM pagamentos WHERE crianca_id IN (' . implode(',', $fam) . ') AND quando = ? AND forma = ? ORDER BY id'); $q->execute([(string)$p['quando'], $de]); $L = $q->fetchAll();
  $valor = round(array_sum(array_map(fn($x) => (float)$x['valor'], $L)), 2); $nomeC = (string)($c['nome'] ?? ''); $vc = (int)round($valor * 100);
  $pc = [];   // as partes, em centavos: [forma => valor]
  if ($partes === null) $pc[$para] = $vc;
  else { foreach ($partes as $x) { if (!is_array($x)) continue; $f = (string)($x['f'] ?? ''); $v = (int)round((float)str_replace(',', '.', (string)($x['v'] ?? 0)) * 100); if ($v <= 0) continue;
      if (!isset(FORMAS_PAGAMENTO[$f])) falha('Escolha a forma de pagamento.'); $pc[$f] = ($pc[$f] ?? 0) + $v; }
    if (!$pc) falha('Escreva o valor de cada forma.');
    if (array_sum($pc) !== $vc) falha('A soma das formas (' . dinheiro(array_sum($pc) / 100) . ') tem que dar ' . dinheiro($valor) . ', o que foi pago em ' . FORMAS_PAGAMENTO[$de] . '.');
    if (count($pc) === 1 && isset($pc[$de])) falha('A forma nova é a mesma de agora.'); }
  $novas = array_diff_key($pc, [$de => 1]); $para = (string)array_key_first($novas ?: $pc);   // a forma nova (a primeira diferente da antiga), para o aviso
  $txtPara = count($pc) > 1 ? implode(' + ', array_map(fn($f, $v) => FORMAS_PAGAMENTO[$f] . ' ' . dinheiro($v / 100), array_keys($pc), $pc)) : FORMAS_PAGAMENTO[$para];
  $fila = []; foreach ($pc as $f => $v) $fila[] = [$f, $v]; usort($fila, fn($a, $b) => ($b[0] === $de) <=> ($a[0] === $de));   // a parte que fica na forma antiga primeiro
  $db->beginTransaction();
  try {
    $up = $db->prepare('UPDATE pagamentos SET forma = ?, valor = ? WHERE id = ?'); $ins = $db->prepare('INSERT INTO pagamentos (venda_id, crianca_id, forma, valor, quando, por, cupom_id, unidade_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'); $k = 0;
    foreach ($L as $x) { $resta = (int)round((float)$x['valor'] * 100); $prim = true;
      while ($resta > 0 && $k < count($fila)) { $usa = min($resta, $fila[$k][1]);
        if ($prim) { $up->execute([$fila[$k][0], $usa / 100, (int)$x['id']]); $prim = false; }
        else $ins->execute([(int)$x['venda_id'], (int)$x['crianca_id'], $fila[$k][0], $usa / 100, (string)$x['quando'], $x['por'], $x['cupom_id'], $x['unidade_id'] ?? null]);
        $resta -= $usa; $fila[$k][1] -= $usa; if ($fila[$k][1] <= 0) $k++; } }
    foreach (array_unique(array_filter(array_map(fn($x) => (int)($x['cupom_id'] ?? 0), $L))) as $k) refazerCupom((int)$k);   // o cupom fiscal acompanha
    $reg = registrar('alt', (int)$p['crianca_id'], $nomeC, 'Troca de pagamento: ' . FORMAS_PAGAMENTO[$de] . ' ' . dinheiro($valor) . ' → ' . $txtPara . ' (pago às ' . substr((string)$p['quando'], 11, 5) . ') · por ' . maiusculo(primeiroNome((string)$u['nome'])));
    $lp = array_map(fn($f, $v) => ['f' => $f, 'v' => round($v / 100, 2)], array_keys($pc), $pc);
    $db->prepare('INSERT INTO trocas_pag (quando, crianca_id, nome, resp, de, para, valor, pago_em, por, por_nome, papel, unidade_id, partes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([agora(), (int)$p['crianca_id'], $nomeC, (string)($c['resp'] ?? ''), $de, $para, $valor, (string)$p['quando'], (int)$u['id'], (string)$u['nome'], (string)$u['papel'], unidadeDoPedido(), count($pc) > 1 ? json_encode($lp) : '']);
    $tid = (int)$db->lastInsertId(); $db->commit();
  } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  if (!$gestor) { require_once __DIR__ . '/push-lib.php'; pushDepois(function () use ($tid) { pushTroca($tid); }); }
  return ['valor' => $valor, 'de' => $de, 'para' => $para, 'partes' => $lp, 'registro' => $reg, 'pago_em' => (string)$p['quando']];
}
/* 2.39 (o dono: "às vezes as meninas podem colocar só uma vez e foi mais de uma ... entrar na venda que elas fizeram e alterar a quantidade
   de vezes"): ajustar as vezes no crédito de um pagamento já feito. Até parcLivre() (4x) qualquer um; de 5x a 12x, o gestor (ou a senha
   dele, digitada ali). Vale para o pagamento inteiro (as vendas da família pagas no crédito na mesma hora): é o que as Finanças contam */
function ajustarVezes(array $in, array $u): array {
  $db = db(); $n = (int)($in['parcelas'] ?? 0); if ($n < 1 || $n > 12) falha('Escolha de 1x a 12x.');
  $q = $db->prepare("SELECT * FROM pagamentos WHERE venda_id = ? AND forma = 'credito' ORDER BY id DESC LIMIT 1"); $q->execute([(int)($in['venda'] ?? 0)]); $p = $q->fetch(); $q->closeCursor();
  if (!$p) falha('Esta compra não foi no crédito.', 404);
  $lib = null; if ($n > parcLivre() && ($u['papel'] ?? '') !== 'gestor') { if (!array_key_exists('senha', $in)) falha('De ' . (parcLivre() + 1) . 'x a 12x, só com a senha do gestor.', 403); $lib = autorizadorPelaSenha('parcelas', (string)$in['senha'], $u); }
  $c = crianca((int)$p['crianca_id']); $fam = $c ? array_merge([(int)$c['id']], array_map(fn($x) => (int)$x['id'], irmaosDe($c))) : [(int)$p['crianca_id']]; $mf = implode(',', array_map('intval', $fam));
  $q = $db->prepare("SELECT venda_id, valor FROM pagamentos WHERE crianca_id IN ($mf) AND quando = ? AND forma = 'credito'"); $q->execute([(string)$p['quando']]); $L = $q->fetchAll();
  $ids = array_values(array_unique(array_filter(array_map(fn($x) => (int)$x['venda_id'], $L)))) ?: [(int)$p['venda_id']]; $mv = implode(',', $ids);
  $valor = round(array_sum(array_map(fn($x) => (float)$x['valor'], $L)), 2);
  $de = (int)$db->query("SELECT MAX(parcelas) FROM vendas WHERE id IN ($mv)")->fetchColumn(); if ($de === $n) falha('Já está em ' . $n . 'x.');
  $db->prepare("UPDATE vendas SET parcelas = ? WHERE id IN ($mv)")->execute([$n]);
  $reg = registrar('alt', (int)$p['crianca_id'], (string)($c['nome'] ?? ''), 'Crédito ajustado: ' . ($de ? $de . 'x' : 'sem as vezes') . ' → ' . $n . 'x (' . dinheiro($valor) . ', pago em ' . date('d/m H:i', strtotime((string)$p['quando'])) . ')'
    . ($lib ? ' · liberado por ' . maiusculo(primeiroNome((string)$lib['nome'])) . ' com a senha' : '') . ' · por ' . maiusculo(primeiroNome((string)$u['nome'])));
  return ['de' => $de, 'para' => $n, 'valor' => $valor, 'vendas' => $ids, 'lib' => $lib ? (string)$lib['nome'] : '', 'registro' => $reg];
}
function trocaPublica(array $t): array {
  return ['id' => -(int)$t['id'], 'troca' => true, 'quando' => (string)$t['quando'], 'crianca' => $t['crianca_id'] === null ? null : (int)$t['crianca_id'], 'nome' => (string)$t['nome'], 'resp' => (string)$t['resp'],
    'de' => (string)$t['de'], 'para' => (string)$t['para'], 'partes' => json_decode((string)($t['partes'] ?? ''), true) ?: [], 'valor' => round((float)$t['valor'], 2), 'por' => (string)$t['por_nome'], 'papel' => (string)$t['papel'], 'uni' => $t['unidade_id'] === null ? null : (int)$t['unidade_id'],
    'motivo' => '', 'atendente' => '', 'entrou' => null, 'itens' => [], 'tipo' => 'troca', 'visto_em' => $t['visto_em'], 'visto_por' => (string)$t['visto_por']];
}
/* ---------- 1.04: desconto do gestor na tela de pagamento ---------- */
// Só o gestor dá desconto: a tela manda o valor em reais (e o percentual, quando foi digitado em %) e o motivo. O desconto sai do valor das
// vendas, em proporção ao que falta receber de cada uma, e fica guardado nelas (desc_valor, desc_motivo, desc_por), com uma linha no dia.
// O pagamento fecha com o total já descontado.
function lerDesconto($d, array $u, ?array $c = null, ?float $conta = null): ?array { // ['valor' => em reais, 'pct' => o percentual digitado (ou 0), 'motivo'], ou null se não há desconto
  if (!is_array($d)) return null; $v = round((float)($d['valor'] ?? 0), 2); if ($v <= 0) return null;
  if (($u['papel'] ?? '') !== 'gestor') { // 1.23: quem não é gestor manda a autorização (o pedido que o gestor liberou): vale o motivo que ele viu, até o valor liberado
    $p = autorizacaoDeDesconto((int)($d['autorizacao'] ?? 0), $u, $c, $v); $dd = json_decode((string)$p['dados'], true);
    // 1.45 (auditoria): liberado em %, vale a % da conta de agora: se a conta mudou depois do pedido (trocou o item), nunca passa daquela %
    $pl = round((float)(is_array($dd) ? ($dd['pct'] ?? 0) : 0), 2);
    if ($pl > 0 && $conta !== null && $conta > 0) { $teto = round($conta * $pl / 100, 2); if ($v > $teto + 0.015) falha('O desconto liberado foi de ' . rtrim(rtrim(number_format($pl, 2, ',', ''), '0'), ',') . '%: nesta conta (' . dinheiro($conta) . ') dá ' . dinheiro($teto) . '. Confira e confirme de novo.', 409); }
    return ['valor' => $v, 'pct' => round((float)(is_array($dd) ? ($dd['pct'] ?? 0) : 0), 2), 'motivo' => (string)$p['motivo'], 'aut' => (int)$p['id'], 'aut_por' => (int)$p['decidido_por'], 'aut_nome' => (string)$p['decidido_nome']];
  }
  $pct = round((float)($d['pct'] ?? 0), 2); if ($pct < 0 || $pct > 100) falha('O desconto não passa de 100%.');
  if ($pct > 0 && $conta !== null && $conta > 0 && abs($v - round($conta * $pct / 100, 2)) > 0.02) $pct = 0.0;   // 1.45 (auditoria): a % que não bate com o valor não vai para a linha do dia (vale o valor)
  $motivo = mb_substr(maiusculo((string)($d['motivo'] ?? '')), 0, 120); if (mb_strlen($motivo) < 3) falha('Escreva o motivo do desconto.');
  return ['valor' => $v, 'pct' => $pct, 'motivo' => $motivo];
}
// reparte o desconto pelas vendas ([venda => quanto falta receber]) em proporção, sem passar do que falta em cada uma e fechando os centavos: [venda => desconto]
function repartirDesconto(float $total, array $faltas): array {
  $faltas = array_filter(array_map(fn($x) => round((float)$x, 2), $faltas), fn($x) => $x > 0.004); $base = round(array_sum($faltas), 2); $o = [];
  $alvo = round(min($total, $base), 2); if ($alvo <= 0) return $o; $resta = $alvo;
  foreach ($faltas as $k => $f) { $x = round(min($f, $resta, $alvo * $f / $base), 2); if ($x > 0) { $o[$k] = $x; $resta = round($resta - $x, 2); } }
  foreach ($faltas as $k => $f) { if ($resta < 0.005) break; $x = round(min($resta, $f - ($o[$k] ?? 0)), 2); if ($x > 0) { $o[$k] = round(($o[$k] ?? 0) + $x, 2); $resta = round($resta - $x, 2); } }   // os centavos que o arredondamento deixou
  return $o;
}
// aplica o desconto nas vendas: ['valor' => total descontado, 'vendas' => [venda => desconto], 'registro' => a linha do dia] (ou [] se não havia o que descontar)
function aplicarDesconto(array $d, array $faltas, array $c, array $u): array {
  $rep = repartirDesconto((float)$d['valor'], $faltas); if (!$rep) return [];
  $q = db()->prepare("UPDATE vendas SET valor = ROUND(valor - ?, 2), desc_valor = ROUND(desc_valor + ?, 2), desc_motivo = CASE WHEN desc_valor > 0.004 AND desc_motivo != '' THEN desc_motivo || ' + ' || ? ELSE ? END, desc_por = ? WHERE id = ?");   // quem já tinha um desconto fica com os dois motivos
  $deQuem = (int)($d['aut_por'] ?? 0) ?: (int)$u['id'];   // 1.23: com autorização, o desconto é de quem autorizou
  foreach ($rep as $vid => $x) $q->execute([$x, $x, $d['motivo'], $d['motivo'], $deQuem, (int)$vid]);
  $total = round(array_sum($rep), 2); $pct = $d['pct'] > 0 ? ' (' . rtrim(rtrim(number_format((float)$d['pct'], 2, ',', ''), '0'), ',') . '%)' : '';
  $reg = registrar('desc', (int)$c['id'], $c['nome'], 'Desconto · ' . dinheiro($total) . $pct . ' · Motivo: ' . $d['motivo'] . ' · por ' . primeiroNome((string)$u['nome']) . (!empty($d['aut_nome']) ? ' · autorizado por ' . primeiroNome((string)$d['aut_nome']) : ''));
  db()->prepare('UPDATE vendas SET desc_reg = ? WHERE id IN (' . implode(',', array_map('intval', array_keys($rep))) . ')')->execute([$reg]);   // 1.49
  return ['valor' => $total, 'vendas' => $rep, 'registro' => $reg, 'motivo' => $d['motivo']];
}
// o Desfazer: devolve às vendas o desconto que tinha sido dado ([venda => desconto]), tira o motivo dele e apaga a linha do dia
function tirarDesconto(int $criancaId, array $rep, int $registro, string $motivo = ''): void {
  $db = db(); $fim = ' + ' . $motivo; $q = $db->prepare("UPDATE vendas SET valor = ROUND(valor + ?, 2), desc_valor = ROUND(desc_valor - ?, 2),
    desc_motivo = CASE WHEN desc_valor - ? < 0.005 THEN '' WHEN ? != '' AND substr(desc_motivo, -length(?)) = ? THEN substr(desc_motivo, 1, length(desc_motivo) - length(?)) ELSE desc_motivo END,
    desc_por = CASE WHEN desc_valor - ? < 0.005 THEN NULL ELSE desc_por END, desc_reg = CASE WHEN desc_valor - ? < 0.005 THEN NULL ELSE desc_reg END WHERE id = ? AND crianca_id = ? AND desc_valor >= ? - 0.005");
  foreach ($rep as $vid => $x) { $x = round((float)$x, 2); if ($x > 0) $q->execute([$x, $x, $x, $motivo, $fim, $fim, $fim, $x, $x, (int)$vid, $criancaId, $x]); }
  if ($registro) $db->prepare("DELETE FROM registros WHERE id = ? AND crianca_id = ? AND tipo = 'desc'")->execute([$registro, $criancaId]);
}
/* ---------- 1.02: pago ou não pago ---------- */
// O cliente paga na entrada ou só na saída. A venda registrada sem pagamento fica a receber: a tela mostra NÃO PAGO, e o valor é recebido
// na saída da criança, na devolução do carrinho ou antes, pela ficha ("Receber"). A cobrança por atraso continua com a conta dela.
function faltaDaVenda(int $vendaId): float { // quanto falta receber de uma venda
  $q = db()->prepare('SELECT v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) FROM vendas v WHERE v.id = ?'); $q->execute([$vendaId]); $f = $q->fetchColumn();
  return $f === false ? 0.0 : max(0.0, round((float)$f, 2));
}
// as vendas desta visita (as que a equipe registrou, não as cobranças por atraso) que ainda têm valor a receber: cada uma com 'falta'
function pendentesDaVisita(array $c): array {
  $o = []; foreach (vendasDaVisita($c, true) as $v) { if (!empty($v['auto']) || (float)$v['valor'] <= 0) continue; $f = faltaDaVenda((int)$v['id']); if ($f > 0.004) $o[] = $v + ['falta' => $f]; }
  return $o;
}
// [criança => [['n' => o que foi vendido, 'v' => quanto falta receber], ...]] das vendas de hoje (desde a última saída ou entrada cancelada
// de cada uma), sem as cobranças por atraso. É a mesma conta de vendasDaVisita(), para todas as crianças de uma vez.
// 1.03: item por item, na ordem em que foram vendidos, para a tela da saída listar o que ainda não foi pago
// 1.31: a saída pelo rosto (iDFace) fecha a visita sem passar pelo pagamento: as cobranças que a visita somou sozinha (a recreação avulsa,
// o tempo além do pacote) e ficaram sem pagar continuam a receber, pela ficha (Receber). Só da última visita, de quem já saiu. $c = uma
// criança (ou null: todas, desde $piso)
function autosSemPagarDaSaida(?array $c, string $piso = ''): array {
  $sql = "SELECT v.id, v.crianca_id, v.servico, v.minutos, v.itens, v.valor, v.auto, v.voucher, v.produto_id, v.qtd, v.alug FROM vendas v JOIN criancas k ON k.id = v.crianca_id AND k.entrou IS NULL
    JOIN visitas x ON x.id = (SELECT MAX(y.id) FROM visitas y WHERE y.crianca_id = v.crianca_id)
    WHERE v.auto = 1 AND v.valor > 0 AND v.quando >= x.entrou AND v.quando <= x.saiu AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = v.id)";
  if ($c) { $q = db()->prepare($sql . ' AND v.crianca_id = ? ORDER BY v.id'); $q->execute([(int)$c['id']]); }
  else { $q = db()->prepare($sql . ' AND v.quando >= ? ORDER BY v.id'); $q->execute([$piso !== '' ? $piso : date('Y-m-d') . ' 00:00:00']); }
  return $q->fetchAll();
}
// 1.31: o Desfazer de um recebimento tira só os pagamentos daquela operação: os que vieram logo depois do marcador, no mesmo instante e
// pela mesma pessoa (um pagamento de outro aparelho, no meio, fica). Devolve quantos saíram
function apagarPagamentosDaOperacao(int $criancaId, int $pagDe, string $desde): int {
  $db = db(); $q = $db->prepare('SELECT quando, COALESCE(por, -1) por FROM pagamentos WHERE crianca_id = ? AND id > ? AND quando >= ? ORDER BY id LIMIT 1'); $q->execute([$criancaId, $pagDe, $desde]); $p = $q->fetch(); $q->closeCursor();
  if (!$p) return 0;
  $d = $db->prepare('DELETE FROM pagamentos WHERE crianca_id = ? AND id > ? AND quando = ? AND COALESCE(por, -1) = CAST(? AS INTEGER)'); $d->execute([$criancaId, $pagDe, (string)$p['quando'], (int)$p['por']]); return $d->rowCount();
}
// 1.39: o que o "Alterar pacote" da saída pode mudar: o pacote (as horas e o valor) ou o produto vendido nesta visita e ainda não pago.
// Não vale para a recreação, o carrinho, a festa, o voucher, as horas importadas, nem para a venda que já teve desconto
function altDaVenda(array $v): string {
  if (!empty($v['auto']) || !empty($v['festa_id']) || (string)($v['voucher'] ?? '') !== '' || (string)($v['alug'] ?? '') !== '' || (float)($v['desc_valor'] ?? 0) > 0.004 || (float)($v['valor'] ?? 0) <= 0.004 || (string)($v['servico'] ?? '') === SERV_IMPORTADO) return '';
  if (!empty($v['produto_id'])) return 'prod';
  if ((int)($v['minutos'] ?? 0) <= 0) return '';
  $s = mb_strtoupper(trim((string)($v['servico'] ?? ''))); if ($s !== '') foreach ((array)(regra()['servicos'] ?? []) as $x) if (mb_strtoupper(trim((string)($x['n'] ?? ''))) === $s) return '';
  return 'pac';
}
/* 1.91 (auditoria): o que ficou a receber (a venda "paga na saída" de uma entrada cancelada, por exemplo) não some à meia-noite: vale desde
   a última saída da criança. O piso de 60 dias só deixa a conta rápida (com anos de vendas no banco) */
const DIAS_A_RECEBER = 60;
function pisoAReceber(): string { return date('Y-m-d', strtotime('-' . DIAS_A_RECEBER . ' days')) . ' 00:00:00'; }
function pendItensDeTodos(): array {
  $db = db(); $hoje = date('Y-m-d') . ' 00:00:00';
  // o piso: nenhuma venda de antes de hoje (ou do dia em que entrou quem está há mais tempo no espaço) pode ser "desta visita".
  // Com ele a consulta anda só pelas vendas recentes, sem buscar criança por criança (com anos de vendas no banco, de 290 ms para 10 ms)
  $min = (string)$db->query('SELECT MIN(entrou) FROM criancas WHERE entrou IS NOT NULL')->fetchColumn();
  $piso = $min !== '' && substr($min, 0, 10) . ' 00:00:00' < $hoje ? substr($min, 0, 10) . ' 00:00:00' : $hoje;
  // 1.91: primeiro as vendas sem pagar inteiro (uma soma por venda), depois só delas a última saída (de 345 para 11 ms com 60 dias cheios)
  $q = $db->prepare("SELECT a.* FROM (SELECT v.id, v.crianca_id c, v.quando, v.servico, v.minutos, v.itens, v.valor, v.auto, v.produto_id, v.alug, v.festa_id, v.voucher, v.desc_valor, v.pcd, v.cheio, v.valor - COALESCE(SUM(p.valor), 0) f
      FROM vendas v JOIN criancas k0 ON k0.id = v.crianca_id LEFT JOIN pagamentos p ON p.venda_id = v.id WHERE v.quando >= ? AND v.auto = 0 AND v.valor > 0 AND v.servico != ? GROUP BY v.id) a
    WHERE a.f > 0.004 AND a.quando >= COALESCE((SELECT MAX(x.saiu) FROM visitas x WHERE x.crianca_id = a.c), '') ORDER BY a.id");   // 1.21: só a saída fecha a conta do que há para receber (a entrada cancelada, não)
  $q->execute([min($piso, pisoAReceber()), SERV_IMPORTADO]); $o = [];
  foreach ($q->fetchAll() as $l) $o[(int)$l['c']][] = ['n' => nomeDaVenda($l), 'v' => round((float)$l['f'], 2), 'id' => (int)$l['id'], 'alt' => (float)$l['f'] >= (float)$l['valor'] - 0.004 ? altDaVenda($l) : '', 'h' => round((int)$l['minutos'] / 60, 2), 't' => tipoNoPapel($l), 'pn' => nomeNoPapel($l)] + (!empty($l['pcd']) && (float)$l['f'] >= (float)$l['valor'] - 0.004 && (float)$l['cheio'] - (float)$l['desc_valor'] > (float)$l['valor'] + 0.004 ? ['ch' => round((float)$l['cheio'] - (float)$l['desc_valor'], 2)] : []);   /* 2.55: ch = o valor cheio da venda PCD (o cupom mostra o desconto) */   // 1.39: alt = o "Alterar pacote" da saída vale para ela ('pac' ou 'prod')   // 1.25: id = a venda   // juntar por criança fica aqui: agrupar no SQL faria a consulta andar pelo índice da criança
  foreach (autosSemPagarDaSaida(null, $piso) as $l) $o[(int)$l['crianca_id']][] = ['n' => nomeDaVenda($l), 'v' => round((float)$l['valor'], 2), 'id' => (int)$l['id']];   // 1.31: a saída pelo rosto que não recebeu
  return $o;
}
// o que entrou por cada forma desde $desde (a tela mostra em Hoje na recepção)
function pagamentosDesde(string $desde): array {
  $o = array_fill_keys(array_keys(FORMAS_PAGAMENTO), 0.0); $q = db()->prepare('SELECT forma, SUM(valor) t FROM pagamentos WHERE quando > ? GROUP BY forma'); $q->execute([$desde]);
  foreach ($q->fetchAll() as $l) $o[(string)$l['forma']] = round((float)$l['t'], 2);
  return $o;
}

/* ---------- 1.25: o dia de cada unidade (o painel da gestora) e os finalizados do dia ---------- */
// O dia é o de calendário (desde a meia-noite), e não as últimas 24 h de "Hoje na recepção". Faturado = o que foi vendido hoje (já com o
// desconto); recebido = os pagamentos de hoje, por forma. A unidade é a da venda (o aparelho que vendeu; a recreação avulsa fica com a da
// entrada); o pagamento vai com a venda dele. Sem unidade (o iDFace, os exemplos, o que é de antes da 1.06) vai à parte (uni 0).
/* 1.87: as metas do mês, por unidade. Pedido do dono: "pego o faturamento do mês do ano passado, jogo 15, 20, 30%, e dá a meta do mês (por
   exemplo, Anália Franco 410 mil). Divido pelos dias: no fim de semana o movimento é maior, então a meta é mais alta; durante a semana eu
   equilibro. Se num dia não bateram, o que passou no outro dia cobre: o que importa é entregar o mês cheio."
   - O gestor coloca, em Ajustes, a meta do mês de cada unidade (direto, ou o mesmo mês do ano passado + a %). O sistema divide pelos dias:
     sábado, domingo e feriado valem mais (o peso, 2× um dia de semana se ninguém mudar); o gestor pode fixar o valor de qualquer dia e os
     outros dias se ajustam sozinhos para a soma continuar sendo a do mês (config metas: { u: { unidade: { 'AAAA-MM': plano } } }).
   - A meta do dia de cada unidade é batida quando o faturado do dia dela (o vendido desde a meia-noite, já com o desconto, só a unidade)
     chega à meta daquele dia; a do mês, quando o faturado do mês chega à do mês. Cada uma fica guardada uma vez (uma linha da config por
     unidade, dia e valor, gravada só por quem chegou primeiro: é também quem manda o aviso para o celular dos gestores).
   - O saldo do mês: o que a unidade fez até ontem menos as metas dos dias até ontem (na frente ou atrás). O que faltou num dia, o que passou
     no outro cobre.
   - Os dois alertas ("quando faltar R$ 500", config meta_alertas) valem para a meta do dia de cada unidade. */
const META_FERIADOS = ['01-01' => 'Ano-novo', '01-25' => 'Aniversário de SP', '04-21' => 'Tiradentes', '05-01' => 'Dia do Trabalho', '07-09' => 'Revolução de 32',
  '09-07' => 'Independência', '10-12' => 'N. Sra. Aparecida', '11-02' => 'Finados', '11-15' => 'República', '11-20' => 'Consciência Negra', '12-25' => 'Natal'];
const META_MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
function metaPascoa(int $a): array {   /* o domingo de Páscoa (o cálculo de Meeus/Jones/Butcher): [mês, dia] */
  $b = $a % 19; $c = intdiv($a, 100); $d = $a % 100; $e = intdiv($c, 4); $f = $c % 4; $g = intdiv($c + 8, 25); $h = intdiv($c - $g + 1, 3);
  $i = (19 * $b + $c - $e - $h + 15) % 30; $k = intdiv($d, 4); $l = $d % 4; $m = (32 + 2 * $f + 2 * $k - $i - $l) % 7; $n = intdiv($b + 11 * $i + 22 * $m, 451);
  return [intdiv($i + $m - 7 * $n + 114, 31), (($i + $m - 7 * $n + 114) % 31) + 1];
}
function metaFeriadoNome(string $dia): string {   /* os feriados (nacionais e os de São Paulo): valem como fim de semana */
  $md = substr($dia, 5, 5); if (isset(META_FERIADOS[$md])) return META_FERIADOS[$md];
  $a = (int)substr($dia, 0, 4); [$pm, $pd] = metaPascoa($a);
  foreach ([-48 => 'Carnaval', -47 => 'Carnaval', -2 => 'Sexta-feira Santa', 60 => 'Corpus Christi'] as $o => $nome) if (date('Y-m-d', mktime(12, 0, 0, $pm, $pd + $o, $a)) === $dia) return $nome;
  return '';
}
/* A meta de cada dia do mês. Os dias fixados pelo gestor ficam como estão; o resto da meta do mês (o que os fixos não cobrem) vai para os
   outros dias pelo peso (dia de semana 1; sábado, domingo e feriado, o peso do fim de semana), em valores redondos de R$ 10 (os restos vão
   para os dias de fração maior; os centavos, se houver, para o dia de peso maior). A tela faz a mesma conta (metaPlanoDias). */
function metaPlanoDias(array $p, string $mes): array {
  $a = (int)substr($mes, 0, 4); $m = (int)substr($mes, 5, 2); $n = (int)date('t', mktime(12, 0, 0, $m, 1, $a));
  $fds = (int)round(min(5, max(1, (float)($p['fds'] ?? 2))) * 10); $fixos = is_array($p['fixos'] ?? null) ? $p['fixos'] : []; $D = []; $somaF = 0; $W = 0;
  for ($i = 1; $i <= $n; $i++) {
    $dia = sprintf('%s-%02d', $mes, $i); $sd = (int)date('w', mktime(12, 0, 0, $m, $i, $a)); $fer = metaFeriadoNome($dia);
    $f = array_key_exists($dia, $fixos) && is_numeric($fixos[$dia]) && (float)$fixos[$dia] >= 0;
    $x = ['dia' => $dia, 'sem' => $sd, 'fer' => $fer, 'fixo' => $f, 'w' => ($sd === 0 || $sd === 6 || $fer !== '') ? $fds : 10, 'c' => $f ? (int)round((float)$fixos[$dia] * 100) : 0, 'u' => 0, 'r' => 0];
    if ($f) $somaF += $x['c']; else $W += $x['w'];
    $D[] = $x;
  }
  $resto = max(0, (int)round((float)($p['valor'] ?? 0) * 100) - $somaF); $U = intdiv($resto, 1000);
  $auto = array_keys(array_filter($D, fn($x) => !$x['fixo']));
  if ($auto) {
    $dist = 0; foreach ($auto as $k) { $D[$k]['u'] = intdiv($U * $D[$k]['w'], $W); $D[$k]['r'] = ($U * $D[$k]['w']) % $W; $dist += $D[$k]['u']; }
    $ord = $auto; usort($ord, fn($x1, $x2) => ($D[$x2]['r'] <=> $D[$x1]['r']) ?: strcmp($D[$x1]['dia'], $D[$x2]['dia']));
    foreach (array_slice($ord, 0, $U - $dist) as $k) $D[$k]['u']++;
    foreach ($auto as $k) $D[$k]['c'] = $D[$k]['u'] * 1000;
    $sobra = $resto - $U * 1000;
    if ($sobra) { $mk = $auto[0]; foreach ($auto as $k) if ($D[$k]['w'] > $D[$mk]['w']) $mk = $k; $D[$mk]['c'] += $sobra; }
  }
  return array_map(fn($x) => ['dia' => $x['dia'], 'sem' => $x['sem'], 'fer' => $x['fer'], 'fixo' => $x['fixo'], 'v' => round($x['c'] / 100, 2)], $D);
}
function metaPlanos(): array { $c = cfg('metas', []); $u = is_array($c) && is_array($c['u'] ?? null) ? $c['u'] : []; ksort($u); return $u; }
function metaPlano(int $uni, string $mes): ?array { $p = metaPlanos()[$uni][$mes] ?? null; return is_array($p) && (float)($p['valor'] ?? 0) > 0 ? $p : null; }
function metaAlertasLista(): array {
  $a = cfg('meta_alertas', []); $o = [];
  foreach (is_array($a) ? $a : [] as $x) if (is_numeric($x) && (float)$x > 0) $o[] = round((float)$x, 2);
  $o = array_values(array_unique($o, SORT_REGULAR)); rsort($o); return array_slice($o, 0, 2);
}
function metaAlertas(float $m): array { return array_values(array_filter(metaAlertasLista(), fn($a) => $a < $m - 0.004)); }   /* os que valem para uma meta (só os menores que ela) */
function metaMesSeguinte(string $mes): string { return date('Y-m', mktime(12, 0, 0, (int)substr($mes, 5, 2) + 1, 1, (int)substr($mes, 0, 4))); }
function metaFatMes(string $mes): array {   /* o faturado de cada unidade em cada dia do mês: [unidade][dia] */
  $q = db()->prepare('SELECT COALESCE(unidade_id, 0) u, substr(quando, 1, 10) d, COALESCE(SUM(valor), 0) v FROM vendas WHERE quando >= ? AND quando < ? AND servico != ? GROUP BY u, d');
  $q->execute([$mes . '-01 00:00:00', metaMesSeguinte($mes) . '-01 00:00:00', SERV_IMPORTADO]); $o = [];
  foreach ($q->fetchAll() as $l) $o[(int)$l['u']][(string)$l['d']] = round((float)$l['v'], 2);
  return $o;
}
function metaHoraQuePassou(string $d0, float $alvo, int $uni): string {   /* a hora da venda da unidade que fez o faturado (desde $d0) chegar a $alvo */
  $t = 0.0; $q = db()->prepare('SELECT valor, quando FROM vendas WHERE quando >= ? AND unidade_id = ? AND servico != ? ORDER BY quando, id'); $q->execute([$d0, $uni, SERV_IMPORTADO]);
  foreach ($q->fetchAll() as $l) { $t = round($t + (float)$l['valor'], 2); if ($t >= $alvo - 0.004) return (string)$l['quando']; }
  return agora();
}
function metaMarca(string $k, $v): bool { $q = db()->prepare('INSERT OR IGNORE INTO config (chave, valor) VALUES (?, ?)'); $q->execute([$k, json_encode($v, JSON_UNESCAPED_UNICODE)]); return $q->rowCount() === 1; }
function metaN(float $v): string { return number_format($v, 2, '.', ''); }
/* a meta batida também no celular de cada gestor (os avisos do celular, os mesmos dos pedidos): só quem marcou primeiro manda */
function metaAvisarCelular(array $b): void {
  try { require_once __DIR__ . '/push-lib.php'; pushDepois(function () use ($b) { pushMeta($b); }); } catch (Throwable $e) { registrarErro('aviso da meta no celular: ' . $e->getMessage()); }
}
function metaLimpar(string $hoje): void {   /* uma vez por dia: as marcas velhas (mais de 40 dias) e as chaves da meta de antes da 1.87 */
  if (cfg('meta_limpo') === $hoje) return; gravarCfg('meta_limpo', $hoje); $lim = date('Y-m-d', strtotime($hoje . ' -40 days'));
  $db = db(); $db->prepare("DELETE FROM config WHERE (chave LIKE 'meta!_bat:%' ESCAPE '!' AND substr(chave, 10, 10) < ?) OR (chave LIKE 'meta!_av:%' ESCAPE '!' AND substr(chave, 9, 10) < ?)")->execute([$lim, $lim]);
  $db->exec("DELETE FROM config WHERE chave IN ('meta_dia', 'meta_batida', 'meta_avisos', 'meta_push')");
}
function metaDoDia(?array $eu): ?array {
  $hoje = date('Y-m-d'); $mes = substr($hoje, 0, 7); $U = [];
  foreach (metaPlanos() as $uni => $planos) { $p = is_array($planos) ? ($planos[$mes] ?? null) : null; if (is_array($p) && (float)($p['valor'] ?? 0) > 0) $U[(int)$uni] = $p; }
  if (!$U) return null;
  try { metaLimpar($hoje); } catch (Throwable $e) { registrarErro('limpeza da meta: ' . $e->getMessage()); }
  $F = metaFatMes($mes); $nomes = []; foreach (db()->query('SELECT id, nome FROM unidades')->fetchAll() as $l) $nomes[(int)$l['id']] = (string)$l['nome'];
  $o = ['dia' => $hoje, 'mes' => $mes, 'unis' => [], 'alertas' => metaAlertasLista()];
  foreach ($U as $uni => $p) {
    $meta = 0.0; $ateOntem = 0.0; $fatOntem = 0.0; $restam = 0;
    foreach (metaPlanoDias($p, $mes) as $d) {
      if ($d['dia'] < $hoje) { $ateOntem += $d['v']; $fatOntem += $F[$uni][$d['dia']] ?? 0; } else { if ($d['dia'] === $hoje) $meta = (float)$d['v']; $restam++; }
    }
    $f = round((float)($F[$uni][$hoje] ?? 0), 2); $fm = round(array_sum($F[$uni] ?? []), 2); $nome = $nomes[$uni] ?? '';
    $x = ['uni' => $uni, 'nome' => $nome, 'valor' => round($meta, 2), 'faturado' => $f, 'batida' => false, 'em' => null, 'avisos' => []];
    if ($meta > 0) {
      $kb = "meta_bat:$hoje:$uni:" . metaN($meta); $b = cfg($kb);
      if (!is_array($b) && $f >= $meta - 0.004) {
        $b = ['em' => metaHoraQuePassou($hoje . ' 00:00:00', $meta, $uni), 'valor' => $meta];
        if (metaMarca($kb, $b)) metaAvisarCelular(['tipo' => 'dia', 'dia' => $hoje, 'uni' => $uni, 'nome' => $nome, 'valor' => $meta, 'em' => $b['em'], 'faturado' => $f]); else $b = cfg($kb);
      }
      if (is_array($b)) { $x['batida'] = true; $x['em'] = (string)$b['em']; }
      foreach (metaAlertas($meta) as $a) {
        $ka = "meta_av:$hoje:$uni:" . metaN($meta) . ':' . metaN($a); $em = cfg($ka);
        if ($em === null && $f >= $meta - $a - 0.004) { $em = metaHoraQuePassou($hoje . ' 00:00:00', $meta - $a, $uni); if (!metaMarca($ka, $em)) $em = cfg($ka); }
        if ($em !== null) $x['avisos'][] = ['falta' => $a, 'em' => (string)$em];
      }
      if ($x['avisos'] && !$x['batida']) $x['resta'] = round($meta - $f, 2);   /* quanto falta agora (para o aviso) */
    }
    $vm = round((float)$p['valor'], 2); $km = "meta_mes:$mes:$uni:" . metaN($vm); $bm = cfg($km);
    if (!is_array($bm) && $fm >= $vm - 0.004) {
      $bm = ['em' => metaHoraQuePassou($mes . '-01 00:00:00', $vm, $uni), 'valor' => $vm];
      if (metaMarca($km, $bm)) metaAvisarCelular(['tipo' => 'mes', 'mes' => $mes, 'dia' => $hoje, 'uni' => $uni, 'nome' => $nome, 'valor' => $vm, 'em' => $bm['em'], 'faturado' => $fm]); else $bm = cfg($km);
    }
    $x['mes'] = ['mes' => $mes, 'valor' => $vm, 'faturado' => $fm, 'meta_ate_ontem' => round($ateOntem, 2), 'fat_ate_ontem' => round($fatOntem, 2), 'saldo' => round($fatOntem - $ateOntem, 2),
      'batida' => is_array($bm), 'em' => is_array($bm) ? (string)$bm['em'] : null, 'dias_restantes' => $restam];
    $o['unis'][] = $x;
  }
  return $o;
}
/* o mês de uma unidade (Ajustes e a folha da meta): o plano salvo, a meta e o faturado de cada dia (até hoje) e os alertas */
function metaMesVer(int $uni, string $mes): array {
  $p = metaPlano($uni, $mes); $F = metaFatMes($mes)[$uni] ?? []; $hoje = date('Y-m-d');
  $dias = metaPlanoDias($p ?: ['valor' => 0, 'fds' => 2, 'fixos' => []], $mes);
  foreach ($dias as &$d) $d['fat'] = $d['dia'] <= $hoje ? round((float)($F[$d['dia']] ?? 0), 2) : null; unset($d);
  return ['uni' => $uni, 'mes' => $mes, 'plano' => $p, 'dias' => $dias, 'alertas' => metaAlertasLista()];
}
function metaPulso(): string {
  return (string)db()->query("SELECT COALESCE(group_concat(chave || '=' || valor, ';'), '') FROM (SELECT chave, valor FROM config WHERE chave IN ('metas', 'meta_alertas') OR chave LIKE 'meta!_bat:%' ESCAPE '!' OR chave LIKE 'meta!_av:%' ESCAPE '!' OR chave LIKE 'meta!_mes:%' ESCAPE '!' ORDER BY chave)")->fetchColumn();
}
function painelDoDia(): array {
  $db = db(); $hoje = date('Y-m-d') . ' 00:00:00'; $o = [];
  $um = function (int $u) use (&$o) { if (!isset($o[$u])) $o[$u] = ['uni' => $u, 'faturado' => 0.0, 'vendas' => 0, 'desc' => 0.0, 'recebido' => 0.0, 'devolvido' => 0.0, 'formas' => array_fill_keys(array_keys(FORMAS_PAGAMENTO), 0.0), 'cancelados' => 0, 'cancelado' => 0.0, 'servicos' => [], 'notas' => 0, 'notas_v' => 0.0]; };
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, COUNT(*) n, COALESCE(SUM(valor), 0) v, COALESCE(SUM(desc_valor), 0) d FROM vendas WHERE quando >= ? AND servico != ? GROUP BY u'); $q->execute([$hoje, SERV_IMPORTADO]);
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $um($u); $o[$u]['faturado'] = round((float)$l['v'], 2); $o[$u]['vendas'] = (int)$l['n']; $o[$u]['desc'] = round((float)$l['d'], 2); }
  $q = $db->prepare('SELECT COALESCE(p.unidade_id, v.unidade_id, 0) u, p.forma f, COALESCE(SUM(p.valor), 0) t FROM pagamentos p JOIN vendas v ON v.id = p.venda_id WHERE p.quando >= ? GROUP BY u, p.forma'); $q->execute([$hoje]);   // 1.31: a unidade em que o dinheiro entrou
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $um($u); $f = (string)$l['f']; $o[$u]['formas'][$f] = round(($o[$u]['formas'][$f] ?? 0) + (float)$l['t'], 2); $o[$u]['recebido'] = round($o[$u]['recebido'] + (float)$l['t'], 2); }
  foreach (devolvidoHoje() as $u => $d) { $um($u); $o[$u]['devolvido'] = $d['total']; $o[$u]['recebido'] = round($o[$u]['recebido'] - $d['total'], 2); foreach ($d['formas'] as $f => $v) $o[$u]['formas'][$f] = round(($o[$u]['formas'][$f] ?? 0) - $v, 2); }   // 1.31: a venda de outro dia devolvida hoje
  $q = $db->prepare("SELECT COALESCE(unidade_id, 0) u, COUNT(*) n, COALESCE(SUM(valor), 0) v FROM cancelamentos WHERE quando >= ? AND tipo != 'desfeita' GROUP BY u"); $q->execute([$hoje]);
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $um($u); $o[$u]['cancelados'] = (int)$l['n']; $o[$u]['cancelado'] = round((float)$l['v'], 2); }
  // 1.27: o faturado de hoje por serviço (o gráfico do painel). Os produtos da loja num grupo, as festas em outro, o serviço pelo nome (a
  // recreação, cada carrinho) e os pacotes de horas num grupo só. Só o que tem valor; anda pelo índice da hora da venda, como as contas de cima.
  // O grupo sai da própria venda: trocar o nome de um serviço em Ajustes no meio do dia não muda onde as vendas já feitas aparecem
  $q = $db->prepare("SELECT COALESCE(unidade_id, 0) u, servico s, (COALESCE(produto_id, 0) > 0) p, (alug != '') a, (minutos > 0 AND auto = 0 AND voucher = '') m, COUNT(*) n, COALESCE(SUM(valor), 0) v FROM vendas WHERE quando >= ? AND servico != ? AND valor > 0 GROUP BY u, s, p, a, m"); $q->execute([$hoje, SERV_IMPORTADO]);
  $sv = []; foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $n = mb_strtoupper(trim((string)$l['s']));
    $g = $l['p'] ? 'PRODUTOS' : (!$l['a'] && $l['m'] && ($n === '' || preg_match('/^PACOTE\b/u', $n)) ? 'PACOTES DE HORAS' : (mb_strpos($n, 'FESTA') !== false ? 'FESTA' : ($n !== '' ? $n : 'OUTROS')));   // (o pacote é a venda de horas sem nome de serviço ou com o nome começando por PACOTE; o resto fica com o nome que tinha na hora da venda)
    if (!isset($sv[$u][$g])) $sv[$u][$g] = ['n' => $g, 'q' => 0, 'v' => 0.0]; $sv[$u][$g]['q'] += (int)$l['n']; $sv[$u][$g]['v'] = round($sv[$u][$g]['v'] + (float)$l['v'], 2); }
  foreach ($sv as $u => $L) { $um($u); $L = array_values($L); usort($L, fn($a, $b) => [$b['v'], $b['q'], $a['n']] <=> [$a['v'], $a['q'], $b['n']]); $o[$u]['servicos'] = $L; }
  // 1.55: as notas fiscais emitidas hoje (na unidade do aparelho em que foram abertas): o faturamento em notas, ao lado do caixa. As
  // canceladas vêm na lista, sem contar
  $nf = []; $q = $db->prepare("SELECT * FROM pedidos WHERE tipo = 'nf' AND numero != '' AND feito_em >= ? AND status IN ('usado', 'cancelado') ORDER BY feito_em DESC, id DESC LIMIT 400"); $q->execute([$hoje]);
  foreach ($q->fetchAll() as $p) { $n = notaPublica($p); if ($n['st'] === 'emitida') { $um($n['uni']); $o[$n['uni']]['notas']++; $o[$n['uni']]['notas_v'] = round($o[$n['uni']]['notas_v'] + $n['valor'], 2); } $nf[] = $n; }
  ksort($o); return ['desde' => $hoje, 'unidades' => array_values($o), 'notas' => $nf];
}
// 1.30: o caixa do dia da recepção. O que foi RECEBIDO hoje (desde a meia-noite), por unidade (a da venda), por forma de pagamento e pelo que
// foi pago: a recreação avulsa (o tempo que não é pacote: os períodos, o tempo além do pacote, o avulso da saída), os pacotes de horas, a
// festa (a festa nova, o upgrade, o adicional), os produtos da loja, os carrinhos (o que sai da loja e volta: o fusquinha, o carrinho de bebê,
// cada um com o nome dele) e o resto. Os carrinhos ficam à parte porque não entram no faturamento da recreação.
function grupoDoCaixa(array $l): string {
  $n = mb_strtoupper(trim((string)$l['s']));
  if (!empty($l['pr'])) return 'produtos';
  if ((string)$l['a'] !== '') return 'carrinhos';
  foreach ((array)regra()['servicos'] as $s) if (!empty($s['volta']) && mb_strtoupper(trim((string)($s['n'] ?? ''))) === $n) return 'carrinhos';   // (a venda antiga do carrinho, sem o "sai e volta")
  if ((int)$l['m'] > 0 && empty($l['au']) && (string)$l['vo'] === '' && ($n === '' || preg_match('/^PACOTE\b/u', $n))) return 'pacotes';   // 1.31: antes da festa (o pacote "FESTA DO PIJAMA" é pacote, como no painel)
  if (mb_strpos($n, 'FESTA') !== false) return 'festa';
  $st = servicoTempo(); if ((int)$l['m'] > 0 || ($st && $n === mb_strtoupper(trim((string)$st['n'])))) return 'recreacao';
  return 'outros';
}
// 1.31: antes de apagar vendas canceladas, o que delas foi pago em OUTRO DIA vira uma devolução de hoje (o dinheiro sai do caixa de hoje,
// na unidade que devolveu, pela forma em que tinha entrado). O que foi pago hoje sai do caixa sozinho, com o pagamento apagado.
function estornarPagosAntes(array $vendas): void {
  $por = []; foreach ($vendas as $v) if (!empty($v['id'])) $por[(int)$v['id']] = $v; if (!$por) return;
  $db = db(); $q = $db->prepare('SELECT venda_id, forma, valor, quando, unidade_id FROM pagamentos WHERE quando < ? AND venda_id IN (' . implode(',', array_keys($por)) . ') ORDER BY id'); $q->execute([date('Y-m-d') . ' 00:00:00']);
  $ins = $db->prepare('INSERT INTO estornos (quando, unidade_id, forma, valor, grupo, item, pago_em, venda_id, crianca_id, pago_uni) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'); $aqui = unidadeDoPedido();   // 1.91: pago_uni
  foreach ($q->fetchAll() as $p) { $v = $por[(int)$p['venda_id']]; $valor = round((float)$p['valor'], 2); if ($valor <= 0) continue;
    $g = grupoDoCaixa(['s' => (string)($v['servico'] ?? ''), 'pr' => (int)($v['produto_id'] ?? 0) > 0, 'a' => (string)($v['alug'] ?? ''), 'm' => (int)($v['minutos'] ?? 0), 'au' => (int)($v['auto'] ?? 0), 'vo' => (string)($v['voucher'] ?? '')]);
    $u = $aqui ?? (($p['unidade_id'] ?? null) !== null ? (int)$p['unidade_id'] : (($v['unidade_id'] ?? null) !== null ? (int)$v['unidade_id'] : null));
    $pu = ($p['unidade_id'] ?? null) !== null ? (int)$p['unidade_id'] : (($v['unidade_id'] ?? null) !== null ? (int)$v['unidade_id'] : $u);   // (a unidade em que o dinheiro entrou)
    $ins->execute([agora(), $u, (string)$p['forma'], $valor, $g, $g === 'carrinhos' ? (mb_strtoupper(trim((string)($v['servico'] ?? ''))) ?: 'CARRINHO') : '', (string)$p['quando'], (int)$v['id'], (int)$v['crianca_id'], $pu]); }
}
// o devolvido hoje, por unidade: [uni => ['total', 'formas', 'grupos', 'itens', 'n']]
/* 2.26 (o dono: "visualizar caixa de ontem, ou selecionar uma data para visualizar o caixa"): o começo e o fim do dia do caixa; sem dia, hoje */
function diaDoCaixa(?string $dia = null): array { $d = $dia !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) ? $dia : date('Y-m-d'); return [$d . ' 00:00:00', date('Y-m-d', strtotime($d . ' 12:00:00') + 86400) . ' 00:00:00']; }
function devolvidoHoje(?string $dia = null): array {
  [$d0, $d1] = diaDoCaixa($dia); $q = db()->prepare('SELECT COALESCE(unidade_id, 0) u, forma, valor, grupo, item, venda_id FROM estornos WHERE quando >= ? AND quando < ? ORDER BY id'); $q->execute([$d0, $d1]); $o = [];
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $v = round((float)$l['valor'], 2); if (!isset($o[$u])) $o[$u] = ['total' => 0.0, 'formas' => [], 'grupos' => [], 'itens' => [], 'n' => 0, 'vendas' => []];
    $o[$u]['total'] = round($o[$u]['total'] + $v, 2); $o[$u]['formas'][$l['forma']] = round(($o[$u]['formas'][$l['forma']] ?? 0) + $v, 2); $o[$u]['grupos'][$l['grupo']] = round(($o[$u]['grupos'][$l['grupo']] ?? 0) + $v, 2); $o[$u]['vendas'][(int)$l['venda_id']] = 1; $o[$u]['n'] = count($o[$u]['vendas']);   // n: quantas vendas devolvidas
    if ((string)$l['item'] !== '') $o[$u]['itens'][$l['item']] = round(($o[$u]['itens'][$l['item']] ?? 0) + $v, 2); }
  foreach ($o as &$x) unset($x['vendas']); unset($x); return $o;
}
function caixaDoDia(int $so = 0, ?string $dia = null): array {   /* 2.15: $so = só esta unidade (quem não é o gestor vê só a dele). 2.26: $dia = outro dia */
  [$d0, $d1] = diaDoCaixa($dia); $ehHoje = $d0 === date('Y-m-d') . ' 00:00:00';
  $q = db()->prepare("SELECT COALESCE(p.unidade_id, v.unidade_id, 0) u, p.forma f, p.valor val, p.venda_id vid, v.servico s, (COALESCE(v.produto_id, 0) > 0) pr, v.alug a, v.minutos m, v.auto au, v.voucher vo, v.festa_id fid FROM pagamentos p JOIN vendas v ON v.id = p.venda_id WHERE p.quando >= ? AND p.quando < ? ORDER BY p.id");   // 1.31: a unidade em que o dinheiro entrou
  $q->execute([$d0, $d1]); $o = [];
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $g = grupoDoCaixa($l); $f = (string)$l['f']; $v = round((float)$l['val'], 2);
    if (!isset($o[$u])) $o[$u] = ['uni' => $u, 'grupos' => []];
    if (!isset($o[$u]['grupos'][$g])) $o[$u]['grupos'][$g] = ['formas' => [], 'total' => 0.0, 'vendas' => [], 'itens' => []];
    $x = &$o[$u]['grupos'][$g]; $x['formas'][$f] = round(($x['formas'][$f] ?? 0) + $v, 2); $x['total'] = round($x['total'] + $v, 2); $x['vendas'][(int)$l['vid']] = 1;
    if ($g === 'carrinhos') { $nm = mb_strtoupper(trim((string)$l['s'])) ?: 'CARRINHO'; $x['itens'][$nm] = round(($x['itens'][$nm] ?? 0) + $v, 2); }
    if ($g === 'festa') { $nm = !empty($l['fid']) ? rotuloFestaCaixa((int)$l['fid'], (string)$l['s']) : (mb_strtoupper(trim((string)$l['s'])) ?: 'FESTA'); $x['itens'][$nm] = round(($x['itens'][$nm] ?? 0) + $v, 2); }   // 1.38: de qual festa
    unset($x); }
  foreach ($o as &$x) foreach ($x['grupos'] as &$g) $g['vendas'] = count($g['vendas']); unset($x, $g);
  foreach (devolvidoHoje($dia) as $u => $d) { if (!isset($o[$u])) $o[$u] = ['uni' => $u, 'grupos' => []]; $o[$u]['devolvido'] = $d; }   // 1.31
  /* 2.15 (o dono: "no fechamento ... quantas crianças passaram pelo espaço no final do dia"): quem saiu hoje e quem está no espaço, uma vez cada */
  $qk = db()->prepare('SELECT u, COUNT(DISTINCT c) n FROM (SELECT COALESCE(unidade_id, 0) u, crianca_id c FROM visitas WHERE saiu >= ? AND saiu < ?' . ($ehHoje ? ' UNION ALL SELECT COALESCE(unidade_id, 0) u, id c FROM criancas WHERE entrou IS NOT NULL' : '') . ') GROUP BY u'); $qk->execute([$d0, $d1]);
  foreach ($qk->fetchAll() as $l) { $u = (int)$l['u']; if (!isset($o[$u])) $o[$u] = ['uni' => $u, 'grupos' => []]; $o[$u]['criancas'] = (int)$l['n']; }
  if ($so > 0) $o = array_filter($o, fn($x) => (int)$x['uni'] === $so);
  ksort($o); return ['desde' => $d0, 'dia' => substr($d0, 0, 10), 'unidades' => array_values($o)];
}
/* ---------- 2.53: Ajustes de comissão (o gestor troca quem fez a venda) ----------
   id > 0: uma linha da venda; a troca vale para a venda inteira (o que foi vendido junto no mesmo atendimento, que tem uma vendedora só).
   id < 0: o cadastro novo (a família cadastrada junto, no mesmo dia, pela mesma pessoa). Fica no registro do cliente: de quem, para quem */
function comissaoAjustar(array $in, array $u): array {
  $db = db(); $id = (int)($in['id'] ?? 0); $novo = trim(preg_replace('/\s+/u', ' ', maiusculo((string)($in['vend'] ?? ''))));
  if ($novo === '') falha('Escolha quem fez a venda.');
  if (!in_array($novo, array_merge(vendedorasTodas(), vendedoras()), true)) falha($novo . ' não está na lista de quem vende. Confira em Ajustes → Equipe.');
  $quem = maiusculo(primeiroNome((string)$u['nome']));
  if ($id > 0) {
    $q = $db->prepare('SELECT * FROM vendas WHERE id = ?'); $q->execute([$id]); $v = $q->fetch(); if (!$v) falha('Venda não encontrada (pode ter sido excluída).', 404);
    $g = (int)($v['vend_g'] ?? 0) ?: (int)$v['id']; $antes = trim((string)$v['vendedora']);
    if ($antes === $novo) falha('Essa venda já está com ' . $novo . '.', 409);
    $L = $db->prepare('SELECT * FROM vendas WHERE id = ? OR vend_g = ? ORDER BY id'); $L->execute([$g, $g]); $V = $L->fetchAll(); if (!$V) $V = [$v];
    $up = $db->prepare('UPDATE vendas SET vendedora = ? WHERE id = ?'); foreach ($V as $x) $up->execute([$novo, (int)$x['id']]);
    $c = crianca((int)$v['crianca_id']); $itens = implode(' + ', array_map(fn($x) => nomeDaVenda($x), $V));
    $reg = registrar('alt', (int)$v['crianca_id'], (string)($c['nome'] ?? ''), 'Comissão ajustada: ' . $itens . ' (' . date('d/m H:i', strtotime((string)$v['quando'])) . ') · de ' . ($antes !== '' ? $antes : 'sem nome') . ' para ' . $novo . ' · por ' . $quem);
    return ['n' => count($V), 'antes' => $antes, 'novo' => $novo, 'registro' => $reg];
  }
  if ($id < 0) {
    $c = crianca(-$id); if (!$c || trim((string)($c['cad_por'] ?? '')) === '') falha('Cadastro não encontrado.', 404);
    $antes = (string)$c['cad_por']; if ($antes === $novo) falha('Esse cadastro já está com ' . $novo . '.', 409);
    $dia = substr((string)$c['criado_em'], 0, 10); $doc = (string)preg_replace('/\D/', '', (string)$c['cpf']);
    $q = $db->prepare("SELECT * FROM criancas WHERE cad_por = ? AND substr(criado_em, 1, 10) = ?"); $q->execute([$antes, $dia]); $ids = [];
    foreach ($q->fetchAll() as $x) { $d = (string)preg_replace('/\D/', '', (string)$x['cpf']);
      $mesma = $doc !== '' ? $d === $doc : ($d === '' && maiusculo((string)$x['resp']) === maiusculo((string)$c['resp']) && preg_replace('/\D/', '', (string)$x['tel']) === preg_replace('/\D/', '', (string)$c['tel']));
      if ($mesma || (int)$x['id'] === (int)$c['id']) $ids[] = (int)$x['id']; }
    $db->prepare('UPDATE criancas SET cad_por = ? WHERE id IN (' . implode(',', $ids) . ')')->execute([$novo]);
    $reg = registrar('alt', (int)$c['id'], (string)$c['nome'], 'Comissão ajustada: cadastro novo (' . date('d/m', strtotime((string)$c['criado_em'])) . ') · de ' . $antes . ' para ' . $novo . ' · por ' . $quem);
    return ['n' => count($ids), 'antes' => $antes, 'novo' => $novo, 'registro' => $reg];
  }
  falha('Escolha a venda.');
}
/* ---------- 2.52: Casa Tom → o sinal de 50% ----------
   A venda da Casa Tom (só produtos) em que o cliente paga a metade agora e o resto na retirada. A venda é feita inteira (o estoque sai, a
   comissão é de quem vendeu); o pagamento de agora é a metade e o resto fica a receber. O sinal guarda o nome e o telefone do cliente,
   para a aba "Sinais" lembrar de cobrar; na retirada, "Receber o restante" fecha. A venda excluída fecha o sinal sozinha */
function ctSinaisTabela(): void {
  static $ok = false; if ($ok) return;
  db()->exec("CREATE TABLE IF NOT EXISTS ct_sinais (id INTEGER PRIMARY KEY, quando TEXT NOT NULL, unidade_id INTEGER, vendas TEXT NOT NULL, cliente TEXT NOT NULL, tel TEXT NOT NULL DEFAULT '',
    itens TEXT NOT NULL DEFAULT '', total REAL NOT NULL DEFAULT 0, sinal REAL NOT NULL DEFAULT 0, vendedora TEXT NOT NULL DEFAULT '', por INTEGER, por_nome TEXT NOT NULL DEFAULT '',
    estado TEXT NOT NULL DEFAULT 'aberto', fechado_em TEXT, fechado_por TEXT NOT NULL DEFAULT '')");
  $ok = true;
}
function ctSinalNovo(array $vendas, string $cliente, string $tel, float $total, float $sinal, string $vend, array $u): array {
  ctSinaisTabela(); $db = db(); $it = []; $q = $db->prepare('SELECT * FROM vendas WHERE id = ?');
  foreach ($vendas as $vid) { $q->execute([(int)$vid]); $v = $q->fetch(); if ($v) $it[] = ((int)($v['qtd'] ?? 0) > 1 ? (int)$v['qtd'] . ' × ' : '') . nomeDaVenda($v); }
  $db->prepare('INSERT INTO ct_sinais (quando, unidade_id, vendas, cliente, tel, itens, total, sinal, vendedora, por, por_nome) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([agora(), unidadeDoPedido(), json_encode(array_values(array_map('intval', $vendas))), $cliente, $tel, mb_substr(implode(', ', $it), 0, 300), round($total, 2), round($sinal, 2), $vend, (int)$u['id'], (string)$u['nome']]);
  return ['id' => (int)$db->lastInsertId(), 'pago' => round($sinal, 2), 'falta' => round($total - $sinal, 2), 'nome' => $cliente, 'tel' => $tel];
}
// o que ainda falta de um sinal: o valor das vendas (as que ainda existem) menos o que já foi pago nelas
function ctSinalConta(array $s): array {
  $ids = array_values(array_filter(array_map('intval', (array)json_decode((string)$s['vendas'], true)))); $o = ['ids' => [], 'valor' => 0.0, 'pago' => 0.0, 'falta' => 0.0, 'crianca' => 0];
  if (!$ids) return $o;
  /* (só as vendas de antes do sinal: o número de uma venda excluída pode voltar numa venda nova) */
  $q = db()->prepare('SELECT id, crianca_id, valor, COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = vendas.id), 0) pago FROM vendas WHERE id IN (' . implode(',', $ids) . ') AND quando <= ?'); $q->execute([(string)$s['quando']]);
  foreach ($q->fetchAll() as $l) {
    $o['ids'][] = (int)$l['id']; $o['crianca'] = (int)$l['crianca_id']; $o['valor'] += (float)$l['valor']; $o['pago'] += (float)$l['pago']; }
  $o['valor'] = round($o['valor'], 2); $o['pago'] = round($o['pago'], 2); $o['falta'] = round(max(0, $o['valor'] - $o['pago']), 2); return $o;
}
function ctSinalPublico(array $s, array $k): array {
  return ['id' => (int)$s['id'], 'quando' => (string)$s['quando'], 'uni' => $s['unidade_id'] === null ? null : (int)$s['unidade_id'], 'cliente' => (string)$s['cliente'], 'tel' => (string)$s['tel'],
    'itens' => (string)$s['itens'], 'total' => $k['ids'] ? $k['valor'] : round((float)$s['total'], 2), 'pago' => $k['ids'] ? $k['pago'] : round((float)$s['sinal'], 2), 'falta' => $k['falta'], 'sinal' => round((float)$s['sinal'], 2),
    'vendedora' => (string)$s['vendedora'], 'por' => primeiroNome((string)$s['por_nome']), 'estado' => (string)$s['estado'], 'fechado_em' => $s['fechado_em'] === null ? null : (string)$s['fechado_em'], 'fechado_por' => (string)$s['fechado_por']];
}
// os sinais em aberto (e os fechados nos últimos 7 dias) de uma unidade (0 = todas)
function ctSinaisLista(int $uni): array {
  ctSinaisTabela(); $db = db(); $o = ['abertos' => [], 'fechados' => []];
  $q = $db->prepare("SELECT * FROM ct_sinais WHERE estado = 'aberto' OR fechado_em >= ? ORDER BY id DESC LIMIT 400"); $q->execute([date('Y-m-d H:i:s', time() - 7 * 86400)]);
  foreach ($q->fetchAll() as $s) {
    if ($uni > 0 && (int)($s['unidade_id'] ?? 0) !== $uni) continue;
    $k = ctSinalConta($s);
    if ($s['estado'] === 'aberto' && $k['falta'] < 0.005) {   /* (pago por outro caminho, ou a venda foi excluída) */
      $s['estado'] = $k['ids'] ? 'retirado' : 'cancelado'; $s['fechado_em'] = agora(); $s['fechado_por'] = $k['ids'] ? 'pago' : 'venda excluída';
      $db->prepare('UPDATE ct_sinais SET estado = ?, fechado_em = ?, fechado_por = ? WHERE id = ?')->execute([$s['estado'], $s['fechado_em'], $s['fechado_por'], (int)$s['id']]); }
    $o[$s['estado'] === 'aberto' ? 'abertos' : 'fechados'][] = ctSinalPublico($s, $k);
  }
  return $o;
}
// na retirada: recebe o que falta (a soma das formas = o que falta) e fecha o sinal
function ctSinalReceber(array $in, array $u): array {
  ctSinaisTabela(); $db = db(); $q = $db->prepare('SELECT * FROM ct_sinais WHERE id = ?'); $q->execute([(int)($in['id'] ?? 0)]); $s = $q->fetch(); $q->closeCursor();
  if (!$s) falha('Sinal não encontrado.', 404);
  if ($s['estado'] !== 'aberto') falha('Esse sinal já foi fechado' . ($s['fechado_por'] !== '' ? ' (' . $s['fechado_por'] . ')' : '') . '.', 409);
  $k = ctSinalConta($s);
  if ($k['falta'] < 0.005) { $db->prepare("UPDATE ct_sinais SET estado = ?, fechado_em = ?, fechado_por = ? WHERE id = ?")->execute([$k['ids'] ? 'retirado' : 'cancelado', agora(), $k['ids'] ? 'pago' : 'venda excluída', (int)$s['id']]);
    falha($k['ids'] ? 'Não falta nada desse cliente: já foi pago.' : 'A venda desse sinal foi excluída.', 409); }
  $pg = lerPagamento($in['pagamento'] ?? null); if (!$pg) falha('Escolha a forma de pagamento.');
  if (abs(array_sum($pg) - $k['falta']) >= 0.01) falha('Falta receber ' . dinheiro($k['falta']) . ', e o pagamento deu ' . dinheiro((float)array_sum($pg)) . '.', 409);
  $c = crianca($k['crianca']); if (!$c) falha('Cliente não encontrado.', 404); $parc = lerParcelas($in, $pg, $u, $c);
  $antes = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM pagamentos')->fetchColumn();
  $pago = gravarPagamento($k['ids'], $pg, (int)$c['id'], (int)$u['id']); anotarParcelas($antes, $parc, $u, (int)$c['id']);
  $reg = registrar('pag', (int)$c['id'], (string)$c['nome'], 'Restante do sinal (retirada) · ' . $s['cliente'] . ' · ' . dinheiro($k['falta']) . ' · ' . textoPagamento($pago ?: $pg) . ' · por ' . maiusculo(primeiroNome((string)$u['nome'])));
  $db->prepare("UPDATE ct_sinais SET estado = 'retirado', fechado_em = ?, fechado_por = ? WHERE id = ?")->execute([agora(), primeiroNome((string)$u['nome']), (int)$s['id']]);
  return ['valor' => $k['falta'], 'pago' => textoPagamento($pago ?: $pg), 'registro' => $reg, 'cupom' => criarCupom($c, 'venda', $antes, $u), 'crianca' => (int)$c['id'], 'vendas' => $k['ids']];
}
/* 2.50: os pagamentos do caixa de um dia (o Ajustar caixa), como a troca da forma os vê: a família, a mesma hora e a mesma forma juntas.
   $uni: a unidade do caixa (0 = todas; -1 = sem unidade). O valor de cada um não muda no ajuste, só a forma */
function pagamentosDoCaixa(?string $dia, int $uni): array {
  [$d0, $d1] = diaDoCaixa($dia);
  $q = db()->prepare("SELECT p.id, p.forma, p.valor, p.quando, p.crianca_id, COALESCE(p.unidade_id, v.unidade_id, 0) u, v.servico, v.itens, v.minutos, c.nome, c.resp
    FROM pagamentos p JOIN vendas v ON v.id = p.venda_id LEFT JOIN criancas c ON c.id = p.crianca_id WHERE p.quando >= ? AND p.quando < ? ORDER BY p.quando, p.id");
  $q->execute([$d0, $d1]); $fam = []; $o = [];
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; if ($uni > 0 && $u !== $uni) continue; if ($uni === -1 && $u !== 0) continue;
    $cid = (int)$l['crianca_id'];
    if (!isset($fam[$cid])) { $c = crianca($cid); $fam[$cid] = $c ? min(array_merge([$cid], array_map(fn($x) => (int)$x['id'], irmaosDe($c)))) : $cid; }
    $k = $fam[$cid] . '|' . (string)$l['quando'] . '|' . (string)$l['forma'];
    if (!isset($o[$k])) $o[$k] = ['pag' => (int)$l['id'], 'f' => (string)$l['forma'], 'v' => 0.0, 'quando' => (string)$l['quando'], 'u' => $u, 'nomes' => [], 'resp' => (string)($l['resp'] ?? ''), 'itens' => []];
    $x = &$o[$k]; $x['v'] = round($x['v'] + (float)$l['valor'], 2);
    $nm = (string)($l['nome'] ?? ''); if ($nm !== '' && !in_array($nm, $x['nomes'], true)) $x['nomes'][] = $nm;
    $it = nomeDaVenda($l); if ($it !== '' && !in_array($it, $x['itens'], true) && count($x['itens']) < 4) $x['itens'][] = $it;
    unset($x); }
  return ['dia' => substr($d0, 0, 10), 'pags' => array_values(array_filter($o, fn($x) => $x['v'] > 0.004))];
}
// as saídas de hoje (cada visita, com o que ela vendeu) e o que voltou para a loja hoje: o filtro "Finalizados do dia" da lista
function finalizadosDoDia(int $so = 0, ?string $dia = null): array {   /* 2.15: $so = só esta unidade. 2.26: $dia = outro dia */
  $db = db(); [$hoje, $fim] = diaDoCaixa($dia); $vis = []; $dev = []; $usadas = []; $janela = [];   /* 2.41: as vendas que já caíram numa saída, e a janela de cada saída */
  $q = $db->prepare('SELECT x.id, x.crianca_id, x.entrou, x.saiu, x.debitado_min, x.unidade_id, c.nome, c.resp FROM visitas x JOIN criancas c ON c.id = x.crianca_id WHERE x.saiu >= ? AND x.saiu < ? ORDER BY x.saiu DESC, x.id DESC LIMIT 400'); $q->execute([$hoje, $fim]); $V = $q->fetchAll();
  if ($V) {
    $ids = implode(',', array_unique(array_map(fn($v) => (int)$v['crianca_id'], $V))); $piso = min(array_map(fn($v) => substr((string)$v['entrou'], 0, 10) . ' 00:00:00', $V));
    $antes = []; $qa = $db->prepare("SELECT crianca_id, saiu FROM visitas WHERE crianca_id IN ($ids) AND saiu >= ? ORDER BY saiu"); $qa->execute([$piso]);   // as visitas de antes (a conta de cada visita começa na saída anterior)
    foreach ($qa->fetchAll() as $l) $antes[(int)$l['crianca_id']][] = (string)$l['saiu'];
    $qv = $db->prepare("SELECT v.id, v.crianca_id, v.quando, v.valor, v.servico, v.itens, v.minutos, v.auto, v.voucher, v.produto_id, v.qtd, v.alug, v.alug_min, COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) pago FROM vendas v WHERE v.crianca_id IN ($ids) AND v.quando >= ? AND v.servico != ? AND v.alug = '' AND v.itens NOT LIKE 'tempo a mais%' ORDER BY v.id");
    $qv->execute([$piso, SERV_IMPORTADO]); $vendas = []; foreach ($qv->fetchAll() as $l) $vendas[(int)$l['crianca_id']][] = $l;
    foreach ($V as $x) { $cid = (int)$x['crianca_id']; $ini = substr((string)$x['entrou'], 0, 10) . ' 00:00:00';
      $deSaida = false; foreach ($antes[$cid] ?? [] as $s) if ($s < (string)$x['saiu'] && $s >= $ini) { $ini = $s; $deSaida = true; }   // (a venda no segundo da saída anterior é daquela visita)
      $L = array_values(array_filter($vendas[$cid] ?? [], fn($v) => ($deSaida ? (string)$v['quando'] > $ini : (string)$v['quando'] >= $ini) && (string)$v['quando'] <= (string)$x['saiu']));
      $am = 0; $al = 0; $nomes = []; foreach ($L as $v) { if (!empty($v['auto'])) { if (strpos((string)$v['itens'], 'avulsa') === 0) $am += (int)$v['minutos']; else $al += (int)$v['minutos']; } elseif ((float)$v['valor'] > 0 || (string)$v['voucher'] !== '') $nomes[] = nomeDaVenda($v); }   // a recreação avulsa da visita numa linha só
      $valor = round(array_sum(array_map(fn($v) => (float)$v['valor'], $L)), 2); $pago = round(array_sum(array_map(fn($v) => min((float)$v['valor'], (float)$v['pago']), $L)), 2);
      foreach ($L as $v) $usadas[(int)$v['id']] = 1; $janela[] = [$ini, (string)$x['saiu']];   /* 2.41 */
      $vis[] = ['vids' => array_values(array_map(fn($v) => (int)$v['id'], array_filter($L, fn($v) => (float)$v['valor'] > 0.004))), 'id' => (int)$x['id'], 'crianca' => $cid, 'nome' => (string)$x['nome'], 'resp' => (string)$x['resp'], 'entrou' => (string)$x['entrou'], 'saiu' => (string)$x['saiu'], 'deb' => (int)$x['debitado_min'], 'dp' => max(0, (int)$x['debitado_min'] - array_sum(array_map(fn($v) => ehVendaDePacote($v) ? 0 : (int)$v['minutos'], $L))), 'uni' => $x['unidade_id'] === null ? null : (int)$x['unidade_id'],   /* 2.44: dp = as horas do pacote (sem o tempo vendido na visita que não é pacote: a recreação, o voucher, o além do pacote) */
        'valor' => $valor, 'falta' => max(0, round($valor - $pago, 2)), 'itens' => array_slice(array_values(array_unique(array_merge($am ? ['RECREAÇÃO avulsa · ' . dur($am)] : [], $al ? ['Além do pacote · ' . dur($al)] : [], $nomes))), 0, 4)]; }
  }
  /* 2.41 (o dono: "quando eu clico no botão vender pacote, ele tá entrando como sem valor pro caixa ... vendi um pacote para Gisele e ele não
     tá somando"): a venda do dia que não caiu em nenhuma saída (o Vender pacote, sem entrada; o pacote de um irmão: as horas são do CPF)
     vai para a saída de alguém da família no mesmo dia; sem ninguém da família saindo (nem no espaço agora), fica em "Vendas sem entrada" */
  $sem = []; $ehHoje = $hoje === date('Y-m-d') . ' 00:00:00';
  $q = $db->prepare("SELECT v.id, v.crianca_id, v.quando, v.valor, v.servico, v.itens, v.minutos, v.auto, v.voucher, v.produto_id, v.qtd, v.alug, v.alug_min, v.unidade_id, COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) pago
    FROM vendas v WHERE v.quando >= ? AND v.quando < ? AND v.servico != ? AND v.alug = '' AND v.itens NOT LIKE 'tempo a mais%' AND v.auto = 0 AND v.valor > 0.004 ORDER BY v.id"); $q->execute([$hoje, $fim, SERV_IMPORTADO]);
  foreach ($q->fetchAll() as $v) { if (isset($usadas[(int)$v['id']])) continue; $c = crianca((int)$v['crianca_id']); $F = $c ? array_merge([$c], irmaosDe($c)) : []; $fam = array_map(fn($x) => (int)$x['id'], $F) ?: [(int)$v['crianca_id']];
    $k = -1; foreach ($vis as $i => $x) if (in_array((int)$x['crianca'], $fam, true) && (string)$v['quando'] >= $janela[$i][0] && (string)$v['quando'] <= $janela[$i][1] && ($k < 0 || (string)$x['saiu'] < (string)$vis[$k]['saiu'])) $k = $i;
    /* 2.44 (o dono: "usou o pacote 2 horas e meia, mas elas venderam o pacote ... tem que colocar as duas coisas"): vendido depois da saída, no
       mesmo dia (a família compra na hora de ir embora): vai para a última saída da família antes da venda */
    if ($k < 0) foreach ($vis as $i => $x) if (in_array((int)$x['crianca'], $fam, true) && (string)$x['saiu'] < (string)$v['quando'] && ($k < 0 || (string)$x['saiu'] > (string)$vis[$k]['saiu'])) $k = $i;
    $vv = round((float)$v['valor'], 2); $falta = max(0, round($vv - min($vv, (float)$v['pago']), 2));
    if ($k >= 0) { $vis[$k]['vids'][] = (int)$v['id']; $vis[$k]['valor'] = round($vis[$k]['valor'] + $vv, 2); $vis[$k]['falta'] = round($vis[$k]['falta'] + $falta, 2); if (count($vis[$k]['itens']) < 4) $vis[$k]['itens'] = array_values(array_unique(array_merge($vis[$k]['itens'], [nomeDaVenda($v)]))); continue; }
    $sem[] = ['vids' => [(int)$v['id']], 'id' => (int)$v['id'], 'crianca' => (int)$v['crianca_id'], 'nome' => (string)($c['nome'] ?? ''), 'resp' => (string)($c['resp'] ?? ''), 'quando' => (string)$v['quando'], 'valor' => $vv, 'falta' => $falta, 'item' => nomeDaVenda($v), 'uni' => $v['unidade_id'] === null ? null : (int)$v['unidade_id']]; }
  $q = $db->prepare("SELECT v.id, v.crianca_id, v.alug, v.servico, v.qtd, v.alug_min, v.quando, v.voltou, v.valor, v.unidade_id, c.nome, c.resp FROM vendas v JOIN criancas c ON c.id = v.crianca_id WHERE v.alug != '' AND v.voltou IS NOT NULL AND v.voltou >= ? AND v.voltou < ? ORDER BY v.voltou DESC, v.id LIMIT 400"); $q->execute([$hoje, $fim]);
  foreach ($q->fetchAll() as $v) { $k = (int)$v['crianca_id'] . '|' . $v['alug'] . '|' . $v['voltou'];
    if (!isset($dev[$k])) $dev[$k] = ['crianca' => (int)$v['crianca_id'], 'nome' => (string)$v['nome'], 'resp' => (string)$v['resp'], 's' => (string)$v['alug'], 'n' => (string)$v['servico'], 'q' => 0, 'saiu' => (string)$v['quando'], 'voltou' => (string)$v['voltou'], 'valor' => 0.0, 'uni' => $v['unidade_id'] === null ? null : (int)$v['unidade_id']];
    if ((float)$v['valor'] > 0.004) $dev[$k]['vids'][] = (int)$v['id'];
    $dev[$k]['q'] += (int)$v['alug_min'] > 0 ? ($dev[$k]['q'] ? 0 : 1) : max(1, (int)$v['qtd']); $dev[$k]['valor'] = round($dev[$k]['valor'] + (float)$v['valor'], 2); if ((string)$v['quando'] < $dev[$k]['saiu']) $dev[$k]['saiu'] = (string)$v['quando']; }
  if ($dev) { $q = $db->prepare("SELECT id, crianca_id, voltou, valor FROM vendas WHERE quando >= ? AND alug = '' AND voltou IS NOT NULL AND voltou >= ? AND itens LIKE 'tempo a mais%'"); $q->execute([date('Y-m-d', strtotime($hoje) - 86400) . ' 00:00:00', $hoje]);   // (o tempo a mais nasce na devolução: anda pelo índice da hora da venda)   // o tempo a mais cobrado na devolução (é uma venda à parte, que já nasce devolvida)
    foreach ($q->fetchAll() as $t) foreach ($dev as $k => $d) if ($d['crianca'] === (int)$t['crianca_id'] && $d['voltou'] === (string)$t['voltou']) { $dev[$k]['valor'] = round($d['valor'] + (float)$t['valor'], 2); if ((float)$t['valor'] > 0.004) $dev[$k]['vids'][] = (int)$t['id']; break; } }
  if ($so > 0) { $vis = array_values(array_filter($vis, fn($x) => (int)($x['uni'] ?? 0) === $so)); $dev = array_filter($dev, fn($x) => (int)($x['uni'] ?? 0) === $so); $sem = array_filter($sem, fn($x) => (int)($x['uni'] ?? 0) === $so); }   /* 2.15 (o dono: "cada unidade só pode ver sua unidade") */
  /* 2.60: a NF de cada linha (o botão NF dos Finalizados: vermelho com nota a emitir, verde com tudo emitido). n = as compras com valor da
     linha, e = quantas têm a nota emitida, v = quais (a tela abre a nota delas) */
  $todas = []; foreach ([$vis, $dev, $sem] as $G) foreach ($G as $x) foreach ($x['vids'] ?? [] as $i) $todas[(int)$i] = 1; $emit = [];
  if ($todas) foreach ($db->query("SELECT DISTINCT venda_id FROM pedidos WHERE tipo = 'nf' AND status = 'usado' AND venda_id IN (" . implode(',', array_keys($todas)) . ")")->fetchAll(PDO::FETCH_COLUMN) as $i) $emit[(int)$i] = 1;
  $nf = function (array $x) use ($emit): array { $L = array_values(array_unique(array_map('intval', $x['vids'] ?? []))); unset($x['vids']); $x['nf'] = ['n' => count($L), 'e' => count(array_filter($L, fn($i) => isset($emit[$i]))), 'v' => $L]; return $x; };
  $vis = array_map($nf, $vis); $dev = array_map($nf, $dev); $sem = array_map($nf, $sem);
  return ['visitas' => $vis, 'devolvidos' => array_values($dev), 'semEntrada' => array_values($sem)];
}
/* 2.43 (o dono: "eu quero uma lixeirazinha vermelha pra mim quando eu quiser excluir um pedido que às vezes lançou errado ... só pro gestor"):
   nos Finalizados do dia, o gestor exclui o que foi lançado errado. A saída sai com o que foi vendido nela para a criança (as horas usadas voltam
   ao saldo, as horas compradas nela saem, o que foi pago sai do caixa); o carrinho devolvido sai com o valor; a venda sem entrada é cancelada.
   Fica nos cancelamentos e na linha do dia, com o motivo */
function excluirFinalizado(array $in, array $u): array {
  if (($u['papel'] ?? '') !== 'gestor') falha('Só o gestor exclui.', 403);
  $db = db(); $motivo = mb_substr(maiusculo(trim((string)($in['motivo'] ?? ''))), 0, 300); if (mb_strlen($motivo) < 5) falha('Escreva o motivo: por que está excluindo.');
  $tipo = (string)($in['tipo'] ?? '');
  if ($tipo === 'venda') { $r = cancelarVenda((int)($in['id'] ?? 0), $u, $motivo, null, '', true); return $r; }
  if ($tipo === 'aluguel') {
    $c = crianca((int)($in['crianca'] ?? 0)); if (!$c) falha('Cliente não encontrado.', 404); $s = (string)($in['s'] ?? ''); $volt = (string)($in['voltou'] ?? '');
    $q = $db->prepare("SELECT * FROM vendas WHERE crianca_id = ? AND voltou = ? AND (alug = ? OR (alug = '' AND itens LIKE 'tempo a mais%')) ORDER BY id"); $q->execute([(int)$c['id'], $volt, $s]); $V = $q->fetchAll();
    $A = array_values(array_filter($V, fn($v) => (string)$v['alug'] === $s)); if (!$A) falha('Este carrinho não foi encontrado (já foi excluído?).', 404);
    $nome = (string)($A[0]['servico'] ?: 'CARRINHO'); $saiu = (string)min(array_column($A, 'quando'));
    return excluirVendas($c, $V, $u, $motivo, 'aluguel', $saiu, 'Devolução do ' . $nome . ' (saiu ' . substr($saiu, 11, 5) . ', voltou ' . substr($volt, 11, 5) . ') excluída', null, $nome);
  }
  if ($tipo === 'visita') {
    $q = $db->prepare('SELECT * FROM visitas WHERE id = ?'); $q->execute([(int)($in['id'] ?? 0)]); $x = $q->fetch(); $q->closeCursor(); if (!$x) falha('Esta saída não foi encontrada (já foi excluída?).', 404);
    $c = crianca((int)$x['crianca_id']); if (!$c) falha('Cliente não encontrado.', 404);
    $ini = substr((string)$x['entrou'], 0, 10) . ' 00:00:00'; $deSaida = false;   // a conta da visita (como nos Finalizados): do começo do dia da entrada, ou da saída anterior, até a saída
    $q = $db->prepare('SELECT saiu FROM visitas WHERE crianca_id = ? AND saiu >= ? AND saiu < ? ORDER BY saiu'); $q->execute([(int)$c['id'], $ini, (string)$x['saiu']]); foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $sv) { $ini = (string)$sv; $deSaida = true; }
    $q = $db->prepare("SELECT * FROM vendas WHERE crianca_id = ? AND quando " . ($deSaida ? '>' : '>=') . " ? AND quando <= ? AND servico != ? AND alug = '' AND itens NOT LIKE 'tempo a mais%' ORDER BY id"); $q->execute([(int)$c['id'], $ini, (string)$x['saiu'], SERV_IMPORTADO]);
    return excluirVendas($c, $q->fetchAll(), $u, $motivo, 'excluida', (string)$x['entrou'], 'Saída das ' . substr((string)$x['saiu'], 11, 5) . ' (entrou ' . substr((string)$x['entrou'], 11, 5) . ') excluída', $x);
  }
  falha('Escolha o que excluir.');
}
function excluirVendas(array $c, array $V, array $u, string $motivo, string $tipoC, string $desde, string $txt, ?array $vis = null, string $nomeA = ''): array {
  $db = db(); $agora = agora(); $mins = array_sum(array_map(fn($v) => (int)$v['minutos'], $V)); $deb = $vis ? (int)$vis['debitado_min'] : 0;
  if ($mins > 0 && (int)$c['pacote_min'] - $mins < (int)$c['usado_min'] - $deb) falha('As horas compradas aqui já foram usadas em outra visita. Exclua aquela primeiro, ou acerte as horas no Banco de horas.');
  $ids = array_map(fn($v) => (int)$v['id'], $V); $pago = $ids ? pagoPorForma($ids) : []; $valor = round(array_sum(array_map(fn($v) => (float)$v['valor'], $V)), 2);
  $itens = array_map(fn($v) => ['n' => nomeDaVenda($v), 'm' => (int)$v['minutos'], 'v' => round((float)$v['valor'], 2), 'tipo' => $tipoC === 'aluguel' ? 'aluguel' : 'venda'] + (($p = pagoPorForma([(int)$v['id']])) ? ['dev' => textoPagamento($p, true)] : []), $V);
  if ($tipoC === 'aluguel' && $itens) $itens[0]['n'] = $nomeA ?: $itens[0]['n'];
  $db->beginTransaction();
  try {
    estornarPagosAntes($V);   // (o que foi pago em outro dia sai do caixa de hoje, em "Devolvido hoje")
    foreach ($V as $v) {
      if ((int)$v['minutos'] > 0) $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$v['minutos'], (int)$c['id']]);
      $db->prepare("UPDATE pedidos SET resultado = 'A VENDA FOI EXCLUÍDA' WHERE venda_id = ? AND status IN ('pendente', 'liberado', 'usado')")->execute([(int)$v['id']]);
      $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([(int)$v['id']]); devolverEstoque($v);   // o pagamento sai junto (o gatilho da tabela)
      if (!empty($v['registro'])) $db->prepare("UPDATE registros SET texto = texto || ' · excluída' WHERE id = ? AND crianca_id = ? AND texto NOT LIKE '% · excluída'")->execute([(int)$v['registro'], (int)$c['id']]); }
    if ($vis) { $db->prepare('UPDATE criancas SET usado_min = MAX(0, usado_min - ?) WHERE id = ?')->execute([$deb, (int)$c['id']]); $db->prepare('DELETE FROM visitas WHERE id = ?')->execute([(int)$vis['id']]);
      $db->prepare("UPDATE registros SET texto = texto || ' · excluída' WHERE crianca_id = ? AND tipo = 'sai' AND quando = ? AND texto NOT LIKE '% · excluída'")->execute([(int)$c['id'], (string)$vis['saiu']]); }
    $min = $vis ? max(0, intdiv(strtotime((string)$vis['saiu']) - strtotime((string)$vis['entrou']), 60)) : 0;
    $db->prepare("INSERT INTO cancelamentos (crianca_id, nome, resp, entrou, quando, minutos, valor, itens, motivo, por, por_nome, papel, fora, tipo, atendente, unidade_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, '', ?)")
      ->execute([(int)$c['id'], $c['nome'], $c['resp'], $desde, $agora, $min, $valor, json_encode($itens, JSON_UNESCAPED_UNICODE), $motivo, (int)$u['id'], $u['nome'], $u['papel'], $tipoC, $vis && $vis['unidade_id'] !== null ? (int)$vis['unidade_id'] : (($V[0]['unidade_id'] ?? null) !== null ? (int)$V[0]['unidade_id'] : unidadeDoPedido())]);
    $id = (int)$db->lastInsertId();
    $reg = registrar('canc', (int)$c['id'], $c['nome'], $txt . ($V ? ' · ' . implode(', ', array_map(fn($i) => $i['n'] . ' ' . dinheiro($i['v']), array_slice($itens, 0, 4))) : '') . ($deb > 0 ? ' · ' . hm($deb) . ' voltam ao saldo' : '') . ($mins > 0 ? ' · saem ' . hm($mins) . ' compradas' : '')
      . ($pago ? ' · devolver ao cliente: ' . textoPagamento($pago, true) : '') . ' · Motivo: ' . $motivo . ' · por ' . primeiroNome((string)$u['nome']) . ' (gestor)', $agora);
    $db->commit();
  } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  if ($pago) acertarCupons();
  return ['cancelamento' => $id, 'registro' => $reg, 'valor' => $valor, 'devolver' => $pago ? textoPagamento($pago, true) : '', 'horas' => $deb, 'saem' => $mins];
}
/* ---------- 0.93: o cupom fiscal de cada pagamento ---------- */
// "Toda vez que o pagamento é feito, precisa sair o cupom fiscal." O sistema ainda não fala com a máquina fiscal (isso depende da máquina e do
// tipo de cupom da loja); o que ele já faz é não deixar nenhum pagamento sem cupom: cada pagamento registrado vira um cupom "pendente" (a emitir),
// com os itens, o total, as formas de pagamento e o CPF do responsável; a equipe emite na máquina e marca "emitido" (com o número, se quiser).
// Os estados: pendente → emitido | sem (sem cupom, com o motivo: só gestor e supervisor); emitido → cancelar (a venda foi desfeita: cancelar na máquina) → cancelado.
// A emissão automática entra por cima disto: em vez de a equipe marcar, o emissor muda o estado e grava o número.
function comCupom(): bool { return (regra()['cupom'] ?? 'nao') === 'sim'; }
function cupom(int $id): ?array { $q = db()->prepare('SELECT * FROM cupons WHERE id = ?'); $q->execute([$id]); return $q->fetch() ?: null; }
// O conteúdo do cupom sai dos pagamentos ligados a ele: os itens (cada venda, com o que foi pago dela), o total e as formas. Devolve o total
function refazerCupom(int $id): float {
  $db = db(); $itens = []; $total = 0.0; $formas = [];
  $q = $db->prepare('SELECT v.id, v.servico, v.itens, v.minutos, v.produto_id, v.auto, v.alug, v.voucher, v.festa_id, SUM(p.valor) AS pago FROM pagamentos p JOIN vendas v ON v.id = p.venda_id WHERE p.cupom_id = ? GROUP BY v.id ORDER BY v.id'); $q->execute([$id]);
  foreach ($q->fetchAll() as $v) { $itens[] = ['n' => nomeNoPapel($v), 'v' => round((float)$v['pago'], 2), 'venda' => (int)$v['id'], 't' => tipoNoPapel($v)]; $total += (float)$v['pago']; }
  $itens = array_map(function ($x) { unset($x['t']); return $x; }, soPacoteNoPapel($itens));   /* 1.70: o produto vendido com o pacote vai dentro da linha do pacote (o total é o mesmo) */
  $q = $db->prepare('SELECT forma, SUM(valor) AS t FROM pagamentos WHERE cupom_id = ? GROUP BY forma ORDER BY MIN(id)'); $q->execute([$id]);
  foreach ($q->fetchAll() as $l) $formas[] = ['f' => (string)$l['forma'], 'v' => round((float)$l['t'], 2)];
  $db->prepare('UPDATE cupons SET valor = ?, itens = ?, formas = ? WHERE id = ?')->execute([round($total, 2), json_encode($itens, JSON_UNESCAPED_UNICODE), json_encode($formas, JSON_UNESCAPED_UNICODE), $id]);
  return round($total, 2);
}
// Os pagamentos gravados depois de $depoisDe (o maior id que havia antes de gravar) viram um cupom a emitir. Devolve o id do cupom (0 = módulo desligado, ou nada foi pago)
function criarCupom(array $c, string $origem, int $depoisDe, ?array $u): int {
  if (!comCupom()) return 0; $db = db();
  $q = $db->prepare('SELECT id FROM pagamentos WHERE id > ? AND crianca_id = ? AND cupom_id IS NULL'); $q->execute([$depoisDe, (int)$c['id']]);
  $ids = array_map('intval', array_column($q->fetchAll(), 'id')); if (!$ids) return 0;
  $db->prepare('INSERT INTO cupons (crianca_id, nome, resp, cpf, quando, origem, por) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([(int)$c['id'], (string)$c['nome'], (string)$c['resp'], (string)($c['cpf'] ?? ''), agora(), $origem === 'saida' ? 'saida' : 'venda', $u ? primeiroNome((string)$u['nome']) : '']);
  $id = (int)$db->lastInsertId(); $db->exec('UPDATE pagamentos SET cupom_id = ' . $id . ' WHERE id IN (' . implode(',', $ids) . ')');
  refazerCupom($id); return $id;
}
// Confere os cupons com os pagamentos: se a venda foi desfeita ou cancelada (ou a cobrança por atraso foi desfeita), o cupom acompanha.
// Ainda não emitido: some, ou fica só com o que continuou pago. Já emitido: vira "cancelar" (o cupom tem de ser cancelado na máquina) e o que
// continuou pago volta como um cupom novo, a emitir. É chamada a cada leitura e a cada pulso das telas (é barata: quase sempre não acha nada).
function acertarCupons(): int {
  $db = db(); $desde = date('Y-m-d H:i:s', time() - 7 * 86400);
  $sql = "SELECT c.id FROM cupons c WHERE c.status IN ('pendente', 'emitido') AND c.quando > ? AND ABS(c.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.cupom_id = c.id), 0)) >= 0.005";
  $q = $db->prepare($sql . ' LIMIT 1'); $q->execute([$desde]); if (!$q->fetchColumn()) return 0;
  $n = 0; $db->exec('BEGIN IMMEDIATE');   // uma tela de cada vez: duas conferindo no mesmo instante não geram a linha (nem o cupom novo) em dobro
  try {
    $q = $db->prepare($sql); $q->execute([$desde]); $soma = $db->prepare('SELECT COALESCE(SUM(valor), 0) FROM pagamentos WHERE cupom_id = ?');
    foreach (array_map('intval', array_column($q->fetchAll(), 'id')) as $id) {
      $k = cupom($id); if (!$k) continue; $soma->execute([$id]); $pago = round((float)$soma->fetchColumn(), 2); $n++;
      if ($k['status'] === 'pendente') { if ($pago < 0.005) $db->prepare('DELETE FROM cupons WHERE id = ?')->execute([$id]); else refazerCupom($id); continue; }
      $parte = $pago >= 0.005;   // só uma parte da venda foi desfeita
      $db->prepare("UPDATE cupons SET status = 'cancelar', motivo = ? WHERE id = ?")->execute([$parte ? 'PARTE DA VENDA FOI DESFEITA' : 'A VENDA FOI DESFEITA', $id]);
      registrar('cup', (int)$k['crianca_id'], (string)$k['nome'], 'Cupom fiscal' . ($k['numero'] !== '' ? ' nº ' . $k['numero'] : '') . ' de ' . dinheiro((float)$k['valor']) . ' a cancelar na máquina: ' . ($parte ? 'parte da venda foi desfeita' : 'a venda foi desfeita'));
      if ($parte) {
        $db->prepare('INSERT INTO cupons (crianca_id, nome, resp, cpf, quando, origem, por) VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([(int)$k['crianca_id'], (string)$k['nome'], (string)$k['resp'], (string)$k['cpf'], agora(), (string)$k['origem'], (string)$k['por']]);
        $novo = (int)$db->lastInsertId(); $db->prepare('UPDATE pagamentos SET cupom_id = ? WHERE cupom_id = ?')->execute([$novo, $id]); refazerCupom($novo);
      }
    }
    $db->exec('COMMIT');
  } catch (Throwable $e) { try { $db->exec('ROLLBACK'); } catch (Throwable $e2) { } throw $e; }
  return $n;
}
function cupomPublico(array $k): array {
  $itens = json_decode((string)$k['itens'], true); $formas = json_decode((string)$k['formas'], true);
  return ['id' => (int)$k['id'], 'crianca' => (int)$k['crianca_id'], 'nome' => (string)$k['nome'], 'resp' => (string)$k['resp'], 'cpf' => (string)$k['cpf'], 'quando' => $k['quando'], 'origem' => (string)$k['origem'], 'valor' => (float)$k['valor'],
    'itens' => is_array($itens) ? $itens : [], 'formas' => is_array($formas) ? $formas : [], 'status' => (string)$k['status'], 'numero' => (string)$k['numero'], 'motivo' => (string)$k['motivo'], 'por' => (string)$k['por'], 'em' => $k['resolvido_em'], 'quem' => (string)$k['resolvido_por']];
}
// o que a tela recebe: os cupons em aberto (a emitir e a cancelar, de qualquer dia) e os das últimas 24 h
function cuponsDaTela(): array {
  if (!comCupom()) return [];
  $q = db()->prepare("SELECT * FROM cupons WHERE status IN ('pendente', 'cancelar') OR quando > ? ORDER BY id DESC LIMIT 300"); $q->execute([date('Y-m-d H:i:s', time() - 86400)]);
  return array_map('cupomPublico', $q->fetchAll());
}
// (para o pulso) muda quando um cupom nasce, some, é marcado ou tem a marcação desfeita
function pulsoCupons(): string {
  $db = db(); $peso = "COALESCE(SUM(CASE status WHEN 'pendente' THEN 1 WHEN 'emitido' THEN 7 WHEN 'cancelar' THEN 41 WHEN 'cancelado' THEN 239 ELSE 1373 END), 0)";
  $q = $db->prepare("SELECT COUNT(*) || ':' || COALESCE(MAX(id), 0) || ':' || $peso || ':' || COALESCE(SUM(LENGTH(numero)), 0) || ':' || COALESCE(SUM(valor), 0) || ':' || COALESCE(MAX(resolvido_em), '') FROM cupons WHERE quando > ?"); $q->execute([date('Y-m-d H:i:s', time() - 86400)]);
  return $q->fetchColumn() . '|' . $db->query("SELECT COUNT(*) || ':' || COALESCE(MAX(id), 0) || ':' || $peso FROM cupons WHERE status IN ('pendente', 'cancelar')")->fetchColumn();
}

/* ---------- o que sai da loja e volta: carrinho de bebê, fusquinha ---------- */
// O serviço marcado como "sai e volta" (regra.servicos[].volta) fica na lista da tela inicial desde a venda até alguém marcar que voltou.
// Uma linha por criança e serviço: vender mais um período do mesmo carrinho soma o tempo na mesma linha; mais unidades (valor único) somam a quantidade.
// Devolve [{ id, crianca, s (o serviço), n (o nome), q (quantos), min (tempo comprado; 0 = sem hora para voltar), ini (quando saiu), vendas }]
// 1.20: o que sai da loja e é cobrado por período (o fusquinha) não é mais vendido por tempo. A equipe só registra a saída: a venda nasce com
// valor zero e o detalhe abaixo, e a conta fecha na devolução, pelo tempo que ficou fora (como a recreação avulsa fecha na saída). O carrinho
// de valor único (o de bebê) continua igual. O que foi vendido por tempo antes da 1.20 e ainda está fora segue a regra antiga (alug_min > 0).
const ITENS_ALUG_ABERTO = 'acerta na devolução';
function servicoPorId(string $id): ?array { foreach ((array)regra()['servicos'] as $s) if (is_array($s) && (string)($s['id'] ?? '') === $id) return $s; return null; }
// (em Ajustes, o serviço pode voltar a ser vendido por tempo comprado, como era até a 1.19: fica com a marca porTempo)
const ALUG_MAX = 4;   // 1.22: quantos carrinhos (de bebê ou fusquinhas) saem de uma vez para a mesma criança
function alugEmAberto(?array $sv): bool { return $sv !== null && !empty($sv['volta']) && empty($sv['banco']) && ($sv['modo'] ?? 'fixo') === 'periodo' && empty($sv['porTempo']); }
function alugueis(?int $criancaId = null, ?string $servico = null): array {
  $sql = "SELECT v.id, v.crianca_id, v.alug, v.alug_min, v.qtd, v.servico, v.itens, v.quando, v.unidade_id, v.vendedora, v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) AS falta FROM vendas v JOIN criancas c ON c.id = v.crianca_id AND c.ativo = 1 WHERE v.alug != '' AND v.voltou IS NULL";
  $arg = []; if ($criancaId !== null) { $sql .= ' AND v.crianca_id = ?'; $arg[] = $criancaId; } if ($servico !== null) { $sql .= ' AND v.alug = ?'; $arg[] = $servico; }
  $q = db()->prepare($sql . ' ORDER BY v.id'); $q->execute($arg); $g = [];
  foreach ($q->fetchAll() as $v) {
    $k = (int)$v['crianca_id'] . '-' . $v['alug']; $m = (int)$v['alug_min'];
    if (!isset($g[$k])) $g[$k] = ['id' => $k, 'crianca' => (int)$v['crianca_id'], 's' => (string)$v['alug'], 'n' => (string)$v['servico'], 'q' => 0, 'min' => 0, 'ini' => $v['quando'], 'vendas' => [], 'vend' => '', 'pend' => 0.0, 'uni' => $v['unidade_id'] === null ? null : (int)$v['unidade_id']];   // 1.06: uni = a unidade de onde saiu
    $g[$k]['pend'] = round($g[$k]['pend'] + max(0.0, (float)$v['falta']), 2);   // 1.02: o que ainda não foi pago (o cliente ficou de pagar na devolução)
    $g[$k]['min'] += $m; $g[$k]['q'] = $m > 0 ? max(1, $g[$k]['q']) : $g[$k]['q'] + max(1, (int)$v['qtd']); $g[$k]['vendas'][] = (int)$v['id']; if ($g[$k]['vend'] === '' && trim((string)($v['vendedora'] ?? '')) !== '') $g[$k]['vend'] = trim((string)$v['vendedora']);   /* 2.22: quem vendeu o carrinho (o cupom da devolução) */
    if ($m <= 0 && (string)$v['itens'] === ITENS_ALUG_ABERTO) $g[$k]['ab'] = true;   // 1.20: a saída nasceu em aberto (sem valor: a conta fecha na devolução)
  }
  // 1.20: em aberto é da própria saída, e não de como o serviço está agora em Ajustes (o gestor pode trocar "quando a conta fecha" com um fusquinha fora).
  // O valor e o tamanho do período saem do serviço na hora da devolução: ele precisa continuar cobrado a cada período
  foreach ($g as $k => $x) { $sv = servicoPorId($x['s']); $g[$k]['aberto'] = !empty($x['ab']) && $x['min'] <= 0 && $sv !== null && ($sv['modo'] ?? 'fixo') === 'periodo'; unset($g[$k]['ab']); }   // (1.22: em aberto, q = quantos carrinhos desta saída ainda estão fora)
  /* 2.30 (o dono: "no carrinho bebê ... a gente cobra na entrada e na saída ele tem que aparecer a forma de pagamento, mas já aparecer selecionada"):
     o que já foi pago de cada carrinho, por forma */
  $ids = []; foreach ($g as $x) foreach ($x['vendas'] as $vid) $ids[(int)$vid] = 1; $por = [];
  if ($ids) foreach (db()->query('SELECT venda_id, forma, valor FROM pagamentos WHERE venda_id IN (' . implode(',', array_keys($ids)) . ')')->fetchAll() as $p) $por[(int)$p['venda_id']][] = $p;
  foreach ($g as $k => $x) { $f = []; foreach ($x['vendas'] as $vid) foreach ($por[(int)$vid] ?? [] as $p) $f[(string)$p['forma']] = round(($f[(string)$p['forma']] ?? 0) + (float)$p['valor'], 2); $g[$k]['pagos'] = (object)$f; }
  return array_values($g);
}
// "Devolveu": o que estava fora volta para a loja e sai da lista. Fica anotado em Hoje na recepção, com quanto tempo ficou fora e quem marcou.
// Nada é cobrado aqui: se passou do tempo comprado, a linha diz quanto passou e a equipe vende o período a mais no atendimento
// 1.00: o que há para receber na devolução do que foi alugado por tempo (o fusquinha): os períodos inteiros que passaram do tempo comprado,
// com a mesma tolerância da recreação (e o relógio para na hora em que a loja fecha, como o da visita). O que tem valor único
// (o carrinho de bebê) não tem tempo a mais.
// 1.20: em aberto (o fusquinha, que só "sai" e acerta na devolução), a conta é o tempo todo: o primeiro período conta sempre, e o seguinte
// só depois da tolerância (com 30 min e tolerância de 9: até 39:00 é um período; passou disso, dois). Vem marcada com 'aberto' => true.
function aluguelExtra(array $a, ?int $quantos = null): array {   // $quantos (1.22): quantos carrinhos voltam agora (em aberto); sem dizer, todos os que estão fora
  $sv = servicoPorId((string)$a['s']);
  $ini = strtotime((string)$a['ini']); $f = (int)(regra()['fecha'] ?? 22); $teto = $ini + 43200;
  $fecha = strtotime(date('Y-m-d', $ini) . ($f >= 24 ? ' 23:59:59' : sprintf(' %02d:00:00', max(0, $f))));
  if ($fecha > $ini && !ehExemplo((int)$a['crianca'])) $teto = min($teto, $fecha);
  $ficou = max(0, (min(agoraTs(), $teto) - $ini) / 60); $r = ['n' => 0, 'min' => 0, 'valor' => 0.0, 'per' => 0, 'un' => 0.0];
  if (!$sv || ($sv['modo'] ?? 'fixo') !== 'periodo') return $r;
  $per = max(1, (int)($sv['min'] ?? 30)); $tol = max(0, (int)regra()['tolerancia']);
  if ((int)$a['min'] <= 0) { if (empty($a['aberto'])) return $r;
    $n = max(1, (int)ceil(($ficou - $tol) / $per)); $de = max(1, (int)($a['q'] ?? 1)); $k = $quantos === null ? $de : max(1, min($de, $quantos));
    return ['n' => $n, 'min' => $n * $per, 'valor' => round($n * (float)$sv['v'] * $k, 2), 'per' => $per, 'un' => (float)$sv['v'], 'aberto' => true, 'q' => $k, 'de' => $de]; }
  $n = max(0, max(1, (int)ceil(($ficou - $tol) / $per)) - (int)ceil((int)$a['min'] / $per));
  return ['n' => $n, 'min' => $n * $per, 'valor' => round($n * (float)$sv['v'], 2), 'per' => $per, 'un' => (float)$sv['v']];
}
// "Devolveu": o que estava fora volta para a loja e sai da lista. Fica anotado em Hoje na recepção, com quanto tempo ficou fora e quem marcou.
// 1.00: se passou do tempo comprado, o período a mais é cobrado aqui e a devolução só fecha com a forma de pagamento.
function devolverAluguel(array $c, string $servico, array $u, array $pg = [], ?int $quantos = null): array {
  $db = db(); $g = alugueis((int)$c['id'], $servico); if (!$g) falha('Isso já foi marcado como devolvido.', 409);
  $a = $g[0]; $agora = agora(); $x = aluguelExtra($a, $quantos); $aberto = !empty($x['aberto']);   // 1.20: em aberto, a conta inteira nasce aqui
  $vids = array_map('intval', $a['vendas']); $de = max(1, (int)$a['q']); if ($aberto) $vids = array_slice($vids, 0, (int)$x['q']);   // 1.22: voltaram só alguns dos que saíram: os primeiros da saída; os outros continuam fora
  $volta = count($vids); $parte = $aberto && $volta < count($a['vendas']); $marcas = implode(',', $vids);
  $pend = $parte ? 0.0 : round((float)($a['pend'] ?? 0), 2); $total = round($x['valor'] + $pend, 2);   // 1.02: o que ficou a pagar do carrinho entra na conta da devolução
  if ($total > 0) {
    if (!$pg) falha($aberto ? $a['n'] . ': falta receber ' . dinheiro($total) . ' (' . dur($x['min']) . '). Escolha a forma de pagamento.'
      : ($x['valor'] > 0 ? $a['n'] . ' passou do tempo comprado: falta receber ' . dinheiro($total) . ' (' . dur($x['min']) . ' a mais' . ($pend > 0 ? ', mais o que não foi pago' : '') . '). Escolha a forma de pagamento.'
      : $a['n'] . ' ainda não foi pago: falta receber ' . dinheiro($total) . '. Escolha a forma de pagamento.'), 409);
    if (abs(array_sum($pg) - $total) >= 0.01) falha('O pagamento (' . dinheiro((float)array_sum($pg)) . ') não fecha com o valor a receber, que é ' . dinheiro($total) . '. Feche e abra a devolução de novo.', 409);
  }
  $qv = $db->prepare("UPDATE vendas SET voltou = ? WHERE id IN ($marcas) AND voltou IS NULL"); $qv->execute([$agora]);
  if (!$qv->rowCount()) falha('Isso já foi marcado como devolvido.', 409);   // 1.20: outro aparelho devolveu no mesmo instante: este não recebe de novo
  $ficou = max(0, (int)floor((agoraTs() - strtotime((string)$a['ini'])) / 60)); $passou = $a['min'] > 0 ? max(0, $ficou - $a['min']) : 0;
  $reg = registrar('dev', (int)$c['id'], $c['nome'], ($parte ? $volta . ' de ' . $de . ' × ' : ($a['q'] > 1 ? $a['q'] . ' × ' : '')) . $a['n'] . ' · devolvido · ficou ' . dur($ficou)
    . ($aberto ? ' · ' . ($volta > 1 ? $volta . ' × ' : '') . dur($x['min']) . ': ' . dinheiro($x['valor']) : ($a['min'] > 0 ? ' · tempo comprado: ' . dur($a['min']) . ($passou > 0 ? ' (passou ' . ($passou < 60 ? $passou . ' min' : dur($passou)) . ')' : '') : '')) . ' · por ' . primeiroNome($u['nome']), $agora);
  $r = ['vendas' => $vids, 'registro' => $reg, 'ficou' => $ficou, 'passou' => $passou, 'voltou' => $agora, 'nome' => $a['n'], 'q' => $aberto ? $volta : $a['q'], 'ficam' => $parte ? $de - $volta : 0, 'extra' => 0, 'registros' => [], 'pago' => '', 'valor' => 0.0, 'pend' => $pend];
  if ($total > 0) {
    $antesPg = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM pagamentos')->fetchColumn(); $ids = $vids;
    if ($aberto) { // 1.20: a venda que marcou a saída ganha o valor e o tempo cobrado (fica uma linha só no histórico do cliente); o Desfazer devolve o zero. 1.22: cada carrinho que voltou, a sua
      if ($x['valor'] > 0) { $cada = round($x['n'] * $x['un'], 2); foreach ($ids as $vid) $db->prepare('UPDATE vendas SET valor = ?, itens = ? WHERE id = ?')->execute([$cada, dur($x['min']), $vid]);
        $r['aberto'] = ['venda' => $ids[0], 'vendas' => $ids, 'min' => $x['min'], 'valor' => $x['valor']]; $r['minutos'] = $x['min']; }
    } elseif ($x['valor'] > 0) { // o tempo a mais vira uma venda (que já nasce devolvida, para não voltar para a lista) e o pagamento fica anotado nela
      $v = fazerVenda($c, 0, $x['valor'], (int)$u['id'], 'tempo a mais · ' . dur($x['min']), (string)$a['n']);
      $db->prepare("UPDATE vendas SET alug = '', alug_min = 0, voltou = ? WHERE id = ?")->execute([$agora, $v['venda']]);
      $ids[] = (int)$v['venda']; $r['extra'] = $v['venda']; $r['registros'][] = $v['registro']; $r['minutos'] = $x['min'];
    }
    $pago = gravarPagamento($ids, $pg, (int)$c['id'], (int)$u['id']);   // primeiro o que ficou a pagar do carrinho, depois o tempo a mais
    $r['registros'][] = registrar('pag', (int)$c['id'], $c['nome'], 'Pagamento · ' . dinheiro($total) . ' · ' . textoPagamento($pago ?: $pg));
    $r['pago'] = textoPagamento($pago ?: $pg); $r['valor'] = $total; $r['pag_de'] = $antesPg;
    $r['cupom'] = criarCupom($c, 'venda', $antesPg, $u);   // (o módulo do cupom fiscal, se estiver ligado)
  }
  return $r;
}
// 1.20: a saída do que é cobrado na devolução pode ser cancelada sem cobrar no mesmo prazo de cancelar a entrada (regra.cancelaMin): saiu
// no nome da criança errada, o cliente desistiu. Fica na lista dos cancelamentos, com o motivo, para a supervisão conferir. Passou do prazo,
// ninguém cancela: o período é cobrado (como na entrada).
function prazoCancelarAluguel(array $a): array { // [limite em minutos, segundos desde a saída, ainda está no prazo]
  $lim = max(0, (int)regra()['cancelaMin']); $seg = max(0, agoraTs() - strtotime((string)$a['ini']));
  return [$lim, $seg, $lim > 0 && $seg <= $lim * 60 + 10];   /* 2.42: todo carrinho (não só o que acerta na devolução) */
}
// $desfeita (1.21): desfeita na hora, pelo botão do aviso, por quem não estorna: sai sem olhar o prazo (o botão só existe nos instantes seguintes) e fica registrada
function cancelarAluguel(array $c, string $servico, array $u, string $motivo, bool $desfeita = false): array {
  $db = db(); $g = alugueis((int)$c['id'], $servico); if (!$g) falha('Isso já foi marcado como devolvido.', 409);
  $a = $g[0];   /* 2.42 (o dono: "no carrinho também tem que ter a opção de cancelar com a tolerância"): todo carrinho, no prazo */
  [$lim, $seg, $noPrazo] = prazoCancelarAluguel($a); if ($desfeita) $noPrazo = true;
  if (!$noPrazo) falha($lim > 0 ? 'Já passou de ' . $lim . ' min da saída de ' . $a['n'] . ': não pode mais ser cancelado. O período é cobrado.' : 'A loja não cancela: o período é cobrado.', 403);
  if (mb_strlen($motivo) < 5) falha('Escreva o motivo do cancelamento: a supervisão vai conferir.');
  /* 2.42: o carrinho pago na saída (o de bebê) cancela no prazo devolvendo o valor ao cliente: o pagamento sai do caixa e o cancelamento fica com o valor */
  $vids = array_map('intval', $a['vendas']); $pago = pagoPorForma($vids); $mv = implode(',', $vids ?: [0]); $valor = round((float)$db->query("SELECT COALESCE(SUM(valor), 0) FROM vendas WHERE id IN ($mv)")->fetchColumn(), 2);
  $agora = agora(); $min = intdiv($seg, 60); $nq = ((int)$a['q'] > 1 ? (int)$a['q'] . ' × ' : '') . (string)$a['n']; $itens = [['n' => $nq, 'm' => 0, 'v' => $valor, 'tipo' => 'aluguel'] + ($pago ? ['dev' => textoPagamento($pago, true)] : [])];   // 1.22: "2 × CARRINHO FUSQUINHA"
  $db->beginTransaction();
  try {
    $qr = $db->prepare('SELECT registro FROM vendas WHERE id = ? AND crianca_id = ?');
    foreach (array_map('intval', $a['vendas']) as $vid) { $qr->execute([$vid, (int)$c['id']]); $rg = (int)$qr->fetchColumn();
      $db->prepare('DELETE FROM pagamentos WHERE venda_id = ? AND crianca_id = ?')->execute([$vid, (int)$c['id']]);   // (a saída em aberto não tem pagamento: é só para não sobrar nada)
      $db->prepare('DELETE FROM vendas WHERE id = ? AND crianca_id = ?')->execute([$vid, (int)$c['id']]);
      if ($rg) $db->prepare("UPDATE registros SET texto = texto || ' · cancelada' WHERE id = ? AND crianca_id = ? AND tipo = 'venda' AND texto NOT LIKE '% · cancelada'")->execute([$rg, (int)$c['id']]); }
    $db->prepare("INSERT INTO cancelamentos (crianca_id, nome, resp, entrou, quando, minutos, valor, itens, motivo, por, por_nome, papel, fora, tipo, atendente, unidade_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'aluguel', ?, ?)")
      ->execute([(int)$c['id'], $c['nome'], $c['resp'], (string)$a['ini'], $agora, $min, $valor, json_encode($itens, JSON_UNESCAPED_UNICODE), $motivo, (int)$u['id'], $u['nome'], $u['papel'], atendente(), unidadeDoPedido()]);
    $id = (int)$db->lastInsertId();
    $reg = registrar('canc', (int)$c['id'], $c['nome'], 'Saída das ' . substr((string)$a['ini'], 11, 5) . ' de ' . $nq . ($desfeita ? ' desfeita na hora' : ' cancelada aos ' . ($min < 60 ? $min . ' min' : hm($min))) . ($pago ? ' · devolvido ao cliente: ' . textoPagamento($pago, true) : ' · nada foi cobrado') . ' · Motivo: ' . $motivo . ' · por ' . porQuem($u), $agora);
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
  if ($pago) acertarCupons();   /* 2.42: o cupom fiscal acompanha */
  return ['cancelamento' => $id, 'registro' => $reg, 'minutos' => $min, 'nome' => $nq, 'devolvido' => $pago ? textoPagamento($pago, true) : ''];
}

/* ---------- de onde sai o tempo de quem está no espaço: pacote de horas ou recreação avulsa ---------- */
// A venda é de pacote quando saiu da tabela de pacotes (o nome começa com "Pacote", ou não tem nome de serviço e não é a "hora avulsa" das primeiras versões).
// Recreação por período, voucher e o que o sistema lança sozinho (a recreação avulsa da visita, o tempo além do pacote) são avulsos
function ehVendaDePacote(array $v): bool {
  if ((int)$v['minutos'] <= 0 || !empty($v['auto']) || (string)($v['voucher'] ?? '') !== '') return false;
  $s = trim((string)($v['servico'] ?? ''));
  return $s === '' ? !((int)$v['minutos'] === 60 && (string)($v['itens'] ?? '') !== 'seco') : (bool)preg_match('/^pacote\b/iu', $s);
}
// [criança => 'pacote' | 'avulso'] de quem está no espaço. O tempo já usado consome as compras na ordem em que foram feitas:
// se ainda sobra alguma hora de pacote, a criança está no pacote; senão, está na recreação avulsa
function planosDoEspaco(): array {
  $db = db(); $o = []; $resta = [];
  foreach ($db->query('SELECT id, usado_min, festa_id FROM criancas WHERE entrou IS NOT NULL AND ativo = 1')->fetchAll() as $c) { $o[(int)$c['id']] = !empty($c['festa_id']) ? 'festa' : 'avulso'; $resta[(int)$c['id']] = (int)$c['usado_min']; }   // 1.29: 'festa' = está na festa
  if (!$o) return $o;
  $q = $db->query('SELECT v.crianca_id, v.minutos, v.servico, v.itens, v.auto, v.voucher FROM criancas c CROSS JOIN vendas v ON v.crianca_id = c.id WHERE c.entrou IS NOT NULL AND c.ativo = 1 AND v.minutos > 0 ORDER BY v.id');
  foreach ($q->fetchAll() as $v) { $id = (int)$v['crianca_id']; $m = (int)$v['minutos'];
    if (!isset($o[$id]) || $o[$id] !== 'avulso') continue;
    if ($resta[$id] >= $m) { $resta[$id] -= $m; continue; }   // esta compra já foi usada inteira
    $resta[$id] = 0; if (ehVendaDePacote($v)) $o[$id] = 'pacote';
  }
  foreach ($db->query('SELECT DISTINCT x FROM (SELECT para x FROM fam_horas WHERE desfeito = 0 UNION SELECT de x FROM fam_horas WHERE desfeito = 0)')->fetchAll(PDO::FETCH_COLUMN) as $id) {   /* 2.11: as horas do CPF */
    $id = (int)$id; if (!isset($o[$id]) || $o[$id] === 'festa') continue; $k = crianca($id); if ($k) { $pf = planoPelasHoras($k); if ($pf !== null) $o[$id] = $pf; } }
  return $o;
}
/* ---------- 1.11: a data de nascimento ---------- */
// 'AAAA-MM-DD' de uma data que existe e não é futura (1.17: de qualquer idade, até 120 anos; antes só valia até 17); senão, ''
function idadeDeNascimento(string $nasc, ?string $em = null): int { try { return (int)(new DateTime($nasc))->diff(new DateTime($em ?: date('Y-m-d')))->y; } catch (Throwable $e) { return 0; } }
function nascimentoValido(string $s): string {
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($s), $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return '';
  if ($m[0] > date('Y-m-d') || (int)$m[1] < 1900 || idadeDeNascimento($m[0]) > 120) return '';
  return $m[0];
}
/* ---------- 1.11: "pagar avulso" na saída (não descontar do pacote) ---------- */
// O tempo de brincar comprado para a visita em curso que não é de pacote: a recreação por período, o voucher e a cobrança por atraso
// que já foi paga. É o que a opção "Pagar avulso" tira do que cobra, para não cobrar duas vezes o que já foi comprado hoje.
function tempoAvulsoDaVisita(array $c): int {
  $t = 0;
  foreach (vendasDaVisita($c) as $v) {
    if ((int)$v['minutos'] <= 0 || ehVendaDePacote($v)) continue;
    if (!empty($v['auto']) && faltaDaVenda((int)$v['id']) > 0.004) continue;   // a cobrança por atraso a receber entra na conta por outro lado
    $t += (int)$v['minutos'];
  }
  return $t;
}
// a mesma conta para todos os que estão no espaço, numa consulta só (como pendItensDeTodos): [criança => minutos]
function tempoAvulsoDeTodos(): array {
  $db = db(); $hoje = date('Y-m-d') . ' 00:00:00';
  $min = (string)$db->query('SELECT MIN(entrou) FROM criancas WHERE entrou IS NOT NULL')->fetchColumn(); if ($min === '') return [];
  $piso = substr($min, 0, 10) . ' 00:00:00' < $hoje ? substr($min, 0, 10) . ' 00:00:00' : $hoje;
  $falta = 'v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0)';
  $q = $db->prepare("SELECT v.crianca_id c, v.servico, v.minutos, v.itens, v.auto, v.voucher, $falta f FROM vendas v CROSS JOIN criancas k0 ON k0.id = v.crianca_id WHERE v.quando >= ? AND v.minutos > 0 AND v.servico != ? AND k0.entrou IS NOT NULL
    AND v.quando >= CASE WHEN substr(k0.entrou, 1, 10) < substr(?, 1, 10) THEN substr(k0.entrou, 1, 10) || ' 00:00:00' ELSE ? END
    AND v.quando >= COALESCE((SELECT MAX(x.saiu) FROM visitas x WHERE x.crianca_id = v.crianca_id), '') AND v.quando >= COALESCE((SELECT MAX(k.quando) FROM cancelamentos k WHERE k.crianca_id = v.crianca_id AND k.tipo = ''), '') ORDER BY v.id");
  $q->execute([$piso, SERV_IMPORTADO, $hoje, $hoje]); $o = [];
  foreach ($q->fetchAll() as $l) { if (ehVendaDePacote($l) || (!empty($l['auto']) && (float)$l['f'] > 0.004)) continue; $o[(int)$l['c']] = ($o[(int)$l['c']] ?? 0) + (int)$l['minutos']; }
  return $o;
}
const ITENS_AVULSO_SAIDA = 'avulso na saída: não descontou do pacote';
// A saída com "pagar avulso": o que a visita usaria das horas que o cliente já tinha (o que ela debita, menos as cobranças por atraso
// a receber e menos o tempo já comprado hoje para ela) é vendido agora, pelo valor do período (com o desconto PCD, se for o caso).
// A venda entra no que ficou para pagar na saída; as horas dela são as que a visita debita, e as do cliente ficam como estavam.
// Devolve ['venda', 'registro', 'min', 'valor'], ou null quando a visita não usaria as horas do cliente.
// 1.15: quanto "pagar avulso" venderia agora (os minutos e o valor), sem vender: a saída usa para conferir o total que a tela mostrou
function contaVisitaAvulsa(array $c, string $quando): array {
  $s = servicoTempo(); if (!$c['entrou'] || !$s) return ['min' => 0, 'valor' => 0.0, 'pcd' => false]; $p = periodo();
  $q = db()->prepare('SELECT COALESCE(SUM(minutos), 0) FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = vendas.id)'); $q->execute([(int)$c['id'], (string)$c['entrou']]);
  $deb = min(cobrar(minutosDaVisita($c, strtotime($quando))), restante($c)); $min = max(0, $deb - (int)$q->fetchColumn() - tempoAvulsoDaVisita($c));
  $preco = precoPcd($c, (float)$s['v']); return ['min' => $min, 'valor' => round($min / $p * $preco, 2), 'pcd' => $preco < (float)$s['v']];
}
function venderVisitaAvulsa(array $c, ?array $u): ?array {
  $s = servicoTempo(); if (!$c['entrou'] || !$s) return null; $p = periodo(); $quando = agora();
  if (acertarCobrancas($c, $quando)) $c = crianca((int)$c['id']);
  if (desfazerCobrancasSobrando($c, $quando)['n']) $c = crianca((int)$c['id']);
  $k = contaVisitaAvulsa($c, $quando); $min = (int)$k['min'];
  if ($min <= 0) return null;
  $pcd = (bool)$k['pcd']; $valor = (float)$k['valor'];
  $x = fazerVenda($c, $min, $valor, $u ? (int)$u['id'] : null, ITENS_AVULSO_SAIDA . ($pcd ? ' · PCD ' . pcdDesc() . '%' : ''), (string)$s['n'], false, '', $pcd ? ['pcd' => 1, 'cheio' => round($min / $p * (float)$s['v'], 2)] : []);
  return ['venda' => (int)$x['venda'], 'registro' => (int)$x['registro'], 'min' => $min, 'valor' => $valor];
}

/* ---------- 1.14: reimprimir o cupom ---------- */
// Cada cupom que sai (entrada, saída, devolução, comprovante) fica guardado com as duas vias, do jeito que foi impresso, para a equipe
// reimprimir pela ficha do cliente (o cliente perdeu, a impressora travou). Fica por PAPEL_DIAS dias. As vias chegam como o texto do
// cupom (só marcações simples: o que não for delas é tirado aqui, e a tela limpa de novo antes de mostrar).
const PAPEL_DIAS = 60;
const PAPEL_MARCAS = '<div><p><span><b><i><small><em><strong>';
function papelBlob(array $vias): string {
  $limpas = [];
  foreach (array_slice(array_values($vias), 0, 2) as $v) { $v = trim(strip_tags((string)$v, PAPEL_MARCAS)); if ($v === '') continue; if (strlen($v) > 60000) falha('O cupom é grande demais para guardar.'); $limpas[] = $v; }
  if (!$limpas) falha('O cupom veio vazio.');
  $j = (string)json_encode($limpas, JSON_UNESCAPED_UNICODE); return function_exists('gzcompress') ? gzcompress($j, 6) : $j;
}
function papelIds($l): string { $ids = array_values(array_unique(array_filter(array_map('intval', is_array($l) ? $l : explode(',', (string)$l))))); return implode(',', array_slice($ids, 0, 60)); }
function papelGuardar(array $c, string $tipo, string $titulo, string $resumo, array $vias, ?array $u, $vendas = ''): int {
  $blob = papelBlob($vias);
  $db = db(); $q = $db->prepare('INSERT INTO papeis (crianca_id, quando, tipo, titulo, resumo, vias, por, unidade_id, vendas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
  $q->bindValue(1, (int)$c['id'], PDO::PARAM_INT); $q->bindValue(2, agora()); $q->bindValue(3, $tipo); $q->bindValue(4, $titulo); $q->bindValue(5, $resumo); $q->bindValue(6, $blob, PDO::PARAM_LOB);
  $q->bindValue(7, $u ? (int)$u['id'] : null, $u ? PDO::PARAM_INT : PDO::PARAM_NULL); $un = unidadeDoPedido(); $q->bindValue(8, $un, $un === null ? PDO::PARAM_NULL : PDO::PARAM_INT); $q->bindValue(9, papelIds($vendas));
  $q->execute(); $id = (int)$db->lastInsertId();
  if ($id % 25 === 0) $db->prepare('DELETE FROM papeis WHERE quando < ?')->execute([date('Y-m-d H:i:s', time() - PAPEL_DIAS * 86400)]);   // de vez em quando, os antigos saem
  return $id;
}
function papelVias(array $l): array {
  $b = is_resource($l['vias']) ? (string)stream_get_contents($l['vias']) : (string)$l['vias'];
  if ($b !== '' && $b[0] !== '[') { $x = function_exists('gzuncompress') ? @gzuncompress($b) : false; $b = $x === false ? '[]' : $x; }
  $v = json_decode($b, true); return is_array($v) ? array_values(array_map('strval', $v)) : [];
}
function papelPublico(array $l): array { return ['id' => (int)$l['id'], 'crianca' => (int)$l['crianca_id'], 'quando' => $l['quando'], 'tipo' => (string)$l['tipo'], 'titulo' => (string)$l['titulo'], 'resumo' => (string)$l['resumo'], 'por' => (string)($l['por_nome'] ?? ''), 'estado' => (string)($l['estado'] ?? '')]; }

/* ---------- 2.45: Casa Tom: o gestor corrige o valor de uma venda ou exclui a venda, pelos Vendidos do dia ----------
   O dono: "o da Casa Tom, eu quero um lapisinho e uma lixeirinha, igual a gente fez no outro, pra mim poder o gestor alterar o valor caso o
   valor seja lançado errado". Cada linha dos Vendidos do dia é um cupom guardado; o cupom sabe das vendas dele (2.45). Nos cupons de antes
   da 2.45, as vendas da loja entre o cupom anterior (da mesma unidade) e ele: a venda é gravada antes, e o cupom logo depois. */
function papelCT(int $id): array {
  $q = db()->prepare('SELECT * FROM papeis WHERE id = ?'); $q->execute([$id]); $p = $q->fetch(); $q->closeCursor();
  $b = balcaoCT(false); if (!$p || !$b || (int)$p['crianca_id'] !== (int)$b['id']) falha('Esta venda da Casa Tom não foi encontrada.', 404);
  if ((string)($p['estado'] ?? '') === 'excluida') falha('Esta venda já foi excluída.', 409);
  return $p;
}
function vendasDoPapel(array $p): array {
  $db = db(); $ids = papelIds((string)($p['vendas'] ?? ''));
  if ($ids !== '') { $q = $db->prepare("SELECT * FROM vendas WHERE crianca_id = ? AND id IN ($ids) AND quando <= ? ORDER BY id"); $q->execute([(int)$p['crianca_id'], (string)$p['quando']]); return $q->fetchAll(); }   /* (a venda é sempre de antes do cupom: um número de venda apagada que voltou a ser usado não entra) */
  $uni = $p['unidade_id'] === null ? 0 : (int)$p['unidade_id']; $dia = substr((string)$p['quando'], 0, 10) . ' 00:00:00';
  $q = $db->prepare('SELECT quando FROM papeis WHERE crianca_id = ? AND id < ? AND COALESCE(unidade_id, 0) = ' . $uni . ' AND quando >= ? ORDER BY id DESC LIMIT 1'); $q->execute([(int)$p['crianca_id'], (int)$p['id'], $dia]);   /* (o número no texto: o COALESCE não compara com o parâmetro em texto) */
  $de = $q->fetchColumn(); $q->closeCursor(); $de = $de === false ? '' : (string)$de;
  $q = $db->prepare('SELECT * FROM vendas WHERE crianca_id = ? AND ' . ($de === '' ? 'quando >= ?' : 'quando > ?') . ' AND quando <= ? AND COALESCE(unidade_id, 0) = ' . $uni . ' ORDER BY id');
  $q->execute([(int)$p['crianca_id'], $de === '' ? $dia : $de, (string)$p['quando']]); return $q->fetchAll();
}
function nomeCt(array $v): string { $s = trim((string)($v['servico'] ?? '')); return $s !== '' ? $s : nomeDaVenda($v); }
function vendaCtPublica(array $v): array { return ['id' => (int)$v['id'], 'n' => nomeCt($v), 'q' => max(1, (int)($v['qtd'] ?? 0)), 'v' => round((float)$v['valor'], 2), 'pago' => (object)pagoPorForma([(int)$v['id']]), 'vend' => (string)($v['vendedora'] ?? '')]; }
function motivoGestor(array $in, string $acao): string {
  $m = mb_substr(maiusculo(trim(preg_replace('/\s+/u', ' ', (string)($in['motivo'] ?? '')))), 0, 300); if (mb_strlen($m) < 5) falha('Escreva o motivo: por que está ' . $acao . '.'); return $m;
}
// O lápis: o valor certo de cada item e como o cliente pagou de verdade (a soma das formas = o total). O caixa, as Finanças, as comissões e
// o cupom fiscal acompanham; o pagamento fica no mesmo instante (o caixa do dia é o mesmo) e o cupom guardado é trocado pelo corrigido
function corrigirVendaCT(array $in, array $u): array {
  if (($u['papel'] ?? '') !== 'gestor') falha('Só o gestor corrige o valor da venda.', 403);
  $db = db(); $p = papelCT((int)($in['papel'] ?? 0)); $V = vendasDoPapel($p); if (!$V) falha('Não achei as vendas deste cupom.', 404);
  $motivo = motivoGestor($in, 'corrigindo');
  $por = []; $novo = []; foreach ($V as $v) { $por[(int)$v['id']] = $v; $novo[(int)$v['id']] = (int)round((float)$v['valor'] * 100); }
  foreach ((array)($in['itens'] ?? []) as $x) { if (!is_array($x)) continue; $id = (int)($x['venda'] ?? 0); if (!isset($por[$id])) falha('Este item não é desta venda.');
    $t = trim(str_replace(',', '.', (string)($x['v'] ?? ''))); if ($t === '' || !is_numeric($t) || (float)$t < 0 || (float)$t > 999999) falha('Confira o valor de ' . nomeCt($por[$id]) . '.');
    $novo[$id] = (int)round((float)$t * 100); }
  $antes = (int)round(array_sum(array_map(fn($v) => (float)$v['valor'], $V)) * 100); $total = array_sum($novo);
  if ($total <= 0) falha('O total ficou zerado. Para tirar a venda, use a lixeira.');
  $F = lerPagamento($in['formas'] ?? []); if (!$F) falha('Escreva como foi pago (Pix, dinheiro, crédito ou débito).');
  $fc = array_map(fn($x) => (int)round($x * 100), $F);
  if (array_sum($fc) !== $total) falha('A soma das formas (' . dinheiro(array_sum($fc) / 100) . ') tem que dar o total da venda (' . dinheiro($total / 100) . ').');
  $ids = array_keys($por); $mi = implode(',', $ids); $pago0 = pagoPorForma($ids); $p0 = array_map(fn($x) => (int)round($x * 100), $pago0);
  $mudou = []; foreach ($V as $v) { $a = (int)round((float)$v['valor'] * 100); $b = $novo[(int)$v['id']]; if ($a !== $b) $mudou[] = nomeCt($v) . ' ' . dinheiro($a / 100) . ' → ' . dinheiro($b / 100); }
  $f1 = $fc; ksort($f1); $f0 = $p0; ksort($f0); if (!$mudou && $f1 === $f0) falha('Nada mudou: escreva o valor certo ou a forma certa.');
  $P = $db->query("SELECT * FROM pagamentos WHERE venda_id IN ($mi) ORDER BY id")->fetchAll();
  $quando = $P ? (string)$P[0]['quando'] : (string)$V[0]['quando']; $porP = $P ? $P[0]['por'] : (int)$u['id']; $cup = null; foreach ($P as $x) if (!empty($x['cupom_id'])) { $cup = (int)$x['cupom_id']; break; }
  $uniP = $P ? ($P[0]['unidade_id'] ?? null) : ($V[0]['unidade_id'] ?? null); $b = crianca((int)$p['crianca_id']);
  $vias = is_array($in['vias'] ?? null) && $in['vias'] ? papelBlob((array)$in['vias']) : null; $resumo = mb_substr(trim(strip_tags((string)($in['resumo'] ?? ''))), 0, 140);
  $db->beginTransaction();
  try {
    $up = $db->prepare('UPDATE vendas SET valor = ? WHERE id = ?'); foreach ($novo as $id => $c) $up->execute([$c / 100, $id]);
    $db->exec("DELETE FROM pagamentos WHERE venda_id IN ($mi)");
    $ins = $db->prepare('INSERT INTO pagamentos (venda_id, crianca_id, forma, valor, quando, por, cupom_id, unidade_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'); $fila = $fc;
    foreach ($novo as $id => $falta) foreach ($fila as $f => $resta) { if ($falta <= 0) break; if ($resta <= 0) continue; $x = min($falta, $resta);
      $ins->execute([$id, (int)$p['crianca_id'], $f, $x / 100, $quando, $porP, $cup, $uniP]); $fila[$f] -= $x; $falta -= $x; }
    $reg = registrar('alt', (int)$p['crianca_id'], (string)($b['nome'] ?? 'CASA TOM'), 'Venda da Casa Tom das ' . substr($quando, 11, 5) . ' corrigida' . ($mudou ? ': ' . implode('; ', $mudou) : '')
      . ' · total ' . dinheiro($antes / 100) . ' → ' . dinheiro($total / 100) . ' · pago: ' . textoPagamento($F, true) . ($f1 !== $f0 && $pago0 ? ' (era ' . textoPagamento($pago0, true) . ')' : '')
      . ' · Motivo: ' . $motivo . ' · por ' . maiusculo(primeiroNome((string)$u['nome'])) . ' (gestor)');
    $q = $db->prepare("UPDATE papeis SET estado = 'corrigida', vendas = ?" . ($resumo !== '' ? ', resumo = ?' : '') . ($vias !== null ? ', vias = ?' : '') . ' WHERE id = ?'); $k = 1;
    $q->bindValue($k++, $mi); if ($resumo !== '') $q->bindValue($k++, $resumo); if ($vias !== null) $q->bindValue($k++, $vias, PDO::PARAM_LOB); $q->bindValue($k, (int)$p['id'], PDO::PARAM_INT); $q->execute();
    $db->commit();
  } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  if ($cup) acertarCupons();   /* o cupom fiscal acompanha (a emitir: refeito; já emitido: a cancelar, e o certo a emitir) */
  return ['antes' => $antes / 100, 'total' => $total / 100, 'formas' => $F, 'registro' => $reg, 'vendas' => array_map('vendaCtPublica', vendasDoPapel(papelCT((int)$p['id'])))];
}
// A lixeira: sai a venda inteira (os itens do cupom); o que foi pago sai do caixa (devolver ao cliente) e os produtos voltam ao estoque.
// Fica nos cancelamentos (venda cancelada, com o motivo) e na linha do dia; o cupom some dos Vendidos do dia
function excluirVendaCT(array $in, array $u): array {
  if (($u['papel'] ?? '') !== 'gestor') falha('Só o gestor exclui a venda.', 403);
  $motivo = motivoGestor($in, 'excluindo'); $p = papelCT((int)($in['papel'] ?? 0)); $V = vendasDoPapel($p); if (!$V) falha('Não achei as vendas deste cupom (já foi excluída?).', 404);
  $c = crianca((int)$p['crianca_id']); if (!$c) falha('Cliente não encontrado.', 404); $ini = (string)min(array_column($V, 'quando'));
  $r = excluirVendas($c, $V, $u, $motivo, 'venda', $ini, 'Venda da Casa Tom das ' . substr($ini, 11, 5) . ' excluída');
  db()->prepare("UPDATE papeis SET estado = 'excluida' WHERE id = ?")->execute([(int)$p['id']]);
  return $r;
}

/* ---------- 1.12: a festa ---------- */
// A tabela "Nossos valores" da loja: o valor pela quantidade de crianças, na festa de semana ou na de final de semana (que vale também
// para férias, emenda e feriado), e o adicional por criança. A festa é vendida só pelo botão Festa; o valor é o desta tabela, não o
// que a tela mandar. 1.13: além da festa nova, o UPGRADE (o cliente já fechou uma festa e passa para uma maior: cobra só a diferença)
// e o ADICIONAL DE FESTA (as crianças a mais do que o pacote, cada uma pelo valor do adicional).
const FESTA_DE_FABRICA = ['n' => [10, 15, 20, 25, 30], 'semana' => [2549, 2999, 3449, 3899, 4349], 'fds' => [2849, 3299, 3749, 4199, 4649], 'extra' => 190, 'extraMax' => 60, 'horas' => 3, 'parcelas' => 4];
const FESTA_SERVICO = ['semana' => 'FESTA DE SEMANA', 'fds' => 'FESTA DE FINAL DE SEMANA'];
const FESTA_TIPOS = ['semana' => 'festa de semana', 'fds' => 'festa de final de semana'];
// a festa que o sistema conhece do cliente (as crianças com o mesmo CPF): a venda de festa ou de upgrade mais recente. ['dia', 'n'] ou null.
// Serve para anotar, na venda do upgrade, quando a festa de origem não está no sistema ou quando a do sistema é outra
function festaDoCliente(array $c): ?array {
  $db = db(); $ids = [(int)$c['id']]; $doc = (string)preg_replace('/\D/', '', (string)($c['cpf'] ?? ''));
  if (($c['doc_tipo'] ?? 'cpf') !== 'rne' && strlen($doc) === 11) { $q = $db->prepare("SELECT id FROM criancas WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?"); $q->execute([$doc]); foreach ($q->fetchAll() as $l) $ids[] = (int)$l['id']; $ids = array_values(array_unique($ids)); }
  $m = implode(',', array_fill(0, count($ids), '?'));
  $q = $db->prepare("SELECT servico, itens FROM vendas WHERE crianca_id IN ($m) AND (servico LIKE 'FESTA %' OR servico = 'UPGRADE DE FESTA') ORDER BY id DESC LIMIT 1"); $q->execute($ids); $v = $q->fetch(); if (!$v) return null;
  $s = (string)$v['servico']; $i = (string)$v['itens'];
  if ($s === 'UPGRADE DE FESTA') return preg_match('/^de \d+ para (\d+) crianças · festa de (semana|final de semana)/u', $i, $x) ? ['dia' => $x[2] === 'semana' ? 'semana' : 'fds', 'n' => (int)$x[1]] : null;
  if ($s === FESTA_SERVICO['semana'] || $s === FESTA_SERVICO['fds']) return preg_match('/^(\d+) crianças/u', $i, $x) ? ['dia' => $s === FESTA_SERVICO['semana'] ? 'semana' : 'fds', 'n' => (int)$x[1]] : null;
  return preg_match('/^FESTA (\d+) CRIANÇAS$/u', $s, $x) ? ['dia' => strpos($i, 'dia de semana') === 0 ? 'semana' : 'fds', 'n' => (int)$x[1]] : null;   // como a 1.12 gravava
}
// as linhas da venda de uma festa, como as do atendimento: [['servico', 'min', 'valor', 'itens']]. $fe é o que a tela mandou:
//   festa nova: dia ('semana' ou 'fds'), n (crianças) e v (o valor que a tela mostrou);
//   upgrade:    up = [dia, de, para, v];
//   adicional:  extra (crianças a mais) e ve (o valor de cada uma que a tela mostrou);
//   data ('AAAA-MM-DD', opcional): o dia da festa.
function linhasDaFesta($fe, array $c): array {
  if (!is_array($fe)) return [];
  $t = regra()['festa']; $fa = festaDaFamilia($c, (int)($fe['festa'] ?? 0)); /* 1.29: a festa marcada deste cliente (o upgrade e o adicional vão para ela) */ $dia = (string)($fe['dia'] ?? ''); $n = (int)($fe['n'] ?? 0); $extra = (int)($fe['extra'] ?? 0); $data = trim((string)($fe['data'] ?? '')); $up = is_array($fe['up'] ?? null) ? $fe['up'] : null;
  if ($extra < 0 || $extra > (int)$t['extraMax']) falha('Quantas crianças no adicional de festa? De 0 a ' . (int)$t['extraMax'] . '.');
  if ($n > 0 && $up) falha('Escolha a festa nova ou o upgrade de festa: os dois juntos, não.');
  if ($n <= 0 && !$up && $extra <= 0) falha('Escolha a festa: toque no valor, faça o upgrade ou ponha o adicional de festa.');
  $quando = ''; $fimDeSemana = false;
  if ($data !== '') {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || $data < date('Y-m-d')) falha('Confira a data da festa.');
    $br = $m[3] . '/' . $m[2] . '/' . $m[1]; $fimDeSemana = (int)date('N', strtotime($data)) >= 6; $quando = ' · festa em ' . $br;
  }
  $noSabado = fn(string $d) => $fimDeSemana && $d === 'semana' ? falha('O dia ' . $br . ' cai num fim de semana: vale a festa de final de semana.') : null;
  $L = [];
  if ($n > 0) { // a festa nova
    if (!isset(FESTA_SERVICO[$dia])) falha('A festa é de semana ou de final de semana?');
    if ($data === '') falha('Escolha a data da festa no calendário.');   // 1.29: a festa nova sempre tem o dia (é o que aparece no calendário das unidades)
    $noSabado($dia);
    $nova = festaNovaDoPedido($fe, $c, $dia, $data);
    $i = array_search($n, $t['n'], true); if ($i === false) falha('A festa para ' . $n . ' crianças não está na tabela.');
    $v = (float)$t[$dia][$i];
    if (isset($fe['v']) && abs((float)$fe['v'] - $v) >= 0.005) falha('O valor da festa para ' . $n . ' crianças mudou para ' . dinheiro($v) . '. Feche e abra a Festa de novo.');
    $L[] = ['servico' => FESTA_SERVICO[$dia], 'min' => 0, 'valor' => $v, 'itens' => $n . ' crianças' . $quando . ($nova['tema'] !== '' ? ' · ' . $nova['tema'] : ''), 'festa_acao' => ['tipo' => 'nova', 'n' => $n] + $nova];
  }
  if ($up) { // o upgrade: de uma festa para outra maior, do mesmo tipo. Cobra só a diferença entre as duas, pela tabela
    $ud = (string)($up['dia'] ?? ''); $de = (int)($up['de'] ?? 0); $para = (int)($up['para'] ?? 0);
    if (!isset(FESTA_SERVICO[$ud])) falha('O upgrade é de uma festa de semana ou de final de semana?');
    if ($fa) { // 1.29: a festa está no sistema: o upgrade é dela, e só antes do dia (no dia, as crianças a mais são o adicional)
      $nome = (string)(crianca((int)$fa['crianca_id'])['nome'] ?? ''); $faBr = substr($fa['data'], 8, 2) . '/' . substr($fa['data'], 5, 2) . '/' . substr($fa['data'], 0, 4);
      if ($fa['data'] <= date('Y-m-d')) falha(($fa['data'] === date('Y-m-d') ? 'A festa de ' . $nome . ' é hoje' : 'A festa de ' . $nome . ' já foi, em ' . $faBr) . ': não tem mais upgrade. As crianças a mais entram como adicional de festa.');
      if ((int)$fa['n'] !== $de || $fa['dia'] !== $ud) falha('A festa de ' . $nome . ' é a ' . FESTA_TIPOS[$fa['dia']] . ' de ' . (int)$fa['n'] . ' crianças. Feche e abra a Festa de novo.');
      if ($data === '') { $data = $fa['data']; $br = $faBr; $quando = ' · festa em ' . $br; $fimDeSemana = (int)date('N', strtotime($data)) >= 6; }
    }
    $noSabado($ud);
    $i = array_search($de, $t['n'], true); $j = array_search($para, $t['n'], true);
    if ($i === false || $j === false) falha('O upgrade é entre duas festas da tabela.');
    if ($j <= $i) falha('O upgrade é para uma festa maior: de ' . $de . ' para ' . $para . ' crianças não dá.');
    $v = round((float)$t[$ud][$j] - (float)$t[$ud][$i], 2); if ($v <= 0) falha('Não há diferença a cobrar de ' . $de . ' para ' . $para . ' crianças.');
    if (isset($up['v']) && abs((float)$up['v'] - $v) >= 0.005) falha('O valor do upgrade de ' . $de . ' para ' . $para . ' crianças mudou para ' . dinheiro($v) . '. Feche e abra a Festa de novo.');
    $tem = festaDoCliente($c);   // o que o sistema sabe da festa deste cliente: fica anotado quando não bate com o que a tela disse
    $nota = !$tem ? ' · sem festa anterior no sistema' : ($tem['n'] !== $de || $tem['dia'] !== $ud ? ' · no sistema: ' . FESTA_TIPOS[$tem['dia']] . ' de ' . $tem['n'] . ' crianças' : '');
    $L[] = ['servico' => 'UPGRADE DE FESTA', 'min' => 0, 'valor' => $v, 'itens' => 'de ' . $de . ' para ' . $para . ' crianças · ' . FESTA_TIPOS[$ud] . $quando . ($fa ? '' : $nota), 'festa_acao' => $fa ? ['tipo' => 'up', 'festa' => (int)$fa['id'], 'n' => $para] : null];
  }
  if ($extra > 0) { // o adicional de festa: as crianças a mais do que o pacote
    $ve = (float)$t['extra'];
    if (isset($fe['ve']) && abs((float)$fe['ve'] - $ve) >= 0.005) falha('O valor do adicional de festa mudou para ' . dinheiro($ve) . ' por criança. Feche e abra a Festa de novo.');
    if ($fa && !$L && $quando === '') $quando = ' · festa em ' . substr($fa['data'], 8, 2) . '/' . substr($fa['data'], 5, 2) . '/' . substr($fa['data'], 0, 4);   // 1.29: o adicional da festa marcada diz o dia dela
    $L[] = ['servico' => 'ADICIONAL DE FESTA', 'min' => 0, 'valor' => round($extra * $ve, 2), 'itens' => $extra . ($extra === 1 ? ' criança' : ' crianças') . ($L ? '' : $quando), 'festa_acao' => ['tipo' => 'extra', 'q' => $extra, 'festa' => $n > 0 ? 0 : ($fa ? (int)$fa['id'] : 0)]];
  }
  return $L;
}

/* ---------- 1.29: a festa marcada ---------- */
// A festa nova vira um registro com o dia, a unidade, o tema, o horário e o aniversariante (uma criança do cliente). O upgrade e o adicional
// vão para a festa marcada do cliente. A quantidade de crianças combinada sai das vendas ligadas à festa: a maior festa_n (a festa nova ou o
// último upgrade) mais o adicional (a soma de qtd). Venda cancelada sai da conta sozinha; sem a venda da festa, ela some do calendário.
// No dia, cada criança entra na festa como aniversariante ou convidada (festa_presencas): a visita não cobra e não gasta horas. O aniversariante
// não paga e não conta no combinado. O convidado que passa do combinado fica marcado (a_mais) e a gestora vê o adicional a cobrar.
const FESTA_HORA_FDS = '10:30';   // a festa de final de semana é das 10h30 às 13h30
function horaDaFesta($h, string $dia): string {
  if ($dia === 'fds') return FESTA_HORA_FDS; $h = trim((string)$h); if ($h === '') return '';
  if (!preg_match('/^([01]?\d|2[0-3])[:h]([0-5]\d)$/', $h, $m)) falha('Escreva o horário da festa assim: 15:00.'); return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
}
// a conta de cada festa: n (a festa contratada), extra (o adicional), conv e aniv (quem entrou), a_mais (convidados além do combinado)
const FESTA_CONTA_SQL = "SELECT f.*, (SELECT MAX(v.festa_n) FROM vendas v WHERE v.festa_id = f.id AND v.festa_n > 0) AS n,
  (SELECT COALESCE(SUM(v.qtd), 0) FROM vendas v WHERE v.festa_id = f.id AND v.servico = 'ADICIONAL DE FESTA') AS extra,
  (SELECT COUNT(*) FROM festa_presencas p WHERE p.festa_id = f.id AND p.papel = 'convidado') AS conv,
  (SELECT COUNT(*) FROM festa_presencas p WHERE p.festa_id = f.id AND p.papel = 'aniversariante') AS aniv,
  (SELECT COUNT(*) FROM criancas k WHERE k.festa_id = f.id AND k.entrou IS NOT NULL) AS dentro FROM festas f";
function festaComConta(int $id): ?array { if ($id <= 0) return null; $q = db()->prepare(FESTA_CONTA_SQL . ' WHERE f.id = ?'); $q->execute([$id]); $f = $q->fetch(); return $f && (int)$f['n'] > 0 ? $f : null; }   // sem a venda da festa (cancelada), ela não existe mais
function festaPublica(array $f): array {
  $c = crianca((int)$f['crianca_id']) ?: []; $nasc = (string)($c['nascimento'] ?? ''); $n = (int)$f['n']; $x = (int)$f['extra'];
  return ['id' => (int)$f['id'], 'crianca' => (int)$f['crianca_id'], 'nome' => (string)($c['nome'] ?? ''), 'idade' => $nasc !== '' ? idadeDeNascimento($nasc, $f['data']) : (int)($c['idade'] ?? 0), 'nasc' => $nasc !== '',
    'resp' => (string)($c['resp'] ?? ''), 'tel' => (string)($c['tel'] ?? ''), 'tel2' => (string)($c['tel2'] ?? ''), 'uni' => $f['unidade_id'] === null ? null : (int)$f['unidade_id'],
    'data' => (string)$f['data'], 'hora' => (string)$f['hora'], 'tema' => (string)$f['tema'], 'dia' => (string)$f['dia'], 'n' => $n, 'extra' => $x, 'conv' => (int)$f['conv'], 'aniv' => (int)$f['aniv'], 'dentro' => (int)$f['dentro'], 'criada' => (string)$f['criado_em']];
}
// 1.38: a venda da festa em partes (a festa, o upgrade, o adicional): cada parte com o dia em que foi vendida, quem vendeu e o que foi
// pago (em que dia e em que forma). A festa é uma venda só para quem olha; cada pagamento continua no caixa do dia em que entrou
function festaVendas(array $f): array {
  $q = db()->prepare('SELECT v.id, v.servico, v.itens, v.valor, v.desc_valor, v.quando, u.nome AS por_nome FROM vendas v LEFT JOIN usuarios u ON u.id = v.por WHERE v.festa_id = ? ORDER BY v.quando, v.id'); $q->execute([(int)$f['id']]); $V = $q->fetchAll();
  if (!$V) return []; $P = [];
  foreach (db()->query('SELECT venda_id, forma, valor, quando FROM pagamentos WHERE venda_id IN (' . implode(',', array_map(fn($v) => (int)$v['id'], $V)) . ') ORDER BY quando, id')->fetchAll() as $p)
    $P[(int)$p['venda_id']][] = ['f' => (string)$p['forma'], 'v' => round((float)$p['valor'], 2), 'quando' => (string)$p['quando']];
  return array_map(fn($v) => ['id' => (int)$v['id'], 'servico' => (string)$v['servico'], 'itens' => (string)$v['itens'], 'v' => round((float)$v['valor'], 2), 'desc' => round((float)$v['desc_valor'], 2), 'quando' => (string)$v['quando'], 'por' => primeiroNome((string)($v['por_nome'] ?? '')), 'pag' => $P[(int)$v['id']] ?? []], $V);
}
// (o caixa do dia) de qual festa é o que entrou hoje: "festa de ALICE (20/11)" e, se a festa foi vendida em outro dia, quando
function rotuloFestaCaixa(int $fid, string $servico): string {
  static $m = []; if (!isset($m[$fid])) { $q = db()->prepare('SELECT f.data, c.nome, (SELECT MIN(v.quando) FROM vendas v WHERE v.festa_id = f.id AND v.festa_n > 0) vendida FROM festas f LEFT JOIN criancas c ON c.id = f.crianca_id WHERE f.id = ?'); $q->execute([$fid]); $m[$fid] = $q->fetch() ?: null; }
  $x = $m[$fid]; $s = mb_strtoupper(trim($servico)) ?: 'FESTA'; if (!$x) return $s;
  $dm = fn($d) => substr((string)$d, 8, 2) . '/' . substr((string)$d, 5, 2);
  return $s . ' · festa de ' . (string)$x['nome'] . ' (' . $dm($x['data']) . ')' . ($x['vendida'] && substr((string)$x['vendida'], 0, 10) !== date('Y-m-d') ? ' · a festa foi vendida em ' . $dm($x['vendida']) : '');
}
// as festas do calendário: de 60 dias atrás até mais de um ano para a frente, das unidades todas
function festasDaTela(): array {
  $q = db()->prepare('SELECT * FROM (' . FESTA_CONTA_SQL . ' WHERE f.data >= ? AND f.data <= ?) WHERE n > 0 ORDER BY data, hora, id LIMIT 600'); $q->execute([date('Y-m-d', time() - 60 * 86400), date('Y-m-d', time() + 430 * 86400)]);
  return array_map('festaPublica', $q->fetchAll());
}
function festasPulso(): string { return (string)db()->query("SELECT COUNT(*) || ':' || COALESCE(MAX(mudou_em), '') || ':' || COALESCE(SUM(LENGTH(tema) + LENGTH(hora) + LENGTH(data) + COALESCE(unidade_id, 0)), 0) FROM festas")->fetchColumn()
  . '|' . db()->query("SELECT COUNT(*) || ':' || COALESCE(MAX(id), 0) || ':' || COALESCE(SUM(papel = 'aniversariante'), 0) FROM festa_presencas")->fetchColumn()
  . '|' . db()->query("SELECT COUNT(*) || ':' || COALESCE(SUM(festa_n), 0) || ':' || COALESCE(SUM(qtd), 0) FROM vendas WHERE festa_id IS NOT NULL")->fetchColumn(); }
// a festa marcada do cliente (as crianças com o mesmo documento): a que a tela disse, ou a mais perto de hoje em diante (a de hoje primeiro)
function festaDaFamilia(array $c, int $id = 0): ?array {
  $ids = array_merge([(int)$c['id']], array_map(fn($x) => (int)$x['id'], irmaosDe($c)));
  if ($id > 0) { $f = festaComConta($id); if (!$f) falha('A festa não está mais marcada (a venda dela foi cancelada).'); if (!in_array((int)$f['crianca_id'], $ids, true)) falha('Essa festa é de outro cliente.'); return $f; }
  $q = db()->prepare('SELECT * FROM (' . FESTA_CONTA_SQL . ' WHERE f.crianca_id IN (' . implode(',', $ids) . ') AND f.data >= ?) WHERE n > 0 ORDER BY data, id'); $q->execute([date('Y-m-d', time() - 30 * 86400)]); $L = $q->fetchAll(); $hoje = date('Y-m-d');
  $prox = array_values(array_filter($L, fn($f) => $f['data'] >= $hoje)); $pas = array_reverse(array_values(array_filter($L, fn($f) => $f['data'] < $hoje)));   // (1.29: a da criança escolhida primeiro; sem festa daqui para a frente, a mais recente dos últimos 30 dias)
  foreach ([$prox, $pas] as $G) { foreach ($G as $f) if ((int)$f['crianca_id'] === (int)$c['id']) return $f; if ($G) return $G[0]; }
  return null;
}
// o que a tela mandou para a festa nova: a unidade, o tema, o horário e o aniversariante (uma das crianças do cliente)
function festaNovaDoPedido(array $fe, array $c, string $dia, string $data): array {
  $uni = isset($fe['uni']) && $fe['uni'] !== '' && $fe['uni'] !== null ? (int)$fe['uni'] : unidadeDoPedido();
  if ($uni !== null) { $ok = false; foreach (unidades() as $u) if ($u['id'] === $uni && $u['ativo']) $ok = true; if (!$ok) falha('Escolha a unidade da festa.'); }
  $aniv = (int)($fe['aniv'] ?? 0) ?: (int)$c['id']; $fam = array_merge([(int)$c['id']], array_map(fn($x) => (int)$x['id'], irmaosDe($c)));
  if (!in_array($aniv, $fam, true)) falha('O aniversariante precisa ser uma das crianças deste cliente.');
  return ['uni' => $uni, 'tema' => mb_substr(maiusculo((string)($fe['tema'] ?? '')), 0, 60), 'hora' => horaDaFesta($fe['hora'] ?? '', $dia), 'aniv' => $aniv, 'dia' => $dia, 'data' => $data];
}
// depois das vendas: a festa nova nasce, e cada venda fica ligada à festa dela. Devolve o número da festa (0 = nenhuma)
function gravarFesta(array $linhas, array $vendas, array $u): int {
  $db = db(); $nova = 0; $ultima = 0;
  foreach ($linhas as $i => $l) { $a = $l['festa_acao'] ?? null; $v = (int)($vendas[$i] ?? 0); if (!$a || !$v) continue;
    if ($a['tipo'] === 'nova') {
      $db->prepare('INSERT INTO festas (crianca_id, unidade_id, data, hora, tema, dia, criado_em, por, mudou_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$a['aniv'], $a['uni'], $a['data'], $a['hora'], $a['tema'], $a['dia'], agora(), (int)$u['id'], agora()]);
      $nova = $ultima = (int)$db->lastInsertId(); $db->prepare('UPDATE vendas SET festa_id = ?, festa_n = ? WHERE id = ?')->execute([$nova, (int)$a['n'], $v]);
    } elseif ($a['tipo'] === 'up') { $ultima = (int)$a['festa']; $db->prepare('UPDATE vendas SET festa_id = ?, festa_n = ? WHERE id = ?')->execute([$ultima, (int)$a['n'], $v]); $db->prepare('UPDATE festas SET mudou_em = ? WHERE id = ?')->execute([agora(), $ultima]); }
    elseif ($a['tipo'] === 'extra') { $fid = (int)($a['festa'] ?? 0) ?: $nova; if ($fid) { $ultima = $fid; $db->prepare('UPDATE vendas SET festa_id = ?, qtd = ? WHERE id = ?')->execute([$fid, (int)$a['q'], $v]); $db->prepare('UPDATE festas SET mudou_em = ? WHERE id = ?')->execute([agora(), $fid]); } }
  }
  return $ultima;
}
// a criança entra na festa (só no dia dela, na unidade dela). O aniversariante é uma criança do cliente da festa. Quem já tinha entrado
// nesta festa hoje (saiu e voltou) não conta de novo.
function festaEntrada(array $f, array $c, string $papel, array $u): array {
  $db = db(); $agora = agora(); $nova = false; $aMais = 0; $pos = 0; $cap = (int)$f['n'] + (int)$f['extra'];
  $doCliente = in_array((int)$c['id'], array_merge([(int)$f['crianca_id']], array_map(fn($x) => (int)$x['id'], irmaosDe(crianca((int)$f['crianca_id']) ?: ['id' => 0, 'cpf' => '', 'tel' => '']))), true);
  if ($papel === 'aniversariante' && !$doCliente) falha('O aniversariante é da família de ' . (crianca((int)$f['crianca_id'])['nome'] ?? 'quem fez a festa') . '. ' . $c['nome'] . ' entra como convidado.');
  $db->exec('BEGIN IMMEDIATE');   // duas recepções ao mesmo tempo não pegam a mesma vaga
  try {
    $q = $db->prepare('SELECT * FROM festa_presencas WHERE festa_id = ? AND crianca_id = ?'); $q->execute([(int)$f['id'], (int)$c['id']]); $ja = $q->fetch();
    if (!$ja) {
      $q = $db->prepare("SELECT COUNT(*) FROM festa_presencas WHERE festa_id = ? AND papel = 'convidado'"); $q->execute([(int)$f['id']]); $pos = (int)$q->fetchColumn() + 1;
      $aMais = $papel === 'convidado' && $pos > $cap ? 1 : 0; $nova = true;
      $db->prepare('INSERT INTO festa_presencas (festa_id, crianca_id, papel, quando, por, a_mais) VALUES (?, ?, ?, ?, ?, ?)')->execute([(int)$f['id'], (int)$c['id'], $papel, $agora, (int)$u['id'], $aMais]);
    } elseif ($ja['papel'] !== $papel) { $db->prepare('UPDATE festa_presencas SET papel = ?, a_mais = 0 WHERE id = ?')->execute([$papel, (int)$ja['id']]); }
    $db->exec('COMMIT');
  } catch (Throwable $e) { try { $db->exec('ROLLBACK'); } catch (Throwable $e2) { } throw $e; }
  $passou = (bool)$c['entrou'];   // já estava no espaço (entrou hoje como recreação: pelo rosto, ou por engano): a visita passa a ser da festa
  try { if ($passou) { $t = tirarAutosDaVisita($c);
      foreach (vouchersDaVisita($c) as $v) { $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$v['minutos'], (int)$c['id']]); $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([(int)$v['id']]);   // 1.45 (auditoria): a festa não usa horas: o voucher desta visita sai
        if (!empty($v['registro'])) $db->prepare("UPDATE registros SET texto = texto || ' · cancelada' WHERE id = ? AND crianca_id = ? AND texto NOT LIKE '% · cancelada'")->execute([(int)$v['registro'], (int)$c['id']]); }
      $db->prepare('UPDATE criancas SET festa_id = ? WHERE id = ?')->execute([(int)$f['id'], (int)$c['id']]); $e = ['registro' => registrar('ent', (int)$c['id'], $c['nome'], '', null), 'mensagem' => null, 'tirou' => $t]; }
    else $e = fazerEntrada($c, 'mão', $agora, (int)$f['id']); }
  catch (Throwable $x) { if ($nova) $db->prepare('DELETE FROM festa_presencas WHERE festa_id = ? AND crianca_id = ?')->execute([(int)$f['id'], (int)$c['id']]); throw $x; }
  $nome = (string)(crianca((int)$f['crianca_id'])['nome'] ?? '');
  $db->prepare('UPDATE registros SET texto = ? WHERE id = ?')->execute([($passou ? 'Passou para a festa de ' : 'Entrada na festa de ') . $nome . ($f['tema'] !== '' ? ' (' . $f['tema'] . ')' : '') . ' · ' . ($papel === 'aniversariante' ? 'aniversariante' : 'convidado') . ($aMais ? ' · ' . $pos . 'ª criança: passou do combinado (' . $cap . ')' : ''), (int)$e['registro']]);
  $db->prepare('UPDATE festas SET mudou_em = ? WHERE id = ?')->execute([agora(), (int)$f['id']]);
  return ['registro' => $e['registro'], 'mensagem' => is_array($e['mensagem']) ? (int)($e['mensagem']['id'] ?? 0) : 0, 'festa' => festaPublica(festaComConta((int)$f['id']) ?: $f), 'a_mais' => (bool)$aMais, 'pos' => $pos, 'nova' => $nova, 'passou' => $passou, 'tirou' => $passou ? $e['tirou'] : null];
}
// a entrada na festa desfeita ou cancelada: a presença que nasceu nela sai (quem já tinha vindo antes, hoje, continua contando)
function tirarPresencaDaEntrada(array $c): void { if (empty($c['festa_id']) || !$c['entrou']) return; db()->prepare('DELETE FROM festa_presencas WHERE festa_id = ? AND crianca_id = ? AND quando >= ?')->execute([(int)$c['festa_id'], (int)$c['id'], (string)$c['entrou']]); db()->prepare('UPDATE festas SET mudou_em = ? WHERE id = ?')->execute([agora(), (int)$c['festa_id']]); }
// a saída de quem está na festa: não debita nada e não cobra (o que foi comprado nesta visita e ficou a pagar continua na conta da saída)
function saidaDaFesta(array $c, string $origem, string $quando): array {
  $db = db(); $min = minutosDaVisita($c, strtotime($quando)); $fid = (int)$c['festa_id'];
  $db->prepare('INSERT INTO visitas (crianca_id, entrou, saiu, debitado_min, origem, unidade_id, festa_id) VALUES (?, ?, ?, 0, ?, ?, ?)')->execute([$c['id'], $c['entrou'], $quando, $origem, $c['unidade_id'] ?? null, $fid]);
  $visita = (int)$db->lastInsertId(); $sap = isset($c['sapateira']) && $c['sapateira'] !== null ? (int)$c['sapateira'] : 0;
  $db->prepare('UPDATE criancas SET entrou = NULL, sapateira = NULL, festa_id = NULL WHERE id = ?')->execute([$c['id']]);
  $q = $db->prepare('SELECT f.tema, c.nome FROM festas f LEFT JOIN criancas c ON c.id = f.crianca_id WHERE f.id = ?'); $q->execute([$fid]); $f = $q->fetch() ?: ['tema' => '', 'nome' => ''];
  $reg = registrar('sai', (int)$c['id'], $c['nome'], 'Saiu da festa de ' . $f['nome'] . ' · ficou ' . dur((int)floor($min)) . ($sap ? ' · sapato na sapateira ' . $sap : '') . ($origem === 'iDFace' || modoManual() ? '' : ' · saída à mão'), $quando);
  $msg = null; if (regra()['autoMsg'] === 'ambos') $msg = mandarMensagem($c, nomeMsg($c['nome']) . ' saiu às ' . date('H:i', strtotime($quando)) . '. Até a próxima! 🧸', true);
  return ['registro' => $reg, 'visita' => $visita, 'debitado' => 0, 'minutos' => (int)floor($min), 'atraso' => ['n' => 0, 'valor' => 0.0], 'plano' => 'festa', 'mensagem' => $msg, 'sapateira' => $sap ?: null, 'festa' => $fid];
}
// o relatório da festa: quem veio (aniversariante e convidados), com o responsável e os telefones, na ordem em que entraram
function festaRelatorio(array $f): array {
  $q = db()->prepare('SELECT p.papel, p.quando, p.a_mais, p.id pid, c.id, c.nome, c.idade, c.nascimento, c.resp, c.rel, c.tel, c.tel2, c.entrou, c.festa_id FROM festa_presencas p JOIN criancas c ON c.id = p.crianca_id WHERE p.festa_id = ? ORDER BY p.papel = \'convidado\', p.quando, p.id');
  $q->execute([(int)$f['id']]); $cap = (int)($f['n'] ?? 0) + (int)($f['extra'] ?? 0); $pos = 0;
  return array_map(function ($x) use ($f, $cap, &$pos) { if ($x['papel'] === 'convidado') $pos++; $x['a_mais'] = $x['papel'] === 'convidado' && $pos > $cap; return $x; }, $q->fetchAll());
}
function festaRelatorioLinhas(array $f, array $L): array {   // (as linhas que vão para a tela)
  return array_map(fn($x) => ['id' => (int)$x['id'], 'nome' => (string)$x['nome'], 'idade' => (string)($x['nascimento'] ?? '') !== '' ? idadeDeNascimento((string)$x['nascimento'], $f['data']) : (int)$x['idade'], 'papel' => (string)$x['papel'], 'quando' => (string)$x['quando'], 'a_mais' => !empty($x['a_mais']),
    'resp' => (string)$x['resp'], 'tel' => (string)$x['tel'], 'tel2' => (string)($x['tel2'] ?? ''), 'dentro' => $x['entrou'] !== null && (int)$x['festa_id'] === (int)$f['id']], $L);
}

/* ---------- cancelar a entrada sem cobrar ---------- */
// Até regra.cancelaMin minutos depois da entrada (minutos cheios, contados do segundo em que entrou), o próprio atendente cancela;
// depois disso, só gestor ou supervisor. Sempre com o motivo escrito: fica na tabela cancelamentos para a supervisão conferir.
function prazoCancelar(array $c): array { // [limite em minutos, segundos desde a entrada, ainda está no prazo]
  $lim = max(0, (int)regra()['cancelaMin']); $seg = $c['entrou'] ? max(0, agoraTs() - strtotime((string)$c['entrou'])) : 0;
  return [$lim, $seg, $lim > 0 && $c['entrou'] && $seg <= $lim * 60 + 10];   // 10 s de folga para o pedido que já estava a caminho quando o relógio da tela zerou
}
// as vendas que pagaram esta visita: as de hoje, feitas depois da última saída (ou do último cancelamento) da criança
// $paraReceber (1.21): a conta do que há para receber só fecha na saída: a entrada cancelada não tira da conta o que continuou cobrado e não foi pago
function vendasDaVisita(array $c, bool $paraReceber = false): array {
  $db = db(); $corte = date('Y-m-d') . ' 00:00:00';
  if (!empty($c['entrou']) && substr((string)$c['entrou'], 0, 10) < date('Y-m-d')) $corte = substr((string)$c['entrou'], 0, 10) . ' 00:00:00';
  if ($paraReceber) $corte = min($corte, pisoAReceber());   // 1.91 (auditoria): o que há para receber não some à meia-noite: vale desde a última saída   // 1.02: entrou ontem e ainda está no espaço (saída esquecida): a visita vale desde o dia da entrada
  foreach (array_merge(['SELECT MAX(saiu) FROM visitas WHERE crianca_id = ?'], $paraReceber ? [] : ["SELECT MAX(quando) FROM cancelamentos WHERE crianca_id = ? AND tipo = ''"]) as $sql) { $q = $db->prepare($sql); $q->execute([$c['id']]); $s = (string)$q->fetchColumn(); if ($s > $corte) $corte = $s; }
  $q = $db->prepare('SELECT * FROM vendas WHERE crianca_id = ? AND quando >= ? AND servico != ? ORDER BY id'); $q->execute([$c['id'], $corte, SERV_IMPORTADO]);   // 1.01: o pacote que veio na importação não é desta visita
  return $q->fetchAll();
}
// vendida junto com esta entrada (no mesmo atendimento, ou até 30 min antes dela): é a que a tela já traz marcada para não cobrar
function vendaJuntoDaEntrada(array $v, array $c): bool { return (bool)$c['entrou'] && strtotime((string)$v['quando']) >= strtotime((string)$c['entrou']) - 1800; }
// $vendaIds: as vendas que deixam de ser cobradas (só valem as desta visita); null = o tempo de brincar vendido junto com a entrada
// $desfeita (1.21): a entrada desfeita na hora, pelo botão do aviso, por quem não estorna. Fica registrada com o tipo 'desfeita' (não fecha a visita)
function vendaPresa(array $v): bool { return (empty($v['auto']) && (float)$v['valor'] > 0.004) || (bool)pagoPorForma([(int)$v['id']]); }   // 1.21: a cobrança que só quem estorna tira: a venda com valor e o que já foi pago
// no cancelamento da entrada (no prazo, com o motivo, registrado): o que já foi pago e o que é coisa (não tem tempo de brincar) continuam presos;
// o tempo que ainda não foi pago (pacote ou recreação "paga na saída") sai com a entrada, como sempre saiu
function vendaPresaNoCancelamento(array $v): bool { return (bool)pagoPorForma([(int)$v['id']]) || (empty($v['auto']) && (float)$v['valor'] > 0.004 && (int)$v['minutos'] <= 0); }
function cancelarEntrada(array $c, array $u, string $motivo, ?array $vendaIds, bool $desfeita = false): array {
  $db = db(); [$lim, $seg, $noPrazo] = prazoCancelar($c); if ($desfeita) $noPrazo = true; $agora = agora(); $minDentro = intdiv($seg, 60);
  $alvo = []; foreach (vendasDaVisita($c) as $v) if ($vendaIds === null ? ((int)$v['minutos'] > 0 && vendaJuntoDaEntrada($v, $c)) : in_array((int)$v['id'], $vendaIds, true)) $alvo[] = $v;
  if (!podeEstornar($u)) { // 1.21: quem não estorna cancela a entrada, mas o que já foi pago e o que é coisa (produto, carrinho) continuam como estão
    $presas = array_values(array_filter($alvo, 'vendaPresaNoCancelamento'));
    if ($presas && $vendaIds !== null && !$desfeita) falha('Só ' . quemEstorna() . ' tira a cobrança de uma venda (' . implode(', ', array_map('nomeDaVenda', array_slice($presas, 0, 3))) . '). Cancele só a entrada, ou chame ' . quemEstorna() . '.', 403);
    $alvo = array_values(array_filter($alvo, fn($v) => !vendaPresaNoCancelamento($v)));
  }
  $jaV = array_map(fn($x) => (int)$x['id'], $alvo); foreach (vouchersDaVisita($c) as $v) if (!in_array((int)$v['id'], $jaV, true)) $alvo[] = $v;   // 1.45 (auditoria): o voucher desta visita sai junto
  // 1.21: o que continua cobrado e ainda não foi pago fica a receber (não sai da conta com a visita): a linha do dia diz, e a tela abre o recebimento.
  // O que saiu da loja (o carrinho) não entra aqui: é recebido na devolução
  $saem = array_flip(array_map(fn($v) => (int)$v['id'], $alvo)); $ficou = $desfeita ? [] : array_values(array_filter(pendentesDaVisita($c), fn($v) => !isset($saem[(int)$v['id']]) && !((string)($v['alug'] ?? '') !== '' && $v['voltou'] === null)));
  $ficouValor = round(array_sum(array_map(fn($v) => (float)$v['falta'], $ficou)), 2);
  // 1.15: a recreação avulsa desta visita (o que o sistema lançou sozinho e ainda não foi pago) sai sempre junto com a entrada cancelada
  $ja = array_flip(array_map(fn($v) => (int)$v['id'], $alvo));
  $qa = $db->prepare('SELECT * FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND NOT EXISTS (SELECT 1 FROM pagamentos p WHERE p.venda_id = vendas.id) ORDER BY id'); $qa->execute([$c['id'], (string)$c['entrou']]);
  foreach ($qa->fetchAll() as $v) if (!isset($ja[(int)$v['id']])) $alvo[] = $v;
  $sap = isset($c['sapateira']) && $c['sapateira'] !== null ? (int)$c['sapateira'] : 0;
  $devolver = pagoPorForma(array_map(fn($v) => (int)$v['id'], $alvo));   // o que o cliente já pagou por essas vendas: é o que a loja devolve
  $db->beginTransaction();
  try {
    $itens = []; $valor = 0.0; $tempo = 0; estornarPagosAntes($alvo);   // 1.31: o que foi pago em outro dia e volta ao cliente hoje
    foreach ($alvo as $v) {
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$v['minutos'], $c['id']]);
      $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([$v['id']]); devolverEstoque($v);   // venda de produto que deixa de ser cobrada: o produto volta para o estoque
      if (!empty($v['registro'])) $db->prepare("UPDATE registros SET texto = texto || ' · cancelada' WHERE id = ? AND crianca_id = ? AND tipo IN ('venda', 'cobr') AND texto NOT LIKE '% · cancelada'")->execute([(int)$v['registro'], $c['id']]);
      $itens[] = ['n' => nomeDaVenda($v), 'm' => (int)$v['minutos'], 'v' => (float)$v['valor']]; $valor += (float)$v['valor']; $tempo += (int)$v['minutos'];
    }
    $db->prepare('UPDATE criancas SET entrou = NULL, sapateira = NULL, festa_id = NULL WHERE id = ?')->execute([$c['id']]);   // sai sem debitar nada; a sapateira fica livre
    if (!empty($c['festa_id'])) tirarPresencaDaEntrada($c);   // 1.29: a entrada na festa cancelada não conta como criança que veio
    $db->prepare('INSERT INTO cancelamentos (crianca_id, nome, resp, entrou, quando, minutos, valor, itens, motivo, por, por_nome, papel, fora, tipo, atendente, unidade_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$c['id'], $c['nome'], $c['resp'], $c['entrou'], $agora, $minDentro, $valor, json_encode($itens, JSON_UNESCAPED_UNICODE), $motivo, (int)$u['id'], $u['nome'], $u['papel'], $noPrazo ? 0 : 1, $desfeita ? 'desfeita' : '', atendente(), unidadeDoPedido()]);
    $id = (int)$db->lastInsertId();
    $oque = $itens ? 'não cobrado: ' . implode(', ', array_map(fn($i) => $i['n'] . ' (' . dinheiro($i['v']) . ')', $itens)) : 'o tempo não foi descontado';
    $reg = registrar('canc', (int)$c['id'], $c['nome'], 'Entrada das ' . substr((string)$c['entrou'], 11, 5) . ($desfeita ? ' desfeita na hora' : ' cancelada aos ' . ($minDentro < 60 ? $minDentro . ' min' : hm((int)$minDentro))) . ' · ' . $oque . ($devolver ? ' · devolver ao cliente: ' . textoPagamento($devolver, true) : '') . ($ficou ? ' · ficou a receber: ' . implode(', ', array_map(fn($v) => nomeDaVenda($v) . ' (' . dinheiro((float)$v['falta']) . ')', $ficou)) : '') . ' · Motivo: ' . $motivo . ' · por ' . porQuem($u) . ($noPrazo ? '' : ' (fora do prazo' . ($lim > 0 ? ' de ' . $lim . ' min' : '') . ')'), $agora);
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
  $c = crianca((int)$c['id']);
  // desfez o tempo e o saldo voltou a zero: o acesso volta a ficar bloqueado, como em quem ainda não comprou
  if ($tempo > 0 && restante($c) < periodo() && regra()['bloqueioAuto'] === 'sim' && (!isset($c['liberado']) || (int)$c['liberado'])) bloquearAcesso($c, false);
  $msg = null;
  if (regra()['autoMsg'] === 'ambos' && !$desfeita) $msg = mandarMensagem($c, 'A entrada de ' . nomeMsg($c['nome']) . ' na Play Space foi cancelada às ' . substr($agora, 11, 5) . ' e ' . ($valor > 0 ? 'nada foi cobrado' : 'o tempo não foi descontado') . '. Até a próxima! 🧸', true);
  return ['cancelamento' => $id, 'registro' => $reg, 'valor' => $valor, 'minutos' => $minDentro, 'tempo' => $tempo, 'sapateira' => $sap ?: null, 'fora' => !$noPrazo, 'mensagem' => $msg, 'devolver' => $devolver ? textoPagamento($devolver, true) : '', 'ficou' => $ficouValor];
}
function cancelamentoPublico(array $x): array {
  $itens = json_decode((string)$x['itens'], true);
  return ['id' => (int)$x['id'], 'crianca' => (int)$x['crianca_id'], 'nome' => $x['nome'], 'resp' => $x['resp'], 'entrou' => $x['entrou'], 'quando' => $x['quando'], 'min' => (int)$x['minutos'], 'valor' => (float)$x['valor'],
    'itens' => is_array($itens) ? $itens : [], 'motivo' => $x['motivo'], 'por' => $x['por_nome'], 'papel' => $x['papel'], 'fora' => (bool)$x['fora'], 'visto_por' => (string)$x['visto_por'], 'visto_em' => $x['visto_em'], 'tipo' => (string)($x['tipo'] ?? ''),
    'atendente' => (string)($x['atendente'] ?? ''), 'uni' => ($x['unidade_id'] ?? null) === null ? null : (int)$x['unidade_id']];   // 1.25: quem cancelou (o nome escrito) e em que unidade
}
/* ---------- 1.25: "não vai fazer agora" (o atendimento cancelado) ---------- */
// O cliente desistiu: a equipe tira de "Falta finalizar" o atendimento que estava aberto (ou o cadastro pela metade). Nada foi vendido, mas
// fica na lista dos cancelamentos, com o que estava escolhido, o nome da atendente e o motivo, e a gestora recebe o aviso. O Desfazer tira.
function cancelarAtendimento(?array $c, array $in, array $u): array {
  $agora = agora(); $motivo = mb_substr(maiusculo((string)($in['motivo'] ?? '')), 0, 300); if ($motivo === '') $motivo = 'NÃO VAI FAZER AGORA';
  $nome = $c ? (string)$c['nome'] : mb_substr(maiusculo((string)($in['nome'] ?? '')), 0, 80); $resp = $c ? (string)$c['resp'] : mb_substr(maiusculo((string)($in['resp'] ?? '')), 0, 80);
  if ($nome === '' && $resp === '') $nome = 'CADASTRO NOVO';
  $oque = mb_substr(maiusculo((string)($in['oque'] ?? '')), 0, 200); $valor = round(min(100000, max(0, (float)($in['valor'] ?? 0))), 2);   // o que estava escolhido ("RECREAÇÃO 1H · R$ 60"), ou em que ponto parou o cadastro
  $desde = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)($in['desde'] ?? '')) && (string)$in['desde'] <= $agora ? (string)$in['desde'] : $agora;   // quando o atendimento começou
  $itens = $oque !== '' ? [['n' => $oque, 'm' => 0, 'v' => $valor, 'tipo' => 'atendimento']] : [];
  db()->prepare("INSERT INTO cancelamentos (crianca_id, nome, resp, entrou, quando, minutos, valor, itens, motivo, por, por_nome, papel, fora, tipo, atendente, unidade_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'atendimento', ?, ?)")
    ->execute([$c ? (int)$c['id'] : 0, $nome, $resp, $desde, $agora, max(0, intdiv(agoraTs() - strtotime($desde), 60)), $valor, json_encode($itens, JSON_UNESCAPED_UNICODE), $motivo, (int)$u['id'], $u['nome'], $u['papel'], atendente(), unidadeDoPedido()]);
  $id = (int)db()->lastInsertId();
  $reg = registrar('canc', $c ? (int)$c['id'] : null, $nome !== '' ? $nome : $resp, 'Atendimento cancelado' . ($oque !== '' ? ': ' . $oque . ($valor > 0 ? ' (' . dinheiro($valor) . ')' : '') : '') . ' · nada foi vendido · Motivo: ' . $motivo . ' · por ' . porQuem($u), $agora);
  return ['cancelamento' => $id, 'registro' => $reg];
}
function tirarCancelamentoAtendimento(int $id, int $registro, array $u): void { // o Desfazer do aviso: só o do próprio usuário, de agora há pouco
  $desde = date('Y-m-d H:i:s', time() - 600);
  $q = db()->prepare("SELECT id, crianca_id FROM cancelamentos WHERE id = ? AND tipo = 'atendimento' AND por = ? AND quando >= ?"); $q->execute([$id, (int)$u['id'], $desde]); $k = $q->fetch(); if (!$k) return;
  db()->prepare('DELETE FROM cancelamentos WHERE id = ?')->execute([(int)$k['id']]);
  $cid = (int)$k['crianca_id'];   // a linha do dia é a dele: da mesma criança (ou do cadastro sem criança), "Atendimento cancelado", de agora há pouco
  if ($registro) db()->prepare("DELETE FROM registros WHERE id = ? AND tipo = 'canc' AND texto LIKE 'Atendimento cancelado%' AND quando >= ? AND " . ($cid ? 'crianca_id = ?' : 'crianca_id IS NULL'))->execute(array_merge([$registro, $desde], $cid ? [$cid] : []));
}
/* ---------- 1.21: cancelar uma venda (só quem estorna), com o motivo ---------- */
// Quem não estorna não desfaz a venda que registrou. Quando ela precisa mesmo sair (foi no nome errado, o cliente desistiu e a loja devolve
// o dinheiro), quem estorna cancela pelo histórico do cliente: as horas dela saem do saldo, o produto volta para o estoque, o pagamento
// dela sai (a linha do dia diz o que devolver ao cliente, por forma) e fica na lista de conferência, com o motivo e quem cancelou.
// Vale para o que a equipe vendeu nos últimos dias; o que a visita somou sozinha (a recreação avulsa) é acertado na saída, não por aqui.
const VENDA_CANCELA_DIAS = 7;
function vendaCancelavel(array $v): bool { return empty($v['auto']) && (string)($v['servico'] ?? '') !== SERV_IMPORTADO && time() - strtotime((string)$v['quando']) <= VENDA_CANCELA_DIAS * 86400; }
function cancelarVenda(int $vendaId, array $u, string $motivo, ?string $quem = null, string $ped = '', bool $semPrazo = false): array {   // 1.54: $semPrazo = a festa cancelada pelo gestor (a venda pode ser de semanas antes)   // $ped: o login de quem pediu   // $quem (1.25): o pedido liberado pelo gestor fica no nome da atendente que pediu
  $db = db(); $q = $db->prepare('SELECT * FROM vendas WHERE id = ?'); $q->execute([$vendaId]); $v = $q->fetch();
  if (!$v) falha('Esta venda não existe mais: já foi desfeita ou cancelada.', 404);
  $c = crianca((int)$v['crianca_id']); if (!$c) falha('Cliente não encontrado.', 404);
  if (!empty($v['auto'])) falha('A recreação que a visita somou sozinha não é cancelada por aqui: ela é acertada na saída.');
  if ((string)($v['servico'] ?? '') === SERV_IMPORTADO) falha('As horas que vieram do sistema anterior não são uma venda: não dá para cancelar por aqui.');
  if (!$semPrazo && time() - strtotime((string)$v['quando']) > VENDA_CANCELA_DIAS * 86400) falha('Só dá para cancelar vendas dos últimos ' . VENDA_CANCELA_DIAS . ' dias.');
  if (mb_strlen($motivo) < 5) falha('Escreva o motivo do cancelamento.');
  $min = (int)$v['minutos']; $resta = restante($c);
  if ($min > 0 && $resta < $min) falha('As horas desta venda já foram usadas' . ($resta > 0 ? ' em parte: sobram ' . hm($resta) . ' de ' . hm($min) : '') . '. Não dá para cancelar a venda inteira.');
  $devolver = pagoPorForma([(int)$v['id']]); $agora = agora(); $nome = nomeDaVenda($v); $valor = round((float)$v['valor'], 2);
  $ha = max(0, intdiv(time() - strtotime((string)$v['quando']), 60)); $outroDia = substr((string)$v['quando'], 0, 10) !== substr($agora, 0, 10);
  $itens = [['n' => $nome, 'm' => $min, 'v' => $valor, 'tipo' => 'venda', 'dev' => $devolver ? textoPagamento($devolver, true) : ''] + ($ped !== '' ? ['ped' => $ped] : [])];   // 1.25: ped = o login de quem pediu (o pedido liberado)   // dev = o que a loja devolve ao cliente (o que ele já tinha pago), por forma
  $db->beginTransaction();
  try {
    // 1.23: os pedidos presos a esta venda (o de cancelar, o da nota fiscal) deixam de valer quando ela é apagada (o gatilho da tabela);
    // antes disso, fica marcado por quê, e se havia nota já emitida (ela continua guardada, com o aviso de cancelar a nota também)
    $qn = $db->prepare("SELECT numero FROM pedidos WHERE venda_id = ? AND tipo = 'nf' AND status = 'usado' ORDER BY id DESC LIMIT 1"); $qn->execute([(int)$v['id']]); $nf = $qn->fetchColumn(); $qn->closeCursor();
    $db->prepare("UPDATE pedidos SET resultado = 'A VENDA FOI CANCELADA' WHERE venda_id = ? AND status IN ('pendente', 'liberado', 'usado')")->execute([(int)$v['id']]);
    estornarPagosAntes([$v]);   // 1.31: paga em outro dia: o dinheiro devolvido hoje sai do caixa de hoje
    $d = $db->prepare('DELETE FROM vendas WHERE id = ?'); $d->execute([(int)$v['id']]);   // o pagamento dela sai junto (o gatilho da tabela)
    if (!$d->rowCount()) { $db->rollBack(); falha('Esta venda não existe mais: já foi desfeita ou cancelada.', 404); }   // outro aparelho cancelou no mesmo instante
    if ($min > 0) $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([$min, (int)$c['id']]);
    devolverEstoque($v);   // era venda de produto: as unidades voltam para o estoque
    if (!empty($v['registro'])) $db->prepare("UPDATE registros SET texto = texto || ' · cancelada' WHERE id = ? AND crianca_id = ? AND tipo = 'venda' AND texto NOT LIKE '% · cancelada'")->execute([(int)$v['registro'], (int)$c['id']]);
    $db->prepare("INSERT INTO cancelamentos (crianca_id, nome, resp, entrou, quando, minutos, valor, itens, motivo, por, por_nome, papel, fora, tipo, atendente, unidade_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'venda', ?, ?)")
      ->execute([(int)$c['id'], $c['nome'], $c['resp'], (string)$v['quando'], $agora, $ha, $valor, json_encode($itens, JSON_UNESCAPED_UNICODE), $motivo, (int)$u['id'], $u['nome'], $u['papel'], $quem ?? atendente(), ($v['unidade_id'] ?? null) !== null ? (int)$v['unidade_id'] : unidadeDoPedido()]);   // (a unidade é a da venda) (o pedido liberado: a atendente é quem pediu; por é quem liberou)
    $id = (int)$db->lastInsertId();
    $reg = registrar('canc', (int)$c['id'], $c['nome'], 'Venda das ' . substr((string)$v['quando'], 11, 5) . ($outroDia ? ' de ' . date('d/m', strtotime((string)$v['quando'])) : '') . ' cancelada: ' . $nome . ' (' . dinheiro($valor) . ')'
      . ($min > 0 ? ' · saem ' . hm($min) . ' do saldo' : '') . ($devolver ? ' · devolver ao cliente: ' . textoPagamento($devolver, true) : ($valor > 0.004 ? ' · não tinha sido paga' : '')) . ($nf !== false ? ' · TEM NOTA FISCAL EMITIDA' . ((string)$nf !== '' ? ' (Nº ' . $nf . ')' : '') . ': CANCELAR A NOTA TAMBÉM' : '') . ' · Motivo: ' . $motivo . ' · por ' . ($quem !== null ? primeiroNome($u['nome']) : porQuem($u)), $agora);
    $db->commit();
  } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  $c = crianca((int)$c['id']);
  if ($min > 0) { // as horas saíram do saldo: quem está no espaço passa a dever o tempo da visita (nasce a recreação avulsa); quem está fora volta a ficar sem acesso
    if ($c['entrou']) acertarCobrancas($c);
    elseif (restante($c) < periodo() && regra()['bloqueioAuto'] === 'sim' && (!isset($c['liberado']) || (int)$c['liberado'])) bloquearAcesso($c, false);
  }
  return ['cancelamento' => $id, 'registro' => $reg, 'valor' => $valor, 'minutos' => $min, 'nome' => $nome, 'devolver' => $devolver ? textoPagamento($devolver, true) : '', 'nf' => $nf === false ? null : (string)$nf];   // nf: a venda tinha nota fiscal emitida (o número, ou '')
}
// guarda a mensagem na conversa e, se o WhatsApp estiver conectado, manda de verdade (texto e, se houver, a foto)
function mandarMensagem(array $c, string $texto, bool $auto = false, string $foto = '', string $por = ''): array {
  $db = db(); $erro = ''; $waId = '';
  $tel = preg_replace('/\D/', '', (string)$c['tel']);
  if ($tel === '') $erro = 'sem WhatsApp no cadastro';
  else {
    try {
      require_once __DIR__ . '/whats-lib.php';
      $w = whatsCfg();
      if (!whatsPronto($w)) $erro = 'WhatsApp não conectado';
      else {
        if ($foto !== '') { try { evoEnviarImagem($w, numeroLimpo($tel), $foto, $texto); $texto_enviado = true; } catch (Throwable $e) { $texto_enviado = false; } }
        if (empty($texto_enviado)) $waId = evoEnviar($w, numeroLimpo($tel), $texto);
      }
    } catch (Throwable $e) { $erro = $e->getMessage(); }
  }
  $db->prepare('INSERT INTO mensagens (crianca_id, de, texto, quando, por, auto, foto, lida, wa_id, erro) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)')->execute([$c['id'], 'nos', $texto, agora(), $auto ? 'automática' : $por, $auto ? 1 : 0, $foto !== '' ? 'idface' : '', $waId, $erro]);
  return ['id' => (int)$db->lastInsertId(), 'erro' => $erro];
}
// um rosto reconhecido pelo iDFace: quem está fora entra, quem está dentro sai (leituras repetidas em 90 s são ignoradas)
function processarRosto(int $idfaceUser, string $quando, string $origem = 'iDFace'): string {
  $db = db(); $q = $db->prepare('SELECT * FROM criancas WHERE idface_id = ? AND ativo = 1'); $q->execute([$idfaceUser]); $c = $q->fetch();
  if (!$c) return 'sem criança';
  if (isset($c['liberado']) && !(int)$c['liberado'] && !$c['entrou']) return 'bloqueada';   // acesso bloqueado e ela está fora: a leitura não conta
  // a mesma criança lida duas vezes seguidas (ficou parada na frente da câmera) conta uma vez só
  $ult = $db->prepare('SELECT quando FROM registros WHERE crianca_id = ? AND tipo IN (\'ent\', \'sai\') ORDER BY id DESC LIMIT 1'); $ult->execute([$c['id']]); $u = $ult->fetchColumn();
  if ($u && abs(strtotime($quando) - strtotime((string)$u)) < 90) return 'repetida';
  $ub = $db->prepare('SELECT quando FROM batidas WHERE idface_user = ? AND resultado IN (\'entrada\', \'saída\') ORDER BY ts DESC LIMIT 1'); $ub->execute([$idfaceUser]); $b = $ub->fetchColumn();
  if ($b && abs(strtotime($quando) - strtotime((string)$b)) < 90) return 'repetida';
  // a entrada acabou de ser cancelada na tela: o rosto lido na saída (nos 10 min seguintes) não vira entrada de novo
  if (!$c['entrou']) { $uc = $db->prepare("SELECT quando FROM registros WHERE crianca_id = ? AND tipo = 'canc' ORDER BY id DESC LIMIT 1"); $uc->execute([$c['id']]); $k = $uc->fetchColumn();
    if ($k && abs(strtotime($quando) - strtotime((string)$k)) < 600) return 'cancelada'; }
  if ($c['entrou']) { fazerSaida($c, $origem, $quando); return 'saída'; }
  fazerEntrada($c, $origem, $quando); return 'entrada';
}

/* ---------- 0.98: começar do zero (apagar todos os cadastros) ---------- */
// O que sai: todos os cadastros (crianças e responsáveis, os ativos e os que já tinham sido removidos, com as fotos) e tudo o que nasceu
// deles: vendas, pagamentos, visitas, as linhas do dia, as entradas canceladas, as conversas, os cupons e as leituras do iDFace.
// O que fica: a equipe e as senhas, a regra das horas, os serviços e preços, os pacotes, os produtos (o que tinha sido vendido volta
// para o estoque: a venda deixou de existir), a agenda, as sapateiras e os outros ajustes.
// Antes de apagar, o banco inteiro é copiado para a pasta de dados (ficam as 3 últimas cópias); pela tela dá para desfazer
// enquanto não houver cadastro novo.
const TABELAS_CADASTRO = ['cupons', 'pagamentos', 'visitas', 'vendas', 'registros', 'mensagens', 'batidas', 'cancelamentos', 'criancas', 'pedidos', 'festas', 'festa_presencas', 'estornos', 'trocas_pag'];   // 1.91: os estornos e as trocas de forma de pagamento também (ficavam soltos)
/* 1.91: o movimento das Finanças (o dono: "pode zerar todo o sistema, menos o Notas"). Ficam as regras e os cadastros: fin_usuarios,
   fin_fornecedores, fin_freelas, fin_com_hist (as regras das comissões) e o que está em config (metas, valores, e-mails) */
const TABELAS_FIN_MOV = ['fin_lanc', 'fin_comprovantes', 'fin_caixa', 'fin_caixa_ajustes', 'fin_caixa_hist', 'fin_caixa_rel', 'fin_festas', 'fin_festas_hist', 'fin_freela_dias', 'fin_freela_fluxo', 'fin_freela_hist', 'fin_planilhas', 'fin_planilha_linhas', 'fin_adiant'];
const CHAVES_MARCAS = '/^(meta_bat|meta_av|meta_mes|baixa):/';   // as metas batidas (para comemorar de novo) e a baixa automática
function tabelaExiste(string $t, string $banco = 'main'): bool { $q = db()->prepare("SELECT 1 FROM $banco.sqlite_master WHERE type = 'table' AND name = ?"); $q->execute([$t]); return (bool)$q->fetchColumn(); }
function finMovimento(): int { $n = 0; foreach (TABELAS_FIN_MOV as $t) if (tabelaExiste($t)) $n += (int)db()->query("SELECT COUNT(*) FROM $t")->fetchColumn(); return $n; }   // 1.29: as festas marcadas e quem veio nelas também   // 1.23: os pedidos de autorização são dos cadastros
const CHAVES_CADASTRO = '/^(mandar|foto|renomear|regra|regra_ok|desregra|falhas_regra|rosto_erro|rosto_ok|rosto_em|pedirosto|baixarfoto)_\d+$/';   // o que o sistema guarda por criança (regra_id e as outras chaves da loja não entram)
function senhaConfere(array $u, string $senha): bool { // a senha de quem está logado (para confirmar o que não tem volta fácil)
  $q = db()->prepare('SELECT senha FROM usuarios WHERE id = ?'); $q->execute([$u['id']]); $h = (string)$q->fetchColumn(); $q->closeCursor();
  return $senha !== '' && password_verify($senha, $h);
}
function limpezaResumo(): array { // o que há para apagar agora
  $db = db(); $n = fn(string $sql) => (int)$db->query($sql)->fetchColumn();
  return ['cadastros' => $n('SELECT COUNT(*) FROM criancas'), 'removidos' => $n('SELECT COUNT(*) FROM criancas WHERE ativo = 0'), 'dentro' => $n('SELECT COUNT(*) FROM criancas WHERE ativo = 1 AND entrou IS NOT NULL'),
    'responsaveis' => $n("SELECT COUNT(DISTINCT CASE WHEN cpf != '' THEN cpf ELSE 'sem' || id END) FROM criancas"), 'fotos' => $n("SELECT COUNT(*) FROM criancas WHERE foto != ''"),
    'financas' => finMovimento(), 'vendas' => $n("SELECT COUNT(*) FROM vendas WHERE servico != '" . SERV_IMPORTADO . "'"), 'valor' => round((float)$db->query('SELECT COALESCE(SUM(valor), 0) FROM pagamentos')->fetchColumn(), 2), 'visitas' => $n('SELECT COUNT(*) FROM visitas'), 'registros' => $n('SELECT COUNT(*) FROM registros')];
}
function pastaCopias(string $nada = 'Nada foi apagado.'): string {
  $d = pastaDados() . '/copias'; if (!is_dir($d)) @mkdir($d, 0700, true);
  if (!is_dir($d) || !is_writable($d)) falha('Não consegui criar a pasta das cópias de segurança. ' . $nada, 500);
  if (!file_exists($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
  if (!file_exists($d . '/index.html')) @file_put_contents($d . '/index.html', '');
  return $d;
}
/* ---------- 1.51: a cópia de segurança da semana ----------
   A cada COPIA_DIAS dias, a primeira tela que perguntar ao servidor (o pulso) faz uma cópia inteira do banco (semana-AAAAMMDD-HHMMSS-xxxx.sqlite,
   na pasta das cópias, trancada para o navegador). Ficam as COPIA_GUARDA mais novas. Nunca derruba o pedido: se der errado, fica para o próximo
   pulso (e o erro vai para o registro de erros). O gestor baixa a cópia em Ajustes e guarda fora do site. */
const COPIA_DIAS = 7, COPIA_GUARDA = 8;
function pastaCopiasSemana(): ?string {
  $d = pastaDados() . '/copias'; if (!is_dir($d)) @mkdir($d, 0700, true); if (!is_dir($d) || !is_writable($d)) return null;
  if (!file_exists($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
  if (!file_exists($d . '/index.html')) @file_put_contents($d . '/index.html', '');
  return $d;
}
function copiasDaSemana(): array {   // as cópias da semana, a mais nova primeiro (o nome começa pela data e hora)
  $d = pastaDados() . '/copias'; $L = array_values(array_filter(glob($d . '/semana-*.sqlite') ?: [], 'is_file')); rsort($L); return $L;
}
function copiaSemanal(bool $agora = false): ?string {   // faz a cópia se já passou o prazo (ou se o gestor pediu agora). Devolve o nome do arquivo novo
  $ult = (int)cfg('copia_semana_em', 0);
  if (!$agora && $ult && time() - $ult < COPIA_DIAS * 86400) return null;
  $d = pastaCopiasSemana(); if (!$d) { if (!$agora) return null; falha('Não consegui criar a pasta das cópias de segurança. Confira a permissão da pasta de dados no cPanel.', 500); }
  $tr = @fopen($d . '/.semana.trava', 'c'); if (!$tr) return null;
  if (!flock($tr, LOCK_EX | LOCK_NB)) { fclose($tr); return null; }   // outra tela já está fazendo a cópia
  try {
    $ult = (int)cfg('copia_semana_em', 0); if (!$agora && $ult && time() - $ult < COPIA_DIAS * 86400) return null;
    $db = db(); $nome = 'semana-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sqlite'; $arq = $d . '/' . $nome;
    try { $db->exec('VACUUM INTO ' . $db->quote($arq)); }
    catch (Throwable $e) {   // SQLite antigo (sem o VACUUM INTO): a cópia tabela por tabela, numa leitura só (como a da importação)
      @unlink($arq);
      try {
        $db->exec('ATTACH DATABASE ' . $db->quote($arq) . ' AS copia'); $db->beginTransaction();
        foreach ($db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN) as $t) $db->exec('CREATE TABLE copia."' . $t . '" AS SELECT * FROM main."' . $t . '"');
        $db->exec('PRAGMA copia.user_version = ' . (int)$db->query('PRAGMA user_version')->fetchColumn()); $db->commit(); $db->exec('DETACH DATABASE copia');
      } catch (Throwable $e2) {
        if ($db->inTransaction()) $db->rollBack(); try { $db->exec('DETACH DATABASE copia'); } catch (Throwable $x) { }
        @unlink($arq); registrarErro('cópia da semana: ' . $e->getMessage() . ' / ' . $e2->getMessage()); if ($agora) falha('Não consegui fazer a cópia de segurança.', 500); return null;
      }
    }
    if (!is_file($arq) || filesize($arq) < 1024) { @unlink($arq); registrarErro('cópia da semana: o arquivo não ficou inteiro'); if ($agora) falha('A cópia de segurança não ficou inteira. Tente de novo.', 500); return null; }
    @chmod($arq, 0600); gravarCfg('copia_semana_em', time());
    foreach (array_slice(copiasDaSemana(), COPIA_GUARDA) as $velha) @unlink($velha);   // ficam as COPIA_GUARDA mais novas
    return $nome;
  } finally { flock($tr, LOCK_UN); fclose($tr); }
}
function copiaInfo(): array {   // o que o cartão de Ajustes e a dica mostram (só o gestor recebe)
  $quando = function (string $f): string { $m = []; return preg_match('/^semana-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})/', basename($f), $m) ? "$m[1]-$m[2]-$m[3] $m[4]:$m[5]:$m[6]" : date('Y-m-d H:i:s', (int)@filemtime($f)); };   // a hora está no nome
  $L = array_map(fn($f) => ['nome' => basename($f), 'em' => $quando($f), 'mb' => round(((int)@filesize($f)) / 1048576, 1)], copiasDaSemana());
  $b = cfg('copia_baixada', null); $ult = (int)cfg('copia_semana_em', 0);
  return ['lista' => $L, 'baixada' => is_array($b) ? $b : null, 'pendente' => $L && !(is_array($b) && ($b['nome'] ?? '') === $L[0]['nome']),   // a mais nova ainda não foi baixada
    'proxima' => $ult ? date('Y-m-d', $ult + COPIA_DIAS * 86400) : null, 'dias' => COPIA_DIAS, 'guarda' => COPIA_GUARDA];
}
// uma cópia inteira do banco, feita pelo próprio SQLite (sai coerente mesmo com outra tela gravando). Devolve o nome do arquivo.
// O nome não começa por "playspace-" (é por esse começo que o sistema acha o banco de verdade) e leva um pedaço sorteado
/* 1.91 (auditoria): a cópia que um gestor (que não é o dono das Finanças) baixa vai sem as tabelas das Finanças nem os ajustes delas */
function copiaSemFinancas(string $arq): string {
  $tmp = dirname($arq) . '/.baixar-' . bin2hex(random_bytes(6)) . '.sqlite'; if (!@copy($arq, $tmp)) falha('Não consegui preparar a cópia. Tente de novo.', 500);
  try { $c = new PDO('sqlite:' . $tmp); $c->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ($c->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE 'fin\_%' ESCAPE '\'")->fetchAll(PDO::FETCH_COLUMN) as $t) $c->exec('DROP TABLE "' . str_replace('"', '', (string)$t) . '"');
    $c->exec("DELETE FROM config WHERE chave LIKE 'fin\_%' ESCAPE '\'"); $c->exec('VACUUM'); $c = null; }
  catch (Throwable $e) { @unlink($tmp); falha('Não consegui preparar a cópia. Tente de novo.', 500); }
  return $tmp;
}
function copiarBanco(string $nada = 'Nada foi apagado.'): string {   // $nada: o fim da mensagem de erro (o que deixou de ser feito por falta da cópia)
  $db = db(); $dir = pastaCopias($nada); $nome = 'copia-' . date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.sqlite'; $arq = $dir . '/' . $nome;
  try { $db->exec('VACUUM INTO ' . $db->quote($arq)); }   // o banco inteiro, igual ao de verdade (tabelas, índices e gatilhos)
  catch (Throwable $e) { // SQLite antigo (antes do 3.27) não tem o VACUUM INTO, e ele também não roda com uma consulta aberta nesta conexão:
    // a cópia é feita tabela por tabela, numa leitura só. Fica com os dados de todas as tabelas, que é o que o Desfazer usa
    @unlink($arq);
    try {
      $db->exec('ATTACH DATABASE ' . $db->quote($arq) . ' AS copia');
      $db->beginTransaction();
      foreach ($db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN) as $t) $db->exec('CREATE TABLE copia."' . $t . '" AS SELECT * FROM main."' . $t . '"');
      $db->exec('PRAGMA copia.user_version = ' . (int)$db->query('PRAGMA user_version')->fetchColumn());
      $db->commit();
      $db->exec('DETACH DATABASE copia');
    } catch (Throwable $e2) {
      if ($db->inTransaction()) $db->rollBack();
      try { $db->exec('DETACH DATABASE copia'); } catch (Throwable $x) { }
      @unlink($arq); registrarErro('cópia do banco: ' . $e->getMessage() . ' / ' . $e2->getMessage());
      falha('Não consegui fazer a cópia de segurança do banco. ' . $nada, 500);
    }
  }
  if (!is_file($arq) || filesize($arq) < 1024) { @unlink($arq); falha('A cópia de segurança do banco não ficou inteira. ' . $nada, 500); }
  @chmod($arq, 0600);
  // ficam a cópia nova e as duas anteriores. A nova nunca entra na conta do que sai (duas cópias feitas no mesmo segundo têm a mesma hora no nome)
  // 1.01: a cópia de que uma limpeza ou uma importação ainda depende também não sai
  $presas = []; foreach (['limpeza', 'importacao', 'importando'] as $k) { $x = cfg($k, null); if (is_array($x) && !empty($x['copia'])) $presas[basename((string)$x['copia'])] = true; }
  $outras = array_values(array_filter(glob($dir . '/copia-*.sqlite') ?: [], fn($f) => $f !== $arq && !isset($presas[basename($f)])));
  usort($outras, fn($a, $b) => ((int)@filemtime($a) <=> (int)@filemtime($b)) ?: strcmp($a, $b));   // as mais antigas primeiro
  foreach (array_slice($outras, 0, max(0, count($outras) - 2)) as $velha) @unlink($velha);
  // 1.01: os arquivos auxiliares (-wal, -shm) de uma cópia que já saiu não ficam para trás
  foreach (glob($dir . '/copia-*.sqlite-*') ?: [] as $resto) if (!is_file((string)preg_replace('/-(wal|shm|journal)$/', '', $resto))) @unlink($resto);
  return $nome;
}
function cadastrosNaCopia(string $arq): int { // abre a cópia e conta: só apaga se ela estiver inteira
  if (!is_file($arq)) return -1;   // (anexar um arquivo que não existe criaria um banco vazio com esse nome)
  $db = db(); $db->exec('ATTACH DATABASE ' . $db->quote($arq) . ' AS copia');
  try { return (int)$db->query('SELECT COUNT(*) FROM copia.criancas')->fetchColumn(); } catch (Throwable $e) { return -1; } finally { try { $db->exec('DETACH DATABASE copia'); } catch (Throwable $e) { } }
}
function limparCadastros(array $u): array {
  $db = db(); $antes = limpezaResumo(); $quem = primeiroNome((string)$u['nome']);
  if (!$antes['cadastros'] && !$antes['vendas'] && !$antes['visitas'] && !$antes['financas']) falha('Não há nada para apagar: o sistema já está limpo.', 409);
  $copia = copiarBanco();
  if (cadastrosNaCopia(pastaCopias() . '/' . $copia) !== $antes['cadastros']) falha('A cópia de segurança não confere com o banco. Nada foi apagado.', 500);
  $ex = cfg('exemplo', null); $comp = is_array($ex) ? array_values(array_map('intval', (array)($ex['compromissos'] ?? []))) : [];   // a agenda fica; só saem os compromissos que vieram com o teste
  $db->beginTransaction();
  try {
    foreach ($db->query('SELECT produto_id, qtd FROM vendas WHERE produto_id IS NOT NULL')->fetchAll() as $v) devolverEstoque($v);   // o produto vendido volta para o estoque
    $fila = []; foreach ($db->query('SELECT idface_id FROM criancas WHERE idface_id IS NOT NULL')->fetchAll() as $c) { $fila[] = (int)$c['idface_id']; gravarCfg('apagar_' . (int)$c['idface_id'], (int)$c['idface_id']); }   // quem está no iDFace sai de lá na próxima conversa com o aparelho
    foreach (TABELAS_CADASTRO as $t) $db->exec("DELETE FROM $t");
    foreach (TABELAS_FIN_MOV as $t) if (tabelaExiste($t)) $db->exec("DELETE FROM $t");   // 1.91: o movimento das Finanças
    $db->exec('DELETE FROM papeis');   // 1.14: os cupons guardados para reimprimir saem junto (o Desfazer da limpeza não traz de volta)
    try { $db->exec("DELETE FROM sqlite_sequence WHERE name = 'cupons'"); } catch (Throwable $e) { }
    foreach ($db->query('SELECT chave FROM config')->fetchAll(PDO::FETCH_COLUMN) as $k) if (preg_match(CHAVES_CADASTRO, (string)$k) || preg_match(CHAVES_MARCAS, (string)$k)) apagarCfg((string)$k);
    apagarCfg('exemplo'); apagarCfg('pend_fora'); apagarCfg('limpeza_oferta'); apagarCfg('importacao'); apagarCfg('importando');   // 1.01: os cadastros importados saíram junto
    if ($comp) $db->prepare('DELETE FROM compromissos WHERE id IN (' . implode(',', array_fill(0, count($comp), '?')) . ')')->execute($comp);
    gravarCfg('limpeza', ['em' => agora(), 'por' => $quem, 'copia' => $copia, 'n' => $antes, 'fila' => $fila, 'comp' => $comp, 'banco' => (int)$db->query('PRAGMA user_version')->fetchColumn()]);
    registrar('cad', null, 'CADASTROS', 'Todos os cadastros foram apagados para começar do zero: ' . $antes['cadastros'] . ($antes['cadastros'] === 1 ? ' cadastro' : ' cadastros') . ', ' . $antes['vendas'] . ($antes['vendas'] === 1 ? ' venda' : ' vendas') . ' e ' . $antes['visitas'] . ($antes['visitas'] === 1 ? ' visita' : ' visitas') . ' · por ' . $quem);
    $db->commit();
  } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); registrarErro('limpeza: ' . $e->getMessage()); falha('Não consegui apagar os cadastros. Nada foi mudado.', 500); }
  try { $db->exec('VACUUM'); } catch (Throwable $e) { }   // devolve ao disco o espaço das fotos
  return ['apagados' => $antes, 'ultima' => limpezaPublica()];
}
// '' se a última limpeza ainda pode ser desfeita; senão, o porquê
function limpezaVolta(array $l): string {
  $db = db(); $arq = pastaDados() . '/copias/' . basename((string)($l['copia'] ?? ''));
  if (empty($l['copia']) || !is_file($arq)) return 'A cópia de segurança dessa limpeza não está mais guardada.';
  if ((int)($l['banco'] ?? 0) !== (int)$db->query('PRAGMA user_version')->fetchColumn()) return 'O sistema foi atualizado depois da limpeza: a cópia é de uma versão anterior.';
  if ((int)$db->query('SELECT COUNT(*) FROM criancas')->fetchColumn() > 0 || (int)$db->query('SELECT COUNT(*) FROM vendas')->fetchColumn() > 0) return 'Já há cadastro novo depois da limpeza: desfazer agora misturaria os dois.';
  if (finMovimento() > 0) return 'Já há lançamento novo nas Finanças depois da limpeza: desfazer agora misturaria os dois.';   // 1.91
  return '';
}
function limpezaPublica(): ?array { // a última limpeza, para a tela mostrar e oferecer o Desfazer enquanto der
  $l = cfg('limpeza', null); if (!is_array($l) || empty($l['em'])) return null;
  return ['em' => (string)$l['em'], 'por' => (string)($l['por'] ?? ''), 'cadastros' => (int)($l['n']['cadastros'] ?? 0), 'desfaz' => limpezaVolta($l) === ''];
}
function limpezaDesfazer(array $u): array {
  $db = db(); $l = cfg('limpeza', null); if (!is_array($l) || empty($l['copia'])) falha('Não há limpeza para desfazer.', 404);
  $motivo = limpezaVolta($l); if ($motivo !== '') falha($motivo, 409);
  $db->exec('ATTACH DATABASE ' . $db->quote(pastaDados() . '/copias/' . basename((string)$l['copia'])) . ' AS copia');
  try {
    $db->beginTransaction();
    foreach (TABELAS_CADASTRO as $t) { if (!tabelaExiste($t, 'copia')) continue; $db->exec("DELETE FROM $t"); $db->exec("INSERT INTO $t SELECT * FROM copia.$t"); }   // (1.91: a cópia de antes das tabelas novas)
    foreach (TABELAS_FIN_MOV as $t) if (tabelaExiste($t) && tabelaExiste($t, 'copia')) { $db->exec("DELETE FROM $t"); $db->exec("INSERT INTO $t SELECT * FROM copia.$t"); }   // 1.91: as Finanças também voltam
    try { $db->exec("DELETE FROM sqlite_sequence WHERE name = 'cupons'"); $db->exec("INSERT INTO sqlite_sequence (name, seq) SELECT name, seq FROM copia.sqlite_sequence WHERE name = 'cupons'"); } catch (Throwable $e) { }
    foreach ($db->query('SELECT produto_id, qtd FROM vendas WHERE produto_id IS NOT NULL')->fetchAll() as $v) mexerEstoque((int)$v['produto_id'], (int)$v['qtd'], -1);   // as vendas voltaram: o estoque baixa de novo
    $por = $db->prepare('INSERT OR REPLACE INTO config (chave, valor) VALUES (?, ?)');
    foreach ($db->query('SELECT chave, valor FROM copia.config')->fetchAll() as $r) if (preg_match(CHAVES_CADASTRO, (string)$r['chave']) || preg_match(CHAVES_MARCAS, (string)$r['chave']) || in_array($r['chave'], ['exemplo', 'pend_fora', 'importacao'], true)) $por->execute([$r['chave'], $r['valor']]);
    foreach ((array)($l['fila'] ?? []) as $id) apagarCfg('apagar_' . (int)$id);   // quem ia sair do iDFace não sai mais
    $comp = array_values(array_filter(array_map('intval', (array)($l['comp'] ?? []))));
    if ($comp) $db->exec('INSERT OR IGNORE INTO compromissos SELECT * FROM copia.compromissos WHERE id IN (' . implode(',', $comp) . ')');
    apagarCfg('limpeza'); unset($GLOBALS['_exemplo']);
    $n = (int)$db->query('SELECT COUNT(*) FROM criancas')->fetchColumn();
    registrar('cad', null, 'CADASTROS', 'A limpeza foi desfeita: ' . $n . ($n === 1 ? ' cadastro voltou' : ' cadastros voltaram') . ', com as vendas e as visitas · por ' . primeiroNome((string)$u['nome']));
    $db->commit();
  } catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack(); registrarErro('desfazer a limpeza: ' . $e->getMessage());
    try { $db->exec('DETACH DATABASE copia'); } catch (Throwable $x) { }
    falha('Não consegui desfazer a limpeza. Nada foi mudado.', 500);
  }
  try { $db->exec('DETACH DATABASE copia'); } catch (Throwable $e) { }
  return ['voltaram' => $n];
}
