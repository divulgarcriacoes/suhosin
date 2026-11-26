/* Kit Matricula: montar a arte na propria vitrine da escola (3 passos: cor, frase, quantidade) e pedir.
   Publicado em wp-content/uploads/dckit-editor.js; os dados vem de window.DCKIT (dckit_vitrine_destaque, mu-plugin dc-kit-matricula.php). */
(function(){
var K=window.DCKIT,sec=document.getElementById('dcv-kit');if(!K||!sec){return;}
var N=1200,MB={x:77,y:395,w:828,h:545};
var EX=[{k:'sq',x:942,y:673,w:115,h:155,src:K.sq,modo:'cor',fit:0.8},{k:'bl',x:755,y:970,w:145,h:88,src:K.bl,modo:'esc',fit:0.8}];
var CORES=[K.cor,'#FFFFFF','#0B2A5B','#1565C0','#00897B','#2E7D32','#F9A825','#E65100','#C62828','#AD1457','#6A1B9A','#212121'].filter(function(c,i,a){return /^#[0-9a-fA-F]{6}$/.test(c||'')&&a.indexOf(c)===i;});
var st={cor:CORES[0],wa:fone(K.tel||''),ig:arroba(K.ig||''),q:K.min},LG={dx:0,dy:0,k:1},LBOX=null,ARR=null,BG=null,BGF=null,LGF=null;/* arquivos originais que a escola importou: vao junto no pedido */
/* arquivo de impressao da pasta: 45 x 25 cm a 150 dpi, recortado do centro da pasta */
var PR={larg:45,alt:25,dpi:150};
function guarda(){}
function fone(v){var d=String(v).replace(/\D/g,'');if(d.length>11&&d.indexOf('55')===0){d=d.slice(2);}if(d.length===11){return '('+d.slice(0,2)+') '+d.slice(2,7)+'-'+d.slice(7);}if(d.length===10){return '('+d.slice(0,2)+') '+d.slice(2,6)+'-'+d.slice(6);}return String(v).trim();}
function arroba(v){v=String(v).trim().replace(/^https?:\/\/(www\.)?instagram\.com\//i,'').replace(/[\/?#].*$/,'').replace(/^@+/,'');return v?'@'+v:'';}
function fmt(v){return 'R$ '+v.toFixed(2).replace('.',',');}
function un(q){var p=null;K.faixas.forEach(function(f){if(q>=f.q){p=f.p;}});return p;}
function esc(s){return String(s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function img(u){return new Promise(function(ok){if(!u){ok(null);return;}var i=new Image();i.onload=function(){ok(i);};i.onerror=function(){ok(null);};i.src=u;});}
function lumImg(im){try{var c=document.createElement('canvas');c.width=48;c.height=27;var g=c.getContext('2d');g.drawImage(im,0,0,48,27);var d=g.getImageData(0,0,48,27).data,s=0,i;for(i=0;i<d.length;i+=4){s+=0.299*d[i]+0.587*d[i+1]+0.114*d[i+2];}return s/(d.length/4)/255;}catch(e){return 0.3;}}
function lumHex(h){var r=parseInt(h.slice(1,3),16),g=parseInt(h.slice(3,5),16),b=parseInt(h.slice(5,7),16);return (0.299*r+0.587*g+0.114*b)/255;}
function escurece(h,f){var r=Math.round(parseInt(h.slice(1,3),16)*f),g=Math.round(parseInt(h.slice(3,5),16)*f),b=Math.round(parseInt(h.slice(5,7),16)*f);return 'rgb('+r+','+g+','+b+')';}

/* ---------- tela */
var css=document.createElement('style');css.textContent='.dckv-ed h3{color:#fff;font-size:17px;margin:16px 0 8px;display:flex;gap:8px;align-items:center}.dckv-ed h3 i{font-style:normal;background:var(--c,#F5B400);color:var(--ct,#0B2A5B);width:26px;height:26px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:900}'
+'.dckv-sw{display:flex;flex-wrap:wrap;gap:10px}.dckv-sw button{width:42px;height:42px;border-radius:50%;border:3px solid rgba(255,255,255,.35);cursor:pointer;padding:0}.dckv-sw button.on{border-color:#fff;box-shadow:0 0 0 3px var(--c,#F5B400)}'
+'.dckv-ed input[type=text]{width:100%;border:0;border-radius:12px;padding:13px 14px;font-size:16px;color:#101828;background:#fff}.dckv-ch{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}.dckv-ch button{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:999px;padding:6px 12px;font-size:13px;cursor:pointer}'
+'.dckv-qt{display:flex;flex-wrap:wrap;gap:8px}.dckv-qt button{background:rgba(255,255,255,.1);border:2px solid rgba(255,255,255,.25);color:#fff;border-radius:12px;padding:8px 12px;font-size:13px;cursor:pointer;line-height:1.25;text-align:left}.dckv-qt button b{display:block;font-size:15px}.dckv-qt button.on{background:#fff;color:#0B2A5B;border-color:var(--c,#F5B400)}'
+'.dckv-tot{margin:12px 0 0;font-size:16px;color:#fff}.dckv-tot b{font-size:22px}.dckv-go{display:block;width:100%;margin-top:14px;border:0;border-radius:14px;padding:17px;font-size:18px;font-weight:900;cursor:pointer;background:var(--c,#F5B400);color:var(--ct,#0B2A5B)}.dckv-go[disabled]{opacity:.6;cursor:wait}'
+'.dckv-lg{margin-top:12px;font-size:13px;color:#C9D3E8}.dckv-lg label{color:#fff;text-decoration:underline;cursor:pointer}.dckv-cv{width:100%;height:auto;display:block;border-radius:14px;background:#fff}.dckv-ld{padding:40px;text-align:center;color:#0B2A5B;font-weight:700}'
+'.dckv-hx{display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap}.dckv-hx input[type=text]{width:130px;font-family:monospace;text-transform:uppercase}.dckv-hx input[type=color]{width:46px;height:46px;border:0;border-radius:10px;padding:0;background:none;cursor:pointer}.dckv-bt2{background:#fff;color:#0B2A5B;border:0;border-radius:12px;padding:12px 14px;font-weight:800;font-size:14px;cursor:pointer;display:inline-block}.dckv-bt2[hidden]{display:none}.dckv-fd{display:flex;align-items:center;gap:14px;border:2px dashed rgba(255,255,255,.55);border-radius:16px;padding:14px 16px;cursor:pointer;background:rgba(255,255,255,.06);color:#fff}.dckv-fd:hover{border-color:var(--c,#F5B400);background:rgba(255,255,255,.12)}.dckv-fd i{flex:0 0 64px;height:44px;border-radius:8px;background:rgba(255,255,255,.15) center/cover no-repeat;display:flex;align-items:center;justify-content:center;font-style:normal;font-size:24px}.dckv-fd b{display:block;font-size:16px}.dckv-fd .fds{display:block;color:#C9D3E8;font-size:12.5px;margin-top:2px;background:none;text-transform:none;letter-spacing:0;font-weight:500;padding:0;border-radius:0}.dckv-ct{display:grid;grid-template-columns:1fr 1fr;gap:8px}.dckv-ct label{font-size:12.5px;color:#C9D3E8;font-weight:700;display:block}.dckv-ct label input{margin-top:4px}.dckv-lgc{display:flex;align-items:center;gap:10px;flex-wrap:wrap;color:#C9D3E8;font-size:13px}.dckv-lgc input[type=range]{flex:1 1 160px;accent-color:var(--c,#F5B400)}.dckv-cv{touch-action:none}.dckv-lv{padding:12px 6px 4px}.dckv .dckv-lv p{margin:0 0 8px;color:#0B2A5B!important;font-weight:800;font-size:14px}.dckv-lv>div{display:flex;gap:8px;flex-wrap:wrap}.dckv-lv .pcs{margin-bottom:10px}.dckv-lv .pcs button{width:auto;flex-direction:row;padding:8px 16px;font-size:13px;border-radius:999px}.dckv-lv .pcs button.on,.dckv-lv .pcs button.on:hover,.dckv-lv .pcs button.on:focus{background:#0B2A5B!important;color:#fff!important;border-color:#0B2A5B;box-shadow:none}.dckv-lv button,.dckv-lv button:hover,.dckv-lv button:focus{width:116px;border:2px solid #E1E4EA;border-radius:12px;background:#fff!important;cursor:pointer;padding:4px 4px 6px;display:flex;flex-direction:column;align-items:center;gap:4px;color:#344054!important;font-size:11px;font-weight:700;line-height:1.2;white-space:normal;text-align:center;text-transform:none;letter-spacing:0;box-shadow:none}.dckv-lv button i{display:block;width:100%;height:52px;border-radius:8px;background:repeating-conic-gradient(#d5d9e0 0 25%,#eef0f4 0 50%) 0 0/14px 14px}.dckv-lv button i b{display:block;width:100%;height:100%;background:center/88% auto no-repeat}.dckv-lv button.on,.dckv-lv button.on:hover,.dckv-lv button.on:focus{border-color:var(--c,#F5B400);box-shadow:0 0 0 2px var(--c,#F5B400);background:#FFF9E8!important}@media(max-width:560px){.dckv-ct{grid-template-columns:1fr}}.dckv-ed .sub{color:#C9D3E8;font-size:13px;margin:12px 0 6px;font-weight:700}.dckv-bt2.x{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.3)}.dckv-ed small.dica{display:block;background:none;color:#C9D3E8;text-transform:none;letter-spacing:0;font-weight:500;padding:0;margin-top:6px}';
document.head.appendChild(css);
var esq=sec.firstElementChild,dir=sec.lastElementChild,ed=null,cv=null;
[].forEach.call(sec.querySelectorAll('[data-dckit-abrir]'),function(a){a.addEventListener('click',function(e){e.preventDefault();abre(true);});});
function abre(rolar){
if(ed){return;}
ed=document.createElement('div');ed.className='dckv-ed';
var h='<small>Kit Matrícula</small><h2 style="font-size:28px">Monte a arte do seu kit</h2><p>Seu logo já está no kit. É só escolher o fundo da pasta, conferir o WhatsApp e o Instagram e ajustar o logo.</p>';
h+='<h3><i>1</i>Escolha o fundo da pasta</h3><label class="dckv-fd"><i class="fdi">&#128444;</i><span><b class="fdt">Clique aqui e escolha o fundo da bolsa</b><span class="fds">Uma imagem do seu computador ou celular. A pasta tem 45 x 25 cm: use imagem deitada e de boa qualidade.</span></span><input type="file" class="bgf" accept="image/*" hidden></label><button type="button" class="dckv-bt2 x bgx" hidden style="margin-top:8px">Tirar a imagem e usar só cor</button><p class="sub">Ou use só uma cor (digite o código ou clique no quadrado)</p>';
h+='<div class="dckv-hx"><input type="color" aria-label="Escolher cor"><input type="text" class="hx" maxlength="7" placeholder="#0B2A5B" aria-label="Cor em hexadecimal"></div>';
h+='<h3><i>2</i>WhatsApp e Instagram da escola</h3><div class="dckv-ct"><label>WhatsApp<input type="text" class="wa" maxlength="20" placeholder="(11) 91234-5678" inputmode="tel"></label><label>Instagram<input type="text" class="ig" maxlength="32" placeholder="@suaescola"></label></div><small class="dica">Saem impressos na pasta, embaixo do logo. Deixe em branco o que não quiser.</small>';
h+='<h3><i>3</i>Posição e tamanho do logo</h3><div class="dckv-lgc"><span>Arraste o logo na foto para mudar de lugar.</span><span>Tamanho</span><input type="range" class="lgk" min="40" max="220" value="100" aria-label="Tamanho do logo"><button type="button" class="dckv-bt2 x lgr">Centralizar</button></div>';
h+='<h3><i>4</i>Quantos kits?</h3><div class="dckv-qt">'+K.faixas.map(function(f){return '<button type="button" data-q="'+f.q+'"><b>'+f.q+' kits</b>'+fmt(f.p)+'/kit</button>';}).join('')+'</div>';
h+='<p class="dckv-tot"></p><button type="button" class="dckv-go"></button>';
h+='<p class="dckv-lg">Quer usar um logo que não está no cadastro? <label>Enviar logo<input type="file" class="lgf" accept="image/*" hidden></label></p>';
ed.innerHTML=h;esq.style.display='none';sec.insertBefore(ed,esq);
cv=document.createElement('canvas');cv.className='dckv-cv';cv.width=N;cv.height=N;cv.setAttribute('role','img');cv.setAttribute('aria-label','Prévia do Kit Matrícula com a sua arte');
var ld=document.createElement('div');ld.className='dckv-ld';ld.textContent='Carregando a prévia do kit...';
dir.style.display='none';var box=document.createElement('div');box.style.cssText='background:#fff;border-radius:20px;padding:8px';box.appendChild(ld);sec.appendChild(box);
var iwa=ed.querySelector('input.wa'),iig=ed.querySelector('input.ig'),lgk=ed.querySelector('input.lgk');iwa.value=st.wa;iig.value=st.ig;
iwa.addEventListener('input',function(){st.wa=iwa.value;pinta();});iwa.addEventListener('blur',function(){st.wa=fone(iwa.value);iwa.value=st.wa;pinta();});
iig.addEventListener('input',function(){st.ig=iig.value;pinta();});iig.addEventListener('blur',function(){st.ig=arroba(iig.value);iig.value=st.ig;pinta();});
lgk.addEventListener('input',function(){LG.k=lgk.value/100;pinta();});
ed.querySelector('.lgr').addEventListener('click',function(){LG={dx:0,dy:0,k:1};lgk.value=100;pinta();});
var hx=ed.querySelector('input.hx'),pk=ed.querySelector('.dckv-hx input[type=color]'),bgx=ed.querySelector('.bgx');
function semImg(){BG=null;BGF=null;bgx.hidden=true;var fi=ed.querySelector('.fdi');fi.style.backgroundImage='';fi.innerHTML='&#128444;';ed.querySelector('.fdt').textContent='Clique aqui e escolha o fundo da bolsa';}
function poeCor(c){st.cor=c.toUpperCase();semImg();marca();pinta();}
hx.addEventListener('input',function(){var v=hx.value.trim();if(v.charAt(0)!=='#'){v='#'+v;}if(/^#[0-9a-fA-F]{6}$/.test(v)){poeCor(v);}});
pk.addEventListener('input',function(){poeCor(pk.value);});
ed.querySelector('.bgf').addEventListener('change',function(){var f=this.files&&this.files[0];if(!f){return;}BGF=f;var r=new FileReader();r.onload=function(){img(r.result).then(function(i){if(i){BG=i;BG.lum=lumImg(i);bgx.hidden=false;var fi=ed.querySelector('.fdi');fi.style.backgroundImage='url('+r.result+')';fi.textContent='';ed.querySelector('.fdt').textContent='Fundo escolhido. Clique para trocar';marca();pinta();}});};r.readAsDataURL(f);this.value='';});
bgx.addEventListener('click',function(){semImg();marca();pinta();});
[].forEach.call(ed.querySelectorAll('.dckv-sw button'),function(b){b.addEventListener('click',function(){poeCor(b.getAttribute('data-c'));});});
[].forEach.call(ed.querySelectorAll('.dckv-qt button'),function(b){b.addEventListener('click',function(){st.q=+b.getAttribute('data-q');guarda();marca();});});
ed.querySelector('.lgf').addEventListener('change',function(){var f=this.files&&this.files[0];if(!f){return;}if(PEC==='pasta'){LGF=f;}var r=new FileReader();r.onload=function(){img(r.result).then(function(i){if(i){var pc=PEC;SEL[pc]='env';poeLogo(pc,limpa(i));if(window.__dckvMarcaV){window.__dckvMarcaV();}}/* o logo enviado vale so para a pasta; squeeze e caderno seguem com os logos do cadastro */});};r.readAsDataURL(f);});
ed.querySelector('.dckv-go').addEventListener('click',pede);
marca();
if(rolar){sec.scrollIntoView({behavior:'smooth',block:'start'});}
Promise.all([img(K.foto),img(K.mask),img(K.logo.cor),img(K.logo.ce),img(EX[0].src),img(EX[1].src),img(K.logo.v),img(K.logo.ve)]).then(function(r){
if(!r[0]||!r[1]){ld.textContent='Não foi possível carregar a prévia. Recarregue a página.';return;}
FOTO=r[0];MASK=r[1];LOGO=r[2]?limpa(r[2]):null;LOGOB=r[3]?limpa(r[3]):null;LV=r[6]?limpa(r[6]):null;LVE=r[7]?limpa(r[7]):null;EX[0].im=r[4];EX[1].im=r[5];
prep();box.replaceChild(cv,ld);versoes(box);arrasta();pinta();});
}
/* logo movel: arrastar com o dedo ou o mouse; roda do mouse muda o tamanho */
function ponto(e){var r=cv.getBoundingClientRect();return {x:(e.clientX-r.left)*N/r.width,y:(e.clientY-r.top)*N/r.height};}
function noLogo(p){var b=LBOX;return b&&p.x>=b.x-20&&p.x<=b.x+b.w+20&&p.y>=b.y-20&&p.y<=b.y+b.h+20;}
function arrasta(){
cv.addEventListener('pointerdown',function(e){var p=ponto(e);if(!noLogo(p)){return;}ARR={x:p.x,y:p.y,dx:LG.dx,dy:LG.dy};cv.setPointerCapture(e.pointerId);cv.style.cursor='grabbing';e.preventDefault();});
cv.addEventListener('pointermove',function(e){var p=ponto(e);if(!ARR){cv.style.cursor=noLogo(p)?'grab':'default';return;}LG.dx=Math.max(-330,Math.min(330,ARR.dx+p.x-ARR.x));LG.dy=Math.max(-300,Math.min(220,ARR.dy+p.y-ARR.y));pinta();});
function solta(){ARR=null;cv.style.cursor='grab';}
cv.addEventListener('pointerup',solta);cv.addEventListener('pointercancel',solta);
cv.addEventListener('wheel',function(e){if(!noLogo(ponto(e))){return;}e.preventDefault();LG.k=Math.max(0.4,Math.min(2.2,LG.k*(e.deltaY<0?1.06:0.94)));var s=ed.querySelector('input.lgk');if(s){s.value=Math.round(LG.k*100);}pinta();},{passive:false});}
/* versoes do logo ja cadastradas pela escola: um clique escolhe a que vai na pasta */
var VERS=[['auto','Automático',''],['cor','Colorido',K.logo.cor],['ce','Colorido p/ fundo escuro',K.logo.ce],['branco','Branco',K.logo.branco],['v','Vertical colorido',K.logo.v],['ve','Vertical p/ fundo escuro',K.logo.ve],['vb','Vertical branco',K.logo.vb]];
function poeLogo(pc,o){if(pc==='pasta'){LOGOU=o;}else{OVR[pc]=o;}pinta();}
function versoes(box){var vs=VERS.filter(function(v){return v[0]==='auto'||v[2];});if(vs.length<3){return;}var d=document.createElement('div');d.className='dckv-lv';
d.innerHTML='<p>Logo de cada peça</p><div class="pcs"><button type="button" data-p="pasta" class="on">Pasta</button><button type="button" data-p="sq">Garrafa</button><button type="button" data-p="bl">Bloco</button></div><div class="vs"></div>';var w=d.querySelector('.vs');
function marcaV(){[].forEach.call(w.children,function(x){x.classList.toggle('on',x.getAttribute('data-v')===SEL[PEC]);});}
[].forEach.call(d.querySelectorAll('.pcs button'),function(t){t.addEventListener('click',function(){PEC=t.getAttribute('data-p');[].forEach.call(d.querySelectorAll('.pcs button'),function(x){x.classList.toggle('on',x===t);});marcaV();});});
vs.forEach(function(v){var b=document.createElement('button');b.type='button';b.setAttribute('data-v',v[0]);b.innerHTML='<i><b'+(v[2]?' style="background-image:url('+encodeURI(v[2])+')"':'')+'></b></i>'+esc(v[1]);if(!v[2]){b.querySelector('i').style.background='#0B2A5B';b.querySelector('b').textContent='Auto';b.querySelector('b').style.cssText='color:#fff;font-size:13px;display:flex;align-items:center;justify-content:center';}
b.addEventListener('click',function(){var pc=PEC;SEL[pc]=v[0];marcaV();if(pc==='pasta'){LGF=null;}if(!v[2]){poeLogo(pc,null);return;}img(v[2]).then(function(i){if(i){poeLogo(pc,limpa(i));}});});w.appendChild(b);});
marcaV();box.appendChild(d);window.__dckvMarcaV=marcaV;}
function marca(){
[].forEach.call(ed.querySelectorAll('.dckv-sw button'),function(b){b.classList.toggle('on',!BG&&b.getAttribute('data-c')===st.cor);});var hx2=ed.querySelector('input.hx'),pk2=ed.querySelector('.dckv-hx input[type=color]');if(hx2&&document.activeElement!==hx2){hx2.value=st.cor;}if(pk2){pk2.value=st.cor.toLowerCase();}
var m=null;[].forEach.call(ed.querySelectorAll('.dckv-qt button'),function(b){if(st.q>=+b.getAttribute('data-q')){m=b;}});
[].forEach.call(ed.querySelectorAll('.dckv-qt button'),function(b){b.classList.toggle('on',b===m);});
var u=un(st.q);ed.querySelector('.dckv-tot').innerHTML=st.q+' kits &times; '+fmt(u)+' = <b>'+fmt(st.q*u)+'</b>';
ed.querySelector('.dckv-go').textContent=K.log?'Pedir meus '+st.q+' kits':'Entrar para pedir os kits';}

/* ---------- logo enviado: tira o fundo branco a partir das bordas */
function limpa(im){var s=Math.min(1,1400/Math.max(im.naturalWidth,im.naturalHeight)),w=Math.max(1,Math.round(im.naturalWidth*s)),h=Math.max(1,Math.round(im.naturalHeight*s)),c=document.createElement('canvas');c.width=w;c.height=h;var g=c.getContext('2d',{willReadFrequently:true});g.drawImage(im,0,0,w,h);
var d;try{d=g.getImageData(0,0,w,h);}catch(e){return {cv:c,x:0,y:0,w:w,h:h};}
var p=d.data,vis=new Uint8Array(w*h),pil=[],x,y,q,i,k,tr=0,tn=0;for(x=0;x<w;x++){tn+=2;if(p[x*4+3]<20){tr++;}if(p[((h-1)*w+x)*4+3]<20){tr++;}}for(y=0;y<h;y++){tn+=2;if(p[y*w*4+3]<20){tr++;}if(p[(y*w+w-1)*4+3]<20){tr++;}}var temT=tr>tn*0.5;/* PNG transparente: so o transparente some (o branco das letras fica) */
function ok(k){var j=k*4;return p[j+3]<20||(!temT&&p[j]>228&&p[j+1]>228&&p[j+2]>228);}
for(x=0;x<w;x++){pil.push(x,(h-1)*w+x);}for(y=0;y<h;y++){pil.push(y*w,y*w+w-1);}
while(pil.length){q=pil.pop();if(vis[q]||!ok(q)){continue;}vis[q]=1;x=q%w;y=(q/w)|0;if(x>0){pil.push(q-1);}if(x<w-1){pil.push(q+1);}if(y>0){pil.push(q-w);}if(y<h-1){pil.push(q+w);}}
var x0=w,y0=h,x1=-1,y1=-1;for(i=0;i<w*h;i++){if(vis[i]){p[i*4+3]=0;}else if(p[i*4+3]>20){x=i%w;y=(i/w)|0;if(x<x0){x0=x;}if(x>x1){x1=x;}if(y<y0){y0=y;}if(y>y1){y1=y;}}}
if(x1<0){return null;}g.putImageData(d,0,0);var sl=0,sn=0;for(i=0;i<w*h;i+=3){k=i*4;if(p[k+3]>128){sl+=0.299*p[k]+0.587*p[k+1]+0.114*p[k+2];sn++;}}return {cv:c,x:x0,y:y0,w:x1-x0+1,h:y1-y0+1,lum:sn?sl/sn/255:0.5};}

/* ---------- foto: a pasta vira "tecido branco" (dobras e trama da propria foto) e a arte e multiplicada por cima */
var FOTO,MASK,LOGO,LOGOB,LOGOU=null,OVR={sq:null,bl:null},PEC='pasta',SEL={pasta:'auto',sq:'auto',bl:'auto'},LV,LVE,FD,LUM,CA,ctx,OUT;
function prep(){var t=document.createElement('canvas');t.width=N;t.height=N;var c=t.getContext('2d',{willReadFrequently:true});c.imageSmoothingQuality='high';
c.drawImage(FOTO,0,0,N,N);FD=c.getImageData(0,0,N,N).data;c.clearRect(0,0,N,N);c.drawImage(MASK,MB.x,MB.y,MB.w,MB.h);var MA=c.getImageData(0,0,N,N).data;
var NN=N*N,R=new Uint8Array(NN),hh=new Uint32Array(256),cnt=0,i,j,k,s=0,p95=235,x,y,dx,dy,sm;
for(i=0;i<NN;i++){if(MA[i*4+3]>30){R[i]=1;j=i*4;hh[Math.round(0.299*FD[j]+0.587*FD[j+1]+0.114*FD[j+2])]++;cnt++;}}
/* a mascara para antes da beirada: a arte avanca sobre o tecido branco vizinho (costura e laterais) ate a borda real, sem pegar ziper, alcas nem o fundo */
var tecido=function(q){var j=q*4,mx=Math.max(FD[j],FD[j+1],FD[j+2]),mn=Math.min(FD[j],FD[j+1],FD[j+2]);return mn>120&&mx-mn<38;},fr=[],nf,q,v,x2,y2,pas;for(i=0;i<NN;i++){if(R[i]){fr.push(i);}}
for(pas=0;pas<16;pas++){nf=[];for(v=0;v<fr.length;v++){q=fr[v];x2=q%N;y2=(q/N)|0;[[1,0],[-1,0],[0,1],[0,-1]].forEach(function(d){var xx=x2+d[0],yy=y2+d[1],n2;if(xx<0||yy<0||xx>=N||yy>=N){return;}n2=yy*N+xx;if(!R[n2]&&tecido(n2)){R[n2]=1;nf.push(n2);j=n2*4;hh[Math.round(0.299*FD[j]+0.587*FD[j+1]+0.114*FD[j+2])]++;cnt++;}});}fr=nf;if(!fr.length){break;}}
for(k=0;k<256;k++){s+=hh[k];if(s>=cnt*0.93){p95=Math.max(120,k);break;}}
LUM=new Float32Array(NN);for(i=0;i<NN;i++){if(R[i]){j=i*4;LUM[i]=Math.min(1,(0.299*FD[j]+0.587*FD[j+1]+0.114*FD[j+2])/p95);}}
CA=new Float32Array(NN);for(y=1;y<N-1;y++){for(x=1;x<N-1;x++){sm=0;for(dy=-1;dy<=1;dy++){for(dx=-1;dx<=1;dx++){sm+=R[(y+dy)*N+x+dx];}}CA[y*N+x]=sm/9;}}
ctx=cv.getContext('2d');OUT=ctx.createImageData(N,N);}
/* pasta: logo colorido; em cor escura, o colorido para fundo escuro (ou o branco) cadastrado pela escola */
function logoPara(cor){return (lumHex(cor)<0.5&&LOGOB)?LOGOB:LOGO;}
function arte(sc){var a=document.createElement('canvas');a.width=Math.round(N*sc);a.height=Math.round(N*sc);var g=a.getContext('2d');g.scale(sc,sc);g.imageSmoothingQuality='high';
var gr=g.createLinearGradient(0,MB.y,0,MB.y+MB.h);gr.addColorStop(0,st.cor);gr.addColorStop(1,st.cor==='#FFFFFF'?'#FFFFFF':escurece(st.cor,0.86));g.fillStyle=gr;g.fillRect(0,0,N,N);
if(BG){var bw=BG.naturalWidth,bh=BG.naturalHeight,k2=Math.max(MB.w/bw,MB.h/bh);g.drawImage(BG,MB.x+(MB.w-bw*k2)/2,MB.y+(MB.h-bh*k2)/2,bw*k2,bh*k2);}
var escuroBg=BG?(BG.lum<0.5):lumHex(st.cor)<0.5,L=LOGOU||((escuroBg&&LOGOB)?LOGOB:LOGO),cx=491,wa=fone(st.wa||''),ig=arroba(st.ig||''),tem=!!(wa||ig),mw=500,mh=tem?200:240,cy=tem?690:735;
if(L){var r=Math.min(mw/L.w,mh/L.h)*LG.k,w=L.w*r,h=L.h*r,lx=cx+LG.dx-w/2,ly=cy+LG.dy-h/2,mx0=MB.x+18,mx1=MB.x+MB.w-18-w,my0=575,my1=(tem?836:MB.y+MB.h-22)-h;/* nao cobre a linha do WhatsApp e do Instagram *//* dentro da pasta, abaixo do ziper */if(mx1>mx0){lx=Math.max(mx0,Math.min(mx1,lx));}else{lx=MB.x+(MB.w-w)/2;}if(my1>my0){ly=Math.max(my0,Math.min(my1,ly));}if(sc===1&&!ARR){LG.dx=lx+w/2-cx;LG.dy=ly+h/2-cy;}g.drawImage(L.cv,L.x,L.y,L.w,L.h,lx,ly,w,h);if(sc===1){LBOX={x:lx,y:ly,w:w,h:h};}}
if(tem){contatos(g,cx,866,escuroBg?'#FFFFFF':'#0B2A5B',wa,ig);}
return a;}
/* WhatsApp e Instagram com icones, centralizados na parte de baixo da pasta */
function icWa(g,x,y,s,c){g.save();g.translate(x,y);var u=s/100;g.strokeStyle=c;g.fillStyle=c;/* balao: circulo com o rabinho embaixo a esquerda */g.lineWidth=8*u;g.lineJoin='round';g.beginPath();g.arc(0,0,40*u,Math.PI*0.80,Math.PI*0.66+Math.PI*2,false);g.lineTo(-43*u,44*u);g.closePath();g.stroke();/* telefone */g.beginPath();g.moveTo(-17*u,-23*u);g.bezierCurveTo(-23*u,-22*u,-27*u,-15*u,-25*u,-7*u);g.bezierCurveTo(-21*u,9*u,-9*u,21*u,7*u,25*u);g.bezierCurveTo(15*u,27*u,22*u,23*u,23*u,17*u);g.lineTo(24*u,12*u);g.bezierCurveTo(24*u,10*u,23*u,8*u,21*u,7*u);g.lineTo(12*u,3*u);g.bezierCurveTo(10*u,2*u,8*u,3*u,7*u,4*u);g.lineTo(3*u,9*u);g.bezierCurveTo(-5*u,5*u,-11*u,-1*u,-15*u,-9*u);g.lineTo(-10*u,-13*u);g.bezierCurveTo(-9*u,-14*u,-8*u,-16*u,-9*u,-18*u);g.lineTo(-13*u,-27*u);g.bezierCurveTo(-14*u,-28*u,-15*u,-29*u,-17*u,-23*u);g.closePath();g.fill();g.restore();}
function icIg(g,x,y,s,c){g.save();g.strokeStyle=c;g.fillStyle=c;g.lineWidth=s*0.1;var h=s*0.42,r=s*0.14;g.beginPath();g.moveTo(x-h+r,y-h);g.arcTo(x+h,y-h,x+h,y+h,r);g.arcTo(x+h,y+h,x-h,y+h,r);g.arcTo(x-h,y+h,x-h,y-h,r);g.arcTo(x-h,y-h,x+h,y-h,r);g.closePath();g.stroke();g.beginPath();g.arc(x,y,s*0.19,0,6.2832);g.stroke();g.beginPath();g.arc(x+s*0.24,y-s*0.24,s*0.05,0,6.2832);g.fill();g.restore();}
function contatos(g,cx,y,cor,wa,ig){var fs=34,ic=40,gap=12,sep=46,it=[];g.font='700 '+fs+'px Poppins,Arial,sans-serif';if(wa){it.push({t:wa,f:icWa});}if(ig){it.push({t:ig,f:icIg});}
var ws=it.map(function(o){return ic+gap+g.measureText(o.t).width;}),tot=ws.reduce(function(a,b){return a+b;},0)+sep*(it.length-1);if(tot>640){fs=Math.max(22,Math.floor(fs*640/tot));ic=Math.round(ic*fs/34);g.font='700 '+fs+'px Poppins,Arial,sans-serif';ws=it.map(function(o){return ic+gap+g.measureText(o.t).width;});tot=ws.reduce(function(a,b){return a+b;},0)+sep*(it.length-1);}
var x=cx-tot/2;g.fillStyle=cor;g.textAlign='left';g.textBaseline='middle';it.forEach(function(o,i){o.f(g,x+ic/2,y,ic,cor);g.fillText(o.t,x+ic+gap,y+1);x+=ws[i]+sep;});g.textBaseline='alphabetic';}
function quebra(g,t,mw){var ws=t.split(/\s+/),ls=[],c='';ws.forEach(function(w){var p=c?c+' '+w:w;if(g.measureText(p).width>mw&&c){ls.push(c);c=w;}else{c=p;}});if(c){ls.push(c);}return ls;}
var raf=0;function pinta(){if(!OUT||raf){return;}raf=requestAnimationFrame(function(){raf=0;desenha();});}
function desenha(){var AD=arte(1).getContext('2d').getImageData(0,0,N,N).data,o=OUT.data,i,j,a,l;
for(i=0;i<N*N;i++){j=i*4;a=CA[i]*0.84;/* igual ao Corel: Subtrair com transparencia 16 */if(a<0.004){o[j]=FD[j];o[j+1]=FD[j+1];o[j+2]=FD[j+2];o[j+3]=255;continue;}l=LUM[i]*255-255;/* mesclagem Subtrair do Corel: arte + tecido - 255 */
o[j]=Math.max(0,Math.min(255,AD[j]+l))*a+FD[j]*(1-a);o[j+1]=Math.max(0,Math.min(255,AD[j+1]+l))*a+FD[j+1]*(1-a);o[j+2]=Math.max(0,Math.min(255,AD[j+2]+l))*a+FD[j+2]*(1-a);o[j+3]=255;}
ctx.putImageData(OUT,0,0);
if(LOGO||LOGOU){EX.forEach(function(E){if(!E.im){return;}var Lx=OVR[E.k]||(E.modo==='esc'?(LVE||LV||LOGO||LOGOU):(LV||LOGO||LOGOU)),tom=null,sil=false;/* squeeze branco: logo claro demais sai escuro; capa azul do caderno: logo escuro demais sai claro */if(!OVR[E.k]&&E.modo!=='esc'&&Lx.lum>0.72){tom='#0B2A5B';}else if(!OVR[E.k]&&E.modo==='esc'&&Lx.lum<0.22){tom='#F2F4F8';}sil=!!tom;var w=E.w,h=E.h,oc=document.createElement('canvas');oc.width=w;oc.height=h;var g=oc.getContext('2d');g.imageSmoothingQuality='high';var r=Math.min(w*E.fit/Lx.w,h*E.fit/Lx.h),lw=Lx.w*r,lh=Lx.h*r;g.drawImage(Lx.cv,Lx.x,Lx.y,Lx.w,Lx.h,(w-lw)/2,(h-lh)/2,lw,lh);
if(sil){g.globalCompositeOperation='source-in';g.fillStyle=tom;g.fillRect(0,0,w,h);}g.globalCompositeOperation='destination-in';g.drawImage(E.im,0,0,w,h);
ctx.save();if(E.modo==='esc'){ctx.globalAlpha=0.95;}else{ctx.globalCompositeOperation='multiply';}ctx.drawImage(oc,E.x,E.y);ctx.restore();});}}

/* ---------- pedido */
function blob(c,q){return new Promise(function(ok){c.toBlob(ok,'image/jpeg',q);});}
/* envio com progresso no botao */
function envia(fd,b){return new Promise(function(ok,no){var x=new XMLHttpRequest();x.open('POST',K.url);x.withCredentials=true;x.upload.onprogress=function(e){if(e.lengthComputable){b.textContent='Enviando o pedido... '+Math.min(99,Math.round(e.loaded/e.total*100))+'%';}};x.upload.onload=function(){b.textContent='Colocando no carrinho...';};x.onload=function(){try{ok(JSON.parse(x.responseText));}catch(e){no(e);}};x.onerror=no;x.send(fd);});}
function pede(){var b=ed.querySelector('.dckv-go');guarda();
if(!K.log){location.href=K.entrar;return;}
if(!OUT){alert('A prévia ainda está carregando. Tente de novo em instantes.');return;}
var ow=Math.round(PR.larg/2.54*PR.dpi),oh=Math.round(PR.alt/2.54*PR.dpi),RW=MB.w,RH=MB.w*PR.alt/PR.larg,RX=MB.x,RY=MB.y+(MB.h-RH)/2+20,sc=ow/RW,a=arte(sc),pr=document.createElement('canvas');pr.width=ow;pr.height=oh;pr.getContext('2d').drawImage(a,RX*sc,RY*sc,RW*sc,RH*sc,0,0,ow,oh);
b.disabled=true;b.textContent='Enviando o pedido...';
Promise.all([blob(cv,0.88),blob(pr,0.95)]).then(function(bl){if(!bl[0]||!bl[1]){throw new Error('png');}
var fd=new FormData();fd.append('action','dckit_pedir');fd.append('n',K.nonce);fd.append('qtd',st.q);fd.append('curso',[fone(st.wa||''),arroba(st.ig||'')].filter(Boolean).join(' · ')||'Kit Matrícula');fd.append('cor',BG?'imagem':st.cor);fd.append('previa',bl[0],'previa.jpg');if(BG&&BGF){fd.append('fundo',BGF,BGF.name);}if(LGF){fd.append('logo_env',LGF,LGF.name);}fd.append('arte',bl[1],'arte.jpg');
return envia(fd,b);}).then(function(j){
if(j&&j.success&&j.data&&j.data.url){location.href=j.data.url;return;}alert((j&&j.data&&j.data.msg)||'Não foi possível fazer o pedido. Tente de novo.');b.disabled=false;marca();
}).catch(function(){alert('Não foi possível enviar o pedido. Confira a internet e tente de novo.');b.disabled=false;marca();});}
/* abre direto com as ferramentas (sem precisar clicar); com #montar-kit no endereco, rola ate o kit */
abre(/[#&?]montar-kit/.test(location.href));
})();
