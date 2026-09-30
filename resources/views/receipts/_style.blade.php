<style>
    @unless($isPdf ?? false)
    @page { size: {{ $width }}mm auto; margin: 2mm; }
    @endunless
    * { box-sizing: border-box; }
    body { margin: 0; background: #f1f5f9; font-family: 'DejaVu Sans Mono', 'Courier New', monospace; font-size: {{ $width === '58' ? '10px' : '11.5px' }}; color: #000; }
    .receipt { width: {{ ((int) $width) - 4 }}mm; margin: 0 auto; padding: 2mm 0; background: #fff; }
    .c { text-align: center; }
    .b { font-weight: bold; }
    .big { font-size: 1.25em; }
    .small { font-size: 0.85em; }
    hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
    .row { display: table; width: 100%; }
    .row > span { display: table-cell; }
    .row > span:last-child { text-align: right; white-space: nowrap; padding-left: 6px; }
    .item { margin-bottom: 5px; }
    .grand { font-size: 1.2em; font-weight: bold; }
    .toolbar { display: flex; gap: 8px; justify-content: center; padding: 10px; font-family: sans-serif; }
    .toolbar button, .toolbar a { padding: 6px 12px; border: 1px solid #94a3b8; border-radius: 6px; background: #fff; color: #0f172a; font-size: 13px; text-decoration: none; cursor: pointer; }
    .toolbar a.on { background: #0f172a; color: #fff; }
    @media print {
        body { background: #fff; }
        .no-print { display: none !important; }
        .receipt { margin: 0; padding: 0; }
    }
</style>
