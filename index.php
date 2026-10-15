```php
<?php
session_start();

require_once "connectdb.php";

// If already logged in, go to dashboard
if (!empty($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
$login = "";

// Process login form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($login === "" || $password === "") {
        $error = "Please enter your username/email and password.";
    } else {
        try {
            // Search using username OR email
            $stmt = $pdo->prepare(
                "SELECT id, username, full_name, email, password, role, status
                 FROM users
                 WHERE username = :username OR email = :email
                 LIMIT 1"
            );

            $stmt->execute([
                "username" => $login,
                "email" => $login
            ]);

            $user = $stmt->fetch();

            if (
                $user &&
                password_verify($password, $user["password"]) &&
                $user["status"] === "active"
            ) {
                // Regenerate session ID after successful login
                session_regenerate_id(true);

                // Store user information in the session
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["useremail"] = $user["email"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid username/email or password, or your account is inactive.";
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Unable to log in. Please check your database configuration.";
        }
    }
}

function escapeOutput($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Accounting System</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: #f1f5f9;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            padding: 36px;
            border-radius: 14px;
            box-shadow: 0 10px 35px rgba(15, 23, 42, 0.09);
        }

        .logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 18px;
            border-radius: 14px;
            background: #dbeafe;
            color: #1d4ed8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 29px;
            font-weight: bold;
        }

        h1 {
            text-align: center;
            font-size: 27px;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 14px;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            background: #ffffff;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .login-button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: #ffffff;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }

        .login-button:hover {
            background: #1d4ed8;
        }

        .error {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 14px;
            line-height: 1.5;
        }

        .register-link {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: #64748b;
        }

        .register-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .footer {
            text-align: center;
            margin-top: 28px;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <div class="login-container">

        <div class="logo">A</div>

        <h1>Accounting System</h1>
        <p class="subtitle">Sign in to access your account</p>

        <?php if ($error !== ""): ?>
            <div class="error">
                <?= escapeOutput($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php">

            <div class="form-group">
                <label for="login">Username or Email</label>
                <input
                    type="text"
                    id="login"
                    name="login"
                    value="<?= escapeOutput($login) ?>"
                    placeholder="Enter your username or email"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="login-button">
                Log In
            </button>

        </form>

        <div class="register-link">
            Don't have an account?
            <a href="register.php">Create Account</a>
        </div>

        <div class="footer">
            &copy; <?= date("Y") ?> Accounting System
        </div>

    </div>

</body>
</html>
```