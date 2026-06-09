<x-shell-layout :title="$title.' | '.config('app.name')">
    <x-glass-card :title="$title" :subtitle="$description">
        <x-empty-state :title="__('ui.panels.empty_title')" :message="__('ui.panels.empty_message')" />
    </x-glass-card>
</x-shell-layout>
