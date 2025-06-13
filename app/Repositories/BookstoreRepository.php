<?php

namespace App\Repositories;

use App\Models\Book;
use App\Repositories\IBookstoreRepository;
use PDO;
use PDOException;
use Exception;
use TypeError; // Додано імпорт для TypeError

class BookstoreRepository implements IBookstoreRepository
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Отримує книгу за її ідентифікатором.
     *
     * @param int $id Ідентифікатор книги.
     * @return Book|null Об'єкт книги, або null, якщо книгу не знайдено.
     */
     public function getBookById(int $id): ?Book
    {
        error_log("BookstoreRepository: getBookById called with ID: " . $id);
        try {
            // ОНОВЛЕНИЙ ЗАПИТ: Включаємо JOIN з book_moods та moods
            $query = "
                SELECT
                    b.id,
                    b.title,
                    b.author,
                    b.genre,
                    b.price,
                    b.cover_image,
                    b.description,
                    b.language,
                    b.popularity,
                    b.discount,
                    b.quantity,
                    GROUP_CONCAT(m.name SEPARATOR ', ') AS moods_list, -- Змінено на moods_list
                    GROUP_CONCAT(m.id SEPARATOR ',') AS mood_ids       -- Додано для відладки, якщо потрібно
                FROM
                    bookshop_book b
                LEFT JOIN
                    book_moods bm ON b.id = bm.book_id
                LEFT JOIN
                    moods m ON bm.mood_id = m.id
                WHERE b.id = :id
                GROUP BY b.id; -- Важливо: групуємо за ID книги
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $bookData = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log("BookstoreRepository: Raw data fetched for ID " . $id . ": " . print_r($bookData, true));

            if (!$bookData) {
                error_log("BookstoreRepository: No data found for book ID: " . $id . " in the database.");
                return null;
            }

            // Розпарсимо рядок moods_list на масив, якщо він існує
            $moodsArray = [];
            // Змінено з $bookData['moods'] на $bookData['moods_list']
            if (isset($bookData['moods_list']) && !empty($bookData['moods_list'])) {
                $moodsArray = array_map('trim', explode(',', $bookData['moods_list']));
            } else {
                error_log("BookstoreRepository: 'moods_list' column is empty or not set for book ID: " . $id);
            }

            // Створюємо об'єкт Book, перевіряючи наявність та типи даних
            $book = new Book(
                $bookData['id'],
                $bookData['title'],
                $bookData['author'],
                $bookData['genre'],
                (float)($bookData['price'] ?? 0.0),
                $bookData['cover_image'] ?? null,
                $bookData['description'] ?? '',
                $bookData['language'] ?? null,
                (int)($bookData['popularity'] ?? 0),
                (float)($bookData['discount'] ?? 0.0),
                (int)($bookData['quantity'] ?? 0),
                $moodsArray                                  // Передаємо розпарсений масив
            );
            error_log("BookstoreRepository: Book object successfully created for ID " . $id);
            return $book;
        } catch (PDOException $e) {
            error_log("BookstoreRepository: PDOException in getBookById for ID " . $id . ": " . $e->getMessage());
            return null;
        } catch (TypeError $e) {
            error_log("BookstoreRepository: TypeError when creating Book object for ID " . $id . ": " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            error_log("BookstoreRepository: Problematic bookData: " . print_r($bookData ?? 'No data', true));
            return null;
        } catch (Exception $e) {
            error_log("BookstoreRepository: General Exception in getBookById for ID " . $id . ": " . $e->getMessage());
            return null;
        }
    }


    /**
     * Отримує список книг за вказаним жанром.
     *
     * @param string $genre Жанр книги.
     * @return array<Book> Масив об'єктів книг.
     */
    public function getBooksByGenre(string $genre): array
    {
        error_log("BookstoreRepository: getBooksByGenre called with genre: " . $genre);
        try {
            $query = "SELECT id, title, author, genre, price, cover_image, description, language, popularity, discount, quantity, moods FROM bookshop_book WHERE genre = :genre";
            $stmt = $this->connection->prepare($query);
            $stmt->bindParam(':genre', $genre, PDO::PARAM_STR);
            $stmt->execute();
            $booksData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("BookstoreRepository: Fetched " . count($booksData) . " books by genre '" . $genre . "'");

            $bookObjects = [];
            foreach ($booksData as $bookData) {
                $moodsArray = [];
                if (isset($bookData['moods']) && !empty($bookData['moods'])) {
                    $moodsArray = array_map('trim', explode(',', $bookData['moods']));
                }

                $bookObjects[] = new Book(
                    $bookData['id'],
                    $bookData['title'],
                    $bookData['author'],
                    $bookData['genre'],
                    (float)($bookData['price'] ?? 0.0),
                    $bookData['cover_image'] ?? null,
                    $bookData['description'] ?? '',
                    $bookData['language'] ?? null,
                    (int)($bookData['popularity'] ?? 0),
                    (float)($bookData['discount'] ?? 0.0),
                    (int)($bookData['quantity'] ?? 0),
                    $moodsArray
                );
            }
            return $bookObjects;
        } catch (PDOException $e) {
            error_log("BookstoreRepository: PDOException in getBooksByGenre: " . $e->getMessage());
            return [];
        } catch (TypeError $e) {
            error_log("BookstoreRepository: TypeError when creating Book object in getBooksByGenre: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            error_log("BookstoreRepository: Problematic bookData: " . print_r($bookData ?? 'No data', true));
            return [];
        }
    }

    /**
     * Знаходить усі книги, можливо, з обмеженням кількості або за жанром.
     *
     * @param int|null $limit Обмеження на кількість книг.
     * @param string|null $genre Жанр для фільтрації.
     * @return array<Book> Масив об'єктів книг.
     */
    public function findAll(?int $limit = null, ?string $genre = null): array
    {
        error_log("BookstoreRepository: findAll - Start. Limit: " . var_export($limit, true) . ", Genre: " . var_export($genre, true));

        $sql = "
            SELECT
                b.id,
                b.title,
                b.author,
                b.genre,
                b.price,
                b.cover_image,
                b.description,
                b.language,
                b.popularity,
                b.discount,
                b.quantity,
                GROUP_CONCAT(m.name SEPARATOR ', ') AS moods_list,
                GROUP_CONCAT(m.id SEPARATOR ',') AS mood_ids
            FROM
                bookshop_book b
            LEFT JOIN
                book_moods bm ON b.id = bm.book_id
            LEFT JOIN
                moods m ON bm.mood_id = m.id
        ";

        $whereClauses = [];
        $params = [];

        // Фільтрація за жанром, якщо він наданий і не є "all"
        if (!empty($genre) && $genre !== 'all') {
            $whereClauses[] = "b.genre = :genre";
            $params[':genre'] = $genre;
        }

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        $sql .= " GROUP BY b.id";
        $sql .= " ORDER BY b.id"; // Важливо: ORDER BY перед LIMIT

        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT :limit_val"; // Використовуємо інше ім'я параметра для уникнення конфліктів
        }
        $sql .= ";";

        error_log("BookstoreRepository: findAll - SQL query before preparation: " . $sql . " with params: " . json_encode($params));

        try {
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            if ($limit !== null && $limit > 0) {
                $stmt->bindValue(':limit_val', $limit, PDO::PARAM_INT);
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("BookstoreRepository: findAll - Fetched " . count($data) . " books.");

            $books = [];
            foreach ($data as $row) {
                $moodsArray = [];
                if (isset($row['moods_list']) && !empty($row['moods_list'])) {
                    $moodsArray = array_map('trim', explode(', ', $row['moods_list']));
                }

                $book = new Book(
                    $row['id'],
                    $row['title'],
                    $row['author'],
                    $row['genre'],
                    (float)($row['price'] ?? 0.0),
                    $row['cover_image'] ?? null,
                    $row['description'] ?? '',
                    $row['language'] ?? null,
                    (int)($row['popularity'] ?? 0),
                    (float)($row['discount'] ?? 0.0),
                    (int)($row['quantity'] ?? 0),
                    $moodsArray
                );
                $books[] = $book;
            }
            return $books;
        } catch (PDOException $e) {
            error_log("BookstoreRepository: PDOException in findAll: " . $e->getMessage());
            return [];
        } catch (TypeError $e) {
            error_log("BookstoreRepository: TypeError when creating Book object in findAll: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            error_log("BookstoreRepository: Problematic row: " . print_r($row ?? 'No data', true));
            return [];
        }
    }

    /**
     * Отримує список книг зі знижками.
     *
     * @param int $limit Максимальна кількість книг для повернення.
     * @return array<Book> Масив об'єктів книг зі знижками.
     */
    public function getDiscountedBooks(int $limit = 10): array
    {
        error_log("BookstoreRepository: getDiscountedBooks - Start. Limit: " . $limit);

        $sql = "
            SELECT
                b.id,
                b.title,
                b.author,
                b.genre,
                b.price,
                b.cover_image,
                b.description,
                b.language,
                b.popularity,
                b.discount,
                b.quantity,
                GROUP_CONCAT(m.name SEPARATOR ', ') AS moods_list,
                GROUP_CONCAT(m.id SEPARATOR ',') AS mood_ids
            FROM
                bookshop_book b
            LEFT JOIN
                book_moods bm ON b.id = bm.book_id
            LEFT JOIN
                moods m ON bm.mood_id = m.id
            WHERE
                b.discount > 0
            GROUP BY b.id
            ORDER BY b.discount DESC, b.id ASC";
        if ($limit > 0) {
            $sql .= " LIMIT :limit";
        }
        $sql .= ";";

        error_log("BookstoreRepository: getDiscountedBooks - SQL query before preparation: " . $sql . " with params: " . json_encode([':limit' => $limit]));

        try {
            $stmt = $this->connection->prepare($sql);
            if ($limit > 0) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("BookstoreRepository: getDiscountedBooks - Fetched " . count($data) . " books.");

            $books = [];
            foreach ($data as $row) {
                $moodsArray = [];
                if (isset($row['moods_list']) && !empty($row['moods_list'])) {
                    $moodsArray = array_map('trim', explode(', ', $row['moods_list']));
                }

                $book = new Book(
                    $row['id'],
                    $row['title'],
                    $row['author'],
                    $row['genre'],
                    (float)($row['price'] ?? 0.0),
                    $row['cover_image'] ?? null,
                    $row['description'] ?? '',
                    $row['language'] ?? null,
                    (int)($row['popularity'] ?? 0),
                    (float)($row['discount'] ?? 0.0),
                    (int)($row['quantity'] ?? 0),
                    $moodsArray
                );
                $books[] = $book;
            }
            return $books;
        } catch (PDOException $e) {
            error_log("BookstoreRepository: PDOException in getDiscountedBooks: " . $e->getMessage());
            return [];
        } catch (TypeError $e) {
            error_log("BookstoreRepository: TypeError when creating Book object in getDiscountedBooks: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            error_log("BookstoreRepository: Problematic row: " . print_r($row ?? 'No data', true));
            return [];
        }
    }

    /**
     * Отримує список популярних книг за різними критеріями.
     *
     * @param int $limit Максимальна кількість книг для повернення.
     * @param string $orderBy Критерій сортування ('orders', 'ratings', 'wishlist', 'popularity').
     * @return array<Book> Масив об'єктів популярних книг.
     */
    public function getPopularBooks(int $limit, string $orderBy = 'orders'): array
    {
        error_log("BookstoreRepository: getPopularBooks - Start. Limit: " . $limit . ", OrderBy: " . $orderBy);

        // SQL запит з агрегаціями для замовлень, рейтингів та списків бажань
        $sql = "
            SELECT
                b.id,
                b.title,
                b.author,
                b.genre,
                b.price,
                b.cover_image,
                b.description,
                b.language,
                b.popularity,
                b.discount,
                b.quantity,
                COALESCE(oi_agg.total_ordered_quantity, 0) AS total_ordered_quantity,
                COALESCE(r_agg.average_rating, 0.0) AS average_rating,
                COALESCE(wl_agg.wishlist_count, 0) AS wishlist_count,
                GROUP_CONCAT(DISTINCT m.name SEPARATOR ', ') AS moods_list,
                GROUP_CONCAT(DISTINCT m.id SEPARATOR ',') AS mood_ids
            FROM
                bookshop_book b
            LEFT JOIN (
                SELECT book_id, SUM(quantity) AS total_ordered_quantity
                FROM order_items
                GROUP BY book_id
            ) oi_agg ON b.id = oi_agg.book_id
            LEFT JOIN (
                SELECT book_id, AVG(rating) AS average_rating
                FROM reviews
                GROUP BY book_id
            ) r_agg ON b.id = r_agg.book_id
            LEFT JOIN (
                SELECT book_id, COUNT(DISTINCT id) AS wishlist_count
                FROM wishlist
                GROUP BY book_id
            ) wl_agg ON b.id = wl_agg.book_id
            LEFT JOIN
                book_moods bm ON b.id = bm.book_id
            LEFT JOIN
                moods m ON bm.mood_id = m.id
            GROUP BY b.id";

        // Логіка ORDER BY
        switch ($orderBy) {
            case 'orders':
                $sql .= " ORDER BY total_ordered_quantity DESC, b.id ASC";
                break;
            case 'ratings':
                $sql .= " ORDER BY average_rating DESC, b.id ASC";
                break;
            case 'wishlist':
                $sql .= " ORDER BY wishlist_count DESC, b.id ASC";
                break;
            case 'popularity':
                $sql .= " ORDER BY b.popularity DESC, b.id ASC";
                break;
            default:
                $sql .= " ORDER BY b.popularity DESC, b.id ASC";
                break;
        }

        // LIMIT завжди має бути після ORDER BY
        if ($limit > 0) {
            $sql .= " LIMIT :limit";
        }
        $sql .= ";";

        error_log("BookstoreRepository: getPopularBooks - SQL query: " . $sql . " with params: {':limit':" . $limit . "}");

        try {
            $stmt = $this->connection->prepare($sql);
            if ($limit > 0) {
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("BookstoreRepository: getPopularBooks - Fetched " . count($data) . " rows from DB.");

            $books = [];
            foreach ($data as $row) {
                error_log("BookstoreRepository: getPopularBooks - Processing book with data: " . json_encode($row));

                $moodsArray = [];
                if (isset($row['moods_list']) && !empty($row['moods_list'])) {
                    $moodsArray = array_map('trim', explode(', ', $row['moods_list']));
                }

                $book = new Book(
                    $row['id'],
                    $row['title'],
                    $row['author'],
                    $row['genre'],
                    (float)($row['price'] ?? 0.0),
                    $row['cover_image'] ?? null,
                    $row['description'] ?? '',
                    $row['language'] ?? null,
                    (int)($row['popularity'] ?? 0),
                    (float)($row['discount'] ?? 0.0),
                    (int)($row['quantity'] ?? 0),
                    $moodsArray
                );

                // Встановлюємо агреговані дані, якщо вони існують
                if (isset($row['total_ordered_quantity'])) {
                    $book->setTotalOrderedQuantity((int)($row['total_ordered_quantity']));
                }
                if (isset($row['average_rating'])) {
                    $book->setAverageRating((float)($row['average_rating']));
                }
                if (isset($row['wishlist_count'])) {
                    $book->setWishlistCount((int)($row['wishlist_count']));
                }

                $books[] = $book;
            }

            error_log("BookstoreRepository: getPopularBooks - Created " . count($books) . " Book objects.");
            return $books;
        } catch (PDOException $e) {
            error_log("BookstoreRepository: PDOException in getPopularBooks: " . $e->getMessage());
            return [];
        } catch (TypeError $e) {
            error_log("BookstoreRepository: TypeError when creating Book object in getPopularBooks: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            error_log("BookstoreRepository: Problematic row: " . print_r($row ?? 'No data', true));
            return [];
        }
    }


    /**
     * Знаходить книги, які відповідають заданим специфікаціям,
     * використовуючи фільтрацію на рівні бази даних та в пам'яті.
     *
     * @param array<SpecificationInterface> $specifications Масив об'єктів специфікацій.
     * @return array<Book> Масив об'єктів книг.
     */
    public function findBySpecifications(array $specifications): array
    {
        error_log("BookstoreRepository: findBySpecifications - Start. Number of specifications: " . count($specifications));

        $baseSql = "
            SELECT
                b.id,
                b.title,
                b.author,
                b.genre,
                b.price,
                b.cover_image,
                b.description,
                b.language,
                b.popularity,
                b.discount,
                b.quantity,
                GROUP_CONCAT(m.name SEPARATOR ', ') AS moods_list,
                GROUP_CONCAT(m.id SEPARATOR ',') AS mood_ids
            FROM
                bookshop_book b
            LEFT JOIN
                book_moods bm ON b.id = bm.book_id
            LEFT JOIN
                moods m ON bm.mood_id = m.id
        ";
        $whereClauses = [];
        $params = [];
        $hasSqlSpecifications = false;

        foreach ($specifications as $spec) {
            if (method_exists($spec, 'toQueryCriteria')) {
                $criteria = $spec->toQueryCriteria();
                if (!empty($criteria['clause'])) {
                    $whereClauses[] = "(" . $criteria['clause'] . ")";
                    $params = array_merge($params, $criteria['params']);
                    $hasSqlSpecifications = true;
                }
            }
        }

        $fullSql = $baseSql;
        if (!empty($whereClauses)) {
            $fullSql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $fullSql .= " GROUP BY b.id ORDER BY b.id"; // Важливо додати GROUP BY і ORDER BY

        error_log("BookstoreRepository: findBySpecification - SQL query before preparation: " . $fullSql . " with params: " . json_encode($params));

        try {
            $stmt = $this->connection->prepare($fullSql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("BookstoreRepository: findBySpecification - Fetched " . count($data) . " books after SQL filtering.");

            $books = [];
            foreach ($data as $row) {
                $moodsArray = [];
                if (isset($row['moods_list']) && !empty($row['moods_list'])) {
                    $moodsArray = array_map('trim', explode(', ', $row['moods_list']));
                }

                $book = new Book(
                    $row['id'],
                    $row['title'],
                    $row['author'],
                    $row['genre'],
                    (float)($row['price'] ?? 0.0),
                    $row['cover_image'] ?? null,
                    $row['description'] ?? '',
                    $row['language'] ?? null,
                    (int)($row['popularity'] ?? 0),
                    (float)($row['discount'] ?? 0.0),
                    (int)($row['quantity'] ?? 0),
                    $moodsArray
                );
                $books[] = $book;
            }

            // Застосовуємо фільтрацію в пам'яті для специфікацій, які не були оброблені SQL
            $filteredBooks = [];
            foreach ($books as $book) {
                $isSatisfied = true;
                foreach ($specifications as $spec) {
                    // Якщо специфікація не мала toQueryCriteria (або не була оброблена SQL), фільтруємо в пам'яті
                    if (!method_exists($spec, 'toQueryCriteria') || !$hasSqlSpecifications) {
                        if (!$spec->isSatisfiedBy($book)) {
                            $isSatisfied = false;
                            break;
                        }
                    }
                }
                if ($isSatisfied) {
                    $filteredBooks[] = $book;
                }
            }
            error_log("BookstoreRepository: findBySpecification - Resulting " . count($filteredBooks) . " books after in-memory filtering.");
            return $filteredBooks;
        } catch (PDOException $e) {
            error_log("BookstoreRepository: PDOException in findBySpecifications: " . $e->getMessage());
            return [];
        } catch (TypeError $e) {
            error_log("BookstoreRepository: TypeError when creating Book object in findBySpecifications: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            error_log("BookstoreRepository: Problematic row: " . print_r($row ?? 'No data', true));
            return [];
        }
    }

    /**
     * Отримує всі книги, з можливістю обмеження або фільтрації за жанром.
     * Цей метод схожий на findAll, але може бути використаний для загального отримання всіх книг.
     *
     * @param int|null $limit Обмеження на кількість книг.
     * @param string|null $genre Жанр для фільтрації.
     * @return array<Book> Масив об'єктів книг.
     */
    public function getAllBooks(?int $limit = null, ?string $genre = null): array
    {
        error_log("BookstoreRepository: getAllBooks - Start. Limit: " . var_export($limit, true) . ", Genre: " . var_export($genre, true));

        $sql = "SELECT b.*, GROUP_CONCAT(m.name SEPARATOR ',') AS book_moods
                        FROM bookshop_book b
                        LEFT JOIN book_moods bm ON b.id = bm.book_id
                        LEFT JOIN moods m ON bm.mood_id = m.id";

        $params = [];
        $whereClauses = [];

        if (!empty($genre) && $genre !== 'all') {
            $whereClauses[] = "b.genre = :genre";
            $params[':genre'] = $genre;
        }

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        $sql .= " GROUP BY b.id"; // Важливо: Групуємо за ID книги

        if ($limit !== null) {
            $sql .= " LIMIT :limit_val";
        }
        $sql .= ";";

        error_log("BookstoreRepository: getAllBooks - SQL query before preparation: " . $sql . " with params: " . json_encode($params));

        try {
            $stmt = $this->connection->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
            }
            if ($limit !== null) {
                $stmt->bindValue(':limit_val', $limit, PDO::PARAM_INT);
            }

            $stmt->execute();

            $rawBooksData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("BookstoreRepository: getAllBooks - Fetched " . count($rawBooksData) . " raw book data.");

            $bookObjects = [];
            foreach ($rawBooksData as $data) {
                $moodsArray = [];
                if (!empty($data['book_moods'])) {
                    $moodsArray = explode(',', $data['book_moods']);
                    $moodsArray = array_map('trim', $moodsArray);
                }
                $bookObjects[] = new Book(
                    $data['id'],
                    $data['title'],
                    $data['author'],
                    $data['genre'],
                    (float)($data['price'] ?? 0.0),
                    $data['cover_image'] ?? null,
                    $data['description'] ?? '',
                    $data['language'] ?? null,
                    (int)($data['popularity'] ?? 0),
                    (float)($data['discount'] ?? 0.0),
                    (int)($data['quantity'] ?? 0),
                    $moodsArray
                );
            }
            return $bookObjects;
        } catch (PDOException $e) {
            error_log("BookstoreRepository: PDOException in getAllBooks: " . $e->getMessage());
            throw $e; // Перекидаємо виняток далі
        } catch (TypeError $e) {
            error_log("BookstoreRepository: TypeError when creating Book object in getAllBooks: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            error_log("BookstoreRepository: Problematic data: " . print_r($data ?? 'No data', true));
            return [];
        }
    }

    /**
     * Додає нову книгу в базу даних.
     *
     * @param Book $book Об'єкт книги для додавання.
     * @return bool True у разі успіху, false у разі помилки.
     */
    public function addBook(Book $book): bool
    {
        error_log("BookstoreRepository: addBook called. Book Title: " . $book->getTitle() . ", Quantity: " . $book->getQuantity());
        try {
            // Оновлюємо INSERT-запит, щоб включити стовпець `moods`
            // Припускаємо, що moods зберігаються як рядок (наприклад, "mood1, mood2")
            $sql = "INSERT INTO bookshop_book (title, author, genre, price, cover_image, description, language, discount, popularity, quantity, moods)
                            VALUES (:title, :author, :genre, :price, :cover_image, :description, :language, :discount, :popularity, :quantity, :moods)";

            $stmt = $this->connection->prepare($sql);

            $stmt->bindValue(':title', $book->getTitle(), PDO::PARAM_STR);
            $stmt->bindValue(':author', $book->getAuthor(), PDO::PARAM_STR);
            $stmt->bindValue(':genre', $book->getGenre(), PDO::PARAM_STR);
            $stmt->bindValue(':price', $book->getPrice());
            $stmt->bindValue(':cover_image', $book->getCoverImage(), PDO::PARAM_STR);
            $stmt->bindValue(':description', $book->getDescription(), PDO::PARAM_STR);
            $stmt->bindValue(':language', $book->getLanguage(), PDO::PARAM_STR);
            $stmt->bindValue(':discount', $book->getDiscount(), PDO::PARAM_INT);
            $stmt->bindValue(':popularity', $book->getPopularity(), PDO::PARAM_INT);
            $stmt->bindValue(':quantity', $book->getQuantity(), PDO::PARAM_INT);

            // Перетворюємо масив настроїв на рядок для зберігання
            $moodsStr = implode(', ', $book->getMoods());
            $stmt->bindValue(':moods', $moodsStr, PDO::PARAM_STR);

            $executeResult = $stmt->execute();
            error_log("BookstoreRepository: addBook execute result: " . ($executeResult ? 'SUCCESS' : 'FAILURE'));
            if (!$executeResult) {
                error_log("BookstoreRepository: addBook - PDO Error Info: " . print_r($stmt->errorInfo(), true));
            }

            // Якщо ви використовуєте окремі таблиці `book_moods` та `moods` для зв'язку "багато до багатьох",
            // то вам потрібно буде додати логіку для вставки в `book_moods` тут,
            // використовуючи $this->connection->lastInsertId() для отримання ID щойно доданої книги.
            // Приклад:
            // $newBookId = $this->connection->lastInsertId();
            // if ($newBookId) {
            //     foreach ($book->getMoods() as $moodName) {
            //         // Тут логіка: знайти mood_id за $moodName або додати новий mood,
            //         // а потім вставити book_id та mood_id в таблицю book_moods.
            //     }
            // }

            return $executeResult;
        } catch (PDOException $e) {
            error_log("BookstoreRepository::addBook PDOException: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            return false;
        } catch (Exception $e) {
            error_log("BookstoreRepository::addBook General Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            return false;
        }
    }

    /**
     * Видаляє книгу з бази даних за її ідентифікатором.
     *
     * @param int $id Ідентифікатор книги для видалення.
     * @return bool True у разі успіху, false у разі помилки.
     */
    public function deleteBook(int $id): bool
    {
        error_log("BookstoreRepository: Deleting book with ID: " . $id);
        try {
            // Рекомендується видалити пов'язані записи з `book_moods` спочатку, якщо вони існують,
            // для підтримки цілісності даних.
            $stmtMoods = $this->connection->prepare("DELETE FROM book_moods WHERE book_id = :id");
            $stmtMoods->execute(['id' => $id]);
            error_log("BookstoreRepository: Deleted " . $stmtMoods->rowCount() . " moods associations for book ID " . $id);

            // Видаляємо саму книгу
            $stmt = $this->connection->prepare("DELETE FROM bookshop_book WHERE id = :id");
            $success = $stmt->execute(['id' => $id]);
            error_log("BookstoreRepository: Book delete result for ID " . $id . ": " . ($success ? 'SUCCESS' : 'FAILURE'));
            return $success;
        } catch (PDOException $e) {
            error_log("BookstoreRepository: Database error in deleteBook(ID $id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отримує доступну кількість книги за її ідентифікатором.
     *
     * @param int $bookId Ідентифікатор книги.
     * @return int Доступна кількість книги.
     * @throws Exception Якщо виникає помилка бази даних.
     */
    public function getAvailableQuantity(int $bookId): int
    {
        try {
            $stmt = $this->connection->prepare("SELECT quantity FROM bookshop_book WHERE id = :id");
            $stmt->bindParam(':id', $bookId, PDO::PARAM_INT);
            $stmt->execute();
            $quantity = $stmt->fetchColumn();
            return $quantity !== false ? (int)$quantity : 0;
        } catch (PDOException $e) {
            error_log("BookstoreRepository: Database error in getAvailableQuantity(bookId $bookId): " . $e->getMessage());
            throw new Exception("Database error getting book quantity: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Зменшує кількість книги.
     *
     * @param int $bookId Ідентифікатор книги.
     * @param int $amount Кількість, на яку потрібно зменшити.
     * @return bool True у разі успіху, false у разі помилки.
     * @throws Exception Якщо книга не знайдена або недостатня кількість.
     */
    public function decreaseBookQuantity(int $bookId, int $amount): bool
    {
        error_log("BookstoreRepository: Decrease book quantity: BookID $bookId, Amount: $amount");
        $currentQuantity = $this->getAvailableQuantity($bookId);
        if ($currentQuantity === 0 && $this->getBookById($bookId) === null) { // Додаткова перевірка чи книга взагалі існує
            throw new Exception("Book with ID $bookId not found.");
        }
        if ($currentQuantity < $amount) {
            throw new Exception("Insufficient quantity for book ID $bookId. Available: $currentQuantity, required: $amount");
        }
        $newQuantity = $currentQuantity - $amount;
        error_log("BookstoreRepository: Calculated new quantity for BookID $bookId = $newQuantity");

        return $this->setBookQuantity($bookId, $newQuantity);
    }

    /**
     * Встановлює нову кількість книги.
     *
     * @param int $bookId Ідентифікатор книги.
     * @param int $newQuantity Нова кількість.
     * @return bool True у разі успіху, false у разі помилки.
     * @throws Exception Якщо виникає помилка бази даних.
     */
    public function setBookQuantity(int $bookId, int $newQuantity): bool
    {
        error_log("BookstoreRepository: Setting book quantity for BookID $bookId to $newQuantity");
        try {
            $stmt = $this->connection->prepare("UPDATE bookshop_book SET quantity = :new_quantity WHERE id = :id");
            $stmt->bindParam(':new_quantity', $newQuantity, PDO::PARAM_INT);
            $stmt->bindParam(':id', $bookId, PDO::PARAM_INT);
            $success = $stmt->execute();
            if ($success && $stmt->rowCount() > 0) {
                error_log("BookstoreRepository: Successfully set book quantity for BookID $bookId to $newQuantity");
            } else {
                error_log("BookstoreRepository: Failed to set book quantity or no rows affected for BookID $bookId. Success: " . var_export($success, true) . ", Rows affected: " . $stmt->rowCount());
            }
            return $success;
        } catch (PDOException $e) {
            error_log("BookstoreRepository: Database error in setBookQuantity(bookId $bookId, newQuantity $newQuantity): " . $e->getMessage());
            throw new Exception("Database error setting book quantity: " . $e->getMessage(), 0, $e);
        }
    }
     public function searchByTitleAuthor(string $searchTerm): array
    {
        error_log("BookstoreRepository: searchByTitleAuthor called with searchTerm: " . $searchTerm);
        try {
            // Використовуємо LIKE для часткового співпадіння та OR для пошуку за назвою АБО автором
            $query = "
                SELECT
                    b.id,
                    b.title,
                    b.author,
                    b.genre,
                    b.price,
                    b.cover_image,
                    b.description,
                    b.language,
                    b.popularity,
                    b.discount,
                    b.quantity,
                    GROUP_CONCAT(m.name SEPARATOR ', ') AS moods_list
                FROM
                    bookshop_book b
                LEFT JOIN
                    book_moods bm ON b.id = bm.book_id
                LEFT JOIN
                    moods m ON bm.mood_id = m.id
                WHERE
                    b.title LIKE :searchTerm OR b.author LIKE :searchTerm
                GROUP BY b.id
                ORDER BY b.title ASC;
            ";

            $stmt = $this->connection->prepare($query);

            // Додаємо символи % для пошуку за підрядком
            $param = '%' . $searchTerm . '%';
            $stmt->bindParam(':searchTerm', $param, PDO::PARAM_STR);

            $stmt->execute();
            $booksData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("BookstoreRepository: Found " . count($booksData) . " books for search term '" . $searchTerm . "'");

            $bookObjects = [];
            foreach ($booksData as $bookData) {
                $moodsArray = [];
                if (isset($bookData['moods_list']) && !empty($bookData['moods_list'])) {
                    $moodsArray = array_map('trim', explode(', ', $bookData['moods_list']));
                }

                $bookObjects[] = new Book(
                    $bookData['id'],
                    $bookData['title'],
                    $bookData['author'],
                    $bookData['genre'],
                    (float)($bookData['price'] ?? 0.0),
                    $bookData['cover_image'] ?? null,
                    $bookData['description'] ?? '',
                    $bookData['language'] ?? null,
                    (int)($bookData['popularity'] ?? 0),
                    (float)($bookData['discount'] ?? 0.0),
                    (int)($bookData['quantity'] ?? 0),
                    $moodsArray
                );
            }
            return $bookObjects;
        } catch (PDOException $e) {
            error_log("BookstoreRepository: PDOException in searchByTitleAuthor: " . $e->getMessage());
            return [];
        } catch (TypeError $e) {
            error_log("BookstoreRepository: TypeError when creating Book object in searchByTitleAuthor: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            error_log("BookstoreRepository: Problematic bookData: " . print_r($bookData ?? 'No data', true));
            return [];
        } catch (Exception $e) {
            error_log("BookstoreRepository: General Exception in searchByTitleAuthor: " . $e->getMessage());
            return [];
        }
    }
}