<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Services\Ai\AiProviderException;
use App\Services\Social\QuizMitraContentSource;
use App\Services\Social\SocialAiContentService;
use App\Services\Social\SocialPermission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The "Generate Social Content" button.
 *
 * Always returns suggestions for the admin to edit; it never writes a post and
 * never publishes. That separation is deliberate - the generator is a drafting
 * aid, and the human stays in the loop by construction rather than by policy.
 */
class AiContentController extends SocialBaseController {

    public function generate(
        Request $request,
        SocialAiContentService $ai,
        QuizMitraContentSource $source
    ) {
        $this->can(SocialPermission::USE_AI);

        $request->validate([
            'platforms'   => 'required|array|min:1',
            'platforms.*' => Rule::in(array_keys(S::PLATFORMS)),

            'source_type' => ['nullable', Rule::in(array_merge(array_keys(QuizMitraContentSource::TYPES), ['text']))],
            'source_id'   => 'nullable|integer',

            'title'       => 'nullable|string|max:300',
            'body'        => 'nullable|string|max:20000',
            'url'         => 'nullable|url|max:500',
            'language'    => 'nullable|string|max:30',
            'tone'        => 'nullable|string|max:60',
        ]);

        if ($reason = $ai->unavailableReason()) {
            return response()->json(['success' => false, 'message' => $reason], 422);
        }

        $material = $this->material($request, $source);

        if (!trim((string) ($material['body'] ?? '')) && !trim((string) ($material['title'] ?? ''))) {
            return response()->json([
                'success' => false,
                'message' => 'There is nothing to write from. Add a title or some content first, or pick a quiz to base the post on.',
            ], 422);
        }

        try {
            $generated = $ai->generate($material, $request->platforms, [
                'language' => $request->input('language', 'English'),
                'tone'     => $request->input('tone', 'energetic but factual'),
            ]);

            return response()->json([
                'success'   => true,
                'generated' => $generated,
                // Repeated in the response so the UI cannot forget to say it.
                'notice'    => 'AI-generated copy. Review and edit it before publishing.',
            ]);
        } catch (AiProviderException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'The content assistant could not complete the request. Try again in a moment.',
            ], 500);
        }
    }

    /** Assembles the source material, from a Quiz Mitra entity or free text. */
    protected function material(Request $request, QuizMitraContentSource $source): array {
        $type = $request->input('source_type', 'text');

        if ($type !== 'text' && $request->source_id) {
            $seed = $source->seed($type, $request->source_id);

            if ($seed) {
                return [
                    'type'  => $type,
                    'title' => $seed['title'] ?? null,
                    'body'  => trim(($seed['caption'] ?? '') . "\n\n" . ($seed['description'] ?? '')),
                    'url'   => $seed['quiz_url'] ?? $seed['website_url'] ?? null,
                ];
            }
        }

        return [
            'type'  => 'text',
            'title' => $request->title,
            'body'  => $request->body,
            'url'   => $request->url,
        ];
    }
}
