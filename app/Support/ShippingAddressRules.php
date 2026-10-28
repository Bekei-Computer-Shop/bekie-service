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
     * @return list<string>
     */
    public static function countryCodes(): array
    {
        return explode(' ', self::COUNTRY_CODES);
    }
}
