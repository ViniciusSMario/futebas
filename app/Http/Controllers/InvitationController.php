<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameInvitationStoreRequest;
use App\Http\Requests\InvitationStoreRequest;
use App\Models\Game;
use App\Models\Invitation;
use App\Models\PlayerProfile;
use App\Notifications\InvitationAnswered;
use App\Notifications\InvitationReceived;
use App\Services\GamePlayerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * List the authenticated player's invitations: pending ones awaiting a
     * response, and the ones already accepted or declined.
     */
    public function index(Request $request): View
    {
        $invitations = Invitation::query()
            ->where('user_id', $request->user()->id)
            ->with(['game', 'organizer'])
            ->latest()
            ->get()
            ->filter(fn (Invitation $invitation) => $invitation->game !== null);

        return view('invitations.index', [
            'pendentes' => $invitations->where('status', Invitation::STATUS_PENDING)->values(),
            'respondidos' => $invitations->whereIn('status', [Invitation::STATUS_ACCEPTED, Invitation::STATUS_DECLINED])->values(),
        ]);
    }

    /**
     * Display the form for inviting a player to one of the organizer's
     * existing open match requests.
     */
    public function create(PlayerProfile $playerProfile): View
    {
        $alreadyInvitedGameIds = Invitation::query()
            ->where('user_id', $playerProfile->user_id)
            ->pluck('game_id');

        $games = Game::query()
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->whereNotIn('id', $alreadyInvitedGameIds)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return view('invitations.create', [
            'playerProfile' => $playerProfile,
            'games' => $games,
        ]);
    }

    /**
     * Send an invitation from the authenticated organizer to the given
     * player for one of the organizer's existing match requests.
     */
    public function store(InvitationStoreRequest $request, PlayerProfile $playerProfile): RedirectResponse
    {
        $validated = $request->validated();

        $game = Game::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($validated['game_id']);

        $alreadyInvited = Invitation::query()
            ->where('game_id', $game->id)
            ->where('user_id', $playerProfile->user_id)
            ->exists();

        if ($alreadyInvited) {
            return back()->withErrors(['game_id' => __('Este jogador já foi convidado para essa partida.')])->withInput();
        }

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $request->user()->id,
            'user_id' => $playerProfile->user_id,
            'team' => $game->team_name,
            'position' => $validated['position'] ?? ($playerProfile->positions[0] ?? null),
            'value' => $validated['value'] ?? $game->price,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $invitation->user->notify(new InvitationReceived($invitation));

        return redirect()->route('players.show', $playerProfile)->with('status', 'invitation-sent');
    }

    /**
     * Accept an invitation. Only the invited player may do this. Accepting
     * also adds the player to the game's roster, respecting its capacity
     * and approval settings.
     */
    public function accept(Request $request, Invitation $invitation, GamePlayerService $service): RedirectResponse
    {
        abort_unless($invitation->user_id === $request->user()->id, 403);

        if ($invitation->status === Invitation::STATUS_PENDING) {
            DB::transaction(function () use ($invitation, $service, $request) {
                $invitation->update(['status' => Invitation::STATUS_ACCEPTED]);

                if ($invitation->game) {
                    $service->join($invitation->game, $request->user());
                }
            });

            // Only the answer goes out, not a separate "joined the game"
            // notice: to the organizer these are the same event.
            $invitation->organizer?->notify(new InvitationAnswered($invitation));
        }

        return redirect()->route('invitations.index')->with('status', 'invitation-accepted');
    }

    /**
     * Decline an invitation. Only the invited player may do this.
     */
    public function decline(Request $request, Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->user_id === $request->user()->id, 403);

        if ($invitation->status === Invitation::STATUS_PENDING) {
            $invitation->update(['status' => Invitation::STATUS_DECLINED]);

            $invitation->organizer?->notify(new InvitationAnswered($invitation));
        }

        return redirect()->route('invitations.index')->with('status', 'invitation-declined');
    }

    /**
     * Procurar jogadores para convidar para esta partida.
     *
     * Existia aqui uma segunda tela de busca, com o mesmo propósito da de
     * `players.search` e um formulário que foi ficando para trás dela. Duas
     * buscas para a mesma pergunta é uma a mais: esta rota continua valendo,
     * porque é o link que sai da partida e é um endereço melhor do que uma
     * query string, mas quem responde é a busca de verdade, com a partida
     * em vista.
     */
    public function searchForGame(Request $request, Game $game): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);

        return redirect()->route('players.search', ['game' => $game->id] + $request->query());
    }

    /**
     * Send an invitation from the authenticated organizer to the given
     * player, scoped to a specific game (the game comes from the route,
     * not a picked value).
     */
    public function storeForGame(GameInvitationStoreRequest $request, Game $game, PlayerProfile $playerProfile): RedirectResponse
    {
        abort_unless($game->user_id === $request->user()->id, 403);
        abort_unless($game->isOpen(), 403);

        $validated = $request->validated();

        $alreadyInvited = Invitation::query()
            ->where('game_id', $game->id)
            ->where('user_id', $playerProfile->user_id)
            ->exists();

        if ($alreadyInvited) {
            // Flash, e não `withErrors`: isto volta para a busca de
            // jogadores, que não tem campo `user_id` onde pendurar a
            // mensagem — ela ia para lugar nenhum. Erro de campo fica no
            // campo; ação recusada é aviso de página.
            return back()->with('error', __('Este jogador já foi convidado para essa partida.'));
        }

        $invitation = Invitation::create([
            'game_id' => $game->id,
            'organizer_id' => $request->user()->id,
            'user_id' => $playerProfile->user_id,
            'team' => $game->team_name,
            'position' => $validated['position'] ?? ($playerProfile->positions[0] ?? null),
            'value' => $validated['value'] ?? $game->price,
            'status' => Invitation::STATUS_PENDING,
        ]);

        $invitation->user->notify(new InvitationReceived($invitation));

        // Volta para de onde o convite saiu, que quase sempre é a busca:
        // quem está montando uma pelada convida quatro pessoas seguidas, e
        // ser jogado de volta para a partida a cada convite obrigaria a
        // refazer a busca inteira toda vez.
        return redirect()
            ->back(fallback: route('games.show', ['game' => $game, 'tab' => 'convites']))
            ->with('status', 'invitation-sent');
    }
}
