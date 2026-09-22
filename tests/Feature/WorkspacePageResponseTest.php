<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Support\FrontendPagePayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class WorkspacePageResponseTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    private User $owner;
    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
        $this->travelTo(now()->startOfMinute());
        config(['frontend.vue3.workspace' => true]);
        foreach (FrontendPagePayload::ROUTES as $route) {
            config(['frontend.vue3.'.$route['flag'] => true]);
        }
        $this->owner = User::create([
            'name' => 'Workspace owner', 'email' => 'workspace-owner@example.test',
            'password' => 'never-serialize-password-hash', 'email_verified_at' => now(),
            'address_1' => '10 Test Street',
        ]);
        $this->device = Device::create([
            'serial_no' => 'workspace-tracker', 'user_id' => $this->owner->id,
        ]);
    }

    private function page(string $url)
    {
        return $this->getJson($url, ['X-Obsidian-Page' => '1']);
    }

    public function test_workspace_dtos_reuse_only_the_authorized_page_mount_props_and_preserve_query_urls(): void
    {
        $this->actingAs($this->owner);
        $paths = [
            '/dashboard?show=20&page=1' => 'dashboard', '/profile' => 'profile',
            '/edit-device/'.$this->device->id => 'edit-device',
            '/devices/'.$this->device->id => 'view-device',
        ];
        foreach ($paths as $url => $page) {
            $html = $this->get($url)->assertOk()->assertSee('data-workspace="1"', false);
            preg_match('/<script id="frontend-'.preg_quote($page, '/').'" type="application\/json">(.*?)<\/script>/s', $html->getContent(), $matches);
            $htmlProps = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            $json = $this->page($url)->assertOk()->assertJsonPath('page', $page)->assertJsonPath('url', $url);
            $this->assertSame(['page', 'props', 'url'], array_keys($json->json()));
            $this->assertSame($htmlProps, $json->json('props'));
            $this->assertStringNotContainsString('never-serialize-password-hash', $json->getContent());
            $this->assertArrayNotHasKey('navigation', $json->json());
            foreach ([$html, $json] as $response) {
                $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
                $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
                $this->assertContains('X-Obsidian-Page', $response->headers->all('vary'));
            }
        }
    }

    public function test_both_negotiation_headers_and_all_workspace_flags_are_required(): void
    {
        $this->actingAs($this->owner);
        $this->getJson('/profile')->assertOk()->assertSee('data-vue-page="profile"', false);
        $this->get('/profile', ['X-Obsidian-Page' => '1', 'Accept' => 'text/html'])
            ->assertOk()->assertSee('data-vue-page="profile"', false);
        config(['frontend.vue3.workspace' => false]);
        $this->page('/profile')->assertStatus(409);
        $this->get('/profile')->assertOk()->assertDontSee('data-workspace="1"', false);
        config(['frontend.vue3.workspace' => true, 'frontend.vue3.view_device' => false]);
        $this->page('/profile')->assertStatus(409);
        $this->get('/profile')->assertOk()->assertDontSee('data-workspace="1"', false);
    }

    public function test_negotiation_never_bypasses_guest_verification_or_device_ownership(): void
    {
        $this->page('/profile')->assertUnauthorized();
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'hash']);
        $this->actingAs($other);
        $this->page('/profile')->assertForbidden();
        $other->email_verified_at = now();
        $other->save();
        foreach (['/edit-device/', '/devices/'] as $prefix) {
            $this->page($prefix.$this->device->id)->assertForbidden();
        }
        config(['app.developer_email' => $other->email]);
        $this->page('/devices/'.$this->device->id)->assertOk()->assertJsonPath('page', 'view-device');
        $this->page('/edit-device/'.$this->device->id)->assertOk()->assertJsonPath('page', 'edit-device');
    }

    public function test_noncanonical_urls_remain_working_vue_islands_without_mounting_an_unmatched_router(): void
    {
        $this->actingAs($this->owner);
        // A full URL with a query prevents the test URL helper from trimming the slash.
        foreach (['http://localhost/profile/?source=compatibility' => 'profile', '/edit-device/0'.$this->device->id => 'edit-device', '/devices/0'.$this->device->id => 'view-device'] as $url => $page) {
            $this->get($url)->assertOk()->assertSee('data-vue-page="'.$page.'"', false)
                ->assertDontSee('data-workspace="1"', false);
            $this->page($url)->assertStatus(409);
        }
    }

    public function test_nonworkspace_routes_and_missing_device_redirects_retain_existing_contracts(): void
    {
        $this->actingAs($this->owner);
        $this->page('/devices/999999')->assertNotFound();
        $this->page('/device-manager?show=20')->assertRedirect('/dashboard?show=20');
        $this->page('/all-devices')->assertOk()->assertJsonPath('0.id', $this->device->id);
        $contact = $this->page('/contact-us')->assertOk()->assertSee('Contact Us');
        $this->assertStringContainsString('text/html', $contact->headers->get('Content-Type'));
        $this->page('/not-a-workspace-route')->assertNotFound();
    }
}
