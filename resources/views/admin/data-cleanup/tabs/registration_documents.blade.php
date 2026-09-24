{{--
    Registration Documents tab — purges private Aadhaar/payment-proof
    FILES for a selected, no-longer-open-for-registration edition. The
    registration record itself (player, payment status, registration
    number, fee, financial history) is never touched — only the file
    and its own path column are cleared.
--}}
<div>
    <p class="mb-4 text-xs text-neutral-500">
        Only editions no longer open for public registration are selectable — deleting sensitive identity documents while an edition is still actively collecting them is not allowed.
    </p>

    <form
        method="POST"
        action="{{ route('admin.data-cleanup.registration-documents.destroy') }}"
        id="registration-documents-form"
        data-confirm-action
        data-confirm-title="Delete registration documents?"
        data-confirm-text="The registration records will remain — only the private files will be permanently deleted. This cannot be undone."
        data-confirm-button-text="Yes, delete"
    >
        @csrf
        @method('DELETE')

        <x-form.select
            name="edition_id"
            label="Edition"
            placeholder="Select an edition"
            :options="$editions->reject(fn ($edition) => $edition->registration_open)->pluck('name', 'id')"
        />

        <x-form.select
            name="document_type"
            label="Document type"
            :options="['aadhaar' => 'Aadhaar', 'payment_proof' => 'Payment Proof', 'both' => 'Both']"
            value="both"
        />

        <div id="registration-documents-preview" class="mb-3.5 hidden rounded-md border border-amber-100 bg-amber-50 p-3 text-[12px] text-amber-900">
            <dl class="grid grid-cols-2 gap-2">
                <div><dt class="text-amber-600">Aadhaar documents</dt><dd class="font-medium" data-preview-aadhaar>&mdash;</dd></div>
                <div><dt class="text-amber-600">Payment proofs</dt><dd class="font-medium" data-preview-payment-proof>&mdash;</dd></div>
            </dl>
        </div>

        <button
            type="submit"
            class="w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[13px] font-medium text-red-700 hover:bg-red-100"
        >
            Delete documents
        </button>
    </form>
</div>

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
                panel.classList.remove('hidden');
            } catch (error) {
                panel.classList.add('hidden');
            }
        };

        editionSelect.addEventListener('change', refresh);
        typeSelect.addEventListener('change', refresh);
    });
</script>
