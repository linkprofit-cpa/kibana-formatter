<?php

namespace Linkprofit\KibanaFormatter;

use InvalidArgumentException;
use Monolog\Formatter\NormalizerFormatter;

/**
 * Formatter for Kibana messages
 */
class KibanaMessageFormatter extends NormalizerFormatter
{
    private const MAX_LENGTH = 32766;

    /** @var KibanaMessage */
    private $message;

    /**
     * @param KibanaMessage $message
     */
    public function __construct(KibanaMessage $message)
    {
        parent::__construct('U.u');

        $this->message = $message;
    }

    /**
     * @param array $record
     *
     * @return KibanaMessage
     */
    public function format(array $record)
    {
        $record = parent::format($record);

        if (!isset($record['datetime'], $record['message'], $record['level'])) {
            $msg = 'The record should at least contain datetime, message and level keys, '
                . var_export($record, true) . ' given';

            throw new InvalidArgumentException($msg);
        }

        $this->message
            ->setTimestamp($record['datetime'])
            ->setShortMessage((string) $record['message'])
            ->setLevel($record['level_name']);

        // message length + system name length + 200 for padding / metadata
        $len = 200 + strlen((string) $record['message']) + strlen($this->message->getHost());

        if ($len > static::MAX_LENGTH) {
            $this->message->setShortMessage(
                substr($record['message'], 0, static::MAX_LENGTH)
            );
        }

        if (isset($record['channel'])) {
            $this->message->setFacility($record['channel']);
        }

        if (isset($record['extra']['line'])) {
            $this->message->setLine($record['extra']['line']);
            unset($record['extra']['line']);
        }
        if (isset($record['extra']['file'])) {
            $this->message->setFile($record['extra']['file']);
            unset($record['extra']['file']);
        }

        foreach ($record['extra'] as $key => $val) {
            if (is_string($val)) {
                $len = strlen($key . $val);
                $val = $len > static::MAX_LENGTH
                    ? substr($val, 0, static::MAX_LENGTH)
                    : $val;
            }

            $this->message->setAdditional($key, $val, 'extra');
        }

        foreach ($record['context'] as $key => $val) {
            if (is_string($val)) {
                $len = strlen($key . $val);
                $val = $len > static::MAX_LENGTH
                    ? substr($val, 0, static::MAX_LENGTH)
                    : $val;
            }

            $this->message->setAdditional($key, $val);
        }

        if (isset($record['context']['exception']['file'], $record['context']['exception']['line'])
            && null === $this->message->getFile()
            && null === $this->message->getLine()
        ) {
            $this->message->setFile($record['context']['exception']['file']);
            $this->message->setLine($record['context']['exception']['line']);
        }

        return $this->message;
    }

    protected function normalize($data, $depth = 0)
    {
        if ($depth > 9) {
            return 'Over 9 levels deep, aborting normalization';
        }

        if (null === $data) {
            return null;
        }

        if (is_scalar($data)) {
            if (\is_float($data)) {
                if (is_infinite($data)) {
                    return ($data > 0 ? '' : '-') . 'INF';
                }
                if (is_nan($data)) {
                    return 'NaN';
                }
            }

            return $data;
        }

        if (is_array($data)) {
            $normalized = array();

            $count = 1;
            foreach ($data as $key => $value) {
                if ($count++ > 1000) {
                    $normalized['...'] = 'Over 1000 items (' . count($data) . ' total), aborting normalization';
                    break;
                }

                $normalized[$key] = $this->normalize($value, $depth + 1);
            }

            return $normalized;
        }

        if ($data instanceof \DateTime) {
            return $data->format($this->dateFormat);
        }

        if (is_object($data)) {
            // non-serializable objects that implement __toString stringified
            $value = method_exists($data, '__toString')
                ? $data->__toString()
                : json_decode(json_encode($data), true);

            if (is_array($value)) {
                $value = ['class_name' => get_class($data)] + $value;
            }

            return $value;
        }

        if (is_resource($data)) {
            return sprintf('[resource] (%s)', get_resource_type($data));
        }

        return '[unknown(' . gettype($data) . ')]';
    }
}
