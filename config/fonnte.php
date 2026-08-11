<?php

return [

    /*
     * Mapping nomor device (CS) Fonnte -> token pengirimnya.
     * Key HARUS sama persis dengan nilai field "device" yang dikirim Fonnte
     * di webhook (cek storage/logs/laravel.log pada key raw_body saat ada
     * chat masuk, format bisa 62xxx atau 0xxx tergantung setting device).
     */
    'devices' => [
        env('FONNTE_DEVICE_1') => env('FONNTE_TOKEN'),
        env('FONNTE_DEVICE_2') => env('FONNTE_TOKEN_2'),
        env('FONNTE_DEVICE_3') => env('FONNTE_TOKEN_3'),
        env('FONNTE_DEVICE_4') => env('FONNTE_TOKEN_4'),
        env('FONNTE_DEVICE_5') => env('FONNTE_TOKEN_5'),
    ],

    // dipakai kalau field "device" kosong atau nomornya belum ada di mapping
    'default_token' => env('FONNTE_TOKEN'),

];