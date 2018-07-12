<?php

namespace Instana\OpenTracing;

use PHPUnit\Framework\TestCase;

class InstanaScopeTest extends TestCase
{
    /**
     * @test
     */
    public function itFinishesOnCloseWhenRequested()
    {
        $span = \Phake::mock(InstanaSpan::class, \Phake::ifUnstubbed()->thenCallParent());
        $scope = new InstanaScope($span, true);

        $this->assertFalse($scope->isClosed());

        $scope->close();

        $this->assertTrue($span->isFinished() !== false);
        $this->assertTrue($scope->isClosed());
    }

    /**
     * @test
     */
    public function itDoesNotFinishOnClose()
    {
        $span = \Phake::mock(InstanaSpan::class, \Phake::ifUnstubbed()->thenCallParent());
        //$span = new InstanaSdkSpan("dummy");
        $scope = new InstanaScope($span, false);

        $this->assertFalse($scope->isClosed());

        $scope->close();

        $this->assertFalse($span->isFinished());
        $this->assertTrue($scope->isClosed());
    }
}
