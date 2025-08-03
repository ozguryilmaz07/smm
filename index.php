<?php
session_start();

// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'social_media_dashboard');

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Veritabanı bağlantısı başarısız: " . $e->getMessage());
}

// Tabloları oluştur (sadece ilk çalıştırmada gerekli)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        fullname VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS social_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        platform ENUM('facebook', 'instagram', 'twitter') NOT NULL,
        username VARCHAR(100) NOT NULL,
        followers INT DEFAULT 0,
        following INT DEFAULT 0,
        posts INT DEFAULT 0,
        engagement_rate DECIMAL(5,2) DEFAULT 0.00,
        access_token TEXT,
        last_updated TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS statistics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        date DATE NOT NULL,
        followers INT DEFAULT 0,
        likes INT DEFAULT 0,
        comments INT DEFAULT 0,
        shares INT DEFAULT 0,
        impressions INT DEFAULT 0,
        FOREIGN KEY (account_id) REFERENCES social_accounts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

// Kullanıcı giriş fonksiyonu
function loginUser($username, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        return true;
    }
    return false;
}

// Kullanıcı kayıt fonksiyonu
function registerUser($username, $password, $fullname) {
    global $pdo;
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname) VALUES (?, ?, ?)");
    return $stmt->execute([$username, $hashedPassword, $fullname]);
}

// Sosyal medya hesabı ekleme
function addSocialAccount($userId, $platform, $username, $followers, $following, $posts, $engagement) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO social_accounts 
        (user_id, platform, username, followers, following, posts, engagement_rate, last_updated) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    return $stmt->execute([$userId, $platform, $username, $followers, $following, $posts, $engagement]);
}

// Kullanıcının sosyal medya hesaplarını getir
function getUserSocialAccounts($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM social_accounts WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// İstatistik ekleme
function addStatistic($accountId, $date, $followers, $likes, $comments, $shares, $impressions) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO statistics 
        (account_id, date, followers, likes, comments, shares, impressions) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$accountId, $date, $followers, $likes, $comments, $shares, $impressions]);
}

// Hesaba ait istatistikleri getir
function getAccountStatistics($accountId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM statistics 
        WHERE account_id = ? 
        ORDER BY date DESC
    ");
    $stmt->execute([$accountId]);
    return $stmt->fetchAll();
}

// Platforma göre toplam istatistikler
function getPlatformSummary($userId, $platform) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_accounts,
            SUM(followers) as total_followers,
            AVG(engagement_rate) as avg_engagement
        FROM social_accounts 
        WHERE user_id = ? AND platform = ?
    ");
    $stmt->execute([$userId, $platform]);
    return $stmt->fetch();
}

// Kullanıcı giriş kontrolü
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Form işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                if (loginUser($_POST['username'], $_POST['password'])) {
                    header("Location: index.php");
                    exit;
                } else {
                    $loginError = "Geçersiz kullanıcı adı veya şifre!";
                }
                break;
                
            case 'register':
                if (registerUser($_POST['username'], $_POST['password'], $_POST['fullname'])) {
                    $registerSuccess = "Kayıt başarılı! Giriş yapabilirsiniz.";
                } else {
                    $registerError = "Kayıt sırasında bir hata oluştu!";
                }
                break;
                
            case 'add_account':
                if (isLoggedIn()) {
                    addSocialAccount(
                        $_SESSION['user_id'],
                        $_POST['platform'],
                        $_POST['account_username'],
                        $_POST['followers'],
                        $_POST['following'],
                        $_POST['posts'],
                        $_POST['engagement']
                    );
                    header("Location: index.php");
                    exit;
                }
                break;
                
            case 'add_statistic':
                if (isLoggedIn() && isset($_POST['account_id'])) {
                    addStatistic(
                        $_POST['account_id'],
                        $_POST['date'],
                        $_POST['followers'],
                        $_POST['likes'],
                        $_POST['comments'],
                        $_POST['shares'],
                        $_POST['impressions']
                    );
                    header("Location: statistics.php?account_id=" . $_POST['account_id']);
                    exit;
                }
                break;
        }
    }
}

// Çıkış işlemi
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sosyal Medya Yönetim Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --facebook: #3b5998;
            --instagram: #e1306c;
            --twitter: #1da1f2;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(135deg, #2c3e50, #4a6491);
            color: white;
            height: 100vh;
            position: fixed;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 5px;
            margin: 5px 0;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
            border: none;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        
        .card-facebook {
            border-top: 4px solid var(--facebook);
        }
        
        .card-instagram {
            border-top: 4px solid var(--instagram);
        }
        
        .card-twitter {
            border-top: 4px solid var(--twitter);
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .stat-card h5 {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
        }
        
        .platform-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .facebook-icon {
            color: var(--facebook);
        }
        
        .instagram-icon {
            color: var(--instagram);
        }
        
        .twitter-icon {
            color: var(--twitter);
        }
        
        .account-item {
            border-left: 4px solid;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .facebook-item {
            border-left-color: var(--facebook);
        }
        
        .instagram-item {
            border-left-color: var(--instagram);
        }
        
        .twitter-item {
            border-left-color: var(--twitter);
        }
        
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
            font-weight: bold;
            font-size: 2rem;
        }
        
        .logo i {
            margin-right: 10px;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .form-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <?php if (!isLoggedIn()): ?>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-chart-network"></i> SocialMetrics
        </div>
        
        <?php if (isset($loginError)): ?>
        <div class="alert alert-danger"><?php echo $loginError; ?></div>
        <?php endif; ?>
        
        <?php if (isset($registerSuccess)): ?>
        <div class="alert alert-success"><?php echo $registerSuccess; ?></div>
        <?php endif; ?>
        
        <ul class="nav nav-tabs mb-4" id="authTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">Giriş Yap</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">Kayıt Ol</button>
            </li>
        </ul>
        
        <div class="tab-content" id="authTabsContent">
            <div class="tab-pane fade show active" id="login" role="tabpanel">
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label for="loginUsername" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="loginUsername" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="loginPassword" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="loginPassword" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
                </form>
            </div>
            
            <div class="tab-pane fade" id="register" role="tabpanel">
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="mb-3">
                        <label for="registerFullname" class="form-label">Tam Adınız</label>
                        <input type="text" class="form-control" id="registerFullname" name="fullname" required>
                    </div>
                    <div class="mb-3">
                        <label for="registerUsername" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="registerUsername" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="registerPassword" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="registerPassword" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Kayıt Ol</button>
                </form>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="sidebar">
        <div class="p-4">
            <h4 class="text-center mb-4">SocialMetrics</h4>
            <div class="text-center mb-4">
                <div class="bg-light rounded-circle d-inline-block p-3">
                    <i class="fas fa-user fa-2x text-dark"></i>
                </div>
                <div class="mt-2"><?php echo $_SESSION['fullname']; ?></div>
                <small class="text-muted">@<?php echo $_SESSION['username']; ?></small>
            </div>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="index.php"><i class="fas fa-home"></i> Gösterge Paneli</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="accounts.php"><i class="fas fa-users"></i> Hesaplar</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="statistics.php"><i class="fas fa-chart-bar"></i> İstatistikler</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php"><i class="fas fa-file-alt"></i> Raporlar</a>
            </li>
            <li class="nav-item mt-4">
                <a class="nav-link" href="?logout"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
            </li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>Gösterge Paneli</h3>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                <i class="fas fa-plus"></i> Yeni Hesap Ekle
            </button>
        </div>
        
        <!-- Platform Özetleri -->
        <div class="row">
            <div class="col-md-4">
                <div class="dashboard-card card-facebook">
                    <div class="card-body">
                        <div class="platform-icon facebook-icon">
                            <i class="fab fa-facebook"></i>
                        </div>
                        <h5 class="card-title">Facebook</h5>
                        <?php 
                        $facebookSummary = getPlatformSummary($_SESSION['user_id'], 'facebook');
                        if ($facebookSummary) {
                            echo "<p class='mb-1'>Toplam Hesaplar: <strong>{$facebookSummary['total_accounts']}</strong></p>";
                            echo "<p class='mb-1'>Toplam Takipçi: <strong>" . number_format($facebookSummary['total_followers']) . "</strong></p>";
                            echo "<p>Ort. Etkileşim: <strong>{$facebookSummary['avg_engagement']}%</strong></p>";
                        } else {
                            echo "<p>Henüz Facebook hesabı eklenmemiş</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="dashboard-card card-instagram">
                    <div class="card-body">
                        <div class="platform-icon instagram-icon">
                            <i class="fab fa-instagram"></i>
                        </div>
                        <h5 class="card-title">Instagram</h5>
                        <?php 
                        $instagramSummary = getPlatformSummary($_SESSION['user_id'], 'instagram');
                        if ($instagramSummary) {
                            echo "<p class='mb-1'>Toplam Hesaplar: <strong>{$instagramSummary['total_accounts']}</strong></p>";
                            echo "<p class='mb-1'>Toplam Takipçi: <strong>" . number_format($instagramSummary['total_followers']) . "</strong></p>";
                            echo "<p>Ort. Etkileşim: <strong>{$instagramSummary['avg_engagement']}%</strong></p>";
                        } else {
                            echo "<p>Henüz Instagram hesabı eklenmemiş</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="dashboard-card card-twitter">
                    <div class="card-body">
                        <div class="platform-icon twitter-icon">
                            <i class="fab fa-twitter"></i>
                        </div>
                        <h5 class="card-title">Twitter</h5>
                        <?php 
                        $twitterSummary = getPlatformSummary($_SESSION['user_id'], 'twitter');
                        if ($twitterSummary) {
                            echo "<p class='mb-1'>Toplam Hesaplar: <strong>{$twitterSummary['total_accounts']}</strong></p>";
                            echo "<p class='mb-1'>Toplam Takipçi: <strong>" . number_format($twitterSummary['total_followers']) . "</strong></p>";
                            echo "<p>Ort. Etkileşim: <strong>{$twitterSummary['avg_engagement']}%</strong></p>";
                        } else {
                            echo "<p>Henüz Twitter hesabı eklenmemiş</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Hesaplar Listesi -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Sosyal Medya Hesaplarınız</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $accounts = getUserSocialAccounts($_SESSION['user_id']);
                        if ($accounts): 
                            foreach ($accounts as $account): 
                                $platformClass = $account['platform'] . '-item';
                        ?>
                        <div class="account-item <?php echo $platformClass; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5>
                                        <i class="fab fa-<?php echo $account['platform']; ?>"></i>
                                        <?php echo ucfirst($account['platform']); ?>: <?php echo $account['username']; ?>
                                    </h5>
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <small>Takipçi</small>
                                            <div class="value"><?php echo number_format($account['followers']); ?></div>
                                        </div>
                                        <div class="me-3">
                                            <small>Takip</small>
                                            <div class="value"><?php echo number_format($account['following']); ?></div>
                                        </div>
                                        <div class="me-3">
                                            <small>Gönderi</small>
                                            <div class="value"><?php echo $account['posts']; ?></div>
                                        </div>
                                        <div>
                                            <small>Etkileşim</small>
                                            <div class="value"><?php echo $account['engagement_rate']; ?>%</div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <a href="statistics.php?account_id=<?php echo $account['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-chart-line"></i> İstatistikler
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Henüz hiç sosyal medya hesabı eklemediniz.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                                Hesap Ekle
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Takipçi Büyüme Grafiği -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="chart-container">
                    <h5 class="mb-4">Takipçi Büyüme Eğrisi (Son 7 Gün)</h5>
                    <canvas id="followerGrowthChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Yeni Hesap Ekle Modal -->
    <div class="modal fade" id="addAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Sosyal Medya Hesabı Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_account">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Platform</label>
                            <select class="form-select" name="platform" required>
                                <option value="">Seçiniz</option>
                                <option value="facebook">Facebook</option>
                                <option value="instagram">Instagram</option>
                                <option value="twitter">Twitter</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hesap Kullanıcı Adı</label>
                            <input type="text" class="form-control" name="account_username" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Takipçi Sayısı</label>
                                <input type="number" class="form-control" name="followers" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Takip Edilen Sayısı</label>
                                <input type="number" class="form-control" name="following" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gönderi Sayısı</label>
                                <input type="number" class="form-control" name="posts" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Etkileşim Oranı (%)</label>
                                <input type="number" step="0.01" class="form-control" name="engagement" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Hesap Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (isLoggedIn()): ?>
    <script>
        // Örnek takipçi büyüme grafiği
        const ctx = document.getElementById('followerGrowthChart').getContext('2d');
        const followerGrowthChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['6 Gün Önce', '5 Gün Önce', '4 Gün Önce', '3 Gün Önce', '2 Gün Önce', 'Dün', 'Bugün'],
                datasets: [
                    {
                        label: 'Facebook',
                        data: [12000, 12150, 12200, 12350, 12500, 12700, 13000],
                        borderColor: '#3b5998',
                        backgroundColor: 'rgba(59, 89, 152, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Instagram',
                        data: [8500, 8700, 8900, 9200, 9500, 9800, 10200],
                        borderColor: '#e1306c',
                        backgroundColor: 'rgba(225, 48, 108, 0.1)',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Twitter',
                        data: [5600, 5700, 5800, 5900, 6000, 6100, 6300],
                        borderColor: '#1da1f2',
                        backgroundColor: 'rgba(29, 161, 242, 0.1)',
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
