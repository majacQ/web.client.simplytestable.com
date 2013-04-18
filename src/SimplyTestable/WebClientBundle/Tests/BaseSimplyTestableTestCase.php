<?php

namespace SimplyTestable\WebClientBundle\Tests;

abstract class BaseSimplyTestableTestCase extends BaseTestCase {
    
    const APP_CONTROLLER_NAME = 'SimplyTestable\WebClientBundle\Controller\AppController';    
    const TEST_START_CONTROLLER_NAME = 'SimplyTestable\WebClientBundle\Controller\TestStartController';    
    const TASK_CONTROLLER_NAME = 'SimplyTestable\WebClientBundle\Controller\TaskController';    

    
    /**
     *
     * @param string $methodName
     * @param array $postData
     * @param array $queryData
     * @return \SimplyTestable\WebClientBundle\Controller\AppController
     */
    protected function getAppController($methodName, $postData = array(), $queryData = array()) {
        return $this->getController(self::APP_CONTROLLER_NAME, $methodName, $postData, $queryData);
    }
    
    
    /**
     *
     * @param string $methodName
     * @param array $postData
     * @param array $queryData
     * @return \SimplyTestable\WebClientBundle\Controller\TaskController
     */
    protected function getTaskController($methodName, $postData = array(), $queryData = array()) {
        return $this->getController(self::TASK_CONTROLLER_NAME, $methodName, $postData, $queryData);
    }    
    

    /**
     *
     * @param string $methodName
     * @param array $postData
     * @return \SimplyTestable\WebClientBundle\Controller\TestStartController
     */
    protected function getTestStartController($methodName, $postData = array()) {
        return $this->getController(self::TEST_START_CONTROLLER_NAME, $methodName, $postData);
    }    
   
    /**
     * 
     * @param string $controllerName
     * @param string $methodName
     * @return Symfony\Bundle\FrameworkBundle\Controller\Controller
     */
    private function getController($controllerName, $methodName, array $postData = array(), array $queryData = array()) {        
        return $this->createController($controllerName, $methodName, $postData, $queryData);
    }
    
    
    /**
     *
     * @return \SimplyTestable\WebClientBundle\Services\HttpClientService
     */
    protected function getHttpClientService() {
        return $this->container->get('simplytestable.services.httpclientservice');
    }  
    
    
    /**
     *
     * @return \SimplyTestable\WebClientBundle\Services\TestWebResourceService
     */    
    protected function getWebResourceService() {
        return $this->container->get('simplytestable.services.webresourceservice');
    }
    
    /**
     *
     * @return \SimplyTestable\WebClientBundle\Services\TestService
     */    
    protected function getTestService() {
        return $this->container->get('simplytestable.services.testservice');
    }    
    
    
    /**
     *
     * @return \SimplyTestable\WebClientBundle\Services\TaskService
     */    
    protected function getTaskService() {
        return $this->container->get('simplytestable.services.taskservice');
    }     
    
    
    protected function removeAllTests() {        
        $tests = $this->getTestService()->getEntityRepository()->findAll();
        
        foreach ($tests as $test) {
            $this->getTestService()->getEntityManager()->remove($test);
        }
        
        $this->getTestService()->getEntityManager()->flush();
    }    
    
    protected function removeAllTasks() {        
        $tasks = $this->getTaskService()->getEntityRepository()->findAll();        
        foreach ($tasks as $task) {
            $this->getTaskService()->getEntityManager()->remove($task);
        }
        
        $this->getTaskService()->getEntityManager()->flush();
    }     


}
