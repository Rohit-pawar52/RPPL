@extends('layouts.admin')

@section('title', 'Record Contribution')

@section('content')
    <x-crud.back :href="route('admin.edition-contributions.index')">Contributions</x-crud.back>

    <x-crud.form :action="route('admin.edition-contributions.store')" :cancel="route('admin.edition-contributions.index')" submit="Save contribution">
        <div class="crud-grid">
            <div class="crud-main">
                <x-admin.card title="Who gave">
                    <div class="crud-cols">
                        <x-form.select
                            name="edition_id"
                            label="Edition"
                            placeholder="Select edition"
                            :options="$editions->pluck('name', 'id')"
                            :value="old('edition_id', request('edition_id'))"
                            :autofocus="! request('edition_id')"
                        />

                        <x-form.select
                            name="contributor_id"
                            label="Contributor"
                            placeholder="Select a contributor"
                            :options="$contributors->pluck('name', 'id')"
                            :value="old('contributor_id', request('contributor_id'))"
                        />
                    </div>

                    <div id="committee-dues-preview" class="mb-3.5 hidden rounded-lg border border-sky-100 bg-sky-50 p-3 text-[12px] text-sky-900">
                        <span class="crud-pill crud-pill-blue">Committee Member</span>
                        <dl class="mt-2 grid grid-cols-3 gap-2">
                            <div><dt class="text-sky-600">Target</dt><dd class="text-sm font-bold tabular-nums" data-dues-target>&mdash;</dd></div>
                            <div><dt class="text-sky-600">Paid So Far</dt><dd class="text-sm font-bold tabular-nums" data-dues-paid>&mdash;</dd></div>
                            <div><dt class="text-sky-600">Remaining</dt><dd class="text-sm font-bold tabular-nums" data-dues-remaining>&mdash;</dd></div>
                        </dl>
                    </div>
                </x-admin.card>

                <x-admin.card title="The money">
                    <div class="crud-cols">
                        <x-form.input
                            name="amount"
                            label="Amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            inputmode="decimal"
                            required
                            :autofocus="(bool) request('edition_id')"
                            help="Any amount greater than {{ money(0, 0) }} is accepted — a committee member's target above may be reached across several separate contributions."
                        />

                        <x-form.input name="contributed_at" label="Date" type="date" required />
                    </div>

                    <x-form.input name="notes" label="Notes" :value="old('notes')" />
                </x-admin.card>
            </div>

            <div class="crud-aside">
                <p class="crud-note crud-note-brand">Each contribution also adds an <strong class="font-semibold text-slate-700">income</strong> line to the ledger, and a receipt you can print or download.</p>
            </div>
        </div>
    </x-crud.form>

    {{--
        Small, page-local, purpose-built preview — not a shared/generic
        AJAX pattern. Fires only when BOTH selects have a value; shows
        nothing (and hides the panel again) whenever either is cleared or
        the pair isn't a committee membership.
    --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const editionSelect = document.getElementById('edition_id');
            const contributorSelect = document.getElementById('contributor_id');
            const panel = document.getElementById('committee-dues-preview');

            if (! editionSelect || ! contributorSelect || ! panel) return;

            const refresh = async () => {
                const editionId = editionSelect.value;
                const contributorId = contributorSelect.value;

                if (! editionId || ! contributorId) {
                    panel.classList.add('hidden');
                    return;
                }

                try {
                    const response = await fetch(`{{ route('admin.edition-contributions.dues-preview') }}?edition_id=${editionId}&contributor_id=${contributorId}`, {
                        headers: { Accept: 'application/json' },
                    });

                    if (! response.ok) {
                        panel.classList.add('hidden');
                        return;
                    }

                    const data = await response.json();

                    if (! data.is_committee_member) {
                        panel.classList.add('hidden');
                        return;
                    }

                    panel.querySelector('[data-dues-target]').textContent = data.dues.target;
                    panel.querySelector('[data-dues-paid]').textContent = data.dues.paid;
                    panel.querySelector('[data-dues-remaining]').textContent = data.dues.remaining;
                    panel.classList.remove('hidden');
                } catch (error) {
                    panel.classList.add('hidden');
                }
            };

            editionSelect.addEventListener('change', refresh);
            contributorSelect.addEventListener('change', refresh);

            // Both may already be pre-filled (old() after a validation
            // failure, or ?edition_id=&contributor_id= from the
            // Committee tab's "Record contribution" link) — show the
            // preview immediately rather than waiting for a change event.
            refresh();
        });
    </script>
@endsection
