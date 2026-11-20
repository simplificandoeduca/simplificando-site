<?php
/* =====================================================================
   Play Space Finanças 3.0 (Play Space 1.69): o comprovante das despesas.
   Pedido do dono: "anexar o comprovante no controle de despesas da empresa: o financeiro consegue adicionar o comprovante lá, tem um campo
   para ele adicionar, e esse comprovante vai bater o valor com o que está escrito lá; ele tem que conferir".
   - Cada despesa (os lançamentos e as contas a pagar) recebe o comprovante: a foto ou o PDF (até 8 MB), guardado na pasta de dados (fora
     do site); só quem entra nas Finanças baixa. Trocar o comprovante não apaga o anterior (fica guardado, com quem trocou).
   - A conferência: o valor que está no comprovante, comparado com o valor da despesa. No PDF, o sistema tenta achar o valor sozinho (quando
     o texto do PDF dá para ler); na foto (ou no PDF que não dá para ler), o financeiro escreve o valor que está no comprovante.
     ✓ confere (o mesmo valor) · ✗ não bate (a diferença) · "falta conferir" (o comprovante sem o valor conferido).
   - A situação é sempre calculada com o valor de agora da despesa: se o valor da despesa mudar depois, a conferência acompanha.
   ===================================================================== */
declare(strict_types=1);

const CP_MAX = 8 * 1024 * 1024;
const CP_TIPOS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heic'];
function cpPreparar(): void {
  static $ok = false; if ($ok) return; $ok = true;
  db()->exec("CREATE TABLE IF NOT EXISTS fin_comprovantes (id INTEGER PRIMARY KEY, lanc_id INTEGER NOT NULL, nome TEXT NOT NULL DEFAULT '', tipo TEXT NOT NULL DEFAULT '', arquivo TEXT NOT NULL DEFAULT '',
    tamanho INTEGER NOT NULL DEFAULT 0, valor_lido REAL, valores_lidos TEXT NOT NULL DEFAULT '', valor_conf REAL, conf_por TEXT NOT NULL DEFAULT '', conf_em TEXT, por TEXT NOT NULL DEFAULT '', em TEXT NOT NULL DEFAULT '',
    removido_em TEXT, removido_por TEXT NOT NULL DEFAULT '')");
  db()->exec('CREATE INDEX IF NOT EXISTS fin_comprovantes_l ON fin_comprovantes (lanc_id, removido_em)');
}
/* o tipo pelo começo do arquivo (não pelo nome) */
function cpTipo(string $bin): string {
  if (substr($bin, 0, 5) === '%PDF-') return 'application/pdf';
  if (substr($bin, 0, 3) === "\xFF\xD8\xFF") return 'image/jpeg';
  if (substr($bin, 0, 8) === "\x89PNG\r\n\x1a\n") return 'image/png';
  if (substr($bin, 0, 4) === 'RIFF' && substr($bin, 8, 4) === 'WEBP') return 'image/webp';
  if (substr($bin, 4, 4) === 'ftyp' && preg_match('/^(heic|heix|mif1|msf1|heif)/', substr($bin, 8, 4))) return 'image/heic';
  return '';
}
/* o texto que dá para ler de um PDF (os textos simples das páginas); vazio se não der */
function cpTextoPdf(string $bin): string {
  require_once __DIR__ . '/redepdf.php';
  $t = '';
  try { foreach (rpObjetos($bin) as $o) { if ($o['dados'] === null) continue; try { $c = rpConteudo($o); } catch (Throwable $e) { continue; }
      if (!preg_match('/\b(Tj|TJ)\b/', $c)) continue;
      if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $c, $m)) foreach ($m[0] as $s) { $s = substr($s, 1, -1);
        $s = preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', fn($x) => ctype_digit($x[1][0]) ? chr(octdec($x[1]) & 255) : (['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => '', 'f' => ''][$x[1]] ?? $x[1]), $s);
        $t .= $s; }
      $t .= "\n"; } }
  catch (Throwable $e) { return ''; }
  if ($t !== '' && !mb_check_encoding($t, 'UTF-8')) $t = mb_convert_encoding($t, 'UTF-8', 'ISO-8859-1');
  return $t;
}
/* os valores em reais que aparecem no texto (1.234,56 · R$ 50,00 · 50.00 depois de R$) */
function cpValores(string $t): array {
  $V = [];
  if (preg_match_all('/(?<![\d,.])(\d{1,3}(?:\.\d{3})+|\d+),(\d{2})(?![\d])/', $t, $m, PREG_SET_ORDER)) foreach ($m as $x) $V[] = round((float)(str_replace('.', '', $x[1]) . '.' . $x[2]), 2);
  if (preg_match_all('/R\$\s*(\d+)\.(\d{2})(?![\d])/', $t, $m, PREG_SET_ORDER)) foreach ($m as $x) $V[] = round((float)($x[1] . '.' . $x[2]), 2);
  $V = array_values(array_unique(array_filter($V, fn($v) => $v > 0))); rsort($V); return array_slice($V, 0, 12);
}
function cpPegaLanc(int $id): array { flPreparar(); $q = db()->prepare("SELECT * FROM fin_lanc WHERE id = ? AND removido_em IS NULL"); $q->execute([$id]); $l = $q->fetch(); if (!$l) falha('Lançamento não encontrado.', 404); if ($l['tipo'] !== 'despesa') falha('O comprovante é das despesas.'); return $l; }
function cpAtual(int $lanc): ?array { cpPreparar(); $q = db()->prepare('SELECT * FROM fin_comprovantes WHERE lanc_id = ? AND removido_em IS NULL ORDER BY id DESC LIMIT 1'); $q->execute([$lanc]); $c = $q->fetch(); return $c ?: null; }
/* o que a tela recebe: o comprovante e a conferência com o valor de agora da despesa */
function cpPublico(?array $c, float $valor): ?array {
  if (!$c) return null; $conf = $c['valor_conf'] === null ? null : round((float)$c['valor_conf'], 2);
  $sit = $conf === null ? 'conferir' : (abs($conf - $valor) < 0.005 ? 'confere' : 'nao_bate');
  return ['id' => (int)$c['id'], 'nome' => (string)$c['nome'], 'tipo' => (string)$c['tipo'], 'tamanho' => (int)$c['tamanho'], 'por' => (string)$c['por'], 'em' => (string)$c['em'],
    'lido' => $c['valor_lido'] === null ? null : round((float)$c['valor_lido'], 2), 'lidos' => array_map('floatval', json_decode((string)$c['valores_lidos'], true) ?: []), 'conf' => $conf, 'conf_por' => (string)$c['conf_por'], 'conf_em' => (string)($c['conf_em'] ?? ''),
    'sit' => $sit, 'dif' => $conf === null ? 0.0 : round($conf - $valor, 2)];
}
/* os comprovantes de vários lançamentos de uma vez (a lista) */
function cpDeVarios(array $ids): array {
  cpPreparar(); $ids = array_values(array_filter(array_map('intval', $ids))); if (!$ids) return []; $o = [];
  foreach (array_chunk($ids, 400) as $ch) { $q = db()->query('SELECT c.*, l.valor lv FROM fin_comprovantes c JOIN fin_lanc l ON l.id = c.lanc_id WHERE c.removido_em IS NULL AND c.lanc_id IN (' . implode(',', $ch) . ') ORDER BY c.id');
    foreach ($q->fetchAll() as $c) $o[(int)$c['lanc_id']] = cpPublico($c, (float)$c['lv']); }
  return $o;
}
/* anexar (ou trocar) o comprovante: o arquivo vem em base64; o PDF é lido para achar o valor */
function cpAnexar(array $in, array $eu): array {
  cpPreparar(); $l = cpPegaLanc((int)($in['lanc'] ?? 0)); $db = db();
  $bin = base64_decode((string)($in['arquivo'] ?? ''), true); if ($bin === false || $bin === '') falha('Não consegui ler o arquivo. Escolha de novo.');
  if (strlen($bin) > CP_MAX) falha('Arquivo grande demais (até 8 MB). Tire uma foto menor ou mande o PDF.');
  $tipo = cpTipo($bin); if (!isset(CP_TIPOS[$tipo])) falha('O comprovante precisa ser uma foto (JPG, PNG) ou um PDF.');
  $nome = mb_substr(basename(str_replace('\\', '/', (string)($in['nome'] ?? 'comprovante'))), 0, 160) ?: 'comprovante';
  $pasta = pastaDados() . '/comprovantes'; if (!is_dir($pasta)) @mkdir($pasta, 0700, true);
  $arq = 'comp-' . (int)$l['id'] . '-' . date('Ymd-His', agoraTs()) . '-' . bin2hex(random_bytes(4)) . '.' . CP_TIPOS[$tipo];
  if (!is_dir($pasta) || @file_put_contents($pasta . '/' . $arq, $bin, LOCK_EX) === false) falha('Não consegui guardar o comprovante. Tente de novo.', 500);
  $valor = round((float)$l['valor'], 2); $lidos = $tipo === 'application/pdf' ? cpValores(cpTextoPdf($bin)) : [];
  $lido = null; foreach ($lidos as $v) if (abs($v - $valor) < 0.005) { $lido = $v; break; }
  if ($lido === null && $lidos) $lido = $lidos[0];
  $ant = cpAtual((int)$l['id']); $agora = agora(); $quem = (string)$eu['nome'];
  if ($ant) $db->prepare('UPDATE fin_comprovantes SET removido_em = ?, removido_por = ? WHERE id = ?')->execute([$agora, $quem, (int)$ant['id']]);
  /* o valor do PDF que é o mesmo da despesa já vale como conferido (lido do PDF); senão, falta o financeiro conferir */
  $conf = $lido !== null && abs($lido - $valor) < 0.005 ? $lido : null;
  $db->prepare('INSERT INTO fin_comprovantes (lanc_id, nome, tipo, arquivo, tamanho, valor_lido, valores_lidos, valor_conf, conf_por, conf_em, por, em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
    ->execute([(int)$l['id'], $nome, $tipo, $arq, strlen($bin), $lido, json_encode($lidos), $conf, $conf !== null ? 'lido do PDF' : '', $conf !== null ? $agora : null, $quem, $agora]);
  return ['comprovante' => cpPublico(cpAtual((int)$l['id']), $valor)];
}
/* conferir: o valor que está no comprovante (o financeiro olha e escreve) */
function cpConferir(array $in, array $eu): array {
  $l = cpPegaLanc((int)($in['lanc'] ?? 0)); $c = cpAtual((int)$l['id']); if (!$c) falha('Anexe o comprovante primeiro.');
  $v = cxValor($in['valor'] ?? null); if ($v === null || $v <= 0) falha('Escreva o valor que está no comprovante (ex.: 150,00).');
  db()->prepare('UPDATE fin_comprovantes SET valor_conf = ?, conf_por = ?, conf_em = ? WHERE id = ?')->execute([round($v, 2), (string)$eu['nome'], agora(), (int)$c['id']]);
  return ['comprovante' => cpPublico(cpAtual((int)$l['id']), round((float)$l['valor'], 2))];
}
function cpVer(array $in): ?array { $l = cpPegaLanc((int)($in['lanc'] ?? 0)); return ['comprovante' => cpPublico(cpAtual((int)$l['id']), round((float)$l['valor'], 2))]; }
/* baixar o comprovante (o arquivo guardado) */
function cpBaixar(array $in): void {
  cpPreparar(); $q = db()->prepare('SELECT * FROM fin_comprovantes WHERE id = ?'); $q->execute([(int)($in['id'] ?? 0)]); $c = $q->fetch();
  if (!$c) falha('Comprovante não encontrado.', 404); $f = pastaDados() . '/comprovantes/' . basename((string)$c['arquivo']); if (!is_file($f)) falha('O arquivo do comprovante não está mais guardado.', 404);
  header('Content-Type: ' . ((string)$c['tipo'] ?: 'application/octet-stream')); header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$c['nome'] ?: 'comprovante') . '"');
  header('Content-Length: ' . filesize($f)); header('X-Content-Type-Options: nosniff'); readfile($f); exit;
}
