<x-filament-panels::page>

    {{-- Form utama --}}
    {{ $this->invoiceForm }}

    {{-- Footer Actions --}}
    <div class="flex items-center justify-start gap-3 mt-6">
        {{-- Save: validasi dulu via Livewire, baru buka modal jika lolos --}}
        <x-filament::button
            color="primary"
            wire:click="validateThenGenerate"
        >
            {{ $this->isEditMode() ? 'Save Changes' : 'Save Invoice' }}
        </x-filament::button>

        {{-- Reset/Cancel: langsung trigger action bawaan Filament --}}
        <x-filament::button
            color="gray"
            wire:click="mountAction('resetForm')"
        >
            {{ $this->isEditMode() ? 'Cancel' : 'Reset' }}
        </x-filament::button>
    </div>

    {{-- Wajib ada agar modal dari getHeaderActions() bisa dirender --}}
    <x-filament-actions::modals />

</x-filament-panels::page>