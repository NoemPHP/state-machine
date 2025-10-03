<?php

declare(strict_types=1);

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Loader\Machine;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Template\TemplateFeature;
use Noem\State\Feature\Ai\AiFeature;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Parse command line arguments (simplified)
 */
function parseArguments(array $argv): array
{
    $options = [
        'specPath' => __DIR__ . '/../../specs/chain/middleware.yaml',
        'stopOnFailure' => false,
        'group' => null,
        'quiet' => false,
        'verbose' => false,
    ];
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if (str_starts_with($arg, '--spec=')) {
            $options['specPath'] = substr($arg, 7);
        } elseif (str_starts_with($arg, '--group=')) {
            $options['group'] = substr($arg, 8);
        } elseif ($arg === '--stop-on-failure') {
            $options['stopOnFailure'] = true;
        } elseif ($arg === '--quiet' || $arg === '-q') {
            $options['quiet'] = true;
            $options['verbose'] = false;
        } elseif ($arg === '--verbose' || $arg === '-v') {
            $options['verbose'] = true;
            $options['quiet'] = false;
        }
    }
    
    return $options;
}

/**
 * Initialize the test runner with all acceptance criteria from any spec file
 */
function initializeTestRunner(): callable
{
    return function (object $t): void {
        global $argv;
        $options = parseArguments($argv ?? []);
        
        // Store options in extended state
        foreach ($options as $key => $value) {
            $this->set($key, $value);
        }
        
        // Load the spec file
        $specPath = $options['specPath'];
        
        if (!file_exists($specPath)) {
            throw new RuntimeException("Spec file not found at: {$specPath}");
        }
        
        $specContent = file_get_contents($specPath);
        if ($specContent === false) {
            throw new RuntimeException("Failed to read spec file at: {$specPath}");
        }
        
        $spec = Yaml::parse($specContent);
        if (!isset($spec['features'])) {
            throw new RuntimeException("Invalid spec file: 'features' key not found");
        }
        
        // Filter features by group if specified
        $features = $spec['features'];
        if ($options['group']) {
            $features = array_filter($features, fn($f) => $f['name'] === $options['group']);
            if (empty($features)) {
                throw new RuntimeException("No features found matching group: {$options['group']}");
            }
        }
        
        // Initialize test data
        $this->set('spec', $spec);
        $this->set('features', array_values($features));
        $this->set('allFeatures', $spec['features']);
        $this->set('testResults', []);
        $this->set('failedTests', []);
        $this->set('passedTests', []);
        $this->set('totalTests', 0);
        $this->set('startTime', microtime(true));
        $this->set('testsComplete', false);
        
        // Count total tests
        $totalTests = 0;
        foreach ($features as $feature) {
            $totalTests += count($feature['specs']);
        }
        $this->set('totalTests', $totalTests);
        
        // Only show header if not in quiet mode
        if (!$options['quiet']) {
            echo "\n";
            echo "========================================\n";
            echo " TEST RUNNER - " . strtoupper($spec['name'] ?? 'ACCEPTANCE CRITERIA') . "\n";
            echo "========================================\n";
            echo "\n";
            if (isset($spec['name'])) echo "Spec: {$spec['name']}\n";
            if (isset($spec['group'])) echo "Group: {$spec['group']}\n";
            if (isset($spec['description'])) echo "Description: {$spec['description']}\n";
            echo "\n";
            echo "Configuration:\n";
            echo "  Spec file: " . basename($specPath) . "\n";
            if ($options['group']) echo "  Test group: {$options['group']}\n";
            echo "  Stop on failure: " . ($options['stopOnFailure'] ? 'yes' : 'no') . "\n";
            echo "  Quiet mode: " . ($options['quiet'] ? 'yes' : 'no') . "\n";
            echo "\n";
            echo "Total features: " . count($features) . "\n";
            echo "Total acceptance criteria: {$totalTests}\n";
            echo "\n";
            echo "Starting test execution...\n";
            echo "----------------------------------------\n";
        }
    };
}



/**
 * Output test summary for partial failures
 */
function outputTestSummary(): callable
{
    return function (object $t): void {
        $totalTests = $this->get('totalTests');
        $passedTests = $this->get('passedTests');
        $failedTests = $this->get('failedTests');
        $duration = round(microtime(true) - $this->get('startTime'), 2);
        $quiet = $this->get('quiet', false);
        
        // Set exit code for failure
        $GLOBALS['TEST_EXIT_CODE'] = 1;
        
        echo "\n";
        echo "========================================\n";
        echo " ❌ TEST FAILURES DETECTED\n";
        echo "========================================\n";
        echo "\n";
        echo "Total Tests: {$totalTests}\n";
        echo "Passed: " . count($passedTests) . " ✅\n";
        echo "Failed: " . count($failedTests) . " ❌\n";
        echo "Total Duration: {$duration}s\n";
        echo "\n";
        
        // Always show failed tests
        if (!empty($failedTests)) {
            echo "Failed Tests:\n";
            echo "----------------------------------------\n";
            foreach ($failedTests as $i => $test) {
                echo ($i + 1) . ". [{$test['feature']}] {$test['acceptanceCriteria']}\n";
                echo "   Command: {$test['test']}\n";
                echo "   Return Code: {$test['returnCode']}\n";
                echo "   Output:\n";
                echo "\n";
                echo $test['output'];
                echo "\n";
                echo "----------------------------------------\n";
                echo "\n";
                if (!$quiet) {
                    echo "   Duration: {$test['duration']}s\n";
                }
                echo "\n";
            }
            echo "\n";
        }
        
        // Only show feature summary if not in quiet mode
        if (!$quiet) {
            $features = $this->get('features');
            if (count($features) > 1) {
                echo "Feature Summary:\n";
                echo "----------------------------------------\n";
                foreach ($features as $feature) {
                    $featurePassed = array_filter($passedTests, fn($t) => $t['feature'] === $feature['name']);
                    $featureFailed = array_filter($failedTests, fn($t) => $t['feature'] === $feature['name']);
                    $featureTestCount = count($feature['specs']);
                    $passedCount = count($featurePassed);
                    $failedCount = count($featureFailed);
                    
                    $icon = $failedCount === 0 ? '✅' : '❌';
                    echo "{$icon} {$feature['name']}: {$passedCount}/{$featureTestCount} passed";
                    if ($failedCount > 0) {
                        echo " ({$failedCount} failed)";
                    }
                    echo "\n";
                }
                echo "\n";
            }
        }
        
        // Only show comprehensive failure output if not in quiet mode
        if (!$quiet) {
            echo "========================================\n";
            echo " DETAILED FAILURE INFORMATION\n";
            echo "========================================\n";
            echo "\n";
            
            foreach ($failedTests as $i => $test) {
                echo "## FAILURE " . ($i + 1) . " ##\n";
                echo "Feature: {$test['feature']}\n";
                echo "Acceptance Criteria: {$test['acceptanceCriteria']}\n";
                echo "Test Command: {$test['test']}\n";
                echo "Return Code: {$test['returnCode']}\n";
                echo "Duration: {$test['duration']}s\n";
                echo "Timestamp: {$test['timestamp']}\n";
                echo "\n";
                echo "--- Full Test Output ---\n";
                echo $test['output'];
                echo "\n--- End Test Output ---\n";
                echo "\n";
                
                // Extract key error patterns
                $output = $test['output'];
                $errorPatterns = [];
                
                // Look for common error patterns
                if (preg_match('/Fatal error:(.+?)in (.+?) on line (\d+)/s', $output, $matches)) {
                    $errorPatterns[] = "PHP Fatal Error: {$matches[1]} in {$matches[2]} on line {$matches[3]}";
                }
                if (preg_match('/ParseError:(.+?)in (.+?)on line (\d+)/s', $output, $matches)) {
                    $errorPatterns[] = "Parse Error: {$matches[1]} in {$matches[2]} on line {$matches[3]}";
                }
                if (preg_match_all('/Failed asserting that (.+?)\./', $output, $matches)) {
                    foreach ($matches[1] as $assertion) {
                        $errorPatterns[] = "Assertion Failed: {$assertion}";
                    }
                }
                if (preg_match('/Class ["\']?([^"\']+)["\']? not found/', $output, $matches)) {
                    $errorPatterns[] = "Missing Class: {$matches[1]}";
                }
                if (preg_match('/Call to undefined (method|function) (.+?)\(\)/', $output, $matches)) {
                    $errorPatterns[] = "Missing {$matches[1]}: {$matches[2]}()";
                }
                if (preg_match('/No such file or directory/', $output)) {
                    $errorPatterns[] = "File Not Found - check test file paths";
                }
                if (preg_match('/Cannot open file "(.+?)"/', $output, $matches)) {
                    $errorPatterns[] = "Cannot open file: {$matches[1]}";
                }
                
                if (!empty($errorPatterns)) {
                    echo "Key Error Indicators:\n";
                    foreach ($errorPatterns as $pattern) {
                        echo "  • " . trim($pattern) . "\n";
                    }
                    echo "\n";
                }
                
                echo "\n";
                echo "========================================\n";
                echo "\n";
            }
            
            echo "========================================\n";
            echo " END OF TEST REPORT\n";
            echo "========================================\n";
            echo "\n";
        } else {
            // In quiet mode, show a hint about getting more details
            echo "Run without --quiet flag to see detailed failure information.\n";
            echo "\n";
        }
    };
}

/**
 * Run all tests in sequence
 */
function runAllTests(): callable
{
    return function (object $t): void {
        $features = $this->get('features');
        $quiet = $this->get('quiet', false);
        $verbose = $this->get('verbose', false);
        $stopOnFailure = $this->get('stopOnFailure', false);
        
        foreach ($features as $feature) {
            // Display feature header
            if (!$quiet) {
                echo "\n";
                echo "========================================\n";
                echo " FEATURE: " . strtoupper($feature['name']) . "\n";
                echo "========================================\n";
                echo "Description: {$feature['description']}\n";
                echo "Tests to run: " . count($feature['specs']) . "\n";
                echo "----------------------------------------\n";
            }
            
            // Run each test in the feature
            foreach ($feature['specs'] as $spec) {
                $testNumber = count($this->get('testResults')) + 1;
                $totalTests = $this->get('totalTests');
                
                if (!$quiet) {
                    echo "\n";
                    echo "[{$testNumber}/{$totalTests}] Testing: {$spec['acceptanceCriteria']}\n";
                    echo "Command: {$spec['test']}\n";
                }
                
                // Execute the test command
                $output = [];
                $returnCode = 0;
                $startTime = microtime(true);
                
                // Prepare the command
                $command = "cd /var/www/html && " . $spec['test'] . " 2>&1";
                exec($command, $output, $returnCode);
                
                $endTime = microtime(true);
                $duration = round($endTime - $startTime, 3);
                
                $testResult = [
                    'feature' => $feature['name'],
                    'acceptanceCriteria' => $spec['acceptanceCriteria'],
                    'test' => $spec['test'],
                    'passed' => $returnCode === 0,
                    'returnCode' => $returnCode,
                    'output' => implode("\n", $output),
                    'duration' => $duration,
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
                
                // Store test result
                $testResults = $this->get('testResults');
                $testResults[] = $testResult;
                $this->set('testResults', $testResults);
                
                if ($testResult['passed']) {
                    if (!$quiet) {
                        echo "✅ PASSED ({$spec['test']} | {$duration}s)\n";
                        
                        // Show output in verbose mode
                        if ($verbose && !empty($testResult['output'])) {
                            echo "--- Output ---\n";
                            echo $testResult['output'] . "\n";
                            echo "--- End Output ---\n";
                        }
                    }
                    
                    $passedTests = $this->get('passedTests');
                    $passedTests[] = $testResult;
                    $this->set('passedTests', $passedTests);
                } else {
                    // Always show failures, even in quiet mode
                    echo "❌ FAILED: [{$feature['name']}] {$spec['acceptanceCriteria']}\n";
                    echo "   Command: {$spec['test']}\n";
                    echo "   Return code: {$returnCode}\n";
                    if (!$quiet) {
                        echo "   Duration: {$duration}s\n";
                        
                        // Always show output for failed tests (unless in quiet mode)
                        if (!empty($testResult['output'])) {
                            echo "--- Error Output ---\n";
                            echo $testResult['output'] . "\n";
                            echo "--- End Output ---\n";
                        }
                    }
                    
                    $failedTests = $this->get('failedTests');
                    $failedTests[] = $testResult;
                    $this->set('failedTests', $failedTests);
                    
                    // Check if we should stop on failure
                    if ($stopOnFailure) {
                        $this->set('shouldStop', true);
                        return; // Exit early
                    }
                }
            }
        }
        
        // Mark tests as complete
        $this->set('testsComplete', true);
    };
}





/**
 * Check if we should stop execution
 */
function shouldStopExecution(): callable
{
    return function (object $t): bool {
        return $this->get('shouldStop', false);
    };
}

/**
 * Check if all tests are complete
 */
function allTestsComplete(): callable
{
    return function (object $t): bool {
        return $this->get('testsComplete', false);
    };
}

/**
 * Output success summary when all tests pass
 */
function outputSuccessSummary(): callable
{
    return function (object $t): void {
        $totalTests = $this->get('totalTests');
        $passedTests = $this->get('passedTests');
        $duration = round(microtime(true) - $this->get('startTime'), 2);
        $quiet = $this->get('quiet', false);
        
        // Set exit code for success
        $GLOBALS['TEST_EXIT_CODE'] = 0;
        
        // In quiet mode, only show minimal success message
        if ($quiet) {
            echo "\n✅ All {$totalTests} tests passed ({$duration}s)\n";
            return;
        }
        
        echo "\n";
        echo "========================================\n";
        echo " 🎉 ALL TESTS PASSED!\n";
        echo "========================================\n";
        echo "\n";
        echo "Total Tests: {$totalTests}\n";
        echo "Passed: " . count($passedTests) . "\n";
        echo "Failed: 0\n";
        echo "Total Duration: {$duration}s\n";
        echo "\n";
        
        // Show feature summary
        $features = $this->get('features');
        if (count($features) > 1) {
            echo "Feature Summary:\n";
            echo "----------------------------------------\n";
            foreach ($features as $feature) {
                $featureTests = array_filter($passedTests, fn($t) => $t['feature'] === $feature['name']);
                $featureTestCount = count($feature['specs']);
                $passedCount = count($featureTests);
                echo "✅ {$feature['name']}: {$passedCount}/{$featureTestCount} tests passed\n";
            }
            echo "\n";
        }
        
        $spec = $this->get('spec');
        echo "The " . ($spec['name'] ?? 'test suite') . " meets all acceptance criteria! 🚀\n";
        echo "\n";
    };
}

/**
 * Determine final test outcome and output appropriate summary
 */
function determineFinalOutcome(): callable
{
    return function (object $t): void {
        $failedTests = $this->get('failedTests');
        
        // Output the appropriate summary
        if (empty($failedTests)) {
            // Call outputSuccessSummary directly
            $callback = outputSuccessSummary();
            $callback->call($this, $t);
            $this->set('allPassed', true);
        } else {
            // Call outputTestSummary directly
            $callback = outputTestSummary();
            $callback->call($this, $t);
            $this->set('allPassed', false);
        }
    };
}

// Initialize global exit code
$GLOBALS['TEST_EXIT_CODE'] = 0;

// Run the state machine
Machine::run(
    new class extends Machine {
        public function features(): iterable
        {
            return array_merge(
                parent::features(), // Include base features (RegionLoader)
                [
                    new ExtendedState(),
                    new TransitionsFeature(),
                    new TemplateFeature(),
                    new AiFeature(),
                ]
            );
        }

        public function trigger(): object
        {
            return new stdClass();
        }

        public function container(): array
        {
            return [];
        }

        public function yaml(): string
        {
            return file_get_contents(__DIR__ . '/machine.yml');
        }
    }
);

// Exit with appropriate code
exit($GLOBALS['TEST_EXIT_CODE']);
