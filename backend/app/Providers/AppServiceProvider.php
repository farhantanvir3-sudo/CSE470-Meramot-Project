<?php

namespace App\Providers;

use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Automatically attach Neon's endpoint ID option when connecting to *.neon.tech
        // so both SNI and non-SNI libpq clients connect seamlessly to pooled & direct endpoints.
        $this->app->bind('db.connector.pgsql', function () {
            return new class extends PostgresConnector {
                protected function getDsn(array $config)
                {
                    $dsn = parent::getDsn($config);
                    $host = (string) ($config['host'] ?? '');

                    if (
                        str_contains($host, '.neon.tech') &&
                        preg_match('/^(ep-[a-z0-9-]+?)(?:-pooler)?\./i', $host, $matches)
                    ) {
                        $dsn .= ";options='endpoint={$matches[1]}'";
                    }

                    return $dsn;
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (
            $this->app->environment('production') ||
            request()->header('X-Forwarded-Proto') === 'https'
        ) {
            URL::forceScheme('https');
        }
    }
}
