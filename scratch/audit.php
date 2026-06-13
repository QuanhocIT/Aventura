<?php
/**
 * Deep audit script: scan the Aventura codebase for performance issues, logic bugs, and optimization opportunities.
 */

$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app'));
$issues = [];

foreach ($dir as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = str_replace('\\', '/', $file->getPathname());
    if (str_contains($path, 'vendor/') || str_contains($path, 'scratch/')) continue;

    $content = file_get_contents($file->getPathname());
    $lines = explode("\n", $content);
    $lineCount = count($lines);

    for ($i = 0; $i < $lineCount; $i++) {
        $line = $lines[$i];
        $nextLine = $lines[$i + 1] ?? '';
        $ln = $i + 1;

        // 1. get()->filter() — fetch all rows then filter in PHP instead of SQL
        if (preg_match('/->get\(\)\s*$/', trim($line)) && preg_match('/^\s*->filter\(/', $nextLine)) {
            $issues[] = ['⚠️ get()->filter()', basename($path), $ln, trim($line)];
        }
        if (preg_match('/->get\(\)->filter\(/', $line)) {
            $issues[] = ['⚠️ get()->filter()', basename($path), $ln, trim($line)];
        }

        // 2. Query inside foreach loop (N+1)
        if (preg_match('/^\s+(foreach|for)\s*\(/', $line)) {
            $braceDepth = 0;
            for ($j = $i; $j < min($i + 30, $lineCount); $j++) {
                $braceDepth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                if ($j > $i && $braceDepth <= 0) break;
                if ($j > $i && preg_match('/(::where|::find|::first|Inventory::where|InventoryTransaction::where)\(/', $lines[$j])) {
                    $issues[] = ['🔴 N+1 query in loop', basename($path), $j + 1, trim($lines[$j])];
                    break;
                }
            }
        }

        // 3. Missing pagination — ->get() on potentially large result sets without ->take()
        if (preg_match('/Order::where.*->get\(\)/', $line) && !str_contains($line, 'take(') && !str_contains($line, 'limit(')) {
            if (!str_contains($line, 'pluck') && !str_contains($line, 'count')) {
                $issues[] = ['⚡ Unbounded get()', basename($path), $ln, trim(substr($line, 0, 100))];
            }
        }

        // 4. Cache::forget without clear pattern — may miss related keys
        // (informational only)

        // 5. Synchronous HTTP calls without queue
        if (preg_match('/Http::timeout\(\d+\)->post\(/', $line) && !str_contains($path, 'Job')) {
            $issues[] = ['💡 Sync HTTP call', basename($path), $ln, 'Consider queuing external API calls'];
        }

        // 6. str_contains/str_starts_with on large strings in loops
        // 7. Raw DB queries without bindings
        if (preg_match('/DB::select\(.*\$/', $line) && !str_contains($line, '?') && !str_contains($line, ':')) {
            $issues[] = ['🔒 Raw SQL without bindings', basename($path), $ln, trim($line)];
        }

        // 8. Missing transaction in multi-write operations
        // (too complex for static analysis)

        // 9. Large Inertia payloads — checking for many ->toArray() calls
    }
}

echo "=== PERFORMANCE & LOGIC AUDIT ===\n";
echo "Found " . count($issues) . " potential issues:\n\n";

$grouped = [];
foreach ($issues as $issue) {
    $grouped[$issue[0]][] = $issue;
}

foreach ($grouped as $type => $items) {
    echo "{$type} (" . count($items) . " occurrences)\n";
    foreach ($items as $item) {
        echo "  📄 {$item[1]}:{$item[2]} — " . substr($item[3], 0, 90) . "\n";
    }
    echo "\n";
}

// ==================== ADDITIONAL CHECKS ====================

echo "=== DATABASE INDEXES CHECK ===\n";
// Check for columns used in WHERE clauses that might need indexes
$commonQueries = [];
foreach ($dir as $file) {
    if ($file->getExtension() !== 'php') continue;
    $content = file_get_contents($file->getPathname());
    if (preg_match_all("/->where\('(\w+)',/", $content, $matches)) {
        foreach ($matches[1] as $col) {
            $commonQueries[$col] = ($commonQueries[$col] ?? 0) + 1;
        }
    }
}
arsort($commonQueries);
echo "Most-queried columns (potential index candidates):\n";
foreach (array_slice($commonQueries, 0, 15, true) as $col => $count) {
    echo "  {$col}: {$count} WHERE clauses\n";
}

echo "\n=== LARGE CONTROLLER CHECK ===\n";
$controllerDir = new DirectoryIterator('app/Http/Controllers');
$controllers = [];
foreach ($controllerDir as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $size = $f->getSize();
        $lines = count(file($f->getPathname()));
        $controllers[] = [$f->getFilename(), $size, $lines];
    }
}
usort($controllers, fn($a, $b) => $b[1] - $a[1]);
echo "Controllers by size:\n";
foreach (array_slice($controllers, 0, 10) as $c) {
    $kb = round($c[1] / 1024, 1);
    $flag = $kb > 30 ? '🔴' : ($kb > 15 ? '🟡' : '🟢');
    echo "  {$flag} {$c[0]}: {$kb}KB ({$c[2]} lines)\n";
}

echo "\n=== MISSING EAGER LOADING ===\n";
// Find Inertia::render calls and check if related models are loaded with ->with()
$missingEager = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('app/Http/Controllers')) as $f) {
    if ($f->getExtension() !== 'php') continue;
    $content = file_get_contents($f->getPathname());
    // Count ->product?->name patterns (accessing relations without eager load)
    $count = preg_match_all('/->(?:product|category|employee|restaurant|user|shift|table|area)\?->/', $content);
    if ($count > 3) {
        echo "  " . basename($f->getPathname()) . ": {$count} nullable relation accesses (check eager loading)\n";
        $missingEager += $count;
    }
}
if ($missingEager === 0) echo "  All good!\n";

echo "\nDone!\n";
