import{Et as e,F as t,I as n,K as r,L as i,N as a,R as o,c as s,d as c,g as l,h as u,kt as d,l as f,q as p,r as m,u as h,w as g,z as _}from"./runtime-core.esm-bundler-a6pTXghO.js";import{$ as v,R as y,U as b,g as x,ht as S,i as C,j as w,nt as T,s as E,t as D,tt as O,z as k}from"./ripple-DLQG0xpb.js";import{B as A,E as j,N as M,V as N}from"./index-BMrcxrjI.js";import{t as P}from"./overlayeventbus-kBA6Y_CW.js";var F=E.extend({name:`menu`,style:`
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
`,classes:{root:function(e){return[`p-menu p-component`,{"p-menu-overlay":e.props.popup}]},start:`p-menu-start`,list:`p-menu-list`,submenuLabel:`p-menu-submenu-label`,separator:`p-menu-separator`,end:`p-menu-end`,item:function(e){var t=e.instance;return[`p-menu-item`,{"p-focus":t.id===t.focusedOptionId,"p-disabled":t.disabled()}]},itemContent:`p-menu-item-content`,itemLink:`p-menu-item-link`,itemIcon:`p-menu-item-icon`,itemLabel:`p-menu-item-label`}}),I={name:`BaseMenu`,extends:C,props:{popup:{type:Boolean,default:!1},model:{type:Array,default:null},appendTo:{type:[String,Object],default:`body`},autoZIndex:{type:Boolean,default:!0},baseZIndex:{type:Number,default:0},tabindex:{type:Number,default:0},ariaLabel:{type:String,default:null},ariaLabelledby:{type:String,default:null}},style:F,provide:function(){return{$pcMenu:this,$parentInstance:this}}},L={name:`Menuitem`,hostName:`Menu`,extends:C,inheritAttrs:!1,emits:[`item-click`,`item-mousemove`],props:{item:null,templates:null,id:null,focusedOptionId:null,index:null},methods:{getItemProp:function(e,t){return e&&e.item?S(e.item[t]):void 0},getPTOptions:function(e){return this.ptm(e,{context:{item:this.item,index:this.index,focused:this.isItemFocused(),disabled:this.disabled()}})},isItemFocused:function(){return this.focusedOptionId===this.id},onItemClick:function(e){var t=this.getItemProp(this.item,`command`);t&&t({originalEvent:e,item:this.item.item}),this.$emit(`item-click`,{originalEvent:e,item:this.item,id:this.id})},onItemMouseMove:function(e){this.$emit(`item-mousemove`,{originalEvent:e,item:this.item,id:this.id})},visible:function(){return typeof this.item.visible==`function`?this.item.visible():this.item.visible!==!1},disabled:function(){return typeof this.item.disabled==`function`?this.item.disabled():this.item.disabled},label:function(){return typeof this.item.label==`function`?this.item.label():this.item.label},getMenuItemProps:function(e){return{action:g({class:this.cx(`itemLink`),tabindex:`-1`},this.getPTOptions(`itemLink`)),icon:g({class:[this.cx(`itemIcon`),e.icon]},this.getPTOptions(`itemIcon`)),label:g({class:this.cx(`itemLabel`)},this.getPTOptions(`itemLabel`))}}},computed:{dataP:function(){return T({focus:this.isItemFocused(),disabled:this.disabled()})}},directives:{ripple:D}},R=[`id`,`aria-label`,`aria-disabled`,`data-p-focused`,`data-p-disabled`,`data-p`],z=[`data-p`],B=[`href`,`target`],V=[`data-p`],H=[`data-p`];function U(t,n,r,i,l,u){var m=o(`ripple`);return u.visible()?(a(),c(`li`,g({key:0,id:r.id,class:[t.cx(`item`),r.item.class],role:`menuitem`,style:r.item.style,"aria-label":u.label(),"aria-disabled":u.disabled(),"data-p-focused":u.isItemFocused(),"data-p-disabled":u.disabled()||!1,"data-p":u.dataP},u.getPTOptions(`item`)),[s(`div`,g({class:t.cx(`itemContent`),onClick:n[0]||=function(e){return u.onItemClick(e)},onMousemove:n[1]||=function(e){return u.onItemMouseMove(e)},"data-p":u.dataP},u.getPTOptions(`itemContent`)),[r.templates.item?r.templates.item?(a(),f(_(r.templates.item),{key:1,item:r.item,label:u.label(),props:u.getMenuItemProps(r.item)},null,8,[`item`,`label`,`props`])):h(``,!0):p((a(),c(`a`,g({key:0,href:r.item.url,class:t.cx(`itemLink`),target:r.item.target,tabindex:`-1`},u.getPTOptions(`itemLink`)),[r.templates.itemicon?(a(),f(_(r.templates.itemicon),{key:0,item:r.item,class:e(t.cx(`itemIcon`))},null,8,[`item`,`class`])):r.item.icon?(a(),c(`span`,g({key:1,class:[t.cx(`itemIcon`),r.item.icon],"data-p":u.dataP},u.getPTOptions(`itemIcon`)),null,16,V)):h(``,!0),s(`span`,g({class:t.cx(`itemLabel`),"data-p":u.dataP},u.getPTOptions(`itemLabel`)),d(u.label()),17,H)],16,B)),[[m]])],16,z)],16,R)):h(``,!0)}L.render=U;function W(e){return J(e)||q(e)||K(e)||G()}function G(){throw TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function K(e,t){if(e){if(typeof e==`string`)return Y(e,t);var n={}.toString.call(e).slice(8,-1);return n===`Object`&&e.constructor&&(n=e.constructor.name),n===`Map`||n===`Set`?Array.from(e):n===`Arguments`||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?Y(e,t):void 0}}function q(e){if(typeof Symbol<`u`&&e[Symbol.iterator]!=null||e[`@@iterator`]!=null)return Array.from(e)}function J(e){if(Array.isArray(e))return Y(e)}function Y(e,t){(t==null||t>e.length)&&(t=e.length);for(var n=0,r=Array(t);n<t;n++)r[n]=e[n];return r}var X={name:`Menu`,extends:I,inheritAttrs:!1,emits:[`show`,`hide`,`focus`,`blur`],data:function(){return{overlayVisible:!1,focused:!1,focusedOptionIndex:-1,selectedOptionIndex:-1}},target:null,outsideClickListener:null,scrollHandler:null,resizeListener:null,container:null,list:null,mounted:function(){this.popup||(this.bindResizeListener(),this.bindOutsideClickListener())},beforeUnmount:function(){this.unbindResizeListener(),this.unbindOutsideClickListener(),this.scrollHandler&&=(this.scrollHandler.destroy(),null),this.target=null,this.container&&this.autoZIndex&&A.clear(this.container),this.container=null},methods:{itemClick:function(e){var t=e.item;this.disabled(t)||(t.command&&t.command(e),this.overlayVisible&&this.hide(),!this.popup&&this.focusedOptionIndex!==e.id&&(this.focusedOptionIndex=e.id))},itemMouseMove:function(e){this.focused&&(this.focusedOptionIndex=e.id)},onListFocus:function(e){this.focused=!0,!this.popup&&this.changeFocusedOptionIndex(0),this.$emit(`focus`,e)},onListBlur:function(e){this.focused=!1,this.focusedOptionIndex=-1,this.$emit(`blur`,e)},onListKeyDown:function(e){switch(e.code){case`ArrowDown`:this.onArrowDownKey(e);break;case`ArrowUp`:this.onArrowUpKey(e);break;case`Home`:this.onHomeKey(e);break;case`End`:this.onEndKey(e);break;case`Enter`:case`NumpadEnter`:this.onEnterKey(e);break;case`Space`:this.onSpaceKey(e);break;case`Escape`:this.popup&&(b(this.target),this.hide());case`Tab`:this.overlayVisible&&this.hide()}},onArrowDownKey:function(e){var t=this.findNextOptionIndex(this.focusedOptionIndex);this.changeFocusedOptionIndex(t),e.preventDefault()},onArrowUpKey:function(e){if(e.altKey&&this.popup)b(this.target),this.hide(),e.preventDefault();else{var t=this.findPrevOptionIndex(this.focusedOptionIndex);this.changeFocusedOptionIndex(t),e.preventDefault()}},onHomeKey:function(e){this.changeFocusedOptionIndex(0),e.preventDefault()},onEndKey:function(e){this.changeFocusedOptionIndex(y(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`).length-1),e.preventDefault()},onEnterKey:function(e){var t=O(this.list,`li[id="${`${this.focusedOptionIndex}`}"]`),n=t&&O(t,`a[data-pc-section="itemlink"]`);this.popup&&b(this.target),n?n.click():t&&t.click(),e.preventDefault()},onSpaceKey:function(e){this.onEnterKey(e)},findNextOptionIndex:function(e){var t=W(y(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`)).findIndex(function(t){return t.id===e});return t>-1?t+1:0},findPrevOptionIndex:function(e){var t=W(y(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`)).findIndex(function(t){return t.id===e});return t>-1?t-1:0},changeFocusedOptionIndex:function(e){var t=y(this.container,`li[data-pc-section="item"][data-p-disabled="false"]`),n=e>=t.length?t.length-1:e<0?0:e;n>-1&&(this.focusedOptionIndex=t[n].getAttribute(`id`))},toggle:function(e,t){this.overlayVisible?this.hide():this.show(e,t)},show:function(e,t){this.overlayVisible=!0,this.target=t??e.currentTarget},hide:function(){this.overlayVisible=!1,this.target=null},onEnter:function(e){w(e,{position:`absolute`,top:`0`}),this.alignOverlay(),this.bindOutsideClickListener(),this.bindResizeListener(),this.bindScrollListener(),this.autoZIndex&&A.set(`menu`,e,this.baseZIndex||this.$primevue.config.zIndex.menu),this.popup&&b(this.list),this.$emit(`show`)},onLeave:function(){this.unbindOutsideClickListener(),this.unbindResizeListener(),this.unbindScrollListener(),this.$emit(`hide`)},onAfterLeave:function(e){this.autoZIndex&&A.clear(e)},alignOverlay:function(){x(this.container,this.target),v(this.target)>v(this.container)&&(this.container.style.minWidth=v(this.target)+`px`)},bindOutsideClickListener:function(){var e=this;this.outsideClickListener||(this.outsideClickListener=function(t){var n=e.container&&!e.container.contains(t.target),r=!(e.target&&(e.target===t.target||e.target.contains(t.target)));e.overlayVisible&&n&&r?e.hide():!e.popup&&n&&r&&(e.focusedOptionIndex=-1)},document.addEventListener(`click`,this.outsideClickListener,!0))},unbindOutsideClickListener:function(){this.outsideClickListener&&=(document.removeEventListener(`click`,this.outsideClickListener,!0),null)},bindScrollListener:function(){var e=this;this.scrollHandler||=new M(this.target,function(){e.overlayVisible&&e.hide()}),this.scrollHandler.bindScrollListener()},unbindScrollListener:function(){this.scrollHandler&&this.scrollHandler.unbindScrollListener()},bindResizeListener:function(){var e=this;this.resizeListener||(this.resizeListener=function(){e.overlayVisible&&!k()&&e.hide()},window.addEventListener(`resize`,this.resizeListener))},unbindResizeListener:function(){this.resizeListener&&=(window.removeEventListener(`resize`,this.resizeListener),null)},visible:function(e){return typeof e.visible==`function`?e.visible():e.visible!==!1},disabled:function(e){return typeof e.disabled==`function`?e.disabled():e.disabled},label:function(e){return typeof e.label==`function`?e.label():e.label},onOverlayClick:function(e){P.emit(`overlay-click`,{originalEvent:e,target:this.target})},containerRef:function(e){this.container=e},listRef:function(e){this.list=e}},computed:{focusedOptionId:function(){return this.focusedOptionIndex===-1?null:this.focusedOptionIndex},dataP:function(){return T({popup:this.popup})}},components:{PVMenuitem:L,Portal:j}},Z=[`id`,`data-p`],Q=[`id`,`tabindex`,`aria-activedescendant`,`aria-label`,`aria-labelledby`],$=[`id`];function ee(e,o,p,_,v,y){var b=i(`PVMenuitem`),x=i(`Portal`);return a(),f(x,{appendTo:e.appendTo,disabled:!e.popup},{default:r(function(){return[l(N,g({name:`p-anchored-overlay`,onEnter:y.onEnter,onLeave:y.onLeave,onAfterLeave:y.onAfterLeave},e.ptm(`transition`)),{default:r(function(){return[!e.popup||v.overlayVisible?(a(),c(`div`,g({key:0,ref:y.containerRef,id:e.$id,class:e.cx(`root`),onClick:o[3]||=function(){return y.onOverlayClick&&y.onOverlayClick.apply(y,arguments)},"data-p":y.dataP},e.ptmi(`root`)),[e.$slots.start?(a(),c(`div`,g({key:0,class:e.cx(`start`)},e.ptm(`start`)),[n(e.$slots,`start`)],16)):h(``,!0),s(`ul`,g({ref:y.listRef,id:e.$id+`_list`,class:e.cx(`list`),role:`menu`,tabindex:e.tabindex,"aria-activedescendant":v.focused?y.focusedOptionId:void 0,"aria-label":e.ariaLabel,"aria-labelledby":e.ariaLabelledby,onFocus:o[0]||=function(){return y.onListFocus&&y.onListFocus.apply(y,arguments)},onBlur:o[1]||=function(){return y.onListBlur&&y.onListBlur.apply(y,arguments)},onKeydown:o[2]||=function(){return y.onListKeyDown&&y.onListKeyDown.apply(y,arguments)}},e.ptm(`list`)),[(a(!0),c(m,null,t(e.model,function(r,i){return a(),c(m,{key:y.label(r)+i.toString()},[r.items&&y.visible(r)&&!r.separator?(a(),c(m,{key:0},[r.items?(a(),c(`li`,g({key:0,id:e.$id+`_`+i,class:[e.cx(`submenuLabel`),r.class],role:`none`},{ref_for:!0},e.ptm(`submenuLabel`)),[n(e.$slots,e.$slots.submenulabel?`submenulabel`:`submenuheader`,{item:r},function(){return[u(d(y.label(r)),1)]})],16,$)):h(``,!0),(a(!0),c(m,null,t(r.items,function(t,n){return a(),c(m,{key:t.label+i+`_`+n},[y.visible(t)&&!t.separator?(a(),f(b,{key:0,id:e.$id+`_`+i+`_`+n,item:t,templates:e.$slots,focusedOptionId:y.focusedOptionId,unstyled:e.unstyled,onItemClick:y.itemClick,onItemMousemove:y.itemMouseMove,pt:e.pt},null,8,[`id`,`item`,`templates`,`focusedOptionId`,`unstyled`,`onItemClick`,`onItemMousemove`,`pt`])):y.visible(t)&&t.separator?(a(),c(`li`,g({key:`separator`+i+n,class:[e.cx(`separator`),r.class],style:t.style,role:`separator`},{ref_for:!0},e.ptm(`separator`)),null,16)):h(``,!0)],64)}),128))],64)):y.visible(r)&&r.separator?(a(),c(`li`,g({key:`separator`+i.toString(),class:[e.cx(`separator`),r.class],style:r.style,role:`separator`},{ref_for:!0},e.ptm(`separator`)),null,16)):(a(),f(b,{key:y.label(r)+i.toString(),id:e.$id+`_`+i,item:r,index:i,templates:e.$slots,focusedOptionId:y.focusedOptionId,unstyled:e.unstyled,onItemClick:y.itemClick,onItemMousemove:y.itemMouseMove,pt:e.pt},null,8,[`id`,`item`,`index`,`templates`,`focusedOptionId`,`unstyled`,`onItemClick`,`onItemMousemove`,`pt`]))],64)}),128))],16,Q),e.$slots.end?(a(),c(`div`,g({key:1,class:e.cx(`end`)},e.ptm(`end`)),[n(e.$slots,`end`)],16)):h(``,!0)],16,Z)):h(``,!0)]}),_:3},16,[`onEnter`,`onLeave`,`onAfterLeave`])]}),_:3},8,[`appendTo`,`disabled`])}X.render=ee;export{X as t};