<?php
session_start(); // Start the session at the top, only once.

include 'connectdb.php'; //db connection

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password_db);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    

    $user_email = $_POST['email'];
    $user_password = $_POST['password'];

    $stmt = $conn->prepare("SELECT ID, username, email, password FROM users WHERE email = :email");
    $stmt->bindParam(':email', $user_email, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (password_verify($user_password, $result['password'])) {
            $_SESSION['user_id'] = $result['ID']; // Use the actual ID from the database.
            $_SESSION['username'] = $result['username'];
            $_SESSION['current_user_email'] = $result['email'];

            if(isset($_REQUEST['rememberme'])){
                setcookie('emailid', urlencode($_POST['email']), time() + 20, "/"); // 20 seconds
                setcookie('pswd',$_POST['password'], time() + 20, "/"); // 20 seconds
            }

            header("Location: ../html/profile.php"); // Redirect to the PHP file, not HTML.
            exit(); // Always call exit after headers to prevent further script execution.
        } else {
            header("Location: ../html/login.html?error=invalidcredentials");
            exit();
        }
    } else {
        header("Location: ../html/login.html?error=nouser");
        exit();
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$conn = null;
?>
