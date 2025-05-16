<?php

use src\Aiqfome\Handlers\CheckOrderDetailsHandler;
use src\geral\Custom;
use src\geral\Enums\RequestConstants;
use src\geral\RequestMulti;

use function Pest\Faker\fake;

beforeEach(function () {
    /** @var RequestMulti */
    $this->requestMulti = Mockery::mock(RequestMulti::class);
    $this->custom = $this->createMock(Custom::class);
    $this->handler = new CheckOrderDetailsHandler($this->requestMulti, $this->custom);
});

afterEach(function () {
    Mockery::close();
});

test('chamando a função execute com um pedido aceito', function () {
    // Prepare mock responses
    $orderId = fake()->uuid();
    $responses = [
        $orderId => (object)[
            'http_code' => RequestConstants::HTTP_OK,
            'content' => json_encode([
                'data' => [
                    'id' => $orderId,
                    'store' => [
                        'id' => fake()->randomNumber(),
                        'name' => fake()->company(),
                    ],
                    'user' => [
                        'name' => fake()->name(),
                        'mobile_phone' => fake()->phoneNumber(),
                        'phone_number' => fake()->phoneNumber(),
                        'address' => [
                            'latitude' => fake()->latitude(),
                            'longitude' => fake()->longitude(),
                            'street_name' => fake()->streetName(),
                            'number' => fake()->buildingNumber(),
                            'complement' => fake()->secondaryAddress(),
                            'neighborhood_name' => fake()->citySuffix(),
                            'city_name' => fake()->city(),
                            'state_uf' => fake()->stateAbbr(),
                            'zip_code' => fake()->postcode(),
                            'reference' => fake()->sentence(),
                        ],
                    ],
                    'payment_method' => [
                        'pre_paid' => false,
                        'total' => fake()->randomFloat(2, 10, 100),
                    ],
                ]
            ])
        ]
    ];

    $this->requestMulti->shouldReceive('execute')->once()->andReturn($responses);

    $ordersEvents = [
        ['order_id' => $orderId]
    ];

    $pipeline = [];

    // Execute handler
    $this->handler->execute($ordersEvents, $pipeline);

    // Assert that pipeline has the correct data
    expect($pipeline)->toHaveCount(1);

    $orderDetails = json_decode($pipeline[0][2], true);

    // Check if order details contain has key order id
    expect($orderDetails)->toHaveKey('order_id');
});

test('chamando a função execute com um pedido não aceito', function () {
    $orderId = fake()->uuid();
    $responses = [
        $orderId => (object)[
            'http_code' => 404,
            'content' => ''
        ]
    ];

    $this->requestMulti->shouldReceive('execute')->once()->andReturn($responses);

    $ordersEvents = [
        ['order_id' => $orderId]
    ];

    $pipeline = [];

    // Execute handler
    $this->handler->execute($ordersEvents, $pipeline);

    // Assert that pipeline is empty
    expect($pipeline)->toBeEmpty();
});

test('formato de um pedido vazio', function () {
    $reflection = new ReflectionClass(CheckOrderDetailsHandler::class);
    $method = $reflection->getMethod('formatOrderResult');
    $method->setAccessible(true);

    $formattedOrderDetails = $method->invoke(new CheckOrderDetailsHandler($this->requestMulti, $this->custom), []);
    expect(empty($formattedOrderDetails))->toBeTrue();
});

test('http code ser igual a 401', function () {
    $orderId = fake()->uuid();
    $responses = [
        $orderId => (object)[
            'http_code' => 401,
            'content' => ''
        ]
    ];

    $this->requestMulti->shouldReceive('execute')->once()->andReturn($responses);

    $ordersEvents = [
        ['order_id' => $orderId, 'merchant_id' => fake()->uuid()]
    ];

    $pipeline = [];

    // Execute handler
    $this->handler->execute($ordersEvents, $pipeline);

    // Assert that pipeline is empty
    expect(count($pipeline))->toBe(1);
});