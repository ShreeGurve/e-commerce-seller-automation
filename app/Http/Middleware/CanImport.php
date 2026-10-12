<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Import screens: orders.edit (orders/returns/SKU mappings), finance.manage (settlements) OR advertising.manage (ad reports). The exact type is re-checked in ImportController. */
class CanImport
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        abort_unless($u && ($u->can('orders.edit') || $u->can('finance.manage') || $u->can('advertising.manage') || $u->can('listings.manage')), 403);
        return $next($request);
    }
}
