import{r as e}from"./rolldown-runtime-hePW80VL.js";import{N as t,d as n,g as r,nt as i,st as a,v as o}from"./runtime-core.esm-bundler-a6pTXghO.js";import{C as s,I as c,i as l}from"./index-BMrcxrjI.js";import{t as u}from"./browser-Cpc4qo6y.js";import{t as d}from"./JsBarcode-JyLvbIYG.js";import{t as f}from"./printImageService-MUfUTrDy.js";var p=e(u()),m=e(d()),h={style:{display:`none`}},g=o({__name:`TicketTallerPrint`,setup(e,{expose:o}){let u=c(),d=i(``),g={printer_name:``,paper_width:80,show_logo:1,show_company_name:1,show_legal:1,show_phone:1,show_address:1,show_email:1,show_cliente:1,show_items:1,show_totals:1,show_barcode:1,show_footer:1,show_qr:0,footer_text:`Gracias por su compra`};function _(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function v(e){return e===!0||e===1||e===`1`}function y(e,t){let n=String(t?.almacen_uid||localStorage.getItem(`almacen_uid`)||localStorage.getItem(`almacen_default_uid`)||``),r=Number(t?.almacen_id||localStorage.getItem(`almacen_id`)||localStorage.getItem(`almacen_default_id`)||0);return n&&e.find(e=>String(e.uid||e.almacen_uid||``)===n)||r&&e.find(e=>Number(e.almacen_id||e.id)===r)||e[0]||{}}function b(e){return l(e)}function x(e){if(!e)return``;try{let t=document.createElementNS(`http://www.w3.org/2000/svg`,`svg`);return(0,m.default)(t,e,{format:`CODE128`,width:2,height:50,displayValue:!0,fontSize:12,margin:2}),new XMLSerializer().serializeToString(t).replace(/width="[^"]*"/,`width="180"`).replace(/height="[^"]*"/,`height="55"`)}catch{return``}}function S(e){let t=String(e.marca_modelo||``).trim();if(!t)return{marca:e.marca||``,modelo:e.modelo||``};let n=t.split(`/`).map(e=>e.trim());return{marca:e.marca||n[0]||``,modelo:e.modelo||n.slice(1).join(` / `)||``}}function C({orden:e,empresa:t,ticketConfig:n,qrCodeData:r}){let i=_(n.paper_width,80),a=i===58?230:300,o=i===58?210:250,s=i===58?200:240,c=S(e),l=_(e.total),u=_(e.abono),d=_(e.pendiente,Math.max(0,l-u)),f=String(t.logoprinter||t.logo||``).trim(),p=x(e.no_orden||e.no_factura||String(e.id||``)),m=[];v(n.show_address)&&t.direccion&&m.push(t.direccion);let h=[];v(n.show_phone)&&t.telefono&&h.push(t.telefono),v(n.show_email)&&t.email&&h.push(t.email),h.length&&m.push(h.join(` / `)),v(n.show_legal)&&(t.legal||t.rnc)&&m.push(`RNC: ${t.legal||t.rnc}`);let g=m.length?`<div class="info"><p style="width:100%;text-align:center;">${m.join(`<br>`)}</p></div>`:``;return`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Orden Taller - ${e.no_orden||``}</title>
  <style>
    * { font-size: 10px; font-family: Arial, Helvetica, sans-serif; }
    @page { size: ${a}px auto; margin: 5px; }
    html, body { background-color: #ffffff; }
    body { width: ${o}px; margin: 5px; padding: 5px; background-color: #ffffff; color: #000; }
    .ticket { width: ${s}px; padding-top: 10px; padding-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; border-spacing: 0 !important; }
    td, th { vertical-align: top; }
    .bordeado { border: 1px solid #000; border-radius: 5px; padding-left: 5px; }
    .bordeado2 { border: 1px solid #000; border-radius: 5px; padding: 3px; max-width: 150px; margin-top: 5px; }
    .contenedor { border: 2px solid #000; border-radius: 5px; box-sizing: border-box; padding: 6px; width: 100%; }
    .linea { width: 100%; border-top: 1px solid #000; padding-top: 5px; padding-bottom: 5px; margin-bottom: 5px; }
    .info { display: flex; justify-content: space-between; align-items: flex-start; }
    .logos img { display: block; width: auto; height: auto; max-width: 100px; max-height: 72px; margin: 0 auto 4px; object-fit: contain; }
    .label { width: 42%; font-weight: bold; padding: 2px 3px 2px 0; }
    .value { width: 58%; padding: 2px 0; word-break: break-word; white-space: pre-wrap; }
    .totales { text-align: right; font-weight: bold; padding-right: 10px; }
  </style>
</head>
<body>
  <div class="ticket">
    <center id="top">
      <div class="logos" style="text-align:center;">
        ${v(n.show_logo)&&f?`<img src="${f}" alt="Logo">`:``}
        ${v(n.show_company_name)?`<div style="font-size:18px !important;font-weight:bold">${t.nombre||`MI EMPRESA`}</div>`:``}
        ${g}
      </div>
    </center>

    <div id="mid" class="bordeado">
      <div class="info">
        <p>
          Fecha: ${e.fecha_entrada||``}<br>
          DOC: <b style="font-size:16px">#${e.no_orden||e.no_factura||``}</b><br>
          ${v(n.show_cliente)?`CLIENTE: ${e.nombre||``}<br>`:``}
          ${v(n.show_cliente)&&e.cedula?`CEDULA: ${e.cedula}<br>`:``}
          ${v(n.show_cliente)&&e.telefono?`TELEFONO: ${e.telefono}<br>`:``}
          METODO DE PAGO: ${e.metodo_pago||``}
        </p>
      </div>
    </div>

    <div class="bordeado" style="text-align:center;padding:3px;margin-bottom:5px;margin-top:5px">
      RESUMEN DE LA ORDEN
    </div>

    ${v(n.show_items)?`<table class="contenedor">
      <tr><td class="label">EQUIPO:</td><td class="value">${e.equipo||``}</td></tr>
      <tr><td class="label">MARCA:</td><td class="value">${c.marca}</td></tr>
      <tr><td class="label">MODELO:</td><td class="value">${c.modelo}</td></tr>
      <tr><td class="label">IMEI:</td><td class="value">${e.imei||``}</td></tr>
      <tr><td class="label">SERIAL:</td><td class="value">${e.serial||``}</td></tr>
      <tr><td class="label">CLAVE:</td><td class="value">${e.clave?`NO SE REVELA`:``}</td></tr>
      <tr><td class="label">ACCESORIOS:</td><td class="value">${e.accesorios||``}</td></tr>
      <tr><td class="label">FALLAS:</td><td class="value">${e.fallas||``}</td></tr>
      <tr><td class="label">PIEZAS CAMBIADAS:</td><td class="value">${e.piezas||``}</td></tr>
      <tr><td class="label">TECNICO ASIGNADO:</td><td class="value">${e.tecnico||``}</td></tr>
      <tr><td class="label">FECHA ENTRADA:</td><td class="value">${e.fecha_entrada||``}</td></tr>
      <tr><td class="label">FECHA ENTREGA:</td><td class="value">${e.fecha_entrega||``}</td></tr>
      <tr><td class="label">ESTADO:</td><td class="value"><b>${e.estado||``}</b></td></tr>
    </table>`:``}

    ${v(n.show_totals)?`<div class="linea" style="margin-top:10px;"></div>
      <div class="totales"><span>TOTAL: </span><span>${b(l)}</span></div>
      <div class="totales"><span>ABONADO: </span><span>${b(u)}</span></div>
      <div class="totales"><span>PENDIENTE: </span><span>${b(d)}</span></div>
    `:``}

    ${v(n.show_barcode)?`<div class="barcode"><center><div class="bordeado2" style="overflow:hidden;">${p}</div></center></div>`:``}

    ${v(n.show_qr)&&r?`<div style="text-align:center;"><img src="${r}" alt="QR" style="width:150px;position:relative;"></div>`:``}

    ${v(n.show_footer)?`<div class="linea" style="margin-top:8px;"></div><div style="text-align:center;">${n.footer_text||``}</div>`:``}
  </div>
</body>
</html>`}async function w(e){let t={...g};try{let e=await window.db.getAll(`impresoras_config`);e.success&&e.data?.length>0&&(t={...t,...e.data[0]},d.value=t.printer_name||``)}catch{}let n={},r={};try{let[t,i]=await Promise.all([window.db.getAll(`empresa`),window.db.getAll(`almacenes`)]);t.success&&t.data?.length>0&&(n=y(t.data,e)),i.success&&i.data?.length>0&&(r=y(i.data,e))}catch{}n={...n,nombre:n?.nombre||r?.nombre||`MI EMPRESA`,direccion:n?.direccion||r?.direccion||``,telefono:n?.telefono||r?.telefono||``,email:n?.email||r?.email||``};let i=await f(n?.logoprinter||n?.logo||r?.logo);i&&(n={...n,logo:i,logoprinter:i});let a=``;try{a=await p.toDataURL(`https://tmposrd.com/taller/${e.no_orden||e.id||``}`)}catch{}let o=C({orden:e,empresa:n,ticketConfig:t,qrCodeData:a});try{let e=await window.electron.invoke(`print:ticket`,o,d.value||void 0);e.success?u.add({severity:`success`,summary:`Imprimiendo...`,detail:`Orden enviada a la impresora`,life:2e3}):u.add({severity:`error`,summary:`Error`,detail:e.error||`No se pudo imprimir`,life:3e3})}catch(e){u.add({severity:`error`,summary:`Error`,detail:e.message||`Error al imprimir`,life:3e3})}}return o({printTicket:w}),(e,i)=>(t(),n(`div`,h,[r(a(s))]))}});export{g as t};