<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Model\Config;
use Coresh\QuoteRequest\Model\Email\CustomerConfirmationSender;
use Coresh\QuoteRequest\Model\Email\TemplateVariablesBuilder;
use Coresh\QuoteRequest\Model\QuoteRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/** Real sender with external transport doubles: no email is delivered by these tests. */
class CustomerConfirmationSecurityTest extends TestCase
{
    private function fixture(string $recipient = 'customer@example.test', string $sender = '', bool $enabled = true): array
    {
        $config = $this->createMock(Config::class);
        $config->method('isFlag')->with('email/customer_confirmation', 2)->willReturn($enabled);
        $config->method('getValue')->willReturnCallback(static fn ($path): string =>
            $path === 'email/sender_identity' ? $sender : '');
        $request = $this->createMock(QuoteRequest::class);
        $request->method('getData')->willReturnCallback(static fn ($key) =>
            ['store_id' => 2, 'customer_email' => $recipient][$key] ?? null);
        $variables = $this->createMock(TemplateVariablesBuilder::class);
        $variables->method('build')->willReturn(['request_id' => 17]);
        $transport = $this->createMock(TransportInterface::class);
        $builder = $this->createMock(TransportBuilder::class);
        foreach (['setTemplateIdentifier', 'setTemplateOptions', 'setTemplateVars', 'setFromByScope', 'addTo'] as $method) {
            $builder->method($method)->willReturnSelf();
        }
        $builder->method('getTransport')->willReturn($transport);
        $inline = $this->createMock(StateInterface::class);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->with(2)->willReturn($this->createMock(Store::class));
        return compact('config', 'request', 'variables', 'transport', 'builder', 'inline') + [
            'sender' => new CustomerConfirmationSender($config, $variables, $builder, $inline, $stores)
        ];
    }

    public function testDisabledConfirmationDoesNotPrepareTransportOrSuspendTranslation(): void
    {
        $f = $this->fixture(enabled: false);
        $f['builder']->expects(self::never())->method('getTransport');
        $f['inline']->expects(self::never())->method('suspend');
        $f['sender']->sendConfirmation($f['request']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeAddresses')]
    public function testUnsafeRecipientNeverReachesTransport(string $address): void
    {
        $f = $this->fixture($address);
        $f['builder']->expects(self::never())->method('getTransport');
        $f['inline']->expects(self::never())->method('suspend');
        $this->expectException(LocalizedException::class);
        $f['sender']->sendConfirmation($f['request']);
    }

    public static function unsafeAddresses(): array
    {
        return [["customer@example.test\r\nBcc: victim@example.test"],
            ["customer@example.test\nBcc: victim@example.test"], ['invalid-address'],
            [str_repeat('a', 243) . '@example.test']];
    }

    public function testUnsafeSenderIdentityCannotReachTransport(): void
    {
        $f = $this->fixture(sender: "general\r\nBcc: victim@example.test");
        $f['builder']->expects(self::never())->method('getTransport');
        $this->expectException(LocalizedException::class);
        $f['sender']->sendConfirmation($f['request']);
    }

    public function testSuccessfulHandoffUsesOriginalStoreAndRestoresTranslation(): void
    {
        $f = $this->fixture();
        $f['builder']->expects(self::once())->method('setTemplateIdentifier')->with('coresh_quote_request_customer_confirmation');
        $f['builder']->expects(self::once())->method('setTemplateOptions')->with(['area' => 'frontend', 'store' => 2]);
        $f['builder']->expects(self::once())->method('setFromByScope')->with('general', 2);
        $f['builder']->expects(self::once())->method('addTo')->with('customer@example.test');
        $f['transport']->expects(self::once())->method('sendMessage');
        $f['inline']->expects(self::once())->method('suspend');
        $f['inline']->expects(self::once())->method('resume');
        $f['sender']->sendConfirmation($f['request']);
    }

    public function testTransportFailureStillRestoresTranslation(): void
    {
        $f = $this->fixture();
        $f['transport']->method('sendMessage')->willThrowException(new \RuntimeException('SMTP unavailable'));
        $f['inline']->expects(self::once())->method('suspend');
        $f['inline']->expects(self::once())->method('resume');
        $this->expectException(\RuntimeException::class);
        $f['sender']->sendConfirmation($f['request']);
    }
}
