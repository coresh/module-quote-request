<?php
/**
 * Minimal isolated block doubles. These are not a Magento runtime.
 * The mutable protected $formKey matches the native Backend Template contract
 * and reproduces the PHP inheritance failure when a child redeclares it readonly.
 * Native classes are used instead when MAGENTO_UNIT_AUTOLOAD is configured.
 */
declare(strict_types=1);

namespace Magento\Framework {
    if (!interface_exists(AuthorizationInterface::class)) {
        interface AuthorizationInterface
        {
            public function isAllowed($resource, $privilege = null);
        }
    }
}

namespace Magento\Backend\Block\Template {
    if (!class_exists(Context::class)) {
        class Context {}
    }
}

namespace Magento\Backend\Block {
    if (!class_exists(Template::class)) {
        class Template extends \Magento\Framework\DataObject
        {
            protected $formKey;

            public function __construct(Template\Context $context, array $data = [])
            {
                parent::__construct($data);
            }

            public function getRequest() { return null; }
            public function getUrl($route = '', $params = []) { return ''; }
            public function getFormKey() { return $this->formKey->getFormKey(); }
        }
    }
}
