<?php

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Custom PHPStan rule to prevent usage of forbidden methods.
 * This enforces best practices and prevents usage of certain methods that bypass security measures.
 *
 * @implements Rule<MethodCall>
 */
class ForbiddenMethodsRule implements Rule
{
    /**
     * Configuration of forbidden methods with their reasons and alternatives.
     *
     * To add new forbidden methods, simply add them to this array:
     *
     * 'methodName' => [
     *     'message' => 'Descriptive error message with alternative',
     *     'identifier' => 'unique.error.identifier'
     * ]
     *
     * @pest-mutate-ignore
     *
     * @var array<string, array{message: string, identifier: string}>
     */
    private array $forbiddenMethods = [
        'hasPermissionTo' => [
            'message' => 'Method hasPermissionTo() is forbidden. Use can() method instead to ensure Gate::before is called.',
            'identifier' => 'laravel.hasPermissionTo.forbidden',
        ],
        // Example for additional forbidden methods:
        // 'directPermissionCheck' => [
        //     'message' => 'Direct permission checks are forbidden. Use can() method instead.',
        //     'identifier' => 'laravel.directPermissionCheck.forbidden'
        // ],
        // 'hasDirectPermission' => [
        //     'message' => 'Method hasDirectPermission() bypasses Gate::before. Use can() method instead.',
        //     'identifier' => 'laravel.hasDirectPermission.forbidden'
        // ],
    ];

    /**
     * Constructor allows dependency injection if needed for future configuration.
     */
    public function __construct()
    {
        // This constructor can be extended to accept configuration parameters
        // if needed in the future for external configuration
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Node\Identifier) {
            return [];
        }

        $methodName = $node->name->toString();

        if (! isset($this->forbiddenMethods[$methodName])) {
            return [];
        }

        $config = $this->forbiddenMethods[$methodName];

        return [
            RuleErrorBuilder::message($config['message'])
                ->identifier($config['identifier'])
                ->tip('This rule helps enforce security best practices by ensuring proper authorization flows.')
                ->build(),
        ];
    }

    /**
     * Get all currently configured forbidden methods.
     * Useful for testing and debugging.
     *
     * @return array<string, array{message: string, identifier: string}>
     */
    public function getForbiddenMethods(): array
    {
        return $this->forbiddenMethods;
    }
}
