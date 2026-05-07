<?php

declare(strict_types=1);

namespace App\Support;

/** Same labels as legacy GetConfigPayMode (stored in party_mast.pterms). */
final class PayTerms
{
    /**
     * @return array<string, string> code => label (for reference); legacy form uses label as value.
     */
    public static function labelList(): array
    {
        return [
            'D' => '7 DAYS',
            'F' => '15 DAYS',
            'C' => 'COD',
            'I' => '1 IN 1 OUT',
            'N' => 'NONE',
        ];
    }
}
