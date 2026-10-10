@php $q = request()->only(['from','to','marketplace_id']); @endphp
<ul class="nav nav-tabs mb-3">
  @foreach([['Overview','ads.index',[],'ads'],['Keywords','ads.targets',['level'=>'keywords'],'ads/keywords'],['Search terms','ads.targets',['level'=>'terms'],'ads/terms'],['Insights','ads.insights',[],'ads/insights']] as [$l,$r,$p,$pat])
    <li class="nav-item"><a class="nav-link {{ request()->is($pat) ? 'active' : '' }}" href="{{ route($r, $p + $q) }}">{{ $l }}</a></li>
  @endforeach
  @can('advertising.manage')<li class="nav-item ms-auto"><a class="btn btn-sm btn-primary" href="{{ route('imports.create',['type'=>'ADS_PRODUCT']) }}"><i class="bi bi-cloud-upload"></i> Import ad report</a></li>@endcan
</ul>
