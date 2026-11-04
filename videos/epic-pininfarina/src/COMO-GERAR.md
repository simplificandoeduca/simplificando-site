# Como regerar o vídeo

Tudo é gerado por código, sem imagens de terceiros.

1. `pip install piper-tts onnxruntime soundfile numpy` e baixe o modelo de voz
   `vits-piper-pt_BR-faber-medium` (releases do k2-fsa/sherpa-onnx) para `models/`.
2. `python3 gen_audio.py` → narração (`build/voice.wav`) e tempos (`build/timeline.json`).
3. `python3 gen_music.py` → trilha sintetizada (`build/music.wav`).
4. `node render.js preview 3.5 28 54.5` → frames de conferência em `build/prev/`.
5. `node render.js full` → `build/video.mp4` (Playwright + ffmpeg, 30 fps).
6. Mix e mux:
   ffmpeg -i voice.wav -i music.wav -filter_complex "...sidechaincompress...loudnorm" mix.m4a
   ffmpeg -i video.mp4 -i mix.m4a -c copy epic-pininfarina-reel.mp4

Para trocar o texto, edite a lista `S` em `gen_audio.py`; as cenas em `scene.html`
leem os tempos do `timeline.json`, então a sincronia se ajusta sozinha.

## Versão 3D (atual)

As cenas dos números, das penthouses e da piscina são renderizadas em 3D (Three.js, WebGL)
dentro da própria página, em `epic3d.js`. Para rodar: `npm install three@0.170.0` numa pasta
`three/` ao lado dos scripts, e então `node render.js preview 36 47 86` ou `node render.js full`
(o script sobe um servidor HTTP local porque módulos ES não carregam via file://).
`test3d.js` renderiza quadros avulsos de cada plano (`orbit`, `enter`, `pool`) para ajustes.
