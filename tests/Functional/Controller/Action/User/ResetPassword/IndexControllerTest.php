<?php

namespace App\Tests\Functional\Controller\Action\User\ResetPassword;

use Psr\Http\Message\ResponseInterface;
use App\Controller\Action\User\ResetPassword\IndexController;
use App\Exception\CoreApplicationRequestException;
use App\Exception\InvalidAdminCredentialsException;
use App\Exception\InvalidContentTypeException;
use App\Tests\Factory\HttpResponseFactory;
use App\Tests\Factory\PostmarkHttpResponseFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Exception\Mail\Configuration\Exception as MailConfigurationException;
use App\Tests\Functional\Controller\AbstractControllerTest;
use App\Tests\Services\HttpMockHandler;
use App\Tests\Services\PostmarkMessageVerifier;
use webignition\HttpHistoryContainer\Container as HttpHistoryContainer;

class IndexControllerTest extends AbstractControllerTest
{
    const ROUTE_NAME = 'action_user_reset_password_request';
    const EMAIL = 'user@example.com';
    const CONFIRMATION_TOKEN = 'confirmation-token-here';

    /**
     * @var IndexController
     */
    private $indexController;

    /**
     * @var HttpMockHandler
     */
    private $httpMockHandler;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->indexController = self::$container->get(IndexController::class);
        $this->httpMockHandler = self::$container->get(HttpMockHandler::class);
    }

    public function testRequestActionPostRequest()
    {
        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createSuccessResponse(),
            HttpResponseFactory::createJsonResponse(self::CONFIRMATION_TOKEN),
            PostmarkHttpResponseFactory::createSuccessResponse(),
        ]);

        $this->client->request(
            'POST',
            $this->router->generate(self::ROUTE_NAME),
            [
                'email' => self::EMAIL
            ]
        );

        /* @var RedirectResponse $response */
        $response = $this->client->getResponse();

        $this->assertEquals(
            '/reset-password/?email=user%40example.com',
            $response->getTargetUrl()
        );
    }

    /**
     * @dataProvider requestActionBadRequestDataProvider
     *
     * @param Request $request
     * @param array $expectedFlashBagValues
     * @param string $expectedRedirectUrl
     *
     * @throws MailConfigurationException
     * @throws CoreApplicationRequestException
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     */
    public function testRequestActionBadRequest(Request $request, array $expectedFlashBagValues, $expectedRedirectUrl)
    {
        $session = self::$container->get('session');

        /* @var RedirectResponse $response */
        $response = $this->indexController->requestAction($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($expectedFlashBagValues, $session->getFlashBag()->peekAll());
        $this->assertEquals($expectedRedirectUrl, $response->getTargetUrl());
    }

    /**
     * @return array
     */
    public function requestActionBadRequestDataProvider()
    {
        return [
            'empty email' => [
                'request' => new Request(),
                'expectedFlashBagValues' => [
                    IndexController::FLASH_BAG_REQUEST_ERROR_KEY => [
                        IndexController::FLASH_BAG_REQUEST_ERROR_MESSAGE_EMAIL_BLANK,
                    ],
                ],
                'expectedRedirectUrl' => '/reset-password/',
            ],
            'invalid email' => [
                'request' => new Request([], [
                    'email' => 'foo',
                ]),
                'expectedFlashBagValues' => [
                    IndexController::FLASH_BAG_REQUEST_ERROR_KEY => [
                        IndexController::FLASH_BAG_REQUEST_ERROR_MESSAGE_EMAIL_INVALID,
                    ],
                ],
                'expectedRedirectUrl' => '/reset-password/?email=foo',
            ],
        ];
    }

    /**
     * @throws CoreApplicationRequestException
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     * @throws MailConfigurationException
     */
    public function testRequestActionUserDoesNotExist()
    {
        $session = self::$container->get('session');

        $request = new Request([], [
            'email' => self::EMAIL,
        ]);

        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createNotFoundResponse(),
        ]);

        /* @var RedirectResponse $response */
        $response = $this->indexController->requestAction($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/reset-password/?email=user%40example.com', $response->getTargetUrl());
        $this->assertEquals(
            [
                IndexController::FLASH_BAG_REQUEST_ERROR_KEY => [
                    IndexController::FLASH_BAG_REQUEST_ERROR_MESSAGE_USER_INVALID,
                ],
            ],
            $session->getFlashBag()->peekAll()
        );
    }

    /**
     * @throws CoreApplicationRequestException
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     * @throws MailConfigurationException
     */
    public function testRequestActionInvalidAdminCredentials()
    {
        $session = self::$container->get('session');

        $request = new Request([], [
            'email' => self::EMAIL,
        ]);

        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createForbiddenResponse(),
            PostmarkHttpResponseFactory::createSuccessResponse(),
        ]);

        /* @var RedirectResponse $response */
        $response = $this->indexController->requestAction($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/reset-password/?email=user%40example.com', $response->getTargetUrl());
        $this->assertEquals(
            [
                IndexController::FLASH_BAG_REQUEST_ERROR_KEY => [
                    IndexController::FLASH_BAG_REQUEST_ERROR_MESSAGE_INVALID_ADMIN_CREDENTIALS,
                ],
            ],
            $session->getFlashBag()->peekAll()
        );
    }


    /**
     * @dataProvider requestActionSendConfirmationTokenFailureDataProvider
     *
     * @param ResponseInterface $postmarkHttpResponse
     * @param array $expectedFlashBagValues
     *
     * @throws CoreApplicationRequestException
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     * @throws MailConfigurationException
     */
    public function testRequestActionSendConfirmationTokenFailure(
        ResponseInterface $postmarkHttpResponse,
        array $expectedFlashBagValues
    ) {
        $session = self::$container->get('session');
        $httpHistoryContainer = self::$container->get(HttpHistoryContainer::class);
        $postmarkMessageVerifier = self::$container->get(PostmarkMessageVerifier::class);

        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createSuccessResponse(),
            HttpResponseFactory::createJsonResponse(self::CONFIRMATION_TOKEN),
            $postmarkHttpResponse,
        ]);

        $request = new Request([], [
            'email' => self::EMAIL,
        ]);

        /* @var RedirectResponse $response */
        $response = $this->indexController->requestAction($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/reset-password/?email=user%40example.com', $response->getTargetUrl());
        $this->assertEquals($expectedFlashBagValues, $session->getFlashBag()->peekAll());

        $postmarkRequest = $httpHistoryContainer->getLastRequest();

        $isPostmarkMessageResult = $postmarkMessageVerifier->isPostmarkRequest($postmarkRequest);
        $this->assertTrue($isPostmarkMessageResult, $isPostmarkMessageResult);
    }

    /**
     * @return array
     */
    public function requestActionSendConfirmationTokenFailureDataProvider()
    {
        return [
            'postmark not allowed to send to user email' => [
                'postmarkHttpResponse' => PostmarkHttpResponseFactory::createErrorResponse(405),
                'expectedFlashBagValues' => [
                    IndexController::FLASH_BAG_REQUEST_ERROR_KEY => [
                        IndexController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_NOT_ALLOWED_TO_SEND,
                    ]
                ],
            ],
            'postmark inactive recipient' => [
                'postmarkHttpResponse' => PostmarkHttpResponseFactory::createErrorResponse(406),
                'expectedFlashBagValues' => [
                    IndexController::FLASH_BAG_REQUEST_ERROR_KEY => [
                        IndexController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_INACTIVE_RECIPIENT
                    ]
                ],
            ],
            'postmark invalid email' => [
                'postmarkHttpResponse' => PostmarkHttpResponseFactory::createErrorResponse(300),
                'expectedFlashBagValues' => [
                    IndexController::FLASH_BAG_REQUEST_ERROR_KEY => [
                        IndexController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_INVALID_EMAIL,
                    ]
                ],
            ],
            'postmark unknown error' => [
                'postmarkHttpResponse' => PostmarkHttpResponseFactory::createErrorResponse(303),
                'expectedFlashBagValues' => [
                    IndexController::FLASH_BAG_REQUEST_ERROR_KEY => [
                        IndexController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_UNKNOWN,
                    ]
                ],
            ],
        ];
    }

    public function testRequestActionMessageConfirmationUrl()
    {
        $httpHistoryContainer = self::$container->get(HttpHistoryContainer::class);
        $postmarkMessageVerifier = self::$container->get(PostmarkMessageVerifier::class);

        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createSuccessResponse(),
            HttpResponseFactory::createJsonResponse(self::CONFIRMATION_TOKEN),
            PostmarkHttpResponseFactory::createSuccessResponse(),
        ]);

        $this->indexController->requestAction(new Request([], [
            'email' => self::EMAIL,
        ]));

        $postmarkRequest = $httpHistoryContainer->getLastRequest();

        $isPostmarkMessageResult = $postmarkMessageVerifier->isPostmarkRequest($postmarkRequest);
        $this->assertTrue($isPostmarkMessageResult, $isPostmarkMessageResult);

        $verificationResult = $postmarkMessageVerifier->verify(
            [
                'From' => 'robot@simplytestable.com',
                'To' => self::EMAIL,
                'Subject' => '[Simply Testable] Reset your password',
                'TextBody' => [
                    sprintf(
                        'http://localhost/reset-password/%s/%s/',
                        self::EMAIL,
                        self::CONFIRMATION_TOKEN
                    ),
                ],
            ],
            $postmarkRequest
        );

        $this->assertTrue($verificationResult, $verificationResult);
    }

    public function testResendActionSuccess()
    {
        $session = self::$container->get('session');

        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createSuccessResponse(),
            HttpResponseFactory::createJsonResponse(self::CONFIRMATION_TOKEN),
            PostmarkHttpResponseFactory::createSuccessResponse(),
        ]);

        $request = new Request([], [
            'email' => self::EMAIL,
        ]);

        /* @var RedirectResponse $response */
        $response = $this->indexController->requestAction($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/reset-password/?email=user%40example.com', $response->getTargetUrl());
        $this->assertEquals(
            [
                IndexController::FLASH_BAG_REQUEST_SUCCESS_KEY => [
                    IndexController::FLASH_BAG_REQUEST_MESSAGE_SUCCESS,
                ]
            ],
            $session->getFlashBag()->peekAll()
        );
    }
}
