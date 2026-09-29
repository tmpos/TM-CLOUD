import{r as e}from"./rolldown-runtime-hePW80VL.js";import{A as t,v as n}from"./reactivity.esm-bundler-C6Po7tbM.js";import{J as r,P as i,c as a,d as o,g as s,r as c,u as l,v as u}from"./runtime-core.esm-bundler-EoPqhpgY.js";import{f as d,l as f,o as p,t as m,u as h,v as g}from"./tmCloudClient-BXA3SgwJ.js";import{B as _,C as v,M as y,O as b,x,xt as S}from"./index-BDHeFXod.js";import{t as C}from"./browser-Cpc4qo6y.js";import{r as ee}from"./documentSettingsService-DYvVqCQX.js";import{a as te,c as w,d as T,i as E,l as D,n as ne,r as O,s as k,u as re,v as A}from"./TicketFacturaPrint-XZKJbGpm.js";import{n as j}from"./funciones-BvWR8JyV.js";var ie=e(C());function M(e){return e?.data?.signature||e?.data?.request||e?.data||e}function N(e){return JSON.parse(JSON.stringify(e??{}))}function P(e){let t=M(e),n=String(t?.url||``).trim();if(!/^https:\/\//i.test(n))throw Error(`El servidor no devolvió un enlace HTTPS de firma válido`);return{uid:String(t?.uid||``),url:n,expiresAt:t?.expires_at||t?.expiresAt||null,status:t?.status===`signed`?`signed`:`pending`}}function F(e){let t=M(e),n=[`none`,`pending`,`signed`,`stale`,`expired`,`unavailable`].includes(t?.status)?t.status:`none`,r=String(t?.signature_data_uri||t?.signatureDataUri||``).trim();return{status:n,signerName:String(t?.signer_name||t?.signerName||``).trim(),signedAt:t?.signed_at||t?.signedAt||null,imageDataUri:n===`signed`&&/^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(r)?r:null}}function I(e,t){return[`Hola ${e?.nombre_cliente||`cliente`},`,`Por favor revisa y firma la factura ${e?.no_factura||``} por RD$${Number(e?.total||0).toFixed(2)}.`,`Abre este enlace seguro para firmar:`,t,``,`La factura mostrará tu firma después de confirmarla.`].join(`
`)}async function L(e){let t=String(e?.uid||``).trim();if(!t)throw Error(`La factura aún no tiene UID de sincronización`);if(window.__isServerSystem||window.__isElectron){let n=await window.electron.invoke(`facturas:crearEnlaceFirma`,{record_uid:t,invoice:N(e)});if(n?.success===!1)throw Error(n.error||`No se pudo crear el enlace de firma`);return P(n)}await p(!0);let n=f();if(!n?.url||!n.key)throw Error(`TM Cloud no está configurado para emitir solicitudes de firma`);let r=await fetch(`${n.url}/invoices/${encodeURIComponent(t)}/signature-request`,{method:`POST`,headers:m(n.key,!0),body:JSON.stringify({expires_at:new Date(Date.now()+6048e5).toISOString().replace(`T`,` `).slice(0,19)})});if(!r.ok)throw Error(await g(r));return P(await r.json())}async function R(e){let t=String(e?.uid||``).trim();if(!t)return{status:`none`,signerName:``,signedAt:null,imageDataUri:null};if(window.__isServerSystem||window.__isElectron){let n=await window.electron.invoke(`facturas:obtenerFirma`,{record_uid:t,invoice:N(e)});if(n?.success===!1)throw Error(n.error||`No se pudo consultar la firma`);return F(n)}await p(!0);let n=f();if(!n?.url||!n.key)throw Error(`TM Cloud no está configurado para consultar firmas`);let r=await fetch(`${n.url}/invoices/${encodeURIComponent(t)}/signature`,{headers:m(n.key)});if(!r.ok)throw Error(await g(r));return F(await r.json())}var z={style:{display:`none`}},B={class:`flex flex-col h-full gap-3`},V=[`src`,`title`],H=u({__name:`FacturaPdfPrint`,setup(e,{expose:u}){function f(e){let t=O(),n=U(e?.otro,{}),r=e.banco_nombre||n?.banco_nombre||``,i=[],a=Number(e.efectivo||0);a>0&&i.push({tipo:`efectivo`,etiqueta:t.cash,monto:a});let o=U(e.tarjeta_mixta,e.tarjeta_mixta),s=o&&typeof o==`object`?o:n?.tarjeta_mixta||{},c=Number(e.tarjeta||s?.total||Number(s?.monto_base||0)+Number(s?.monto_comision||0));c>0&&i.push({tipo:`tarjeta`,etiqueta:t.card,monto:c});let l=U(e.transferencias_mixtas,e.transferencias_mixtas),u=U(n?.transferencias_mixtas,n?.transferencias_mixtas),d=Array.isArray(l)?l:Array.isArray(u)?u:Number(e.transferencia)>0?[{monto:e.transferencia,banco_nombre:r}]:[];for(let e of d){let n=Number(e?.monto||0);if(n<=0)continue;let r=e?.banco_nombre?` (${e.banco_nombre})`:``;i.push({tipo:`transferencia`,etiqueta:`${t.transfer}${r}`,monto:n})}if(Number(e.cheque)>0&&i.push({tipo:`cheque`,etiqueta:t.check,monto:Number(e.cheque)}),a<=0){let n=Number(e.total||0),r=i.reduce((e,t)=>e+t.monto,0),a=Math.round((n-r)*100)/100;a>.009&&i.unshift({tipo:`efectivo`,etiqueta:t.cash,monto:a})}return i}function m(e){let t=O(),n=String(e.metodo_pago||``).toUpperCase(),r=U(e?.otro,{}),i=e.banco_nombre||r?.banco_nombre||``;if(n!==`MIXTO`){let t=T(e.metodo_pago);return i?`${t} - ${i}`:t}return t.mixed}function g(e){let t=O(),n={efectivo:t.cash,tarjeta:t.card,transferencia:t.transfer,cheque:t.check},r=[...new Set(f(e).map(e=>n[e.tipo].toUpperCase()))];return r.length?`${r.join(` Y `)} - ${t.mixed}`:t.mixed}let C=E,M=S(),N=y(),P=n(!1),F=n(!1),I=n(``),L=n(``),H=n(``);function U(e,t){if(e==null)return t;if(typeof e==`string`)try{return JSON.parse(e)}catch{return t}return e}function W(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function G(e,t={}){let n=U(e?.otro,{}),r=n?.alanube_response||e?.alanube_response||{};return{documentStampUrl:t?.document_stamp_url||e?.document_stamp_url||e?.documentStampUrl||n?.documentStampUrl||n?.document_stamp_url||r?.documentStampUrl||r?.document_stamp_url||``,securityCode:t?.security_code||e?.codigo_seguridad||e?.securityCode||n?.securityCode||n?.security_code||r?.securityCode||r?.security_code||``,legalStatus:t?.legal_status||e?.alanube_legal_status||r?.legalStatus||n?.legalStatus||``,status:t?.status||e?.alanube_status||r?.status||n?.status||``}}async function ae(e){if(e?.id)try{let t=await window.db.getWhere(`facturas_ecf`,`factura_id = ?`,[e.id]),n=t?.success&&Array.isArray(t.data)?t.data[0]:null;if(n)return G(e,n)}catch{}return G(e)}function K(e){return b(e)}function oe(e,t=``){let n=String(t||``).trim().match(/^(\d{1,2}:\d{2})/),r=String(e||``).trim(),i=r.match(/(?:T|\s)(\d{1,2}:\d{2})/),a=n?n[1].padStart(5,`0`):i?i[1].padStart(5,`0`):``,o=r.match(/^(\d{4})-(\d{2})-(\d{2})/),s=r.match(/^(\d{2})\/(\d{2})\/(\d{4})/);if(o)return`${o[3]}/${o[2]}/${o[1]}${a?` ${a}`:``}`;if(s)return`${s[1]}/${s[2]}/${s[3]}${a?` ${a}`:``}`;let c=e instanceof Date?e:new Date(e);if(Number.isNaN(c.getTime()))return a;let l=`${String(c.getDate()).padStart(2,`0`)}/${String(c.getMonth()+1).padStart(2,`0`)}/${c.getFullYear()}`,u=`${String(c.getHours()).padStart(2,`0`)}:${String(c.getMinutes()).padStart(2,`0`)}`;return`${l} ${a||u}`}function se(e){return[e?.imei,e?.lista_imei,e?.imeis,e?.serial,e?.seriales].flatMap(e=>Array.isArray(e)?e:typeof e==`string`?e.split(`,`):e?[e]:[]).map(e=>typeof e==`object`?String(e.imei||e.serial||``).trim():String(e).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t)}function ce(e){return String(e?.codigo||e?.codigo_barra||e?.cod_producto||e?.sku||e?.referencia||e?.barcode||e?.imei||e?.serial||e?.accesorio_id||e?.telefono_id||e?.imei_id||e?.serial_id||``).trim()}function q(e,t=``){let n=String(e??``).trim();return n?/^(data:|https?:\/\/|file:|blob:)/i.test(n)?n:n.startsWith(`/`)?t?`${t.replace(/\/$/,``)}${n}`:n:t?`${t.replace(/\/$/,``)}/${n.replace(/^\.\//,``)}`:n:``}function J(e){let t=new Uint8Array(e),n=``,r=32768;for(let e=0;e<t.length;e+=r)n+=String.fromCharCode(...t.subarray(e,e+r));return btoa(n)}async function le(e,t=``){let n=String(e??``).trim();if(!n)return``;let r=q(n,t);if(/^data:/i.test(r))return r;if(!/^(https?:\/\/|file:|blob:|\/)/i.test(n))try{await p();let e=d(n),t=h()?.key||``;if(e){let n=await fetch(e,{headers:t?{Authorization:`Bearer ${t}`}:{}});if(n.ok)return`data:${n.headers.get(`content-type`)||`image/png`};base64,${J(await n.arrayBuffer())}`}}catch{}return r}async function ue(e){try{let t=await window.db.getAll(`empresa`);if(t.success&&t.data?.length>0)return k(e,t.data)}catch{}return{}}async function de(e){try{let t=await window.db.getAll(`clientes`);return!t.success||!Array.isArray(t.data)?{}:w(e,t.data)}catch{return{}}}function fe(e,t={}){return{nombre:e.nombre_cliente||e.cliente||e.comprador||t?.nombre||`CONSUMIDOR FINAL`,telefono:e.telefono_cliente||e.telefono||e.whatsapp||t?.telefono||t?.whatsapp||``,documento:e.rnc_cliente||e.cedula_cliente||e.rnc||e.cedula||t?.rnc||t?.cedula||``,direccion:e.direccion_cliente||e.direccion||t?.direccion||``}}function pe(e){return String(e.nota||e.observacion||e.observaciones||e.nota_factura||e.comentario||``).trim()}async function me(){try{return await j(`datosarchivo`)}catch{return{}}}function Y(e,t,n,r){let i=Number(e);return Number.isFinite(i)?Math.min(r,Math.max(n,i)):t}function X(e){return String(e||``).replace(/[&<>"']/g,e=>({"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#39;`})[e]||e)}async function Z({factura:e,cliente:t=null,datosEmpresa:n=null,signature:r=null}){let i=O(),a=C(e),o=D(e),s=a?i.quote:i.invoice,c=(await me())?.VITE_LINKURL||``,l=n?.empresa||n?.datosEmpresa?.empresa||e.empresa||await ue(e),u=t||await de(e),d=te({factura:e,empresa:l,cliente:u}),p={...fe(e,u),...d.customer};p.nombre=re(p.nombre);let h=(pe(e)||(a?``:i.thanks)).replace(/\n/g,`<br>`),_=d.items,v=await ee(),y=e=>v.visibility?.[e]!==!1,b=(e,t)=>y(e)?t:``,x=[`item_name`,`description`,`identifiers`].some(y),S=v.show_company_signature&&v.representative_signature?`<div class="client-signature company-signature"><img src="${v.representative_signature}" alt="Firma del representante"><div class="signature-line"><strong>${X(v.representative_name)}</strong>${i.language===`en`?`Company representative`:`Representante de la empresa`}</div></div>`:``,w=v.show_logo?await le(l?.logoprinter||l?.logo,c):``,T={factura_logo_ancho:v.logo_width,factura_logo_alto:v.logo_height},E=Y(T.factura_logo_ancho,150,30,400),k=Y(T.factura_logo_alto,90,20,250),j=a?G({}):await ae(e),M=j.documentStampUrl||`${c||`https://tmposrd.com`}/receipt/factura?factura=${o}`,P=a?``:e.qr||``;try{!a&&!P&&(P=await ie.toDataURL(M))}catch{}let F=Array.isArray(_)?_.map(e=>{let t=W(e.cantidad??e.quantity,0),n=W(e.precio_final??e.precio_venta??e.precio_unitario??e.precio,0),r=W(e.precio_normal??e.precio_lista??e.precio_venta_normal,n),i=W(e.descuento,0),a=W(e.impuesto_venta??e.impuesto,0),o=W(e.total,n*t-i),s=r>0&&n>=0&&n<r;return{...e,codigoProducto:ce(e),cantidad:t,precioUnidad:n,precioNormal:r,descuento:i,tieneDescuentoProducto:s,impuestoTotal:a*t,totalProducto:o,imeis:se(e),detallesImei:ne(e)}}):[],I=W(e.impuesto??e.impuestos)>0||F.some(e=>e.impuestoTotal>0),L=W(e.descuento)>0||F.some(e=>e.descuento>0),R=F.reduce((e,t)=>e+t.impuestoTotal,0)||W(e.impuesto??e.impuestos),z=W(e.total),B=W(e.subtotal,z+W(e.descuento)-R),V=!a&&String(e.metodo_pago||``).toUpperCase()===`MIXTO`,H=V?z:B,U=(V?f(e):[]).map(e=>`<tr class="payment-row"><td>${e.etiqueta}</td><td class="text-right">${K(e.monto)}</td></tr>`).join(``),q=[`code`,`quantity`,`price`,`line_total`].filter(y).length+Number(x)+Number(y(`line_tax`)&&I)+Number(y(`line_discount`)&&L),J=oe(e.fecha_emision||e.fecha||``,e.hora||``),Z=i.language===`en`?`CUSTOMER DETAILS`:`DATOS DEL CLIENTE`,Q=a?i.language===`en`?`QUOTE SUMMARY`:`RESUMEN DE ${i.quote.toUpperCase()}`:i.language===`en`?`PAYMENT SUMMARY`:`RESUMEN DE PAGO`,$=C(e)?i.quote.toUpperCase():e.metodo_pago===`CREDITO`?i.creditInvoice:i.invoice.toUpperCase(),he=F.map(e=>{let t=e.detallesImei.length?e.detallesImei.map(e=>`<div class="imei-line">IMEI: ${e}</div>`).join(``):e.imeis.length?`<div class="imei-line">IMEI: ${e.imeis.join(`, `)}</div>`:``,n=e.tieneDescuentoProducto?`<div class="discount-line">Normal: <span class="line-through">${K(e.precioNormal)}</span> &nbsp; Con descuento: <strong>${K(e.precioUnidad)}</strong></div>`:``;return`
      <tr class="invoice-line">
        ${b(`code`,`<td>${e.codigoProducto||``}</td>`)}
        ${x?`<td>
          ${b(`item_name`,`<div>${X(e.nombre||e.descripcion||``)}</div>`)}
          ${y(`description`)&&e.nombre&&String(e.descripcion||``).trim()&&String(e.descripcion).trim()!==String(e.nombre).trim()?`<div class="item-description">${X(e.descripcion).replace(/\r?\n/g,`<br>`)}</div>`:``}
          ${y(`price`)&&y(`line_discount`)?n:``}
          ${y(`identifiers`)?t:``}
        </td>`:``}
        ${b(`quantity`,`<td class="text-center">${e.cantidad}</td>`)}
        ${b(`price`,`<td class="text-right">${K(e.precioUnidad)}</td>`)}
        ${y(`line_tax`)&&I?`<td class="text-right">${K(e.impuestoTotal)}</td>`:``}
        ${y(`line_discount`)&&L?`<td class="text-right">${K(e.descuento)}</td>`:``}
        ${b(`line_total`,`<td class="text-right"><strong>${K(e.totalProducto)}</strong></td>`)}
      </tr>
    `}).join(``),ge=Array.from({length:Math.max(0,8-F.length)},()=>`<tr class="invoice-line empty-row">${Array.from({length:q},()=>`<td>&nbsp;</td>`).join(``)}</tr>`).join(``);return`<!DOCTYPE html>
<html lang="${i.language}">
<head>
  <meta charset="UTF-8">
  <title>${s} ${o}</title>
  <style>
    * { box-sizing: border-box; }
    @page { size: letter; margin: 10mm; }
    :root { --navy: ${v.heading_color}; --blue: ${v.primary_color}; --blue-soft: #eaf4f9; --slate: #52667a; --line: #d8e2ea; --surface: #f7fafc; }
    body { margin: 0; background: #fff; color: #172b3a; font-family: "Segoe UI", Arial, Helvetica, sans-serif; font-size: 11px; }
    .page { position: relative; width: 100%; max-width: 760px; margin: 0 auto; padding: 18px 20px 14px; border-top: 6px solid var(--blue); }
    .header { display: flex; justify-content: space-between; align-items: stretch; gap: 28px; padding-bottom: 16px; border-bottom: 1px solid var(--line); }
    .company { flex: 1; min-width: 0; display: flex; align-items: center; gap: 14px; line-height: 1.45; }
    .company img { max-width: ${E}px; max-height: ${k}px; object-fit: contain; flex: 0 0 auto; }
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
    .item-description { margin-top: 4px; font-size: 10px; line-height: 1.45; white-space: normal; overflow-wrap: anywhere; color: var(--slate); }
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
        ${w?`<img src="${w}" alt="Logo">`:``}
        <div class="company-copy">
          ${b(`company_name`,`<div class="company-name">${l.nombre||i.company}</div>`)}
          <div class="company-meta">
            ${y(`company_tax_id`)&&(l.legal||l.rnc)?`${N.businessIdLabel}: ${l.legal||l.rnc}<br>`:``}
            ${y(`company_phone`)&&l.telefono?`Tel: ${l.telefono}`:``}${y(`company_email`)&&l.email?`${y(`company_phone`)&&l.telefono?` &nbsp;|&nbsp; `:``}${l.email}<br>`:`<br>`}
            ${b(`company_address`,`${l.direccion||``}`)}
          </div>
        </div>
      </div>

      <div class="invoice-box">
        <div class="document-heading">
          ${b(`document_title`,`<div class="document-type">${$}</div>`)}
          ${y(`fiscal`)&&!C(e)&&A(e)?`<div class="document-type">${A(e).toUpperCase()}</div>`:``}
          ${b(`document_number`,`<div class="document-number">${o}</div>`)}
        </div>
        <table>
          ${b(`date`,`<tr><td><strong>${i.date}</strong></td><td class="text-right">${J}</td></tr>`)}
          ${!y(`fiscal`)||C(e)||!(e.ncf||e.comprobante)?``:`<tr><td><strong>${N.fiscalDocumentLabel}</strong></td><td class="text-right">${e.ncf||e.comprobante||``}</td></tr>`}
        </table>

        ${y(`document_title`)&&y(`document_number`)&&y(`payment`)?`        <div class="invoice-title">${C(e)?`${i.quote.toUpperCase()} #${o}`:e.metodo_pago===`CREDITO`?`${i.creditInvoice} #${o}`:V?g(e):`${i.invoice.toUpperCase()} #${o}`}</div>`:``}
        ${y(`validity`)&&C(e)?`<div style="text-align:center;margin-top:8px;font-size:10px;color:#666;font-style:italic">${i.quoteValidity.replace(`30`,String(v.quote_validity_days))}</div>`:``}
      </div>
    </div>

    ${[`customer_name`,`customer_phone`,`customer_tax_id`,`customer_email`,`customer_address`,`payment`,`qr`].some(y)?`    <div class="client-box">
      <div class="client-data">
        <div class="section-label">${Z}</div>
        <div class="client-grid">
          ${b(`customer_name`,`<p><strong>${i.customer}:</strong><br>${X(p.nombre||i.unregistered)}</p>`)}
          ${b(`customer_phone`,`<p><strong>${i.phone}:</strong><br>${X(p.telefono||i.notApplicable)}</p>`)}
          ${b(`customer_tax_id`,`<p><strong>${N.customerIdLabel.toUpperCase()}:</strong><br>${X(p.documento||i.notApplicable)}</p>`)}
          ${a||!y(`payment`)?``:`<p><strong>${i.paymentMethod}:</strong><br><span class="payment-detail">${m(e)}</span></p>`}
          ${b(`customer_email`,`<p><strong>EMAIL:</strong><br>${X(p.email||i.notApplicable)}</p>`)}
          ${b(`customer_address`,`<p class="client-wide"><strong>${i.address}:</strong><br>${X(p.direccion||i.notApplicable)}</p>`)}
        </div>
      </div>
${b(`qr`,`      <div class="qr">
        ${P?`<img src="${P}" alt="QR">`:``}
        ${j.securityCode?`<div style="font-size:9px;font-weight:700;text-align:center;margin-top:4px">${i.securityCode}: ${j.securityCode}</div>`:``}
      </div>`)}
    </div>

`:``}
    ${q?`    <table class="products">
      <thead>
        <tr>
          ${b(`code`,`<th>${i.code}</th>`)}
          ${x?`<th>${i.description}</th>`:``}
          ${b(`quantity`,`<th>${i.quantity}</th>`)}
          ${b(`price`,`<th>${i.unitPrice}</th>`)}
          ${y(`line_tax`)&&I?`<th>${N.shortName}</th>`:``}
          ${y(`line_discount`)&&L?`<th>${i.discount}</th>`:``}
          ${b(`line_total`,`<th>${i.subtotal}</th>`)}
        </tr>
      </thead>
      <tbody>
        ${he}
        ${ge}
      </tbody>
    </table>`:``}

${b(`notes`,`    <div class="note"><strong>${i.observation}:</strong><br>${h}</div>`)}

    <div class="bottom">
      ${a&&!S?``:`<div class="signatures">
        ${S||(y(`delivered_by`)?`<div class="signature-line"><strong>${i.deliveredBy}</strong>${e.usuario||e.cajero||i.user}</div>`:``)}
        ${a||!y(`customer_signature`)?``:`
        <div class="client-signature">${r?.status===`signed`&&r.imageDataUri?`<img src="${r.imageDataUri}" alt="Firma del cliente">`:``}<div class="signature-line"><strong>${i.receivedBy}</strong>${r?.status===`signed`&&r.imageDataUri?X(r.signerName||p.nombre||i.unregistered):p.nombre||i.unregistered}${r?.status===`signed`&&r.imageDataUri&&r.signedAt?`<small>Firmado: ${X(r.signedAt)}</small>`:``}</div></div>
        `}
      </div>`}

      ${[`subtotal`,`tax`,`discount`,`payment`,`total`].some(y)?`      <div class="totals">
        <div class="totals-title">${Q}</div>
        <table>
          ${b(`subtotal`,`<tr><td>${i.subtotal}</td><td class="text-right">${K(H)}</td></tr>`)}
          ${y(`tax`)&&I?`<tr><td>${N.shortName}</td><td class="text-right">${K(R)}</td></tr>`:``}
          ${y(`discount`)&&L?`<tr><td>${i.discount}</td><td class="text-right">${K(e.descuento)}</td></tr>`:``}
          ${y(`payment`)?U:``}
          ${b(`total`,`<tr><td>${i.total}</td><td class="text-right">${K(e.total)}</td></tr>`)}
        </table>
      </div>`:``}
    </div>

    ${b(`footer`,`<div class="footer"><span>${y(`company_name`)?l.nombre||i.company:``}</span><span>${y(`document_title`)?s:``} ${y(`document_number`)?o:``}</span></div>`)}

  </div>
</body>
</html>`}async function Q(e,t){I.value&&URL.revokeObjectURL(I.value),I.value=e,L.value=t,P.value=!0}function $(){I.value&&URL.revokeObjectURL(I.value),I.value=``,L.value=``,P.value=!1}async function he(){if(!I.value)return;let e=`data:application/pdf;base64,${J(await(await(await fetch(I.value)).blob()).arrayBuffer())}`,t=await window.electron.invoke(`save:pdf`,e,L.value);t.success?M.add({severity:`success`,summary:`Guardado`,detail:`PDF descargado`,life:2e3}):M.add({severity:`error`,summary:`Error`,detail:t.error||`No se pudo guardar el PDF`,life:3e3})}async function ge(e){F.value=!0;try{let t=null;if(!C(e)&&e?.uid)try{t=await R(e)}catch{M.add({severity:`warn`,summary:`Firma no disponible`,detail:`El PDF se generará sin firma porque no se pudo consultar el servidor.`,life:4500})}let n=await Z({factura:e,signature:t});H.value=C(e)?O().quote:O().invoice;let r=`${C(e)?`Cotizacion`:`Factura`}_${D(e)||`sin_numero`}.pdf`,i=await window.electron.invoke(`generate:pdf`,n,r);if(i.success&&i.dataUrl){let e=await(await fetch(i.dataUrl)).blob();await Q(URL.createObjectURL(e),r)}else M.add({severity:`error`,summary:`Error`,detail:i.error||`No se pudo generar el PDF`,life:3e3})}catch(e){M.add({severity:`error`,summary:`Error`,detail:e.message||`Error al generar PDF`,life:3e3})}finally{F.value=!1}}return u({printFactura:ge,generateFacturaHtml:Z}),(e,n)=>(i(),o(c,null,[a(`div`,z,[s(t(_))]),s(t(x),{visible:P.value,"onUpdate:visible":n[0]||=e=>P.value=e,header:`Vista Previa - ${H.value} PDF`,modal:``,style:{width:`80vw`,height:`90vh`},draggable:!1,onHide:$},{footer:r(()=>[s(t(v),{label:`Cerrar`,severity:`secondary`,text:``,onClick:$}),s(t(v),{label:`Descargar PDF`,icon:`pi pi-download`,onClick:he})]),default:r(()=>[a(`div`,B,[I.value?(i(),o(`iframe`,{key:0,src:I.value,class:`w-full flex-1 border-0 rounded-lg`,style:{"min-height":`70vh`},title:`${H.value} PDF`},null,8,V)):l(``,!0)])]),_:1},8,[`visible`,`header`])],64))}});export{R as i,I as n,L as r,H as t};