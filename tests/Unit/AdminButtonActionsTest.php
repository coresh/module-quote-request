<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Controller\Adminhtml\Migration\Run;
use Coresh\QuoteRequest\Controller\Adminhtml\Request\ExportCsv;
use Coresh\QuoteRequest\Controller\Adminhtml\Request\MassStatus;
use Coresh\QuoteRequest\Controller\Adminhtml\Request\ResendEmail;
use Coresh\QuoteRequest\Controller\Adminhtml\Request\SaveStatus;
use Coresh\QuoteRequest\Model\Email\AdminNotificationSender;
use Coresh\QuoteRequest\Exception\NotificationException;
use Coresh\QuoteRequest\Model\Migration\LegacyDataMigrator;
use Coresh\QuoteRequest\Model\Migration\LegacySourceDetector;
use Coresh\QuoteRequest\Model\Migration\MigrationApproval;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as RequestResource;
use Coresh\QuoteRequest\Ui\Export\RequestCsvExporter;
use Coresh\QuoteRequest\Ui\Service\SelectedRequests;
use Magento\Backend\Model\Session;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

require_once dirname(__DIR__) . '/Fixtures/admin_action_framework.php';
require_once dirname(__DIR__) . '/Fixtures/admin_operation_framework.php';

/** Execute actual button controllers with explicit dependencies; native dispatch/ACL is not emulated. */
class AdminButtonActionsTest extends TestCase
{
    private const APPROVAL = 'coresh_quote_request_migration_approval';
    private const PREVIEW = 'coresh_quote_request_migration_preview';

    private function migrationSession(array &$state): Session
    {
        $session = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->onlyMethods(['getData', '__call'])->getMock();
        $session->method('getData')->willReturnCallback(static function ($key) use (&$state) { return $state[$key] ?? null; });
        $session->method('__call')->willReturnCallback(static function ($method, $args) use (&$state, $session) {
            if ($method === 'setData') {
                $state[$args[0]] = $args[1];
            } elseif ($method === 'unsetData') {
                unset($state[$args[0]]);
            } else {
                throw new \LogicException('Unexpected session operation in the controller regression.');
            }
            return $session;
        });
        return $session;
    }

    private function controller(string $class, array $body = [], array $dependencies = [], array $params = [], bool $isPost = true, bool $frameworkValid = true): array
    {
        $request = $this->createMock(Http::class);
        $request->method('isPost')->willReturn($isPost);
        $request->method('getPost')->willReturnCallback(static fn ($key, $default = null) => $body[$key] ?? $default);
        $request->method('getParam')->willReturnCallback(static fn ($key, $default = null) => $params[$key] ?? $default);
        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('current-session-key');
        $validator = $this->createMock(Validator::class);
        $validator->method('validate')->with($request)->willReturn($frameworkValid);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(static function ($message, $context = []): void {
            // Logs must not expose customer data or exception messages to administrators or transport output.
            foreach ($context as $value) {
                self::assertIsNotObject($value);
            }
            self::assertStringNotContainsString('secret-customer@example.com', json_encode([$message, $context]));
        });
        $messages = new class {
            public array $errors = [];
            public array $successes = [];
            public array $notices = [];
            public function addErrorMessage($value) { $this->errors[] = (string) $value; }
            public function addSuccessMessage($value) { $this->successes[] = (string) $value; }
            public function addNoticeMessage($value) { $this->notices[] = (string) $value; }
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
        $methods = ['getRequest'];
        if ($class === SaveStatus::class || $class === ResendEmail::class) {
            $methods[] = 'loadRequestEntity';
        }
        $controller = $this->getMockBuilder($class)->disableOriginalConstructor()->onlyMethods($methods)->getMock();
        $controller->method('getRequest')->willReturn($request);
        $dependencies += [
            'formKey' => $formKey, $class === Run::class ? 'validator' : 'formKeyValidator' => $validator,
            'logger' => $logger, 'messageManager' => $messages, 'resultRedirectFactory' => $redirectFactory
        ];
        foreach ($dependencies as $field => $value) {
            $property = new \ReflectionProperty($class, $field);
            (new \ReflectionProperty($property->getDeclaringClass()->getName(), $field))->setValue($controller, $value);
        }
        return [$controller, $messages, $redirect];
    }

    #[DataProvider('invalidOperationTokens')]
    public function testEveryOperationRejectsGetMissingMismatchedAndInvalidBodyTokens(string $class, bool $post, mixed $token, bool $frameworkValid): void
    {
        $resource = $this->createMock(RequestResource::class);
        $resource->expects(self::never())->method('updateRequestStatuses');
        $exporter = $this->createMock(RequestCsvExporter::class);
        $exporter->expects(self::never())->method('getCsvFile');
        $migrator = $this->createMock(LegacyDataMigrator::class);
        $migrator->expects(self::never())->method('migrate');
        $extra = match ($class) {
            MassStatus::class => ['resource' => $resource],
            ExportCsv::class => ['exporter' => $exporter],
            Run::class => ['migrator' => $migrator],
            default => []
        };
        [$controller, $messages, $redirect] = $this->controller(
            $class, ['form_key' => $token, 'store_id' => '1', 'mode' => 'preview'], $extra,
            ['form_key' => 'current-session-key'], $post, $frameworkValid
        );
        if ($class === SaveStatus::class || $class === ResendEmail::class) {
            $controller->expects(self::never())->method('loadRequestEntity');
        }
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/index', $redirect->path);
        self::assertCount(1, $messages->errors);
        self::assertSame([], $messages->successes);
        self::assertSame([], $messages->notices);
    }

    public static function invalidOperationTokens(): array
    {
        $cases = [];
        foreach ([SaveStatus::class, ResendEmail::class, MassStatus::class, ExportCsv::class, Run::class] as $class) {
            foreach ([[false, 'current-session-key', true], [true, null, true], [true, [], true], [true, '', true],
                [true, 'stale-session-key', true], [true, 'current-session-key', false]] as $case
            ) {
                $cases[] = [$class, ...$case];
            }
        }
        return $cases;
    }

    #[DataProvider('statusCases')]
    public function testSaveStatusReturnsTheCorrectMessageAndRequestRedirect(mixed $status, bool $valid, bool $failure): void
    {
        $entity = $this->createMock(QuoteRequest::class);
        $entity->method('getId')->willReturn(42);
        $resource = $this->createMock(RequestResource::class);
        $update = $resource->expects($valid ? self::once() : self::never())->method('updateRequestStatus');
        if ($valid) {
            $update->with(42, $status);
            if ($failure) {
                $update->willThrowException(new \RuntimeException('secret-customer@example.com'));
            }
        }
        [$controller, $messages, $redirect] = $this->controller(SaveStatus::class,
            ['form_key' => 'current-session-key', 'request_status' => $status], ['quoteRequestResource' => $resource]);
        $controller->expects(self::once())->method('loadRequestEntity')->willReturn($entity);
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/view', $redirect->path);
        self::assertSame(['request_id' => 42], $redirect->params);
        self::assertSame($valid && !$failure ? ['The request status has been saved.'] : [], $messages->successes);
        self::assertSame($valid ? ($failure ? ['The request status could not be saved.'] : [])
            : ['Please select a valid request status.'], $messages->errors);
    }

    public static function statusCases(): array
    {
        return [['new', true, false], ['processing', true, false], ['closed', true, false], ['new', true, true],
            [null, false, false], [[], false, false], [true, false, false], [1, false, false],
            ['delete', false, false], ['PROCESSING', false, false], ['closed\n', false, false]];
    }

    #[DataProvider('entityOperations')]
    public function testMissingRequestShowsAUsefulMessageWithoutInvokingActions(string $class): void
    {
        [$controller, $messages, $redirect] = $this->controller($class, ['form_key' => 'current-session-key']);
        $controller->expects(self::once())->method('loadRequestEntity')->willReturn(null);
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/index', $redirect->path);
        self::assertSame(['This quote request no longer exists.'], $messages->errors);
        self::assertSame([], $messages->successes);
    }

    public static function entityOperations(): array { return [[SaveStatus::class], [ResendEmail::class]]; }

    #[DataProvider('emailResults')]
    public function testResendButtonDistinguishesTransportAcceptedDisabledAndFailed(string $result): void
    {
        $entity = $this->createMock(QuoteRequest::class);
        $entity->method('getId')->willReturn(42);
        $sender = $this->createMock(AdminNotificationSender::class);
        $send = $sender->expects(self::once())->method('sendNotification')->with($entity);
        if ($result === 'failed') {
            $send->willThrowException(new \RuntimeException('secret-customer@example.com'));
        } else {
            $send->willReturn($result === 'sent');
        }
        [$controller, $messages, $redirect] = $this->controller(ResendEmail::class,
            ['form_key' => 'current-session-key'], ['notificationSender' => $sender]);
        $controller->expects(self::once())->method('loadRequestEntity')->willReturn($entity);
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/view', $redirect->path);
        self::assertSame(['request_id' => 42], $redirect->params);
        self::assertSame($result === 'sent' ? ['The notification was passed to the email transport.'] : [], $messages->successes);
        self::assertSame($result === 'disabled' ? ['Notification emails are disabled for this request store.'] : [], $messages->notices);
        self::assertSame($result === 'failed' ? ['The notification could not be sent. Check its email status.'] : [], $messages->errors);
    }

    public static function emailResults(): array { return [['sent'], ['disabled'], ['failed']]; }

    #[DataProvider('notificationFailures')]
    public function testResendReportsSafeActionableFailuresAndDoesNotCallHandoffUnconfirmedASendFailure(
        string $code,
        bool $handoffUnconfirmed
    ): void {
        $entity = $this->createMock(QuoteRequest::class);
        $entity->method('getId')->willReturn(42);
        $failure = new NotificationException($code);
        $sender = $this->createMock(AdminNotificationSender::class);
        $sender->expects(self::once())->method('sendNotification')->with($entity)->willThrowException($failure);
        [$controller, $messages, $redirect] = $this->controller(ResendEmail::class,
            ['form_key' => 'current-session-key'], ['notificationSender' => $sender]);
        $controller->expects(self::once())->method('loadRequestEntity')->willReturn($entity);

        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/view', $redirect->path);
        self::assertSame(['request_id' => 42], $redirect->params);
        self::assertSame([], $messages->successes);
        self::assertSame($handoffUnconfirmed ? [] : [$failure->getMessage()], $messages->errors);
        self::assertSame($handoffUnconfirmed ? [$failure->getMessage()] : [], $messages->notices);
    }

    public static function notificationFailures(): array
    {
        return [
            ['original_store_unavailable', false], ['email_attempt_in_progress', false],
            ['notification_recipient_invalid', false], ['notification_reply_to_invalid', false],
            ['notification_sender_invalid', false], ['notification_template_failed', false],
            ['notification_transport_failed', false], ['notification_load_failed', false],
            ['notification_lease_failed', false], ['notification_state_update_failed', false],
            ['notification_failed', false], ['notification_status_unconfirmed', true],
        ];
    }

    #[DataProvider('unsafeEmailFailures')]
    public function testUnexpectedLocalizedAndRuntimeEmailErrorsNeverExposeTheirText(string $kind): void
    {
        $secret = 'SMTP password secret-customer@example.com';
        $exception = $kind === 'localized' ? new LocalizedException(__($secret)) : new \Error($secret);
        $entity = $this->createMock(QuoteRequest::class);
        $entity->method('getId')->willReturn(42);
        $sender = $this->createMock(AdminNotificationSender::class);
        $sender->expects(self::once())->method('sendNotification')->willThrowException($exception);
        [$controller, $messages, $redirect] = $this->controller(ResendEmail::class,
            ['form_key' => 'current-session-key'], ['notificationSender' => $sender]);
        $controller->method('loadRequestEntity')->willReturn($entity);
        self::assertSame($redirect, $controller->execute());
        self::assertSame(['The notification could not be sent. Check its email status.'], $messages->errors);
        self::assertSame([], $messages->notices);
        self::assertSame([], $messages->successes);
    }

    public static function unsafeEmailFailures(): array { return [['localized'], ['runtime_error']]; }

    #[DataProvider('loggingFailures')]
    public function testUnavailableLogBackendDoesNotMaskTheNotificationMessageOrRedirect(string $outcome): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $level = $outcome === 'unconfirmed' ? 'warning' : 'error';
        $logger->expects(self::once())->method($level)
            ->willThrowException(new \RuntimeException('Log backend secret-customer@example.com'));
        $failure = $outcome === 'unexpected'
            ? new \RuntimeException('SMTP password secret-customer@example.com')
            : new NotificationException($outcome === 'unconfirmed'
                ? 'notification_status_unconfirmed' : 'notification_recipient_invalid');
        $entity = $this->createMock(QuoteRequest::class);
        $entity->method('getId')->willReturn(42);
        $sender = $this->createMock(AdminNotificationSender::class);
        $sender->method('sendNotification')->willThrowException($failure);
        [$controller, $messages, $redirect] = $this->controller(ResendEmail::class,
            ['form_key' => 'current-session-key'], ['notificationSender' => $sender, 'logger' => $logger]);
        $controller->method('loadRequestEntity')->willReturn($entity);
        self::assertSame($redirect, $controller->execute());
        self::assertSame(['request_id' => 42], $redirect->params);
        $expected = $outcome === 'unexpected'
            ? 'The notification could not be sent. Check its email status.' : $failure->getMessage();
        self::assertSame($outcome === 'unconfirmed' ? [$expected] : [], $messages->notices);
        self::assertSame($outcome === 'unconfirmed' ? [] : [$expected], $messages->errors);
        self::assertSame([], $messages->successes);
    }

    public static function loggingFailures(): array { return [['typed_failure'], ['unconfirmed'], ['unexpected']]; }

    public function testResendKeepsItsOriginalRequestRedirectWhenAFailedReloadChangesTheModel(): void
    {
        $id = 42;
        $entity = $this->createMock(QuoteRequest::class);
        $entity->method('getId')->willReturnCallback(static function () use (&$id) { return $id; });
        $sender = $this->createMock(AdminNotificationSender::class);
        $sender->method('sendNotification')->willReturnCallback(static function () use (&$id): never {
            $id = null;
            throw new NotificationException('notification_load_failed');
        });
        [$controller, $messages, $redirect] = $this->controller(ResendEmail::class,
            ['form_key' => 'current-session-key'], ['notificationSender' => $sender]);
        $controller->method('loadRequestEntity')->willReturn($entity);
        self::assertSame($redirect, $controller->execute());
        self::assertSame(['request_id' => 42], $redirect->params);
        self::assertCount(1, $messages->errors);
    }

    public function testExportReturnsCsvDownloadUsingGeneratedFileAndRemovalFlag(): void
    {
        $file = ['type' => 'filename', 'value' => 'export/coresh_quote_requests_test.csv', 'rm' => true];
        $exporter = $this->createMock(RequestCsvExporter::class);
        $exporter->expects(self::once())->method('getCsvFile')->willReturn($file);
        $response = new \stdClass();
        $factory = $this->createMock(FileFactory::class);
        $factory->expects(self::once())->method('create')->with('quote-requests.csv', $file, DirectoryList::VAR_DIR, 'text/csv')->willReturn($response);
        [$controller, $messages, $redirect] = $this->controller(ExportCsv::class, ['form_key' => 'current-session-key'],
            ['exporter' => $exporter, 'fileFactory' => $factory]);
        self::assertSame($response, $controller->execute());
        self::assertNull($redirect->path);
        self::assertSame([], $messages->errors);
        self::assertSame([], $messages->successes);
    }

    #[DataProvider('exportFailures')]
    public function testExportFailureReturnsListingAndSafeMessage(bool $localized): void
    {
        $exporter = $this->createMock(RequestCsvExporter::class);
        $exporter->expects(self::once())->method('getCsvFile')->willThrowException($localized
            ? new LocalizedException(__('Please select valid quote requests from this listing.'))
            : new \RuntimeException('secret-customer@example.com'));
        $factory = $this->createMock(FileFactory::class);
        $factory->expects(self::never())->method('create');
        [$controller, $messages, $redirect] = $this->controller(ExportCsv::class, ['form_key' => 'current-session-key'],
            ['exporter' => $exporter, 'fileFactory' => $factory]);
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/index', $redirect->path);
        self::assertSame([$localized ? 'Please select valid quote requests from this listing.' : 'The quote requests could not be exported.'], $messages->errors);
        self::assertSame([], $messages->successes);
    }

    public static function exportFailures(): array { return [[true], [false]]; }

    #[DataProvider('batchSizes')]
    public function testMassStatusBatchesRowsAndReturnsListing(int $count): void
    {
        $selection = $this->createMock(SelectedRequests::class);
        $selection->expects(self::once())->method('rows')->willReturnCallback(static function () use ($count): \Generator {
            for ($id = 1; $id <= $count; $id++) { yield ['request_id' => (string) $id]; }
        });
        $batches = [];
        $resource = $this->createMock(RequestResource::class);
        $resource->expects($count ? self::exactly((int) ceil($count / 500)) : self::never())->method('updateRequestStatuses')
            ->willReturnCallback(static function (array $ids, string $status) use (&$batches): int {
                self::assertSame('closed', $status);
                self::assertLessThanOrEqual(500, count($ids));
                $batches[] = $ids;
                return count($ids);
            });
        [$controller, $messages, $redirect] = $this->controller(MassStatus::class, ['form_key' => 'current-session-key'],
            ['selection' => $selection, 'resource' => $resource], ['status' => 'closed']);
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/index', $redirect->path);
        self::assertSame($count ? range(1, $count) : [], array_merge([], ...$batches));
        self::assertSame([], $messages->errors);
        self::assertCount(1, $messages->successes);
    }

    public static function batchSizes(): array { return [[0], [1], [500], [501], [1001]]; }

    #[DataProvider('massStatusInvalid')]
    public function testMassStatusRejectsInvalidStatusBeforeSelection(mixed $status): void
    {
        $selection = $this->createMock(SelectedRequests::class);
        $selection->expects(self::never())->method('rows');
        [$controller, $messages, $redirect] = $this->controller(MassStatus::class, ['form_key' => 'current-session-key'],
            ['selection' => $selection], ['status' => $status]);
        self::assertSame($redirect, $controller->execute());
        self::assertSame(['Please select a valid request status.'], $messages->errors);
        self::assertSame([], $messages->successes);
    }

    public static function massStatusInvalid(): array { return [[null], [[]], [true], ['delete'], ['Closed']]; }

    public function testMassStatusReportsPartialBatchFailureWithoutSuccessMessage(): void
    {
        $selection = $this->createMock(SelectedRequests::class);
        $selection->method('rows')->willReturnCallback(static function (): \Generator {
            for ($id = 1; $id <= 501; $id++) { yield ['request_id' => $id]; }
        });
        $calls = 0;
        $resource = $this->createMock(RequestResource::class);
        $resource->expects(self::exactly(2))->method('updateRequestStatuses')->willReturnCallback(
            static function (array $ids, string $status) use (&$calls): int {
                $calls++;
                if ($calls === 2) { throw new \RuntimeException('secret-customer@example.com'); }
                return count($ids);
            });
        [$controller, $messages, $redirect] = $this->controller(MassStatus::class, ['form_key' => 'current-session-key'],
            ['selection' => $selection, 'resource' => $resource], ['status' => 'processing']);
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/index', $redirect->path);
        self::assertSame(['The status update could not finish. Refresh the listing to check its result.'], $messages->errors);
        self::assertSame([], $messages->successes);
    }

    public function testMassStatusInvalidSelectionShowsValidationErrorWithoutUpdatingRows(): void
    {
        $selection = $this->createMock(SelectedRequests::class);
        $selection->expects(self::once())->method('rows')->willReturnCallback(static function (): \Generator {
            throw new LocalizedException(__('Please select valid quote requests from this listing.'));
            yield [];
        });
        $resource = $this->createMock(RequestResource::class);
        $resource->expects(self::never())->method('updateRequestStatuses');
        [$controller, $messages, $redirect] = $this->controller(MassStatus::class, ['form_key' => 'current-session-key'],
            ['selection' => $selection, 'resource' => $resource], ['status' => 'processing']);
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/index', $redirect->path);
        self::assertSame(['Please select valid quote requests from this listing.'], $messages->errors);
        self::assertSame([], $messages->successes);
    }

    public function testMigrationPreviewWritesOnlySessionReportAndNewApproval(): void
    {
        $state = [self::APPROVAL => ['old' => true], self::PREVIEW => ['old' => true]];
        $session = $this->migrationSession($state);
        $migrator = $this->createMock(LegacyDataMigrator::class);
        $migrator->expects(self::once())->method('migrate')->with(3, false)->willReturnCallback(static function () use (&$state): array {
            self::assertArrayNotHasKey(self::APPROVAL, $state);
            self::assertArrayNotHasKey(self::PREVIEW, $state);
            return ['requests_pending' => 5];
        });
        $detector = $this->createMock(LegacySourceDetector::class);
        $detector->method('hasSupportedSource')->willReturn(true);
        [$controller, $messages, $redirect] = $this->controller(Run::class,
            ['form_key' => 'current-session-key', 'store_id' => '3', 'mode' => 'preview'],
            ['session' => $session, 'migrator' => $migrator, 'detector' => $detector, 'approval' => new MigrationApproval()]);
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/index', $redirect->path);
        self::assertSame(3, $state[self::PREVIEW]['store_id']);
        self::assertSame(['requests_pending' => 5], $state[self::PREVIEW]['report']);
        self::assertTrue((new MigrationApproval())->validate($state[self::PREVIEW]['token'], 3, $state[self::APPROVAL]));
        self::assertSame(['Preview complete. No data was written and no email was sent.'], $messages->successes);
        self::assertSame([], $messages->errors);
    }

    public function testFailedMigrationPreviewRemovesPriorApprovalAndShowsSafeError(): void
    {
        $state = [self::APPROVAL => ['old' => true], self::PREVIEW => ['old' => true]];
        $session = $this->migrationSession($state);
        $migrator = $this->createMock(LegacyDataMigrator::class);
        $migrator->expects(self::once())->method('migrate')->with(3, false)
            ->willThrowException(new \RuntimeException('secret-customer@example.com'));
        $detector = $this->createMock(LegacySourceDetector::class);
        $detector->method('hasSupportedSource')->willReturn(true);
        $approval = $this->createMock(MigrationApproval::class);
        $approval->expects(self::never())->method('create');
        [$controller, $messages, $redirect] = $this->controller(Run::class,
            ['form_key' => 'current-session-key', 'store_id' => '3', 'mode' => 'preview'],
            ['session' => $session, 'migrator' => $migrator, 'detector' => $detector, 'approval' => $approval]);
        self::assertSame($redirect, $controller->execute());
        self::assertSame([], $state);
        self::assertSame([], $messages->successes);
        self::assertSame(['Migration failed. Source data was not deleted; successful partial imports can be previewed and resumed.'], $messages->errors);
    }

    #[DataProvider('approvalCases')]
    public function testMigrationRequiresConfirmedCurrentStorePreviewAndConsumesItBeforeImport(string $case): void
    {
        $approval = new MigrationApproval();
        $prepared = $approval->create($case === 'different_store' ? 4 : 3, $case === 'expired' ? time() - 601 : time());
        $state = [self::APPROVAL => $prepared['state'], self::PREVIEW => ['token' => $prepared['token']]];
        if ($case === 'already_consumed') { unset($state[self::APPROVAL]); }
        $session = $this->migrationSession($state);
        $success = $case === 'valid';
        $attempt = $success || $case === 'failed_import';
        $migrator = $this->createMock(LegacyDataMigrator::class);
        $migrator->expects($attempt ? self::once() : self::never())->method('migrate')->with(3, true)->willReturnCallback(
            static function () use (&$state, $case): array {
                self::assertArrayNotHasKey(self::APPROVAL, $state);
                self::assertArrayNotHasKey(self::PREVIEW, $state);
                if ($case === 'failed_import') { throw new \RuntimeException('secret-customer@example.com'); }
                return ['requests_imported' => 5];
            });
        $detector = $this->createMock(LegacySourceDetector::class);
        $detector->method('hasSupportedSource')->willReturn(true);
        [$controller, $messages, $redirect] = $this->controller(Run::class,
            ['form_key' => 'current-session-key', 'store_id' => '3', 'mode' => 'execute',
                'preview_token' => $case === 'forged' ? str_repeat('a', 64) : $prepared['token'],
                'confirmed' => $case === 'unconfirmed' ? null : '1'],
            ['session' => $session, 'migrator' => $migrator, 'detector' => $detector, 'approval' => $approval]);
        self::assertSame($redirect, $controller->execute());
        self::assertSame('*/*/index', $redirect->path);
        self::assertCount($success ? 1 : 0, $messages->successes);
        self::assertCount($success ? 0 : 1, $messages->errors);
        if ($attempt) { self::assertArrayNotHasKey(self::APPROVAL, $state); }
        if ($success) {
            self::assertSame(['store_id' => 3, 'report' => ['requests_imported' => 5]], $state[self::PREVIEW]);
        }
        if ($case === 'failed_import') {
            self::assertSame(['Migration failed. Source data was not deleted; successful partial imports can be previewed and resumed.'], $messages->errors);
        }
    }

    public static function approvalCases(): array
    {
        return [['valid'], ['unconfirmed'], ['forged'], ['different_store'], ['expired'], ['already_consumed'], ['failed_import']];
    }

    public function testMigrationWithoutVerifiedSourceReportsErrorAndDoesNotMigrate(): void
    {
        $migrator = $this->createMock(LegacyDataMigrator::class);
        $migrator->expects(self::never())->method('migrate');
        $detector = $this->createMock(LegacySourceDetector::class);
        $detector->method('hasSupportedSource')->willReturn(false);
        [$controller, $messages, $redirect] = $this->controller(Run::class,
            ['form_key' => 'current-session-key', 'store_id' => '3', 'mode' => 'preview'],
            ['migrator' => $migrator, 'detector' => $detector]);
        self::assertSame($redirect, $controller->execute());
        self::assertSame(['A verified legacy source is required.'], $messages->errors);
        self::assertSame([], $messages->successes);
    }

    #[DataProvider('invalidMigrationInput')]
    public function testMigrationRejectsInvalidStoreAndModeBeforeDetector(mixed $store, mixed $mode): void
    {
        $detector = $this->createMock(LegacySourceDetector::class);
        $detector->expects(self::never())->method('hasSupportedSource');
        [$controller, $messages, $redirect] = $this->controller(Run::class,
            ['form_key' => 'current-session-key', 'store_id' => $store, 'mode' => $mode], ['detector' => $detector]);
        self::assertSame($redirect, $controller->execute());
        self::assertSame(['Invalid migration request. Refresh the page.'], $messages->errors);
        self::assertSame([], $messages->successes);
    }

    public static function invalidMigrationInput(): array
    {
        return [[null, 'preview'], [[], 'preview'], [3, 'preview'], ['0', 'preview'], ['03', 'preview'],
            ['-1', 'preview'], ['3\n', 'preview'], ['100000', 'preview'], ['3', 'delete'], ['3', []]];
    }

    public function testAdministrativeOperationsKeepSeparateAclResources(): void
    {
        self::assertSame('Coresh_QuoteRequest::manage', SaveStatus::ADMIN_RESOURCE);
        self::assertSame('Coresh_QuoteRequest::manage', MassStatus::ADMIN_RESOURCE);
        self::assertSame('Coresh_QuoteRequest::send_email', ResendEmail::ADMIN_RESOURCE);
        self::assertSame('Coresh_QuoteRequest::view', ExportCsv::ADMIN_RESOURCE);
        self::assertSame('Coresh_QuoteRequest::migrate', Run::ADMIN_RESOURCE);
        // Actual Magento dispatch authorization is covered by integration assets, not this declaration check.
    }
}
