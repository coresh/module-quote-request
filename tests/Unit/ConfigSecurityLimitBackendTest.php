<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config\Backend\SecurityLimit;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Reject unsafe input before the native save hook; persistence is not exercised. */
class ConfigSecurityLimitBackendTest extends TestCase
{
    #[DataProvider('invalidLimits')]
    public function testSaveRejectsMalformedOrOutOfRangeLimits(string $field, mixed $value): void
    {
        $backend = $this->getMockBuilder(SecurityLimit::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $backend->setData(['path' => 'coresh_quote_request/security/' . $field, 'value' => $value]);
        $this->expectException(LocalizedException::class);
        $backend->beforeSave();
    }

    public static function invalidLimits(): array
    {
        $cases = [];
        foreach (['rate_limit_max', 'rate_limit_window', 'max_message_length', 'max_quantity'] as $field) {
            foreach (["1\n", "1\0", '1e2', '-1', '0', [], new \stdClass()] as $value) {
                $cases[] = [$field, $value];
            }
        }
        return array_merge($cases, [
            ['rate_limit_max', '1001'], ['rate_limit_window', '86401'],
            ['max_message_length', '20001'], ['max_quantity', '100000000'],
            ['max_quantity', '0.00001'], ['max_quantity', '1.00001']
        ]);
    }
}
