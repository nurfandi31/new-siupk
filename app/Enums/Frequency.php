<?php

declare(strict_types=1);

namespace App\Enums;

enum Frequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Bimonthly = 'bimonthly';
    case Quarterly = 'quarterly';
    case Every4Months = 'every_4_months';
    case Every5Months = 'every_5_months';
    case Every6Months = 'every_6_months';
    case Every7Months = 'every_7_months';
    case Every8Months = 'every_8_months';
    case Every9Months = 'every_9_months';
    case Every10Months = 'every_10_months';
    case Every11Months = 'every_11_months';
    case Every12Months = 'every_12_months';
    case Every24Months = 'every_24_months';
    case Every36Months = 'every_36_months';
    case AtMaturity = 'at_maturity';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
