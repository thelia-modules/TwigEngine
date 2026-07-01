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

use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateDefinition;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Publishes an asset shipped by a module (JS, CSS, image) to the web directory and returns its URL,
 * the Twig counterpart of the Smarty {javascripts}/{stylesheet} plugins. This lets a module reference
 * its own assets without a CDN:
 *
 *   <script src="{{ module_asset('CustomFields', 'assets/js/Sortable.min.js') }}"></script>
 *
 * The file is looked up under the module template directory (e.g.
 * MyModule/templates/backOffice/default-twig/assets/...) and copied to public/assets on first use.
 */
class AssetExtension extends AbstractExtension
{
    public function __construct(
        private readonly ParserResolver $parserResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('module_asset', [$this, 'moduleAsset']),
        ];
    }

    /**
     * @param string $moduleCode   module code owning the asset (e.g. "CustomFields")
     * @param string $relativePath path relative to the module template directory (e.g. "assets/js/foo.js")
     * @param string $type         asset type ("js", "css", ...); deduced from the extension when empty
     */
    public function moduleAsset(string $moduleCode, string $relativePath, string $type = ''): string
    {
        $parser = $this->resolveParser();

        return $this->parserResolver->getAssetResolver($parser)->resolveAssetURL(
            $moduleCode,
            $relativePath,
            '' !== $type ? $type : pathinfo($relativePath, \PATHINFO_EXTENSION),
            $parser,
        );
    }

    /**
     * On a Symfony-rendered Twig back-office page no parser is current, so fall back to the
     * highest-priority parser and give it the active template definition, mirroring BaseHook::getParser().
     */
    private function resolveParser(): ParserInterface
    {
        $current = ParserResolver::getCurrentParser();

        if ($current instanceof ParserInterface) {
            return $current;
        }

        $parser = $this->parserResolver->getDefaultParser();
        $definition = $this->parserResolver->getCurrentTemplateDefinition();

        if ($definition instanceof TemplateDefinition && !$parser->getTemplateDefinition() instanceof TemplateDefinition) {
            $parser->setTemplateDefinition($definition, true);
        }

        return $parser;
    }
}
