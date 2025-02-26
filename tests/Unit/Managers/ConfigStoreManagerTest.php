<?php
declare(strict_types=1);

namespace Sprout\Bud\Tests\Unit\Managers;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use Sprout\Bud\Bud;
use Sprout\Bud\Stores\DatabaseConfigStore;
use Sprout\Bud\Stores\FilesystemConfigStore;
use Sprout\Bud\Tests\Unit\UnitTestCase;
use Sprout\Exceptions\MisconfigurationException;
use Sprout\Http\Resolvers\CookieIdentityResolver;
use Sprout\Http\Resolvers\HeaderIdentityResolver;
use Sprout\Http\Resolvers\PathIdentityResolver;
use Sprout\Http\Resolvers\SessionIdentityResolver;
use Sprout\Http\Resolvers\SubdomainIdentityResolver;
use Sprout\Managers\IdentityResolverManager;
use function Sprout\sprout;

class ConfigStoreManagerTest extends UnitTestCase
{
    protected function withoutDefault($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('multitenancy.defaults.config', null);
        });
    }

    protected function withoutConfig($app): void
    {
        tap($app['config'], static function ($config) {
            $config->set('sprout.bud.stores.database', null);
        });
    }

    #[Test]
    public function isNamedCorrectly(): void
    {
        $manager = app(Bud::class)->stores();

        $this->assertSame('config', $manager->getFactoryName());
    }

    #[Test]
    public function getsTheDefaultNameFromTheConfig(): void
    {
        $manager = app(Bud::class)->stores();

        config()->set('multitenancy.defaults.config', 'database');

        $this->assertSame('database', $manager->getDefaultName());

        config()->set('multitenancy.defaults.config', 'filesystem');

        $this->assertSame('filesystem', $manager->getDefaultName());
    }

    #[Test]
    public function generatesConfigKeys(): void
    {
        $manager = app(Bud::class)->stores();

        $this->assertSame('sprout.bud.stores.test-config', $manager->getConfigKey('test-config'));
    }

    #[Test]
    public function hasDefaultFirstPartyDrivers(): void
    {
        $manager = app(Bud::class)->stores();

        $this->assertFalse($manager->hasResolved());

        $this->assertTrue($manager->hasDriver('database'));
        $this->assertTrue($manager->hasDriver('filesystem'));
        $this->assertFalse($manager->hasDriver('fake-driver'));

        $this->assertFalse($manager->hasResolved());

        $this->assertInstanceOf(DatabaseConfigStore::class, $manager->get('database'));
        $this->assertInstanceOf(FilesystemConfigStore::class, $manager->get('filesystem'));

        $this->assertTrue($manager->hasResolved('database'));
        $this->assertTrue($manager->hasResolved('filesystem'));
    }

    #[Test]
    public function canFlushResolvedInstances(): void
    {
        $manager = app(Bud::class)->stores();

        $this->assertFalse($manager->hasResolved());

        $this->assertTrue($manager->hasDriver('database'));
        $this->assertTrue($manager->hasDriver('filesystem'));

        $this->assertFalse($manager->hasResolved());

        $this->assertInstanceOf(DatabaseConfigStore::class, $manager->get('database'));
        $this->assertInstanceOf(FilesystemConfigStore::class, $manager->get('filesystem'));

        $this->assertTrue($manager->hasResolved('database'));
        $this->assertTrue($manager->hasResolved('filesystem'));

        $manager->flushResolved();

        $this->assertFalse($manager->hasResolved('database'));
        $this->assertFalse($manager->hasResolved('filesystem'));
    }

    #[Test]
    public function errorsIfTheresNoConfigCanBeFoundForADriver(): void
    {
        $manager = app(Bud::class)->stores();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The config for [config::missing] could not be found');

        $manager->get('missing');
    }

    #[Test]
    public function errorsIfTheresNoCreatorForADriver(): void
    {
        $manager = app(Bud::class)->stores();

        config()->set('sprout.bud.stores.missing', []);

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The creator for [config::missing] could not be found');

        $manager->get('missing');
    }

    #[Test]
    public function errorsIfNoFilesystemDiskWasProvided(): void
    {
        config()->set('sprout.bud.stores.filesystem.disk', null);

        $manager = app(Bud::class)->stores();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The config store [filesystem] is missing a required value for \'disk\'');

        $manager->get('filesystem');
    }

    #[Test]
    public function errorsIfNoDatabaseTableWasProvided(): void
    {
        config()->set('sprout.bud.stores.database.table', null);

        $manager = app(Bud::class)->stores();

        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('The config store [database] is missing a required value for \'table\'');

        $manager->get('database');
    }

    #[Test, DefineEnvironment('withoutDefault')]
    public function errorsIfTheresNoDefault(): void
    {
        $this->expectException(MisconfigurationException::class);
        $this->expectExceptionMessage('There is no default config set');

        $manager = app(Bud::class)->stores();

        $manager->get();
    }

    #[Test]
    public function allowsCustomCreators(): void
    {
        $this->markTestSkipped('Needs rejigging');

        config()->set('sprout.bud.stores.database.driver', 'hello-there');

        IdentityResolverManager::register('hello-there', static function () {
            return new SubdomainIdentityResolver('hello-there', 'somedomain.local');
        });

        $manager = sprout()->resolvers();

        $this->assertTrue($manager->hasDriver('hello-there'));
        $this->assertFalse($manager->hasResolved('path'));
        $this->assertFalse($manager->hasResolved('subdomain'));

        $resolver = $manager->get('path');

        $this->assertInstanceOf(SubdomainIdentityResolver::class, $resolver);
        $this->assertSame('hello-there', $resolver->getName());
        $this->assertSame('somedomain.local', $resolver->getDomain());
        $this->assertTrue($manager->hasResolved('path'));
        $this->assertFalse($manager->hasResolved('subdomain'));
    }
}
