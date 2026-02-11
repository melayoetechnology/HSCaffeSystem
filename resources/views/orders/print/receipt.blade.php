@extends('orders.print.layout')

@section('title', __('Struk') . ' ' . $order->order_number)

@section('content')
<div class="receipt-header">
    @if ($order->tenant->name ?? null)
        <p class="line font-bold" style="font-size: 14px;">{{ $order->tenant->name }}</p>
    @endif
    <p class="line text-sm">{{ __('STRUK PELANGGAN') }}</p>
    <p class="line text-sm">{{ $order->order_number }}</p>
    <p class="line text-sm">{{ $order->created_at->format('d/m/Y H:i') }}</p>
    <p class="line text-sm">
        {{ $order->table ? __('Meja') . ' ' . $order->table->number : __('Take Away') }}
        @if ($order->customer?->name)
            · {{ $order->customer->name }}
        @endif
    </p>
</div>
<table>
    <thead>
        <tr>
            <th>{{ __('Item') }}</th>
            <th class="text-right" style="width: 28px;">{{ __('Qty') }}</th>
            <th class="text-right">{{ __('Subtotal') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
        <tr>
            <td style="padding-right: 6px;">
                {{ $item->item_name }}@if ($item->variant_name) ({{ $item->variant_name }})@endif
                @foreach ($item->modifiers as $mod)
                    <div class="item-detail">+ {{ $mod->modifier_name }}</div>
                @endforeach
                <div class="item-detail">{{ $item->quantity }} x Rp {{ number_format($item->unit_price, 0, ',', '.') }}</div>
            </td>
            <td class="text-right">{{ $item->quantity }}</td>
            <td class="text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="receipt-divider text-sm">
    <div style="display: flex; justify-content: space-between; margin: 0; line-height: 1.4;"><span>Subtotal</span><span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span></div>
    <div style="display: flex; justify-content: space-between; margin: 0; line-height: 1.4;"><span>Pajak</span><span>Rp {{ number_format($order->tax_amount, 0, ',', '.') }}</span></div>
    @if ($order->service_charge > 0)
    <div style="display: flex; justify-content: space-between; margin: 0; line-height: 1.4;"><span>Service</span><span>Rp {{ number_format($order->service_charge, 0, ',', '.') }}</span></div>
    @endif
    <div style="display: flex; justify-content: space-between; margin-top: 3px; font-weight: 700; font-size: 14px; line-height: 1.4;"><span>Total</span><span>Rp {{ number_format($order->total, 0, ',', '.') }}</span></div>
</div>
<div class="receipt-footer">
    {{ __('Terima kasih telah memesan') }}
</div>
@endsection
