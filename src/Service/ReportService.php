<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Booth\RentDeductionRepository;
use CurserPos\Domain\Item\Item;
use PDO;

class ReportService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly RentDeductionRepository $rentDeductionRepository
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function salesSummary(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = 'SELECT COUNT(*) AS total_sales, COALESCE(SUM(total), 0) AS total_amount, COALESCE(SUM(discount_amount), 0) AS total_discounts, COALESCE(SUM(tax_amount), 0) AS total_tax FROM sales WHERE status = ?';
        $params = ['completed'];
        if ($dateFrom !== null) {
            $sql .= ' AND created_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= ' AND created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : ['total_sales' => 0, 'total_amount' => 0, 'total_discounts' => 0, 'total_tax' => 0];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function salesByConsignor(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = 'SELECT si.consignor_id, c.name AS consignor_name, c.custom_id AS consignor_custom_id, COUNT(*) AS sale_count, COALESCE(SUM(consignor_share), 0) AS total_share FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN consignors c ON c.id = si.consignor_id WHERE s.status = ?';
        $params = ['completed'];
        if ($dateFrom !== null) {
            $sql .= ' AND s.created_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= ' AND s.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        $sql .= ' GROUP BY si.consignor_id, c.name, c.custom_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inventorySummary(): array
    {
        $stmt = $this->pdo->query(
            'SELECT status, COUNT(*) AS count, COALESCE(SUM(price), 0) AS total_value FROM items GROUP BY status'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payoutsSummary(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = 'SELECT method, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total FROM payouts WHERE status = ?';
        $params = ['processed'];
        if ($dateFrom !== null) {
            $sql .= ' AND processed_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= ' AND processed_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        $sql .= ' GROUP BY method';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Detailed list of processed payouts with consignor info for a date range.
     *
     * @return list<array<string, mixed>>
     */
    public function payoutsDetail(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = 'SELECT p.id, p.consignor_id, c.name AS consignor_name, p.amount, p.method, p.status, p.reference, p.method_metadata, p.created_at, p.processed_at FROM payouts p JOIN consignors c ON c.id = p.consignor_id WHERE p.status = ?';
        $params = ['processed'];
        if ($dateFrom !== null) {
            $sql .= ' AND p.processed_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= ' AND p.processed_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        $sql .= ' ORDER BY p.processed_at';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Export sales to CSV format (string).
     */
    public function exportSalesCsv(?string $dateFrom = null, ?string $dateTo = null): string
    {
        $sql = 'SELECT id, sale_number, subtotal, discount_amount, tax_amount, total, status, created_at FROM sales WHERE 1=1';
        $params = [];
        if ($dateFrom !== null) {
            $sql .= ' AND created_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= ' AND created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        $sql .= ' ORDER BY created_at';
        $stmt = $params === [] ? $this->pdo->query($sql) : $this->pdo->prepare($sql);
        if ($params !== []) {
            $stmt->execute($params);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }
        if ($rows !== []) {
            fputcsv($out, array_keys($rows[0]), ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '\\');
            }
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv !== false ? $csv : '';
    }

    /**
     * Dashboard key metrics.
     *
     * @return array<string, mixed>
     */
    public function dashboardMetrics(): array
    {
        $todayStart = date('Y-m-d') . ' 00:00:00';
        $todayEnd = date('Y-m-d') . ' 23:59:59';
        $weekStart = date('Y-m-d', strtotime('-7 days')) . ' 00:00:00';

        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS total FROM sales WHERE status = ? AND created_at >= ? AND created_at <= ?');
        $stmt->execute(['completed', $todayStart, $todayEnd]);
        $today = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS total FROM sales WHERE status = ? AND created_at >= ?');
        $stmt->execute(['completed', $weekStart]);
        $week = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM items WHERE status = ?');
        $stmt->execute([Item::STATUS_AVAILABLE]);
        $availableItems = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM consignors WHERE status = ?');
        $stmt->execute(['active']);
        $activeConsignors = (int) $stmt->fetchColumn();

        return [
            'sales_today_count' => (int) ($today['cnt'] ?? 0),
            'sales_today_total' => (float) ($today['total'] ?? 0),
            'sales_week_count' => (int) ($week['cnt'] ?? 0),
            'sales_week_total' => (float) ($week['total'] ?? 0),
            'inventory_available_count' => $availableItems,
            'active_consignors_count' => $activeConsignors,
        ];
    }

    /**
     * QuickBooks-friendly sales export (CSV with standard columns).
     */
    public function exportQuickBooksSalesCsv(?string $dateFrom = null, ?string $dateTo = null): string
    {
        $sql = 'SELECT sale_number AS "Num", created_at AS "Date", total AS "Amount", subtotal, discount_amount, tax_amount, status FROM sales WHERE status IN (?, ?)';
        $params = ['completed', 'refunded'];
        if ($dateFrom !== null) {
            $sql .= ' AND created_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= ' AND created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        $sql .= ' ORDER BY created_at';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }
        if ($rows !== []) {
            fputcsv($out, array_keys($rows[0]), ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '\\');
            }
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv !== false ? $csv : '';
    }

    /**
     * QuickBooks-friendly payouts export (CSV).
     */
    public function exportQuickBooksPayoutsCsv(?string $dateFrom = null, ?string $dateTo = null): string
    {
        $sql = 'SELECT p.id, p.consignor_id, c.name AS consignor_name, p.amount, p.method, p.status, p.created_at, p.processed_at FROM payouts p JOIN consignors c ON c.id = p.consignor_id WHERE p.status = ?';
        $params = ['processed'];
        if ($dateFrom !== null) {
            $sql .= ' AND p.processed_at >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $sql .= ' AND p.processed_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        $sql .= ' ORDER BY p.processed_at';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }
        if ($rows !== []) {
            fputcsv($out, array_keys($rows[0]), ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '\\');
            }
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv !== false ? $csv : '';
    }

    /**
     * Vendor mall report: sales by vendor (consignor), rent collected, booth count.
     *
     * @return array<string, mixed>
     */
    public function vendorMallSummary(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $salesByConsignor = $this->salesByConsignor($dateFrom, $dateTo);
        $rentCollected = $this->rentDeductionRepository->sumCollected($dateFrom, $dateTo);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM consignor_booth_assignments WHERE ended_at IS NULL');
        $stmt->execute();
        $vendorsWithBooths = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM booths WHERE status = ?');
        $stmt->execute(['active']);
        $activeBooths = (int) $stmt->fetchColumn();

        return [
            'sales_by_vendor' => $salesByConsignor,
            'rent_collected' => $rentCollected,
            'vendors_with_booths' => $vendorsWithBooths,
            'active_booths' => $activeBooths,
        ];
    }
}
