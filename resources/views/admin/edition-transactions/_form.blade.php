{{-- Shared by create.blade.php and edit.blade.php. $transaction is null on create.
     On create, ?type=income|expense and ?edition_id= pre-fill the choices (the
     "Add income" / "Add expense" buttons of the ledger use them). --}}
@php
    $transaction = $transaction ?? null;
    $prefillType = $transaction->type ?? (in_array(request('type'), \App\Models\EditionTransaction::TYPES, true) ? request('type') : '');
    $prefillEdition = $transaction->edition_id ?? request('edition_id', '');
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card :title="__('Amount')">
            <div class="crud-cols">
                <x-form.select
                    name="type"
                    :label="__('Type')"
                    :placeholder="__('Select type')"
                    :options="['income' => __('Income'), 'expense' => __('Expense')]"
                    :value="$prefillType"
                    required
                    :autofocus="$prefillType === ''"
                    :help="__('Income is money coming in; expense is money going out.')"
                />
                <x-form.input name="amount" :label="__('Amount')" type="number" step="0.01" min="0.01" inputmode="decimal" :value="$transaction->amount ?? ''" required :autofocus="$prefillType !== ''" />
            </div>

            <x-form.input name="category" :label="__('Category')" :value="$transaction->category ?? ''" :placeholder="__('e.g. Trophies, Sponsorship, Ground rent')" />
            <x-form.input name="description" :label="__('Description')" :value="$transaction->description ?? ''" />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('Where and when')">
            <x-form.select
                name="edition_id"
                :label="__('Edition')"
                :placeholder="__('Select edition')"
                :options="$editions->pluck('name', 'id')"
                :value="$prefillEdition"
            />
            <x-form.input
                name="transaction_date"
                :label="__('Date')"
                type="date"
                :value="$transaction?->transaction_date?->format('Y-m-d') ?? ''"
                required
            />
        </x-admin.card>
    </div>
</div>
