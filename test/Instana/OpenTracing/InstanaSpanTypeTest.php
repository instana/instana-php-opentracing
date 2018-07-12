<?php

namespace Instana\OpenTracing;

use PHPUnit\Framework\TestCase;

class InstanaSpanTypeTest extends TestCase
{
    /**
     * @test
     */
    public function verifyEntryReturnsSameInstanceAndHasCorrectTypeAndKind()
    {
        $exit = InstanaSpanType::entry();
        $this->assertSame($exit, InstanaSpanType::entry());
        $this->assertEquals(1, $exit->getKind());
        $this->assertEquals('entry', $exit->getType());
    }

    /**
     * @test
     */
    public function verifyExitReturnsSameInstanceAndHasCorrectTypeAndKind()
    {
        $exit = InstanaSpanType::exit();
        $this->assertSame($exit, InstanaSpanType::exit());
        $this->assertEquals(2, $exit->getKind());
        $this->assertEquals('exit', $exit->getType());
    }

    /**
     * @test
     */
    public function verifyLocalReturnsSameInstanceAndHasCorrectTypeAndKind()
    {
        $exit = InstanaSpanType::local();
        $this->assertSame($exit, InstanaSpanType::local());
        $this->assertEquals(3, $exit->getKind());
        $this->assertEquals('local', $exit->getType());
    }
}
