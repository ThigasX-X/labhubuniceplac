</div>

<div class="modal fade" id="modalFoto" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-camera me-2"></i>Alterar Foto de Perfil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="<?= htmlspecialchars($fotoAtual) ?>" class="rounded-circle mb-3 border object-fit-cover" width="100" height="100" alt="Foto atual">
                <form method="POST" enctype="multipart/form-data" action="index.php?page=<?= htmlspecialchars($painelRota) ?>">
                    <input type="file" class="form-control mb-3" name="nova_foto" accept="image/jpeg,image/png,image/webp" required>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold">Salvar Foto</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<?php if (!empty($painelScriptsExtra)): ?>
<script><?= $painelScriptsExtra ?></script>
<?php endif; ?>
</body>
</html>
