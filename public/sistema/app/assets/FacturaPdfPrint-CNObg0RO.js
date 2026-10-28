import{r as e}from"./rolldown-runtime-hePW80VL.js";import{j as t,v as n}from"./reactivity.esm-bundler-CYZ1FWiK.js";import{J as r,P as i,c as a,d as o,g as s,r as c,u as l,v as u}from"./runtime-core.esm-bundler-CyLDb3-k.js";import{f as d,l as f,o as p,t as m,u as h,v as g}from"./tmCloudClient-BOoKx8fp.js";import{n as ee,t as _}from"./invoiceCreditPayment-BiGdfNIQ.js";import{A as te,C as v,Et as y,H as b,P as ne,T as x}from"./index-nD0f1mfo.js";import{t as S}from"./browser-Cpc4qo6y.js";import{r as re}from"./documentSettingsService-CVzPV94Z.js";import{c as C,i as ie,l as ae,n as w,o as T,r as E,s as D,t as oe,u as O}from"./documentDataService-B_BT9tIJ.js";import{i as se,n as ce,r as le,t as ue}from"./hondurasFiscalDocument-gACH-bPM.js";import{n as k}from"./funciones-BBLY1f5Z.js";import{s as de}from"./electronicInvoiceTypes-Dbkp0D11.js";var fe=e(S());function A(e,t){let n=t?.totals;if(!n||!e.ncf||t?.idDoc?.encf!==e.ncf)return null;let r=[n.itbisTotal,n.totalAmount,e.total];if(r.some(e=>e==null||e===``||!Number.isFinite(Number(e))))return null;let[i,a,o]=r.map(Number);if(i<0||i>a||Math.abs(a-o)>.01)return null;let s=Number(e.descuento_monto||e.descuento||0);return{tax:i,subtotal:Math.round((o-i+s)*100)/100}}function pe(e,t){let n=e?.itemDetails;if(!Array.isArray(n)||!n.length||n.length!==t.length)return null;let r=e=>String(e||``).trim().toUpperCase();if(n.some((e,n)=>Number(e.lineNumber)!==n+1||r(e.itemName)!==r(t[n].nombre||t[n].descripcion)||Number(e.quantityItem)!==Number(t[n].cantidad??t[n].quantity)||!Number.isFinite(Number(e.itemAmount))||Number(e.itemAmount)<0||![1,2,3,4].includes(Number(e.billingIndicator))))return null;let i=t.map(()=>0);for(let t of[1,2,3]){let r=n.flatMap((e,n)=>Number(e.billingIndicator)===t?[n]:[]);if(!r.length)continue;let a=e.totals?.[`itbis${t}Total`]??(t===3?0:null);if(a==null||a===``||!Number.isFinite(Number(a))||Number(a)<0)return null;let o=Math.round(Number(a)*100),s=r.reduce((e,t)=>e+Number(n[t].itemAmount),0);if(!s&&o)return null;let c=0,l=0;for(let e of r){c+=Number(n[e].itemAmount);let t=s?Math.round(o*c/s):0;i[e]=(t-l)/100,l=t}}return Math.round(i.reduce((e,t)=>e+t,0)*100)===Math.round(Number(e.totals?.itbisTotal)*100)?i:null}function j(e){return e?.data?.signature||e?.data?.request||e?.data||e}function M(e){return JSON.parse(JSON.stringify(e??{}))}function N(e){let t=j(e),n=String(t?.url||``).trim();if(!/^https:\/\//i.test(n))throw Error(`El servidor no devolvió un enlace HTTPS de firma válido`);return{uid:String(t?.uid||``),url:n,expiresAt:t?.expires_at||t?.expiresAt||null,status:t?.status===`signed`?`signed`:`pending`}}function P(e){let t=j(e),n=[`none`,`pending`,`signed`,`stale`,`expired`,`unavailable`].includes(t?.status)?t.status:`none`,r=String(t?.signature_data_uri||t?.signatureDataUri||``).trim();return{status:n,signerName:String(t?.signer_name||t?.signerName||``).trim(),signedAt:t?.signed_at||t?.signedAt||null,imageDataUri:n===`signed`&&/^data:image\/png;base64,[A-Za-z0-9+/=]+$/.test(r)?r:null}}function F(e,t){return[`Hola ${e?.nombre_cliente||`cliente`},`,`Por favor revisa y firma la factura ${e?.no_factura||``} por RD$${Number(e?.total||0).toFixed(2)}.`,`Abre este enlace seguro para firmar:`,t,``,`La factura mostrará tu firma después de confirmarla.`].join(`
`)}async function I(e){let t=String(e?.uid||``).trim();if(!t)throw Error(`La factura aún no tiene UID de sincronización`);if(window.__isServerSystem||window.__isElectron){let n=await window.electron.invoke(`facturas:crearEnlaceFirma`,{record_uid:t,invoice:M(e)});if(n?.success===!1)throw Error(n.error||`No se pudo crear el enlace de firma`);return N(n)}await p(!0);let n=f();if(!n?.url||!n.key)throw Error(`TM Cloud no está configurado para emitir solicitudes de firma`);let r=await fetch(`${n.url}/invoices/${encodeURIComponent(t)}/signature-request`,{method:`POST`,headers:m(n.key,!0),body:JSON.stringify({expires_at:new Date(Date.now()+6048e5).toISOString().replace(`T`,` `).slice(0,19)})});if(!r.ok)throw Error(await g(r));return N(await r.json())}async function L(e){let t=String(e?.uid||``).trim();if(!t)return{status:`none`,signerName:``,signedAt:null,imageDataUri:null};if(window.__isServerSystem||window.__isElectron){let n=await window.electron.invoke(`facturas:obtenerFirma`,{record_uid:t,invoice:M(e)});if(n?.success===!1)throw Error(n.error||`No se pudo consultar la firma`);return P(n)}await p(!0);let n=f();if(!n?.url||!n.key)throw Error(`TM Cloud no está configurado para consultar firmas`);let r=await fetch(`${n.url}/invoices/${encodeURIComponent(t)}/signature`,{headers:m(n.key)});if(!r.ok)throw Error(await g(r));return P(await r.json())}var R={style:{display:`none`}},z={class:`flex flex-col h-full gap-3`},B=[`src`,`title`],V=u({__name:`FacturaPdfPrint`,setup(e,{expose:u}){function f(e){let t=w(),n=V(e?.otro,{}),r=e.banco_nombre||n?.banco_nombre||``,i=[],a=Number(e.efectivo||0);a>0&&i.push({tipo:`efectivo`,etiqueta:t.cash,monto:a});let o=V(e.tarjeta_mixta,e.tarjeta_mixta),s=o&&typeof o==`object`?o:n?.tarjeta_mixta||{},c=Number(e.tarjeta||s?.total||Number(s?.monto_base||0)+Number(s?.monto_comision||0));c>0&&i.push({tipo:`tarjeta`,etiqueta:t.card,monto:c});let l=V(e.transferencias_mixtas,e.transferencias_mixtas),u=V(n?.transferencias_mixtas,n?.transferencias_mixtas),d=Array.isArray(l)?l:Array.isArray(u)?u:Number(e.transferencia)>0?[{monto:e.transferencia,banco_nombre:r}]:[];for(let e of d){let n=Number(e?.monto||0);if(n<=0)continue;let r=e?.banco_nombre?` (${e.banco_nombre})`:``;i.push({tipo:`transferencia`,etiqueta:`${t.transfer}${r}`,monto:n})}if(Number(e.cheque)>0&&i.push({tipo:`cheque`,etiqueta:t.check,monto:Number(e.cheque)}),a<=0){let n=_(e),r=i.reduce((e,t)=>e+t.monto,0),a=Math.round((n-r)*100)/100;a>.009&&i.unshift({tipo:`efectivo`,etiqueta:t.cash,monto:a})}return i}function m(e){let t=w(),n=String(e.metodo_pago||``).toUpperCase(),r=V(e?.otro,{}),i=e.banco_nombre||r?.banco_nombre||``;if(n!==`MIXTO`){let t=O(e.metodo_pago);return i?`${t} - ${i}`:t}return t.mixed}function g(e){let t=w(),n={efectivo:t.cash,tarjeta:t.card,transferencia:t.transfer,cheque:t.check},r=[...new Set(f(e).map(e=>n[e.tipo].toUpperCase()))];return r.length?`${r.join(` Y `)} - ${t.mixed}`:t.mixed}let S=E,j=y();ne();let M=n(!1),N=n(!1),P=n(``),F=n(``),I=n(``);function V(e,t){if(e==null)return t;if(typeof e==`string`)try{return JSON.parse(e)}catch{return t}return e}function H(e,t=0){let n=Number(e);return Number.isFinite(n)?n:t}function U(e,t={}){let n=V(e?.otro,{}),r=n?.alanube_response||e?.alanube_response||{},i=V(t?.payload,null)||V(n?.alanube_payload||e?.alanube_payload,{});return{payload:i,totals:A(e,i),documentStampUrl:t?.document_stamp_url||e?.document_stamp_url||e?.documentStampUrl||n?.documentStampUrl||n?.document_stamp_url||r?.documentStampUrl||r?.document_stamp_url||``,securityCode:t?.security_code||e?.codigo_seguridad||e?.securityCode||n?.securityCode||n?.security_code||r?.securityCode||r?.security_code||``,legalStatus:t?.legal_status||e?.alanube_legal_status||r?.legalStatus||n?.legalStatus||``,status:t?.status||e?.alanube_status||r?.status||n?.status||``}}async function me(e){if(e?.id)try{let t=await window.db.getWhere(`facturas_ecf`,`factura_id = ?`,[e.id]),n=t?.success&&Array.isArray(t.data)?t.data.find(t=>!e.ncf||t.ncf===e.ncf):null;if(n)return U(e,n)}catch{}return U(e)}function he(e,t=``){let n=String(t||``).trim().match(/^(\d{1,2}:\d{2})/),r=String(e||``).trim(),i=r.match(/(?:T|\s)(\d{1,2}:\d{2})/),a=n?n[1].padStart(5,`0`):i?i[1].padStart(5,`0`):``,o=r.match(/^(\d{4})-(\d{2})-(\d{2})/),s=r.match(/^(\d{2})\/(\d{2})\/(\d{4})/);if(o)return`${o[3]}/${o[2]}/${o[1]}${a?` ${a}`:``}`;if(s)return`${s[1]}/${s[2]}/${s[3]}${a?` ${a}`:``}`;let c=e instanceof Date?e:new Date(e);if(Number.isNaN(c.getTime()))return a;let l=`${String(c.getDate()).padStart(2,`0`)}/${String(c.getMonth()+1).padStart(2,`0`)}/${c.getFullYear()}`,u=`${String(c.getHours()).padStart(2,`0`)}:${String(c.getMinutes()).padStart(2,`0`)}`;return`${l} ${a||u}`}function ge(e){return[e?.imei,e?.lista_imei,e?.imeis,e?.serial,e?.seriales].flatMap(e=>Array.isArray(e)?e:typeof e==`string`?e.split(`,`):e?[e]:[]).map(e=>typeof e==`object`?String(e.imei||e.serial||``).trim():String(e).trim()).filter(Boolean).filter((e,t,n)=>n.indexOf(e)===t)}function _e(e){for(let t of[`codigo`,`codigo_barra`,`cod_producto`,`codigo_producto`,`codigo_barras`,`sku`,`referencia`,`barcode`,`code`,`imei`,`serial`,`accesorio_id`,`pieza_id`,`telefono_id`,`imei_id`,`serial_id`,`servicio_id`,`electrodomestico_id`,`producto_id`,`id`]){let n=e?.[t];if(typeof n!=`string`&&typeof n!=`number`)continue;let r=String(n).trim();if(r&&!((t.endsWith(`_id`)||t===`id`)&&Number(r)===0))return r}return``}function W(e,t=``){let n=String(e??``).trim();return n?/^(data:|https?:\/\/|file:|blob:)/i.test(n)?n:n.startsWith(`/`)?t?`${t.replace(/\/$/,``)}${n}`:n:t?`${t.replace(/\/$/,``)}/${n.replace(/^\.\//,``)}`:n:``}function G(e){let t=new Uint8Array(e),n=``,r=32768;for(let e=0;e<t.length;e+=r)n+=String.fromCharCode(...t.subarray(e,e+r));return btoa(n)}async function ve(e,t=``){let n=String(e??``).trim();if(!n)return``;let r=W(n,t);if(/^data:/i.test(r))return r;if(!/^(https?:\/\/|file:|blob:|\/)/i.test(n))try{await p();let e=d(n),t=h()?.key||``;if(e){let n=await fetch(e,{headers:t?{Authorization:`Bearer ${t}`}:{}});if(n.ok)return`data:${n.headers.get(`content-type`)||`image/png`};base64,${G(await n.arrayBuffer())}`}}catch{}return r}async function ye(e){try{let t=await window.db.getAll(`empresa`);if(t.success&&t.data?.length>0)return T(e,t.data)}catch{}return{}}async function be(e){try{let t=await window.db.getAll(`clientes`);return!t.success||!Array.isArray(t.data)?{}:D(e,t.data)}catch{return{}}}function xe(e,t={}){return{nombre:e.nombre_cliente||e.cliente||e.comprador||t?.nombre||`CONSUMIDOR FINAL`,telefono:e.telefono_cliente||e.telefono||e.whatsapp||t?.telefono||t?.whatsapp||``,documento:e.rnc_cliente||e.cedula_cliente||e.rnc||e.cedula||t?.rnc||t?.cedula||``,direccion:e.direccion_cliente||e.direccion||t?.direccion||``}}function Se(e){return String(e.nota||e.observacion||e.observaciones||e.nota_factura||e.comentario||``).trim()}async function Ce(){try{return await k(`datosarchivo`)}catch{return{}}}function we(e,t,n,r){let i=Number(e);return Number.isFinite(i)?Math.min(r,Math.max(n,i)):t}function K(e){return String(e||``).replace(/[&<>"']/g,e=>({"&":`&amp;`,"<":`&lt;`,">":`&gt;`,'"':`&quot;`,"'":`&#39;`})[e]||e)}async function q({factura:e,cliente:t=null,datosEmpresa:n=null,signature:r=null}){let i=w(),a=ce(e),o=ne(a?`HN`:void 0),s=e=>a?`HNL ${Number(e||0).toFixed(2)}`:te(e),c=S(e),l=C(e),u=c?i.quote:i.invoice,d=(await Ce())?.VITE_LINKURL||``,p=n?.empresa||n?.datosEmpresa?.empresa||e.empresa,h=p?{...p}:await ye(e);a&&Object.assign(h,{nombre:a.legalName,legal:a.taxpayerId,rnc:a.taxpayerId,direccion:a.address}),p&&p.marca_registrada==null&&(h.marca_registrada=(await ye(e)).marca_registrada||``);let v=t||await be(e),y=ie({factura:e,empresa:h,cliente:v}),b={...xe(e,v),...y.customer};a&&Object.assign(b,{nombre:a.customerName||e.nombre_cliente,documento:a.customerId}),b.nombre=ae(b.nombre);let x=(Se(e)||(c?``:i.thanks)).replace(/\n/g,`<br>`),T=y.items,E=await re(),D=new Set([`item_name`,`description`,`quantity`,`price`,`line_total`,`customer_name`,`customer_tax_id`,`subtotal`,`tax`,`total`,`discount`,`fiscal`]),O=e=>!!(a&&D.has(e))||E.visibility?.[e]!==!1,k=(e,t)=>O(e)?t:``,A=[`item_name`,`description`,`identifiers`].some(O),j=E.show_company_signature&&E.representative_signature?`<div class="client-signature company-signature"><img src="${E.representative_signature}" alt="Firma del representante"><div class="signature-line"><strong>${K(E.representative_name)}</strong>${i.language===`en`?`Company representative`:`Representante de la empresa`}</div></div>`:``,M=E.show_logo?await ve(h?.logoprinter||h?.logo,d):``,N={factura_logo_ancho:E.logo_width,factura_logo_alto:E.logo_height},P=we(N.factura_logo_ancho,150,30,400),F=we(N.factura_logo_alto,90,20,250),I=c?U({}):await me(e),L=I.documentStampUrl||`${d||`https://tmposrd.com`}/receipt/factura?factura=${l}`,R=c?``:e.qr||``;try{!c&&!R&&(R=await fe.toDataURL(L))}catch{}let z=I.totals&&Array.isArray(T)?pe(I.payload,T):null,B=Array.isArray(T)?T.map((e,t)=>{let n=H(e.cantidad??e.quantity,0),r=H(e.precio_final??e.precio_venta??e.precio_unitario??e.precio,0),i=H(e.precio_normal??e.precio_lista??e.precio_venta_normal,r),a=H(e.descuento,0),o=H(e.impuesto_venta??e.impuesto,0),s=H(e.total,r*n-a),c=i>0&&r>=0&&r<i;return{...e,codigoProducto:_e(e),cantidad:n,precioUnidad:r,precioNormal:i,descuento:a,tieneDescuentoProducto:c,impuestoTotal:z?.[t]??o*n,totalProducto:s,imeis:ge(e),detallesImei:oe(e)}}):[],W=a?.tax??I.totals?.tax??(y.totals.tax||B.reduce((e,t)=>e+t.impuestoTotal,0));if(!z&&y.taxMode===1&&W>0&&B.length&&B.every(e=>e.impuestoTotal===0)){let e=le(W,B.map(e=>e.totalProducto));B.forEach((t,n)=>{t.impuestoTotal=e[n]})}let G=H(e.descuento)>0||B.some(e=>e.descuento>0),q=H(e.total),J=c?0:ee(e),Y=J>0,X=Number((e.salon_deposit||V(e.otro,{})?.salon_deposit)?.amount||0),Z=!c&&Number.isFinite(X)&&X>0&&X<=q,Te=V(e.otro,{}),Q=Te?.cobro_caja||Te?.cobro_pos,Ee=!c&&Number(Q?.efectivo_recibido||0)>0,De=I.totals?.subtotal??y.totals.subtotal,$=!c&&String(e.metodo_pago||``).toUpperCase()===`MIXTO`,Oe=a?a.base+H(e.descuento):$&&!I.totals?q:De,ke=($?f(e):[]).map(e=>`<tr class="payment-row"><td>${e.etiqueta}</td><td class="text-right">${s(e.monto)}</td></tr>`).join(``),Ae=[`code`,`quantity`,`price`,`line_total`].filter(O).length+Number(A)+Number(O(`line_tax`))+Number(O(`line_discount`)&&G),je=he(e.fecha_emision||e.fecha||``,e.hora||``),Me=i.language===`en`?`CUSTOMER DETAILS`:`DATOS DEL CLIENTE`,Ne=c?i.language===`en`?`QUOTE SUMMARY`:`RESUMEN DE ${i.quote.toUpperCase()}`:i.language===`en`?`PAYMENT SUMMARY`:`RESUMEN DE PAGO`,Pe=S(e)?i.quote.toUpperCase():e.metodo_pago===`CREDITO`?i.creditInvoice:i.invoice.toUpperCase(),Fe=B.map(e=>{let t=y.taxMode===1&&e.impuestoTotal>0?Math.round((e.totalProducto-e.impuestoTotal)*100)/100:e.totalProducto,n=e.detallesImei.length?e.detallesImei.map(e=>`<div class="imei-line">IMEI: ${e}</div>`).join(``):e.imeis.length?`<div class="imei-line">IMEI: ${e.imeis.join(`, `)}</div>`:``,r=e.tieneDescuentoProducto?`<div class="discount-line">Normal: <span class="line-through">${s(e.precioNormal)}</span> &nbsp; Con descuento: <strong>${s(e.precioUnidad)}</strong></div>`:``;return`
      <tr class="invoice-line">
        ${k(`code`,`<td class="product-code">${K(e.codigoProducto)||`—`}</td>`)}
        ${A?`<td>
          ${k(`item_name`,`<div>${K(e.nombre||e.descripcion||``)}</div>`)}
          ${O(`description`)&&e.nombre&&String(e.descripcion||``).trim()&&String(e.descripcion).trim()!==String(e.nombre).trim()?`<div class="item-description">${K(e.descripcion).replace(/\r?\n/g,`<br>`)}</div>`:``}
          ${O(`price`)&&O(`line_discount`)?r:``}
          ${O(`identifiers`)?n:``}
        </td>`:``}
        ${k(`quantity`,`<td class="text-center">${e.cantidad}</td>`)}
        ${k(`price`,`<td class="text-right">${s(e.precioUnidad)}</td>`)}
        ${O(`line_tax`)?`<td class="text-right">${s(e.impuestoTotal)}</td>`:``}
        ${O(`line_discount`)&&G?`<td class="text-right">${s(e.descuento)}</td>`:``}
        ${k(`line_total`,`<td class="text-right"><strong>${s(t)}</strong></td>`)}
      </tr>
    `}).join(``),Ie=Array.from({length:Math.max(0,8-B.length)},()=>`<tr class="invoice-line empty-row">${Array.from({length:Ae},()=>`<td>&nbsp;</td>`).join(``)}</tr>`).join(``);return`<!DOCTYPE html>
<html lang="${i.language}">
<head>
  <meta charset="UTF-8">
  <title>${u} ${l}</title>
  <style>
    * { box-sizing: border-box; }
    @page { size: letter; margin: 10mm; }
    :root { --navy: ${E.heading_color}; --blue: ${E.primary_color}; --blue-soft: #eaf4f9; --slate: #52667a; --line: #d8e2ea; --surface: #f7fafc; }
    body { margin: 0; background: #fff; color: #172b3a; font-family: "Segoe UI", Arial, Helvetica, sans-serif; font-size: 11px; }
    .page { position: relative; width: 100%; max-width: 760px; margin: 0 auto; padding: 18px 20px 14px; border-top: 6px solid var(--blue); }
    .header { display: flex; justify-content: space-between; align-items: stretch; gap: 28px; padding-bottom: 16px; border-bottom: 1px solid var(--line); }
    .company { flex: 1; min-width: 0; display: flex; align-items: center; gap: 14px; line-height: 1.45; }
    .company img { max-width: ${P}px; max-height: ${F}px; object-fit: contain; flex: 0 0 auto; }
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
    .totals .credit-row td { color: #066487; font-weight: 700; background: #f2f9fc; }
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
        ${M?`<img src="${M}" alt="Logo">`:``}
        <div class="company-copy">
          ${k(`company_name`,`<div class="company-name">${h.nombre||i.company}</div>`)}
          <div class="company-meta">
            ${String(h.marca_registrada||``).trim()?`<div class="company-trademark">${K(String(h.marca_registrada).trim())}</div>`:``}
            ${O(`company_tax_id`)&&(h.legal||h.rnc)?`${o.businessIdLabel}: ${h.legal||h.rnc}<br>`:``}
            ${O(`company_phone`)&&h.telefono?`Tel: ${h.telefono}`:``}${O(`company_email`)&&h.email?`${O(`company_phone`)&&h.telefono?` &nbsp;|&nbsp; `:``}${h.email}<br>`:`<br>`}
            ${k(`company_address`,`${h.direccion||``}`)}
          </div>
        </div>
      </div>

      <div class="invoice-box">
        <div class="document-heading">
          ${k(`document_title`,`<div class="document-type">${Pe}</div>`)}
          ${ue(e)}
          ${O(`fiscal`)&&!S(e)&&de(e)?`<div class="document-type">${de(e).toUpperCase()}</div>`:``}
          ${k(`document_number`,`<div class="document-number">${l}</div>`)}
        </div>
        <table>
          ${k(`date`,`<tr><td><strong>${i.date}</strong></td><td class="text-right">${je}</td></tr>`)}
          ${!O(`fiscal`)||S(e)||!(e.ncf||e.comprobante)?``:`<tr><td><strong>${a?`Número de factura`:o.fiscalDocumentLabel}</strong></td><td class="text-right">${e.ncf||e.comprobante||``}</td></tr>`}
        </table>

        ${O(`document_title`)&&O(`document_number`)&&O(`payment`)?`        <div class="invoice-title">${S(e)?`${i.quote.toUpperCase()} #${l}`:e.metodo_pago===`CREDITO`?`${i.creditInvoice} #${l}`:$?g(e):`${i.invoice.toUpperCase()} #${l}`}</div>`:``}
        ${O(`validity`)&&S(e)?`<div style="text-align:center;margin-top:8px;font-size:10px;color:#666;font-style:italic">${i.quoteValidity.replace(`30`,String(E.quote_validity_days))}</div>`:``}
      </div>
    </div>

    ${[`customer_name`,`customer_phone`,`customer_tax_id`,`customer_email`,`customer_address`,`payment`,`qr`].some(O)?`    <div class="client-box">
      <div class="client-data">
        <div class="section-label">${Me}</div>
        <div class="client-grid">
          ${k(`customer_name`,`<p><strong>${i.customer}:</strong><br>${K(b.nombre||i.unregistered)}</p>`)}
          ${k(`customer_phone`,`<p><strong>${i.phone}:</strong><br>${K(b.telefono||i.notApplicable)}</p>`)}
          ${k(`customer_tax_id`,`<p><strong>${o.customerIdLabel.toUpperCase()}:</strong><br>${K(b.documento||i.notApplicable)}</p>`)}
          ${c||!O(`payment`)?``:`<p><strong>${i.paymentMethod}:</strong><br><span class="payment-detail">${m(e)}</span></p>`}
          ${k(`customer_email`,`<p><strong>EMAIL:</strong><br>${K(b.email||i.notApplicable)}</p>`)}
          ${k(`customer_address`,`<p class="client-wide"><strong>${i.address}:</strong><br>${K(b.direccion||i.notApplicable)}</p>`)}
        </div>
      </div>
${k(`qr`,`      <div class="qr">
        ${R?`<img src="${R}" alt="QR">`:``}
        ${I.securityCode?`<div style="font-size:9px;font-weight:700;text-align:center;margin-top:4px">${i.securityCode}: ${I.securityCode}</div>`:``}
      </div>`)}
    </div>

`:``}
    ${Ae?`    <table class="products">
      <thead>
        <tr>
          ${k(`code`,`<th>${i.code}</th>`)}
          ${A?`<th>${i.description}</th>`:``}
          ${k(`quantity`,`<th>${i.quantity}</th>`)}
          ${k(`price`,`<th>${i.unitPrice}</th>`)}
          ${O(`line_tax`)?`<th>${o.shortName}</th>`:``}
          ${O(`line_discount`)&&G?`<th>${i.discount}</th>`:``}
          ${k(`line_total`,`<th>${i.subtotal}</th>`)}
        </tr>
      </thead>
      <tbody>
        ${Fe}
        ${Ie}
      </tbody>
    </table>`:``}

${c?``:se(e,i.language)}
${Y&&!O(`total`)?`<div class="note"><strong>Nota de crédito aplicada:</strong> ${s(J)}<br><strong>Total a pagar:</strong> ${s(_(e))}</div>`:``}
${Z?`<div class="note salon-deposit"><strong>Anticipo aplicado:</strong> ${s(X)}<br><strong>Saldo cobrado:</strong> ${s(_(e))}</div>`:``}
${k(`notes`,`    <div class="note"><strong>${i.observation}:</strong><br>${x}</div>`)}

    <div class="bottom">
      ${c&&!j?``:`<div class="signatures">
        ${j||(O(`delivered_by`)?`<div class="signature-line"><strong>${i.deliveredBy}</strong>${e.usuario||e.cajero||i.user}</div>`:``)}
        ${c||!O(`customer_signature`)?``:`
        <div class="client-signature">${r?.status===`signed`&&r.imageDataUri?`<img src="${r.imageDataUri}" alt="Firma del cliente">`:``}<div class="signature-line"><strong>${i.receivedBy}</strong>${r?.status===`signed`&&r.imageDataUri?K(r.signerName||b.nombre||i.unregistered):b.nombre||i.unregistered}${r?.status===`signed`&&r.imageDataUri&&r.signedAt?`<small>Firmado: ${K(r.signedAt)}</small>`:``}</div></div>
        `}
      </div>`}

      ${[`subtotal`,`tax`,`discount`,`payment`,`total`].some(O)?`      <div class="totals">
        <div class="totals-title">${Ne}</div>
        <table>
          ${k(`subtotal`,`<tr><td>${i.subtotal}</td><td class="text-right">${s(Oe)}</td></tr>`)}
          ${O(`tax`)?`<tr><td>${o.shortName}</td><td class="text-right">${s(W)}</td></tr>`:``}
          ${O(`discount`)&&G?`<tr><td>${i.discount}</td><td class="text-right">${s(e.descuento)}</td></tr>`:``}
          ${O(`payment`)?ke:``}
          ${k(`total`,Y||Z?`<tr><td>${i.language===`en`?`INVOICE TOTAL`:`TOTAL FACTURA`}</td><td class="text-right">${s(e.total)}</td></tr>
          ${Y?`<tr class="credit-row"><td>${i.language===`en`?`CREDIT NOTE APPLIED`:`NOTA DE CRÉDITO APLICADA`}</td><td class="text-right">-${s(J)}</td></tr>`:``}
          ${Z?`<tr class="credit-row"><td>${i.language===`en`?`DEPOSIT APPLIED`:`ANTICIPO APLICADO`}</td><td class="text-right">-${s(X)}</td></tr>`:``}
          <tr><td>${i.language===`en`?`TOTAL TO PAY`:`TOTAL A PAGAR`}</td><td class="text-right">${s(_(e))}</td></tr>`:`<tr><td>${i.total}</td><td class="text-right">${s(e.total)}</td></tr>`)}
          ${O(`total`)&&Ee?`<tr><td>${i.paidWith}</td><td class="text-right">${s(Q.efectivo_recibido)}</td></tr>
          <tr><td>${i.change}</td><td class="text-right">${s(Q.cambio)}</td></tr>`:``}
        </table>
      </div>`:``}
    </div>

    ${k(`footer`,`<div class="footer"><span>${O(`company_name`)?h.nombre||i.company:``}</span><span>${O(`document_title`)?u:``} ${O(`document_number`)?l:``}</span></div>`)}

  </div>
</body>
</html>`}async function J(e,t){P.value&&URL.revokeObjectURL(P.value),P.value=e,F.value=t,M.value=!0}function Y(){P.value&&URL.revokeObjectURL(P.value),P.value=``,F.value=``,M.value=!1}async function X(){if(!P.value)return;let e=`data:application/pdf;base64,${G(await(await(await fetch(P.value)).blob()).arrayBuffer())}`,t=await window.electron.invoke(`save:pdf`,e,F.value);t.success?j.add({severity:`success`,summary:`Guardado`,detail:`PDF descargado`,life:2e3}):j.add({severity:`error`,summary:`Error`,detail:t.error||`No se pudo guardar el PDF`,life:3e3})}async function Z(e){N.value=!0;try{let t=null;if(!S(e)&&e?.uid)try{t=await L(e)}catch{j.add({severity:`warn`,summary:`Firma no disponible`,detail:`El PDF se generará sin firma porque no se pudo consultar el servidor.`,life:4500})}let n=await q({factura:e,signature:t});I.value=S(e)?w().quote:w().invoice;let r=`${S(e)?`Cotizacion`:`Factura`}_${C(e)||`sin_numero`}.pdf`,i=await window.electron.invoke(`generate:pdf`,n,r);if(i.success&&i.dataUrl){let e=await(await fetch(i.dataUrl)).blob();await J(URL.createObjectURL(e),r)}else j.add({severity:`error`,summary:`Error`,detail:i.error||`No se pudo generar el PDF`,life:3e3})}catch(e){j.add({severity:`error`,summary:`Error`,detail:e.message||`Error al generar PDF`,life:3e3})}finally{N.value=!1}}return u({printFactura:Z,generateFacturaHtml:q}),(e,n)=>(i(),o(c,null,[a(`div`,R,[s(t(b))]),s(t(v),{visible:M.value,"onUpdate:visible":n[0]||=e=>M.value=e,header:`Vista Previa - ${I.value} PDF`,modal:``,style:{width:`80vw`,height:`90vh`},draggable:!1,onHide:Y},{footer:r(()=>[s(t(x),{label:`Cerrar`,severity:`secondary`,text:``,onClick:Y}),s(t(x),{label:`Descargar PDF`,icon:`pi pi-download`,onClick:X})]),default:r(()=>[a(`div`,z,[P.value?(i(),o(`iframe`,{key:0,src:P.value,class:`w-full flex-1 border-0 rounded-lg`,style:{"min-height":`70vh`},title:`${I.value} PDF`},null,8,B)):l(``,!0)])]),_:1},8,[`visible`,`header`])],64))}});export{L as i,F as n,I as r,V as t};