<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function download(Invoice $invoice)
    {
        $customer = [
            'name' => $invoice->customer->nama ?? 'Unknown',
            'alias' => $invoice->customer->alias ?? '',
            'address' => $invoice->customer->alamat ?? '-',
            'city' => $invoice->customer->kota ?? '-',
            'province' => $invoice->customer->provinsi ?? '-',
            'country' => $invoice->customer->negara ?? 'Indonesia',
            'email' => $invoice->customer->email ?? '-',
            'phone_number' => $invoice->customer->nomor_hp ?? '-',
            'membership' => $invoice->customer->membership->nama ?? ($invoice->customer_type === 'member' ? 'Member' : '-'),
        ];

        $hasDiscount = false;
        $isPerItemDiscount = false;

        if ($invoice->customer_type === 'member') {
            $hasDiscount = true;
        } else {
            if ($invoice->discount_mode !== 'none') {
                $hasDiscount = true;
            }
            if ($invoice->discount_mode === 'per_item') {
                $isPerItemDiscount = true;
            }
        }

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

        // ALL items (including Box/Wrapping) go into the product list
        $products = [];
        foreach ($invoice->items()->with('product')->get() as $item) {
            $productName = $item->product->nama_barang ?? 'Unknown Product';

            $products[] = [
                'name' => $productName,
                'unit_price' => $item->unit_price,
                'quantity' => $item->quantity,
                'normal_price' => $item->normal_price,
                'item_discount_percent' => $item->item_discount,
                'discount_price' => $item->discount_price,
                'total' => $item->discount_price, 
            ];
        }

        $summary = [
            'subtotal' => $invoice->subtotal,
            'discount_total' => $invoice->discount_total,
            'ongkir' => $invoice->ongkir,
            'total' => $invoice->grand_total,
        ];

        $pdf = Pdf::loadView('invoice-template', [
            'invoiceNumber' => $invoice->invoice_number,
            'issuedDate' => $invoice->issued_date ? $invoice->issued_date->format('d M Y') : now()->format('d M Y'),
            'dueDate' => $invoice->due_date ? $invoice->due_date->format('d M Y') : now()->format('d M Y'),
            'customer' => $customer,
            'products' => $products,
            'summary' => $summary,
            'flags' => $flags,
        ]);
        
        $pdf->setPaper('A4', 'portrait');
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, "INV_Bloomorist_{$invoice->invoice_number}.pdf");
    }
}
