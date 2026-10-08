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
        'secondary_color', 'latitude', 'longitude', 'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

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

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function displayLabel(): string
    {
        $label = trim((string) $this->display_name);

        return $label !== '' ? $label : (string) $this->name;
    }

    public function brandColor(): string
    {
        return $this->validHex($this->primary_color)
            ?? $this->tenant?->brandColor()
            ?? '#0b5ed7';
    }

    public function secondaryColor(): string
    {
        return $this->validHex($this->secondary_color)
            ?? $this->tenant?->secondaryColor()
            ?? '#071e3d';
    }

    public function buttonColor(): string
    {
        return $this->tenant?->buttonColor() ?? $this->brandColor();
    }

    public function logoUrl(): ?string
    {
        return $this->mediaUrl($this->logo_path) ?? $this->tenant?->logoUrl();
    }

    public function contactPhone(): ?string
    {
        return filled($this->phone) ? (string) $this->phone : ($this->tenant?->phone ?: null);
    }

    public function contactWhatsapp(): ?string
    {
        return filled($this->whatsapp) ? (string) $this->whatsapp : ($this->tenant?->whatsapp ?: null);
    }

    public function contactEmail(): ?string
    {
        return filled($this->email) ? (string) $this->email : ($this->tenant?->email ?: null);
    }

    public function sloganLine(): ?string
    {
        return filled($this->slogan) ? (string) $this->slogan : ($this->tenant?->slogan ?: null);
    }

    public function addressLine(): ?string
    {
        if (filled($this->location)) {
            return (string) $this->location;
        }

        $parts = array_values(array_filter([
            $this->tenant?->address,
            $this->tenant?->city,
            $this->tenant?->country,
        ]));

        return $parts === [] ? null : implode(', ', $parts);
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
            'slogan' => $this->sloganLine(),
            'primary' => $this->brandColor(),
            'secondary' => $this->secondaryColor(),
            'logo' => $this->absoluteMedia($this->logoUrl()),
            'banner' => $this->absoluteMedia($this->bannerUrl()),
            'whatsapp' => $this->whatsappDigits(),
            'phone' => $this->contactPhone(),
            'email' => $this->contactEmail(),
            'address' => $this->addressLine(),
            'shop' => route('shop.show', $this->slug),
        ];
    }

    /**
     * Portail de cette zone seulement, à partir d'un DNS réellement enregistré.
     * Un DNS d'une autre zone n'est pas utilisé.
     */
    public function captiveLoginUrl(): ?string
    {
        $host = $this->hotspotLoginHost();

        return $host ? 'https://'.$host.'/login' : null;
    }

    /**
     * Lien MikroTik qui connecte ce ticket, au format du portail captif.
     * Le mot de passe ne doit être placé que dans le QR, jamais dans le texte imprimé.
     */
    public function hotspotAccessUrl(string $username, string $password): ?string
    {
        $host = $this->hotspotLoginHost();
        $username = trim($username);
        $password = trim($password);
        if ($host === null || $username === '' || $password === '') {
            return null;
        }

        return 'http://'.$host.'/login?'.http_build_query([
            'username' => $username,
            'password' => $password,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function hotspotLoginHost(): ?string
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
                    return $host;
                }
            }
        }

        return null;
    }

    public function whatsappDigits(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->contactWhatsapp());

        return $digits !== '' ? $digits : null;
    }

    private function validHex(mixed $value): ?string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value) ? (string) $value : null;
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
