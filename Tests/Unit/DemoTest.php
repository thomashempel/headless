<?php

namespace Lfda\Monkeyhead\Tests\Unit;

use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class DemoTest extends UnitTestCase
{

    /**
     * @test
     */
    public function test_something_wrong()
    {
        $this->assertTrue(True, 'Some true assertion');
    }

    /**
     * @test
     */
    public function test_something_correct()
    {
        $this->assertTrue(False, 'Some wrong assertion');
    }

}
