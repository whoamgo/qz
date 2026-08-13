<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\AttendExam;
use App\Models\Exam;
use App\Models\NotificationLog;
use App\Models\Subscription;
use App\Models\UserLogin;
use Illuminate\Http\Request;

class ReportController extends Controller {
    public function subscriptions(Request $request, $userId = null) {
        $pageTitle     = 'Subscription History';
        $subscriptions = Subscription::searchable(['user:username', 'plan:name'])->dateFilter()->orderBy('id', 'desc')->with(['user', 'plan'])->paginate(getPaginate());
        return view('admin.reports.subscriptions', compact('pageTitle', 'subscriptions', ));
    }
    public function exams(Request $request, $userId = null) {
        $pageTitle = 'Exam History';
        $examLogs  = AttendExam::with('exam')->searchable(['exam:title', 'user:username'])->latest()->paginate(getPaginate());
        return view('admin.reports.exams', compact('pageTitle', 'examLogs', ));
    }

    public function examView($id) {
        $pageTitle   = "View Exam";
        $attendExam  = AttendExam::with('examAnswer')->findOrFail($id);
        $isCompleted = $attendExam->status == Status::EXAM_COMPLETED;

        $exam = Exam::where('id', $attendExam->exam_id)
            ->withWhereHas('questions', function ($query) use ($isCompleted) {
                $columns = ['id', 'exam_id', 'title'];

                if ($isCompleted) {
                    $columns[] = 'result_option_id';
                }

                $query->active()
                    ->select($columns)
                    ->withWhereHas('options', function ($q) {
                        $q->active()->select(['id', 'question_id', 'title']);
                    });
            })
            ->firstOrFail();
        $examAnswer = $attendExam->examAnswer()->pluck('option_id', 'question_id')->toArray();
        return view('admin.reports.exam_view', compact('pageTitle', 'exam', 'examAnswer', 'attendExam'));
    }

    public function loginHistory(Request $request) {
        $pageTitle = 'User Login History';
        $loginLogs = UserLogin::orderBy('id', 'desc')->searchable(['user:username'])->dateFilter()->with('user')->paginate(getPaginate());
        return view('admin.reports.logins', compact('pageTitle', 'loginLogs'));
    }

    public function loginIpHistory($ip) {
        $pageTitle = 'Login by - ' . $ip;
        $loginLogs = UserLogin::where('user_ip', $ip)->orderBy('id', 'desc')->with('user')->paginate(getPaginate());
        return view('admin.reports.logins', compact('pageTitle', 'loginLogs', 'ip'));
    }

    public function notificationHistory(Request $request) {
        $pageTitle = 'Notification History';
        $logs      = NotificationLog::orderBy('id', 'desc')->searchable(['user:username'])->dateFilter()->with('user')->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle', 'logs'));
    }

    public function emailDetails($id) {
        $pageTitle = 'Email Details';
        $email     = NotificationLog::findOrFail($id);
        return view('admin.reports.email_details', compact('pageTitle', 'email'));
    }
}
