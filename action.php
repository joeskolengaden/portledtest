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
 *   status                                -> current fppd status_name / playlist, for the "sequence is playing" banner
 *   stop_playback                         -> GET /api/playlists/stop (stop the current sequence, not test mode)
 *   fill      start,count,c1,c2,c3        -> RGBFill that channel range
 *   fill_all  c1,c2,c3                    -> RGBFill every configured port at once
 *   stop                                  -> disable test mode (all outputs off)
 *   save_result  id,observed,configured,note
 *   clear_result id
 *   history_csv (GET)                     -> download full test history as a CSV file
 */
// This endpoint's entire response body must be clean JSON (or CSV, for
// history_csv) - never let a stray PHP notice/warning from the host's own
// error-display setting get mixed into it and break the caller's parser.
@ini_set('display_errors', '0');
require_once __DIR__ . '/lib/ports.php';

function pt_out($ok, $extra = array()) {
    @header('Content-Type: application/json');
    echo json_encode(array_merge(array('ok' => $ok), $extra));
    exit;
}

/*
 * GET/POST to FPP's own local API. Uses curl when available (most FPP
 * builds), falling back to a plain stream-context request otherwise, so a
 * minimal PHP build without ext-curl still works.
 */
function pt_http($method, $path, $payload = null) {
    $url = 'http://127.0.0.1' . $path;
    $body = ($payload !== null) ? json_encode($payload) : null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        }
        $resp = curl_exec($ch);
        $ok = ($resp !== false) && (curl_errno($ch) === 0);
        // No curl_close(): a no-op since PHP 8.0, deprecated (warns) in 8.5+,
        // and any stray warning here would corrupt the JSON response body.
        return array($ok, $ok ? $resp : null);
    }

    // Fallback: no ext-curl available.
    $opts = array('http' => array('method' => $method, 'timeout' => 5, 'ignore_errors' => true));
    if ($method === 'POST') {
        $opts['http']['header'] = "Content-Type: application/json\r\n";
        $opts['http']['content'] = $body;
    }
    $resp = @file_get_contents($url, false, stream_context_create($opts));
    return array($resp !== false, $resp !== false ? $resp : null);
}

function pt_set_testmode_fill($start, $count, $c1, $c2, $c3) {
    $end = $start + $count - 1;
    list($ok, ) = pt_http('POST', '/api/testmode', array(
        'enabled' => 1,
        'mode' => 'RGBFill',
        'channelSet' => "$start-$end",
        'channelSetType' => 'channelRange',
        'color1' => $c1,
        'color2' => $c2,
        'color3' => $c3,
    ));
    return $ok;
}

function pt_set_testmode_fill_multi($ranges, $c1, $c2, $c3) {
    // $ranges is an array of "start-end" strings; TestPatternBase accepts
    // a ';'-joined channelSet so many ports can be lit in one pattern.
    list($ok, ) = pt_http('POST', '/api/testmode', array(
        'enabled' => 1,
        'mode' => 'RGBFill',
        'channelSet' => implode(';', $ranges),
        'channelSetType' => 'channelRange',
        'color1' => $c1,
        'color2' => $c2,
        'color3' => $c3,
    ));
    return $ok;
}

function pt_disable_testmode() {
    list($ok, ) = pt_http('POST', '/api/testmode', array('enabled' => 0));
    return $ok;
}

// A short label for a port, used in the history CSV / status lookups.
function pt_port_label($p) {
    $bits = array($p['type'] . ' #' . $p['port']);
    if (!empty($p['capeLabel'])) $bits[] = $p['capeLabel'];
    if (!empty($p['description']) && $p['description'] !== ('Port ' . $p['port'])) $bits[] = $p['description'];
    return implode(' - ', $bits);
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

if ($action === 'status') {
    list($ok, $resp) = pt_http('GET', '/api/system/status');
    if (!$ok) pt_out(false, array('error' => 'Could not reach fppd'));
    $j = json_decode($resp, true);
    if (!is_array($j)) pt_out(false, array('error' => 'Bad response from fppd'));
    pt_out(true, array(
        'status_name' => isset($j['status_name']) ? $j['status_name'] : 'unknown',
        'current_playlist' => isset($j['current_playlist']['playlist']) ? $j['current_playlist']['playlist'] : '',
        'current_sequence' => isset($j['current_sequence']) ? $j['current_sequence'] : '',
    ));
}

if ($action === 'stop_playback') {
    list($ok, ) = pt_http('GET', '/api/playlists/stop');
    pt_out($ok);
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

    $ports = pt_list_ports();
    $label = $id;
    foreach ($ports as $p) {
        if ($p['id'] === $id) { $label = pt_port_label($p); break; }
    }

    $row = array(
        'observed' => $observed,
        'configured' => $configured,
        'match' => ($observed === $configured),
        'note' => $note,
        'testedAt' => date('c'),
    );

    $results = pt_load_results();
    $results[$id] = $row;
    if (!pt_save_results($results)) pt_out(false, array('error' => 'Could not write results file'));

    pt_append_history(array_merge(array('id' => $id, 'port' => $label), $row));
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

if ($action === 'history_csv') {
    $rows = pt_load_history();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="portledtest-history.csv"');
    $out = fopen('php://output', 'w');
    // Explicit delimiter/enclosure/escape: PHP 8.4+ deprecates the 3-arg form,
    // and we can't risk a stray notice landing inside the CSV body.
    fputcsv($out, array('Tested at', 'Port', 'Configured', 'Observed', 'Match', 'Note'), ',', '"', '\\');
    foreach ($rows as $r) {
        fputcsv($out, array(
            isset($r['testedAt']) ? $r['testedAt'] : '',
            isset($r['port']) ? $r['port'] : (isset($r['id']) ? $r['id'] : ''),
            isset($r['configured']) ? $r['configured'] : '',
            isset($r['observed']) ? $r['observed'] : '',
            (!empty($r['match'])) ? 'yes' : 'no',
            isset($r['note']) ? $r['note'] : '',
        ), ',', '"', '\\');
    }
    fclose($out);
    exit;
}

pt_out(false, array('error' => 'Unknown action'));
