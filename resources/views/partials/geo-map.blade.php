@if(is_numeric($lat) && is_numeric($lng))
    @php
        $geoLat = (float) $lat;
        $geoLng = (float) $lng;
        $bbox = implode(',', [$geoLng - 0.012, $geoLat - 0.008, $geoLng + 0.012, $geoLat + 0.008]);
    @endphp
    <div class="ratio ratio-16x9 mt-3 lm-geo-frame">
        <iframe
            title="Carte de la position"
            loading="lazy"
            referrerpolicy="no-referrer"
            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&amp;layer=mapnik&amp;marker={{ $geoLat }}%2C{{ $geoLng }}"
        ></iframe>
    </div>
@endif
