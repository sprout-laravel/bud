<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides;

use RuntimeException;
use Sprout\Bud\Bud;
use Sprout\Bud\Exceptions\CyclicOverrideException;
use Sprout\Exceptions\TenancyMissingException;
use Sprout\Exceptions\TenantMissingException;
use Sprout\Sprout;

abstract class BaseCreator
{
    /**
     * @param \Sprout\Sprout                                    $sprout
     * @param \Sprout\Bud\Bud                                   $bud
     * @param array<string, mixed>&array{budStore?:string|null} $config
     * @param string                                            $name
     *
     * @return array<string, mixed>
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function getConfig(Sprout $sprout, Bud $bud, array $config, string $name): array
    {
        // If we're not within a multitenanted context, we need to error
        // out, as this driver shouldn't be hit without one
        if (! $sprout->withinContext()) {
            throw TenancyMissingException::make();
        }

        // Get the current active tenancy
        $tenancy = $sprout->getCurrentTenancy();

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
        $store = $bud->store($config['budStore'] ?? null);

        // Get the config for the connection from the store.
        $budConfig = $store->get(
            $tenancy,
            $tenant,
            'database',
            $name,
        );

        // If there isn't any config, it's an error.
        if ($budConfig === null) {
            // TODO: Throw a better exception
            throw new RuntimeException(sprintf(
                'Unable to find database configuration for connection [%s] for tenant [%s] on tenancy [%s]',
                $name,
                $tenant->getTenantIdentifier(),
                $tenancy->getName()
            ));
        }

        return array_merge($config, $budConfig);
    }

    /**
     * Check if the driver is cyclic.
     *
     * @param string|null $driver
     * @param string      $term
     * @param string      $name
     *
     * @return void
     *
     */
    protected function checkForCyclicDrivers(?string $driver, string $term, string $name): void
    {
        if ($driver === 'bud') {
            throw CyclicOverrideException::make($term, $name);
        }
    }
}
