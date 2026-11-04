const http=require('http'),fs=require('fs'),path=require('path');
const root=__dirname; const mime={'.html':'text/html','.js':'text/javascript','.mjs':'text/javascript','.json':'application/json','.jpg':'image/jpeg','.png':'image/png'};
module.exports=function serve(port){return new Promise(res=>{const s=http.createServer((q,r)=>{const p=path.join(root,decodeURIComponent(q.url.split('?')[0]));fs.readFile(p,(e,d)=>{if(e){r.writeHead(404);r.end();return}r.writeHead(200,{'Content-Type':mime[path.extname(p)]||'application/octet-stream'});r.end(d)})});s.listen(port,'127.0.0.1',()=>res(s))})};
