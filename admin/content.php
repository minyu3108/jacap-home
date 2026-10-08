<?php
require __DIR__ . '/_boot.php';

$E = entities();
$t = isset($_GET['t']) ? (string)$_GET['t'] : '';
if (!isset($E[$t])) { redirect(url('admin/')); }
$e = $E[$t];
$readonly = !empty($e['readonly']);
$act = isset($_GET['a']) ? (string)$_GET['a'] : 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sub = (isset($_GET['sub']) && isset($e['edit_groups']) && isset($e['edit_groups'][$_GET['sub']])) ? (string)$_GET['sub'] : '';
$ret = (isset($_GET['ret']) && preg_match('/^[a-z]+\.php(\?[a-z0-9=&]+)?$/', (string)$_GET['ret'])) ? (string)$_GET['ret'] : '';
$self = url('admin/content.php') . '?t=' . urlencode($t);
$back = $ret !== '' ? url($ret) : $self;
$retq = $ret !== '' ? '&ret=' . urlencode($ret) : '';
$subq = $sub !== '' ? '&sub=' . urlencode($sub) : '';

/** $group === '' 는 "본편(main)" 필드 (group 지정이 없거나 'main'인 것들) */
function fields_for_group($fields, $group) {
    return array_values(array_filter($fields, function ($f) use ($group) {
        $fg = isset($f['group']) ? $f['group'] : '';
        return $group === '' ? ($fg === '' || $fg === 'main') : $fg === $group;
    }));
}

/* ---------- 삭제 ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'delete') {
    require_post_csrf();
    q('DELETE FROM `' . $t . '` WHERE id=?', [(int)post('id')]);
    flash('삭제했어요.');
    redirect($self);
}

/* ---------- 저장 ---------- */
$row = null;
if ($act === 'edit' && $id) {
    $row = row('SELECT * FROM `' . $t . '` WHERE id=?', [$id]);
    if (!$row) { flash('찾을 수 없는 항목이에요.', 'err'); redirect($self); }
}
$formRow = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'save' && !$readonly) {
    require_post_csrf();
    if (!$row) { $sub = ''; $subq = ''; } // 새 글에는 하위 편집이 없음
    $errs = [];
    $data = [];
    foreach (fields_for_group($e['fields'], $sub) as $f) {
        if (!empty($f['hide'])) { $data[$f['n']] = $row ? (string)$row[$f['n']] : (string)(isset($f['def']) ? $f['def'] : '0'); continue; }
        $old = $row ? $row[$f['n']] : null;
        $data[$f['n']] = collect_field($f, $old, $errs);
    }
    if ($sub === '' && trim((string)$data[$e['title']]) === '') { $errs[] = '제목(이름)을 입력해 주세요.'; }
    if ($t === 'trpg') { $data['raw_html'] = sanitize_doc($data['raw_html']); }
    if ($t === 'trpg') {
        $rawBody = isset($_POST['v_body']) ? (string)$_POST['v_body'] : '';
        $ext = '';
        if (preg_match_all('#<style[^>]*>(.*?)</style>#is', $rawBody, $mm)) { $ext = implode("\n", $mm[1]); }
        $data['body_css'] = clean_css(($data['body_css'] !== '' ? $data['body_css'] . "\n" : '') . $ext);
    }
    if ($t === 'trpg' && isset($data['t_convert']) && on1($data['t_convert'])) { $data['body'] = normalize_log($data['body']); }
    if ($t === 'trpg' && $data['room_url'] !== '') {
        $ru = safe_url($data['room_url']);
        if ($ru === '' || !preg_match('#^https?://#i', $ru)) { $errs[] = '롤20 방 링크는 https:// 로 시작하는 주소여야 해요.'; }
    }

    if ($sub === '' && !empty($e['protect'])) {
        $vis = post('visibility');
        if (!in_array($vis, ['public', 'admin', 'password'], true)) $vis = 'public';
        $pw = isset($_POST['new_pw']) ? (string)$_POST['new_pw'] : '';
        if ($vis === 'password') {
            if ($pw !== '') { $data['pw_hash'] = password_hash($pw, PASSWORD_DEFAULT); }
            elseif ($row && $row['pw_hash'] !== '') { $data['pw_hash'] = $row['pw_hash']; }
            else { $errs[] = '비밀글은 비밀번호를 입력해 주세요.'; $data['pw_hash'] = ''; }
        } else {
            $data['pw_hash'] = '';
        }
        $data['visibility'] = $vis;

        $d = post('created_date');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            if ($row && substr((string)$row['created_at'], 0, 10) === $d) { $data['created_at'] = $row['created_at']; }
            else { $data['created_at'] = $d . ' 12:00:00'; }
        } else {
            $data['created_at'] = $row ? $row['created_at'] : now();
        }
    } elseif (!$row) {
        $data['created_at'] = now();
    }

    if (!$errs) {
        if ($row) { update_row($t, $row['id'], $data); }
        else { $newId = insert_row($t, $data); }
        flash('저장했어요.');
        redirect($sub !== '' ? ($self . '&a=edit&id=' . (int)$row['id'] . $subq . $retq) : $back);
    }
    foreach ($errs as $er) { flash($er, 'err'); }
    $formRow = array_merge($row ? $row : [], $data);
    $act = $row ? 'edit' : 'new';
}

/* ---------- 화면 ---------- */
if ($act === 'new' || $act === 'edit') {
    if ($readonly) { redirect($self); }
    if (!$row) { $sub = ''; $subq = ''; }
    $isNew = ($act === 'new');
    $groupLabel = ($sub !== '' && isset($e['edit_groups'][$sub])) ? $e['edit_groups'][$sub] : '';
    $pageTitle = $isNew ? ('새로 만들기 · ' . $e['label']) : ($groupLabel !== '' ? ($groupLabel . ' · ' . (string)$row[$e['title']]) : ('수정 · ' . $e['label']));
    editor_head($pageTitle, $back);
    ?>
<p><a class="back" href="<?= h($back) ?>">&#8592; <?= $ret !== '' ? '돌아가기' : '목록으로' ?></a>
<?php if ($groupLabel !== ''): ?> &nbsp;·&nbsp; <a class="back" href="<?= h($self . '&a=edit&id=' . (int)$id . $retq) ?>">&#8592; <?= h($e['label']) ?> 편집으로 돌아가기</a><?php endif; ?></p>
<?php if ($t === 'stickers'): ?><p class="help">스티커는 여기서 이미지·크기·기울기를 정하고, <b>기본 위치는 메인 홈에서 관리자로 로그인한 상태로 마우스로 끌어서</b> 옮긴 뒤 로그인 카드의 "위젯·스티커 위치 저장"을 눌러 정해요. 방문자도 스티커를 옮겨 볼 수 있지만, 다시 접속하면 이 기본 위치로 돌아가요.</p><?php endif; ?>
<?php if ($groupLabel !== ''): ?><p class="help"><b><?= h($groupLabel) ?></b>를 편집하고 있어요. 여기서 저장하면 이 항목의 다른 내용은 바뀌지 않아요.</p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="card form" action="<?= h($self . ($isNew ? '&a=new' : '&a=edit&id=' . (int)$id) . $subq . $retq) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="do" value="save">
  <?php foreach (fields_for_group($e['fields'], $sub) as $f):
      if (!empty($f['hide'])) continue;
      $val = ($formRow && isset($formRow[$f['n']])) ? $formRow[$f['n']] : '';
      if (!$formRow) {
          if (isset($f['def'])) { $val = $f['def']; }
          if ($f['t'] === 'select') { $o = field_opts($f); reset($o); $val = (string)key($o); }
          if ($f['t'] === 'color') { $val = ''; }
      }
      echo render_field($f, $val);
  endforeach; ?>

  <?php if ($sub === '' && !empty($e['protect'])):
      $vis = $formRow && isset($formRow['visibility']) ? $formRow['visibility'] : 'public';
      $cd = ($formRow && !empty($formRow['created_at'])) ? substr((string)$formRow['created_at'], 0, 10) : date('Y-m-d'); ?>
  <div class="fld">
    <label for="f_visibility">공개 설정</label>
    <select id="f_visibility" name="visibility">
      <option value="public" <?= $vis === 'public' ? 'selected' : '' ?>>전체 공개</option>
      <option value="password" <?= $vis === 'password' ? 'selected' : '' ?>>비밀번호 보호글 (비밀번호를 아는 방문자만)</option>
      <option value="admin" <?= $vis === 'admin' ? 'selected' : '' ?>>관리자만 보기</option>
    </select>
  </div>
  <div class="fld">
    <label for="f_new_pw">비밀번호 (비밀번호 보호글일 때)</label>
    <input type="text" id="f_new_pw" name="new_pw" autocomplete="off" placeholder="<?= ($formRow && !empty($formRow['pw_hash'])) ? '비워두면 기존 비밀번호를 유지해요' : '방문자가 입력할 비밀번호' ?>">
  </div>
  <div class="fld">
    <label for="f_created_date">작성일</label>
    <input type="date" id="f_created_date" name="created_date" value="<?= h($cd) ?>">
    <small class="help">예전에 쓴 글을 옮길 때는 원래 날짜로 바꿔주세요. 목록 정렬 기준이 돼요.</small>
  </div>
  <?php endif; ?>

  <?php if ($sub === '' && !$isNew && isset($e['edit_groups'])): ?>
  <div class="fld">
    <label>하위 편집</label>
    <div class="subedit-links">
      <?php foreach ($e['edit_groups'] as $gk => $gl): ?>
        <a class="btn ghost sm" data-nopjax href="<?= h($self . '&a=edit&id=' . (int)$id . '&sub=' . urlencode($gk) . $retq) ?>"><?= h($gl) ?> →</a>
      <?php endforeach; ?>
    </div>
    <small class="help">먼저 이 화면에서 저장한 뒤에 들어가면 더 안전해요.</small>
  </div>
  <?php endif; ?>

  <div class="actions">
    <button class="btn" type="submit">저장하기</button>
    <a class="btn ghost" href="<?= h($back) ?>">취소</a>
  </div>
</form>
<?php
    editor_foot();
    exit;
}

/* ---------- 목록 ---------- */
$items = rows('SELECT * FROM `' . $t . '` ORDER BY ' . $e['order']);
$protect = !empty($e['protect']);
$visCount = ['public' => 0, 'password' => 0, 'admin' => 0];
if ($protect) { foreach ($items as $r0) { $v0 = isset($r0['visibility']) ? $r0['visibility'] : 'public'; if (isset($visCount[$v0])) $visCount[$v0]++; } }
$visLabel = ['public' => '공개', 'password' => '비밀번호', 'admin' => '관리자만'];
$frontPage = ['profiles' => 'profile.php', 'running' => 'running.php', 'timeline' => 'timeline.php', 'rp' => 'rp.php', 'side' => 'side.php', 'gallery' => 'gallery.php', 'logs' => 'log.php', 'aus' => 'au.php', 'trpg' => 'trpg.php'];
$newBtn = $readonly ? '' : '<a class="btn" href="' . h($self) . '&amp;a=new">' . admin_icon('plus', 16) . '새로 만들기</a>';
admin_head($e['label'], 'ent:' . $t, ['sub' => '글 ' . count($items) . '개', 'actions' => $newBtn]);
?>
<?php if (!$items): ?>
  <div class="card"><p class="help">아직 아무것도 없어요.<?= $readonly ? '' : ' 오른쪽 위의 "새로 만들기"를 눌러 시작해 보세요.' ?></p></div>
<?php else: ?>
<div class="tools" data-listtools>
  <label class="sbox"><?= admin_icon('search', 16) ?><input type="search" placeholder="제목으로 검색" autocomplete="off"></label>
  <?php if ($protect): ?>
  <div class="chips">
    <button type="button" class="chip on" data-vis="all">전체<small><?= count($items) ?></small></button>
    <?php foreach ($visLabel as $vk => $vl): if ($visCount[$vk] > 0): ?>
      <button type="button" class="chip" data-vis="<?= h($vk) ?>"><?= h($vl) ?><small><?= (int)$visCount[$vk] ?></small></button>
    <?php endif; endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<div class="card tbl-wrap">
<table class="tbl">
  <thead><tr><th class="th"></th><th>제목</th><?php if ($protect): ?><th>공개</th><th>작성일</th><?php endif; ?><th></th></tr></thead>
  <tbody>
  <?php foreach ($items as $r):
      $subTxt = isset($e['sub']) ? (string)$r[$e['sub']] : '';
      if (isset($e['sub']) && $e['sub'] === 'side') { $subTxt = ($subTxt === '2') ? S('char2_name', 'B') : S('char1_name', 'A'); }
      if (isset($e['sub']) && $t === 'voices' && $e['sub'] === 'profile_id') { $pn = row('SELECT name FROM profiles WHERE id=?', [(int)$subTxt]); $subTxt = $pn ? $pn['name'] : '(삭제된 프로필)'; }
      if ($t === 'running' && $e['sub'] === 'character') { $subTxt = ($subTxt === '2') ? S('char2_name', 'B') : S('char1_name', 'A'); }
      $ttl = cut(strip_tags((string)$r[$e['title']]), 60);
      $vis = isset($r['visibility']) ? $r['visibility'] : 'public';
      $thumb = admin_thumb($e, $r);
      ?>
    <tr data-t="<?= h(mb_strtolower($ttl . ' ' . strip_tags($subTxt))) ?>" data-v="<?= h($vis) ?>">
      <td class="th"><span class="thumb"><?php if ($thumb !== ''): ?><img loading="lazy" src="<?= h($thumb) ?>" alt=""><?php else: ?><?= admin_icon('image', 18) ?><?php endif; ?></span></td>
      <td class="t"><b><?= h($ttl) ?></b><?php if (trim(strip_tags($subTxt)) !== ''): ?><small><?= h(cut(strip_tags($subTxt), 50)) ?></small><?php endif; ?></td>
      <?php if ($protect): ?>
        <td><span class="pill <?= h($vis) ?>"><?= h(isset($visLabel[$vis]) ? $visLabel[$vis] : $vis) ?></span></td>
        <td class="d"><?= h(fmt_date($r['created_at'])) ?></td>
      <?php endif; ?>
      <td class="a">
        <?php if (isset($frontPage[$t])): ?><a class="btn sm plain" target="_blank" rel="noopener" href="<?= h(url($frontPage[$t])) ?>?id=<?= (int)$r['id'] ?>">보기</a><?php endif; ?>
        <?php if (!$readonly): ?><a class="btn sm ghost" href="<?= h($self) ?>&amp;a=edit&amp;id=<?= (int)$r['id'] ?>">수정</a><?php endif; ?>
        <form method="post" action="<?= h($self) ?>" data-confirm="정말 삭제할까요? 되돌릴 수 없어요." class="inl">
          <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn sm danger" type="submit">삭제</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="help" data-list-empty hidden style="padding:14px 4px">검색 결과가 없어요.</p>
<?php endif; ?>
<?php admin_foot();
