<?php

namespace SimplyTestable\WebClientBundle\Tests\Services\TestOptions\Adapter\Request\HasTestTypeTest;

use SimplyTestable\WebClientBundle\Tests\Services\TestOptions\Adapter\Request\ServiceTest;

abstract class HasTestTypeTest extends ServiceTest {

    abstract protected function getRequestHasHtmlValidation();
    abstract protected function getRequestHasCssValidation();
    abstract protected function getRequestHasJsStaticAnalysis();
    abstract protected function getRequestHasLinkIntegrity();

    /**
     * @var \SimplyTestable\WebClientBundle\Model\TestOptions
     */
    private $testOptions;

    public function setUp() {
        parent::setUp();

        $this->getRequestData()->set('html-validation', $this->getRequestHasHtmlValidation() ? '1' : '0');
        $this->getRequestData()->set('css-validation', $this->getRequestHasCssValidation() ? '1' : '0');
        $this->getRequestData()->set('js-static-analysis', $this->getRequestHasJsStaticAnalysis() ? '1' : '0');
        $this->getRequestData()->set('link-integrity', $this->getRequestHasLinkIntegrity() ? '1' : '0');

        $testOptionsParameters = $this->container->getParameter('test_options');

        $this->getAvailableTaskTypeService()->setIsAuthenticated(true);

        $this->getRequestAdapter()->setNamesAndDefaultValues($testOptionsParameters['names_and_default_values']);
        $this->getRequestAdapter()->setAvailableTaskTypes($this->getAvailableTaskTypes());
        $this->getRequestAdapter()->setRequestData($this->getRequestData());

        $this->testOptions = $this->getRequestAdapter()->getTestOptions();
    }

    public function testHasSelectedTestTypes() {
        if ($this->getRequestHasHtmlValidation()) {
            $this->assertTrue($this->testOptions->hasTestType('HTML validation'));
        }

        if ($this->getRequestHasCssValidation()) {
            $this->assertTrue($this->testOptions->hasTestType('CSS validation'));
        }

        if ($this->getRequestHasJsStaticAnalysis()) {
            $this->assertTrue($this->testOptions->hasTestType('JS static analysis'));
        }

        if ($this->getRequestHasLinkIntegrity()) {
            $this->assertTrue($this->testOptions->hasTestType('Link integrity'));
        }
    }

    public function testHasNotUnselectedTestTypes() {
        if (!$this->getRequestHasHtmlValidation()) {
            $this->assertFalse($this->testOptions->hasTestType('HTML validation'));
        }

        if (!$this->getRequestHasCssValidation()) {
            $this->assertFalse($this->testOptions->hasTestType('CSS validation'));
        }

        if (!$this->getRequestHasJsStaticAnalysis()) {
            $this->assertFalse($this->testOptions->hasTestType('JS static analysis'));
        }

        if (!$this->getRequestHasLinkIntegrity()) {
            $this->assertFalse($this->testOptions->hasTestType('Link integrity'));
        }
    }
}
