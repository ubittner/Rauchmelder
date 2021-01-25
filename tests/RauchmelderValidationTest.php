<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class RauchmelderValidationTest extends TestCaseSymconValidation
{
    public function testValidateRauchmelder(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateRauchmelderModule(): void
    {
        $this->validateModule(__DIR__ . '/../Rauchmelder');
    }
}