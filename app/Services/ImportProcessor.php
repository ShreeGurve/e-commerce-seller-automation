<?php
namespace App\Services;

use App\Marketplaces\{MarketplaceInterface, MarketplaceManager};
use App\Models\{DataImport, ImportRow, Order, OrderItem, Product, ProductMarketplace, ProductReturn, ReturnItem, Settlement, SettlementItem, AdvertisingCampaign, AdvertisingDaily, AdvertisingGroup, AdvertisingKeyword, AdvertisingSearchTerm, Keyword, KeywordGroup, KeywordRanking};
use App\Support\{FileReader, ImportValue, Money};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

/**
 * One engine for Orders / Returns / SKU mappings.  run($import, false) = dry run (validate only, writes nothing).
 * run($import, true) = real import. Orders and returns are all-or-nothing PER ORDER/RETURN; the rest of the file still imports.
 * Re-importing the same data is safe: existing marketplace ids are reported as DUPLICATE, never created twice.
 */
class ImportProcessor
{
    private DataImport $import;
    private MarketplaceInterface $adapter;
    private array $res = [];          // row number => [status, message, entityType, entityId]
    private array $skuCache = [];
    private array $samples = [];
    private int $created = 0;
    private array $touchedOrders = [];
    private array $touchedSettlements = [];
    private array $adDates = [];

    public function __construct(private OrderService $orders, private ReturnService $returns, private MarketplaceManager $markets) {}

    public function run(DataImport $import, bool $commit): array
    {
        set_time_limit(300);
        if ($commit) {
            ignore_user_abort(true);
            $claimed = DataImport::whereKey($import->id)->whereIn('status', ['MAPPED', 'FAILED'])->update(['status' => 'RUNNING', 'error_message' => null]);
            if (! $claimed) throw ValidationException::withMessages(['import' => 'This import is already running or finished. Upload the file again to re-run it.']);
        } elseif (! $import->mapping) {
            throw ValidationException::withMessages(['import' => 'Map the columns first.']);
        }
        $this->import = $import->fresh();
        $this->adapter = $this->markets->for($this->import->marketplace);
        $this->res = []; $this->samples = []; $this->created = 0; $this->skuCache = []; $this->touchedOrders = []; $this->touchedSettlements = []; $this->adDates = [];

        try {
            $rows = $this->load();
            match ($this->import->data_type) {
                'ORDERS' => $this->orders($rows, $commit),
                'RETURNS' => $this->returns($rows, $commit),
                'MAPPINGS' => $this->mappings($rows, $commit),
                'SETTLEMENTS' => $this->settlements($rows, $commit),
                'KEYWORDS' => $this->keywords($rows, $commit),
                'ADS_CAMPAIGN', 'ADS_KEYWORD', 'ADS_TERM', 'ADS_PRODUCT' => $this->ads($rows, $commit),
            };
            $summary = $this->summary($rows);
            if ($commit) { $this->persist($summary, $rows); $this->afterCommit(); }
            return $summary;
        } catch (\Throwable $e) {
            if ($commit) DataImport::whereKey($import->id)->update(['status' => 'FAILED', 'error_message' => substr($e->getMessage(), 0, 500)]);
            throw $e;
        }
    }

    // ------------------------------------------------------------------ loading
    private function load(): array
    {
        $map = $this->import->mapping;
        [$h, $gen] = FileReader::open(Storage::disk('local')->path($this->import->stored_path), pathinfo($this->import->file_name, PATHINFO_EXTENSION));
        $rows = [];
        foreach ($gen as $n => $raw) {
            $d = [];
            foreach ($map as $field => $col) $d[$field] = trim((string) ($raw[$col] ?? ''));
            $rows[] = ['n' => $n, 'raw' => $raw, 'd' => $d];
        }
        return $rows;
    }

    private function mark(int $n, string $status, ?string $msg = null, ?string $type = null, ?int $id = null): void
    {
        $this->res[$n] = [$status, $msg, $type, $id];
    }
    private function markAll(array $lines, string $status, string $msg): void { foreach ($lines as $l) $this->mark($l['n'], $status, $msg); }

    private function product(string $sku): ?Product
    {
        if (! array_key_exists($sku, $this->skuCache)) {
            $pid = ProductMarketplace::where('marketplace_id', $this->import->marketplace_id)->where('marketplace_sku', $sku)->value('product_id');
            // mapped SKU first, then fall back to our own product SKU
            $this->skuCache[$sku] = ($pid ? Product::find($pid) : null) ?? Product::where('sku', $sku)->first();
        }
        return $this->skuCache[$sku];
    }

    private function user()
    {
        return $this->import->uploader ?? auth()->user() ?? throw new \RuntimeException('No user for this import.');
    }

    // ------------------------------------------------------------------ ORDERS
    private function orders(array $rows, bool $commit): void
    {
        $opts = $this->import->options ?? [];
        $groups = [];
        foreach ($rows as $r) $groups[$r['d']['external_order_id'] !== '' ? $r['d']['external_order_id'] : '#blank' . $r['n']][] = $r;

        foreach ($groups as $oid => $lines) {
            if (str_starts_with($oid, '#blank')) { $this->mark($lines[0]['n'], 'ERROR', 'Order ID is empty.'); continue; }
            if (strlen($oid) > 60) { $this->markAll($lines, 'ERROR', 'Order ID is longer than 60 characters.'); continue; }
            if (Order::withTrashed()->where('marketplace_id', $this->import->marketplace_id)->where('external_order_id', $oid)->exists()) {
                $this->markAll($lines, 'DUPLICATE', 'Order already exists. Skipped.'); continue;
            }
            $clean = []; $bad = false; $seen = [];
            foreach ($lines as $r) {
                [$c, $err] = $this->cleanOrderLine($r['d'], $opts);
                if ($err) { $this->mark($r['n'], 'ERROR', $err); $bad = true; continue; }
                if ($c['item']['external_line_id'] && isset($seen[$c['item']['external_line_id']])) { $this->mark($r['n'], 'DUPLICATE', 'Order item ID repeated in this file. Skipped.'); continue; }
                if ($c['item']['external_line_id']) $seen[$c['item']['external_line_id']] = true;
                $clean[] = ['n' => $r['n'], 'c' => $c];
            }
            if ($bad) { foreach ($clean as $l) $this->mark($l['n'], 'ERROR', 'Order skipped because another line of this order has an error.'); continue; }
            if (! $clean) continue;

            $first = $clean[0]['c'];
            $data = [
                'marketplace_id' => $this->import->marketplace_id, 'external_order_id' => $oid, 'order_date' => $first['date'],
                'status' => $this->adapter->mapOrderStatus($first['status_raw']), 'marketplace_status' => substr((string) $first['status_raw'], 0, 60),
                'fulfillment_type' => $this->adapter->normalizeFulfillment($first['fulfillment_raw']), 'customer_ref' => substr($first['customer_ref'], 0, 120) ?: null,
                'ship_state' => substr($first['ship_state'], 0, 80) ?: null,
                'shipping_charged' => Money::m(array_sum(array_map(fn ($l) => Money::p($l['c']['shipping']), $clean))),
                'source' => 'CSV', 'import_id' => $this->import->id, 'deduct_stock' => (bool) ($opts['deduct_stock'] ?? true),
            ];
            $items = array_map(fn ($l) => $l['c']['item'], $clean);

            if (! $commit) {
                foreach ($clean as $l) $this->mark($l['n'], 'OK');
                $this->created++;
                if (count($this->samples) < 10) $this->samples[] = "Order {$oid}: " . count($items) . ' line(s), status ' . ucfirst(strtolower($data['status']));
                continue;
            }
            try {
                $order = $this->orders->create($data, $items, $this->user());
                foreach ($clean as $l) $this->mark($l['n'], 'OK', null, 'order', $order->id);
                $this->created++;
            } catch (ValidationException $e) {
                $this->markAll($clean, 'ERROR', collect($e->errors())->flatten()->first());
            } catch (\Throwable $e) {
                report($e);
                $this->markAll($clean, 'ERROR', 'Unexpected error (see storage/logs/laravel.log).');
            }
        }
    }

    /** @return array{0:?array,1:?string} */
    private function cleanOrderLine(array $d, array $opts): array
    {
        $sku = $d['sku'] ?? '';
        if ($sku === '') return [null, 'SKU is empty.'];
        $product = $this->product($sku);
        if (! $product) return [null, "Unknown SKU '{$sku}'. Map it first (Product > Marketplace tab, or import SKU mappings)."];
        $qty = ImportValue::int($d['quantity'] ?? null);
        if ($qty === null || $qty < 1) return [null, 'Quantity must be a whole number, 1 or more.'];
        $date = ImportValue::date($d['order_date'] ?? null, $opts['date_format'] ?? 'auto');
        if (! $date) return [null, 'Order date is missing or not in the chosen date format.'];
        if ($date->gt(now()->addDay())) return [null, 'Order date is in the future.'];
        $price = ImportValue::money($d['unit_price'] ?? null);
        if ($price === null) return [null, 'Item price is missing or invalid.'];
        $disc = ($d['discount'] ?? '') === '' ? '0.00' : ImportValue::money($d['discount']);
        $ship = ($d['shipping'] ?? '') === '' ? '0.00' : ImportValue::money($d['shipping']);
        if ($disc === null || $ship === null) return [null, 'Discount or shipping value is invalid.'];
        $gross = (($opts['price_mode'] ?? 'unit') === 'line') ? Money::p($price) : $qty * Money::p($price);
        if (Money::p($disc) > $gross) return [null, 'Discount is more than the item value.'];

        $item = ['product_id' => $product->id, 'quantity' => $qty, 'discount' => $disc, 'marketplace_sku' => $sku, 'external_line_id' => ($d['external_line_id'] ?? '') ?: null];
        if (($opts['price_mode'] ?? 'unit') === 'line') { $item['line_total'] = $price; $item['unit_price'] = Money::m((int) round(Money::p($price) / $qty)); }
        else $item['unit_price'] = $price;

        return [['item' => $item, 'date' => $date, 'status_raw' => $d['status'] ?? null, 'fulfillment_raw' => $d['fulfillment'] ?? null,
            'customer_ref' => $d['customer_ref'] ?? '', 'ship_state' => $d['ship_state'] ?? '', 'shipping' => $ship], null];
    }

    // ------------------------------------------------------------------ RETURNS
    private function returns(array $rows, bool $commit): void
    {
        $opts = $this->import->options ?? [];
        $groups = [];
        foreach ($rows as $r) $groups[$r['d']['external_return_id'] !== '' ? $r['d']['external_return_id'] : '#blank' . $r['n']][] = $r;
        $alloc = [];

        foreach ($groups as $rid => $lines) {
            if (str_starts_with($rid, '#blank')) { $this->mark($lines[0]['n'], 'ERROR', 'Return ID is empty.'); continue; }
            if (ProductReturn::withTrashed()->where('marketplace_id', $this->import->marketplace_id)->where('external_return_id', $rid)->exists()) {
                $this->markAll($lines, 'DUPLICATE', 'Return already exists. Skipped.'); continue;
            }
            $orderIds = array_unique(array_column(array_column($lines, 'd'), 'external_order_id'));
            if (count($orderIds) !== 1 || $orderIds[0] === '') { $this->markAll($lines, 'ERROR', 'Return lines must all belong to one order.'); continue; }
            $order = Order::where('marketplace_id', $this->import->marketplace_id)->where('external_order_id', $orderIds[0])->first();
            if (! $order) { $this->markAll($lines, 'ERROR', "Order {$orderIds[0]} not found. Import the orders first."); continue; }
            if (! in_array($order->status, ['SHIPPED', 'DELIVERED', 'RETURNED'], true)) { $this->markAll($lines, 'ERROR', "Order {$orderIds[0]} is {$order->status}; returns need a shipped order."); continue; }

            $date = ImportValue::date($lines[0]['d']['return_date'] ?? null, $opts['date_format'] ?? 'auto');
            if (! $date || $date->gt(now()->addDay())) { $this->markAll($lines, 'ERROR', 'Return date is missing, invalid or in the future.'); continue; }

            $qtys = []; $bad = false;
            foreach ($lines as $l) {
                $sku = $l['d']['sku'] ?? '';
                $product = $sku === '' ? null : $this->product($sku);
                $qty = ImportValue::int($l['d']['quantity'] ?? null);
                if (! $product) { $this->mark($l['n'], 'ERROR', "Unknown SKU '{$sku}'."); $bad = true; continue; }
                if ($qty === null || $qty < 1) { $this->mark($l['n'], 'ERROR', 'Quantity must be a whole number, 1 or more.'); $bad = true; continue; }
                $item = null;
                foreach (OrderItem::where('order_id', $order->id)->where('product_id', $product->id)->get() as $cand) {
                    $left = $cand->quantity - (int) ReturnItem::where('order_item_id', $cand->id)->sum('quantity') - ($commit ? 0 : ($alloc[$cand->id] ?? 0)) - ($qtys[$cand->id] ?? 0);
                    if ($left >= $qty) { $item = $cand; break; }
                }
                if (! $item) { $this->mark($l['n'], 'ERROR', "SKU '{$sku}' is not on order {$order->external_order_id} or the quantity is more than what is left to return."); $bad = true; continue; }
                $qtys[$item->id] = ($qtys[$item->id] ?? 0) + $qty;
            }
            if ($bad) { foreach ($lines as $l) if (! isset($this->res[$l['n']])) $this->mark($l['n'], 'ERROR', 'Return skipped because another line has an error.'); continue; }

            if (! $commit) {
                foreach ($qtys as $id => $q) $alloc[$id] = ($alloc[$id] ?? 0) + $q;
                $this->markAll($lines, 'OK', null); $this->created++;
                if (count($this->samples) < 10) $this->samples[] = "Return {$rid} on order {$order->external_order_id}: " . array_sum($qtys) . ' unit(s)';
                continue;
            }
            try {
                $ret = $this->returns->create($order, [
                    'return_type' => ImportValue::returnType($lines[0]['d']['reason'] ?? ''), 'return_date' => $date->toDateString(),
                    'reason_text' => substr($lines[0]['d']['reason'] ?? '', 0, 255) ?: null, 'external_return_id' => $rid,
                ], $qtys, $this->user());
                foreach ($lines as $l) $this->mark($l['n'], 'OK', null, 'return', $ret->id);
                $this->created++;
            } catch (ValidationException $e) {
                $this->markAll($lines, 'ERROR', collect($e->errors())->flatten()->first());
            } catch (\Throwable $e) {
                report($e);
                $this->markAll($lines, 'ERROR', 'Unexpected error (see storage/logs/laravel.log).');
            }
        }
    }

    // ------------------------------------------------------------------ SKU MAPPINGS
    private function mappings(array $rows, bool $commit): void
    {
        $seen = [];
        foreach ($rows as $r) {
            $d = $r['d']; $n = $r['n'];
            $msku = $d['marketplace_sku'] ?? ''; $psku = $d['product_sku'] ?? '';
            if ($msku === '' || $psku === '') { $this->mark($n, 'ERROR', 'Marketplace SKU and product SKU are both required.'); continue; }
            $product = Product::where('sku', $psku)->first();
            if (! $product) { $this->mark($n, 'ERROR', "Our product SKU '{$psku}' does not exist."); continue; }
            $status = strtolower($d['listing_status'] ?? '');
            $status = in_array($status, ['active', 'inactive', 'suppressed', 'draft'], true) ? $status : 'active';
            $vals = ['product_id' => $product->id, 'external_product_id' => ($d['external_product_id'] ?? '') ?: null, 'fulfillment_sku' => ($d['fulfillment_sku'] ?? '') ?: null, 'listing_status' => $status];

            $existing = ProductMarketplace::where('marketplace_id', $this->import->marketplace_id)->where('marketplace_sku', $msku)->first();
            $prevProduct = $existing?->product_id ?? ($seen[$msku] ?? null);
            if ($prevProduct && $prevProduct !== $product->id) { $this->mark($n, 'ERROR', "'{$msku}' is already mapped to a different product. Change it on the product page if that is intended."); continue; }
            if (isset($seen[$msku]) || ($existing && $existing->external_product_id === $vals['external_product_id'] && $existing->fulfillment_sku === $vals['fulfillment_sku'] && $existing->listing_status === $status)) {
                $this->mark($n, 'DUPLICATE', 'Mapping already exists with the same details.'); continue;
            }
            $seen[$msku] = $product->id;
            if ($commit) {
                $row = ProductMarketplace::updateOrCreate(['marketplace_id' => $this->import->marketplace_id, 'marketplace_sku' => $msku], $vals);
                $this->mark($n, 'OK', null, 'mapping', $row->id);
            } else $this->mark($n, 'OK');
            $this->created++;
            if (count($this->samples) < 10) $this->samples[] = "{$msku} → {$psku}";
        }
    }


    // ------------------------------------------------------------------ SETTLEMENTS
    /** one-column-per-item format: field => [kind, is a deduction column] */
    private const WIDE = [
        'sale_amount' => ['SALE', false], 'shipping_credit' => ['SHIPPING_CREDIT', false], 'refund_amount' => ['REFUND', true],
        'referral_fee' => ['REFERRAL_FEE', true], 'closing_fee' => ['CLOSING_FEE', true], 'shipping_fee' => ['SHIPPING_FEE', true],
        'collection_fee' => ['COLLECTION_FEE', true], 'payment_fee' => ['PAYMENT_FEE', true], 'other_fee' => ['OTHER_FEE', true],
        'fee_tax' => ['FEE_TAX', true], 'reimbursement' => ['REIMBURSEMENT', false], 'adjustment' => ['ADJUSTMENT', false],
    ];

    /** @return array{0:array,1:?string} lines [kind, signed amount "12.34", description] or an error */
    private function settlementLines(array $d, array $opts): array
    {
        $out = []; $any = false;
        if (($d['amount'] ?? '') !== '') {
            $any = true;
            $amt = ImportValue::signed($d['amount']);
            if ($amt === null) return [[], 'Amount is not a valid number.'];
            if ((float) $amt != 0) $out[] = [$this->adapter->classifySettlementLine($d['txn_type'] ?? null, $d['amount_type'] ?? null, $d['amount_desc'] ?? null), $amt,
                substr(trim(($d['amount_type'] ?? '') . ' ' . ($d['amount_desc'] ?? '')), 0, 255)];
        }
        $dedPositive = (bool) ($opts['deductions_positive'] ?? true);
        foreach (self::WIDE as $field => [$kind, $isDeduction]) {
            if (($d[$field] ?? '') === '') continue;
            $any = true;
            $n = ImportValue::signed($d[$field]);
            if ($n === null) return [[], "'" . $d[$field] . "' is not a valid number (" . (DataImport::TYPES['SETTLEMENTS']['fields'][$field][0] ?? $field) . ').'];
            if ((float) $n == 0) continue;
            $out[] = [$kind, number_format($isDeduction && $dedPositive ? -abs((float) $n) : (float) $n, 2, '.', ''), null];
        }
        return $any ? [$out, null] : [[], 'No amount found on this row.'];
    }

    private function settlements(array $rows, bool $commit): void
    {
        $opts = $this->import->options ?? [];
        $mp = $this->import->marketplace_id;
        $groups = [];
        foreach ($rows as $r) $groups[$r['d']['external_settlement_id'] !== '' ? $r['d']['external_settlement_id'] : '#blank' . $r['n']][] = $r;
        $orderIds = [];

        foreach ($groups as $sid => $lines) {
            if (str_starts_with($sid, '#blank')) { $this->mark($lines[0]['n'], 'ERROR', 'Settlement ID is empty.'); continue; }
            if (strlen($sid) > 60) { $this->markAll($lines, 'ERROR', 'Settlement ID is longer than 60 characters.'); continue; }
            $existing = Settlement::where('marketplace_id', $mp)->where('external_settlement_id', $sid)->first();
            $known = $existing ? SettlementItem::where('settlement_id', $existing->id)->pluck('row_hash')->flip()->all() : [];
            $occ = []; $batch = []; $dates = [];

            foreach ($lines as $r) {
                $d = $r['d'];
                $posted = ImportValue::date($d['posted_date'] ?? null, $opts['date_format'] ?? 'auto');
                if (! $posted) { $this->mark($r['n'], 'ERROR', 'Posted date is missing or not in the chosen date format.'); continue; }
                if ($posted->gt(now()->addDay())) { $this->mark($r['n'], 'ERROR', 'Posted date is in the future.'); continue; }
                [$items, $err] = $this->settlementLines($d, $opts);
                if ($err) { $this->mark($r['n'], 'ERROR', $err); continue; }
                if (! $items) { $this->mark($r['n'], 'OK', 'Zero amounts. Nothing stored.'); continue; }

                $new = 0; $dup = 0; $oid = ($d['external_order_id'] ?? '') ?: null; $sku = ($d['sku'] ?? '') ?: null;
                foreach ($items as [$kind, $amt, $desc]) {
                    $key = sha1(implode('|', [$sid, $oid, $sku, $kind, $amt, $posted->toDateString(), $desc]));
                    $occ[$key] = ($occ[$key] ?? 0) + 1;                       // identical lines in one file are legitimate; count them
                    $hash = sha1($key . '#' . $occ[$key]);
                    if (isset($known[$hash]) || isset($batch[$hash])) { $dup++; continue; }
                    $batch[$hash] = ['kind' => $kind, 'amount' => $amt, 'desc' => $desc, 'oid' => $oid, 'sku' => $sku, 'date' => $posted->toDateString(), 'n' => $r['n']];
                    $new++;
                }
                $dates[] = $posted->toDateString();
                $this->mark($r['n'], $new > 0 ? 'OK' : 'DUPLICATE', $new > 0 ? null : 'Already imported. Skipped.');
            }
            if (! $batch) continue;
            $net = array_sum(array_map(fn ($b) => (float) $b['amount'], $batch));

            if (! $commit) {
                $this->created++;
                if (count($this->samples) < 10) $this->samples[] = "Settlement {$sid}: " . count($batch) . ' new line(s), net ₹' . number_format($net, 2);
                continue;
            }
            $stId = null;
            try {
                DB::transaction(function () use ($sid, $mp, $batch, $dates, &$orderIds, &$stId) {
                    $st = Settlement::firstOrCreate(['marketplace_id' => $mp, 'external_settlement_id' => $sid], ['import_id' => $this->import->id]);
                    $orders = []; $now = now();
                    $insert = [];
                    foreach ($batch as $hash => $b) {
                        $oid = null; $itemId = null;
                        if ($b['oid']) {
                            $oid = $orders[$b['oid']] ??= (Order::where('marketplace_id', $mp)->where('external_order_id', $b['oid'])->value('id') ?: 0);
                            if ($oid && $b['sku'] && ($p = $this->product($b['sku']))) $itemId = OrderItem::where('order_id', $oid)->where('product_id', $p->id)->value('id');
                            if ($oid) $orderIds[$oid] = true;
                        }
                        $insert[] = ['settlement_id' => $st->id, 'order_id' => $oid ?: null, 'order_item_id' => $itemId, 'external_order_id' => $b['oid'], 'sku' => $b['sku'],
                            'kind' => $b['kind'], 'description' => $b['desc'], 'amount' => $b['amount'], 'posted_date' => $b['date'], 'row_hash' => $hash, 'created_at' => $now];
                    }
                    foreach (array_chunk($insert, 500) as $c) SettlementItem::insertOrIgnore($c);   // unique(settlement,row_hash) = idempotent
                    $agg = SettlementItem::where('settlement_id', $st->id)->selectRaw('COUNT(*) c, SUM(amount) s, MIN(posted_date) a, MAX(posted_date) b')->first();
                    $st->update(['item_count' => $agg->c, 'total_amount' => $agg->s ?? 0, 'period_start' => $agg->a, 'period_end' => $agg->b]);
                    $this->touchedSettlements[$st->id] = true;
                    $stId = $st->id;
                });
                foreach ($lines as $l) if (($this->res[$l['n']][0] ?? '') === 'OK') { $this->res[$l['n']][2] = 'settlement'; $this->res[$l['n']][3] = $stId; }
                $this->created++;
            } catch (\Throwable $e) {
                report($e);
                $this->markAll($lines, 'ERROR', 'Unexpected error while saving this settlement (see storage/logs/laravel.log).');
            }
        }
        $this->touchedOrders = array_keys($orderIds);
    }


    // ------------------------------------------------------------------ ADVERTISING
    private const AD_LEVEL = ['ADS_CAMPAIGN' => 'CAMPAIGN', 'ADS_KEYWORD' => 'KEYWORD', 'ADS_TERM' => 'TERM', 'ADS_PRODUCT' => 'PRODUCT'];

    private static function matchType(string $raw): string
    {
        $r = strtolower($raw);
        return match (true) { $r === '' => '', str_contains($r, 'broad') => 'BROAD', str_contains($r, 'phrase') => 'PHRASE', str_contains($r, 'exact') => 'EXACT',
            str_contains($r, 'auto') || str_contains($r, 'close') || str_contains($r, 'loose') || $r === '*' => 'AUTO', default => strtoupper(substr($raw, 0, 12)) };
    }

    /** Same day + campaign + target = same row. A newer report for the same day UPDATES the numbers (ad sales get revised for days). */
    private function ads(array $rows, bool $commit): void
    {
        $level = self::AD_LEVEL[$this->import->data_type]; $opts = $this->import->options ?? []; $mp = $this->import->marketplace_id;
        $seen = []; $ids = ['c' => [], 'g' => [], 'k' => [], 't' => []];
        $new = 0; $upd = 0; $spendTotal = 0;

        foreach ($rows as $r) {
            $d = $r['d']; $n = $r['n'];
            $date = ImportValue::date($d['date'] ?? null, $opts['date_format'] ?? 'auto');
            if (! $date) { $this->mark($n, 'ERROR', 'Date is missing or not in the chosen date format.'); continue; }
            if ($date->gt(now()->addDay())) { $this->mark($n, 'ERROR', 'Date is in the future.'); continue; }
            $campaign = trim($d['campaign'] ?? '');
            if ($campaign === '' || mb_strlen($campaign) > 200) { $this->mark($n, 'ERROR', 'Campaign name is empty or longer than 200 characters.'); continue; }
            $group = trim($d['ad_group'] ?? '') ?: '(no ad group)';
            $kw = trim($d['keyword'] ?? ''); $match = self::matchType(trim($d['match_type'] ?? '')); $term = trim($d['search_term'] ?? ''); $product = null;
            if ($level === 'KEYWORD' && $kw === '') { $this->mark($n, 'ERROR', 'Keyword / targeting is empty.'); continue; }
            if ($level === 'TERM' && $term === '') { $this->mark($n, 'ERROR', 'Customer search term is empty.'); continue; }
            if (mb_strlen($kw) > 255 || mb_strlen($term) > 255) { $this->mark($n, 'ERROR', 'Keyword or search term is longer than 255 characters.'); continue; }
            if ($level === 'PRODUCT') {
                $sku = trim($d['sku'] ?? '');
                $product = $sku === '' ? null : $this->product($sku);
                if (! $product) { $this->mark($n, 'ERROR', "Unknown advertised SKU '{$sku}'. Map it first (Product > Marketplace tab, or import SKU mappings)."); continue; }
            }
            $imp = ($d['impressions'] ?? '') === '' ? 0 : ImportValue::int($d['impressions']);
            $clk = ($d['clicks'] ?? '') === '' ? 0 : ImportValue::int($d['clicks']);
            $ord = ($d['orders'] ?? '') === '' ? 0 : ImportValue::int($d['orders']);
            $uni = ($d['units'] ?? '') === '' ? 0 : ImportValue::int($d['units']);
            $spend = ImportValue::money($d['spend'] ?? null);
            $sales = ($d['sales'] ?? '') === '' ? '0.00' : ImportValue::money($d['sales']);
            if ($imp === null || $clk === null || $ord === null || $uni === null || $imp < 0 || $clk < 0 || $ord < 0 || $uni < 0) { $this->mark($n, 'ERROR', 'Impressions, clicks, orders and units must be whole numbers, 0 or more.'); continue; }
            if ($spend === null || $sales === null) { $this->mark($n, 'ERROR', 'Spend (and sales, if given) must be valid amounts.'); continue; }

            $key = sha1(implode('|', [$level, $mp, $date->toDateString(), mb_strtolower($campaign), mb_strtolower($group), mb_strtolower($kw), $match, mb_strtolower($term), $product?->id]));
            if (isset($seen[$key])) { $this->mark($n, 'ERROR', 'Same date, campaign and target appears twice in this file. Combine them first.'); continue; }
            $seen[$key] = true;

            $vals = ['impressions' => $imp, 'clicks' => $clk, 'spend' => $spend, 'sales' => $sales, 'orders' => $ord, 'units' => $uni];
            $ex = AdvertisingDaily::where('row_key', $key)->first();
            if ($ex && (int) $ex->impressions === $imp && (int) $ex->clicks === $clk && (int) $ex->orders === $ord && (int) $ex->units === $uni
                && Money::p($ex->spend) === Money::p($spend) && Money::p($ex->sales) === Money::p($sales)) {
                $this->mark($n, 'DUPLICATE', 'Same numbers already imported. Skipped.'); continue;
            }
            $ex ? $upd++ : $new++; $spendTotal += Money::p($spend);
            $this->adDates[] = $date->toDateString();
            if (! $commit) { $this->mark($n, 'OK', $ex ? 'Will update older numbers.' : null); continue; }

            try {
                $cid = $ids['c'][mb_strtolower($campaign)] ??= AdvertisingCampaign::firstOrCreate(['marketplace_id' => $mp, 'name' => $campaign])->id;
                $gid = $ids['g'][$cid . '|' . mb_strtolower($group)] ??= AdvertisingGroup::firstOrCreate(['campaign_id' => $cid, 'name' => $group])->id;
                $kid = null; $tid = null;
                if ($kw !== '') {
                    $kid = $ids['k'][$gid . '|' . $match . '|' . mb_strtolower($kw)] ??= AdvertisingKeyword::firstOrCreate(
                        ['group_id' => $gid, 'match_type' => $match, 'key_hash' => sha1(mb_strtolower($kw))], ['campaign_id' => $cid, 'keyword' => $kw])->id;
                }
                if ($term !== '') {
                    $tid = $ids['t'][$gid . '|' . mb_strtolower($kw . '|' . $match) . '|' . mb_strtolower($term)] ??= AdvertisingSearchTerm::firstOrCreate(
                        ['group_id' => $gid, 'target_hash' => sha1(mb_strtolower($kw . '|' . $match)), 'key_hash' => sha1(mb_strtolower($term))],
                        ['campaign_id' => $cid, 'keyword_id' => $kid, 'search_term' => $term])->id;
                }
                if ($ex) $ex->update($vals + ['import_id' => $this->import->id]);
                else AdvertisingDaily::create($vals + ['marketplace_id' => $mp, 'date' => $date->toDateString(), 'level' => $level, 'campaign_id' => $cid, 'group_id' => $gid,
                    'keyword_id' => $kid, 'search_term_id' => $tid, 'product_id' => $product?->id, 'row_key' => $key, 'import_id' => $this->import->id]);
                $this->mark($n, 'OK', $ex ? 'Updated with newer numbers.' : null, 'ads', null);
            } catch (\Throwable $e) {
                report($e);
                $this->mark($n, 'ERROR', 'Unexpected error while saving this row (see storage/logs/laravel.log).');
            }
        }
        $this->created = $new + $upd;
        if ($this->created && count($this->samples) < 10) $this->samples[] = "{$new} new row(s), {$upd} updated row(s), total spend ₹" . number_format($spendTotal / 100, 2) . ' (' . strtolower($level) . ' level)';
    }


    // ------------------------------------------------------------------ KEYWORDS (+ rankings)
    private const KW_TYPES = ['primary' => 'PRIMARY', 'secondary' => 'SECONDARY', 'longtail' => 'LONG_TAIL', 'backend' => 'BACKEND', 'aeo' => 'AEO', 'seo' => 'SEO', 'geo' => 'GEO'];

    /** New keywords are created; existing ones are updated only with the columns you gave; a row that changes nothing is a Duplicate. */
    private function keywords(array $rows, bool $commit): void
    {
        $opts = $this->import->options ?? []; $mp = $this->import->marketplace_id; $seen = []; $new = 0; $upd = 0;
        foreach ($rows as $r) {
            $d = $r['d']; $n = $r['n'];
            $sku = trim($d['sku'] ?? ''); $product = $sku === '' ? null : $this->product($sku);
            if (! $product) { $this->mark($n, 'ERROR', "Unknown SKU '{$sku}'. Map it first (Product > Marketplace tab, or import SKU mappings)."); continue; }
            $kw = trim($d['keyword'] ?? '');
            if ($kw === '' || mb_strlen($kw) > 255) { $this->mark($n, 'ERROR', 'Keyword is empty or longer than 255 characters.'); continue; }
            $type = null;
            if (($d['keyword_type'] ?? '') !== '') {
                $type = self::KW_TYPES[\App\Marketplaces\BaseAdapter::norm($d['keyword_type'])] ?? null;
                if (! $type) { $this->mark($n, 'ERROR', "Unknown keyword type '{$d['keyword_type']}'. Use primary, secondary, long-tail, backend, AEO, SEO or GEO."); continue; }
            }
            $vol = ($d['search_volume'] ?? '') === '' ? null : ImportValue::int($d['search_volume']);
            $rel = ($d['relevance'] ?? '') === '' ? null : ImportValue::int($d['relevance']);
            $rank = ($d['rank'] ?? '') === '' ? null : ImportValue::int($d['rank']);
            if ((($d['search_volume'] ?? '') !== '' && ($vol === null || $vol < 0)) || (($d['relevance'] ?? '') !== '' && ($rel === null || $rel < 0 || $rel > 100)) || (($d['rank'] ?? '') !== '' && ($rank === null || $rank < 1))) {
                $this->mark($n, 'ERROR', 'Search volume must be 0 or more, relevance 0-100, rank 1 or more (whole numbers).'); continue;
            }
            $comp = null;
            if (($d['competition'] ?? '') !== '') {
                $c = strtoupper(trim($d['competition'])); $comp = in_array($c, ['LOW', 'MEDIUM', 'HIGH'], true) ? $c : (str_starts_with($c, 'MED') ? 'MEDIUM' : null);
                if (! $comp) { $this->mark($n, 'ERROR', "Competition '{$d['competition']}' must be low, medium or high."); continue; }
            }
            $rdate = ($d['rank_date'] ?? '') === '' ? now() : ImportValue::date($d['rank_date'], $opts['date_format'] ?? 'auto');
            if ($rank !== null && (! $rdate || $rdate->gt(now()->addDay()))) { $this->mark($n, 'ERROR', 'Rank date is invalid or in the future.'); continue; }
            $hash = Keyword::hash($kw);
            if (isset($seen[$product->id . $hash])) { $this->mark($n, 'ERROR', 'Same keyword appears twice for this product in this file.'); continue; }
            $seen[$product->id . $hash] = true;

            $given = array_filter(['keyword_type' => $type, 'search_volume' => $vol, 'competition' => $comp, 'relevance' => $rel, 'source' => ($d['source'] ?? '') ?: null], fn ($v) => $v !== null);
            $ex = Keyword::where(['product_id' => $product->id, 'marketplace_id' => $mp, 'key_hash' => $hash])->first();
            $diff = $ex ? array_filter($given, fn ($v, $k) => (string) $ex->{$k} !== (string) $v, ARRAY_FILTER_USE_BOTH) : $given;
            $groupName = trim($d['group'] ?? ''); $groupChange = $ex && $groupName !== '' && optional($ex->group)->name !== $groupName;
            $rankChange = $rank !== null && (! $ex || ! KeywordRanking::where(['keyword_id' => $ex->id, 'checked_on' => $rdate->toDateString(), 'rank_type' => 'ORGANIC', 'rank' => $rank])->exists());
            if ($ex && ! $diff && ! $groupChange && ! $rankChange) { $this->mark($n, 'DUPLICATE', 'Nothing new for this keyword. Skipped.'); continue; }
            $ex ? $upd++ : $new++;
            if (! $commit) { $this->mark($n, 'OK', $ex ? 'Will update this keyword.' : null); continue; }
            try {
                $gid = $groupName !== '' ? KeywordGroup::firstOrCreate(['name' => mb_substr($groupName, 0, 255)])->id : null;
                if ($ex) { $ex->update($diff + ($gid ? ['group_id' => $gid] : [])); $k = $ex; }
                else $k = Keyword::create($given + ['product_id' => $product->id, 'marketplace_id' => $mp, 'keyword' => $kw, 'key_hash' => $hash, 'keyword_type' => $type ?? 'SECONDARY', 'group_id' => $gid, 'created_by' => $this->import->uploaded_by]);
                if ($rank !== null) {
                    KeywordRanking::updateOrCreate(['keyword_id' => $k->id, 'checked_on' => $rdate->toDateString(), 'rank_type' => 'ORGANIC'], ['rank' => $rank, 'source' => $given['source'] ?? null, 'created_by' => $this->import->uploaded_by]);
                    if (! $k->rank_checked_on || $k->rank_checked_on->toDateString() <= $rdate->toDateString()) $k->update(['current_rank' => $rank, 'rank_checked_on' => $rdate->toDateString()]);
                }
                $this->mark($n, 'OK', $ex ? 'Updated.' : null, 'keyword', $k->id);
            } catch (\Throwable $e) {
                report($e); $this->mark($n, 'ERROR', 'Unexpected error while saving this keyword (see storage/logs/laravel.log).');
            }
        }
        $this->created = $new + $upd;
        if ($this->created && count($this->samples) < 10) $this->samples[] = "{$new} new keyword(s), {$upd} updated";
    }

    /** After a real import: refresh profit and reconciliation for everything it touched. Never fails the import itself. */
    private function afterCommit(): void
    {
        try {
            $profit = app(ProfitService::class);
            $recon = app(ReconciliationService::class);
            if ($this->import->data_type === 'SETTLEMENTS') {
                foreach ($this->touchedOrders as $id) { if ($o = Order::find($id)) $profit->safe($o); }
                $recon->run($this->touchedOrders, array_keys($this->touchedSettlements));
            } elseif (str_starts_with($this->import->data_type, 'ADS_') && $this->adDates) {
                app(AdAllocationService::class)->allocateRange(min($this->adDates), max($this->adDates));   // ad cost lands on the sales it paid for
            } elseif ($this->import->data_type === 'ORDERS' && SettlementItem::whereNull('order_id')->exists()) {
                $recon->run([]);                           // payouts that arrived before their order get linked now
            }
        } catch (\Throwable $e) { report($e); }
    }

    // ------------------------------------------------------------------ results
    private function summary(array $rows): array
    {
        $c = ['OK' => 0, 'ERROR' => 0, 'DUPLICATE' => 0]; $issues = [];
        foreach ($rows as $r) {
            [$st, $msg] = $this->res[$r['n']] ?? ['ERROR', 'Row was not processed.'];
            $c[$st]++;
            if ($st !== 'OK' && count($issues) < 100) $issues[] = ['n' => $r['n'], 'status' => $st, 'message' => $msg];
        }
        return ['total' => count($rows), 'ok' => $c['OK'], 'error' => $c['ERROR'], 'duplicate' => $c['DUPLICATE'], 'created' => $this->created, 'issues' => $issues, 'samples' => $this->samples];
    }

    private function persist(array $s, array $rows): void
    {
        DB::transaction(function () use ($s, $rows) {
            ImportRow::where('import_id', $this->import->id)->delete();
            $batch = [];
            foreach ($rows as $r) {
                [$st, $msg, $type, $id] = $this->res[$r['n']] ?? ['ERROR', 'Row was not processed.', null, null];
                $batch[] = ['import_id' => $this->import->id, 'row_number' => $r['n'], 'status' => $st, 'message' => $msg,
                    'raw' => $st === 'OK' ? null : json_encode($r['raw'], JSON_UNESCAPED_UNICODE), 'entity_type' => $type, 'entity_id' => $id, 'created_at' => now()];
                if (count($batch) >= 500) { ImportRow::insert($batch); $batch = []; }
            }
            if ($batch) ImportRow::insert($batch);
            $this->import->update(['status' => 'IMPORTED', 'imported_at' => now(), 'total_rows' => $s['total'], 'ok_rows' => $s['ok'], 'failed_rows' => $s['error'], 'duplicate_rows' => $s['duplicate']]);
        });
        ActivityLogger::log('import.completed', $this->import, null, ['type' => $this->import->data_type, 'created' => $s['created'], 'errors' => $s['error'], 'duplicates' => $s['duplicate']]);
    }
}
