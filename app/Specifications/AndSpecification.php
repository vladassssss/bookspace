<?php
namespace App\Specifications;

use App\Models\Book;

class AndSpecification implements SpecificationInterface
{
    private array $specifications;

    public function __construct(SpecificationInterface ...$specifications)
    {
        $this->specifications = $specifications;
    }

    public function isSatisfiedBy(Book $book): bool
    {
        foreach ($this->specifications as $spec) {
            if (!$spec->isSatisfiedBy($book)) {
                return false;
            }
        }
        return true;
    }

    // Якщо ви хочете, щоб CompositeSpecification також намагалася створювати SQL-критерії,
    // це буде складніше, оскільки вона повинна об'єднати критерії всіх своїх під-специфікацій.
    // Для спрощення, ми дозволимо BookstoreRepository::findBySpecifications
    // обробляти виклик toQueryCriteria для кожної специфікації окремо.
    // Тому цей метод тут не потрібен, якщо findBySpecifications працює з масивом специфікацій.
    // Якщо findBySpecifications очікує ОДИН об'єкт SpecificationInterface,
    // який може бути CompositeSpecification, то AndSpecification має мати toQueryCriteria.
    // У вашому BookstoreRepository::findBySpecifications, я припустив, що він
    // приймає `array $specifications`, що дозволяє йому обробляти їх по одній.
    // Тому AndSpecification може не мати toQueryCriteria.
}