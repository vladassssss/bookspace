<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Яка книга тобі підійде?</title>
    <style>
        body {
            font-family: 'Nunito Sans', sans-serif;
            line-height: 1.7;
            margin: 0;
            padding: 40px 20px;
            background: linear-gradient(135deg, #2c3e50, #000000);
            color: #ecf0f1;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }

        h1 {
            color: #3498db;
            text-align: center;
            margin-bottom: 40px;
            font-size: 2.8em;
            letter-spacing: 0.05em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        form {
            background-color: #34495e;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.4);
            max-width: 700px;
            width: 100%;
            margin: 0 auto;
            animation: fadeIn 0.5s ease-out;
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        .form-group {
            margin-bottom: 0;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #f0f0f0;
            font-size: 1.1em;
        }

        select, input[type="text"] { /* Додано input[type="text"] */
            padding: 12px;
            border: none;
            border-radius: 6px;
            width: calc(100% - 24px);
            box-sizing: border-box;
            margin-top: 8px;
            background-color: #4a6572;
            color: #ecf0f1;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        select:focus, input[type="text"]:focus {
            outline: none;
            border-color: #3498db;
        }

        .mood-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px 15px;
            margin-top: 10px;
        }

        .mood-option {
            display: flex;
            align-items: center;
        }

        input[type="radio"] {
            width: auto;
            margin-right: 8px;
            vertical-align: middle;
        }

        label[for^="mood_"] {
            display: inline-block;
            color: #d0d0d0;
            font-size: 0.95em;
            cursor: pointer;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        button[type="submit"] {
            background-color: #3498db;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2em;
            transition: background-color 0.3s ease, transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            justify-self: center;
            width: fit-content;
        }

        button[type="submit"]:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }

        .interesting-note {
            margin-top: 40px;
            padding: 20px;
            background-color: #2c3e50;
            border-left: 5px solid #3498db;
            border-radius: 6px;
            font-style: italic;
            color: #95a5a6;
            animation: slideInLeft 0.5s ease-out;
            text-align: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
    <h1>Яка книга тобі підійде?</h1>
    <form action="recommendations.php" method="POST">
        <div class="form-group">
            <label for="genre">Який жанр вас цікавить?</label>
            <select name="genre" id="genre">
                <option value="">Будь-який</option>
                <option value="Детектив">Детектив</option>
                <option value="Фантастика">Фантастика</option>
                <option value="Наукова фантастика">Наукова фантастика</option>
                <option value="Жахи">Жахи</option>
                <option value="Романтика">Романтика</option>
                <option value="Психологія">Психологія</option>
                <option value="Белетристика">Складна література</option>
                <option value="Антиутопія">Антиутопія</option>
                <option value="Історичний роман">Історичний роман</option>
                <option value="Фентезі">Фентезі</option>
                <option value="Казка">Казка</option>
                <option value="Пригоди">Пригоди</option>
                <option value="Підлітковий">Підліткова література</option>
                <option value="Драма">Драма</option>
                <option value="Класика">Класика</option>
                <option value="Наука">Наука</option>
            </select>
        </div>

        <div class="form-group">
            <label>Який у вас сьогодні настрій?</label>
            <div class="mood-options">
                <div class="mood-option">
                    <input type="radio" id="mood_happy" name="mood" value="Гумористичний">
                    <label for="mood_happy">Веселий</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_sad" name="mood" value="Трагічний">
                    <label for="mood_sad">Сумний</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_exciting" name="mood" value="Захоплюючий">
                    <label for="mood_exciting">Захоплюючий</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_relaxing" name="mood" value="Атмосферний">
                    <label for="mood_relaxing">Розслабляючий</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_thoughtful" name="mood" value="Філософський">
                    <label for="mood_thoughtful">Задумливий</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_inspiring" name="mood" value="Героїчний">
                    <label for="mood_inspiring">Надихаючий</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_mysterious" name="mood" value="Детективний">
                    <label for="mood_mysterious">Загадковий</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_scary" name="mood" value="Моторошний">
                    <label for="mood_scary">Лячно</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_adventurous" name="mood" value="Пригодницький">
                    <label for="mood_adventurous">Пригодницький</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_romantic" name="mood" value="Романтичний">
                    <label for="mood_romantic">Романтичний</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_complex" name="mood" value="Складний">
                    <label for="mood_complex">Складний</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_epic" name="mood" value="Епічний">
                    <label for="mood_epic">Епічний</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_sensitive" name="mood" value="Чуттєвий">
                    <label for="mood_sensitive">Чуттєвий</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_antiwar" name="mood" value="Антивоєнний">
                    <label for="mood_antiwar">Проти війни</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_teen" name="mood" value="Підлітковий">
                    <label for="mood_teen">Підлітковий</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_historical" name="mood" value="Історичний">
                    <label for="mood_historical">Історичний</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_fantasy" name="mood" value="Фентезійний">
                    <label for="mood_fantasy">Фентезійний</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_fairy" name="mood" value="Казковий">
                    <label for="mood_fairy">Казковий</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_psychological" name="mood" value="Психологічний">
                    <label for="mood_psychological">Психологічний</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_scientific" name="mood" value="Науковий">
                    <label for="mood_scientific">Науковий</label>
                </div>
                <div class="mood-option">
                    <input type="radio" id="mood_classic" name="mood" value="Класичний">
                    <label for="mood_classic">Класичний</label>
                </div>
            </div>
        </div>

        <label for="search_query">Пошук в описі:</label>
<input type="text" id="search_query" name="search_query" placeholder="Ключові слова в описі">
        <button type="submit">Отримати рекомендацію</button>

        <div class="interesting-note">
            <p><b>Цікаво знати:</b> Наш алгоритм постійно навчається, щоб надавати вам найточніші та найцікавіші рекомендації. Чим більше ви використовуєте сервіс, тим краще він розуміє ваші смаки!</p>
        </div>
    </form>
</body>
</html>