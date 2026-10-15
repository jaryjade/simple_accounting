<?php
session_start();
require_once "connectdb.php";

$error = "";
$success = "";

$username = "";
$full_name = "";
$email = "";

// Process registration
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // Validate required fields
    if (
        $username === "" ||
        $full_name === "" ||
        $email === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {
        $error = "Please fill in all fields.";

    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $error = "Username must be 3-50 characters and contain only letters, numbers, and underscores.";

    } elseif (strlen($full_name) > 100) {
        $error = "Full name must not exceed 100 characters.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";

    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";

    } else {
        try {
            // Check whether username or email already exists
            $check = $pdo->prepare(
                "SELECT id FROM users
                 WHERE username = :username OR email = :email
                 LIMIT 1"
            );

            $check->execute([
                "username" => $username,
                "email" => $email
            ]);

            if ($check->fetch()) {
                $error = "Username or email already exists.";

            } else {
                // Hash the password before saving
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Create the new user with the default user role
                $insert = $pdo->prepare(
                    "INSERT INTO users
                    (username, full_name, email, password, role, status)
                    VALUES
                    (:username, :full_name, :email, :password, 'user', 'active')"
                );

                $insert->execute([
                    "username" => $username,
                    "full_name" => $full_name,
                    "email" => $email,
                    "password" => $hashed_password
                ]);

                $success = "Registration successful! You can now log in.";

                // Clear form fields after successful registration
                $username = "";
                $full_name = "";
                $email = "";
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Registration failed. Please try again.";
        }
    }
}

function escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Accounting System</title>

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
            align-items: center;
            justify-content: center;
            padding: 25px 15px;
            background: #f1f5f9;
        }

        .register-box {
            width: 100%;
            max-width: 460px;
            background: #fff;
            padding: 32px;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        h1 {
            text-align: center;
            color: #0f172a;
            font-size: 27px;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
            color: #334155;
        }

        input {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 8px;
            font-size: 14px;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .login-link {
            text-align: center;
            margin-top: 22px;
            color: #64748b;
            font-size: 14px;
        }

        .login-link a {
            color: #2563eb;
            font-weight: bold;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="register-box">

    <h1>Create Account</h1>
    <p class="subtitle">Register for the Accounting System</p>

    <?php if ($error !== ""): ?>
        <div class="message error">
            <?= escape($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="message success">
            <?= escape($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">

        <div class="form-group">
            <label for="full_name">Full Name</label>
            <input
                type="text"
                id="full_name"
                name="full_name"
                maxlength="100"
                value="<?= escape($full_name) ?>"
                placeholder="Enter your full name"
                autocomplete="name"
                required
            >
        </div>

        <div class="form-group">
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                minlength="3"
                maxlength="50"
                pattern="[a-zA-Z0-9_]+"
                value="<?= escape($username) ?>"
                placeholder="Choose a username"
                autocomplete="username"
                required
            >
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                maxlength="150"
                value="<?= escape($email) ?>"
                placeholder="Enter your email"
                autocomplete="email"
                required
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                placeholder="At least 8 characters"
                autocomplete="new-password"
                required
            >
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                placeholder="Enter your password again"
                autocomplete="new-password"
                required
            >
        </div>

        <button type="submit">Create Account</button>

    </form>

    <div class="login-link">
        Already have an account?
        <a href="index.php">Log In</a>
    </div>

</div>

</body>
</html>
