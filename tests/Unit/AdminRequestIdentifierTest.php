<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Controller\Adminhtml\Request\AbstractRequestAction;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\QuoteRequestFactory;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Coresh\QuoteRequest\Test\Fixture\RequestIdentifierCases;
use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/Fixtures/admin_action_framework.php';
require_once dirname(__DIR__) . '/Fixtures/request_identifier_cases.php';

/** Exposes the real protected loader without emulating Magento routing or ACL. */
class RequestLoaderProbe extends AbstractRequestAction
{
    public function execute() { return null; }
    public function loadForTest(): ?QuoteRequest { return $this->loadRequestEntity(); }
}

class AdminRequestIdentifierTest extends TestCase
{
    private function action(mixed $id, QuoteRequestFactory $factory, QuoteRequestResource $resource): RequestLoaderProbe
    {
        $request = $this->createMock(Http::class);
        $request->expects(self::once())->method('getParam')->with('request_id')->willReturn($id);
        $action = $this->getMockBuilder(RequestLoaderProbe::class)->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])->getMock();
        $action->method('getRequest')->willReturn($request);
        foreach (['quoteRequestFactory' => $factory, 'quoteRequestResource' => $resource] as $field => $dependency) {
            (new \ReflectionProperty(AbstractRequestAction::class, $field))->setValue($action, $dependency);
        }
        return $action;
    }

    #[DataProvider('invalidIdentifiers')]
    public function testInvalidIdentifiersCannotReadOrMutateAnAccidentallyCoercedEntity(mixed $id): void
    {
        $factory = $this->createMock(QuoteRequestFactory::class);
        $factory->expects(self::never())->method('create');
        $resource = $this->createMock(QuoteRequestResource::class);
        $resource->expects(self::never())->method('load');
        self::assertNull($this->action($id, $factory, $resource)->loadForTest());
    }

    #[DataProvider('validIdentifiers')]
    public function testCanonicalUnsignedDatabaseIdentifiersLoadTheExactEntity(int|string $id, int $expected): void
    {
        $entity = $this->createMock(QuoteRequest::class);
        $entity->method('getId')->willReturn($expected);
        $factory = $this->createMock(QuoteRequestFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($entity);
        $resource = $this->createMock(QuoteRequestResource::class);
        $resource->expects(self::once())->method('load')->with($entity, $expected)->willReturnSelf();
        self::assertSame($entity, $this->action($id, $factory, $resource)->loadForTest());
    }

    public function testMissingStoredEntityReturnsNull(): void
    {
        $entity = $this->createMock(QuoteRequest::class);
        $entity->method('getId')->willReturn(null);
        $factory = $this->createMock(QuoteRequestFactory::class);
        $factory->expects(self::once())->method('create')->willReturn($entity);
        $resource = $this->createMock(QuoteRequestResource::class);
        $resource->expects(self::once())->method('load')->with($entity, 42)->willReturnSelf();
        self::assertNull($this->action('42', $factory, $resource)->loadForTest());
    }

    public static function invalidIdentifiers(): array
    {
        return RequestIdentifierCases::invalid();
    }

    public static function validIdentifiers(): array
    {
        return RequestIdentifierCases::valid();
    }
}
