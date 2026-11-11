
<?php

/**
 * CryptoScrape
 * Cryptocurrency market scraper using PHP.
 * Source: CoinMarketCap
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

const SOURCE_URL = 'https://coinmarketcap.com/';


// 1. Fetch webpage using cURL
function fetchPage(string $url): string
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9'
        ]
    ]);

    $html = curl_exec($ch);

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($html === false) {
        throw new Exception("Request failed: " . $error);
    }

    if ($statusCode !== 200) {
        throw new Exception("CoinMarketCap returned HTTP $statusCode");
    }

    return $html;
}


// 2. Clean HTML text
function cleanText(string $text): string
{
    return trim(preg_replace('/\s+/', ' ', $text));
}


// 3. Convert formatted market values into numbers
function parseMetric(string $value): ?float
{
    $value = str_replace(',', '', $value);

    if (preg_match('/(-?\d+(?:\.\d+)?)\s*([KMBT])?/i', $value, $matches)) {

        $number = (float) $matches[1];

        $suffix = strtoupper($matches[2] ?? '');

        $multipliers = [
            'K' => 1000,
            'M' => 1000000,
            'B' => 1000000000,
            'T' => 1000000000000
        ];

        return $number * ($multipliers[$suffix] ?? 1);
    }

    return null;
}


// 4. Extract percentage changes
function parsePercentage(string $value): ?float
{
    if (preg_match('/-?\d+(?:\.\d+)?/', $value, $matches)) {
        return (float) $matches[0];
    }

    return null;
}


// 5. Parse cryptocurrency table
function parseCryptocurrencies(string $html, int $limit = 20): array
{
    $dom = new DOMDocument();

    libxml_use_internal_errors(true);

    $dom->loadHTML($html);

    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    $rows = $xpath->query('//table/tbody/tr');

    $cryptocurrencies = [];

    foreach ($rows as $row) {

        $cells = $xpath->query('./td', $row);

        // Expected columns:
        // Rank, Name, Price, 1h, 24h, 7d,
        // Market Cap, Volume, Circulating Supply

        if ($cells->length < 8) {
            continue;
        }

        $rankText = cleanText($cells->item(0)->textContent);

        $coinCell = $cells->item(1);

        $coinText = cleanText($coinCell->textContent);

        // Find cryptocurrency link
        $links = $xpath->query(
            './/a[contains(@href, "/currencies/")]',
            $coinCell
        );

        $name = null;

        if ($links->length > 0) {
            $name = cleanText($links->item(0)->textContent);
        }

        // Extract symbol from the coin cell
        $symbol = null;

        if (preg_match('/\b([A-Z0-9]{2,12})\b/', $coinText, $matches)) {
            $symbol = $matches[1];
        }

        if (!$name || !$symbol) {
            continue;
        }

        $priceText = cleanText($cells->item(2)->textContent);

        $change1h = cleanText($cells->item(3)->textContent);

        $change24h = cleanText($cells->item(4)->textContent);

        $change7d = cleanText($cells->item(5)->textContent);

        $marketCap = cleanText($cells->item(6)->textContent);

        $volume = cleanText($cells->item(7)->textContent);

        $cryptocurrencies[] = [

            'rank' => (int) preg_replace('/\D/', '', $rankText),

            'name' => $name,

            'symbol' => $symbol,

            'price' => parseMetric($priceText),

            'price_display' => $priceText,

            'change_1h' => parsePercentage($change1h),

            'change_24h' => parsePercentage($change24h),

            'change_7d' => parsePercentage($change7d),

            'market_cap' => parseMetric($marketCap),

            'market_cap_display' => $marketCap,

            'volume_24h' => parseMetric($volume),

            'volume_24h_display' => $volume

        ];

        if (count($cryptocurrencies) >= $limit) {
            break;
        }
    }

    return $cryptocurrencies;
}


// 6. Main execution
try {

    $limit = isset($_GET['limit'])
        ? max(1, min(100, (int) $_GET['limit']))
        : 20;

    echo "Fetching CoinMarketCap data...\n";

    $html = fetchPage(SOURCE_URL);

    $data = parseCryptocurrencies($html, $limit);

    if (empty($data)) {
        throw new Exception(
            "No cryptocurrency data extracted. The website may have changed its HTML structure or blocked the request."
        );
    }

    $response = [
        'success' => true,
        'source' => 'CoinMarketCap',
        'scraped_at' => date('Y-m-d H:i:s'),
        'total_coins' => count($data),
        'data' => $data
    ];

    $json = json_encode(
        $response,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        throw new Exception("JSON encoding failed.");
    }

    // Output JSON
    header('Content-Type: application/json');

    echo $json;

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);

}
