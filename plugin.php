<?php
// Port & LED Tester - main content page.
// All data loads client-side via action.php so this file stays a static shell.
?>
<style>
#pt{max-width:1080px;margin:0 auto;color:#1f2733;font-size:14px}
#pt .intro{color:#6b7280;font-size:13px;margin:0 0 14px}
#pt .note{background:#fff8e6;border:1px solid #f5e3a6;color:#7a5d00;padding:10px 12px;border-radius:8px;font-size:13px;margin:0 0 14px}
#pt .card{border:1px solid #e4e7ec;border-radius:12px;background:#fff;margin:0 0 14px;overflow:hidden}
#pt .head{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#f6f8fa;border-bottom:1px solid #eceef2;flex-wrap:wrap}
#pt .head .t{font-size:15px;font-weight:600;flex:1}
#pt .body{padding:16px}
#pt button{padding:8px 14px;border:0;border-radius:7px;background:#2f6fed;color:#fff;font-size:13.5px;font-weight:600;cursor:pointer}
#pt button.sec{background:#eceef2;color:#374151}
#pt button.danger{background:#c0392b}
#pt button:disabled{opacity:.5;cursor:default}
#pt button.swatch{width:30px;height:30px;padding:0;border-radius:6px;border:2px solid transparent}
#pt button.swatch.active{border-color:#1f2733}
#pt input[type=number],#pt input[type=text]{padding:7px 9px;border:1px solid #cdd3dc;border-radius:7px;background:#fff;font-size:13.5px}
#pt select{padding:7px 9px;border:1px solid #cdd3dc;border-radius:7px;background:#fff;font-size:13.5px}
#pt .row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
#pt table{width:100%;border-collapse:collapse;font-size:13px}
#pt th,#pt td{padding:8px 10px;border-bottom:1px solid #eceef2;text-align:left;white-space:nowrap}
#pt th{color:#6b7280;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.02em}
#pt tr.sel{background:#eef4ff}
#pt tr:hover{background:#f9fafb}
#pt .pill{display:inline-flex;align-items:center;gap:6px;padding:3px 9px;border-radius:20px;font-size:12px;font-weight:600}
#pt .pill.ok{background:#e6f4ed;color:#1d8a5b}
#pt .pill.warn{background:#fdecec;color:#c0392b}
#pt .pill.off{background:#eceef2;color:#6b7280}
#pt .dot{width:8px;height:8px;border-radius:50%;background:currentColor}
#pt .muted{color:#6b7280}
#pt .msg{font-size:13px;margin-top:8px;min-height:18px}
#pt .msg.err{color:#c0392b}
#pt .msg.good{color:#1d8a5b}
#pt .stepbar{display:flex;align-items:center;gap:10px;justify-content:center;margin:14px 0}
#pt .stepbar button{min-width:44px}
#pt .stepnum{font-size:30px;font-weight:700;font-variant-numeric:tabular-nums;min-width:170px;text-align:center}
#pt .stepnum small{display:block;font-size:12px;font-weight:500;color:#6b7280;margin-top:2px}
#pt .bar{height:10px;background:#eceef2;border-radius:6px;overflow:hidden;margin:0 0 14px}
#pt .bar>div{height:100%;background:#2f6fed;width:0%;transition:width .15s}
#pt .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
#pt .empty{padding:30px;text-align:center;color:#6b7280}
@media (max-width:700px){#pt .grid2{grid-template-columns:1fr}}
</style>

<div id="pt">
  <p class="intro">Lights up one physical output port at a time (or all of them at once) using FPP's built-in test mode, and steps through pixels one at a time so you can visually count how many LEDs on a strand actually light up - handy for verifying a new run or finding where a strand died.</p>
  <div class="note">This takes over channel output while active. Playback should be stopped. Leaving this page, or clicking "All Off", clears test mode.</div>

  <div class="card">
    <div class="head">
      <span class="t">Configured ports</span>
      <button class="sec" onclick="ptLoadPorts()">Refresh</button>
      <button class="sec" onclick="ptFillAll()">Fill all ports (white)</button>
      <button class="danger" onclick="ptStop()">All Off / Stop Test</button>
    </div>
    <div class="body">
      <div id="pt-table-wrap"><div class="empty">Loading ports…</div></div>
      <div class="msg" id="pt-msg-list"></div>
    </div>
  </div>

  <div class="card" id="pt-tester" style="display:none">
    <div class="head">
      <span class="t" id="pt-tester-title">Testing port</span>
      <span id="pt-tester-badge" class="pill off"><span class="dot"></span><span>Idle</span></span>
      <button class="sec" onclick="ptCloseTester()">Close</button>
    </div>
    <div class="body">
      <div class="grid2">
        <div>
          <div class="row" style="margin-bottom:10px">
            <span class="muted">Test color:</span>
            <button class="swatch active" id="sw-white" style="background:#fff;border-color:#ccc" onclick="ptSetColor(255,255,255,'sw-white')" title="White"></button>
            <button class="swatch" id="sw-red" style="background:#e03131" onclick="ptSetColor(255,0,0,'sw-red')" title="Channel 1 only"></button>
            <button class="swatch" id="sw-green" style="background:#2f9e44" onclick="ptSetColor(0,255,0,'sw-green')" title="Channel 2 only"></button>
            <button class="swatch" id="sw-blue" style="background:#1971c2" onclick="ptSetColor(0,0,255,'sw-blue')" title="Channel 3 only"></button>
          </div>
          <div class="row">
            <button onclick="ptFillWholePort()">Fill whole port</button>
            <button class="sec" onclick="ptStop()">Off</button>
          </div>
          <div class="help muted" style="font-size:12.5px;margin-top:8px">Solid fill of the entire port - quick check that a whole run is alive and wired to the right port. Colors are raw wire-byte order (channel 1/2/3), not logical RGB, so they work regardless of colorOrder.</div>
        </div>
        <div>
          <div class="muted" style="font-size:12.5px;margin-bottom:6px">Step size</div>
          <select id="pt-stepsize" onchange="ptRenderStep()">
            <option value="1">1 pixel</option>
            <option value="5">5 pixels</option>
            <option value="10">10 pixels</option>
            <option value="25">25 pixels</option>
          </select>
          <div class="muted" style="font-size:12.5px;margin:10px 0 6px">Auto-play speed</div>
          <select id="pt-speed">
            <option value="1000">Slow (1/s)</option>
            <option value="500" selected>Medium (2/s)</option>
            <option value="250">Fast (4/s)</option>
            <option value="100">Very fast (10/s)</option>
          </select>
        </div>
      </div>

      <hr style="border:none;border-top:1px solid #eceef2;margin:16px 0">

      <div class="muted" style="text-align:center;font-size:12.5px">Count mode - one pixel lit at a time</div>
      <div class="stepbar">
        <button class="sec" onclick="ptJump(1)">|&lt;</button>
        <button class="sec" onclick="ptStep(-1)">&lt;</button>
        <div class="stepnum"><span id="pt-stepnum">1</span> / <span id="pt-steptotal">-</span><small id="pt-stepphys"></small></div>
        <button class="sec" onclick="ptStep(1)">&gt;</button>
        <button class="sec" onclick="ptJumpLast()">&gt;|</button>
        <button id="pt-play" onclick="ptTogglePlay()">▶ Play</button>
      </div>
      <div class="bar"><div id="pt-progress"></div></div>

      <div class="row" style="justify-content:center;margin-bottom:6px">
        <input type="number" id="pt-jumpto" style="width:90px" placeholder="Jump to #">
        <button class="sec" onclick="ptJumpToInput()">Go</button>
        <button class="sec" onclick="ptMarkStoppedHere()">This is where it stopped</button>
      </div>

      <div class="row" style="justify-content:center;margin-top:14px">
        <span class="muted">Observed LED count:</span>
        <input type="number" id="pt-observed" style="width:90px" min="0">
        <input type="text" id="pt-note" placeholder="Note (optional)" style="width:220px">
        <button onclick="ptSaveObserved()">Save result</button>
      </div>
      <div class="msg" id="pt-msg-test"></div>
    </div>
  </div>
</div>

<script>
(function(){
  var base = 'plugin.php?plugin=portledtest&page=action.php&nopage=1';
  var PORTS = [];
  var sel = null;
  var stepIndex = 1;
  var color = {r:255,g:255,b:255};
  var playing = false;
  var playTimer = null;

  function $(id){ return document.getElementById(id); }
  function esc(s){ return String(s).replace(/[&<>"]/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function msg(id, text, ok){ var e=$(id); if(!e) return; e.textContent=text||''; e.className='msg '+(text?(ok?'good':'err'):''); }

  function post(action, data, cb){
    var fd = new FormData();
    fd.append('action', action);
    for (var k in data) fd.append(k, data[k]);
    fetch(base, {method:'POST', body:fd, credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(cb)
      .catch(function(){ cb({ok:false, error:'Request failed'}); });
  }

  window.ptLoadPorts = function(){
    post('list', {}, function(r){
      if (!r.ok){ $('pt-table-wrap').innerHTML = '<div class="empty">Could not load ports.</div>'; return; }
      PORTS = r.ports || [];
      renderTable();
      if (sel) {
        var updated = PORTS.find(function(p){ return p.id === sel.id; });
        if (updated) sel = updated;
      }
    });
  };

  function statusPill(p){
    if (!p.result) return '<span class="pill off"><span class="dot"></span>Not tested</span>';
    if (p.result.match) return '<span class="pill ok"><span class="dot"></span>OK ('+p.result.observed+')</span>';
    return '<span class="pill warn"><span class="dot"></span>Mismatch: '+p.result.observed+' / '+p.result.configured+'</span>';
  }

  function renderTable(){
    if (!PORTS.length){
      $('pt-table-wrap').innerHTML = '<div class="empty">No pixel outputs found in channeloutputs.json. Configure ports under Content Setup &rarr; Channel Outputs first.</div>';
      return;
    }
    var html = '<table><thead><tr>' +
      '<th>Type / Port</th><th>Description</th><th>Channels</th><th>Configured</th>' +
      '<th>Color order</th><th>Last tested</th><th>Status</th><th></th></tr></thead><tbody>';
    PORTS.forEach(function(p){
      var isSel = sel && sel.id === p.id;
      var unit = (p.colorOrder === 'RAW') ? 'ch' : 'px';
      html += '<tr class="'+(isSel?'sel':'')+'">' +
        '<td>'+esc(p.type)+' #'+esc(p.port)+(p.coEnabled?'':' <span class="pill warn" title="This output is disabled under Channel Outputs">disabled</span>')+'</td>' +
        '<td>'+esc(p.description)+'</td>' +
        '<td class="muted">'+p.dataStartChannel+'-'+p.endChannel+'</td>' +
        '<td>'+p.addressablePixels+' '+unit+(p.groupCount>1?(' (&times;'+p.groupCount+' grouped)'):'')+'</td>' +
        '<td class="muted">'+esc(p.colorOrder)+'</td>' +
        '<td class="muted">'+(p.result ? new Date(p.result.testedAt).toLocaleString() : '—')+'</td>' +
        '<td>'+statusPill(p)+'</td>' +
        '<td><button class="sec" onclick="ptSelectPort(\''+p.id+'\')">Test</button></td>' +
        '</tr>';
    });
    html += '</tbody></table>';
    $('pt-table-wrap').innerHTML = html;
  }

  window.ptSelectPort = function(id){
    sel = PORTS.find(function(p){ return p.id === id; });
    if (!sel) return;
    stepIndex = 1;
    ptStopPlay();
    $('pt-tester').style.display = '';
    $('pt-tester-title').textContent = 'Testing ' + sel.type + ' #' + sel.port + ' - ' + sel.description;
    $('pt-observed').value = sel.addressablePixels;
    $('pt-note').value = '';
    msg('pt-msg-test', '', true);
    renderTable();
    ptRenderStep();
    $('pt-tester').scrollIntoView({behavior:'smooth', block:'nearest'});
  };

  window.ptCloseTester = function(){
    ptStopPlay();
    ptStop();
    sel = null;
    $('pt-tester').style.display = 'none';
    renderTable();
  };

  window.ptSetColor = function(r,g,b,btnId){
    color = {r:r,g:g,b:b};
    ['sw-white','sw-red','sw-green','sw-blue'].forEach(function(id){
      var e=$(id); if(e) e.className = 'swatch' + (id===btnId?' active':'');
    });
  };

  function setBadge(active){
    var b = $('pt-tester-badge');
    if (active){ b.className='pill ok'; b.innerHTML='<span class="dot"></span>Live'; }
    else { b.className='pill off'; b.innerHTML='<span class="dot"></span>Idle'; }
  }

  window.ptFillWholePort = function(){
    if (!sel) return;
    var count = sel.endChannel - sel.dataStartChannel + 1;
    post('fill', {start:sel.dataStartChannel, count:count, c1:color.r, c2:color.g, c3:color.b}, function(r){
      setBadge(!!r.ok);
      msg('pt-msg-test', r.ok ? 'Whole port filled.' : (r.error||'Failed'), r.ok);
    });
  };

  window.ptFillAll = function(){
    post('fill_all', {c1:255, c2:255, c3:255}, function(r){
      msg('pt-msg-list', r.ok ? ('Lit '+r.ports+' port(s) white.') : (r.error||'Failed'), r.ok);
    });
  };

  window.ptStop = function(){
    ptStopPlay();
    post('stop', {}, function(r){
      setBadge(false);
      msg('pt-msg-test', r.ok ? 'Test mode stopped.' : (r.error||'Failed'), r.ok);
      msg('pt-msg-list', r.ok ? 'All outputs cleared.' : (r.error||'Failed'), r.ok);
    });
  };

  function pushStep(){
    if (!sel) return;
    var cpp = sel.channelsPerPixel;
    var start = sel.dataStartChannel + (stepIndex - 1) * cpp;
    post('fill', {start:start, count:cpp, c1:color.r, c2:color.g, c3:color.b}, function(r){
      setBadge(!!r.ok);
    });
  }

  window.ptRenderStep = function(){
    if (!sel) return;
    var total = sel.addressablePixels;
    $('pt-stepnum').textContent = stepIndex;
    $('pt-steptotal').textContent = total;
    var phys = '';
    if (sel.groupCount > 1){
      var lo = (stepIndex-1)*sel.groupCount + 1, hi = stepIndex*sel.groupCount;
      phys = 'physical LEDs ' + lo + '-' + hi;
    }
    $('pt-stepphys').textContent = phys;
    $('pt-progress').style.width = Math.round((stepIndex/total)*100) + '%';
    pushStep();
  };

  window.ptStep = function(delta){
    if (!sel) return;
    var size = parseInt($('pt-stepsize').value) || 1;
    ptJump(stepIndex + delta*size);
  };

  window.ptJump = function(i){
    if (!sel) return;
    var total = sel.addressablePixels;
    if (i < 1) i = 1;
    if (i > total) i = total;
    stepIndex = i;
    ptRenderStep();
  };

  window.ptJumpLast = function(){ if (sel) ptJump(sel.addressablePixels); };
  window.ptJumpToInput = function(){
    var v = parseInt($('pt-jumpto').value);
    if (!isNaN(v)) ptJump(v);
  };

  window.ptMarkStoppedHere = function(){
    $('pt-observed').value = stepIndex;
    msg('pt-msg-test', 'Observed count set to current step ('+stepIndex+'). Click Save result to record it.', true);
  };

  window.ptTogglePlay = function(){
    if (playing){ ptStopPlay(); return; }
    playing = true;
    $('pt-play').textContent = '⏸ Pause';
    var speed = parseInt($('pt-speed').value) || 500;
    playTimer = setInterval(function(){
      if (!sel) { ptStopPlay(); return; }
      var size = parseInt($('pt-stepsize').value) || 1;
      var next = stepIndex + size;
      if (next > sel.addressablePixels){ ptStopPlay(); return; }
      stepIndex = next;
      ptRenderStep();
    }, speed);
  };

  function ptStopPlay(){
    playing = false;
    if (playTimer) { clearInterval(playTimer); playTimer = null; }
    var b = $('pt-play'); if (b) b.textContent = '▶ Play';
  }
  window.ptStopPlay = ptStopPlay;

  window.ptSaveObserved = function(){
    if (!sel) return;
    var observed = parseInt($('pt-observed').value);
    if (isNaN(observed) || observed < 0){ msg('pt-msg-test', 'Enter a valid count', false); return; }
    post('save_result', {id:sel.id, observed:observed, configured:sel.addressablePixels, note:$('pt-note').value}, function(r){
      msg('pt-msg-test', r.ok ? 'Saved.' : (r.error||'Failed'), r.ok);
      if (r.ok) ptLoadPorts();
    });
  };

  // Best-effort: clear test mode if the user navigates away.
  window.addEventListener('beforeunload', function(){
    try {
      var fd = new FormData(); fd.append('action','stop');
      navigator.sendBeacon && navigator.sendBeacon(base, fd);
    } catch(e){}
  });

  ptLoadPorts();
})();
</script>
