<?php

namespace SimplyTestable\WebClientBundle\Controller;

use SimplyTestable\WebClientBundle\Entity\Test\Test;
use SimplyTestable\WebClientBundle\Exception\WebResourceException;
use SimplyTestable\WebClientBundle\Model\RemoteTest\RemoteTest;
use SimplyTestable\WebClientBundle\Repository\TestRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use webignition\NormalisedUrl\NormalisedUrl;

/**
 * Redirects valid-looking URLs to those that match actual controller actions
 */
class RedirectController extends BaseController
{
    const TASK_RESULTS_URL_PATTERN = '/\/[0-9]+\/[0-9]+\/results\/?$/';

    /**
     * @var string[]
     */
    private $testFinishedStates = [
        Test::STATE_CANCELLED,
        Test::STATE_COMPLETED,
        Test::STATE_FAILED_NO_SITEMAP,
    ];

    /**
     * @param Request $request
     * @param string $website
     * @param int $test_id
     *
     * @return RedirectResponse
     */
    public function testAction(Request $request, $website, $test_id = null)
    {
        $testService = $this->container->get('simplytestable.services.testservice');
        $remoteTestService = $this->container->get('simplytestable.services.remotetestservice');
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $logger = $this->container->get('logger');

        /* @var TestRepository $testRepository */
        $testRepository = $entityManager->getRepository(Test::class);

        $remoteTestService->setUser($this->getUser());

        $isTaskResultsUrl = preg_match(self::TASK_RESULTS_URL_PATTERN, $website) > 0;

        if ($isTaskResultsUrl) {
            $routeParameters = $this->getWebsiteAndTestIdAndTaskIdFromWebsite($website);

            return $this->redirect($this->generateUrl(
                'view_test_task_results_index_index_verbose',
                $routeParameters,
                UrlGeneratorInterface::ABSOLUTE_URL
            ));
        }

        list ($normalisedWebsite, $normalisedTestId) = $this->createNormalisedWebsiteAndTestId(
            $request,
            $website,
            $test_id
        );

        $hasWebsite = !is_null($normalisedWebsite);
        $hasTestId = !is_null($normalisedTestId);

        if ($hasWebsite && !$hasTestId) {
            $latestRemoteTest = $remoteTestService->retrieveLatest($normalisedWebsite);

            if ($latestRemoteTest instanceof RemoteTest) {
                return $this->redirect($this->generateUrl(
                    'app_test_redirector',
                    [
                        'website' => $latestRemoteTest->getWebsite(),
                        'test_id' => $latestRemoteTest->getId()
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ));
            }

            if ($testRepository->hasForWebsite($normalisedWebsite)) {
                $testId = $testRepository->getLatestId($normalisedWebsite);

                $redirectUrl = $this->generateUrl(
                    'app_test_redirector',
                    [
                        'website' => $normalisedWebsite,
                        'test_id' => $testId
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                return $this->redirect($redirectUrl);
            }

            return $this->redirect($this->generateUrl(
                'view_dashboard_index_index',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            ));
        }

        if ($hasWebsite && $hasTestId) {
            $test = null;

            try {
                $test = $testService->get($normalisedWebsite, $normalisedTestId);
            } catch (WebResourceException $webResourceException) {
                $logger->error(sprintf(
                    'RedirectController::webResourceException %s',
                    $webResourceException->getResponse()->getStatusCode()
                ));
                $logger->error('[request]');
                $logger->error($webResourceException->getRequest());
                $logger->error('[response]');
                $logger->error($webResourceException->getResponse());

                $redirectUrl = $this->generateUrl(
                    'app_website',
                    [
                        'website' => $website
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                return $this->redirect($redirectUrl);
            }

            if (in_array($test->getState(), $this->testFinishedStates)) {
                return $this->redirect($this->getResultsUrl($normalisedWebsite, $normalisedTestId));
            } else {
                return $this->redirect($this->getProgressUrl($normalisedWebsite, $normalisedTestId));
            }
        }

        return $this->redirect($this->generateUrl(
            'view_dashboard_index_index',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        ));
    }

    /**
     * @param $website
     * @param string $test_id
     * @param int $task_id
     *
     * @return RedirectResponse
     */
    public function taskAction($website, $test_id, $task_id)
    {
        $router = $this->container->get('router');
        $redirectUrl = $router->generate(
            'view_test_task_results_index_index',
            [
                'website' => $website,
                'test_id' => $test_id,
                'task_id' => $task_id
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param Request $request
     * @param string $website
     * @param int $test_id
     *
     * @return array
     */
    private function createNormalisedWebsiteAndTestId(Request $request, $website, $test_id)
    {
        $requestWebsite = $request->request->get('website');
        if (empty($requestWebsite)) {
            $requestWebsite = $request->query->get('website');

            if (empty($requestWebsite)) {
                $requestWebsite = $website;
            }
        }

        if (empty($requestWebsite) && empty($test_id)) {
            return [
                null,
                null,
            ];
        }

        $normalisedWebsite = new NormalisedUrl($requestWebsite);

        if (!$normalisedWebsite->hasScheme()) {
            $normalisedWebsite->setScheme(self::DEFAULT_WEBSITE_SCHEME);
        }

        if (!$normalisedWebsite->hasHost()) {
            $normalisedWebsite = new NormalisedUrl($website . '/' . $test_id);

            return [
                (string)$normalisedWebsite,
                null,
            ];
        }

        if (is_int($test_id) || ctype_digit($test_id)) {
            return [
                (string)$normalisedWebsite,
                (int)$test_id,
            ];
        }

        $pathParts = explode('/', $normalisedWebsite->getPath());
        $pathPartLength = count($pathParts);

        for ($pathPartIndex = $pathPartLength - 1; $pathPartIndex >= 0; $pathPartIndex--) {
            if (ctype_digit($pathParts[$pathPartIndex])) {
                $normalisedWebsite->setPath('');

                return [
                    (string)$normalisedWebsite,
                    (int)$pathParts[$pathPartIndex],
                ];
            }
        }

        return [
            (string)$normalisedWebsite,
            null,
        ];
    }

    /**
     * @param string $website
     *
     * @return array
     */
    private function getWebsiteAndTestIdAndTaskIdFromWebsite($website)
    {
        $website = rtrim($website, '/');

        $pathParts = explode('/', $website);
        array_pop($pathParts);

        $taskId = array_pop($pathParts);
        $testId = array_pop($pathParts);

        return [
            'website' => implode('/', $pathParts),
            'test_id' => $testId,
            'task_id' => $taskId
        ];
    }

}
