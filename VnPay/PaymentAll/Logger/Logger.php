<?php

namespace VnPay\PaymentAll\Logger;

class Logger  extends \Monolog\Logger
{
    const LOG_PREFIX = 'VnPay_';

    /**
     * @param string|array $name Log file name
     * @param boolean $clear Clear old log file or not
     */
    public function __construct($name, $clear = false) {
        if (is_array($name)) {
            $constructName = $name[count($name) - 1];
        } else {
            $constructName = $name;
            $name = [$name];
        }

        $filePath = $this->getFilePath($name);
        $handlers[] = new Handler($filePath, $clear);
        parent::__construct($constructName, $handlers);
    }

    /**
     * @param array $name
     * @return string
     */
    public function getFilePath($name)
    {
        $varDir = \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR;
        $logDir = \Magento\Framework\App\Filesystem\DirectoryList::LOG;

        $name = is_array($name) ? $name : [$name];
        $fileName = array_pop($name);
        $fileName = static::LOG_PREFIX . $fileName . '.log';
        $filePath = "/$varDir/$logDir/" . implode("/", $name);
        $absPath = BP . $filePath;
        if (!file_exists($absPath)) {
            mkdir($absPath, 0777, true);
        }
        $fullPath =  "$filePath/$fileName";
        $this->fullPath = $fullPath;
        $this->fileName = $fileName;
        return $fullPath;
    }

    /**
     * Get log name with current date time
     * @param string $logFileName
     * @return string
     */
    public function getNameWithDateTime($logFileName)
    {
        $logDate = new \DateTime();
//        $logDate->setTimezone(new \DateTimeZone('Asia/Bangkok'));
        $name = $logFileName . $logDate->format("_Y-m-d_H-i-s");
        return $name;
    }

    /**
     * @param array $target
     * @return boolean
     */
    public function makeCopy($target)
    {
        $source = BP . $this->fullPath;
        $target = BP . $this->getFilePath($target);
        return copy($source, $target);
    }
}
