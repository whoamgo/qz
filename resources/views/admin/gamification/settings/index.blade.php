@extends('admin.layouts.app')
@section('panel')
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h5>Gamification Settings</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.xp.settings.update') }}" method="POST">
                    @csrf

                    <div class="form-group">
                        <label><strong>XP System</strong></label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="xp_system_enabled" name="xp_system_enabled" value="1" {{ $settings->xp_system_enabled ? 'checked' : '' }}>
                            <label class="custom-control-label" for="xp_system_enabled">Enable XP System</label>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Daily XP Cap</label>
                                <input type="number" class="form-control" name="daily_xp_cap" value="{{ $settings->daily_xp_cap }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Weekly XP Cap</label>
                                <input type="number" class="form-control" name="weekly_xp_cap" value="{{ $settings->weekly_xp_cap }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Max XP per Quiz</label>
                                <input type="number" class="form-control" name="max_xp_per_quiz" value="{{ $settings->max_xp_per_quiz }}">
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label><strong>Attempt Multipliers</strong></label>
                        <div class="row">
                            <div class="col-md-4">
                                <label>First Attempt (%)</label>
                                <input type="number" class="form-control" name="first_attempt_percentage" value="{{ $settings->first_attempt_percentage }}">
                            </div>
                            <div class="col-md-4">
                                <label>Second Attempt (%)</label>
                                <input type="number" class="form-control" name="second_attempt_percentage" value="{{ $settings->second_attempt_percentage }}">
                            </div>
                            <div class="col-md-4">
                                <label>Third+ Attempt (%)</label>
                                <input type="number" class="form-control" name="third_plus_attempt_percentage" value="{{ $settings->third_plus_attempt_percentage }}">
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label><strong>Features</strong></label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="levels_enabled" name="levels_enabled" value="1" {{ $settings->levels_enabled ? 'checked' : '' }}>
                            <label class="custom-control-label" for="levels_enabled">Enable Levels</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="badges_enabled" name="badges_enabled" value="1" {{ $settings->badges_enabled ? 'checked' : '' }}>
                            <label class="custom-control-label" for="badges_enabled">Enable Badges</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="streaks_enabled" name="streaks_enabled" value="1" {{ $settings->streaks_enabled ? 'checked' : '' }}>
                            <label class="custom-control-label" for="streaks_enabled">Enable Streaks</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="leaderboard_enabled" name="leaderboard_enabled" value="1" {{ $settings->leaderboard_enabled ? 'checked' : '' }}>
                            <label class="custom-control-label" for="leaderboard_enabled">Enable Leaderboard</label>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label><strong>Notifications</strong></label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_xp_earned" name="notify_xp_earned" value="1" {{ $settings->notify_xp_earned ? 'checked' : '' }}>
                            <label class="custom-control-label" for="notify_xp_earned">Notify XP Earned</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_level_up" name="notify_level_up" value="1" {{ $settings->notify_level_up ? 'checked' : '' }}>
                            <label class="custom-control-label" for="notify_level_up">Notify Level Up</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_badge_earned" name="notify_badge_earned" value="1" {{ $settings->notify_badge_earned ? 'checked' : '' }}>
                            <label class="custom-control-label" for="notify_badge_earned">Notify Badge Earned</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="notify_streak" name="notify_streak" value="1" {{ $settings->notify_streak ? 'checked' : '' }}>
                            <label class="custom-control-label" for="notify_streak">Notify Streak</label>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Leaderboard Sort By</label>
                                <select class="form-control" name="leaderboard_sort_by">
                                    <option value="xp" {{ $settings->leaderboard_sort_by == 'xp' ? 'selected' : '' }}>Total XP</option>
                                    <option value="accuracy" {{ $settings->leaderboard_sort_by == 'accuracy' ? 'selected' : '' }}>Quiz Accuracy</option>
                                    <option value="quiz_count" {{ $settings->leaderboard_sort_by == 'quiz_count' ? 'selected' : '' }}>Quizzes Completed</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Leaderboard Period</label>
                                <select class="form-control" name="leaderboard_period">
                                    <option value="1" {{ $settings->leaderboard_period == 1 ? 'selected' : '' }}>Daily</option>
                                    <option value="2" {{ $settings->leaderboard_period == 2 ? 'selected' : '' }}>Weekly</option>
                                    <option value="3" {{ $settings->leaderboard_period == 3 ? 'selected' : '' }}>Monthly</option>
                                    <option value="4" {{ $settings->leaderboard_period == 4 ? 'selected' : '' }}>All Time</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Leaderboard Users Shown</label>
                        <input type="number" class="form-control" name="leaderboard_users_shown" value="{{ $settings->leaderboard_users_shown }}" min="10" max="1000">
                    </div>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
