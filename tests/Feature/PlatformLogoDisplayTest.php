<?php

namespace Tests\Feature;

use App\Filament\Resources\PlatformResource\Pages\CreatePlatform;
use App\Filament\Resources\PlatformResource\Pages\EditPlatform;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformLogoDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_public_disk_generates_media_route_url_without_storage_link(): void
    {
        $this->assertSame(
            '/media?file=/platforms/new-store.png',
            Storage::disk('public')->url('platforms/new-store.png')
        );
    }

    public function test_superadmin_uploaded_website_logo_is_served_without_storage_link(): void
    {
        Storage::fake('public', [
            'url' => config('filesystems.disks.public.url'),
        ]);

        $superAdmin = User::factory()->create([
            'role' => 'superadmin',
        ]);

        $this->actingAs($superAdmin);

        $uploadedLogo = UploadedFile::fake()->image('ebay-logo.png', 240, 120);

        Livewire::test(CreatePlatform::class)
            ->fillForm([
                'name' => 'eBay',
                'url' => 'https://www.ebay.com',
                'logo' => $uploadedLogo,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $platform = Platform::where('name', 'eBay')->first();
        $this->assertNotNull($platform);
        $this->assertStringStartsWith('platforms/', $platform->logo);
        Storage::disk('public')->assertExists($platform->logo);

        // Model accessor resolves to Nginx/Apache/symlink-safe /media?file=/... URL
        $expectedUrl = '/media?file=/' . $platform->logo;
        $this->assertSame($expectedUrl, $platform->logo_url);

        // Query-based media route serves the uploaded image directly from storage/app/public
        $mediaResponse = $this->get($platform->logo_url);
        $mediaResponse->assertOk();
        $this->assertStringStartsWith('image/', (string) $mediaResponse->headers->get('Content-Type'));
        $this->assertStringContainsString(
            basename($platform->logo),
            (string) $mediaResponse->headers->get('Content-Disposition')
        );

        // Path-based fallback route (/media/...) also serves the uploaded image
        $this->get('/media/' . $platform->logo)->assertOk();

        // Public order page exposes logo_url alongside seeded platforms
        $orderPageResponse = $this->get('/order');
        $orderPageResponse->assertOk();
        $orderPageResponse->assertSee(json_encode($platform->logo_url));

        // Filament ImageColumn resolves the exact same /media?file=/... URL
        $imageColumn = \Filament\Tables\Columns\ImageColumn::make('logo')
            ->disk('public')
            ->visibility('public')
            ->checkFileExistence(false);
        $this->assertSame($expectedUrl, $imageColumn->getImageUrl($platform->logo));

        // Filament admin edit page loads existing record cleanly
        Livewire::test(EditPlatform::class, ['record' => $platform->getKey()])
            ->assertSuccessful();
    }

    public function test_seeded_platform_logos_display_identically_even_when_storage_copy_missing(): void
    {
        Storage::fake('public', [
            'url' => config('filesystems.disks.public.url'),
        ]);

        foreach (['Amazon' => 'amazon.png', 'Walmart' => 'walmart.png', 'Best Buy' => 'bestbuy.png'] as $name => $file) {
            $platform = Platform::create([
                'name' => $name,
                'url' => 'https://www.' . strtolower(str_replace(' ', '', $name)) . '.com',
                'logo' => 'platforms/' . $file,
                'is_active' => true,
            ]);

            $response = $this->get($platform->logo_url);
            $response->assertOk();
            $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
        }
    }

    public function test_public_media_blocks_dotfiles_executable_extensions_and_traversal(): void
    {
        $this->get('/media?file=/.gitignore')->assertNotFound();
        $this->get('/media?file=/../.env')->assertNotFound();
        $this->get('/media?file=/platforms/.htaccess')->assertNotFound();
        $this->get('/media?file=/platforms/test.php')->assertNotFound();
        $this->get('/media/.gitignore')->assertNotFound();
        $this->get('/media/platforms/test.php')->assertNotFound();
    }
}
