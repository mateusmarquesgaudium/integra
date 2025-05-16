<?php

use src\geral\RedisService;
use src\AnotaAi\Entities\OrderCache;
use src\AnotaAi\Enums\RedisSchema;
use src\Delivery\Enums\EventWebhookType;
use src\Delivery\Enums\OrderStatus;
use src\Delivery\Enums\OrderType;
use src\Delivery\Enums\Provider;
use src\Delivery\Enums\RedisSchema as DeliveryRedisSchema;
use function Pest\Faker\fake;

beforeEach(function () {
    $this->redisClient = $this->createMock(RedisService::class);
    $this->orderCache = new OrderCache($this->redisClient);
    $this->merchantId = fake()->uuid();
    $this->orderId = fake()->uuid();
});

test('evento pedido criado', function () {
    $orderDetails = [
        'webhookType' => EventWebhookType::ANOTAAI_CREATED,
        'type' => OrderType::DELIVERY,
        'salesChannel' => Provider::ANOTAAI,
        'id' => $this->orderId,
        'shortReference' => fake()->randomNumber(),
        'merchant' => ['id' => $this->merchantId],
        'createdAt' => fake()->date('Y-m-d\TH:i:s\Z'),
        'schedule_order' => ['date' => fake()->date('Y-m-d\TH:i:s\Z')],
        'deliveryAddress' => [
            'formattedAddress' => fake()->address(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'complement' => fake()->sentence(),
            'reference' => fake()->sentence(),
        ],
        'customer' => ['name' => fake()->name(), 'phone' => fake()->numerify('###########')],
        'payments' => [['prepaid' => false, 'value' => fake()->randomFloat(2, 0, 1000)]],
    ];

    $this->redisClient->expects($this->once())
        ->method('rPush')
        ->with(
            RedisSchema::KEY_LIST_PENDING_ORDER_EVENTS,
            $this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['order_status'] === OrderStatus::APPROVED;
            })
        );

    $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, $orderDetails);
});

test('evento pedido cancelado', function () {
    $orderDetails = [
        'webhookType' => EventWebhookType::ANOTAAI_CANCELED,
        'salesChannel' => Provider::ANOTAAI,
        'id' => $this->orderId,
    ];

    $this->redisClient->expects($this->once())
        ->method('rPush')
        ->with(
            DeliveryRedisSchema::LIST_ORDERS_EVENTS_WEBHOOK,
            $this->callback(function ($json) {
                $data = json_decode($json, true);
                return $data['order_status'] === OrderStatus::HIDDEN;
            })
        );

    $this->redisClient->expects($this->once())
        ->method('del')
        ->with(str_replace('{order_id}', $this->orderId, RedisSchema::KEY_ORDER_DETAILS));

    $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, $orderDetails);
});

test('retornar se salesChannel iFood', function () {
    $orderDetails = [
        'webhookType' => EventWebhookType::ANOTAAI_CREATED,
        'type' => OrderType::DELIVERY,
        'salesChannel' => Provider::IFOOD,
        'id' => $this->orderId,
    ];

    $this->redisClient->expects($this->never())->method('rPush');
    $this->redisClient->expects($this->never())->method('del');

    $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, $orderDetails);
});

test('retornar se tipo não DELIVERY', function () {
    $orderDetails = [
        'webhookType' => EventWebhookType::ANOTAAI_CREATED,
        'type' => 'PICKUP',
        'salesChannel' => Provider::ANOTAAI,
        'id' => $this->orderId,
    ];

    $this->redisClient->expects($this->never())->method('rPush');
    $this->redisClient->expects($this->never())->method('del');

    $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, $orderDetails);
});

test('retornar se webhookType desconhecido', function () {
    $orderDetails = [
        'webhookType' => 'UNKNOWN_TYPE',
        'type' => OrderType::DELIVERY,
        'salesChannel' => Provider::ANOTAAI,
        'id' => $this->orderId,
    ];

    $this->redisClient->expects($this->never())->method('rPush');
    $this->redisClient->expects($this->never())->method('del');

    $this->orderCache->addEventOrderWebhook($this->merchantId, $this->orderId, $orderDetails);
});
