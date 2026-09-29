<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class WifiZone extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'display_name', 'slug', 'location', 'description', 'slogan',
        'phone', 'whatsapp', 'email', 'logo_path', 'banner_path', 'primary_color',
        'secondary_color', 'status',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mikrotiks(): HasMany
    {
        return $this->hasMany(Mikrotik::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function displayLabel(): string
    {
        $label = trim((string) $this->display_name);

        return $label !== '' ? $label : (string) $this->name;
    }

    public function brandColor(): string
    {
        return $this->hexColor($this->primary_color, '#0b5ed7');
    }

    public function secondaryColor(): string
    {
        return $this->hexColor($this->secondary_color, '#071e3d');
    }

    public function logoUrl(): ?string
    {
        return $this->mediaUrl($this->logo_path);
    }

    public function bannerUrl(): ?string
    {
        return $this->mediaUrl($this->banner_path);
    }

    /**
     * Données publiques du portail. Aucun secret d'administration.
     *
     * @return array<string, mixed>
     */
    public function publicBrand(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->displayLabel(),
            'zone' => $this->name,
            'slogan' => $this->slogan,
            'primary' => $this->brandColor(),
            'secondary' => $this->secondaryColor(),
            'logo' => $this->absoluteMedia($this->logoUrl()),
            'banner' => $this->absoluteMedia($this->bannerUrl()),
            'whatsapp' => $this->whatsappDigits(),
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->location,
            'shop' => route('shop.show', $this->slug),
        ];
    }

    /**
     * Portail de cette zone seulement, à partir d'un DNS réellement enregistré.
     * Un DNS d'une autre zone n'est pas utilisé.
     */
    public function captiveLoginUrl(): ?string
    {
        $this->loadMissing('mikrotiks');
        $checker = app(\App\Services\Mikrotik\MikrotikService::class);
        $routers = $this->mikrotiks
            ->filter(fn ($router) => $router->is_active
                && (int) $router->wifi_zone_id === (int) $this->id
                && (int) $router->tenant_id === (int) $this->tenant_id)
            ->sortBy(fn ($router) => $router->status === 'online' ? 0 : 1)
            ->values();

        foreach ($routers as $router) {
            foreach ([$router->dns, $router->detail('dns_name')] as $host) {
                $host = strtolower(trim((string) $host));
                if ($checker->isSafeDns($host)) {
                    return 'https://'.$host.'/login';
                }
            }
        }

        return null;
    }

    public function whatsappDigits(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->whatsapp);

        return $digits !== '' ? $digits : null;
    }

    private function hexColor(mixed $value, string $fallback): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value) ? (string) $value : $fallback;
    }

    private function mediaUrl(mixed $path): ?string
    {
        if (! filled($path) || str_contains((string) $path, '..')) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::url($path);
    }

    private function absoluteMedia(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }
}
