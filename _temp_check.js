
(function(){
"use strict";
var ajaxUrl="modules/addons/broodle_whmcs_tools/ajax.php";
var wpAjaxUrl="modules/addons/broodle_whmcs_tools/ajax_wordpress.php";
var C={};
var wpInstances=[];var currentWpInstance=null;

function esc(s){var d=document.createElement("div");d.textContent=s;return d.innerHTML;}
function $(id){return document.getElementById(id);}
function showMsg(el,msg,ok){el.textContent=msg;el.className="bt-msg "+(ok?"success":"error");el.style.display="block";}
function ajax(url,data,cb){
    var fd=new FormData();for(var k in data)fd.append(k,data[k]);
    fd.append("service_id",C.serviceId);
    var x=new XMLHttpRequest();x.open("POST",url,true);
    x.onload=function(){try{cb(JSON.parse(x.responseText));}catch(e){cb({success:false,message:"Invalid response"});}};
    x.onerror=function(){cb({success:false,message:"Network error"});};
    x.send(fd);
}
function post(data,cb){ajax(ajaxUrl,data,cb);}
function wpPost(data,cb){ajax(wpAjaxUrl,data,cb);}
function doCopy(t,btn){if(navigator.clipboard){navigator.clipboard.writeText(t).then(function(){btn.classList.add("copied");setTimeout(function(){btn.classList.remove("copied");},1500);});}else{var ta=document.createElement("textarea");ta.value=t;ta.style.cssText="position:fixed;opacity:0";document.body.appendChild(ta);ta.select();document.execCommand("copy");document.body.removeChild(ta);btn.classList.add("copied");setTimeout(function(){btn.classList.remove("copied");},1500);}}

var wpSvg16="<svg width=\\"16\\" height=\\"16\\" viewBox=\\"0 0 16 16\\" fill=\\"currentColor\\"><path d=\\"M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046zM1.137 8a6.86 6.86 0 0 0 3.868 6.176L1.73 5.206A6.8 6.8 0 0 0 1.137 8\\"/><path d=\\"M6.061 14.583 8.121 8.6l2.109 5.78q.02.05.049.094a6.85 6.85 0 0 1-4.218.109m7.96-9.876q.046.328.047.706c0 .696-.13 1.479-.522 2.458l-2.096 6.06a6.86 6.86 0 0 0 2.572-9.224z\\"/><path fill-rule=\\"evenodd\\" d=\\"M0 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.59 8-8 8-8-3.589-8-8m.367 0c0 4.209 3.424 7.633 7.633 7.633S15.632 12.209 15.632 8C15.632 3.79 12.208.367 8 .367 3.79.367.367 3.79.367 8\\"/></svg>";
var wpSvg20=wpSvg16.replace(/width=\\"16\\"/g,"width=\\"20\\"").replace(/height=\\"16\\"/g,"height=\\"20\\"");
var wpSvg32=wpSvg16.replace(/width=\\"16\\"/g,"width=\\"32\\"").replace(/height=\\"16\\"/g,"height=\\"32\\"");

/* â”€â”€â”€ Addon/Upgrade Icon Helpers â”€â”€â”€ */
function btAddonIcon(name){
    var n=name.toLowerCase();
    if(n.indexOf("wordpress")!==-1||n.indexOf("wp ")!==-1||n.indexOf("wp manager")!==-1) return wpSvg16;
    if(n.indexOf("site builder")!==-1||n.indexOf("sitebuilder")!==-1||n.indexOf("weebly")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x273\\x27 y=\\x273\\x27 width=\\x2718\\x27 height=\\x2718\\x27 rx=\\x272\\x27/><path d=\\x27M3 9h18M9 21V9\\x27/></svg>";
    if(n.indexOf("ssl")!==-1||n.indexOf("certificate")!==-1||n.indexOf("digicert")!==-1||n.indexOf("geotrust")!==-1||n.indexOf("rapidssl")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z\\x27/></svg>";
    if(n.indexOf("aapanel")!==-1||n.indexOf("cyberpanel")!==-1||n.indexOf("control panel")!==-1||n.indexOf("cpanel")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x272\\x27 y=\\x273\\x27 width=\\x2720\\x27 height=\\x2714\\x27 rx=\\x272\\x27/><line x1=\\x278\\x27 y1=\\x2721\\x27 x2=\\x2716\\x27 y2=\\x2721\\x27/><line x1=\\x2712\\x27 y1=\\x2717\\x27 x2=\\x2712\\x27 y2=\\x2721\\x27/></svg>";
    if(n.indexOf("store")!==-1||n.indexOf("ecommerce")!==-1||n.indexOf("woocommerce")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><circle cx=\\x279\\x27 cy=\\x2721\\x27 r=\\x271\\x27/><circle cx=\\x2720\\x27 cy=\\x2721\\x27 r=\\x271\\x27/><path d=\\x271 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6\\x27/></svg>";
    if(n.indexOf("ip address")!==-1||n.indexOf("public ip")!==-1||n.indexOf("additional ip")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><circle cx=\\x2712\\x27 cy=\\x2712\\x27 r=\\x2710\\x27/><line x1=\\x272\\x27 y1=\\x2712\\x27 x2=\\x2722\\x27 y2=\\x2712\\x27/><path d=\\x27M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\\x27/></svg>";
    return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z\\x27/></svg>";
}
function btUpgradeIcon(name){
    var n=name.toLowerCase();
    if(n.indexOf("ram")!==-1||n.indexOf("memory")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x272\\x27 y=\\x276\\x27 width=\\x2720\\x27 height=\\x2712\\x27 rx=\\x272\\x27/><path d=\\x276 6V4M10 6V4M14 6V4M18 6V4\\x27/></svg>";
    if(n.indexOf("backup")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\\x27/><polyline points=\\x277 10 12 15 17 10\\x27/><line x1=\\x2712\\x27 y1=\\x2715\\x27 x2=\\x2712\\x27 y2=\\x273\\x27/></svg>";
    if(n.indexOf("cpu")!==-1||n.indexOf("vcpu")!==-1||n.indexOf("core")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x274\\x27 y=\\x274\\x27 width=\\x2716\\x27 height=\\x2716\\x27 rx=\\x272\\x27/><rect x=\\x279\\x27 y=\\x279\\x27 width=\\x276\\x27 height=\\x276\\x27/><path d=\\x272 9h2M2 15h2M20 9h2M20 15h2M9 2v2M15 2v2M9 20v2M15 20v2\\x27/></svg>";
    if(n.indexOf("disk")!==-1||n.indexOf("storage")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><ellipse cx=\\x2712\\x27 cy=\\x275\\x27 rx=\\x279\\x27 ry=\\x273\\x27/><path d=\\x27M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\\x27/><path d=\\x27M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\\x27/></svg>";
    if(n.indexOf("bandwidth")!==-1) return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><path d=\\x27M22 12h-4l-3 9L9 3l-3 9H2\\x27/></svg>";
    return "<svg width=\\x2716\\x27 height=\\x2716\\x27 viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><polyline points=\\x2723 6 13.5 15.5 8.5 10.5 1 18\\x27/><polyline points=\\x2717 6 23 6 23 12\\x27/></svg>";
}

/* â”€â”€â”€ Init â”€â”€â”€ */
function init(){
    var dataEl=$("bt-data");
    if(!dataEl) return;
    try{C=JSON.parse(dataEl.getAttribute("data-config"));}catch(e){return;}
    hideDefaultTabs();
    buildTabs();
    bindModals();
}

function hideDefaultTabs(){
    var selectors=["ul.panel-tabs.nav.nav-tabs",".product-details-tab-container",".section-body > ul.nav.nav-tabs",".panel > ul.nav.nav-tabs"];
    selectors.forEach(function(sel){
        document.querySelectorAll(sel).forEach(function(el){
            el.style.display="none";
            var sib=el.nextElementSibling;
            while(sib){if(sib.classList&&(sib.classList.contains("tab-content")||sib.classList.contains("product-details-tab-container"))){sib.style.display="none";break;}sib=sib.nextElementSibling;}
        });
    });
    ["billingInfo","tabOverview","domainInfo","tabAddons"].forEach(function(id){var el=$(id);if(el)el.style.display="none";});
    var panelTabs=document.querySelector("ul.panel-tabs");
    if(panelTabs){var panel=panelTabs.closest(".panel");if(panel) panel.style.display="none";}
    // Hide Quick Create Email section
    document.querySelectorAll(".quick-create-email,.quick-create-email-section,[class*=quick-create-email],.module-quick-create-email,.quick-create-section,.module-quick-shortcuts,.quick-shortcuts-container,.quick-shortcuts,.quick-shortcut-container,.quick-shortcut,.sidebar-shortcuts,.sidebar-quick-create,[class*=quick-create],[class*=quick-shortcut],#cPanelQuickEmailPanel,#cPanelExtrasPurchasePanel").forEach(function(el){el.style.display="none";});
    // Hide .section elements by title text (Lagom theme uses .section > .section-header > h2.section-title)
    document.querySelectorAll(".section").forEach(function(sec){
        var title=sec.querySelector(".section-title,h2,h3");
        if(!title) return;
        var t=(title.textContent||"").toLowerCase().trim();
        if(t.indexOf("quick create email")!==-1||t.indexOf("addons and extras")!==-1||t.indexOf("addon")!==-1&&t.indexOf("extra")!==-1){
            sec.classList.add("bt-hidden-section");sec.style.display="none";
            sec.setAttribute("data-bt-hidden","1");
        }
    });
    // Hide Addons & Extras panels (we move content to overview)
    document.querySelectorAll(".panel,.card,.sidebar-box,.sidebar-panel").forEach(function(p){
        var h=p.querySelector(".panel-heading,.card-header,h3,h4,h5,.panel-title,.sidebar-header,.sidebar-title");
        if(!h) return;
        var t=(h.textContent||"").toLowerCase();
        if(t.indexOf("addon")!==-1||t.indexOf("extra")!==-1||t.indexOf("configurable")!==-1||t.indexOf("quick create email")!==-1||t.indexOf("quick create")!==-1||t.indexOf("shortcut")!==-1){
            p.setAttribute("data-bt-hidden","1");p.style.display="none";
        }
    });
    // Also hide by sidebar menu item IDs
    ["Primary_Sidebar-productdetails_addons_and_extras"].forEach(function(id){var el=$(id);if(el)el.style.display="none";});
}

function buildTabs(){
    var target=document.querySelector(".panel");
    if(!target) target=document.querySelector(".section-body");
    if(!target) return;
    var hiddenPanel=document.querySelector("ul.panel-tabs");
    var insertAfter=hiddenPanel?hiddenPanel.closest(".panel"):null;
    if(!insertAfter) insertAfter=target;

    var wrap=document.createElement("div");
    wrap.className="bt-wrap";wrap.id="bt-wrap";

    // WordPress icon â€” official WP logo
    var wpIcon="<svg viewBox=\\x270 0 16 16\\x27 fill=\\x27currentColor\\x27><path d=\\x27M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046zM1.137 8a6.86 6.86 0 0 0 3.868 6.176L1.73 5.206A6.8 6.8 0 0 0 1.137 8\\x27/><path d=\\x27M6.061 14.583 8.121 8.6l2.109 5.78q.02.05.049.094a6.85 6.85 0 0 1-4.218.109m7.96-9.876q.046.328.047.706c0 .696-.13 1.479-.522 2.458l-2.096 6.06a6.86 6.86 0 0 0 2.572-9.224z\\x27/><path fill-rule=\\x27evenodd\\x27 d=\\x27M0 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.59 8-8 8-8-3.589-8-8m.367 0c0 4.209 3.424 7.633 7.633 7.633S15.632 12.209 15.632 8C15.632 3.79 12.208.367 8 .367 3.79.367.367 3.79.367 8\\x27/></svg>";

    var tabs=[
        {id:"overview",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x273\\x27 y=\\x273\\x27 width=\\x2718\\x27 height=\\x2718\\x27 rx=\\x272\\x27/><path d=\\x27M3 9h18M9 21V9\\x27/></svg>",label:"Overview"},
        {id:"domains",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><circle cx=\\x2712\\x27 cy=\\x2712\\x27 r=\\x2710\\x27/><line x1=\\x272\\x27 y1=\\x2712\\x27 x2=\\x2722\\x27 y2=\\x2712\\x27/><path d=\\x27M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\\x27/></svg>",label:"Domains",check:"domainEnabled"},
        {id:"email",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><rect x=\\x272\\x27 y=\\x274\\x27 width=\\x2720\\x27 height=\\x2716\\x27 rx=\\x272\\x27/><path d=\\x27m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\\x27/></svg>",label:"Email Accounts",check:"emailEnabled"},
        {id:"databases",icon:"<svg viewBox=\\x270 0 24 24\\x27 fill=\\x27none\\x27 stroke=\\x27currentColor\\x27 stroke-width=\\x272\\x27><ellipse cx=\\x2712\\x27 cy=\\x275\\x27 rx=\\x279\\x27 ry=\\x273\\x27/><path d=\\x27M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\\x27/><path d=\\x27M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\\x27/></svg>",label:"Databases",check:"dbEnabled"},
        {id:"wordpress",icon:wpIcon,label:"WordPress",check:"wpEnabled"}
    ];

    var nav=document.createElement("div");nav.className="bt-tabs-nav";
    var panes=document.createElement("div");
    var firstTab=true;

    tabs.forEach(function(t){
        if(t.check&&!C[t.check]) return;
        var btn=document.createElement("button");
        btn.type="button";
        btn.className="bt-tab-btn"+(firstTab?" active":"");
        btn.setAttribute("data-tab",t.id);
        btn.innerHTML=t.icon+" "+t.label;
        btn.addEventListener("click",function(){
            nav.querySelectorAll(".bt-tab-btn").forEach(function(b){b.classList.remove("active");});
            panes.querySelectorAll(".bt-tab-pane").forEach(function(p){p.classList.remove("active");});
            btn.classList.add("active");
            var pane=$("bt-pane-"+t.id);if(pane) pane.classList.add("active");
            if(t.id==="databases"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadDatabases();}
            if(t.id==="wordpress"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadWpInstances();}
        });
        nav.appendChild(btn);

        var pane=document.createElement("div");
        pane.className="bt-tab-pane"+(firstTab?" active":"");
        pane.id="bt-pane-"+t.id;
        panes.appendChild(pane);
        firstTab=false;
    });

    wrap.appendChild(nav);wrap.appendChild(panes);
    if(insertAfter&&insertAfter.parentNode) insertAfter.parentNode.insertBefore(wrap,insertAfter.nextSibling);
    else document.querySelector(".main-content,.content-padded,.section-body,.container").appendChild(wrap);

    buildOverviewPane();
    if(C.domainEnabled) buildDomainsPane();
    if(C.emailEnabled) buildEmailPane();
    if(C.dbEnabled) buildDatabasesPane();
    if(C.wpEnabled) buildWpPane();
}

/* â”€â”€â”€ Overview Pane (improved) â”€â”€â”€ */
function buildOverviewPane(){
    var pane=$("bt-pane-overview");if(!pane) return;
    var pairs=[];
    var billingEl=$("billingInfo")||$("tabOverview");
    if(billingEl){
        billingEl.querySelectorAll(".col-sm-6.col-md-3.m-b-2x,.col-sm-6.col-md-3").forEach(function(col){
            var lbl=col.querySelector(".text-faded.text-small,.text-faded");if(!lbl) return;
            var label=lbl.textContent.trim().replace(/:$/,"");
            var sib=lbl.nextElementSibling;var val=sib?sib.innerHTML.trim():"";
            if(!val){var c=col.cloneNode(true);var l2=c.querySelector(".text-faded");if(l2)l2.remove();val=c.innerHTML.trim();}
            if(label&&val) pairs.push({label:label,value:val});
        });
        if(!pairs.length){var rc=billingEl.querySelector(".col-md-6.text-center");
            if(rc){rc.querySelectorAll("h4").forEach(function(h4){
                var label=h4.textContent.trim().replace(/:$/,"");var val="";var s=h4.nextSibling;
                while(s&&!(s.nodeType===1&&s.tagName==="H4")){if(s.nodeType===3)val+=s.textContent.trim();else if(s.nodeType===1)val+=s.outerHTML;s=s.nextSibling;}
                val=val.trim();if(label&&val)pairs.push({label:label,value:val});
            });}}
        if(!pairs.length){billingEl.querySelectorAll(".row").forEach(function(r){
            var l=r.querySelector(".col-sm-5,.col-md-5");var v=r.querySelector(".col-sm-7,.col-md-7");
            if(l&&v)pairs.push({label:l.textContent.trim().replace(/:$/,""),value:v.innerHTML.trim()});
        });}
        billingEl.style.display="none";
    }

    var html="";
    if(pairs.length){
        html+="<div class=\"bt-ov-grid\">";
        pairs.forEach(function(p){
            var lbl=p.label.toLowerCase();
            var extra="";
            // Detect due date / next due date fields and add color + days remaining
            if(lbl.indexOf("due")!==-1||lbl.indexOf("renewal")!==-1||lbl.indexOf("expir")!==-1){
                var dateMatch=p.value.match(/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/);
                if(!dateMatch) dateMatch=p.value.match(/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
                if(dateMatch){
                    var dueDate;
                    if(dateMatch[3]&&dateMatch[3].length===4) dueDate=new Date(parseInt(dateMatch[3]),parseInt(dateMatch[1])-1,parseInt(dateMatch[2]));
                    else dueDate=new Date(parseInt(dateMatch[1]),parseInt(dateMatch[2])-1,parseInt(dateMatch[3]));
                    var now=new Date();now.setHours(0,0,0,0);dueDate.setHours(0,0,0,0);
                    var diff=Math.ceil((dueDate-now)/(1000*60*60*24));
                    var cls="bt-ov-due-ok";
                    if(diff<0){cls="bt-ov-due-past";extra="<span class=\"bt-ov-days "+cls+"\">Overdue by "+Math.abs(diff)+" day"+(Math.abs(diff)!==1?"s":"")+"</span>";}
                    else if(diff===0){cls="bt-ov-due-danger";extra="<span class=\"bt-ov-days "+cls+"\">Due today</span>";}
                    else if(diff<=7){cls="bt-ov-due-danger";extra="<span class=\"bt-ov-days "+cls+"\">"+diff+" day"+(diff!==1?"s":"")+" left</span>";}
                    else if(diff<=30){cls="bt-ov-due-warn";extra="<span class=\"bt-ov-days "+cls+"\">"+diff+" days left</span>";}
                    else{extra="<span class=\"bt-ov-days "+cls+"\">"+diff+" days left</span>";}
                    p.value="<span class=\""+cls+"\">"+p.value+"</span>";
                }
            }
            html+="<div class=\"bt-ov-card\"><div class=\"bt-ov-label\">"+esc(p.label)+"</div><div class=\"bt-ov-value\">"+p.value+extra+"</div></div>";
        });
        html+="</div>";
    }

    // Nameservers (accordion, closed by default)
    if(C.nsEnabled&&C.ns&&C.ns.ns&&C.ns.ns.length){
        var nsCount=C.ns.ns.length+(C.ns.ip?1:0);
        html+="<div class=\"bt-accordion\" id=\"btAccNs\"><div class=\"bt-accordion-head\" onclick=\"this.parentElement.classList.toggle(\\x27open\\x27)\"><div class=\"bt-accordion-icon\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"/><path d=\"M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\"/></svg></div><div class=\"bt-accordion-info\"><h5>Nameservers</h5><p>"+nsCount+" record"+(nsCount!==1?"s":"")+" Â· Point your domain to these nameservers</p></div><svg class=\"bt-accordion-arrow\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"6 9 12 15 18 9\"/></svg></div><div class=\"bt-accordion-body\"><div class=\"bt-list\" style=\"padding:4px 10px 10px\">";
        C.ns.ns.forEach(function(ns,i){
            html+="<div class=\"bt-row\"><div class=\"bt-row-icon ns\">NS"+(i+1)+"</div><div class=\"bt-row-info\"><span class=\"bt-row-name mono\">"+esc(ns)+"</span></div><button type=\"button\" class=\"bt-copy\" data-copy=\""+esc(ns)+"\"><svg width=\"15\" height=\"15\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><rect x=\"9\" y=\"9\" width=\"13\" height=\"13\" rx=\"2\" ry=\"2\"/><path d=\"M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1\"/></svg></button></div>";
        });
        if(C.ns.ip){
            html+="<div class=\"bt-row\"><div class=\"bt-row-icon ip\">IP</div><div class=\"bt-row-info\"><span class=\"bt-row-name mono\">"+esc(C.ns.ip)+"</span></div><button type=\"button\" class=\"bt-copy\" data-copy=\""+esc(C.ns.ip)+"\"><svg width=\"15\" height=\"15\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><rect x=\"9\" y=\"9\" width=\"13\" height=\"13\" rx=\"2\" ry=\"2\"/><path d=\"M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1\"/></svg></button></div>";
        }
        html+="</div></div></div>";
    }

    // Addons/Upgrades â€” parse from hidden WHMCS panels into clean UI
    var addonItems=[];var upgradeItems=[];
    // Parse the Addons & Extras select options
    var extrasPanel=$("cPanelExtrasPurchasePanel");
    if(extrasPanel){
        var form=extrasPanel.querySelector("form");
        var tokenInput=form?form.querySelector("input[name=token]"):null;
        var svcInput=form?form.querySelector("input[name=serviceid]"):null;
        var token=tokenInput?tokenInput.value:"";
        var svcId=svcInput?svcInput.value:"";
        extrasPanel.querySelectorAll("select[name=aid] option").forEach(function(opt){
            var name=opt.textContent.trim();var aid=opt.value;
            if(!name||!aid) return;
            // Categorize: backup/ram/resource = upgrade, rest = addon
            var nl=name.toLowerCase();
            if(nl.indexOf("backup")!==-1||nl.indexOf("ram")!==-1||nl.indexOf("cpu")!==-1||nl.indexOf("disk")!==-1||nl.indexOf("bandwidth")!==-1||nl.indexOf("upgrade")!==-1||nl.indexOf("resource")!==-1){
                upgradeItems.push({name:name,aid:aid,token:token,svcId:svcId});
            }else{
                addonItems.push({name:name,aid:aid,token:token,svcId:svcId});
            }
        });
    }
    // Also check tabAddons for configurable options / upgrade links (not email forms)
    var addonsEl=$("tabAddons");
    if(addonsEl){
        addonsEl.querySelectorAll("a[href*=upgrade],a[href*=configoptions],.btn[href*=upgrade]").forEach(function(a){
            var txt=a.textContent.trim();var href=a.getAttribute("href")||"";
            if(txt&&href) upgradeItems.push({name:txt,link:href});
        });
        addonsEl.style.display="none";
    }
    // Render combined addons & upgrades carousel
    var allItems=[];
    addonItems.forEach(function(a){allItems.push({name:a.name,aid:a.aid,token:a.token,svcId:a.svcId,type:"addon"});});
    upgradeItems.forEach(function(u){allItems.push({name:u.name,aid:u.aid||"",token:u.token||"",svcId:u.svcId||"",link:u.link||"",type:"upgrade"});});
    if(allItems.length){
        var perPage=window.innerWidth<=600?4:4;
        var pages=[];for(var pi=0;pi<allItems.length;pi+=perPage){pages.push(allItems.slice(pi,pi+perPage));}
        html+="<div class=\"bt-accordion\" id=\"btAccAddons\"><div class=\"bt-accordion-head\" onclick=\"this.parentElement.classList.toggle(\\x27open\\x27)\"><div class=\"bt-accordion-icon\" style=\"background:rgba(124,58,237,1)\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z\"/></svg></div><div class=\"bt-accordion-info\"><h5>Addons &amp; Upgrades</h5><p>"+allItems.length+" available Â· Enhance your hosting</p></div><svg class=\"bt-accordion-arrow\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"6 9 12 15 18 9\"/></svg></div><div class=\"bt-accordion-body\"><div class=\"bt-addon-wrap\"><button type=\"button\" class=\"bt-addon-nav prev"+(pages.length<=1?" hidden":"")+"\" id=\"btAddonPrev\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"15 18 9 12 15 6\"/></svg></button><button type=\"button\" class=\"bt-addon-nav next"+(pages.length<=1?" hidden":"")+"\" id=\"btAddonNext\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"9 18 15 12 9 6\"/></svg></button><div class=\"bt-addon-scroll\" id=\"btAddonScroll\">";
        pages.forEach(function(page){
            html+="<div class=\"bt-addon-page\">";
            page.forEach(function(item){
                var icon=item.type==="upgrade"?btUpgradeIcon(item.name):btAddonIcon(item.name);
                var iconCls=item.type==="upgrade"?"upgrade":"addon";
                var btnHtml="";
                if(item.link){
                    btnHtml="<a href=\""+esc(item.link)+"\" class=\"bt-addon-btn\">Get</a>";
                }else{
                    btnHtml="<form method=\"post\" action=\"cart.php?a=add\" style=\"margin:0\"><input type=\"hidden\" name=\"token\" value=\""+esc(item.token)+"\"><input type=\"hidden\" name=\"serviceid\" value=\""+esc(item.svcId)+"\"><input type=\"hidden\" name=\"aid\" value=\""+esc(item.aid)+"\"><button type=\"submit\" class=\"bt-addon-btn\">Get</button></form>";
                }
                html+="<div class=\"bt-addon-item\"><div class=\"bt-addon-icon "+iconCls+"\">"+icon+"</div><div class=\"bt-addon-text\"><span class=\"bt-addon-name\" title=\""+esc(item.name)+"\">"+esc(item.name)+"</span><span class=\"bt-addon-price\" data-aid=\""+(item.aid||"")+"\"></span></div><div class=\"bt-addon-tip-wrap\"><button type=\"button\" class=\"bt-addon-tip-btn\" data-aid=\""+(item.aid||"")+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><path d=\"M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3\"/><line x1=\"12\" y1=\"17\" x2=\"12.01\" y2=\"17\"/></svg></button><div class=\"bt-addon-tooltip\">Loading...</div></div>"+btnHtml+"</div>";
            });
            html+="</div>";
        });
        html+="</div>";
        if(pages.length>1){
            html+="<div class=\"bt-addon-dots\" id=\"btAddonDots\">";
            for(var di=0;di<pages.length;di++) html+="<button type=\"button\" class=\"bt-addon-dot"+(di===0?" active":"")+"\" data-page=\""+di+"\"></button>";
            html+="</div>";
        }
        html+="</div></div></div>";
    }

    pane.innerHTML=html;
    pane.querySelectorAll(".bt-copy").forEach(function(b){b.addEventListener("click",function(){doCopy(this.getAttribute("data-copy"),this);});});
    // Carousel nav for addons
    var scroller=$("btAddonScroll");
    if(scroller){
        var curPage=0;var totalPages=scroller.querySelectorAll(".bt-addon-page").length;
        function goToPage(p){
            if(p<0||p>=totalPages) return;
            curPage=p;
            scroller.children[p].scrollIntoView({behavior:"smooth",block:"nearest",inline:"start"});
            var dots=$("btAddonDots");
            if(dots) dots.querySelectorAll(".bt-addon-dot").forEach(function(d,i){d.classList.toggle("active",i===p);});
            var prev=$("btAddonPrev");var next=$("btAddonNext");
            if(prev) prev.classList.toggle("hidden",p===0);
            if(next) next.classList.toggle("hidden",p===totalPages-1);
        }
        var prev=$("btAddonPrev");if(prev) prev.addEventListener("click",function(){goToPage(curPage-1);});
        var next=$("btAddonNext");if(next) next.addEventListener("click",function(){goToPage(curPage+1);});
        var dots=$("btAddonDots");
        if(dots) dots.querySelectorAll(".bt-addon-dot").forEach(function(d){d.addEventListener("click",function(){goToPage(parseInt(this.getAttribute("data-page")));});});
        // Drag support
        var dragStartX=0;var dragScrollLeft=0;var isDragging=false;
        function onDragStart(e){
            isDragging=true;scroller.classList.add("dragging");
            dragStartX=(e.touches?e.touches[0].pageX:e.pageX)-scroller.offsetLeft;
            dragScrollLeft=scroller.scrollLeft;
        }
        function onDragMove(e){
            if(!isDragging) return;
            var x=(e.touches?e.touches[0].pageX:e.pageX)-scroller.offsetLeft;
            scroller.scrollLeft=dragScrollLeft-(x-dragStartX);
        }
        function onDragEnd(){
            if(!isDragging) return;
            isDragging=false;scroller.classList.remove("dragging");
            // Snap to nearest page
            var w=scroller.offsetWidth;var nearest=Math.round(scroller.scrollLeft/w);
            goToPage(Math.max(0,Math.min(nearest,totalPages-1)));
        }
        scroller.addEventListener("mousedown",onDragStart);
        scroller.addEventListener("mousemove",onDragMove);
        scroller.addEventListener("mouseup",onDragEnd);
        scroller.addEventListener("mouseleave",onDragEnd);
        scroller.addEventListener("touchstart",onDragStart,{passive:true});
        scroller.addEventListener("touchmove",onDragMove,{passive:true});
        scroller.addEventListener("touchend",onDragEnd);
        // Tooltip + pricing: fetch on hover/click
        var tipCache={};
        pane.querySelectorAll(".bt-addon-tip-wrap").forEach(function(wrap){
            var btn=wrap.querySelector(".bt-addon-tip-btn");
            var tip=wrap.querySelector(".bt-addon-tooltip");
            var aid=btn?btn.getAttribute("data-aid"):"";
            function loadTip(){
                if(!aid||!tip) return;
                if(tipCache[aid]){tip.textContent=tipCache[aid];return;}
                btn.classList.add("loading");
                post({action:"get_addon_description",addon_id:aid},function(r){
                    btn.classList.remove("loading");
                    var desc=(r.success&&r.description)?r.description:"No description available";
                    tipCache[aid]=desc;tip.textContent=desc;
                    // Also set pricing
                    if(r.price){
                        pane.querySelectorAll(".bt-addon-price[data-aid=\\x22"+aid+"\\x22]").forEach(function(pe){pe.textContent=r.price;pe.classList.add("visible");});
                    }
                });
            }
            wrap.addEventListener("mouseenter",loadTip);
            btn.addEventListener("click",function(e){e.stopPropagation();loadTip();wrap.classList.toggle("show-tip");});
        });
        document.addEventListener("click",function(){pane.querySelectorAll(".bt-addon-tip-wrap.show-tip").forEach(function(w){w.classList.remove("show-tip");});});
        // Prefetch pricing for visible page
        var firstPageItems=pane.querySelectorAll(".bt-addon-page:first-child .bt-addon-tip-btn[data-aid]");
        firstPageItems.forEach(function(btn){
            var aid=btn.getAttribute("data-aid");if(!aid||tipCache[aid]) return;
            post({action:"get_addon_description",addon_id:aid},function(r){
                if(r.success){
                    tipCache[aid]=r.description||"No description available";
                    if(r.price){
                        pane.querySelectorAll(".bt-addon-price[data-aid=\\x22"+aid+"\\x22]").forEach(function(pe){pe.textContent=r.price;pe.classList.add("visible");});
                    }
                }
            });
        });
    }
}

/* â”€â”€â”€ Domains Pane â”€â”€â”€ */
function buildDomainsPane(){
    var pane=$("bt-pane-domains");if(!pane||!C.domains) return;
    var d=C.domains;var total=1+(d.addon?d.addon.length:0)+(d.sub?d.sub.length:0)+(d.parked?d.parked.length:0);
    var html="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"/><path d=\"M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z\"/></svg></div><div><h5>Domains</h5><p class=\"bt-dom-count\">"+total+" domain"+(total!==1?"s":"")+"</p></div></div><div class=\"bt-card-head-right\"><button type=\"button\" class=\"bt-btn-add\" id=\"bdmAddAddonBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/></svg> Add Domain</button><button type=\"button\" class=\"bt-btn-outline\" id=\"bdmAddSubBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"16 3 21 3 21 8\"/><line x1=\"4\" y1=\"20\" x2=\"21\" y2=\"3\"/></svg> Add Subdomain</button></div></div><div class=\"bt-list\" id=\"bt-dom-list\">";
    if(d.main) html+=domRow(d.main,"main","Primary",false);
    if(d.addon) d.addon.forEach(function(dm){html+=domRow(dm,"addon","Addon",true);});
    if(d.sub) d.sub.forEach(function(dm){html+=domRow(dm,"sub","Subdomain",true);});
    if(d.parked) d.parked.forEach(function(dm){html+=domRow(dm,"parked","Alias",true);});
    html+="</div></div>";
    pane.innerHTML=html;
    bindDomainActions(pane);
    $("bdmAddAddonBtn").addEventListener("click",openAddonModal);
    $("bdmAddSubBtn").addEventListener("click",openSubModal);
}
function domRow(name,type,badge,canDel){
    var e=esc(name);var badgeClass=type==="main"?"bt-badge-primary":type==="addon"?"bt-badge-green":type==="sub"?"bt-badge-purple":"bt-badge-amber";
    return "<div class=\"bt-row\" data-domain=\""+e+"\" data-type=\""+type+"\"><div class=\"bt-row-icon "+type+"\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"2\" y1=\"12\" x2=\"22\" y2=\"12\"/></svg></div><div class=\"bt-row-info\"><span class=\"bt-row-name\">"+e+"</span><span class=\"bt-row-badge "+badgeClass+"\">"+badge+"</span></div><div class=\"bt-row-actions\"><a href=\"https://"+e+"\" target=\"_blank\" class=\"bt-row-btn visit\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/><polyline points=\"15 3 21 3 21 9\"/><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"/></svg><span>Visit</span></a>"+(canDel?"<button type=\"button\" class=\"bt-row-btn del\" data-domain=\""+e+"\" data-type=\""+type+"\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg><span>Delete</span></button>":"")+"</div></div>";
}

/* â”€â”€â”€ Email Pane â”€â”€â”€ */
function buildEmailPane(){
    var pane=$("bt-pane-email");if(!pane) return;
    var emails=C.emails||[];var count=emails.length;
    var html="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><rect x=\"2\" y=\"4\" width=\"20\" height=\"16\" rx=\"2\"/><path d=\"m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\"/></svg></div><div><h5>Email Accounts</h5><p class=\"bt-email-count\">"+(count===1?"1 account":count+" accounts")+"</p></div></div><div class=\"bt-card-head-right\"><button type=\"button\" class=\"bt-btn-add\" id=\"bemCreateBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/></svg> Create Email</button></div></div><div class=\"bt-list\" id=\"bt-email-list\">";
    if(!count) html+="<div class=\"bt-empty\"><svg width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\"><rect x=\"2\" y=\"4\" width=\"20\" height=\"16\" rx=\"2\"/><path d=\"m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7\"/></svg><span>No email accounts found</span></div>";
    else emails.forEach(function(em){html+=emailRow(em);});
    html+="</div></div>";
    pane.innerHTML=html;bindEmailActions(pane);
    $("bemCreateBtn").addEventListener("click",openCreateEmailModal);
}
function emailRow(email){
    var e=esc(email);var ini=email.charAt(0).toUpperCase();
    return "<div class=\"bt-row\" data-email=\""+e+"\"><div class=\"bt-row-icon email\">"+ini+"</div><div class=\"bt-row-info\"><span class=\"bt-row-name\">"+e+"</span></div><div class=\"bt-row-actions\"><button type=\"button\" class=\"bt-row-btn login\" data-email=\""+e+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4\"/><polyline points=\"10 17 15 12 10 7\"/><line x1=\"15\" y1=\"12\" x2=\"3\" y2=\"12\"/></svg><span>Login</span></button><button type=\"button\" class=\"bt-row-btn pass\" data-email=\""+e+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><rect x=\"3\" y=\"11\" width=\"18\" height=\"11\" rx=\"2\" ry=\"2\"/><path d=\"M7 11V7a5 5 0 0 1 10 0v4\"/></svg><span>Password</span></button><button type=\"button\" class=\"bt-row-btn del\" data-email=\""+e+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg><span>Delete</span></button></div></div>";
}

/* â”€â”€â”€ Databases Pane â”€â”€â”€ */
function buildDatabasesPane(){
    var pane=$("bt-pane-databases");if(!pane) return;
    pane.innerHTML="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><ellipse cx=\"12\" cy=\"5\" rx=\"9\" ry=\"3\"/><path d=\"M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\"/><path d=\"M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\"/></svg></div><div><h5>Databases</h5><p class=\"bt-db-count\">Loading...</p></div></div><div class=\"bt-card-head-right\"><button type=\"button\" class=\"bt-btn-add\" id=\"bdbCreateBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><line x1=\"12\" y1=\"5\" x2=\"12\" y2=\"19\"/><line x1=\"5\" y1=\"12\" x2=\"19\" y2=\"12\"/></svg> New Database</button><button type=\"button\" class=\"bt-btn-outline\" id=\"bdbUserBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2\"/><circle cx=\"12\" cy=\"7\" r=\"4\"/></svg> New User</button><button type=\"button\" class=\"bt-btn-outline\" id=\"bdbAssignBtn\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2\"/><circle cx=\"8.5\" cy=\"7\" r=\"4\"/><line x1=\"20\" y1=\"8\" x2=\"20\" y2=\"14\"/><line x1=\"23\" y1=\"11\" x2=\"17\" y2=\"11\"/></svg> Assign</button><a class=\"bt-btn-outline\" id=\"bdbPmaBtn\" href=\"#\" target=\"_blank\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/><polyline points=\"15 3 21 3 21 9\"/><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"/></svg> phpMyAdmin</a></div></div><div class=\"bt-list\" id=\"bt-db-list\"><div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading databases...</span></div></div></div>";
    $("bdbCreateBtn").addEventListener("click",function(){$("bdbCreateModal").style.display="flex";$("bdbNewName").value="";$("bdbCreateMsg").style.display="none";});
    $("bdbUserBtn").addEventListener("click",function(){$("bdbUserModal").style.display="flex";$("bdbNewUser").value="";$("bdbUserPass").value="";$("bdbUserMsg").style.display="none";});
    $("bdbAssignBtn").addEventListener("click",openAssignModal);
    post({action:"get_phpmyadmin_url"},function(r){if(r.success&&r.url) $("bdbPmaBtn").href=r.url;});
    $("bdbCreateSubmit").addEventListener("click",submitCreateDb);
    $("bdbUserSubmit").addEventListener("click",submitCreateDbUser);
    $("bdbAssignSubmit").addEventListener("click",submitAssignDb);
}

function loadDatabases(){
    var list=$("bt-db-list");if(!list) return;
    list.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading databases...</span></div>";
    post({action:"list_databases"},function(r){
        if(!r.success){list.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Failed to load")+"</span></div>";return;}
        var dbs=r.databases||[];var users=r.users||[];var mappings=r.mappings||[];
        var countEl=document.querySelector(".bt-db-count");
        if(countEl) countEl.textContent=dbs.length+" database"+(dbs.length!==1?"s":"")+", "+users.length+" user"+(users.length!==1?"s":"");
        if(r.prefix){var pe=$("bdbPrefix");if(pe)pe.textContent=r.prefix;var upe=$("bdbUserPrefix");if(upe)upe.textContent=r.prefix;}
        var html="";
        if(!dbs.length&&!users.length){
            html="<div class=\"bt-empty\"><svg width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\"><ellipse cx=\"12\" cy=\"5\" rx=\"9\" ry=\"3\"/><path d=\"M21 12c0 1.66-4 3-9 3s-9-1.34-9-3\"/><path d=\"M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\"/></svg><span>No databases found</span></div>";
        }else{
            dbs.forEach(function(db){
                var dbUsers=[];
                mappings.forEach(function(m){if(m.db===db&&m.user)dbUsers.push(m.user);});
                var userBadges=dbUsers.length?dbUsers.map(function(u){return "<span class=\"bt-row-badge bt-badge-purple\">"+esc(u)+"</span>";}).join(""):"<span class=\"bt-row-badge bt-badge-amber\">No users</span>";
                html+="<div class=\"bt-row\" data-db=\""+esc(db)+"\"><div class=\"bt-row-icon db\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><ellipse cx=\"12\" cy=\"5\" rx=\"9\" ry=\"3\"/><path d=\"M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5\"/></svg></div><div class=\"bt-row-info\" style=\"flex-wrap:wrap\"><span class=\"bt-row-name mono\">"+esc(db)+"</span>"+userBadges+"</div><div class=\"bt-row-actions\"><button type=\"button\" class=\"bt-row-btn del\" data-db=\""+esc(db)+"\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg><span>Delete</span></button></div></div>";
            });
            if(users.length){
                html+="<div style=\"padding:12px 14px 4px;font-size:12px;font-weight:700;color:var(--text-muted,#9ca3af);text-transform:uppercase;letter-spacing:.5px\">Database Users</div>";
                users.forEach(function(u){
                    html+="<div class=\"bt-row\" data-dbuser=\""+esc(u)+"\"><div class=\"bt-row-icon dbuser\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2\"/><circle cx=\"12\" cy=\"7\" r=\"4\"/></svg></div><div class=\"bt-row-info\"><span class=\"bt-row-name mono\">"+esc(u)+"</span><span class=\"bt-row-badge bt-badge-purple\">User</span></div><div class=\"bt-row-actions\"><button type=\"button\" class=\"bt-row-btn del\" data-dbuser=\""+esc(u)+"\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\"/></svg><span>Delete</span></button></div></div>";
                });
            }
        }
        list.innerHTML=html;
        list.querySelectorAll(".bt-row-btn.del[data-db]").forEach(function(b){b.addEventListener("click",function(){
            if(confirm("Delete database "+this.getAttribute("data-db")+"?")){var btn=this;btn.disabled=true;post({action:"delete_database",database:this.getAttribute("data-db")},function(r){btn.disabled=false;if(r.success)loadDatabases();else alert(r.message||"Failed");});}
        });});
        list.querySelectorAll(".bt-row-btn.del[data-dbuser]").forEach(function(b){b.addEventListener("click",function(){
            if(confirm("Delete user "+this.getAttribute("data-dbuser")+"?")){var btn=this;btn.disabled=true;post({action:"delete_db_user",dbuser:this.getAttribute("data-dbuser")},function(r){btn.disabled=false;if(r.success)loadDatabases();else alert(r.message||"Failed");});}
        });});
        updateAssignSelects(dbs,users);
    });
}

function submitCreateDb(){
    var name=$("bdbNewName").value.trim();var msg=$("bdbCreateMsg");msg.style.display="none";
    if(!name){showMsg(msg,"Please enter a database name",false);return;}
    $("bdbCreateSubmit").disabled=true;
    post({action:"create_database",dbname:name},function(r){
        $("bdbCreateSubmit").disabled=false;
        showMsg(msg,r.message||"Done",r.success);
        if(r.success){setTimeout(function(){$("bdbCreateModal").style.display="none";loadDatabases();},800);}
    });
}

function submitCreateDbUser(){
    var name=$("bdbNewUser").value.trim();var pass=$("bdbUserPass").value;var msg=$("bdbUserMsg");msg.style.display="none";
    if(!name||!pass){showMsg(msg,"Please fill in all fields",false);return;}
    $("bdbUserSubmit").disabled=true;
    post({action:"create_db_user",dbuser:name,dbpass:pass},function(r){
        $("bdbUserSubmit").disabled=false;
        showMsg(msg,r.message||"Done",r.success);
        if(r.success){setTimeout(function(){$("bdbUserModal").style.display="none";loadDatabases();},800);}
    });
}

function openAssignModal(){
    $("bdbAssignModal").style.display="flex";$("bdbAssignMsg").style.display="none";
}
function updateAssignSelects(dbs,users){
    var dbSel=$("bdbAssignDb");var uSel=$("bdbAssignUser");
    if(!dbSel||!uSel) return;
    dbSel.innerHTML="";uSel.innerHTML="";
    dbs.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;dbSel.appendChild(o);});
    users.forEach(function(u){var o=document.createElement("option");o.value=u;o.textContent=u;uSel.appendChild(o);});
}
function submitAssignDb(){
    var db=$("bdbAssignDb").value;var user=$("bdbAssignUser").value;var msg=$("bdbAssignMsg");msg.style.display="none";
    var priv=$("bdbAssignAll").checked?"ALL PRIVILEGES":"SELECT,INSERT,UPDATE,DELETE";
    if(!db||!user){showMsg(msg,"Select a database and user",false);return;}
    $("bdbAssignSubmit").disabled=true;
    post({action:"assign_db_user",database:db,dbuser:user,privileges:priv},function(r){
        $("bdbAssignSubmit").disabled=false;
        showMsg(msg,r.message||"Done",r.success);
        if(r.success){setTimeout(function(){$("bdbAssignModal").style.display="none";loadDatabases();},800);}
    });
}

/* â”€â”€â”€ WordPress Pane â”€â”€â”€ */
function buildWpPane(){
    var pane=$("bt-pane-wordpress");if(!pane) return;
    pane.innerHTML="<div class=\"bt-card\"><div class=\"bt-card-head\"><div class=\"bt-card-head-left\"><div class=\"bt-icon-circle\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 16 16\" fill=\"currentColor\"><path d=\"M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046zM1.137 8a6.86 6.86 0 0 0 3.868 6.176L1.73 5.206A6.8 6.8 0 0 0 1.137 8\"/><path d=\"M6.061 14.583 8.121 8.6l2.109 5.78q.02.05.049.094a6.85 6.85 0 0 1-4.218.109m7.96-9.876q.046.328.047.706c0 .696-.13 1.479-.522 2.458l-2.096 6.06a6.86 6.86 0 0 0 2.572-9.224z\"/><path fill-rule=\"evenodd\" d=\"M0 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.59 8-8 8-8-3.589-8-8m.367 0c0 4.209 3.424 7.633 7.633 7.633S15.632 12.209 15.632 8C15.632 3.79 12.208.367 8 .367 3.79.367.367 3.79.367 8\"/></svg></div><div><h5>WordPress Manager</h5><p class=\"bt-wp-count\">Loading...</p></div></div></div><div class=\"bt-list\" id=\"bt-wp-list\"><div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading WordPress installations...</span></div></div></div>";
}

function loadWpInstances(){
    var list=$("bt-wp-list");if(!list) return;
    list.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading WordPress installations...</span></div>";
    wpPost({action:"get_wp_instances"},function(r){
        if(!r.success){list.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Failed to load")+"</span></div>";return;}
        wpInstances=r.instances||[];
        var countEl=document.querySelector(".bt-wp-count");
        if(countEl) countEl.textContent=wpInstances.length+" site"+(wpInstances.length!==1?"s":"");
        if(!wpInstances.length){list.innerHTML="<div class=\"bt-empty\">"+wpSvg32+"<span>No WordPress installations found</span></div>";return;}
        var html="";
        wpInstances.forEach(function(inst){
            var statusCls=inst.alive?"active":"inactive";var statusTxt=inst.alive?"Active":"Inactive";
            var meta="<span>WP "+esc(inst.version)+"</span>";
            if(inst.pluginUpdates>0) meta+="<span style=\"color:#0a5ed3\">"+inst.pluginUpdates+" plugin update"+(inst.pluginUpdates>1?"s":"")+"</span>";
            if(inst.themeUpdates>0) meta+="<span style=\"color:#7c3aed\">"+inst.themeUpdates+" theme update"+(inst.themeUpdates>1?"s":"")+"</span>";
            if(inst.availableUpdate) meta+="<span style=\"color:#d97706\">Core update: "+esc(inst.availableUpdate)+"</span>";
            html+="<div class=\"bwp-site\" data-id=\""+inst.id+"\"><div class=\"bwp-site-icon\">"+wpSvg20+"</div><div class=\"bwp-site-info\"><p class=\"bwp-site-domain\">"+esc(inst.displayTitle||inst.domain)+"</p><div class=\"bwp-site-meta\"><span class=\"bwp-status-badge "+statusCls+"\"><span style=\"width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block\"></span> "+statusTxt+"</span>"+meta+"</div></div><div class=\"bwp-site-actions\"><button type=\"button\" class=\"bt-row-btn login\" data-wpid=\""+inst.id+"\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4\"/><polyline points=\"10 17 15 12 10 7\"/><line x1=\"15\" y1=\"12\" x2=\"3\" y2=\"12\"/></svg><span>Login</span></button><a href=\""+esc(inst.site_url)+"\" target=\"_blank\" class=\"bt-row-btn visit\"><svg width=\"13\" height=\"13\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/><polyline points=\"15 3 21 3 21 9\"/><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"/></svg><span>Visit</span></a><button type=\"button\" class=\"bt-row-btn\" data-wpdetail=\""+inst.id+"\" style=\"color:#0a5ed3\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><circle cx=\"12\" cy=\"12\" r=\"3\"/><path d=\"M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z\"/></svg><span>Manage</span></button></div></div>";
        });
        list.innerHTML=html;
        list.querySelectorAll(".bt-row-btn.login[data-wpid]").forEach(function(b){b.addEventListener("click",function(){bwpAutoLogin(parseInt(this.getAttribute("data-wpid")));});});
        list.querySelectorAll("[data-wpdetail]").forEach(function(b){b.addEventListener("click",function(){bwpOpenDetail(parseInt(this.getAttribute("data-wpdetail")));});});
    });
}

function bwpAutoLogin(id){
    wpPost({action:"wp_autologin",instance_id:id},function(r){
        if(r.success&&r.login_url) window.open(r.login_url,"_blank");
        else alert(r.message||"Could not generate login link");
    });
}
window.bwpDoLogin=bwpAutoLogin;

function bwpOpenDetail(id){
    currentWpInstance=null;
    for(var i=0;i<wpInstances.length;i++){if(wpInstances[i].id===id){currentWpInstance=wpInstances[i];break;}}
    if(!currentWpInstance) return;
    var ov=$("bwpDetailOverlay");ov.style.display="flex";
    $("bwpDetailTitle").textContent=currentWpInstance.displayTitle||currentWpInstance.domain;
    // Reset tabs to overview
    ov.querySelectorAll(".bwp-tab").forEach(function(t,i){t.classList.toggle("active",i===0);});
    ov.querySelectorAll(".bwp-tab-content").forEach(function(c,i){c.classList.toggle("active",i===0);});
    // Build overview
    var ovTab=$("bwpTabOverview");
    var siteUrl=currentWpInstance.site_url||"";
    var html="<div class=\"bwp-overview-hero\"><div class=\"bwp-preview-col\"><div class=\"bwp-preview-wrap\"><div class=\"bwp-preview-bar\"><div class=\"bwp-preview-dots\"><span></span><span></span><span></span></div><div class=\"bwp-preview-url\">"+esc(siteUrl)+"</div></div><div class=\"bwp-preview-frame-wrap\"><iframe src=\""+esc(siteUrl)+"\" style=\"width:200%;height:200%;transform:scale(.5);transform-origin:0 0;border:none;pointer-events:none\" loading=\"lazy\" sandbox=\"allow-same-origin\"></iframe></div></div><div class=\"bwp-quick-actions\"><button type=\"button\" class=\"bt-btn-add\" onclick=\"bwpDoLogin("+id+")\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4\"/><polyline points=\"10 17 15 12 10 7\"/><line x1=\"15\" y1=\"12\" x2=\"3\" y2=\"12\"/></svg> WP Admin</button><a href=\""+esc(siteUrl)+"\" target=\"_blank\" class=\"bt-btn-outline\"><svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/><polyline points=\"15 3 21 3 21 9\"/><line x1=\"10\" y1=\"14\" x2=\"21\" y2=\"3\"/></svg> Visit Site</a></div></div>";
    html+="<div class=\"bwp-overview-right\"><div class=\"bwp-site-header\"><div class=\"bwp-site-header-icon\">"+wpSvg20+"</div><div class=\"bwp-site-header-info\"><h4>"+esc(currentWpInstance.displayTitle||currentWpInstance.domain)+"</h4><p><span>WP "+esc(currentWpInstance.version)+"</span><span>"+esc(currentWpInstance.path)+"</span></p></div></div>";
    html+="<div class=\"bwp-overview-grid\">";
    html+="<div class=\"bwp-stat\"><div class=\"bwp-stat-label\">Status</div><div class=\"bwp-stat-value\">"+(currentWpInstance.alive?"<span style=\"color:#059669\">Active</span>":"<span style=\"color:#ef4444\">Inactive</span>")+"</div></div>";
    html+="<div class=\"bwp-stat\"><div class=\"bwp-stat-label\">SSL</div><div class=\"bwp-stat-value\">"+(currentWpInstance.ssl?"<span style=\"color:#059669\">Enabled</span>":"<span style=\"color:#d97706\">Disabled</span>")+"</div></div>";
    html+="<div class=\"bwp-stat\"><div class=\"bwp-stat-label\">Plugin Updates</div><div class=\"bwp-stat-value\">"+(currentWpInstance.pluginUpdates>0?"<span style=\"color:#0a5ed3\">"+currentWpInstance.pluginUpdates+" available</span>":"<span style=\"color:#059669\">Up to date</span>")+"</div></div>";
    html+="<div class=\"bwp-stat\"><div class=\"bwp-stat-label\">Theme Updates</div><div class=\"bwp-stat-value\">"+(currentWpInstance.themeUpdates>0?"<span style=\"color:#7c3aed\">"+currentWpInstance.themeUpdates+" available</span>":"<span style=\"color:#059669\">Up to date</span>")+"</div></div>";
    html+="</div>";
    if(currentWpInstance.availableUpdate) html+="<div class=\"bwp-msg info\">Core update available: WordPress "+esc(currentWpInstance.availableUpdate)+"</div>";
    html+="</div></div>";
    ovTab.innerHTML=html;
    // Mark sub-tabs as not loaded so they load on first view
    ["bwpTabPlugins","bwpTabThemes","bwpTabSecurity"].forEach(function(tid){
        var t=$(tid);if(t){t.removeAttribute("data-loaded");t.innerHTML="<div class=\"bt-loading\"><div class=\"bt-spinner\"></div><span>Loading...</span></div>";}
    });
    // Pre-load plugins since it is the next likely tab
    bwpLoadPlugins();
}

/* â”€â”€â”€ WP Detail Tab Handlers (lazy load, no reset on switch) â”€â”€â”€ */
(function(){
    var overlay=$("bwpDetailOverlay");if(!overlay) return;
    overlay.querySelectorAll(".bwp-tab").forEach(function(tab){
        tab.addEventListener("click",function(){
            overlay.querySelectorAll(".bwp-tab").forEach(function(t){t.classList.remove("active");});
            overlay.querySelectorAll(".bwp-tab-content").forEach(function(c){c.classList.remove("active");});
            tab.classList.add("active");
            var tabName=tab.getAttribute("data-tab");
            var target=$("bwpTab"+tabName.charAt(0).toUpperCase()+tabName.slice(1));
            if(target){
                target.classList.add("active");
                // Lazy load: only load if not already loaded
                if(!target.getAttribute("data-loaded")){
                    if(tabName==="plugins") bwpLoadPlugins();
                    else if(tabName==="themes") bwpLoadThemes();
                    else if(tabName==="security") bwpLoadSecurity();
                }
            }
        });
    });
    $("bwpDetailClose").addEventListener("click",function(){overlay.style.display="none";});
    overlay.addEventListener("click",function(e){if(e.target===overlay) overlay.style.display="none";});
})();

function bwpLoadPlugins(){
    if(!currentWpInstance) return;
    wpPost({action:"wp_list_plugins",instance_id:currentWpInstance.id},function(r){
        var el=$("bwpTabPlugins");
        if(!r.success){el.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Failed")+"</span></div>";return;}
        var plugins=r.plugins||[];
        if(!plugins.length){el.innerHTML="<div class=\"bt-empty\"><span>No plugins found</span></div>";return;}
        var html="";
        plugins.forEach(function(p){
            var active=p.active||p.status==="active";
            var hasUpdate=!!p.availableVersion;
            html+="<div class=\"bwp-item-row\"><div class=\"bwp-item-icon plugin\">"+esc((p.title||p.name||p.slug||"P").charAt(0).toUpperCase())+"</div><div class=\"bwp-item-info\"><p class=\"bwp-item-name\">"+esc(p.title||p.name||p.slug)+"</p><p class=\"bwp-item-detail\">v"+esc(p.version||"?")+(hasUpdate?" â†’ "+esc(p.availableVersion):"")+"</p></div><div class=\"bwp-item-actions\">";
            html+="<button type=\"button\" class=\"bwp-item-btn "+(active?"active-state":"inactive-state")+"\" onclick=\"bwpTogglePlugin(\\x27"+esc(p.slug)+"\\x27,"+(!active)+")\">"+(active?"Deactivate":"Activate")+"</button>";
            if(hasUpdate) html+="<button type=\"button\" class=\"bwp-item-btn update\" onclick=\"bwpUpdatePlugin(\\x27"+esc(p.slug)+"\\x27,this)\">Update</button>";
            html+="</div></div>";
        });
        el.innerHTML=html;
        el.setAttribute("data-loaded","1");
    });
}
window.bwpTogglePlugin=function(slug,activate){
    if(!currentWpInstance) return;
    wpPost({action:"wp_toggle_plugin",instance_id:currentWpInstance.id,slug:slug,activate:activate?"1":"0"},function(r){
        if(r.success) bwpLoadPlugins(); else alert(r.message||"Failed");
    });
};
window.bwpUpdatePlugin=function(slug,btn){
    if(!currentWpInstance) return;btn.disabled=true;btn.textContent="Updating...";
    wpPost({action:"wp_update",instance_id:currentWpInstance.id,type:"plugins",slug:slug},function(r){
        btn.disabled=false;
        if(r.success){btn.textContent="Updated";btn.style.color="#059669";setTimeout(function(){bwpLoadPlugins();},1000);}
        else{btn.textContent="Update";alert(r.message||"Failed");}
    });
};

function bwpLoadThemes(){
    if(!currentWpInstance) return;
    wpPost({action:"wp_list_themes",instance_id:currentWpInstance.id},function(r){
        var el=$("bwpTabThemes");
        if(!r.success){el.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Failed")+"</span></div>";return;}
        var themes=r.themes||[];
        if(!themes.length){el.innerHTML="<div class=\"bt-empty\"><span>No themes found</span></div>";return;}
        var html="<div class=\"bwp-theme-grid\">";
        themes.forEach(function(t){
            var active=t.active||t.status==="active";
            var hasUpdate=!!t.availableVersion;
            var screenshot=t.screenshot||t.screenshotUrl||"";
            html+="<div class=\"bwp-theme-card"+(active?" bwp-theme-active":"")+"\"><div class=\"bwp-theme-screenshot\">"+(screenshot?"<img src=\""+esc(screenshot)+"\" alt=\""+esc(t.title||t.name||t.slug)+"\" loading=\"lazy\">":"")+(active?"<div class=\"bwp-theme-active-badge\">Active</div>":"")+"</div><div class=\"bwp-theme-info\"><p class=\"bwp-theme-name\">"+esc(t.title||t.name||t.slug)+"</p><p class=\"bwp-theme-ver\">v"+esc(t.version||"?")+(hasUpdate?" â†’ "+esc(t.availableVersion):"")+"</p><div class=\"bwp-theme-actions\">";
            if(!active) html+="<button type=\"button\" class=\"bwp-item-btn\" onclick=\"bwpActivateTheme(\\x27"+esc(t.slug)+"\\x27,this)\">Activate</button>";
            if(hasUpdate) html+="<button type=\"button\" class=\"bwp-item-btn update\" onclick=\"bwpUpdateTheme(\\x27"+esc(t.slug)+"\\x27,this)\">Update</button>";
            html+="</div></div></div>";
        });
        html+="</div>";
        el.innerHTML=html;
        el.setAttribute("data-loaded","1");
    });
}
window.bwpActivateTheme=function(slug,btn){
    if(!currentWpInstance) return;btn.disabled=true;btn.textContent="Activating...";
    wpPost({action:"wp_toggle_theme",instance_id:currentWpInstance.id,slug:slug},function(r){
        btn.disabled=false;
        if(r.success) bwpLoadThemes(); else{btn.textContent="Activate";alert(r.message||"Failed");}
    });
};
window.bwpUpdateTheme=function(slug,btn){
    if(!currentWpInstance) return;btn.disabled=true;btn.textContent="Updating...";
    wpPost({action:"wp_update",instance_id:currentWpInstance.id,type:"themes",slug:slug},function(r){
        btn.disabled=false;
        if(r.success){btn.textContent="Updated";btn.style.color="#059669";setTimeout(function(){bwpLoadThemes();},1000);}
        else{btn.textContent="Update";alert(r.message||"Failed");}
    });
};

function bwpLoadSecurity(){
    if(!currentWpInstance) return;
    wpPost({action:"wp_security_scan",instance_id:currentWpInstance.id},function(r){
        var el=$("bwpTabSecurity");
        if(!r.success){el.innerHTML="<div class=\"bt-empty\"><span>"+(r.message||"Security scan failed")+"</span></div>";el.setAttribute("data-loaded","1");return;}
        var measures=r.security||[];
        if(!measures.length){el.innerHTML="<div class=\"bt-empty\"><span>No security data available</span></div>";el.setAttribute("data-loaded","1");return;}
        var applied=0;measures.forEach(function(m){if(m.status==="applied"||m.status==="true"||m.status===true) applied++;});
        var pct=Math.round(applied/measures.length*100);
        var html="<div class=\"bwp-sec-summary\"><div class=\"bwp-sec-summary-bar\"><div class=\"bwp-sec-summary-fill\" style=\"width:"+pct+"%\"></div></div><div class=\"bwp-sec-summary-text\"><span><strong>"+applied+"</strong> of <strong>"+measures.length+"</strong> measures applied</span><span><strong>"+pct+"%</strong> secure</span></div></div>";
        measures.forEach(function(m){
            var ok=m.status==="applied"||m.status==="true"||m.status===true;
            var mid=esc(m.id);
            html+="<div class=\"bwp-security-item\" data-measure=\""+mid+"\"><div class=\"bwp-sec-icon "+(ok?"ok":"warning")+"\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\">"+(ok?"<polyline points=\"20 6 9 17 4 12\"/>":"<circle cx=\"12\" cy=\"12\" r=\"10\"/><line x1=\"12\" y1=\"8\" x2=\"12\" y2=\"12\"/><line x1=\"12\" y1=\"16\" x2=\"12.01\" y2=\"16\"/>")+"</svg></div><div class=\"bwp-sec-info\"><p class=\"bwp-sec-label\">"+esc(m.title||m.id)+"</p><p class=\"bwp-sec-detail\">"+mid+"</p></div><div class=\"bwp-sec-actions\" style=\"display:flex;gap:6px;flex-shrink:0\">"+(ok?"<button type=\"button\" class=\"bwp-item-btn inactive-state\" onclick=\"bwpRevertSecurity(\\x27"+mid+"\\x27,this)\">Revert</button>":"<button type=\"button\" class=\"bwp-item-btn active-state\" onclick=\"bwpApplySecurity(\\x27"+mid+"\\x27,this)\">Apply</button>")+"</div></div>";
        });
        el.innerHTML=html;
        el.setAttribute("data-loaded","1");
    });
}
window.bwpApplySecurity=function(measureId,btn){
    if(!currentWpInstance) return;btn.disabled=true;btn.textContent="Applying...";
    wpPost({action:"wp_security_apply",instance_id:currentWpInstance.id,measure_id:measureId},function(r){
        btn.disabled=false;
        if(r.success){$("bwpTabSecurity").removeAttribute("data-loaded");bwpLoadSecurity();}
        else{btn.textContent="Apply";alert(r.message||"Failed to apply security measure");}
    });
};
window.bwpRevertSecurity=function(measureId,btn){
    if(!currentWpInstance) return;btn.disabled=true;btn.textContent="Reverting...";
    wpPost({action:"wp_security_revert",instance_id:currentWpInstance.id,measure_id:measureId},function(r){
        btn.disabled=false;
        if(r.success){$("bwpTabSecurity").removeAttribute("data-loaded");bwpLoadSecurity();}
        else{btn.textContent="Revert";alert(r.message||"Failed to revert security measure");}
    });
};

/* â”€â”€â”€ Email Actions â”€â”€â”€ */
function bindEmailActions(pane){
    pane.querySelectorAll(".bt-row-btn.login[data-email]").forEach(function(b){b.addEventListener("click",function(){
        var email=this.getAttribute("data-email");var btn=this;btn.disabled=true;
        post({action:"webmail_login",email:email},function(r){btn.disabled=false;if(r.success&&r.url) window.open(r.url,"_blank");else alert(r.message||"Failed");});
    });});
    pane.querySelectorAll(".bt-row-btn.pass[data-email]").forEach(function(b){b.addEventListener("click",function(){
        $("bemPassEmail").value=this.getAttribute("data-email");$("bemPassNew").value="";$("bemPassMsg").style.display="none";$("bemPassModal").style.display="flex";
    });});
    pane.querySelectorAll(".bt-row-btn.del[data-email]").forEach(function(b){b.addEventListener("click",function(){
        $("bemDelEmail").textContent=this.getAttribute("data-email");$("bemDelMsg").style.display="none";$("bemDelModal").style.display="flex";
    });});
}

function openCreateEmailModal(){
    $("bemNewUser").value="";$("bemNewPass").value="";$("bemNewQuota").value="250";$("bemCreateMsg").style.display="none";
    var sel=$("bemNewDomain");sel.innerHTML="<option>Loading...</option>";
    $("bemCreateModal").style.display="flex";
    post({action:"get_domains"},function(r){
        sel.innerHTML="";
        var doms=r.domains||[];
        if(!doms.length&&C.domains&&C.domains.main) doms=[C.domains.main];
        doms.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;sel.appendChild(o);});
    });
}

$("bemCreateSubmit").addEventListener("click",function(){
    var user=$("bemNewUser").value.trim();var pass=$("bemNewPass").value;var domain=$("bemNewDomain").value;var quota=$("bemNewQuota").value;var msg=$("bemCreateMsg");msg.style.display="none";
    if(!user||!pass||!domain){showMsg(msg,"Please fill in all fields",false);return;}
    this.disabled=true;var btn=this;
    post({action:"create_email",email_user:user,email_pass:pass,domain:domain,quota:quota},function(r){
        btn.disabled=false;showMsg(msg,r.message||"Done",r.success);
        if(r.success){C.emails=C.emails||[];C.emails.push(r.email);setTimeout(function(){$("bemCreateModal").style.display="none";buildEmailPane();},800);}
    });
});

$("bemPassSubmit").addEventListener("click",function(){
    var email=$("bemPassEmail").value;var pass=$("bemPassNew").value;var msg=$("bemPassMsg");msg.style.display="none";
    if(!pass){showMsg(msg,"Please enter a new password",false);return;}
    this.disabled=true;var btn=this;
    post({action:"change_password",email:email,new_pass:pass},function(r){btn.disabled=false;showMsg(msg,r.message||"Done",r.success);if(r.success) setTimeout(function(){$("bemPassModal").style.display="none";},800);});
});

$("bemDelSubmit").addEventListener("click",function(){
    var email=$("bemDelEmail").textContent;var msg=$("bemDelMsg");msg.style.display="none";
    this.disabled=true;var btn=this;
    post({action:"delete_email",email:email},function(r){
        btn.disabled=false;showMsg(msg,r.message||"Done",r.success);
        if(r.success){C.emails=(C.emails||[]).filter(function(e){return e!==email;});setTimeout(function(){$("bemDelModal").style.display="none";buildEmailPane();},800);}
    });
});

/* â”€â”€â”€ Domain Actions â”€â”€â”€ */
function bindDomainActions(pane){
    pane.querySelectorAll(".bt-row-btn.del[data-domain]").forEach(function(b){b.addEventListener("click",function(){
        openDelDomainModal(this.getAttribute("data-domain"),this.getAttribute("data-type"));
    });});
}

function openAddonModal(){
    $("bdmAddonDomain").value="";$("bdmAddonDocroot").value="";$("bdmAddonMsg").style.display="none";$("bdmAddonModal").style.display="flex";
}
function openSubModal(){
    $("bdmSubName").value="";$("bdmSubDocroot").value="";$("bdmSubMsg").style.display="none";
    var sel=$("bdmSubParent");sel.innerHTML="<option>Loading...</option>";
    $("bdmSubModal").style.display="flex";
    post({action:"get_parent_domains"},function(r){
        sel.innerHTML="";
        var doms=r.domains||[];
        doms.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;sel.appendChild(o);});
    });
}

$("bdmAddonSubmit").addEventListener("click",function(){
    var domain=$("bdmAddonDomain").value.trim();var docroot=$("bdmAddonDocroot").value.trim();var msg=$("bdmAddonMsg");msg.style.display="none";
    if(!domain){showMsg(msg,"Please enter a domain name",false);return;}
    this.disabled=true;var btn=this;
    post({action:"add_addon_domain",domain:domain,docroot:docroot},function(r){
        btn.disabled=false;showMsg(msg,r.message||"Done",r.success);
        if(r.success){if(C.domains) C.domains.addon=(C.domains.addon||[]).concat([domain]);setTimeout(function(){$("bdmAddonModal").style.display="none";buildDomainsPane();},800);}
    });
});

$("bdmSubSubmit").addEventListener("click",function(){
    var sub=$("bdmSubName").value.trim();var parent=$("bdmSubParent").value;var docroot=$("bdmSubDocroot").value.trim();var msg=$("bdmSubMsg");msg.style.display="none";
    if(!sub||!parent){showMsg(msg,"Please fill in all fields",false);return;}
    this.disabled=true;var btn=this;
    post({action:"add_subdomain",subdomain:sub,domain:parent,docroot:docroot},function(r){
        btn.disabled=false;showMsg(msg,r.message||"Done",r.success);
        if(r.success){if(C.domains) C.domains.sub=(C.domains.sub||[]).concat([r.domain||sub+"."+parent]);setTimeout(function(){$("bdmSubModal").style.display="none";buildDomainsPane();},800);}
    });
});

function openDelDomainModal(domain,type){
    $("bdmDelDomain").textContent=domain;$("bdmDelMsg").style.display="none";$("bdmDelModal").style.display="flex";
    $("bdmDelSubmit").onclick=function(){
        var msg=$("bdmDelMsg");msg.style.display="none";this.disabled=true;var btn=this;
        post({action:"delete_domain",domain:domain,type:type},function(r){
            btn.disabled=false;showMsg(msg,r.message||"Done",r.success);
            if(r.success){
                if(C.domains){
                    if(type==="addon") C.domains.addon=(C.domains.addon||[]).filter(function(d){return d!==domain;});
                    if(type==="sub") C.domains.sub=(C.domains.sub||[]).filter(function(d){return d!==domain;});
                    if(type==="parked") C.domains.parked=(C.domains.parked||[]).filter(function(d){return d!==domain;});
                }
                setTimeout(function(){$("bdmDelModal").style.display="none";buildDomainsPane();},800);
            }
        });
    };
}

/* â”€â”€â”€ Modal Bindings â”€â”€â”€ */
function bindModals(){
    document.querySelectorAll(".bt-overlay").forEach(function(ov){
        ov.addEventListener("click",function(e){if(e.target===ov) ov.style.display="none";});
    });
    document.querySelectorAll("[data-close]").forEach(function(b){
        b.addEventListener("click",function(){var ov=this.closest(".bt-overlay");if(ov) ov.style.display="none";});
    });
    document.querySelectorAll("[data-toggle-pass]").forEach(function(b){
        b.addEventListener("click",function(){
            var inp=$(this.getAttribute("data-toggle-pass"));
            if(inp) inp.type=inp.type==="password"?"text":"password";
        });
    });
    document.querySelectorAll(".bt-copy").forEach(function(b){
        b.addEventListener("click",function(){doCopy(this.getAttribute("data-copy"),this);});
    });
}

/* â”€â”€â”€ Boot â”€â”€â”€ */
if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",init);
else init();

})();
