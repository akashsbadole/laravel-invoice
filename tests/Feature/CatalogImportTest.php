<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    protected function csv(string $contents, string $name = 'catalog.csv', ?string $mime = null): UploadedFile
    {
        return $mime
            ? UploadedFile::fake()->create($name, 8, $mime)
            : UploadedFile::fake()->createWithContent($name, $contents);
    }

    public function test_a_simple_csv_is_imported(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->csv("name,item_code,brand,rate_type,default_rate\nBall Valve,BV-1,Jindal,per_piece,750\n"),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Ball Valve',
            'item_code' => 'BV-1',
            'brand' => 'Jindal',
            'default_rate' => 750,
        ]);
    }

    /**
     * Excel writes a UTF-8 BOM before the first header cell, which turns
     * "name" into an unrecognised key and fails the import outright.
     */
    public function test_an_excel_csv_with_a_byte_order_mark_is_imported(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->csv("\xEF\xBB\xBFname,default_rate\nBall Valve,750\n"),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('catalog_items', ['name' => 'Ball Valve']);
    }

    /**
     * A hand-written CSV rarely carries every column, and the importer must
     * not assume the optional ones are present.
     */
    public function test_a_csv_with_only_some_columns_is_imported(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->csv("name,default_rate\nBall Valve,750\n"),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Ball Valve',
            'default_rate' => 750,
        ]);
    }

    public function test_header_names_are_matched_case_insensitively_and_with_spaces(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->csv("Name,Default Rate,Item Code\nBall Valve,750,BV-1\n"),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('catalog_items', [
            'name' => 'Ball Valve',
            'item_code' => 'BV-1',
        ]);
    }

    public function test_a_matching_item_code_updates_the_existing_row(): void
    {
        $user = $this->adminFor();

        $item = $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Old name',
            'item_code' => 'BV-1',
            'rate_type' => 'per_piece',
            'default_rate' => 100,
            'is_active' => true,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->csv("name,item_code,default_rate\nNew name,BV-1,999\n"),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();

        $this->assertSame('New name', $item->name);
        $this->assertEquals(999, $item->default_rate);
        $this->assertSame(1, CatalogItem::count());
    }

    public function test_rows_without_a_name_are_skipped(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->csv("name,default_rate\nBall Valve,750\n,900\n\n"),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CatalogItem::count());
    }

    public function test_a_csv_without_a_name_column_is_rejected_with_a_clear_message(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->csv("brand,default_rate\nJindal,750\n"),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->assertSame(0, CatalogItem::count());
    }

    public function test_an_unknown_rate_type_falls_back_to_the_industry_default(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->csv("name,rate_type,default_rate\nBall Valve,per_furlong,750\n"),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = CatalogItem::firstOrFail();

        $this->assertNotSame('per_furlong', $item->rate_type->value);
    }

    public function test_a_csv_uploaded_from_excel_is_accepted(): void
    {
        $user = $this->adminFor();

        // Excel commonly reports application/vnd.ms-excel (or a generic
        // binary type) for a .csv, which a strict `mimes:csv` rule rejects.
        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->realCsv('name,default_rate' . "\n" . 'Ball Valve,750' . "\n", 'application/vnd.ms-excel'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('catalog_items', ['name' => 'Ball Valve']);
    }

public function test_a_php_upload_is_rejected(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->realCsv('name' . "\n" . 'Ball Valve' . "\n", 'text/plain', 'evil.php'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('file');
    }

public function test_an_empty_file_is_reported_clearly(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->csv(''),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('file');
    }

public function test_the_is_active_column_can_deactivate_a_product(): void
    {
        $user = $this->adminFor();

        $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Ball Valve',
            'item_code' => 'BV-1',
            'rate_type' => 'per_piece',
            'is_active' => true,
            'created_by' => $user->id,
        ]));

        $this->actingAs($user)
            ->post(route('catalog.upload'), [
                'file' => $this->csv("name,item_code,is_active\nBall Valve,BV-1,0\n"),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse(CatalogItem::firstOrFail()->is_active);
    }

protected function realCsv(string $contents, string $mime, string $filename = 'catalog.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $filename, $mime, null, true);
    }

    public function test_the_exported_catalog_can_be_imported_again(): void
    {
        $user = $this->adminFor();

        $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Ball Valve',
            'item_code' => 'BV-1',
            'brand' => 'Jindal',
            'rate_type' => 'per_piece',
            'default_rate' => 750,
            'is_active' => true,
            'created_by' => $user->id,
        ]));

        $response = $this->actingAs($user)->get(route('catalog.export'));
        $csv = $response->streamedContent();

        $this->actingAs($user)
            ->post(route('catalog.upload'), ['file' => $this->csv($csv, 'roundtrip.csv')])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Same item_code, so it updates rather than duplicating.
        $this->assertSame(1, CatalogItem::count());
    }

    public function test_importing_requires_write_permission(): void
    {
        $admin = $this->adminFor();
        $viewer = $this->inTenant($admin, fn () => \App\Models\User::create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Viewer',
            'email' => 'viewer@example.test',
            'password' => bcrypt('secret1234'),
            'role' => 'viewer',
            'is_active' => true,
        ]));

        $this->actingAs($viewer)
            ->post(route('catalog.upload'), [
                'file' => $this->csv("name\nBall Valve\n"),
            ])
            ->assertForbidden();
    }
}