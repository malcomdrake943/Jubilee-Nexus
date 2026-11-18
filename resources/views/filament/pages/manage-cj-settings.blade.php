<x-filament-panels::page>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center gap-4 text-left">
            <x-filament::button type="submit">
                Save CJ Settings
            </x-filament::button>

            <x-filament::button type="button" color="info" wire:click="testConnection">
                Test API Connection
            </x-filament::button>

            <x-filament::button type="button" color="gray" wire:click="registerWebhooks">
                Register CJ Webhooks
            </x-filament::button>
        </div>

        <div class="text-xs text-gray-500 dark:text-gray-400">
            Webhook Endpoint URL: <code>{{ route('webhooks.cjdropshipping') }}</code>
        </div>
    </form>
</x-filament-panels::page>
