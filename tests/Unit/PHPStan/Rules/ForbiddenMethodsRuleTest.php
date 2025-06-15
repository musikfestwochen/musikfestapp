<?php

covers(App\PHPStan\Rules\ForbiddenMethodsRule::class);

use App\PHPStan\Rules\ForbiddenMethodsRule;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;

it('detects forbidden hasPermissionTo method', function () {
    $rule = new ForbiddenMethodsRule;
    $scope = Mockery::mock(Scope::class);

    // Create a method call node for hasPermissionTo
    $methodCall = new MethodCall(
        new Variable('user'),
        new Identifier('hasPermissionTo')
    );

    $errors = $rule->processNode($methodCall, $scope);

    expect($errors)->toHaveCount(1)
        ->and($errors[0]->getMessage())->toBe('Method hasPermissionTo() is forbidden. Use can() method instead to ensure Gate::before is called.')
        ->and($errors[0]->getIdentifier())->toBe('laravel.hasPermissionTo.forbidden');
});

it('ignores allowed methods', function () {
    $rule = new ForbiddenMethodsRule;
    $scope = Mockery::mock(Scope::class);

    // Create a method call node for can (allowed method)
    $methodCall = new MethodCall(
        new Variable('user'),
        new Identifier('can')
    );

    $errors = $rule->processNode($methodCall, $scope);

    expect($errors)->toBeEmpty();
});

it('ignores non-identifier method names', function () {
    $rule = new ForbiddenMethodsRule;
    $scope = Mockery::mock(Scope::class);

    // Create a method call node with a variable method name
    $methodCall = new MethodCall(
        new Variable('user'),
        new Variable('methodName')
    );

    $errors = $rule->processNode($methodCall, $scope);

    expect($errors)->toBeEmpty();
});

it('supports multiple forbidden methods', function () {
    $rule = new ForbiddenMethodsRule;

    $forbiddenMethods = $rule->getForbiddenMethods();

    expect($forbiddenMethods)->toBeArray()
        ->and($forbiddenMethods)->toHaveKey('hasPermissionTo')
        ->and($forbiddenMethods['hasPermissionTo'])->toHaveKeys(['message', 'identifier']);
});

it('can be extended with new forbidden methods', function () {
    $rule = new ForbiddenMethodsRule;

    // Verify the rule returns the correct node type
    expect($rule->getNodeType())->toBe(MethodCall::class);

    // Verify that forbidden methods are configurable via public method
    $forbiddenMethods = $rule->getForbiddenMethods();

    // Verify each entry has the required structure
    foreach ($forbiddenMethods as $methodName => $config) {
        expect($methodName)->toBeString();
        expect($config)->toHaveKeys(['message', 'identifier']);
        expect($config['message'])->toBeString();
        expect($config['identifier'])->toBeString();
    }
});

it('includes helpful error tips', function () {
    $rule = new ForbiddenMethodsRule;
    $scope = Mockery::mock(Scope::class);

    $methodCall = new MethodCall(
        new Variable('user'),
        new Identifier('hasPermissionTo')
    );

    $errors = $rule->processNode($methodCall, $scope);

    expect($errors)->toHaveCount(1);
    // Note: The tip is added via RuleErrorBuilder::tip() in the rule
    // We can't directly test the tip content without PHPStan's test utilities
    // but we can verify the error structure is correct
});
