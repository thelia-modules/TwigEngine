<?php

namespace TwigEngine\Tests;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\TwigFunction;
use TwigEngine\Template\Security\InlineTemplatePolicy;
use TwigEngine\Template\Security\InlineTemplateSandbox;

/**
 * A message stored in database renders in a sandbox built from the application environment: the
 * functions the extensions bring, their runtimes and the formats the framework sets are there,
 * what the policy refuses is not, and no deprecated sandbox method of Twig is called.
 */
class InlineTemplateSandboxTest extends TestCase
{
    public function testAStoredMessageReadsTheStoreConfiguration(): void
    {
        $this->assertSame(
            'Your order ORD000000000042 at Thelia shop',
            $this->sandbox()->render('Your order {{ order_ref }} at {{ config("store_name") }}', ['order_ref' => 'ORD000000000042'])
        );
    }

    public function testAFunctionServedByARuntimeIsAvailable(): void
    {
        $this->assertSame('Bonjour', $this->sandbox()->render('{{ greeting() }}'));
    }

    public function testTheFormatsSetOnTheApplicationEnvironmentApply(): void
    {
        $this->assertSame('1 234,50', $this->sandbox()->render('{{ 1234.5|number_format }}'));
    }

    public function testAGlobalOfTheApplicationEnvironmentIsAvailable(): void
    {
        $this->assertSame('https://shop.test', $this->sandbox()->render('{{ shop_url }}'));
    }

    public function testAFunctionThePolicyRefusesIsRefused(): void
    {
        $this->expectException(SecurityNotAllowedFunctionError::class);

        $this->sandbox()->render('{{ constant("PHP_VERSION") }}');
    }

    public function testTheApplicationEnvironmentKeepsTheFullLanguage(): void
    {
        $twig = $this->applicationEnvironment();
        $sandbox = new InlineTemplateSandbox($twig, new InlineTemplatePolicy());

        try {
            $sandbox->render('{{ constant("PHP_VERSION") }}');
        } catch (SecurityNotAllowedFunctionError) {
            // What matters is the application environment afterwards.
        }

        $this->assertSame(\PHP_VERSION, $twig->createTemplate('{{ constant("PHP_VERSION") }}')->render());
    }

    public function testRenderingTriggersNoDeprecation(): void
    {
        $deprecations = [];
        $previousErrorReporting = error_reporting(-1);
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_DEPRECATED | \E_USER_DEPRECATED);

        try {
            $sandbox = $this->sandbox();
            $sandbox->render('{{ config("store_name") }}');
            $sandbox->render('{{ config("store_name") }}');
        } finally {
            restore_error_handler();
            error_reporting($previousErrorReporting);
        }

        $this->assertSame([], $deprecations);
    }

    private function sandbox(): InlineTemplateSandbox
    {
        return new InlineTemplateSandbox(
            $this->applicationEnvironment(),
            new InlineTemplatePolicy(),
            new FactoryRuntimeLoader([SandboxTestGreetingRuntime::class => static fn (): SandboxTestGreetingRuntime => new SandboxTestGreetingRuntime()]),
            static function (Environment $environment): void {
                $environment->getExtension(CoreExtension::class)->setNumberFormat(2, ',', ' ');
            },
        );
    }

    /**
     * What the module registers on the application environment: the sandbox extension, off, and
     * the extensions of the themes and modules.
     */
    private function applicationEnvironment(): Environment
    {
        $twig = new Environment(new ArrayLoader([]), ['cache' => false]);
        $twig->addExtension(new SandboxExtension(new InlineTemplatePolicy()));
        $twig->addExtension(new SandboxTestShopExtension());
        $twig->addGlobal('shop_url', 'https://shop.test');

        return $twig;
    }
}

class SandboxTestShopExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('config', static fn (string $key): ?string => 'store_name' === $key ? 'Thelia shop' : null),
            new TwigFunction('greeting', [SandboxTestGreetingRuntime::class, 'greet']),
        ];
    }
}

class SandboxTestGreetingRuntime
{
    public function greet(): string
    {
        return 'Bonjour';
    }
}
