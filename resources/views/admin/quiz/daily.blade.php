@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-lg-11">

        @if (!empty($existing))
            <div class="alert alert--warning"><i class="las la-exclamation-triangle"></i>
                A daily quiz for this date already exists:
                <a href="{{ route('admin.quiz.show', $existing->id) }}" class="fw-bold">{{ $existing->title }}</a>.
                Publishing again is blocked — edit the existing one instead.
            </div>
        @endif

        <form action="{{ route('admin.quiz.daily.store') }}" method="POST">
            @csrf

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0"><i class="las la-bolt"></i> Quick Publish — Daily Current Affairs</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="{{ old('date', $today) }}" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="form-label">Difficulty</label>
                            <select name="difficulty" class="form-control">
                                @foreach (['easy','medium','hard'] as $d)
                                    <option value="{{ $d }}" @selected(old('difficulty','medium')===$d)>{{ ucfirst($d) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="form-label">Time Limit (min)</label>
                            <input type="number" name="time_limit" class="form-control" value="{{ old('time_limit', 10) }}" min="0" max="600" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="form-label">Pass %</label>
                            <input type="number" name="pass_percentage" class="form-control" value="{{ old('pass_percentage', 40) }}" min="0" max="100" required>
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="publish" name="publish" value="1" @checked(old('publish', true))>
                        <label class="form-check-label" for="publish">Publish immediately (uncheck to save as draft)</label>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Paste Questions</h6>
                    <span class="text-muted small">Detected blocks: <strong id="qCount">0</strong></span>
                </div>
                <div class="card-body">
                    <div class="alert alert--info p-2 mb-2"><small>
                        <strong>Format</strong> — one question per block, a blank line between questions.
                        Mark the correct option with <code>*</code> (or add an <code>Answer: B</code> line).
                        <code>Explanation:</code> is optional.
                    </small></div>
                    <textarea name="questions" id="questions" class="form-control" rows="14" style="font-family:monospace;font-size:13px;"
                              placeholder="1. Who was appointed the new RBI Governor in August 2026?&#10;A) Person One&#10;B) Person Two *&#10;C) Person Three&#10;D) Person Four&#10;Explanation: Person Two was appointed on...&#10;&#10;2. Which country hosted the 2026 summit?&#10;A) India *&#10;B) Japan&#10;C) Brazil&#10;D) France">{{ old('questions') }}</textarea>
                    <details class="mt-2"><summary class="text-muted small" style="cursor:pointer;">Show a copy-paste example</summary>
<pre class="mt-2 p-2" style="background:#f6f8fa;border-radius:6px;font-size:12px;">1. Who won the 2026 award for XYZ?
A) Alpha
B) Beta *
C) Gamma
D) Delta
Explanation: Beta won the award for their work on...

2. What is the theme of this year's important day?
A) Theme One
B) Theme Two
C) Theme Three *
D) Theme Four
Answer: C</pre>
                    </details>
                </div>
            </div>

            <div class="mb-4 d-flex gap-2">
                <button type="submit" name="action" value="preview" class="btn btn-outline--primary"><i class="las la-eye"></i> Preview</button>
                <button type="submit" name="action" value="publish" class="btn btn--success"><i class="las la-check-circle"></i> Create &amp; Publish</button>
            </div>
        </form>

        {{-- Parsed preview --}}
        @isset($preview)
            @if (!empty($preview['errors']))
                <div class="card mb-3 border--danger"><div class="card-header"><h6 class="mb-0 text--danger">{{ count($preview['errors']) }} issue(s) — fix and preview again</h6></div>
                    <div class="card-body"><ul class="mb-0">@foreach ($preview['errors'] as $err)<li>{{ $err }}</li>@endforeach</ul></div>
                </div>
            @endif

            <div class="card mb-5">
                <div class="card-header"><h6 class="mb-0">Preview — {{ count($preview['questions']) }} question(s) parsed correctly</h6></div>
                <div class="card-body">
                    @forelse ($preview['questions'] as $i => $q)
                        <div class="mb-3 pb-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="fw-bold mb-1">{{ $i + 1 }}. {{ $q['text'] }}</div>
                            <ul class="list-unstyled mb-1">
                                @foreach ($q['options'] as $letter => $o)
                                    <li class="{{ $letter === $q['correct'] ? 'text--success fw-bold' : '' }}">
                                        {{ $letter }}) {{ $o['text'] }}
                                        @if ($letter === $q['correct'])<i class="las la-check-circle"></i>@endif
                                    </li>
                                @endforeach
                            </ul>
                            @if (!empty($q['explanation']))<div class="text-muted small"><i class="las la-lightbulb"></i> {{ $q['explanation'] }}</div>@endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">No questions parsed. Check the format above.</p>
                    @endforelse
                </div>
            </div>
        @endisset
    </div>
</div>
@endsection

@push('script')
<script>
    (function ($) {
        "use strict";
        function count() {
            var v = ($('#questions').val() || '').trim();
            if (!v) return 0;
            // Blocks separated by a blank line.
            return v.split(/\n\s*\n/).filter(function (b) { return b.trim() !== ''; }).length;
        }
        $('#questions').on('input', function () { $('#qCount').text(count()); }).trigger('input');
    })(jQuery);
</script>
@endpush
