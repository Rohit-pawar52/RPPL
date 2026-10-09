{{-- A "most viewed" table. Expects $title, $noun and $rows (a paginator of
     rows built by AdminAnalyticsService::top()). A row whose subject has been
     deleted is still listed, as "Deleted …", because its views were real. --}}
<x-admin.card :title="$title" flush>
    <table class="adm-table">
        <thead>
            <tr>
                <th class="w-12">#</th>
                <th>{{ ucfirst($noun) }}</th>
                <th class="text-right">Views</th>
                <th class="text-right">Unique visitors</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="tabular-nums text-slate-400">{{ $rows->firstItem() + $loop->index }}</td>
                    <td>
                        @if($row['url'])
                            <a href="{{ $row['url'] }}" class="font-semibold text-slate-900 hover:text-link hover:underline">{{ $row['label'] }}</a>
                        @else
                            <span class="italic text-slate-500">{{ $row['label'] }}</span>
                        @endif
                        @if($row['detail'])
                            <span class="block text-[11px] text-slate-500">{{ $row['detail'] }}</span>
                        @endif
                    </td>
                    <td class="num font-semibold text-slate-900">{{ number_format($row['views']) }}</td>
                    <td class="num text-slate-600">{{ number_format($row['visitors']) }}</td>
                </tr>
            @empty
                <x-admin.empty table :colspan="4">No {{ $noun }} views recorded for this period.</x-admin.empty>
            @endforelse
        </tbody>
    </table>

    @if($rows->hasPages())
        <div class="border-t border-line px-4 py-3">
            {{ $rows->links() }}
        </div>
    @endif
</x-admin.card>
