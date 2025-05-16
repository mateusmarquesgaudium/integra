<?php

use src\Delivery\Enums\Provider;
use src\geral\CustomException;
use src\geral\RedisService;
use src\Opendelivery\Handlers\NewDeliveryHandler;

use function Pest\Faker\fake;

beforeEach(function() {
    $this->redisService = Mockery::mock('src\geral\RedisService');
    $this->provider = Provider::SAIPOS;
});

test('criar um novo pedido', function() {
    // Mock the $_SERVER variable
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
    $_SERVER['HTTP_HOST'] = 'example.com';

    $merchantId = fake()->uuid();
    $data = [
        'orderId' => fake()->uuid(),
        'orderDisplayId' => fake()->randomNumber(),
        'merchant' => [
            'id' => $merchantId,
            'name' => fake()->company()
        ],
        'pickupAddress' => [
            'country' => fake()->country(),
            'state' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->name(),
            'street' => fake()->streetName(),
            'number' => fake()->randomNumber(),
            'complement' => fake()->sentence()
        ],
        'deliveryAddress' => [
            'country' => fake()->country(),
            'state' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->name(),
            'street' => fake()->streetName(),
            'number' => fake()->randomNumber(),
            'complement' => fake()->sentence(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'postalCode' => fake()->postcode()
        ],
        'returnToMerchant' => false,
        'customerName' => fake()->name(),
        'customerPhone' => fake()->phoneNumber(),
        'customerPhoneLocalizer' => fake()->randomNumber(),
        'payments' => [
            'method' => 'ONLINE',
            'value' => fake()->randomFloat()
        ]
    ];

    $this->redisService->shouldReceive('sisMember')->andReturn(true);
    $this->redisService->shouldReceive('rPush')->andReturn(true);
    $this->redisService->shouldReceive('set')->andReturn(true);
    $this->redisService->shouldReceive('expire')->andReturn(true);

    $newDeliveryHandler = new NewDeliveryHandler($this->redisService, $this->provider);
    $response = $newDeliveryHandler->createNewDelivery($data);

    $this->assertArrayHasKey('deliveryId', $response);
    $this->assertArrayHasKey('event', $response);
    $this->assertArrayHasKey('completion', $response);
    $this->assertArrayHasKey('deliveryDetailsURL', $response);
});

test('criar um pedido para um merchant inválido', function() {
    $merchantId = fake()->uuid();
    $data = [
        'orderId' => fake()->uuid(),
        'orderDisplayId' => fake()->randomNumber(),
        'merchant' => [
            'id' => $merchantId,
            'name' => fake()->company()
        ],
        'pickupAddress' => [
            'country' => fake()->country(),
            'state' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->name(),
            'street' => fake()->streetName(),
            'number' => fake()->randomNumber(),
            'complement' => fake()->sentence()
        ],
        'deliveryAddress' => [
            'country' => fake()->country(),
            'state' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->name(),
            'street' => fake()->streetName(),
            'number' => fake()->randomNumber(),
            'complement' => fake()->sentence(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'postalCode' => fake()->postcode()
        ],
        'returnToMerchant' => false,
        'customerName' => fake()->name(),
        'customerPhone' => fake()->phoneNumber(),
        'customerPhoneLocalizer' => fake()->randomNumber(),
        'payments' => [
            'method' => 'ONLINE',
            'value' => fake()->randomFloat()
        ]
    ];

    $this->redisService->shouldReceive('sisMember')->andReturn(false);

    $newDeliveryHandler = new NewDeliveryHandler($this->redisService, $this->provider);
    $newDeliveryHandler->createNewDelivery($data);
})->throws(CustomException::class);

test('criar um pedido sem o método de pagamento', function() {
    $merchantId = fake()->uuid();
    $data = [
        'orderId' => fake()->uuid(),
        'orderDisplayId' => fake()->randomNumber(),
        'merchant' => [
            'id' => $merchantId,
            'name' => fake()->company()
        ],
        'pickupAddress' => [
            'country' => fake()->country(),
            'state' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->name(),
            'street' => fake()->streetName(),
            'number' => fake()->randomNumber(),
            'complement' => fake()->sentence()
        ],
        'deliveryAddress' => [
            'country' => fake()->country(),
            'state' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->name(),
            'street' => fake()->streetName(),
            'number' => fake()->randomNumber(),
            'complement' => fake()->sentence(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'postalCode' => fake()->postcode()
        ],
        'returnToMerchant' => false,
        'customerName' => fake()->name(),
        'customerPhone' => fake()->phoneNumber(),
        'customerPhoneLocalizer' => fake()->randomNumber()
    ];

    $this->redisService->shouldReceive('sisMember')->andReturn(true);

    $newDeliveryHandler = new NewDeliveryHandler($this->redisService, $this->provider);
    $newDeliveryHandler->createNewDelivery($data);
})->throws(Exception::class);

test('criar um pedido com método de pagamento OFFILINE e sem amount or currency', function() {
    $merchantId = fake()->uuid();
    $data = [
        'orderId' => fake()->uuid(),
        'orderDisplayId' => fake()->randomNumber(),
        'merchant' => [
            'id' => $merchantId,
            'name' => fake()->company()
        ],
        'pickupAddress' => [
            'country' => fake()->country(),
            'state' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->name(),
            'street' => fake()->streetName(),
            'number' => fake()->randomNumber(),
            'complement' => fake()->sentence()
        ],
        'deliveryAddress' => [
            'country' => fake()->country(),
            'state' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->name(),
            'street' => fake()->streetName(),
            'number' => fake()->randomNumber(),
            'complement' => fake()->sentence(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'postalCode' => fake()->postcode()
        ],
        'returnToMerchant' => false,
        'customerName' => fake()->name(),
        'customerPhone' => fake()->phoneNumber(),
        'customerPhoneLocalizer' => fake()->randomNumber(),
        'payments' => [
            'method' => 'OFFLINE',
            'offlineMethod' => [
                'value' => fake()->randomFloat()
            ]
        ]
    ];

    $this->redisService->shouldReceive('sisMember')->andReturn(true);

    $newDeliveryHandler = new NewDeliveryHandler($this->redisService, $this->provider);
    $newDeliveryHandler->createNewDelivery($data);
})->throws(Exception::class);

test('verificar validate fields', function() {
    $newDeliveryHandler = new NewDeliveryHandler($this->redisService, $this->provider);
    $validateFields = $newDeliveryHandler->getValidateFields();

    $this->assertIsArray($validateFields);
    $this->assertContains('orderId', $validateFields);
});
