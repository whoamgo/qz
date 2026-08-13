@forelse ($exams as $exam)
    <div class="col-md-6 col-lg-4">
        <div class="exam-view wow fadeInDown" data-wow-delay="0.3s">
            <div class="exam-view-thumb position-relative">
                <img src="{{ getImage(getFilePath('exam') . '/' . $exam->image, getFileSize('exam')) }}" alt="">
                <div class="d-flex gap-2 position-absolute top-2 start-2">
                    <span class="badge badge--{{ $exam->difficulty == 'easy' ? 'success' : ($exam->difficulty == 'medium' ? 'warning' : 'danger') }}">
                        {{ __(ucfirst($exam->difficulty)) }}
                    </span>
                    @if ($exam->isFree())
                        <span class="badge badge--primary">@lang('Free')</span>
                    @else
                        <span class="badge badge--dark">{{ showAmount($exam->price) }}</span>
                    @endif
                </div>
                @if (isset($exam->attend_exam_count))
                    <span class="badge badge--base position-absolute top-2 end-2">
                        <i class="las la-users"></i> {{ $exam->attend_exam_count }}
                    </span>
                @endif
            </div>
            <div class="exam-view-body">
                <div class="exam-view-duration mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"
                         color="currentColor" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <path d="M12 8V12L14 14" />
                    </svg>
                    <span class="value">{{ getAmount($exam->duration) }} @lang('Min')</span>
                    <span class="ms-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"
                             color="currentColor" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 11l3 3L22 4" />
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                        </svg>
                        {{ getAmount($exam->pass_percentage) }}% @lang('Pass')
                    </span>
                </div>
                <h5 class="exam-view-title">
                    <a href="{{ route('user.exam.start', $exam->slug) }}">{{ __($exam->title) }}</a>
                </h5>
                <p class="exam-view-desc">
                    {{ implode(', ', $exam->subjects) }}
                </p>
                <div class="flex-between">
                    <span class="exam-view-category">{{ __($exam?->category?->name ?? '') }}</span>
                    @if ($exam->isFree() || $exam->start_at < now())
                        <a href="{{ route('user.exam.start', $exam->slug) }}" class="btn btn--base btn--md">
                            @lang('Start Now')
                            <span class="icon ms-2">
                                <svg class="transform transition-transform duration-300 group-hover:translate-x-[10px]"
                                     xmlns="http://www.w3.org/2000/svg" width="22" height="16" viewBox="0 0 26 16"
                                     fill="none">
                                    <path
                                          d="M25.1922 8.6582C25.5908 8.2759 25.604 7.64287 25.2217 7.2443L18.9917 0.749164C18.6093 0.35059 17.9763 0.337402 17.5777 0.719708C17.1792 1.10201 17.166 1.73504 17.5483 2.13361L23.0861 7.90707L17.3126 13.4449C16.9141 13.8272 16.9009 14.4602 17.2832 14.8588C17.6655 15.2573 18.2985 15.2705 18.6971 14.8882L25.1922 8.6582ZM0.479171 8.43631L24.4792 8.93631L24.5208 6.93674L0.520829 6.43674L0.479171 8.43631Z"
                                          fill="currentColor"></path>
                                </svg>
                            </span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="col-lg-4">
        <div class="empty__data">
            <div class="empty__data__inner">
                <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none"
                     class="injected-svg"
                     data-src="https://cdn.hugeicons.com/icons/file-not-found-stroke-standard.svg?v=1.0.1"
                     xmlns:xlink="http://www.w3.org/1999/xlink" role="img" color="currentColor">
                    <path d="M15 15C13.8954 15 13 15.8954 13 17V22" stroke="currentColor" stroke-width="1.5"
                          stroke-linejoin="round"></path>
                    <path d="M2 2L22 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                          stroke-linejoin="round"></path>
                    <path
                          d="M6.02496 2H17.9969C19.1014 2 19.9969 2.89543 19.9969 4V15.0145L19.5052 15.5052M4.02496 3.99688L4 19.9984C3.99828 21.1042 4.89421 22.0015 6 22.0015H12.9955L17.5031 17.5031"
                          stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <h4>@lang('No Exam Found')</h4>
            </div>
        </div>
    </div>
@endforelse
