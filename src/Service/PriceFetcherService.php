<?php 

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PriceFetcherService 
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $twelveDataApiKey
    ) {}

    public function searchAssets(string $query): array
    {
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
            // Log the error or handle it as needed
            return [];

        }
    }

    public function fetchStockPrice(string $ticker): ?float
    {
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