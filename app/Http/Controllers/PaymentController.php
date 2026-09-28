<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $allowed = array_map(fn (PaymentStatus $status) => $status->value, PaymentStatus::cases());

        $payments = Payment::query()
            ->with(['payable' => function ($query) {
                $query->morphWith([
                    Sale::class => ['customer', 'items.plan', 'items.voucher'],
                ]);
            }])
            ->when(in_array($status, $allowed, true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'statuses' => PaymentStatus::cases(),
            'current' => $status,
        ]);
    }
}
