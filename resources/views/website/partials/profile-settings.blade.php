<div class="w-card"><div class="w-card-body">
    <h2 class="w-card-title">Account settings</h2>

    <form id="wSettingsForm" action="{{ route('website.profile.settings.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

        {{-- Avatar --}}
        <div class="d-flex flex-wrap align-items-center gap-4 mb-4 pb-4 border-bottom">
            <img id="wAvatarPreview" class="w-profile-avatar"
                 style="width:96px;height:96px;border:3px solid var(--w-border);"
                 src="{{ getImage(getFilePath('userProfile') . '/' . $user->image, getFileSize('userProfile')) }}"
                 alt="Your profile photo" width="96" height="96">

            <div class="flex-grow-1">
                <label class="form-label d-block mb-1">Profile photo</label>
                <p class="w-text-xs w-muted mb-2">
                    JPG, PNG or WEBP. Square images work best &mdash; shown at {{ getFileSize('userProfile') }}px. Max 2&nbsp;MB.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <label class="btn w-btn-outline btn-sm mb-0" for="wAvatarInput">
                        <i class="bi bi-upload" aria-hidden="true"></i> Choose image
                    </label>
                    <button type="button" class="btn w-btn-outline btn-sm d-none" id="wAvatarClear">
                        <i class="bi bi-x-lg" aria-hidden="true"></i> Cancel
                    </button>
                </div>
                <input type="file" id="wAvatarInput" name="image" accept="image/jpeg,image/png,image/webp"
                       class="visually-hidden @error('image') is-invalid @enderror">
                <div class="w-text-xs mt-2" id="wAvatarName"></div>
                @error('image')<div class="text--danger w-text-sm mt-1">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="sFirst">First name <span class="text-danger">*</span></label>
                <input type="text" id="sFirst" name="firstname" class="form-control" required
                       value="{{ old('firstname', $user->firstname) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="sLast">Last name <span class="text-danger">*</span></label>
                <input type="text" id="sLast" name="lastname" class="form-control" required
                       value="{{ old('lastname', $user->lastname) }}">
            </div>

            {{-- Read-only: changing these goes through the account/verification flow. --}}
            <div class="col-md-6">
                <label class="form-label" for="sEmail">Email</label>
                <input type="email" id="sEmail" class="form-control" value="{{ $user->email }}" disabled readonly>
                <small class="w-muted">Contact support to change your email.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="sUser">Username</label>
                <input type="text" id="sUser" class="form-control" value="{{ $user->username }}" disabled readonly>
            </div>

            <div class="col-12">
                <label class="form-label" for="sAddress">Address</label>
                <input type="text" id="sAddress" name="address" class="form-control"
                       value="{{ old('address', $user->address->address ?? '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="sCity">City</label>
                <input type="text" id="sCity" name="city" class="form-control" value="{{ old('city', $user->address->city ?? '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="sState">State</label>
                <input type="text" id="sState" name="state" class="form-control" value="{{ old('state', $user->address->state ?? '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="sZip">ZIP</label>
                <input type="text" id="sZip" name="zip" class="form-control" value="{{ old('zip', $user->address->zip ?? '') }}">
            </div>
        </div>

        <button type="submit" class="btn w-btn-primary mt-4">Save changes</button>
    </form>
</div></div>
