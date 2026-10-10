<?php
/** Minimal operation dependencies; native Magento definitions take precedence. */
declare(strict_types=1);

namespace Magento\Backend\Model;

if (!class_exists(Session::class)) {
    class Session
    {
        public function getData($key = '', $clear = false) { return null; }
        // Native SessionManager delegates setData/unsetData to storage through this method.
        public function __call($method, $args) { return null; }
    }
}

namespace Magento\Framework\App\Response\Http;

if (!class_exists(FileFactory::class)) {
    class FileFactory
    {
        public function create($fileName, $content, $baseDir = 'root', $contentType = 'application/octet-stream', $contentLength = null)
        {
            return null;
        }
    }
}
