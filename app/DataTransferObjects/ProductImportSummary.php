<?php

namespace App\DataTransferObjects;

class ProductImportSummary
{
    public function __construct(
        public int $total = 0,
        public int $created = 0,
        public int $updated = 0,
        public int $invalid = 0,
        public int $duplicates = 0,
        public array $errors = [],
    ) {
    }

    public function recordError(int $row, string $message): void
    {
        $this->errors[] = ['row' => $row, 'message' => $message];
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'created' => $this->created,
            'updated' => $this->updated,
            'invalid' => $this->invalid,
            'duplicates' => $this->duplicates,
            'errors' => $this->errors,
        ];
    }
}