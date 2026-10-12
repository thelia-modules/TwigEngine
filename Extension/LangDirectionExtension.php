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

use Thelia\Domain\Localization\LocalizationFacade;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Answers the writing direction of the current language, "ltr" or "rtl", for a template
 * to write straight into a dir attribute: {{ lang_direction() }}.
 *
 * TwigParser::render() also publishes the same value as a `lang_direction` *variable*,
 * and a theme rendered through the parser may keep reading it. The function exists
 * because a context variable does not exist where nobody puts it, and several renders
 * never go through the parser at all:
 *
 *  - a back-office controller renders with $this->twig->render(...) on the Symfony Twig
 *    environment, which assigns none of the parser's variables;
 *  - a live component re-renders itself from its own state, not from the context of the
 *    page that first mounted it;
 *  - a macro, and an `include ... only`, are compiled with an empty context by design.
 *
 * Registered on the global Twig environment like every other extension of this module,
 * so it answers in all of those, front and back, and a theme that never calls it is
 * unaffected.
 *
 * The direction itself is never computed here: the core owns the list of right-to-left
 * locales and resolves the current one - the working language of the administrator in
 * the back-office, the shop language in the front. It is never read from a request
 * parameter nor from an address.
 */
final class LangDirectionExtension extends AbstractExtension
{
    public function __construct(
        private readonly LocalizationFacade $localization,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('lang_direction', $this->langDirection(...)),
        ];
    }

    public function langDirection(): string
    {
        return $this->localization->getCurrentLangDirection();
    }
}
