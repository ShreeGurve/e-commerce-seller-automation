<div class="row g-3">
  <div class="col-md-6"><label class="form-label">Name *</label><input name="name" value="{{ old('name',$user->name) }}" class="form-control" required></div>
  <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" value="{{ old('email',$user->email) }}" class="form-control" required></div>
  <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" value="{{ old('phone',$user->phone) }}" class="form-control"></div>
  <div class="col-md-4"><label class="form-label">Password {{ $user->exists ? '(leave blank to keep)' : '*' }}</label><input type="password" name="password" class="form-control" {{ $user->exists?'':'required' }}></div>
  <div class="col-md-4"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control"></div>
  <div class="col-12"><label class="form-label d-block">Roles</label>
    @php $sel = old('roles', $selected); @endphp
    <div class="row">@foreach($roles as $r)<div class="col-sm-6 col-lg-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $r->id }}" id="r{{ $r->id }}" @checked(in_array($r->id,$sel))><label class="form-check-label" for="r{{ $r->id }}">{{ $r->name }}</label></div></div>@endforeach</div></div>
  <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active',$user->is_active ?? true))><label class="form-check-label" for="act">Active (can sign in)</label></div></div>
</div>
