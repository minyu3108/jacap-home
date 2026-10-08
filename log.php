<?php
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/board.php';
board_guard('log');

$cfg = ['label' => S('m_log'), 'page' => 'log', 'menu' => 3, 'bg' => 'log', 'file' => 'log.php', 'group' => 'category'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) { board_detail('logs', $id, $cfg); } else { board_list('logs', $cfg); }
