import numpy as np, json, soundfile as sf
SR=44100
T=json.load(open("build/timeline.json"))
total=T["total"]; N=int(total*SR); t=np.arange(N)/SR
blk={}
for s in T["sentences"]: blk.setdefault(s["block"],[s["start"],s["end"]]); blk[s["block"]][1]=s["end"]
turn=blk["turn"][0]; ask=blk["ask"][0]; meta=blk["meta"][0]
def note(n): return 440*2**((n-69)/12)
# chords (midi) D minor: Dm, Bb, F, C  -> 4 bars each 8 s
chords=[[50,57,62,65],[46,53,58,62],[41,48,53,57],[48,55,60,64]]
bar=8.0
mix=np.zeros(N)
# --- PAD: soft harmonics, slow attack, per bar crossfade
for i in range(int(np.ceil(total/bar))):
    ch=chords[i%4]; s0=i*bar; s1=min(total,(i+1)*bar)
    a=int(s0*SR); b=int(s1*SR); tt=t[a:b]-s0
    env=np.minimum(1,tt/1.5)*np.minimum(1,(s1-s0-tt)/1.5+0.02)
    seg=np.zeros(b-a)
    for m in ch:
        f=note(m)
        for h,g in ((1,1),(2,.35),(3,.15),(4,.08)):
            seg+=g*np.sin(2*np.pi*f*h*tt+np.random.rand()*6.28)*(1+0.06*np.sin(2*np.pi*0.2*tt))
        seg+=0.5*np.sin(2*np.pi*f*1.003*tt)  # detune shimmer
    mix[a:b]+=0.035*seg*env
# --- SUB BASS: root sine, an octave down
for i in range(int(np.ceil(total/bar))):
    ch=chords[i%4]; s0=i*bar; s1=min(total,(i+1)*bar); a=int(s0*SR); b=int(s1*SR); tt=t[a:b]-s0
    env=np.minimum(1,tt/0.3)*np.minimum(1,(s1-s0-tt)/0.3+0.02)
    mix[a:b]+=0.16*np.sin(2*np.pi*note(ch[0]-12)*tt)*env
# --- PULSE: soft kick, 90 bpm, intensity builds; stops at turn, returns after price
bpm=90; beat=60/bpm
def kick(tt): return np.sin(2*np.pi*(45*tt+ 60*np.exp(-tt*25)))*np.exp(-tt*9)
kl=int(0.5*SR); kk=kick(np.arange(kl)/SR)
tb=0.0
while tb<total:
    a=int(tb*SR); g=0.0
    if tb<turn-5: g=0.25+0.45*min(1,tb/turn)
    elif tb<turn: g=0.75+0.25*(tb-(turn-5))/5
    elif tb<blk["price"][0]: g=0
    elif tb<meta: g=0.85
    else: g=0.3
    seg=kk[:min(kl,N-a)]; mix[a:a+len(seg)]+=0.55*g*seg
    tb+=beat
# --- PLUCK arpeggio (sparse, 8ths, only on odd bars, quiet)
tp=0.0; k=0
while tp<total:
    i=int(tp//bar); ch=chords[i%4]
    if (i%2==1 or tp>blk["price"][0]) and tp<meta:
        m=ch[k%4]+12+(12 if k%8==7 else 0); f=note(m); a=int(tp*SR); L=int(1.2*SR); tt=np.arange(min(L,N-a))/SR
        mix[a:a+len(tt)]+=0.09*np.sin(2*np.pi*f*tt)*np.exp(-tt*4)*(1+0.3*np.sin(2*np.pi*f*2*tt)*np.exp(-tt*8))
    tp+=beat/2; k+=1
# --- RISER: filtered noise sweeping up into the turn
r0=turn-6; a=int(r0*SR); b=int(turn*SR); tt=np.linspace(0,1,b-a)
noise=np.random.randn(b-a)
# crude lowpass->highpass sweep via cumulative smoothing
from numpy.fft import rfft,irfft
F=rfft(noise); fr=np.fft.rfftfreq(b-a,1/SR)
F*=np.exp(-((fr-900)/700)**2); nz=irfft(F,b-a)
mix[a:b]+=0.5*nz/np.max(np.abs(nz))*(tt**2.5)
# --- HITS: boom at turn and at 'ask'
def boom(a,g):
    L=int(2.5*SR); tt=np.arange(min(L,N-a))/SR
    h=np.sin(2*np.pi*(38*tt+40*np.exp(-tt*12)))*np.exp(-tt*1.8)
    h+=0.3*np.random.randn(len(tt))*np.exp(-tt*30)
    mix[a:a+len(tt)]+=g*h
boom(int(turn*SR)-int(0.08*SR),0.9); boom(int(ask*SR)-int(0.1*SR),0.7); boom(int(blk["pinin"][1]*SR),0.35)
# --- master: gentle limiter, fade in/out
mix=np.tanh(mix*1.6)/1.6
fi=int(1.5*SR); mix[:fi]*=np.linspace(0,1,fi); fo=int(2.5*SR); mix[-fo:]*=np.linspace(1,0,fo)
mix=mix/np.max(np.abs(mix))*0.85
sf.write("build/music.wav",mix.astype(np.float32),SR)
print("music",round(total,2),"s  turn@",turn,"ask@",ask)
