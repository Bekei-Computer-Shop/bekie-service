<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rule;

class ShippingAddressRules
{
    private const COUNTRY_CODES = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW';

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $prefix = '', bool $required = true, ?string $requiredWith = null): array
    {
        $requiredRules = $requiredWith !== null
            ? [$requiredWith]
            : ($required ? ['required'] : ['sometimes', 'required']);
        $optionalRules = $required ? ['nullable'] : ['sometimes', 'nullable'];
        $countryCodes = explode(' ', self::COUNTRY_CODES);

        return [
            $prefix.'full_name' => [...$requiredRules, 'string', 'max:255'],
            $prefix.'phone' => [...$optionalRules, 'string', 'max:50'],
            $prefix.'email' => [...$optionalRules, 'email', 'max:255'],
            $prefix.'company' => [...$optionalRules, 'string', 'max:255'],
            $prefix.'address_line_1' => [...$requiredRules, 'string', 'max:255'],
            $prefix.'address_line_2' => [...$optionalRules, 'string', 'max:255'],
            $prefix.'city' => [...$requiredRules, 'string', 'max:100'],
            $prefix.'state' => [...$optionalRules, 'string', 'max:100'],
            $prefix.'postal_code' => [...$requiredRules, 'string', 'max:50'],
            $prefix.'country' => [...$requiredRules, 'string', 'size:2', Rule::in($countryCodes)],
            $prefix.'label' => [...$optionalRules, 'string', 'max:100'],
            $prefix.'delivery_note' => [...$optionalRules, 'string', 'max:1000'],
        ];
    }

    /**
     * Country names older clients send instead of a code, by upper-cased name.
     * The storefront on `main` posts "Cambodia"; the rest cover the region and
     * common shorthands. Any other English name is resolved through intl where
     * the extension is loaded (it is in the production image).
     */
    private const COUNTRY_ALIASES = [
        'CAMBODIA' => 'KH', 'KINGDOM OF CAMBODIA' => 'KH', 'KAMPUCHEA' => 'KH', 'KHM' => 'KH',
        'THAILAND' => 'TH', 'VIETNAM' => 'VN', 'VIET NAM' => 'VN', 'LAOS' => 'LA', 'LAO PDR' => 'LA',
        'MYANMAR' => 'MM', 'SINGAPORE' => 'SG', 'MALAYSIA' => 'MY', 'INDONESIA' => 'ID',
        'PHILIPPINES' => 'PH', 'CHINA' => 'CN', 'JAPAN' => 'JP', 'SOUTH KOREA' => 'KR', 'KOREA' => 'KR',
        'UNITED STATES' => 'US', 'UNITED STATES OF AMERICA' => 'US', 'USA' => 'US',
        'UNITED KINGDOM' => 'GB', 'UK' => 'GB', 'AUSTRALIA' => 'AU', 'FRANCE' => 'FR',
    ];

    /**
     * @return list<string>
     */
    public static function countryCodes(): array
    {
        return explode(' ', self::COUNTRY_CODES);
    }

    /**
     * The ISO 3166-1 alpha-2 code for a submitted country: a code in any case,
     * or a country name ("Cambodia" → "KH"). Anything not recognised comes
     * back trimmed and upper-cased, for the country rules to reject.
     */
    public static function normalizeCountry(string $country): string
    {
        $value = strtoupper(trim(preg_replace('/\s+/', ' ', $country) ?? $country));

        if (strlen($value) === 2) {
            return $value;
        }

        return self::COUNTRY_ALIASES[$value] ?? self::countryNamesFromIntl()[$value] ?? $value;
    }

    /**
     * English country names to codes, from intl when it is available.
     *
     * @return array<string, string>
     */
    private static function countryNamesFromIntl(): array
    {
        static $names = null;

        if ($names === null) {
            $names = [];
            if (extension_loaded('intl')) {
                foreach (self::countryCodes() as $code) {
                    $names[strtoupper(\Locale::getDisplayRegion('-'.$code, 'en'))] = $code;
                }
            }
        }

        return $names;
    }
}
