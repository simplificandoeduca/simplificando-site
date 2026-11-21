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
require __DIR__ . '/resumo.php';   // 1.54: os números, o PDF e o e-mail do resumo
require __DIR__ . '/gestao.php';   // 2.0: os lançamentos do financeiro (receitas, despesas, recebimentos), o relatório e o PDF dele
require __DIR__ . '/gestao2.php';   /* 2.1: fornecedores, contas a pagar, o painel financeiro e as planilhas */
require __DIR__ . '/caixa.php';   /* 2.2: o controle de caixa (o caixa do dia conferido com a Rede) */
require __DIR__ . '/festas.php';   /* 2.3: as festas, com o lucro real de cada uma */
require __DIR__ . '/pdfbonito.php';   /* 2.6: a moldura dos relatórios bonitos (a logo, o ursinho, os gráficos) */
require __DIR__ . '/freelas.php';   /* 2.6: os freelancers */
require __DIR__ . '/relatorios.php';   /* 2.6: os relatórios do caixa e das festas */
require __DIR__ . '/comissoes.php';   /* 2.8: as comissões das vendedoras */
require __DIR__ . '/clientes.php';   /* 3.0: o relatório por cliente */
require __DIR__ . '/comprovantes.php';   /* 3.0: o comprovante das despesas */
set_exception_handler(function (Throwable $e) { registrarErro('[financas] ' . $e->getMessage() . ' @' . basename($e->getFile()) . ':' . $e->getLine()); falha('Algo deu errado. Tente de novo em instantes.', 500); });
const FIN_VERSAO = '3.6';

$in = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $bruto = file_get_contents('php://input') ?: '';
  /* 2.4: o PDF da Rede (anexado no Controle de caixa) pode passar disso: até 12 MB, só para ele */
  if (strlen($bruto) > (preg_match('/^\{\s*"acao"\s*:\s*"(cx_pdf|cp_anexar)"/', $bruto) ? 12 * 1024 * 1024 : 200000)) falha('Envio grande demais.', 413);   /* 3.0: e o comprovante da despesa */
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
  $psv = json_decode((string)@file_get_contents(__DIR__ . '/../versao.json'), true);   /* 2.5: a versão instalada, para a página velha se renovar */
  responder(['versao' => FIN_VERSAO, 'ps' => is_array($psv) ? (string)($psv['versao'] ?? '') : '', 'temConta' => (bool)finPrincipal(), 'usuario' => $eu ? finOut($eu) : null, 'unidades' => $eu ? unidades() : []]);
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
try { if (finEmailDevido()) finEmailAuto(); } catch (Throwable $e) { registrarErro('e-mail do resumo do dia: ' . $e->getMessage()); }   // 1.54
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
if ($acao === 'meta') responder(['meta' => metaDoDia(null)]);   /* 3.4 (1.80): a meta do dia, para a comemoração */
if ($acao === 'resumo') responder(finResumo($in));
// 1.54: o resumo do dia (ou do período) em PDF, e o resumo do dia por e-mail
if ($acao === 'pdf') finPdfSaida($in);
if ($acao === 'email_ver') responder(['email' => finEmailPublico()]);
if ($acao === 'email_salvar') responder(['email' => finEmailSalvar($in)]);
if ($acao === 'email_enviar') {
  $c = finEmailCfg(); $dia = (string)($in['dia'] ?? date('Y-m-d'));
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dia, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || $dia > date('Y-m-d')) falha('Escolha o dia.');
  $r = finEmailMandar($dia, $c['para']); $c['res'] = $r['ok'] ? 'ok' : $r['erro']; $c['quando'] = date('Y-m-d H:i:s'); $d = finDominio($c); if ($d !== '') $c['dominio'] = $d; finEmailGuarda($c);
  if (!$r['ok']) falha($r['erro'], 502); responder(['email' => finEmailPublico(), 'enviado' => $r]);
}

/* ---------- 2.0: a gestão financeira: os lançamentos do financeiro (no lugar da planilha), o relatório e o PDF dele ---------- */
if ($acao === 'fl_listas') { flPreparar(); responder(['listas' => flListas(), 'importacoes' => flImportacoes(), 'fornecedores' => fnNomes(), 'apagar' => apSomas()]); }
if ($acao === 'fl_listas_salvar') responder(['listas' => flListasSalvar($in)]);
if ($acao === 'fl_listar') { $r_ = flListar($in); if (($in['tipo'] ?? '') === 'despesa') { $cp_ = cpDeVarios(array_column($r_['linhas'], 'id')); foreach ($r_['linhas'] as &$x_) $x_['comp'] = $cp_[$x_['id']] ?? null; unset($x_); } responder($r_); }
if ($acao === 'fl_salvar') responder(flSalvar($in, $eu));
if ($acao === 'fl_remover') responder(flRemover($in, $eu));
if ($acao === 'fl_exportar') responder(flExportar($in));
if ($acao === 'fl_importar') responder(flImportar($in, $eu));
if ($acao === 'fl_importar_fim') responder(flImportarFim($in));
if ($acao === 'fl_rel') responder(flRel($in));
if ($acao === 'fl_pdf') flPdfSaida($in);
/* ---------- 2.2: o controle de caixa: o que passou no caixa no dia (crédito, débito, Pix) e o valor da Rede, conferidos ---------- */
if ($acao === 'cx_dia') responder(cxDia($in));
if ($acao === 'cx_salvar') responder(cxSalvar($in, $eu));
/* 2.4: o PDF da Rede (lido e preenchido sozinho), baixar o PDF anexado e consertar o caixa */
if ($acao === 'cx_pdf') responder(cxPdf($in, $eu));
if ($acao === 'cx_pdf_ver') cxPdfVer($in);
if ($acao === 'cx_ajuste') responder(cxAjuste($in, $eu));
if ($acao === 'cx_ajuste_tirar') responder(cxAjusteTirar($in, $eu));
/* ---------- 2.3: as festas: o que vem do sistema e o que o dono lança (buffet, salgados, papelaria, adiantamento, retornado) → o lucro real ---------- */
if ($acao === 'fs_listar') responder(fsListar($in));
if ($acao === 'fs_salvar') responder(fsSalvar($in, $eu));
/* ---------- 2.6: os freelancers (os dias trabalhados, o combinado, o VT, o que foi pago) e os relatórios bonitos em PDF ---------- */
if ($acao === 'fz_listar') responder(fzListar($in));
if ($acao === 'fz_freela_salvar') responder(fzFreelaSalvar($in, $eu));
if ($acao === 'fz_lancar') responder(fzLancar($in, $eu));
if ($acao === 'fz_dia_salvar') responder(fzDiaSalvar($in, $eu));
if ($acao === 'fz_pagar') responder(fzPagar($in, $eu));
if ($acao === 'fz_dia_tirar') responder(fzDiaTirar($in, $eu));
/* 3.0: o relatório por cliente (o nome do cliente, o pacote e o produto, discriminados) */
if ($acao === 'vc_listar') responder(vcListar($in));
if ($acao === 'vc_pdf') { $de_ = vcDia($in['de'] ?? '', date('Y-m-d')); $ate_ = vcDia($in['ate'] ?? '', $de_); pbSaida(vcPdf($in), 'play-space-vendas-por-cliente-' . $de_ . ($ate_ !== $de_ ? '-a-' . $ate_ : '') . '.pdf'); }
if ($acao === 'fz_valores_salvar') responder(fzValoresSalvar($in, $eu));   /* 3.0: a diária de semana e a de fim de semana */
if ($acao === 'ff_salvar') responder(ffSalvar($in, $eu));   /* 2.9: o fluxo de caixa dos freelancers */
if ($acao === 'ff_devolver') responder(ffDevolver($in, $eu));
if ($acao === 'ff_tirar') responder(ffTirar($in, $eu));
if ($acao === 'fz_pdf') pbSaida(fzPdf($in), 'play-space-freelancers-' . fzMes($in['mes'] ?? '') . '.pdf');
if ($acao === 'fs_pdf') pbSaida(fsPdf($in), 'play-space-festas-' . fsMes($in['mes'] ?? '') . '.pdf');
if ($acao === 'cx_rel_pdf') { $de_ = cxDiaOk($in['de'] ?? ''); $ate_ = cxDiaOk($in['ate'] ?? $de_); pbSaida(cxRelPdf($in), 'play-space-caixa-' . ($de_ === $ate_ ? $de_ : $de_ . '-a-' . $ate_) . '.pdf'); }
/* ---------- 2.8: as comissões (o bônus das vendedoras: quem vendeu cada pacote e cada produto, pelas regras que o financeiro edita) ---------- */
if ($acao === 'cm_listar') responder(cmListar($in));
if ($acao === 'cm_regras_salvar') responder(cmRegrasSalvar($in, $eu));
if ($acao === 'cm_vendedoras_salvar') responder(cmVendedorasSalvar($in, $eu));
if ($acao === 'cm_pdf') { $P_ = cmPeriodo($in['per'] ?? ''); pbSaida(cmPdf($in), 'play-space-comissoes-' . $P_['per'] . '.pdf'); }
/* ---------- 2.1: fornecedores, contas a pagar, o painel financeiro e as planilhas ---------- */
if ($acao === 'fn_listar') responder(fnListar($in));
if ($acao === 'fn_nomes') { flPreparar(); responder(['fornecedores' => fnNomes()]); }
if ($acao === 'fn_ver') responder(fnVer($in));
if ($acao === 'fn_salvar') responder(fnSalvar($in, $eu));
if ($acao === 'fn_remover') responder(fnRemover($in, $eu));
if ($acao === 'ap_listar') { $r_ = apListar($in); $cp_ = cpDeVarios(array_column($r_['linhas'], 'id')); foreach ($r_['linhas'] as &$x_) $x_['comp'] = $cp_[$x_['id']] ?? null; unset($x_); responder($r_); }
/* 3.0: o comprovante das despesas (anexar, conferir o valor, ver, baixar) */
if ($acao === 'cp_anexar') responder(cpAnexar($in, $eu));
if ($acao === 'cp_conferir') responder(cpConferir($in, $eu));
if ($acao === 'cp_ver') responder(cpVer($in));
if ($acao === 'cp_baixar') cpBaixar($in);
if ($acao === 'ap_pagar') responder(apPagar($in, $eu));
if ($acao === 'ap_reabrir') responder(apReabrir($in, $eu));
if ($acao === 'fl_painel') responder(flPainel($in));
if ($acao === 'pl_listar') responder(plListar());
if ($acao === 'pl_criar') responder(plCriar($in, $eu));
if ($acao === 'pl_linhas_add') responder(plLinhasAdd($in, $eu));
if ($acao === 'pl_ver') responder(plVer($in));
if ($acao === 'pl_linha_salvar') responder(plLinhaSalvar($in, $eu));
if ($acao === 'pl_linha_remover') responder(plLinhaRemover($in, $eu));
if ($acao === 'pl_salvar') responder(plSalvar($in, $eu));
if ($acao === 'pl_remover') responder(plRemover($in, $eu));

falha('Ação desconhecida.', 404);
