<?php
// includes/auth.php
session_start();

function require_role($role) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $role) {
        header("Location: ../../index.php");
        exit();
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function has_role($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}
?>