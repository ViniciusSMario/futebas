<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\SosRequest;
use App\Models\User;
use App\Notifications\SosRequestPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The in-app inbox: the reliable half of the notification story, since push
 * delivery can always fail.
 */
class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    private function notifiedPlayer(): User
    {
        $organizer = User::factory()->organizer()->create(['city' => 'Teresina', 'state' => 'PI']);
        $player = User::factory()->create();

        $game = Game::create([
            'user_id' => $organizer->id,
            'team_name' => 'Pelada da quinta',
            'location' => 'Arena Society Central',
            'city' => 'Teresina',
            'modality' => 'Society',
            'date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '19:00',
            'max_players' => 10,
            'price' => '20.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
        ]);

        $sosRequest = SosRequest::create([
            'game_id' => $game->id,
            'organizer_id' => $organizer->id,
            'position' => 'Goleiro',
            'offered_value' => '60.00',
            'status' => SosRequest::STATUS_OPEN,
        ]);

        $player->notify(new SosRequestPublished($sosRequest));

        return $player;
    }

    public function test_guests_cannot_see_the_inbox(): void
    {
        $this->get('/notificacoes')->assertRedirect('/login');
    }

    public function test_a_push_notification_is_also_stored_in_the_inbox(): void
    {
        $player = $this->notifiedPlayer();

        $this->assertSame(1, $player->notifications()->count());

        $response = $this->actingAs($player)->get('/notificacoes');

        $response->assertOk();
        $response->assertSee('Precisa-se de Goleiro!');
        $response->assertSee('Arena Society Central');
    }

    public function test_opening_a_notification_marks_it_read_and_follows_its_link(): void
    {
        $player = $this->notifiedPlayer();
        $notification = $player->notifications()->sole();

        $response = $this->actingAs($player)->get(route('notifications.show', $notification->id));

        $response->assertRedirect($notification->data['url']);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_open_someone_elses_notification(): void
    {
        $player = $this->notifiedPlayer();
        $notification = $player->notifications()->sole();

        $this->actingAs(User::factory()->create())
            ->get(route('notifications.show', $notification->id))
            ->assertNotFound();
    }

    public function test_all_notifications_can_be_marked_read_at_once(): void
    {
        $player = $this->notifiedPlayer();

        $this->actingAs($player)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $player->unreadNotifications()->count());
    }
}
