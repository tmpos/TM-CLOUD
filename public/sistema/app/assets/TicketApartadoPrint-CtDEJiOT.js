import{N as e,d as t,g as n,nt as r,st as i,v as a}from"./runtime-core.esm-bundler-a6pTXghO.js";import{C as o,I as s,i as c}from"./index-BMrcxrjI.js";var l={style:{display:`none`}},u=a({__name:`TicketApartadoPrint`,setup(a,{expose:u}){let d=s(),f=r(``);function p(e){return c(e)}function m(e){if(!e)return``;let t=String(e).split(`T`)[0].split(`-`);return t.length===3?`${t[2]}/${t[1]}/${t[0]}`:e}function h(e){try{return Array.isArray(e?.pagos)?e.pagos:JSON.parse(e?.pagos||`[]`)}catch{return[]}}function g(e){return h(e).reduce((e,t)=>e+Number(t?.monto||0),0)}function _(e){return Math.max(0,Number(e?.total||0)-g(e))}function v(e,t=`apartado`,n){let r=h(e),i=t===`pago`?_(e)<=0?`APARTADO COMPLETADO`:`PAGO DE APARTADO`:`RECIBO DE APARTADO`,a=window.__empresaNombre||`MI EMPRESA`,o=window.__empresaDireccion||``,s=window.__empresaTelefono||``,c=String(e?.notas||``),l=c.match(/IMEI:\s*([^|]+)/i)?.[1]?.trim()||``,u=c.match(/MODELO:\s*([^|]+)/i)?.[1]?.trim()||``,d=n?`
      <div class="row strong"><span>Monto pagado</span><b>${p(n.monto)}</b></div>
      <div class="row"><span>Metodo</span><b>${n.metodo_pago||`N/A`}</b></div>
      ${n.referencia?`<div class="row"><span>Referencia</span><b>${n.referencia}</b></div>`:``}
      <div class="sep"></div>
    `:``,f=r.length?`
      <div class="section-title">Historial de pagos</div>
      ${r.map(e=>`
        <div class="payment">
          <span>${m(e.fecha)} ${e.metodo_pago||``}</span>
          <b>${p(e.monto)}</b>
        </div>
      `).join(``)}
      <div class="sep"></div>
    `:``;return`<!doctype html>
  <html>
  <head>
    <meta charset="utf-8">
    <title>${i} ${e?.no_factura||``}</title>
    <style>
      * { box-sizing: border-box; }
      body { margin: 0; padding: 10px; background: #fff; color: #111; font-family: "Courier New", monospace; }
      .ticket { width: 300px; margin: 0 auto; }
      .center { text-align: center; }
      .brand { font-size: 16px; font-weight: 800; letter-spacing: .4px; text-transform: uppercase; }
      .title { margin-top: 6px; font-size: 15px; font-weight: 800; text-transform: uppercase; }
      .muted { color: #444; font-size: 11px; }
      .sep { border-top: 1px dashed #111; margin: 8px 0; }
      .row { display: flex; justify-content: space-between; gap: 10px; font-size: 12px; line-height: 1.45; }
      .row span { color: #333; }
      .row b { text-align: right; }
      .strong { font-size: 13px; font-weight: 800; }
      .section-title { margin: 8px 0 4px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
      .payment { display: flex; justify-content: space-between; gap: 8px; font-size: 11px; padding: 2px 0; }
      .total-box { border: 1px solid #111; padding: 7px; margin: 8px 0; }
      .footer { margin-top: 10px; font-size: 11px; line-height: 1.4; }
      @media print { body { padding: 0; } .ticket { width: 300px; } }
    </style>
  </head>
  <body>
    <div class="ticket">
      <div class="center">
        <div class="brand">${a}</div>
        ${o?`<div class="muted">${o}</div>`:``}
        ${s?`<div class="muted">Tel: ${s}</div>`:``}
        <div class="sep"></div>
        <div class="title">${i}</div>
      </div>
      <div class="sep"></div>
      <div class="row"><span>No.</span><b>${e?.no_factura||e?.no_apartado||`-`}</b></div>
      <div class="row"><span>Fecha</span><b>${m(e?.fecha_venta||new Date().toISOString())}</b></div>
      <div class="row"><span>Cliente</span><b>${e?.nombre_cliente||`SIN CLIENTE`}</b></div>
      <div class="row"><span>Telefono</span><b>${e?.telefono_cliente||`N/A`}</b></div>
      <div class="sep"></div>
      ${u?`<div class="row"><span>Equipo</span><b>${u}</b></div>`:``}
      ${l?`<div class="row"><span>IMEI</span><b>${l}</b></div>`:``}
      ${d}
      <div class="total-box">
        <div class="row strong"><span>Total</span><b>${p(e?.total)}</b></div>
        <div class="row"><span>Abonado</span><b>${p(g(e))}</b></div>
        <div class="row strong"><span>Saldo</span><b>${p(_(e))}</b></div>
      </div>
      ${f}
      <div class="center footer">
        Gracias por su preferencia.<br>
        Este recibo confirma el movimiento registrado.
      </div>
    </div>
  </body>
  </html>`}async function y(e,t=`apartado`,n){try{let r=localStorage.getItem(`etiquetas_printer`);f.value=r||``;let i=await window.db.getAll(`impresoras_config`);i.success&&i.data?.length>0&&(f.value=i.data[0].printer_name||f.value);let a=await window.electron.invoke(`print:ticket`,v(e,t,n),f.value||void 0);a.success?d.add({severity:`success`,summary:`Imprimiendo recibo`,life:2e3}):d.add({severity:`error`,summary:`Error`,detail:a.error||`No se pudo imprimir`,life:3e3})}catch(e){d.add({severity:`error`,summary:`Error`,detail:e.message||`No se pudo imprimir`,life:3e3})}}return u({printTicket:y,buildTicketHtml:v}),(r,a)=>(e(),t(`div`,l,[n(i(o))]))}});export{u as t};