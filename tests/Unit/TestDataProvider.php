<?php

declare(strict_types=1);

namespace Zaphyr\CacheTests\Unit;

use stdClass;

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
            ['{str'],
            ['rand{'],
            ['rand{str'],
            ['rand}str'],
            ['rand(str'],
            ['rand)str'],
            ['rand/str'],
            ['rand\\str'],
            ['rand@str'],
            ['rand:str'],
        ];
    }

    /**
     * @return array<string[]>
     */
    public static function getValidKeysDataProvider(): array
    {
        return [
            ['ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'],
            ['ABCabc19_.-'],
        ];
    }

    /**
     * @return array<mixed[]>
     */
    public static function getValidValuesDataProvider(): array
    {
        return [
            ['string'],
            [11],
            [1.1],
            [true],
            [null],
            [['test.key' => 'test_value']],
            [new stdClass()],
        ];
    }
}
