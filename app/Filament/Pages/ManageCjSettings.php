<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\CjDropshippingService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageCjSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'CJdropshipping API';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 14;

    protected static string $view = 'filament.pages.manage-cj-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'cj_markup_percent'             => Setting::get('cj_markup_percent', (string) config('services.cjdropshipping.markup_percent', 25)),
            'store_shipping_markup_percent' => Setting::get('store_shipping_markup_percent', '0'),
            'store_shipping_flat_fee'       => Setting::get('store_shipping_flat_fee', '0'),
            'store_tax_percent'             => Setting::get('store_tax_percent', '0'),
            'store_tax_flat'                => Setting::get('store_tax_flat', '0'),
            'cj_auto_fulfill'               => filter_var(Setting::get('cj_auto_fulfill', config('services.cjdropshipping.auto_fulfill', true) ? '1' : '0'), FILTER_VALIDATE_BOOLEAN),
            'cj_auto_pay'                   => filter_var(Setting::get('cj_auto_pay', config('services.cjdropshipping.auto_pay', true) ? '1' : '0'), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('CJdropshipping Order Automation')
                    ->description('Control automated order placement and CJ Wallet balance payment for Store purchases.')
                    ->schema([
                        Toggle::make('cj_auto_fulfill')
                            ->label('Auto-create orders on CJ immediately when customer places a Store order')
                            ->default(true),
                        Toggle::make('cj_auto_pay')
                            ->label('Auto-pay CJ Wallet balance (payType = 2)')
                            ->default(true),
                    ]),

                Section::make('Store Pricing, Shipping & Tax Rules')
                    ->description('Control default item retail markup, shipping fees/markups, and tax rates shown to customers in the Store tab.')
                    ->schema([
                        TextInput::make('cj_markup_percent')
                            ->label('Default Item Retail Markup (%)')
                            ->numeric()
                            ->default(25)
                            ->required()
                            ->suffix('%'),
                        TextInput::make('store_shipping_markup_percent')
                            ->label('Shipping Markup (% over CJ Freight)')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->suffix('%'),
                        TextInput::make('store_shipping_flat_fee')
                            ->label('Additional Flat Shipping / Handling Fee ($)')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->prefix('$'),
                        TextInput::make('store_tax_percent')
                            ->label('Global Store Tax / VAT Rate (%)')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->suffix('%'),
                        TextInput::make('store_tax_flat')
                            ->label('Global Flat Tax Fee ($ per order)')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->prefix('$'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        Setting::set('cj_markup_percent', (string) ($data['cj_markup_percent'] ?? 25), 'CJdropshipping Default Markup %', 'cjdropshipping');
        Setting::set('store_shipping_markup_percent', (string) ($data['store_shipping_markup_percent'] ?? 0), 'Store Shipping Markup %', 'cjdropshipping');
        Setting::set('store_shipping_flat_fee', (string) ($data['store_shipping_flat_fee'] ?? 0), 'Store Shipping Flat Fee $', 'cjdropshipping');
        Setting::set('store_tax_percent', (string) ($data['store_tax_percent'] ?? 0), 'Store Global Tax %', 'cjdropshipping');
        Setting::set('store_tax_flat', (string) ($data['store_tax_flat'] ?? 0), 'Store Global Flat Tax $', 'cjdropshipping');
        Setting::set('cj_auto_fulfill', ! empty($data['cj_auto_fulfill']) ? '1' : '0', 'CJdropshipping Auto Fulfill', 'cjdropshipping');
        Setting::set('cj_auto_pay', ! empty($data['cj_auto_pay']) ? '1' : '0', 'CJdropshipping Auto Pay Balance', 'cjdropshipping');

        Notification::make()
            ->title('CJdropshipping Settings Saved')
            ->success()
            ->send();
    }

    public function testConnection(CjDropshippingService $cjService): void
    {
        $this->submit();

        try {
            $token = $cjService->getAccessToken(true);
            Notification::make()
                ->title('Connected to CJdropshipping')
                ->body('Access token retrieved successfully (' . substr($token, 0, 12) . '...).')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('CJ Connection Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function registerWebhooks(CjDropshippingService $cjService): void
    {
        $this->submit();

        try {
            $callbackUrl = route('webhooks.cjdropshipping');
            $cjService->registerWebhooks($callbackUrl);

            Notification::make()
                ->title('CJ Webhooks Registered')
                ->body("Callback URL set to {$callbackUrl}")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Webhook Registration Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
