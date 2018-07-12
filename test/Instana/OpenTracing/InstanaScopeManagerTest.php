<?php

namespace Instana\OpenTracing;

use PHPUnit\Framework\TestCase;

class InstanaScopeManagerTest extends TestCase
{
    /**
     * @test
     */
    public function emptyStackReturnsNullScope()
    {
        $manager = new InstanaScopeManager();
        $this->assertNull($manager->getActive());
    }

    /**
     * @test
     */
    public function returnsLastActivatedScopeAsActive()
    {
        $span1 = \Phake::mock(InstanaSpan::class);
        $span2 = \Phake::mock(InstanaSpan::class);

        $manager = new InstanaScopeManager();
        $scope1 = $manager->activate($span1, true);

        $this->assertInstanceOf(InstanaScope::class, $scope1);
        $this->assertSame($span1, $scope1->getSpan());
        $this->assertSame($scope1, $manager->getActive());

        $scope2 = $manager->activate($span2, true);

        $this->assertInstanceOf(InstanaScope::class, $scope2);
        $this->assertSame($span2, $scope2->getSpan());
        $this->assertSame($scope2, $manager->getActive());

        $scope2->close();

        $this->assertSame($scope1, $manager->getActive());

        $scope1->close();

        $this->assertNull($manager->getActive());
    }
}
