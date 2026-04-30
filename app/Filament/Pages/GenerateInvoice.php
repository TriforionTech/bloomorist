<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use App\Models\Membership;
use App\Models\Customer;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Support\Number;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\View;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Support\HtmlString;

class GenerateInvoice extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.generate-invoice';
    protected static ?string $title = 'Create Invoice';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-plus';
    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    public array $data = [];

    // Cache untuk mengurangi database queries
    protected array $productPriceCache = [];
    protected array $membershipDiscountCache = [];

    public function mount(): void
    {
        $this->getSchema('invoiceForm')->fill();
    }

     
    // Helper method untuk get product price dengan cache
    protected function getProductPrice(int $productId): float
    {
        if (!isset($this->productPriceCache[$productId])) {
            $this->productPriceCache[$productId] = Product::whereKey($productId)->value('harga_jual_barang') ?? 0;
        }
        return $this->productPriceCache[$productId];
    }

    // Helper method untuk get membership discount dengan cache
    protected function getMembershipDiscount(int $membershipId): float
    {
        if (!isset($this->membershipDiscountCache[$membershipId])) {
            $this->membershipDiscountCache[$membershipId] = Membership::whereKey($membershipId)->value('besaran_diskon_persen') ?? 0;
        }
        return $this->membershipDiscountCache[$membershipId];
    }

    // Fungsi untuk generate nomor invoice
    protected function generateInvoiceNumber(): string
    {
        // Format: YYMMDD (contoh dari PDF: 191225)
        return now()->format('dmy') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    // Fungsi untuk membuat  field readonly jika customer type = member
    protected function isMember(Get $get): bool
    {
        return $get('customer_type') === 'member';        
    }

    // Fungsi untuk membuat placeholder dinamis jika customer type = non-member
    protected function dynamicPlaceholder(Get $get, string $text): ?string
    {
        return $this->isMember($get) ? null : $text;
    }

    // Fungsi Perhitungan Layer 1
    // Dipakai pada repeater
    private function calculateItemPrices(Get $get, Set $set): void {
        $productId = $get('product_id');
        $quantity = (int) ($get('quantity') ?? 1);
        
        // Dapatkan state dari parent
        $customerType = $get('../../customer_type');
        $discountModeNonMember = $get('../../discount_mode');
        $discountModeMember = $get('../../discount_mode_member');
        $membershipId = $get('../../membership_id');

        if (! $productId) {
            $set('normal_price', 0);
            $set('discount_price', 0);
            return;
        }

        // ambil harga produk dari cache
        $price = $this->getProductPrice($productId);
        
        // harga 1 produk
        $set('unit_price', Number::format($price, locale: 'id'));
        
        // harga total sebelum diskon
        $normal = $price * $quantity;
        
        // --- LOGIKA PENENTUAN DISKON ---
        $diskonPersen = 0;

        // LOGIC DISKON
        if ($customerType === 'member') {
            if ($discountModeMember) {
                // Prioritas 1: Custom diskon per produk untuk member jika toggle aktif
                $diskonPersen = (float) ($get('item_discount') ?? 0);
            } else {
                // Prioritas 2: Diskon default dari database membership
                $diskonPersen = $membershipId ? $this->getMembershipDiscount($membershipId) : 0;
            }
        } elseif ($customerType === 'non_member') {
            if ($discountModeNonMember === 'global') {
                // Ambil dari global custom discount
                $diskonPersen = (float) ($get('../../custom_discount') ?? 0);
            } elseif ($discountModeNonMember === 'per_item') {
                // Ambil dari diskon per baris item
                $diskonPersen = (float) ($get('item_discount') ?? 0);
            }
        }

        $discountSubtotal = $normal - ($normal * $diskonPersen / 100);

        $set('normal_price', Number::format($normal, locale: 'id'));
        $set('discount_price', Number::format($discountSubtotal, locale: 'id'));
    }

    // Fungsi Perhitungan Layer 2
    // Dipakai saat ada trigger dari luar repeater (ganti customer_type, mode diskon, dll)
    private function recalculateAllProducts (Get $get, Set $set): void {
        $products = $get('products') ?? [];

        $customerType = $get('customer_type');
        $membershipId = $get('membership_id');
        $discountMode = $get('discount_mode');
        $discountModeMember = $get('discount_mode_member');
        $globalCustomDiscount = $get('custom_discount');

        foreach ($products as $index => $product) {
            $productId = $product['product_id'] ?? null;
            $quantity = (int) ($product['quantity'] ?? 1);
            
            if ($productId) {
                $price = $this->getProductPrice($productId);
                $normal = $price * $quantity;
                
                $diskonPersen = 0;

                // Terapkan logika yang sama persis untuk iterasi array
                if ($customerType === 'member') {
                    if ($discountModeMember) {
                        $diskonPersen = (float) ($product['item_discount'] ?? 0);
                    } else {
                        $diskonPersen = $membershipId ? $this->getMembershipDiscount($membershipId) : 0;
                    }
                } elseif ($customerType === 'non_member') {
                    if ($discountMode === 'global') {
                        $diskonPersen = (float) ($globalCustomDiscount ?? 0);
                    } elseif ($discountMode === 'per_item') {
                        $diskonPersen = (float) ($product['item_discount'] ?? 0);
                    }
                }

                $discountSubtotal = $normal - ($normal * $diskonPersen / 100);

                $set("products.{$index}.unit_price", Number::format($price, locale:'id'));
                $set("products.{$index}.normal_price", Number::format($normal, locale:'id'));
                $set("products.{$index}.discount_price", Number::format($discountSubtotal, locale:'id'));
            }
        }
    }


    // SCHEMA BUILDERS
    protected function setCustomerTypeSection(): Section{
        return Section::make('Customer Type')
        ->icon('heroicon-o-user-circle')
        ->description('Tentukan kategori pelanggan untuk menyesuaikan harga, benefit, dan aturan transaksi.')
        ->schema([
            Radio::make('customer_type')
                ->label('Select Customer Type')
                ->options([
                    'member' => 'Member',
                    'non_member' => 'Non-Member',
                ])
                ->default('member')
                ->inline()
                ->live()                            
                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                $set('member_id', null);
                $set('name', null);
                $set('alias', null);
                $set('address', null);
                $set('city', null);
                $set('province', null);
                $set('country', 'Indonesia');
                $set('email', null);
                $set('phone_number', null);
                $set('membership_id', null);
                
                $set('products', [
                    [
                        'product_id' => null, 
                        'quantity' => 1,
                        'unit_price' => 0,
                        'normal_price' => 0,
                        'item_discount' => 0,
                        'discount_price' => 0,
                    ]
                ]);

                $this->recalculateAllProducts($get, $set);
            }),

            Select::make('member_id')
                ->label('Search Member')
                ->loadingMessage('Loading members...')
                ->searchable(['nama', 'alias'])
                ->getSearchResultsUsing(function (string $search) {
                    return Customer::query()
                        ->where('nama', 'like', "%{$search}%")
                        ->orWhere('alias', 'like', "%{$search}%")
                        ->limit(100)
                        ->get()
                        ->mapWithKeys(function ($member) {
                            return [
                                $member->id => "{$member->nama} [{$member->alias}]"
                            ];
                        });
                })
                ->getOptionLabelUsing(function ($value) {
                    $member = Customer::query()->find($value);
                    return $member
                        ? "{$member->nama} [{$member->alias}]"
                        : null;
                })
                ->searchingMessage('Searching members...')
                ->searchPrompt('Search by name or alias')
                ->preload()
                ->noSearchResultsMessage('No members found.')
                ->noOptionsMessage('No members available.')
                // ->visible(fn (Get $get) => $this->isMember($get))
                ->live()
                ->native(false)
                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                    // Jika admin memilih member, tarik datanya dari DB
                    if ($state) {
                        $member = Customer::query()->find($state);
                        
                        if ($member) {
                            // Autofill semua field
                            $set('name', $member->nama);
                            $set('alias', $member->alias);
                            $set('address', $member->alamat);
                            $set('city', $member->kota);
                            $set('province', $member->provinsi);
                            $set('country', $member->negara);
                            $set('email', $member->email);
                            $set('phone_number', $member->nomor_hp);
                            $set('membership_id', $member->membership_id);
                        }
                    } else {
                        // Clear dropdown, bersihkan field
                        $set('name', null);
                        $set('alias', null);
                        $set('address', null);
                        $set('city', null);
                        $set('province', null);
                        $set('country', null);
                        $set('email', null);
                        $set('phone_number', null);
                        $set('membership_id', null);                                    
                    }

                    $this->recalculateAllProducts($get, $set);
                })
                ->columnSpanFull(),
        ]);
    }

    protected function setCustomerInformationSection(): Section{
        return Section::make('Customer Information')
        ->icon('heroicon-o-identification')
        ->description('Masukkan data pelanggan yang diperlukan untuk identifikasi dan keperluan transaksi.')
        ->columns(2)
        ->schema([
            TextInput::make('name')
                ->required()
                ->placeholder(fn (Get $get) => 
                    $this->dynamicPlaceholder($get, 'Enter customer name')
                )
                ->readOnly(fn (Get $get) => $this->isMember($get)),

            TextInput::make('alias')
                ->placeholder(fn (Get $get) => 
                    $this->dynamicPlaceholder($get, 'Enter customer alias (optional)')
                )
                ->readOnly(fn (Get $get) => $this->isMember($get)),

            Textarea::make('address')
                ->columnSpanFull()
                ->required()
                ->placeholder(fn (Get $get) => 
                    $this->dynamicPlaceholder($get, 'Enter customer address')
                )
                ->readOnly(fn (Get $get) => $this->isMember($get)),

            TextInput::make('city')
                ->placeholder(fn (Get $get) => 
                    $this->dynamicPlaceholder($get, 'Enter customer city')
                )
                ->readOnly(fn (Get $get) => $this->isMember($get)),

            TextInput::make('province')
                ->placeholder(fn (Get $get) => 
                    $this->dynamicPlaceholder($get, 'Enter customer province')
                )
                ->readOnly(fn (Get $get) => $this->isMember($get)),

            TextInput::make('country')
                ->readOnly(fn (Get $get) => $this->isMember($get))
                ->default(fn (Get $get) => 
                    $get('customer_type') !== 'member' ? 'Indonesia' : null
                )
                ->afterStateHydrated(function (Set $set, Get $get, $state) {
                    if ($get('customer_type') !== 'member' && blank($state)) {
                        $set('country', 'Indonesia');
                    }
                }),

            TextInput::make('email')
                ->email()
                ->readOnly(fn (Get $get) => $this->isMember($get))
                ->placeholder(fn (Get $get) => 
                    $this->dynamicPlaceholder($get, 'example@domain.com')
                ),

            TextInput::make('phone_number')
                ->required()
                ->tel()
                ->rule('regex:/^[0-9+\-\s]+$/')
                ->readOnly(fn (Get $get) => $this->isMember($get))
                ->placeholder(fn (Get $get) => 
                    $this->dynamicPlaceholder($get, 'e.g., 081234567890')
                ),

            Select::make('membership_id')
                ->label('Membership Category')
                ->options(Membership::query()->pluck('nama', 'id'))
                ->searchable()
                ->preload()
                ->required(fn (Get $get) => $this->isMember($get))
                ->live(debounce: 300)
                ->visible(fn (Get $get) => $this->isMember($get))
                ->disabled(fn (Get $get) => $this->isMember($get))
                ->dehydrated(),
        ]);
    }

    protected function setProductListSection(): Section{
        return Section::make('Product List')
        ->icon('heroicon-o-shopping-bag')
        ->description('Pilih produk yang akan dimasukkan ke dalam transaksi beserta jumlahnya. Pastikan untuk memeriksa kembali harga dan diskon yang diterapkan sebelum generate invoice.')
        ->schema([
            Toggle::make('discount_mode_member')
                ->label('Custom Discount')
                ->visible(fn (Get $get) => $this->isMember($get))
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => $this->recalculateAllProducts($get, $set)),

            Radio::make('discount_mode')
                ->label('Discount Applied')
                ->options([
                    'none' => 'No Discount',
                    'global' => 'Global (All Products)',
                    'per_item' => 'Per Product (Custom)',
                ])
                ->default('none')
                ->inline()
                ->live()
                ->visible(fn (Get $get) => !$this->isMember($get))
                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                    // Jika admin berpindah mode, bersihkan field diskon yang tidak relevan agar tidak bocor
                    if ($state !== 'global') {
                        $set('custom_discount', null);
                    }
                    
                    if ($state !== 'per_item') {
                        $products = $get('products') ?? [];
                        foreach ($products as $index => $product) {
                            $set("products.{$index}.item_discount", null);
                        }
                    }
                    $this->recalculateAllProducts($get, $set);
                }),

            TextInput::make('custom_discount')
                ->label('Global Discount (%)')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->extraInputAttributes([
                    'min' => 0, 
                    'max' => 100, 
                    'oninput' => 'this.value = this.value.slice(0, 3);'
                ])
                ->maxLength(3)
                ->placeholder('Enter global discount for non-members')
                ->suffix('%')
                ->live(onBlur: true) // Gunakan onBlur agar tidak terlalu sering me-render saat mengetik
                ->required(fn (Get $get) => $get('discount_mode') === 'global')
                ->visible(fn (Get $get) => !$this->isMember($get) && $get('discount_mode') === 'global')
                ->afterStateUpdated(fn (Get $get, Set $set) => $this->recalculateAllProducts($get, $set)),                        

            Repeater::make('products')
                ->columns(fn (Get $get) => 
                    $get('discount_mode') === 'per_item' && !$this->isMember($get) || 
                    $get('discount_mode_member') && $this->isMember($get)
                    ? 6 : 5)
                ->schema([
                    Select::make('product_id')
                        ->label('Item')
                        ->options(
                            Product::query()
                                ->where(function ($query) {
                                    $query->whereNotIn('nama_barang', ['Wrapping', 'Box']);
                                })
                                ->orderBy('nama_barang')
                                ->pluck('nama_barang', 'id')
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(debounce: 300)
                        ->native(false)
                        ->disableOptionWhen(function (string $value, Get $get) {
                            // ambil semua data dari repeater 'products'
                            $allRepeaterItems = $get('../../products') ?? [];
                            
                            // ambil semua produk yang telah dipilih
                            $selectedProductIds = collect($allRepeaterItems)
                                ->pluck('product_id')
                                ->filter() // Buang yang masih null/kosong
                                ->toArray();

                            // ambil product_id yang sedang terisi di baris skrg
                            $currentSelectedId = $get('product_id');

                            // disable jika
                            // - ID-nya sudah ada di keranjang ($selectedProductIds)
                            // - DAN ID-nya BUKAN milik baris ini (agar pilihan sendiri tidak ikut ter-disable)
                            return in_array($value, $selectedProductIds) && $value != $currentSelectedId;
                        })
                        ->afterStateUpdated(fn (Get $get, Set $set) => $this->calculateItemPrices($get, $set)),
                    
                    TextInput::make('quantity')
                        ->label('Qty')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->extraInputAttributes(['min' => 1])
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Get $get, Set $set) => $this->calculateItemPrices($get, $set)),

                    TextInput::make('unit_price')
                        ->label('Item Price')
                        ->prefix('Rp') 
                        ->readOnly()
                        ->default(0)
                        ->dehydrated()
                        ->formatStateUsing(fn ($state) => $state ? Number::format($state, locale: 'id') : '0')
                        ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state)),

                    TextInput::make('normal_price')
                        ->label('Normal Price')
                        ->prefix('Rp')
                        ->readOnly()
                        ->default(0)
                        ->dehydrated()
                        ->formatStateUsing(fn ($state) => $state ? Number::format($state, locale: 'id') : '0')
                        ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state)),

                    // field diskon per item
                    TextInput::make('item_discount')
                        ->label('Disc (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->maxLength(3)
                        ->extraInputAttributes([
                            'min' => 0, 
                            'max' => 100, 
                            'oninput' => 'this.value = this.value.slice(0, 3);'
                        ])
                        ->suffix('%')
                        ->default(0)
                        ->live(onBlur: true)
                        ->visible(
                            fn (Get $get) => 
                            $get('../../discount_mode') === 'per_item' && $get('../../customer_type') === 'non_member' || 
                            $get('../../discount_mode_member') && $get('../../customer_type') === 'member')
                        ->afterStateUpdated(fn (Get $get, Set $set) => $this->calculateItemPrices($get, $set)),

                    TextInput::make('discount_price')
                        ->label('Discount Price')
                        ->prefix('Rp')
                        // ->numeric()
                        ->readOnly()
                        ->default(0)
                        ->dehydrated()
                        ->formatStateUsing(fn ($state) => $state ? Number::format($state, locale: 'id') : '0')
                        ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state))
                ])
                ->addActionLabel('Add Product')
                ->defaultItems(1)
                ->reorderable(false)
                ->collapsible(),
        ]);
    }

    protected function setAdditionalSection(): Section{
        return Section::make('Add Ons')
            ->description('Tambahkan ongkos kirim dan perlengkapan tambahan untuk pesanan ini.')
            ->icon('heroicon-o-plus-circle')
            ->columns(1)
            ->schema([
                TextInput::make('ongkir')
                    ->label('Ongkos Kirim')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999999999)
                    ->maxLength(9)
                    ->extraInputAttributes([
                        'min' => 0, 
                        'max' => 999999999, 
                        'oninput' => 'this.value = this.value.slice(0, 9);'
                    ])
                    ->prefix('Rp')
                    ->default(0)
                    ->live(onBlur: true)
                    ->required(),

                Grid::make(2)
                ->schema([
                    // Box
                    Fieldset::make('Packaging Box')
                        ->schema([
                            Toggle::make('use_box')
                                ->label('Tambahkan Box')
                                ->inline(false)
                                ->live()
                                ->helperText(fn (Get $get) => $get('use_box') 
                                    ? 'Biaya Per Box: Rp ' . number_format($get('single_box_fee'), 0, ',', '.') 
                                    : 'Tanpa Box'
                                )
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($state) {
                                        // Fetch harga dari DB
                                        $hargaBox = Product::query()->where('nama_barang', 'Box')->value('harga_jual_barang') ?? 0;
                                        $set('box_unit_price', $hargaBox);
                                        $set('box_qty', 1);
                                        $set('single_box_fee', $hargaBox);
                                        $set('box_fee', $hargaBox);
                                    } else {
                                        $set('box_qty', null);
                                        $set('box_unit_price', 0);
                                        $set('single_box_fee', 0);
                                        $set('box_fee', 0);
                                    }
                                }),
                            TextInput::make('box_qty')
                                ->label('Jumlah Box')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->live(onBlur: true)
                                ->visible(fn (Get $get) => $get('use_box')) // Hanya muncul jika toggle nyala
                                ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                    $qty = (int) ($state ?: 0);
                                    $price = (int) $get('box_unit_price');
                                    $set('box_fee', $qty * $price);
                                })
                                ->helperText(fn (Get $get) => 'Subtotal Box: Rp ' . number_format((int) $get('box_fee'), 0, ',', '.')),

                            // Hidden state untuk menyimpan perhitungan
                            Hidden::make('box_unit_price')->default(0),
                            Hidden::make('box_fee')->default(0),
                        ])
                        ->columns(1), // Memastikan elemen di dalam fieldset berbaris vertikal ke bawah

                    // Wrapping
                    Fieldset::make('Kertas Wrapping')
                        ->schema([
                            Toggle::make('use_wrapping')
                                ->label('Tambahkan Wrapping')
                                ->inline(false)
                                ->live()
                                ->helperText(fn (Get $get) => $get('use_wrapping') 
                                    ? 'Biaya Wrapping: Rp ' . number_format($get('single_wrapping_fee'), 0, ',', '.') 
                                    : 'Tanpa Wrapping'
                                )
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($state) {
                                        $hargaWrap = Product::query()->where('nama_barang', 'Wrapping')->value('harga_jual_barang') ?? 0;
                                        $set('wrap_unit_price', $hargaWrap);
                                        $set('wrapping_qty', 1);
                                        $set('single_wrapping_fee', $hargaWrap);
                                        $set('wrapping_fee', $hargaWrap);
                                    } else {
                                        $set('wrapping_qty', null);
                                        $set('wrap_unit_price', 0);
                                        $set('single_wrapping_fee', 0);
                                        $set('wrapping_fee', 0);
                                    }
                                }),
                            TextInput::make('wrapping_qty')
                                ->label('Jumlah Wrapping')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->live(onBlur: true)
                                ->visible(fn (Get $get) => $get('use_wrapping'))
                                ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                    $qty = (int) ($state ?: 0);
                                    $price = (int) $get('wrap_unit_price');
                                    $set('wrapping_fee', $qty * $price);
                                })
                                ->helperText(fn (Get $get) => 'Subtotal Wrapping: Rp ' . number_format((int) $get('wrapping_fee'), 0, ',', '.')),

                            Hidden::make('wrap_unit_price')->default(0),
                            Hidden::make('wrapping_fee')->default(0),
                        ])
                        ->columns(1),
                ]),
            ]);
    }

    protected function setInvoiceDetailSection(): Section
    {
        return Section::make('Invoice Details')
            ->icon('heroicon-o-document-text')
            ->description('Periksa ringkasan pesanan sebelum generate invoice. Anda juga dapat melakukan custom nomor dan tanggal penerbitan/jatuh tempo sesuai kebutuhan.')
            ->schema([
    
                // Ringkasan Transaksi
                View::make('filament.forms.invoice-summary')
                    ->columnSpanFull()
                    ->live(),
    
                // Custom Fields
                Grid::make(3)
                    ->schema([
                        TextInput::make('custom_invoice_number')
                            ->label('Invoice Number')
                            ->placeholder(fn () => $this->generateInvoiceNumber())
                            ->helperText('Leave blank to auto-generate')
                            ->numeric()
                            ->maxLength(10)
                            ->extraInputAttributes([
                                'min'     => 0,
                                'max'     => 9999999999,
                                'oninput' => 'this.value = this.value.slice(0, 10);',
                            ]),
    
                        DatePicker::make('issued_date')
                            ->label('Issued On')
                            ->placeholder(now()->format('d M Y'))
                            ->helperText('Leave blank to use today')
                            ->native(false)
                            ->displayFormat('d M Y'),
    
                        DatePicker::make('due_date')
                            ->label('Due By')
                            ->placeholder(now()->format('d M Y'))
                            ->helperText('Leave blank to use today')
                            ->native(false)
                            ->displayFormat('d M Y'),
                    ]),
            ]);
    }
    

    // MAIN WRAPPER
    public function invoiceForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                $this->setCustomerTypeSection(),
                $this->setCustomerInformationSection(),
                $this->setProductListSection(),
                $this->setAdditionalSection(),
                $this->setInvoiceDetailSection(),
            ]);
    }


    // Download Logic
    public function getHeaderActions(): array
    {
        return [];
    }

    public function resetFormAction(): Action
    {
        return Action::make('reset')
            ->label('Reset')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Reset Form')
            ->modalDescription('Semua data yang sudah Anda isi akan dihapus. Lanjutkan?')
            ->action(fn () => $this->getSchema('invoiceForm')->fill());
    }

    public function generateAction(): Action{
        return Action::make('generate')
                ->label('Generate Invoice')
                ->icon('heroicon-o-document-arrow-down')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Generate Invoice')
                ->modalDescription(function () {
                    // validasi minimal ada 1 product yang diinsert
                    // cegah user generate invoice kosong
                    $products = $this->data['products'] ?? [];
                    $hasProduct = collect($products)->contains(
                        fn($item) => !empty($item['product_id'])
                    );

                    if (!$hasProduct) {
                        $warnings[] = '⚠️ Belum ada produk yang ditambahkan. Harap isi minimal satu produk sebelum generate invoice.';
                    }

                    // validasi issued on dan due by
                    $issuedDate = $this->data['issued_date'] ?? null;
                    $dueDate    = $this->data['due_date'] ?? null;

                    // if (empty($issuedDate)) {
                    //     $warnings[] = '📅 Issued On tidak diisi — akan menggunakan tanggal hari ini (' . now()->format('d M Y') . ').';
                    // }

                    // if (empty($dueDate)) {
                    //     $warnings[] = '📅 Due By tidak diisi — akan menggunakan tanggal hari ini (' . now()->format('d M Y') . ').';
                    // }

                    if (!empty($issuedDate) && !empty($dueDate)) {
                        $issued = Carbon::parse($issuedDate);
                        $due    = Carbon::parse($dueDate);

                        if ($due->lt($issued)) {
                            $warnings[] = '⚠️ Due By (' . $due->format('d M Y') . ') lebih awal dari Issued On (' . $issued->format('d M Y') . '). Pastikan tanggalnya sudah benar.';
                        }
                    }

                    // validasi ongkir = 0
                    $ongkir = (float) ($this->data['ongkir'] ?? 0);

                    if ($ongkir == 0) {
                        $warnings[] = '⚠️ Ongkos kirim saat ini bernilai Rp 0. Pastikan ini sudah disengaja.';
                    }

                    if (!empty($warnings)) {
                        return new HtmlString(implode('<br><br>', $warnings));
                    }

                    return 'Apakah semua data sudah dipastikan benar? Invoice PDF akan segera dibuat dan diunduh.';
                })
                ->modalSubmitActionLabel('Ya, Generate PDF')
                ->action(function () {
                $data = $this->getSchema('invoiceForm')->getState();
                
                // konfigurasi invoice details
                $invoiceNumber = !empty($data['custom_invoice_number']) 
                    ? $data['custom_invoice_number'] 
                    : $this->generateInvoiceNumber();

                $issuedDate = !empty($data['issued_date']) 
                    ? Carbon::parse($data['issued_date'])->format('d M Y') 
                    : now()->format('d M Y');

                $dueDate = !empty($data['due_date']) 
                    ? Carbon::parse($data['due_date'])->format('d M Y') 
                    : now()->format('d M Y');

                // konfigurasi jenis member
                $isMember = !empty($data['membership_id']);
                $membershipName = '-';
                if ($isMember) {
                    $membershipName = Membership::whereKey($data['membership_id'])->value('nama') ?? 'Member';                        
                } 

                // ambil datanya
                $customer = [
                    'name' => $data['name'] ?? 'Unknown',
                    'alias' => $data['alias'] ?? '',
                    'address' => $data['address'] ?? '-',
                    'city' => $data['city'] ?? '-',
                    'province' => $data['province'] ?? '-',
                    'country' => $data['country'] ?? 'Indonesia',
                    'email' => $data['email'] ?? '-',
                    'phone_number' => $data['phone_number'] ?? '-',
                    'membership' => $membershipName
                ];

                // konfigurasi diskon
                $discountMode = $data['discount_mode'] ?? 'none';
                $hasDiscount = false;
                $isPerItemDiscount = false;

                if ($isMember) {
                    $hasDiscount = true;
                } else {
                    if ($discountMode !== 'none') {
                        $hasDiscount = true;
                    }
                    if ($discountMode === 'per_item') {
                        $isPerItemDiscount = true;
                    }
                }

                // konfigurasi colspan dinamis untuk PDF
                $tableColspan = 4;
                if ($hasDiscount) {
                    $tableColspan += 2;
                    if ($isPerItemDiscount) {
                        $tableColspan += 1;
                    }
                }

                $flags = [
                    'has_discount' => $hasDiscount,
                    'is_per_item_discount' => $isPerItemDiscount,
                    'table_colspan' => $tableColspan,
                ];
                
                // START PROSES
                $products = [];
                $subtotal = 0;

                $cleanNumber = function($val) {
                    return (float) str_replace('.', '', (string) ($val ?? 0));
                };

                foreach ($data['products'] ?? [] as $item) {
                    $productName = Product::whereKey($item['product_id'])->value('nama_barang') ?? 'Unknown Product';
                    
                    $unitPrice = $cleanNumber($item['unit_price']);
                    $normalPrice = $cleanNumber($item['normal_price']);
                    $discountPrice = $cleanNumber($item['discount_price']);
                    $itemDiscountPercent = (float) ($item['item_discount'] ?? 0);
                    
                    $products[] = [
                        'name' => $productName,
                        'unit_price' => $unitPrice,
                        'quantity' => $item['quantity'],
                        'normal_price' => $normalPrice,
                        'item_discount_percent' => $itemDiscountPercent,
                        'discount_price' => $discountPrice,
                        'total' => $discountPrice, // total per baris = harga yang sudah didiskon
                    ];

                    // // Hitung tax -> sementara belum ada perhitungan tax
                    // $taxAmount = 0;
                    // if (isset($item['tax']) && $item['tax'] > 0) {
                    //     $taxAmount = $itemTotal * ($item['tax'] / 100);
                    //     $itemTotal += $taxAmount;
                    //     $totalTax += $taxAmount;
                    // }
                    
                    $subtotal += $discountPrice;
                }                    

                // hitung add ons dan total akhir
                $ongkir = (float) ($data['ongkir'] ?? 0);
                $box_fee = (float) ($data['box_fee'] ?? 0);
                $wrapping_fee = (float) ($data['wrapping_fee'] ?? 0);

                $grand_total = $subtotal + $ongkir + $box_fee + $wrapping_fee;

                $summary = [
                    'subtotal' => $subtotal,
                    'ongkir' => $ongkir,
                    'box_fee' => $box_fee,
                    'wrapping_fee' => $wrapping_fee,
                    'total' => $grand_total
                ];
                
                // generate
                $pdf = Pdf::loadView('invoice-template', [
                    'invoiceNumber' => $invoiceNumber,
                    'issuedDate' => $issuedDate,
                    'dueDate' => $dueDate,
                    'customer' => $customer,
                    'products' => $products,
                    'summary' => $summary,
                    'flags' => $flags,
                ]);
                
                $pdf->setPaper('A4', 'portrait');
                
                return response()->streamDownload(function () use ($pdf) {
                    echo $pdf->stream();
                }, "INV_Bloomorist_{$invoiceNumber}.pdf");
            });
    }

    // Untuk validasi field kosong atau kondisi lain sebelum generate
    public function validateThenGenerate(): void
    {        
        $this->getSchema('invoiceForm')->getState();
    
        $this->mountAction('generate');
    }
}



// GAK DIPAKE TPI SIMPEN AJA
// public function getHeaderActions(): array
// {
//     return [
//         Action::make('reset')
//             ->color('gray')
//             ->requiresConfirmation()
//             ->modalHeading('Reset Form')
//             ->modalDescription('Semua data yang sudah Anda isi akan dihapus. Lanjutkan?')
//             ->action(fn () => $this->getSchema('invoiceForm')->fill()),

    // Action::make('generate')
    //     ->label('Generate Invoice')
    //     ->icon('heroicon-o-document-arrow-down')
    //     ->requiresConfirmation()
    //     ->modalHeading('Konfirmasi Generate Invoice')
    //     ->modalDescription(function () {
    //         $ongkir = (float) ($this->data['ongkir'] ?? 0);

    //         if ($ongkir == 0) {
    //             return '⚠️ PERINGATAN: Ongkos kirim saat ini bernilai Rp 0. Apakah Anda yakin ingin melanjutkan pencetakan invoice tanpa biaya ongkir?';
    //         }

    //         return 'Apakah semua data sudah dipastikan benar? Invoice PDF akan segera dibuat dan diunduh.';
    //     })
    //     ->modalSubmitActionLabel('Ya, Generate PDF')
    //     ->action(function () {
    //     // $data = $this->data;
    //     $data = $this->getSchema('invoiceForm')->getState();
        
    //     // konfigurasi invoice details
    //     $invoiceNumber = !empty($data['custom_invoice_number']) 
    //         ? $data['custom_invoice_number'] 
    //         : $this->generateInvoiceNumber();

    //     $issuedDate = !empty($data['issued_date']) 
    //         ? Carbon::parse($data['issued_date'])->format('d M Y') 
    //         : now()->format('d M Y');

    //     $dueDate = !empty($data['due_date']) 
    //         ? Carbon::parse($data['due_date'])->format('d M Y') 
    //         : now()->format('d M Y');

    //     // konfigurasi jenis member
    //     $isMember = !empty($data['membership_id']);
    //     $membershipName = '-';
    //     if ($isMember) {
    //         $membershipName = Membership::whereKey($data['membership_id'])->value('nama') ?? 'Member';                        
    //     } 

    //     // ambil datanya
    //     $customer = [
    //         'name' => $data['name'] ?? 'Unknown',
    //         'alias' => $data['alias'] ?? '',
    //         'address' => $data['address'] ?? '-',
    //         'city' => $data['city'] ?? '-',
    //         'province' => $data['province'] ?? '-',
    //         'country' => $data['country'] ?? 'Indonesia',
    //         'email' => $data['email'] ?? '-',
    //         'phone_number' => $data['phone_number'] ?? '-',
    //         'membership' => $membershipName
    //     ];

    //     // konfigurasi diskon
    //     $discountMode = $data['discount_mode'] ?? 'none';
    //     $hasDiscount = false;
    //     $isPerItemDiscount = false;

    //     if ($isMember) {
    //         $hasDiscount = true;
    //     } else {
    //         if ($discountMode !== 'none') {
    //             $hasDiscount = true;
    //         }
    //         if ($discountMode === 'per_item') {
    //             $isPerItemDiscount = true;
    //         }
    //     }

    //     // konfigurasi colspan dinamis untuk PDF
    //     $tableColspan = 4;
    //     if ($hasDiscount) {
    //         $tableColspan += 2;
    //         if ($isPerItemDiscount) {
    //             $tableColspan += 1;
    //         }
    //     }

    //     $flags = [
    //         'has_discount' => $hasDiscount,
    //         'is_per_item_discount' => $isPerItemDiscount,
    //         'table_colspan' => $tableColspan,
    //     ];
        
    //     // START PROSES
    //     $products = [];
    //     $subtotal = 0;

    //     $cleanNumber = function($val) {
    //         return (float) str_replace('.', '', (string) ($val ?? 0));
    //     };

    //     foreach ($data['products'] ?? [] as $item) {
    //         $productName = Product::whereKey($item['product_id'])->value('nama_barang') ?? 'Unknown Product';
            
    //         $unitPrice = $cleanNumber($item['unit_price']);
    //         $normalPrice = $cleanNumber($item['normal_price']);
    //         $discountPrice = $cleanNumber($item['discount_price']);
    //         $itemDiscountPercent = (float) ($item['item_discount'] ?? 0);
            
    //         $products[] = [
    //             'name' => $productName,
    //             'unit_price' => $unitPrice,
    //             'quantity' => $item['quantity'],
    //             'normal_price' => $normalPrice,
    //             'item_discount_percent' => $itemDiscountPercent,
    //             'discount_price' => $discountPrice,
    //             'total' => $discountPrice, // total per baris = harga yang sudah didiskon
    //         ];

    //         // // Hitung tax -> sementara belum ada perhitungan tax
    //         // $taxAmount = 0;
    //         // if (isset($item['tax']) && $item['tax'] > 0) {
    //         //     $taxAmount = $itemTotal * ($item['tax'] / 100);
    //         //     $itemTotal += $taxAmount;
    //         //     $totalTax += $taxAmount;
    //         // }
            
    //         $subtotal += $discountPrice;
    //     }                    

    //     // hitung add ons dan total akhir
    //     $ongkir = (float) ($data['ongkir'] ?? 0);
    //     $box_fee = (float) ($data['box_fee'] ?? 0);
    //     $wrapping_fee = (float) ($data['wrapping_fee'] ?? 0);

    //     $grand_total = $subtotal + $ongkir + $box_fee + $wrapping_fee;

    //     $summary = [
    //         'subtotal' => $subtotal,
    //         'ongkir' => $ongkir,
    //         'box_fee' => $box_fee,
    //         'wrapping_fee' => $wrapping_fee,
    //         'total' => $grand_total
    //     ];
        
    //     // generate
    //     $pdf = Pdf::loadView('invoice-template', [
    //         'invoiceNumber' => $invoiceNumber,
    //         'issuedDate' => $issuedDate,
    //         'dueDate' => $dueDate,
    //         'customer' => $customer,
    //         'products' => $products,
    //         'summary' => $summary,
    //         'flags' => $flags,
    //     ]);
        
    //     $pdf->setPaper('A4', 'portrait');
        
    //     return response()->streamDownload(function () use ($pdf) {
    //         echo $pdf->stream();
    //     }, "Invoice_Bloomorist_{$invoiceNumber}.pdf");
    // })
//     ];
// }