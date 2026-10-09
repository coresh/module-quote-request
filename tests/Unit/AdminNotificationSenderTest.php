<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Email\AdminNotificationSender;
use Coresh\QuoteRequest\Model\Email\TemplateVariablesBuilder;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Coresh\QuoteRequest\Model\ResourceModel\QuoteRequest as Resource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/** Transport handoff tests do not send email or prove remote SMTP delivery. */
class AdminNotificationSenderTest extends TestCase
{
    private function fixture(string $recipient = 'quotes@example.test', string $replyTo = 'customer@example.test', bool $enabled = true): array
    {
        $config = $this->createMock(Config::class);
        $config->method('isFlag')->with('email/enabled', 2)->willReturn($enabled);
        $config->method('getRecipient')->with(2)->willReturn($recipient);
        $config->method('getValue')->willReturn('');
        $request = $this->createMock(QuoteRequest::class);
        $request->method('getId')->willReturn(17);
        $request->method('getData')->willReturnCallback(static fn ($key) => ['store_id' => 2,
            'customer_email' => $replyTo][$key] ?? null);
        $resource = $this->createMock(Resource::class);
        $resource->method('load')->willReturnSelf();
        $variables = $this->createMock(TemplateVariablesBuilder::class);
        $variables->method('build')->willReturn(['request_id' => 17]);
        $transport = $this->createMock(TransportInterface::class);
        $builder = $this->createMock(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setTemplateVars', 'setFromByScope',
            'addTo', 'addReplyTo'] as $method) {
            $builder->method($method)->willReturnSelf();
        }
        $builder->method('getTransport')->willReturn($transport);
        $inline = $this->createMock(StateInterface::class);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->with(2)->willReturn($this->createMock(Store::class));
        $logger = $this->createMock(LoggerInterface::class);
        $sender = new AdminNotificationSender($config, $resource, $variables, $builder, $inline, $stores, $logger);
        return compact('config', 'request', 'resource', 'variables', 'transport', 'builder', 'inline', 'logger', 'sender');
    }

    public function testDisabledNotificationDoesNotClaimLeaseOrPrepareTransport(): void
    {
        $f = $this->fixture(enabled: false);
        $f['resource']->expects(self::never())->method('claimEmailAttempt');
        $f['builder']->expects(self::never())->method('getTransport');
        $f['inline']->expects(self::never())->method('suspend');
        self::assertFalse($f['sender']->sendNotification($f['request']));
    }

    public function testBusyLeaseDoesNotSendOrOverwriteOtherWorkerState(): void
    {
        $f = $this->fixture();
        $f['resource']->method('claimEmailAttempt')->willReturn(false);
        $f['resource']->expects(self::never())->method('completeEmailAttempt');
        $f['resource']->expects(self::never())->method('load');
        $f['transport']->expects(self::never())->method('sendMessage');
        $this->expectException(LocalizedException::class);
        $f['sender']->sendNotification($f['request']);
    }

    public function testSuccessfulHandoffUsesStoreScopeAndOwnedAttemptThenRestoresTranslation(): void
    {
        $f = $this->fixture();
        $f['resource']->expects(self::once())->method('claimEmailAttempt')->with(17)->willReturn(5);
        $f['resource']->expects(self::exactly(2))->method('load')->with($f['request'], 17);
        $f['builder']->expects(self::once())->method('setTemplateIdentifier')->with('coresh_quote_request_admin_notification');
        $f['builder']->expects(self::once())->method('setTemplateOptions')->with(['area' => 'frontend', 'store' => 2]);
        $f['builder']->expects(self::once())->method('setFromByScope')->with('general', 2);
        $f['builder']->expects(self::once())->method('addTo')->with('quotes@example.test');
        $f['builder']->expects(self::once())->method('addReplyTo')->with('customer@example.test');
        $f['transport']->expects(self::once())->method('sendMessage');
        $f['resource']->expects(self::once())->method('completeEmailAttempt')->with(17, 'sent', null, 5);
        $f['inline']->expects(self::once())->method('suspend');
        $f['inline']->expects(self::once())->method('resume');
        self::assertTrue($f['sender']->sendNotification($f['request']));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeAddresses')]
    public function testInvalidRecipientAndReplyToNeverReachTransport(string $recipient, string $replyTo): void
    {
        $f = $this->fixture($recipient, $replyTo);
        $f['resource']->method('claimEmailAttempt')->willReturn(5);
        $f['resource']->expects(self::once())->method('completeEmailAttempt')->with(17, 'failed', 'notification_failed', 5);
        $f['builder']->expects(self::never())->method('getTransport');
        $f['inline']->expects(self::never())->method('suspend');
        $f['logger']->expects(self::once())->method('error')->with('Quote request email transport failed.',
            ['request_id' => 17, 'error_code' => 'notification_failed']);
        $this->expectException(LocalizedException::class);
        $f['sender']->sendNotification($f['request']);
    }

    public static function unsafeAddresses(): array
    {
        return [["quotes@example.test\r\nBcc: victim@example.test", 'customer@example.test'],
            ['quotes@example.test', "customer@example.test\nBcc: victim@example.test"],
            ['invalid-address', 'customer@example.test'], ['quotes@example.test', 'invalid-address']];
    }

    public function testTransportExceptionMarksOnlyOwnedAttemptFailedAndDoesNotDiscloseSecrets(): void
    {
        $f = $this->fixture();
        $f['resource']->method('claimEmailAttempt')->willReturn(5);
        $f['transport']->method('sendMessage')->willThrowException(new \RuntimeException('SMTP password customer@example.test'));
        $f['resource']->expects(self::once())->method('completeEmailAttempt')->with(17, 'failed', 'notification_failed', 5);
        $f['inline']->expects(self::once())->method('suspend');
        $f['inline']->expects(self::once())->method('resume');
        $f['logger']->expects(self::once())->method('error')->with('Quote request email transport failed.',
            ['request_id' => 17, 'error_code' => 'notification_failed']);
        try {
            $f['sender']->sendNotification($f['request']);
            self::fail('The transport exception must become a safe localized failure.');
        } catch (LocalizedException $exception) {
            self::assertStringNotContainsString('password', $exception->getMessage());
            self::assertStringNotContainsString('customer@example.test', $exception->getMessage());
        }
    }
}
