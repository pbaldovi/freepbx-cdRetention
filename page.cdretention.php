<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$msg = "";

// 1. Acción: Guardar Configuración
if (isset($_POST['action']) && $_POST['action'] == 'save') {
    $days = intval($_POST['purge_days']);
    $FreePBX->Config->set_conf_setting('CDRPURGE_DAYS', $days);
    $msg = '<div class="alert alert-success">Configuración guardada.</div>';
}

// 2. Acción: Purgar Ahora
if (isset($_POST['action']) && $_POST['action'] == 'purge_now') {
    $count = $FreePBX->Cdretention->purgeOldRecords();
    $msg = '<div class="alert alert-warning">Purga manual completada: ' . $count . ' registros eliminados.</div>';
}

$current_days = $FreePBX->Config->get_conf_setting('CDRPURGE_DAYS');
$current_days = ($current_days !== null) ? $current_days : 30;
?>

<div class="container-fluid">
    <h1>Purgador Automático de CDR</h1>
    <?php echo $msg; ?>

    <div class="row">
        <div class="col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading">Configuración de Retención</div>
                <div class="panel-body">
                    <form method="post">
                        <input type="hidden" name="action" value="save">
                        <div class="form-group">
                            <label>Días de historial a mantener:</label>
                            <input type="number" name="purge_days" class="form-control" value="<?php echo $current_days; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar Días</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-sm-6">
            <div class="panel panel-danger">
                <div class="panel-heading">Acciones Manuales</div>
                <div class="panel-body">
                    <p>Si deseas limpiar la base de datos inmediatamente usando los días configurados:</p>
                    <form method="post">
                        <input type="hidden" name="action" value="purge_now">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('¿Estás seguro de querer borrar los datos antiguos ahora?')">
                            <i class="fa fa-trash"></i> Purgar Ahora
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>