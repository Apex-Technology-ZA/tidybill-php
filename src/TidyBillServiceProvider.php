<?php

namespace ApexTechnology\TidyBill;

use Illuminate\Support\ServiceProvider;

class TidyBillServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/tidybill.php', 'tidybill');

        $this->app->singleton(TidyBillClient::class, function ($app): TidyBillClient {
            $config = $app['config']['tidybill'];

            return new TidyBillClient(
                token: $config['token'],
                companyId: $config['company_id'],
                baseUrl: $config['base_url'],
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/tidybill.php' => config_path('tidybill.php'),
        ], 'tidybill-config');
    }
}
