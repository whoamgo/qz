{{-- Shared taxonomy opportunity table. Props: $rows (collection of stdClass with
     name,is_sub,url,quizzes,questions,edit), $emptyMsg. --}}
<div class="table-responsive">
    <table class="table--light style--two table">
        <thead>
            <tr>
                <th>Page</th>
                <th>Type</th>
                <th class="text-end">Quizzes</th>
                <th class="text-end">Questions</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td><a href="{{ $r->url }}" target="_blank" class="fw-bold">{{ $r->name }}</a></td>
                    <td><span class="badge badge--{{ $r->is_sub ? 'primary' : 'dark' }}">{{ $r->is_sub ? 'Sub-category' : 'Category' }}</span></td>
                    <td class="text-end">{{ number_format($r->quizzes) }}</td>
                    <td class="text-end">{{ number_format($r->questions) }}</td>
                    <td><a href="{{ $r->edit }}" class="btn btn-sm btn-outline--primary"><i class="las la-pencil"></i> Edit SEO</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">{{ $emptyMsg }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
