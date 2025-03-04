<?php
$urls = [
    'http://localhost:8080/health',
    'http://localhost:8000/up',
];

foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "URL: $url is healthy (HTTP $httpCode)\n";
    } else {
        echo "URL: $url is NOT healthy (HTTP $httpCode)\n";
    }
}
