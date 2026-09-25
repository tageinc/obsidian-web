<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class DashboardSearchTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    private User $owner;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
        config(['frontend.vue3.dashboard' => true]);
        foreach (['owner', 'other'] as $role) {
            $this->{$role} = User::create([
                'name' => ucfirst($role), 'email' => $role.'@search.example.test',
                'password' => 'synthetic-hash', 'email_verified_at' => now(),
            ]);
        }
        $this->actingAs($this->owner);
    }

    private function device(string $name, ?User $owner = null, int $hardware = 1): Device
    {
        return Device::create([
            'serial_no' => 'search-'.(Device::count() + 1), 'name' => $name,
            'user_id' => ($owner ?? $this->owner)->id,
            'state' => 'active',
            'latitude' => 33, 'longitude' => -117,
        ]);
    }

    private function dashboard(string $query): array
    {
        $response = $this->get('/dashboard?'.$query)->assertOk();
        preg_match('/<script id="frontend-dashboard" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_status_filter_uses_latest_status_before_pagination_and_matches_map(): void
    {
        $sleep = $this->device('Roof sleeper');
        $online = $this->device('Roof online');
        $foreign = $this->device('Other owner', $this->other);
        foreach ([[$sleep, 'online', now()->subMinute()], [$sleep, 'sleep', now()], [$online, 'online', now()], [$foreign, 'private-status', now()]] as [$device, $status, $time]) {
            DB::table('geocode')->insert(['serial_no' => $device->serial_no, 'status' => $status, 'updated_at' => $time]);
        }
        $props = $this->dashboard('search=Roof&status=sleep&show=1');
        $this->assertSame(1, $props['pagination']['total']);
        $this->assertSame($sleep->id, $props['devices'][0]['id']);
        $this->assertSame(['sleep'], $props['statusFilter']);
        $this->assertSame(['online', 'sleep'], $props['statusOptions']);
        $this->assertStringContainsString('sleep', $props['mapEndpoints']['paginated']);
        $this->get($props['mapEndpoints']['paginated'])->assertOk()->assertJsonPath('data.0.id', $sleep->id)->assertJsonPath('total', 1);
        $this->get('/all-devices?status=online')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $online->id);
        $this->assertSame(2, $this->dashboard('status=')['pagination']['total']);
        $this->getJson('/paginated-devices?status[]=sleep&status[]=online')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/paginated-devices?status[][]=sleep')->assertStatus(422);
    }

    public function test_name_search_filters_before_pagination_and_keeps_owned_maps_aligned(): void
    {
        for ($index = 1; $index <= 12; $index++) {
            $this->device('Earlier device '.$index);
        }
        $match = $this->device('Roof TARGET tracker');
        $this->device('Other TARGET tracker', $this->other);
        $this->device('Inactive TARGET tracker', $this->owner, 2);

        $props = $this->dashboard('search=%20tArGeT%20&show=1');
        $this->assertSame('tArGeT', $props['search']);
        $this->assertSame(2, $props['pagination']['total']);
        $this->assertSame([$match->id], array_column($props['devices'], 'id'));
        $this->getJson($props['mapEndpoints']['all'])->assertOk()->assertJsonCount(2)->assertJsonPath('0.id', $match->id);
        $this->getJson($props['mapEndpoints']['paginated'].'&show=1')->assertOk()->assertJsonPath('total', 2)->assertJsonPath('data.0.id', $match->id);
    }

    public function test_state_filter_includes_inactive_devices_only_when_selected(): void
    {
        $active = $this->device('Active tracker');
        $inactive = $this->device('Inactive tracker');
        $inactive->update(['state' => 'inactive']);

        $this->assertDatabaseHas('devices', ['id' => $active->id, 'state' => 'active']);
        $defaultProps = $this->dashboard('');
        $this->assertSame([$active->id], array_column($defaultProps['devices'], 'id'));

        $props = $this->dashboard('state=inactive');
        $this->assertSame(['inactive'], $props['stateFilter']);
        $this->assertSame(['active', 'inactive'], $props['stateOptions']);
        $this->assertSame([$inactive->id], array_column($props['devices'], 'id'));
        $this->get($props['mapEndpoints']['all'])->assertOk()->assertJsonPath('0.id', $inactive->id);
        $this->getJson('/paginated-devices?state[]=active&state[]=inactive')
            ->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/paginated-devices?state=removed')->assertUnprocessable()->assertJsonValidationErrors('state.0');
    }

    public function test_search_treats_sql_wildcards_escape_characters_and_quotes_as_literal_name_text(): void
    {
        $names = ['Panel 100%', 'Panel 1000', 'Deck_one', 'DeckXone', 'Bang! tracker', 'Bang tracker', "Owner's tracker", 'Owners tracker'];
        $devices = [];
        foreach ($names as $name) $devices[$name] = $this->device($name);
        foreach (['%' => 'Panel 100%', '_' => 'Deck_one', '!' => 'Bang! tracker', "'" => "Owner's tracker"] as $search => $name) {
            $query = http_build_query(['search' => $search]);
            $props = $this->dashboard($query);
            $this->assertSame([$devices[$name]->id], array_column($props['devices'], 'id'));
            $this->getJson('/all-devices?'.$query)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $devices[$name]->id);
            $this->getJson('/paginated-devices?'.$query)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $devices[$name]->id);
        }
    }

    public function test_search_and_page_size_survive_pagination_and_legacy_redirects(): void
    {
        foreach (['North roof', 'South roof', 'East roof'] as $name) $this->device($name);
        $props = $this->dashboard('search=roof&show=1&page=2');
        $this->assertSame('South roof', $props['devices'][0]['name']);
        foreach ($props['pagination']['links'] as $link) {
            if (!$link['url']) continue;
            parse_str(parse_url($link['url'], PHP_URL_QUERY), $query);
            $this->assertSame('roof', $query['search']);
            $this->assertSame('1', $query['show']);
        }
        $map = $this->getJson('/paginated-devices?search=roof&show=1&page=2')->assertOk();
        $this->assertSame('South roof', $map->json('data.0.name'));
        foreach (['next_page_url', 'prev_page_url'] as $key) {
            parse_str(parse_url($map->json($key), PHP_URL_QUERY), $query);
            $this->assertSame('roof', $query['search']);
            $this->assertSame('1', $query['show']);
        }
        foreach (['/', '/device-manager'] as $url) {
            $this->get($url.'?search=roof&show=1&page=2')->assertRedirect('/dashboard?search=roof&show=1&page=2');
        }
    }

    public function test_empty_and_blank_search_restore_all_owned_devices_and_no_match_returns_empty_maps(): void
    {
        $this->device('Owner tracker');
        $this->device('Foreign tracker', $this->other);
        foreach (['', 'search=', 'search=%20%20'] as $query) {
            $props = $this->dashboard($query);
            $this->assertSame('', $props['search']);
            $this->assertSame(1, $props['pagination']['total']);
        }
        $props = $this->dashboard('search=not-found');
        $this->assertSame([], $props['devices']);
        $this->assertSame(0, $props['pagination']['total']);
        $this->getJson($props['mapEndpoints']['all'])->assertOk()->assertExactJson([]);
        $this->getJson($props['mapEndpoints']['paginated'])->assertOk()->assertJsonPath('total', 0)->assertJsonCount(0, 'data');
    }

    public function test_search_validation_rejects_arrays_and_overlong_values_on_each_browser_endpoint(): void
    {
        foreach (['/dashboard', '/all-devices', '/paginated-devices'] as $url) {
            foreach ([['search' => ['invalid']], ['search' => str_repeat('a', 256)]] as $query) {
                $this->getJson($url.'?'.http_build_query($query))->assertUnprocessable()->assertJsonValidationErrors('search');
            }
            $this->getJson($url.'?search='.str_repeat('a', 255))->assertOk();
        }
    }

    public function test_legacy_search_form_empty_states_and_action_disclosure_preserve_routes(): void
    {
        config(['frontend.vue3.dashboard' => false]);
        $device = $this->device('Legacy roof');
        $response = $this->get('/dashboard?search=roof&show=20')->assertOk()
            ->assertSee('name="search" value="roof"', false)->assertSee('maxlength="255"', false)
            ->assertSee('aria-label="Actions for Legacy roof"', false)->assertSee('<details>', false)
            ->assertSee(route('devices.show', $device->id), false)
            ->assertSee(route('edit-device', $device->id), false)
            ->assertSee(route('retireDevice', $device->id), false)
            ->assertSee('>Inactivate</button>', false);
        $this->assertStringContainsString("encodeURIComponent(\"roof\")", $response->getContent());
        $this->get('/dashboard?search=no-match')->assertOk()->assertSee('No devices match this name search.')->assertDontSee('No devices registered yet.');
        $this->actingAs($this->other)->get('/dashboard')->assertOk()->assertSee('No devices registered yet.')->assertDontSee('No devices match this name search.');
    }
}
