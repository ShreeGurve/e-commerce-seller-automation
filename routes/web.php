<?php

use App\Http\Controllers\AdsController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ApprovalWorkflowController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\AutomationControlCenterController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\AutomationGovernanceController;
use App\Http\Controllers\AutomationInsightsController;
use App\Http\Controllers\AutomationIntelligenceController;
use App\Http\Controllers\AutomationOperationsController;
use App\Http\Controllers\AutomationOptimizationController;
use App\Http\Controllers\AutomationPlanningController;
use App\Http\Controllers\AutomationTaskController;
use App\Http\Controllers\AutomationWorkforceController;
use App\Http\Controllers\BusinessAlertIntelligenceController;
use App\Http\Controllers\BusinessAnalyticsController;
use App\Http\Controllers\BusinessAutomationInsightsController;
use App\Http\Controllers\BusinessPerformanceController;
use App\Http\Controllers\CashPlanningController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClaimController;
use App\Http\Controllers\CommercialReconciliationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExecutiveDecisionDashboardController;
use App\Http\Controllers\ExecutiveIntelligenceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FeeRuleController;
use App\Http\Controllers\FinanceOperationsInsightController;
use App\Http\Controllers\FinanceOperationsIntelligenceController;
use App\Http\Controllers\FinancialPlanningIntelligenceController;
use App\Http\Controllers\FulfilmentController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InventoryAutomationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryCorrectionController;
use App\Http\Controllers\InventoryForecastController;
use App\Http\Controllers\InventoryHealthController;
use App\Http\Controllers\InventoryMovementCostController;
use App\Http\Controllers\InventoryOperationsController;
use App\Http\Controllers\InventoryReconciliationReportController;
use App\Http\Controllers\InventoryValuationController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\KpiTrendController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\ListingReadinessController;
use App\Http\Controllers\ListingScoreRuleController;
use App\Http\Controllers\MarketplaceAutomationExecutionController;
use App\Http\Controllers\MarketplaceAutomationRuleController;
use App\Http\Controllers\MarketplaceBusinessIntelligenceController;
use App\Http\Controllers\MarketplaceConnectionController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MarketplaceFinancialAnalyticsController;
use App\Http\Controllers\MarketplaceRecommendationController;
use App\Http\Controllers\MarketplaceWriteAlertPreferenceController;
use App\Http\Controllers\MarketplaceWriteAuditController;
use App\Http\Controllers\MarketplaceWriteEmergencyController;
use App\Http\Controllers\MarketplaceWriteEscalationController;
use App\Http\Controllers\MarketplaceWriteEvidenceController;
use App\Http\Controllers\MarketplaceWriteExecutionController;
use App\Http\Controllers\MarketplaceWriteGovernanceController;
use App\Http\Controllers\MarketplaceWriteIncidentController;
use App\Http\Controllers\MarketplaceWriteMonitoringController;
use App\Http\Controllers\MarketplaceWriteRecoveryController;
use App\Http\Controllers\MarketplaceWriteRetentionController;
use App\Http\Controllers\MarketplaceWriteRetryAuthorizationController;
use App\Http\Controllers\MarketplaceWriteSlaController;
use App\Http\Controllers\MarketplaceWriteVerificationController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationalInsightsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderOperationsController;
use App\Http\Controllers\OrderOperationsInsightsController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProcurementAutomationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfitController;
use App\Http\Controllers\PurchaseBudgetController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseCostIntelligenceController;
use App\Http\Controllers\PurchaseInvoiceDocumentController;
use App\Http\Controllers\PurchaseInvoiceLineCheckController;
use App\Http\Controllers\PurchaseInvoiceMatchController;
use App\Http\Controllers\PurchaseQualityInspectionController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReorderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\SmartReorderController;
use App\Http\Controllers\SupplierAccountStatementController;
use App\Http\Controllers\SupplierAnalyticsController;
use App\Http\Controllers\SupplierComplianceController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierCreditNoteController;
use App\Http\Controllers\SupplierDocumentController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\SupplierPaymentPlanningController;
use App\Http\Controllers\SupplierRelationshipController;
use App\Http\Controllers\SupplierReturnController;
use App\Http\Controllers\SupplierReturnSettlementController;
use App\Http\Controllers\SupplierRiskController;
use App\Http\Controllers\SupplierSourcingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseIntelligenceController;
use App\Http\Middleware\CanImport;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('home');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
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
    Route::post('/products/{product}/marketplace-settings', [ProductController::class, 'saveMarketplaceSettings'])->middleware('can:products.edit')->name('products.marketplaces.settings');
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

    // Phase 47: monthly business performance targets and scorecard
    Route::get('/business-performance', [BusinessPerformanceController::class, 'index'])
        ->middleware('can:reports.view')->name('business-performance.index');
    Route::post('/business-performance/targets', [BusinessPerformanceController::class, 'storeTarget'])
        ->middleware('can:reports.export')->name('business-performance.targets.store');

    Route::get('/business-alerts/kpi-monitor', [BusinessAlertIntelligenceController::class, 'kpiMonitor'])
        ->middleware('can:reports.view')->name('business-alerts.kpi-monitor');
    Route::post('/business-alerts/kpi-evaluate', [BusinessAlertIntelligenceController::class, 'evaluateKpis'])
        ->middleware('can:reports.export')->name('business-alerts.kpi-evaluate');
    Route::get('/business-alerts/recommendations', [BusinessAlertIntelligenceController::class, 'recommendations'])
        ->middleware('can:reports.view')->name('business-alerts.recommendations');
    Route::get('/business-alerts/digest.csv', [BusinessAlertIntelligenceController::class, 'digest'])
        ->middleware('can:reports.export')->name('business-alerts.digest');

    // Packages 63–65: add inside the existing authenticated Route::middleware('auth')->group(...).
    Route::get('/business-automation/daily-digest', [BusinessAutomationInsightsController::class, 'dailyDigest'])
        ->middleware('can:reports.view')->name('business-automation.daily-digest');
    Route::get('/business-automation/alert-sla', [BusinessAutomationInsightsController::class, 'alertSla'])
        ->middleware('can:reports.view')->name('business-automation.alert-sla');
    Route::get('/business-automation/kpi-forecast', [BusinessAutomationInsightsController::class, 'kpiForecast'])
        ->middleware('can:reports.view')->name('business-automation.kpi-forecast');

    // Phases 51-53: Operational Intelligence & Reports
    Route::get('/automation/operational-insights', [OperationalInsightsController::class, 'index'])->middleware('can:reports.view')->name('operational-insights.index');
    Route::get('/automation/operational-insights/export', [OperationalInsightsController::class, 'export'])->middleware('can:reports.export')->name('operational-insights.export');

    // Phases 48-50: Automation Control & Governance
    Route::get('/automation/governance/control-center', [AutomationGovernanceController::class, 'index'])->middleware('can:reports.view')->name('governance.control-center');
    Route::patch('/automation/control-center/alerts/{event}/assign', [AutomationGovernanceController::class, 'assignEvent'])->middleware('can:purchases.edit')->name('governance.alerts.assign');
    Route::patch('/automation/control-center/alerts/{event}/resolve', [AutomationGovernanceController::class, 'resolveEvent'])->middleware('can:purchases.edit')->name('governance.alerts.resolve');
    Route::post('/automation/control-center/approval-sla', [AutomationGovernanceController::class, 'saveSlaPolicy'])->middleware('can:settings.manage')->name('governance.approval-sla.save');

    // Phases 42-44: Executive Intelligence & Internal Automation
    Route::get('/executive/intelligence', [ExecutiveIntelligenceController::class, 'index'])->middleware('can:purchases.view')->name('executive.intelligence.index');
    Route::get('/executive/intelligence/export', [ExecutiveIntelligenceController::class, 'export'])->middleware('can:purchases.view')->name('executive.intelligence.export');
    Route::post('/executive/intelligence/rules', [ExecutiveIntelligenceController::class, 'saveRule'])->middleware('can:purchases.edit')->name('executive.intelligence.rules.save');
    Route::post('/executive/intelligence/evaluate', [ExecutiveIntelligenceController::class, 'evaluate'])->middleware('can:purchases.edit')->name('executive.intelligence.evaluate');
    Route::patch('/executive/intelligence/events/{event}/resolve', [ExecutiveIntelligenceController::class, 'resolveEvent'])->middleware('can:purchases.edit')->name('executive.intelligence.events.resolve');

    // Purchases
    Route::get('/purchases', [PurchaseController::class, 'index'])->middleware('can:purchases.view')->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->middleware('can:purchases.create')->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])->middleware('can:purchases.create')->name('purchases.store');
    // Specific purchase feature routes must precede the dynamic {purchase} routes.
    Route::get('/purchases/cost-intelligence', [PurchaseCostIntelligenceController::class, 'index'])
        ->middleware('can:purchases.view')
        ->name('purchase-cost-intelligence.index');

    Route::get('/purchases/cost-intelligence/export', [PurchaseCostIntelligenceController::class, 'export'])
        ->middleware('can:purchases.view')
        ->name('purchase-cost-intelligence.export');

    // Phase 25: Purchase Receiving Quality Control
    Route::get('/purchases/quality-control', [PurchaseQualityInspectionController::class, 'index'])
        ->middleware('can:purchases.view')->name('purchase-quality.index');
    Route::get('/purchases/quality-control/create', [PurchaseQualityInspectionController::class, 'create'])
        ->middleware('can:purchases.create')->name('purchase-quality.create');
    Route::post('/purchases/quality-control', [PurchaseQualityInspectionController::class, 'store'])
        ->middleware('can:purchases.create')->name('purchase-quality.store');
    Route::patch('/purchases/quality-control/{inspection}', [PurchaseQualityInspectionController::class, 'update'])
        ->middleware('can:purchases.edit')->name('purchase-quality.update');

    // Phase 24: Purchase Budgeting & Planning
    Route::get('/purchases/budgets', [PurchaseBudgetController::class, 'index'])
        ->middleware('can:purchases.view')
        ->name('purchase-budgets.index');

    Route::post('/purchases/budgets', [PurchaseBudgetController::class, 'store'])
        ->middleware('can:purchases.create')
        ->name('purchase-budgets.store');

    Route::get('/purchases/budgets/export', [PurchaseBudgetController::class, 'export'])
        ->middleware('can:purchases.view')
        ->name('purchase-budgets.export');
    // Phase 26: Supplier Returns & Replacement Management
    Route::get('/purchases/supplier-returns', [SupplierReturnController::class, 'index'])
        ->middleware('can:purchases.view')->name('supplier-returns.index');
    Route::get('/purchases/supplier-returns/create', [SupplierReturnController::class, 'create'])
        ->middleware('can:purchases.create')->name('supplier-returns.create');
    Route::post('/purchases/supplier-returns', [SupplierReturnController::class, 'store'])
        ->middleware('can:purchases.create')->name('supplier-returns.store');
    Route::patch('/purchases/supplier-returns/{supplierReturn}/status', [SupplierReturnController::class, 'updateStatus'])
        ->middleware('can:purchases.edit')->name('supplier-returns.status');

    // Phase 27: Supplier Return Settlement & Replacement Receipt Tracking
    Route::get('/purchases/supplier-return-settlements', [SupplierReturnSettlementController::class, 'index'])
        ->middleware('can:purchases.view')->name('supplier-return-settlements.index');
    Route::post('/purchases/supplier-return-settlements', [SupplierReturnSettlementController::class, 'store'])
        ->middleware('can:purchases.create')->name('supplier-return-settlements.store');
    Route::patch('/purchases/supplier-return-settlements/{settlement}/confirm', [SupplierReturnSettlementController::class, 'confirm'])
        ->middleware('can:purchases.edit')->name('supplier-return-settlements.confirm');
    Route::patch('/purchases/supplier-return-settlements/{settlement}/cancel', [SupplierReturnSettlementController::class, 'cancel'])
        ->middleware('can:purchases.edit')->name('supplier-return-settlements.cancel');

    // Phase 28: Supplier Invoice Matching & Purchase Reconciliation
    Route::get('/purchases/invoice-matching', [PurchaseInvoiceMatchController::class, 'index'])
        ->middleware('can:purchases.view')->name('purchase-invoice-matching.index');
    Route::get('/purchases/invoice-matching/create', [PurchaseInvoiceMatchController::class, 'create'])
        ->middleware('can:purchases.create')->name('purchase-invoice-matching.create');
    Route::post('/purchases/invoice-matching', [PurchaseInvoiceMatchController::class, 'store'])
        ->middleware('can:purchases.create')->name('purchase-invoice-matching.store');
    Route::patch('/purchases/invoice-matching/{match}/review', [PurchaseInvoiceMatchController::class, 'review'])
        ->middleware('can:purchases.edit')->name('purchase-invoice-matching.review');

    // Phase 29: Invoice line-level quantity and unit-cost reconciliation
    Route::get('/purchases/invoice-line-checks', [PurchaseInvoiceLineCheckController::class, 'index'])
        ->middleware('can:purchases.view')->name('purchase-invoice-line-checks.index');
    Route::post('/purchases/invoice-matching/{match}/line-checks', [PurchaseInvoiceLineCheckController::class, 'store'])
        ->middleware('can:purchases.create')->name('purchase-invoice-line-checks.store');

    // Phases 30–32: supplier invoice documents, GST checks and processing review
    Route::get('/purchases/invoice-documents', [PurchaseInvoiceDocumentController::class, 'index'])
        ->middleware('can:purchases.view')->name('purchase-invoice-documents.index');
    Route::post('/purchases/invoice-matching/{match}/document', [PurchaseInvoiceDocumentController::class, 'upload'])
        ->middleware('can:purchases.create')->name('purchase-invoice-documents.upload');
    Route::get('/purchases/invoice-matching/{match}/document', [PurchaseInvoiceDocumentController::class, 'download'])
        ->middleware('can:purchases.view')->name('purchase-invoice-documents.download');
    Route::post('/purchases/invoice-matching/{match}/gst-validation', [PurchaseInvoiceDocumentController::class, 'validateGst'])
        ->middleware('can:purchases.edit')->name('purchase-invoice-documents.gst-validation');

    // Phases 33–35: manual supplier payment, credit note and statement workflows
    Route::get('/purchases/supplier-payments', [SupplierPaymentController::class, 'index'])
        ->middleware('can:purchases.view')->name('supplier-invoice-payments.index');
    Route::post('/purchases/invoice-matching/{match}/payments', [SupplierPaymentController::class, 'store'])
        ->middleware('can:purchases.create')->name('supplier-payments.store');

    Route::get('/purchases/supplier-credit-notes', [SupplierCreditNoteController::class, 'index'])
        ->middleware('can:purchases.view')->name('supplier-credit-notes.index');
    Route::post('/purchases/supplier-credit-notes', [SupplierCreditNoteController::class, 'store'])
        ->middleware('can:purchases.create')->name('supplier-credit-notes.store');
    Route::post('/purchases/supplier-credit-notes/{credit}/apply', [SupplierCreditNoteController::class, 'apply'])
        ->middleware('can:purchases.edit')->name('supplier-credit-notes.apply');

    Route::get('/purchases/supplier-statements', [SupplierAccountStatementController::class, 'index'])
        ->middleware('can:purchases.view')->name('supplier-statements.index');

    // Phases 36–38: internal procurement requisitions, supplier quotes and award review
    Route::get('/purchases/procurement', [ProcurementAutomationController::class, 'index'])
        ->middleware('can:purchases.view')->name('procurement.index');
    Route::post('/purchases/procurement/requisitions', [ProcurementAutomationController::class, 'storeRequisition'])
        ->middleware('can:purchases.create')->name('procurement.requisitions.store');
    Route::patch('/purchases/procurement/requisitions/{requisition}/review', [ProcurementAutomationController::class, 'approveRequisition'])
        ->middleware('can:purchases.edit')->name('procurement.requisitions.review');
    Route::post('/purchases/procurement/requisitions/{requisition}/quotes', [ProcurementAutomationController::class, 'storeQuote'])
        ->middleware('can:purchases.create')->name('procurement.quotes.store');
    Route::patch('/purchases/procurement/requisitions/{requisition}/quotes/{quote}/award', [ProcurementAutomationController::class, 'awardQuote'])
        ->middleware('can:purchases.edit')->name('procurement.quotes.award');

    // Phases 39–41: warehouse locations, internal transfers and physical stock counts
    Route::get('/warehouse/intelligence', [WarehouseIntelligenceController::class, 'index'])
        ->middleware('can:products.view')->name('warehouse.intelligence.index');
    Route::post('/warehouse/locations', [WarehouseIntelligenceController::class, 'storeLocation'])
        ->middleware('can:products.edit')->name('warehouse.locations.store');
    Route::patch('/warehouse/locations/{location}', [WarehouseIntelligenceController::class, 'updateLocation'])
        ->middleware('can:products.edit')->name('warehouse.locations.update');
    Route::post('/warehouse/locations/{location}/balances', [WarehouseIntelligenceController::class, 'storeBalance'])
        ->middleware('can:products.edit')->name('warehouse.balances.store');
    Route::post('/warehouse/transfers', [WarehouseIntelligenceController::class, 'storeTransfer'])
        ->middleware('can:products.edit')->name('warehouse.transfers.store');
    Route::patch('/warehouse/transfers/{transfer}/dispatch', [WarehouseIntelligenceController::class, 'dispatchTransfer'])
        ->middleware('can:products.edit')->name('warehouse.transfers.dispatch');
    Route::patch('/warehouse/transfers/{transfer}/receive', [WarehouseIntelligenceController::class, 'receiveTransfer'])
        ->middleware('can:products.edit')->name('warehouse.transfers.receive');
    Route::post('/warehouse/stock-counts', [WarehouseIntelligenceController::class, 'storeCount'])
        ->middleware('can:products.edit')->name('warehouse.stock-counts.store');
    Route::patch('/warehouse/stock-counts/{count}/finalize', [WarehouseIntelligenceController::class, 'finalizeCount'])
        ->middleware('can:products.edit')->name('warehouse.stock-counts.finalize');

    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->whereNumber('purchase')->middleware('can:purchases.view')->name('purchases.show');
    Route::get('/purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->whereNumber('purchase')->middleware('can:purchases.edit')->name('purchases.edit');
    Route::put('/purchases/{purchase}', [PurchaseController::class, 'update'])->whereNumber('purchase')->middleware('can:purchases.edit')->name('purchases.update');
    Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->whereNumber('purchase')->middleware('can:purchases.edit')->name('purchases.receive');
    Route::post('/purchases/{purchase}/receive-quantities', [PurchaseController::class, 'receiveQuantities'])->whereNumber('purchase')->middleware('can:purchases.edit')->name('purchases.receive-quantities');
    Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->whereNumber('purchase')->middleware('can:purchases.edit')->name('purchases.cancel');
    Route::post('/purchases/{purchase}/payment', [PurchaseController::class, 'payment'])->whereNumber('purchase')->middleware('can:purchases.edit')->name('purchases.payment');

    // Phase 45: Smart reorder recommendations -> internal procurement requisition only
    Route::get('/reorders/smart', [SmartReorderController::class, 'index'])->middleware('can:purchases.view')->name('reorders.smart.index');
    Route::post('/reorders/smart/generate', [SmartReorderController::class, 'generate'])->middleware('can:purchases.create')->name('reorders.smart.generate');

    // Phase 7: reorder planning
    Route::get('/reorders', [ReorderController::class, 'index'])->middleware('can:purchases.view')->name('reorders.index');
    Route::post('/reorders/purchase', [ReorderController::class, 'createPurchase'])->middleware('can:purchases.create')->name('reorders.purchase');

    // Phase 8: supplier performance, purchase analytics and internal ratings
    Route::get('/supplier-analytics', [SupplierAnalyticsController::class, 'index'])->middleware('can:purchases.view')->name('supplier-analytics.index');
    Route::get('/supplier-analytics/export', [SupplierAnalyticsController::class, 'export'])->middleware('can:purchases.view')->name('supplier-analytics.export');
    Route::get('/supplier-analytics/{supplier}', [SupplierAnalyticsController::class, 'show'])->middleware('can:purchases.view')->name('supplier-analytics.show');
    Route::post('/supplier-analytics/{supplier}/reviews', [SupplierAnalyticsController::class, 'review'])->middleware('can:purchases.edit')->name('supplier-analytics.review');

    // Phase 9: internal inventory demand forecasting and stock-out planning
    Route::get('/inventory-forecast', [InventoryForecastController::class, 'index'])->middleware('can:purchases.view')->name('inventory-forecast.index');
    Route::post('/inventory-forecast/snapshot', [InventoryForecastController::class, 'snapshot'])->middleware('can:purchases.edit')->name('inventory-forecast.snapshot');

    // Inventory operations dashboards (Batches 18-26)
    Route::get('/inventory/corrections', [InventoryCorrectionController::class, 'index'])->middleware('can:inventory.view')->name('inventory.corrections');
    Route::post('/inventory/{product}/corrections', [InventoryCorrectionController::class, 'store'])->middleware('can:inventory.manage')->name('inventory.corrections.store');
    Route::post('/inventory/corrections/{correction}/approve', [InventoryCorrectionController::class, 'approve'])->middleware('can:inventory.manage')->name('inventory.corrections.approve');
    Route::post('/inventory/corrections/{correction}/reject', [InventoryCorrectionController::class, 'reject'])->middleware('can:inventory.manage')->name('inventory.corrections.reject');
    Route::get('/inventory/reconciliation-report', [InventoryReconciliationReportController::class, 'index'])->middleware('can:inventory.view')->name('inventory.reconciliation-report');
    Route::get('/inventory/reconciliation-report/export', [InventoryReconciliationReportController::class, 'export'])->middleware('can:inventory.view')->name('inventory.reconciliation-report.export');
    Route::get('/inventory/automation', [InventoryAutomationController::class, 'index'])->middleware('can:inventory.view')->name('inventory.automation');
    Route::get('/inventory/automation/export', [InventoryAutomationController::class, 'export'])->middleware('can:inventory.view')->name('inventory.automation.export');
    Route::get('/inventory/ledger', [InventoryOperationsController::class, 'ledger'])->middleware('can:inventory.view')->name('inventory.ledger');
    Route::get('/inventory/ledger/export', [InventoryOperationsController::class, 'ledgerExport'])->middleware('can:inventory.view')->name('inventory.ledger.export');
    Route::get('/inventory/inactivity', [InventoryOperationsController::class, 'inactivity'])->middleware('can:inventory.view')->name('inventory.inactivity');
    Route::get('/inventory/reservations/audit', [InventoryOperationsController::class, 'reservations'])->middleware('can:inventory.view')->name('inventory.reservations.audit');
    Route::get('/inventory/reservations/audit/export', [InventoryOperationsController::class, 'reservationExport'])->middleware('can:inventory.view')->name('inventory.reservations.export');
    Route::get('/inventory/health', [InventoryHealthController::class, 'index'])->middleware('can:inventory.view')->name('inventory.health');
    Route::get('/inventory/health/export', [InventoryHealthController::class, 'export'])->middleware('can:inventory.view')->name('inventory.health.export');
    Route::get('/inventory/valuation', [InventoryValuationController::class, 'index'])->middleware('can:inventory.view')->name('inventory.valuation');
    Route::get('/inventory/valuation/export', [InventoryValuationController::class, 'export'])->middleware('can:inventory.view')->name('inventory.valuation.export');
    Route::get('/inventory/movement-costs', [InventoryMovementCostController::class, 'index'])->middleware('can:inventory.view')->name('inventory.movement-costs');
    Route::get('/inventory/movement-costs/export', [InventoryMovementCostController::class, 'export'])->middleware('can:inventory.view')->name('inventory.movement-costs.export');

    // Inventory core routes (dynamic product route stays after static routes)
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

    // Order and return operations monitors (Batch 27-29)
    Route::get('/orders/operations/sla', [OrderOperationsController::class, 'sla'])->middleware('can:orders.view')->name('orders.operations.sla');
    Route::get('/orders/operations/fulfillment-exceptions', [OrderOperationsController::class, 'fulfillmentExceptions'])->middleware('can:orders.view')->name('orders.operations.fulfillment-exceptions');
    Route::get('/returns/operations/intake-monitor', [OrderOperationsController::class, 'returnIntake'])->middleware('can:returns.manage')->name('returns.operations.intake-monitor');

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

    Route::post('/dashboard/rebuild', [DashboardController::class, 'rebuild'])->middleware(['can:finance.manage', 'throttle:6,1'])->name('dashboard.rebuild');

    // Automation engine (rules and internal alerts only; no marketplace writes).
    Route::get('/automation', [AutomationController::class, 'index'])
        ->middleware('can:automation.view')->name('automation.index');
    Route::get('/automation/rules/{automationRule}/preview', [AutomationController::class, 'preview'])
        ->middleware('can:automation.view')->name('automation.preview');
    Route::middleware('can:automation.manage')->prefix('automation')->name('automation.')->group(function () {
        Route::post('/rules', [AutomationController::class, 'store'])->name('store');
        Route::put('/rules/{automationRule}', [AutomationController::class, 'update'])->name('update');
        Route::post('/rules/{automationRule}/toggle', [AutomationController::class, 'toggle'])->name('toggle');
        Route::delete('/rules/{automationRule}', [AutomationController::class, 'destroy'])->name('destroy');
        Route::post('/executions/{execution}/retry', [AutomationController::class, 'retry'])->middleware('throttle:6,1')->name('retry');
        Route::post('/run', [AutomationController::class, 'run'])->middleware('throttle:6,1')->name('run');
    });

    // Notifications used by the header icon and notification centre.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // Approval workflow used by the sidebar and approval screens.
    Route::get('/approvals', [ApprovalController::class, 'index'])
        ->middleware('can:approvals.view')->name('approvals.index');
    Route::post('/approvals', [ApprovalController::class, 'store'])
        ->middleware('can:approvals.view')->name('approvals.store');
    Route::post('/approvals/{approvalRequest}/approve', [ApprovalController::class, 'approve'])
        ->name('approvals.approve');
    Route::post('/approvals/{approvalRequest}/reject', [ApprovalController::class, 'reject'])
        ->name('approvals.reject');
    Route::post('/approvals/{approvalRequest}/cancel', [ApprovalController::class, 'cancel'])
        ->name('approvals.cancel');

    // Phase 46: configurable sequential approval policies (new requests only)
    Route::get('/approvals/workflows', [ApprovalWorkflowController::class, 'index'])
        ->middleware('can:settings.manage')->name('approval-workflows.index');
    Route::post('/approvals/workflows', [ApprovalWorkflowController::class, 'store'])
        ->middleware('can:settings.manage')->name('approval-workflows.store');
    Route::delete('/approvals/workflows/{policy}', [ApprovalWorkflowController::class, 'destroy'])
        ->middleware('can:settings.manage')->name('approval-workflows.destroy');

    // Marketplace connector diagnostics (credentials are configured in .env, never stored in the UI).
    Route::get('/marketplace-connections', [MarketplaceConnectionController::class, 'index'])->middleware('can:settings.manage')->name('marketplaces.connections');
    Route::get('/marketplace-connections/{marketplace}/add', [MarketplaceConnectionController::class, 'create'])->middleware('can:settings.manage')->name('marketplaces.connections.create');
    Route::post('/marketplace-connections/{marketplace}/verify', [MarketplaceConnectionController::class, 'verify'])->middleware(['can:settings.manage', 'throttle:6,1'])->name('marketplaces.connections.verify');
    Route::delete('/marketplace-connections/{connection}', [MarketplaceConnectionController::class, 'destroy'])->middleware('can:settings.manage')->name('marketplaces.connections.destroy');
    Route::post('/marketplace-connections/{connection}/recheck', [MarketplaceConnectionController::class, 'recheck'])->middleware('can:settings.manage')->name('marketplaces.connections.recheck');

    // Marketplace hubs (Amazon / Flipkart / ...)
    Route::get('/marketplaces/{code}', [MarketplaceController::class, 'show'])->middleware('can:orders.view')->name('marketplaces.show');

    // Marketplace Operations
    Route::get('/marketplace-operations', [MarketplaceController::class, 'operations'])
        ->middleware('can:products.view')
        ->name('marketplaces.operations');

    Route::post('/marketplace-operations/mappings', [MarketplaceController::class, 'storeMapping'])
        ->middleware('can:products.edit')
        ->name('marketplaces.operations.mappings.store');

    Route::delete('/marketplace-operations/mappings/{mapping}', [MarketplaceController::class, 'destroyMapping'])
        ->middleware('can:products.edit')
        ->name('marketplaces.operations.mappings.destroy');

    Route::middleware(['auth', 'can:listings.manage'])
        ->prefix('marketplace-writes/recovery')
        ->name('marketplaces.writes.recovery.')
        ->group(function () {
            Route::get('/', [MarketplaceWriteRecoveryController::class, 'index'])
                ->name('index');

            Route::post('/', [MarketplaceWriteRecoveryController::class, 'store'])
                ->name('store');

            Route::patch('/{case}/steps/{step}', [MarketplaceWriteRecoveryController::class, 'updateStep'])
                ->whereNumber('case')
                ->whereNumber('step')
                ->name('steps.update');

            Route::patch('/{case}/close', [MarketplaceWriteRecoveryController::class, 'close'])
                ->whereNumber('case')
                ->name('close');
        });

    Route::middleware(['auth', 'can:listings.manage'])
        ->prefix('marketplace-writes/evidence')
        ->name('marketplaces.writes.evidence.')
        ->group(function () {
            Route::get(
                '/',
                [MarketplaceWriteEvidenceController::class, 'index']
            )->name('index');

            Route::post(
                '/',
                [MarketplaceWriteEvidenceController::class, 'store']
            )->name('store');

            Route::get(
                '/{evidence}',
                [MarketplaceWriteEvidenceController::class, 'show']
            )->whereNumber('evidence')
                ->name('show');
        });

    // Import engine (CSV / Excel). Same permission as editing orders.
    Route::middleware(CanImport::class)->prefix('imports')->name('imports.')->group(function () {
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

    Route::get('/finance/reconciliation/export', [ReconciliationController::class, 'export'])
        ->middleware('can:finance.view')
        ->name('reconciliation.export');

    Route::middleware('can:finance.manage')->group(function () {
        Route::post('/finance/reconciliation/run', [ReconciliationController::class, 'run'])->middleware('throttle:6,1')->name('reconciliation.run');
        Route::post('/finance/reconciliation/{reconciliationIssue}/ignore', [ReconciliationController::class, 'ignore'])->name('reconciliation.ignore');
        Route::post('/finance/reconciliation/{reconciliationIssue}/reopen', [ReconciliationController::class, 'reopen'])->name('reconciliation.reopen');
    });

    Route::get('/finance/profit/export', [ProfitController::class, 'export'])
        ->middleware('can:finance.view')
        ->name('profit.export');

    Route::get('/finance/cash-planning', [CashPlanningController::class, 'index'])
        ->middleware('can:finance.view')
        ->name('cash-planning.index');

    Route::get('/finance/supplier-payments', [SupplierPaymentPlanningController::class, 'index'])
        ->middleware('can:finance.view')
        ->name('supplier-payments.index');

    Route::get('/finance/supplier-payments/export', [SupplierPaymentPlanningController::class, 'export'])
        ->middleware('can:finance.view')
        ->name('supplier-payments.export');

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

    Route::middleware(['auth', 'can:listings.manage'])
        ->prefix('marketplace-writes')
        ->name('marketplaces.writes.')
        ->group(function () {
            Route::get('/', [MarketplaceWriteExecutionController::class, 'index'])
                ->name('index');

            Route::patch('/connections/{connection}/policies/{operation}', [MarketplaceWriteExecutionController::class, 'togglePolicy'])
                ->name('policy');

            Route::post('/prepare', [MarketplaceWriteExecutionController::class, 'prepare'])
                ->name('prepare');

            Route::post('/attempts/{attempt}/approve-execute', [MarketplaceWriteExecutionController::class, 'approveExecute'])
                ->name('approve-execute');
        });

    Route::middleware(['auth', 'can:listings.manage'])
        ->prefix('marketplace-writes/verification')
        ->name('marketplaces.writes.verification.')
        ->group(function () {
            Route::get('/', [MarketplaceWriteVerificationController::class, 'index'])
                ->name('index');

            Route::get('/{attempt}/history', [MarketplaceWriteVerificationController::class, 'history'])
                ->name('history');

            Route::post('/{attempt}', [MarketplaceWriteVerificationController::class, 'store'])
                ->name('store');
        });

    Route::middleware(['auth', 'can:listings.manage'])
        ->prefix('marketplace-writes/audit')
        ->name('marketplaces.writes.audit.')
        ->group(function () {

            Route::get('/', [MarketplaceWriteAuditController::class, 'index'])
                ->name('index');

            Route::get('/export', [MarketplaceWriteAuditController::class, 'export'])
                ->name('export');
        });

    Route::middleware(['auth', 'can:listings.manage'])
        ->prefix('marketplace-writes/incidents')
        ->name('marketplaces.writes.incidents.')
        ->group(function () {

            Route::get('/', [MarketplaceWriteIncidentController::class, 'index'])
                ->name('index');

            Route::post('/', [MarketplaceWriteIncidentController::class, 'store'])
                ->name('store');

            Route::patch('/{incident}', [MarketplaceWriteIncidentController::class, 'update'])
                ->name('update');

            Route::get('/{incident}/history', [MarketplaceWriteIncidentController::class, 'history'])
                ->name('history');
        });

    Route::middleware(['auth', 'can:listings.manage'])
        ->prefix('marketplace-writes/sla')
        ->name('marketplaces.writes.sla.')
        ->group(function () {
            Route::get('/', [MarketplaceWriteSlaController::class, 'index'])
                ->name('index');

            Route::post('/policy', [MarketplaceWriteSlaController::class, 'savePolicy'])
                ->name('policy');

            Route::post('/incidents/{incident}/respond', [MarketplaceWriteSlaController::class, 'recordResponse'])
                ->name('respond');
        });

    Route::middleware(['auth', 'can:listings.manage'])
        ->prefix('marketplace-writes/escalations')
        ->name('marketplaces.writes.escalations.')
        ->group(function () {
            Route::get(
                '/',
                [MarketplaceWriteEscalationController::class, 'index']
            )->name('index');

            Route::post(
                '/rules',
                [MarketplaceWriteEscalationController::class, 'saveRule']
            )->name('rules');
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
        Route::post('/listings/{listing}/request-review', [ListingController::class, 'requestReview'])->name('listings.request-review');
        Route::post('/listings/{listing}/reviews/{review}/approve', [ListingController::class, 'approveReview'])->name('listings.reviews.approve');
        Route::post('/listings/{listing}/reviews/{review}/request-changes', [ListingController::class, 'requestChanges'])->name('listings.reviews.request-changes');
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

    // Phase 11: internal sales analytics and business decision dashboard
    Route::get('/analytics/business', [BusinessAnalyticsController::class, 'index'])->middleware('can:reports.view')->name('analytics.business');
    Route::get('/analytics/business/export', [BusinessAnalyticsController::class, 'export'])->middleware('can:reports.export')->name('analytics.business.export');

    // Reports (+ CSV / Excel / print)
    Route::middleware('can:reports.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/daily', [ReportController::class, 'daily'])->name('daily');
        Route::get('/{key}', [ReportController::class, 'show'])->where('key', '[a-z_]+')->name('show');
        Route::get('/{key}/print', [ReportController::class, 'print'])->where('key', '[a-z_]+')->name('print');
    });
    Route::get('/reports/{key}/export/{format}', [ReportController::class, 'export'])->middleware('can:reports.export')->where(['key' => '[a-z_]+', 'format' => 'csv|xlsx'])->name('reports.export');

    Route::get('/supplier-relationships', [SupplierRelationshipController::class, 'index'])
        ->middleware('can:purchases.view')
        ->name('supplier-relationships.index');

    Route::post('/supplier-relationships', [SupplierRelationshipController::class, 'store'])
        ->middleware('can:purchases.create')
        ->name('supplier-relationships.store');

    Route::patch('/supplier-relationships/{interaction}/status', [SupplierRelationshipController::class, 'updateStatus'])
        ->middleware('can:purchases.edit')
        ->name('supplier-relationships.status');

    // Phase 19: Supplier Document Vault & Compliance Management

    Route::get('/supplier-documents', [SupplierDocumentController::class, 'index'])
        ->middleware('can:purchases.view')
        ->name('supplier-documents.index');

    Route::post('/supplier-documents', [SupplierDocumentController::class, 'store'])
        ->middleware('can:purchases.create')
        ->name('supplier-documents.store');

    Route::get('/supplier-documents/{document}/download', [SupplierDocumentController::class, 'download'])
        ->middleware('can:purchases.view')
        ->name('supplier-documents.download');

    Route::patch('/supplier-documents/{document}/review', [SupplierDocumentController::class, 'review'])
        ->middleware('can:purchases.edit')
        ->name('supplier-documents.review');

    // Phase 20: Supplier Compliance Management
    Route::get('/supplier-compliance', [SupplierComplianceController::class, 'index'])
        ->middleware('can:purchases.view')
        ->name('supplier-compliance.index');

    // Phase 21: Supplier Risk Management
    Route::get('/supplier-risk', [SupplierRiskController::class, 'index'])
        ->middleware('can:purchases.view')
        ->name('supplier-risk.index');

    Route::get('/supplier-risk/export', [SupplierRiskController::class, 'export'])
        ->middleware('can:purchases.view')
        ->name('supplier-risk.export');

    // Phase 22: Supplier Sourcing Management
    Route::get('/supplier-sourcing', [SupplierSourcingController::class, 'index'])
        ->middleware('can:purchases.view')
        ->name('supplier-sourcing.index');

    // Phase 23: Supplier Cost Intelligence

    Route::get('/orders/operations/carrier-handover', [OrderOperationsInsightsController::class, 'carrierHandover'])
        ->middleware('can:orders.view')->name('orders.operations.carrier-handover');
    Route::get('/orders/operations/delivery-aging', [OrderOperationsInsightsController::class, 'deliveryAging'])
        ->middleware('can:orders.view')->name('orders.operations.delivery-aging');
    Route::get('/returns/operations/disposition-reconciliation', [OrderOperationsInsightsController::class, 'returnDisposition'])
        ->middleware('can:returns.view')->name('returns.operations.disposition-reconciliation');

    // Add these routes inside the existing authenticated route group in routes/web.php.
    // Place all three before dynamic routes such as /orders/{order} and /claims/{claim}.
    Route::get('/orders/operations/refund-reconciliation', [CommercialReconciliationController::class, 'refunds'])
        ->middleware('can:orders.view')->name('orders.operations.refund-reconciliation');
    Route::get('/claims/operations/recovery-review', [CommercialReconciliationController::class, 'claimRecovery'])
        ->middleware('can:claims.view')->name('claims.operations.recovery-review');
    Route::get('/orders/operations/settlement-coverage', [CommercialReconciliationController::class, 'settlementCoverage'])
        ->middleware('can:orders.view')->name('orders.operations.settlement-coverage');

    // Add these routes inside the existing authenticated routes group.
    // Keep these static finance routes before any dynamic /finance/{...} route.
    Route::get('/finance/operations/profitability-exceptions', [FinanceOperationsInsightController::class, 'profitabilityExceptions'])
        ->middleware('can:finance.view')->name('finance.operations.profitability-exceptions');
    Route::get('/finance/operations/refund-impact', [FinanceOperationsInsightController::class, 'refundImpact'])
        ->middleware('can:finance.view')->name('finance.operations.refund-impact');
    Route::get('/finance/operations/marketplace-performance', [FinanceOperationsInsightController::class, 'marketplacePerformance'])
        ->middleware('can:finance.view')->name('finance.operations.marketplace-performance');

    // Keep these static paths before any /finance/{...} catch-all routes.
    Route::get('/finance/operations/risk-monitor', [FinanceOperationsIntelligenceController::class, 'risk'])
        ->middleware('can:finance.view')->name('finance.operations.risk-monitor');
    Route::get('/finance/operations/profit-leakage', [FinanceOperationsIntelligenceController::class, 'leakage'])
        ->middleware('can:finance.view')->name('finance.operations.profit-leakage');
    Route::get('/finance/operations/settlement-performance', [FinanceOperationsIntelligenceController::class, 'settlements'])
        ->middleware('can:finance.view')->name('finance.operations.settlement-performance');

    // Place before any dynamic /finance/{...} route if your application has one.
    Route::get('/finance/operations/cash-forecast', [FinancialPlanningIntelligenceController::class, 'cashForecast'])
        ->middleware('can:finance.view')->name('finance.operations.cash-forecast');
    Route::get('/finance/operations/working-capital', [FinancialPlanningIntelligenceController::class, 'workingCapital'])
        ->middleware('can:finance.view')->name('finance.operations.working-capital');
    Route::get('/finance/operations/financial-health', [FinancialPlanningIntelligenceController::class, 'financialHealth'])
        ->middleware('can:finance.view')->name('finance.operations.financial-health');

    // Package 57 — Product Performance Scorecard
    Route::get('/analytics/product-performance', [ProductPerformanceController::class, 'index'])
        ->middleware('can:reports.view')->name('product-performance.index');
    Route::get('/analytics/product-performance/export', [ProductPerformanceController::class, 'export'])
        ->middleware('can:reports.export')->name('product-performance.export');

    // Package 58 — Executive Decision Dashboard
    Route::get('/executive/decision-dashboard', ExecutiveDecisionDashboardController::class)
        ->middleware('can:reports.view')->name('executive.decision-dashboard');

    // Package 59 — Six-month KPI Target vs Actual Trends
    Route::get('/business-performance/trends', [KpiTrendController::class, 'index'])
        ->middleware('can:reports.view')->name('business-performance.trends');

    // Modules not built yet
    Route::get('/m/{module}', ModuleController::class)->name('module');

    // Place before any catch-all/dynamic automation routes.
    Route::get('/automation/task-queue', [AutomationOperationsController::class, 'tasks'])
        ->middleware('can:reports.view')->name('automation.tasks.index');
    Route::post('/automation/task-queue', [AutomationOperationsController::class, 'storeFollowup'])
        ->middleware('can:purchases.edit')->name('automation.tasks.store');
    Route::get('/automation/alert-priorities', [AutomationOperationsController::class, 'priorities'])
        ->middleware('can:reports.view')->name('automation.alert-priorities.index');
    Route::get('/automation/workflow-monitor', [AutomationOperationsController::class, 'workflowMonitor'])
        ->middleware('can:reports.view')->name('automation.workflow-monitor.index');

    Route::get('/automation/tasks', [AutomationTaskController::class, 'index'])->middleware('can:reports.view')->name('automation.tasks.index');
    Route::post('/automation/tasks', [AutomationTaskController::class, 'store'])->middleware('can:purchases.edit')->name('automation.tasks.store');
    Route::patch('/automation/tasks/{task}/complete', [AutomationTaskController::class, 'complete'])->middleware('can:purchases.edit')->name('automation.tasks.complete');
    Route::post('/automation/recurring-tasks', [AutomationTaskController::class, 'storeRecurring'])->middleware('can:purchases.edit')->name('automation.recurring.store');
    Route::patch('/automation/recurring-tasks/{template}/toggle', [AutomationTaskController::class, 'toggleRecurring'])->middleware('can:purchases.edit')->name('automation.recurring.toggle');

    Route::get('/automation/planning/recommendations', [AutomationPlanningController::class, 'recommendations'])->middleware('can:reports.view')->name('automation.planning.recommendations');
    Route::get('/automation/planning/bottlenecks', [AutomationPlanningController::class, 'bottlenecks'])->middleware('can:reports.view')->name('automation.planning.bottlenecks');
    Route::get('/automation/planning/capacity', [AutomationPlanningController::class, 'capacity'])->middleware('can:reports.view')->name('automation.planning.capacity');
    Route::post('/automation/planning/capacity', [AutomationPlanningController::class, 'saveCapacity'])->middleware('can:purchases.edit')->name('automation.planning.capacity.update');
    Route::post('/automation/planning/task-effort', [AutomationPlanningController::class, 'saveEffort'])->middleware('can:purchases.edit')->name('automation.planning.task-effort.update');

    Route::get('/automation/optimization/deadline-risk', [AutomationOptimizationController::class, 'deadlineRisk'])->middleware('can:reports.view')->name('automation.optimization.deadline-risk');
    Route::get('/automation/optimization/dependency-release', [AutomationOptimizationController::class, 'dependencyRelease'])->middleware('can:reports.view')->name('automation.optimization.dependency-release');
    Route::get('/automation/optimization/rebalance-simulator', [AutomationOptimizationController::class, 'rebalanceSimulator'])->middleware('can:reports.view')->name('automation.optimization.rebalance-simulator');

    Route::get('/automation/intelligence/aging', [AutomationIntelligenceController::class, 'agingAnalysis'])->middleware('can:reports.view')->name('automation.intelligence.aging');
    Route::get('/automation/intelligence/throughput', [AutomationIntelligenceController::class, 'throughputAnalysis'])->middleware('can:reports.view')->name('automation.intelligence.throughput');
    Route::get('/automation/intelligence/dependency-impact', [AutomationIntelligenceController::class, 'dependencyImpact'])->middleware('can:reports.view')->name('automation.intelligence.dependency-impact');

    Route::get('/automation/governance/due-date-compliance', [AutomationGovernanceController::class, 'dueDateCompliance'])->middleware('can:reports.view')->name('automation.governance.due-date-compliance');
    Route::get('/automation/governance/assignment-health', [AutomationGovernanceController::class, 'assignmentHealth'])->middleware('can:reports.view')->name('automation.governance.assignment-health');
    Route::get('/automation/governance/priority-alignment', [AutomationGovernanceController::class, 'priorityAlignment'])->middleware('can:reports.view')->name('automation.governance.priority-alignment');

    Route::get('/automation/control-center', [AutomationControlCenterController::class, 'overview'])->middleware('can:reports.view')->name('automation.control-center');
    Route::get('/automation/control-center/export', [AutomationControlCenterController::class, 'export'])->middleware('can:reports.view')->name('automation.control-center.export');
    Route::get('/automation/workflow-readiness', [AutomationControlCenterController::class, 'readiness'])->middleware('can:reports.view')->name('automation.workflow-readiness');

    Route::get('/finance/marketplace-analytics', [MarketplaceFinancialAnalyticsController::class, 'index'])->middleware('can:finance.view')->name('finance.marketplace-analytics.index');

    Route::get('/analytics/marketplace-business-intelligence', [MarketplaceBusinessIntelligenceController::class, 'index'])->middleware('can:finance.view')->name('analytics.marketplace-business-intelligence.index');

    Route::get('/analytics/marketplace-recommendations', [MarketplaceRecommendationController::class, 'index'])->middleware('can:finance.view')->name('analytics.marketplace-recommendations.index');

    // Package 18: Marketplace Automation Rules & Approval Center.
    Route::middleware('can:finance.view')->prefix('automation/marketplace-rules')->name('automation.marketplace-rules.')->group(function () {
        Route::get('/', [MarketplaceAutomationRuleController::class, 'index'])->name('index');
        Route::post('/rules', [MarketplaceAutomationRuleController::class, 'store'])->middleware('can:orders.edit')->name('store');
        Route::patch('/rules/{rule}/toggle', [MarketplaceAutomationRuleController::class, 'toggle'])->middleware('can:orders.edit')->name('toggle');
        Route::post('/generate', [MarketplaceAutomationRuleController::class, 'generate'])->middleware('can:orders.edit')->name('generate');
        Route::post('/tasks/{task}/review', [MarketplaceAutomationRuleController::class, 'review'])->middleware('can:orders.edit')->name('review');
    });

});

Route::middleware('can:reports.view')->prefix('automation/workforce')->name('automation.workforce.')->group(function () {
    Route::get('/escalations', [AutomationWorkforceController::class, 'escalationIndex'])->name('escalations');
    Route::get('/workload', [AutomationWorkforceController::class, 'workloadIndex'])->name('workload');
    Route::get('/performance', [AutomationWorkforceController::class, 'performanceIndex'])->name('performance');
});
Route::middleware('can:purchases.edit')->prefix('automation/workforce')->name('automation.workforce.')->group(function () {
    Route::post('/tasks/{task}/escalate', [AutomationWorkforceController::class, 'escalate'])->name('escalate');
    Route::post('/tasks/{task}/reassign', [AutomationWorkforceController::class, 'reassign'])->name('reassign');
});

Route::post('/inventory/{product}/stock-count', [InventoryController::class, 'stockCount'])
    ->middleware('can:inventory.manage')
    ->name('inventory.stock-count');

Route::middleware('can:reports.view')->prefix('automation/insights')->name('automation.insights.')->group(function () {
    Route::get('/productivity', [AutomationInsightsController::class, 'productivity'])->name('productivity');
    Route::get('/dependencies', [AutomationInsightsController::class, 'dependencies'])->name('dependencies');
    Route::get('/analytics', [AutomationInsightsController::class, 'analytics'])->name('analytics');
});
Route::middleware('can:purchases.edit')->prefix('automation/insights')->name('automation.insights.')->group(function () {
    Route::post('/dependencies', [AutomationInsightsController::class, 'storeDependency'])->name('dependencies.store');
    Route::delete('/dependencies/{dependency}', [AutomationInsightsController::class, 'destroyDependency'])->name('dependencies.destroy');
});

Route::middleware(['auth', 'can:finance.view'])
    ->prefix('automation/marketplace-executions')
    ->name('automation.marketplace-executions.')
    ->group(function () {
        Route::get('/', [MarketplaceAutomationExecutionController::class, 'index'])
            ->name('index');

        Route::post('/tasks/{task}/dry-run', [MarketplaceAutomationExecutionController::class, 'dryRun'])
            ->middleware('can:orders.edit')
            ->name('dry-run');

        Route::post('/tasks/{task}/live', [MarketplaceAutomationExecutionController::class, 'live'])
            ->middleware('can:orders.edit')
            ->name('live');
    });

Route::middleware('auth')->group(function () {

    // Fulfilment (pack, dispatch, pickup, files)
    Route::get('/fulfilment-queue', [FulfilmentController::class, 'index'])->middleware('can:orders.view')->name('fulfilment.index');
    Route::put('/orders/{order}/fulfilment', [FulfilmentController::class, 'update'])->middleware('can:orders.edit')->name('fulfilment.update');
    Route::post('/orders/{order}/fulfilment/pack', [FulfilmentController::class, 'pack'])->middleware('can:orders.edit')->name('fulfilment.pack');
    Route::post('/orders/{order}/fulfilment/dispatch', [FulfilmentController::class, 'dispatch'])->middleware('can:orders.edit')->name('fulfilment.dispatch');
    Route::post('/orders/{order}/fulfilment/pickup', [FulfilmentController::class, 'pickup'])->middleware('can:orders.edit')->name('fulfilment.pickup');
    Route::get('/orders/{order}/fulfilment/files/{type}', [FulfilmentController::class, 'file'])->middleware('can:orders.view')->name('fulfilment.file');

    // Test orders (for testing fulfilment and returns)
    Route::get('/test-orders/create', [OrderController::class, 'createTest'])->middleware('can:orders.edit')->name('test-orders.create');
    Route::post('/test-orders', [OrderController::class, 'storeTest'])->middleware('can:orders.edit')->name('test-orders.store');
});

Route::middleware(['auth', 'can:listings.view'])->prefix('listing-readiness')->name('listing-readiness.')->group(function () {
    Route::get('/', [ListingReadinessController::class, 'index'])->name('index');
    Route::get('/export', [ListingReadinessController::class, 'export'])->name('export');
});

/*
|--------------------------------------------------------------------------
| Marketplace Write Evidence Retention & Compliance — Package 28
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'can:listings.manage'])->prefix('marketplace-writes/retention')->name('marketplaces.writes.retention.')->group(function () {

    // Retention dashboard
    Route::get(
        '/',
        [MarketplaceWriteRetentionController::class, 'index']
    )->name('index');

    // Save business retention policy
    Route::post(
        '/policy',
        [MarketplaceWriteRetentionController::class, 'savePolicy']
    )->name('policy');

    // Place legal hold on evidence
    Route::post(
        '/evidence/{evidence}/hold',
        [MarketplaceWriteRetentionController::class, 'placeHold']
    )->whereNumber('evidence')
        ->name('hold');

    // Release legal hold
    Route::post(
        '/holds/{hold}/release',
        [MarketplaceWriteRetentionController::class, 'releaseHold']
    )->whereNumber('hold')
        ->name('release');

    // Request evidence deletion
    Route::post(
        '/evidence/{evidence}/request-deletion',
        [MarketplaceWriteRetentionController::class, 'requestDeletion']
    )->whereNumber('evidence')
        ->name('request-deletion');

    // Approve deletion request
    Route::post(
        '/evidence/{evidence}/approve-deletion',
        [MarketplaceWriteRetentionController::class, 'approveDeletion']
    )->whereNumber('evidence')
        ->name('approve');
});

Route::middleware(['auth', 'can:listings.manage'])->prefix('marketplace-writes/governance')->name('marketplaces.writes.governance.')->group(function () {
    Route::get(
        '/',
        [MarketplaceWriteGovernanceController::class, 'index']
    )->name('index');

    Route::post(
        '/requests',
        [MarketplaceWriteGovernanceController::class, 'store']
    )->name('store');

    Route::post(
        '/requests/{governanceRequest}/review',
        [MarketplaceWriteGovernanceController::class, 'review']
    )->whereNumber('governanceRequest')
        ->name('review');
});

Route::middleware(['auth', 'can:listings.manage'])->prefix('marketplace-writes/governance')->name('marketplaces.writes.governance.')->group(function () {
    Route::get('/', [MarketplaceWriteGovernanceController::class, 'index'])
        ->name('index');

    Route::post('/requests', [MarketplaceWriteGovernanceController::class, 'store'])
        ->name('store');

    Route::post('/requests/{governanceRequest}/review', [MarketplaceWriteGovernanceController::class, 'review'])
        ->whereNumber('governanceRequest')
        ->name('review');

    Route::post('/emergency-stop', [MarketplaceWriteEmergencyController::class, 'activate'])
        ->name('emergency.activate');

    Route::post('/emergency-stop/release', [MarketplaceWriteEmergencyController::class, 'release'])
        ->name('emergency.release');
});


Route::middleware(['auth', 'can:listings.manage'])->prefix('marketplace-writes/monitoring')->name('marketplaces.writes.monitoring.')
->group(function () {
    Route::get('/', [MarketplaceWriteMonitoringController::class, 'index'])
        ->name('index');

    Route::post('/scan', [MarketplaceWriteMonitoringController::class, 'scan'])
        ->name('scan');

    Route::post('/incidents/{incident}/acknowledge', [MarketplaceWriteMonitoringController::class, 'acknowledge'])
        ->whereNumber('incident')
        ->name('acknowledge');

    Route::post('/incidents/{incident}/resolve', [MarketplaceWriteMonitoringController::class, 'resolve'])
        ->whereNumber('incident')
        ->name('resolve');
});

Route::middleware(['auth', 'can:listings.manage'])->prefix('marketplace-writes/retry-authorizations')->name('marketplaces.writes.retry-authorizations.')->group(function () {
    Route::get('/', [MarketplaceWriteRetryAuthorizationController::class, 'index'])
        ->name('index');

    Route::post('/', [MarketplaceWriteRetryAuthorizationController::class, 'store'])
        ->name('store');

    Route::post('/{authorization}/review', [MarketplaceWriteRetryAuthorizationController::class, 'review'])
        ->whereNumber('authorization')
        ->name('review');
});

/*
|--------------------------------------------------------------------------
| Marketplace Write Governance Alerts — Package 38
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'can:listings.manage'])->prefix('marketplace-writes/alerts')->name('marketplaces.writes.alerts.')->group(function () {

    // Notification center
    Route::get(
        '/',
        [App\Http\Controllers\MarketplaceWriteGovernanceAlertController::class, 'index']
    )->name('index');

    // Mark individual notification as read
    Route::post(
        '/{notification}/read',
        [App\Http\Controllers\MarketplaceWriteGovernanceAlertController::class, 'markRead']
    )->name('read');

    // Mark all marketplace-write notifications as read
    Route::post(
        '/read-all',
        [App\Http\Controllers\MarketplaceWriteGovernanceAlertController::class, 'markAllRead']
    )->name('read-all');

});

Route::middleware(['auth', 'can:listings.manage'])->prefix('marketplace-writes/alerts/preferences')->name('marketplaces.writes.alerts.preferences.')->group(function () {
    Route::get('/', [MarketplaceWriteAlertPreferenceController::class, 'edit'])
        ->name('edit');

    Route::put('/', [MarketplaceWriteAlertPreferenceController::class, 'update'])
        ->name('update');
});
