<?php
/*
 * Maps a WinterLights cape's FPP driver slot (portNumber in channeloutputs.json)
 * to the port label actually printed on the PCB silkscreen. FPP has four
 * independent numbering systems for the same physical port (driver slot,
 * web-UI port, BBB header pin, silkscreen label) and they are NOT
 * contiguous with each other - e.g. cape "port 17" lives at driver slot 30.
 * Without this table you have to mentally translate every time you test.
 *
 * WinterLights48: device-verified (live BBB, FPP 5.4.1).
 * WinterLights16: silkscreen renumbering is documented design intent, but
 * not yet device-verified - flagged as such in the returned array.
 * Anything else (including WL16v3/WL24v3, whose BBB pin maps are still
 * being worked out) intentionally has no table: better to show nothing
 * than a guessed label.
 */

function pt_wl_label_table($subType) {
    if ($subType === 'WinterLights48') {
        $labels = array();
        $diffNames = array('diff exp port 1', 'diff exp port 2', 'diff exp port 3', 'diff exp port 4',
                            'diff exp port 5', 'diff exp port 6', 'diff exp port 7', 'diff exp port 8');
        foreach ($diffNames as $i => $name) $labels[$i] = $name;
        for ($slot = 8; $slot <= 15; $slot++) $labels[$slot] = 'port ' . ($slot + 1); // 9-16
        $reversed = array(18 => 19, 19 => 20, 20 => 21, 21 => 22, 22 => 23, 23 => 24);
        foreach ($reversed as $slot => $portNo) $labels[$slot] = 'port ' . $portNo;
        $labels[30] = 'port 17';
        $labels[31] = 'port 18';
        return array('labels' => $labels, 'verified' => true);
    }

    if ($subType === 'WinterLights16') {
        $labels = array();
        for ($slot = 0; $slot <= 7; $slot++) $labels[$slot] = 'diff exp port ' . ($slot + 1);
        for ($slot = 8; $slot <= 15; $slot++) $labels[$slot] = 'port ' . ($slot + 1); // 9-16
        for ($slot = 16; $slot <= 23; $slot++) $labels[$slot] = 'port ' . ($slot + 1); // renumbered 17-24
        return array('labels' => $labels, 'verified' => false);
    }

    return null;
}

/*
 * Returns array('label' => string, 'verified' => bool) or null if this
 * subType/slot combination isn't in the table.
 */
function pt_wl_cape_label($subType, $portNumber) {
    if ($subType === '' || $subType === null) return null;
    $table = pt_wl_label_table($subType);
    if (!$table || !isset($table['labels'][$portNumber])) return null;
    return array('label' => $table['labels'][$portNumber], 'verified' => $table['verified']);
}
