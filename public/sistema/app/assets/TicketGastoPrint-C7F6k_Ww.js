import{N as e,d as t,g as n,st as r,v as i}from"./runtime-core.esm-bundler-a6pTXghO.js";import{f as a,o,u as s}from"./tmCloudClient-BJVGK5pA.js";import{C as c,I as l,l as u,u as d}from"./index-BMrcxrjI.js";var f={style:{display:`none`}},p=i({__name:`TicketGastoPrint`,setup(i,{expose:p}){let m=l(),h={printer_name:``,paper_width:80,show_logo:1,show_company_name:1,show_legal:1,show_phone:1,show_address:1,show_email:1,show_footer:1,footer_text:`Gracias por su preferencia`};function g(e){return e===!0||e===1||e===`1`}function _(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function v(e){return _(e).toFixed(2)}function y(e){return String(e?.logoprinter||e?.logo||``).trim()}function b(e){let t=new Uint8Array(e),n=``,r=32768;for(let e=0;e<t.length;e+=r)n+=String.fromCharCode(...t.subarray(e,e+r));return btoa(n)}async function x(e){let t=String(e||``).trim();if(!t||/^data:/i.test(t))return t;try{await o();let e=/^(https?:\/\/|blob:|file:|\/)/i.test(t)?t:a(t);if(!e)return t;let n=s()?.key||``,r=await fetch(e,{headers:n?{Authorization:`Bearer ${n}`}:{}});return r.ok?`data:${r.headers.get(`content-type`)||`image/png`};base64,${b(await r.arrayBuffer())}`:t}catch{return t}}function S(e){if(!e)return``;let t=new Date(e);return isNaN(t.getTime())?e:`${String(t.getDate()).padStart(2,`0`)}/${String(t.getMonth()+1).padStart(2,`0`)}/${t.getFullYear()}`}function C({gasto:e,empresa:t,ticketConfig:n}){let r=d(),i=y(t),a=_(n.paper_width,80),o=a===58?230:300,s=a===58?210:250,c=a===58?200:240,l=new Date,f=`${l.toLocaleDateString(u().locale)} ${String(l.getHours()).padStart(2,`0`)}:${String(l.getMinutes()).padStart(2,`0`)}`,p=[];g(n.show_address)&&t.direccion&&p.push(t.direccion);let m=[];g(n.show_phone)&&t.telefono&&m.push(t.telefono),g(n.show_email)&&t.email&&m.push(t.email),m.length&&p.push(m.join(` / `)),g(n.show_legal)&&(t.legal||t.rnc)&&p.push(`RNC: ${t.legal||t.rnc}`);let h=p.length?`<p>${p.join(`<br>`)}</p>`:``;return`<!DOCTYPE html>
<html>
<head>
  <style>
    * { font-size: 10px; font-family: Arial, Helvetica, sans-serif; }
    @page { size: ${o}px auto; margin: 5px; }
    body { width: ${s}px; margin: 5px; padding: 5px; color: #111; }
    .ticket { width: ${c}px; padding-top: 10px; padding-bottom: 10px; }
    .bordeado { border: 1px solid #000; border-radius: 5px; padding: 5px; }
    .linea { width: 100%; border-top: 1px solid #000; margin: 6px 0; }
    table { width: 100%; border-collapse: collapse; }
    .precio { text-align: right; }
    .centrado { text-align: center; }
    .big { font-size: 1.35em !important; font-weight: bold; }
    .rojo { color: #dc2626; }
  </style>
</head>
<body>
  <div class="ticket">
    <center>
      <div class="logos">
        ${g(n.show_logo)&&i?`<img src="${i}" alt="Logo" style="max-width:100px">`:g(n.show_company_name)?`<div style="font-size:18px !important;font-weight:bold">${t.nombre||`MI EMPRESA`}</div>`:``}
      ${h}
    </center>

    <div class="bordeado" style="text-align:center;padding:4px;margin-top:6px;">
      COMPROBANTE DE GASTO
    </div>

    <table style="margin-top:6px;">
      <tr><td style="padding:2px 0"><b>No. Gasto:</b></td><td class="precio">${e.id||``}</td></tr>
      <tr><td style="padding:2px 0"><b>Fecha:</b></td><td class="precio">${S(e.fecha)}</td></tr>
      <tr><td style="padding:2px 0"><b>Hora:</b></td><td class="precio">${e.hora||``}</td></tr>
    </table>

    <div class="linea"></div>

    <div style="text-align:center;margin:8px 0;">
      <span class="big rojo">${r}${v(e.cantidad)}</span>
    </div>

    <table style="margin-top:6px;">
      <tr><td style="padding:2px 0"><b>Metodo:</b></td><td class="precio">${e.metodo_pago||`EFECTIVO`}</td></tr>
      ${String(e.metodo_pago||``).toUpperCase()===`MIXTO`?`
      <tr><td style="padding:2px 0">Efectivo:</td><td class="precio">${r}${v(e.efectivo||0)}</td></tr>
      <tr><td style="padding:2px 0">Transferencia:</td><td class="precio">${r}${v(e.transferencia||0)}</td></tr>`:``}
      ${e.banco_nombre?`<tr><td style="padding:2px 0"><b>Banco:</b></td><td class="precio">${e.banco_nombre}</td></tr>`:``}
    </table>

    ${e.comentario?`<div class="bordeado" style="margin-top:6px"><p><b>Concepto:</b><br>${String(e.comentario).replace(/\n/g,`<br>`)}</p></div>`:``}

    <div class="linea"></div>
    <div style="text-align:center;font-size:9px;color:#666;">Emitido: ${f}</div>

    ${g(n.show_footer)?`<div class="linea"></div><div style="text-align:center;">${n.footer_text||`Gracias por su preferencia`}</div>`:``}
  </div>
</body>
</html>`}async function w(e){let t={...h};try{let e=await window.db.getAll(`impresoras_config`);e.success&&e.data?.length>0&&(t={...t,...e.data[0]})}catch{}let n={};try{let e=await window.db.getAll(`empresa`);e.success&&e.data?.length>0&&(n=e.data[0])}catch{}let r=await x(n?.logoprinter||n?.logo);r&&(n={...n,logo:r,logoprinter:r});let i=C({gasto:e,empresa:n,ticketConfig:t}),a=t.printer_name||localStorage.getItem(`etiquetas_printer`)||``;try{let e=await window.electron.invoke(`print:ticket`,i,a||void 0);e.success?m.add({severity:`success`,summary:`Imprimiendo...`,life:2e3}):m.add({severity:`error`,summary:`Error`,detail:e.error,life:3e3})}catch(e){m.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}}return p({printTicket:w}),(i,a)=>(e(),t(`div`,f,[n(r(c))]))}});export{p as t};