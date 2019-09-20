<?php

namespace Instana\OpenTracing;

use OpenTracing\Span;
use PHPUnit\Framework\TestCase;
use OpenTracing\ScopeManager;

class InstanaTracerTest extends TestCase
{
    /**
     * @var InstanaTracer
     */
    private $tracer;

    /**
     * @var  InstanaHttpSpanFlusher
     */
    private $spanFlusher;

    /**
     * @var InstanaScopeManager
     */
    private $scopeManager;

    /**
     * @var InstanaSpanFactory
     */
    private $spanFactory;

    public function setUp()
    {
        $this->spanFlusher = \Phake::mock(InstanaTcpSpanFlusher::class);
        $this->scopeManager = \Phake::mock(InstanaScopeManager::class);
        $this->spanFactory = \Phake::partialMock(InstanaSpanFactory::class, InstanaSdkSpan::class);
        $this->tracer = new InstanaTracer($this->scopeManager, $this->spanFlusher, $this->spanFactory);
    }

    /**
     * @test
     */
    public function getScopeManagerReturnsScopeManager()
    {
        $this->assertInstanceOf(ScopeManager::class, $this->tracer->getScopeManager());
    }

    /**
     * @test
     */
    public function startSpanReturnsSpan()
    {
        $this->assertInstanceOf(Span::class, $this->tracer->startSpan('dummy', []));
    }

    /**
     * @test
     */
    public function flushAllUnflushedOnlyFinishedSpans()
    {
        $span1 = $this->tracer->startSpan('dummy', []);
        $span2 = $this->tracer->startSpan('dummy', []);

        $this->tracer->flush();

        \Phake::verifyNoInteraction($this->spanFlusher);
    }

    /**
     * @test
     */
    public function flushAllUnflushedspans()
    {
        $span1 = $this->tracer->startSpan('dummy', []);
        $span2 = $this->tracer->startSpan('dummy', []);
        $span1->finish();
        $span2->finish();

        $this->tracer->flush();

        \Phake::verify($this->spanFlusher)->flushAll(\Phake::capture($flushables));
        $this->assertCount(2, $flushables);
    }

    /**
     * @test
     */
    public function startActiveSpanNotifiesScopeManager()
    {
        \Phake::when($this->scopeManager)->activate(\Phake::anyParameters())->thenReturn(\Phake::mock(InstanaScope::class));

        $this->tracer->startActiveSpan('dummy', []);

        \Phake::verify($this->scopeManager)->activate($this->isInstanceOf(InstanaSpan::class), true);
    }

    /**
     * @test
     */
    public function startActiveSpanNotifiesScopeManagerNotFinishOnClose()
    {
        \Phake::when($this->scopeManager)->activate(\Phake::anyParameters())->thenReturn(\Phake::mock(InstanaScope::class));

        $this->tracer->startActiveSpan('dummy', ['finish_span_on_close' => false]);

        \Phake::verify($this->scopeManager)->activate($this->isInstanceOf(InstanaSpan::class), false);
    }

    /**
     * @test
     */
    public function injectText()
    {
        $span = $this->tracer->startSpan('dummy', []);

        $data = [];
        $this->tracer->inject($span->getContext(), \OpenTracing\Formats\TEXT_MAP, $data);

        $this->assertCount(3, $data);
        $this->assertArrayHasKey('X-INSTANA-S', $data);
        $this->assertArrayHasKey('X-INSTANA-T', $data);
        $this->assertArrayHasKey('X-INSTANA-L', $data);
    }

    /**
     * @test
     */
    public function injectFromInvalidContext()
    {
        $this->expectException(\RuntimeException::class);

        $data = [];
        $this->tracer->inject(
            \Phake::mock(\OpenTracing\SpanContext::class),
            \OpenTracing\Formats\TEXT_MAP,
            $data
        );
    }

    /**
     * @test
     */
    public function extractTextMapHttpHeader()
    {
        $context = $this->tracer->extract(\OpenTracing\Formats\TEXT_MAP, [
            'HTTP_X_INSTANA_S' => '0a13e31f73fe93dc',
            'HTTP_X_INSTANA_T' => 'f2999d3780d3bedb',
            'HTTP_X_INSTANA_L' => '1',
        ]);

        $this->assertInstanceOf(InstanaSpanContext::class, $context);
        $this->assertEquals('0a13e31f73fe93dc', $context->getSpanId());
        $this->assertEquals('f2999d3780d3bedb', $context->getTraceId());
    }
}
