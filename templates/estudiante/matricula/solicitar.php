<?php
use App\Application\Formato\Dinero;
use App\Application\Security\Csrf;
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$g = $datos['grupo']; $r = $datos['requisito']; $est = $datos['estudiante'];
$edad = $est['fecha_nacimiento'] ? (new DateTimeImmutable($est['fecha_nacimiento']))->diff(new DateTimeImmutable('today'))->y : null;
?>
<div>
    <div class="mb-4"><h1 class="portal-title mb-1">Solicitar Matrícula</h1><p class="portal-subtitle mb-0">Revisa los detalles y confirma tu solicitud pendiente.</p></div>
    <?php if ($errores): ?><div role="alert" class="portal-alert portal-alert--peligro p-3 mb-4"><?= $e($errores['general']) ?></div><?php endif; ?>
    <?php if ($datos['vigente']): ?><div class="portal-alert portal-alert--peligro p-3 mb-4">Ya tienes una matrícula vigente para este curso.</div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-8"><section class="portal-card p-4">
            <h2 class="portal-card-title">Grupo seleccionado</h2>
            <p><strong><?= $e($g['curso']) ?></strong> · <?= $e($g['nivel']) ?></p>
            <p><?= $e($g['instructor']) ?> · <?= $e($g['aula']) ?></p><p>Inicio: <?= $e($g['fecha_inicio']) ?><?= $g['fecha_fin'] ? ' · Fin: ' . $e($g['fecha_fin']) : '' ?></p>
            <?php foreach ($datos['horarios'] as $h): ?><p class="small text-secondary mb-1"><?= $e($h['dia_semana'] . ' ' . $h['hora_inicio'] . '–' . $h['hora_fin']) ?></p><?php endforeach; ?>
            <p>Lugares disponibles al preparar la solicitud: <strong><?= (int) $datos['lugares'] ?></strong></p>
            <p class="small text-secondary">La disponibilidad se vuelve a comprobar al confirmar.</p>
        </section></div>
        <div class="col-lg-4"><section class="portal-card p-4">
            <h2 class="portal-card-title">Requisitos y confirmación</h2>
            <p>Edad registrada: <strong><?= $edad !== null ? (int) $edad . ' años' : 'Sin fecha de nacimiento registrada' ?></strong></p>
            <?php if ($r): ?><ul class="small text-secondary"><li><?= $e($r['nivel_minimo'] ?: 'Nivel mínimo: revisión manual') ?></li><li><?= $e($r['conocimientos_previos'] ?: 'Conocimientos previos: revisión manual') ?></li><li>Instrumento: <?= $r['necesita_instrumento'] ? ($r['puede_alquilar'] ? 'propio o alquiler' : 'propio') : 'no requerido' ?></li><li>Rango de edad: <?= (int) $r['edad_minima'] ?>–<?= (int) $r['edad_maxima'] ?> años</li></ul><?php else: ?><p class="small text-secondary">Este curso no tiene requisitos configurados; la revisión seguirá siendo administrativa.</p><?php endif; ?>
            <p>Precio congelado al solicitar: <strong><?= Dinero::monto((float) $g['precio']) ?></strong>. Solicitar matrícula no registra ningún pago.</p>
            <?php if (!$datos['vigente'] && $datos['lugares'] > 0 && $g['estado'] === 'activo'): ?>
                <form method="post" action="/estudiante/matricular/<?= (int) $g['id_grupo'] ?>" id="form-academico">
                    <?= Csrf::campo() ?>
                    <div class="form-check mb-3"><input id="confirmar_requisitos" name="confirmar_requisitos" value="1" type="checkbox" class="form-check-input" required><label for="confirmar_requisitos" class="form-check-label small">Confirmo que revisé los requisitos, el horario y la información del grupo.</label></div>
                    <button class="btn btn-accent w-100 fw-semibold">ENVIAR SOLICITUD</button>
                </form>
            <?php else: ?><p class="small text-secondary">Esta solicitud no está disponible en este momento.</p><?php endif; ?>
        </section></div>
    </div>
</div>
