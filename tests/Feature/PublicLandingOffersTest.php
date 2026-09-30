<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Platform;
use Tests\TestCase;

class PublicLandingOffersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_offers_come_from_active_zones_without_private_data(): void
    {
        $owner = Platform::entrepreneur('Boutique Kingabwa', 'kingabwa@example.test');
        $kingabwa = Platform::zone($owner, 'Kingabwa');
        $hour = Platform::plan($owner, $kingabwa);
        $hour->update(['name' => '1 HEURE', 'duration_seconds' => 3600, 'price' => 500]);
        $week = Platform::plan($owner, $kingabwa);
        $week->update(['name' => '7 JOURS', 'duration_seconds' => 7 * 86400, 'price' => 5000]);
        $hidden = Platform::plan($owner, $kingabwa);
        $hidden->update(['name' => 'FORFAIT MASQUE', 'status' => 'inactive', 'price' => 7777]);

        $closed = Platform::zone($owner, 'Zone fermee');
        $closed->update(['status' => 'inactive']);
        $closedPlan = Platform::plan($owner, $closed);
        $closedPlan->update(['name' => 'PLAN FERME', 'price' => 4242]);

        $other = Platform::entrepreneur('Autre vendeur', 'autre@example.test');
        $xyz = Platform::zone($other, 'Zone XYZ');
        $day = Platform::plan($other, $xyz);
        $day->update(['name' => 'JOURNEE XYZ', 'price' => 1500]);
        $router = Platform::router($other, $xyz);
        $router->update(['password' => 'secret-router-xyz', 'username' => 'router-user-secret']);

        $paused = Platform::entrepreneur('Vendeur pause', 'pause@example.test');
        $paused->tenant->update(['status' => 'inactive']);
        $pausedZone = Platform::zone($paused, 'Zone pause');
        $pausedPlan = Platform::plan($paused, $pausedZone);
        $pausedPlan->update(['name' => 'PLAN PAUSE']);

        $html = $this->get(route('home'))
            ->assertOk()
            ->assertSee('Kingabwa', false)
            ->assertSee('Zone XYZ', false)
            ->assertSee('Boutique Kingabwa', false)
            ->assertSee('Autre vendeur', false)
            ->assertSee('1 HEURE', false)
            ->assertSee('7 JOURS', false)
            ->assertSee('JOURNEE XYZ', false)
            ->assertSee('500 FC', false)
            ->assertSee('5 000 FC', false)
            ->assertSee('1 500 FC', false)
            ->assertSee('Zone : Kingabwa', false)
            ->assertSee('Zone : Zone XYZ', false)
            ->assertSee(route('shop.plan', [$kingabwa->slug, $hour->id]), false)
            ->assertSee(route('shop.plan', [$kingabwa->slug, $week->id]), false)
            ->assertSee(route('shop.plan', [$xyz->slug, $day->id]), false)
            ->assertSee('https://whatsapp.com/channel/0029VaUWQydISTkMIlmbpy1E', false)
            ->assertSee('https://www.tiktok.com/@limete.wifi', false)
            ->assertSee('Votre ticket, prêt à vous connecter.', false)
            ->assertSee('edos-services.png', false)
            ->assertDontSee('FORFAIT MASQUE', false)
            ->assertDontSee('7 777 FC', false)
            ->assertDontSee('PLAN FERME', false)
            ->assertDontSee('4 242 FC', false)
            ->assertDontSee('PLAN PAUSE', false)
            ->assertDontSee('kingabwa@example.test', false)
            ->assertDontSee('autre@example.test', false)
            ->assertDontSee('pause@example.test', false)
            ->assertDontSee('secret-router-xyz', false)
            ->assertDontSee('router-user-secret', false)
            ->assertDontSee('+243860392283', false)
            ->assertDontSee('2,000 FC', false)
            ->assertDontSee('http://limetewifi.cd', false)
            ->assertDontSee('facebook.com', false)
            ->getContent();

        $this->assertStringNotContainsString('videos/limete-wifi.mp4', $html);
        $this->assertStringContainsString('data-rail', $html);
    }
}
