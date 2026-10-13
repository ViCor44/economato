<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/colaborador_offboarding.php';

final class ColaboradorOffboardingTest extends TestCase
{
    public function testBlocksInactivationWhileAnyLockerIsAssigned(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cacifos');

        validarPrecondicoesInativacao(1, false);
    }

    public function testBlocksInactivationWhileUniformIsUntreated(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('fardas');

        validarPrecondicoesInativacao(0, true);
    }

    public function testAllowsInactivationAfterUniformsAndLockersAreHandled(): void
    {
        $this->expectNotToPerformAssertions();

        validarPrecondicoesInativacao(0, false);
    }

    public function testLockerRequirementTakesPrecedenceWhenBothStepsArePending(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cacifos');

        validarPrecondicoesInativacao(1, true);
    }
}
