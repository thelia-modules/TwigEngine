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

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use TwigEngine\Service\StoreMediaService;

/**
 * Exposes the store images (logo, banner, favicon) to Twig, the Twig counterpart of the Smarty
 * {local_media} plugin. Returns an absolute URL through the image cache, empty string when no
 * image is available, so a template can render it conditionally: {% if media_url('logo') %}...{% endif %}.
 */
class MediaExtension extends AbstractExtension
{
    public function __construct(
        private readonly StoreMediaService $storeMediaService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('media_url', [$this, 'mediaUrl']),
        ];
    }

    /**
     * {{ media_url('logo') }}
     *
     * @param array{width?: int, height?: int, rotation?: int, resize_mode?: string} $options
     */
    public function mediaUrl(string $type, array $options = []): string
    {
        return $this->storeMediaService->url($type, $options);
    }
}
