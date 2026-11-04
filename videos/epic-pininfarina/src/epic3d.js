import * as THREE from 'three';
import { RectAreaLightUniformsLib } from 'three/addons/lights/RectAreaLightUniformsLib.js';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import * as BGU from 'three/addons/utils/BufferGeometryUtils.js';

const clamp=(x,a=0,b=1)=>Math.max(a,Math.min(b,x));
const lerp=(a,b,x)=>a+(b-a)*x;
const eio=x=>x<.5?4*x*x*x:1-Math.pow(-2*x+2,3)/2;
const eo=x=>1-Math.pow(1-x,3);
function rng(seed){return function(){seed|=0;seed=seed+0x6D2B79F5|0;let t=Math.imul(seed^seed>>>15,1|seed);t=t+Math.imul(t^t>>>7,61|t)^t;return((t^t>>>14)>>>0)/4294967296}}

/* ---------- procedural textures ---------- */
function canvasTex(w,h,fn,opts={}){const c=document.createElement('canvas');c.width=w;c.height=h;const g=c.getContext('2d');fn(g,w,h);const t=new THREE.CanvasTexture(c);t.wrapS=t.wrapT=THREE.RepeatWrapping;t.colorSpace=opts.linear?THREE.NoColorSpace:THREE.SRGBColorSpace;t.anisotropy=8;return t}
function woodTex(seed=1,base=[150,105,62]){return canvasTex(1024,1024,(g,w,h)=>{const r=rng(seed);const pw=w/14;for(let i=0;i<14;i++){const off=(r()*h/2);for(let j=-1;j<3;j++){const y=j*h/2+off;const k=0.9+r()*0.2;g.fillStyle=`rgb(${base[0]*k|0},${base[1]*k|0},${base[2]*k|0})`;g.fillRect(i*pw,y,pw,h/2);for(let s=0;s<40;s++){g.fillStyle=`rgba(0,0,0,${0.03+r()*0.06})`;g.fillRect(i*pw+r()*pw,y+r()*h/2,1+r()*2,r()*h/2)}g.fillStyle='rgba(0,0,0,.3)';g.fillRect(i*pw,y,2,h/2);g.fillRect(i*pw,y,pw,2)}}})}
function windowTex(seed=2,lit=.35){return canvasTex(256,512,(g,w,h)=>{const r=rng(seed);g.fillStyle='#141a2a';g.fillRect(0,0,w,h);for(let y=0;y<h;y+=32)for(let x=0;x<w;x+=32){g.fillStyle='#1d2538';g.fillRect(x+4,y+4,24,22);if(r()<lit){const c=r()<.75?'#ffd79a':'#bfe0ff';g.fillStyle=c;g.globalAlpha=.3+r()*.6;g.fillRect(x+4,y+4,24,22);g.globalAlpha=1}}})}
function noiseNormal(seed=5){return canvasTex(512,512,(g,w,h)=>{const r=rng(seed);const img=g.createImageData(w,h);const d=img.data;const f=[];for(let i=0;i<24;i++)f.push([r()*6.28,0.5+r()*3,r()*6.28]);for(let y=0;y<h;y++)for(let x=0;x<w;x++){let nx=0,ny=0;for(const [a,k,p] of f){const u=(x/w)*6.283*k,v=(y/h)*6.283*k;nx+=Math.cos(u*Math.cos(a)+v*Math.sin(a)+p)*0.08;ny+=Math.cos(u*Math.sin(a)-v*Math.cos(a)+p+1)*0.08}const i=(y*w+x)*4;d[i]=128+nx*127;d[i+1]=128+ny*127;d[i+2]=255;d[i+3]=255}g.putImageData(img,0,0)},{linear:true})}
function artTex(seed=9){return canvasTex(512,384,(g,w,h)=>{const r=rng(seed);const gr=g.createLinearGradient(0,0,w,h);gr.addColorStop(0,'#2b2a3d');gr.addColorStop(.5,'#9a6b4a');gr.addColorStop(1,'#e2c08f');g.fillStyle=gr;g.fillRect(0,0,w,h);for(let i=0;i<9;i++){g.fillStyle=`rgba(${20+r()*40|0},${20+r()*30|0},${30+r()*40|0},${.3+r()*.5})`;g.beginPath();g.ellipse(r()*w,r()*h,40+r()*160,20+r()*80,r()*3,0,6.283);g.fill()}})}
function marbleTex(seed=4){return canvasTex(512,512,(g,w,h)=>{const r=rng(seed);g.fillStyle='#e9e4dc';g.fillRect(0,0,w,h);for(let i=0;i<30;i++){g.strokeStyle=`rgba(140,130,120,${.06+r()*.1})`;g.lineWidth=1+r()*1.5;g.beginPath();let x=r()*w,y=r()*h;g.moveTo(x,y);for(let k=0;k<12;k++){x+=(r()-.5)*120;y+=(r()-.5)*120;g.lineTo(x,y)}g.stroke()}})}

/* ---------- sky + sun + city ---------- */
const SUN_DIR=new THREE.Vector3(-0.55,0.16,-0.82).normalize();
function makeSky(){
  const geo=new THREE.SphereGeometry(4000,48,24);
  const mat=new THREE.ShaderMaterial({side:THREE.BackSide,depthWrite:false,uniforms:{sun:{value:SUN_DIR}},
    vertexShader:`varying vec3 vW;void main(){vW=normalize((modelMatrix*vec4(position,1.)).xyz);gl_Position=projectionMatrix*modelViewMatrix*vec4(position,1.);}`,
    fragmentShader:`uniform vec3 sun;varying vec3 vW;void main(){float h=vW.y;vec3 top=vec3(.13,.16,.36);vec3 mid=vec3(.55,.42,.52);vec3 hor=vec3(1.0,.62,.36);vec3 c=mix(hor,mid,smoothstep(-.02,.18,h));c=mix(c,top,smoothstep(.18,.7,h));float d=max(dot(vW,sun),0.);c+=vec3(1.,.75,.45)*pow(d,6.)*.6+vec3(1.,.9,.7)*pow(d,80.)*0.7+vec3(1.,.95,.85)*pow(d,900.)*4.;c=mix(c,vec3(.45,.38,.42),smoothstep(0.,-.25,h));gl_FragColor=vec4(c,1.);}`});
  return new THREE.Mesh(geo,mat);
}
function makeCity(seed,opts){
  const {radius=2200,clear=90,yBase=0,count=1700,tall=1}=opts; const r=rng(seed);
  const winT=windowTex(seed+1,0.3); winT.repeat.set(1,1);
  const geos=[]; const col=new THREE.Color();
  for(let i=0;i<count;i++){
    const a=r()*6.283, d=clear+Math.pow(r(),0.6)*(radius-clear); const x=Math.cos(a)*d, z=Math.sin(a)*d;
    const far=d/radius; const w=12+r()*26, dpt=12+r()*26; let h=(8+r()*40)*(1+2.2*Math.pow(Math.max(0,1-far),1.5)*(x>0?1.3:0.5))*tall; if(r()<.05)h*=1.8;
    const g=new THREE.BoxGeometry(w,h,dpt); g.translate(x,yBase+h/2,z);
    const uv=g.attributes.uv; const fx=w/4, fy=h/3.6, fz=dpt/4; // scale uvs per face: box uv order: px,nx,py,ny,pz,nz (4 verts each)
    for(let k=0;k<24;k++){const f=Math.floor(k/4); let su=1,sv=1; if(f<2){su=fz;sv=fy}else if(f<4){su=fx;sv=fz}else{su=fx;sv=fy} uv.setXY(k,uv.getX(k)*su,uv.getY(k)*sv)}
    const n=g.attributes.position.count; const cols=new Float32Array(n*3); const k=0.55+r()*0.5; col.setRGB(0.42*k,0.40*k,0.40*k); for(let j=0;j<n;j++){cols[j*3]=col.r;cols[j*3+1]=col.g;cols[j*3+2]=col.b} g.setAttribute('color',new THREE.BufferAttribute(cols,3));
    geos.push(g);
  }
  const merged=BGU.mergeGeometries(geos,false);
  const mat=new THREE.MeshStandardMaterial({vertexColors:true,map:winT,emissiveMap:winT,emissive:new THREE.Color(0xffe2b0),emissiveIntensity:0.6,roughness:0.85,metalness:0.1});
  const mesh=new THREE.Mesh(merged,mat); mesh.receiveShadow=true;
  const grp=new THREE.Group(); grp.add(mesh);
  const ground=new THREE.Mesh(new THREE.CircleGeometry(radius+800,64),new THREE.MeshStandardMaterial({color:0x2a2622,roughness:1})); ground.rotation.x=-Math.PI/2; ground.position.y=yBase-0.05; ground.receiveShadow=true; grp.add(ground);
  // trees (Jardim Europa greenery)
  const tg=new THREE.SphereGeometry(1,6,5); const tree=new THREE.InstancedMesh(tg,new THREE.MeshStandardMaterial({color:0x2f5a2a,roughness:1}),900); const m=new THREE.Matrix4(); const q=new THREE.Quaternion(); const s=new THREE.Vector3(); const p=new THREE.Vector3();
  for(let i=0;i<900;i++){const a=r()*6.283,d=clear*0.6+r()*radius*0.5; const sz=4+r()*7; p.set(Math.cos(a)*d,yBase+sz*0.5,Math.sin(a)*d); s.set(sz,sz*0.8,sz); m.compose(p,q,s); tree.setMatrixAt(i,m)} tree.instanceMatrix.needsUpdate=true; grp.add(tree);
  return grp;
}
function sunLight(target,size){const l=new THREE.DirectionalLight(0xffc38a,3.2);l.position.copy(SUN_DIR).multiplyScalar(600).add(target);l.target.position.copy(target);l.castShadow=true;l.shadow.mapSize.set(2048,2048);const c=l.shadow.camera;c.left=-size;c.right=size;c.top=size;c.bottom=-size;c.near=10;c.far=1400;l.shadow.bias=-0.0006;l.shadow.normalBias=0.03;return l}
function baseScene(fogColor,fogDensity){const sc=new THREE.Scene();sc.add(makeSky());sc.fog=new THREE.FogExp2(fogColor,fogDensity);sc.add(new THREE.HemisphereLight(0x8a93c8,0x3a2a22,0.9));return sc}

/* ---------- EXTERIOR ---------- */
function roundedRect(w,d,r){const s=new THREE.Shape();s.moveTo(-w/2+r,-d/2);s.lineTo(w/2-r,-d/2);s.quadraticCurveTo(w/2,-d/2,w/2,-d/2+r);s.lineTo(w/2,d/2-r);s.quadraticCurveTo(w/2,d/2,w/2-r,d/2);s.lineTo(-w/2+r,d/2);s.quadraticCurveTo(-w/2,d/2,-w/2,d/2-r);s.lineTo(-w/2,-d/2+r);s.quadraticCurveTo(-w/2,-d/2,-w/2+r,-d/2);return s}
function buildExterior(env){
  const sc=baseScene(0xd9a07a,0.00075);
  sc.add(makeCity(21,{radius:2200,clear:330,count:1800}));
  { const r2=rng(61); const hm=new THREE.MeshStandardMaterial({color:0xcfc2ad,roughness:0.9}); const rm=new THREE.MeshStandardMaterial({color:0x7a4a3a,roughness:0.9}); const geos=[]; for(let i=0;i<260;i++){const a=r2()*6.283,d=70+r2()*255; const w=8+r2()*10,dd=8+r2()*10,h=4+r2()*5; const g=new THREE.BoxGeometry(w,h,dd); g.translate(Math.cos(a)*d,h/2,Math.sin(a)*d); geos.push(g)} const houses=new THREE.Mesh(BGU.mergeGeometries(geos),hm); houses.castShadow=houses.receiveShadow=true; sc.add(houses); const tm=new THREE.MeshStandardMaterial({color:0x2f5a2a,roughness:1}); const tg=[]; for(let i=0;i<700;i++){const a=r2()*6.283,d=60+r2()*270; const sz=3+r2()*6; const g=new THREE.SphereGeometry(sz,6,5); g.scale(1,0.8,1); g.translate(Math.cos(a)*d,sz*0.7,Math.sin(a)*d); tg.push(g)} const trees=new THREE.Mesh(BGU.mergeGeometries(tg),tm); trees.castShadow=true; sc.add(trees); }
  const H=210,F=57,FH=H/F;
  const twT=windowTex(55,0.22); twT.repeat.set(6,26); const glass=new THREE.MeshPhysicalMaterial({color:0x5d7fa8,metalness:0.35,roughness:0.18,envMap:env,envMapIntensity:1.4,clearcoat:1,clearcoatRoughness:0.1,emissiveMap:twT,emissive:new THREE.Color(0xffe3b8),emissiveIntensity:0.55});
  const slabM=new THREE.MeshStandardMaterial({color:0xe8dcc4,roughness:0.7,metalness:0.05});
  const tower=new THREE.Group();
  const coreG=new THREE.ExtrudeGeometry(roundedRect(28,20,6),{depth:H-8,bevelEnabled:false}); coreG.rotateX(-Math.PI/2); const core=new THREE.Mesh(coreG,glass); core.position.y=4; core.castShadow=core.receiveShadow=true; tower.add(core);
  const slabs=[]; for(let f=0;f<=F;f++){const g=new THREE.ExtrudeGeometry(roundedRect(31.5,23.5,7.5),{depth:0.45,bevelEnabled:false});g.rotateX(-Math.PI/2);g.translate(0,f*FH+3.8,0);slabs.push(g)} const slabMesh=new THREE.Mesh(BGU.mergeGeometries(slabs),slabM); slabMesh.castShadow=slabMesh.receiveShadow=true; tower.add(slabMesh);
  // balcony glass rails
  const railG=new THREE.ExtrudeGeometry(roundedRect(31.6,23.6,7.5),{depth:1.1,bevelEnabled:false}); const rails=[]; for(let f=1;f<=F;f++){const g=railG.clone();g.rotateX(-Math.PI/2);g.translate(0,f*FH+3.8+0.45,0);rails.push(g)} const railMesh=new THREE.Mesh(BGU.mergeGeometries(rails),new THREE.MeshPhysicalMaterial({color:0xbcd3e6,transparent:true,opacity:0.35,roughness:0.05,metalness:0.2,envMap:env,envMapIntensity:1.2,side:THREE.DoubleSide})); tower.add(railMesh);
  // crown
  const crownG=new THREE.ExtrudeGeometry(roundedRect(26,18,6),{depth:5,bevelEnabled:true,bevelSize:1.5,bevelThickness:1.5,bevelSegments:4}); crownG.rotateX(-Math.PI/2); const crown=new THREE.Mesh(crownG,new THREE.MeshStandardMaterial({color:0x4a4034,roughness:0.5,metalness:0.6})); crown.position.y=H-3; tower.add(crown);
  const fin=new THREE.Mesh(new THREE.BoxGeometry(24,0.6,16),new THREE.MeshStandardMaterial({color:0xd9b26a,metalness:0.8,roughness:0.3,emissive:0x5a4010,emissiveIntensity:0.6})); fin.position.y=H+2.6; tower.add(fin);
  // podium
  const podG=new THREE.ExtrudeGeometry(roundedRect(60,46,10),{depth:14,bevelEnabled:false}); podG.rotateX(-Math.PI/2); const pod=new THREE.Mesh(podG,new THREE.MeshPhysicalMaterial({color:0x2a3a4a,metalness:0.5,roughness:0.2,envMap:env,envMapIntensity:1.2})); pod.castShadow=pod.receiveShadow=true; tower.add(pod);
  const podTop=new THREE.Mesh(new THREE.BoxGeometry(62,0.6,48),slabM); podTop.position.y=14.3; podTop.receiveShadow=true; tower.add(podTop);
  const pool=new THREE.Mesh(new THREE.BoxGeometry(22,0.2,8),new THREE.MeshPhysicalMaterial({color:0x3fb6e8,roughness:0.05,metalness:0.1,envMap:env,envMapIntensity:1.5})); pool.position.set(10,14.7,10); tower.add(pool);
  const r=rng(31); const treeM=new THREE.MeshStandardMaterial({color:0x3a6b2e,roughness:1}); for(let i=0;i<60;i++){const s=2+r()*3;const m=new THREE.Mesh(new THREE.SphereGeometry(s,8,6),treeM);const e=r()<.5;m.position.set(e?(r()<.5?-27:27):(r()-.5)*54,14.6+s*0.7,e?(r()-.5)*42:(r()<.5?-20:20));m.castShadow=true;tower.add(m)}
  // plaza + street greenery around base
  const plaza=new THREE.Mesh(new THREE.CircleGeometry(95,48),new THREE.MeshStandardMaterial({color:0x3b3a36,roughness:1})); plaza.rotation.x=-Math.PI/2; plaza.position.y=0.02; plaza.receiveShadow=true; sc.add(plaza);
  for(let i=0;i<90;i++){const a=r()*6.283,d=45+r()*48,s=3+r()*5;const m=new THREE.Mesh(new THREE.SphereGeometry(s,7,6),treeM);m.position.set(Math.cos(a)*d,s*0.8,Math.sin(a)*d);m.castShadow=true;sc.add(m)}
  sc.add(tower);
  const sun=sunLight(new THREE.Vector3(0,100,0),240); sc.add(sun); sc.add(sun.target); const fillL=new THREE.DirectionalLight(0x9fb4e8,1.3); fillL.position.set(300,200,300); sc.add(fillL);
  const cam=new THREE.PerspectiveCamera(50,1080/1920,1,9000);
  const right=new THREE.Vector3(); const tgt=new THREE.Vector3(); return {scene:sc,cam,update(p,t){const q=eio(p); const a=lerp(-0.35,3.4,q); const rad=lerp(290,185,q); const hgt=lerp(75,150,q); cam.position.set(Math.cos(a)*rad,hgt,Math.sin(a)*rad); tgt.set(0,lerp(112,125,q),0); right.set(-Math.sin(a),0,Math.cos(a)); tgt.addScaledVector(right,-lerp(0,34,q)); cam.lookAt(tgt); cam.fov=lerp(62,50,q); cam.updateProjectionMatrix();}};
}

/* ---------- INTERIOR ---------- */
function buildInterior(env){
  const sc=baseScene(0xe0a884,0.00055);
  const CITY_Y=-191; sc.add(makeCity(44,{radius:2600,clear:40,yBase:CITY_Y,count:2000,tall:1.1}));
  const W=16,D=12,Hh=3.4; const wood=woodTex(3,[168,120,74]); wood.repeat.set(W/2.2,D/3.4);
  const floorM=new THREE.MeshStandardMaterial({map:wood,roughness:0.45,metalness:0.05,envMap:env,envMapIntensity:0.35});
  const wallM=new THREE.MeshStandardMaterial({color:0xefe9df,roughness:0.95});
  const ceilM=new THREE.MeshStandardMaterial({color:0xf6f2ea,roughness:1,emissive:0xfff3e4,emissiveIntensity:0.22});
  const room=new THREE.Group(); sc.add(room);
  const floor=new THREE.Mesh(new THREE.PlaneGeometry(W,D),floorM); floor.rotation.x=-Math.PI/2; floor.receiveShadow=true; room.add(floor);
  const ceil=new THREE.Mesh(new THREE.PlaneGeometry(W,D),ceilM); ceil.rotation.x=Math.PI/2; ceil.position.y=Hh; room.add(ceil);
  // back wall with door opening (z=+D/2)
  const bw=(w,h,x,y,z,ry=0)=>{const m=new THREE.Mesh(new THREE.BoxGeometry(w,h,0.25),wallM);m.position.set(x,y,z);m.rotation.y=ry;m.receiveShadow=m.castShadow=true;room.add(m);return m};
  bw(W/2-1.3,Hh,-(W/4+0.65),Hh/2,D/2); bw(W/2-1.3,Hh,(W/4+0.65),Hh/2,D/2); bw(2.6,Hh-2.7,0,2.7+(Hh-2.7)/2,D/2);
  bw(D,Hh,-W/2,Hh/2,0,Math.PI/2); bw(D,Hh,W/2,Hh/2,0,Math.PI/2);
  // cove light
  const cove=new THREE.Mesh(new THREE.BoxGeometry(W-2,0.05,D-2),new THREE.MeshStandardMaterial({color:0xfff2dc,emissive:0xffe6c0,emissiveIntensity:1.2})); cove.position.y=Hh-0.03; room.add(cove);
  
  // window wall (z=-D/2)
  const glassM=new THREE.MeshPhysicalMaterial({color:0xdfe9f2,transparent:true,opacity:0.1,roughness:0.02,metalness:0.1,envMap:env,envMapIntensity:0.6,side:THREE.DoubleSide,depthWrite:false});
  const mullM=new THREE.MeshStandardMaterial({color:0x1b1b1b,roughness:0.4,metalness:0.7});
  const glass=new THREE.Mesh(new THREE.PlaneGeometry(W,Hh),glassM); glass.position.set(0,Hh/2,-D/2); room.add(glass);
  for(let x=-W/2;x<=W/2+0.01;x+=2){const m=new THREE.Mesh(new THREE.BoxGeometry(0.08,Hh,0.12),mullM);m.position.set(x,Hh/2,-D/2);room.add(m)}
  const sill=new THREE.Mesh(new THREE.BoxGeometry(W,0.12,0.3),mullM); sill.position.set(0,0.06,-D/2); room.add(sill);
  const head=new THREE.Mesh(new THREE.BoxGeometry(W,0.12,0.3),mullM); head.position.set(0,Hh-0.06,-D/2); room.add(head);
  // terrace outside the window
  const terr=new THREE.Mesh(new THREE.BoxGeometry(W+4,0.3,4),new THREE.MeshStandardMaterial({color:0xd9cdb6,roughness:0.9})); terr.position.set(0,-0.15,-D/2-2); terr.receiveShadow=true; room.add(terr);
  const rail=new THREE.Mesh(new THREE.PlaneGeometry(W+4,1.1),glassM.clone()); rail.position.set(0,0.55,-D/2-4); room.add(rail);
  // sheer curtains at the sides
  const curtM=new THREE.MeshStandardMaterial({color:0xfff7ec,transparent:true,opacity:0.55,roughness:1,side:THREE.DoubleSide});
  for(const x of [-W/2+1.2,W/2-1.2]){const c=new THREE.Mesh(new THREE.PlaneGeometry(2.2,Hh-0.1,8,1),curtM);const pa=c.geometry.attributes.position;for(let i=0;i<pa.count;i++)pa.setZ(i,Math.sin(pa.getX(i)*6)*0.12);c.position.set(x,Hh/2,-D/2+0.35);room.add(c)}
  // hall (z from D/2 to D/2+5)
  const hall=new THREE.Group(); room.add(hall);
  const hallFloor=new THREE.Mesh(new THREE.PlaneGeometry(3.4,5.2),new THREE.MeshStandardMaterial({map:marbleTex(4),roughness:0.25,metalness:0.05,envMap:env,envMapIntensity:0.5})); hallFloor.rotation.x=-Math.PI/2; hallFloor.position.set(0,0.002,D/2+2.6); hallFloor.receiveShadow=true; hall.add(hallFloor);
  const hallCeil=new THREE.Mesh(new THREE.PlaneGeometry(3.4,5.2),ceilM); hallCeil.rotation.x=Math.PI/2; hallCeil.position.set(0,Hh-0.3,D/2+2.6); hall.add(hallCeil);
  const hw=new THREE.MeshStandardMaterial({color:0x6b5a4a,roughness:0.6}); // wood panel walls
  for(const x of [-1.7,1.7]){const m=new THREE.Mesh(new THREE.BoxGeometry(0.25,Hh,5.2),hw);m.position.set(x,Hh/2,D/2+2.6);m.receiveShadow=true;hall.add(m)}
  const elev=new THREE.Mesh(new THREE.BoxGeometry(3.4,Hh,0.25),new THREE.MeshStandardMaterial({color:0x8a8f96,metalness:0.9,roughness:0.25,envMap:env,envMapIntensity:1})); elev.position.set(0,Hh/2,D/2+5.2); hall.add(elev);
  const elevLine=new THREE.Mesh(new THREE.BoxGeometry(0.03,2.4,0.3),new THREE.MeshStandardMaterial({color:0x111111})); elevLine.position.set(0,1.2,D/2+5.1); hall.add(elevLine);
  const hallLight=new THREE.Mesh(new THREE.BoxGeometry(0.15,0.02,4.6),new THREE.MeshStandardMaterial({emissive:0xffe9c8,emissiveIntensity:2,color:0xffffff})); hallLight.position.set(0,Hh-0.31,D/2+2.6); hall.add(hallLight);
  const hp=new THREE.PointLight(0xffdcb0,14,10,1.6); hp.position.set(0,Hh-0.5,D/2+2.6); hall.add(hp);
  // double doors (pivot at outer edges)
  const doorM=new THREE.MeshStandardMaterial({color:0x3a2a22,roughness:0.45,metalness:0.1,envMap:env,envMapIntensity:0.3});
  const doors=[]; for(const s of [-1,1]){const pv=new THREE.Group();pv.position.set(s*1.3,0,D/2);const d=new THREE.Mesh(new RoundedBoxGeometry(1.3,2.7,0.08,2,0.02),doorM);d.position.set(-s*0.65,1.35,0);d.castShadow=true;pv.add(d);const hd=new THREE.Mesh(new THREE.CylinderGeometry(0.015,0.015,0.9,8),new THREE.MeshStandardMaterial({color:0xd9b26a,metalness:0.9,roughness:0.2}));hd.position.set(-s*1.15,1.1,0.08);pv.add(hd);hall.add(pv);doors.push({pv,s})}
  // furniture
  const fab=new THREE.MeshStandardMaterial({color:0xd8cdb8,roughness:1}); const fabD=new THREE.MeshStandardMaterial({color:0x8d7b66,roughness:1});
  const rb=(w,h,d,m,x,y,z,r=0.08)=>{const o=new THREE.Mesh(new RoundedBoxGeometry(w,h,d,3,r),m);o.position.set(x,y,z);o.castShadow=o.receiveShadow=true;room.add(o);return o};
  const rug=new THREE.Mesh(new THREE.BoxGeometry(7.5,0.03,5),new THREE.MeshStandardMaterial({color:0xcfc6b8,roughness:1})); rug.position.set(-2.5,0.015,-1.2); rug.receiveShadow=true; room.add(rug);
  rb(4.4,0.45,1.1,fab,-2.5,0.35,0.6); rb(1.1,0.45,2.6,fab,-4.4,0.35,-0.6); // L sofa base
  rb(4.4,0.35,0.35,fab,-2.5,0.75,1.0,0.1); rb(0.35,0.35,2.6,fab,-4.8,0.75,-0.6,0.1); // backs
  for(let i=0;i<3;i++) rb(0.55,0.18,0.55,fabD,-3.8+i*1.3,0.66,0.55,0.08); // cushions
  rb(0.9,0.14,0.9,new THREE.MeshStandardMaterial({color:0x4a3b30,roughness:0.5}),-1.8,0.32,-1.4,0.03); // coffee table low
  const ct=new THREE.Mesh(new THREE.CylinderGeometry(0.75,0.75,0.08,32),new THREE.MeshStandardMaterial({map:marbleTex(7),roughness:0.2,envMap:env,envMapIntensity:0.5})); ct.position.set(-2.6,0.42,-1.6); ct.castShadow=true; room.add(ct);
  const ctb=new THREE.Mesh(new THREE.CylinderGeometry(0.3,0.4,0.38,24),mullM); ctb.position.set(-2.6,0.19,-1.6); room.add(ctb);
  rb(1.6,0.5,0.9,fab,-4.2,0.3,-3.6,0.12); // pouf / chaise
  // dining
  const dt=new THREE.Mesh(new THREE.BoxGeometry(3.2,0.08,1.1),new THREE.MeshStandardMaterial({map:woodTex(8,[92,64,42]),roughness:0.35,envMap:env,envMapIntensity:0.4})); dt.position.set(4.2,0.76,-1.6); dt.castShadow=true; room.add(dt);
  for(const x of [-1.3,1.3]){const l=new THREE.Mesh(new THREE.BoxGeometry(0.12,0.72,0.9),mullM);l.position.set(4.2+x,0.36,-1.6);room.add(l)}
  const chM=new THREE.MeshStandardMaterial({color:0x2b2622,roughness:0.8}); for(let i=0;i<3;i++)for(const sz of [-1,1]){rb(0.46,0.06,0.46,chM,3.2+i*1.0,0.46,-1.6+sz*0.8,0.02);rb(0.46,0.5,0.05,chM,3.2+i*1.0,0.72,-1.6+sz*1.02,0.02);rb(0.04,0.44,0.04,chM,3.0+i*1.0,0.22,-1.6+sz*0.6,0.01);rb(0.04,0.44,0.04,chM,3.4+i*1.0,0.22,-1.6+sz*0.6,0.01)}
  const pendM=new THREE.MeshStandardMaterial({color:0xfff1dc,emissive:0xffd9a8,emissiveIntensity:2.2}); for(let i=0;i<3;i++){const p=new THREE.Mesh(new THREE.SphereGeometry(0.16,16,12),pendM);p.position.set(3.2+i*1.0,2.2,-1.6);room.add(p);const c=new THREE.Mesh(new THREE.CylinderGeometry(0.006,0.006,Hh-2.36,6),mullM);c.position.set(3.2+i*1.0,2.2+(Hh-2.36)/2,-1.6);room.add(c);const pl=new THREE.PointLight(0xffd9a8,5,7,1.8);pl.position.set(3.2+i*1.0,2.1,-1.6);room.add(pl)}
  // sideboard + art on back wall
  rb(3.2,0.7,0.5,new THREE.MeshStandardMaterial({map:woodTex(12,[70,50,36]),roughness:0.4,envMap:env,envMapIntensity:0.4}),-3.5,0.35,D/2-0.4,0.02);
  const art=new THREE.Mesh(new THREE.PlaneGeometry(2.2,1.5),new THREE.MeshStandardMaterial({map:artTex(9),roughness:0.9})); art.position.set(-3.5,1.9,D/2-0.14); room.add(art);
  const frame=new THREE.Mesh(new THREE.BoxGeometry(2.3,1.6,0.05),mullM); frame.position.set(-3.5,1.9,D/2-0.17); room.add(frame);
  const art2=new THREE.Mesh(new THREE.PlaneGeometry(1.4,1.9),new THREE.MeshStandardMaterial({map:artTex(21),roughness:0.9})); art2.position.set(5,1.8,D/2-0.14); room.add(art2);
  const frame2=new THREE.Mesh(new THREE.BoxGeometry(1.5,2.0,0.05),mullM); frame2.position.set(5,1.8,D/2-0.17); room.add(frame2);
  // plants
  const leafM=new THREE.MeshStandardMaterial({color:0x3e7a3a,roughness:0.9}); const potM=new THREE.MeshStandardMaterial({color:0xcfc4b0,roughness:0.9});
  const plant=(x,z,s=1)=>{const pot=new THREE.Mesh(new THREE.CylinderGeometry(0.3*s,0.24*s,0.5*s,16),potM);pot.position.set(x,0.25*s,z);pot.castShadow=true;room.add(pot);const r=rng(x*10+z);for(let i=0;i<7;i++){const l=new THREE.Mesh(new THREE.SphereGeometry((0.22+r()*0.2)*s,8,6),leafM);l.position.set(x+(r()-.5)*0.6*s,(0.8+r()*0.9)*s,z+(r()-.5)*0.6*s);l.castShadow=true;room.add(l)}};
  plant(-7.2,-4.8,1.4); plant(7.2,-4.9,1.2); plant(6.8,4.6,1.0);
  // floor lamp
  const lampS=new THREE.Mesh(new THREE.CylinderGeometry(0.012,0.012,1.7,8),new THREE.MeshStandardMaterial({color:0xd9b26a,metalness:0.9,roughness:0.3})); lampS.position.set(-5.6,0.85,1.6); room.add(lampS);
  const lampH=new THREE.Mesh(new THREE.CylinderGeometry(0.25,0.32,0.4,24,1,true),new THREE.MeshStandardMaterial({color:0xfff4e0,emissive:0xffe0b0,emissiveIntensity:1.5,side:THREE.DoubleSide})); lampH.position.set(-5.6,1.75,1.6); room.add(lampH);
  const lampL=new THREE.PointLight(0xffe0b0,6,6,1.8); lampL.position.set(-5.6,1.6,1.6); room.add(lampL);
  // lights
  const sun=sunLight(new THREE.Vector3(0,1,0),18); sun.intensity=4.5; sun.shadow.mapSize.set(2048,2048); sun.shadow.camera.near=200; sun.shadow.camera.far=900; sc.add(sun); sc.add(sun.target);
  RectAreaLightUniformsLib.init(); const ra=new THREE.RectAreaLight(0xffe9d0,2.2,W-2,D-2); ra.position.set(0,Hh-0.05,0); ra.lookAt(0,0,0); sc.add(ra);
  const fill=new THREE.AmbientLight(0xffe6cc,0.45); sc.add(fill);
  const cam=new THREE.PerspectiveCamera(66,1080/1920,0.05,9000);
  // camera path
  const P=new THREE.CatmullRomCurve3([new THREE.Vector3(0,1.55,D/2+4.3),new THREE.Vector3(0,1.55,D/2+2.2),new THREE.Vector3(0.1,1.5,D/2-0.4),new THREE.Vector3(0.7,1.45,2.6),new THREE.Vector3(1.3,1.4,0.6),new THREE.Vector3(1.0,1.35,-0.9)]);
  const L=new THREE.CatmullRomCurve3([new THREE.Vector3(0,1.4,D/2),new THREE.Vector3(0,1.35,D/2-2),new THREE.Vector3(-1.5,1.1,-2),new THREE.Vector3(-2.8,0.9,-5),new THREE.Vector3(1.5,0.9,-7),new THREE.Vector3(-0.5,0.2,-16)]);
  return {scene:sc,cam,update(p,t){const q=eio(p); const pos=P.getPoint(q); const look=L.getPoint(q); cam.position.copy(pos); cam.position.y+=0.012*Math.sin(t*2.1); cam.lookAt(look); cam.fov=lerp(62,70,q); cam.updateProjectionMatrix(); const open=eio(clamp((p-0.08)/0.3)); for(const d of doors) d.pv.rotation.y=d.s*open*1.85;}};
}

/* ---------- ROOFTOP POOL (51º) ---------- */
function buildPool(env){
  const sc=baseScene(0xe0a884,0.0006);
  const CITY_Y=-187; sc.add(makeCity(77,{radius:2600,clear:40,yBase:CITY_Y,count:2000,tall:1.1}));
  const deckW=30,deckD=22; const wood=woodTex(5,[150,112,76]); wood.repeat.set(deckW/2.4,deckD/2.4);
  const pw=16,pd=6.5,pz=-5; const deckM=new THREE.MeshStandardMaterial({map:wood,roughness:0.6,envMap:env,envMapIntensity:0.3});
  for(const [w,d,x,z] of [[deckW,deckD/2+pz-pd/2,0,(-deckD/2+(pz-pd/2))/2],[deckW,deckD/2-(pz+pd/2),0,(deckD/2+(pz+pd/2))/2],[(deckW-pw)/2,pd,-(pw/2+(deckW-pw)/4),pz],[(deckW-pw)/2,pd,(pw/2+(deckW-pw)/4),pz]]){const m=new THREE.Mesh(new THREE.BoxGeometry(w,0.4,d),deckM);m.position.set(x,-0.2,z);m.receiveShadow=true;sc.add(m)}
  // pool basin const basin=new THREE.Mesh(new THREE.BoxGeometry(pw,1.5,pd),new THREE.MeshStandardMaterial({color:0xd6eef5,roughness:0.5,side:THREE.BackSide})); basin.position.set(0,-0.75,pz); sc.add(basin);
  const nrm=noiseNormal(6); nrm.repeat.set(6,2.5);
  const waterM=new THREE.MeshPhysicalMaterial({color:0x56c4ea,roughness:0.04,metalness:0.05,transparent:true,opacity:0.7,envMap:env,envMapIntensity:1.8,normalMap:nrm,normalScale:new THREE.Vector2(0.35,0.35),clearcoat:1});
  const water=new THREE.Mesh(new THREE.PlaneGeometry(pw,pd),waterM); water.rotation.x=-Math.PI/2; water.position.set(0,-0.06,pz); water.renderOrder=5; sc.add(water);
  const coping=new THREE.MeshStandardMaterial({color:0xe6dccb,roughness:0.8});
  for(const [w,d,x,z] of [[pw+0.8,0.4,0,pz-pd/2-0.2],[0.4,pd+0.8,-pw/2-0.2,pz],[0.4,pd+0.8,pw/2+0.2,pz],[pw+0.8,0.4,0,pz+pd/2+0.2]]){const m=new THREE.Mesh(new THREE.BoxGeometry(w,0.08,d),coping);m.position.set(x,0.04,z);sc.add(m)}
  // infinity edge: water spill plane beyond far edge, glass rail further
  const spill=new THREE.Mesh(new THREE.PlaneGeometry(pw,1.2),waterM.clone()); spill.position.set(0,-0.6,pz-pd/2-0.41); sc.add(spill);
  const glassM=new THREE.MeshPhysicalMaterial({color:0xdfe9f2,transparent:true,opacity:0.12,roughness:0.02,metalness:0.1,envMap:env,envMapIntensity:0.6,side:THREE.DoubleSide,depthWrite:false});
  const rail=new THREE.Mesh(new THREE.PlaneGeometry(deckW,1.15),glassM); rail.position.set(0,0.575,-deckD/2+0.1); sc.add(rail);
  const railTop=new THREE.Mesh(new THREE.BoxGeometry(deckW,0.05,0.06),new THREE.MeshStandardMaterial({color:0x2a2a2a,metalness:0.8,roughness:0.3})); railTop.position.set(0,1.15,-deckD/2+0.1); sc.add(railTop);
  // loungers, planters, lounge wall
  const lg=new THREE.MeshStandardMaterial({color:0xf2ede4,roughness:0.9}); const r=rng(9);
  for(let i=0;i<4;i++){const x=-10+i*2.2;const b=new THREE.Mesh(new RoundedBoxGeometry(0.8,0.25,2.0,2,0.06),lg);b.position.set(x,0.3,2.2);b.castShadow=true;sc.add(b);const bk=new THREE.Mesh(new RoundedBoxGeometry(0.8,0.7,0.12,2,0.04),lg);bk.position.set(x,0.6,1.3);bk.rotation.x=-0.5;bk.castShadow=true;sc.add(bk)}
  const leafM=new THREE.MeshStandardMaterial({color:0x3e7a3a,roughness:0.9}); const potM=new THREE.MeshStandardMaterial({color:0x6b6560,roughness:0.9});
  for(let i=0;i<7;i++){const x=-13+i*4.4;const p=new THREE.Mesh(new THREE.BoxGeometry(2,0.7,0.9),potM);p.position.set(x,0.35,8.5);p.castShadow=true;sc.add(p);for(let k=0;k<9;k++){const l=new THREE.Mesh(new THREE.SphereGeometry(0.3+r()*0.3,8,6),leafM);l.position.set(x+(r()-.5)*1.8,0.9+r()*0.9,8.5+(r()-.5)*0.7);l.castShadow=true;sc.add(l)}}
  for(const x of [-9,9]){const t=new THREE.Mesh(new THREE.CylinderGeometry(0.12,0.16,4.5,10),new THREE.MeshStandardMaterial({color:0x5a4634,roughness:1}));t.position.set(x,2.25,-2);t.castShadow=true;sc.add(t);for(let k=0;k<10;k++){const a=k/10*6.283;const f=new THREE.Mesh(new THREE.BoxGeometry(0.25,0.06,2.6),leafM);f.position.set(x+Math.cos(a)*1.3,4.4+Math.sin(k)*0.15,-2+Math.sin(a)*1.3);f.rotation.y=-a;f.rotation.z=0.25;f.castShadow=true;sc.add(f)}}
  const wall=new THREE.Mesh(new THREE.BoxGeometry(deckW,3.4,0.5),new THREE.MeshStandardMaterial({color:0x4a4038,roughness:0.6})); wall.position.set(0,1.7,deckD/2-0.25); wall.receiveShadow=true; sc.add(wall);
  const strip=new THREE.Mesh(new THREE.BoxGeometry(deckW-4,0.06,0.05),new THREE.MeshStandardMaterial({color:0xffffff,emissive:0xffe0b0,emissiveIntensity:2.5})); strip.position.set(0,2.9,deckD/2-0.52); sc.add(strip);
  for(let i=0;i<3;i++){const pl=new THREE.PointLight(0xffdcb0,10,14,1.8);pl.position.set(-10+i*10,2.6,deckD/2-1.5);sc.add(pl)}
  const sun=sunLight(new THREE.Vector3(0,0,0),30); sun.intensity=4; sun.shadow.camera.near=200; sun.shadow.camera.far=900; sc.add(sun); sc.add(sun.target);
  const cam=new THREE.PerspectiveCamera(62,1080/1920,0.05,9000);
  const P=new THREE.CatmullRomCurve3([new THREE.Vector3(-12,1.6,6),new THREE.Vector3(-8,1.3,2.5),new THREE.Vector3(-5,1.0,-0.8),new THREE.Vector3(-3,0.8,-3.2),new THREE.Vector3(-1.5,0.7,-4.6)]);
  const L=new THREE.CatmullRomCurve3([new THREE.Vector3(3,0.2,-5),new THREE.Vector3(3,0.1,-7),new THREE.Vector3(2.5,0.0,-9),new THREE.Vector3(2,-0.2,-16),new THREE.Vector3(0,-1.2,-45)]);
  return {scene:sc,cam,update(p,t){const q=eio(p); cam.position.copy(P.getPoint(q)); cam.lookAt(L.getPoint(q)); cam.fov=lerp(60,66,q); cam.updateProjectionMatrix(); nrm.offset.set(t*0.03,t*0.02); spill.material.normalMap.offset.set(0,-t*0.3);}};
}

export async function init3D(canvas,w,h){
  const renderer=new THREE.WebGLRenderer({canvas,antialias:true,preserveDrawingBuffer:true,powerPreference:'high-performance'});
  renderer.setSize(w,h,false); renderer.setPixelRatio(1); renderer.shadowMap.enabled=true; renderer.shadowMap.type=THREE.PCFSoftShadowMap;
  renderer.toneMapping=THREE.ACESFilmicToneMapping; renderer.toneMappingExposure=1.0; renderer.outputColorSpace=THREE.SRGBColorSpace;
  const pm=new THREE.PMREMGenerator(renderer); const skyScene=new THREE.Scene(); skyScene.add(makeSky()); const env=pm.fromScene(skyScene,0.04).texture;
  const shots={orbit:buildExterior(env),enter:buildInterior(env),pool:buildPool(env)};
  for(const k in shots){ shots[k].scene.environment=env; }
  return {renderer,shots,render(shot,p,t){const s=shots[shot]; s.update(clamp(p),t); renderer.toneMappingExposure = shot==='enter'?0.95:1.05; renderer.render(s.scene,s.cam);}};
}
