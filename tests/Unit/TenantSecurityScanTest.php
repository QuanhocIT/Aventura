<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class TenantSecurityScanTest extends TestCase
{
    /**
     * Scan code for raw queries that might bypass automatic tenant isolation.
     */
    public function test_raw_queries_must_contain_restaurant_id_or_be_explicitly_exempted()
    {
        $finder = new Finder();
        $finder->files()
            ->in(__DIR__ . '/../../app')
            ->exclude('Console/Commands')
            ->exclude('Http/Controllers/SuperAdmin')
            ->notName('DatabaseBackupService.php')
            ->notName('FraudDetectionService.php')
            ->name('*.php');

        $violations = [];

        foreach ($finder as $file) {
            $content = $file->getContents();
            $lines = explode("\n", $content);

            foreach ($lines as $index => $line) {
                // Check if line contains DB raw execution functions
                if (preg_match('/DB::(select|statement|unprepared|insert|update|delete)\(/i', $line)) {
                    // Check if it has an exemption comment or handles scopes
                    if (str_contains($line, '@bypass-tenant-check') || str_contains($line, 'withoutGlobalScopes')) {
                        continue;
                    }

                    // Look in adjacent lines (current, previous, next) for restaurant_id or branch_id or tenant
                    $context = '';
                    for ($offset = -1; $offset <= 1; $offset++) {
                        if (isset($lines[$index + $offset])) {
                            $context .= $lines[$index + $offset];
                        }
                    }

                    if (!preg_match('/restaurant_id|branch_id|tenant_id|withoutGlobalScope/i', $context)) {
                        $violations[] = sprintf(
                            "File: %s:%d\nLine: %s",
                            $file->getRelativePathname(),
                            $index + 1,
                            trim($line)
                        );
                    }
                }
            }
        }

        if (!empty($violations)) {
            $this->fail(
                "Found raw SQL queries without explicit tenant isolation (restaurant_id/branch_id) check or exemption comment:\n\n" .
                implode("\n\n", $violations) .
                "\n\nTo bypass this check, append a comment like '// @bypass-tenant-check' to the raw query line."
            );
        }

        $this->assertTrue(true);
    }
}
