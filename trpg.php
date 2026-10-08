<?php
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/board.php';
board_guard('trpg');

$cfg = ['label' => S('m_trpg'), 'page' => 'trpg', 'menu' => 5, 'bg' => 'trpg', 'file' => 'trpg.php', 'group' => 'trpg_system', 'trpg' => true];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) { board_detail('trpg', $id, $cfg); } else { board_list('trpg', $cfg); }
