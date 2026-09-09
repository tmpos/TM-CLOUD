import{r as e}from"./rolldown-runtime-hePW80VL.js";import{K as t,N as n,c as r,d as i,g as a,nt as o,r as s,st as c,u as l,v as u}from"./runtime-core.esm-bundler-a6pTXghO.js";import{f as d,o as f,u as p}from"./tmCloudClient-BJVGK5pA.js";import{C as m,I as h,_ as g,c as _,i as v,y}from"./index-BMrcxrjI.js";import{t as b}from"./browser-Cpc4qo6y.js";import{a as x,i as S,n as C,o as w,r as T}from"./TicketFacturaPrint-Mxu-clKB.js";import{n as E}from"./funciones-CZsKWvmS.js";var D=e(b()),O={style:{display:`none`}},k={class:`flex flex-col h-full gap-3`},A=[`src`],j=u({__name:`FacturaPdfPrint`,setup(e,{expose:u}){function b(e){let t=T(),n=B(e?.otro,{}),r=e.banco_nombre||n?.banco_nombre||``,i=[],a=Number(e.efectivo||0);a>0&&i.push({tipo:`efectivo`,etiqueta:t.cash,monto:a});let o=B(e.tarjeta_mixta,e.tarjeta_mixta),s=o&&typeof o==`object`?o:n?.tarjeta_mixta||{},c=Number(e.tarjeta||s?.total||Number(s?.monto_base||0)+Number(s?.monto_comision||0));c>0&&i.push({tipo:`tarjeta`,etiqueta:t.card,monto:c});let l=B(e.transferencias_mixtas,e.transferencias_mixtas),u=B(n?.transferencias_mixtas,n?.transferencias_mixtas),d=Array.isArray(l)?l:Array.isArray(u)?u:Number(e.transferencia)>0?[{monto:e.transferencia,banco_nombre:r}]:[];for(let e of d){let n=Number(e?.monto||0);if(n<=0)continue;let r=e?.banco_nombre?` (${e.banco_nombre})`:``;i.push({tipo:`transferencia`,etiqueta:`${t.transfer}${r}`,monto:n})}if(Number(e.cheque)>0&&i.push({tipo:`cheque`,etiqueta:t.check,monto:Number(e.cheque)}),a<=0){let n=Number(e.total||0),r=i.reduce((e,t)=>e+t.monto,0),a=Math.round((n-r)*100)/100;a>.009&&i.unshift({tipo:`efectivo`,etiqueta:t.cash,monto:a})}return i}function j(e){let t=T(),n=String(e.metodo_pago||``).toUpperCase(),r=B(e?.otro,{}),i=e.banco_nombre||r?.banco_nombre||``;if(n!==`MIXTO`){let t=w(e.metodo_pago);return i?`${t} - ${i}`:t}return t.mixed}function M(e){let t=T(),n={efectivo:t.cash,tarjeta:t.card,transferencia:t.transfer,cheque:t.check},r=[...new Set(b(e).map(e=>n[e.tipo].toUpperCase()))];return r.length?`${r.join(` Y `)} - ${t.mixed}`:t.mixed}function N(e){return String(e.tipo_factura||``).toLowerCase()===`cotizacion`||String(e.estado_factura||``).toLowerCase()===`cotizacion`}let P=h(),F=_(),I=o(!1),L=o(!1),R=o(``),z=o(``);function B(e,t){if(e==null)return t;if(typeof e==`string`)try{return JSON.parse(e)}catch{return t}return e}function V(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function H(e,t={}){let n=B(e?.otro,{}),r=n?.alanube_response||e?.alanube_response||{};return{documentStampUrl:t?.document_stamp_url||e?.document_stamp_url||e?.documentStampUrl||n?.documentStampUrl||n?.document_stamp_url||r?.documentStampUrl||r?.document_stamp_url||``,securityCode:t?.security_code||e?.codigo_seguridad||e?.securityCode||n?.securityCode||n?.security_code||r?.securityCode||r?.security_code||``,legalStatus:t?.legal_status||e?.alanube_legal_status||r?.legalStatus||n?.legalStatus||``,status:t?.status||e?.alanube_status||r?.status||n?.status||``}}async function U(e){if(e?.id)try{let t=await window.db.getWhere(`facturas_ecf`,`factura_id = ?`,[e.id]),n=t?.success&&Array.isArray(t.data)?t.data[0]:null;if(n)return H(e,n)}catch{}return H(e)}function W(e){return v(e)}function G(e,t=``){let n=String(t||``).trim().match(/^(\d{1,2}:\d{2})/),r=String(e||``).trim(),i=r.match(/(?:T|\s)(\d{1,2}:\d{2})/),a=n?n[1].padStart(5,`0`):i?i[1].padStart(5,`0`):``,o=r.match(/^(\d{4})-(\d{2})-(\d{2})/),s=r.match(/^(\d{2})\/(\d{2})\/(\d{4})/);if(o)return`${o[3]}/${o[2]}/${o[1]}${a?` ${a}`:``}`;if(s)return`${s[1]}/${s[2]}/${s[3]}${a?` ${a}`:``}`;let c=e instanceof Date?e:new Date(e);if(Number.isNaN(c.getTime()))return a;let l=`${String(c.getDate()).padStart(2,`0`)}/${String(c.getMonth()+1).padStart(2,`0`)}/${c.getFullYear()}`,u=`${String(c.getHours()).padStart(2,`0`)}:${String(c.getMinutes()).padStart(2,`0`)}`;return`${l} ${a||u}`}function K(e){return[e?.imei,e?.lista_imei,e?.imeis,e?.serial,e?.seriales].flatMap(e=>Array.isArray(e)?e:typeof e==`string`?e.split(`,`):e?[e]:[]).map(e=>typeof e==`object`?String(e.imei||e.serial||``).trim():String(e).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t)}function q(e){return String(e?.codigo||e?.codigo_barra||e?.cod_producto||e?.sku||e?.referencia||e?.barcode||e?.imei||e?.serial||e?.accesorio_id||e?.telefono_id||e?.imei_id||e?.serial_id||``).trim()}function J(e,t=``){let n=String(e??``).trim();return n?/^(data:|https?:\/\/|file:|blob:)/i.test(n)?n:n.startsWith(`/`)?t?`${t.replace(/\/$/,``)}${n}`:n:t?`${t.replace(/\/$/,``)}/${n.replace(/^\.\//,``)}`:n:``}function Y(e){let t=new Uint8Array(e),n=``,r=32768;for(let e=0;e<t.length;e+=r)n+=String.fromCharCode(...t.subarray(e,e+r));return btoa(n)}async function X(e,t=``){let n=String(e??``).trim();if(!n)return``;let r=J(n,t);if(/^data:/i.test(r))return r;if(!/^(https?:\/\/|file:|blob:|\/)/i.test(n))try{await f();let e=d(n),t=p()?.key||``;if(e){let n=await fetch(e,{headers:t?{Authorization:`Bearer ${t}`}:{}});if(n.ok)return`data:${n.headers.get(`content-type`)||`image/png`};base64,${Y(await n.arrayBuffer())}`}}catch{}return r}async function ee(){try{let e=await window.db.getAll(`empresa`);if(e.success&&e.data?.length>0)return e.data[0]}catch{}return{}}async function te(e){try{let t=await window.db.getAll(`clientes`);if(!t.success||!Array.isArray(t.data))return{};let n=String(e.cod_cliente||e.cliente_id||``).trim(),r=String(e.nombre_cliente||e.cliente||e.comprador||``).trim().toUpperCase(),i=String(e.telefono_cliente||e.telefono||e.whatsapp||``).trim(),a=String(e.rnc_cliente||e.cedula_cliente||e.rnc||e.cedula||``).trim();return t.data.find(e=>String(e.id||``)===n||String(e.codigo||``)===n||String(e.nombre||``).trim().toUpperCase()===r||String(e.telefono||``).trim()===i||String(e.whatsapp||``).trim()===i||String(e.rnc||``).trim()===a||String(e.cedula||``).trim()===a)||{}}catch{return{}}}function ne(e,t={}){return{nombre:e.nombre_cliente||e.cliente||e.comprador||t?.nombre||`CONSUMIDOR FINAL`,telefono:e.telefono_cliente||e.telefono||e.whatsapp||t?.telefono||t?.whatsapp||``,documento:e.rnc_cliente||e.cedula_cliente||e.rnc||e.cedula||t?.rnc||t?.cedula||``,direccion:e.direccion_cliente||e.direccion||t?.direccion||``}}function re(e){return String(e.nota||e.observacion||e.observaciones||e.nota_factura||e.comentario||``).trim()}async function ie(){try{return await E(`datosarchivo`)}catch{return{}}}async function ae(){try{let e=await window.db.getAll(`impresoras_config`);return e?.success&&Array.isArray(e.data)&&e.data[0]||{}}catch{return{}}}function Z(e,t,n,r){let i=Number(e);return Number.isFinite(i)?Math.min(r,Math.max(n,i)):t}async function Q({factura:e,cliente:t=null,datosEmpresa:n=null}){let r=T(),i=(await ie())?.VITE_LINKURL||``,a=n?.empresa||n?.datosEmpresa?.empresa||e.empresa||await ee(),o=t||await te(e),s=S({factura:e,empresa:a,cliente:o}),c={...ne(e,o),...s.customer};c.nombre=x(c.nombre);let l=(re(e)||r.thanks).replace(/\n/g,`<br>`),u=s.items,d=await X(a?.logoprinter||a?.logo,i),f=await ae(),p=Z(f.factura_logo_ancho,150,30,400),m=Z(f.factura_logo_alto,90,20,250),h=await U(e),g=h.documentStampUrl||`${i||`https://tmposrd.com`}/receipt/factura?factura=${e.no_factura||``}`,_=e.qr||``;try{_||=await D.toDataURL(g)}catch{}let v=Array.isArray(u)?u.map(e=>{let t=V(e.cantidad??e.quantity,0),n=V(e.precio_final??e.precio_venta??e.precio_unitario??e.precio,0),r=V(e.precio_normal??e.precio_lista??e.precio_venta_normal,n),i=V(e.descuento,0),a=V(e.impuesto_venta??e.impuesto,0),o=V(e.total,n*t-i),s=r>0&&n>=0&&n<r;return{...e,codigoProducto:q(e),cantidad:t,precioUnidad:n,precioNormal:r,descuento:i,tieneDescuentoProducto:s,impuestoTotal:a*t,totalProducto:o,imeis:K(e),detallesImei:C(e)}}):[],y=V(e.impuesto??e.impuestos)>0||v.some(e=>e.impuestoTotal>0),w=V(e.descuento)>0||v.some(e=>e.descuento>0),E=v.reduce((e,t)=>e+t.impuestoTotal,0)||V(e.impuesto??e.impuestos),O=V(e.total),k=V(e.subtotal,O+V(e.descuento)-E),A=String(e.metodo_pago||``).toUpperCase()===`MIXTO`,P=A?O:k,I=(A?b(e):[]).map(e=>`<tr class="payment-row"><td>${e.etiqueta}</td><td class="text-right">${W(e.monto)}</td></tr>`).join(``),L=5+ +!!y+ +!!w,R=G(e.fecha_emision||e.fecha||``,e.hora||``),z=r.language===`en`?`CUSTOMER DETAILS`:`DATOS DEL CLIENTE`,B=r.language===`en`?`PAYMENT SUMMARY`:`RESUMEN DE PAGO`,H=N(e)?r.quote.toUpperCase():e.metodo_pago===`CREDITO`?r.creditInvoice:r.invoice.toUpperCase(),J=v.map(e=>{let t=e.detallesImei.length?e.detallesImei.map(e=>`<div class="imei-line">IMEI: ${e}</div>`).join(``):e.imeis.length?`<div class="imei-line">IMEI: ${e.imeis.join(`, `)}</div>`:``,n=e.tieneDescuentoProducto?`<div class="discount-line">Normal: <span class="line-through">${W(e.precioNormal)}</span> &nbsp; Con descuento: <strong>${W(e.precioUnidad)}</strong></div>`:``;return`
      <tr class="invoice-line">
        <td>${e.codigoProducto||``}</td>
        <td>
          <div>${e.nombre||e.descripcion||``}</div>
          ${n}
          ${t}
        </td>
        <td class="text-center">${e.cantidad}</td>
        <td class="text-right">${W(e.precioUnidad)}</td>
        ${y?`<td class="text-right">${W(e.impuestoTotal)}</td>`:``}
        ${w?`<td class="text-right">${W(e.descuento)}</td>`:``}
        <td class="text-right"><strong>${W(e.totalProducto)}</strong></td>
      </tr>
    `}).join(``),Y=Array.from({length:Math.max(0,8-v.length)},()=>`<tr class="invoice-line empty-row">${Array.from({length:L},()=>`<td>&nbsp;</td>`).join(``)}</tr>`).join(``);return`<!DOCTYPE html>
<html lang="${r.language}">
<head>
  <meta charset="UTF-8">
  <title>${r.invoice} ${e.no_factura||``}</title>
  <style>
    * { box-sizing: border-box; }
    @page { size: letter; margin: 10mm; }
    :root { --navy: #102a43; --blue: #176b9c; --blue-soft: #eaf4f9; --slate: #52667a; --line: #d8e2ea; --surface: #f7fafc; }
    body { margin: 0; background: #fff; color: #172b3a; font-family: "Segoe UI", Arial, Helvetica, sans-serif; font-size: 11px; }
    .page { position: relative; width: 100%; max-width: 760px; margin: 0 auto; padding: 18px 20px 14px; border-top: 6px solid var(--blue); }
    .header { display: flex; justify-content: space-between; align-items: stretch; gap: 28px; padding-bottom: 16px; border-bottom: 1px solid var(--line); }
    .company { flex: 1; min-width: 0; display: flex; align-items: center; gap: 14px; line-height: 1.45; }
    .company img { max-width: ${p}px; max-height: ${m}px; object-fit: contain; flex: 0 0 auto; }
    .company-copy { min-width: 0; }
    .company-name { color: var(--navy); font-size: 20px; line-height: 1.15; font-weight: 800; letter-spacing: -.02em; margin-bottom: 6px; }
    .company-meta { color: var(--slate); font-size: 10px; }
    .invoice-box { width: 290px; flex: 0 0 290px; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: var(--surface); }
    .document-heading { padding: 11px 13px 9px; background: var(--navy); color: #fff; }
    .document-type { font-size: 9px; font-weight: 700; letter-spacing: .16em; opacity: .72; }
    .document-number { margin-top: 3px; font-size: 15px; font-weight: 800; letter-spacing: .01em; overflow-wrap: anywhere; }
    .invoice-box table { width: 100%; border-collapse: collapse; }
    .invoice-box td { padding: 5px 10px; border-bottom: 1px solid var(--line); color: var(--slate); }
    .invoice-box td:first-child { width: 40%; text-transform: uppercase; font-size: 9px; font-weight: 700; letter-spacing: .04em; }
    .invoice-box td:last-child { color: var(--navy); font-weight: 600; }
    .invoice-title { margin: 8px; padding: 7px 9px; text-align: center; background: var(--blue-soft); color: var(--blue); border-radius: 7px; font-size: 10px; font-weight: 800; letter-spacing: .04em; }
    .section-label { margin-bottom: 7px; color: var(--blue); font-size: 9px; font-weight: 800; letter-spacing: .13em; }
    .client-box { margin-top: 14px; border: 1px solid var(--line); border-radius: 12px; display: flex; justify-content: space-between; gap: 18px; padding: 11px 13px; background: #fff; }
    .client-data { flex: 1; min-width: 0; }
    .client-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px 18px; }
    .client-box p { margin: 0; color: var(--slate); }
    .client-box strong { color: var(--navy); font-size: 9px; letter-spacing: .02em; }
    .client-wide { grid-column: 1 / -1; }
    .payment-detail { display: inline; line-height: 1.4; overflow-wrap: anywhere; }
    .qr { flex: 0 0 auto; text-align: center; }
    .qr img { width: 78px; height: 78px; padding: 3px; border: 1px solid var(--line); border-radius: 7px; }
    .products { margin-top: 14px; width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
    .products th { background: var(--navy); color: #fff; padding: 8px 7px; border: none; font-weight: 700; font-size: 9px; text-transform: uppercase; letter-spacing: .06em; }
    .products td { padding: 7px; border: none; border-bottom: 1px solid #e8eef3; vertical-align: top; font-size: 10px; }
    .products tbody tr:nth-child(even):not(.empty-row) td { background: var(--surface); }
    .products tbody tr:last-child td { border-bottom: none; }
    .invoice-line { min-height: 24px; }
    .empty-row td { height: 22px; }
    .imei-line { margin-top: 3px; font-size: 8px; font-weight: 700; color: var(--slate); }
    .discount-line { margin-top: 3px; font-size: 8px; color: var(--slate); }
    .line-through { text-decoration: line-through; color: #6b7280; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .bottom { display: flex; justify-content: space-between; align-items: flex-start; gap: 34px; margin-top: 14px; }
    .signatures { flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 18px; padding-top: 26px; }
    .signature-line { border-top: 1px solid #7b8b99; padding-top: 5px; text-align: center; color: var(--slate); font-size: 9px; }
    .signature-line strong { display: block; color: var(--navy); font-size: 9px; text-transform: uppercase; }
    .totals { width: 310px; border: 1px solid var(--line); border-radius: 11px; overflow: hidden; align-self: flex-start; box-shadow: 0 3px 10px rgba(16,42,67,.06); }
    .totals-title { padding: 8px 10px; background: var(--blue-soft); color: var(--blue); font-size: 9px; font-weight: 800; letter-spacing: .12em; }
    .totals table { width: 100%; border-collapse: collapse; }
    .totals td { padding: 6px 10px; border-bottom: 1px solid #e8eef3; }
    .totals .payment-row td { color: var(--blue); font-size: 10px; background: #fbfdff; }
    .totals tr:last-child td { border-bottom: none; background: var(--navy); color: #fff; font-size: 14px; font-weight: 800; padding-top: 9px; padding-bottom: 9px; }
    .note { margin-top: 10px; padding: 9px 11px; border-left: 3px solid var(--blue); border-radius: 0 8px 8px 0; font-size: 10px; line-height: 1.4; color: var(--slate); background: var(--surface); }
    .note strong { color: var(--navy); font-size: 9px; letter-spacing: .06em; }
    .footer { margin-top: 16px; padding-top: 8px; border-top: 1px solid var(--line); display: flex; justify-content: space-between; color: #8494a3; font-size: 8px; }
    @media print {
      * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
      .page { padding: 0; }
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="header">
      <div class="company">
        ${d?`<img src="${d}" alt="Logo">`:``}
        <div class="company-copy">
          <div class="company-name">${a.nombre||r.company}</div>
          <div class="company-meta">
            ${a.legal||a.rnc?`${F.businessIdLabel}: ${a.legal||a.rnc}<br>`:``}
            ${a.telefono?`Tel: ${a.telefono}`:``}${a.email?`${a.telefono?` &nbsp;|&nbsp; `:``}${a.email}<br>`:`<br>`}
            ${a.direccion||``}
          </div>
        </div>
      </div>

      <div class="invoice-box">
        <div class="document-heading">
          <div class="document-type">${H}</div>
          <div class="document-number">${e.no_factura||``}</div>
        </div>
        <table>
          <tr><td><strong>${r.date}</strong></td><td class="text-right">${R}</td></tr>
          ${N(e)||!(e.ncf||e.comprobante)?``:`<tr><td><strong>${F.fiscalDocumentLabel}</strong></td><td class="text-right">${e.ncf||e.comprobante||``}</td></tr>`}
        </table>
        <div class="invoice-title">${N(e)?`${r.quote.toUpperCase()} #${e.no_factura||``}`:e.metodo_pago===`CREDITO`?`${r.creditInvoice} #${e.no_factura||``}`:A?M(e):`${r.invoice.toUpperCase()} #${e.no_factura||``}`}</div>
        ${N(e)?`<div style="text-align:center;margin-top:8px;font-size:10px;color:#666;font-style:italic">${r.quoteValidity}</div>`:``}
      </div>
    </div>

    <div class="client-box">
      <div class="client-data">
        <div class="section-label">${z}</div>
        <div class="client-grid">
          <p><strong>${r.customer}:</strong><br>${c.nombre||r.unregistered}</p>
          <p><strong>${r.phone}:</strong><br>${c.telefono||r.notApplicable}</p>
          <p><strong>${F.customerIdLabel.toUpperCase()}:</strong><br>${c.documento||r.notApplicable}</p>
          <p><strong>${r.paymentMethod}:</strong><br><span class="payment-detail">${j(e)}</span></p>
          <p class="client-wide"><strong>${r.address}:</strong><br>${c.direccion||r.notApplicable}</p>
        </div>
      </div>
      <div class="qr">
        ${_?`<img src="${_}" alt="QR">`:``}
        ${h.securityCode?`<div style="font-size:9px;font-weight:700;text-align:center;margin-top:4px">${r.securityCode}: ${h.securityCode}</div>`:``}
      </div>
    </div>

    <table class="products">
      <thead>
        <tr>
          <th>${r.code}</th>
          <th>${r.description}</th>
          <th>${r.quantity}</th>
          <th>${r.unitPrice}</th>
          ${y?`<th>${F.shortName}</th>`:``}
          ${w?`<th>${r.discount}</th>`:``}
          <th>${r.subtotal}</th>
        </tr>
      </thead>
      <tbody>
        ${J}
        ${Y}
      </tbody>
    </table>

    <div class="note"><strong>${r.observation}:</strong><br>${l}</div>

    <div class="bottom">
      <div class="signatures">
        <div class="signature-line"><strong>${r.deliveredBy}</strong>${e.usuario||e.cajero||r.user}</div>
        <div class="signature-line"><strong>${r.receivedBy}</strong>${c.nombre||r.unregistered}</div>
      </div>

      <div class="totals">
        <div class="totals-title">${B}</div>
        <table>
          <tr><td>${r.subtotal}</td><td class="text-right">${W(P)}</td></tr>
          ${y?`<tr><td>${F.shortName}</td><td class="text-right">${W(E)}</td></tr>`:``}
          ${w?`<tr><td>${r.discount}</td><td class="text-right">${W(e.descuento)}</td></tr>`:``}
          ${I}
          <tr><td>${r.total}</td><td class="text-right">${W(e.total)}</td></tr>
        </table>
      </div>
    </div>

    <div class="footer"><span>${a.nombre||r.company}</span><span>${r.invoice} ${e.no_factura||``}</span></div>

  </div>
</body>
</html>`}async function oe(e,t){R.value&&URL.revokeObjectURL(R.value),R.value=e,z.value=t,I.value=!0}function $(){R.value&&URL.revokeObjectURL(R.value),R.value=``,z.value=``,I.value=!1}async function se(){if(!R.value)return;let e=`data:application/pdf;base64,${Y(await(await(await fetch(R.value)).blob()).arrayBuffer())}`,t=await window.electron.invoke(`save:pdf`,e,z.value);t.success?P.add({severity:`success`,summary:`Guardado`,detail:`PDF descargado`,life:2e3}):P.add({severity:`error`,summary:`Error`,detail:t.error||`No se pudo guardar el PDF`,life:3e3})}async function ce(e){L.value=!0;try{let t=await Q({factura:e}),n=`Factura_${e?.no_factura||`sin_numero`}.pdf`,r=await window.electron.invoke(`generate:pdf`,t,n);if(r.success&&r.dataUrl){let e=await(await fetch(r.dataUrl)).blob();await oe(URL.createObjectURL(e),n)}else P.add({severity:`error`,summary:`Error`,detail:r.error||`No se pudo generar el PDF`,life:3e3})}catch(e){P.add({severity:`error`,summary:`Error`,detail:e.message||`Error al generar PDF`,life:3e3})}finally{L.value=!1}}return u({printFactura:ce,generateFacturaHtml:Q}),(e,o)=>(n(),i(s,null,[r(`div`,O,[a(c(m))]),a(c(g),{visible:I.value,"onUpdate:visible":o[0]||=e=>I.value=e,header:`Vista Previa - Factura PDF`,modal:``,style:{width:`80vw`,height:`90vh`},draggable:!1,onHide:$},{footer:t(()=>[a(c(y),{label:`Cerrar`,severity:`secondary`,text:``,onClick:$}),a(c(y),{label:`Descargar PDF`,icon:`pi pi-download`,onClick:se})]),default:t(()=>[r(`div`,k,[R.value?(n(),i(`iframe`,{key:0,src:R.value,class:`w-full flex-1 border-0 rounded-lg`,style:{"min-height":`70vh`},title:`Factura PDF`},null,8,A)):l(``,!0)])]),_:1},8,[`visible`])],64))}});export{j as t};