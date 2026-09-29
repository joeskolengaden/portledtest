<?php
/*
 * Backend for the Port & LED Tester plugin.
 *
 * Drives FPP's own, always-present /api/testmode endpoint (RGBFill mode) to
 * light up one port, or one single pixel within a port, at a time. No fppd
 * modification and no compiled component - this only orchestrates FPP's
 * stock channel tester, which has been part of FPP since long before 5.4.
 *
 * Actions (POST unless noted):
 *   list                                  -> ports + any saved results
 *   fill      start,count,c1,c2,c3        -> RGBFill that channel range
 *   stop                                  -> disable test mode (all outputs off)
 *   save_result  id,observed,configured,note
 *   clear_result id
 */
@header('Content-Type: application/json');
require_once __DIR__ . '/lib/ports.php';

function pt_out($ok, $extra = array()) {
    echo json_encode(array_merge(array('ok' => $ok), $extra));
    exit;
}

function pt_http_post_json($path, $payload) {
    $url = 'http://127.0.0.1' . $path;
    $body = json_encode($payload);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $resp = curl_exec($ch);
    $ok = ($resp !== false) && (curl_errno($ch) === 0);
    curl_close($ch);
    return $ok;
}

function pt_set_testmode_fill($start, $count, $c1, $c2, $c3) {
    $end = $start + $count - 1;
    return pt_http_post_json('/api/testmode', array(
        'enabled' => 1,
        'mode' => 'RGBFill',
        'channelSet' => "$start-$end",
        'channelSetType' => 'channelRange',
        'color1' => $c1,
        'color2' => $c2,
        'color3' => $c3,
    ));
}

function pt_set_testmode_fill_multi($ranges, $c1, $c2, $c3) {
    // $ranges is an array of "start-end" strings; TestPatternBase accepts
    // a ';'-joined channelSet so many ports can be lit in one pattern.
    return pt_http_post_json('/api/testmode', array(
        'enabled' => 1,
        'mode' => 'RGBFill',
        'channelSet' => implode(';', $ranges),
        'channelSetType' => 'channelRange',
        'color1' => $c1,
        'color2' => $c2,
        'color3' => $c3,
    ));
}

function pt_disable_testmode() {
    return pt_http_post_json('/api/testmode', array('enabled' => 0));
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if ($action === 'list') {
    $ports = pt_list_ports();
    $results = pt_load_results();
    foreach ($ports as &$p) {
        $p['result'] = isset($results[$p['id']]) ? $results[$p['id']] : null;
    }
    unset($p);
    pt_out(true, array('ports' => $ports));
}

if ($action === 'fill') {
    $start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
    $count = isset($_POST['count']) ? (int)$_POST['count'] : 0;
    $c1 = isset($_POST['c1']) ? max(0, min(255, (int)$_POST['c1'])) : 255;
    $c2 = isset($_POST['c2']) ? max(0, min(255, (int)$_POST['c2'])) : 255;
    $c3 = isset($_POST['c3']) ? max(0, min(255, (int)$_POST['c3'])) : 255;

    if ($start < 1 || $count < 1) pt_out(false, array('error' => 'Invalid channel range'));

    $ok = pt_set_testmode_fill($start, $count, $c1, $c2, $c3);
    pt_out($ok, array('channelSet' => ($start . '-' . ($start + $count - 1))));
}

if ($action === 'fill_all') {
    // Light every configured port at once, for a quick whole-rig check.
    $c1 = isset($_POST['c1']) ? max(0, min(255, (int)$_POST['c1'])) : 255;
    $c2 = isset($_POST['c2']) ? max(0, min(255, (int)$_POST['c2'])) : 255;
    $c3 = isset($_POST['c3']) ? max(0, min(255, (int)$_POST['c3'])) : 255;

    $ports = pt_list_ports();
    if (!count($ports)) pt_out(false, array('error' => 'No configured ports found'));

    $ranges = array();
    foreach ($ports as $p) {
        $ranges[] = $p['dataStartChannel'] . '-' . $p['endChannel'];
    }
    $ok = pt_set_testmode_fill_multi($ranges, $c1, $c2, $c3);
    pt_out($ok, array('ports' => count($ports)));
}

if ($action === 'stop') {
    $ok = pt_disable_testmode();
    pt_out($ok);
}

if ($action === 'save_result') {
    $id = isset($_POST['id']) ? (string)$_POST['id'] : '';
    $observed = isset($_POST['observed']) ? (int)$_POST['observed'] : -1;
    $configured = isset($_POST['configured']) ? (int)$_POST['configured'] : -1;
    $note = isset($_POST['note']) ? substr((string)$_POST['note'], 0, 200) : '';

    if ($id === '' || $observed < 0) pt_out(false, array('error' => 'Missing id/observed'));

    $results = pt_load_results();
    $results[$id] = array(
        'observed' => $observed,
        'configured' => $configured,
        'match' => ($observed === $configured),
        'note' => $note,
        'testedAt' => date('c'),
    );
    if (!pt_save_results($results)) pt_out(false, array('error' => 'Could not write results file'));
    pt_out(true);
}

if ($action === 'clear_result') {
    $id = isset($_POST['id']) ? (string)$_POST['id'] : '';
    $results = pt_load_results();
    if (isset($results[$id])) {
        unset($results[$id]);
        pt_save_results($results);
    }
    pt_out(true);
}

pt_out(false, array('error' => 'Unknown action'));
