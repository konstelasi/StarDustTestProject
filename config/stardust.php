<?php

return [
    'tenant_id' => (int) env('STARDUST_TENANT_ID', 1),
    'warehouse_model_name' => 'gudang',
    'item_model_name' => 'barang',
    'model_name' => 'barang',

    'seed_warehouses' => [
        [
            'name' => 'Gudang Utama Jakarta',
            'code' => 'WH-JKT-01',
            'location' => 'Kawasan Industri Pulogadung, Jakarta Timur',
            'manager' => 'Budi Santoso',
        ],
        [
            'name' => 'Gudang Cabang Surabaya',
            'code' => 'WH-SBY-02',
            'location' => 'Kawasan Industri Rungkut, Surabaya',
            'manager' => 'Siti Rahma',
        ],
        [
            'name' => 'Gudang Logistik Bandung',
            'code' => 'WH-BDG-03',
            'location' => 'Kawasan Industri Cimahi, Bandung',
            'manager' => 'Asep Kurnia',
        ],
    ],
];

