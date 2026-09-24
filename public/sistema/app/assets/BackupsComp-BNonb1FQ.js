import{A as e,dt as t,st as n,v as r}from"./reactivity.esm-bundler-C6Po7tbM.js";import{B as i,J as a,L as o,P as s,R as c,T as l,Y as u,c as d,d as f,g as p,h as m,j as h,l as g,s as _,u as v,v as y,z as b}from"./runtime-core.esm-bundler-EoPqhpgY.js";import{o as x,r as S,t as C,tt as w}from"./ripple-C4CLo5f9.js";import{C as T,V as E,_ as D,d as O,f as k,g as A,h as j,k as M,m as ee,p as te,u as ne,v as N,wt as P,y as F,yt as I,z as L}from"./index-CpmlcHQ0.js";import{n as R,t as z}from"./column-BUw_uYdC.js";import{t as B}from"./select-B10Yoqb3.js";import{t as V}from"./inputnumber-Bk5NDaSz.js";import{t as H}from"./useAccionesSensibles-CFyrFCRB.js";import{t as U}from"./tag-DSQkQL36.js";import{t as W}from"./fieldset-BMsMEzEj.js";var re=x.extend({name:`message`,style:`
    .p-message {
        display: grid;
        grid-template-rows: 1fr;
        border-radius: dt('message.border.radius');
        outline-width: dt('message.border.width');
        outline-style: solid;
    }

    .p-message-content-wrapper {
        min-height: 0;
    }

    .p-message-content {
        display: flex;
        align-items: center;
        padding: dt('message.content.padding');
        gap: dt('message.content.gap');
    }

    .p-message-icon {
        flex-shrink: 0;
    }

    .p-message-close-button {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-inline-start: auto;
        overflow: hidden;
        position: relative;
        width: dt('message.close.button.width');
        height: dt('message.close.button.height');
        border-radius: dt('message.close.button.border.radius');
        background: transparent;
        transition:
            background dt('message.transition.duration'),
            color dt('message.transition.duration'),
            outline-color dt('message.transition.duration'),
            box-shadow dt('message.transition.duration'),
            opacity 0.3s;
        outline-color: transparent;
        color: inherit;
        padding: 0;
        border: none;
        cursor: pointer;
        user-select: none;
    }

    .p-message-close-icon {
        font-size: dt('message.close.icon.size');
        width: dt('message.close.icon.size');
        height: dt('message.close.icon.size');
    }

    .p-message-close-button:focus-visible {
        outline-width: dt('message.close.button.focus.ring.width');
        outline-style: dt('message.close.button.focus.ring.style');
        outline-offset: dt('message.close.button.focus.ring.offset');
    }

    .p-message-info {
        background: dt('message.info.background');
        outline-color: dt('message.info.border.color');
        color: dt('message.info.color');
        box-shadow: dt('message.info.shadow');
    }

    .p-message-info .p-message-close-button:focus-visible {
        outline-color: dt('message.info.close.button.focus.ring.color');
        box-shadow: dt('message.info.close.button.focus.ring.shadow');
    }

    .p-message-info .p-message-close-button:hover {
        background: dt('message.info.close.button.hover.background');
    }

    .p-message-info.p-message-outlined {
        color: dt('message.info.outlined.color');
        outline-color: dt('message.info.outlined.border.color');
    }

    .p-message-info.p-message-simple {
        color: dt('message.info.simple.color');
    }

    .p-message-success {
        background: dt('message.success.background');
        outline-color: dt('message.success.border.color');
        color: dt('message.success.color');
        box-shadow: dt('message.success.shadow');
    }

    .p-message-success .p-message-close-button:focus-visible {
        outline-color: dt('message.success.close.button.focus.ring.color');
        box-shadow: dt('message.success.close.button.focus.ring.shadow');
    }

    .p-message-success .p-message-close-button:hover {
        background: dt('message.success.close.button.hover.background');
    }

    .p-message-success.p-message-outlined {
        color: dt('message.success.outlined.color');
        outline-color: dt('message.success.outlined.border.color');
    }

    .p-message-success.p-message-simple {
        color: dt('message.success.simple.color');
    }

    .p-message-warn {
        background: dt('message.warn.background');
        outline-color: dt('message.warn.border.color');
        color: dt('message.warn.color');
        box-shadow: dt('message.warn.shadow');
    }

    .p-message-warn .p-message-close-button:focus-visible {
        outline-color: dt('message.warn.close.button.focus.ring.color');
        box-shadow: dt('message.warn.close.button.focus.ring.shadow');
    }

    .p-message-warn .p-message-close-button:hover {
        background: dt('message.warn.close.button.hover.background');
    }

    .p-message-warn.p-message-outlined {
        color: dt('message.warn.outlined.color');
        outline-color: dt('message.warn.outlined.border.color');
    }

    .p-message-warn.p-message-simple {
        color: dt('message.warn.simple.color');
    }

    .p-message-error {
        background: dt('message.error.background');
        outline-color: dt('message.error.border.color');
        color: dt('message.error.color');
        box-shadow: dt('message.error.shadow');
    }

    .p-message-error .p-message-close-button:focus-visible {
        outline-color: dt('message.error.close.button.focus.ring.color');
        box-shadow: dt('message.error.close.button.focus.ring.shadow');
    }

    .p-message-error .p-message-close-button:hover {
        background: dt('message.error.close.button.hover.background');
    }

    .p-message-error.p-message-outlined {
        color: dt('message.error.outlined.color');
        outline-color: dt('message.error.outlined.border.color');
    }

    .p-message-error.p-message-simple {
        color: dt('message.error.simple.color');
    }

    .p-message-secondary {
        background: dt('message.secondary.background');
        outline-color: dt('message.secondary.border.color');
        color: dt('message.secondary.color');
        box-shadow: dt('message.secondary.shadow');
    }

    .p-message-secondary .p-message-close-button:focus-visible {
        outline-color: dt('message.secondary.close.button.focus.ring.color');
        box-shadow: dt('message.secondary.close.button.focus.ring.shadow');
    }

    .p-message-secondary .p-message-close-button:hover {
        background: dt('message.secondary.close.button.hover.background');
    }

    .p-message-secondary.p-message-outlined {
        color: dt('message.secondary.outlined.color');
        outline-color: dt('message.secondary.outlined.border.color');
    }

    .p-message-secondary.p-message-simple {
        color: dt('message.secondary.simple.color');
    }

    .p-message-contrast {
        background: dt('message.contrast.background');
        outline-color: dt('message.contrast.border.color');
        color: dt('message.contrast.color');
        box-shadow: dt('message.contrast.shadow');
    }

    .p-message-contrast .p-message-close-button:focus-visible {
        outline-color: dt('message.contrast.close.button.focus.ring.color');
        box-shadow: dt('message.contrast.close.button.focus.ring.shadow');
    }

    .p-message-contrast .p-message-close-button:hover {
        background: dt('message.contrast.close.button.hover.background');
    }

    .p-message-contrast.p-message-outlined {
        color: dt('message.contrast.outlined.color');
        outline-color: dt('message.contrast.outlined.border.color');
    }

    .p-message-contrast.p-message-simple {
        color: dt('message.contrast.simple.color');
    }

    .p-message-text {
        font-size: dt('message.text.font.size');
        font-weight: dt('message.text.font.weight');
    }

    .p-message-icon {
        font-size: dt('message.icon.size');
        width: dt('message.icon.size');
        height: dt('message.icon.size');
    }

    .p-message-sm .p-message-content {
        padding: dt('message.content.sm.padding');
    }

    .p-message-sm .p-message-text {
        font-size: dt('message.text.sm.font.size');
    }

    .p-message-sm .p-message-icon {
        font-size: dt('message.icon.sm.size');
        width: dt('message.icon.sm.size');
        height: dt('message.icon.sm.size');
    }

    .p-message-sm .p-message-close-icon {
        font-size: dt('message.close.icon.sm.size');
        width: dt('message.close.icon.sm.size');
        height: dt('message.close.icon.sm.size');
    }

    .p-message-lg .p-message-content {
        padding: dt('message.content.lg.padding');
    }

    .p-message-lg .p-message-text {
        font-size: dt('message.text.lg.font.size');
    }

    .p-message-lg .p-message-icon {
        font-size: dt('message.icon.lg.size');
        width: dt('message.icon.lg.size');
        height: dt('message.icon.lg.size');
    }

    .p-message-lg .p-message-close-icon {
        font-size: dt('message.close.icon.lg.size');
        width: dt('message.close.icon.lg.size');
        height: dt('message.close.icon.lg.size');
    }

    .p-message-outlined {
        background: transparent;
        outline-width: dt('message.outlined.border.width');
    }

    .p-message-simple {
        background: transparent;
        outline-color: transparent;
        box-shadow: none;
    }

    .p-message-simple .p-message-content {
        padding: dt('message.simple.content.padding');
    }

    .p-message-outlined .p-message-close-button:hover,
    .p-message-simple .p-message-close-button:hover {
        background: transparent;
    }

    .p-message-enter-active {
        animation: p-animate-message-enter 0.3s ease-out forwards;
        overflow: hidden;
    }

    .p-message-leave-active {
        animation: p-animate-message-leave 0.15s ease-in forwards;
        overflow: hidden;
    }

    @keyframes p-animate-message-enter {
        from {
            opacity: 0;
            grid-template-rows: 0fr;
        }
        to {
            opacity: 1;
            grid-template-rows: 1fr;
        }
    }

    @keyframes p-animate-message-leave {
        from {
            opacity: 1;
            grid-template-rows: 1fr;
        }
        to {
            opacity: 0;
            margin: 0;
            grid-template-rows: 0fr;
        }
    }
`,classes:{root:function(e){var t=e.props;return[`p-message p-component p-message-`+t.severity,{"p-message-outlined":t.variant===`outlined`,"p-message-simple":t.variant===`simple`,"p-message-sm":t.size===`small`,"p-message-lg":t.size===`large`}]},contentWrapper:`p-message-content-wrapper`,content:`p-message-content`,icon:`p-message-icon`,text:`p-message-text`,closeButton:`p-message-close-button`,closeIcon:`p-message-close-icon`}}),ie={name:`BaseMessage`,extends:S,props:{severity:{type:String,default:`info`},closable:{type:Boolean,default:!1},life:{type:Number,default:null},icon:{type:String,default:void 0},closeIcon:{type:String,default:void 0},closeButtonProps:{type:null,default:null},size:{type:String,default:null},variant:{type:String,default:null}},style:re,provide:function(){return{$pcMessage:this,$parentInstance:this}}};function G(e){"@babel/helpers - typeof";return G=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},G(e)}function K(e,t,n){return(t=q(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function q(e){var t=J(e,`string`);return G(t)==`symbol`?t:t+``}function J(e,t){if(G(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(G(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var Y={name:`Message`,extends:ie,inheritAttrs:!1,emits:[`close`,`life-end`],timeout:null,data:function(){return{visible:!0}},mounted:function(){var e=this;this.life&&setTimeout(function(){e.visible=!1,e.$emit(`life-end`)},this.life)},methods:{close:function(e){this.visible=!1,this.$emit(`close`,e)}},computed:{closeAriaLabel:function(){return this.$primevue.config.locale.aria?this.$primevue.config.locale.aria.close:void 0},dataP:function(){return w(K(K({outlined:this.variant===`outlined`,simple:this.variant===`simple`},this.severity,this.severity),this.size,this.size))}},directives:{ripple:C},components:{TimesIcon:E}};function X(e){"@babel/helpers - typeof";return X=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},X(e)}function Z(e,t){var n=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter(function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable})),n.push.apply(n,r)}return n}function Q(e){for(var t=1;t<arguments.length;t++){var n=arguments[t]==null?{}:arguments[t];t%2?Z(Object(n),!0).forEach(function(t){$(e,t,n[t])}):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(n)):Z(Object(n)).forEach(function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(n,t))})}return e}function $(e,t,n){return(t=ae(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function ae(e){var t=oe(e,`string`);return X(t)==`symbol`?t:t+``}function oe(e,t){if(X(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(X(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var se=[`data-p`],ce=[`data-p`],le=[`data-p`],ue=[`aria-label`,`data-p`],de=[`data-p`];function fe(e,t,r,p,m,h){var _=c(`TimesIcon`),y=b(`ripple`);return s(),g(P,l({name:`p-message`,appear:``},e.ptmi(`transition`)),{default:a(function(){return[m.visible?(s(),f(`div`,l({key:0,class:e.cx(`root`),role:`alert`,"aria-live":`assertive`,"aria-atomic":`true`,"data-p":h.dataP},e.ptm(`root`)),[d(`div`,l({class:e.cx(`contentWrapper`)},e.ptm(`contentWrapper`)),[e.$slots.container?o(e.$slots,`container`,{key:0,closeCallback:h.close}):(s(),f(`div`,l({key:1,class:e.cx(`content`),"data-p":h.dataP},e.ptm(`content`)),[o(e.$slots,`icon`,{class:n(e.cx(`icon`))},function(){return[(s(),g(i(e.icon?`span`:null),l({class:[e.cx(`icon`),e.icon],"data-p":h.dataP},e.ptm(`icon`)),null,16,[`class`,`data-p`]))]}),e.$slots.default?(s(),f(`div`,l({key:0,class:e.cx(`text`),"data-p":h.dataP},e.ptm(`text`)),[o(e.$slots,`default`)],16,le)):v(``,!0),e.closable?u((s(),f(`button`,l({key:1,class:e.cx(`closeButton`),"aria-label":h.closeAriaLabel,type:`button`,onClick:t[0]||=function(e){return h.close(e)},"data-p":h.dataP},Q(Q({},e.closeButtonProps),e.ptm(`closeButton`))),[o(e.$slots,`closeicon`,{},function(){return[e.closeIcon?(s(),f(`i`,l({key:0,class:[e.cx(`closeIcon`),e.closeIcon],"data-p":h.dataP},e.ptm(`closeIcon`)),null,16,de)):(s(),g(_,l({key:1,class:[e.cx(`closeIcon`),e.closeIcon],"data-p":h.dataP},e.ptm(`closeIcon`)),null,16,[`class`,`data-p`]))]})],16,ue)),[[y]]):v(``,!0)],16,ce))],16)],16,se)):v(``,!0)]}),_:3},16)}Y.render=fe;var pe={class:`flex flex-col gap-4`},me={class:`grid grid-cols-1 sm:grid-cols-3 gap-3`},he={class:`flex flex-col gap-1`},ge={class:`flex flex-col gap-1`},_e={class:`flex flex-col gap-1`},ve={class:`flex flex-wrap gap-2 justify-end`},ye={class:`rounded-lg border border-surface-200 dark:border-surface-700 p-3 text-sm grid grid-cols-1 sm:grid-cols-2 gap-2`},be={class:`flex items-center gap-2`},xe={class:`flex items-center gap-2`},Se={class:`flex items-center gap-2`},Ce={key:0,class:`flex items-center gap-2`},we={key:2,class:`sm:col-span-2 text-amber-600 text-xs`},Te=y({__name:`BackupAutomaticoNubeComp`,setup(i){let o=I(),c=r({...N}),l=te(),u=r(!1),y=r(!0),b=r(!1),x=[{label:`Desactivado`,value:`desactivado`},{label:`Diario`,value:`diario`},{label:`Semanal`,value:`semanal`}],S=Array.from({length:24},(e,t)=>({label:`${String(t).padStart(2,`0`)}:00`,value:t})),C=_(()=>O.value),w=_(()=>{let e=F(new Date,C.value.lastRunAt||null,c.value.frecuencia,c.value.hora);return e?L(e.toISOString()):``}),E=_(()=>C.value.lastStatus===`ok`?`success`:C.value.lastStatus===`error`?`danger`:C.value.lastStatus===`omitido`?`info`:`secondary`),P=_(()=>({ok:`Correcto`,error:`Error`,omitido:`Omitido`})[C.value.lastStatus]||`Sin ejecuciones`);function L(e){return e?M(e,{year:`numeric`,month:`2-digit`,day:`2-digit`,hour:`2-digit`,minute:`2-digit`}):`-`}async function R(){y.value=!0;try{c.value=await ee(),await j(),u.value=await k()}finally{y.value=!1}}async function z(){b.value=!0;try{await D(c.value),o.add({severity:`success`,summary:`Respaldo automatico`,detail:`Configuracion guardada`,life:2500})}catch(e){o.add({severity:`error`,summary:`Error`,detail:e?.message||`No se pudo guardar`,life:3500})}finally{b.value=!1}}async function H(){let e=await A({force:!0});e.lastStatus===`error`?o.add({severity:`error`,summary:`Respaldo en TM Cloud`,detail:e.lastMessage,life:4500}):e.lastStatus&&o.add({severity:`success`,summary:`Respaldo en TM Cloud`,detail:e.lastMessage,life:3500})}return h(R),(r,i)=>(s(),g(e(W),{legend:`Respaldo automatico en TM Cloud`},{default:a(()=>[d(`div`,pe,[i[12]||=d(`p`,{class:`text-sm text-surface-500`},` Crea copias de la base de datos de la empresa en linea (TM Cloud) segun la frecuencia elegida. La programacion se ejecuta desde la aplicacion de escritorio mientras este abierta; si la PC estaba apagada a la hora preferida, el respaldo se crea al abrirla. Si otra PC ya creo un respaldo en el mismo periodo, no se crea otro. `,-1),e(l)?!y.value&&!u.value?(s(),g(e(Y),{key:1,severity:`warn`,closable:!1},{default:a(()=>[...i[4]||=[m(` Esta PC no tiene TM Cloud configurado con la llave Secret necesaria para crear respaldos. Configuralo en la seccion TM Cloud. `,-1)]]),_:1})):v(``,!0):(s(),g(e(Y),{key:0,severity:`info`,closable:!1},{default:a(()=>[...i[3]||=[m(` La programacion de respaldos automaticos se ejecuta desde la aplicacion de escritorio (Windows/macOS). En la version web y en Android puedes ver esta configuracion, pero los respaldos no se crean desde aqui. `,-1)]]),_:1})),d(`div`,me,[d(`div`,he,[i[5]||=d(`label`,{class:`text-xs font-semibold`},`Frecuencia`,-1),p(e(B),{modelValue:c.value.frecuencia,"onUpdate:modelValue":i[0]||=e=>c.value.frecuencia=e,options:x,optionLabel:`label`,optionValue:`value`,disabled:y.value,fluid:``},null,8,[`modelValue`,`disabled`])]),d(`div`,ge,[i[6]||=d(`label`,{class:`text-xs font-semibold`},`Hora preferida`,-1),p(e(B),{modelValue:c.value.hora,"onUpdate:modelValue":i[1]||=e=>c.value.hora=e,options:e(S),optionLabel:`label`,optionValue:`value`,disabled:y.value||c.value.frecuencia===`desactivado`,fluid:``},null,8,[`modelValue`,`options`,`disabled`])]),d(`div`,_e,[i[7]||=d(`label`,{class:`text-xs font-semibold`},`Conservar ultimos (0 = todos)`,-1),p(e(V),{modelValue:c.value.retener,"onUpdate:modelValue":i[2]||=e=>c.value.retener=e,min:0,max:e(60),showButtons:``,disabled:y.value,fluid:``},null,8,[`modelValue`,`max`,`disabled`])])]),i[13]||=d(`p`,{class:`text-xs text-surface-500`},` La retencion solo elimina respaldos creados automaticamente por esta PC (identificados por su codigo al crearlos). TM Cloud no permite etiquetar respaldos, por lo que los respaldos manuales o creados por otras PCs nunca se eliminan. `,-1),d(`div`,ve,[p(e(T),{label:`Crear respaldo ahora`,icon:`pi pi-cloud-upload`,severity:`secondary`,outlined:``,loading:e(ne),disabled:y.value||!u.value,onClick:H},null,8,[`loading`,`disabled`]),p(e(T),{label:`Guardar`,icon:`pi pi-save`,loading:b.value,disabled:y.value,onClick:z},null,8,[`loading`,`disabled`])]),d(`div`,ye,[d(`div`,be,[i[8]||=d(`span`,{class:`text-surface-500`},`Ultimo respaldo automatico:`,-1),d(`strong`,null,t(L(C.value.lastRunAt)),1)]),d(`div`,xe,[i[9]||=d(`span`,{class:`text-surface-500`},`Estado:`,-1),p(e(U),{value:P.value,severity:E.value},null,8,[`value`,`severity`])]),d(`div`,Se,[i[10]||=d(`span`,{class:`text-surface-500`},`Ultimo intento:`,-1),d(`span`,null,t(L(C.value.lastAttemptAt)),1)]),e(l)&&c.value.frecuencia!==`desactivado`?(s(),f(`div`,Ce,[i[11]||=d(`span`,{class:`text-surface-500`},`Proxima ejecucion:`,-1),d(`span`,null,t(w.value),1)])):v(``,!0),C.value.lastMessage?(s(),f(`div`,{key:1,class:n([`sm:col-span-2`,C.value.lastStatus===`error`?`text-red-600`:`text-surface-600 dark:text-surface-300`])},t(C.value.lastMessage),3)):v(``,!0),C.value.retentionNote?(s(),f(`div`,we,t(C.value.retentionNote),1)):v(``,!0)])])]),_:1}))}}),Ee={class:`flex items-center justify-between gap-3 mb-4 flex-wrap`},De={class:`flex items-center gap-2`},Oe={class:`flex gap-1`},ke={class:`flex items-center gap-2 min-w-0`},Ae={class:`font-mono text-sm truncate`},je={class:`mt-4`},Me=y({__name:`BackupsComp`,setup(n){let i=I(),{puedeEliminar:o,exigirAccion:c}=H(),l=r([]),_=r(!1),y=r(!1),x=r(``),S=r(``),C=r(``);function w(e){if(!e)return`0 KB`;let t=e/1024;return t<1024?`${t.toFixed(1)} KB`:`${(t/1024).toFixed(2)} MB`}function E(e){return M(e,{year:`numeric`,month:`2-digit`,day:`2-digit`,hour:`2-digit`,minute:`2-digit`})}async function D(){_.value=!0;try{let e=await window.electron.invoke(`backup:list`);e.success?l.value=e.data||[]:i.add({severity:`error`,summary:`Error`,detail:e.error||`No se pudieron listar los backups`,life:3e3})}catch(e){i.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}finally{_.value=!1}}async function O(){y.value=!0;try{let e=await window.electron.invoke(`backup:create`);e.success?(i.add({severity:`success`,summary:`Backup creado`,detail:`La copia de seguridad fue creada`,life:2500}),await D()):i.add({severity:`error`,summary:`Error`,detail:e.error||`No se pudo crear el backup`,life:3e3})}catch(e){i.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}finally{y.value=!1}}async function k(e){C.value=e.nombre;try{let t=await window.electron.invoke(`backup:download`,e.nombre);t.success?i.add({severity:`success`,summary:`Descargado`,detail:`Backup guardado correctamente`,life:2500}):t.error&&i.add({severity:`error`,summary:`Error`,detail:t.error,life:3e3})}catch(e){i.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}finally{C.value=``}}async function A(e){if(window.confirm(`Restaurar el backup "${e.nombre}" reemplazara la base de datos actual. Deseas continuar?`)){x.value=e.nombre;try{let t=await window.electron.invoke(`backup:restore`,e.nombre);t.success?(i.add({severity:`success`,summary:`Restaurado`,detail:`Backup restaurado. La app se recargara.`,life:2500}),setTimeout(()=>window.location.reload(),1200)):i.add({severity:`error`,summary:`Error`,detail:t.error||`No se pudo restaurar`,life:3e3})}catch(e){i.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}finally{x.value=``}}}async function j(e){if(c(`accion_eliminar`)&&window.confirm(`Eliminar el backup "${e.nombre}"?`)){S.value=e.nombre;try{let t=await window.electron.invoke(`backup:delete`,e.nombre);t.success?(i.add({severity:`success`,summary:`Eliminado`,detail:`Backup eliminado`,life:2500}),await D()):i.add({severity:`error`,summary:`Error`,detail:t.error||`No se pudo eliminar`,life:3e3})}catch(e){i.add({severity:`error`,summary:`Error`,detail:e.message,life:3e3})}finally{S.value=``}}}return h(D),(n,r)=>{let i=b(`tooltip`);return s(),f(`div`,null,[p(e(L)),p(e(W),{legend:`Backups`},{default:a(()=>[d(`div`,Ee,[r[0]||=d(`div`,null,[d(`h3`,{class:`font-semibold text-lg`},`Copias de seguridad`),d(`p`,{class:`text-sm text-surface-500`},`Crea, descarga, restaura o elimina backups de la base de datos local.`)],-1),d(`div`,De,[p(e(T),{icon:`pi pi-refresh`,severity:`secondary`,outlined:``,onClick:D}),p(e(T),{label:`Crear Backup`,icon:`pi pi-save`,loading:y.value,onClick:O},null,8,[`loading`])])]),p(e(R),{value:l.value,loading:_.value,stripedRows:``,paginator:``,rows:10,rowsPerPageOptions:[10,25,50],dataKey:`nombre`,responsiveLayout:`scroll`},{empty:a(()=>[...r[2]||=[d(`div`,{class:`text-center py-8 text-surface-500`},` No hay backups creados. `,-1)]]),default:a(()=>[p(e(z),{header:`Acciones`,style:{width:`12rem`}},{body:a(({data:t})=>[d(`div`,Oe,[u(p(e(T),{icon:`pi pi-download`,severity:`info`,text:``,rounded:``,loading:C.value===t.nombre,onClick:e=>k(t)},null,8,[`loading`,`onClick`]),[[i,`Descargar`]]),u(p(e(T),{icon:`pi pi-history`,severity:`warn`,text:``,rounded:``,loading:x.value===t.nombre,onClick:e=>A(t)},null,8,[`loading`,`onClick`]),[[i,`Restaurar`]]),e(o)?u((s(),g(e(T),{key:0,icon:`pi pi-trash`,severity:`danger`,text:``,rounded:``,loading:S.value===t.nombre,onClick:e=>j(t)},null,8,[`loading`,`onClick`])),[[i,`Eliminar`]]):v(``,!0)])]),_:1}),p(e(z),{field:`nombre`,header:`Archivo`,sortable:``},{body:a(({data:e})=>[d(`div`,ke,[r[1]||=d(`i`,{class:`pi pi-database text-primary`},null,-1),d(`span`,Ae,t(e.nombre),1)])]),_:1}),p(e(z),{field:`fecha`,header:`Fecha`,sortable:``,style:{width:`13rem`}},{body:a(({data:e})=>[m(t(E(e.fecha)),1)]),_:1}),p(e(z),{field:`tamano`,header:`Tamano`,sortable:``,style:{width:`8rem`}},{body:a(({data:t})=>[p(e(U),{value:w(t.tamano),severity:`secondary`},null,8,[`value`])]),_:1})]),_:1},8,[`value`,`loading`])]),_:1}),d(`div`,je,[p(Te)])])}}});export{Me as default};