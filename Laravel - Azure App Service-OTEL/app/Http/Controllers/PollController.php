<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PollController extends Controller
{
    /**
     * The poll and its choices are deliberately kept in code, not in storage.
     * Every request starts from a clean set of results.
     *
     * @return array<string, mixed>
     */
    private function poll(): array
    {
        return [
            'question' => 'Which language would you choose for your next project?',
            'description' => 'Pick the tool you love building with. Your choice stays in your session until you refresh.',
            'options' => [
                'php' => [
                    'label' => 'PHP',
                    'detail' => 'Powering the web, one request at a time.',
                    'tag' => 'Great for the web',
                    'class' => 'php',
                ],
                'javascript' => [
                    'label' => 'JavaScript',
                    'detail' => 'From interactive pages to full-stack apps.',
                    'tag' => 'Runs everywhere',
                    'class' => 'javascript',
                ],
                'python' => [
                    'label' => 'Python',
                    'detail' => 'A friendly start with plenty of room to grow.',
                    'tag' => 'Easy to explore',
                    'class' => 'python',
                ],
                'java' => [
                    'label' => 'Java',
                    'detail' => 'Reliable, versatile, and built to scale.',
                    'tag' => 'Made to scale',
                    'class' => 'java',
                ],
            ],
        ];
    }

    public function index(Request $request): View
    {
        $poll = $this->poll();
        $votes = $request->session()->get('poll_votes', []);
        $votes = is_array($votes) ? $votes : [];

        foreach (array_keys($poll['options']) as $option) {
            $votes[$option] = max(0, (int) ($votes[$option] ?? 0));
        }

        $totalVotes = array_sum($votes);
        $lastVote = $request->session()->get('poll_last_vote');
        $selected = is_string($lastVote) && array_key_exists($lastVote, $poll['options'])
            ? $lastVote
            : null;
        $showResults = $totalVotes > 0;

        return view('poll.index', $this->viewData(
            votes: $votes,
            totalVotes: $totalVotes,
            selected: $showResults ? $selected : old('option'),
            showResults: $showResults,
        ));
    }

    public function vote(Request $request): RedirectResponse
    {
        $poll = $this->poll();
        $validated = $request->validate([
            'option' => ['required', 'string', 'in:'.implode(',', array_keys($poll['options']))],
        ], [
            'option.required' => 'Choose one of the options before submitting your vote.',
            'option.in' => 'Choose one of the options shown in the poll.',
        ]);

        $votes = $request->session()->get('poll_votes', []);
        $votes = is_array($votes) ? $votes : [];

        foreach (array_keys($poll['options']) as $option) {
            $votes[$option] = max(0, (int) ($votes[$option] ?? 0));
        }

        $votes[$validated['option']]++;
        $request->session()->put('poll_votes', $votes);
        $request->session()->put('poll_last_vote', $validated['option']);

        return redirect()->route('poll.index');
    }

    public function clearSession(Request $request): RedirectResponse
    {
        $request->session()->forget(['poll_votes', 'poll_last_vote']);

        return redirect()->route('poll.index');
    }

    /**
     * Build clean view data for each individual request.
     *
     * @return array<string, mixed>
     */
    private function viewData(
        array $votes,
        int $totalVotes,
        ?string $selected = null,
        bool $showResults = false,
    ): array {
        $poll = $this->poll();

        return [
            'poll' => $poll,
            'votes' => $votes,
            'totalVotes' => $totalVotes,
            'selected' => $selected,
            'showResults' => $showResults,
        ];
    }
}
