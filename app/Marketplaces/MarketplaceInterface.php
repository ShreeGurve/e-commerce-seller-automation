<?php
namespace App\Marketplaces;

use DateTimeInterface;

/** Every marketplace (Amazon, Flipkart, future ones) implements this. The rest of the app never talks to a marketplace directly. */
interface MarketplaceInterface
{
    public function code(): string;
    public function supportsApi(): bool;

    // ---- CSV / report mode (works today) ----
    /** internal field => list of column names this marketplace commonly uses */
    public function columnAliases(string $dataType): array;
    /** Best-guess internal field => file column, from the file's headers */
    public function suggestMapping(array $headers, string $dataType): array;
    /** Marketplace status text -> internal order status (Order::STATUSES) */
    public function mapOrderStatus(?string $raw): string;
    /** Settlement report line -> SettlementItem kind (REFERRAL_FEE, SALE, REFUND, ...) */
    public function classifySettlementLine(?string $txnType, ?string $amountType, ?string $description): string;
    /** Marketplace fulfilment text -> SELF | MARKETPLACE */
    public function normalizeFulfillment(?string $raw): string;

    // ---- API mode (added when API access is approved; until then they throw MarketplaceNotSupported) ----
    public function getOrders(DateTimeInterface $from, DateTimeInterface $to): iterable;
    public function getReturns(DateTimeInterface $from, DateTimeInterface $to): iterable;
    public function getInventory(): iterable;
    public function getSettlements(DateTimeInterface $from, DateTimeInterface $to): iterable;
    public function getProducts(): iterable;
    public function getFees(DateTimeInterface $from, DateTimeInterface $to): iterable;
    public function getAdvertisingData(DateTimeInterface $from, DateTimeInterface $to): iterable;
    /** Must only be called from an APPROVED approval request, never automatically. */
    public function submitClaim(array $claim): array;
}
