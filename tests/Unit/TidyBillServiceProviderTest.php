<?php

namespace ApexTechnology\TidyBill\Tests\Unit;

use ApexTechnology\TidyBill\Tests\TestCase;
use ApexTechnology\TidyBill\TidyBillClient;
use ApexTechnology\TidyBill\TidyBillServiceProvider;
use PHPUnit\Framework\Attributes\Test;

class TidyBillServiceProviderTest extends TestCase
{
    #[Test]
    public function registers_tidybill_client_as_singleton(): void
    {
        $client1 = app(TidyBillClient::class);
        $client2 = app(TidyBillClient::class);

        $this->assertInstanceOf(TidyBillClient::class, $client1);
        $this->assertSame($client1, $client2);
    }

    #[Test]
    public function merges_config_from_package(): void
    {
        $config = config('tidybill');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('token', $config);
        $this->assertArrayHasKey('company_id', $config);
        $this->assertArrayHasKey('base_url', $config);
    }

    #[Test]
    public function config_values_reflect_environment_overrides(): void
    {
        $this->assertSame('test-token', config('tidybill.token'));
        $this->assertSame('test-company', config('tidybill.company_id'));
        $this->assertSame('https://tidybill.test', config('tidybill.base_url'));
    }

    #[Test]
    public function service_provider_is_registered(): void
    {
        // The provider registers the singleton — verify the binding exists.
        $this->assertTrue($this->app->bound(TidyBillClient::class));
    }

    #[Test]
    public function publishes_config_under_tidybill_config_tag(): void
    {
        $publishes = TidyBillServiceProvider::pathsToPublish(
            TidyBillServiceProvider::class,
            'tidybill-config',
        );

        $this->assertNotEmpty($publishes);

        // The published source key is the resolved path from inside the provider.
        // We match by filename rather than full path to avoid symlink resolution issues.
        $sourceKeys = array_keys($publishes);
        $matched = array_filter($sourceKeys, fn ($p) => str_ends_with($p, 'config/tidybill.php'));

        $this->assertNotEmpty($matched, 'Expected config/tidybill.php in published paths. Got: ' . implode(', ', $sourceKeys));
    }
}
