<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class PublicFrontendTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
    }

    private function props($response): array
    {
        $response->assertOk()->assertSee('data-vue-page="public"', false);
        preg_match('/<script id="frontend-public" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_contact_is_public_in_both_renderers_and_keeps_contact_details(): void
    {
        config(['frontend.vue3.public_pages' => true]);
        $response = $this->get('/contact-us');
        $props = $this->props($response);
        $this->assertSame('contact', $props['mode']);
        $this->assertSame('tel:+019494902059', $props['contact']['phoneHref']);
        $this->assertSame('info@tezca.net', $props['contact']['email']);
        $this->assertSame(1, substr_count(strtolower($response->getContent()), '<!doctype html>'));
        config(['frontend.vue3.public_pages' => false]);
        $this->get('/contact-us')->assertOk()->assertDontSee('data-vue-page="public"', false)
            ->assertSee('href="mailto:info@tezca.net"', false);
        $this->assertFileExists(base_path('public/pdf/Obsidian Privacy Policy May1st2024.pdf'));
    }

    public function test_thank_you_keeps_verified_authentication_and_original_links_in_both_renderers(): void
    {
        config(['frontend.vue3.public_pages' => true]);
        $this->get('/thank-you')->assertRedirect('/login');
        $user = User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'hash']);
        $this->actingAs($user)->get('/thank-you')->assertRedirect('/email/verify');
        $user->email_verified_at = now();
        $user->save();
        $props = $this->props($this->get('/thank-you'));
        $this->assertSame('thank-you', $props['mode']);
        $this->assertSame(route('create-device'), $props['links']['createDevice']);
        $this->assertSame(route('device-manager'), $props['links']['deviceManager']);
        config(['frontend.vue3.public_pages' => false]);
        $this->get('/thank-you')->assertOk()->assertDontSee('data-vue-page="public"', false)
            ->assertSee('Create Another Device')->assertSee('View My Devices');
    }
}
