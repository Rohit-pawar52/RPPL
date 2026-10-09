{{--
    Registration Documents tab — purges private Aadhaar/payment-proof
    FILES for a selected, no-longer-open-for-registration edition. The
    registration record itself (player, payment status, registration
    number, fee, financial history) is never touched — only the file
    and its own path column are cleared.
--}}
<section class="crud-danger">
    <div class="crud-danger-head">
        <span class="crud-danger-icon"><x-icon name="shield" class="h-4 w-4" /></span>
        <div class="min-w-0">
            <h3 class="crud-card-title">{{ __('Registration documents') }}</h3>
            <p class="crud-hint">
                {{ __('Only editions no longer open for public registration are selectable — deleting sensitive identity documents while an edition is still actively collecting them is not allowed.') }}
            </p>
        </div>
    </div>

    <div class="crud-card-body">
        <form
            method="POST"
            action="{{ route('admin.data-cleanup.registration-documents.destroy') }}"
            id="registration-documents-form"
            class="max-w-xl"
            data-confirm-action
            data-confirm-title="{{ __('Delete registration documents?') }}"
            data-confirm-text="{{ __('The registration records will remain — only the private files will be permanently deleted. This cannot be undone.') }}"
            data-confirm-button-text="{{ __('Yes, delete') }}"
        >
            @csrf
            @method('DELETE')

            <div class="crud-cols">
                <x-form.select
                    name="edition_id"
                    :label="__('Edition')"
                    :placeholder="__('Select an edition')"
                    :options="$editions->reject(fn ($edition) => $edition->registration_open)->pluck('name', 'id')"
                />

                <x-form.select
                    name="document_type"
                    :label="__('Document type')"
                    :options="['aadhaar' => __('Aadhaar'), 'payment_proof' => __('Payment Proof'), 'both' => __('Aadhaar + Payment Proof'), 'photo' => __('Submitted photo')]"
                    value="both"
                />
            </div>

            <div id="registration-documents-preview" class="mb-3.5 hidden rounded-lg border border-amber-200 bg-amber-50 p-3 text-[12px] text-amber-900">
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-amber-700">{{ __('Files that exist now') }}</p>
                <dl class="grid grid-cols-3 gap-2">
                    <div><dt class="text-amber-700">{{ __('Aadhaar documents') }}</dt><dd class="text-lg font-bold tabular-nums" data-preview-aadhaar>&mdash;</dd></div>
                    <div><dt class="text-amber-700">{{ __('Payment proofs') }}</dt><dd class="text-lg font-bold tabular-nums" data-preview-payment-proof>&mdash;</dd></div>
                    <div><dt class="text-amber-700">{{ __('Submitted photos') }}</dt><dd class="text-lg font-bold tabular-nums" data-preview-photo>&mdash;</dd></div>
                </dl>
            </div>

            <button type="submit" class="btn btn-danger btn-block sm:w-auto">
                <x-icon name="trash" class="h-4 w-4" /> {{ __('Delete documents') }}
            </button>
        </form>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('registration-documents-form');
        if (! form) return;

        const editionSelect = form.querySelector('[name="edition_id"]');
        const typeSelect = form.querySelector('[name="document_type"]');
        const panel = document.getElementById('registration-documents-preview');

        const refresh = async () => {
            if (! editionSelect.value || ! typeSelect.value) {
                panel.classList.add('hidden');
                return;
            }

            try {
                const response = await fetch(`{{ route('admin.data-cleanup.preview.registration-documents') }}?edition_id=${editionSelect.value}&document_type=${typeSelect.value}`, {
                    headers: { Accept: 'application/json' },
                });

                if (! response.ok) {
                    panel.classList.add('hidden');
                    return;
                }

                const data = await response.json();
                panel.querySelector('[data-preview-aadhaar]').textContent = data.aadhaar;
                panel.querySelector('[data-preview-payment-proof]').textContent = data.payment_proof;
                panel.querySelector('[data-preview-photo]').textContent = data.photo;
                panel.classList.remove('hidden');
            } catch (error) {
                panel.classList.add('hidden');
            }
        };

        editionSelect.addEventListener('change', refresh);
        typeSelect.addEventListener('change', refresh);
    });
</script>
