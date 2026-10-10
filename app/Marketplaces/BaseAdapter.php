<?php
namespace App\Marketplaces;

use App\Models\Marketplace;
use DateTimeInterface;

abstract class BaseAdapter implements MarketplaceInterface
{
    /** dataType => [field => [aliases]] */
    protected array $aliases = [];
    /** normalised raw status => internal status */
    protected array $statusMap = [];

    public function __construct(protected Marketplace $marketplace) {}

    public function code(): string { return $this->marketplace->code; }
    public function supportsApi(): bool { return false; }
    public function columnAliases(string $dataType): array { return $this->aliases[str_starts_with($dataType, 'ADS_') ? 'ADS' : $dataType] ?? []; }   // all ad reports share one alias set

    public static function norm(?string $s): string { return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $s)); }

    public function suggestMapping(array $headers, string $dataType): array
    {
        $byNorm = [];
        foreach ($headers as $h) $byNorm[self::norm($h)] ??= $h;
        $out = [];
        foreach ($this->columnAliases($dataType) as $field => $aliases) {
            foreach ($aliases as $a) {
                if (isset($byNorm[self::norm($a)])) { $out[$field] = $byNorm[self::norm($a)]; break; }
            }
        }
        return $out;
    }

    public function mapOrderStatus(?string $raw): string
    {
        return $this->statusMap[self::norm($raw)] ?? 'IMPORTED';   // unknown text -> review manually
    }


    public function classifySettlementLine(?string $txnType, ?string $amountType, ?string $description): string
    {
        $t = self::norm($txnType); $a = self::norm($amountType); $d = self::norm($description); $all = $t . $a . $d;
        if (preg_match('/reimburs|safet|compensat/', $all)) return 'REIMBURSEMENT';
        if (str_contains($a, 'promotion') || str_contains($d, 'promotion')) return 'ADJUSTMENT';
        if (str_contains($a, 'fee') || preg_match('/commission|closingfee|fixedfee|fee|chargeback|fulfil|fba|weighthandling|pickpack/', $d)) {
            if (preg_match('/tax|gst|igst|cgst|sgst/', $d)) return 'FEE_TAX';
            if (preg_match('/commission|referral/', $d)) return 'REFERRAL_FEE';
            if (preg_match('/closing|fixed/', $d)) return 'CLOSING_FEE';
            if (preg_match('/shipping|fulfil|fba|weight|pick|delivery|logistic|chargeback/', $d)) return 'SHIPPING_FEE';
            if (preg_match('/payment|gateway|technology/', $d)) return 'PAYMENT_FEE';
            if (preg_match('/collection|cod/', $d)) return 'COLLECTION_FEE';
            return 'OTHER_FEE';
        }
        if (preg_match('/tax|gst|tcs|tds/', $d)) return 'TAX';
        $refund = str_contains($t, 'refund') || str_contains($t, 'return');
        if (preg_match('/^(shipping|delivery)/', $d)) return $refund ? 'REFUND' : 'SHIPPING_CREDIT';
        if (preg_match('/principal|productprice|sale/', $d) || ($d === '' && str_contains($a, 'price'))) return $refund ? 'REFUND' : 'SALE';
        if ($refund) return 'REFUND';
        if (str_contains($t, 'adjust') || str_contains($d, 'adjust')) return 'ADJUSTMENT';
        return 'OTHER';
    }

    public function normalizeFulfillment(?string $raw): string
    {
        $r = strtolower((string) $raw);
        if (preg_match('/mfn|merchant|seller|self|easy ?ship/', $r)) return 'SELF';
        return preg_match('/afn|fba|amazon|flipkart|fulfil|fk/', $r) ? 'MARKETPLACE' : 'SELF';
    }

    private function na(string $what): never { throw new MarketplaceNotSupported("{$this->marketplace->name}: API {$what} is not configured yet. Use the CSV import."); }
    public function getOrders(DateTimeInterface $from, DateTimeInterface $to): iterable { $this->na('orders'); }
    public function getReturns(DateTimeInterface $from, DateTimeInterface $to): iterable { $this->na('returns'); }
    public function getInventory(): iterable { $this->na('inventory'); }
    public function getSettlements(DateTimeInterface $from, DateTimeInterface $to): iterable { $this->na('settlements'); }
    public function getProducts(): iterable { $this->na('products'); }
    public function getFees(DateTimeInterface $from, DateTimeInterface $to): iterable { $this->na('fees'); }
    public function getAdvertisingData(DateTimeInterface $from, DateTimeInterface $to): iterable { $this->na('advertising'); }
    public function submitClaim(array $claim): array { $this->na('claims'); }
}
