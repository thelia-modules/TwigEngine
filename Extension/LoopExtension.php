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

use Thelia\Api\Service\DataAccess\LoopDataAccessService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Runs a Thelia loop from Twig, the Twig counterpart of the Smarty {loop} plugin.
 *
 * This lives in TwigEngine (the engine module, always present when Twig renders) rather
 * than in a front theme, so loops work the same in the front office (any theme), the
 * back office, emails and PDFs. Execution is delegated to the core LoopDataAccessService,
 * which returns each row as an associative array keyed by the uppercase output names, so a
 * template reads {{ row.KEY }} exactly like the Smarty {loop} plugin, with {% else %}
 * covering the empty case ({ifloop}/{elseloop} in Smarty).
 */
class LoopExtension extends AbstractExtension
{
    public function __construct(
        private readonly LoopDataAccessService $loopDataAccessService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('loop', [$this, 'loop']),
            new TwigFunction('loopCount', [$this, 'loopCount']),
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<int, array<string, mixed>>
     */
    public function loop(string $name, string $type, array $params = []): array
    {
        return $this->loopDataAccessService->theliaLoop($name, $type, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function loopCount(string $type, array $params = []): int
    {
        return $this->loopDataAccessService->theliaCount($type, $params);
    }
}
