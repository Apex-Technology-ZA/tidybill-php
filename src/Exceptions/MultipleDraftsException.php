<?php

namespace ApexTechnology\TidyBill\Exceptions;

final class MultipleDraftsException extends TidyBillException
{
    /** @param int[] $draftIds */
    public function __construct(
        public readonly string $clientId,
        public readonly array $draftIds,
    ) {
        parent::__construct(sprintf(
            'Multiple draft invoices for TidyBill client %s: %s',
            $clientId,
            implode(', ', $draftIds),
        ));
    }
}
