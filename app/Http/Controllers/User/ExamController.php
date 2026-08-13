<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\AttendExam;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\GetCertificateUser;
use App\Models\Option;
use App\Services\QuizEvaluationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamController extends Controller {
    public function start($slug) {
        $exam = Exam::active()->running()->where('slug', $slug)->withWhereHas('questions', function ($query) {
            $query->active()->select(['id', 'exam_id', 'title'])->withWhereHas('options', function ($q) {
                $q->active()->select(['id', 'question_id', 'title']);
            });
        })->firstOrFail();

        $pageTitle = 'Start Exam';

        $user = auth()->user();

        if ($exam->isPaid()) {
            if (!$user->plan_id) {
                $notify[] = ['error', 'You do not have any active subscription plan.'];
                return back()->withNotify($notify);
            }

            if ($user->exam_limit <= 0) {
                $notify[] = ['error', 'You do not have enough credit to perform this action.'];
                return back()->withNotify($notify);
            }

            if ($user->expired_date < now()->format('Y-m-d')) {
                $notify[] = ['error', 'Your subscription plan has expired. Please renew to continue using the service.'];
                return back()->withNotify($notify);
            }
        }

        $attendExam = AttendExam::where('user_id', $user->id)->where('exam_id', $exam->id)->first();
        if ($attendExam && $attendExam->is_submit == Status::YES) {
            $notify[] = ['error', 'You have already submitted this exam.'];
            return back()->withNotify($notify);
        }

        $examEnd = false;
        if ($attendExam && ($attendExam->end_time < now())) {
            $examEnd = true;
            return view('Template::user.exams.start', compact('pageTitle', 'exam', 'examEnd', 'attendExam'));
        }

        if (!$attendExam) {
            $attendExam             = new AttendExam();
            $attendExam->user_id    = $user->id;
            $attendExam->exam_id    = $exam->id;
            $attendExam->start_time = now();
            $attendExam->end_time   = now()->addMinutes((int) $exam->duration);
            $attendExam->save();

            if ($exam->isPaid()) {
                $user->exam_limit -= 1;
                $user->save();
            }
        }

        return view('Template::user.exams.start', compact('pageTitle', 'exam', 'examEnd', 'attendExam'));
    }

    public function submit(Request $request, $slug) {
        $exam = Exam::active()->running()->where('slug', $slug)->withWhereHas('questions', function ($query) {
            $query->active()->select(['id', 'exam_id', 'title'])->withWhereHas('options', function ($q) {
                $q->active()->select(['id', 'question_id', 'title']);
            });
        })->firstOrFail();

        $examQuestions = $exam->questions->pluck('id')->toArray();

        $request->validate([
            'answer'   => 'required|array',
            'answer.*' => [
                'required',
                'integer',
                Rule::exists('options', 'id'),
            ],
        ], [
            'answer.required'   => 'You must select at least one answer.',
            'answer.array'      => 'Invalid answer format.',
            'answer.*.required' => 'Please select an option for every question.',
            'answer.*.integer'  => 'Invalid option selected.',
            'answer.*.exists'   => 'One or more selected options are invalid.',
        ]);

        foreach ($request->answer as $questionId => $optionId) {
            if (!in_array($questionId, $examQuestions)) {
                $notify[] = ['error', 'Invalid question submitted.'];
                return back()->withNotify($notify);
            }
            $exists = Option::where('id', $optionId)->where('question_id', $questionId)->exists();
            if (!$exists) {
                $notify[] = ['error', 'Invalid option selected.'];
                return back()->withNotify($notify);
            }
        }
        $user = auth()->user();

        if ($exam->isPaid()) {
            if (!$user->plan_id) {
                $notify[] = ['error', 'You do not have any active subscription plan.'];
                return back()->withNotify($notify);
            }

            if ($user->exam_limit <= 0) {
                $notify[] = ['error', 'You do not have enough credit to perform this action.'];
                return back()->withNotify($notify);
            }

            if ($user->expired_date < now()->format('Y-m-d')) {
                $notify[] = ['error', 'Your subscription plan has expired. Please renew to continue using the service.'];
                return back()->withNotify($notify);
            }
        }

        $submitExam = AttendExam::where('user_id', $user->id)->where('exam_id', $exam->id)->where('is_submit', Status::NO)->first();

        if (!$submitExam) {
            $notify[] = ['error', 'You have already submitted the exam.'];
            return back()->withNotify($notify);
        }

        if ($submitExam->end_time < now()) {
            $notify[] = ['error', 'The time for this exam has expired.'];
            return back()->withNotify($notify);
        }

        $data = [];
        foreach ($request->answer as $key => $answer) {
            $data[] = [
                'exam_attend_id' => $submitExam->id,
                'question_id'    => $key,
                'option_id'      => $answer,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        if (!blank($data)) {
            ExamAnswer::insert($data);
        }

        $submitExam->is_submit = Status::YES;
        $submitExam->status    = Status::WAITING_RESULT;
        $submitExam->save();

        // Evaluate exam and award XP
        try {
            QuizEvaluationService::evaluateExamWithXp($submitExam);
        } catch (\Exception $e) {
            // Log error but don't fail the submission
            \Illuminate\Support\Facades\Log::error('XP award failed: ' . $e->getMessage());
        }

        return to_route('user.exam.view', $submitExam->id)->withNotify(['success', 'You have successfully submitted the exam.']);

    }

    public function history() {
        $pageTitle = 'Exam History';
        $examLogs  = AttendExam::where('user_id', auth()->id())->with('exam')->searchable(['exam:title'])->latest()->paginate(getPaginate());
        return view('Template::user.exams.history', compact('pageTitle', 'examLogs'));
    }

    public function view($id) {
        $pageTitle   = "View Exam";
        $attendExam  = AttendExam::where('user_id', auth()->id())->with('examAnswer')->findOrFail($id);
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

        // Get XP data if exam is completed
        $xpData = null;
        $gamificationStatus = null;
        if ($isCompleted) {
            try {
                $gamificationService = new \App\Services\GamificationService(auth()->user());
                $gamificationStatus = $gamificationService->getUserGamificationStatus();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to get gamification status: ' . $e->getMessage());
            }
        }

        return view('Template::user.exams.view', compact('pageTitle', 'exam', 'examAnswer', 'attendExam', 'gamificationStatus'));
    }

    public function certificateDownload($id) {
        $attendExam = AttendExam::completed()->where('user_id', auth()->id())->where('id', $id)->with('exam', 'user')->firstOrFail();

        $user = auth()->user();

        $user = $attendExam->user;
        if (!$user) {
            abort(404);
        }
        $exam = $attendExam->exam;
        if (!$exam) {
            abort(404);
        }

        if ($exam->isFree()) {
            $notify[] = ['error', 'Certificate is not available for free exams.'];
            return back()->withNotify($notify);
        }

        if ($attendExam->pass_percentage < $exam->pass_percentage) {
            $notify[] = ['error', 'You did not pass this exam. Certificate is not available.'];
            return back()->withNotify($notify);
        }

        $getCertificateData = GetCertificateUser::where('user_id', $user->id)->where('attend_exam_id', $attendExam->id)->first();

        if (!$getCertificateData) {
            $secret                             = getTrx();
            $getCertificateData                 = new GetCertificateUser();
            $getCertificateData->user_id        = $user->id;
            $getCertificateData->attend_exam_id = $attendExam->id;
            $getCertificateData->secret         = $secret;
            $getCertificateData->save();
        }

        $url = gs('verify_url') . '/' . $getCertificateData->secret;

        $qrCodeUrl = certificateQr($url);

        $general = gs();

        $message = "You achieved {$attendExam->pass_percentage}% in your exam. Well done!";

        $qrCode = '<img  src="data:image/png;base64,' . base64_encode(file_get_contents($qrCodeUrl)) . '" />';
        $logo   = '<img src="data:image/png;base64,' . base64_encode(file_get_contents(getFilePath('logoIcon') . '/logo_dark.png')) . '" />';

        $certificate = str_replace("{{fullname}}", $user->firstname . ' ' . $user->lastname, $general->certificate_template);
        $certificate = str_replace("{{site_name}}", $general->site_name, $certificate);
        $certificate = str_replace("{{qr_code}}", $qrCode, $certificate);
        $certificate = str_replace("{{site_logo}}", $logo, $certificate);
        $certificate = str_replace("{{exam_title}}", $exam->title, $certificate);
        $certificate = str_replace("{{exam_completion_date}}", showDateTime($exam->created_at, 'F j, Y'), $certificate);
        $certificate = str_replace("{{exam_based_message}}", $message, $certificate);

        $data = [
            'certificate' => $certificate,
        ];

        $pdf = PDF::loadView('certificate_pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('dpi', 110)
            ->setOption('defaultFont', 'sans-serif');

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="certificate.pdf"');
    }
}
