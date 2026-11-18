<?php

namespace App\Filament\Resources\StoreProductResource\Pages;

use App\Filament\Resources\StoreProductResource;
use App\Services\CjDropshippingService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListStoreProducts extends ListRecords
{
    protected static string $resource = StoreProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_by_pid')
                ->label('Import from CJ by PID')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('info')
                ->form([
                    Forms\Components\TextInput::make('cj_pid')
                        ->label('CJ Product ID (pid)')
                        ->required()
                        ->placeholder('e.g. 000B9312-456A-4D31-94BD-B083E2A198E8'),
                    Forms\Components\TextInput::make('markup_percent')
                        ->label('Retail Markup (%)')
                        ->numeric()
                        ->default(fn (CjDropshippingService $cj) => $cj->getDefaultMarkupPercent())
                        ->required(),
                ])
                ->action(function (array $data, CjDropshippingService $cjService): void {
                    try {
                        $product = $cjService->importProductByPid(
                            trim($data['cj_pid']),
                            (float) $data['markup_percent']
                        );

                        Notification::make()
                            ->title('Product Imported')
                            ->body("{$product->name} imported at \${$product->retail_price}.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('CJ Import Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('sync_my_products')
                ->label('Sync CJ "My Products"')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('keyword')
                        ->label('Filter Keyword (optional)')
                        ->placeholder('Leave empty to import up to 20 items'),
                    Forms\Components\TextInput::make('markup_percent')
                        ->label('Retail Markup (%)')
                        ->numeric()
                        ->default(fn (CjDropshippingService $cj) => $cj->getDefaultMarkupPercent())
                        ->required(),
                ])
                ->action(function (array $data, CjDropshippingService $cjService): void {
                    try {
                        $items = $cjService->getMyProducts((string) ($data['keyword'] ?? ''), 1, 20);
                        $count = 0;

                        foreach ($items as $item) {
                            $pid = $item['productId'] ?? $item['pid'] ?? null;
                            if ($pid) {
                                $cjService->importProductByPid((string) $pid, (float) $data['markup_percent']);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title('Sync Complete')
                            ->body("Imported/updated {$count} products from CJ My Products.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('CJ Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\CreateAction::make(),
        ];
    }
}
