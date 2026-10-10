<?php
declare(strict_types=1);

namespace Coresh\QuoteRequest\Test\Integration;

use Magento\Email\Model\Template\Config;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Prepared native HTTP/form/ACL checks; NOT RUN without an installed Magento test harness.
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 */
class ConfigSectionTest extends AbstractBackendController
{
    protected $resource = 'Coresh_QuoteRequest::config';
    protected $uri = 'backend/admin/system_config/edit/section/coresh_quote_request/';
    protected $httpMethod = 'GET';

    public function testConfigurationPageRendersGroupsAndBothDefaultTemplateOptions(): void
    {
        $this->dispatch($this->uri);
        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        $html = $this->getResponse()->getBody();
        foreach (['general', 'email', 'catalog', 'security'] as $group) {
            self::assertStringContainsString('id="coresh_quote_request_' . $group . '"', $html);
        }
        foreach (['admin_template', 'customer_template'] as $field) {
            self::assertStringContainsString('id="coresh_quote_request_email_' . $field . '"', $html);
            self::assertStringContainsString('value="coresh_quote_request_email_' . $field . '"', $html);
        }
        self::assertStringContainsString('Save Config', $html);
    }

    public function testStoredTemplateIdentifiersRemainNativeResolvableAliases(): void
    {
        $config = $this->_objectManager->get(Config::class);
        foreach ([
            'coresh_quote_request_email_admin_template' => 'coresh_quote_request_admin_notification',
            'coresh_quote_request_email_customer_template' => 'coresh_quote_request_customer_confirmation'
        ] as $canonical => $legacy) {
            self::assertSame($config->getTemplateFilename($canonical), $config->getTemplateFilename($legacy));
            self::assertSame($config->getTemplateLabel($canonical), $config->getTemplateLabel($legacy));
        }
    }
}
