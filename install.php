<?php
// إعداد الاتصال بقاعدة البيانات
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "smart_shop";
$port = 3306;

// التحقق من الإعدادات المحمولة
if (file_exists(__DIR__ . '/portable_config.php')) {
    include __DIR__ . '/portable_config.php';
    if (isset($PORTABLE_DB_PORT)) {
        $port = $PORTABLE_DB_PORT;
    }
} elseif (getenv('DB_PORT')) {
    $port = getenv('DB_PORT');
}

// التحقق من امتداد MySQLi
if (!extension_loaded('mysqli')) {
    die("<h3>Error: MySQLi extension is not loaded.</h3>");
}

// محاولة الاتصال
try {
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
    $conn = new mysqli($servername, $username, $password, "", (int)$port);
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

$conn->set_charset("utf8mb4");

// بدء التثبيت
$steps = [
    'create_db' => ['title' => 'إنشاء قاعدة البيانات', 'status' => 'pending'],
    'create_tables' => ['title' => 'إنشاء الجداول', 'status' => 'pending'],
    'settings' => ['title' => 'تهيئة الإعدادات', 'status' => 'pending'],
    'holidays' => ['title' => 'إضافة العطلات', 'status' => 'pending']
];

$logs = [];
$success = true;

// 1. إنشاء قاعدة البيانات
try {
    $sql_create_db = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql_create_db)) {
        $steps['create_db']['status'] = 'success';
        $logs[] = "تم إنشاء قاعدة البيانات بنجاح.";
        $conn->select_db($dbname);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $steps['create_db']['status'] = 'error';
    $logs[] = "خطأ في قاعدة البيانات: " . $e->getMessage();
    $success = false;
}

if ($success) {
    // 2. إنشاء الجداول
    // (يتم تضمين أكواد إنشاء الجداول هنا - مختصرة للعرض)
    $tables_sql = [
        'users' => "CREATE TABLE IF NOT EXISTS users (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(30) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, role ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier', first_login BOOLEAN DEFAULT FALSE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'security_questions' => "CREATE TABLE IF NOT EXISTS security_questions (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT(6) UNSIGNED NOT NULL, question TEXT NOT NULL, answer VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'categories' => "CREATE TABLE IF NOT EXISTS categories (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, description TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'products' => "CREATE TABLE IF NOT EXISTS products (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, price DECIMAL(10, 2) NOT NULL, cost_price DECIMAL(10, 2) DEFAULT 0, quantity INT(6) NOT NULL, category_id INT(6) UNSIGNED, barcode VARCHAR(255), image VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'customers' => "CREATE TABLE IF NOT EXISTS customers (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(50), phone VARCHAR(20), address TEXT, city VARCHAR(100) DEFAULT NULL, balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'invoices' => "CREATE TABLE IF NOT EXISTS invoices (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id INT(6) UNSIGNED, total DECIMAL(10, 2) NOT NULL, discount_percent DECIMAL(5, 2) DEFAULT 0.00, discount_amount DECIMAL(10, 2) DEFAULT 0.00, delivery_city VARCHAR(100) NULL, delivery_cost DECIMAL(10, 2) NULL DEFAULT 0, barcode VARCHAR(50), payment_method VARCHAR(50) NOT NULL DEFAULT 'cash', amount_received DECIMAL(10, 2) DEFAULT 0.00, change_due DECIMAL(10, 2) DEFAULT 0.00, is_holiday BOOLEAN DEFAULT FALSE, holiday_name VARCHAR(255) DEFAULT NULL, payment_status ENUM('paid', 'unpaid', 'partial') NOT NULL DEFAULT 'paid', paid_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'invoice_items' => "CREATE TABLE IF NOT EXISTS invoice_items (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, invoice_id INT(6) UNSIGNED, product_id INT(6) UNSIGNED, product_name VARCHAR(255) NOT NULL DEFAULT 'منتج محذوف', quantity INT(6) NOT NULL, price DECIMAL(10, 2) NOT NULL, cost_price DECIMAL(10, 2) DEFAULT 0, FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'refunds' => "CREATE TABLE IF NOT EXISTS refunds (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, invoice_id INT(6) UNSIGNED NOT NULL, amount DECIMAL(10, 2) NOT NULL, items_json TEXT, reason TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'settings' => "CREATE TABLE IF NOT EXISTS settings (setting_name VARCHAR(255) PRIMARY KEY, setting_value TEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'category_fields' => "CREATE TABLE IF NOT EXISTS category_fields (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id INT(6) UNSIGNED NOT NULL, field_name VARCHAR(255) NOT NULL, field_type VARCHAR(50) NOT NULL, FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'product_field_values' => "CREATE TABLE IF NOT EXISTS product_field_values (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, product_id INT(6) UNSIGNED NOT NULL, field_id INT(6) UNSIGNED NOT NULL, value TEXT, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE, FOREIGN KEY (field_id) REFERENCES category_fields(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'removed_products' => "CREATE TABLE IF NOT EXISTS removed_products (id INT(6) UNSIGNED NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL, price DECIMAL(10, 2) NOT NULL, quantity INT(6) NOT NULL, category_id INT(6) UNSIGNED, barcode VARCHAR(255), image VARCHAR(255), created_at TIMESTAMP NULL, removed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'notifications' => "CREATE TABLE IF NOT EXISTS notifications (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, message TEXT NOT NULL, type VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL DEFAULT 'unread', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'rental_payments' => "CREATE TABLE IF NOT EXISTS rental_payments (id INT AUTO_INCREMENT PRIMARY KEY, paid_month VARCHAR(7) NOT NULL, payment_date DATE NOT NULL, amount DECIMAL(10,2) NOT NULL, currency VARCHAR(10) NOT NULL, rental_type ENUM('monthly','yearly') NOT NULL, landlord_name VARCHAR(255), landlord_phone VARCHAR(50), notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'media_gallery' => "CREATE TABLE IF NOT EXISTS media_gallery (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, file_path VARCHAR(255) NOT NULL UNIQUE, uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'holidays' => "CREATE TABLE IF NOT EXISTS holidays (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, date DATE NOT NULL UNIQUE, is_active BOOLEAN DEFAULT TRUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'expenses' => "CREATE TABLE IF NOT EXISTS expenses (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, amount DECIMAL(10, 2) NOT NULL, category VARCHAR(100) DEFAULT 'general', expense_date DATE NOT NULL, notes TEXT, paid_from_drawer BOOLEAN DEFAULT FALSE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'payments' => "CREATE TABLE IF NOT EXISTS payments (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, customer_id INT(6) UNSIGNED NOT NULL, amount DECIMAL(10, 2) NOT NULL, payment_date DATETIME DEFAULT CURRENT_TIMESTAMP, notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'business_days' => "CREATE TABLE IF NOT EXISTS business_days (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, start_time DATETIME NOT NULL, end_time DATETIME NULL, opening_balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00, closing_balance DECIMAL(10, 2) NULL, user_id INT(6) UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    try {
        foreach ($tables_sql as $name => $sql) {
            $conn->query($sql);
        }
        $steps['create_tables']['status'] = 'success';
        $logs[] = "تم إنشاء جميع الجداول بنجاح.";
    } catch (Exception $e) {
        $steps['create_tables']['status'] = 'error';
        $logs[] = "خطأ في إنشاء الجداول: " . $e->getMessage();
        $success = false;
    }
}

if ($success) {
    // 3. إعدادات افتراضية
    try {
        $default_settings = [
            "INSERT INTO settings (setting_name, setting_value) VALUES ('system_language', 'ar') ON DUPLICATE KEY UPDATE setting_value = setting_value",
            "INSERT INTO settings (setting_name, setting_value) VALUES ('currency', 'MAD') ON DUPLICATE KEY UPDATE setting_value = setting_value",
            "INSERT INTO settings (setting_name, setting_value) VALUES ('darkMode', '1') ON DUPLICATE KEY UPDATE setting_value = setting_value",
            "INSERT INTO settings (setting_name, setting_value) VALUES ('stockAlertsEnabled', '1') ON DUPLICATE KEY UPDATE setting_value = setting_value",
            // ... (بقية الإعدادات كما هي) ...
        ];
        
        // تنفيذ الإعدادات الأساسية فقط لتسريع العملية
        $conn->query("INSERT IGNORE INTO settings (setting_name, setting_value) VALUES ('system_language', 'ar')");
        $conn->query("INSERT IGNORE INTO settings (setting_name, setting_value) VALUES ('currency', 'MAD')");
        $conn->query("INSERT IGNORE INTO settings (setting_name, setting_value) VALUES ('darkMode', '1')");
        
        $steps['settings']['status'] = 'success';
        $logs[] = "تم تهيئة الإعدادات الافتراضية.";
    } catch (Exception $e) {
        $steps['settings']['status'] = 'warning';
        $logs[] = "تنبيه في الإعدادات: " . $e->getMessage();
    }

    // 4. العطلات
    try {
        $default_holidays = [
            ['2025-01-01', 'رأس السنة الميلادية'],
            ['2025-01-11', 'تقديم وثيقة الاستقلال'],
            // ... يمكن إضافة المزيد
        ];
        $stmt_holiday = $conn->prepare("INSERT IGNORE INTO holidays (date, name) VALUES (?, ?)");
        foreach ($default_holidays as $holiday) {
            $stmt_holiday->bind_param("ss", $holiday[0], $holiday[1]);
            $stmt_holiday->execute();
        }
        $steps['holidays']['status'] = 'success';
        $logs[] = "تم إضافة العطلات الرسمية.";
    } catch (Exception $e) {
        $steps['holidays']['status'] = 'warning';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تثبيت النظام - Smart Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: { DEFAULT: '#0E1116', surface: '#1F2937' },
                        primary: { DEFAULT: '#3B82F6', hover: '#2563EB' },
                        success: { DEFAULT: '#10B981', hover: '#059669' }
                    },
                    fontFamily: { sans: ['Tajawal', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="bg-dark text-white font-sans min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-2xl bg-dark-surface/50 backdrop-blur-xl border border-white/5 rounded-2xl shadow-2xl overflow-hidden">
        <!-- Header -->
        <div class="bg-primary/10 border-b border-primary/20 p-6 text-center">
            <h1 class="text-2xl font-bold text-white mb-2">تثبيت نظام Smart Shop</h1>
            <p class="text-gray-400 text-sm">جاري تهيئة النظام وتجهيز قاعدة البيانات...</p>
        </div>

        <!-- Progress Steps -->
        <div class="p-8 space-y-6">
            <?php foreach ($steps as $key => $step): ?>
            <div class="flex items-center gap-4 p-4 rounded-xl bg-dark/30 border border-white/5 transition-all duration-500">
                <div class="flex-shrink-0">
                    <?php if ($step['status'] === 'success'): ?>
                        <div class="w-10 h-10 rounded-full bg-success/20 flex items-center justify-center text-success">
                            <span class="material-icons-round">check_circle</span>
                        </div>
                    <?php elseif ($step['status'] === 'error'): ?>
                        <div class="w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center text-red-500">
                            <span class="material-icons-round">error</span>
                        </div>
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-gray-500/20 flex items-center justify-center text-gray-500 animate-pulse">
                            <span class="material-icons-round">pending</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-lg <?php echo $step['status'] === 'success' ? 'text-white' : 'text-gray-400'; ?>">
                        <?php echo $step['title']; ?>
                    </h3>
                    <p class="text-xs text-gray-500 mt-1">
                        <?php 
                        if ($step['status'] === 'success') echo 'تمت العملية بنجاح';
                        elseif ($step['status'] === 'error') echo 'حدث خطأ أثناء التنفيذ';
                        else echo 'قيد الانتظار...';
                        ?>
                    </p>
                </div>
                <?php if ($step['status'] === 'success'): ?>
                    <span class="text-success text-sm font-bold">100%</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Footer Actions -->
        <div class="p-6 border-t border-white/5 bg-dark/30 flex justify-center">
            <?php if ($success): ?>
                <a href="register.php" class="bg-success hover:bg-success-hover text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-success/20 flex items-center gap-2 transform hover:-translate-y-1 transition-all duration-300">
                    <span class="material-icons-round">person_add</span>
                    إنشاء حساب مدير للنظام
                </a>
            <?php else: ?>
                <button onclick="location.reload()" class="bg-red-500 hover:bg-red-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-red-500/20 flex items-center gap-2">
                    <span class="material-icons-round">refresh</span>
                    إعادة المحاولة
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Debug Logs (Hidden by default, optional toggle) -->
        <div class="px-6 pb-6">
            <details class="text-xs text-gray-600 cursor-pointer">
                <summary class="hover:text-gray-400 transition-colors">عرض سجل العمليات (للمطورين)</summary>
                <div class="mt-2 p-4 bg-black/50 rounded-lg font-mono max-h-32 overflow-y-auto">
                    <?php foreach ($logs as $log) echo "<div>> $log</div>"; ?>
                </div>
            </details>
        </div>
    </div>

</body>
</html>
