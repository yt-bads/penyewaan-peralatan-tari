<?php
// includes/auth.php

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: /penyewaan-peralatan-tari/login.php?error=unauthorized');
        exit();
    }
}

function requireRole($allowed_roles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'], (array)$allowed_roles)) {
        header('Location: /penyewaan-peralatan-tari/403.php');
        exit();
    }
}

function getCurrentUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'nama' => $_SESSION['user_nama']  ?? null,
        'role' => $_SESSION['user_role']  ?? null,
    ];
}

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}
