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
# Magnit B2B PHP Client

PHP клиент для интеграции с Magnit B2B Platform API. Полная поддержка всех сервисов Магнит для B2B-партнеров.

## 📦 Установка

```bash
composer require your-vendor/magnit-b2b-client
```
Или вручную:
```
php
require_once 'MagnitB2BClient.php';
```
🚀 Быстрый старт
```
<?php
use MagnitB2B\MagnitB2BClient;
use MagnitB2B\OrderBuilder;
use MagnitB2B\CartItemBuilder;

$client = new MagnitB2BClient('your-client-id', 'your-client-secret', true);
$token = $client->getAuthToken();

$cartItem = (new CartItemBuilder())
    ->setGoodId('13234864')
    ->setName('Огурцы свежие')
    ->setQuantity(1500, 'weight')
    ->setPrice(299.99)
    ->build();

$order = (new OrderBuilder())
    ->setOriginalOrderId('ORDER-123')
    ->setStoreCode('123456')
    ->setCustomer('Иван Иванов', '+79031111111')
    ->setCart([$cartItem])
    ->setPrice(299.99)
    ->build();

$orderResponse = $client->createOrder($order);
```


📊 Основные методы
php
// Заказы
$client->createOrder($orderData);
$client->getOrder($orderId);
$client->cancelOrder($orderId, $reason);

// Номенклатура
$client->getStorePrices($storeId);
$client->getStoreStocks($storeId);

// Доставка
$client->createDeliveryClaim($requestId, $partnerId, $claimData);

// ПВЗ
$client->getPickupPoints($page, $size, $filters);

// Товары
$client->getCategories();
$client->updatePrices($prices);
$client->updateStocks($stocks);
🛠️ Требования
PHP 7.4+

GuzzleHTTP 7.0+

JSON расширение

📖 Документация
Полная документация по методам доступна в документации API.

🐛 Поддержка
Для сообщений об ошибках и предложений создавайте Issue.