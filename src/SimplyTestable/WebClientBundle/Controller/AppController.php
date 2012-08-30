<?php

namespace SimplyTestable\WebClientBundle\Controller;

use SimplyTestable\WebClientBundle\Entity\Test\Test;
use SimplyTestable\WebClientBundle\Entity\Task\Task;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class AppController extends BaseViewController
{
    private $progressStates = array(
        'new',
        'preparing',
        'queued',        
        'in-progress'        
    );
    
    private $completedStates = array(
        'cancelled',
        'completed'
    );
    
    private $testStateLabelMap = array(
        'new' => 'New',
        'queued' => 'Queued',
        'preparing' => 'Running',
        'in-progress' => 'Running'        
    );
    
    private $testStateIconMap = array(
        'new' => 'icon-off',
        'queued' => 'icon-off',
        'preparing' => 'icon-play-circle',
        'in-progress' => 'icon-play-circle'        
    );    
    
    public function indexAction()
    {
        $response =  $this->render('SimplyTestableWebClientBundle:App:index.html.twig', array(            
            'test_input_action_url' => $this->generateUrl('test_start'),
            'test_start_error' => $this->hasFlash('test_start_error')
        ));
        
        $response->setETag(md5($response->getContent()));        
        $response->setPublic();        
        $response->isNotModified($this->getRequest());      
        
        return $response;
    }
    
    
    public function progressAction($website, $test_id) {
        if (!$this->getTestService()->has($website, $test_id)) {
            return $this->redirect($this->generateUrl('app', array(), true));
        }

        $test = $this->getTestService()->get($website, $test_id);
        
        if (in_array($test->getState(), $this->completedStates)) {
            return $this->redirect($this->getResultsUrl($website, $test_id));
        }
        
        $viewData = array(
            'this_url' => $this->getProgressUrl($website, $test_id),
            'test_input_action_url' => $this->generateUrl('test_cancel', array(
                'website' => $website,
                'test_id' => $test_id
            )),
            'test' => $test,
            'urls' => $this->getTestUrls($test),
            'state_label' => $this->testStateLabelMap[$test->getState()].': ',
            'state_icon' => $this->testStateIconMap[$test->getState()],
            'taskCountByState' => $this->getTaskCountByState($test),
            'completionPercent' => $test->getCompletionPercent(),
            'testId' => $test_id
        );
        
        $this->setTemplate('SimplyTestableWebClientBundle:App:progress.html.twig');
        return $this->sendResponse($viewData);
    }
    
    
    /**
     *
     * @param Test $test
     * @return array 
     */
    private function getTaskCountByState(Test $test) {
        $taskStates = array(
            'in-progress',
            'queued',
            'completed',
            'cancelled'
        );
        
        $taskCountByState = array();        
        
        foreach ($taskStates as $taskState) {
            $taskCountByState[$taskState] = $test->getTaskCountByState($taskState);
        }
        
        return $taskCountByState;
    }
    
    
    /**
     *
     * @param Test $test
     * @return array 
     */
    private function getTestUrls(Test $test) {
        $urls = array();
        $urlListResponse = $this->getTestService()->getUrls($test);
        
        if (!$urlListResponse) {
            return $urls;
        }
        
        $urlListContentObject = $urlListResponse->getContentObject();
        foreach ($urlListContentObject as $urlObject) {
            $urls[] = $urlObject->url;
        }
        
        return $urls;           
    }
    
    
    public function resultsAction($website, $test_id) {                
        if (!$this->getTestService()->has($website, $test_id)) {
            return $this->redirect($this->generateUrl('app', array(), true));
        }
        
        $test = $this->getTestService()->get($website, $test_id);        
        
        foreach ($test->getTasks() as $task) {
            /* @var $task Task */
            if ($task->getState() == 'completed' && !$task->hasOutput()) {
                if ($this->getTaskOutputService()->has($test, $task)) {
                    $this->getTaskOutputService()->get($test, $task);
                } 
            }
        }       
        
        if (in_array($test->getState(), $this->progressStates)) {
            return $this->redirect($this->getResultsUrl($website, $test_id));
        }
        
        $viewData = array(
            'this_url' => $this->getResultsUrl($website, $test_id),
            'test_input_action_url' => $this->generateUrl('test_start'),
            'test' => $test,            
            'urls' => $this->getTestUrls($test),
            'testId' => $test_id,
            'taskCountByState' => $this->getTaskCountByState($test),
            'taskErrorCount' => $this->getTaskErrorCount($test),
            'erroredTaskCount' => $this->getErroredTaskCount($test)
        );
        
        $this->setTemplate('SimplyTestableWebClientBundle:App:results.html.twig');
        return $this->sendResponse($viewData);
    }
    
    
    private function getErroredTaskCount(Test $test) {
        $totalTaskErrorCount = 0;
        
        foreach ($test->getTasks() as $task) {
            /* @var $task Task */
            if ($task->getState() == 'completed' && $task->hasOutput()) {
                $this->getTaskOutputService()->setParsedOutput($task);
                if ($task->getOutput()->getResult()->hasErrors()) {
                    $totalTaskErrorCount += 1;
                }
            }
        }
        
        return $totalTaskErrorCount;        
    }
    
    
    /**
     *
     * @param Test $test
     * @return array
     */
    private function getTaskErrorCount(Test $test) {
        $taskErrorCounts = array();
        
        foreach ($test->getTasks() as $task) {
            /* @var $task Task */
            if ($task->getState() == 'completed' && $task->hasOutput()) {
                $this->getTaskOutputService()->setParsedOutput($task);
                $taskErrorCounts[$task->getId()] = $task->getOutput()->getResult()->getErrorCount();
            }
        }
        
        return $taskErrorCounts;
    }    
    
    
    /**
     * Get the progress page URL for a given site and test ID
     * 
     * @param string $website
     * @param string $test_id
     * @return string
     */
    private function getProgressUrl($website, $test_id) {
        return $this->generateUrl(
            'app_progress',
            array(
                'website' => $website,
                'test_id' => $test_id
            ),
            true
        );
    }
    
    
    /**
     * Get the results page URL for a given site and test ID
     * 
     * @param string $website
     * @param string $test_id
     * @return string
     */    
    private function getResultsUrl($website, $test_id) {
        return $this->generateUrl(
            'app_results',
            array(
                'website' => $website,
                'test_id' => $test_id
            ),
            true
        );
    }
    
    
    /**
     *
     * @param string $flashKey
     * @return boolean
     */
    private function hasFlash($flashKey) {
        $flashMessages = $this->get('session')->getFlashBag()->get($flashKey);        
        return is_array($flashMessages) && count($flashMessages) > 0;
    }
    
    
    /**
     *
     * @param type $flashKey
     * @param type $messageIndex
     * @return string|null 
     */
    private function getFlash($flashKey, $messageIndex = 0) {        
        $flashMessages = $this->get('session')->getFlashBag()->get($flashKey);         
        if (!isset($flashMessages[$messageIndex])) {
            return false;
        }
        
        return $flashMessages[$messageIndex];
    }
    
    
    /**
     *
     * @return \SimplyTestable\WebClientBundle\Services\TestService 
     */
    private function getTestService() {
        return $this->container->get('simplytestable.services.testservice');
    }  
    
    
    /**
     *
     * @return \SimplyTestable\WebClientBundle\Services\TaskService 
     */
    private function getTaskService() {
        return $this->container->get('simplytestable.services.taskservice');
    }      
    
    
    /**
     *
     * @return \SimplyTestable\WebClientBundle\Services\TaskOutputService 
     */
    private function getTaskOutputService() {
        return $this->container->get('simplytestable.services.taskoutputservice');
    }   
    
    
    /**
     *
     * @return \JMS\SerializerBundle\Serializer\Serializer
     */
    protected function getSerializer() {
        return $this->container->get('serializer');
    }    
}
