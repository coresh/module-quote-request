<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Exception\NotificationException;
use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Email\AdminNotificationSender;
use Coresh\QuoteRequest\Model\Email\TemplateVariablesBuilder;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as Resource;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\Template\TransportBuilderFactory;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/** Transport doubles record handoff; these tests do not prove remote inbox delivery. */
class AdminNotificationSenderTest extends TestCase
{
    private function fixture(string $recipient = 'quotes@example.test', string $replyTo = 'customer@example.test'): array
    {
        $state = (object) ['enabled' => true, 'senderIdentity' => '', 'lease' => 5, 'failLease' => false,
            'failStore' => false, 'loadCalls' => 0, 'failLoadAt' => [], 'failCompleteStatus' => null,
            'updates' => [], 'logs' => [], 'translationEnabled' => true, 'storedTranslation' => null,
            'failEnable' => false, 'failLog' => false, 'factoryCreates' => 0,
            'builderCalls' => [], 'failBuilderAt' => null, 'failVariables' => false, 'sendCalls' => 0,
            'failTransport' => false, 'loadedId' => 17, 'loadedStoreId' => 2, 'emailStatus' => 'sending',
            'emailAttempts' => 5, 'noOpComplete' => false, 'postReloadOverrides' => []];
        $config = $this->createMock(Config::class);
        $config->method('isFlag')->with('email/enabled', 2)->willReturnCallback(static fn () => $state->enabled);
        $config->method('getRecipient')->with(2)->willReturn($recipient);
        $config->method('getValue')->willReturnCallback(static fn ($key): string =>
            $key === 'email/sender_identity' ? $state->senderIdentity : '');
        $request = $this->createMock(QuoteRequest::class);
        $request->method('getId')->willReturnCallback(static fn () => $state->loadedId);
        $request->method('getData')->willReturnCallback(static fn ($key) =>
            ['store_id' => $state->loadedStoreId, 'customer_email' => $replyTo,
                'email_status' => $state->emailStatus, 'email_attempts' => $state->emailAttempts][$key] ?? null);
        $resource = $this->createMock(Resource::class);
        $resource->method('claimEmailAttempt')->willReturnCallback(static function () use ($state) {
            if ($state->failLease) { throw new \RuntimeException('Database password customer@example.test'); }
            return $state->lease;
        });
        $resource->method('load')->willReturnCallback(static function () use ($state, $resource) {
            ++$state->loadCalls;
            if (in_array($state->loadCalls, $state->failLoadAt, true)) {
                throw new \RuntimeException('Database password customer@example.test');
            }
            if ($state->loadCalls === 2) {
                foreach ($state->postReloadOverrides as $field => $value) { $state->{$field} = $value; }
            }
            return $resource;
        });
        $resource->method('completeEmailAttempt')->willReturnCallback(static function ($id, $status, $code, $attempt) use ($state): void {
            $state->updates[] = [$id, $status, $code, $attempt];
            if ($state->failCompleteStatus === $status) {
                throw new \RuntimeException('Database password customer@example.test');
            }
            if (!$state->noOpComplete) { $state->emailStatus = $status; }
        });
        $variables = $this->createMock(TemplateVariablesBuilder::class);
        $variables->method('build')->willReturnCallback(static function () use ($state): array {
            if ($state->failVariables) { throw new \RuntimeException('Template password customer@example.test'); }
            return ['request_id' => 17];
        });
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('sendMessage')->willReturnCallback(static function () use ($state): void {
            ++$state->sendCalls;
            if ($state->failTransport) { throw new \RuntimeException('SMTP password customer@example.test'); }
        });
        $builder = $this->createMock(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setTemplateVars', 'setFromByScope',
            'addTo', 'setReplyTo'] as $method) {
            $builder->method($method)->willReturnCallback(static function (...$args) use ($state, $method, $builder) {
                $state->builderCalls[$method][] = $args;
                if ($state->failBuilderAt === $method) { throw new \RuntimeException('Mail password customer@example.test'); }
                return $builder;
            });
        }
        $builder->method('getTransport')->willReturnCallback(static function () use ($state, $transport) {
            if ($state->failBuilderAt === 'getTransport') { throw new \RuntimeException('Template password customer@example.test'); }
            return $transport;
        });
        $factory = $this->createMock(TransportBuilderFactory::class);
        $factory->method('create')->willReturnCallback(static function () use ($state, $builder) {
            ++$state->factoryCreates;
            return $builder;
        });
        $inline = $this->createMock(StateInterface::class);
        $inline->method('isEnabled')->willReturnCallback(static fn () => $state->translationEnabled);
        $inline->method('disable')->willReturnCallback(static function () use ($state): void {
            $state->translationEnabled = false;
        });
        $inline->method('enable')->willReturnCallback(static function () use ($state): void {
            if ($state->failEnable) { throw new \RuntimeException('Cleanup password customer@example.test'); }
            $state->translationEnabled = true;
        });
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->with(2)->willReturnCallback(function () use ($state) {
            if ($state->failStore) { throw new \RuntimeException('Store password customer@example.test'); }
            return $this->createMock(Store::class);
        });
        $logger = $this->createMock(LoggerInterface::class);
        foreach (['error', 'warning'] as $level) {
            $logger->method($level)->willReturnCallback(static function ($message, $context) use ($state, $level): void {
                $state->logs[] = [$level, $message, $context];
                if ($state->failLog) { throw new \RuntimeException('Logger password'); }
            });
        }
        $sender = new AdminNotificationSender($config, $resource, $variables, $factory, $inline, $stores, $logger);
        return compact('state', 'config', 'request', 'resource', 'variables', 'transport', 'builder', 'factory',
            'inline', 'stores', 'logger', 'sender');
    }

    private function assertFailure(array $f, string $code): NotificationException
    {
        try {
            $f['sender']->sendNotification($f['request']);
            self::fail('Expected a safe notification exception.');
        } catch (NotificationException $exception) {
            self::assertSame($code, $exception->getErrorCode());
            self::assertStringNotContainsString('password', $exception->getMessage());
            self::assertStringNotContainsString('customer@example.test', $exception->getMessage());
            $logs = json_encode($f['state']->logs, JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('password', $logs);
            self::assertStringNotContainsString('customer@example.test', $logs);
            return $exception;
        }
    }

    public function testDisabledNotificationDoesNotClaimLeaseOrCreateTransport(): void
    {
        $f = $this->fixture(); $f['state']->enabled = false;
        $f['resource']->expects(self::never())->method('claimEmailAttempt');
        $f['inline']->expects(self::never())->method('disable');
        self::assertFalse($f['sender']->sendNotification($f['request']));
        self::assertSame(0, $f['state']->factoryCreates);
    }

    public function testBusyLeaseDoesNotSendOrOverwriteOtherWorkerState(): void
    {
        $f = $this->fixture(); $f['state']->lease = false;
        $this->assertFailure($f, 'email_attempt_in_progress');
        self::assertSame([], $f['state']->updates);
        self::assertSame(0, $f['state']->loadCalls);
        self::assertSame(0, $f['state']->factoryCreates);
    }

    public function testSuccessfulHandoffUsesOriginalStoreOwnedAttemptAndNativeReplyToMethod(): void
    {
        $f = $this->fixture();
        self::assertTrue($f['sender']->sendNotification($f['request']));
        self::assertSame([['coresh_quote_request_admin_notification']], $f['state']->builderCalls['setTemplateIdentifier']);
        self::assertSame([[['area' => 'frontend', 'store' => 2]]], $f['state']->builderCalls['setTemplateOptions']);
        self::assertSame([['general', 2]], $f['state']->builderCalls['setFromByScope']);
        self::assertSame([['quotes@example.test', '']], $f['state']->builderCalls['addTo']);
        self::assertSame([['customer@example.test', null]], $f['state']->builderCalls['setReplyTo']);
        self::assertSame([[17, 'sent', null, 5]], $f['state']->updates);
        self::assertSame(1, $f['state']->sendCalls);
        self::assertSame(2, $f['state']->loadCalls);
        self::assertSame(1, $f['state']->factoryCreates);
        self::assertTrue($f['state']->translationEnabled);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeAddresses')]
    public function testInvalidAddressCannotCreateTransport(string $recipient, string $replyTo, string $code): void
    {
        $f = $this->fixture($recipient, $replyTo);
        $this->assertFailure($f, $code);
        self::assertSame([[17, 'failed', $code, 5]], $f['state']->updates);
        self::assertSame(0, $f['state']->factoryCreates);
        self::assertSame(0, $f['state']->sendCalls);
        self::assertTrue($f['state']->translationEnabled);
    }

    public static function unsafeAddresses(): array
    {
        return [["quotes@example.test\r\nBcc: victim@example.test", 'customer@example.test', 'notification_recipient_invalid'],
            ['quotes@example.test', "customer@example.test\nBcc: victim@example.test", 'notification_reply_to_invalid'],
            ['invalid-address', 'customer@example.test', 'notification_recipient_invalid'],
            ['quotes@example.test', 'invalid-address', 'notification_reply_to_invalid'],
            [str_repeat('a', 243) . '@example.test', 'customer@example.test', 'notification_recipient_invalid'],
            ['quotes@example.test', str_repeat('a', 243) . '@example.test', 'notification_reply_to_invalid']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeSenders')]
    public function testUnsafeSenderIdentityCannotCreateTransport(string $identity): void
    {
        $f = $this->fixture(); $f['state']->senderIdentity = $identity;
        $this->assertFailure($f, 'notification_sender_invalid');
        self::assertSame(0, $f['state']->factoryCreates);
        self::assertSame([[17, 'failed', 'notification_sender_invalid', 5]], $f['state']->updates);
    }

    public static function unsafeSenders(): array
    {
        return [["general\r\nBcc: victim@example.test"], ['../general'], [str_repeat('a', 65)]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('preHandoffFailures')]
    public function testSetupFailureHasSafePhaseCodeAndDoesNotHandoff(string $method, string $code): void
    {
        $f = $this->fixture(); $f['state']->failBuilderAt = $method;
        $this->assertFailure($f, $code);
        self::assertSame([[17, 'failed', $code, 5]], $f['state']->updates);
        self::assertSame(0, $f['state']->sendCalls);
        self::assertTrue($f['state']->translationEnabled);
    }

    public static function preHandoffFailures(): array
    {
        return [['setTemplateIdentifier', 'notification_template_failed'], ['setTemplateOptions', 'notification_template_failed'],
            ['setTemplateVars', 'notification_template_failed'], ['setFromByScope', 'notification_sender_invalid'],
            ['addTo', 'notification_recipient_invalid'], ['setReplyTo', 'notification_reply_to_invalid'],
            ['getTransport', 'notification_template_failed']];
    }

    public function testVariableFailureIsSafeAndRestoresTranslation(): void
    {
        $f = $this->fixture(); $f['state']->failVariables = true;
        $this->assertFailure($f, 'notification_template_failed');
        self::assertSame(0, $f['state']->sendCalls);
        self::assertTrue($f['state']->translationEnabled);
    }

    public function testUnavailableOriginalStoreDoesNotClaimLease(): void
    {
        $f = $this->fixture(); $f['state']->failStore = true;
        $f['resource']->expects(self::never())->method('claimEmailAttempt');
        $this->assertFailure($f, 'original_store_unavailable');
        self::assertSame([], $f['state']->updates);
    }

    public function testLeaseFailureHasSafeCodeWithoutClaimingOwnership(): void
    {
        $f = $this->fixture(); $f['state']->failLease = true;
        $this->assertFailure($f, 'notification_lease_failed');
        self::assertSame([], $f['state']->updates);
        self::assertSame(0, $f['state']->factoryCreates);
    }

    public function testInitialLoadFailureMarksOnlyOwnedAttemptFailed(): void
    {
        $f = $this->fixture(); $f['state']->failLoadAt = [1];
        $this->assertFailure($f, 'notification_load_failed');
        self::assertSame([[17, 'failed', 'notification_load_failed', 5]], $f['state']->updates);
        self::assertSame(0, $f['state']->factoryCreates);
    }

    public function testFailedReloadDoesNotReplaceOriginalSafeFailure(): void
    {
        $f = $this->fixture(); $f['state']->failLoadAt = [1, 2];
        $this->assertFailure($f, 'notification_load_failed');
        self::assertSame([[17, 'failed', 'notification_load_failed', 5]], $f['state']->updates);
    }

    public function testTransportFailureWarnsAboutAmbiguousDeliveryAndMarksOwnedAttempt(): void
    {
        $f = $this->fixture(); $f['state']->failTransport = true;
        $exception = $this->assertFailure($f, 'notification_transport_failed');
        self::assertStringContainsString('Check delivery before resending', $exception->getMessage());
        self::assertSame([[17, 'failed', 'notification_transport_failed', 5]], $f['state']->updates);
        self::assertTrue($f['state']->translationEnabled);
    }

    public function testFailureToPersistFailedStateHasDistinctSafeCode(): void
    {
        $f = $this->fixture(); $f['state']->failTransport = true; $f['state']->failCompleteStatus = 'failed';
        $this->assertFailure($f, 'notification_state_update_failed');
        self::assertSame([[17, 'failed', 'notification_transport_failed', 5]], $f['state']->updates);
    }

    public function testFailureToPersistSentStateNeverMarksHandedOffEmailFailed(): void
    {
        $f = $this->fixture(); $f['state']->failCompleteStatus = 'sent';
        $this->assertFailure($f, 'notification_status_unconfirmed');
        self::assertSame([[17, 'sent', null, 5]], $f['state']->updates);
        self::assertSame(1, $f['state']->sendCalls);
    }

    public function testPostHandoffReloadFailureNeverMarksHandedOffEmailFailed(): void
    {
        $f = $this->fixture(); $f['state']->failLoadAt = [2];
        $this->assertFailure($f, 'notification_status_unconfirmed');
        self::assertSame([[17, 'sent', null, 5]], $f['state']->updates);
        self::assertSame(1, $f['state']->sendCalls);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unconfirmedStateReadbacks')]
    public function testPostHandoffNoOpOrChangedRecordCannotConfirmSavedStatus(bool $noOp, array $overrides): void
    {
        $f = $this->fixture();
        $f['state']->noOpComplete = $noOp;
        $f['state']->postReloadOverrides = $overrides;
        $exception = $this->assertFailure($f, 'notification_status_unconfirmed');
        self::assertStringContainsString('Check delivery before resending', $exception->getMessage());
        self::assertSame([[17, 'sent', null, 5]], $f['state']->updates);
        self::assertSame(1, $f['state']->sendCalls);
        self::assertTrue($f['state']->translationEnabled);
    }

    public static function unconfirmedStateReadbacks(): array
    {
        return ['zero affected rows' => [true, []],
            'newer lease completed' => [true, ['emailStatus' => 'sent', 'emailAttempts' => 6]],
            'record disappeared' => [true, ['loadedId' => 0, 'loadedStoreId' => 0]],
            'original store changed' => [false, ['loadedStoreId' => 3]],
            'unconfirmed saved status' => [false, ['emailStatus' => 'failed']]];
    }

    public function testOuterTranslationSuspensionIsNotResumedByNotification(): void
    {
        $f = $this->fixture(); $f['state']->translationEnabled = false; $f['state']->storedTranslation = true;
        $f['inline']->expects(self::never())->method('suspend');
        $f['inline']->expects(self::never())->method('resume');
        $f['inline']->expects(self::never())->method('disable');
        $f['inline']->expects(self::never())->method('enable');
        self::assertTrue($f['sender']->sendNotification($f['request']));
        self::assertFalse($f['state']->translationEnabled);
        self::assertTrue($f['state']->storedTranslation);
    }

    public function testTranslationCleanupFailureDoesNotHideSuccessfulHandoff(): void
    {
        $f = $this->fixture(); $f['state']->failEnable = true;
        self::assertTrue($f['sender']->sendNotification($f['request']));
        self::assertSame([[17, 'sent', null, 5]], $f['state']->updates);
        self::assertStringNotContainsString('password', json_encode($f['state']->logs, JSON_THROW_ON_ERROR));
    }

    public function testTranslationAndLogFailureDoNotReplaceOriginalTransportFailure(): void
    {
        $f = $this->fixture(); $f['state']->failTransport = true;
        $f['state']->failEnable = true; $f['state']->failLog = true;
        $this->assertFailure($f, 'notification_transport_failed');
        self::assertSame([[17, 'failed', 'notification_transport_failed', 5]], $f['state']->updates);
    }

    public function testUnknownNotificationCodeCannotDisplayUntrustedMessage(): void
    {
        $exception = new NotificationException('SMTP password customer@example.test');
        self::assertSame('notification_failed', $exception->getErrorCode());
        self::assertStringNotContainsString('password', $exception->getMessage());
    }
}
