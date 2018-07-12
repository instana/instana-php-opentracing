<?php

namespace Instana\OpenTracing;

use OpenTracing\SpanContext;
use PHPUnit\Framework\TestCase;

class InstanaSdkSpanTest extends TestCase
{

    /**
     * @var InstanaSpan
     */
    private $span;

    public function setup()
    {
        $this->span = new InstanaSdkSpan("dummy");
    }

    /**
     * @test
     */
    public function finishSetsFinishingTimestamp()
    {
        $this->assertFalse($this->span->isFinished());
        $this->span->finish();
        $this->assertInstanceOf(Microtime::class, $this->span->isFinished());
    }

    /**
     * @test
     */
    public function getContextReturnsSpanContext()
    {
        $this->assertInstanceOf(SpanContext::class, $this->span->getContext());
    }

    /**
     * @test
     */
    public function canAddAndGetBaggageItems()
    {
        $this->span->addBaggageItem('foo', 'bar');
        $this->assertSame('bar', $this->span->getBaggageItem('foo'));
    }

    /**
     * @test
     */
    public function getOperationNameReturnsOperationName()
    {
        $this->assertSame('dummy', $this->span->getOperationName());
    }

    /**
     * @test
     */
    public function overwriteOperationNameOverwritesOperationName()
    {
        $newName = 'new name';
        $this->assertNotSame($newName, $this->span->getOperationName());
        $this->span->overwriteOperationName($newName);
        $this->assertSame($newName, $this->span->getOperationName());
    }

    /**
     * @test
     * @dataProvider provideNonNullScalarValues
     */
    public function setTagWritesNonNullScalarsToAnnotations($value)
    {
        $this->span->setTag('foo', $value);
        $spanData = $this->span->jsonSerialize();
        $tags = $spanData['data']['sdk']['custom']['tags'];
        $this->assertArrayHasKey('foo', $tags);
        $this->assertEquals($value, $tags['foo']);
    }

    /**
     * @return array
     */
    public function provideNonNullScalarValues()
    {
        return [
            'string' => ["string"],
            'bool' => [true],
            'int' => [42],
            'float' => [3.147],
        ];
    }

    /**
     * @test
     * @dataProvider provideNonNullScalarValues
     */
    public function logWritesEventsToAnnotations($value)
    {
        $this->span->log(['foo' => $value], 1234567890);
        $spanData = $this->span->jsonSerialize();
        $tags = $spanData['data']['sdk']['custom']['tags'];
        $this->assertArrayHasKey('log.1234567890000000.foo', $tags);
        $this->assertEquals($value, $tags['log.1234567890000000.foo']);
    }

    /**
     * @test
     */
    public function spanWithNoParentIsMarkedEntrySpan()
    {
        $spanData = $this->span->jsonSerialize();
        $this->assertEquals(1, $spanData['k']);
        $this->assertEquals("entry", $spanData['data']['sdk']['type']);
    }

    /**
     * @test
     */
    public function spanWithParentIsMarkedLocalSpan()
    {
        $spanContext = InstanaSpanContext::createRoot()->createChildContext();
        $span = new InstanaSdkSpan('foo', null, $spanContext);
        $spanData = $span->jsonSerialize();
        $this->assertEquals(3, $spanData['k']);
        $this->assertEquals("local", $spanData['data']['sdk']['type']);
    }

    /**
     * @test
     */
    public function spanWithSpanKindRpcServerIsMarkedEntrySpan()
    {
        $this->span->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_RPC_SERVER);
        $spanData = $this->span->jsonSerialize();
        $this->assertEquals(1, $spanData['k']);
        $this->assertEquals("entry", $spanData['data']['sdk']['type']);
    }

    /**
     * @test
     */
    public function spanWithSpanKindMessageBusConsumerIsMarkedEntrySpan()
    {
        $this->span->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_MESSAGE_BUS_CONSUMER);
        $spanData = $this->span->jsonSerialize();
        $this->assertEquals(1, $spanData['k']);
        $this->assertEquals("entry", $spanData['data']['sdk']['type']);
    }

    /**
     * @test
     */
    public function spanWithSpanKindRpcClientIsMarkedExitSpan()
    {
        $this->span->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_RPC_CLIENT);
        $spanData = $this->span->jsonSerialize();
        $this->assertEquals(2, $spanData['k']);
        $this->assertEquals("exit", $spanData['data']['sdk']['type']);
    }

    /**
     * @test
     */
    public function spanWithSpanKindMessageBusProducerIsMarkedExitSpan()
    {
        $this->span->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_MESSAGE_BUS_PRODUCER);
        $spanData = $this->span->jsonSerialize();
        $this->assertEquals(2, $spanData['k']);
        $this->assertEquals("exit", $spanData['data']['sdk']['type']);
    }

    /**
     * @test
     */
    public function spanWithSpanKindErrorIsMarkedAsErroneous()
    {
        $this->span->setTag(\OpenTracing\Tags\ERROR, 'reason');
        $spanData = $this->span->jsonSerialize();
        $this->assertEquals(1, $spanData['ec']);
        $this->assertEquals(true, $spanData['error']);
        $this->assertEquals("reason", $spanData['data']['sdk']['custom']['tags']['error']);
    }

    /**
     * @test
     */
    public function spanWithInstanaServiceTagSetsServiceNameOnEntrySpan()
    {
        $this->span->setTag(InstanaTags\SERVICE, 'example');
        $spanData = $this->span->jsonSerialize();
        $this->assertEquals("example", $spanData['data']['service']);
    }
}