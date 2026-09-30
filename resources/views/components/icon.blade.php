@props(['name', 'size' => 22])
@php
    $safe = preg_replace('/[^a-z0-9-]/', '', (string) $name) ?: '';
    $path = resource_path('icons/'.$safe.'.svg');
    $svg = is_file($path) ? file_get_contents($path) : '';
    if ($svg !== '') {
        $svg = preg_replace('/\s(?:width|height|class)="[^"]*"/', '', $svg);
        $svg = preg_replace(
            '/<svg\b/',
            '<svg class="vh-ico" width="'.(int) $size.'" height="'.(int) $size.'" aria-hidden="true" focusable="false"',
            $svg,
            1
        );
    }
@endphp
{!! $svg !!}
