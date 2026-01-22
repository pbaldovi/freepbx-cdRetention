<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Invocamos el framework de forma segura
$fpbx = \FreePBX::create();
$msg = $fpbx->Cdretention->message;

$current_days = $fpbx->Cdretention->getConfig('purge_days');
$current_days = ($current_days !== null) ? $current_days : 30;
?>

<div class="container-fluid">
    <h1><i class="fa fa-history"></i> Retención de CDR</h1>
    <?php echo $msg; ?>

    <div class="row">
        <div class="col-sm-6">
            <form method="post" class="panel panel-default">
                <div class="panel-heading">Días de Historial</div>
                <div class="panel-body">
                    <input type="hidden" name="action" value="save">
                    <div class="form-group">
                        <label>Mantener registros por (días):</label>
                        <input type="number" name="purge_days" class="form-control" value="<?php echo $current_days; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Actualizar Configuración</button>
                </div>
            </form>
        </div>

        <div class="col-sm-6">
            <div class="panel panel-danger">
                <div class="panel-heading">Ejecución Manual</div>
                <div class="panel-body">
                    <p>Borrar registros más antiguos de <b><?php echo $current_days; ?> días</b> ahora mismo:</p>
                    <form method="post">
                        <input type="hidden" name="action" value="purge_now">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('¿Seguro que quieres borrar los registros antiguos?')">
                            <i class="fa fa-trash"></i> Purgar Ahora
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>