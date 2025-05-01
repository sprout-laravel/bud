<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Mail\MailManager;
use RuntimeException;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\Mailer\BudMailerTransportCreator;
use Sprout\Contracts\BootableServiceOverride;
use Sprout\Contracts\Tenancy;
use Sprout\Contracts\Tenant;
use Sprout\Overrides\BaseOverride;
use Sprout\Sprout;

/**
 * Mailer Override
 *
 * This override specifically allows for the creation of mailers
 * using Bud config stores.
 */
final class MailerOverride extends BaseOverride implements BootableServiceOverride
{
    /**
     * @var list<string>
     */
    protected array $mailers = [];

    /**
     * Get the resolved Bud mailers.
     *
     * @return list<string>
     */
    public function getMailers(): array
    {
        return $this->mailers;
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
        $tracker = fn (string $mailer) => $this->mailers[] = $mailer;

        if ($app->resolved('mail.manager')) {
            $this->addDriver($app->make('mail.manager'), $app->make(Bud::class), $sprout, $tracker);
        } else {
            $app->afterResolving('mail.manager', function (MailManager $mail, Application $app) use ($sprout, $tracker) {
                $this->addDriver($mail, $app->make(Bud::class), $sprout, $tracker);
            });
        }
    }

    private function addDriver(MailManager $mail, Bud $bud, Sprout $sprout, Closure $tracker): void
    {
        // Add a bud driver.
        $mail->extend('bud', function ($config) use ($mail, $bud, $sprout, $tracker) {
            if (! isset($config['name'])) {
                throw new RuntimeException('Cannot create a mailer using bud without a name');
            }

            // Track the mailer name.
            $tracker($config['name']);

            return (new BudMailerTransportCreator($mail, $bud, $sprout, $config['name'], $config))();
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
     * @phpstan-param Tenant                         $tenant
     *
     * @return void
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function cleanup(Tenancy $tenancy, Tenant $tenant): void
    {
        // If the mail manager was resolved, and we resolved bud-specific
        // mailers, we'll tidy up by purging them.
        if ($this->getApp()->resolved('mail.manager')) {
            $mailers = $this->getMailers();

            if (! empty($mailers)) {
                /** @var \Illuminate\Mail\MailManager $manager */
                $manager = $this->getApp()->make('mail.manager');

                foreach ($mailers as $mailer) {
                    $manager->purge($mailer);
                }
            }
        }
    }


}
