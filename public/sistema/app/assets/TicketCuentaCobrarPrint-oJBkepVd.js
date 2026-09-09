import{N as e,d as t,g as n,st as r,v as i}from"./runtime-core.esm-bundler-a6pTXghO.js";import{C as a,I as o,k as s,l as c,u as l}from"./index-BMrcxrjI.js";import{t as u}from"./printImageService-MUfUTrDy.js";var d={style:{display:`none`}},f=i({__name:`TicketCuentaCobrarPrint`,setup(i,{expose:f}){let p=o(),m=s(),h={printer_name:``,paper_width:80,show_logo:1,show_company_name:1,show_legal:1,show_phone:1,show_address:1,show_email:1,show_cliente:1,show_items:1,show_totals:1,show_barcode:1,show_footer:1,show_qr:0,footer_text:`Gracias por su compra`};function g(e){return e===!0||e===1||e===`1`}function _(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function v(e){return _(e).toFixed(2)}function y(e){return String(e?.logoprinter||e?.logo||``).trim()}function b(e){if(!e)return``;let t=new Date(e);return isNaN(t.getTime())?e:`${String(t.getDate()).padStart(2,`0`)}/${String(t.getMonth()+1).padStart(2,`0`)}/${t.getFullYear()}`}function x({cuenta:e,empresa:t,monto:n,abonadoTotal:r,saldoRestante:i,ticketConfig:a,productos:o,factura:s}){let u=l(),d=y(t),f=_(a.paper_width,80),p=f===58?230:300,m=f===58?210:250,h=f===58?200:240,x=n>0?`RECIBO DE ABONO`:`ESTADO DE CUENTA`,S=new Date,C=`${S.toLocaleDateString(c().locale)} ${String(S.getHours()).padStart(2,`0`)}:${String(S.getMinutes()).padStart(2,`0`)}`,w=e.fecha_venta?b(e.fecha_venta):``,T=e.fecha_vencimiento?b(e.fecha_vencimiento):``,E=o.length>0?o.map(e=>{let t=e.nombre||e.descripcion||e.producto||`Producto`,n=_(e.cantidad??e.quantity,1),r=_(e.precio_venta??e.precio_unitario??e.precio??e.price),i=_(e.total,r*n);return`<tr>
          <td>${t}</td>
          <td class="centrado">${n}</td>
          <td class="precio">${u}${v(r)}</td>
          <td class="precio"><b>${u}${v(i)}</b></td>
        </tr>`}).join(``):``,D=[];g(a.show_address)&&t.direccion&&D.push(t.direccion);let O=[];g(a.show_phone)&&t.telefono&&O.push(t.telefono),g(a.show_email)&&t.email&&O.push(t.email),O.length&&D.push(O.join(` / `)),g(a.show_legal)&&(t.legal||t.rnc)&&D.push(`RNC: ${t.legal||t.rnc}`);let k=D.length?`<p>${D.join(`<br>`)}</p>`:``,A=s?`Metodo: ${s.metodo_pago||`CREDITO`}<br>
       ${s.vendedor?`Vendedor: ${s.vendedor}<br>`:``}
       ${s.cajero?`Cajero: ${s.cajero}<br>`:``}`:``,j=[];try{j=JSON.parse(e.pagos||`[]`),Array.isArray(j)||(j=[])}catch{j=[]}let M=j.length>0?j.map(e=>`
      <tr>
        <td>Pago #${e.nopago||``}<br><small>${e.metodo||e.metodo_pago||`ABONO`}${e.banco_nombre?` · ${e.banco_nombre}`:``}</small></td>
        <td>${e.fecha||``} ${e.hora||``}</td>
        <td class="precio"><b>${u}${v(e.cantidad)}</b></td>
      </tr>
    `).join(``):`<tr><td colspan="3">Sin abonos registrados</td></tr>`;return`<!DOCTYPE html>
<html>
<head>
  <style>
    * { font-size: 10px; font-family: Arial, Helvetica, sans-serif; }
    @page { size: ${p}px auto; margin: 5px; }
    body { width: ${m}px; margin: 5px; padding: 5px; color: #111; }
    .ticket { width: ${h}px; padding-top: 10px; padding-bottom: 10px; }
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
        ${g(a.show_logo)&&d?`<img src="${d}" alt="Logo" style="max-width:100px">`:g(a.show_company_name)?`<div style="font-size:18px !important;font-weight:bold">${t.nombre||`MI EMPRESA`}</div>`:``}
      ${k}
    </center>

    <div class="bordeado">
      <p>
        <b>${x}</b><br>
        Fecha: ${C}<br>
        Factura Ref: <b style="font-size:14px">#${e.no_factura||``}</b><br>
        ${g(a.show_cliente)?`Cliente: ${e.nombre_cliente||`SIN REGISTRO`}<br>`:``}
        ${g(a.show_cliente)&&e.telefono_cliente?`Telefono: ${e.telefono_cliente}<br>`:``}
        ${w?`Fecha Venta: ${w}<br>`:``}
        ${T?`Vencimiento: ${T}<br>`:``}
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
          <td class="precio"><b>${u}${v(e.total)}</b></td>
        </tr>
        <tr>
          <td>Abonado Anterior</td>
          <td class="precio">${u}${v(e.abonado||0)}</td>
        </tr>
        ${n>0?`<tr style="color:#059669;font-weight:bold">
          <td style="color:#059669;font-weight:bold">Nuevo Abono</td>
          <td class="precio" style="color:#059669;font-weight:bold">${u}${v(n)}</td>
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
          <td class="precio"><span class="big">${u}${v(r)}</span></td>
        </tr>
      </table>
    </div>

    <div style="font-weight:bold;">
      <table>
        <tr>
          <td style="color:#dc2626">SALDO PENDIENTE:</td>
          <td class="precio"><span class="big" style="color:#dc2626">${u}${v(i)}</span></td>
        </tr>
      </table>
    </div>

    ${i<=0?`<div style="text-align:center;font-size:12px;color:#059669;font-weight:bold;border:2px solid #059669;border-radius:5px;padding:6px;margin:8px 0">CUENTA PAGADA EN SU TOTALIDAD</div>`:``}

    ${e.notas?`<div class="bordeado" style="margin-top:6px"><p>${String(e.notas).replace(/\n/g,`<br>`)}</p></div>`:``}

    ${g(a.show_footer)?`<div class="linea"></div><div style="text-align:center;">${a.footer_text||`Gracias por su preferencia`}</div>`:``}
  </div>
</body>
</html>`}async function S(e,t=0,n=0,r=0){let i={...h};try{let e=await window.db.getAll(`impresoras_config`);e.success&&e.data?.length>0&&(i={...i,...e.data[0]})}catch{}let a={};try{await m.load();let e=await window.db.getAll(`empresa`);if(e.success&&e.data?.length>0){a=m.activeUid&&e.data.find(e=>String(e.uid||e.almacen_uid||``)===String(m.activeUid))||e.data.find(e=>Number(e.almacen_id||e.id)===Number(m.activeId))||e.data[0];let t=await u(a.logoprinter||a.logo);t&&(a={...a,logo:t,logoprinter:t})}}catch{}let o=null,s=[];try{let t=await window.db.getAll(`facturas`);if(t.success&&t.data&&(o=t.data.find(t=>t.no_factura===e.no_factura)||null,o))try{let e=typeof o.productos==`string`?JSON.parse(o.productos):o.productos;s=Array.isArray(e)?e:[]}catch{s=[]}}catch{}let c=x({cuenta:e,empresa:a,monto:t,abonadoTotal:n,saldoRestante:r,ticketConfig:i,productos:s,factura:o}),l=i.printer_name||localStorage.getItem(`etiquetas_printer`)||``;try{let e=await window.electron.invoke(`print:ticket`,c,l||void 0);e.success?p.add({severity:`success`,summary:`Imprimiendo...`,life:2e3}):p.add({severity:`error`,summary:`Error`,detail:e.error,life:3e3})}catch(e){p.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}}return f({printTicket:S}),(i,o)=>(e(),t(`div`,d,[n(r(a))]))}});export{f as t};