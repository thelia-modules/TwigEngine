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

namespace TwigEngine\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Action\Image;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\ConfigQuery;

/**
 * Resolves the absolute URL of a store image (logo, banner, favicon), the counterpart of the
 * Smarty {local_media} plugin used by the email/pdf themes.
 *
 * This variant always returns an absolute URL through the image processing cache, which is what
 * an email needs (it is read remotely by the mail client). The local-filesystem variant a PDF
 * engine may need (to avoid SSRF when embedding images) is a separate concern handled with the
 * PDF migration.
 */
final readonly class StoreMediaService
{
    /** @var array<string, array{config: string, default: string}> */
    private const TYPES = [
        'logo' => ['config' => 'logo_file', 'default' => 'thelia.svg'],
        'banner' => ['config' => 'banner_file', 'default' => 'banner.png'],
        'favicon' => ['config' => 'favicon_file', 'default' => 'favicon.png'],
    ];

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array{width?: int, height?: int, rotation?: int, resize_mode?: string} $options
     */
    public function url(string $type, array $options = []): string
    {
        if (!isset(self::TYPES[$type])) {
            return '';
        }

        $uploadDir = ConfigQuery::read('images_library_path');
        $uploadDir = ($uploadDir === null ? THELIA_LOCAL_DIR.'media'.DS.'images' : THELIA_ROOT.$uploadDir).DS.'store';

        $fileName = ConfigQuery::read(self::TYPES[$type]['config']);
        $sourcePath = $fileName === null ? null : $uploadDir.DS.$fileName;

        // Fall back to the bundled default (and ultimately thelia.svg) when the configured image is
        // missing or a 0-byte placeholder, so a fresh install still renders instead of throwing.
        if ($sourcePath === null || !file_exists($sourcePath) || @filesize($sourcePath) === 0) {
            $sourcePath = $uploadDir.DS.self::TYPES[$type]['default'];
        }
        if (!file_exists($sourcePath) || @filesize($sourcePath) === 0) {
            $sourcePath = $uploadDir.DS.'thelia.svg';
        }
        if (!file_exists($sourcePath) || @filesize($sourcePath) === 0) {
            return '';
        }

        $event = (new ImageEvent())
            ->setSourceFilepath($sourcePath)
            ->setCacheSubdirectory('store')
            ->setResizeMode($this->resizeMode($options['resize_mode'] ?? null));

        if (isset($options['width'])) {
            $event->setWidth((int) $options['width']);
        }
        if (isset($options['height'])) {
            $event->setHeight((int) $options['height']);
        }
        if (isset($options['rotation'])) {
            $event->setRotation((int) $options['rotation']);
        }

        try {
            $this->eventDispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);

            return $event->getFileUrl() ?? '';
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());

            return '';
        }
    }

    private function resizeMode(?string $mode): string
    {
        return match ($mode) {
            'crop' => Image::EXACT_RATIO_WITH_CROP,
            'borders' => Image::EXACT_RATIO_WITH_BORDERS,
            default => Image::KEEP_IMAGE_RATIO,
        };
    }
}
