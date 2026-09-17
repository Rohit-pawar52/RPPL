{{-- Shared by create.blade.php and edit.blade.php. $transaction is null on create. --}}
@php
    $transaction = $transaction ?? null;
@endphp

<x-form.select
    name="edition_id"
    label="Edition"
    placeholder="Select edition"
    :options="$editions->pluck('name', 'id')"
    :value="$transaction->edition_id ?? ''"
/>

<x-form.select
    name="type"
    label="Type"
    placeholder="Select type"
    :options="['income' => 'Income', 'expense' => 'Expense']"
    :value="$transaction->type ?? ''"
/>

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="category" label="Category" :value="$transaction->category ?? ''" />
    <x-form.input name="amount" label="Amount" type="number" step="0.01" min="0.01" :value="$transaction->amount ?? ''" required />
</div>

<x-form.input
    name="transaction_date"
    label="Date"
    type="date"
    :value="$transaction?->transaction_date?->format('Y-m-d') ?? ''"
    required
/>

<x-form.input name="description" label="Description" :value="$transaction->description ?? ''" />
