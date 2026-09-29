<?php
/*
 * Reads config/channeloutputs.json and flattens it into a list of testable
 * "ports" (one entry per virtual string / physical output), each carrying
 * the absolute channel range that FPP's own test mode understands.
 */

function pt_config_dir() {
    global $settings;
    return isset($settings['configDirectory']) ? $settings['configDirectory'] : '/home/fpp/media/config';
}

function pt_results_path() {
    return pt_config_dir() . '/plugin.portledtest.results.json';
}

function pt_load_results() {
    $path = pt_results_path();
    if (!file_exists($path)) return array();
    $j = json_decode(@file_get_contents($path), true);
    return is_array($j) ? $j : array();
}

function pt_save_results($data) {
    return @file_put_contents(pt_results_path(), json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

function pt_get_channel_outputs() {
    $path = pt_config_dir() . '/channeloutputs.json';
    if (!file_exists($path)) return array();
    $j = json_decode(@file_get_contents($path), true);
    if (!is_array($j) || !isset($j['channelOutputs']) || !is_array($j['channelOutputs'])) return array();
    return $j['channelOutputs'];
}

/*
 * Returns a flat array of ports:
 *   id, type, port, description, protocol,
 *   startChannel      - first channel of this string's block (1-based, includes null nodes)
 *   dataStartChannel  - first channel of the first REAL pixel (after null nodes)
 *   endChannel        - last channel of this string's block
 *   pixelCount        - configured pixel count (addressable, before grouping)
 *   addressablePixels - number of distinct channel-steps (pixelCount / groupCount)
 *   channelsPerPixel  - bytes per pixel (3 for RGB, 4 for RGBW, ...)
 *   colorOrder, groupCount, nullNodes, coEnabled
 */
function pt_list_ports() {
    $cos = pt_get_channel_outputs();
    $ports = array();

    foreach ($cos as $co) {
        $type = isset($co['type']) ? $co['type'] : 'Unknown';
        $enabled = isset($co['enabled']) ? (int)$co['enabled'] : 1;
        $baseStart = isset($co['startChannel']) ? (int)$co['startChannel'] : 1;

        if (isset($co['outputs']) && is_array($co['outputs'])) {
            foreach ($co['outputs'] as $out) {
                $portNum = isset($out['portNumber']) ? $out['portNumber']
                    : (isset($out['outputNumber']) ? $out['outputNumber'] : count($ports));

                if (isset($out['virtualStrings']) && is_array($out['virtualStrings']) && count($out['virtualStrings'])) {
                    foreach ($out['virtualStrings'] as $vi => $vs) {
                        $colorOrder = (isset($vs['colorOrder']) && $vs['colorOrder'] !== '') ? $vs['colorOrder'] : 'RGB';
                        $cpp = max(1, strlen($colorOrder));
                        $pixelCount = isset($vs['pixelCount']) ? (int)$vs['pixelCount'] : 0;
                        if ($pixelCount <= 0) continue;
                        $nullNodes = isset($vs['nullNodes']) ? (int)$vs['nullNodes'] : 0;
                        $groupCount = (isset($vs['groupCount']) && (int)$vs['groupCount'] > 0) ? (int)$vs['groupCount'] : 1;
                        $relStart = isset($vs['startChannel']) ? (int)$vs['startChannel'] : 0;
                        $absStart = $baseStart + $relStart;
                        $dataStart = $absStart + ($nullNodes * $cpp);
                        $addressable = (int)ceil($pixelCount / $groupCount);
                        $desc = (isset($vs['description']) && $vs['description'] !== '') ? $vs['description'] : ('Port ' . $portNum);

                        $ports[] = array(
                            'id' => md5($type . '|' . $portNum . '|' . $vi . '|' . $absStart),
                            'type' => $type,
                            'port' => $portNum,
                            'vstring' => $vi,
                            'description' => $desc,
                            'protocol' => isset($out['protocol']) ? $out['protocol'] : '',
                            'startChannel' => $absStart,
                            'dataStartChannel' => $dataStart,
                            'endChannel' => $dataStart + ($pixelCount * $cpp) - 1,
                            'pixelCount' => $pixelCount,
                            'addressablePixels' => $addressable,
                            'channelsPerPixel' => $cpp,
                            'colorOrder' => $colorOrder,
                            'groupCount' => $groupCount,
                            'nullNodes' => $nullNodes,
                            'coEnabled' => $enabled,
                        );
                    }
                } else {
                    // Flat output with no virtualStrings breakdown (e.g. some serial outputs).
                    $outputType = isset($out['outputType']) ? $out['outputType'] : '';
                    if ($outputType === '' || $outputType === 'off') continue;
                    $startChannel = isset($out['startChannel']) ? (int)$out['startChannel'] : $baseStart;
                    $channelCount = isset($out['channelCount']) ? (int)$out['channelCount'] : 0;
                    if ($channelCount <= 0) continue;
                    $ports[] = array(
                        'id' => md5($type . '|' . $portNum . '|flat|' . $startChannel),
                        'type' => $type,
                        'port' => $portNum,
                        'vstring' => 0,
                        'description' => isset($out['outputType']) ? $out['outputType'] : ('Port ' . $portNum),
                        'protocol' => isset($out['outputType']) ? $out['outputType'] : '',
                        'startChannel' => $startChannel,
                        'dataStartChannel' => $startChannel,
                        'endChannel' => $startChannel + $channelCount - 1,
                        'pixelCount' => $channelCount,
                        'addressablePixels' => $channelCount,
                        'channelsPerPixel' => 1,
                        'colorOrder' => 'RAW',
                        'groupCount' => 1,
                        'nullNodes' => 0,
                        'coEnabled' => $enabled,
                    );
                }
            }
        } elseif (isset($co['channelCount']) && (int)$co['channelCount'] > 0) {
            // Whole-output fallback (e.g. DDP/E1.31/generic outputs with no per-port breakdown).
            $ports[] = array(
                'id' => md5($type . '|whole|' . $baseStart),
                'type' => $type,
                'port' => 0,
                'vstring' => 0,
                'description' => $type,
                'protocol' => $type,
                'startChannel' => $baseStart,
                'dataStartChannel' => $baseStart,
                'endChannel' => $baseStart + (int)$co['channelCount'] - 1,
                'pixelCount' => (int)$co['channelCount'],
                'addressablePixels' => (int)$co['channelCount'],
                'channelsPerPixel' => 1,
                'colorOrder' => 'RAW',
                'groupCount' => 1,
                'nullNodes' => 0,
                'coEnabled' => $enabled,
            );
        }
    }

    return $ports;
}
