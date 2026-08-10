<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Staff | RMS</title>

    <link rel="stylesheet" href="../assets/css/register.css">
</head>

<body>

    <div class="register-page">

        <!-- LEFT SIDE -->
        <div class="register-left">

            <div class="brand-box">

                <img src="../assets/images/logo.png"
                    alt="RMS Logo"
                    class="logo-img">

                <div class="brand-content">
                    <h1>Restaurant</h1>
                    <h1>Management System</h1>
                    <p>Modern Restaurant Management Platform</p>
                </div>

            </div>

            <div class="hero-text">

                <h1>Create Staff Account</h1>

                <p>
                    Register staff members and assign
                    role-based access for restaurant operations.
                </p>

                <div class="feature-list">
                    <span>👑 Admin</span>
                    <span>👔 Manager</span>
                    <span>🍽 Waiter</span>
                    <span>👨‍🍳 Chef</span>
                    <span>💳 Cashier</span>
                </div>

            </div>

        </div>


        <!-- RIGHT SIDE -->
        <div class="register-right">

            <div class="register-card">

                <h2>Register</h2>

                <p class="subtitle">
                    Create a new staff account
                </p>

                <form id="registerForm">

                    <div class="form-group">
                        <label for="fullname">Full Name</label>

                        <input
                            type="text"
                            id="fullname"
                            name="name"
                            placeholder="Enter full name"
                            required>
                    </div>


                    <div class="form-group">
                        <label for="username">Username</label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter username"
                            required>
                    </div>


                    <div class="form-group">
                        <label for="password">Password</label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter password"
                            required>
                    </div>


                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>

                        <input
                            type="password"
                            id="confirmPassword"
                            name="confirmPassword"
                            placeholder="Confirm password"
                            required>
                    </div>


                    <div class="form-group">

                        <label for="role">Role</label>

                        <select
                            id="role"
                            name="role"
                            required>

                            <option value="">Select Role</option>
                            <option value="MANAGER">Manager</option>
                            <option value="WAITER">Waiter</option>
                            <option value="CHEF">Chef</option>
                            <option value="CASHIER">Cashier</option>

                        </select>

                    </div>


                    <button type="submit">
                        Register
                    </button>

                    <p id="message"></p>

                    <p class="login-link">
                        Already have an account?
                        <a href="../index.php">Login</a>
                    </p>

                </form>

            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/register.js"></script>

</body>

</html>