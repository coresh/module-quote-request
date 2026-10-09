<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Ui\Service\GridSelectionValidator;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class GridSelectionValidatorTest extends TestCase
{
    public function testSelectedReferencesAreCanonicalAndDeduplicated(): void
    {
        self::assertSame(['namespace' => GridSelectionValidator::NAMESPACE, 'selected' => [1, 2], 'excluded' => null],
            (new GridSelectionValidator())->validate(['namespace' => GridSelectionValidator::NAMESPACE,
                'selected' => ['1', 1, '2'], 'status' => 'closed', 'form_key' => 'token']));
    }

    public function testSelectAllUsesExplicitMagentoFalseString(): void
    {
        self::assertSame(['namespace' => GridSelectionValidator::NAMESPACE, 'selected' => null, 'excluded' => 'false'],
            (new GridSelectionValidator())->validate(['namespace' => GridSelectionValidator::NAMESPACE,
                'excluded' => 'false']));
    }

    public function testExcludedReferencesAreCanonical(): void
    {
        self::assertSame(['namespace' => GridSelectionValidator::NAMESPACE, 'selected' => null, 'excluded' => [1, 2]],
            (new GridSelectionValidator())->validate(['namespace' => GridSelectionValidator::NAMESPACE, 'excluded' => ['1', '2']]));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidSelections')]
    public function testMalformedForeignOrContradictorySelectionIsRejected(array $params): void
    {
        $this->expectException(LocalizedException::class);
        (new GridSelectionValidator())->validate($params);
    }

    public static function invalidSelections(): array
    {
        $ns = ['namespace' => GridSelectionValidator::NAMESPACE];
        return [[[]], [['namespace' => 'sales_order_grid', 'excluded' => 'false']],
            [$ns], [$ns + ['selected' => []]], [$ns + ['selected' => '1']],
            [$ns + ['excluded' => false]], [$ns + ['excluded' => true]], [$ns + ['excluded' => 'true']],
            [$ns + ['selected' => [0]]], [$ns + ['selected' => [-1]]], [$ns + ['selected' => ['001']]],
            [$ns + ['selected' => [true]]], [$ns + ['selected' => [1.0]]], [$ns + ['selected' => [[]]]],
            [$ns + ['selected' => ['1 OR 1=1']]], [$ns + ['selected' => ['4294967296']]],
            [$ns + ['selected' => [1], 'excluded' => [2]]], [$ns + ['selected' => [1], 'excluded' => 'false']],
            [$ns + ['selected' => [], 'excluded' => 'false']],
            [$ns + ['selected' => range(1, GridSelectionValidator::MAX_ROWS + 1)]]];
    }
}
