<?php

require 'vendor/autoload.php';

use Noem\State\Middleware\ChainMail;
use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;

$chainMail = new ChainMail();
$registry = new InteractionRegistry();

// Test 1: Direct supply
echo "Test 1: Direct supply\n";
$chainMail->supply(fn(): InteractionRegistry => $registry);
$chainMail->boot(); // Boot the ChainMail!

$retrieved = null;
$chainMail->use(function (?InteractionRegistry $r = null) use (&$retrieved) {
    echo "Retrieved: " . ($r ? get_class($r) : "NULL") . "\n";
    $retrieved = $r;
});

echo "Result: " . ($retrieved instanceof InteractionRegistry ? "SUCCESS" : "FAIL") . "\n\n";

// Test 2: Via feature
echo "Test 2: Via feature\n";
$chainMail2 = new ChainMail();
$feature = new InteractionRegistryFeature();
$feature($chainMail2);
$chainMail2->boot(); // Boot the ChainMail!

$retrieved2 = null;
$chainMail2->use(function (?InteractionRegistry $r = null) use (&$retrieved2) {
    echo "Retrieved: " . ($r ? get_class($r) : "NULL") . "\n";
    $retrieved2 = $r;
});

echo "Result: " . ($retrieved2 instanceof InteractionRegistry ? "SUCCESS" : "FAIL") . "\n";
