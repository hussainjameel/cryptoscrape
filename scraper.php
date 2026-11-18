<?php

$url = "https://coinmarketcap.com/";

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => "Mozilla/5.0"
]);

$html = curl_exec($ch);

$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$error = curl_error($ch);

curl_close($ch);

echo "HTTP Status: " . $statusCode . PHP_EOL;

if ($html === false) {
    die("Request failed: " . $error);
}

echo "HTML Length: " . strlen($html) . " bytes" . PHP_EOL;

echo "\nFirst 1000 characters:\n";

echo substr($html, 0, 1000);

?>