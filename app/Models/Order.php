<?php
namespace App\Models;

class Order
{
    private ?int $id = null; // Краще ініціалізувати null, якщо ID може бути не одразу встановлений
    private int $userId;
    private string $orderDate;
    private string $status;        // <--- ДОДАНО: Властивість для статусу
    private float $totalAmount;
    private ?int $deliveryAddressId;
    private ?string $phone;
    private ?string $userName = null; // Краще ініціалізувати null
    private array $orderItems = [];

    /**
     * Конструктор для створення нового об'єкта замовлення.
     *
     * @param int $userId ID користувача, який розміщує замовлення.
     * @param float $totalAmount Загальна сума замовлення.
     * @param int|null $deliveryAddressId ID адреси доставки (може бути null, якщо адреса не вказана).
     * @param string|null $phone Номер телефону для зв'язку щодо замовлення.
     * @param string $status Початковий статус замовлення. За замовчуванням 'pending'.
     */
    public function __construct(
        int $userId,
        float $totalAmount,
        ?int $deliveryAddressId,
        ?string $phone,
        string $status = 'pending' // <--- ЗМІНЕНО: Додано параметр $status з дефолтним значенням
    ) {
        $this->userId = $userId;
        $this->totalAmount = $totalAmount;
        $this->deliveryAddressId = $deliveryAddressId;
        $this->phone = $phone;
        $this->status = $status; // <--- ЗМІНЕНО: Встановлюємо статус
        // $this->orderDate встановлюється або тут, або через setOrderDate,
        // або через базу даних (краще через БД з NOW())
        // Якщо ви все ще встановлюєте його тут, то:
        // $this->orderDate = date('Y-m-d H:i:s');
    }

    // --- Гетери та Сетери для всіх властивостей ---

    /**
     * Отримує ID замовлення.
     * @return int|null ID замовлення або null, якщо не встановлено.
     */
    public function getId(): ?int { return $this->id; }

    /**
     * Встановлює ID замовлення.
     * @param int $id ID замовлення.
     */
    public function setId(int $id): void { $this->id = $id; }

    /**
     * Отримує ID користувача, який розмістив замовлення.
     * @return int ID користувача.
     */
    public function getUserId(): int { return $this->userId; }

    /**
     * Встановлює ID користувача (зазвичай встановлюється через конструктор, але може бути корисним для Hydration).
     * @param int $userId ID користувача.
     */
    public function setUserId(int $userId): void { $this->userId = $userId; }

    /**
     * Отримує дату та час створення замовлення.
     * @return string Дата та час у форматі 'YYYY-MM-DD HH:MM:SS'.
     */
    public function getOrderDate(): string { return $this->orderDate; }

    /**
     * Встановлює дату та час створення замовлення.
     * @param string $orderDate Дата та час у форматі 'YYYY-MM-DD HH:MM:SS'.
     */
    public function setOrderDate(string $orderDate): void { $this->orderDate = $orderDate; }

    /**
     * Отримує статус замовлення.
     * @return string Статус замовлення.
     */
    public function getStatus(): string // <--- ДОДАНО: Геттер для статусу
    {
        return $this->status;
    }

    /**
     * Встановлює статус замовлення.
     * @param string $status Статус замовлення.
     */
    public function setStatus(string $status): void // <--- ДОДАНО: Сеттер для статусу (може знадобитися для оновлення)
    {
        $this->status = $status;
    }

    /**
     * Отримує загальну суму замовлення.
     * @return float Загальна сума.
     */
    public function getTotalAmount(): float { return $this->totalAmount; }

    /**
     * Встановлює загальну суму замовлення.
     * @param float $totalAmount Загальна сума.
     */
    public function setTotalAmount(float $totalAmount): void { $this->totalAmount = $totalAmount; }

    /**
     * Отримує ID адреси доставки.
     * @return int|null ID адреси доставки або null.
     */
    public function getDeliveryAddressId(): ?int { return $this->deliveryAddressId; }

    /**
     * Встановлює ID адреси доставки.
     * @param int|null $deliveryAddressId ID адреси доставки або null.
     */
    public function setDeliveryAddressId(?int $deliveryAddressId): void { $this->deliveryAddressId = $deliveryAddressId; }

    /**
     * Отримує номер телефону, вказаний для замовлення.
     * @return string|null Номер телефону або null.
     */
    public function getPhone(): ?string { return $this->phone; }

    /**
     * Встановлює номер телефону.
     * @param string|null $phone Номер телефону або null.
     */
    public function setPhone(?string $phone): void { $this->phone = $phone; }

    /**
     * Отримує ім'я користувача (якщо встановлено, наприклад, з Join у репозиторії).
     * @return string|null Ім'я користувача або null.
     */
    public function getUserName(): ?string { return $this->userName; }

    /**
     * Встановлює ім'я користувача.
     * @param string $userName Ім'я користувача.
     */
    public function setUserName(string $userName): void { $this->userName = $userName; }

    /**
     * Отримує масив позицій замовлення (об'єктів OrderItem).
     * @return OrderItem[] Масив об'єктів OrderItem.
     */
    public function getOrderItems(): array { return $this->orderItems; }

    /**
     * Встановлює масив позицій замовлення (об'єктів OrderItem).
     * @param OrderItem[] $orderItems Масив об'єктів OrderItem.
     */
    public function setOrderItems(array $orderItems): void { $this->orderItems = $orderItems; }
}