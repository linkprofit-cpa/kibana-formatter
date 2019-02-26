<?php

namespace App\Common;

use Gelf\Message;
use RuntimeException;

/**
 * Class for Kibana log-message
 */
class KibanaMessage extends Message
{
    /** @var string */
    protected $appCode;
    /** @var string */
    protected $appVersion;
    /** @noinspection MagicMethodsValidityInspection */
    /** @noinspection PhpMissingParentConstructorInspection */

    /**
     * Init message with default values
     *
     * @param string $appCode
     * @param string $appVersion
     */
    public function __construct(string $appCode, string $appVersion)
    {
        $this->host = gethostname();
        $this->version = '1.0';
        $this->appVersion = $appVersion;
        $this->appCode = $appCode;
    }

    public function setLevel($level)
    {
        $this->level = $level;

        return $this;
    }

    /**
     * Get application code
     *
     * @return string
     */
    public function getAppCode(): string
    {
        return $this->appCode;
    }

    /**
     * Get application version
     *
     * @return string
     */
    public function getAppVersion(): string
    {
        return $this->appVersion;
    }

    /**
     * Get message severity
     *
     * @return string
     */
    public function getSyslogLevel()
    {
        return $this->level;
    }

    /**
     * Set additional data to message
     *
     * @param $key
     * @param $value
     * @param string $scope
     * @return $this|Message
     */
    public function setAdditional($key, $value, $scope = 'context')
    {
        if (!$key) {
            throw new RuntimeException('Additional field key cannot be empty');
        }

        $this->additionals[$scope][$key] = $value;

        return $this;
    }

    /**
     * Convert message to array
     *
     * @return array
     */
    public function toArray()
    {
        $message = [
            'app'           => $this->getAppCode(),
            'version'       => $this->getAppVersion(),
            'format'        => $this->getVersion(),
            'host'          => $this->getHost(),
            'short_message' => $this->getShortMessage(),
            'full_message'  => $this->getFullMessage(),
            'level'         => $this->getSyslogLevel(),
            'timestamp'     => $this->getTimestamp(),
            'facility'      => $this->getFacility(),
            'file'          => $this->getFile(),
            'line'          => $this->getLine(),
        ];

        foreach ($this->getAllAdditionals() as $key => $value) {
            $message['_'. $key] = $value;
        }

        return array_filter($message, function ($message) {
            return is_bool($message)
                || (is_string($message) && $message !== '')
                || is_int($message)
                || !empty($message);
        });
    }
}
