<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/mb_helpers.php';

// --- mb_ucfirst: Capitalize first character of a multibyte string ---
echo mb_ucfirst('héllo world');   // Héllo world
echo "\n";
echo mb_ucfirst('текст');         // Текст (Russian)
echo "\n\n";

// --- mb_ucwords: Capitalize first letter of each word ---
echo mb_ucwords('héllo wörld');   // Héllo Wörld
echo "\n";
echo mb_ucwords('привет мир');    // Привет Мир
echo "\n\n";

// --- mb_strrev: Reverse a multibyte string ---
echo mb_strrev('héllo');          // olléh
echo "\n";
echo mb_strrev('日本語');          // 語本日
echo "\n\n";

// --- mb_str_pad: Pad a multibyte string ---
echo mb_str_pad('café', 8, '-');  // café----
echo "\n";
echo mb_str_pad('café', 8, '-', STR_PAD_LEFT);  // ----café
echo "\n\n";

// --- mb_count_chars: Count character occurrences ---
$counts = mb_count_chars('héllo', 1);
foreach ($counts as $char => $count) {
    echo "{$char}: {$count}\n";
}
echo "\n";

// mode 3: return string of unique characters
echo mb_count_chars('héllo', 3);  // hélo
echo "\n\n";

// --- mb_str_split: Split multibyte string into characters ---
$chars = mb_str_split('日本語');
print_r($chars);
// Array ( [0] => 日 [1] => 本 [2] => 語 )
