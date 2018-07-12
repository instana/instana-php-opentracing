<?php

namespace Instana\OpenTracing;

class IdGenerator
{
    const REGEXP_TRACE_ID = '#^[0-9a-f]{16}$#';
    const REGEXP_SPAN_ID = '#^[0-9a-f]{16}$#';

    /**
     * Returns a new Span ID
     *
     * @return string
     * @throws \Exception
     */
    public static function spanId()
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Returns a new Trace ID
     *
     * @return string
     * @throws \Exception
     */
    public static function traceId()
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * @param string $spanId
     * @throws \DomainException
     */
    public static function assertSpanId($spanId)
    {
        if (!self::isValidSpanId($spanId)) {
            throw new \DomainException('Not a valid Trace ID');
        }
    }

    /**
     * @param string $traceId
     * @throws \DomainException
     */
    public static function assertTraceId($traceId)
    {
        if (!self::isValidTraceId($traceId)) {
            throw new \DomainException('Not a valid Trace ID');
        }
    }

    /**
     * @param $spanId
     * @return boolean
     */
    public static function isValidSpanId($spanId)
    {
        return (bool) preg_match(self::REGEXP_SPAN_ID, $spanId);
    }

    /**
     * @param $traceId
     * @return boolean
     */
    public static function isValidTraceId($traceId)
    {
        return (bool) preg_match(self::REGEXP_TRACE_ID, $traceId);
    }
}
