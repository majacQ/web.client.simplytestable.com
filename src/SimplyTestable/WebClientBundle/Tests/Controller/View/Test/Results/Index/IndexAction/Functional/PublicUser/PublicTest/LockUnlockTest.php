<?php

namespace SimplyTestable\WebClientBundle\Tests\Controller\View\Test\Results\Index\IndexAction\Functional\PublicUser\PublicTest;

use SimplyTestable\WebClientBundle\Tests\Controller\View\Test\Results\Index\IndexAction\Functional\PublicUser\FunctionalTest as BaseFunctionalTest;

class LockUnlockTest extends BaseFunctionalTest {

    public function testLockUnlockFeatureIsNotPresent() {
        $this->assertLockUnlockFeatureIsNotPresent();
    }
}