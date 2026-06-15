<?php

namespace App\Http\Controllers;

use App\Models\PsyTest;
use App\Services\PsyTestService;
use Illuminate\Http\Request;

class PublicPsyTestController extends Controller
{
    public function __construct(
        private readonly PsyTestService $psyTestService
    ) {}

    public function show(string $token)
    {
        $psyTest = PsyTest::where('token', $token)->firstOrFail();

        if ($psyTest->isCompleted()) {
            return view('public.psy-tests.completed', compact('psyTest'));
        }

        if ($psyTest->isExpired()) {
            $psyTest->update(['status' => PsyTest::STATUS_EXPIRED]);
            abort(403, 'Ce test a expiré.');
        }

        $profileData = $this->psyTestService->loadQuestions($psyTest->profile);
        $questions = collect($profileData['questions'] ?? [])->shuffle()->all();

        return view('public.psy-tests.show', compact('psyTest', 'questions'));
    }

    public function submit(Request $request, string $token)
    {
        $psyTest = PsyTest::where('token', $token)->firstOrFail();

        if ($psyTest->isCompleted() || $psyTest->isExpired()) {
            abort(403, 'Ce test n\'est plus disponible.');
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'integer'],
        ]);

        $evaluation = $this->psyTestService->evaluate($psyTest, $validated['answers']);

        $psyTest->update([
            'status' => PsyTest::STATUS_COMPLETED,
            'completed_at' => now(),
            'score' => $evaluation['score'],
            'answers_json' => $validated['answers'],
            'dimension_scores_json' => $evaluation,
        ]);

        return response()->json([
            'status' => 'success',
            'redirect' => route('public.psy-test.show', ['token' => $token]),
        ]);
    }
}
