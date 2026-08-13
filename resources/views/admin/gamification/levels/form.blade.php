@extends('admin.layouts.app')
@section('panel')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-body">
                <form action="{{ $level ? route('admin.xp.levels.update', $level->id) : route('admin.xp.levels.store') }}" method="POST">
                    @csrf

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Level Number <span class="text-danger">*</span></p>
                                    <p class="note">e.g., 1, 2, 3...</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="number" name="level_number" class="form-control" value="{{ old('level_number', $level?->level_number) }}" required {{ $level ? 'disabled' : '' }}>
                                    @error('level_number') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Level Name <span class="text-danger">*</span></p>
                                    <p class="note">e.g., Beginner, Expert, Master</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="text" name="name" class="form-control" value="{{ old('name', $level?->name) }}" required>
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Required XP <span class="text-danger">*</span></p>
                                    <p class="note">XP needed to reach this level</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="number" name="required_xp" class="form-control" value="{{ old('required_xp', $level?->required_xp) }}" required>
                                    @error('required_xp') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Reward XP <span class="text-danger">*</span></p>
                                    <p class="note">Bonus XP for reaching this level</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="number" name="reward_xp" class="form-control" value="{{ old('reward_xp', $level?->reward_xp ?? 0) }}" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="field-wrapper">
                        <div class="field-wrapper-label">
                            <p class="title">Description</p>
                            <p class="note">Optional description of this level</p>
                        </div>
                        <div class="field-wrapper-view">
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $level?->description) }}</textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Badge Icon</p>
                                    <p class="note">Icon or emoji for the badge</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="text" name="badge_icon" class="form-control" value="{{ old('badge_icon', $level?->badge_icon) }}" placeholder="e.g., 🎖️">
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">Badge Color</p>
                                    <p class="note">Hex color code</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="color" name="badge_color" class="form-control" value="{{ old('badge_color', $level?->badge_color ?? '#808080') }}">
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
                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', $level?->is_active ?? true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary">{{ $level ? 'Update Level' : 'Create Level' }}</button>
                        <a href="{{ route('admin.xp.levels.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
