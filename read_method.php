<?php
$lines = file('app/Http/Controllers/CheckoutController.php');
for ($i = 474; $i < 540; $i++) {
    echo ($i + 1) . '|' . $lines[$i];
}
