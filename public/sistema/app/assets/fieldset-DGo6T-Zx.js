import{Et as e,I as t,K as n,N as r,R as i,c as a,d as o,g as s,kt as c,l,q as u,u as d,w as f,z as p}from"./runtime-core.esm-bundler-a6pTXghO.js";import{i as m,nt as h,s as g,t as _}from"./ripple-DLQG0xpb.js";import{K as v,V as y}from"./index-BMrcxrjI.js";import{a as b,r as x}from"./column-1d2ah2pn.js";var S=g.extend({name:`fieldset`,style:`
    .p-fieldset {
        background: dt('fieldset.background');
        border: 1px solid dt('fieldset.border.color');
        border-radius: dt('fieldset.border.radius');
        color: dt('fieldset.color');
        padding: dt('fieldset.padding');
        margin: 0;
    }

    .p-fieldset-legend {
        background: dt('fieldset.legend.background');
        border-radius: dt('fieldset.legend.border.radius');
        border-width: dt('fieldset.legend.border.width');
        border-style: solid;
        border-color: dt('fieldset.legend.border.color');
        color: dt('fieldset.legend.color');
        padding: dt('fieldset.legend.padding');
        transition:
            background dt('fieldset.transition.duration'),
            color dt('fieldset.transition.duration'),
            outline-color dt('fieldset.transition.duration'),
            box-shadow dt('fieldset.transition.duration');
    }

    .p-fieldset-toggleable > .p-fieldset-legend {
        padding: 0;
    }

    .p-fieldset-toggle-button {
        cursor: pointer;
        user-select: none;
        overflow: hidden;
        position: relative;
        text-decoration: none;
        display: flex;
        gap: dt('fieldset.legend.gap');
        align-items: center;
        justify-content: center;
        padding: dt('fieldset.legend.padding');
        background: transparent;
        border: 0 none;
        border-radius: dt('fieldset.legend.border.radius');
        transition:
            background dt('fieldset.transition.duration'),
            color dt('fieldset.transition.duration'),
            outline-color dt('fieldset.transition.duration'),
            box-shadow dt('fieldset.transition.duration');
        outline-color: transparent;
    }

    .p-fieldset-legend-label {
        font-weight: dt('fieldset.legend.font.weight');
    }

    .p-fieldset-toggle-button:focus-visible {
        box-shadow: dt('fieldset.legend.focus.ring.shadow');
        outline: dt('fieldset.legend.focus.ring.width') dt('fieldset.legend.focus.ring.style') dt('fieldset.legend.focus.ring.color');
        outline-offset: dt('fieldset.legend.focus.ring.offset');
    }

    .p-fieldset-toggleable > .p-fieldset-legend:hover {
        color: dt('fieldset.legend.hover.color');
        background: dt('fieldset.legend.hover.background');
    }

    .p-fieldset-toggle-icon {
        color: dt('fieldset.toggle.icon.color');
        transition: color dt('fieldset.transition.duration');
    }

    .p-fieldset-toggleable > .p-fieldset-legend:hover .p-fieldset-toggle-icon {
        color: dt('fieldset.toggle.icon.hover.color');
    }

    .p-fieldset-content-container {
        display: grid;
        grid-template-rows: 1fr;
    }

    .p-fieldset-content-wrapper {
        min-height: 0;
    }

    .p-fieldset-content {
        padding: dt('fieldset.content.padding');
    }
`,classes:{root:function(e){return[`p-fieldset p-component`,{"p-fieldset-toggleable":e.props.toggleable}]},legend:`p-fieldset-legend`,legendLabel:`p-fieldset-legend-label`,toggleButton:`p-fieldset-toggle-button`,toggleIcon:`p-fieldset-toggle-icon`,contentContainer:`p-fieldset-content-container`,contentWrapper:`p-fieldset-content-wrapper`,content:`p-fieldset-content`}}),C={name:`Fieldset`,extends:{name:`BaseFieldset`,extends:m,props:{legend:String,toggleable:Boolean,collapsed:Boolean,toggleButtonProps:{type:null,default:null}},style:S,provide:function(){return{$pcFieldset:this,$parentInstance:this}}},inheritAttrs:!1,emits:[`update:collapsed`,`toggle`],data:function(){return{d_collapsed:this.collapsed}},watch:{collapsed:function(e){this.d_collapsed=e}},methods:{toggle:function(e){this.d_collapsed=!this.d_collapsed,this.$emit(`update:collapsed`,this.d_collapsed),this.$emit(`toggle`,{originalEvent:e,value:this.d_collapsed})},onKeyDown:function(e){(e.code===`Enter`||e.code===`NumpadEnter`||e.code===`Space`)&&(this.toggle(e),e.preventDefault())}},computed:{buttonAriaLabel:function(){return this.toggleButtonProps&&this.toggleButtonProps.ariaLabel?this.toggleButtonProps.ariaLabel:this.legend},dataP:function(){return h({toggleable:this.toggleable})}},directives:{ripple:_},components:{PlusIcon:x,MinusIcon:b}};function w(e){"@babel/helpers - typeof";return w=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},w(e)}function T(e,t){var n=Object.keys(e);if(Object.getOwnPropertySymbols){var r=Object.getOwnPropertySymbols(e);t&&(r=r.filter(function(t){return Object.getOwnPropertyDescriptor(e,t).enumerable})),n.push.apply(n,r)}return n}function E(e){for(var t=1;t<arguments.length;t++){var n=arguments[t]==null?{}:arguments[t];t%2?T(Object(n),!0).forEach(function(t){D(e,t,n[t])}):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(n)):T(Object(n)).forEach(function(t){Object.defineProperty(e,t,Object.getOwnPropertyDescriptor(n,t))})}return e}function D(e,t,n){return(t=O(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function O(e){var t=k(e,`string`);return w(t)==`symbol`?t:t+``}function k(e,t){if(w(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(w(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var A=[`data-p`],j=[`data-p`],M=[`id`],N=[`id`,`aria-controls`,`aria-expanded`,`aria-label`],P=[`id`,`aria-labelledby`];function F(m,h,g,_,b,x){var S=i(`ripple`);return r(),o(`fieldset`,f({class:m.cx(`root`),"data-p":x.dataP},m.ptmi(`root`)),[a(`legend`,f({class:m.cx(`legend`),"data-p":x.dataP},m.ptm(`legend`)),[t(m.$slots,`legend`,{toggleCallback:x.toggle},function(){return[m.toggleable?d(``,!0):(r(),o(`span`,f({key:0,id:m.$id+`_header`,class:m.cx(`legendLabel`)},m.ptm(`legendLabel`)),c(m.legend),17,M)),m.toggleable?u((r(),o(`button`,f({key:1,id:m.$id+`_header`,type:`button`,"aria-controls":m.$id+`_content`,"aria-expanded":!b.d_collapsed,"aria-label":x.buttonAriaLabel,class:m.cx(`toggleButton`),onClick:h[0]||=function(){return x.toggle&&x.toggle.apply(x,arguments)},onKeydown:h[1]||=function(){return x.onKeyDown&&x.onKeyDown.apply(x,arguments)}},E(E({},m.toggleButtonProps),m.ptm(`toggleButton`))),[t(m.$slots,m.$slots.toggleicon?`toggleicon`:`togglericon`,{collapsed:b.d_collapsed,class:e(m.cx(`toggleIcon`))},function(){return[(r(),l(p(b.d_collapsed?`PlusIcon`:`MinusIcon`),f({class:m.cx(`toggleIcon`)},m.ptm(`toggleIcon`)),null,16,[`class`]))]}),a(`span`,f({class:m.cx(`legendLabel`)},m.ptm(`legendLabel`)),c(m.legend),17)],16,N)),[[S]]):d(``,!0)]})],16,j),s(y,f({name:`p-collapsible`},m.ptm(`transition`)),{default:n(function(){return[u(a(`div`,f({id:m.$id+`_content`,class:m.cx(`contentContainer`),role:`region`,"aria-labelledby":m.$id+`_header`},m.ptm(`contentContainer`)),[a(`div`,f({class:m.cx(`contentWrapper`)},m.ptm(`contentWrapper`)),[a(`div`,f({class:m.cx(`content`)},m.ptm(`content`)),[t(m.$slots,`default`)],16)],16)],16,P),[[v,!b.d_collapsed]])]}),_:3},16)],16,A)}C.render=F;export{C as t};