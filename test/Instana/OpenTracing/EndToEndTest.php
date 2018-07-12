<?php

namespace Instana\OpenTracing;

use PHPUnit\Framework\TestCase;

/**
 * These requires an Instana agent to run on the same machine and access to the Instana UI
 *
 * This test is meant to be executed against a running Instana agent.
 * The created traces need to be checked manually in the Instana UI.
 *
 * @test
 * @group end2end
 */
class EndToEndTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideTracer
     */
    public function e2eInstanaSdk(InstanaTracer $instanaTracer)
    {
        \OpenTracing\GlobalTracer::set($instanaTracer);

        $parentScope = \OpenTracing\GlobalTracer::get()->startActiveSpan('one');
        $parentSpan = $parentScope->getSpan();
        $parentSpan->setTag(\Instana\OpenTracing\InstanaTags\SERVICE, "example service");
        $parentSpan->setTag(\OpenTracing\Tags\COMPONENT, 'PHP simple example app');
        $parentSpan->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_RPC_SERVER);
        $parentSpan->setTag(\OpenTracing\Tags\PEER_HOSTNAME, 'localhost');
        $parentSpan->setTag(\OpenTracing\Tags\HTTP_URL, '/php/simple/one');
        $parentSpan->setTag(\OpenTracing\Tags\HTTP_METHOD, 'GET');
        $parentSpan->setTag(\OpenTracing\Tags\HTTP_STATUS_CODE, 200);
        $parentSpan->log(['event' => 'bootstrap', 'type' => 'kernel.load', 'waiter.millis' => 1500]);

        $childScope = \OpenTracing\GlobalTracer::get()->startActiveSpan('two');
        $childSpan = $childScope->getSpan();
        $childSpan->setTag(\OpenTracing\Tags\SPAN_KIND, \OpenTracing\Tags\SPAN_KIND_RPC_CLIENT);
        $childSpan->setTag(\OpenTracing\Tags\PEER_HOSTNAME, 'localhost');
        $childSpan->setTag(\OpenTracing\Tags\HTTP_URL, '/php/simple/two');
        $childSpan->setTag(\OpenTracing\Tags\HTTP_METHOD, 'POST');
        $childSpan->setTag(\OpenTracing\Tags\HTTP_STATUS_CODE, 204);

        $childScope->close();
        $parentScope->close();

        \OpenTracing\GlobalTracer::get()->flush();
    }

    public function provideTracer()
    {
        return [
            [InstanaTracer::phpSensor()],
            [InstanaTracer::restSdk()]
        ];
    }
}