<?php
/* =====================================================================
   Play Space Finanças 2.6 (Play Space 1.65): a moldura dos relatórios bonitos em PDF (o caixa, as festas e os freelancers)
   Pedido do dono: "um PDF bonito, com dashboards, com informações do crédito, na estética da Play Space, com um urso e a logo".
   - A capa: a faixa creme com a logo (a mesma do sistema), o título, o período e o ursinho (o da tela de entrar) espiando por cima
     dos números, com as patinhas na beirada (e um balãozinho com o recado mais importante do relatório).
   - Os números grandes em cartões, os gráficos (rosca, barras em pé e deitadas) e as tabelas (o cabeçalho repete na página nova).
   - O rodapé de toda página: o nome do relatório, a carinha do ursinho e "página x de y".
   Tudo desenhado aqui (PdfSimples, sem biblioteca), com as letras Helvetica e as cores do sistema.
   ===================================================================== */
final class PdfBonito {
  const CORAL = 'E25B3F'; const TINTA = '3B3B3B'; const MUDO = '7A736B'; const CREME = 'FAF3E9'; const CREME2 = 'FFFBF4'; const GRADE = 'EDE3D6';
  const PESSEGO = 'F5BEA9'; const PESSEGO2 = 'FCE3D8'; const MOSTARDA = 'F0BB5F'; const MOSTARDA2 = 'FBECC8'; const VERDE = '3F8F5E'; const VERDE2 = 'DDEFE3';
  const MENTA = 'DBE8DA'; const VERM = 'C2412A'; const VERM2 = 'F9D9D0'; const PIX = '4FA37E'; const CINZA = '8C8279';
  const FORMAS = ['credito' => ['Crédito', 'E25B3F'], 'debito' => ['Débito', 'F0BB5F'], 'pix' => ['Pix', '4FA37E'], 'dinheiro' => ['Dinheiro', '8C8279']];
  public $p; public $y = 0; public $M = 36; public $W;
  private $tit; private $sub; private $logo; private $rod;
  function __construct(string $titulo, string $sub, string $rodape = '') {
    $this->p = new PdfSimples(); $this->W = PdfSimples::W - 2 * $this->M; $this->tit = $titulo; $this->sub = $sub; $this->rod = $rodape !== '' ? $rodape : $titulo;
    $this->logo = @file_get_contents(__DIR__ . '/../img/logo-pdf.jpg') ?: '';
  }
  static function brl($v) { return finBrl($v); }
  static function pct($a, $b, int $casas = 1) { return $b > 0.004 ? number_format($a / $b * 100, $casas, ',', '.') . '%' : '–'; }
  static function curto($v) { $a = abs((float)$v); $s = $v < 0 ? '-' : ''; if ($a >= 1e6) return $s . 'R$ ' . number_format($a / 1e6, 1, ',', '.') . ' mi'; if ($a >= 1e4) return $s . 'R$ ' . number_format($a / 1e3, 1, ',', '.') . ' mil'; return $s . 'R$ ' . number_format($a, 0, ',', '.'); }

  /* ---------- o ursinho: (cx, cy) é o meio da cabeça; s, o tamanho (1 = o desenho da tela de entrar, cabeça com raio 36) ---------- */
  function urso(float $cx, float $cy, float $s, bool $patas = true) {
    $p = $this->p; $X = fn($v) => $cx + ($v - 62) * $s; $Y = fn($v) => $cy + ($v - 56) * $s;
    foreach ([30, 94] as $ox) { $p->circulo($X($ox), $Y(26), 13 * $s, 'D9A07F'); $p->circulo($X($ox), $Y(26), 7 * $s, 'C98B6B'); }
    $p->circulo($X(62), $Y(56), 36 * $s, 'D9A07F');
    $p->circulo($X(36), $Y(64), 5 * $s, 'E8B096'); $p->circulo($X(88), $Y(64), 5 * $s, 'E8B096');   /* as bochechas */
    $p->elipse($X(62), $Y(72), 15 * $s, 11 * $s, 'F5D9C6');
    foreach ([[48, 49.6], [76, 77.6]] as [$ox, $bx]) { $p->circulo($X($ox), $Y(52), 4.6 * $s, '3B3B3B'); $p->circulo($X($bx), $Y(50.4), 1.4 * $s, 'FFFFFF'); }
    $p->elipse($X(62), $Y(67), 5 * $s, 3.6 * $s, '3B3B3B');
    $p->traco([[$X(62), $Y(70)], [$X(62), $Y(75)]], '3B3B3B', 1.8 * $s);
    $p->curva($X(56), $Y(78), $X(62), $Y(82), $X(68), $Y(78), '3B3B3B', 1.8 * $s);
    if ($patas) foreach ([34, 90] as $ox) {   /* as patinhas na beirada */
      $py = $cy + 41 * $s; $p->elipse($X($ox), $py, 13 * $s, 9.5 * $s, 'E3B08E', 'C98B6B', 1.6 * $s);
      foreach ([[-8, -5], [0, -7], [8, -5]] as [$dx, $dy]) $p->traco([[$X($ox + $dx), $py + $dy * $s], [$X($ox + $dx), $py + ($dy + 4) * $s]], 'C98B6B', 2 * $s);
    }
  }
  /* o balãozinho de fala do ursinho (à esquerda dele), com o recado em até 3 linhas */
  private function balao(float $xd, float $yc, string $t): float {
    $p = $this->p; $tam = 8.6; $L = array_slice($p->quebra($t, 128, $tam, true), 0, 3); $w = 14; foreach ($L as $l) $w = max($w, $p->larg($l, $tam, true) + 22);
    $h = 12 + count($L) * 11.5; $yc = max($yc, 96 + $h / 2); $x = $xd - $w; $y = $yc - $h / 2;   /* abaixo do subtítulo */
    $p->ret($x, $y, $w, $h, 'FFFFFF', 10, 'EFD9CB');
    $p->traco([[$xd - 1, $yc - 4], [$xd + 7, $yc], [$xd - 1, $yc + 4]], 'EFD9CB', 0.7); $p->ret($xd - 2.2, $yc - 3.4, 2.6, 6.8, 'FFFFFF');
    foreach ($L as $i => $l) $p->texto($x + 11, $y + 15.5 + $i * 11.5, $l, $tam, true, self::TINTA);
    return $x;
  }
  /* ---------- a capa (a primeira página): a faixa creme, a logo, o título, os chips e o ursinho ---------- */
  function capa(array $chips = [], string $fala = '') {
    $p = $this->p; $M = $this->M; $W = $this->W; $H = 150;
    $p->ret(0, 0, PdfSimples::W, $H, self::CREME); $p->ret(0, 0, PdfSimples::W, 5, self::CORAL);
    foreach ([[300, 24, 3.2, self::PESSEGO], [338, 128, 2.4, self::MOSTARDA], [412, 18, 2.2, 'B9D3B7'], [262, 134, 2.8, 'B9D3B7'], [452, 132, 2.2, self::PESSEGO], [370, 40, 1.8, self::MOSTARDA]] as [$cx, $cy, $r, $c]) $p->circulo($cx, $cy, $r, $c);   /* confetes */
    if ($this->logo !== '') $p->imagem($this->logo, $M, 26, 80, 64.8);
    $x = $M + ($this->logo !== '' ? 96 : 0);
    $p->texto($x, 40, 'PLAY SPACE · FINANÇAS', 8.5, true, self::CORAL);
    $tam = 23; while ($tam > 15 && $p->larg($this->tit, $tam, true) > 300) $tam -= 0.5;
    $p->texto($x, 68, $this->tit, $tam, true, self::TINTA);
    foreach ($p->quebra($this->sub, 300, 10.5) as $i => $l) { if ($i > 1) break; $p->texto($x, 86 + $i * 13, $l, 10.5, false, self::MUDO); }
    $s = 0.86; $ux = $M + $W - 58; $uy = $H - 41 * $s; $this->urso($ux, $uy, $s);
    $lim = $M + 330; if ($fala !== '') $lim = min($lim, $this->balao($ux - 50, $uy - 10, $fala) - 8);
    $cx = $x; $cy = 112; foreach ($chips as $c) { $w = $p->larg($c, 8, true) + 16; if ($cx + $w > $lim) break; $p->ret($cx, $cy, $w, 17, 'FFFFFF', 8.5, 'EADCCB'); $p->texto($cx + 8, $cy + 11.6, $c, 8, true, self::MUDO); $cx += $w + 6; }
    $this->y = $H + 22;
  }
  function cabPeq() {
    $p = $this->p; $M = $this->M; $W = $this->W;
    $p->ret(0, 0, PdfSimples::W, 58, self::CREME); $p->ret(0, 0, PdfSimples::W, 4, self::CORAL);
    if ($this->logo !== '') $p->imagem($this->logo, $M, 16, 37, 30);
    $x = $M + ($this->logo !== '' ? 46 : 0); $p->texto($x, 28, 'PLAY SPACE · FINANÇAS', 7.5, true, self::CORAL); $p->texto($x, 42, $this->tit . ' · ' . $this->sub, 10, true, self::TINTA);
    $this->urso($M + $W - 16, 34, 0.24, false);
    $this->y = 74;
  }
  function novaPagina() { $this->p->pagina(); $this->cabPeq(); }
  function cabe(float $h) { if ($this->y + $h > PdfSimples::H - 54) $this->novaPagina(); }
  /* o título de uma parte (com a bolinha colorida) e a explicação embaixo; junto: o espaço que precisa caber junto com o título */
  function secao(string $t, string $exp = '', float $junto = 90, string $cor = self::CORAL) {
    $this->cabe($junto); $p = $this->p; $M = $this->M; $this->y += 10;
    $p->circulo($M + 4.5, $this->y + 8.2, 4.5, $cor); $p->texto($M + 15, $this->y + 12, $t, 13.5, true, self::TINTA); $this->y += 20;
    if ($exp !== '') { foreach ($p->quebra($exp, $this->W - 15, 8.6) as $l) { $p->texto($M + 15, $this->y + 9, $l, 8.6, false, self::MUDO); $this->y += 11.5; } }
    $this->y += 6;
  }
  function nota(string $t, string $cor = self::MUDO, float $tam = 8.4) { $p = $this->p; foreach ($p->quebra($t, $this->W, $tam) as $l) { $this->cabe(12); $p->texto($this->M, $this->y + 9, $l, $tam, false, $cor); $this->y += 11.5; } $this->y += 4; }
  /* o aviso numa caixa colorida (o que não bateu, o que falta pagar) */
  function aviso(string $t, string $fundo = self::MOSTARDA2, string $tinta = '7A5A12') {
    $p = $this->p; $L = $p->quebra($t, $this->W - 34, 9, true); $h = 14 + count($L) * 12; $this->cabe($h + 6);
    $p->ret($this->M, $this->y, $this->W, $h, $fundo, 8); $p->circulo($this->M + 13, $this->y + 13, 3.2, $tinta);
    foreach ($L as $i => $l) $p->texto($this->M + 24, $this->y + 16 + $i * 12, $l, 9, true, $tinta);
    $this->y += $h + 8;
  }
  /* ---------- os números grandes: [rótulo, valor, embaixo, cor] em cartões ---------- */
  function kpis(array $L, int $cols = 4, float $h = 66) {
    $p = $this->p; $g = 10; $cw = ($this->W - ($cols - 1) * $g) / $cols;
    foreach (array_chunk($L, $cols) as $linha) {
      $this->cabe($h + 8);
      foreach ($linha as $i => $c) {
        [$rot, $val, $sub, $cor] = $c + ['', '', '', self::CORAL]; $x = $this->M + $i * ($cw + $g); $y = $this->y;
        $p->ret($x, $y, $cw, $h, 'FFFFFF', 10, self::GRADE); $p->ret($x + 12, $y + 11, 18, 3.2, $cor, 1.6);
        $p->texto($x + 12, $y + 26, mb_strtoupper($rot), 7.2, true, self::MUDO);
        $tam = 15.5; while ($tam > 9 && $p->larg($val, $tam, true) > $cw - 22) $tam -= 0.5; $p->texto($x + 12, $y + 44, $val, $tam, true, self::TINTA);
        if ($sub !== '') $p->texto($x + 12, $y + 57, $p->corta($sub, $cw - 22, 7.6), 7.6, false, self::MUDO);
      }
      $this->y += $h + $g;
    }
  }
  /* ---------- a rosca: itens [nome, valor, cor]; no meio, o total; a legenda ao lado (com o valor e o %) ---------- */
  function rosca(float $x, float $y, float $r, array $itens, string $meio, string $meioSub, float $wLeg = 170): float {
    $p = $this->p; $tot = 0.0; foreach ($itens as $it) $tot += max(0, (float)$it[1]); $cx = $x + $r; $cy = $y + $r;
    if ($tot <= 0.004) { $p->circulo($cx, $cy, $r, self::GRADE); $p->circulo($cx, $cy, $r * 0.62, 'FFFFFF'); }
    else { $a = 0.0; foreach ($itens as $it) { $v = max(0, (float)$it[1]); if ($v <= 0) continue; $b = $a + $v / $tot * 360; $p->fatia($cx, $cy, $r, $r * 0.62, $a, $b, $it[2]); if (count(array_filter($itens, fn($z) => $z[1] > 0)) > 1) $p->fatia($cx, $cy, $r + 0.6, $r * 0.62 - 0.6, $b - 0.6, $b + 0.6, 'FFFFFF'); $a = $b; } }
    $tam = 11.5; while ($tam > 7 && $p->larg($meio, $tam, true) > $r * 1.1) $tam -= 0.5;
    $p->texto($cx, $cy + 2, $meio, $tam, true, self::TINTA, 'C'); $p->texto($cx, $cy + 13, $meioSub, 7, false, self::MUDO, 'C');
    $lx = $x + 2 * $r + 18; $ly = $y + max(4, $r - count($itens) * 10);
    foreach ($itens as $i => $it) { $yy = $ly + $i * 20; $p->ret($lx, $yy, 9, 9, $it[2], 2.5);
      $p->texto($lx + 15, $yy + 8, $it[0], 9, true, self::TINTA); $p->texto($lx + $wLeg, $yy + 8, self::brl($it[1]), 9, true, self::TINTA, 'D');
      $p->texto($lx + $wLeg + 40, $yy + 8, self::pct($it[1], $tot), 8.5, false, self::MUDO, 'D'); }
    return max(2 * $r, count($itens) * 20);
  }
  /* ---------- as barras em pé: cats = rótulos; series = [[nome, cor, [valores]]]; empilhadas ou lado a lado ---------- */
  function barras(float $h, array $cats, array $series, bool $pilha = true, string $legenda = '') {
    $p = $this->p; $this->cabe($h + 30); $M = $this->M; $W = $this->W; $y0 = $this->y; $gx = $M + 46; $gw = $W - 46; $ph = $h - 22; $n = max(1, count($cats));
    $max = 0.0; foreach ($cats as $i => $_) { if ($pilha) { $s = 0.0; foreach ($series as $se) $s += max(0, (float)($se[2][$i] ?? 0)); $max = max($max, $s); } else foreach ($series as $se) $max = max($max, (float)($se[2][$i] ?? 0)); }
    $topo = self::topo($max);
    for ($k = 0; $k <= 4; $k++) { $yy = $y0 + $ph - $ph * $k / 4; $p->linha($gx, $yy, $gx + $gw, $yy, $k ? 'F1E9DE' : 'DCCFBF', $k ? 0.6 : 0.9); $p->texto($gx - 6, $yy + 3, self::curto($topo * $k / 4), 7, false, self::MUDO, 'D'); }
    $slot = $gw / $n; $bw = min(26, $slot * ($pilha ? 0.62 : 0.8) / ($pilha ? 1 : max(1, count($series))));
    foreach ($cats as $i => $rot) {
      $xc = $gx + $slot * ($i + 0.5);
      if ($pilha) { $acc = 0.0; foreach ($series as $se) { $v = max(0, (float)($se[2][$i] ?? 0)); if ($v <= 0) continue; $hh = $topo > 0 ? $ph * $v / $topo : 0; $p->ret($xc - $bw / 2, $y0 + $ph - $acc - $hh, $bw, max(0.8, $hh), $se[1], min(2.5, $hh / 2)); $acc += $hh; } }
      else foreach ($series as $j => $se) { $v = max(0, (float)($se[2][$i] ?? 0)); if ($v <= 0) continue; $hh = $topo > 0 ? $ph * $v / $topo : 0; $bx = $xc - count($series) * $bw / 2 + $j * $bw; $p->ret($bx + 0.6, $y0 + $ph - $hh, $bw - 1.2, max(0.8, $hh), $se[1], min(2.5, $hh / 2)); }
      if ($n <= 16 || $i % 2 === 0) $p->texto($xc, $y0 + $ph + 11, $p->corta((string)$rot, $slot * ($n > 16 ? 1.9 : 0.98), 6.8), 6.8, false, self::MUDO, 'C');
    }
    $ly = $y0 + $h + 2; $lx = $gx; foreach ($series as $se) { $p->ret($lx, $ly - 6.5, 8, 8, $se[1], 2); $p->texto($lx + 12, $ly + 0.5, $se[0], 8, true, self::TINTA); $lx += $p->larg($se[0], 8, true) + 30; }
    if ($legenda !== '') $p->texto($M + $W, $ly + 0.5, $legenda, 7.6, false, self::MUDO, 'D');
    $this->y = $ly + 14;
  }
  /* ---------- as barras deitadas (o ranking): [nome, valor, cor, texto à direita] ---------- */
  function barrasH(array $L, float $wNome = 130) {
    $p = $this->p; $M = $this->M; $W = $this->W; $max = 0.0; foreach ($L as $it) $max = max($max, (float)$it[1]);
    foreach ($L as $it) { $this->cabe(20); $y = $this->y;
      $p->texto($M, $y + 10, $p->corta($it[0], $wNome - 8, 8.8, true), 8.8, true, self::TINTA);
      $bw = $W - $wNome - 96; $w = $max > 0 ? max(2, $bw * max(0, (float)$it[1]) / $max) : 2;
      $p->ret($M + $wNome, $y + 2, $bw, 10, 'F4ECE2', 5); $p->ret($M + $wNome, $y + 2, $w, 10, $it[2] ?? self::CORAL, 5);
      $p->texto($M + $W, $y + 10, $it[3] ?? self::brl($it[1]), 8.8, true, self::TINTA, 'D'); $this->y += 18; }
    $this->y += 4;
  }
  private static function topo(float $max): float { if ($max <= 0) return 100; $e = pow(10, floor(log10($max))); foreach ([1, 1.2, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $m) if ($m * $e >= $max) return $m * $e; return 10 * $e; }
  /* ---------- a tabela: cols = [[título, largura (soma = 1), 'E'|'D'|'C']]; linhas = [[células]] (a célula pode ser ['t' => , 'b' => , 'cor' => ]);
     op: total (a linha do total), fundo (por linha: [i => cor]), tam (a letra) ---------- */
  function tabela(array $cols, array $linhas, ?array $total = null, array $op = []) {
    $p = $this->p; $M = $this->M; $W = $this->W; $tam = $op['tam'] ?? 8.6; $lh = $op['alt'] ?? 17;
    $xs = []; $x = $M; foreach ($cols as $c) { $w = $c[1] * $W; $xs[] = [$x, $w, $c[2] ?? 'E']; $x += $w; }
    $cab = function () use ($p, $cols, $xs, $M, $W) { $y = $this->y; $p->ret($M, $y, $W, 18, self::CREME, 6);
      foreach ($cols as $i => $c) { [$x, $w, $al] = $xs[$i]; $tx = $al === 'D' ? $x + $w - 7 : ($al === 'C' ? $x + $w / 2 : $x + 7); $p->texto($tx, $y + 12, mb_strtoupper($c[0]), 6.8, true, self::MUDO, $al); }
      $this->y += 20; };
    $this->cabe(20 + $lh * min(3, max(1, count($linhas)))); $cab();
    $celula = function (array $L, bool $neg) use ($p, $xs, $tam) { $y = $this->y;
      foreach ($L as $i => $c) { if (!isset($xs[$i])) break; [$x, $w, $al] = $xs[$i]; $t = is_array($c) ? (string)($c['t'] ?? '') : (string)$c; $b = is_array($c) && !empty($c['b']) || $neg; $cor = is_array($c) && isset($c['cor']) ? $c['cor'] : self::TINTA;
        $t = $p->corta($t, $w - 12, $tam, $b); $tx = $al === 'D' ? $x + $w - 7 : ($al === 'C' ? $x + $w / 2 : $x + 7); $p->texto($tx, $y + 11.5, $t, $tam, $b, $cor, $al); } };
    foreach ($linhas as $k => $L) {
      if ($this->y + $lh > PdfSimples::H - 54) { $this->novaPagina(); $cab(); }
      $f = $op['fundo'][$k] ?? ($k % 2 ? self::CREME2 : null); if ($f) $p->ret($M, $this->y - 1, $W, $lh, $f, 4);
      $celula($L, false); $this->y += $lh;
    }
    if ($total) { if ($this->y + $lh + 4 > PdfSimples::H - 54) { $this->novaPagina(); $cab(); } $p->linha($M, $this->y + 1, $M + $W, $this->y + 1, 'D8C9B6', 1); $this->y += 4; $celula($total, true); $this->y += $lh; }
    $this->y += 6;
  }
  /* ---------- o arquivo: o rodapé de cada página (o nome, a carinha do ursinho e a página) ---------- */
  function pdf(): string {
    $rod = $this->rod; $M = $this->M; $W = $this->W; $g = date('d/m/Y \à\s H:i', agoraTs());
    $this->p->rodape(function ($p, $i, $n) use ($rod, $M, $W, $g) {
      $y = PdfSimples::H - 30; $p->linha($M, $y - 12, $M + $W, $y - 12, self::GRADE, 0.8);
      $s = 0.15; $cx = $M + 7; $cy = $y - 3; $X = fn($v) => $cx + ($v - 62) * $s; $Y = fn($v) => $cy + ($v - 56) * $s;
      foreach ([30, 94] as $ox) $p->circulo($X($ox), $Y(26), 13 * $s, 'D9A07F'); $p->circulo($cx, $cy, 36 * $s, 'D9A07F'); $p->elipse($X(62), $Y(72), 15 * $s, 11 * $s, 'F5D9C6');
      $p->circulo($X(48), $Y(52), 4.6 * $s, '3B3B3B'); $p->circulo($X(76), $Y(52), 4.6 * $s, '3B3B3B'); $p->elipse($X(62), $Y(67), 5 * $s, 3.6 * $s, '3B3B3B');
      $p->texto($M + 20, $y, 'Play Space · ' . $rod . ' · gerado em ' . $g, 7.6, false, self::MUDO);
      $p->texto($M + $W, $y, 'página ' . $i . ' de ' . $n, 7.6, false, self::MUDO, 'D');
    });
    return $this->p->pdf('Play Space · ' . $this->tit);
  }
}
/* "Novembro de 2026" */
function finMesNome(string $ym): string { $m = (int)substr($ym, 5, 2); return ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'][$m - 1] . ' de ' . substr($ym, 0, 4); }
/* manda o PDF para baixar (como os outros PDFs das Finanças) */
function pbSaida(string $pdf, string $nome): void {
  header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . $nome . '"'); header('Cache-Control: no-store');
  header('Content-Length: ' . strlen($pdf)); echo $pdf; exit;
}
