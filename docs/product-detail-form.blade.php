{{-- Add this inside resources/views/inventory/show.blade.php, where $product is available. --}}
@if(auth()->user()->can('inventory.manage'))
    <div class="card mt-3">
        <div class="card-body">
            <h5 class="card-title">Submit Stock Count for Approval</h5>
            <p class="small text-muted">This submits a count variance for review. It does not change stock until approved.</p>
            <form method="POST" action="{{ route('inventory.corrections.store', $product) }}">
                @csrf
                <div class="mb-2">
                    <label class="form-label" for="counted_qty">Physically Counted Quantity</label>
                    <input class="form-control" id="counted_qty" name="counted_qty" type="number" min="0" step="1" required>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="correction_note">Count Note</label>
                    <input class="form-control" id="correction_note" name="note" maxlength="500" placeholder="Optional reason or reference">
                </div>
                <button class="btn btn-primary">Submit for Approval</button>
                <a class="btn btn-outline-secondary" href="{{ route('inventory.corrections') }}">View Requests</a>
            </form>
        </div>
    </div>
@endif
