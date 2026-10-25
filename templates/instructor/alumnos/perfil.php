<?php
$idEstudiante = $idEstudiante ?? 0;
?>
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="/instructor/grupos" style="color:#94a3b8;">Mis Grupos</a></li>
            <li class="breadcrumb-item active" style="color:#fff;">Perfil Académico</li>
        </ol>
    </nav>
    <h1 class="portal-title mb-0">Perfil Académico del Alumno</h1>
    <p class="portal-subtitle">Ficha académica, asistencias, calificaciones y progreso.</p>
</div>

<div class="portal-card p-4">
    <p class="mb-0">Vista en construcción. Identificador de alumno: <?= (int)$idEstudiante ?></p>
</div>
