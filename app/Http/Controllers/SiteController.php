<?php

namespace App\Http\Controllers;

use App\Constants\Status;
use App\Models\AdminNotification;
use App\Models\AttendExam;
use App\Models\Exam;
use App\Models\Frontend;
use App\Models\GetCertificateUser;
use App\Models\Language;
use App\Models\Page;
use App\Models\Plan;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class SiteController extends Controller {
    public function index() {

        $pageTitle   = 'Home';
        $sections    = Page::where('tempname', activeTemplate())->where('slug', '/')->first();
        $seoContents = $sections->seo_content;
        $seoImage    = $seoContents?->image ? getImage(getFilePath('seo') . '/' . $seoContents?->image, getFileSize('seo')) : null;

        $popularExams = Exam::active()->popular()->with('category:id,name')->limit(6)->get();

        return view('Template::home', compact('pageTitle', 'sections', 'seoContents', 'seoImage', 'popularExams'));
    }

    public function pages($slug) {
        $page        = Page::where('tempname', activeTemplate())->where('slug', $slug)->firstOrFail();
        $pageTitle   = $page->name;
        $sections    = $page->secs;
        $seoContents = $page->seo_content;
        $seoImage    = $seoContents?->image ? getImage(getFilePath('seo') . '/' . $seoContents?->image, getFileSize('seo')) : null;
        return view('Template::pages', compact('pageTitle', 'sections', 'seoContents', 'seoImage'));
    }

    public function contact() {
        $pageTitle   = "Contact Us";
        $user        = auth()->user();
        $sections    = Page::where('tempname', activeTemplate())->where('slug', 'contact')->first();
        $seoContents = $sections->seo_content;
        $seoImage    = $seoContents?->image ? getImage(getFilePath('seo') . '/' . $seoContents?->image, getFileSize('seo')) : null;
        return view('Template::contact', compact('pageTitle', 'user', 'sections', 'seoContents', 'seoImage'));
    }

    public function contactSubmit(Request $request) {
        $request->validate([
            'name'    => 'required',
            'email'   => 'required',
            'subject' => 'required|string|max:255',
            'message' => 'required',
        ]);

        $request->session()->regenerateToken();

        if (!verifyCaptcha()) {
            $notify[] = ['error', 'Invalid captcha provided'];
            return back()->withNotify($notify);
        }

        $random = getNumber();

        $ticket           = new SupportTicket();
        $ticket->user_id  = auth()->id() ?? 0;
        $ticket->name     = $request->name;
        $ticket->email    = $request->email;
        $ticket->priority = Status::PRIORITY_MEDIUM;

        $ticket->ticket     = $random;
        $ticket->subject    = $request->subject;
        $ticket->last_reply = Carbon::now();
        $ticket->status     = Status::TICKET_OPEN;
        $ticket->save();

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = auth()->user() ? auth()->user()->id : 0;
        $adminNotification->title     = 'A new contact message has been submitted';
        $adminNotification->click_url = urlPath('admin.ticket.view', $ticket->id);
        $adminNotification->save();

        $message                    = new SupportMessage();
        $message->support_ticket_id = $ticket->id;
        $message->message           = $request->message;
        $message->save();

        $notify[] = ['success', 'Ticket created successfully!'];

        return to_route('ticket.view', [$ticket->ticket])->withNotify($notify);
    }

    public function policyPages($slug) {
        $policy      = Frontend::where('tempname', activeTemplateName())->where('slug', $slug)->where('data_keys', 'policy_pages.element')->firstOrFail();
        $pageTitle   = $policy->data_values->title;
        $seoContents = $policy->seo_content;
        $seoImage    = $seoContents?->image ? frontendImage('policy_pages', $seoContents?->image, getFileSize('seo'), true) : null;
        return view('Template::policy', compact('policy', 'pageTitle', 'seoContents', 'seoImage'));
    }

    public function changeLanguage($lang = null) {
        $language = Language::where('code', $lang)->first();
        if (!$language) {
            $lang = 'en';
        }

        session()->put('lang', $lang);
        return back();
    }

    public function pricing() {
        $pageTitle   = "Pricing";
        $plans       = Plan::active()->get();
        $sections    = Page::where('tempname', activeTemplate())->where('slug', 'pricing')->first();
        $seoContents = $sections->seo_content;
        $seoImage    = $seoContents?->image ? getImage(getFilePath('seo') . '/' . $seoContents?->image, getFileSize('seo')) : null;
        return view('Template::pricing', compact('pageTitle', 'sections', 'seoContents', 'seoImage', 'plans'));
    }

    public function exams() {
        $pageTitle = "All Exam";
        $exams     = Exam::active()->running()->whereDoesntHave('attendExam', function ($q) {
            $q->where('user_id', auth()->id());
        })->searchable(['title', 'category:name,slug'])->latest()->paginate(getPaginate(12));
        $sections    = Page::where('tempname', activeTemplate())->where('slug', 'exams')->first();
        $seoContents = $sections->seo_content;
        $seoImage    = $seoContents?->image ? getImage(getFilePath('seo') . '/' . $seoContents?->image, getFileSize('seo')) : null;
        return view('Template::exams', compact('pageTitle', 'sections', 'seoContents', 'seoImage', 'exams'));
    }
    public function blog() {
        $blogs       = Frontend::where('tempname', activeTemplateName())->where('data_keys', 'blog.element')->latest()->paginate(getPaginate());
        $pageTitle   = 'Blogs';
        $sections    = Page::where('tempname', activeTemplate())->where('slug', 'blog')->first();
        $seoContents = $sections->seo_content;
        $seoImage    = $seoContents?->image ? frontendImage('blog', $seoContents?->image, getFileSize('seo'), true) : null;
        return view('Template::blog', compact('blogs', 'pageTitle', 'seoContents', 'seoImage', 'sections'));
    }
    public function blogDetails($slug) {
        $blog            = Frontend::where('slug', $slug)->where('data_keys', 'blog.element')->firstOrFail();
        $recentBlogs     = Frontend::where('data_keys', 'blog.element')->where('id', '!=', $blog->id)->latest()->limit(3)->get();
        $pageTitle       = $blog->data_values->title;
        $customPageTitle = 'Blog Detail';
        $seoContents     = $blog->seo_content;
        $seoImage        = $seoContents?->image ? frontendImage('blog', $seoContents?->image, getFileSize('seo'), true) : null;
        return view('Template::blog_details', compact('blog', 'pageTitle', 'seoContents', 'seoImage', 'recentBlogs', 'customPageTitle'));
    }

    public function cookieAccept() {
        Cookie::queue('gdpr_cookie', gs('site_name'), 43200);
    }

    public function cookiePolicy() {
        $cookieContent = Frontend::where('data_keys', 'cookie.data')->first();
        abort_if($cookieContent->data_values->status != Status::ENABLE, 404);
        $pageTitle = 'Cookie Policy';
        $cookie    = Frontend::where('data_keys', 'cookie.data')->first();
        return view('Template::cookie', compact('pageTitle', 'cookie'));
    }

    public function placeholderImage($size = null) {
        $imgWidth  = explode('x', $size)[0];
        $imgHeight = explode('x', $size)[1];
        $text      = $imgWidth . '×' . $imgHeight;
        $fontFile  = realpath('assets/font/solaimanLipi_bold.ttf');
        $fontSize  = round(($imgWidth - 50) / 8);
        if ($fontSize <= 9) {
            $fontSize = 9;
        }
        if ($imgHeight < 100 && $fontSize > 30) {
            $fontSize = 30;
        }

        $image     = imagecreatetruecolor($imgWidth, $imgHeight);
        $colorFill = imagecolorallocate($image, 100, 100, 100);
        $bgFill    = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgFill);
        $textBox    = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textWidth  = abs($textBox[4] - $textBox[0]);
        $textHeight = abs($textBox[5] - $textBox[1]);
        $textX      = ($imgWidth - $textWidth) / 2;
        $textY      = ($imgHeight + $textHeight) / 2;
        header('Content-Type: image/jpeg');
        imagettftext($image, $fontSize, 0, $textX, $textY, $colorFill, $fontFile, $text);
        imagejpeg($image);
        imagedestroy($image);
    }

    public function maintenance() {
        $pageTitle = 'Maintenance Mode';
        if (gs('maintenance_mode') == Status::DISABLE) {
            return to_route('home');
        }
        $maintenance = Frontend::where('data_keys', 'maintenance.data')->first();
        return view('Template::maintenance', compact('pageTitle', 'maintenance'));
    }

    public function verifyCertificate($secret) {
        $getCertificateData = GetCertificateUser::where('secret', $secret)->first();

        if (!$getCertificateData) {
            $notify[] = ['error', 'Invalid Secret'];
            return back()->withNotify($notify);
        }

        $attendExam = AttendExam::completed()->with('user', 'exam')->find($getCertificateData->attend_exam_id);
        if (!$attendExam) {
            $notify[] = ['error', 'Invalid Exam Found'];
            return back()->withNotify($notify);
        }

        $user = User::active()->find($getCertificateData->user_id);
        if (!$user) {
            $notify[] = ['error', 'Invalid User Found'];
            return back()->withNotify($notify);
        }
        $pageTitle = 'Verify Certificate';
        return view('Template::verify_certificate', compact('user', 'attendExam', 'getCertificateData', 'pageTitle'));
    }
}
