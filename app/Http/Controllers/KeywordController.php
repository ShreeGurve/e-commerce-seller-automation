<?php
namespace App\Http\Controllers;

use App\Models\{Keyword, KeywordGroup, KeywordRanking, Marketplace, Product};
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KeywordController extends Controller
{
    public function index(Request $request)
    {
        $sorts = ['keyword' => 'keywords.keyword ASC', 'volume' => 'keywords.search_volume DESC', 'rank' => 'CASE WHEN keywords.current_rank IS NULL THEN 1 ELSE 0 END, keywords.current_rank ASC', 'new' => 'keywords.id DESC'];
        $rows = Keyword::with(['product:id,sku', 'marketplace:id,name', 'group:id,name'])
            ->when($request->product_id, fn ($q, $v) => $q->where('product_id', $v))->when($request->marketplace_id, fn ($q, $v) => $q->where('marketplace_id', $v))
            ->when($request->type, fn ($q, $v) => $q->where('keyword_type', $v))->when($request->group_id, fn ($q, $v) => $q->where('group_id', $v))
            ->when($request->q, fn ($q, $s) => $q->where('keyword', 'like', "%$s%"))->orderByRaw($sorts[$request->sort ?? 'new'] ?? $sorts['new'])->paginate(40)->withQueryString();
        return view('keywords.index', ['rows' => $rows, 'products' => Product::orderBy('sku')->get(['id', 'sku', 'name']), 'marketplaces' => Marketplace::active()->get(), 'groups' => KeywordGroup::orderBy('name')->get(),
            'counts' => Keyword::select('keyword_type', DB::raw('count(*) c'))->groupBy('keyword_type')->pluck('c', 'keyword_type')]);
    }

    /** One keyword per line, so a whole research list goes in at once. Existing keywords are skipped, never duplicated. */
    public function store(Request $request)
    {
        $d = $request->validate(['product_id' => 'required|exists:products,id', 'marketplace_id' => 'required|exists:marketplaces,id', 'keywords' => 'required|string|max:20000',
            'keyword_type' => ['required', Rule::in(array_keys(Keyword::TYPES))], 'search_volume' => 'nullable|integer|min:0|max:2000000000', 'competition' => ['nullable', Rule::in(array_keys(Keyword::COMPETITION))],
            'relevance' => 'nullable|integer|min:0|max:100', 'source' => 'nullable|string|max:40', 'group_name' => 'nullable|string|max:255']);
        $gid = ! empty($d['group_name']) ? KeywordGroup::firstOrCreate(['name' => trim($d['group_name'])])->id : null;
        $added = 0; $skipped = 0; $seen = [];
        foreach (preg_split('/\r\n|\r|\n/', $d['keywords']) as $line) {
            $kw = trim($line);
            if ($kw === '' || mb_strlen($kw) > 255) { if ($kw !== '') $skipped++; continue; }
            $h = Keyword::hash($kw);
            if (isset($seen[$h]) || Keyword::where(['product_id' => $d['product_id'], 'marketplace_id' => $d['marketplace_id'], 'key_hash' => $h])->exists()) { $skipped++; continue; }
            $seen[$h] = true;
            Keyword::create(['product_id' => $d['product_id'], 'marketplace_id' => $d['marketplace_id'], 'keyword' => $kw, 'key_hash' => $h, 'keyword_type' => $d['keyword_type'],
                'search_volume' => $d['search_volume'] ?? null, 'competition' => $d['competition'] ?? null, 'relevance' => $d['relevance'] ?? null, 'source' => $d['source'] ?? null,
                'group_id' => $gid, 'created_by' => $request->user()->id]);
            $added++;
        }
        if ($added) ActivityLogger::log('keywords.added', null, null, ['count' => $added, 'product_id' => $d['product_id'], 'type' => $d['keyword_type']]);
        return back()->with('success', "{$added} keyword(s) added" . ($skipped ? ", {$skipped} skipped (already exist or too long)." : '.'));
    }

    public function edit(Keyword $keyword)
    {
        $keyword->load(['product', 'marketplace', 'group']);
        return view('keywords.edit', ['k' => $keyword, 'groups' => KeywordGroup::orderBy('name')->get(), 'history' => $keyword->rankings()->limit(60)->get()]);
    }

    public function update(Request $request, Keyword $keyword)
    {
        $d = $request->validate(['keyword_type' => ['required', Rule::in(array_keys(Keyword::TYPES))], 'search_volume' => 'nullable|integer|min:0|max:2000000000',
            'competition' => ['nullable', Rule::in(array_keys(Keyword::COMPETITION))], 'relevance' => 'nullable|integer|min:0|max:100', 'source' => 'nullable|string|max:40',
            'group_name' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:255']);
        $d['group_id'] = ! empty($d['group_name']) ? KeywordGroup::firstOrCreate(['name' => trim($d['group_name'])])->id : null; unset($d['group_name']);
        $keyword->fill($d); $dirty = $keyword->getDirty(); $old = array_intersect_key($keyword->getOriginal(), $dirty); $keyword->save();
        if ($dirty) ActivityLogger::log('keyword.updated', $keyword, $old, $dirty);
        return redirect()->route('keywords.index')->with('success', 'Keyword updated.');
    }

    public function destroy(Keyword $keyword)
    {
        $keyword->delete();
        ActivityLogger::log('keyword.deleted', $keyword, ['keyword' => $keyword->keyword]);
        return redirect()->route('keywords.index')->with('success', 'Keyword deleted.');
    }

    public function rank(Request $request, Keyword $keyword)
    {
        $d = $request->validate(['rank' => 'required|integer|min:1|max:100000', 'checked_on' => 'required|date|before_or_equal:today', 'rank_type' => ['required', Rule::in(['ORGANIC', 'SPONSORED'])], 'source' => 'nullable|string|max:40']);
        KeywordRanking::updateOrCreate(['keyword_id' => $keyword->id, 'checked_on' => $d['checked_on'], 'rank_type' => $d['rank_type']], ['rank' => $d['rank'], 'source' => $d['source'] ?? null, 'created_by' => $request->user()->id]);
        if ($d['rank_type'] === 'ORGANIC' && (! $keyword->rank_checked_on || $keyword->rank_checked_on->toDateString() <= $d['checked_on'])) {
            $keyword->update(['current_rank' => $d['rank'], 'rank_checked_on' => $d['checked_on']]);
        }
        return back()->with('success', 'Ranking saved.');
    }
}
