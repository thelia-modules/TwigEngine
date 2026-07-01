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

use Thelia\Core\Template\Helper\ConfigAccess;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Reads a store configuration value from Twig, the Twig counterpart of the Smarty
 * {config key="..."} plugin. {{ config('store_name') }} returns the stored value, with an
 * optional fallback: {{ config('store_name', 'Thelia') }}. The lookup lives in the
 * engine-agnostic core ConfigAccess; this extension is a thin adapter.
 */
class ConfigExtension extends AbstractExtension
{
    public function __construct(
        private readonly ConfigAccess $configAccess,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('config', [$this, 'config']),
        ];
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->configAccess->read($key, $default);
    }
}
