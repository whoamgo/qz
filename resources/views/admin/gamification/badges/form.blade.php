@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body">
                <form action="{{ $badge ? route('admin.xp.badges.update', $badge->id) : route('admin.xp.badges.store') }}" method="POST">
                    @csrf

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Badge Name <span class="text-danger">*</span></p>
                                    <p class="note">e.g., Perfect Score, Quiz Master</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="text" name="name" class="form-control" value="{{ old('name', $badge?->name) }}" required>
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Badge Slug <span class="text-danger">*</span></p>
                                    <p class="note">URL-friendly identifier</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $badge?->slug) }}" required {{ $badge ? 'disabled' : '' }}>
                                    @error('slug') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="field-wrapper">
                        <div class="field-wrapper-label">
                            <p class="title">Description</p>
                            <p class="note">What this badge is for</p>
                        </div>
                        <div class="field-wrapper-view">
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $badge?->description) }}</textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Condition Type <span class="text-danger">*</span></p>
                                    <p class="note">What triggers this badge</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <select name="condition_type" class="form-control" required>
                                        <option value="">Select Condition Type</option>
                                        <option value="quiz_count" {{ old('condition_type', $badge?->condition_type) == 'quiz_count' ? 'selected' : '' }}>Quiz Count</option>
                                        <option value="correct_answer_count" {{ old('condition_type', $badge?->condition_type) == 'correct_answer_count' ? 'selected' : '' }}>Correct Answers</option>
                                        <option value="perfect_score_count" {{ old('condition_type', $badge?->condition_type) == 'perfect_score_count' ? 'selected' : '' }}>Perfect Scores</option>
                                        <option value="streak_days" {{ old('condition_type', $badge?->condition_type) == 'streak_days' ? 'selected' : '' }}>Streak Days</option>
                                        <option value="total_xp" {{ old('condition_type', $badge?->condition_type) == 'total_xp' ? 'selected' : '' }}>Total XP</option>
                                        <option value="category_quiz_count" {{ old('condition_type', $badge?->condition_type) == 'category_quiz_count' ? 'selected' : '' }}>Category Quiz Count</option>
                                        <option value="leaderboard_rank" {{ old('condition_type', $badge?->condition_type) == 'leaderboard_rank' ? 'selected' : '' }}>Leaderboard Rank</option>
                                    </select>
                                    @error('condition_type') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Condition Value (JSON)</p>
                                    <p class="note">e.g., {"required_count": 5}</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <textarea name="condition_data" class="form-control" rows="2" required>{{ old('condition_data', json_encode($badge?->condition_data ?? [])) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Icon/Emoji</p>
                                    <p class="note">Badge icon or emoji</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="text" name="icon" class="form-control" value="{{ old('icon', $badge?->icon) }}" placeholder="e.g., 🏆">
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Color</p>
                                    <p class="note">Hex color code</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="color" name="color" class="form-control" value="{{ old('color', $badge?->color ?? '#FFD700') }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Reward XP <span class="text-danger">*</span></p>
                                    <p class="note">XP for earning badge</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="number" name="reward_xp" class="form-control" value="{{ old('reward_xp', $badge?->reward_xp ?? 0) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="field-wrapper">
                        <div class="field-wrapper-label">
                            <p class="title">Status</p>
                        </div>
                        <div class="field-wrapper-view">
                            <div class="custom-control custom-checkbox">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', $badge?->is_active ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary">{{ $badge ? 'Update Badge' : 'Create Badge' }}</button>
                        <a href="{{ route('admin.xp.badges.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
