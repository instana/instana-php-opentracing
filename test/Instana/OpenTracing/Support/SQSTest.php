<?php

namespace Instana\OpenTracing\Support;

use Instana\OpenTracing\InstanaSpanContext;
use Instana\OpenTracing\InstanaTracer;
use PHPUnit\Framework\TestCase;

/**
 * @test
 */
class SQSTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideTracer
     */
    public function testInjectionAndExtraction(InstanaTracer $instanaTracer)
    {
        $spanContext = InstanaSpanContext::createRoot();
        $this->assertNull($spanContext->getParentId());
        $this->assertRegExp('#^[0-9a-f]{16}$#', $spanContext->getSpanId());

        $message = [
            'MessageAttributes' => []
        ];

        SQS::injectContext($instanaTracer, $spanContext, $message);

        // check if the context was injected into the SQS message
        $this->assertArrayHasKey('Instana', $message['MessageAttributes']);
        $this->assertArrayHasKey('DataType', $message['MessageAttributes']['Instana']);
        $this->assertArrayHasKey('StringValue', $message['MessageAttributes']['Instana']);

        $this->assertEquals('String', $message['MessageAttributes']['Instana']['DataType']);
        $this->assertJson($message['MessageAttributes']['Instana']['StringValue']);

        $context = json_decode($message['MessageAttributes']['Instana']['StringValue'], true);

        $this->assertEquals($spanContext->getSpanId(), $context['X-INSTANA-T']);
        $this->assertEquals($spanContext->getSpanId(), $context['X-INSTANA-S']);
        $this->assertEquals('1', $context['X-INSTANA-L']);

        /** @var InstanaSpanContext */
        $extractedContext = SQS::extractContext($instanaTracer, $message);

        $this->assertInstanceOf(InstanaSpanContext::class, $extractedContext);

        $this->assertEquals($spanContext->getSpanId(), $extractedContext->getSpanId());
    }

    public function provideTracer()
    {
        return [
            [InstanaTracer::phpSensor()],
            [InstanaTracer::restSdk()]
        ];
    }
}
