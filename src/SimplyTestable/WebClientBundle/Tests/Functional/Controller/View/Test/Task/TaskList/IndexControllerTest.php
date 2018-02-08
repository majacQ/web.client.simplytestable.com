<?php

namespace SimplyTestable\WebClientBundle\Tests\Functional\Controller\View\Test\Task\TaskList;

use SimplyTestable\WebClientBundle\Controller\View\Test\Task\TaskList\IndexController;
use SimplyTestable\WebClientBundle\Entity\Task\Task;
use SimplyTestable\WebClientBundle\Entity\Test\Test;
use SimplyTestable\WebClientBundle\Exception\CoreApplicationReadOnlyException;
use SimplyTestable\WebClientBundle\Exception\CoreApplicationRequestException;
use SimplyTestable\WebClientBundle\Exception\InvalidAdminCredentialsException;
use SimplyTestable\WebClientBundle\Exception\InvalidContentTypeException;
use SimplyTestable\WebClientBundle\Exception\InvalidCredentialsException;
use SimplyTestable\WebClientBundle\Exception\WebResourceException;
use SimplyTestable\WebClientBundle\Services\CoreApplicationHttpClient;
use SimplyTestable\WebClientBundle\Tests\Factory\HttpResponseFactory;
use SimplyTestable\WebClientBundle\Tests\Functional\AbstractBaseTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class IndexControllerTest extends AbstractBaseTestCase
{
    const VIEW_NAME = 'SimplyTestableWebClientBundle:bs3/Test/Task/TaskList/Index:index.html.twig';
    const ROUTE_NAME = 'view_test_task_tasklist_index_index';

    const WEBSITE = 'http://example.com/';
    const TEST_ID = 1;
    const USER_EMAIL = 'user@example.com';

    /**
     * @var IndexController
     */
    private $indexController;

    /**
     * @var array
     */
    private $remoteTestData = [
        'id' => self::TEST_ID,
        'website' => self::WEBSITE,
        'task_types' => [],
        'user' => self::USER_EMAIL,
        'state' => Test::STATE_IN_PROGRESS,
        'task_type_options' => [],
        'task_count' => 12,
    ];

    /**
     * @var array
     */
    private $remoteTaskData = [
        'id' => 2,
        'url' => 'http://example.com/',
        'state' => Task::STATE_COMPLETED,
        'worker' => '',
        'type' => Task::TYPE_HTML_VALIDATION,
        'output' => [
            'output' => '',
            'content-type' => 'application/json',
            'error_count' => 0,
            'warning_count' => 0,
        ],
    ];

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->indexController = new IndexController();
    }

    public function testIndexActionInvalidUserGetRequest()
    {
        $this->setHttpFixtures([
            HttpResponseFactory::create(404),
        ]);

        $router = $this->container->get('router');
        $requestUrl = $router->generate(self::ROUTE_NAME, [
            'website' => self::WEBSITE,
            'test_id' => self::TEST_ID,
        ]);

        $this->client->request(
            'GET',
            $requestUrl
        );

        /* @var RedirectResponse $response */
        $response = $this->client->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('http://localhost/signout/', $response->getTargetUrl());
    }

    public function testIndexActionInvalidOwnerGetRequest()
    {
        $this->setHttpFixtures([
            HttpResponseFactory::create(200),
            HttpResponseFactory::createForbiddenResponse(),
        ]);

        $router = $this->container->get('router');
        $requestUrl = $router->generate(self::ROUTE_NAME, [
            'website' => self::WEBSITE,
            'test_id' => self::TEST_ID,
        ]);

        $this->client->request(
            'GET',
            $requestUrl
        );

        /* @var Response $response */
        $response = $this->client->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testIndexActionPublicUserGetRequest()
    {
        $this->setHttpFixtures([
            HttpResponseFactory::create(200),
            HttpResponseFactory::createJsonResponse($this->remoteTestData),
        ]);

        $this->setCoreApplicationHttpClientHttpFixtures([
            HttpResponseFactory::createJsonResponse([$this->remoteTaskData]),
        ]);

        $router = $this->container->get('router');
        $requestUrl = $router->generate(self::ROUTE_NAME, [
            'website' => self::WEBSITE,
            'test_id' => self::TEST_ID,
        ]);

        $this->client->request(
            'POST',
            $requestUrl,
            [
                'taskIds' => [2],
            ]
        );

        /* @var Response $response */
        $response = $this->client->getResponse();
        $this->assertTrue($response->isSuccessful());
        $this->assertNotEmpty($response->getContent());
    }

    /**
     * @dataProvider indexActionRenderEmptyContentDataProvider
     *
     * @param array $httpFixtures
     * @param Request $request
     *
     * @throws WebResourceException
     * @throws CoreApplicationReadOnlyException
     * @throws CoreApplicationRequestException
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     * @throws InvalidCredentialsException
     */
    public function testIndexActionRenderEmptyContent(
        array $httpFixtures,
        Request $request
    ) {
        $userService = $this->container->get('simplytestable.services.userservice');
        $coreApplicationHttpClient = $this->container->get(CoreApplicationHttpClient::class);
        $coreApplicationHttpClient->setUser($userService->getPublicUser());

        $this->setHttpFixtures([$httpFixtures[0]]);
        $this->setCoreApplicationHttpClientHttpFixtures([$httpFixtures[1]]);

        $this->indexController->setContainer($this->container);

        $response = $this->indexController->indexAction(
            $request,
            self::WEBSITE,
            self::TEST_ID
        );

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEmpty($response->getContent());
    }

    /**
     * @return array
     */
    public function indexActionRenderEmptyContentDataProvider()
    {
        return [
            'no request task ids' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse($this->remoteTestData),
                    HttpResponseFactory::createJsonResponse([]),
                ],
                'request' => new Request(),
            ],
            'invalid request task ids' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse($this->remoteTestData),
                    HttpResponseFactory::createJsonResponse([]),
                ],
                'request' => new Request([], [
                    'taskIds' => [
                        'foo', 'bar', true, false,
                    ],
                ]),
            ],
            'valid request task ids, no tasks' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse($this->remoteTestData),
                    HttpResponseFactory::createJsonResponse([]),
                ],
                'request' => new Request([], [
                    'taskIds' => [1, 2, 3],
                ]),
                'expectedResponseIsEmpty' => true,
            ],
        ];
    }

    /**
     * @dataProvider indexActionRenderContentDataProvider
     *
     * @param array $httpFixtures
     * @param Request $request
     * @param array $expectedTaskSetCollection
     *
     * @throws CoreApplicationReadOnlyException
     * @throws CoreApplicationRequestException
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     * @throws InvalidCredentialsException
     * @throws WebResourceException
     */
    public function testIndexActionRenderContent(
        array $httpFixtures,
        Request $request,
        array $expectedTaskSetCollection
    ) {
        $userService = $this->container->get('simplytestable.services.userservice');
        $coreApplicationHttpClient = $this->container->get(CoreApplicationHttpClient::class);
        $coreApplicationHttpClient->setUser($userService->getPublicUser());

        $this->setHttpFixtures([$httpFixtures[0]]);
        $this->setCoreApplicationHttpClientHttpFixtures([$httpFixtures[1]]);

        $this->indexController->setContainer($this->container);

        $response = $this->indexController->indexAction(
            $request,
            self::WEBSITE,
            self::TEST_ID
        );

        $this->assertInstanceOf(Response::class, $response);

        $content = $response->getContent();

        $this->assertNotEmpty($content);

        $crawler = new Crawler($content);

        $taskSets = $crawler->filter('.task-set');

        $this->assertCount(count($expectedTaskSetCollection), $taskSets);

        $taskSets->each(function (Crawler $taskSet, $taskSetIndex) use ($expectedTaskSetCollection) {
            $expectedTaskSet = $expectedTaskSetCollection[$taskSetIndex];

            $taskSetUrl = $taskSet->filter('.url')->text();
            $this->assertEquals($expectedTaskSet['url'], $taskSetUrl);

            $tasks = $taskSet->filter('.task');

            $expectedTasks = $expectedTaskSet['tasks'];

            $tasks->each(function (Crawler $task, $taskIndex) use ($expectedTasks) {
                $expectedTask = $expectedTasks[$taskIndex];

                $this->assertEquals('task' . $expectedTask['id'], $task->attr('id'));
                $this->assertEquals($expectedTask['state'], $task->attr('data-state'));
                $this->assertEquals($expectedTask['type'], $task->filter('.type')->text());

                $errorLabel =  $task->filter('.label-danger');
                if (is_null($expectedTask['error_count'])) {
                    $this->assertEquals(0, $errorLabel->count());
                } else {
                    $trimmedErrorLabel = preg_replace('!\s+!', ' ', trim($errorLabel->text()));
                    $this->assertEquals($expectedTask['error_count'] . ' errors', $trimmedErrorLabel);
                }

                $warningLabel =  $task->filter('.label-primary');
                if (is_null($expectedTask['warning_count'])) {
                    $this->assertEquals(0, $warningLabel->count());

                } else {
                    $trimmedWarningLabel = preg_replace('!\s+!', ' ', trim($warningLabel->text()));
                    $this->assertEquals($expectedTask['warning_count'] . ' warnings', $trimmedWarningLabel);
                }
            });
        });
    }

    /**
     * @return array
     */
    public function indexActionRenderContentDataProvider()
    {
        return [
            'single task, no errors, no warnings' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse($this->remoteTestData),
                    HttpResponseFactory::createJsonResponse([
                        array_merge($this->remoteTaskData, [
                            'id' => 2,
                            'url' => 'http://example.com/',
                            'type' => Task::TYPE_HTML_VALIDATION,
                            'state' => Task::STATE_COMPLETED,
                        ]),
                    ]),
                ],
                'request' => new Request([], [
                    'taskIds' => [2,],
                ]),
                'expectedTaskSetCollection' => [
                    [
                        'url' => 'http://example.com/',
                        'tasks' => [
                            [
                                'id' => 2,
                                'type' => Task::TYPE_HTML_VALIDATION,
                                'state' => Task::STATE_COMPLETED,
                                'error_count' => null,
                                'warning_count' => null,
                            ],
                        ],
                    ],
                ],
            ],
            'single task, has errors, has warnings' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse($this->remoteTestData),
                    HttpResponseFactory::createJsonResponse([
                        array_merge($this->remoteTaskData, [
                            'id' => 2,
                            'url' => 'http://example.com/',
                            'type' => Task::TYPE_HTML_VALIDATION,
                            'state' => Task::STATE_COMPLETED,
                            'output' => [
                                'output' => '',
                                'content-type' => 'application/json',
                                'error_count' => 12,
                                'warning_count' => 22,
                            ],
                        ]),
                    ]),
                ],
                'request' => new Request([], [
                    'taskIds' => [2,],
                ]),
                'expectedTaskSetCollection' => [
                    [
                        'url' => 'http://example.com/',
                        'tasks' => [
                            [
                                'id' => 2,
                                'type' => Task::TYPE_HTML_VALIDATION,
                                'state' => Task::STATE_COMPLETED,
                                'error_count' => 12,
                                'warning_count' => 22,
                            ],
                        ],
                    ],
                ],
            ],
            'multiple tasks, no errors, no warnings' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse($this->remoteTestData),
                    HttpResponseFactory::createJsonResponse([
                        array_merge($this->remoteTaskData, [
                            'id' => 2,
                            'url' => 'http://example.com/',
                            'type' => Task::TYPE_HTML_VALIDATION,
                            'state' => Task::STATE_COMPLETED,
                        ]),
                        array_merge($this->remoteTaskData, [
                            'id' => 3,
                            'url' => 'http://example.com/',
                            'type' => Task::TYPE_CSS_VALIDATION,
                            'state' => Task::STATE_CANCELLED,
                        ]),
                        array_merge($this->remoteTaskData, [
                            'id' => 4,
                            'url' => 'http://example.com/foo',
                            'type' => Task::TYPE_HTML_VALIDATION,
                            'state' => Task::STATE_SKIPPED,
                        ]),
                        array_merge($this->remoteTaskData, [
                            'id' => 5,
                            'url' => 'http://example.com/foo',
                            'type' => Task::TYPE_CSS_VALIDATION,
                            'state' => Task::STATE_IN_PROGRESS,
                        ]),
                    ]),
                ],
                'request' => new Request([], [
                    'taskIds' => [2, 3, 4, 5, ],
                ]),
                'expectedTaskSetCollection' => [
                    [
                        'url' => 'http://example.com/',
                        'tasks' => [
                            [
                                'id' => 2,
                                'type' => Task::TYPE_HTML_VALIDATION,
                                'state' => Task::STATE_COMPLETED,
                                'error_count' => null,
                                'warning_count' => null,
                            ],
                            [
                                'id' => 3,
                                'type' => Task::TYPE_CSS_VALIDATION,
                                'state' => Task::STATE_CANCELLED,
                                'error_count' => null,
                                'warning_count' => null,
                            ],
                        ],
                    ],
                    [
                        'url' => 'http://example.com/foo',
                        'tasks' => [
                            [
                                'id' => 4,
                                'type' => Task::TYPE_HTML_VALIDATION,
                                'state' => Task::STATE_SKIPPED,
                                'error_count' => null,
                                'warning_count' => null,
                            ],
                            [
                                'id' => 5,
                                'type' => Task::TYPE_CSS_VALIDATION,
                                'state' => Task::STATE_IN_PROGRESS,
                                'error_count' => null,
                                'warning_count' => null,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testIndexActionCachedResponse()
    {
        $userService = $this->container->get('simplytestable.services.userservice');
        $coreApplicationHttpClient = $this->container->get(CoreApplicationHttpClient::class);
        $coreApplicationHttpClient->setUser($userService->getPublicUser());

        $this->setHttpFixtures([
            HttpResponseFactory::createJsonResponse($this->remoteTestData),
        ]);

        $this->setCoreApplicationHttpClientHttpFixtures([
            HttpResponseFactory::createJsonResponse([$this->remoteTaskData]),
        ]);

        $request = new Request([], [
            'taskIds' => [2],
        ]);

        $this->indexController->setContainer($this->container);

        $response = $this->indexController->indexAction(
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
        $newResponse = $this->indexController->indexAction(
            $newRequest,
            self::WEBSITE,
            self::TEST_ID
        );

        $this->assertInstanceOf(Response::class, $newResponse);
        $this->assertEquals(304, $newResponse->getStatusCode());
    }
}
