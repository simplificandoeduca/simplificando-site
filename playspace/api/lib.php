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
const FORMAS_PAGAMENTO = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'credito' => 'Crédito', 'debito' => 'Débito'];

function falha(string $msg, int $st = 400): void { http_response_code($st); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => false, 'erro' => $msg], JSON_UNESCAPED_UNICODE); exit; }
function responder(array $d = []): void {
  header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
  $j = (string)json_encode(['ok' => true] + $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  // 1.01: com milhares de cadastros a lista fica grande. Ela vai comprimida quando a tela pede (gz = 1), e a tela só pede depois de conferir
  // que lê a resposta comprimida deste servidor. Se o servidor já comprime sozinho, não mexe.
  if (!empty($GLOBALS['_gz']) && strlen($j) > 8000 && function_exists('gzencode') && !headers_sent() && !ini_get('zlib.output_compression') && !ini_get('output_handler')) {
    $z = @gzencode($j, 4);
    if (is_string($z) && $z !== '') { header('Content-Encoding: gzip'); header('Vary: Accept-Encoding'); header('Content-Length: ' . strlen($z)); echo $z; exit; }
  }
  echo $j; exit;
}
function agora(): string { return date('Y-m-d H:i:s'); }
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

/* ---------- onde os dados ficam ---------- */
function pastaDados(): string {
  static $pasta = null;
  if ($pasta) return $pasta;
  $raiz = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
  foreach ([dirname($raiz) . '/playspace-dados', __DIR__ . '/dados'] as $dir) {
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
  return $pdo;
}
/* ---------- 1.06: unidades ---------- */
// A Play Space tem mais de uma unidade. O gestor cadastra as unidades em Ajustes; cada aparelho guarda em qual delas está e manda em todo
// pedido (cabeçalho X-Play-Unidade). O servidor confere se a unidade existe e guarda na entrada, na venda e na linha do dia.
// Unidade removida não some dos registros antigos: fica inativa (some só das escolhas).
const UNIDADES_DE_FABRICA = ['ANÁLIA FRANCO', 'MORUMBI'];
const UNIDADES_MAX = 30;
function unidadePublica(array $u): array { return ['id' => (int)$u['id'], 'nome' => (string)$u['nome'], 'ativo' => !empty($u['ativo'])]; }
function unidades(): array { return array_map('unidadePublica', db()->query('SELECT * FROM unidades ORDER BY ativo DESC, id')->fetchAll()); }   // as ativas primeiro; as inativas vão junto, para a tela saber o nome das antigas
function unidadeDoPedido(): ?int {
  if (!array_key_exists('_unidade', $GLOBALS)) {
    $id = (int)($_SERVER['HTTP_X_PLAY_UNIDADE'] ?? 0); $GLOBALS['_unidade'] = null;
    if ($id > 0) { $q = db()->prepare('SELECT id FROM unidades WHERE id = ? AND ativo = 1'); $q->execute([$id]); if ($q->fetchColumn()) $GLOBALS['_unidade'] = $id; }
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
  'cupomLoja' => 'PLAY SPACE', 'cupomTel' => '', 'cupomAviso' => 'ATENÇÃO: SOMENTE O RESPONSÁVEL QUE ASSINAR A ENTRADA DA CRIANÇA PODERÁ ASSINAR SUA SAÍDA.'];
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
  if (!MODULO_WHATS) $r['autoMsg'] = 'nao';   // com o módulo desligado, nenhum aviso automático sai
  $r['festa'] = FESTA_DE_FABRICA;   // 1.12: a tabela da festa vai com a regra para a tela (a de fábrica: não fica gravada)
  return $r;
}
function dur(int $min): string { $min = max(0, $min); return intdiv($min, 60) . 'h' . ($min % 60 ? str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT) : ''); }   // sempre em horas: 0h30, 1h, 1h30 (a loja vende meia hora e hora)
// O tempo é sempre debitado em períodos inteiros (não existe período quebrado): quem ficou 11 min debita 30.
// O período é o do serviço de tempo de brincar (Recreação, 30 min). O período seguinte só conta depois da tolerância:
// com 30 min e tolerância de 9, até 39:00 é um período; passou disso, dois; depois de 1h09, três.
function servicoTempo(): ?array { foreach ((array)regra()['servicos'] as $s) if (!empty($s['banco']) && ($s['modo'] ?? '') === 'periodo') return $s; return null; }
function periodo(): int { $s = servicoTempo(); return max(5, (int)($s['min'] ?? 30)); }
function periodosDe(float $minutos): int { return max(1, (int)ceil(($minutos - max(0, (int)regra()['tolerancia'])) / periodo())); }
function cobrar(float $minutos): int { return periodosDe($minutos) * periodo(); }
// o preço da recreação avulsa para esta criança: a criança PCD paga com o desconto (50% de fábrica)
function pcdDesc(): int { return max(0, min(100, (int)(regra()['pcdDesc'] ?? 50))); }
function precoPcd(array $c, float $v): float { return !empty($c['pcd']) ? round($v * (100 - pcdDesc()) / 100, 2) : $v; }
// quantos minutos a visita conta até $fim: no máximo até a hora em que a loja fecha (no dia da entrada) e nunca mais de 12 h.
// Assim uma saída esquecida não vira cobrança a noite toda (a supervisão corrige a hora da saída depois).
function minutosDaVisita(array $c, int $fim): float {
  $ent = strtotime((string)$c['entrou']); $f = (int)(regra()['fecha'] ?? 22); $teto = $ent + 43200;
  $fecha = strtotime(date('Y-m-d', $ent) . ($f >= 24 ? ' 23:59:59' : sprintf(' %02d:00:00', max(0, $f))));
  if ($fecha > $ent && !ehExemplo((int)$c['id'])) $teto = min($teto, $fecha);   // entrou depois da hora de fechar, ou é criança do teste do rodapé (que precisa funcionar a qualquer hora): vale só o limite de 12 h
  return max(0, (min($fim, $teto) - $ent) / 60);
}
function hm(int $min): string { $min = max(0, $min); return intdiv($min, 60) . 'h' . ($min % 60 ? str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT) : ''); }
function restante(array $c): int { return max(0, (int)$c['pacote_min'] - (int)$c['usado_min']); }
function minDentro(array $c): float { return $c['entrou'] ? (time() - strtotime((string)$c['entrou'])) / 60 : 0; }
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
function eu(): ?array {
  $id = (int)($_SESSION['uid'] ?? 0); if (!$id) return null;
  $q = db()->prepare('SELECT id, nome, login, papel FROM usuarios WHERE id = ? AND ativo = 1'); $q->execute([$id]);
  return $q->fetch() ?: null;
}
function exigir(array $papeis = []): array {
  $u = eu(); if (!$u) falha('Entre de novo para continuar.', 401);
  if ($papeis && !in_array($u['papel'], $papeis, true)) falha('Só ' . implode(' ou ', array_map(fn($p) => PAPEIS[$p] ?? $p, $papeis)) . ' faz isso.', 403);
  return $u;
}
/* ---------- 1.21: a blindagem do operador ---------- */
// Quem estorna desfaz e cancela o que já tem valor: a venda registrada, o pagamento recebido, a saída e a devolução que receberam.
// De fábrica é só o gestor (regra.estorno). Quem não estorna continua fazendo tudo da recepção (cadastro, entrada, venda, saída,
// devolução, receber); o que ele errar e tiver valor é cancelado por quem estorna, com o motivo, e fica na lista de conferência.
function podeEstornar(array $u): bool { $q = (string)(regra()['estorno'] ?? 'gestor'); $p = (string)($u['papel'] ?? ''); return $p === 'gestor' || $q === 'todos' || ($q === 'supervisor' && $p === 'supervisor'); }
function quemEstorna(): string { return (string)(regra()['estorno'] ?? 'gestor') === 'supervisor' ? 'o gestor ou o supervisor' : 'o gestor'; }
const MOTIVO_DESFEITA = 'DESFEITA NA HORA (TOQUE ERRADO)';   // o motivo da entrada desfeita pelo botão do aviso por quem não estorna

/* ---------- registros (o painel "Hoje na recepção") e as mensagens ---------- */
function registrar(string $tipo, ?int $criancaId, string $nome, string $texto, ?string $quando = null, $unidade = false): int {   // 1.06: $unidade = false usa a do aparelho que fez o pedido
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
function fazerEntrada(array $c, string $origem = 'iDFace', ?string $quando = null): array {
  $db = db(); $quando = $quando ?: agora();
  $db->prepare('UPDATE criancas SET entrou = ?, unidade_id = ? WHERE id = ?')->execute([$quando, unidadeDoPedido(), $c['id']]);   // 1.06: a unidade do aparelho em que a entrada foi dada (o iDFace não diz: fica sem)
  $c['entrou'] = $quando; $c['unidade_id'] = unidadeDoPedido();
  $reg = registrar('ent', (int)$c['id'], $c['nome'], $origem === 'iDFace' ? 'Rosto reconhecido na entrada' : (modoManual() ? 'Entrada registrada' : 'Entrada registrada à mão'), $quando);
  $msg = null;
  if (regra()['autoMsg'] !== 'nao') {
    $texto = 'Oi, ' . nomeMsg(primeiroNome($c['resp'])) . '! ' . nomeMsg($c['nome']) . ' chegou na Play Space às ' . date('H:i', strtotime($quando)) . ' 💛 Qualquer coisa a gente te chama por aqui.';   // sem falar de tempo nem de saldo
    $msg = mandarMensagem($c, $texto, true, $c['foto'] ?: '');
  }
  return ['registro' => $reg, 'mensagem' => $msg];
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
  if (!$c['entrou'] || !servicoTempo()) return [];
  if ((string)$c['entrou'] < (string)cfg('cobranca_desde', '') && !ehExemplo((int)$c['id'])) return [];   // já estava no espaço quando esta regra chegou: não gera cobrança para trás
  $fim = $quando ? strtotime($quando) : time();
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
  $nada = ['n' => 0, 'valor' => 0.0]; if (!$c['entrou'] || !servicoTempo()) return $nada;
  $db = db(); $p = periodo(); $fim = $quando ? strtotime($quando) : time();
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
function fazerSaida(array $c, string $origem = 'iDFace', ?string $quando = null, bool $avulso = false): array {   // $avulso (1.11): a visita acabou de ser vendida avulsa (venderVisitaAvulsa)
  $db = db(); $quando = $quando ?: agora();
  $plano = planoDaCrianca($c);   // 1.15: antes de debitar: recreação avulsa, ou pacote (para a linha da saída dizer o que é o valor a receber)
  if (acertarCobrancas($c, $quando)) $c = crianca((int)$c['id']);   // o que a visita precisa até a hora da saída e ainda não tinha sido lançado nasce agora
  if (!$avulso && desfazerCobrancasSobrando($c, $quando)['n']) $c = crianca((int)$c['id']);   // 1.00: o que o cliente comprou durante a visita cobre o tempo: a cobrança por atraso que sobrou é desfeita
  $min = minutosDaVisita($c, strtotime($quando)); $deb = min(cobrar($min), restante($c)); $atraso = atrasoDaVisita((int)$c['id'], (string)$c['entrou']);   // nunca debita mais do que há (a cobrança por atraso já cobriu o que faltava)
  $db->prepare('INSERT INTO visitas (crianca_id, entrou, saiu, debitado_min, origem, unidade_id) VALUES (?, ?, ?, ?, ?, ?)')->execute([$c['id'], $c['entrou'], $quando, $deb, $origem, $c['unidade_id'] ?? null]);   // 1.06: a visita fica com a unidade em que a criança entrou
  $visita = (int)$db->lastInsertId();
  $sap = isset($c['sapateira']) && $c['sapateira'] !== null ? (int)$c['sapateira'] : 0;   // a sapateira fica livre na saída
  $db->prepare('UPDATE criancas SET entrou = NULL, usado_min = usado_min + ?, sapateira = NULL WHERE id = ?')->execute([$deb, $c['id']]);
  $c['usado_min'] += $deb; $c['entrou'] = null;
  $r = restante($c);
  if ($r < periodo() && regra()['bloqueioAuto'] === 'sim') bloquearAcesso($c, false);   // não sobra um período inteiro: o iDFace passa a recusar até venderem tempo
  // 1.18: na recreação avulsa nada é "debitado" (o cliente não comprou período): a linha diz a recreação que a visita somou e, se houver, o tempo que saiu do que ele já tinha (voucher)
  $proprias = max(0, $deb - (int)$atraso['n'] * periodo());
  $reg = registrar('sai', (int)$c['id'], $c['nome'], 'Ficou ' . dur((int)floor($min)) . ($plano === 'pacote' ? ' · ' . dur($deb) . ' debitados' : ($proprias > 0 ? ' · ' . dur($proprias) . ' do tempo que já tinha' : '')) . ($r > 0 ? ' · restam ' . hm($r) : '') . ($atraso['n'] ? ' · ' . textoAutos($atraso, $plano) . ' a receber' : '') . ($sap ? ' · sapato na sapateira ' . $sap : '') . ($origem === 'iDFace' || modoManual() ? '' : ' · saída à mão'), $quando);
  $msg = null;
  if (regra()['autoMsg'] === 'ambos') {
    $texto = nomeMsg($c['nome']) . ' saiu às ' . date('H:i', strtotime($quando)) . '. Até a próxima! 🧸';
    $msg = mandarMensagem($c, $texto, true);
  }
  return ['registro' => $reg, 'visita' => $visita, 'debitado' => $deb, 'minutos' => (int)floor($min), 'atraso' => $atraso, 'plano' => $plano, 'mensagem' => $msg, 'sapateira' => $sap ?: null];
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
  $fora = max(0, time() - strtotime((string)$v['saiu']));
  if ($fora > $lim * 60 + 10) falha('Já passou de ' . $lim . ' min da saída de ' . $c['nome'] . ' (saiu às ' . substr((string)$v['saiu'], 11, 5) . '). Agora é uma entrada nova.');   // 10 s de folga para o pedido que já estava a caminho
  $q = $db->prepare("SELECT 1 FROM cancelamentos WHERE crianca_id = ? AND entrou >= ? AND tipo = ''"); $q->execute([$c['id'], $v['saiu']]);   // uma entrada feita depois dessa saída e cancelada
  if ($q->fetchColumn()) falha('Depois dessa saída houve uma entrada cancelada. Faça uma entrada nova.');
  // a sapateira que a criança tinha na saída volta para ela, se ainda estiver livre
  $q = $db->prepare("SELECT id, texto FROM registros WHERE crianca_id = ? AND tipo = 'sai' AND quando = ? ORDER BY id DESC LIMIT 1"); $q->execute([$c['id'], $v['saiu']]); $linha = $q->fetch() ?: null;
  $sap = $linha && preg_match('/sapateira (\d+)/u', (string)$linha['texto'], $m) ? (int)$m[1] : 0;
  if ($sap && ($sap > (int)regra()['sapateiras'] || donoSapateira($sap, (int)$c['id']))) $sap = 0;
  $agora = agora(); $min = intdiv($fora, 60);
  $db->beginTransaction();
  try {
    $db->prepare('UPDATE criancas SET entrou = ?, usado_min = MAX(0, usado_min - ?), sapateira = ? WHERE id = ?')->execute([$v['entrou'], (int)$v['debitado_min'], $sap ?: null, $c['id']]);
    $db->prepare('DELETE FROM visitas WHERE id = ?')->execute([$v['id']]);
    if ($linha) $db->prepare('UPDATE registros SET texto = texto || ? WHERE id = ?')->execute([' · visita reaberta às ' . substr($agora, 11, 5), $linha['id']]);
    $reg = registrar('reab', (int)$c['id'], $c['nome'], 'Visita reaberta: a saída das ' . substr((string)$v['saiu'], 11, 5) . ' foi desfeita (' . ($min < 1 ? 'menos de 1 min' : $min . ' min') . ' fora) · o tempo continua desde a entrada das ' . substr((string)$v['entrou'], 11, 5) . ' · por ' . primeiroNome($u['nome']), $agora);
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
  $db->beginTransaction();
  try {
    $db->prepare('UPDATE visitas SET saiu = ?, debitado_min = ? WHERE id = ?')->execute([$nova, $deb1, $v['id']]);
    $db->prepare("UPDATE registros SET texto = texto || ' · corrigida' WHERE crianca_id = ? AND tipo = 'sai' AND quando = ? AND texto NOT LIKE '% · corrigida'")->execute([$c['id'], $v['saiu']]);   // a linha da saída antiga fica marcada
    $db->prepare('UPDATE criancas SET usado_min = MAX(0, usado_min - ?) WHERE id = ?')->execute([$dif, $c['id']]);
    foreach ($tirar as $x) {
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$x['minutos'], $c['id']]);
      $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([$x['id']]); $valor += (float)$x['valor'];
      if (!empty($x['registro'])) $db->prepare("UPDATE registros SET texto = texto || ' · desfeita' WHERE id = ? AND crianca_id = ? AND texto NOT LIKE '% · desfeita'")->execute([(int)$x['registro'], $c['id']]);
    }
    $reg = registrar('corr', (int)$c['id'], $c['nome'], 'Saída corrigida de ' . substr((string)$v['saiu'], 11, 5) . ' para ' . substr($nova, 11, 5) . ' · ' . dur($deb1) . ' debitados' . ($dif > 0 ? ' (eram ' . dur($deb0) . ')' : '')
      . ($tirar ? ' · ' . (count($tirar) > 1 ? count($tirar) . ' cobranças de recreação desfeitas' : 'cobrança de recreação desfeita') . ': ' . dinheiro($valor) : '') . ' · Motivo: ' . $motivo . ' · por ' . primeiroNome($u['nome']));
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
  $c = crianca((int)$c['id']);
  if (!$c['entrou'] && restante($c) >= $p && isset($c['liberado']) && !(int)$c['liberado'] && regra()['bloqueioAuto'] === 'sim') bloquearAcesso($c, true);   // voltou a ter um período guardado
  return ['registro' => $reg, 'debitado' => $deb1, 'antes' => $deb0, 'desfeitas' => count($tirar), 'valor' => $valor, 'saiu' => $nova];
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
function nomeDaVenda(array $v): string { // "Recreação · 30 min", "Carrinho de bebê · das 10h às 22h", "Pacote de 5h"
  $min = (int)($v['minutos'] ?? 0); $s = trim((string)($v['servico'] ?? '')); $it = trim((string)($v['itens'] ?? ''));
  if ($s !== '') return $s . ($min > 0 ? ' · ' . dur($min) : '') . ($it !== '' ? ' · ' . $it : '');
  return $min > 0 ? nomePacoteVenda($min, $it) : ($it !== '' ? $it : 'Produto');
}

/* ---------- produtos da loja: nome, valor e estoque ---------- */
// estoque NULL = a loja não controla o estoque desse produto. A venda nunca é barrada pelo estoque: ele pode ficar negativo, e a tela mostra em vermelho para a gestora acertar
function produto(int $id): ?array { $q = db()->prepare('SELECT * FROM produtos WHERE id = ?'); $q->execute([$id]); return $q->fetch() ?: null; }
function produtoPublico(array $p): array { return ['id' => (int)$p['id'], 'n' => (string)$p['nome'], 'v' => (float)$p['valor'], 'est' => $p['estoque'] === null ? null : (int)$p['estoque']]; }
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
  $ins = $db->prepare('INSERT INTO pagamentos (venda_id, crianca_id, forma, valor, quando, por) VALUES (?, ?, ?, ?, ?, ?)');
  foreach ($vendaIds as $vid) {
    $q->execute([(int)$vid]); $falta = $q->fetchColumn(); if ($falta === false) continue; $falta = round((float)$falta, 2);
    foreach ($formas as $f => $resta) {
      if ($falta < 0.005) break; if ($resta < 0.005) continue; $x = round(min($falta, $resta), 2);
      $ins->execute([(int)$vid, $criancaId, $f, $x, $quando, $por]); $formas[$f] = round($resta - $x, 2); $falta = round($falta - $x, 2); $gravado[$f] = round(($gravado[$f] ?? 0) + $x, 2);
    }
  }
  return $gravado;
}
/* ---------- 1.04: desconto do gestor na tela de pagamento ---------- */
// Só o gestor dá desconto: a tela manda o valor em reais (e o percentual, quando foi digitado em %) e o motivo. O desconto sai do valor das
// vendas, em proporção ao que falta receber de cada uma, e fica guardado nelas (desc_valor, desc_motivo, desc_por), com uma linha no dia.
// O pagamento fecha com o total já descontado.
function lerDesconto($d, array $u): ?array { // ['valor' => em reais, 'pct' => o percentual digitado (ou 0), 'motivo'], ou null se não há desconto
  if (!is_array($d)) return null; $v = round((float)($d['valor'] ?? 0), 2); if ($v <= 0) return null;
  if (($u['papel'] ?? '') !== 'gestor') falha('Só o gestor dá desconto.', 403);
  $pct = round((float)($d['pct'] ?? 0), 2); if ($pct < 0 || $pct > 100) falha('O desconto não passa de 100%.');
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
  foreach ($rep as $vid => $x) $q->execute([$x, $x, $d['motivo'], $d['motivo'], (int)$u['id'], (int)$vid]);
  $total = round(array_sum($rep), 2); $pct = $d['pct'] > 0 ? ' (' . rtrim(rtrim(number_format((float)$d['pct'], 2, ',', ''), '0'), ',') . '%)' : '';
  $reg = registrar('desc', (int)$c['id'], $c['nome'], 'Desconto · ' . dinheiro($total) . $pct . ' · Motivo: ' . $d['motivo'] . ' · por ' . primeiroNome((string)$u['nome']));
  return ['valor' => $total, 'vendas' => $rep, 'registro' => $reg, 'motivo' => $d['motivo']];
}
// o Desfazer: devolve às vendas o desconto que tinha sido dado ([venda => desconto]), tira o motivo dele e apaga a linha do dia
function tirarDesconto(int $criancaId, array $rep, int $registro, string $motivo = ''): void {
  $db = db(); $fim = ' + ' . $motivo; $q = $db->prepare("UPDATE vendas SET valor = ROUND(valor + ?, 2), desc_valor = ROUND(desc_valor - ?, 2),
    desc_motivo = CASE WHEN desc_valor - ? < 0.005 THEN '' WHEN ? != '' AND substr(desc_motivo, -length(?)) = ? THEN substr(desc_motivo, 1, length(desc_motivo) - length(?)) ELSE desc_motivo END,
    desc_por = CASE WHEN desc_valor - ? < 0.005 THEN NULL ELSE desc_por END WHERE id = ? AND crianca_id = ? AND desc_valor >= ? - 0.005");
  foreach ($rep as $vid => $x) { $x = round((float)$x, 2); if ($x > 0) $q->execute([$x, $x, $x, $motivo, $fim, $fim, $fim, $x, (int)$vid, $criancaId, $x]); }
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
function pendItensDeTodos(): array {
  $db = db(); $hoje = date('Y-m-d') . ' 00:00:00';
  // o piso: nenhuma venda de antes de hoje (ou do dia em que entrou quem está há mais tempo no espaço) pode ser "desta visita".
  // Com ele a consulta anda só pelas vendas recentes, sem buscar criança por criança (com anos de vendas no banco, de 290 ms para 10 ms)
  $min = (string)$db->query('SELECT MIN(entrou) FROM criancas WHERE entrou IS NOT NULL')->fetchColumn();
  $piso = $min !== '' && substr($min, 0, 10) . ' 00:00:00' < $hoje ? substr($min, 0, 10) . ' 00:00:00' : $hoje;
  $falta = 'v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0)';
  $q = $db->prepare("SELECT v.crianca_id c, v.servico, v.minutos, v.itens, $falta f FROM vendas v CROSS JOIN criancas k0 ON k0.id = v.crianca_id WHERE v.quando >= ? AND v.auto = 0 AND v.valor > 0 AND v.servico != ?
    AND v.quando >= CASE WHEN k0.entrou IS NOT NULL AND substr(k0.entrou, 1, 10) < substr(?, 1, 10) THEN substr(k0.entrou, 1, 10) || ' 00:00:00' ELSE ? END
    AND v.quando >= COALESCE((SELECT MAX(x.saiu) FROM visitas x WHERE x.crianca_id = v.crianca_id), '')
    AND $falta > 0.004 ORDER BY v.id");   // 1.21: só a saída fecha a conta do que há para receber (a entrada cancelada, não)
  $q->execute([$piso, SERV_IMPORTADO, $hoje, $hoje]); $o = [];
  foreach ($q->fetchAll() as $l) $o[(int)$l['c']][] = ['n' => nomeDaVenda($l), 'v' => round((float)$l['f'], 2)];   // juntar por criança fica aqui: agrupar no SQL faria a consulta andar pelo índice da criança
  return $o;
}
// o que entrou por cada forma desde $desde (a tela mostra em Hoje na recepção)
function pagamentosDesde(string $desde): array {
  $o = array_fill_keys(array_keys(FORMAS_PAGAMENTO), 0.0); $q = db()->prepare('SELECT forma, SUM(valor) t FROM pagamentos WHERE quando > ? GROUP BY forma'); $q->execute([$desde]);
  foreach ($q->fetchAll() as $l) $o[(string)$l['forma']] = round((float)$l['t'], 2);
  return $o;
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
  $q = $db->prepare('SELECT v.id, v.servico, v.itens, v.minutos, SUM(p.valor) AS pago FROM pagamentos p JOIN vendas v ON v.id = p.venda_id WHERE p.cupom_id = ? GROUP BY v.id ORDER BY v.id'); $q->execute([$id]);
  foreach ($q->fetchAll() as $v) { $itens[] = ['n' => nomeDaVenda($v), 'v' => round((float)$v['pago'], 2), 'venda' => (int)$v['id']]; $total += (float)$v['pago']; }
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
function alugEmAberto(?array $sv): bool { return $sv !== null && !empty($sv['volta']) && empty($sv['banco']) && ($sv['modo'] ?? 'fixo') === 'periodo' && empty($sv['porTempo']); }
function alugueis(?int $criancaId = null, ?string $servico = null): array {
  $sql = "SELECT v.id, v.crianca_id, v.alug, v.alug_min, v.qtd, v.servico, v.itens, v.quando, v.unidade_id, v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) AS falta FROM vendas v JOIN criancas c ON c.id = v.crianca_id AND c.ativo = 1 WHERE v.alug != '' AND v.voltou IS NULL";
  $arg = []; if ($criancaId !== null) { $sql .= ' AND v.crianca_id = ?'; $arg[] = $criancaId; } if ($servico !== null) { $sql .= ' AND v.alug = ?'; $arg[] = $servico; }
  $q = db()->prepare($sql . ' ORDER BY v.id'); $q->execute($arg); $g = [];
  foreach ($q->fetchAll() as $v) {
    $k = (int)$v['crianca_id'] . '-' . $v['alug']; $m = (int)$v['alug_min'];
    if (!isset($g[$k])) $g[$k] = ['id' => $k, 'crianca' => (int)$v['crianca_id'], 's' => (string)$v['alug'], 'n' => (string)$v['servico'], 'q' => 0, 'min' => 0, 'ini' => $v['quando'], 'vendas' => [], 'pend' => 0.0, 'uni' => $v['unidade_id'] === null ? null : (int)$v['unidade_id']];   // 1.06: uni = a unidade de onde saiu
    $g[$k]['pend'] = round($g[$k]['pend'] + max(0.0, (float)$v['falta']), 2);   // 1.02: o que ainda não foi pago (o cliente ficou de pagar na devolução)
    $g[$k]['min'] += $m; $g[$k]['q'] = $m > 0 ? max(1, $g[$k]['q']) : $g[$k]['q'] + max(1, (int)$v['qtd']); $g[$k]['vendas'][] = (int)$v['id'];
    if ($m <= 0 && (string)$v['itens'] === ITENS_ALUG_ABERTO) $g[$k]['ab'] = true;   // 1.20: a saída nasceu em aberto (sem valor: a conta fecha na devolução)
  }
  // 1.20: em aberto é da própria saída, e não de como o serviço está agora em Ajustes (o gestor pode trocar "quando a conta fecha" com um fusquinha fora).
  // O valor e o tamanho do período saem do serviço na hora da devolução: ele precisa continuar cobrado a cada período
  foreach ($g as $k => $x) { $sv = servicoPorId($x['s']); $g[$k]['aberto'] = !empty($x['ab']) && $x['min'] <= 0 && $sv !== null && ($sv['modo'] ?? 'fixo') === 'periodo'; unset($g[$k]['ab']); if ($g[$k]['aberto']) $g[$k]['q'] = 1; }
  return array_values($g);
}
// "Devolveu": o que estava fora volta para a loja e sai da lista. Fica anotado em Hoje na recepção, com quanto tempo ficou fora e quem marcou.
// Nada é cobrado aqui: se passou do tempo comprado, a linha diz quanto passou e a equipe vende o período a mais no atendimento
// 1.00: o que há para receber na devolução do que foi alugado por tempo (o fusquinha): os períodos inteiros que passaram do tempo comprado,
// com a mesma tolerância da recreação (e o relógio para na hora em que a loja fecha, como o da visita). O que tem valor único
// (o carrinho de bebê) não tem tempo a mais.
// 1.20: em aberto (o fusquinha, que só "sai" e acerta na devolução), a conta é o tempo todo: o primeiro período conta sempre, e o seguinte
// só depois da tolerância (com 30 min e tolerância de 9: até 39:00 é um período; passou disso, dois). Vem marcada com 'aberto' => true.
function aluguelExtra(array $a): array {
  $sv = servicoPorId((string)$a['s']);
  $ini = strtotime((string)$a['ini']); $f = (int)(regra()['fecha'] ?? 22); $teto = $ini + 43200;
  $fecha = strtotime(date('Y-m-d', $ini) . ($f >= 24 ? ' 23:59:59' : sprintf(' %02d:00:00', max(0, $f))));
  if ($fecha > $ini && !ehExemplo((int)$a['crianca'])) $teto = min($teto, $fecha);
  $ficou = max(0, (min(time(), $teto) - $ini) / 60); $r = ['n' => 0, 'min' => 0, 'valor' => 0.0, 'per' => 0, 'un' => 0.0];
  if (!$sv || ($sv['modo'] ?? 'fixo') !== 'periodo') return $r;
  $per = max(1, (int)($sv['min'] ?? 30)); $tol = max(0, (int)regra()['tolerancia']);
  if ((int)$a['min'] <= 0) { if (empty($a['aberto'])) return $r;
    $n = max(1, (int)ceil(($ficou - $tol) / $per));
    return ['n' => $n, 'min' => $n * $per, 'valor' => round($n * (float)$sv['v'], 2), 'per' => $per, 'un' => (float)$sv['v'], 'aberto' => true]; }
  $n = max(0, max(1, (int)ceil(($ficou - $tol) / $per)) - (int)ceil((int)$a['min'] / $per));
  return ['n' => $n, 'min' => $n * $per, 'valor' => round($n * (float)$sv['v'], 2), 'per' => $per, 'un' => (float)$sv['v']];
}
// "Devolveu": o que estava fora volta para a loja e sai da lista. Fica anotado em Hoje na recepção, com quanto tempo ficou fora e quem marcou.
// 1.00: se passou do tempo comprado, o período a mais é cobrado aqui e a devolução só fecha com a forma de pagamento.
function devolverAluguel(array $c, string $servico, array $u, array $pg = []): array {
  $db = db(); $g = alugueis((int)$c['id'], $servico); if (!$g) falha('Isso já foi marcado como devolvido.', 409);
  $a = $g[0]; $agora = agora(); $marcas = implode(',', array_map('intval', $a['vendas'])); $x = aluguelExtra($a); $aberto = !empty($x['aberto']);   // 1.20: em aberto, a conta inteira nasce aqui
  $pend = round((float)($a['pend'] ?? 0), 2); $total = round($x['valor'] + $pend, 2);   // 1.02: o que ficou a pagar do carrinho entra na conta da devolução
  if ($total > 0) {
    if (!$pg) falha($aberto ? $a['n'] . ': falta receber ' . dinheiro($total) . ' (' . dur($x['min']) . '). Escolha a forma de pagamento.'
      : ($x['valor'] > 0 ? $a['n'] . ' passou do tempo comprado: falta receber ' . dinheiro($total) . ' (' . dur($x['min']) . ' a mais' . ($pend > 0 ? ', mais o que não foi pago' : '') . '). Escolha a forma de pagamento.'
      : $a['n'] . ' ainda não foi pago: falta receber ' . dinheiro($total) . '. Escolha a forma de pagamento.'), 409);
    if (abs(array_sum($pg) - $total) >= 0.01) falha('O pagamento (' . dinheiro((float)array_sum($pg)) . ') não fecha com o valor a receber, que é ' . dinheiro($total) . '. Feche e abra a devolução de novo.', 409);
  }
  $qv = $db->prepare("UPDATE vendas SET voltou = ? WHERE id IN ($marcas) AND voltou IS NULL"); $qv->execute([$agora]);
  if (!$qv->rowCount()) falha('Isso já foi marcado como devolvido.', 409);   // 1.20: outro aparelho devolveu no mesmo instante: este não recebe de novo
  $ficou = max(0, (int)floor((time() - strtotime((string)$a['ini'])) / 60)); $passou = $a['min'] > 0 ? max(0, $ficou - $a['min']) : 0;
  $reg = registrar('dev', (int)$c['id'], $c['nome'], ($a['q'] > 1 ? $a['q'] . ' × ' : '') . $a['n'] . ' · devolvido · ficou ' . dur($ficou)
    . ($aberto ? ' · ' . dur($x['min']) . ': ' . dinheiro($x['valor']) : ($a['min'] > 0 ? ' · tempo comprado: ' . dur($a['min']) . ($passou > 0 ? ' (passou ' . ($passou < 60 ? $passou . ' min' : dur($passou)) . ')' : '') : '')) . ' · por ' . primeiroNome($u['nome']), $agora);
  $r = ['vendas' => $a['vendas'], 'registro' => $reg, 'ficou' => $ficou, 'passou' => $passou, 'voltou' => $agora, 'nome' => $a['n'], 'q' => $a['q'], 'extra' => 0, 'registros' => [], 'pago' => '', 'valor' => 0.0, 'pend' => $pend];
  if ($total > 0) {
    $antesPg = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM pagamentos')->fetchColumn(); $ids = array_map('intval', $a['vendas']);
    if ($aberto) { // 1.20: a venda que marcou a saída ganha o valor e o tempo cobrado (fica uma linha só no histórico do cliente); o Desfazer devolve o zero
      if ($x['valor'] > 0) { $db->prepare('UPDATE vendas SET valor = ?, itens = ? WHERE id = ?')->execute([$x['valor'], dur($x['min']), $ids[0]]); $r['aberto'] = ['venda' => $ids[0], 'min' => $x['min'], 'valor' => $x['valor']]; $r['minutos'] = $x['min']; }
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
  $lim = max(0, (int)regra()['cancelaMin']); $seg = max(0, time() - strtotime((string)$a['ini']));
  return [$lim, $seg, !empty($a['aberto']) && $lim > 0 && $seg <= $lim * 60 + 10];
}
// $desfeita (1.21): desfeita na hora, pelo botão do aviso, por quem não estorna: sai sem olhar o prazo (o botão só existe nos instantes seguintes) e fica registrada
function cancelarAluguel(array $c, string $servico, array $u, string $motivo, bool $desfeita = false): array {
  $db = db(); $g = alugueis((int)$c['id'], $servico); if (!$g) falha('Isso já foi marcado como devolvido.', 409);
  $a = $g[0]; if (empty($a['aberto'])) falha('Só o que é cobrado na devolução pode ser cancelado por aqui.');
  [$lim, $seg, $noPrazo] = prazoCancelarAluguel($a); if ($desfeita) $noPrazo = true;
  if (!$noPrazo) falha($lim > 0 ? 'Já passou de ' . $lim . ' min da saída de ' . $a['n'] . ': não pode mais ser cancelado. O período é cobrado.' : 'A loja não cancela: o período é cobrado.', 403);
  if (mb_strlen($motivo) < 5) falha('Escreva o motivo do cancelamento: a supervisão vai conferir.');
  if (!podeEstornar($u) && pagoPorForma(array_map('intval', $a['vendas']))) falha('Isso já recebeu pagamento: só ' . quemEstorna() . ' cancela.', 403);   // 1.21 (a saída em aberto não tem pagamento: é só por garantia)
  $agora = agora(); $min = intdiv($seg, 60); $itens = [['n' => (string)$a['n'], 'm' => 0, 'v' => 0.0, 'tipo' => 'aluguel']];
  $db->beginTransaction();
  try {
    $qr = $db->prepare('SELECT registro FROM vendas WHERE id = ? AND crianca_id = ?');
    foreach (array_map('intval', $a['vendas']) as $vid) { $qr->execute([$vid, (int)$c['id']]); $rg = (int)$qr->fetchColumn();
      $db->prepare('DELETE FROM pagamentos WHERE venda_id = ? AND crianca_id = ?')->execute([$vid, (int)$c['id']]);   // (a saída em aberto não tem pagamento: é só para não sobrar nada)
      $db->prepare('DELETE FROM vendas WHERE id = ? AND crianca_id = ?')->execute([$vid, (int)$c['id']]);
      if ($rg) $db->prepare("UPDATE registros SET texto = texto || ' · cancelada' WHERE id = ? AND crianca_id = ? AND tipo = 'venda' AND texto NOT LIKE '% · cancelada'")->execute([$rg, (int)$c['id']]); }
    $db->prepare("INSERT INTO cancelamentos (crianca_id, nome, resp, entrou, quando, minutos, valor, itens, motivo, por, por_nome, papel, fora, tipo) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 0, 'aluguel')")
      ->execute([(int)$c['id'], $c['nome'], $c['resp'], (string)$a['ini'], $agora, $min, json_encode($itens, JSON_UNESCAPED_UNICODE), $motivo, (int)$u['id'], $u['nome'], $u['papel']]);
    $id = (int)$db->lastInsertId();
    $reg = registrar('canc', (int)$c['id'], $c['nome'], 'Saída das ' . substr((string)$a['ini'], 11, 5) . ' de ' . $a['n'] . ($desfeita ? ' desfeita na hora' : ' cancelada aos ' . ($min < 60 ? $min . ' min' : hm($min))) . ' · nada foi cobrado · Motivo: ' . $motivo . ' · por ' . primeiroNome($u['nome']), $agora);
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
  return ['cancelamento' => $id, 'registro' => $reg, 'minutos' => $min, 'nome' => (string)$a['n']];
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
  foreach ($db->query('SELECT id, usado_min FROM criancas WHERE entrou IS NOT NULL AND ativo = 1')->fetchAll() as $c) { $o[(int)$c['id']] = 'avulso'; $resta[(int)$c['id']] = (int)$c['usado_min']; }
  if (!$o) return $o;
  $q = $db->query('SELECT v.crianca_id, v.minutos, v.servico, v.itens, v.auto, v.voucher FROM criancas c CROSS JOIN vendas v ON v.crianca_id = c.id WHERE c.entrou IS NOT NULL AND c.ativo = 1 AND v.minutos > 0 ORDER BY v.id');
  foreach ($q->fetchAll() as $v) { $id = (int)$v['crianca_id']; $m = (int)$v['minutos'];
    if (!isset($o[$id]) || $o[$id] === 'pacote') continue;
    if ($resta[$id] >= $m) { $resta[$id] -= $m; continue; }   // esta compra já foi usada inteira
    $resta[$id] = 0; if (ehVendaDePacote($v)) $o[$id] = 'pacote';
  }
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
function papelGuardar(array $c, string $tipo, string $titulo, string $resumo, array $vias, ?array $u): int {
  $limpas = [];
  foreach (array_slice(array_values($vias), 0, 2) as $v) { $v = trim(strip_tags((string)$v, PAPEL_MARCAS)); if ($v === '') continue; if (strlen($v) > 60000) falha('O cupom é grande demais para guardar.'); $limpas[] = $v; }
  if (!$limpas) falha('O cupom veio vazio.');
  $j = (string)json_encode($limpas, JSON_UNESCAPED_UNICODE); $blob = function_exists('gzcompress') ? gzcompress($j, 6) : $j;
  $db = db(); $q = $db->prepare('INSERT INTO papeis (crianca_id, quando, tipo, titulo, resumo, vias, por, unidade_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
  $q->bindValue(1, (int)$c['id'], PDO::PARAM_INT); $q->bindValue(2, agora()); $q->bindValue(3, $tipo); $q->bindValue(4, $titulo); $q->bindValue(5, $resumo); $q->bindValue(6, $blob, PDO::PARAM_LOB);
  $q->bindValue(7, $u ? (int)$u['id'] : null, $u ? PDO::PARAM_INT : PDO::PARAM_NULL); $un = unidadeDoPedido(); $q->bindValue(8, $un, $un === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
  $q->execute(); $id = (int)$db->lastInsertId();
  if ($id % 25 === 0) $db->prepare('DELETE FROM papeis WHERE quando < ?')->execute([date('Y-m-d H:i:s', time() - PAPEL_DIAS * 86400)]);   // de vez em quando, os antigos saem
  return $id;
}
function papelVias(array $l): array {
  $b = is_resource($l['vias']) ? (string)stream_get_contents($l['vias']) : (string)$l['vias'];
  if ($b !== '' && $b[0] !== '[') { $x = function_exists('gzuncompress') ? @gzuncompress($b) : false; $b = $x === false ? '[]' : $x; }
  $v = json_decode($b, true); return is_array($v) ? array_values(array_map('strval', $v)) : [];
}
function papelPublico(array $l): array { return ['id' => (int)$l['id'], 'crianca' => (int)$l['crianca_id'], 'quando' => $l['quando'], 'tipo' => (string)$l['tipo'], 'titulo' => (string)$l['titulo'], 'resumo' => (string)$l['resumo'], 'por' => (string)($l['por_nome'] ?? '')]; }

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
  $t = regra()['festa']; $dia = (string)($fe['dia'] ?? ''); $n = (int)($fe['n'] ?? 0); $extra = (int)($fe['extra'] ?? 0); $data = trim((string)($fe['data'] ?? '')); $up = is_array($fe['up'] ?? null) ? $fe['up'] : null;
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
    $noSabado($dia);
    $i = array_search($n, $t['n'], true); if ($i === false) falha('A festa para ' . $n . ' crianças não está na tabela.');
    $v = (float)$t[$dia][$i];
    if (isset($fe['v']) && abs((float)$fe['v'] - $v) >= 0.005) falha('O valor da festa para ' . $n . ' crianças mudou para ' . dinheiro($v) . '. Feche e abra a Festa de novo.');
    $L[] = ['servico' => FESTA_SERVICO[$dia], 'min' => 0, 'valor' => $v, 'itens' => $n . ' crianças' . $quando];
  }
  if ($up) { // o upgrade: de uma festa para outra maior, do mesmo tipo. Cobra só a diferença entre as duas, pela tabela
    $ud = (string)($up['dia'] ?? ''); $de = (int)($up['de'] ?? 0); $para = (int)($up['para'] ?? 0);
    if (!isset(FESTA_SERVICO[$ud])) falha('O upgrade é de uma festa de semana ou de final de semana?');
    $noSabado($ud);
    $i = array_search($de, $t['n'], true); $j = array_search($para, $t['n'], true);
    if ($i === false || $j === false) falha('O upgrade é entre duas festas da tabela.');
    if ($j <= $i) falha('O upgrade é para uma festa maior: de ' . $de . ' para ' . $para . ' crianças não dá.');
    $v = round((float)$t[$ud][$j] - (float)$t[$ud][$i], 2); if ($v <= 0) falha('Não há diferença a cobrar de ' . $de . ' para ' . $para . ' crianças.');
    if (isset($up['v']) && abs((float)$up['v'] - $v) >= 0.005) falha('O valor do upgrade de ' . $de . ' para ' . $para . ' crianças mudou para ' . dinheiro($v) . '. Feche e abra a Festa de novo.');
    $tem = festaDoCliente($c);   // o que o sistema sabe da festa deste cliente: fica anotado quando não bate com o que a tela disse
    $nota = !$tem ? ' · sem festa anterior no sistema' : ($tem['n'] !== $de || $tem['dia'] !== $ud ? ' · no sistema: ' . FESTA_TIPOS[$tem['dia']] . ' de ' . $tem['n'] . ' crianças' : '');
    $L[] = ['servico' => 'UPGRADE DE FESTA', 'min' => 0, 'valor' => $v, 'itens' => 'de ' . $de . ' para ' . $para . ' crianças · ' . FESTA_TIPOS[$ud] . $quando . $nota];
  }
  if ($extra > 0) { // o adicional de festa: as crianças a mais do que o pacote
    $ve = (float)$t['extra'];
    if (isset($fe['ve']) && abs((float)$fe['ve'] - $ve) >= 0.005) falha('O valor do adicional de festa mudou para ' . dinheiro($ve) . ' por criança. Feche e abra a Festa de novo.');
    $L[] = ['servico' => 'ADICIONAL DE FESTA', 'min' => 0, 'valor' => round($extra * $ve, 2), 'itens' => $extra . ($extra === 1 ? ' criança' : ' crianças') . ($L ? '' : $quando)];
  }
  return $L;
}

/* ---------- cancelar a entrada sem cobrar ---------- */
// Até regra.cancelaMin minutos depois da entrada (minutos cheios, contados do segundo em que entrou), o próprio atendente cancela;
// depois disso, só gestor ou supervisor. Sempre com o motivo escrito: fica na tabela cancelamentos para a supervisão conferir.
function prazoCancelar(array $c): array { // [limite em minutos, segundos desde a entrada, ainda está no prazo]
  $lim = max(0, (int)regra()['cancelaMin']); $seg = $c['entrou'] ? max(0, time() - strtotime((string)$c['entrou'])) : 0;
  return [$lim, $seg, $lim > 0 && $c['entrou'] && $seg <= $lim * 60 + 10];   // 10 s de folga para o pedido que já estava a caminho quando o relógio da tela zerou
}
// as vendas que pagaram esta visita: as de hoje, feitas depois da última saída (ou do último cancelamento) da criança
// $paraReceber (1.21): a conta do que há para receber só fecha na saída: a entrada cancelada não tira da conta o que continuou cobrado e não foi pago
function vendasDaVisita(array $c, bool $paraReceber = false): array {
  $db = db(); $corte = date('Y-m-d') . ' 00:00:00';
  if (!empty($c['entrou']) && substr((string)$c['entrou'], 0, 10) < date('Y-m-d')) $corte = substr((string)$c['entrou'], 0, 10) . ' 00:00:00';   // 1.02: entrou ontem e ainda está no espaço (saída esquecida): a visita vale desde o dia da entrada
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
    $itens = []; $valor = 0.0; $tempo = 0;
    foreach ($alvo as $v) {
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$v['minutos'], $c['id']]);
      $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([$v['id']]); devolverEstoque($v);   // venda de produto que deixa de ser cobrada: o produto volta para o estoque
      if (!empty($v['registro'])) $db->prepare("UPDATE registros SET texto = texto || ' · cancelada' WHERE id = ? AND crianca_id = ? AND tipo IN ('venda', 'cobr') AND texto NOT LIKE '% · cancelada'")->execute([(int)$v['registro'], $c['id']]);
      $itens[] = ['n' => nomeDaVenda($v), 'm' => (int)$v['minutos'], 'v' => (float)$v['valor']]; $valor += (float)$v['valor']; $tempo += (int)$v['minutos'];
    }
    $db->prepare('UPDATE criancas SET entrou = NULL, sapateira = NULL WHERE id = ?')->execute([$c['id']]);   // sai sem debitar nada; a sapateira fica livre
    $db->prepare('INSERT INTO cancelamentos (crianca_id, nome, resp, entrou, quando, minutos, valor, itens, motivo, por, por_nome, papel, fora, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$c['id'], $c['nome'], $c['resp'], $c['entrou'], $agora, $minDentro, $valor, json_encode($itens, JSON_UNESCAPED_UNICODE), $motivo, (int)$u['id'], $u['nome'], $u['papel'], $noPrazo ? 0 : 1, $desfeita ? 'desfeita' : '']);
    $id = (int)$db->lastInsertId();
    $oque = $itens ? 'não cobrado: ' . implode(', ', array_map(fn($i) => $i['n'] . ' (' . dinheiro($i['v']) . ')', $itens)) : 'o tempo não foi descontado';
    $reg = registrar('canc', (int)$c['id'], $c['nome'], 'Entrada das ' . substr((string)$c['entrou'], 11, 5) . ($desfeita ? ' desfeita na hora' : ' cancelada aos ' . ($minDentro < 60 ? $minDentro . ' min' : hm((int)$minDentro))) . ' · ' . $oque . ($devolver ? ' · devolver ao cliente: ' . textoPagamento($devolver, true) : '') . ($ficou ? ' · ficou a receber: ' . implode(', ', array_map(fn($v) => nomeDaVenda($v) . ' (' . dinheiro((float)$v['falta']) . ')', $ficou)) : '') . ' · Motivo: ' . $motivo . ' · por ' . primeiroNome($u['nome']) . ($noPrazo ? '' : ' (fora do prazo' . ($lim > 0 ? ' de ' . $lim . ' min' : '') . ')'), $agora);
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
    'itens' => is_array($itens) ? $itens : [], 'motivo' => $x['motivo'], 'por' => $x['por_nome'], 'papel' => $x['papel'], 'fora' => (bool)$x['fora'], 'visto_por' => (string)$x['visto_por'], 'visto_em' => $x['visto_em'], 'tipo' => (string)($x['tipo'] ?? '')];
}
/* ---------- 1.21: cancelar uma venda (só quem estorna), com o motivo ---------- */
// Quem não estorna não desfaz a venda que registrou. Quando ela precisa mesmo sair (foi no nome errado, o cliente desistiu e a loja devolve
// o dinheiro), quem estorna cancela pelo histórico do cliente: as horas dela saem do saldo, o produto volta para o estoque, o pagamento
// dela sai (a linha do dia diz o que devolver ao cliente, por forma) e fica na lista de conferência, com o motivo e quem cancelou.
// Vale para o que a equipe vendeu nos últimos dias; o que a visita somou sozinha (a recreação avulsa) é acertado na saída, não por aqui.
const VENDA_CANCELA_DIAS = 7;
function vendaCancelavel(array $v): bool { return empty($v['auto']) && (string)($v['servico'] ?? '') !== SERV_IMPORTADO && time() - strtotime((string)$v['quando']) <= VENDA_CANCELA_DIAS * 86400; }
function cancelarVenda(int $vendaId, array $u, string $motivo): array {
  $db = db(); $q = $db->prepare('SELECT * FROM vendas WHERE id = ?'); $q->execute([$vendaId]); $v = $q->fetch();
  if (!$v) falha('Esta venda não existe mais: já foi desfeita ou cancelada.', 404);
  $c = crianca((int)$v['crianca_id']); if (!$c) falha('Cliente não encontrado.', 404);
  if (!empty($v['auto'])) falha('A recreação que a visita somou sozinha não é cancelada por aqui: ela é acertada na saída.');
  if ((string)($v['servico'] ?? '') === SERV_IMPORTADO) falha('As horas que vieram do sistema anterior não são uma venda: não dá para cancelar por aqui.');
  if (time() - strtotime((string)$v['quando']) > VENDA_CANCELA_DIAS * 86400) falha('Só dá para cancelar vendas dos últimos ' . VENDA_CANCELA_DIAS . ' dias.');
  if (mb_strlen($motivo) < 5) falha('Escreva o motivo do cancelamento.');
  $min = (int)$v['minutos']; $resta = restante($c);
  if ($min > 0 && $resta < $min) falha('As horas desta venda já foram usadas' . ($resta > 0 ? ' em parte: sobram ' . hm($resta) . ' de ' . hm($min) : '') . '. Não dá para cancelar a venda inteira.');
  $devolver = pagoPorForma([(int)$v['id']]); $agora = agora(); $nome = nomeDaVenda($v); $valor = round((float)$v['valor'], 2);
  $ha = max(0, intdiv(time() - strtotime((string)$v['quando']), 60)); $outroDia = substr((string)$v['quando'], 0, 10) !== substr($agora, 0, 10);
  $itens = [['n' => $nome, 'm' => $min, 'v' => $valor, 'tipo' => 'venda', 'dev' => $devolver ? textoPagamento($devolver, true) : '']];   // dev = o que a loja devolve ao cliente (o que ele já tinha pago), por forma
  $db->beginTransaction();
  try {
    $d = $db->prepare('DELETE FROM vendas WHERE id = ?'); $d->execute([(int)$v['id']]);   // o pagamento dela sai junto (o gatilho da tabela)
    if (!$d->rowCount()) { $db->rollBack(); falha('Esta venda não existe mais: já foi desfeita ou cancelada.', 404); }   // outro aparelho cancelou no mesmo instante
    if ($min > 0) $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([$min, (int)$c['id']]);
    devolverEstoque($v);   // era venda de produto: as unidades voltam para o estoque
    if (!empty($v['registro'])) $db->prepare("UPDATE registros SET texto = texto || ' · cancelada' WHERE id = ? AND crianca_id = ? AND tipo = 'venda' AND texto NOT LIKE '% · cancelada'")->execute([(int)$v['registro'], (int)$c['id']]);
    $db->prepare("INSERT INTO cancelamentos (crianca_id, nome, resp, entrou, quando, minutos, valor, itens, motivo, por, por_nome, papel, fora, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'venda')")
      ->execute([(int)$c['id'], $c['nome'], $c['resp'], (string)$v['quando'], $agora, $ha, $valor, json_encode($itens, JSON_UNESCAPED_UNICODE), $motivo, (int)$u['id'], $u['nome'], $u['papel']]);
    $id = (int)$db->lastInsertId();
    $reg = registrar('canc', (int)$c['id'], $c['nome'], 'Venda das ' . substr((string)$v['quando'], 11, 5) . ($outroDia ? ' de ' . date('d/m', strtotime((string)$v['quando'])) : '') . ' cancelada: ' . $nome . ' (' . dinheiro($valor) . ')'
      . ($min > 0 ? ' · saem ' . hm($min) . ' do saldo' : '') . ($devolver ? ' · devolver ao cliente: ' . textoPagamento($devolver, true) : ($valor > 0.004 ? ' · não tinha sido paga' : '')) . ' · Motivo: ' . $motivo . ' · por ' . primeiroNome($u['nome']), $agora);
    $db->commit();
  } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
  $c = crianca((int)$c['id']);
  if ($min > 0) { // as horas saíram do saldo: quem está no espaço passa a dever o tempo da visita (nasce a recreação avulsa); quem está fora volta a ficar sem acesso
    if ($c['entrou']) acertarCobrancas($c);
    elseif (restante($c) < periodo() && regra()['bloqueioAuto'] === 'sim' && (!isset($c['liberado']) || (int)$c['liberado'])) bloquearAcesso($c, false);
  }
  return ['cancelamento' => $id, 'registro' => $reg, 'valor' => $valor, 'minutos' => $min, 'nome' => $nome, 'devolver' => $devolver ? textoPagamento($devolver, true) : ''];
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
const TABELAS_CADASTRO = ['cupons', 'pagamentos', 'visitas', 'vendas', 'registros', 'mensagens', 'batidas', 'cancelamentos', 'criancas'];
const CHAVES_CADASTRO = '/^(mandar|foto|renomear|regra|regra_ok|desregra|falhas_regra|rosto_erro|rosto_ok|rosto_em|pedirosto|baixarfoto)_\d+$/';   // o que o sistema guarda por criança (regra_id e as outras chaves da loja não entram)
function senhaConfere(array $u, string $senha): bool { // a senha de quem está logado (para confirmar o que não tem volta fácil)
  $q = db()->prepare('SELECT senha FROM usuarios WHERE id = ?'); $q->execute([$u['id']]); $h = (string)$q->fetchColumn(); $q->closeCursor();
  return $senha !== '' && password_verify($senha, $h);
}
function limpezaResumo(): array { // o que há para apagar agora
  $db = db(); $n = fn(string $sql) => (int)$db->query($sql)->fetchColumn();
  return ['cadastros' => $n('SELECT COUNT(*) FROM criancas'), 'removidos' => $n('SELECT COUNT(*) FROM criancas WHERE ativo = 0'), 'dentro' => $n('SELECT COUNT(*) FROM criancas WHERE ativo = 1 AND entrou IS NOT NULL'),
    'responsaveis' => $n("SELECT COUNT(DISTINCT CASE WHEN cpf != '' THEN cpf ELSE 'sem' || id END) FROM criancas"), 'fotos' => $n("SELECT COUNT(*) FROM criancas WHERE foto != ''"),
    'vendas' => $n("SELECT COUNT(*) FROM vendas WHERE servico != '" . SERV_IMPORTADO . "'"), 'valor' => round((float)$db->query('SELECT COALESCE(SUM(valor), 0) FROM pagamentos')->fetchColumn(), 2), 'visitas' => $n('SELECT COUNT(*) FROM visitas'), 'registros' => $n('SELECT COUNT(*) FROM registros')];
}
function pastaCopias(string $nada = 'Nada foi apagado.'): string {
  $d = pastaDados() . '/copias'; if (!is_dir($d)) @mkdir($d, 0700, true);
  if (!is_dir($d) || !is_writable($d)) falha('Não consegui criar a pasta das cópias de segurança. ' . $nada, 500);
  if (!file_exists($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
  if (!file_exists($d . '/index.html')) @file_put_contents($d . '/index.html', '');
  return $d;
}
// uma cópia inteira do banco, feita pelo próprio SQLite (sai coerente mesmo com outra tela gravando). Devolve o nome do arquivo.
// O nome não começa por "playspace-" (é por esse começo que o sistema acha o banco de verdade) e leva um pedaço sorteado
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
  if (!$antes['cadastros'] && !$antes['vendas'] && !$antes['visitas']) falha('Não há cadastro para apagar: o sistema já está limpo.', 409);
  $copia = copiarBanco();
  if (cadastrosNaCopia(pastaCopias() . '/' . $copia) !== $antes['cadastros']) falha('A cópia de segurança não confere com o banco. Nada foi apagado.', 500);
  $ex = cfg('exemplo', null); $comp = is_array($ex) ? array_values(array_map('intval', (array)($ex['compromissos'] ?? []))) : [];   // a agenda fica; só saem os compromissos que vieram com o teste
  $db->beginTransaction();
  try {
    foreach ($db->query('SELECT produto_id, qtd FROM vendas WHERE produto_id IS NOT NULL')->fetchAll() as $v) devolverEstoque($v);   // o produto vendido volta para o estoque
    $fila = []; foreach ($db->query('SELECT idface_id FROM criancas WHERE idface_id IS NOT NULL')->fetchAll() as $c) { $fila[] = (int)$c['idface_id']; gravarCfg('apagar_' . (int)$c['idface_id'], (int)$c['idface_id']); }   // quem está no iDFace sai de lá na próxima conversa com o aparelho
    foreach (TABELAS_CADASTRO as $t) $db->exec("DELETE FROM $t");
    $db->exec('DELETE FROM papeis');   // 1.14: os cupons guardados para reimprimir saem junto (o Desfazer da limpeza não traz de volta)
    try { $db->exec("DELETE FROM sqlite_sequence WHERE name = 'cupons'"); } catch (Throwable $e) { }
    foreach ($db->query('SELECT chave FROM config')->fetchAll(PDO::FETCH_COLUMN) as $k) if (preg_match(CHAVES_CADASTRO, (string)$k)) apagarCfg((string)$k);
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
    foreach (TABELAS_CADASTRO as $t) { $db->exec("DELETE FROM $t"); $db->exec("INSERT INTO $t SELECT * FROM copia.$t"); }
    try { $db->exec("DELETE FROM sqlite_sequence WHERE name = 'cupons'"); $db->exec("INSERT INTO sqlite_sequence (name, seq) SELECT name, seq FROM copia.sqlite_sequence WHERE name = 'cupons'"); } catch (Throwable $e) { }
    foreach ($db->query('SELECT produto_id, qtd FROM vendas WHERE produto_id IS NOT NULL')->fetchAll() as $v) mexerEstoque((int)$v['produto_id'], (int)$v['qtd'], -1);   // as vendas voltaram: o estoque baixa de novo
    $por = $db->prepare('INSERT OR REPLACE INTO config (chave, valor) VALUES (?, ?)');
    foreach ($db->query('SELECT chave, valor FROM copia.config')->fetchAll() as $r) if (preg_match(CHAVES_CADASTRO, (string)$r['chave']) || in_array($r['chave'], ['exemplo', 'pend_fora', 'importacao'], true)) $por->execute([$r['chave'], $r['valor']]);
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
