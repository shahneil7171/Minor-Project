<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;

class SellerAudit extends Command
{
    protected $signature = 'catalogue:seller-audit';
    protected $description = 'Read-only audit of product seller ownership';

    public function handle(): int
    {
        $products = Product::with('seller', 'category.parent')->orderBy('id')->get();

        $dir = storage_path('app/reports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($dir . '/kdp_mart_seller_product_audit.csv', 'w');
        fputcsv($fp, ['Product ID','Product Name','SKU','Brand','Main Category','Subcategory','Seller ID','Seller Name','Seller Email','Seller Role','Price','Special Price','Stock','Status','Ownership Status']);

        foreach ($products as $p) {
            $seller = $p->seller;
            $ownerStatus = 'Valid owner';
            if ($p->seller_id === null) {
                $ownerStatus = 'No seller assigned';
            } elseif (!$seller) {
                $ownerStatus = 'Invalid/missing owner';
            } elseif (!in_array($seller->account_type, ['admin','seller'], true)) {
                $ownerStatus = 'Unexpected role';
            }

            $mainCat = $p->category?->parent?->name ?? $p->category?->name ?? '';
            $subCat = $p->category?->name ?? '';

            fputcsv($fp, [
                $p->id,
                $p->title,
                $p->sku,
                $p->brand,
                $mainCat,
                $subCat,
                $p->seller_id,
                $seller?->name,
                $seller?->email,
                $seller?->account_type,
                $p->price,
                $p->special_price,
                $p->quantity,
                $p->status,
                $ownerStatus,
            ]);
        }
        fclose($fp);

        // Summary
        $summary = [];
        foreach ($products as $p) {
            $sid = $p->seller_id ?? 0;
            if (!isset($summary[$sid])) {
                $s = $p->seller;
                $summary[$sid] = [
                    'seller_id' => $sid,
                    'name' => $s?->name ?? 'Unassigned',
                    'email' => $s?->email ?? '',
                    'role' => $s?->account_type ?? 'none',
                    'status' => $s?->status ?? '',
                    'count' => 0,
                    'active' => 0,
                    'inactive' => 0,
                    'stock' => 0,
                    'names' => [],
                ];
            }
            $summary[$sid]['count']++;
            if ($p->status == 1) $summary[$sid]['active']++; else $summary[$sid]['inactive']++;
            $summary[$sid]['stock'] += (int)$p->quantity;
            $summary[$sid]['names'][] = $p->id . ': ' . $p->title;
        }
        uasort($summary, fn($a,$b) => $b['count'] <=> $a['count']);

        $fp2 = fopen($dir . '/kdp_mart_seller_summary.csv', 'w');
        fputcsv($fp2, ['Seller ID','Seller Name','Seller Email','Role','Status','Total Products','Active','Inactive/Pending','Total Stock','Product Names']);
        foreach ($summary as $s) {
            fputcsv($fp2, [
                $s['seller_id'],
                $s['name'],
                $s['email'],
                $s['role'],
                $s['status'],
                $s['count'],
                $s['active'],
                $s['inactive'],
                $s['stock'],
                implode('; ', $s['names']),
            ]);
        }
        fclose($fp2);

        $this->info('Total products: ' . $products->count());
        $this->info('Distinct sellers: ' . count($summary));
        foreach ($summary as $s) {
            $this->line($s['seller_id'] . ' ' . $s['name'] . ': ' . $s['count']);
        }
        $this->info('Reports written to ' . $dir);
        return 0;
    }
}
