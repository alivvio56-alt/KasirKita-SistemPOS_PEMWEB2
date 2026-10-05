<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception untuk pelanggaran aturan bisnis (stok kurang, transisi status
 * tidak valid, dsb). Dirender menjadi response JSON yang konsisten.
 */
class BusinessException extends Exception
{
    public function __construct(
        string $message,
        protected int $status = 422,
        protected array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
