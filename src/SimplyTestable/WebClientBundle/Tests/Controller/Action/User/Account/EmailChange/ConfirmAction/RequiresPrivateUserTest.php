<?php

namespace SimplyTestable\WebClientBundle\Tests\Controller\Action\User\Account\EmailChange\ConfirmAction;

use SimplyTestable\WebClientBundle\Tests\Controller\Base\RequiresPrivateUserTest as BaseRequiresPrivateUserTest;

class RequiresPrivateUserTest extends BaseRequiresPrivateUserTest {
    
    public function setUp() {
        parent::setUp();
        $this->setHttpFixtures($this->buildHttpFixtureSet(array(
            "HTTP/1.1 200 OK",
        )));
    }
    
}