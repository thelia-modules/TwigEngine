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

use Thelia\Core\Template\Helper\FormatService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes the Thelia money/number/date/address formatters to Twig, the Twig counterpart
 * of the Smarty {format_money}/{format_number}/{format_date}/{format_address} plugins.
 *
 * All the logic lives in the engine-agnostic core FormatService; this extension is a thin
 * adapter, so emails and PDFs render the same values whatever the template engine.
 *
 * These are registered as Twig functions, not filters, on purpose: Symfony's IntlExtension
 * already owns the "format_date"/"format_number" filter names (with different, ICU-style
 * semantics), so filters would be silently shadowed. Functions live in a separate namespace
 * and keep the Thelia semantics (admin-configured language format, currency, decimals).
 */
class FormatExtension extends AbstractExtension
{
    public function __construct(
        private readonly FormatService $formatService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('format_money', [$this, 'money']),
            new TwigFunction('format_number', [$this, 'number']),
            new TwigFunction('format_date', [$this, 'date']),
            new TwigFunction('format_address', [$this, 'address']),
        ];
    }

    /**
     * {{ format_money(amount, currency_id) }}
     */
    public function money(
        int|float|string $number,
        ?int $currencyId = null,
        ?string $locale = null,
        ?int $decimals = null,
        ?string $decPoint = null,
        ?string $thousandsSep = null,
        bool $removeZeroDecimal = false,
    ): string {
        return $this->formatService->money($number, $currencyId, $locale, $decimals, $decPoint, $thousandsSep, $removeZeroDecimal);
    }

    /**
     * {{ format_number(number, decimals=2) }}
     */
    public function number(
        int|float|string $number,
        ?int $decimals = null,
        ?string $decPoint = null,
        ?string $thousandsSep = null,
        ?string $locale = null,
    ): string {
        return $this->formatService->number($number, $decimals, $decPoint, $thousandsSep, $locale);
    }

    /**
     * {{ format_date(date, 'date') }} — the second argument is the system output
     * ("date", "time" or "datetime"); pass an explicit php format via `format`.
     *
     * @param \DateTimeInterface|string|int|array<string,int|string>|null $date
     */
    public function date(
        \DateTimeInterface|string|int|array|null $date,
        ?string $output = null,
        ?string $format = null,
        ?string $locale = null,
    ): string {
        return $this->formatService->date($date, $format, $output, $locale);
    }

    /**
     * {{ format_address(order_address_id, locale) }}
     */
    public function address(
        int $orderAddressId,
        ?string $locale = null,
        bool $html = true,
        string $htmlTag = 'p',
        bool $postal = false,
        ?string $originCountry = null,
    ): string {
        return $this->formatService->address($orderAddressId, $locale, $html, $htmlTag, $postal, $originCountry);
    }
}
