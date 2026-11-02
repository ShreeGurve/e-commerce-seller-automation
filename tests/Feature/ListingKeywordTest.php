<?php
namespace Tests\Feature;

use App\Marketplaces\Amazon\AmazonAdapter;
use App\Models\{DataImport, Keyword, KeywordGroup, KeywordRanking, Listing, ListingScoreRule, Marketplace, Product, User};
use App\Services\{ImportProcessor, ListingScoreService, ListingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ListingKeywordTest extends TestCase
{
    use RefreshDatabase;

    private User $u; private Product $p; private Marketplace $mp;

    protected function setUp(): void
    {
        // SAFETY: RefreshDatabase wipes the database. Only run on in-memory SQLite.
        if ((getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '')) !== ':memory:') {
            $this->markTestSkipped('Skipped: use DB_CONNECTION=sqlite + DB_DATABASE=:memory: (never your real MySQL).');
        }
        parent::setUp();
        $this->u = User::forceCreate(['name' => 'T', 'email' => 't@test.local', 'password' => 'x']);
        $this->mp = Marketplace::create(['code' => 'amazon', 'name' => 'Amazon', 'adapter_class' => AmazonAdapter::class]);
        $this->p = Product::create(['sku' => 'SKU1', 'name' => 'Steel Bottle']);
    }

    private function save(array $over = [], ?string $note = null): Listing
    {
        return app(ListingService::class)->save($this->p, $this->mp, $over + ['title' => 'Steel Water Bottle 1 Litre', 'bullets' => ['Keeps drinks cold'], 'description' => 'A bottle.', 'image_count' => 3], $this->u, $note);
    }

    public function test_every_save_is_a_new_version_and_identical_saves_are_refused(): void
    {
        $l = $this->save([], 'first');
        $this->assertSame(1, $l->current->version_no);
        $this->assertGreaterThan(0, (float) $l->current->score);

        $l2 = $this->save(['title' => 'Steel Water Bottle 1 Litre Insulated']);
        $this->assertSame([2, $l->id], [$l2->current->version_no, $l2->id]);                  // same listing, new version
        try { $this->save(['title' => 'Steel Water Bottle 1 Litre Insulated']); $this->fail('identical save must be refused'); } catch (ValidationException) {}

        $l3 = app(ListingService::class)->restore($l2, $l2->versions()->where('version_no', 1)->first(), $this->u);
        $this->assertSame('Steel Water Bottle 1 Litre', $l3->current->title);                  // restored copy is a NEW version, history is kept
        $this->assertSame(3, $l3->versions()->count());

        app(ListingService::class)->publish($l3, $l3->versions()->where('version_no', 2)->first());
        $this->assertSame(['LIVE', 'Steel Water Bottle 1 Litre Insulated'], [$l3->fresh()->status, $l3->fresh()->published->title]);
    }

    public function test_score_uses_the_products_saved_keywords_and_configurable_rules(): void
    {
        $svc = app(ListingScoreService::class);
        $fields = ['title' => 'Steel Water Bottle 1 Litre', 'bullets' => ['x']];
        Keyword::create(['product_id' => $this->p->id, 'marketplace_id' => $this->mp->id, 'keyword' => 'steel water bottle', 'key_hash' => Keyword::hash('steel water bottle'), 'keyword_type' => 'PRIMARY']);
        $with = $svc->score($this->p->id, $this->mp->id, $fields);
        $without = $svc->score($this->p->id, $this->mp->id, ['title' => 'Plain Flask 1 Litre'] + $fields);
        $this->assertGreaterThan($without['total'], $with['total']);
        $this->assertContains('Keyword coverage', array_column($with['breakdown'], 'label'));

        ListingScoreRule::where('criterion', 'backend')->update(['is_active' => false]);       // switch a criterion off
        $this->assertArrayNotHasKey('backend', $svc->score($this->p->id, $this->mp->id, $fields)['breakdown']);
        ListingScoreRule::where('criterion', 'title')->update(['max_points' => 40]);           // re-weight another
        $this->assertSame(40.0, (float) $svc->score($this->p->id, $this->mp->id, $fields)['breakdown']['title']['max']);
    }

    private function import(string $csv): DataImport
    {
        Storage::fake('local'); Storage::disk('local')->put('imports/k.csv', $csv);
        return DataImport::create(['marketplace_id' => $this->mp->id, 'data_type' => 'KEYWORDS', 'file_name' => 'k.csv', 'stored_path' => 'imports/k.csv', 'file_hash' => str_repeat('d', 64),
            'status' => 'MAPPED', 'uploaded_by' => $this->u->id, 'options' => ['date_format' => 'auto'],
            'mapping' => ['sku' => 'sku', 'keyword' => 'keyword', 'keyword_type' => 'type', 'search_volume' => 'vol', 'competition' => 'comp', 'rank' => 'rank', 'group' => 'grp']]);
    }

    public function test_keyword_import_creates_updates_and_never_duplicates(): void
    {
        $h = "sku,keyword,type,vol,comp,rank,grp\n";
        $csv = $h . "SKU1,steel water bottle,Primary,5000,High,12,core\nSKU1,insulated flask,long tail,800,medium,,core\nSKU1,bad type,weird,1,low,,\nNOPE,x,primary,1,low,,\n";
        $imp = $this->import($csv);
        $s = app(ImportProcessor::class)->run($imp, true);
        $this->assertSame([2, 2], [$s['ok'], $s['error']]);                                    // bad type + unknown SKU are reported, not guessed
        $this->assertSame(2, Keyword::count());
        $k = Keyword::where('keyword', 'steel water bottle')->first();
        $this->assertSame(['PRIMARY', 5000, 'HIGH', 12], [$k->keyword_type, $k->search_volume, $k->competition, $k->current_rank]);
        $this->assertSame(1, KeywordRanking::count());
        $this->assertSame('LONG_TAIL', Keyword::where('keyword', 'insulated flask')->value('keyword_type'));
        $this->assertSame(1, KeywordGroup::count());

        $imp->update(['status' => 'MAPPED']);                                                  // same file again
        $s2 = app(ImportProcessor::class)->run($imp, true);
        $this->assertSame(2, $s2['duplicate']);
        $this->assertSame([2, 1], [Keyword::count(), KeywordRanking::count()]);

        $upd = $this->import($h . "SKU1,steel water bottle,Primary,9000,High,12,core\n");     // only the volume changed
        $s3 = app(ImportProcessor::class)->run($upd, true);
        $this->assertSame([1, 0], [$s3['created'], $s3['duplicate']]);
        $this->assertSame(9000, $k->fresh()->search_volume);
    }
}
