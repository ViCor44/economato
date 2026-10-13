<?php

function validarPrecondicoesInativacao(int $cacifosPendentes, bool $temFardasNaoTratadas): void
{
    if ($cacifosPendentes > 0) {
        throw new DomainException("Devolva primeiro todos os cacifos associados ao colaborador.");
    }

    if ($temFardasNaoTratadas) {
        throw new DomainException("Trate todas as fardas atribuídas: devolva-as ou marque-as formalmente como dívida.");
    }
}
