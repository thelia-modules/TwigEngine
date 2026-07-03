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

namespace TwigEngine\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Thelia\Core\Hook\Theme\ThemeHookInterface;

/**
 * Renders a theme hook point declared with the theme_hook() Twig function.
 *
 * Every module implementing ThemeHookInterface is collected through the
 * "thelia.theme_hook" tag; the tag priority drives the rendering order. The
 * fragments returned by the supporting handlers are concatenated. A failing
 * handler never breaks the page in production: its error is logged and the
 * next handlers still render. In debug mode the exception is rethrown.
 */
readonly class ThemeHookRenderer
{
    /**
     * @param iterable<ThemeHookInterface> $handlers
     */
    public function __construct(
        private bool $kernelDebug,
        private LoggerInterface $logger,
        #[AutowireIterator('thelia.theme_hook')]
        private iterable $handlers,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function render(string $hookName, array $parameters = []): string
    {
        $content = '';

        foreach ($this->handlers as $handler) {
            if (!$handler->supports($hookName)) {
                continue;
            }

            try {
                $content .= $handler->render($hookName, $parameters);
            } catch (\Throwable $exception) {
                if ($this->kernelDebug) {
                    throw $exception;
                }

                $this->logger->error(
                    'Theme hook "{hook}" handler {handler} failed: {message}',
                    [
                        'hook' => $hookName,
                        'handler' => $handler::class,
                        'message' => $exception->getMessage(),
                        'exception' => $exception,
                    ]
                );
            }
        }

        return $content;
    }
}
