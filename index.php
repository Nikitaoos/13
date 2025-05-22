<?php
session_start();
require 'db.php';

header('Content-Type: text/html; charset=UTF-8');
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
   <meta charset="UTF-8">
    <title>index</title>
  </head>

  <body>
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
    <form action="sub.php" method="POST" id="form" class="w-50 mx-auto">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

          <label class="form-label">
            1) ФИО:<br>
            <input type="text" class="form-control <?php echo !empty($errors['name']) ? 'is-invalid' : ''; ?>" placeholder="Введите ваше ФИО" name="name" id = "name" required
                           value="<?php echo htmlspecialchars($values['name'] ?? ''); ?>">
                    <?php if (!empty($errors['name'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($errors['name']); ?></div>
                    <?php endif; ?>
          </label><br>

          <label class="form-label">
            2) Телефон:<br>
            <input class="form-control" type="tel" placeholder="+123456-78-90" name="phone" id="phone" required
                           value="<?php echo htmlspecialchars($values['phone'] ?? ''); ?>"
                           class="<?php echo !empty($errors['phone']) ? 'is-invalid' : ''; ?>">
                           <?php if (!empty($errors['phone'])): ?>
                      <div class="invalid-feedback"><?php echo htmlspecialchars($errors['phone']); ?></div>
                  <?php endif; ?>
          </label><br>

          <label class="form-label">
            3) e-mail:<br>
            <input class="form-control" type="email" placeholder="Введите вашу почту" name="email" id="email" required
                           value="<?php echo htmlspecialchars($values['email'] ?? ''); ?>"
                           class="<?php echo !empty($errors['email']) ? 'is-invalid' : ''; ?>">
                    <?php if (!empty($errors['birthdate'])): ?>
                       <div class="invalid-feedback"><?php echo htmlspecialchars($errors['birthdate']); ?></div>
                   <?php endif; ?>
          </label><br>

          <label class="form-label">
            4) Дата рождения:<br>
            <input class="form-control" value="2000-07-15" type="date" name="birthdate" id="birthdate" required
                           value="<?php echo htmlspecialchars($values['birthdate'] ?? ''); ?>"
                           class="<?php echo !empty($errors['birthdate']) ? 'is-invalid' : ''; ?>">
                     <?php if (!empty($errors['birthdate'])): ?>
                        <div class="invalid-feedback"><?php echo htmlspecialchars($errors['birthdate']); ?></div>
                    <?php endif; ?>
           </label><br>
          <div><br>
            5) Пол:<br>
          <label class="form-check-label"><input type="radio" checked="checked" class="form-check-input" value="male" id="male" name="gender" required
                               <?php echo ($values['gender'] ?? '') === 'male' ? 'checked' : ''; ?>
                               class="<?php echo !empty($errors['gender']) ? 'is-invalid' : ''; ?>">
            Мужской</label>
          <label class="form-check-label"><input type="radio" class="form-check-input" value="female" id="female" name="gender"
                               <?php echo ($values['gender'] ?? '') === 'female' ? 'checked' : ''; ?>
                               class="<?php echo !empty($errors['gender']) ? 'is-invalid' : ''; ?>">
            Женский</label><br>
            <?php if (!empty($errors['gender'])): ?>
                <div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['gender']); ?></div>
            <?php endif; ?>

          </div><br>

          <label class="form-label">
            6) Любимый язык программирования:<br>
            <select class="form-select" id="languages" name="languages[]" multiple="multiple" required class="<?php echo !empty($errors['languages']) ? 'is-invalid' : ''; ?>" size="5">
            <?php
              $allLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala'];
              $selectedLanguages = isset($values['languages']) ? (is_array($values['languages']) ? $values['languages'] : explode(',', $values['languages'])) : [];

              foreach ($allLanguages as $lang): ?>
                  <option value="<?php echo htmlspecialchars($lang); ?>"
                      <?php echo in_array($lang, $selectedLanguages) ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($lang); ?>
                  </option>
              <?php endforeach; ?>
          </select>
          <?php if (!empty($errors['languages'])): ?>
              <div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['languages']); ?></div>
          <?php endif; ?>
          </label><br>

          <label class="form-label">
            7) Биография:<br>
            <input type="text" class="form-control" id="bio" name="bio" required
                              class="<?php echo !empty($errors['bio']) ? 'is-invalid' : ''; ?>"><?php
                              echo htmlspecialchars($values['bio'] ?? ''); ?></textarea>
                      <?php if (!empty($errors['bio'])): ?>
                          <div class="invalid-feedback"><?php echo htmlspecialchars($errors['bio']); ?></div>
                      <?php endif; ?>
          </label><br>

            8):<br>
          <label class="form-check-label"><input type="checkbox" class="form-check-input" name="agreement" id="agreement" value="1" required
                           class="<?php echo !empty($errors['agreement']) ? 'is-invalid' : ''; ?>">
            <?php echo ($values['agreement'] ?? '') ? 'checked' : ''; ?>
            С контрактом ознакомлен(а)
            <?php if (!empty($errors['agreement'])): ?>
                <div class="invalid-feedback d-block"><?php echo htmlspecialchars($errors['agreement']); ?></div>
            <?php endif; ?>
          </label><br>

            9)Кнопка:<br>
          <button type="submit" name="save" class="btn btn-primary">Опубликовать</button>

          <?php if (!empty($_SESSION['login'])): ?>
                <a href="logout.php" class="btn btn-danger">Выйти</a>
            <?php endif; ?>
    </form>
  </body>

</html>