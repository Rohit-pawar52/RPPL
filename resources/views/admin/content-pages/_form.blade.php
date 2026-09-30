{{-- Shared by every tab in index.blade.php — one form partial for all
     three fixed content pages. $page is always a real, existing record
     (see DemoContentPageSeeder); there is no "create" state. --}}
<div class="mb-3 flex items-center justify-between">
    <p class="text-xs text-slate-500">
        Public URL:
        <a href="{{ $page->publicUrl() }}" target="_blank" rel="noopener" class="text-green-700 hover:underline">
            {{ $page->publicUrl() }}
        </a>
    </p>
</div>

<form method="POST" action="{{ route('admin.content-pages.update', $page) }}" novalidate>
    @csrf
    @method('PUT')

    <x-form.input name="title" label="Title" :value="$page->title" required maxlength="150" />

    <x-form.textarea name="content" label="Content" :value="$page->content" rows="16" maxlength="20000" help="Markdown — headings (## Heading), paragraphs, lists (- item), links ([text](url)), and **bold**/*italic* emphasis. Raw HTML is not supported and will display as plain text." />

    <x-form.select
        name="is_active"
        label="Status"
        :options="['1' => 'Active', '0' => 'Inactive']"
        :value="$page->is_active ? '1' : '0'"
    />
    <p class="-mt-2.5 mb-3.5 text-[11px] text-slate-400">
        Inactive pages are removed from the footer and return a 404 if visited directly.
    </p>

    <x-admin.button>Save changes</x-admin.button>
</form>
