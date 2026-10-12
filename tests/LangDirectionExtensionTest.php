<?php

namespace TwigEngine\Tests;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Localization\LocalizationFacade;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Localization\Service\LocaleDirection;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use TwigEngine\Extension\LangDirectionExtension;

/**
 * The direction is published as a Twig *function* rather than only as a render variable,
 * because a variable does not exist where nobody assigns it: a back-office controller
 * rendering straight through the Twig environment, a live component re-rendering itself,
 * a macro. The environment below assigns nothing at all, which is the point.
 */
class LangDirectionExtensionTest extends TestCase
{
    public function testTheFunctionAnswersRtlForARightToLeftLocale(): void
    {
        $this->assertSame('rtl', $this->render('ar_SA'));
    }

    public function testTheFunctionAnswersLtrForALeftToRightLocale(): void
    {
        $this->assertSame('ltr', $this->render('fr_FR'));
    }

    public function testTheFunctionAnswersWithNoLocaleAtAll(): void
    {
        $this->assertSame('ltr', $this->render(null));
    }

    /**
     * The name is the contract a third-party theme inherits, so it is asserted rather
     * than merely called.
     */
    public function testTheFunctionIsRegisteredUnderTheNameThemesCall(): void
    {
        $names = array_map(
            static fn ($function) => $function->getName(),
            $this->extension('fr_FR')->getFunctions(),
        );

        $this->assertSame(['lang_direction'], $names);
    }

    private function render(?string $locale): string
    {
        $twig = new Environment(new ArrayLoader(['page' => '{{ lang_direction() }}']), ['cache' => false]);
        $twig->addExtension($this->extension($locale));

        return $twig->render('page');
    }

    private function extension(?string $locale): LangDirectionExtension
    {
        $langService = $this->createMock(LangService::class);
        $langService->method('getLocale')->willReturn($locale);

        return new LangDirectionExtension(new LocalizationFacade($langService, new LocaleDirection()));
    }
}
