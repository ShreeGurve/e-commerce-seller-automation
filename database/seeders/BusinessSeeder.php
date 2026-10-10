<?php
namespace Database\Seeders;

use App\Models\{Brand, Business, BusinessSetting, Marketplace, Role, User};
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    public function run(): void
    {
        $biz = Business::firstOrCreate(['name' => 'SHREE GURVE'], ['currency' => 'INR', 'timezone' => 'Asia/Kolkata']);
        Brand::firstOrCreate(['business_id' => $biz->id, 'name' => 'SHREE GURVE']);

        Marketplace::firstOrCreate(['code' => 'amazon'], [
            'name' => 'Amazon', 'adapter_class' => 'App\\Marketplaces\\Amazon\\AmazonAdapter', 'sort_order' => 1]);
        Marketplace::firstOrCreate(['code' => 'flipkart'], [
            'name' => 'Flipkart', 'adapter_class' => 'App\\Marketplaces\\Flipkart\\FlipkartAdapter', 'sort_order' => 2]);

        $defaults = [
            ['general', 'default_marketplace', 'amazon', 'string'],
            ['finance', 'default_profit_margin', '20', 'decimal'],
            ['inventory', 'default_minimum_stock', '10', 'int'],
            ['advertising', 'default_target_acos', '25', 'decimal'],
            ['returns', 'default_return_threshold', '10', 'decimal'],
        ];
        foreach ($defaults as [$group, $key, $value, $type]) {
            BusinessSetting::firstOrCreate(['business_id' => $biz->id, 'key' => $key],
                ['group' => $group, 'value' => $value, 'type' => $type]);
        }

        $admin = User::firstOrCreate(['email' => 'admin@sg.test'], [
            'business_id' => $biz->id, 'name' => 'Super Admin', 'password' => 'Gems@1234', 'is_active' => true,
        ]);
        $admin->roles()->syncWithoutDetaching([Role::where('slug', 'super-admin')->value('id')]);
    }
}
