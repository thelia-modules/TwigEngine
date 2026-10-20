<?php

namespace TwigEngine\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Thelia\Core\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use TwigEngine\Extension\URLExtension;
use TwigEngine\Service\URLService;

/**
 * path() keeps Symfony's contract on every page: a path without scheme and host. On a back-office
 * page it goes through the router.admin service first, and since the back-office theme aliases that
 * service to the main router, every route resolves there; it used to come back as an absolute URL.
 */
class URLExtensionPathTest extends TestCase
{
    public function testPathOnABackOfficePageHasNoSchemeNorHost(): void
    {
        $this->assertSame('/admin/module/seone/configuration', $this->render('/admin/module/SEOne', "{{ path('seone.configuration') }}"));
    }

    public function testPathOnAFrontPageHasNoSchemeNorHost(): void
    {
        $this->assertSame('/admin/module/seone/configuration', $this->render('/account', "{{ path('seone.configuration') }}"));
    }

    public function testPathCanBeRelativeToTheCurrentPage(): void
    {
        $this->assertSame('configuration', $this->render('/admin/module/seone/edit', "{{ path('seone.configuration', {}, true) }}"));
    }

    public function testPathFallsBackOnTheApplicationRouterForARouteTheNamedRouterDoesNotKnow(): void
    {
        $this->assertSame('/only-in-the-application-router', $this->render('/admin', "{{ path('application.only') }}"));
    }

    /**
     * generateUrlFunction() is public: a caller that does not ask for a reference type keeps the
     * absolute URL it always got.
     */
    public function testTheServiceStillAnswersAnAbsoluteUrlByDefault(): void
    {
        $service = $this->urlService(Request::create('http://shop.test/admin'));

        $this->assertSame('http://shop.test/admin/module/seone/configuration', $service->generateUrlFunction('seone.configuration', []));
    }

    private function render(string $currentPath, string $template): string
    {
        $twig = new Environment(new ArrayLoader(['page' => $template]), ['cache' => false]);
        $twig->addExtension(new URLExtension(
            $this->urlService(Request::create('http://shop.test'.$currentPath)),
            $this->generator(['application.only' => '/only-in-the-application-router'], $currentPath),
        ));

        return $twig->render('page');
    }

    private function urlService(Request $request): URLService
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $router = $this->generator(['seone.configuration' => '/admin/module/seone/configuration'], $request->getPathInfo());

        $container = new Container();
        $container->set('router.admin', $router);
        $container->set('router.front', $router);

        return new URLService($requestStack, $container);
    }

    /**
     * @param array<string, string> $routes
     */
    private function generator(array $routes, string $currentPath): UrlGeneratorInterface
    {
        $collection = new RouteCollection();
        foreach ($routes as $name => $path) {
            $collection->add($name, new Route($path));
        }

        return new UrlGenerator($collection, new RequestContext('', 'GET', 'shop.test', 'http', 80, 443, $currentPath));
    }
}
