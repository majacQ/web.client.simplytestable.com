<?php

namespace SimplyTestable\WebClientBundle\Controller\View;

use SimplyTestable\WebClientBundle\Controller\BaseViewController;
use SimplyTestable\WebClientBundle\Interfaces\Controller\Cacheable;

abstract class CacheableViewController extends BaseViewController implements Cacheable {
    
    /**
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    private $request;
    
    
    /**
     * 
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function setRequest(\Symfony\Component\HttpFoundation\Request $request) {
        $this->request = $request;
    }   
    
    
    /**
     * 
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest() {
        return (is_null($this->request)) ? $this->get('request') : $this->request;
    }


    /**
     *
     * @param array $definition
     * @return array
     */
    protected function getDefaultedRequestValues($definition) {
        $values = array();

        foreach ($definition as $key => $default) {
            $values[$key] = ($this->getRequest()->query->has($key)) ? $this->getRequest()->query->get($key) : $default;
        }

        return $values;
    }

}