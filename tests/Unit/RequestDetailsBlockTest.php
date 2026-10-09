<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Block\Adminhtml\RequestDetails;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\QuoteRequestFactory;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Coresh\QuoteRequest\Test\Fixture\RequestIdentifierCases;
use Magento\Backend\Block\Template;
use Magento\Framework\App\Request\Http;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Fixtures/admin_block_framework.php';
require_once dirname(__DIR__) . '/Fixtures/request_identifier_cases.php';

/** Real module methods exercised against explicit minimal doubles in isolated mode. */
class RequestDetailsBlockTest extends TestCase
{
    private function block(array $dependencies = []): RequestDetails
    {
        $block = $this->getMockBuilder(RequestDetails::class)->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])->getMock();
        foreach ($dependencies as $field => $value) {
            $property = new \ReflectionProperty(RequestDetails::class, $field);
            $property->setValue($block, $value);
        }
        return $block;
    }

    public function testChildKeepsNativeMutableFormKeyPropertyWithoutRedeclaration(): void
    {
        $parentProperty = new \ReflectionProperty(Template::class, 'formKey');
        self::assertFalse($parentProperty->isReadOnly());
        $childProperty = new \ReflectionProperty(RequestDetails::class, 'formKey');
        self::assertSame(Template::class, $childProperty->getDeclaringClass()->getName());
        self::assertTrue((new \ReflectionProperty(RequestDetails::class, 'requestFormKey'))->isPrivate());
        self::assertTrue((new \ReflectionProperty(RequestDetails::class, 'requestFormKey'))->isReadOnly());
        // Preserve the public constructor argument name used by Magento DI configuration.
        $parameters = (new \ReflectionMethod(RequestDetails::class, '__construct'))->getParameters();
        self::assertSame('formKey', $parameters[4]->getName());
        self::assertFalse($parameters[4]->isPromoted());
    }

    public function testGetFormKeyReadsCurrentServerTokenRatherThanParentOrCachedValue(): void
    {
        $tokens = $this->createMock(FormKey::class);
        $tokens->expects(self::exactly(2))->method('getFormKey')->willReturnOnConsecutiveCalls('first', 'rotated');
        $block = $this->block(['requestFormKey' => $tokens]);
        $parentToken = $this->createMock(FormKey::class);
        $parentToken->expects(self::never())->method('getFormKey');
        (new \ReflectionProperty(Template::class, 'formKey'))->setValue($block, $parentToken);
        self::assertSame('first', $block->getFormKey());
        self::assertSame('rotated', $block->getFormKey());
    }

    #[DataProvider('permissionCombinations')]
    public function testStatusAndEmailPermissionsRemainIndependent(bool $manage, bool $send): void
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $resources = [];
        $authorization->expects(self::exactly(2))->method('isAllowed')
            ->willReturnCallback(static function ($resource) use ($manage, $send, &$resources): bool {
                $resources[] = $resource;
                return match ($resource) {
                    'Coresh_QuoteRequest::manage' => $manage,
                    'Coresh_QuoteRequest::send_email' => $send,
                    default => throw new \LogicException('Unexpected ACL resource')
                };
            });
        $block = $this->block(['authorization' => $authorization]);
        self::assertSame($manage, $block->canManage());
        self::assertSame($send, $block->canSendEmail());
        self::assertSame(['Coresh_QuoteRequest::manage', 'Coresh_QuoteRequest::send_email'], $resources);
    }

    public static function permissionCombinations(): array
    {
        return [[false, false], [true, false], [false, true], [true, true]];
    }

    public function testEntityIsLoadedOnceAndItsSnapshotIsReused(): void
    {
        $entity = $this->createMock(QuoteRequest::class);
        $factory = $this->createMock(QuoteRequestFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($entity);
        $resource = $this->createMock(QuoteRequestResource::class);
        $resource->expects(self::once())->method('load')->with($entity, 42)->willReturnSelf();
        $request = $this->createMock(Http::class);
        $request->expects(self::once())->method('getParam')->with('request_id')->willReturn('42');
        $block = $this->block(['quoteRequestFactory' => $factory, 'quoteRequestResource' => $resource]);
        $block->method('getRequest')->willReturn($request);
        self::assertSame($entity, $block->getQuoteRequest());
        self::assertSame($entity, $block->getQuoteRequest());
    }

    #[DataProvider('invalidRequestIds')]
    public function testInvalidIdentifiersNeverLoadAnEntity(mixed $id): void
    {
        $entity = $this->createMock(QuoteRequest::class);
        $factory = $this->createMock(QuoteRequestFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($entity);
        $resource = $this->createMock(QuoteRequestResource::class);
        $resource->expects(self::never())->method('load');
        $request = $this->createMock(Http::class);
        $request->expects(self::once())->method('getParam')->with('request_id')->willReturn($id);
        $block = $this->block(['quoteRequestFactory' => $factory, 'quoteRequestResource' => $resource]);
        $block->method('getRequest')->willReturn($request);
        self::assertSame($entity, $block->getQuoteRequest());
        self::assertSame($entity, $block->getQuoteRequest());
    }

    #[DataProvider('validRequestIds')]
    public function testCanonicalUnsignedDatabaseIdentifiersAreAccepted(int|string $id, int $expected): void
    {
        $entity = $this->createMock(QuoteRequest::class);
        $factory = $this->createMock(QuoteRequestFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($entity);
        $resource = $this->createMock(QuoteRequestResource::class);
        $resource->expects(self::once())->method('load')->with($entity, $expected)->willReturnSelf();
        $request = $this->createMock(Http::class);
        $request->method('getParam')->with('request_id')->willReturn($id);
        $block = $this->block(['quoteRequestFactory' => $factory, 'quoteRequestResource' => $resource]);
        $block->method('getRequest')->willReturn($request);
        self::assertSame($entity, $block->getQuoteRequest());
    }

    public static function validRequestIds(): array
    {
        return RequestIdentifierCases::valid();
    }

    public static function invalidRequestIds(): array
    {
        return RequestIdentifierCases::invalid();
    }

    public function testSnapshotCarriesUntrustedValuesToTheEscapedTemplateWithoutPrivateHashes(): void
    {
        $entity = $this->createMock(QuoteRequest::class);
        $values = ['product_name' => '<script>alert(1)</script>', 'product_id' => 0,
            'message' => '<img src=x onerror=alert(2)>', 'quantity' => '',
            'idempotency_key' => 'private-token', 'payload_hash' => 'private-hash'];
        $entity->method('getData')->willReturnCallback(static fn ($key) => $values[$key] ?? null);
        $rows = $this->block(['quoteRequest' => $entity])->getSnapshotRows();
        self::assertCount(22, $rows);
        self::assertSame($values['product_name'], $rows[0]['value']);
        self::assertSame('0', $rows[1]['value']);
        self::assertSame('', $rows[2]['value']);
        self::assertSame('Unknown / not provided', $rows[3]['value']);
        self::assertSame($values['message'], $rows[14]['value']);
        self::assertNotContains('private-token', array_column($rows, 'value'));
        self::assertNotContains('private-hash', array_column($rows, 'value'));
    }

    public function testDeletedStoreDoesNotAttemptToLoadDefaultStore(): void
    {
        $entity = $this->createMock(QuoteRequest::class);
        $entity->method('getData')->with('store_id')->willReturn(null);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->expects(self::never())->method('getStore');
        self::assertSame('Deleted store', $this->block(['quoteRequest' => $entity,
            'storeManager' => $stores])->getStoreLabel());
    }
}
