<?php
declare(strict_types=1);
namespace Coresh\QuoteRequest\Test\Unit;

use Coresh\QuoteRequest\Controller\Request\Submit;
use Coresh\QuoteRequest\Model\Service\SubmitQuoteRequest;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SubmitControllerTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedRequests')]
    public function testGetAndInvalidCsrfNeverReachSubmitter(bool $isPost, mixed $postedToken, bool $frameworkValid, int $status): void
    {
        $request = $this->createMock(Http::class);
        $request->method('isPost')->willReturn($isPost);
        $request->method('getPostValue')->willReturn($postedToken);
        $result = $this->createMock(Json::class);
        $result->expects(self::once())->method('setHeader')->with('Cache-Control', 'no-store, no-cache, must-revalidate', true)->willReturnSelf();
        $result->expects(self::once())->method('setHttpResponseCode')->with($status)->willReturnSelf();
        $result->expects(self::once())->method('setData')->with(self::callback(static fn ($data) => $data['success'] === false))->willReturnSelf();
        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($result);
        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('correcttoken');
        $validator = $this->createMock(Validator::class);
        $validator->method('validate')->willReturn($frameworkValid);
        $submitter = $this->createMock(SubmitQuoteRequest::class);
        $submitter->expects(self::never())->method('submit');
        $logger = $this->createMock(LoggerInterface::class);
        self::assertSame($result, (new Submit($request, $factory, $validator, $formKey, $submitter, $logger))->execute());
    }

    public static function rejectedRequests(): array
    {
        return [[false, 'correcttoken', true, 405], [true, null, true, 400],
            [true, [], true, 400], [true, '', true, 400], [true, 'wrongtoken', true, 400],
            [true, 'correcttoken', false, 400]];
    }

    public function testUnexpectedFailureReturnsGenericResponseAndSafeLog(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('isPost')->willReturn(true);
        $request->method('getPostValue')->willReturnCallback(static fn ($key = null) => $key === 'form_key' ? 'correcttoken' : ['form_key' => 'correcttoken']);
        $result = $this->createMock(Json::class);
        $result->method('setHeader')->willReturnSelf();
        $result->expects(self::once())->method('setHttpResponseCode')->with(500)->willReturnSelf();
        $result->expects(self::once())->method('setData')->with(self::callback(static fn ($data) =>
            $data['success'] === false && !str_contains($data['message'], 'private-password')))->willReturnSelf();
        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($result);
        $formKey = $this->createMock(FormKey::class);
        $formKey->method('getFormKey')->willReturn('correcttoken');
        $validator = $this->createMock(Validator::class);
        $validator->method('validate')->willReturn(true);
        $submitter = $this->createMock(SubmitQuoteRequest::class);
        $submitter->method('submit')->willThrowException(new \RuntimeException('SQL private-password customer@example.test'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Quote request submission failed.', ['error_code' => 'submission_failed']);
        (new Submit($request, $factory, $validator, $formKey, $submitter, $logger))->execute();
    }
}
