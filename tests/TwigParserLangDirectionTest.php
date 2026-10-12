<?php

namespace TwigEngine\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Localization\LocalizationFacade;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Domain\Localization\Service\LocaleDirection;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use TwigEngine\Template\TwigParser;

/**
 * Every render carries lang_direction next to lang_code, so that a theme writes the dir
 * attribute of its root element without knowing which languages are read right to left.
 * It is deduced from the locale rather than from the language object, which is null
 * outside a session: a template rendered by a command still gets a direction.
 */
class TwigParserLangDirectionTest extends TestCase
{
    private static string $workingDirectory = '';

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

        if (!\defined('DS')) {
            \define('DS', \DIRECTORY_SEPARATOR);
        }

        self::$workingDirectory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'twig-parser-direction-'.uniqid('', false);
        mkdir(self::$workingDirectory, 0o777, true);

        file_put_contents(self::$workingDirectory.DS.'page.html.twig', '<html dir="{{ lang_direction }}">');
    }

    public static function tearDownAfterClass(): void
    {
        foreach (glob(self::$workingDirectory.DS.'*') ?: [] as $file) {
            unlink($file);
        }

        rmdir(self::$workingDirectory);
    }

    public function testARightToLeftLocaleRendersRtl(): void
    {
        $this->assertSame('<html dir="rtl">', $this->createParser('ar_SA')->render('page'));
    }

    public function testALeftToRightLocaleRendersLtr(): void
    {
        $this->assertSame('<html dir="ltr">', $this->createParser('fr_FR')->render('page'));
    }

    public function testNoLocaleAtAllStillRendersADirection(): void
    {
        $this->assertSame('<html dir="ltr">', $this->createParser(null)->render('page'));
    }

    /**
     * The direction reaches an inline template too - a message body edited in the back
     * office - which renders against the assigned variables rather than against globals.
     */
    public function testTheDirectionReachesAnInlineTemplateAfterAFirstRender(): void
    {
        $parser = $this->createParser('he_IL');
        $parser->render('page');

        $this->assertSame('rtl', $parser->renderString('{{ lang_direction }}'));
    }

    /**
     * The facade is the last argument and an optional one: a parser built the way it was
     * built before the writing direction existed still renders, and renders left to right
     * - which is exactly what it rendered then.
     */
    public function testAParserBuiltWithoutTheFacadeStillRenders(): void
    {
        $loader = new FilesystemLoader([self::$workingDirectory]);

        $parserContext = $this->createMock(ParserContext::class);
        $parserContext->method('getIterator')->willReturn(new \ArrayIterator([]));

        $parser = new TwigParser(
            new Environment($loader, ['cache' => false, 'strict_variables' => false]),
            $loader,
            $parserContext,
            $this->createMock(LangService::class),
        );

        $parser->templateHelper = $this->createMock(TemplateHelperInterface::class);
        $parser->requestStack = new RequestStack();
        $parser->requestStack->push(Request::create('/'));

        $this->assertSame('<html dir="ltr">', $parser->render('page'));
    }

    private function createParser(?string $locale): TwigParser
    {
        $loader = new FilesystemLoader([self::$workingDirectory]);

        $parserContext = $this->createMock(ParserContext::class);
        $parserContext->method('getIterator')->willReturn(new \ArrayIterator([]));

        $langService = $this->createMock(LangService::class);
        $langService->method('getLang')->willReturn(null);
        $langService->method('getLocale')->willReturn($locale);

        $parser = new TwigParser(
            new Environment($loader, ['cache' => false, 'strict_variables' => false]),
            $loader,
            $parserContext,
            $langService,
            localizationFacade: new LocalizationFacade($langService, new LocaleDirection()),
        );

        $parser->templateHelper = $this->createMock(TemplateHelperInterface::class);
        $parser->requestStack = new RequestStack();
        $parser->requestStack->push(Request::create('/'));

        return $parser;
    }
}
