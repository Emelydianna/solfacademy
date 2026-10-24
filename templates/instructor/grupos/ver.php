<?php
$grupo = $grupo ?? [];
$alumnos = $alumnos ?? [];
?>
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="/instructor/grupos" style="color:#94a3b8;">Mis Grupos</a></li>
            <li class="breadcrumb-item active" style="color:#fff;"><?= htmlspecialchars($grupo['curso'] ?? '') ?></li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="portal-title mb-0"><?= htmlspecialchars($grupo['curso'] ?? 'Grupo') ?></h1>
        <button class="btn btn-outline-warning"><i class="bi bi-download me-1"></i>Exportar Lista</button>
    </div>
</div>

<div class="portal-card p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="portal-card-title mb-0">Estudiantes Inscritos (<?= count($alumnos) ?> Alumnos)</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-borderless align-middle mb-0">
            <thead>
                <tr style="border-bottom:1px solid var(--portal-border);">
                    <th>ALUMNO</th>
                    <th>EMAIL</th>
                    <th>TELÉFONO</th>
                    <th>INSCRIPCIÓN</th>
                    <th>ASISTENCIA</th>
                    <th>PROMEDIO</th>
                    <th>ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alumnos as $a): ?>
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.03);">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="portal-avatar" style="width:36px;height:36px;font-size:0.8rem;"><?= htmlspecialchars(mb_strtoupper(mb_substr($a['nombre'],0,1).mb_substr($a['apellido'],0,1))) ?></div>
                                <span class="fw-semibold"><?= htmlspecialchars($a['nombre'].' '.$a['apellido']) ?></span>
                            </div>
                        </td>
                        <td style="color:#94a3b8;"><?= htmlspecialchars($a['email']) ?></td>
                        <td style="color:#94a3b8;"><?= htmlspecialchars($a['telefono'] ?: '—') ?></td>
                        <td style="color:#94a3b8;"><?= $a['inscripcion_fecha'] ? date('d/M/Y', strtotime($a['inscripcion_fecha'])) : '—' ?></td>
                        <td class="fw-bold"><?= (int)$a['asistencia_pct'] ?>%</td>
                        <td class="fw-bold" style="color:#ffc107;"><?= number_format((float)$a['promedio'], 1) ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-secondary">Perfil</button>
                                <button class="btn btn-sm btn-outline-secondary">Mensaje</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
