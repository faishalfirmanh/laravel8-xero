<?php

namespace Database\Seeders;

use App\Models\MasterData\MasterCurrency;
use Illuminate\Database\Seeder;

class SeederCurrency extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $currencies = [
            [
                'code_curr' => 'IDR',
                'ket' => 'indonesia-idr',
                'nominal_currency' => 1,
            ],
            [
                'code_curr' => 'SAR-invoice',
                'ket' => 'SAR-BILL',
                'nominal_currency' => 4800,
            ],
            [
                'code_curr' => 'SAR-bill',
                'ket' => 'SAR-Invoice',
                'nominal_currency' => 4750,
            ],
            [
                'code_curr' => 'USD',
                'ket' => 'USD',
                'nominal_currency' => 18000,
            ],
        ];

        foreach ($currencies as $currency) {
            MasterCurrency::updateOrCreate(
                [
                    'ket' => $currency['ket'],
                ],
                [
                    'code_curr' => $currency['code_curr'],
                    'satu_rupiah' => 1,
                    'nominal_currency' => $currency['nominal_currency'],
                    'is_active' => true,
                ]
            );
        }
    }
}
