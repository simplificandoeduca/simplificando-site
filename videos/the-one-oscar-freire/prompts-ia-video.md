# The One Oscar Freire (Cyrela) — Prompts para geração de vídeo 3D por IA

Para usar em Kling, Runway Gen-3/4, Luma Dream Machine, Veo, Hailuo/MiniMax ou Sora.
Fluxo recomendado: **imagem → vídeo** (image-to-video), subindo o render do book como
quadro inicial e colando o prompt. Gere clipes de 5 a 10 s, 9:16, e monte depois na edição.
Prompts em inglês funcionam melhor na maioria dessas ferramentas; a versão em português
está logo abaixo de cada um, para ajustes.

Dados públicos usados (Exame, Forbes, Portas, iApartamentos, out/2025):
Rua Oscar Freire, 2150, Pinheiros, ao lado do metrô Sumaré. Torre de 150 m, a mais alta
da rua. ~45 andares residenciais, ~90 unidades, 2 por andar. Apartamentos de 284 a 365 m²,
penthouses duplex de 581 e 698 m², garden de 437 m², 3 a 4 suítes, até 4 vagas.
Lazer com piscinas coberta e descoberta, spa, saunas, quadra de tênis, áreas verdes e
brinquedoteca em dois andares. VGV estimado R$ 800 milhões. Entrega prevista 2030.

Dicas gerais
- Sempre que possível use o render oficial como imagem inicial; o prompt descreve só o
  MOVIMENTO e a atmosfera, não o prédio (a IA copia o prédio da imagem).
- Peça movimentos lentos. Movimento rápido gera deformação.
- Para o giro 360°, gere 2 a 3 clipes de 90°–120° cada a partir de renders de ângulos
  diferentes e emende na edição; nenhuma IA segura um 360° inteiro sem deformar.
- Negative prompt padrão: `blurry, warped geometry, morphing, extra floors, text,
  watermark, people, cars, flicker, low quality, fisheye`.

---

## 1. Giro aéreo em volta da torre (hero shot)

**Imagem inicial:** render da fachada (vista aérea ou de rua, ao pôr do sol se houver).

**EN**
Cinematic aerial drone shot slowly orbiting a slender 150-meter luxury residential tower
on Rua Oscar Freire, São Paulo, at golden hour. The camera circles the building
counter-clockwise, rising gently from mid-height toward the crown, keeping the tower
centered. Curved floor slabs and floor-to-ceiling glass catch the warm sunset light;
balconies with greenery; tree-lined streets of Jardins and Pinheiros below, the São Paulo
skyline and soft haze on the horizon. Smooth, slow, steady camera motion, photoreal
architectural visualization, 35mm lens, shallow depth, volumetric light, no distortion.

**PT**
Plano aéreo cinematográfico de drone orbitando lentamente uma torre residencial de luxo de
150 m na Rua Oscar Freire, São Paulo, na golden hour. A câmera circula o prédio no sentido
anti-horário subindo suavemente da metade até o topo, mantendo a torre centralizada. Lajes
curvas e vidro do piso ao teto refletem a luz quente; sacadas com vegetação; ruas
arborizadas dos Jardins e Pinheiros embaixo, skyline de São Paulo e névoa suave no
horizonte. Movimento lento e estável, visualização arquitetônica fotorrealista, lente 35 mm,
luz volumétrica, sem distorção.

Variações: "clockwise" / "starting from street level and craning up" / "at blue hour with
the apartments lit from inside".

---

## 2. Aproximação pela rua e subida pela fachada (reveal)

**Imagem inicial:** render de rua / fachada frontal.

**EN**
Slow cinematic dolly forward along Rua Oscar Freire toward the entrance of a luxury
residential tower, then the camera tilts and cranes straight up along the glass façade,
floor by floor, until the top of the 150-meter building is revealed against a sunset sky.
Warm reflections on the glass, trees lining the street, soft lens flare. Steady, slow,
photoreal architectural visualization, no distortion.

**PT**
Dolly lento pela Rua Oscar Freire em direção à entrada da torre, depois a câmera inclina e
sobe reta pela fachada de vidro, andar por andar, até revelar o topo dos 150 m contra o céu
do pôr do sol. Reflexos quentes no vidro, árvores na rua, leve flare. Movimento estável e
lento, fotorrealista, sem distorção.

---

## 3. Entrando no apartamento (hall → living)

**Imagem inicial:** render do living (o melhor do book). Se houver render do hall, gere um
clipe a partir dele e outro a partir do living, e corte entre os dois na porta.

**EN**
First-person steadicam walking slowly through a private elevator hall into the living room
of a 300 m² luxury apartment: double wooden doors open ahead, the camera glides forward
across wide-plank oak floors into an open living space with a 3.2 m ceiling, floor-to-ceiling
windows and a terrace, São Paulo skyline and warm sunset light pouring in. Designer sofa,
dining table with pendant lights, art on the walls, plants. The camera keeps moving forward
and slowly pans toward the window. Smooth, slow, steady motion, photoreal interior
architectural visualization, natural warm light, no distortion.

**PT**
Steadicam em primeira pessoa caminhando devagar do hall privativo do elevador para o living
de um apartamento de 300 m²: portas duplas de madeira abrem à frente, a câmera avança sobre
piso de madeira clara até um living integrado com pé-direito de 3,2 m, janelas do piso ao
teto e terraço, com o skyline de São Paulo e luz quente de fim de tarde entrando. Sofá de
design, mesa de jantar com pendentes, quadros, plantas. A câmera segue avançando e gira
devagar para a janela. Movimento suave e estável, fotorrealista, luz natural quente.

---

## 4. Giro dentro do living (orbit interior)

**Imagem inicial:** render do living.

**EN**
Slow cinematic orbit inside a luxury penthouse living room: the camera arcs around the
sofa and coffee table, revealing the dining area, the open kitchen and the floor-to-ceiling
windows with the São Paulo skyline at sunset. Warm interior lighting, soft shadows,
parallax between furniture and windows. Gentle, continuous motion, photoreal interior
visualization, no distortion.

**PT**
Giro lento dentro do living de uma penthouse de luxo: a câmera faz um arco em volta do sofá
e da mesa de centro, revelando a sala de jantar, a cozinha integrada e as janelas do piso ao
teto com o skyline de São Paulo ao pôr do sol. Iluminação interna quente, sombras suaves,
paralaxe entre móveis e janelas. Movimento contínuo e suave, fotorrealista.

---

## 5. Suíte master e varanda

**Imagem inicial:** render da suíte master.

**EN**
Slow push-in through a luxury master suite toward the floor-to-ceiling window and private
balcony, morning light, linen bedding, wood paneling, city view of Jardins and the São Paulo
skyline beyond the glass. The camera ends at the balcony railing looking out over the city.
Steady, slow, photoreal interior visualization.

**PT**
Avanço lento pela suíte master até a janela do piso ao teto e a varanda privativa, luz da
manhã, roupa de cama de linho, painéis de madeira, vista dos Jardins e do skyline de São
Paulo além do vidro. A câmera termina no guarda-corpo da varanda olhando a cidade.

---

## 6. Lazer: piscina panorâmica

**Imagem inicial:** render da piscina (coberta ou descoberta).

**EN**
Low cinematic glide just above the surface of a rooftop infinity pool on a luxury tower in
São Paulo at sunset, gentle ripples and reflections, sun loungers and planters passing by,
the camera moving toward the infinity edge until the city skyline fills the frame. Smooth,
slow, photoreal architectural visualization.

**PT**
Deslize cinematográfico baixo sobre a água de uma piscina com borda infinita no alto de uma
torre de luxo em São Paulo ao pôr do sol, ondulações e reflexos suaves, espreguiçadeiras e
floreiras passando, a câmera avança até a borda infinita até o skyline preencher o quadro.

---

## 7. Lazer: spa, quadra de tênis, brinquedoteca (um clipe por ambiente)

**EN (modelo, troque o ambiente)**
Slow cinematic dolly through the [spa / tennis court / kids playroom] of a luxury residential
tower in São Paulo, soft warm lighting, high-end materials, steady forward motion, photoreal
architectural visualization, no people.

---

## 8. Transição dia → noite da fachada (time-lapse)

**Imagem inicial:** render da fachada.

**EN**
Static or very slow push-in on a luxury residential tower in São Paulo as the sky transitions
from golden sunset to blue hour, apartment windows gradually lighting up warm, city lights
appearing below, subtle clouds drifting. Timelapse feel, photoreal, no distortion.

---

## Ordem sugerida de montagem (reel de 60 a 90 s)

1. Giro aéreo (1) — gancho
2. Reveal pela rua (2) — endereço
3. Entrando no apartamento (3) — exclusividade
4. Giro no living (4) — metragem e acabamento
5. Suíte (5)
6. Piscina (6) e demais lazer (7)
7. Dia → noite (8) — fechamento

Legendas, narração e trilha podem seguir o mesmo padrão do vídeo do Epic.
