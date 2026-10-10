<?php
/** Minimal route-observer contracts for isolated tests; native Magento classes take precedence. */
declare(strict_types=1);

namespace Magento\Framework\App {
    if (!interface_exists(ActionInterface::class)) {
        interface ActionInterface {
            public const FLAG_NO_DISPATCH = 'no-dispatch';
            public function execute();
        }
    }
    if (!class_exists(ActionFlag::class)) {
        class ActionFlag {
            public function set($action, $flag, $value) {}
        }
    }
}
namespace Magento\Framework\App\Response {
    if (!class_exists(Http::class)) {
        class Http {
            public function setNoCacheHeaders() { return $this; }
            public function setHttpResponseCode($code) { return $this; }
            public function setHeader($name, $value, $replace = false) { return $this; }
            public function setBody($body) { return $this; }
        }
    }
    if (!interface_exists(RedirectInterface::class)) {
        interface RedirectInterface {
            public function redirect($response, $path, $arguments = []);
        }
    }
}
namespace Magento\Framework\Event {
    if (!interface_exists(ObserverInterface::class)) {
        interface ObserverInterface { public function execute(Observer $observer); }
    }
    if (!class_exists(Observer::class)) {
        class Observer extends \Magento\Framework\DataObject {}
    }
}
namespace Magento\Framework {
    if (!class_exists(Event::class)) {
        class Event extends DataObject {}
    }
    if (!interface_exists(UrlInterface::class)) {
        interface UrlInterface { public function getUrl($routePath = null, $routeParams = null); }
    }
}
namespace Magento\Framework\Message {
    if (!interface_exists(ManagerInterface::class)) {
        interface ManagerInterface { public function addErrorMessage($message); }
    }
}
namespace Magento\Checkout\Model {
    if (!class_exists(Session::class)) {
        class Session {
            public function getQuoteId() { return null; }
            public function getQuote() { return null; }
        }
    }
}
