<?php

// Architecture test to ensure Unit tests remain pure and framework-free
it('ensures unit tests do not use framework dependencies', function () {
    $projectRoot = dirname(__DIR__, 2);
    $unitTestPath = $projectRoot.'/tests/Unit';

    if (! is_dir($unitTestPath)) {
        expect(true)->toBeTrue('Unit test directory does not exist');

        return;
    }

    // Define forbidden patterns for unit tests
    $forbiddenPatterns = [
        // Database-related traits and classes
        'RefreshDatabase' => 'Unit tests should not use RefreshDatabase - move to Integration if database is needed',
        'DatabaseTransactions' => 'Unit tests should not use DatabaseTransactions - move to Integration if database is needed',
        'DatabaseMigrations' => 'Unit tests should not use DatabaseMigrations - move to Integration if database is needed',

        // Laravel HTTP testing
        'MakesHttpRequests' => 'Unit tests should not make HTTP requests - move to Feature if HTTP testing is needed',
        'InteractsWithContainer' => 'Unit tests should not interact with the container - move to Integration if needed',
        'InteractsWithSession' => 'Unit tests should not interact with sessions - move to Feature if session testing is needed',
        'InteractsWithAuthentication' => 'Unit tests should not test authentication - move to Feature if auth testing is needed',
        'InteractsWithConsole' => 'Unit tests should not interact with console - move to Integration if console testing is needed',

        // Storage and filesystem
        'WithFaker' => 'Unit tests should use factories/explicit data instead of WithFaker trait',
        'InteractsWithRedis' => 'Unit tests should not use Redis - move to Integration if Redis is needed',

        // Specific Laravel facades that indicate framework usage
        'Facade' => 'Unit tests should not use Laravel facades - inject dependencies or move to Integration',
        'app(' => 'Unit tests should not use app() helper - inject dependencies or move to Integration',
        'resolve(' => 'Unit tests should not use resolve() helper - inject dependencies or move to Integration',

        // HTTP testing methods
        '$this->get(' => 'Unit tests should not make HTTP requests - move to Feature for HTTP testing',
        '$this->post(' => 'Unit tests should not make HTTP requests - move to Feature for HTTP testing',
        '$this->put(' => 'Unit tests should not make HTTP requests - move to Feature for HTTP testing',
        '$this->patch(' => 'Unit tests should not make HTTP requests - move to Feature for HTTP testing',
        '$this->delete(' => 'Unit tests should not make HTTP requests - move to Feature for HTTP testing',
        '->actingAs(' => 'Unit tests should not test authentication - move to Feature for auth testing',

        // Queue and job testing
        'Queue::' => 'Unit tests should not test queues - move to Integration for queue testing',
        'Bus::' => 'Unit tests should not test command bus - move to Integration for bus testing',
    ];

    $violations = [];

    // Get all PHP files in the Unit test directory recursively
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($unitTestPath, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $filePath = $file->getPathname();
        $fileContent = file_get_contents($filePath);
        $relativePath = str_replace($projectRoot.DIRECTORY_SEPARATOR, '', $filePath);

        // Check for forbidden patterns
        foreach ($forbiddenPatterns as $pattern => $message) {
            // Use case-insensitive search for better detection
            if (stripos($fileContent, $pattern) !== false) {
                // Extract the line number for better debugging
                $lines = explode("\n", $fileContent);
                $lineNumber = 0;
                foreach ($lines as $index => $line) {
                    if (stripos($line, $pattern) !== false) {
                        $lineNumber = $index + 1;
                        break;
                    }
                }

                $violations[] = sprintf("%s:%d - Found '%s': %s", $relativePath, $lineNumber, $pattern, $message);
            }
        }

        // Additional check for uses() calls with forbidden traits
        if (preg_match_all('/uses\s*\(\s*([^)]+)\s*\)/i', $fileContent, $matches)) {
            foreach ($matches[1] as $usesContent) {
                foreach ($forbiddenPatterns as $pattern => $message) {
                    if (stripos($usesContent, $pattern) !== false) {
                        $violations[] = sprintf('%s - Found uses(%s): %s', $relativePath, $pattern, $message);
                    }
                }
            }
        }

        // Check for use statements that import forbidden classes
        if (preg_match_all('/^use\s+([^;]+);/m', $fileContent, $matches)) {
            foreach ($matches[1] as $useStatement) {
                $forbiddenUsePatterns = [
                    \Illuminate\Foundation\Testing\RefreshDatabase::class,
                    \Illuminate\Foundation\Testing\DatabaseTransactions::class,
                    \Illuminate\Foundation\Testing\DatabaseMigrations::class,
                    \Illuminate\Foundation\Testing\WithFaker::class,
                    \Illuminate\Foundation\Testing\WithoutMiddleware::class,
                ];

                foreach ($forbiddenUsePatterns as $forbiddenUse) {
                    if (stripos($useStatement, $forbiddenUse) !== false) {
                        $violations[] = sprintf("%s - Found 'use %s': Unit tests should not use this trait - move to Integration/Feature if needed", $relativePath, $forbiddenUse);
                    }
                }
            }
        }
    }

    if ($violations !== []) {
        $message = "Unit test purity violations found:\n\n".implode("\n", $violations);
        $message .= "\n\nUnit tests should be pure and framework-free. They should only test logic without database, HTTP, or other Laravel framework features.";
        $message .= "\nIf your test needs these features, move it to tests/Integration/ or tests/Feature/ as appropriate.";

        expect(false)->toBeTrue($message);
    }

    expect(true)->toBeTrue();
});
