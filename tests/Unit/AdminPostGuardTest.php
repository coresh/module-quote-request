<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Controller\Adminhtml\Request\ResendEmail;
use Coresh\QuoteRequest\Controller\Adminhtml\Request\SaveStatus;
use Coresh\QuoteRequest\Model\QuoteRequestFactory;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Coresh\QuoteRequest\Model\Email\AdminNotificationSender;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

require_once dirname(__DIR__) . '/Fixtures/admin_action_framework.php';

class AdminPostGuardTest extends TestCase
{
    #[DataProvider('invalidRequests')]
    public function testAdminMutationsRejectMissingPostTokenEvenWhenMergedParametersAreValid(
        string $controllerClass, bool $isPost, mixed $bodyToken, bool $frameworkValid
    ): void {
        $request = $this->createMock(Http::class);
        $request->method('isPost')->willReturn($isPost);
        $request->method('getPost')->with('form_key')->willReturn($bodyToken);
        // Core Validator reads merged parameters. A valid query token must not replace a missing body token.
        $request->method('getParam')->willReturn('correct-token');
        $validator = $this->createMock(Validator::class);
        $callsFramework = $isPost && is_string($bodyToken) && $bodyToken === 'correct-token';
        $validator->expects($callsFramework ? self::once() : self::never())
            ->method('validate')->willReturn($frameworkValid);
        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('correct-token');
        $factory = $this->createMock(QuoteRequestFactory::class);
        $factory->expects(self::never())->method('create');
        $messages = new class {
            public array $errors = [];
            public function addErrorMessage($message) { $this->errors[] = (string) $message; }
        };
        $redirect = new class {
            public ?string $path = null;
            public function setPath($path, $params = []) { $this->path = $path; return $this; }
        };
        $redirectFactory = new class ($redirect) {
            public function __construct(private object $redirect) {}
            public function create() { return $this->redirect; }
        };
        // Execute the real module guard while leaving framework dispatch and parent initialization out of this isolated test.
        $controller = $this->getMockBuilder($controllerClass)->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])->getMock();
        $controller->method('getRequest')->willReturn($request);
        foreach (['formKeyValidator' => $validator, 'formKey' => $formKey,
            'quoteRequestFactory' => $factory, 'messageManager' => $messages,
            'resultRedirectFactory' => $redirectFactory] as $field => $value
        ) {
            $property = new \ReflectionProperty($controllerClass, $field);
            // PHP 8.3 requires the declaring scope when initializing inherited readonly properties.
            (new \ReflectionProperty($property->getDeclaringClass()->getName(), $field))
                ->setValue($controller, $value);
        }
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/index', $redirect->path);
        self::assertSame(['Invalid form key. Please refresh the page.'], $messages->errors);
    }

    #[DataProvider('acceptedActions')]
    public function testValidBodyTokenStillAllowsTheAuthorizedMutation(string $controllerClass): void
    {
        $request = $this->createMock(Http::class);
        $request->method('isPost')->willReturn(true);
        $request->method('getPost')->willReturnCallback(static fn ($key) => match ($key) {
            'form_key' => 'correct-token', 'request_status' => 'processing', default => null
        });
        $validator = $this->createMock(Validator::class);
        $validator->expects(self::once())->method('validate')->with($request)->willReturn(true);
        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('correct-token');
        $storedRequest = $this->createMock(QuoteRequest::class);
        $storedRequest->method('getId')->willReturn(42);
        $resource = $this->createMock(QuoteRequestResource::class);
        $sender = $this->createMock(AdminNotificationSender::class);
        if ($controllerClass === SaveStatus::class) {
            $resource->expects(self::once())->method('updateRequestStatus')->with(42, 'processing');
            $sender->expects(self::never())->method('sendNotification');
        } else {
            $sender->expects(self::once())->method('sendNotification')->with($storedRequest)->willReturn(true);
            $resource->expects(self::never())->method('updateRequestStatus');
        }
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        $messages = new class {
            public array $successes = [];
            public function addSuccessMessage($message) { $this->successes[] = (string) $message; }
        };
        $redirect = new class {
            public ?string $path = null;
            public array $params = [];
            public function setPath($path, $params = []) { $this->path = $path; $this->params = $params; return $this; }
        };
        $redirectFactory = new class ($redirect) {
            public function __construct(private object $redirect) {}
            public function create() { return $this->redirect; }
        };
        $controller = $this->getMockBuilder($controllerClass)->disableOriginalConstructor()
            ->onlyMethods(['getRequest', 'loadRequestEntity'])->getMock();
        $controller->method('getRequest')->willReturn($request);
        $controller->expects(self::once())->method('loadRequestEntity')->willReturn($storedRequest);
        $dependencies = ['formKeyValidator' => $validator, 'formKey' => $formKey,
            'quoteRequestResource' => $resource, 'logger' => $logger, 'messageManager' => $messages,
            'resultRedirectFactory' => $redirectFactory];
        if ($controllerClass === ResendEmail::class) {
            $dependencies['notificationSender'] = $sender;
        }
        foreach ($dependencies as $field => $value) {
            $property = new \ReflectionProperty($controllerClass, $field);
            (new \ReflectionProperty($property->getDeclaringClass()->getName(), $field))
                ->setValue($controller, $value);
        }
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/view', $redirect->path);
        self::assertSame(['request_id' => 42], $redirect->params);
        self::assertCount(1, $messages->successes);
    }

    public static function acceptedActions(): array
    {
        return [[SaveStatus::class], [ResendEmail::class]];
    }

    public static function invalidRequests(): array
    {
        $cases = [];
        foreach ([SaveStatus::class, ResendEmail::class] as $class) {
            foreach ([[false, 'correct-token', true], [true, null, true], [true, [], true],
                [true, '', true], [true, 'wrong-token', true], [true, 'correct-token', false]] as $case
            ) {
                $cases[] = [$class, ...$case];
            }
        }
        return $cases;
    }
}
