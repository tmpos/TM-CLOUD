import{r as e}from"./rolldown-runtime-hePW80VL.js";import{j as t,v as n}from"./reactivity.esm-bundler-CYZ1FWiK.js";import{P as r,d as i,g as a,v as o}from"./runtime-core.esm-bundler-CyLDb3-k.js";import{A as s,Et as c,H as l}from"./index-C44qAKLU.js";import{t as u}from"./browser-Cpc4qo6y.js";import{i as d}from"./ticketLogoSettings-DWcskBne.js";import{t as f}from"./printImageService-exRYtwo5.js";import{n as p}from"./ticketPrinterSettings-DGEi3DvG.js";import{t as m}from"./JsBarcode-JyLvbIYG.js";var h=e(u()),g=e(m()),_={style:{display:`none`}},v=o({__name:`TicketTallerPrint`,setup(e,{expose:o}){let u=c(),m=n(``),v={printer_name:``,paper_width:80,show_logo:1,show_company_name:1,show_legal:1,show_phone:1,show_address:1,show_email:1,show_cliente:1,show_items:1,show_totals:1,show_barcode:1,show_footer:1,show_qr:0,footer_text:`Gracias por su compra`};function y(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function b(e){return e===!0||e===1||e===`1`}function x(e,t){let n=String(t?.almacen_uid||localStorage.getItem(`almacen_uid`)||localStorage.getItem(`almacen_default_uid`)||``),r=Number(t?.almacen_id||localStorage.getItem(`almacen_id`)||localStorage.getItem(`almacen_default_id`)||0);return n&&e.find(e=>String(e.uid||e.almacen_uid||``)===n)||r&&e.find(e=>Number(e.almacen_id||e.id)===r)||e[0]||{}}function S(e){return s(e)}function C(e){if(!e)return``;try{let t=document.createElementNS(`http://www.w3.org/2000/svg`,`svg`);return(0,g.default)(t,e,{format:`CODE128`,width:2,height:50,displayValue:!0,fontSize:12,margin:2}),new XMLSerializer().serializeToString(t).replace(/width="[^"]*"/,`width="180"`).replace(/height="[^"]*"/,`height="55"`)}catch{return``}}function w(e){let t=String(e.marca_modelo||``).trim();if(!t)return{marca:e.marca||``,modelo:e.modelo||``};let n=t.split(`/`).map(e=>e.trim());return{marca:e.marca||n[0]||``,modelo:e.modelo||n.slice(1).join(` / `)||``}}function T({orden:e,empresa:t,ticketConfig:n,qrCodeData:r}){let i=y(n.paper_width,80)===58?58:80,a=w(e),o=y(e.total),s=y(e.abono),c=y(e.pendiente,Math.max(0,o-s)),l=String(t.logoprinter||t.logo||``).trim(),u=C(e.no_orden||e.no_factura||String(e.id||``)),f=[];b(n.show_address)&&t.direccion&&f.push(t.direccion);let p=[];b(n.show_phone)&&t.telefono&&p.push(t.telefono),b(n.show_email)&&t.email&&p.push(t.email),p.length&&f.push(p.join(` / `)),b(n.show_legal)&&(t.legal||t.rnc)&&f.push(`RNC: ${t.legal||t.rnc}`);let m=f.length?`<div class="info"><p style="width:100%;text-align:center;">${f.join(`<br>`)}</p></div>`:``;return`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Orden Taller - ${e.no_orden||``}</title>
  <style>
    * { font-size: 10px; font-family: Arial, Helvetica, sans-serif; }
    @page { size: ${i}mm auto; margin: 0; }
    html { width: ${i}mm; margin: 0; padding: 0; background-color: #ffffff; }
    body { width: ${i}mm; box-sizing: border-box; margin: 0; padding: 0 ${i===58?3:4}mm; background-color: #ffffff; color: #000; }
    .ticket { width: 100%; box-sizing: border-box; padding-top: 10px; padding-bottom: 10px; }
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
        ${b(n.show_logo)&&l?`<img src="${l}" alt="Logo" style="${d(n.printer_name||localStorage.getItem(`etiquetas_printer`)||``)}">`:``}
        ${b(n.show_company_name)?`<div style="font-size:18px !important;font-weight:bold">${t.nombre||`MI EMPRESA`}</div>`:``}
        ${m}
      </div>
    </center>

    <div id="mid" class="bordeado">
      <div class="info">
        <p>
          Fecha: ${e.fecha_entrada||``}<br>
          DOC: <b style="font-size:16px">#${e.no_orden||e.no_factura||``}</b><br>
          ${b(n.show_cliente)?`CLIENTE: ${e.nombre||``}<br>`:``}
          ${b(n.show_cliente)&&e.cedula?`CEDULA: ${e.cedula}<br>`:``}
          ${b(n.show_cliente)&&e.telefono?`TELEFONO: ${e.telefono}<br>`:``}
          METODO DE PAGO: ${e.metodo_pago||``}
        </p>
      </div>
    </div>

    <div class="bordeado" style="text-align:center;padding:3px;margin-bottom:5px;margin-top:5px">
      RESUMEN DE LA ORDEN
    </div>

    ${b(n.show_items)?`<table class="contenedor">
      <tr><td class="label">EQUIPO:</td><td class="value">${e.equipo||``}</td></tr>
      <tr><td class="label">MARCA:</td><td class="value">${a.marca}</td></tr>
      <tr><td class="label">MODELO:</td><td class="value">${a.modelo}</td></tr>
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

    ${b(n.show_totals)?`<div class="linea" style="margin-top:10px;"></div>
      <div class="totales"><span>TOTAL: </span><span>${S(o)}</span></div>
      <div class="totales"><span>ABONADO: </span><span>${S(s)}</span></div>
      <div class="totales"><span>PENDIENTE: </span><span>${S(c)}</span></div>
    `:``}

    ${b(n.show_barcode)?`<div class="barcode"><center><div class="bordeado2" style="overflow:hidden;">${u}</div></center></div>`:``}

    ${b(n.show_qr)&&r?`<div style="text-align:center;"><img src="${r}" alt="QR" style="width:150px;position:relative;"></div>`:``}

    ${b(n.show_footer)?`<div class="linea" style="margin-top:8px;"></div><div style="text-align:center;">${n.footer_text||``}</div>`:``}
  </div>
</body>
</html>`}async function E(){let e={...v};try{let t=await window.db.getAll(`impresoras_config`);t.success&&t.data?.length>0&&(e={...e,...t.data[0]})}catch{}return e}async function D(e,t){let n={},r={};try{let[t,i]=await Promise.all([window.db.getAll(`empresa`),window.db.getAll(`almacenes`)]);t.success&&t.data?.length>0&&(n=x(t.data,e)),i.success&&i.data?.length>0&&(r=x(i.data,e))}catch{}n={...n,nombre:n?.nombre||r?.nombre||`MI EMPRESA`,direccion:n?.direccion||r?.direccion||``,telefono:n?.telefono||r?.telefono||``,email:n?.email||r?.email||``};let i=await f(n?.logoprinter||n?.logo||r?.logo);i&&(n={...n,logo:i,logoprinter:i});let a=``;try{a=await h.toDataURL(`https://tmposrd.com/taller/${e.no_orden||e.id||``}`)}catch{}return T({orden:e,empresa:n,ticketConfig:t,qrCodeData:a})}async function O(e,t=80){let n=await E();return n.paper_width=t,D(e,n)}async function k(e,t){let n=await E();t&&(n.paper_width=t),m.value=``;try{let e=await p(n.printer_name);m.value=e.name,n.printer_name=e.name,e.usedDefault&&u.add({severity:`info`,summary:`Impresora predeterminada`,detail:`La impresora guardada no está instalada en este equipo. Se enviará a la impresora predeterminada.`,life:5e3})}catch(e){return u.add({severity:`error`,summary:`Impresora no disponible`,detail:e?.message||`Selecciona una impresora en Configuración.`,life:6e3}),!1}let r=await D(e,n);try{let e=y(n.paper_width,80)===58?58:80,t=await window.electron.invoke(`print:ticket`,r,m.value,{width:e});return t.success?u.add({severity:`success`,summary:`Imprimiendo...`,detail:`Orden enviada a la impresora`,life:2e3}):u.add({severity:`error`,summary:`Error`,detail:t.error||`No se pudo imprimir`,life:3e3}),!!t.success}catch(e){return u.add({severity:`error`,summary:`Error`,detail:e.message||`Error al imprimir`,life:3e3}),!1}}return o({printTicket:k,previewTicket:O}),(e,n)=>(r(),i(`div`,_,[a(t(l))]))}});export{v as t};