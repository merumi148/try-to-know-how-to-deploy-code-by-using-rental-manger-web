<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['GET']);

$landlordId = require_landlord_id();

try {
    $db = (new Database())->getMysqliConnection();

    $totalRoomsStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT COUNT(*) AS total
         FROM rooms r
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE b.landlord_id = ?'
    );
    mysqli_bind_and_execute($totalRoomsStmt, 'i', [$landlordId]);
    $totalRooms = mysqli_fetch_one_assoc($totalRoomsStmt);
    $totalRoomsStmt->close();

    $availableRoomsStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT COUNT(*) AS total
         FROM rooms r
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE b.landlord_id = ? AND r.status = ?'
    );
    mysqli_bind_and_execute($availableRoomsStmt, 'is', [$landlordId, 'available']);
    $availableRooms = mysqli_fetch_one_assoc($availableRoomsStmt);
    $availableRoomsStmt->close();

    $occupiedRoomsStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT COUNT(*) AS total
         FROM rooms r
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE b.landlord_id = ? AND r.status = ?'
    );
    mysqli_bind_and_execute($occupiedRoomsStmt, 'is', [$landlordId, 'occupied']);
    $occupiedRooms = mysqli_fetch_one_assoc($occupiedRoomsStmt);
    $occupiedRoomsStmt->close();

    $totalRevenueStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT COALESCE(SUM(p.total_amount), 0) AS total
         FROM payments p
         INNER JOIN contracts c ON c.id = p.contract_id
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE b.landlord_id = ?
           AND p.payment_status = ?'
    );
    mysqli_bind_and_execute($totalRevenueStmt, 'is', [$landlordId, 'paid']);
    $totalRevenue = mysqli_fetch_one_assoc($totalRevenueStmt);
    $totalRevenueStmt->close();

    $currentMonth = date('Y-m');
    $monthlyRevenueStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT COALESCE(SUM(p.total_amount), 0) AS total
         FROM payments p
         INNER JOIN contracts c ON c.id = p.contract_id
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE b.landlord_id = ?
           AND p.payment_status = ?
           AND p.month_year = ?'
    );
    mysqli_bind_and_execute($monthlyRevenueStmt, 'iss', [$landlordId, 'paid', $currentMonth]);
    $monthlyRevenue = mysqli_fetch_one_assoc($monthlyRevenueStmt);
    $monthlyRevenueStmt->close();

    $pendingRequestsStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT COUNT(*) AS total
         FROM viewing_requests vr
         INNER JOIN rooms r ON r.id = vr.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE b.landlord_id = ? AND vr.status = ?'
    );
    mysqli_bind_and_execute($pendingRequestsStmt, 'is', [$landlordId, 'pending']);
    $pendingRequests = mysqli_fetch_one_assoc($pendingRequestsStmt);
    $pendingRequestsStmt->close();

    $totalContractsStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT COUNT(*) AS total
         FROM contracts c
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE b.landlord_id = ?'
    );
    mysqli_bind_and_execute($totalContractsStmt, 'i', [$landlordId]);
    $totalContracts = mysqli_fetch_one_assoc($totalContractsStmt);
    $totalContractsStmt->close();

    $activeContractsStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT COUNT(*) AS total
         FROM contracts c
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE b.landlord_id = ? AND c.status = ?'
    );
    mysqli_bind_and_execute($activeContractsStmt, 'is', [$landlordId, 'active']);
    $activeContracts = mysqli_fetch_one_assoc($activeContractsStmt);
    $activeContractsStmt->close();

    $pendingPaymentsStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT COUNT(*) AS total
         FROM payments p
         INNER JOIN contracts c ON c.id = p.contract_id
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE b.landlord_id = ? AND p.payment_status IN (?, ?)'
    );
    mysqli_bind_and_execute($pendingPaymentsStmt, 'iss', [$landlordId, 'pending', 'unpaid']);
    $pendingPayments = mysqli_fetch_one_assoc($pendingPaymentsStmt);
    $pendingPaymentsStmt->close();

    json_response(true, 'Landlord dashboard statistics fetched successfully', [
        'total_rooms' => (int)($totalRooms['total'] ?? 0),
        'available_rooms' => (int)($availableRooms['total'] ?? 0),
        'rented_rooms' => (int)($occupiedRooms['total'] ?? 0),
        'total_revenue' => (float)($totalRevenue['total'] ?? 0),
        'monthly_revenue' => (float)($monthlyRevenue['total'] ?? 0),
        'pending_viewing_requests' => (int)($pendingRequests['total'] ?? 0),
        'total_contracts' => (int)($totalContracts['total'] ?? 0),
        'active_contracts' => (int)($activeContracts['total'] ?? 0),
        'pending_payments' => (int)($pendingPayments['total'] ?? 0),
    ]);
} catch (Throwable $e) {
    json_response(false, 'Failed to fetch stats', ['error' => $e->getMessage()], 500);
}
