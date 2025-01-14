<?php
session_start(); // Start the session to manage logged-in users

// Include the database connection
require '../database/db_connection.php'; // Adjust the path as needed

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = filter_input(type: INPUT_POST, var_name: 'email', filter: FILTER_SANITIZE_EMAIL);
  $password = isset($_POST['password']) ? $_POST['password'] : '';

  // Validate that email and password are not empty
  if (empty($email) || empty($password)) {
    $_SESSION['error'] = "Email and password cannot be empty.";
  } else {
    try {
      // Check if user exists in the database
      $stmt = $pdo->prepare(query: "SELECT id, email, password FROM users WHERE email = :email");
      $stmt->bindParam(param: ':email', var: $email, type: PDO::PARAM_STR);
      $stmt->execute();
      $user = $stmt->fetch(mode: PDO::FETCH_ASSOC);

      if ($user && password_verify(password: $password, hash: $user['password'])) {
        // Password is correct, set up the session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        header(header: "Location: dashboard.php"); // Redirect to dashboard after login
        exit;
      } else {
        // Invalid email or password
        $_SESSION['error'] = "Invalid email or password.";
      }
    } catch (PDOException $e) {
      // Log the error and display a generic error message
      error_log(message: "Database error: " . $e->getMessage());
      $_SESSION['error'] = "An error occurred. Please try again later.";
    }
  }
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <link rel='stylesheet' href='https://use.fontawesome.com/releases/v5.2.0/css/all.css'>
  <link rel='stylesheet' href='https://use.fontawesome.com/releases/v5.2.0/css/fontawesome.css'>
  <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
  <div class="container">
    <div class="screen">
      <div class="screen__content">
        <form class="login" action="login.php" method="POST">
          <?php
          if (isset($_SESSION['error'])) {
            echo '<p class="error" style="color: red;">' . htmlspecialchars($_SESSION['error'], ENT_QUOTES) . '</p>';
            unset($_SESSION['error']); // Clear the error message after displaying
          }
          ?>
          <div class="login__field">
            <i class="login__icon fas fa-user"></i>
            <input type="email" class="login__input" name="email" placeholder="Email" required>
          </div>
          <div class="login__field">
            <i class="login__icon fas fa-lock"></i>
            <input type="password" class="login__input" name="password" placeholder="Password" required>
          </div>
          <button type="submit" class="button login__submit">
            <span class="button__text">Log In</span>
            <i class="button__icon fas fa-chevron-right"></i>
          </button>
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