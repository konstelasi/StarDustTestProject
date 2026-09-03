<?php

return [
    'tenant_id' => (int) env('STARDUST_TENANT_ID', 1),
    'model_name' => 'product',

    'warehouses' => [
        1 => [
            'id' => 1,
            'name' => 'Gudang Utama Jakarta',
            'code' => 'WH-JKT-01',
            'location' => 'Kawasan Industri Pulogadung, Jakarta Timur',
            'manager' => 'Budi Santoso',
        ],
        2 => [
            'id' => 2,
            'name' => 'Gudang Cabang Surabaya',
            'code' => 'WH-SBY-02',
            'location' => 'Kawasan Industri Rungkut, Surabaya',
            'manager' => 'Siti Rahma',
        ],
        3 => [
            'id' => 3,
            'name' => 'Gudang Logistik Bandung',
            'code' => 'WH-BDG-03',
            'location' => 'Kawasan Industri Cimahi, Bandung',
            'manager' => 'Asep Kurnia',
        ],
    ],
];
