import{r as e}from"./rolldown-runtime-hePW80VL.js";import{A as t,v as n}from"./reactivity.esm-bundler-C6Po7tbM.js";import{J as r,P as i,c as a,d as o,g as s,r as c,u as l,v as u}from"./runtime-core.esm-bundler-EoPqhpgY.js";import{f as d,l as f,o as p,t as m,u as h,v as g}from"./tmCloudClient-BXA3SgwJ.js";import{B as _,C as v,M as y,O as b,wt as x,x as S}from"./index-CVe8knhC.js";import{t as C}from"./browser-Cpc4qo6y.js";import{r as ee}from"./documentSettingsService-DYvVqCQX.js";import{a as te,c as w,d as T,i as E,l as D,n as ne,r as O,s as k,u as re,v as A}from"./TicketFacturaPrint-C-xE2yBG.js";import{n as j}from"./funciones-BvWR8JyV.js";var ie=e(C());function M(e,t){let n=t?.totals;if(!n||!e.ncf||t?.idDoc?.encf!==e.ncf)return null;let r=[n.itbisTotal,n.totalAmount,e.total];if(r.some(e=>e==null||e===``||!Number.isFinite(Number(e))))return null;let[i,a,o]=r.map(Number);if(i<0||i>a||Math.abs(a-o)>.01)return null;let s=Number(e.descuento_monto||e.descuento||0);return{tax:i,subtotal:Math.round((o-i+s)*100)/100}}function ae(e,t){let n=e?.itemDetails;if(!Array.isArray(n)||!n.length||n.length!==t.length)return null;let r=e=>String(e||``).trim().toUpperCase();if(n.some((e,n)=>Number(e.lineNumber)!==n+1||r(e.itemName)!==r(t[n].nombre||t[n].descripcion)||Number(e.quantityItem)!==Number(t[n].cantidad??t[n].quantity)||!Number.isFinite(Number(e.itemAmount))||Number(e.itemAmount)<0||![1,2,3,4].includes(Number(e.billingIndicator))))return null;let i=t.map(()=>0);for(let t of[1,2,3]){let r=n.flatMap((e,n)=>Number(e.billingIndicator)===t?[n]:[]);if(!r.length)continue;let a=e.totals?.[`itbis${t}Total`]??(t===3?0:null);if(a==null||a===``||!Number.isFinite(Number(a))||Number(a)<0)return null;let o=Math.round(Number(a)*100),s=r.reduce((e,t)=>e+Number(n[t].itemAmount),0);if(!s&&o)return null;let c=0,l=0;for(let e of r){c+=Number(n[e].itemAmount);let t=s?Math.round(o*c/s):0;i[e]=(t-l)/100,l=t}}return Math.round(i.reduce((e,t)=>e+t,0)*100)===Math.round(Number(e.totals?.itbisTotal)*100)?i:null}function N(e){return e?.data?.signature||e?.data?.request||e?.data||e}function P(e){return JSON.parse(JSON.stringify(e??{}))}function F(e){let t=N(e),n=String(t?.url||``).trim();if(!/^https:\/\//i.test(n))throw Error(`El servidor no devolvió un enlace HTTPS de firma válido`);return{uid:String(t?.uid||``),url:n,expiresAt:t?.expires_at||t?.expiresAt||null,status:t?.status===`signed`?`signed`:`pending`}}function I(e){let t=N(e),n=[`none`,`pending`,`signed`,`stale`,`expired`,`unavailable`].includes(t?.status)?t.status:`none`,r=String(t?.signature_data_uri||t?.signatureDataUri||``).trim();return{status:n,signerName:String(t?.signer_name||t?.signerName||``).trim(),signedAt:t?.signed_at||t?.signedAt||null,imageDataUri:n===`signed`&&/^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(r)?r:null}}function L(e,t){return[`Hola ${e?.nombre_cliente||`cliente`},`,`Por favor revisa y firma la factura ${e?.no_factura||``} por RD$${Number(e?.total||0).toFixed(2)}.`,`Abre este enlace seguro para firmar:`,t,``,`La factura mostrará tu firma después de confirmarla.`].join(`
`)}async function R(e){let t=String(e?.uid||``).trim();if(!t)throw Error(`La factura aún no tiene UID de sincronización`);if(window.__isServerSystem||window.__isElectron){let n=await window.electron.invoke(`facturas:crearEnlaceFirma`,{record_uid:t,invoice:P(e)});if(n?.success===!1)throw Error(n.error||`No se pudo crear el enlace de firma`);return F(n)}await p(!0);let n=f();if(!n?.url||!n.key)throw Error(`TM Cloud no está configurado para emitir solicitudes de firma`);let r=await fetch(`${n.url}/invoices/${encodeURIComponent(t)}/signature-request`,{method:`POST`,headers:m(n.key,!0),body:JSON.stringify({expires_at:new Date(Date.now()+6048e5).toISOString().replace(`T`,` `).slice(0,19)})});if(!r.ok)throw Error(await g(r));return F(await r.json())}async function z(e){let t=String(e?.uid||``).trim();if(!t)return{status:`none`,signerName:``,signedAt:null,imageDataUri:null};if(window.__isServerSystem||window.__isElectron){let n=await window.electron.invoke(`facturas:obtenerFirma`,{record_uid:t,invoice:P(e)});if(n?.success===!1)throw Error(n.error||`No se pudo consultar la firma`);return I(n)}await p(!0);let n=f();if(!n?.url||!n.key)throw Error(`TM Cloud no está configurado para consultar firmas`);let r=await fetch(`${n.url}/invoices/${encodeURIComponent(t)}/signature`,{headers:m(n.key)});if(!r.ok)throw Error(await g(r));return I(await r.json())}var B={style:{display:`none`}},V={class:`flex flex-col h-full gap-3`},H=[`src`,`title`],U=u({__name:`FacturaPdfPrint`,setup(e,{expose:u}){function f(e){let t=O(),n=W(e?.otro,{}),r=e.banco_nombre||n?.banco_nombre||``,i=[],a=Number(e.efectivo||0);a>0&&i.push({tipo:`efectivo`,etiqueta:t.cash,monto:a});let o=W(e.tarjeta_mixta,e.tarjeta_mixta),s=o&&typeof o==`object`?o:n?.tarjeta_mixta||{},c=Number(e.tarjeta||s?.total||Number(s?.monto_base||0)+Number(s?.monto_comision||0));c>0&&i.push({tipo:`tarjeta`,etiqueta:t.card,monto:c});let l=W(e.transferencias_mixtas,e.transferencias_mixtas),u=W(n?.transferencias_mixtas,n?.transferencias_mixtas),d=Array.isArray(l)?l:Array.isArray(u)?u:Number(e.transferencia)>0?[{monto:e.transferencia,banco_nombre:r}]:[];for(let e of d){let n=Number(e?.monto||0);if(n<=0)continue;let r=e?.banco_nombre?` (${e.banco_nombre})`:``;i.push({tipo:`transferencia`,etiqueta:`${t.transfer}${r}`,monto:n})}if(Number(e.cheque)>0&&i.push({tipo:`cheque`,etiqueta:t.check,monto:Number(e.cheque)}),a<=0){let n=Number(e.total||0),r=i.reduce((e,t)=>e+t.monto,0),a=Math.round((n-r)*100)/100;a>.009&&i.unshift({tipo:`efectivo`,etiqueta:t.cash,monto:a})}return i}function m(e){let t=O(),n=String(e.metodo_pago||``).toUpperCase(),r=W(e?.otro,{}),i=e.banco_nombre||r?.banco_nombre||``;if(n!==`MIXTO`){let t=T(e.metodo_pago);return i?`${t} - ${i}`:t}return t.mixed}function g(e){let t=O(),n={efectivo:t.cash,tarjeta:t.card,transferencia:t.transfer,cheque:t.check},r=[...new Set(f(e).map(e=>n[e.tipo].toUpperCase()))];return r.length?`${r.join(` Y `)} - ${t.mixed}`:t.mixed}let C=E,N=x(),P=y(),F=n(!1),I=n(!1),L=n(``),R=n(``),U=n(``);function W(e,t){if(e==null)return t;if(typeof e==`string`)try{return JSON.parse(e)}catch{return t}return e}function G(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function K(e,t={}){let n=W(e?.otro,{}),r=n?.alanube_response||e?.alanube_response||{},i=W(t?.payload,null)||W(n?.alanube_payload||e?.alanube_payload,{});return{payload:i,totals:M(e,i),documentStampUrl:t?.document_stamp_url||e?.document_stamp_url||e?.documentStampUrl||n?.documentStampUrl||n?.document_stamp_url||r?.documentStampUrl||r?.document_stamp_url||``,securityCode:t?.security_code||e?.codigo_seguridad||e?.securityCode||n?.securityCode||n?.security_code||r?.securityCode||r?.security_code||``,legalStatus:t?.legal_status||e?.alanube_legal_status||r?.legalStatus||n?.legalStatus||``,status:t?.status||e?.alanube_status||r?.status||n?.status||``}}async function oe(e){if(e?.id)try{let t=await window.db.getWhere(`facturas_ecf`,`factura_id = ?`,[e.id]),n=t?.success&&Array.isArray(t.data)?t.data.find(t=>!e.ncf||t.ncf===e.ncf):null;if(n)return K(e,n)}catch{}return K(e)}function q(e){return b(e)}function se(e,t=``){let n=String(t||``).trim().match(/^(\d{1,2}:\d{2})/),r=String(e||``).trim(),i=r.match(/(?:T|\s)(\d{1,2}:\d{2})/),a=n?n[1].padStart(5,`0`):i?i[1].padStart(5,`0`):``,o=r.match(/^(\d{4})-(\d{2})-(\d{2})/),s=r.match(/^(\d{2})\/(\d{2})\/(\d{4})/);if(o)return`${o[3]}/${o[2]}/${o[1]}${a?` ${a}`:``}`;if(s)return`${s[1]}/${s[2]}/${s[3]}${a?` ${a}`:``}`;let c=e instanceof Date?e:new Date(e);if(Number.isNaN(c.getTime()))return a;let l=`${String(c.getDate()).padStart(2,`0`)}/${String(c.getMonth()+1).padStart(2,`0`)}/${c.getFullYear()}`,u=`${String(c.getHours()).padStart(2,`0`)}:${String(c.getMinutes()).padStart(2,`0`)}`;return`${l} ${a||u}`}function ce(e){return[e?.imei,e?.lista_imei,e?.imeis,e?.serial,e?.seriales].flatMap(e=>Array.isArray(e)?e:typeof e==`string`?e.split(`,`):e?[e]:[]).map(e=>typeof e==`object`?String(e.imei||e.serial||``).trim():String(e).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t)}function le(e){for(let t of[`codigo`,`codigo_barra`,`cod_producto`,`codigo_producto`,`codigo_barras`,`sku`,`referencia`,`barcode`,`code`,`imei`,`serial`,`accesorio_id`,`telefono_id`,`imei_id`,`serial_id`,`servicio_id`,`electrodomestico_id`,`producto_id`,`id`]){let n=e?.[t];if(typeof n!=`string`&&typeof n!=`number`)continue;let r=String(n).trim();if(r&&!((t.endsWith(`_id`)||t===`id`)&&Number(r)===0))return r}return``}function J(e,t=``){let n=String(e??``).trim();return n?/^(data:|https?:\/\/|file:|blob:)/i.test(n)?n:n.startsWith(`/`)?t?`${t.replace(/\/$/,``)}${n}`:n:t?`${t.replace(/\/$/,``)}/${n.replace(/^\.\//,``)}`:n:``}function Y(e){let t=new Uint8Array(e),n=``,r=32768;for(let e=0;e<t.length;e+=r)n+=String.fromCharCode(...t.subarray(e,e+r));return btoa(n)}async function ue(e,t=``){let n=String(e??``).trim();if(!n)return``;let r=J(n,t);if(/^data:/i.test(r))return r;if(!/^(https?:\/\/|file:|blob:|\/)/i.test(n))try{await p();let e=d(n),t=h()?.key||``;if(e){let n=await fetch(e,{headers:t?{Authorization:`Bearer ${t}`}:{}});if(n.ok)return`data:${n.headers.get(`content-type`)||`image/png`};base64,${Y(await n.arrayBuffer())}`}}catch{}return r}async function X(e){try{let t=await window.db.getAll(`empresa`);if(t.success&&t.data?.length>0)return k(e,t.data)}catch{}return{}}async function de(e){try{let t=await window.db.getAll(`clientes`);return!t.success||!Array.isArray(t.data)?{}:w(e,t.data)}catch{return{}}}function fe(e,t={}){return{nombre:e.nombre_cliente||e.cliente||e.comprador||t?.nombre||`CONSUMIDOR FINAL`,telefono:e.telefono_cliente||e.telefono||e.whatsapp||t?.telefono||t?.whatsapp||``,documento:e.rnc_cliente||e.cedula_cliente||e.rnc||e.cedula||t?.rnc||t?.cedula||``,direccion:e.direccion_cliente||e.direccion||t?.direccion||``}}function pe(e){return String(e.nota||e.observacion||e.observaciones||e.nota_factura||e.comentario||``).trim()}async function me(){try{return await j(`datosarchivo`)}catch{return{}}}function he(e,t,n,r){let i=Number(e);return Number.isFinite(i)?Math.min(r,Math.max(n,i)):t}function Z(e){return String(e||``).replace(/[&<>"']/g,e=>({"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#39;`})[e]||e)}async function Q({factura:e,cliente:t=null,datosEmpresa:n=null,signature:r=null}){let i=O(),a=C(e),o=D(e),s=a?i.quote:i.invoice,c=(await me())?.VITE_LINKURL||``,l=n?.empresa||n?.datosEmpresa?.empresa||e.empresa,u=l?{...l}:await X(e);l&&l.marca_registrada==null&&(u.marca_registrada=(await X(e)).marca_registrada||``);let d=t||await de(e),p=te({factura:e,empresa:u,cliente:d}),h={...fe(e,d),...p.customer};h.nombre=re(h.nombre);let _=(pe(e)||(a?``:i.thanks)).replace(/\n/g,`<br>`),v=p.items,y=await ee(),b=e=>y.visibility?.[e]!==!1,x=(e,t)=>b(e)?t:``,S=[`item_name`,`description`,`identifiers`].some(b),w=y.show_company_signature&&y.representative_signature?`<div class="client-signature company-signature"><img src="${y.representative_signature}" alt="Firma del representante"><div class="signature-line"><strong>${Z(y.representative_name)}</strong>${i.language===`en`?`Company representative`:`Representante de la empresa`}</div></div>`:``,T=y.show_logo?await ue(u?.logoprinter||u?.logo,c):``,E={factura_logo_ancho:y.logo_width,factura_logo_alto:y.logo_height},k=he(E.factura_logo_ancho,150,30,400),j=he(E.factura_logo_alto,90,20,250),M=a?K({}):await oe(e),N=M.documentStampUrl||`${c||`https://tmposrd.com`}/receipt/factura?factura=${o}`,F=a?``:e.qr||``;try{!a&&!F&&(F=await ie.toDataURL(N))}catch{}let I=M.totals&&Array.isArray(v)?ae(M.payload,v):null,L=Array.isArray(v)?v.map((e,t)=>{let n=G(e.cantidad??e.quantity,0),r=G(e.precio_final??e.precio_venta??e.precio_unitario??e.precio,0),i=G(e.precio_normal??e.precio_lista??e.precio_venta_normal,r),a=G(e.descuento,0),o=G(e.impuesto_venta??e.impuesto,0),s=G(e.total,r*n-a),c=i>0&&r>=0&&r<i;return{...e,codigoProducto:le(e),cantidad:n,precioUnidad:r,precioNormal:i,descuento:a,tieneDescuentoProducto:c,impuestoTotal:I?.[t]??o*n,totalProducto:s,imeis:ce(e),detallesImei:ne(e)}}):[],R=M.totals?.tax??(L.reduce((e,t)=>e+t.impuestoTotal,0)||G(e.impuesto_monto||e.impuesto||e.impuestos)),z=R>0,B=G(e.descuento)>0||L.some(e=>e.descuento>0),V=G(e.total),H=M.totals?.subtotal??G(e.subtotal,V+G(e.descuento)-R),U=!a&&String(e.metodo_pago||``).toUpperCase()===`MIXTO`,W=U&&!M.totals?V:H,J=(U?f(e):[]).map(e=>`<tr class="payment-row"><td>${e.etiqueta}</td><td class="text-right">${q(e.monto)}</td></tr>`).join(``),Y=[`code`,`quantity`,`price`,`line_total`].filter(b).length+Number(S)+Number(b(`line_tax`)&&z)+Number(b(`line_discount`)&&B),Q=se(e.fecha_emision||e.fecha||``,e.hora||``),ge=i.language===`en`?`CUSTOMER DETAILS`:`DATOS DEL CLIENTE`,$=a?i.language===`en`?`QUOTE SUMMARY`:`RESUMEN DE ${i.quote.toUpperCase()}`:i.language===`en`?`PAYMENT SUMMARY`:`RESUMEN DE PAGO`,_e=C(e)?i.quote.toUpperCase():e.metodo_pago===`CREDITO`?i.creditInvoice:i.invoice.toUpperCase(),ve=L.map(e=>{let t=e.detallesImei.length?e.detallesImei.map(e=>`<div class="imei-line">IMEI: ${e}</div>`).join(``):e.imeis.length?`<div class="imei-line">IMEI: ${e.imeis.join(`, `)}</div>`:``,n=e.tieneDescuentoProducto?`<div class="discount-line">Normal: <span class="line-through">${q(e.precioNormal)}</span> &nbsp; Con descuento: <strong>${q(e.precioUnidad)}</strong></div>`:``;return`
      <tr class="invoice-line">
        ${x(`code`,`<td class="product-code">${Z(e.codigoProducto)||`—`}</td>`)}
        ${S?`<td>
          ${x(`item_name`,`<div>${Z(e.nombre||e.descripcion||``)}</div>`)}
          ${b(`description`)&&e.nombre&&String(e.descripcion||``).trim()&&String(e.descripcion).trim()!==String(e.nombre).trim()?`<div class="item-description">${Z(e.descripcion).replace(/\r?\n/g,`<br>`)}</div>`:``}
          ${b(`price`)&&b(`line_discount`)?n:``}
          ${b(`identifiers`)?t:``}
        </td>`:``}
        ${x(`quantity`,`<td class="text-center">${e.cantidad}</td>`)}
        ${x(`price`,`<td class="text-right">${q(e.precioUnidad)}</td>`)}
        ${b(`line_tax`)&&z?`<td class="text-right">${q(e.impuestoTotal)}</td>`:``}
        ${b(`line_discount`)&&B?`<td class="text-right">${q(e.descuento)}</td>`:``}
        ${x(`line_total`,`<td class="text-right"><strong>${q(e.totalProducto)}</strong></td>`)}
      </tr>
    `}).join(``),ye=Array.from({length:Math.max(0,8-L.length)},()=>`<tr class="invoice-line empty-row">${Array.from({length:Y},()=>`<td>&nbsp;</td>`).join(``)}</tr>`).join(``);return`<!DOCTYPE html>
<html lang="${i.language}">
<head>
  <meta charset="UTF-8">
  <title>${s} ${o}</title>
  <style>
    * { box-sizing: border-box; }
    @page { size: letter; margin: 10mm; }
    :root { --navy: ${y.heading_color}; --blue: ${y.primary_color}; --blue-soft: #eaf4f9; --slate: #52667a; --line: #d8e2ea; --surface: #f7fafc; }
    body { margin: 0; background: #fff; color: #172b3a; font-family: "Segoe UI", Arial, Helvetica, sans-serif; font-size: 11px; }
    .page { position: relative; width: 100%; max-width: 760px; margin: 0 auto; padding: 18px 20px 14px; border-top: 6px solid var(--blue); }
    .header { display: flex; justify-content: space-between; align-items: stretch; gap: 28px; padding-bottom: 16px; border-bottom: 1px solid var(--line); }
    .company { flex: 1; min-width: 0; display: flex; align-items: center; gap: 14px; line-height: 1.45; }
    .company img { max-width: ${k}px; max-height: ${j}px; object-fit: contain; flex: 0 0 auto; }
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
    .products .product-code { min-width: 48px; max-width: 110px; overflow-wrap: anywhere; color: var(--navy); }
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
        ${T?`<img src="${T}" alt="Logo">`:``}
        <div class="company-copy">
          ${x(`company_name`,`<div class="company-name">${u.nombre||i.company}</div>`)}
          <div class="company-meta">
            ${String(u.marca_registrada||``).trim()?`<div class="company-trademark">${Z(String(u.marca_registrada).trim())}</div>`:``}
            ${b(`company_tax_id`)&&(u.legal||u.rnc)?`${P.businessIdLabel}: ${u.legal||u.rnc}<br>`:``}
            ${b(`company_phone`)&&u.telefono?`Tel: ${u.telefono}`:``}${b(`company_email`)&&u.email?`${b(`company_phone`)&&u.telefono?` &nbsp;|&nbsp; `:``}${u.email}<br>`:`<br>`}
            ${x(`company_address`,`${u.direccion||``}`)}
          </div>
        </div>
      </div>

      <div class="invoice-box">
        <div class="document-heading">
          ${x(`document_title`,`<div class="document-type">${_e}</div>`)}
          ${b(`fiscal`)&&!C(e)&&A(e)?`<div class="document-type">${A(e).toUpperCase()}</div>`:``}
          ${x(`document_number`,`<div class="document-number">${o}</div>`)}
        </div>
        <table>
          ${x(`date`,`<tr><td><strong>${i.date}</strong></td><td class="text-right">${Q}</td></tr>`)}
          ${!b(`fiscal`)||C(e)||!(e.ncf||e.comprobante)?``:`<tr><td><strong>${P.fiscalDocumentLabel}</strong></td><td class="text-right">${e.ncf||e.comprobante||``}</td></tr>`}
        </table>

        ${b(`document_title`)&&b(`document_number`)&&b(`payment`)?`        <div class="invoice-title">${C(e)?`${i.quote.toUpperCase()} #${o}`:e.metodo_pago===`CREDITO`?`${i.creditInvoice} #${o}`:U?g(e):`${i.invoice.toUpperCase()} #${o}`}</div>`:``}
        ${b(`validity`)&&C(e)?`<div style="text-align:center;margin-top:8px;font-size:10px;color:#666;font-style:italic">${i.quoteValidity.replace(`30`,String(y.quote_validity_days))}</div>`:``}
      </div>
    </div>

    ${[`customer_name`,`customer_phone`,`customer_tax_id`,`customer_email`,`customer_address`,`payment`,`qr`].some(b)?`    <div class="client-box">
      <div class="client-data">
        <div class="section-label">${ge}</div>
        <div class="client-grid">
          ${x(`customer_name`,`<p><strong>${i.customer}:</strong><br>${Z(h.nombre||i.unregistered)}</p>`)}
          ${x(`customer_phone`,`<p><strong>${i.phone}:</strong><br>${Z(h.telefono||i.notApplicable)}</p>`)}
          ${x(`customer_tax_id`,`<p><strong>${P.customerIdLabel.toUpperCase()}:</strong><br>${Z(h.documento||i.notApplicable)}</p>`)}
          ${a||!b(`payment`)?``:`<p><strong>${i.paymentMethod}:</strong><br><span class="payment-detail">${m(e)}</span></p>`}
          ${x(`customer_email`,`<p><strong>EMAIL:</strong><br>${Z(h.email||i.notApplicable)}</p>`)}
          ${x(`customer_address`,`<p class="client-wide"><strong>${i.address}:</strong><br>${Z(h.direccion||i.notApplicable)}</p>`)}
        </div>
      </div>
${x(`qr`,`      <div class="qr">
        ${F?`<img src="${F}" alt="QR">`:``}
        ${M.securityCode?`<div style="font-size:9px;font-weight:700;text-align:center;margin-top:4px">${i.securityCode}: ${M.securityCode}</div>`:``}
      </div>`)}
    </div>

`:``}
    ${Y?`    <table class="products">
      <thead>
        <tr>
          ${x(`code`,`<th>${i.code}</th>`)}
          ${S?`<th>${i.description}</th>`:``}
          ${x(`quantity`,`<th>${i.quantity}</th>`)}
          ${x(`price`,`<th>${i.unitPrice}</th>`)}
          ${b(`line_tax`)&&z?`<th>${P.shortName}</th>`:``}
          ${b(`line_discount`)&&B?`<th>${i.discount}</th>`:``}
          ${x(`line_total`,`<th>${i.subtotal}</th>`)}
        </tr>
      </thead>
      <tbody>
        ${ve}
        ${ye}
      </tbody>
    </table>`:``}

${x(`notes`,`    <div class="note"><strong>${i.observation}:</strong><br>${_}</div>`)}

    <div class="bottom">
      ${a&&!w?``:`<div class="signatures">
        ${w||(b(`delivered_by`)?`<div class="signature-line"><strong>${i.deliveredBy}</strong>${e.usuario||e.cajero||i.user}</div>`:``)}
        ${a||!b(`customer_signature`)?``:`
        <div class="client-signature">${r?.status===`signed`&&r.imageDataUri?`<img src="${r.imageDataUri}" alt="Firma del cliente">`:``}<div class="signature-line"><strong>${i.receivedBy}</strong>${r?.status===`signed`&&r.imageDataUri?Z(r.signerName||h.nombre||i.unregistered):h.nombre||i.unregistered}${r?.status===`signed`&&r.imageDataUri&&r.signedAt?`<small>Firmado: ${Z(r.signedAt)}</small>`:``}</div></div>
        `}
      </div>`}

      ${[`subtotal`,`tax`,`discount`,`payment`,`total`].some(b)?`      <div class="totals">
        <div class="totals-title">${$}</div>
        <table>
          ${x(`subtotal`,`<tr><td>${i.subtotal}</td><td class="text-right">${q(W)}</td></tr>`)}
          ${b(`tax`)&&z?`<tr><td>${P.shortName}</td><td class="text-right">${q(R)}</td></tr>`:``}
          ${b(`discount`)&&B?`<tr><td>${i.discount}</td><td class="text-right">${q(e.descuento)}</td></tr>`:``}
          ${b(`payment`)?J:``}
          ${x(`total`,`<tr><td>${i.total}</td><td class="text-right">${q(e.total)}</td></tr>`)}
        </table>
      </div>`:``}
    </div>

    ${x(`footer`,`<div class="footer"><span>${b(`company_name`)?u.nombre||i.company:``}</span><span>${b(`document_title`)?s:``} ${b(`document_number`)?o:``}</span></div>`)}

  </div>
</body>
</html>`}async function ge(e,t){L.value&&URL.revokeObjectURL(L.value),L.value=e,R.value=t,F.value=!0}function $(){L.value&&URL.revokeObjectURL(L.value),L.value=``,R.value=``,F.value=!1}async function _e(){if(!L.value)return;let e=`data:application/pdf;base64,${Y(await(await(await fetch(L.value)).blob()).arrayBuffer())}`,t=await window.electron.invoke(`save:pdf`,e,R.value);t.success?N.add({severity:`success`,summary:`Guardado`,detail:`PDF descargado`,life:2e3}):N.add({severity:`error`,summary:`Error`,detail:t.error||`No se pudo guardar el PDF`,life:3e3})}async function ve(e){I.value=!0;try{let t=null;if(!C(e)&&e?.uid)try{t=await z(e)}catch{N.add({severity:`warn`,summary:`Firma no disponible`,detail:`El PDF se generará sin firma porque no se pudo consultar el servidor.`,life:4500})}let n=await Q({factura:e,signature:t});U.value=C(e)?O().quote:O().invoice;let r=`${C(e)?`Cotizacion`:`Factura`}_${D(e)||`sin_numero`}.pdf`,i=await window.electron.invoke(`generate:pdf`,n,r);if(i.success&&i.dataUrl){let e=await(await fetch(i.dataUrl)).blob();await ge(URL.createObjectURL(e),r)}else N.add({severity:`error`,summary:`Error`,detail:i.error||`No se pudo generar el PDF`,life:3e3})}catch(e){N.add({severity:`error`,summary:`Error`,detail:e.message||`Error al generar PDF`,life:3e3})}finally{I.value=!1}}return u({printFactura:ve,generateFacturaHtml:Q}),(e,n)=>(i(),o(c,null,[a(`div`,B,[s(t(_))]),s(t(S),{visible:F.value,"onUpdate:visible":n[0]||=e=>F.value=e,header:`Vista Previa - ${U.value} PDF`,modal:``,style:{width:`80vw`,height:`90vh`},draggable:!1,onHide:$},{footer:r(()=>[s(t(v),{label:`Cerrar`,severity:`secondary`,text:``,onClick:$}),s(t(v),{label:`Descargar PDF`,icon:`pi pi-download`,onClick:_e})]),default:r(()=>[a(`div`,V,[L.value?(i(),o(`iframe`,{key:0,src:L.value,class:`w-full flex-1 border-0 rounded-lg`,style:{"min-height":`70vh`},title:`${U.value} PDF`},null,8,H)):l(``,!0)])]),_:1},8,[`visible`,`header`])],64))}});export{z as i,L as n,R as r,U as t};