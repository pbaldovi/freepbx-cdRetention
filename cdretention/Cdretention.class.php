<?php
namespace FreePBX\modules;

class Cdretention implements \BMO {
    public function __construct($FreePBX = null) {
        if ($FreePBX == null) {
            throw new \Exception("No FreePBX Object");
        }
        $this->FreePBX = $FreePBX;
        // El CDR suele estar en una base de datos distinta (asteriskcdrdb)
        $this->db = $this->FreePBX->Database; 
    }

    public $message = "";

    public function doConfigPageInit($page) {
        $fpbx = \FreePBX::create();
        if (isset($_POST['action']) && $_POST['action'] == 'save') {
            $days = intval($_POST['purge_days']);
            $this->setConfig('purge_days', $days);
            $this->message = '<div class="alert alert-success">Configuración guardada correctamente.</div>';
        }

        if (isset($_POST['action']) && $_POST['action'] == 'purge_now') {
            $count = $this->purgeOldRecords();
            $this->message = '<div class="alert alert-warning">Purga completada: Se eliminaron ' . $count . ' registros.</div>';
        }
    }

    public function getConfig($key) {
        $fpbx = \FreePBX::create();
        $sql = "SELECT value FROM cdretention_settings WHERE `key` = :key";
        $stmt = $fpbx->Database->prepare($sql);
        $stmt->execute(array(':key' => $key));
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $res ? $res['value'] : null;
    }

    public function setConfig($key, $value) {
        $fpbx = \FreePBX::create();
        $sql = "INSERT INTO cdretention_settings (`key`, `value`) VALUES (:key, :value) ON DUPLICATE KEY UPDATE `value` = :value";
        $stmt = $fpbx->Database->prepare($sql);
        $stmt->execute(array(':key' => $key, ':value' => $value));
    }

    public function install() {
        $fpbx = \FreePBX::create();
        // Crear tabla de configuración local si no existe
        $sql = "CREATE TABLE IF NOT EXISTS cdretention_settings (
            `key` VARCHAR(50) PRIMARY KEY,
            `value` VARCHAR(255)
        )";
        $fpbx->Database->query($sql);

        // Programar tarea diaria a las 01:00 AM
        $fpbx->Cron->add("0 1 * * * /usr/sbin/fwconsole cdretention purge");


        // 2. Establecer el valor por defecto si no existe
        if ($this->getConfig('purge_days') === null) {
            $this->setConfig('purge_days', 30);
        }

    }

    public function uninstall() {
        $fpbx = \FreePBX::create();
        $fpbx->Cron->remove("/usr/sbin/fwconsole cdretention purge");
        $fpbx->Database->query("DROP TABLE IF EXISTS cdretention_settings");
    }

    public function purgeOldRecords($days = null) {
        $fpbx = \FreePBX::create();
        if ($days === null) {
            $days = $this->getConfig('purge_days');
        }
        
        if (!is_numeric($days) || $days < 1) return 0;

        $totalDeleted = 0;
        $batchSize = 5000;

        // 1. Purga de CDR
        do {
            $sql = "DELETE FROM asteriskcdrdb.cdr WHERE calldate < DATE_SUB(NOW(), INTERVAL :days DAY) LIMIT :limit";
            $stmt = $fpbx->Database->prepare($sql);
            $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
            $stmt->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
            $stmt->execute();
            
            $count = $stmt->rowCount();
            $totalDeleted += $count;
            
            if ($count > 0) {
                usleep(50000); // 50ms pause
            }
        } while ($count > 0);

        // 2. Purga de CEL
        do {
            $sql = "DELETE FROM asteriskcdrdb.cel WHERE eventtime < DATE_SUB(NOW(), INTERVAL :days DAY) LIMIT :limit";
            $stmt = $fpbx->Database->prepare($sql);
            $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
            $stmt->bindValue(':limit', $batchSize, \PDO::PARAM_INT);
            $stmt->execute();
            
            $count = $stmt->rowCount();
            $totalDeleted += $count; // Sumamos también los de CEL al total
            
            if ($count > 0) {
                usleep(50000); // 50ms pause
            }
        } while ($count > 0);
        
        return $totalDeleted;
    }

    // Permite ejecutar 'fwconsole cdretention purge' desde SSH
    public function getConsoleCommands() {
        return array(
            'purge' => array(
                'description' => 'Purga manual de CDR',
                'help' => 'Borra registros anteriores a los días configurados',
                'callback' => function($output) {
                    $count = $this->purgeOldRecords();
                    $output->writeln("Éxito: Se eliminaron $count registros.");
                }
            )
        );
    }
}
