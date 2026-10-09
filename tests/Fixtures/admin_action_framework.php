<?php
/** Minimal controller doubles for isolated tests; real Magento classes take precedence. */
declare(strict_types=1);

namespace Magento\Backend\App;

if (!class_exists(Action::class)) {
    abstract class Action
    {
        protected $messageManager;
        protected $resultRedirectFactory;
        public function getRequest() { return null; }
    }
}
