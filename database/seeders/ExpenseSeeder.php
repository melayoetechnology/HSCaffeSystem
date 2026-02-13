<?php

namespace Database\Seeders;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $owner = User::where('tenant_id', $tenant->id)->first();

            if (! $owner) {
                continue;
            }

            $expenses = [
                ['category' => ExpenseCategory::Rent, 'description' => 'Sewa tempat bulan ini', 'amount' => 5000000],
                ['category' => ExpenseCategory::Utilities, 'description' => 'Tagihan listrik', 'amount' => 1200000],
                ['category' => ExpenseCategory::Utilities, 'description' => 'Tagihan air', 'amount' => 350000],
                ['category' => ExpenseCategory::Salaries, 'description' => 'Gaji karyawan (4 orang)', 'amount' => 8000000],
                ['category' => ExpenseCategory::Supplies, 'description' => 'Tissue, sabun, dll', 'amount' => 150000],
                ['category' => ExpenseCategory::Marketing, 'description' => 'Iklan Instagram', 'amount' => 200000],
                ['category' => ExpenseCategory::Maintenance, 'description' => 'Service mesin kopi', 'amount' => 350000],
                ['category' => ExpenseCategory::Other, 'description' => 'Biaya parkir bulanan', 'amount' => 100000],
            ];

            foreach ($expenses as $expense) {
                Expense::create([
                    'tenant_id' => $tenant->id,
                    'category' => $expense['category']->value,
                    'description' => $expense['description'],
                    'amount' => $expense['amount'],
                    'expense_date' => now()->subDays(rand(0, 28))->format('Y-m-d'),
                    'reference' => 'EXP-'.strtoupper(fake()->bothify('??###')),
                    'notes' => null,
                    'user_id' => $owner->id,
                ]);
            }
        }
    }
}
