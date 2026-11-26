
<?php

/**
 * CryptoScrape
 * CoinMarketCap cryptocurrency listing scraper.
 *
 * Default: 100 cryptocurrencies (1 page).
 * Maximum: 500 cryptocurrencies (5 pages).
 *
 * Usage:
 * php scraper.php
 * set CRYPTOSCRAPE_PAGES=2 && php scraper.php
 */

// -----------------------------------------
// 1. Configuration
// -----------------------------------------

$homepageUrl = 'https://coinmarketcap.com/';

$pageSize = 100;

$pageCount = filter_var(
    getenv('CRYPTOSCRAPE_PAGES') ?: '1',
    FILTER_VALIDATE_INT
);

$pageCount = ($pageCount !== false && $pageCount > 0)
    ? min($pageCount, 5)
    : 1;


// -----------------------------------------
// 2. Fetch HTML / API response using cURL
// -----------------------------------------

function fetchPage(string $url): string
{
    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_USERAGENT =>
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0 Safari/537.36',

        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/json'
        ],
    ]);

    $body = curl_exec($curl);

    if ($body === false) {
        $error = curl_error($curl);
        curl_close($curl);

        throw new RuntimeException(
            'Connection failed: ' . $error
        );
    }

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($status !== 200) {
        throw new RuntimeException(
            'CoinMarketCap returned HTTP ' . $status . ': ' . $url
        );
    }

    return $body;
}


// -----------------------------------------
// 3. Clean extracted text
// -----------------------------------------

function cleanText($value): string
{
    return trim(
        preg_replace('/\s+/u', ' ', (string) $value) ?? ''
    );
}


// -----------------------------------------
// 4. Convert values into numeric format
// -----------------------------------------

function parseNumber($value): ?float
{
    if (is_int($value) || is_float($value)) {
        return is_finite((float) $value)
            ? (float) $value
            : null;
    }

    if (!is_string($value)) {
        return null;
    }

    $value = str_replace(
        [',', '$', '%', ' '],
        '',
        $value
    );

    if (!is_numeric($value)) {
        return null;
    }

    $number = (float) $value;

    return is_finite($number) ? $number : null;
}


// -----------------------------------------
// 5. Get first available value from an array
// -----------------------------------------

function firstValue(array $record, array $keys)
{
    foreach ($keys as $key) {

        if (
            array_key_exists($key, $record) &&
            $record[$key] !== null &&
            $record[$key] !== ''
        ) {
            return $record[$key];
        }
    }

    return null;
}


// -----------------------------------------
// 6. Extract first-page listing from HTML
// -----------------------------------------

function extractHomepageListing(string $html): array
{
    if (!preg_match(
        '~<script[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)</script>~s',
        $html,
        $matches
    )) {
        throw new RuntimeException(
            'CoinMarketCap __NEXT_DATA__ payload not found.'
        );
    }

    $nextData = json_decode($matches[1], true);

    if (!is_array($nextData)) {
        throw new RuntimeException(
            'Could not decode __NEXT_DATA__: ' .
            json_last_error_msg()
        );
    }

    $queries =
        $nextData['props']['dehydratedState']['queries'] ?? null;

    if (!is_array($queries)) {
        throw new RuntimeException(
            'No dehydrated query list found.'
        );
    }

    foreach ($queries as $query) {

        $queryKey = $query['queryKey'] ?? [];

        if (($queryKey[0] ?? null) !== 'homepage-data') {
            continue;
        }

        $listing =
            $query['state']['data']['data']['listing'] ?? null;

        if (!is_array($listing)) {
            throw new RuntimeException(
                'Homepage query is missing listing data.'
            );
        }

        $coins = $listing['cryptoCurrencyList'] ?? null;

        if (!is_array($coins) || empty($coins)) {
            throw new RuntimeException(
                'No cryptocurrency records found on page one.'
            );
        }

        return [
            'coins' => $coins,
            'total_count' => isset($listing['totalCount'])
                ? (int) $listing['totalCount']
                : null,
        ];
    }

    throw new RuntimeException(
        'homepage-data query not found.'
    );
}


// -----------------------------------------
// 7. Extract subsequent pages from API JSON
// -----------------------------------------

function extractApiListing(string $json): array
{
    $decoded = json_decode($json, true);

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'Invalid API JSON: ' . json_last_error_msg()
        );
    }

    $listing =
        $decoded['data']['data']['listing'] ??
        $decoded['data']['listing'] ??
        $decoded['data'] ??
        null;

    if (!is_array($listing)) {
        throw new RuntimeException(
            'API response does not contain listing data.'
        );
    }

    $coins = $listing['cryptoCurrencyList'] ?? null;

    if (!is_array($coins) || empty($coins)) {
        throw new RuntimeException(
            'No cryptocurrency records returned by API.'
        );
    }

    return [
        'coins' => $coins,
        'total_count' => isset($listing['totalCount'])
            ? (int) $listing['totalCount']
            : null,
    ];
}


// -----------------------------------------
// 8. Map cryptocurrency data
// -----------------------------------------

function mapCoin(array $coin): array
{
    $quotes = $coin['quotes'] ?? $coin['quote'] ?? [];

    $quote = [];

    if (is_array($quotes)) {

        foreach ($quotes as $candidate) {

            if (!is_array($candidate)) {
                continue;
            }

            $currency = strtoupper(
                (string) (
                    $candidate['name'] ??
                    $candidate['symbol'] ??
                    ''
                )
            );

            if ($currency === 'USD') {
                $quote = $candidate;
                break;
            }
        }

        if (!$quote) {
            $quote = $quotes[0] ?? $quotes;
        }
    }

    if (isset($quote['USD']) && is_array($quote['USD'])) {
        $quote = $quote['USD'];
    }

    if (!is_array($quote)) {
        $quote = [];
    }

    $rank = firstValue(
        $coin,
        ['cmcRank', 'rank', 'cmc_rank']
    );

    $supply = firstValue(
        $coin,
        [
            'circulatingSupply',
            'circulating_supply',
            'circulatingSupplyStr'
        ]
    );

    if (is_numeric($supply)) {
        $supply = (string) $supply;
    }

    return [

        'rank' => is_numeric($rank)
            ? (int) $rank
            : null,

        'name' => firstValue($coin, ['name']) !== null
            ? cleanText(firstValue($coin, ['name']))
            : null,

        'symbol' => firstValue($coin, ['symbol']) !== null
            ? cleanText(firstValue($coin, ['symbol']))
            : null,

        'price' => parseNumber(
            firstValue($quote, ['price', 'p'])
        ),

        'change_1h' => parseNumber(
            firstValue($quote, [
                'percentChange1h',
                'percent_change_1h',
                'p1h'
            ])
        ),

        'change_24h' => parseNumber(
            firstValue($quote, [
                'percentChange24h',
                'percent_change_24h',
                'p24h'
            ])
        ),

        'change_7d' => parseNumber(
            firstValue($quote, [
                'percentChange7d',
                'percent_change_7d',
                'p7d'
            ])
        ),

        'market_cap' => parseNumber(
            firstValue($quote, [
                'marketCap',
                'market_cap'
            ])
        ),

        'volume_24h' => parseNumber(
            firstValue($quote, [
                'volume24h',
                'volume_24h'
            ])
        ),

        'circulating_supply' => $supply,

        'sentiment' => firstValue(
            $coin,
            ['sentiment']
        ),
    ];
}


// -----------------------------------------
// 9. Main scraper
// -----------------------------------------

try {

    // Fetch first page from homepage HTML
    $firstPage = extractHomepageListing(
        fetchPage($homepageUrl)
    );

    $records = $firstPage['coins'];

    $totalAvailable = $firstPage['total_count'];

    $pageErrors = [];


    // -------------------------------------
    // 10. Pagination
    // -------------------------------------

    for ($page = 2; $page <= $pageCount; $page++) {

        $start = (($page - 1) * $pageSize) + 1;

        $apiUrl =
            'https://api.coinmarketcap.com/data-api/v3/cryptocurrency/listing?' .
            http_build_query([
                'start' => $start,
                'limit' => $pageSize,
                'convert' => 'USD',
                'sortBy' => 'market_cap',
                'sortType' => 'desc',
                'cryptoType' => 'all',
            ]);

        try {

            echo "Fetching page " . $page . "...\n";

            $pageData = extractApiListing(
                fetchPage($apiUrl)
            );

            foreach ($pageData['coins'] as $coin) {
                $records[] = $coin;
            }

            if ($pageData['total_count'] !== null) {
                $totalAvailable = $pageData['total_count'];
            }

            echo "Page " . $page . " retrieved successfully: "
                . count($pageData['coins']) . " records\n";

        } catch (Throwable $error) {

            $pageErrors[] =
                'Page ' . $page . ': ' . $error->getMessage();

            echo "Page " . $page . " failed.\n";
        }
    }


    // -------------------------------------
    // 11. Map and remove duplicate records
    // -------------------------------------

    $data = [];

    $seen = [];

    foreach ($records as $coin) {

        if (!is_array($coin)) {
            continue;
        }

        $mapped = mapCoin($coin);

        if (
            $mapped['name'] === null ||
            $mapped['symbol'] === null
        ) {
            continue;
        }

        $identity = (string) (
            $coin['id'] ??
            $mapped['rank'] ??
            strtolower($mapped['symbol'])
        );

        if (isset($seen[$identity])) {
            continue;
        }

        $seen[$identity] = true;

        $data[] = $mapped;
    }

    if (empty($data)) {
        throw new RuntimeException(
            'No valid cryptocurrency records extracted.'
        );
    }


    // -------------------------------------
    // 12. Generate final JSON
    // -------------------------------------

    $response = [

        'success' => true,

        'source' => 'CoinMarketCap',

        'scraped_at' => date('Y-m-d H:i:s'),

        'total_coins' => count($data),

        'total_available' => $totalAvailable,

        'pages_requested' => $pageCount,

        'pages_failed' => $pageErrors,

        'data' => $data,
    ];


    // echo "\nJSON OUTPUT:\n";

    echo json_encode(
        $response,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES |
        JSON_THROW_ON_ERROR
    ) . PHP_EOL;


} catch (Throwable $error) {

    fwrite(
        STDERR,
        json_encode([
            'success' => false,
            'source' => 'CoinMarketCap',
            'error' => $error->getMessage(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
    );

    exit(1);
}
