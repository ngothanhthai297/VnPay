<?php

namespace VnPay\PaymentAll\Logger;

use Monolog\Formatter\LineFormatter;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = \Monolog\Logger::INFO; // Your level type

    public function __construct(
        $filePath = null,
        $clean = false
    ) {
        $file = new \Magento\Framework\Filesystem\Driver\File();
        $absPath = BP.$filePath;

        if ($clean && $file->isExists($absPath)) {
            $file->deleteFile($absPath);
        }
        parent::__construct($file, null, $filePath);
        $this->setFormatter(new LineFormatter(null, "Y-m-d H:i:s", true));
    }
}
