import{j as e}from"./reactivity.esm-bundler-CYZ1FWiK.js";import{P as t,d as n,g as r,v as i}from"./runtime-core.esm-bundler-CyLDb3-k.js";import{t as a}from"./almacen.store-BYgHbYN0.js";import{Et as o,F as s,H as c,I as l}from"./index-nD0f1mfo.js";import{i as u}from"./ticketLogoSettings-DWcskBne.js";import{t as d}from"./printImageService-DTxmYonb.js";import{n as f}from"./ticketPrinterSettings-DGEi3DvG.js";var p={style:{display:`none`}},m=i({__name:`TicketCuentaCobrarPrint`,setup(i,{expose:m}){let h=o(),g=a(),_={printer_name:``,paper_width:80,show_logo:1,show_company_name:1,show_legal:1,show_phone:1,show_address:1,show_email:1,show_cliente:1,show_items:1,show_totals:1,show_barcode:1,show_footer:1,show_qr:0,footer_text:`Gracias por su compra`};function v(e){return e===!0||e===1||e===`1`}function y(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function b(e){return y(e).toFixed(2)}function x(e){return String(e??``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`).replace(/'/g,`&#39;`)}function S(e){try{let t=typeof e==`string`?JSON.parse(e||`[]`):e;return Array.isArray(t)?t:[]}catch{return[]}}function C(e,t,n,r){let i=x(l()),a=y(r.paper_width,80)===58?58:72,o=a,c=e=>`${i}${b(e)}`,d=Math.round(t.reduce((e,t)=>e+y(t.total),0)*100)/100,f=Math.round(t.reduce((e,t)=>e+y(t.abonado),0)*100)/100,p=Math.round(t.reduce((e,t)=>e+Math.max(0,y(t.saldo)),0)*100)/100,m=t.flatMap(e=>S(e.pagos).map(t=>({...t,invoice:e.no_factura||e.id,sortKey:String(t.created_at||`${t.fecha||``}T${t.hora||``}`)}))).sort((e,t)=>t.sortKey.localeCompare(e.sortKey)),h=String(n.logoprinter||n.logo||``),g=v(r.show_logo)&&h?`<img src="${x(h)}" alt="Logo" style="max-width:100px;${u(r.printer_name||``)}">`:v(r.show_company_name)?`<strong class="company">${x(n.nombre||`MI EMPRESA`)}</strong>`:``,_=t.map(e=>`<tr>
    <td><b>#${x(e.no_factura||e.id)}</b><br><small>${x(e.fecha_venta||``)}</small></td>
    <td class="amount">${c(e.total)}</td><td class="amount">${c(e.abonado)}</td><td class="amount">${c(e.saldo)}</td>
  </tr>`).join(``),C=m.map(e=>`<tr>
    <td>#${x(e.invoice)}<br><small>${x(e.fecha||``)} ${x(e.hora||``)}</small></td>
    <td>${x(e.metodo||e.metodo_pago||`ABONO`)}${e.banco_nombre?`<br><small>${x(e.banco_nombre)}</small>`:``}</td>
    <td class="amount">${c(e.cantidad??e.monto)}</td>
  </tr>`).join(``)||`<tr><td colspan="3">Sin abonos registrados</td></tr>`,w=new Date,T=`${w.toLocaleDateString(s().locale)} ${String(w.getHours()).padStart(2,`0`)}:${String(w.getMinutes()).padStart(2,`0`)}`;return`<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
    *{font-family:Arial,Helvetica,sans-serif;font-size:10px;box-sizing:border-box}
    @page{size:${a}mm auto;margin:0}body{width:${o}mm;margin:0;padding:3mm;color:#111}
    .center{text-align:center}.company{font-size:16px}.section{border:1px solid #111;border-radius:4px;padding:5px;margin:8px 0 4px;text-align:center;font-weight:bold}
    table{width:100%;border-collapse:collapse}th,td{padding:4px 2px;border-bottom:1px solid #bbb;vertical-align:top}th{text-align:left}
    .amount{text-align:right;white-space:nowrap}small{font-size:8px}.total{font-size:12px;font-weight:bold}
  </style></head><body>
    <div class="center">${g}<p>${x(n.direccion||``)}<br>${x(n.telefono||``)}</p></div>
    <div class="section">ESTADO DE CUENTA GENERAL</div>
    <p>Fecha: ${x(T)}<br>Cliente: <b>${x(e.nombre||`SIN REGISTRO`)}</b>${e.telefono?`<br>Teléfono: ${x(e.telefono)}`:``}<br>Cuentas: ${t.length}</p>
    <table><thead><tr><th>Factura</th><th class="amount">Total</th><th class="amount">Abon.</th><th class="amount">Saldo</th></tr></thead><tbody>${_}</tbody></table>
    <table><tbody><tr><td>Total facturado</td><td class="amount">${c(d)}</td></tr>
      <tr><td>Total abonado</td><td class="amount">${c(f)}</td></tr>
      <tr><td class="total">Saldo pendiente</td><td class="amount total">${c(p)}</td></tr></tbody></table>
    <div class="section">HISTORIAL DE ABONOS</div>
    <table><thead><tr><th>Factura / fecha</th><th>Método</th><th class="amount">Monto</th></tr></thead><tbody>${C}</tbody></table>
    <p class="center">Generado por TMPOS</p>
  </body></html>`}function w(e){return String(e?.logoprinter||e?.logo||``).trim()}function T(e){if(!e)return``;let t=new Date(e);return isNaN(t.getTime())?e:`${String(t.getDate()).padStart(2,`0`)}/${String(t.getMonth()+1).padStart(2,`0`)}/${t.getFullYear()}`}function E({cuenta:e,empresa:t,monto:n,abonadoTotal:r,saldoRestante:i,ticketConfig:a,productos:o,factura:c}){let d=l(),f=w(t),p=y(a.paper_width,80)===58?58:72,m=p-6,h=m,g=n>0?`RECIBO DE ABONO`:`ESTADO DE CUENTA`,_=new Date,x=`${_.toLocaleDateString(s().locale)} ${String(_.getHours()).padStart(2,`0`)}:${String(_.getMinutes()).padStart(2,`0`)}`,S=e.fecha_venta?T(e.fecha_venta):``,C=e.fecha_vencimiento?T(e.fecha_vencimiento):``,E=o.length>0?o.map(e=>{let t=e.nombre||e.descripcion||e.producto||`Producto`,n=y(e.cantidad??e.quantity,1),r=y(e.precio_venta??e.precio_unitario??e.precio??e.price),i=y(e.total,r*n);return`<tr>
          <td>${t}</td>
          <td class="centrado">${n}</td>
          <td class="precio">${d}${b(r)}</td>
          <td class="precio"><b>${d}${b(i)}</b></td>
        </tr>`}).join(``):``,D=[];v(a.show_address)&&t.direccion&&D.push(t.direccion);let O=[];v(a.show_phone)&&t.telefono&&O.push(t.telefono),v(a.show_email)&&t.email&&O.push(t.email),O.length&&D.push(O.join(` / `)),v(a.show_legal)&&(t.legal||t.rnc)&&D.push(`RNC: ${t.legal||t.rnc}`);let k=D.length?`<p>${D.join(`<br>`)}</p>`:``,A=c?`Metodo: ${c.metodo_pago||`CREDITO`}<br>
       ${c.vendedor?`Vendedor: ${c.vendedor}<br>`:``}
       ${c.cajero?`Cajero: ${c.cajero}<br>`:``}`:``,j=[];try{j=JSON.parse(e.pagos||`[]`),Array.isArray(j)||(j=[])}catch{j=[]}let M=j.length>0?j.map(e=>`
      <tr>
        <td>Pago #${e.nopago||``}<br><small>${e.metodo||e.metodo_pago||`ABONO`}${e.banco_nombre?` · ${e.banco_nombre}`:``}</small></td>
        <td>${e.fecha||``} ${e.hora||``}</td>
        <td class="precio"><b>${d}${b(e.cantidad)}</b></td>
      </tr>
    `).join(``):`<tr><td colspan="3">Sin abonos registrados</td></tr>`;return`<!DOCTYPE html>
<html>
<head>
  <style>
    * { font-size: 10px; font-family: Arial, Helvetica, sans-serif; }
    @page { size: ${p}mm auto; margin: 0; }
    body { width: ${m}mm; margin: 0; padding: 3mm; color: #111; }
    .ticket { width: ${h}mm; padding-top: 10px; padding-bottom: 10px; }
    .bordeado { border: 1px solid #000; border-radius: 5px; padding: 5px; }
    .linea { width: 100%; border-top: 1px solid #000; margin: 6px 0; }
    table { width: 100%; border-collapse: separate; border-spacing: 0; }
    th { text-align: left; padding: 5px 0; border-bottom: 1px solid #000; }
    .precio { text-align: right; }
    .centrado { text-align: center; }
    .big { font-size: 1.35em !important; font-weight: bold; }
  </style>
</head>
<body>
  <div class="ticket">
    <center>
      <div class="logos">
        ${v(a.show_logo)&&f?`<img src="${f}" alt="Logo" style="max-width:100px;${u(a.printer_name||localStorage.getItem(`etiquetas_printer`)||``)}">`:v(a.show_company_name)?`<div style="font-size:18px !important;font-weight:bold">${t.nombre||`MI EMPRESA`}</div>`:``}
      ${k}
    </center>

    <div class="bordeado">
      <p>
        <b>${g}</b><br>
        Fecha: ${x}<br>
        Factura Ref: <b style="font-size:14px">#${e.no_factura||``}</b><br>
        ${v(a.show_cliente)?`Cliente: ${e.nombre_cliente||`SIN REGISTRO`}<br>`:``}
        ${v(a.show_cliente)&&e.telefono_cliente?`Telefono: ${e.telefono_cliente}<br>`:``}
        ${S?`Fecha Venta: ${S}<br>`:``}
        ${C?`Vencimiento: ${C}<br>`:``}
        ${A}
        Estado: <b>${e.estado||`ACTIVA`}</b>
      </p>
    </div>

    ${E?`
    <div class="bordeado" style="text-align:center;padding:4px;margin-top:6px;">
      PRODUCTOS A CREDITO
    </div>

    <table>
      <thead>
        <tr>
          <th>Producto</th>
          <th class="centrado">Cant.</th>
          <th class="precio">Precio</th>
          <th class="precio">Total</th>
        </tr>
      </thead>
      <tbody>
        ${E}
      </tbody>
    </table>
    `:``}

    <div class="bordeado" style="text-align:center;padding:4px;margin-top:6px;">
      DETALLE DE CUENTA
    </div>

    <table>
      <thead>
        <tr>
          <th>Concepto</th>
          <th class="precio">Monto</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Total Factura</td>
          <td class="precio"><b>${d}${b(e.total)}</b></td>
        </tr>
        <tr>
          <td>Abonado Anterior</td>
          <td class="precio">${d}${b(e.abonado||0)}</td>
        </tr>
        ${n>0?`<tr style="color:#059669;font-weight:bold">
          <td style="color:#059669;font-weight:bold">Nuevo Abono</td>
          <td class="precio" style="color:#059669;font-weight:bold">${d}${b(n)}</td>
        </tr>`:``}
      </tbody>
    </table>

    <div class="bordeado" style="text-align:center;padding:4px;margin-top:6px;">
      ABONOS REALIZADOS
    </div>

    <table>
      <thead>
        <tr>
          <th>Pago / metodo</th>
          <th>Fecha</th>
          <th class="precio">Monto</th>
        </tr>
      </thead>
      <tbody>
        ${M}
      </tbody>
    </table>

    <div class="linea" style="margin-top: 12px;"></div>

    <div style="font-weight:bold;">
      <table>
        <tr>
          <td>TOTAL ABONADO:</td>
          <td class="precio"><span class="big">${d}${b(r)}</span></td>
        </tr>
      </table>
    </div>

    <div style="font-weight:bold;">
      <table>
        <tr>
          <td style="color:#dc2626">SALDO PENDIENTE:</td>
          <td class="precio"><span class="big" style="color:#dc2626">${d}${b(i)}</span></td>
        </tr>
      </table>
    </div>

    ${i<=0?`<div style="text-align:center;font-size:12px;color:#059669;font-weight:bold;border:2px solid #059669;border-radius:5px;padding:6px;margin:8px 0">CUENTA PAGADA EN SU TOTALIDAD</div>`:``}

    ${e.notas?`<div class="bordeado" style="margin-top:6px"><p>${String(e.notas).replace(/\n/g,`<br>`)}</p></div>`:``}

    ${v(a.show_footer)?`<div class="linea"></div><div style="text-align:center;">${a.footer_text||`Gracias por su preferencia`}</div>`:``}
  </div>
</body>
</html>`}async function D(e,t){let n=await f(t.printer_name||``);n.usedDefault&&h.add({severity:`info`,summary:`Impresora predeterminada`,detail:`La impresora guardada no está instalada en este equipo. Se enviará a la impresora predeterminada.`,life:5e3});let r=y(t.paper_width,80)===58?58:72,i=await window.electron.invoke(`print:ticket`,e,n.name,{width:r});if(!i?.success)throw Error(i?.error||`No se pudo imprimir el ticket.`)}async function O(e,t=0,n=0,r=0){let i={..._};try{let e=await window.db.getAll(`impresoras_config`);e.success&&e.data?.length>0&&(i={...i,...e.data[0]})}catch{}let a={};try{await g.load();let e=await window.db.getAll(`empresa`);if(e.success&&e.data?.length>0){a=g.activeUid&&e.data.find(e=>String(e.uid||e.almacen_uid||``)===String(g.activeUid))||e.data.find(e=>Number(e.almacen_id||e.id)===Number(g.activeId))||e.data[0];let t=await d(a.logoprinter||a.logo);t&&(a={...a,logo:t,logoprinter:t})}}catch{}let o=null,s=[];try{let t=await window.db.getAll(`facturas`);if(t.success&&t.data&&(o=t.data.find(t=>String(t.no_factura||``)===String(e.no_factura||``)&&(e.almacen_uid&&t.almacen_uid?String(t.almacen_uid)===String(e.almacen_uid):Number(t.almacen_id||0)===Number(e.almacen_id||0)))||null,o))try{let e=typeof o.productos==`string`?JSON.parse(o.productos):o.productos;s=Array.isArray(e)?e:[]}catch{s=[]}}catch{}let c=E({cuenta:e,empresa:a,monto:t,abonadoTotal:n,saldoRestante:r,ticketConfig:i,productos:s,factura:o});try{await D(c,i),h.add({severity:`success`,summary:`Imprimiendo...`,life:2e3})}catch(e){h.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}}async function k(e,t){if(!t.length)throw Error(`Este cliente no tiene cuentas por cobrar.`);let n={..._};try{let e=await window.db.getAll(`impresoras_config`);e.success&&e.data?.length&&(n={...n,...e.data[0]})}catch{}let r={};try{await g.load();let e=await window.db.getAll(`empresa`);if(e.success&&e.data?.length){r=g.activeUid&&e.data.find(e=>String(e.uid||e.almacen_uid||``)===String(g.activeUid))||e.data.find(e=>Number(e.almacen_id||e.id)===Number(g.activeId))||e.data[0];let t=await d(r.logoprinter||r.logo);t&&(r={...r,logo:t,logoprinter:t})}}catch{}await D(C(e,t,r,n),n),h.add({severity:`success`,summary:`Imprimiendo estado de cuenta`,life:2e3})}return m({printTicket:O,printGeneralTicket:k}),(i,a)=>(t(),n(`div`,p,[r(e(c))]))}});export{m as t};