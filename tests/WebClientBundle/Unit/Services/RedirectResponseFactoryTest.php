<?php

namespace Tests\WebClientBundle\Unit\Services;

use Mockery\MockInterface;
use SimplyTestable\WebClientBundle\Request\User\SignInRequest;
use SimplyTestable\WebClientBundle\Services\RedirectResponseFactory;
use Symfony\Component\Routing\RouterInterface;

class RedirectResponseFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider createSignInRedirectResponseDataProvider
     *
     * @param string $email
     * @param string $redirect
     * @param bool $staySignedIn
     * @param array $expectedRouteParameters
     */
    public function testCreateSignInRedirectResponse($email, $redirect, $staySignedIn, array $expectedRouteParameters)
    {
        /* @var MockInterface|RouterInterface $router */
        $router = \Mockery::mock(RouterInterface::class);
        $router
            ->shouldReceive('generate')
            ->withArgs([
                'view_user_signin_index',
                $expectedRouteParameters
            ])
            ->andReturn('http://example.com/');

        $redirectResponseFactory = new RedirectResponseFactory($router);

        $signInRequest = new SignInRequest($email, '', $redirect, $staySignedIn);

        $redirectResponseFactory->createSignInRedirectResponse($signInRequest);
    }

    /**
     * @return array
     */
    public function createSignInRedirectResponseDataProvider()
    {
        return [
            'no email, no redirect, stay-signed-in=false' => [
                'email' => '',
                'redirect' => '',
                'staySignedIn' => false,
                'expectedRouteParameters' => [
                    'stay-signed-in' => 0,
                ],
            ],
            'email, redirect, stay-signed-in=true' => [
                'email' => 'user@example.com',
                'redirect' => 'foo',
                'staySignedIn' => true,
                'expectedRouteParameters' => [
                    'email' => 'user@example.com',
                    'redirect' => 'foo',
                    'stay-signed-in' => 1,
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        parent::tearDown();

        \Mockery::close();
    }
}
