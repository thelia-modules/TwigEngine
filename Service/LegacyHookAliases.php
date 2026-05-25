<?php

declare(strict_types=1);

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

/**
 * Maps a new Twig hook name to the list of legacy Smarty hook names a third-party
 * module may still subscribe to. The {@see HookService} dispatches the modern hook
 * first, then replays each alias so listeners written against the Smarty back-office
 * keep contributing without forcing a rename.
 */
final readonly class LegacyHookAliases
{
    /**
     * @return list<array{name: string, params?: array<string, scalar>, paramRemap?: array<string, string>}>
     */
    public function aliasesFor(string $hookName): array
    {
        return match ($hookName) {
            'attribute.update-form' => [['name' => 'attribute.create-form']],
            'category.update-form' => [['name' => 'category.create-form']],
            'feature.update-form' => [['name' => 'feature.create-form']],
            'product.update-form' => [['name' => 'product.create-form']],
            'content.update-form' => [['name' => 'content.create-form']],
            'folder.update-form' => [['name' => 'folder.create-form']],
            'message.update-form' => [['name' => 'message.create-form']],
            'currency.edit-form' => [['name' => 'currency.create-form']],
            'variable.edit-form' => [
                ['name' => 'variable.create-form'],
                ['name' => 'variables-edit.top'],
                ['name' => 'variables-edit.bottom'],
            ],
            'coupon.edit-top' => [
                ['name' => 'coupon.create-js'],
                ['name' => 'coupon.update-js'],
            ],
            'product.seo.update-form' => [[
                'name' => 'tab-seo.update-form',
                'params' => ['type' => 'product'],
                'paramRemap' => ['product_id' => 'id'],
            ]],
            'category.seo.update-form' => [[
                'name' => 'tab-seo.update-form',
                'params' => ['type' => 'category'],
                'paramRemap' => ['category_id' => 'id'],
            ]],
            'folder.seo.update-form' => [[
                'name' => 'tab-seo.update-form',
                'params' => ['type' => 'folder'],
                'paramRemap' => ['folder_id' => 'id'],
            ]],
            'content.seo.update-form' => [[
                'name' => 'tab-seo.update-form',
                'params' => ['type' => 'content'],
                'paramRemap' => ['content_id' => 'id'],
            ]],
            'brand.seo.update-form' => [[
                'name' => 'tab-seo.update-form',
                'params' => ['type' => 'brand'],
                'paramRemap' => ['brand_id' => 'id'],
            ]],
            default => [],
        };
    }
}
