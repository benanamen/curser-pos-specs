<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Service\ReportService;
use PerfectApp\Routing\Route;

final class ReportController
{
    public function __construct(
        private readonly ReportService $reportService
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/reports/sales/summary', ['GET'])]
    public function salesSummary(string $slug): void
    {
        $dateFrom = isset($_GET['from']) ? (string) $_GET['from'] : null;
        $dateTo = isset($_GET['to']) ? (string) $_GET['to'] : null;
        $data = $this->reportService->salesSummary($dateFrom, $dateTo);
        $this->json(200, $data);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/reports/sales/by-consignor', ['GET'])]
    public function salesByConsignor(string $slug): void
    {
        $dateFrom = isset($_GET['from']) ? (string) $_GET['from'] : null;
        $dateTo = isset($_GET['to']) ? (string) $_GET['to'] : null;
        $data = $this->reportService->salesByConsignor($dateFrom, $dateTo);
        $this->json(200, $data);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/reports/inventory/summary', ['GET'])]
    public function inventorySummary(string $slug): void
    {
        $data = $this->reportService->inventorySummary();
        $this->json(200, $data);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/reports/payouts/summary', ['GET'])]
    public function payoutsSummary(string $slug): void
    {
        $dateFrom = isset($_GET['from']) ? (string) $_GET['from'] : null;
        $dateTo = isset($_GET['to']) ? (string) $_GET['to'] : null;
        $data = $this->reportService->payoutsSummary($dateFrom, $dateTo);
        $this->json(200, $data);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/reports/sales/export', ['GET'])]
    public function exportSalesCsv(string $slug): void
    {
        $dateFrom = isset($_GET['from']) ? (string) $_GET['from'] : null;
        $dateTo = isset($_GET['to']) ? (string) $_GET['to'] : null;
        $csv = $this->reportService->exportSalesCsv($dateFrom, $dateTo);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sales-export.csv"');
        echo $csv;
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/dashboard', ['GET'])]
    public function dashboard(string $slug): void
    {
        $data = $this->reportService->dashboardMetrics();
        $this->json(200, $data);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/reports/quickbooks/sales', ['GET'])]
    public function quickBooksSalesExport(string $slug): void
    {
        $dateFrom = isset($_GET['from']) ? (string) $_GET['from'] : null;
        $dateTo = isset($_GET['to']) ? (string) $_GET['to'] : null;
        $csv = $this->reportService->exportQuickBooksSalesCsv($dateFrom, $dateTo);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="quickbooks-sales.csv"');
        echo $csv;
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/reports/quickbooks/payouts', ['GET'])]
    public function quickBooksPayoutsExport(string $slug): void
    {
        $dateFrom = isset($_GET['from']) ? (string) $_GET['from'] : null;
        $dateTo = isset($_GET['to']) ? (string) $_GET['to'] : null;
        $csv = $this->reportService->exportQuickBooksPayoutsCsv($dateFrom, $dateTo);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="quickbooks-payouts.csv"');
        echo $csv;
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/reports/vendor-mall', ['GET'])]
    public function vendorMallSummary(string $slug): void
    {
        $dateFrom = isset($_GET['from']) ? (string) $_GET['from'] : null;
        $dateTo = isset($_GET['to']) ? (string) $_GET['to'] : null;
        $data = $this->reportService->vendorMallSummary($dateFrom, $dateTo);
        $this->json(200, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
