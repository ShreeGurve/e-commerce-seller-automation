<?php
namespace App\Marketplaces;

use App\Models\Marketplace;

class MarketplaceManager
{
    public function for(Marketplace $marketplace): MarketplaceInterface
    {
        $class = $marketplace->adapter_class;
        if ($class && class_exists($class) && is_subclass_of($class, MarketplaceInterface::class)) return new $class($marketplace);
        return new GenericAdapter($marketplace);
    }
}
