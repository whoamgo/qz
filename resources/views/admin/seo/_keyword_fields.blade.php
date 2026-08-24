{{-- Keyword / search-intent / priority block shared by the category & quiz SEO
     editors. Expects $model (a Category or Quiz with the Phase 2 columns). --}}
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">Keywords &amp; Intent</h6></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 form-group">
                <label class="form-label">Primary Keyword</label>
                <input type="text" name="primary_keyword" class="form-control" maxlength="191"
                       value="{{ old('primary_keyword', $model->primary_keyword) }}"
                       placeholder="e.g. reasoning questions">
                <small class="text-muted">The single query this page should rank for.</small>
            </div>
            <div class="col-md-3 form-group">
                <label class="form-label">Search Intent</label>
                <select name="search_intent" class="form-control">
                    <option value="">— none —</option>
                    @foreach (['Informational','Transactional','Navigational','Commercial Investigation','Exam Preparation','Quiz Practice','Current Affairs'] as $intent)
                        <option value="{{ $intent }}" @selected(old('search_intent', $model->search_intent) === $intent)>{{ $intent }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 form-group">
                <label class="form-label">SEO Priority</label>
                <select name="seo_priority" class="form-control">
                    <option value="">— none —</option>
                    @foreach (['P0' => 'P0 — highest', 'P1' => 'P1 — high', 'P2' => 'P2 — medium', 'P3' => 'P3 — low'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('seo_priority', $model->seo_priority) === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group mb-0">
            <label class="form-label">Secondary Keywords <span class="text-muted small">(comma-separated)</span></label>
            <textarea name="secondary_keywords" class="form-control" rows="2" placeholder="reasoning quiz, reasoning practice, reasoning mock test">{{ old('secondary_keywords', $model->secondary_keywords) }}</textarea>
        </div>
    </div>
</div>
