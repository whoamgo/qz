<?php

namespace App\Http\Controllers\Website;

use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Public newsletter sign-up. Writes to the same `subscribers` table the admin
 * Subscriber module already reads, so there is one list, not two.
 */
class SubscriberController extends BaseWebsiteController {
    public function store(Request $request) {
        // The column is varchar(40); validating to the same length stops the
        // database silently truncating a longer address.
        $validator = validator($request->all(), [
            'email' => 'required|email|max:40',
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email'    => 'That does not look like a valid email address.',
            'email.max'      => 'That email address is too long (40 characters maximum).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('email'),
            ], 422);
        }

        // Light throttle so the endpoint cannot be used to stuff the list.
        $key = 'subscribe:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please try again in a few minutes.',
            ], 429);
        }
        RateLimiter::hit($key, 600);

        $email = strtolower(trim($request->email));

        // Already subscribed is a success from the visitor's point of view,
        // and it avoids confirming or denying who is on the list.
        $existing = Subscriber::where('email', $email)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'You are already subscribed. Thanks for being with us!',
            ]);
        }

        $subscriber = new Subscriber();
        $subscriber->email = $email;
        $subscriber->save();

        return response()->json([
            'success' => true,
            'message' => 'Thanks for subscribing. Look out for updates in your inbox.',
        ]);
    }
}
