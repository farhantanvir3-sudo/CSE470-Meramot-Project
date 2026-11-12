<?php

namespace App\Providers;

use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind native PDO::PARAM_BOOL on PostgreSQL so boolean attributes (`is_active => false`, etc.)
        // never fail strict PostgreSQL 18 type checking across PHP 8.2.x / Neon configurations.
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new class($connection, $database, $prefix, $config) extends PostgresConnection {
                public function prepareBindings(array $bindings)
                {
                    $grammar = $this->getQueryGrammar();

                    foreach ($bindings as $key => $value) {
                        if ($value instanceof DateTimeInterface) {
                            $bindings[$key] = $value->format($grammar->getDateFormat());
                        } elseif (is_bool($value)) {
                            $bindings[$key] = $value;
                        }
                    }

                    return $bindings;
                }

                public function bindValues($statement, $bindings)
                {
                    foreach ($bindings as $key => $value) {
                        $statement->bindValue(
                            is_string($key) ? $key : $key + 1,
                            $value,
                            match (true) {
                                is_bool($value) => PDO::PARAM_BOOL,
                                is_int($value) => PDO::PARAM_INT,
                                is_resource($value) => PDO::PARAM_LOB,
                                default => PDO::PARAM_STR,
                            },
                        );
                    }
                }
            };
        });

        // Windows PHP bundles an older libpq.dll without TLS SNI support, requiring
        // options='endpoint=...' on *.neon.tech. Linux (Render Docker) libpq sends SNI
        // natively and rejects mismatched endpoint options on -pooler hosts.
        if (PHP_OS_FAMILY === 'Windows') {
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
