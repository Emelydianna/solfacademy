<?php
$urlPagina = static function (int $p) use ($base, $listado): string {
    return $base . '?' . http_build_query(array_filter(array_merge($listado['filtros'], ['pagina' => $p]), static fn ($v): bool => $v !== ''));
};
?>
<div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-3 border-top admin-border">
    <span class="small admin-muted">Mostrando <?= (int) $listado['desde'] ?>–<?= (int) $listado['hasta'] ?> de <?= (int) $listado['total'] ?> registros</span>
    <nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $listado['pagina'] === 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $e($urlPagina(max(1, $listado['pagina'] - 1))) ?>" <?= $listado['pagina'] === 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Anterior</a></li>
        <?php for ($p = max(1, $listado['pagina'] - 2); $p <= min($listado['paginas'], $listado['pagina'] + 2); $p++): ?>
            <li class="page-item <?= $p === $listado['pagina'] ? 'active' : '' ?>"><a class="page-link" href="<?= $e($urlPagina($p)) ?>" <?= $p === $listado['pagina'] ? 'aria-current="page"' : '' ?>><?= $p ?></a></li>
        <?php endfor; ?>
        <li class="page-item <?= $listado['pagina'] === $listado['paginas'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= $e($urlPagina(min($listado['paginas'], $listado['pagina'] + 1))) ?>" <?= $listado['pagina'] === $listado['paginas'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Siguiente</a></li>
    </ul></nav>
</div>
