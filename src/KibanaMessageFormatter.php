<?php

namespace Linkprofit\KibanaFormatter;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use Monolog\Formatter\NormalizerFormatter;
use Throwable;

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
     * Build KibanaMessage
     *
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

        if (isset($record['context']['exception']['file'])
            && null === $this->message->getFile()
            && preg_match("/^(.+):(\d+)$/", $record['context']['exception']['file'], $matches)
        ) {
            $this->message->setFile($matches[1]);
            $this->message->setLine($matches[2]);
        }

        return $this->message;
    }

    /**
     * Data normalization
     *
     * @param $data
     * @param int $depth
     *
     * @return array|mixed|string|null
     */
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

        if ($data instanceof DateTime) {
            return $data->format($this->dateFormat);
        }

        if (is_object($data)) {
            if ($data instanceof Throwable) {
                return $this->normalizeException($data);
            }

            $encodeOptions = JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE;

            $value = json_decode(json_encode($data, $encodeOptions), true);

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

    /**
     * Exception object normalization
     *
     * @param $e
     *
     * @return array
     */
    protected function normalizeException($e)
    {
        if (!$e instanceof Throwable) {
            $msg = 'Throwable expected, got ' . gettype($e) . ' / ' . get_class($e);
            throw new InvalidArgumentException($msg);
        }

        $data = array(
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile() . ':' . $e->getLine(),
        );

        if ($e instanceof \SoapFault) {
            if (isset($e->faultcode)) {
                $data['faultcode'] = $e->faultcode;
            }

            if (isset($e->faultactor)) {
                $data['faultactor'] = $e->faultactor;
            }

            if (isset($e->detail)) {
                $data['detail'] = $e->detail;
            }
        }

        $trace = $e->getTrace();
        foreach ($trace as $frame) {
            if (isset($frame['file'])) {
                $data['trace'][] = $frame['file'] . ':' . $frame['line'];
            } elseif (isset($frame['function']) && $frame['function'] === '{closure}') {
                // Simplify closures handling
                $data['trace'][] = $frame['function'];
            } else {
                if (isset($frame['args'])) {
                    // Make sure that objects present as arguments are not serialized nicely but rather only
                    // as a class name to avoid any unexpected leak of sensitive information
                    $frame['args'] = array_map(function ($arg) {
                        if (is_object($arg) && !($arg instanceof DateTime || $arg instanceof DateTimeInterface)) {
                            return sprintf("[object] (%s)", get_class($arg));
                        }

                        return $arg;
                    }, $frame['args']);
                }
                // We should again normalize the frames, because it might contain invalid items
                $data['trace'][] = $this->normalize($frame);
            }
        }

        if ($previous = $e->getPrevious()) {
            $data['previous'] = $this->normalizeException($previous);
        }

        return $data;
    }
}
