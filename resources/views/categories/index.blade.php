@extends('layouts.app')
@section('title','Categories')
@section('content')
<div class="row g-3">
  <div class="col-lg-4"><div class="card"><div class="card-header bg-white fw-semibold">Add category</div><div class="card-body">
    <form method="POST" action="{{ route('categories.store') }}">@csrf
      <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
      <div class="row g-2 mb-3"><div class="col"><label class="form-label">HSN</label><input name="hsn" class="form-control"></div>
        <div class="col"><label class="form-label">GST %</label><input name="gst_rate" type="number" step="0.01" class="form-control"></div></div>
      <button class="btn btn-primary">Add</button>
    </form></div></div></div>
  <div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead class="table-light"><tr><th>Name</th><th>HSN</th><th>GST %</th><th>Products</th><th></th></tr></thead><tbody>
    @forelse($categories as $c)
      <tr>
        <form method="POST" action="{{ route('categories.update',$c) }}" id="f{{ $c->id }}">@csrf @method('PUT')</form>
        <td><input form="f{{ $c->id }}" name="name" value="{{ $c->name }}" class="form-control form-control-sm"></td>
        <td><input form="f{{ $c->id }}" name="hsn" value="{{ $c->hsn }}" class="form-control form-control-sm" style="width:110px"></td>
        <td><input form="f{{ $c->id }}" name="gst_rate" value="{{ $c->gst_rate }}" type="number" step="0.01" class="form-control form-control-sm" style="width:90px"></td>
        <td>{{ $c->products_count }}</td>
        <td class="text-end text-nowrap">
          <button form="f{{ $c->id }}" class="btn btn-sm btn-outline-primary">Save</button>
          <form method="POST" action="{{ route('categories.destroy',$c) }}" class="d-inline" onsubmit="return confirm('Delete category?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
        </td></tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-4">No categories yet.</td></tr>@endforelse
    </tbody></table></div></div></div>
</div>
@endsection
