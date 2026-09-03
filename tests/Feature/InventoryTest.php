<?php

namespace Tests\Feature;

use Tests\TestCase;
use StarDust\StarDust;
use StarDust\Write\EntryPayload;
use Illuminate\Support\Facades\DB;

class InventoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Wipe StarDust data tables to guarantee a fresh seed for test isolation
        DB::table('entry_data')->delete();
        DB::table('stardust_sync_queue')->delete();
        
        $this->artisan('inventory:setup', ['--seed' => true]);
    }

    public function test_inventory_index_page_loads_successfully(): void
    {
        $response = $this->get('/inventory?warehouse=1');

        $response->assertStatus(200);
        $response->assertSee('Gudang Utama Jakarta');
        $response->assertSee('damarbob/StarDust');
    }

    public function test_can_switch_active_warehouse_tenant(): void
    {
        $response = $this->get('/inventory?warehouse=2');

        $response->assertStatus(200);
        $response->assertSee('Gudang Cabang Surabaya');
        $response->assertSee('Printer HP LaserJet Pro M404dn');
    }

    public function test_inventory_search_works(): void
    {
        $response = $this->get('/inventory?warehouse=1&search=Laptop');

        $response->assertStatus(200);
        $response->assertSee('Laptop Asus ROG Strix G15');
    }

    public function test_inventory_category_filter_works(): void
    {
        $response = $this->get('/inventory?warehouse=1&category=Elektronik');

        $response->assertStatus(200);
        $response->assertSee('Elektronik');
    }

    public function test_inventory_low_stock_filter_works(): void
    {
        $response = $this->get('/inventory?warehouse=1&low_stock=1');

        $response->assertStatus(200);
    }

    public function test_can_create_new_inventory_item_with_custom_attributes(): void
    {
        $newItemData = [
            'warehouse' => 1,
            'name' => 'Testing Device StarDust',
            'sku' => 'TST-STARDUST-999',
            'category' => 'Testing',
            'quantity' => 10,
            'price' => 500000,
            'unit' => 'Pcs',
            'supplier' => 'PT Test Supplier',
            'location' => 'Rak Test',
            'min_stock' => 2,
            'description' => 'Created via StarDust automated feature test',
            'warranty_months' => 24,
            'serial_number' => 'SN-TEST-001',
        ];

        $response = $this->post('/inventory', $newItemData);

        $response->assertRedirect('/inventory?warehouse=1');
        $response->assertSessionHas('success');

        // Verify via search
        $searchResponse = $this->get('/inventory?warehouse=1&search=Testing');
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('Testing Device StarDust');
    }

    public function test_can_perform_stock_in_and_stock_out(): void
    {
        /** @var StarDust $stardust */
        $stardust = app(StarDust::class);

        $entries = $stardust->read(new \StarDust\Read\EntryQuery(
            tenantId: 1,
            modelId: 1,
            pageSize: 1
        ));

        $this->assertNotEmpty($entries->rows);
        $item = $entries->rows[0];
        $initialQty = (int) ($item->fields['quantity'] ?? 0);

        // Stock in (+5)
        $inResponse = $this->post("/inventory/{$item->id}/stock-in?warehouse=1", ['amount' => 5]);
        $inResponse->assertSessionHas('success');

        $afterInEntry = $stardust->get(1, $item->id);
        $this->assertEquals($initialQty + 5, (int) $afterInEntry->fields['quantity']);

        // Stock out (-2)
        $outResponse = $this->post("/inventory/{$item->id}/stock-out?warehouse=1", ['amount' => 2]);
        $outResponse->assertSessionHas('success');

        $afterOutEntry = $stardust->get(1, $item->id);
        $this->assertEquals($initialQty + 3, (int) $afterOutEntry->fields['quantity']);
    }

    public function test_can_perform_stardust_bulk_import(): void
    {
        $response = $this->post('/inventory/bulk-import?warehouse=1');

        $response->assertRedirect('/inventory?warehouse=1');
        $response->assertSessionHas('success');

        // Verify bulk item exists
        $searchResponse = $this->get('/inventory?warehouse=1&search=Barcode');
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('Barcode Scanner Honeywell 1470g');
    }

    public function test_can_delete_inventory_item(): void
    {
        /** @var StarDust $stardust */
        $stardust = app(StarDust::class);

        // Create temporary item to delete
        $writeResult = $stardust->write(new EntryPayload(
            tenantId: 1,
            modelId: 1,
            fields: [
                'name' => 'To Be Deleted Item',
                'sku' => 'DEL-123',
                'category' => 'Temp',
                'quantity' => 1,
                'price' => 100,
                'unit' => 'Pcs',
                'supplier' => 'Temp',
                'location' => 'Temp',
                'min_stock' => 1,
            ]
        ));
        $entryId = $writeResult->entryId;

        $response = $this->delete("/inventory/{$entryId}?warehouse=1");
        $response->assertRedirect('/inventory?warehouse=1');
        $response->assertSessionHas('success');

        // Verify soft-deleted in StarDust
        $deletedEntry = $stardust->get(1, $entryId);
        $this->assertNull($deletedEntry);
    }
}
