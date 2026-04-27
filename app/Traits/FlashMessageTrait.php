<?php

declare(strict_types=1);

namespace App\Traits;

trait FlashMessageTrait
{
    private function setFlash(string $type, string $message): void
    {
        if ($type === 'success') {
            $_SESSION['flash_success'] = $message;
            return;
        }

        $_SESSION['flash_error'] = $message;
    }
}
