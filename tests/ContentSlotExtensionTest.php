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

namespace TwigEngine\Tests;

use PHPUnit\Framework\TestCase;
use Thelia\Core\Content\Slot\ContentSlotLink;
use Thelia\Core\Content\Slot\ContentSlotResolverInterface;
use Thelia\Core\Content\Slot\ContentSlotService;
use Thelia\Domain\Localization\Service\LangService;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use TwigEngine\Extension\ContentSlotExtension;

/**
 * A theme names a slot and gets its links in the language of the visitor: the template
 * never knows which content, folder or module is behind it.
 */
class ContentSlotExtensionTest extends TestCase
{
    /** @var \ArrayObject<int, array{slot: string, locale: string}> */
    private \ArrayObject $questions;

    public static function setUpBeforeClass(): void
    {
        spl_autoload_register(static function (string $class): void {
            if (!str_starts_with($class, 'TwigEngine\\')) {
                return;
            }

            $relativePath = str_replace('\\', \DIRECTORY_SEPARATOR, substr($class, \strlen('TwigEngine\\')));
            $file = \dirname(__DIR__).\DIRECTORY_SEPARATOR.$relativePath.'.php';

            if (is_file($file)) {
                require_once $file;
            }
        });
    }

    protected function setUp(): void
    {
        $this->questions = new \ArrayObject();
    }

    public function testASlotListsItsLinksInTheLanguageOfTheVisitor(): void
    {
        $html = $this->render(
            '{% for link in content_slot("footer_links") %}<a href="{{ link.url }}"{% if link.opensInNewWindow %} target="_blank" rel="noopener"{% endif %}>{{ link.label }}</a>{% endfor %}',
            'fr_FR',
            [
                'footer_links' => [
                    new ContentSlotLink(label: 'Mentions légales', url: '/mentions-legales.html', source: 'content', sourceId: 3),
                    new ContentSlotLink(label: 'Blog', url: 'https://blog.example', source: 'url', opensInNewWindow: true),
                ],
            ],
        );

        self::assertSame(
            '<a href="/mentions-legales.html">Mentions légales</a><a href="https://blog.example" target="_blank" rel="noopener">Blog</a>',
            $html,
        );
        self::assertSame([['slot' => 'footer_links', 'locale' => 'fr_FR']], $this->questions->getArrayCopy());
    }

    public function testTheFirstLinkOfASlotIsTheOneTheResolverPutFirst(): void
    {
        $html = $this->render(
            '{% set terms = content_slot_first("consent.terms_and_conditions") %}{% if terms and terms.url %}<a href="{{ terms.url }}">{{ terms.label }}</a>{% endif %}',
            'en_US',
            [
                'consent.terms_and_conditions' => [
                    new ContentSlotLink(label: 'Terms', url: '/terms.html', source: 'content', sourceId: 2),
                    new ContentSlotLink(label: 'Other', url: '/other.html', source: 'content', sourceId: 4),
                ],
            ],
        );

        self::assertSame('<a href="/terms.html">Terms</a>', $html);
        self::assertSame([['slot' => 'consent.terms_and_conditions', 'locale' => 'en_US']], $this->questions->getArrayCopy());
    }

    public function testASlotNobodyFillsRendersNothing(): void
    {
        $html = $this->render(
            '[{% for link in content_slot("unknown") %}{{ link.label }}{% endfor %}][{{ content_slot_first("consent.none") is null ? "none" : "some" }}]',
            'en_US',
            ['consent.none' => []],
        );

        self::assertSame('[][none]', $html);
    }

    /**
     * @param array<string, list<ContentSlotLink>> $slots
     */
    private function render(string $template, string $locale, array $slots): string
    {
        $resolver = new class($slots, $this->questions) implements ContentSlotResolverInterface {
            /**
             * @param array<string, list<ContentSlotLink>>                   $slots
             * @param \ArrayObject<int, array{slot: string, locale: string}> $questions
             */
            public function __construct(private readonly array $slots, private readonly \ArrayObject $questions)
            {
            }

            public function resolve(string $slot, string $locale): ?array
            {
                $this->questions[] = ['slot' => $slot, 'locale' => $locale];

                return $this->slots[$slot] ?? null;
            }
        };

        $langService = $this->createMock(LangService::class);
        $langService->method('getLocale')->willReturn($locale);

        $twig = new Environment(new ArrayLoader(['slot' => $template]), ['autoescape' => 'html']);
        $twig->addExtension(new ContentSlotExtension(new ContentSlotService([$resolver]), $langService));

        return $twig->render('slot');
    }
}
