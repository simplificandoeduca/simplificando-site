<?php
/* =====================================================================
   Play Space Finanças — a API (POST JSON com "acao"; a resposta é JSON)
   Pedido do dono: um /financas com login próprio (como no Notas), com o que é crítico e delicado do negócio: o faturamento, as crianças
   de cada unidade, os cancelamentos (com o nome da atendente), os descontos e os pacotes vendidos.
   - Os números saem do próprio banco do Play Space (o mesmo arquivo SQLite), só lendo: nada aqui muda uma venda, um pagamento ou um cadastro.
   - O login é só das Finanças (tabela fin_usuarios): a equipe do Play Space não entra aqui. Quem cria o primeiro acesso (a conta principal)
     prova que é o dono com o usuário de gestor do Play Space, uma vez; depois a conta principal cria e tira o acesso dos outros.
   ===================================================================== */
declare(strict_types=1);
require __DIR__ . '/../api/lib.php';
set_exception_handler(function (Throwable $e) { registrarErro('[financas] ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()); falha('Algo deu errado. Tente de novo em instantes.', 500); });
const FIN_VERSAO = '1.2';

$in = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $bruto = file_get_contents('php://input') ?: '';
  if (strlen($bruto) > 200000) falha('Envio grande demais.', 413);
  $in = json_decode($bruto, true); if (!is_array($in)) $in = [];
} else falha('Use a tela das Finanças.', 405);
$acao = (string)($in['acao'] ?? '');
// a tela manda o cabeçalho X-Fin: um formulário de outro site não consegue (proteção contra CSRF)
if (($_SERVER['HTTP_X_FIN'] ?? '') !== '1') falha('Pedido sem o cabeçalho do app.', 403);

// a sessão das Finanças é outra (outro nome, só na pasta /financas): entrar no Play Space não entra aqui, e vice-versa
function finSessao(): void {
  $dir = pastaDados() . '/sessoes';
  if (!is_dir($dir)) @mkdir($dir, 0700, true);
  if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
  ini_set('session.gc_maxlifetime', '43200'); ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
  $caminho = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/financas/api.php'), '/') . '/';
  session_name('PSFINANCAS');
  session_set_cookie_params(['lifetime' => 43200, 'path' => $caminho, 'secure' => https(), 'httponly' => true, 'samesite' => 'Strict']);
  session_start();
}
finSessao();
$db = db();
$db->exec("CREATE TABLE IF NOT EXISTS fin_usuarios (id INTEGER PRIMARY KEY, nome TEXT NOT NULL, login TEXT NOT NULL UNIQUE COLLATE NOCASE, senha TEXT NOT NULL, criado_em TEXT NOT NULL, ultimo_login TEXT)");
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

// "José", "jose" e " JOSÉ " são o mesmo login
function finLogin($v): string {
  $v = trim((string)$v); $v = function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
  $v = strtr($v, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
    'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', ' ' => '.']);
  return substr((string)preg_replace('/[^a-z0-9._-]/', '', $v), 0, 40);
}
function finNome($v): string { return mb_substr(trim((string)preg_replace('/\s+/u', ' ', (string)$v)), 0, 80); }
function finPrincipal(): ?array { return db()->query('SELECT * FROM fin_usuarios ORDER BY id LIMIT 1')->fetch() ?: null; }
function finEu(): ?array { $id = (int)($_SESSION['fin_uid'] ?? 0); if (!$id) return null; $q = db()->prepare('SELECT * FROM fin_usuarios WHERE id = ?'); $q->execute([$id]); return $q->fetch() ?: null; }
function finOut(array $u): array { $p = finPrincipal(); return ['id' => (int)$u['id'], 'nome' => (string)$u['nome'], 'login' => (string)$u['login'], 'principal' => $p && (int)$p['id'] === (int)$u['id'], 'ultimoLogin' => $u['ultimo_login']]; }
// 8 tentativas erradas em 15 minutos (de qualquer login, deste aparelho) bloqueiam um pouco
function finBloqueado(string $ip): void {
  $q = db()->prepare("SELECT COUNT(*) FROM tentativas WHERE login = 'financas' AND ip = ? AND quando > ?"); $q->execute([$ip, time() - 900]);
  if ((int)$q->fetchColumn() >= 8) falha('Muitas tentativas erradas. Espere 15 minutos e tente de novo.', 429);
}
function finErrou(string $ip): void { db()->prepare("INSERT INTO tentativas (login, ip, quando) VALUES ('financas', ?, ?)")->execute([$ip, time()]); usleep(400000); }
// o dono prova quem é com o usuário de gestor do Play Space (para criar o primeiro acesso e para a senha esquecida)
function finGestorConfere(array $in, string $ip): array {
  $l = trim((string)($in['gestor_login'] ?? '')); $s = (string)($in['gestor_senha'] ?? '');
  if ($l === '' || $s === '') falha('Digite o usuário e a senha de gestor do Play Space.');
  $lm = mb_strtolower($l); $q = db()->prepare('SELECT COUNT(*) FROM tentativas WHERE login = ? AND quando > ?'); $q->execute([$lm, time() - 900]);   // (a mesma trava do login do Play Space)
  if ((int)$q->fetchColumn() >= 8) falha('Muitas tentativas com esse usuário. Espere 15 minutos e tente de novo.', 429);
  $q = db()->prepare("SELECT * FROM usuarios WHERE login = ? AND ativo = 1"); $q->execute([$l]); $g = $q->fetch();
  if (!$g || !password_verify($s, (string)$g['senha'])) { db()->prepare('INSERT INTO tentativas (login, ip, quando) VALUES (?, ?, ?)')->execute([$lm, $ip, time()]); finErrou($ip); falha('Usuário ou senha do Play Space incorretos.', 401); }
  if ((string)$g['papel'] !== 'gestor') { finErrou($ip); falha('Só um usuário de gestor do Play Space pode fazer isso.', 403); }
  return $g;
}
function finEntrou(array $u): void {
  session_regenerate_id(true); $_SESSION['fin_uid'] = (int)$u['id'];
  db()->prepare('UPDATE fin_usuarios SET ultimo_login = ? WHERE id = ?')->execute([agora(), (int)$u['id']]);
  db()->prepare("DELETE FROM tentativas WHERE login = 'financas' AND ip = ?")->execute([(string)($_SERVER['REMOTE_ADDR'] ?? '')]);
}

/* ---------- sem login ---------- */
if ($acao === 'estado') {
  $eu = finEu();
  responder(['versao' => FIN_VERSAO, 'temConta' => (bool)finPrincipal(), 'usuario' => $eu ? finOut($eu) : null, 'unidades' => $eu ? unidades() : []]);
}
if ($acao === 'criar_conta') {
  finBloqueado($ip);
  if (finPrincipal()) falha('O acesso às Finanças já foi criado. Entre com o seu login e a senha.', 409);
  $gDono = finGestorConfere($in, $ip);
  $nome = finNome($in['nome'] ?? ''); if ($nome === '') falha('Escreva o seu nome.');
  $login = finLogin($in['login'] ?? '') ?: finLogin(explode(' ', $nome)[0] ?? ''); if (strlen($login) < 3) falha('O login precisa ter pelo menos 3 letras (ex.: jose).');
  $senha = (string)($in['senha'] ?? ''); if (strlen($senha) < 6) falha('A senha precisa ter pelo menos 6 caracteres.');
  $db->prepare('INSERT INTO fin_usuarios (nome, login, senha, criado_em) VALUES (?, ?, ?, ?)')->execute([$nome, $login, password_hash($senha, PASSWORD_DEFAULT), agora()]);
  $db->prepare("INSERT OR REPLACE INTO config (chave, valor) VALUES ('fin_dono', ?)")->execute([(string)(int)$gDono['id']]);   // 1.49: o gestor que criou: só ele recupera a senha
  $q = $db->prepare('SELECT * FROM fin_usuarios WHERE login = ?'); $q->execute([$login]); $u = $q->fetch();
  finEntrou($u); responder(['usuario' => finOut($u)]);
}
if ($acao === 'entrar') {
  finBloqueado($ip);
  if (!finPrincipal()) falha('O acesso às Finanças ainda não foi criado.', 404);
  $login = finLogin($in['login'] ?? ''); if ($login === '') falha('Digite o seu login.');
  $q = $db->prepare('SELECT * FROM fin_usuarios WHERE login = ?'); $q->execute([$login]); $u = $q->fetch();
  if (!$u || !password_verify((string)($in['senha'] ?? ''), (string)$u['senha'])) { finErrou($ip); falha('Login ou senha incorretos.', 401); }
  finEntrou($u); responder(['usuario' => finOut($u)]);
}
if ($acao === 'redefinir') { // esqueceu a senha: o dono prova quem é com o usuário de gestor do Play Space e escolhe a senha nova
  finBloqueado($ip); $p = finPrincipal(); if (!$p) falha('O acesso às Finanças ainda não foi criado.', 404);
  $g = finGestorConfere($in, $ip);
  // 1.49 (auditoria): só o mesmo usuário de gestor que criou o acesso às Finanças troca a senha por aqui (outro gestor, como a gestora da
  // loja, não entra nas Finanças trocando a senha do dono)
  $qd = $db->query("SELECT valor FROM config WHERE chave = 'fin_dono'"); $dono = (int)$qd->fetchColumn(); $qd->closeCursor();
  if ($dono && $dono !== (int)$g['id']) { finErrou($ip); falha('Só o usuário de gestor que criou o acesso às Finanças troca a senha por aqui.', 403); }
  if (!$dono) $db->prepare("INSERT OR REPLACE INTO config (chave, valor) VALUES ('fin_dono', ?)")->execute([(string)(int)$g['id']]);
  $senha = (string)($in['senha'] ?? ''); if (strlen($senha) < 6) falha('A senha nova precisa ter pelo menos 6 caracteres.');
  $alvo = $p; $lg = finLogin($in['login'] ?? '');
  if ($lg !== '') { $q = $db->prepare('SELECT * FROM fin_usuarios WHERE login = ?'); $q->execute([$lg]); $alvo = $q->fetch(); if (!$alvo) falha('Não existe acesso com esse login.', 404); }
  $db->prepare('UPDATE fin_usuarios SET senha = ? WHERE id = ?')->execute([password_hash($senha, PASSWORD_DEFAULT), (int)$alvo['id']]);
  finEntrou($alvo); responder(['usuario' => finOut($alvo)]);
}
if ($acao === 'sair') { $_SESSION = []; session_destroy(); responder(); }

/* ---------- daqui para baixo, só com login ---------- */
$eu = finEu();
if (!$eu) { $_SESSION = []; @session_destroy(); falha('Entre de novo para continuar.', 401); }
$ehPrincipal = (int)(finPrincipal()['id'] ?? 0) === (int)$eu['id'];
function soPrincipal(bool $eh): void { if (!$eh) falha('Só a conta principal mexe nos acessos.', 403); }

if ($acao === 'trocar_senha') {
  if (!password_verify((string)($in['atual'] ?? ''), (string)$eu['senha'])) { finErrou($ip); falha('A senha atual não confere.', 401); }
  $s = (string)($in['senha'] ?? ''); if (strlen($s) < 6) falha('A senha nova precisa ter pelo menos 6 caracteres.');
  $db->prepare('UPDATE fin_usuarios SET senha = ? WHERE id = ?')->execute([password_hash($s, PASSWORD_DEFAULT), (int)$eu['id']]); responder();
}
if ($acao === 'usuarios') { soPrincipal($ehPrincipal); responder(['usuarios' => array_map('finOut', $db->query('SELECT * FROM fin_usuarios ORDER BY id')->fetchAll())]); }
if ($acao === 'usuario_criar') {
  soPrincipal($ehPrincipal);
  $nome = finNome($in['nome'] ?? ''); if ($nome === '') falha('Escreva o nome da pessoa.');
  $login = finLogin($in['login'] ?? ''); if (strlen($login) < 3) falha('O login precisa ter pelo menos 3 letras.');
  $s = (string)($in['senha'] ?? ''); if (strlen($s) < 6) falha('A senha precisa ter pelo menos 6 caracteres.');
  $q = $db->prepare('SELECT 1 FROM fin_usuarios WHERE login = ?'); $q->execute([$login]); if ($q->fetchColumn()) falha('Já existe um acesso com o login "' . $login . '". Escolha outro.', 409);
  $db->prepare('INSERT INTO fin_usuarios (nome, login, senha, criado_em) VALUES (?, ?, ?, ?)')->execute([$nome, $login, password_hash($s, PASSWORD_DEFAULT), agora()]);
  responder(['login' => $login]);
}
if ($acao === 'usuario_senha') {
  soPrincipal($ehPrincipal); $s = (string)($in['senha'] ?? ''); if (strlen($s) < 6) falha('A senha precisa ter pelo menos 6 caracteres.');
  $q = $db->prepare('UPDATE fin_usuarios SET senha = ? WHERE id = ?'); $q->execute([password_hash($s, PASSWORD_DEFAULT), (int)($in['id'] ?? 0)]);
  if (!$q->rowCount()) falha('Acesso não encontrado.', 404); responder();
}
if ($acao === 'usuario_remover') {
  soPrincipal($ehPrincipal); $id = (int)($in['id'] ?? 0); if ($id === (int)$eu['id']) falha('A conta principal não pode tirar o próprio acesso.', 409);
  $q = $db->prepare('DELETE FROM fin_usuarios WHERE id = ?'); $q->execute([$id]); if (!$q->rowCount()) falha('Acesso não encontrado.', 404); responder();
}

/* ---------- os números de um período ---------- */
// de/ate: os dias (AAAA-MM-DD), inclusive os dois. A unidade é a do aparelho em que a coisa aconteceu (a venda, o pagamento, a visita, o
// cancelamento); o que não tem unidade (o iDFace, os exemplos, o que é de antes das unidades) vai à parte (unidade 0).
// Faturado = o que foi vendido no período, já com o desconto (as horas trazidas do sistema anterior não entram: não são venda).
// Recebido = os pagamentos do período, por forma, menos o que foi devolvido no período. Em aberto = o que foi vendido no período e ainda
// não foi pago (a recreação de quem está no espaço, o que ficou para a saída ou para a devolução do carrinho).
if ($acao === 'resumo') {
  $de = (string)($in['de'] ?? ''); $ate = (string)($in['ate'] ?? '');
  $dataOk = fn(string $x) => (bool)preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $x, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);   // 1.49: 30/02 não existe
  if (!$dataOk($de) || !$dataOk($ate)) falha('Escolha o período (datas que existem).');
  if ($ate < $de) [$de, $ate] = [$ate, $de];
  $dias = (int)round((strtotime($ate) - strtotime($de)) / 86400) + 1; if ($dias > 800) falha('Escolha um período de até 800 dias.');
  $ini = $de . ' 00:00:00'; $fim = date('Y-m-d', strtotime($ate . ' +1 day')) . ' 00:00:00';
  // 1.53: o filtro de produto ('' = tudo · g:<tipo> · i:<tipo>|<NOME>)
  $prod = trim((string)($in['prod'] ?? ''));
  if ($prod !== '' && !preg_match('/^(g:(recreacao|pacotes|festa|produtos|carrinhos|outros)|i:(recreacao|pacotes|festa|produtos|carrinhos|outros)\|.{1,160})$/su', $prod)) $prod = '';
  // o tipo (grupo) e o nome de cada venda, como no Caixa do dia: o pacote pelo nome, o produto da loja pelo nome, a festa, o carrinho, a recreação
  $cls = function (array $r): array {
    $g = grupoDoCaixa(['s' => (string)($r['s'] ?? ''), 'pr' => (int)($r['pr'] ?? 0) > 0 ? 1 : 0, 'a' => (string)($r['a'] ?? ''), 'm' => (int)($r['m'] ?? 0), 'au' => (int)($r['au'] ?? 0), 'vo' => (string)($r['vo'] ?? '')]);
    $n = mb_strtoupper(trim((string)preg_replace('/\s+/u', ' ', (string)($r['s'] ?? ''))));
    if ($n === '') $n = $g === 'pacotes' ? 'PACOTE ' . str_pad((string)intdiv((int)($r['m'] ?? 0), 60), 2, '0', STR_PAD_LEFT) . ' HORAS' : ['recreacao' => 'RECREAÇÃO', 'produtos' => 'PRODUTO', 'carrinhos' => 'CARRINHO', 'festa' => 'FESTA'][$g] ?? 'OUTROS';
    return [$g, $n];
  };
  $passa = function (string $g, string $n) use ($prod): bool { if ($prod === '') return true; if (strpos($prod, 'g:') === 0) return substr($prod, 2) === $g; return substr($prod, 2) === $g . '|' . $n; };
  $uni = []; $U = function (int $u) use (&$uni) { if (!isset($uni[$u])) $uni[$u] = ['faturado' => 0.0, 'vendas' => 0, 'recebido' => 0.0, 'devolvido' => 0.0, 'aberto' => 0.0, 'visitas' => 0, 'criancas' => 0, 'festaVisitas' => 0, 'agora' => 0,
    'cancN' => 0, 'cancV' => 0.0, 'desfN' => 0, 'foraN' => 0, 'descN' => 0, 'descV' => 0.0, 'descPct' => 0.0, 'pcdN' => 0, 'pcdV' => 0.0, 'voucherN' => 0, 'pacN' => 0, 'pacV' => 0.0, 'festas' => 0]; };
  $nomeUsu = []; foreach ($db->query('SELECT id, nome FROM usuarios')->fetchAll() as $x) $nomeUsu[(int)$x['id']] = (string)$x['nome'];
  // vendas: o faturado, os grupos (recreação, pacotes, festa, produtos, carrinhos), os pacotes por nome, por dia e por atendente
  $grupos = []; $pacotes = []; $porDia = []; $atend = [];
  $q = $db->prepare("SELECT COALESCE(unidade_id, 0) u, servico s, (COALESCE(produto_id, 0) > 0) pr, alug a, minutos m, auto au, voucher vo, COUNT(*) n, SUM(valor > 0.004) nv, SUM(valor > 0.004 AND auto = 0) nr, COALESCE(SUM(valor), 0) v
    FROM vendas WHERE quando >= ? AND quando < ? AND servico != ? GROUP BY u, s, pr, a, (minutos > 0), auto, (voucher != ''), (CASE WHEN minutos > 0 THEN minutos ELSE 0 END)");
  $q->execute([$ini, $fim, SERV_IMPORTADO]);
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $v = round((float)$l['v'], 2); $nv = (int)$l['nv'];
    $uni[$u]['faturado'] = round($uni[$u]['faturado'] + $v, 2); $uni[$u]['vendas'] += (int)$l['nr']; if ((string)$l['vo'] !== '') $uni[$u]['voucherN'] += (int)$l['n'];
    if ($nv < 1 && $v <= 0.004) continue;
    $g = grupoDoCaixa(['s' => (string)$l['s'], 'pr' => (int)$l['pr'], 'a' => (string)$l['a'], 'm' => (int)$l['m'], 'au' => (int)$l['au'], 'vo' => (string)$l['vo']]);
    $k = $u . '|' . $g; if (!isset($grupos[$k])) $grupos[$k] = ['u' => $u, 'g' => $g, 'n' => 0, 'v' => 0.0]; $grupos[$k]['n'] += $nv; $grupos[$k]['v'] = round($grupos[$k]['v'] + $v, 2);
    if ($g === 'pacotes') { $nm = trim((string)$l['s']) !== '' ? mb_strtoupper(trim((string)$l['s'])) : 'PACOTE ' . str_pad((string)intdiv((int)$l['m'], 60), 2, '0', STR_PAD_LEFT) . ' HORAS';
      $uni[$u]['pacN'] += $nv; $uni[$u]['pacV'] = round($uni[$u]['pacV'] + $v, 2);
      $k = $u . '|' . $nm; if (!isset($pacotes[$k])) $pacotes[$k] = ['u' => $u, 'n' => $nm, 'h' => round((int)$l['m'] / 60, 1), 'q' => 0, 'v' => 0.0]; $pacotes[$k]['q'] += $nv; $pacotes[$k]['v'] = round($pacotes[$k]['v'] + $v, 2); } }
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, substr(quando, 1, 10) d, COALESCE(SUM(valor), 0) v FROM vendas WHERE quando >= ? AND quando < ? AND servico != ? GROUP BY u, d ORDER BY d');
  $q->execute([$ini, $fim, SERV_IMPORTADO]); foreach ($q->fetchAll() as $l) $porDia[] = ['u' => (int)$l['u'], 'd' => (string)$l['d'], 'v' => round((float)$l['v'], 2)];
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, por, COUNT(*) n, COALESCE(SUM(valor), 0) v FROM vendas WHERE quando >= ? AND quando < ? AND servico != ? AND valor > 0.004 AND auto = 0 GROUP BY u, por');
  $q->execute([$ini, $fim, SERV_IMPORTADO]); foreach ($q->fetchAll() as $l) $atend[] = ['u' => (int)$l['u'], 'nome' => $nomeUsu[(int)$l['por']] ?? 'Sem nome', 'n' => (int)$l['n'], 'v' => round((float)$l['v'], 2)];
  // recebido: os pagamentos do período (na unidade em que o dinheiro entrou), menos o devolvido no período
  $formas = [];
  $q = $db->prepare('SELECT COALESCE(p.unidade_id, v.unidade_id, 0) u, p.forma f, COALESCE(SUM(p.valor), 0) t FROM pagamentos p JOIN vendas v ON v.id = p.venda_id WHERE p.quando >= ? AND p.quando < ? GROUP BY u, f');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $t = round((float)$l['t'], 2); $uni[$u]['recebido'] = round($uni[$u]['recebido'] + $t, 2); $formas[$u . '|' . $l['f']] = ['u' => $u, 'f' => (string)$l['f'], 'v' => round(($formas[$u . '|' . $l['f']]['v'] ?? 0) + $t, 2)]; }
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, forma f, COALESCE(SUM(valor), 0) t FROM estornos WHERE quando >= ? AND quando < ? GROUP BY u, f');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $t = round((float)$l['t'], 2); $uni[$u]['devolvido'] = round($uni[$u]['devolvido'] + $t, 2); $uni[$u]['recebido'] = round($uni[$u]['recebido'] - $t, 2);
    $formas[$u . '|' . $l['f']] = ['u' => $u, 'f' => (string)$l['f'], 'v' => round(($formas[$u . '|' . $l['f']]['v'] ?? 0) - $t, 2)]; }
  // 1.49 (auditoria): a venda cancelada em outro dia leva junto o pagamento dela (que é apagado) e deixa o estorno com o dia em que tinha sido
  // paga: esse dinheiro volta ao Recebido daquele dia (senão sairia duas vezes: o pagamento apagado e o estorno)
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, forma f, COALESCE(SUM(valor), 0) t FROM estornos WHERE pago_em >= ? AND pago_em < ? GROUP BY u, f');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $t = round((float)$l['t'], 2); $uni[$u]['recebido'] = round($uni[$u]['recebido'] + $t, 2);
    $formas[$u . '|' . $l['f']] = ['u' => $u, 'f' => (string)$l['f'], 'v' => round(($formas[$u . '|' . $l['f']]['v'] ?? 0) + $t, 2)]; }
  // em aberto: o vendido no período que ainda não foi pago
  $q = $db->prepare('SELECT COALESCE(v.unidade_id, 0) u, COALESCE(SUM(v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0)), 0) f FROM vendas v
    WHERE v.quando >= ? AND v.quando < ? AND v.servico != ? AND v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) > 0.004 GROUP BY u');
  $q->execute([$ini, $fim, SERV_IMPORTADO]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $uni[$u]['aberto'] = round((float)$l['f'], 2); }
  // as crianças: as visitas que terminaram no período (e quantas crianças diferentes), as que estão no espaço agora e as festas marcadas
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, COUNT(*) n, COUNT(DISTINCT crianca_id) c, SUM(festa_id IS NOT NULL) f FROM visitas WHERE saiu >= ? AND saiu < ? GROUP BY u');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $uni[$u]['visitas'] = (int)$l['n']; $uni[$u]['criancas'] = (int)$l['c']; $uni[$u]['festaVisitas'] = (int)$l['f']; }
  $q = $db->prepare('SELECT COUNT(DISTINCT crianca_id) FROM visitas WHERE saiu >= ? AND saiu < ?'); $q->execute([$ini, $fim]); $criancasTotal = (int)$q->fetchColumn();
  foreach ($db->query('SELECT COALESCE(unidade_id, 0) u, COUNT(*) n FROM criancas WHERE entrou IS NOT NULL AND ativo = 1 GROUP BY u')->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $uni[$u]['agora'] = (int)$l['n']; }
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, COUNT(*) n FROM festas WHERE data >= ? AND data <= ? GROUP BY u'); $q->execute([$de, $ate]);
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $uni[$u]['festas'] = (int)$l['n']; }
  // 1.53: cada venda do período, uma a uma: o "produto a produto" (sempre de tudo) e, com o filtro de produto, os números só daquilo
  $porItem = []; $VQ = $db->prepare('SELECT COALESCE(unidade_id, 0) u, substr(quando, 1, 10) d, servico s, COALESCE(produto_id, 0) pr, alug a, minutos m, auto au, voucher vo, valor v, COALESCE(qtd, 1) qtd, por FROM vendas WHERE quando >= ? AND quando < ? AND servico != ?');
  $VQ->execute([$ini, $fim, SERV_IMPORTADO]); $VL = $VQ->fetchAll();
  foreach ($VL as $r) { [$g, $n] = $cls($r); $v = (float)$r['v']; if ($v <= 0.004) continue; $k = (int)$r['u'] . '|' . $g . '|' . $n;
    if (!isset($porItem[$k])) $porItem[$k] = ['u' => (int)$r['u'], 'g' => $g, 'n' => $n, 'q' => 0, 'v' => 0.0]; $porItem[$k]['q'] += $g === 'produtos' ? max(1, (int)$r['qtd']) : 1; $porItem[$k]['v'] = round($porItem[$k]['v'] + $v, 2); }
  if ($prod !== '') {
    foreach ($uni as $u => $x) { foreach (['faturado', 'recebido', 'devolvido', 'aberto', 'pacV'] as $c) $uni[$u][$c] = 0.0; foreach (['vendas', 'pacN'] as $c) $uni[$u][$c] = 0; }
    $grupos = []; $pacotes = []; $porDia = []; $atend = []; $formas = []; $pdP = []; $atP = [];
    foreach ($VL as $r) { [$g, $n] = $cls($r); if (!$passa($g, $n)) continue; $u = (int)$r['u']; $U($u); $v = round((float)$r['v'], 2); $valeu = $v > 0.004;
      $uni[$u]['faturado'] = round($uni[$u]['faturado'] + $v, 2); if ($valeu && !(int)$r['au']) $uni[$u]['vendas']++;
      $pdP[$u . '|' . $r['d']] = ['u' => $u, 'd' => (string)$r['d'], 'v' => round(($pdP[$u . '|' . $r['d']]['v'] ?? 0) + $v, 2)];
      if (!$valeu) continue;
      $k = $u . '|' . $g; if (!isset($grupos[$k])) $grupos[$k] = ['u' => $u, 'g' => $g, 'n' => 0, 'v' => 0.0]; $grupos[$k]['n']++; $grupos[$k]['v'] = round($grupos[$k]['v'] + $v, 2);
      if (!(int)$r['au']) { $k = $u . '|' . (int)$r['por']; if (!isset($atP[$k])) $atP[$k] = ['u' => $u, 'nome' => $nomeUsu[(int)$r['por']] ?? 'Sem nome', 'n' => 0, 'v' => 0.0]; $atP[$k]['n']++; $atP[$k]['v'] = round($atP[$k]['v'] + $v, 2); }
      if ($g === 'pacotes') { $uni[$u]['pacN']++; $uni[$u]['pacV'] = round($uni[$u]['pacV'] + $v, 2);
        $k = $u . '|' . $n; if (!isset($pacotes[$k])) $pacotes[$k] = ['u' => $u, 'n' => $n, 'h' => round((int)$r['m'] / 60, 1), 'q' => 0, 'v' => 0.0]; $pacotes[$k]['q']++; $pacotes[$k]['v'] = round($pacotes[$k]['v'] + $v, 2); } }
    ksort($pdP); $porDia = array_values($pdP); $atend = array_values($atP);
    $soma = function (int $u, string $f, float $t) use (&$uni, &$formas, $U) { $U($u); $uni[$u]['recebido'] = round($uni[$u]['recebido'] + $t, 2); $formas[$u . '|' . $f] = ['u' => $u, 'f' => $f, 'v' => round(($formas[$u . '|' . $f]['v'] ?? 0) + $t, 2)]; };
    $q = $db->prepare('SELECT COALESCE(p.unidade_id, v.unidade_id, 0) u, p.forma f, p.valor t, v.servico s, COALESCE(v.produto_id, 0) pr, v.alug a, v.minutos m, v.auto au, v.voucher vo FROM pagamentos p JOIN vendas v ON v.id = p.venda_id WHERE p.quando >= ? AND p.quando < ?');
    $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { [$g, $n] = $cls($l); if ($passa($g, $n)) $soma((int)$l['u'], (string)$l['f'], round((float)$l['t'], 2)); }
    // o devolvido: a venda pode já ter sido apagada (cancelada): vale o tipo guardado na devolução
    $eq = 'SELECT COALESCE(e.unidade_id, 0) u, e.forma f, e.valor t, e.grupo eg, e.item it, v.id vid, v.servico s, COALESCE(v.produto_id, 0) pr, v.alug a, v.minutos m, v.auto au, v.voucher vo FROM estornos e LEFT JOIN vendas v ON v.id = e.venda_id WHERE e.';
    $clsE = fn(array $l): array => $l['vid'] ? $cls($l) : [(string)$l['eg'], mb_strtoupper(trim((string)$l['it']))];
    $q = $db->prepare($eq . 'quando >= ? AND e.quando < ?'); $q->execute([$ini, $fim]);
    foreach ($q->fetchAll() as $l) { [$g, $n] = $clsE($l); if (!$passa($g, $n)) continue; $t = round((float)$l['t'], 2); $u = (int)$l['u']; $U($u); $uni[$u]['devolvido'] = round($uni[$u]['devolvido'] + $t, 2); $soma($u, (string)$l['f'], -$t); }
    $q = $db->prepare($eq . 'pago_em >= ? AND e.pago_em < ?'); $q->execute([$ini, $fim]);
    foreach ($q->fetchAll() as $l) { [$g, $n] = $clsE($l); if ($passa($g, $n)) $soma((int)$l['u'], (string)$l['f'], round((float)$l['t'], 2)); }
    $q = $db->prepare('SELECT COALESCE(v.unidade_id, 0) u, v.servico s, COALESCE(v.produto_id, 0) pr, v.alug a, v.minutos m, v.auto au, v.voucher vo, v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) f FROM vendas v
      WHERE v.quando >= ? AND v.quando < ? AND v.servico != ? AND v.valor - COALESCE((SELECT SUM(p.valor) FROM pagamentos p WHERE p.venda_id = v.id), 0) > 0.004');
    $q->execute([$ini, $fim, SERV_IMPORTADO]); foreach ($q->fetchAll() as $l) { [$g, $n] = $cls($l); if (!$passa($g, $n)) continue; $u = (int)$l['u']; $U($u); $uni[$u]['aberto'] = round($uni[$u]['aberto'] + (float)$l['f'], 2); }
  }
  // 1.53: o catálogo (para o filtro e o produto a produto trazerem todos, mesmo o que não vendeu): os produtos da loja, os pacotes, os serviços e as festas
  $catalogo = []; $poe = function (string $g, string $n) use (&$catalogo) { $n = mb_strtoupper(trim((string)preg_replace('/\s+/u', ' ', $n))); if ($n !== '') $catalogo[$g . '|' . $n] = ['g' => $g, 'n' => $n]; };
  foreach ($db->query('SELECT nome FROM produtos WHERE ativo = 1')->fetchAll(PDO::FETCH_COLUMN) as $n) $poe('produtos', (string)$n);
  foreach ((array)(regra()['pacotes'] ?? []) as $p) if (is_array($p)) $poe('pacotes', servicoDoPacote($p) !== '' ? servicoDoPacote($p) : 'PACOTE ' . str_pad((string)(int)($p['h'] ?? 0), 2, '0', STR_PAD_LEFT) . ' HORAS');
  foreach ((array)(regra()['servicos'] ?? []) as $s) if (is_array($s) && trim((string)($s['n'] ?? '')) !== '') { [$g, $n] = $cls(['s' => (string)$s['n'], 'pr' => 0, 'a' => !empty($s['volta']) ? 'x' : '', 'm' => 0, 'au' => 0, 'vo' => '']); $poe($g, $n); }
  foreach (array_merge(array_values(FESTA_SERVICO), ['UPGRADE DE FESTA', 'ADICIONAL DE FESTA']) as $n) $poe('festa', $n);
  // os cancelamentos: quantos, quanto deixou de ser cobrado, de quem (o nome da atendente que a pessoa escreveu; sem ele, quem estava no aparelho)
  $tipos = ['' => 'Entrada cancelada', 'desfeita' => 'Entrada desfeita na hora', 'venda' => 'Venda cancelada', 'atendimento' => 'Atendimento cancelado', 'aluguel' => 'Saída do carrinho cancelada'];
  $canc = []; $cancTotal = 0; $cancAt = []; $cancTipo = [];
  $q = $db->prepare('SELECT id, quando, COALESCE(unidade_id, 0) u, nome, resp, valor, minutos, itens, motivo, por_nome, papel, atendente, tipo, fora, visto_por FROM cancelamentos WHERE quando >= ? AND quando < ? ORDER BY quando DESC, id DESC');
  $q->execute([$ini, $fim]);
  foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $conta = (string)$l['tipo'] !== 'desfeita'; $cancTotal++;   // 1.49: a entrada desfeita na hora não conta (como no painel)
    $at = trim((string)$l['atendente']) !== '' ? mb_strtoupper(trim((string)$l['atendente'])) : mb_strtoupper(primeiroNome((string)$l['por_nome'])); $tp = $tipos[(string)$l['tipo']] ?? 'Cancelamento';
    if ($conta) { $uni[$u]['cancN']++; $uni[$u]['cancV'] = round($uni[$u]['cancV'] + (float)$l['valor'], 2); if ((int)$l['fora'] === 1) $uni[$u]['foraN']++;
      $k = $u . '|' . $at; if (!isset($cancAt[$k])) $cancAt[$k] = ['u' => $u, 'nome' => $at, 'n' => 0, 'v' => 0.0, 'fora' => 0]; $cancAt[$k]['n']++; $cancAt[$k]['v'] = round($cancAt[$k]['v'] + (float)$l['valor'], 2); if ((int)$l['fora'] === 1) $cancAt[$k]['fora']++;
      $k = $u . '|' . $tp; if (!isset($cancTipo[$k])) $cancTipo[$k] = ['u' => $u, 'tipo' => $tp, 'n' => 0]; $cancTipo[$k]['n']++; }
    else $uni[$u]['desfN']++;
    if (count($canc) >= 1500) continue;
    $it = json_decode((string)$l['itens'], true); $cont = [];   // os itens iguais juntos ("3 × RECREAÇÃO · 30 MIN"), para a linha não ficar enorme
    if (is_array($it)) foreach ($it as $x) { $n = is_array($x) ? trim((string)($x['n'] ?? '')) : ''; if ($n !== '') $cont[$n] = ($cont[$n] ?? 0) + 1; }
    $itens = implode(', ', array_map(fn($n, $q) => ($q > 1 ? $q . ' × ' : '') . $n, array_keys($cont), $cont)); if (mb_strlen($itens) > 160) $itens = mb_substr($itens, 0, 157) . '…';
    $canc[] = ['id' => (int)$l['id'], 'quando' => (string)$l['quando'], 'u' => $u, 'tipo' => $tipos[(string)$l['tipo']] ?? 'Cancelamento', 'crianca' => (string)$l['nome'], 'resp' => (string)$l['resp'], 'valor' => round((float)$l['valor'], 2), 'min' => (int)$l['minutos'],
      'itens' => $itens, 'motivo' => (string)$l['motivo'], 'atendente' => $at, 'usuario' => (string)$l['por_nome'], 'conta' => $conta, 'entrada' => in_array((string)$l['tipo'], ['', 'desfeita'], true),
      'papel' => PAPEIS[(string)$l['papel']] ?? (string)$l['papel'], 'fora' => (int)$l['fora'] === 1, 'visto' => (string)$l['visto_por']]; }
  // os descontos: cada desconto dado (o do gestor, em R$ ou %) é uma linha, mesmo que tenha sido repartido em mais de uma venda;
  // o desconto PCD (o da tabela, automático) e os vouchers do shopping vão à parte
  $desc = []; $descQuem = []; $descTotal = 0;
  // 1.49 (auditoria): cada desconto dado é uma linha só, na data em que foi dado (o desconto guarda a linha do dia dele: desc_reg), mesmo
  // repartido em vendas de outro dia; dois descontos diferentes nunca se juntam. Os de antes da 1.49 (sem desc_reg) ficam agrupados como antes
  $tem = (bool)$db->query("SELECT 1 FROM pragma_table_info('vendas') WHERE name = 'desc_reg'")->fetchColumn();
  $L = [];
  if ($tem) { $q = $db->prepare("SELECT * FROM (SELECT COALESCE(MAX(rg.quando), MAX(v.quando)) quando, COALESCE(MAX(rg.unidade_id), MAX(v.unidade_id), 0) u, MAX(v.desc_por) desc_por, MAX(v.desc_motivo) desc_motivo, MIN(v.por) por,
        GROUP_CONCAT(v.servico, ' + ') itens, COALESCE(SUM(v.valor), 0) valor, COALESCE(SUM(v.desc_valor), 0) d, MAX(c.nome) nome, MAX(c.resp) resp FROM vendas v LEFT JOIN registros rg ON rg.id = v.desc_reg LEFT JOIN criancas c ON c.id = v.crianca_id
      WHERE v.desc_reg IS NOT NULL AND v.desc_valor > 0.004 GROUP BY v.desc_reg) WHERE quando >= ? AND quando < ?"); $q->execute([$ini, $fim]); $L = $q->fetchAll(); }
  $q = $db->prepare("SELECT COALESCE(v.unidade_id, 0) u, v.crianca_id, v.desc_por, v.desc_motivo, substr(v.quando, 1, 10) dia, MAX(v.quando) quando, MIN(v.por) por, GROUP_CONCAT(v.servico, ' + ') itens,
      COALESCE(SUM(v.valor), 0) valor, COALESCE(SUM(v.desc_valor), 0) d, c.nome, c.resp FROM vendas v LEFT JOIN criancas c ON c.id = v.crianca_id
    WHERE v.quando >= ? AND v.quando < ? AND v.desc_valor > 0.004" . ($tem ? ' AND v.desc_reg IS NULL' : '') . " GROUP BY u, v.crianca_id, v.desc_por, v.desc_motivo, dia");
  $q->execute([$ini, $fim]); $L = array_merge($L, $q->fetchAll()); usort($L, fn($x, $y) => strcmp((string)$y['quando'], (string)$x['quando']));
  foreach ($L as $l) { $u = (int)$l['u']; $U($u); $d = round((float)$l['d'], 2); $uni[$u]['descN']++; $uni[$u]['descV'] = round($uni[$u]['descV'] + $d, 2); $descTotal++;
    $cheio0 = round((float)$l['valor'] + $d, 2); $uni[$u]['descPct'] = round($uni[$u]['descPct'] + ($cheio0 > 0 ? $d / $cheio0 * 100 : 0), 2);
    $qn = $nomeUsu[(int)$l['desc_por']] ?? 'Sem nome'; $k = $u . '|' . $qn; if (!isset($descQuem[$k])) $descQuem[$k] = ['u' => $u, 'nome' => $qn, 'n' => 0, 'v' => 0.0]; $descQuem[$k]['n']++; $descQuem[$k]['v'] = round($descQuem[$k]['v'] + $d, 2);
    if (count($desc) >= 1500) continue; $cheio = round((float)$l['valor'] + $d, 2);
    $desc[] = ['quando' => (string)$l['quando'], 'u' => $u, 'crianca' => (string)$l['nome'], 'resp' => (string)$l['resp'], 'itens' => (string)$l['itens'], 'cheio' => $cheio, 'desconto' => $d, 'pct' => $cheio > 0 ? round($d / $cheio * 100, 1) : 0,
      'motivo' => (string)$l['desc_motivo'], 'liberou' => $nomeUsu[(int)$l['desc_por']] ?? '', 'atendente' => $nomeUsu[(int)$l['por']] ?? '']; }
  $q = $db->prepare('SELECT COALESCE(unidade_id, 0) u, COUNT(*) n, COALESCE(SUM(cheio - valor - desc_valor), 0) v FROM vendas WHERE quando >= ? AND quando < ? AND pcd = 1 AND cheio IS NOT NULL AND cheio - valor - desc_valor > 0.004 GROUP BY u');
  $q->execute([$ini, $fim]); foreach ($q->fetchAll() as $l) { $u = (int)$l['u']; $U($u); $uni[$u]['pcdN'] = (int)$l['n']; $uni[$u]['pcdV'] = round((float)$l['v'], 2); }
  ksort($uni); $out = []; foreach ($uni as $u => $x) $out[] = ['u' => $u] + $x;
  responder(['de' => $de, 'ate' => $ate, 'dias' => $dias, 'gerado' => agora(), 'hoje' => date('Y-m-d'), 'unidades' => unidades(), 'porUnidade' => $out, 'criancasTotal' => $criancasTotal,
    'grupos' => array_values($grupos), 'pacotes' => array_values($pacotes), 'porDia' => $porDia, 'atendentes' => $atend, 'formas' => array_values($formas),
    'prod' => $prod, 'itens' => array_values($porItem), 'catalogo' => array_values($catalogo),
    'cancelamentos' => ['total' => $cancTotal, 'lista' => $canc, 'atendentes' => array_values($cancAt), 'tipos' => array_values($cancTipo)], 'descontos' => ['total' => $descTotal, 'lista' => $desc, 'quem' => array_values($descQuem)]]);
}
falha('Ação desconhecida.', 404);
