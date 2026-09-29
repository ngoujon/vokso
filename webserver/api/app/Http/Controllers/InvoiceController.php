<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\FacturX\FacturXService;
use App\Services\FacturX\InvoiceSellerConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Factures émises du temps des anciens abonnements. Le service est gratuit :
 * plus aucune facture n'est émise, mais celles-ci restent consultables
 * (conservation légale de 10 ans). Le PDF/A-3 Factur-X n'est jamais stocké :
 * il est reconstruit à la demande à partir des données figées en base.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::where('user_id', $request->user()->id)
            ->orderByDesc('issued_at')
            ->get(['id', 'number', 'issued_at', 'currency', 'plan', 'description', 'amount_total']);

        return response()->json(['data' => $invoices]);
    }

    public function download(Request $request): Response
    {
        $invoice = Invoice::where('id', (int) $request->query('id', 0))
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $invoice) {
            return $this->error('Facture introuvable.', 404);
        }

        $seller = InvoiceSellerConfig::fromConfig();
        if ($seller === null) {
            return $this->error('Le vendeur n\'est pas encore configuré, impossible de régénérer la facture.', 503);
        }

        try {
            $result = (new FacturXService())->generate([
                'number' => $invoice->number,
                'issue_date' => $invoice->issued_at->toDateTimeImmutable(),
                'currency' => $invoice->currency,
                'seller' => $seller,
                'buyer' => json_decode((string) $invoice->client_snapshot, true) ?? [],
                'line_description' => $invoice->description,
                'amount_excl_tax' => $invoice->amount_excl_tax,
                'vat_rate' => $invoice->vat_rate,
                'vat_exempt' => (float) $invoice->vat_rate <= 0.0,
                'vat_exemption_reason' => $invoice->vat_exemption_reason,
                'amount_tax' => $invoice->amount_tax,
                'amount_total' => $invoice->amount_total,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return $this->error('Impossible de générer la facture pour le moment.', 500);
        }

        return response($result['pdf'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"',
        ]);
    }
}
