<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\Database\BudDatabaseConnectionCreator;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\BaseOverride;
use Sprout\Sprout;

/**
 * Database Connection Override
 *
 * This override specifically allows for the creation of database connections
 * using Bud config store.
 */
final class DatabaseConnectionOverride extends BaseOverride implements BootableServiceOverride
{
    /**
     * @var list<string>
     */
    protected array $connections = [];

    /**
     * Get the resolved Bud connections.
     *
     * @return list<string>
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Boot a service override
     *
     * This method should perform any initial steps required for the service
     * override that take place during the booting of the framework.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Sprout\Sprout                               $sprout
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function boot(Application $app, Sprout $sprout): void
    {
        $tracker = fn (string $connection) => $this->connections[] = $connection;

        if ($app->resolved('db')) {
            $this->addDriver($app->make('db'), $app->make(Bud::class), $sprout, $tracker);
        } else {
            $app->afterResolving('db', function (DatabaseManager $db, Application $app) use ($sprout, $tracker) {
                $this->addDriver($db, $app->make(Bud::class), $sprout, $tracker);
            });
        }
    }

    private function addDriver(DatabaseManager $db, Bud $bud, Sprout $sprout, \Closure $tracker): void
    {
        // Add a bud driver.
        $db->extend('bud', function ($config, $name) use ($db, $bud, $sprout, $tracker) {
            // Track the connection name.
            $tracker($name);

            return (new BudDatabaseConnectionCreator($db, $bud, $sprout, $name, $config))();
        });
    }

    /**
     * Clean up the service override
     *
     * This method should perform any necessary setup actions for the service
     * override.
     * It is called when the current tenant is unset, either to be replaced
     * by another tenant, or none.
     *
     * It will be called before {@see self::setup()}, but only if the previous
     * tenant was not null.
     *
     * @template TenantClass of \Sprout\Contracts\Tenant
     *
     * @param \Sprout\Contracts\Tenancy<TenantClass> $tenancy
     * @param \Sprout\Contracts\Tenant               $tenant
     *
     * @phpstan-param TenantClass                    $tenant
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        // If the database manager was resolved, and we resolved bud-specific
        // connections, we'll tidy up by purging them.
        if ($this->getApp()->resolved('db')) {
            $connections = $this->getConnections();

            if (! empty($connections)) {
                /** @var \Illuminate\Database\DatabaseManager $manager */
                $manager = $this->getApp()->make('db');

                foreach ($connections as $connection) {
                    $manager->purge($connection);
                }
            }
        }
    }


}
