<?php

namespace Tests\WebClientBundle\Functional\Controller\Action\User\Account\EmailChange;

use Postmark\PostmarkClient;
use Psr\Http\Message\ResponseInterface;
use SimplyTestable\WebClientBundle\Controller\Action\User\Account\EmailChangeController;
use SimplyTestable\WebClientBundle\Exception\CoreApplicationRequestException;
use SimplyTestable\WebClientBundle\Exception\InvalidAdminCredentialsException;
use SimplyTestable\WebClientBundle\Exception\InvalidContentTypeException;
use SimplyTestable\WebClientBundle\Services\Configuration\MailConfiguration;
use SimplyTestable\WebClientBundle\Services\UserManager;
use Tests\WebClientBundle\Factory\HttpResponseFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use SimplyTestable\WebClientBundle\Exception\Mail\Configuration\Exception as MailConfigurationException;
use Tests\WebClientBundle\Factory\PostmarkHttpResponseFactory;
use webignition\SimplyTestableUserModel\User;
use Tests\WebClientBundle\Services\PostmarkMessageVerifier;
use webignition\HttpHistoryContainer\Container as HttpHistoryContainer;

class EmailChangeControllerResendActionTest extends AbstractEmailChangeControllerTest
{
    const ROUTE_NAME = 'action_user_account_emailchange_resend';
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

    public function testResendActionPostRequestPrivateUser()
    {
        $userManager = $this->container->get(UserManager::class);

        $userManager->setUser(new User('user@example.com'));

        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createSuccessResponse(),
            HttpResponseFactory::createJsonResponse([
                'token' => self::CONFIRMATION_TOKEN,
                'new_email' => self::NEW_EMAIL,
            ]),
            PostmarkHttpResponseFactory::createSuccessResponse(),
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
     * @dataProvider resendActionSendConfirmationTokenFailureDataProvider
     *
     * @param ResponseInterface $postmarkHttpResponse
     * @param array $expectedFlashBagValues
     *
     * @throws InvalidAdminCredentialsException
     * @throws MailConfigurationException
     * @throws CoreApplicationRequestException
     * @throws InvalidContentTypeException
     */
    public function testResendActionSendConfirmationTokenFailure(
        ResponseInterface $postmarkHttpResponse,
        array $expectedFlashBagValues
    ) {
        $session = $this->container->get('session');
        $userManager = $this->container->get(UserManager::class);
        $httpHistoryContainer = $this->container->get(HttpHistoryContainer::class);
        $postmarkMessageVerifier = $this->container->get(PostmarkMessageVerifier::class);

        $userManager->setUser($this->user);

        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createJsonResponse([
                'token' => self::CONFIRMATION_TOKEN,
                'new_email' => self::NEW_EMAIL,
            ]),
            $postmarkHttpResponse,
        ]);

        $response = $this->callResendAction();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(self::EXPECTED_REDIRECT_URL, $response->getTargetUrl());
        $this->assertEquals($expectedFlashBagValues, $session->getFlashBag()->peekAll());

        $postmarkRequest = $httpHistoryContainer->getLastRequest();

        $isPostmarkMessageResult = $postmarkMessageVerifier->isPostmarkRequest($postmarkRequest);
        $this->assertTrue($isPostmarkMessageResult, $isPostmarkMessageResult);
    }

    /**
     * @return array
     */
    public function resendActionSendConfirmationTokenFailureDataProvider()
    {
        return [
            'postmark not allowed to send to user email' => [
                'postmarkHttpResponse' => PostmarkHttpResponseFactory::createErrorResponse(405),
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_RESEND_ERROR_KEY => [
                        EmailChangeController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_NOT_ALLOWED_TO_SEND,
                    ],
                ],
            ],
            'postmark inactive recipient' => [
                'postmarkHttpResponse' => PostmarkHttpResponseFactory::createErrorResponse(406),
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_RESEND_ERROR_KEY => [
                        EmailChangeController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_INACTIVE_RECIPIENT,
                    ],
                 ],
            ],
            'postmark invalid email address' => [
                'postmarkHttpResponse' => PostmarkHttpResponseFactory::createErrorResponse(300),
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_RESEND_ERROR_KEY => [
                        EmailChangeController::FLASH_BAG_REQUEST_ERROR_MESSAGE_EMAIL_INVALID,
                    ],
                 ],
            ],
            'postmark unknown error' => [
                'postmarkHttpResponse' => PostmarkHttpResponseFactory::createErrorResponse(206),
                'expectedFlashBagValues' => [
                    EmailChangeController::FLASH_BAG_RESEND_ERROR_KEY => [
                        EmailChangeController::FLASH_BAG_ERROR_MESSAGE_POSTMARK_UNKNOWN,
                    ],
                ],
            ],
        ];
    }

    public function testRequestActionSuccess()
    {
        $session = $this->container->get('session');
        $userManager = $this->container->get(UserManager::class);
        $httpHistoryContainer = $this->container->get(HttpHistoryContainer::class);
        $postmarkMessageVerifier = $this->container->get(PostmarkMessageVerifier::class);

        $userManager->setUser($this->user);

        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createJsonResponse([
                'token' => self::CONFIRMATION_TOKEN,
                'new_email' => self::NEW_EMAIL,
            ]),
            PostmarkHttpResponseFactory::createSuccessResponse(),
        ]);

        $response = $this->callResendAction();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(self::EXPECTED_REDIRECT_URL, $response->getTargetUrl());
        $this->assertEquals([
            EmailChangeController::FLASH_BAG_RESEND_SUCCESS_KEY => [
                EmailChangeController::FLASH_BAG_RESEND_MESSAGE_SUCCESS
            ],
        ], $session->getFlashBag()->peekAll());

        $postmarkRequest = $httpHistoryContainer->getLastRequest();

        $isPostmarkMessageResult = $postmarkMessageVerifier->isPostmarkRequest($postmarkRequest);
        $this->assertTrue($isPostmarkMessageResult, $isPostmarkMessageResult);

        $verificationResult = $postmarkMessageVerifier->verify(
            [
                'From' => 'robot@simplytestable.com',
                'To' => self::NEW_EMAIL,
                'Subject' => '[Simply Testable] Confirm your email address change',
                'TextBody' => [
                    sprintf(
                        'http://localhost/account/?token=%s',
                        self::CONFIRMATION_TOKEN
                    ),
                ],
            ],
            $postmarkRequest
        );

        $this->assertTrue($verificationResult, $verificationResult);
    }

    /**
     * @return RedirectResponse
     *
     * @throws CoreApplicationRequestException
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     * @throws MailConfigurationException
     */
    private function callResendAction()
    {
        return $this->emailChangeController->resendAction(
            $this->container->get(MailConfiguration::class),
            $this->container->get(PostmarkClient::class),
            $this->container->get('twig')
        );
    }
}
