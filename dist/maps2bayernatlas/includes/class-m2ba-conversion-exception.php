<?php
declare(strict_types=1);

final class M2BA_Conversion_Exception extends RuntimeException {
    private int $status;
    private string $error_key;

    public function __construct(string $message, int $status = 400, string $error_key = 'm2ba_error') {
        parent::__construct($message);

        $this->status    = $status;
        $this->error_key = $error_key;
    }

    public function get_status(): int {
        return $this->status;
    }

    public function get_error_key(): string {
        return $this->error_key;
    }
}
