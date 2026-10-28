<?php
require __DIR__.'/vendor/autoload.php';
 = require_once __DIR__.'/bootstrap/app.php';
 = ->make(Illuminate\Contracts\Console\Kernel::class);
->bootstrap();
 = App\Models\WishlistItem::all();
 = App\Models\Product::pluck('slug')->toArray();
=0;
foreach( as ){
    ->product_slug = [ % count()];
    ->save();
    ++;
}
echo  updated\n;
