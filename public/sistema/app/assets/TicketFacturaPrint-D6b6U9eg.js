import{r as e}from"./rolldown-runtime-hePW80VL.js";import{A as t,v as n}from"./reactivity.esm-bundler-C6Po7tbM.js";import{P as r,d as i,g as a,v as o}from"./runtime-core.esm-bundler-EoPqhpgY.js";import{f as s,o as c,u as l}from"./tmCloudClient-BXA3SgwJ.js";import{n as u,t as d}from"./invoiceCreditPayment-BiGdfNIQ.js";import{F as f,N as p,V as m,wt as h}from"./index-DX_qrafp.js";import{t as g}from"./browser-Cpc4qo6y.js";import{i as _}from"./ticketLogoSettings-DWcskBne.js";import{c as ee,d as te,i as ne,l as re,n as v,o as y,r as b,u as x}from"./documentDataService-DBELFZlT.js";import{t as S}from"./ticketWebsite-D-W7CZgP.js";import{t as C}from"./JsBarcode-JyLvbIYG.js";import{s as ie}from"./electronicInvoiceTypes-Dbkp0D11.js";function w(e){if(typeof e!=`string`)return e;try{return JSON.parse(e)}catch{return null}}function T(e){let t=w(e?.otro)?.pharmacy?.allocations,n=w(e?.productos||e?.items);return Array.isArray(t)?t.flatMap(e=>{if(!e||!Number.isInteger(Number(e.productId))||Number(e.productId)<=0||!Number.isFinite(Number(e.quantity))||Number(e.quantity)<=0||!String(e.lotNumber||``).trim()||!/^\d{4}-\d{2}-\d{2}$/.test(String(e.expiresAt||``)))return[];let t=Array.isArray(n)?n.find(t=>Number(t?.accesorio_id)===Number(e.productId)):void 0;return[{productId:Number(e.productId),productName:String(t?.nombre||t?.descripcion||`#${e.productId}`),lotNumber:String(e.lotNumber),expiresAt:String(e.expiresAt),quantity:Number(e.quantity)}]}):[]}var E=e=>String(e).replace(/[&<>"']/g,e=>({"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#39;`})[e]);function D(e,t=`es`){let n=T(e);if(!n.length)return``;let r=t===`en`;return`<section class="pharmacy-lots" style="margin-top:10px;padding:7px 0;border-top:1px solid #777;overflow-wrap:anywhere;font-size:9px"><strong>${r?`DISPENSED LOTS`:`LOTES DISPENSADOS`}</strong>${n.map(e=>`<div style="margin-top:5px;break-inside:avoid"><strong>${E(e.productName)}</strong><br>${r?`Lot`:`Lote`}: ${E(e.lotNumber)} · ${r?`Qty`:`Cant.`}: ${E(e.quantity)}<br>${r?`Expires`:`Vence`}: ${E(e.expiresAt)}</div>`).join(``)}</section>`}var O=e(g()),k=e(C());function A(e){try{let t=(typeof e?.otro==`string`?JSON.parse(e.otro):e?.otro)?.hn_fiscal;return t?.version===1&&t.country===`HN`&&t.number?t:null}catch{return null}}var j=e=>String(e??``).replace(/[&<>"']/g,e=>({"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#39;`})[e]),M=e=>`L ${Number(e).toFixed(2)}`;function N(e){let t=`CERO.UNO.DOS.TRES.CUATRO.CINCO.SEIS.SIETE.OCHO.NUEVE.DIEZ.ONCE.DOCE.TRECE.CATORCE.QUINCE.DIECISÉIS.DIECISIETE.DIECIOCHO.DIECINUEVE.VEINTE.VEINTIUNO.VEINTIDÓS.VEINTITRÉS.VEINTICUATRO.VEINTICINCO.VEINTISÉIS.VEINTISIETE.VEINTIOCHO.VEINTINUEVE`.split(`.`);return e<30?t[e]:e<100?[``,``,``,`TREINTA`,`CUARENTA`,`CINCUENTA`,`SESENTA`,`SETENTA`,`OCHENTA`,`NOVENTA`][Math.floor(e/10)]+(e%10?` Y ${t[e%10]}`:``):e===100?`CIEN`:e<1e3?[``,`CIENTO`,`DOSCIENTOS`,`TRESCIENTOS`,`CUATROCIENTOS`,`QUINIENTOS`,`SEISCIENTOS`,`SETECIENTOS`,`OCHOCIENTOS`,`NOVECIENTOS`][Math.floor(e/100)]+(e%100?` ${N(e%100)}`:``):e<1e6?(e<2e3?`MIL`:`${N(Math.floor(e/1e3)).replace(/UNO$/,`UN`)} MIL`)+(e%1e3?` ${N(e%1e3)}`:``):(e<2e6?`UN MILLÓN`:`${N(Math.floor(e/1e6)).replace(/UNO$/,`UN`)} MILLONES`)+(e%1e6?` ${N(e%1e6)}`:``)}function P(e){if(!Number.isFinite(e)||e<0||e>=0xe8d4a51000)throw Error(`Importe fuera del límite de impresión.`);let t=Math.round(e*100);return`${N(Math.floor(t/100)).replace(/UNO$/,`UN`)} LEMPIRAS CON ${String(t%100).padStart(2,`0`)}/100`}function F(e){let t=A(e);return t?`<section class="hn-fiscal" style="border:1px solid #333;padding:8px;margin:8px 0;font-size:10px;line-height:1.5;break-inside:avoid;overflow-wrap:anywhere">
    <strong>FACTURA · HONDURAS · ${j(t.number)}</strong><br>
    ${j(t.legalName)} · RTN: ${j(t.taxpayerId)}<br>${j(t.address)}<br>
    Fecha de emisión: ${j(t.issuedOn)}<br>Cliente: ${j(t.customerName||e.nombre_cliente||e.cliente||`CONSUMIDOR FINAL`)}<br>RTN / DNI cliente: ${j(t.customerId||`CONSUMIDOR FINAL`)}<br>
    CAI: ${j(t.cai)}<br>Rango autorizado: ${j(t.rangeStart)} al ${j(t.rangeEnd)}<br>
    Fecha límite de emisión: ${j(t.expiresOn)}<br>
    Importe exento: ${M(t.rate===0?t.base:0)}<br>Importe exonerado: L 0.00<br>
    Importe gravado 15%: ${M(t.rate===15?t.base:0)} · ISV 15%: ${M(t.rate===15?t.tax:0)}<br>
    Importe gravado 18%: ${M(t.rate===18?t.base:0)} · ISV 18%: ${M(t.rate===18?t.tax:0)}<br>
    <strong>Total: ${M(t.total)}</strong><br>${j(P(t.total))}<br>
    Original: cliente · Copia: emisor<br>LA FACTURA ES BENEFICIO DE TODOS. EXÍJALA.
  </section>`:``}var I={style:{display:`none`}},L=o({__name:`TicketFacturaPrint`,setup(e,{expose:o}){function g(e){let t=v(),n=P(e?.otro,{}),r=[],i=Number(e.efectivo||0);i>0&&r.push({tipo:`efectivo`,etiqueta:t.cash,monto:i});let a=P(e.tarjeta_mixta,e.tarjeta_mixta),o=a&&typeof a==`object`?a:n?.tarjeta_mixta||{},s=Number(e.tarjeta||o?.total||Number(o?.monto_base||0)+Number(o?.monto_comision||0));s>0&&r.push({tipo:`tarjeta`,etiqueta:t.card,monto:s});let c=P(e.transferencias_mixtas,e.transferencias_mixtas),l=P(n?.transferencias_mixtas,n?.transferencias_mixtas),u=Array.isArray(c)?c:Array.isArray(l)?l:Number(e.transferencia)>0?[{monto:e.transferencia,banco_nombre:e.banco_nombre||n?.banco_nombre||``}]:[];for(let e of u){let n=Number(e?.monto||0);if(n<=0)continue;let i=e?.banco_nombre?` (${e.banco_nombre})`:``;r.push({tipo:`transferencia`,etiqueta:`${t.transfer}${i}`,monto:n})}if(Number(e.cheque)>0&&r.push({tipo:`cheque`,etiqueta:t.check,monto:Number(e.cheque)}),i<=0){let n=r.reduce((e,t)=>e+t.monto,0),i=Math.round((d(e)-n)*100)/100;i>.009&&r.unshift({tipo:`efectivo`,etiqueta:t.cash,monto:i})}return r}function C(e){let t=v();return String(e.metodo_pago||``).toLowerCase()===`mixto`?t.mixed:x(e.metodo_pago)}let w=h();p();let T=n(``),E={copies:1,printer_name:``,paper_width:80,show_logo:1,show_company_name:1,show_legal:1,show_phone:1,show_address:1,show_email:1,show_cliente:1,show_items:1,show_totals:1,show_barcode:1,show_footer:1,show_qr:0,show_nota:1,footer_text:`Gracias por su compra`};function j(e){let t={...e||{}},n=Array.isArray(t.items)?t.items:P(t.productos,[]);return{...t,fecha_emision:t.fecha_emision||t.fecha||``,nombre_cliente:t.nombre_cliente||t.cliente||`CONSUMIDOR FINAL`,telefono_cliente:t.telefono_cliente||t.telefono||``,comprobante:t.comprobante||t.tipo_comprobante||``,productos:n.map(e=>({...e,precio_venta:e.precio_venta??e.precio,precio_final:e.precio_final??e.precio,total:e.total??Number(e.precio||e.precio_venta||0)*Number(e.cantidad||1)}))}}function M(e,t){return t?.empresa&&typeof t.empresa==`object`?t.empresa:y(t,e)}function N(e){return e===!0||e===1||e===`1`}function P(e,t){if(e==null)return t;if(typeof e==`string`)try{return JSON.parse(e)}catch{return t}return e}function L(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function R(e){return L(e).toFixed(2)}function z(e){return L(e.precio_venta??e.precio_unitario??e.precio??e.price)}function B(e){let t=L(e.cantidad??e.quantity,1),n=L(e.precio_final??e.precio_venta??e.precio_unitario??e.precio??e.price);return L(e.total,n*t)}function ae(e){return String(e?.logoprinter||e?.logo||``).trim()}function V(e){let t=new Uint8Array(e),n=``,r=32768;for(let e=0;e<t.length;e+=r)n+=String.fromCharCode(...t.subarray(e,e+r));return btoa(n)}async function H(e){let t=String(e||``).trim();if(!t||/^data:/i.test(t)||/^(https?:\/\/|file:|blob:|\/)/i.test(t))return t;try{await c();let e=s(t),n=l()?.key||``;if(!e)return t;let r=await fetch(e,{headers:n?{Authorization:`Bearer ${n}`}:{}});return r.ok?`data:${r.headers.get(`content-type`)||`image/png`};base64,${V(await r.arrayBuffer())}`:t}catch{return t}}function U(e,t={}){let n=P(e?.otro,{}),r=n?.alanube_response||e?.alanube_response||{};return{documentStampUrl:t?.document_stamp_url||e?.document_stamp_url||e?.documentStampUrl||n?.documentStampUrl||n?.document_stamp_url||r?.documentStampUrl||r?.document_stamp_url||``,securityCode:t?.security_code||e?.codigo_seguridad||e?.securityCode||n?.securityCode||n?.security_code||r?.securityCode||r?.security_code||``}}async function W(e){if(e?.id)try{let t=await window.db.getWhere(`facturas_ecf`,`factura_id = ?`,[e.id]),n=t?.success&&Array.isArray(t.data)?t.data[0]:null;if(n)return U(e,n)}catch{}return U(e)}function oe(e){return/^E\d{2}/i.test(String(e?.ncf||e?.comprobante||e?.tipo_comprobante||``))}async function G(e){try{return await O.toDataURL(e,{width:200,margin:1})}catch{return``}}function se(e){if(!e)return``;try{let t=document.createElementNS(`http://www.w3.org/2000/svg`,`svg`);return(0,k.default)(t,e,{format:`CODE128`,width:2,height:50,displayValue:!0,fontSize:12,margin:2}),new XMLSerializer().serializeToString(t).replace(/width="[^"]*"/,`width="180"`).replace(/height="[^"]*"/,`height="55"`)}catch{return``}}function ce(e,t,n){return e.map(e=>{let r=L(e.cantidad??e.quantity,1),i=z(e),a=B(e),o=L(e.descuento),s=e.nombre||e.descripcion||e.producto||``,c=[e.imeis,e.imei].flatMap(e=>Array.isArray(e)?e:e?String(e).split(`,`):[]).map(e=>String(e||``).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t),l=[e.seriales,e.serial].flatMap(e=>Array.isArray(e)?e:e?String(e).split(`,`):[]).map(e=>String(e||``).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t),u=c.length?`<br><span style="font-size:8px;color:#555;">IMEI: ${c.join(`, `)}</span>`:``,d=l.length?`<br><span style="font-size:8px;color:#555;">Serial: ${l.join(`, `)}</span>`:``;return`
      <tr class="item-name">
        <td colspan="${n?5:4}" style="overflow-wrap:break-word;white-space:normal;word-break:break-word;">
          ${s}${u}${d}
        </td>
      </tr>
      <tr class="item-values">
        <td>${r} x</td>
        <td>${e.empaque||``}</td>
        <td>${t}${R(i)}</td>
        ${n?`<td class="precio">${t}${R(o)}</td>`:``}
        <td class="precio">
          <b>${t}${R(a)}</b>
        </td>
      </tr>
    `}).join(``)}function K({factura:e,empresa:t,productos:n,qrCodeData:r,websiteQrData:i,alanubeData:a,ticketConfig:o}){let s=v(),c=A(e),l=p(c?`HN`:void 0);c&&(t={...t,nombre:c.legalName,legal:c.taxpayerId,rnc:c.taxpayerId,direccion:c.address}),c&&(o={...o,show_items:1,show_totals:1});let m=ne({factura:e,empresa:t,items:n}),h=m.isQuote;e={...e,no_factura:ee(e)},n=m.items;let y=c?`HNL`:f(),b=ae(t),x=L(o.paper_width,80)===58?58:72,S=m.totals.discount,w=m.totals.tax,T=m.totals.total,O=Number((e.salon_deposit||P(e.otro,{})?.salon_deposit)?.amount||0),k=!h&&Number.isFinite(O)&&O>0&&O<=T,j=m.totals.subtotal,M=!h&&String(e.metodo_pago||``).toUpperCase()===`MIXTO`,I=c?c.base+S:M?T:j,z=(M?g(e):[]).map(e=>`
    <div class="payment-row">
      <span>${e.etiqueta}</span>
      <strong>${y}${R(e.monto)}</strong>
    </div>
  `).join(``),B=h?s.quote.toUpperCase():s.language===`en`?`PAYMENT SUMMARY`:`RESUMEN DE PAGO`,V=s.language===`en`?`PAYMENT DISTRIBUTION`:`DISTRIBUCION DEL PAGO`,H=S>0||n.some(e=>L(e.descuento)>0),U=ce(n,y,H),W=P(e.otro,[]),G=W?.cobro_caja?{pagocon:W.cobro_caja.efectivo_recibido,sucambio:W.cobro_caja.cambio}:Array.isArray(W)?W[0]:W||{},K=L(G?.pagocon),q=L(G?.sucambio),J=G?.delivery||``,Y=h?``:ie(e).toUpperCase(),X=c?c.customerId:e.rnc_cliente||e.cedula_cliente||e.rnc||``,Z=se(e.no_factura||e.id||``),le=!h&&!!(r&&(a?.documentStampUrl||oe(e))),Q=[];N(o.show_address)&&t.direccion&&Q.push(t.direccion);let $=[];N(o.show_phone)&&t.telefono&&$.push(t.telefono),N(o.show_email)&&t.email&&$.push(t.email),$.length&&Q.push($.join(` / `)),N(o.show_legal)&&(t.legal||t.rnc)&&Q.push(`${l.businessIdLabel}: ${t.legal||t.rnc}`);let ue=Q.length?`<div class="brand-info">${Q.join(`<br>`)}</div>`:``;return`<!DOCTYPE html>
<html lang="${s.language}">
<head>
  <meta charset="utf-8">
  <title>${h?s.quote:s.invoice} - ${e.no_factura||``}</title>
  <style>
    * { box-sizing: border-box; font-family: Arial, Helvetica, sans-serif; }
    @page { size: ${x}mm auto; margin: 0; }
    html { width: ${x}mm; margin: 0; padding: 0; background: #fff; }
    body { width: ${x}mm; margin: 0; padding: 0 3mm; background: #fff; color: #000; font-size: 10px; overflow: hidden; }
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
    .qr-code img { display: block; width: ${x===58?112:130}px; height: ${x===58?112:130}px; margin: 0 auto; }
    .security-code { margin-top: 3px; font-size: 8px; font-weight: 700; }
    .footer { margin-top: 9px; padding-top: 7px; border-top: 1px dashed #000; text-align: center; font-size: 10px; font-weight: 700; }
  </style>
</head>
<body>
  <div class="ticket">
    <div class="brand">
      ${N(o.show_logo)&&b?`<img src="${b}" alt="Logo" style="${_(o.printer_name||localStorage.getItem(`etiquetas_printer`)||``)}">`:``}
      ${N(o.show_company_name)?`<div class="brand-name">${t.nombre||s.company}</div>`:``}
      ${ue}
    </div>

    <div class="document">
      <div class="document-label">${h?s.quote.toUpperCase():te(e.tipo_factura)}</div>
      ${Y?`<div class="document-label receipt-name">${Y}</div>`:``}
      <div class="document-number">#${e.no_factura||``}</div>
      ${e.fecha_emision?`<div class="meta-row"><span class="meta-label">${s.date}</span><span class="meta-value">${e.fecha_emision||``} ${e.hora||``}</span></div>`:``}
      ${F(e)}
      ${!h&&(e.ncf||e.comprobante)?`<div class="meta-row"><span class="meta-label">${c?`Número de factura`:l.fiscalDocumentLabel}</span><span class="meta-value">${e.ncf||e.comprobante}</span></div>`:``}
    </div>

    ${N(o.show_cliente)?`<div class="customer">
      <div class="section-title">${s.customer}</div>
      <div class="meta-row"><span class="meta-label">${s.customer}</span><span class="meta-value">${re(e.nombre_cliente)}</span></div>
      ${X?`<div class="meta-row"><span class="meta-label">${l.customerIdLabel}</span><span class="meta-value">${X}</span></div>`:``}
      ${e.telefono_cliente?`<div class="meta-row"><span class="meta-label">${s.phone}</span><span class="meta-value">${e.telefono_cliente}</span></div>`:``}
      ${e.direccion_cliente?`<div class="meta-row"><span class="meta-label">${s.address}</span><span class="meta-value">${e.direccion_cliente}</span></div>`:``}
      ${e.vendedor?`<div class="meta-row"><span class="meta-label">${s.seller}</span><span class="meta-value">${e.vendedor}</span></div>`:``}
      ${e.cajero?`<div class="meta-row"><span class="meta-label">${s.cashier}</span><span class="meta-value">${e.cajero}</span></div>`:``}
      ${J?`<div class="meta-row"><span class="meta-label">DELIVERY</span><span class="meta-value">${J}</span></div>`:``}
      ${!h&&e.metodo_pago?`<div class="meta-row"><span class="meta-label">${s.paymentMethod}</span><span class="meta-value">${C(e)}</span></div>`:``}
    </div>`:``}

    <div class="rule"></div>

    ${N(o.show_items)?`<table class="items">
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

    ${N(o.show_totals)?`<div class="summary">
      <div class="summary-title">${B}</div>
      <div class="summary-row"><span>${s.subtotal}</span><strong>${y}${R(I)}</strong></div>
      ${S>0?`<div class="summary-row"><span>${s.discount}</span><strong>${y}${R(S)}</strong></div>`:``}
      ${w>0?`<div class="summary-row"><span>${l.shortName}</span><strong>${y}${R(w)}</strong></div>`:``}
      ${M&&z?`<div class="payment-group"><div class="payment-caption">${V}</div>${z}</div>`:``}
      <div class="summary-row grand-total"><span>${s.total}</span><strong>${y}${R(T)}</strong></div>
      ${!h&&K>0?`<div class="summary-row"><span>${s.paidWith}</span><strong>${y}${R(K)}</strong></div>`:``}
      ${!h&&K>0?`<div class="summary-row"><span>${s.change}</span><strong>${y}${R(q)}</strong></div>`:``}
    </div>`:``}

    ${h?``:D(e,s.language)}
    ${!h&&u(e)>0?`<div class="summary"><div class="summary-row"><span>Saldo a favor aplicado (NC)</span><strong>${y}${R(u(e))}</strong></div><div class="summary-row"><span>Importe por otros medios</span><strong>${y}${R(d(e))}</strong></div></div>`:``}
${k?`<div class="summary salon-deposit"><div class="summary-row"><span>Anticipo aplicado</span><strong>${y}${R(O)}</strong></div><div class="summary-row"><span>Saldo cobrado</span><strong>${y}${R(d(e))}</strong></div></div>`:``}
    ${N(o.show_nota)&&e.nota?`<div class="note"><strong>${s.observation}:</strong><br>${String(e.nota).replace(/\n/g,`<br>`)}</div>`:``}

    ${N(o.show_barcode)&&Z?`<div class="code-block barcode">${Z}</div>`:``}

    ${!h&&le&&r?`<div class="code-block qr-code">
      <img src="${r}" alt="Codigo QR">
      ${a?.securityCode?`<div class="security-code">${s.securityCode}: ${a.securityCode}</div>`:``}
    </div>
    `:``}

    ${!h&&N(o.show_qr)&&i?`<div class="code-block qr-code"><div class="section-title">Visita nuestra página web</div><img src="${i}" alt="QR de la página web"></div>`:``}
    ${!h&&N(o.show_footer)?`<div class="footer">${o.footer_text===E.footer_text?s.thanks:o.footer_text||``}</div>`:``}
  </div>
</body>
</html>`}function q(e){let t=Number(e);return Number.isInteger(t)&&t>=1&&t<=10?t:1}async function J(){let e=await window.db.getAll(`impresoras_config`);if(!e.success)throw Error(e.error||`No se pudo consultar la configuración de impresoras.`);return q(e.data?.[0]?.copies)}async function Y(e,t){let n=j(e),r={...E};try{let e=await window.db.getAll(`impresoras_config`);e.success&&e.data?.length>0&&(r={...r,...e.data[0]},T.value=r.printer_name||``)}catch{}let i=q(t??r.copies),a=localStorage.getItem(`etiquetas_printer`);a&&!r.printer_name&&(T.value=a);let o=P(n.productos,[]),s={};try{let e=await window.db.getAll(`empresa`);e.success&&e.data?.length>0&&(s=M(e.data,n))}catch{}n.empresa&&typeof n.empresa==`object`&&(s=n.empresa);let c=await H(s?.logoprinter||s?.logo);c&&(s={...s,logo:c,logoprinter:c});let l=b(n)?U({}):await W(n),u=l.documentStampUrl||`https://tmposrd.com/factura/${n.no_factura}`,d=b(n)?``:await G(u),f=!b(n)&&N(r.show_qr)?await S():``,p=f?await G(f):``,m=K({factura:n,empresa:s,productos:Array.isArray(o)?o:[],qrCodeData:d,websiteQrData:p,alanubeData:l,ticketConfig:r});try{let e=L(r.paper_width,80)===58?58:72;for(let t=1;t<=i;t++){let n=await window.electron.invoke(`print:ticket`,m,T.value||void 0,{width:e});if(!n.success)return w.add({severity:`error`,summary:`Error de impresión`,detail:`Copia ${t} de ${i}: ${n.error||`No se pudo imprimir`}. Copias enviadas: ${t-1}.`,life:6e3}),!1}return w.add({severity:`success`,summary:`Imprimiendo...`,life:2e3}),!0}catch(e){return w.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3}),!1}}return o({printTicket:Y,getDefaultCopies:J}),(e,n)=>(r(),i(`div`,I,[a(t(m))]))}});export{D as i,F as n,A as r,L as t};