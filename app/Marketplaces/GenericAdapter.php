<?php
namespace App\Marketplaces;

/** Fallback for marketplaces without their own adapter yet (Shopify, Meesho, ...). CSV import still works via manual mapping. */
class GenericAdapter extends BaseAdapter {}
