<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Two-letter US state codes plus DC.
 * Rocket Coding currently has fee schedules for CA, TX, NY, FL, IL (per
 * FEE_STATES in rocket-coding.jsx line 86). The full set is listed here so
 * that clinic addresses and provider licenses can reference any state.
 */
enum UsState: string
{
    case AL = 'AL';
    case AK = 'AK';
    case AZ = 'AZ';
    case AR = 'AR';
    case CA = 'CA';
    case CO = 'CO';
    case CT = 'CT';
    case DE = 'DE';
    case FL = 'FL';
    case GA = 'GA';
    case HI = 'HI';
    case ID = 'ID';
    case IL = 'IL';
    case IN = 'IN';
    case IA = 'IA';
    case KS = 'KS';
    case KY = 'KY';
    case LA = 'LA';
    case ME = 'ME';
    case MD = 'MD';
    case MA = 'MA';
    case MI = 'MI';
    case MN = 'MN';
    case MS = 'MS';
    case MO = 'MO';
    case MT = 'MT';
    case NE = 'NE';
    case NV = 'NV';
    case NH = 'NH';
    case NJ = 'NJ';
    case NM = 'NM';
    case NY = 'NY';
    case NC = 'NC';
    case ND = 'ND';
    case OH = 'OH';
    case OK = 'OK';
    case OR = 'OR';
    case PA = 'PA';
    case RI = 'RI';
    case SC = 'SC';
    case SD = 'SD';
    case TN = 'TN';
    case TX = 'TX';
    case UT = 'UT';
    case VT = 'VT';
    case VA = 'VA';
    case WA = 'WA';
    case WV = 'WV';
    case WI = 'WI';
    case WY = 'WY';
    case DC = 'DC';

    public static function supportedFeeScheduleStates(): array
    {
        return [self::CA, self::TX, self::NY, self::FL, self::IL];
    }

    public function hasFeeSchedule(): bool
    {
        return in_array($this, self::supportedFeeScheduleStates(), strict: true);
    }
}
