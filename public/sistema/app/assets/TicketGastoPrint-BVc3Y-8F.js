import{A as e}from"./reactivity.esm-bundler-C6Po7tbM.js";import{P as t,d as n,g as r,v as i}from"./runtime-core.esm-bundler-EoPqhpgY.js";import{f as a,o,u as s}from"./tmCloudClient-BXA3SgwJ.js";import{F as c,P as l,V as u,wt as d}from"./index-JSEo2EM7.js";import{i as f}from"./ticketLogoSettings-DWcskBne.js";var p={style:{display:`none`}},m=i({__name:`TicketGastoPrint`,setup(i,{expose:m}){let h=d(),g={printer_name:``,paper_width:80,show_logo:1,show_company_name:1,show_legal:1,show_phone:1,show_address:1,show_email:1,show_footer:1,footer_text:`Gracias por su preferencia`};function _(e){return e===!0||e===1||e===`1`}function v(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function y(e){return v(e).toFixed(2)}function b(e){return String(e?.logoprinter||e?.logo||``).trim()}function x(e){let t=new Uint8Array(e),n=``,r=32768;for(let e=0;e<t.length;e+=r)n+=String.fromCharCode(...t.subarray(e,e+r));return btoa(n)}async function S(e){let t=String(e||``).trim();if(!t||/^data:/i.test(t))return t;try{await o();let e=/^(https?:\/\/|blob:|file:|\/)/i.test(t)?t:a(t);if(!e)return t;let n=s()?.key||``,r=await fetch(e,{headers:n?{Authorization:`Bearer ${n}`}:{}});return r.ok?`data:${r.headers.get(`content-type`)||`image/png`};base64,${x(await r.arrayBuffer())}`:t}catch{return t}}function C(e){if(!e)return``;let t=new Date(e);return isNaN(t.getTime())?e:`${String(t.getDate()).padStart(2,`0`)}/${String(t.getMonth()+1).padStart(2,`0`)}/${t.getFullYear()}`}function w({gasto:e,empresa:t,ticketConfig:n}){let r=c(),i=b(t),a=v(n.paper_width,80),o=a===58?230:300,s=a===58?210:250,u=a===58?200:240,d=new Date,p=`${d.toLocaleDateString(l().locale)} ${String(d.getHours()).padStart(2,`0`)}:${String(d.getMinutes()).padStart(2,`0`)}`,m=[];_(n.show_address)&&t.direccion&&m.push(t.direccion);let h=[];_(n.show_phone)&&t.telefono&&h.push(t.telefono),_(n.show_email)&&t.email&&h.push(t.email),h.length&&m.push(h.join(` / `)),_(n.show_legal)&&(t.legal||t.rnc)&&m.push(`RNC: ${t.legal||t.rnc}`);let g=m.length?`<p>${m.join(`<br>`)}</p>`:``;return`<!DOCTYPE html>
<html>
<head>
  <style>
    * { font-size: 10px; font-family: Arial, Helvetica, sans-serif; }
    @page { size: ${o}px auto; margin: 5px; }
    body { width: ${s}px; margin: 5px; padding: 5px; color: #111; }
    .ticket { width: ${u}px; padding-top: 10px; padding-bottom: 10px; }
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
        ${_(n.show_logo)&&i?`<img src="${i}" alt="Logo" style="max-width:100px;${f(n.printer_name||localStorage.getItem(`etiquetas_printer`)||``)}">`:_(n.show_company_name)?`<div style="font-size:18px !important;font-weight:bold">${t.nombre||`MI EMPRESA`}</div>`:``}
      ${g}
    </center>

    <div class="bordeado" style="text-align:center;padding:4px;margin-top:6px;">
      COMPROBANTE DE GASTO
    </div>

    <table style="margin-top:6px;">
      <tr><td style="padding:2px 0"><b>No. Gasto:</b></td><td class="precio">${e.id||``}</td></tr>
      <tr><td style="padding:2px 0"><b>Fecha:</b></td><td class="precio">${C(e.fecha)}</td></tr>
      <tr><td style="padding:2px 0"><b>Hora:</b></td><td class="precio">${e.hora||``}</td></tr>
    </table>

    <div class="linea"></div>

    <div style="text-align:center;margin:8px 0;">
      <span class="big rojo">${r}${y(e.cantidad)}</span>
    </div>

    <table style="margin-top:6px;">
      <tr><td style="padding:2px 0"><b>Metodo:</b></td><td class="precio">${e.metodo_pago||`EFECTIVO`}</td></tr>
      ${String(e.metodo_pago||``).toUpperCase()===`MIXTO`?`
      <tr><td style="padding:2px 0">Efectivo:</td><td class="precio">${r}${y(e.efectivo||0)}</td></tr>
      <tr><td style="padding:2px 0">Transferencia:</td><td class="precio">${r}${y(e.transferencia||0)}</td></tr>`:``}
      ${e.banco_nombre?`<tr><td style="padding:2px 0"><b>Banco:</b></td><td class="precio">${e.banco_nombre}</td></tr>`:``}
    </table>

    ${e.comentario?`<div class="bordeado" style="margin-top:6px"><p><b>Concepto:</b><br>${String(e.comentario).replace(/\n/g,`<br>`)}</p></div>`:``}

    <div class="linea"></div>
    <div style="text-align:center;font-size:9px;color:#666;">Emitido: ${p}</div>

    ${_(n.show_footer)?`<div class="linea"></div><div style="text-align:center;">${n.footer_text||`Gracias por su preferencia`}</div>`:``}
  </div>
</body>
</html>`}async function T(e){let t={...g};try{let e=await window.db.getAll(`impresoras_config`);e.success&&e.data?.length>0&&(t={...t,...e.data[0]})}catch{}let n={};try{let e=await window.db.getAll(`empresa`);e.success&&e.data?.length>0&&(n=e.data[0])}catch{}let r=await S(n?.logoprinter||n?.logo);r&&(n={...n,logo:r,logoprinter:r});let i=w({gasto:e,empresa:n,ticketConfig:t}),a=t.printer_name||localStorage.getItem(`etiquetas_printer`)||``;try{let e=await window.electron.invoke(`print:ticket`,i,a||void 0);e.success?h.add({severity:`success`,summary:`Imprimiendo...`,life:2e3}):h.add({severity:`error`,summary:`Error`,detail:e.error,life:3e3})}catch(e){h.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}}return m({printTicket:T}),(i,a)=>(t(),n(`div`,p,[r(e(u))]))}});export{m as t};