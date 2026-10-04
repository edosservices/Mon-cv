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
    public const ECONOMICAL = 15;

    /**
     * @return array<int, string>
     */
    public static function layoutOptions(): array
    {
        return [
            self::ECONOMICAL => '15 tickets / page',
            4 => '4 tickets / page',
            6 => '6 tickets / page',
            8 => '8 tickets / page',
        ];
    }

    public static function normalizePerPage(int $perPage): int
    {
        return array_key_exists($perPage, self::layoutOptions()) ? $perPage : self::ECONOMICAL;
    }

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
        $perPage = self::normalizePerPage($perPage);
        $tickets = $vouchers->values()->map(function (Voucher $voucher, int $index) use ($shared, $templatesById) {
            $chosen = TicketTemplates::normalize($templatesById[$voucher->id] ?? $shared);
            $payload = $this->payload($voucher, $chosen);
            $payload['number'] = $index + 1;

            return $payload;
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
        $accessUrl = $zone?->hotspotAccessUrl((string) $voucher->username, (string) $voucher->password);
        $host = $zone?->hotspotLoginHost();

        return [
            'id' => $voucher->id,
            'number' => 0,
            'template' => $template,
            'business' => $zone?->tenant?->name ?: $zone?->displayLabel() ?: 'WiFi',
            'zone' => $zone?->name ?: '',
            'logo' => $zone ? $this->logo($zone) : null,
            'color' => $zone?->brandColor() ?: '#0b5ed7',
            'plan' => $plan?->name ?: 'Forfait',
            'price' => Money::format($voucher->price_amount ?? $plan?->price, $voucher->currency ?: $plan?->currency),
            'duration' => $plan?->validityLabel() ?: '',
            'offer' => $this->compactOffer($voucher),
            'username' => $voucher->username,
            'password' => $voucher->password,
            'qr' => QrCodes::svg($publicUrl),
            'access' => $accessUrl ? 'hotspot' : 'ticket',
            'access_qr' => QrCodes::svg($accessUrl ?: $publicUrl),
            'login' => $host ? 'http://'.$host : null,
            'public_url' => $publicUrl,
            'created' => $voucher->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'expires' => $voucher->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'phone' => $zone?->contactPhone(),
            'whatsapp' => $zone?->contactWhatsapp(),
            'address' => $zone?->addressLine(),
            'status' => $voucher->statusLabel(),
        ];
    }

    private function compactOffer(Voucher $voucher): string
    {
        $plan = $voucher->plan;
        $seconds = (int) ($plan?->duration_seconds ?? 0);
        if ($seconds > 0 && $seconds % 86400 === 0) {
            $span = ((int) ($seconds / 86400)).'d';
        } elseif ($seconds > 0 && $seconds % 3600 === 0) {
            $span = ((int) ($seconds / 3600)).'h';
        } elseif ($seconds > 0 && $seconds % 60 === 0) {
            $span = ((int) ($seconds / 60)).'m';
        } else {
            $span = $plan?->validityLabel() ?: '';
        }

        $amount = $voucher->price_amount ?? $plan?->price;
        if ($amount === null || $amount === '') {
            return trim($span);
        }

        $currency = $voucher->currency ?: $plan?->currency ?: 'CDF';
        $code = $currency === 'CDF' ? 'FC' : $currency;

        return trim($span.' '.$code.' '.number_format((float) $amount, 2, '.', ','));
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
        if (! is_file($full)) {
            return null;
        }

        $mime = mime_content_type($full) ?: '';
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
            return null;
        }

        $bytes = file_get_contents($full);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
