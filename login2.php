<?php
session_start();
require_once 'db.php';
require_once 'src/language.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $user['role'];

            // إنشاء إشعار تسجيل الدخول
            $notification_message = "قام المستخدم '{$username}' بتسجيل الدخول إلى النظام";
            $notification_type = "user_login";
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications (message, type, status) VALUES (?, ?, 'unread')");
            $notif_stmt->bind_param("ss", $notification_message, $notification_type);
            $notif_stmt->execute();
            $notif_stmt->close();

            $stmt->close();
            header("location: reports.php");
            exit;
        } else {
            $login_err = __('invalid_password');
        }
    } else {
        $login_err = __('invalid_credentials');
    }

    $stmt->close();
}

// Get shop favicon
$shopFavicon = '';
if (method_exists($conn, 'query')) {
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'shopFavicon'");
    $shopFavicon = ($result && isset($result->num_rows) && $result->num_rows > 0) ? $result->fetch_assoc()['setting_value'] : '';
}
?>
<!DOCTYPE html>
<html lang="<?php echo get_locale(); ?>" dir="<?php echo get_dir(); ?>" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($shopFavicon)): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($shopFavicon); ?>">
    <?php endif; ?>
    <title><?php echo __('login_title'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            DEFAULT: '#0E1116',
                            surface: '#1F2937',
                            glass: 'rgba(14, 17, 22, 0.7)',
                        },
                        primary: {
                            DEFAULT: '#3B82F6',
                            hover: '#2563EB',
                        },
                        accent: {
                            DEFAULT: '#84CC16',
                        }
                    },
                    fontFamily: {
                        sans: ['Tajawal', 'sans-serif'],
                    },
                },
            },
        }
    </script>
    <style>
        .glass-panel {
            background-color: rgba(31, 41, 55, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
</head>

<body class="bg-dark text-white font-sans min-h-screen flex flex-col md:flex-row relative">

<!-- نظام الرسائل الموحد -->
<div id="toast-notification" class="fixed top-5 left-1/2 transform -translate-x-1/2 z-[9999] transition-all duration-300 ease-out opacity-0 -translate-y-10 pointer-events-none w-[90%] max-w-md md:w-auto">
    <div id="toast-content" class="flex items-center gap-3 px-4 py-3 md:px-6 md:py-4 rounded-xl shadow-2xl backdrop-blur-md">
        <span id="toast-icon" class="material-icons-round text-xl md:text-2xl"></span>
        <span id="toast-message" class="font-bold text-base md:text-lg"></span>
    </div>
</div>

<script>
    function togglePassword(inputId, iconId) {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(iconId);
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.textContent = 'visibility_off';
        } else {
            passwordInput.type = 'password';
            toggleIcon.textContent = 'visibility';
        }
    }
</script>

<script>
    window.translations = <?php echo json_encode($translations); ?>;
    window.__ = function(key) {
        return window.translations[key] || key;
    };

    function showToast(message, isSuccess) {
        // Smart detection for error messages if isSuccess is not explicitly provided
        if (typeof isSuccess === 'undefined') {
            const lowerMsg = String(message).toLowerCase();
            const errorKeywords = [
                'error', 'fail', 'wrong', 'denied', 'unauthorized', // English
                'خطأ', 'فشل', 'مشكلة', 'تنبيه', 'عذراً', 'غير مصرح', 'مرفوض', 'تعذر', 'نفذت' // Arabic
            ];
            // Default to true (success), but switch to false (error) if keyword found
            isSuccess = !errorKeywords.some(keyword => lowerMsg.includes(keyword));
        }

        const toast = document.getElementById('toast-notification');
        const toastContent = document.getElementById('toast-content');
        const toastMessage = document.getElementById('toast-message');
        const toastIcon = document.getElementById('toast-icon');

        toastMessage.textContent = message;
        
        if (isSuccess) {
            toastContent.className = 'flex items-center gap-3 px-6 py-4 rounded-xl shadow-2xl backdrop-blur-md bg-emerald-600 text-white border border-emerald-400/30';
            toastIcon.textContent = 'check_circle';
        } else {
            toastContent.className = 'flex items-center gap-3 px-6 py-4 rounded-xl shadow-2xl backdrop-blur-md bg-rose-600 text-white border border-rose-400/30';
            toastIcon.textContent = 'error';
        }

        toast.classList.remove('opacity-0', '-translate-y-10', 'pointer-events-none');
        toast.classList.add('opacity-100', 'translate-y-0', 'pointer-events-auto');

        setTimeout(() => {
            toast.classList.remove('opacity-100', 'translate-y-0');
            toast.classList.add('opacity-0', '-translate-y-10');
            setTimeout(() => {
                toast.classList.add('pointer-events-none');
            }, 300);
        }, 3000);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('success')) {
            const successMsg = urlParams.get('success');
            showToast(successMsg, true);
            
            urlParams.delete('success');
            const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
            window.history.replaceState({}, '', newUrl);
        }
    });
</script>

    <!-- Image Section (Right Side in RTL, rendered first in flex container) -->
    <div class="hidden md:flex w-full md:w-1/2 relative">
        <div class="absolute inset-0 z-10 bg-gradient-to-t from-dark via-dark/40 to-transparent"></div>
        <div class="absolute inset-0 z-10 bg-dark/30 mix-blend-multiply"></div>
        <!-- High quality professional retail/business image -->
        <img src="src/img/login.png" 
             alt="Professional Business Image" 
             class="absolute inset-0 w-full h-full object-cover">
    </div>

    <!-- Form Section (Left Side in RTL) -->
    <div class="w-full md:w-1/2 flex items-center justify-center relative p-4 md:p-8 overflow-hidden bg-dark">
        
        <!-- Modern Animated Orbs Background for the Form Side -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-primary/20 rounded-full mix-blend-screen filter blur-[100px] animate-pulse pointer-events-none z-0"></div>
        <div class="absolute bottom-0 left-0 w-96 h-96 bg-blue-600/20 rounded-full mix-blend-screen filter blur-[100px] animate-pulse pointer-events-none z-0" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-accent/10 rounded-full mix-blend-screen filter blur-[80px] pointer-events-none z-0"></div>

        <!-- Form Panel -->
        <div class="w-full max-w-md bg-dark-surface/70 backdrop-blur-2xl border border-white/10 rounded-[2rem] shadow-[0_8px_32px_0_rgba(0,0,0,0.3)] p-8 md:p-10 relative z-10 mx-4 md:mx-0">

        <div class="text-center mb-10 relative">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-primary to-blue-400 mb-6 shadow-lg shadow-primary/30">
                <span class="material-icons-round text-3xl text-white">storefront</span>
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-400 mb-3"><?php echo __('welcome_back'); ?></h1>
            <p class="text-sm md:text-base text-gray-400 font-medium"><?php echo __('login_subtitle'); ?></p>
        </div>

        <div class="mb-8 bg-gradient-to-r from-yellow-500/10 to-orange-500/10 border border-yellow-500/30 rounded-2xl p-4 text-center backdrop-blur-sm">
            <p class="text-yellow-400 font-bold mb-3 text-sm flex items-center justify-center gap-2">
                <span class="material-icons-round text-sm">info</span>
                <?php echo (get_locale() == 'ar') ? 'بيانات الدخول للتجربة' : 'Demo Credentials'; ?>
            </p>
            <div dir="ltr" class="flex justify-center gap-4 text-sm bg-black/40 py-2.5 rounded-xl border border-white/5 shadow-inner">
                <span class="text-gray-400">User: <strong class="text-white select-all font-mono ml-1">admin</strong></span>
                <span class="w-px bg-white/10"></span>
                <span class="text-gray-400">Pass: <strong class="text-white select-all font-mono ml-1">123456</strong></span>
            </div>
        </div>

        <form action="login.php" method="POST" class="space-y-6">
            <?php 
            if(!empty($login_err)){
                echo '<script>setTimeout(() => showToast("' . addslashes($login_err) . '", false), 100);</script>';
            }        
            ?>
            
            <div class="space-y-5">
                <!-- Username Input -->
                <div class="relative group">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                        <span class="material-icons-round text-gray-400 group-focus-within:text-primary transition-colors">person</span>
                    </div>
                    <input type="text" id="username" name="username"
                        class="w-full bg-black/40 border border-white/10 text-white text-start placeholder-transparent rounded-2xl px-4 py-4 pr-12 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary focus:bg-black/60 transition-all duration-300 peer"
                        placeholder=" " required>
                    <label for="username" 
                        class="absolute text-sm text-gray-400 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] right-12 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-4 peer-focus:scale-75 peer-focus:-translate-y-4 peer-focus:text-primary">
                        <?php echo __('username'); ?>
                    </label>
                </div>

                <!-- Password Input -->
                <div class="relative group">
                    <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                        <span class="material-icons-round text-gray-400 group-focus-within:text-primary transition-colors">lock</span>
                    </div>
                    <input type="password" id="password" name="password"
                        class="w-full bg-black/40 border border-white/10 text-white text-start placeholder-transparent rounded-2xl px-4 py-4 pr-12 pl-12 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary focus:bg-black/60 transition-all duration-300 peer"
                        placeholder=" " required>
                    <label for="password" 
                        class="absolute text-sm text-gray-400 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] right-12 peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-4 peer-focus:scale-75 peer-focus:-translate-y-4 peer-focus:text-primary">
                        <?php echo __('password'); ?>
                    </label>
                    <button type="button" onclick="togglePassword('password', 'togglePasswordIcon')" 
                        class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-white transition-colors focus:outline-none p-1 rounded-lg hover:bg-white/10">
                        <span id="togglePasswordIcon" class="material-icons-round text-xl">visibility</span>
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <label class="flex items-center gap-3 cursor-pointer group">
                    <div class="relative flex items-center">
                        <input id="remember-me" name="remember-me" type="checkbox"
                            class="peer sr-only">
                        <div class="w-5 h-5 border-2 border-gray-500 rounded bg-black/40 peer-checked:bg-primary peer-checked:border-primary transition-all flex items-center justify-center">
                            <span class="material-icons-round text-white text-sm opacity-0 peer-checked:opacity-100 transform scale-50 peer-checked:scale-100 transition-all duration-200">check</span>
                        </div>
                    </div>
                    <span class="text-sm font-medium text-gray-400 group-hover:text-gray-300 transition-colors select-none"><?php echo __('remember_me'); ?></span>
                </label>
                
                <a href="password_reset.php" class="text-sm font-semibold text-primary hover:text-blue-400 transition-colors relative after:absolute after:bottom-0 after:right-0 after:w-0 after:h-px after:bg-blue-400 hover:after:w-full after:transition-all after:duration-300">
                    <?php echo __('forgot_password'); ?>
                </a>
            </div>

            <button type="submit"
                class="w-full relative group overflow-hidden py-4 px-6 rounded-2xl bg-gradient-to-r from-primary to-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-dark focus:ring-primary transition-all duration-300 transform hover:-translate-y-1 hover:shadow-[0_10px_40px_-10px_rgba(59,130,246,0.7)]">
                <div class="absolute inset-0 w-full h-full bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:animate-[shimmer_1.5s_infinite]"></div>
                <div class="relative flex items-center justify-center gap-2">
                    <span class="text-base font-bold text-white tracking-wide"><?php echo __('login_btn'); ?></span>
                    <span class="material-icons-round text-white text-sm group-hover:translate-x-1 transition-transform rtl:group-hover:-translate-x-1">arrow_forward</span>
                </div>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-white/10 text-center">
            <p class="text-xs font-medium text-gray-500 mb-4"><?php echo __('system_version'); ?></p>
            <!-- Language Switcher -->
            <div class="flex items-center justify-center gap-6">
                <?php
                $currentParams = $_GET;
                $currentParams['lang'] = 'ar';
                $arLink = '?' . http_build_query($currentParams);
                $currentParams['lang'] = 'fr';
                $frLink = '?' . http_build_query($currentParams);
                ?>
               <a href="<?php echo htmlspecialchars($arLink); ?>" class="flex items-center gap-2 px-3 py-1.5 rounded-lg transition-colors <?php echo get_locale() === 'ar' ? 'bg-primary/20 text-primary' : 'text-gray-500 hover:bg-white/5 hover:text-gray-300'; ?>">
                   <span class="text-sm font-bold"><?php echo __('arabic'); ?></span>
               </a>
               <a href="<?php echo htmlspecialchars($frLink); ?>" class="flex items-center gap-2 px-3 py-1.5 rounded-lg transition-colors <?php echo get_locale() === 'fr' ? 'bg-primary/20 text-primary' : 'text-gray-500 hover:bg-white/5 hover:text-gray-300'; ?>">
                   <span class="text-sm font-bold"><?php echo __('french'); ?></span>
               </a>
            </div>
        </div>
        
        </div> <!-- End Form Panel -->
    </div> <!-- End Form Section -->

    <style>
        @keyframes shimmer {
            100% {
                transform: translateX(100%);
            }
        }
        /* Fix for Webkit autofill to maintain dark background */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active{
            -webkit-box-shadow: 0 0 0 30px #1a1d24 inset !important;
            -webkit-text-fill-color: white !important;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>

</body>

</html>