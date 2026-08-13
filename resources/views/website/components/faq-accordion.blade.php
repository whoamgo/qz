@props(['faqs' => [], 'id' => 'wFaq', 'title' => 'Frequently Asked Questions'])
@if (count($faqs))
    <div class="w-faq">
        @if ($title)<h2 class="mb-4">{{ $title }}</h2>@endif
        <div class="accordion" id="{{ $id }}">
            @foreach ($faqs as $i => $faq)
                <div class="accordion-item">
                    <h3 class="accordion-header" id="{{ $id }}h{{ $i }}">
                        <button class="accordion-button {{ $i === 0 ? '' : 'collapsed' }}" type="button"
                                data-bs-toggle="collapse" data-bs-target="#{{ $id }}c{{ $i }}"
                                aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="{{ $id }}c{{ $i }}">
                            {{ $faq['question'] }}
                        </button>
                    </h3>
                    <div id="{{ $id }}c{{ $i }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}"
                         aria-labelledby="{{ $id }}h{{ $i }}" data-bs-parent="#{{ $id }}">
                        <div class="accordion-body w-muted">{{ $faq['answer'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
