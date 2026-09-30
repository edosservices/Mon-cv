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
            ->assertSee('Cette page n’encaisse aucun paiement.', false)
            ->assertSee('https://whatsapp.com/channel/0029VaUWQydISTkMIlmbpy1E', false)
            ->assertSee('https://www.tiktok.com/@limete.wifi', false)
            ->assertDontSee('2,000 FC', false)
            ->assertDontSee('http://limetewifi.cd', false)
            ->assertDontSee('>akm<', false);
    }
}
