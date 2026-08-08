// ============================================
// VERIFICAR COMPRAS PENDIENTES DE OTROS USUARIOS
// ============================================
$stmtPending = $pdo->prepare("
    SELECT seats FROM purchases 
    WHERE showtime_id = ? AND status = 'pending' AND user_id != ?
    FOR UPDATE
");
$stmtPending->execute([$showtimeId, $_SESSION['user_id']]);
$pendingPurchases = $stmtPending->fetchAll();

foreach ($pendingPurchases as $pending) {
    $pendingSeats = explode(',', $pending['seats']);
    $conflictSeats = array_intersect($seatsArray, $pendingSeats);
    if (!empty($conflictSeats)) {
        throw new Exception("Los siguientes asientos están siendo reservados por otro usuario: " . implode(', ', $conflictSeats));
    }
}