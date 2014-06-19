<?php

namespace SimplyTestable\WebClientBundle\Controller\View\Test\Progress;

use SimplyTestable\WebClientBundle\Controller\View\Test\CacheableViewController;
use SimplyTestable\WebClientBundle\Interfaces\Controller\IEFiltered;
use SimplyTestable\WebClientBundle\Interfaces\Controller\RequiresValidUser;
use SimplyTestable\WebClientBundle\Interfaces\Controller\Test\RequiresValidOwner;
use SimplyTestable\WebClientBundle\Entity\Test\Test;

class IndexController extends CacheableViewController implements IEFiltered, RequiresValidUser, RequiresValidOwner {

    const RESULTS_PREPARATION_THRESHOLD = 100;

    private $testStateLabelMap = array(
        'new' => 'New, waiting to start',
        'queued' => 'waiting for first test to begin',
        'resolving' => 'Resolving website',
        'resolved' => 'Resolving website',
        'preparing' => 'Finding URLs to test: looking for sitemap or news feed',
        'crawling' => 'Finding URLs to test',
        'failed-no-sitemap' => 'Finding URLs to test: preparing to crawl'
    );

    /**
     * @var \SimplyTestable\WebClientBundle\Services\TaskTypeService
     */
    private $taskTypeService = null;

    /**
     *
     * @var \SimplyTestable\WebClientBundle\Services\TestOptions\Adapter\Request\Adapter
     */
    private $testOptionsAdapter = null;

    protected function modifyViewName($viewName) {
        return str_replace(array(
            ':Test',
        ), array(
            ':bs3/Test',
        ), $viewName);
    }


    protected function getAllowedContentTypes() {
        return array_merge(['application/json'], parent::getAllowedContentTypes());
    }


    public function indexAction($website, $test_id) {
        if ($this->getTest()->getWebsite() != $website) {
            if  ($this->isXmlHttpRequest()) {
                return $this->renderResponse($this->getRequest(), [
                    'this_url' => $this->getProgressUrl($this->getTest()->getWebsite(), $test_id)
                ]);
            } else {
                return $this->redirect($this->generateUrl('view_test_progress_index_index', array(
                    'website' => $this->getTest()->getWebsite(),
                    'test_id' => $test_id
                ), true));
            }
        }

        if ($this->getTestService()->isFinished($this->getTest())) {
            if ($this->getTest()->getState() !== 'failed-no-sitemap') {
                return $this->redirect($this->generateUrl('view_test_results_index_index', array(
                    'website' => $this->getTest()->getWebsite(),
                    'test_id' => $test_id
                ), true));
            }

            if ($this->getUserService()->isPublicUser($this->getUser())) {
                return $this->redirect($this->generateUrl('view_test_results_index_index', array(
                    'website' => $this->getTest()->getWebsite(),
                    'test_id' => $test_id
                ), true));
            }

            return $this->forward('SimplyTestableWebClientBundle:Test:retest', array(
                'website' => $website,
                'test_id' => $test_id
            ));
        }

        $this->getTestOptionsAdapter()->setRequestData($this->getRemoteTest()->getOptions());

        return $this->renderCacheableResponse([
            'website' => $this->getUrlViewValues($website),
            'test' => $this->getTest(),
            'this_url' => $this->getProgressUrl($this->getTest()->getWebsite(), $test_id),
            'remote_test' => $this->requestIsForApplicationJson($this->getRequest()) ? $this->getRemoteTest()->__toArray() : $this->getRemoteTest(),
            'state_label' => $this->getStateLabel(),
            'available_task_types' => $this->getTaskTypeService()->getAvailable(),
            'task_types' => $this->getTaskTypeService()->get(),
            'test_options' => $this->getTestOptionsAdapter()->getTestOptions()->__toKeyArray(),
            'css_validation_ignore_common_cdns' => $this->getCssValidationCommonCdnsToIgnore(),
            'js_static_analysis_ignore_common_cdns' => $this->getJsStaticAnalysisCommonCdnsToIgnore(),
            'is_public_user_test' => $this->getTest()->getUser() == $this->getUserService()->getPublicUser()->getUsername(),
            'default_css_validation_options' => array(
                'ignore-warnings' => 1,
                'vendor-extensions' => 'warn',
                'ignore-common-cdns' => 1
            ),
            'default_js_static_analysis_options' => array(
                'ignore-common-cdns' => 1,
                'jslint-option-maxerr' => 50,
                'jslint-option-indent' => 4,
                'jslint-option-maxlen' => 256
            ),
        ]);
    }

    /**
     * @return bool
     */
    private function isXmlHttpRequest() {
        return $this->getRequest()->headers->has('X-Requested-With') && $this->getRequest()->headers->get('X-Requested-With') == 'XMLHttpRequest';
    }


    public function getCacheValidatorParameters() {
        $test = $this->getTest();

        return array(
            'website' => $this->getRequest()->attributes->get('website'),
            'test_id' => $this->getRequest()->attributes->get('test_id'),
            'is_public' => $this->getTestService()->getRemoteTestService()->isPublic(),
            'is_public_user_test' => $test->getUser() == $this->getUserService()->getPublicUser()->getUsername(),
            'timestamp' => ($this->getRequest()->query->has('timestamp')) ? $this->getRequest()->query->get('timestamp') : '',
            'state' => $test->getState()
        );
    }


    private function getStateLabel() {
        $label = (isset($this->testStateLabelMap[$this->getTest()->getState()])) ? $this->testStateLabelMap[$this->getTest()->getState()] : '';

        if ($this->getTest()->getState() == 'in-progress') {
            $label = $this->getRemoteTest()->getCompletionPercent().'% done';
        }

        if (in_array($this->getTest()->getState(), ['queued', 'in-progress'])) {
            $label = $this->getRemoteTest()->getUrlCount() . ' urls, ' . $this->getRemoteTest()->getTaskCount() . ' tests; ' . $label;
        }

        if ($this->getTest()->getState() == 'crawling') {
            $label .= ': '. $this->getRemoteTest()->getCrawl()->processed_url_count .' pages examined, ' . $this->getRemoteTest()->getCrawl()->discovered_url_count.' of '. $this->getRemoteTest()->getCrawl()->limit .' found';        }

        return $label;
    }


    /**
     *
     * @return \SimplyTestable\WebClientBundle\Services\TestOptions\Adapter\Request\Adapter
     */
    private function getTestOptionsAdapter() {
        if (is_null($this->testOptionsAdapter)) {
            $testOptionsParameters = $this->container->getParameter('test_options');

            $this->testOptionsAdapter = $this->container->get('simplytestable.services.testoptions.adapter.request');

            $this->testOptionsAdapter->setNamesAndDefaultValues($testOptionsParameters['names_and_default_values']);
            $this->testOptionsAdapter->setAvailableTaskTypes($this->getTaskTypeService()->getAvailable());
            $this->testOptionsAdapter->setInvertOptionKeys($testOptionsParameters['invert_option_keys']);
            $this->testOptionsAdapter->setInvertInvertableOptions(true);

            if (isset($testOptionsParameters['features'])) {
                $this->testOptionsAdapter->setAvailableFeatures($testOptionsParameters['features']);
            }
        }

        return $this->testOptionsAdapter;
    }


    /**
     *
     * @return array
     */
    private function getCssValidationCommonCdnsToIgnore() {
        if (!$this->container->hasParameter('css-validation-ignore-common-cdns')) {
            return array();
        }

        return $this->container->getParameter('css-validation-ignore-common-cdns');
    }


    /**
     *
     * @return array
     */
    private function getJsStaticAnalysisCommonCdnsToIgnore() {
        if (!$this->container->hasParameter('js-static-analysis-ignore-common-cdns')) {
            return array();
        }

        return $this->container->getParameter('js-static-analysis-ignore-common-cdns');
    }


    /**
     * @return \SimplyTestable\WebClientBundle\Services\TaskTypeService
     */
    private function getTaskTypeService() {
        if (is_null($this->taskTypeService)) {
            $this->taskTypeService = $this->container->get('simplytestable.services.tasktypeservice');
            $this->taskTypeService->setUser($this->getUser());

            if (!$this->getUser()->equals($this->getUserService()->getPublicUser())) {
                $this->taskTypeService->setUserIsAuthenticated();
            }
        }

        return $this->taskTypeService;
    }
}