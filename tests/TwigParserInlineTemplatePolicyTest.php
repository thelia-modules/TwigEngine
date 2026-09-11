<?php

namespace TwigEngine\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Localization\Service\LangService;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\SandboxExtension;
use Twig\Loader\FilesystemLoader;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\TwigFunction;
use TwigEngine\Template\Security\InlineTemplatePolicy;
use TwigEngine\Template\TwigParser;

/**
 * renderString() compiles a source the parser did not get from a theme directory - in practice a
 * mail subject or body edited in the back-office - so what that source can reach is what
 * InlineTemplatePolicy allows: the shop data through read accessors, and no way back into PHP.
 * A theme file rendered right after keeps the full language.
 */
class TwigParserInlineTemplatePolicyTest extends TestCase
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

        self::$workingDirectory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'twig-parser-policy-'.uniqid('', false);
        mkdir(self::$workingDirectory, 0o777, true);

        file_put_contents(self::$workingDirectory.\DIRECTORY_SEPARATOR.'theme.html.twig', '{{ constant("PHP_EOL")|length }}');
    }

    public static function tearDownAfterClass(): void
    {
        foreach (glob(self::$workingDirectory.\DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }

        rmdir(self::$workingDirectory);
    }

    public function testAnInlineSourceCannotNameAPhpFunctionForAFilterToCall(): void
    {
        $parser = $this->createParser();

        $this->expectException(RuntimeError::class);

        $parser->renderString('{{ ["/etc/passwd"]|map("file_get_contents")|first }}');
    }

    public function testAnInlineSourceCannotReadThePhpRuntime(): void
    {
        $parser = $this->createParser();

        $this->expectException(SecurityNotAllowedFunctionError::class);

        $parser->renderString('{{ constant("PHP_VERSION") }}');
    }

    public function testAnInlineSourceReadsAnObjectThroughItsAccessors(): void
    {
        $parser = $this->createParser();

        $this->assertSame(
            'ORD000000000042',
            $parser->renderString('{{ order.ref }}', ['order' => new PolicyTestOrder()])
        );
    }

    public function testAnInlineSourceCannotWriteThroughAnObject(): void
    {
        $parser = $this->createParser();

        $this->expectException(SecurityNotAllowedMethodError::class);

        $parser->renderString('{{ order.setRef("ORD000000000043") }}', ['order' => new PolicyTestOrder()]);
    }

    public function testAnInlineSourceCannotReachTheApplicationThroughAnAccessor(): void
    {
        $parser = $this->createParser();

        // getEnvironment() is a read accessor, so the policy lets the template call it; what
        // stops there is the object it returns, whatever is asked of it next.
        $this->expectException(SecurityError::class);

        $parser->renderString('{{ order.environment.charset }}', ['order' => new PolicyTestOrder()]);
    }

    public function testAnInlineSourceKeepsTheLanguageAndTheFunctionsTheThemesBring(): void
    {
        $parser = $this->createParser();

        $this->assertSame(
            'ORD000000000042 THELIA',
            $parser->renderString(
                '{% set ref = order.ref %}{{ ref }} {{ store_name()|upper }}',
                ['order' => new PolicyTestOrder()]
            )
        );
    }

    public function testAThemeFileRenderedAfterAnInlineSourceKeepsTheFullLanguage(): void
    {
        $parser = $this->createParser();

        try {
            $parser->renderString('{{ constant("PHP_VERSION") }}');
        } catch (SecurityNotAllowedFunctionError) {
            // The point of the test is what the parser leaves behind for the next render.
        }

        $this->assertSame((string) \strlen(\PHP_EOL), $parser->render('theme'));
    }

    private function createParser(): TwigParser
    {
        $loader = new FilesystemLoader([self::$workingDirectory]);

        $twig = new Environment($loader, ['cache' => false, 'strict_variables' => false]);
        $twig->addExtension(new SandboxExtension(new InlineTemplatePolicy()));
        $twig->addFunction(new TwigFunction('store_name', static fn (): string => 'Thelia'));

        $parserContext = $this->createMock(ParserContext::class);
        $parserContext->method('getIterator')->willReturn(new \ArrayIterator([]));

        $parser = new TwigParser(
            $twig,
            $loader,
            $parserContext,
            $this->createMock(LangService::class),
        );

        $parser->templateHelper = $this->createMock(TemplateHelperInterface::class);
        $parser->requestStack = new RequestStack();
        $parser->requestStack->push(Request::create('/'));

        return $parser;
    }
}

class PolicyTestOrder
{
    private string $ref = 'ORD000000000042';

    public function getRef(): string
    {
        return $this->ref;
    }

    public function setRef(string $ref): void
    {
        $this->ref = $ref;
    }

    public function getEnvironment(): Environment
    {
        return new Environment(new \Twig\Loader\ArrayLoader([]));
    }
}
