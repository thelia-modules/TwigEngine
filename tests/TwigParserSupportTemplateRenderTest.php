<?php

namespace TwigEngine\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Localization\Service\LangService;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use TwigEngine\Template\TwigParser;

/**
 * A view is renderable as soon as one of the directories the Twig loader is filled with
 * holds the template - the theme, a theme it inherits from, or a module contributing to
 * any of them. Answering from the theme directory alone made the parser reject views that
 * modules ship themselves.
 */
class TwigParserSupportTemplateRenderTest extends TestCase
{
    /** A theme name no install ships, so no real theme descriptor is ever read. */
    private const THEME_NAME = 'twig-parser-test-theme';

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

        self::$workingDirectory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'twig-parser-support-'.uniqid('', false);
    }

    public static function tearDownAfterClass(): void
    {
        self::removeDirectory(self::$workingDirectory);
    }

    protected function setUp(): void
    {
        self::removeDirectory(self::$workingDirectory);

        mkdir($this->themeDirectory(), 0o777, true);
        mkdir($this->moduleTemplateDirectory(), 0o777, true);
    }

    public function testAThemeTemplateIsRenderable(): void
    {
        $parser = $this->createParser();

        touch($this->themeDirectory().DS.'page.html.twig');

        $this->assertTrue($parser->supportTemplateRender($this->themeDirectory(), 'page'));
    }

    public function testATemplateShippedByAModuleIsRenderableEvenWhenTheThemeDoesNotOverrideIt(): void
    {
        $parser = $this->createParser();
        $parser->addTemplateDirectory(
            TemplateDefinition::FRONT_OFFICE,
            self::THEME_NAME,
            $this->moduleTemplateDirectory(),
            'Page'
        );

        touch($this->moduleTemplateDirectory().DS.'page.html.twig');

        $this->assertFileDoesNotExist($this->themeDirectory().DS.'page.html.twig');
        $this->assertTrue($parser->supportTemplateRender($this->themeDirectory(), 'page'));
    }

    public function testATemplateShippedByAModuleForTheDefaultThemeIsRenderable(): void
    {
        $parser = $this->createParser();
        $parser->addTemplateDirectory(
            TemplateDefinition::FRONT_OFFICE,
            'default',
            $this->moduleTemplateDirectory(),
            'Page'
        );

        touch($this->moduleTemplateDirectory().DS.'page.html.twig');

        $this->assertFileDoesNotExist($this->themeDirectory().DS.'page.html.twig');
        $this->assertTrue($parser->supportTemplateRender($this->themeDirectory(), 'page'));
    }

    public function testATemplateShippedByAModuleForAnotherThemeIsNotRenderable(): void
    {
        $parser = $this->createParser();
        $parser->addTemplateDirectory(
            TemplateDefinition::FRONT_OFFICE,
            'some-other-theme',
            $this->moduleTemplateDirectory(),
            'Page'
        );

        touch($this->moduleTemplateDirectory().DS.'page.html.twig');

        $this->assertFalse($parser->supportTemplateRender($this->themeDirectory(), 'page'));
    }

    public function testAModuleTemplateRegisteredForAnotherTemplateTypeIsNotRenderable(): void
    {
        $parser = $this->createParser();
        $parser->addTemplateDirectory(
            TemplateDefinition::BACK_OFFICE,
            self::THEME_NAME,
            $this->moduleTemplateDirectory(),
            'Page'
        );

        touch($this->moduleTemplateDirectory().DS.'page.html.twig');

        $this->assertFalse($parser->supportTemplateRender($this->themeDirectory(), 'page'));
    }

    public function testAnUnknownTemplateIsNotRenderable(): void
    {
        $parser = $this->createParser();
        $parser->addTemplateDirectory(
            TemplateDefinition::FRONT_OFFICE,
            self::THEME_NAME,
            $this->moduleTemplateDirectory(),
            'Page'
        );

        $this->assertFalse($parser->supportTemplateRender($this->themeDirectory(), 'page'));
    }

    public function testATextTemplateShippedByAModuleIsRenderable(): void
    {
        $parser = $this->createParser();
        $parser->addTemplateDirectory(
            TemplateDefinition::EMAIL,
            self::THEME_NAME,
            $this->moduleTemplateDirectory(),
            'Page'
        );

        touch($this->moduleTemplateDirectory().DS.'order-confirmation.txt.twig');

        mkdir($this->emailThemeDirectory(), 0o777, true);

        $this->assertTrue($parser->supportTemplateRender($this->emailThemeDirectory(), 'order-confirmation'));
    }

    public function testANameCarryingItsFinalExtensionIsMappedToItsTwigFile(): void
    {
        $parser = $this->createParser();

        mkdir($this->emailThemeDirectory(), 0o777, true);
        touch($this->emailThemeDirectory().DS.'order-confirmation.txt.twig');

        $this->assertTrue($parser->supportTemplateRender($this->emailThemeDirectory(), 'order-confirmation.txt'));
    }

    public function testAnHtmlNameIsMappedToItsTwigFile(): void
    {
        $parser = $this->createParser();

        touch($this->themeDirectory().DS.'page.html.twig');

        $this->assertTrue($parser->supportTemplateRender($this->themeDirectory(), 'page.html'));
    }

    public function testANameAlreadyCarryingItsTwigExtensionIsRenderableAsIs(): void
    {
        $parser = $this->createParser();

        touch($this->themeDirectory().DS.'page.html.twig');

        $this->assertTrue($parser->supportTemplateRender($this->themeDirectory(), 'page.html.twig'));
    }

    public function testANameIsNotClaimedWhenTheOnlyMatchingFileAppendsAnExtensionToIt(): void
    {
        $parser = $this->createParser();

        // What render() looks for is "file.txt.twig": a file named after the requested name
        // plus an extension is not the file it would load, so the view is not renderable.
        touch($this->themeDirectory().DS.'file.txt.html.twig');

        $this->assertFileDoesNotExist($this->themeDirectory().DS.'file.txt.twig');
        $this->assertFalse($parser->supportTemplateRender($this->themeDirectory(), 'file.txt'));
    }

    private function createParser(): TwigParser
    {
        $parser = new TwigParser(
            $this->createMock(Environment::class),
            $this->createMock(FilesystemLoader::class),
            $this->createMock(ParserContext::class),
            $this->createMock(LangService::class),
        );

        $parser->templateHelper = $this->createMock(TemplateHelperInterface::class);
        $parser->requestStack = new RequestStack();

        return $parser;
    }

    private function themeDirectory(): string
    {
        return self::$workingDirectory.DS.'templates'.DS.'frontOffice'.DS.self::THEME_NAME;
    }

    private function emailThemeDirectory(): string
    {
        return self::$workingDirectory.DS.'templates'.DS.'email'.DS.self::THEME_NAME;
    }

    private function moduleTemplateDirectory(): string
    {
        return self::$workingDirectory.DS.'modules'.DS.'Page'.DS.'templates';
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
