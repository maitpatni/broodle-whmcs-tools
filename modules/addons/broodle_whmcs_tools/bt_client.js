(function(){
"use strict";
window.__btClientLoaded=true;
console.log("[BT] bt_client.js loaded successfully, version 3.10.51");
/* Detect base path: always use full module path since page loads within WHMCS client area */
var btBasePath="modules/addons/broodle_whmcs_tools/";
var ajaxUrl=btBasePath+"ajax.php";
var wpAjaxUrl=btBasePath+"ajax_wordpress.php";
var C={};
var wpInstances=[];var currentWpInstance=null;

function esc(s){var d=document.createElement("div");d.textContent=s;return d.innerHTML;}
function $(id){return document.getElementById(id);}
function showMsg(el,msg,ok){el.textContent=msg;el.className="bt-msg "+(ok?"success":"error");el.style.display="block";}
var spinSvg='<svg class="bt-btn-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur=".7s" repeatCount="indefinite"/></path></svg>';
function btnLoad(btn,text){if(!btn)return;btn._origHtml=btn.innerHTML;btn.disabled=true;btn.innerHTML=spinSvg+(text?' '+text:'');}
function btnDone(btn){if(!btn)return;btn.disabled=false;if(btn._origHtml!==undefined)btn.innerHTML=btn._origHtml;}
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

var wpSvg16="<svg width=\"16\" height=\"16\" viewBox=\"0 0 16 16\" fill=\"currentColor\"><path d=\"M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046zM1.137 8a6.86 6.86 0 0 0 3.868 6.176L1.73 5.206A6.8 6.8 0 0 0 1.137 8\"/><path d=\"M6.061 14.583 8.121 8.6l2.109 5.78q.02.05.049.094a6.85 6.85 0 0 1-4.218.109m7.96-9.876q.046.328.047.706c0 .696-.13 1.479-.522 2.458l-2.096 6.06a6.86 6.86 0 0 0 2.572-9.224z\"/><path fill-rule=\"evenodd\" d=\"M0 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.59 8-8 8-8-3.589-8-8m.367 0c0 4.209 3.424 7.633 7.633 7.633S15.632 12.209 15.632 8C15.632 3.79 12.208.367 8 .367 3.79.367.367 3.79.367 8\"/></svg>";
var wpSvg20=wpSvg16.replace(/width=\"16\"/g,"width=\"20\"").replace(/height=\"16\"/g,"height=\"20\"");
var wpSvg32=wpSvg16.replace(/width=\"16\"/g,"width=\"32\"").replace(/height=\"16\"/g,"height=\"32\"");

/* ─── Addon/Upgrade Icon Helpers ─── */
function btAddonIcon(name){
    var n=name.toLowerCase();
    if(n.indexOf("wordpress")!==-1||n.indexOf("wp ")!==-1) return wpSvg16;
    if(n.indexOf("site builder")!==-1||n.indexOf("sitebuilder")!==-1) return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>';
    if(n.indexOf("ssl")!==-1||n.indexOf("certificate")!==-1) return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>';
}
function btUpgradeIcon(name){
    var n=name.toLowerCase();
    if(n.indexOf("ram")!==-1||n.indexOf("memory")!==-1) return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 6V4M10 6V4M14 6V4M18 6V4"/></svg>';
    if(n.indexOf("backup")!==-1) return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>';
}

/* ─── CSS Injection ─── */
function injectStyles(){
    if(document.getElementById("bt-injected-styles")) return;
    var s=document.createElement("style");s.id="bt-injected-styles";
    s.textContent=[
'.bt-btn-spin{display:inline-block;vertical-align:middle;animation:btSpin .7s linear infinite}@keyframes btSpin{to{transform:rotate(360deg)}}',
'.bt-row-btn:disabled,.bt-btn-add:disabled,.bt-btn-primary:disabled,.bt-btn-danger:disabled,.bt-btn-outline:disabled{opacity:.6;cursor:not-allowed;pointer-events:none}',
/* Page takeover layout */
'.bt-page-wrap,#bt-page-wrap{display:flex;gap:0;min-height:400px}',
'.bt-sidebar{width:240px;flex-shrink:0;padding:0 12px 0 0}',
'.bt-sidebar-panel{background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;margin-bottom:16px;overflow:hidden}',
'.bt-sidebar-title{padding:14px 16px 10px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted,#9ca3af)}',
'.bt-sidebar-item{display:flex;align-items:center;gap:12px;padding:10px 16px;cursor:pointer;transition:background .12s,color .12s;color:var(--heading-color,#374151);text-decoration:none;border-left:3px solid transparent;font-size:14px;font-weight:500}',
'.bt-sidebar-item:hover{background:var(--input-bg,#f9fafb);text-decoration:none;color:var(--heading-color,#374151)}',
'.bt-sidebar-item.active{background:rgba(10,94,211,.04);color:#0a5ed3;border-left-color:#0a5ed3;font-weight:600}',
'.bt-sidebar-item .bt-si-icon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}',
'.bt-sidebar-item .bt-si-label{flex:1;min-width:0}',
'.bt-sidebar-item .bt-si-label span{display:block;font-size:11px;font-weight:400;color:var(--text-muted,#9ca3af);margin-top:1px}',
'.bt-main-area{flex:1;min-width:0;overflow:hidden}',
'.bt-preserved-content{margin-bottom:20px}',
'@media(max-width:991px){.bt-page-wrap,#bt-page-wrap{flex-direction:column}.bt-sidebar{width:100%;padding:0 0 16px 0}}',
/* Tabs */
'.bt-wrap{margin-bottom:24px;font-family:inherit}.bt-wrap *{font-family:inherit}',
'.bt-tabs-nav{display:flex;gap:0;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none;border-bottom:2px solid var(--border-color,#e5e7eb);padding:0;margin:0}.bt-tabs-nav::-webkit-scrollbar{display:none}',
'.bt-tab-btn{display:inline-flex;align-items:center;gap:7px;padding:12px 18px;font-size:13px;font-weight:600;color:var(--text-muted,#6b7280);cursor:pointer;border:none;background:none;white-space:nowrap;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s;flex-shrink:0}',
'.bt-tab-btn:hover{color:var(--heading-color,#111827)}.bt-tab-btn.active{color:#0a5ed3;border-bottom-color:#0a5ed3}.bt-tab-btn svg{width:16px;height:16px;flex-shrink:0}',
'.bt-tab-pane{display:none;padding:20px 0 0}.bt-tab-pane.active{display:block}',
/* Overview grid */
'.bt-ov-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}',
'.bt-ov-card{background:var(--input-bg,#f8fafc);border:1px solid var(--border-color,#e5e7eb);border-radius:10px;padding:16px 18px;transition:border-color .15s,box-shadow .15s}',
'.bt-ov-card:hover{border-color:rgba(10,94,211,.25);box-shadow:0 2px 8px rgba(10,94,211,.06)}',
'.bt-ov-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted,#9ca3af);margin:0 0 6px}',
'.bt-ov-value{font-size:14px;font-weight:600;color:var(--heading-color,#111827);margin:0;word-break:break-word}',
'.bt-ov-value a{color:#0a5ed3;text-decoration:none}.bt-ov-value .label,.bt-ov-value .badge{font-size:12px;padding:3px 10px;border-radius:6px;font-weight:600}',
'.bt-ov-due-ok{color:#059669}.bt-ov-due-warn{color:#d97706}.bt-ov-due-danger{color:#ef4444}.bt-ov-due-past{color:#ef4444;font-weight:700}',
'.bt-ov-days{display:block;font-size:11px;font-weight:500;margin-top:2px}',
'@media(max-width:768px){.bt-ov-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:480px){.bt-ov-grid{grid-template-columns:1fr}}',
/* Cards and rows */
'.bt-card{background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;overflow:hidden}',
'.bt-card-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-color,#f3f4f6)}',
'.bt-card-head-left{display:flex;align-items:center;gap:12px}',
'.bt-icon-circle{width:36px;height:36px;background:#0a5ed3;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}',
'.bt-card-head h5{margin:0;font-size:15px;font-weight:600;color:var(--heading-color,#111827)}',
'.bt-card-head p{margin:2px 0 0;font-size:12px;color:var(--text-muted,#6b7280)}',
'.bt-card-head-right{display:flex;gap:8px}',
'.bt-list{padding:6px 8px}',
'.bt-row{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:9px;transition:background .15s}',
'.bt-row:hover{background:var(--input-bg,#f9fafb)}',
'.bt-row+.bt-row{border-top:1px solid var(--border-color,#f3f4f6)}',
'.bt-row-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}',
'.bt-row-icon.ns{background:rgba(10,94,211,.08);color:#0a5ed3}.bt-row-icon.ip{background:rgba(5,150,105,.08);color:#059669}',
'.bt-row-icon.email{background:rgba(10,94,211,.08);color:#0a5ed3;border-radius:50%}',
'.bt-row-icon.main{background:rgba(10,94,211,.08);color:#0a5ed3}.bt-row-icon.addon{background:rgba(5,150,105,.08);color:#059669}',
'.bt-row-icon.sub{background:rgba(124,58,237,.08);color:#7c3aed}.bt-row-icon.parked{background:rgba(217,119,6,.08);color:#d97706}',
'.bt-row-icon.db{background:rgba(10,94,211,.08);color:#0a5ed3}.bt-row-icon.dbuser{background:rgba(124,58,237,.08);color:#7c3aed}',
    ].join('\n');
    document.head.appendChild(s);
}

/* ─── CSS Part 2 ─── */
function injectStyles2(){
    if(document.getElementById("bt-injected-styles2")) return;
    var s=document.createElement("style");s.id="bt-injected-styles2";
    s.textContent=[
'.bt-row-info{flex:1;min-width:0;display:flex;align-items:center;gap:8px;overflow:hidden}',
'.bt-row-name{font-size:14px;font-weight:500;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
'.bt-row-name.mono{font-family:"SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace}',
'.bt-row-badge{padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;flex-shrink:0}',
'.bt-badge-primary{background:rgba(10,94,211,.08);color:#0a5ed3}.bt-badge-green{background:rgba(5,150,105,.08);color:#059669}',
'.bt-badge-purple{background:rgba(124,58,237,.08);color:#7c3aed}.bt-badge-amber{background:rgba(217,119,6,.08);color:#d97706}',
'.bt-row-actions{display:flex;gap:6px;flex-shrink:0}',
'.bt-row-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);transition:all .15s;white-space:nowrap;text-decoration:none;color:var(--heading-color,#374151)}',
'.bt-row-btn span{display:none}.bt-row-btn:hover span{display:inline}',
'.bt-row-btn:hover{border-color:#0a5ed3;color:#0a5ed3}',
'.bt-row-btn.login{color:#0a5ed3}.bt-row-btn.login:hover{background:rgba(10,94,211,.06);border-color:#0a5ed3}',
'.bt-row-btn.visit{color:#0a5ed3}.bt-row-btn.visit:hover{background:rgba(10,94,211,.06);border-color:#0a5ed3;text-decoration:none;color:#0a5ed3}',
'.bt-row-btn.pass{color:#d97706}.bt-row-btn.pass:hover{background:rgba(217,119,6,.06);border-color:#d97706}',
'.bt-row-btn.del{color:#ef4444}.bt-row-btn.del:hover{background:rgba(239,68,68,.06);border-color:#ef4444}',
'.bt-copy{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid var(--border-color,#e5e7eb);border-radius:7px;background:var(--card-bg,#fff);color:var(--text-muted,#9ca3af);cursor:pointer;transition:all .15s;flex-shrink:0}',
'.bt-copy:hover{color:#0a5ed3;border-color:#0a5ed3}.bt-copy.copied{color:#fff;background:#059669;border-color:#059669}',
'.bt-empty{padding:30px 22px;text-align:center;color:var(--text-muted,#9ca3af);font-size:14px;display:flex;flex-direction:column;align-items:center;gap:10px}.bt-empty svg{opacity:.4}',
'.bt-btn-add{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:#0a5ed3;color:#fff;transition:background .15s}.bt-btn-add:hover{background:#0950b3}',
'.bt-btn-outline{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#d1d5db);background:var(--card-bg,#fff);color:var(--heading-color,#374151);transition:all .15s}',
'.bt-btn-outline:hover{border-color:#0a5ed3;color:#0a5ed3;background:rgba(10,94,211,.04)}',
/* Accordion */
'.bt-accordion{margin-top:20px;border:1px solid var(--border-color,#e5e7eb);border-radius:12px;overflow:visible;background:var(--card-bg,#fff)}',
'.bt-accordion-head{display:flex;align-items:center;gap:12px;padding:14px 18px;cursor:pointer;user-select:none;transition:background .12s;border-radius:12px}',
'.bt-accordion-head:hover{background:var(--input-bg,#f9fafb)}',
'.bt-accordion-icon{width:36px;height:36px;border-radius:10px;background:#0a5ed3;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}',
'.bt-accordion-info{flex:1;min-width:0}.bt-accordion-info h5{margin:0;font-size:14px;font-weight:600;color:var(--heading-color,#111827)}.bt-accordion-info p{margin:2px 0 0;font-size:12px;color:var(--text-muted,#6b7280)}',
'.bt-accordion-arrow{width:20px;height:20px;color:var(--text-muted,#9ca3af);transition:transform .25s ease;flex-shrink:0}',
'.bt-accordion.open .bt-accordion-arrow{transform:rotate(180deg)}',
'.bt-accordion-body{max-height:0;overflow:hidden;transition:max-height .3s ease,overflow 0s .3s}',
'.bt-accordion.open .bt-accordion-body{max-height:800px;overflow:visible;transition:max-height .3s ease,overflow 0s 0s}',
    ].join('\n');
    document.head.appendChild(s);
}

/* ─── CSS Part 3: Addons carousel, SSL, Logs, Modals, WP detail ─── */
function injectStyles3(){
    if(document.getElementById("bt-injected-styles3")) return;
    var s=document.createElement("style");s.id="bt-injected-styles3";
    s.textContent=[
/* Addons carousel */
'.bt-addons-section{margin-top:20px}',
'.bt-addon-wrap{position:relative;padding:0 36px 6px}',
'.bt-addon-scroll{display:flex;gap:0;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none;padding:0;cursor:grab;user-select:none}.bt-addon-scroll::-webkit-scrollbar{display:none}.bt-addon-scroll.dragging{cursor:grabbing;scroll-snap-type:none;scroll-behavior:auto}',
'.bt-addon-page{min-width:100%;flex-shrink:0;scroll-snap-align:start;display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;gap:2px 0;padding:6px 4px}',
'.bt-addon-item{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:10px;transition:background .12s;min-height:54px}.bt-addon-item:hover{background:var(--input-bg,#f5f7fa)}',
'.bt-addon-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}',
'.bt-addon-icon.addon{background:rgba(124,58,237,.08);color:#7c3aed}.bt-addon-icon.upgrade{background:rgba(5,150,105,.08);color:#059669}',
'.bt-addon-text{flex:1;min-width:0;overflow:hidden}',
'.bt-addon-name{font-size:13px;font-weight:500;color:var(--heading-color,#111827);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
'.bt-addon-price{font-size:11px;color:#0a5ed3;font-weight:600;margin-top:1px;display:none}.bt-addon-price.visible{display:block}',
'.bt-addon-btn{display:inline-flex;align-items:center;gap:4px;padding:5px 12px;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);color:var(--heading-color,#374151);transition:all .12s;white-space:nowrap;text-decoration:none;flex-shrink:0}.bt-addon-btn:hover{border-color:#0a5ed3;color:#0a5ed3;background:rgba(10,94,211,.04);text-decoration:none}',
'.bt-addon-dots{display:flex;justify-content:center;gap:6px;padding:10px 0 4px}',
'.bt-addon-dot{width:6px;height:6px;border-radius:50%;background:var(--border-color,#d1d5db);border:none;padding:0;cursor:pointer;transition:all .2s}.bt-addon-dot.active{background:#0a5ed3;width:18px;border-radius:3px}',
'.bt-addon-nav{position:absolute;top:50%;transform:translateY(-60%);width:30px;height:30px;border-radius:50%;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);color:var(--text-muted,#6b7280);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;z-index:2;box-shadow:0 2px 6px rgba(0,0,0,.1)}.bt-addon-nav:hover{border-color:#0a5ed3;color:#0a5ed3;box-shadow:0 2px 8px rgba(10,94,211,.15)}.bt-addon-nav.prev{left:0}.bt-addon-nav.next{right:0}.bt-addon-nav.hidden{opacity:0;pointer-events:none}',
'.bt-addon-tip-wrap{position:relative;flex-shrink:0;margin-right:2px}',
'.bt-addon-tip-btn{width:18px;height:18px;border-radius:50%;border:none;background:transparent;color:var(--text-muted,#c0c5cc);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:color .12s;padding:0}.bt-addon-tip-btn:hover{color:#0a5ed3}.bt-addon-tip-btn.loading{opacity:.4}',
'.bt-addon-tooltip{position:fixed;width:340px;max-width:90vw;max-height:200px;overflow-y:auto;overflow-x:hidden;padding:12px 15px;background:#1f2937;color:#f3f4f6;font-size:12px;line-height:1.55;border-radius:9px;box-shadow:0 8px 28px rgba(0,0,0,.22);z-index:99999;word-wrap:break-word;opacity:0;visibility:hidden;transition:opacity .12s,visibility .12s;pointer-events:none;scrollbar-width:none}.bt-addon-tooltip::-webkit-scrollbar{display:none}.bt-addon-tooltip.visible{opacity:1;visibility:visible;pointer-events:auto}.bt-addon-tooltip::after{display:none}',
'@media(max-width:600px){.bt-addon-page{grid-template-columns:1fr;grid-template-rows:repeat(4,1fr)}.bt-addon-wrap{padding:0 30px 6px}.bt-addon-tooltip{width:280px}}',
/* SSL */
'.bt-ssl-row .bt-row-info{flex-wrap:wrap}',
'.bt-ssl-meta{display:flex;align-items:center;gap:12px;flex-shrink:0;font-size:11px;color:var(--text-muted,#6b7280)}.bt-ssl-meta span{display:inline-flex;align-items:center;gap:4px;white-space:nowrap}',
'.bt-ssl-issuer{color:var(--text-muted,#6b7280)}.bt-ssl-days-ok{color:#059669}.bt-ssl-days-warn{color:#d97706}.bt-ssl-days-danger{color:#ef4444}',
'.bt-row-icon.ssl-valid{background:rgba(5,150,105,.08);color:#059669}.bt-row-icon.ssl-selfsigned{background:rgba(217,119,6,.08);color:#d97706}.bt-row-icon.ssl-expired{background:rgba(239,68,68,.08);color:#ef4444}.bt-row-icon.ssl-expiring{background:rgba(217,119,6,.08);color:#d97706}',
'.bt-badge-red{background:rgba(239,68,68,.08);color:#ef4444}',
'.bt-ssl-generate:hover{background:rgba(5,150,105,.06)!important}',
'@media(max-width:600px){.bt-ssl-meta{flex-direction:column;align-items:flex-start;gap:4px}}',
    ].join('\n');
    document.head.appendChild(s);
}

/* ─── CSS Part 4: Logs, Modals, Form fields, WP detail panel ─── */
function injectStyles4(){
    if(document.getElementById("bt-injected-styles4")) return;
    var s=document.createElement("style");s.id="bt-injected-styles4";
    s.textContent=[
/* Error logs */
'.bt-log-pre{max-height:500px;overflow-y:auto;padding:0;margin:0;background:#0f172a;border-radius:0 0 12px 12px}',
'.bt-log-summary{display:flex;align-items:center;gap:8px;padding:10px 16px;border-bottom:1px solid rgba(148,163,184,.1);flex-wrap:wrap}',
'.bt-log-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;letter-spacing:.2px}',
'.bt-log-badge-fatal{background:rgba(239,68,68,.15);color:#f87171}.bt-log-badge-error{background:rgba(251,146,60,.12);color:#fb923c}.bt-log-badge-warn{background:rgba(250,204,21,.12);color:#fcd34d}.bt-log-badge-ok{background:rgba(5,150,105,.12);color:#6ee7b7}',
'.bt-log-lines{padding:6px 0;display:flex;flex-direction:column}',
'.bt-log-entry{display:flex;align-items:flex-start;gap:0;padding:3px 16px;font-family:"SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace;font-size:12px;line-height:1.65;transition:background .1s;border-left:3px solid transparent}.bt-log-entry:hover{background:rgba(255,255,255,.04)}',
'.bt-log-fatal{border-left-color:#ef4444;background:rgba(239,68,68,.06)}.bt-log-error{border-left-color:#fb923c;background:rgba(251,146,60,.04)}.bt-log-warn{border-left-color:#fbbf24}.bt-log-info{border-left-color:#60a5fa}.bt-log-other{border-left-color:transparent}',
'.bt-log-num{min-width:36px;padding:0 8px 0 0;text-align:right;color:rgba(148,163,184,.35);user-select:none;flex-shrink:0}',
'.bt-log-level-dot{width:6px;height:6px;border-radius:50%;margin:7px 8px 0 0;flex-shrink:0}',
'.bt-log-fatal .bt-log-level-dot{background:#ef4444}.bt-log-error .bt-log-level-dot{background:#fb923c}.bt-log-warn .bt-log-level-dot{background:#fbbf24}.bt-log-info .bt-log-level-dot{background:#60a5fa}.bt-log-other .bt-log-level-dot{background:rgba(148,163,184,.3)}',
'.bt-log-ts{color:rgba(148,163,184,.5);font-size:11px;margin-right:8px;white-space:nowrap;flex-shrink:0}',
'.bt-log-msg{color:#e2e8f0;word-break:break-all;white-space:pre-wrap;flex:1;min-width:0}',
'.bt-log-fatal .bt-log-msg{color:#fca5a5;font-weight:600}.bt-log-error .bt-log-msg{color:#fdba74}.bt-log-warn .bt-log-msg{color:#fde68a}.bt-log-info .bt-log-msg{color:#93c5fd}',
/* Modals */
'.bt-overlay{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:20px;animation:btFadeIn .2s}@keyframes btFadeIn{from{opacity:0}to{opacity:1}}',
'.bt-modal{background:var(--card-bg,#fff);border-radius:14px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.2);animation:btSlideUp .25s}.bt-modal-sm{max-width:380px}@keyframes btSlideUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}',
'.bt-modal-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border-color,#f3f4f6)}',
'.bt-modal-head h5{margin:0;font-size:16px;font-weight:600;color:var(--heading-color,#111827)}',
'.bt-modal-close{width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:none;background:none;font-size:20px;color:var(--text-muted,#9ca3af);border-radius:6px;cursor:pointer;transition:all .15s}.bt-modal-close:hover{background:var(--input-bg,#f3f4f6);color:var(--heading-color,#111827)}',
'.bt-modal-body{padding:20px 22px}',
'.bt-modal-foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 22px;border-top:1px solid var(--border-color,#f3f4f6)}',
    ].join('\n');
    document.head.appendChild(s);
}

/* ─── CSS Part 5: Form fields, buttons, loading, WP detail ─── */
function injectStyles5(){
    if(document.getElementById("bt-injected-styles5")) return;
    var s=document.createElement("style");s.id="bt-injected-styles5";
    s.textContent=[
'.bt-field{margin-bottom:16px}.bt-field:last-child{margin-bottom:0}',
'.bt-field label{display:block;font-size:12px;font-weight:600;color:var(--text-muted,#6b7280);margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px}',
'.bt-field input,.bt-field select,.bt-select{width:100%;padding:9px 12px;border:1px solid var(--border-color,#d1d5db);border-radius:8px;font-size:14px;color:var(--heading-color,#111827);background:var(--input-bg,#fff);outline:none;transition:border-color .15s;box-sizing:border-box}',
'.bt-field input:focus,.bt-field select:focus,.bt-select:focus{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}',
'.bt-field input[readonly]{background:var(--input-bg,#f9fafb);color:var(--text-muted,#6b7280)}',
'.bt-input-group{display:flex;align-items:center;border:1px solid var(--border-color,#d1d5db);border-radius:8px;overflow:hidden;transition:border-color .15s}.bt-input-group:focus-within{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}.bt-input-group input{border:none;border-radius:0;flex:1;min-width:0}.bt-input-group input:focus{box-shadow:none}.bt-input-group select{border:none;border-radius:0;flex:1;min-width:0}.bt-input-group select:focus{box-shadow:none}',
'.bt-at,.bt-prefix{padding:0 10px;font-size:13px;color:var(--text-muted,#9ca3af);font-weight:600;background:var(--input-bg,#f9fafb);border-right:1px solid var(--border-color,#e5e7eb);height:100%;display:flex;align-items:center;white-space:nowrap;flex-shrink:0}',
'.bt-pass-wrap{position:relative}.bt-pass-wrap input{width:100%;padding-right:40px}',
'.bt-pass-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted,#9ca3af);cursor:pointer;padding:4px}.bt-pass-toggle:hover{color:var(--heading-color,#111827)}',
'.bt-docroot-wrap{display:flex;align-items:center;border:1px solid var(--border-color,#d1d5db);border-radius:8px;overflow:hidden;transition:border-color .15s}.bt-docroot-wrap:focus-within{border-color:#0a5ed3;box-shadow:0 0 0 3px rgba(10,94,211,.1)}',
'.bt-docroot-prefix{padding:9px 10px;font-size:13px;color:var(--text-muted,#9ca3af);background:var(--input-bg,#f9fafb);border-right:1px solid var(--border-color,#e5e7eb);white-space:nowrap;flex-shrink:0}',
'.bt-docroot-wrap input{border:none;border-radius:0;flex:1;min-width:0;padding:9px 12px;font-size:14px;outline:none}.bt-docroot-wrap input:focus{box-shadow:none}',
'.bt-btn-cancel{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:var(--input-bg,#f3f4f6);color:var(--heading-color,#374151);transition:all .15s}.bt-btn-cancel:hover{background:#e5e7eb}',
'.bt-btn-primary{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:#0a5ed3;color:#fff;transition:all .15s}.bt-btn-primary:hover{background:#0950b3}',
'.bt-btn-danger{padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;background:#ef4444;color:#fff;transition:all .15s}.bt-btn-danger:hover{background:#dc2626}',
'.bt-btn-primary:disabled,.bt-btn-danger:disabled,.bt-btn-add:disabled{opacity:.5;cursor:not-allowed}',
'.bt-msg{margin-top:12px;padding:8px 12px;border-radius:6px;font-size:13px;display:none}.bt-msg.success{display:block;background:rgba(5,150,105,.08);color:#059669}.bt-msg.error{display:block;background:rgba(239,68,68,.08);color:#ef4444}',
'.bt-checkbox{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:500;color:var(--heading-color,#111827);cursor:pointer;text-transform:none;letter-spacing:0}.bt-checkbox input{width:16px;height:16px;accent-color:#0a5ed3}',
'.bt-loading{padding:40px 22px;text-align:center;color:var(--text-muted,#9ca3af);font-size:14px;display:flex;flex-direction:column;align-items:center;gap:12px}',
'.bt-spinner{width:28px;height:28px;border:3px solid var(--border-color,#e5e7eb);border-top-color:#0a5ed3;border-radius:50%;animation:btSpin .7s linear infinite}',
    ].join('\n');
    document.head.appendChild(s);
}

/* ─── CSS Part 6: WP detail panel ─── */
function injectStyles6(){
    if(document.getElementById("bt-injected-styles6")) return;
    var s=document.createElement("style");s.id="bt-injected-styles6";
    s.textContent=[
'.bwp-detail-panel{width:100%;max-width:900px;max-height:90vh;background:var(--card-bg,#fff);border-radius:16px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,.25);animation:btSlideUp .3s}',
'.bwp-detail-head{display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid var(--border-color,#f3f4f6);flex-shrink:0}',
'.bwp-detail-head h5{flex:1;margin:0;font-size:14px;font-weight:700;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
'.bwp-detail-tabs{display:flex;gap:0;padding:0 20px;border-bottom:1px solid var(--border-color,#f3f4f6);flex-shrink:0}',
'.bwp-tab{padding:12px 18px;font-size:13px;font-weight:600;color:var(--text-muted,#6b7280);cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;margin-bottom:-1px;transition:color .15s,border-color .15s}.bwp-tab:hover{color:var(--heading-color,#111827)}.bwp-tab.active{color:#0a5ed3;border-bottom-color:#0a5ed3}',
'.bwp-detail-body{flex:1;overflow-y:auto;padding:20px}',
'.bwp-tab-content{display:none}.bwp-tab-content.active{display:block}',
'.bwp-overview-hero{display:grid;grid-template-columns:280px 1fr;gap:20px}@media(max-width:700px){.bwp-overview-hero{grid-template-columns:1fr}}',
'.bwp-preview-col{display:flex;flex-direction:column;gap:12px}',
'.bwp-preview-wrap{border:1px solid var(--border-color,#e5e7eb);border-radius:10px;overflow:hidden}',
'.bwp-preview-bar{display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--input-bg,#f9fafb);border-bottom:1px solid var(--border-color,#e5e7eb)}',
'.bwp-preview-dots{display:flex;gap:4px}.bwp-preview-dots span{width:8px;height:8px;border-radius:50%;background:var(--border-color,#d1d5db)}',
'.bwp-preview-url{font-size:11px;color:var(--text-muted,#9ca3af);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
'.bwp-preview-frame-wrap{width:100%;height:200px;overflow:hidden;position:relative}',
'.bwp-quick-actions{display:flex;gap:8px}',
'.bwp-overview-right{display:flex;flex-direction:column;gap:16px}',
'.bwp-site-header{display:flex;align-items:center;gap:12px}.bwp-site-header-icon{width:40px;height:40px;border-radius:10px;background:rgba(33,117,208,.08);display:flex;align-items:center;justify-content:center;color:#2175d0;flex-shrink:0}',
'.bwp-site-header-info h4{margin:0;font-size:16px;font-weight:700;color:var(--heading-color,#111827)}.bwp-site-header-info p{margin:2px 0 0;font-size:12px;color:var(--text-muted,#6b7280);display:flex;gap:12px}',
'.bwp-overview-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}',
'.bwp-stat{padding:12px 14px;background:var(--input-bg,#f8fafc);border:1px solid var(--border-color,#e5e7eb);border-radius:8px}',
'.bwp-stat-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;color:var(--text-muted,#9ca3af);margin:0 0 4px}',
'.bwp-stat-value{font-size:14px;font-weight:600;color:var(--heading-color,#111827)}',
'.bwp-msg{padding:10px 14px;border-radius:8px;font-size:13px;font-weight:500}.bwp-msg.info{background:rgba(217,119,6,.08);color:#d97706}',
    ].join('\n');
    document.head.appendChild(s);
}

/* ─── CSS Part 7: WP site list, items, themes, security ─── */
function injectStyles7(){
    if(document.getElementById("bt-injected-styles7")) return;
    var s=document.createElement("style");s.id="bt-injected-styles7";
    s.textContent=[
'.bwp-site{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:10px;transition:background .12s}.bwp-site:hover{background:var(--input-bg,#f9fafb)}.bwp-site+.bwp-site{border-top:1px solid var(--border-color,#f3f4f6)}',
'.bwp-site-icon{width:40px;height:40px;border-radius:10px;background:rgba(33,117,208,.08);display:flex;align-items:center;justify-content:center;color:#2175d0;flex-shrink:0}',
'.bwp-site-info{flex:1;min-width:0}.bwp-site-domain{margin:0;font-size:14px;font-weight:600;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
'.bwp-site-meta{display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;font-size:11px;color:var(--text-muted,#6b7280)}',
'.bwp-status-badge{display:inline-flex;align-items:center;gap:4px;font-weight:600}.bwp-status-badge.active{color:#059669}.bwp-status-badge.inactive{color:#ef4444}',
'.bwp-site-actions{display:flex;gap:6px;flex-shrink:0}',
'.bwp-item-row{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:9px;transition:background .12s}.bwp-item-row:hover{background:var(--input-bg,#f9fafb)}.bwp-item-row+.bwp-item-row{border-top:1px solid var(--border-color,#f3f4f6)}',
'.bwp-item-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;font-weight:700}.bwp-item-icon.plugin{background:rgba(10,94,211,.08);color:#0a5ed3}.bwp-item-icon.theme{background:rgba(124,58,237,.08);color:#7c3aed}',
'.bwp-item-info{flex:1;min-width:0}.bwp-item-name{margin:0;font-size:13px;font-weight:600;color:var(--heading-color,#111827)}.bwp-item-detail{margin:2px 0 0;font-size:11px;color:var(--text-muted,#6b7280)}',
'.bwp-item-actions{display:flex;gap:6px;flex-shrink:0}',
'.bwp-item-btn{padding:5px 12px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);color:var(--heading-color,#374151);transition:all .12s;white-space:nowrap}.bwp-item-btn:hover{border-color:#0a5ed3;color:#0a5ed3}',
'.bwp-item-btn.active-state{color:#ef4444;border-color:#fca5a5}.bwp-item-btn.active-state:hover{background:rgba(239,68,68,.04);border-color:#ef4444}',
'.bwp-item-btn.inactive-state{color:#059669;border-color:#6ee7b7}.bwp-item-btn.inactive-state:hover{background:rgba(5,150,105,.04);border-color:#059669}',
'.bwp-item-btn.update{color:#0a5ed3;border-color:#93c5fd}.bwp-item-btn.update:hover{background:rgba(10,94,211,.04);border-color:#0a5ed3}',
'.bwp-theme-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px}',
'.bwp-theme-card{border:1px solid var(--border-color,#e5e7eb);border-radius:10px;overflow:hidden;transition:border-color .15s}.bwp-theme-card:hover{border-color:rgba(10,94,211,.25)}.bwp-theme-active{border-color:#0a5ed3;box-shadow:0 0 0 1px #0a5ed3}',
'.bwp-theme-screenshot{height:120px;background:var(--input-bg,#f3f4f6);overflow:hidden;position:relative}.bwp-theme-screenshot img{width:100%;height:100%;object-fit:cover}',
'.bwp-theme-active-badge{position:absolute;top:8px;right:8px;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;background:#0a5ed3;color:#fff}',
'.bwp-theme-info{padding:12px}.bwp-theme-name{margin:0;font-size:13px;font-weight:600;color:var(--heading-color,#111827)}.bwp-theme-ver{margin:2px 0 8px;font-size:11px;color:var(--text-muted,#6b7280)}.bwp-theme-actions{display:flex;gap:6px}',
'.bwp-sec-summary{margin-bottom:16px}.bwp-sec-summary-bar{height:8px;background:var(--input-bg,#e5e7eb);border-radius:4px;overflow:hidden}.bwp-sec-summary-fill{height:100%;background:linear-gradient(90deg,#059669,#0a5ed3);border-radius:4px;transition:width .5s}',
'.bwp-sec-summary-text{display:flex;justify-content:space-between;margin-top:6px;font-size:12px;color:var(--text-muted,#6b7280)}',
'.bwp-security-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-color,#f3f4f6)}',
'.bwp-sec-icon{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}.bwp-sec-icon.ok{background:rgba(5,150,105,.08);color:#059669}.bwp-sec-icon.warning{background:rgba(217,119,6,.08);color:#d97706}',
'.bwp-sec-info{flex:1;min-width:0}.bwp-sec-label{margin:0;font-size:13px;font-weight:600;color:var(--heading-color,#111827)}.bwp-sec-detail{margin:1px 0 0;font-size:11px;color:var(--text-muted,#6b7280)}',
    ].join('\n');
    document.head.appendChild(s);
}

/* ═══════════════════════════════════════════════════════════
   COMPLETE PAGE TAKEOVER — init()
   Replaces .main-content entirely with our own layout.
   Preserves: product-icon card, usage stats, Quick Shortcuts,
   cPanel SSO link, Change Password form.
   ═══════════════════════════════════════════════════════════ */

/* Saved references for cPanel SSO and Change Password */
var savedCpanelLink=null;

/* ─── CSS Part 8: Service header + dark/light mode inheritance ─── */
function injectStyles8(){
    if(document.getElementById("bt-injected-styles8")) return;
    var s=document.createElement("style");s.id="bt-injected-styles8";
    s.textContent=[
/* ── Hero Card ── */
'.bt-hero{display:flex;gap:0;margin-bottom:24px;border-radius:14px;overflow:hidden;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);box-sizing:border-box;max-width:100%}',
'.bt-hero-left{flex:1;min-width:0;background:linear-gradient(135deg,#1a6ddb 0%,#0a5ed3 40%,#3b82f6 100%);color:#fff;padding:28px 24px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;min-height:180px;position:relative;overflow:hidden}',
'.bt-hero-left::before{content:"";position:absolute;top:-40px;right:-40px;width:120px;height:120px;background:rgba(255,255,255,.06);border-radius:50%}',
'.bt-hero-left::after{content:"";position:absolute;bottom:-30px;left:-30px;width:90px;height:90px;background:rgba(255,255,255,.04);border-radius:50%}',
'.bt-hero-icon{width:64px;height:64px;background:rgba(255,255,255,.15);border-radius:16px;display:flex;align-items:center;justify-content:center;margin-bottom:16px;backdrop-filter:blur(4px)}',
'.bt-hero-plan{margin:0;font-size:20px;font-weight:700;line-height:1.3;letter-spacing:-.3px;color:#fff}',
'.bt-hero-status{display:inline-flex;align-items:center;gap:5px;margin-top:10px;font-size:12px;font-weight:600;padding:4px 14px;border-radius:20px;background:rgba(255,255,255,.2);color:#fff;backdrop-filter:blur(4px)}',
'.bt-hero-status .dot{width:7px;height:7px;border-radius:50%;background:#4ade80}',
'.bt-hero-status.suspended .dot{background:#fbbf24}',
'.bt-hero-status.terminated .dot,.bt-hero-status.cancelled .dot{background:#f87171}',
'.bt-hero-domain{margin-top:12px;font-size:13px;color:rgba(255,255,255,.85);font-weight:500}',
/* ── Usage Panel ── */
'.bt-hero-right{width:240px;flex-shrink:0;padding:24px 16px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px}',
'.bt-gauges{display:flex;gap:20px;align-items:center;justify-content:center}',
'.bt-gauge{text-align:center}',
'.bt-gauge-ring{position:relative;width:80px;height:80px}',
'.bt-gauge-ring svg{transform:rotate(-90deg)}',
'.bt-gauge-ring circle{fill:none;stroke-width:7;stroke-linecap:round}',
'.bt-gauge-ring .bg{stroke:var(--border-color,#e5e7eb)}',
'.bt-gauge-ring .fill{transition:stroke-dashoffset .8s ease}',
'.bt-gauge-pct{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:var(--heading-color,#111827)}',
'.bt-gauge-label{margin-top:6px;font-size:11px;font-weight:600;color:var(--heading-color,#111827)}',
'.bt-gauge-sub{font-size:10px;color:var(--text-muted,#6b7280);margin-top:1px}',
/* ── Quick Shortcuts ── */
'.bt-shortcuts{margin-bottom:24px}',
'.bt-shortcuts-title{font-size:16px;font-weight:700;color:var(--heading-color,#111827);margin:0 0 14px}',
'.bt-shortcuts-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;padding:14px 16px}',
'.bt-sc-item{display:flex;align-items:center;gap:7px;padding:7px 8px;border-radius:8px;font-size:12px;font-weight:500;color:#0a5ed3;cursor:pointer;transition:background .12s;text-decoration:none}',
'.bt-sc-item:hover{background:rgba(10,94,211,.06);text-decoration:none;color:#0a5ed3}',
'.bt-sc-item svg{flex-shrink:0;width:16px;height:16px}',
/* ── Responsive ── */
'@media(max-width:900px){.bt-hero{flex-direction:column}.bt-hero-right{width:100%;padding:20px}.bt-hero-left{min-height:140px}}',
'@media(max-width:640px){.bt-shortcuts-grid{grid-template-columns:repeat(2,1fr)}}',
'@media(max-width:400px){.bt-shortcuts-grid{grid-template-columns:1fr}.bt-gauges{gap:12px}}',
/* ── Dark mode ── */
'[data-theme="dark"] .bt-hero{border-color:var(--border-color,#334155)}',
'[data-theme="dark"] .bt-hero-right{background:var(--card-bg,#1e293b)}',
'[data-theme="dark"] .bt-shortcuts-grid{background:var(--card-bg,#1e293b);border-color:var(--border-color,#334155)}',
'[data-theme="dark"] .bt-sc-item:hover{background:rgba(59,130,246,.1)}',
    ].join('\n');
    document.head.appendChild(s);
}
var savedChangePwPane=null;

function init(){
    injectStyles();injectStyles2();injectStyles3();injectStyles4();injectStyles5();injectStyles6();injectStyles7();injectStyles8();

    /* ── Parse config ── */
    var dataEl=$("bt-data");
    if(dataEl){
        try{C=JSON.parse(dataEl.getAttribute("data-config"));}catch(e){return;}
    }else if(window.__btConfig){
        C=window.__btConfig;
    }else{return;}

    /* ── Standalone page mode: #bt-page-wrap already exists in managev2.php ── */
    var pageWrap=$("bt-page-wrap");
    if(!pageWrap) return;
    pageWrap.className="bt-page-wrap";

    /* ── Build hero card with circular gauges ── */
    var statusLc=(C.status||"active").toLowerCase();
    var diskPct=C.diskLimit>0?Math.min(100,Math.round(C.diskUsed/C.diskLimit*100)):0;
    var bwPct=C.bwLimit>0?Math.min(100,Math.round(C.bwUsed/C.bwLimit*100)):0;
    var circ=2*Math.PI*35;/* radius 35, circumference ~219.91 */
    var diskOff=circ-(diskPct/100*circ);
    var bwOff=circ-(bwPct/100*circ);
    var diskColor=diskPct>90?"#ef4444":diskPct>70?"#f59e0b":"#0a5ed3";
    var bwColor=bwPct>90?"#ef4444":bwPct>70?"#f59e0b":"#0a5ed3";
    function fmtSize(mb){if(mb>=1024)return(mb/1024).toFixed(1)+' GB';return mb+' M';}

    var heroHtml='<div class="bt-hero">';
    heroHtml+='<div class="bt-hero-left"><div class="bt-hero-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>';
    heroHtml+='<h2 class="bt-hero-plan">'+esc(C.productName||"Hosting Plan")+'</h2>';
    heroHtml+='<span class="bt-hero-status '+statusLc+'"><span class="dot"></span>'+esc(C.status||"Active")+'</span>';
    heroHtml+='<div class="bt-hero-domain">'+esc(C.domain||"")+'</div>';
    heroHtml+='</div>';
    heroHtml+='<div class="bt-hero-right"><div class="bt-gauges">';
    /* Disk gauge */
    heroHtml+='<div class="bt-gauge"><div class="bt-gauge-ring"><svg width="80" height="80" viewBox="0 0 80 80"><circle class="bg" cx="40" cy="40" r="35"/><circle class="fill" cx="40" cy="40" r="35" stroke="'+diskColor+'" stroke-dasharray="'+circ.toFixed(1)+'" stroke-dashoffset="'+diskOff.toFixed(1)+'"/></svg><div class="bt-gauge-pct">'+diskPct+'%</div></div><div class="bt-gauge-label">Disk</div><div class="bt-gauge-sub">'+fmtSize(C.diskUsed)+' / '+fmtSize(C.diskLimit)+'</div></div>';
    /* BW gauge */
    heroHtml+='<div class="bt-gauge"><div class="bt-gauge-ring"><svg width="80" height="80" viewBox="0 0 80 80"><circle class="bg" cx="40" cy="40" r="35"/><circle class="fill" cx="40" cy="40" r="35" stroke="'+bwColor+'" stroke-dasharray="'+circ.toFixed(1)+'" stroke-dashoffset="'+bwOff.toFixed(1)+'"/></svg><div class="bt-gauge-pct">'+bwPct+'%</div></div><div class="bt-gauge-label">Bandwidth</div><div class="bt-gauge-sub">'+fmtSize(C.bwUsed)+' / '+(C.bwLimit>0?fmtSize(C.bwLimit):'Unlimited')+'</div></div>';
    heroHtml+='</div></div></div>';

    /* ── Quick Shortcuts — open cPanel features in new tab ── */
    var cpBase='clientarea.php?action=productdetails&id='+C.serviceId+'&dosinglesignon=1';
    heroHtml+='<div class="bt-shortcuts"><h3 class="bt-shortcuts-title">Quick Shortcuts</h3><div class="bt-shortcuts-grid">';
    var shortcuts=[
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',label:'Email Accounts',cpanel:'EMAIL_ACCOUNTS'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/><path d="M22 6l-10 7L2 6"/></svg>',label:'Forwarders',cpanel:'EMAIL_FWD'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',label:'Backup',cpanel:'BACKUP'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>',label:'File Manager',cpanel:'FILE_MANAGER'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg>',label:'Domains',cpanel:'ADDON_DOMAINS'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',label:'Cron Jobs',cpanel:'CRON_JOBS'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',label:'MySQL Databases',cpanel:'DATABASES'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',label:'Awstats',cpanel:'AWSTATS'}
    ];
    shortcuts.forEach(function(sc){
        heroHtml+='<a class="bt-sc-item" href="'+cpBase+'&goto='+sc.cpanel+'" target="_blank">'+sc.icon+' '+esc(sc.label)+'</a>';
    });
    heroHtml+='</div></div>';

    /* ── Build sidebar ── */
    var sidebar=document.createElement("div");
    sidebar.className="bt-sidebar";
    sidebar.id="bt-sidebar";
    sidebar.innerHTML=buildSidebarHtml();

    /* ── Build main area ── */
    var mainArea=document.createElement("div");
    mainArea.className="bt-main-area";
    mainArea.id="bt-main-area";

    /* ── Service header + shortcuts ── */
    var headerDiv=document.createElement("div");
    headerDiv.innerHTML=heroHtml;
    while(headerDiv.firstChild) mainArea.appendChild(headerDiv.firstChild);

    /* ── Tabs container ── */
    var tabsWrap=document.createElement("div");
    tabsWrap.className="bt-wrap";tabsWrap.id="bt-wrap";
    mainArea.appendChild(tabsWrap);

    /* ── WordPress page (hidden by default) ── */
    var wpPage=document.createElement("div");
    wpPage.id="bt-wp-page";
    wpPage.style.display="none";
    mainArea.appendChild(wpPage);

    /* ── Change Password page (hidden by default) ── */
    var changePwPage=document.createElement("div");
    changePwPage.id="bt-changepw-page";
    changePwPage.style.display="none";
    mainArea.appendChild(changePwPage);

    /* ── Assemble layout ── */
    pageWrap.appendChild(sidebar);
    pageWrap.appendChild(mainArea);

    /* ── Build modals ── */
    if(!$("bemCreateModal")&&C.serviceId){
        var modalsHtml=buildModalsHtml();
        var tmp=document.createElement("div");
        tmp.innerHTML=modalsHtml;
        while(tmp.firstChild) document.body.appendChild(tmp.firstChild);
    }

    /* ── Build tabs ── */
    buildTabs();
    bindModals();
    bindSidebarActions();

    /* ── Deep link from hash ── */
    activateTabFromHash();
    window.addEventListener("hashchange",activateTabFromHash);
}

/* ─── Build Sidebar HTML ─── */
function buildSidebarHtml(){
    var html='<div class="bt-sidebar-panel"><div class="bt-sidebar-title">Overview</div>';
    html+='<a class="bt-sidebar-item active" data-page="tabs" data-tab="overview"><div class="bt-si-icon" style="background:rgba(10,94,211,.08);color:#0a5ed3"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div><div class="bt-si-label">Information<span>Service Details</span></div></a>';
    html+='<a class="bt-sidebar-item" data-page="tabs" data-tab="overview" data-scroll="addons"><div class="bt-si-icon" style="background:rgba(5,150,105,.08);color:#059669"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div><div class="bt-si-label">Addons<span>Extra Services</span></div></a>';
    if(C.wpEnabled){
        html+='<a class="bt-sidebar-item" data-page="wordpress"><div class="bt-si-icon" style="background:rgba(33,117,208,.08);color:#2175d0"><svg viewBox="0 0 16 16" fill="#2175d0" width="18" height="18"><path d="M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046zM1.137 8a6.86 6.86 0 0 0 3.868 6.176L1.73 5.206A6.8 6.8 0 0 0 1.137 8"/><path d="M6.061 14.583 8.121 8.6l2.109 5.78q.02.05.049.094a6.85 6.85 0 0 1-4.218.109m7.96-9.876q.046.328.047.706c0 .696-.13 1.479-.522 2.458l-2.096 6.06a6.86 6.86 0 0 0 2.572-9.224z"/><path fill-rule="evenodd" d="M0 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.59 8-8 8-8-3.589-8-8m.367 0c0 4.209 3.424 7.633 7.633 7.633S15.632 12.209 15.632 8C15.632 3.79 12.208.367 8 .367 3.79.367.367 3.79.367 8"/></svg></div><div class="bt-si-label">WordPress<span>Site Management</span></div></a>';
    }
    html+='</div>';

    /* Actions panel */
    html+='<div class="bt-sidebar-panel"><div class="bt-sidebar-title">Actions</div>';
    /* cPanel login */
    html+='<a class="bt-sidebar-item" href="clientarea.php?action=productdetails&id='+C.serviceId+'&dosinglesignon=1" target="_blank" id="bt-cpanel-link"><div class="bt-si-icon" style="background:rgba(255,106,19,.08);color:#ff6a13"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div><div class="bt-si-label">cPanel<span>Control Panel</span></div></a>';
    html+='<a class="bt-sidebar-item" href="clientarea.php?action=productdetails&id='+C.serviceId+'#tabChangepw" target="_blank"><div class="bt-si-icon" style="background:rgba(217,119,6,.08);color:#d97706"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div><div class="bt-si-label">Change Password<span>Update Credentials</span></div></a>';
    html+='<a class="bt-sidebar-item" href="upgrade.php?type=package&id='+C.serviceId+'"><div class="bt-si-icon" style="background:rgba(5,150,105,.08);color:#059669"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div><div class="bt-si-label">Upgrade/Downgrade<span>Change Plan</span></div></a>';
    html+='<a class="bt-sidebar-item" href="clientarea.php?action=cancel&id='+C.serviceId+'"><div class="bt-si-icon" style="background:rgba(239,68,68,.08);color:#ef4444"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div class="bt-si-label">Cancel Service<span>Request Cancellation</span></div></a>';
    html+='</div>';
    return html;
}

/* ─── Sidebar click handlers ─── */
function bindSidebarActions(){
    var sidebar=$("bt-sidebar");if(!sidebar) return;
    sidebar.querySelectorAll(".bt-sidebar-item[data-page]").forEach(function(item){
        item.addEventListener("click",function(e){
            e.preventDefault();
            var page=this.getAttribute("data-page");
            var tab=this.getAttribute("data-tab")||"";
            /* Deactivate all sidebar items */
            sidebar.querySelectorAll(".bt-sidebar-item").forEach(function(si){si.classList.remove("active");});
            this.classList.add("active");

            /* Hide all pages */
            var wrap=$("bt-wrap");
            var wpPage=$("bt-wp-page");
            var changePwPage=$("bt-changepw-page");
            if(wrap) wrap.style.display="none";
            if(wpPage) wpPage.style.display="none";
            if(changePwPage) changePwPage.style.display="none";

            if(page==="tabs"){
                if(wrap) wrap.style.display="";
                if(tab){
                    var tabBtn=document.querySelector('.bt-tab-btn[data-tab="'+tab+'"]');
                    if(tabBtn) tabBtn.click();
                }
                var scrollTarget=this.getAttribute("data-scroll");
                if(scrollTarget==="addons"){
                    var acc=$("btAccAddons");
                    if(acc){acc.classList.add("open");setTimeout(function(){acc.scrollIntoView({behavior:"smooth",block:"start"});},100);}
                }
                var hashName="tab"+(tab?tab.charAt(0).toUpperCase()+tab.slice(1):"Overview");
                if(history.replaceState) history.replaceState(null,null,"#"+hashName);
            }else if(page==="wordpress"){
                if(wpPage){
                    wpPage.style.display="";
                    if(!wpPage.dataset.loaded){
                        wpPage.dataset.loaded="1";
                        buildWpPaneInto(wpPage);
                        loadWpInstances();
                    }
                }
                if(history.replaceState) history.replaceState(null,null,"#tabWordpress");
            }else if(page==="changepw"){
                if(changePwPage) changePwPage.style.display="";
                if(history.replaceState) history.replaceState(null,null,"#tabChangepw");
            }
        });
    });
}

/* ─── Build Tabs (simplified — renders into #bt-wrap) ─── */
function buildTabs(){
    var wrap=$("bt-wrap");if(!wrap) return;

    var tabs=[
        {id:"overview",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>',label:"Overview"},
        {id:"domains",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg>',label:"Domains",check:"domainEnabled"},
        {id:"ssl",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',label:"SSL",check:"sslEnabled"},
        {id:"email",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',label:"Email Accounts",check:"emailEnabled"},
        {id:"databases",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',label:"Databases",check:"dbEnabled"},
        {id:"dns",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',label:"DNS Manager",check:"dnsEnabled"},
        {id:"cronjobs",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',label:"Cron Jobs",check:"cronEnabled"},
        {id:"phpversion",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/><line x1="14" y1="4" x2="10" y2="20"/></svg>',label:"PHP",check:"phpEnabled"},
        {id:"errorlogs",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',label:"Error Logs",check:"logsEnabled"},
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
            /* Update sidebar: activate Information item */
            var sidebar=$("bt-sidebar");
            if(sidebar){
                sidebar.querySelectorAll(".bt-sidebar-item").forEach(function(si){si.classList.remove("active");});
                var infoItem=sidebar.querySelector('.bt-sidebar-item[data-page="tabs"][data-tab="overview"]');
                if(infoItem) infoItem.classList.add("active");
            }
            /* Show tabs, hide other pages */
            var wpPage=$("bt-wp-page");if(wpPage) wpPage.style.display="none";
            var changePwPage=$("bt-changepw-page");if(changePwPage) changePwPage.style.display="none";
            wrap.style.display="";
            var hashName="tab"+t.id.charAt(0).toUpperCase()+t.id.slice(1);
            if(history.replaceState) history.replaceState(null,null,"#"+hashName);
            if(t.id==="databases"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadDatabases();}
            if(t.id==="ssl"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadSSLStatus();}
            if(t.id==="dns"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadDnsDomains();}
            if(t.id==="cronjobs"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadCronJobs();}
            if(t.id==="phpversion"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadPhpVersions();}
            if(t.id==="errorlogs"&&!pane.dataset.loaded){pane.dataset.loaded="1";loadErrorLogs();}
        });
        nav.appendChild(btn);
        var pane=document.createElement("div");
        pane.className="bt-tab-pane"+(firstTab?" active":"");
        pane.id="bt-pane-"+t.id;
        panes.appendChild(pane);
        firstTab=false;
    });

    wrap.appendChild(nav);wrap.appendChild(panes);

    buildOverviewPane();
    if(C.domainEnabled) buildDomainsPane();
    if(C.emailEnabled) buildEmailPane();
    if(C.dbEnabled) buildDatabasesPane();
    if(C.sslEnabled) buildSSLPane();
    if(C.dnsEnabled) buildDnsPane();
    if(C.cronEnabled) buildCronPane();
    if(C.phpEnabled) buildPhpPane();
    if(C.logsEnabled) buildLogsPane();
}

/* ─── Deep link from URL hash ─── */
function activateTabFromHash(){
    var hash=(location.hash||"").replace("#","").toLowerCase();
    if(!hash||hash.indexOf("tab")!==0) return;
    var tabName=hash.replace(/^tab/,"").toLowerCase();
    var hashMap={
        "wordpress":"wordpress","wp":"wordpress",
        "overview":"overview","domains":"domains","domain":"domains",
        "ssl":"ssl","email":"email","emailaccounts":"email",
        "databases":"databases","database":"databases","db":"databases",
        "dns":"dns","dnsmanager":"dns","cronjobs":"cronjobs","cron":"cronjobs",
        "php":"phpversion","phpversion":"phpversion",
        "errorlogs":"errorlogs","errors":"errorlogs","logs":"errorlogs",
        "addons":"overview","addonsextras":"overview",
        "changepw":"changepw","changepassword":"changepw"
    };
    var targetId=hashMap[tabName]||tabName;

    if(targetId==="wordpress"){
        var wpItem=document.querySelector('.bt-sidebar-item[data-page="wordpress"]');
        if(wpItem) wpItem.click();
        return;
    }
    if(targetId==="changepw"){
        var cpItem=document.querySelector('.bt-sidebar-item[data-page="changepw"]');
        if(cpItem) cpItem.click();
        return;
    }
    var tabBtn=document.querySelector('.bt-tab-btn[data-tab="'+targetId+'"]');
    if(tabBtn) tabBtn.click();
}

/* ─── Modals HTML ─── */
function buildModalsHtml(){
    return '<div class="bt-overlay" id="bemCreateModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Create Email Account</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Email Address</label><div class="bt-input-group"><input type="text" id="bemNewUser" placeholder="username" autocomplete="off"><span class="bt-at">@</span><select id="bemNewDomain"><option>Loading...</option></select></div></div><div class="bt-field"><label>Password</label><div class="bt-pass-wrap"><input type="password" id="bemNewPass" placeholder="Strong password" autocomplete="new-password"><button type="button" class="bt-pass-toggle" data-toggle-pass="bemNewPass">&#128065;</button></div></div><div class="bt-field"><label>Quota (MB)</label><input type="number" id="bemNewQuota" value="250" min="1"></div><div class="bt-msg" id="bemCreateMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bemCreateSubmit">Create Account</button></div></div></div>'
    +'<div class="bt-overlay" id="bemPassModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Change Password</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Email</label><input type="text" id="bemPassEmail" readonly></div><div class="bt-field"><label>New Password</label><div class="bt-pass-wrap"><input type="password" id="bemPassNew" placeholder="New password" autocomplete="new-password"><button type="button" class="bt-pass-toggle" data-toggle-pass="bemPassNew">&#128065;</button></div></div><div class="bt-msg" id="bemPassMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bemPassSubmit">Update Password</button></div></div></div>'
    +'<div class="bt-overlay" id="bemDelModal" style="display:none"><div class="bt-modal bt-modal-sm"><div class="bt-modal-head"><h5>Delete Email Account</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body" style="text-align:center"><p style="margin:0 0 4px;font-size:14px">Are you sure you want to delete</p><p style="margin:0;font-size:15px;font-weight:600;color:#ef4444" id="bemDelEmail"></p><div class="bt-msg" id="bemDelMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-danger" id="bemDelSubmit">Delete</button></div></div></div>'
    +'<div class="bt-overlay" id="bdmAddonModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Add Addon Domain</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Domain Name</label><input type="text" id="bdmAddonDomain" placeholder="example.com" autocomplete="off"></div><div class="bt-field"><label>Document Root</label><div class="bt-docroot-wrap"><span class="bt-docroot-prefix">/home/user/</span><input type="text" id="bdmAddonDocroot" placeholder="example.com"></div></div><div class="bt-msg" id="bdmAddonMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdmAddonSubmit">Add Domain</button></div></div></div>'
    +'<div class="bt-overlay" id="bdmSubModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Add Subdomain</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Subdomain</label><div class="bt-input-group"><input type="text" id="bdmSubName" placeholder="blog" autocomplete="off"><span class="bt-at">.</span><select id="bdmSubParent"><option>Loading...</option></select></div></div><div class="bt-field"><label>Document Root</label><div class="bt-docroot-wrap"><span class="bt-docroot-prefix">/home/user/</span><input type="text" id="bdmSubDocroot" placeholder="blog.example.com"></div></div><div class="bt-msg" id="bdmSubMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdmSubSubmit">Add Subdomain</button></div></div></div>'
    +'<div class="bt-overlay" id="bdmDelModal" style="display:none"><div class="bt-modal bt-modal-sm"><div class="bt-modal-head"><h5>Delete Domain</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body" style="text-align:center"><p style="margin:0 0 4px;font-size:14px">Are you sure you want to delete</p><p style="margin:0;font-size:15px;font-weight:600;color:#ef4444" id="bdmDelDomain"></p><div class="bt-msg" id="bdmDelMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-danger" id="bdmDelSubmit">Delete</button></div></div></div>'
    +'<div class="bt-overlay" id="bdbCreateModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Create Database</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Database Name</label><div class="bt-input-group"><span class="bt-prefix" id="bdbPrefix">user_</span><input type="text" id="bdbNewName" placeholder="mydb" autocomplete="off"></div></div><div class="bt-msg" id="bdbCreateMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdbCreateSubmit">Create Database</button></div></div></div>'
    +'<div class="bt-overlay" id="bdbUserModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Create Database User</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Username</label><div class="bt-input-group"><span class="bt-prefix" id="bdbUserPrefix">user_</span><input type="text" id="bdbNewUser" placeholder="dbuser" autocomplete="off"></div></div><div class="bt-field"><label>Password</label><div class="bt-pass-wrap"><input type="password" id="bdbUserPass" placeholder="Strong password" autocomplete="new-password"><button type="button" class="bt-pass-toggle" data-toggle-pass="bdbUserPass">&#128065;</button></div></div><div class="bt-msg" id="bdbUserMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdbUserSubmit">Create User</button></div></div></div>'
    +'<div class="bt-overlay" id="bdbAssignModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Assign User to Database</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Database</label><select id="bdbAssignDb" class="bt-select"></select></div><div class="bt-field"><label>User</label><select id="bdbAssignUser" class="bt-select"></select></div><div class="bt-field"><label>Privileges</label><label class="bt-checkbox"><input type="checkbox" id="bdbAssignAll" checked> All Privileges</label></div><div class="bt-msg" id="bdbAssignMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdbAssignSubmit">Assign Privileges</button></div></div></div>'
    +'<div class="bt-overlay" id="bwpDetailOverlay" style="display:none"><div class="bwp-detail-panel"><div class="bwp-detail-head"><h5 id="bwpDetailTitle">Site Details</h5><button type="button" class="bt-modal-close" id="bwpDetailClose">&times;</button></div><div class="bwp-detail-tabs"><button type="button" class="bwp-tab active" data-tab="overview">Overview</button><button type="button" class="bwp-tab" data-tab="plugins">Plugins</button><button type="button" class="bwp-tab" data-tab="themes">Themes</button><button type="button" class="bwp-tab" data-tab="security">Security</button></div><div class="bwp-detail-body" id="bwpDetailBody"><div class="bwp-tab-content active" id="bwpTabOverview"></div><div class="bwp-tab-content" id="bwpTabPlugins"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading plugins...</span></div></div><div class="bwp-tab-content" id="bwpTabThemes"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading themes...</span></div></div><div class="bwp-tab-content" id="bwpTabSecurity"><div class="bt-loading"><div class="bt-spinner"></div><span>Running security scan...</span></div></div></div></div></div>';
}

/* ─── Overview Pane ─── */
function buildOverviewPane(){
    var pane=$("bt-pane-overview");if(!pane) return;
    var pairs=[];
    /* Try to get billing info from config data */
    /* Since we nuked the DOM, we rely on C (config) for overview data */
    /* The config doesn't include billing pairs directly — they were in the DOM */
    /* We'll show what we have from config */
    var html="";

    /* Build overview cards from available config data */
    if(C.domains&&C.domains.main){
        pairs.push({label:"Primary Domain",value:'<a href="https://'+esc(C.domains.main)+'" target="_blank">'+esc(C.domains.main)+'</a>'});
    }
    if(C.serviceId){
        pairs.push({label:"Service ID",value:esc(String(C.serviceId))});
    }

    if(pairs.length){
        html+='<div class="bt-ov-grid">';
        pairs.forEach(function(p){
            html+='<div class="bt-ov-card"><div class="bt-ov-label">'+esc(p.label)+'</div><div class="bt-ov-value">'+p.value+'</div></div>';
        });
        html+='</div>';
    }

    /* Nameservers accordion */
    if(C.nsEnabled&&C.ns&&C.ns.ns&&C.ns.ns.length){
        html+='<div class="bt-accordion" id="btAccNs"><div class="bt-accordion-head" onclick="this.parentElement.classList.toggle(\'open\')"><div class="bt-accordion-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg></div><div class="bt-accordion-info"><h5>Nameservers</h5><p>Point your domain to these nameservers</p></div><svg class="bt-accordion-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></div><div class="bt-accordion-body"><div class="bt-list" style="padding:4px 10px 10px">';
        C.ns.ns.forEach(function(ns,i){
            html+='<div class="bt-row"><div class="bt-row-icon ns">NS'+(i+1)+'</div><div class="bt-row-info"><span class="bt-row-name mono">'+esc(ns)+'</span></div><button type="button" class="bt-copy" data-copy="'+esc(ns)+'"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button></div>';
        });
        if(C.ns.ip){
            html+='<div class="bt-row"><div class="bt-row-icon ip">IP</div><div class="bt-row-info"><span class="bt-row-name mono">'+esc(C.ns.ip)+'</span></div><button type="button" class="bt-copy" data-copy="'+esc(C.ns.ip)+'"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button></div>';
        }
        html+='</div></div></div>';
    }

    /* Addons/Upgrades — we can't parse from DOM anymore since we nuked it.
       We'll add a placeholder that loads via AJAX if needed */
    html+='<div id="btAccAddons"></div>';

    pane.innerHTML=html;
    pane.querySelectorAll(".bt-copy").forEach(function(b){b.addEventListener("click",function(){doCopy(this.getAttribute("data-copy"),this);});});
}

/* ─── Domains Pane ─── */
function buildDomainsPane(){
    var pane=$("bt-pane-domains");if(!pane||!C.domains) return;
    var d=C.domains;var total=1+(d.addon?d.addon.length:0)+(d.sub?d.sub.length:0)+(d.parked?d.parked.length:0);
    var html='<div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg></div><div><h5>Domains</h5><p class="bt-dom-count">'+total+' domain'+(total!==1?'s':'')+'</p></div></div><div class="bt-card-head-right"><button type="button" class="bt-btn-add" id="bdmAddAddonBtn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Domain</button><button type="button" class="bt-btn-outline" id="bdmAddSubBtn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/></svg> Add Subdomain</button></div></div><div class="bt-list" id="bt-dom-list">';
    if(d.main) html+=domRow(d.main,"main","Primary",false);
    if(d.addon) d.addon.forEach(function(dm){html+=domRow(dm,"addon","Addon",true);});
    if(d.sub) d.sub.forEach(function(dm){html+=domRow(dm,"sub","Subdomain",true);});
    if(d.parked) d.parked.forEach(function(dm){html+=domRow(dm,"parked","Alias",true);});
    html+='</div></div>';
    pane.innerHTML=html;
    bindDomainActions(pane);
    $("bdmAddAddonBtn").addEventListener("click",openAddonModal);
    $("bdmAddSubBtn").addEventListener("click",openSubModal);
}
function domRow(name,type,badge,canDel){
    var e=esc(name);var badgeClass=type==="main"?"bt-badge-primary":type==="addon"?"bt-badge-green":type==="sub"?"bt-badge-purple":"bt-badge-amber";
    return '<div class="bt-row" data-domain="'+e+'" data-type="'+type+'"><div class="bt-row-icon '+type+'"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/></svg></div><div class="bt-row-info"><span class="bt-row-name">'+e+'</span><span class="bt-row-badge '+badgeClass+'">'+badge+'</span></div><div class="bt-row-actions"><a href="https://'+e+'" target="_blank" class="bt-row-btn visit"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg><span>Visit</span></a>'+(canDel?'<button type="button" class="bt-row-btn del" data-domain="'+e+'" data-type="'+type+'"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button>':'')+'</div></div>';
}

/* ─── Email Pane ─── */
function buildEmailPane(){
    var pane=$("bt-pane-email");if(!pane) return;
    var emails=C.emails||[];var count=emails.length;
    var html='<div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></div><div><h5>Email Accounts</h5><p class="bt-email-count">'+(count===1?"1 account":count+" accounts")+'</p></div></div><div class="bt-card-head-right"><button type="button" class="bt-btn-add" id="bemCreateBtn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Create Email</button></div></div><div class="bt-list" id="bt-email-list">';
    if(!count) html+='<div class="bt-empty"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg><span>No email accounts found</span></div>';
    else emails.forEach(function(em){html+=emailRow(em);});
    html+='</div></div>';
    pane.innerHTML=html;bindEmailActions(pane);
    $("bemCreateBtn").addEventListener("click",openCreateEmailModal);
}
function emailRow(email){
    var e=esc(email);var ini=email.charAt(0).toUpperCase();
    return '<div class="bt-row" data-email="'+e+'"><div class="bt-row-icon email">'+ini+'</div><div class="bt-row-info"><span class="bt-row-name">'+e+'</span></div><div class="bt-row-actions"><button type="button" class="bt-row-btn login" data-email="'+e+'"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg><span>Login</span></button><button type="button" class="bt-row-btn pass" data-email="'+e+'"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><span>Password</span></button><button type="button" class="bt-row-btn del" data-email="'+e+'"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button></div></div>';
}

/* ─── Databases Pane ─── */
function buildDatabasesPane(){
    var pane=$("bt-pane-databases");if(!pane) return;
    pane.innerHTML='<div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg></div><div><h5>Databases</h5><p class="bt-db-count">Loading...</p></div></div><div class="bt-card-head-right"><button type="button" class="bt-btn-add" id="bdbCreateBtn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> New Database</button><button type="button" class="bt-btn-outline" id="bdbUserBtn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> New User</button><button type="button" class="bt-btn-outline" id="bdbAssignBtn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg> Assign</button><a class="bt-btn-outline" id="bdbPmaBtn" href="#" target="_blank"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg> phpMyAdmin</a></div></div><div class="bt-list" id="bt-db-list"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading databases...</span></div></div></div>';
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
    list.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading databases...</span></div>';
    post({action:"list_databases"},function(r){
        if(!r.success){list.innerHTML='<div class="bt-empty"><span>'+(r.message||"Failed to load")+'</span></div>';return;}
        var dbs=r.databases||[];var users=r.users||[];var mappings=r.mappings||[];
        var countEl=document.querySelector(".bt-db-count");
        if(countEl) countEl.textContent=dbs.length+" database"+(dbs.length!==1?"s":"")+", "+users.length+" user"+(users.length!==1?"s":"");
        if(r.prefix){var pe=$("bdbPrefix");if(pe)pe.textContent=r.prefix;var upe=$("bdbUserPrefix");if(upe)upe.textContent=r.prefix;}
        var html="";
        if(!dbs.length&&!users.length){
            html='<div class="bt-empty"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg><span>No databases found</span></div>';
        }else{
            dbs.forEach(function(db){
                var dbUsers=[];mappings.forEach(function(m){if(m.db===db&&m.user)dbUsers.push(m.user);});
                var userBadges=dbUsers.length?dbUsers.map(function(u){return '<span class="bt-row-badge bt-badge-purple">'+esc(u)+'</span>';}).join(""):'<span class="bt-row-badge bt-badge-amber">No users</span>';
                html+='<div class="bt-row" data-db="'+esc(db)+'"><div class="bt-row-icon db"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg></div><div class="bt-row-info" style="flex-wrap:wrap"><span class="bt-row-name mono">'+esc(db)+'</span>'+userBadges+'</div><div class="bt-row-actions"><button type="button" class="bt-row-btn del" data-db="'+esc(db)+'"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button></div></div>';
            });
            if(users.length){
                html+='<div style="padding:12px 14px 4px;font-size:12px;font-weight:700;color:var(--text-muted,#9ca3af);text-transform:uppercase;letter-spacing:.5px">Database Users</div>';
                users.forEach(function(u){
                    html+='<div class="bt-row" data-dbuser="'+esc(u)+'"><div class="bt-row-icon dbuser"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div><div class="bt-row-info"><span class="bt-row-name mono">'+esc(u)+'</span><span class="bt-row-badge bt-badge-purple">User</span></div><div class="bt-row-actions"><button type="button" class="bt-row-btn del" data-dbuser="'+esc(u)+'"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button></div></div>';
                });
            }
        }
        list.innerHTML=html;
        list.querySelectorAll(".bt-row-btn.del[data-db]").forEach(function(b){b.addEventListener("click",function(){
            if(confirm("Delete database "+this.getAttribute("data-db")+"?")){var btn=this;btnLoad(btn,"");post({action:"delete_database",database:this.getAttribute("data-db")},function(r){btnDone(btn);if(r.success)loadDatabases();else alert(r.message||"Failed");});}
        });});
        list.querySelectorAll(".bt-row-btn.del[data-dbuser]").forEach(function(b){b.addEventListener("click",function(){
            if(confirm("Delete user "+this.getAttribute("data-dbuser")+"?")){var btn=this;btnLoad(btn,"");post({action:"delete_db_user",dbuser:this.getAttribute("data-dbuser")},function(r){btnDone(btn);if(r.success)loadDatabases();else alert(r.message||"Failed");});}
        });});
        updateAssignSelects(dbs,users);
    });
}
function submitCreateDb(){var name=$("bdbNewName").value.trim();var msg=$("bdbCreateMsg");msg.style.display="none";if(!name){showMsg(msg,"Please enter a database name",false);return;}var btn=$("bdbCreateSubmit");btnLoad(btn,"Creating...");post({action:"create_database",dbname:name},function(r){btnDone(btn);showMsg(msg,r.message||"Done",r.success);if(r.success){setTimeout(function(){$("bdbCreateModal").style.display="none";loadDatabases();},800);}});}
function submitCreateDbUser(){var name=$("bdbNewUser").value.trim();var pass=$("bdbUserPass").value;var msg=$("bdbUserMsg");msg.style.display="none";if(!name||!pass){showMsg(msg,"Please fill in all fields",false);return;}var btn=$("bdbUserSubmit");btnLoad(btn,"Creating...");post({action:"create_db_user",dbuser:name,dbpass:pass},function(r){btnDone(btn);showMsg(msg,r.message||"Done",r.success);if(r.success){setTimeout(function(){$("bdbUserModal").style.display="none";loadDatabases();},800);}});}
function openAssignModal(){$("bdbAssignModal").style.display="flex";$("bdbAssignMsg").style.display="none";}
function updateAssignSelects(dbs,users){var dbSel=$("bdbAssignDb");var uSel=$("bdbAssignUser");if(!dbSel||!uSel)return;dbSel.innerHTML="";uSel.innerHTML="";dbs.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;dbSel.appendChild(o);});users.forEach(function(u){var o=document.createElement("option");o.value=u;o.textContent=u;uSel.appendChild(o);});}
function submitAssignDb(){var db=$("bdbAssignDb").value;var user=$("bdbAssignUser").value;var msg=$("bdbAssignMsg");msg.style.display="none";var priv=$("bdbAssignAll").checked?"ALL PRIVILEGES":"SELECT,INSERT,UPDATE,DELETE";if(!db||!user){showMsg(msg,"Select a database and user",false);return;}var btn=$("bdbAssignSubmit");btnLoad(btn,"Assigning...");post({action:"assign_db_user",database:db,dbuser:user,privileges:priv},function(r){btnDone(btn);showMsg(msg,r.message||"Done",r.success);if(r.success){setTimeout(function(){$("bdbAssignModal").style.display="none";loadDatabases();},800);}});}

/* ─── SSL Pane ─── */
function buildSSLPane(){var pane=$("bt-pane-ssl");if(!pane) return;pane.innerHTML='<div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle" style="background:#059669"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div><div><h5>SSL Certificates</h5><p class="bt-ssl-count">Loading...</p></div></div><div class="bt-card-head-right"><button type="button" class="bt-btn-add" id="btSslRunAutossl" style="background:#059669"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Run AutoSSL</button></div></div><div class="bt-list" id="bt-ssl-list"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading SSL status...</span></div></div></div>';$("btSslRunAutossl").addEventListener("click",function(){startAutoSSL(this);});}
function loadSSLStatus(){var list=$("bt-ssl-list");if(!list) return;list.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading SSL status...</span></div>';post({action:"ssl_status"},function(r){if(!r.success){list.innerHTML='<div class="bt-empty"><span>'+(r.message||"Failed to load SSL status")+'</span></div>';return;}var certs=r.certificates||[];var countEl=document.querySelector(".bt-ssl-count");if(countEl) countEl.textContent=certs.length+" domain"+(certs.length!==1?"s":"");if(!certs.length){list.innerHTML='<div class="bt-empty"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><span>No SSL data found</span></div>';return;}var html="";certs.forEach(function(c){var statusCls="bt-ssl-valid";var statusTxt="Valid";var statusIcon="check";var badgeCls="bt-badge-green";var issuer=c.issuer||"Unknown";var isSelfSigned=c.is_self_signed||c.self_signed;if(isSelfSigned){statusCls="bt-ssl-selfsigned";statusTxt="Self-Signed";statusIcon="warning";badgeCls="bt-badge-amber";}var daysLeft=null;if(c.expiry_epoch){var now=Math.floor(Date.now()/1000);daysLeft=Math.floor((c.expiry_epoch-now)/86400);if(daysLeft<0){statusCls="bt-ssl-expired";statusTxt="Expired";statusIcon="expired";badgeCls="bt-badge-red";}else if(daysLeft<=7&&!isSelfSigned){statusCls="bt-ssl-expiring";statusTxt="Expiring Soon";statusIcon="warning";badgeCls="bt-badge-amber";}}if(!c.has_cert){statusCls="bt-ssl-none";statusTxt="No SSL";statusIcon="none";badgeCls="bt-badge-red";}var iconSvg="";if(statusIcon==="check") iconSvg='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';else if(statusIcon==="warning") iconSvg='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';else iconSvg='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';var rowIconCls=statusCls==="bt-ssl-valid"?"ssl-valid":statusCls==="bt-ssl-selfsigned"?"ssl-selfsigned":statusCls==="bt-ssl-expired"||statusCls==="bt-ssl-none"?"ssl-expired":"ssl-expiring";html+='<div class="bt-row bt-ssl-row" data-domain="'+esc(c.domain)+'"><div class="bt-row-icon '+rowIconCls+'">'+iconSvg+'</div><div class="bt-row-info" style="flex-wrap:wrap;gap:4px 8px"><span class="bt-row-name">'+esc(c.domain)+'</span><span class="bt-row-badge '+badgeCls+'">'+statusTxt+'</span></div><div class="bt-ssl-meta">';if(c.has_cert&&!isSelfSigned){html+='<span class="bt-ssl-issuer" title="Issuer: '+esc(issuer)+'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> '+esc(issuer)+'</span>';if(daysLeft!==null){var daysCls=daysLeft<=7?"bt-ssl-days-danger":daysLeft<=30?"bt-ssl-days-warn":"bt-ssl-days-ok";html+='<span class="bt-ssl-expiry '+daysCls+'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> '+daysLeft+'d left</span>';}}html+='</div><div class="bt-row-actions">';if(isSelfSigned||!c.has_cert||statusCls==="bt-ssl-expired"){html+='<button type="button" class="bt-row-btn bt-ssl-generate" data-domain="'+esc(c.domain)+'" style="color:#059669;border-color:#059669"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><span>Generate SSL</span></button>';}html+='</div></div>';});list.innerHTML=html;list.querySelectorAll(".bt-ssl-generate").forEach(function(b){b.addEventListener("click",function(){startAutoSSL($("btSslRunAutossl"));});});});}
function startAutoSSL(btn){if(!btn)return;var origHtml=btn.innerHTML;btn.disabled=true;btn.innerHTML='<div class="bt-spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;vertical-align:middle"></div> Running AutoSSL...';post({action:"start_autossl"},function(r){if(!r.success){btn.disabled=false;btn.innerHTML=origHtml;alert(r.message||"Failed to start AutoSSL");return;}pollAutoSSL(btn,origHtml);});}
function pollAutoSSL(btn,origHtml){var pollCount=0;var maxPolls=60;function doPoll(){pollCount++;if(pollCount>maxPolls){btn.disabled=false;btn.innerHTML=origHtml;loadSSLStatus();return;}post({action:"autossl_progress"},function(r){if(r.in_progress){btn.innerHTML='<div class="bt-spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;vertical-align:middle"></div> AutoSSL in progress... ('+pollCount+'/'+maxPolls+')';setTimeout(doPoll,5000);}else{btn.disabled=false;btn.innerHTML=origHtml;var pane=$("bt-pane-ssl");if(pane) pane.dataset.loaded="";loadSSLStatus();}});}setTimeout(doPoll,5000);}

/* ─── WordPress Pane ─── */
function buildWpPaneInto(pane){if(!pane) return;pane.innerHTML='<div style="padding:20px 0"><div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle">'+wpSvg16.replace(/width="16"/g,'width="18"').replace(/height="16"/g,'height="18"')+'</div><div><h5>WordPress Manager</h5><p class="bt-wp-count">Loading...</p></div></div></div><div class="bt-list" id="bt-wp-list"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading WordPress installations...</span></div></div></div></div>';}
function loadWpInstances(){var list=$("bt-wp-list");if(!list) return;list.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading WordPress installations...</span></div>';wpPost({action:"get_wp_instances"},function(r){if(!r.success){list.innerHTML='<div class="bt-empty"><span>'+(r.message||"Failed to load")+'</span></div>';return;}wpInstances=r.instances||[];var countEl=document.querySelector(".bt-wp-count");if(countEl) countEl.textContent=wpInstances.length+" site"+(wpInstances.length!==1?"s":"");if(!wpInstances.length){list.innerHTML='<div class="bt-empty">'+wpSvg32+'<span>No WordPress installations found</span></div>';return;}var html="";wpInstances.forEach(function(inst){var statusCls=inst.alive?"active":"inactive";var statusTxt=inst.alive?"Active":"Inactive";var meta='<span>WP '+esc(inst.version)+'</span>';if(inst.pluginUpdates>0) meta+='<span style="color:#0a5ed3">'+inst.pluginUpdates+' plugin update'+(inst.pluginUpdates>1?"s":"")+'</span>';if(inst.themeUpdates>0) meta+='<span style="color:#7c3aed">'+inst.themeUpdates+' theme update'+(inst.themeUpdates>1?"s":"")+'</span>';if(inst.availableUpdate) meta+='<span style="color:#d97706">Core update: '+esc(inst.availableUpdate)+'</span>';html+='<div class="bwp-site" data-id="'+inst.id+'"><div class="bwp-site-icon">'+wpSvg20+'</div><div class="bwp-site-info"><p class="bwp-site-domain">'+esc(inst.displayTitle||inst.domain)+'</p><div class="bwp-site-meta"><span class="bwp-status-badge '+statusCls+'"><span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block"></span> '+statusTxt+'</span>'+meta+'</div></div><div class="bwp-site-actions"><button type="button" class="bt-row-btn login" data-wpid="'+inst.id+'"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg><span>Login</span></button><a href="'+esc(inst.site_url)+'" target="_blank" class="bt-row-btn visit"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg><span>Visit</span></a><button type="button" class="bt-row-btn" data-wpdetail="'+inst.id+'" style="color:#0a5ed3"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span>Manage</span></button></div></div>';});list.innerHTML=html;list.querySelectorAll(".bt-row-btn.login[data-wpid]").forEach(function(b){b.addEventListener("click",function(){bwpAutoLogin(parseInt(this.getAttribute("data-wpid")));});});list.querySelectorAll("[data-wpdetail]").forEach(function(b){b.addEventListener("click",function(){bwpOpenDetail(parseInt(this.getAttribute("data-wpdetail")));});});});}
function bwpAutoLogin(id){var btn=document.querySelector(".bt-row-btn.login[data-wpid='"+id+"']");if(btn) btnLoad(btn,"Logging in...");wpPost({action:"wp_autologin",instance_id:id},function(r){if(btn) btnDone(btn);if(r.success&&r.login_url) window.open(r.login_url,"_blank");else alert(r.message||"Could not generate login link");});}
window.bwpDoLogin=bwpAutoLogin;

function bwpOpenDetail(id){currentWpInstance=null;for(var i=0;i<wpInstances.length;i++){if(wpInstances[i].id===id){currentWpInstance=wpInstances[i];break;}}if(!currentWpInstance) return;var ov=$("bwpDetailOverlay");ov.style.display="flex";$("bwpDetailTitle").textContent=currentWpInstance.displayTitle||currentWpInstance.domain;ov.querySelectorAll(".bwp-tab").forEach(function(t,i){t.classList.toggle("active",i===0);});ov.querySelectorAll(".bwp-tab-content").forEach(function(c,i){c.classList.toggle("active",i===0);});var ovTab=$("bwpTabOverview");var siteUrl=currentWpInstance.site_url||"";var html='<div class="bwp-overview-hero"><div class="bwp-preview-col"><div class="bwp-preview-wrap"><div class="bwp-preview-bar"><div class="bwp-preview-dots"><span></span><span></span><span></span></div><div class="bwp-preview-url">'+esc(siteUrl)+'</div></div><div class="bwp-preview-frame-wrap"><iframe src="'+esc(siteUrl)+'" style="width:200%;height:200%;transform:scale(.5);transform-origin:0 0;border:none;pointer-events:none" loading="lazy" sandbox="allow-same-origin"></iframe></div></div><div class="bwp-quick-actions"><button type="button" class="bt-btn-add" onclick="bwpDoLogin('+id+')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg> WP Admin</button><a href="'+esc(siteUrl)+'" target="_blank" class="bt-btn-outline"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg> Visit Site</a></div></div>';html+='<div class="bwp-overview-right"><div class="bwp-site-header"><div class="bwp-site-header-icon">'+wpSvg20+'</div><div class="bwp-site-header-info"><h4>'+esc(currentWpInstance.displayTitle||currentWpInstance.domain)+'</h4><p><span>WP '+esc(currentWpInstance.version)+'</span><span>'+esc(currentWpInstance.path)+'</span></p></div></div><div class="bwp-overview-grid"><div class="bwp-stat"><div class="bwp-stat-label">Status</div><div class="bwp-stat-value">'+(currentWpInstance.alive?'<span style="color:#059669">Active</span>':'<span style="color:#ef4444">Inactive</span>')+'</div></div><div class="bwp-stat"><div class="bwp-stat-label">SSL</div><div class="bwp-stat-value">'+(currentWpInstance.ssl?'<span style="color:#059669">Enabled</span>':'<span style="color:#d97706">Disabled</span>')+'</div></div><div class="bwp-stat"><div class="bwp-stat-label">Plugin Updates</div><div class="bwp-stat-value">'+(currentWpInstance.pluginUpdates>0?'<span style="color:#0a5ed3">'+currentWpInstance.pluginUpdates+' available</span>':'<span style="color:#059669">Up to date</span>')+'</div></div><div class="bwp-stat"><div class="bwp-stat-label">Theme Updates</div><div class="bwp-stat-value">'+(currentWpInstance.themeUpdates>0?'<span style="color:#7c3aed">'+currentWpInstance.themeUpdates+' available</span>':'<span style="color:#059669">Up to date</span>')+'</div></div></div>';if(currentWpInstance.availableUpdate) html+='<div class="bwp-msg info">Core update available: WordPress '+esc(currentWpInstance.availableUpdate)+'</div>';html+='</div></div>';ovTab.innerHTML=html;["bwpTabPlugins","bwpTabThemes","bwpTabSecurity"].forEach(function(tid){var t=$(tid);if(t){t.removeAttribute("data-loaded");t.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading...</span></div>';}});bwpLoadPlugins();}
function bindWpDetailPanel(){var overlay=$("bwpDetailOverlay");if(!overlay) return;overlay.querySelectorAll(".bwp-tab").forEach(function(tab){tab.addEventListener("click",function(){overlay.querySelectorAll(".bwp-tab").forEach(function(t){t.classList.remove("active");});overlay.querySelectorAll(".bwp-tab-content").forEach(function(c){c.classList.remove("active");});tab.classList.add("active");var tabName=tab.getAttribute("data-tab");var target=$("bwpTab"+tabName.charAt(0).toUpperCase()+tabName.slice(1));if(target){target.classList.add("active");if(!target.getAttribute("data-loaded")){if(tabName==="plugins") bwpLoadPlugins();else if(tabName==="themes") bwpLoadThemes();else if(tabName==="security") bwpLoadSecurity();}}});});$("bwpDetailClose").addEventListener("click",function(){overlay.style.display="none";});overlay.addEventListener("click",function(e){if(e.target===overlay) overlay.style.display="none";});}
function bwpLoadPlugins(){if(!currentWpInstance) return;var el=$("bwpTabPlugins");if(!el) return;if(!el.getAttribute("data-loaded")){el.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading plugins...</span></div>';}wpPost({action:"wp_list_plugins",instance_id:currentWpInstance.id},function(r){if(!r.success){el.innerHTML='<div class="bt-empty"><span>'+(r.message||"Failed")+'</span></div>';return;}var plugins=r.plugins||[];var html='<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:0 2px"><span style="font-size:12px;color:var(--text-muted,#6b7280)">'+plugins.length+' plugin'+(plugins.length!==1?"s":"")+'</span><button type="button" class="bt-btn-outline" id="bwpRefreshPlugins" style="padding:5px 12px;font-size:11px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Refresh</button></div>';if(!plugins.length){html+='<div class="bt-empty"><span>No plugins found</span></div>';el.innerHTML=html;return;}plugins.forEach(function(p){var active=p.active||p.status==="active";var hasUpdate=!!p.availableVersion;html+='<div class="bwp-item-row" id="bwp-plugin-'+esc(p.slug)+'"><div class="bwp-item-icon plugin">'+esc((p.title||p.name||p.slug||"P").charAt(0).toUpperCase())+'</div><div class="bwp-item-info"><p class="bwp-item-name">'+esc(p.title||p.name||p.slug)+'</p><p class="bwp-item-detail">v'+esc(p.version||"?")+(hasUpdate?' \u2192 '+esc(p.availableVersion):'')+'</p></div><div class="bwp-item-actions"><button type="button" class="bwp-item-btn '+(active?"active-state":"inactive-state")+'" data-action="toggle" data-slug="'+esc(p.slug)+'" data-activate="'+(!active)+'">'+(active?"Deactivate":"Activate")+'</button>';if(hasUpdate) html+='<button type="button" class="bwp-item-btn update" data-action="update" data-slug="'+esc(p.slug)+'">Update</button>';html+='</div></div>';});el.innerHTML=html;el.setAttribute("data-loaded","1");var refreshBtn=$("bwpRefreshPlugins");if(refreshBtn) refreshBtn.addEventListener("click",function(){btnLoad(this,"Refreshing...");var self=this;bwpLoadPlugins();setTimeout(function(){btnDone(self);},500);});el.querySelectorAll("[data-action='toggle']").forEach(function(btn){btn.addEventListener("click",function(){var slug=this.getAttribute("data-slug");var activate=this.getAttribute("data-activate")==="true";btnLoad(this,activate?"Activating...":"Deactivating...");var self=this;wpPost({action:"wp_toggle_plugin",instance_id:currentWpInstance.id,slug:slug,activate:activate?"1":"0"},function(r){if(r.success){bwpLoadPlugins();}else{btnDone(self);alert(r.message||"Failed");}});});});el.querySelectorAll("[data-action='update']").forEach(function(btn){btn.addEventListener("click",function(){var slug=this.getAttribute("data-slug");btnLoad(this,"Updating...");var self=this;wpPost({action:"wp_update",instance_id:currentWpInstance.id,type:"plugins",slug:slug},function(r){if(r.success){self.disabled=false;self.innerHTML='Updated';self.style.color="#059669";setTimeout(function(){bwpLoadPlugins();},1500);}else{btnDone(self);alert(r.message||"Failed");}});});});});}

function bwpLoadThemes(){if(!currentWpInstance) return;wpPost({action:"wp_list_themes",instance_id:currentWpInstance.id},function(r){var el=$("bwpTabThemes");if(!r.success){el.innerHTML='<div class="bt-empty"><span>'+(r.message||"Failed")+'</span></div>';return;}var themes=r.themes||[];if(!themes.length){el.innerHTML='<div class="bt-empty"><span>No themes found</span></div>';return;}var html='<div class="bwp-theme-grid">';themes.forEach(function(t){var active=t.active||t.status==="active";var hasUpdate=!!t.availableVersion;var screenshot=t.screenshot||t.screenshotUrl||"";html+='<div class="bwp-theme-card'+(active?" bwp-theme-active":"")+'"><div class="bwp-theme-screenshot">'+(screenshot?'<img src="'+esc(screenshot)+'" alt="'+esc(t.title||t.name||t.slug)+'" loading="lazy">':'')+(active?'<div class="bwp-theme-active-badge">Active</div>':'')+'</div><div class="bwp-theme-info"><p class="bwp-theme-name">'+esc(t.title||t.name||t.slug)+'</p><p class="bwp-theme-ver">v'+esc(t.version||"?")+(hasUpdate?' → '+esc(t.availableVersion):'')+'</p><div class="bwp-theme-actions">';if(!active) html+='<button type="button" class="bwp-item-btn" onclick="bwpActivateTheme(\''+esc(t.slug)+'\',this)">Activate</button>';if(hasUpdate) html+='<button type="button" class="bwp-item-btn update" onclick="bwpUpdateTheme(\''+esc(t.slug)+'\',this)">Update</button>';html+='</div></div></div>';});html+='</div>';el.innerHTML=html;el.setAttribute("data-loaded","1");});}
window.bwpActivateTheme=function(slug,btn){if(!currentWpInstance) return;btnLoad(btn,"Activating...");wpPost({action:"wp_toggle_theme",instance_id:currentWpInstance.id,slug:slug},function(r){if(r.success) bwpLoadThemes();else{btnDone(btn);alert(r.message||"Failed");}});};
window.bwpUpdateTheme=function(slug,btn){if(!currentWpInstance) return;btnLoad(btn,"Updating...");wpPost({action:"wp_update",instance_id:currentWpInstance.id,type:"themes",slug:slug},function(r){if(r.success){btn.disabled=false;btn.innerHTML="Updated";btn.style.color="#059669";setTimeout(function(){bwpLoadThemes();},1500);}else{btnDone(btn);alert(r.message||"Failed");}});};
function bwpLoadSecurity(){if(!currentWpInstance) return;wpPost({action:"wp_security_scan",instance_id:currentWpInstance.id},function(r){var el=$("bwpTabSecurity");if(!r.success){el.innerHTML='<div class="bt-empty"><span>'+(r.message||"Security scan failed")+'</span></div>';el.setAttribute("data-loaded","1");return;}var measures=r.security||[];if(!measures.length){el.innerHTML='<div class="bt-empty"><span>No security data available</span></div>';el.setAttribute("data-loaded","1");return;}var applied=0;measures.forEach(function(m){if(m.status==="applied"||m.status==="true"||m.status===true) applied++;});var pct=Math.round(applied/measures.length*100);var html='<div class="bwp-sec-summary"><div class="bwp-sec-summary-bar"><div class="bwp-sec-summary-fill" style="width:'+pct+'%"></div></div><div class="bwp-sec-summary-text"><span><strong>'+applied+'</strong> of <strong>'+measures.length+'</strong> measures applied</span><span><strong>'+pct+'%</strong> secure</span></div></div>';measures.forEach(function(m){var ok=m.status==="applied"||m.status==="true"||m.status===true;var mid=esc(m.id);html+='<div class="bwp-security-item" data-measure="'+mid+'"><div class="bwp-sec-icon '+(ok?"ok":"warning")+'"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'+(ok?'<polyline points="20 6 9 17 4 12"/>':'<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>')+'</svg></div><div class="bwp-sec-info"><p class="bwp-sec-label">'+esc(m.title||m.id)+'</p><p class="bwp-sec-detail">'+mid+'</p></div><div class="bwp-sec-actions" style="display:flex;gap:6px;flex-shrink:0">'+(ok?'<button type="button" class="bwp-item-btn inactive-state" onclick="bwpRevertSecurity(\''+mid+'\',this)">Revert</button>':'<button type="button" class="bwp-item-btn active-state" onclick="bwpApplySecurity(\''+mid+'\',this)">Apply</button>')+'</div></div>';});el.innerHTML=html;el.setAttribute("data-loaded","1");});}
window.bwpApplySecurity=function(measureId,btn){if(!currentWpInstance) return;btnLoad(btn,"Applying...");wpPost({action:"wp_security_apply",instance_id:currentWpInstance.id,measure_id:measureId},function(r){btnDone(btn);if(r.success){$("bwpTabSecurity").removeAttribute("data-loaded");bwpLoadSecurity();}else{alert(r.message||"Failed");}});};
window.bwpRevertSecurity=function(measureId,btn){if(!currentWpInstance) return;btnLoad(btn,"Reverting...");wpPost({action:"wp_security_revert",instance_id:currentWpInstance.id,measure_id:measureId},function(r){btnDone(btn);if(r.success){$("bwpTabSecurity").removeAttribute("data-loaded");bwpLoadSecurity();}else{alert(r.message||"Failed");}});};

/* ─── Email Actions ─── */
function bindEmailActions(pane){pane.querySelectorAll(".bt-row-btn.login[data-email]").forEach(function(b){b.addEventListener("click",function(){var email=this.getAttribute("data-email");var btn=this;btnLoad(btn,"Opening...");post({action:"webmail_login",email:email},function(r){btnDone(btn);if(r.success&&r.url) window.open(r.url,"_blank");else alert(r.message||"Failed");});});});pane.querySelectorAll(".bt-row-btn.pass[data-email]").forEach(function(b){b.addEventListener("click",function(){$("bemPassEmail").value=this.getAttribute("data-email");$("bemPassNew").value="";$("bemPassMsg").style.display="none";$("bemPassModal").style.display="flex";});});pane.querySelectorAll(".bt-row-btn.del[data-email]").forEach(function(b){b.addEventListener("click",function(){$("bemDelEmail").textContent=this.getAttribute("data-email");$("bemDelMsg").style.display="none";$("bemDelModal").style.display="flex";});});}
function openCreateEmailModal(){$("bemNewUser").value="";$("bemNewPass").value="";$("bemNewQuota").value="250";$("bemCreateMsg").style.display="none";var sel=$("bemNewDomain");sel.innerHTML="<option>Loading...</option>";$("bemCreateModal").style.display="flex";post({action:"get_domains"},function(r){sel.innerHTML="";var doms=r.domains||[];if(!doms.length&&C.domains&&C.domains.main) doms=[C.domains.main];doms.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;sel.appendChild(o);});});}
function bindEmailModalSubmits(){$("bemCreateSubmit").addEventListener("click",function(){var user=$("bemNewUser").value.trim();var pass=$("bemNewPass").value;var domain=$("bemNewDomain").value;var quota=$("bemNewQuota").value;var msg=$("bemCreateMsg");msg.style.display="none";if(!user||!pass||!domain){showMsg(msg,"Please fill in all fields",false);return;}var btn=this;btnLoad(btn,"Creating...");post({action:"create_email",email_user:user,email_pass:pass,domain:domain,quota:quota},function(r){btnDone(btn);showMsg(msg,r.message||"Done",r.success);if(r.success){C.emails=C.emails||[];C.emails.push(r.email);setTimeout(function(){$("bemCreateModal").style.display="none";buildEmailPane();},800);}});});$("bemPassSubmit").addEventListener("click",function(){var email=$("bemPassEmail").value;var pass=$("bemPassNew").value;var msg=$("bemPassMsg");msg.style.display="none";if(!pass){showMsg(msg,"Please enter a new password",false);return;}var btn=this;btnLoad(btn,"Updating...");post({action:"change_password",email:email,new_pass:pass},function(r){btnDone(btn);showMsg(msg,r.message||"Done",r.success);if(r.success) setTimeout(function(){$("bemPassModal").style.display="none";},800);});});$("bemDelSubmit").addEventListener("click",function(){var email=$("bemDelEmail").textContent;var msg=$("bemDelMsg");msg.style.display="none";var btn=this;btnLoad(btn,"Deleting...");post({action:"delete_email",email:email},function(r){btnDone(btn);showMsg(msg,r.message||"Done",r.success);if(r.success){C.emails=(C.emails||[]).filter(function(e){return e!==email;});setTimeout(function(){$("bemDelModal").style.display="none";buildEmailPane();},800);}});});}
/* ─── Domain Actions ─── */
function bindDomainActions(pane){pane.querySelectorAll(".bt-row-btn.del[data-domain]").forEach(function(b){b.addEventListener("click",function(){openDelDomainModal(this.getAttribute("data-domain"),this.getAttribute("data-type"));});});}
function openAddonModal(){$("bdmAddonDomain").value="";$("bdmAddonDocroot").value="";$("bdmAddonMsg").style.display="none";$("bdmAddonModal").style.display="flex";}
function openSubModal(){$("bdmSubName").value="";$("bdmSubDocroot").value="";$("bdmSubMsg").style.display="none";var sel=$("bdmSubParent");sel.innerHTML="<option>Loading...</option>";$("bdmSubModal").style.display="flex";post({action:"get_parent_domains"},function(r){sel.innerHTML="";var doms=r.domains||[];doms.forEach(function(d){var o=document.createElement("option");o.value=d;o.textContent=d;sel.appendChild(o);});});}
function bindDomainModalSubmits(){$("bdmAddonSubmit").addEventListener("click",function(){var domain=$("bdmAddonDomain").value.trim();var docroot=$("bdmAddonDocroot").value.trim();var msg=$("bdmAddonMsg");msg.style.display="none";if(!domain){showMsg(msg,"Please enter a domain name",false);return;}var btn=this;btnLoad(btn,"Adding...");post({action:"add_addon_domain",domain:domain,docroot:docroot},function(r){btnDone(btn);showMsg(msg,r.message||"Done",r.success);if(r.success){if(C.domains) C.domains.addon=(C.domains.addon||[]).concat([domain]);setTimeout(function(){$("bdmAddonModal").style.display="none";buildDomainsPane();},800);}});});$("bdmSubSubmit").addEventListener("click",function(){var sub=$("bdmSubName").value.trim();var parent=$("bdmSubParent").value;var docroot=$("bdmSubDocroot").value.trim();var msg=$("bdmSubMsg");msg.style.display="none";if(!sub||!parent){showMsg(msg,"Please fill in all fields",false);return;}var btn=this;btnLoad(btn,"Adding...");post({action:"add_subdomain",subdomain:sub,domain:parent,docroot:docroot},function(r){btnDone(btn);showMsg(msg,r.message||"Done",r.success);if(r.success){if(C.domains) C.domains.sub=(C.domains.sub||[]).concat([r.domain||sub+"."+parent]);setTimeout(function(){$("bdmSubModal").style.display="none";buildDomainsPane();},800);}});});}
function openDelDomainModal(domain,type){$("bdmDelDomain").textContent=domain;$("bdmDelMsg").style.display="none";$("bdmDelModal").style.display="flex";$("bdmDelSubmit").onclick=function(){var msg=$("bdmDelMsg");msg.style.display="none";var btn=this;btnLoad(btn,"Deleting...");post({action:"delete_domain",domain:domain,type:type},function(r){btnDone(btn);showMsg(msg,r.message||"Done",r.success);if(r.success){if(C.domains){if(type==="addon") C.domains.addon=(C.domains.addon||[]).filter(function(d){return d!==domain;});if(type==="sub") C.domains.sub=(C.domains.sub||[]).filter(function(d){return d!==domain;});if(type==="parked") C.domains.parked=(C.domains.parked||[]).filter(function(d){return d!==domain;});}setTimeout(function(){$("bdmDelModal").style.display="none";buildDomainsPane();},800);}});};}

/* ─── DNS Manager Pane ─── */
var dnsCurrentDomain="";var dnsZoneDomain="";var dnsRecords=[];var dnsSelectedLines={};var dnsActiveFilter="ALL";
function buildDnsPane(){var pane=$("bt-pane-dns");if(!pane) return;pane.innerHTML='<div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle" style="background:#7c3aed"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div><div><h5>DNS Manager</h5><p id="bt-dns-subtitle">Select a domain to manage DNS records</p></div></div></div><div id="bt-dns-body"><div id="bt-dns-domain-list" class="bt-list"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading domains...</span></div></div><div id="bt-dns-records-view" style="display:none"><div class="bt-dns-toolbar" id="bt-dns-toolbar"></div><div class="bt-dns-filter-bar" id="bt-dns-filter-bar"></div><div class="bt-list" id="bt-dns-records-list"></div></div></div></div>';}
function loadDnsDomains(){var list=$("bt-dns-domain-list");if(!list) return;list.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading domains...</span></div>';post({action:"dns_list_domains"},function(r){if(!r.success||!r.domains||!r.domains.length){list.innerHTML='<div class="bt-empty"><span>No domains found</span></div>';return;}var html="";r.domains.forEach(function(d){html+='<div class="bt-row bt-dns-domain-row" data-domain="'+esc(d.domain)+'" style="cursor:pointer"><div class="bt-row-icon '+(d.type||"main")+'"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/></svg></div><div class="bt-row-info"><span class="bt-row-name">'+esc(d.domain)+'</span><span class="bt-row-badge bt-badge-primary">'+esc(d.type)+'</span></div><div class="bt-row-actions"><span style="color:var(--text-muted,#9ca3af)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span></div></div>';});list.innerHTML=html;list.querySelectorAll(".bt-dns-domain-row").forEach(function(row){row.addEventListener("click",function(){var domain=this.getAttribute("data-domain");dnsCurrentDomain=domain;$("bt-dns-domain-list").style.display="none";$("bt-dns-records-view").style.display="block";$("bt-dns-subtitle").textContent=domain;loadDnsRecords(domain);});});});}
function loadDnsRecords(domain){var list=$("bt-dns-records-list");if(!list) return;list.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading DNS records...</span></div>';dnsSelectedLines={};var zoneDomain=domain;if(C.domains&&C.domains.main){var main=C.domains.main.toLowerCase();var dl=domain.toLowerCase();if(dl!==main&&dl.indexOf("."+main)!==-1) zoneDomain=C.domains.main;}post({action:"dns_fetch_records",domain:zoneDomain},function(r){if(!r.success){if(zoneDomain!==domain){post({action:"dns_fetch_records",domain:domain},function(r2){if(!r2.success){list.innerHTML='<div class="bt-empty"><span>'+(r2.message||"Failed to load records")+'</span></div>';return;}dnsRecords=r2.records||[];dnsZoneDomain=domain;renderDnsToolbar();renderDnsFilterBar();renderDnsRecords();});return;}list.innerHTML='<div class="bt-empty"><span>'+(r.message||"Failed to load records")+'</span></div>';return;}var allRecords=r.records||[];dnsZoneDomain=zoneDomain;var selectedDomain=domain.toLowerCase().replace(/\.$/,"");dnsRecords=allRecords.filter(function(rec){var name=(rec.name||"").toLowerCase().replace(/\.$/,"");if(name===selectedDomain) return true;if(name.indexOf("."+selectedDomain)!==-1&&name.indexOf("."+selectedDomain)===name.length-selectedDomain.length-1) return true;return false;});if(!dnsRecords.length&&allRecords.length&&domain.toLowerCase()===zoneDomain.toLowerCase()) dnsRecords=allRecords;renderDnsToolbar();renderDnsFilterBar();renderDnsRecords();var sub=$("bt-dns-subtitle");if(sub) sub.textContent=domain+" · "+dnsRecords.length+" record"+(dnsRecords.length!==1?"s":"");});}

function renderDnsToolbar(){var tb=$("bt-dns-toolbar");if(!tb) return;tb.innerHTML='<div style="display:flex;align-items:center;gap:8px;padding:12px 14px;flex-wrap:wrap"><button type="button" class="bt-btn-outline bt-dns-back-btn" style="padding:6px 12px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg> Domains</button><div style="flex:1"></div><button type="button" class="bt-btn-outline bt-dns-bulk-del-btn" style="padding:6px 12px;display:none;color:#ef4444;border-color:#ef4444"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Delete Selected (<span class="bt-dns-sel-count">0</span>)</button><button type="button" class="bt-btn-add bt-dns-add-btn" style="padding:7px 14px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Record</button></div>';tb.querySelector(".bt-dns-back-btn").addEventListener("click",function(){$("bt-dns-records-view").style.display="none";$("bt-dns-domain-list").style.display="block";$("bt-dns-subtitle").textContent="Select a domain to manage DNS records";dnsCurrentDomain="";});tb.querySelector(".bt-dns-add-btn").addEventListener("click",function(){openDnsAddModal();});tb.querySelector(".bt-dns-bulk-del-btn").addEventListener("click",function(){dnsHandleBulkDelete();});}
function renderDnsFilterBar(){var fb=$("bt-dns-filter-bar");if(!fb) return;var types=["ALL"];var typeCounts={ALL:0};dnsRecords.forEach(function(r){typeCounts.ALL++;if(!typeCounts[r.type]) typeCounts[r.type]=0;typeCounts[r.type]++;if(types.indexOf(r.type)===-1) types.push(r.type);});var order=["ALL","SOA","NS","A","AAAA","CNAME","MX","TXT","SRV","CAA"];types.sort(function(a,b){var ia=order.indexOf(a),ib=order.indexOf(b);if(ia===-1) ia=99;if(ib===-1) ib=99;return ia-ib;});var html='<div style="display:flex;gap:4px;padding:8px 14px;overflow-x:auto;flex-wrap:wrap">';types.forEach(function(t){var active=t===dnsActiveFilter?" active":"";html+='<button type="button" class="bt-dns-filter-btn'+active+'" data-filter="'+t+'" style="padding:4px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:'+(active?"#0a5ed3":"var(--card-bg,#fff)")+';color:'+(active?"#fff":"var(--heading-color,#374151)")+';transition:all .15s;white-space:nowrap">'+t+' <span style="opacity:.6;font-size:11px">('+typeCounts[t]+')</span></button>';});html+='</div>';fb.innerHTML=html;fb.querySelectorAll(".bt-dns-filter-btn").forEach(function(btn){btn.addEventListener("click",function(){dnsActiveFilter=this.getAttribute("data-filter");renderDnsFilterBar();renderDnsRecords();});});}
function dnsRecordValue(r){switch(r.type){case "A":case "AAAA":return r.address||"";case "CNAME":return r.cname||"";case "MX":return (r.preference||0)+" "+(r.exchange||"");case "TXT":return r.txtdata||"";case "NS":return r.nsdname||"";case "SRV":return (r.priority||0)+" "+(r.weight||0)+" "+(r.port||0)+" "+(r.target||"");case "CAA":return (r.flag||0)+" "+(r.tag||"")+" "+(r.value||"");case "SOA":return (r.mname||"")+" "+(r.rname||"");default:return "";}}
function dnsTypeColor(type){var colors={A:"#0a5ed3",AAAA:"#7c3aed",CNAME:"#059669",MX:"#d97706",TXT:"#6366f1",NS:"#0891b2",SRV:"#be185d",CAA:"#dc2626",SOA:"#6b7280"};return colors[type]||"#6b7280";}
function renderDnsRecords(){var list=$("bt-dns-records-list");if(!list) return;var filtered=dnsActiveFilter==="ALL"?dnsRecords:dnsRecords.filter(function(r){return r.type===dnsActiveFilter;});if(!filtered.length){list.innerHTML='<div class="bt-empty"><span>No '+dnsActiveFilter+' records found</span></div>';return;}var html="";filtered.forEach(function(r){var val=dnsRecordValue(r);var isEditable=["A","AAAA","CNAME","MX","TXT","SRV","CAA"].indexOf(r.type)!==-1;var isDeletable=isEditable;var checked=dnsSelectedLines[r.line]?" checked":"";var color=dnsTypeColor(r.type);html+='<div class="bt-row bt-dns-record-row" data-line="'+r.line+'">'+(isDeletable?'<label style="display:flex;align-items:center;cursor:pointer;flex-shrink:0"><input type="checkbox" class="bt-dns-check" data-line="'+r.line+'"'+checked+' style="width:16px;height:16px;accent-color:#0a5ed3;cursor:pointer"></label>':'<div style="width:16px"></div>')+'<div style="min-width:56px;flex-shrink:0"><span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:'+color+'15;color:'+color+';letter-spacing:.3px">'+esc(r.type)+'</span></div><div class="bt-row-info" style="flex-direction:column;align-items:flex-start;gap:2px;min-width:0"><span class="bt-row-name mono" style="font-size:13px">'+esc(r.name)+'</span><span style="font-size:12px;color:var(--text-muted,#6b7280);word-break:break-all;max-width:100%;overflow:hidden;text-overflow:ellipsis">'+esc(val)+'</span></div><div style="font-size:11px;color:var(--text-muted,#9ca3af);white-space:nowrap;flex-shrink:0">TTL: '+r.ttl+'</div><div class="bt-row-actions">'+(isEditable?'<button type="button" class="bt-row-btn pass bt-dns-edit-btn" data-line="'+r.line+'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg><span>Edit</span></button>':'')+(isDeletable?'<button type="button" class="bt-row-btn del bt-dns-del-btn" data-line="'+r.line+'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button>':'')+'</div></div>';});list.innerHTML=html;list.querySelectorAll(".bt-dns-check").forEach(function(cb){cb.addEventListener("change",function(){var line=parseInt(this.getAttribute("data-line"));if(this.checked) dnsSelectedLines[line]=true;else delete dnsSelectedLines[line];updateDnsBulkBtn();});});list.querySelectorAll(".bt-dns-edit-btn").forEach(function(btn){btn.addEventListener("click",function(){var line=parseInt(this.getAttribute("data-line"));var rec=dnsRecords.find(function(r){return r.line===line;});if(rec) openDnsEditModal(rec);});});list.querySelectorAll(".bt-dns-del-btn").forEach(function(btn){btn.addEventListener("click",function(){var line=parseInt(this.getAttribute("data-line"));var rec=dnsRecords.find(function(r){return r.line===line;});if(rec) openDnsDeleteConfirm(rec);});});}
function updateDnsBulkBtn(){var count=Object.keys(dnsSelectedLines).length;var btn=document.querySelector(".bt-dns-bulk-del-btn");if(!btn) return;btn.style.display=count>0?"inline-flex":"none";var span=btn.querySelector(".bt-dns-sel-count");if(span) span.textContent=count;}
function dnsHandleBulkDelete(){var lines=Object.keys(dnsSelectedLines).map(Number);if(!lines.length) return;if(!confirm("Delete "+lines.length+" selected DNS record(s)?")) return;var btn=document.querySelector(".bt-dns-bulk-del-btn");if(btn) btnLoad(btn,"Deleting...");post({action:"dns_bulk_delete",domain:dnsZoneDomain||dnsCurrentDomain,lines:lines.join(",")},function(r){if(btn) btnDone(btn);if(r.success||r.deleted>0){dnsSelectedLines={};loadDnsRecords(dnsCurrentDomain);}else{alert(r.message||"Failed to delete records");}});}
function openDnsDeleteConfirm(rec){if(!confirm("Delete this "+rec.type+" record for "+rec.name+"?")) return;post({action:"dns_delete_record",domain:dnsZoneDomain||dnsCurrentDomain,line:rec.line},function(r){if(r.success) loadDnsRecords(dnsCurrentDomain);else alert(r.message||"Failed to delete record");});}

/* ─── DNS Add/Edit Modals ─── */
function openDnsAddModal(){
    var overlay=document.createElement("div");
    overlay.className="bt-overlay";overlay.id="btDnsAddOverlay";
    overlay.innerHTML='<div class="bt-modal" style="max-width:520px"><div class="bt-modal-head"><h5>Add DNS Record</h5><button type="button" class="bt-modal-close" data-dns-close>&times;</button></div><div class="bt-modal-body">'
        +'<div class="bt-field"><label>Record Type</label><select id="btDnsAddType" class="bt-select"><option value="A">A</option><option value="AAAA">AAAA</option><option value="CNAME">CNAME</option><option value="MX">MX</option><option value="TXT">TXT</option><option value="SRV">SRV</option><option value="CAA">CAA</option></select></div>'
        +'<div class="bt-field"><label>Name</label><input type="text" id="btDnsAddName" placeholder="subdomain.'+esc(dnsCurrentDomain)+'." autocomplete="off"></div>'
        +'<div class="bt-field"><label>TTL</label><input type="number" id="btDnsAddTtl" value="14400" min="60"></div>'
        +'<div id="btDnsAddFields"></div>'
        +'<div class="bt-msg" id="btDnsAddMsg"></div>'
        +'</div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-dns-close>Cancel</button><button type="button" class="bt-btn-primary" id="btDnsAddSubmit">Add Record</button></div></div>';
    document.body.appendChild(overlay);
    overlay.style.display="flex";
    var typeSelect=$("btDnsAddType");
    function updateFields(){dnsRenderTypeFields("btDnsAddFields",typeSelect.value,{});}
    typeSelect.addEventListener("change",updateFields);
    updateFields();
    overlay.querySelectorAll("[data-dns-close]").forEach(function(b){b.addEventListener("click",function(){overlay.remove();});});
    overlay.addEventListener("click",function(e){if(e.target===overlay) overlay.remove();});
    $("btDnsAddSubmit").addEventListener("click",function(){
        var data={action:"dns_add_record",domain:dnsCurrentDomain,type:typeSelect.value,name:$("btDnsAddName").value.trim(),ttl:$("btDnsAddTtl").value};
        Object.assign(data,dnsCollectTypeFields("btDnsAddFields",typeSelect.value));
        var msg=$("btDnsAddMsg");msg.style.display="none";
        this.disabled=true;var btn=this;
        post(data,function(r){btn.disabled=false;showMsg(msg,r.message||"Done",r.success);if(r.success) setTimeout(function(){overlay.remove();loadDnsRecords(dnsCurrentDomain);},600);});
    });
}

function openDnsEditModal(rec){
    var overlay=document.createElement("div");
    overlay.className="bt-overlay";overlay.id="btDnsEditOverlay";
    overlay.innerHTML='<div class="bt-modal" style="max-width:520px"><div class="bt-modal-head"><h5>Edit '+esc(rec.type)+' Record</h5><button type="button" class="bt-modal-close" data-dns-close>&times;</button></div><div class="bt-modal-body">'
        +'<div class="bt-field"><label>Name</label><input type="text" id="btDnsEditName" value="'+esc(rec.name)+'" autocomplete="off"></div>'
        +'<div class="bt-field"><label>TTL</label><input type="number" id="btDnsEditTtl" value="'+rec.ttl+'" min="60"></div>'
        +'<div id="btDnsEditFields"></div>'
        +'<div class="bt-msg" id="btDnsEditMsg"></div>'
        +'</div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-dns-close>Cancel</button><button type="button" class="bt-btn-primary" id="btDnsEditSubmit">Save Changes</button></div></div>';
    document.body.appendChild(overlay);
    overlay.style.display="flex";
    dnsRenderTypeFields("btDnsEditFields",rec.type,rec);
    overlay.querySelectorAll("[data-dns-close]").forEach(function(b){b.addEventListener("click",function(){overlay.remove();});});
    overlay.addEventListener("click",function(e){if(e.target===overlay) overlay.remove();});
    $("btDnsEditSubmit").addEventListener("click",function(){
        var data={action:"dns_edit_record",domain:dnsCurrentDomain,line:rec.line,type:rec.type,name:$("btDnsEditName").value.trim(),ttl:$("btDnsEditTtl").value};
        Object.assign(data,dnsCollectTypeFields("btDnsEditFields",rec.type));
        var msg=$("btDnsEditMsg");msg.style.display="none";
        this.disabled=true;var btn=this;
        post(data,function(r){btn.disabled=false;showMsg(msg,r.message||"Done",r.success);if(r.success) setTimeout(function(){overlay.remove();loadDnsRecords(dnsCurrentDomain);},600);});
    });
}

function dnsRenderTypeFields(containerId,type,rec){
    var c=$(containerId);if(!c) return;
    var html="";
    switch(type){
        case "A":html='<div class="bt-field"><label>IPv4 Address</label><input type="text" class="dns-field" data-key="address" value="'+esc(rec.address||"")+'" placeholder="192.168.1.1"></div>';break;
        case "AAAA":html='<div class="bt-field"><label>IPv6 Address</label><input type="text" class="dns-field" data-key="address" value="'+esc(rec.address||"")+'" placeholder="2001:db8::1"></div>';break;
        case "CNAME":html='<div class="bt-field"><label>Target</label><input type="text" class="dns-field" data-key="cname" value="'+esc(rec.cname||"")+'" placeholder="target.example.com"></div>';break;
        case "MX":html='<div class="bt-field"><label>Priority</label><input type="number" class="dns-field" data-key="preference" value="'+(rec.preference||10)+'" min="0"></div><div class="bt-field"><label>Mail Server</label><input type="text" class="dns-field" data-key="exchange" value="'+esc(rec.exchange||"")+'" placeholder="mail.example.com"></div>';break;
        case "TXT":html='<div class="bt-field"><label>TXT Data</label><textarea class="dns-field" data-key="txtdata" rows="3" style="width:100%;padding:9px 12px;border:1px solid var(--border-color,#d1d5db);border-radius:8px;font-size:14px;color:var(--heading-color,#111827);background:var(--input-bg,#fff);resize:vertical;font-family:monospace;box-sizing:border-box">'+esc(rec.txtdata||"")+'</textarea></div>';break;
        case "SRV":html='<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px"><div class="bt-field"><label>Priority</label><input type="number" class="dns-field" data-key="priority" value="'+(rec.priority||0)+'" min="0"></div><div class="bt-field"><label>Weight</label><input type="number" class="dns-field" data-key="weight" value="'+(rec.weight||0)+'" min="0"></div><div class="bt-field"><label>Port</label><input type="number" class="dns-field" data-key="port" value="'+(rec.port||0)+'" min="0"></div></div><div class="bt-field"><label>Target</label><input type="text" class="dns-field" data-key="target" value="'+esc(rec.target||"")+'" placeholder="target.example.com"></div>';break;
        case "CAA":html='<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><div class="bt-field"><label>Flag</label><input type="number" class="dns-field" data-key="flag" value="'+(rec.flag||0)+'" min="0" max="255"></div><div class="bt-field"><label>Tag</label><select class="dns-field bt-select" data-key="tag"><option value="issue"'+(rec.tag==="issue"?" selected":"")+'>issue</option><option value="issuewild"'+(rec.tag==="issuewild"?" selected":"")+'>issuewild</option><option value="iodef"'+(rec.tag==="iodef"?" selected":"")+'>iodef</option></select></div></div><div class="bt-field"><label>Value</label><input type="text" class="dns-field" data-key="value" value="'+esc(rec.value||"")+'" placeholder="letsencrypt.org"></div>';break;
    }
    c.innerHTML=html;
}
function dnsCollectTypeFields(containerId,type){
    var c=$(containerId);if(!c) return {};
    var data={};
    c.querySelectorAll(".dns-field").forEach(function(f){var key=f.getAttribute("data-key");if(key) data[key]=f.value;});
    return data;
}

/* ─── Cron Jobs ─── */
var cronPresets=[
    {label:"Every minute",minute:"*",hour:"*",day:"*",month:"*",weekday:"*"},
    {label:"Every 5 minutes",minute:"*/5",hour:"*",day:"*",month:"*",weekday:"*"},
    {label:"Every 15 minutes",minute:"*/15",hour:"*",day:"*",month:"*",weekday:"*"},
    {label:"Every 30 minutes",minute:"*/30",hour:"*",day:"*",month:"*",weekday:"*"},
    {label:"Once per hour",minute:"0",hour:"*",day:"*",month:"*",weekday:"*"},
    {label:"Twice per day",minute:"0",hour:"0,12",day:"*",month:"*",weekday:"*"},
    {label:"Once per day",minute:"0",hour:"0",day:"*",month:"*",weekday:"*"},
    {label:"Once per week",minute:"0",hour:"0",day:"*",month:"*",weekday:"0"},
    {label:"Once per month",minute:"0",hour:"0",day:"1",month:"*",weekday:"*"}
];
function cronScheduleLabel(j){
    for(var i=0;i<cronPresets.length;i++){var p=cronPresets[i];if(p.minute===j.minute&&p.hour===j.hour&&p.day===j.day&&p.month===j.month&&p.weekday===j.weekday) return p.label;}
    return j.minute+" "+j.hour+" "+j.day+" "+j.month+" "+j.weekday;
}

function buildCronPane(){
    var pane=$("bt-pane-cronjobs");if(!pane) return;
    pane.innerHTML='<div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle" style="background:#0891b2"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div><h5>Cron Jobs</h5><p class="bt-cron-count">Loading...</p></div></div><div class="bt-card-head-right"><button type="button" class="bt-btn-add" id="btCronAddBtn" style="background:#0891b2"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Cron Job</button></div></div><div class="bt-list" id="bt-cron-list"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading cron jobs...</span></div></div></div>';
    $("btCronAddBtn").addEventListener("click",function(){openCronModal(null);});
}
function loadCronJobs(){
    var list=$("bt-cron-list");if(!list) return;
    list.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading cron jobs...</span></div>';
    post({action:"cron_list"},function(r){
        if(!r.success){list.innerHTML='<div class="bt-empty"><span>'+(r.message||"Failed to load cron jobs")+'</span></div>';return;}
        var jobs=r.jobs||[];
        var countEl=document.querySelector(".bt-cron-count");if(countEl) countEl.textContent=jobs.length+" job"+(jobs.length!==1?"s":"");
        if(!jobs.length){list.innerHTML='<div class="bt-empty"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span>No cron jobs configured</span></div>';return;}
        var html="";
        jobs.forEach(function(j){
            var sched=cronScheduleLabel(j);
            html+='<div class="bt-row" data-linekey="'+esc(j.linekey)+'">'
                +'<div class="bt-row-icon" style="background:rgba(8,145,178,.08);color:#0891b2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>'
                +'<div class="bt-row-info" style="flex-direction:column;align-items:flex-start;gap:2px;min-width:0"><span class="bt-row-name mono" style="font-size:13px;word-break:break-all">'+esc(j.command)+'</span><span style="font-size:12px;color:var(--text-muted,#6b7280)">'+esc(sched)+'</span></div>'
                +'<div class="bt-row-actions"><button type="button" class="bt-row-btn pass bt-cron-edit" data-linekey="'+esc(j.linekey)+'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg><span>Edit</span></button>'
                +'<button type="button" class="bt-row-btn del bt-cron-del" data-linekey="'+esc(j.linekey)+'"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button></div></div>';
        });
        list.innerHTML=html;
        list.querySelectorAll(".bt-cron-edit").forEach(function(btn){btn.addEventListener("click",function(){var lk=this.getAttribute("data-linekey");var job=jobs.find(function(j){return j.linekey===lk;});if(job) openCronModal(job);});});
        list.querySelectorAll(".bt-cron-del").forEach(function(btn){btn.addEventListener("click",function(){var lk=this.getAttribute("data-linekey");if(!confirm("Delete this cron job?")) return;btnLoad(this,"Deleting...");var self=this;post({action:"cron_delete",linekey:lk},function(r){btnDone(self);if(r.success) loadCronJobs();else alert(r.message||"Failed");});});});
    });
}

function openCronModal(job){
    var isEdit=!!job;
    var overlay=document.createElement("div");overlay.className="bt-overlay";overlay.id="btCronOverlay";
    var presetHtml='<option value="">Custom</option>';cronPresets.forEach(function(p,i){presetHtml+='<option value="'+i+'">'+esc(p.label)+'</option>';});
    overlay.innerHTML='<div class="bt-modal" style="max-width:560px"><div class="bt-modal-head"><h5>'+(isEdit?"Edit":"Add")+' Cron Job</h5><button type="button" class="bt-modal-close" data-cron-close>&times;</button></div><div class="bt-modal-body">'
        +'<div class="bt-field"><label>Schedule Preset</label><select id="btCronPreset" class="bt-select">'+presetHtml+'</select></div>'
        +'<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px">'
        +'<div class="bt-field"><label>Minute</label><input type="text" id="btCronMin" value="'+esc(job?job.minute:"*")+'"></div>'
        +'<div class="bt-field"><label>Hour</label><input type="text" id="btCronHour" value="'+esc(job?job.hour:"*")+'"></div>'
        +'<div class="bt-field"><label>Day</label><input type="text" id="btCronDay" value="'+esc(job?job.day:"*")+'"></div>'
        +'<div class="bt-field"><label>Month</label><input type="text" id="btCronMonth" value="'+esc(job?job.month:"*")+'"></div>'
        +'<div class="bt-field"><label>Weekday</label><input type="text" id="btCronWeekday" value="'+esc(job?job.weekday:"*")+'"></div>'
        +'</div>'
        +'<div class="bt-field"><label>Command</label><input type="text" id="btCronCmd" value="'+esc(job?job.command:"")+'" placeholder="/usr/local/bin/php /home/user/script.php"></div>'
        +'<div class="bt-msg" id="btCronMsg"></div>'
        +'</div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-cron-close>Cancel</button><button type="button" class="bt-btn-primary" id="btCronSubmit">'+(isEdit?"Save Changes":"Add Cron Job")+'</button></div></div>';
    document.body.appendChild(overlay);overlay.style.display="flex";
    $("btCronPreset").addEventListener("change",function(){var idx=this.value;if(idx==="") return;var p=cronPresets[parseInt(idx)];$("btCronMin").value=p.minute;$("btCronHour").value=p.hour;$("btCronDay").value=p.day;$("btCronMonth").value=p.month;$("btCronWeekday").value=p.weekday;});
    overlay.querySelectorAll("[data-cron-close]").forEach(function(b){b.addEventListener("click",function(){overlay.remove();});});
    overlay.addEventListener("click",function(e){if(e.target===overlay) overlay.remove();});
    $("btCronSubmit").addEventListener("click",function(){
        var data={action:isEdit?"cron_edit":"cron_add",minute:$("btCronMin").value,hour:$("btCronHour").value,day:$("btCronDay").value,month:$("btCronMonth").value,weekday:$("btCronWeekday").value,command:$("btCronCmd").value.trim()};
        if(isEdit) data.linekey=job.linekey;
        var msg=$("btCronMsg");msg.style.display="none";
        if(!data.command){showMsg(msg,"Command is required",false);return;}
        this.disabled=true;var btn=this;
        post(data,function(r){btn.disabled=false;showMsg(msg,r.message||"Done",r.success);if(r.success) setTimeout(function(){overlay.remove();loadCronJobs();},600);});
    });
}

/* ─── PHP Version ─── */
var phpInstalledVersions=[];
function buildPhpPane(){
    var pane=$("bt-pane-phpversion");if(!pane) return;
    pane.innerHTML='<div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle" style="background:#6366f1"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/><line x1="14" y1="4" x2="10" y2="20"/></svg></div><div><h5>PHP Version</h5><p class="bt-php-subtitle">Manage PHP versions per domain</p></div></div></div><div class="bt-list" id="bt-php-list"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading PHP versions...</span></div></div></div>';
}
function loadPhpVersions(){
    var list=$("bt-php-list");if(!list) return;
    list.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading PHP versions...</span></div>';
    post({action:"php_get_versions"},function(r){
        if(!r.success){list.innerHTML='<div class="bt-empty"><span>'+(r.message||"Failed to load PHP versions")+'</span></div>';return;}
        phpInstalledVersions=r.installed||[];
        var vhosts=r.vhosts||[];
        var defaultVer=r["default"]||"";
        var sub=document.querySelector(".bt-php-subtitle");
        if(sub) sub.textContent=phpInstalledVersions.length+" version"+(phpInstalledVersions.length!==1?"s":"")+" available"+(defaultVer?" · Default: "+defaultVer:"");
        if(!vhosts.length&&!phpInstalledVersions.length){list.innerHTML='<div class="bt-empty"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/><line x1="14" y1="4" x2="10" y2="20"/></svg><span>PHP version management not available</span></div>';return;}
        var html="";
        vhosts.forEach(function(vh){
            var ver=vh.version||defaultVer||"Unknown";
            var opts="";phpInstalledVersions.forEach(function(v){opts+='<option value="'+esc(v)+'"'+(v===ver?" selected":"")+'>'+esc(v)+'</option>';});
            html+='<div class="bt-row" data-vhost="'+esc(vh.vhost)+'">'
                +'<div class="bt-row-icon" style="background:rgba(99,102,241,.08);color:#6366f1"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/></svg></div>'
                +'<div class="bt-row-info"><span class="bt-row-name">'+esc(vh.vhost)+'</span><span class="bt-row-badge bt-badge-primary">'+esc(ver)+'</span></div>'
                +'<div class="bt-row-actions" style="display:flex;align-items:center;gap:8px"><select class="bt-select bt-php-select" data-vhost="'+esc(vh.vhost)+'" style="padding:6px 10px;font-size:12px;min-width:100px">'+opts+'</select>'
                +'<button type="button" class="bt-row-btn pass bt-php-apply" data-vhost="'+esc(vh.vhost)+'" style="color:#6366f1"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg><span>Apply</span></button></div></div>';
        });
        list.innerHTML=html;
        list.querySelectorAll(".bt-php-apply").forEach(function(btn){
            btn.addEventListener("click",function(){
                var vhost=this.getAttribute("data-vhost");
                var sel=list.querySelector('.bt-php-select[data-vhost="'+vhost+'"]');
                if(!sel) return;
                btnLoad(this,"Applying...");var self=this;
                post({action:"php_set_version",vhost:vhost,version:sel.value},function(r){btnDone(self);if(r.success){self.innerHTML='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg><span>Applied</span>';self.style.color="#059669";var pane=$("bt-pane-phpversion");if(pane) pane.dataset.loaded="";setTimeout(function(){loadPhpVersions();},1500);}else{alert(r.message||"Failed");}});
            });
        });
    });
}

/* ─── Error Logs ─── */
var logsAutoRefresh=null;
function buildLogsPane(){
    var pane=$("bt-pane-errorlogs");if(!pane) return;
    pane.innerHTML='<div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle" style="background:#ef4444"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div><div><h5>Error Logs</h5><p class="bt-logs-subtitle">Loading...</p></div></div><div class="bt-card-head-right" style="display:flex;gap:8px;align-items:center"><select id="btLogsLines" class="bt-select" style="padding:6px 10px;font-size:12px"><option value="50">50 lines</option><option value="100" selected>100 lines</option><option value="200">200 lines</option><option value="500">500 lines</option></select><button type="button" class="bt-btn-outline" id="btLogsRefresh" style="padding:6px 12px;font-size:12px"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Refresh</button></div></div><div id="bt-logs-body"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading error logs...</span></div></div></div>';
    $("btLogsRefresh").addEventListener("click",function(){loadErrorLogs();});
    $("btLogsLines").addEventListener("change",function(){loadErrorLogs();});
}

function loadErrorLogs(){
    var body=$("bt-logs-body");if(!body) return;
    body.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading error logs...</span></div>';
    var linesSel=$("btLogsLines");var numLines=linesSel?parseInt(linesSel.value):100;
    post({action:"error_log_read",lines:numLines},function(r){
        if(!r.success&&!r.lines){body.innerHTML='<div class="bt-empty"><span>'+(r.message||"Failed to load error logs")+'</span></div>';return;}
        var lines=r.lines||[];
        var sub=document.querySelector(".bt-logs-subtitle");
        if(sub) sub.textContent=(r.file||"Error Log")+" · Showing "+lines.length+" of "+(r.total||lines.length)+" entries";
        if(!lines.length){body.innerHTML='<div class="bt-empty"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>No error log entries found</span></div>';return;}
        var html='<div class="bt-log-pre"><div class="bt-log-summary"><span style="color:#94a3b8;font-size:12px">'+lines.length+' entries</span></div><div style="padding:0 4px">';
        lines.forEach(function(line){
            var cls="bt-log-line";
            var lower=line.toLowerCase();
            if(lower.indexOf("[error]")!==-1||lower.indexOf("fatal")!==-1) cls+=" error";
            else if(lower.indexOf("[warn")!==-1||lower.indexOf("warning")!==-1) cls+=" warning";
            else if(lower.indexOf("[notice")!==-1||lower.indexOf("[info")!==-1) cls+=" notice";
            html+='<div class="'+cls+'">'+esc(line)+'</div>';
        });
        html+='</div></div>';
        body.innerHTML=html;
    });
}

/* ─── Modal Bindings ─── */
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
    bindEmailModalSubmits();
    bindDomainModalSubmits();
    bindWpDetailPanel();
}

/* ─── Boot ─── */
if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",init);
else init();

})();