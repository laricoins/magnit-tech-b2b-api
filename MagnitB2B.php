<?php

namespace MagnitB2B;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class MagnitB2BClient
{
    private Client $client;
    private ?string $accessToken = null;
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private array $defaultHeaders = [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];

    public function __construct(
        string $clientId,
        string $clientSecret,
        bool $useDemo = false,
        array $clientOptions = []
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->baseUrl = $useDemo 
            ? 'https://b2b-api-gateway.uat.ya.magnit.ru/api'
            : 'https://b2b-api.magnit.ru/api';

        $this->client = new Client(array_merge([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'verify' => true,
        ], $clientOptions));
    }

    /**
     * Получение токена авторизации (v2)
     */
    public function getAuthToken(array $scopes = ['openid', 'last-mile:claims']): array
    {
        try {
            $response = $this->client->post('/v2/oauth/token', [
                RequestOptions::FORM_PARAMS => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => implode(' ', $scopes),
                    'grant_type' => 'client_credentials',
                ],
                RequestOptions::HEADERS => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $this->accessToken = $data['access_token'] ?? null;
            
            return $data;
        } catch (GuzzleException $e) {
            throw new Exception("Failed to get auth token: " . $e->getMessage());
        }
    }

    /**
     * Создание заказа
     */
    public function createOrder(array $orderData): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->post('/v1/orders', [
            RequestOptions::JSON => $orderData,
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Получение информации о заказе
     */
    public function getOrder(string $orderId): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->get("/v1/orders/{$orderId}", [
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Отмена заказа
     */
    public function cancelOrder(string $orderId, string $reason, ?string $cancelledAt = null): bool
    {
        $this->ensureAuthenticated();

        $data = ['reason' => $reason];
        if ($cancelledAt) {
            $data['cancelled_at'] = $cancelledAt;
        }

        $response = $this->client->post("/v1/orders/{$orderId}/cancel", [
            RequestOptions::JSON => $data,
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return $response->getStatusCode() === 200;
    }

    /**
     * Получение статуса заказа
     */
    public function getOrderStatus(string $orderId): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->get("/v1/orders/{$orderId}/status", [
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Изменение статуса заказа
     */
    public function updateOrderStatus(string $orderId, array $statusData): bool
    {
        $this->ensureAuthenticated();

        $response = $this->client->put("/v1/orders/{$orderId}/status", [
            RequestOptions::JSON => $statusData,
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return $response->getStatusCode() === 202;
    }

    /**
     * Отправка события по заказу
     */
    public function sendOrderEvent(string $orderId, array $eventData): bool
    {
        $this->ensureAuthenticated();

        $response = $this->client->post("/v1/orders/{$orderId}/event", [
            RequestOptions::JSON => $eventData,
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return $response->getStatusCode() === 202;
    }

    /**
     * Получение цен товаров в торговом объекте
     */
    public function getStorePrices(string $storeId): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->get("/v1/nomenclature/stores/{$storeId}/prices", [
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Получение остатков товаров в торговом объекте
     */
    public function getStoreStocks(string $storeId): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->get("/v1/nomenclature/stores/{$storeId}/stocks", [
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Получение дельты остатков товаров
     */
    public function getStoreStocksDelta(string $storeId, int $timestampFrom): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->get("/v1/nomenclature/stores/{$storeId}/stocks_delta", [
            RequestOptions::QUERY => ['timestamp_from' => $timestampFrom],
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Создание заявки на доставку (Last Mile)
     */
    public function createDeliveryClaim(string $requestId, string $partnerId, array $claimData): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->post('/v1/last-mile/claims/create', [
            RequestOptions::QUERY => ['request_id' => $requestId],
            RequestOptions::JSON => $claimData,
            RequestOptions::HEADERS => array_merge($this->getAuthHeaders(), [
                'X-Partner-ID' => $partnerId,
            ]),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Отмена заявки на доставку
     */
    public function cancelDeliveryClaim(string $partnerId, array $cancelData): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->post('/v1/last-mile/claims/cancel', [
            RequestOptions::JSON => $cancelData,
            RequestOptions::HEADERS => array_merge($this->getAuthHeaders(), [
                'X-Partner-ID' => $partnerId,
            ]),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Получение информации по заявкам на доставку
     */
    public function getDeliveryClaimsInfo(string $partnerId, array $claimIds): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->post('/v1/last-mile/claims/info', [
            RequestOptions::JSON => ['claim_ids' => $claimIds],
            RequestOptions::HEADERS => array_merge($this->getAuthHeaders(), [
                'X-Partner-ID' => $partnerId,
            ]),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Получение событий по заявкам на доставку
     */
    public function getDeliveryClaimsEvents(string $partnerId, ?string $lastKnownId = null, ?int $limit = 1000): array
    {
        $this->ensureAuthenticated();

        $query = [];
        if ($lastKnownId) $query['last_known_id'] = $lastKnownId;
        if ($limit) $query['limit'] = $limit;

        $response = $this->client->get('/v1/last-mile/claims/events', [
            RequestOptions::QUERY => $query,
            RequestOptions::HEADERS => array_merge($this->getAuthHeaders(), [
                'X-Partner-ID' => $partnerId,
            ]),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Получение списка пунктов выдачи заказов (Magnit Post)
     */
    public function getPickupPoints(
        int $page = 1,
        int $size = 100,
        ?string $key = null,
        ?string $region = null,
        ?string $city = null
    ): array {
        $this->ensureAuthenticated();

        $query = [
            'page' => $page,
            'size' => $size,
        ];

        if ($key) $query['key'] = $key;
        if ($region) $query['region'] = $region;
        if ($city) $query['city'] = $city;

        $response = $this->client->get('/v1/magnit-post/pickup-points', [
            RequestOptions::QUERY => $query,
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Создание заказа на доставку (Magnit Post)
     */
    public function createDeliveryOrder(array $orderData): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->post('/v1/magnit-post/orders', [
            RequestOptions::JSON => $orderData,
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Получение информации о заказе доставки
     */
    public function getDeliveryOrder(string $trackingNumber): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->get("/v1/magnit-post/orders/{$trackingNumber}", [
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Отмена заказа доставки
     */
    public function cancelDeliveryOrder(string $trackingNumber): bool
    {
        $this->ensureAuthenticated();

        $response = $this->client->delete("/v1/magnit-post/orders/{$trackingNumber}", [
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return $response->getStatusCode() === 204;
    }

    /**
     * Получение истории статусов заказа доставки
     */
    public function getDeliveryOrderStatusHistory(string $trackingNumber): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->get("/v1/magnit-post/orders/{$trackingNumber}/status-history", [
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Получение статусов нескольких заказов доставки
     */
    public function getDeliveryOrdersStatuses(array $trackingNumbers): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->post('/v1/magnit-post/order-statuses', [
            RequestOptions::JSON => ['trackingNumbers' => $trackingNumbers],
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Расчет стоимости и срока доставки (Magnit Post v2)
     */
    public function estimateDeliveryOrder(array $estimateData): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->post('/v2/magnit-post/orders/estimate', [
            RequestOptions::JSON => $estimateData,
            RequestOptions::HEADERS => $this->getAuthHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Получение списка категорий товаров (Magnit Market)
     */
    public function getCategories(): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->get('/seller/v1/categories', [
            RequestOptions::HEADERS => $this->getMarketHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Получение характеристик категорий
     */
    public function getCategoryCharacteristics(array $categoryIds): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->post('/seller/v1/products/defined-characteristics', [
            RequestOptions::JSON => ['category_ids' => $categoryIds],
            RequestOptions::HEADERS => $this->getMarketHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Создание SKU товаров
     */
    public function createSku(array $skuList): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->post('/seller/v1/products/sku', [
            RequestOptions::JSON => ['sku_list' => $skuList],
            RequestOptions::HEADERS => $this->getMarketHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Обновление цен товаров
     */
    public function updatePrices(array $prices): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->post('/seller/v1/products/sku/price', [
            RequestOptions::JSON => ['prices' => $prices],
            RequestOptions::HEADERS => $this->getMarketHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Обновление остатков товаров
     */
    public function updateStocks(array $stocks): bool
    {
        $this->ensureAuthenticated();

        $response = $this->client->post('/seller/v1/products/sku/stocks', [
            RequestOptions::JSON => ['stocks' => $stocks],
            RequestOptions::HEADERS => $this->getMarketHeaders(),
        ]);

        return $response->getStatusCode() === 200;
    }

    /**
     * Получение списка магазинов
     */
    public function getShops(): array
    {
        $this->ensureAuthenticated();

        $response = $this->client->get('/seller/v1/shops', [
            RequestOptions::HEADERS => $this->getMarketHeaders(),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Вспомогательные методы
     */
    private function ensureAuthenticated(): void
    {
        if (!$this->accessToken) {
            $this->getAuthToken();
        }
    }

    private function getAuthHeaders(): array
    {
        return array_merge($this->defaultHeaders, [
            'Authorization' => 'Bearer ' . $this->accessToken,
        ]);
    }

    private function getMarketHeaders(): array
    {
        return array_merge($this->defaultHeaders, [
            'x-api-key' => $this->accessToken,
        ]);
    }

    public function setAccessToken(string $token): void
    {
        $this->accessToken = $token;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}

/**
 * Класс для построения заказов
 */
class OrderBuilder
{
    private array $order = [];

    public function setOriginalOrderId(string $id): self
    {
        $this->order['original_order_id'] = $id;
        return $this;
    }

    public function setStoreCode(string $code): self
    {
        $this->order['store_code'] = $code;
        return $this;
    }

    public function setCustomer(string $name, string $phone): self
    {
        $this->order['customer'] = [
            'name' => $name,
            'phone' => $this->normalizePhone($phone),
        ];
        return $this;
    }

    public function setDelivery(array $deliveryData): self
    {
        $this->order['delivery'] = $deliveryData;
        return $this;
    }

    public function setCollect(array $collectData): self
    {
        $this->order['collect'] = $collectData;
        return $this;
    }

    public function setCart(array $cartItems): self
    {
        $this->order['cart'] = ['items' => $cartItems];
        return $this;
    }

    public function setPrice(float $total, string $currency = 'RUB'): self
    {
        $this->order['price'] = [
            'total' => [
                'value' => (int)($total * 100), // Convert to kopecks
                'currency' => $currency,
            ],
        ];
        return $this;
    }

    public function setComment(string $comment): self
    {
        $this->order['comment'] = $comment;
        return $this;
    }

    public function build(): array
    {
        // Обязательные поля
        $required = ['original_order_id', 'store_code', 'customer', 'collect', 'cart', 'price'];
        foreach ($required as $field) {
            if (!isset($this->order[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        return $this->order;
    }

    private function normalizePhone(string $phone): string
    {
        // Нормализация телефонного номера
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strpos($phone, '+') !== 0) {
            $phone = '+7' . substr($phone, -10);
        }
        return $phone;
    }
}

/**
 * Класс для построения элементов корзины
 */
class CartItemBuilder
{
    private array $item = [];

    public function setGoodId(string $goodId): self
    {
        $this->item['good_id'] = $goodId;
        return $this;
    }

    public function setName(string $name): self
    {
        $this->item['name'] = $name;
        return $this;
    }

    public function setQuantity(int $quantity, string $unit = 'apiece'): self
    {
        $this->item['qnty'] = $quantity;
        $this->item['unit'] = $unit;
        return $this;
    }

    public function setPrice(float $price, string $currency = 'RUB'): self
    {
        $this->item['price'] = [
            'original' => [
                'value' => (int)($price * 100), // Convert to kopecks
                'currency' => $currency,
            ],
        ];
        return $this;
    }

    public function setMarking(array $markingData): self
    {
        $this->item['marking'] = $markingData;
        return $this;
    }

    public function build(): array
    {
        // Обязательные поля
        $required = ['good_id', 'name', 'qnty', 'unit', 'price'];
        foreach ($required as $field) {
            if (!isset($this->item[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        return $this->item;
    }
}

/**
 * Класс для построения данных доставки
 */
class DeliveryBuilder
{
    private array $delivery = [];

    public function setTimeSlot(string $from, string $to): self
    {
        $this->delivery['time_slot'] = [
            'from' => $from,
            'to' => $to,
        ];
        return $this;
    }

    public function setAddress(array $addressData): self
    {
        $this->delivery['address'] = $addressData;
        return $this;
    }

    public function setCoordinates(float $lat, float $lng): self
    {
        $this->delivery['coordinates'] = [
            'lat' => $lat,
            'lng' => $lng,
        ];
        return $this;
    }

    public function setPrice(float $price, string $currency = 'RUB'): self
    {
        $this->delivery['price'] = [
            'base' => [
                'value' => (int)($price * 100),
                'currency' => $currency,
            ],
        ];
        return $this;
    }

    public function build(): array
    {
        return $this->delivery;
    }
}

/**
 * Класс для построения данных сборки
 */
class CollectBuilder
{
    private array $collect = [];

    public function setStrategy(string $strategy): self
    {
        $this->collect['strategy'] = $strategy;
        return $this;
    }

    public function setDesiredAt(string $datetime): self
    {
        $this->collect['desired_at'] = $datetime;
        return $this;
    }

    public function build(): array
    {
        if (!isset($this->collect['strategy'])) {
            throw new Exception("Strategy is required");
        }

        return $this->collect;
    }
}