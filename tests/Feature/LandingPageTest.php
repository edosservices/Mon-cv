<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_home_is_the_marketing_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Gérez votre WiFi comme un vrai business.', false)
            ->assertSee(route('register'), false)
            ->assertSee(route('login'), false)
            ->assertSee(route('client.buy'), false)
            ->assertSee('5 $', false)
            ->assertSee('Classique', false)
            ->assertSee('Cette page n’encaisse aucun paiement.', false);
    }
}
