<?php
/**
 * Coresh Quote Request
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
declare(strict_types=1);

namespace Coresh\QuoteRequest\Model\Validation;

use Coresh\QuoteRequest\Model\Config;
use Magento\Framework\Exception\LocalizedException;

/**
 * Validate an explicit input allowlist before resolving server-side product data.
 *
 * User text remains plain text. HTML/email/admin renderers must escape it in their
 * own output context; attempting to sanitize HTML here would not replace that.
 */
class QuoteRequestValidator
{
    private const MAX_PRODUCT_ID = '4294967295';
    private const DATABASE_MAX_QUANTITY = '99999999.9999';
    private const DEFAULT_MAX_QUANTITY = '1000000.0000';
    private const DEFAULT_MAX_MESSAGE_LENGTH = 5000;
    private const MAX_MESSAGE_BYTES = 65535;

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function validate(array $input, int $storeId): array
    {
        if (array_key_exists('honeypot', $input)
            && (!is_string($input['honeypot']) || $input['honeypot'] !== '')
        ) {
            $this->reject();
        }

        $productId = $this->positiveId($input['product_id'] ?? null);
        $quantity = $this->normalizeQuantity($input['quantity'] ?? null, $storeId);
        $name = $this->text($input['customer_name'] ?? null, 128, false, true);
        $email = $this->text($input['customer_email'] ?? null, 254, false, true);
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->reject();
        }

        $telephone = array_key_exists('customer_telephone', $input)
            ? $this->text($input['customer_telephone'], 64, false, false)
            : '';
        $messageLimit = $this->getMaximumMessageLength($storeId);
        $message = $this->text($input['message'] ?? null, $messageLimit, true, true);
        if (strlen($message) > self::MAX_MESSAGE_BYTES) {
            $this->reject();
        }

        $token = $input['request_token'] ?? null;
        if (!is_string($token) || preg_match('/\A[0-9a-fA-F]{64}\z/D', $token) !== 1) {
            $this->reject();
        }

        return [
            'product_id' => $productId,
            'quantity' => $quantity,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_telephone' => $telephone,
            'message' => $message,
            'request_token' => strtolower($token),
            'super_attribute' => $this->superAttributes(
                array_key_exists('super_attribute', $input) ? $input['super_attribute'] : []
            ),
        ];
    }

    /**
     * Normalize a decimal without rounding or using floating-point arithmetic.
     *
     * @throws LocalizedException
     */
    public function normalizeQuantity(mixed $quantity, int $storeId): string
    {
        $normalized = $this->decimal($quantity);
        if ($normalized === null || $normalized === '0.0000') {
            $this->reject();
        }

        $maximum = $this->getMaximumQuantity($storeId);

        if ($this->compareDecimals($normalized, $maximum) > 0) {
            $this->reject();
        }

        return $normalized;
    }

    /** Shared public limit for frontend constraints and authoritative server validation. */
    public function getMaximumQuantity(int $storeId): string
    {
        $configuredMaximum = $this->decimal($this->config->getValue('security/max_quantity', $storeId));
        return $configuredMaximum !== null && $configuredMaximum !== '0.0000'
            ? $configuredMaximum
            : self::DEFAULT_MAX_QUANTITY;
    }

    /**
     * Null means the input is not an exact representable DECIMAL(12,4).
     */
    private function decimal(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $decimal = (string) $value;
        if (strlen($decimal) > 32
            || preg_match('/\A[0-9]+(?:\.[0-9]{1,4})?\z/D', $decimal) !== 1
        ) {
            return null;
        }

        $parts = explode('.', $decimal, 2);
        $integer = ltrim($parts[0], '0');
        $integer = $integer === '' ? '0' : $integer;
        if (strlen($integer) > 8) {
            return null;
        }
        $normalized = $integer . '.' . str_pad($parts[1] ?? '', 4, '0');
        if ($this->compareDecimals($normalized, self::DATABASE_MAX_QUANTITY) > 0) {
            return null;
        }

        return $normalized;
    }

    private function compareDecimals(string $left, string $right): int
    {
        [$leftInteger] = explode('.', $left, 2);
        [$rightInteger] = explode('.', $right, 2);
        $lengthComparison = strlen($leftInteger) <=> strlen($rightInteger);
        return $lengthComparison !== 0 ? $lengthComparison : strcmp($left, $right);
    }

    private function positiveId(mixed $value): int
    {
        if (!is_int($value) && !is_string($value)) {
            $this->reject();
        }
        $id = (string) $value;
        if (preg_match('/\A[1-9][0-9]{0,9}\z/D', $id) !== 1
            || (strlen($id) === 10 && strcmp($id, self::MAX_PRODUCT_ID) > 0)
        ) {
            $this->reject();
        }
        return (int) $id;
    }

    /**
     * @return array<int, int>
     */
    private function superAttributes(mixed $attributes): array
    {
        if ($attributes === '') {
            return [];
        }
        if (!is_array($attributes) || count($attributes) > 20) {
            $this->reject();
        }

        $validated = [];
        foreach ($attributes as $attributeId => $optionId) {
            $validated[$this->positiveId($attributeId)] = $this->positiveId($optionId);
        }
        ksort($validated, SORT_NUMERIC);
        return $validated;
    }

    private function text(mixed $value, int $maxLength, bool $allowNewlines, bool $required): string
    {
        if (!is_string($value)
            || strlen($value) > self::MAX_MESSAGE_BYTES
            || preg_match('//u', $value) !== 1
        ) {
            $this->reject();
        }

        if ($allowNewlines) {
            // Browser form encoding may submit CRLF; store plain-text newlines as LF.
            $value = str_replace(["\r\n", "\r"], "\n", $value);
        }
        // Check before trimming so control bytes cannot disappear at the edges.
        $controlPattern = $allowNewlines
            ? '/[\x00-\x08\x0B-\x1F\x7F]/'
            : '/[\x00-\x1F\x7F]/';
        if (preg_match($controlPattern, $value) === 1) {
            $this->reject();
        }
        $characterCount = preg_match_all('/./us', $value);
        if ($characterCount === false || $characterCount > $maxLength) {
            $this->reject();
        }

        $value = trim($value);
        if ($required && $value === '') {
            $this->reject();
        }
        return $value;
    }

    public function getMaximumMessageLength(int $storeId): int
    {
        $configured = $this->config->getValue('security/max_message_length', $storeId);
        if (preg_match('/\A[1-9][0-9]{0,4}\z/D', $configured) !== 1) {
            return self::DEFAULT_MAX_MESSAGE_LENGTH;
        }
        return min((int) $configured, self::MAX_MESSAGE_BYTES);
    }

    private function reject(): never
    {
        throw new LocalizedException(__('Please check the quote request details and try again.'));
    }
}
