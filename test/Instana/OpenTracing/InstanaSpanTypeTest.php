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
        $exit = InstanaSpanType::entryType();
        $this->assertSame($exit, InstanaSpanType::entryType());
        $this->assertEquals(1, $exit->getKind());
        $this->assertEquals('entry', $exit->getType());
    }

    /**
     * @test
     */
    public function verifyExitReturnsSameInstanceAndHasCorrectTypeAndKind()
    {
        $exit = InstanaSpanType::exitType();
        $this->assertSame($exit, InstanaSpanType::exitType());
        $this->assertEquals(2, $exit->getKind());
        $this->assertEquals('exit', $exit->getType());
    }

    /**
     * @test
     */
    public function verifyLocalReturnsSameInstanceAndHasCorrectTypeAndKind()
    {
        $exit = InstanaSpanType::localType();
        $this->assertSame($exit, InstanaSpanType::localType());
        $this->assertEquals(3, $exit->getKind());
        $this->assertEquals('local', $exit->getType());
    }
}
