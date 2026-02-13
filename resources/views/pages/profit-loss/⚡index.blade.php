<?php

use App\Enums\ExpenseCategory;
use App\Enums\PaymentStatus;
use App\Models\Expense;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Laba / Rugi')] class extends Component {
    public string $period = 'month';

    public string $startDate = '';

    public string $endDate = '';

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function updatedPeriod(): void
    {
        $this->startDate = match ($this->period) {
            'today' => now()->format('Y-m-d'),
            'week' => now()->startOfWeek()->format('Y-m-d'),
            'month' => now()->startOfMonth()->format('Y-m-d'),
            default => $this->startDate,
        };
        $this->endDate = now()->format('Y-m-d');
        unset($this->incomeSummary, $this->expenseSummary, $this->dailyTrend);
    }

    #[Computed]
    public function incomeSummary(): array
    {
        $query = Order::query()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59']);

        $subtotal = (float) (clone $query)->sum('subtotal');
        $taxAmount = (float) (clone $query)->sum('tax_amount');
        $serviceCharge = (float) (clone $query)->sum('service_charge');
        $discountAmount = (float) (clone $query)->sum('discount_amount');
        $totalRevenue = (float) (clone $query)->sum('total');
        $orderCount = (clone $query)->count();

        return compact('subtotal', 'taxAmount', 'serviceCharge', 'discountAmount', 'totalRevenue', 'orderCount');
    }

    #[Computed]
    public function expenseSummary(): array
    {
        $query = Expense::query()
            ->whereBetween('expense_date', [$this->startDate, $this->endDate]);

        $totalExpenses = (float) (clone $query)->sum('amount');
        $expenseCount = (clone $query)->count();

        $byCategory = (clone $query)
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $stockExpenses = (float) $byCategory->where('category', ExpenseCategory::StockPurchase->value)->first()?->total ?? 0;
        $operationalExpenses = $totalExpenses - $stockExpenses;

        return compact('totalExpenses', 'expenseCount', 'byCategory', 'stockExpenses', 'operationalExpenses');
    }

    #[Computed]
    public function dailyTrend(): \Illuminate\Support\Collection
    {
        $incomeByDay = Order::query()
            ->selectRaw("DATE(created_at) as date, SUM(total) as income")
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereBetween('created_at', [$this->startDate.' 00:00:00', $this->endDate.' 23:59:59'])
            ->groupByRaw('DATE(created_at)')
            ->pluck('income', 'date');

        $expenseByDay = Expense::query()
            ->selectRaw('expense_date as date, SUM(amount) as expense')
            ->whereBetween('expense_date', [$this->startDate, $this->endDate])
            ->groupBy('expense_date')
            ->pluck('expense', 'date');

        $allDates = $incomeByDay->keys()->merge($expenseByDay->keys())->unique()->sort();

        return $allDates->map(fn ($date) => (object) [
            'date' => $date,
            'income' => (float) ($incomeByDay[$date] ?? 0),
            'expense' => (float) ($expenseByDay[$date] ?? 0),
            'profit' => (float) ($incomeByDay[$date] ?? 0) - (float) ($expenseByDay[$date] ?? 0),
        ]);
    }

    #[Computed]
    public function netProfit(): float
    {
        return $this->incomeSummary['totalRevenue'] - $this->expenseSummary['totalExpenses'];
    }

    #[Computed]
    public function profitMargin(): float
    {
        $revenue = $this->incomeSummary['totalRevenue'];

        return $revenue > 0 ? ($this->netProfit / $revenue) * 100 : 0;
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    {{-- Header --}}
    <div>
        <flux:heading size="xl">{{ __('Laba / Rugi') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Ringkasan pendapatan vs pengeluaran dalam periode tertentu.') }}</flux:text>
    </div>

    {{-- Period Selection --}}
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex gap-2">
            @foreach (['today' => 'Hari Ini', 'week' => 'Minggu Ini', 'month' => 'Bulan Ini', 'custom' => 'Custom'] as $p => $label)
                <flux:badge as="button" wire:click="$set('period', '{{ $p }}')" :variant="$period === $p ? 'primary' : 'default'" size="sm">
                    {{ __($label) }}
                </flux:badge>
            @endforeach
        </div>
        @if ($period === 'custom')
            <div class="flex items-center gap-2">
                <flux:input wire:model.live="startDate" type="date" size="sm" />
                <span class="text-zinc-400">-</span>
                <flux:input wire:model.live="endDate" type="date" size="sm" />
            </div>
        @endif
    </div>

    {{-- Main Summary Cards --}}
    @php
        $income = $this->incomeSummary;
        $expenses = $this->expenseSummary;
        $net = $this->netProfit;
        $margin = $this->profitMargin;
    @endphp
    <div class="grid gap-4 md:grid-cols-3">
        {{-- Pendapatan --}}
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 dark:border-emerald-800 dark:bg-emerald-900/20">
            <flux:text class="text-sm text-emerald-700 dark:text-emerald-400">{{ __('Total Pendapatan') }}</flux:text>
            <div class="mt-2 text-3xl font-bold text-emerald-700 dark:text-emerald-300">Rp {{ number_format($income['totalRevenue'], 0, ',', '.') }}</div>
            <flux:text class="text-xs text-emerald-600 dark:text-emerald-500">{{ $income['orderCount'] }} {{ __('pesanan lunas') }}</flux:text>
        </div>

        {{-- Pengeluaran --}}
        <div class="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-800 dark:bg-red-900/20">
            <flux:text class="text-sm text-red-700 dark:text-red-400">{{ __('Total Pengeluaran') }}</flux:text>
            <div class="mt-2 text-3xl font-bold text-red-700 dark:text-red-300">Rp {{ number_format($expenses['totalExpenses'], 0, ',', '.') }}</div>
            <flux:text class="text-xs text-red-600 dark:text-red-500">{{ $expenses['expenseCount'] }} {{ __('transaksi') }}</flux:text>
        </div>

        {{-- Laba/Rugi Bersih --}}
        <div class="rounded-xl border p-6 {{ $net >= 0 ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20' : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20' }}">
            <flux:text class="text-sm {{ $net >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">{{ __('Laba / Rugi Bersih') }}</flux:text>
            <div class="mt-2 text-3xl font-bold {{ $net >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">
                {{ $net < 0 ? '-' : '' }}Rp {{ number_format(abs($net), 0, ',', '.') }}
            </div>
            <flux:text class="text-xs {{ $net >= 0 ? 'text-emerald-600 dark:text-emerald-500' : 'text-red-600 dark:text-red-500' }}">
                {{ __('Margin:') }} {{ number_format($margin, 1) }}%
            </flux:text>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Income Breakdown --}}
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Rincian Pendapatan') }}</flux:heading>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <flux:text>{{ __('Subtotal Penjualan') }}</flux:text>
                    <span class="font-semibold">Rp {{ number_format($income['subtotal'], 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <flux:text>{{ __('Pajak') }}</flux:text>
                    <span class="font-semibold">Rp {{ number_format($income['taxAmount'], 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <flux:text>{{ __('Service Charge') }}</flux:text>
                    <span class="font-semibold">Rp {{ number_format($income['serviceCharge'], 0, ',', '.') }}</span>
                </div>
                @if ($income['discountAmount'] > 0)
                    <div class="flex justify-between">
                        <flux:text>{{ __('Diskon') }}</flux:text>
                        <span class="font-semibold text-red-600">-Rp {{ number_format($income['discountAmount'], 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between border-t border-zinc-200 pt-3 dark:border-zinc-700">
                    <span class="font-bold">{{ __('Total Pendapatan') }}</span>
                    <span class="font-bold text-emerald-600">Rp {{ number_format($income['totalRevenue'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Expense Breakdown --}}
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">{{ __('Rincian Pengeluaran') }}</flux:heading>
            <div class="space-y-3">
                @forelse ($expenses['byCategory'] as $catData)
                    @php
                        $catEnum = $catData->category instanceof \App\Enums\ExpenseCategory
                            ? $catData->category
                            : \App\Enums\ExpenseCategory::tryFrom($catData->category);
                    @endphp
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            @if ($catEnum)
                                <flux:badge :variant="$catEnum->color()" size="sm">{{ $catEnum->label() }}</flux:badge>
                            @else
                                <flux:badge size="sm">{{ $catData->category }}</flux:badge>
                            @endif
                            <flux:text class="text-xs">({{ $catData->count }}x)</flux:text>
                        </div>
                        <span class="font-semibold">Rp {{ number_format($catData->total, 0, ',', '.') }}</span>
                    </div>
                @empty
                    <flux:text class="text-center text-zinc-400">{{ __('Belum ada pengeluaran.') }}</flux:text>
                @endforelse
                @if ($expenses['byCategory']->isNotEmpty())
                    <div class="flex justify-between border-t border-zinc-200 pt-3 dark:border-zinc-700">
                        <span class="font-bold">{{ __('Total Pengeluaran') }}</span>
                        <span class="font-bold text-red-600">Rp {{ number_format($expenses['totalExpenses'], 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Expense Composition Summary --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Pengeluaran Operasional') }}</flux:text>
            <div class="mt-2 text-2xl font-bold">Rp {{ number_format($expenses['operationalExpenses'], 0, ',', '.') }}</div>
            <flux:text class="text-xs">{{ __('Sewa, utilitas, gaji, dll.') }}</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Pembelian Stok') }}</flux:text>
            <div class="mt-2 text-2xl font-bold">Rp {{ number_format($expenses['stockExpenses'], 0, ',', '.') }}</div>
            <flux:text class="text-xs">{{ __('Otomatis dari stok masuk.') }}</flux:text>
        </div>
    </div>

    {{-- Daily Trend --}}
    @if ($this->dailyTrend->count() > 1)
        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="border-b border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="lg">{{ __('Tren Harian') }}</flux:heading>
            </div>
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Tanggal') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Pendapatan') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Pengeluaran') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ __('Laba/Rugi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($this->dailyTrend as $day)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ \Carbon\Carbon::parse($day->date)->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600">Rp {{ number_format($day->income, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-red-600">Rp {{ number_format($day->expense, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $day->profit >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $day->profit < 0 ? '-' : '' }}Rp {{ number_format(abs($day->profit), 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-zinc-300 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800">
                    <tr>
                        <td class="px-4 py-3 font-bold">{{ __('Total') }}</td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-600">Rp {{ number_format($this->dailyTrend->sum('income'), 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">Rp {{ number_format($this->dailyTrend->sum('expense'), 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-bold {{ $this->dailyTrend->sum('profit') >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            @php $totalProfit = $this->dailyTrend->sum('profit'); @endphp
                            {{ $totalProfit < 0 ? '-' : '' }}Rp {{ number_format(abs($totalProfit), 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
