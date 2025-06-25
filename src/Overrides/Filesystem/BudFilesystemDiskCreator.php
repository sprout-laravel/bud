<?php
declare(strict_types=1);

namespace Sprout\Bud\Overrides\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Sprout\Bud\Bud;
use Sprout\Bud\Overrides\BaseCreator;
use Sprout\Sprout;

/**
 * Bud Database Connection Creator
 *
 * This class is an abstraction for the logic that creates a database connection
 * using a config store within Bud.
 */
final class BudFilesystemDiskCreator extends BaseCreator
{
    private FilesystemManager $manager;

    private Bud $bud;

    private Sprout $sprout;

    private string $name;

    /**
     * @var array<string, mixed>&array{budStore?:string|null}
     */
    private array $config;

    /**
     * @param \Illuminate\Filesystem\FilesystemManager          $manager
     * @param \Sprout\Bud\Bud                                   $bud
     * @param \Sprout\Sprout                                    $sprout
     * @param string                                            $name
     * @param array<string, mixed>&array{budStore?:string|null} $config
     */
    public function __construct(
        FilesystemManager $manager,
        Bud               $bud,
        Sprout            $sprout,
        string            $name,
        array             $config
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
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     *
     * @throws \Sprout\Exceptions\MisconfigurationException
     * @throws \Sprout\Exceptions\TenancyMissingException
     * @throws \Sprout\Exceptions\TenantMissingException
     */
    public function __invoke(): Filesystem
    {
        /** @var array<string, mixed>&array{driver?:string|null} $config */
        $config = $this->getConfig($this->sprout, $this->bud, $this->config, $this->name);

        // We need to make sure that this isn't going to recurse infinitely.
        $this->checkForCyclicDrivers(
            $config['driver'] ?? null,
            'filesystem disk',
            $this->name
        );

        return $this->manager->build(
            array_merge([
                'name' => $this->name,
            ], $config)
        );
    }

    /**
     * Get the name of the service for the creator.
     *
     * @return string
     */
    protected function getService(): string
    {
        return 'filesystem';
    }
}
