<?php

namespace DreamFactory\Core\SqlAnywhere\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Security: SqlAnywhereSchema getTableConstraints must parameterize the
 * creator/foreign_creator IN clauses.
 */
class SchemaInterpolationTest extends TestCase
{
    private string $contents;

    protected function setUp(): void
    {
        $sourcePath = __DIR__ . '/../../src/Database/Schema/SqlAnywhereSchema.php';
        $this->assertFileExists($sourcePath);
        $this->contents = file_get_contents($sourcePath);
    }

    public function testNoSchemaInterpolation(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            "/IN\s*\(\s*'\{\\\$schema\}'\s*\)/",
            $this->contents,
            'No \$schema must be interpolated single-quoted into IN clauses'
        );
    }

    public function testInClauseUsesPlaceholders(): void
    {
        $this->assertMatchesRegularExpression(
            '/array_fill\s*\(\s*0\s*,\s*count\s*\(\s*\$schemas\s*\)/',
            $this->contents,
            'Multi-schema IN list must use array_fill ?-placeholder list'
        );
    }
}
