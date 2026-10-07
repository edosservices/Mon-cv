<?php

namespace App\Http\Controllers;

use App\Services\Payments\InvalidPaymentSignature;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentSettlement;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentManager $payments, PaymentSettlement $settlement)
    {
        if ($provider === 'manual') {
            abort(404);
        }

        try {
            $gateway = $payments->gateway($provider);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        try {
            $signature = $provider === 'unipay'
                ? ($request->header('X-UniPay-Signature') ?: $request->header('X-Payment-Signature'))
                : $request->header('X-Payment-Signature');
            $notice = $gateway->verifyPayment($request->getContent(), $signature);
        } catch (InvalidPaymentSignature) {
            return response()->json(['status' => 'rejected'], 401);
        } catch (InvalidArgumentException) {
            return response()->json(['status' => 'rejected'], 422);
        }

        $result = $settlement->apply($provider, $notice);

        return response()->json(['status' => $result === 'duplicate' ? 'accepted' : $result], match ($result) {
            'missing' => 404,
            'rejected' => 422,
            default => 200,
        });
    }
}
