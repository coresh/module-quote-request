<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Integration;

use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class SubmitCsrfTest extends AbstractController
{
    public function testAjaxPostWithoutFormKeyCannotCreateRequest(): void
    {
        $this->getRequest()->setMethod('POST')->setPostValue(['product_id' => 42, 'quantity' => 1]);
        $this->getRequest()->setHeader('X-Requested-With', 'XMLHttpRequest');
        $this->dispatch('quoterequest/request/submit');
        self::assertSame(400, $this->getResponse()->getHttpResponseCode());
        $response = json_decode($this->getResponse()->getBody(), true, 32, JSON_THROW_ON_ERROR);
        self::assertFalse($response['success']);
        self::assertArrayNotHasKey('request_id', $response);
    }

    public function testGetSubmissionIsRejectedByMagentoPostActionGuard(): void
    {
        $this->getRequest()->setMethod('GET');
        $this->dispatch('quoterequest/request/submit');
        self::assertGreaterThanOrEqual(400, $this->getResponse()->getHttpResponseCode());
    }
}
