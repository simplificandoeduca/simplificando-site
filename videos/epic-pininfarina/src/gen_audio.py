import wave, json, io, numpy as np, soundfile as sf
from piper import PiperVoice, SynthesisConfig
d="models/vits-piper-pt_BR-faber-medium"
v=PiperVoice.load(f"{d}/pt_BR-faber-medium.onnx", config_path=f"{d}/pt_BR-faber-medium.onnx.json")
SR=22050
# (block, sentence as spoken, caption shown, gap after in seconds)
S=[
 ("hook","Existe um prédio em São Paulo que vai ser mais alto do que qualquer outro residencial da cidade.","Existe um prédio em São Paulo que vai ser <b>mais alto</b> do que qualquer outro residencial da cidade.",0.35),
 ("hook","E o apartamento mais barato dele custa mais de doze milhões de reais.","E o apartamento <b>mais barato</b> dele custa mais de <b>R$ 12 milhões</b>.",0.7),
 ("name","O nome dele é Épic Jardim Europa, da Cyrela com a J. Safra, na divisa entre Pinheiros e o Jardim Europa.","O nome dele é <b>Epic Jardim Europa</b>, da Cyrela com a J. Safra, na divisa entre Pinheiros e o Jardim Europa.",0.35),
 ("name","E não à toa é chamado de o residencial mais alto de São Paulo.","E não à toa é chamado de <b>o residencial mais alto de São Paulo</b>.",0.7),
 ("pinin","Desenhado pela Pininfarina, o mesmo estúdio italiano que desenha Ferraris, e que assinou o Heritage, o prédio mais caro do Brasil.","Desenhado pela <b>Pininfarina</b>, o estúdio italiano que desenha <b>Ferraris</b> e assinou o Heritage, o prédio mais caro do Brasil.",0.35),
 ("pinin","Esse aqui é o irmão mais alto.","Esse aqui é <b>o irmão mais alto</b>.",0.8),
 ("excl","São duzentos e dez metros, cinquenta e sete andares e só oitenta e nove residências.","São <b>210 metros</b>, <b>57 andares</b> e só <b>89 residências</b>.",0.35),
 ("excl","Duas por andar, com hall privativo, de trezentos e trinta e nove a quatrocentos metros quadrados, quatro suítes e quatro vagas.","Duas por andar, com hall privativo, de <b>339 a 400 m²</b>, 4 suítes e 4 vagas.",0.7),
 ("pent","As versões mais raras ficam a partir do andar cinquenta e dois.","As versões mais raras ficam a partir do <b>andar 52</b>.",0.3),
 ("pent","Penthouses de até seiscentos e trinta e sete metros quadrados, com seis vagas de garagem e a cidade inteira aos pés.","Penthouses de até <b>637 m²</b>, com <b>6 vagas</b> e a cidade inteira aos pés.",0.9),
 ("turn","Mas é nos valores que o choque acontece.","Mas é nos valores que <b>o choque acontece</b>.",1.0),
 ("price","O metro quadrado foi vendido entre trinta e cinco e cinquenta e cinco mil reais.","O metro quadrado foi vendido entre <b>R$ 35 mil e R$ 55 mil</b>.",0.35),
 ("price","Uma unidade de trezentos e trinta e nove metros passa dos doze milhões, e as penthouses podem ultrapassar trinta milhões de reais.","Uma unidade de 339 m² passa dos <b>R$ 12 milhões</b>, e as penthouses podem ultrapassar <b>R$ 30 milhões</b>.",0.35),
 ("price","Mesmo assim, em poucos meses, mais de setenta por cento já tinha sido vendido.","Mesmo assim, em poucos meses, <b>mais de 70%</b> já tinha sido vendido.",0.8),
 ("lazer","Em troca, o morador recebe dois andares inteiros de lazer.","Em troca, o morador recebe <b>dois andares inteiros</b> de lazer.",0.3),
 ("lazer","No oitavo, piscinas coberta e descoberta, spa, sauna, academia e quadra de tênis.","No <b>8º</b>, piscinas coberta e descoberta, spa, sauna, academia e quadra de tênis.",0.3),
 ("lazer","No andar cinquenta e um, uma piscina panorâmica a quase duzentos metros do chão, de onde dá para ver São Paulo inteira.","No <b>andar 51</b>, uma piscina panorâmica a quase <b>200 metros do chão</b>, de onde dá para ver São Paulo inteira.",0.9),
 ("meta","O Épic não é apenas um prédio.","O Epic não é apenas um prédio.",0.5),
 ("meta","É uma escultura habitável, onde o luxo deixa de ser conforto e se torna altitude.","É uma <b>escultura habitável</b>, onde o luxo deixa de ser conforto e se torna <b>altitude</b>.",1.0),
 ("ask","A pergunta que fica é: exclusividade ou exagero?","A pergunta que fica é: <b>exclusividade ou exagero?</b>",0.2),
]
cfg=SynthesisConfig(length_scale=0.92, noise_scale=0.6, noise_w_scale=0.75)
LEAD=0.6
t=LEAD
out=[np.zeros(int(LEAD*SR),dtype=np.float32)]
timeline=[]
for block,spoken,cap,gap in S:
    buf=io.BytesIO()
    with wave.open(buf,"wb") as w:
        v.synthesize_wav(spoken,w,syn_config=cfg)
    buf.seek(0)
    a,sr=sf.read(buf,dtype="float32")
    assert sr==SR, sr
    # trim leading/trailing silence
    thr=0.01
    idx=np.where(np.abs(a)>thr)[0]
    a=a[max(0,idx[0]-int(0.05*SR)):min(len(a),idx[-1]+int(0.12*SR))]
    dur=len(a)/SR
    timeline.append({"block":block,"text":cap,"start":round(t,3),"end":round(t+dur,3)})
    out.append(a); out.append(np.zeros(int(gap*SR),dtype=np.float32))
    t+=dur+gap
out.append(np.zeros(int(2.5*SR),dtype=np.float32))
voice=np.concatenate(out)
voice=voice/np.max(np.abs(voice))*0.9
sf.write("build/voice.wav",voice,SR)
total=len(voice)/SR
json.dump({"total":round(total,3),"sentences":timeline},open("build/timeline.json","w"),ensure_ascii=False,indent=1)
print("total",round(total,2),"s")
for s in timeline: print(f'{s["start"]:6.2f}-{s["end"]:6.2f} {s["block"]:6s} {s["text"][:60]}')
