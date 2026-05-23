<?php

declare(strict_types=1);

use Syriable\Localizer\Analysis\PhpExpressionClassifier;
use Syriable\Localizer\Analysis\PlaceholderType;

beforeEach(function () {
    $this->classifier = new PhpExpressionClassifier;
});

describe('PhpExpressionClassifier', function () {
    it('classifies a bare variable', function () {
        $result = $this->classifier->classify('$name');

        expect($result['type'])->toBe(PlaceholderType::Variable);
        expect($result['structure'])->toBe(['name' => '$name']);
    });

    it('classifies a simple object property', function () {
        $result = $this->classifier->classify('$user->name');

        expect($result['type'])->toBe(PlaceholderType::ObjectProperty);
        expect($result['structure'])->toBe([
            'object' => '$user',
            'property' => 'name',
        ]);
    });

    it('classifies a nested object property', function () {
        $result = $this->classifier->classify('$user->profile->full_name');

        expect($result['type'])->toBe(PlaceholderType::NestedObjectProperty);
        expect($result['structure'])->toBe([
            'object' => '$user',
            'path' => ['profile', 'full_name'],
        ]);
    });

    it('classifies a deeply nested object property', function () {
        $result = $this->classifier->classify('$a->b->c->d->e');

        expect($result['type'])->toBe(PlaceholderType::NestedObjectProperty);
        expect($result['structure']['path'])->toBe(['b', 'c', 'd', 'e']);
    });

    it('classifies a function call with no arguments', function () {
        $result = $this->classifier->classify('getUser()');

        expect($result['type'])->toBe(PlaceholderType::FunctionCall);
        expect($result['structure'])->toBe([
            'function' => 'getUser',
            'arguments' => [],
        ]);
    });

    it('classifies a function call with arguments', function () {
        $result = $this->classifier->classify('getOrdersCount($user->id)');

        expect($result['type'])->toBe(PlaceholderType::FunctionCall);
        expect($result['structure'])->toBe([
            'function' => 'getOrdersCount',
            'arguments' => ['$user->id'],
        ]);
    });

    it('classifies a function call with multiple arguments and a string', function () {
        $result = $this->classifier->classify("implode(', ', \$list)");

        expect($result['type'])->toBe(PlaceholderType::FunctionCall);
        expect($result['structure']['function'])->toBe('implode');
        expect($result['structure']['arguments'])->toBe(["', '", '$list']);
    });

    it('classifies a method call', function () {
        $result = $this->classifier->classify('$user->getOrders()');

        expect($result['type'])->toBe(PlaceholderType::MethodCall);
        expect($result['structure']['object'])->toBe('$user');
        expect($result['structure']['method'])->toBe('getOrders');
        expect($result['structure']['arguments'])->toBe([]);
    });

    it('classifies a method call with a chained property path', function () {
        $result = $this->classifier->classify('$user->profile->getAvatar()');

        expect($result['type'])->toBe(PlaceholderType::MethodCall);
        expect($result['structure']['object'])->toBe('$user');
        expect($result['structure']['path'])->toBe(['profile']);
        expect($result['structure']['method'])->toBe('getAvatar');
    });

    it('classifies a static method call', function () {
        $result = $this->classifier->classify('User::find($id)');

        expect($result['type'])->toBe(PlaceholderType::StaticMethodCall);
        expect($result['structure'])->toBe([
            'class' => 'User',
            'method' => 'find',
            'arguments' => ['$id'],
        ]);
    });

    it('classifies a fully-qualified static method call', function () {
        $result = $this->classifier->classify('\\App\\Models\\User::find($id)');

        expect($result['type'])->toBe(PlaceholderType::StaticMethodCall);
        expect($result['structure']['class'])->toBe('\\App\\Models\\User');
    });

    it('classifies an integer literal', function () {
        $result = $this->classifier->classify('42');

        expect($result['type'])->toBe(PlaceholderType::Literal);
        expect($result['structure'])->toBe(['value' => '42']);
    });

    it('classifies a negative numeric literal', function () {
        $result = $this->classifier->classify('-1.5');

        expect($result['type'])->toBe(PlaceholderType::Literal);
        expect($result['structure'])->toBe(['value' => '-1.5']);
    });

    it('classifies a single-quoted string literal', function () {
        $result = $this->classifier->classify("'admin'");

        expect($result['type'])->toBe(PlaceholderType::Literal);
        expect($result['structure'])->toBe(['value' => "'admin'"]);
    });

    it('classifies a double-quoted string literal', function () {
        $result = $this->classifier->classify('"admin"');

        expect($result['type'])->toBe(PlaceholderType::Literal);
        expect($result['structure'])->toBe(['value' => '"admin"']);
    });

    it('classifies boolean and null literals', function () {
        expect($this->classifier->classify('true')['type'])->toBe(PlaceholderType::Literal);
        expect($this->classifier->classify('false')['type'])->toBe(PlaceholderType::Literal);
        expect($this->classifier->classify('null')['type'])->toBe(PlaceholderType::Literal);
    });

    it('falls back to expression for arithmetic', function () {
        $result = $this->classifier->classify('$count + 1');

        expect($result['type'])->toBe(PlaceholderType::Expression);
        expect($result['structure'])->toBe(['raw' => '$count + 1']);
    });

    it('falls back to expression for ternary', function () {
        $result = $this->classifier->classify('$count > 1 ? "many" : "one"');

        expect($result['type'])->toBe(PlaceholderType::Expression);
    });

    it('preserves the verbatim source in expression fallback', function () {
        $weird = '$x ?? $y->fallback() ?: "n/a"';
        $result = $this->classifier->classify($weird);

        expect($result['type'])->toBe(PlaceholderType::Expression);
        expect($result['structure']['raw'])->toBe($weird);
    });

    it('does not misclassify chained calls with trailing junk', function () {
        // The expression is technically valid PHP but its outer brackets
        // do not balance as a single call; we should fall back to "expression"
        // rather than misreporting structure.
        $result = $this->classifier->classify('foo() + bar()');

        expect($result['type'])->toBe(PlaceholderType::Expression);
    });

    it('trims surrounding whitespace before matching', function () {
        $result = $this->classifier->classify("\n  \$user->name  \t");

        expect($result['type'])->toBe(PlaceholderType::ObjectProperty);
    });

    it('handles config-style function calls with dotted arguments', function () {
        $result = $this->classifier->classify("config('app.name')");

        expect($result['type'])->toBe(PlaceholderType::FunctionCall);
        expect($result['structure']['function'])->toBe('config');
        expect($result['structure']['arguments'])->toBe(["'app.name'"]);
    });
});
