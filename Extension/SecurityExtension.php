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

namespace TwigEngine\Extension;

use Propel\Runtime\Exception\PropelException;
use Psr\Cache\InvalidArgumentException;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use TwigEngine\Service\DataAccess\AttributeAccessService;
use TwigEngine\Service\DataAccess\DataAccessService;
use TwigEngine\Service\SecurityService;

class SecurityExtension extends AbstractExtension
{
    public function __construct(
        private readonly SecurityService $securityService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('isAuthenticated', [$this, 'isAuthenticated']),
            new TwigFunction('isAuthenticatedFront', [$this, 'isAuthenticatedFront']),
            new TwigFunction('isAuthenticatedAdmin', [$this, 'isAuthenticatedAdmin']),
            new TwigFunction('assertAuth', [$this, 'assertAuth']),
            new TwigFunction('assertCartNotEmpty', [$this, 'assertCartNotEmpty']),
            new TwigFunction('assertValidDelivery', [$this, 'assertValidDelivery']),
        ];
    }

    public function isAuthenticated(): bool
    {
        return $this->securityService->isAuthenticated();
    }

    public function isAuthenticatedFront(): bool
    {
        return $this->securityService->isAuthenticatedFront();
    }

    public function isAuthenticatedAdmin(): bool
    {
        return $this->securityService->isAuthenticatedAdmin();
    }

    /**
     * @throws AuthorizationException
     * @throws AuthenticationException
     */
    public function assertAuth(
        array $roles = [],
        array $resources = [],
        array $modules = [],
        array $attributes = []
    ): void {
        $this->securityService->assertAuth($roles, $resources, $modules, $attributes);
    }

    /**
     * @throws PropelException
     */
    public function assertCartNotEmpty(): void
    {
        $this->securityService->assertCartNotEmpty();
    }

    public function assertValidDelivery(): void
    {
        $this->securityService->assertValidDelivery();
    }
}
