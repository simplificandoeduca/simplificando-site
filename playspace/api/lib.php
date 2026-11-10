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
const PAPEIS = ['gestor' => 'Gestor', 'supervisor' => 'Supervisor', 'atendente' => 'Atendente'];

function falha(string $msg, int $st = 400): void { http_response_code($st); header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => false, 'erro' => $msg], JSON_UNESCAPED_UNICODE); exit; }
function responder(array $d = []): void { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode(['ok' => true] + $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function agora(): string { return date('Y-m-d H:i:s'); }
function registrarErro(string $msg): void { error_log('[playspace] ' . $msg); }

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
  return $pdo;
}
function cfg(string $k, $padrao = null) { $q = db()->prepare('SELECT valor FROM config WHERE chave = ?'); $q->execute([$k]); $v = $q->fetchColumn(); return $v === false ? $padrao : json_decode((string)$v, true); }
function gravarCfg(string $k, $v): void { if ($k === 'regra') unset($GLOBALS['_regra']); db()->prepare('INSERT INTO config (chave, valor) VALUES (?, ?) ON CONFLICT(chave) DO UPDATE SET valor = excluded.valor')->execute([$k, json_encode($v, JSON_UNESCAPED_UNICODE)]); }
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
  ['id' => 'recreacao', 'n' => 'Recreação', 'v' => 80, 'modo' => 'periodo', 'min' => 30, 'banco' => true, 'obs' => ''],
  ['id' => 'fusquinha', 'n' => 'Carrinho fusquinha', 'v' => 15, 'modo' => 'periodo', 'min' => 30, 'banco' => false, 'obs' => ''],
  ['id' => 'bebe', 'n' => 'Carrinho de bebê', 'v' => 30, 'modo' => 'fixo', 'min' => 0, 'banco' => false, 'obs' => 'das 10h às 22h'],
];
const PACOTES_DE_EXEMPLO = [['h' => 1, 'v' => 60], ['h' => 5, 'v' => 250], ['h' => 10, 'v' => 450], ['h' => 20, 'v' => 800]];   // inventados nas primeiras versões: saem sozinhos se ninguém mexeu neles
// cancelaMin: até quantos minutos depois da entrada o próprio atendente cancela sem cobrar (0 = só gestor e supervisor cancelam)
// tolerancia: quantos minutos depois do fim do tempo comprado a criança ainda pode ficar sem gerar nova cobrança
// autoMsg 'nao': nenhum aviso automático no WhatsApp (a loja não quer que o responsável fique acompanhando o tempo)
// fecha: a hora em que a loja fecha (24 = meia-noite): depois dela o tempo de quem ficou marcado no espaço para de contar
const REGRA_PADRAO = ['tolerancia' => 9, 'fecha' => 22, 'aviso' => 10, 'autoMsg' => 'nao', 'bloqueioAuto' => 'sim', 'pacotes' => [], 'extras' => [], 'sapateiras' => 44, 'cancelaMin' => 9];
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
  return $r;
}
function dur(int $min): string { return $min < 60 ? $min . ' min' : intdiv($min, 60) . 'h' . ($min % 60 ? str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT) : ''); }   // 30 min, 1h, 1h30
// O tempo é sempre debitado em períodos inteiros (não existe período quebrado): quem ficou 11 min debita 30.
// O período é o do serviço de tempo de brincar (Recreação, 30 min). O período seguinte só conta depois da tolerância:
// com 30 min e tolerância de 9, até 39:00 é um período; passou disso, dois; depois de 1h09, três.
function servicoTempo(): ?array { foreach ((array)regra()['servicos'] as $s) if (!empty($s['banco']) && ($s['modo'] ?? '') === 'periodo') return $s; return null; }
function periodo(): int { $s = servicoTempo(); return max(5, (int)($s['min'] ?? 30)); }
function periodosDe(float $minutos): int { return max(1, (int)ceil(($minutos - max(0, (int)regra()['tolerancia'])) / periodo())); }
function cobrar(float $minutos): int { return periodosDe($minutos) * periodo(); }
// quantos minutos a visita conta até $fim: no máximo até a hora em que a loja fecha (no dia da entrada) e nunca mais de 12 h.
// Assim uma saída esquecida não vira cobrança a noite toda (a supervisão corrige a hora da saída depois).
function minutosDaVisita(array $c, int $fim): float {
  $ent = strtotime((string)$c['entrou']); $f = (int)(regra()['fecha'] ?? 22); $teto = $ent + 43200;
  $fecha = strtotime(date('Y-m-d', $ent) . ($f >= 24 ? ' 23:59:59' : sprintf(' %02d:00:00', max(0, $f))));
  if ($fecha > $ent) $teto = min($teto, $fecha);   // entrou depois da hora de fechar (teste fora de hora): vale só o limite de 12 h
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

/* ---------- registros (o painel "Hoje na recepção") e as mensagens ---------- */
function registrar(string $tipo, ?int $criancaId, string $nome, string $texto, ?string $quando = null): int {
  db()->prepare('INSERT INTO registros (quando, tipo, crianca_id, nome, texto) VALUES (?, ?, ?, ?, ?)')->execute([$quando ?: agora(), $tipo, $criancaId, $nome, $texto]);
  return (int)db()->lastInsertId();
}
function crianca(int $id): ?array { $q = db()->prepare('SELECT * FROM criancas WHERE id = ?'); $q->execute([$id]); return $q->fetch() ?: null; }

/* ---------- entrada, saída e venda: a mesma lógica para o iDFace e para o toque na tela ---------- */
// sem o iDFace (leitura desligada em Ajustes), a equipe marca a entrada e a saída na tela
function modoManual(): bool { $c = cfg('idface', null); return is_array($c) && array_key_exists('ligado', $c) && !$c['ligado']; }
function fazerEntrada(array $c, string $origem = 'iDFace', ?string $quando = null): array {
  $db = db(); $quando = $quando ?: agora();
  $db->prepare('UPDATE criancas SET entrou = ? WHERE id = ?')->execute([$quando, $c['id']]);
  $c['entrou'] = $quando;
  $reg = registrar('ent', (int)$c['id'], $c['nome'], $origem === 'iDFace' ? 'Rosto reconhecido na entrada' : (modoManual() ? 'Entrada registrada' : 'Entrada registrada à mão'), $quando);
  $msg = null;
  if (regra()['autoMsg'] !== 'nao') {
    $texto = 'Oi, ' . primeiroNome($c['resp']) . '! ' . $c['nome'] . ' chegou na Play Space às ' . date('H:i', strtotime($quando)) . ' 💛 Qualquer coisa a gente te chama por aqui.';   // sem falar de tempo nem de saldo
    $msg = mandarMensagem($c, $texto, true, $c['foto'] ?: '');
  }
  return ['registro' => $reg, 'mensagem' => $msg];
}
/* ---------- cobrança por atraso: passou do tempo comprado mais a tolerância, o sistema registra sozinho mais um período ---------- */
// A venda entra marcada como automática, com a observação, e vira uma linha em "Hoje na recepção" para todo mundo ver.
// É chamada a cada pulso das telas abertas (para quem ainda está no espaço) e de novo na saída. Devolve as vendas criadas.
function acertarCobrancas(array $c, ?string $quando = null): array {
  if (!$c['entrou'] || !servicoTempo()) return [];
  if ((string)$c['entrou'] < (string)cfg('cobranca_desde', '')) return [];   // já estava no espaço quando esta regra chegou: não gera cobrança para trás
  $fim = $quando ? strtotime($quando) : time();
  if ((int)$c['usado_min'] + cobrar(minutosDaVisita($c, $fim)) <= (int)$c['pacote_min']) return [];
  $db = db(); $s = servicoTempo(); $p = periodo(); $criadas = [];
  $db->exec('BEGIN IMMEDIATE');   // uma tela de cada vez: duas perguntando no mesmo instante não geram a cobrança em dobro
  try {
    $c = crianca((int)$c['id']);
    if ($c && $c['entrou']) {
      $min = minutosDaVisita($c, $fim); $falta = (int)$c['usado_min'] + cobrar($min) - (int)$c['pacote_min'];
      for ($n = $falta > 0 ? (int)ceil($falta / $p) : 0; $n > 0; $n--) {
        $semTempo = $min <= (int)regra()['tolerancia'];   // ainda no primeiro período: entrou sem ter um período inteiro comprado
        $x = fazerVenda($c, $p, (float)$s['v'], null, $semTempo ? 'entrou sem tempo comprado' : 'cobrança por atraso', (string)$s['n'], true,
          ($semTempo ? 'Cobrança gerada na entrada: entrou sem tempo comprado' : 'Nova cobrança por atraso: ficou além do tempo comprado') . ' · ' . $s['n'] . ' +' . dur($p) . ' · ' . dinheiro((float)$s['v']) . ' a receber na saída');
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
  $q = db()->prepare('SELECT COUNT(*) n, COALESCE(SUM(valor), 0) v FROM vendas WHERE crianca_id = ? AND auto = 1 AND quando >= ? AND quando <= ?'); $q->execute([$criancaId, $entrou, $ate ?: '9999']); $a = $q->fetch();
  return ['n' => (int)$a['n'], 'valor' => (float)$a['v']];
}
function fazerSaida(array $c, string $origem = 'iDFace', ?string $quando = null): array {
  $db = db(); $quando = $quando ?: agora();
  if (acertarCobrancas($c, $quando)) $c = crianca((int)$c['id']);   // passou do tempo sem nenhuma tela aberta: a cobrança por atraso é gerada agora
  $min = minutosDaVisita($c, strtotime($quando)); $deb = min(cobrar($min), restante($c)); $atraso = atrasoDaVisita((int)$c['id'], (string)$c['entrou']);   // nunca debita mais do que há (a cobrança por atraso já cobriu o que faltava)
  $db->prepare('INSERT INTO visitas (crianca_id, entrou, saiu, debitado_min, origem) VALUES (?, ?, ?, ?, ?)')->execute([$c['id'], $c['entrou'], $quando, $deb, $origem]);
  $visita = (int)$db->lastInsertId();
  $sap = isset($c['sapateira']) && $c['sapateira'] !== null ? (int)$c['sapateira'] : 0;   // a sapateira fica livre na saída
  $db->prepare('UPDATE criancas SET entrou = NULL, usado_min = usado_min + ?, sapateira = NULL WHERE id = ?')->execute([$deb, $c['id']]);
  $c['usado_min'] += $deb; $c['entrou'] = null;
  $r = restante($c);
  if ($r < periodo() && regra()['bloqueioAuto'] === 'sim') bloquearAcesso($c, false);   // não sobra um período inteiro: o iDFace passa a recusar até venderem tempo
  $reg = registrar('sai', (int)$c['id'], $c['nome'], 'Ficou ' . dur((int)floor($min)) . ' · ' . dur($deb) . ' debitados' . ($r > 0 ? ' · restam ' . hm($r) : '') . ($atraso['n'] ? ' · ' . ($atraso['n'] > 1 ? $atraso['n'] . ' cobranças' : 'cobrança') . ' por atraso: ' . dinheiro($atraso['valor']) . ' a receber' : '') . ($sap ? ' · sapato na sapateira ' . $sap : '') . ($origem === 'iDFace' || modoManual() ? '' : ' · saída à mão'), $quando);
  $msg = null;
  if (regra()['autoMsg'] === 'ambos') {
    $texto = $c['nome'] . ' saiu às ' . date('H:i', strtotime($quando)) . '. Até a próxima! 🧸';
    $msg = mandarMensagem($c, $texto, true);
  }
  return ['registro' => $reg, 'visita' => $visita, 'debitado' => $deb, 'minutos' => (int)floor($min), 'atraso' => $atraso, 'mensagem' => $msg, 'sapateira' => $sap ?: null];
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
      . ($tirar ? ' · ' . (count($tirar) > 1 ? count($tirar) . ' cobranças' : 'cobrança') . ' por atraso desfeita' . (count($tirar) > 1 ? 's' : '') . ': ' . dinheiro($valor) : '') . ' · Motivo: ' . $motivo . ' · por ' . primeiroNome($u['nome']));
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
function fazerVenda(array $c, int $min, float $valor, ?int $por, string $itens = '', string $servico = '', bool $auto = false, string $textoAuto = ''): array {
  $db = db(); $itens = mb_substr(trim($itens), 0, 80); $servico = mb_substr(trim($servico), 0, 60); $min = max(0, $min);
  if ($min > 0) {
    $db->prepare('UPDATE criancas SET pacote_min = pacote_min + ? WHERE id = ?')->execute([$min, $c['id']]);
    if (isset($c['liberado']) && !(int)$c['liberado']) bloquearAcesso($c, true);   // pagou o tempo: o acesso volta
  }
  $db->prepare('INSERT INTO vendas (crianca_id, horas, minutos, valor, quando, por, itens, servico, auto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$c['id'], round($min / 60, 2), $min, $valor, agora(), $por, $itens, $servico, $auto ? 1 : 0]);
  $venda = (int)$db->lastInsertId();
  $dinheiro = 'R$ ' . number_format($valor, fmod($valor, 1) ? 2 : 0, ',', '.');
  if ($servico !== '') $texto = $servico . ($min > 0 ? ' · ' . dur($min) : '') . ($itens !== '' ? ' · ' . $itens : '') . ' · ' . $dinheiro;
  else $texto = $min > 0 ? ($min === 60 ? 'Hora avulsa' : 'Pacote de ' . dur($min)) . ($itens !== '' ? ' com ' . $itens : '') . ' vendido · ' . $dinheiro : $itens . ' · ' . $dinheiro;
  $reg = $auto ? registrar('cobr', (int)$c['id'], $c['nome'], $textoAuto !== '' ? $textoAuto : $texto) : registrar('venda', (int)$c['id'], $c['nome'], $texto);
  $db->prepare('UPDATE vendas SET registro = ? WHERE id = ?')->execute([$reg, $venda]);
  return ['venda' => $venda, 'registro' => $reg];
}
function dinheiro(float $v): string { return 'R$ ' . number_format($v, fmod($v, 1) ? 2 : 0, ',', '.'); }
function nomeDaVenda(array $v): string { // "Recreação · 30 min", "Carrinho de bebê · das 10h às 22h", "Pacote de 5h"
  $min = (int)($v['minutos'] ?? 0); $s = trim((string)($v['servico'] ?? '')); $it = trim((string)($v['itens'] ?? ''));
  if ($s !== '') return $s . ($min > 0 ? ' · ' . dur($min) : '') . ($it !== '' ? ' · ' . $it : '');
  return $min > 0 ? ($min === 60 ? 'Hora avulsa' : 'Pacote de ' . dur($min)) . ($it !== '' ? ' com ' . $it : '') : ($it !== '' ? $it : 'Produto');
}

/* ---------- cancelar a entrada sem cobrar ---------- */
// Até regra.cancelaMin minutos depois da entrada (minutos cheios, contados do segundo em que entrou), o próprio atendente cancela;
// depois disso, só gestor ou supervisor. Sempre com o motivo escrito: fica na tabela cancelamentos para a supervisão conferir.
function prazoCancelar(array $c): array { // [limite em minutos, segundos desde a entrada, ainda está no prazo]
  $lim = max(0, (int)regra()['cancelaMin']); $seg = $c['entrou'] ? max(0, time() - strtotime((string)$c['entrou'])) : 0;
  return [$lim, $seg, $lim > 0 && $c['entrou'] && $seg <= $lim * 60 + 10];   // 10 s de folga para o pedido que já estava a caminho quando o relógio da tela zerou
}
// as vendas que pagaram esta visita: as de hoje, feitas depois da última saída (ou do último cancelamento) da criança
function vendasDaVisita(array $c): array {
  $db = db(); $corte = date('Y-m-d') . ' 00:00:00';
  foreach (['SELECT MAX(saiu) FROM visitas WHERE crianca_id = ?', 'SELECT MAX(quando) FROM cancelamentos WHERE crianca_id = ?'] as $sql) { $q = $db->prepare($sql); $q->execute([$c['id']]); $s = (string)$q->fetchColumn(); if ($s > $corte) $corte = $s; }
  $q = $db->prepare('SELECT * FROM vendas WHERE crianca_id = ? AND quando >= ? ORDER BY id'); $q->execute([$c['id'], $corte]);
  return $q->fetchAll();
}
// vendida junto com esta entrada (no mesmo atendimento, ou até 30 min antes dela): é a que a tela já traz marcada para não cobrar
function vendaJuntoDaEntrada(array $v, array $c): bool { return (bool)$c['entrou'] && strtotime((string)$v['quando']) >= strtotime((string)$c['entrou']) - 1800; }
// $vendaIds: as vendas que deixam de ser cobradas (só valem as desta visita); null = o tempo de brincar vendido junto com a entrada
function cancelarEntrada(array $c, array $u, string $motivo, ?array $vendaIds): array {
  $db = db(); [$lim, $seg, $noPrazo] = prazoCancelar($c); $agora = agora(); $minDentro = intdiv($seg, 60);
  $alvo = []; foreach (vendasDaVisita($c) as $v) if ($vendaIds === null ? ((int)$v['minutos'] > 0 && vendaJuntoDaEntrada($v, $c)) : in_array((int)$v['id'], $vendaIds, true)) $alvo[] = $v;
  $sap = isset($c['sapateira']) && $c['sapateira'] !== null ? (int)$c['sapateira'] : 0;
  $db->beginTransaction();
  try {
    $itens = []; $valor = 0.0; $tempo = 0;
    foreach ($alvo as $v) {
      $db->prepare('UPDATE criancas SET pacote_min = MAX(0, pacote_min - ?) WHERE id = ?')->execute([(int)$v['minutos'], $c['id']]);
      $db->prepare('DELETE FROM vendas WHERE id = ?')->execute([$v['id']]);
      if (!empty($v['registro'])) $db->prepare("UPDATE registros SET texto = texto || ' · cancelada' WHERE id = ? AND crianca_id = ? AND tipo = 'venda' AND texto NOT LIKE '% · cancelada'")->execute([(int)$v['registro'], $c['id']]);
      $itens[] = ['n' => nomeDaVenda($v), 'm' => (int)$v['minutos'], 'v' => (float)$v['valor']]; $valor += (float)$v['valor']; $tempo += (int)$v['minutos'];
    }
    $db->prepare('UPDATE criancas SET entrou = NULL, sapateira = NULL WHERE id = ?')->execute([$c['id']]);   // sai sem debitar nada; a sapateira fica livre
    $db->prepare('INSERT INTO cancelamentos (crianca_id, nome, resp, entrou, quando, minutos, valor, itens, motivo, por, por_nome, papel, fora) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      ->execute([$c['id'], $c['nome'], $c['resp'], $c['entrou'], $agora, $minDentro, $valor, json_encode($itens, JSON_UNESCAPED_UNICODE), $motivo, (int)$u['id'], $u['nome'], $u['papel'], $noPrazo ? 0 : 1]);
    $id = (int)$db->lastInsertId();
    $oque = $itens ? 'não cobrado: ' . implode(', ', array_map(fn($i) => $i['n'] . ' (' . dinheiro($i['v']) . ')', $itens)) : 'o tempo não foi descontado';
    $reg = registrar('canc', (int)$c['id'], $c['nome'], 'Entrada das ' . substr((string)$c['entrou'], 11, 5) . ' cancelada aos ' . $minDentro . ' min · ' . $oque . ' · Motivo: ' . $motivo . ' · por ' . primeiroNome($u['nome']) . ($noPrazo ? '' : ' (fora do prazo' . ($lim > 0 ? ' de ' . $lim . ' min' : '') . ')'), $agora);
    $db->commit();
  } catch (Throwable $e) { $db->rollBack(); throw $e; }
  $c = crianca((int)$c['id']);
  // desfez o tempo e o saldo voltou a zero: o acesso volta a ficar bloqueado, como em quem ainda não comprou
  if ($tempo > 0 && restante($c) < periodo() && regra()['bloqueioAuto'] === 'sim' && (!isset($c['liberado']) || (int)$c['liberado'])) bloquearAcesso($c, false);
  $msg = null;
  if (regra()['autoMsg'] === 'ambos') $msg = mandarMensagem($c, 'A entrada de ' . $c['nome'] . ' na Play Space foi cancelada às ' . substr($agora, 11, 5) . ' e ' . ($valor > 0 ? 'nada foi cobrado' : 'o tempo não foi descontado') . '. Até a próxima! 🧸', true);
  return ['cancelamento' => $id, 'registro' => $reg, 'valor' => $valor, 'minutos' => $minDentro, 'tempo' => $tempo, 'sapateira' => $sap ?: null, 'fora' => !$noPrazo, 'mensagem' => $msg];
}
function cancelamentoPublico(array $x): array {
  $itens = json_decode((string)$x['itens'], true);
  return ['id' => (int)$x['id'], 'crianca' => (int)$x['crianca_id'], 'nome' => $x['nome'], 'resp' => $x['resp'], 'entrou' => $x['entrou'], 'quando' => $x['quando'], 'min' => (int)$x['minutos'], 'valor' => (float)$x['valor'],
    'itens' => is_array($itens) ? $itens : [], 'motivo' => $x['motivo'], 'por' => $x['por_nome'], 'papel' => $x['papel'], 'fora' => (bool)$x['fora'], 'visto_por' => (string)$x['visto_por'], 'visto_em' => $x['visto_em']];
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
