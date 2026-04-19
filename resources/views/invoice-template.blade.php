<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - {{ $invoiceNumber }}</title>
    <style>{!! file_get_contents(public_path('css/invoice_pdf.css')) !!}</style>    
</head>
<body>
    <div class="container">
        
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="header-left">
                <img src="{{ public_path('img/BloomoristLogo.png') }}" alt="Logo" class="logo-image">
                <span class="brand-text">BLOOMORIST</span>
            </div>
            
            <div class="header-right invoice-details">
                <div class="invoice-title">Invoice: {{ $invoiceNumber }}</div>
                <div>Issued on: {{ $issuedDate }}</div>
                <div>Due by: {{ $dueDate }}</div>
            </div>
        </div>

        <!-- From and To Section -->
        <div class="info-section">
            <!-- From -->
            <div class="from-section">
                <div class="section-label">From</div>
                <div class="company-name">BLOOMORIST</div>
                <div class="address-block">
                    <div>JL. Purwosenjoto RT01/RW03, Dusun Buludendeng,</div>
                    <div>Desa Bulukerto, Kecamatan Bumiaji, Kota Batu</div>
                    <div>65334</div>
                    <div>Indonesia</div>
                    <div class="contact-info">bloomorist@gmail.com</div>
                    <div>+6289660600406</div>
                </div>
            </div>

            <!-- To -->
            <div class="to-section">
                <div class="section-label">To</div>
                <div class="company-name">{{ $customer['name'] }}</div>
                <div class="address-block">
                    @if(!empty($customer['alias']))
                        <div class="font-medium">{{ $customer['alias'] }}</div>
                    @endif
                    <div>{{ $customer['address'] }}</div>
                    <div>{{ $customer['city'] }}, {{ $customer['province'] }}</div>
                    <div>{{ $customer['country'] }}</div>
                    <div class="contact-info">{{ $customer['email'] }}</div>
                    <div>{{ $customer['phone_number'] }}</div>
                    @if($customer['membership'] !== '-')
                        <div>
                            {{ $customer['membership'] }} Membership
                        </div>
                     @else
                        <div>Non-Membership</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Product Table -->
        <div class="product-table-wrapper">
            <table class="product-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Qty</th>
                        @if($flags['has_discount'])
                            <th class="text-right">Normal Price</th>
                            @if($flags['is_per_item_discount'])
                                <th class="text-right">Disc (%)</th>
                            @endif
                            <th class="text-right">Discount Price</th>
                        @endif
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                    <tr>
                        <td>{{ $product['name'] }}</td>
                        <td class="text-right">Rp {{ number_format($product['unit_price'], 0, ',', '.') }}</td>
                        <td class="text-right">{{ $product['quantity'] }}</td>
                        @if($flags['has_discount'])
                            <td class="text-right">Rp. {{ number_format($product['normal_price'], 0, ',', '.') }}</td>
                            @if($flags['is_per_item_discount'])
                                <td class="text-right">{{ $product['item_discount_percent'] }}%</td>
                            @endif
                            <td class="text-right">Rp. {{ number_format($product['discount_price'], 0, ',', '.') }}</td>
                        @endif
                        <td class="text-right total-price">Rp. {{ number_format($product['total'], 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="{{ $flags['table_colspan'] }}"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Summary Section -->
        <div class="summary-section">
            <!-- Terms -->
            <div class="terms-column">
                <div class="section-title">Terms</div>
                <div class="terms-content">
                    <div class="bank-name">Bank BCA</div>
                    <div>an. Bigatri Indoflora Pacific</div>
                    <div>No rek : 0191268856</div>
                </div>
            </div>

            <!-- Invoice Summary -->
            <div class="summary-column">
                <div class="section-title">Invoice Summary</div>
                <table class="summary-table">
                    <tr>
                        <td class="label">Subtotal</td>
                        <td class="value">Rp. {{ number_format($summary['subtotal'], 0, ',', '.') }}</td>
                    </tr>
                    @if(isset($summary['ongkir']) && $summary['ongkir'] > 0)
                    <tr>
                        <td class="label">Ongkos Kirim</td>
                        <td class="value">Rp. {{ number_format($summary['ongkir'], 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    
                    @if(isset($summary['box_fee']) && $summary['box_fee'] > 0)
                    <tr>
                        <td class="label">Box Tambahan</td>
                        <td class="value">Rp. {{ number_format($summary['box_fee'], 0, ',', '.') }}</td>
                    </tr>
                    @endif

                    @if(isset($summary['wrapping_fee']) && $summary['wrapping_fee'] > 0)
                    <tr>
                        <td class="label">Biaya Wrapping</td>
                        <td class="value">Rp. {{ number_format($summary['wrapping_fee'], 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td class="label">Total</td>
                        <td class="value">Rp. {{ number_format($summary['total'], 0, ',', '.') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Thank you for your business!</p>
        </div>

    </div>
</body>
</html>