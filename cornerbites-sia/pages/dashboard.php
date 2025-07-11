
<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// Handle AJAX request for ranking pagination with search
if (isset($_GET['ajax']) && $_GET['ajax'] === 'ranking') {
    $ranking_page = isset($_GET['ranking_page']) ? max((int)$_GET['ranking_page'], 1) : 1;
    $ranking_limit = isset($_GET['ranking_limit']) ? max((int)$_GET['ranking_limit'], 5) : 5;
    $ranking_offset = ($ranking_page - 1) * $ranking_limit;
    $search_ranking = isset($_GET['search_ranking']) ? trim($_GET['search_ranking']) : '';

    try {
        $conn = $db;

        // Build search condition - lebih permisif untuk menampilkan produk dengan HPP
        $where_condition = "WHERE cost_price > 0 AND cost_price IS NOT NULL";
        $params = [];

        if (!empty($search_ranking)) {
            $where_condition .= " AND name LIKE :search";
            $params[':search'] = '%' . $search_ranking . '%';
        }

        // Count total products for pagination
        $count_query = "SELECT COUNT(*) as total FROM products " . $where_condition;
        $stmt = $conn->prepare($count_query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $total_products_ranking = $result ? ($result['total'] ?? 0) : 0;
        $total_ranking_pages = ceil($total_products_ranking / $ranking_limit);

        // Get ranking data
        $ranking_query = "SELECT name, cost_price, 
                         COALESCE(sale_price, 0) as sale_price, 
                         stock, 
                         (COALESCE(sale_price, 0) - cost_price) as profit, 
                         CASE 
                           WHEN COALESCE(sale_price, 0) > 0 THEN ((COALESCE(sale_price, 0) - cost_price) / COALESCE(sale_price, 0) * 100)
                           ELSE 0 
                         END as margin,
                         CASE 
                           WHEN COALESCE(sale_price, 0) = 0 THEN 'Belum Set Harga'
                           WHEN COALESCE(sale_price, 0) > cost_price THEN 'Menguntungkan' 
                           ELSE 'Rugi' 
                         END as status 
                         FROM products " . $where_condition . "
                         ORDER BY cost_price DESC LIMIT :limit OFFSET :offset";

        $stmt = $conn->prepare($ranking_query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $ranking_limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $ranking_offset, PDO::PARAM_INT);
        $stmt->execute();
        $profitabilityRanking = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        ?>
        <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center space-x-2">
                <label for="ranking_limit_select" class="text-sm font-medium text-gray-700">Show:</label>
                <select id="ranking_limit_select" onchange="updateRankingLimit(this.value)" 
                        class="border border-gray-300 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="5" <?php echo $ranking_limit == 5 ? 'selected' : ''; ?>>5</option>
                    <option value="10" <?php echo $ranking_limit == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $ranking_limit == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $ranking_limit == 50 ? 'selected' : ''; ?>>50</option>
                </select>
                <span class="text-sm text-gray-700">entries</span>
            </div>

            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input type="text" id="search_ranking_input" 
                       placeholder="Cari produk..." 
                       value="<?php echo htmlspecialchars($search_ranking); ?>"
                       onkeyup="searchRanking(this.value)"
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Produk</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HPP per Unit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Jual</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit per Unit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin (%)</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($profitabilityRanking)): ?>
                        <?php foreach ($profitabilityRanking as $index => $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <span class="inline-flex items-center justify-center w-8 h-8 bg-yellow-100 text-yellow-800 rounded-full text-xs font-bold">
                                        <?php echo (($ranking_page - 1) * $ranking_limit) + $index + 1; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($product['cost_price'], 0, ',', '.'); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($product['sale_price'], 0, ',', '.'); ?></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold <?php echo $product['profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    Rp <?php echo number_format($product['profit'], 0, ',', '.'); ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold <?php echo $product['margin'] >= 15 ? 'text-green-600' : ($product['margin'] >= 5 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                    <?php echo number_format($product['margin'], 1); ?>%
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $product['stock'] >= 10 ? 'bg-green-100 text-green-800' : ($product['stock'] >= 5 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                        <?php echo number_format($product['stock']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $product['status'] == 'Menguntungkan' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $product['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                <?php echo !empty($search_ranking) ? 'Tidak ada produk yang ditemukan dengan pencarian "' . htmlspecialchars($search_ranking) . '"' : 'Belum ada produk dengan HPP yang sudah dihitung'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination for Ranking -->
        <?php if ($total_ranking_pages > 1): ?>
        <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="text-sm text-gray-700">
                Menampilkan <?php echo (($ranking_page - 1) * $ranking_limit) + 1; ?> - 
                <?php echo min($ranking_page * $ranking_limit, $total_products_ranking); ?> 
                dari <?php echo $total_products_ranking; ?> produk
                <?php if (!empty($search_ranking)): ?>
                    <span class="text-blue-600">(filtered from search)</span>
                <?php endif; ?>
            </div>
            <div class="flex space-x-2">
                <?php if ($ranking_page > 1): ?>
                    <button onclick="loadRankingData(<?php echo $ranking_page - 1; ?>)"
                           class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Previous
                    </button>
                <?php endif; ?>

                <?php
                $start_page = max(1, $ranking_page - 2);
                $end_page = min($total_ranking_pages, $ranking_page + 2);
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <button onclick="loadRankingData(<?php echo $i; ?>)"
                           class="px-3 py-2 text-sm <?php echo $i == $ranking_page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> rounded-lg transition-colors">
                        <?php echo $i; ?>
                    </button>
                <?php endfor; ?>

                <?php if ($ranking_page < $total_ranking_pages): ?>
                    <button onclick="loadRankingData(<?php echo $ranking_page + 1; ?>)"
                           class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Next
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php
        $content = ob_get_clean();
        echo $content;
        exit;
    } catch (Exception $e) {
        echo '<div class="text-center text-red-500 py-8">Error loading data: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }
}

// Initialize variables with default values
$totalProducts = 0;
$totalRawMaterials = 0;
$lowStockProducts = 0;
$lowStockMaterials = 0;
$totalRecipes = 0;
$avgHPP = 0;
$avgMargin = 0;
$profitableProducts = 0;
$totalBahanBaku = 0;
$totalKemasan = 0;
$totalLaborPositions = 0;
$totalOverheadItems = 0;
$totalLaborCost = 0;
$totalOverheadCost = 0;
$highestProfitProduct = null;
$lowestProfitProduct = null;
$profitabilityRanking = [];
$total_products_ranking = 0;
$total_ranking_pages = 0;

try {
    $conn = $db;

    // Total Products
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products");
    $result = $stmt->fetch();
    $totalProducts = $result ? ($result['total'] ?? 0) : 0;

    // Total Raw Materials (Bahan Baku + Kemasan)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM raw_materials");
    $result = $stmt->fetch();
    $totalRawMaterials = $result ? ($result['total'] ?? 0) : 0;

    // Total Bahan Baku only
    $stmt = $conn->query("SELECT COUNT(*) as total FROM raw_materials WHERE type = 'bahan'");
    $result = $stmt->fetch();
    $totalBahanBaku = $result ? ($result['total'] ?? 0) : 0;

    // Total Kemasan only  
    $stmt = $conn->query("SELECT COUNT(*) as total FROM raw_materials WHERE type = 'kemasan'");
    $result = $stmt->fetch();
    $totalKemasan = $result ? ($result['total'] ?? 0) : 0;

    // Low Stock Products (< 10)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE stock < 10");
    $result = $stmt->fetch();
    $lowStockProducts = $result ? ($result['total'] ?? 0) : 0;

    // Low Stock Raw Materials (< 1)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM raw_materials WHERE current_stock < 1");
    $result = $stmt->fetch();
    $lowStockMaterials = $result ? ($result['total'] ?? 0) : 0;

    // Total Recipes Active (products that have recipes)
    $stmt = $conn->query("SELECT COUNT(DISTINCT product_id) as total FROM product_recipes");
    $result = $stmt->fetch();
    $totalRecipes = $result ? ($result['total'] ?? 0) : 0;

    // Total Labor Positions and Cost (hanya yang aktif)
    try {
        // Cek apakah tabel labor_costs ada
        $stmt = $conn->query("SHOW TABLES LIKE 'labor_costs'");
        if ($stmt->rowCount() > 0) {
            $stmt = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(hourly_rate), 0) as total_cost FROM labor_costs WHERE is_active = 1");
            $result = $stmt->fetch();
            $totalLaborPositions = $result ? ($result['total'] ?? 0) : 0;
            $totalLaborCost = $result ? ($result['total_cost'] ?? 0) : 0;
        } else {
            $totalLaborPositions = 0;
            $totalLaborCost = 0;
        }
    } catch (PDOException $e) {
        error_log("Error labor query: " . $e->getMessage());
        $totalLaborPositions = 0;
        $totalLaborCost = 0;
    }

    // Total Overhead Items and Cost (hanya yang aktif)
    try {
        // Cek apakah tabel overhead_costs ada
        $stmt = $conn->query("SHOW TABLES LIKE 'overhead_costs'");
        if ($stmt->rowCount() > 0) {
            $stmt = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as total_cost FROM overhead_costs WHERE is_active = 1");
            $result = $stmt->fetch();
            $totalOverheadItems = $result ? ($result['total'] ?? 0) : 0;
            $totalOverheadCost = $result ? ($result['total_cost'] ?? 0) : 0;
        } else {
            $totalOverheadItems = 0;
            $totalOverheadCost = 0;
        }
    } catch (PDOException $e) {
        error_log("Error overhead query: " . $e->getMessage());
        $totalOverheadItems = 0;
        $totalOverheadCost = 0;
    }

    // Calculate Average HPP (only products with calculated HPP)
    $stmt = $conn->query("SELECT AVG(cost_price) as avg_hpp FROM products WHERE cost_price > 0 AND cost_price IS NOT NULL");
    $result = $stmt->fetch();
    $avgHPP = $result ? ($result['avg_hpp'] ?? 0) : 0;

    // Calculate Average Margin (only products with both cost and sale price)
    $stmt = $conn->query("SELECT AVG(((sale_price - cost_price) / sale_price) * 100) as avg_margin FROM products WHERE sale_price > 0 AND cost_price > 0 AND sale_price IS NOT NULL AND cost_price IS NOT NULL");
    $result = $stmt->fetch();
    $avgMargin = $result ? ($result['avg_margin'] ?? 0) : 0;

    // Count Profitable Products (profit > 0)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE sale_price > cost_price AND sale_price > 0 AND cost_price > 0 AND sale_price IS NOT NULL AND cost_price IS NOT NULL");
    $result = $stmt->fetch();
    $profitableProducts = $result ? ($result['total'] ?? 0) : 0;

    // Pagination for profitability ranking
    $ranking_page = isset($_GET['ranking_page']) ? max((int)$_GET['ranking_page'], 1) : 1;
    $ranking_limit = 10;
    $ranking_offset = ($ranking_page - 1) * $ranking_limit;

    // Count total products with HPP for pagination  
    $stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE cost_price > 0 AND cost_price IS NOT NULL");
    $result = $stmt->fetch();
    $total_products_ranking = $result ? ($result['total'] ?? 0) : 0;
    $total_ranking_pages = ceil($total_products_ranking / $ranking_limit);

    // Profitability Ranking with pagination (products with calculated HPP)
    if ($total_products_ranking > 0) {
        $stmt = $conn->prepare("SELECT name, cost_price, 
                              COALESCE(sale_price, 0) as sale_price, 
                              stock,
                              (COALESCE(sale_price, 0) - cost_price) as profit, 
                              CASE 
                                WHEN COALESCE(sale_price, 0) > 0 THEN ((COALESCE(sale_price, 0) - cost_price) / COALESCE(sale_price, 0) * 100)
                                ELSE 0 
                              END as margin,
                              CASE 
                                WHEN COALESCE(sale_price, 0) = 0 THEN 'Belum Set Harga'
                                WHEN COALESCE(sale_price, 0) > cost_price THEN 'Menguntungkan' 
                                ELSE 'Rugi' 
                              END as status 
                              FROM products WHERE cost_price > 0 AND cost_price IS NOT NULL
                              ORDER BY cost_price DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $ranking_limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $ranking_offset, PDO::PARAM_INT);
        $stmt->execute();
        $profitabilityRanking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Error di Dashboard: " . $e->getMessage());
}
?>

<?php include_once __DIR__ . '/../includes/header.php'; ?>
<div class="flex h-screen bg-gradient-to-br from-gray-50 to-gray-100 font-sans">
    <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gradient-to-br from-gray-50 to-gray-100 p-6">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Dashboard HPP Calculator</h1>
                    <p class="text-gray-600">Analisis Harga Pokok Produksi dengan metode Full Costing</p>
                </div>

                <!-- Main Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Products -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-blue-600 bg-blue-100 px-2 py-1 rounded-full">Total Produk</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Produk</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalProducts); ?></p>
                        <div class="flex items-center text-xs text-gray-500">
                            <svg class="w-3 h-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 12L8 10l1.41-1.41L10 9.17l.59-.58L12 10l-2 2z"/>
                            </svg>
                            <?php echo number_format($totalRecipes); ?> dengan resep
                        </div>
                    </div>

                    <!-- Rata-rata Margin -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-green-600 bg-green-100 px-2 py-1 rounded-full">Rata-rata Margin</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Rata-rata Margin</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($avgMargin, 1); ?>%</p>
                        <div class="text-xs text-gray-500">
                            <?php if ($avgMargin >= 20): ?>
                                <span class="text-green-600">Sangat baik</span>
                            <?php elseif ($avgMargin >= 15): ?>
                                <span class="text-blue-600">Baik</span>
                            <?php elseif ($avgMargin >= 10): ?>
                                <span class="text-yellow-600">Cukup</span>
                            <?php else: ?>
                                <span class="text-red-600">Perlu peningkatan</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rata-rata HPP -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-purple-600 bg-purple-100 px-2 py-1 rounded-full">Rata-rata HPP</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Rata-rata HPP</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1">Rp <?php echo number_format($avgHPP, 0, ',', '.'); ?></p>
                        <div class="text-xs text-gray-500">Per unit produk</div>
                    </div>

                    <!-- Produk Menguntungkan -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-yellow-600 bg-yellow-100 px-2 py-1 rounded-full">Produk Profit ></span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Produk Menguntungkan</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($profitableProducts); ?></p>
                        <div class="text-xs text-gray-500">dari <?php echo number_format($total_products_ranking); ?> produk</div>
                    </div>
                </div>

                <!-- Secondary Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Total Bahan Baku & Kemasan -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-amber-400 to-amber-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-amber-600 bg-amber-100 px-2 py-1 rounded-full">Bahan & Kemasan</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Bahan Baku & Kemasan</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalRawMaterials); ?></p>
                        <div class="text-xs text-gray-500">
                            <?php echo number_format($totalBahanBaku); ?> bahan, <?php echo number_format($totalKemasan); ?> kemasan
                        </div>
                    </div>

                    

                    <!-- Total Tenaga Kerja -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-orange-400 to-orange-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-orange-600 bg-orange-100 px-2 py-1 rounded-full">Tenaga Kerja</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Tenaga Kerja</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalLaborPositions); ?></p>
                        <div class="text-xs text-gray-500">Rp <?php echo number_format($totalLaborCost, 0, ',', '.'); ?>/jam</div>
                    </div>

                    <!-- Total Overhead -->
                    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100 transform hover:scale-105 transition-transform duration-200">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 bg-gradient-to-r from-emerald-400 to-emerald-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-emerald-600 bg-emerald-100 px-2 py-1 rounded-full">Overhead</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Overhead</h3>
                        <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalOverheadItems); ?></p>
                        <div class="text-xs text-gray-500">Rp <?php echo number_format($totalOverheadCost, 0, ',', '.'); ?></div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                    <!-- Ranking Profitabilitas Produk -->
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Ranking Profitabilitas Produk</h3>
                                <p class="text-sm text-gray-600">Produk dengan HPP yang sudah dihitung</p>
                            </div>
                        </div>

                        <div id="ranking-container">
                            <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                <div class="flex items-center space-x-2">
                                    <label for="ranking_limit_select" class="text-sm font-medium text-gray-700">Show:</label>
                                    <select id="ranking_limit_select" onchange="updateRankingLimit(this.value)" 
                                            class="border border-gray-300 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="5">5</option>
                                        <option value="10" selected>10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </select>
                                    <span class="text-sm text-gray-700">entries</span>
                                </div>

                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                    <input type="text" id="search_ranking_input" 
                                           placeholder="Cari produk..." 
                                           onkeyup="searchRanking(this.value)"
                                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Produk</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HPP per Unit</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Jual</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit per Unit</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin (%)</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (!empty($profitabilityRanking)): ?>
                                            <?php foreach ($profitabilityRanking as $index => $product): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <span class="inline-flex items-center justify-center w-8 h-8 bg-yellow-100 text-yellow-800 rounded-full text-xs font-bold">
                                                            <?php echo (($ranking_page - 1) * $ranking_limit) + $index + 1; ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">Rp <?php echo number_format($product['cost_price'], 0, ',', '.'); ?></td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?php if ($product['sale_price'] > 0): ?>
                                                            Rp <?php echo number_format($product['sale_price'], 0, ',', '.'); ?>
                                                        <?php else: ?>
                                                            <span class="text-gray-400 italic">Belum diset</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold <?php echo $product['profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                        <?php if ($product['sale_price'] > 0): ?>
                                                            Rp <?php echo number_format($product['profit'], 0, ',', '.'); ?>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold <?php echo $product['margin'] >= 15 ? 'text-green-600' : ($product['margin'] >= 5 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                                        <?php if ($product['sale_price'] > 0): ?>
                                                            <?php echo number_format($product['margin'], 1); ?>%
                                                        <?php else: ?>
                                                            <span class="text-gray-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $product['stock'] >= 10 ? 'bg-green-100 text-green-800' : ($product['stock'] >= 5 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                            <?php echo number_format($product['stock']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-4 whitespace-nowrap">
                                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $product['status'] == 'Menguntungkan' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                                            <?php echo $product['status']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                                    Belum ada produk dengan HPP yang sudah dihitung. Buat resep produk terlebih dahulu untuk menghitung HPP.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination for Ranking -->
                            <?php if ($total_ranking_pages > 1): ?>
                            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                                <div class="text-sm text-gray-700">
                                    Menampilkan <?php echo (($ranking_page - 1) * $ranking_limit) + 1; ?> - 
                                    <?php echo min($ranking_page * $ranking_limit, $total_products_ranking); ?> 
                                    dari <?php echo $total_products_ranking; ?> produk
                                </div>
                                <div class="flex space-x-2">
                                    <?php if ($ranking_page > 1): ?>
                                        <button onclick="loadRankingData(<?php echo $ranking_page - 1; ?>)"
                                               class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                            Previous
                                        </button>
                                    <?php endif; ?>

                                    <?php
                                    $start_page = max(1, $ranking_page - 2);
                                    $end_page = min($total_ranking_pages, $ranking_page + 2);
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <button onclick="loadRankingData(<?php echo $i; ?>)"
                                               class="px-3 py-2 text-sm <?php echo $i == $ranking_page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> rounded-lg transition-colors">
                                            <?php echo $i; ?>
                                        </button>
                                    <?php endfor; ?>

                                    <?php if ($ranking_page < $total_ranking_pages): ?>
                                        <button onclick="loadRankingData(<?php echo $ranking_page + 1; ?>)"
                                               class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                            Next
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rekomendasi Strategis -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Rekomendasi Strategis</h3>
                            </div>
                        </div>

                        <!-- Tips Umum -->
                        <div class="mb-6">
                            <div class="flex items-center mb-3">
                                <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mr-2">
                                    <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <h4 class="text-sm font-semibold text-gray-800">Tips Umum</h4>
                            </div>
                            <ul class="space-y-2 text-sm text-gray-600">
                                <li class="flex items-start">
                                    <span class="text-green-500 mr-2">•</span>
                                    <span>Target margin minimum 15-20% untuk UMKM makanan</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="text-blue-500 mr-2">•</span>
                                    <span>Review HPP setiap bulan karena fluktuasi harga bahan</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="text-purple-500 mr-2">•</span>
                                    <span>Negosiasi dengan supplier untuk pembelian dalam jumlah besar</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Alert untuk Lengkapi Resep -->
                        <?php if ($totalProducts - $totalRecipes > 0): ?>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-amber-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                <h4 class="text-sm font-semibold text-amber-800">Lengkapi Resep</h4>
                            </div>
                            <p class="text-sm text-amber-700">
                                <?php echo number_format($totalProducts - $totalRecipes); ?> produk belum memiliki resep. Lengkapi resep untuk perhitungan HPP yang akurat.
                            </p>
                        </div>
                        <?php endif; ?>

                        <!-- Alert untuk Data Labor/Overhead Kosong -->
                        <?php if ($totalLaborPositions == 0 || $totalOverheadItems == 0): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <h4 class="text-sm font-semibold text-red-800">Data Belum Lengkap</h4>
                            </div>
                            <div class="text-sm text-red-700">
                                <?php if ($totalLaborPositions == 0): ?>
                                    <p>• Belum ada data tenaga kerja</p>
                                <?php endif; ?>
                                <?php if ($totalOverheadItems == 0): ?>
                                    <p>• Belum ada data overhead</p>
                                <?php endif; ?>
                                <p class="mt-2">Lengkapi data ini untuk kalkulasi HPP yang tepat.</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
let currentRankingPage = 1;
let currentRankingLimit = 10;
let currentSearchRanking = '';

function loadRankingData(page) {
    currentRankingPage = page;
    
    const url = `?ajax=ranking&ranking_page=${page}&ranking_limit=${currentRankingLimit}&search_ranking=${encodeURIComponent(currentSearchRanking)}`;
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            document.getElementById('ranking-container').innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading ranking data:', error);
        });
}

function updateRankingLimit(limit) {
    currentRankingLimit = limit;
    loadRankingData(1);
}

function searchRanking(searchTerm) {
    currentSearchRanking = searchTerm;
    loadRankingData(1);
}

// Auto-refresh data setiap 30 detik untuk data yang dinamis
setInterval(function() {
    // Refresh hanya jika tidak sedang ada interaksi user
    if (!document.querySelector('input:focus') && !document.querySelector('select:focus')) {
        loadRankingData(currentRankingPage);
    }
}, 30000);
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
