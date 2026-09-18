import{at as e,lt as t}from"./reactivity.esm-bundler-CoVOkjw3.js";import{F as n,I as r,K as i,L as a,N as o,R as s,c,d as l,g as u,h as d,l as f,q as p,r as m,u as h,w as g,z as _}from"./runtime-core.esm-bundler-rmv8K8AY.js";import{A as v,H as y,L as b,Q as x,R as S,et as C,h as w,mt as T,o as E,r as D,t as O,tt as k}from"./ripple-Dp-vyuQr.js";import{M as A,Q as j,W as M,Z as N}from"./index-BApG51kI.js";import{t as P}from"./overlayeventbus-Dh8IiWo8.js";var F=E.extend({name:`menu`,style:`
    .p-menu {
        background: dt('menu.background');
        color: dt('menu.color');
        border: 1px solid dt('menu.border.color');
        border-radius: dt('menu.border.radius');
        min-width: 12.5rem;
    }

    .p-menu-list {
        margin: 0;
        padding: dt('menu.list.padding');
        outline: 0 none;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: dt('menu.list.gap');
    }

    .p-menu-item-content {
        transition:
            background dt('menu.transition.duration'),
            color dt('menu.transition.duration');
        border-radius: dt('menu.item.border.radius');
        color: dt('menu.item.color');
        overflow: hidden;
    }

    .p-menu-item-link {
        cursor: pointer;
        display: flex;
        align-items: center;
        text-decoration: none;
        overflow: hidden;
        position: relative;
        color: inherit;
        padding: dt('menu.item.padding');
        gap: dt('menu.item.gap');
        user-select: none;
        outline: 0 none;
    }

    .p-menu-item-label {
        line-height: 1;
    }

    .p-menu-item-icon {
        color: dt('menu.item.icon.color');
    }

    .p-menu-item.p-focus .p-menu-item-content {
        color: dt('menu.item.focus.color');
        background: dt('menu.item.focus.background');
    }

    .p-menu-item.p-focus .p-menu-item-icon {
        color: dt('menu.item.icon.focus.color');
    }

    .p-menu-item:not(.p-disabled) .p-menu-item-content:hover {
        color: dt('menu.item.focus.color');
        background: dt('menu.item.focus.background');
    }

    .p-menu-item:not(.p-disabled) .p-menu-item-content:hover .p-menu-item-icon {
        color: dt('menu.item.icon.focus.color');
    }

    .p-menu-overlay {
        box-shadow: dt('menu.shadow');
    }

    .p-menu-submenu-label {
        background: dt('menu.submenu.label.background');
        padding: dt('menu.submenu.label.padding');
        color: dt('menu.submenu.label.color');
        font-weight: dt('menu.submenu.label.font.weight');
    }

    .p-menu-separator {
        border-block-start: 1px solid dt('menu.separator.border.color');
    }
`,classes:{root:function(e){return[`p-menu p-component`,{"p-menu-overlay":e.props.popup}]},start:`p-menu-start`,list:`p-menu-list`,submenuLabel:`p-menu-submenu-label`,separator:`p-menu-separator`,end:`p-menu-end`,item:function(e){var t=e.instance;return[`p-menu-item`,{"p-focus":t.id===t.focusedOptionId,"p-disabled":t.disabled()}]},itemContent:`p-menu-item-content`,itemLink:`p-menu-item-link`,itemIcon:`p-menu-item-icon`,itemLabel:`p-menu-item-label`}}),I={name:`BaseMenu`,extends:D,props:{popup:{type:Boolean,default:!1},model:{type:Array,default:null},appendTo:{type:[String,Object],default:`body`},autoZIndex:{type:Boolean,default:!0},baseZIndex:{type:Number,default:0},tabindex:{type:Number,default:0},ariaLabel:{type:String,default:null},ariaLabelledby:{type:String,default:null}},style:F,provide:function(){return{$pcMenu:this,$parentInstance:this}}},L={name:`Menuitem`,hostName:`Menu`,extends:D,inheritAttrs:!1,emits:[`item-click`,`item-mousemove`],props:{item:null,templates:null,id:null,focusedOptionId:null,index:null},methods:{getItemProp:function(e,t){return e&&e.item?T(e.item[t]):void 0},getPTOptions:function(e){return this.ptm(e,{context:{item:this.item,index:this.index,focused:this.isItemFocused(),disabled:this.disabled()}})},isItemFocused:function(){return this.focusedOptionId===this.id},onItemClick:function(e){var t=this.getItemProp(this.item,`command`);t&&t({originalEvent:e,item:this.item.item}),this.$emit(`item-click`,{originalEvent:e,item:this.item,id:this.id})},onItemMouseMove:function(e){this.$emit(`item-mousemove`,{originalEvent:e,item:this.item,id:this.id})},visible:function(){return typeof this.item.visible==`function`?this.item.visible():this.item.visible!==!1},disabled:function(){return typeof this.item.disabled==`function`?this.item.disabled():this.item.disabled},label:function(){return typeof this.item.label==`function`?this.item.label():this.item.label},getMenuItemProps:function(e){return{action:g({class:this.cx(`itemLink`),tabindex:`-1`},this.getPTOptions(`itemLink`)),icon:g({class:[this.cx(`itemIcon`),e.icon]},this.getPTOptions(`itemIcon`)),label:g({class:this.cx(`itemLabel`)},this.getPTOptions(`itemLabel`))}}},computed:{dataP:function(){return k({focus:this.isItemFocused(),disabled:this.disabled()})}},directives:{ripple:O}},R=[`id`,`aria-label`,`aria-disabled`,`data-p-focused`,`data-p-disabled`,`data-p`],z=[`data-p`],B=[`href`,`target`],V=[`data-p`],H=[`data-p`];function U(n,r,i,a,u,d){var m=s(`ripple`);return d.visible()?(o(),l(`li`,g({key:0,id:i.id,class:[n.cx(`item`),i.item.class],role:`menuitem`,style:i.item.style,"aria-label":d.label(),"aria-disabled":d.disabled(),"data-p-focused":d.isItemFocused(),"data-p-disabled":d.disabled()||!1,"data-p":d.dataP},d.getPTOptions(`item`)),[c(`div`,g({class:n.cx(`itemContent`),onClick:r[0]||=function(e){return d.onItemClick(e)},onMousemove:r[1]||=function(e){return d.onItemMouseMove(e)},"data-p":d.dataP},d.getPTOptions(`itemContent`)),[i.templates.item?i.templates.item?(o(),f(_(i.templates.item),{key:1,item:i.item,label:d.label(),props:d.getMenuItemProps(i.item)},null,8,[`item`,`label`,`props`])):h(``,!0):p((o(),l(`a`,g({key:0,href:i.item.url,class:n.cx(`itemLink`),target:i.item.target,tabindex:`-1`},d.getPTOptions(`itemLink`)),[i.templates.itemicon?(o(),f(_(i.templates.itemicon),{key:0,item:i.item,class:e(n.cx(`itemIcon`))},null,8,[`item`,`class`])):i.item.icon?(o(),l(`span`,g({key:1,class:[n.cx(`itemIcon`),i.item.icon],"data-p":d.dataP},d.getPTOptions(`itemIcon`)),null,16,V)):h(``,!0),c(`span`,g({class:n.cx(`itemLabel`),"data-p":d.dataP},d.getPTOptions(`itemLabel`)),t(d.label()),17,H)],16,B)),[[m]])],16,z)],16,R)):h(``,!0)}L.render=U;function W(e){return J(e)||q(e)||K(e)||G()}function G(){throw TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function K(e,t){if(e){if(typeof e==`string`)return Y(e,t);var n={}.toString.call(e).slice(8,-1);return n===`Object`&&e.constructor&&(n=e.constructor.name),n===`Map`||n===`Set`?Array.from(e):n===`Arguments`||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?Y(e,t):void 0}}function q(e){if(typeof Symbol<`u`&&e[Symbol.iterator]!=null||e[`@@iterator`]!=null)return Array.from(e)}function J(e){if(Array.isArray(e))return Y(e)}function Y(e,t){(t==null||t>e.length)&&(t=e.length);for(var n=0,r=Array(t);n<t;n++)r[n]=e[n];return r}var X={name:`Menu`,extends:I,inheritAttrs:!1,emits:[`show`,`hide`,`focus`,`blur`],data:function(){return{overlayVisible:!1,focused:!1,focusedOptionIndex:-1,selectedOptionIndex:-1}},target:null,outsideClickListener:null,scrollHandler:null,resizeListener:null,container:null,list:null,mounted:function(){this.popup||(this.bindResizeListener(),this.bindOutsideClickListener())},beforeUnmount:function(){this.unbindResizeListener(),this.unbindOutsideClickListener(),this.scrollHandler&&=(this.scrollHandler.destroy(),null),this.target=null,this.container&&this.autoZIndex&&N.clear(this.container),this.container=null},methods:{itemClick:function(e){var t=e.item;this.disabled(t)||(t.command&&t.command(e),this.overlayVisible&&this.hide(),!this.popup&&this.focusedOptionIndex!==e.id&&(this.focusedOptionIndex=e.id))},itemMouseMove:function(e){this.focused&&(this.focusedOptionIndex=e.id)},onListFocus:function(e){this.focused=!0,!this.popup&&this.changeFocusedOptionIndex(0),this.$emit(`focus`,e)},onListBlur:function(e){this.focused=!1,this.focusedOptionIndex=-1,this.$emit(`blur`,e)},onListKeyDown:function(e){switch(e.code){case`ArrowDown`:this.onArrowDownKey(e);break;case`ArrowUp`:this.onArrowUpKey(e);break;case`Home`:this.onHomeKey(e);break;case`End`:this.onEndKey(e);break;case`Enter`:case`NumpadEnter`:this.onEnterKey(e);break;case`Space`:this.onSpaceKey(e);break;case`Escape`:this.popup&&(y(this.target),this.hide());case`Tab`:this.overlayVisible&&this.hide()}},onArrowDownKey:function(e){var t=this.findNextOptionIndex(this.focusedOptionIndex);this.changeFocusedOptionIndex(t),e.preventDefault()},onArrowUpKey:function(e){if(e.altKey&&this.popup)y(this.target),this.hide(),e.preventDefault();else{var t=this.findPrevOptionIndex(this.focusedOptionIndex);this.changeFocusedOptionIndex(t),e.preventDefault()}},onHomeKey:function(e){this.changeFocusedOptionIndex(0),e.preventDefault()},onEndKey:function(e){this.changeFocusedOptionIndex(b(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`).length-1),e.preventDefault()},onEnterKey:function(e){var t=C(this.list,`li[id="${`${this.focusedOptionIndex}`}"]`),n=t&&C(t,`a[data-pc-section="itemlink"]`);this.popup&&y(this.target),n?n.click():t&&t.click(),e.preventDefault()},onSpaceKey:function(e){this.onEnterKey(e)},findNextOptionIndex:function(e){var t=W(b(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`)).findIndex(function(t){return t.id===e});return t>-1?t+1:0},findPrevOptionIndex:function(e){var t=W(b(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`)).findIndex(function(t){return t.id===e});return t>-1?t-1:0},changeFocusedOptionIndex:function(e){var t=b(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`),n=e>=t.length?t.length-1:e<0?0:e;n>-1&&(this.focusedOptionIndex=t[n].getAttribute(`id`))},toggle:function(e,t){this.overlayVisible?this.hide():this.show(e,t)},show:function(e,t){this.overlayVisible=!0,this.target=t??e.currentTarget},hide:function(){this.overlayVisible=!1,this.target=null},onEnter:function(e){v(e,{position:`absolute`,top:`0`}),this.alignOverlay(),this.bindOutsideClickListener(),this.bindResizeListener(),this.bindScrollListener(),this.autoZIndex&&N.set(`menu`,e,this.baseZIndex||this.$primevue.config.zIndex.menu),this.popup&&y(this.list),this.$emit(`show`)},onLeave:function(){this.unbindOutsideClickListener(),this.unbindResizeListener(),this.unbindScrollListener(),this.$emit(`hide`)},onAfterLeave:function(e){this.autoZIndex&&N.clear(e)},alignOverlay:function(){w(this.container,this.target),x(this.target)>x(this.container)&&(this.container.style.minWidth=x(this.target)+`px`)},bindOutsideClickListener:function(){var e=this;this.outsideClickListener||(this.outsideClickListener=function(t){var n=e.container&&!e.container.contains(t.target),r=!(e.target&&(e.target===t.target||e.target.contains(t.target)));e.overlayVisible&&n&&r?e.hide():!e.popup&&n&&r&&(e.focusedOptionIndex=-1)},document.addEventListener(`click`,this.outsideClickListener,!0))},unbindOutsideClickListener:function(){this.outsideClickListener&&=(document.removeEventListener(`click`,this.outsideClickListener,!0),null)},bindScrollListener:function(){var e=this;this.scrollHandler||=new M(this.target,function(){e.overlayVisible&&e.hide()}),this.scrollHandler.bindScrollListener()},unbindScrollListener:function(){this.scrollHandler&&this.scrollHandler.unbindScrollListener()},bindResizeListener:function(){var e=this;this.resizeListener||(this.resizeListener=function(){e.overlayVisible&&!S()&&e.hide()},window.addEventListener(`resize`,this.resizeListener))},unbindResizeListener:function(){this.resizeListener&&=(window.removeEventListener(`resize`,this.resizeListener),null)},visible:function(e){return typeof e.visible==`function`?e.visible():e.visible!==!1},disabled:function(e){return typeof e.disabled==`function`?e.disabled():e.disabled},label:function(e){return typeof e.label==`function`?e.label():e.label},onOverlayClick:function(e){P.emit(`overlay-click`,{originalEvent:e,target:this.target})},containerRef:function(e){this.container=e},listRef:function(e){this.list=e}},computed:{focusedOptionId:function(){return this.focusedOptionIndex===-1?null:this.focusedOptionIndex},dataP:function(){return k({popup:this.popup})}},components:{PVMenuitem:L,Portal:A}},Z=[`id`,`data-p`],Q=[`id`,`tabindex`,`aria-activedescendant`,`aria-label`,`aria-labelledby`],$=[`id`];function ee(e,s,p,_,v,y){var b=a(`PVMenuitem`),x=a(`Portal`);return o(),f(x,{appendTo:e.appendTo,disabled:!e.popup},{default:i(function(){return[u(j,g({name:`p-anchored-overlay`,onEnter:y.onEnter,onLeave:y.onLeave,onAfterLeave:y.onAfterLeave},e.ptm(`transition`)),{default:i(function(){return[!e.popup||v.overlayVisible?(o(),l(`div`,g({key:0,ref:y.containerRef,id:e.$id,class:e.cx(`root`),onClick:s[3]||=function(){return y.onOverlayClick&&y.onOverlayClick.apply(y,arguments)},"data-p":y.dataP},e.ptmi(`root`)),[e.$slots.start?(o(),l(`div`,g({key:0,class:e.cx(`start`)},e.ptm(`start`)),[r(e.$slots,`start`)],16)):h(``,!0),c(`ul`,g({ref:y.listRef,id:e.$id+`_list`,class:e.cx(`list`),role:`menu`,tabindex:e.tabindex,"aria-activedescendant":v.focused?y.focusedOptionId:void 0,"aria-label":e.ariaLabel,"aria-labelledby":e.ariaLabelledby,onFocus:s[0]||=function(){return y.onListFocus&&y.onListFocus.apply(y,arguments)},onBlur:s[1]||=function(){return y.onListBlur&&y.onListBlur.apply(y,arguments)},onKeydown:s[2]||=function(){return y.onListKeyDown&&y.onListKeyDown.apply(y,arguments)}},e.ptm(`list`)),[(o(!0),l(m,null,n(e.model,function(i,a){return o(),l(m,{key:y.label(i)+a.toString()},[i.items&&y.visible(i)&&!i.separator?(o(),l(m,{key:0},[i.items?(o(),l(`li`,g({key:0,id:e.$id+`_`+a,class:[e.cx(`submenuLabel`),i.class],role:`none`},{ref_for:!0},e.ptm(`submenuLabel`)),[r(e.$slots,e.$slots.submenulabel?`submenulabel`:`submenuheader`,{item:i},function(){return[d(t(y.label(i)),1)]})],16,$)):h(``,!0),(o(!0),l(m,null,n(i.items,function(t,n){return o(),l(m,{key:t.label+a+`_`+n},[y.visible(t)&&!t.separator?(o(),f(b,{key:0,id:e.$id+`_`+a+`_`+n,item:t,templates:e.$slots,focusedOptionId:y.focusedOptionId,unstyled:e.unstyled,onItemClick:y.itemClick,onItemMousemove:y.itemMouseMove,pt:e.pt},null,8,[`id`,`item`,`templates`,`focusedOptionId`,`unstyled`,`onItemClick`,`onItemMousemove`,`pt`])):y.visible(t)&&t.separator?(o(),l(`li`,g({key:`separator`+a+n,class:[e.cx(`separator`),i.class],style:t.style,role:`separator`},{ref_for:!0},e.ptm(`separator`)),null,16)):h(``,!0)],64)}),128))],64)):y.visible(i)&&i.separator?(o(),l(`li`,g({key:`separator`+a.toString(),class:[e.cx(`separator`),i.class],style:i.style,role:`separator`},{ref_for:!0},e.ptm(`separator`)),null,16)):(o(),f(b,{key:y.label(i)+a.toString(),id:e.$id+`_`+a,item:i,index:a,templates:e.$slots,focusedOptionId:y.focusedOptionId,unstyled:e.unstyled,onItemClick:y.itemClick,onItemMousemove:y.itemMouseMove,pt:e.pt},null,8,[`id`,`item`,`index`,`templates`,`focusedOptionId`,`unstyled`,`onItemClick`,`onItemMousemove`,`pt`]))],64)}),128))],16,Q),e.$slots.end?(o(),l(`div`,g({key:1,class:e.cx(`end`)},e.ptm(`end`)),[r(e.$slots,`end`)],16)):h(``,!0)],16,Z)):h(``,!0)]}),_:3},16,[`onEnter`,`onLeave`,`onAfterLeave`])]}),_:3},8,[`appendTo`,`disabled`])}X.render=ee;export{X as t};