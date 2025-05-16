<?php

namespace Tests\Helpers\MockHelpers;

use Mockery;
use src\geral\Request;

use function Pest\Faker\fake;

class RequestMockHelper
{
    public static function createExternalMock(int $httpCode, array $content): void
    {
        $requestMock = Mockery::mock('overload:' . Request::class);
        $requestMock->shouldReceive('setOptions')->andReturnSelf();
        $requestMock->shouldReceive('setRequestMethod')->andReturnSelf();
        $requestMock->shouldReceive('setHeaders')->andReturnSelf();
        $requestMock->shouldReceive('setRequestType')->andReturnSelf();
        $requestMock->shouldReceive('setPostFields')->andReturnSelf();
        $requestMock->shouldReceive('setBasicAuth')->andReturnSelf();
        $requestMock->shouldReceive('setSaveLogs')->andReturnSelf();
        $requestMock->shouldReceive('setUrl')->andReturnSelf();
        $requestMock->shouldReceive('getUrl')->andReturn(fake()->url());
        $requestMock->shouldReceive('getOptions')->andReturn([]);
        $requestMock->shouldReceive('execute')->andReturn((object)[
            'http_code' => $httpCode,
            'content' => json_encode($content),
        ]);
    }
}
