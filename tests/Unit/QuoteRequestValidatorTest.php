<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Validation\QuoteRequestValidator;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class QuoteRequestValidatorTest extends TestCase
{
    private function validator(array $settings = []): QuoteRequestValidator
    {
        $config = $this->createMock(Config::class);
        $config->method('getValue')->willReturnCallback(static fn (string $path): string => $settings[$path] ?? '');
        return new QuoteRequestValidator($config);
    }

    private function validInput(): array
    {
        return ['product_id' => '123', 'quantity' => '2', 'customer_name' => 'Maria',
            'customer_email' => 'customer@example.test', 'message' => 'Please quote this item.',
            'request_token' => str_repeat('A', 64)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validQuantities')]
    public function testExactDecimalQuantity(mixed $input, string $expected): void
    {
        self::assertSame($expected, $this->validator()->normalizeQuantity($input, 2));
    }

    public static function validQuantities(): array
    {
        return [[1, '1.0000'], ['1', '1.0000'], ['0002.5', '2.5000'], ['0.0001', '0.0001'],
            ['1000000', '1000000.0000'], ['123.4500', '123.4500']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidQuantities')]
    public function testRejectsInvalidQuantity(mixed $input): void
    {
        $this->expectException(LocalizedException::class);
        $this->validator()->normalizeQuantity($input, 1);
    }

    public static function invalidQuantities(): array
    {
        return [[0], ['0'], ['0.0000'], [-1], ['-1'], ['1.00001'], ['NaN'], ['INF'],
            [INF], [NAN], [1.0], [true], [null], [[]], ['1e3'], [' 1'], ['1 '], ['+1'],
            ['1.'], ['.5'], ['1000000.0001'], ['9999999999999999999999'], [new \stdClass()]];
    }

    public function testConfiguredMaximumIsAppliedWithoutRounding(): void
    {
        self::assertSame('2.5000', $this->validator(['security/max_quantity' => '2.5'])->normalizeQuantity('2.5000', 1));
        $this->expectException(LocalizedException::class);
        $this->validator(['security/max_quantity' => '2.5'])->normalizeQuantity('2.5001', 1);
    }

    public function testMalformedMaximumFallsBackToSafeDefault(): void
    {
        $this->expectException(LocalizedException::class);
        $this->validator(['security/max_quantity' => 'unlimited'])->normalizeQuantity('1000001', 1);
    }

    public function testAllowlistDropsForgedServerFields(): void
    {
        $input = $this->validInput() + ['product_name' => 'Forged', 'product_sku' => 'forged',
            'product_url' => 'https://attacker.test', 'customer_id' => 999, 'store_id' => 999,
            'request_status' => 'approved', 'email_status' => 'sent'];
        $result = $this->validator()->validate($input, 2);
        self::assertSame(123, $result['product_id']);
        self::assertSame('2.0000', $result['quantity']);
        self::assertSame(str_repeat('a', 64), $result['request_token']);
        self::assertSame('', $result['customer_telephone']);
        foreach (['product_name', 'product_sku', 'product_url', 'customer_id', 'store_id', 'request_status', 'email_status'] as $key) {
            self::assertArrayNotHasKey($key, $result);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFields')]
    public function testInvalidFieldsAreRejected(string $field, mixed $value): void
    {
        $input = $this->validInput();
        $input[$field] = $value;
        $this->expectException(LocalizedException::class);
        $this->validator()->validate($input, 1);
    }

    public static function invalidFields(): array
    {
        return [
            ['product_id', 0], ['product_id', '001'], ['product_id', '4294967296'], ['product_id', []],
            ['customer_name', []], ['customer_name', ' '], ['customer_name', str_repeat('a', 129)],
            ['customer_email', []], ['customer_email', 'not-an-email'],
            ['customer_email', "victim@example.test\r\nBcc: attacker@example.test"],
            ['customer_name', "Customer\nBcc: attacker@example.test"],
            ['message', []], ['message', "text\0hidden"], ['message', "invalid\xFFutf8"],
            ['message', str_repeat('x', 5001)], ['customer_telephone', []],
            ['request_token', []], ['request_token', str_repeat('f', 63)], ['request_token', str_repeat('g', 64)],
            ['honeypot', 'bot'], ['honeypot', []], ['super_attribute', null],
            ['super_attribute', ['12' => '0']], ['super_attribute', ['12' => []]],
            ['super_attribute', ['bad' => '4']],
        ];
    }

    public function testTextIsPreservedForContextualEscapingAndDirectivesAreNotExecuted(): void
    {
        $input = $this->validInput();
        $input['message'] = "</textarea><script>alert(1)</script>\r\n{{var secret}}";
        $result = $this->validator()->validate($input, 1);
        self::assertSame("</textarea><script>alert(1)</script>\n{{var secret}}", $result['message']);
    }

    public function testOptionsAreCanonicalAndDeterministicallySorted(): void
    {
        $input = $this->validInput();
        $input['super_attribute'] = [19 => '5', 12 => '3'];
        self::assertSame([12 => 3, 19 => 5], $this->validator()->validate($input, 1)['super_attribute']);
    }

    public function testUnicodeLimitCountsCharactersAndByteCapIsIndependent(): void
    {
        $input = $this->validInput();
        $input['message'] = str_repeat('é', 3);
        self::assertSame('ééé', $this->validator(['security/max_message_length' => '3'])->validate($input, 1)['message']);
        $input['message'] .= 'é';
        $this->expectException(LocalizedException::class);
        $this->validator(['security/max_message_length' => '3'])->validate($input, 1);
    }
}
