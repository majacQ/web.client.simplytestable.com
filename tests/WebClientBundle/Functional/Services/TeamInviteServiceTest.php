<?php

namespace Tests\WebClientBundle\Functional\Services\TeamInvite;

use SimplyTestable\WebClientBundle\Exception\CoreApplicationReadOnlyException;
use SimplyTestable\WebClientBundle\Exception\CoreApplicationRequestException;
use SimplyTestable\WebClientBundle\Exception\InvalidAdminCredentialsException;
use SimplyTestable\WebClientBundle\Exception\InvalidContentTypeException;
use SimplyTestable\WebClientBundle\Exception\InvalidCredentialsException;
use SimplyTestable\WebClientBundle\Model\Team\Invite;
use SimplyTestable\WebClientBundle\Services\TeamInviteService;
use Tests\WebClientBundle\Factory\ConnectExceptionFactory;
use Tests\WebClientBundle\Factory\HttpResponseFactory;
use SimplyTestable\WebClientBundle\Exception\Team\Service\Exception as TeamServiceException;
use Tests\WebClientBundle\Functional\Services\AbstractCoreApplicationServiceTest;

class TeamInviteServiceTest extends AbstractCoreApplicationServiceTest
{
    const TEAM_NAME = 'Team Name';
    const TOKEN = 'TokenValue';
    const USERNAME = 'user@example.com';

    /**
     * @var TeamInviteService
     */
    private $teamInviteService;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->teamInviteService = self::$container->get(TeamInviteService::class);
    }

    /**
     * @dataProvider getRemoteFailureDataProvider
     *
     * @param array $httpFixtures
     * @param string $expectedException
     * @param string $expectedExceptionMessage
     * @param string $expectedExceptionCode
     *
     * @throws CoreApplicationRequestException
     * @throws TeamServiceException
     * @throws InvalidContentTypeException
     * @throws InvalidCredentialsException
     */
    public function testGetRemoteFailure(
        array $httpFixtures,
        $expectedException,
        $expectedExceptionMessage,
        $expectedExceptionCode
    ) {
        $this->httpMockHandler->appendFixtures($httpFixtures);

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);
        $this->expectExceptionCode($expectedExceptionCode);

        $this->teamInviteService->get(self::USERNAME);
    }

    /**
     * @return array
     */
    public function getRemoteFailureDataProvider()
    {
        $internalServerErrorResponse = HttpResponseFactory::createInternalServerErrorResponse();
        $curlTimeoutConnectException = ConnectExceptionFactory::create('CURL/28 Operation timed out');

        return [
            'HTTP 404' => [
                'httpFixtures' => [
                    HttpResponseFactory::createNotFoundResponse(),
                ],
                'expectedException' => CoreApplicationRequestException::class,
                'expectedExceptionMessage' => 'Not Found',
                'expectedExceptionCode' => 404,
            ],
            'HTTP 500' => [
                'httpFixtures' => [
                    $internalServerErrorResponse,
                    $internalServerErrorResponse,
                    $internalServerErrorResponse,
                    $internalServerErrorResponse,
                    $internalServerErrorResponse,
                    $internalServerErrorResponse,
                ],
                'expectedException' => CoreApplicationRequestException::class,
                'expectedExceptionMessage' => 'Internal Server Error',
                'expectedExceptionCode' => 500,
            ],
            'CURL 28' => [
                'httpFixtures' => [
                    $curlTimeoutConnectException,
                    $curlTimeoutConnectException,
                    $curlTimeoutConnectException,
                    $curlTimeoutConnectException,
                    $curlTimeoutConnectException,
                    $curlTimeoutConnectException,
                ],
                'expectedException' => CoreApplicationRequestException::class,
                'expectedExceptionMessage' => 'Operation timed out',
                'expectedExceptionCode' => 28,
            ],
            'Application-level error' => [
                'httpFixtures' => [
                    HttpResponseFactory::createBadRequestResponse([
                        'X-TeamInviteGet-Error-Code' => 1,
                        'X-TeamInviteGet-Error-Message' => 'foo',
                    ]),
                ],
                'expectedException' => TeamServiceException::class,
                'expectedExceptionMessage' => 'foo',
                'expectedExceptionCode' => 1,
            ],
        ];
    }

    public function testGetSuccess()
    {
        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createJsonResponse([
                'team' => self::TEAM_NAME,
                'user' => self::USERNAME,
                'token' => self::TOKEN,
            ]),
        ]);

        $invite = $this->teamInviteService->get(self::USERNAME);

        $this->assertInstanceOf(Invite::class, $invite);

        $this->assertEquals(self::TEAM_NAME, $invite->getTeam());
        $this->assertEquals(self::TOKEN, $invite->getToken());
        $this->assertEquals(self::USERNAME, $invite->getUser());
        $this->assertEquals(
            'http://null/team/invite/user@example.com/',
            $this->httpHistory->getLastRequestUrl()
        );
    }

    public function testGetForUserSuccess()
    {
        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createJsonResponse([
                [
                    'team' => self::TEAM_NAME,
                    'user' => self::USERNAME,
                    'token' => self::TOKEN,
                ],
            ]),
        ]);

        $invites = $this->teamInviteService->getForUser();

        $this->assertInternalType('array', $invites);
        $invite = $invites[0];

        $this->assertInstanceOf(Invite::class, $invite);

        $this->assertEquals(self::TEAM_NAME, $invite->getTeam());
        $this->assertEquals(self::TOKEN, $invite->getToken());
        $this->assertEquals(self::USERNAME, $invite->getUser());
        $this->assertEquals('http://null/team/user/invites/', $this->httpHistory->getLastRequestUrl());
    }

    /**
     * @dataProvider booleanResponseDataProvider
     *
     * @param array $httpFixtures
     * @param bool $expectedReturnValue
     *
     * @throws CoreApplicationReadOnlyException
     * @throws InvalidCredentialsException
     */
    public function testDeclineInvite(array $httpFixtures, $expectedReturnValue)
    {
        $this->httpMockHandler->appendFixtures($httpFixtures);

        $invite = new Invite([
            'team' => self::TEAM_NAME,
        ]);

        $returnValue = $this->teamInviteService->declineInvite($invite);

        $this->assertEquals($expectedReturnValue, $returnValue);

        $lastRequest = $this->httpHistory->getLastRequest();

        $postedData = [];
        parse_str($lastRequest->getBody()->getContents(), $postedData);

        $this->assertEquals('http://null/team/invite/decline/', $lastRequest->getUri());
        $this->assertEquals(self::TEAM_NAME, $postedData['team']);
    }

    /**
     * @dataProvider booleanResponseDataProvider
     *
     * @param array $httpFixtures
     * @param bool $expectedReturnValue
     *
     * @throws InvalidCredentialsException
     * @throws CoreApplicationReadOnlyException
     */
    public function testAcceptInvite(array $httpFixtures, $expectedReturnValue)
    {
        $this->httpMockHandler->appendFixtures($httpFixtures);

        $invite = new Invite([
            'team' => self::TEAM_NAME,
        ]);

        $returnValue = $this->teamInviteService->acceptInvite($invite);

        $this->assertEquals($expectedReturnValue, $returnValue);

        $lastRequest = $this->httpHistory->getLastRequest();

        $postedData = [];
        parse_str($lastRequest->getBody()->getContents(), $postedData);

        $this->assertEquals('http://null/team/invite/accept/', $lastRequest->getUri());
        $this->assertEquals(self::TEAM_NAME, $postedData['team']);
    }

    public function testGetForTeamSuccess()
    {
        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createJsonResponse([
                [
                    'team' => self::TEAM_NAME,
                    'user' => self::USERNAME,
                    'token' => self::TOKEN,
                ],
            ]),
        ]);

        $invites = $this->teamInviteService->getForTeam();

        $this->assertInternalType('array', $invites);
        $invite = $invites[0];

        $this->assertInstanceOf(Invite::class, $invite);

        $this->assertEquals(self::TEAM_NAME, $invite->getTeam());
        $this->assertEquals(self::TOKEN, $invite->getToken());
        $this->assertEquals(self::USERNAME, $invite->getUser());
        $this->assertEquals('http://null/team/invites/', $this->httpHistory->getLastRequestUrl());
    }

    /**
     * @dataProvider booleanResponseDataProvider
     *
     * @param array $httpFixtures
     * @param bool $expectedReturnValue
     *
     * @throws CoreApplicationReadOnlyException
     * @throws InvalidCredentialsException
     */
    public function testRemoveForUser(array $httpFixtures, $expectedReturnValue)
    {
        $this->httpMockHandler->appendFixtures($httpFixtures);

        $invite = new Invite([
            'team' => self::TEAM_NAME,
            'user' => self::USERNAME,
        ]);

        $returnValue = $this->teamInviteService->removeForUser($invite);

        $this->assertEquals($expectedReturnValue, $returnValue);
        $this->assertEquals(
            'http://null/team/invite/user@example.com/remove/',
            $this->httpHistory->getLastRequestUrl()
        );
    }

    /**
     * @dataProvider getForTokenDataProvider
     *
     * @param array $httpFixtures
     * @param Invite|null $expectedReturnValue
     *
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     */
    public function testGetForTokenSuccess(array $httpFixtures, $expectedReturnValue)
    {
        $this->httpMockHandler->appendFixtures($httpFixtures);

        $invite = $this->teamInviteService->getForToken(self::TOKEN);

        $this->assertEquals($expectedReturnValue, $invite);
    }

    /**
     * @return array
     */
    public function getForTokenDataProvider()
    {
        return [
            'valid response data' => [
                'httpFixtures' => [
                    HttpResponseFactory::createJsonResponse([
                        'team' => self::TEAM_NAME,
                        'user' => self::USERNAME,
                        'token' => self::TOKEN,
                    ]),
                ],
                'expectedReturnValue' => new Invite([
                    'team' => self::TEAM_NAME,
                    'user' => self::USERNAME,
                    'token' => self::TOKEN,
                ]),
            ],
            'invalid response data' => [
                'httpFixtures' => [
                    HttpResponseFactory::createNotFoundResponse(),
                ],
                'expectedReturnValue' => null,
            ],
        ];
    }

    public function testGetForTokenInvalidAdminCredentials()
    {
        $this->httpMockHandler->appendFixtures([
            HttpResponseFactory::createForbiddenResponse(),
        ]);

        $this->expectException(InvalidAdminCredentialsException::class);

        $this->teamInviteService->getForToken(self::TOKEN);
    }

    /**
     * @return array
     */
    public function booleanResponseDataProvider()
    {
        return [
            'failure' => [
                'httpFixtures' => [
                    HttpResponseFactory::createBadRequestResponse(),
                ],
                'expectedReturnValue' => false,
            ],
            'success' => [
                'httpFixtures' => [
                    HttpResponseFactory::createSuccessResponse(),
                ],
                'expectedReturnValue' => true,
            ],
        ];
    }
}
