@extends('components.layouts.backend_layout', ['guard' => 'admin'])

@section('content')
<div class="px-6 py-8">
    <livewire:global.ai.external-agent-editor :agentId="$id" />
</div>
@endsection
