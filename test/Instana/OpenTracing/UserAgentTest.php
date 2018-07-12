<?php

namespace Instana\OpenTracing;

use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    /**
     * @test
     */
    public function getReturnsUserAgent()
    {
        $this->assertEquals('Instana PHP OpenTracing/1.0.0', UserAgent::get());
    }
}
