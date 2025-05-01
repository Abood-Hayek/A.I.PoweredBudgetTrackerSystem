<?php
session_start(); // Start the session to manage errors or success messages

// Include the database connection
require '../database/db_connection.php';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
  // Sanitize and validate input
  $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

  $password_regex = "/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{5,25}$/";

  if (empty($email) || empty($password) || empty($confirm_password)) {
    $_SESSION['error'] = "All fields are required.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Validate email format
    $_SESSION['error'] = "Invalid email format.";
  } elseif ($password !== $confirm_password) {
    // Check if passwords match
    $_SESSION['error'] = "Passwords do not match.";
  } elseif (!preg_match($password_regex, $password)) {
    // Check if password meets the required format
    $_SESSION['error'] = "Password must be between 5-25 characters, include at least one number, one uppercase letter, and one special character.";
  } else {
    try {
      // Check if the email already exists in the database
      $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
      $stmt->bindParam(':email', $email, PDO::PARAM_STR);
      $stmt->execute();

      if ($stmt->fetch()) {
        $_SESSION['error'] = "An account with this email already exists.";
      } else {
        // Hash the password for secure storage
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insert the new user into the database
        $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (:email, :password)");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);

        if ($stmt->execute()) {
          header("Location: login.php");
        } else {
          $_SESSION['error'] = "An error occurred. Please try again later.";
        }
      }
    } catch (PDOException $e) {
      // Log the error and display a generic error message
      error_log("Database error: " . $e->getMessage());
      $_SESSION['error'] = "An error occurred. Please try again later.";
    }
  }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Sign Up</title>
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.2.0/css/all.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.2.0/css/fontawesome.css">
  <link rel="stylesheet" href="../assets/css/login.css">

</head>

<body>
  <div class="container">
    <div class="screen">
      <div class="screen__content">
        <form class="login" action="signup.php" method="POST">
          <?php
          if (isset($_SESSION['error'])) {
            echo '<p class="error" style="color: red;">' . htmlspecialchars($_SESSION['error'], ENT_QUOTES) . '</p>';
            unset($_SESSION['error']); // Clear error after display
          }
          ?>

          <div class="login__field">
            <i class="login__icon fas fa-envelope"></i>
            <input type="email" class="login__input" name="email" placeholder="Email"
              value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES) : ''; ?>"
              required>
          </div>
          <div class="login__field">
            <i class="login__icon fas fa-lock"></i>
            <input type="password" class="login__input" name="password" placeholder="Password" required
              pattern="^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{5,25}$"
              title="Password must be between 5-25 characters, include at least one uppercase letter, one number, and one special character.">
          </div>
          <div class="login__field">
            <i class="login__icon fas fa-lock"></i>
            <input type="password" class="login__input" name="confirm_password" placeholder="Confirm Password" required>
          </div>

          <div class="button-group" style="display: flex; gap: 10px;">
            <button type="submit" class="button login__submit">
              <span class="button__text">Sign Up</span>
              <i class="button__icon fas fa-user-plus"></i>
            </button>
            <a href="login.php" class="button login__submit" style="text-decoration: none; text-align: center;">
              <span class="button__text">Back to Login</span>
              <i class="button__icon fas fa-arrow-left"></i>
            </a>
          </div>
        </form>
      </div>
      <div class="screen__background">
        <span class="screen__background__shape screen__background__shape4"></span>
        <span class="screen__background__shape screen__background__shape3"></span>
        <span class="screen__background__shape screen__background__shape2"></span>
        <span class="screen__background__shape screen__background__shape1"></span>
      </div>
    </div>
  </div>
</body>

</html>