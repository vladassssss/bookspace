<?php

namespace App\Services;

use App\Models\PaymentInformation;

interface PaymentServiceInterface
{
    /**
     * Валідує вибір способу оплати.
     *
     * @param array $data Масив з даними оплати.
     * @return array Масив помилок валідації (порожній, якщо немає помилок).
     */
    public function validatePaymentData(array $data): array;

    /**
     * Створює об'єкт PaymentInformation з масиву даних.
     *
     * @param array $data Масив з даними оплати.
     * @return PaymentInformation|null
     */
    public function createPaymentInformation(array $data): ?PaymentInformation;

    /**
     * Отримує підсумкову суму замовлення (тимчасово, логіка буде в іншому сервісі).
     *
     * @return float
     */
    public function calculateTotalAmount(): float;
}