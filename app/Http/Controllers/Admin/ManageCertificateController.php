<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendExam;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ManageCertificateController extends Controller {
    public function certificateTemplate() {
        $pageTitle = 'Certificate Template';
        return view('admin.exams.certificate_template', compact('pageTitle'));
    }

    public function certificateTemplateUpdate(Request $request) {
        $request->validate([
            'certificate_template' => 'required',
        ]);

        $general                       = gs();
        $general->verify_url           = $request->verify_url;
        $general->certificate_template = $request->certificate_template;
        $general->save();

        $notify[] = ['success', 'certificate template settings updated successfully'];
        return back()->withNotify($notify);
    }

    public function certificatePreview($id) {
        $getCertificate = AttendExam::completed()->with('user', 'exam', 'certificateUser')->findOrFail($id);
        $user           = $getCertificate->user;
        if (!$user) {
            abort(404);
        }
        $exam = $getCertificate->exam;
        if (!$exam) {
            abort(404);
        }

        $message = "You achieved {$getCertificate->pass_percentage}% in your exam. Well done!";

        $url         = gs('verify_url') . '/' . $getCertificate?->certificateUser?->secret;
        $qrCodeUrl   = certificateQr($url);
        $logo        = '<img src="data:image/png;base64,' . base64_encode(file_get_contents(getFilePath('logoIcon') . '/logo_dark.png')) . '" />';
        $qrCode      = '<img src="data:image/png;base64,' . base64_encode(file_get_contents($qrCodeUrl)) . '" />';
        $certificate = str_replace("{{fullname}}", $user->firstname . ' ' . $user->lastname, gs('certificate_template'));
        $certificate = str_replace("{{site_name}}", gs('site_name'), $certificate);
        $certificate = str_replace("{{site_logo}}", $logo, $certificate);
        $certificate = str_replace("{{qr_code}}", $qrCode, $certificate);
        $certificate = str_replace("{{exam_title}}", $exam->title, $certificate);
        $certificate = str_replace("{{exam_completion_date}}", showDateTime($exam->result_published_at, 'F j, Y'), $certificate);
        $certificate = str_replace("{{exam_based_message}}", $message, $certificate);
        $data        = [
            'certificate' => $certificate,
        ];
        // Generate PDF
        $pdf = Pdf::loadView('certificate_pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('dpi', 110)
            ->setOption('defaultFont', 'sans-serif');
        //
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="certificate.pdf"');
    }
}
