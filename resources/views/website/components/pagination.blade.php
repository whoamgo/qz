@props(['paginator'])
@if ($paginator->hasPages())
    <nav aria-label="Pagination" class="mt-5">
        <ul class="pagination justify-content-center flex-wrap gap-1">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                <a class="page-link" href="{{ $paginator->previousPageUrl() ?: '#' }}"
                   rel="prev" aria-label="Previous page">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </a>
            </li>

            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                <li class="page-item {{ $page == $paginator->currentPage() ? 'active' : '' }}">
                    <a class="page-link" href="{{ $url }}"
                       @if($page == $paginator->currentPage()) aria-current="page" @endif>{{ $page }}</a>
                </li>
            @endforeach

            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                <a class="page-link" href="{{ $paginator->nextPageUrl() ?: '#' }}"
                   rel="next" aria-label="Next page">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
            </li>
        </ul>
        <p class="text-center w-text-sm w-muted mt-2">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ number_format($paginator->total()) }}
        </p>
    </nav>
@endif
