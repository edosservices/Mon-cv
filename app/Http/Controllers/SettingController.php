<?php

namespace App\Http\Controllers;

use App\Support\TicketTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function edit()
    {
        return view('business.edit', [
            'tenant' => auth()->user()->tenant,
            'templates' => TicketTemplates::options(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slogan' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'button_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'ticket_style' => ['nullable', Rule::in(TicketTemplates::keys())],
            'custom_domain' => ['nullable', 'string', 'max:160'],
            'ikeepay_public_key' => ['nullable', 'string', 'max:255'],
            'ikeepay_secret_key' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048', 'dimensions:min_width=32,min_height=32,max_width=4096,max_height=4096'],
        ], [
            'logo.mimes' => 'Le logo doit être un fichier PNG, JPG, JPEG ou WEBP.',
            'logo.mimetypes' => 'Le logo doit être une image PNG, JPG ou WEBP.',
            'logo.max' => 'Le logo ne doit pas dépasser 2 Mo.',
            'logo.dimensions' => 'Le logo doit mesurer entre 32 et 4096 pixels de côté.',
        ]);

        $tenant = $request->user()->tenant;

        if ($request->hasFile('logo')) {
            $this->deleteStored($tenant->logo_path);
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }
        unset($data['logo']);

        foreach (['slogan', 'phone', 'whatsapp', 'email', 'address', 'city', 'country', 'ticket_style', 'custom_domain', 'ikeepay_public_key'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        if (array_key_exists('ikeepay_secret_key', $data) && ! filled($data['ikeepay_secret_key'])) {
            unset($data['ikeepay_secret_key']);
        }

        $tenant->update($data);

        return back()->with('status', 'Business enregistré.');
    }

    private function deleteStored(?string $path): void
    {
        if (! is_string($path) || ! str_starts_with($path, 'logos/') || str_contains($path, '..')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
