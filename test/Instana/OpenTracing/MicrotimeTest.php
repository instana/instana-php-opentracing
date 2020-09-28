<?php

namespace Instana\OpenTracing;

use PHPUnit\Framework\TestCase;

class MicrotimeTest extends TestCase
{
    const DELTA = 1000*1000;

    private function microtime()
    {
        return (int) (microtime(true) * 1000 * 1000);
    }

    /**
     * Testing microtime is finnicky, so we only test the seconds portion
     */
    private function assertTimestampEquals($expected, $actual)
    {
        $this->assertEquals($expected, $actual, '', static::DELTA);
    }

    /**
     * @test
     * @dataProvider provideSupportedTypes
     */
    public function createReturnsMicrotimeForSupportedTypes($expected, $supportedType)
    {
        $this->assertTimestampEquals($expected, Microtime::create($supportedType)->getMicrotime());
    }

    /**
     * @return array
     */
    public function provideSupportedTypes()
    {
        $dt = \DateTimeImmutable::createFromFormat('U', 1234567890);
        return [
            'null' => [$this->microtime(), null],
            'float' => ['1234567890123400', 1234567890.1234],
            'int' => ['1234567890000000', 1234567890],
            'datetime' => ['1234567890000000', $dt]
        ];
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @dataProvider provideUnsupportedTypes
     */
    public function createThrowsOnUnsupportedTypes($unsupportedType)
    {
        Microtime::create($unsupportedType);
    }

    /**
     * @return array
     */
    public function provideUnsupportedTypes()
    {
        return [
            'resources' => [STDIN],
            'objects' => [new \StdClass]
        ];
    }

    /**
     * @test
     */
    public function durationFromReturnsMicrotime()
    {
        $start = Microtime::create(1234567890);
        $end = Microtime::create(1234567899);
        $this->assertEquals("9000000", $end->durationFrom($start));
    }

    /**
     * @test
     * @expectedException \RangeException
     */
    public function durationFromThrowsWhenResultIsLessThanZero()
    {
        $start = Microtime::create(1234567899);
        $end = Microtime::create(1234567890);
        $end->durationFrom($start);
    }

    /**
     * @test
     */
    public function getMillisecondsReturnsMilliseconds()
    {
        $microtime = Microtime::create();
        $this->assertTrue(strlen($microtime->getMilliseconds()) == strlen($microtime->getMicrotime()) - 3);
        $this->assertStringStartsWith($microtime->getMilliseconds(), $microtime->getMicrotime());
    }
}
