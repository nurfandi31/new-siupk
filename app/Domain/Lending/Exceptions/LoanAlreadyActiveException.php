<?php

declare(strict_types=1);

namespace App\Domain\Lending\Exceptions;

use DomainException;

final class LoanAlreadyActiveException extends DomainException
{
    public function __construct(
        public readonly string $memberIdentifier,
        public readonly string $loanNumber,
        public readonly string $currentStatus,
    ) {
        parent::__construct(sprintf(
            'Anggota %s masih memiliki pinjaman aktif (%s) berstatus "%s". Selesaikan pinjaman sebelumnya terlebih dahulu.',
            $memberIdentifier,
            $loanNumber,
            $currentStatus,
        ));
    }
}
