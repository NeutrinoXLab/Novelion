<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformationalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_five_information_pages_render_with_their_existing_core_content(): void
    {
        $pages = [
            'pages.returns' => ['Retururi, produse neconforme și garanții', 'Vreau să returnez un produs', 'Formular-model de retragere', 'Produs defect/neconform', 'Regulamentul de punere în aplicare (UE) 2025/1960'],
            'pages.terms' => ['Termeni și condiții', 'Contract, preț și plată', 'Retur, neconformitate și rambursare', 'Garanții și reclamații', 'SAL'],
            'pages.privacy' => ['Politica de confidențialitate și cookies', 'Date și scopuri', 'Destinatari și păstrare', 'Ștergerea contului', 'Cookies'],
            'pages.shipping' => ['Livrare', 'Sameday', 'Pregătire și predare', 'Timpul de pregătire și predare este distinct de timpul efectiv de transport al curierului.'],
            'pages.contact' => ['Contact Novelion', 'NOVELION S.R.L.', '52627291', 'J2025075714007', 'Str. Daciei nr. 11, Ploiești, Prahova, 100352'],
        ];

        foreach ($pages as $route => $texts) {
            $response = $this->get(route($route));

            $response->assertOk();
            $this->assertSame(1, preg_match_all('/<h1\b/i', $response->getContent()), $route);
            $response->assertSee('max-w-5xl', false)
                ->assertSee('rounded-3xl', false);

            foreach ($texts as $text) {
                $response->assertSeeText($text);
            }
        }
    }

    public function test_information_pages_preserve_essential_links_and_semantic_lists(): void
    {
        $this->get(route('pages.returns'))
            ->assertOk()
            ->assertSee('https://eur-lex.europa.eu/eli/reg_impl/2025/1960/oj/ron', false)
            ->assertSee('mailto:novelionprime@gmail.com', false);

        $this->get(route('pages.terms'))
            ->assertOk()
            ->assertSee('https://reclamatiisal.anpc.ro', false);

        $this->get(route('pages.privacy'))
            ->assertOk()
            ->assertSee('list-disc', false)
            ->assertSeeText('consimțământul pentru newsletter');

        $this->get(route('pages.contact'))
            ->assertOk()
            ->assertSee('mailto:novelionprime@gmail.com', false)
            ->assertSee('tel:+40750444672', false)
            ->assertSeeText('Aceasta este și adresa oficială pentru retururi și reclamații.');
    }
}
