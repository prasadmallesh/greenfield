<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Port of legacy Library::SetCustomerDeliveryDt (order date and delivery selection in d/m/Y).
 */
final class CustomerDeliveryDateService
{
    public function resolveDeliveryDate(string $orderDateDmY, string $dvdt): string
    {
        $sdtarr = explode('/', $orderDateDmY);
        $dvtarr = explode('/', $dvdt);
        if (count($dvtarr) < 3 || count($sdtarr) < 3) {
            return $dvdt;
        }

        if ($dvtarr[1] === '00' || $dvtarr[2] === '0000') {
            $mon = $sdtarr[1];
            $year = $sdtarr[2];
            $invdt = $sdtarr[2] . '-' . $sdtarr[1] . '-' . $sdtarr[0];
            $lastday = (int) date('t', strtotime($invdt));

            if ($dvtarr[1] === '00') {
                if (((int) $sdtarr[0] <= $lastday) && ((int) $dvtarr[0] <= (int) $sdtarr[0])) {
                    $mon = date('m', strtotime('+1 month', strtotime($invdt)));
                }
            }

            if ($dvtarr[2] === '0000') {
                if ((int) $sdtarr[1] === 12 && ((int) $sdtarr[0] <= $lastday) && ((int) $dvtarr[0] <= (int) $sdtarr[0])) {
                    $year = (string) ((int) date('Y', strtotime('+1 year', strtotime($invdt))));
                }
            }

            return $dvtarr[0] . '/' . $mon . '/' . $year;
        }

        return $dvdt;
    }

    /**
     * @return array<string, string> map date d/m/Y => label
     */
    public function displayDateOptions(string $date1Ymd, string $date2Ymd, string $format = 'd/m/Y'): array
    {
        $dates = [];
        $current = strtotime($date1Ymd);
        $end = strtotime($date2Ymd);
        $i = 1;
        while ($current !== false && $end !== false && $current <= $end) {
            $key = date($format, $current);
            if ($i === 1) {
                $dates[$key] = 'Today';
            } else {
                $dates[$key] = $key;
            }
            $current = strtotime('+1 day', $current);
            ++$i;
        }

        return $dates;
    }
}
