const { chromium } = require('playwright'); const fs = require('fs');
const lib = String.raw`
const svg=document.getElementById('s'); const rc=rough.svg(svg); const INK='#141414'; const add=n=>svg.appendChild(n);
let SEED=11; const o=(x={})=>Object.assign({stroke:INK,strokeWidth:2.6,roughness:2,bowing:2,seed:(SEED++),disableMultiStroke:false},x);
const wash=(f,x={})=>Object.assign({fill:f,fillStyle:'hachure',hachureGap:5,fillWeight:1.4,hachureAngle:-38},x);
const sol=(f,x={})=>Object.assign({fill:f,fillStyle:'solid'},x);
function RS(x,y,w,h,f,opt={}){add(rc.rectangle(x,y,w,h,o(Object.assign(f?sol(f):{},opt))))}
function RW(x,y,w,h,f,opt={}){add(rc.rectangle(x,y,w,h,o(Object.assign(f?wash(f):{},opt))))}
function C(x,y,d,f,opt={}){add(rc.circle(x,y,d,o(Object.assign(f?sol(f):{},opt))))}
function E(x,y,w,h,f,opt={}){add(rc.ellipse(x,y,w,h,o(Object.assign(f?sol(f):{},opt))))}
function EW(x,y,w,h,f,opt={}){add(rc.ellipse(x,y,w,h,o(Object.assign(f?wash(f):{},opt))))}
function L(x1,y1,x2,y2,opt={}){add(rc.line(x1,y1,x2,y2,o(opt)))}
function P(d,f,opt={}){add(rc.path(d,o(Object.assign(f?sol(f):{},opt))))}
function PW(d,f,opt={}){add(rc.path(d,o(Object.assign(f?wash(f):{},opt))))}
function raw(tag,a){const n=document.createElementNS('http://www.w3.org/2000/svg',tag);for(const k in a)n.setAttribute(k,a[k]);add(n);return n}
function T(x,y,txt,size,opt={}){const t=raw('text',{x,y,'font-size':size,fill:opt.fill||INK,'text-anchor':opt.anchor||'start','font-family':'PH'});
 txt.split('\n').forEach((l,i)=>{const s=document.createElementNS('http://www.w3.org/2000/svg','tspan');s.setAttribute('x',x);s.setAttribute('dy',i?size*1.12:0);s.textContent=l;t.appendChild(s)})}
// dialogue without balloon, with pointer line (press-cartoon style)
function say(x,y,txt,size,px,py,anchor){const t=raw('text',{x,y,'font-size':size,fill:INK,'font-family':'PH'});
 txt.split('\n').forEach((l,i)=>{const sp=document.createElementNS('http://www.w3.org/2000/svg','tspan');sp.setAttribute('x',x);sp.setAttribute('dy',i?size*1.12:0);sp.textContent=l;t.appendChild(sp)});
 const bb=t.getBBox();svg.removeChild(t);const cx=bb.x+bb.width/2,cy=bb.y+bb.height/2,w=bb.width*1.22+60,h=bb.height*1.25+60;
 const ang=Math.atan2(py-cy,px-cx);const ex=cx+Math.cos(ang)*w*0.42,ey=cy+Math.sin(ang)*h*0.42;const nx=-Math.sin(ang)*22,ny=Math.cos(ang)*22;
 const tx=cx+(px-cx)*0.82,ty=cy+(py-cy)*0.82;
 P('M'+(ex+nx)+' '+(ey+ny)+' Q '+((ex+tx)/2+nx*0.3)+' '+((ey+ty)/2+ny*0.3)+' '+tx+' '+ty+' Q '+((ex+tx)/2-nx*0.3)+' '+((ey+ty)/2-ny*0.3)+' '+(ex-nx)+' '+(ey-ny)+' Z','#fff',{roughness:1.4,strokeWidth:2.6});
 E(cx,cy,w,h,'#fff',{roughness:1.6,strokeWidth:2.8});add(t)}
function scribble(cx,cy,w,h,n,col){for(let i=0;i<n;i++){const a=Math.random()*Math.PI*2;const r=Math.random();P('M'+(cx+Math.cos(a)*w*r*0.5)+' '+(cy+Math.sin(a)*h*r*0.5)+' q '+((Math.random()-.5)*w*0.6)+' '+((Math.random()-.5)*h*0.6)+' '+((Math.random()-.5)*w*0.5)+' '+((Math.random()-.5)*h*0.5),null,{stroke:col||INK,strokeWidth:1.8,roughness:2.5})}}
// big-nosed character. hx,hy head centre
function tipo(hx,hy,s,c={}){
 const body=c.body||'#9cc3ff', bw=110*s, by=hy+58*s, bh=c.short?120*s:150*s;
 // legs & big shoes
 if(!c.noLegs){L(hx-20*s,by+bh-6,hx-24*s,by+bh+60*s,{strokeWidth:4});L(hx+20*s,by+bh-6,hx+24*s,by+bh+60*s,{strokeWidth:4});
  E(hx-40*s,by+bh+66*s,64*s,26*s,'#2a2a2a');E(hx+40*s,by+bh+66*s,64*s,26*s,'#2a2a2a')}
 // sack body
 PW('M'+(hx-bw*0.42)+' '+by+' Q '+(hx-bw*0.62)+' '+(by+bh*0.6)+' '+(hx-bw*0.5)+' '+(by+bh)+' L '+(hx+bw*0.5)+' '+(by+bh)+' Q '+(hx+bw*0.62)+' '+(by+bh*0.6)+' '+(hx+bw*0.42)+' '+by+' Z',body,{strokeWidth:2.8});
 if(c.apron)PW('M'+(hx-30*s)+' '+(by+30*s)+' L '+(hx+30*s)+' '+(by+30*s)+' L '+(hx+40*s)+' '+(by+bh)+' L '+(hx-40*s)+' '+(by+bh)+' Z',c.apron,{strokeWidth:2.2});
 if(c.tie)P('M'+(hx-6*s)+' '+(by+4*s)+' L '+(hx+6*s)+' '+(by+4*s)+' L '+(hx+10*s)+' '+(by+60*s)+' L '+hx+' '+(by+72*s)+' L '+(hx-10*s)+' '+(by+60*s)+' Z','#d64545',{strokeWidth:2});
 if(c.coat){L(hx,by+6,hx,by+bh-4,{strokeWidth:2});L(hx-30*s,by+2*s,hx-6*s,by+30*s,{strokeWidth:2});L(hx+30*s,by+2*s,hx+6*s,by+30*s,{strokeWidth:2})}
 // head (big)
 const hw=150*s, hh=140*s;
 EW(hx,hy,hw,hh,'#F7D9BE',{strokeWidth:2.8,hachureGap:7});
 // hair
 if(c.hair==='scribble'){scribble(hx-10*s,hy-hh*0.42,hw*0.9,hh*0.4,26,c.hc)}
 if(c.hair==='bald'){P('M'+(hx-hw*0.5)+' '+(hy-6*s)+' q -10 -26 6 -40',null,{strokeWidth:2});P('M'+(hx+hw*0.5)+' '+(hy-6*s)+' q 10 -26 -6 -40',null,{strokeWidth:2});scribble(hx-hw*0.48,hy-18*s,20*s,30*s,6,c.hc);scribble(hx+hw*0.48,hy-18*s,20*s,30*s,6,c.hc);for(let i=0;i<3;i++)P('M'+(hx-20*s+i*18*s)+' '+(hy-hh*0.5)+' q 6 -30 -4 -40',null,{strokeWidth:1.6})}
 if(c.hair==='perm'){for(let i=0;i<16;i++){const a=Math.PI*(1.05+i*0.058);C(hx+Math.cos(a)*hw*0.5,hy+Math.sin(a)*hh*0.5,30*s,c.hc,{strokeWidth:1.8})}}
 if(c.cap){PW('M'+(hx-hw*0.48)+' '+(hy-hh*0.18)+' Q '+hx+' '+(hy-hh*0.8)+' '+(hx+hw*0.48)+' '+(hy-hh*0.18)+' Z',c.cap,{strokeWidth:2.5});P('M'+(hx+hw*0.2)+' '+(hy-hh*0.2)+' q 60 -10 80 6 q -40 10 -80 -6',c.cap,{strokeWidth:2.5})}
 // eyebrows + tiny dot eyes close together above the nose
 const ey=hy-8*s;
 const br=c.brows||'neutral';
 if(br==='up'){P('M'+(hx-34*s)+' '+(ey-16*s)+' q 10 -10 20 0',null,{strokeWidth:2.8});P('M'+(hx+14*s)+' '+(ey-16*s)+' q 10 -10 20 0',null,{strokeWidth:2.8})}
 else if(br==='angry'){L(hx-34*s,ey-24*s,hx-12*s,ey-14*s,{strokeWidth:3.2});L(hx+12*s,ey-14*s,hx+34*s,ey-24*s,{strokeWidth:3.2})}
 else if(br==='sad'){L(hx-34*s,ey-14*s,hx-12*s,ey-24*s,{strokeWidth:3});L(hx+12*s,ey-24*s,hx+34*s,ey-14*s,{strokeWidth:3})}
 else {L(hx-32*s,ey-18*s,hx-14*s,ey-20*s,{strokeWidth:2.8});L(hx+14*s,ey-20*s,hx+32*s,ey-18*s,{strokeWidth:2.8})}
 if(c.glasses){C(hx-22*s,ey,34*s,null,{strokeWidth:2.2});C(hx+22*s,ey,34*s,null,{strokeWidth:2.2})}
 if(c.sleepy){L(hx-30*s,ey,hx-14*s,ey,{strokeWidth:3});L(hx+14*s,ey,hx+30*s,ey,{strokeWidth:3})} else {C(hx-22*s,ey,8*s,INK);C(hx+22*s,ey,8*s,INK)}
 // the BIG nose
 EW(hx+(c.noseDir||8)*s,hy+30*s,78*s,58*s,'#F2A68E',{strokeWidth:2.8,hachureGap:4});
 // mouth under nose
 const my=hy+68*s;
 if(c.mouth==='open')EW(hx+4*s,my,30*s,22*s,'#7a2020',{strokeWidth:2.4});
 else if(c.mouth==='smile')P('M'+(hx-18*s)+' '+(my-4*s)+' q 20 18 40 0',null,{strokeWidth:2.6});
 else if(c.mouth==='wavy')P('M'+(hx-16*s)+' '+my+' q 8 -8 16 0 q 8 8 16 0',null,{strokeWidth:2.6});
 else L(hx-14*s,my,hx+16*s,my-2*s,{strokeWidth:2.6});
 if(c.mous)P('M'+(hx-30*s)+' '+(my-8*s)+' q 34 -18 64 0 q -32 8 -64 0',c.hc||INK,{strokeWidth:2});
 // arms
 const sl=[hx-bw*0.45,by+20*s], sr=[hx+bw*0.45,by+20*s];
 const pose=(S,p,side)=>{let h;
  if(p==='up')h=[S[0]+10*s,S[1]-170*s];
  else if(p==='front')h=[hx+side*16*s,S[1]+70*s];
  else if(p==='hip')h=[S[0]+side*36*s,S[1]+70*s];
  else if(p==='point')h=[S[0]+side*110*s,S[1]-10*s];
  else if(p==='chin')h=[hx+6*s,hy+80*s];
  else h=[S[0]+side*16*s,S[1]+100*s];
  P('M'+S[0]+' '+S[1]+' Q '+((S[0]+h[0])/2+side*16*s)+' '+((S[1]+h[1])/2)+' '+h[0]+' '+h[1],null,{strokeWidth:4.5});C(h[0],h[1],26*s,'#F7D9BE',{strokeWidth:2.2});return h};
 return {hl:pose(sl,c.left||'down',-1),hr:pose(sr,c.right||'down',1)};
}
function robotito(x,y,s,c={}){
 L(x,y-60*s,x+6*s,y-90*s,{strokeWidth:2.4});C(x+6*s,y-94*s,16*s,'#2F6BFF');
 RW(x-58*s,y-62*s,116*s,96*s,'#d7dde6',{strokeWidth:2.8});RS(x-44*s,y-48*s,88*s,64*s,'#0B1220',{strokeWidth:2});
 if(c.eyes==='sad'){P('M'+(x-30*s)+' '+(y-14*s)+' q 10 -10 20 0',null,{stroke:'#7FA8FF',strokeWidth:4.5});P('M'+(x+10*s)+' '+(y-14*s)+' q 10 -10 20 0',null,{stroke:'#7FA8FF',strokeWidth:4.5})}
 else if(c.eyes==='stern'){L(x-32*s,y-26*s,x-10*s,y-18*s,{stroke:'#7FA8FF',strokeWidth:5});L(x+10*s,y-18*s,x+32*s,y-26*s,{stroke:'#7FA8FF',strokeWidth:5});C(x-20*s,y-6*s,10*s,'#7FA8FF',{stroke:'none'});C(x+20*s,y-6*s,10*s,'#7FA8FF',{stroke:'none'})}
 else {C(x-20*s,y-16*s,14*s,'#7FA8FF',{stroke:'none'});C(x+20*s,y-16*s,14*s,'#7FA8FF',{stroke:'none'})}
 PW('M'+(x-46*s)+' '+(y+38*s)+' L '+(x+46*s)+' '+(y+38*s)+' L '+(x+40*s)+' '+(y+128*s)+' L '+(x-40*s)+' '+(y+128*s)+' Z','#c3ccd8',{strokeWidth:2.6});
 if(c.tie)P('M'+(x-6*s)+' '+(y+40*s)+' L '+(x+6*s)+' '+(y+40*s)+' L '+(x+10*s)+' '+(y+90*s)+' L '+x+' '+(y+100*s)+' L '+(x-10*s)+' '+(y+90*s)+' Z','#d64545',{strokeWidth:2});
 if(!c.noLegs){L(x-20*s,y+128*s,x-20*s,y+170*s,{strokeWidth:4});L(x+20*s,y+128*s,x+20*s,y+170*s,{strokeWidth:4});E(x-26*s,y+174*s,40*s,16*s,'#444');E(x+26*s,y+174*s,40*s,16*s,'#444')}
}
function footer(src){const g=raw('g',{transform:'translate(60,1268)'});g.innerHTML='<path d="M7 42 L24 7 L41 42" fill="none" stroke="#F3F6FA" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="31" r="5" fill="#2F6BFF"/>';
 const t=raw('text',{x:118,y:1307,'font-size':40,fill:'#F3F6FA','font-family':'SG','font-weight':700});t.innerHTML='innov<tspan fill="#2F6BFF">AI</tspan>';
 raw('text',{x:1020,y:1302,'font-size':24,fill:'#97A3B6','font-family':'SG','text-anchor':'end'}).textContent='Noticia real · '+src}
function frame(){raw('rect',{x:48,y:50,width:1000,height:1190,rx:6,fill:'#2F6BFF',opacity:.35});RS(40,40,1000,1190,'#FBF9F3',{stroke:'#2F6BFF',strokeWidth:7,roughness:.8,bowing:.5})}
function head(txt){T(540,112,txt,40,{anchor:'middle'});L(110,138+ (txt.split('\n').length-1)*45,970,140+(txt.split('\n').length-1)*45,{strokeWidth:1.6,stroke:'#2F6BFF',roughness:1.5})}
`;
const page=(draw)=>`<!doctype html><meta charset=utf-8><style>@font-face{font-family:PH;src:url(ph.woff2)}@font-face{font-family:SG;src:url(space-grotesk-latin-700-normal.woff2);font-weight:700}@font-face{font-family:SG;src:url(space-grotesk-latin-400-normal.woff2);font-weight:400}body{margin:0}</style><svg id=s xmlns="http://www.w3.org/2000/svg" width=1080 height=1350 viewBox="0 0 1080 1350"><rect width=1080 height=1350 fill="#05070B"/></svg><script src=rough.js></script><script>let _r=42;Math.random=()=>{_r=(_r*16807)%2147483647;return (_r-1)/2147483646};${lib}
Promise.all([document.fonts.load('40px PH'),document.fonts.load('700 40px SG'),document.fonts.load('400 24px SG')]).then(()=>{ frame(); ${draw}; document.title='ok';});</script>`;
const v={
'forges-minecraft':`head('Dejan a 50 IA solas en Minecraft\\npara ver qué construyen.');
// lab: big monitor
RW(560,330,420,300,'#c9ced6',{strokeWidth:2.8});RS(585,352,370,240,'#0B1220',{strokeWidth:2});
// tiny scene in monitor: robot at window with stamp
RS(610,470,320,110,'#7a5a3a',{strokeWidth:1.6});RS(680,420,180,36,'#fbf9f3',{strokeWidth:1.6});T(770,446,'VENTANILLA',24,{anchor:'middle'});
robotito(770,520,0.42,{tie:true,eyes:'stern',noLegs:true});robotito(640,540,0.32,{eyes:'sad',noLegs:true});
L(740,630,800,630,{strokeWidth:3});RS(730,630,80,60,null);RS(700,690,140,14,'#c9ced6');
// two scientists
tipo(250,520,1,{hair:'bald',hc:'#888',coat:true,body:'#ffffff',glasses:true,brows:'up',mouth:'open',left:'down',right:'point'});
tipo(430,640,0.8,{hair:'perm',hc:'#d9a441',coat:true,body:'#ffffff',brows:'sad',mouth:'wavy',left:'chin',right:'down',noseDir:-6});
say(90,250,'¿Y qué han construido,\\nuna ciudad? ¿Un cohete?',36,200,420);
say(560,850,'Un ayuntamiento. Llevo tres días\\nesperando a que me sellen\\nun bloque de piedra.',36,470,640);
footer('Xataka, 17/11/2026');`,
'forges-stories':`head('Instagram ya deja editar\\nlas stories después de publicarlas.');
// shop door
RW(90,300,330,700,'#bcd3ef',{strokeWidth:3});RS(120,330,270,330,'#eef4fb',{strokeWidth:2});RS(300,360,84,60,'#fbf9f3',{strokeWidth:2});T(342,386,'CERRADO',18,{anchor:'middle',fill:'#d64545'});T(342,408,'abre a las 9',15,{anchor:'middle'});
// customer outside (left), angry
tipo(250,540,0.95,{hair:'scribble',hc:'#333',body:'#e3a94a',brows:'angry',mouth:'line',left:'hip',right:'point'});
// shopkeeper inside behind half-door, with phone, sleepy, coffee
const pe=tipo(720,560,1,{hair:'perm',hc:'#6b3a1e',body:'#f3b0a0',apron:'#7ac5b8',brows:'up',mouth:'smile',left:'front',right:'up',noseDir:-8,sleepy:true});
RS(pe.hr[0]-20,pe.hr[1]-72,40,64,'#222',{strokeWidth:2});RS(pe.hr[0]-14,pe.hr[1]-66,28,50,'#ffe0ec',{strokeWidth:1.2});
RS(520,760,480,240,'#e8cfae',{strokeWidth:3});RW(520,760,480,240,'#c79b6d',{strokeWidth:3});
RS(560,716,60,44,'#fff',{strokeWidth:2});P('M 620 726 q 20 4 0 24',null,{strokeWidth:2.5});
say(80,240,'¡Ponía que abríais a las nueve!',38,240,470);
say(600,300,'Ponía. Ahora Instagram\\ndeja editarlo.',38,680,500);
footer('Xataka Móvil, 19/11/2026');`,
'forges-5g':`head('17 millones para llevar el 5G\\na zonas donde no llega ni el 4G.');
L(40,700,1040,700,{strokeWidth:4});
RW(820,560,160,140,'#cfd5dc',{strokeWidth:2.6});EW(900,560,160,36,'#e3e7ec',{strokeWidth:2.4});
L(760,700,760,560,{strokeWidth:3});L(730,580,790,580,{strokeWidth:2.4});L(740,610,780,610,{strokeWidth:2.4});
PW('M 560 700 L 572 620 L 668 620 L 680 700 Z','#9cc3ff',{strokeWidth:2.6});
const m=tipo(620,400,0.8,{cap:'#d64545',hair:'scribble',hc:'#222',body:'#5b7fd1',brows:'sad',mouth:'wavy',mous:true,left:'hip',right:'up'});
RS(m.hr[0]-18,m.hr[1]-70,36,60,'#222',{strokeWidth:2});RS(m.hr[0]-12,m.hr[1]-64,24,46,'#ffe9a8',{strokeWidth:1.2});
for(let i=0;i<3;i++)P('M '+(m.hr[0]-26-i*14)+' '+(m.hr[1]-80-i*8)+' a '+(26+i*14)+' '+(26+i*14)+' 0 0 1 '+(52+i*28)+' 0',null,{strokeWidth:2});
RW(60,700,440,530,'#efd9b4',{strokeWidth:3});RS(140,820,240,190,'#bcd3ef',{strokeWidth:2.6});
tipo(260,880,0.72,{hair:'perm',hc:'#9a4b2a',body:'#e07a8a',brows:'up',mouth:'open',noLegs:true,left:'down',right:'point',short:true});
RS(120,1000,280,30,'#c79b6d',{strokeWidth:2.4});
say(80,290,'¡Que traigan primero el 4G,\\nPili, que llevo aquí arriba\\ndesde 2014!',38,560,360);
say(540,880,'¡Manolo, baja, que dicen\\nen la tele que nos\\ntraen el 5G!',38,330,880);
footer('Xataka Móvil, 21/11/2026');`};
(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:1080,height:1350}});
for(const [k,d] of Object.entries(v)){fs.writeFileSync(k+'.html',page(d));await p.goto('file://'+process.cwd()+'/'+k+'.html');
 try{await p.waitForFunction(()=>document.title==='ok',{timeout:8000})}catch(e){console.log(k,'ERR')}
 await p.screenshot({path:k+'.jpg',type:'jpeg',quality:92});}
await b.close();})();
