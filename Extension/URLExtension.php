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

namespace TwigEngine\Extension;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use TwigEngine\Service\URLService;

class URLExtension extends AbstractExtension
{
    public function __construct(
        private readonly URLService $URLService,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('path', [$this, 'path']),
            new TwigFunction('thelia_url', [$this, 'theliaUrl']),
        ];
    }

    /**
     * {{ thelia_url('/customer/confirm/%token', {token: t}) }}
     *
     * Builds an absolute URL from a raw path, the Twig counterpart of the Smarty {url path=...} plugin.
     * CLI-safe (no Request, no named router), so it works for emails/PDFs rendered from a worker or console.
     *
     * @param array<string, mixed> $parameters
     */
    public function theliaUrl(string $path = '', array $parameters = []): string
    {
        return $this->URLService->generateFromPath($path, $parameters);
    }

    public function path(string $routeId, array $parameters = []): string
    {
        $url = '';
        try {
            $url = $this->URLService->generateUrlFunction($routeId, $parameters);
            $checkSymfonyRoutes = $url === '';
        } catch (\Exception) {
            $checkSymfonyRoutes = true;
        }

        return $checkSymfonyRoutes ? $this->urlGenerator->generate($routeId, $parameters) : $url;
    }
}
