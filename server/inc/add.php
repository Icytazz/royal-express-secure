<?php

function insertImagetoGallery($img)
{
	include 'connection.php';

	$sql  = "INSERT INTO gallery(gallery_image) VALUES(?)";
	$stmt = mysqli_prepare($con, $sql);
	mysqli_stmt_bind_param($stmt, "s", $img);
	$ok = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);

	return $ok;
}

function addBranch($data)
{
	include 'connection.php';

	$branch_name = $data['branch_name'] ?? '';

	$sql  = "INSERT INTO branch(branch_name, is_deleted) VALUES(?, 0)";
	$stmt = mysqli_prepare($con, $sql);
	mysqli_stmt_bind_param($stmt, "s", $branch_name);
	$ok = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);

	return $ok;
}

function addArea($data)
{
	include 'connection.php';

	$area_name = $data['area_name'] ?? '';

	$count = checkAreaByName($area_name);

	if ($count == 0) {

		$sql  = "INSERT INTO area(area_name, is_deleted) VALUES(?, 0)";
		$stmt = mysqli_prepare($con, $sql);
		mysqli_stmt_bind_param($stmt, "s", $area_name);
		$ok = mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		return $ok;
	} else {
		echo json_encode($count);
	}
}

function addPrice($data)
{
	include 'connection.php';

	$start_area = $data['start_area'] ?? '';
	$end_area   = $data['end_area']   ?? '';
	$price      = $data['price']      ?? '';

	$count = checkPrice($start_area, $end_area);

	if ($count == 0) {

		$sql  = "INSERT INTO price_table(start_area, end_area, price, is_deleted, date_updated)
		         VALUES(?, ?, ?, 0, now())";
		$stmt = mysqli_prepare($con, $sql);
		mysqli_stmt_bind_param($stmt, "sss", $start_area, $end_area, $price);
		$ok = mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		return $ok;
	} else {
		echo json_encode($count);
	}
}

function addRequest($data)
{
	include 'connection.php';

	$customer_id   = $data['customer_id']   ?? '';
	$sender_phone  = $data['sender_phone']  ?? '';
	$weight        = $data['weight']        ?? '';
	$send_location = $data['send_location'] ?? '';
	$end_location  = $data['end_location']  ?? '';
	$total_fee     = $data['total_fee']     ?? '';
	$res_phone     = $data['res_phone']     ?? '';
	$red_address   = $data['red_address']   ?? '';
	$res_name      = $data['res_name']      ?? '';

	$sql = "INSERT INTO request(customer_id, sender_phone, weight, send_location,
	                            end_location, total_fee, res_phone, red_address,
	                            is_deleted, date_updated, tracking_status, res_name)
	        VALUES(?, ?, ?, ?, ?, ?, ?, ?, 0, now(), 1, ?)";
	$stmt = mysqli_prepare($con, $sql);
	mysqli_stmt_bind_param($stmt, "sssssssss", $customer_id, $sender_phone, $weight,
		$send_location, $end_location, $total_fee, $res_phone, $red_address, $res_name);
	$ok = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);

	return $ok;
}

function addEmployee($data)
{
	include 'connection.php';

	$name      = $data['name']      ?? '';
	$email     = $data['email']     ?? '';
	$phone     = $data['phone']     ?? '';
	$nic       = $data['nic']       ?? '';
	$address   = $data['address']   ?? '';
	$gender    = $data['gender']    ?? '';
	$password  = $data['password']  ?? '';
	$branch_id = $data['branch_id'] ?? '';

	$count = checkemployeetByEmail($email);

	if ($count == 0) {

		$password = password_hash($password, PASSWORD_DEFAULT);

		require_once __DIR__ . '/logger.php';
		logSecurityEvent('account.created',
			['type' => 'employee', 'identity' => $email]);

		$sql = "INSERT INTO employee(name, email, phone, nic, address, gender,
		                             password, is_deleted, branch_id)
		        VALUES(?, ?, ?, ?, ?, ?, ?, 0, ?)";
		$stmt = mysqli_prepare($con, $sql);
		mysqli_stmt_bind_param($stmt, "ssssssss", $name, $email, $phone, $nic,
			$address, $gender, $password, $branch_id);
		$ok = mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);

		return $ok;
	} else {
		echo json_encode($count);
	}
}

//contact
function addMessage($data)
{
	include 'connection.php';

	$name    = $data['name']    ?? '';
	$email   = $data['email']   ?? '';
	$subject = $data['subject'] ?? '';
	$message = $data['message'] ?? '';

	$sql  = "INSERT INTO contact(name, email, subject, message, date_updated)
	         VALUES(?, ?, ?, ?, now())";
	$stmt = mysqli_prepare($con, $sql);
	mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $subject, $message);
	$ok = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);

	return $ok;
}

function createCustomer($data)
{
	include 'connection.php';

	$name     = $data['name']     ?? '';
	$email    = $data['email']    ?? '';
	$phone    = $data['phone']    ?? '';
	$nic      = $data['nic']      ?? '';
	$address  = $data['address']  ?? '';
	$gender   = $data['gender']   ?? '';
	$password = $data['password'] ?? '';

	$password = password_hash($password, PASSWORD_DEFAULT);

	$sql = "INSERT INTO customer(name, email, phone, nic, address, gender,
	                             password, is_deleted)
	        VALUES(?, ?, ?, ?, ?, ?, ?, 0)";
	$stmt = mysqli_prepare($con, $sql);
	mysqli_stmt_bind_param($stmt, "sssssss", $name, $email, $phone, $nic,
		$address, $gender, $password);
	$ok = mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);

	return $ok;
}
