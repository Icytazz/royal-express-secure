<?php
/**
 * Customer page guard.
 *
 * The previous version issued header("Location: ...") without a following
 * exit(), so the redirect was advisory only: PHP carried on and emitted the
 * protected page body beneath it. requireRole() terminates on refusal.
 */

require_once __DIR__ . '/server/inc/authorize.php';

requireRole('customer', 'Admin/login.php');

$getall      = getAllcustomerById($_SESSION['customer']);
$cus         = mysqli_fetch_assoc($getall);
$customer_id = $cus['customer_id'];
