<div class="d-flex flex-wrap gap-2 mb-4">
  @foreach([1=>'Upload',2=>'Map columns',3=>'Check',4=>'Result'] as $n=>$l)
    <span class="badge rounded-pill text-bg-{{ $n<$step ? 'success' : ($n==$step ? 'primary' : 'light border') }} fs-6">{{ $n<$step ? '✓' : $n }} {{ $l }}</span>
  @endforeach
</div>
