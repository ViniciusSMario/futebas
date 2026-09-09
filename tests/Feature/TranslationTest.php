<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * O app fala português — inclusive quando recusa alguma coisa.
 *
 * Faltavam `lang/pt_BR/validation.php`, `auth.php` e `passwords.php`, e
 * como `fallback_locale` também é `pt_BR` não havia inglês para onde cair:
 * todo erro de formulário do app inteiro saía como a **chave**, literal,
 * embaixo do campo — "validation.required". O login errado respondia
 * "auth.failed".
 *
 * A comparação de chaves com `lang/en` é a guarda que importa: uma chave a
 * menos aqui não quebra nada visivelmente, só volta a imprimir
 * `validation.something` na cara de alguém.
 */
class TranslationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{0: string}>
     */
    public static function translationFiles(): array
    {
        return [
            'validation' => ['validation'],
            'auth' => ['auth'],
            'passwords' => ['passwords'],
            'pagination' => ['pagination'],
        ];
    }

    #[DataProvider('translationFiles')]
    public function test_the_portuguese_file_covers_every_key_laravel_ships(string $file): void
    {
        $english = $this->flatten(require base_path("lang/en/{$file}.php"));
        $portuguese = $this->flatten(require base_path("lang/pt_BR/{$file}.php"));

        // `custom` e `attributes` são nossos, não do Laravel: o inglês vem
        // vazio e não há nada a cobrar deles.
        $missing = array_values(array_filter(
            array_diff($english, $portuguese),
            fn (string $key) => ! str_starts_with($key, 'custom.') && ! str_starts_with($key, 'attributes.'),
        ));

        $this->assertSame([], $missing, "Chaves sem tradução em lang/pt_BR/{$file}.php.");
    }

    public function test_a_rejected_form_answers_in_portuguese(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), ['name' => '', 'email' => 'nao-e-email'])
            ->assertSessionHasErrors([
                'name' => 'O campo nome é obrigatório.',
                'email' => 'O campo e-mail deve conter um e-mail válido.',
            ]);
    }

    /**
     * A frase mais vista do app depois de "Entrar", e a que saía como
     * `auth.failed`.
     */
    public function test_a_wrong_login_answers_in_portuguese(): void
    {
        User::factory()->create(['email' => 'ze@exemplo.com']);

        $this->from(route('login'))
            ->post(route('login'), ['email' => 'ze@exemplo.com', 'password' => 'errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha não conferem.']);
    }

    /**
     * `attributes` é o que separa "O campo team_name é obrigatório" de uma
     * frase que alguém entende.
     */
    public function test_field_names_are_said_in_portuguese(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->from(route('games.create'))
            ->post(route('games.store'), [])
            ->assertSessionHasErrors([
                'team_name' => 'O campo nome da partida é obrigatório.',
                'max_players' => 'O campo máximo de jogadores é obrigatório.',
            ]);
    }

    /**
     * @param  array<string, mixed>  $lines
     * @return array<int, string>
     */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flatten($value, $path));

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }
}
