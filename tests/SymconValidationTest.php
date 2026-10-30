<?php
declare(strict_types=1);

$stubs = getenv('SYMCON_STUBS_DIR');
if ($stubs === false || !is_file($stubs . '/Validator.php')) {
    throw new RuntimeException('Set SYMCON_STUBS_DIR to the official symcon/SymconStubs checkout.');
}
require_once $stubs . '/Validator.php';

final class SymconValidationTest extends TestCaseSymconValidation
{
    public function testLibraryMetadata(): void { $this->validateLibrary(dirname(__DIR__)); }
    public function testHeaterModule(): void { $this->validateModule(dirname(__DIR__) . '/DreoHeater'); }
}
