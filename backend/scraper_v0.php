
<?php

/**
 * CryptoScrape
 * Cryptocurrency market scraper
 * Source: CoinMarketCap
 */

// -----------------------------------------
// 1. Configuration
// -----------------------------------------

$url = "https://coinmarketcap.com/";


// -----------------------------------------
// 2. Fetch HTML using cURL
// -----------------------------------------

function fetchPage($url)
{
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

    if ($html === false) {
        $error = curl_error($ch);
        curl_close($ch);

        throw new Exception("Request failed: " . $error);
    }

    curl_close($ch);

    if ($statusCode !== 200) {
        throw new Exception("HTTP Error: " . $statusCode);
    }

    return $html;
}


// -----------------------------------------
// 3. Clean text
// -----------------------------------------

function cleanText($value)
{
    return trim(preg_replace('/\s+/', ' ', $value));
}


// -----------------------------------------
// 4. Convert market values into numbers
// -----------------------------------------

function parseMarketValue($value)
{
    $value = strtoupper(str_replace(',', '', trim($value)));

    if (!preg_match('/\$?\s*(-?\d+(?:\.\d+)?)\s*([KMBT])?/i', $value, $matches)) {
        return null;
    }

    $number = (float) $matches[1];

    $suffix = $matches[2] ?? '';

    $multipliers = [
        'K' => 1000,
        'M' => 1000000,
        'B' => 1000000000,
        'T' => 1000000000000
    ];

    return $number * ($multipliers[$suffix] ?? 1);
}


// -----------------------------------------
// 5. Extract percentage values
// -----------------------------------------

function parsePercentage($value)
{
    if (preg_match('/-?\d+(?:\.\d+)?/', $value, $matches)) {
        return (float) $matches[0];
    }

    return null;
}


// -----------------------------------------
// 6. Extract cryptocurrency information
// -----------------------------------------

function scrapeCryptocurrencies($html)
{
    $dom = new DOMDocument();

    libxml_use_internal_errors(true);

    $dom->loadHTML($html);

    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    $rows = $xpath->query('//table//tbody/tr');

    $cryptocurrencies = [];

    foreach ($rows as $row) {

        $cells = $xpath->query('./td', $row);

        if ($cells->length < 11) {
            continue;
        }

        // Rank
        $rankText = cleanText($cells->item(1)->textContent);

        // Skip non-cryptocurrency rows
        if (!ctype_digit($rankText)) {
            continue;
        }

        // Cryptocurrency name and symbol
        $coinCell = $cells->item(2);

        $nameNode = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " coin-item-name ")]',
            $coinCell
        );

        $symbolNode = $xpath->query(
            './/*[contains(concat(" ", normalize-space(@class), " "), " coin-item-symbol ")]',
            $coinCell
        );

        if ($nameNode->length === 0 || $symbolNode->length === 0) {
            continue;
        }

        $name = cleanText($nameNode->item(0)->textContent);

        $symbol = cleanText($symbolNode->item(0)->textContent);

        // Extract remaining columns
        $price = cleanText($cells->item(3)->textContent);

        $change1h = cleanText($cells->item(4)->textContent);

        $change24h = cleanText($cells->item(5)->textContent);

        $change7d = cleanText($cells->item(6)->textContent);

        $marketCap = cleanText($cells->item(7)->textContent);

        $volume = cleanText($cells->item(8)->textContent);

        $supply = cleanText($cells->item(9)->textContent);

        $sentiment = cleanText($cells->item(10)->textContent);

        // Store structured data
        $cryptocurrencies[] = [

            'rank' => (int) $rankText,

            'name' => $name,

            'symbol' => $symbol,

            'price' => parseMarketValue($price),

            'change_1h' => parsePercentage($change1h),

            'change_24h' => parsePercentage($change24h),

            'change_7d' => parsePercentage($change7d),

            'market_cap' => parseMarketValue($marketCap),

            'volume_24h' => parseMarketValue($volume),

            'circulating_supply' => $supply,

            'sentiment' => $sentiment

        ];
    }

    return $cryptocurrencies;
}


// -----------------------------------------
// 7. Main execution
// -----------------------------------------

try {

    echo "Fetching CoinMarketCap data...\n";

    $html = fetchPage($url);

    echo "HTML retrieved successfully!\n";

    $data = scrapeCryptocurrencies($html);

    if (empty($data)) {
        throw new Exception("No cryptocurrency data extracted.");
    }

    $response = [
        'success' => true,
        'source' => 'CoinMarketCap',
        'scraped_at' => date('Y-m-d H:i:s'),
        'total_coins' => count($data),
        'data' => $data
    ];

    echo "\nTotal cryptocurrencies extracted: " . count($data) . "\n\n";

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

} catch (Throwable $e) {

    echo "Error: " . $e->getMessage();

}

?>
