import{a as e,c as t,d as n,handleElectronInvoke as r,i,initLicencia as a,l as o,n as s,o as c,r as l,s as u,t as d,u as f}from"./capacitorElectron-Dhz6s6nr.js";async function p(){console.log(`[Capacitor] Inicializando app para Android...`),m(),await n(),window.db||(window.db={getAll:t=>Promise.resolve(e(t)),getWhere:(e,n,r=[])=>Promise.resolve(t(e,n,r)),getModified:(e,t)=>Promise.resolve(u(e,t)),getById:(e,t)=>Promise.resolve(c(e,t)),insert:(e,t)=>Promise.resolve(o(e,t)),update:(e,t,n)=>Promise.resolve(f(e,t,n)),delete:(e,t)=>{let n=``;try{n=localStorage.getItem(`mr_user_usuario`)||``}catch{}return Promise.resolve(l(e,t,n))},bitacoraList:e=>Promise.resolve(s(e)),bitacoraDeleteAll:()=>Promise.resolve(d())}),window.config||(window.config={get:e=>Promise.resolve({success:!0,data:localStorage.getItem(`config_${e}`)||``}),set:(e,t)=>(localStorage.setItem(`config_${e}`,String(t??``)),Promise.resolve({success:!0}))}),window.electron||(window.electron={invoke:(e,...t)=>e===`consultaservidor`&&t[0]===`executeSQL`?Promise.resolve(i(t[1])):r(e,...t),send:()=>{},on:()=>{}}),await a(),window.__isElectron=!1,window.__isCapacitor=!0,g(),console.log(`[Capacitor] App inicializada correctamente`)}function m(){typeof Object.hasOwn!=`function`&&(Object.hasOwn=(e,t)=>Object.prototype.hasOwnProperty.call(e,t))}function h(){if(document.documentElement.classList.add(`capacitor-android`),document.getElementById(`android-webview-compatibility`))return;let e=document.createElement(`style`);e.id=`android-webview-compatibility`,e.textContent=`
    .capacitor-android *,
    .capacitor-android *::before,
    .capacitor-android *::after {
      animation: none !important;
      filter: none !important;
      -webkit-backdrop-filter: none !important;
      backdrop-filter: none !important;
    }

    .capacitor-android .p-dialog-mask {
      position: fixed !important;
      inset: 0 !important;
      z-index: 1100 !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      padding: 1rem !important;
      background: rgba(2, 6, 23, 0.82) !important;
    }

    .capacitor-android .p-dialog {
      display: flex !important;
      flex-direction: column !important;
      max-width: calc(100vw - 2rem) !important;
      max-height: calc(100vh - 2rem) !important;
      overflow: hidden !important;
      color: #f8fafc !important;
      background: #111827 !important;
      border: 1px solid rgba(148, 163, 184, 0.38) !important;
      border-radius: 1rem !important;
      box-shadow: 0 24px 64px rgba(0, 0, 0, 0.72) !important;
    }

    .capacitor-android .p-dialog-header {
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      flex: 0 0 auto !important;
      padding: 1rem 1.25rem !important;
      background: #172033 !important;
      border-bottom: 1px solid rgba(148, 163, 184, 0.24) !important;
    }

    .capacitor-android .p-dialog-title {
      color: #f8fafc !important;
      font-size: 1rem !important;
      font-weight: 700 !important;
    }

    .capacitor-android .p-dialog-header-actions {
      display: flex !important;
      align-items: center !important;
      gap: 0.35rem !important;
    }

    .capacitor-android .p-dialog-close-button {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      width: 2rem !important;
      height: 2rem !important;
      color: #cbd5e1 !important;
      background: rgba(255, 255, 255, 0.06) !important;
      border: 1px solid rgba(148, 163, 184, 0.25) !important;
      border-radius: 0.5rem !important;
    }

    .capacitor-android .p-dialog-content,
    .capacitor-android .license-dialog-content {
      flex: 1 1 auto !important;
      min-height: 0 !important;
      overflow: auto !important;
      padding: 1.25rem !important;
      color: #e2e8f0 !important;
      background: #111827 !important;
    }

    .capacitor-android .p-dialog-footer {
      display: flex !important;
      justify-content: flex-end !important;
      align-items: center !important;
      gap: 0.65rem !important;
      flex: 0 0 auto !important;
      padding: 0.9rem 1.25rem !important;
      background: #172033 !important;
      border-top: 1px solid rgba(148, 163, 184, 0.24) !important;
    }

    .capacitor-android .p-button {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 0.5rem !important;
      min-height: 2.5rem !important;
      padding: 0.6rem 1rem !important;
      color: #ffffff !important;
      background: #2563eb !important;
      border: 1px solid #3b82f6 !important;
      border-radius: 0.625rem !important;
      font-weight: 600 !important;
    }

    .capacitor-android .p-button.p-button-text {
      color: #cbd5e1 !important;
      background: transparent !important;
      border-color: rgba(148, 163, 184, 0.3) !important;
    }

    .capacitor-android .p-button.p-button-secondary {
      color: #e2e8f0 !important;
      background: #334155 !important;
      border-color: #475569 !important;
    }

    .capacitor-android .p-button.p-button-danger {
      color: #ffffff !important;
      background: #dc2626 !important;
      border-color: #ef4444 !important;
    }

    .capacitor-android .p-button.p-button-success {
      color: #ffffff !important;
      background: #059669 !important;
      border-color: #10b981 !important;
    }

    .capacitor-android .p-button.p-button-warning {
      color: #111827 !important;
      background: #f59e0b !important;
      border-color: #fbbf24 !important;
    }

    .capacitor-android .p-button.p-button-outlined {
      color: #93c5fd !important;
      background: transparent !important;
      border-color: #3b82f6 !important;
    }

    .capacitor-android .p-button:disabled {
      cursor: not-allowed !important;
      opacity: 0.5 !important;
    }

    .capacitor-android .p-inputtext,
    .capacitor-android .p-textarea {
      width: 100%;
      min-height: 2.6rem !important;
      padding: 0.65rem 0.8rem !important;
      color: #f8fafc !important;
      background: #1f2937 !important;
      border: 1px solid #475569 !important;
      border-radius: 0.625rem !important;
    }

    .capacitor-android .p-inputnumber {
      display: inline-flex !important;
      width: 100% !important;
    }

    .capacitor-android .p-inputnumber-input {
      width: 100% !important;
      min-width: 0 !important;
    }

    .capacitor-android .p-select {
      position: relative !important;
      display: inline-flex !important;
      align-items: center !important;
      width: 100% !important;
      min-height: 2.6rem !important;
      color: #f8fafc !important;
      background: #1f2937 !important;
      border: 1px solid #475569 !important;
      border-radius: 0.625rem !important;
      overflow: hidden !important;
    }

    .capacitor-android .p-select-label {
      display: flex !important;
      align-items: center !important;
      flex: 1 1 auto !important;
      min-width: 0 !important;
      padding: 0.65rem 0.8rem !important;
      color: #f8fafc !important;
      white-space: nowrap !important;
      text-overflow: ellipsis !important;
      overflow: hidden !important;
    }

    .capacitor-android .p-select-dropdown {
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      flex: 0 0 2.5rem !important;
      width: 2.5rem !important;
      color: #cbd5e1 !important;
      align-self: stretch !important;
      border-left: 1px solid #475569 !important;
    }

    .capacitor-android .p-select-overlay,
    .capacitor-android .p-popover,
    .capacitor-android .p-menu {
      position: absolute !important;
      z-index: 1200 !important;
      color: #f8fafc !important;
      background: #1f2937 !important;
      border: 1px solid #475569 !important;
      border-radius: 0.625rem !important;
      box-shadow: 0 18px 46px rgba(0, 0, 0, 0.65) !important;
      overflow: hidden !important;
    }

    .capacitor-android .p-select-header {
      padding: 0.65rem !important;
      background: #172033 !important;
      border-bottom: 1px solid #475569 !important;
    }

    .capacitor-android .p-select-list {
      list-style: none !important;
      margin: 0 !important;
      padding: 0.35rem !important;
    }

    .capacitor-android .p-select-option {
      display: flex !important;
      align-items: center !important;
      min-height: 2.5rem !important;
      padding: 0.55rem 0.75rem !important;
      color: #e2e8f0 !important;
      border-radius: 0.45rem !important;
    }

    .capacitor-android .p-select-option.p-select-option-selected,
    .capacitor-android .p-select-option[aria-selected='true'] {
      color: #ffffff !important;
      background: #2563eb !important;
    }

    .capacitor-android .p-selectbutton {
      display: inline-flex !important;
      align-items: stretch !important;
      padding: 0.2rem !important;
      background: #1f2937 !important;
      border: 1px solid #475569 !important;
      border-radius: 0.625rem !important;
    }

    .capacitor-android .p-selectbutton .p-togglebutton {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      min-height: 2.25rem !important;
      padding: 0.45rem 0.8rem !important;
      color: #cbd5e1 !important;
      background: transparent !important;
      border: 0 !important;
      border-radius: 0.45rem !important;
    }

    .capacitor-android .p-selectbutton .p-togglebutton.p-togglebutton-checked,
    .capacitor-android .p-selectbutton .p-togglebutton[aria-pressed='true'] {
      color: #ffffff !important;
      background: #2563eb !important;
    }

    .capacitor-android .p-datatable {
      width: 100% !important;
      color: #e2e8f0 !important;
      background: #111827 !important;
      border: 1px solid #334155 !important;
      border-radius: 0.75rem !important;
      overflow: hidden !important;
    }

    .capacitor-android .p-datatable-table {
      width: 100% !important;
      border-collapse: collapse !important;
    }

    .capacitor-android .p-datatable-thead > tr > th {
      padding: 0.7rem 0.8rem !important;
      color: #f8fafc !important;
      text-align: left !important;
      background: #1e293b !important;
      border-bottom: 1px solid #475569 !important;
    }

    .capacitor-android .p-datatable-tbody > tr > td {
      padding: 0.65rem 0.8rem !important;
      color: #e2e8f0 !important;
      background: #111827 !important;
      border-bottom: 1px solid #263244 !important;
    }

    .capacitor-android .p-paginator {
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 0.3rem !important;
      padding: 0.65rem !important;
      color: #cbd5e1 !important;
      background: #172033 !important;
      border-top: 1px solid #334155 !important;
    }

    .capacitor-android .p-paginator button {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      min-width: 2rem !important;
      min-height: 2rem !important;
      color: #cbd5e1 !important;
      background: #1f2937 !important;
      border: 1px solid #475569 !important;
      border-radius: 0.45rem !important;
    }
  `,document.head.appendChild(e)}async function g(){if(navigator.mediaDevices?.getUserMedia)try{(await navigator.mediaDevices.getUserMedia({video:{facingMode:`environment`}})).getTracks().forEach(e=>e.stop()),console.log(`[Permisos] Camara concedido`)}catch{console.log(`[Permisos] Camara no concedido (se pedira al escanear)`)}`Notification`in window&&Notification.permission===`default`&&Notification.requestPermission().then(e=>console.log(`[Permisos] Notificaciones:`,e))}export{h as applyAndroidRenderingCompatibility,p as initCapacitorApp};