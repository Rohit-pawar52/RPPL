{{-- A "most viewed" table. Expects $title, $noun and $rows (a paginator of
     rows built by AdminAnalyticsService::top()). A row whose subject has been
     deleted is still listed, as "Deleted …", because its views were real. --}}
<x-admin.card :title="$title" flush>
    <table class="w-full text-left text-[13px]">
        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
            <tr>
                <th class="w-12 px-4 py-2 font-medium">#</th>
                <th class="px-4 py-2 font-medium">{{ ucfirst($noun) }}</th>
                <th class="px-4 py-2 text-right font-medium">Views</th>
                <th class="px-4 py-2 text-right font-medium">Unique visitors</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($rows as $row)
                <tr>
                    <td class="px-4 py-2 text-slate-400">{{ $rows->firstItem() + $loop->index }}</td>
                    <td class="px-4 py-2">
                        @if($row['url'])
                            <a href="{{ $row['url'] }}" class="font-medium text-slate-800 hover:underline">{{ $row['label'] }}</a>
                        @else
                            <span class="italic text-slate-500">{{ $row['label'] }}</span>
                        @endif
                        @if($row['detail'])
                            <span class="block text-[11px] text-slate-400">{{ $row['detail'] }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right font-medium text-slate-800">{{ number_format($row['views']) }}</td>
                    <td class="px-4 py-2 text-right text-slate-600">{{ number_format($row['visitors']) }}</td>
                </tr>
            @empty
                <x-admin.empty table :colspan="4">No {{ $noun }} views recorded for this period.</x-admin.empty>
            @endforelse
        </tbody>
    </table>

    @if($rows->hasPages())
        <div class="border-t border-slate-100 px-4 py-3">
            {{ $rows->links() }}
        </div>
    @endif
</x-admin.card>
