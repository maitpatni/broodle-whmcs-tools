(function(){
"use strict";
window.__btClientLoaded=true;
console.log("[BT] bt_client.js loaded successfully, version 3.10.65");
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
'.bt-page-wrap,#bt-page-wrap{display:flex;gap:24px;min-height:400px}',
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
/* cPanel Credentials */
'.bt-cpanel-creds{background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;padding:0;overflow:hidden}',
'.bt-cred-header{display:flex;align-items:center;gap:14px;padding:20px 24px;border-bottom:1px solid var(--border-color,#f3f4f6);background:var(--input-bg,#f8fafc)}',
'.bt-cred-icon{width:48px;height:48px;background:linear-gradient(135deg,#ff6c2c 0%,#ff8f2c 100%);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}',
'.bt-cred-icon svg{stroke:#fff}',
'.bt-cred-row{padding:18px 24px;border-bottom:1px solid var(--border-color,#f3f4f6)}',
'.bt-cred-row:last-of-type{border-bottom:none}',
'.bt-cred-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted,#9ca3af);margin:0 0 8px}',
'.bt-cred-field{display:flex;align-items:center;justify-content:space-between;background:var(--input-bg,#f8fafc);border:1px solid var(--border-color,#e5e7eb);border-radius:8px;padding:10px 14px;gap:10px}',
'.bt-cred-value{font-size:14px;font-weight:500;color:var(--heading-color,#1e293b);word-break:break-all;flex:1}',
'.bt-cred-value.mono{font-family:"SF Mono",SFMono-Regular,Consolas,"Liberation Mono",Menlo,monospace;font-size:13px;letter-spacing:.3px}',
'.bt-cred-actions{display:flex;align-items:center;gap:4px;flex-shrink:0}',
'.bt-cred-btn{background:none;border:1px solid transparent;border-radius:6px;padding:6px;cursor:pointer;color:var(--text-muted,#6b7280);display:flex;align-items:center;justify-content:center;transition:all .15s}',
'.bt-cred-btn:hover{background:var(--card-bg,#fff);border-color:var(--border-color,#e5e7eb);color:#0a5ed3}',
'a.bt-cred-btn{text-decoration:none}',
'.bt-cred-extra{display:flex;gap:24px;padding:16px 24px;background:var(--input-bg,#f8fafc);border-top:1px solid var(--border-color,#f3f4f6)}',
'.bt-cred-extra-item{display:flex;flex-direction:column;gap:2px}',
'.bt-cred-extra-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted,#9ca3af)}',
'.bt-cred-extra-val{font-size:13px;font-weight:500;color:var(--heading-color,#1e293b)}',
'@media(max-width:480px){.bt-cred-field{flex-direction:column;align-items:flex-start}.bt-cred-actions{align-self:flex-end}.bt-cred-extra{flex-direction:column;gap:12px}}',
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
/* WP card list layout */
'.bwp-card-list{display:flex;flex-direction:column;gap:14px;padding:16px}',
'.bwp-card-item{display:flex;border:1px solid var(--border-color,#e5e7eb);border-radius:14px;overflow:hidden;cursor:pointer;transition:all .2s ease}.bwp-card-item:hover{border-color:#0a5ed3;box-shadow:0 4px 16px rgba(10,94,211,.1);transform:translateY(-1px)}',
'.bwp-card-preview{width:220px;min-width:220px;flex-shrink:0;background:var(--input-bg,#f3f4f6);display:flex;flex-direction:column;border-right:1px solid var(--border-color,#e5e7eb);position:relative}',
'.bwp-card-preview-bar{display:flex;align-items:center;gap:6px;padding:7px 10px;background:var(--input-bg,#f9fafb);border-bottom:1px solid var(--border-color,#e5e7eb)}',
'.bwp-card-preview-frame{flex:1;overflow:hidden;position:relative;min-height:140px}',
'.bwp-card-body{flex:1;min-width:0;padding:16px 18px;display:flex;flex-direction:column;gap:12px}',
'.bwp-card-header{display:flex;align-items:flex-start;gap:12px}',
'.bwp-card-wp-icon{width:40px;height:40px;border-radius:10px;background:rgba(33,117,208,.08);display:flex;align-items:center;justify-content:center;color:#2175d0;flex-shrink:0}',
'.bwp-card-header-info{flex:1;min-width:0}',
'.bwp-card-domain{margin:0;font-size:15px;font-weight:700;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;letter-spacing:-.2px}',
'.bwp-card-path{margin:2px 0 0;font-size:11px;color:var(--text-muted,#9ca3af);font-family:monospace}',
'.bwp-card-badges{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:2px}',
'.bwp-card-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:600;letter-spacing:.2px}',
'.bwp-card-badge.version{background:rgba(33,117,208,.06);color:#2175d0}',
'.bwp-card-badge.status-active{background:rgba(5,150,105,.06);color:#059669}',
'.bwp-card-badge.status-inactive{background:rgba(239,68,68,.06);color:#ef4444}',
'.bwp-card-badge.updates{background:rgba(217,119,6,.06);color:#d97706}',
'.bwp-card-badge.ssl-on{background:rgba(5,150,105,.06);color:#059669}',
'.bwp-card-badge.ssl-off{background:rgba(217,119,6,.06);color:#d97706}',
'.bwp-card-stats{display:flex;gap:16px;padding-top:8px;border-top:1px solid var(--border-color,#f3f4f6)}',
'.bwp-card-stat{display:flex;flex-direction:column;gap:1px}',
'.bwp-card-stat-label{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted,#9ca3af)}',
'.bwp-card-stat-val{font-size:13px;font-weight:600;color:var(--heading-color,#111827)}',
'.bwp-card-arrow{display:flex;align-items:center;padding:0 14px;color:var(--text-muted,#d1d5db);flex-shrink:0;transition:color .15s}.bwp-card-item:hover .bwp-card-arrow{color:#0a5ed3}',
'@media(max-width:640px){.bwp-card-item{flex-direction:column}.bwp-card-preview{width:100%;min-width:0;border-right:none;border-bottom:1px solid var(--border-color,#e5e7eb)}.bwp-card-preview-frame{min-height:110px}.bwp-card-arrow{display:none}}',
/* WP inline detail */
'.bwp-inline-detail{padding:20px 0}',
'.bwp-inline-detail .bt-card{border-radius:14px;overflow:hidden}',
'.bwp-inline-detail .bwp-detail-head{display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid var(--border-color,#f3f4f6);background:var(--card-bg,#fff)}',
'.bwp-inline-detail .bwp-detail-head h5{flex:1;margin:0;font-size:16px;font-weight:700;color:var(--heading-color,#111827);letter-spacing:-.2px}',
'.bwp-inline-detail .bwp-detail-body{padding:20px}',
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
'.bt-hero-left{flex:0 1 auto;min-width:0;max-width:380px;width:100%;background:linear-gradient(135deg,#1a6ddb 0%,#0a5ed3 40%,#3b82f6 100%);color:#fff;padding:28px 24px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;min-height:180px;position:relative;overflow:hidden}',
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
'.bt-hero-right{flex:1;min-width:200px;padding:24px 20px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;border-left:1px solid var(--border-color,#e5e7eb)}',
'.bt-gauges{display:flex;gap:20px;align-items:center;justify-content:center;flex-wrap:wrap}',
'.bt-gauge{text-align:center}',
'.bt-gauge-ring{position:relative;width:80px;height:80px}',
'.bt-gauge-ring svg{transform:rotate(-90deg)}',
'.bt-gauge-ring circle{fill:none;stroke-width:7;stroke-linecap:round}',
'.bt-gauge-ring .bg{stroke:var(--border-color,#e5e7eb)}',
'.bt-gauge-ring .fill{transition:stroke-dashoffset .8s ease}',
'.bt-gauge-pct{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:var(--heading-color,#111827)}',
'.bt-gauge-label{margin-top:6px;font-size:11px;font-weight:600;color:var(--heading-color,#111827)}',
'.bt-gauge-sub{font-size:10px;color:var(--text-muted,#6b7280);margin-top:1px}',
/* ── Hero Resource Bars ── */
'.bt-hero-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px;padding-top:10px;border-top:1px solid var(--border-color,#f3f4f6);width:100%;margin-top:4px}',
'.bt-res-bar{display:flex;flex-direction:column;gap:3px}',
'.bt-res-bar-head{display:flex;justify-content:space-between;align-items:center}',
'.bt-res-bar-label{font-size:10px;font-weight:600;color:var(--heading-color,#111827);display:flex;align-items:center;gap:4px}',
'.bt-res-bar-label svg{width:12px;height:12px;opacity:.6}',
'.bt-res-bar-val{font-size:10px;color:var(--text-muted,#6b7280)}',
'.bt-res-bar-track{height:6px;background:var(--border-color,#e5e7eb);border-radius:3px;overflow:hidden}',
'.bt-res-bar-fill{height:100%;border-radius:3px;transition:width .8s ease;min-width:2px}',
'.bt-res-bar-fill.green{background:linear-gradient(90deg,#10b981,#34d399)}',
'.bt-res-bar-fill.yellow{background:linear-gradient(90deg,#f59e0b,#fbbf24)}',
'.bt-res-bar-fill.red{background:linear-gradient(90deg,#ef4444,#f87171)}',
'.bt-res-bar-fill.blue{background:linear-gradient(90deg,#0a5ed3,#3b82f6)}',
'.bt-hero-stats .bt-res-loading{grid-column:1/-1;text-align:center;font-size:11px;color:var(--text-muted,#9ca3af);padding:6px 0}',
/* ── Quick Access ── */
'.bt-shortcuts{margin-bottom:20px}',
'.bt-shortcuts-title{font-size:14px;font-weight:700;color:var(--heading-color,#111827);margin:0 0 10px}',
'.bt-shortcuts-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:12px;padding:12px 14px}',
'.bt-sc-item{display:flex;align-items:center;gap:7px;padding:8px 10px;border-radius:8px;font-size:12px;font-weight:500;color:#0a5ed3;cursor:pointer;transition:background .12s;text-decoration:none}',
'.bt-sc-item:hover{background:rgba(10,94,211,.06);text-decoration:none;color:#0a5ed3}',
'.bt-sc-item svg{flex-shrink:0;width:16px;height:16px}',
/* ── Responsive ── */
'@media(max-width:900px){.bt-hero{flex-direction:column}.bt-hero-left{max-width:100%}.bt-hero-right{width:100%;padding:20px;border-left:none;border-top:1px solid var(--border-color,#e5e7eb)}.bt-hero-left{min-height:140px}}',
'@media(max-width:768px){.bt-shortcuts-grid{grid-template-columns:repeat(2,1fr)}.bt-hero-stats{grid-template-columns:1fr}}',
'@media(max-width:400px){.bt-shortcuts-grid{grid-template-columns:1fr}.bt-gauges{gap:10px}.bt-gauge-ring{width:60px;height:60px}.bt-gauge-ring svg{width:60px;height:60px}.bt-gauge-pct{font-size:12px}}',
/* ── Dark mode ── */
'[data-theme="dark"] .bt-hero{border-color:var(--border-color,#334155)}',
'[data-theme="dark"] .bt-hero-right{background:var(--card-bg,#1e293b)}',
'[data-theme="dark"] .bt-shortcuts-grid{background:var(--card-bg,#1e293b);border-color:var(--border-color,#334155)}',
'[data-theme="dark"] .bt-sc-item:hover{background:rgba(59,130,246,.1)}',
    ].join('\n');
    document.head.appendChild(s);
}
var savedChangePwPane=null;

/* ─── Open specific cPanel page via SSO ─── */
window.btOpenCpanelPage=function(page,el){
    if(el){el.style.opacity='.5';el.style.pointerEvents='none';}
    post({action:'get_cpanel_sso_url',page:page},function(r){
        if(el){el.style.opacity='';el.style.pointerEvents='';}
        if(r.success&&r.url) window.open(r.url,'_blank');
        else window.open('clientarea.php?action=productdetails&id='+C.serviceId+'&dosinglesignon=1','_blank');
    });
};

function init(){
    injectStyles();injectStyles2();injectStyles3();injectStyles4();injectStyles5();injectStyles6();injectStyles7();injectStyles8();injectStyles9();

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
    /* Domain gauge */
    var domUsed=C.totalDomainCount||0;var domLimit=C.domainLimit||0;
    var domPct=domLimit>0?Math.min(100,Math.round(domUsed/domLimit*100)):0;
    var domOff=circ-(domPct/100*circ);
    var domColor=domLimit<=0?"#0a5ed3":domPct>90?"#ef4444":domPct>70?"#f59e0b":"#0a5ed3";
    var domLimitTxt=domLimit<0?"Unlimited":(domLimit>0?domLimit:"0");
    heroHtml+='<div class="bt-gauge"><div class="bt-gauge-ring"><svg width="80" height="80" viewBox="0 0 80 80"><circle class="bg" cx="40" cy="40" r="35"/><circle class="fill" cx="40" cy="40" r="35" stroke="'+domColor+'" stroke-dasharray="'+circ.toFixed(1)+'" stroke-dashoffset="'+(domLimit>0?domOff.toFixed(1):circ.toFixed(1))+'"/></svg><div class="bt-gauge-pct">'+(domLimit>0?domPct+'%':domUsed)+'</div></div><div class="bt-gauge-label">Domains</div><div class="bt-gauge-sub">'+domUsed+' / '+domLimitTxt+'</div></div>';
    /* Email gauge */
    var emUsed=C.emailCount||0;var emLimit=C.emailLimit||0;
    var emPct=emLimit>0?Math.min(100,Math.round(emUsed/emLimit*100)):0;
    var emOff=circ-(emPct/100*circ);
    var emColor=emLimit<=0?"#0a5ed3":emPct>90?"#ef4444":emPct>70?"#f59e0b":"#0a5ed3";
    var emLimitTxt=emLimit<0?"Unlimited":(emLimit>0?emLimit:"0");
    heroHtml+='<div class="bt-gauge"><div class="bt-gauge-ring"><svg width="80" height="80" viewBox="0 0 80 80"><circle class="bg" cx="40" cy="40" r="35"/><circle class="fill" cx="40" cy="40" r="35" stroke="'+emColor+'" stroke-dasharray="'+circ.toFixed(1)+'" stroke-dashoffset="'+(emLimit>0?emOff.toFixed(1):circ.toFixed(1))+'"/></svg><div class="bt-gauge-pct">'+(emLimit>0?emPct+'%':emUsed)+'</div></div><div class="bt-gauge-label">Emails</div><div class="bt-gauge-sub">'+emUsed+' / '+emLimitTxt+'</div></div>';
    heroHtml+='</div>';
    /* Resource usage bars — loaded via AJAX */
    heroHtml+='<div class="bt-hero-stats" id="bt-hero-res"><div class="bt-res-loading"><div class="bt-spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:6px"></div>Loading resource usage...</div></div>';
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
    var heroSection=document.createElement("div");
    heroSection.id="bt-hero-section";
    var headerDiv=document.createElement("div");
    headerDiv.innerHTML=heroHtml;
    while(headerDiv.firstChild) heroSection.appendChild(headerDiv.firstChild);
    mainArea.appendChild(heroSection);

    /* ── Tabs container ── */
    var tabsWrap=document.createElement("div");
    tabsWrap.className="bt-wrap";tabsWrap.id="bt-wrap";
    mainArea.appendChild(tabsWrap);

    /* ── WordPress page (hidden by default) ── */
    var wpPage=document.createElement("div");
    wpPage.id="bt-wp-page";
    wpPage.style.display="none";
    mainArea.appendChild(wpPage);

    /* ── File Manager page (hidden by default) ── */
    var fmPage=document.createElement("div");
    fmPage.id="bt-fm-page";
    fmPage.style.display="none";
    mainArea.appendChild(fmPage);

    /* ── Domains page (hidden by default) ── */
    var domainsPage=document.createElement("div");
    domainsPage.id="bt-domains-page";
    domainsPage.style.display="none";
    mainArea.appendChild(domainsPage);

    /* ── Databases page (hidden by default) ── */
    var databasesPage=document.createElement("div");
    databasesPage.id="bt-databases-page";
    databasesPage.style.display="none";
    mainArea.appendChild(databasesPage);

    /* ── SSL page (hidden by default) ── */
    var sslPage=document.createElement("div");
    sslPage.id="bt-ssl-page";
    sslPage.style.display="none";
    mainArea.appendChild(sslPage);

    /* ── Email page (hidden by default) ── */
    var emailPage=document.createElement("div");
    emailPage.id="bt-email-page";
    emailPage.style.display="none";
    mainArea.appendChild(emailPage);

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
    html+='</div>';

    /* Management panel */
    html+='<div class="bt-sidebar-panel"><div class="bt-sidebar-title">Management</div>';
    if(C.fmEnabled){
        html+='<a class="bt-sidebar-item" data-page="filemanager"><div class="bt-si-icon" style="background:rgba(234,88,12,.08);color:#ea580c"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div><div class="bt-si-label">File Manager<span>Files &amp; Folders</span></div></a>';
    }
    if(C.domainEnabled){
        html+='<a class="bt-sidebar-item" data-page="domains"><div class="bt-si-icon" style="background:rgba(10,94,211,.08);color:#0a5ed3"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg></div><div class="bt-si-label">Domains<span>Domain Management</span></div></a>';
    }
    if(C.dbEnabled){
        html+='<a class="bt-sidebar-item" data-page="databases"><div class="bt-si-icon" style="background:rgba(124,58,237,.08);color:#7c3aed"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg></div><div class="bt-si-label">Databases<span>MySQL Management</span></div></a>';
    }
    if(C.sslEnabled){
        html+='<a class="bt-sidebar-item" data-page="ssl"><div class="bt-si-icon" style="background:rgba(5,150,105,.08);color:#059669"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div><div class="bt-si-label">SSL<span>Certificate Management</span></div></a>';
    }
    if(C.emailEnabled){
        html+='<a class="bt-sidebar-item" data-page="email"><div class="bt-si-icon" style="background:rgba(217,119,6,.08);color:#d97706"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></div><div class="bt-si-label">Email<span>Email Accounts</span></div></a>';
    }
    if(C.wpEnabled){
        html+='<a class="bt-sidebar-item" data-page="wordpress"><div class="bt-si-icon" style="background:rgba(33,117,208,.08);color:#2175d0"><svg viewBox="0 0 16 16" fill="#2175d0" width="18" height="18"><path d="M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046zM1.137 8a6.86 6.86 0 0 0 3.868 6.176L1.73 5.206A6.8 6.8 0 0 0 1.137 8"/><path d="M6.061 14.583 8.121 8.6l2.109 5.78q.02.05.049.094a6.85 6.85 0 0 1-4.218.109m7.96-9.876q.046.328.047.706c0 .696-.13 1.479-.522 2.458l-2.096 6.06a6.86 6.86 0 0 0 2.572-9.224z"/><path fill-rule="evenodd" d="M0 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.59 8-8 8-8-3.589-8-8m.367 0c0 4.209 3.424 7.633 7.633 7.633S15.632 12.209 15.632 8C15.632 3.79 12.208.367 8 .367 3.79.367.367 3.79.367 8"/></svg></div><div class="bt-si-label">WordPress<span>Site Management</span></div></a>';
    }
    html+='</div>';

    /* Actions panel */
    html+='<div class="bt-sidebar-panel"><div class="bt-sidebar-title">Actions</div>';
    /* cPanel login */
    html+='<a class="bt-sidebar-item" href="clientarea.php?action=productdetails&id='+C.serviceId+'&dosinglesignon=1" target="_blank" id="bt-cpanel-link"><div class="bt-si-icon" style="background:rgba(255,106,19,.08);padding:0"><img src="'+btBasePath+'cpanel-icon.png" width="34" height="34" alt="cPanel" style="border-radius:9px"></div><div class="bt-si-label">Login to cPanel<span>Control Panel</span></div></a>';
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
            var fmPage=$("bt-fm-page");
            var changePwPage=$("bt-changepw-page");
            var domainsPage=$("bt-domains-page");
            var databasesPage=$("bt-databases-page");
            var sslPage=$("bt-ssl-page");
            var emailPage=$("bt-email-page");
            var heroSection=$("bt-hero-section");
            if(wrap) wrap.style.display="none";
            if(wpPage) wpPage.style.display="none";
            if(fmPage) fmPage.style.display="none";
            if(changePwPage) changePwPage.style.display="none";
            if(domainsPage) domainsPage.style.display="none";
            if(databasesPage) databasesPage.style.display="none";
            if(sslPage) sslPage.style.display="none";
            if(emailPage) emailPage.style.display="none";

            if(page==="tabs"){
                if(heroSection) heroSection.style.display="";
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
                if(heroSection) heroSection.style.display="none";
                if(wpPage){
                    wpPage.style.display="";
                    if(!wpPage.dataset.loaded){
                        wpPage.dataset.loaded="1";
                        buildWpPaneInto(wpPage);
                        loadWpInstances();
                    }
                }
                if(history.replaceState) history.replaceState(null,null,"#tabWordpress");
            }else if(page==="filemanager"){
                if(heroSection) heroSection.style.display="none";
                if(fmPage){
                    fmPage.style.display="";
                    if(!fmPage.dataset.loaded){
                        fmPage.dataset.loaded="1";
                        buildFileManagerPageInto(fmPage);
                    }
                }
                if(history.replaceState) history.replaceState(null,null,"#tabFilemanager");
            }else if(page==="domains"){
                if(heroSection) heroSection.style.display="none";
                if(domainsPage){
                    domainsPage.style.display="";
                    if(!domainsPage.dataset.loaded){
                        domainsPage.dataset.loaded="1";
                        buildDomainPageInto(domainsPage);
                    }
                }
                if(history.replaceState) history.replaceState(null,null,"#tabDomains");
            }else if(page==="databases"){
                if(heroSection) heroSection.style.display="none";
                if(databasesPage){
                    databasesPage.style.display="";
                    if(!databasesPage.dataset.loaded){
                        databasesPage.dataset.loaded="1";
                        buildDatabasePageInto(databasesPage);
                    }
                }
                if(history.replaceState) history.replaceState(null,null,"#tabDatabases");
            }else if(page==="ssl"){
                if(heroSection) heroSection.style.display="none";
                if(sslPage){
                    sslPage.style.display="";
                    if(!sslPage.dataset.loaded){
                        sslPage.dataset.loaded="1";
                        buildSSLPageInto(sslPage);
                    }
                }
                if(history.replaceState) history.replaceState(null,null,"#tabSsl");
            }else if(page==="email"){
                if(heroSection) heroSection.style.display="none";
                if(emailPage){
                    emailPage.style.display="";
                    if(!emailPage.dataset.loaded){
                        emailPage.dataset.loaded="1";
                        buildEmailPageInto(emailPage);
                    }
                }
                if(history.replaceState) history.replaceState(null,null,"#tabEmail");
            }else if(page==="changepw"){
                if(heroSection) heroSection.style.display="none";
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
        {id:"cpanel",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="3"/><path d="M3 12h2M19 12h2M12 3v2M12 19v2"/></svg>',label:"cPanel Credentials"},
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
            var fmPage=$("bt-fm-page");if(fmPage) fmPage.style.display="none";
            var changePwPage=$("bt-changepw-page");if(changePwPage) changePwPage.style.display="none";
            var domainsPage=$("bt-domains-page");if(domainsPage) domainsPage.style.display="none";
            var databasesPage=$("bt-databases-page");if(databasesPage) databasesPage.style.display="none";
            var sslPage=$("bt-ssl-page");if(sslPage) sslPage.style.display="none";
            var emailPage=$("bt-email-page");if(emailPage) emailPage.style.display="none";
            var heroSection=$("bt-hero-section");if(heroSection) heroSection.style.display="";
            wrap.style.display="";
            var hashName="tab"+t.id.charAt(0).toUpperCase()+t.id.slice(1);
            if(history.replaceState) history.replaceState(null,null,"#"+hashName);
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
    buildCpanelPane();
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
        "filemanager":"filemanager","files":"filemanager","fm":"filemanager",
        "overview":"overview","domains":"domains","domain":"domains",
        "ssl":"ssl","email":"email","emailaccounts":"email",
        "databases":"databases","database":"databases","db":"databases",
        "dns":"domains","dnsmanager":"domains","cronjobs":"cronjobs","cron":"cronjobs",
        "php":"phpversion","phpversion":"phpversion",
        "cpanel":"cpanel","cpanelcredentials":"cpanel",
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
    if(targetId==="filemanager"){
        var fmItem=document.querySelector('.bt-sidebar-item[data-page="filemanager"]');
        if(fmItem) fmItem.click();
        return;
    }
    if(targetId==="changepw"){
        var cpItem=document.querySelector('.bt-sidebar-item[data-page="changepw"]');
        if(cpItem) cpItem.click();
        return;
    }
    if(targetId==="domains"){
        var domItem=document.querySelector('.bt-sidebar-item[data-page="domains"]');
        if(domItem){domItem.click();return;}
    }
    if(targetId==="databases"){
        var dbItem=document.querySelector('.bt-sidebar-item[data-page="databases"]');
        if(dbItem){dbItem.click();return;}
    }
    if(targetId==="ssl"){
        var sslItem=document.querySelector('.bt-sidebar-item[data-page="ssl"]');
        if(sslItem){sslItem.click();return;}
    }
    if(targetId==="email"){
        var emItem=document.querySelector('.bt-sidebar-item[data-page="email"]');
        if(emItem){emItem.click();return;}
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
    +'<div class="bt-overlay" id="bdbAssignModal" style="display:none"><div class="bt-modal"><div class="bt-modal-head"><h5>Assign User to Database</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>Database</label><select id="bdbAssignDb" class="bt-select"></select></div><div class="bt-field"><label>User</label><select id="bdbAssignUser" class="bt-select"></select></div><div class="bt-field"><label>Privileges</label><label class="bt-checkbox"><input type="checkbox" id="bdbAssignAll" checked> All Privileges</label></div><div class="bt-msg" id="bdbAssignMsg"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="bdbAssignSubmit">Assign Privileges</button></div></div></div>';
}

/* ─── Overview Pane ─── */
function buildOverviewPane(){
    var pane=$("bt-pane-overview");if(!pane) return;
    var pairs=[];
    var html="";

    /* Billing & service info cards */
    if(C.domains&&C.domains.main) pairs.push({label:"Primary Domain",value:'<a href="https://'+esc(C.domains.main)+'" target="_blank">'+esc(C.domains.main)+'</a>'});
    if(C.regDate) pairs.push({label:"Registration Date",value:esc(C.regDate)});
    if(C.nextDueDate){
        var dueVal=esc(C.nextDueDate);
        /* Color-code due date */
        var now=new Date();var due=new Date(C.nextDueDate);
        var diffDays=Math.ceil((due-now)/(1000*60*60*24));
        var dueCls="bt-ov-due-ok";var daysText="";
        if(diffDays<0){dueCls="bt-ov-due-past";daysText='<span class="bt-ov-days" style="color:#ef4444">'+Math.abs(diffDays)+' days overdue</span>';}
        else if(diffDays<=7){dueCls="bt-ov-due-danger";daysText='<span class="bt-ov-days" style="color:#ef4444">'+diffDays+' days left</span>';}
        else if(diffDays<=30){dueCls="bt-ov-due-warn";daysText='<span class="bt-ov-days" style="color:#d97706">'+diffDays+' days left</span>';}
        else{daysText='<span class="bt-ov-days" style="color:#059669">'+diffDays+' days left</span>';}
        pairs.push({label:"Next Due Date",value:'<span class="'+dueCls+'">'+dueVal+'</span>'+daysText});
    }
    if(C.price) pairs.push({label:"Recurring Amount",value:esc(C.price)+(C.billingCycle?' <span style="font-size:11px;color:var(--text-muted,#9ca3af);font-weight:400">('+esc(C.billingCycle)+')</span>':'')});
    if(C.paymentMethod) pairs.push({label:"Payment Method",value:esc(C.paymentMethod.charAt(0).toUpperCase()+C.paymentMethod.slice(1))});

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

/* ─── cPanel Credentials Pane ─── */
function buildCpanelPane(){
    var pane=$("bt-pane-cpanel");if(!pane) return;
    var cpUrl=C.cpanelUrl||"";
    var cpUser=C.username||"";
    var cpPass=C.cpanelPassword||"";

    var svgCopy='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
    var svgEye='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var svgEyeOff='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    var svgLink='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';

    var html='<div class="bt-cpanel-creds">';
    html+='<div class="bt-cred-header"><div class="bt-cred-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ff6c2c" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="4"/><path d="M12 8v4l3 3"/></svg></div><div><h4 style="margin:0;font-size:18px;font-weight:600;color:var(--text-primary,#1e293b)">cPanel Credentials</h4><p style="margin:2px 0 0;font-size:13px;color:var(--text-muted,#64748b)">Access your hosting control panel</p></div></div>';

    /* Login URL */
    html+='<div class="bt-cred-row">';
    html+='<div class="bt-cred-label">Login URL</div>';
    html+='<div class="bt-cred-field"><span class="bt-cred-value mono" id="btCpUrl">'+esc(cpUrl)+'</span><div class="bt-cred-actions"><button type="button" class="bt-cred-btn bt-copy" data-copy="'+esc(cpUrl)+'" title="Copy">'+svgCopy+'</button><a href="'+esc(cpUrl)+'" target="_blank" class="bt-cred-btn" title="Open cPanel">'+svgLink+'</a></div></div>';
    html+='</div>';

    /* Username */
    html+='<div class="bt-cred-row">';
    html+='<div class="bt-cred-label">Username</div>';
    html+='<div class="bt-cred-field"><span class="bt-cred-value mono" id="btCpUser">'+esc(cpUser)+'</span><div class="bt-cred-actions"><button type="button" class="bt-cred-btn bt-copy" data-copy="'+esc(cpUser)+'" title="Copy">'+svgCopy+'</button></div></div>';
    html+='</div>';

    /* Password */
    html+='<div class="bt-cred-row">';
    html+='<div class="bt-cred-label">Password</div>';
    html+='<div class="bt-cred-field"><span class="bt-cred-value mono" id="btCpPass" data-hidden="1">'+("•".repeat(Math.max(cpPass.length,8)))+'</span><div class="bt-cred-actions"><button type="button" class="bt-cred-btn" id="btCpToggle" title="Show/Hide">'+svgEye+'</button><button type="button" class="bt-cred-btn bt-copy" data-copy="'+esc(cpPass)+'" title="Copy">'+svgCopy+'</button></div></div>';
    html+='</div>';

    /* Server info */
    if(C.serverName||C.serverIp){
        html+='<div class="bt-cred-extra">';
        if(C.serverName) html+='<div class="bt-cred-extra-item"><span class="bt-cred-extra-label">Server</span><span class="bt-cred-extra-val">'+esc(C.serverName)+'</span></div>';
        if(C.serverIp) html+='<div class="bt-cred-extra-item"><span class="bt-cred-extra-label">IP Address</span><span class="bt-cred-extra-val">'+esc(C.serverIp)+'</span></div>';
        html+='</div>';
    }

    html+='</div>';
    pane.innerHTML=html;

    /* Toggle password visibility */
    var toggleBtn=$("btCpToggle");
    var passEl=$("btCpPass");
    if(toggleBtn&&passEl){
        toggleBtn.addEventListener("click",function(){
            var hidden=passEl.getAttribute("data-hidden")==="1";
            if(hidden){passEl.textContent=cpPass;passEl.setAttribute("data-hidden","0");toggleBtn.innerHTML=svgEyeOff;}
            else{passEl.textContent="•".repeat(Math.max(cpPass.length,8));passEl.setAttribute("data-hidden","1");toggleBtn.innerHTML=svgEye;}
        });
    }
    pane.querySelectorAll(".bt-copy").forEach(function(b){b.addEventListener("click",function(){doCopy(this.getAttribute("data-copy"),this);});});
}

/* ─── Quick Access builder helper ─── */
function buildQuickAccess(items){
    var html='<div class="bt-shortcuts"><h3 class="bt-shortcuts-title">Quick Access</h3><div class="bt-shortcuts-grid">';
    items.forEach(function(sc){
        html+='<a class="bt-sc-item" href="#" onclick="btOpenCpanelPage(\''+esc(sc.page)+'\',this);return false;">'+sc.icon+' '+esc(sc.label)+'</a>';
    });
    html+='</div></div>';
    return html;
}

/* ─── Email Page (separate page with Quick Access) ─── */
function buildEmailPageInto(container){
    if(!container) return;
    var qa=buildQuickAccess([
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',label:'Email Accounts',page:'mail/pops'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>',label:'Forwarders',page:'mail/fwds'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',label:'Email Routing',page:'mail/mx'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',label:'Spam Filters',page:'mail/spam'}
    ]);
    container.innerHTML=qa+'<div id="bt-pane-email"></div>';
    buildEmailPane();
}

/* ─── Domains Page (separate page with Quick Access) ─── */
function buildDomainPageInto(container){
    if(!container) return;
    var qa=buildQuickAccess([
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10A15.3 15.3 0 0 1 12 2z"/></svg>',label:'Addon Domains',page:'addon/index.html'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/></svg>',label:'Subdomains',page:'subdomain/index.html'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',label:'DNS Zone Editor',page:'zone_editor/index.html'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',label:'Redirects',page:'mime/redirect.html'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',label:'SSL/TLS Status',page:'ssl/index.html'}
    ]);
    container.innerHTML=qa+'<div id="bt-pane-domains"></div>'+(C.dnsEnabled?'<div id="bt-pane-dns" style="margin-top:20px"></div>':'');
    buildDomainsPane();
    if(C.dnsEnabled){
        buildDnsPane();
        loadDnsDomains();
    }
}

/* ─── Databases Page (separate page with Quick Access) ─── */
function buildDatabasePageInto(container){
    if(!container) return;
    var qa=buildQuickAccess([
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>',label:'MySQL Databases',page:'sql/index.html'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',label:'phpMyAdmin',page:'3rdparty/phpMyAdmin/index.php'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',label:'MySQL Users',page:'sql/index.html'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',label:'Remote MySQL',page:'sql/remotemysql.html'}
    ]);
    container.innerHTML=qa+'<div id="bt-pane-databases"></div>';
    buildDatabasesPane();
    loadDatabases();
}

/* ─── SSL Page (separate page with Quick Access) ─── */
function buildSSLPageInto(container){
    if(!container) return;
    var qa=buildQuickAccess([
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',label:'SSL/TLS Status',page:'ssl/index.html'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',label:'SSL/TLS Manager',page:'ssl/manage.html'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',label:'AutoSSL',page:'ssl/autossl.html'},
        {icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',label:'Install SSL',page:'ssl/install.html'}
    ]);
    container.innerHTML=qa+'<div id="bt-pane-ssl"></div>';
    buildSSLPane();
    loadSSLStatus();
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
function buildWpPaneInto(pane){if(!pane) return;pane.innerHTML='<div style="padding:20px 0"><div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle">'+wpSvg16.replace(/width="16"/g,'width="18"').replace(/height="16"/g,'height="18"')+'</div><div><h5>WordPress Manager</h5><p class="bt-wp-count">Loading...</p></div></div></div><div id="bt-wp-list"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading WordPress installations...</span></div></div></div></div>';}
function loadWpInstances(){var list=$("bt-wp-list");if(!list) return;list.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div><span>Loading WordPress installations...</span></div>';wpPost({action:"get_wp_instances"},function(r){if(!r.success){list.innerHTML='<div class="bt-empty"><span>'+(r.message||"Failed to load")+'</span></div>';return;}wpInstances=r.instances||[];var countEl=document.querySelector(".bt-wp-count");if(countEl) countEl.textContent=wpInstances.length+" site"+(wpInstances.length!==1?"s":"");if(!wpInstances.length){list.innerHTML='<div class="bt-empty" style="padding:40px 20px">'+wpSvg32+'<span style="margin-top:8px">No WordPress installations found</span><p style="margin:4px 0 0;font-size:12px;color:var(--text-muted,#9ca3af)">Install WordPress via cPanel to manage it here</p></div>';return;}var html='<div class="bwp-card-list">';wpInstances.forEach(function(inst){var siteUrl=inst.site_url||"";var totalUpdates=(inst.pluginUpdates||0)+(inst.themeUpdates||0)+(inst.availableUpdate?1:0);html+='<div class="bwp-card-item" data-wpid="'+inst.id+'">';html+='<div class="bwp-card-preview"><div class="bwp-card-preview-bar"><div class="bwp-preview-dots"><span></span><span></span><span></span></div><div class="bwp-preview-url">'+esc(siteUrl)+'</div></div><div class="bwp-card-preview-frame"><iframe src="'+esc(siteUrl)+'" style="width:200%;height:200%;transform:scale(.5);transform-origin:0 0;border:none;pointer-events:none" loading="lazy" sandbox="allow-same-origin"></iframe></div></div>';html+='<div class="bwp-card-body"><div class="bwp-card-header"><div class="bwp-card-wp-icon">'+wpSvg20+'</div><div class="bwp-card-header-info"><p class="bwp-card-domain">'+esc(inst.displayTitle||inst.domain)+'</p><p class="bwp-card-path">'+esc(inst.path||"/")+'</p></div></div>';html+='<div class="bwp-card-badges"><span class="bwp-card-badge version"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg> WP '+esc(inst.version)+'</span>';html+='<span class="bwp-card-badge '+(inst.alive?"status-active":"status-inactive")+'"><span style="width:5px;height:5px;border-radius:50%;background:currentColor;display:inline-block"></span> '+(inst.alive?"Active":"Inactive")+'</span>';if(inst.ssl!==undefined) html+='<span class="bwp-card-badge '+(inst.ssl?"ssl-on":"ssl-off")+'"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> SSL '+(inst.ssl?"On":"Off")+'</span>';if(totalUpdates>0) html+='<span class="bwp-card-badge updates"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> '+totalUpdates+' update'+(totalUpdates>1?"s":"")+'</span>';html+='</div>';html+='<div class="bwp-card-stats"><div class="bwp-card-stat"><div class="bwp-card-stat-label">Plugins</div><div class="bwp-card-stat-val">'+(inst.pluginUpdates>0?'<span style="color:#0a5ed3">'+inst.pluginUpdates+' update'+(inst.pluginUpdates>1?"s":"")+'</span>':'<span style="color:#059669">Up to date</span>')+'</div></div><div class="bwp-card-stat"><div class="bwp-card-stat-label">Themes</div><div class="bwp-card-stat-val">'+(inst.themeUpdates>0?'<span style="color:#7c3aed">'+inst.themeUpdates+' update'+(inst.themeUpdates>1?"s":"")+'</span>':'<span style="color:#059669">Up to date</span>')+'</div></div>';if(inst.availableUpdate) html+='<div class="bwp-card-stat"><div class="bwp-card-stat-label">Core</div><div class="bwp-card-stat-val"><span style="color:#d97706">'+esc(inst.availableUpdate)+'</span></div></div>';html+='</div></div>';html+='<div class="bwp-card-arrow"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>';html+='</div>';});html+='</div>';list.innerHTML=html;list.querySelectorAll(".bwp-card-item[data-wpid]").forEach(function(card){card.addEventListener("click",function(e){if(e.target.closest("a")) return;bwpOpenDetail(parseInt(this.getAttribute("data-wpid")));});});});}
function bwpAutoLogin(id){var btn=document.querySelector(".bt-row-btn.login[data-wpid='"+id+"']");if(btn) btnLoad(btn,"Logging in...");wpPost({action:"wp_autologin",instance_id:id},function(r){if(btn) btnDone(btn);if(r.success&&r.login_url) window.open(r.login_url,"_blank");else alert(r.message||"Could not generate login link");});}
window.bwpDoLogin=bwpAutoLogin;

function bwpOpenDetail(id){currentWpInstance=null;for(var i=0;i<wpInstances.length;i++){if(wpInstances[i].id===id){currentWpInstance=wpInstances[i];break;}}if(!currentWpInstance) return;var wpPage=$("bt-wp-page");if(!wpPage) return;var siteUrl=currentWpInstance.site_url||"";var totalUpdates=(currentWpInstance.pluginUpdates||0)+(currentWpInstance.themeUpdates||0)+(currentWpInstance.availableUpdate?1:0);var html='<div class="bwp-inline-detail" id="bwp-inline-detail">';html+='<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px"><button type="button" class="bt-btn-outline bwp-back-btn" id="bwpInlineBack" style="padding:7px 14px;display:inline-flex;align-items:center;gap:6px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg> Back to Sites</button><div style="display:flex;gap:8px"><button type="button" class="bt-btn-add" id="bwpDetailLogin" style="padding:7px 16px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg> WP Admin</button><a href="'+esc(siteUrl)+'" target="_blank" class="bt-btn-outline" style="padding:7px 16px;display:inline-flex;align-items:center;gap:6px;text-decoration:none"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg> Visit Site</a></div></div>';html+='<div class="bt-card" style="overflow:visible;border-radius:14px">';html+='<div class="bwp-detail-head"><div class="bwp-card-wp-icon" style="width:36px;height:36px;border-radius:9px;background:rgba(33,117,208,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0">'+wpSvg20+'</div><div style="flex:1;min-width:0"><h5 style="margin:0;font-size:16px;font-weight:700;color:var(--heading-color,#111827)">'+esc(currentWpInstance.displayTitle||currentWpInstance.domain)+'</h5><div style="display:flex;gap:6px;margin-top:4px;flex-wrap:wrap"><span class="bwp-card-badge version">WP '+esc(currentWpInstance.version)+'</span><span class="bwp-card-badge '+(currentWpInstance.alive?"status-active":"status-inactive")+'"><span style="width:5px;height:5px;border-radius:50%;background:currentColor;display:inline-block"></span> '+(currentWpInstance.alive?"Active":"Inactive")+'</span>'+(totalUpdates>0?'<span class="bwp-card-badge updates">'+totalUpdates+' update'+(totalUpdates>1?"s":"")+'</span>':'')+'</div></div></div>';html+='<div class="bwp-detail-tabs"><button type="button" class="bwp-tab active" data-tab="overview">Overview</button><button type="button" class="bwp-tab" data-tab="plugins">Plugins</button><button type="button" class="bwp-tab" data-tab="themes">Themes</button><button type="button" class="bwp-tab" data-tab="security">Security</button></div>';html+='<div class="bwp-detail-body" id="bwpDetailBody"><div class="bwp-tab-content active" id="bwpTabOverview"></div><div class="bwp-tab-content" id="bwpTabPlugins"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading plugins...</span></div></div><div class="bwp-tab-content" id="bwpTabThemes"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading themes...</span></div></div><div class="bwp-tab-content" id="bwpTabSecurity"><div class="bt-loading"><div class="bt-spinner"></div><span>Running security scan...</span></div></div></div></div></div>';wpPage.innerHTML=html;/* Render overview tab */var ovTab=$("bwpTabOverview");var ovHtml='<div class="bwp-overview-hero"><div class="bwp-preview-col"><div class="bwp-preview-wrap"><div class="bwp-preview-bar"><div class="bwp-preview-dots"><span></span><span></span><span></span></div><div class="bwp-preview-url">'+esc(siteUrl)+'</div></div><div class="bwp-preview-frame-wrap" style="height:220px"><iframe src="'+esc(siteUrl)+'" style="width:200%;height:200%;transform:scale(.5);transform-origin:0 0;border:none;pointer-events:none" loading="lazy" sandbox="allow-same-origin"></iframe></div></div></div>';ovHtml+='<div class="bwp-overview-right"><div class="bwp-overview-grid" style="grid-template-columns:1fr 1fr"><div class="bwp-stat"><div class="bwp-stat-label">Status</div><div class="bwp-stat-value">'+(currentWpInstance.alive?'<span style="color:#059669">Active</span>':'<span style="color:#ef4444">Inactive</span>')+'</div></div><div class="bwp-stat"><div class="bwp-stat-label">SSL</div><div class="bwp-stat-value">'+(currentWpInstance.ssl?'<span style="color:#059669">Enabled</span>':'<span style="color:#d97706">Disabled</span>')+'</div></div><div class="bwp-stat"><div class="bwp-stat-label">Plugin Updates</div><div class="bwp-stat-value">'+(currentWpInstance.pluginUpdates>0?'<span style="color:#0a5ed3">'+currentWpInstance.pluginUpdates+' available</span>':'<span style="color:#059669">Up to date</span>')+'</div></div><div class="bwp-stat"><div class="bwp-stat-label">Theme Updates</div><div class="bwp-stat-value">'+(currentWpInstance.themeUpdates>0?'<span style="color:#7c3aed">'+currentWpInstance.themeUpdates+' available</span>':'<span style="color:#059669">Up to date</span>')+'</div></div><div class="bwp-stat"><div class="bwp-stat-label">Path</div><div class="bwp-stat-value" style="font-family:monospace;font-size:12px">'+esc(currentWpInstance.path||"/")+'</div></div><div class="bwp-stat"><div class="bwp-stat-label">Version</div><div class="bwp-stat-value">WordPress '+esc(currentWpInstance.version)+'</div></div></div>';if(currentWpInstance.availableUpdate) ovHtml+='<div class="bwp-msg info" style="margin-top:12px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;vertical-align:middle;margin-right:6px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Core update available: WordPress '+esc(currentWpInstance.availableUpdate)+'</div>';ovHtml+='</div></div>';ovTab.innerHTML=ovHtml;/* Bind login button */$("bwpDetailLogin").addEventListener("click",function(){bwpAutoLogin(id);});/* Bind tabs */wpPage.querySelectorAll(".bwp-tab").forEach(function(tab){tab.addEventListener("click",function(){wpPage.querySelectorAll(".bwp-tab").forEach(function(t){t.classList.remove("active");});wpPage.querySelectorAll(".bwp-tab-content").forEach(function(c){c.classList.remove("active");});tab.classList.add("active");var tabName=tab.getAttribute("data-tab");var target=$("bwpTab"+tabName.charAt(0).toUpperCase()+tabName.slice(1));if(target){target.classList.add("active");if(!target.getAttribute("data-loaded")){if(tabName==="plugins") bwpLoadPlugins();else if(tabName==="themes") bwpLoadThemes();else if(tabName==="security") bwpLoadSecurity();}}});});/* Back button */$("bwpInlineBack").addEventListener("click",function(){currentWpInstance=null;wpPage.innerHTML="";buildWpPaneInto(wpPage);loadWpInstances();});bwpLoadPlugins();}
/* bindWpDetailPanel removed — detail now renders inline */
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
function buildDnsPane(){var pane=$("bt-pane-dns");if(!pane) return;pane.innerHTML='<div class="bt-card"><div class="bt-card-head"><div class="bt-card-head-left"><div class="bt-icon-circle" style="background:#7c3aed"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div><div><h5>DNS Management</h5><p id="bt-dns-subtitle">Select a domain to manage DNS records</p></div></div></div><div id="bt-dns-body"><div id="bt-dns-domain-list" class="bt-list"><div class="bt-loading"><div class="bt-spinner"></div><span>Loading domains...</span></div></div><div id="bt-dns-records-view" style="display:none"><div class="bt-dns-toolbar" id="bt-dns-toolbar"></div><div class="bt-dns-filter-bar" id="bt-dns-filter-bar"></div><div class="bt-list" id="bt-dns-records-list"></div></div></div></div>';}
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
    loadResourceStats();
}

/* ─── Resource Stats (CPU, Memory, I/O, Processes) ─── */
function loadResourceStats(){
    var container=$("bt-hero-res");if(!container) return;
    post({action:"cpanel_resource_stats"},function(r){
        if(!r.success||!r.stats){container.innerHTML='<div class="bt-res-loading" style="font-size:10px;color:#9ca3af">Resource stats unavailable</div>';return;}
        var s=r.stats;var bars=[];
        if(s.cpu) bars.push({label:"CPU",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>',used:s.cpu.used,max:s.cpu.max,unit:"%"});
        if(s.mem){var memU=parseFloat(s.mem.used)||0;var memM=parseFloat(s.mem.max)||0;var memUnit="MB";if(memM>1024||memU>1024){memU=(memU/1024);memM=memM>0?(memM/1024):0;memUnit="GB";}bars.push({label:"Memory",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><line x1="6" y1="6" x2="6" y2="18"/><line x1="10" y1="6" x2="10" y2="18"/><line x1="14" y1="6" x2="14" y2="18"/><line x1="18" y1="6" x2="18" y2="18"/></svg>',used:memU,max:memM,unit:memUnit,decimals:memUnit==="GB"?1:0});}
        if(s.io){var ioU=parseFloat(s.io.used)||0;var ioM=parseFloat(s.io.max)||0;var ioUnit="KB/s";if(ioM>=1024||ioU>=1024){ioU=ioU/1024;ioM=ioM>0?ioM/1024:0;ioUnit="MB/s";}bars.push({label:"I/O",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',used:ioU,max:ioM,unit:ioUnit,decimals:ioUnit==="MB/s"?1:0});}
        if(s.nproc) bars.push({label:"Processes",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',used:s.nproc.used,max:s.nproc.max,unit:""});
        if(s.ep) bars.push({label:"Entry Proc",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>',used:s.ep.used,max:s.ep.max,unit:""});
        if(s.iops) bars.push({label:"IOPS",icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',used:s.iops.used,max:s.iops.max,unit:""});
        if(!bars.length){container.innerHTML='<div class="bt-res-loading" style="font-size:10px;color:#9ca3af">No resource data available</div>';return;}
        var html="";
        bars.forEach(function(b){
            var used=parseFloat(b.used)||0;var max=parseFloat(b.max)||0;
            var pct=max>0?Math.min(100,Math.round(used/max*100)):0;
            var dec=b.decimals||0;
            var usedStr=dec>0?used.toFixed(dec):Math.round(used);
            var maxStr=max>0?(dec>0?max.toFixed(dec):Math.round(max)):"∞";
            var valTxt=usedStr+(b.unit?" "+b.unit:"")+" / "+(max>0?maxStr+(b.unit?" "+b.unit:""):"Unlimited");
            var colorClass=pct>90?"red":pct>70?"yellow":pct>40?"blue":"green";
            html+='<div class="bt-res-bar"><div class="bt-res-bar-head"><span class="bt-res-bar-label">'+b.icon+' '+esc(b.label)+'</span><span class="bt-res-bar-val">'+esc(valTxt)+'</span></div><div class="bt-res-bar-track"><div class="bt-res-bar-fill '+colorClass+'" style="width:'+pct+'%"></div></div></div>';
        });
        container.innerHTML=html;
    });
}

/* ─── CSS Part 9: File Manager ─── */
function injectStyles9(){
    if(document.getElementById("bt-injected-styles9")) return;
    var s=document.createElement("style");s.id="bt-injected-styles9";
    s.textContent=[
/* File Manager Layout */
'.fm-wrap{display:flex;flex-direction:column;height:100%;min-height:500px}',
'.fm-toolbar{display:flex;align-items:center;gap:6px;padding:10px 16px;border-bottom:1px solid var(--border-color,#e5e7eb);flex-wrap:wrap;background:var(--card-bg,#fff);border-radius:12px 12px 0 0}',
'.fm-toolbar-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 10px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border-color,#e5e7eb);background:var(--card-bg,#fff);color:var(--heading-color,#374151);transition:all .12s;white-space:nowrap}',
'.fm-toolbar-btn:hover{border-color:#0a5ed3;color:#0a5ed3;background:rgba(10,94,211,.04)}',
'.fm-toolbar-btn:disabled{opacity:.4;cursor:not-allowed;pointer-events:none}',
'.fm-toolbar-btn svg{width:14px;height:14px;flex-shrink:0}',
'.fm-toolbar-sep{width:1px;height:24px;background:var(--border-color,#e5e7eb);margin:0 2px;flex-shrink:0}',
'.fm-toolbar-search{display:flex;align-items:center;gap:6px;margin-left:auto}',
'.fm-toolbar-search input{padding:6px 10px;border:1px solid var(--border-color,#d1d5db);border-radius:7px;font-size:12px;width:160px;outline:none;background:var(--input-bg,#fff);color:var(--heading-color,#111827);transition:border-color .15s}',
'.fm-toolbar-search input:focus{border-color:#0a5ed3;box-shadow:0 0 0 2px rgba(10,94,211,.1)}',
/* Breadcrumb */
'.fm-breadcrumb{display:flex;align-items:center;gap:4px;padding:8px 16px;font-size:13px;color:var(--text-muted,#6b7280);border-bottom:1px solid var(--border-color,#f3f4f6);flex-wrap:wrap;background:var(--input-bg,#f9fafb)}',
'.fm-breadcrumb a{color:#0a5ed3;text-decoration:none;cursor:pointer;font-weight:500;padding:2px 4px;border-radius:4px;transition:background .12s}',
'.fm-breadcrumb a:hover{background:rgba(10,94,211,.08)}',
'.fm-breadcrumb .fm-bc-sep{color:var(--border-color,#d1d5db);font-size:11px}',
'.fm-breadcrumb .fm-bc-current{font-weight:600;color:var(--heading-color,#111827)}',
/* File list */
'.fm-list-wrap{flex:1;overflow:auto;background:var(--card-bg,#fff);border-radius:0 0 12px 12px;position:relative}',
'.fm-table{width:100%;border-collapse:collapse;font-size:13px}',
'.fm-table th{position:sticky;top:0;background:var(--input-bg,#f8fafc);padding:8px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--text-muted,#9ca3af);border-bottom:1px solid var(--border-color,#e5e7eb);cursor:pointer;user-select:none;white-space:nowrap;z-index:2}',
'.fm-table th:hover{color:var(--heading-color,#374151)}',
'.fm-table th .fm-sort{display:inline-block;margin-left:3px;opacity:.3;font-size:10px}.fm-table th.sorted .fm-sort{opacity:1;color:#0a5ed3}',
'.fm-table td{padding:6px 12px;border-bottom:1px solid var(--border-color,#f3f4f6);color:var(--heading-color,#374151);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px}',
'.fm-table tr{transition:background .08s}.fm-table tr:hover{background:rgba(10,94,211,.02)}',
'.fm-table tr.selected{background:rgba(10,94,211,.06)}',
'.fm-table .fm-name-cell{display:flex;align-items:center;gap:8px;cursor:pointer;min-width:0}',
'.fm-table .fm-name-cell .fm-icon{width:20px;height:20px;flex-shrink:0;display:flex;align-items:center;justify-content:center}',
'.fm-table .fm-name-cell .fm-fname{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500}',
'.fm-table .fm-name-cell:hover .fm-fname{color:#0a5ed3}',
'.fm-table .fm-size{color:var(--text-muted,#6b7280);font-size:12px;font-family:"SFMono-Regular",Consolas,monospace}',
'.fm-table .fm-perms{font-family:"SFMono-Regular",Consolas,monospace;font-size:12px;color:var(--text-muted,#6b7280)}',
'.fm-table .fm-date{color:var(--text-muted,#6b7280);font-size:12px}',
'.fm-table .fm-check{width:16px;height:16px;accent-color:#0a5ed3;cursor:pointer}',
/* Context menu */
'.fm-ctx{position:fixed;z-index:100001;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e5e7eb);border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.15);padding:4px;min-width:180px;animation:btFadeIn .12s}',
'.fm-ctx-item{display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:13px;font-weight:500;color:var(--heading-color,#374151);cursor:pointer;border-radius:6px;transition:background .1s}',
'.fm-ctx-item:hover{background:var(--input-bg,#f3f4f6)}',
'.fm-ctx-item.danger{color:#ef4444}.fm-ctx-item.danger:hover{background:rgba(239,68,68,.06)}',
'.fm-ctx-item svg{width:14px;height:14px;flex-shrink:0}',
'.fm-ctx-sep{height:1px;background:var(--border-color,#f3f4f6);margin:4px 0}',
/* Drop zone */
'.fm-dropzone{position:absolute;inset:0;background:rgba(10,94,211,.06);border:2px dashed #0a5ed3;border-radius:12px;display:none;align-items:center;justify-content:center;z-index:10;pointer-events:none}',
'.fm-dropzone.active{display:flex}',
'.fm-dropzone-text{font-size:16px;font-weight:600;color:#0a5ed3}',
/* Upload progress */
'.fm-upload-bar{padding:8px 16px;background:rgba(10,94,211,.04);border-bottom:1px solid var(--border-color,#e5e7eb);display:none;align-items:center;gap:10px;font-size:12px;color:var(--heading-color,#374151)}',
'.fm-upload-bar.active{display:flex}',
'.fm-upload-progress{flex:1;height:4px;background:var(--border-color,#e5e7eb);border-radius:2px;overflow:hidden}',
'.fm-upload-progress-fill{height:100%;background:#0a5ed3;border-radius:2px;transition:width .2s}',
/* Responsive */
'@media(max-width:768px){.fm-toolbar{gap:4px;padding:8px 10px}.fm-toolbar-btn span{display:none}.fm-toolbar-search input{width:100px}.fm-table .fm-perms,.fm-table .fm-date,.fm-table th:nth-child(4),.fm-table th:nth-child(5){display:none}}',
'@media(max-width:480px){.fm-table .fm-size,.fm-table th:nth-child(3){display:none}}',
    ].join('\n');
    document.head.appendChild(s);
}

/* ─── File Manager State ─── */
var fmState={dir:"/",homedir:"",files:[],selected:[],sortCol:"name",sortAsc:true,history:[],historyIdx:-1,clipboard:null,clipOp:null};

/* ─── Build File Manager Page ─── */
function buildFileManagerPageInto(container){
    var card=document.createElement("div");
    card.className="bt-card";
    card.style.display="flex";card.style.flexDirection="column";card.style.minHeight="500px";

    /* Toolbar */
    var tb=document.createElement("div");tb.className="fm-toolbar";tb.id="fm-toolbar";
    tb.innerHTML='<button class="fm-toolbar-btn" data-fm="newfile" title="New File"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg><span>New File</span></button>'
    +'<button class="fm-toolbar-btn" data-fm="newfolder" title="New Folder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg><span>New Folder</span></button>'
    +'<button class="fm-toolbar-btn" data-fm="upload" title="Upload"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><span>Upload</span></button>'
    +'<div class="fm-toolbar-sep"></div>'
    +'<button class="fm-toolbar-btn" data-fm="rename" title="Rename" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg><span>Rename</span></button>'
    +'<button class="fm-toolbar-btn" data-fm="copy" title="Copy" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span>Copy</span></button>'
    +'<button class="fm-toolbar-btn" data-fm="move" title="Move" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="5 9 2 12 5 15"/><polyline points="9 5 12 2 15 5"/><polyline points="15 19 12 22 9 19"/><polyline points="19 9 22 12 19 15"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="22"/></svg><span>Move</span></button>'
    +'<button class="fm-toolbar-btn" data-fm="delete" title="Delete" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg><span>Delete</span></button>'
    +'<div class="fm-toolbar-sep"></div>'
    +'<button class="fm-toolbar-btn" data-fm="compress" title="Compress" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg><span>Compress</span></button>'
    +'<button class="fm-toolbar-btn" data-fm="extract" title="Extract" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><span>Extract</span></button>'
    +'<button class="fm-toolbar-btn" data-fm="perms" title="Permissions" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><span>Permissions</span></button>'
    +'<div class="fm-toolbar-sep"></div>'
    +'<button class="fm-toolbar-btn" data-fm="refresh" title="Refresh"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg></button>'
    +'<div class="fm-toolbar-search"><input type="text" id="fm-search-input" placeholder="Search files..."><button class="fm-toolbar-btn" data-fm="search" title="Search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button></div>';
    card.appendChild(tb);

    /* Upload progress bar */
    var upBar=document.createElement("div");upBar.className="fm-upload-bar";upBar.id="fm-upload-bar";
    upBar.innerHTML='<span id="fm-upload-name">Uploading...</span><div class="fm-upload-progress"><div class="fm-upload-progress-fill" id="fm-upload-fill" style="width:0%"></div></div><span id="fm-upload-pct">0%</span>';
    card.appendChild(upBar);

    /* Breadcrumb */
    var bc=document.createElement("div");bc.className="fm-breadcrumb";bc.id="fm-breadcrumb";
    card.appendChild(bc);

    /* File list */
    var listWrap=document.createElement("div");listWrap.className="fm-list-wrap";listWrap.id="fm-list-wrap";
    listWrap.innerHTML='<div class="fm-dropzone" id="fm-dropzone"><span class="fm-dropzone-text">Drop files here to upload</span></div><div class="bt-loading" id="fm-loading"><div class="bt-spinner"></div>Loading files...</div>';
    card.appendChild(listWrap);

    container.appendChild(card);

    /* Hidden file input for upload */
    var fileInput=document.createElement("input");fileInput.type="file";fileInput.id="fm-file-input";fileInput.multiple=true;fileInput.style.display="none";
    container.appendChild(fileInput);

    /* Bind events */
    fmBindToolbar();
    fmBindDragDrop();
    fmLoadDir("/public_html");
}

/* ─── FM: Load Directory ─── */
function fmLoadDir(dir){
    fmState.dir=dir;fmState.selected=[];
    fmUpdateToolbarState();
    var listWrap=$("fm-list-wrap");
    if(listWrap) listWrap.innerHTML='<div class="fm-dropzone" id="fm-dropzone"><span class="fm-dropzone-text">Drop files here to upload</span></div><div class="bt-loading"><div class="bt-spinner"></div>Loading files...</div>';
    post({action:"fm_list",dir:dir},function(r){
        if(!r.success){if(listWrap) listWrap.innerHTML='<div class="bt-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'+esc(r.message||"Failed to load")+'</div>';return;}
        if(r.homedir) fmState.homedir=r.homedir;
        fmState.files=r.files||[];
        fmState.selected=[];
        fmRenderBreadcrumb();
        fmRenderFiles();
        fmBindDragDrop();
    });
}

/* ─── FM: Render Breadcrumb ─── */
function fmRenderBreadcrumb(){
    var bc=$("fm-breadcrumb");if(!bc) return;
    var parts=fmState.dir.replace(/\/+$/,"").split("/").filter(Boolean);
    var html='<a data-fmdir="/" title="Root"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></a>';
    var path="";
    for(var i=0;i<parts.length;i++){
        path+="/"+parts[i];
        html+='<span class="fm-bc-sep">/</span>';
        if(i===parts.length-1) html+='<span class="fm-bc-current">'+esc(parts[i])+'</span>';
        else html+='<a data-fmdir="'+esc(path)+'">'+esc(parts[i])+'</a>';
    }
    bc.innerHTML=html;
    bc.querySelectorAll("a[data-fmdir]").forEach(function(a){
        a.addEventListener("click",function(e){e.preventDefault();fmLoadDir(this.getAttribute("data-fmdir"));});
    });
}

/* ─── FM: Render File List ─── */
function fmRenderFiles(){
    var listWrap=$("fm-list-wrap");if(!listWrap) return;
    var files=fmSortFiles(fmState.files);
    var html='<div class="fm-dropzone" id="fm-dropzone"><span class="fm-dropzone-text">Drop files here to upload</span></div>';
    if(!files.length){
        html+='<div class="bt-empty" style="padding:60px 22px"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>This folder is empty</div>';
        listWrap.innerHTML=html;return;
    }
    html+='<table class="fm-table"><thead><tr>';
    html+='<th style="width:30px"><input type="checkbox" class="fm-check" id="fm-check-all"></th>';
    var cols=[{id:"name",label:"Name",w:""},{id:"size",label:"Size",w:"90px"},{id:"perms",label:"Permissions",w:"100px"},{id:"mtime",label:"Modified",w:"150px"}];
    cols.forEach(function(c){
        var sorted=fmState.sortCol===c.id;
        var arrow=sorted?(fmState.sortAsc?"\u25B2":"\u25BC"):"\u25B2";
        html+='<th data-fmsort="'+c.id+'"'+(c.w?' style="width:'+c.w+'"':'')+(sorted?' class="sorted"':'')+'>'+c.label+'<span class="fm-sort">'+arrow+'</span></th>';
    });
    html+='</tr></thead><tbody>';
    /* Parent dir link */
    if(fmState.dir!=="/"){
        html+='<tr class="fm-parent-row"><td></td><td colspan="4"><div class="fm-name-cell" data-fmdir="'+esc(fmParentDir(fmState.dir))+'"><div class="fm-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></div><div class="fm-fname">..</div></div></td></tr>';
    }
    files.forEach(function(f,idx){
        var isDir=f.type==="dir";
        var sel=fmState.selected.indexOf(f.path)!==-1;
        html+='<tr data-fmpath="'+esc(f.path)+'" data-fmtype="'+f.type+'"'+(sel?' class="selected"':'')+'>';
        html+='<td><input type="checkbox" class="fm-check fm-item-check" data-fmpath="'+esc(f.path)+'"'+(sel?' checked':'')+'></td>';
        html+='<td><div class="fm-name-cell" data-fmopen="'+esc(f.path)+'" data-fmtype="'+f.type+'"><div class="fm-icon">'+fmFileIcon(f)+'</div><div class="fm-fname">'+esc(f.name)+'</div></div></td>';
        html+='<td class="fm-size">'+(isDir?"-":fmFormatSize(f.size))+'</td>';
        html+='<td class="fm-perms">'+esc(f.perms||"")+'</td>';
        html+='<td class="fm-date">'+esc(fmFormatDate(f.mtime))+'</td>';
        html+='</tr>';
    });
    html+='</tbody></table>';
    listWrap.innerHTML=html;

    /* Bind events */
    listWrap.querySelectorAll(".fm-name-cell[data-fmopen]").forEach(function(el){
        el.addEventListener("click",function(){
            var p=this.getAttribute("data-fmopen");var t=this.getAttribute("data-fmtype");
            if(t==="dir") fmLoadDir(p);
            else fmOpenFile(p);
        });
    });
    listWrap.querySelectorAll(".fm-name-cell[data-fmdir]").forEach(function(el){
        el.addEventListener("click",function(){fmLoadDir(this.getAttribute("data-fmdir"));});
    });
    /* Checkboxes */
    var checkAll=$("fm-check-all");
    if(checkAll) checkAll.addEventListener("change",function(){
        var checked=this.checked;
        listWrap.querySelectorAll(".fm-item-check").forEach(function(cb){
            cb.checked=checked;
            var p=cb.getAttribute("data-fmpath");
            var tr=cb.closest("tr");
            if(checked){if(fmState.selected.indexOf(p)===-1) fmState.selected.push(p);if(tr) tr.classList.add("selected");}
            else{fmState.selected=fmState.selected.filter(function(s){return s!==p;});if(tr) tr.classList.remove("selected");}
        });
        fmUpdateToolbarState();
    });
    listWrap.querySelectorAll(".fm-item-check").forEach(function(cb){
        cb.addEventListener("change",function(){
            var p=this.getAttribute("data-fmpath");var tr=this.closest("tr");
            if(this.checked){if(fmState.selected.indexOf(p)===-1) fmState.selected.push(p);if(tr) tr.classList.add("selected");}
            else{fmState.selected=fmState.selected.filter(function(s){return s!==p;});if(tr) tr.classList.remove("selected");}
            fmUpdateToolbarState();
        });
    });
    /* Sort headers */
    listWrap.querySelectorAll("th[data-fmsort]").forEach(function(th){
        th.addEventListener("click",function(){
            var col=this.getAttribute("data-fmsort");
            if(fmState.sortCol===col) fmState.sortAsc=!fmState.sortAsc;
            else{fmState.sortCol=col;fmState.sortAsc=true;}
            fmRenderFiles();
        });
    });
    /* Context menu */
    listWrap.querySelectorAll("tr[data-fmpath]").forEach(function(tr){
        tr.addEventListener("contextmenu",function(e){
            e.preventDefault();
            var p=tr.getAttribute("data-fmpath");var t=tr.getAttribute("data-fmtype");
            if(fmState.selected.indexOf(p)===-1){fmState.selected=[p];fmRenderFiles();}
            fmShowContextMenu(e.clientX,e.clientY,p,t);
        });
    });
}

/* ─── FM: Helpers ─── */
function fmParentDir(dir){var p=dir.replace(/\/+$/,"");var i=p.lastIndexOf("/");return i<=0?"/":p.substring(0,i);}
function fmFormatSize(bytes){if(!bytes||bytes<=0) return "0 B";var u=["B","KB","MB","GB"];var i=0;var b=bytes;while(b>=1024&&i<u.length-1){b/=1024;i++;}return b.toFixed(i>0?1:0)+" "+u[i];}
function fmFormatDate(d){if(!d) return "";try{var dt=new Date(typeof d==="number"?d*1000:d);if(isNaN(dt.getTime())) return String(d);var m=dt.getMonth()+1;var day=dt.getDate();var y=dt.getFullYear();var h=dt.getHours();var min=dt.getMinutes();return y+"-"+(m<10?"0":"")+m+"-"+(day<10?"0":"")+day+" "+(h<10?"0":"")+h+":"+(min<10?"0":"")+min;}catch(e){return String(d);}}
function fmSortFiles(files){
    var dirs=files.filter(function(f){return f.type==="dir";});
    var fls=files.filter(function(f){return f.type!=="dir";});
    var cmp=function(a,b){
        var va,vb;
        if(fmState.sortCol==="size"){va=a.size||0;vb=b.size||0;}
        else if(fmState.sortCol==="mtime"){va=a.mtime||"";vb=b.mtime||"";}
        else if(fmState.sortCol==="perms"){va=a.perms||"";vb=b.perms||"";}
        else{va=(a.name||"").toLowerCase();vb=(b.name||"").toLowerCase();}
        if(va<vb) return fmState.sortAsc?-1:1;
        if(va>vb) return fmState.sortAsc?1:-1;
        return 0;
    };
    dirs.sort(cmp);fls.sort(cmp);
    return dirs.concat(fls);
}
function fmFileIcon(f){
    if(f.type==="dir") return '<svg width="18" height="18" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
    var ext=(f.name||"").split(".").pop().toLowerCase();
    var codeExts=["php","js","ts","jsx","tsx","css","scss","html","htm","xml","json","yml","yaml","py","rb","sh","bash","sql","vue","svelte","go","rs","java","c","cpp","h","md","txt","conf","cfg","ini","env","htaccess","log"];
    var imgExts=["jpg","jpeg","png","gif","svg","webp","ico","bmp"];
    var archExts=["zip","tar","gz","bz2","rar","7z","tgz"];
    if(codeExts.indexOf(ext)!==-1) return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0a5ed3" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>';
    if(imgExts.indexOf(ext)!==-1) return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';
    if(archExts.indexOf(ext)!==-1) return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
}
function fmUpdateToolbarState(){
    var sel=fmState.selected.length;
    var tb=$("fm-toolbar");if(!tb) return;
    tb.querySelector('[data-fm="rename"]').disabled=sel!==1;
    tb.querySelector('[data-fm="copy"]').disabled=sel<1;
    tb.querySelector('[data-fm="move"]').disabled=sel<1;
    tb.querySelector('[data-fm="delete"]').disabled=sel<1;
    tb.querySelector('[data-fm="compress"]').disabled=sel<1;
    tb.querySelector('[data-fm="perms"]').disabled=sel!==1;
    /* Extract: only if single archive selected */
    var canExtract=false;
    if(sel===1){var ext=(fmState.selected[0]||"").split(".").pop().toLowerCase();canExtract=["zip","tar","gz","bz2","tgz","rar","7z"].indexOf(ext)!==-1;}
    tb.querySelector('[data-fm="extract"]').disabled=!canExtract;
}

/* ─── FM: Toolbar Binding ─── */
function fmBindToolbar(){
    var tb=$("fm-toolbar");if(!tb) return;
    tb.querySelectorAll(".fm-toolbar-btn[data-fm]").forEach(function(btn){
        btn.addEventListener("click",function(){
            var act=this.getAttribute("data-fm");
            if(act==="newfile") fmPrompt("New File","Enter file name:","",function(name){if(name) fmCreateFile(name);});
            else if(act==="newfolder") fmPrompt("New Folder","Enter folder name:","",function(name){if(name) fmCreateFolder(name);});
            else if(act==="upload"){var fi=$("fm-file-input");if(fi){fi.value="";fi.click();}}
            else if(act==="rename"&&fmState.selected.length===1){var old=fmState.selected[0].split("/").pop();fmPrompt("Rename","Enter new name:",old,function(name){if(name&&name!==old) fmRename(fmState.selected[0],name);});}
            else if(act==="copy"&&fmState.selected.length>0) fmPromptDest("Copy","Copy to directory:",fmState.dir,function(dest){if(dest) fmCopyMove("fm_copy",fmState.selected,dest);});
            else if(act==="move"&&fmState.selected.length>0) fmPromptDest("Move","Move to directory:",fmState.dir,function(dest){if(dest) fmCopyMove("fm_move",fmState.selected,dest);});
            else if(act==="delete"&&fmState.selected.length>0) fmConfirmDelete();
            else if(act==="compress"&&fmState.selected.length>0) fmPrompt("Compress","Archive name (e.g. archive.zip):","archive.zip",function(name){if(name) fmCompress(name);});
            else if(act==="extract"&&fmState.selected.length===1) fmPromptDest("Extract","Extract to directory:",fmState.dir,function(dest){if(dest) fmExtract(fmState.selected[0],dest);});
            else if(act==="perms"&&fmState.selected.length===1) fmPromptPerms();
            else if(act==="refresh") fmLoadDir(fmState.dir);
            else if(act==="search") fmDoSearch();
        });
    });
    /* Upload file input */
    var fi=$("fm-file-input");
    if(fi) fi.addEventListener("change",function(){if(this.files&&this.files.length) fmUploadFiles(this.files);});
    /* Search on Enter */
    var si=$("fm-search-input");
    if(si) si.addEventListener("keydown",function(e){if(e.key==="Enter") fmDoSearch();});
}

/* ─── FM: Drag & Drop ─── */
function fmBindDragDrop(){
    var lw=$("fm-list-wrap");if(!lw) return;
    var dz=$("fm-dropzone");
    var counter=0;
    lw.addEventListener("dragenter",function(e){e.preventDefault();counter++;if(dz) dz.classList.add("active");});
    lw.addEventListener("dragleave",function(e){e.preventDefault();counter--;if(counter<=0){counter=0;if(dz) dz.classList.remove("active");}});
    lw.addEventListener("dragover",function(e){e.preventDefault();});
    lw.addEventListener("drop",function(e){
        e.preventDefault();counter=0;if(dz) dz.classList.remove("active");
        if(e.dataTransfer&&e.dataTransfer.files&&e.dataTransfer.files.length) fmUploadFiles(e.dataTransfer.files);
    });
}

/* ─── CodeMirror CDN Loader ─── */
var _cmLoaded=false;
var _cmCallbacks=[];
function fmLoadCodeMirror(cb){
    if(_cmLoaded&&window.CodeMirror){cb();return;}
    _cmCallbacks.push(cb);
    if(_cmCallbacks.length>1) return; /* already loading */
    var cdnBase="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.18/";
    /* Load CSS */
    var link=document.createElement("link");link.rel="stylesheet";link.href=cdnBase+"codemirror.min.css";document.head.appendChild(link);
    /* Load main JS */
    var sc=document.createElement("script");sc.src=cdnBase+"codemirror.min.js";
    sc.onload=function(){
        /* Load addons and modes */
        var assets=[
            "addon/search/search.min.js","addon/search/searchcursor.min.js","addon/search/jump-to-line.min.js",
            "addon/dialog/dialog.min.js","addon/edit/matchbrackets.min.js","addon/edit/closebrackets.min.js",
            "addon/fold/foldcode.min.js","addon/fold/foldgutter.min.js","addon/fold/brace-fold.min.js",
            "addon/fold/comment-fold.min.js","addon/selection/active-line.min.js","addon/comment/comment.min.js",
            "mode/javascript/javascript.min.js","mode/xml/xml.min.js","mode/css/css.min.js",
            "mode/htmlmixed/htmlmixed.min.js","mode/php/php.min.js","mode/python/python.min.js",
            "mode/ruby/ruby.min.js","mode/sql/sql.min.js","mode/yaml/yaml.min.js",
            "mode/shell/shell.min.js","mode/markdown/markdown.min.js","mode/clike/clike.min.js"
        ];
        var cssList=["addon/dialog/dialog.min.css","addon/fold/foldgutter.min.css"];
        cssList.forEach(function(c){var l2=document.createElement("link");l2.rel="stylesheet";l2.href=cdnBase+c;document.head.appendChild(l2);});
        var loaded=0;var total=assets.length;
        if(total===0){_cmLoaded=true;_cmCallbacks.forEach(function(fn){fn();});_cmCallbacks=[];return;}
        assets.forEach(function(a){
            var s2=document.createElement("script");s2.src=cdnBase+a;
            s2.onload=s2.onerror=function(){loaded++;if(loaded>=total){_cmLoaded=true;_cmCallbacks.forEach(function(fn){fn();});_cmCallbacks=[];}};
            document.head.appendChild(s2);
        });
    };
    sc.onerror=function(){_cmCallbacks.forEach(function(fn){fn();});_cmCallbacks=[];};
    document.head.appendChild(sc);
}

function fmOpenFile(filePath){
    /* Remove any existing editor modal */
    var old=document.getElementById("fm-editor-overlay");if(old&&old.parentNode) old.parentNode.removeChild(old);

    /* Detect CodeMirror mode from extension */
    var ext=(filePath||"").split(".").pop().toLowerCase();
    var modeMap={
        js:"javascript",jsx:"javascript",mjs:"javascript",ts:{name:"javascript",typescript:true},tsx:{name:"javascript",typescript:true},
        php:"application/x-httpd-php",phtml:"application/x-httpd-php",
        py:"python",rb:"ruby",css:"css",scss:"text/x-scss",less:"text/x-less",
        html:"htmlmixed",htm:"htmlmixed",tpl:"htmlmixed",blade:"htmlmixed",
        xml:"xml",svg:"xml",xsl:"xml",
        json:{name:"javascript",json:true},
        yml:"yaml",yaml:"yaml",
        sh:"shell",bash:"shell",zsh:"shell",
        sql:"sql",
        md:"markdown",
        c:"text/x-csrc",cpp:"text/x-c++src",h:"text/x-csrc",java:"text/x-java",cs:"text/x-csharp",
        txt:null,conf:null,ini:null,log:null,htaccess:null,env:null,gitignore:null
    };
    var cmMode=modeMap[ext]!==undefined?modeMap[ext]:"javascript";
    var langLabel=typeof cmMode==="string"?(cmMode||"text"):(cmMode&&cmMode.name?cmMode.name:"text");
    var fileName=filePath.split("/").pop();

    /* Build overlay */
    var overlay=document.createElement("div");overlay.className="bt-overlay";overlay.id="fm-editor-overlay";
    overlay.style.zIndex="100002";overlay.style.padding="16px";

    var modal=document.createElement("div");
    modal.style.cssText="background:var(--card-bg,#fff);border-radius:14px;width:100%;max-width:1200px;height:92vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,.25);animation:btSlideUp .25s;overflow:hidden";

    /* Header */
    var head=document.createElement("div");
    head.style.cssText="display:flex;align-items:center;gap:8px;padding:8px 14px;border-bottom:1px solid var(--border-color,#e5e7eb);flex-shrink:0;background:var(--input-bg,#f9fafb)";
    head.innerHTML='<button class="fm-toolbar-btn" id="fm-ed-close" title="Close (Esc)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
    +'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="flex-shrink:0;color:var(--text-muted,#6b7280)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'
    +'<div style="flex:1;font-size:13px;font-weight:600;color:var(--heading-color,#111827);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" id="fm-ed-title"></div>'
    +'<span style="font-size:11px;padding:2px 8px;border-radius:4px;background:rgba(10,94,211,.08);color:#0a5ed3;font-weight:600;text-transform:uppercase" id="fm-ed-lang"></span>'
    +'<div class="fm-toolbar-sep"></div>'
    +'<button class="fm-toolbar-btn" id="fm-ed-find" title="Find & Replace (Ctrl+F)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><span>Find</span></button>'
    +'<button class="fm-toolbar-btn" id="fm-ed-goto" title="Go to Line (Ctrl+G)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><polyline points="10 3 8 6 6 3"/></svg><span>Go to</span></button>'
    +'<button class="fm-toolbar-btn" id="fm-ed-copy" title="Copy All"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span>Copy</span></button>'
    +'<button class="fm-toolbar-btn" id="fm-ed-save" style="background:#0a5ed3;color:#fff;border-color:#0a5ed3"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg><span>Save</span></button>'
    +'<button class="fm-toolbar-btn" id="fm-ed-dl" title="Download"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></button>';
    modal.appendChild(head);

    /* Editor container */
    var edWrap=document.createElement("div");edWrap.id="fm-ed-cm-wrap";
    edWrap.style.cssText="flex:1;overflow:hidden;position:relative";
    edWrap.innerHTML='<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted,#9ca3af);font-size:13px"><div class="bt-spinner" style="margin-right:10px"></div>Loading editor...</div>';
    modal.appendChild(edWrap);

    /* Footer status */
    var foot=document.createElement("div");
    foot.style.cssText="padding:5px 14px;font-size:11px;color:var(--text-muted,#9ca3af);border-top:1px solid var(--border-color,#f3f4f6);background:var(--input-bg,#f9fafb);display:flex;align-items:center;gap:12px;flex-shrink:0";
    foot.innerHTML='<span id="fm-ed-fpath" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>'
    +'<span style="flex:1"></span>'
    +'<span id="fm-ed-cursor" style="font-family:SFMono-Regular,Consolas,monospace">Ln 1, Col 1</span>'
    +'<span id="fm-ed-fsize"></span>'
    +'<span id="fm-ed-msg"></span>';
    modal.appendChild(foot);

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    /* Set title, lang, path */
    var titleEl=overlay.querySelector("#fm-ed-title");if(titleEl) titleEl.textContent=fileName;
    var langEl=overlay.querySelector("#fm-ed-lang");if(langEl) langEl.textContent=langLabel.replace("application/x-httpd-","");
    var fpathEl=overlay.querySelector("#fm-ed-fpath");if(fpathEl){fpathEl.textContent=filePath;fpathEl.dataset.path=filePath;}

    var fmEditorDirty=false;
    var cmEditor=null;

    /* Close handler */
    function closeEditor(){
        document.removeEventListener("keydown",escHandler);
        if(overlay.parentNode) overlay.parentNode.removeChild(overlay);
        if(fmEditorDirty) fmLoadDir(fmState.dir);
    }
    overlay.querySelector("#fm-ed-close").addEventListener("click",closeEditor);
    overlay.addEventListener("click",function(e){if(e.target===overlay) closeEditor();});

    /* Save handler */
    function doSave(){
        if(!cmEditor) return;
        var msgEl=overlay.querySelector("#fm-ed-msg");
        var saveBtn=overlay.querySelector("#fm-ed-save");
        btnLoad(saveBtn,"Saving...");
        if(msgEl){msgEl.textContent="Saving...";msgEl.style.color="";}
        post({action:"fm_save",file:filePath,content:cmEditor.getValue()},function(r){
            btnDone(saveBtn);
            if(msgEl){msgEl.textContent=r.success?"Saved":(r.message||"Failed");msgEl.style.color=r.success?"#059669":"#ef4444";}
            if(r.success){fmEditorDirty=true;cmEditor.markClean();if(msgEl) setTimeout(function(){msgEl.textContent="";},3000);}
        });
    }
    overlay.querySelector("#fm-ed-save").addEventListener("click",doSave);

    /* Download handler */
    overlay.querySelector("#fm-ed-dl").addEventListener("click",function(){fmDownload(filePath);});

    /* Copy all */
    overlay.querySelector("#fm-ed-copy").addEventListener("click",function(){
        if(!cmEditor) return;
        var text=cmEditor.getValue();
        if(navigator.clipboard&&navigator.clipboard.writeText){
            navigator.clipboard.writeText(text).then(function(){fmToast("Copied to clipboard",true);});
        }else{
            var tmp=document.createElement("textarea");tmp.value=text;document.body.appendChild(tmp);tmp.select();document.execCommand("copy");document.body.removeChild(tmp);
            fmToast("Copied to clipboard",true);
        }
    });

    /* Find button */
    overlay.querySelector("#fm-ed-find").addEventListener("click",function(){
        if(cmEditor) cmEditor.execCommand("findPersistent");
    });

    /* Go to line button */
    overlay.querySelector("#fm-ed-goto").addEventListener("click",function(){
        if(!cmEditor) return;
        fmPrompt("Go to Line","Line number:","",function(v){
            var ln=parseInt(v,10);if(isNaN(ln)||ln<1) return;
            cmEditor.setCursor(ln-1,0);cmEditor.scrollIntoView(null,100);cmEditor.focus();
        });
    });

    /* Escape handler */
    function escHandler(e){
        if(e.key==="Escape"){
            /* Let CodeMirror handle Escape for its own dialogs first */
            var dialogs=overlay.querySelectorAll(".CodeMirror-dialog");
            if(dialogs.length>0) return;
            closeEditor();
        }
    }
    document.addEventListener("keydown",escHandler);

    /* Inject CodeMirror overrides for our modal */
    if(!document.getElementById("bt-cm-overrides")){
        var cmStyle=document.createElement("style");cmStyle.id="bt-cm-overrides";
        cmStyle.textContent=[
            '#fm-ed-cm-wrap .CodeMirror{height:100%;font-family:"SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace;font-size:13px;line-height:1.55;border:none}',
            '#fm-ed-cm-wrap .CodeMirror-gutters{background:var(--input-bg,#f8fafc);border-right:1px solid var(--border-color,#e5e7eb)}',
            '#fm-ed-cm-wrap .CodeMirror-linenumber{color:var(--text-muted,#b0b8c4);padding:0 8px 0 4px;min-width:32px}',
            '#fm-ed-cm-wrap .CodeMirror-activeline-background{background:rgba(10,94,211,.04)}',
            '#fm-ed-cm-wrap .CodeMirror-matchingbracket{color:#0a5ed3 !important;font-weight:700;text-decoration:underline}',
            '#fm-ed-cm-wrap .CodeMirror-cursor{border-left:2px solid #0a5ed3}',
            '#fm-ed-cm-wrap .CodeMirror-selected{background:rgba(10,94,211,.15)}',
            '#fm-ed-cm-wrap .CodeMirror-focused .CodeMirror-selected{background:rgba(10,94,211,.2)}',
            '#fm-ed-cm-wrap .CodeMirror-dialog{background:var(--input-bg,#f9fafb);border-bottom:1px solid var(--border-color,#e5e7eb);padding:6px 12px;font-size:13px}',
            '#fm-ed-cm-wrap .CodeMirror-dialog input{border:1px solid var(--border-color,#d1d5db);border-radius:5px;padding:4px 8px;font-size:12px;outline:none;background:var(--card-bg,#fff);color:var(--heading-color,#111827)}',
            '#fm-ed-cm-wrap .CodeMirror-dialog input:focus{border-color:#0a5ed3;box-shadow:0 0 0 2px rgba(10,94,211,.1)}',
            '#fm-ed-cm-wrap .CodeMirror-foldgutter{width:14px}',
            '#fm-ed-cm-wrap .CodeMirror-foldgutter-open:after{content:"\\25BE";color:var(--text-muted,#b0b8c4)}',
            '#fm-ed-cm-wrap .CodeMirror-foldgutter-folded:after{content:"\\25B8";color:#0a5ed3}',
            '#fm-ed-cm-wrap .cm-searching{background:rgba(255,200,0,.4);border-radius:2px}',
            /* Syntax colors */
            '#fm-ed-cm-wrap .cm-keyword{color:#8959a8}',
            '#fm-ed-cm-wrap .cm-def{color:#4271ae}',
            '#fm-ed-cm-wrap .cm-variable{color:#374151}',
            '#fm-ed-cm-wrap .cm-variable-2{color:#c08b30}',
            '#fm-ed-cm-wrap .cm-variable-3{color:#3e999f}',
            '#fm-ed-cm-wrap .cm-type{color:#3e999f}',
            '#fm-ed-cm-wrap .cm-property{color:#374151}',
            '#fm-ed-cm-wrap .cm-operator{color:#3e999f}',
            '#fm-ed-cm-wrap .cm-number{color:#f5871f}',
            '#fm-ed-cm-wrap .cm-string{color:#718c00}',
            '#fm-ed-cm-wrap .cm-string-2{color:#f5871f}',
            '#fm-ed-cm-wrap .cm-comment{color:#8e908c;font-style:italic}',
            '#fm-ed-cm-wrap .cm-atom{color:#8959a8}',
            '#fm-ed-cm-wrap .cm-tag{color:#c82829}',
            '#fm-ed-cm-wrap .cm-attribute{color:#f5871f}',
            '#fm-ed-cm-wrap .cm-qualifier{color:#4271ae}',
            '#fm-ed-cm-wrap .cm-builtin{color:#4271ae}',
            '#fm-ed-cm-wrap .cm-meta{color:#8e908c}',
            '#fm-ed-cm-wrap .cm-bracket{color:#374151}',
            '#fm-ed-cm-wrap .cm-header{color:#c82829;font-weight:700}',
            '#fm-ed-cm-wrap .cm-link{color:#4271ae;text-decoration:underline}',
        ].join('\n');
        document.head.appendChild(cmStyle);
    }

    /* Load CodeMirror then file content */
    fmLoadCodeMirror(function(){
        post({action:"fm_read",file:filePath},function(r){
            if(!r.success){
                edWrap.innerHTML='<div style="padding:24px;color:#ef4444;font-size:13px">Error: '+esc(r.message||"Failed to read file")+'</div>';
                return;
            }
            edWrap.innerHTML="";
            var content=r.content||"";

            /* Init CodeMirror */
            cmEditor=CodeMirror(edWrap,{
                value:content,
                mode:cmMode,
                lineNumbers:true,
                lineWrapping:false,
                matchBrackets:true,
                autoCloseBrackets:true,
                styleActiveLine:true,
                indentUnit:4,
                tabSize:4,
                indentWithTabs:false,
                foldGutter:true,
                gutters:["CodeMirror-linenumber","CodeMirror-foldgutter"],
                extraKeys:{
                    "Ctrl-S":function(){doSave();},
                    "Cmd-S":function(){doSave();},
                    "Ctrl-F":"findPersistent",
                    "Cmd-F":"findPersistent",
                    "Ctrl-H":"replace",
                    "Cmd-Alt-F":"replace",
                    "Ctrl-G":function(cm){
                        fmPrompt("Go to Line","Line number:","",function(v){
                            var ln=parseInt(v,10);if(isNaN(ln)||ln<1) return;
                            cm.setCursor(ln-1,0);cm.scrollIntoView(null,100);cm.focus();
                        });
                    },
                    "Ctrl-D":function(cm){
                        var cur=cm.getCursor();var line=cm.getLine(cur.line);
                        cm.replaceRange(line+"\n",{line:cur.line,ch:0});
                    },
                    "Ctrl-/":"toggleComment",
                    "Cmd-/":"toggleComment",
                    "Tab":function(cm){
                        if(cm.somethingSelected()){cm.indentSelection("add");}
                        else{cm.replaceSelection("    ","end");}
                    },
                    "Shift-Tab":function(cm){cm.indentSelection("subtract");}
                }
            });

            /* Update cursor position in footer */
            cmEditor.on("cursorActivity",function(){
                var cur=cmEditor.getCursor();
                var cursorEl=overlay.querySelector("#fm-ed-cursor");
                if(cursorEl) cursorEl.textContent="Ln "+(cur.line+1)+", Col "+(cur.ch+1);
            });

            /* File size */
            var fsizeEl=overlay.querySelector("#fm-ed-fsize");
            if(fsizeEl) fsizeEl.textContent=fmFormatSize(content.length);

            /* Focus editor */
            setTimeout(function(){cmEditor.refresh();cmEditor.focus();},50);
        });
    });
}



/* (fmSaveFile and fmCloseEditor removed — editor is now a popup modal in fmOpenFile) */

/* ─── FM: Create File ─── */
function fmCreateFile(name){
    post({action:"fm_create_file",dir:fmState.dir,name:name},function(r){
        fmLoadDir(fmState.dir);
        if(!r.success) fmToast(r.message||"Failed to create file",false);
    });
}

/* ─── FM: Create Folder ─── */
function fmCreateFolder(name){
    post({action:"fm_create_folder",dir:fmState.dir,name:name},function(r){
        fmLoadDir(fmState.dir);
        if(!r.success) fmToast(r.message||"Failed to create folder",false);
    });
}

/* ─── FM: Delete ─── */
function fmConfirmDelete(){
    var items=fmState.selected;
    var names=items.map(function(p){return p.split("/").pop();}).join(", ");
    fmConfirm("Delete","Delete "+items.length+" item(s)?\n\n"+names,function(){
        post({action:"fm_delete",items:JSON.stringify(items)},function(r){
            fmLoadDir(fmState.dir);
            if(!r.success) fmToast(r.message||"Failed to delete",false);
        });
    });
}

/* ─── FM: Rename ─── */
function fmRename(oldPath,newName){
    post({action:"fm_rename",old:oldPath,new_name:newName},function(r){
        fmLoadDir(fmState.dir);
        if(!r.success) fmToast(r.message||"Failed to rename",false);
    });
}

/* ─── FM: Copy/Move ─── */
function fmCopyMove(action,items,dest){
    var done=0;var errors=[];
    items.forEach(function(src){
        var destPath=dest.replace(/\/+$/,"")+"/"+src.split("/").pop();
        post({action:action,source:src,dest:destPath},function(r){
            done++;
            if(!r.success) errors.push(r.message||"Failed");
            if(done===items.length){
                if(errors.length) fmToast(errors.join("; "),false);
                fmLoadDir(fmState.dir);
            }
        });
    });
}

/* ─── FM: Upload ─── */
function fmUploadFiles(fileList){
    var bar=$("fm-upload-bar");var fill=$("fm-upload-fill");var pct=$("fm-upload-pct");var uname=$("fm-upload-name");
    var total=fileList.length;var done=0;var errors=[];
    if(bar) bar.classList.add("active");
    function uploadNext(idx){
        if(idx>=total){
            if(bar) setTimeout(function(){bar.classList.remove("active");},1500);
            if(errors.length) fmToast("Upload errors: "+errors.join("; "),false);
            fmLoadDir(fmState.dir);
            return;
        }
        var f=fileList[idx];
        if(uname) uname.textContent="Uploading: "+f.name;
        var fd=new FormData();
        fd.append("action","fm_upload");
        fd.append("service_id",C.serviceId);
        fd.append("dir",fmState.dir);
        fd.append("file",f);
        var x=new XMLHttpRequest();
        x.open("POST",ajaxUrl,true);
        x.upload.onprogress=function(e){
            if(e.lengthComputable){
                var p=Math.round(e.loaded/e.total*100);
                if(fill) fill.style.width=p+"%";
                if(pct) pct.textContent=p+"%";
            }
        };
        x.onload=function(){
            done++;
            var overallPct=Math.round(done/total*100);
            if(fill) fill.style.width=overallPct+"%";
            if(pct) pct.textContent=done+"/"+total;
            try{var r=JSON.parse(x.responseText);if(!r.success) errors.push(f.name+": "+(r.message||"Failed"));}catch(e){errors.push(f.name+": Invalid response");}
            uploadNext(idx+1);
        };
        x.onerror=function(){done++;errors.push(f.name+": Network error");uploadNext(idx+1);};
        x.send(fd);
    }
    uploadNext(0);
}

/* ─── FM: Download ─── */
function fmDownload(filePath){
    post({action:"fm_download_url",file:filePath},function(r){
        if(r.success&&r.url) window.open(r.url,"_blank");
        else fmToast(r.message||"Failed to get download URL",false);
    });
}

/* ─── FM: Compress ─── */
function fmCompress(archiveName){
    var dest=fmState.dir.replace(/\/+$/,"")+"/"+archiveName;
    fmToast("Compressing...",true);
    post({action:"fm_compress",items:JSON.stringify(fmState.selected),dest:dest},function(r){
        fmLoadDir(fmState.dir);
        if(!r.success) fmToast(r.message||"Failed to compress",false);
        else fmToast("Compressed successfully",true);
    });
}

/* ─── FM: Extract ─── */
function fmExtract(filePath,dest){
    fmToast("Extracting...",true);
    post({action:"fm_extract",file:filePath,dest:dest},function(r){
        fmLoadDir(fmState.dir);
        if(!r.success) fmToast(r.message||"Failed to extract",false);
        else fmToast("Extracted successfully",true);
    });
}

/* ─── FM: Search ─── */
function fmDoSearch(){
    var si=$("fm-search-input");if(!si||!si.value.trim()) return;
    var query=si.value.trim();
    var listWrap=$("fm-list-wrap");
    if(listWrap) listWrap.innerHTML='<div class="bt-loading"><div class="bt-spinner"></div>Searching...</div>';
    post({action:"fm_search",dir:fmState.dir,query:query},function(r){
        if(!r.success){if(listWrap) listWrap.innerHTML='<div class="bt-empty">'+esc(r.message||"Search failed")+'</div>';return;}
        var results=r.results||[];
        if(!results.length){if(listWrap) listWrap.innerHTML='<div class="bt-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>No results for "'+esc(query)+'"</div>';return;}
        var html='<table class="fm-table"><thead><tr><th>Name</th><th>Path</th></tr></thead><tbody>';
        results.forEach(function(item){
            var fullPath=item.path||((item.dir||"")+"/"+item.file);
            var itemType=item.type||(item.file&&item.file.indexOf(".")===-1?"dir":"file");
            html+='<tr data-fmpath="'+esc(fullPath)+'" data-fmtype="'+esc(itemType)+'"><td><div class="fm-name-cell" data-fmsearchpath="'+esc(item.path)+'"><div class="fm-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div><div class="fm-fname">'+esc(item.file||"")+'</div></div></td><td style="font-size:12px;color:var(--text-muted,#6b7280)">'+esc(item.dir||"")+'</td></tr>';
        });
        html+='</tbody></table>';
        if(listWrap) listWrap.innerHTML=html;
        listWrap.querySelectorAll("[data-fmsearchpath]").forEach(function(el){
            el.addEventListener("click",function(){
                var p=this.getAttribute("data-fmsearchpath");
                fmOpenFile(p);
            });
        });
        /* Context menu on search result rows */
        listWrap.querySelectorAll("tr[data-fmpath]").forEach(function(tr){
            tr.addEventListener("contextmenu",function(e){
                e.preventDefault();e.stopPropagation();
                var p=this.getAttribute("data-fmpath");
                var t=this.getAttribute("data-fmtype");
                fmShowContextMenu(e.clientX,e.clientY,p,t);
            });
        });
    });
}

/* ─── FM: Prompt Modal ─── */
function fmPrompt(title,label,defaultVal,callback){
    var overlay=document.createElement("div");overlay.className="bt-overlay";
    overlay.innerHTML='<div class="bt-modal"><div class="bt-modal-head"><h5>'+esc(title)+'</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body"><div class="bt-field"><label>'+esc(label)+'</label><input type="text" id="fm-prompt-input" value="'+esc(defaultVal||"")+'" autocomplete="off"></div></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-primary" id="fm-prompt-ok">OK</button></div></div>';
    document.body.appendChild(overlay);
    var inp=overlay.querySelector("#fm-prompt-input");
    setTimeout(function(){if(inp){inp.focus();inp.select();}},50);
    function close(){if(overlay.parentNode) overlay.parentNode.removeChild(overlay);}
    overlay.querySelectorAll("[data-close]").forEach(function(b){b.addEventListener("click",close);});
    overlay.addEventListener("click",function(e){if(e.target===overlay) close();});
    overlay.querySelector("#fm-prompt-ok").addEventListener("click",function(){var v=inp.value.trim();close();callback(v);});
    if(inp) inp.addEventListener("keydown",function(e){if(e.key==="Enter"){var v=inp.value.trim();close();callback(v);}});
}

/* ─── FM: Prompt for Destination ─── */
function fmPromptDest(title,label,defaultVal,callback){
    fmPrompt(title,label,defaultVal,callback);
}

/* ─── FM: Confirm Dialog ─── */
function fmConfirm(title,message,callback){
    var overlay=document.createElement("div");overlay.className="bt-overlay";
    overlay.innerHTML='<div class="bt-modal bt-modal-sm"><div class="bt-modal-head"><h5>'+esc(title)+'</h5><button type="button" class="bt-modal-close" data-close>&times;</button></div><div class="bt-modal-body" style="text-align:center"><div style="margin:8px 0 16px"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5" style="margin:0 auto;display:block"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><p style="margin:0;font-size:14px;white-space:pre-wrap">'+esc(message)+'</p></div><div class="bt-modal-foot"><button type="button" class="bt-btn-cancel" data-close>Cancel</button><button type="button" class="bt-btn-danger" id="fm-confirm-ok">Confirm</button></div></div>';
    document.body.appendChild(overlay);
    function close(){if(overlay.parentNode) overlay.parentNode.removeChild(overlay);}
    overlay.querySelectorAll("[data-close]").forEach(function(b){b.addEventListener("click",close);});
    overlay.addEventListener("click",function(e){if(e.target===overlay) close();});
    overlay.querySelector("#fm-confirm-ok").addEventListener("click",function(){close();callback();});
}

/* ─── FM: Permissions Prompt ─── */
function fmPromptPerms(){
    if(fmState.selected.length!==1) return;
    var filePath=fmState.selected[0];
    var file=fmState.files.find(function(f){return f.path===filePath;});
    var currentPerms=file?file.rawperms||file.perms||"0644":"0644";
    fmPrompt("Change Permissions","Enter permissions (e.g. 0755):",currentPerms,function(perms){
        if(!perms) return;
        post({action:"fm_permissions",file:filePath,perms:perms},function(r){
            fmLoadDir(fmState.dir);
            if(!r.success) fmToast(r.message||"Failed to change permissions",false);
        });
    });
}

/* ─── FM: Context Menu ─── */
function fmShowContextMenu(x,y,filePath,fileType){
    fmHideContextMenu();
    var menu=document.createElement("div");menu.className="fm-ctx";menu.id="fm-ctx-menu";
    var isDir=fileType==="dir";
    var isArchive=false;
    if(!isDir){var ext=(filePath||"").split(".").pop().toLowerCase();isArchive=["zip","tar","gz","bz2","tgz","rar","7z"].indexOf(ext)!==-1;}
    var items=[];
    if(isDir) items.push({icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',label:"Open",action:function(){fmLoadDir(filePath);}});
    else items.push({icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',label:"Edit",action:function(){fmOpenFile(filePath);}});
    if(!isDir) items.push({icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',label:"Download",action:function(){fmDownload(filePath);}});
    items.push({sep:true});
    items.push({icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>',label:"Rename",action:function(){var old=filePath.split("/").pop();fmPrompt("Rename","New name:",old,function(n){if(n&&n!==old) fmRename(filePath,n);});}});
    items.push({icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',label:"Copy",action:function(){fmPromptDest("Copy","Copy to:",fmState.dir,function(d){if(d) fmCopyMove("fm_copy",[filePath],d);});}});
    items.push({icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="5 9 2 12 5 15"/><polyline points="9 5 12 2 15 5"/><polyline points="15 19 12 22 9 19"/><polyline points="19 9 22 12 19 15"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="22"/></svg>',label:"Move",action:function(){fmPromptDest("Move","Move to:",fmState.dir,function(d){if(d) fmCopyMove("fm_move",[filePath],d);});}});
    items.push({icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',label:"Permissions",action:function(){fmState.selected=[filePath];fmPromptPerms();}});
    items.push({sep:true});
    if(isArchive) items.push({icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',label:"Extract",action:function(){fmPromptDest("Extract","Extract to:",fmState.dir,function(d){if(d) fmExtract(filePath,d);});}});
    items.push({icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',label:"Compress",action:function(){fmPrompt("Compress","Archive name:","archive.zip",function(n){if(n){fmState.selected=[filePath];fmCompress(n);}});}});
    items.push({sep:true});
    items.push({icon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',label:"Delete",cls:"danger",action:function(){fmState.selected=[filePath];fmConfirmDelete();}});

    items.forEach(function(it){
        if(it.sep){var sep=document.createElement("div");sep.className="fm-ctx-sep";menu.appendChild(sep);return;}
        var el=document.createElement("div");el.className="fm-ctx-item"+(it.cls?" "+it.cls:"");
        el.innerHTML=it.icon+" "+esc(it.label);
        el.addEventListener("click",function(){fmHideContextMenu();it.action();});
        menu.appendChild(el);
    });

    /* Position */
    document.body.appendChild(menu);
    var mw=menu.offsetWidth;var mh=menu.offsetHeight;
    var ww=window.innerWidth;var wh=window.innerHeight;
    if(x+mw>ww) x=ww-mw-8;
    if(y+mh>wh) y=wh-mh-8;
    menu.style.left=x+"px";menu.style.top=y+"px";

    /* Close on click outside */
    setTimeout(function(){
        document.addEventListener("click",fmHideContextMenu);
        document.addEventListener("contextmenu",fmHideContextMenu);
    },10);
}
function fmHideContextMenu(){
    var m=$("fm-ctx-menu");if(m&&m.parentNode) m.parentNode.removeChild(m);
    document.removeEventListener("click",fmHideContextMenu);
    document.removeEventListener("contextmenu",fmHideContextMenu);
}

/* ─── FM: Toast notification ─── */
function fmToast(msg,ok){
    var existing=document.querySelectorAll(".fm-toast");existing.forEach(function(t){if(t.parentNode) t.parentNode.removeChild(t);});
    var toast=document.createElement("div");toast.className="fm-toast";
    toast.style.cssText="position:fixed;bottom:24px;right:24px;z-index:100003;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:600;color:#fff;box-shadow:0 4px 16px rgba(0,0,0,.15);animation:btSlideUp .2s;max-width:400px;word-break:break-word;"+(ok?"background:#059669":"background:#ef4444");
    toast.textContent=msg;
    document.body.appendChild(toast);
    setTimeout(function(){if(toast.parentNode){toast.style.opacity="0";toast.style.transition="opacity .3s";setTimeout(function(){if(toast.parentNode) toast.parentNode.removeChild(toast);},300);}},3500);
}

/* ─── Boot ─── */
if(document.readyState==="loading") document.addEventListener("DOMContentLoaded",init);
else init();

})();
