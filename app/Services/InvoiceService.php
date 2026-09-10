<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceService
{
    /**
     * Generează factura PDF.
     */
    public function generate(Order $order)
    {
        return Pdf::loadView(
            'invoices.pdf',
            [
                'order' => $order,
            ]
        )
        ->setPaper('a4', 'landscape');
    }
}