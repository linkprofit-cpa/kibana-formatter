<?php

namespace Linkprofit\KibanaFormatter;

use Gelf\MessageInterface;
use Gelf\MessageValidator;
use RuntimeException;

/**
 * Class for Kibana log-messages validation
 */
class KibanaMessageValidator extends MessageValidator
{
    /**
     * @param MessageInterface $message
     * @param string $reason
     *
     * @return bool
     */
    public function validate(MessageInterface $message, &$reason = '')
    {
        if ('1.0' === $message->getVersion()) {
            return $this->validate0200($message, $reason);
        }

        throw new RuntimeException(
            sprintf(
                "No validator for message version '%s'",
                $message->getVersion()
            )
        );
    }

    /**
     * Validates a message according to 1.0 standard
     *
     * @param KibanaMessage $message
     * @param string &$reason
     *
     * @return bool
     */
    protected function validate0200(KibanaMessage $message, &$reason = ''): bool
    {
        // 1.0 includes \Gelf\MessageValidator standards v1.1
        if (!$this->validate0101($message, $reason)) {
            return false;
        }

        if (self::isEmpty($message->getAppCode())) {
            $reason = 'Application code not set';

            return false;
        }

        if (self::isEmpty($message->getAppVersion())) {
            $reason = 'Application version not set';

            return false;
        }

        return true;
    }
}
