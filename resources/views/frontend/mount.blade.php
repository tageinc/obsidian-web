@php
    $mountId = 'frontend-'.preg_replace('/[^a-z0-9-]/', '', $page);
    $workspaceEnabled = \App\Support\FrontendPagePayload::record($page, $props);
@endphp
<div data-vue-page="{{ $page }}" data-props-id="{{ $mountId }}" @if($workspaceEnabled) data-workspace="1" @endif>
    <p role="status" class="text-muted">Loading…</p>
</div>
<script id="{{ $mountId }}" type="application/json">{!! json_encode($props, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
<noscript><p role="alert">Please enable JavaScript to use this page.</p></noscript>
