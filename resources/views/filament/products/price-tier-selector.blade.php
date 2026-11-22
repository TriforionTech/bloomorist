<div class="flex items-center gap-2">
    <span class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap hidden sm:inline">
        Tier Harga:
    </span>

    <x-filament::input.wrapper class="w-44">
        <x-filament::input.select wire:model.live="priceTier">
            <option value="toko">Toko (Default)</option>
            <option value="vendor">Vendor</option>
            <option value="dekor">Dekor</option>
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>