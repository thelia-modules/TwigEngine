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

use Thelia\Core\Content\Slot\ContentSlotLink;
use Thelia\Core\Content\Slot\ContentSlotService;
use Thelia\Domain\Localization\Service\LangService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the content slots of the core to the templates: a theme asks for a slot by its
 * code (header_links, footer_links, consent.<code>) and never for a content or folder id.
 *
 *     {% for link in content_slot('footer_links') %}…{% endfor %}
 *     {% set terms = content_slot_first('consent.terms_and_conditions') %}
 *
 * The slot is read in the language of the visitor, or in the default language of the
 * shop when there is no request (a command, a message rendered by a worker).
 */
final class ContentSlotExtension extends AbstractExtension
{
    public function __construct(
        private readonly ContentSlotService $contentSlotService,
        private readonly LangService $langService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('content_slot', $this->contentSlot(...)),
            new TwigFunction('content_slot_first', $this->firstContentSlotLink(...)),
        ];
    }

    /**
     * @return list<ContentSlotLink>
     */
    public function contentSlot(string $slot): array
    {
        return $this->contentSlotService->links($slot, $this->currentLocale());
    }

    public function firstContentSlotLink(string $slot): ?ContentSlotLink
    {
        return $this->contentSlotService->first($slot, $this->currentLocale());
    }

    private function currentLocale(): string
    {
        return (string) $this->langService->getLocale();
    }
}
