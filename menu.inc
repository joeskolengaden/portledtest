<?php
$menuEntries = Array(
    Array('text' => 'Port &amp; LED Tester - Summary', 'type' => 'status', 'page' => 'status.php', 'wrap' => 1),
    Array('text' => 'Port &amp; LED Tester', 'type' => 'content', 'page' => 'plugin.php', 'wrap' => 1),
    Array('text' => 'Port &amp; LED Tester - Help', 'type' => 'help', 'page' => 'https://github.com/joeskolengaden/portledtest', 'wrap' => 1)
);
foreach ($menuEntries as $entry) {
    if ($entry['type'] != $menu) continue;
    if (preg_match('/^http.?:\/\//', $entry['page']))
        printf("<li><a href='%s' target='_blank'>%s</a></li>\n", $entry['page'], $entry['text']);
    else {
        $nopage = (isset($entry['wrap']) && $entry['wrap'] == 0) ? '&nopage=1' : '';
        printf("<li><a href='plugin.php?plugin=%s&page=%s%s'>%s</a></li>\n", $plugin, $entry['page'], $nopage, $entry['text']);
    }
}
?>
