<?php

namespace Tests\Feature;

use App\Models\ExternalApiKey;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalApiKeySearchTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;
    private const KEYS = '/developer-workspace/api-keys';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00', 'UTC'));
        $this->developer = User::factory()->create(['email' => 'developer@key-search.example.test']);
        config(['app.developer_email' => $this->developer->email]);
        $this->actingAs($this->developer);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function key(string $name, array $attributes = [], ?User $owner = null): ExternalApiKey
    {
        [$key] = ExternalApiKey::issue($owner ?? $this->developer, $name, null);
        $key->forceFill(array_merge(['created_at' => '2026-09-01 12:00:00'], $attributes))->save();

        return $key;
    }

    private function listing(array $query = []): array
    {
        $response = $this->getJson(self::KEYS.'?'.http_build_query($query))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        return $response->json();
    }

    private function ids(array $query): array
    {
        return array_column($this->listing($query)['data'], 'id');
    }

    public function test_search_filters_full_history_before_pagination_and_keeps_every_condition_owner_scoped(): void
    {
        $matches = [];
        for ($index = 1; $index <= 11; $index++) {
            $matches[] = $this->key('Needle integration '.$index)->id;
        }
        for ($index = 1; $index <= 25; $index++) {
            $this->key('Unrelated '.$index, ['created_at' => '2026-09-20 12:00:00']);
        }
        $foreign = $this->key('Needle foreign integration', ['prefix' => 'needle-prefix'], User::factory()->create());
        $result = $this->listing(['search' => ' NeEdLe ', 'per_page' => 10, 'page' => 2]);
        $this->assertSame([$matches[0]], array_column($result['data'], 'id'));
        $this->assertSame(11, $result['total']);
        $this->assertSame(11, $result['from']);
        $this->assertSame(11, $result['to']);
        $this->assertSame(2, $result['current_page']);
        $this->assertSame(2, $result['last_page']);
        $this->assertSame(10, $result['per_page']);
        $this->assertSame('NeEdLe', $result['filters']['search']);
        $this->assertSame([], $this->ids(['search' => $foreign->prefix]));
        $this->assertNotContains($foreign->id, $this->ids(['statuses' => ['Active', 'Expired', 'Revoked'], 'expiration' => ['never', 'dated'], 'per_page' => 100]));
    }

    public function test_status_filters_match_expiration_boundaries_and_revocation_precedence(): void
    {
        $never = $this->key('Never expires');
        $future = $this->key('Future', ['expires_at' => now()->addSecond()]);
        $atBoundary = $this->key('At boundary', ['expires_at' => now()]);
        $past = $this->key('Past', ['expires_at' => now()->subSecond()]);
        $revokedExpired = $this->key('Revoked expired', ['expires_at' => now()->subDay(), 'revoked_at' => now()]);
        $revokedFuture = $this->key('Revoked future', ['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $revokedNever = $this->key('Revoked never', ['revoked_at' => now()]);
        foreach ([
            'Active' => [$future->id, $never->id],
            'Expired' => [$past->id, $atBoundary->id],
            'Revoked' => [$revokedNever->id, $revokedFuture->id, $revokedExpired->id],
        ] as $status => $expected) {
            $result = $this->listing(['statuses' => [$status]]);
            $this->assertSame($expected, array_column($result['data'], 'id'));
            $this->assertSame([$status], array_values(array_unique(array_column($result['data'], 'status'))));
        }
        $this->assertSame([$past->id, $atBoundary->id, $future->id, $never->id], $this->ids(['statuses' => ['Active', 'Expired']]));
        $this->assertSame([$revokedNever->id, $never->id], $this->ids(['expiration' => ['never']]));
        $this->assertSame([$future->id], $this->ids(['statuses' => ['Active'], 'expiration' => ['dated']]));
        $this->assertSame(7, $this->listing(['expiration' => ['never', 'dated']])['total']);
        $this->assertSame(['Active'], $this->listing(['statuses' => ['Active', 'Active']])['filters']['statuses']);
    }

    public function test_search_status_expiration_and_inclusive_utc_created_dates_combine(): void
    {
        $first = $this->key('Roof first', ['created_at' => '2026-09-10 00:00:00', 'expires_at' => '2026-11-01 00:00:00']);
        $last = $this->key('Roof last', ['created_at' => '2026-09-12 23:59:59', 'expires_at' => '2026-11-01 00:00:00']);
        $this->key('Roof before', ['created_at' => '2026-09-09 23:59:59', 'expires_at' => '2026-11-01 00:00:00']);
        $this->key('Roof after', ['created_at' => '2026-09-13 00:00:00', 'expires_at' => '2026-11-01 00:00:00']);
        $this->key('Roof no date', ['created_at' => '2026-09-11 12:00:00']);
        $this->key('Roof revoked', ['created_at' => '2026-09-11 12:00:00', 'expires_at' => '2026-11-01 00:00:00', 'revoked_at' => now()]);
        $this->key('Garden dated', ['created_at' => '2026-09-11 12:00:00', 'expires_at' => '2026-11-01 00:00:00']);
        $query = ['search' => 'roof', 'statuses' => ['Active'], 'expiration' => ['dated'], 'from' => '2026-09-10', 'to' => '2026-09-12'];
        $this->assertSame([$last->id, $first->id], $this->ids($query));
        $query['from'] = '2026-09-12';
        $this->assertSame([$last->id], $this->ids($query));
    }

    public function test_sorting_is_deterministic_and_puts_missing_expiration_and_usage_last(): void
    {
        $zulu = $this->key('Zulu', ['created_at' => '2026-09-01 12:00:00']);
        $alpha = $this->key('Alpha', ['created_at' => '2026-09-01 12:00:00', 'expires_at' => '2026-12-01 00:00:00', 'last_used_at' => '2026-09-20 12:00:00']);
        $beta = $this->key('Beta', ['created_at' => '2026-09-02 12:00:00', 'expires_at' => '2026-11-01 00:00:00', 'last_used_at' => '2026-09-25 12:00:00']);
        $secondBeta = $this->key('Beta', ['created_at' => '2026-09-02 12:00:00', 'expires_at' => '2026-11-01 00:00:00', 'last_used_at' => '2026-09-25 12:00:00']);
        foreach ([
            'newest' => [$secondBeta->id, $beta->id, $alpha->id, $zulu->id],
            'oldest' => [$zulu->id, $alpha->id, $beta->id, $secondBeta->id],
            'name_asc' => [$alpha->id, $secondBeta->id, $beta->id, $zulu->id],
            'expires_asc' => [$secondBeta->id, $beta->id, $alpha->id, $zulu->id],
            'last_used_desc' => [$secondBeta->id, $beta->id, $alpha->id, $zulu->id],
        ] as $sort => $expected) {
            $this->assertSame($expected, $this->ids(['sort' => $sort]));
        }
    }

    public function test_literal_search_uses_only_name_and_display_prefix_and_lists_omit_secrets(): void
    {
        $names = ['100% ready', '1000 ready', 'Deck_one', 'DeckXone', 'Bang! integration', 'Bang integration', "Owner's integration", 'Owners integration'];
        $keys = [];
        foreach ($names as $name) $keys[$name] = $this->key($name, ['prefix' => 'test-prefix']);
        foreach (['%' => '100% ready', '_' => 'Deck_one', '!' => 'Bang! integration', "'" => "Owner's integration"] as $search => $name) {
            $this->assertSame([$keys[$name]->id], $this->ids(['search' => $search]));
        }
        $prefixMatch = $this->key('Unique integration', ['prefix' => 'display_needle']);
        $this->assertSame([$prefixMatch->id], $this->ids(['search' => 'lay_nee']));
        $this->assertSame([], $this->ids(['search' => 'Active']));
        [$key, $secret] = ExternalApiKey::issue($this->developer, 'Secret absent', null);
        $response = $this->getJson(self::KEYS)->assertOk()->assertDontSee($secret)->assertDontSee($key->token_hash);
        foreach ($response->json('data') as $row) {
            $this->assertSame(['id', 'name', 'prefix', 'created_at', 'last_used_at', 'expires_at', 'status'], array_keys($row));
        }
    }

    public function test_page_clamps_after_filtered_last_row_is_revoked_and_for_empty_results(): void
    {
        for ($index = 1; $index <= 11; $index++) $this->key('Integration '.$index);
        $query = ['statuses' => ['Active'], 'per_page' => 10, 'page' => 2];
        $before = $this->listing($query);
        $this->assertSame(2, $before['current_page']);
        $this->assertCount(1, $before['data']);
        $this->postJson(self::KEYS.'/'.$before['data'][0]['id'].'/revoke')->assertOk();
        $after = $this->listing($query);
        $this->assertSame(1, $after['current_page']);
        $this->assertSame(1, $after['last_page']);
        $this->assertSame(10, $after['total']);
        $this->assertCount(10, $after['data']);
        $this->assertSame(1, $after['from']);
        $this->assertSame(10, $after['to']);
        $empty = $this->listing(['search' => 'no-match', 'page' => 1000000]);
        $this->assertSame([], $empty['data']);
        $this->assertSame(1, $empty['current_page']);
        $this->assertSame(0, $empty['total']);
        $this->assertNull($empty['from']);
        $this->assertNull($empty['to']);
    }

    public function test_defaults_and_malformed_filter_validation_are_safe(): void
    {
        $defaults = $this->listing();
        $this->assertSame(20, $defaults['per_page']);
        $this->assertSame(['search' => '', 'statuses' => [], 'expiration' => [], 'from' => '', 'to' => '', 'sort' => 'newest'], $defaults['filters']);
        foreach ([
            ['search', ['nested']], ['search', str_repeat('x', 256)],
            ['statuses', 'Active'], ['statuses', [['nested']]], ['statuses', ['Invalid']], ['statuses', array_fill(0, 4, 'Active')],
            ['expiration', 'never'], ['expiration', [['nested']]], ['expiration', ['Invalid']], ['expiration', array_fill(0, 3, 'never')],
            ['from', '2026-02-30'], ['from', ['nested']], ['to', 'tomorrow'], ['to', ['nested']],
            ['sort', 'name desc; drop table users'], ['sort', ['newest']],
            ['per_page', 2], ['per_page', 0], ['per_page', 101], ['per_page', ['20']],
            ['page', 0], ['page', 1000001], ['page', ['1']],
        ] as [$filter, $value]) {
            $this->getJson(self::KEYS.'?'.http_build_query([$filter => $value]))->assertUnprocessable();
        }
        $this->getJson(self::KEYS.'?from=2026-09-20&to=2026-09-01')
            ->assertUnprocessable()->assertJsonValidationErrors('to');
        foreach ([10, 20, 50, 100] as $size) $this->assertSame($size, $this->listing(['per_page' => $size])['per_page']);
        $this->assertSame('', $this->listing(['search' => '  '])['filters']['search']);
    }

    public function test_legacy_creation_does_not_validate_post_body_as_listing_filters(): void
    {
        $response = $this->post(self::KEYS.'?statuses=invalid', [
            'name' => 'Legacy integration', 'search' => ['invalid'], 'statuses' => 'invalid',
            'expiration' => ['invalid'], 'from' => ['invalid'], 'page' => 0, 'per_page' => 0,
        ])->assertCreated()->assertSee('Copy this key now')->assertSee('Legacy integration');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('external_api_keys', ['user_id' => $this->developer->id, 'name' => 'Legacy integration']);
        $this->get(self::KEYS)->assertOk()->assertDontSee('Copy this key now');
    }
}
