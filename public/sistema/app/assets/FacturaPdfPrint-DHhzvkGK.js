import{r as e}from"./rolldown-runtime-hePW80VL.js";import{A as t,v as n}from"./reactivity.esm-bundler-C6Po7tbM.js";import{J as r,P as i,c as a,d as o,g as s,r as c,u as l,v as u}from"./runtime-core.esm-bundler-EoPqhpgY.js";import{f as d,l as f,o as p,t as m,u as h,v as g}from"./tmCloudClient-BXA3SgwJ.js";import{C as _,D as v,j as y,vt as b,x,z as S}from"./index-j_HC5XiA.js";import{t as C}from"./browser-Cpc4qo6y.js";import{a as w,i as T,n as ee,o as E,r as D}from"./TicketFacturaPrint-CsndFJn3.js";import{n as O}from"./funciones-BvWR8JyV.js";var k=e(C());function A(e){return e?.data?.signature||e?.data?.request||e?.data||e}function j(e){return JSON.parse(JSON.stringify(e??{}))}function M(e){let t=A(e),n=String(t?.url||``).trim();if(!/^https:\/\//i.test(n))throw Error(`El servidor no devolvió un enlace HTTPS de firma válido`);return{uid:String(t?.uid||``),url:n,expiresAt:t?.expires_at||t?.expiresAt||null,status:t?.status===`signed`?`signed`:`pending`}}function N(e){let t=A(e),n=[`none`,`pending`,`signed`,`stale`,`expired`,`unavailable`].includes(t?.status)?t.status:`none`,r=String(t?.signature_data_uri||t?.signatureDataUri||``).trim();return{status:n,signerName:String(t?.signer_name||t?.signerName||``).trim(),signedAt:t?.signed_at||t?.signedAt||null,imageDataUri:n===`signed`&&/^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(r)?r:null}}function P(e,t){return[`Hola ${e?.nombre_cliente||`cliente`},`,`Por favor revisa y firma la factura ${e?.no_factura||``} por RD$${Number(e?.total||0).toFixed(2)}.`,`Abre este enlace seguro para firmar:`,t,``,`La factura mostrará tu firma después de confirmarla.`].join(`
`)}async function F(e){let t=String(e?.uid||``).trim();if(!t)throw Error(`La factura aún no tiene UID de sincronización`);if(window.__isServerSystem||window.__isElectron){let n=await window.electron.invoke(`facturas:crearEnlaceFirma`,{record_uid:t,invoice:j(e)});if(n?.success===!1)throw Error(n.error||`No se pudo crear el enlace de firma`);return M(n)}await p(!0);let n=f();if(!n?.url||!n.key)throw Error(`TM Cloud no está configurado para emitir solicitudes de firma`);let r=await fetch(`${n.url}/invoices/${encodeURIComponent(t)}/signature-request`,{method:`POST`,headers:m(n.key,!0),body:JSON.stringify({expires_at:new Date(Date.now()+6048e5).toISOString().replace(`T`,` `).slice(0,19)})});if(!r.ok)throw Error(await g(r));return M(await r.json())}async function I(e){let t=String(e?.uid||``).trim();if(!t)return{status:`none`,signerName:``,signedAt:null,imageDataUri:null};if(window.__isServerSystem||window.__isElectron){let n=await window.electron.invoke(`facturas:obtenerFirma`,{record_uid:t,invoice:j(e)});if(n?.success===!1)throw Error(n.error||`No se pudo consultar la firma`);return N(n)}await p(!0);let n=f();if(!n?.url||!n.key)throw Error(`TM Cloud no está configurado para consultar firmas`);let r=await fetch(`${n.url}/invoices/${encodeURIComponent(t)}/signature`,{headers:m(n.key)});if(!r.ok)throw Error(await g(r));return N(await r.json())}var L={style:{display:`none`}},R={class:`flex flex-col h-full gap-3`},z=[`src`],B=u({__name:`FacturaPdfPrint`,setup(e,{expose:u}){function f(e){let t=D(),n=B(e?.otro,{}),r=e.banco_nombre||n?.banco_nombre||``,i=[],a=Number(e.efectivo||0);a>0&&i.push({tipo:`efectivo`,etiqueta:t.cash,monto:a});let o=B(e.tarjeta_mixta,e.tarjeta_mixta),s=o&&typeof o==`object`?o:n?.tarjeta_mixta||{},c=Number(e.tarjeta||s?.total||Number(s?.monto_base||0)+Number(s?.monto_comision||0));c>0&&i.push({tipo:`tarjeta`,etiqueta:t.card,monto:c});let l=B(e.transferencias_mixtas,e.transferencias_mixtas),u=B(n?.transferencias_mixtas,n?.transferencias_mixtas),d=Array.isArray(l)?l:Array.isArray(u)?u:Number(e.transferencia)>0?[{monto:e.transferencia,banco_nombre:r}]:[];for(let e of d){let n=Number(e?.monto||0);if(n<=0)continue;let r=e?.banco_nombre?` (${e.banco_nombre})`:``;i.push({tipo:`transferencia`,etiqueta:`${t.transfer}${r}`,monto:n})}if(Number(e.cheque)>0&&i.push({tipo:`cheque`,etiqueta:t.check,monto:Number(e.cheque)}),a<=0){let n=Number(e.total||0),r=i.reduce((e,t)=>e+t.monto,0),a=Math.round((n-r)*100)/100;a>.009&&i.unshift({tipo:`efectivo`,etiqueta:t.cash,monto:a})}return i}function m(e){let t=D(),n=String(e.metodo_pago||``).toUpperCase(),r=B(e?.otro,{}),i=e.banco_nombre||r?.banco_nombre||``;if(n!==`MIXTO`){let t=E(e.metodo_pago);return i?`${t} - ${i}`:t}return t.mixed}function g(e){let t=D(),n={efectivo:t.cash,tarjeta:t.card,transferencia:t.transfer,cheque:t.check},r=[...new Set(f(e).map(e=>n[e.tipo].toUpperCase()))];return r.length?`${r.join(` Y `)} - ${t.mixed}`:t.mixed}function C(e){return String(e.tipo_factura||``).toLowerCase()===`cotizacion`||String(e.estado_factura||``).toLowerCase()===`cotizacion`}let A=b(),j=y(),M=n(!1),N=n(!1),P=n(``),F=n(``);function B(e,t){if(e==null)return t;if(typeof e==`string`)try{return JSON.parse(e)}catch{return t}return e}function V(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function H(e,t={}){let n=B(e?.otro,{}),r=n?.alanube_response||e?.alanube_response||{};return{documentStampUrl:t?.document_stamp_url||e?.document_stamp_url||e?.documentStampUrl||n?.documentStampUrl||n?.document_stamp_url||r?.documentStampUrl||r?.document_stamp_url||``,securityCode:t?.security_code||e?.codigo_seguridad||e?.securityCode||n?.securityCode||n?.security_code||r?.securityCode||r?.security_code||``,legalStatus:t?.legal_status||e?.alanube_legal_status||r?.legalStatus||n?.legalStatus||``,status:t?.status||e?.alanube_status||r?.status||n?.status||``}}async function U(e){if(e?.id)try{let t=await window.db.getWhere(`facturas_ecf`,`factura_id = ?`,[e.id]),n=t?.success&&Array.isArray(t.data)?t.data[0]:null;if(n)return H(e,n)}catch{}return H(e)}function W(e){return v(e)}function G(e,t=``){let n=String(t||``).trim().match(/^(\d{1,2}:\d{2})/),r=String(e||``).trim(),i=r.match(/(?:T|\s)(\d{1,2}:\d{2})/),a=n?n[1].padStart(5,`0`):i?i[1].padStart(5,`0`):``,o=r.match(/^(\d{4})-(\d{2})-(\d{2})/),s=r.match(/^(\d{2})\/(\d{2})\/(\d{4})/);if(o)return`${o[3]}/${o[2]}/${o[1]}${a?` ${a}`:``}`;if(s)return`${s[1]}/${s[2]}/${s[3]}${a?` ${a}`:``}`;let c=e instanceof Date?e:new Date(e);if(Number.isNaN(c.getTime()))return a;let l=`${String(c.getDate()).padStart(2,`0`)}/${String(c.getMonth()+1).padStart(2,`0`)}/${c.getFullYear()}`,u=`${String(c.getHours()).padStart(2,`0`)}:${String(c.getMinutes()).padStart(2,`0`)}`;return`${l} ${a||u}`}function K(e){return[e?.imei,e?.lista_imei,e?.imeis,e?.serial,e?.seriales].flatMap(e=>Array.isArray(e)?e:typeof e==`string`?e.split(`,`):e?[e]:[]).map(e=>typeof e==`object`?String(e.imei||e.serial||``).trim():String(e).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t)}function q(e){return String(e?.codigo||e?.codigo_barra||e?.cod_producto||e?.sku||e?.referencia||e?.barcode||e?.imei||e?.serial||e?.accesorio_id||e?.telefono_id||e?.imei_id||e?.serial_id||``).trim()}function J(e,t=``){let n=String(e??``).trim();return n?/^(data:|https?:\/\/|file:|blob:)/i.test(n)?n:n.startsWith(`/`)?t?`${t.replace(/\/$/,``)}${n}`:n:t?`${t.replace(/\/$/,``)}/${n.replace(/^\.\//,``)}`:n:``}function Y(e){let t=new Uint8Array(e),n=``,r=32768;for(let e=0;e<t.length;e+=r)n+=String.fromCharCode(...t.subarray(e,e+r));return btoa(n)}async function te(e,t=``){let n=String(e??``).trim();if(!n)return``;let r=J(n,t);if(/^data:/i.test(r))return r;if(!/^(https?:\/\/|file:|blob:|\/)/i.test(n))try{await p();let e=d(n),t=h()?.key||``;if(e){let n=await fetch(e,{headers:t?{Authorization:`Bearer ${t}`}:{}});if(n.ok)return`data:${n.headers.get(`content-type`)||`image/png`};base64,${Y(await n.arrayBuffer())}`}}catch{}return r}async function ne(){try{let e=await window.db.getAll(`empresa`);if(e.success&&e.data?.length>0)return e.data[0]}catch{}return{}}async function re(e){try{let t=await window.db.getAll(`clientes`);if(!t.success||!Array.isArray(t.data))return{};let n=String(e.cod_cliente||e.cliente_id||``).trim(),r=String(e.nombre_cliente||e.cliente||e.comprador||``).trim().toUpperCase(),i=String(e.telefono_cliente||e.telefono||e.whatsapp||``).trim(),a=String(e.rnc_cliente||e.cedula_cliente||e.rnc||e.cedula||``).trim();return t.data.find(e=>String(e.id||``)===n||String(e.codigo||``)===n||String(e.nombre||``).trim().toUpperCase()===r||String(e.telefono||``).trim()===i||String(e.whatsapp||``).trim()===i||String(e.rnc||``).trim()===a||String(e.cedula||``).trim()===a)||{}}catch{return{}}}function ie(e,t={}){return{nombre:e.nombre_cliente||e.cliente||e.comprador||t?.nombre||`CONSUMIDOR FINAL`,telefono:e.telefono_cliente||e.telefono||e.whatsapp||t?.telefono||t?.whatsapp||``,documento:e.rnc_cliente||e.cedula_cliente||e.rnc||e.cedula||t?.rnc||t?.cedula||``,direccion:e.direccion_cliente||e.direccion||t?.direccion||``}}function ae(e){return String(e.nota||e.observacion||e.observaciones||e.nota_factura||e.comentario||``).trim()}async function oe(){try{return await O(`datosarchivo`)}catch{return{}}}async function se(){try{let e=await window.db.getAll(`impresoras_config`);return e?.success&&Array.isArray(e.data)&&e.data[0]||{}}catch{return{}}}function X(e,t,n,r){let i=Number(e);return Number.isFinite(i)?Math.min(r,Math.max(n,i)):t}function Z(e){return String(e||``).replace(/[&<>"']/g,e=>({"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#39;`})[e]||e)}async function Q({factura:e,cliente:t=null,datosEmpresa:n=null,signature:r=null}){let i=D(),a=(await oe())?.VITE_LINKURL||``,o=n?.empresa||n?.datosEmpresa?.empresa||e.empresa||await ne(),s=t||await re(e),c=T({factura:e,empresa:o,cliente:s}),l={...ie(e,s),...c.customer};l.nombre=w(l.nombre);let u=(ae(e)||i.thanks).replace(/\n/g,`<br>`),d=c.items,p=await te(o?.logoprinter||o?.logo,a),h=await se(),_=X(h.factura_logo_ancho,150,30,400),v=X(h.factura_logo_alto,90,20,250),y=await U(e),b=y.documentStampUrl||`${a||`https://tmposrd.com`}/receipt/factura?factura=${e.no_factura||``}`,x=e.qr||``;try{x||=await k.toDataURL(b)}catch{}let S=Array.isArray(d)?d.map(e=>{let t=V(e.cantidad??e.quantity,0),n=V(e.precio_final??e.precio_venta??e.precio_unitario??e.precio,0),r=V(e.precio_normal??e.precio_lista??e.precio_venta_normal,n),i=V(e.descuento,0),a=V(e.impuesto_venta??e.impuesto,0),o=V(e.total,n*t-i),s=r>0&&n>=0&&n<r;return{...e,codigoProducto:q(e),cantidad:t,precioUnidad:n,precioNormal:r,descuento:i,tieneDescuentoProducto:s,impuestoTotal:a*t,totalProducto:o,imeis:K(e),detallesImei:ee(e)}}):[],E=V(e.impuesto??e.impuestos)>0||S.some(e=>e.impuestoTotal>0),O=V(e.descuento)>0||S.some(e=>e.descuento>0),A=S.reduce((e,t)=>e+t.impuestoTotal,0)||V(e.impuesto??e.impuestos),M=V(e.total),N=V(e.subtotal,M+V(e.descuento)-A),P=String(e.metodo_pago||``).toUpperCase()===`MIXTO`,F=P?M:N,I=(P?f(e):[]).map(e=>`<tr class="payment-row"><td>${e.etiqueta}</td><td class="text-right">${W(e.monto)}</td></tr>`).join(``),L=5+ +!!E+ +!!O,R=G(e.fecha_emision||e.fecha||``,e.hora||``),z=i.language===`en`?`CUSTOMER DETAILS`:`DATOS DEL CLIENTE`,B=i.language===`en`?`PAYMENT SUMMARY`:`RESUMEN DE PAGO`,H=C(e)?i.quote.toUpperCase():e.metodo_pago===`CREDITO`?i.creditInvoice:i.invoice.toUpperCase(),J=S.map(e=>{let t=e.detallesImei.length?e.detallesImei.map(e=>`<div class="imei-line">IMEI: ${e}</div>`).join(``):e.imeis.length?`<div class="imei-line">IMEI: ${e.imeis.join(`, `)}</div>`:``,n=e.tieneDescuentoProducto?`<div class="discount-line">Normal: <span class="line-through">${W(e.precioNormal)}</span> &nbsp; Con descuento: <strong>${W(e.precioUnidad)}</strong></div>`:``;return`
      <tr class="invoice-line">
        <td>${e.codigoProducto||``}</td>
        <td>
          <div>${e.nombre||e.descripcion||``}</div>
          ${n}
          ${t}
        </td>
        <td class="text-center">${e.cantidad}</td>
        <td class="text-right">${W(e.precioUnidad)}</td>
        ${E?`<td class="text-right">${W(e.impuestoTotal)}</td>`:``}
        ${O?`<td class="text-right">${W(e.descuento)}</td>`:``}
        <td class="text-right"><strong>${W(e.totalProducto)}</strong></td>
      </tr>
    `}).join(``),Y=Array.from({length:Math.max(0,8-S.length)},()=>`<tr class="invoice-line empty-row">${Array.from({length:L},()=>`<td>&nbsp;</td>`).join(``)}</tr>`).join(``);return`<!DOCTYPE html>
<html lang="${i.language}">
<head>
  <meta charset="UTF-8">
  <title>${i.invoice} ${e.no_factura||``}</title>
  <style>
    * { box-sizing: border-box; }
    @page { size: letter; margin: 10mm; }
    :root { --navy: #102a43; --blue: #176b9c; --blue-soft: #eaf4f9; --slate: #52667a; --line: #d8e2ea; --surface: #f7fafc; }
    body { margin: 0; background: #fff; color: #172b3a; font-family: "Segoe UI", Arial, Helvetica, sans-serif; font-size: 11px; }
    .page { position: relative; width: 100%; max-width: 760px; margin: 0 auto; padding: 18px 20px 14px; border-top: 6px solid var(--blue); }
    .header { display: flex; justify-content: space-between; align-items: stretch; gap: 28px; padding-bottom: 16px; border-bottom: 1px solid var(--line); }
    .company { flex: 1; min-width: 0; display: flex; align-items: center; gap: 14px; line-height: 1.45; }
    .company img { max-width: ${_}px; max-height: ${v}px; object-fit: contain; flex: 0 0 auto; }
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
    .client-signature img { display: block; max-width: 150px; max-height: 54px; margin: 0 auto 4px; object-fit: contain; }
    .client-signature small { display: block; margin-top: 2px; font-size: 7px; color: var(--slate); }
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
        ${p?`<img src="${p}" alt="Logo">`:``}
        <div class="company-copy">
          <div class="company-name">${o.nombre||i.company}</div>
          <div class="company-meta">
            ${o.legal||o.rnc?`${j.businessIdLabel}: ${o.legal||o.rnc}<br>`:``}
            ${o.telefono?`Tel: ${o.telefono}`:``}${o.email?`${o.telefono?` &nbsp;|&nbsp; `:``}${o.email}<br>`:`<br>`}
            ${o.direccion||``}
          </div>
        </div>
      </div>

      <div class="invoice-box">
        <div class="document-heading">
          <div class="document-type">${H}</div>
          <div class="document-number">${e.no_factura||``}</div>
        </div>
        <table>
          <tr><td><strong>${i.date}</strong></td><td class="text-right">${R}</td></tr>
          ${C(e)||!(e.ncf||e.comprobante)?``:`<tr><td><strong>${j.fiscalDocumentLabel}</strong></td><td class="text-right">${e.ncf||e.comprobante||``}</td></tr>`}
        </table>
        <div class="invoice-title">${C(e)?`${i.quote.toUpperCase()} #${e.no_factura||``}`:e.metodo_pago===`CREDITO`?`${i.creditInvoice} #${e.no_factura||``}`:P?g(e):`${i.invoice.toUpperCase()} #${e.no_factura||``}`}</div>
        ${C(e)?`<div style="text-align:center;margin-top:8px;font-size:10px;color:#666;font-style:italic">${i.quoteValidity}</div>`:``}
      </div>
    </div>

    <div class="client-box">
      <div class="client-data">
        <div class="section-label">${z}</div>
        <div class="client-grid">
          <p><strong>${i.customer}:</strong><br>${l.nombre||i.unregistered}</p>
          <p><strong>${i.phone}:</strong><br>${l.telefono||i.notApplicable}</p>
          <p><strong>${j.customerIdLabel.toUpperCase()}:</strong><br>${l.documento||i.notApplicable}</p>
          <p><strong>${i.paymentMethod}:</strong><br><span class="payment-detail">${m(e)}</span></p>
          <p class="client-wide"><strong>${i.address}:</strong><br>${l.direccion||i.notApplicable}</p>
        </div>
      </div>
      <div class="qr">
        ${x?`<img src="${x}" alt="QR">`:``}
        ${y.securityCode?`<div style="font-size:9px;font-weight:700;text-align:center;margin-top:4px">${i.securityCode}: ${y.securityCode}</div>`:``}
      </div>
    </div>

    <table class="products">
      <thead>
        <tr>
          <th>${i.code}</th>
          <th>${i.description}</th>
          <th>${i.quantity}</th>
          <th>${i.unitPrice}</th>
          ${E?`<th>${j.shortName}</th>`:``}
          ${O?`<th>${i.discount}</th>`:``}
          <th>${i.subtotal}</th>
        </tr>
      </thead>
      <tbody>
        ${J}
        ${Y}
      </tbody>
    </table>

    <div class="note"><strong>${i.observation}:</strong><br>${u}</div>

    <div class="bottom">
      <div class="signatures">
        <div class="signature-line"><strong>${i.deliveredBy}</strong>${e.usuario||e.cajero||i.user}</div>
        <div class="client-signature">${r?.status===`signed`&&r.imageDataUri?`<img src="${r.imageDataUri}" alt="Firma del cliente">`:``}<div class="signature-line"><strong>${i.receivedBy}</strong>${r?.status===`signed`&&r.imageDataUri?Z(r.signerName||l.nombre||i.unregistered):l.nombre||i.unregistered}${r?.status===`signed`&&r.imageDataUri&&r.signedAt?`<small>Firmado: ${Z(r.signedAt)}</small>`:``}</div></div>
      </div>

      <div class="totals">
        <div class="totals-title">${B}</div>
        <table>
          <tr><td>${i.subtotal}</td><td class="text-right">${W(F)}</td></tr>
          ${E?`<tr><td>${j.shortName}</td><td class="text-right">${W(A)}</td></tr>`:``}
          ${O?`<tr><td>${i.discount}</td><td class="text-right">${W(e.descuento)}</td></tr>`:``}
          ${I}
          <tr><td>${i.total}</td><td class="text-right">${W(e.total)}</td></tr>
        </table>
      </div>
    </div>

    <div class="footer"><span>${o.nombre||i.company}</span><span>${i.invoice} ${e.no_factura||``}</span></div>

  </div>
</body>
</html>`}async function ce(e,t){P.value&&URL.revokeObjectURL(P.value),P.value=e,F.value=t,M.value=!0}function $(){P.value&&URL.revokeObjectURL(P.value),P.value=``,F.value=``,M.value=!1}async function le(){if(!P.value)return;let e=`data:application/pdf;base64,${Y(await(await(await fetch(P.value)).blob()).arrayBuffer())}`,t=await window.electron.invoke(`save:pdf`,e,F.value);t.success?A.add({severity:`success`,summary:`Guardado`,detail:`PDF descargado`,life:2e3}):A.add({severity:`error`,summary:`Error`,detail:t.error||`No se pudo guardar el PDF`,life:3e3})}async function ue(e){N.value=!0;try{let t=null;if(!C(e)&&e?.uid)try{t=await I(e)}catch{A.add({severity:`warn`,summary:`Firma no disponible`,detail:`El PDF se generará sin firma porque no se pudo consultar el servidor.`,life:4500})}let n=await Q({factura:e,signature:t}),r=`Factura_${e?.no_factura||`sin_numero`}.pdf`,i=await window.electron.invoke(`generate:pdf`,n,r);if(i.success&&i.dataUrl){let e=await(await fetch(i.dataUrl)).blob();await ce(URL.createObjectURL(e),r)}else A.add({severity:`error`,summary:`Error`,detail:i.error||`No se pudo generar el PDF`,life:3e3})}catch(e){A.add({severity:`error`,summary:`Error`,detail:e.message||`Error al generar PDF`,life:3e3})}finally{N.value=!1}}return u({printFactura:ue,generateFacturaHtml:Q}),(e,n)=>(i(),o(c,null,[a(`div`,L,[s(t(S))]),s(t(x),{visible:M.value,"onUpdate:visible":n[0]||=e=>M.value=e,header:`Vista Previa - Factura PDF`,modal:``,style:{width:`80vw`,height:`90vh`},draggable:!1,onHide:$},{footer:r(()=>[s(t(_),{label:`Cerrar`,severity:`secondary`,text:``,onClick:$}),s(t(_),{label:`Descargar PDF`,icon:`pi pi-download`,onClick:le})]),default:r(()=>[a(`div`,R,[P.value?(i(),o(`iframe`,{key:0,src:P.value,class:`w-full flex-1 border-0 rounded-lg`,style:{"min-height":`70vh`},title:`Factura PDF`},null,8,z)):l(``,!0)])]),_:1},8,[`visible`])],64))}});export{I as i,P as n,F as r,B as t};