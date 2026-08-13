@props(['trail' => []])
<ol class="breadcrumb">
    @php $last = array_key_last($trail); @endphp
    @foreach ($trail as $label => $url)
        <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" @if($loop->last) aria-current="page" @endif>
            @if ($loop->last)
                {{ $label }}
            @else
                <a href="{{ $url }}">{{ $label }}</a>
            @endif
        </li>
    @endforeach
</ol>
