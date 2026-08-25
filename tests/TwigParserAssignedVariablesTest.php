<?php

namespace TwigEngine\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Domain\Localization\Service\LangService;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use TwigEngine\Template\TwigParser;

/**
 * A variable handed to assign() has to reach the template whatever happened before on the
 * shared Twig environment. Globals stop taking new entries once the environment has rendered
 * anything, which used to leave the second email of a request - or a hook rendered mid-page -
 * reading null where the caller had set a value.
 */
class TwigParserAssignedVariablesTest extends TestCase
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

        self::$workingDirectory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'twig-parser-assign-'.uniqid('', false);
        mkdir(self::$workingDirectory, 0o777, true);

        file_put_contents(self::$workingDirectory.DS.'first.html.twig', '{{ greeting }}');
        file_put_contents(self::$workingDirectory.DS.'second.html.twig', '{{ order_ref }}');
    }

    public static function tearDownAfterClass(): void
    {
        foreach (glob(self::$workingDirectory.DS.'*') ?: [] as $file) {
            unlink($file);
        }

        rmdir(self::$workingDirectory);
    }

    public function testAVariableAssignedBeforeTheFirstRenderReachesTheTemplate(): void
    {
        $parser = $this->createParser();

        $parser->assign('greeting', 'hello');

        $this->assertSame('hello', $parser->render('first'));
    }

    public function testAVariableAssignedAfterAFirstRenderStillReachesTheNextTemplate(): void
    {
        $parser = $this->createParser();

        $parser->assign('greeting', 'hello');
        $parser->render('first');

        $parser->assign('order_ref', 'ORD000000000042');

        $this->assertSame('ORD000000000042', $parser->render('second'));
    }

    public function testAVariableAssignedAfterAFirstRenderStillReachesAnInlineTemplate(): void
    {
        $parser = $this->createParser();

        $parser->assign('greeting', 'hello');
        $parser->render('first');

        $parser->assign('order_ref', 'ORD000000000042');

        $this->assertSame('Order ORD000000000042', $parser->renderString('Order {{ order_ref }}'));
    }

    public function testARenderParameterWinsOverAnAssignedVariable(): void
    {
        $parser = $this->createParser();

        $parser->assign('greeting', 'hello');
        $parser->render('first');

        $parser->assign('order_ref', 'assigned');

        $this->assertSame('given', $parser->renderString('{{ order_ref }}', ['order_ref' => 'given']));
    }

    public function testAnArrayOfVariablesIsAssignedAtOnce(): void
    {
        $parser = $this->createParser();

        $parser->render('first');
        $parser->assign(['greeting' => 'hello', 'order_ref' => 'ORD000000000042']);

        $this->assertSame('hello ORD000000000042', $parser->renderString('{{ greeting }} {{ order_ref }}'));
    }

    private function createParser(): TwigParser
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

        return $parser;
    }
}
