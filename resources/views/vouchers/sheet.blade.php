<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Tickets</title>
    <style>
        @page { size: A4 portrait; margin: 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #10233f; background: #eef2f6; font-family: DejaVu Sans, sans-serif; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px 16px; background: #fff; }
        .toolbar button, .toolbar a { font: inherit; font-size: 14px; padding: 8px 12px; border-radius: 8px; border: 1px solid #c5d0de; background: #fff; color: #10233f; text-decoration: none; }
        .toolbar .primary { background: #071e3d; color: #fff; border-color: #071e3d; }
        .page { width: 194mm; margin: 8px auto; background: #fff; }
        table.grid { width: 100%; border-collapse: separate; border-spacing: 2mm; }
        td.slot { width: 50%; vertical-align: top; }
        .ticket { border: 1px solid #d5e0ee; border-radius: 8px; overflow: hidden; background: #fff; page-break-inside: avoid; break-inside: avoid; }
        .head { padding: 6px 8px; }
        .head img { height: 22px; width: auto; }
        .business { margin: 0; font-size: 12px; font-weight: 700; }
        .zone { margin: 0; font-size: 10px; }
        .fields { padding: 6px 8px 8px; }
        .plan, .price, .duration, .meta, .codes p { margin: 0; }
        .plan { font-size: 11px; font-weight: 700; }
        .price { font-size: 13px; font-weight: 700; }
        .duration, .meta { font-size: 9px; color: #3d516b; }
        .codes { margin-top: 4px; }
        .codes span { display: block; font-size: 8px; letter-spacing: .04em; text-transform: uppercase; color: #5c6e86; }
        .code { font-size: 14px; letter-spacing: .04em; word-break: break-all; }
        .qr { width: 72px; margin-top: 4px; }
        .qr svg { width: 100%; height: auto; display: block; }
        .model-classique .head { color: #fff; text-align: center; }
        .model-moderne .head { display: table; width: 100%; }
        .model-moderne .head img, .model-moderne .head div { display: table-cell; vertical-align: middle; }
        .model-moderne .head div { padding-left: 6px; }
        .model-compact { font-size: 9px; }
        .model-compact .head { padding: 3px 6px; background: #f4f7fb; }
        .model-compact .business, .model-compact .zone { display: inline; }
        .model-compact .fields { padding: 4px 6px; }
        .model-compact .code { font-size: 12px; }
        .model-premium .head { background: #071e3d; color: #fff; border-bottom: 3px solid #c4a35a; text-align: center; }
        .layout-4 .code { font-size: 16px; }
        .layout-4 .qr { width: 88px; }
        .layout-6 .qr { width: 68px; }
        .layout-8 .business { font-size: 10px; }
        .layout-8 .code { font-size: 11px; }
        .layout-8 .qr { width: 52px; }
        .layout-8 .fields { padding: 4px 6px; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .page { width: auto; margin: 0; page-break-after: always; break-after: page; }
            .page:last-child { page-break-after: auto; break-after: auto; }
            .ticket, td.slot, tr { page-break-inside: avoid; break-inside: avoid; }
        }
    </style>
</head>
<body class="layout-{{ $perPage }}">
    @unless($pdf)
        <div class="toolbar no-print">
            <button type="button" class="primary" onclick="window.print()">Imprimer</button>
            <form method="POST" action="{{ route('vouchers.sheet-pdf') }}">
                @csrf
                <input type="hidden" name="template" value="{{ $template }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                @foreach($ids as $id)
                    <input type="hidden" name="ids[]" value="{{ $id }}">
                @endforeach
                <button type="submit">Télécharger PDF</button>
            </form>
            <a href="{{ route('vouchers.generated') }}">Retour aux tickets</a>
        </div>
    @endunless
    @foreach($pages as $page)
        <section class="page">
            <table class="grid">
                @foreach(array_chunk($page, 2) as $row)
                    <tr>
                        @foreach($row as $ticket)
                            <td class="slot">@include('vouchers.templates.'.$ticket['template'], ['ticket' => $ticket])</td>
                        @endforeach
                        @if(count($row) === 1)
                            <td class="slot"></td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </section>
    @endforeach
</body>
</html>
