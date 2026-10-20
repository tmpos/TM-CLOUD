import{A as e}from"./reactivity.esm-bundler-C6Po7tbM.js";import{P as t,d as n,g as r,v as i}from"./runtime-core.esm-bundler-EoPqhpgY.js";import{t as a}from"./almacen.store-z7qrn8ih.js";import{F as o,P as s,V as c,wt as l}from"./index-ptAt_IIi.js";import{i as u}from"./ticketLogoSettings-DWcskBne.js";import{t as d}from"./printImageService-BQdZNYcQ.js";var f={style:{display:`none`}},p=i({__name:`TicketCuentaCobrarPrint`,setup(i,{expose:p}){let m=l(),h=a(),g={printer_name:``,paper_width:80,show_logo:1,show_company_name:1,show_legal:1,show_phone:1,show_address:1,show_email:1,show_cliente:1,show_items:1,show_totals:1,show_barcode:1,show_footer:1,show_qr:0,footer_text:`Gracias por su compra`};function _(e){return e===!0||e===1||e===`1`}function v(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function y(e){return v(e).toFixed(2)}function b(e){return String(e?.logoprinter||e?.logo||``).trim()}function x(e){if(!e)return``;let t=new Date(e);return isNaN(t.getTime())?e:`${String(t.getDate()).padStart(2,`0`)}/${String(t.getMonth()+1).padStart(2,`0`)}/${t.getFullYear()}`}function S({cuenta:e,empresa:t,monto:n,abonadoTotal:r,saldoRestante:i,ticketConfig:a,productos:c,factura:l}){let d=o(),f=b(t),p=v(a.paper_width,80),m=p===58?230:300,h=p===58?210:250,g=p===58?200:240,S=n>0?`RECIBO DE ABONO`:`ESTADO DE CUENTA`,C=new Date,w=`${C.toLocaleDateString(s().locale)} ${String(C.getHours()).padStart(2,`0`)}:${String(C.getMinutes()).padStart(2,`0`)}`,T=e.fecha_venta?x(e.fecha_venta):``,E=e.fecha_vencimiento?x(e.fecha_vencimiento):``,D=c.length>0?c.map(e=>{let t=e.nombre||e.descripcion||e.producto||`Producto`,n=v(e.cantidad??e.quantity,1),r=v(e.precio_venta??e.precio_unitario??e.precio??e.price),i=v(e.total,r*n);return`<tr>
          <td>${t}</td>
          <td class="centrado">${n}</td>
          <td class="precio">${d}${y(r)}</td>
          <td class="precio"><b>${d}${y(i)}</b></td>
        </tr>`}).join(``):``,O=[];_(a.show_address)&&t.direccion&&O.push(t.direccion);let k=[];_(a.show_phone)&&t.telefono&&k.push(t.telefono),_(a.show_email)&&t.email&&k.push(t.email),k.length&&O.push(k.join(` / `)),_(a.show_legal)&&(t.legal||t.rnc)&&O.push(`RNC: ${t.legal||t.rnc}`);let A=O.length?`<p>${O.join(`<br>`)}</p>`:``,j=l?`Metodo: ${l.metodo_pago||`CREDITO`}<br>
       ${l.vendedor?`Vendedor: ${l.vendedor}<br>`:``}
       ${l.cajero?`Cajero: ${l.cajero}<br>`:``}`:``,M=[];try{M=JSON.parse(e.pagos||`[]`),Array.isArray(M)||(M=[])}catch{M=[]}let N=M.length>0?M.map(e=>`
      <tr>
        <td>Pago #${e.nopago||``}<br><small>${e.metodo||e.metodo_pago||`ABONO`}${e.banco_nombre?` · ${e.banco_nombre}`:``}</small></td>
        <td>${e.fecha||``} ${e.hora||``}</td>
        <td class="precio"><b>${d}${y(e.cantidad)}</b></td>
      </tr>
    `).join(``):`<tr><td colspan="3">Sin abonos registrados</td></tr>`;return`<!DOCTYPE html>
<html>
<head>
  <style>
    * { font-size: 10px; font-family: Arial, Helvetica, sans-serif; }
    @page { size: ${m}px auto; margin: 5px; }
    body { width: ${h}px; margin: 5px; padding: 5px; color: #111; }
    .ticket { width: ${g}px; padding-top: 10px; padding-bottom: 10px; }
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
        ${_(a.show_logo)&&f?`<img src="${f}" alt="Logo" style="max-width:100px;${u(a.printer_name||localStorage.getItem(`etiquetas_printer`)||``)}">`:_(a.show_company_name)?`<div style="font-size:18px !important;font-weight:bold">${t.nombre||`MI EMPRESA`}</div>`:``}
      ${A}
    </center>

    <div class="bordeado">
      <p>
        <b>${S}</b><br>
        Fecha: ${w}<br>
        Factura Ref: <b style="font-size:14px">#${e.no_factura||``}</b><br>
        ${_(a.show_cliente)?`Cliente: ${e.nombre_cliente||`SIN REGISTRO`}<br>`:``}
        ${_(a.show_cliente)&&e.telefono_cliente?`Telefono: ${e.telefono_cliente}<br>`:``}
        ${T?`Fecha Venta: ${T}<br>`:``}
        ${E?`Vencimiento: ${E}<br>`:``}
        ${j}
        Estado: <b>${e.estado||`ACTIVA`}</b>
      </p>
    </div>

    ${D?`
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
        ${D}
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
          <td class="precio"><b>${d}${y(e.total)}</b></td>
        </tr>
        <tr>
          <td>Abonado Anterior</td>
          <td class="precio">${d}${y(e.abonado||0)}</td>
        </tr>
        ${n>0?`<tr style="color:#059669;font-weight:bold">
          <td style="color:#059669;font-weight:bold">Nuevo Abono</td>
          <td class="precio" style="color:#059669;font-weight:bold">${d}${y(n)}</td>
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
        ${N}
      </tbody>
    </table>

    <div class="linea" style="margin-top: 12px;"></div>

    <div style="font-weight:bold;">
      <table>
        <tr>
          <td>TOTAL ABONADO:</td>
          <td class="precio"><span class="big">${d}${y(r)}</span></td>
        </tr>
      </table>
    </div>

    <div style="font-weight:bold;">
      <table>
        <tr>
          <td style="color:#dc2626">SALDO PENDIENTE:</td>
          <td class="precio"><span class="big" style="color:#dc2626">${d}${y(i)}</span></td>
        </tr>
      </table>
    </div>

    ${i<=0?`<div style="text-align:center;font-size:12px;color:#059669;font-weight:bold;border:2px solid #059669;border-radius:5px;padding:6px;margin:8px 0">CUENTA PAGADA EN SU TOTALIDAD</div>`:``}

    ${e.notas?`<div class="bordeado" style="margin-top:6px"><p>${String(e.notas).replace(/\n/g,`<br>`)}</p></div>`:``}

    ${_(a.show_footer)?`<div class="linea"></div><div style="text-align:center;">${a.footer_text||`Gracias por su preferencia`}</div>`:``}
  </div>
</body>
</html>`}async function C(e,t=0,n=0,r=0){let i={...g};try{let e=await window.db.getAll(`impresoras_config`);e.success&&e.data?.length>0&&(i={...i,...e.data[0]})}catch{}let a={};try{await h.load();let e=await window.db.getAll(`empresa`);if(e.success&&e.data?.length>0){a=h.activeUid&&e.data.find(e=>String(e.uid||e.almacen_uid||``)===String(h.activeUid))||e.data.find(e=>Number(e.almacen_id||e.id)===Number(h.activeId))||e.data[0];let t=await d(a.logoprinter||a.logo);t&&(a={...a,logo:t,logoprinter:t})}}catch{}let o=null,s=[];try{let t=await window.db.getAll(`facturas`);if(t.success&&t.data&&(o=t.data.find(t=>t.no_factura===e.no_factura)||null,o))try{let e=typeof o.productos==`string`?JSON.parse(o.productos):o.productos;s=Array.isArray(e)?e:[]}catch{s=[]}}catch{}let c=S({cuenta:e,empresa:a,monto:t,abonadoTotal:n,saldoRestante:r,ticketConfig:i,productos:s,factura:o}),l=i.printer_name||localStorage.getItem(`etiquetas_printer`)||``;try{let e=await window.electron.invoke(`print:ticket`,c,l||void 0);e.success?m.add({severity:`success`,summary:`Imprimiendo...`,life:2e3}):m.add({severity:`error`,summary:`Error`,detail:e.error,life:3e3})}catch(e){m.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}}return p({printTicket:C}),(i,a)=>(t(),n(`div`,f,[r(e(c))]))}});export{p as t};