<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') · SHREE GURVE</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
 body{font-family:'Nunito Sans',system-ui,sans-serif;background:#eff2f6;color:#31374a}
 .btn-primary{--bs-btn-bg:#3874ff;--bs-btn-border-color:#3874ff;--bs-btn-hover-bg:#2c60d6;--bs-btn-hover-border-color:#2c60d6;font-weight:700;border-radius:.5rem}
 .form-control{border-radius:.5rem}.form-control:focus{border-color:#3874ff;box-shadow:0 0 0 .2rem rgba(56,116,255,.15)}
 .card{border:1px solid #e3e6ed;border-radius:.75rem;box-shadow:0 8px 30px rgba(20,33,61,.08)}
</style></head>
<body class="d-flex align-items-center min-vh-100 py-4">
<div class="container" style="max-width:430px">
  <div class="text-center mb-4"><div class="d-inline-flex align-items-center gap-2 fs-3 fw-bolder"><i class="bi bi-graph-up-arrow" style="color:#3874ff"></i> SHREE GURVE</div></div>
  <div class="card"><div class="card-body p-4">
    @if(session('status'))<div class="alert alert-success py-2">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger py-2"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield('content')
  </div></div>
  <div class="text-center small mt-3"><a href="{{ route('login') }}" class="text-decoration-none">&larr; Back to sign in</a></div>
</div></body></html>
