<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The placeholder is the project's convention for every image slot that
 * isn't user-uploaded (see docs/imagens.md). What has to hold is that
 * dropping a file in swaps the frame for a real <img> without the caller
 * rewriting the markup around it.
 */
class ImgPlaceholderTest extends TestCase
{
    public function test_it_reserves_the_slot_with_the_intended_path_and_size(): void
    {
        $html = Blade::render(
            '<x-img-placeholder ratio="4/5" label="Foto principal" path="images/landing/hero.jpg" size="1200x1500" />'
        );

        $this->assertStringContainsString('aspect-ratio: 4/5', $html);
        $this->assertStringContainsString('Foto principal', $html);
        $this->assertStringContainsString('images/landing/hero.jpg', $html);
        $this->assertStringContainsString('1200x1500', $html);
        $this->assertStringContainsString('border-dashed', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_passing_src_swaps_the_frame_for_the_real_image(): void
    {
        $html = Blade::render(
            '<x-img-placeholder ratio="4/5" src="images/landing/hero.jpg" alt="Pelada de quinta" />'
        );

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString(asset('images/landing/hero.jpg'), $html);
        $this->assertStringContainsString('alt="Pelada de quinta"', $html);
        // The ratio survives the swap, so the surrounding layout doesn't move.
        $this->assertStringContainsString('aspect-ratio: 4/5', $html);
        $this->assertStringNotContainsString('border-dashed', $html);
    }

    public function test_it_lazy_loads_by_default_and_prioritises_above_the_fold_images(): void
    {
        $lazy = Blade::render('<x-img-placeholder src="images/landing/hero.jpg" />');
        $eager = Blade::render('<x-img-placeholder src="images/landing/hero.jpg" :eager="true" />');

        $this->assertStringContainsString('loading="lazy"', $lazy);
        $this->assertStringNotContainsString('fetchpriority', $lazy);

        $this->assertStringContainsString('loading="eager"', $eager);
        $this->assertStringContainsString('fetchpriority="high"', $eager);
    }

    public function test_an_absolute_src_is_used_as_given(): void
    {
        $html = Blade::render('<x-img-placeholder src="https://cdn.exemplo.com/foto.jpg" />');

        $this->assertStringContainsString('src="https://cdn.exemplo.com/foto.jpg"', $html);
    }

    /**
     * The component owns cropping, ratio and rounding; the caller owns the
     * box. It must not ship its own `w-*`/`h-*`: `merge()` only concatenates
     * classes and Tailwind resolves by stylesheet order, where `w-full`
     * beats `w-10` — a 40px avatar would render full-bleed the moment the
     * real file was dropped in.
     */
    public function test_the_caller_owns_the_box_in_both_branches(): void
    {
        $box = 'w-10 h-10 ring-2';

        $reserved = Blade::render('<x-img-placeholder ratio="1/1" rounded="rounded-full" class="'.$box.'" />');
        $filled = Blade::render('<x-img-placeholder ratio="1/1" rounded="rounded-full" class="'.$box.'" src="images/landing/jogador-1.jpg" />');

        // Only the root element's classes matter here — inside the frame the
        // content wrapper legitimately fills it with `w-full h-full`.
        foreach (['reservado' => $reserved, 'preenchido' => $filled] as $branch => $html) {
            $classes = $this->rootClasses($html);

            $this->assertStringContainsString('w-10', $classes, "ramo {$branch}");
            $this->assertStringContainsString('h-10', $classes, "ramo {$branch}");
            $this->assertStringContainsString('rounded-full', $classes, "ramo {$branch}");
            $this->assertStringNotContainsString('w-full', $classes, "ramo {$branch}");
            $this->assertStringNotContainsString('h-full', $classes, "ramo {$branch}");
        }
    }

    /** The class attribute of the first element in the rendered markup. */
    private function rootClasses(string $html): string
    {
        preg_match('/<[a-z]+\b[^>]*\bclass="([^"]*)"/i', $html, $matches);

        $this->assertNotEmpty($matches, 'não foi possível ler a classe do elemento raiz');

        return $matches[1];
    }
}
