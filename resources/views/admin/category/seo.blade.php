@extends('admin.layouts.app')
@section('panel')
    <div class="row justify-content-center">
        <div class="col-lg-11">

            <form action="{{ route('admin.category.seo.update', $category->id) }}" method="POST">
                @csrf

                {{-- Header: entity + advisory score + link to public page --}}
                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-1">
                                {{ $category->parent_id ? 'Sub-category' : 'Category' }} SEO —
                                <span class="text--primary">{{ $category->name }}</span>
                            </h5>
                            <span class="text-muted">
                                @if ($category->parent)Parent: {{ $category->parent->name }} &middot; @endif
                                {{ $quizCount }} published quiz(zes)
                                @if ($category->seo_updated_at) &middot; SEO updated {{ $category->seo_updated_at->diffForHumans() }} @endif
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-center">
                                <span class="badge badge--{{ $seoScore >= 80 ? 'success' : ($seoScore >= 50 ? 'warning' : 'danger') }}" style="font-size:16px;">
                                    {{ $seoScore }}/100
                                </span>
                                <div class="text-muted small">Advisory score</div>
                            </div>
                            <a href="{{ $publicUrl }}" target="_blank" class="btn btn-sm btn-outline--primary"><i class="las la-external-link-alt"></i> View page</a>
                        </div>
                    </div>
                </div>

                {{-- Google-style preview --}}
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Search Preview</h6></div>
                    <div class="card-body">
                        <div style="max-width:600px;font-family:arial,sans-serif;">
                            <div id="seoPrevTitle" style="color:#1a0dab;font-size:18px;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
                            <div style="color:#006621;font-size:13px;">{{ $publicUrl }}</div>
                            <div id="seoPrevDesc" style="color:#545454;font-size:13px;line-height:1.4;"></div>
                        </div>
                    </div>
                </div>

                {{-- SEO basics --}}
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">SEO</h6></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">SEO Title <span id="mtCount" class="text-muted small"></span></label>
                            <input type="text" id="meta_title" name="meta_title" class="form-control" maxlength="255"
                                   value="{{ old('meta_title', $category->meta_title) }}"
                                   placeholder="{{ $category->parent_id ? $category->name.' Questions & Quiz – '.optional($category->parent)->name.' Practice' : $category->name.' Quiz & Practice Questions' }}">
                            <small class="text-muted">Recommended 50–60 characters. Leave blank to auto-generate. “ | {{ gs('site_name') }}” is appended automatically.</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Meta Description <span id="mdCount" class="text-muted small"></span></label>
                            <textarea id="meta_description" name="meta_description" class="form-control" rows="2" maxlength="320"
                                      placeholder="Auto-generated from the category name and quiz counts if left blank.">{{ old('meta_description', $category->meta_description) }}</textarea>
                            <small class="text-muted">Recommended 140–160 characters.</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">SEO H1</label>
                                <input type="text" name="seo_h1" class="form-control" maxlength="255"
                                       value="{{ old('seo_h1', $category->seo_h1) }}"
                                       placeholder="{{ $category->parent_id ? $category->name.' Questions' : $category->name.' Quizzes' }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">SEO Keywords <span class="text-muted small">(optional)</span></label>
                                <input type="text" name="meta_keywords" class="form-control" maxlength="255"
                                       value="{{ old('meta_keywords', $category->meta_keywords) }}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Canonical URL <span class="text-muted small">(optional — self-canonical by default)</span></label>
                            <input type="url" name="canonical_url" class="form-control" maxlength="512"
                                   value="{{ old('canonical_url', $category->canonical_url) }}" placeholder="{{ $publicUrl }}">
                        </div>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="robots_index" name="robots_index" value="1" @checked(old('robots_index', $category->robots_index ?? true))>
                                <label class="form-check-label" for="robots_index">Index (allow search engines)</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="robots_follow" name="robots_follow" value="1" @checked(old('robots_follow', $category->robots_follow ?? true))>
                                <label class="form-check-label" for="robots_follow">Follow links</label>
                            </div>
                        </div>
                        @if ($quizCount === 0)
                            <div class="alert alert--warning mt-3 mb-0 p-2"><i class="las la-exclamation-triangle"></i>
                                This {{ $category->parent_id ? 'sub-category' : 'category' }} has no published quizzes, so it stays <strong>noindex</strong> until it has quizzes or substantial SEO content — protecting you from thin pages.</div>
                        @endif
                    </div>
                </div>

                {{-- SEO content (HTML editors) --}}
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">SEO Content</h6></div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Short Introduction</label>
                            <textarea name="seo_intro" class="form-control" rows="2" maxlength="1000" placeholder="One or two sentences shown under the H1.">{{ old('seo_intro', $category->seo_intro) }}</textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Main SEO Content (HTML)</label>
                            <textarea name="seo_content" class="nicEdit form-control">{{ old('seo_content', $category->seo_content) }}</textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bottom SEO Content (HTML)</label>
                            <textarea name="seo_bottom_content" class="nicEdit form-control">{{ old('seo_bottom_content', $category->seo_bottom_content) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Social --}}
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Social (Open Graph & Twitter)</h6></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">OG Title</label>
                                <input type="text" name="og_title" class="form-control" maxlength="255" value="{{ old('og_title', $category->og_title) }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">OG Image URL</label>
                                <input type="url" name="og_image" class="form-control" maxlength="512" value="{{ old('og_image', $category->og_image) }}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">OG Description</label>
                            <textarea name="og_description" class="form-control" rows="2" maxlength="320">{{ old('og_description', $category->og_description) }}</textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">Twitter Title</label>
                                <input type="text" name="twitter_title" class="form-control" maxlength="255" value="{{ old('twitter_title', $category->twitter_title) }}">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">Twitter Description</label>
                                <input type="text" name="twitter_description" class="form-control" maxlength="320" value="{{ old('twitter_description', $category->twitter_description) }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Schema --}}
                <div class="card mb-3">
                    <div class="card-header"><h6 class="mb-0">Structured Data (JSON-LD)</h6></div>
                    <div class="card-body">
                        <div class="form-group mb-0">
                            <label class="form-label">Schema JSON <span class="text-muted small">(optional — must be valid JSON)</span></label>
                            <textarea name="schema_json" class="form-control" rows="5" style="font-family:monospace;" placeholder='{"@context":"https://schema.org", ...}'>{{ old('schema_json', $category->schema_json) }}</textarea>
                            <small class="text-muted">Added on top of the automatic FAQ + Breadcrumb schema. Invalid JSON is ignored on the frontend.</small>
                        </div>
                    </div>
                </div>

                <div class="mb-5">
                    <button type="submit" class="btn btn--primary w-100"><i class="las la-save"></i> Save SEO Settings</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('script')
<script>
    (function ($) {
        "use strict";
        var site = @json(gs('site_name'));
        var $t = $('#meta_title'), $d = $('#meta_description');

        function counter($el, $out, min, max) {
            var len = ($el.val() || '').length;
            $out.text('(' + len + ' chars)');
            $out.css('color', (len === 0 || len < min || len > max) ? '#dc3545' : '#198754');
        }
        function preview() {
            var t = ($t.val() || '').trim();
            var title = t ? (t + ' | ' + site) : @json($category->name) + ' | ' + site;
            $('#seoPrevTitle').text(title);
            $('#seoPrevDesc').text(($d.val() || '').trim() || 'Meta description preview will appear here…');
        }
        $t.on('input', function () { counter($t, $('#mtCount'), 30, 60); preview(); });
        $d.on('input', function () { counter($d, $('#mdCount'), 120, 160); preview(); });
        counter($t, $('#mtCount'), 30, 60);
        counter($d, $('#mdCount'), 120, 160);
        preview();
    })(jQuery);
</script>
@endpush
