<?php

namespace SimplyTestable\WebClientBundle\Tests\EventListener\RequestListener\OnKernelRequest\RequiresUser;

class RequestWithUserNotRedirectedTest extends RequiresUserTest {
    
    protected function buildEvent() {     
        $user = $this->makeUser();
        $serializedUser =$this->getUserSerializerService()->serializeToString($user);
        
        $event = parent::buildEvent();        
        
        $event->getRequest()->cookies->add(array(
            'simplytestable-user' =>  $serializedUser
        ));
        
        
        return $event;
    }

    protected function getControllerActionString() {
        return 'SimplyTestable\WebClientBundle\Controller\View\User\Account\IndexController::indexAction';
    }
    
    protected function getControllerRouteString() {
        return 'view_user_account_index_index';
    }
    
    public function testEventHasNoResponse() {
        $this->assertNull($this->getEvent()->getResponse());
    }

}