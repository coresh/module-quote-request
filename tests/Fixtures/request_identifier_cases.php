<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Fixture;

/** Shared adversarial inputs for the block and admin controller entry points. */
final class RequestIdentifierCases
{
    public static function valid(): array
    {
        return [[1, 1], ['1', 1], [42, 42], ['42', 42], ['4294967295', 4294967295], [4294967295, 4294967295]];
    }

    public static function invalid(): array
    {
        return [
            'missing' => [null], 'empty' => [''], 'zero integer' => [0], 'zero string' => ['0'],
            'negative integer' => [-1], 'negative string' => ['-1'], 'boolean true' => [true],
            'boolean false' => [false], 'float one' => [1.0], 'float NaN' => [NAN],
            'leading zero' => ['01'], 'only zeros' => ['00'], 'positive sign' => ['+1'],
            'space before' => [' 1'], 'space after' => ['1 '], 'newline after' => ["1\n"],
            'exponent' => ['1e2'], 'hexadecimal' => ['0x1'], 'decimal' => ['1.0'],
            'array' => [[1]], 'object' => [new \stdClass()], 'nul' => ["1\0"],
            'database overflow' => ['4294967296'], 'database overflow integer' => [4294967296],
            'integer overflow' => [(string) PHP_INT_MAX . '0'], 'large input' => [str_repeat('9', 512)]
        ];
    }
}
