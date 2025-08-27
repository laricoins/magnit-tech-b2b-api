Полную интеграцию со всеми основными endpoints API Magnit B2B

Поддержку всех сервисов: Orders, Nomenclature, Last Mile, Magnit Post, Magnit Market

Builder-классы для удобного создания сложных структур данных

Обработку ошибок и валидацию данных

Автоматическое управление токенами

Нормализацию данных (телефоны, цены в копейках)

Гибкие методы для работы с различными типами запросов

Класс готов к использованию в production-среде и покрывает все основные сценарии работы с API Magnit.
если что ...  https://t.me/ddnitecry



Вот несколько примеров использования PHP класса для работы с Magnit B2B API:

1. Базовая инициализация и авторизация
php
<?php

require_once 'MagnitB2BClient.php';

use MagnitB2B\MagnitB2BClient;
use MagnitB2B\OrderBuilder;
use MagnitB2B\CartItemBuilder;
use MagnitB2B\DeliveryBuilder;
use MagnitB2B\CollectBuilder;

// Инициализация клиента
$client = new MagnitB2BClient(
    'your-client-id-here',
    'your-client-secret-here',
    true // Использовать демо-стенд для тестирования
);

try {
    // Авторизация
    $tokenData = $client->getAuthToken();
    echo "Токен получен: " . $tokenData['access_token'] . "\n";
    echo "Срок действия: " . $tokenData['expires_in'] . " секунд\n";

} catch (Exception $e) {
    echo "Ошибка авторизации: " . $e->getMessage() . "\n";
}
2. Создание заказа
php
<?php

// ... инициализация клиента как выше ...

try {
    // Создание товара для корзины
    $cartItem = (new CartItemBuilder())
        ->setGoodId('13234864') // ID товара в системе Магнит
        ->setName('Огурцы свежие')
        ->setQuantity(1500, 'weight') // 1500 грамм, весовой товар
        ->setPrice(299.99) // Цена в рублях (автоматически конвертируется в копейки)
        ->build();

    // Создание данных доставки
    $delivery = (new DeliveryBuilder())
        ->setTimeSlot('2024-01-15T14:00:00+03:00', '2024-01-15T15:00:00+03:00')
        ->setAddress([
            'city' => 'Москва',
            'street' => 'Сухонская',
            'building' => '11',
            'flat' => '202',
            'full' => 'Москва, ул. Сухонская, д.11, кв. 202'
        ])
        ->setCoordinates(55.735616, 37.642384)
        ->setPrice(250.00) // Стоимость доставки
        ->build();

    // Создание данных сборки
    $collect = (new CollectBuilder())
        ->setStrategy('call_to_customer')
        ->setDesiredAt('2024-01-15T12:00:00+03:00')
        ->build();

    // Создание заказа
    $order = (new OrderBuilder())
        ->setOriginalOrderId('ORDER-' . time())
        ->setStoreCode('123456') // ID магазина Магнит
        ->setCustomer('Иванов Иван Иванович', '+79031111111')
        ->setDelivery($delivery)
        ->setCollect($collect)
        ->setCart([$cartItem])
        ->setPrice(549.99) // Общая сумма заказа
        ->setComment('Просьба позвонить за час до доставки')
        ->build();

    // Отправка заказа
    $orderResponse = $client->createOrder($order);
    echo "Заказ создан успешно!\n";
    echo "ID заказа в системе Магнит: " . $orderResponse['id'] . "\n";
    echo "ID заказа в вашей системе: " . $orderResponse['original_order_id'] . "\n";

} catch (Exception $e) {
    echo "Ошибка создания заказа: " . $e->getMessage() . "\n";
}
3. Работа с существующими заказами
php
<?php

// ... инициализация клиента ...

try {
    $orderId = 'PM-bB00000001';
    
    // Получение информации о заказе
    $orderInfo = $client->getOrder($orderId);
    echo "Статус заказа: " . $orderInfo['status']['code'] . "\n";
    echo "Магазин: " . $orderInfo['store_code'] . "\n";
    
    // Получение статуса заказа
    $status = $client->getOrderStatus($orderId);
    echo "Текущий статус: " . $status['status']['code'] . "\n";
    echo "Последнее обновление: " . $status['status']['updated_at'] . "\n";
    
    // Отмена заказа
    $cancelled = $client->cancelOrder($orderId, 'customer_no_product_needed');
    if ($cancelled) {
        echo "Заказ успешно отменен\n";
    }
    
    // Отправка события о готовности к выдаче
    $eventSent = $client->sendOrderEvent($orderId, [
        'type' => 'order_ready_to_pick_up'
    ]);
    if ($eventSent) {
        echo "Событие отправлено успешно\n";
    }

} catch (Exception $e) {
    echo "Ошибка работы с заказом: " . $e->getMessage() . "\n";
}
4. Работа с номенклатурой и ценами
php
<?php

// ... инициализация клиента ...

try {
    $storeId = '123456';
    
    // Получение цен в магазине
    $prices = $client->getStorePrices($storeId);
    echo "Цены товаров в магазине {$storeId}:\n";
    foreach ($prices['items'] as $item) {
        echo "Товар {$item['good_id']}: {$item['base']['value']} коп.\n";
        if (isset($item['action'])) {
            echo "Акционная цена: {$item['action']['value']} коп.\n";
        }
    }
    
    // Получение остатков товаров
    $stocks = $client->getStoreStocks($storeId);
    echo "\nОстатки товаров:\n";
    foreach ($stocks['items'] as $item) {
        echo "Товар {$item['good_id']}: {$item['quantity']} " . 
             ($item['quantity'] > 1 ? 'штук' : 'штука') . "\n";
    }
    
    // Получение изменений остатков
    $timestampFrom = strtotime('-1 day');
    $stocksDelta = $client->getStoreStocksDelta($storeId, $timestampFrom);
    echo "\nИзменения остатков за последние 24 часа:\n";
    foreach ($stocksDelta['items'] as $item) {
        echo "Товар {$item['good_id']}: {$item['quantity']}\n";
    }

} catch (Exception $e) {
    echo "Ошибка получения данных: " . $e->getMessage() . "\n";
}
5. Работа с доставкой (Last Mile)
php
<?php

// ... инициализация клиента ...

try {
    $partnerId = 'partner-uuid-here';
    $requestId = uniqid('claim_', true);
    
    // Создание заявки на доставку
    $claimData = [
        'external_order_id' => 'EXT-ORDER-123',
        'items' => [
            'weight' => 2000, // вес в граммах
            'cost' => 150000  // стоимость в копейках
        ],
        'route_points' => [
            [
                'point_type' => 'source',
                'address' => [
                    'full_name' => 'Москва, Садовническая улица, 82с2',
                    'coordinates' => [
                        'lat' => 55.735616,
                        'lon' => 37.642384
                    ]
                ],
                'contact' => [
                    'name' => 'Магазин Магнит',
                    'phone' => '+78005553535'
                ],
                'transfer_code' => '1234'
            ],
            [
                'point_type' => 'destination',
                'address' => [
                    'full_name' => 'Москва, ул. Сухонская, д.11',
                    'coordinates' => [
                        'lat' => 55.878315,
                        'lon' => 37.65372
                    ],
                    'flat' => '202',
                    'floor' => '2'
                ],
                'contact' => [
                    'name' => 'Иван Иванов',
                    'phone' => '+79031111111'
                ]
            ]
        ],
        'comment' => 'Осторожно, хрупкий груз'
    ];
    
    $claimResponse = $client->createDeliveryClaim($requestId, $partnerId, $claimData);
    echo "Заявка на доставку создана: " . $claimResponse['claim_id'] . "\n";
    
    // Получение информации о заявках
    $claimsInfo = $client->getDeliveryClaimsInfo($partnerId, [$claimResponse['claim_id']]);
    print_r($claimsInfo);

} catch (Exception $e) {
    echo "Ошибка работы с доставкой: " . $e->getMessage() . "\n";
}
6. Работа с Magnit Post (ПВЗ)
php
<?php

// ... инициализация клиента ...

try {
    // Получение списка пунктов выдачи
    $pickupPoints = $client->getPickupPoints(1, 50, null, 'Татарстан', 'Казань');
    echo "Найдено ПВЗ: " . count($pickupPoints['pickupPoints']) . "\n";
    
    foreach ($pickupPoints['pickupPoints'] as $point) {
        echo "ПВЗ {$point['key']}: {$point['name']}, {$point['address']}\n";
    }
    
    // Создание заказа доставки до ПВЗ
    $deliveryOrder = [
        'customerOrderId' => 'DO-' . time(),
        'payment' => [
            'declaredValue' => 1500.00
        ],
        'delivery' => [
            'pickupPointKey' => '63933',
            'recipient' => [
                'firstName' => 'Иван',
                'familyName' => 'Иванов',
                'phoneNumber' => '+79031111111'
            ]
        ],
        'characteristic' => [
            'weight' => 500,
            'length' => 200,
            'width' => 150,
            'height' => 50
        ]
    ];
    
    $orderResponse = $client->createDeliveryOrder($deliveryOrder);
    echo "Заказ доставки создан. Трек-номер: " . $orderResponse['trackingNumber'] . "\n";

} catch (Exception $e) {
    echo "Ошибка работы с ПВЗ: " . $e->getMessage() . "\n";
}
7. Работа с Magnit Market (товары)
php
<?php

// ... инициализация клиента ...

try {
    // Получение списка категорий
    $categories = $client->getCategories();
    echo "Доступные категории:\n";
    foreach ($categories as $category) {
        echo "{$category['category_id']}: {$category['category_title']}\n";
    }
    
    // Получение характеристик категорий
    $characteristics = $client->getCategoryCharacteristics([123, 124]);
    print_r($characteristics);
    
    // Обновление цен
    $pricesUpdate = $client->updatePrices([
        [
            'seller_sku_id' => 'sku-123',
            'sku_id' => 121,
            'price' => 1500.00,
            'old_price' => 1800.00,
            'currency_code' => 'RUB'
        ]
    ]);
    echo "Цены обновлены\n";
    
    // Обновление остатков
    $stocksUpdate = $client->updateStocks([
        [
            'seller_sku_id' => 'sku-123',
            'sku_id' => 121,
            'stock' => 50,
            'warehouse_id' => 'warehouse-uuid'
        ]
    ]);
    echo "Остатки обновлены\n";

} catch (Exception $e) {
    echo "Ошибка работы с товарами: " . $e->getMessage() . "\n";
}
8. Комплексный пример
php
<?php

// Комплексный пример: проверка цен → создание заказа → отслеживание статуса

try {
    $client = new MagnitB2BClient('client-id', 'client-secret', true);
    $client->getAuthToken();
    
    // 1. Проверяем цены и наличие
    $storeCode = '123456';
    $productId = '13234864';
    
    $prices = $client->getStorePrices($storeCode);
    $stocks = $client->getStoreStocks($storeCode);
    
    $productPrice = null;
    $productStock = null;
    
    foreach ($prices['items'] as $item) {
        if ($item['good_id'] === $productId) {
            $productPrice = $item['base']['value'] / 100; // Конвертируем в рубли
            break;
        }
    }
    
    foreach ($stocks['items'] as $item) {
        if ($item['good_id'] === $productId) {
            $productStock = $item['quantity'];
            break;
        }
    }
    
    if ($productStock > 0) {
        // 2. Создаем заказ
        $cartItem = (new CartItemBuilder())
            ->setGoodId($productId)
            ->setName('Тестовый товар')
            ->setQuantity(2)
            ->setPrice($productPrice)
            ->build();
        
        $order = (new OrderBuilder())
            ->setOriginalOrderId('TEST-ORDER-' . date('Ymd-His'))
            ->setStoreCode($storeCode)
            ->setCustomer('Тестовый Клиент', '+79001112233')
            ->setCollect((new CollectBuilder())->setStrategy('call_to_customer')->build())
            ->setCart([$cartItem])
            ->setPrice($productPrice * 2)
            ->build();
        
        $orderResponse = $client->createOrder($order);
        echo "Заказ создан: {$orderResponse['id']}\n";
        
        // 3. Отслеживаем статус
        $maxChecks = 10;
        $checkInterval = 30; // секунды
        
        for ($i = 0; $i < $maxChecks; $i++) {
            sleep($checkInterval);
            
            $status = $client->getOrderStatus($orderResponse['id']);
            echo "Статус заказа: {$status['status']['code']}\n";
            
            if ($status['status']['code'] === 'order_ready') {
                echo "Заказ готов к выдаче!\n";
                break;
            }
        }
    } else {
        echo "Товара нет в наличии\n";
    }

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
Эти примеры показывают основные сценарии работы с API Magnit B2B. Класс предоставляет удобный интерфейс для всех основных операций и автоматически обрабатывает авторизацию, форматирование данных и ошибки.