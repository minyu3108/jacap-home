<?php
/** 관리자 로그인/로그아웃 (메인 홈의 로그인 카드에서 사용) */
require __DIR__ . '/inc/core.php';

$home = url('home.php');

if (isset($_GET['out'])) {
    $t = isset($_GET['t']) ? (string)$_GET['t'] : '';
    if (!empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t)) {
        unset($_SESSION['admin_id'], $_SESSION['admin_name']);
        session_regenerate_id(true);
    }
    redirect($home);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect($home); }
if (!csrf_ok()) { redirect($home . '?loginfail=1'); }

if (attempts_count('login', 600) >= 6) { redirect($home . '?locked=1'); }

$u = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
$p = isset($_POST['password']) ? (string)$_POST['password'] : '';
$a = row('SELECT * FROM admins WHERE username=?', [$u]);

if ($a && password_verify($p, $a['pass_hash'])) {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$a['id'];
    $_SESSION['admin_name'] = $a['display_name'] !== '' ? $a['display_name'] : $a['username'];
    redirect($home);
}
attempts_add('login');
usleep(600000);
redirect($home . '?loginfail=1');
