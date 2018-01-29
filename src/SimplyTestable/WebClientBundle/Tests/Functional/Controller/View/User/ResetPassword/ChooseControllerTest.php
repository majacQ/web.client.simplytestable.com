<?php

namespace SimplyTestable\WebClientBundle\Tests\Functional\Controller\View\User\ResetPassword;

use SimplyTestable\WebClientBundle\Controller\Action\User\ResetPassword\IndexController;
use SimplyTestable\WebClientBundle\Controller\View\User\ResetPassword\ChooseController;
use SimplyTestable\WebClientBundle\Exception\CoreApplicationAdminRequestException;
use SimplyTestable\WebClientBundle\Exception\WebResourceException;
use SimplyTestable\WebClientBundle\Model\User;
use SimplyTestable\WebClientBundle\Tests\Factory\ContainerFactory;
use SimplyTestable\WebClientBundle\Tests\Factory\HttpResponseFactory;
use SimplyTestable\WebClientBundle\Tests\Factory\MockFactory;
use SimplyTestable\WebClientBundle\Tests\Functional\BaseSimplyTestableTestCase;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ChooseControllerTest extends BaseSimplyTestableTestCase
{
    const VIEW_NAME = 'SimplyTestableWebClientBundle:bs3/User/ResetPassword/Choose:index.html.twig';
    const ROUTE_NAME = 'view_user_resetpassword_choose_index';

    const USER_EMAIL = 'user@example.com';
    const TOKEN = 'token-value';

    /**
     * @var ChooseController
     */
    private $chooseController;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->chooseController = new ChooseController();
    }

    public function testIndexActionPublicUserGetRequest()
    {
        $this->setHttpFixtures([
            HttpResponseFactory::create(200),
            HttpResponseFactory::createJsonResponse(self::TOKEN),
        ]);

        $this->client->request(
            'GET',
            $this->createRequestUrl()
        );

        /* @var Response $response */
        $response = $this->client->getResponse();
        $this->assertTrue($response->isSuccessful());
    }

    /**
     * @dataProvider indexActionRenderDataProvider
     *
     * @param array $httpFixtures
     * @param array $flashBagValues
     * @param Request $request
     * @param string $token
     * @param EngineInterface $templatingEngine
     *
     * @throws CoreApplicationAdminRequestException
     */
    public function testIndexActionRender(
        array $httpFixtures,
        array $flashBagValues,
        Request $request,
        $token,
        EngineInterface $templatingEngine
    ) {
        $userService = $this->container->get('simplytestable.services.userservice');
        $session = $this->container->get('session');

        $user = new User(self::USER_EMAIL);

        $userService->setUser($user);

        if (!empty($httpFixtures)) {
            $this->setHttpFixtures($httpFixtures);
        }

        if (!empty($flashBagValues)) {
            foreach ($flashBagValues as $key => $value) {
                $session->getFlashBag()->set($key, $value);
            }
        }

        $containerFactory = new ContainerFactory($this->container);
        $container = $containerFactory->create(
            [
                'simplytestable.services.cachevalidator',
                'simplytestable.services.userservice',
                'simplytestable.services.flashbagvalues',
            ],
            [
                'templating' => $templatingEngine,
            ],
            [
                'public_site',
                'external_links',
            ]
        );

        $this->chooseController->setContainer($container);

        $response = $this->chooseController->indexAction($request, self::USER_EMAIL, $token);
        $this->assertInstanceOf(Response::class, $response);
    }

    /**
     * @return array
     */
    public function indexActionRenderDataProvider()
    {
        return [
            'invalid token' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(self::TOKEN),
                ],
                'flashBagValues' => [],
                'request' => new Request(),
                'token' => 'invalid-token',
                'templatingEngine' => MockFactory::createTemplatingEngine([
                    'render' => [
                        'withArgs' => function ($viewName, $parameters) {
                            $this->assertCommonViewData($viewName, $parameters);

                            $this->assertEquals('invalid-token', $parameters['token']);
                            $this->assertNull($parameters['stay_signed_in']);
                            $this->assertEquals('invalid-token', $parameters['user_reset_password_error']);

                            return true;
                        },
                        'return' => new Response(),
                    ],
                ]),
            ],
            'invalid token, has password reset error' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(self::TOKEN),
                ],
                'flashBagValues' => [
                    IndexController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_INACTIVE_RECIPIENT,
                ],
                'request' => new Request(),
                'token' => 'invalid-token',
                'templatingEngine' => MockFactory::createTemplatingEngine([
                    'render' => [
                        'withArgs' => function ($viewName, $parameters) {
                            $this->assertCommonViewData($viewName, $parameters);

                            $this->assertEquals('invalid-token', $parameters['token']);
                            $this->assertNull($parameters['stay_signed_in']);
                            $this->assertEquals('invalid-token', $parameters['user_reset_password_error']);

                            return true;
                        },
                        'return' => new Response(),
                    ],
                ]),
            ],
            'valid token, has password reset error' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(self::TOKEN),
                ],
                'flashBagValues' => [
                    IndexController::FLASH_BAG_REQUEST_ERROR_KEY =>
                        IndexController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_INACTIVE_RECIPIENT,
                ],
                'request' => new Request(),
                'token' => self::TOKEN,
                'templatingEngine' => MockFactory::createTemplatingEngine([
                    'render' => [
                        'withArgs' => function ($viewName, $parameters) {
                            $this->assertCommonViewData($viewName, $parameters);

                            $this->assertEquals(self::TOKEN, $parameters['token']);
                            $this->assertNull($parameters['stay_signed_in']);
                            $this->assertEquals(
                                IndexController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_INACTIVE_RECIPIENT,
                                $parameters['user_reset_password_error']
                            );

                            return true;
                        },
                        'return' => new Response(),
                    ],
                ]),
            ],
            'valid token' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(self::TOKEN),
                ],
                'flashBagValues' => [],
                'request' => new Request(),
                'token' => self::TOKEN,
                'templatingEngine' => MockFactory::createTemplatingEngine([
                    'render' => [
                        'withArgs' => function ($viewName, $parameters) {
                            $this->assertCommonViewData($viewName, $parameters);

                            $this->assertEquals(self::TOKEN, $parameters['token']);
                            $this->assertNull($parameters['stay_signed_in']);
                            $this->assertNull($parameters['user_reset_password_error']);

                            return true;
                        },
                        'return' => new Response(),
                    ],
                ]),
            ],
            'valid token, stay-signed-in' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse(self::TOKEN),
                ],
                'flashBagValues' => [],
                'request' => new Request([
                    'stay-signed-in' => 1,
                ]),
                'token' => self::TOKEN,
                'templatingEngine' => MockFactory::createTemplatingEngine([
                    'render' => [
                        'withArgs' => function ($viewName, $parameters) {
                            $this->assertCommonViewData($viewName, $parameters);

                            $this->assertEquals(self::TOKEN, $parameters['token']);
                            $this->assertEquals(1, $parameters['stay_signed_in']);
                            $this->assertNull($parameters['user_reset_password_error']);

                            return true;
                        },
                        'return' => new Response(),
                    ],
                ]),
            ],
        ];
    }

    public function testIndexActionCachedResponse()
    {
        $this->setHttpFixtures([
            HttpResponseFactory::createJsonResponse(self::TOKEN),
        ]);

        $request = new Request();

        $this->container->set('request', $request);
        $this->chooseController->setContainer($this->container);

        $response = $this->chooseController->indexAction(
            $request,
            self::USER_EMAIL,
            self::TOKEN
        );
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseLastModified = new \DateTime($response->headers->get('last-modified'));
        $responseLastModified->modify('+1 hour');

        $newRequest = $request->duplicate();

        $newRequest->headers->set('if-modified-since', $responseLastModified->format('c'));
        $newResponse = $this->chooseController->indexAction(
            $newRequest,
            self::USER_EMAIL,
            self::TOKEN
        );

        $this->assertInstanceOf(Response::class, $newResponse);
        $this->assertEquals(304, $newResponse->getStatusCode());
    }

    /**
     * @param string $viewName
     * @param array $parameters
     */
    private function assertCommonViewData($viewName, $parameters)
    {
        $this->assertEquals(self::VIEW_NAME, $viewName);
        $this->assertViewParameterKeys($parameters);

        $this->assertEquals(self::USER_EMAIL, $parameters['email']);
    }

    /**
     * @param array $parameters
     */
    private function assertViewParameterKeys(array $parameters)
    {
        $this->assertEquals(
            [
                'user',
                'is_logged_in',
                'public_site',
                'external_links',
                'email',
                'token',
                'stay_signed_in',
                'user_reset_password_error',
            ],
            array_keys($parameters)
        );
    }


    /**
     * @return string
     */
    private function createRequestUrl()
    {
        $router = $this->container->get('router');

        return $router->generate(self::ROUTE_NAME, [
            'email' => self::USER_EMAIL,
            'token' => self::TOKEN,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        \Mockery::close();
        parent::tearDown();
    }
}
