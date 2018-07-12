<?php

namespace Instana\OpenTracing;

use DateTimeInterface;

final class Microtime
{
    /**
     * @var string
     */
    private $microtime;

    /**
     * @param string $microtime
     */
    private function __construct($microtime)
    {
        $this->microtime = $microtime;
    }

    /**
     *
     * @param float|int|DateTimeInterface|null $timestamp if passing float or int
     * it should represent the timestamp (including as many decimal places as you need)
     * @return Microtime
     */
    public static function create($timestamp = null)
    {
        if (null === $timestamp) {
            $timestamp = microtime(true);
        }

        if (is_float($timestamp)) {
            return new static(number_format($timestamp, 6, '', ''));
        }

        if (is_int($timestamp)) {
            return new static((string) ($timestamp * 1000 * 1000));
        }

        if ($timestamp instanceof DateTimeInterface) {
            return new static($timestamp->format('Uu'));
        }

        throw new \InvalidArgumentException("Cannot handle passed timestamp.");
    }

    /**
     * @return string
     */
    public function getMicrotime()
    {
        return number_format($this->microtime, 0, '', '');
    }

    /**
     * @return string
     */
    public function getMilliseconds()
    {
        return number_format((int) ($this->microtime / 1000), 0, '', '');
    }

    /**
     * @param Microtime $other
     * @return Microtime
     */
    public function durationFrom(Microtime $other)
    {
        $duration = (int) $this->microtime - (int) $other->microtime;
        if ($duration < 0) {
            throw new \RangeException("Duration cannot be less than zero");
        }
        return new static($duration);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->microtime;
    }
}
