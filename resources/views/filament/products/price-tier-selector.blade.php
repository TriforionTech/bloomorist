<div class="px-4 py-3 w-72">
    <div class="flex items-center gap-2">
        <div class="text-sm font-medium text-gray-700 dark:text-gray-200 whitespace-nowrap">
            Tampilkan Harga:
        </div>

        <x-filament::input.wrapper class="flex-1">
            <x-filament::input.select wire:model.live="priceTier">
                <option value="toko">Toko (Default)</option>
                <option value="vendor">Vendor</option>
                <option value="dekor">Dekor</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
</div>