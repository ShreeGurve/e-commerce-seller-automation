<?php
namespace App\Http\Controllers;
use App\Models\{Keyword,Marketplace,Product};use Illuminate\Http\Request;
class KeywordController extends Controller {
 public function index(Request $r){$keywords=Keyword::with(['product','marketplace'])->when($r->q,fn($q,$s)=>$q->where('keyword','like',"%$s%"))->when($r->product_id,fn($q,$v)=>$q->where('product_id',$v))->when($r->keyword_type,fn($q,$v)=>$q->where('keyword_type',$v))->latest()->paginate(30)->withQueryString();return view('keywords.index',['keywords'=>$keywords,'products'=>Product::orderBy('name')->get(),'marketplaces'=>Marketplace::active()->orderBy('name')->get()]);}
 public function store(Request $r){$d=$r->validate(['product_id'=>'nullable|exists:products,id','marketplace_id'=>'nullable|exists:marketplaces,id','keyword'=>'required|string|max:255','keyword_type'=>'required|in:primary,secondary,long-tail,backend,AEO,SEO,GEO','search_volume'=>'nullable|integer|min:0','competition'=>'nullable|string|max:30','relevance'=>'nullable|integer|between:0,100','ranking'=>'nullable|integer|min:1','source'=>'nullable|string|max:100','group_name'=>'nullable|string|max:120']);Keyword::create($d);return back()->with('success','Keyword saved.');}
 public function destroy(Keyword $keyword){$keyword->delete();return back()->with('success','Keyword removed.');}
}
