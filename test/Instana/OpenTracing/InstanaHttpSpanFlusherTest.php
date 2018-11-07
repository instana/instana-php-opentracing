<?php

namespace Instana\OpenTracing;

include_once 'HttpMockServer.php';

use PHPUnit\Framework\TestCase;

class InstanaHttpSpanFlusherTest extends TestCase
{
    /**
     * @var string
     */
    private static $router;

    /**
     * @var string
     */
    private static $outfile;

    /**
     * @var string
     */
    private static $serverAddress;

    /**
     * Creates a Mock HTTP Server with a router writing Header and php:/input to an outfile
     */
    public static function setupBeforeClass()
    {
        self::$outfile = tempnam(sys_get_temp_dir(), basename(__FILE__));
        self::$router = tempnam(sys_get_temp_dir(), basename(__FILE__));

        file_put_contents(self::$router, '<?php 
            $input = file_get_contents("php://input");
            $headers = getallheaders();
            $headers["data"] = "$input";
            file_put_contents("' . self::$outfile . '", "<?php return " . var_export($headers, true) . ";");
        ');

        $server = new HttpMockServer('127.0.0.1', 8080, self::$router);

        self::$serverAddress = $server->start();
    }

    /**
     * Removes the Http Mock Server's router file
     */
    public static function tearDownAfterClass() {
        unlink(self::$router);
    }

    /**
     * Sets a new InstanaTracer as the Global OT tracer
     */
    public function setup()
    {
        \OpenTracing\GlobalTracer::set(
            new InstanaTracer(
                new InstanaScopeManager,
                new InstanaHttpSpanFlusher("http://" . self::$serverAddress),
                new InstanaSpanFactory(InstanaRestSdkSpan::class)
            )
        );
    }

    /**
     * Removes the Http Mock Server's outfile
     */
    public function tearDown()
    {
        if (file_exists(self::$outfile)) {
            unlink(self::$outfile);
        }
    }

    /**
     * @return mixed
     */
    private function getRequestData()
    {
        return include self::$outfile;
    }

    /**
     * @test
     */
    public function creatingRootSpan()
    {
        $tracer = \OpenTracing\GlobalTracer::get();
        $span = $tracer->startSpan('my_first_span');
        $span->finish();
        $tracer->flush();

        $requestData = $this->getRequestData();
        $this->assertEquals('Instana PHP OpenTracing/1.0.3', $requestData['User-Agent']);
        $this->assertEquals('application/json', $requestData['Content-type']);
        $this->assertEquals('close', $requestData['Connection']);

        $spanData = json_decode($requestData['data'], true);
        $this->assertCount(1, $spanData);
        $this->assertTrue(IdGenerator::isValidTraceId($spanData[0]['traceId']), "TraceId is in invalid format");
        $this->assertTrue(IdGenerator::isValidSpanId($spanData[0]['spanId']), "SpanId is in invalid format");
        $this->assertEquals(Microtime::create()->getMicrotime() / 1000, $spanData[0]['timestamp'], null, 2000);

        $this->assertEquals([
            'spanId' => $spanData[0]['spanId'],
            'traceId' => $spanData[0]['traceId'],
            'name' => 'my_first_span',
            'timestamp' => $spanData[0]['timestamp'],
            'duration' => $spanData[0]['duration'],
            'type' => 'ENTRY',
            'data' => []
        ], $spanData[0]);

    }

    /**
     * @test
     */
    public function creatingSpanGivenExistingRequest()
    {
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

        $requestData = $this->getRequestData();
        $spanData = json_decode($requestData['data'], true);
        $this->assertCount(1, $spanData);
        $this->assertTrue(IdGenerator::isValidSpanId($spanData[0]['spanId']), "SpanId is in invalid format");
        $this->assertEquals(Microtime::create()->getMicrotime() / 1000, $spanData[0]['timestamp'], null, 2000);

        $tags = $spanData[0]['data'];
        foreach ($tags as $tag => $val) {
            $this->assertTrue((bool)preg_match('#^log\.\d+\.(event|type|waiter\.millis)$#', $tag), "$tag doesn't match");
        }

        $this->assertEquals([
            'spanId' => $spanData[0]['spanId'],
            'traceId' => 'f2999d3780d3bedb',
            'parentId' => '0a13e31f73fe93dc',
            'name' => 'my_span',
            'timestamp' => $spanData[0]['timestamp'],
            'duration' => $spanData[0]['duration'],
            'type' => 'LOCAL',
            'data' => $spanData[0]['data']
        ], $spanData[0]);
    }

    /**
     * @test
     */
    public function startingActiveSpans()
    {
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

        $requestData = $this->getRequestData();
        $spanData = json_decode($requestData['data'], true);

        $this->assertEquals([[
            'spanId' => $spanData[0]['spanId'],
            'traceId' => $spanData[0]['traceId'],
            'name' => 'parent',
            'timestamp' => $spanData[0]['timestamp'],
            'duration' => $spanData[0]['duration'],
            'type' => 'ENTRY',
            'data' => ['foo' => 1]
        ], [
            'spanId' => $spanData[1]['spanId'],
            'traceId' => $spanData[0]['traceId'],
            'parentId' => $spanData[0]['spanId'],
            'name' => 'my_second_span',
            'timestamp' => $spanData[1]['timestamp'],
            'duration' => $spanData[1]['duration'],
            'type' => 'LOCAL',
            'data' => ['bar' => 2]
        ],[
            'spanId' => $spanData[2]['spanId'],
            'traceId' => $spanData[0]['traceId'],
            'parentId' => $spanData[0]['spanId'],
            'name' => 'my_third_span',
            'timestamp' => $spanData[2]['timestamp'],
            'duration' => $spanData[2]['duration'],
            'type' => 'EXIT',
            'data' => []
        ]], $spanData);
    }
}