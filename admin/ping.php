<?php
/** 글을 오래 쓰는 동안 로그인이 풀리지 않게 주기적으로 호출됩니다 */
require __DIR__ . '/../inc/core.php';
json_out(['ok' => is_admin()]);
