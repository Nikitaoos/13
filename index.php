<?php
session_start();
require 'db.php';

header('Content-Type: text/html; charset=UTF-8');
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$messages = [];
$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (!empty($_COOKIE['save'])) {
        setcookie('save', '', time() - 3600);
        $messages[] = 'Спасибо, результаты сохранены.';

        if (!empty($_COOKIE['login']) && !empty($_COOKIE['pass'])) {
            $messages[] = sprintf(
                'Вы можете <a href="login.php">войти</a> с логином <strong>%s</strong> и паролем <strong>%s</strong> для изменения данных.',
                htmlspecialchars($_COOKIE['login']),
                htmlspecialchars($_COOKIE['pass'])
            );
        }
    }

    $field_names = ['name', 'phone', 'email', 'birthdate', 'gender', 'languages', 'bio', 'agreement'];
    foreach ($field_names as $field) {
        $errors[$field] = !empty($_COOKIE[$field.'_error']) ? $_COOKIE[$field.'_error'] : '';
        if (!empty($errors[$field])) {
            setcookie($field.'_error', '', time() - 3600);
        }
        $values[$field] = empty($_COOKIE[$field.'_value']) ? '' : $_COOKIE[$field.'_value'];
    }

    if (!empty($_SESSION['login'])) {
        try {
            $stmt = $pdo->prepare("SELECT a.*, GROUP_CONCAT(l.name) as languages
                FROM applications a
                LEFT JOIN application_languages al ON a.id = al.application_id
                LEFT JOIN languages l ON al.language_id = l.id
                WHERE a.login = ?
                GROUP BY a.id");
            $stmt->execute([$_SESSION['login']]);
            $user_data = $stmt->fetch();

            if ($user_data) {
                $values = array_merge($values, $user_data);
                $values['languages'] = $user_data['languages'] ? explode(',', $user_data['languages']) : [];
            }
        } catch (PDOException $e) {
            $messages[] = '<div class="alert alert-danger">Ошибка загрузки данных: '.htmlspecialchars($e->getMessage()).'</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru-RU">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <meta charset="UTF-8">
    <title>Форма</title>
</head>
<body class="bg-light">

<div class="container mt-4">

    <?php if (!empty($messages)): ?>
        <div class="mb-3">
            <?php foreach ($messages as $message): ?>
                <div class="alert alert-info"><?= $message ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
    $has_errors = false;
    foreach ($errors as $error) {
        if (!empty($error)) {
            $has_errors = true;
            break;
        }
    }
    ?>

    <?php if ($has_errors): ?>
        <div class="alert alert-danger mb-3">
            <h4>Обнаружены ошибки:</h4>
            <ul class="mb-0">
                <?php foreach ($errors as $field => $error): ?>
                    <?php if (!empty($error)): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="sub.php" method="POST" class="w-75 mx-auto bg-white p-4 rounded shadow-sm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="mb-3">
            <label class="form-label">ФИО:</label>
            <input type="text" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" name="name" value="<?= htmlspecialchars($values['name'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Телефон:</label>
            <input type="tel" class="form-control <?= !empty($errors['phone']) ? 'is-invalid' : '' ?>" name="phone" value="<?= htmlspecialchars($values['phone'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">E-mail:</label>
            <input type="email" class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>" name="email" value="<?= htmlspecialchars($values['email'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Дата рождения:</label>
            <input type="date" class="form-control <?= !empty($errors['birthdate']) ? 'is-invalid' : '' ?>" name="birthdate" value="<?= htmlspecialchars($values['birthdate'] ?? '') ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Пол:</label><br>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="gender" value="male" <?= ($values['gender'] ?? '') === 'male' ? 'checked' : '' ?>>
                <label class="form-check-label">Мужской</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="gender" value="female" <?= ($values['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                <label class="form-check-label">Женский</label>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Любимые языки программирования:</label>
            <select class="form-select <?= !empty($errors['languages']) ? 'is-invalid' : '' ?>" name="languages[]" multiple size="5" required>
                <?php
                $allLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala'];
                $selectedLanguages = isset($values['languages']) ? (is_array($values['languages']) ? $values['languages'] : explode(',', $values['languages'])) : [];

                foreach ($allLanguages as $lang): ?>
                    <option value="<?= htmlspecialchars($lang) ?>" <?= in_array($lang, $selectedLanguages) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lang) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Биография:</label>
            <textarea class="form-control <?= !empty($errors['bio']) ? 'is-invalid' : '' ?>" name="bio" rows="4" required><?= htmlspecialchars($values['bio'] ?? '') ?></textarea>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input <?= !empty($errors['agreement']) ? 'is-invalid' : '' ?>" type="checkbox" name="agreement" value="1" <?= !empty($values['agreement']) ? 'checked' : '' ?> required>
            <label class="form-check-label">С контрактом ознакомлен(а)</label>
        </div>

        <button type="submit" class="btn btn-primary">Сохранить</button>
        <?php if (!empty($_SESSION['login'])): ?>
            <a href="logout.php" class="btn btn-danger ms-2">Выйти</a>
        <?php endif; ?>
    </form>
</div>

</body>
</html>
