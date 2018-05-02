<?php

namespace Tests\WebClientBundle\Functional\Controller\Action\User\Account\EmailChange;

use SimplyTestable\WebClientBundle\Controller\Action\User\Account\EmailChangeController;
use SimplyTestable\WebClientBundle\Exception\CoreApplicationRequestException;
use SimplyTestable\WebClientBundle\Exception\InvalidAdminCredentialsException;
use SimplyTestable\WebClientBundle\Exception\InvalidContentTypeException;
use SimplyTestable\WebClientBundle\Exception\InvalidCredentialsException;
use SimplyTestable\WebClientBundle\Services\UserManager;
use Tests\WebClientBundle\Factory\HttpResponseFactory;
use Tests\WebClientBundle\Factory\MockPostmarkMessageFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use MZ\PostmarkBundle\Postmark\Message as PostmarkMessage;
use SimplyTestable\WebClientBundle\Exception\Mail\Configuration\Exception as MailConfigurationException;
use SimplyTestable\WebClientBundle\Services\Mail\Service as MailService;
use Tests\WebClientBundle\Helper\MockeryArgumentValidator;
use webignition\SimplyTestableUserModel\User;

class EmailChangeControllerRequestActionTest extends AbstractEmailChangeControllerTest
{
    const ROUTE_NAME = 'action_user_account_emailchange_request';
    const NEW_EMAIL = 'new-email@example.com';
    const CONFIRMATION_TOKEN = 'email-change-request-token';
    const EXPECTED_REDIRECT_URL = '/account/';

    /**
     * @var User
     */
    private $user;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->user = new User('user@example.com');
    }

    /**
     * {@inheritdoc}
     */
    public function postRequestPublicUserDataProvider()
    {
        return [
            'default' => [
                'routeName' => self::ROUTE_NAME,
            ],
        ];
    }

    public function testRequestActionPostRequestPrivateUser()
    {
        $mailService = $this->container->get(MailService::class);
        $userManager = $this->container->get(UserManager::class);

        $userManager->setUser(new User('user@example.com'));

        $mailService->setPostmarkMessage(MockPostmarkMessageFactory::createMockConfirmEmailAddressPostmarkMessage(
            self::NEW_EMAIL,
            [
                'ErrorCode' => 0,
                'Message' => 'OK',
            ]
        ));

        $this->setCoreApplicationHttpClientHttpFixtures([
            HttpResponseFactory::createJsonResponse([
                'token' => self::CONFIRMATION_TOKEN,
                'new_email' => self::NEW_EMAIL,
            ]),
            HttpResponseFactory::createSuccessResponse(),
        ]);

        $this->client->request(
            'POST',
            $this->createRequestUrl(self::ROUTE_NAME)
        );

        /* @var RedirectResponse $response */
        $response = $this->client->getResponse();

        $this->assertEquals(
            self::EXPECTED_REDIRECT_URL,
            $response->getTargetUrl()
        );
    }

    /**
     * @dataProvider requestActionBadRequestDataProvider
     *
     * @param Request $request
     * @param array $expectedFlashBagValues
     *
     * @throws InvalidAdminCredentialsException
     * @throws InvalidCredentialsException
     * @throws MailConfigurationException
     * @throws CoreApplicationRequestException
     * @throws InvalidContentTypeException
     */
    public function testRequestActionBadRequest(Request $request, array $expectedFlashBagValues)
    {
        $session = $this->container->get('session');
        $userManager = $this->container->get(UserManager::class);

        $userManager->setUser($this->user);

        $response = $this->callRequestAction($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(self::EXPECTED_REDIRECT_URL, $response->getTargetUrl());
        $this->assertEquals($expectedFlashBagValues, $session->getFlashBag()->peekAll());
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
                    EmailChangeController::FLASH_BAG_REQUEST_KEY => [
                        EmailChangeController::FLASH_BAG_REQUEST_ERROR_MESSAGE_EMAIL_EMPTY,
                    ],
                ],
            ],
            'same email' => [
                'request' => new Request([], [
                    'email' => 'user@example.com',
                ]),
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_REQUEST_KEY => [
                        EmailChangeController::FLASH_BAG_REQUEST_MESSAGE_EMAIL_SAME,
                    ],
                ],
            ],
            'invalid email' => [
                'request' => new Request([], [
                    'email' => 'foo',
                ]),
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_REQUEST_KEY => [
                        EmailChangeController::FLASH_BAG_REQUEST_ERROR_MESSAGE_EMAIL_INVALID,
                    ],
                    EmailChangeController::FLASH_BAG_EMAIL_VALUE_KEY => [
                        'foo'
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider requestActionCreateFailureDataProvider
     *
     * @param array $httpFixtures
     * @param array $expectedFlashBagValues
     *
     * @throws CoreApplicationRequestException
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     * @throws InvalidCredentialsException
     * @throws MailConfigurationException
     */
    public function testRequestActionCreateFailure(array $httpFixtures, array $expectedFlashBagValues)
    {
        $session = $this->container->get('session');
        $userManager = $this->container->get(UserManager::class);

        $userManager->setUser($this->user);

        $this->setCoreApplicationHttpClientHttpFixtures($httpFixtures);

        $request = new Request([], [
            'email' => self::NEW_EMAIL,
        ]);

        $response = $this->callRequestAction($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(self::EXPECTED_REDIRECT_URL, $response->getTargetUrl());
        $this->assertEquals($expectedFlashBagValues, $session->getFlashBag()->peekAll());
    }

    /**
     * @return array
     */
    public function requestActionCreateFailureDataProvider()
    {
        return [
            'email taken' => [
                'httpFixtures' => [
                    HttpResponseFactory::createConflictResponse()
                ],
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_REQUEST_KEY => [
                        EmailChangeController::FLASH_BAG_REQUEST_ERROR_MESSAGE_EMAIL_TAKEN,
                    ],
                    EmailChangeController::FLASH_BAG_EMAIL_VALUE_KEY => [
                        self::NEW_EMAIL,
                    ],
                ],
            ],
            'unknown' => [
                'httpFixtures' => [
                    HttpResponseFactory::createInternalServerErrorResponse(),
                ],
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_REQUEST_KEY => [
                        EmailChangeController::FLASH_BAG_REQUEST_ERROR_MESSAGE_UNKNOWN,
                    ],
                    EmailChangeController::FLASH_BAG_EMAIL_VALUE_KEY => [
                        self::NEW_EMAIL,
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider requestActionSendConfirmationTokenFailureDataProvider
     *
     * @param PostmarkMessage $postmarkMessage
     * @param array $expectedFlashBagValues
     *
     * @throws CoreApplicationRequestException
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     * @throws InvalidCredentialsException
     * @throws MailConfigurationException
     */
    public function testRequestActionSendConfirmationTokenFailure(
        PostmarkMessage $postmarkMessage,
        array $expectedFlashBagValues
    ) {
        $session = $this->container->get('session');
        $mailService = $this->container->get(MailService::class);
        $userManager = $this->container->get(UserManager::class);

        $userManager->setUser($this->user);
        $mailService->setPostmarkMessage($postmarkMessage);

        $this->setCoreApplicationHttpClientHttpFixtures([
            HttpResponseFactory::createSuccessResponse(),
            HttpResponseFactory::createJsonResponse([
                'token' => self::CONFIRMATION_TOKEN,
                'new_email' => self::NEW_EMAIL,
            ]),
            HttpResponseFactory::createSuccessResponse(),
        ]);

        $request = new Request([], [
            'email' => self::NEW_EMAIL,
        ]);

        $response = $this->callRequestAction($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(self::EXPECTED_REDIRECT_URL, $response->getTargetUrl());
        $this->assertEquals($expectedFlashBagValues, $session->getFlashBag()->peekAll());
    }

    /**
     * @return array
     */
    public function requestActionSendConfirmationTokenFailureDataProvider()
    {
        return [
            'postmark not allowed to send to user email' => [
                'postmarkMessage' => MockPostmarkMessageFactory::createMockConfirmEmailAddressPostmarkMessage(
                    self::NEW_EMAIL,
                    [
                        'ErrorCode' => 405,
                        'Message' => 'foo',
                    ]
                ),
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_REQUEST_KEY => [
                        EmailChangeController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_NOT_ALLOWED_TO_SEND,
                    ],
                    EmailChangeController::FLASH_BAG_EMAIL_VALUE_KEY => [
                        self::NEW_EMAIL,
                    ],
                ],
            ],
            'postmark inactive recipient' => [
                'postmarkMessage' => MockPostmarkMessageFactory::createMockConfirmEmailAddressPostmarkMessage(
                    self::NEW_EMAIL,
                    [
                        'ErrorCode' => 406,
                        'Message' => 'foo',
                    ]
                ),
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_REQUEST_KEY => [
                        EmailChangeController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_INACTIVE_RECIPIENT,
                    ],
                    EmailChangeController::FLASH_BAG_EMAIL_VALUE_KEY => [
                        self::NEW_EMAIL,
                    ],
                ],
            ],
            'postmark invaild email address' => [
                'postmarkMessage' => MockPostmarkMessageFactory::createMockConfirmEmailAddressPostmarkMessage(
                    self::NEW_EMAIL,
                    [
                        'ErrorCode' => 300,
                        'Message' => 'foo',
                    ]
                ),
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_REQUEST_KEY => [
                        EmailChangeController::FLASH_BAG_REQUEST_ERROR_MESSAGE_EMAIL_INVALID,
                    ],
                    EmailChangeController::FLASH_BAG_EMAIL_VALUE_KEY => [
                        self::NEW_EMAIL,
                    ],
                ],
            ],
            'postmark unknown error' => [
                'postmarkMessage' => MockPostmarkMessageFactory::createMockConfirmEmailAddressPostmarkMessage(
                    self::NEW_EMAIL,
                    [
                        'ErrorCode' => 206,
                        'Message' => 'foo',
                    ]
                ),
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_REQUEST_KEY => [
                        EmailChangeController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_UNKNOWN,
                    ],
                    EmailChangeController::FLASH_BAG_EMAIL_VALUE_KEY => [
                        self::NEW_EMAIL,
                    ],
                ],
            ],
        ];
    }

    public function testRequestActionSuccess()
    {
        $session = $this->container->get('session');
        $mailService = $this->container->get(MailService::class);
        $userManager = $this->container->get(UserManager::class);

        $userManager->setUser($this->user);

        $postmarkMessage = MockPostmarkMessageFactory::createMockPostmarkMessage(
            self::NEW_EMAIL,
            MockPostmarkMessageFactory::SUBJECT_CONFIRM_EMAIL_ADDRESS_CHANGE,
            [
                'ErrorCode' => 0,
                'Message' => 'OK',
            ],
            [
                'with' => \Mockery::on(MockeryArgumentValidator::stringContains([
                    sprintf(
                        'http://localhost/account/?token=%s',
                        self::CONFIRMATION_TOKEN
                    )
                ])),
            ]
        );

        $mailService->setPostmarkMessage($postmarkMessage);

        $this->setCoreApplicationHttpClientHttpFixtures([
            HttpResponseFactory::createSuccessResponse(),
            HttpResponseFactory::createJsonResponse([
                'token' => self::CONFIRMATION_TOKEN,
                'new_email' => self::NEW_EMAIL,
            ]),
        ]);

        $request = new Request([], [
            'email' => self::NEW_EMAIL,
        ]);

        $response = $this->callRequestAction($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(self::EXPECTED_REDIRECT_URL, $response->getTargetUrl());
        $this->assertEquals([
            EmailChangeController::FLASH_BAG_REQUEST_KEY => [
                EmailChangeController::FLASH_BAG_REQUEST_MESSAGE_SUCCESS,
            ],
        ], $session->getFlashBag()->peekAll());
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse
     *
     * @throws CoreApplicationRequestException
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     * @throws InvalidCredentialsException
     * @throws MailConfigurationException
     */
    private function callRequestAction(Request $request)
    {
        return $this->emailChangeController->requestAction(
            $this->container->get(MailService::class),
            $this->container->get('twig'),
            $request
        );
    }
}
