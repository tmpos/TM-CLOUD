import{r as e}from"./rolldown-runtime-hePW80VL.js";import{N as t,d as n,g as r,nt as i,st as a,v as o}from"./runtime-core.esm-bundler-a6pTXghO.js";import{f as s,o as c,u as l}from"./tmCloudClient-BJVGK5pA.js";import{C as u,I as d,c as f,l as p,u as m}from"./index-BMrcxrjI.js";import{t as h}from"./browser-Cpc4qo6y.js";import{t as g}from"./JsBarcode-JyLvbIYG.js";var _=e(h()),v=e(g());function y(e,t=[]){if(Array.isArray(e))return e;if(e==null||String(e).trim()===``)return t;try{let n=JSON.parse(String(e));return Array.isArray(n)?n:t}catch{return t}}function b(e){return(Array.isArray(e)?e:e==null?[]:[e]).flatMap(e=>typeof e==`string`?e.split(`,`):[e]).map(e=>String(e?.imei??e?.nombre??e??``).trim()).filter(Boolean)}function x(e){return e?/\b(?:KB|MB|GB|TB)\b/i.test(e)?e:/^\d+(?:[.,]\d+)?$/.test(e)?`${e} GB`:e:``}function S(e){let t=b(e?.imeis?.length?e.imeis:e?.imei);if(!t.length)return[];let n=b(e?.capacidades?.length?e.capacidades:e?.capacidad);return t.map((e,t)=>{let r=x(n[t]||(n.length===1?n[0]:``));return r?`${e} — ${r}`:e})}var C={es:{language:`es`,date:`Fecha`,quote:`Cotización`,invoice:`Factura`,creditInvoice:`FACTURA A CRÉDITO`,salesInvoice:`FACTURA DE VENTA`,customer:`CLIENTE`,phone:`TELÉFONO`,address:`DIRECCIÓN`,paymentMethod:`MÉTODO DE PAGO`,seller:`VENDEDOR`,cashier:`CAJERO`,code:`CÓD.`,description:`DESCRIPCIÓN`,quantity:`CANT.`,package:`EMPAQ.`,unitPrice:`P.U.`,price:`PRECIO`,discount:`DESC.`,subtotal:`SUBTOTAL`,total:`TOTAL`,observation:`OBSERVACIÓN`,deliveredBy:`ENTREGADO POR`,receivedBy:`RECIBIDO POR`,unregistered:`SIN REGISTRO`,finalConsumer:`CONSUMIDOR FINAL`,company:`MI EMPRESA`,notApplicable:`N/A`,user:`Usuario`,thanks:`¡Gracias por su compra!`,quoteValidity:`Esta cotización tiene una validez de 30 días`,paidWith:`PAGO CON`,change:`SU CAMBIO`,securityCode:`Código de seguridad`,cash:`Efectivo`,card:`Tarjeta`,transfer:`Transferencia`,check:`Cheque`,mixed:`MIXTO`,credit:`CRÉDITO`,saleInvoiceType:`FACTURA_VENTA`},en:{language:`en`,date:`Date`,quote:`Quote`,invoice:`Invoice`,creditInvoice:`CREDIT INVOICE`,salesInvoice:`SALES INVOICE`,customer:`CUSTOMER`,phone:`PHONE`,address:`ADDRESS`,paymentMethod:`PAYMENT METHOD`,seller:`SALESPERSON`,cashier:`CASHIER`,code:`CODE`,description:`DESCRIPTION`,quantity:`QTY.`,package:`PACK`,unitPrice:`UNIT PRICE`,price:`PRICE`,discount:`DISC.`,subtotal:`SUBTOTAL`,total:`TOTAL`,observation:`NOTE`,deliveredBy:`DELIVERED BY`,receivedBy:`RECEIVED BY`,unregistered:`NOT PROVIDED`,finalConsumer:`FINAL CONSUMER`,company:`MY COMPANY`,notApplicable:`N/A`,user:`User`,thanks:`Thank you for your purchase!`,quoteValidity:`This quote is valid for 30 days`,paidWith:`AMOUNT PAID`,change:`CHANGE`,securityCode:`Security code`,cash:`Cash`,card:`Card`,transfer:`Transfer`,check:`Check`,mixed:`MIXED`,credit:`CREDIT`,saleInvoiceType:`FACTURA_VENTA`}};function w(){return(localStorage.getItem(`sistema_idioma`)||`es`).toLowerCase().startsWith(`en`)?C.en:C.es}function T(e){let t=w(),n=String(e||``).trim();return{EFECTIVO:t.cash,TARJETA:t.card,TRANSFERENCIA:t.transfer,CHEQUE:t.check,MIXTO:t.mixed,CREDITO:t.credit,CRÉDITO:t.credit}[n.toUpperCase()]||n}function E(e){let t=w(),n=String(e||``).trim().toUpperCase();return n.includes(`COTIZACION`)||n.includes(`COTIZACIÓN`)?t.quote.toUpperCase():n===t.saleInvoiceType||n===`FACTURA`||n===`FACTURA DE VENTA`?t.salesInvoice:String(e||t.salesInvoice)}function D(e){let t=w(),n=String(e||``).trim();return!n||n.toUpperCase()===`CONSUMIDOR FINAL`?t.finalConsumer:n}function O(e){let t=e.factura||{},n=y(t.productos,e.items||t.items||[]),r=Number(t.total||0),i=Number(t.impuesto??t.impuestos??0),a=Number(t.descuento||0),o=Number(t.subtotal??r+a-i),s=p(),c={nombre:D(t.nombre_cliente||t.cliente||e.cliente?.nombre),documento:t.rnc_cliente||t.cedula_cliente||t.cod_cliente||e.cliente?.rnc||e.cliente?.cedula||``,telefono:t.telefono_cliente||e.cliente?.telefono||e.cliente?.whatsapp||``,direccion:t.direccion_cliente||e.cliente?.direccion||``};return{factura:t,empresa:e.empresa||t.empresa||{},customer:c,items:n,totals:{subtotal:o,discount:a,tax:i,total:r},regional:s,documentNumber:t.ncf||t.comprobante||``,isQuote:String(t.tipo_factura||t.estado_factura||``).toLowerCase().includes(`cotizacion`)}}var k={style:{display:`none`}},A=o({__name:`TicketFacturaPrint`,setup(e,{expose:o}){function p(e){let t=w(),n=j(e?.otro,{}),r=[],i=Number(e.efectivo||0);i>0&&r.push({tipo:`efectivo`,etiqueta:t.cash,monto:i});let a=j(e.tarjeta_mixta,e.tarjeta_mixta),o=a&&typeof a==`object`?a:n?.tarjeta_mixta||{},s=Number(e.tarjeta||o?.total||Number(o?.monto_base||0)+Number(o?.monto_comision||0));s>0&&r.push({tipo:`tarjeta`,etiqueta:t.card,monto:s});let c=j(e.transferencias_mixtas,e.transferencias_mixtas),l=j(n?.transferencias_mixtas,n?.transferencias_mixtas),u=Array.isArray(c)?c:Array.isArray(l)?l:Number(e.transferencia)>0?[{monto:e.transferencia,banco_nombre:e.banco_nombre||n?.banco_nombre||``}]:[];for(let e of u){let n=Number(e?.monto||0);if(n<=0)continue;let i=e?.banco_nombre?` (${e.banco_nombre})`:``;r.push({tipo:`transferencia`,etiqueta:`${t.transfer}${i}`,monto:n})}if(Number(e.cheque)>0&&r.push({tipo:`cheque`,etiqueta:t.check,monto:Number(e.cheque)}),i<=0){let n=r.reduce((e,t)=>e+t.monto,0),i=Math.round((Number(e.total||0)-n)*100)/100;i>.009&&r.unshift({tipo:`efectivo`,etiqueta:t.cash,monto:i})}return r}function h(e){let t=w();return String(e.metodo_pago||``).toLowerCase()===`mixto`?t.mixed:T(e.metodo_pago)}let g=d(),y=f(),b=i(``),x={printer_name:``,paper_width:80,show_logo:1,show_company_name:1,show_legal:1,show_phone:1,show_address:1,show_email:1,show_cliente:1,show_items:1,show_totals:1,show_barcode:1,show_footer:1,show_qr:0,show_nota:1,footer_text:`Gracias por su compra`};function S(e){let t={...e||{}},n=Array.isArray(t.items)?t.items:j(t.productos,[]);return{...t,fecha_emision:t.fecha_emision||t.fecha||``,nombre_cliente:t.nombre_cliente||t.cliente||`CONSUMIDOR FINAL`,telefono_cliente:t.telefono_cliente||t.telefono||``,comprobante:t.comprobante||t.tipo_comprobante||``,productos:n.map(e=>({...e,precio_venta:e.precio_venta??e.precio,precio_final:e.precio_final??e.precio,total:e.total??Number(e.precio||e.precio_venta||0)*Number(e.cantidad||1)}))}}function C(e,t){if(t?.empresa&&typeof t.empresa==`object`)return t.empresa;let n=String(t?.almacen_uid||localStorage.getItem(`almacen_uid`)||localStorage.getItem(`almacen_default_uid`)||``),r=Number(t?.almacen_id||localStorage.getItem(`almacen_id`)||localStorage.getItem(`almacen_default_id`)||0);return n&&e.find(e=>String(e.uid||e.almacen_uid||``)===n)||r&&e.find(e=>Number(e.almacen_id||e.id)===r)||e[0]||{}}function A(e){return e===!0||e===1||e===`1`}function j(e,t){if(e==null)return t;if(typeof e==`string`)try{return JSON.parse(e)}catch{return t}return e}function M(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function N(e){return M(e).toFixed(2)}function P(e){return M(e.precio_venta??e.precio_unitario??e.precio??e.price)}function F(e){let t=M(e.cantidad??e.quantity,1),n=M(e.precio_final??e.precio_venta??e.precio_unitario??e.precio??e.price);return M(e.total,n*t)}function I(e){return String(e?.logoprinter||e?.logo||``).trim()}function L(e){let t=new Uint8Array(e),n=``,r=32768;for(let e=0;e<t.length;e+=r)n+=String.fromCharCode(...t.subarray(e,e+r));return btoa(n)}async function R(e){let t=String(e||``).trim();if(!t||/^data:/i.test(t)||/^(https?:\/\/|file:|blob:|\/)/i.test(t))return t;try{await c();let e=s(t),n=l()?.key||``;if(!e)return t;let r=await fetch(e,{headers:n?{Authorization:`Bearer ${n}`}:{}});return r.ok?`data:${r.headers.get(`content-type`)||`image/png`};base64,${L(await r.arrayBuffer())}`:t}catch{return t}}function z(e,t={}){let n=j(e?.otro,{}),r=n?.alanube_response||e?.alanube_response||{};return{documentStampUrl:t?.document_stamp_url||e?.document_stamp_url||e?.documentStampUrl||n?.documentStampUrl||n?.document_stamp_url||r?.documentStampUrl||r?.document_stamp_url||``,securityCode:t?.security_code||e?.codigo_seguridad||e?.securityCode||n?.securityCode||n?.security_code||r?.securityCode||r?.security_code||``}}async function B(e){if(e?.id)try{let t=await window.db.getWhere(`facturas_ecf`,`factura_id = ?`,[e.id]),n=t?.success&&Array.isArray(t.data)?t.data[0]:null;if(n)return z(e,n)}catch{}return z(e)}function V(e){return/^E\d{2}/i.test(String(e?.ncf||e?.comprobante||e?.tipo_comprobante||``))}async function H(e){try{return await _.toDataURL(e,{width:200,margin:1})}catch{return``}}function U(e){if(!e)return``;try{let t=document.createElementNS(`http://www.w3.org/2000/svg`,`svg`);return(0,v.default)(t,e,{format:`CODE128`,width:2,height:50,displayValue:!0,fontSize:12,margin:2}),new XMLSerializer().serializeToString(t).replace(/width="[^"]*"/,`width="180"`).replace(/height="[^"]*"/,`height="55"`)}catch{return``}}function W(e,t,n){return e.map(e=>{let r=M(e.cantidad??e.quantity,1),i=P(e),a=F(e),o=M(e.descuento),s=e.nombre||e.descripcion||e.producto||``,c=[e.imeis,e.imei].flatMap(e=>Array.isArray(e)?e:e?String(e).split(`,`):[]).map(e=>String(e||``).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t),l=[e.seriales,e.serial].flatMap(e=>Array.isArray(e)?e:e?String(e).split(`,`):[]).map(e=>String(e||``).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t),u=c.length?`<br><span style="font-size:8px;color:#555;">IMEI: ${c.join(`, `)}</span>`:``,d=l.length?`<br><span style="font-size:8px;color:#555;">Serial: ${l.join(`, `)}</span>`:``;return`
      <tr class="item-name">
        <td colspan="${n?5:4}" style="overflow-wrap:break-word;white-space:normal;word-break:break-word;">
          ${s}${u}${d}
        </td>
      </tr>
      <tr class="item-values">
        <td>${r} x</td>
        <td>${e.empaque||``}</td>
        <td>${t}${N(i)}</td>
        ${n?`<td class="precio">${t}${N(o)}</td>`:``}
        <td class="precio">
          <b>${t}${N(a)}</b>
        </td>
      </tr>
    `}).join(``)}function G({factura:e,empresa:t,productos:n,qrCodeData:r,alanubeData:i,ticketConfig:a}){let o=w(),s=O({factura:e,empresa:t,items:n});n=s.items;let c=m(),l=I(t),u=M(a.paper_width,80)===58?58:72,d=s.totals.discount,f=s.totals.tax,g=s.totals.total,_=s.totals.subtotal,v=String(e.metodo_pago||``).toUpperCase()===`MIXTO`,b=v?g:_,S=(v?p(e):[]).map(e=>`
    <div class="payment-row">
      <span>${e.etiqueta}</span>
      <strong>${c}${N(e.monto)}</strong>
    </div>
  `).join(``),C=o.language===`en`?`PAYMENT SUMMARY`:`RESUMEN DE PAGO`,T=o.language===`en`?`PAYMENT DISTRIBUTION`:`DISTRIBUCION DEL PAGO`,k=d>0||n.some(e=>M(e.descuento)>0),P=W(n,c,k),F=j(e.otro,[]),L=Array.isArray(F)?F[0]:{},R=M(L?.pagocon),z=M(L?.sucambio),B=L?.delivery||``,H=e.rnc_cliente||e.cedula_cliente||e.rnc||``,G=U(e.no_factura||e.id||``),K=!!(r&&(i?.documentStampUrl||V(e))),q=[];A(a.show_address)&&t.direccion&&q.push(t.direccion);let J=[];A(a.show_phone)&&t.telefono&&J.push(t.telefono),A(a.show_email)&&t.email&&J.push(t.email),J.length&&q.push(J.join(` / `)),A(a.show_legal)&&(t.legal||t.rnc)&&q.push(`${y.businessIdLabel}: ${t.legal||t.rnc}`);let Y=q.length?`<div class="brand-info">${q.join(`<br>`)}</div>`:``;return`<!DOCTYPE html>
<html lang="${o.language}">
<head>
  <meta charset="utf-8">
  <title>${o.invoice} - ${e.no_factura||``}</title>
  <style>
    * { box-sizing: border-box; font-family: Arial, Helvetica, sans-serif; }
    @page { size: ${u}mm auto; margin: 0; }
    html { width: ${u}mm; margin: 0; padding: 0; background: #fff; }
    body { width: ${u}mm; margin: 0; padding: 0 3mm; background: #fff; color: #000; font-size: 10px; overflow: hidden; }
    .ticket { width: 100%; max-width: 100%; margin: 0; padding: 8px 0 12px; overflow: hidden; }
    .brand { text-align: center; padding-bottom: 7px; }
    .brand img { display: block; max-width: 92px; max-height: 62px; object-fit: contain; margin: 0 auto 4px; }
    .brand-name { font-size: 16px; line-height: 1.1; font-weight: 800; text-transform: uppercase; }
    .brand-info { margin-top: 5px; font-size: 8px; line-height: 1.35; }
    .rule { width: 100%; border-top: 1px dashed #000; margin: 7px 0; }
    .document { border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 7px 0; }
    .document-label { text-align: center; font-size: 9px; font-weight: 700; letter-spacing: .12em; }
    .document-number { margin: 2px 0 6px; text-align: center; font-size: 15px; line-height: 1.1; font-weight: 800; overflow-wrap: anywhere; }
    .meta-row { display: flex; justify-content: space-between; gap: 8px; padding: 1px 0; line-height: 1.3; }
    .meta-label { flex: 0 0 auto; font-size: 8px; font-weight: 700; text-transform: uppercase; }
    .meta-value { min-width: 0; text-align: right; overflow-wrap: anywhere; }
    .customer { padding: 7px 0 2px; }
    .section-title { margin-bottom: 4px; text-align: center; font-size: 8px; font-weight: 800; letter-spacing: .12em; }
    table { width: 100%; border-collapse: collapse; }
    .items thead th { padding: 5px 2px; border-top: 1px solid #000; border-bottom: 1px solid #000; font-size: 8px; text-transform: uppercase; }
    .items tbody td { padding: 2px; vertical-align: top; font-size: 9px; }
    .items .item-name td { padding-top: 6px; font-weight: 800; }
    .items .item-values td { padding-bottom: 5px; border-bottom: 1px dotted #777; }
    .centrado { text-align: center; }
    .derecha, .precio { text-align: right; }
    .summary { margin-top: 8px; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 5px 0; }
    .summary-title { margin-bottom: 4px; text-align: center; font-size: 8px; font-weight: 800; letter-spacing: .1em; }
    .summary-row, .payment-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; padding: 2px 0; }
    .summary-row span:first-child, .payment-row span { min-width: 0; overflow-wrap: anywhere; }
    .summary-row strong, .payment-row strong { flex: 0 0 auto; text-align: right; }
    .payment-group { margin: 4px 0; padding: 4px 0; border-top: 1px dashed #777; border-bottom: 1px dashed #777; }
    .payment-caption { margin-bottom: 2px; font-size: 7px; font-weight: 800; letter-spacing: .08em; }
    .payment-row { font-size: 9px; }
    .grand-total { margin-top: 3px; padding-top: 5px; border-top: 1px solid #000; font-size: 14px; font-weight: 800; }
    .note { margin-top: 7px; padding: 6px 0; border-top: 1px dashed #000; border-bottom: 1px dashed #000; font-size: 8px; line-height: 1.35; }
    .code-block { margin-top: 8px; text-align: center; }
    .barcode svg { display: block; max-width: 100%; margin: 0 auto; }
    .qr-code img { display: block; width: ${u===58?112:130}px; height: ${u===58?112:130}px; margin: 0 auto; }
    .security-code { margin-top: 3px; font-size: 8px; font-weight: 700; }
    .footer { margin-top: 9px; padding-top: 7px; border-top: 1px dashed #000; text-align: center; font-size: 10px; font-weight: 700; }
  </style>
</head>
<body>
  <div class="ticket">
    <div class="brand">
      ${A(a.show_logo)&&l?`<img src="${l}" alt="Logo">`:``}
      ${A(a.show_company_name)?`<div class="brand-name">${t.nombre||o.company}</div>`:``}
      ${Y}
    </div>

    <div class="document">
      <div class="document-label">${E(e.tipo_factura)}</div>
      <div class="document-number">#${e.no_factura||``}</div>
      ${e.fecha_emision?`<div class="meta-row"><span class="meta-label">${o.date}</span><span class="meta-value">${e.fecha_emision||``} ${e.hora||``}</span></div>`:``}
      ${e.ncf||e.comprobante?`<div class="meta-row"><span class="meta-label">${y.fiscalDocumentLabel}</span><span class="meta-value">${e.ncf||e.comprobante}</span></div>`:``}
    </div>

    ${A(a.show_cliente)?`<div class="customer">
      <div class="section-title">${o.customer}</div>
      <div class="meta-row"><span class="meta-label">${o.customer}</span><span class="meta-value">${D(e.nombre_cliente)}</span></div>
      ${H?`<div class="meta-row"><span class="meta-label">${y.customerIdLabel}</span><span class="meta-value">${H}</span></div>`:``}
      ${e.telefono_cliente?`<div class="meta-row"><span class="meta-label">${o.phone}</span><span class="meta-value">${e.telefono_cliente}</span></div>`:``}
      ${e.direccion_cliente?`<div class="meta-row"><span class="meta-label">${o.address}</span><span class="meta-value">${e.direccion_cliente}</span></div>`:``}
      ${e.vendedor?`<div class="meta-row"><span class="meta-label">${o.seller}</span><span class="meta-value">${e.vendedor}</span></div>`:``}
      ${e.cajero?`<div class="meta-row"><span class="meta-label">${o.cashier}</span><span class="meta-value">${e.cajero}</span></div>`:``}
      ${B?`<div class="meta-row"><span class="meta-label">DELIVERY</span><span class="meta-value">${B}</span></div>`:``}
      ${e.metodo_pago?`<div class="meta-row"><span class="meta-label">${o.paymentMethod}</span><span class="meta-value">${h(e)}</span></div>`:``}
    </div>`:``}

    <div class="rule"></div>

    ${A(a.show_items)?`<table class="items">
      <thead>
        <tr>
          <th>${o.quantity}</th>
          <th>${o.package}</th>
          <th>${o.price}</th>
          ${k?`<th class="precio">${o.discount}</th>`:``}
          <th class="precio">${o.total}</th>
        </tr>
      </thead>
      <tbody>${P}</tbody>
    </table>`:``}

    ${A(a.show_totals)?`<div class="summary">
      <div class="summary-title">${C}</div>
      <div class="summary-row"><span>${o.subtotal}</span><strong>${c}${N(b)}</strong></div>
      ${d>0?`<div class="summary-row"><span>${o.discount}</span><strong>${c}${N(d)}</strong></div>`:``}
      ${f>0?`<div class="summary-row"><span>${y.shortName}</span><strong>${c}${N(f)}</strong></div>`:``}
      ${v&&S?`<div class="payment-group"><div class="payment-caption">${T}</div>${S}</div>`:``}
      <div class="summary-row grand-total"><span>${o.total}</span><strong>${c}${N(g)}</strong></div>
      ${R>0?`<div class="summary-row"><span>${o.paidWith}</span><strong>${c}${N(R)}</strong></div>`:``}
      ${R>0?`<div class="summary-row"><span>${o.change}</span><strong>${c}${N(z)}</strong></div>`:``}
    </div>`:``}

    ${A(a.show_nota)&&e.nota?`<div class="note"><strong>${o.observation}:</strong><br>${String(e.nota).replace(/\n/g,`<br>`)}</div>`:``}

    ${A(a.show_barcode)&&G?`<div class="code-block barcode">${G}</div>`:``}

    ${(A(a.show_qr)||K)&&r?`<div class="code-block qr-code">
      <img src="${r}" alt="Codigo QR">
      ${i?.securityCode?`<div class="security-code">${o.securityCode}: ${i.securityCode}</div>`:``}
    </div>
    `:``}

    ${A(a.show_footer)?`<div class="footer">${a.footer_text===x.footer_text?o.thanks:a.footer_text||``}</div>`:``}
  </div>
</body>
</html>`}async function K(e){let t=S(e),n={...x};try{let e=await window.db.getAll(`impresoras_config`);e.success&&e.data?.length>0&&(n={...n,...e.data[0]},b.value=n.printer_name||``)}catch{}let r=localStorage.getItem(`etiquetas_printer`);r&&!n.printer_name&&(b.value=r);let i=j(t.productos,[]),a={};try{let e=await window.db.getAll(`empresa`);e.success&&e.data?.length>0&&(a=C(e.data,t))}catch{}t.empresa&&typeof t.empresa==`object`&&(a=t.empresa);let o=await R(a?.logoprinter||a?.logo);o&&(a={...a,logo:o,logoprinter:o});let s=await B(t),c=await H(s.documentStampUrl||`https://tmposrd.com/factura/${t.no_factura}`),l=G({factura:t,empresa:a,productos:Array.isArray(i)?i:[],qrCodeData:c,alanubeData:s,ticketConfig:n});try{let e=M(n.paper_width,80)===58?58:72,t=await window.electron.invoke(`print:ticket`,l,b.value||void 0,{width:e});t.success?g.add({severity:`success`,summary:`Imprimiendo...`,life:2e3}):g.add({severity:`error`,summary:`Error`,detail:t.error,life:3e3})}catch(e){g.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}}return o({printTicket:K}),(e,i)=>(t(),n(`div`,k,[r(a(u))]))}});export{D as a,O as i,S as n,T as o,w as r,A as t};