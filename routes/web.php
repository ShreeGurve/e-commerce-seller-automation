<?php
use App\Http\Controllers\{PricingController, KeywordController, ListingController, ListingScoreRuleController, ClaimController, AdsController, ReconciliationController, SettlementController, ExpenseController, FeeRuleController, ProfitController, ImportController, MarketplaceController, OrderController, RefundController, ReturnController, CategoryController, DashboardController, InventoryController, ModuleController, ProductController, ProfileController, PurchaseController, RoleController, SettingsController, SupplierController, UserController};
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    // Closure (not Route::redirect) so redirect keeps the /laravel/seller-automation sub-folder.
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Products
    Route::get('/products', [ProductController::class, 'index'])->middleware('can:products.view')->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->middleware('can:products.create')->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->middleware('can:products.create')->name('products.store');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('can:products.view')->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('can:products.edit')->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('can:products.edit')->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('can:products.delete')->name('products.destroy');
    Route::post('/products/{product}/costs', [ProductController::class, 'storeCost'])->middleware('can:products.edit')->name('products.costs.store');
    Route::post('/products/{product}/marketplaces', [ProductController::class, 'storeMarketplace'])->middleware('can:products.edit')->name('products.marketplaces.store');
    Route::delete('/products/{product}/marketplaces/{mapping}', [ProductController::class, 'destroyMarketplace'])->middleware('can:products.edit')->name('products.marketplaces.destroy');

    // Categories
    Route::middleware('can:categories.manage')->prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
    });

    // Suppliers (share purchases.* permissions)
    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('can:purchases.view')->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->middleware('can:purchases.create')->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('can:purchases.create')->name('suppliers.store');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->middleware('can:purchases.edit')->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('can:purchases.edit')->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('can:purchases.edit')->name('suppliers.destroy');

    // Purchases
    Route::get('/purchases', [PurchaseController::class, 'index'])->middleware('can:purchases.view')->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->middleware('can:purchases.create')->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])->middleware('can:purchases.create')->name('purchases.store');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->middleware('can:purchases.view')->name('purchases.show');
    Route::get('/purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->middleware('can:purchases.edit')->name('purchases.edit');
    Route::put('/purchases/{purchase}', [PurchaseController::class, 'update'])->middleware('can:purchases.edit')->name('purchases.update');
    Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->middleware('can:purchases.edit')->name('purchases.receive');
    Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->middleware('can:purchases.edit')->name('purchases.cancel');
    Route::post('/purchases/{purchase}/payment', [PurchaseController::class, 'payment'])->middleware('can:purchases.edit')->name('purchases.payment');

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index'])->middleware('can:inventory.view')->name('inventory.index');
    Route::get('/inventory/{product}', [InventoryController::class, 'show'])->middleware('can:inventory.view')->name('inventory.show');
    Route::post('/inventory/{product}/adjust', [InventoryController::class, 'adjust'])->middleware('can:inventory.manage')->name('inventory.adjust');
    Route::put('/inventory/{product}/levels', [InventoryController::class, 'levels'])->middleware('can:inventory.manage')->name('inventory.levels');

    // Profile (any logged-in user)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    // Users
    Route::middleware('can:users.manage')->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    // Roles & permissions (Super Admin only: roles.manage is a pseudo-permission nobody else has)
    Route::middleware('can:roles.manage')->prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::put('/', [RoleController::class, 'update'])->name('update');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->middleware('can:settings.manage')->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->middleware('can:settings.manage')->name('settings.update');

    // Orders, refunds
    Route::get('/orders', [OrderController::class, 'index'])->middleware('can:orders.view')->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->middleware('can:orders.edit')->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('can:orders.edit')->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('can:orders.view')->name('orders.show');
    Route::post('/orders/{order}/status', [OrderController::class, 'status'])->middleware('can:orders.edit')->name('orders.status');
    Route::post('/orders/{order}/refunds', [RefundController::class, 'store'])->middleware('can:orders.edit')->name('orders.refunds.store');

    // Returns ({productReturn} / {media} are implicit-bound by parameter name)
    Route::get('/orders/{order}/returns/create', [ReturnController::class, 'create'])->middleware('can:returns.manage')->name('orders.returns.create');
    Route::post('/orders/{order}/returns', [ReturnController::class, 'store'])->middleware('can:returns.manage')->name('orders.returns.store');
    Route::get('/returns', [ReturnController::class, 'index'])->middleware('can:returns.view')->name('returns.index');
    Route::get('/returns/{productReturn}', [ReturnController::class, 'show'])->middleware('can:returns.view')->name('returns.show');
    Route::post('/returns/{productReturn}/inspect', [ReturnController::class, 'inspect'])->middleware('can:returns.manage')->name('returns.inspect');
    Route::post('/returns/{productReturn}/close', [ReturnController::class, 'close'])->middleware('can:returns.manage')->name('returns.close');
    Route::post('/returns/{productReturn}/media', [ReturnController::class, 'upload'])->middleware('can:returns.manage')->name('returns.upload');
    Route::get('/returns/{productReturn}/media/{media}', [ReturnController::class, 'media'])->middleware('can:returns.view')->name('returns.media');
    Route::delete('/returns/{productReturn}/media/{media}', [ReturnController::class, 'deleteMedia'])->middleware('can:returns.manage')->name('returns.media.delete');

    // Marketplace hubs (Amazon / Flipkart / ...)
    Route::get('/marketplaces/{code}', [MarketplaceController::class, 'show'])->middleware('can:orders.view')->name('marketplaces.show');

    // Import engine (CSV / Excel). Same permission as editing orders.
    Route::middleware(\App\Http\Middleware\CanImport::class)->prefix('imports')->name('imports.')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::get('/create', [ImportController::class, 'create'])->name('create');
        Route::post('/', [ImportController::class, 'store'])->name('store');
        Route::get('/{dataImport}', [ImportController::class, 'show'])->name('show');
        Route::get('/{dataImport}/map', [ImportController::class, 'map'])->name('map');
        Route::post('/{dataImport}/map', [ImportController::class, 'saveMap'])->name('map.save');
        Route::get('/{dataImport}/preview', [ImportController::class, 'preview'])->name('preview');
        Route::post('/{dataImport}/run', [ImportController::class, 'run'])->middleware('throttle:6,1')->name('run');
        Route::get('/{dataImport}/errors', [ImportController::class, 'errors'])->name('errors');
    });

    // Finance: profit, fee rules, expenses
    Route::get('/finance/profit', [ProfitController::class, 'index'])->middleware('can:finance.view')->name('profit.index');
    Route::post('/finance/profit/recalculate', [ProfitController::class, 'recalculate'])->middleware(['can:finance.manage', 'throttle:6,1'])->name('profit.recalculate');

    Route::get('/finance/fees', [FeeRuleController::class, 'index'])->middleware('can:finance.view')->name('fees.index');
    Route::middleware('can:finance.manage')->group(function () {
        Route::get('/finance/fees/create', [FeeRuleController::class, 'create'])->name('fees.create');
        Route::post('/finance/fees', [FeeRuleController::class, 'store'])->name('fees.store');
        Route::get('/finance/fees/{feeRule}/edit', [FeeRuleController::class, 'edit'])->name('fees.edit');
        Route::put('/finance/fees/{feeRule}', [FeeRuleController::class, 'update'])->name('fees.update');
        Route::delete('/finance/fees/{feeRule}', [FeeRuleController::class, 'destroy'])->name('fees.destroy');

        Route::get('/finance/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('/finance/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/finance/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('/finance/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/finance/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::post('/finance/expense-categories', [ExpenseController::class, 'storeCategory'])->name('expenses.categories.store');
    });
    Route::get('/finance/expenses', [ExpenseController::class, 'index'])->middleware('can:finance.view')->name('expenses.index');
    Route::get('/finance/expenses/{expense}/attachment', [ExpenseController::class, 'attachment'])->middleware('can:finance.view')->name('expenses.attachment');

    // Settlements + reconciliation
    Route::get('/finance/settlements', [SettlementController::class, 'index'])->middleware('can:finance.view')->name('settlements.index');
    Route::get('/finance/settlements/{settlement}', [SettlementController::class, 'show'])->middleware('can:finance.view')->name('settlements.show');
    Route::get('/finance/reconciliation', [ReconciliationController::class, 'index'])->middleware('can:finance.view')->name('reconciliation.index');
    Route::middleware('can:finance.manage')->group(function () {
        Route::post('/finance/reconciliation/run', [ReconciliationController::class, 'run'])->middleware('throttle:6,1')->name('reconciliation.run');
        Route::post('/finance/reconciliation/{reconciliationIssue}/ignore', [ReconciliationController::class, 'ignore'])->name('reconciliation.ignore');
        Route::post('/finance/reconciliation/{reconciliationIssue}/reopen', [ReconciliationController::class, 'reopen'])->name('reconciliation.reopen');
    });

    // Advertising (reports come from the import wizard)
    Route::middleware('can:advertising.view')->prefix('ads')->name('ads.')->group(function () {
        Route::get('/', [AdsController::class, 'index'])->name('index');
        Route::get('/insights', [AdsController::class, 'insights'])->name('insights');
        Route::get('/{level}', [AdsController::class, 'targets'])->where('level', 'keywords|terms')->name('targets');
    });

    // Claims (SAFE-T, reimbursement, disputes)
    Route::get('/claims', [ClaimController::class, 'index'])->middleware('can:claims.view')->name('claims.index');
    Route::get('/claims/create', [ClaimController::class, 'create'])->middleware('can:claims.create')->name('claims.create');
    Route::post('/claims', [ClaimController::class, 'store'])->middleware('can:claims.create')->name('claims.store');
    Route::get('/claims/{claim}', [ClaimController::class, 'show'])->middleware('can:claims.view')->name('claims.show');
    Route::get('/claims/{claim}/edit', [ClaimController::class, 'edit'])->middleware('can:claims.manage')->name('claims.edit');
    Route::put('/claims/{claim}', [ClaimController::class, 'update'])->middleware('can:claims.manage')->name('claims.update');
    Route::post('/claims/{claim}/status', [ClaimController::class, 'status'])->middleware('can:claims.manage')->name('claims.status');
    Route::post('/claims/{claim}/documents', [ClaimController::class, 'upload'])->middleware('can:claims.create')->name('claims.upload');
    Route::post('/claims/{claim}/attach-return-evidence', [ClaimController::class, 'attachReturn'])->middleware('can:claims.create')->name('claims.attach');
    Route::get('/claims/{claim}/documents/{document}', [ClaimController::class, 'document'])->middleware('can:claims.view')->name('claims.document');
    Route::delete('/claims/{claim}/documents/{document}', [ClaimController::class, 'deleteDocument'])->middleware('can:claims.manage')->name('claims.document.delete');

    // Listings (copy workspace with versions) + score rules
    Route::middleware('can:listings.view')->group(function () {
        Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
        Route::get('/listings/score-rules', [ListingScoreRuleController::class, 'index'])->name('listings.rules');
    });
    Route::middleware('can:listings.manage')->group(function () {
        Route::put('/listings/score-rules', [ListingScoreRuleController::class, 'update'])->name('listings.rules.update');
        Route::get('/listings/create', [ListingController::class, 'create'])->name('listings.create');
        Route::post('/listings', [ListingController::class, 'store'])->name('listings.store');
        Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
        Route::put('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
        Route::post('/listings/{listing}/status', [ListingController::class, 'status'])->name('listings.status');
        Route::post('/listings/{listing}/rescore', [ListingController::class, 'rescore'])->name('listings.rescore');
        Route::post('/listings/{listing}/publish/{version}', [ListingController::class, 'publish'])->name('listings.publish');
        Route::post('/listings/{listing}/restore/{version}', [ListingController::class, 'restore'])->name('listings.restore');
    });
    Route::get('/listings/{listing}', [ListingController::class, 'show'])->middleware('can:listings.view')->name('listings.show');
    Route::get('/listings/{listing}/compare', [ListingController::class, 'compare'])->middleware('can:listings.view')->name('listings.compare');

    // Keywords
    Route::get('/keywords', [KeywordController::class, 'index'])->middleware('can:listings.view')->name('keywords.index');
    Route::middleware('can:listings.manage')->group(function () {
        Route::post('/keywords', [KeywordController::class, 'store'])->name('keywords.store');
        Route::get('/keywords/{keyword}/edit', [KeywordController::class, 'edit'])->name('keywords.edit');
        Route::put('/keywords/{keyword}', [KeywordController::class, 'update'])->name('keywords.update');
        Route::delete('/keywords/{keyword}', [KeywordController::class, 'destroy'])->name('keywords.destroy');
        Route::post('/keywords/{keyword}/rank', [KeywordController::class, 'rank'])->name('keywords.rank');
    });

    // Pricing intelligence (cost + fees => break-even / minimum / target price). Advice only.
    Route::get('/pricing', [PricingController::class, 'index'])->middleware('can:products.view')->name('pricing.index');
    Route::get('/pricing/{product}/{marketplace}', [PricingController::class, 'show'])->middleware('can:products.view')->name('pricing.show');
    Route::middleware('can:products.edit')->group(function () {
        Route::post('/pricing/{product}/{marketplace}/prices', [PricingController::class, 'recordPrice'])->name('pricing.price');
        Route::post('/pricing/{product}/{marketplace}/competitors', [PricingController::class, 'competitor'])->name('pricing.competitor');
        Route::delete('/pricing/competitors/{competitorPrice}', [PricingController::class, 'deleteCompetitor'])->name('pricing.competitor.delete');
    });

    // Modules not built yet
    Route::get('/m/{module}', ModuleController::class)->name('module');
});
