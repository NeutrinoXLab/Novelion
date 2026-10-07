<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ProductImageUpload;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductPolishTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $data = []): Product
    {
        return Product::create(array_merge([
            'category_id' => Category::create(['name' => 'Test', 'slug' => 'test'])->id,
            'name' => 'Produs test', 'sku' => 'POLISH', 'purchase_price' => 10,
            'selling_price' => 20, 'stock_quantity' => 3, 'is_active' => true,
        ], $data));
    }

    public function test_descriptions_and_safety_are_escaped_readable_and_in_order(): void
    {
        $product = $this->product([
            'short_description' => 'Scurt <script>bad()</script>',
            'description' => "Paragraf unu\nLinie doi\n\nParagraf trei",
            'warnings' => "• Primul avertisment\n• Al doilea <img src=x onerror=bad()>",
            'safety_instructions' => "Instrucțiune unu\nInstrucțiune doi",
        ]);
        $this->get(route('products.show', $product))->assertOk()
            ->assertSeeInOrder(['product-short-description', 'Adaugă în coș', 'product-description-heading', 'product-safety-heading'], false)
            ->assertSee('Scurt &lt;script&gt;bad()&lt;/script&gt;', false)
            ->assertSee("<p>Paragraf unu<br />\nLinie doi</p>", false)
            ->assertSee('<p>Paragraf trei</p>', false)
            ->assertSee('• Al doilea &lt;img src=x onerror=bad()&gt;', false)
            ->assertSee('draggable="false"', false);
    }

    public function test_empty_short_description_is_omitted_and_contact_only_safety_remains_visible(): void
    {
        $product = $this->product(['short_description' => " \n ", 'manufacturer_contact' => 'Contact real']);
        $this->get(route('products.show', $product))->assertOk()
            ->assertDontSee('product-short-description', false)
            ->assertSee('product-safety-heading', false)->assertSeeText('Contact real');
    }

    public function test_rich_description_keeps_formatting_but_removes_unsafe_attributes(): void
    {
        $product = $this->product(['description' => '<p>Prima</p><p></p><p><strong>A doua</strong></p><ul><li>Detaliu</li></ul><img src=x onerror="alert(1)"><script>alert(2)</script>']);
        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('<strong>A doua</strong>', false)->assertSee('<li>Detaliu</li>', false)
            ->assertDontSee('onerror', false)->assertDontSee('alert(2)', false);
    }

    public static function dimensions(): array
    {
        return [[800, 800], [600, 1000], [1200, 600], [2400, 1200]];
    }

    #[DataProvider('dimensions')]
    public function test_upload_preserves_private_original_and_brands_only_corner(int $w, int $h): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $file = UploadedFile::fake()->image('original.png', $w, $h);
        $original = file_get_contents($file->getRealPath());
        $path = app(ProductImageUpload::class)->store($file);
        $uuid = pathinfo($path, PATHINFO_FILENAME);
        $this->assertSame($original, Storage::disk('local')->get("product-originals/{$uuid}.source"));
        $this->assertSame([$path], Storage::disk('public')->allFiles());
        $img = imagecreatefromstring(Storage::disk('public')->get($path));
        $scale = min(1, 2000 / max($w, $h));
        $this->assertSame((int) round($w * $scale), imagesx($img));
        $this->assertSame((int) round($h * $scale), imagesy($img));
        $center = imagecolorsforindex($img, imagecolorat($img, (int) (imagesx($img) / 2), (int) (imagesy($img) / 2)));
        $this->assertLessThan(8, $center['red'] + $center['green'] + $center['blue']);
        $edge = (int) round(min(imagesx($img), imagesy($img)) * .12);
        $pad = (int) round(min(imagesx($img), imagesy($img)) * .025);
        $changed = 0;
        for ($y = imagesy($img) - $edge - $pad; $y < imagesy($img) - $pad; $y++) {
            for ($x = imagesx($img) - $edge - $pad; $x < imagesx($img) - $pad; $x++) {
                $pixel = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                if ($pixel['red'] + $pixel['green'] + $pixel['blue'] > 20) {
                    $changed++;
                }
            }
        }
        $this->assertGreaterThan(20, $changed);
    }

    public function test_transparency_and_jpeg_webp_inputs_are_supported(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        foreach (['jpeg', 'webp'] as $extension) {
            $path = app(ProductImageUpload::class)->store(UploadedFile::fake()->image("input.{$extension}", 600, 400));
            $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($path))['mime']);
        }
        $img = imagecreatetruecolor(600, 400);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        $path = app(ProductImageUpload::class)->store(UploadedFile::fake()->createWithContent('transparent.png', $bytes));
        $result = imagecreatefromstring(Storage::disk('public')->get($path));
        $pixel = imagecolorsforindex($result, imagecolorat($result, 0, 0));
        $this->assertSame(127, $pixel['alpha']);
    }

    public function test_failed_public_write_cleans_only_new_upload_and_preserves_existing_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('product-originals/old.source', 'old-original');
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturnFalse();
        $disk->shouldReceive('delete')->once()->with(\Mockery::on(fn ($path) => str_starts_with($path, 'products/branded/')))->andReturnTrue();
        Storage::set('public', $disk);
        $this->expectException(ValidationException::class);
        try {
            app(ProductImageUpload::class)->store(UploadedFile::fake()->image('input.png', 600, 400));
        } finally {
            $this->assertSame(['product-originals/old.source'], Storage::disk('local')->allFiles());
            $this->assertSame('old-original', Storage::disk('local')->get('product-originals/old.source'));
        }
    }

    public function test_invalid_upload_does_not_write_any_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->expectException(ValidationException::class);
        try {
            app(ProductImageUpload::class)->store(UploadedFile::fake()->createWithContent('bad.png', '<svg onload="bad()"></svg>'));
        } finally {
            $this->assertSame([], Storage::disk('local')->allFiles());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_admin_upload_and_replacement_use_derivatives_and_retain_originals(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $product = $this->product();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: ['image_path' => UploadedFile::fake()->image('first.png', 600, 600), 'is_primary' => true, 'sort_order' => 0])
            ->assertHasNoTableActionErrors();
        $image = ProductImage::sole();
        $first = $image->image_path;
        $this->assertStringStartsWith('products/branded/', $first);
        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->mountTableAction('edit', $image)
            ->setTableActionData(['image_path' => []])
            ->setTableActionData(['image_path' => UploadedFile::fake()->image('second.png', 500, 800)])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();
        $this->assertNotSame($first, $image->fresh()->image_path);
        $this->assertCount(2, Storage::disk('local')->allFiles('product-originals'));
        Storage::disk('public')->assertExists($first);
        $this->get(route('products.show', $product))->assertOk()->assertSee($image->fresh()->image_path, false);
        $image->delete();
        $this->assertCount(2, Storage::disk('local')->allFiles('product-originals'));
    }
}
