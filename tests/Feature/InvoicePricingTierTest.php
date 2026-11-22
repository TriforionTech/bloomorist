<?php

namespace Tests\Feature;

use App\Filament\Pages\GenerateInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicePricingTierTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Product $product;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@bloomorist.test',
            'password' => bcrypt('password'),
        ]);

        $this->product = Product::create([
            'sku' => 'FLW-001',
            'nama' => 'Mawar Holland Super',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 20000,
            'harga_vendor' => 15000,
            'harga_dekor' => 12000,
            'stok' => 50,
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'nama' => 'Budi Santoso',
            'alias' => 'Budi',
            'alamat' => 'Jl. Mawar No. 12',
            'kota' => 'Surabaya',
            'provinsi' => 'Jawa Timur',
            'negara' => 'Indonesia',
            'nomor_hp' => '081234567890',
            'tipe_customer' => 'vendor',
        ]);
    }

    public function test_invoice_model_saves_and_retrieves_pricing_tier(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'customer_type' => 'member',
            'customer_id' => $this->customer->id,
            'pricing_tier' => 'vendor',
            'discount_mode' => 'none',
            'subtotal' => 15000,
            'grand_total' => 15000,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('bl_invoices_t', [
            'id' => $invoice->id,
            'invoice_number' => 'INV-TEST-001',
            'pricing_tier' => 'vendor',
        ]);

        $this->assertEquals('vendor', $invoice->fresh()->pricing_tier);
    }

    public function test_edit_invoice_loads_saved_pricing_tier(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-002',
            'customer_type' => 'member',
            'customer_id' => $this->customer->id,
            'pricing_tier' => 'vendor',
            'discount_mode' => 'none',
            'subtotal' => 15000,
            'grand_total' => 15000,
            'status' => 'pending',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'snapshot_sku' => $this->product->sku,
            'snapshot_name' => $this->product->nama,
            'quantity' => 1,
            'unit_price' => 15000,
            'normal_price' => 15000,
            'item_discount' => 0,
            'discount_price' => 15000,
        ]);

        $this->actingAs($this->user);

        Livewire::withQueryParams(['invoice' => $invoice->id])
            ->test(GenerateInvoice::class)
            ->assertSchemaStateSet([
                'pricing_tier' => 'vendor',
            ], 'invoiceForm');
    }

    public function test_changing_pricing_tier_recalculates_existing_products_in_invoice(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-003',
            'customer_type' => 'non_member',
            'customer_id' => $this->customer->id,
            'pricing_tier' => 'toko',
            'discount_mode' => 'none',
            'subtotal' => 20000,
            'grand_total' => 20000,
            'status' => 'pending',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'snapshot_sku' => $this->product->sku,
            'snapshot_name' => $this->product->nama,
            'quantity' => 2,
            'unit_price' => 20000,
            'normal_price' => 40000,
            'item_discount' => 0,
            'discount_price' => 40000,
        ]);

        $this->actingAs($this->user);

        // Mount form in edit mode
        $component = Livewire::withQueryParams(['invoice' => $invoice->id])
            ->test(GenerateInvoice::class)
            ->assertSchemaStateSet([
                'pricing_tier' => 'toko',
            ], 'invoiceForm');

        // Verify initial prices in toko tier
        $products = array_values($component->get('data.products'));
        $this->assertEquals('20.000', $products[0]['unit_price']);
        $this->assertEquals('40.000', $products[0]['normal_price']);

        // Switch to Vendor tier: unit_price should become 15.000, normal_price 30.000
        $component->set('data.pricing_tier', 'vendor');
        $products = array_values($component->get('data.products'));
        $this->assertEquals('15.000', $products[0]['unit_price']);
        $this->assertEquals('30.000', $products[0]['normal_price']);

        // Switch to Dekorator tier: unit_price should become 12.000, normal_price 24.000
        $component->set('data.pricing_tier', 'dekor');
        $products = array_values($component->get('data.products'));
        $this->assertEquals('12.000', $products[0]['unit_price']);
        $this->assertEquals('24.000', $products[0]['normal_price']);

        // Save the invoice via generate action
        $component->callAction('generate');

        $invoice->refresh();
        $this->assertEquals('dekor', $invoice->pricing_tier);
        $this->assertEquals(24000, (float) $invoice->grand_total);
        $this->assertEquals(12000, (float) $invoice->regularItems()->first()->unit_price);
    }
}
