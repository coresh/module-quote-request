<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Exception\NotificationException;
use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Email\AdminNotificationSender;
use Coresh\QuoteRequest\Model\Email\CustomerConfirmationSender;
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

/** Fresh builders deliberately keep abandoned setup data so reuse would leak previous recipients. */
class CustomerConfirmationSenderTest extends TestCase
{
    private function request(string $email): QuoteRequest
    {
        $request = $this->getMockBuilder(QuoteRequest::class)->disableOriginalConstructor()->onlyMethods(['getId'])->getMock();
        $request->method('getId')->willReturn(17);
        $request->setData(['store_id' => 2, 'customer_email' => $email, 'email_status' => 'sending', 'email_attempts' => 5]);
        return $request;
    }

    private function fixture(): array
    {
        $state = (object) ['created' => [], 'messages' => [], 'failBuilderAt' => [], 'failTransportAt' => [],
            'enabled' => true, 'failEnable' => false, 'logs' => [], 'updates' => [], 'emailStatus' => 'sending'];
        $config = $this->createMock(Config::class);
        $config->method('isFlag')->willReturn(true);
        $config->method('getValue')->willReturn('');
        $config->method('getRecipient')->with(2)->willReturn('quotes@example.test');
        $variables = $this->createMock(TemplateVariablesBuilder::class);
        $variables->method('build')->willReturn(['request_id' => 17]);
        $factory = $this->createMock(TransportBuilderFactory::class);
        $factory->method('create')->willReturnCallback(function () use ($state): TransportBuilder {
            $index = count($state->created);
            $message = (object) ['recipient' => [], 'reply_to' => []];
            $builder = $this->createMock(TransportBuilder::class);
            $state->created[] = $message;
            foreach (['setTemplateIdentifier' => 'template', 'setTemplateOptions' => 'options',
                'setTemplateVars' => 'variables', 'setFromByScope' => 'sender', 'addTo' => 'recipient',
                'setReplyTo' => 'reply_to'] as $method => $field) {
                $builder->method($method)->willReturnCallback(static function ($value) use ($state, $index,
                    $message, $field, $method, $builder) {
                    if (in_array($field, ['recipient', 'reply_to'], true)) { $message->{$field}[] = $value; }
                    else { $message->{$field} = $value; }
                    if (($state->failBuilderAt[$index] ?? null) === $method) {
                        throw new \RuntimeException('Mail password customer-secret@example.test');
                    }
                    return $builder;
                });
            }
            $transport = $this->createMock(TransportInterface::class);
            $transport->method('sendMessage')->willReturnCallback(static function () use ($state, $index, $message): void {
                if (in_array($index, $state->failTransportAt, true)) {
                    throw new \RuntimeException('Transport password customer-secret@example.test');
                }
                $state->messages[] = clone $message;
            });
            $builder->method('getTransport')->willReturnCallback(static function () use ($state, $index, $transport) {
                if (($state->failBuilderAt[$index] ?? null) === 'getTransport') {
                    throw new \RuntimeException('Template password customer-secret@example.test');
                }
                return $transport;
            });
            return $builder;
        });
        $inline = $this->createMock(StateInterface::class);
        $inline->method('isEnabled')->willReturnCallback(static fn () => $state->enabled);
        $inline->method('disable')->willReturnCallback(static function () use ($state): void { $state->enabled = false; });
        $inline->method('enable')->willReturnCallback(static function () use ($state): void {
            if ($state->failEnable) { throw new \RuntimeException('Cleanup password customer-secret@example.test'); }
            $state->enabled = true;
        });
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->with(2)->willReturn($this->createMock(Store::class));
        $logger = $this->createMock(LoggerInterface::class);
        foreach (['error', 'warning'] as $level) {
            $logger->method($level)->willReturnCallback(static function ($message, $context) use ($state): void {
                $state->logs[] = [$message, $context];
            });
        }
        $resource = $this->createMock(Resource::class);
        $resource->method('load')->willReturnCallback(static function ($request) use ($state, $resource) {
            $request->setData('email_status', $state->emailStatus);
            $request->setData('email_attempts', 5);
            return $resource;
        });
        $resource->method('claimEmailAttempt')->willReturn(5);
        $resource->method('completeEmailAttempt')->willReturnCallback(static function ($id, $status, $code, $attempt) use ($state): void {
            $state->updates[] = [$id, $status, $code, $attempt];
            $state->emailStatus = $status;
        });
        $customer = new CustomerConfirmationSender($config, $variables, $factory, $inline, $stores, $logger);
        $admin = new AdminNotificationSender($config, $resource, $variables, $factory, $inline, $stores, $logger);
        return compact('state', 'factory', 'inline', 'customer', 'admin');
    }

    public function testEachCustomerConfirmationHasFreshBuilderAndOnlyItsOwnRecipient(): void
    {
        $f = $this->fixture();
        $f['customer']->sendConfirmation($this->request('first@example.test'));
        $f['customer']->sendConfirmation($this->request('second@example.test'));
        self::assertCount(2, $f['state']->created);
        self::assertCount(2, $f['state']->messages);
        self::assertSame(['first@example.test'], $f['state']->messages[0]->recipient);
        self::assertSame(['second@example.test'], $f['state']->messages[1]->recipient);
        self::assertSame([], $f['state']->messages[1]->reply_to);
        self::assertSame('coresh_quote_request_customer_confirmation', $f['state']->messages[1]->template);
        self::assertSame(['area' => 'frontend', 'store' => 2], $f['state']->messages[1]->options);
        self::assertTrue($f['state']->enabled);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('abandonedAdminSetup')]
    public function testAbandonedAdminSetupCannotLeakIntoCustomerConfirmation(string $method, string $code): void
    {
        $f = $this->fixture(); $f['state']->failBuilderAt[0] = $method;
        $request = $this->request('customer@example.test');
        try {
            $f['admin']->sendNotification($request);
            self::fail('The first setup must fail before transport handoff.');
        } catch (NotificationException $exception) {
            self::assertSame($code, $exception->getErrorCode());
        }
        self::assertSame(['quotes@example.test'], $f['state']->created[0]->recipient);
        $f['customer']->sendConfirmation($request);
        self::assertCount(2, $f['state']->created);
        self::assertCount(1, $f['state']->messages);
        self::assertSame(['customer@example.test'], $f['state']->messages[0]->recipient);
        self::assertSame([], $f['state']->messages[0]->reply_to);
        self::assertSame('coresh_quote_request_customer_confirmation', $f['state']->messages[0]->template);
        self::assertSame([[17, 'failed', $code, 5]], $f['state']->updates);
        self::assertTrue($f['state']->enabled);
    }

    public static function abandonedAdminSetup(): array
    {
        return [['setReplyTo', 'notification_reply_to_invalid'], ['getTransport', 'notification_template_failed']];
    }

    public function testAbandonedCustomerSetupCannotLeakIntoLaterCustomerConfirmation(): void
    {
        $f = $this->fixture(); $f['state']->failBuilderAt[0] = 'getTransport';
        try {
            $f['customer']->sendConfirmation($this->request('first@example.test'));
            self::fail('The first setup must fail.');
        } catch (\RuntimeException $exception) {
            self::assertCount(0, $f['state']->messages);
        }
        $f['customer']->sendConfirmation($this->request('second@example.test'));
        self::assertCount(2, $f['state']->created);
        self::assertSame(['second@example.test'], $f['state']->messages[0]->recipient);
        self::assertSame([], $f['state']->messages[0]->reply_to);
    }

    public function testAdminAndCustomerSuccessNeverShareRecipientsOrReplyTo(): void
    {
        $f = $this->fixture(); $request = $this->request('customer@example.test');
        self::assertTrue($f['admin']->sendNotification($request));
        $f['customer']->sendConfirmation($request);
        self::assertCount(2, $f['state']->created);
        self::assertSame(['quotes@example.test'], $f['state']->messages[0]->recipient);
        self::assertSame(['customer@example.test'], $f['state']->messages[0]->reply_to);
        self::assertSame(['customer@example.test'], $f['state']->messages[1]->recipient);
        self::assertSame([], $f['state']->messages[1]->reply_to);
    }

    public function testAlreadyDisabledTranslationIsLeftUntouched(): void
    {
        $f = $this->fixture(); $f['state']->enabled = false;
        foreach (['disable', 'enable', 'suspend', 'resume'] as $method) {
            $f['inline']->expects(self::never())->method($method);
        }
        $f['customer']->sendConfirmation($this->request('customer@example.test'));
        self::assertFalse($f['state']->enabled);
        self::assertCount(1, $f['state']->messages);
    }

    public function testCleanupFailureCannotReplaceSuccessfulCustomerHandoff(): void
    {
        $f = $this->fixture(); $f['state']->failEnable = true;
        $f['customer']->sendConfirmation($this->request('customer@example.test'));
        self::assertCount(1, $f['state']->messages);
        self::assertCount(1, $f['state']->logs);
        self::assertStringNotContainsString('password', json_encode($f['state']->logs, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('customer-secret', json_encode($f['state']->logs, JSON_THROW_ON_ERROR));
        self::assertSame('translation_cleanup_failed', $f['state']->logs[0][1]['error_code']);
    }

    public function testCleanupFailureCannotReplaceOriginalCustomerTransportError(): void
    {
        $f = $this->fixture(); $f['state']->failTransportAt = [0]; $f['state']->failEnable = true;
        try {
            $f['customer']->sendConfirmation($this->request('customer@example.test'));
            self::fail('Expected the original transport failure.');
        } catch (\RuntimeException $exception) {
            self::assertStringStartsWith('Transport ', $exception->getMessage());
            self::assertCount(0, $f['state']->messages);
            self::assertStringNotContainsString('password', json_encode($f['state']->logs, JSON_THROW_ON_ERROR));
        }
    }
}
