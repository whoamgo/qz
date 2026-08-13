@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.xp.rules.store') }}" method="POST">
                    @csrf

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Rule Name <span class="text-danger">*</span></p>
                                    <p class="note">e.g., Quiz Completed, Correct Medium Answer</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="text" name="name" class="form-control" value="{{ old('name', $rule?->name) }}" required>
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Rule Key <span class="text-danger">*</span></p>
                                    <p class="note">e.g., quiz_completed, correct_medium_answer</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="text" name="key" class="form-control" value="{{ old('key', $rule?->key) }}" required {{ $rule ? 'disabled' : '' }}>
                                    @error('key') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="field-wrapper">
                        <div class="field-wrapper-label">
                            <p class="title">Description</p>
                            <p class="note">Brief description of what this rule awards</p>
                        </div>
                        <div class="field-wrapper-view">
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $rule?->description) }}</textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-3">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">XP Value <span class="text-danger">*</span></p>
                                    <p class="note">Amount of XP to award</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="number" name="xp_value" class="form-control" value="{{ old('xp_value', $rule?->xp_value) }}" required>
                                    @error('xp_value') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Category <span class="text-danger">*</span></p>
                                    <p class="note">Rule category</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <select name="category" class="form-control" required>
                                        <option value="">Select Category</option>
                                        <option value="quiz" {{ old('category', $rule?->category) == 'quiz' ? 'selected' : '' }}>Quiz</option>
                                        <option value="learning" {{ old('category', $rule?->category) == 'learning' ? 'selected' : '' }}>Learning</option>
                                        <option value="streak" {{ old('category', $rule?->category) == 'streak' ? 'selected' : '' }}>Streak</option>
                                        <option value="other" {{ old('category', $rule?->category) == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('category') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Daily Limit</p>
                                    <p class="note">Max times per day (leave blank for unlimited)</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="number" name="daily_limit" class="form-control" value="{{ old('daily_limit', $rule?->daily_limit) }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Cooldown (minutes)</p>
                                    <p class="note">Delay between awards</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="number" name="cooldown_minutes" class="form-control" value="{{ old('cooldown_minutes', $rule?->cooldown_minutes ?? 0) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Sort Order <span class="text-danger">*</span></p>
                                    <p class="note">Display order in list</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $rule?->sort_order ?? 0) }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Status</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <div class="custom-control custom-checkbox">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', $rule?->is_active ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="is_active">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary">{{ $rule ? 'Update Rule' : 'Create Rule' }}</button>
                        <a href="{{ route('admin.xp.rules.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
