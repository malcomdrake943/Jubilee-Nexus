<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformResource\Pages;
use App\Models\Platform;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PlatformResource extends Resource
{
    protected static ?string $model = Platform::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Websites';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Website details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('url')
                    ->label('Store URL')
                    ->url()
                    ->required(),
                Forms\Components\FileUpload::make('logo')
                    ->image()
                    ->disk('public')
                    ->directory('platforms')
                    ->visibility('public')
                    ->saveUploadedFileUsing(static function (Forms\Components\BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                        if (! $file->exists()) {
                            return null;
                        }

                        $directory = trim((string) $component->getDirectory(), '/');
                        $fileName = $component->getUploadedFileNameForStorage($file);
                        $targetPath = $directory !== '' ? "{$directory}/{$fileName}" : $fileName;

                        $disk = $component->getDisk();
                        $stream = $file->readStream();

                        if (! is_resource($stream)) {
                            throw ValidationException::withMessages([
                                $component->getStatePath() => 'Unable to read the uploaded logo file.',
                            ]);
                        }

                        try {
                            $written = $disk->put($targetPath, $stream, [
                                'visibility' => $component->getVisibility(),
                            ]);
                        } catch (\Throwable $e) {
                            report($e);

                            throw ValidationException::withMessages([
                                $component->getStatePath() => 'Unable to save logo to storage. Please check write permissions on storage/app/public/platforms.',
                            ]);
                        } finally {
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }

                        if (! $written || ! $disk->exists($targetPath)) {
                            throw ValidationException::withMessages([
                                $component->getStatePath() => 'Unable to save logo to storage. Please check write permissions on storage/app/public/platforms.',
                            ]);
                        }

                        return $targetPath;
                    })
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Is Active')
                    ->default(true),
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->disk('public')
                    ->visibility('public')
                    ->circular()
                    ->extraImgAttributes(['class' => '!object-contain bg-white p-1'])
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('url')
                    ->label('Store URL')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatforms::route('/'),
            'create' => Pages\CreatePlatform::route('/create'),
            'edit' => Pages\EditPlatform::route('/{record}/edit'),
        ];
    }
}
