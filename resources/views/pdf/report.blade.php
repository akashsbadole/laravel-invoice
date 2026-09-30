<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1e293b; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .muted { color: #64748b; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; border-bottom: 1.5px solid #334155; text-align: left; padding: 5px 4px; font-size: 8.5px; text-transform: uppercase; }
        td { padding: 4px; border-bottom: 1px solid #e2e8f0; }
        .num { text-align: right; }
        tr.total td { font-weight: bold; border-top: 1.5px solid #334155; border-bottom: none; background: #f8fafc; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <div class="muted">
        @if(!empty($filters['from']) || !empty($filters['to']))
            Period: {{ $filters['from'] ?? '…' }} to {{ $filters['to'] ?? '…' }} &nbsp;·&nbsp;
        @endif
        Generated {{ now()->format('d M Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                @foreach($report['columns'] as $col)
                    <th class="{{ in_array($col['type'], ['money','number']) ? 'num' : '' }}">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $row)
                <tr>
                    @foreach($report['columns'] as $col)
                        <td class="{{ in_array($col['type'], ['money','number']) ? 'num' : '' }}">
                            @if($col['type'] === 'money') {{ number_format((float) ($row[$col['key']] ?? 0), 2) }}
                            @else {{ $row[$col['key']] ?? '' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}">No data for the selected filters.</td></tr>
            @endforelse
            @if($report['totals'])
                <tr class="total">
                    @foreach($report['columns'] as $col)
                        <td class="{{ in_array($col['type'], ['money','number']) ? 'num' : '' }}">
                            @if($col['type'] === 'money' && $report['totals'][$col['key']] !== '') {{ number_format((float) $report['totals'][$col['key']], 2) }}
                            @else {{ $report['totals'][$col['key']] ?? '' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
