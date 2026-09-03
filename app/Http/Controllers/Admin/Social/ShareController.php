<?php

namespace App\Http\Controllers\Admin\Social;

use App\Services\Social\QuizMitraContentSource;
use App\Services\Social\SocialPermission;
use Illuminate\Validation\ValidationException;

/**
 * "Share to Social Media" from anywhere in the panel.
 *
 * Opens the publishing wizard with the Quiz Mitra content already filled in.
 * Nothing is created here - the seed goes into the session and the wizard is
 * where the admin decides what actually happens.
 */
class ShareController extends SocialBaseController {

    public function share(string $type, int $id, QuizMitraContentSource $source) {
        $this->can(SocialPermission::CREATE);

        if (!array_key_exists($type, QuizMitraContentSource::TYPES)) {
            throw ValidationException::withMessages(['type' => 'Unknown content type.']);
        }

        $seed = $source->seed($type, $id);

        if (!$seed) {
            $notify[] = ['error', 'That content could not be found.'];
            return back()->withNotify($notify);
        }

        session(['social.seed' => $seed]);

        $notify[] = ['success', 'Content loaded into the publishing wizard. Review it, choose your platforms and publish.'];
        return to_route('admin.social.posts.create')->withNotify($notify);
    }
}
