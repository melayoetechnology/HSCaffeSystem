@extends('orders.print.layout')

@section('title', __('Pesanan Dapur'))

@section('content')
<div class="receipt-header">
    <p class="line font-bold">{{ __('PESANAN DAPUR / KITCHEN') }}</p>
    <p class="line text-sm">{{ $order->order_number }}</p>
    <p class="line text-sm">
        {{ $order->table ? __('Meja') . ' ' . $order->table->number : __('Take Away') }}
        · {{ $order->created_at->format('d/m/Y H:i') }}
    </p>
</div>
<table>
    <thead>
        <tr>
            <th>{{ __('Item') }}</th>
            <th class="text-right" style="width: 32px;">{{ __('Qty') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
        <tr>
            <td style="padding-right: 6px;">
                <span class="font-bold">{{ $item->item_name }}</span>@if ($item->variant_name) ({{ $item->variant_name }})@endif
                @foreach ($item->modifiers as $mod)
                    <div class="item-detail">+ {{ $mod->modifier_name }}</div>
                @endforeach
                @if ($item->notes)
                    <div class="item-detail" style="font-style: italic;">{{ $item->notes }}</div>
                @endif
            </td>
            <td class="text-right font-bold">{{ $item->quantity }}x</td>
        </tr>
        @endforeach
    </tbody>
</table>
@if ($order->notes)
<div class="receipt-divider text-sm"><strong>{{ __('Catatan order:') }}</strong> {{ $order->notes }}</div>
@endif
@endsection
