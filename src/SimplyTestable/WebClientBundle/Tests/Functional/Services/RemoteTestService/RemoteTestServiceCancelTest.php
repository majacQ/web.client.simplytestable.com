<?php

namespace SimplyTestable\WebClientBundle\Tests\Functional\Services\RemoteTestService;

use SimplyTestable\WebClientBundle\Entity\Test\Test;
use SimplyTestable\WebClientBundle\Tests\Factory\HttpResponseFactory;
use webignition\NormalisedUrl\NormalisedUrl;

class RemoteTestServiceCancelTest extends AbstractRemoteTestServiceTest
{
    /**
     * @var Test
     */
    private $test;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->test = new Test();
        $this->test->setTestId(1);
        $this->test->setWebsite(new NormalisedUrl('http://example.com/'));

        $this->setRemoteTestServiceTest($this->test);

        $this->setCoreApplicationHttpClientHttpFixtures([
            HttpResponseFactory::createSuccessResponse(),
        ]);
    }

    public function testCancel()
    {
        $this->remoteTestService->cancel();

        $this->assertEquals('http://null/job/http%3A%2F%2Fexample.com%2F/1/cancel/', $this->getLastRequest()->getUrl());
    }

    public function testCancelByTestProperties()
    {
        $this->remoteTestService->cancelByTestProperties(2, 'http://foo.example.com');

        $this->assertEquals(
            'http://null/job/http%3A%2F%2Ffoo.example.com/2/cancel/',
            $this->getLastRequest()->getUrl()
        );
    }
}
