<?php

namespace Tests\Helpers;

use Mockery\MockInterface;

class CacheHelper
{
    public static function createCacheKey(string $template, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public static function mockRedisClientMethod(MockInterface $redisClient, string $method, $with, $willReturn, $times = null): void
    {
        $mock = $redisClient->shouldReceive($method);
        if (!empty($with)) {
            $mock->with(...(is_array($with) ? $with : [$with]));
        }
        $mock->andReturn($willReturn);
        if (!is_null($times) && is_int($times)) {
            $mock->times($times);
        }
    }
}
