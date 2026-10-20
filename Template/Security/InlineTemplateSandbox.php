<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TwigEngine\Template\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireCallable;
use Twig\Environment;
use Twig\Extension\EscaperExtension;
use Twig\Extension\SandboxExtension;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use Twig\Sandbox\Sandbox;
use Twig\Sandbox\SandboxInterface;

/**
 * Renders a template source that does not come from a theme directory, in practice a message
 * stored in database, under InlineTemplatePolicy.
 *
 * Since Twig 3.29 a sandbox is an environment of its own, sandboxed for good. That environment is
 * built from the application one: same loader, extensions, runtimes, globals, formats and escaping,
 * so a message keeps every filter, function and test the themes and the modules bring and loses
 * only what the policy refuses. Callables added straight to the application environment, outside
 * an extension, are not carried over: Twig does not expose them.
 *
 * Before Twig 3.29 there is no such environment: the sandbox of the application environment is
 * switched on around the render instead, as the previous releases did.
 */
final class InlineTemplateSandbox
{
    private ?SandboxInterface $sandbox = null;

    public function __construct(
        private readonly Environment $twig,
        private readonly InlineTemplatePolicy $policy,
        #[Autowire(service: 'twig.runtime_loader')]
        private readonly ?RuntimeLoaderInterface $runtimeLoader = null,
        #[AutowireCallable(service: 'twig.configurator.environment', method: 'configure')]
        private readonly ?\Closure $configureEnvironment = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $source, array $context = []): string
    {
        if (!class_exists(Sandbox::class)) {
            return $this->renderWithTheApplicationSandbox($source, $context);
        }

        $this->sandbox ??= new Sandbox($this->createEnvironment(), $this->policy);

        return $this->sandbox->createTemplate($source)->render($context);
    }

    private function createEnvironment(): Environment
    {
        $escaper = $this->twig->getExtension(EscaperExtension::class);

        $environment = new Environment($this->twig->getLoader(), [
            'debug' => $this->twig->isDebug(),
            'charset' => $this->twig->getCharset(),
            'strict_variables' => $this->twig->isStrictVariables(),
            'autoescape' => static fn (string $templateName): string|false => $escaper->getDefaultStrategy($templateName),
            'cache' => $this->twig->getCache(false),
            'auto_reload' => $this->twig->isAutoReload(),
            'use_yield' => $this->twig->useYield(),
        ]);

        foreach ($this->twig->getExtensions() as $extension) {
            // The core, escaper and optimizer extensions come with every environment, and the
            // sandbox registers its own SandboxExtension.
            if ($extension instanceof SandboxExtension || $environment->hasExtension($extension::class)) {
                continue;
            }

            $environment->addExtension($extension);
        }

        if (null !== $this->runtimeLoader) {
            $environment->addRuntimeLoader($this->runtimeLoader);
        }

        foreach ($this->twig->getGlobals() as $name => $value) {
            $environment->addGlobal($name, $value);
        }

        // Date and number formats, timezone, component lexer: what the framework sets on the
        // application environment.
        if (null !== $this->configureEnvironment) {
            ($this->configureEnvironment)($environment);
        }

        return $environment;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderWithTheApplicationSandbox(string $source, array $context): string
    {
        $sandbox = $this->twig->hasExtension(SandboxExtension::class)
            ? $this->twig->getExtension(SandboxExtension::class)
            : null;

        // A sandbox already on is left on: nothing says the caller did not open one around
        // several renders, a subject then a body.
        $sandboxWasAlreadyOn = $sandbox?->isSandboxed() ?? false;
        $sandbox?->enableSandbox();

        try {
            return $this->twig->createTemplate($source)->render($context);
        } finally {
            if (null !== $sandbox && !$sandboxWasAlreadyOn) {
                $sandbox->disableSandbox();
            }
        }
    }
}
