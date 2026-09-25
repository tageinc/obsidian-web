<?php

namespace Tests\Feature;

use App\Models\ConfigVersions;
use App\Models\FirmwareVersions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsesFrontendManifest;
use Tests\TestCase;

class DeveloperReleaseSearchTest extends TestCase
{
    use RefreshDatabase;
    use UsesFrontendManifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useFrontendManifest();
        config(['frontend.vue3.developer' => true, 'app.developer_email' => 'developer@release.example.test']);
        $this->actingAs(User::create([
            'name' => 'Developer', 'email' => 'developer@release.example.test',
            'password' => 'synthetic-hash', 'email_verified_at' => now(),
        ]));
    }

    private function release(string $model, string $version, string $prefix = 'SP1', string $description = 'Standard release', string $date = '2026-09-01 12:00:00'): void
    {
        $record = $model::create([
            'version' => $version, 'prefix' => $prefix, 'description' => $description,
            'file_path' => 'private-storage-must-not-be-serialized',
        ]);
        $record->forceFill(['created_at' => $date])->save();
    }

    private function workspace(array $query = []): array
    {
        $response = $this->get('/developer-workspace?'.http_build_query($query))->assertOk();
        $response->assertDontSee('private-storage-must-not-be-serialized');
        $this->assertSame(1, preg_match('/<script id="frontend-developer" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches));

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_search_filters_the_whole_history_before_pagination_and_options_stay_complete(): void
    {
        foreach (['firmware' => FirmwareVersions::class, 'config' => ConfigVersions::class] as $kind => $model) {
            for ($version = 1; $version <= 12; $version++) {
                $this->release($model, (string) $version, 'SP1', 'Standard release', '2026-09-20 12:00:00');
            }
            $this->release($model, '13', 'SP2', 'North roof TARGET release', '2026-08-01 12:00:00');
            $props = $this->workspace([$kind.'_search' => ' tArGeT ', $kind.'_show' => 2])[$kind];
            $this->assertSame('tArGeT', $props['filters']['search']);
            $this->assertSame(['13'], array_column($props['rows'], 'version'));
            $this->assertSame(1, $props['pagination']['total']);
            $this->assertSame(1, $props['pagination']['from']);
            $this->assertSame(1, $props['pagination']['to']);
            $this->assertSame(['SP1', 'SP2'], $props['filterOptions']['prefixes']);
            $this->assertCount(13, $props['filterOptions']['versions']);
            $this->assertSame(['13', '12', '11', '10'], array_slice($props['filterOptions']['versions'], 0, 4));
            foreach (['13', 'SP2'] as $search) {
                $this->assertSame(['13'], array_column($this->workspace([$kind.'_search' => $search])[$kind]['rows'], 'version'));
            }
        }
    }

    public function test_multi_selects_combine_with_search_and_inclusive_upload_dates(): void
    {
        foreach (['firmware' => FirmwareVersions::class, 'config' => ConfigVersions::class] as $kind => $model) {
            $this->release($model, '1', 'SP1', 'Roof release', '2026-09-10 00:00:00');
            $this->release($model, '2', 'SP2', 'Roof release', '2026-09-12 23:59:59');
            $this->release($model, '3', 'SP3', 'Roof release', '2026-09-11 12:00:00');
            $this->release($model, '4', 'SP1', 'Roof release', '2026-09-09 23:59:59');
            $this->release($model, '5', 'SP1', 'Roof release', '2026-09-13 00:00:00');
            $this->release($model, '6', 'SP1', 'Garden release', '2026-09-11 12:00:00');
            $query = [
                $kind.'_search' => 'roof', $kind.'_prefixes' => ['SP1', 'SP2', 'SP1'],
                $kind.'_versions' => ['1', '2', '3', '4', '5', '6'],
                $kind.'_from' => '2026-09-10', $kind.'_to' => '2026-09-12',
            ];
            $props = $this->workspace($query)[$kind];
            $this->assertSame(['2', '1'], array_column($props['rows'], 'version'));
            $this->assertSame(['SP1', 'SP2'], $props['filters']['prefixes']);
            $this->assertSame(2, $props['pagination']['total']);
            $query[$kind.'_versions'] = ['1'];
            $this->assertSame(['1'], array_column($this->workspace($query)[$kind]['rows'], 'version'));
            $query[$kind.'_prefixes'] = ['SP'];
            $this->assertSame([], $this->workspace($query)[$kind]['rows']);
        }
    }

    public function test_search_treats_wildcards_quotes_and_escape_characters_as_literal_text(): void
    {
        $descriptions = ['100% ready', '1000 ready', 'Deck_one', 'DeckXone', 'Bang! release', 'Bang release', "Owner's release", 'Owners release'];
        foreach ($descriptions as $index => $description) {
            $this->release(FirmwareVersions::class, (string) ($index + 1), 'SP1', $description);
        }
        foreach (['%' => '1', '_' => '3', '!' => '5', "'" => '7'] as $search => $version) {
            $props = $this->workspace(['firmware_search' => $search])['firmware'];
            $this->assertSame([$version], array_column($props['rows'], 'version'));
        }
    }

    public function test_sorting_is_numeric_and_uses_stable_created_at_and_id_order(): void
    {
        foreach (['firmware' => FirmwareVersions::class, 'config' => ConfigVersions::class] as $kind => $model) {
            $this->release($model, '2', 'ZZZ', 'First release', '2026-09-01 12:00:00');
            $this->release($model, '10', 'AAA', 'Second release', '2026-09-01 12:00:00');
            $this->release($model, '3', 'AAA', 'Newest release', '2026-09-02 12:00:00');
            foreach ([
                'newest' => ['3', '10', '2'], 'oldest' => ['2', '10', '3'],
                'version_desc' => ['10', '3', '2'], 'version_asc' => ['2', '3', '10'],
                'prefix_asc' => ['3', '10', '2'],
            ] as $sort => $expected) {
                $this->assertSame($expected, array_column($this->workspace([$kind.'_sort' => $sort])[$kind]['rows'], 'version'));
            }
        }
    }

    public function test_pagination_and_hidden_query_preserve_each_history_independently(): void
    {
        for ($version = 1; $version <= 5; $version++) {
            $this->release(FirmwareVersions::class, (string) $version, 'FW', 'Roof firmware');
            $this->release(ConfigVersions::class, (string) $version, 'CFG', 'Roof configuration');
        }
        $query = [
            'section' => 'config', 'firmware_show' => 1, 'config_show' => 2,
            'firmware_page' => 2, 'config_page' => 3, 'page' => 5,
            'firmware_search' => ' Roof ', 'config_search' => 'configuration',
            'firmware_prefixes' => ['FW'], 'config_prefixes' => ['CFG'],
            'firmware_sort' => 'oldest', 'config_sort' => 'version_desc',
            'unrelated' => 'must-not-persist',
        ];
        $props = $this->workspace($query);
        $this->assertSame('config', $props['activeSection']);
        $this->assertSame(['2'], array_column($props['firmware']['rows'], 'version'));
        $this->assertSame(['1'], array_column($props['config']['rows'], 'version'));
        $this->assertSame(2, $props['firmware']['pagination']['currentPage']);
        $this->assertSame(3, $props['config']['pagination']['currentPage']);
        foreach (['firmware', 'config'] as $kind) {
            $otherKind = $kind === 'firmware' ? 'config' : 'firmware';
            $preserved = array_column($props[$kind]['preservedQuery'], 'value', 'name');
            $this->assertSame((string) $query[$otherKind.'_page'], $preserved[$otherKind.'_page']);
            $this->assertSame($query[$otherKind.'_prefixes'][0], $preserved[$otherKind.'_prefixes[]']);
            $this->assertArrayNotHasKey($kind.'_page', $preserved);
            foreach ($props[$kind]['pagination']['links'] as $link) {
                if (!$link['url']) continue;
                parse_str(parse_url($link['url'], PHP_URL_QUERY), $params);
                $this->assertSame($kind, $params['section']);
                $this->assertSame('Roof', $params['firmware_search']);
                $this->assertSame('configuration', $params['config_search']);
                $this->assertSame(['CFG'], $params['config_prefixes']);
                $this->assertSame(['FW'], $params['firmware_prefixes']);
                $this->assertSame((string) $query[$otherKind.'_page'], $params[$otherKind.'_page']);
                $this->assertArrayNotHasKey('page', $params);
                $this->assertArrayNotHasKey('unrelated', $params);
            }
        }
        $props = $this->workspace(['section' => 'config', 'page' => 2, 'config_show' => 1]);
        $this->assertSame(1, $props['firmware']['pagination']['currentPage']);
        $this->assertSame(2, $props['config']['pagination']['currentPage']);
        $props = $this->workspace(['page' => 2, 'firmware_show' => 1]);
        $this->assertSame(2, $props['firmware']['pagination']['currentPage']);
        $this->assertSame(1, $props['config']['pagination']['currentPage']);
    }

    public function test_default_empty_and_no_match_payloads_are_bounded_and_do_not_leak_storage(): void
    {
        $empty = $this->workspace();
        foreach (['firmware', 'config'] as $kind) {
            $this->assertSame([], $empty[$kind]['rows']);
            $this->assertSame(10, $empty[$kind]['pagination']['perPage']);
            $this->assertSame(0, $empty[$kind]['pagination']['total']);
            $this->assertNull($empty[$kind]['pagination']['from']);
            $this->assertNull($empty[$kind]['pagination']['to']);
            $this->assertSame(['search' => '', 'prefixes' => [], 'versions' => [], 'from' => '', 'to' => '', 'sort' => 'newest'], $empty[$kind]['filters']);
        }
        $this->release(FirmwareVersions::class, '1');
        $props = $this->workspace(['firmware_search' => '  ', 'firmware_show' => 100]);
        $this->assertSame('', $props['firmware']['filters']['search']);
        $this->assertCount(1, $props['firmware']['rows']);
        $this->assertSame(['version', 'prefix', 'description', 'createdAt'], array_keys($props['firmware']['rows'][0]));
        $props = $this->workspace(['firmware_search' => 'missing']);
        $this->assertSame([], $props['firmware']['rows']);
        $this->assertSame(0, $props['firmware']['pagination']['total']);
        $this->assertSame(['SP1'], $props['firmware']['filterOptions']['prefixes']);
    }

    public function test_invalid_filters_dates_sort_and_pagination_are_rejected(): void
    {
        foreach (['firmware', 'config'] as $kind) {
            foreach ([
                ['search', ['nested']], ['search', str_repeat('x', 256)],
                ['prefixes', 'SP1'], ['prefixes', [['nested']]], ['prefixes', array_fill(0, 101, 'SP1')],
                ['versions', ['x' => ['nested']]], ['versions', [str_repeat('x', 256)]],
                ['from', '2026-02-30'], ['from', ['nested']], ['to', 'yesterday'],
                ['sort', 'created_at desc; drop table users'], ['sort', ['newest']],
                ['show', 0], ['show', 101], ['show', ['10']], ['show', 'ten'],
                ['page', 0], ['page', 1000001], ['page', ['1']],
            ] as [$filter, $value]) {
                $this->getJson('/developer-workspace?'.http_build_query([$kind.'_'.$filter => $value]))
                    ->assertUnprocessable();
            }
            $this->getJson('/developer-workspace?'.http_build_query([
                $kind.'_from' => '2026-09-20', $kind.'_to' => '2026-09-01',
            ]))->assertUnprocessable()->assertJsonValidationErrors($kind.'_to');
        }
        $this->getJson('/developer-workspace?page[]=1')->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    public function test_legacy_page_size_forms_preserve_other_history_filters_and_named_pages(): void
    {
        config(['frontend.vue3.developer' => false]);
        $this->release(FirmwareVersions::class, '1');
        $this->get('/developer-workspace?firmware_search=release&config_prefixes[]=CFG&config_page=2&firmware_show=1')
            ->assertOk()->assertSee('Developer Workspace')
            ->assertSee('name="firmware_search" value="release"', false)
            ->assertSee('name="config_prefixes[]" value="CFG"', false)
            ->assertSee('name="config_page" value="2"', false)
            ->assertDontSee('private-storage-must-not-be-serialized');
    }
}
