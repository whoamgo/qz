@extends('admin.layouts.app')
@include('admin.social.partials.head')

@section('panel')

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">@lang('What each role can do')</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th style="min-width:280px">@lang('Ability')</th>
                        @foreach($roles as $key => $label)
                            <th class="text-center">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($abilities as $ability => $label)
                        <tr>
                            <td>{{ $label }}</td>
                            @foreach($matrix as $row)
                                <td class="text-center">
                                    @if($row['abilities'][$ability] ?? false)
                                        <i class="las la-check-circle text--success" style="font-size:18px"></i>
                                    @else
                                        <i class="las la-minus text-muted"></i>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <small class="social-counter">
            @lang('Connecting accounts and changing settings stay with the Super Admin, because a connection grants long-lived credentials that can publish to a live audience. Every action is re-checked on the server, so hiding a button is never the only thing standing between a role and an action.')
        </small>
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">@lang('Admin accounts')</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th>@lang('Admin')</th>
                        <th>@lang('Email')</th>
                        <th>@lang('Role')</th>
                        <th>@lang('Action')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($admins as $admin)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if($admin->image)
                                        <img src="{{ getImage(getFilePath('adminProfile') . '/' . $admin->image, getFileSize('adminProfile')) }}"
                                             class="social-avatar" style="width:32px;height:32px" alt="">
                                    @endif
                                    {{ $admin->name }}
                                    @if($admin->id === auth('admin')->id())
                                        <span class="badge badge--primary">@lang('You')</span>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $admin->email }}</td>
                            <td><span class="badge badge--info">{{ $admin->role_name }}</span></td>
                            <td>
                                <form action="{{ route('admin.social.settings.roles.update', $admin->id) }}" method="POST"
                                      class="d-flex gap-2" style="max-width:340px">
                                    @csrf
                                    <select name="role" class="form-control form-control-sm">
                                        @foreach($roles as $key => $label)
                                            <option value="{{ $key }}" @selected($admin->role === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn--primary">@lang('Save')</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@push('breadcrumb-plugins')
    <a href="{{ route('admin.social.settings.index') }}" class="btn btn-sm btn-outline--primary">
        <i class="las la-arrow-left"></i> @lang('Back to settings')
    </a>
@endpush
