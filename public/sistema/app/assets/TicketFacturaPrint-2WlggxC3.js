import{r as e}from"./rolldown-runtime-hePW80VL.js";import{A as t,v as n}from"./reactivity.esm-bundler-C6Po7tbM.js";import{P as r,d as i,g as a,v as o}from"./runtime-core.esm-bundler-EoPqhpgY.js";import{f as s,o as c,u as l}from"./tmCloudClient-BXA3SgwJ.js";import{B as u,M as d,N as f,P as p,xt as m}from"./index-CkJS0fd1.js";import{t as h}from"./browser-Cpc4qo6y.js";import{t as g}from"./JsBarcode-JyLvbIYG.js";var _=e(h()),v=e(g()),y=[`E31`,`E32`,`E45`];function b(e){return String(e||``).trim().toUpperCase()}function x(e){return y.includes(b(e))}function S(e){let t=b(e);return t===`E31`?`fiscal-invoices`:t===`E32`?`invoices`:t===`E45`?`gubernamentals`:null}function C(e){let t=b(e);return t===`E31`||t===`E45`}function w(e,t){if(typeof e==`string`)return e.trim()||`Alanube respondio ${t}`;let n=e&&typeof e==`object`?e:{},r=(Array.isArray(n.errors)?n.errors:Array.isArray(n.response)?n.response:[]).map(e=>[e?.code,e?.message].filter(Boolean).join(`: `)).filter(Boolean).join(` | `);return n.message||n.error||r||`Alanube respondio ${t}`}function T(e){let t=e?.errors;return Array.isArray(t)&&t.some(e=>String(e?.code||``).toUpperCase()===`AP3001`)}function E(e){return Math.round((Number(e)||0)*100)/100}function D(e){let t=E(e.gravado),n=E(e.exento),r=E(e.itbis),i={};if(t>0){let n=Number(e.tasa??18),a=n===16?2:n===0?3:1;i.totalTaxedAmount=t,i[`i${a}AmountTaxed`]=t,i[`itbisS${a}`]=a===1?18:a===2?16:0,i[`itbis${a}Total`]=r,i.itbisTotal=r}return n>0&&(i.exemptAmount=n),i.totalAmount=E(e.total),i}var O={"01":`Factura de Credito Fiscal`,"02":`Factura de Consumo`,"03":`Nota de Debito`,"04":`Nota de Credito`,11:`Comprobante de Compras`,12:`Registro Unico de Ingresos`,13:`Comprobante de Gastos Menores`,14:`Comprobante de Regimenes Especiales`,15:`Comprobante Gubernamental`,16:`Comprobante de Exportacion`,17:`Comprobante de Pagos al Exterior`};function k(e){let t=b(e?.ncf);if(!t||t===`SIN`)return``;let n=[e?.tipo_comprobante,e?.comprobante].map(b).find(e=>/^[BE]\d{2}$/.test(e))||(/^[BE]\d{2}/.test(t)?t.slice(0,3):``);if(!n)return``;let r=n.startsWith(`E`),i=O[r?String(Number(n.slice(1))-30).padStart(2,`0`):n.slice(1)];return i?r?i.startsWith(`Comprobante `)?i.replace(`Comprobante `,`Comprobante Electronico `):`${i} Electronica`:i:``}function A(e){let t=b(e);return/^E\d{2}$/.test(t)&&t!==`E32`&&t!==`E34`}function j(e){let t=String(e?.fecha_vencimiento||``).trim().slice(0,10);return/^\d{4}-\d{2}-\d{2}$/.test(t)?t:``}function M(e){return`El ${b(e)} requiere fecha de vencimiento de la secuencia. Configurala en Configuracion > Comprobantes e-CF`}function N(e,t){let n=Number(t.totalTaxedAmount||0);if(n<=0)return;let r=e.filter(e=>[1,2,3].includes(Number(e.billingIndicator))).reduce((e,t)=>e+Number(t.itemAmount||0),0),i=n+Number(t.itbisTotal||0);return+(Math.abs(r-i)<Math.abs(r-n))}function P(e,t){let n=String(e?.almacen_uid||``).trim();if(n)return t.find(e=>String(e.uid||``)===n||String(e.almacen_uid||``)===n)||{};let r=Number(e?.almacen_id||0);if(r){let e=t.filter(e=>Number(e.almacen_id||e.id)===r);return e.length===1?e[0]:{}}return t[0]||{}}function F(e,t=[]){if(Array.isArray(e))return e;if(e==null||String(e).trim()===``)return t;try{let n=JSON.parse(String(e));return Array.isArray(n)?n:t}catch{return t}}function I(e){return(Array.isArray(e)?e:e==null?[]:[e]).flatMap(e=>typeof e==`string`?e.split(`,`):[e]).map(e=>String(e?.imei??e?.nombre??e??``).trim()).filter(Boolean)}function L(e){return e?/\b(?:KB|MB|GB|TB)\b/i.test(e)?e:/^\d+(?:[.,]\d+)?$/.test(e)?`${e} GB`:e:``}function R(e){let t=I(e?.imeis?.length?e.imeis:e?.imei);if(!t.length)return[];let n=I(e?.capacidades?.length?e.capacidades:e?.capacidad);return t.map((e,t)=>{let r=L(n[t]||(n.length===1?n[0]:``));return r?`${e} — ${r}`:e})}var z={es:{language:`es`,date:`Fecha`,quote:`Cotización`,invoice:`Factura`,creditInvoice:`FACTURA A CRÉDITO`,salesInvoice:`FACTURA DE VENTA`,customer:`CLIENTE`,phone:`TELÉFONO`,address:`DIRECCIÓN`,paymentMethod:`MÉTODO DE PAGO`,seller:`VENDEDOR`,cashier:`CAJERO`,code:`CÓD.`,description:`DESCRIPCIÓN`,quantity:`CANT.`,package:`EMPAQ.`,unitPrice:`P.U.`,price:`PRECIO`,discount:`DESC.`,subtotal:`SUBTOTAL`,total:`TOTAL`,observation:`OBSERVACIÓN`,deliveredBy:`ENTREGADO POR`,receivedBy:`RECIBIDO POR`,unregistered:`SIN REGISTRO`,finalConsumer:`CONSUMIDOR FINAL`,company:`MI EMPRESA`,notApplicable:`N/A`,user:`Usuario`,thanks:`¡Gracias por su compra!`,quoteValidity:`Esta cotización tiene una validez de 30 días`,paidWith:`PAGO CON`,change:`SU CAMBIO`,securityCode:`Código de seguridad`,cash:`Efectivo`,card:`Tarjeta`,transfer:`Transferencia`,check:`Cheque`,mixed:`MIXTO`,credit:`CRÉDITO`,saleInvoiceType:`FACTURA_VENTA`},en:{language:`en`,date:`Date`,quote:`Quote`,invoice:`Invoice`,creditInvoice:`CREDIT INVOICE`,salesInvoice:`SALES INVOICE`,customer:`CUSTOMER`,phone:`PHONE`,address:`ADDRESS`,paymentMethod:`PAYMENT METHOD`,seller:`SALESPERSON`,cashier:`CASHIER`,code:`CODE`,description:`DESCRIPTION`,quantity:`QTY.`,package:`PACK`,unitPrice:`UNIT PRICE`,price:`PRICE`,discount:`DISC.`,subtotal:`SUBTOTAL`,total:`TOTAL`,observation:`NOTE`,deliveredBy:`DELIVERED BY`,receivedBy:`RECEIVED BY`,unregistered:`NOT PROVIDED`,finalConsumer:`FINAL CONSUMER`,company:`MY COMPANY`,notApplicable:`N/A`,user:`User`,thanks:`Thank you for your purchase!`,quoteValidity:`This quote is valid for 30 days`,paidWith:`AMOUNT PAID`,change:`CHANGE`,securityCode:`Security code`,cash:`Cash`,card:`Card`,transfer:`Transfer`,check:`Check`,mixed:`MIXED`,credit:`CREDIT`,saleInvoiceType:`FACTURA_VENTA`}};function B(){return(localStorage.getItem(`sistema_idioma`)||`es`).toLowerCase().startsWith(`en`)?z.en:z.es}function V(e){let t=B(),n=String(e||``).trim();return{EFECTIVO:t.cash,TARJETA:t.card,TRANSFERENCIA:t.transfer,CHEQUE:t.check,MIXTO:t.mixed,CREDITO:t.credit,CRÉDITO:t.credit}[n.toUpperCase()]||n}function H(e){let t=B(),n=String(e||``).trim().toUpperCase();return n.includes(`COTIZACION`)||n.includes(`COTIZACIÓN`)?t.quote.toUpperCase():n===t.saleInvoiceType||n===`FACTURA`||n===`FACTURA DE VENTA`?t.salesInvoice:String(e||t.salesInvoice)}function U(e){let t=B(),n=String(e||``).trim();return!n||n.toUpperCase()===`CONSUMIDOR FINAL`?t.finalConsumer:n}function W(e){let t=e.factura||{},n=F(t.productos,e.items||t.items||[]),r=Number(t.total||0),i=Number(t.impuesto??t.impuestos??0),a=Number(t.descuento||0),o=Number(t.subtotal??r+a-i),s=f(),c={nombre:U(t.nombre_cliente||t.cliente||e.cliente?.nombre),documento:t.rnc_cliente||t.cedula_cliente||t.cedula_rnc||t.customer_document||e.cliente?.rnc||e.cliente?.cedula||e.cliente?.cedula_rnc||``,telefono:t.telefono_cliente||t.telefono||t.customer_phone||e.cliente?.telefono||e.cliente?.whatsapp||``,direccion:t.direccion_cliente||t.direccion||t.delivery_address||e.cliente?.direccion||``,email:t.email_cliente||t.correo_cliente||t.customer_email||e.cliente?.email||e.cliente?.correo||``};return{factura:t,empresa:e.empresa||t.empresa||{},customer:c,items:n,totals:{subtotal:o,discount:a,tax:i,total:r},regional:s,documentNumber:G(t)?``:t.ncf||t.comprobante||``,isQuote:G(t)}}function G(e){return[e?.tipo_factura,e?.estado_factura].some(e=>String(e||``).trim().normalize(`NFD`).replace(/[\u0300-\u036f]/g,``).toUpperCase()===`COTIZACION`)}function K(e){return`COT${String(e||``).trim().replace(/^(?:COT|F)[-\s]*/i,``).padStart(6,`0`)}`}function q(e){let t=e?.no_factura||e?.id||``;return G(e)?K(t):String(t)}function J(e,t){for(let n of[e.cliente_uid,e.cliente_id,e.cod_cliente])if(String(n??``).trim())for(let e of[`uid`,`id`,`codigo`]){let r=t.filter(t=>t[e]!=null&&String(t[e])===String(n));if(r.length===1)return r[0]}for(let[n,r]of[[e.rnc_cliente||e.cedula_cliente||e.cedula_rnc,[`rnc`,`cedula`,`cedula_rnc`]],[e.telefono_cliente||e.telefono,[`telefono`,`whatsapp`]],[e.nombre_cliente||e.cliente,[`nombre`]]]){let e=String(n||``).trim().toUpperCase();if(!e||e===`CONSUMIDOR FINAL`)continue;let i=t.filter(t=>r.some(n=>String(t[n]||``).trim().toUpperCase()===e));if(i.length===1)return i[0]}return{}}var Y={style:{display:`none`}},X=o({__name:`TicketFacturaPrint`,setup(e,{expose:o}){function f(e){let t=B(),n=T(e?.otro,{}),r=[],i=Number(e.efectivo||0);i>0&&r.push({tipo:`efectivo`,etiqueta:t.cash,monto:i});let a=T(e.tarjeta_mixta,e.tarjeta_mixta),o=a&&typeof a==`object`?a:n?.tarjeta_mixta||{},s=Number(e.tarjeta||o?.total||Number(o?.monto_base||0)+Number(o?.monto_comision||0));s>0&&r.push({tipo:`tarjeta`,etiqueta:t.card,monto:s});let c=T(e.transferencias_mixtas,e.transferencias_mixtas),l=T(n?.transferencias_mixtas,n?.transferencias_mixtas),u=Array.isArray(c)?c:Array.isArray(l)?l:Number(e.transferencia)>0?[{monto:e.transferencia,banco_nombre:e.banco_nombre||n?.banco_nombre||``}]:[];for(let e of u){let n=Number(e?.monto||0);if(n<=0)continue;let i=e?.banco_nombre?` (${e.banco_nombre})`:``;r.push({tipo:`transferencia`,etiqueta:`${t.transfer}${i}`,monto:n})}if(Number(e.cheque)>0&&r.push({tipo:`cheque`,etiqueta:t.check,monto:Number(e.cheque)}),i<=0){let n=r.reduce((e,t)=>e+t.monto,0),i=Math.round((Number(e.total||0)-n)*100)/100;i>.009&&r.unshift({tipo:`efectivo`,etiqueta:t.cash,monto:i})}return r}function h(e){let t=B();return String(e.metodo_pago||``).toLowerCase()===`mixto`?t.mixed:V(e.metodo_pago)}let g=m(),y=d(),b=n(``),x={printer_name:``,paper_width:80,show_logo:1,show_company_name:1,show_legal:1,show_phone:1,show_address:1,show_email:1,show_cliente:1,show_items:1,show_totals:1,show_barcode:1,show_footer:1,show_qr:0,show_nota:1,footer_text:`Gracias por su compra`};function S(e){let t={...e||{}},n=Array.isArray(t.items)?t.items:T(t.productos,[]);return{...t,fecha_emision:t.fecha_emision||t.fecha||``,nombre_cliente:t.nombre_cliente||t.cliente||`CONSUMIDOR FINAL`,telefono_cliente:t.telefono_cliente||t.telefono||``,comprobante:t.comprobante||t.tipo_comprobante||``,productos:n.map(e=>({...e,precio_venta:e.precio_venta??e.precio,precio_final:e.precio_final??e.precio,total:e.total??Number(e.precio||e.precio_venta||0)*Number(e.cantidad||1)}))}}function C(e,t){return t?.empresa&&typeof t.empresa==`object`?t.empresa:P(t,e)}function w(e){return e===!0||e===1||e===`1`}function T(e,t){if(e==null)return t;if(typeof e==`string`)try{return JSON.parse(e)}catch{return t}return e}function E(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function D(e){return E(e).toFixed(2)}function O(e){return E(e.precio_venta??e.precio_unitario??e.precio??e.price)}function A(e){let t=E(e.cantidad??e.quantity,1),n=E(e.precio_final??e.precio_venta??e.precio_unitario??e.precio??e.price);return E(e.total,n*t)}function j(e){return String(e?.logoprinter||e?.logo||``).trim()}function M(e){let t=new Uint8Array(e),n=``,r=32768;for(let e=0;e<t.length;e+=r)n+=String.fromCharCode(...t.subarray(e,e+r));return btoa(n)}async function N(e){let t=String(e||``).trim();if(!t||/^data:/i.test(t)||/^(https?:\/\/|file:|blob:|\/)/i.test(t))return t;try{await c();let e=s(t),n=l()?.key||``;if(!e)return t;let r=await fetch(e,{headers:n?{Authorization:`Bearer ${n}`}:{}});return r.ok?`data:${r.headers.get(`content-type`)||`image/png`};base64,${M(await r.arrayBuffer())}`:t}catch{return t}}function F(e,t={}){let n=T(e?.otro,{}),r=n?.alanube_response||e?.alanube_response||{};return{documentStampUrl:t?.document_stamp_url||e?.document_stamp_url||e?.documentStampUrl||n?.documentStampUrl||n?.document_stamp_url||r?.documentStampUrl||r?.document_stamp_url||``,securityCode:t?.security_code||e?.codigo_seguridad||e?.securityCode||n?.securityCode||n?.security_code||r?.securityCode||r?.security_code||``}}async function I(e){if(e?.id)try{let t=await window.db.getWhere(`facturas_ecf`,`factura_id = ?`,[e.id]),n=t?.success&&Array.isArray(t.data)?t.data[0]:null;if(n)return F(e,n)}catch{}return F(e)}function L(e){return/^E\d{2}/i.test(String(e?.ncf||e?.comprobante||e?.tipo_comprobante||``))}async function R(e){try{return await _.toDataURL(e,{width:200,margin:1})}catch{return``}}function z(e){if(!e)return``;try{let t=document.createElementNS(`http://www.w3.org/2000/svg`,`svg`);return(0,v.default)(t,e,{format:`CODE128`,width:2,height:50,displayValue:!0,fontSize:12,margin:2}),new XMLSerializer().serializeToString(t).replace(/width="[^"]*"/,`width="180"`).replace(/height="[^"]*"/,`height="55"`)}catch{return``}}function K(e,t,n){return e.map(e=>{let r=E(e.cantidad??e.quantity,1),i=O(e),a=A(e),o=E(e.descuento),s=e.nombre||e.descripcion||e.producto||``,c=[e.imeis,e.imei].flatMap(e=>Array.isArray(e)?e:e?String(e).split(`,`):[]).map(e=>String(e||``).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t),l=[e.seriales,e.serial].flatMap(e=>Array.isArray(e)?e:e?String(e).split(`,`):[]).map(e=>String(e||``).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t),u=c.length?`<br><span style="font-size:8px;color:#555;">IMEI: ${c.join(`, `)}</span>`:``,d=l.length?`<br><span style="font-size:8px;color:#555;">Serial: ${l.join(`, `)}</span>`:``;return`
      <tr class="item-name">
        <td colspan="${n?5:4}" style="overflow-wrap:break-word;white-space:normal;word-break:break-word;">
          ${s}${u}${d}
        </td>
      </tr>
      <tr class="item-values">
        <td>${r} x</td>
        <td>${e.empaque||``}</td>
        <td>${t}${D(i)}</td>
        ${n?`<td class="precio">${t}${D(o)}</td>`:``}
        <td class="precio">
          <b>${t}${D(a)}</b>
        </td>
      </tr>
    `}).join(``)}function J({factura:e,empresa:t,productos:n,qrCodeData:r,alanubeData:i,ticketConfig:a}){let o=B(),s=W({factura:e,empresa:t,items:n}),c=s.isQuote;e={...e,no_factura:q(e)},n=s.items;let l=p(),u=j(t),d=E(a.paper_width,80)===58?58:72,m=s.totals.discount,g=s.totals.tax,_=s.totals.total,v=s.totals.subtotal,b=!c&&String(e.metodo_pago||``).toUpperCase()===`MIXTO`,S=b?_:v,C=(b?f(e):[]).map(e=>`
    <div class="payment-row">
      <span>${e.etiqueta}</span>
      <strong>${l}${D(e.monto)}</strong>
    </div>
  `).join(``),O=c?o.quote.toUpperCase():o.language===`en`?`PAYMENT SUMMARY`:`RESUMEN DE PAGO`,A=o.language===`en`?`PAYMENT DISTRIBUTION`:`DISTRIBUCION DEL PAGO`,M=m>0||n.some(e=>E(e.descuento)>0),N=K(n,l,M),P=T(e.otro,[]),F=Array.isArray(P)?P[0]:{},I=E(F?.pagocon),R=E(F?.sucambio),V=F?.delivery||``,G=c?``:k(e).toUpperCase(),J=e.rnc_cliente||e.cedula_cliente||e.rnc||``,Y=z(e.no_factura||e.id||``),X=!c&&!!(r&&(i?.documentStampUrl||L(e))),Z=[];w(a.show_address)&&t.direccion&&Z.push(t.direccion);let Q=[];w(a.show_phone)&&t.telefono&&Q.push(t.telefono),w(a.show_email)&&t.email&&Q.push(t.email),Q.length&&Z.push(Q.join(` / `)),w(a.show_legal)&&(t.legal||t.rnc)&&Z.push(`${y.businessIdLabel}: ${t.legal||t.rnc}`);let $=Z.length?`<div class="brand-info">${Z.join(`<br>`)}</div>`:``;return`<!DOCTYPE html>
<html lang="${o.language}">
<head>
  <meta charset="utf-8">
  <title>${c?o.quote:o.invoice} - ${e.no_factura||``}</title>
  <style>
    * { box-sizing: border-box; font-family: Arial, Helvetica, sans-serif; }
    @page { size: ${d}mm auto; margin: 0; }
    html { width: ${d}mm; margin: 0; padding: 0; background: #fff; }
    body { width: ${d}mm; margin: 0; padding: 0 3mm; background: #fff; color: #000; font-size: 10px; overflow: hidden; }
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
    .qr-code img { display: block; width: ${d===58?112:130}px; height: ${d===58?112:130}px; margin: 0 auto; }
    .security-code { margin-top: 3px; font-size: 8px; font-weight: 700; }
    .footer { margin-top: 9px; padding-top: 7px; border-top: 1px dashed #000; text-align: center; font-size: 10px; font-weight: 700; }
  </style>
</head>
<body>
  <div class="ticket">
    <div class="brand">
      ${w(a.show_logo)&&u?`<img src="${u}" alt="Logo">`:``}
      ${w(a.show_company_name)?`<div class="brand-name">${t.nombre||o.company}</div>`:``}
      ${$}
    </div>

    <div class="document">
      <div class="document-label">${c?o.quote.toUpperCase():H(e.tipo_factura)}</div>
      ${G?`<div class="document-label receipt-name">${G}</div>`:``}
      <div class="document-number">#${e.no_factura||``}</div>
      ${e.fecha_emision?`<div class="meta-row"><span class="meta-label">${o.date}</span><span class="meta-value">${e.fecha_emision||``} ${e.hora||``}</span></div>`:``}
      ${!c&&(e.ncf||e.comprobante)?`<div class="meta-row"><span class="meta-label">${y.fiscalDocumentLabel}</span><span class="meta-value">${e.ncf||e.comprobante}</span></div>`:``}
    </div>

    ${w(a.show_cliente)?`<div class="customer">
      <div class="section-title">${o.customer}</div>
      <div class="meta-row"><span class="meta-label">${o.customer}</span><span class="meta-value">${U(e.nombre_cliente)}</span></div>
      ${J?`<div class="meta-row"><span class="meta-label">${y.customerIdLabel}</span><span class="meta-value">${J}</span></div>`:``}
      ${e.telefono_cliente?`<div class="meta-row"><span class="meta-label">${o.phone}</span><span class="meta-value">${e.telefono_cliente}</span></div>`:``}
      ${e.direccion_cliente?`<div class="meta-row"><span class="meta-label">${o.address}</span><span class="meta-value">${e.direccion_cliente}</span></div>`:``}
      ${e.vendedor?`<div class="meta-row"><span class="meta-label">${o.seller}</span><span class="meta-value">${e.vendedor}</span></div>`:``}
      ${e.cajero?`<div class="meta-row"><span class="meta-label">${o.cashier}</span><span class="meta-value">${e.cajero}</span></div>`:``}
      ${V?`<div class="meta-row"><span class="meta-label">DELIVERY</span><span class="meta-value">${V}</span></div>`:``}
      ${!c&&e.metodo_pago?`<div class="meta-row"><span class="meta-label">${o.paymentMethod}</span><span class="meta-value">${h(e)}</span></div>`:``}
    </div>`:``}

    <div class="rule"></div>

    ${w(a.show_items)?`<table class="items">
      <thead>
        <tr>
          <th>${o.quantity}</th>
          <th>${o.package}</th>
          <th>${o.price}</th>
          ${M?`<th class="precio">${o.discount}</th>`:``}
          <th class="precio">${o.total}</th>
        </tr>
      </thead>
      <tbody>${N}</tbody>
    </table>`:``}

    ${w(a.show_totals)?`<div class="summary">
      <div class="summary-title">${O}</div>
      <div class="summary-row"><span>${o.subtotal}</span><strong>${l}${D(S)}</strong></div>
      ${m>0?`<div class="summary-row"><span>${o.discount}</span><strong>${l}${D(m)}</strong></div>`:``}
      ${g>0?`<div class="summary-row"><span>${y.shortName}</span><strong>${l}${D(g)}</strong></div>`:``}
      ${b&&C?`<div class="payment-group"><div class="payment-caption">${A}</div>${C}</div>`:``}
      <div class="summary-row grand-total"><span>${o.total}</span><strong>${l}${D(_)}</strong></div>
      ${!c&&I>0?`<div class="summary-row"><span>${o.paidWith}</span><strong>${l}${D(I)}</strong></div>`:``}
      ${!c&&I>0?`<div class="summary-row"><span>${o.change}</span><strong>${l}${D(R)}</strong></div>`:``}
    </div>`:``}

    ${w(a.show_nota)&&e.nota?`<div class="note"><strong>${o.observation}:</strong><br>${String(e.nota).replace(/\n/g,`<br>`)}</div>`:``}

    ${w(a.show_barcode)&&Y?`<div class="code-block barcode">${Y}</div>`:``}

    ${!c&&(w(a.show_qr)||X)&&r?`<div class="code-block qr-code">
      <img src="${r}" alt="Codigo QR">
      ${i?.securityCode?`<div class="security-code">${o.securityCode}: ${i.securityCode}</div>`:``}
    </div>
    `:``}

    ${!c&&w(a.show_footer)?`<div class="footer">${a.footer_text===x.footer_text?o.thanks:a.footer_text||``}</div>`:``}
  </div>
</body>
</html>`}async function X(e){let t=S(e),n={...x};try{let e=await window.db.getAll(`impresoras_config`);e.success&&e.data?.length>0&&(n={...n,...e.data[0]},b.value=n.printer_name||``)}catch{}let r=localStorage.getItem(`etiquetas_printer`);r&&!n.printer_name&&(b.value=r);let i=T(t.productos,[]),a={};try{let e=await window.db.getAll(`empresa`);e.success&&e.data?.length>0&&(a=C(e.data,t))}catch{}t.empresa&&typeof t.empresa==`object`&&(a=t.empresa);let o=await N(a?.logoprinter||a?.logo);o&&(a={...a,logo:o,logoprinter:o});let s=G(t)?F({}):await I(t),c=s.documentStampUrl||`https://tmposrd.com/factura/${t.no_factura}`,l=G(t)?``:await R(c),u=J({factura:t,empresa:a,productos:Array.isArray(i)?i:[],qrCodeData:l,alanubeData:s,ticketConfig:n});try{let e=E(n.paper_width,80)===58?58:72,t=await window.electron.invoke(`print:ticket`,u,b.value||void 0,{width:e});t.success?g.add({severity:`success`,summary:`Imprimiendo...`,life:2e3}):g.add({severity:`error`,summary:`Error`,detail:t.error,life:3e3})}catch(e){g.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}}return o({printTicket:X}),(e,n)=>(r(),i(`div`,Y,[a(t(u))]))}});export{C,b as S,j as _,W as a,x as b,J as c,V as d,w as f,A as g,D as h,G as i,q as l,N as m,R as n,K as o,S as p,B as r,P as s,X as t,U as u,k as v,M as x,T as y};