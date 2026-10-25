<?php
$alumnoId = $alumnoId ?? null;
?>
<div class="mb-4">
    <h1 class="portal-title mb-1">Mensajes</h1>
    <p class="portal-subtitle mb-0">Comunicación con estudiantes</p>
</div>

<div class="portal-card p-4">
    <?php if ($alumnoId): ?>
        <p class="mb-0">Redactar mensaje al alumno #<?= htmlspecialchars((string)$alumnoId) ?> (funcionalidad en desarrollo).</p>
    <?php else: ?>
        <p class="mb-0">Bandeja de mensajes (funcionalidad en desarrollo).</p>
    <?php endif; ?>
</div>
