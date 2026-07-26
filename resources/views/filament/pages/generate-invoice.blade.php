<x-filament-panels::page>

    {{-- Form utama --}}
    {{ $this->invoiceForm }}

    {{-- Footer Actions --}}
    <div class="items-center justify-start mt-6" style="display:flex; gap:12px;">
        @if (! $this->isViewMode)
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
        @else
            {{-- Back button for View Mode --}}
            <x-filament::button
                color="gray"
                tag="a"
                href="{{ \App\Filament\Resources\Invoices\InvoiceResource::getUrl('index') }}"
            >
                Back
            </x-filament::button>
        @endif
    </div>

    {{-- Wajib ada agar modal dari getHeaderActions() bisa dirender --}}
    <x-filament-actions::modals />

</x-filament-panels::page>