<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CatalogExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_excel_template_downloads_as_a_workbook(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->get(route('catalog.excel-template'))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
    }

    public function test_the_excel_export_carries_the_catalog_rows(): void
    {
        $user = $this->adminFor();

        $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Ball Valve',
            'item_code' => 'BV-1',
            'rate_type' => 'per_piece',
            'default_rate' => 750,
            'status' => 'active',
            'created_by' => $user->id,
        ]));

        $sheet = $this->loadWorkbook(
            $this->actingAs($user)->get(route('catalog.excel-export'))->streamedContent(),
        );

        $rows = $sheet->getActiveSheet()->toArray(null, true, false, false);

        $this->assertSame('name', $rows[0][0]);
        $this->assertSame('Ball Valve', $rows[1][0]);
        $this->assertSame('BV-1', $rows[1][1]);
    }

    public function test_uploading_a_workbook_opens_an_editable_preview(): void
    {
        $user = $this->adminFor();

        $token = $this->uploadPreview($user, [
            ['name', 'item_code', 'default_rate'],
            ['Ball Valve', 'BV-1', '750'],
            ['Gate Valve', 'GV-1', '650'],
        ]);

        $this->actingAs($user)
            ->get(route('catalog.excel-preview', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('catalog/excel-preview')
                ->where('rows', fn ($rows) => count($rows) === 2)
                ->where('rows.0.name', 'Ball Valve')
                ->where('rows.1.item_code', 'GV-1'));

        // Preview only — nothing is written until the owner confirms.
        $this->assertSame(0, CatalogItem::count());
    }

    public function test_preview_rows_can_be_added_and_edited_before_import(): void
    {
        $user = $this->adminFor();

        $existing = $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Old name',
            'item_code' => 'BV-1',
            'rate_type' => 'per_piece',
            'default_rate' => 100,
            'status' => 'active',
            'created_by' => $user->id,
        ]));

        $token = $this->uploadPreview($user, [
            ['name', 'item_code', 'default_rate'],
            ['Old name', 'BV-1', '100'],
        ]);

        // The owner edits one row and adds another in the preview grid.
        $this->actingAs($user)
            ->post(route('catalog.excel-import'), [
                'token' => $token,
                'rows' => [
                    ['name' => 'Renamed', 'item_code' => 'BV-1', 'default_rate' => '999'],
                    ['name' => 'New Valve', 'item_code' => 'NV-1', 'default_rate' => '500'],
                    ['name' => '', 'item_code' => 'SKIP-1'],
                ],
            ])
            ->assertRedirect(route('catalog.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, CatalogItem::count());
        $this->assertSame('Renamed', $existing->refresh()->name);
        $this->assertEquals(999, $existing->default_rate);
        $this->assertDatabaseHas('catalog_items', ['item_code' => 'NV-1']);
        $this->assertDatabaseMissing('catalog_items', ['item_code' => 'SKIP-1']);
    }

    public function test_an_empty_name_row_is_skipped_and_reported(): void
    {
        $user = $this->adminFor();

        $token = $this->uploadPreview($user, [
            ['name', 'default_rate'],
            ['', '750'],
            ['Ball Valve', '750'],
        ]);

        $this->actingAs($user)
            ->post(route('catalog.excel-import'), [
                'token' => $token,
                'rows' => [
                    ['name' => '', 'default_rate' => '750'],
                    ['name' => 'Ball Valve', 'default_rate' => '750'],
                ],
            ])
            ->assertRedirect(route('catalog.index'))
            ->assertSessionHas('toast');

        $this->assertSame(1, CatalogItem::count());
    }

    public function test_an_expired_preview_asks_for_the_file_again(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->get(route('catalog.excel-preview', ['token' => 'expired']))
            ->assertRedirect(route('catalog.index'));
    }

    public function test_a_workbook_without_a_name_column_is_rejected(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.excel-preview-upload'), [
                'file' => $this->workbook([
                    ['brand', 'default_rate'],
                    ['Jindal', '750'],
                ]),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_a_php_file_is_rejected(): void
    {
        $user = $this->adminFor();

        $this->actingAs($user)
            ->post(route('catalog.excel-preview-upload'), [
                'file' => UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_the_exported_workbook_round_trips_without_duplicating(): void
    {
        $user = $this->adminFor();

        $this->inTenant($user, fn () => CatalogItem::create([
            'name' => 'Ball Valve',
            'item_code' => 'BV-1',
            'rate_type' => 'per_piece',
            'default_rate' => 750,
            'status' => 'active',
            'created_by' => $user->id,
        ]));

        $exported = $this->actingAs($user)
            ->get(route('catalog.excel-export'))
            ->streamedContent();

        $rows = $this->loadWorkbook($exported)
            ->getActiveSheet()
            ->toArray(null, true, false, false);

        $header = array_shift($rows);
        $index = array_flip($header);

        $payload = array_map(fn (array $row) => array_filter([
            'name' => $row[$index['name']] ?? null,
            'item_code' => $row[$index['item_code']] ?? null,
            'default_rate' => $row[$index['default_rate']] ?? null,
        ], fn ($value) => $value !== null), $rows);

        $this->actingAs($user)
            ->post(route('catalog.excel-import'), [
                'token' => 'round-trip',
                'rows' => $payload,
            ])
            ->assertRedirect(route('catalog.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CatalogItem::count());
    }

    public function test_excel_import_requires_write_permission(): void
    {
        $admin = $this->adminFor();
        $viewer = $this->inTenant($admin, fn () => User::create([
            'tenant_id' => $admin->tenant_id,
            'name' => 'Viewer',
            'email' => 'excel-viewer@example.test',
            'password' => bcrypt('secret1234'),
            'role' => 'viewer',
            'is_active' => true,
        ]));

        $this->actingAs($viewer)
            ->post(route('catalog.excel-preview-upload'), [
                'file' => $this->workbook([
                    ['name'],
                    ['Ball Valve'],
                ]),
            ])
            ->assertForbidden();
    }

    /**
     * Upload a workbook and return the preview token it opens with.
     *
     * @param  array<int,array<int,string>>  $rows
     */
    protected function uploadPreview($user, array $rows): string
    {
        $response = $this->actingAs($user)
            ->post(route('catalog.excel-preview-upload'), ['file' => $this->workbook($rows)])
            ->assertRedirect();

        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertNotEmpty($query['token'] ?? null);

        return $query['token'];
    }

    /**
     * @param  array<int,array<int,string>>  $rows
     */
    protected function workbook(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'catalog.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    protected function loadWorkbook(string $contents): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($path, $contents);

        return IOFactory::load($path);
    }
}
