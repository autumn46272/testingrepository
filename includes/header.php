<?php
// Ensure this file is included, not accessed directly
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Placeholder for Auth check inclusion in parent files
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student DB Admin</title>
    <!-- Open Sans Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for Icons (Optional, using CDN for simplicity) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>assets/css/style.css">
</head>

<body>
    <div class="wrapper">
        <!-- Sidebar and Top Bar will be separated to allow greater flexibility -->