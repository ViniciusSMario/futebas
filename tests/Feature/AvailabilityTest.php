<?php

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_availability(): void
    {
        $response = $this->get('/availability');

        $response->assertRedirect('/login');
    }

    public function test_availability_edit_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/availability');

        $response->assertOk();
    }

    public function test_availability_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/availability', [
            'days' => [1, 3, 6],
            'start_time' => '18:00',
            'end_time' => '20:00',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/availability');

        $this->assertSame(3, $user->availabilities()->count());
        $this->assertSame([1, 3, 6], $user->availabilities()->orderBy('day_of_week')->pluck('day_of_week')->all());

        $availability = $user->availabilities()->first();
        $this->assertSame('18:00', $availability->start_time->format('H:i'));
        $this->assertSame('20:00', $availability->end_time->format('H:i'));
    }

    public function test_availability_can_be_updated_and_replaces_previous_days(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/availability', [
            'days' => [1, 2],
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response = $this->actingAs($user)->put('/availability', [
            'days' => [0, 6],
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertSame(2, Availability::count());
        $this->assertSame([0, 6], $user->availabilities()->orderBy('day_of_week')->pluck('day_of_week')->all());
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/availability', [
            'days' => [1],
            'start_time' => '20:00',
            'end_time' => '18:00',
        ]);

        $response->assertSessionHasErrors('end_time');
        $this->assertSame(0, Availability::count());
    }

    public function test_at_least_one_day_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put('/availability', [
            'days' => [],
            'start_time' => '18:00',
            'end_time' => '20:00',
        ]);

        $response->assertSessionHasErrors('days');
    }
}
