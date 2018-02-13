<?php

namespace SimplyTestable\WebClientBundle\Tests\Functional\Controller\View\User\SignUp;

use Guzzle\Http\Message\Response;
use SimplyTestable\WebClientBundle\Controller\Action\SignUp\Team\InviteController as ActionInviteController;
use SimplyTestable\WebClientBundle\Controller\View\User\SignUp\InviteController;
use SimplyTestable\WebClientBundle\Exception\CoreApplicationAdminRequestException;
use SimplyTestable\WebClientBundle\Model\Team\Invite;
use SimplyTestable\WebClientBundle\Model\User;
use SimplyTestable\WebClientBundle\Services\UserManager;
use SimplyTestable\WebClientBundle\Tests\Factory\ContainerFactory;
use SimplyTestable\WebClientBundle\Tests\Factory\HttpResponseFactory;
use SimplyTestable\WebClientBundle\Tests\Factory\MockFactory;
use SimplyTestable\WebClientBundle\Tests\Functional\AbstractBaseTestCase;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InviteControllerTest extends AbstractBaseTestCase
{
    const INVITE_USERNAME = 'user@example.com';
    const TOKEN = 'tokenValue';
    const TEAM_NAME = 'Team Name';

    /**
     * @var InviteController
     */
    private $inviteController;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->inviteController = new InviteController();
    }

    public function testIndexActionGetRequest()
    {
        $this->setCoreApplicationHttpClientHttpFixtures([
            HttpResponseFactory::createJsonResponse([
                'team' => 'Team Name',
                'user' => self::INVITE_USERNAME,
                'token' => self::TOKEN,
            ]),
        ]);

        $session = $this->container->get('session');
        $flashBag = $session->getFlashBag();

        $flashBag->set(ActionInviteController::FLASH_BAG_INVITE_ACCEPT_ERROR_KEY, 'foo');

        $router = $this->container->get('router');
        $requestUrl = $router->generate('view_user_signup_invite_index', [
            'token' => self::TOKEN,
        ]);

        $this->client->request(
            'GET',
            $requestUrl
        );

        /* @var SymfonyResponse $response */
        $response = $this->client->getResponse();

        $this->assertTrue($response->isSuccessful());
    }

    /**
     * @dataProvider indexActionRenderDataProvider
     *
     * @param array $httpFixtures
     * @param Request $request
     * @param array $flashBagValues
     * @param EngineInterface $templatingEngine
     *
     * @throws CoreApplicationAdminRequestException
     */
    public function testIndexActionRender(
        array $httpFixtures,
        Request $request,
        array $flashBagValues,
        EngineInterface $templatingEngine
    ) {
        $this->setCoreApplicationHttpClientHttpFixtures($httpFixtures);

        $session = $this->container->get('session');

        $flashBag = $session->getFlashBag();

        foreach ($flashBagValues as $key => $value) {
            $flashBag->set($key, $value);
        }

        $containerFactory = new ContainerFactory($this->container);

        $container = $containerFactory->create(
            [
                'simplytestable.services.teaminviteservice',
                'simplytestable.services.userservice',
                'simplytestable.services.flashbagvalues',
                'simplytestable.services.cachevalidator',
                UserManager::class,
            ],
            [
                'templating' => $templatingEngine,
            ],
            [
                'public_site',
                'external_links',
            ]
        );

        $this->inviteController->setContainer($container);

        $this->inviteController->indexAction($request, self::TOKEN);
    }

    /**
     * @return array
     */
    public function indexActionRenderDataProvider()
    {
        $inviteData = [
            'team' => self::TEAM_NAME,
            'user' => self::INVITE_USERNAME,
            'token' => self::TOKEN,
        ];

        $invite = new Invite($inviteData);

        return [
            'password blank' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse($inviteData),
                ],
                'request' => new Request(),
                'flashBagValues' => [
                    ActionInviteController::FLASH_BAG_INVITE_ACCEPT_ERROR_KEY =>
                        ActionInviteController::FLASH_BAG_INVITE_ACCEPT_ERROR_MESSAGE_PASSWORD_BLANK
                ],
                'templatingEngine' => MockFactory::createTemplatingEngine([
                    'render' => [
                        'withArgs' => function ($viewName, $parameters) use ($invite) {
                            $this->assertEquals(
                                'SimplyTestableWebClientBundle:bs3/User/SignUp/Invite:index.html.twig',
                                $viewName
                            );

                            $this->assertEquals(
                                ActionInviteController::FLASH_BAG_INVITE_ACCEPT_ERROR_MESSAGE_PASSWORD_BLANK,
                                $parameters[ActionInviteController::FLASH_BAG_INVITE_ACCEPT_ERROR_KEY]
                            );
                            $this->assertArrayNotHasKey(
                                ActionInviteController::FLASH_BAG_INVITE_ACCEPT_FAILURE_KEY,
                                $parameters
                            );

                            $this->assertEquals(
                                $invite,
                                $parameters['invite']
                            );
                            $this->assertTrue($parameters['has_invite']);
                            $this->assertNull($parameters['stay_signed_in']);

                            $this->assertTemplateParameters($parameters);

                            return true;
                        },
                        'return' => new SymfonyResponse(),
                    ],
                ]),
            ],
            'get invite failure' => [
                'httpFixtures' => [
                    HttpResponseFactory::createInternalServerErrorResponse(),
                    HttpResponseFactory::createInternalServerErrorResponse(),
                    HttpResponseFactory::createInternalServerErrorResponse(),
                    HttpResponseFactory::createInternalServerErrorResponse(),
                ],
                'request' => new Request(),
                'flashBagValues' => [
                    ActionInviteController::FLASH_BAG_INVITE_ACCEPT_ERROR_KEY =>
                        ActionInviteController::FLASH_BAG_INVITE_ACCEPT_ERROR_MESSAGE_FAILURE,
                    ActionInviteController::FLASH_BAG_INVITE_ACCEPT_FAILURE_KEY => 500,
                ],
                'templatingEngine' => MockFactory::createTemplatingEngine([
                    'render' => [
                        'withArgs' => function ($viewName, $parameters) {
                            $this->assertEquals(
                                'SimplyTestableWebClientBundle:bs3/User/SignUp/Invite:index.html.twig',
                                $viewName
                            );

                            $this->assertEquals(
                                ActionInviteController::FLASH_BAG_INVITE_ACCEPT_ERROR_MESSAGE_FAILURE,
                                $parameters[ActionInviteController::FLASH_BAG_INVITE_ACCEPT_ERROR_KEY]
                            );
                            $this->assertEquals(
                                500,
                                $parameters[ActionInviteController::FLASH_BAG_INVITE_ACCEPT_FAILURE_KEY]
                            );

                            $this->assertNull($parameters['invite']);
                            $this->assertFalse($parameters['has_invite']);
                            $this->assertNull($parameters['stay_signed_in']);

                            $this->assertTemplateParameters($parameters);

                            return true;
                        },
                        'return' => new SymfonyResponse(),
                    ],
                ]),
            ],
            'no errors, stay signed in' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse($inviteData),
                ],
                'request' => new Request([
                    'stay-signed-in' => 1,
                ]),
                'flashBagValues' => [],
                'templatingEngine' => MockFactory::createTemplatingEngine([
                    'render' => [
                        'withArgs' => function ($viewName, $parameters) use ($invite) {
                            $this->assertEquals(
                                'SimplyTestableWebClientBundle:bs3/User/SignUp/Invite:index.html.twig',
                                $viewName
                            );

                            $this->assertArrayNotHasKey(
                                ActionInviteController::FLASH_BAG_INVITE_ACCEPT_ERROR_KEY,
                                $parameters
                            );
                            $this->assertArrayNotHasKey(
                                ActionInviteController::FLASH_BAG_INVITE_ACCEPT_FAILURE_KEY,
                                $parameters
                            );

                            $this->assertEquals($invite, $parameters['invite']);
                            $this->assertTrue($parameters['has_invite']);
                            $this->assertEquals(1, $parameters['stay_signed_in']);

                            $this->assertTemplateParameters($parameters);

                            return true;
                        },
                        'return' => new SymfonyResponse(),
                    ],
                ]),
            ],
        ];
    }

    public function testIndexActionCachedResponse()
    {
        $this->setCoreApplicationHttpClientHttpFixtures([
            HttpResponseFactory::createJsonResponse([
                'team' => self::TEAM_NAME,
                'user' => self::INVITE_USERNAME,
                'token' => self::TOKEN,
            ]),
        ]);

        $request = new Request();

        $this->container->get('request_stack')->push($request);
        $this->inviteController->setContainer($this->container);

        $response = $this->inviteController->indexAction($request, self::TOKEN);
        $this->assertInstanceOf(SymfonyResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $responseLastModified = new \DateTime($response->headers->get('last-modified'));
        $responseLastModified->modify('+1 hour');

        $newRequest = $request->duplicate();

        $newRequest->headers->set('if-modified-since', $responseLastModified->format('c'));
        $newResponse = $this->inviteController->indexAction($newRequest, self::TOKEN);

        $this->assertInstanceOf(SymfonyResponse::class, $newResponse);
        $this->assertEquals(304, $newResponse->getStatusCode());
    }

    /**
     * @param array $parameters
     */
    private function assertTemplateParameters(array $parameters)
    {
        $publicUser = new User('public', 'public');

        $this->assertInternalType('array', $parameters['public_site']);
        $this->assertInternalType('array', $parameters['external_links']);
        $this->assertEquals(self::TOKEN, $parameters['token']);
        $this->assertEquals($publicUser, $parameters['user']);
        $this->assertFalse($parameters['is_logged_in']);
    }
}
