{{-- <x-filament-panels::page>
    <form wire:submit.prevent="save">
        {{ $this->invoiceForm }}
    </form>
</x-filament-panels::page> --}}



<x-filament-panels::page>

    {{-- Form utama --}}
    {{ $this->invoiceForm }}

    {{-- Footer Actions --}}
    <div class="flex items-center justify-start gap-3 mt-6">
        {{-- Generate: validasi dulu via Livewire, baru buka modal jika lolos --}}
        <x-filament::button
            color="primary"
            {{-- icon="heroicon-o-document-arrow-down" --}}
            wire:click="validateThenGenerate"
        >
            Generate Invoice
        </x-filament::button>

        {{-- Reset: langsung trigger action bawaan Filament --}}
        <x-filament::button
            color="gray"
            wire:click="mountAction('resetForm')"
        >
            Reset
        </x-filament::button>


    </div>

    {{-- Wajib ada agar modal dari getHeaderActions() bisa dirender --}}
    <x-filament-actions::modals />

</x-filament-panels::page>