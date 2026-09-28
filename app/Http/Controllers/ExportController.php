<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Sale;
use App\Services\BusinessReport;
use App\Support\Money;
use App\Support\SyncLabel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function sales(Request $request, BusinessReport $report): StreamedResponse
    {
        $sales = $report->exportSales($report->filters($request));

        return $this->csv('ventes.csv', ['Date', 'Ticket', 'Client', 'Forfait', 'Montant', 'Méthode', 'Paiement', 'MikroTik'], function ($write) use ($sales) {
            foreach ($sales as $sale) {
                $write($this->saleRow($sale));
            }
        });
    }

    public function salesPdf(Request $request, BusinessReport $report)
    {
        $filters = $report->filters($request);
        $sales = $report->exportSales($filters);

        return Pdf::loadView('reports.sales', [
            'sales' => $sales,
            'from' => $filters['from'],
            'to' => $filters['to'],
            'revenue' => $report->netRevenue($filters['from'], $filters['to'], $filters['zone_id']),
        ])->download('ventes.pdf');
    }

    public function customers(Request $request, BusinessReport $report): StreamedResponse
    {
        $customers = $report->exportCustomers($report->filters($request));

        return $this->csv('clients.csv', ['Nom', 'Téléphone', 'Zone', 'Achats', 'Dernier achat', 'Dernière connexion'], function ($write) use ($customers) {
            foreach ($customers as $customer) {
                $write([
                    $customer['name'],
                    $customer['phone'],
                    $customer['zone'],
                    $customer['purchases'],
                    $customer['last_purchase'] ? (string) $customer['last_purchase'] : '',
                    $customer['last_seen'] ? (string) $customer['last_seen'] : '',
                ]);
            }
        });
    }

    public function tickets(Request $request, BusinessReport $report): StreamedResponse
    {
        $tickets = $report->exportTickets($report->filters($request));

        return $this->csv('tickets.csv', ['Code', 'Forfait', 'Client', 'Statut', 'Activation', 'Expiration', 'MikroTik'], function ($write) use ($tickets) {
            foreach ($tickets as $voucher) {
                $write([
                    $voucher->username,
                    $voucher->plan->name ?? '',
                    $voucher->customer->phone ?? $voucher->customer->name ?? '',
                    $voucher->statusLabel(),
                    $voucher->activated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    $voucher->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    SyncLabel::for($voucher->sync_status),
                ]);
            }
        });
    }

    public function payments(Request $request, BusinessReport $report): StreamedResponse
    {
        $payments = $report->exportPayments($report->filters($request));

        return $this->csv('paiements.csv', ['Date', 'Référence', 'Montant', 'Méthode', 'Statut'], function ($write) use ($payments) {
            foreach ($payments as $payment) {
                $write([
                    $payment->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                    $payment->internal_reference,
                    Money::format($payment->amount, $payment->currency),
                    config('limete.payment_providers.'.$payment->provider, $payment->provider),
                    PaymentStatus::tryFrom($payment->status)?->label() ?? $payment->status,
                ]);
            }
        });
    }

    private function saleRow(Sale $sale): array
    {
        $item = $sale->items->first();
        $payment = $sale->payment;

        return [
            $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            $item?->voucher?->username,
            $sale->customer->phone ?? $sale->customer->name ?? '',
            $item?->plan?->name,
            Money::format($sale->total_amount, $sale->currency),
            config('limete.payment_providers.'.$payment?->provider, $payment?->provider),
            PaymentStatus::tryFrom((string) $payment?->status)?->label() ?? $sale->status,
            SyncLabel::for($item?->voucher?->sync_status),
        ];
    }

    private function csv(string $filename, array $headers, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, $headers, ';');
            $rows(function (array $row) use ($out) {
                fputcsv($out, array_map(fn ($value) => $this->cell($value), $row), ';');
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
            return "'".$text;
        }

        return $text;
    }
}
