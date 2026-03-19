<?php
// PDF report download (scope-aware)
// Uses simple HTML-to-PDF via browser print (no FPDF dependency needed initially)
// If FPDF is installed in vendor/fpdf/, the native PDF generation will be used.
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
if (
    ($_SESSION['user_type'] ?? '') !== 'staff' ||
    !(
        currentStaffHasPermission('reports.view_all') ||
        currentStaffHasPermission('reports.view_own') ||
        currentStaffHasPermission('reports.view')
    )
) {
    setFlash('error', 'Unauthorized access.');
    $target = isOwnerAdminSession() ? '/admin/dashboard' : '/staff/dashboard';
    header('Location: ' . APP_URL . $target);
    exit;
}

$pdo      = getDB();
$staffId  = currentStaffId();
$canViewAllReports = currentStaffCanViewOrganizationReports();
$dateFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo   = $_GET['to']   ?? date('Y-m-d');

// Summary stats
$summarySql = "
    SELECT COUNT(*) AS total,
           SUM(status = 'solved') AS solved,
           SUM(status != 'solved') AS open_count,
           ROUND(AVG(CASE WHEN solved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, solved_at) END),1) AS avg_hours
    FROM tickets WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
";
$summaryParams = [$dateFrom, $dateTo];
if (!$canViewAllReports) {
    $summarySql .= " AND assigned_to = ?";
    $summaryParams[] = $staffId;
}
$stmt = $pdo->prepare($summarySql);
$stmt->execute($summaryParams);
$summary = $stmt->fetch();

// Staff performance
if ($canViewAllReports) {
    $stmt = $pdo->prepare("
        SELECT s.name, s.designation, s.role,
               COUNT(t.id) AS assigned_count,
               SUM(t.status = 'solved') AS solved_count,
               ROUND(AVG(CASE WHEN t.solved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.solved_at) END),1) AS avg_hours
        FROM it_staff s
        LEFT JOIN tickets t ON t.assigned_to = s.id AND t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        WHERE s.is_active = 1 AND s.role != 'admin' GROUP BY s.id ORDER BY solved_count DESC
    ");
    $stmt->execute([$dateFrom, $dateTo]);
} else {
    $stmt = $pdo->prepare("
        SELECT s.name, s.designation, s.role,
               COUNT(t.id) AS assigned_count,
               SUM(t.status = 'solved') AS solved_count,
               ROUND(AVG(CASE WHEN t.solved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, t.created_at, t.solved_at) END),1) AS avg_hours
        FROM it_staff s
        LEFT JOIN tickets t ON t.assigned_to = s.id AND t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
        WHERE s.id = ? GROUP BY s.id
    ");
    $stmt->execute([$dateFrom, $dateTo, $staffId]);
}
$staffPerf = $stmt->fetchAll();

// Category breakdown
$categorySql = "
    SELECT COALESCE(pc.name,'Other') AS category, COUNT(*) AS cnt
    FROM tickets t LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
    WHERE t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
";
$categoryParams = [$dateFrom, $dateTo];
if (!$canViewAllReports) {
    $categorySql .= " AND t.assigned_to = ?";
    $categoryParams[] = $staffId;
}
$categorySql .= " GROUP BY pc.id ORDER BY cnt DESC";
$stmt = $pdo->prepare($categorySql);
$stmt->execute($categoryParams);
$catBreakdown = $stmt->fetchAll();

// Tickets
$ticketSql = "
    SELECT t.ticket_number, u.name AS user_name, u.department,
           COALESCE(pc.name,'Custom') AS category, t.priority, t.status,
           s.name AS assigned_to, t.created_at, t.solved_at,
           TIMESTAMPDIFF(HOUR, t.created_at, t.solved_at) AS hours, f.rating
    FROM tickets t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN problem_categories pc ON t.problem_category_id = pc.id
    LEFT JOIN it_staff s ON t.assigned_to = s.id
    LEFT JOIN feedback f ON f.ticket_id = t.id
    WHERE t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
";
$ticketParams = [$dateFrom, $dateTo];
if (!$canViewAllReports) {
    $ticketSql .= " AND t.assigned_to = ?";
    $ticketParams[] = $staffId;
}
$ticketSql .= " ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($ticketSql);
$stmt->execute($ticketParams);
$tickets = $stmt->fetchAll();

$periodLabel = formatDate($dateFrom, 'd M Y') . ' to ' . formatDate($dateTo, 'd M Y');
$scopeLabel = $canViewAllReports ? 'Organization Scope' : 'My Scope';

// Check if FPDF is available
$fpdfPath = VENDOR_PATH . '/fpdf/fpdf.php';
$useFPDF  = file_exists($fpdfPath);

if ($useFPDF) {
    // ── Native FPDF PDF generation ──────────────────────────
    require_once $fpdfPath;

    class SVCET_PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial','B',16);
            $this->Cell(0, 10, ORG_NAME . ' Complaint Management', 0, 1, 'C');
            $this->SetFont('Arial','',10);
            $this->Cell(0, 6, 'Complaint Management Report', 0, 1, 'C');
            $this->Ln(4);
            $this->Line(10, $this->GetY(), $this->GetPageWidth()-10, $this->GetY());
            $this->Ln(4);
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->Cell(0, 10, 'Page '.$this->PageNo().'/{nb}  |  Generated: '.date('d M Y, h:i A'), 0, 0, 'C');
        }
    }

    $pdf = new SVCET_PDF('L','mm','A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Period
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(0, 8, 'Report Period: ' . $periodLabel . ' | ' . $scopeLabel, 0, 1);
    $pdf->Ln(2);

    // Summary
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 7, 'Summary', 0, 1);
    $pdf->SetFont('Arial','',9);
    $pdf->Cell(50, 6, 'Total Tickets: ' . (int)$summary['total'], 0, 0);
    $pdf->Cell(50, 6, 'Solved: ' . (int)$summary['solved'], 0, 0);
    $pdf->Cell(50, 6, 'Open: ' . (int)$summary['open_count'], 0, 0);
    $pdf->Cell(60, 6, 'Avg Resolution: ' . ($summary['avg_hours'] ?? 'N/A') . ' hrs', 0, 1);
    $pdf->Ln(4);

    // Staff Performance Table
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 7, $canViewAllReports ? 'Staff Performance' : 'My Performance', 0, 1);
    $pdf->SetFont('Arial','B',8);
    $pdf->SetFillColor(26, 58, 92);
    $pdf->SetTextColor(255);
    $w = [60, 50, 40, 30, 30, 30];
    $headers = ['Staff Name', 'Designation', 'Role', 'Assigned', 'Solved', 'Avg Hrs'];
    foreach ($headers as $i => $h) $pdf->Cell($w[$i], 6, $h, 1, 0, 'C', true);
    $pdf->Ln();
    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial','',8);
    $fill = false;
    foreach ($staffPerf as $sp) {
        $pdf->SetFillColor(240, 244, 248);
        $pdf->Cell($w[0], 5, $sp['name'], 1, 0, 'L', $fill);
        $pdf->Cell($w[1], 5, $sp['designation'], 1, 0, 'L', $fill);
        $pdf->Cell($w[2], 5, roleLabel($sp['role']), 1, 0, 'C', $fill);
        $pdf->Cell($w[3], 5, (int)$sp['assigned_count'], 1, 0, 'C', $fill);
        $pdf->Cell($w[4], 5, (int)$sp['solved_count'], 1, 0, 'C', $fill);
        $pdf->Cell($w[5], 5, $sp['avg_hours'] ?? '-', 1, 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }
    $pdf->Ln(6);

    // Ticket Details
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(0, 7, 'Ticket Details (' . count($tickets) . ')', 0, 1);
    $pdf->SetFont('Arial','B',7);
    $pdf->SetFillColor(26, 58, 92);
    $pdf->SetTextColor(255);
    $tw = [28, 30, 22, 30, 18, 20, 30, 28, 28, 16, 14];
    $th = ['Ticket #','User','Dept','Category','Prio','Status','Assigned','Created','Solved','Hrs','Rate'];
    foreach ($th as $i => $h) $pdf->Cell($tw[$i], 5, $h, 1, 0, 'C', true);
    $pdf->Ln();
    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial','',7);
    $fill = false;
    foreach ($tickets as $t) {
        $pdf->SetFillColor(240, 244, 248);
        $pdf->Cell($tw[0], 4, $t['ticket_number'], 1, 0, 'L', $fill);
        $pdf->Cell($tw[1], 4, substr($t['user_name'],0,18), 1, 0, 'L', $fill);
        $pdf->Cell($tw[2], 4, substr($t['department'] ?? '',0,14), 1, 0, 'L', $fill);
        $pdf->Cell($tw[3], 4, substr($t['category'],0,18), 1, 0, 'L', $fill);
        $pdf->Cell($tw[4], 4, ucfirst($t['priority']), 1, 0, 'C', $fill);
        $pdf->Cell($tw[5], 4, ucfirst($t['status']), 1, 0, 'C', $fill);
        $pdf->Cell($tw[6], 4, substr($t['assigned_to'] ?? '-',0,18), 1, 0, 'L', $fill);
        $pdf->Cell($tw[7], 4, date('d M H:i', strtotime($t['created_at'])), 1, 0, 'C', $fill);
        $pdf->Cell($tw[8], 4, $t['solved_at'] ? date('d M H:i', strtotime($t['solved_at'])) : '-', 1, 0, 'C', $fill);
        $pdf->Cell($tw[9], 4, $t['hours'] !== null ? $t['hours'] : '-', 1, 0, 'C', $fill);
        $pdf->Cell($tw[10], 4, $t['rating'] ?? '-', 1, 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    $pdfFilePrefix = $canViewAllReports ? 'SVCET_Complaint_Report_' : 'SVCET_My_Complaint_Report_';
    $pdf->Output('D', $pdfFilePrefix . date('Ymd') . '.pdf');
    exit;

} else {
    // ── Fallback: Print-friendly HTML (user can Ctrl+P → Save as PDF) ──
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SVCET Complaint Report — <?= h($periodLabel) ?> (<?= h($scopeLabel) ?>)</title>
<style>
    @media print { @page { size: landscape; margin: 10mm; } .no-print { display:none !important; } }
    body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
    h1 { font-size: 20px; margin: 0; color: #1a3a5c; }
    h2 { font-size: 14px; margin: 20px 0 5px; color: #1a3a5c; border-bottom: 2px solid #1a3a5c; padding-bottom: 3px; }
    .subtitle { font-size: 12px; color: #666; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0 20px; }
    th { background: #1a3a5c; color: #fff; padding: 5px 8px; font-size: 10px; text-align: center; }
    td { border: 1px solid #ddd; padding: 4px 6px; font-size: 10px; }
    tr:nth-child(even) { background: #f8f9fa; }
    .summary-box { display: inline-block; padding: 10px 20px; margin: 5px 10px 5px 0; background: #f0f4f8; border-left: 3px solid #1a3a5c; }
    .summary-num { font-size: 22px; font-weight: bold; color: #1a3a5c; }
    .summary-label { font-size: 10px; color: #666; }
    .btn-print { background: #1a3a5c; color: #fff; border: none; padding: 8px 20px; cursor: pointer; border-radius: 5px; font-size: 13px; }
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:15px;">
    <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
    <a href="<?= APP_URL ?>/staff/reports" style="margin-left:10px;font-size:13px;">Back to Reports</a>
</div>

<h1><?= h(ORG_NAME) ?> — Complaint Management Report</h1>
<p class="subtitle">Report Period: <strong><?= h($periodLabel) ?></strong> | Scope: <strong><?= h($scopeLabel) ?></strong> | Generated: <?= date('d M Y, h:i A') ?></p>

<h2>Summary</h2>
<div>
    <div class="summary-box"><div class="summary-num"><?= (int)$summary['total'] ?></div><div class="summary-label">Total Tickets</div></div>
    <div class="summary-box"><div class="summary-num"><?= (int)$summary['solved'] ?></div><div class="summary-label">Solved</div></div>
    <div class="summary-box"><div class="summary-num"><?= (int)$summary['open_count'] ?></div><div class="summary-label">Open</div></div>
    <div class="summary-box"><div class="summary-num"><?= $summary['avg_hours'] ?? '—' ?></div><div class="summary-label">Avg Hours</div></div>
</div>

<h2><?= $canViewAllReports ? 'Staff Performance' : 'My Performance' ?></h2>
<table>
    <tr><th>Staff Name</th><th>Designation</th><th>Role</th><th>Assigned</th><th>Solved</th><th>Avg Hours</th></tr>
    <?php foreach ($staffPerf as $sp): ?>
    <tr>
        <td><?= h($sp['name']) ?></td><td><?= h($sp['designation']) ?></td><td><?= roleLabel($sp['role']) ?></td>
        <td style="text-align:center;"><?= (int)$sp['assigned_count'] ?></td>
        <td style="text-align:center;"><?= (int)$sp['solved_count'] ?></td>
        <td style="text-align:center;"><?= $sp['avg_hours'] ?? '—' ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>Category Breakdown</h2>
<table style="width:50%;">
    <tr><th>Category</th><th>Count</th><th>%</th></tr>
    <?php $totalCat = array_sum(array_column($catBreakdown, 'cnt'));
    foreach ($catBreakdown as $cb): $pct = $totalCat > 0 ? round(($cb['cnt']/$totalCat)*100) : 0; ?>
    <tr><td><?= h($cb['category']) ?></td><td style="text-align:center;"><?= $cb['cnt'] ?></td><td style="text-align:center;"><?= $pct ?>%</td></tr>
    <?php endforeach; ?>
</table>

<h2>Ticket Details (<?= count($tickets) ?>)</h2>
<table>
    <tr><th>Ticket #</th><th>Raised By</th><th>Dept</th><th>Category</th><th>Priority</th><th>Status</th><th>Assigned To</th><th>Created</th><th>Solved</th><th>Hours</th><th>Rating</th></tr>
    <?php foreach ($tickets as $t): ?>
    <tr>
        <td><?= h($t['ticket_number']) ?></td><td><?= h($t['user_name']) ?></td><td><?= h($t['department'] ?? '') ?></td>
        <td><?= h($t['category']) ?></td><td><?= ucfirst(h($t['priority'])) ?></td><td><?= ucfirst(h($t['status'])) ?></td>
        <td><?= h($t['assigned_to'] ?? '—') ?></td>
        <td><?= formatDate($t['created_at'],'d M, H:i') ?></td>
        <td><?= $t['solved_at'] ? formatDate($t['solved_at'],'d M, H:i') : '—' ?></td>
        <td style="text-align:center;"><?= $t['hours'] !== null ? $t['hours'] : '—' ?></td>
        <td style="text-align:center;"><?= $t['rating'] ?? '—' ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<p style="color:#999; font-size:10px; margin-top:30px;">Generated by <?= APP_NAME ?> on <?= date('d M Y, h:i A') ?></p>
</body>
</html>
<?php
}
