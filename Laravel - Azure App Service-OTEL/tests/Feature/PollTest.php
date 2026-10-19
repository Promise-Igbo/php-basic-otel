<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PollTest extends TestCase
{
    public function test_poll_page_starts_clean(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Which language would you choose for your next project?')
            ->assertSee('Session only · clears on refresh');
    }

    public function test_multiple_votes_accumulate_in_the_session_until_cleared(): void
    {
        $this->from('/')
            ->post('/', ['option' => 'python'])
            ->assertRedirect(route('poll.index'));

        $this->get('/')
            ->assertOk()
            ->assertSee('YOUR PICK')
            ->assertSee('<strong>1</strong> temporary pick', false)
            ->assertSee('Python');

        $this->post('/', ['option' => 'php'])
            ->assertRedirect(route('poll.index'));

        $this->get('/')
            ->assertOk()
            ->assertSee('YOUR PICK')
            ->assertSee('<strong>2</strong> temporary picks', false)
            ->assertSee('1 <span>pick</span>', false);

        $this->post('/session/clear')
            ->assertRedirect(route('poll.index'));

        $this->get('/')
            ->assertOk()
            ->assertSee('/ THE QUESTION')
            ->assertDontSee('YOUR PICK')
            ->assertSee('<span>Your session picks</span><strong>0</strong>', false);
    }

    public function test_invalid_choices_are_rejected_and_the_error_clears_after_refresh(): void
    {
        $this->from('/')
            ->post('/', ['option' => 'not-an-option'])
            ->assertRedirect('/');

        $this->get('/')
            ->assertOk()
            ->assertSee('Choose one of the options shown in the poll.');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Choose one of the options shown in the poll.');
    }

    public function test_poll_route_uses_laravels_web_session_middleware(): void
    {
        $route = Route::getRoutes()->getByName('poll.index');
        $middleware = $route->gatherMiddleware();
        $middlewareNames = implode(' ', $middleware);

        $this->assertStringContainsString('web', $middlewareNames);
    }
}
