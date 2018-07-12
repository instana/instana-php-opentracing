<?php

namespace Instana\OpenTracing;

use Iterator;
use OpenTracing\SpanContext;
use PHPUnit\Framework\TestCase;

class InstanaSpanContextTest extends TestCase
{
    /**
     * @test
     */
    public function getIteratorReturnsIterator()
    {
        $spanContext = InstanaSpanContext::createRoot();
        $this->assertInstanceOf(Iterator::class, $spanContext->getIterator());
    }

    /**
     * @test
     */
    public function getBaggageItemReturnsNullForInexistingItems()
    {
        $spanContext = InstanaSpanContext::createRoot();
        $this->assertNull($spanContext->getBaggageItem("foo"));
    }

    /**
     * @test
     */
    public function getBaggageItemReturnsExistingItems()
    {
        $spanContext = InstanaSpanContext::createRoot(['foo' => 'bar']);
        $this->assertSame('bar', $spanContext->getBaggageItem('foo'));
    }

    /**
     * @test
     */
    public function withBaggageItemReturnsNewSpanContextWithItemAdded()
    {
        $initialSpanContext = InstanaSpanContext::createRoot();
        $newSpanContext = $initialSpanContext->withBaggageItem('foo', 'bar');
        $this->assertInstanceOf(SpanContext::class, $newSpanContext);
        $this->assertNull($initialSpanContext->getBaggageItem('foo'));
        $this->assertSame('bar', $newSpanContext->getBaggageItem('foo'));
    }

    /**
     * @test
     */
    public function createRootInitsIds()
    {
        $spanContext = InstanaSpanContext::createRoot();
        $this->assertNull($spanContext->getParentId());
        $this->assertRegExp('#^[0-9a-f]{16}$#', $spanContext->getSpanId());
        $this->assertSame($spanContext->getTraceId(), $spanContext->getSpanId());
    }
}
