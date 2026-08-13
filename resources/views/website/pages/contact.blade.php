@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), 'Contact' => route('contact')]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="row g-5 align-items-start">
            <div class="col-lg-7">
                <h1 class="mb-2">Contact Us</h1>
                <p class="w-muted mb-4">Questions, feedback or a problem with a quiz? Send us a message.</p>

                <form action="{{ route('website.contact.submit') }}" method="POST" class="w-card">
                    @csrf
                    <div class="w-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="cName">Name <span class="text-danger">*</span></label>
                                <input type="text" id="cName" name="name" class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', auth()->user()->fullname ?? '') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="cEmail">Email <span class="text-danger">*</span></label>
                                <input type="email" id="cEmail" name="email" class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email', auth()->user()->email ?? '') }}" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="cSubject">Subject <span class="text-danger">*</span></label>
                                <input type="text" id="cSubject" name="subject" class="form-control @error('subject') is-invalid @enderror"
                                       value="{{ old('subject') }}" required>
                                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="cMessage">Message <span class="text-danger">*</span></label>
                                <textarea id="cMessage" name="message" rows="6" class="form-control @error('message') is-invalid @enderror" required>{{ old('message') }}</textarea>
                                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <button type="submit" class="btn w-btn-primary mt-4">
                            <i class="bi bi-send"></i> Send message
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-lg-5">
                <div class="w-card"><div class="w-card-body">
                    <h2 class="w-card-title">Other ways to reach us</h2>
                    @if (!empty($content->data_values->contact_details))
                        <div class="w-muted">@php echo $content->data_values->contact_details; @endphp</div>
                    @else
                        <p class="w-muted mb-0">Send us a message using the form and our team will reply by email.</p>
                    @endif
                </div></div>
            </div>
        </div>
    </div>
</section>
@endsection
