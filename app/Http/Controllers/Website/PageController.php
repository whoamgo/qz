<?php

namespace App\Http\Controllers\Website;

use App\Models\Frontend;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends BaseWebsiteController {
    public function about() {
        $content = getContent('about.content', true);

        return view('website.pages.about', [
            'seo' => $this->seo([
                'title'       => 'About Us — Our Mission and How the Platform Works',
                'description' => 'Learn about our mission to make competitive exam preparation accessible through free practice quizzes, instant feedback and gamified learning.',
                'canonical'   => route('website.about'),
                'schema'      => [$this->breadcrumbSchema([
                    'Home' => route('home'), 'About' => route('website.about'),
                ])],
            ]),
            'content' => $content,
            'counters' => Frontend::where('data_keys', 'counter.element')->get(),
        ]);
    }

    public function contact() {
        $content = getContent('contact_us.content', true);

        return view('website.pages.contact', [
            'seo' => $this->seo([
                'title'       => 'Contact Us — Support and Feedback',
                'description' => 'Get in touch with our support team for help with quizzes, your account, or to share feedback and report issues.',
                'canonical'   => route('contact'),
                'schema'      => [$this->breadcrumbSchema([
                    'Home' => route('home'), 'Contact' => route('contact'),
                ])],
            ]),
            'content' => $content,
        ]);
    }

    /**
     * Creates a support ticket, matching the behaviour of the existing
     * SiteController::contactSubmit so both entry points behave the same.
     */
    public function contactSubmit(Request $request) {
        $request->validate([
            'name'    => 'required|string|max:191',
            'email'   => 'required|email|max:191',
            'subject' => 'required|string|max:191',
            'message' => 'required|string|max:5000',
        ]);

        $ticket = new SupportTicket();
        $ticket->user_id    = auth()->id() ?? 0;
        $ticket->name       = $request->name;
        $ticket->email      = $request->email;
        $ticket->priority   = 2;
        $ticket->ticket     = mt_rand(100000, 999999);
        $ticket->subject    = $request->subject;
        $ticket->last_reply = now();
        $ticket->status     = 0;
        $ticket->save();

        $message = new SupportMessage();
        $message->support_ticket_id = $ticket->id;
        $message->message           = $request->message;
        $message->save();

        return back()->with('success', 'Thank you for reaching out. Our team will reply to you by email shortly.');
    }

    public function privacy() {
        return $this->policyPage('privacy', 'Privacy Policy', 'How we collect, use and protect your personal data on this platform.');
    }

    public function terms() {
        return $this->policyPage('terms', 'Terms & Conditions', 'The terms governing your use of this quiz platform, accounts, content and subscriptions.');
    }

    public function disclaimer() {
        return $this->policyPage('disclaimer', 'Disclaimer', 'Important information about the accuracy and intended use of the educational content on this site.');
    }

    /**
     * Policy pages are stored as policy_pages.element rows. Matching is by
     * slug first, then by a loose title match, so admin-created pages are
     * picked up without hard-coding ids.
     */
    private function policyPage(string $key, string $title, string $description) {
        $pages = Frontend::where('data_keys', 'policy_pages.element')->get();

        $page = $pages->first(function ($p) use ($key, $title) {
            $slug  = (string) ($p->slug ?? '');
            $ptitle = strtolower((string) ($p->data_values->title ?? ''));
            return str_contains($slug, $key)
                || str_contains($ptitle, strtolower(explode(' ', $title)[0]));
        });

        $routeName = 'website.' . ($key === 'terms' ? 'terms' : $key);

        return view('website.pages.policy', [
            'seo' => $this->seo([
                'title'       => $title,
                'description' => $description,
                'canonical'   => route($routeName),
                'schema'      => [$this->breadcrumbSchema([
                    'Home' => route('home'), $title => route($routeName),
                ])],
            ]),
            'title'    => $title,
            'page'     => $page,
            'allPages' => $pages,
        ]);
    }
}
