<?php 

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PriceFetcherService 
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $twelveDataApiKey
    ) {}

    private function searchAssetsFromYahoo(string $query): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://query2.finance.yahoo.com/v1/finance/search', [
                'query' => [
                    'q' => $query,
                    'quotesCount' => 8,
                    'newsCount' => 0,
                ],
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
            ]);

            $data = $response->toArray();

            if (empty($data['quotes'])) return [];

            $typeMap = [
                'EQUITY' => 'Common Stock',
                'ETF'    => 'ETF',
            ];

            return array_values(array_filter(array_map(function ($item) use ($typeMap) {
                $quoteType = $item['quoteType'] ?? '';
                if (!isset($typeMap[$quoteType])) return null; // ignore les types non pertinents (indices, options...)

                return [
                    'symbol'   => $item['symbol'],
                    'name'     => $item['longname'] ?? $item['shortname'] ?? $item['symbol'],
                    'exchange' => $item['exchange'] ?? '',
                    'type'     => $typeMap[$quoteType],
                ];
            }, $data['quotes'])));
        } catch (\Exception $e) {
            return [];
        }
    }

    public function searchAssets(string $query, string $type = 'stock'): array
    {
        if ($type === 'crypto') {
            return $this->searchCryptos($query);
        }

        $yahooResults = $this->searchAssetsFromYahoo($query);
        if (!empty($yahooResults)) {
            return $yahooResults;
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.twelvedata.com/symbol_search', [
                'query' => [
                    'symbol' => $query,
                    'apikey' => $this->twelveDataApiKey,
                ],
            ]);

            $data = $response->toArray();

            if (empty($data['data'])) return [];

            return array_slice(array_map(fn($item) => [
                'symbol'   => $item['symbol'],
                'name'     => $item['instrument_name'],
                'exchange' => $item['exchange'],
                'type'     => $item['instrument_type'],
            ], $data['data']), 0, 8);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function searchCryptos(string $query): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.coingecko.com/api/v3/search', [
                'query' => ['query' => $query],
            ]);

            $data = $response->toArray();

            if (empty($data['coins'])) return [];

            return array_slice(array_map(fn($coin) => [
                'symbol'   => $coin['id'],     // ex: "bitcoin" — utilisé pour fetchCryptoPrice
                'name'     => $coin['name'],    // ex: "Bitcoin"
                'exchange' => $coin['symbol'],  // ex: "BTC" — affiché comme tag
                'type'     => 'Digital Currency',
            ], $data['coins']), 0, 8);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function fetchStockPriceFromYahoo(string $ticker): ?float
    {
        try {
            $response = $this->httpClient->request('GET', "https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}", [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
            ]);

            $data = $response->toArray();

            $price = $data['chart']['result'][0]['meta']['regularMarketPrice'] ?? null;

            return $price !== null ? (float) $price : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function fetchStockPrice(string $ticker): ?float
    {
        $price = $this->fetchStockPriceFromYahoo($ticker);
        if ($price !== null) {
            return $price;
        }
        try {
            $response = $this->httpClient->request('GET', 'https://api.twelvedata.com/price', [
                'query' => [
                    'symbol' => $ticker,
                    'apikey' => $this->twelveDataApiKey,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['price'])) return null;

            return (float) $data['price'];
        } catch (\Exception $e) {
            // Log the error or handle it as needed
            return null;
        }
    }
    
    public function fetchCryptoPrice(string $coinId): ?float
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.coingecko.com/api/v3/simple/price', [
                'query' => [
                'ids'           => $coinId,
                'vs_currencies' => 'eur',
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data[$coinId]['eur'])) return null;

            return (float) $data[$coinId]['eur'];
        } catch (\Exception $e) {
            // Log the error or handle it as needed
            return null;
        }
    }
}