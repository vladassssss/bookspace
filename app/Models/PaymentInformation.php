<?php

namespace App\Models;

class PaymentInformation
{
    private string $paymentMethod;

    public function __construct(string $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }
}