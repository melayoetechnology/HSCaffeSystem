<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Cetak')) - {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, 'Segoe UI', sans-serif;
            font-size: 13px;
            line-height: 1.25;
            color: #111;
            max-width: 80mm;
            margin: 0 auto;
            padding: 6px 8px;
        }
        @media print {
            body {
                max-width: none;
                padding: 4mm 5mm;
                margin: 0;
                font-size: 12px;
            }
            .no-print { display: none !important; }
        }
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; }
        .text-sm { font-size: 11px; }
        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #333;
            padding-bottom: 4px;
            margin-bottom: 4px;
        }
        .receipt-header .line { margin: 0; line-height: 1.35; }
        .receipt-header .line + .line { margin-top: 1px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2px 0; text-align: left; vertical-align: top; }
        th { font-weight: 600; font-size: 10px; text-transform: uppercase; letter-spacing: 0.02em; color: #374151; }
        .text-right { text-align: right; }
        .receipt-divider {
            border-top: 1px dashed #333;
            margin-top: 4px;
            padding-top: 4px;
        }
        .receipt-footer {
            text-align: center;
            font-size: 11px;
            color: #6b7280;
            margin-top: 6px;
            padding-top: 4px;
        }
        .item-detail { font-size: 11px; margin-top: 0; line-height: 1.3; }
    </style>
</head>
<body>
    @yield('content')
    <div class="no-print" style="margin-top: 12px; text-align: center;">
        <button type="button" onclick="window.print()" style="padding: 6px 14px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer;">
            {{ __('Cetak') }}
        </button>
        <span style="margin-left: 8px; font-size: 12px; color: #6b7280;">{{ __('atau tutup jendela ini') }}</span>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (new URLSearchParams(window.location.search).get('auto') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
