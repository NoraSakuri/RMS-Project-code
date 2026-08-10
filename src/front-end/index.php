<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Restaurant Management System</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>

    <div class="login-page">

        <div class="login-left">
            <div class="brand-box">
                <div class="login-logo">
                    <img src="assets/images/logo.png" alt="RMS Logo">
                </div>

                <h1>Restaurant Management System</h1>
            </div>

            <div class="hero-text">
                <h2>Manage your restaurant operations easily.</h2>
                <p>Orders, tables, kitchen, billing, inventory and reports in one clean system.</p>
            </div>

            <div class="feature-list">
                <span>🍽 Menu</span>
                <span>🧾 Orders</span>
                <span>👨‍🍳 Kitchen</span>
                <span>💳 Billing</span>
            </div>
        </div>

        <div class="login-right">
            <div class="login-card">
                <h2>Welcome Back</h2>
                <p class="subtitle">Login to continue</p>

                <form id="loginForm">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" id="username" placeholder="Enter username" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" id="password" placeholder="Enter password" required>
                    </div>

                    <button type="submit">Login</button>

                    <p id="message"></p>

                    <div class="demo-users">
                        <small>Don't have an account? <a href="admin/register.php">Register here</a></small>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script src="assets/js/login.js"></script>

</body>

</html>