@php $bul = old('bullets', $v->bullets ?? []); while (count($bul) < 5) $bul[] = ''; $f=fn($k,$d=null)=>old($k, $v->{$k} ?? $d); @endphp
<div class="mb-3"><label class="form-label fw-semibold">Title *</label><input name="title" id="f_title" value="{{ $f('title') }}" class="form-control" required maxlength="500"><div class="form-text"><span data-count="f_title">0</span> characters</div></div>
<div class="mb-3"><label class="form-label fw-semibold">Bullet points</label><div id="bullets">@foreach($bul as $i=>$b)<textarea name="bullets[]" rows="2" class="form-control mb-2" placeholder="Bullet {{ $i+1 }}" maxlength="1000">{{ $b }}</textarea>@endforeach</div>
  <button type="button" class="btn btn-sm btn-outline-secondary" id="addBullet">+ Add bullet</button></div>
<div class="mb-3"><label class="form-label fw-semibold">Description</label><textarea name="description" id="f_desc" rows="6" class="form-control">{{ $f('description') }}</textarea><div class="form-text"><span data-count="f_desc">0</span> characters</div></div>
<div class="row g-3 mb-3">
  <div class="col-md-6"><label class="form-label fw-semibold">Backend search terms</label><textarea name="backend_keywords" id="f_back" rows="3" class="form-control" maxlength="500">{{ $f('backend_keywords') }}</textarea><div class="form-text"><span data-bytes="f_back">0</span> bytes</div></div>
  <div class="col-md-6"><label class="form-label fw-semibold">Special features <span class="text-muted fw-normal small">(one per line)</span></label><textarea name="special_features" rows="3" class="form-control">{{ $f('special_features') }}</textarea></div>
  <div class="col-12"><label class="form-label fw-semibold">A+ / enhanced content <span class="text-muted fw-normal small">(paste the text)</span></label><textarea name="aplus_content" id="f_aplus" rows="4" class="form-control">{{ $f('aplus_content') }}</textarea><div class="form-text"><span data-count="f_aplus">0</span> characters</div></div>
  <div class="col-md-3"><label class="form-label fw-semibold">Number of images</label><input type="number" min="0" max="100" name="image_count" value="{{ $f('image_count',0) }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label fw-semibold d-block">&nbsp;</label><div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="has_video" value="1" id="vid" @checked(old('has_video',$v->has_video ?? false))><label class="form-check-label" for="vid">Has a video</label></div></div>
  <div class="col-md-3"><label class="form-label fw-semibold">Brand</label><input name="brand" value="{{ old('brand',$listing->brand ?? '') }}" class="form-control"></div>
  <div class="col-md-3"><label class="form-label fw-semibold">Category path</label><input name="category_path" value="{{ old('category_path',$listing->category_path ?? '') }}" class="form-control"></div>
  <div class="col-12"><label class="form-label fw-semibold">What changed? <span class="text-muted fw-normal small">(saved with the version)</span></label><input name="change_note" value="{{ old('change_note') }}" class="form-control" maxlength="255" placeholder="e.g. moved main keyword to the start of the title"></div>
</div>
@push('scripts')<script>
document.querySelectorAll('[data-count]').forEach(s=>{const el=document.getElementById(s.dataset.count),u=()=>s.textContent=[...el.value.trim()].length;el.addEventListener('input',u);u();});
document.querySelectorAll('[data-bytes]').forEach(s=>{const el=document.getElementById(s.dataset.bytes),u=()=>s.textContent=new TextEncoder().encode(el.value.trim()).length;el.addEventListener('input',u);u();});
document.getElementById('addBullet').onclick=()=>{const b=document.getElementById('bullets');if(b.children.length>=10)return;const t=document.createElement('textarea');t.name='bullets[]';t.rows=2;t.className='form-control mb-2';t.placeholder='Bullet '+(b.children.length+1);b.appendChild(t);};
</script>@endpush
