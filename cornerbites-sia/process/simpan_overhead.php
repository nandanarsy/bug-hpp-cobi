
<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = $db;
        $type = $_POST['type'] ?? '';

        if ($type === 'overhead') {
            $overhead_id = $_POST['overhead_id'] ?? '';
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $amount = floatval(str_replace(['.', ','], ['', '.'], $_POST['amount']));
            $allocation_method = $_POST['allocation_method'] ?? 'per_batch';
            $estimated_uses = intval($_POST['estimated_uses'] ?? 1);

            if (empty($name) || $amount <= 0 || $estimated_uses <= 0) {
                $_SESSION['overhead_message'] = [
                    'text' => 'Nama overhead, jumlah, dan estimasi pemakaian harus diisi dengan benar.',
                    'type' => 'error'
                ];
                header("Location: /cornerbites-sia/pages/overhead_management.php");
                exit();
            }

            if (!empty($overhead_id)) {
                // Update overhead
                $stmt = $conn->prepare("UPDATE overhead_costs SET name = ?, description = ?, amount = ?, allocation_method = ?, estimated_uses = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($stmt->execute([$name, $description, $amount, $allocation_method, $estimated_uses, $overhead_id])) {
                    $_SESSION['overhead_message'] = [
                        'text' => 'Overhead berhasil diperbarui.',
                        'type' => 'success'
                    ];
                } else {
                    throw new Exception("Gagal memperbarui overhead.");
                }
            } else {
                // Insert new overhead
                $stmt = $conn->prepare("INSERT INTO overhead_costs (name, description, amount, allocation_method, estimated_uses, created_at, updated_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                if ($stmt->execute([$name, $description, $amount, $allocation_method, $estimated_uses])) {
                    $_SESSION['overhead_message'] = [
                        'text' => 'Overhead berhasil ditambahkan.',
                        'type' => 'success'
                    ];
                } else {
                    throw new Exception("Gagal menambahkan overhead.");
                }
            }

        } elseif ($type === 'labor') {
            $labor_id = $_POST['labor_id'] ?? '';
            $position_name = trim($_POST['position_name']);
            $hourly_rate = floatval(str_replace(['.', ','], ['', '.'], $_POST['hourly_rate']));

            if (empty($position_name) || $hourly_rate <= 0) {
                $_SESSION['overhead_message'] = [
                    'text' => 'Nama posisi dan upah per jam harus diisi dengan benar.',
                    'type' => 'error'
                ];
                header("Location: /cornerbites-sia/pages/overhead_management.php");
                exit();
            }

            if (!empty($labor_id)) {
                // Update labor
                $stmt = $conn->prepare("UPDATE labor_costs SET position_name = ?, hourly_rate = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                if ($stmt->execute([$position_name, $hourly_rate, $labor_id])) {
                    $_SESSION['overhead_message'] = [
                        'text' => 'Data tenaga kerja berhasil diperbarui.',
                        'type' => 'success'
                    ];
                } else {
                    throw new Exception("Gagal memperbarui data tenaga kerja.");
                }
            } else {
                // Insert new labor
                $stmt = $conn->prepare("INSERT INTO labor_costs (position_name, hourly_rate, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                if ($stmt->execute([$position_name, $hourly_rate])) {
                    $_SESSION['overhead_message'] = [
                        'text' => 'Data tenaga kerja berhasil ditambahkan.',
                        'type' => 'success'
                    ];
                } else {
                    throw new Exception("Gagal menambahkan data tenaga kerja.");
                }
            }
        } else {
            throw new Exception("Tipe aksi tidak valid.");
        }

    } catch (Exception $e) {
        $_SESSION['overhead_message'] = [
            'text' => $e->getMessage(),
            'type' => 'error'
        ];
        error_log("Error in simpan_overhead.php: " . $e->getMessage());
    }

    header("Location: /cornerbites-sia/pages/overhead_management.php");
    exit();
} else {
    header("Location: /cornerbites-sia/pages/overhead_management.php");
    exit();
}
?>
