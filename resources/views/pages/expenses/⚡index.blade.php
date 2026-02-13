<?php

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pengeluaran')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $filterCategory = '';
    public string $period = 'month';
    public string $startDate = '';
    public string $endDate = '';

    // Form
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $category = '';
    public string $description = '';
    public string $amount = '';
    public string $expenseDate = '';
    public string $reference = '';
    public string $notes = '';

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
        $this->expenseDate = now()->format('Y-m-d');
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
        $this->resetPage();
        unset($this->summary);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCategory(): void
    {
        $this->resetPage();
        unset($this->summary);
    }

    #[Computed]
    public function expenses()
    {
        return Expense::query()
            ->with('user')
            ->whereBetween('expense_date', [$this->startDate, $this->endDate])
            ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('description', 'like', "%{$this->search}%")
                ->orWhere('reference', 'like', "%{$this->search}%")
            ))
            ->latest('expense_date')
            ->latest()
            ->paginate(20);
    }

    #[Computed]
    public function summary(): array
    {
        $query = Expense::query()
            ->whereBetween('expense_date', [$this->startDate, $this->endDate])
            ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory));

        $total = (float) (clone $query)->sum('amount');
        $count = (clone $query)->count();

        $byCategory = (clone $query)
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        return compact('total', 'count', 'byCategory');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->expenseDate = now()->format('Y-m-d');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $expense = Expense::findOrFail($id);

        if ($expense->stock_movement_id) {
            $this->dispatch('toast', type: 'warning', message: __('Pengeluaran dari stok tidak bisa diedit manual.'));

            return;
        }

        $this->editingId = $expense->id;
        $this->category = $expense->category->value;
        $this->description = $expense->description;
        $this->amount = (string) $expense->amount;
        $this->expenseDate = $expense->expense_date->format('Y-m-d');
        $this->reference = $expense->reference ?? '';
        $this->notes = $expense->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'category' => ['required', 'string'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'expenseDate' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = [
            'category' => $this->category,
            'description' => $this->description,
            'amount' => $this->amount,
            'expense_date' => $this->expenseDate,
            'reference' => $this->reference ?: null,
            'notes' => $this->notes ?: null,
            'user_id' => auth()->id(),
        ];

        if ($this->editingId) {
            $expense = Expense::findOrFail($this->editingId);
            $expense->update($data);
        } else {
            Expense::create($data);
        }

        $this->resetForm();
        $this->showForm = false;
        unset($this->expenses, $this->summary);
    }

    public function delete(int $id): void
    {
        $expense = Expense::findOrFail($id);

        if ($expense->stock_movement_id) {
            $this->dispatch('toast', type: 'warning', message: __('Pengeluaran dari stok tidak bisa dihapus manual.'));

            return;
        }

        $expense->delete();
        unset($this->expenses, $this->summary);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->category = '';
        $this->description = '';
        $this->amount = '';
        $this->expenseDate = now()->format('Y-m-d');
        $this->reference = '';
        $this->notes = '';
        $this->resetValidation();
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Pengeluaran') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Catat dan kelola pengeluaran operasional cafe.') }}</flux:text>
        </div>
        <flux:button wire:click="create" variant="primary" icon="plus">{{ __('Tambah Pengeluaran') }}</flux:button>
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

    {{-- Summary Cards --}}
    @php $summary = $this->summary; @endphp
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text class="text-sm">{{ __('Total Pengeluaran') }}</flux:text>
            <div class="mt-2 text-2xl font-bold text-red-600">Rp {{ number_format($summary['total'], 0, ',', '.') }}</div>
            <flux:text class="text-xs">{{ $summary['count'] }} {{ __('transaksi') }}</flux:text>
        </div>
        @foreach ($summary['byCategory'] as $cat => $data)
            @php
                $catEnum = $cat instanceof \App\Enums\ExpenseCategory
                    ? $cat
                    : \App\Enums\ExpenseCategory::tryFrom($cat);
            @endphp
            @if ($catEnum && $loop->index < 3)
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:text class="text-sm">{{ $catEnum->label() }}</flux:text>
                    <div class="mt-2 text-2xl font-bold">Rp {{ number_format($data->total, 0, ',', '.') }}</div>
                    <flux:text class="text-xs">{{ $data->count }} {{ __('transaksi') }}</flux:text>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Cari deskripsi atau referensi...') }}" />
        </div>
        <flux:select wire:model.live="filterCategory" class="min-w-[180px]">
            <option value="">{{ __('Semua Kategori') }}</option>
            @foreach (\App\Enums\ExpenseCategory::cases() as $cat)
                <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
            @endforeach
        </flux:select>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium">{{ __('Tanggal') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Kategori') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Deskripsi') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Referensi') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Jumlah') }}</th>
                    <th class="px-4 py-3 font-medium text-right">{{ __('Aksi') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($this->expenses as $expense)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $expense->expense_date->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <flux:badge :variant="$expense->category->color()" size="sm">{{ $expense->category->label() }}</flux:badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $expense->description }}</div>
                            @if ($expense->notes)
                                <div class="mt-0.5 text-xs text-zinc-500">{{ Str::limit($expense->notes, 50) }}</div>
                            @endif
                            @if ($expense->stock_movement_id)
                                <div class="mt-0.5 text-xs text-emerald-600">{{ __('Otomatis dari stok masuk') }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-zinc-500">{{ $expense->reference ?? '-' }}</td>
                        <td class="px-4 py-3 text-right font-semibold">Rp {{ number_format($expense->amount, 0, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if (! $expense->stock_movement_id)
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button wire:click="edit({{ $expense->id }})" variant="ghost" size="sm" icon="pencil" />
                                    <flux:button wire:click="delete({{ $expense->id }})" wire:confirm="{{ __('Hapus pengeluaran ini?') }}" variant="ghost" size="sm" icon="trash" class="text-red-500 hover:text-red-700" />
                                </div>
                            @else
                                <flux:text class="text-xs text-zinc-400">{{ __('Auto') }}</flux:text>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                            {{ $search || $filterCategory ? __('Tidak ada pengeluaran ditemukan.') : __('Belum ada data pengeluaran.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->expenses->links() }}

    {{-- Create/Edit Modal --}}
    <flux:modal wire:model="showForm" class="w-full max-w-lg">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit Pengeluaran') : __('Tambah Pengeluaran') }}</flux:heading>

            <form wire:submit="save" class="space-y-4">
                <flux:select wire:model="category" label="{{ __('Kategori') }}">
                    <option value="">{{ __('Pilih Kategori') }}</option>
                    @foreach (\App\Enums\ExpenseCategory::cases() as $cat)
                        @if ($cat !== \App\Enums\ExpenseCategory::StockPurchase)
                            <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                        @endif
                    @endforeach
                </flux:select>

                <flux:input wire:model="description" label="{{ __('Deskripsi') }}" placeholder="{{ __('Contoh: Tagihan listrik bulan Februari') }}" required />

                <flux:input wire:model="amount" type="number" label="{{ __('Jumlah (Rp)') }}" placeholder="0" min="1" step="1" required />

                <flux:input wire:model="expenseDate" type="date" label="{{ __('Tanggal') }}" required />

                <flux:input wire:model="reference" label="{{ __('Referensi/No. Invoice') }}" placeholder="{{ __('Opsional') }}" />

                <flux:textarea wire:model="notes" label="{{ __('Catatan') }}" placeholder="{{ __('Opsional') }}" rows="2" />

                <div class="flex justify-end gap-2">
                    <flux:button wire:click="$set('showForm', false)" variant="ghost">{{ __('Batal') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ $editingId ? __('Simpan') : __('Tambah') }}</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
