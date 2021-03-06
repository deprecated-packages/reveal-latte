<?php

declare(strict_types=1);

namespace Reveal\RevealLatte\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use Reveal\LattePHPStanCompiler\NodeAnalyzer\MissingLatteTemplateRenderVariableResolver;
use Reveal\RevealLatte\NodeAnalyzer\TemplateRenderAnalyzer;
use Reveal\TemplatePHPStanCompiler\NodeAnalyzer\TemplateFilePathResolver;
use Symplify\PHPStanRules\Rules\AbstractSymplifyRule;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Reveal\RevealLatte\Tests\Rules\NoNetteRenderMissingVariableRule\NoNetteRenderMissingVariableRuleTest
 */
final class NoNetteRenderMissingVariableRule extends AbstractSymplifyRule
{
    /**
     * @var string
     */
    public const ERROR_MESSAGE = 'Passed "%s" variable that are not used in the template';
    /**
     * @var \Reveal\RevealLatte\NodeAnalyzer\TemplateRenderAnalyzer
     */
    private $templateRenderAnalyzer;
    /**
     * @var \Reveal\TemplatePHPStanCompiler\NodeAnalyzer\TemplateFilePathResolver
     */
    private $templateFilePathResolver;
    /**
     * @var \Reveal\LattePHPStanCompiler\NodeAnalyzer\MissingLatteTemplateRenderVariableResolver
     */
    private $missingLatteTemplateRenderVariableResolver;

    public function __construct(TemplateRenderAnalyzer $templateRenderAnalyzer, TemplateFilePathResolver $templateFilePathResolver, MissingLatteTemplateRenderVariableResolver $missingLatteTemplateRenderVariableResolver)
    {
        $this->templateRenderAnalyzer = $templateRenderAnalyzer;
        $this->templateFilePathResolver = $templateFilePathResolver;
        $this->missingLatteTemplateRenderVariableResolver = $missingLatteTemplateRenderVariableResolver;
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $node
     * @return string[]
     */
    public function process(Node $node, Scope $scope): array
    {
        if (! $this->templateRenderAnalyzer->isNetteTemplateRenderMethodCall($node, $scope)) {
            return [];
        }

        if (count($node->args) < 1) {
            return [];
        }

        $argOrVariadicPlaceholder = $node->args[0];
        if (! $argOrVariadicPlaceholder instanceof Arg) {
            return [];
        }

        $firstArgValue = $argOrVariadicPlaceholder->value;

        $templateFilePaths = $this->templateFilePathResolver->resolveExistingFilePaths($firstArgValue, $scope, 'latte');
        if ($templateFilePaths === []) {
            return [];
        }

        $missingVariableNames = [];
        foreach ($templateFilePaths as $templateFilePath) {
            $currentMissingVariableNames = $this->missingLatteTemplateRenderVariableResolver->resolveFromTemplateAndMethodCall(
                $node,
                $templateFilePath,
                $scope
            );

            $missingVariableNames = array_merge($missingVariableNames, $currentMissingVariableNames);
        }

        if ($missingVariableNames === []) {
            return [];
        }

        $unusedPassedVariablesString = implode('", "', $missingVariableNames);
        $errorMessage = sprintf(self::ERROR_MESSAGE, $unusedPassedVariablesString);
        return [$errorMessage];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(self::ERROR_MESSAGE, [
            new CodeSample(
                <<<'CODE_SAMPLE'
use Nette\Application\UI\Control;

final class SomeControl extends Control
{
    public function render()
    {
        $this->template->render(__DIR__ . '/some_file.latte');
    }
}

// some_file.latte
{$usedValue}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use Nette\Application\UI\Control;

final class SomeControl extends Control
{
    public function render()
    {
        $this->template->render(__DIR__ . '/some_file.latte', [
            'usedValue' => 'value'
        ]);
    }
}

// some_file.latte
{$usedValue}
CODE_SAMPLE
            ),
        ]);
    }
}
