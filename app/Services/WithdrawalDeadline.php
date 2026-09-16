<?php

namespace App\Services;

use Carbon\CarbonInterface;

class WithdrawalDeadline
{
    public function forDelivery(CarbonInterface $deliveredAt): CarbonInterface
    {
        $deadline = $deliveredAt->copy()->timezone('Europe/Bucharest')->startOfDay()->addDays(15)->subSecond();

        while ($deadline->isWeekend() || $this->isRomanianPublicHoliday($deadline)) {
            $deadline = $deadline->addDay()->endOfDay();
        }

        return $deadline;
    }

    private function isRomanianPublicHoliday(CarbonInterface $date): bool
    {
        $fixed = ['01-01', '01-02', '01-06', '01-07', '01-24', '05-01', '06-01', '08-15', '11-30', '12-01', '12-25', '12-26'];
        if (in_array($date->format('m-d'), $fixed, true)) {
            return true;
        }

        [$month, $day, $year] = array_map('intval', explode('/', jdtogregorian(
            juliantojd(3, 21, $date->year) + easter_days($date->year, CAL_EASTER_ALWAYS_JULIAN)
        )));
        $easter = $date->copy()->setDate($year, $month, $day);

        return $date->isSameDay($easter->copy()->subDays(2))
            || $date->isSameDay($easter)
            || $date->isSameDay($easter->copy()->addDay())
            || $date->isSameDay($easter->copy()->addDays(49))
            || $date->isSameDay($easter->copy()->addDays(50));
    }
}
