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

declare(strict_types=1);

namespace TwigEngine\Template\Security;

use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedPropertyError;
use Twig\Sandbox\SecurityPolicyInterface;

/**
 * Security policy for the template sources that do not come from a theme directory: an e-mail
 * subject or body edited in the back-office and stored in database, compiled by
 * TwigParser::renderString().
 *
 * A theme file is written by whoever deploys the shop, an e-mail message by whoever holds the
 * back-office permission on it, so the two do not get the same reach. Messages keep the whole
 * template language and every filter, function and test the themes and the modules bring, and
 * objects answer their read accessors, which is what a message needs: a customer name, an order
 * total, a delivery date. What they lose is the way from the template back into PHP.
 *
 * Refusing by name rather than allowing by name is deliberate: a module ships its own Twig
 * filters and functions, and an allow list would silently break the messages that use them. The
 * barrier that does not depend on this list is the method policy below, plus Twig's own rule that
 * a callable handed to map(), filter(), sort(), reduce() or find() has to be a Closure while the
 * sandbox is on, so a template cannot name a PHP function for those filters to call.
 */
final class InlineTemplatePolicy implements SecurityPolicyInterface
{
    /**
     * Functions that read the PHP runtime or the filesystem instead of the shop data.
     */
    private const DENIED_FUNCTIONS = [
        'constant',
        'dump',
        'source',
        'template_from_string',
    ];

    /**
     * An object answers the accessors that read it, and nothing that writes, saves, deletes or
     * renders. Propel models, the Thelia models among them, expose their columns as get*()/is*(),
     * and their relations the same way, so a message keeps traversing them.
     */
    private const READ_ACCESSOR_PREFIXES = [
        'get',
        'is',
        'has',
        'can',
    ];

    /**
     * Read methods that do not start with one of the prefixes above.
     */
    private const ALLOWED_METHODS = [
        '__tostring',
        'count',
        'current',
        'format',
        'jsonserialize',
        'key',
        'offsetexists',
        'offsetget',
        'toarray',
        'tostring',
        'valid',
    ];

    /**
     * Objects that hand over the application itself rather than shop data. None of their methods
     * or properties are reachable, whatever their name: a getter that returns one of these is a
     * dead end rather than a way through.
     */
    private const DENIED_CLASS_PREFIXES = [
        'Doctrine\\DBAL\\',
        'PDO',
        'Propel\\Runtime\\Adapter\\',
        'Propel\\Runtime\\Connection\\',
        'Propel\\Runtime\\Propel',
        'Reflection',
        'Symfony\\Component\\Cache\\',
        'Symfony\\Component\\Console\\',
        'Symfony\\Component\\DependencyInjection\\',
        'Symfony\\Component\\Filesystem\\',
        'Symfony\\Component\\HttpFoundation\\',
        'Symfony\\Component\\HttpKernel\\',
        'Symfony\\Component\\Process\\',
        'Thelia\\Core\\Template\\',
        'Thelia\\Install\\',
        'Thelia\\Log\\',
        'Twig\\',
        'mysqli',
    ];

    public function checkSecurity($tags, $filters, $functions, array $tests = []): void
    {
        foreach ($functions as $function) {
            if (\in_array(strtolower((string) $function), self::DENIED_FUNCTIONS, true)) {
                throw new SecurityNotAllowedFunctionError(
                    sprintf('Function "%s" is not allowed in a message stored in database.', $function),
                    (string) $function
                );
            }
        }
    }

    public function checkMethodAllowed($obj, $method): void
    {
        $className = \get_class($obj);
        $methodName = strtolower((string) $method);

        if (!$this->isReachable($className) || !$this->isReadAccessor($methodName)) {
            throw new SecurityNotAllowedMethodError(
                sprintf('Calling "%s" on a "%s" object is not allowed in a message stored in database.', $method, $className),
                $className,
                (string) $method
            );
        }
    }

    public function checkPropertyAllowed($obj, $property): void
    {
        $className = \get_class($obj);

        if (!$this->isReachable($className)) {
            throw new SecurityNotAllowedPropertyError(
                sprintf('Reading "%s" on a "%s" object is not allowed in a message stored in database.', $property, $className),
                $className,
                (string) $property
            );
        }
    }

    private function isReachable(string $className): bool
    {
        foreach (self::DENIED_CLASS_PREFIXES as $deniedPrefix) {
            if (str_starts_with($className, $deniedPrefix)) {
                return false;
            }
        }

        return true;
    }

    private function isReadAccessor(string $methodName): bool
    {
        if (\in_array($methodName, self::ALLOWED_METHODS, true)) {
            return true;
        }

        foreach (self::READ_ACCESSOR_PREFIXES as $prefix) {
            if (str_starts_with($methodName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
