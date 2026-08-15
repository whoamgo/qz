<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Exam;
use App\Models\GetCertificateUser;
use App\Models\Option;
use App\Models\Question;
use App\Rules\FileTypeValidate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ManageExamController extends Controller {
    public function add($id = 0) {

        $pageTitle = "Add Exam";
        $exam      = null;
        if ($id) {
            $pageTitle = "Update Exam";
            $exam      = Exam::findOrFail($id);
        }
        $categories = Category::active()->get();
        return view('admin.exams.add', compact('pageTitle', 'exam', 'categories'));
    }

    public function store(Request $request, $id = 0) {
        $imgValidation = $id ? 'nullable' : 'required';
        $request->validate([
            'category_id'         => 'required|integer|exists:categories,id',
            'difficulty'          => 'required|in:easy,medium,hard',
            'exam_type'           => 'required|in:free,paid',
            'price'               => 'required_if:exam_type,paid|numeric|min:0',
            'title'               => 'required|string|max:255',
            'duration'            => 'required|integer|gt:0|max:60',
            'question_quantity'   => 'required|integer|gt:0|max:60',
            'pass_percentage'     => 'required|integer|gt:0|lt:100',
            'subjects'            => 'required|array|min:1',
            'subjects.*'          => 'required|string|distinct',
            'start_at'            => 'required_if:exam_type,paid|nullable|date_format:Y-m-d h:i A',
            'result_published_at' => 'required_if:exam_type,paid|nullable|date_format:Y-m-d h:i A|after:start_at',
            'image'               => [$imgValidation, 'image', new FileTypeValidate(['jpeg', 'jpg', 'png', 'webp'])],
        ]);

        if ($id) {
            $exam    = Exam::findOrFail($id);
            $message = 'Exam updated successfully';
        } else {
            $exam    = new Exam();
            $message = 'Exam added successfully';
        }

        if ($request->hasFile('image')) {
            try {
                $old         = $exam->image;
                $exam->image = fileUploader($request->image, getFilePath('exam'), getFileSize('exam'), $old);
            } catch (\Exception $exp) {
                $notify[] = ['error', 'Couldn\'t upload your image'];
                return back()->withNotify($notify);
            }
        }

        $exam->category_id       = $request->category_id;
        $exam->difficulty        = $request->difficulty;
        $exam->exam_type         = $request->exam_type;
        $exam->price             = $request->exam_type == 'paid' ? $request->price : 0;
        $exam->title             = $request->title;
        $exam->slug              = slug($request->title);
        $exam->duration          = $request->duration;
        $exam->question_quantity = $request->question_quantity;
        $exam->pass_percentage   = $request->pass_percentage;
        $exam->subjects          = $request->subjects;

        if ($request->exam_type == 'paid') {
            $exam->start_at          = Carbon::createFromFormat('Y-m-d h:i A', $request->start_at)->format('Y-m-d H:i:s');
            $exam->result_published_at = Carbon::createFromFormat('Y-m-d h:i A', $request->result_published_at)->format('Y-m-d H:i:s');
        } else {
            $exam->start_at          = now()->subDay()->format('Y-m-d H:i:s');
            $exam->result_published_at = now()->addYears(10)->format('Y-m-d H:i:s');
            $exam->result_published  = Status::YES;
        }

        $exam->save();

        $notify[] = ['success', $message];
        return to_route('admin.exams.questions', $exam->id)->withNotify($notify);

    }

    public function index() {
        $pageTitle = "All Exams";
        $exams     = Exam::searchable('title')->latest()->paginate(getPaginate());
        return view('admin.exams.index', compact('pageTitle', 'exams'));
    }
    public function result() {
        $pageTitle = "Declare Results";
        $exams     = Exam::where('result_published_at', '<', now())->where('result_published', Status::NO)->searchable('title')->latest()->paginate(getPaginate());
        return view('admin.exams.index', compact('pageTitle', 'exams'));
    }

    public function status($id) {
        return Exam::changeStatus($id);
    }

    public function questions($id) {
        $exam      = Exam::findOrFail($id);
        $pageTitle = "All Questions";
        $questions = Question::where('exam_id', $exam->id)->searchable('title')->with(['options', 'result'])->latest()->paginate(getPaginate());
        return view('admin.exams.questions', compact('exam', 'questions', 'pageTitle'));
    }

    public function questionStore(Request $request, $examId, $id = 0) {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $exam = Exam::findOrFail($examId);
        if ($id) {
            $question = Question::findOrFail($id);
            $message  = 'Question updated successfully';
        } else {
            $question = new Question();
            $message  = 'Question added successfully';
        }

        $question->exam_id = $exam->id;
        $question->title   = $request->title;
        $question->save();

        $notify[] = ['success', $message];
        return back()->withNotify($notify);
    }

    public function questionStatus($id) {
        return Question::changeStatus($id);
    }

    public function optionStore(Request $request, $id = 0) {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'question_id' => 'required|integer|exists:questions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->all()]);
        }

        if ($id) {
            $option = Option::where('id', $id)->first();
            if (!$option) {
                return response()->json(['error' => 'Option not found']);
            }
            $notification = 'Option updated successfully';
        } else {
            $option              = new Option();
            $option->question_id = $request->question_id;
            $notification        = 'Option added successfully';
        }

        $option->title = $request->title;
        $option->save();

        $question = $option->question;
        $options  = $question->options()->get();
        return response()->json(['success' => $notification, 'options' => $options]);
    }

    public function optionStatus($id) {
        $option = Option::where('id', $id)->with('question')->first();
        if (!$option) {
            return response()->json(['success' => 'Option not found']);
        }
        if ($option->status == Status::ENABLE) {
            $option->status = Status::DISABLE;
        } else {
            $option->status = Status::ENABLE;
        }
        $option->save();
        $question = $option->question;
        $options  = $question->options()->get();
        return response()->json(['success' => 'Status changed successfully', 'options' => $options]);
    }

    public function questionGenerate(Request $request, $id) {

        $validator = Validator::make($request->all(), [
            'prompt' => 'required|string',
        ]);
        if ($validator->fails()) {
            return responseError('error', $validator->errors()->all());
        }

        $apiToken = gs('gemini_api_key');
        if (!$apiToken) {
            return responseError('missing_api_token', 'API key is missing for the selected engine.');
        }

        $exam = Exam::where('id', $id)->first();
        if (!$exam) {
            return responseError('error', 'Exam not found');
        }

        $response = $this->geminiApi($request, $exam);
        if (isset($response['status']) && $response['status'] == 'error') {
            return responseError('api_error', $response['message']);
        }

        $questionData = $response['data'];

        if (!$questionData) {
            return responseError('error', 'No question generated');
        }

        if (!$questionData['questions']) {
            return responseError('error', 'No question generated');
        }

        foreach ($questionData['questions'] as $singleQuestion) {
            $question          = new Question();
            $question->exam_id = $exam->id;
            $question->title   = $singleQuestion['question'];
            $question->save();

            foreach ($singleQuestion['options'] as $key => $singleOption) {
                $option              = new Option();
                $option->question_id = $question->id;
                $option->title       = $singleOption;
                $option->save();
                if ($key === $singleQuestion['answer']) {
                    $question->result_option_id = $option->id;
                    $question->save();
                }
            }
        }

        return responseSuccess('success', 'Question generated successfully');

    }

    private function geminiApi($request, $exam) {
        try {

            $apiKey   = gs('gemini_api_key');
            $subjects = implode(',', $exam->subjects);

            $instruction = <<<PROMPT
You are an expert exam question generator AI.

The admin will provide a fully custom instruction text, which must be followed strictly.

Admin Prompt:
$request->prompt
-----------------------------------------

Exam Settings:
Total Questions: $exam->question_quantity
Subjects:  $subjects // ["Bangla", "Math", "General Knowledge", ...]
Difficulty: $exam->difficulty

-----------------------------------------
Your Task:
1. Read the **Admin Prompt** carefully.
2. Combine the Admin Prompt with the Subject Instructions to generate all questions.
3. Distribute the total questions automatically across all subjects:
   - Divide equally.
   - If not divisible, add remaining questions to the last subject.
4. For each question, provide:
   - "subject"
   - "question"
   - "options": { "A": "...", "B": "...", "C": "...", "D": "..." }
   - "answer": one of "A", "B", "C", or "D"

-----------------------------------------
Output Format (STRICT):
Return ONLY valid JSON in the format:

{
  "meta": {
    "total_questions": number,
    "difficulty": "string",
    "subjects": ["string"],
    "distribution": {
      "Subject1": number,
      "Subject2": number
    },
    "admin_prompt_used": "string"
  },
  "questions": [
    {
      "subject": "string",
      "question": "string",
      "options": {
        "A": "string",
        "B": "string",
        "C": "string",
        "D": "string"
      },
      "answer": "A"
    }
  ]
}

-----------------------------------------
Rules:
- STRICTLY follow the Admin Prompt.
- Use only the subjects and instructions provided by the admin.
- Questions must be original and exam-ready.
- Output must be PURE JSON — no markdown, no explanation.
- Do not include text outside the JSON.

PROMPT;

            $geminiResponse = Http::withHeaders([
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])->timeout(300)->post(
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
                [
                    'contents'         => [
                        ['parts' => [['text' => $instruction]]],
                    ],
                    'generationConfig' => [
                        'temperature'      => 0.7,
                        'maxOutputTokens'  => 80000,
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );

            $content = $geminiResponse->json();

            // Check for API-level errors (quota exceeded, invalid key, etc.)
            if (isset($content['error'])) {
                $apiError = $content['error']['message'] ?? 'Unknown API error';
                $apiCode  = $content['error']['code'] ?? 0;
                Log::error('Gemini API error', ['code' => $apiCode, 'message' => $apiError]);
                return [
                    'status'  => 'error',
                    'message' => "Gemini API error ($apiCode): $apiError",
                ];
            }

            if (!$content || !isset($content['candidates'][0]['content']['parts'][0]['text'])) {
                Log::error('Gemini API returned unexpected structure', ['response' => $content]);
                return [
                    'status'  => 'error',
                    'message' => 'Gemini API returned an empty or unexpected response.',
                ];
            }

            $rawText = $content['candidates'][0]['content']['parts'][0]['text'];

            Log::info('Gemini raw response text', ['rawText' => $rawText]);

            // Extract JSON from markdown code fences if present
            if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $rawText, $matches)) {
                $cleanText = trim($matches[1]);
            } else {
                // No code fences — try to find the JSON object directly
                $cleanText = trim($rawText);
            }

            // Ensure we have content that starts with { and ends with }
            $firstBrace = strpos($cleanText, '{');
            $lastBrace  = strrpos($cleanText, '}');
            if ($firstBrace !== false && $lastBrace !== false) {
                $cleanText = substr($cleanText, $firstBrace, $lastBrace - $firstBrace + 1);
            }

            $questionData = json_decode($cleanText, true);

            Log::info('Gemini parsed question data', ['parsed' => $questionData]);

            if (!is_array($questionData)) {
                Log::error('Gemini JSON decode failed', ['cleanText' => $cleanText, 'json_error' => json_last_error_msg()]);
                return [
                    'status'  => 'error',
                    'message' => 'Invalid JSON response from Gemini. Error: ' . json_last_error_msg(),
                ];
            }

            if (!isset($questionData['questions']) || !is_array($questionData['questions'])) {
                return [
                    'status'  => 'error',
                    'message' => 'Invalid response from Gemini. Required key missing: questions',
                ];
            }

            return [
                'status' => 'success',
                'data'   => $questionData,
            ];

        } catch (\Throwable $th) {
            return ['status' => 'error', 'message' => $th->getMessage()];
        }
    }

    public function questionResult(Request $request, $id) {
        $request->validate([
            'option_id' => 'required|integer|exists:options,id',
        ]);

        $question = Question::findOrFail($id);
        $option   = Option::where('question_id', $question->id)->where('id', $request->option_id)->first();

        if (!$option) {
            $notify[] = ['error', 'Question not found yet!'];
            return back()->withNotify($notify);
        }

        $question->result_option_id = $option->id;
        $question->save();

        $notify[] = ['success', 'Answer submitted successfully'];
        return back()->withNotify($notify);
    }

    public function publishResult($id) {
        $exam = Exam::where('result_published', Status::NO)->where('result_published_at', '<=', now())->withWhereHas('attendExam', function ($query) {
            $query->withWhereHas('examAnswer', function ($q) {
                $q->with('question:id,result_option_id');
            });
        })->where('id', $id)->first();

        if (!$exam) {
            $notify[] = ['error', 'Result already published!'];
            return back()->withNotify($notify);
        }

        $totalQuestions = $exam->questions->count() > 0 ? $exam->questions->count() : 1;

        foreach ($exam->attendExam as $attendExam) {
            $correctCount = 0;
            foreach ($attendExam->examAnswer as $examAnswer) {
                $examAnswer->is_correct = $examAnswer->question->result_option_id == $examAnswer->option_id ? Status::YES : Status::NO;
                $examAnswer->save();

                if ($examAnswer->is_correct == Status::YES) {
                    $correctCount++;
                }
            }
            $passPercentage = ($correctCount / $totalQuestions) * 100;
            $attendExam->correct_answer  = $correctCount;
            $attendExam->pass_percentage = $passPercentage;
            $attendExam->status          = Status::EXAM_COMPLETED;
            $attendExam->save();

            if ($exam->isPaid() && $passPercentage >= $exam->pass_percentage) {
                $existingCertificate = GetCertificateUser::where('user_id', $attendExam->user_id)
                    ->where('attend_exam_id', $attendExam->id)
                    ->first();
                if (!$existingCertificate) {
                    $certificate                 = new GetCertificateUser();
                    $certificate->user_id        = $attendExam->user_id;
                    $certificate->attend_exam_id = $attendExam->id;
                    $certificate->secret         = getTrx();
                    $certificate->save();
                }
            }
        }

        $exam->result_published = Status::YES;
        $exam->save();

        $notify[] = ['success', 'Result published successfully'];
        return back()->withNotify($notify);
    }
}
