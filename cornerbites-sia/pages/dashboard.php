<?php
// pages/dashboard.php
// Halaman dashboard utama yang menampilkan ringkasan keuangan dan grafik untuk pengguna biasa.

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Inisialisasi variabel dengan nilai default
$totalPenjualan = 0;
$totalPengeluaran = 0;
$estimasiLabaBersih = 0;
$produkTerjual = 0;
$stokRendahCount = 0;
$totalProduk = 0;
$totalTransaksi = 0;

$monthlySales = [];
$monthlyExpenses = [];
$monthsLabel = [];

$popularProducts = [];
$popularProductNames = [];
$popularProductQuantities = [];

// Handle AJAX request for ranking
if (isset($_GET['ajax']) && $_GET['ajax'] == 'ranking') {
    $ranking_page = isset($_GET['ranking_page']) ? max((int)$_GET['ranking_page'], 1) : 1;
    $ranking_limit = isset($_GET['ranking_limit']) ? (int)$_GET['ranking_limit'] : 10;
    $search_ranking = $_GET['search_ranking'] ?? '';
    
    $offset = ($ranking_page - 1) * $ranking_limit;
    
    try {
        $conn = $db;
        
        // Build WHERE clause for search
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($search_ranking)) {
            $whereClause .= " AND p.name LIKE :search";
            $params[':search'] = '%' . $search_ranking . '%';
        }
        
        // Count total products for pagination
        $countQuery = "SELECT COUNT(*) FROM products p " . $whereClause;
        $countStmt = $conn->prepare($countQuery);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalRankingProducts = $countStmt->fetchColumn();
        $totalRankingPages = ceil($totalRankingProducts / $ranking_limit);
        
        // Get products with HPP ranking
        $query = "SELECT p.id, p.name, p.unit, p.sale_price, p.cost_price,
                         CASE 
                             WHEN p.cost_price > 0 THEN ((p.sale_price - p.cost_price) / p.cost_price) * 100
                             ELSE 0 
                         END as profit_margin_percent,
                         (p.sale_price - p.cost_price) as profit_per_unit
                  FROM products p " . $whereClause . "
                  ORDER BY profit_margin_percent DESC, p.sale_price DESC 
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $ranking_limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rankingProducts = $stmt->fetchAll();
        
        // Return AJAX response
        ob_start();
        ?>
        <div class="space-y-4">
            <!-- Search and Controls -->
            <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
                <div class="flex-1 max-w-md">
                    <input type="text" id="search_ranking_input" value="<?php echo htmlspecialchars($search_ranking); ?>" 
                           placeholder="Cari produk..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Tampilkan:</label>
                    <select id="ranking_limit_select" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="5" <?php echo $ranking_limit == 5 ? 'selected' : ''; ?>>5</option>
                        <option value="10" <?php echo $ranking_limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="15" <?php echo $ranking_limit == 15 ? 'selected' : ''; ?>>15</option>
                        <option value="20" <?php echo $ranking_limit == 20 ? 'selected' : ''; ?>>20</option>
                    </select>
                </div>
            </div>

            <!-- Products Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HPP</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Jual</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit/Unit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin (%)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($rankingProducts)): ?>
                            <?php foreach ($rankingProducts as $index => $product): ?>
                                <?php 
                                $rank = $offset + $index + 1;
                                $marginClass = '';
                                if ($product['profit_margin_percent'] >= 50) {
                                    $marginClass = 'text-green-600 bg-green-50';
                                } elseif ($product['profit_margin_percent'] >= 25) {
                                    $marginClass = 'text-yellow-600 bg-yellow-50';
                                } else {
                                    $marginClass = 'text-red-600 bg-red-50';
                                }
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php if ($rank <= 3): ?>
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-white font-bold text-sm
                                                    <?php echo $rank == 1 ? 'bg-yellow-500' : ($rank == 2 ? 'bg-gray-400' : 'bg-orange-500'); ?>">
                                                    <?php echo $rank; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-600 font-medium text-sm">
                                                    <?php echo $rank; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($product['unit']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        Rp <?php echo number_format($product['cost_price'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        Rp <?php echo number_format($product['sale_price'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                        Rp <?php echo number_format($product['profit_per_unit'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $marginClass; ?>">
                                            <?php echo number_format($product['profit_margin_percent'], 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                        <p class="text-gray-500 text-lg font-medium">Tidak ada produk ditemukan</p>
                                        <p class="text-gray-400 text-sm mt-1">Coba ubah kata kunci pencarian</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalRankingPages > 1): ?>
            <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                <div class="flex flex-1 justify-between sm:hidden">
                    <?php if ($ranking_page > 1): ?>
                        <button onclick="loadRankingData(<?php echo $ranking_page - 1; ?>)" class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Previous</button>
                    <?php endif; ?>
                    <?php if ($ranking_page < $totalRankingPages): ?>
                        <button onclick="loadRankingData(<?php echo $ranking_page + 1; ?>)" class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Next</button>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Menampilkan <span class="font-medium"><?php echo $offset + 1; ?></span> sampai <span class="font-medium"><?php echo min($offset + $ranking_limit, $totalRankingProducts); ?></span> dari <span class="font-medium"><?php echo $totalRankingProducts; ?></span> produk
                        </p>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <?php if ($ranking_page > 1): ?>
                                <button onclick="loadRankingData(<?php echo $ranking_page - 1; ?>)" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $ranking_page - 2); $i <= min($totalRankingPages, $ranking_page + 2); $i++): ?>
                                <button onclick="loadRankingData(<?php echo $i; ?>)" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo $i == $ranking_page ? 'z-10 bg-blue-600 text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>
                            
                            <?php if ($ranking_page < $totalRankingPages): ?>
                                <button onclick="loadRankingData(<?php echo $ranking_page + 1; ?>)" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        echo ob_get_clean();
        exit;
    }

try {
    $conn = $db;

    // Query untuk Total Penjualan
    $stmtPenjualan = $conn->query("SELECT SUM(amount) AS total_penjualan FROM transactions WHERE type = 'pemasukan'");
    $resultPenjualan = $stmtPenjualan->fetch();
    $totalPenjualan = $resultPenjualan['total_penjualan'] ?? 0;

    // Query untuk Total Pengeluaran
    $stmtPengeluaran = $conn->query("SELECT SUM(amount) AS total_pengeluaran FROM transactions WHERE type = 'pengeluaran'");
    $resultPengeluaran = $stmtPengeluaran->fetch();
    $totalPengeluaran = $resultPengeluaran['total_pengeluaran'] ?? 0;

    // Hitung Estimasi Laba Bersih
    $estimasiLabaBersih = $totalPenjualan - $totalPengeluaran;

    // Query untuk Jumlah Produk Terjual
    $stmtProdukTerjual = $conn->query("SELECT SUM(quantity) AS total_quantity_sold FROM transactions WHERE type = 'pemasukan' AND product_id IS NOT NULL");
    $resultProdukTerjual = $stmtProdukTerjual->fetch();
    $produkTerjual = $resultProdukTerjual['total_quantity_sold'] ?? 0;

    // Query untuk jumlah produk dengan stok rendah
    $stmtStokRendah = $conn->query("SELECT COUNT(*) AS stok_rendah_count FROM products WHERE current_stock < 10");
    $resultStokRendah = $stmtStokRendah->fetch();
    $stokRendahCount = $resultStokRendah['stok_rendah_count'] ?? 0;

    // Query untuk total produk
    $stmtTotalProduk = $conn->query("SELECT COUNT(*) AS total_produk FROM products");
    $resultTotalProduk = $stmtTotalProduk->fetch();
    $totalProduk = $resultTotalProduk['total_produk'] ?? 0;

    // Query untuk total transaksi
    $stmtTotalTransaksi = $conn->query("SELECT COUNT(*) AS total_transaksi FROM transactions");
    $resultTotalTransaksi = $stmtTotalTransaksi->fetch();
    $totalTransaksi = $resultTotalTransaksi['total_transaksi'] ?? 0;

    // Data untuk Grafik Tren (Penjualan dan Pengeluaran Bulanan)
    $stmtMonthlySales = $conn->query("
        SELECT
            DATE_FORMAT(date, '%Y-%m') as period,
            DATE_FORMAT(date, '%M %Y') as month_label,
            SUM(amount) as total_amount
        FROM transactions
        WHERE type = 'pemasukan' AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY period, month_label
        ORDER BY period ASC
    ");
    $rawMonthlySales = $stmtMonthlySales->fetchAll(PDO::FETCH_ASSOC);

    $stmtMonthlyExpenses = $conn->query("
        SELECT
            DATE_FORMAT(date, '%Y-%m') as period,
            DATE_FORMAT(date, '%M %Y') as month_label,
            SUM(amount) as total_amount
        FROM transactions
        WHERE type = 'pengeluaran' AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY period, month_label
        ORDER BY period ASC
    ");
    $rawMonthlyExpenses = $stmtMonthlyExpenses->fetchAll(PDO::FETCH_ASSOC);

    // Populate monthsLabel, monthlySales, monthlyExpenses for the last 6 months
    $salesData = [];
    $expenseData = [];

    foreach ($rawMonthlySales as $row) {
        $salesData[$row['period']] = $row['total_amount'];
    }

    foreach ($rawMonthlyExpenses as $row) {
        $expenseData[$row['period']] = $row['total_amount'];
    }

    // Generate data for last 6 months
    for ($i = 5; $i >= 0; $i--) {
        $date = new DateTime("-$i months");
        $period = $date->format('Y-m');
        $monthLabel = $date->format('M Y');
        $monthsLabel[] = $monthLabel;

        $monthlySales[] = isset($salesData[$period]) ? $salesData[$period] : 0;
        $monthlyExpenses[] = isset($expenseData[$period]) ? $expenseData[$period] : 0;
    }

    // Data untuk Grafik Produk Terlaris (Top 5 Products)
    $stmtPopularProducts = $conn->query("
        SELECT p.name AS product_name, SUM(t.quantity) AS total_sold_quantity
        FROM transactions t
        JOIN products p ON t.product_id = p.id
        WHERE t.type = 'pemasukan' AND t.product_id IS NOT NULL
        GROUP BY p.name
        ORDER BY total_sold_quantity DESC
        LIMIT 5
    ");
    $popularProducts = $stmtPopularProducts->fetchAll();

    foreach ($popularProducts as $product) {
        $popularProductNames[] = $product['product_name'];
        $popularProductQuantities[] = $product['total_sold_quantity'];
    }

} catch (PDOException $e) {
    error_log("Error di Dashboard Pengguna: " . $e->getMessage());
    $monthsLabel = [];
    $monthlySales = [];
    $monthlyExpenses = [];
    $popularProductNames = [];
    $popularProductQuantities = [];
}

// Convert PHP arrays to JSON for JavaScript
$monthsLabelJson = json_encode($monthsLabel);
$monthlySalesJson = json_encode($monthlySales);
$monthlyExpensesJson = json_encode($monthlyExpenses);
$popularProductNamesJson = json_encode($popularProductNames);
$popularProductQuantitiesJson = json_encode($popularProductQuantities);

?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top Bar/Header -->
        <header class="flex items-center justify-between h-16 bg-white border-b border-gray-200 px-6 shadow-sm">
            <div class="flex items-center">
                <h1 class="text-xl font-semibold text-gray-800">Dashboard Utama</h1>
            </div>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm text-gray-500">Selamat datang,</p>
                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Pengguna'); ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                    <span class="text-white font-semibold text-sm"><?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></span>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
            <!-- Welcome Section -->
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Ringkasan Bisnis Anda</h2>
                <p class="text-gray-600">Pantau performa keuangan dan operasional bisnis Anda dalam satu tampilan</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
                <!-- Total Penjualan -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-sm font-medium text-gray-500">Total Penjualan</h3>
                            <p class="text-2xl font-bold text-gray-900">Rp <?php echo number_format($totalPenjualan, 0, ',', '.'); ?></p>
                            <p class="text-xs text-gray-500 mt-1">Selama ini</p>
                        </div>
                    </div>
                </div>

                <!-- Total Pengeluaran -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-red-100 rounded-lg">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-sm font-medium text-gray-500">Total Pengeluaran</h3>
                            <p class="text-2xl font-bold text-gray-900">Rp <?php echo number_format($totalPengeluaran, 0, ',', '.'); ?></p>
                            <p class="text-xs text-gray-500 mt-1">Selama ini</p>
                        </div>
                    </div>
                </div>

                <!-- Laba Bersih -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="p-3 <?php echo $estimasiLabaBersih >= 0 ? 'bg-blue-100' : 'bg-red-100'; ?> rounded-lg">
                            <svg class="w-6 h-6 <?php echo $estimasiLabaBersih >= 0 ? 'text-blue-600' : 'text-red-600'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-sm font-medium text-gray-500">Laba Bersih</h3>
                            <p class="text-2xl font-bold <?php echo $estimasiLabaBersih >= 0 ? 'text-blue-600' : 'text-red-600'; ?>">
                                Rp <?php echo number_format($estimasiLabaBersih, 0, ',', '.'); ?>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">Penjualan - Pengeluaran</p>
                        </div>
                    </div>
                </div>

                <!-- Produk Terjual -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-sm font-medium text-gray-500">Produk Terjual</h3>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($produkTerjual, 0, ',', '.'); ?></p>
                            <p class="text-xs text-gray-500 mt-1">Total unit</p>
                        </div>
                    </div>
                </div>

                <!-- Total Produk -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-indigo-100 rounded-lg">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-sm font-medium text-gray-500">Total Produk</h3>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($totalProduk, 0, ',', '.'); ?></p>
                            <p class="text-xs text-gray-500 mt-1">Jenis produk</p>
                        </div>
                    </div>
                </div>

                <!-- Total Transaksi -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-sm font-medium text-gray-500">Total Transaksi</h3>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($totalTransaksi, 0, ',', '.'); ?></p>
                            <p class="text-xs text-gray-500 mt-1">Semua transaksi</p>
                        </div>
                    </div>
                </div>

                <!-- Stok Rendah -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="p-3 bg-orange-100 rounded-lg">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <div class="ml-4 flex-1">
                            <h3 class="text-sm font-medium text-gray-500">Stok Rendah</h3>
                            <p class="text-2xl font-bold text-orange-600"><?php echo $stokRendahCount; ?></p>
                            <p class="text-xs text-gray-500 mt-1">Perlu restock</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Card -->
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-sm p-6 text-white">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">Aksi Cepat</h3>
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div class="space-y-3">
                        <a href="/cornerbites-sia/pages/produk.php" class="block bg-white/20 hover:bg-white/30 rounded-lg p-3 transition-colors duration-200">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                <span class="text-sm font-medium">Tambah Produk</span>
                            </div>
                        </a>
                        <a href="/cornerbites-sia/pages/resep_produk.php" class="block bg-white/20 hover:bg-white/30 rounded-lg p-3 transition-colors duration-200">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                                <span class="text-sm font-medium">Kelola HPP</span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Grafik Tren Penjualan & Pengeluaran -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Tren Keuangan</h3>
                            <p class="text-sm text-gray-500">Penjualan vs Pengeluaran (6 bulan terakhir)</p>
                        </div>
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="relative h-80">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <!-- Grafik Produk Terlaris -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Produk Terlaris</h3>
                            <p class="text-sm text-gray-500">Top 5 produk berdasarkan unit terjual</p>
                        </div>
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="relative h-80">
                        <canvas id="popularProductsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Ranking Produk Berdasarkan HPP -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Ranking Produk Berdasarkan Margin Keuntungan</h3>
                        <p class="text-sm text-gray-500">Produk dengan margin keuntungan tertinggi berdasarkan HPP</p>
                    </div>
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                </div>
                <div id="ranking-container">
                    <!-- Content will be loaded via AJAX -->
                    <div class="flex justify-center items-center py-12">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-600"></div>
                        <span class="ml-2 text-gray-600">Memuat ranking produk...</span>
                    </div>
                </div>
            </div>

            <!-- Alert untuk Stok Rendah -->
            <?php if ($stokRendahCount > 0): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            <strong>Perhatian!</strong> Ada <?php echo $stokRendahCount; ?> produk dengan stok rendah yang perlu segera di-restock.
                            <a href="/cornerbites-sia/pages/produk.php" class="font-medium underline text-yellow-700 hover:text-yellow-600">
                                Lihat detail produk →
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
<script src="/cornerbites-sia/assets/js/dashboard.js"></script>

<script>
    // Data PHP yang di-encode ke JSON
    const monthsLabel = <?php echo $monthsLabelJson; ?>;
    const monthlySales = <?php echo $monthlySalesJson; ?>;
    const monthlyExpenses = <?php echo $monthlyExpensesJson; ?>;
    const popularProductNames = <?php echo $popularProductNamesJson; ?>;
    const popularProductQuantities = <?php echo $popularProductQuantitiesJson; ?>;

    // Chart.js untuk Tren Penjualan & Pengeluaran
    const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
    const monthlyChart = new Chart(ctxMonthly, {
        type: 'line',
        data: {
            labels: monthsLabel,
            datasets: [
                {
                    label: 'Penjualan',
                    data: monthlySales,
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderColor: 'rgba(34, 197, 94, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(34, 197, 94, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                },
                {
                    label: 'Pengeluaran',
                    data: monthlyExpenses,
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(239, 68, 68, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            family: 'Inter',
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                    },
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
                        },
                        font: {
                            family: 'Inter',
                            size: 11
                        }
                    }
                },
                x: {
                    grid: {
                        display: false,
                    },
                    ticks: {
                        font: {
                            family: 'Inter',
                            size: 11
                        }
                    }
                }
            }
        }
    });

    // Chart.js untuk Produk Terlaris
    const ctxPopular = document.getElementById('popularProductsChart').getContext('2d');
    const popularProductsChart = new Chart(ctxPopular, {
        type: 'doughnut',
        data: {
            labels: popularProductNames,
            datasets: [{
                data: popularProductQuantities,
                backgroundColor: [
                    'rgba(99, 102, 241, 0.8)',
                    'rgba(168, 85, 247, 0.8)',
                    'rgba(236, 72, 153, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(34, 197, 94, 0.8)'
                ],
                borderColor: [
                    'rgba(99, 102, 241, 1)',
                    'rgba(168, 85, 247, 1)',
                    'rgba(236, 72, 153, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(34, 197, 94, 1)'
                ],
                borderWidth: 2,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            family: 'Inter',
                            size: 11
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(255, 255, 255, 0.1)',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + new Intl.NumberFormat('id-ID').format(context.parsed) + ' unit';
                        }
                    }
                }
            }
        }
    });

    // Load ranking data on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadRankingData(1, 10, '');
    });
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>