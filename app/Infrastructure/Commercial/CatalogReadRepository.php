<?php
declare(strict_types=1);
namespace Digiti\Infrastructure\Commercial;

use Digiti\Core\Store;

/** Three bounded catalog reads instead of one prices query per plan (N+1). */
final class CatalogReadRepository
{
    public static function snapshot(): array
    {
        $features=Store::all('SELECT f.feature_guid,f.feature_key,f.name,f.feature_type,f.unit,p.name product FROM features f LEFT JOIN products p ON p.product_guid=f.product_guid ORDER BY f.feature_key');
        $plans=Store::all('SELECT p.plan_guid,p.plan_key,p.name,p.status,prod.name product FROM plans p JOIN products prod ON prod.product_guid=p.product_guid ORDER BY prod.name,p.name');
        $prices=Store::all('SELECT price_guid,plan_guid,currency,amount_minor,billing_interval,interval_count,status FROM prices ORDER BY plan_guid,created_at DESC');
        $byPlan=[];
        foreach($prices as $price)$byPlan[$price['plan_guid']][]=$price;
        return ['features'=>$features,'plans'=>$plans,'prices_by_plan'=>$byPlan];
    }
}
