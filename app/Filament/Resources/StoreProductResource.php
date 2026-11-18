<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreProductResource\Pages;
use App\Models\StoreProduct;
use App\Services\CjDropshippingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StoreProductResource extends Resource
{
    protected static ?string $model = StoreProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Store Products';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product Information')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Leave blank on create to auto-generate from name.'),
                Forms\Components\TextInput::make('category_name')
                    ->label('Category')
                    ->maxLength(191),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(191),
                Forms\Components\TextInput::make('image_url')
                    ->label('Main Image URL')
                    ->url()
                    ->maxLength(2048)
                    ->columnSpan(2),
                Forms\Components\Textarea::make('description')
                    ->rows(4)
                    ->columnSpan(2),
            ])->columns(2),

            Forms\Components\Section::make('Pricing & Stock')->schema([
                Forms\Components\TextInput::make('cj_cost_price')
                    ->label('CJ Cost Price')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),
                Forms\Components\TextInput::make('retail_price')
                    ->label('Store Retail Price')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\TextInput::make('custom_shipping_price')
                    ->label('Custom Shipping Price ($)')
                    ->numeric()
                    ->prefix('$')
                    ->nullable()
                    ->helperText('Leave blank to use CJ live shipping + global shipping markup, or enter a fixed shipping price for this item.'),
                Forms\Components\TextInput::make('tax_percent')
                    ->label('Custom Tax Rate (%)')
                    ->numeric()
                    ->suffix('%')
                    ->nullable()
                    ->helperText('Leave blank to use global store tax rate.'),
                Forms\Components\TextInput::make('custom_tax_amount')
                    ->label('Custom Flat Tax ($ per item)')
                    ->numeric()
                    ->prefix('$')
                    ->nullable()
                    ->helperText('Optional fixed tax override per item (takes priority over Tax Rate %).'),
                Forms\Components\TextInput::make('weight_grams')
                    ->label('Weight (grams)')
                    ->numeric()
                    ->default(500),
                Forms\Components\TextInput::make('stock')
                    ->numeric()
                    ->default(100),
                Forms\Components\Toggle::make('is_featured')
                    ->label('Featured in Store')
                    ->default(false),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active in Store')
                    ->default(true),
            ])->columns(2),

            Forms\Components\Section::make('Product Variants & Variant Prices')
                ->description('Edit individual variant retail prices, SKUs, or weights.')
                ->schema([
                    Forms\Components\Repeater::make('variants')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Option Name')
                                ->required(),
                            Forms\Components\TextInput::make('sku')
                                ->label('SKU'),
                            Forms\Components\TextInput::make('vid')
                                ->label('CJ VID'),
                            Forms\Components\TextInput::make('cj_price')
                                ->label('CJ Cost ($)')
                                ->numeric()
                                ->prefix('$'),
                            Forms\Components\TextInput::make('retail_price')
                                ->label('Your Retail Price ($)')
                                ->numeric()
                                ->prefix('$')
                                ->required(),
                            Forms\Components\TextInput::make('weight_grams')
                                ->label('Weight (g)')
                                ->numeric()
                                ->default(500),
                            Forms\Components\TextInput::make('image')
                                ->label('Variant Image URL')
                                ->url()
                                ->columnSpan(2),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->addActionLabel('Add Variant')
                        ->collapsed()
                        ->itemLabel(fn (array $state): ?string => ($state['name'] ?? 'Variant') . (isset($state['retail_price']) ? ' — $' . number_format((float) $state['retail_price'], 2) : '')),
                ]),

            Forms\Components\Section::make('CJdropshipping Identifiers')->schema([
                Forms\Components\TextInput::make('cj_pid')
                    ->label('CJ Product ID (PID)')
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('cj_vid')
                    ->label('Primary CJ Variant ID (VID)'),
                Forms\Components\TextInput::make('source_country_code')
                    ->label('Warehouse Country Code')
                    ->default('CN')
                    ->maxLength(5),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->circular()
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('category_name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('cj_cost_price')
                    ->label('CJ Cost')
                    ->formatStateUsing(fn ($state) => '$' . number_format((float) $state, 2))
                    ->sortable(),
                Tables\Columns\TextColumn::make('retail_price')
                    ->label('Retail Price')
                    ->formatStateUsing(fn ($state) => '$' . number_format((float) $state, 2))
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('custom_shipping_price')
                    ->label('Custom Shipping')
                    ->formatStateUsing(fn ($state) => $state !== null ? '$' . number_format((float) $state, 2) : 'Auto')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tax_percent')
                    ->label('Tax %')
                    ->formatStateUsing(fn ($state) => $state !== null ? rtrim(rtrim((string) $state, '0'), '.') . '%' : 'Global')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('cj_pid')
                    ->label('CJ PID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('update_prices')
                    ->label('Update Prices')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->form(fn (StoreProduct $record) => [
                        Forms\Components\TextInput::make('retail_price')
                            ->label('Item Retail Price ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default($record->retail_price)
                            ->required(),
                        Forms\Components\TextInput::make('custom_shipping_price')
                            ->label('Custom Shipping Price ($)')
                            ->numeric()
                            ->prefix('$')
                            ->default($record->custom_shipping_price)
                            ->nullable()
                            ->helperText('Leave empty to use live CJ / Delivery Option shipping.'),
                        Forms\Components\TextInput::make('tax_percent')
                            ->label('Tax Rate (%)')
                            ->numeric()
                            ->suffix('%')
                            ->default($record->tax_percent)
                            ->nullable()
                            ->helperText('Leave empty to use global store tax rate.'),
                    ])
                    ->action(function (StoreProduct $record, array $data): void {
                        $record->update([
                            'retail_price'          => $data['retail_price'],
                            'custom_shipping_price' => $data['custom_shipping_price'] !== '' ? $data['custom_shipping_price'] : null,
                            'tax_percent'           => $data['tax_percent'] !== '' ? $data['tax_percent'] : null,
                        ]);
                        Notification::make()->title('Product prices updated.')->success()->send();
                    }),
                Tables\Actions\Action::make('sync_from_cj')
                    ->label('Sync CJ')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (StoreProduct $record) => filled($record->cj_pid))
                    ->action(function (StoreProduct $record, CjDropshippingService $cjService): void {
                        try {
                            $cjService->importProductByPid((string) $record->cj_pid);
                            Notification::make()->title('Product synced from CJdropshipping.')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('CJ Sync Failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListStoreProducts::route('/'),
            'create' => Pages\CreateStoreProduct::route('/create'),
            'edit'   => Pages\EditStoreProduct::route('/{record}/edit'),
        ];
    }
}
