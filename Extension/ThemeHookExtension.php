<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TwigEngine\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use TwigEngine\Service\ThemeHookRenderer;

/**
 * Exposes the theme_hook() Twig function: a front theme declares an extension
 * point with theme_hook('page.zone.position', {...}) and modules answer it
 * through Thelia\Core\Hook\Theme\ThemeHookInterface.
 */
class ThemeHookExtension extends AbstractExtension
{
    public function __construct(
        private readonly ThemeHookRenderer $themeHookRenderer,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_hook', [$this, 'themeHook'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function themeHook(string $hookName, array $parameters = []): string
    {
        return $this->themeHookRenderer->render($hookName, $parameters);
    }
}
