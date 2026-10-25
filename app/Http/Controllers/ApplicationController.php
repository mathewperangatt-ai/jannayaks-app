<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentLinkStateUncertainException;
use App\Models\Application;
use App\Models\GeoDistrict;
use App\Models\GeoState;
use App\Models\Payment;
use App\Models\SourceMaterial;
use App\Services\ApplicationPaymentStateService;
use App\Services\OnlineInterviewService;
use App\Services\ProfileUrlService;
use App\Services\PublicProfilePresentationService;
use App\Services\RazorpayPaymentService;
use App\Support\OnlineInterviewCatalog;
use App\Support\PricingAmounts;
use App\Support\TierLabels;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    private const LIVING_TIERS = ['emerging', 'accomplished', 'distinguished'];

    private const FLOW_LANGUAGE_SESSION_KEY = 'apply.language';

    private const FLOW_LANGUAGES = ['ml', 'en'];

    public function create(Request $request): View|JsonResponse
    {
        $language = $this->flowLanguage($request);
        $tiers = self::LIVING_TIERS;
        $sourceMethods = ['online_interview', 'direct_submission'];
        $tierLabels = TierLabels::forLivingTiers();

        $auth = Auth::check();
        $showTiers = $auth || $request->boolean('tiers') || $request->query('step') === 'tiers';

        if (! $auth && ! $showTiers) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'login_required' => true,
                    'intro' => true,
                    'next' => route('apply', ['step' => 'tiers']),
                ]);
            }

            return view('application.intro', [
                'login_required' => true,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'login_required' => ! $auth,
                'offering' => 'standard_profile',
                'source_methods' => $sourceMethods,
            ]);
        }

        return view('application.tier-select', [
            'tiers' => $tiers,
            'source_methods' => $sourceMethods,
            'tier_labels' => $tierLabels,
            'guest' => ! $auth,
            'language' => $language,
            'districts' => GeoDistrict::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Display language for the intake → payment flow: Malayalam by default,
     * switchable via ?lang=en|ml. The choice is kept in the session because
     * sign-in and the Razorpay callback return without the query string.
     */
    private function flowLanguage(Request $request): string
    {
        $requested = $request->query('lang');
        if (is_string($requested) && in_array($requested, self::FLOW_LANGUAGES, true)) {
            $request->session()->put(self::FLOW_LANGUAGE_SESSION_KEY, $requested);

            return $requested;
        }

        $stored = $request->session()->get(self::FLOW_LANGUAGE_SESSION_KEY);

        return is_string($stored) && in_array($stored, self::FLOW_LANGUAGES, true) ? $stored : self::FLOW_LANGUAGES[0];
    }

    /**
     * Supplied wording for the intake form's required-field errors.
     *
     * @return array<string, string>
     */
    private function intakeValidationMessages(Request $request): array
    {
        if ($this->flowLanguage($request) !== 'ml') {
            return [
                'source_method.required' => 'Please select how you would like to submit your content.',
                'district_id.required' => 'Choose the district in Keralam.',
                'district_id.exists' => 'Choose a district from the list.',
                'broad_location.required' => 'Enter a town, city or other broad location.',
                'location_state.required' => 'Enter the state or province.',
                'location_country.required' => 'Enter the country.',
                'location_scope.in' => 'Choose Keralam or somewhere else.',
            ];
        }

        return [
            'source_method.required' => 'നിങ്ങളുടെ ഉള്ളടക്കം എങ്ങനെ സമർപ്പിക്കണമെന്ന് ദയവായി തിരഞ്ഞെടുക്കുക.',
            'full_name.required' => 'പൂർണ്ണനാമം നൽകേണ്ടതാണ്.',
            'contact_email.required_without' => 'ബന്ധപ്പെടാനുള്ള മൊബൈൽ നമ്പർ നൽകിയിട്ടില്ലെങ്കിൽ ഇമെയിൽ നൽകേണ്ടതാണ്.',
            'contact_mobile.required_without' => 'ഇമെയിൽ നൽകിയിട്ടില്ലെങ്കിൽ ബന്ധപ്പെടാനുള്ള മൊബൈൽ നമ്പർ നൽകേണ്ടതാണ്.',
            'district_id.required' => 'കേരളത്തിലെ ജില്ല തിരഞ്ഞെടുക്കുക.',
            'district_id.exists' => 'പട്ടികയിലുള്ള ഒരു ജില്ല തിരഞ്ഞെടുക്കുക.',
            'broad_location.required' => 'പട്ടണം, നഗരം അല്ലെങ്കിൽ മറ്റൊരു വിശാലമായ സ്ഥലം നൽകുക.',
            'location_state.required' => 'സംസ്ഥാനം അല്ലെങ്കിൽ പ്രവിശ്യ നൽകുക.',
            'location_country.required' => 'രാജ്യം നൽകുക.',
            'location_scope.in' => 'കേരളമോ മറ്റൊരു സ്ഥലമോ തിരഞ്ഞെടുക്കുക.',
        ];
    }

    /**
     * Location is required when the customer form sends a scope. Requests that
     * omit it, including existing intake tests, still create an application.
     *
     * @return array<string, mixed>
     */
    private function intakeLocationRules(): array
    {
        return [
            'location_scope' => ['sometimes', 'nullable', 'in:kerala,elsewhere'],
            'district_id' => ['exclude_unless:location_scope,kerala', 'required', 'integer', 'exists:geo_districts,id'],
            'broad_location' => ['exclude_unless:location_scope,elsewhere', 'required', 'string', 'min:2', 'max:120'],
            'location_state' => ['exclude_unless:location_scope,elsewhere', 'required', 'string', 'min:2', 'max:120'],
            'location_country' => ['exclude_unless:location_scope,elsewhere', 'required', 'string', 'min:2', 'max:80'],
        ];
    }

    /**
     * Form-field names kept in the guest session so sign-in can resume them.
     *
     * @return array<string, mixed>
     */
    private function locationIntent(Request $request): array
    {
        $scope = $request->input('location_scope');
        if (! is_string($scope) || ! in_array($scope, ['kerala', 'elsewhere'], true)) {
            return [];
        }

        if ($scope === 'kerala') {
            return [
                'location_scope' => 'kerala',
                'district_id' => (int) $request->input('district_id'),
            ];
        }

        return [
            'location_scope' => 'elsewhere',
            'broad_location' => trim((string) $request->input('broad_location')),
            'location_state' => trim((string) $request->input('location_state')),
            'location_country' => trim((string) $request->input('location_country')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function locationColumns(Request $request): array
    {
        $scope = $request->input('location_scope');
        if (! is_string($scope) || ! in_array($scope, ['kerala', 'elsewhere'], true)) {
            return [
                'location_scope' => null,
                'location_district_id' => null,
                'broad_location' => null,
                'location_state' => null,
                'location_country' => null,
            ];
        }

        if ($scope === 'kerala') {
            return [
                'location_scope' => 'kerala',
                'location_district_id' => (int) $request->input('district_id'),
                'broad_location' => null,
                'location_state' => GeoState::currentDisplayName(),
                'location_country' => GeoState::currentCountryName(),
            ];
        }

        return [
            'location_scope' => 'elsewhere',
            'location_district_id' => null,
            'broad_location' => trim((string) $request->input('broad_location')),
            'location_state' => trim((string) $request->input('location_state')),
            'location_country' => trim((string) $request->input('location_country')),
        ];
    }

    /**
     * Guest (or authenticated) stores Standard Profile intake intent, then
     * registers/logs in before payment. No customer tier choice is collected.
     */
    public function storeIntent(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'source_method' => ['required', 'string', 'in:online_interview,direct_submission'],
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'preferred_slug' => ['nullable', 'string', 'max:128'],
            'contact_email' => ['required_without:contact_mobile', 'nullable', 'email:strict', 'max:255'],
            'contact_mobile' => ['required_without:contact_email', 'nullable', 'string', 'max:32'],
            'contact_whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s]{6,20}$/'],
            'direct_submission_note' => ['nullable', 'string', 'max:500'],
            'honey_bot' => ['nullable', 'string', 'max:0'],
        ] + $this->intakeLocationRules(), $this->intakeValidationMessages($request));

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }
        $preferredSlug = $this->preferredSlugHint($request, (string) $request->input('full_name'));
        if ($preferredSlug['ok'] !== true) {
            $validator->errors()->add('preferred_slug', $preferredSlug['error']);

            return $this->validationErrorResponse($request, $validator);
        }
        if ((string) $request->input('honey_bot', '') !== '') {
            return $this->noContentResponse($request);
        }

        $validated = $validator->safe()->all();
        $intent = [
            'package_tier' => PricingAmounts::STANDARD_INTERNAL_TIER,
            'source_method' => (string) $validated['source_method'],
            'full_name' => (string) $validated['full_name'],
            'preferred_slug' => $preferredSlug['slug'],
            'contact_email' => isset($validated['contact_email']) && $validated['contact_email'] !== '' ? (string) $validated['contact_email'] : null,
            'contact_mobile' => isset($validated['contact_mobile']) && $validated['contact_mobile'] !== '' ? (string) $validated['contact_mobile'] : null,
            'contact_whatsapp' => isset($validated['contact_whatsapp']) && $validated['contact_whatsapp'] !== '' ? (string) $validated['contact_whatsapp'] : null,
            'direct_submission_note' => isset($validated['direct_submission_note']) && $validated['direct_submission_note'] !== '' ? (string) $validated['direct_submission_note'] : null,
        ] + $this->locationIntent($request);

        $request->session()->put('apply.intent', $intent);

        if (Auth::check()) {
            return $this->continueFromIntent($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'login_required' => true,
                'redirect_to' => route('login', ['return' => route('apply.continue')]),
            ]);
        }

        $next = ($intent['source_method'] ?? '') === 'direct_submission'
            ? 'Sign in to continue to document submission.'
            : 'Sign in to continue to the Online Interview.';

        return redirect()->route('login', ['return' => route('apply.continue')])
            ->with('status', $next);
    }

    public function continueFromIntent(Request $request): JsonResponse|RedirectResponse
    {
        if (! Auth::check()) {
            return $this->unauthorizedResponse($request, 'You must sign in before continuing.');
        }

        $intent = $request->session()->get('apply.intent');
        if (! is_array($intent)) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'No application intent found. Start your profile application first.'], 422);
            }

            return redirect()->route('apply', ['step' => 'tiers'])
                ->withErrors(['apply' => 'Start your profile application to continue.']);
        }

        $request->session()->forget('apply.intent');

        $merged = array_merge($intent, [
            'package_tier' => PricingAmounts::STANDARD_INTERNAL_TIER,
            'source_method' => $intent['source_method'] ?? 'online_interview',
            'full_name' => $intent['full_name'] ?? (Auth::user()->name ?? 'Member'),
        ]);

        $request->merge($merged);

        return $this->store($request);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        if (! Auth::check()) {
            return $this->unauthorizedResponse($request, 'You must log in before creating an application.');
        }

        // A2 — one active application workflow per customer. A second
        // application would dead-end at payment (the first holds the
        // one-to-one profile link).
        $existingApplication = Application::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', [
                Application::STATUS_PAYMENT_PENDING,
                Application::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW,
                Application::STATUS_INTAKE_IN_PROGRESS,
                Application::STATUS_INTERVIEW_IN_PROGRESS,
                Application::STATUS_INTERVIEW_SUBMITTED,
                Application::STATUS_DIRECT_SUBMITTED,
                Application::STATUS_AWAITING_EDITORIAL_REVIEW,
                Application::STATUS_IN_EDITORIAL_REVIEW,
                Application::STATUS_EDITORIAL_APPROVED,
                Application::STATUS_EDITORIAL_REVISION_REQUESTED,
                Application::STATUS_AWAITING_PUBLICATION,
                Application::STATUS_PUBLISHED,
            ])
            ->orderByDesc('id')
            ->first();

        if ($existingApplication !== null) {
            return $this->redirectHomeWithExisting($request, $existingApplication);
        }

        $validator = Validator::make($request->all(), [
            'source_method' => ['required', 'string', 'in:online_interview,direct_submission'],
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'preferred_slug' => ['nullable', 'string', 'max:128'],
            'contact_email' => ['required_without:contact_mobile', 'nullable', 'email:strict', 'max:255'],
            'contact_mobile' => ['required_without:contact_email', 'nullable', 'string', 'max:32'],
            'contact_whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s]{6,20}$/'],
            'direct_submission_note' => ['nullable', 'string', 'max:500'],
            'honey_bot' => ['nullable', 'string', 'max:0'],
        ] + $this->intakeLocationRules(), $this->intakeValidationMessages($request));

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }
        $preferredSlug = $this->preferredSlugHint($request, (string) $request->input('full_name'));
        if ($preferredSlug['ok'] !== true) {
            $validator->errors()->add('preferred_slug', $preferredSlug['error']);

            return $this->validationErrorResponse($request, $validator);
        }

        if ((string) $request->input('honey_bot', '') !== '') {
            return $this->noContentResponse($request);
        }

        $validated = $validator->safe()->all();

        return DB::transaction(function () use ($request, $validated, $preferredSlug) {
            $user = Auth::user();

            $application = new Application;
            $application->forceFill([
                'user_id' => $user->id,
                'source_method' => (string) $validated['source_method'],
                'package_tier' => PricingAmounts::STANDARD_INTERNAL_TIER,
                'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
                'full_name' => (string) $validated['full_name'],
                'preferred_display_name' => (string) $validated['full_name'],
                'preferred_slug' => $preferredSlug['slug'],
                ...$this->locationColumns($request),
                'preferred_contact_email' => isset($validated['contact_email']) && $validated['contact_email'] !== '' ? (string) $validated['contact_email'] : null,
                'preferred_contact_mobile' => isset($validated['contact_mobile']) && $validated['contact_mobile'] !== '' ? (string) $validated['contact_mobile'] : null,
                'preferred_whatsapp_mobile' => isset($validated['contact_whatsapp']) && $validated['contact_whatsapp'] !== '' ? (string) $validated['contact_whatsapp'] : null,
                'direct_submission_note' => isset($validated['direct_submission_note']) && $validated['direct_submission_note'] !== '' ? (string) $validated['direct_submission_note'] : null,
                'intake_started_at' => now(),
                'direct_submission_received_at' => (string) $validated['source_method'] === 'direct_submission' ? now() : null,
                'payment_status' => Application::PAYMENT_STATUS_PENDING,
                'status' => (string) $validated['source_method'] === 'direct_submission'
                    ? Application::STATUS_INTAKE_IN_PROGRESS
                    : Application::STATUS_INTERVIEW_IN_PROGRESS,
            ])->save();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'application_id' => $application->id,
                    'package_tier' => $application->package_tier,
                    'source_method' => $application->source_method,
                    'redirect_to' => $this->redirectUrlFor($application),
                ], 201);
            }

            $message = $application->source_method === 'direct_submission'
                ? 'Application started. Upload the documents for this profile.'
                : 'Application started. Continue to the Online Interview. Payment comes after the questionnaire is submitted.';

            return redirect($this->redirectUrlFor($application))
                ->with('application_created', $message);
        });
    }

    private function redirectUrlFor(Application $application): string
    {
        if ($application->source_method === 'direct_submission') {
            return route('applications.upload.show', ['application' => $application->id]);
        }

        if ($application->source_method === 'online_interview' && ! $application->isInterviewSubmitted()) {
            return route('online-interview.show', ['application' => $application->id]);
        }

        return route('applications.payment', ['application' => $application->id]);
    }

    /**
     * Online Interview customers reach payment only after the questionnaire
     * is submitted. Opening the form and saving the application do not
     * create a payment.
     */
    private function paymentIsPremature(Application $application): bool
    {
        if (ApplicationPaymentStateService::testingModeActive()) {
            return false;
        }

        return $application->source_method === 'online_interview'
            && ! $application->isInterviewSubmitted()
            && ! app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application)
            && $application->status === Application::STATUS_INTERVIEW_IN_PROGRESS;
    }

    /**
     * @return array{ok: bool, slug: ?string, error?: string}
     */
    private function preferredSlugHint(Request $request, string $fullName): array
    {
        $raw = $request->input('preferred_slug');
        if (! is_string($raw) || trim($raw) === '') {
            return ['ok' => true, 'slug' => null];
        }

        $candidate = preg_replace('/\s+/', '.', trim($raw)) ?? '';
        $urls = app(ProfileUrlService::class);
        $result = $urls->validatePersonalSlugCandidate($candidate, null, $fullName);
        if (($result['ok'] ?? false) === true) {
            return ['ok' => true, 'slug' => $result['slug']];
        }
        if (($result['error'] ?? '') === 'That URL is not available.') {
            return ['ok' => true, 'slug' => $urls->normalizePersonalSlug($candidate)];
        }

        $error = (string) ($result['error'] ?? 'Use a name-based address such as sree or arun.kumar.');
        if ($this->flowLanguage($request) === 'ml') {
            $error = 'ഈ വിലാസം പ്രൊഫൈലിലെ പേരിൽ നിന്നുള്ളതായിരിക്കണം. ഉദാഹരണം: sree അല്ലെങ്കിൽ arun.kumar. വലിയ അക്ഷരം, അടിവര, അല്ലെങ്കിൽ മറ്റൊരു പേര് സ്വീകരിക്കില്ല. ശൂന്യമായി വിട്ടാലും മതി.';
        }

        return ['ok' => false, 'slug' => null, 'error' => $error];
    }

    private function materialsOpen(Application $application): bool
    {
        if ($application->source_method === 'direct_submission') {
            return true;
        }

        if ($application->source_method === 'online_interview' && ! $application->isInterviewSubmitted()) {
            return true;
        }

        return app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application);
    }

    private function paymentGateResponse(Request $request, Application $application): JsonResponse|RedirectResponse
    {
        $message = 'Complete payment for this application before continuing to the Online Interview or uploads.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'error' => $message,
                'redirect_to' => route('applications.payment', ['application' => $application->id]),
            ], 403);
        }

        return redirect()
            ->route('applications.payment', ['application' => $application->id])
            ->withErrors(['payment' => $message]);
    }

    public function showOwn(Request $request, Application $application): JsonResponse|RedirectResponse|View
    {
        if (! Auth::check()) {
            return $this->unauthorizedResponse($request, 'Login required.');
        }
        if ((int) $application->user_id !== (int) Auth::id()) {
            return $this->forbiddenResponse($request, 'This application is not yours.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'application' => [
                    'id' => $application->id,
                    'package_tier' => $application->package_tier,
                    'source_method' => $application->source_method,
                    'full_name' => $application->full_name,
                    'preferred_display_name' => $application->preferred_display_name,
                    'intake_started_at' => $application->intake_started_at?->toIso8601String(),
                    'online_interview_completed_at' => $application->online_interview_completed_at?->toIso8601String(),
                    'direct_submission_received_at' => $application->direct_submission_received_at?->toIso8601String(),
                    'has_profile' => $application->profile_id !== null,
                ],
            ]);
        }

        $materials = $application->sourceMaterials()->orderByDesc('uploaded_at')->get();
        $answersMap = $application->source_method === 'online_interview'
            ? (new OnlineInterviewService)->loadAnswersMap($application)
            : [];
        $progress = $application->source_method === 'online_interview'
            ? OnlineInterviewCatalog::progress($application->package_tier, $answersMap)
            : ['answered' => 0, 'total' => 0, 'required_total' => 0, 'required_answered' => 0, 'missing_required' => []];

        $typeMap = (array) config('online_interview.source_material_types', []);

        // Identity area data: reference number comes from the profile itself;
        // the photograph and public URL use the existing approved-media and
        // publication gates, so an unpublished profile never links publicly.
        $profile = $application->profile;
        $publicProfileUrl = null;
        $publicProfilePath = null;
        $identityPhoto = null;
        if ($profile !== null && filled($profile->slug)
            && app(ProfileUrlService::class)->isPubliclyVisible($profile)) {
            $publicProfileUrl = app(ProfileUrlService::class)->canonicalPublicUrl($profile);
            $publicProfilePath = '/'.$profile->slug;
            $identityPhoto = app(PublicProfilePresentationService::class)->publicProfilePhotos($profile)->first();
        }

        return view('application.show', [
            'application' => $application,
            'materials' => $materials,
            'materialsCount' => $materials->count(),
            'progress' => $progress,
            'typeMap' => $typeMap,
            'publicProfileUrl' => $publicProfileUrl,
            'publicProfilePath' => $publicProfilePath,
            'identityPhoto' => $identityPhoto,
            'paymentsTestingMode' => ApplicationPaymentStateService::testingModeActive(),
        ]);
    }

    public function uploadsShow(Request $request, Application $application): JsonResponse|RedirectResponse|View
    {
        if (! Auth::check()) {
            return $this->unauthorizedResponse($request, 'Login required.');
        }
        if ((int) $application->user_id !== (int) Auth::id()) {
            return $this->forbiddenResponse($request, 'This application is not yours.');
        }
        if (! $this->materialsOpen($application)) {
            return $this->paymentGateResponse($request, $application);
        }

        if ($request->expectsJson()) {
            $materialTypes = (array) config('online_interview.source_material_types', []);

            return response()->json([
                'ok' => true,
                'application_id' => $application->id,
                'source_method' => $application->source_method,
                'material_types' => array_keys($materialTypes),
                'materials_count' => $application->sourceMaterials()->count(),
            ]);
        }

        $materialTypes = (array) config('online_interview.source_material_types', []);
        $maxKb = (int) config('online_interview.uploads.max_upload_kb', 10240);
        $materials = $application->sourceMaterials()->orderByDesc('uploaded_at')->get();
        $typeMap = $materialTypes;

        return view('application.upload', [
            'application' => $application,
            'materialTypes' => $materialTypes,
            'maxKb' => $maxKb,
            'materials' => $materials,
            'typeMap' => $typeMap,
        ]);
    }

    public function uploadMaterial(Request $request, Application $application): JsonResponse|RedirectResponse
    {
        if (! Auth::check()) {
            return $this->unauthorizedResponse($request, 'Login required.');
        }
        if ((int) $application->user_id !== (int) Auth::id()) {
            return $this->forbiddenResponse($request, 'This application is not yours.');
        }
        if (! $this->materialsOpen($application)) {
            return $this->paymentGateResponse($request, $application);
        }
        if ($application->isInterviewSubmitted()) {
            return $this->forbiddenResponse($request, 'This application has already been submitted and cannot accept new materials.');
        }

        $maxKb = (int) config('online_interview.uploads.max_upload_kb', 10240);
        $allowedMimes = (array) config('online_interview.uploads.allowed_mime_types', []);
        $materialTypes = array_keys((array) config('online_interview.source_material_types', []));
        $materialTypesStr = implode(',', $materialTypes);

        $validator = Validator::make($request->all(), [
            'material' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:'.implode(',', $allowedMimes)],
            'material_type' => ['required', 'string', 'in:'.$materialTypesStr],
            'honey_bot' => ['nullable', 'string', 'max:0'],
        ]);
        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }
        if ((string) $request->input('honey_bot', '') !== '') {
            return $this->noContentResponse($request);
        }

        $uploaded = $request->file('material');
        if (! $uploaded || ! $uploaded->isValid()) {
            return $this->validationErrorResponse($request, Validator::make([], [])->after(function ($v) {
                $v->errors()->add('material', 'Uploaded file is not valid.');
            }));
        }

        $forbiddenExt = array_map('strtolower', (array) config('online_interview.uploads.forbidden_extensions', []));
        $clientExt = strtolower((string) $uploaded->getClientOriginalExtension());
        if (in_array($clientExt, $forbiddenExt, true)) {
            return $this->forbiddenResponse($request, 'This file type is not allowed.');
        }

        $diskName = (string) config('online_interview.uploads.disk', 'private_uploads');
        $prefix = (string) config('online_interview.uploads.storage_prefix', 'source-materials/applications');
        $storedPath = $uploaded->store($prefix.'/'.$application->id, $diskName);
        if ($storedPath === false) {
            return $this->serverErrorResponse($request, 'Failed to store the uploaded file.');
        }

        $sanitizeOrig = (bool) config('online_interview.uploads.sanitize_original_name', true);
        $originalName = $sanitizeOrig
            ? preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $uploaded->getClientOriginalName())
            : (string) $uploaded->getClientOriginalName();

        $material = SourceMaterial::query()->create([
            'application_id' => $application->id,
            'user_id' => (int) Auth::id(),
            'material_type' => (string) $request->input('material_type'),
            'storage_disk' => $diskName,
            'storage_path' => $storedPath,
            'original_filename' => $originalName,
            'mime_type' => (string) $uploaded->getMimeType(),
            'file_bytes' => (int) $uploaded->getSize(),
            'client_hash_sha256' => hash_file('sha256', $uploaded->getRealPath()) ?: null,
            'uploaded_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'material_id' => $material->id,
                'application_id' => $application->id,
                'material_type' => $material->material_type,
                'bytes' => $material->file_bytes,
            ], 201);
        }

        return redirect()
            ->route('applications.upload.show', ['application' => $application->id])
            ->with('material_uploaded', 'Source material received.');
    }

    public function showPayment(Request $request, Application $application): JsonResponse|RedirectResponse|View
    {
        if (! Auth::check()) {
            return $this->unauthorizedResponse($request, 'Login required.');
        }
        if ((int) $application->user_id !== (int) Auth::id()) {
            return $this->forbiddenResponse($request, 'This application is not yours.');
        }
        if ($this->paymentIsPremature($application)) {
            $message = 'Complete the Online Interview before payment. No payment has been started.';
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'error' => $message,
                    'redirect_to' => route('online-interview.show', ['application' => $application->id]),
                ], 403);
            }

            return redirect()
                ->route('online-interview.show', ['application' => $application->id])
                ->withErrors(['payment' => $message]);
        }

        $payments = Payment::query()
            ->where('application_id', $application->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $activePayment = $payments->first(fn ($p) => $p->isActiveAttempt());
        $settledPayment = $payments->first(fn ($p) => $p->isPaidOrBetter());

        $package = null;
        try {
            if (in_array($application->package_tier, ['emerging', 'accomplished', 'distinguished'], true)) {
                $package = PricingAmounts::forApplication($application);
            }
        } catch (\Throwable $e) {
            $package = null;
        }

        if ($request->expectsJson()) {
            $summary = [
                'application_id' => $application->id,
                'package_tier' => $application->package_tier,
                'payment_settled' => $application->isPaymentSettled(),
                'payment_status' => $application->payment_status,
                'package' => $package,
                'active_payment' => $activePayment ? [
                    'id' => $activePayment->id,
                    'status' => $activePayment->status,
                    'amount' => (string) $activePayment->amount,
                    'currency' => (string) $activePayment->currency,
                    'razorpay_link_url' => null,
                    'created_at' => $activePayment->created_at?->toIso8601String(),
                ] : null,
                'settled_payment' => $settledPayment ? [
                    'id' => $settledPayment->id,
                    'status' => $settledPayment->status,
                    'amount' => (string) $settledPayment->amount,
                    'receipt_reference' => $settledPayment->invoice_number,
                    'tax_invoice_number' => $settledPayment->tax_invoice_number,
                    'credit_note_number' => $settledPayment->credit_note_number,
                    'paid_at' => $settledPayment->paid_at?->toIso8601String(),
                ] : null,
                'payments_count' => $payments->count(),
            ];
            $quoteIsStale = $this->activePaymentQuoteIsStale($activePayment, $package);
            if ($activePayment && ! $quoteIsStale && $request->user() && (int) $activePayment->application?->user_id === (int) Auth::id()) {
                $summary['active_payment']['razorpay_link_url'] = $activePayment->razorpay_link_url;
            }
            $summary['quote_is_stale'] = $quoteIsStale;
            if ($quoteIsStale && is_array($package)) {
                $summary['current_amount'] = $package['amount_incl_formatted'] ?? null;
                $summary['stale_link_notice'] = 'An earlier payment link must not be paid. The current amount is shown above.';
            }

            return response()->json(['ok' => true, 'payment' => $summary]);
        }

        return view('application.payment', [
            'application' => $application,
            'package' => $package,
            'payments' => $payments,
            'activePayment' => $activePayment,
            'settledPayment' => $settledPayment,
            'isSettled' => $settledPayment !== null,
            'quoteIsStale' => $this->activePaymentQuoteIsStale($activePayment, $package),
            'language' => $this->flowLanguage($request),
            'paymentsTestingMode' => ApplicationPaymentStateService::testingModeActive(),
        ]);
    }

    public function initiatePayment(Request $request, Application $application): JsonResponse|RedirectResponse
    {
        if (! Auth::check()) {
            return $this->unauthorizedResponse($request, 'Login required.');
        }
        if ((int) $application->user_id !== (int) Auth::id()) {
            return $this->forbiddenResponse($request, 'This application is not yours.');
        }
        if (! in_array($application->package_tier, ['emerging', 'accomplished', 'distinguished'], true)) {
            return $this->validationErrorResponse($request, Validator::make([], [])->after(function ($v) {
                $v->errors()->add('package_tier', 'Invalid package tier for payment.');
            }));
        }
        if ($this->paymentIsPremature($application)) {
            $message = 'Complete the Online Interview before payment. No payment has been started.';
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $message], 403);
            }

            return redirect()
                ->route('online-interview.show', ['application' => $application->id])
                ->withErrors(['payment' => $message]);
        }
        if ($application->isPaymentSettled()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'already_settled' => true,
                    'message' => 'Payment for this application has already been settled.',
                    'redirect_to' => route('applications.payment', ['application' => $application->id]),
                ], 409);
            }

            return redirect()
                ->route('applications.payment', ['application' => $application->id])
                ->withErrors(['payment' => 'Payment for this application has already been settled.']);
        }

        // Payments testing mode — initiation is refused while testing runs.
        if (ApplicationPaymentStateService::testingModeActive()) {
            $message = $this->flowLanguage($request) === 'ml'
                ? 'ടെസ്റ്റിംഗ് കാലത്ത് ഓൺലൈൻ പേയ്‌മെൻ്റ് താൽക്കാലികമായി നിർത്തിവെച്ചിരിക്കുകയാണ്.'
                : 'Online payments are temporarily unavailable during testing.';
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $message], 503);
            }

            return back()->withErrors(['payment' => $message]);
        }

        try {
            /** @var RazorpayPaymentService $svc */
            $svc = app(RazorpayPaymentService::class);
            $payment = $svc->createApplicationPaymentLink($application);
        } catch (PaymentLinkStateUncertainException $e) {
            $message = $this->flowLanguage($request) === 'ml'
                ? 'മുൻ പേയ്‌മെൻ്റ് ലിങ്ക് സ്ഥിരീകരിക്കുന്നതുവരെ പുതിയ ലിങ്ക് ആരംഭിക്കാനാവില്ല. ആ ലിങ്കിൽ പണമടയ്ക്കരുത്.'
                : 'An earlier payment link is still being confirmed. Do not pay it. A new link cannot be started yet.';
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $message], 409);
            }

            return back()->withErrors(['payment' => $message]);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Payment initiation failed: '.($e->getMessage() ?: 'Unknown error.'),
                ], 502);
            }

            return back()->withErrors(['payment' => 'Payment initiation failed. Please try again in a moment.']);
        }

        if ($payment->isPaidOrBetter()) {
            $message = 'A payment on the earlier link was confirmed and recorded.';
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'already_settled' => true,
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'message' => $message,
                    'redirect_to' => route('applications.payment', ['application' => $application->id]),
                ], 200);
            }

            return redirect()
                ->route('applications.payment', ['application' => $application->id])
                ->with('payment_initiated', $message);
        }

        $redirectTo = $payment->razorpay_link_url
            ? $payment->razorpay_link_url
            : route('applications.payment', ['application' => $application->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'razorpay_link_url' => $payment->razorpay_link_url,
                'razorpay_link_id' => $payment->razorpay_link_id,
                'redirect_to' => $redirectTo,
            ], 201);
        }

        if ($payment->razorpay_link_url !== null && $payment->razorpay_link_url !== '') {
            return redirect()->away($payment->razorpay_link_url);
        }

        return redirect()
            ->route('applications.payment', ['application' => $application->id])
            ->with('payment_initiated', 'Payment has been initiated. Please complete the transaction.');
    }

    private function unauthorizedResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'error' => $message], 401);
        }

        return redirect()->route('login')->withErrors(['auth' => $message]);
    }

    /**
     * @param  array<string, mixed>|null  $package
     */
    private function activePaymentQuoteIsStale(?Payment $activePayment, ?array $package): bool
    {
        if (! $activePayment instanceof Payment || ! is_array($package) || ! isset($package['amount_incl_paise'])) {
            return false;
        }

        return $activePayment->totalPaise() !== (int) $package['amount_incl_paise'];
    }

    /**
     * A2 — an existing active application wins: the customer is returned to
     * it instead of being allowed to create a dead-end second application.
     */
    private function redirectHomeWithExisting(Request $request, Application $application): JsonResponse|RedirectResponse
    {
        $message = 'You already have an active Jannayaks application. Continuing with it.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'error' => $message,
                'application_id' => $application->id,
            ], 409);
        }

        return redirect()->route('applications.show', $application)->with('info', $message);
    }

    private function forbiddenResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'error' => $message], 403);
        }

        return redirect()->route('home')->withErrors(['application' => $message]);
    }

    private function serverErrorResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'error' => $message], 500);
        }

        return back()->withErrors(['application' => $message]);
    }

    private function validationErrorResponse(Request $request, ValidatorContract $validator): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'error' => 'Validation failed.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        return back()->withErrors($validator)->withInput();
    }

    private function noContentResponse(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true], 204);
        }

        return redirect()->route('home')->with('ok', 'Saved.');
    }
}
