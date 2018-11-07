<?php

namespace Instana\OpenTracing;

use PHPUnit\Framework\TestCase;

class InstanaTcpSpanFlusherTest extends TestCase
{
    /**
     * @var string
     */
    private static $socket;

    /**
     * Creates a Mock HTTP Server with a router writing Header and php:/input to an outfile
     */
    public static function setupBeforeClass()
    {
        self::$socket = stream_socket_server('tcp://127.0.0.1:16816');
    }

    /**
     * Removes the Http Mock Server's router file
     */
    public static function tearDownAfterClass() {
        stream_socket_shutdown(self::$socket, \STREAM_SHUT_RDWR);
    }

    /**
     * Sets a new InstanaTracer as the Global OT tracer
     */
    public function setup()
    {
        \OpenTracing\GlobalTracer::set(InstanaTracer::phpSensor());
    }

    private function getRequestData($callback)
    {
        $callback();
        $conn = stream_socket_accept(self::$socket, 1);
        $requestData = stream_get_contents($conn);
        fclose($conn);

        return $requestData;
    }

    /**
     * @test
     */
    public function creatingRootSpan()
    {
        $requestData = $this->getRequestData(function() {
            $tracer = \OpenTracing\GlobalTracer::get();
            $span = $tracer->startSpan('my_first_span');
            $span->finish();
            $tracer->flush();
        });

        $spanData = json_decode($requestData, true);
        $this->assertCount(1, $spanData);
        $this->assertTrue(IdGenerator::isValidTraceId($spanData[0]['t']), "TraceId is in invalid format");
        $this->assertTrue(IdGenerator::isValidSpanId($spanData[0]['s']), "SpanId is in invalid format");
        $this->assertEquals(Microtime::create()->getMicrotime() / 1000, $spanData[0]['ts'], null, 2000);

        $this->assertEquals([
            's' => $spanData[0]['s'],
            't' => $spanData[0]['t'],
            'ta' => 'php',
            'n' => 'sdk',
            'ts' => $spanData[0]['ts'],
            'd' => $spanData[0]['d'],
            'k' => 1,
            'ppid' => $spanData[0]['ppid'],
            'data' => [
                'sdk' => [
                    'name' => 'my_first_span',
                    'type' => 'entry',
                    'custom' => [
                        'tags' => []
                    ]
                ]
            ]
        ], $spanData[0]);

    }

    /**
     * @test
     */
    public function creatingSpanGivenExistingRequest()
    {
        $requestData = $this->getRequestData(function() {
            $tracer = \OpenTracing\GlobalTracer::get();
            $spanContext = $tracer->extract(
                \OpenTracing\Formats\HTTP_HEADERS,
                [
                    'X-INSTANA-T' => 'f2999d3780d3bedb',
                    'X-INSTANA-S' => '0a13e31f73fe93dc'
                ]
            );

            $span = $tracer->startSpan('my_span', ['child_of' => $spanContext]);
            $span->log(['event' => 'soft error', 'type' => 'cache timeout', 'waiter.millis' => 1500]);
            $span->finish();
            $tracer->flush();

        });

        $spanData = json_decode($requestData, true);
        $this->assertCount(1, $spanData);
        $this->assertTrue(IdGenerator::isValidSpanId($spanData[0]['s']), "SpanId is in invalid format");
        $this->assertEquals(Microtime::create()->getMicrotime() / 1000, $spanData[0]['ts'], null, 2000);

        $tags = $spanData[0]['data']['sdk']['custom']['tags'];
        foreach ($tags as $tag => $val) {
            $this->assertTrue((bool)preg_match('#^log\.\d+\.(event|type|waiter\.millis)$#', $tag), "$tag doesn't match");
        }

        $this->assertEquals([
            's' => $spanData[0]['s'],
            't' => 'f2999d3780d3bedb',
            'p' => '0a13e31f73fe93dc',
            'ta' => 'php',
            'n' => 'sdk',
            'ts' => $spanData[0]['ts'],
            'd' => $spanData[0]['d'],
            'k' => 3,
            'data' => [
                'sdk' => [
                    'name' => 'my_span',
                    'type' => 'local',
                    'custom' => [
                        'tags' => $spanData[0]['data']['sdk']['custom']['tags']
                    ]
                ]
            ]
        ], $spanData[0]);
    }

    /**
     * @test
     */
    public function startingActiveSpans()
    {
        $requestData = $this->getRequestData(function() {
            $parent = \OpenTracing\GlobalTracer::get()->startActiveSpan('parent');
            $parent->getSpan()->setTag('foo', 1);

            $child = \OpenTracing\GlobalTracer::get()->startActiveSpan('my_second_span');
            $child->getSpan()->setTag('bar', 2);
            $child->close();

            $child = \OpenTracing\GlobalTracer::get()->startActiveSpan('my_third_span');
            $child->getSpan()->setTag('span.kind', 'client');
            $child->close();

            $parent->close();

            \OpenTracing\GlobalTracer::get()->flush();
        });

        $spanData = json_decode($requestData, true);

        $this->assertEquals([[
            's' => $spanData[0]['s'],
            't' => $spanData[0]['t'],
            'ta' => 'php',
            'n' => 'sdk',
            'ts' => $spanData[0]['ts'],
            'd' => $spanData[0]['d'],
            'k' => 1,
            'ppid' => $spanData[0]['ppid'],
            'data' => [
                'sdk' => [
                    'name' => 'parent',
                    'type' => 'entry',
                    'custom' => [
                        'tags' => [
                            'foo' => 1
                        ]
                    ]
                ]
            ]
        ], [
            's' => $spanData[1]['s'],
            't' => $spanData[0]['t'],
            'p' => $spanData[0]['s'],
            'ta' => 'php',
            'n' => 'sdk',
            'ts' => $spanData[1]['ts'],
            'd' => $spanData[1]['d'],
            'k' => 3,
            'data' => [
                'sdk' => [
                    'name' => 'my_second_span',
                    'type' => 'local',
                    'custom' => [
                        'tags' => [
                            'bar' => 2
                        ]
                    ]
                ]
            ]
        ], [
            's' => $spanData[2]['s'],
            't' => $spanData[0]['t'],
            'p' => $spanData[0]['s'],
            'ta' => 'php',
            'n' => 'sdk',
            'ts' => $spanData[2]['ts'],
            'd' => $spanData[2]['d'],
            'k' => 2,
            'data' => [
                'sdk' => [
                    'name' => 'my_third_span',
                    'type' => 'exit',
                    'custom' => [
                        'tags' => []
                    ]
                ]
            ]
        ]], $spanData);
    }
}