<?php

use App\Http\Controllers\AutomationInsightsController;
use Illuminate\Support\Facades\Route;

// Add inside the existing authenticated route group. Keep these static routes
// before any broad /automation/{...} routes.
