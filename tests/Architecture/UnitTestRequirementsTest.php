<?php

// Custom architecture test to ensure test coverage for classes requiring it
test('all classes requiring tests have corresponding test files with covers() annotations', function () {
    $projectRoot = dirname(__DIR__, 2);
    $baseAppPath = $projectRoot.'/app';
    $baseUnitTestPath = $projectRoot.'/tests/Unit';
    $baseIntegrationTestPath = $projectRoot.'/tests/Integration';
    $baseFeatureTestPath = $projectRoot.'/tests/Feature';

    // Define directories that require tests according to guidelines
    $strictUnitTestDirectories = [
        'Models' => $baseAppPath.'/Models',
    ];

    // Requests can be in Unit, Integration, or Feature - just need covers() annotation
    $flexibleRequestDirectories = [
        'Requests' => $baseAppPath.'/Http/Requests',
    ];

    // Services can be in either Unit (pure) or Integration (database-aware)
    $flexibleTestDirectories = [
        'Services' => $baseAppPath.'/Services',
    ];

    $violations = [];

    // Check strict unit test requirements (Models must be in Unit)
    foreach ($strictUnitTestDirectories as $testType => $sourcePath) {
        if (! is_dir($sourcePath)) {
            continue;
        }

        // Get all PHP files in the source directory recursively
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $sourceFile = $file->getPathname();
            $relativePath = str_replace([$sourcePath.'/', $sourcePath.'\\'], '', $sourceFile);
            $relativePath = str_replace('\\', '/', $relativePath);

            // Convert file path to expected test file path
            $testFilePath = $baseUnitTestPath.'/'.$testType.'/'.str_replace('.php', 'Test.php', $relativePath);

            // Check if test file exists
            if (! file_exists($testFilePath)) {
                $violations[] = sprintf('Missing unit test: %s should have test at %s', $sourceFile, $testFilePath);

                continue;
            }

            // Check if test file contains covers() annotation
            $testContent = file_get_contents($testFilePath);
            if (! preg_match('/covers\s*\(/m', $testContent)) {
                // Extract class name from source file for covers() annotation
                $sourceContent = file_get_contents($sourceFile);

                $expectedClass = '';
                if (preg_match('/namespace\s+([^;]+)/m', $sourceContent, $namespaceMatches) &&
                    preg_match('/(?:class|interface|trait)\s+(\w+)/m', $sourceContent, $classMatches)) {
                    $expectedClass = trim($namespaceMatches[1]).'\\'.$classMatches[1];
                }

                $violations[] = sprintf('Missing covers() annotation: %s should include covers(%s::class)', $testFilePath, $expectedClass);
            }
        }
    }

    // Check Request classes - can be tested anywhere but must have covers() annotation
    foreach ($flexibleRequestDirectories as $testType => $sourcePath) {
        if (! is_dir($sourcePath)) {
            continue;
        }

        // Get all PHP files in the source directory recursively
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $sourceFile = $file->getPathname();
            $relativePath = str_replace([$sourcePath.'/', $sourcePath.'\\'], '', $sourceFile);
            $relativePath = str_replace('\\', '/', $relativePath);

            // Convert file path to expected test file paths (try Unit, Integration, and Feature)
            $unitTestFilePath = $baseUnitTestPath.'/'.$testType.'/'.str_replace('.php', 'Test.php', $relativePath);
            $integrationTestFilePath = $baseIntegrationTestPath.'/'.$testType.'/'.str_replace('.php', 'Test.php', $relativePath);
            $featureTestFilePath = $baseFeatureTestPath.'/'.$testType.'/'.str_replace('.php', 'Test.php', $relativePath);

            $testExists = false;
            $testFilePath = '';

            // Check if test exists in any directory
            if (file_exists($unitTestFilePath)) {
                $testExists = true;
                $testFilePath = $unitTestFilePath;
            } elseif (file_exists($integrationTestFilePath)) {
                $testExists = true;
                $testFilePath = $integrationTestFilePath;
            } elseif (file_exists($featureTestFilePath)) {
                $testExists = true;
                $testFilePath = $featureTestFilePath;
            }

            // Check if test file exists in any location
            if (! $testExists) {
                $violations[] = sprintf('Missing test: %s should have test with covers() annotation in Unit, Integration, or Feature directory', $sourceFile);

                continue;
            }

            // Check if test file contains covers() annotation
            $testContent = file_get_contents($testFilePath);
            if (! preg_match('/covers\s*\(/m', $testContent)) {
                // Extract class name from source file for covers() annotation
                $sourceContent = file_get_contents($sourceFile);

                $expectedClass = '';
                if (preg_match('/namespace\s+([^;]+)/m', $sourceContent, $namespaceMatches) &&
                    preg_match('/(?:class|interface|trait)\s+(\w+)/m', $sourceContent, $classMatches)) {
                    $expectedClass = trim($namespaceMatches[1]).'\\'.$classMatches[1];
                }

                $violations[] = sprintf('Missing covers() annotation: %s should include covers(%s::class)', $testFilePath, $expectedClass);
            }
        }
    }

    // Check flexible test requirements (Services can be in Unit OR Integration)
    foreach ($flexibleTestDirectories as $testType => $sourcePath) {
        if (! is_dir($sourcePath)) {
            continue;
        }

        // Get all PHP files in the source directory recursively
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $sourceFile = $file->getPathname();
            $relativePath = str_replace([$sourcePath.'/', $sourcePath.'\\'], '', $sourceFile);
            $relativePath = str_replace('\\', '/', $relativePath);

            // Convert file path to expected test file paths (try both Unit and Integration)
            $unitTestFilePath = $baseUnitTestPath.'/'.$testType.'/'.str_replace('.php', 'Test.php', $relativePath);
            $integrationTestFilePath = $baseIntegrationTestPath.'/'.$testType.'/'.str_replace('.php', 'Test.php', $relativePath);

            $testExists = false;
            $testFilePath = '';

            // Check if test exists in Unit directory
            if (file_exists($unitTestFilePath)) {
                $testExists = true;
                $testFilePath = $unitTestFilePath;
            } elseif (file_exists($integrationTestFilePath)) {
                $testExists = true;
                $testFilePath = $integrationTestFilePath;
            }

            // Check if test file exists in either location
            if (! $testExists) {
                $violations[] = sprintf('Missing test: %s should have test at either %s (pure) or %s (database-aware)', $sourceFile, $unitTestFilePath, $integrationTestFilePath);

                continue;
            }

            // Check if test file contains covers() annotation
            $testContent = file_get_contents($testFilePath);
            if (! preg_match('/covers\s*\(/m', $testContent)) {
                // Extract class name from source file for covers() annotation
                $sourceContent = file_get_contents($sourceFile);

                $expectedClass = '';
                if (preg_match('/namespace\s+([^;]+)/m', $sourceContent, $namespaceMatches) &&
                    preg_match('/(?:class|interface|trait)\s+(\w+)/m', $sourceContent, $classMatches)) {
                    $expectedClass = trim($namespaceMatches[1]).'\\'.$classMatches[1];
                }

                $violations[] = sprintf('Missing covers() annotation: %s should include covers(%s::class)', $testFilePath, $expectedClass);
            }
        }
    }

    if ($violations !== []) {
        $message = "Testing requirements violations:\n\n".implode("\n", $violations);
        $message .= "\n\nAccording to testing guidelines:\n";
        $message .= "- Models MUST have unit tests in tests/Unit/ with covers() annotations\n";
        $message .= "- Requests MUST have tests with covers() annotations in Unit, Integration, or Feature directories\n";
        $message .= '- Services MUST have tests with covers() annotations, either in tests/Unit/ (pure) or tests/Integration/ (database-aware)';

        expect(false)->toBeTrue($message);
    }

    expect(true)->toBeTrue();
});
