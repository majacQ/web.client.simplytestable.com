<?php

namespace SimplyTestable\WebClientBundle\Tests\EventListener\Stripe\OnCustomerSubscriptionUpdated\PlanChange\Active;

abstract class CurrencyTest extends ListenerTest {

    abstract protected function getExpectedCurrencySymbol();

    public function testNotificationMessageContainsCurrencySymbolAndFormattedAmount() {
        $this->assertNotificationMessageContains($this->getExpectedCurrencySymbol() . $this->getExpectedFormattedAmount());
    }

}