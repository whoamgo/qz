<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGenerationSetting;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiProviderFactory;
use Illuminate\Http\Request;

class AiGenerationSettingController extends Controller {
    public function index() {
        $pageTitle = 'AI Generation Settings';
        $settings  = AiGenerationSetting::config();

        // Only booleans reach the view - never the key material itself.
        $keyStatus = [
            'gemini'    => $settings->hasKeyFor('gemini'),
            'openai'    => $settings->hasKeyFor('openai'),
            'anthropic' => $settings->hasKeyFor('anthropic'),
        ];

        return view('admin.ai_generator.settings', compact('pageTitle', 'settings', 'keyStatus'));
    }

    public function update(Request $request) {
        $request->validate([
            'provider'              => 'required|in:gemini,openai,anthropic',
            'model'                 => 'required|string|max:100',
            'temperature'           => 'required|numeric|min:0|max:2',
            'max_tokens'            => 'required|integer|min:256|max:200000',
            'request_timeout'       => 'required|integer|min:10|max:600',
            'default_language'      => 'required|in:english,hindi',
            'default_difficulty'    => 'required|in:easy,medium,hard,expert',
            'default_question_type' => 'required|in:mcq,true_false',
            'default_quantity'      => 'required|integer|min:1',
            'max_quantity'          => 'required|integer|min:1|max:500',
            'system_prompt'         => 'nullable|string|max:20000',
            'default_user_prompt'   => 'nullable|string|max:20000',
            'gemini_api_key'        => 'nullable|string|max:500',
            'openai_api_key'        => 'nullable|string|max:500',
            'anthropic_api_key'     => 'nullable|string|max:500',
        ]);

        if ($request->default_quantity > $request->max_quantity) {
            $notify[] = ['error', 'Default quantity cannot exceed the maximum per generation.'];
            return back()->withInput()->withNotify($notify);
        }

        $settings = AiGenerationSetting::config();

        $settings->fill($request->only([
            'provider', 'model', 'temperature', 'max_tokens', 'request_timeout',
            'default_language', 'default_difficulty', 'default_question_type',
            'default_quantity', 'max_quantity', 'system_prompt', 'default_user_prompt',
        ]));
        $settings->enabled = $request->boolean('enabled');

        // Blank means "leave the stored key alone" - the form never renders the
        // existing value, so submitting it unchanged must not wipe it.
        foreach (['gemini_api_key', 'openai_api_key', 'anthropic_api_key'] as $field) {
            $value = trim((string) $request->input($field));
            if ($value !== '') {
                $settings->{$field} = $value;
            }
        }

        $settings->save();

        $notify[] = ['success', 'AI settings updated.'];
        return back()->withNotify($notify);
    }

    /** Clears one stored key. */
    public function clearKey(Request $request) {
        $request->validate(['provider' => 'required|in:gemini,openai,anthropic']);

        $settings = AiGenerationSetting::config();
        $settings->{$request->provider . '_api_key'} = null;
        $settings->save();

        $notify[] = ['success', ucfirst($request->provider) . ' API key cleared.'];
        return back()->withNotify($notify);
    }

    /**
     * Sends a minimal prompt to confirm the key, model and connectivity work.
     * Returns only pass/fail plus the provider's message - never the key.
     */
    public function testConnection(Request $request) {
        $settings = AiGenerationSetting::config();
        $provider = $request->input('provider', $settings->provider);

        try {
            $driver = app(AiProviderFactory::class)->make($settings, $provider);
            $result = $driver->generate(
                'You are a connectivity test. Reply with JSON only.',
                'Return exactly this JSON and nothing else: {"ok":true}'
            );

            return response()->json([
                'success' => true,
                'message' => 'Connection successful. The provider responded normally.',
                'usage'   => [
                    'input_tokens'  => $result->inputTokens,
                    'output_tokens' => $result->outputTokens,
                ],
            ]);
        } catch (AiProviderException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
