<?php

declare(strict_types=1);

namespace Reveal\RevealLatte\Tests\Rules\NoNetteRenderUnusedVariableRule\Fixture;

use Nette\Application\UI\Control;

final class SkipExtendsVariable extends Control
{
    public function render()
    {
        $this->template->render(__DIR__ . '/../Source/template_with_extends.latte', [
            'use_me' => 'some_value',
        ]);
    }
}
