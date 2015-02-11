<?php

namespace SimplyTestable\WebClientBundle\Tests\EventListener\Stripe\OnInvoicePaymentSucceeded\SubscriptionWithoutDiscount;

class CurrencyGbpTest extends ListenerTest {

    protected function getCurrency() {
        return 'gbp';
    }

    protected function getExpectedCurrencySymbol() {
        return '£';
    }
}