<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit;

class TestDataProvider
{
    /**
     * @return array<string[]>
     */
    public static function getIllegalCharactersDataProvider(): array
    {
        return [
            [''],
            [' '],
            ["\x80\x81\x82"],
            ['{'],
            ['}'],
            ['('],
            [')'],
            ['/'],
            ['\\'],
            ['@'],
            [':'],
            ['{}()/\@:'],
        ];
    }
}
