<?php

namespace ApexTechnology\TidyBill\Tests;

use ApexTechnology\TidyBill\TidyBillServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [TidyBillServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('tidybill.token', 'test-token');
        $app['config']->set('tidybill.company_id', 'test-company');
        $app['config']->set('tidybill.base_url', 'https://tidybill.test');
    }
}
