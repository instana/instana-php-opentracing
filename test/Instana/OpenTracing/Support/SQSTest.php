<?php

namespace Instana\OpenTracing\Support;

use Aws\Result;
use Aws\Sqs\SqsClient;
use Instana\OpenTracing\InstanaScopeManager;
use Instana\OpenTracing\InstanaSpanContext;
use Instana\OpenTracing\InstanaSpanFactory;
use Instana\OpenTracing\InstanaTracer;
use Instana\OpenTracing\NoopSpanFlusher;
use Instana\OpenTracing\InstanaRestSdkSpan;
use OpenTracing\GlobalTracer;
use OpenTracing\Tags;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @test
 */
class SQSTest extends TestCase
{
    /**
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

    /**
     * @return void
     * @throws Exception
     */
    public function testSendMessage() {
        $queueUrl = 'https://my.aws.sqs/queue/id';
        $message = self::message($queueUrl);

        $noopFlusher = new NoopSpanFlusher();
        $instanaTracer = new InstanaTracer(
            new InstanaScopeManager,
            $noopFlusher,
            new InstanaSpanFactory(InstanaRestSdkSpan::class)
        );
        GlobalTracer::set($instanaTracer);

        /** @var SqsClient|MockObject */
        $mockClient = $this->createMock(SqsClient::class);
        $mockClient
            ->expects($this->once())
            ->method('__call')
            ->with(
                $this->equalTo('sendMessage'),
                $this->anything()
            )
            ->willReturn($this->createMock(Result::class))
        ;

        SQS::sendMessage($mockClient, $message);

        GlobalTracer::get()->flush();

        $flushedSpans = $noopFlusher->getSpans();
        $this->assertCount(1, $flushedSpans);

        /** @var InstanaRestSdkSpan */
        $span = $flushedSpans[0];
        $this->assertInstanceOf(InstanaRestSdkSpan::class, $span);

        $values = $span->jsonSerialize();

        $this->assertEquals('sqs', $span->getOperationName());
        $this->assertEquals('EXIT', $values['type']);
        $this->assertArrayHasKey(Tags\MESSAGE_BUS_DESTINATION, $values['data']);
        $this->assertEquals($queueUrl, $values['data'][Tags\MESSAGE_BUS_DESTINATION]);
    }

    public function provideTracer()
    {
        return [
            [InstanaTracer::phpSensor()],
            [InstanaTracer::restSdk()]
        ];
    }

    /**
     * Creates a mock message.
     *
     * @param string $queueUrl
     * @return (int|string[][]|string)[]
     */
    private static function message(string $queueUrl)
    {
        return [
            'DelaySeconds' => 10,
            'MessageAttributes' => [
                "Title" => [
                    'DataType' => "String",
                    'StringValue' => "The Hitchhiker's Guide to the Galaxy"
                ],
                "Author" => [
                    'DataType' => "String",
                    'StringValue' => "Douglas Adams."
                ],
                "WeeksOn" => [
                    'DataType' => "Number",
                    'StringValue' => "6"
                ]
            ],
            'MessageBody' => "Information about current NY Times fiction bestseller for week of 12/11/2016.",
            'QueueUrl' => $queueUrl
        ];
    }
}
