<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\ApplicationPaymentStateService;
use App\Services\ApplicationWorkflowService;
use App\Services\OnlineInterviewService;
use App\Support\OnlineInterviewCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class OnlineInterviewController extends Controller
{
    public function __construct(
        private readonly OnlineInterviewService $interview,
    ) {}

    public function show(Request $request, Application $application): JsonResponse|RedirectResponse|View
    {
        if (! Auth::check()) {
            return $this->unauth($request, 'Login required.');
        }
        if ((int) $application->user_id !== (int) Auth::id()) {
            return $this->forbid($request, 'This interview is not yours.');
        }
        if (! app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application)) {
            return $this->paymentGate($request, $application);
        }
        if (! in_array((string) $application->source_method, (array) config('online_interview.submission.valid_source_methods_for_interview', ['online_interview']), true)) {
            return $this->forbid($request, 'This application is not an Online Interview intake.');
        }
        if ($application->isInterviewSubmitted()) {
            $readOnly = true;
        } else {
            $readOnly = false;
        }

        $questions = OnlineInterviewCatalog::questionsForTier($application->package_tier);
        $answers = $this->interview->loadAnswersMap($application);
        $progress = OnlineInterviewCatalog::progress($application->package_tier, $answers);

        $sections = [];
        foreach ($questions as $q) {
            $sections[(string) ($q['section'] ?? '')][] = $q;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'application_id' => $application->id,
                'package_tier' => $application->package_tier,
                'read_only' => $readOnly,
                'submitted_at' => $application->online_interview_completed_at?->toIso8601String(),
                'sections' => $sections,
                'allowed_qids' => OnlineInterviewCatalog::idsForTier($application->package_tier),
                'answers' => $answers,
                'progress' => $progress,
            ]);
        }

        return view('interview.show', [
            'application' => $application,
            'sections' => $sections,
            'answers' => $answers,
            'progress' => $progress,
            'readOnly' => $readOnly,
            'submittedAt' => $application->online_interview_completed_at,
            'package_tier' => $application->package_tier,
            'allowed_qids' => OnlineInterviewCatalog::idsForTier($application->package_tier),
        ]);
    }

    public function save(Request $request, Application $application): JsonResponse|RedirectResponse
    {
        if (! Auth::check()) {
            return $this->unauth($request, 'Login required.');
        }
        if ((int) $application->user_id !== (int) Auth::id()) {
            return $this->forbid($request, 'This interview is not yours.');
        }
        if (! app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application)) {
            return $this->paymentGate($request, $application);
        }
        if ($application->isInterviewSubmitted()) {
            return $this->forbid($request, 'This interview has already been submitted.');
        }
        if (! in_array((string) $application->source_method, (array) config('online_interview.submission.valid_source_methods_for_interview', ['online_interview']), true)) {
            return $this->forbid($request, 'This application is not an Online Interview intake.');
        }

        $answers = $request->input('answers', []);
        $singletonQid = $request->input('question_id');
        $singletonVal = $request->input('answer');
        if (! is_array($answers) && $singletonQid === null) {
            return $this->validationFail($request, ['answers' => ['Provide answers or a single question_id/answer pair.']]);
        }

        $toSave = [];
        if ($singletonQid !== null) {
            $qid = (string) $singletonQid;
            if (! preg_match('/^[A-Za-z0-9_.-]+$/', $qid)) {
                return $this->validationFail($request, ['question_id' => ['Invalid question identifier.']]);
            }
            $toSave[$qid] = is_scalar($singletonVal) ? (string) $singletonVal : '';
        } else {
            foreach ($answers as $qid => $value) {
                $qid = (string) $qid;
                if (! preg_match('/^[A-Za-z0-9_.-]+$/', $qid)) {
                    continue;
                }
                $toSave[$qid] = is_scalar($value) ? (string) $value : '';
            }
        }

        $summary = $this->interview->saveAnswers($application, $toSave);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'saved' => $summary['saved'] ?? 0,
                'answered' => $summary['answered'] ?? 0,
                'total' => $summary['total'] ?? 0,
                'required_answered' => $summary['required_answered'] ?? 0,
                'required_total' => $summary['required_total'] ?? 0,
                'saved_at' => now()->toIso8601String(),
            ]);
        }

        return redirect()->route('online-interview.show', ['application' => $application->id])
            ->with('saved', 'Interview answers saved.');
    }

    public function submit(Request $request, Application $application): JsonResponse|RedirectResponse
    {
        if (! Auth::check()) {
            return $this->unauth($request, 'Login required.');
        }
        if ((int) $application->user_id !== (int) Auth::id()) {
            return $this->forbid($request, 'This interview is not yours.');
        }
        if (! app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application)) {
            return $this->paymentGate($request, $application);
        }
        if ($application->isInterviewSubmitted()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'already_submitted' => true,
                    'submitted_at' => $application->online_interview_completed_at?->toIso8601String(),
                ]);
            }

            return redirect()->route('online-interview.show', ['application' => $application->id])
                ->with('already_submitted', 'Your interview is already submitted.');
        }
        if (! in_array((string) $application->source_method, (array) config('online_interview.submission.valid_source_methods_for_interview', ['online_interview']), true)) {
            return $this->forbid($request, 'This application is not an Online Interview intake.');
        }

        $rateKey = 'ol-submit:user:'.(int) Auth::id();
        if (! RateLimiter::attempt($rateKey, 1, fn () => true, (int) config('online_interview.submission.prevent_duplicate_within_seconds', 30))) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Please wait before submitting again.',
                    'retry_in_seconds' => (int) config('online_interview.submission.prevent_duplicate_within_seconds', 30),
                ], 429);
            }

            return back()->withErrors(['submit' => 'Please wait before submitting again.']);
        }

        $answers = $this->interview->loadAnswersMap($application);
        $progress = OnlineInterviewCatalog::progress($application->package_tier, $answers);
        if ($progress['missing_required'] !== []) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Please complete all required questions before submitting.',
                    'missing_required' => $progress['missing_required'],
                    'required_answered' => $progress['required_answered'],
                    'required_total' => $progress['required_total'],
                ], 422);
            }

            return back()
                ->withErrors([
                    'submit' => 'Please complete all required questions before submitting. Missing: '.count($progress['missing_required']).'.',
                ]);
        }

        app(ApplicationWorkflowService::class)->markInterviewSubmitted($application, $request->user());
        $application->refresh();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'submitted_at' => $application->online_interview_completed_at->toIso8601String(),
                'application_id' => $application->id,
                'next_step' => 'editorial_processing',
                'status' => $application->status,
            ]);
        }

        return redirect()->route('online-interview.show', ['application' => $application->id])
            ->with('submitted', 'Online Interview submitted for editorial processing.');
    }

    private function unauth(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'error' => $message], 401);
        }

        return redirect()->route('login')->withErrors(['auth' => $message]);
    }

    private function forbid(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'error' => $message], 403);
        }

        return redirect()->route('home')->withErrors(['interview' => $message]);
    }

    private function paymentGate(Request $request, Application $application): JsonResponse|RedirectResponse
    {
        $message = 'Complete payment for this application before continuing the Online Interview.';

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

    /**
     * @param  array<string,list<string>>  $bag
     */
    private function validationFail(Request $request, array $bag): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => false, 'error' => 'Validation failed.', 'errors' => $bag], 422);
        }

        return back()->withErrors($bag)->withInput();
    }
}
