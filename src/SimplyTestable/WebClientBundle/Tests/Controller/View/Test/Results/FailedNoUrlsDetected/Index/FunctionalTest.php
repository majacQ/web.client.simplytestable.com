<?php

namespace SimplyTestable\WebClientBundle\Tests\Controller\View\Test\Results\FailedNoUrlsDetected\Index\IndexAction;

use SimplyTestable\WebClientBundle\Tests\Controller\Base\FunctionalTest as BaseFunctionalTest;

class FunctionalTest extends BaseFunctionalTest {

    const WEBSITE = 'http://example.com/abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz';

    public function setUp() {
        parent::setUp();
        $this->setHttpFixtures($this->getHttpFixtures($this->getFixturesDataPath()));
    }

    protected function getRouteParameters() {
        return array(
            'website' => self::WEBSITE,
            'test_id' => 1
        );
    }

    public function testNavbarContainsSignInButton() {
        $this->assertNavbarContainsSignInButton();
    }

    public function testNavbarSignInButtonUrl() {
        $this->assertNavbarSignInButtonUrl();
    }

    public function testNavbarContainsCreateAccountButton() {
        $this->assertNavbarContainsCreateAccountButton();
    }

    public function testNavbarCreateAccountButtonUrl() {
        $this->assertNavbarCreateAccountButtonUrl();
    }

    public function testLongUrlInPageTitleIsTruncated() {
        $expectedTitleUrl = substr(str_replace('http://', '', self::WEBSITE), 0, 64) . '…';
        $this->assertTitleContainsText($expectedTitleUrl);
    }

}