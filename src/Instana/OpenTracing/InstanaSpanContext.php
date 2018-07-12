<?php

namespace Instana\OpenTracing;

use ArrayIterator;
use OpenTracing\SpanContext;
use Traversable;

final class InstanaSpanContext implements SpanContext
{
    /**
     * @var string
     */
    private $spanId;

    /**
     * @var string
     */
    private $parentId;

    /**
     * @var string
     */
    private $traceId;

    /**
     * @var array
     */
    private $baggageItems;

    /**
     * @param string $spanId
     * @param string $parentId
     * @param string $traceId
     * @param array $baggageItems
     */
    private function __construct($spanId, $parentId, $traceId, array $baggageItems = [])
    {
        $this->spanId = $spanId;
        $this->parentId = $parentId;
        $this->traceId = $traceId;
        $this->baggageItems = $baggageItems;
    }

    /**
     * @param string $traceId
     * @param string $spanId
     * @return InstanaSpanContext
     * @throws \Exception when ID format is invalid
     */
    public static function fromDistributed($traceId, $spanId)
    {
        IdGenerator::assertTraceId($traceId);
        IdGenerator::assertSpanId($spanId);
        return new self($spanId, null, $traceId);
    }

    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        return new ArrayIterator($this->baggageItems);
    }

    /**
     * Returns the value of a baggage item based on its key. If there is no
     * value with such key it will return null.
     *
     * @param string $key
     * @return string|null
     */
    public function getBaggageItem($key)
    {
        return isset($this->baggageItems[$key]) ? $this->baggageItems[$key] : null;
    }

    /**
     * Creates a new SpanContext out of the existing one and the new key => value pair.
     *
     * @param string $key
     * @param string $value
     * @return SpanContext
     */
    public function withBaggageItem($key, $value)
    {
        $baggage = $this->baggageItems;
        $baggage[$key] = $value;

        return new self($this->spanId, $this->parentId, $this->traceId, $baggage);
    }

    /**
     * Creates a new Child SpanContext with a new Span ID
     *
     * @private
     */
    public function createChildContext()
    {
        $spanId = IdGenerator::spanId();
        return new self($spanId, $this->spanId, $this->traceId, $this->baggageItems);
    }

    /**
     * Creates a new SpanContext with a new Trace ID, Span ID and optional baggage items
     *
     * @private
     */
    public static function createRoot(array $baggageItems = [])
    {
        $id = IdGenerator::spanId();
        return new self($id, null, $id, $baggageItems);
    }

    /**
     * Returns the span's span ID
     *
     * @return string
     */
    public function getSpanId()
    {
        return $this->spanId;
    }

    /**
     * Returns the span's parent ID (or null if not set)
     *
     * @return string|null
     */
    public function getParentId()
    {
        return $this->parentId;
    }

    /**
     * Returns the span's trace ID
     * @return string
     */
    public function getTraceId()
    {
        return $this->traceId;
    }
}
