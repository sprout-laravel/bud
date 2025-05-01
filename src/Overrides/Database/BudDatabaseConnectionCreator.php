<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides\Database;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use RuntimeException;
use Sprout\Bud\Bud;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Sprout;

/**
 * Bud Database Connection Creator
 *
 * This class is an abstraction for the logic that creates a database connection
 * using config store within Bud.
 */
final class BudDatabaseConnectionCreator
{
    private DatabaseManager $manager;

    private Bud $bud;

    private Sprout $sprout;

    private string $name;

    /**
     * @var array<string, mixed>&array{budStore?:string|null}
     */
    private array $config;

    /**
     * @param \Illuminate\Database\DatabaseManager              $manager
     * @param \Sprout\Bud\Bud                                   $bud
     * @param \Sprout\Sprout                                    $sprout
     * @param string                                            $name
     * @param array<string, mixed>&array{budStore?:string|null} $config
     */
    public function __construct(
        DatabaseManager $manager,
        Bud             $bud,
        Sprout          $sprout,
        string          $name,
        array           $config
    )
    {
        $this->manager = $manager;
        $this->bud     = $bud;
        $this->sprout  = $sprout;
        $this->name    = $name;
        $this->config  = $config;
    }

    /**
     * Create the connection using Bud.
     *
     * @return \Illuminate\Database\ConnectionInterface
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function __invoke(): ConnectionInterface
    {
        // If we're not within a multitenanted context, we need to error
        // out, as this driver shouldn't be hit without one
        if (! $this->sprout->withinContext()) {
            throw TenancyMissingException::make();
        }

        // Get the current active tenancy
        $tenancy = $this->sprout->getCurrentTenancy();

        // If there isn't one, that's an issue as we need a tenancy
        if ($tenancy === null) {
            throw TenancyMissingException::make();
        }

        // If there is a tenancy, but it doesn't have a tenant, that's also
        // an issue
        if ($tenancy->check() === false) {
            throw TenantMissingException::make($tenancy->getName());
        }

        /** @var \Sprout\Contracts\Tenant $tenant */
        $tenant = $tenancy->tenant();

        // Get the default store, or the one specified in the config, if there
        // is one.
        $store  = $this->bud->store($this->config['budStore'] ?? null);

        // Get the config for the connection from the store.
        $config = $store->get(
            $tenancy,
            $tenant,
            'database',
            $this->name,
        );

        // If there isn't any config, it's an error.
        if ($config === null) {
            // TODO: Throw a better exception
            throw new RuntimeException(sprintf(
                'Unable to find database configuration for connection [%s] for tenant [%s] on tenancy [%s]',
                $this->name,
                $tenant->getTenantIdentifier(),
                $tenancy->getName()
            ));
        }

        // If we're here, it all worked, so we'll create a dynamic connection.
        // We're intentionally not using the methods for creating a dynamic
        // connection because it does funky stuff with the names.
        return $this->manager->connectUsing(
            $this->name,
            array_merge($this->config, $config),
            true // This is important, it needs to be here to avoid side-effect errors.
        );
    }
}
