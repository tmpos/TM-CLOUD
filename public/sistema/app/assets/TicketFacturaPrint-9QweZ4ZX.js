import{r as e}from"./rolldown-runtime-hePW80VL.js";import{j as t,v as n}from"./reactivity.esm-bundler-CYZ1FWiK.js";import{P as r,d as i,g as a,v as o}from"./runtime-core.esm-bundler-CyLDb3-k.js";import{f as s,o as c,u as l}from"./tmCloudClient-FObHWOav.js";import{n as u,t as d}from"./invoiceCreditPayment-BiGdfNIQ.js";import{Et as f,H as p,I as m,P as h}from"./index-C44qAKLU.js";import{t as g}from"./browser-Cpc4qo6y.js";import{i as ee}from"./ticketLogoSettings-DWcskBne.js";import{n as _}from"./ticketPrinterSettings-DGEi3DvG.js";import{c as v,d as te,i as y,l as ne,n as b,o as x,r as S,u as C}from"./documentDataService-CAVkl6Yc.js";import{t as w}from"./ticketWebsite-BblSxZgt.js";import{i as T,n as E}from"./eloInvoicePrintService-DtNP8vVA.js";import{n as re,r as ie,t as ae}from"./hondurasFiscalDocument-CtFiURLc.js";import{s as oe}from"./electronicInvoiceTypes-Dbkp0D11.js";import{t as D}from"./JsBarcode-JyLvbIYG.js";function O(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function k(e){if(e&&typeof e==`object`)return e;if(typeof e==`string`)try{return JSON.parse(e)}catch{return{}}return{}}function A(e){let t=e=>(Array.isArray(e)?e:String(e||``).split(`,`)).map(e=>String(e||``).trim()).filter(Boolean),n=[...new Set([...t(e.imeis),...t(e.imei)])],r=[...new Set([...t(e.seriales),...t(e.serial)])];return[String(e.nombre||e.descripcion||e.producto||``).trim(),n.length?`IMEI: ${n.join(`, `)}`:``,r.length?`Serial: ${r.join(`, `)}`:``].filter(Boolean).join(` | `)}function j(e){let{factura:t,empresa:n,productos:r}=e,i=y({factura:t,empresa:n,items:r}),a=k(t.otro),o=a?.cobro_caja||a?.cobro_pos||(Array.isArray(a)?a[0]:a)||{},s=C(t.metodo_pago),c=d(t),l=O(o.efectivo_recibido??o.pagocon),u=O(t.monto_pagado),f=l>0?l:u>0?u:/^CR[EÉ]DITO$/i.test(String(t.metodo_pago||``))?0:c,p=O(o.cambio??o.sucambio??t.cambio),m=String(t.nota||``).trim(),h=[String(e.footerText||``).trim(),m].filter(Boolean).join(`
`);return{cutPaper:!0,company:{name:String(n.nombre||n.nombre_comercial||``),commercialName:String(n.nombre_comercial||n.nombre||``),rnc:String(n.legal||n.rnc||``),address:String(n.direccion||``),phone:String(n.telefono||``),email:String(n.email||``),website:String(n.website||n.sitio_web||``),logoBase64:/^data:image\//i.test(String(e.logo||``))?String(e.logo):``},customer:{name:String(i.customer.nombre||``),rnc:String(i.customer.documento||``),phone:String(i.customer.telefono||``)},invoice:{invoiceNumber:v(t),ncf:String(t.ncf||t.comprobante||``),date:String(t.fecha_emision||t.fecha||``),time:String(t.hora||``),cashier:String(t.cajero||t.vendedor||``),paymentMethod:s},items:i.items.map(e=>{let t=O(e.cantidad??e.quantity,1),n=O(e.precio_venta??e.precio_unitario??e.precio??e.price);return{code:String(e.codigo||e.codigo_barras||e.sku||``),description:A(e),quantity:t,price:n,unitPrice:n,discount:O(e.descuento),tax:O(e.impuesto??e.itbis),total:O(e.total,O(e.precio_final??n)*t)}}),totals:{subtotal:i.totals.subtotal,discount:i.totals.discount,tax:i.totals.tax,shipping:O(t.delivery_amount??t.costo_envio),grandTotal:i.totals.total,paidAmount:f,changeAmount:p},payment:{method:s},footer:{message:h,qrData:``},qrUrl:``}}var M=e(g()),N=e(D()),P={style:{display:`none`}},F=o({__name:`TicketFacturaPrint`,setup(e,{expose:o}){function g(e){let t=b(),n=R(e?.otro,{}),r=[],i=Number(e.efectivo||0);i>0&&r.push({tipo:`efectivo`,etiqueta:t.cash,monto:i});let a=R(e.tarjeta_mixta,e.tarjeta_mixta),o=a&&typeof a==`object`?a:n?.tarjeta_mixta||{},s=Number(e.tarjeta||o?.total||Number(o?.monto_base||0)+Number(o?.monto_comision||0));s>0&&r.push({tipo:`tarjeta`,etiqueta:t.card,monto:s});let c=R(e.transferencias_mixtas,e.transferencias_mixtas),l=R(n?.transferencias_mixtas,n?.transferencias_mixtas),u=Array.isArray(c)?c:Array.isArray(l)?l:Number(e.transferencia)>0?[{monto:e.transferencia,banco_nombre:e.banco_nombre||n?.banco_nombre||``}]:[];for(let e of u){let n=Number(e?.monto||0);if(n<=0)continue;let i=e?.banco_nombre?` (${e.banco_nombre})`:``;r.push({tipo:`transferencia`,etiqueta:`${t.transfer}${i}`,monto:n})}if(Number(e.cheque)>0&&r.push({tipo:`cheque`,etiqueta:t.check,monto:Number(e.cheque)}),i<=0){let n=r.reduce((e,t)=>e+t.monto,0),i=Math.round((d(e)-n)*100)/100;i>.009&&r.unshift({tipo:`efectivo`,etiqueta:t.cash,monto:i})}return r}function D(e){let t=b();return String(e.metodo_pago||``).toLowerCase()===`mixto`?t.mixed:C(e.metodo_pago)}let O=f();h();let k=n(``),A={copies:1,printer_name:``,paper_width:80,show_logo:1,show_company_name:1,show_legal:1,show_phone:1,show_address:1,show_email:1,show_cliente:1,show_items:1,show_totals:1,show_barcode:1,show_footer:1,show_qr:0,show_nota:1,footer_text:`Gracias por su compra`};function F(e){let t={...e||{}},n=Array.isArray(t.items)?t.items:R(t.productos,[]);return{...t,fecha_emision:t.fecha_emision||t.fecha||``,nombre_cliente:t.nombre_cliente||t.cliente||`CONSUMIDOR FINAL`,telefono_cliente:t.telefono_cliente||t.telefono||``,comprobante:t.comprobante||t.tipo_comprobante||``,productos:n.map(e=>({...e,precio_venta:e.precio_venta??e.precio,precio_final:e.precio_final??e.precio,total:e.total??Number(e.precio||e.precio_venta||0)*Number(e.cantidad||1)}))}}function I(e,t){return t?.empresa&&typeof t.empresa==`object`?t.empresa:x(t,e)}function L(e){return e===!0||e===1||e===`1`}function R(e,t){if(e==null)return t;if(typeof e==`string`)try{return JSON.parse(e)}catch{return t}return e}function z(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function B(e){return z(e).toFixed(2)}function V(e){return z(e.precio_venta??e.precio_unitario??e.precio??e.price)}function H(e){let t=z(e.cantidad??e.quantity,1),n=z(e.precio_final??e.precio_venta??e.precio_unitario??e.precio??e.price);return z(e.total,n*t)}function se(e){return String(e?.logoprinter||e?.logo||``).trim()}function U(e){let t=new Uint8Array(e),n=``,r=32768;for(let e=0;e<t.length;e+=r)n+=String.fromCharCode(...t.subarray(e,e+r));return btoa(n)}async function W(e){let t=String(e||``).trim();if(!t||/^data:/i.test(t)||/^(file:|blob:|\/)/i.test(t))return t;try{await c();let e=s(t),n=l()?.key||``;if(!e)return t;let r=await fetch(e,{headers:n?{Authorization:`Bearer ${n}`}:{}});return r.ok?`data:${r.headers.get(`content-type`)||`image/png`};base64,${U(await r.arrayBuffer())}`:t}catch{return t}}function G(e,t={}){let n=R(e?.otro,{}),r=n?.alanube_response||e?.alanube_response||{};return{documentStampUrl:t?.document_stamp_url||e?.document_stamp_url||e?.documentStampUrl||n?.documentStampUrl||n?.document_stamp_url||r?.documentStampUrl||r?.document_stamp_url||``,securityCode:t?.security_code||e?.codigo_seguridad||e?.securityCode||n?.securityCode||n?.security_code||r?.securityCode||r?.security_code||``}}async function K(e){if(e?.id)try{let t=await window.db.getWhere(`facturas_ecf`,`factura_id = ?`,[e.id]),n=t?.success&&Array.isArray(t.data)?t.data[0]:null;if(n)return G(e,n)}catch{}return G(e)}function ce(e){return/^E\d{2}/i.test(String(e?.ncf||e?.comprobante||e?.tipo_comprobante||``))}async function q(e){try{return await M.toDataURL(e,{width:200,margin:1})}catch{return``}}function le(e){if(!e)return``;try{let t=document.createElementNS(`http://www.w3.org/2000/svg`,`svg`);return(0,N.default)(t,e,{format:`CODE128`,width:2,height:50,displayValue:!0,fontSize:12,margin:2}),new XMLSerializer().serializeToString(t).replace(/width="[^"]*"/,`width="180"`).replace(/height="[^"]*"/,`height="55"`)}catch{return``}}function ue(e,t,n){return e.map(e=>{let r=z(e.cantidad??e.quantity,1),i=V(e),a=H(e),o=z(e.descuento),s=e.nombre||e.descripcion||e.producto||``,c=[e.imeis,e.imei].flatMap(e=>Array.isArray(e)?e:e?String(e).split(`,`):[]).map(e=>String(e||``).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t),l=[e.seriales,e.serial].flatMap(e=>Array.isArray(e)?e:e?String(e).split(`,`):[]).map(e=>String(e||``).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t),u=c.length?`<br><span style="font-size:8px;color:#555;">IMEI: ${c.join(`, `)}</span>`:``,d=l.length?`<br><span style="font-size:8px;color:#555;">Serial: ${l.join(`, `)}</span>`:``;return`
      <tr class="item-name">
        <td colspan="${n?5:4}" style="overflow-wrap:break-word;white-space:normal;word-break:break-word;">
          ${s}${u}${d}
        </td>
      </tr>
      <tr class="item-values">
        <td>${r} x</td>
        <td>${e.empaque||``}</td>
        <td>${t}${B(i)}</td>
        ${n?`<td class="precio">${t}${B(o)}</td>`:``}
        <td class="precio">
          <b>${t}${B(a)}</b>
        </td>
      </tr>
    `}).join(``)}function J({factura:e,empresa:t,productos:n,qrCodeData:r,websiteQrData:i,alanubeData:a,ticketConfig:o}){let s=b(),c=re(e),l=h(c?`HN`:void 0);c&&(t={...t,nombre:c.legalName,legal:c.taxpayerId,rnc:c.taxpayerId,direccion:c.address}),c&&(o={...o,show_items:1,show_totals:1});let f=y({factura:e,empresa:t,items:n}),p=f.isQuote;e={...e,no_factura:v(e)},n=f.items;let _=c?`HNL`:m(),x=se(t),S=z(o.paper_width,80)===58?58:72,C=f.totals.discount,w=f.totals.tax,T=f.totals.total,E=p?0:u(e),O=E>0,k=Number((e.salon_deposit||R(e.otro,{})?.salon_deposit)?.amount||0),j=!p&&Number.isFinite(k)&&k>0&&k<=T,M=f.totals.subtotal,N=!p&&String(e.metodo_pago||``).toUpperCase()===`MIXTO`,P=c?c.base+C:N?T:M,F=(N?g(e):[]).map(e=>`
    <div class="payment-row">
      <span>${e.etiqueta}</span>
      <strong>${_}${B(e.monto)}</strong>
    </div>
  `).join(``),I=p?s.quote.toUpperCase():s.language===`en`?`PAYMENT SUMMARY`:`RESUMEN DE PAGO`,V=s.language===`en`?`PAYMENT DISTRIBUTION`:`DISTRIBUCION DEL PAGO`,H=C>0||n.some(e=>z(e.descuento)>0),U=ue(n,_,H),W=R(e.otro,[]),G=W?.cobro_caja||W?.cobro_pos,K=G?{pagocon:G.efectivo_recibido,sucambio:G.cambio}:Array.isArray(W)?W[0]:W||{},q=z(K?.pagocon),J=z(K?.sucambio),Y=K?.delivery||``,X=p?``:oe(e).toUpperCase(),Z=c?c.customerId:e.rnc_cliente||e.cedula_cliente||e.rnc||``,de=le(e.no_factura||e.id||``),fe=!p&&!!(r&&(a?.documentStampUrl||ce(e))),Q=[];L(o.show_address)&&t.direccion&&Q.push(t.direccion);let $=[];L(o.show_phone)&&t.telefono&&$.push(t.telefono),L(o.show_email)&&t.email&&$.push(t.email),$.length&&Q.push($.join(` / `)),L(o.show_legal)&&(t.legal||t.rnc)&&Q.push(`${l.businessIdLabel}: ${t.legal||t.rnc}`);let pe=Q.length?`<div class="brand-info">${Q.join(`<br>`)}</div>`:``;return`<!DOCTYPE html>
<html lang="${s.language}">
<head>
  <meta charset="utf-8">
  <title>${p?s.quote:s.invoice} - ${e.no_factura||``}</title>
  <style>
    * { box-sizing: border-box; font-family: Arial, Helvetica, sans-serif; }
    @page { size: ${S}mm auto; margin: 0; }
    html { width: ${S}mm; margin: 0; padding: 0; background: #fff; }
    body { width: ${S}mm; margin: 0; padding: 0 3mm; background: #fff; color: #000; font-size: 10px; overflow: hidden; }
    .ticket { width: 100%; max-width: 100%; margin: 0; padding: 8px 0 12px; overflow: hidden; }
    .brand { text-align: center; padding-bottom: 7px; }
    .brand img { display: block; max-width: 92px; max-height: 62px; object-fit: contain; margin: 0 auto 4px; }
    .brand-name { font-size: 16px; line-height: 1.1; font-weight: 800; text-transform: uppercase; }
    .brand-info { margin-top: 5px; font-size: 8px; line-height: 1.35; }
    .rule { width: 100%; border-top: 1px dashed #000; margin: 7px 0; }
    .document { border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 7px 0; }
    .document-label { text-align: center; font-size: 9px; font-weight: 700; letter-spacing: .12em; }
    .document-label.receipt-name { font-size: 10px; letter-spacing: .04em; margin-top: 2px; }
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
    .qr-code img { display: block; width: ${S===58?112:130}px; height: ${S===58?112:130}px; margin: 0 auto; }
    .security-code { margin-top: 3px; font-size: 8px; font-weight: 700; }
    .footer { margin-top: 9px; padding-top: 7px; border-top: 1px dashed #000; text-align: center; font-size: 10px; font-weight: 700; }
  </style>
</head>
<body>
  <div class="ticket">
    <div class="brand">
      ${L(o.show_logo)&&x?`<img src="${x}" alt="Logo" style="${ee(o.printer_name||localStorage.getItem(`etiquetas_printer`)||``)}">`:``}
      ${L(o.show_company_name)?`<div class="brand-name">${t.nombre||s.company}</div>`:``}
      ${pe}
    </div>

    <div class="document">
      <div class="document-label">${p?s.quote.toUpperCase():te(e.tipo_factura)}</div>
      ${X?`<div class="document-label receipt-name">${X}</div>`:``}
      <div class="document-number">#${e.no_factura||``}</div>
      ${e.fecha_emision?`<div class="meta-row"><span class="meta-label">${s.date}</span><span class="meta-value">${e.fecha_emision||``} ${e.hora||``}</span></div>`:``}
      ${ae(e)}
      ${!p&&(e.ncf||e.comprobante)?`<div class="meta-row"><span class="meta-label">${c?`Número de factura`:l.fiscalDocumentLabel}</span><span class="meta-value">${e.ncf||e.comprobante}</span></div>`:``}
    </div>

    ${L(o.show_cliente)?`<div class="customer">
      <div class="section-title">${s.customer}</div>
      <div class="meta-row"><span class="meta-label">${s.customer}</span><span class="meta-value">${ne(e.nombre_cliente)}</span></div>
      ${Z?`<div class="meta-row"><span class="meta-label">${l.customerIdLabel}</span><span class="meta-value">${Z}</span></div>`:``}
      ${e.telefono_cliente?`<div class="meta-row"><span class="meta-label">${s.phone}</span><span class="meta-value">${e.telefono_cliente}</span></div>`:``}
      ${e.direccion_cliente?`<div class="meta-row"><span class="meta-label">${s.address}</span><span class="meta-value">${e.direccion_cliente}</span></div>`:``}
      ${e.vendedor?`<div class="meta-row"><span class="meta-label">${s.seller}</span><span class="meta-value">${e.vendedor}</span></div>`:``}
      ${e.cajero?`<div class="meta-row"><span class="meta-label">${s.cashier}</span><span class="meta-value">${e.cajero}</span></div>`:``}
      ${Y?`<div class="meta-row"><span class="meta-label">DELIVERY</span><span class="meta-value">${Y}</span></div>`:``}
      ${!p&&e.metodo_pago?`<div class="meta-row"><span class="meta-label">${s.paymentMethod}</span><span class="meta-value">${D(e)}</span></div>`:``}
    </div>`:``}

    <div class="rule"></div>

    ${L(o.show_items)?`<table class="items">
      <thead>
        <tr>
          <th>${s.quantity}</th>
          <th>${s.package}</th>
          <th>${s.price}</th>
          ${H?`<th class="precio">${s.discount}</th>`:``}
          <th class="precio">${s.total}</th>
        </tr>
      </thead>
      <tbody>${U}</tbody>
    </table>`:``}

    ${L(o.show_totals)?`<div class="summary">
      <div class="summary-title">${I}</div>
      <div class="summary-row"><span>${s.subtotal}</span><strong>${_}${B(P)}</strong></div>
      ${C>0?`<div class="summary-row"><span>${s.discount}</span><strong>${_}${B(C)}</strong></div>`:``}
      ${w>0?`<div class="summary-row"><span>${l.shortName}</span><strong>${_}${B(w)}</strong></div>`:``}
      ${N&&F?`<div class="payment-group"><div class="payment-caption">${V}</div>${F}</div>`:``}
      ${O||j?`<div class="summary-row"><span>${s.language===`en`?`INVOICE TOTAL`:`TOTAL FACTURA`}</span><strong>${_}${B(T)}</strong></div>`:``}
      ${O?`<div class="summary-row"><span>${s.language===`en`?`CREDIT NOTE APPLIED`:`NOTA DE CRÉDITO APLICADA`}</span><strong>-${_}${B(E)}</strong></div>`:``}
      ${j?`<div class="summary-row"><span>${s.language===`en`?`DEPOSIT APPLIED`:`ANTICIPO APLICADO`}</span><strong>-${_}${B(k)}</strong></div>`:``}
      <div class="summary-row grand-total"><span>${O||j?s.language===`en`?`TOTAL TO PAY`:`TOTAL A PAGAR`:s.total}</span><strong>${_}${B(O||j?d(e):T)}</strong></div>
      ${!p&&q>0?`<div class="summary-row"><span>${s.paidWith}</span><strong>${_}${B(q)}</strong></div>`:``}
      ${!p&&q>0?`<div class="summary-row"><span>${s.change}</span><strong>${_}${B(J)}</strong></div>`:``}
    </div>`:``}

    ${p?``:ie(e,s.language)}
    ${O&&!L(o.show_totals)?`<div class="summary"><div class="summary-row"><span>Nota de crédito aplicada</span><strong>${_}${B(E)}</strong></div><div class="summary-row"><span>Total a pagar</span><strong>${_}${B(d(e))}</strong></div></div>`:``}
${j?`<div class="summary salon-deposit"><div class="summary-row"><span>Anticipo aplicado</span><strong>${_}${B(k)}</strong></div><div class="summary-row"><span>Saldo cobrado</span><strong>${_}${B(d(e))}</strong></div></div>`:``}
    ${L(o.show_nota)&&e.nota?`<div class="note"><strong>${s.observation}:</strong><br>${String(e.nota).replace(/\n/g,`<br>`)}</div>`:``}

    ${L(o.show_barcode)&&de?`<div class="code-block barcode">${de}</div>`:``}

    ${!p&&fe&&r?`<div class="code-block qr-code">
      <img src="${r}" alt="Codigo QR">
      ${a?.securityCode?`<div class="security-code">${s.securityCode}: ${a.securityCode}</div>`:``}
    </div>
    `:``}

    ${!p&&L(o.show_qr)&&i?`<div class="code-block qr-code"><div class="section-title">Visita nuestra página web</div><img src="${i}" alt="QR de la página web"></div>`:``}
    ${!p&&L(o.show_footer)?`<div class="footer">${o.footer_text===A.footer_text?s.thanks:o.footer_text||``}</div>`:``}
  </div>
</body>
</html>`}function Y(e){let t=Number(e);return Number.isInteger(t)&&t>=1&&t<=10?t:1}async function X(){let e=await window.db.getAll(`impresoras_config`);if(!e.success)throw Error(e.error||`No se pudo consultar la configuración de impresoras.`);return Y(e.data?.[0]?.copies)}async function Z(e,t){let n=F(e),r=null;if(window.__isServerSystem)try{r=await E()}catch(e){return O.add({severity:`error`,summary:`Impresión Elo`,detail:e.message,life:6e3}),!1}let i={...A};k.value=``;try{let e=await window.db.getAll(`impresoras_config`);e.success&&e.data?.length>0&&(i={...i,...e.data[0]})}catch{}let a=Y(t??i.copies);if(!r?.enabled)try{let e=await _(i.printer_name);k.value=e.name,i.printer_name=e.name,e.usedDefault&&O.add({severity:`info`,summary:`Impresora predeterminada`,detail:`La impresora guardada no está instalada en este equipo. Se enviará a la impresora predeterminada.`,life:5e3})}catch(e){return O.add({severity:`error`,summary:`Impresora no disponible`,detail:e?.message||`Selecciona una impresora en Configuración.`,life:6e3}),!1}let o=R(n.productos,[]),s={};try{let e=await window.db.getAll(`empresa`);e.success&&e.data?.length>0&&(s=I(e.data,n))}catch{}n.empresa&&typeof n.empresa==`object`&&(s=n.empresa);let c=await W(s?.logoprinter||s?.logo);c&&(s={...s,logo:c,logoprinter:c});let l=S(n)?G({}):await K(n),u=l.documentStampUrl||`https://tmposrd.com/factura/${n.no_factura}`,d=S(n)||r?.enabled?``:await q(u),f=!r?.enabled&&!S(n)&&L(i.show_qr)?await w():``,p=f?await q(f):``,m=r?.enabled?j({factura:n,empresa:s,productos:Array.isArray(o)?o:[],logo:L(i.show_logo)?c:``,footerText:L(i.show_footer)?i.footer_text===A.footer_text?b().thanks:i.footer_text||``:``}):null,h=r?.enabled?``:J({factura:n,empresa:s,productos:Array.isArray(o)?o:[],qrCodeData:d,websiteQrData:p,alanubeData:l,ticketConfig:i});try{let e=z(i.paper_width,80)===58?58:72;for(let t=1;t<=a;t++){if(r?.enabled){try{await T(m,r)}catch(e){return O.add({severity:`error`,summary:`Error de impresión Elo`,detail:`Copia ${t} de ${a}: ${e?.message||`No se pudo imprimir`}.`,life:6e3}),!1}continue}let n=await window.electron.invoke(`print:ticket`,h,k.value||void 0,{width:e});if(!n.success)return O.add({severity:`error`,summary:`Error de impresión`,detail:`Copia ${t} de ${a}: ${n.error||`No se pudo imprimir`}. Copias enviadas: ${t-1}.`,life:6e3}),!1}return O.add({severity:`success`,summary:r?.enabled?`Factura enviada a la Elo`:`Imprimiendo...`,life:2e3}),!0}catch(e){return O.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3}),!1}}return o({printTicket:Z,getDefaultCopies:X}),(e,n)=>(r(),i(`div`,P,[a(t(p))]))}});export{F as t};