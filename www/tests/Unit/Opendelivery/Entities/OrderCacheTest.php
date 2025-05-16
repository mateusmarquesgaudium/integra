<?php

use src\Delivery\Enums\Provider;
use src\geral\RedisService;
use src\Opendelivery\Entities\OrderCache;
use src\Opendelivery\Enums\RedisSchema;

use function Pest\Faker\fake;

beforeEach(function () {
    /** @var RedisService */
    $this->redisClient = Mockery::mock(RedisService::class);
    $this->orderId = fake()->uuid();
    $this->provider = Provider::SAIPOS;
    $this->keyOrderDetails = str_replace(['{order_id}', '{provider}'], [$this->orderId, $this->provider], RedisSchema::KEY_ORDER_DETAILS);

    $this->orderCache = new OrderCache($this->redisClient, $this->provider);
});

afterEach(function () {
    Mockery::close();
});

test('busca detalhes no cache', function () {
    $dataDetails = ['orderId' => $this->orderId, 'provider' => $this->provider];

    $this->redisClient
        ->shouldReceive('get')
        ->with($this->keyOrderDetails)
        ->andReturn(json_encode($dataDetails));

    $result = $this->orderCache->getOrderCache($this->orderId);
    expect($result)->toBe($dataDetails);
});
