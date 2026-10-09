<?php

namespace App\Traits;

use Carbon\Carbon;

trait XeroMonthFilter
{
    /**
     * Hasilkan potongan filter `where` Xero untuk 1 bulan.
     * @param string|null $monthFilter format m-Y, contoh 02-2026. Null = tanpa filter.
     * @return string ' AND Date>=DateTime(...) AND Date<=DateTime(...)' atau ''
     */
    protected function xeroMonthClause(?string $monthFilter): string
    {
        if (!$monthFilter) {
            return '';
        }

        $start = Carbon::createFromFormat('!m-Y', $monthFilter)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Xero: DateTime(yyyy,m,d), integer tanpa leading zero
        return sprintf(
            ' AND Date>=DateTime(%d,%d,%d) AND Date<=DateTime(%d,%d,%d)',
            $start->year,
            $start->month,
            $start->day,
            $end->year,
            $end->month,
            $end->day
        );
    }
}