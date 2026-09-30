<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\WifiZone;
use App\Support\Money;
use App\Support\QrCodes;
use App\Support\TicketTemplates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class TicketSheet
{
    /**
     * Un modèle par ticket est possible via $templatesById.
     * Sans entrée pour un identifiant, le modèle commun est utilisé.
     *
     * @param  Collection<int, Voucher>  $vouchers
     * @param  array<int, string>  $templatesById
     * @return array{template: string, per_page: int, pages: list<list<array<string, mixed>>>}
     */
    public function pages(Collection $vouchers, string $template, int $perPage, array $templatesById = []): array
    {
        $shared = TicketTemplates::normalize($template);
        $perPage = in_array($perPage, [4, 6, 8], true) ? $perPage : 6;
        $tickets = $vouchers->map(function (Voucher $voucher) use ($shared, $templatesById) {
            $chosen = TicketTemplates::normalize($templatesById[$voucher->id] ?? $shared);

            return $this->payload($voucher, $chosen);
        })->all();

        return [
            'template' => $shared,
            'per_page' => $perPage,
            'pages' => array_chunk($tickets, $perPage),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Voucher>
     */
    public function owned(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            abort(404);
        }

        $found = Voucher::query()->with(['plan', 'wifiZone.tenant'])->whereIn('id', $ids)->get()->keyBy('id');
        $ordered = collect($ids)->map(fn (int $id) => $found->get($id))->filter()->values();
        if ($ordered->isEmpty()) {
            abort(404);
        }

        return $ordered;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Voucher $voucher, string $template): array
    {
        $zone = $voucher->wifiZone;
        $zone?->loadMissing('tenant');
        $plan = $voucher->plan;
        $publicUrl = route('tickets.public', $voucher->public_token);

        return [
            'id' => $voucher->id,
            'template' => $template,
            'business' => $zone?->tenant?->name ?: $zone?->displayLabel() ?: 'WiFi',
            'zone' => $zone?->name ?: '',
            'logo' => $zone ? $this->logo($zone) : null,
            'color' => $zone?->brandColor() ?: '#0b5ed7',
            'plan' => $plan?->name ?: 'Forfait',
            'price' => Money::format($voucher->price_amount ?? $plan?->price, $voucher->currency ?: $plan?->currency),
            'duration' => $plan?->validityLabel() ?: '',
            'username' => $voucher->username,
            'password' => $voucher->password,
            'qr' => QrCodes::svg($publicUrl),
            'public_url' => $publicUrl,
            'created' => $voucher->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'expires' => $voucher->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'phone' => $zone?->contactPhone(),
            'whatsapp' => $zone?->contactWhatsapp(),
            'address' => $zone?->addressLine(),
            'status' => $voucher->statusLabel(),
        ];
    }

    private function logo(WifiZone $zone): ?string
    {
        $zone->loadMissing('tenant');
        $path = filled($zone->logo_path) ? (string) $zone->logo_path : (string) ($zone->tenant?->logo_path ?? '');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $full = Storage::disk('public')->path($path);

        return is_file($full) ? $full : $zone->logoUrl();
    }
}
