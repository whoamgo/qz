@extends('admin.layouts.app')

@section('panel')
    <div class="row gy-4">

        <div class="col-xxl-3 col-sm-6">

            <x-widget
                      style="6"
                      link="{{ route('admin.users.all') }}"
                      icon="las la-users"
                      title="Total Users"
                      value="{{ $widget['total_users'] }}"
                      bg="primary" />
        </div><!-- dashboard-w1 end -->
        <div class="col-xxl-3 col-sm-6">
            <x-widget
                      style="6"
                      link="{{ route('admin.users.active') }}"
                      icon="las la-user-check"
                      title="Active Users"
                      value="{{ $widget['verified_users'] }}"
                      bg="success" />
        </div><!-- dashboard-w1 end -->
        <div class="col-xxl-3 col-sm-6">
            <x-widget
                      style="6"
                      link="{{ route('admin.users.email.unverified') }}"
                      icon="lar la-envelope"
                      title="Email Unverified Users"
                      value="{{ $widget['email_unverified_users'] }}"
                      bg="danger" />
        </div><!-- dashboard-w1 end -->
        <div class="col-xxl-3 col-sm-6">
            <x-widget
                      style="6"
                      link="{{ route('admin.users.mobile.unverified') }}"
                      icon="las la-comment-slash"
                      title="Mobile Unverified Users"
                      value="{{ $widget['mobile_unverified_users'] }}"
                      bg="warning" />
        </div><!-- dashboard-w1 end -->
    </div><!-- row end-->

    <div class="row mt-2 gy-4">
        <div class="col-xxl-6">
            <div class="card box-shadow3 h-100">
                <div class="card-body">
                    <h5 class="card-title">@lang('Quiz Overview')</h5>
                    <div class="widget-card-wrapper">
                        <div class="widget-card bg--primary">
                            <a href="{{ route('admin.quiz.index') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-question-circle"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $widget['total_quizzes'] }}</h6>
                                    <p class="widget-card-title">@lang('Total Quizzes')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--success">
                            <a href="{{ route('admin.quiz.index', ['status' => 'published']) }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $widget['published_quizzes'] }}</h6>
                                    <p class="widget-card-title">@lang('Published Quizzes')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--warning">
                            <a href="{{ route('admin.category.index') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-folder-open"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $widget['total_categories'] }}</h6>
                                    <p class="widget-card-title">@lang('Categories')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--info">
                            <a href="{{ route('admin.question-bank.index') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-database"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $widget['total_questions'] }}</h6>
                                    <p class="widget-card-title">@lang('Question Bank')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl-6">
            <div class="card box-shadow3 h-100">
                <div class="card-body">
                    <h5 class="card-title">@lang('Creative Controls')</h5>
                    <div class="widget-card-wrapper">
                        <div class="widget-card bg--success">
                            <a href="{{ route('admin.plan.index') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-list-alt"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $widget['total_plan'] }}</h6>
                                    <p class="widget-card-title">@lang('Total Plan')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--warning">
                            <a href="{{ route('admin.ticket.pending') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-ticket-alt"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $widget['pending_tickets'] }}</h6>
                                    <p class="widget-card-title">@lang('Pending Tickets')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--primary">
                            <a href="{{ route('admin.notifications') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $widget['pending_notification'] }}</h6>
                                    <p class="widget-card-title">@lang('Pending Notification')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                        <div class="widget-card bg--danger">
                            <a href="{{ route('admin.users.banned') }}" class="widget-card-link"></a>
                            <div class="widget-card-left">
                                <div class="widget-card-icon">
                                    <i class="fas fa-ban"></i>
                                </div>
                                <div class="widget-card-content">
                                    <h6 class="widget-card-amount">{{ $widget['banned_user'] }}</h6>
                                    <p class="widget-card-title">@lang('Banned User')</p>
                                </div>
                            </div>
                            <span class="widget-card-arrow">
                                <i class="las la-angle-right"></i>
                            </span>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-none-30 mt-30">
        <div class="col-xl-4 col-lg-6 mb-30">
            <div class="card overflow-hidden">
                <div class="card-body">
                    <h5 class="card-title">@lang('Login By Browser') (@lang('Last 30 days'))</h5>
                    <canvas id="userBrowserChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6 mb-30">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">@lang('Login By OS') (@lang('Last 30 days'))</h5>
                    <canvas id="userOsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6 mb-30">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">@lang('Login By Country') (@lang('Last 30 days'))</h5>
                    <canvas id="userCountryChart"></canvas>
                </div>
            </div>
        </div>
    </div>



        @include('admin.partials.cron_modal')
    @endsection
    @push('breadcrumb-plugins')
        <button class="btn btn-outline--primary btn-sm" data-bs-toggle="modal" data-bs-target="#cronModal">
            <i class="las la-server"></i>@lang('Cron Setup')
        </button>
    @endpush


    @push('script-lib')
        <script src="{{ asset('assets/admin/js/vendor/chart.js.2.8.0.js') }}"></script>
        <script src="{{ asset('assets/admin/js/charts.js') }}"></script>
    @endpush

    @push('script')
        <script>
            "use strict";

            // Login analytics for the last 30 days (browser / OS / country).
            piChart(
                document.getElementById('userBrowserChart'),
                JSON.parse(`@php echo json_encode($chart['user_browser_counter']->keys()); @endphp`),
                JSON.parse(`@php echo json_encode($chart['user_browser_counter']->flatten()); @endphp`)
            );

            piChart(
                document.getElementById('userOsChart'),
                JSON.parse(`@php echo json_encode($chart['user_os_counter']->keys()); @endphp`),
                JSON.parse(`@php echo json_encode($chart['user_os_counter']->flatten()); @endphp`)
            );

            piChart(
                document.getElementById('userCountryChart'),
                JSON.parse(`@php echo json_encode($chart['user_country_counter']->keys()); @endphp`),
                JSON.parse(`@php echo json_encode($chart['user_country_counter']->flatten()); @endphp`)
            );
        </script>
    @endpush
