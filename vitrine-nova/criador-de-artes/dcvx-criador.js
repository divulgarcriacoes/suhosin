(function(){
var D=window.D;var cv=document.getElementById('dcvx-cv');if(!cv){return;}
var N=992,ALPHA=0.96,X0=297,X1=635,Y0=392,Y1=802,RMAX=636,YTOP=410,RR=620;cv.width=N;cv.height=N;
var PU=D.pasta||{foto:'/wp-content/uploads/2026/11/pasta-escolar-3.jpg',mask:'/wp-content/uploads/2026/11/MARCARA-PASTA-3.png',thumb:'/wp-content/uploads/2026/11/pasta-escolar.jpg'};
/* Produtos: foto, mascara (onde a arte aparece), area em pixels do canvas, layout da arte e tamanho de impressao (medidas provisorias a confirmar com a grafica) */
var PRODS={
caneca:{tipo:'caneca',art:'caneca',hlr:[2,28],cyl:1.2,hc:[18,10],foto:D.foto,mask:D.mascara,X0:297,X1:635,Y0:392,Y1:802,RMAX:636,YTOP:410,RR:620,lay:{cx:460,cy:602,s:1,wrap:300,maxh:372,logow:250},tam:{nome:'Caneca 325 ml',larg:21,alt:9.5,dpi:300,provisoria:true}},
pasta:{tipo:'pasta',art:'pasta',branca:true,refw:1600,op:0.75,fop:0.94,foto:PU.foto,mask:PU.mask,N:1200,crop:{x:60,y:100,w:1400,h:1400},mb:{x:69,y:293,w:1096,h:746},X0:69,X1:1165,Y0:293,Y1:1039,RMAX:1165,YTOP:293,RR:1165,lay:{cx:617,cy:780,s:1.5,wrap:640,maxh:330,logow:460},gain:1.1,tam:{nome:'Pasta escolar em nylon',larg:46,alt:31,dpi:120,provisoria:true}},
bolsa:{tipo:'bolsa',art:'bolsa',branca:true,refw:1600,op:0.75,fop:0.94,bri:1.05,con:0.9,sat:1.15,foto:'/wp-content/uploads/2026/11/bolsa-escolar.jpg',mask:'/wp-content/uploads/2026/11/MARCARA-bolsa-escolar.png',N:1200,crop:{x:0,y:0,w:1600,h:1600},mb:{x:186,y:322,w:893,h:733},X0:186,X1:1079,Y0:322,Y1:1055,RMAX:1079,YTOP:322,RR:1079,lay:{cx:710,cy:688,s:1.26,wrap:502,maxh:276,logow:368},lado:{x0:362,x1:349,y0:374,y1:1052,f:0.8,g:0.25,s:0.93,w:42},grow:4,growmin:140,gain:1.1,tam:{nome:'Bolsa escolar',larg:40,alt:33,dpi:120,provisoria:true}},
caneca2:{tipo:'caneca2',art:'caneca 2',branca:true,refw:1000,mth:8,nojoin:1,op:0.75,fop:0.94,tint:{cx:466,cy:502,rx:200,ry:40},cores:[{n:'Verde',h:'#8BC53F'},{n:'Azul',h:'#1A9BD7',t:'#007CAF'},{n:'Laranja',h:'#F26A0F',t:'#C94E04'},{n:'Preto',h:'#1A1A1A',t:'#0B0B0B'},{n:'Rosa',h:'#D4849A',t:'#C77387'},{n:'Roxo',h:'#9A8FC0',t:'#857AA0'},{n:'Vermelho',h:'#E3000F',t:'#CF0008'}],foto:'/wp-content/uploads/2026/11/caneca-2-2.jpg',mask:'/wp-content/uploads/2026/11/caneca-2-1.png',N:1200,crop:{x:0,y:0,w:1000,h:1000},mb:{x:301,y:601,w:464,h:346},X0:301,X1:765,Y0:601,Y1:947,RMAX:765,YTOP:601,RR:765,lay:{cx:533,cy:774,s:0.85,wrap:430,maxh:230,logow:290},gain:1.1,tam:{nome:'Caneca 2 (empilhável)',larg:19.8,alt:12.9,dpi:300,provisoria:true}},
kit:{tipo:'kit',art:'kit',btn:'Pedir estes kits',branca:true,mth:30,refw:1600,op:0.75,fop:0.94,foto:'/wp-content/uploads/2026/11/KIT-MATRICULA.jpg',mask:'/wp-content/uploads/2026/11/KIT-MATRICULA-MASCARA-BOLSA.png',N:1200,crop:{x:0,y:0,w:1600,h:1600},mb:{x:77,y:395,w:828,h:545},X0:77,X1:905,Y0:395,Y1:940,RMAX:905,YTOP:395,RR:905,lay:{cx:491,cy:722,s:1.15,wrap:470,maxh:300,logow:330},gain:1.1,extras:[{nome:'squeeze',x:942,y:673,w:115,h:155,mask:'/wp-content/uploads/2026/11/MASCARA-SQUEEZE.png',modo:'cor',fit:0.8},{nome:'bloco',x:755,y:970,w:145,h:88,mask:'/wp-content/uploads/2026/11/MASCARA-BLOCO.png',modo:'grav',fit:0.72}],tam:{nome:'Kit Matrícula (arte da pasta)',larg:45,alt:25,dpi:120,provisoria:true}}};
var PRD=PRODS.caneca,UN=1;
var ctx=cv.getContext('2d',{willReadFrequently:true});
var foto=new Image(),mk=new Image(),ready=0,FD=null,CA=null,LUM=null,REF=0.8,OUT=null,erro=false,covs=[],logoC=null,logoOrig=null;
var items=[],selIt=null,LG={dx:0,dy:0,k:1,r:0},logoBox=null,TEX=null,TI=0.7,BGON=false,SH=null,BG={im:null,k:1,dx:0,dy:0},BGMOVE=false,LCOR=null,cursoBox=null,CG={dx:0,dy:0,k:1,r:0,off:false},drag=null,raf=0,showSel=true,COR=null,EST=null,AIMG=null,AHIDE=true;
var SEM={n:' ',cor:'#FFFFFF',esc:'#EEF0F4'},sel=-1,m=new RegExp('[?&]curso=([0-9]+)').exec(location.search);if(m&&D.cursos[+m[1]]){sel=+m[1];}
function box(src,w,h,k,mx){
var tmp=new Uint8Array(w*h),out=new Uint8Array(w*h),r=(k-1)>>1,x,y,i,v,a;
for(y=0;y<h;y++){for(x=0;x<w;x++){v=mx?0:1;for(i=-r;i<=r;i++){a=x+i;a=a<0?0:(a>=w?w-1:a);if(mx){if(src[y*w+a]){v=1;break;}}else{if(!src[y*w+a]){v=0;break;}}}tmp[y*w+x]=v;}}
for(y=0;y<h;y++){for(x=0;x<w;x++){v=mx?0:1;for(i=-r;i<=r;i++){a=y+i;a=a<0?0:(a>=h?h-1:a);if(mx){if(tmp[a*w+x]){v=1;break;}}else{if(!tmp[a*w+x]){v=0;break;}}}out[y*w+x]=v;}}
return out;}
function bandURL(Q){var B=Q.band,w=B.x1-B.x0+1,x,tp=[],ymn=1e9,ymx=-1e9,y;for(x=B.x0;x<=B.x1;x++){y=B.co[0]*x*x+B.co[1]*x+B.co[2]+B.off;tp.push(y);if(y<ymn){ymn=y;}if(y>ymx){ymx=y;}}var h=Math.ceil(ymx+B.hb-ymn),cn=document.createElement('canvas');cn.width=w;cn.height=h;var g=cn.getContext('2d');g.fillStyle='#fff';g.beginPath();for(x=0;x<w;x++){if(x===0){g.moveTo(x,tp[x]-ymn);}else{g.lineTo(x,tp[x]-ymn);}}for(x=w-1;x>=0;x--){g.lineTo(x,tp[x]-ymn+B.hb);}g.closePath();g.fill();return cn.toDataURL('image/png');}
function recolorInt(c,N,CR,T,hex){var sx=N/CR.w,sy=N/CR.h,x0=Math.max(0,Math.floor((T.cx-T.rx-CR.x)*sx)),y0=Math.max(0,Math.floor((T.cy-T.ry-CR.y)*sy)),w=Math.min(N-x0,Math.ceil(2*T.rx*sx)),h=Math.min(N-y0,Math.ceil(2*T.ry*sy)),im=c.getImageData(x0,y0,w,h),d=im.data,i,n=0,sl=0,k,px,py,dx,dy,ok=new Uint8Array(w*h),tr=parseInt(hex.slice(1,3),16),tg=parseInt(hex.slice(3,5),16),tb=parseInt(hex.slice(5,7),16),L,av;
for(i=0;i<w*h;i++){k=i*4;px=CR.x+(x0+i%w)/sx;py=CR.y+(y0+((i/w)|0))/sy;dx=(px-T.cx)/T.rx;dy=(py-T.cy)/T.ry;if(dx*dx+dy*dy<=1&&d[k+1]>d[k]-6&&d[k+1]>d[k+2]+22){ok[i]=1;sl+=0.299*d[k]+0.587*d[k+1]+0.114*d[k+2];n++;}}
if(!n){return;}av=sl/n;for(i=0;i<w*h;i++){if(ok[i]){k=i*4;L=(0.299*d[k]+0.587*d[k+1]+0.114*d[k+2])/av;d[k]=Math.min(255,tr*L);d[k+1]=Math.min(255,tg*L);d[k+2]=Math.min(255,tb*L);}}c.putImageData(im,x0,y0);}
function setProd(k){var Q=PRODS[k];if(!Q||Q===PRD){return;}PRD=Q;N=Q.N||992;UN=N/992;cv.width=N;cv.height=N;X0=Q.X0;X1=Q.X1;Y0=Q.Y0;Y1=Q.Y1;RMAX=Q.RMAX;YTOP=Q.YTOP;RR=Q.RR;
items=[];selIt=null;LG={dx:0,dy:0,k:1,r:0};CG={dx:0,dy:0,k:1,r:0,off:false};if(window.__voltaCurso){window.__voltaCurso();}AIMG=null;BGON=false;TEX=null;[].forEach.call(document.querySelectorAll('.dcvx-tx button'),function(x){x.classList.remove('on');});FD=null;CA=null;ready=0;erro=false;window.__erro=null;
(window.__prodHooks||[]).forEach(function(f){f(Q);});
(Q.extras||[]).forEach(function(E){if(!E.im){E.im=new Image();E.im.onload=function(){pedir();};E.im.src=E.mask;}});
foto.src=Q.foto;mk.src=Q.band?bandURL(Q):Q.mask;}
/* pasta: o tecido branco da mascara vira a base em branco da foto (a foto traz uma arte de exemplo) */
/* pasta BRANCA: o tecido vem da PROPRIA FOTO (costura, dobras, trama); a mascara so marca onde a arte entra (sem ziper e sem puxador) */
function prepBranca(){
try{
var Q=PRD,CR=Q.crop,MB=Q.mb,t=document.createElement('canvas');t.width=N;t.height=N;var c=t.getContext('2d',{willReadFrequently:true});
var fw=Q.refw?(foto.naturalWidth||Q.refw)/Q.refw:1;/* a foto pode chegar menor (celular, CDN): as medidas do recorte valem para a foto de referencia */
c.imageSmoothingQuality='high';c.fillStyle='#fff';c.fillRect(0,0,N,N);c.drawImage(foto,CR.x*fw,CR.y*fw,CR.w*fw,CR.h*fw,0,0,N,N);
if(Q.cores&&Q.corI>0){var crr=Q.cores[Q.corI],stt=crr&&crr._st;if(stt){var ovx=N/CR.w,ovy=N/CR.h,OV=Q.ov;c.drawImage(stt.img,stt.bx,stt.by,stt.bw,stt.bh,(OV.x-CR.x)*ovx,(OV.y-CR.y)*ovy,OV.w*ovx,OV.h*ovy);}}
if(Q.cores&&Q.corI>0&&Q.tint){var cc2=Q.cores[Q.corI];if(cc2&&cc2.t){recolorInt(c,N,CR,Q.tint,cc2.t);}}
FD=c.getImageData(0,0,N,N).data;
c.clearRect(0,0,N,N);c.drawImage(mk,MB.x,MB.y,MB.w,MB.h);var MA=c.getImageData(0,0,N,N).data;
var NN=N*N,i,j,x,y,R=new Uint8Array(NN),h=new Uint32Array(256),cnt=0,k,s3=0,p95=235,L;
for(i=0;i<NN;i++){if(MA[i*4+3]>(Q.mth||110)){R[i]=1;}}
if(Q.nosat){for(i=0;i<NN;i++){if(R[i]){j=i*4;if(Math.max(FD[j],FD[j+1],FD[j+2])-Math.min(FD[j],FD[j+1],FD[j+2])>Q.nosat){R[i]=0;}}}}
/* a mascara para um pouco antes da borda real: junta o tecido branco vizinho (a lateral/costura da borda), sem pegar o ziper, o puxador nem o fundo */
var SI0=new Uint8Array(NN),ya,yb,Rd=box(R,N,N,2*Math.round(UN*7)+1,true),mxv,mnv;
for(x=0;x<N;x++){ya=-1;yb=-1;for(y=0;y<N;y++){if(R[y*N+x]){if(ya<0){ya=y;}yb=y;}}if(ya>=0){for(y=ya;y<=yb;y++){SI0[y*N+x]=1;}}}
if(!Q.nojoin){for(i=0;i<NN;i++){if(Rd[i]&&!SI0[i]&&!R[i]){j=i*4;mxv=Math.max(FD[j],FD[j+1],FD[j+2]);mnv=Math.min(FD[j],FD[j+1],FD[j+2]);if(mnv>130&&mxv-mnv<55){R[i]=2;}}}}
if(Q.growx){var Rx=new Uint8Array(NN),gx;for(y=0;y<N;y++){for(x=0;x<N;x++){if(R[y*N+x]){for(gx=-Q.growx;gx<=Q.growx;gx++){if(x+gx>=0&&x+gx<N&&x+gx<MB.x&&!R[y*N+x+gx]){j=(y*N+x+gx)*4;if(Math.min(FD[j],FD[j+1],FD[j+2])>Q.growmin){Rx[y*N+x+gx]=1;}}}}}}for(i=0;i<NN;i++){if(Rx[i]){R[i]=1;}}}
if(Q.grow){var Rg=box(R,N,N,2*Q.grow+1,true);for(i=0;i<NN;i++){if(Rg[i]&&!R[i]){j=i*4;if(Math.min(FD[j],FD[j+1],FD[j+2])>Q.growmin){R[i]=1;}}}}
for(i=0;i<NN;i++){if(R[i]){R[i]=1;}}
for(i=0;i<NN;i++){if(R[i]){j=i*4;L=Math.round(0.299*FD[j]+0.587*FD[j+1]+0.114*FD[j+2]);h[L]++;cnt++;}}
for(k=0;k<256;k++){s3+=h[k];if(s3>=cnt*0.93){p95=Math.max(120,k);break;}}
for(i=0;i<NN;i++){if(R[i]){j=i*4;L=0.299*FD[j]+0.587*FD[j+1]+0.114*FD[j+2];L=Math.min(255,L*250/p95);FD[j]=L;FD[j+1]=L;FD[j+2]=L;}}
LUM=new Float32Array(NN);for(i=0;i<NN;i++){j=i*4;LUM[i]=(0.299*FD[j]+0.587*FD[j+1]+0.114*FD[j+2])/255;}
SH=null;if(Q.lado){SH=new Float32Array(NN).fill(1);var LD=Q.lado,bx2,t2,t3,fl,fs,dd;for(y=0;y<N;y++){t2=Math.max(0,Math.min(1,(y-LD.y0)/(LD.y1-LD.y0)));bx2=LD.x0+(LD.x1-LD.x0)*t2;for(x=0;x<N;x++){i=y*N+x;if(!R[i]){continue;}dd=x-bx2;fs=LD.f*(1-LD.g*Math.min(1,Math.max(0,-dd-6)/Math.max(1,bx2-X0)));if(dd<-6){fl=fs;}else if(dd<12){t3=(dd+6)/18;fl=fs+(1-fs)*t3*t3*(3-2*t3);}else if(dd<LD.w){fl=1-(1-LD.s)*(1-(dd-12)/(LD.w-12));}else{fl=1;}fl=1-(1-fl)*Math.max(0,Math.min(1,(y-(LD.y0-8))/16));SH[i]=fl;}}}
CA=new Float32Array(NN);var sm,dy,dx;
for(y=1;y<N-1;y++){for(x=1;x<N-1;x++){sm=0;for(dy=-1;dy<=1;dy++){for(dx=-1;dx<=1;dx++){sm+=R[(y+dy)*N+x+dx];}}CA[y*N+x]=sm/9;}}
REF=0.985;OUT=ctx.createImageData(N,N);
}catch(e){erro=true;window.__erro=String(e);}
draw();
}
function prepPasta(){
try{
var Q=PRD,CR=Q.crop,K=N/CR.w,MB=Q.mb,t=document.createElement('canvas');t.width=N;t.height=N;var c=t.getContext('2d',{willReadFrequently:true});
c.imageSmoothingQuality='high';c.fillStyle='#fff';c.fillRect(0,0,N,N);c.drawImage(foto,CR.x,CR.y,CR.w,CR.h,0,0,N,N);FD=c.getImageData(0,0,N,N).data;
c.clearRect(0,0,N,N);c.fillStyle='#000';c.strokeStyle='#000';c.lineWidth=Math.round(5*UN);c.lineJoin='round';c.beginPath();
Q.polys.forEach(function(pl){pl.forEach(function(q,n){var x=(q[0]-CR.x)*K,y=(q[1]-CR.y)*K;if(n){c.lineTo(x,y);}else{c.moveTo(x,y);}});c.closePath();});
c.fill('evenodd');c.stroke();var RA=c.getImageData(0,0,N,N).data;
c.clearRect(0,0,N,N);c.drawImage(mk,MB.x,MB.y,MB.w,MB.h);var MA=c.getImageData(0,0,N,N).data;
var NN=N*N,i,j,x,y,R=new Uint8Array(NN),g,cnt=0,h=new Uint32Array(256),med=215,s2=0,k;
for(i=0;i<NN;i++){j=i*4;if(RA[j+3]>127){R[i]=1;if(MA[j+3]>60){g=Math.round((MA[j]+MA[j+1]+MA[j+2])/3);h[g]++;cnt++;}}}
var p5=0,p95=255,s3=0;for(k=0;k<256;k++){s3+=h[k];if(!p5&&s3>=cnt*0.05){p5=k;}if(s3>=cnt*0.95){p95=k;break;}}if(p95-p5<8){p5=Math.max(0,p5-4);p95=p5+8;}
var SI=new Uint8Array(NN),y0c,y1c;
for(x=0;x<N;x++){y0c=-1;y1c=-1;for(y=0;y<N;y++){if(R[y*N+x]){if(y0c<0){y0c=y;}y1c=y;}}if(y0c>=0){for(y=y0c;y<=y1c;y++){SI[y*N+x]=1;}}}
var TP=Math.round(260*UN),TXo=Math.round(MB.x+MB.w*0.35),TYo=Math.round(MB.y+MB.h*0.55);
function TX(px,py){var a=px%(2*TP),b2=py%(2*TP),q;if(a>=TP){a=2*TP-1-a;}if(b2>=TP){b2=2*TP-1-b2;}q=((TYo+b2)*N+TXo+a)*4;return (MA[q]+MA[q+1]+MA[q+2])/3;}
var BL=new Float32Array(NN),TB=new Float32Array(NN),rd=Math.round(22*UN),acc,u,v2;
for(y=0;y<N;y++){acc=0;for(x=-rd;x<=rd;x++){acc+=x>=0&&x<N?SI[y*N+x]:0;}for(x=0;x<N;x++){TB[y*N+x]=acc/(2*rd+1);u=x+rd+1;v2=x-rd;acc+=(u<N?SI[y*N+u]:0)-(v2>=0?SI[y*N+v2]:0);}}
for(x=0;x<N;x++){acc=0;for(y=-rd;y<=rd;y++){acc+=y>=0&&y<N?TB[y*N+x]:0;}for(y=0;y<N;y++){BL[y*N+x]=acc/(2*rd+1);u=y+rd+1;v2=y-rd;acc+=(u<N?TB[u*N+x]:0)-(v2>=0?TB[v2*N+x]:0);}}
for(i=0;i<NN;i++){j=i*4;
if(R[i]){g=MA[j+3]>60?252*(0.72+0.28*Math.max(0,Math.min(1,((MA[j]+MA[j+1]+MA[j+2])/3-p5)/(p95-p5)))):252*(0.84+0.28*(TX(i%N,(i/N)|0)-med)/(p95-p5));g=Math.max(120,Math.min(252,g))*(0.965+0.035*Math.min(1,BL[i]*1.15));FD[j]=g;FD[j+1]=g;FD[j+2]=g;}}
LUM=new Float32Array(NN);for(i=0;i<NN;i++){j=i*4;LUM[i]=(0.299*FD[j]+0.587*FD[j+1]+0.114*FD[j+2])/255;}
CA=new Float32Array(NN);var sm,dy,dx;
for(y=1;y<N-1;y++){for(x=1;x<N-1;x++){sm=0;for(dy=-1;dy<=1;dy++){for(dx=-1;dx<=1;dx++){sm+=R[(y+dy)*N+x+dx];}}CA[y*N+x]=sm/9;}}
REF=0.97;OUT=ctx.createImageData(N,N);
}catch(e){erro=true;window.__erro=String(e);}
draw();
}
function prep(){
if(++ready<2){return;}
if(PRD.branca){prepBranca();return;}if(PRD.tipo==='pasta'){prepPasta();return;}
try{
var t=document.createElement('canvas');t.width=N;t.height=N;var c=t.getContext('2d',{willReadFrequently:true});
c.drawImage(foto,0,0,N,N);FD=c.getImageData(0,0,N,N).data;
c.clearRect(0,0,N,N);c.drawImage(mk,X0,Y0,X1-X0,Y1-Y0);var MA=c.getImageData(0,0,N,N).data;
var NN=N*N,i,j,x,y,Ab=new Uint8Array(NN),S=new Uint8Array(NN),l,mxv,mnv;
LUM=new Float32Array(NN);
for(i=0;i<NN;i++){j=i*4;l=0.299*FD[j]+0.587*FD[j+1]+0.114*FD[j+2];LUM[i]=l/255;
mxv=Math.max(FD[j],FD[j+1],FD[j+2]);mnv=Math.min(FD[j],FD[j+1],FD[j+2]);
S[i]=(mnv>95&&(mxv-mnv)<48&&l>110)?1:0;Ab[i]=MA[j+3]>128?1:0;}
var ext=new Uint8Array(Ab),EL=new Int16Array(N),XL=new Int16Array(N).fill(-1);
for(y=0;y<N;y++){var xl=-1,xr=-1;for(x=0;x<N;x++){if(Ab[y*N+x]){if(xl<0){xl=x;}xr=x;}}
if(xl<0){continue;}
x=xr+1;while(x<=RMAX&&S[y*N+x]){ext[y*N+x]=1;x++;}
x=xl-1;var en=0;while(x>=xl-12&&x>=0&&S[y*N+x]){en++;x--;}EL[y]=en;XL[y]=xl;}
for(y=0;y<N;y++){if(XL[y]<0){continue;}var wn=[],yy,cp;for(yy=y-6;yy<=y+6;yy++){if(yy>=0&&yy<N&&XL[yy]>=0){wn.push(EL[yy]);}}wn.sort(function(u,v){return u-v;});cp=Math.min(EL[y],wn[wn.length>>1]+1);for(x=1;x<=cp;x++){ext[y*N+XL[y]-x]=1;}}
var Sd=box(box(S,N,N,5,true),N,N,5,false);
var core=box(Ab,N,N,15,false);
var F=new Uint8Array(NN);
for(i=0;i<NN;i++){x=i%N;F[i]=(((ext[i]&&Sd[i])||core[i])&&x<=RMAX&&((i/N)|0)>=YTOP)?1:0;}
CA=new Float32Array(NN);
for(y=1;y<N-1;y++){for(x=1;x<N-1;x++){var s=0;for(var dy=-1;dy<=1;dy++){for(var dx=-1;dx<=1;dx++){s+=F[(y+dy)*N+x+dx];}}CA[y*N+x]=s/9*ALPHA*(x>=RR?0:(x>RR-4?(RR-x)/4:1));}}
var h=new Uint32Array(256),n=0;
for(i=0;i<NN;i++){if(CA[i]>0.4*ALPHA){h[Math.round(LUM[i]*255)]++;n++;}}
var s2=0,k=0;for(;k<256;k++){s2+=h[k];if(s2>=n/2){break;}}REF=k/255;
OUT=ctx.createImageData(N,N);
}catch(e){erro=true;window.__erro=String(e);}
draw();
}
function limpa(im,forca,mx){
var s=Math.min(1,(mx||400)/Math.max(im.naturalWidth,im.naturalHeight)),w=Math.max(1,Math.round(im.naturalWidth*s)),h=Math.max(1,Math.round(im.naturalHeight*s));
var cc=document.createElement('canvas');cc.width=w;cc.height=h;var g=cc.getContext('2d',{willReadFrequently:true});g.drawImage(im,0,0,w,h);
var d=g.getImageData(0,0,w,h),p=d.data,vis=new Uint8Array(w*h),st=[],i,x,y,q;
var tr=0,tn=0;for(x=0;x<w;x++){tn+=2;if(p[x*4+3]<20){tr++;}if(p[((h-1)*w+x)*4+3]<20){tr++;}}for(y=0;y<h;y++){tn+=2;if(p[y*w*4+3]<20){tr++;}if(p[(y*w+w-1)*4+3]<20){tr++;}}
var temT=tr>tn*0.5;/* PNG com fundo transparente: so o transparente some; o branco do desenho (contorno) fica */
function ok(k){var j=k*4;return p[j+3]<20||(!temT&&p[j]>226&&p[j+1]>226&&p[j+2]>226);}
if(forca||p[3]>=20){for(x=0;x<w;x++){st.push(x);st.push((h-1)*w+x);}for(y=0;y<h;y++){st.push(y*w);st.push(y*w+w-1);}}
while(st.length){q=st.pop();if(vis[q]||!ok(q)){continue;}vis[q]=1;x=q%w;y=(q/w)|0;if(x>0){st.push(q-1);}if(x<w-1){st.push(q+1);}if(y>0){st.push(q-w);}if(y<h-1){st.push(q+w);}}
var x0=w,y0=h,x1=0,y1=0,ls=0,ln=0;
for(i=0;i<w*h;i++){if(vis[i]){p[i*4+3]=0;}else if(p[i*4+3]>20){ls+=0.299*p[i*4]+0.587*p[i*4+1]+0.114*p[i*4+2];ln++;x=i%w;y=(i/w)|0;if(x<x0){x0=x;}if(x>x1){x1=x;}if(y<y0){y0=y;}if(y>y1){y1=y;}}}
if(x1<x0){return null;}
g.putImageData(d,0,0);return {cv:cc,bx:x0,by:y0,bw:x1-x0+1,bh:y1-y0+1,dark:(ls/Math.max(1,ln))<150};
}
function lcv(){if(!LCOR||!logoC){return logoC.cv;}var t=logoC._t;if(t&&t.c===LCOR&&t.s===logoC.cv){return t.cv;}var cv=document.createElement('canvas');cv.width=logoC.cv.width;cv.height=logoC.cv.height;var g=cv.getContext('2d');g.drawImage(logoC.cv,0,0);g.globalCompositeOperation='source-in';g.fillStyle=LCOR;g.fillRect(0,0,cv.width,cv.height);logoC._t={c:LCOR,s:logoC.cv,cv:cv};return cv;}
function lescuro(){if(!LCOR){return logoC.dark;}var r=rgbDe(LCOR);return (0.299*r[0]+0.587*r[1]+0.114*r[2])<150;}
function recorta(l){var c=document.createElement('canvas');c.width=l.bw;c.height=l.bh;c.getContext('2d').drawImage(l.cv,l.bx,l.by,l.bw,l.bh,0,0,l.bw,l.bh);return c;}
// adesivo: contorno branco grosso + borda cinza fina, como figurinha recortada
function adesivo(src){
var sw=src.naturalWidth||src.width,sh=src.naturalHeight||src.height,s=Math.min(1,300/Math.max(sw,sh)),cw=Math.max(1,Math.round(sw*s)),ch=Math.max(1,Math.round(sh*s)),R=13,pad=R+6;
var W=cw+2*pad,H=ch+2*pad,c1=document.createElement('canvas');c1.width=cw;c1.height=ch;c1.getContext('2d').drawImage(src,0,0,cw,ch);
var sil=document.createElement('canvas');sil.width=W;sil.height=H;var g=sil.getContext('2d');g.drawImage(c1,pad,pad);g.globalCompositeOperation='source-in';g.fillStyle='#fff';g.fillRect(0,0,W,H);
var gr=document.createElement('canvas');gr.width=W;gr.height=H;var q=gr.getContext('2d');q.drawImage(sil,0,0);q.globalCompositeOperation='source-in';q.fillStyle='#CBD2DC';q.fillRect(0,0,W,H);
var out=document.createElement('canvas');out.width=W;out.height=H;var o=out.getContext('2d'),a,k,r;
for(k=0;k<28;k++){a=k/28*6.2832;o.drawImage(gr,Math.cos(a)*(R+1.6),Math.sin(a)*(R+1.6));}
for(k=0;k<28;k++){a=k/28*6.2832;for(r=0.45;r<=1.01;r+=0.55){o.drawImage(sil,Math.cos(a)*R*r,Math.sin(a)*R*r);}}
o.drawImage(c1,pad,pad);
return out;
}
function wrap(c,t,w){var p=t.split(' '),l=[],cur='',i,tt;for(i=0;i<p.length;i++){tt=cur?cur+' '+p[i]:p[i];if(c.measureText(tt).width>w&&cur){l.push(cur);cur=p[i];}else{cur=tt;}}if(cur){l.push(cur);}return l;}
function rr(c,x,y,w,h,r){c.beginPath();c.moveTo(x+r,y);c.arcTo(x+w,y,x+w,y+h,r);c.arcTo(x+w,y+h,x,y+h,r);c.arcTo(x,y+h,x,y,r);c.arcTo(x,y,x+w,y,r);c.closePath();}
function rgbDe(t){if(t.charAt(0)==='#'){var n=parseInt(t.slice(1),16);return [(n>>16)&255,(n>>8)&255,n&255];}return t.slice(t.indexOf('(')+1,-1).split(',').map(Number);}
function lumRel(r,g,b){function f(v){v=v/255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4);}return 0.2126*f(r)+0.7152*f(g)+0.0722*f(b);}
function textoEscuro(c){var a=rgbDe(c.cor),b=rgbDe(c.esc||c.cor),m=lumRel((a[0]+b[0])/2,(a[1]+b[1])/2,(a[2]+b[2])/2);return 1.05/(m+0.05)<4;}
function arte(c){return artC(c,1).getContext('2d').getImageData(0,0,N,N).data;}
function artC(c,S,fg){
if(COR){c={n:c.n,cor:COR.cor,esc:COR.esc};}
if(AIMG&&AHIDE){c={n:' ',cor:c.cor,esc:c.esc};}
var art=document.createElement('canvas');art.width=N*S;art.height=N*S;var a=art.getContext('2d',{willReadFrequently:true});a.imageSmoothingQuality='high';a.scale(S,S);
var g=a.createLinearGradient(0,0,N*0.4,N);g.addColorStop(0,c.cor);g.addColorStop(1,c.esc);if(!fg){a.fillStyle=g;a.fillRect(0,0,N,N);}
if(TEX&&!fg){var pt=a.createPattern(TEX.cv,'repeat');if(pt){if(pt.setTransform){pt.setTransform(new DOMMatrix().scale(TEX.sz*UN/TEX.cv.width));}a.save();a.globalAlpha=TI;a.globalCompositeOperation='overlay';a.fillStyle=pt;a.fillRect(0,0,N,N);a.restore();}}
if(!fg&&EST){EST.fn(a);}if(!fg&&AIMG){a.drawImage(AIMG,X0,YTOP,RR-X0,Y1-YTOP);}
var LY=PRD.lay,SC=LY.s,cx=LY.cx,fs,lines,i,tile=104*SC,lg=70*SC,gap=14*SC,cv0=covs[sel],hasT=!(AIMG&&AHIDE)&&!!(cv0&&cv0.naturalWidth),hasL=!!logoC,sem=!c.n||!c.n.trim(),tot,wm;
a.textAlign='center';a.textBaseline='alphabetic';
for(fs=72*SC;fs>=24*SC;fs-=4*SC){a.font='800 '+fs+'px Poppins,Arial,sans-serif';lines=sem?[]:wrap(a,c.n,LY.wrap);wm=0;for(i=0;i<lines.length;i++){wm=Math.max(wm,a.measureText(lines[i]).width);}tot=(hasT?tile+gap:0)+lines.length*fs*1.04+(hasL?gap+lg:0);if(wm<=LY.wrap&&tot<=LY.maxh){break;}}
var lh=fs*1.04,total=(hasT?tile+gap:0)+lines.length*lh+(hasL?gap+lg:0),y=LY.cy-total/2;
var lh0=fs*1.04,blockH=(hasT?tile+gap:0)+lines.length*lh0,cH=blockH-(lines.length?lh0*0.02:(hasT?gap:0)),cOn=cH>0,bcx=cx,bcy=y+cH/2,wb=Math.max(wm,hasT?tile:0)+10*SC;
if(cOn){a.save();a.translate(bcx+CG.dx,bcy+CG.dy);a.rotate(CG.r||0);a.scale(CG.k,CG.k);a.translate(-bcx,-bcy);a.globalAlpha=CG.off?0:1;}
var cW=cOn&&isWarp(CG.q)&&!CG.off,a0=a,oc=null,PADC=20;if(cW){oc=document.createElement('canvas');oc.width=Math.ceil((wb+2*PADC)*S);oc.height=Math.ceil((cH+2*PADC)*S);a=oc.getContext('2d');a.imageSmoothingQuality='high';a.scale(S,S);a.translate(PADC-(bcx-wb/2),PADC-y);a.textAlign='center';a.textBaseline='alphabetic';}
if(hasT){a.save();a.shadowColor='rgba(0,0,0,0.28)';a.shadowBlur=14;a.shadowOffsetY=4;rr(a,cx-tile/2,y,tile,tile,22);a.fillStyle='#fff';a.fill();a.restore();a.save();rr(a,cx-tile/2,y,tile,tile,22);a.clip();a.drawImage(cv0,cx-tile/2,y,tile,tile);a.restore();y+=tile+gap;}
var te=textoEscuro(c);a.fillStyle=te?'#0B1F3F':'#fff';a.shadowColor=te?'rgba(255,255,255,0.55)':'rgba(0,0,0,0.35)';a.shadowBlur=8;a.shadowOffsetY=2;a.font='800 '+fs+'px Poppins,Arial,sans-serif';for(i=0;i<lines.length;i++){y+=lh;a.fillText(lines[i],cx,y-lh*0.18);}a.shadowColor='rgba(0,0,0,0)';a.shadowBlur=0;a.shadowOffsetY=0;
if(cW){a=a0;var Q2=CG.q;warpDraw(a,oc,PADC*S,PADC*S,wb*S,cH*S,[[bcx-wb/2+Q2[0][0],bcy-cH/2+Q2[0][1]],[bcx+wb/2+Q2[1][0],bcy-cH/2+Q2[1][1]],[bcx+wb/2+Q2[2][0],bcy+cH/2+Q2[2][1]],[bcx-wb/2+Q2[3][0],bcy+cH/2+Q2[3][1]]]);}
if(cOn){a.restore();}a.globalAlpha=1;
cursoBox=cOn&&!CG.off?{x:bcx+CG.dx-wb*CG.k/2,y:bcy+CG.dy-cH*CG.k/2,w:wb*CG.k,h:cH*CG.k}:null;
if(hasL){y+=gap;}
var lcx=cx+LG.dx,lcy=y+lg/2+LG.dy,lw=0,lhh=0;
if(hasL){a.save();a.translate(lcx,lcy);a.rotate(LG.r||0);a.scale(LG.k,LG.k);
{var r=Math.min(LY.logow/logoC.bw,lg/logoC.bh);lw=logoC.bw*r;lhh=logoC.bh*r;var ldk=lescuro();a.shadowColor=ldk?'rgba(255,255,255,0.85)':'rgba(0,0,0,0.10)';a.shadowBlur=ldk?10:5;a.shadowOffsetY=ldk?0:1;if(isWarp(LG.q)){var Q0=LG.q;a.shadowColor='rgba(0,0,0,0)';warpDraw(a,lcv(),logoC.bx,logoC.by,logoC.bw,logoC.bh,[[-lw/2+Q0[0][0],-lhh/2+Q0[0][1]],[lw/2+Q0[1][0],-lhh/2+Q0[1][1]],[lw/2+Q0[2][0],lhh/2+Q0[2][1]],[-lw/2+Q0[3][0],lhh/2+Q0[3][1]]]);}else{a.drawImage(lcv(),logoC.bx,logoC.by,logoC.bw,logoC.bh,-lw/2,-lhh/2,lw,lhh);}}
a.restore();}
logoBox={x:lcx-lw*LG.k/2,y:lcy-lhh*LG.k/2,w:lw*LG.k,h:lhh*LG.k};
for(i=0;i<items.length;i++){var it=items[i];a.save();a.shadowColor='rgba(0,0,0,0.30)';a.shadowBlur=10;a.shadowOffsetY=3;a.translate(it.x,it.y);a.rotate(it.r||0);if(isWarp(it.q)){var Q1=it.q;a.shadowColor='rgba(0,0,0,0)';warpDraw(a,it.cv,0,0,it.cv.width,it.cv.height,[[-it.w/2+Q1[0][0],-it.h/2+Q1[0][1]],[it.w/2+Q1[1][0],-it.h/2+Q1[1][1]],[it.w/2+Q1[2][0],it.h/2+Q1[2][1]],[-it.w/2+Q1[3][0],it.h/2+Q1[3][1]]]);}else{a.drawImage(it.cv,-it.w/2,-it.h/2,it.w,it.h);}a.restore();}
return art;
}
var ADW=null,ADWk='';
function curvaCil(AD){var tm=PRD.cyl,sn=Math.sin(tm),hw=(X1-X0)/2,cx0=(X0+X1)/2,key=N+'|'+AD.length,x,y,sx,u,sxs=new Int16Array(X1-X0+1),rs=new Int16Array(X1-X0+1),o,a2,sy,s2,hc=PRD.hc||[0,0],e1=1-Math.sqrt(1-sn*sn);if(!ADW||ADWk!==key){ADW=new Uint8ClampedArray(AD.length);ADWk=key;}ADW.set(AD);
for(x=X0;x<=X1;x++){u=Math.asin(Math.max(-1,Math.min(1,((x-cx0)/hw)*sn)))/tm;sxs[x-X0]=Math.round(cx0+u*hw);s2=((x-cx0)/hw)*sn;rs[x-X0]=Math.round((s2<0?hc[0]:hc[1])*(1-Math.sqrt(1-s2*s2))/e1);}
for(y=0;y<N;y++){for(x=X0;x<=X1;x++){sx=sxs[x-X0];sy=y+rs[x-X0];if(sy>=N){sy=N-1;}if(sx!==x||sy!==y){o=(y*N+x)*4;a2=(sy*N+sx)*4;ADW[o]=AD[a2];ADW[o+1]=AD[a2+1];ADW[o+2]=AD[a2+2];ADW[o+3]=AD[a2+3];}}}
return ADW;}
function draw(){
var c=D.cursos[sel]||SEM;if(!FD||!OUT){return;}if(!AIMG&&BG.im&&window.__hideBg){window.__hideBg();}
var VAZ=sel<0&&!AIMG&&!EST&&!TEX&&!items.length&&!logoC&&!COR;
if(sel>=0&&covs[sel]===undefined){if(c.c){var ci=new Image();covs[sel]=ci;ci.onload=function(){pedir();};ci.src=c.c;}else{covs[sel]=null;}}
var GN=PRD.gain||1.14;var BRC=!!PRD.branca;var OPA=PRD.op||0.94;var AD=VAZ?null:arte(c);if(AD&&PRD.cyl){AD=curvaCil(AD);}var NOBG=!VAZ&&PRD.tipo==='caneca2'&&sel<0&&!COR&&!EST&&!AIMG&&!TEX;var ADF=(!VAZ&&((PRD.lado&&SH)||PRD.fop||NOBG))?artC(c,1,true).getContext('2d').getImageData(0,0,N,N).data:null,o=OUT.data,i,j,al,r,g,b,sp,shd,ar,ag,ab,CON=PRD.con||1,BRI=PRD.bri||1,SAT=PRD.sat||1,AJ=(CON!==1||BRI!==1||SAT!==1),sref=REF+0.02,den=1-sref;
for(i=0;i<N*N;i++){
j=i*4;al=CA[i]*OPA;if(ADF&&PRD.fop&&al>=0.004){al=CA[i]*(OPA+(PRD.fop-OPA)*ADF[j+3]/255);}if(NOBG){al=CA[i]*(PRD.fop||OPA)*ADF[j+3]/255;}
if(VAZ||al<0.004){o[j]=FD[j];o[j+1]=FD[j+1];o[j+2]=FD[j+2];o[j+3]=255;continue;}
shd=(PRD.lado&&SH)?SH[i]:1;if(ADF&&shd<1){shd=shd+(1-shd)*ADF[j+3]/255;}ar=AD[j];ag=AD[j+1];ab=AD[j+2];if(AJ){ar=Math.max(0,Math.min(255,((ar-128)*CON+128)*BRI));ag=Math.max(0,Math.min(255,((ag-128)*CON+128)*BRI));ab=Math.max(0,Math.min(255,((ab-128)*CON+128)*BRI));if(SAT!==1){var lm=0.299*ar+0.587*ag+0.114*ab;ar=Math.max(0,Math.min(255,lm+(ar-lm)*SAT));ag=Math.max(0,Math.min(255,lm+(ag-lm)*SAT));ab=Math.max(0,Math.min(255,lm+(ab-lm)*SAT));}if(ADF){var fa=ADF[j+3]/255;ar+=(AD[j]-ar)*fa;ag+=(AD[j+1]-ag)*fa;ab+=(AD[j+2]-ab)*fa;}}r=Math.min(255,FD[j]*ar/255*GN*shd)*al+FD[j]*(1-al);g=Math.min(255,FD[j+1]*ag/255*GN*shd)*al+FD[j+1]*(1-al);b=Math.min(255,FD[j+2]*ab/255*GN*shd)*al+FD[j+2]*(1-al);
sp=BRC?0:(LUM[i]-sref)/den;if(sp>0&&PRD.hlr){sp*=Math.max(0,Math.min(1,(((i/N)|0)-YTOP-PRD.hlr[0])/PRD.hlr[1]));}if(sp>0){sp=Math.pow(sp>1?1:sp,0.8)*0.95*al;r+=(255-r)*sp;g+=(255-g)*sp;b+=(255-b)*sp;}
o[j]=r;o[j+1]=g;o[j+2]=b;o[j+3]=255;
}
ctx.putImageData(OUT,0,0);
if(PRD.extras&&!VAZ){kitExtras();}
if(showSel&&selIt){var H0=hdl(selIt),cs0=H0.cs,k0,ii,U2=UN*HM(),U3=U2*(COARSE?0.6:1);ctx.save();ctx.lineJoin='round';
ctx.setLineDash([10*U2,8*U2]);ctx.beginPath();ctx.moveTo(cs0[0].x,cs0[0].y);for(ii=1;ii<4;ii++){ctx.lineTo(cs0[ii].x,cs0[ii].y);}ctx.closePath();
ctx.lineWidth=5*U2;ctx.strokeStyle='rgba(0,0,0,0.55)';ctx.stroke();ctx.lineWidth=2.5*U2;ctx.strokeStyle='#fff';ctx.stroke();ctx.setLineDash([]);
ctx.beginPath();ctx.moveTo(H0.mt.x,H0.mt.y);ctx.lineTo(H0.rot.x,H0.rot.y);ctx.lineWidth=3*U2;ctx.strokeStyle='#fff';ctx.stroke();
ctx.beginPath();ctx.arc(H0.rot.x,H0.rot.y,17*U3,0,6.2832);ctx.fillStyle='#fff';ctx.fill();ctx.lineWidth=3*U3;ctx.strokeStyle='#C9A24A';ctx.stroke();
ctx.beginPath();ctx.arc(H0.rot.x,H0.rot.y,7*U3,-2.4,2.9);ctx.lineWidth=3*U3;ctx.strokeStyle='#0B1F3F';ctx.stroke();ctx.beginPath();ctx.moveTo(H0.rot.x-3*U3,H0.rot.y-12*U3);ctx.lineTo(H0.rot.x+4*U3,H0.rot.y-6*U3);ctx.lineTo(H0.rot.x-4*U3,H0.rot.y-2*U3);ctx.fillStyle='#0B1F3F';ctx.fill();
ctx.beginPath();ctx.arc(H0.del.x,H0.del.y,17*U3,0,6.2832);ctx.fillStyle='#fff';ctx.fill();ctx.lineWidth=3*U3;ctx.strokeStyle='#D92D20';ctx.stroke();ctx.beginPath();ctx.moveTo(H0.del.x-6*U3,H0.del.y-6*U3);ctx.lineTo(H0.del.x+6*U3,H0.del.y+6*U3);ctx.moveTo(H0.del.x+6*U3,H0.del.y-6*U3);ctx.lineTo(H0.del.x-6*U3,H0.del.y+6*U3);ctx.lineWidth=3*U3;ctx.strokeStyle='#D92D20';ctx.stroke();
ctx.setLineDash([6*U3,5*U3]);ctx.beginPath();ctx.moveTo(cs0[2].x,cs0[2].y);ctx.lineTo(H0.esc.x,H0.esc.y);ctx.lineWidth=2.5*U3;ctx.strokeStyle='#1E6FD9';ctx.stroke();ctx.setLineDash([]);
ctx.beginPath();ctx.rect(H0.esc.x-17*U3,H0.esc.y-17*U3,34*U3,34*U3);ctx.fillStyle='#1E6FD9';ctx.fill();ctx.lineWidth=3*U3;ctx.strokeStyle='#fff';ctx.stroke();
ctx.beginPath();ctx.moveTo(H0.esc.x-7*U3,H0.esc.y+7*U3);ctx.lineTo(H0.esc.x+7*U3,H0.esc.y-7*U3);ctx.moveTo(H0.esc.x+7*U3,H0.esc.y-7*U3);ctx.lineTo(H0.esc.x+1*U3,H0.esc.y-7*U3);ctx.moveTo(H0.esc.x+7*U3,H0.esc.y-7*U3);ctx.lineTo(H0.esc.x+7*U3,H0.esc.y-1*U3);ctx.moveTo(H0.esc.x-7*U3,H0.esc.y+7*U3);ctx.lineTo(H0.esc.x-1*U3,H0.esc.y+7*U3);ctx.moveTo(H0.esc.x-7*U3,H0.esc.y+7*U3);ctx.lineTo(H0.esc.x-7*U3,H0.esc.y+1*U3);ctx.lineWidth=2.5*U3;ctx.strokeStyle='#fff';ctx.stroke();
for(ii=0;ii<4;ii++){ctx.beginPath();ctx.arc(cs0[ii].x,cs0[ii].y,13*U2*0.3,0,6.2832);ctx.fillStyle='#E53935';ctx.fill();ctx.lineWidth=1.4*U2;ctx.strokeStyle='#fff';ctx.stroke();}
ctx.beginPath();ctx.arc(H0.c.x,H0.c.y,9*U2*0.3,0,6.2832);ctx.fillStyle='#E53935';ctx.fill();ctx.lineWidth=1.2*U2;ctx.strokeStyle='#fff';ctx.stroke();
ctx.restore();}
[].forEach.call(document.querySelectorAll('#dcvx-g .dcvx-t'),function(x){x.classList.toggle('on',+x.getAttribute('data-i')===sel);});
window.__ok=true;
}
/* Kit: logo da escola nas outras pecas (squeeze em cores, bloco gravado), recortado pela mascara de cada peca */
function kitExtras(){if(!logoC){return;}var src=lcv();PRD.extras.forEach(function(E){if(!E.im||!E.im.complete||!E.im.naturalWidth){return;}var w=Math.round(E.w),h=Math.round(E.h),oc=document.createElement('canvas');oc.width=w;oc.height=h;var g=oc.getContext('2d');g.imageSmoothingQuality='high';var r=Math.min(w*E.fit/logoC.bw,h*E.fit/logoC.bh),lw=logoC.bw*r,lh=logoC.bh*r;g.drawImage(src,logoC.bx,logoC.by,logoC.bw,logoC.bh,(w-lw)/2,(h-lh)/2,lw,lh);if(E.modo==='grav'){g.globalCompositeOperation='source-in';g.fillStyle='#E4E7EE';g.fillRect(0,0,w,h);}g.globalCompositeOperation='destination-in';g.drawImage(E.im,0,0,w,h);ctx.save();if(E.modo==='grav'){ctx.globalAlpha=0.82;}else{ctx.globalCompositeOperation='multiply';}ctx.drawImage(oc,E.x,E.y);ctx.restore();});}
function pedir(){if(raf){return;}raf=requestAnimationFrame(function(){raf=0;draw();});}
function isSp(it){return it==='logo'||it==='curso';}
function SP(it){return it==='curso'?CG:LG;}
function bounds(it){if(it==='logo'){return logoBox||{x:0,y:0,w:0,h:0};}if(it==='curso'){return cursoBox||{x:0,y:0,w:0,h:0};}return {x:it.x-it.w/2,y:it.y-it.h/2,w:it.w,h:it.h};}
// painel de controles
var sz=document.getElementById('dcvx-sz');
function syncSz(){if(!sz){return;}if(isSp(selIt)){sz.min=30;sz.max=300;sz.value=Math.round(SP(selIt).k*100);sz.disabled=false;}else if(selIt){sz.min=Math.round(40*UN);sz.max=Math.round(420*UN);sz.value=Math.round(selIt.w);sz.disabled=false;}else{sz.disabled=true;}}
if(sz){sz.addEventListener('input',function(){var v=+sz.value;if(isSp(selIt)){SP(selIt).k=v/100;}else if(selIt){var asp=selIt.h/selIt.w;selIt.w=v;selIt.h=v*asp;}pedir();});}
function pt(e){var r=cv.getBoundingClientRect();return {x:(e.clientX-r.left)*N/r.width,y:(e.clientY-r.top)*N/r.height};}
function rotDe(it){return isSp(it)?(SP(it).r||0):(it.r||0);}
var SGN=[[-1,-1],[1,-1],[1,1],[-1,1]];
function isWarp(q){return !!q&&q.some(function(v){return v[0]||v[1];});}
function getQ(it){var H=isSp(it)?SP(it):it;if(!H.q){H.q=[[0,0],[0,0],[0,0],[0,0]];}return H.q;}
var COARSE=false;try{COARSE=window.matchMedia('(pointer:coarse)').matches;}catch(e){}
function HM(){var w=cv.getBoundingClientRect().width||N;return Math.max(1,(COARSE?17:8)/(13*UN*w/N));}
function geo(it){var b=bounds(it),k=isSp(it)?(SP(it).k||1):1,q=isSp(it)?SP(it).q:it.q;return {c:{x:b.x+b.w/2,y:b.y+b.h/2},w0:b.w/k,h0:b.h/k,k:k,r:rotDe(it),q:q||[[0,0],[0,0],[0,0],[0,0]]};}
function hdl(it){var g=geo(it),co=Math.cos(g.r),si=Math.sin(g.r);function W(x,y){return {x:g.c.x+(x*co-y*si)*g.k,y:g.c.y+(x*si+y*co)*g.k};}
var cs=SGN.map(function(sg,i){return W(sg[0]*g.w0/2+g.q[i][0],sg[1]*g.h0/2+g.q[i][1]);});
var mt={x:(cs[0].x+cs[1].x)/2,y:(cs[0].y+cs[1].y)/2},dx=cs[2].x-g.c.x,dy=cs[2].y-g.c.y,dl=Math.hypot(dx,dy)||1,U=UN*HM();
function cl(v){return Math.max(20*U,Math.min(N-20*U,v));}
return {esc:{x:cl(cs[2].x+dx/dl*50*U),y:cl(cs[2].y+dy/dl*50*U)},cs:cs,mt:mt,rot:{x:cl(mt.x+si*50*U),y:cl(mt.y-co*50*U)},del:{x:cl(mt.x+si*50*U-co*46*U),y:cl(mt.y-co*50*U-si*46*U)},c:{x:g.c.x,y:g.c.y,w:g.w0*g.k,h:g.h0*g.k}};}
/* deformacao por cantos: a imagem e desenhada em malha de triangulos entre os 4 cantos escolhidos pelo cliente */
function tri(g,img,x0,y0,x1,y1,x2,y2,A,B,D){var det=(x1-x0)*(y2-y0)-(x2-x0)*(y1-y0);if(!det){return;}
var a=((B.x-A.x)*(y2-y0)-(D.x-A.x)*(y1-y0))/det,c=((D.x-A.x)*(x1-x0)-(B.x-A.x)*(x2-x0))/det,b=((B.y-A.y)*(y2-y0)-(D.y-A.y)*(y1-y0))/det,d=((D.y-A.y)*(x1-x0)-(B.y-A.y)*(x2-x0))/det;
var cx=(A.x+B.x+D.x)/3,cy=(A.y+B.y+D.y)/3;function ex(P){var dx=P.x-cx,dy=P.y-cy,l=Math.hypot(dx,dy)||1;return [P.x+dx/l*0.8,P.y+dy/l*0.8];}
var e0=ex(A),e1=ex(B),e2=ex(D),mnx=Math.floor(Math.min(x0,x1,x2))-1,mny=Math.floor(Math.min(y0,y1,y2))-1,mxx=Math.ceil(Math.max(x0,x1,x2))+1,mxy=Math.ceil(Math.max(y0,y1,y2))+1;
g.save();g.beginPath();g.moveTo(e0[0],e0[1]);g.lineTo(e1[0],e1[1]);g.lineTo(e2[0],e2[1]);g.closePath();g.clip();
g.transform(a,b,c,d,A.x-a*x0-c*y0,A.y-b*x0-d*y0);g.drawImage(img,mnx,mny,mxx-mnx,mxy-mny,mnx,mny,mxx-mnx,mxy-mny);g.restore();}
function warpDraw(g,img,sx,sy,sw,sh,P){var n=10,i,j;function pt2(u,v){return {x:(1-u)*(1-v)*P[0][0]+u*(1-v)*P[1][0]+u*v*P[2][0]+(1-u)*v*P[3][0],y:(1-u)*(1-v)*P[0][1]+u*(1-v)*P[1][1]+u*v*P[2][1]+(1-u)*v*P[3][1]};}
for(j=0;j<n;j++){for(i=0;i<n;i++){var u0=i/n,u1=(i+1)/n,v0=j/n,v1=(j+1)/n,A=pt2(u0,v0),B=pt2(u1,v0),C=pt2(u1,v1),D=pt2(u0,v1);
tri(g,img,sx+sw*u0,sy+sh*v0,sx+sw*u1,sy+sh*v0,sx+sw*u0,sy+sh*v1,A,B,D);tri(g,img,sx+sw*u1,sy+sh*v0,sx+sw*u1,sy+sh*v1,sx+sw*u0,sy+sh*v1,B,C,D);}}}
function dentro(it,p){var h=hdl(it).cs,i,j,d=false;for(i=0,j=3;i<4;j=i++){if(((h[i].y>p.y)!==(h[j].y>p.y))&&(p.x<(h[j].x-h[i].x)*(p.y-h[i].y)/(h[j].y-h[i].y)+h[i].x)){d=!d;}}return d;}
function hit(p){var i;for(i=items.length-1;i>=0;i--){if(dentro(items[i],p)){return items[i];}}if(dentro('logo',p)){return 'logo';}if(dentro('curso',p)){return 'curso';}return null;}
function alca(p){if(!selIt){return '';}var h=hdl(selIt),q,U=UN*HM()*(COARSE?1.25:1);if(Math.hypot(p.x-h.del.x,p.y-h.del.y)<=24*U){return 'del';}if(Math.hypot(p.x-h.rot.x,p.y-h.rot.y)<=28*U){return 'rot';}if(Math.hypot(p.x-h.esc.x,p.y-h.esc.y)<=26*U){return 'esc';}for(q=0;q<4;q++){if(Math.hypot(p.x-h.cs[q].x,p.y-h.cs[q].y)<=22*U){return 'c'+q;}}return '';}
function apagaSel(){if(selIt==='curso'){CG.off=true;}else if(selIt==='logo'){logoC=null;if(window.__lcorSync){window.__lcorSync('');}}else if(selIt){items.splice(items.indexOf(selIt),1);}else{return;}selIt=null;syncSz();pedir();if(window.__voltaCurso){window.__voltaCurso();}}
function tamIt(it){return isSp(it)?SP(it).k:it.w;}
cv.addEventListener('pointerdown',function(e){if(BGMOVE&&BG.im){var pb=pt(e);drag={modo:'bg',x:pb.x,y:pb.y,dx:BG.dx,dy:BG.dy};try{cv.setPointerCapture(e.pointerId);}catch(x){}e.preventDefault();return;}var p=pt(e),al=alca(p);
if(al==='del'){apagaSel();return;}if(al){var h=hdl(selIt);drag={modo:(al==='rot'||al==='esc')?al:'pin',i:(al==='rot'||al==='esc')?0:+al.slice(1),cx:h.c.x,cy:h.c.y,d0:Math.max(20,Math.hypot(p.x-h.c.x,p.y-h.c.y)),t0:tamIt(selIt),h0:isSp(selIt)?0:selIt.h};try{cv.setPointerCapture(e.pointerId);}catch(x){}return;}
var hi=hit(p);selIt=hi;syncSz();if(hi){var b=bounds(hi);drag={modo:'mov',ox:p.x-(b.x+b.w/2),oy:p.y-(b.y+b.h/2)};try{cv.setPointerCapture(e.pointerId);}catch(x){}}pedir();});
cv.addEventListener('pointermove',function(e){var p=pt(e);
if(drag&&drag.modo==='bg'){BG.dx=drag.dx+(p.x-drag.x)*2;BG.dy=drag.dy+(p.y-drag.y)*2;window.__renderBg();pedir();return;}
if(!drag){var al=alca(p);cv.style.cursor=al==='rot'?'grab':(al==='esc'?'nwse-resize':(al==='del'?'pointer':(al?'crosshair':'')));return;}
if(!selIt){return;}
if(drag.modo==='rot'){var a=Math.atan2(p.y-drag.cy,p.x-drag.cx)+Math.PI/2,q=Math.PI/12,n=Math.round(a/q)*q;if(Math.abs(a-n)<0.06){a=n;}if(isSp(selIt)){SP(selIt).r=a;}else{selIt.r=a;}pedir();return;}
if(drag.modo==='pin'){var g=geo(selIt),co=Math.cos(g.r),si=Math.sin(g.r),dx=p.x-g.c.x,dy=p.y-g.c.y,lx=(dx*co+dy*si)/g.k,ly=(-dx*si+dy*co)/g.k,sg=SGN[drag.i],lim=Math.max(g.w0,g.h0)*1.3,Q=getQ(selIt);Q[drag.i]=[Math.max(-lim,Math.min(lim,lx-sg[0]*g.w0/2)),Math.max(-lim,Math.min(lim,ly-sg[1]*g.h0/2))];pedir();return;}
if(drag.modo==='esc'){var f=Math.hypot(p.x-drag.cx,p.y-drag.cy)/drag.d0;if(isSp(selIt)){SP(selIt).k=Math.max(0.3,Math.min(3,drag.t0*f));}else{var nw=Math.max(40*UN,Math.min(520*UN,drag.t0*f));selIt.h=drag.h0*nw/drag.t0;selIt.w=nw;}syncSz();pedir();return;}
var nx=Math.max(X0+10,Math.min(RR,p.x-drag.ox)),ny=Math.max(YTOP+5,Math.min(Y1-10,p.y-drag.oy));if(isSp(selIt)){var bl=bounds(selIt),SS=SP(selIt);SS.dx+=nx-(bl.x+bl.w/2);SS.dy+=ny-(bl.y+bl.h/2);}else{selIt.x=nx;selIt.y=ny;}pedir();});
cv.addEventListener('dblclick',function(e){var p=pt(e),al=alca(p);if(selIt&&al&&al!=='rot'&&al!=='esc'&&al!=='del'){var H=isSp(selIt)?SP(selIt):selIt;H.q=null;pedir();}});
cv.addEventListener('pointerup',function(){drag=null;});cv.addEventListener('pointercancel',function(){drag=null;});
function lerArq(f,cb){var fr=new FileReader();fr.onload=function(){var im=new Image();im.onload=function(){cb(im);};im.src=fr.result;};fr.readAsDataURL(f);}
var lgin=document.getElementById('dcvx-lgin');
if(lgin){lgin.addEventListener('change',function(){var f=lgin.files&&lgin.files[0];if(!f){return;}lerArq(f,function(im){var l=limpa(im,true,1400);if(l){logoC=l;if(window.__lcorSync){window.__lcorSync('');}LG={dx:0,dy:0,k:1,r:0};selIt='logo';syncSz();pedir();}});lgin.value='';});}
var imp=document.getElementById('dcvx-imp');
if(imp){imp.addEventListener('change',function(){var f=imp.files&&imp.files[0];if(!f){return;}lerArq(f,function(im){var l=limpa(im,false);if(l){addSticker(adesivo(recorta(l)));}});imp.value='';});}
function addSticker(c,w0){var w=(w0||150)*UN,n=items.length,it={cv:c,w:w,h:w*c.height/c.width,x:PRD.lay.cx+((n%3)-1)*46*UN,y:PRD.lay.cy-42*UN+((n%4)-1.5)*40*UN};items.push(it);selIt=it;syncSz();pedir();}
var tx=document.getElementById('dcvx-tx'),tcor=document.getElementById('dcvx-tc'),tbt=document.getElementById('dcvx-tb');
function inserirTexto(){var t=(tx.value||'').trim();if(!t){tx.focus();return;}var c=document.createElement('canvas'),g=c.getContext('2d'),F='800 80px Poppins,Arial,sans-serif';g.font=F;var w=Math.ceil(g.measureText(t).width)+36,h=118;c.width=w;c.height=h;g=c.getContext('2d');g.font=F;g.textAlign='center';g.textBaseline='middle';g.lineJoin='round';g.lineWidth=12;g.strokeStyle='rgba(0,0,0,0.38)';g.strokeText(t,w/2,h/2+4);g.fillStyle=(tcor&&tcor.value)||'#FFFFFF';g.fillText(t,w/2,h/2+4);addSticker(c,Math.max(90,Math.min(320,w*0.42)));tx.value='';}
if(tbt&&tx){tbt.addEventListener('click',inserirTexto);tx.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();inserirTexto();}});}
var bdel=document.getElementById('dcvx-del'),brst=document.getElementById('dcvx-rst');
if(bdel){bdel.addEventListener('click',function(){if(selIt){apagaSel();}else if(items.length){items.pop();pedir();}});}
if(brst){brst.addEventListener('click',function(){LG={dx:0,dy:0,k:1,r:0};CG={dx:0,dy:0,k:1,r:0,off:false};TEX=null;if(BGON){AIMG=null;BGON=false;}logoC=null;if(window.__lcorSync){window.__lcorSync('');}items=[];selIt=null;syncSz();if(window.__voltaCurso){window.__voltaCurso();}pedir();});}
// biblioteca de stickers
function SV(i){return "<svg xmlns='http://www.w3.org/2000/svg' width='300' height='300' viewBox='0 0 100 100'>"+i+"</svg>";}
var HEART="M50 86 C10 58 6 30 24 20 C36 14 46 20 50 30 C54 20 64 14 76 20 C94 30 90 58 50 86Z";
function PL(t,a,b){var ls=t.split('|'),fs,mx=0,i,tx='';for(i=0;i<ls.length;i++){mx=Math.max(mx,ls[i].length);}fs=Math.min(ls.length>1?30:34,Math.floor(270/mx));
for(i=0;i<ls.length;i++){tx+="<text x='100' y='"+(ls.length>1?(46+i*28):(58))+"' text-anchor='middle' font-family='Arial,Helvetica,sans-serif' font-weight='900' font-size='"+fs+"' fill='#fff' stroke='#1F2937' stroke-width='1.2' paint-order='stroke'>"+ls[i]+"</text>";}
return "<svg xmlns='http://www.w3.org/2000/svg' width='400' height='200' viewBox='0 0 200 100'><defs><linearGradient id='g' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='"+a+"'/><stop offset='1' stop-color='"+b+"'/></linearGradient></defs><rect x='6' y='12' width='188' height='76' rx='32' fill='url(#g)' stroke='#1F2937' stroke-width='4'/><rect x='14' y='19' width='172' height='62' rx='26' fill='none' stroke='#fff' stroke-opacity='.55' stroke-width='2' stroke-dasharray='5 5'/>"+tx+"</svg>";}
var K="stroke='#1F2937' stroke-width='3' stroke-linejoin='round'";
var LIB=[
['Lápis','Escola',SV("<g transform='rotate(-40 50 50)'><rect x='38' y='14' width='24' height='58' rx='3' fill='#FBBF24' "+K+"/><polygon points='38,72 62,72 50,94' fill='#FDE7B0' "+K+"/><polygon points='46,86 54,86 50,94' fill='#1F2937'/><rect x='38' y='4' width='24' height='12' rx='3' fill='#F472B6' "+K+"/><rect x='38' y='14' width='24' height='6' fill='#9CA3AF' "+K+"/></g>")],
['Livro','Escola',SV("<path d='M50 24 C38 16 22 16 10 20 V78 C22 74 38 74 50 82 C62 74 78 74 90 78 V20 C78 16 62 16 50 24Z' fill='#fff' "+K+"/><path d='M50 24 V82' stroke='#1F2937' stroke-width='3'/><path d='M18 32 C28 30 38 31 44 35 M18 44 C28 42 38 43 44 47 M56 35 C62 31 72 30 82 32 M56 47 C62 43 72 42 82 44' stroke='#60A5FA' stroke-width='3' fill='none' stroke-linecap='round'/>")],
['Mochila','Escola',SV("<path d='M36 30 C36 10 64 10 64 30' fill='none' stroke='#1F2937' stroke-width='6'/><rect x='24' y='28' width='52' height='62' rx='20' fill='#EF4444' "+K+"/><rect x='34' y='58' width='32' height='24' rx='8' fill='#F87171' "+K+"/><path d='M34 70 H66' stroke='#1F2937' stroke-width='3'/><rect x='46' y='40' width='8' height='12' rx='3' fill='#FBBF24' stroke='#1F2937' stroke-width='2'/>")],
['Capelo','Escola',SV("<path d='M26 48 V66 C26 76 74 76 74 66 V48 L50 60Z' fill='#374151' stroke='#111827' stroke-width='3' stroke-linejoin='round'/><polygon points='50,20 94,40 50,60 6,40' fill='#1F2937' stroke='#111827' stroke-width='3' stroke-linejoin='round'/><path d='M88 43 V68' stroke='#FBBF24' stroke-width='3'/><circle cx='88' cy='72' r='5' fill='#FBBF24' stroke='#1F2937' stroke-width='2'/>")],
['Diploma','Escola',SV("<path d='M62 66 L56 88 L68 80 L80 88 L74 66' fill='#EF4444' "+K+"/><rect x='10' y='22' width='80' height='46' rx='8' fill='#FEF3C7' "+K+"/><path d='M22 38 H78 M22 49 H60' stroke='#B45309' stroke-width='3' stroke-linecap='round'/><circle cx='68' cy='62' r='9' fill='#EF4444' "+K+"/>")],
['Régua','Escola',SV("<g transform='rotate(-30 50 50)'><rect x='6' y='36' width='88' height='28' rx='5' fill='#FDE047' "+K+"/><path d='M20 36 V48 M32 36 V44 M44 36 V48 M56 36 V44 M68 36 V48 M80 36 V44' stroke='#1F2937' stroke-width='3'/></g>")],
['Lupa','Escola',SV("<circle cx='42' cy='42' r='26' fill='#DBEAFE' stroke='#1F2937' stroke-width='5'/><path d='M62 62 L88 88' stroke='#1F2937' stroke-width='11' stroke-linecap='round'/><path d='M30 34 C34 28 40 26 46 27' stroke='#fff' stroke-width='5' fill='none' stroke-linecap='round'/>")],
['Relógio','Escola',SV("<path d='M22 20 L12 30 M78 20 L88 30' stroke='#EF4444' stroke-width='7' stroke-linecap='round'/><circle cx='50' cy='54' r='34' fill='#fff' stroke='#1F2937' stroke-width='5'/><path d='M50 54 V32 M50 54 L66 62' stroke='#1F2937' stroke-width='5' stroke-linecap='round'/><circle cx='50' cy='54' r='4' fill='#EF4444'/>")],
['Sino','Escola',SV("<path d='M50 14 C32 14 26 30 26 46 C26 60 18 66 14 72 H86 C82 66 74 60 74 46 C74 30 68 14 50 14Z' fill='#FBBF24' "+K+"/><circle cx='50' cy='82' r='8' fill='#F59E0B' "+K+"/><circle cx='50' cy='10' r='4' fill='#FBBF24' "+K+"/>")],
['Medalha','Escola',SV("<polygon points='28,6 46,6 58,42 40,42' fill='#3B82F6' "+K+"/><polygon points='72,6 54,6 42,42 60,42' fill='#EF4444' "+K+"/><circle cx='50' cy='64' r='26' fill='#FBBF24' stroke='#1F2937' stroke-width='4'/><circle cx='50' cy='64' r='18' fill='#FDE047' stroke='#B45309' stroke-width='2'/><text x='50' y='72' text-anchor='middle' font-family='Arial' font-weight='900' font-size='22' fill='#B45309'>10</text>")],
['Estrela','Escola',SV("<path d='M50 8 L61 36 L92 38 L68 57 L76 88 L50 71 L24 88 L32 57 L8 38 L39 36Z' fill='#FBBF24' stroke='#1F2937' stroke-width='4' stroke-linejoin='round'/>")],
['Ideia','Escola',SV("<path d='M50 10 C30 10 22 26 24 40 C26 52 36 56 38 68 H62 C64 56 74 52 76 40 C78 26 70 10 50 10Z' fill='#FEF08A' stroke='#1F2937' stroke-width='4' stroke-linejoin='round'/><rect x='38' y='68' width='24' height='8' rx='3' fill='#9CA3AF' "+K+"/><rect x='41' y='78' width='18' height='8' rx='3' fill='#6B7280' "+K+"/><path d='M44 40 L50 52 L56 40' fill='none' stroke='#F59E0B' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/>")],
['Computador','Tecnologia',SV("<rect x='10' y='14' width='80' height='52' rx='7' fill='#60A5FA' stroke='#1F2937' stroke-width='4'/><rect x='17' y='21' width='66' height='38' rx='3' fill='#DBEAFE'/><path d='M40 66 L36 84 H64 L60 66' fill='#9CA3AF' "+K+"/><rect x='28' y='84' width='44' height='6' rx='3' fill='#6B7280' "+K+"/><path d='M26 34 H54 M26 44 H44' stroke='#2563EB' stroke-width='4' stroke-linecap='round'/>")],
['Mouse','Tecnologia',SV("<rect x='28' y='10' width='44' height='80' rx='22' fill='#F3F4F6' stroke='#1F2937' stroke-width='4'/><path d='M28 42 H72 M50 10 V42' stroke='#1F2937' stroke-width='4'/><rect x='46' y='20' width='8' height='14' rx='4' fill='#EF4444'/>")],
['Nuvem','Tecnologia',SV("<path d='M28 74 C12 74 8 54 24 50 C22 34 42 26 52 38 C62 26 84 34 80 52 C94 54 92 74 76 74Z' fill='#BFDBFE' stroke='#1F2937' stroke-width='4' stroke-linejoin='round'/>")],
['Engrenagem','Tecnologia',SV("<circle cx='50' cy='50' r='38' fill='none' stroke='#374151' stroke-width='11' stroke-dasharray='10 9.9'/><circle cx='50' cy='50' r='31' fill='#9CA3AF' stroke='#1F2937' stroke-width='4'/><circle cx='50' cy='50' r='11' fill='#fff' stroke='#1F2937' stroke-width='4'/>")],
['Cadeado','Tecnologia',SV("<path d='M30 44 V32 C30 10 70 10 70 32 V44' fill='none' stroke='#1F2937' stroke-width='9'/><rect x='20' y='42' width='60' height='46' rx='10' fill='#FBBF24' stroke='#1F2937' stroke-width='4'/><circle cx='50' cy='62' r='6' fill='#1F2937'/><rect x='47' y='64' width='6' height='14' rx='3' fill='#1F2937'/>")],
['Wi-Fi','Tecnologia',SV("<g fill='none' stroke-linecap='round'><path d='M10 38 C32 16 68 16 90 38 M24 52 C38 38 62 38 76 52 M38 66 C45 59 55 59 62 66' stroke='#1F2937' stroke-width='13'/><path d='M10 38 C32 16 68 16 90 38 M24 52 C38 38 62 38 76 52 M38 66 C45 59 55 59 62 66' stroke='#3B82F6' stroke-width='7'/></g><circle cx='50' cy='82' r='8' fill='#3B82F6' "+K+"/>")],
['Código','Tecnologia',SV("<rect x='8' y='20' width='84' height='60' rx='14' fill='#111827' stroke='#1F2937' stroke-width='3'/><text x='50' y='62' text-anchor='middle' font-family='Courier New,monospace' font-weight='900' font-size='34' fill='#34D399'>&lt;/&gt;</text>")],
['Paleta','Design',SV("<path d='M50 10 C24 10 8 30 10 52 C12 74 32 90 52 88 C62 87 62 78 56 72 C50 66 56 58 66 60 C84 64 94 54 92 42 C88 22 72 10 50 10Z' fill='#FDE68A' stroke='#1F2937' stroke-width='4' stroke-linejoin='round'/><circle cx='30' cy='42' r='7' fill='#EF4444'/><circle cx='46' cy='28' r='7' fill='#3B82F6'/><circle cx='66' cy='30' r='7' fill='#22C55E'/><circle cx='30' cy='64' r='7' fill='#A855F7'/>")],
['Câmera','Design',SV("<path d='M34 24 L40 14 H60 L66 24' fill='#6B7280' "+K+"/><rect x='8' y='24' width='84' height='60' rx='12' fill='#374151' stroke='#1F2937' stroke-width='4'/><circle cx='50' cy='54' r='20' fill='#DBEAFE' stroke='#1F2937' stroke-width='4'/><circle cx='50' cy='54' r='10' fill='#2563EB'/><circle cx='80' cy='36' r='4' fill='#FBBF24'/>")],
['Megafone','Design',SV("<path d='M16 38 H34 L74 14 V86 L34 62 H16Z' fill='#F87171' "+K+"/><rect x='18' y='62' width='14' height='24' rx='4' fill='#FBBF24' stroke='#1F2937' stroke-width='4'/><path d='M82 36 C90 44 90 56 82 64' fill='none' stroke='#1F2937' stroke-width='5' stroke-linecap='round'/>")],
['Coração','Design',SV("<path d='"+HEART+"' fill='#EF4444' stroke='#1F2937' stroke-width='4' stroke-linejoin='round'/><path d='M26 34 C30 28 36 27 40 30' stroke='#fff' stroke-width='5' fill='none' stroke-linecap='round'/>")],
['Balão','Festa',SV("<path d='M50 8 C24 8 20 38 34 54 C40 60 46 62 50 64 C54 62 60 60 66 54 C80 38 76 8 50 8Z' fill='#A855F7' stroke='#1F2937' stroke-width='4' stroke-linejoin='round'/><path d='M50 64 L44 72 H56Z' fill='#A855F7' "+K+"/><path d='M50 72 C44 80 56 84 50 94' fill='none' stroke='#1F2937' stroke-width='3' stroke-linecap='round'/><path d='M34 24 C36 18 42 15 46 15' stroke='#fff' stroke-width='5' fill='none' stroke-linecap='round'/>")],
['Novo','Festa',SV("<polygon points='50,4 60,22 80,12 78,34 98,40 82,54 94,74 72,74 68,96 50,82 32,96 28,74 6,74 18,54 2,40 22,34 20,12 40,22' fill='#EF4444' "+K+"/><text x='50' y='57' text-anchor='middle' font-family='Arial' font-weight='900' font-size='21' fill='#fff' stroke='#1F2937' stroke-width='1' paint-order='stroke'>NOVO!</text>")],
['Seta','Festa',SV("<path d='M10 38 H56 V18 L92 50 L56 82 V62 H10Z' fill='#22C55E' stroke='#1F2937' stroke-width='4' stroke-linejoin='round'/>")],
['Feliz','Emojis',SV("<circle cx='50' cy='50' r='40' fill='#FDE047' stroke='#1F2937' stroke-width='4'/><circle cx='36' cy='42' r='5' fill='#1F2937'/><circle cx='64' cy='42' r='5' fill='#1F2937'/><path d='M28 58 C34 78 66 78 72 58Z' fill='#7C2D12' "+K+"/>")],
['Apaixonado','Emojis',SV("<circle cx='50' cy='50' r='40' fill='#FDE047' stroke='#1F2937' stroke-width='4'/><g transform='translate(19 25) scale(.3)'><path d='"+HEART+"' fill='#EF4444'/></g><g transform='translate(51 25) scale(.3)'><path d='"+HEART+"' fill='#EF4444'/></g><path d='M30 62 C36 78 64 78 70 62' fill='none' stroke='#1F2937' stroke-width='4' stroke-linecap='round'/>")],
['Óculos','Emojis',SV("<circle cx='50' cy='50' r='40' fill='#FDE047' stroke='#1F2937' stroke-width='4'/><rect x='17' y='36' width='29' height='19' rx='7' fill='#111827'/><rect x='54' y='36' width='29' height='19' rx='7' fill='#111827'/><path d='M46 42 H54' stroke='#111827' stroke-width='4'/><path d='M30 66 C38 78 62 78 70 66' fill='none' stroke='#1F2937' stroke-width='4' stroke-linecap='round'/>")],
['Nota 10','Selos',SV("<circle cx='50' cy='50' r='44' fill='#FBBF24' stroke='#1F2937' stroke-width='4'/><circle cx='50' cy='50' r='35' fill='none' stroke='#fff' stroke-width='3' stroke-dasharray='5 5'/><text x='50' y='44' text-anchor='middle' font-family='Arial' font-weight='900' font-size='20' fill='#1F2937'>NOTA</text><text x='50' y='76' text-anchor='middle' font-family='Arial' font-weight='900' font-size='36' fill='#B91C1C'>10</text>")],
['Aluno nota 10','Selos',PL('ALUNO|NOTA 10','#3B82F6','#1D4ED8')],
['Aluno do mês','Selos',PL('ALUNO DO MÊS','#F59E0B','#D97706')],
['Melhor aluno','Selos',PL('MELHOR|ALUNO','#EAB308','#CA8A04')],
['Matrículas abertas','Selos',PL('MATRÍCULAS|ABERTAS','#F97316','#EA580C')],
['Eu estudo aqui','Selos',PL('EU ESTUDO|AQUI','#8B5CF6','#6D28D9')],
['Turma nova','Selos',PL('TURMA|NOVA','#10B981','#059669')],
['Formando','Selos',PL('FORMANDO','#1F2937','#111827')],
['Parabéns','Selos',PL('PARABÉNS!','#EC4899','#DB2777')],
['Aprovado','Selos',PL('APROVADO!','#22C55E','#16A34A')],
['Orgulho de estudar aqui','Selos',PL('ORGULHO DE|ESTUDAR AQUI','#0EA5E9','#0284C7')]
];
var stc=document.getElementById('dcvx-stc'),stg=document.getElementById('dcvx-st'),cat='',cats=[],cache={};
function uri(s){return 'data:image/svg+xml;charset=utf-8,'+encodeURIComponent(s);}
if(stc&&stg){
LIB.forEach(function(e){if(cats.indexOf(e[1])<0){cats.push(e[1]);}});
(['']).concat(cats).forEach(function(k){var b=document.createElement('button');b.type='button';b.textContent=k||'Todos';if(!k){b.className='on';}b.addEventListener('click',function(){cat=k;[].forEach.call(stc.children,function(x){x.classList.toggle('on',x===b);});lista();});stc.appendChild(b);});
}
function lista(){if(!stg){return;}stg.innerHTML='';LIB.forEach(function(e,i){if(cat&&e[1]!==cat){return;}var b=document.createElement('button');b.type='button';b.title=e[0];b.setAttribute('aria-label','Adicionar sticker '+e[0]);var im=document.createElement('img');im.alt='';im.src=uri(e[2]);b.appendChild(im);b.addEventListener('click',function(){if(cache[i]){addSticker(cache[i]);return;}var s=new Image();s.onload=function(){cache[i]=adesivo(s);addSticker(cache[i]);};s.src=uri(e[2]);});stg.appendChild(b);});}
lista();
// logo, foto e mascara
window.__prep=function(){ready=1;prep();};foto.onload=prep;mk.onload=prep;foto.src=D.foto;mk.src=D.mascara;
/* O logo NAO entra sozinho: o cliente escolhe (do computador ou o logo cadastrado da escola) */
function usaLogoEscola(){if(!D.escola.logo){return;}var lgi=new Image();lgi.onload=function(){var l=limpa(lgi,true,1400);if(l){logoC=l;if(window.__lcorSync){window.__lcorSync('');}LG={dx:0,dy:0,k:1,r:0};selIt='logo';syncSz();pedir();}};lgi.src=D.escola.logo;}
// busca e categorias de cursos
var gr=document.getElementById('dcvx-g'),q=document.getElementById('dcvx-q'),kk='',bs=document.querySelectorAll('.dcvx-bar button');
function f(){var t=(q.value||'').toLowerCase().trim();[].forEach.call(gr.children,function(e){var ok=(!kk||e.getAttribute('data-k')===kk)&&(!t||e.getAttribute('data-n').indexOf(t)>-1);e.style.display=ok?'':'none';});}
q.addEventListener('input',f);
[].forEach.call(bs,function(b){b.addEventListener('click',function(){kk=b.getAttribute('data-k');[].forEach.call(bs,function(x){x.classList.toggle('on',x===b);});f();});});
[].forEach.call(gr.children,function(b){b.addEventListener('click',function(){sel=+b.getAttribute('data-i');if(AIMG&&AHIDE){AIMG=null;var ax=document.getElementById('dcvx-arqx');if(ax){ax.style.display='none';}[].forEach.call(document.querySelectorAll('.dcvx-est button'),function(x){x.classList.remove('on');});}pedir();});});
var P=D.pedir||{},bq=document.getElementById('dcvx-qtd'),bt=document.getElementById('dcvx-tot'),bb=document.getElementById('dcvx-buy'),bx=document.getElementById('dcvx-buybox');
function fmt(v){return 'R$ '+v.toFixed(2).replace('.',',');}
function precoUn(q){var f=P.faixas,i,p;if(f&&f.length){p=f[0].p;for(i=0;i<f.length;i++){if(q>=f[i].q){p=f[i].p;}}return p;}return P.preco||0;}
function tot(){if(!bq||!bt){return;}var mn=P.min||1,q=Math.max(mn,Math.min(5000,parseInt(bq.value,10)||mn)),u=precoUn(q);bt.textContent=u?(q+' x '+fmt(u)+' = '+fmt(q*u)):'';}
var fxEl=null;if(bt&&bt.parentNode){fxEl=document.createElement('div');fxEl.id='dcvx-faixas';fxEl.style.cssText='margin:6px 0 0;font-size:12px;color:#667085;line-height:1.5;text-align:center';bt.parentNode.appendChild(fxEl);}
function aplicaPed(){P=PRD.tipo==='kit'?(D.pedirKit||window.DCVX_PEDIR_KIT||{}):(PRD.tipo==='pasta'?(D.pedirPasta||window.DCVX_PEDIR_PASTA||{}):(PRD.tipo==='bolsa'?(D.pedirBolsa||window.DCVX_PEDIR_BOLSA||{}):(PRD.tipo==='caneca2'?(D.pedirCaneca2||window.DCVX_PEDIR_CANECA2||{}):(D.pedir||{}))));if(bx){bx.style.display=P.ok?'':'none';}if(bb){bb.disabled=false;bb.textContent=(PRD.btn||'Pedir esta '+PRD.art);}
var mn=P.min||1;if(bq){bq.min=mn;bq.value=mn;}
if(fxEl){fxEl.innerHTML=(P.faixas&&P.faixas.length)?('<b>Pedido mínimo: '+mn+' unidades.</b> Preço por unidade: '+P.faixas.map(function(r){return r.q+'+ '+fmt(r.p);}).join(' · ')):'';}
tot();}
aplicaPed();(window.__prodHooks=window.__prodHooks||[]).push(aplicaPed);
if(bq){bq.addEventListener('input',tot);bq.addEventListener('change',function(){var mn=P.min||1;bq.value=Math.max(mn,Math.min(5000,parseInt(bq.value,10)||mn));tot();});tot();}
function blobDe(cn){return new Promise(function(ok){cn.toBlob(ok,'image/png');});}
function impressao(c){if(PRD.tipo!=='caneca'){var T=PRD.tam,ow=Math.round(T.larg/2.54*T.dpi),oh=Math.round(T.alt/2.54*T.dpi),SS=ow/(RR-X0),cq=artC(c,SS),oc=document.createElement('canvas');oc.width=ow;oc.height=oh;oc.getContext('2d').drawImage(cq,X0*SS,YTOP*SS,(RR-X0)*SS,(Y1-YTOP)*SS,0,0,ow,oh);return oc;}var S=3,cvp=artC(c,S),x0=X0,y0=YTOP,w=RR-X0,h=Y1-YTOP,o=document.createElement('canvas');o.width=w*S;o.height=h*S;o.getContext('2d').drawImage(cvp,x0*S,y0*S,w*S,h*S,0,0,w*S,h*S);return o;}
if(bb){bb.addEventListener('click',function(){
if(erro||!FD||!P.ok){alert('A prévia ainda não carregou. Tente de novo em instantes.');return;}
var q=Math.max(P.min||1,Math.min(5000,parseInt(bq.value,10)||(P.min||1))),c=D.cursos[sel]||SEM;
bb.disabled=true;bb.textContent='Enviando o pedido...';
showSel=false;draw();var cp=document.createElement('canvas');cp.width=N;cp.height=N;cp.getContext('2d').drawImage(cv,0,0);showSel=true;draw();
Promise.all([blobDe(cp),blobDe(impressao(c))]).then(function(bl){
var fd=new FormData();fd.append('action',P.action||'dcvx_pedir');fd.append('n',P.nonce);fd.append('qtd',q);fd.append('curso',(c.n||'').trim()||'Sem curso');fd.append('prod',PRD.tipo);fd.append('previa',bl[0],'previa.png');fd.append('arte',bl[1],'arte.png');
return fetch(P.url,{method:'POST',body:fd,credentials:'same-origin'});
}).then(function(r){return r.json();}).then(function(j){
if(j&&j.success&&j.data&&j.data.url){location.href=j.data.url;return;}
alert((j&&j.data&&j.data.msg)||'Não foi possível fazer o pedido. Tente de novo.');bb.disabled=false;bb.textContent=(PRD.btn||'Pedir esta '+PRD.art);
}).catch(function(){alert('Não foi possível enviar o pedido. Confira a conexão e tente de novo.');bb.disabled=false;bb.textContent=(PRD.btn||'Pedir esta '+PRD.art);});
});}
document.getElementById('dcvx-dl').addEventListener('click',function(){
if(erro||!FD){alert('A prévia ainda não carregou. Tente de novo em instantes.');return;}
showSel=false;draw();
cv.toBlob(function(bl){var u=URL.createObjectURL(bl),el=document.createElement('a');el.href=u;el.download='arte-'+PRD.art+'-'+(D.cursos[sel]||{n:'sem curso'}).n.toLowerCase().normalize('NFD').replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')+'.png';document.body.appendChild(el);el.click();setTimeout(function(){URL.revokeObjectURL(u);el.remove();},600);},'image/png');
showSel=true;draw();});
/* Layout do Gerador de Artes (conceito): 1 produto | previa | 2 curso, e cores do layout. So a caneca existe por enquanto. */
(function(){
var st=document.createElement('style');st.id='dcvx-css2';
st.textContent='.dcvx-w{grid-template-columns:minmax(0,170px) minmax(0,520px) minmax(0,1fr)}.dcvx-p canvas{max-width:none}.dcvx-g{max-height:340px;overflow:auto;padding:2px}.dcvx-s3{background:#fff;border:1px solid #E7E9F0;border-radius:20px;padding:14px;margin-top:14px}.dcvx-pr{background:#fff;border:1px solid #E7E9F0;border-radius:20px;padding:14px;align-self:start}.dcvx-hd3{margin:0 0 10px;font-size:15px;font-weight:800;color:#0B1F3F;display:flex;align-items:center;gap:8px}.dcvx-hd3 i{font-style:normal;width:24px;height:24px;border-radius:50%;background:#C9A24A;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px}.dcvx-pg{display:grid;grid-template-columns:1fr 1fr;gap:8px}.dcvx-pi{border:2px dashed #E1E4EA;border-radius:14px;padding:8px 4px;text-align:center;font-size:11px;font-weight:700;color:#98A2B3;line-height:1.2}.dcvx-pi small{display:block;font-weight:600;font-size:10px;margin-top:2px}.dcvx-pi.on{border:2px solid #C9A24A;background:#FFF9E8;color:#101828;box-shadow:0 0 0 3px rgba(201,162,74,.25)}.dcvx-pi .th{width:56px;height:56px;margin:0 auto 6px;border-radius:10px;background:#F4F5F8 no-repeat;background-size:190px 190px;background-position:-57px -73px}.dcvx-cores{align-items:center}.dcvx-sw{width:28px;height:28px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1px #D0D5DD;cursor:pointer;padding:0}.dcvx-sw.on,.dcvx-sw:hover{box-shadow:0 0 0 2px #C9A24A}@media(max-width:1100px){.dcvx-w{grid-template-columns:minmax(0,420px) minmax(0,1fr)}.dcvx-pr{grid-column:1/-1}}@media(max-width:900px){.dcvx-w{grid-template-columns:1fr}}';
document.head.appendChild(st);
var w=document.querySelector('.dcvx-w');
if(w){
var pr=document.createElement('div');pr.className='dcvx-pr';
var h='<h3 class="dcvx-hd3"><i>1</i>Produto</h3><div class="dcvx-pg"><div class="dcvx-pi" data-prod="kit"><div class="th" style="background:#fff url(/wp-content/uploads/2026/11/KIT-MATRICULA.jpg) no-repeat;background-size:150% auto;background-position:50% 70%;width:100%;height:100px;border-radius:10px;border:1px solid #E7E9F0;box-sizing:border-box"></div>Kit Matrícula<small style="color:#C9A24A">pasta + squeeze + bloco</small></div><div class="dcvx-pi on" data-prod="caneca"><div class="th" style="background:linear-gradient(#fff,#fff) 100% 0/32% 20% no-repeat,#fff url(/wp-content/uploads/2026/11/imgi_67_caneca-live.jpg) no-repeat;background-size:32% 20%,105% auto;background-position:100% 0,50% 82%;width:100%;height:100px;border-radius:10px;border:1px solid #E7E9F0;box-sizing:border-box"></div>Caneca</div><div class="dcvx-pi" data-prod="pasta"><div class="th" style="background:#fff url('+(PU.thumb||'/wp-content/uploads/2026/11/pasta-escolar.jpg')+') no-repeat;background-size:140% auto;background-position:48% 50%;width:100%;height:100px;border-radius:10px;border:1px solid #E7E9F0;box-sizing:border-box"></div>Pasta escolar</div><div class="dcvx-pi" data-prod="bolsa"><div class="th" style="background:#fff url(/wp-content/uploads/2026/11/pastabolsa.jpg) no-repeat;background-size:auto 149px;background-position:calc(50% + 5px) 50%;width:100%;height:100px;border-radius:10px;border:1px solid #E7E9F0;box-sizing:border-box"></div>Bolsa escolar</div><div class="dcvx-pi" data-prod="caneca2"><div class="th" style="background:#fff url(/wp-content/uploads/2026/11/caneca-2-2.jpg) no-repeat;background-size:auto 170px;background-position:calc(50% + 5px) calc(50% - 25px);width:100%;height:100px;border-radius:10px;border:1px solid #E7E9F0;box-sizing:border-box"></div>Caneca 2</div>';
['Garrafa','Copo térmico','Caderno','Sacola','Mochila','Caneta'].forEach(function(n){h+='<div class="dcvx-pi">'+n+'<small>em breve</small></div>';});
pr.innerHTML=h+'</div>';
[].forEach.call(pr.querySelectorAll('.dcvx-pi[data-prod]'),function(el){el.style.cursor='pointer';el.addEventListener('click',function(){[].forEach.call(pr.querySelectorAll('.dcvx-pi'),function(x){x.classList.toggle('on',x===el);});setProd(el.getAttribute('data-prod'));});});
(window.__prodHooks=window.__prodHooks||[]).push(function(){var ax=document.getElementById('dcvx-arqx');if(ax){ax.style.display='none';}[].forEach.call(document.querySelectorAll('.dcvx-est button'),function(x){x.classList.remove('on');});});
w.insertBefore(pr,w.firstChild);
if(/[?&]prod=kit(&|$)/.test(location.search)){var pk=pr.querySelector('[data-prod="kit"]');if(pk){pk.click();}}
var bar=w.querySelector('.dcvx-bar');
if(bar){var t=document.createElement('h3');t.className='dcvx-hd3';t.innerHTML='<i>2</i>Escolha o curso';bar.parentNode.insertBefore(t,bar);}
var pn=w.querySelector('.dcvx-p'),dir=bar&&bar.parentNode;
if(pn&&dir){var s3=document.createElement('div');s3.className='dcvx-s3';s3.innerHTML='<h3 class="dcvx-hd3"><i>3</i>Personalize e gere a arte</h3>';
var dlb=document.getElementById('dcvx-dl'),bb2=document.getElementById('dcvx-buybox');
[].slice.call(pn.children).forEach(function(e){if(e.tagName!=='CANVAS'&&e!==dlb&&e!==bb2){s3.appendChild(e);}});
if(bb2){s3.appendChild(bb2);}if(dlb){s3.appendChild(dlb);}
dir.appendChild(s3);}
}
var dl=document.getElementById('dcvx-dl');if(dl){dl.textContent='Gerar arte do produto (baixar PNG)';}
var stc2=document.getElementById('dcvx-stc');
if(stc2&&stc2.parentNode){
var box2=document.createElement('div');box2.className='dcvx-ctl dcvx-cores';
box2.innerHTML='<span>Cores do layout</span>';
function esc2(hx){var n=parseInt(hx.slice(1),16),r=(n>>16)&255,g=(n>>8)&255,b=n&255;return 'rgb('+Math.round(r*0.62)+','+Math.round(g*0.62)+','+Math.round(b*0.62)+')';}
var lista2=[[null,'Cor do curso (padrão)'],['#C9A24A','Dourado'],['#0B1F3F','Azul-marinho'],['#C0392B','Vermelho'],['#344054','Cinza']];
lista2.forEach(function(it,ix){var b=document.createElement('button');b.type='button';b.className='dcvx-sw'+(ix===0?' on':'');b.title=it[1];b.setAttribute('aria-label',it[1]);
b.style.background=it[0]?it[0]:'conic-gradient(#226BCB,#C0392B,#C9A24A,#226BCB)';
b.addEventListener('click',function(){COR=it[0]?{cor:it[0],esc:esc2(it[0])}:null;[].forEach.call(box2.querySelectorAll('.dcvx-sw:not(.dcvx-lsw)'),function(x){x.classList.toggle('on',x===b);});if(window.__hexSync){window.__hexSync(it[0]||'');}pedir();});
box2.appendChild(b);});
var hx=document.createElement('div');hx.style.cssText='display:flex;align-items:center;gap:6px;flex-basis:100%;justify-content:center;margin-top:6px';
hx.innerHTML='<label for="dcvx-hex" style="font-size:12.5px;color:#344054;white-space:nowrap">Código da cor</label><input type="text" id="dcvx-hex" placeholder="#RRGGBB" maxlength="7" autocomplete="off" spellcheck="false" style="flex:0 0 96px!important;width:96px!important;min-width:0;text-transform:uppercase;text-align:center;border:1px solid #D0D5DD;border-radius:10px;padding:8px 6px;font:600 13px/1 Poppins,system-ui,sans-serif;letter-spacing:.5px"><input type="color" id="dcvx-hexc" value="#226bcb" aria-label="Escolher a cor" style="flex:0 0 40px;width:40px;height:36px;padding:2px;border:1px solid #D0D5DD;border-radius:10px;background:#fff;cursor:pointer">';
box2.appendChild(hx);
(function(){var ti=hx.querySelector('#dcvx-hex'),ci=hx.querySelector('#dcvx-hexc');function norm(v){v=String(v||'').trim().replace(/^#?/,'#');if(/^#[0-9a-fA-F]{3}$/.test(v)){v='#'+v[1]+v[1]+v[2]+v[2]+v[3]+v[3];}return /^#[0-9a-fA-F]{6}$/.test(v)?v.toUpperCase():null;}
function aplica(v){COR={cor:v,esc:esc2(v)};[].forEach.call(box2.querySelectorAll('.dcvx-sw:not(.dcvx-lsw)'),function(x){x.classList.remove('on');});ci.value=v.toLowerCase();pedir();}
ti.addEventListener('input',function(){var v=norm(ti.value);ti.style.borderColor=(!ti.value||v)?'#D0D5DD':'#D92D20';if(v&&ti.value.length>=4){aplica(v);}});
ti.addEventListener('blur',function(){var v=norm(ti.value);if(v){ti.value=v;}});
ci.addEventListener('input',function(){var v=norm(ci.value);if(v){ti.value=v;ti.style.borderColor='#D0D5DD';aplica(v);}});
window.__hexSync=function(v){ti.value=v?v.toUpperCase():'';ti.style.borderColor='#D0D5DD';if(v){ci.value=v.toLowerCase();}};})();
var lc=document.createElement('div');lc.style.cssText='display:flex;align-items:center;gap:6px;flex-basis:100%;justify-content:center;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid #EEF0F4';
lc.innerHTML='<span style="font-size:12.5px;color:#344054;white-space:nowrap">Cor do logo</span>';
(function(){var lista=[['','Cor original do logo'],['#FFFFFF','Branco'],['#000000','Preto']],bts=[];
lista.forEach(function(it,ix){var b=document.createElement('button');b.type='button';b.className='dcvx-sw dcvx-lsw'+(ix===0?' on':'');b.title=it[1];b.setAttribute('aria-label','Logo: '+it[1]);b.style.background=it[0]?it[0]:'conic-gradient(#226BCB,#C0392B,#C9A24A,#226BCB)';if(it[0]==='#FFFFFF'){b.style.boxShadow='inset 0 0 0 1px #D0D5DD';}
b.addEventListener('click',function(){aplicaL(it[0]);});lc.appendChild(b);bts.push(b);});
var ti=document.createElement('input');ti.type='text';ti.id='dcvx-lhex';ti.placeholder='#RRGGBB';ti.maxLength=7;ti.autocomplete='off';ti.spellcheck=false;ti.setAttribute('aria-label','Código da cor do logo');ti.style.cssText='flex:0 0 96px;width:96px;min-width:0;text-transform:uppercase;text-align:center;border:1px solid #D0D5DD;border-radius:10px;padding:8px 6px;font:600 13px/1 Poppins,system-ui,sans-serif;letter-spacing:.5px';
var ci=document.createElement('input');ci.type='color';ci.id='dcvx-lhexc';ci.value='#226bcb';ci.setAttribute('aria-label','Escolher a cor do logo');ci.style.cssText='flex:0 0 40px;width:40px;height:36px;padding:2px;border:1px solid #D0D5DD;border-radius:10px;background:#fff;cursor:pointer';
lc.appendChild(ti);lc.appendChild(ci);
function norm(v){v=String(v||'').trim().replace(/^#?/,'#');if(/^#[0-9a-fA-F]{3}$/.test(v)){v='#'+v[1]+v[1]+v[2]+v[2]+v[3]+v[3];}return /^#[0-9a-fA-F]{6}$/.test(v)?v.toUpperCase():null;}
function marca(v){bts.forEach(function(b,i){b.classList.toggle('on',(lista[i][0]||'')===(v||''));});}
function aplicaL(v){LCOR=v||null;ti.value=v&&v!=='#FFFFFF'&&v!=='#000000'?v:(v||'');if(v){ci.value=v.toLowerCase();}ti.style.borderColor='#D0D5DD';marca(v);pedir();}
ti.addEventListener('input',function(){var v=norm(ti.value);ti.style.borderColor=(!ti.value||v)?'#D0D5DD':'#D92D20';if(v&&ti.value.length>=4){LCOR=v;ci.value=v.toLowerCase();marca('');pedir();}});
ti.addEventListener('blur',function(){var v=norm(ti.value);if(v){ti.value=v;}});
ci.addEventListener('input',function(){var v=norm(ci.value);if(v){ti.value=v;LCOR=v;marca('');pedir();}});
window.__lcorSync=function(v){LCOR=v||null;ti.value=v||'';ti.style.borderColor='#D0D5DD';marca(v);};})();
box2.appendChild(lc);
stc2.parentNode.insertBefore(box2,stc2);
}
})();
/* Teclado: setas movem o item selecionado (Shift = passo maior); Delete apaga o sticker */
document.addEventListener('keydown',function(e){var t=e.target&&e.target.tagName;if(t==='INPUT'||t==='TEXTAREA'||!selIt){return;}var d=(e.shiftKey?10:3)*UN,k=e.key;
if(k==='Delete'||k==='Backspace'){e.preventDefault();apagaSel();return;}
var dx=k==='ArrowLeft'?-d:(k==='ArrowRight'?d:0),dy=k==='ArrowUp'?-d:(k==='ArrowDown'?d:0);if(!dx&&!dy){return;}e.preventDefault();
if(isSp(selIt)){SP(selIt).dx+=dx;SP(selIt).dy+=dy;}else{selIt.x=Math.max(X0+10,Math.min(RR,selIt.x+dx));selIt.y=Math.max(YTOP+5,Math.min(Y1-10,selIt.y+dy));}pedir();});
/* Estampas prontas (fundos desenhados por codigo) */
function listras(a){a.strokeStyle='rgba(255,255,255,0.12)';a.lineWidth=34;for(var k=-N;k<2*N;k+=70){a.beginPath();a.moveTo(k,0);a.lineTo(k+N,N);a.stroke();}}
function rnd(s){return function(){s=(s*1664525+1013904223)%4294967296;return s/4294967296;};}
var PAT=[
{n:'Lisa',fn:function(a){}},
{n:'Listras',fn:listras},
{n:'Bolinhas',fn:function(a){a.fillStyle='rgba(255,255,255,0.18)';for(var y=0;y<N;y+=46){for(var x=((y/46)%2)*23;x<N;x+=46){a.beginPath();a.arc(x,y,9,0,6.2832);a.fill();}}}},
{n:'Ondas',fn:function(a){a.strokeStyle='rgba(255,255,255,0.18)';a.lineWidth=6;for(var y=0;y<N;y+=40){a.beginPath();a.moveTo(0,y);for(var x=0;x<=N;x+=10){a.lineTo(x,y+Math.sin(x/28)*9);}a.stroke();}}},
{n:'Hexágonos',fn:function(a){var r=34,dx=r*1.5,dy=r*1.732,c,l,x,y,i;a.strokeStyle='rgba(255,255,255,0.2)';a.lineWidth=3;for(c=0;c*dx<N+dx;c++){for(l=-1;l*dy<N+dy;l++){x=c*dx;y=l*dy+(c%2?dy/2:0);a.beginPath();for(i=0;i<6;i++){a.lineTo(x+r*Math.cos(i*1.0472),y+r*Math.sin(i*1.0472));}a.closePath();a.stroke();}}}},
{n:'Xadrez',fn:function(a){a.fillStyle='rgba(255,255,255,0.1)';for(var y=0;y<N;y+=56){for(var x=((y/56)%2)*56;x<N;x+=112){a.fillRect(x,y,56,56);}}}},
{n:'Raios',fn:function(a){a.fillStyle='rgba(255,255,255,0.1)';for(var i=0;i<24;i+=2){a.beginPath();a.moveTo(460,600);a.arc(460,600,1400,i*0.2618,(i+1)*0.2618);a.closePath();a.fill();}}},
{n:'Grade',fn:function(a){a.strokeStyle='rgba(255,255,255,0.2)';a.lineWidth=2;for(var k=0;k<N;k+=40){a.beginPath();a.moveTo(k,0);a.lineTo(k,N);a.moveTo(0,k);a.lineTo(N,k);a.stroke();}}},
{n:'Circuito',fn:function(a){var r=rnd(7),i,x,y,l,v;a.strokeStyle='rgba(255,255,255,0.22)';a.fillStyle='rgba(255,255,255,0.28)';a.lineWidth=4;for(i=0;i<110;i++){x=Math.round(r()*N/30)*30;y=Math.round(r()*N/30)*30;l=(1+Math.floor(r()*4))*30;v=r()<0.5;a.beginPath();a.moveTo(x,y);if(v){a.lineTo(x,y+l);}else{a.lineTo(x+l,y);}a.stroke();a.beginPath();a.arc(v?x:x+l,v?y+l:y,6,0,6.2832);a.fill();}}},
{n:'Confete',fn:function(a){var r=rnd(11),i;for(i=0;i<150;i++){a.save();a.translate(r()*N,r()*N);a.rotate(r()*6.28);a.fillStyle='rgba(255,255,255,'+(0.12+r()*0.18)+')';a.fillRect(-8,-4,16,8);a.restore();}}},
{n:'Zigue-zague',fn:function(a){a.strokeStyle='rgba(255,255,255,0.18)';a.lineWidth=8;for(var y=0;y<N;y+=50){a.beginPath();a.moveTo(0,y);for(var x=0;x<=N;x+=40){a.lineTo(x+20,y+((x/40)%2?14:-14));}a.stroke();}}},
{n:'Losangos',fn:function(a){a.strokeStyle='rgba(255,255,255,0.2)';a.lineWidth=4;for(var y=0;y<N+60;y+=60){for(var x=((y/60)%2)*30;x<N+60;x+=60){a.beginPath();a.moveTo(x,y-26);a.lineTo(x+26,y);a.lineTo(x,y+26);a.lineTo(x-26,y);a.closePath();a.stroke();}}}},
{n:'Pontilhado',fn:function(a){a.fillStyle='rgba(255,255,255,0.22)';for(var y=0;y<N;y+=18){for(var x=0;x<N;x+=18){a.beginPath();a.arc(x,y,2.5,0,6.2832);a.fill();}}}}
];
/* Layout: esquerda = produto + cursos; meio = produto grande; direita = estampas, enviar arte e personalizar */
(function(){
var w=document.querySelector('.dcvx-w');if(!w){return;}
var pr=w.querySelector('.dcvx-pr'),pn=w.querySelector('.dcvx-p'),s3=w.querySelector('.dcvx-s3'),bar=w.querySelector('.dcvx-bar'),g2=document.getElementById('dcvx-g');
if(!pr||!pn||!s3||!bar||!g2){return;}
var st=document.createElement('style');st.id='dcvx-css3';
st.textContent='.dcvx-w{grid-template-columns:minmax(0,430px) minmax(0,1fr) minmax(0,390px);align-items:start}.dcvx-w .dcvx-p canvas{max-width:625px!important}.dcvx-L,.dcvx-R{display:flex;flex-direction:column;gap:14px;min-width:0}.dcvx-L .dcvx-pr{padding:14px}.dcvx-pg{grid-template-columns:repeat(3,1fr)}.dcvx-L .dcvx-g{grid-template-columns:repeat(4,1fr);gap:6px;max-height:440px}.dcvx-L .dcvx-t{padding:3px;border-width:2px;border-radius:12px;gap:0}.dcvx-L .dcvx-t img{border-radius:9px}.dcvx-L .dcvx-t b,.dcvx-L .dcvx-t small,.dcvx-L .dcvx-t .go{display:none}.dcvx-L .dcvx-bar{margin:0 0 10px}.dcvx-card{background:#fff;border:1px solid #E7E9F0;border-radius:20px;padding:14px}.dcvx-R .dcvx-s3{margin-top:0}.dcvx-est{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}.dcvx-est button,.dcvx-est button:hover,.dcvx-est button:focus{padding:0;border:2px solid #E7E9F0;border-radius:10px;overflow:hidden;cursor:pointer;background:#fff;line-height:0}.dcvx-est button.on{border-color:#C9A24A;box-shadow:0 0 0 2px rgba(201,162,74,.35)}.dcvx-est canvas{width:100%;height:auto;display:block}.dcvx-p .dcvx-hd3{justify-content:center;margin:0 0 10px}@media(max-width:1250px){.dcvx-w{grid-template-columns:minmax(0,300px) minmax(0,1fr) minmax(0,310px);gap:14px}}@media(max-width:980px){.dcvx-w{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.dcvx-R{grid-column:1/-1}}@media(max-width:900px){.dcvx-w{grid-template-columns:1fr}.dcvx-L .dcvx-g{grid-template-columns:repeat(3,1fr)}}';
document.head.appendChild(st);
var L=document.createElement('div');L.className='dcvx-L';var R=document.createElement('div');R.className='dcvx-R';
var dir=bar.parentNode,h2=dir.querySelector('.dcvx-hd3');
L.appendChild(pr);
/* Tamanho da arte do produto escolhido (MEDIDA PROVISORIA: confirmar com a grafica) + gabarito para baixar */
var TAM=PRD.tam,tpx=Math.round(TAM.larg/2.54*TAM.dpi),tpy=Math.round(TAM.alt/2.54*TAM.dpi);
var stT=document.createElement('style');stT.id='dcvx-css5';
stT.textContent='.dcvx-tam{margin-top:12px;background:#FFF9E8;border:1px solid #F0DFA8;border-radius:12px;padding:10px;font-size:12px;color:#344054;line-height:1.45}.dcvx-tam b{display:block;color:#0B1F3F;font-size:13px;margin-bottom:2px}.dcvx-tam .m{font-size:15px;font-weight:800;color:#0B1F3F}.dcvx-tam button{margin-top:8px}';
document.head.appendChild(stT);
var tam=document.createElement('div');tam.className='dcvx-tam';
function tamUI(){TAM=PRD.tam;tpx=Math.round(TAM.larg/2.54*TAM.dpi);tpy=Math.round(TAM.alt/2.54*TAM.dpi);var up=document.getElementById('dcvx-uptam');if(up){up.textContent=String(TAM.larg).replace('.',',')+' x '+String(TAM.alt).replace('.',',')+' cm ('+tpx+' x '+tpy+' px).';}
tam.innerHTML='<b>Tamanho da arte · '+TAM.nome+'</b><span class="m">'+String(TAM.larg).replace('.',',')+' x '+String(TAM.alt).replace('.',',')+' cm</span><br>'+tpx+' x '+tpy+' px a '+TAM.dpi+' dpi<br>Envie sua arte nessa medida para ela sair certa no produto.'+(TAM.provisoria?'<br><em>Medida provisória, a confirmar com a gráfica.</em>':'')+'<br><button type="button" class="dcvx-b" id="dcvx-gab">Baixar gabarito (PNG)</button>';}
pr.appendChild(tam);tamUI();(window.__prodHooks=window.__prodHooks||[]).push(tamUI);
tam.addEventListener('click',function(ev){if(!ev.target||ev.target.id!=='dcvx-gab'){return;}var c=document.createElement('canvas');c.width=tpx;c.height=tpy;var g=c.getContext('2d'),m=Math.round(tpy*0.04);
g.fillStyle='#FFFFFF';g.fillRect(0,0,tpx,tpy);g.strokeStyle='#C9A24A';g.lineWidth=Math.max(4,Math.round(tpy/200));g.strokeRect(g.lineWidth/2,g.lineWidth/2,tpx-g.lineWidth,tpy-g.lineWidth);
g.setLineDash([Math.round(tpy/40),Math.round(tpy/60)]);g.strokeStyle='#98A2B3';g.strokeRect(m,m,tpx-2*m,tpy-2*m);g.beginPath();g.moveTo(tpx/2,0);g.lineTo(tpx/2,tpy);g.moveTo(0,tpy/2);g.lineTo(tpx,tpy/2);g.stroke();g.setLineDash([]);
g.fillStyle='#667085';g.textAlign='center';g.textBaseline='middle';g.font='700 '+Math.round(tpy/14)+'px Arial,sans-serif';
g.fillText('GABARITO · '+TAM.nome.toUpperCase(),tpx/2,tpy*0.40);g.font='600 '+Math.round(tpy/18)+'px Arial,sans-serif';
g.fillText(String(TAM.larg).replace('.',',')+' x '+String(TAM.alt).replace('.',',')+' cm · '+tpx+' x '+tpy+' px · '+TAM.dpi+' dpi',tpx/2,tpy*0.52);
g.fillText('Linha tracejada = margem de segurança. Apague esta camada e desenhe sua arte.',tpx/2,tpy*0.62);
c.toBlob(function(bl){var u=URL.createObjectURL(bl),el=document.createElement('a');el.href=u;el.download='gabarito-'+PRD.art+'-'+tpx+'x'+tpy+'.png';document.body.appendChild(el);el.click();setTimeout(function(){URL.revokeObjectURL(u);el.remove();},600);},'image/png');});
var cc=document.createElement('div');cc.className='dcvx-card';
if(h2){h2.innerHTML='<i>2</i>Sua estampa com os cursos';cc.appendChild(h2);}
cc.appendChild(bar);cc.appendChild(g2);L.appendChild(cc);
[].forEach.call(g2.children,function(t){var b=t.querySelector('b');if(b){t.title=b.textContent;}});
/* meio: titulo + caneca grande + pedido */
var mt=document.createElement('h3');mt.className='dcvx-hd3';mt.innerHTML='<i>1</i>Olha como está ficando seu produto!';pn.insertBefore(mt,pn.firstChild);
var bb3=document.getElementById('dcvx-buybox'),dl3=document.getElementById('dcvx-dl');
if(bb3){pn.appendChild(bb3);}if(dl3){pn.appendChild(dl3);}
if(bb3){var hc=document.createElement('div');hc.id='dcvx-comprar';hc.textContent='Comprar agora!';hc.style.cssText='flex:1 1 100%;font-size:20px;font-weight:800;color:#0B1F3F;text-align:center;margin:0 0 2px';bb3.insertBefore(hc,bb3.firstChild);}
/* Zoom na arte: aproxima o produto para ver os detalhes sem sair da edicao (rolar/arrastar com a mao para mover) */
var zw=document.createElement('div');zw.id='dcvx-zw';zw.style.cssText='max-width:625px;margin:0 auto;aspect-ratio:1/1;overflow:hidden;border-radius:12px;background:#fff;scrollbar-width:none';
var zst=document.createElement('style');zst.id='dcvx-css10';zst.textContent='#dcvx-zw::-webkit-scrollbar{display:none}';document.head.appendChild(zst);
cv.parentNode.insertBefore(zw,cv);zw.appendChild(cv);
var zbar=document.createElement('div');zbar.id='dcvx-zbar';zbar.style.cssText='display:flex;gap:6px;justify-content:center;align-items:center;margin:8px 0 0;flex-wrap:wrap';
zbar.innerHTML='<button type="button" class="dcvx-b" id="dcvx-zm" aria-label="Diminuir zoom">−</button><span id="dcvx-zv" style="min-width:48px;text-align:center;font-weight:700;font-size:13px;color:#344054">100%</span><button type="button" class="dcvx-b" id="dcvx-zp" aria-label="Aumentar zoom">+</button><button type="button" class="dcvx-b" id="dcvx-z1">Ajustar</button><button type="button" class="dcvx-b" id="dcvx-zh" title="Com a mão ligada, arraste a imagem para mover. Desligada, arraste o logo, textos e stickers.">✋ Mover</button>';
zw.parentNode.insertBefore(zbar,zw.nextSibling);
var zdica=document.createElement('p');zdica.style.cssText='margin:6px 8px 0;font-size:11.5px;color:#667085;text-align:center;line-height:1.45';zdica.innerHTML='Clique no logo, no nome do curso ou num sticker. <b style="color:#E53935">Pontos vermelhos</b>: ajustam cada canto (encaixe na superfície; dois cliques num ponto desfaz). <b style="color:#1E6FD9">Quadrado azul</b>: aumenta e diminui. Círculo de cima: gira.';zbar.parentNode.insertBefore(zdica,zbar.nextSibling);
var ZV=1,ZMAO=false,zi=null;
function zoom(v,fx,fy){var o=ZV;v=Math.max(1,Math.min(4,v));if(v===o){return;}var cw=zw.clientWidth,ch=zw.clientHeight,cx0=(zw.scrollLeft+(fx==null?cw/2:fx))/o,cy0=(zw.scrollTop+(fy==null?ch/2:fy))/o;ZV=v;zw.style.overflow=v>1?'auto':'hidden';zw.style.overscrollBehavior=v>1?'contain':'';
cv.style.setProperty('max-width','none','important');cv.style.setProperty('width',(v*100)+'%','important');cv.style.height='auto';
zw.scrollLeft=cx0*v-(fx==null?cw/2:fx);zw.scrollTop=cy0*v-(fy==null?ch/2:fy);
document.getElementById('dcvx-zv').textContent=Math.round(v*100)+'%';if(v===1){cv.style.removeProperty('max-width');cv.style.removeProperty('width');}}
document.getElementById('dcvx-zp').addEventListener('click',function(){zoom(ZV*1.5);});
document.getElementById('dcvx-zm').addEventListener('click',function(){zoom(ZV/1.5);});
document.getElementById('dcvx-z1').addEventListener('click',function(){zoom(1);});
function maoLiga(l){ZMAO=l;var zb=document.getElementById('dcvx-zh');zb.style.background=l?'#FFF4D6':'';zb.style.borderColor=l?'#C9A24A':'';cv.style.cursor=l?'grab':'';if(l&&selIt){selIt=null;syncSz();pedir();}}
document.getElementById('dcvx-zh').addEventListener('click',function(){maoLiga(!ZMAO);});
zw.addEventListener('wheel',function(e){if(!e.ctrlKey&&!e.metaKey){return;}e.preventDefault();var r=zw.getBoundingClientRect();zoom(ZV*(e.deltaY<0?1.25:0.8),e.clientX-r.left,e.clientY-r.top);},{passive:false});
/* mao: arrasta a imagem em vez de editar (captura antes do editor) */
cv.addEventListener('pointerdown',function(e){if(!ZMAO||ZV<=1){return;}e.stopImmediatePropagation();zi={x:e.clientX,y:e.clientY,l:zw.scrollLeft,t:zw.scrollTop};try{cv.setPointerCapture(e.pointerId);}catch(x){}cv.style.cursor='grabbing';},true);
cv.addEventListener('pointermove',function(e){if(!zi){return;}e.stopImmediatePropagation();zw.scrollLeft=zi.l-(e.clientX-zi.x);zw.scrollTop=zi.t-(e.clientY-zi.y);},true);
cv.addEventListener('pointerup',function(e){if(zi){zi=null;e.stopImmediatePropagation();maoLiga(false);}},true);
cv.addEventListener('pointercancel',function(){if(zi){zi=null;maoLiga(false);}},true);
window.__zoom=function(v){zoom(v);return ZV;};
/* direita 3: estampas prontas */
var ce=document.createElement('div');ce.className='dcvx-card';ce.innerHTML='<h3 class="dcvx-hd3"><i>3</i>Estampas prontas para brilhar</h3>';
var ge=document.createElement('div');ge.className='dcvx-est';
PAT.forEach(function(p,ix){return;var b=document.createElement('button');b.type='button';b.title='Padrão inicial';b.setAttribute('aria-label','Estampa padrão inicial');if(ix===1){b.className='on';}
var c=document.createElement('canvas');c.width=96;c.height=116;var a=c.getContext('2d');a.scale(0.2975,0.2975);a.translate(-297,-410);
var gg=a.createLinearGradient(0,0,N*0.4,N);gg.addColorStop(0,'#226BCB');gg.addColorStop(1,'#15427D');a.fillStyle=gg;a.fillRect(0,0,N,N);p.fn(a);
b.appendChild(c);b.addEventListener('click',function(){EST=ix===1?null:p;AIMG=null;var ax=document.getElementById('dcvx-arqx');if(ax){ax.style.display='none';}[].forEach.call(document.querySelectorAll('.dcvx-estr button'),function(x){x.classList.remove('on');});[].forEach.call(ge.children,function(x){x.classList.toggle('on',x===b);});pedir();});ge.appendChild(b);});
ce.appendChild(ge);R.appendChild(ce);
/* Estampas da Rede (criadas pelo marketing da Matriz; chegam em window.DCVX_EST) */
(function(){var LE=(window.DCVX_EST||[]).filter(function(e){return !e.tex;});if(!LE.length){ge.style.display='block';ge.innerHTML='<p style="margin:4px 0;font-size:12.5px;color:#667085">As estampas da rede aparecem aqui.</p>';return;}
var sty=document.createElement('style');sty.id='dcvx-css6';sty.textContent='.dcvx-est button img{width:100%;aspect-ratio:323/392;object-fit:cover;display:block;border-radius:8px}';
document.head.appendChild(sty);
var gr=ge;
LE.forEach(function(e){var b=document.createElement('button');b.type='button';b.title=e.n;b.setAttribute('aria-label','Estampa da rede '+e.n);var im=document.createElement('img');im.src=e.c;im.alt='';im.loading='lazy';b.appendChild(im);
b.addEventListener('click',function(){if(b.classList.contains('on')){AIMG=null;b.classList.remove('on');pedir();return;}var o=new Image();o.onload=function(){limpaTex();window.__useBg(o,!e.nome);
var ax=document.getElementById('dcvx-arqx');if(ax){ax.style.display='none';}[].forEach.call(ge.children,function(x){x.classList.remove('on');});[].forEach.call(gr.children,function(x){x.classList.toggle('on',x===b);});pedir();};o.src=e.u;});gr.appendChild(b);});
})();
/* direita 4: enviar a propria arte */
var cu=document.createElement('div');cu.className='dcvx-card';cu.innerHTML='<h3 class="dcvx-hd3"><i>4</i>Pode enviar sua arte</h3><div class="dcvx-ctl" style="margin-top:0;justify-content:flex-start"><label class="dcvx-b">Escolher arquivo<input type="file" id="dcvx-arq" accept="image/*" hidden></label><button type="button" class="dcvx-b" id="dcvx-arqx" style="display:none">Remover arte enviada</button></div><p style="margin:8px 0 0;font-size:12px;color:#667085">Ela cobre a caneca no lugar do fundo do curso. O logo, os textos e os stickers continuam por cima. Medida ideal: <span id="dcvx-uptam">'+String(TAM.larg).replace('.',',')+' x '+String(TAM.alt).replace('.',',')+' cm ('+tpx+' x '+tpy+' px).</span></p>';
R.appendChild(cu);
var h3s=s3.querySelector('.dcvx-hd3');if(h3s){h3s.innerHTML='<i>5</i>Personalize e gere a arte';}
R.appendChild(s3);
while(w.firstChild){w.removeChild(w.firstChild);}
w.appendChild(L);w.appendChild(pn);w.appendChild(R);
/* Garante as 3 colunas mesmo que o tema da pagina sobrescreva o CSS (estilo inline com !important) */
function colunas(){var W=window.innerWidth;if(W>980){w.style.setProperty('display','grid','important');w.style.setProperty('grid-template-columns',W>1250?'minmax(0,430px) minmax(0,1fr) minmax(0,390px)':'minmax(0,300px) minmax(0,1fr) minmax(0,310px)','important');L.style.gridArea='1/1';pn.style.gridArea='1/2';R.style.gridArea='1/3';}else{w.style.removeProperty('grid-template-columns');L.style.gridArea='';pn.style.gridArea='';R.style.gridArea='';}}
colunas();window.addEventListener('resize',colunas);
var arq=document.getElementById('dcvx-arq'),arqx=document.getElementById('dcvx-arqx');
if(arq){arq.addEventListener('change',function(){var f=arq.files&&arq.files[0];if(!f){return;}lerArq(f,function(im){limpaTex();window.__useBg(im,true);if(arqx){arqx.style.display='';}[].forEach.call(ge.children,function(x){x.classList.remove('on');});pedir();});arq.value='';});}
if(arqx){arqx.addEventListener('click',function(){AIMG=null;arqx.style.display='none';pedir();});}
/* Sanfonas: cada bloco abre e fecha pelo titulo; os controles de personalizar ficam agrupados por acao */
var st4=document.createElement('style');st4.id='dcvx-css4';
st4.textContent='.dcvx-ac{cursor:pointer;user-select:none}.dcvx-ac::after{content:"";margin-left:auto;width:8px;height:8px;border-right:2px solid #667085;border-bottom:2px solid #667085;transform:rotate(225deg);transition:transform .2s}.dcvx-ac.fecha::after{transform:rotate(45deg)}.dcvx-ac.fecha{margin-bottom:0!important}.dcvx-p .dcvx-hd3{justify-content:flex-start}.dcvx-sub{border-top:1px solid #EEF0F4;margin-top:10px;padding-top:10px}.dcvx-sub .dcvx-hd3{font-size:13px;margin:0 0 8px}.dcvx-sub .dcvx-ac.fecha{margin:0!important}';
document.head.appendChild(st4);
function sanfona(box,h,aberto){if(!box||!h){return;}var bd=document.createElement('div');bd.className='dcvx-bd';while(h.nextSibling){bd.appendChild(h.nextSibling);}box.appendChild(bd);
h.classList.add('dcvx-ac');h.setAttribute('role','button');h.tabIndex=0;
function ajusta(o){bd.style.display=o?'':'none';h.setAttribute('aria-expanded',o?'true':'false');h.classList.toggle('fecha',!o);}
box._fecha=function(){ajusta(false);};box._abre=function(){ajusta(true);};
ajusta(aberto);
h.addEventListener('click',function(){var abre=bd.style.display==='none';ajusta(abre);if(abre&&box!==pn){irPara(h);}});
h.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();ajusta(bd.style.display==='none');}});}
function sub(titulo,nos,aberto){var d=document.createElement('div');d.className='dcvx-sub';var h=document.createElement('h3');h.className='dcvx-hd3';h.textContent=titulo;d.appendChild(h);nos.forEach(function(n){if(n){d.appendChild(n);}});s3.appendChild(d);sanfona(d,h,aberto);return d;}
function ctlDe(id){var e=document.getElementById(id);return e?e.closest('.dcvx-ctl'):null;}
var ajustes=ctlDe('dcvx-sz');
if(ajustes){s3.appendChild(ajustes);}
(function(){var lb=document.getElementById('dcvx-lgin'),cl=ctlDe('dcvx-lgin');if(lb&&lb.parentNode&&lb.parentNode.firstChild&&lb.parentNode.firstChild.nodeType===3){lb.parentNode.firstChild.nodeValue='Importar logo';}
if(cl){if(D.escola.logo){var b1=document.createElement('button');b1.type='button';b1.className='dcvx-b';b1.textContent='Logo da escola';b1.style.order='1';b1.addEventListener('click',usaLogoEscola);cl.appendChild(b1);}
cl.classList.add('dcvx-ctl2');var b3=document.createElement('button');b3.type='button';b3.className='dcvx-b';b3.textContent='Voltar o nome do curso';b3.style.display='none';b3.style.order='5';b3.addEventListener('click',function(){CG.off=false;window.__voltaCurso();pedir();});cl.appendChild(b3);window.__voltaCurso=function(){b3.style.display=CG.off?'':'none';};}
var ctlCss=document.createElement('style');ctlCss.textContent='.dcvx-ctl2{display:grid!important;grid-template-columns:1fr 1fr;gap:8px}.dcvx-ctl2>.dcvx-b{width:100%;min-height:42px;display:flex;align-items:center;justify-content:center;text-align:center;padding:8px 10px;margin:0;font-size:13.5px;line-height:1.2}.dcvx-ctl2>label.dcvx-b{order:2}.dcvx-stimp{display:flex;align-items:center;justify-content:center;width:100%;min-height:42px;margin:0 0 10px;font-size:13.5px}';document.head.appendChild(ctlCss);})();
(function(){var st=document.createElement('style');st.textContent='.dcvx-full{display:flex!important;align-items:center;justify-content:center;width:100%;min-height:42px;margin:0 0 8px!important;font-size:13.5px;text-align:center}';document.head.appendChild(st);['dcvx-txn','dcvx-txf','dcvx-arq'].forEach(function(id){var e=document.getElementById(id);if(e){e=e.tagName==='INPUT'?e.parentNode:e;e.classList.add('dcvx-full');}});var n=document.getElementById('dcvx-txn'),f=document.getElementById('dcvx-txf');if(n&&f&&f.parentNode&&n.parentNode&&n.parentNode.parentNode){var w=n.parentNode.parentNode;}})();
var sbLogo=sub('Logo e cores',[ctlDe('dcvx-lgin'),s3.querySelector('.dcvx-cores')],false);
var sbTexto=sub('Texto',[ctlDe('dcvx-tx')],false);
var sbStick=sub('Stickers',[document.getElementById('dcvx-stc'),document.getElementById('dcvx-st')],false);
(function(){var im=document.getElementById('dcvx-imp'),sc=document.getElementById('dcvx-stc');if(im&&im.parentNode&&sc&&sc.parentNode){var lb=im.parentNode;lb.classList.add('dcvx-stimp');if(lb.firstChild&&lb.firstChild.nodeType===3){lb.firstChild.nodeValue='Importar sticker';}sc.parentNode.insertBefore(lb,sc);}})();
/* Texturas: o cliente poe uma textura (linho, jeans, couro...) por cima da cor do produto, com intensidade; o marketing tambem posta texturas na Matriz */
function limpaTex(){TEX=null;BGON=false;if(window.__hideBg){window.__hideBg();}[].forEach.call(document.querySelectorAll('.dcvx-tx button'),function(x){x.classList.remove('on');});}
var TXN=[['Linho',function(x,y,r){return 128+r.l[y]*16+r.c[x]*16+(((x>>1)+(y>>1))&1?7:-7);}],
['Jeans',function(x,y,r){return 128+(((x+y)%6)<3?15:-15)+r.n()*9+r.l[y]*5;}],
['Carbono',function(x,y,r){var c=(((x>>2)+(y>>2))&1);return 112+(c?(x%4-1.5)*11:(y%4-1.5)*11)+r.n()*4;}],
['Papel kraft',function(x,y,r){return 128+r.n()*18+r.l[y]*8;}],
['Mármore',function(x,y,r){var t=(x*2+y*3)/512+0.35*Math.sin(6.2832*y*2/512)+0.2*Math.sin(6.2832*x*3/512),v=Math.pow(1-Math.abs(Math.sin(Math.PI*t*1.5)),14);return 150-85*v+r.n()*4;}],
['Glitter',function(x,y,r){var q=r.u();return q>0.992?255:(q<0.004?30:128+r.n()*10);}]];
function geraTex(i){var S=512,c=document.createElement('canvas');c.width=S;c.height=S;var g=c.getContext('2d'),d=g.createImageData(S,S),sd=7+i*13,rr0=function(){sd=(sd*16807)%2147483647;return sd/2147483647;},r={l:[],c:[],n:function(){return rr0()*2-1;},u:rr0},x,y,v,j;
for(x=0;x<S;x++){r.c.push(rr0()*2-1);r.l.push(rr0()*2-1);}
for(y=0;y<S;y++){for(x=0;x<S;x++){v=TXN[i][1](x,y,r);v=v<0?0:(v>255?255:v);j=(y*S+x)*4;d.data[j]=v;d.data[j+1]=v;d.data[j+2]=v;d.data[j+3]=255;}}
g.putImageData(d,0,0);
if(i===3||i===0){g.globalAlpha=0.12;for(j=0;j<260;j++){g.strokeStyle=rr0()>0.5?'#000':'#fff';g.beginPath();var lx=rr0()*S,ly=rr0()*S;g.moveTo(lx,ly);g.lineTo(lx+(rr0()-0.5)*40,ly+(rr0()-0.5)*40);g.stroke();}g.globalAlpha=1;}
return c;}
var txbox=document.createElement('div');txbox.className='dcvx-tx';
txbox.innerHTML='<div class="dcvx-est" id="dcvx-txg" style="margin:0"></div><div style="display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap"><label style="font-size:12.5px;font-weight:700;color:#344054">Intensidade do padrão</label><input type="range" id="dcvx-txi" min="15" max="100" value="70" style="flex:1;min-width:120px"><button type="button" class="dcvx-b dcvx-full" id="dcvx-txn">Tirar background</button></div><div style="margin-top:8px"><label class="dcvx-b dcvx-full">Importar meu background<input type="file" id="dcvx-txf" accept="image/*" hidden></label><span style="display:block;font-size:11.5px;color:#667085;margin-top:4px">Use uma foto ou arte do tamanho do produto (quanto maior, melhor). Ela vira o fundo inteiro; o logo e o nome do curso ficam por cima. Vale só nesta montagem.</span></div>';
function texMiniatura(cv){var m=document.createElement('canvas');m.width=96;m.height=96;var g=m.getContext('2d');g.fillStyle='#2C6FD6';g.fillRect(0,0,96,96);var pt=g.createPattern(cv,'repeat');if(pt.setTransform){pt.setTransform(new DOMMatrix().scale(96/cv.width*1.6));}g.globalCompositeOperation='overlay';g.globalAlpha=0.95;g.fillStyle=pt;g.fillRect(0,0,96,96);return m;}
function renderBg(){var im=BG.im;if(!im){return;}var iw=im.naturalWidth||im.width,ih=im.naturalHeight||im.height,F=PRD.tam?Math.max(2,Math.min(5,Math.ceil(PRD.tam.larg/2.54*PRD.tam.dpi/(RR-X0)))):2,cw=(RR-X0)*F,ch=(Y1-YTOP)*F,c=document.createElement('canvas');c.width=cw;c.height=ch;var g=c.getContext('2d');g.imageSmoothingQuality='high';var k=Math.max(cw/iw,ch/ih)*BG.k,dw=iw*k,dh=ih*k,mx=Math.max(0,(dw-cw)/2),my=Math.max(0,(dh-ch)/2);BG.dx=Math.max(-mx,Math.min(mx,BG.dx));BG.dy=Math.max(-my,Math.min(my,BG.dy));g.drawImage(im,(cw-dw)/2+BG.dx,(ch-dh)/2+BG.dy,dw,dh);AIMG=c;}
window.__renderBg=renderBg;
(function(){var zb=document.getElementById('dcvx-zbar'),pa=document.createElement('div');pa.id='dcvx-bgadj';pa.style.cssText='display:none;margin:10px auto 0;max-width:625px;padding:10px 12px;border:1px solid #E7E9F0;border-radius:12px;background:#fff';
pa.innerHTML='<div style="font-size:12.5px;font-weight:700;color:#344054;margin-bottom:6px">Ajustar a imagem de fundo</div><div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><label style="font-size:12.5px;color:#344054">Zoom</label><input type="range" id="dcvx-bgz" min="100" max="300" value="100" style="flex:1;min-width:120px"></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px"><button type="button" class="dcvx-b dcvx-full" id="dcvx-bgm" style="margin:0!important">Mover a imagem</button><button type="button" class="dcvx-b dcvx-full" id="dcvx-bgc" style="margin:0!important">Centralizar</button></div><span style="display:block;font-size:11.5px;color:#667085;margin-top:6px">Toque em "Mover a imagem" e arraste sobre o produto para escolher o que aparece (por exemplo, para não cortar um texto). O zoom aproxima a imagem.</span>';
if(zb&&zb.parentNode){zb.parentNode.insertBefore(pa,zb.nextSibling);}
var z=pa.querySelector('#dcvx-bgz'),m=pa.querySelector('#dcvx-bgm'),c=pa.querySelector('#dcvx-bgc');
z.addEventListener('input',function(){BG.k=(+z.value)/100;renderBg();pedir();});
m.addEventListener('click',function(){BGMOVE=!BGMOVE;m.classList.toggle('on',BGMOVE);m.textContent=BGMOVE?'Terminar de mover':'Mover a imagem';cv.style.touchAction=BGMOVE?'none':'';if(BGMOVE){selIt=null;syncSz();pedir();}});
c.addEventListener('click',function(){BG.dx=0;BG.dy=0;BG.k=1;z.value=100;renderBg();pedir();});
window.__useBg=function(im,hide){BG.im=im;BG.k=1;BG.dx=0;BG.dy=0;BGMOVE=false;renderBg();AHIDE=!!hide;z.value=100;m.classList.remove('on');m.textContent='Mover a imagem';pa.style.display='block';};
window.__hideBg=function(){BGMOVE=false;BG.im=null;pa.style.display='none';cv.style.touchAction='';};})();
function escolheBg(im,btn){if(btn.classList.contains('on')){if(BGON){AIMG=null;}limpaTex();pedir();return;}
limpaTex();window.__useBg(im,false);BGON=true;[].forEach.call(document.querySelectorAll('.dcvx-est:not(#dcvx-txg) button'),function(x){x.classList.remove('on');});var ax=document.getElementById('dcvx-arqx');if(ax){ax.style.display='none';}btn.classList.add('on');pedir();}
function escolheTex(cv,btn,sz){if(btn.classList.contains('on')){limpaTex();pedir();return;}if(BGON){AIMG=null;}limpaTex();AIMG=null;[].forEach.call(document.querySelectorAll('.dcvx-est:not(#dcvx-txg) button'),function(x){x.classList.remove('on');});var ax=document.getElementById('dcvx-arqx');if(ax){ax.style.display='none';}TEX={cv:cv,sz:sz||230};btn.classList.add('on');pedir();}
setTimeout(function(){var gt=txbox.querySelector('#dcvx-txg');if(!gt){return;}
TXN.forEach(function(q,i){var b=document.createElement('button');b.type='button';b.title=q[0];b.setAttribute('aria-label','Textura '+q[0]);var cv=geraTex(i);b.appendChild(texMiniatura(cv));b.addEventListener('click',function(){escolheTex(cv,b,230);});gt.appendChild(b);});
(window.DCVX_EST||[]).filter(function(e){return e.tex;}).forEach(function(e){var b=document.createElement('button');b.type='button';b.title=e.n;b.setAttribute('aria-label','Background da rede '+e.n);var im=document.createElement('img');im.src=e.c;im.alt='';im.style.cssText='width:100%;aspect-ratio:1;object-fit:cover;display:block;border-radius:8px';b.appendChild(im);
b.addEventListener('click',function(){if(b.classList.contains('on')){escolheBg(null,b);return;}var o=new Image();o.onload=function(){escolheBg(o,b);};o.src=e.u;});gt.appendChild(b);});},900);
var txi=txbox.querySelector('#dcvx-txi');txi.addEventListener('input',function(){TI=(+txi.value)/100;pedir();});
txbox.querySelector('#dcvx-txn').addEventListener('click',function(){if(BGON){AIMG=null;}limpaTex();pedir();});
var txf=txbox.querySelector('#dcvx-txf');
if(txf){txf.addEventListener('change',function(){var f=txf.files&&txf.files[0];if(!f){return;}lerArq(f,function(im){var iw=im.naturalWidth,ih=im.naturalHeight;
var gt=txbox.querySelector('#dcvx-txg'),b=document.createElement('button');b.type='button';b.title='Meu background';b.setAttribute('aria-label','Meu background');var m=document.createElement('canvas');m.width=96;m.height=96;var mg=m.getContext('2d'),mk2=Math.max(96/iw,96/ih);mg.drawImage(im,(96-iw*mk2)/2,(96-ih*mk2)/2,iw*mk2,ih*mk2);
b.appendChild(m);b.addEventListener('click',function(){escolheBg(im,b);});if(gt){gt.appendChild(b);}escolheBg(im,b);});txf.value='';});}
var sbTex=sub('Background',[txbox],false);
sanfona(s3,h3s,false);
sanfona(pr,pr.querySelector('.dcvx-hd3'),true);
sanfona(cc,h2,false);
sanfona(pn,mt,true);
sanfona(ce,ce.querySelector('.dcvx-hd3'),false);
sanfona(cu,cu.querySelector('.dcvx-hd3'),false);
/* CELULAR: a previa do produto fica fixa no topo enquanto o cliente escolhe curso, estampa e logo; quantidade e pedido vao para o fim */
var stM=document.createElement('style');stM.id='dcvx-css9';
stM.textContent='@media(max-width:900px){.dcvx-w .dcvx-p{order:-1;position:sticky;top:0;z-index:30;padding:8px 10px!important;box-shadow:0 8px 20px rgba(16,24,40,.14)}.dcvx-w .dcvx-p>.dcvx-hd3{display:none}#dcvx-zw{max-width:min(100%,46vh)!important}#dcvx-zdica{display:none}#dcvx-buyslot{order:99}}';
document.head.appendChild(stM);zdica.id='dcvx-zdica';
var slot=document.createElement('div');slot.id='dcvx-buyslot';slot.className='dcvx-card';slot.style.display='none';w.appendChild(slot);
var mq=window.matchMedia('(max-width:900px)');
function lugar(){var bd=pn.querySelector('.dcvx-bd')||pn;if(mq.matches){slot.style.display='block';if(bb3){slot.appendChild(bb3);}if(dl3){slot.appendChild(dl3);}}else{slot.style.display='none';if(bb3){bd.appendChild(bb3);}if(dl3){bd.appendChild(dl3);}}}
lugar();if(mq.addEventListener){mq.addEventListener('change',lugar);}else if(mq.addListener){mq.addListener(lugar);}
/* CELULAR: ao entrar na tela rola ate o titulo (o conteudo ficava abaixo do menu); ao abrir um bloco leva ate ele, abaixo da previa fixa */
function stickyH(){var e=document.querySelector('.dcvx-p');return mq.matches&&e?e.offsetHeight:0;}
function irPara(el){if(!mq.matches||!el){return;}setTimeout(function(){var y=el.getBoundingClientRect().top+window.pageYOffset-stickyH()-12;window.scrollTo({top:Math.max(0,y),behavior:'smooth'});},60);}
if(mq.matches){setTimeout(function(){var t=document.querySelector('.dcvx');if(t&&window.pageYOffset<t.getBoundingClientRect().top+window.pageYOffset-40){t.scrollIntoView({behavior:'smooth',block:'start'});}},350);}
window.__ir=function(el){irPara(el);};
/* Depois que o cliente escolhe, o grupo fecha sozinho (celular) e a tela vai para o proximo; escolher o produto abre os cursos */
var GRUPOS=[pr,cc,ce,cu,s3];
function fechaApos(box,ehSub){if(!mq.matches||!box||!box._fecha){return;}setTimeout(function(){box._fecha();var prox=ehSub?null:GRUPOS[GRUPOS.indexOf(box)+1];
if(box===pr&&cc&&cc._abre){cc._abre();prox=cc;}
irPara(prox?(prox.querySelector('.dcvx-hd3')||prox):(box.querySelector('.dcvx-hd3')||box));},320);}
pr.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('.dcvx-pi[data-prod]')){fechaApos(pr,false);}});
cc.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('#dcvx-g .dcvx-t')){fechaApos(cc,false);}});
ce.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('.dcvx-est button')){fechaApos(ce,false);}});
cu.addEventListener('change',function(e){if(e.target&&e.target.id==='dcvx-arq'){fechaApos(cu,false);}});
sbLogo.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('button,.dcvx-sw')){fechaApos(sbLogo,true);}});
sbLogo.addEventListener('change',function(e){if(e.target&&e.target.id==='dcvx-lgin'){fechaApos(sbLogo,true);}});
sbStick.addEventListener('change',function(e){if(e.target&&e.target.id==='dcvx-imp'){fechaApos(sbStick,true);}});
sbTexto.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('#dcvx-tb')){fechaApos(sbTexto,true);}});
sbStick.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('#dcvx-st button')){fechaApos(sbStick,true);}});
sbTex.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('#dcvx-txg button,#dcvx-txn')){fechaApos(sbTex,true);}});
sbTex.addEventListener('change',function(e){if(e.target&&e.target.id==='dcvx-txf'){fechaApos(sbTex,true);}});
})();
(function(){var pg=document.querySelector('.dcvx-pg');if(!pg||!pg.parentNode){return;}
var box=document.createElement('div');box.id='dcvx-corx';box.style.cssText='display:none;margin:10px 0 0;padding:10px 12px;border:1px solid #E7E9F0;border-radius:12px;background:#fff';
box.innerHTML='<div style="font-size:12.5px;font-weight:700;color:#344054;margin-bottom:8px">Cor do interior <span id="dcvx-corn" style="font-weight:600;color:#667085"></span></div><div id="dcvx-corb" style="display:flex;gap:8px;flex-wrap:wrap"></div>';
pg.parentNode.insertBefore(box,pg.nextSibling);
function carrega(Q,i,cb){var c=Q.cores[i];if(!c.u||c._st){cb();return;}var im=new Image();im.onload=function(){var t=document.createElement('canvas');t.width=im.naturalWidth;t.height=im.naturalHeight;var g=t.getContext('2d',{willReadFrequently:true});g.drawImage(im,0,0);var d=g.getImageData(0,0,t.width,t.height).data,x0=t.width,y0=t.height,x1=0,y1=0,x,y;for(y=0;y<t.height;y++){for(x=0;x<t.width;x++){if(d[(y*t.width+x)*4+3]>20){if(x<x0){x0=x;}if(x>x1){x1=x;}if(y<y0){y0=y;}if(y>y1){y1=y;}}}}if(x1>=x0){c._st={img:im,bx:x0,by:y0,bw:x1-x0+1,bh:y1-y0+1};}cb();};im.onerror=function(){cb();};im.src=c.u;}
function monta(Q){var bx=document.getElementById('dcvx-corb');if(!Q||!Q.cores){box.style.display='none';return;}box.style.display='block';if(Q.corI==null){Q.corI=0;}bx.innerHTML='';
Q.cores.forEach(function(c,i){var b=document.createElement('button');b.type='button';b.className='dcvx-sw'+(i===Q.corI?' on':'');b.title=c.n;b.setAttribute('aria-label','Cor do interior: '+c.n);b.style.background=c.h;
b.addEventListener('click',function(){Q.corI=i;[].forEach.call(bx.children,function(x,k){x.classList.toggle('on',k===i);});document.getElementById('dcvx-corn').textContent='· '+c.n;carrega(Q,i,function(){window.__prep();});});bx.appendChild(b);});
document.getElementById('dcvx-corn').textContent='· '+Q.cores[Q.corI].n;}
(window.__prodHooks=window.__prodHooks||[]).push(monta);})();
window.__dcvx={impr:function(){var o=impressao(D.cursos[sel]||SEM);return {w:o.width,h:o.height,url:o.toDataURL('image/png')};},prod:function(k){setProd(k);},sel:function(i){sel=i;pedir();},n:function(){return LIB.length;},add:function(i){var s=new Image();s.onload=function(){addSticker(adesivo(s));};s.src=uri(LIB[i][2]);},items:function(){return items.length;},pos:function(){return selIt&&selIt!=='logo'?{x:selIt.x,y:selIt.y}:null;},padrao:function(i,S){var c=document.createElement('canvas');c.width=Math.round((RR-X0)*S);c.height=Math.round((Y1-YTOP)*S);var g=c.getContext('2d');g.scale(S,S);g.translate(-X0,-YTOP);var gg=g.createLinearGradient(0,0,N*0.4,N);gg.addColorStop(0,'#226BCB');gg.addColorStop(1,'#15427D');g.fillStyle=gg;g.fillRect(0,0,N,N);PAT[i].fn(g);return c.toDataURL('image/png');},npadroes:function(){return PAT.length;},nomepadrao:function(i){return PAT[i].n;},aimg:function(){return AIMG?(AHIDE?'arte':'fundo'):'nenhuma';},hdl:function(){return selIt?hdl(selIt):null;},txup:function(url){var b=document.getElementById('dcvx-txf');return !!b;},abreTudo:function(){[].forEach.call(document.querySelectorAll('.dcvx-ac.fecha'),function(h){h.click();});},tex:function(i){var b=document.querySelectorAll('#dcvx-txg button')[i];if(b){b.click();}return !!b;},sellogo:function(){selIt='logo';syncSz();pedir();},q:function(){var H=selIt==='logo'?LG:(selIt==='curso'?CG:selIt);return H&&H.q?H.q:null;},selcurso:function(){selIt='curso';syncSz();pedir();},cg:function(){return {dx:CG.dx,dy:CG.dy,k:CG.k,r:CG.r,off:CG.off};},rot:function(){return selIt?rotDe(selIt):null;},k:function(){return LG.k;}};
})();
