<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Dashboard') · SHREE GURVE</title>
  <script>
    /* apply saved theme + sidebar state before paint (no flicker) */
    try{
      if(localStorage.sgTheme==='dark')document.documentElement.setAttribute('data-bs-theme','dark');
      if(localStorage.sgSide==='1'&&matchMedia('(min-width:992px)').matches)document.documentElement.classList.add('sg-collapsed');
    }catch(e){}
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    :root{--sg-primary:#3874ff;--sg-bg:#eff2f6;--sg-card:#fff;--sg-border:#e3e6ed;--sg-text:#31374a;--sg-muted:#6e7891;
          --sg-nav-h:64px;--sg-side-w:250px;--bs-primary:#3874ff;--bs-primary-rgb:56,116,255;--bs-link-color:#3874ff;--bs-link-hover-color:#2c60d6;
          --bs-link-color-rgb:56,116,255;--bs-body-font-family:'Nunito Sans',system-ui,-apple-system,'Segoe UI',sans-serif}
    [data-bs-theme=dark]{--sg-bg:#0f1524;--sg-card:#161d31;--sg-border:#27304a;--sg-text:#d8def0;--sg-muted:#8f9bb8}
    body{background:var(--sg-bg);color:var(--sg-text);font-size:.92rem}
    h1,h2,h3,h4,h5,h6{color:var(--sg-text)}
    .text-muted{color:var(--sg-muted)!important}
    /* buttons / forms */
    .btn-primary{--bs-btn-bg:#3874ff;--bs-btn-border-color:#3874ff;--bs-btn-hover-bg:#2c60d6;--bs-btn-hover-border-color:#2c60d6;--bs-btn-active-bg:#2754bd;--bs-btn-active-border-color:#2754bd;--bs-btn-disabled-bg:#3874ff;--bs-btn-disabled-border-color:#3874ff}
    .btn-outline-primary{--bs-btn-color:#3874ff;--bs-btn-border-color:#3874ff;--bs-btn-hover-bg:#3874ff;--bs-btn-hover-border-color:#3874ff;--bs-btn-active-bg:#2c60d6;--bs-btn-active-border-color:#2c60d6}
    .btn{font-weight:600;border-radius:.5rem}
    .form-control,.form-select{border-color:var(--sg-border);font-size:.9rem;border-radius:.5rem}
    .form-control:focus,.form-select:focus{border-color:#3874ff;box-shadow:0 0 0 .2rem rgba(56,116,255,.15)}
    .nav-tabs{border-color:var(--sg-border)}
    .nav-tabs .nav-link{color:var(--sg-muted);font-weight:700;border:0;border-bottom:2px solid transparent}
    .nav-tabs .nav-link.active{color:#3874ff;background:transparent;border-bottom-color:#3874ff}
    /* cards / tables */
    .card{background:var(--sg-card);border:1px solid var(--sg-border);border-radius:.65rem;box-shadow:none}
    .card-header{background:transparent!important;border-bottom:1px solid var(--sg-border);font-weight:700}
    .card-footer{background:transparent!important;border-top:1px solid var(--sg-border)}
    [data-bs-theme=dark] .bg-white{background:var(--sg-card)!important}
    .table{--bs-table-bg:transparent;--bs-table-border-color:var(--sg-border)}
    .table thead th,.table-light{--bs-table-bg:transparent;--bs-table-color:var(--sg-muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700}
    .list-group-item{background:transparent;border-color:var(--sg-border);color:var(--sg-text)}
    /* topbar */
    .sg-top{position:fixed;top:0;left:0;right:0;height:var(--sg-nav-h);background:var(--sg-card);border-bottom:1px solid var(--sg-border);z-index:1030;display:flex;align-items:center;gap:.5rem;padding:0 1rem}
    .sg-logo{display:flex;align-items:center;gap:.5rem;font-weight:800;font-size:1.2rem;color:var(--sg-text);text-decoration:none;letter-spacing:.3px;white-space:nowrap}
    .sg-logo i{color:#3874ff;font-size:1.5rem}
    .sg-iconbtn{width:38px;height:38px;border-radius:50%;border:0;background:transparent;color:var(--sg-muted);display:inline-grid;place-items:center;font-size:1.1rem;text-decoration:none}
    .sg-iconbtn:hover{background:rgba(56,116,255,.1);color:#3874ff}
    .sg-topnav{position:absolute;left:50%;transform:translateX(-50%);display:none;gap:.25rem}
    .sg-topnav>a,.sg-topnav .dropdown>a{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .85rem;border-radius:.5rem;color:var(--sg-text);text-decoration:none;font-weight:600;font-size:.88rem}
    .sg-topnav a:hover,.sg-topnav a.active{color:#3874ff;background:rgba(56,116,255,.08)}
    @media(min-width:1200px){.sg-topnav{display:flex}}
    .sg-search{position:relative;display:none}
    .sg-search input{border-radius:2rem;padding-left:2.2rem;width:220px;background:var(--sg-bg);border-color:transparent}
    .sg-search i{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:var(--sg-muted)}
    @media(min-width:768px){.sg-search{display:block}.sg-searchicon{display:none!important}}
    .sg-avatar{width:36px;height:36px;border-radius:50%;background:#3874ff;color:#fff;font-weight:800;display:grid;place-items:center;border:0}
    .sg-appgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:.25rem;width:280px;padding:.5rem}
    .sg-appgrid a{display:flex;flex-direction:column;align-items:center;gap:.25rem;padding:.6rem .2rem;border-radius:.5rem;color:var(--sg-text);text-decoration:none;font-size:.74rem;text-align:center}
    .sg-appgrid a:hover{background:rgba(56,116,255,.08);color:#3874ff}
    .sg-appgrid i{font-size:1.3rem;color:#3874ff}
    /* sidebar */
    .sg-side{position:fixed;top:var(--sg-nav-h);bottom:0;left:0;width:var(--sg-side-w);background:var(--sg-card);border-right:1px solid var(--sg-border);display:flex;flex-direction:column;z-index:1020;transition:width .2s ease,transform .25s ease}
    .sg-side-scroll{flex:1;overflow-y:auto;padding:.5rem 0}
    .sg-sec{font-size:.68rem;font-weight:800;letter-spacing:.08em;color:var(--sg-muted);padding:1rem 1.5rem .3rem;text-transform:uppercase;white-space:nowrap}
    .sg-link{display:flex;align-items:center;gap:.75rem;padding:.5rem 1.5rem;color:var(--sg-text);text-decoration:none;font-size:.9rem;font-weight:600;white-space:nowrap;border-left:3px solid transparent}
    .sg-link i{font-size:1.05rem;width:1.25rem;text-align:center;color:var(--sg-muted)}
    .sg-link:hover{background:rgba(56,116,255,.06)}
    .sg-link.active{color:#3874ff;background:rgba(56,116,255,.09);border-left-color:#3874ff}
    .sg-link.active i{color:#3874ff}
    .sg-soon{margin-left:auto;font-size:.6rem;font-weight:700;padding:.1rem .4rem;border-radius:1rem;background:rgba(110,120,145,.15);color:var(--sg-muted)}
    .sg-side-foot{border-top:1px solid var(--sg-border);padding:.5rem 0}
    .sg-side-foot button{all:unset;cursor:pointer;display:flex;align-items:center;gap:.75rem;padding:.55rem 1.5rem;color:var(--sg-text);font-weight:600;width:100%;box-sizing:border-box;white-space:nowrap}
    .sg-side-foot button:hover{color:#3874ff}
    .sg-backdrop{display:none;position:fixed;inset:var(--sg-nav-h) 0 0 0;background:rgba(15,21,36,.5);z-index:1015}
    /* main */
    .sg-main{margin-left:var(--sg-side-w);padding-top:var(--sg-nav-h);min-height:100vh;display:flex;flex-direction:column;transition:margin .2s ease}
    .sg-main>main{flex:1;padding:1.75rem 1.75rem 1rem}
    .sg-page-head{margin-bottom:1.5rem}
    .sg-page-head h1{font-size:1.9rem;font-weight:800;margin:0;line-height:1.2;word-break:break-word}
    .sg-page-head p{margin:.25rem 0 0;color:var(--sg-muted);font-size:1.02rem}
    .sg-footer{padding:.8rem 1.75rem;font-size:.82rem;color:var(--sg-muted);border-top:1px solid var(--sg-border);background:var(--sg-card)}
    .sg-footer a{color:var(--sg-muted);text-decoration:none}.sg-footer a:hover{color:#3874ff}
    #sgTop{position:fixed;right:1.25rem;bottom:1.25rem;width:40px;height:40px;border-radius:50%;border:0;background:#3874ff;color:#fff;display:none;box-shadow:0 4px 12px rgba(56,116,255,.4);z-index:1010}
    /* dashboard helpers */
    .sg-tile{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;font-size:1.35rem;flex:none}
    .t-green{background:rgba(37,176,3,.14);color:#25b003}.t-orange{background:rgba(245,128,62,.16);color:#f5803e}
    .t-red{background:rgba(237,43,42,.13);color:#ed2b2a}.t-blue{background:rgba(56,116,255,.14);color:#3874ff}
    .sg-bignum{font-size:1.9rem;font-weight:800;line-height:1}
    /* desktop collapsed */
    @media(min-width:992px){
      html.sg-collapsed{--sg-side-w:72px}
      .sg-collapsed .sg-label,.sg-collapsed .sg-sec,.sg-collapsed .sg-soon{display:none}
      .sg-collapsed .sg-link,.sg-collapsed .sg-side-foot button{justify-content:center;padding-left:0;padding-right:0}
      .sg-collapsed .sg-link i{font-size:1.2rem}
    }
    /* tablet + phone: off-canvas sidebar */
    @media(max-width:991.98px){
      .sg-side{transform:translateX(-100%);width:272px;box-shadow:none}
      .sg-open .sg-side{transform:none;box-shadow:6px 0 24px rgba(0,0,0,.18)}
      .sg-open .sg-backdrop{display:block}
      .sg-main{margin-left:0}
      .sg-side-foot{display:none}
      .sg-main>main{padding:1.25rem 1rem .75rem}
      .sg-footer{padding:.8rem 1rem}
    }
    @media(max-width:575.98px){
      .sg-page-head h1{font-size:1.5rem}
      .sg-logo span{display:none}
      .btn-sm,.btn{white-space:nowrap}
      .table-responsive{-webkit-overflow-scrolling:touch}
    }
  </style>
</head>
<body>
@php
  $go = fn($r) => route($r);
  $groups = [
    'Main' => [
      ['Dashboard','bi-speedometer2',route('dashboard'),'dashboard',null,false],
    ],
    'Catalog' => [
      ['Products','bi-box-seam',route('products.index'),'products*','products.view',false],
      ['Categories','bi-tags',route('categories.index'),'categories*','categories.manage',false],
      ['Suppliers','bi-truck',route('suppliers.index'),'suppliers*','purchases.view',false],
      ['Purchases','bi-cart3',route('purchases.index'),'purchases*','purchases.view',false],
      ['Inventory','bi-boxes',route('inventory.index'),'inventory*','inventory.view',false],
    ],
    'Sales' => [
      ['Orders','bi-receipt',route('orders.index'),'orders*','orders.view',false],
      ['Returns','bi-arrow-return-left',route('returns.index'),'returns*','returns.view',false],
      ['Claims','bi-shield-check',route('claims.index'),'claims*','claims.view',false],
      ['Amazon','bi-amazon',route('marketplaces.show','amazon'),'marketplaces/amazon','orders.view',false],
      ['Flipkart','bi-bag-heart',route('marketplaces.show','flipkart'),'marketplaces/flipkart','orders.view',false],
      ['Imports','bi-cloud-upload',route('imports.index'),'imports*','orders.edit',false],
    ],
    'Growth & Finance' => [
      ['Advertising','bi-megaphone',route('ads.index'),'ads*','advertising.view',false],
      ['Profit','bi-graph-up-arrow',route('profit.index'),'finance/profit*','finance.view',false],
      ['Pricing','bi-tag',route('pricing.index'),'pricing*','products.view',false],
      ['Expenses','bi-wallet2',route('expenses.index'),'finance/expenses*','finance.view',false],
      ['Fee rules','bi-percent',route('fees.index'),'finance/fees*','finance.view',false],
      ['Settlements','bi-bank',route('settlements.index'),'finance/settlements*','finance.view',false],
      ['Reconciliation','bi-check2-all',route('reconciliation.index'),'finance/reconciliation*','finance.view',false],
      ['Listings','bi-card-text',route('listings.index'),'listings*','listings.view',false],
      ['Keywords','bi-search',route('keywords.index'),'keywords*','listings.view',false],
      ['Reports','bi-file-earmark-bar-graph',route('module','reports'),'m/reports',null,true],
    ],
    'System' => [
      ['Automation','bi-gear-wide-connected',route('module','automation'),'m/automation',null,true],
      ['Notifications','bi-bell',route('module','notifications'),'m/notifications',null,true],
      ['Approvals','bi-check2-square',route('module','approvals'),'m/approvals',null,true],
      ['Users','bi-people',route('users.index'),'users*','users.manage',false],
      ['Roles & Permissions','bi-shield-lock',route('roles.index'),'roles*','roles.manage',false],
      ['Settings','bi-sliders',route('settings.index'),'settings*','settings.manage',false],
    ],
  ];
  $isActive = fn($p,$soon) => ($soon || str_contains($p,'/')) ? request()->is($p) : request()->routeIs($p);
  $u = auth()->user();
  try { $urgentClaims = $u->can('claims.view') ? \App\Models\Claim::urgentCount() : 0; } catch (\Throwable $e) { $urgentClaims = 0; }   // never break a page because of the alert counter
@endphp

<header class="sg-top">
  <button class="sg-iconbtn" type="button" id="sgBurger" aria-label="Toggle menu"><i class="bi bi-list fs-4"></i></button>
  <a href="{{ route('dashboard') }}" class="sg-logo"><i class="bi bi-graph-up-arrow"></i><span>SHREE GURVE</span></a>

  <nav class="sg-topnav">
    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard')?'active':'' }}"><i class="bi bi-speedometer2"></i>Dashboard</a>
    @can('products.view')<a href="{{ route('products.index') }}" class="{{ request()->routeIs('products*')?'active':'' }}"><i class="bi bi-box-seam"></i>Products</a>@endcan
    @can('categories.manage')<a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories*')?'active':'' }}"><i class="bi bi-tags"></i>Categories</a>@endcan
    <div class="dropdown">
      <a href="#" data-bs-toggle="dropdown"><i class="bi bi-grid"></i>Modules <i class="bi bi-chevron-down small"></i></a>
      <div class="dropdown-menu p-0">
        <div class="sg-appgrid">
          @foreach($groups as $g => $items) @foreach($items as [$l,$i,$url,$p,$perm,$soon]) @if($soon)
            <a href="{{ $url }}"><i class="bi {{ $i }}"></i>{{ $l }}</a>
          @endif @endforeach @endforeach
        </div>
      </div>
    </div>
  </nav>

  <div class="ms-auto d-flex align-items-center gap-1">
    @can('products.view')
    <form class="sg-search" method="GET" action="{{ route('products.index') }}"><i class="bi bi-search"></i>
      <input class="form-control form-control-sm" name="q" placeholder="Search SKU / product" value="{{ request()->routeIs('products.index') ? request('q') : '' }}"></form>
    <a href="{{ route('products.index') }}" class="sg-iconbtn sg-searchicon" aria-label="Search products"><i class="bi bi-search"></i></a>
    @endcan
    <button class="sg-iconbtn" type="button" id="sgTheme" aria-label="Toggle dark mode"><i class="bi bi-sun" id="sgThemeIcon"></i></button>
    <a href="{{ route('module','notifications') }}" class="sg-iconbtn" aria-label="Notifications"><i class="bi bi-bell"></i></a>
    <div class="dropdown d-none d-sm-block">
      <button class="sg-iconbtn" data-bs-toggle="dropdown" aria-label="Quick apps"><i class="bi bi-grid-3x3-gap-fill"></i></button>
      <div class="dropdown-menu dropdown-menu-end p-0"><div class="sg-appgrid">
        <a href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i>Dashboard</a>
        @can('products.view')<a href="{{ route('products.index') }}"><i class="bi bi-box-seam"></i>Products</a>@endcan
        @can('categories.manage')<a href="{{ route('categories.index') }}"><i class="bi bi-tags"></i>Categories</a>@endcan
        @foreach($groups as $items) @foreach($items as [$l,$i,$url,$p,$perm,$soon]) @if($soon)<a href="{{ $url }}"><i class="bi {{ $i }}"></i>{{ $l }}</a>@endif @endforeach @endforeach
      </div></div>
    </div>
    <div class="dropdown ms-1">
      <button class="sg-avatar" data-bs-toggle="dropdown" aria-label="Account">{{ strtoupper(mb_substr($u->name,0,1)) }}</button>
      <ul class="dropdown-menu dropdown-menu-end" style="min-width:220px">
        <li class="px-3 py-2"><div class="fw-bold">{{ $u->name }}</div><div class="small text-muted">{{ $u->email }}</div></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>My profile</a></li>
        <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Logout</button></form></li>
      </ul>
    </div>
  </div>
</header>

<aside class="sg-side" id="sgSide">
  <div class="sg-side-scroll">
    @foreach($groups as $title => $items)
      @php $visible = collect($items)->filter(fn($it) => !$it[4] || $u->can($it[4])); @endphp
      @if($visible->isNotEmpty())
        <div class="sg-sec">{{ $title }}</div>
        @foreach($visible as [$l,$i,$url,$p,$perm,$soon])
          <a href="{{ $url }}" class="sg-link {{ $isActive($p,$soon) ? 'active' : '' }}" title="{{ $l }}">
            <i class="bi {{ $i }}"></i><span class="sg-label">{{ $l }}</span>@if($l==='Claims' && $urgentClaims)<span class="sg-soon" style="background:#ed2b2a;color:#fff" title="Claims at or past their deadline window">{{ $urgentClaims }}</span>@endif @if($soon)<span class="sg-soon">Soon</span>@endif
          </a>
        @endforeach
      @endif
    @endforeach
  </div>
  <div class="sg-side-foot">
    <button type="button" id="sgCollapse"><i class="bi bi-chevron-bar-left" id="sgCollapseIcon"></i><span class="sg-label">Collapsed View</span></button>
  </div>
</aside>
<div class="sg-backdrop" id="sgBackdrop"></div>

<div class="sg-main">
  <main>
    <div class="sg-page-head">
      <h1>@yield('title', 'Dashboard')</h1>
      @hasSection('subtitle')<p>@yield('subtitle')</p>@endif
    </div>
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if(session('error'))<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield('content')
  </main>
  <footer class="sg-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span>&copy; {{ date('Y') }} <strong>SHREE GURVE</strong>. All rights reserved.</span>
    <span><a href="{{ route('dashboard') }}">Dashboard</a><span class="mx-2">&middot;</span><a href="{{ route('products.index') }}">Products</a><span class="mx-2">&middot;</span>v0.1</span>
  </footer>
</div>
<button id="sgTop" type="button" aria-label="Back to top"><i class="bi bi-chevron-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const root=document.documentElement, mq=matchMedia('(min-width:992px)');
  const save=(k,v)=>{try{localStorage.setItem(k,v)}catch(e){}};
  function toggleSide(){
    if(mq.matches){ root.classList.toggle('sg-collapsed'); save('sgSide',root.classList.contains('sg-collapsed')?'1':'0'); }
    else root.classList.toggle('sg-open');
  }
  document.getElementById('sgBurger').onclick=toggleSide;
  document.getElementById('sgCollapse').onclick=toggleSide;
  document.getElementById('sgBackdrop').onclick=()=>root.classList.remove('sg-open');
  document.querySelectorAll('#sgSide a').forEach(a=>a.addEventListener('click',()=>root.classList.remove('sg-open')));
  mq.addEventListener('change',()=>root.classList.remove('sg-open'));
  const ico=document.getElementById('sgThemeIcon');
  const paint=()=>ico.className='bi '+(root.getAttribute('data-bs-theme')==='dark'?'bi-moon-stars':'bi-sun');
  paint();
  document.getElementById('sgTheme').onclick=()=>{
    const d=root.getAttribute('data-bs-theme')==='dark'; d?root.removeAttribute('data-bs-theme'):root.setAttribute('data-bs-theme','dark');
    save('sgTheme',d?'light':'dark'); paint();
  };
  const t=document.getElementById('sgTop');
  addEventListener('scroll',()=>t.style.display=scrollY>300?'block':'none',{passive:true});
  t.onclick=()=>scrollTo({top:0,behavior:'smooth'});
})();
</script>
@stack('scripts')
</body>
</html>
