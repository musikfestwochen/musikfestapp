<?php

// Architecture test to ensure test coverage uniqueness for classes requiring it
it('ensures each class appears exactly once in covers() annotations', function () {
    $projectRoot = dirname(__DIR__, 2);
    $baseAppPath = $projectRoot.'/app';
    $testDirectories = [
        $projectRoot.'/tests/Unit',
        $projectRoot.'/tests/Integration',
        $projectRoot.'/tests/Feature',
    ];

    // Define directories that require covered tests according to guidelines
    $sourceDirectories = [
        'Models' => $baseAppPath.'/Models',
        'Requests' => $baseAppPath.'/Http/Requests',
        'Services' => $baseAppPath.'/Services',
        'PHPStan/Rules' => $baseAppPath.'/PHPStan/Rules',
        'Casts' => $baseAppPath.'/Casts',
    ];

    $violations = [];
    $classToTestFileMap = [];  // Track which classes are covered by which test files
    $allSourceClasses = [];    // Track all classes that should have tests

    // First, collect all source classes that should have tests
    foreach ($sourceDirectories as $sourcePath) {
        if (! is_dir($sourcePath)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $sourceFile = $file->getPathname();
            $sourceContent = file_get_contents($sourceFile);

            if (preg_match('/namespace\s+([^;]+)/m', $sourceContent, $namespaceMatches) &&
                preg_match('/(?:class|interface|trait)\s+(\w+)/m', $sourceContent, $classMatches)) {
                $className = trim($namespaceMatches[1]).'\\'.$classMatches[1];
                $allSourceClasses[$className] = $sourceFile;
            }
        }
    }

    // Then, scan all test files to find covers() annotations
    foreach ($testDirectories as $testDir) {
        if (! is_dir($testDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($testDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            $testFile = $file->getPathname();
            $testContent = file_get_contents($testFile);

            // Find covers() annotations in this test file
            if (preg_match_all('/covers\s*\(\s*([^:]+)::class\s*\)/m', $testContent, $matches)) {
                foreach ($matches[1] as $coveredClass) {
                    // Clean up the class name
                    $coveredClass = trim($coveredClass);

                    // Handle relative class names (add namespace if needed)
                    if (! str_contains($coveredClass, '\\')) {
                        // Look for namespace in the test file or try to infer it
                        if (preg_match('/use\s+([^;]*\\\\'.preg_quote($coveredClass, '/').')\s*;/m', $testContent, $useMatch)) {
                            $coveredClass = $useMatch[1];
                        } elseif (preg_match('/namespace\s+([^;]+)/m', $testContent, $namespaceMatch)) {
                            // If the covered class might be in app namespace
                            $possibleClass = 'App\\'.$coveredClass;
                            if (isset($allSourceClasses[$possibleClass])) {
                                $coveredClass = $possibleClass;
                            }
                        }
                    }

                    // Track this class -> test file mapping
                    if (! isset($classToTestFileMap[$coveredClass])) {
                        $classToTestFileMap[$coveredClass] = [];
                    }

                    $classToTestFileMap[$coveredClass][] = $testFile;
                }
            }
        }
    }

    // Check each source class for coverage violations
    foreach ($allSourceClasses as $className => $sourceFile) {
        if (! isset($classToTestFileMap[$className])) {
            $violations[] = sprintf('Missing covers() annotation: Class %s (from %s) is not covered by any test file', $className, $sourceFile);
        } elseif (count($classToTestFileMap[$className]) > 1) {
            $testFiles = implode(', ', $classToTestFileMap[$className]);
            // Print all locations to the console for duplicate covers
            echo "Duplicate covers() annotation for class {$className} found in the following files:\n";
            foreach ($classToTestFileMap[$className] as $file) {
                echo sprintf(' - %s%s', $file, PHP_EOL);
            }

            $violations[] = sprintf('Duplicate covers() annotation: Class %s is covered by multiple test files: %s', $className, $testFiles);
        }
    }

    // Check for covers() annotations that target non-existent or non-required classes
    foreach ($classToTestFileMap as $coveredClass => $testFiles) {
        // Only report if it looks like an app class (to avoid external dependencies)
        if (! isset($allSourceClasses[$coveredClass]) && str_starts_with($coveredClass, 'App\\')) {
            $testFiles = implode(', ', $testFiles);
            $violations[] = sprintf('Unnecessary covers() annotation: Class %s is covered by %s but does not exist or is not required to have tests', $coveredClass, $testFiles);
        }
    }

    if ($violations !== []) {
        $message = "Test coverage uniqueness violations:\n\n".implode("\n", $violations);
        $message .= "\n\nRequirements:\n";
        $message .= "- Each required class MUST have exactly one covers() annotation\n";
        $message .= "- No class should be covered by multiple test files\n";
        $message .= "- covers() annotations should only target classes that require testing\n";
        $message .= "\nRequired classes: Models, Requests, Services, PHPStan Rules";

        expect(false)->toBeTrue($message);
    }

    expect(true)->toBeTrue();
});
