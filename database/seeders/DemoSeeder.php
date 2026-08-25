<?php

namespace Database\Seeders;

use App\Models\Availability;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameSeries;
use App\Models\GuestPlayer;
use App\Models\Invitation;
use App\Models\PlayerProfile;
use App\Models\Rating;
use App\Models\SosApplication;
use App\Models\User;
use App\Notifications\InvitationReceived;
use App\Services\GamePlayerService;
use App\Services\GameSeriesService;
use App\Services\SosService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * A populated Teresina for demoing Futebas end to end.
 *
 * Everything here goes through the real models and services, so the data
 * behaves like data the app produced itself: capacity and waiting lists are
 * enforced, attendance and rating averages are recomputed rather than
 * written by hand, and a shared match link actually resolves.
 *
 * Run with: php artisan migrate:fresh --seed
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const CITY = 'Teresina';

    private const STATE = 'PI';

    /**
     * The cast. `quality` drives the ratings they receive and `reliability`
     * how often they turn up, so the search's "melhor avaliados" and "mais
     * presentes" orderings show real contrast instead of noise.
     *
     * @var array<int, array{name: string, position: string, level: string, price: int, quality: int, reliability: int, email?: string}>
     */
    private const ROSTER = [
        ['name' => 'Bruno Carvalho', 'email' => 'jogador@futebas.test', 'position' => 'Atacante', 'level' => 'Intermediário', 'price' => 30, 'quality' => 4, 'reliability' => 90],
        ['name' => 'Marcos Paulo', 'email' => 'goleiro@futebas.test', 'position' => 'Goleiro', 'level' => 'Avançado', 'price' => 60, 'quality' => 5, 'reliability' => 100],
        ['name' => 'Diego Fontes', 'position' => 'Goleiro', 'level' => 'Intermediário', 'price' => 45, 'quality' => 4, 'reliability' => 80],
        ['name' => 'Alan Ribeiro', 'position' => 'Goleiro', 'level' => 'Recreativo', 'price' => 35, 'quality' => 3, 'reliability' => 60],
        ['name' => 'Thiago Muniz', 'position' => 'Zagueiro', 'level' => 'Avançado', 'price' => 40, 'quality' => 5, 'reliability' => 100],
        ['name' => 'Rafael Lopes', 'position' => 'Zagueiro', 'level' => 'Intermediário', 'price' => 30, 'quality' => 4, 'reliability' => 85],
        ['name' => 'André Coelho', 'position' => 'Zagueiro', 'level' => 'Recreativo', 'price' => 25, 'quality' => 3, 'reliability' => 50],
        ['name' => 'Caio Bezerra', 'position' => 'Lateral', 'level' => 'Intermediário', 'price' => 30, 'quality' => 4, 'reliability' => 90],
        ['name' => 'Igor Vasconcelos', 'position' => 'Lateral', 'level' => 'Recreativo', 'price' => 25, 'quality' => 3, 'reliability' => 70],
        ['name' => 'Pedro Henrique', 'position' => 'Volante', 'level' => 'Avançado', 'price' => 45, 'quality' => 5, 'reliability' => 95],
        ['name' => 'Lucas Farias', 'position' => 'Volante', 'level' => 'Intermediário', 'price' => 30, 'quality' => 4, 'reliability' => 80],
        ['name' => 'Wesley Amorim', 'position' => 'Meia', 'level' => 'Avançado', 'price' => 50, 'quality' => 5, 'reliability' => 90],
        ['name' => 'Danilo Sousa', 'position' => 'Meia', 'level' => 'Intermediário', 'price' => 35, 'quality' => 4, 'reliability' => 75],
        ['name' => 'Vinícius Teles', 'position' => 'Meia', 'level' => 'Iniciante', 'price' => 20, 'quality' => 3, 'reliability' => 65],
        ['name' => 'Gustavo Lima', 'position' => 'Atacante', 'level' => 'Avançado', 'price' => 55, 'quality' => 5, 'reliability' => 85],
        ['name' => 'Felipe Rocha', 'position' => 'Atacante', 'level' => 'Recreativo', 'price' => 25, 'quality' => 2, 'reliability' => 40],
    ];

    private User $organizer;

    private User $rivalOrganizer;

    /** @var Collection<int, User> */
    private Collection $players;

    public function run(): void
    {
        // Notifications are ShouldQueue and dev runs the database queue, so
        // without this the demo inboxes stay empty until a worker picks the
        // jobs up. Sending inline keeps seeding self-contained.
        config(['queue.default' => 'sync']);

        $this->organizer = $this->createOrganizer('Ricardo Nunes', 'organizador@futebas.test');
        $this->rivalOrganizer = $this->createOrganizer('Sandra Aguiar', 'sandra@futebas.test');
        $this->players = $this->createRoster();

        $this->seedHistory();
        $this->seedFinishedMatchAwaitingRatings();
        $this->seedTodaysMatch();
        $this->seedUpcomingMatches();
        $this->seedWeeklySeries();
        $this->seedOpenSosCall();
        $this->seedPendingInvitations();

        $this->recomputeReputations();

        $this->command?->info('Demo pronta. Entre com organizador@futebas.test, jogador@futebas.test ou goleiro@futebas.test — senha: '.self::PASSWORD);
    }

    private function createOrganizer(string $name, string $email): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'role' => User::ROLE_ORGANIZER,
            'phone' => '86988880000',
            'city' => self::CITY,
            'state' => self::STATE,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function createRoster(): Collection
    {
        $players = new Collection;

        foreach (self::ROSTER as $index => $entry) {
            $user = User::create([
                'name' => $entry['name'],
                'email' => $entry['email'] ?? $this->emailFor($entry['name']),
                'password' => Hash::make(self::PASSWORD),
                'role' => User::ROLE_PLAYER,
                'phone' => '869'.str_pad((string) (10000000 + $index), 8, '0', STR_PAD_LEFT),
                'city' => self::CITY,
                'state' => self::STATE,
                'email_verified_at' => now(),
            ]);

            PlayerProfile::create([
                'user_id' => $user->id,
                'birth_date' => Carbon::parse('1995-01-01')->addMonths($index * 5)->format('Y-m-d'),
                'state' => self::STATE,
                'city' => self::CITY,
                'phone' => $user->phone,
                'positions' => [$entry['position']],
                'modalities' => ['Society', 'Futsal'],
                'level' => $entry['level'],
                'price_per_game' => $entry['price'].'.00',
                'plays_outside_city' => $index % 3 === 0,
                'price_per_game_outside' => $index % 3 === 0 ? ($entry['price'] + 20).'.00' : null,
            ]);

            // Two evenings and a Saturday morning — enough for the
            // availability filter in the player search to bite.
            foreach ([2, 4, 6] as $day) {
                Availability::create([
                    'user_id' => $user->id,
                    'day_of_week' => $day,
                    'start_time' => $day === 6 ? '08:00' : '19:00',
                    'end_time' => $day === 6 ? '11:00' : '22:00',
                ]);
            }

            $players->push($user);
        }

        return $players;
    }

    /**
     * Six weeks of matches already played, which is what gives every player
     * an attendance record and a rating history to sort and filter on.
     */
    private function seedHistory(): void
    {
        // previous() always steps strictly backwards, so these are six
        // distinct Thursdays that have certainly already happened.
        $lastThursday = today()->previous(Carbon::THURSDAY);

        foreach (range(1, 6) as $weeksAgo) {
            $date = $lastThursday->copy()->subWeeks($weeksAgo - 1);

            $game = $this->createGame([
                'user_id' => $this->organizer->id,
                'team_name' => 'Pelada de Quinta',
                'location' => 'Arena Society Dirceu',
                'date' => $date->format('Y-m-d'),
                'start_time' => '19:00',
                'end_time' => '21:00',
                'max_players' => 14,
                'price' => '25.00',
                'status' => Game::STATUS_FINISHED,
            ]);

            $lineup = $this->players->slice(0, 12);

            foreach ($lineup as $player) {
                $entry = $this->rosterEntry($player);

                // Deterministic, but spread out: the flakier the player, the
                // more often the absence lands on them.
                $turnedUp = (($player->id * 7 + $weeksAgo * 13) % 100) < $entry['reliability'];

                $gamePlayer = GamePlayer::create([
                    'game_id' => $game->id,
                    'user_id' => $player->id,
                    'status' => GamePlayer::STATUS_CONFIRMED,
                    'payment_status' => GamePlayer::PAYMENT_PAID,
                    'amount_due' => $game->price,
                    'joined_at' => $date->copy()->subDays(3),
                    'checked_in_at' => $turnedUp ? $date->copy()->setTime(18, 30) : null,
                    'no_show' => ! $turnedUp,
                ]);

                if ($turnedUp) {
                    $this->rate($game, $player, $entry['quality'], $gamePlayer->joined_at);
                }
            }
        }
    }

    /**
     * Last week's match: finished, with only part of the squad rated — so
     * "Avaliar Jogadores" opens showing both states side by side.
     */
    private function seedFinishedMatchAwaitingRatings(): void
    {
        $date = today()->subDays(3);

        $game = $this->createGame([
            'user_id' => $this->organizer->id,
            'team_name' => 'Racha de Sábado',
            'location' => 'Society Boa Esperança',
            'date' => $date->format('Y-m-d'),
            'start_time' => '17:00',
            'end_time' => '19:00',
            'max_players' => 12,
            'price' => '30.00',
            'status' => Game::STATUS_FINISHED,
        ]);

        $lineup = $this->players->slice(0, 10);

        foreach ($lineup as $index => $player) {
            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $player->id,
                'status' => GamePlayer::STATUS_CONFIRMED,
                'payment_status' => $index < 8 ? GamePlayer::PAYMENT_PAID : GamePlayer::PAYMENT_PENDING,
                'amount_due' => $game->price,
                'joined_at' => $date->copy()->subDays(4),
                'checked_in_at' => $date->copy()->setTime(16, 40),
            ]);

            // Half rated, half still waiting — the demo needs both.
            if ($index < 5) {
                $this->rate($game, $player, $this->rosterEntry($player)['quality'], $date);
            }
        }
    }

    /**
     * A match kicking off tonight, with the check-in window already open and
     * the demo player yet to confirm.
     */
    private function seedTodaysMatch(): void
    {
        $kickoff = now()->addHours(5);

        $game = $this->createGame([
            'user_id' => $this->organizer->id,
            'team_name' => 'Futebas da Semana',
            'location' => 'Arena Society Dirceu',
            'date' => today()->format('Y-m-d'),
            'start_time' => $kickoff->format('H:i'),
            'end_time' => $kickoff->copy()->addHours(2)->format('H:i'),
            'max_players' => 12,
            'price' => '25.00',
            'status' => Game::STATUS_OPEN,
        ]);

        foreach ($this->players->slice(0, 9) as $index => $player) {
            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $player->id,
                'status' => GamePlayer::STATUS_CONFIRMED,
                'payment_status' => GamePlayer::PAYMENT_PENDING,
                'amount_due' => $game->price,
                'joined_at' => now()->subDays(2),
                // Everyone except the demo player has already checked in, so
                // the organizer's "presença de hoje" panel has something to
                // show and the player still has the button to press.
                'checked_in_at' => $player->email === 'jogador@futebas.test' ? null : now()->subHours(2),
            ]);
        }
    }

    /**
     * Matches to find in the search: room to spare, nearly full, and one
     * full enough that joining means the waiting list.
     */
    private function seedUpcomingMatches(): void
    {
        $service = app(GamePlayerService::class);

        $spacious = $this->createGame([
            'user_id' => $this->rivalOrganizer->id,
            'team_name' => 'Bola Rolando FC',
            'location' => 'Quadra do Parque Piauí',
            'date' => today()->addDays(2)->format('Y-m-d'),
            'start_time' => '20:00',
            'end_time' => '22:00',
            'max_players' => 14,
            'price' => '20.00',
            'positions' => ['Goleiro', 'Zagueiro'],
            'description' => 'Pelada tranquila, nível recreativo. Levem colete branco.',
            'status' => Game::STATUS_OPEN,
        ]);

        foreach ($this->players->slice(4, 5) as $player) {
            $service->join($spacious, $player, bypassApproval: true);
        }

        $almostFull = $this->createGame([
            'user_id' => $this->rivalOrganizer->id,
            'team_name' => 'Fut das Antigas',
            'location' => 'Campo do Bela Vista',
            'modality' => 'Campo',
            'date' => today()->addDays(5)->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'max_players' => 12,
            'price' => '15.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
        ]);

        foreach ($this->players->slice(0, 11) as $player) {
            $service->join($almostFull, $player, bypassApproval: true);
        }

        // Full, so anyone joining from the search lands on the waiting list —
        // and two people are already queued to show how that reads.
        $full = $this->createGame([
            'user_id' => $this->rivalOrganizer->id,
            'team_name' => 'Futsal da Firma',
            'location' => 'Ginásio Verdão',
            'modality' => 'Futsal',
            'date' => today()->addDays(4)->format('Y-m-d'),
            'start_time' => '21:00',
            'end_time' => '22:30',
            'max_players' => 10,
            'price' => '18.00',
            'requires_approval' => false,
            'status' => Game::STATUS_OPEN,
        ]);

        foreach ($this->players->slice(0, 12) as $player) {
            $service->join($full, $player, bypassApproval: true);
        }
    }

    /**
     * The recurring pelada, with its regulars seated in every occurrence the
     * service generates.
     */
    private function seedWeeklySeries(): void
    {
        $series = GameSeries::create([
            'user_id' => $this->organizer->id,
            'team_name' => 'Pelada de Quinta',
            'location' => 'Arena Society Dirceu',
            'city' => self::CITY,
            'modality' => 'Society',
            'day_of_week' => Carbon::THURSDAY,
            'start_time' => '19:00',
            'end_time' => '21:00',
            'max_players' => 14,
            'price' => '25.00',
            'positions' => ['Goleiro'],
            'description' => 'Nossa pelada de toda quinta. Mensalistas já entram confirmados.',
            'requires_approval' => false,
            'status' => GameSeries::STATUS_ACTIVE,
        ]);

        $service = app(GameSeriesService::class);

        foreach ($this->players->slice(0, 8) as $player) {
            $service->addMember($series, $player);
        }

        // One regular with no app account, since that is how half a real
        // pelada's roster looks.
        $service->addMember($series, GuestPlayer::create([
            'organizer_id' => $this->organizer->id,
            'name' => 'Zé do Posto',
            'phone' => '86999990000',
        ]));

        $service->syncUpcoming($series);
    }

    /**
     * A live SOS with three goalkeepers competing at different prices.
     */
    private function seedOpenSosCall(): void
    {
        $game = $this->createGame([
            'user_id' => $this->organizer->id,
            'team_name' => 'Amistoso de Domingo',
            'location' => 'Arena Society Dirceu',
            'date' => today()->addDays(3)->format('Y-m-d'),
            'start_time' => '16:00',
            'end_time' => '18:00',
            'max_players' => 12,
            'price' => '30.00',
            'positions' => ['Goleiro'],
            'status' => Game::STATUS_OPEN,
        ]);

        // Outfield players only: the point of the call is the missing keeper.
        foreach ($this->players->whereNotIn('email', ['goleiro@futebas.test']) as $player) {
            if ($this->rosterEntry($player)['position'] !== 'Goleiro') {
                app(GamePlayerService::class)->join($game, $player, bypassApproval: true);
            }
        }

        $sosRequest = app(SosService::class)->publish(
            $game,
            $this->organizer,
            70.0,
            'Goleiro titular machucou. Pago na hora, via Pix.',
        );

        $bids = ['goleiro@futebas.test' => 70, 'Diego Fontes' => 60, 'Alan Ribeiro' => 85];

        foreach ($bids as $key => $price) {
            $keeper = $this->players->first(fn (User $user) => $user->email === $key || $user->name === $key);

            SosApplication::create([
                'sos_request_id' => $sosRequest->id,
                'user_id' => $keeper->id,
                'asking_price' => $price.'.00',
                'message' => $price < 70 ? 'Posso chegar 20 minutos antes.' : 'Levo luvas e colete.',
                'status' => SosApplication::STATUS_PENDING,
            ]);
        }
    }

    /**
     * Invitations still waiting on an answer, so the player's inbox and the
     * organizer's "Convites" tab both have something in them.
     */
    private function seedPendingInvitations(): void
    {
        $game = $this->createGame([
            'user_id' => $this->rivalOrganizer->id,
            'team_name' => 'Treino de Terça',
            'location' => 'Society Zona Leste',
            'date' => today()->addDays(6)->format('Y-m-d'),
            'start_time' => '19:30',
            'end_time' => '21:00',
            'max_players' => 10,
            'price' => '22.00',
            'status' => Game::STATUS_OPEN,
        ]);

        foreach ($this->players->slice(0, 3) as $player) {
            $invitation = Invitation::create([
                'game_id' => $game->id,
                'organizer_id' => $this->rivalOrganizer->id,
                'user_id' => $player->id,
                'team' => $game->team_name,
                'position' => $this->rosterEntry($player)['position'],
                'value' => $game->price,
                'status' => Invitation::STATUS_PENDING,
            ]);

            $player->notify(new InvitationReceived($invitation));
        }
    }

    /**
     * Rating averages and attendance records are denormalised, so they are
     * recomputed from the history above rather than written by hand.
     */
    private function recomputeReputations(): void
    {
        foreach ($this->players as $player) {
            $profile = $player->playerProfile;

            $profile?->recalculateRatingAverages();
            $profile?->recalculateAttendanceStats();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createGame(array $attributes): Game
    {
        return Game::create(array_merge([
            'city' => self::CITY,
            'modality' => 'Society',
            'positions' => [],
            'requires_approval' => false,
            'status' => Game::STATUS_OPEN,
        ], $attributes));
    }

    /**
     * A rating around the player's quality, nudged by their id so the four
     * criteria don't all come out identical.
     */
    private function rate(Game $game, User $player, int $quality, Carbon $when): void
    {
        $vary = fn (int $offset) => max(1, min(5, $quality - (($player->id + $offset) % 2)));

        Rating::create([
            'game_id' => $game->id,
            'organizer_id' => $game->user_id,
            'user_id' => $player->id,
            'overall_rating' => $quality,
            'punctuality_rating' => $vary(1),
            'behavior_rating' => $vary(2),
            'performance_rating' => $vary(3),
            'comment' => $quality >= 4 ? 'Jogou bem e é gente boa.' : null,
            'created_at' => $when,
            'updated_at' => $when,
        ]);
    }

    /**
     * @return array{name: string, position: string, level: string, price: int, quality: int, reliability: int, email?: string}
     */
    private function rosterEntry(User $player): array
    {
        foreach (self::ROSTER as $entry) {
            if ($entry['name'] === $player->name) {
                return $entry;
            }
        }

        return self::ROSTER[0];
    }

    private function emailFor(string $name): string
    {
        return str($name)->ascii()->lower()->replace(' ', '.')->toString().'@futebas.test';
    }
}
