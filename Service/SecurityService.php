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

namespace TwigEngine\Service;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\Exception\AuthenticationException;
use Thelia\Core\Security\Exception\AuthorizationException;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Exception\OrderException;
use Thelia\Model\AddressQuery;
use Thelia\Model\ModuleQuery;

class SecurityService
{
    public function __construct(
        protected RequestStack $requestStack,
        protected EventDispatcherInterface $dispatcher,
        protected SecurityContext $securityContext
    ) {
    }

    public function isAuthenticated(): bool
    {
        $type = ParserResolver::getCurrentParser()?->getTemplateDefinition()?->getType();
        return $type === TemplateDefinition::BACK_OFFICE ? $this->isAuthenticatedFront() : $this->isAuthenticatedAdmin();
    }

    public function isAuthenticatedFront(): bool
    {
        return $this->securityContext->hasCustomerUser();
    }

    public function isAuthenticatedAdmin(): bool
    {
        return $this->securityContext->hasAdminUser();
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function assertAuth(
        array $roles = [],
        array $resources = [],
        array $modules = [],
        array $accesses = [],
        string $loginTemplate = null
    ): void {
        if ($this->securityContext->isGranted($roles, $resources, $modules, $accesses)) {
            return;
        }
        $exceptionMessage = sprintf(
            "User not granted for roles '%s', to access resources '%s' with %s.",
            implode(',', $roles),
            implode(',', $resources),
            implode(',', $accesses)
        );
        if (null === $this->securityContext->checkRole($roles)) {
            // The current user is not logged-in.
            $ex = new AuthenticationException($exceptionMessage);
            if (null !== $loginTemplate) {
                $ex->setLoginTemplate($loginTemplate);
            }
        } else {
            // We have a logged-in user, who do not have the proper permission. Issue an AuthorizationException.
            $ex = new AuthorizationException($exceptionMessage);
        }
        throw $ex;
    }

    /**
     * @throws PropelException
     */
    public function assertCartNotEmpty(): void
    {
        $cart = $this->getSession()->getSessionCart($this->dispatcher);
        if ($cart === null || $cart->countCartItems() === 0) {
            throw new OrderException('Cart must not be empty', OrderException::CART_EMPTY, ['empty' => 1]);
        }
    }

    public function assertValidDelivery(): void
    {
        $order = $this->getSession()->getOrder();
        $checkAddress = $checkModule = null;
        // Does address and module still exists ? We assume address owner can't change neither module type
        if (null !== $order?->getChoosenDeliveryAddress()) {
            $checkAddress = AddressQuery::create()->findPk($order->getChoosenDeliveryAddress());
        }

        if (null !== $order?->getDeliveryModuleId()) {
            $checkModule = ModuleQuery::create()->findPk($order->getDeliveryModuleId());
        }

        if (null === $order || null === $checkAddress || null === $checkModule) {
            throw new OrderException('Delivery must be defined', OrderException::UNDEFINED_DELIVERY, ['missing' => 1]);
        }
    }

    protected function getSession(): Session
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        if (!$session instanceof Session) {
            throw new \RuntimeException('Thelia session not found.');
        }

        return $session;
    }
}
