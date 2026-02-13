<?php

namespace App\Observers;

use App\Enums\ExpenseCategory;
use App\Enums\StockMovementType;
use App\Models\Expense;
use App\Models\StockMovement;

class StockMovementObserver
{
    public function created(StockMovement $stockMovement): void
    {
        $type = $stockMovement->type instanceof StockMovementType
            ? $stockMovement->type
            : StockMovementType::from($stockMovement->type);

        if ($type !== StockMovementType::In || ! $stockMovement->cost_per_unit) {
            return;
        }

        $totalCost = $stockMovement->quantity * $stockMovement->cost_per_unit;

        $ingredientName = $stockMovement->ingredient?->name ?? 'Bahan baku';

        Expense::create([
            'tenant_id' => $stockMovement->tenant_id,
            'category' => ExpenseCategory::StockPurchase->value,
            'description' => "Pembelian stok: {$ingredientName} ({$stockMovement->quantity} unit)",
            'amount' => $totalCost,
            'expense_date' => $stockMovement->created_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'reference' => $stockMovement->reference,
            'notes' => $stockMovement->notes,
            'user_id' => $stockMovement->user_id ?? auth()->id(),
            'stock_movement_id' => $stockMovement->id,
        ]);
    }
}
