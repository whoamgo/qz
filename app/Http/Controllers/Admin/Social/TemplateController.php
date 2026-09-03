<?php

namespace App\Http\Controllers\Admin\Social;

use App\Constants\SocialStatus as S;
use App\Models\Social\SocialTemplate;
use App\Services\Social\SocialAuditLogger;
use App\Services\Social\SocialPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Caption templates.
 *
 * Rendering is plain {{ variable }} substitution done by SocialTemplate - never
 * Blade or eval. Templates are admin-editable text, and compiling them would
 * make this screen a remote code execution surface.
 */
class TemplateController extends SocialBaseController {

    public function index(Request $request) {
        $pageTitle = 'Caption Templates';

        $query = SocialTemplate::latest('id');

        if ($category = $request->category) {
            $query->where('category', $category);
        }
        if ($platform = $request->platform) {
            $query->where('platform', $platform);
        }

        $templates = $query->paginate(getPaginate())->withQueryString();

        return view('admin.social.templates.index', [
            'pageTitle' => $pageTitle,
            'templates' => $templates,
            'categories'=> SocialTemplate::CATEGORIES,
            'variables' => SocialTemplate::BUILTIN_VARIABLES,
            'platforms' => S::PLATFORMS,
        ]);
    }

    public function store(Request $request) {
        $this->can(SocialPermission::MANAGE_LIBRARY);

        $template = SocialTemplate::create($this->validated($request) + [
            'created_by' => Auth::guard('admin')->id(),
        ]);

        SocialAuditLogger::forModel('template.save', $template, [
            'description' => 'Created template "' . $template->name . '"',
        ]);

        $notify[] = ['success', 'Template created.'];
        return back()->withNotify($notify);
    }

    public function update(Request $request, SocialTemplate $template) {
        $this->can(SocialPermission::MANAGE_LIBRARY);

        $template->update($this->validated($request));

        SocialAuditLogger::forModel('template.save', $template, [
            'description' => 'Updated template "' . $template->name . '"',
        ]);

        $notify[] = ['success', 'Template updated.'];
        return back()->withNotify($notify);
    }

    public function destroy(SocialTemplate $template) {
        $this->can(SocialPermission::MANAGE_LIBRARY);

        $name = $template->name;
        $template->delete();

        $notify[] = ['success', 'Deleted template "' . $name . '".'];
        return back()->withNotify($notify);
    }

    /** Renders the template against sample values so the admin can see it. */
    public function preview(Request $request, SocialTemplate $template) {
        $sample = [
            'title'      => 'Can You Answer This GK Question?',
            'caption'    => 'Which Indian state has the longest coastline?',
            'quiz_url'   => rtrim((string) config('app.url'), '/') . '/quiz/daily-gk-quiz',
            'website_url'=> rtrim((string) config('app.url'), '/'),
            'category'   => 'General Knowledge',
            'hashtags'   => '#gk #quiz #currentaffairs',
            'date'       => now()->format('d M Y'),
            'time'       => now()->format('h:i A'),
            'cta'        => 'Play now',
            'site_name'  => gs('site_name') ?: config('app.name'),
            'language'   => 'English',
        ];

        return response()->json([
            'rendered'  => $template->render(array_merge($sample, (array) $request->input('values', []))),
            'variables' => $template->variables,
        ]);
    }

    protected function validated(Request $request): array {
        return $request->validate([
            'name'                 => 'required|string|max:150',
            'category'             => ['required', Rule::in(array_keys(SocialTemplate::CATEGORIES))],
            'platform'             => ['nullable', Rule::in(array_keys(S::PLATFORMS))],
            'title_template'       => 'nullable|string|max:500',
            'caption_template'     => 'nullable|string|max:20000',
            'description_template' => 'nullable|string|max:60000',
            'hashtag_template'     => 'nullable|string|max:2000',
            'cta_template'         => 'nullable|string|max:255',
            'is_active'            => 'nullable|boolean',
        ]);
    }
}
