<?php
require_once __DIR__ . '/lib/ports.php';
$ports = pt_list_ports();
$results = pt_load_results();
$tested = 0; $mismatched = 0;
foreach ($ports as $p) {
    if (isset($results[$p['id']])) {
        $tested++;
        if (!$results[$p['id']]['match']) $mismatched++;
    }
}
?>
<style>
#pts{max-width:640px;margin:0 auto;color:#1f2733;font-size:14px}
#pts .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:14px 0}
#pts .stat{border:1px solid #e4e7ec;border-radius:12px;padding:16px;text-align:center;background:#fff}
#pts .stat .n{font-size:28px;font-weight:700}
#pts .stat .l{color:#6b7280;font-size:12.5px;margin-top:4px}
#pts .warn .n{color:#c0392b}
#pts .ok .n{color:#1d8a5b}
</style>
<div id="pts">
  <p>Summary of configured ports and their last recorded LED counts. Use <b>Port &amp; LED Tester</b> under Content Setup to run a test.</p>
  <div class="grid">
    <div class="stat"><div class="n"><?php echo count($ports); ?></div><div class="l">Configured ports</div></div>
    <div class="stat ok"><div class="n"><?php echo $tested; ?></div><div class="l">Tested</div></div>
    <div class="stat warn"><div class="n"><?php echo $mismatched; ?></div><div class="l">Mismatched count</div></div>
  </div>
</div>
