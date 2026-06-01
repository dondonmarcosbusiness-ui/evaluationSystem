<?php
$key = 'AIzaSyB8h3YBMtkIAEsMF7_wKHAnCUDtAXKHhCk';
$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?key=' . $key);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$data = json_decode($res, true);
if (isset($data['models'])) {
    foreach($data['models'] as $m) {
        if (strpos($m['name'], 'gemini') !== false) {
            echo $m['name'] . "\n";
        }
    }
} else {
    print_r($data);
}
