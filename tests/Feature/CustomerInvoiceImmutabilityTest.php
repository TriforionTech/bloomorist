<?php

namespace Tests\Feature;

use App\Filament\Pages\GenerateInvoice;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerInvoiceImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Product $product;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@bloomorist.test',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);

        $this->product = Product::create([
            'sku' => 'FLW-001',
            'nama' => 'Mawar Merah',
            'kategori' => 'bunga',
            'harga_beli' => 10000,
            'harga_jual' => 25000,
            'harga_vendor' => 20000,
            'harga_dekor' => 18000,
            'stok' => 100,
            'is_active' => true,
        ]);

        $membership = \App\Models\Membership::create([
            'nama' => 'Gold',
            'besaran_diskon_persen' => 10,
        ]);

        $this->customer = Customer::create([
            'nama' => 'PT Bunga Cantik',
            'alias' => 'Bunga Cantik',
            'email' => 'cantik@bloomorist.test',
            'alamat' => 'Jl. Kenanga No. 5',
            'kota' => 'Surabaya',
            'provinsi' => 'Jawa Timur',
            'negara' => 'Indonesia',
            'nomor_hp' => '081234567890',
            'tipe_customer' => 'vendor',
            'membership_id' => $membership->id,
        ]);
    }

    public function test_invoice_creation_via_generate_invoice_saves_customer_name_snapshot(): void
    {
        $this->actingAs($this->user);

        Livewire::test(GenerateInvoice::class)
            ->set('data.member_id', $this->customer->id)
            ->set('data.products', [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 20000,
                    'normal_price' => 40000,
                    'discount_price' => 40000,
                    'item_discount' => 0,
                ],
            ])
            ->callAction('generate');

        $invoice = Invoice::latest('id')->first();
        $this->assertNotNull($invoice);
        $this->assertEquals($this->customer->id, $invoice->customer_id);
        $this->assertEquals('PT Bunga Cantik', $invoice->customer_name);
    }

    public function test_deleting_customer_soft_deletes_customer_and_preserves_invoice_relationship(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-TEST01',
            'customer_type' => 'member',
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->nama,
            'pricing_tier' => 'vendor',
            'subtotal' => 40000,
            'grand_total' => 40000,
            'status' => 'pending',
        ]);

        // Customer dihapus (soft delete)
        $this->customer->delete();

        // 1. Data master customer ter-soft-delete (deleted_at tidak null)
        $this->assertSoftDeleted('bl_customers_t', [
            'id' => $this->customer->id,
        ]);

        // 2. Relasi customer() pada Invoice tetap bisa diakses via withTrashed()
        $freshInvoice = $invoice->fresh();
        $this->assertNotNull($freshInvoice->customer);
        $this->assertTrue($freshInvoice->customer->trashed());
        $this->assertEquals('PT Bunga Cantik', $freshInvoice->customer->nama);
        $this->assertEquals('PT Bunga Cantik', $freshInvoice->customer_name);
    }

    public function test_invoice_history_table_still_displays_customer_name_when_customer_is_deleted(): void
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-TEST02',
            'customer_type' => 'member',
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->nama,
            'pricing_tier' => 'vendor',
            'subtotal' => 50000,
            'grand_total' => 50000,
            'status' => 'pending',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'snapshot_sku' => $this->product->sku,
            'snapshot_name' => $this->product->nama,
            'quantity' => 2,
            'unit_price' => 25000,
            'normal_price' => 50000,
            'item_discount' => 0,
            'discount_price' => 50000,
        ]);

        // Soft-delete customer
        $this->customer->delete();

        // Cek halaman Invoice History
        Livewire::test(ListInvoices::class)
            ->assertSuccessful()
            ->assertSee('PT Bunga Cantik')
            ->assertSee('(Customer Diarsipkan)');
    }

    public function test_invoice_view_mode_fills_customer_name_even_if_customer_was_deleted(): void
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-TEST03',
            'customer_type' => 'member',
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->nama,
            'pricing_tier' => 'vendor',
            'subtotal' => 25000,
            'grand_total' => 25000,
            'status' => 'pending',
        ]);

        $this->customer->delete();

        // Buka form view mode
        Livewire::withQueryParams(['invoice' => $invoice->id, 'view' => '1'])
            ->test(GenerateInvoice::class)
            ->assertSchemaStateSet([
                'name' => 'PT Bunga Cantik',
            ], 'invoiceForm');
    }

    public function test_invoice_pdf_download_resolves_customer_name_when_customer_is_deleted(): void
    {
        $this->actingAs($this->user);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-2026-TEST04',
            'customer_type' => 'member',
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->nama,
            'pricing_tier' => 'vendor',
            'subtotal' => 25000,
            'grand_total' => 25000,
            'status' => 'pending',
        ]);

        $this->customer->delete();

        $response = $this->get("/invoice/{$invoice->id}/download");
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_customer_soft_delete_and_restore_via_filament_table(): void
    {
        $this->actingAs($this->user);

        // Hapus customer via action tabel
        Livewire::test(ListCustomers::class)
            ->callTableAction('delete', $this->customer);

        $this->assertSoftDeleted('bl_customers_t', [
            'id' => $this->customer->id,
        ]);

        // Pulihkan customer via action restore
        Livewire::test(ListCustomers::class)
            ->filterTable('trashed', 'only_trashed')
            ->callTableAction('restore', $this->customer);

        $this->assertNotSoftDeleted('bl_customers_t', [
            'id' => $this->customer->id,
        ]);
    }

    public function test_can_create_customer_with_same_email_as_soft_deleted_customer(): void
    {
        $this->actingAs($this->user);

        // Soft delete the existing customer
        $this->customer->delete();
        $this->assertSoftDeleted('bl_customers_t', ['id' => $this->customer->id]);

        // Creating a new customer with the same email should succeed without 500 error
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'nama' => 'Bunga Cantik Baru',
                'email' => $this->customer->email,
                'tipe_customer' => 'toko',
                'alamat' => 'Jl. Baru No. 1',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bl_customers_t', [
            'nama' => 'Bunga Cantik Baru',
            'email' => $this->customer->email,
            'deleted_at' => null,
        ]);
    }

    public function test_cannot_create_customer_with_duplicate_email_of_active_customer(): void
    {
        $this->actingAs($this->user);

        // $this->customer is active
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'nama' => 'Customer Duplikat',
                'email' => $this->customer->email,
                'tipe_customer' => 'toko',
                'alamat' => 'Jl. Duplikat No. 2',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    public function test_can_update_customer_retaining_own_email(): void
    {
        $this->actingAs($this->user);

        Livewire::test(EditCustomer::class, [
            'record' => $this->customer->getRouteKey(),
        ])
            ->fillForm([
                'nama' => 'PT Bunga Cantik Updated',
                'email' => $this->customer->email,
                'tipe_customer' => 'vendor',
                'alamat' => 'Jl. Kenanga No. 5 Baru',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bl_customers_t', [
            'id' => $this->customer->id,
            'nama' => 'Pt Bunga Cantik Updated',
            'email' => $this->customer->email,
        ]);
    }
}
