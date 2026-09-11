<?php

namespace TwigEngine;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Install\Database;
use Thelia\Module\BaseModule;
use Twig\Extension\SandboxExtension;
use TwigEngine\Template\Security\InlineTemplatePolicy;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class TwigEngine extends BaseModule
{
    /** @var string */
    const DOMAIN_NAME = 'theliatwig';

    /*
     * You may now override BaseModuleInterface methods, such as:
     * install, destroy, preActivation, postActivation, preDeactivation, postDeactivation
     *
     * Have fun !
     */

    /**
     * Defines how services are loaded in your modules
     *
     * @param ServicesConfigurator $servicesConfigurator
     */
    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([
                __DIR__.'/I18n',
                __DIR__.'/I18n/*',
                __DIR__.'/I18n/**/*',
                __DIR__.'/tests',
                __DIR__.'/tests/*',
            ])
            ->autowire(true)
            ->autoconfigure(true);

        // The sandbox has to be registered under Twig's own class name: a compiled template asks
        // the environment for SandboxExtension::class, so a subclass would leave the sandbox
        // unseen at render time. It is off by default and TwigParser::renderString() turns it on
        // around the sources it compiles, so theme files are unaffected.
        $servicesConfigurator
            ->set(SandboxExtension::class)
            ->args([service(InlineTemplatePolicy::class)])
            ->tag('twig.extension');
    }

    /**
     * Execute sql files in Config/update/ folder named with module version (ex: 1.0.1.sql).
     *
     * @param $currentVersion
     * @param $newVersion
     * @param ConnectionInterface $con
     */
    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        $updateDir = __DIR__.DS.'Config'.DS.'update';

        if (! is_dir($updateDir)) {
            return;
        }

        $finder = Finder::create()
            ->name('*.sql')
            ->depth(0)
            ->sortByName()
            ->in($updateDir);

        $database = new Database($con);

        /** @var \SplFileInfo $file */
        foreach ($finder as $file) {
            if (version_compare($currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }
    }
}
