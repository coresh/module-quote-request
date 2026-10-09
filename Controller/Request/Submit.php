<?php
/** SPDX-License-Identifier: GPL-3.0-or-later */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Controller\Request;

use Coresh\QuoteRequest\Model\Service\SubmitQuoteRequest;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/** Explicit form-key validation also covers Magento's AJAX requests. */
class Submit implements HttpPostActionInterface
{
    public function __construct(
        private readonly Http $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Validator $formKeyValidator,
        private readonly FormKey $formKey,
        private readonly SubmitQuoteRequest $submitter,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);
        if (!$this->request->isPost()) {
            return $result->setHttpResponseCode(405)->setData([
                'success' => false,
                'message' => (string) __('This request requires POST.')
            ]);
        }
        // Never accept CSRF tokens from a query string or malformed array fields.
        $formKey = $this->request->getPostValue('form_key');
        if (!is_string($formKey) || $formKey === ''
            || !hash_equals($this->formKey->getFormKey(), $formKey)
            || !$this->formKeyValidator->validate($this->request)
        ) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => (string) __('Your session has expired. Please refresh the page and try again.')
            ]);
        }
        try {
            $post = $this->request->getPostValue();
            $response = $this->submitter->submit(is_array($post) ? $post : []);
            return $result->setData($response);
        } catch (LocalizedException $exception) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $exception->getMessage()
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Quote request submission failed.', ['error_code' => 'submission_failed']);
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string) __('The request could not be processed. Please try again later.')
            ]);
        }
    }
}
