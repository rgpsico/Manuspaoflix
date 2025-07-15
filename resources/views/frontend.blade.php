<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frontend Demo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <h1>Frontend Demo - Auth Routes</h1>

    <section>
        <h2>Login</h2>
        <form id="login-form">
            <label>Email <input type="email" name="email" required></label><br>
            <label>Password <input type="password" name="password" required></label><br>
            <button type="submit">Login</button>
        </form>
    </section>

    <section>
        <h2>Token</h2>
        <pre id="token-display"></pre>
    </section>

    <section>
        <button id="btn-me">Load Me</button>
    </section>

    <section>
        <h2>Update Profile</h2>
        <form id="profile-form">
            <label>Name <input type="text" name="name" required></label><br>
            <label>Email <input type="email" name="email" required></label><br>
            <label>Phone <input type="text" name="phone"></label><br>
            <button type="submit">Save</button>
        </form>
    </section>

    <section>
        <h2>Change Password</h2>
        <form id="password-form">
            <label>Current Password <input type="password" name="current_password" required></label><br>
            <label>New Password <input type="password" name="password" required></label><br>
            <label>Confirm Password <input type="password" name="password_confirmation" required></label><br>
            <button type="submit">Change</button>
        </form>
    </section>

    <section>
        <button id="logout-btn">Logout</button>
    </section>

    <pre id="output"></pre>
</body>
</html>
