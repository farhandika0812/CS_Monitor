<?php
// login.php
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && md5($password) == $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Log activity
            logActivity($user['username'], 'Login berhasil', 'Login');
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PanglimaNet Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 380px;
        }
        
        .login-card {
            background: white;
            border-radius: 8px;
            padding: 28px 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .logo {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }
        
        .logo i {
            font-size: 20px;
            color: white;
        }
        
        .login-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
        }
        
        .login-header p {
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }
        
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-group i.input-icon {
            position: absolute;
            left: 12px;
            color: #999;
            font-size: 14px;
            pointer-events: none;
            z-index: 1;
        }
        
        .input-group input {
            width: 100%;
            padding: 10px 40px 10px 36px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: white;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }
        
        /* Password Toggle - di kanan form */
        .password-toggle {
            position: absolute;
            right: 12px;
            cursor: pointer;
            color: #999;
            font-size: 14px;
            z-index: 2;
            background: white;
            padding: 0;
            width: auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .password-toggle:hover {
            color: #667eea;
        }
        
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 11px;
        }
        
        .checkbox {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            color: #666;
        }
        
        .checkbox input {
            width: 14px;
            height: 14px;
            cursor: pointer;
            margin: 0;
        }
        
        .forgot-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 11px;
            transition: color 0.2s;
        }
        
        .forgot-link:hover {
            color: #764ba2;
        }
        
        .login-btn {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .login-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #eee;
        }
        
        .login-footer p {
            font-size: 10px;
            color: #999;
            font-weight: 500;
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 24px 20px;
            }
            
            .login-header h2 {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-network-wired"></i>
                </div>
                <h2>PanglimaNet</h2>
                <p>Admin Control Panel</p>
            </div>
            
            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-group">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="username" id="username" placeholder="Masukkan username" required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" id="password" placeholder="Masukkan password" required>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
                    </div>
                </div>
                
                <div class="login-options">
                    <label class="checkbox">
                        <input type="checkbox" id="rememberMe">
                        <span>Ingat saya</span>
                    </label>
                    <a href="#" class="forgot-link" id="forgotPassword">Lupa password?</a>
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
            </form>
            
            <div class="login-footer">
                <p>© 2024 PanglimaNet</p>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Remember me functionality
        const rememberCheckbox = document.getElementById('rememberMe');
        const usernameInput = document.getElementById('username');
        
        const rememberedUsername = localStorage.getItem('rememberUsername');
        if (rememberedUsername) {
            usernameInput.value = rememberedUsername;
            rememberCheckbox.checked = true;
        }
        
        rememberCheckbox.addEventListener('change', function(e) {
            if (e.target.checked) {
                localStorage.setItem('rememberUsername', usernameInput.value);
            } else {
                localStorage.removeItem('rememberUsername');
            }
        });
        
        usernameInput.addEventListener('input', function() {
            if (rememberCheckbox.checked) {
                localStorage.setItem('rememberUsername', this.value);
            }
        });
        
        <?php if ($error): ?>
        Swal.fire({
            icon: 'error',
            title: 'Login Gagal!',
            text: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#667eea',
            confirmButtonText: 'OK',
            background: '#fff',
            borderRadius: '8px'
        });
        <?php endif; ?>
        
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            const username = document.querySelector('input[name="username"]').value.trim();
            const password = document.querySelector('input[name="password"]').value;
            
            if (username === '' || password === '') {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Username dan password harus diisi!',
                    confirmButtonColor: '#667eea',
                    confirmButtonText: 'OK',
                    background: '#fff',
                    borderRadius: '8px',
                    timer: 2000,
                    showConfirmButton: true,
                    timerProgressBar: true
                });
            }
        });
        
        document.getElementById('forgotPassword')?.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                icon: 'info',
                title: 'Lupa Password?',
                html: 'Silakan hubungi administrator sistem untuk mereset password Anda.',
                confirmButtonColor: '#667eea',
                confirmButtonText: 'OK',
                background: '#fff',
                borderRadius: '8px'
            });
        });
    </script>
</body>
</html>