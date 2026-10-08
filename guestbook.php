<?php
/** 방명록: 박스형 목록 + 작성 폼. 비밀글(관리자만 보임), 비밀번호로 본인 글 수정/삭제, 관리자 답글 지원 */
require __DIR__ . '/inc/core.php';
require __DIR__ . '/inc/layout.php';
board_guard('guestbook');

$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$per = 15;
$total = (int)val('SELECT COUNT(*) FROM guestbook WHERE ' . vis_sql());
list($off, $pages, $page) = paginate($total, $per, $page);
$items = rows('SELECT * FROM guestbook WHERE ' . vis_sql() . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)$off);
$canWrite = is_admin() || S('gb_open') === '1';

view_start(['title' => S('m_guestbook'), 'page' => 'guestbook', 'menu' => 6, 'bg' => 'guestbook']);
$hex = '/^#[0-9a-fA-F]{6}$/';
$gbVars = '';
if (preg_match($hex, (string)S('gb_card_color'))) {
    $op = min(100, max(10, (int)S('gb_card_opacity', 68))) / 100;
    $gbVars .= '--gb-bg:' . hex_rgba(S('gb_card_color'), $op) . ';';
}
if (preg_match($hex, (string)S('gb_text_color'))) $gbVars .= '--gb-ink:' . S('gb_text_color') . ';';
if (preg_match($hex, (string)S('gb_line_color'))) $gbVars .= '--gb-line:' . S('gb_line_color') . ';';
if (preg_match($hex, (string)S('gb_reply_color'))) {
    $rc = S('gb_reply_color');
    $rop = min(60, max(5, (int)S('gb_reply_opacity', 12))) / 100;
    $lum = (hexdec(substr($rc, 1, 2)) * 0.299 + hexdec(substr($rc, 3, 2)) * 0.587 + hexdec(substr($rc, 5, 2)) * 0.114) / 255;
    $gbVars .= '--gb-rp-bg:' . hex_rgba($rc, $rop) . ';--gb-rp-line:' . hex_rgba($rc, 0.45) . ';--gb-rp-ink:' . $rc . ';--gb-rp-tagink:' . ($lum > 0.6 ? '#141414' : '#ffffff') . ';';
}
?>
<section class="gb" style="<?= h($gbVars) ?>">
  <header class="pg-head"><h1 class="sr-only"><?= h(S('m_guestbook')) ?></h1><div class="pg-count">총 <?= $total ?>개</div></header>

  <?php if ($canWrite): ?>
  <form class="gb-form" data-gb>
    <?php if (S('gb_notice') !== ''): ?><p class="gb-note"><?= h(S('gb_notice')) ?></p><?php endif; ?>
    <textarea class="gb-ta" name="message" rows="3" maxlength="500" placeholder="글 입력..." required></textarea>
    <div class="gb-foot">
      <input type="text" name="name" maxlength="20" placeholder="이름" value="<?= is_admin() ? h(admin_name()) : '' ?>" required>
      <input type="password" name="pw" maxlength="50" placeholder="비밀번호 (수정·삭제할 때 필요해요)" autocomplete="new-password">
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
      <label class="gb-secret"><input type="checkbox" name="secret" value="1"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg>비밀글</label>
      <span class="gb-err" role="alert"></span>
      <button class="btn gb-submit" type="submit">등록</button>
    </div>
  </form>
  <?php else: ?>
    <p class="empty">지금은 관리자만 방명록을 남길 수 있어요.</p>
  <?php endif; ?>

  <?php if (!$items): ?>
    <p class="empty">아직 남겨진 글이 없어요.</p>
  <?php else: ?>
  <ul class="gb-list">
    <?php foreach ($items as $r):
        $isSecret = $r['visibility'] === 'admin';
        $hasReply = trim((string)$r['reply']) !== '';
        try { $gbReplies = rows('SELECT * FROM guestbook_replies WHERE gb_id=? ORDER BY created_at ASC, id ASC', [$r['id']]); } catch (Exception $e) { $gbReplies = []; }
        $replyCount = ($hasReply ? 1 : 0) + count($gbReplies);
    ?>
      <li data-gb-item data-id="<?= (int)$r['id'] ?>">
        <div class="gb-view">
          <div class="gb-h">
            <b class="gb-n"><?= h($r['name']) ?></b>
            <time><?= h(date('Y.m.d H:i', strtotime($r['created_at']))) ?></time>
            <?php if ($isSecret): ?><span class="gb-lock" title="비밀글"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg></span><?php endif; ?>
            <div class="gb-more-wrap">
              <button type="button" class="gb-more" data-gb-menu aria-label="더보기">···</button>
              <div class="gb-menu">
                <button type="button" data-gb-edit-open>수정</button>
                <button type="button" data-gb-del-open>삭제</button>
              </div>
            </div>
          </div>
          <p class="gb-msg"><?= nl2br_h($r['message']) ?></p>
          <?php if ($replyCount > 0): $rOpen = S('gb_reply_open') === '1'; ?>
          <button type="button" class="gb-rtoggle <?= $rOpen ? 'open' : '' ?>" data-gb-rtoggle aria-expanded="<?= $rOpen ? 'true' : 'false' ?>"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"></path></svg>답글 <?= $replyCount ?>개</button>
          <div class="gb-reply-list" <?= $rOpen ? '' : 'hidden' ?>>
            <?php if ($hasReply): ?>
            <div class="gb-reply">
              <div class="gb-h"><b class="gb-n"><?= h(trim((string)$r['reply_by']) !== '' ? $r['reply_by'] : '주인장') ?></b><span class="gb-tag">답글</span><?php if ($r['reply_at'] !== ''): ?><time><?= h(date('Y.m.d H:i', strtotime($r['reply_at']))) ?></time><?php endif; ?>
                <?php if (is_admin()): ?><div class="gb-more-wrap"><button type="button" class="gb-more" data-gb-menu aria-label="더보기">···</button><div class="gb-menu"><button type="button" data-gb-reply-open>수정</button></div></div><?php endif; ?>
              </div>
              <p class="gb-msg"><?= nl2br_h($r['reply']) ?></p>
            </div>
            <?php endif; ?>
            <?php foreach ($gbReplies as $rp): $rpAdmin = on1($rp['is_admin']); ?>
            <div class="gb-reply <?= $rpAdmin ? 'gb-reply-owner' : '' ?>" data-gbr-item data-id="<?= (int)$rp['id'] ?>">
              <div class="gb-r-view">
                <div class="gb-h">
                  <b class="gb-n"><?= h($rp['name']) ?></b>
                  <?php if ($rpAdmin): ?><span class="gb-tag">답글</span><?php endif; ?>
                  <time><?= h(date('Y.m.d H:i', strtotime($rp['created_at']))) ?></time>
                  <div class="gb-more-wrap">
                    <button type="button" class="gb-more" data-gbr-menu aria-label="더보기">···</button>
                    <div class="gb-menu">
                      <button type="button" data-gbr-edit-open>수정</button>
                      <button type="button" data-gbr-del-open>삭제</button>
                    </div>
                  </div>
                </div>
                <p class="gb-msg"><?= nl2br_h($rp['message']) ?></p>
              </div>
              <form class="gb-r-editform" data-gbr-editform hidden>
                <textarea name="message" rows="2" maxlength="500"><?= h($rp['message']) ?></textarea>
                <div class="gb-foot">
                  <?php if (!is_admin()): ?><input type="password" name="pw" placeholder="비밀번호" required><?php endif; ?>
                  <span class="gb-err" role="alert"></span>
                  <button type="button" class="btn sm" data-gbr-cancel>취소</button>
                  <button class="btn sm gb-submit" type="submit">저장</button>
                </div>
              </form>
              <form class="gb-r-delform" data-gbr-delform hidden>
                <?php if (!is_admin()): ?><input type="password" name="pw" placeholder="비밀번호" required><?php endif; ?>
                <span class="gb-err" role="alert"></span>
                <button type="button" class="btn sm" data-gbr-cancel>취소</button>
                <button class="btn sm gb-submit-del" type="submit">삭제 확인</button>
              </form>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="gb-adminrow">
            <?php if (is_admin() && !$hasReply): ?><button type="button" class="gb-replybtn" data-gb-reply-open>주인장 답글 달기</button><?php endif; ?>
            <button type="button" class="gb-replybtn" data-gbr-add-open>답글 남기기</button>
          </div>
        </div>

        <form class="gb-editform" data-gb-editform hidden>
          <textarea name="message" rows="3" maxlength="500"><?= h($r['message']) ?></textarea>
          <div class="gb-foot">
            <?php if (!is_admin()): ?><input type="password" name="pw" placeholder="비밀번호" required><?php endif; ?>
            <label class="gb-secret"><input type="checkbox" name="secret" value="1" <?= $isSecret ? 'checked' : '' ?>><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="9" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg>비밀글</label>
            <span class="gb-err" role="alert"></span>
            <button type="button" class="btn sm" data-gb-cancel>취소</button>
            <button class="btn sm gb-submit" type="submit">저장</button>
          </div>
        </form>

        <form class="gb-delform" data-gb-delform hidden>
          <?php if (!is_admin()): ?><input type="password" name="pw" placeholder="비밀번호" required><?php endif; ?>
          <span class="gb-err" role="alert"></span>
          <button type="button" class="btn sm" data-gb-cancel>취소</button>
          <button class="btn sm gb-submit-del" type="submit">삭제 확인</button>
        </form>

        <?php if (is_admin()): ?>
        <form class="gb-replyform" data-gb-replyform hidden>
          <textarea name="reply" rows="2" maxlength="500" placeholder="답글을 입력하세요"><?= h($r['reply']) ?></textarea>
          <div class="gb-foot">
            <span class="gb-err" role="alert"></span>
            <button type="button" class="btn sm" data-gb-cancel>취소</button>
            <button class="btn sm gb-submit" type="submit">답글 저장</button>
          </div>
        </form>
        <?php endif; ?>

        <form class="gb-r-addform" data-gbr-addform hidden>
          <textarea name="message" rows="2" maxlength="500" placeholder="답글을 입력하세요" required></textarea>
          <div class="gb-foot">
            <?php if (!is_admin()): ?>
            <input type="text" name="name" maxlength="20" placeholder="이름" required>
            <input type="password" name="pw" maxlength="50" placeholder="비밀번호 (선택)" autocomplete="new-password">
            <?php endif; ?>
            <span class="gb-err" role="alert"></span>
            <button type="button" class="btn sm" data-gbr-add-cancel>취소</button>
            <button class="btn sm gb-submit" type="submit">등록</button>
          </div>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
  <?= pager($page, $pages) ?>
  <?php endif; ?>
</section>
<?php
view_end();
