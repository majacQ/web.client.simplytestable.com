<?php
/** @noinspection PhpDocSignatureInspection */

namespace App\Tests\Functional\Controller\View\Test\Progress;

use App\Controller\View\Test\ProgressController;
use App\Entity\Test;
use App\Model\Test\DecoratedTest;
use App\Services\SystemUserService;
use App\Services\UserManager;
use App\Tests\Factory\HttpResponseFactory;
use App\Tests\Factory\MockFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Tests\Functional\Controller\View\AbstractViewControllerTest;
use Twig_Environment;
use webignition\SimplyTestableUserModel\User;

class ProgressControllerTest extends AbstractViewControllerTest
{
    const VIEW_NAME = 'test-progress.html.twig';
    const ROUTE_NAME = 'view_test_progress';
    const WEBSITE = 'http://example.com/';
    const TEST_ID = 1;
    const USER_EMAIL = 'user@example.com';

    private $routeParameters = [
        'website' => self::WEBSITE,
        'test_id' => self::TEST_ID,
    ];

    private $remoteTestData = [
        'id' => self::TEST_ID,
        'website' => self::WEBSITE,
        'task_types' => [],
        'user' => self::USER_EMAIL,
        'state' => Test::STATE_IN_PROGRESS,
        'task_type_options' => [],
    ];

    public function testIsIEFiltered()
    {
        $this->issueIERequest(self::ROUTE_NAME, $this->routeParameters);
        $this->assertIEFilteredRedirectResponse();
    }

    /**
     * @dataProvider indexActionInvalidGetRequestDataProvider
     */
    public function testIndexActionInvalidGetRequest(array $httpFixtures, string $expectedRedirectUrl)
    {
        $this->httpMockHandler->appendFixtures($httpFixtures);

        $this->client->request(
            'GET',
            $this->router->generate(self::ROUTE_NAME, $this->routeParameters)
        );

        /* @var RedirectResponse $response */
        $response = $this->client->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($expectedRedirectUrl, $response->getTargetUrl());
    }

    public function indexActionInvalidGetRequestDataProvider(): array
    {
        return [
            'invalid user' => [
                'httpFixtures' => [
                    HttpResponseFactory::createNotFoundResponse(),
                ],
                'expectedRedirectUrl' => '/signout/',
            ],
            'invalid owner, not logged in' => [
                'httpFixtures' => [
                    HttpResponseFactory::createSuccessResponse(),
                    HttpResponseFactory::createForbiddenResponse(),
                ],
                'expectedRedirectUrl' => sprintf(
                    '/signin/?redirect=%s%s',
                    'eyJyb3V0ZSI6InZpZXdfdGVzdF9wcm9ncmVzcyIsInBhcmFtZXRlcnMiOnsid2Vic2l0ZSI6Imh0dHA6XC9cL2V4',
                    'YW1wbGUuY29tXC8iLCJ0ZXN0X2lkIjoiMSJ9fQ%3D%3D'
                ),
            ],
        ];
    }

    public function testIndexActionInvalidTestOwnerIsLoggedIn()
    {
        $userManager = self::$container->get(UserManager::class);

        $userManager->setUser(new User(self::USER_EMAIL));

        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createSuccessResponse(),
            HttpResponseFactory::createForbiddenResponse(),
        ]);

        $this->client->request(
            'GET',
            $this->router->generate(self::ROUTE_NAME, $this->routeParameters)
        );

        /* @var RedirectResponse $response */
        $response = $this->client->getResponse();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/http://example.com//1/unauthorised/', $response->getTargetUrl());
    }

    public function testIndexActionPublicUserGetRequest()
    {
        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createSuccessResponse(),
            HttpResponseFactory::createJsonResponse($this->remoteTestData),
        ]);

        $this->client->request(
            'GET',
            $this->router->generate(self::ROUTE_NAME, $this->routeParameters)
        );

        /* @var Response $response */
        $response = $this->client->getResponse();

        $this->assertTrue($response->isSuccessful());
    }

    /**
     * @dataProvider indexActionRedirectDataProvider
     */
    public function testIndexActionHttpRedirect(
        array $httpFixtures,
        User $user,
        string $website,
        int $testId,
        string $expectedRedirectUrl,
        string $expectedRequestUrl
    ) {
        $userManager = self::$container->get(UserManager::class);

        $userManager->setUser($user);
        $this->httpMockHandler->appendFixtures($httpFixtures);

        /* @var ProgressController $progressController */
        $progressController = self::$container->get(ProgressController::class);

        $response = $progressController->indexAction(new Request(), $website, $testId);
        $this->assertInstanceOf(RedirectResponse::class, $response);

        $this->assertEquals($expectedRedirectUrl, $response->getTargetUrl());
        $this->assertEquals($expectedRequestUrl, (string) $this->httpHistory->getLastRequestUrl());
    }

    /**
     * @dataProvider indexActionRedirectDataProvider
     */
    public function testIndexActionJsRedirect(
        array $httpFixtures,
        User $user,
        string $website,
        int $testId,
        string $expectedRedirectUrl,
        string $expectedRequestUrl
    ) {
        $userManager = self::$container->get(UserManager::class);

        $userManager->setUser($user);
        $this->httpMockHandler->appendFixtures($httpFixtures);

        /* @var ProgressController $progressController */
        $progressController = self::$container->get(ProgressController::class);

        $request = new Request();
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $progressController->indexAction($request, $website, $testId);
        $this->assertInstanceOf(JsonResponse::class, $response);

        $responseData = json_decode($response->getContent());

        $this->assertEquals('http://localhost' . $expectedRedirectUrl, $responseData->this_url);
        $this->assertEquals($expectedRequestUrl, (string) $this->httpHistory->getLastRequestUrl());
    }

    public function indexActionRedirectDataProvider(): array
    {
        $publicUser = SystemUserService::getPublicUser();
        $privateUser = new User('user@example.com');

        return [
            'remote test website not match request website' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse($this->remoteTestData),
                    HttpResponseFactory::createJsonResponse(array_merge($this->remoteTestData, [
                        'website' => 'bar',
                    ])),
                ],
                'user' => $publicUser,
                'website' => 'foo',
                'testId' => self::TEST_ID,
                'expectedRedirectUrl' => '/http://example.com//1/progress/',
                'expectedRequestUrl' => 'http://null/job/1/',
            ],
            'finished test; state=completed' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(array_merge($this->remoteTestData, [
                        'state' => Test::STATE_COMPLETED,
                    ])),
                ],
                'user' => $publicUser,
                'website' => self::WEBSITE,
                'testId' => self::TEST_ID,
                'expectedRedirectUrl' => '/http://example.com//1/results/',
                'expectedRequestUrl' => 'http://null/job/1/',
            ],
            'finished test; state=failed_no_sitemap; public user' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(array_merge($this->remoteTestData, [
                        'state' => Test::STATE_FAILED_NO_SITEMAP,
                        'user' => SystemUserService::PUBLIC_USER_USERNAME,
                    ])),
                ],
                'user' => $publicUser,
                'website' => self::WEBSITE,
                'testId' => self::TEST_ID,
                'expectedRedirectUrl' => '/http://example.com//1/results/',
                'expectedRequestUrl' => 'http://null/job/1/',
            ],
            'finished test; state=failed_no_sitemap; private user' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(array_merge($this->remoteTestData, [
                        'state' => Test::STATE_FAILED_NO_SITEMAP,
                        'user' => SystemUserService::PUBLIC_USER_USERNAME,
                    ])),
                ],
                'user' => $privateUser,
                'website' => self::WEBSITE,
                'testId' => self::TEST_ID,
                'expectedRedirectUrl' => '/1/re-test/',
                'expectedRequestUrl' => 'http://null/job/1/',
            ],
            'invalid remote test' => [
                'httpFixtures' => [
                    HttpResponseFactory::createSuccessResponse(),
                ],
                'user' => $publicUser,
                'website' => 'foo',
                'testId' => self::TEST_ID,
                'expectedRedirectUrl' => '/',
                'expectedRequestUrl' => 'http://null/job/1/',
            ],
        ];
    }

    /**
     * @dataProvider indexActionRenderTextHtmlDataProvider
     */
    public function testIndexActionRenderTextHtml(array $httpFixtures, User $user, Twig_Environment $twig)
    {
        $userManager = self::$container->get(UserManager::class);

        $userManager->setUser($user);

        $this->httpMockHandler->appendFixtures($httpFixtures);

        /* @var ProgressController $progressController */
        $progressController = self::$container->get(ProgressController::class);
        $this->setTwigOnController($twig, $progressController);

        $response = $progressController->indexAction(new Request(), self::WEBSITE, self::TEST_ID);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function indexActionRenderTextHtmlDataProvider(): array
    {
        return [
            'public user, in-progress, 79% done' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(array_merge($this->remoteTestData, [
                        'state' => Test::STATE_IN_PROGRESS,
                        'task_count' => 100,
                        'url_count' => 50,
                        'task_count_by_state' => [
                            'completed' => 79,
                        ],
                        'user' => SystemUserService::PUBLIC_USER_USERNAME,
                    ])),
                ],
                'user' => SystemUserService::getPublicUser(),
                'twig' => MockFactory::createTwig([
                    'render' => [
                        'withArgs' => function ($viewName, $parameters) {
                            $this->assertEquals(self::VIEW_NAME, $viewName);
                            $this->assertViewParameterKeys($parameters);
                            $this->assertTestAndRemoteTest($parameters);
                            $this->assertIsArray($parameters['website']);
                            $this->assertStateLabel($parameters, '50 urls, 100 tests; 79% done');
                            $this->assertAvailableTaskTypes($parameters, [
                                'html-validation',
                                'css-validation',
                            ]);
                            $this->assertIsArray($parameters['test_options']);
                            $this->assertTrue($parameters['is_public_user_test']);

                            return true;
                        },
                        'return' => new Response(),
                    ],
                ]),
            ],
            'public user, queued, 0% done' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(array_merge($this->remoteTestData, [
                        'state' => Test::STATE_QUEUED,
                        'task_count' => 44,
                        'url_count' => 11,
                        'user' => SystemUserService::PUBLIC_USER_USERNAME,
                    ])),
                ],
                'user' => SystemUserService::getPublicUser(),
                'twig' => MockFactory::createTwig([
                    'render' => [
                        'withArgs' => function ($viewName, $parameters) {
                            $this->assertEquals(self::VIEW_NAME, $viewName);
                            $this->assertViewParameterKeys($parameters);
                            $this->assertTestAndRemoteTest($parameters);
                            $this->assertIsArray($parameters['website']);
                            $this->assertStateLabel(
                                $parameters,
                                '11 urls, 44 tests; waiting for first test to begin'
                            );
                            $this->assertAvailableTaskTypes($parameters, [
                                'html-validation',
                                'css-validation',
                            ]);
                            $this->assertIsArray($parameters['test_options']);
                            $this->assertTrue($parameters['is_public_user_test']);

                            return true;
                        },
                        'return' => new Response(),
                    ],
                ]),
            ],
            'private user, crawling' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(array_merge($this->remoteTestData, [
                        'state' => Test::STATE_CRAWLING,
                        'task_count' => 44,
                        'url_count' => 11,
                        'user' => 'user@example.com',
                        'crawl' => [
                            'processed_url_count' => 10,
                            'discovered_url_count' => 30,
                            'limit' => 250,
                        ],
                    ])),
                ],
                'user' => new User('user@example.com'),
                'twig' => MockFactory::createTwig([
                    'render' => [
                        'withArgs' => function ($viewName, $parameters) {
                            $this->assertEquals(self::VIEW_NAME, $viewName);
                            $this->assertViewParameterKeys($parameters);
                            $this->assertTestAndRemoteTest($parameters);
                            $this->assertIsArray($parameters['website']);
                            $this->assertStateLabel(
                                $parameters,
                                'Finding URLs to test: 10 pages examined, 30 of 250 found'
                            );
                            $this->assertAvailableTaskTypes($parameters, [
                                'html-validation',
                                'css-validation',
                                'link-integrity',
                            ]);
                            $this->assertIsArray($parameters['test_options']);
                            $this->assertFalse($parameters['is_public_user_test']);

                            return true;
                        },
                        'return' => new Response(),
                    ],
                ]),
            ],
        ];
    }

    /**
     * @dataProvider indexActionRenderApplicationJsonDataProvider
     */
    public function testIndexActionRenderApplicationJson(array $httpFixtures, User $user, $expectedStateLabel)
    {
        $userManager = self::$container->get(UserManager::class);

        $userManager->setUser($user);

        $this->httpMockHandler->appendFixtures($httpFixtures);

        /* @var ProgressController $progressController */
        $progressController = self::$container->get(ProgressController::class);

        $request = new Request();
        $request->headers->set('accept', 'application/json');

        $response = $progressController->indexAction($request, self::WEBSITE, self::TEST_ID);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('application/json', $response->headers->get('content-type'));

        $responseData = json_decode($response->getContent(), true);

        $this->assertIsArray($responseData);
        $this->assertEquals([
            'test',
            'state_label',
            'this_url',
        ], array_keys($responseData));

        $this->assertIsArray($responseData['test']);
        $this->assertEquals(self::TEST_ID, $responseData['test']['test_id']);

        $this->assertEquals('http://localhost/http://example.com//1/progress/', $responseData['this_url']);
        $this->assertEquals($expectedStateLabel, $responseData['state_label']);
    }

    public function indexActionRenderApplicationJsonDataProvider(): array
    {
        return [
            'public user, in-progress, 79% done' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(array_merge($this->remoteTestData, [
                        'state' => Test::STATE_IN_PROGRESS,
                        'task_count' => 100,
                        'url_count' => 50,
                        'task_count_by_state' => [
                            'completed' => 79,
                        ],
                        'user' => SystemUserService::PUBLIC_USER_USERNAME,
                    ])),
                ],
                'user' => SystemUserService::getPublicUser(),
                'expectedStateLabel' => '50 urls, 100 tests; 79% done',
            ],
            'public user, queued, 0% done' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(array_merge($this->remoteTestData, [
                        'state' => Test::STATE_QUEUED,
                        'task_count' => 44,
                        'url_count' => 11,
                        'user' => SystemUserService::PUBLIC_USER_USERNAME,
                    ])),
                ],
                'user' => SystemUserService::getPublicUser(),
                'expectedStateLabel' => '11 urls, 44 tests; waiting for first test to begin',
            ],
            'private user, crawling' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(array_merge($this->remoteTestData, [
                        'state' => Test::STATE_CRAWLING,
                        'task_count' => 44,
                        'url_count' => 11,
                        'user' => 'user@example.com',
                        'crawl' => [
                            'processed_url_count' => 10,
                            'discovered_url_count' => 30,
                            'limit' => 250,
                        ],
                    ])),
                ],
                'user' => new User('user@example.com'),
                'expectedStateLabel' => 'Finding URLs to test: 10 pages examined, 30 of 250 found',
            ],
        ];
    }

    public function testIndexActionCachedResponse()
    {
        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createJsonResponse($this->remoteTestData),
        ]);

        $request = new Request();

        self::$container->get('request_stack')->push($request);

        /* @var ProgressController $progressController */
        $progressController = self::$container->get(ProgressController::class);

        $response = $progressController->indexAction(
            $request,
            self::WEBSITE,
            self::TEST_ID
        );
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseLastModified = new \DateTime($response->headers->get('last-modified'));
        $responseLastModified->modify('+1 hour');

        $newRequest = $request->duplicate();

        $newRequest->headers->set('if-modified-since', $responseLastModified->format('c'));
        $newResponse = $progressController->indexAction(
            $newRequest,
            self::WEBSITE,
            self::TEST_ID
        );

        $this->assertInstanceOf(Response::class, $newResponse);
        $this->assertEquals(304, $newResponse->getStatusCode());
    }

    private function assertViewParameterKeys(array $parameters)
    {
        $this->assertEquals(
            [
                'user',
                'is_logged_in',
                'test',
                'state_label',
                'website',
                'available_task_types',
                'task_types',
                'test_options',
                'is_public_user_test',
                'css_validation_ignore_common_cdns',
                'default_css_validation_options',
            ],
            array_keys($parameters)
        );
    }

    private function assertTestAndRemoteTest(array $parameters)
    {
        $this->assertInstanceOf(DecoratedTest::class, $parameters['test']);

        /* @var Test $test */
        $test = $parameters['test'];
        $this->assertEquals(self::TEST_ID, $test->getTestId());
        $this->assertEquals(self::WEBSITE, $test->getWebsite());
    }

    private function assertStateLabel(array $parameters, string $expectedStateLabel)
    {
        $this->assertIsString($parameters['state_label']);
        $this->assertEquals($expectedStateLabel, $parameters['state_label']);
    }

    private function assertAvailableTaskTypes(array $parameters, array $expectedTaskTypeKeys)
    {
        $this->assertIsArray($parameters['available_task_types']);
        $this->assertEquals($expectedTaskTypeKeys, array_keys($parameters['available_task_types']));
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        \Mockery::close();
        parent::tearDown();
    }
}
