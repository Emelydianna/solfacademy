<?php $iniciales = mb_strtoupper(mb_substr((string) $instructor['nombre'], 0, 1) . mb_substr((string) $instructor['apellido'], 0, 1)); ?>
<div class="<?= htmlspecialchars($claseAvatar ?? 'instructor-photo', ENT_QUOTES) ?> mx-auto mb-3">
    <?php if (!empty($instructor['foto'])): ?>
        <img src="/instructores/<?= (int) $instructor['id_instructor'] ?>/foto" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
        <span hidden><?= htmlspecialchars($iniciales) ?></span>
    <?php else: ?><?= htmlspecialchars($iniciales) ?><?php endif; ?>
</div>
