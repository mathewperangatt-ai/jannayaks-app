<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Support\OnlineInterviewCatalog;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    private const LIVING_TIERS = ['emerging', 'accomplished', 'distinguished'];

    public function create(Request $request): \Illuminate\View\View|JsonResponse
    {
        $tiers = self::LIVING_TIERS;
        $sourceMethods = ['online_interview', 'direct_submission'];
        $tierLabels = [
            'emerging'      => 'Emerging Leader',
            'accomplished'  => 'Accomplished Leader',
            'distinguished' => 'Distinguished Leader',
        ];

        $auth = Auth::check();
        $showTiers = $auth || $request->boolean('tiers') || $request->query('step') === 'tiers';

        if (! $auth && ! $showTiers) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok'             => true,
                    'login_required' => true,
                    'intro'          => true,
                    'next'           => route('apply', ['step' => 'tiers']),
                ]);
            }

            return view('application.intro', [
                'login_required' => true,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok'             => true,
                'login_required' => ! $auth,
                'tiers'          => $tiers,
                'source_methods' => $sourceMethods,
                'tier_labels'    => $tierLabels,
            ]);
        }

        return view('application.tier-select', [
            'tiers'          => $tiers,
            'source_methods' => $sourceMethods,
            'tier_labels'    => $tierLabels,
            'guest'          => ! $auth,
        ]);
    }

    /**
     * Guest (or authenticated) stores tier intent, then registers/logs in before payment.
     */
    public function storeIntent(Request $request): JsonResponse|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'package_tier'     => ['required', 'string', 'in:emerging,accomplished,distinguished'],
            'source_method'    => ['required', 'string', 'in:online_interview,direct_submission'],
            'full_name'        => ['required', 'string', 'min:2', 'max:255'],
            'preferred_slug'   => ['nullable', 'string', 'max:128', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/'],
            'contact_email'    => ['required_without:contact_mobile', 'nullable', 'email:strict', 'max:255'],
            'contact_mobile'   => ['required_without:contact_email', 'nullable', 'string', 'max:32'],
            'distinguished_interview_addon' => ['nullable', 'boolean'],
            'direct_submission_note' => ['nullable', 'string', 'max:500'],
            'honey_bot'        => ['nullable', 'string', 'max:0'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }
        if ((string) $request->input('honey_bot', '') !== '') {
            return $this->noContentResponse($request);
        }

        $validated = $validator->safe()->all();
        $intent = [
            'package_tier' => (string) $validated['package_tier'],
            'source_method' => (string) $validated['source_method'],
            'full_name' => (string) $validated['full_name'],
            'preferred_slug' => isset($validated['preferred_slug']) && (string) $validated['preferred_slug'] !== '' ? (string) $validated['preferred_slug'] : null,
            'contact_email' => isset($validated['contact_email']) && $validated['contact_email'] !== '' ? (string) $validated['contact_email'] : null,
            'contact_mobile' => isset($validated['contact_mobile']) && $validated['contact_mobile'] !== '' ? (string) $validated['contact_mobile'] : null,
            'distinguished_interview_addon' => (bool) ($validated['distinguished_interview_addon'] ?? false),
            'direct_submission_note' => isset($validated['direct_submission_note']) && $validated['direct_submission_note'] !== '' ? (string) $validated['direct_submission_note'] : null,
        ];

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

        return redirect()->route('login', ['return' => route('apply.continue')])
            ->with('status', 'Sign in to continue — payment comes next, then the Online Interview.');
    }

    public function continueFromIntent(Request $request): JsonResponse|RedirectResponse
    {
        if (! Auth::check()) {
            return $this->unauthorizedResponse($request, 'You must sign in before continuing.');
        }

        $intent = $request->session()->get('apply.intent');
        if (! is_array($intent)) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => 'No application intent found. Choose a tier first.'], 422);
            }

            return redirect()->route('apply', ['step' => 'tiers'])
                ->withErrors(['apply' => 'Choose a profile tier to continue.']);
        }

        $request->session()->forget('apply.intent');

        $merged = array_merge($intent, [
            'package_tier' => $intent['package_tier'] ?? 'emerging',
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

        $validator = Validator::make($request->all(), [
            'package_tier'     => ['required', 'string', 'in:emerging,accomplished,distinguished'],
            'source_method'    => ['required', 'string', 'in:online_interview,direct_submission'],
            'full_name'        => ['required', 'string', 'min:2', 'max:255'],
            'preferred_slug'   => ['nullable', 'string', 'max:128', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/'],
            'contact_email'    => ['required_without:contact_mobile', 'nullable', 'email:strict', 'max:255'],
            'contact_mobile'   => ['required_without:contact_email', 'nullable', 'string', 'max:32'],
            'distinguished_interview_addon' => ['nullable', 'boolean'],
            'direct_submission_note' => ['nullable', 'string', 'max:500'],
            'honey_bot'        => ['nullable', 'string', 'max:0'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($request, $validator);
        }

        if ((string) $request->input('honey_bot', '') !== '') {
            return $this->noContentResponse($request);
        }

        $validated = $validator->safe()->all();

        return DB::transaction(function () use ($request, $validated) {
            $user = Auth::user();

            $application = new Application;
            $application->forceFill([
                'user_id'                        => $user->id,
                'source_method'                  => (string) $validated['source_method'],
                'package_tier'                   => (string) $validated['package_tier'],
                'full_name'                      => (string) $validated['full_name'],
                'preferred_display_name'         => (string) $validated['full_name'],
                'preferred_slug'                 => isset($validated['preferred_slug']) && (string) $validated['preferred_slug'] !== '' ? (string) $validated['preferred_slug'] : null,
                'distinguished_interview_addon'  => (bool) ($validated['distinguished_interview_addon'] ?? false),
                'preferred_contact_email'        => isset($validated['contact_email']) && $validated['contact_email'] !== '' ? (string) $validated['contact_email'] : null,
                'preferred_contact_mobile'       => isset($validated['contact_mobile']) && $validated['contact_mobile'] !== '' ? (string) $validated['contact_mobile'] : null,
                'direct_submission_note'         => isset($validated['direct_submission_note']) && $validated['direct_submission_note'] !== '' ? (string) $validated['direct_submission_note'] : null,
                'intake_started_at'              => now(),
                'direct_submission_received_at'  => (string) $validated['source_method'] === 'direct_submission' ? now() : null,
                'payment_status'                 => Application::PAYMENT_STATUS_PENDING,
                'status'                         => Application::STATUS_PAYMENT_PENDING,
            ])->save();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok'              => true,
                    'application_id'  => $application->id,
                    'package_tier'    => $application->package_tier,
                    'source_method'   => $application->source_method,
                    'redirect_to'     => $this->redirectUrlFor($application),
                ], 201);
            }

            return redirect($this->redirectUrlFor($application))
                ->with('application_created', 'Application started. Complete payment to unlock the Online Interview or source-material uploads.');
        });
    }

    private function redirectUrlFor(Application $application): string
    {
        return route('applications.payment', ['application' => $application->id]);
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

    public function showOwn(Request $request, Application $application): JsonResponse|RedirectResponse|\Illuminate\View\View
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
                    'id'                            => $application->id,
                    'package_tier'                  => $application->package_tier,
                    'source_method'                 => $application->source_method,
                    'full_name'                     => $application->full_name,
                    'preferred_display_name'        => $application->preferred_display_name,
                    'intake_started_at'             => $application->intake_started_at?->toIso8601String(),
                    'online_interview_completed_at' => $application->online_interview_completed_at?->toIso8601String(),
                    'direct_submission_received_at' => $application->direct_submission_received_at?->toIso8601String(),
                    'has_profile'                   => $application->profile_id !== null,
                ],
            ]);
        }

        $materials = $application->sourceMaterials()->orderByDesc('uploaded_at')->get();
        $answersMap = $application->source_method === 'online_interview'
            ? (new \App\Services\OnlineInterviewService())->loadAnswersMap($application)
            : [];
        $progress = $application->source_method === 'online_interview'
            ? OnlineInterviewCatalog::progress($application->package_tier, $answersMap)
            : ['answered'=>0,'total'=>0,'required_total'=>0,'required_answered'=>0,'missing_required'=>[]];

        $typeMap = (array) config('online_interview.source_material_types', []);

        return view('application.show', [
            'application'   => $application,
            'materials'     => $materials,
            'materialsCount'=> $materials->count(),
            'progress'      => $progress,
            'typeMap'       => $typeMap,
        ]);
    }

    public function uploadsShow(Request $request, Application $application): JsonResponse|RedirectResponse|\Illuminate\View\View
    {
        if (! Auth::check()) {
            return $this->unauthorizedResponse($request, 'Login required.');
        }
        if ((int) $application->user_id !== (int) Auth::id()) {
            return $this->forbiddenResponse($request, 'This application is not yours.');
        }
        if (! app(\App\Services\ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application)) {
            return $this->paymentGateResponse($request, $application);
        }

        if ($request->expectsJson()) {
            $materialTypes = (array) config('online_interview.source_material_types', []);
            return response()->json([
                'ok' => true,
                'application_id' => $application->id,
                'source_method'  => $application->source_method,
                'material_types' => array_keys($materialTypes),
                'materials_count' => $application->sourceMaterials()->count(),
            ]);
        }

        $materialTypes = (array) config('online_interview.source_material_types', []);
        $maxKb = (int) config('online_interview.uploads.max_upload_kb', 10240);
        $materials = $application->sourceMaterials()->orderByDesc('uploaded_at')->get();
        $typeMap = $materialTypes;

        return view('application.upload', [
            'application'   => $application,
            'materialTypes' => $materialTypes,
            'maxKb'         => $maxKb,
            'materials'     => $materials,
            'typeMap'       => $typeMap,
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
        if (! app(\App\Services\ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application)) {
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
            'material'       => ['required', 'file', 'max:'.$maxKb, 'mimetypes:'.implode(',', $allowedMimes)],
            'material_type'  => ['required', 'string', 'in:'.$materialTypesStr],
            'honey_bot'      => ['nullable', 'string', 'max:0'],
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
        $prefix   = (string) config('online_interview.uploads.storage_prefix', 'source-materials/applications');
        $storedPath = $uploaded->store($prefix.'/'.$application->id, $diskName);
        if ($storedPath === false) {
            return $this->serverErrorResponse($request, 'Failed to store the uploaded file.');
        }

        $sanitizeOrig = (bool) config('online_interview.uploads.sanitize_original_name', true);
        $originalName = $sanitizeOrig
            ? preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $uploaded->getClientOriginalName())
            : (string) $uploaded->getClientOriginalName();

        $material = \App\Models\SourceMaterial::query()->create([
            'application_id'     => $application->id,
            'user_id'            => (int) Auth::id(),
            'material_type'      => (string) $request->input('material_type'),
            'storage_disk'       => $diskName,
            'storage_path'       => $storedPath,
            'original_filename'  => $originalName,
            'mime_type'          => (string) $uploaded->getMimeType(),
            'file_bytes'         => (int) $uploaded->getSize(),
            'client_hash_sha256' => hash_file('sha256', $uploaded->getRealPath()) ?: null,
            'uploaded_at'        => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'             => true,
                'material_id'    => $material->id,
                'application_id' => $application->id,
                'material_type'  => $material->material_type,
                'bytes'          => $material->file_bytes,
            ], 201);
        }

        return redirect()
            ->route('applications.upload.show', ['application' => $application->id])
            ->with('material_uploaded', 'Source material received.');
    }

    public function showPayment(Request $request, Application $application): JsonResponse|RedirectResponse|\Illuminate\View\View
    {
        if (! Auth::check()) {
            return $this->unauthorizedResponse($request, 'Login required.');
        }
        if ((int) $application->user_id !== (int) Auth::id()) {
            return $this->forbiddenResponse($request, 'This application is not yours.');
        }

        $payments = \App\Models\Payment::query()
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
                $package = \App\Support\PricingAmounts::forTier($application->package_tier);
            }
        } catch (\Throwable $e) {
            $package = null;
        }

        if ($request->expectsJson()) {
            $summary = [
                'application_id'    => $application->id,
                'package_tier'      => $application->package_tier,
                'payment_settled'   => $application->isPaymentSettled(),
                'payment_status'    => $application->payment_status,
                'package'           => $package,
                'active_payment'    => $activePayment ? [
                    'id'                => $activePayment->id,
                    'status'            => $activePayment->status,
                    'amount'            => (string) $activePayment->amount,
                    'currency'          => (string) $activePayment->currency,
                    'razorpay_link_url' => null,
                    'created_at'        => $activePayment->created_at?->toIso8601String(),
                ] : null,
                'settled_payment'   => $settledPayment ? [
                    'id'                => $settledPayment->id,
                    'status'            => $settledPayment->status,
                    'amount'            => (string) $settledPayment->amount,
                    'receipt_reference' => $settledPayment->invoice_number,
                    'paid_at'           => $settledPayment->paid_at?->toIso8601String(),
                ] : null,
                'payments_count'    => $payments->count(),
            ];
            if ($activePayment && $request->user() && (int) $activePayment->application?->user_id === (int) Auth::id()) {
                $summary['active_payment']['razorpay_link_url'] = $activePayment->razorpay_link_url;
            }

            return response()->json(['ok' => true, 'payment' => $summary]);
        }

        return view('application.payment', [
            'application'    => $application,
            'package'        => $package,
            'payments'       => $payments,
            'activePayment'  => $activePayment,
            'settledPayment' => $settledPayment,
            'isSettled'      => $settledPayment !== null,
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
        if ($application->isPaymentSettled()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok'                 => true,
                    'already_settled'    => true,
                    'message'            => 'Payment for this application has already been settled.',
                    'redirect_to'        => route('applications.payment', ['application' => $application->id]),
                ], 409);
            }

            return redirect()
                ->route('applications.payment', ['application' => $application->id])
                ->withErrors(['payment' => 'Payment for this application has already been settled.']);
        }

        try {
            /** @var \App\Services\RazorpayPaymentService $svc */
            $svc = app(\App\Services\RazorpayPaymentService::class);
            $payment = $svc->createApplicationPaymentLink($application);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Payment initiation failed: '.($e->getMessage() ?: 'Unknown error.'),
                ], 502);
            }

            return back()->withErrors(['payment' => 'Payment initiation failed. Please try again in a moment.']);
        }

        $redirectTo = $payment->razorpay_link_url
            ? $payment->razorpay_link_url
            : route('applications.payment', ['application' => $application->id]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'                 => true,
                'payment_id'         => $payment->id,
                'status'             => $payment->status,
                'razorpay_link_url'  => $payment->razorpay_link_url,
                'razorpay_link_id'   => $payment->razorpay_link_id,
                'redirect_to'        => $redirectTo,
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
        $loginRoute = app('router')->has('filament.admin.auth.login') ? 'filament.admin.auth.login' : 'home';
        return redirect()->route($loginRoute)->withErrors(['auth' => $message]);
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
                'ok'     => false,
                'error'  => 'Validation failed.',
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
